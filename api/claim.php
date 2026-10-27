<?php
require __DIR__ . '/_lib.php';
/* Guarda una compra hecha sin cuenta dentro de una cuenta:
   - con sesión iniciada: la agrega a esa cuenta;
   - sin sesión: crea la cuenta con el correo de la compra (nombre + contraseña) y la agrega.
   Siempre hace falta el código del enlace del pedido: es la prueba de que la compra es suya. */
require_method('POST');
$in = read_json();
$ref = isset($in['pedido']) ? (string)$in['pedido'] : '';
$tok = isset($in['t']) ? (string)$in['t'] : '';
try {
    $pdo = db();
    $o = guest_order($ref, $tok);
    if (!$o || $o['guest_email'] === null) fail('order_not_found', 404);
    if ($o['status'] !== 'paid') fail('not_paid', 409);

    $u = current_user();
    if (!$u) {
        if (!rate_limit('claim-ip|' . client_ip(), 20, 3600)) fail('too_many', 429);
        $pass = isset($in['password']) ? (string)$in['password'] : '';
        if (strlen($pass) < 8) fail('weak_password');
        $name = mb_substr(trim(isset($in['name']) ? (string)$in['name'] : ''), 0, 120);
        if ($name === '') $name = strstr($o['guest_email'], '@', true);
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute(array($o['guest_email']));
        if ($st->fetch()) fail('account_exists', 409);          /* que inicie sesión y vuelva a este enlace */
        $pdo->prepare('INSERT INTO users (email, name, pass_hash) VALUES (?, ?, ?)')->execute(array($o['guest_email'], $name, password_hash($pass, PASSWORD_DEFAULT)));
        $uid = (int)$pdo->lastInsertId();
        start_session();
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
        $u = array('id' => $uid, 'email' => $o['guest_email'], 'name' => $name);
    }
    if ($o['user_id'] === null) {
        $pdo->prepare('UPDATE orders SET user_id = ? WHERE id = ? AND user_id IS NULL')->execute(array($u['id'], $o['id']));
    } elseif ((int)$o['user_id'] !== (int)$u['id']) {
        fail('already_saved', 409);                             /* ya pertenece a otra cuenta */
    }
    ok(array('user' => array('name' => $u['name'], 'email' => $u['email'])));
} catch (PDOException $e) {
    error_log('claim: ' . $e->getMessage());
    fail('server', 500);
}
