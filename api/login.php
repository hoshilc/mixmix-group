<?php
require __DIR__ . '/_lib.php';
require_method('POST');
$in = read_json();
$email = strtolower(trim(isset($in['email']) ? (string)$in['email'] : ''));
$pass = isset($in['password']) ? (string)$in['password'] : '';
try {
    $st = db()->prepare('SELECT id, email, name, pass_hash FROM users WHERE email = ?');
    $st->execute(array($email));
    $u = $st->fetch();
    if (!$u || !password_verify($pass, $u['pass_hash'])) { usleep(700000); fail('bad_credentials', 401); }
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    ok(array('user' => array('name' => $u['name'], 'email' => $u['email'])));
} catch (PDOException $e) {
    error_log('login: ' . $e->getMessage());
    fail('server', 500);
}