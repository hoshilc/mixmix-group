<?php
require __DIR__ . '/_lib.php';
/* Entrega un archivo SOLO si el usuario lo compró. Los archivos viven en privado/descargas (fuera de public_html). */
require_method('GET');
$u = require_user();
$p = isset($_GET['p']) ? (string)$_GET['p'] : '';
if ($p === '' || strpos($p, '..') !== false || strpos($p, "\0") !== false) fail('bad_path');
try {
    $allowed = array();
    foreach (owned_products($u['id']) as $prod) foreach (product_files($prod) as $path => $name) if (!isset($allowed[$path])) $allowed[$path] = $name;
} catch (PDOException $e) { error_log('download: ' . $e->getMessage()); fail('server', 500); }
if (!isset($allowed[$p])) fail('not_owned', 403);

$base = realpath(private_dir());
$file = $base ? realpath($base . '/' . $p) : false;
if (!$file || strpos($file, $base . DIRECTORY_SEPARATOR . 'descargas' . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) fail('file_missing', 404);

$name = $allowed[$p];
$ascii = preg_replace('/[^\x20-\x7E]/', '_', $name);
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
header('Content-Type: ' . ($ext === 'zip' ? 'application/zip' : 'application/pdf'));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $ascii) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
@set_time_limit(0);
while (ob_get_level()) ob_end_clean();
$fh = fopen($file, 'rb');
while (!feof($fh)) { echo fread($fh, 1048576); flush(); }
fclose($fh);
exit;