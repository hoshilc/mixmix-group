<?php
require __DIR__ . '/_lib.php';
/* Diagnóstico: no muestra claves ni rutas, solo si cada pieza está lista. */
$out = array('php' => PHP_VERSION, 'curl' => function_exists('curl_init'), 'pdo_mysql' => extension_loaded('pdo_mysql'), 'mbstring' => function_exists('mb_substr'));
$dir = private_dir();
$out['private_dir'] = $dir !== '';
$out['config'] = ($dir !== '' && is_file($dir . '/config.php'));
$out['descargas_files'] = 0;
if ($dir !== '' && is_dir($dir . '/descargas')) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir . '/descargas', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $out['descargas_files']++;
}
$cat = null;
try { $cat = catalog(); $out['catalog_items'] = count($cat['items']); } catch (Exception $e) { $out['catalog_items'] = 0; }
if ($out['config']) {
    $cfg = require $dir . '/config.php';
    $out['stripe_mode'] = (isset($cfg['stripe_secret']) && strpos($cfg['stripe_secret'], 'sk_live') === 0) ? 'live' : ((isset($cfg['stripe_secret']) && strpos($cfg['stripe_secret'], 'sk_test') === 0) ? 'test' : 'sin_clave');
    $out['webhook_secret'] = !empty($cfg['stripe_webhook_secret']) && strpos($cfg['stripe_webhook_secret'], 'whsec_') === 0 && $cfg['stripe_webhook_secret'] !== 'whsec_CAMBIAR';
    try {
        $pdo = db();
        $out['db'] = true;
        $out['tables'] = array('users' => (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetch(), 'orders' => (bool)$pdo->query("SHOW TABLES LIKE 'orders'")->fetch());
    } catch (Exception $e) { $out['db'] = false; }
}
json_out($out);