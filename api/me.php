<?php
require __DIR__ . '/_lib.php';
require_method('GET');
$cfg = config();
$mode = (strpos($cfg['stripe_secret'], 'sk_live') === 0) ? 'live' : 'test';
$gid = isset($cfg['google_client_id']) ? trim($cfg['google_client_id']) : '';
try {
    $u = current_user();
    if (!$u) ok(array('user' => null, 'orders' => array(), 'stripe_mode' => $mode, 'google_client_id' => $gid));
    $orders = array();
    foreach (paid_orders($u['id']) as $o) {
        $orders[] = array(
            'id' => $o['ref'],
            'date' => gmdate('c', strtotime($o['paid_at'] . ' UTC')),
            'lines' => json_decode($o['lines_json'], true),
            'discount' => (int)$o['discount'],
            'total' => (int)$o['total'],
        );
    }
    ok(array('user' => array('name' => $u['name'], 'email' => $u['email']), 'orders' => $orders, 'stripe_mode' => $mode, 'google_client_id' => $gid));
} catch (PDOException $e) {
    error_log('me: ' . $e->getMessage());
    fail('server', 500);
}