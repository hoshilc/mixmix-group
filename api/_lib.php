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
    if ($code >= 400 || !is_array($d)) {
        error_log('stripe http ' . $code . ': ' . $body);
        $e = (is_array($d) && isset($d['error']) && is_array($d['error'])) ? $d['error'] : array();
        $parts = array();
        foreach (array('type', 'code', 'param', 'message') as $k) if (!empty($e[$k])) $parts[] = $k . '=' . $e[$k];
        $detail = preg_replace('/(sk|rk|pk)_(test|live)_[A-Za-z0-9*_]+/', '$1_$2_***', 'http ' . $code . ' ' . implode(' | ', $parts));
        json_out(array('ok' => false, 'error' => 'stripe_error', 'detail' => mb_substr($detail, 0, 400)), 502);
    }
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

/* ---------- compras sin cuenta (invitado) ----------
   El comprador sin cuenta recibe un enlace con un código que solo el servidor puede calcular
   (HMAC del número de pedido con un secreto de privado/config.php). No se guarda en la base de datos. */
function guest_token($ref) {
    $c = config();
    $key = isset($c['stripe_webhook_secret']) && $c['stripe_webhook_secret'] !== '' ? $c['stripe_webhook_secret'] : $c['stripe_secret'];
    return substr(hash_hmac('sha256', 'guest-order|' . $ref, $key), 0, 48);
}
/* pedido por número + código; null si no coincide */
function guest_order($ref, $token) {
    if (!is_string($ref) || !preg_match('/^MM-[A-F0-9]{8}$/', $ref)) return null;
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/', $token) || !hash_equals(guest_token($ref), $token)) return null;
    $st = db()->prepare('SELECT id, ref, user_id, guest_email, status, lines_json, discount, total, paid_at, mailed_at FROM orders WHERE ref = ?');
    $st->execute(array($ref));
    $o = $st->fetch();
    return $o ? $o : null;
}
function guest_link($ref) {
    $c = config();
    return $c['site_url'] . '/pedido.html?pedido=' . $ref . '&t=' . guest_token($ref);
}
/* productos (del catálogo) de un pedido */
function order_products($o) {
    $cat = catalog();
    $out = array();
    $lines = json_decode($o['lines_json'], true);
    if (is_array($lines)) foreach ($lines as $l) if (isset($cat['byId'][$l['id']])) $out[$l['id']] = $cat['byId'][$l['id']];
    return $out;
}
/* después de confirmar un pago: manda UNA vez el correo de confirmación.
   Compra sin cuenta: con el enlace personal a los archivos. Compra con cuenta: con la liga a «Mis compras». */
