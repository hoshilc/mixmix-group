<?php
/* MixMix — funciones comunes del servidor de la tienda.
   No se sirve directamente (ver api/.htaccess). Configuración y archivos de pago viven FUERA de
   public_html, en la carpeta "privado" del hosting (ver api/config.sample.php). */

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail($error, $code = 400) { json_out(array('ok' => false, 'error' => $error), $code); }
function ok($data = array()) { json_out(array_merge(array('ok' => true), $data)); }

/* carpeta "privado": la primera que exista subiendo desde esta carpeta */
function private_dir() {
    static $dir = null;
    if ($dir !== null) return $dir;
    $p = __DIR__;
    for ($i = 0; $i < 8; $i++) {
        $p = dirname($p);
        if (is_dir($p . '/privado')) { $dir = $p . '/privado'; return $dir; }
    }
    $dir = '';
    return $dir;
}

function config() {
    static $c = null;
    if ($c !== null) return $c;
    $dir = private_dir();
    $f = $dir . '/config.php';
    if ($dir === '' || !is_file($f)) fail('config_missing', 500);
    $c = require $f;
    return $c;
}

function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = config();
    $d = $c['db'];
    $pdo = new PDO('mysql:host=' . $d['host'] . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['pass'], array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
    return $pdo;
}

/* ---------- peticiones ---------- */
function require_method($m) {
    if ($_SERVER['REQUEST_METHOD'] !== $m) fail('method_not_allowed', 405);
}
/* el cuerpo debe ser JSON y venir del mismo sitio (protege contra formularios de otros sitios) */
function read_json() {
    $type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($type, 'application/json') === false) fail('bad_content_type', 415);
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $o = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        if ($o !== $_SERVER['HTTP_HOST'] && $o . ':' . (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '') !== $_SERVER['HTTP_HOST']) fail('bad_origin', 403);
    }
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : array();
}

