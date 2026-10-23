<?php
require __DIR__ . '/_lib.php';
/* Inicio de sesión con Google: se verifica el token que entrega Google y se abre la sesión. */
require_method('POST');
$in = read_json();
$cred = isset($in['credential']) ? (string)$in['credential'] : '';
$cfg = config();
$clientId = isset($cfg['google_client_id']) ? trim($cfg['google_client_id']) : '';
if ($clientId === '') fail('google_not_configured', 503);
if ($cred === '' || strlen($cred) > 4096) fail('bad_token');
list($code, $g) = http_get_json('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($cred));
if ($code !== 200 || empty($g['sub'])) fail('bad_token', 401);
$okIss = isset($g['iss']) && in_array($g['iss'], array('accounts.google.com', 'https://accounts.google.com'), true);
$okAud = isset($g['aud']) && hash_equals($clientId, (string)$g['aud']);
$okMail = isset($g['email_verified']) && ($g['email_verified'] === true || $g['email_verified'] === 'true');
if (!$okIss || !$okAud || !$okMail || empty($g['email']) || (isset($g['exp']) && (int)$g['exp'] < time())) fail('bad_token', 401);
$email = strtolower($g['email']);
$sub = (string)$g['sub'];
$name = mb_substr(isset($g['name']) && $g['name'] !== '' ? $g['name'] : strstr($email, '@', true), 0, 120);
try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, name, email FROM users WHERE google_sub = ?');
    $st->execute(array($sub));
    $usr = $st->fetch();
    if (!$usr) {
        $st = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $st->execute(array($email));
        $usr = $st->fetch();
        if ($usr) {   /* ya tenía cuenta con ese correo (verificado por Google): se vincula */
            $pdo->prepare('UPDATE users SET google_sub = ? WHERE id = ?')->execute(array($sub, $usr['id']));
        } else {
            $pdo->prepare('INSERT INTO users (email, name, pass_hash, google_sub) VALUES (?, ?, NULL, ?)')->execute(array($email, $name, $sub));
            $usr = array('id' => (int)$pdo->lastInsertId(), 'name' => $name, 'email' => $email);
        }
    }
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$usr['id'];
    ok(array('user' => array('name' => $usr['name'], 'email' => $usr['email'])));
} catch (PDOException $e) {
    error_log('google: ' . $e->getMessage());
    fail('server', 500);
}