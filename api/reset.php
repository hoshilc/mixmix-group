<?php
require __DIR__ . '/_lib.php';
/* Cambia la contraseña con el enlace recibido por correo. */
require_method('POST');
$in = read_json();
$token = isset($in['token']) ? (string)$in['token'] : '';
$pass = isset($in['password']) ? (string)$in['password'] : '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) fail('bad_token', 400);
if (strlen($pass) < 8) fail('weak_password');
try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()');
    $st->execute(array(hash('sha256', $token)));
    $row = $st->fetch();
    if (!$row) fail('bad_token', 400);
    $pdo->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute(array(password_hash($pass, PASSWORD_DEFAULT), $row['user_id']));
    $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ?')->execute(array($row['user_id']));
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['user_id'];
    $st = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
    $st->execute(array($row['user_id']));
    ok(array('user' => $st->fetch()));
} catch (PDOException $e) {
    error_log('reset: ' . $e->getMessage());
    fail('server', 500);
}