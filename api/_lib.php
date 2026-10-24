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
/* después de confirmar un pago: manda UNA vez el correo con el enlace a los archivos si fue compra sin cuenta */
function after_paid($sessionId) {
    $pdo = db();
    try {
        $st = $pdo->prepare("SELECT id, ref, guest_email, total FROM orders WHERE stripe_session_id = ? AND status = 'paid' AND guest_email IS NOT NULL AND mailed_at IS NULL");
        $st->execute(array($sessionId));
        $o = $st->fetch();
    } catch (PDOException $e) { error_log('after_paid (¿falta migracion-3?): ' . $e->getMessage()); return; }   /* nunca debe romper la confirmación del pago */
    if (!$o) return;
    $claim = $pdo->prepare('UPDATE orders SET mailed_at = UTC_TIMESTAMP() WHERE id = ? AND mailed_at IS NULL');
    $claim->execute(array($o['id']));
    if ($claim->rowCount() !== 1) return;
    $link = guest_link($o['ref']);
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#111110">' .
        '<p style="font-size:12px;letter-spacing:2px;color:#8A6D2F">GRUPO MIXMIX</p>' .
        '<h2 style="margin:0 0 12px">Gracias por tu compra, chef</h2>' .
        '<p>Tu pedido <strong>' . htmlspecialchars($o['ref'], ENT_QUOTES, 'UTF-8') . '</strong> está confirmado. Descarga tus archivos aquí:</p>' .
        '<p><a href="' . $link . '" style="display:inline-block;background:#111110;color:#F0EFF4;padding:12px 20px;text-decoration:none;font-weight:bold">Descargar mis archivos</a></p>' .
        '<p style="font-size:13px;color:#555">Guarda este correo: el enlace es personal y es tu acceso a lo que compraste. Si creas una cuenta con este correo en grupomixmix.com, todas tus compras quedan guardadas y podrás descargarlas siempre.</p>' .
        '</div>';
    $text = "Gracias por tu compra, chef.\n\nTu pedido " . $o['ref'] . " está confirmado. Descarga tus archivos aquí (enlace personal, guárdalo):\n" . $link .
        "\n\nSi creas una cuenta en grupomixmix.com con este correo, tus compras quedan guardadas y podrás descargarlas siempre.\n\nGrupo MixMix";
    if (!mail_send($o['guest_email'], 'Tus archivos de Grupo MixMix — pedido ' . $o['ref'], $html, $text)) {
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

/* ---------- correo (recuperar contraseña) ----------
   Por defecto usa mail() del hosting. Si en privado/config.php hay 'smtp', usa ese buzón (más confiable). */
function mail_send($to, $subject, $html, $text) {
    $cfg = config();
    $from = isset($cfg['mail_from']) ? $cfg['mail_from'] : 'no-reply@' . preg_replace('/^www\./', '', parse_url($cfg['site_url'], PHP_URL_HOST));
    $name = 'Grupo MixMix';
    $boundary = 'mm_' . bin2hex(random_bytes(8));
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = array(
        'From: =?UTF-8?B?' . base64_encode($name) . '?= <' . $from . '>',
        'Reply-To: ' . (isset($cfg['reply_to']) ? $cfg['reply_to'] : $from),
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