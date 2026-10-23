<?php
require __DIR__ . '/_lib.php';
require_method('POST');
$in = read_json();
$name = trim(isset($in['name']) ? (string)$in['name'] : '');
$email = strtolower(trim(isset($in['email']) ? (string)$in['email'] : ''));
$pass = isset($in['password']) ? (string)$in['password'] : '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) fail('invalid_email');
if (strlen($pass) < 8) fail('weak_password');
if ($name === '') $name = strstr($email, '@', true);
$name = mb_substr($name, 0, 120);
try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute(array($email));
    if ($st->fetch()) fail('email_taken', 409);
    $st = $pdo->prepare('INSERT INTO users (email, name, pass_hash) VALUES (?, ?, ?)');
    $st->execute(array($email, $name, password_hash($pass, PASSWORD_DEFAULT)));
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$pdo->lastInsertId();
    ok(array('user' => array('name' => $name, 'email' => $email)));
} catch (PDOException $e) {
    error_log('register: ' . $e->getMessage());
    fail('server', 500);
}