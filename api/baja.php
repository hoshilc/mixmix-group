<?php
require __DIR__ . '/_lib.php';
/* Baja de mensajes: el enlace de cada correo lleva a /baja (que pide confirmar) y esa página llama aquí.
   El código es un HMAC del contacto, así nadie puede dar de baja a otra persona adivinando números. */
require_method('POST');
$in = read_json();
if (!rate_limit('baja-ip|' . client_ip(), 30, 3600)) fail('too_many', 429);
$id = isset($in['c']) && preg_match('/^[0-9]{1,10}$/', (string)$in['c']) ? (int)$in['c'] : 0;
$t = isset($in['t']) ? (string)$in['t'] : '';
if (!$id || !preg_match('/^[a-f0-9]{32}$/', $t) || !hash_equals(contact_baja_code($id), $t)) fail('bad_link', 400);
try {
    contacts_ensure();
    db()->prepare('UPDATE contacts SET baja = 1, fecha_baja = COALESCE(fecha_baja, UTC_TIMESTAMP()), ultima_actualizacion = UTC_TIMESTAMP() WHERE id = ?')->execute(array($id));
} catch (PDOException $e) {
    error_log('baja: ' . $e->getMessage());
    fail('server', 500);
}
ok();
