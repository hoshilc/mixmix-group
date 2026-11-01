<?php
require __DIR__ . '/_lib.php';
/* Datos para /manual/gracias: el nombre del registro recién guardado y si hay que disparar el evento Lead.
   El Lead sale UNA sola vez por persona: el servidor lo reclama con un UPDATE condicional (recargar la página no lo repite). */
require_method('GET');
start_session();
$id = isset($_SESSION['manual_cid']) ? (int)$_SESSION['manual_cid'] : 0;
if (!$id) fail('no_contact', 404);
try {
    contacts_ensure();
    $pdo = db();
    $st = $pdo->prepare('SELECT id, nombre, correo, whatsapp FROM contacts WHERE id = ?');
    $st->execute(array($id));
    $c = $st->fetch();
    if (!$c) fail('no_contact', 404);
    $claim = $pdo->prepare('UPDATE contacts SET lead_enviado = 1 WHERE id = ? AND lead_enviado = 0');
    $claim->execute(array($id));
    $lead = $claim->rowCount() === 1;
} catch (PDOException $e) {
    error_log('manual-gracias: ' . $e->getMessage());
    fail('server', 500);
}
$eventId = 'lead-' . $id;
if ($lead) {
    $cfg = config();
    $first = mb_strtolower(mb_substr($c['nombre'], 0, (int)mb_strpos($c['nombre'] . ' ', ' ')));
    meta_capi('Lead', $eventId, array(
        'em' => array(meta_hash($c['correo'])),
        'ph' => array(meta_hash('52' . substr($c['whatsapp'], 3))),
        'fn' => array(meta_hash($first)),
        'client_ip_address' => client_ip(),
        'client_user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : null,
        'fbp' => isset($_COOKIE['_fbp']) ? (string)$_COOKIE['_fbp'] : null,
        'fbc' => isset($_COOKIE['_fbc']) ? (string)$_COOKIE['_fbc'] : null,
    ), array(), $cfg['site_url'] . '/manual/gracias');
}
$first = trim(mb_substr($c['nombre'], 0, (int)mb_strpos($c['nombre'] . ' ', ' ')));
ok(array('nombre' => $first, 'correo' => !empty($c['correo']), 'lead' => $lead, 'event_id' => $eventId));
