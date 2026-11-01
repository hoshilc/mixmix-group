<?php
/* TEMPORAL: prueba de la plantilla del correo del manual. Se borra enseguida. */
require __DIR__ . '/_lib.php';
if (!isset($_GET['k']) || !hash_equals('9b3e5d1a7c40f2862e1d0a4b', (string)$_GET['k'])) { http_response_code(404); exit; }
$ok = manual_mail('hoshicoetowork@gmail.com', 'Hoshi López', 1);
header('Content-Type: application/json');
echo json_encode(array('mail_devolvio' => $ok));