function after_paid($sessionId) {
    $pdo = db();
    try {
        $st = $pdo->prepare("SELECT o.id, o.ref, o.user_id, o.guest_email, o.total, o.lines_json, u.email AS user_email, u.name AS user_name FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.stripe_session_id = ? AND o.status = 'paid' AND o.mailed_at IS NULL");
        $st->execute(array($sessionId));
        $o = $st->fetch();
    } catch (PDOException $e) { error_log('after_paid (¿falta migracion-3?): ' . $e->getMessage()); return; }   /* nunca debe romper la confirmación del pago */
    if (!$o) return;
    $to = $o['user_id'] !== null ? $o['user_email'] : $o['guest_email'];
    if (!$to) return;
    $claim = $pdo->prepare('UPDATE orders SET mailed_at = UTC_TIMESTAMP() WHERE id = ? AND mailed_at IS NULL');
    $claim->execute(array($o['id']));
    if ($claim->rowCount() !== 1) return;
    $cfg = config();
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    /* resumen de lo comprado */
    $items = ''; $itemsText = '';
    $cat = catalog();
    $catNames = array();
    foreach ($cat['categories'] as $c) $catNames[$c['id']] = $c['name'];
    $lines = json_decode($o['lines_json'], true);
    if (is_array($lines)) foreach ($lines as $l) {
        if (!isset($l['title'])) continue;
        /* nombre del archivo como lo recibe el cliente y categoría de descargable a la que pertenece */
        $p = isset($l['id']) && isset($cat['byId'][$l['id']]) ? $cat['byId'][$l['id']] : null;
        $name = $l['title']; $kind = '';
        if ($p && isset($p['cat'])) {
            $kind = isset($catNames[$p['cat']]) ? $catNames[$p['cat']] : 'Descargable';
            $fn = array_values(product_files($p));
            if (count($fn) === 1) $name = $fn[0];
            elseif (count($fn) > 1) $kind .= ' · ' . count($fn) . ' archivos';
        } elseif ($p) {
            $n = count(contents_of($p));
            $kind = ($p['id'] === 'catalogo' ? 'Catálogo completo' : 'Paquete') . ($n > 1 ? ' · ' . $n . ' documentos' : '');
        }
        $items .= '<li style="margin:0 0 10px"><strong>' . $esc($name) . '</strong>' .
            ($kind !== '' ? '<br><span style="font-size:13px;color:#555555">' . $esc($kind) . '</span>' : '') . '</li>';
        $itemsText .= '- ' . $name . ($kind !== '' ? ' (' . $kind . ')' : '') . "\n";
    }
    $total = '$' . number_format((int)$o['total']) . ' MXN';
    $isGuest = $o['user_id'] === null;
    $link = $isGuest ? guest_link($o['ref']) : $cfg['site_url'] . '/cuenta.html#compras';
    $btn = $isGuest ? 'Descargar mis archivos' : 'Ir a mis compras';
    $intro = $isGuest ? 'Tu compra está confirmada y tus archivos ya están listos para descargar.' : 'Tu compra está confirmada y tus archivos ya están en tu cuenta, listos para descargar.';
    $foot = $isGuest
        ? 'Guarda este correo: el enlace es personal y es tu acceso a lo que compraste. Si creas una cuenta con este correo en grupomixmix.com, todas tus compras quedan guardadas y podrás descargarlas siempre.'
        : 'Tus compras quedan guardadas en tu cuenta y puedes descargarlas siempre que quieras.';
    /* el folio solo va como dato de referencia, discreto y al final */
    $html = mail_layout('Gracias por tu compra, chef',
        '<p style="margin:0 0 8px">' . $intro . '</p>' .
        mail_button($link, $btn) .
        ($items !== '' ? '<p style="margin:16px 0 8px;font-weight:bold">Lo que compraste</p><ul style="margin:0;padding-left:20px">' . $items . '</ul><p style="margin:8px 0 0">Total pagado: <strong>' . $total . '</strong></p>' : '') .
        '<p style="font-size:13px;color:#555555;margin:20px 0 4px">' . $foot . '</p>' .
        '<p style="font-size:12px;color:#8A8A87;margin:0 0 16px">Pedido ' . $esc($o['ref']) . '</p>');
    $text = "Gracias por tu compra, chef.\n\n" . $intro . "\n" . $link .
        ($itemsText !== '' ? "\n\nLo que compraste:\n" . $itemsText . "Total pagado: " . $total : '') .
        "\n\n" . $foot . "\n\nPedido " . $o['ref'] . "\nGrupo MixMix";
    if (!mail_send($to, 'Tu compra en Grupo MixMix está lista', $html, $text)) {
        error_log('after_paid: no se pudo enviar el correo del pedido ' . $o['ref']);
        $pdo->prepare('UPDATE orders SET mailed_at = NULL WHERE id = ?')->execute(array($o['id']));   /* se reintenta en la siguiente consulta */
    }
}
/* una cuenta con correo VERIFICADO (Google o enlace de recuperación) hereda sus compras como invitado */
function adopt_guest_orders($userId, $email) {
    try {
        db()->prepare("UPDATE orders SET user_id = ? WHERE user_id IS NULL AND guest_email = ?")->execute(array($userId, strtolower($email)));
    } catch (PDOException $e) { error_log('adopt_guest_orders (¿falta migracion-3?): ' . $e->getMessage()); }   /* nunca debe impedir entrar */
}

