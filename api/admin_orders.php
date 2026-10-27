<?php
require __DIR__ . '/_lib.php';
/* Vista de pedidos para el dueño (admin-pedidos.html). Se abre con la clave 'admin_key' de privado/config.php.
   Solo lectura: no cambia nada de los pedidos (salvo marcar como vencidos los que nunca se pagaron). */
require_method('POST');
$in = read_json();
$cfg = config();
$key = isset($cfg['admin_key']) ? (string)$cfg['admin_key'] : '';
if (strlen($key) < 16) fail('admin_not_configured', 503);
if (!rate_limit('admin-ip|' . client_ip(), 40, 900)) fail('too_many', 429);
$given = isset($in['key']) ? (string)$in['key'] : '';
if (!hash_equals($key, $given)) fail('bad_key', 403);

try {
    $pdo = db();
    expire_old_pending();
    $sql = "SELECT o.ref, o.status, o.total, o.discount, o.lines_json, UNIX_TIMESTAMP(o.created_at) AS created_ts, o.paid_at, %s AS guest_email, u.email AS user_email, u.name AS user_name
            FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 300";
    try {
        $rows = $pdo->query(sprintf($sql, 'o.guest_email'))->fetchAll();
    } catch (PDOException $e) {   /* todavía sin la migración 3 */
        $rows = $pdo->query(sprintf($sql, 'NULL'))->fetchAll();
    }
    $orders = array();
    foreach ($rows as $r) {
        $titles = array();
        $lines = json_decode($r['lines_json'], true);
        if (is_array($lines)) foreach ($lines as $l) if (isset($l['title'])) $titles[] = (string)$l['title'];
        $orders[] = array(
            'ref' => $r['ref'],
            'status' => $r['status'],
            'total' => (int)$r['total'],
            'discount' => (int)$r['discount'],
            'items' => $titles,
            'created' => (int)$r['created_ts'],
            'paid' => $r['paid_at'] ? strtotime($r['paid_at'] . ' UTC') : null,
            'who' => $r['user_email'] ? $r['user_email'] : ($r['guest_email'] ? $r['guest_email'] : ''),
            'name' => $r['user_name'] ? $r['user_name'] : '',
            'kind' => $r['user_email'] ? 'cuenta' : 'invitado',
        );
    }
    $sum = array('paid' => 0, 'paid_total' => 0, 'pending' => 0, 'expired' => 0, 'last30' => 0);
    foreach ($pdo->query("SELECT status, COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM orders GROUP BY status")->fetchAll() as $s) {
        if ($s['status'] === 'paid') { $sum['paid'] = (int)$s['n']; $sum['paid_total'] = (int)$s['t']; }
        elseif ($s['status'] === 'pending') $sum['pending'] = (int)$s['n'];
        elseif ($s['status'] === 'expired') $sum['expired'] = (int)$s['n'];
    }
    $r30 = $pdo->query("SELECT COALESCE(SUM(total), 0) AS t FROM orders WHERE status = 'paid' AND paid_at > (UTC_TIMESTAMP() - INTERVAL 30 DAY)")->fetch();
    $sum['last30'] = (int)$r30['t'];
    $u = $pdo->query("SELECT COUNT(*) AS n FROM users")->fetch();
    ok(array('orders' => $orders, 'summary' => $sum, 'users' => (int)$u['n'], 'mode' => stripe_is_test() ? 'test' : 'live'));
} catch (PDOException $e) {
    error_log('admin_orders: ' . $e->getMessage());
    fail('server', 500);
}
