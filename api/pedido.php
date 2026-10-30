<?php
require __DIR__ . '/_lib.php';
/* Estado de una compra hecha SIN cuenta. Se abre con el número de pedido + el código del enlace.
   Si todavía figura pendiente y viene el session_id de Stripe, se confirma el pago consultando a Stripe. */
require_method('GET');
$ref = isset($_GET['pedido']) ? (string)$_GET['pedido'] : '';
$tok = isset($_GET['t']) ? (string)$_GET['t'] : '';
$sid = isset($_GET['session_id']) ? (string)$_GET['session_id'] : '';
try {
    $o = guest_order($ref, $tok);
    if (!$o || $o['guest_email'] === null) fail('order_not_found', 404);
    if ($o['status'] !== 'paid' && preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) {
        $st = db()->prepare('SELECT stripe_session_id FROM orders WHERE id = ?');
        $st->execute(array($o['id']));
        $row = $st->fetch();
        if ($row && $row['stripe_session_id'] === $sid) {
            $s = stripe_request('GET', '/checkout/sessions/' . $sid);
            if (isset($s['payment_status']) && $s['payment_status'] === 'paid') mark_paid($sid);
        }
        $o = guest_order($ref, $tok);
    }
    $pid = isset($_GET['payment_id']) ? (string)$_GET['payment_id'] : '';
    if ($o['status'] !== 'paid' && preg_match('/^[0-9]{1,20}$/', $pid)) {   /* regreso de Mercado Pago */
        mp_confirm_payment($pid);   /* solo marca el pedido si el pago es de ESTE pedido y por su monto */
        $o = guest_order($ref, $tok);
    }
    if ($o['status'] === 'paid') {
        $st = db()->prepare('SELECT stripe_session_id FROM orders WHERE id = ?');
        $st->execute(array($o['id']));
        $row = $st->fetch();
        if ($row && $row['stripe_session_id']) after_paid($row['stripe_session_id']);
    }
    $me = current_user();
    $st = db()->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute(array($o['guest_email']));
    ok(array(
        'status' => $o['status'],
        'ref' => $o['ref'],
        'email' => $o['guest_email'],
        'lines' => json_decode($o['lines_json'], true),
        'discount' => (int)$o['discount'],
        'total' => (int)$o['total'],
        'saved' => $o['user_id'] !== null,                 /* ya está guardada en una cuenta */
        'account_exists' => (bool)$st->fetch(),              /* ya hay una cuenta con ese correo */
        'logged_in' => $me ? array('name' => $me['name'], 'email' => $me['email']) : null,
    ));
} catch (PDOException $e) {
    error_log('pedido: ' . $e->getMessage());
    fail('server', 500);
}
