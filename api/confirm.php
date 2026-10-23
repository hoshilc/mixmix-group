<?php
require __DIR__ . '/_lib.php';
/* Al volver de Stripe: confirma el pago consultando a Stripe (por si el aviso del webhook tarda). */
require_method('GET');
$u = require_user();
$sid = isset($_GET['session_id']) ? (string)$_GET['session_id'] : '';
if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) fail('bad_session');
try {
    $st = db()->prepare('SELECT status FROM orders WHERE stripe_session_id = ? AND user_id = ?');
    $st->execute(array($sid, $u['id']));
    $o = $st->fetch();
    if (!$o) fail('order_not_found', 404);
    if ($o['status'] !== 'paid') {
        $s = stripe_request('GET', '/checkout/sessions/' . $sid);
        if (isset($s['payment_status']) && $s['payment_status'] === 'paid') mark_paid($sid);
    }
    $st->execute(array($sid, $u['id']));
    $o = $st->fetch();
    ok(array('status' => $o['status']));
} catch (PDOException $e) {
    error_log('confirm: ' . $e->getMessage());
    fail('server', 500);
}