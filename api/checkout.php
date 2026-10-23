<?php
require __DIR__ . '/_lib.php';
require_method('POST');
$in = read_json();
$u = require_user();
$ids = (isset($in['ids']) && is_array($in['ids'])) ? array_slice($in['ids'], 0, 200) : array();
try {
    $pdo = db();
    $cfg = config();
    $cart = price_cart($ids, owned_item_ids($u['id']));        /* el precio sale de aquí, nunca del navegador */
    if (!$cart['lines'] || $cart['total'] <= 0) fail('empty_cart');

    $ref = 'MM-' . strtoupper(bin2hex(random_bytes(4)));
    $st = $pdo->prepare("INSERT INTO orders (ref, user_id, status, lines_json, discount, total) VALUES (?, ?, 'pending', ?, ?, ?)");
    $st->execute(array($ref, $u['id'], json_encode($cart['lines'], JSON_UNESCAPED_UNICODE), $cart['discount'], $cart['total']));

    /* líneas de Stripe: los manuales van juntos en una sola línea con el precio por volumen */
    $cat = catalog();
    $items = array();
    foreach ($cart['lines'] as $l) {
        $p = $cat['byId'][$l['id']];
        if (isset($p['cat']) && $p['cat'] === 'manuales') continue;
        $items[] = array('name' => $l['title'], 'amount' => $l['price'] * 100);
    }
    if ($cart['manuals'] > 0) {
        $items[] = array('name' => 'Manuales MixMix (' . $cart['manuals'] . ($cart['manuals'] === 1 ? ' manual' : ' manuales') . ', precio por volumen)', 'amount' => $cart['manualsCost'] * 100);
    }
    $params = array(
        'mode' => 'payment',
        'success_url' => $cfg['site_url'] . '/cuenta.html?pedido=' . $ref . '&session_id={CHECKOUT_SESSION_ID}#compras',
        'cancel_url' => $cfg['site_url'] . '/carrito.html',
        'customer_email' => $u['email'],
        'client_reference_id' => $ref,
        'metadata' => array('order_ref' => $ref),
        'locale' => 'es-419',
    );
    foreach ($items as $i => $it) {
        $params['line_items'][$i] = array(
            'quantity' => 1,
            'price_data' => array('currency' => 'mxn', 'unit_amount' => $it['amount'], 'product_data' => array('name' => mb_substr($it['name'], 0, 250))),
        );
    }
    foreach (isset($cfg['payment_methods']) ? $cfg['payment_methods'] : array('card') as $i => $m) $params['payment_method_types'][$i] = $m;

    $s = stripe_request('POST', '/checkout/sessions', $params);
    $pdo->prepare('UPDATE orders SET stripe_session_id = ? WHERE ref = ?')->execute(array($s['id'], $ref));
    ok(array('url' => $s['url'], 'ref' => $ref));
} catch (PDOException $e) {
    error_log('checkout: ' . $e->getMessage());
    fail('server', 500);
}