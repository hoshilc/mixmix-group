<?php
require __DIR__ . '/_lib.php';
/* Mercado Pago avisa aquí cuando cambia un pago (la dirección va en cada preferencia, ver checkout.php).
   El aviso solo trae el número de pago: se vuelve a consultar a Mercado Pago con nuestro token, así que un aviso falso no puede marcar nada como pagado. */
if (!mp_enabled()) { http_response_code(404); exit; }
if (!rate_limit('mpwh|' . client_ip(), 600, 3600)) { http_response_code(429); exit; }
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = array();
/* formato Webhooks (JSON: type + data.id) o IPN antiguo (topic + id en la dirección; PHP vuelve "data.id" -> "data_id") */
$type = isset($body['type']) ? $body['type'] : (isset($_GET['type']) ? $_GET['type'] : (isset($_GET['topic']) ? $_GET['topic'] : ''));
$id = isset($body['data']['id']) ? $body['data']['id'] : (isset($_GET['data_id']) ? $_GET['data_id'] : (isset($_GET['id']) ? $_GET['id'] : ''));
try {
    if ($type === 'payment' && $id !== '') mp_confirm_payment((string)$id);
} catch (PDOException $e) {
    error_log('mp-webhook: ' . $e->getMessage());
    http_response_code(500);   /* Mercado Pago reintenta el aviso */
    exit;
}
http_response_code(200);
echo 'ok';
