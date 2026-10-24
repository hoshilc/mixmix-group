<?php
require __DIR__ . '/_lib.php';
/* Stripe avisa aquí cuando un pago se completa (Developers > Webhooks). */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$payload = file_get_contents('php://input');
$sig = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
$cfg = config();
if (!verify_stripe_signature($payload, $sig, $cfg['stripe_webhook_secret'])) { http_response_code(400); echo 'bad signature'; exit; }
$event = json_decode($payload, true);
$type = isset($event['type']) ? $event['type'] : '';
$obj = isset($event['data']['object']) ? $event['data']['object'] : array();
try {
    if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
        if (isset($obj['payment_status']) && $obj['payment_status'] === 'paid') { mark_paid($obj['id']); after_paid($obj['id']); }
    } elseif ($type === 'checkout.session.expired' || $type === 'checkout.session.async_payment_failed') {
        db()->prepare("UPDATE orders SET status = 'expired' WHERE stripe_session_id = ? AND status = 'pending'")->execute(array($obj['id']));
    }
} catch (PDOException $e) {
    error_log('webhook: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
http_response_code(200);
echo 'ok';