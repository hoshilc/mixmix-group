<?php
require __DIR__ . '/_lib.php';
/* Lista de contactos del manual para el dueño (admin-contactos.html). Se abre con la misma clave 'admin_key' de privado/config.php.
   Solo lectura. Las fechas salen en segundos Unix (UTC); la página las muestra en hora de Ciudad de México. */
require_method('POST');
$in = read_json();
$cfg = config();
$key = isset($cfg['admin_key']) ? (string)$cfg['admin_key'] : '';
if (strlen($key) < 16) fail('admin_not_configured', 503);
if (!rate_limit('admin-ip|' . client_ip(), 40, 900)) fail('too_many', 429);
$given = isset($in['key']) ? (string)$in['key'] : '';
if (!hash_equals($key, $given)) fail('bad_key', 403);
try {
    contacts_ensure();
    $rows = db()->query('SELECT id, nombre, whatsapp, correo, tipo_negocio, sucursales, consentimiento, UNIX_TIMESTAMP(consentimiento_at) AS consent_ts,
            UNIX_TIMESTAMP(fecha_registro) AS reg_ts, utm_source, utm_medium, utm_campaign, compro, UNIX_TIMESTAMP(fecha_compra) AS compra_ts, baja
            FROM contacts ORDER BY id DESC LIMIT 20000')->fetchAll();
} catch (PDOException $e) {
    error_log('admin_contacts: ' . $e->getMessage());
    fail('server', 500);
}
$out = array();
foreach ($rows as $r) {
    $out[] = array(
        'id' => (int)$r['id'], 'nombre' => $r['nombre'], 'whatsapp' => $r['whatsapp'], 'correo' => $r['correo'] ? $r['correo'] : '',
        'tipo' => $r['tipo_negocio'], 'sucursales' => $r['sucursales'],
        'consentimiento' => (int)$r['consentimiento'], 'consent' => $r['consent_ts'] ? (int)$r['consent_ts'] : null,
        'registro' => (int)$r['reg_ts'],
        'utm_source' => $r['utm_source'] ? $r['utm_source'] : '', 'utm_medium' => $r['utm_medium'] ? $r['utm_medium'] : '', 'utm_campaign' => $r['utm_campaign'] ? $r['utm_campaign'] : '',
        'compro' => (int)$r['compro'], 'compra' => $r['compra_ts'] ? (int)$r['compra_ts'] : null, 'baja' => (int)$r['baja'],
    );
}
ok(array('contacts' => $out, 'now' => time(), 'pdf' => $cfg['site_url'] . '/descargas/manual-donde-pierde-dinero.pdf'));