/* ---------- límites de uso y limpieza ---------- */
function client_ip() { return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '0'; }
/* ¿puede esta clave hacer otra acción? Máximo $max veces cada $windowSec segundos. Cuenta en archivos de privado/ratelimit
   (sin base de datos). Si no se puede escribir ahí, no limita: nunca debe romper la tienda. */
function rate_limit($key, $max, $windowSec) {
    $base = private_dir();
    if ($base === '') return true;
    $d = $base . '/ratelimit';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    if (!is_dir($d) || !is_writable($d)) return true;
    $fh = @fopen($d . '/' . hash('sha256', $key) . '.json', 'c+');
    if (!$fh) return true;
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $hits = $raw ? json_decode($raw, true) : array();
    if (!is_array($hits)) $hits = array();
    $now = time();
    $hits = array_values(array_filter($hits, function ($t) use ($now, $windowSec) { return $t > $now - $windowSec; }));
    $allowed = count($hits) < $max;
    if ($allowed) $hits[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    flock($fh, LOCK_UN);
    fclose($fh);
    if (mt_rand(1, 50) === 1) {   /* de vez en cuando se borran los contadores de más de un día */
        foreach ((array)glob($d . '/*.json') as $f) if (@filemtime($f) < $now - 86400) @unlink($f);
    }
    return $allowed;
}
/* pedidos que nunca se pagaron (Stripe cierra el pago a las 24 h): se marcan como vencidos */
function expire_old_pending() {
    try {
        db()->exec("UPDATE orders SET status = 'expired' WHERE status = 'pending' AND created_at < (NOW() - INTERVAL 2 DAY)");
    } catch (PDOException $e) { error_log('expire_old_pending: ' . $e->getMessage()); }
}

/* ---------- archivos de pago ---------- */
/* Carpetas donde pueden estar los archivos: privado/ y cualquier subcarpeta directa (d1, d2, d3...),
   siempre que dentro tengan "descargas/". Así da igual si los zips se extrajeron juntos o por partes. */
function private_roots() {
    $base = private_dir();
    if ($base === '') return array();
    $roots = array();
    if (is_dir($base . '/descargas')) $roots[] = $base;
    $subs = glob($base . '/*', GLOB_ONLYDIR);
    if ($subs) foreach ($subs as $d) {
        if (basename($d) === 'sessions') continue;
        if (is_dir($d . '/descargas')) $roots[] = $d;
    }
    return $roots;
}
/* ruta real de un archivo del catálogo ("descargas/manuales/x.pdf"), o false si no está */
function locate_file($rel) {
    foreach (private_roots() as $root) {
        $dirReal = realpath($root . '/descargas');
        $f = realpath($root . '/' . $rel);
        if ($dirReal && $f && is_file($f) && strpos($f, $dirReal . DIRECTORY_SEPARATOR) === 0) return $f;
    }
    return false;
}
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

/* ---------- plantilla de marca de los correos ----------
   Franja negra con el logo, filete dorado, contenido sobre blanco y pie con los datos de contacto.
   Es una tabla con estilos en línea porque los gestores de correo ignoran casi todo el CSS. */
function mail_button($href, $label) {
    return '<p style="margin:24px 0"><a href="' . $href . '" style="display:inline-block;background:#111110;color:#F0EFF4;padding:14px 26px;text-decoration:none;font-weight:bold;font-size:15px;border-bottom:3px solid #C6A15B">' . $label . '</a></p>';
}
function mail_layout($heading, $inner) {
    $cfg = config();
    $site = rtrim($cfg['site_url'], '/');
    $host = preg_replace('#^https?://#', '', $site);
    $contact = isset($cfg['reply_to']) && $cfg['reply_to'] !== '' ? $cfg['reply_to'] : 'contacto@' . preg_replace('/^www\./', '', $host);
    $font = 'font-family:Arial,Helvetica,sans-serif';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</title></head>' .
        '<body style="margin:0;padding:0;background:#F0EFF4">' .
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F0EFF4"><tr><td align="center" style="padding:24px 12px">' .
        '<table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background:#FFFFFF">' .
        '<tr><td align="center" style="background:#111110;padding:18px 24px;border-bottom:3px solid #C6A15B">' .
            '<a href="' . $site . '" style="text-decoration:none"><img src="' . $site . '/img/logo.png" width="120" alt="MixMix" style="display:block;border:0;width:120px;height:auto"></a></td></tr>' .
        '<tr><td style="padding:32px 32px 12px;' . $font . ';color:#111110;font-size:16px;line-height:1.55">' .
            '<h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#111110">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1>' . $inner . '</td></tr>' .
        '<tr><td align="center" style="background:#111110;padding:20px 32px;' . $font . ';font-size:12px;line-height:1.7;color:#B9B7B0">' .
            '<span style="color:#C6A15B;letter-spacing:2px;font-weight:bold">GRUPO MIXMIX</span><br>Consultoría y herramientas para restaurantes<br>' .
            '<a href="' . $site . '" style="color:#F0EFF4">' . $host . '</a> · <a href="mailto:' . $contact . '" style="color:#F0EFF4">' . $contact . '</a></td></tr>' .
        '</table></td></tr></table></body></html>';
}

/* ---------- correo (recuperar contraseña) ----------
   Por defecto usa mail() del hosting. Si en privado/config.php hay 'smtp', usa ese buzón (más confiable). */
function mail_send($to, $subject, $html, $text, $replyTo = null) {
    $cfg = config();
    $domain = preg_replace('/^www\./', '', parse_url($cfg['site_url'], PHP_URL_HOST));
    $from = isset($cfg['mail_from']) ? $cfg['mail_from'] : 'no-reply@' . $domain;
    /* las respuestas de los clientes van a la bandeja de contacto, no al no-reply */
    if (!$replyTo) $replyTo = !empty($cfg['reply_to']) ? $cfg['reply_to'] : 'contacto@' . $domain;
    $name = 'Grupo MixMix';
    $boundary = 'mm_' . bin2hex(random_bytes(8));
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = array(
        'From: =?UTF-8?B?' . base64_encode($name) . '?= <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    );
    $body = '--' . $boundary . "\r\n" . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) .
            '--' . $boundary . "\r\n" . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) .
            '--' . $boundary . "--\r\n";
    if (!empty($cfg['smtp']) && is_array($cfg['smtp'])) {
        return smtp_send($cfg['smtp'], $from, $to, $subj, $headers, $body);
    }
    return @mail($to, $subj, $body, implode("\r\n", $headers), '-f' . $from);
}
function smtp_send($s, $from, $to, $subj, $headers, $body) {
    $secure = isset($s['secure']) ? $s['secure'] : 'ssl';
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $s['port'], $errno, $errstr, 15);
    if (!$fp) { error_log('smtp connect: ' . $errstr); return false; }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $r; };
    $cmd = function ($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $read();
    $cmd('EHLO ' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'));
    if ($secure === 'tls') {
        $cmd('STARTTLS');
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $cmd('EHLO ' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'));
    }
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($s['user']));
    $r = $cmd(base64_encode($s['pass']));
    if (strpos($r, '235') !== 0) { error_log('smtp auth failed'); fclose($fp); return false; }
    $cmd('MAIL FROM:<' . $from . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');
    $msg = 'To: <' . $to . ">\r\nSubject: " . $subj . "\r\nDate: " . date('r') . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $msg = preg_replace('/^\./m', '..', $msg);
    fwrite($fp, $msg . "\r\n.\r\n");
    $r = $read();
    $cmd('QUIT');
    fclose($fp);
    return strpos($r, '250') === 0;
}
/* GET sencillo con cURL (para verificar el token de Google) */
function http_get_json($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode((string)$body, true);
    return array($code, is_array($d) ? $d : array());
}
function stripe_is_test() { $c = config(); return strpos($c['stripe_secret'], 'sk_live') !== 0; }