/* ---------- sesión ---------- */
function start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $dir = private_dir();
    if ($dir !== '') {
        $sp = $dir . '/sessions';
        if (!is_dir($sp)) @mkdir($sp, 0700, true);
        if (is_dir($sp) && is_writable($sp)) session_save_path($sp);
    }
    ini_set('session.gc_maxlifetime', '2592000');
    session_name('mmsid');
    session_set_cookie_params(array('lifetime' => 2592000, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    session_start();
}
function current_user() {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT id, email, name FROM users WHERE id = ?');
    $st->execute(array($_SESSION['uid']));
    $u = $st->fetch();
    return $u ? $u : null;
}
function require_user() {
    $u = current_user();
    if (!$u) fail('not_logged_in', 401);
    return $u;
}

/* ---------- catálogo (la misma fuente que usa el sitio: js/catalog.js) ---------- */
function catalog() {
    static $c = null;
    if ($c !== null) return $c;
    $t = file_get_contents(dirname(__DIR__) . '/js/catalog.js');
    $s = strpos($t, 'window.MM_CATALOG');
    $a = strpos($t, '{', $s);
    $b = strrpos($t, '}');
    $c = json_decode(substr($t, $a, $b - $a + 1), true);
    if (!is_array($c)) fail('catalog_invalid', 500);
    $c['byId'] = array();
    foreach (array('items', 'bundles', 'packages') as $k) {
        foreach ($c[$k] as $x) $c['byId'][$x['id']] = $x;
    }
    return $c;
}
/* documentos (items) que trae un producto */
function contents_of($p) {
    $cat = catalog();
    if (isset($p['cat'])) return array($p);
    $out = array();
    if (!empty($p['items'])) {
        foreach ($p['items'] as $id) if (isset($cat['byId'][$id])) $out[] = $cat['byId'][$id];
    } elseif (!empty($p['cats'])) {
        foreach ($cat['items'] as $it) if (in_array($it['cat'], $p['cats'], true)) $out[] = $it;
    }
    return $out;
}

/* ---------- precio de manuales por volumen (igual que js/mm.js) ---------- */
function volume_cost($n, $tiers) {
    if ($n <= 0) return 0;
    usort($tiers, function ($a, $b) { return $a['n'] - $b['n']; });
    $maxT = $tiers[count($tiers) - 1]['n'];
    $lim = $n + $maxT;
    $INF = PHP_INT_MAX / 4;
    $dp = array(0);
    for ($k = 1; $k <= $lim; $k++) {
        $dp[$k] = $INF;
        foreach ($tiers as $t) {
            if ($k >= $t['n'] && $dp[$k - $t['n']] + $t['price'] < $dp[$k]) $dp[$k] = $dp[$k - $t['n']] + $t['price'];
        }
    }
    $best = $dp[$n];
    for ($k = $n; $k <= $lim; $k++) if ($dp[$k] < $best) $best = $dp[$k];
    return (int)$best;
}

/* ids que ya compró el usuario (incluye lo que trae cada paquete) */
function paid_orders($userId) {
    $st = db()->prepare("SELECT ref, lines_json, discount, total, paid_at FROM orders WHERE user_id = ? AND status = 'paid' ORDER BY paid_at DESC, id DESC");
    $st->execute(array($userId));
    return $st->fetchAll();
}
function owned_item_ids($userId) {
    $cat = catalog();
    $own = array();
    foreach (paid_orders($userId) as $o) {
        $lines = json_decode($o['lines_json'], true);
        if (!is_array($lines)) continue;
        foreach ($lines as $l) {
            if (!isset($cat['byId'][$l['id']])) continue;
            foreach (contents_of($cat['byId'][$l['id']]) as $it) $own[$it['id']] = true;
        }
    }
    return $own;
}
function owned_products($userId) {
    $cat = catalog();
    $out = array();
    foreach (paid_orders($userId) as $o) {
        $lines = json_decode($o['lines_json'], true);
        if (!is_array($lines)) continue;
        foreach ($lines as $l) if (isset($cat['byId'][$l['id']])) $out[$l['id']] = $cat['byId'][$l['id']];
    }
    return $out;
}

/* precio del carrito, calculado SIEMPRE en el servidor */
function price_cart($ids, $ownedItems) {
    $cat = catalog();
    $tiers = isset($cat['volume']['manuales']) ? $cat['volume']['manuales'] : array();
    $lines = array();
    $man = 0; $manBase = 0; $other = 0;
    foreach (array_values(array_unique($ids)) as $id) {
        if (!is_string($id) || !isset($cat['byId'][$id])) continue;
        $p = $cat['byId'][$id];
        if (isset($p['choose'])) continue;                      /* el paquete "a elegir" no se vende directo: se vende su variante */
        $docs = contents_of($p);
        $allOwned = count($docs) > 0;
        foreach ($docs as $d) if (empty($ownedItems[$d['id']])) { $allOwned = false; break; }
        if ($allOwned) continue;                                /* ya lo tiene: no se cobra dos veces */
        $lines[] = array('id' => $id, 'title' => $p['title'], 'price' => (int)$p['price']);
        if (isset($p['cat']) && $p['cat'] === 'manuales') { $man++; $manBase += (int)$p['price']; }
        else $other += (int)$p['price'];
    }
    $manCost = $man > 0 ? min($manBase, $tiers ? volume_cost($man, $tiers) : $manBase) : 0;
    return array('lines' => $lines, 'manuals' => $man, 'manualsBase' => $manBase, 'manualsCost' => $manCost,
                 'discount' => $manBase - $manCost, 'other' => $other, 'total' => $other + $manCost);
}

/* ---------- Stripe (sin librería: API REST con cURL) ---------- */
function stripe_request($method, $path, $params = array()) {
    $c = config();
    $ch = curl_init('https://api.stripe.com/v1' . $path . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $c['stripe_secret'] . ':',
        CURLOPT_TIMEOUT => 30,
    );
    if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($params); }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) { error_log('stripe curl: ' . $err); fail('stripe_unreachable', 502); }
    $d = json_decode($body, true);
    if ($code >= 400 || !is_array($d)) { error_log('stripe http ' . $code . ': ' . $body); fail('stripe_error', 502); }
    return $d;
}
function verify_stripe_signature($payload, $header, $secret, $tolerance = 300) {
    $t = null; $sigs = array();
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 't') $t = $kv[1];
        elseif ($kv[0] === 'v1') $sigs[] = $kv[1];
    }
    if (!$t || !$sigs || abs(time() - (int)$t) > $tolerance) return false;
    $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
    return false;
}
function mark_paid($sessionId) {
    $st = db()->prepare("UPDATE orders SET status = 'paid', paid_at = UTC_TIMESTAMP() WHERE stripe_session_id = ? AND status <> 'paid'");
    $st->execute(array($sessionId));
    return $st->rowCount();
}

/* ---------- archivos de pago ---------- */
function nice_name($title, $ext) {
    $n = preg_replace('/[\\\\\/:*?"<>|]+/u', ' - ', $title);
    $n = trim(preg_replace('/\s+/u', ' ', $n));
    if (!preg_match('/mix/i', $n)) $n = 'MixMix - ' . $n;
    return $n . $ext;
}
/* archivos descargables de un producto: ruta del catálogo => nombre para el cliente */
function product_files($p) {
    $out = array();
    if (isset($p['cat'])) {
        if (!empty($p['files'])) foreach ($p['files'] as $f) $out[$f['file']] = nice_name($f['t'], '.pdf');
        elseif (!empty($p['file'])) $out[$p['file']] = nice_name($p['title'], '.pdf');
    } else {
        if (!empty($p['zips'])) foreach ($p['zips'] as $z) $out[$z['file']] = nice_name($z['t'], '.zip');
        if (!empty($p['file'])) $out[$p['file']] = nice_name($p['title'], '.zip');
        foreach (contents_of($p) as $it) foreach (product_files($it) as $k => $v) if (!isset($out[$k])) $out[$k] = $v;
    }
    return $out;
}
