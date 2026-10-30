<?php
/* PRUEBA TEMPORAL (se borra después): corre price_cart con fases simuladas y devuelve los números. No toca la base de datos. */
require __DIR__ . '/_lib.php';
header('Content-Type: application/json');
$caso = isset($_GET['caso']) ? (int)$_GET['caso'] : 1;
$fases = array(
    array('id' => 'semana-1', 'inicio' => '2026-10-30T12:00:00-05:00', 'fin' => '2026-11-05T23:59:59-05:00', 'paquetes' => 30, 'sueltos' => 20),
    array('id' => 'semana-2', 'inicio' => '2026-11-06T00:00:00-05:00', 'fin' => '2026-11-12T23:59:59-05:00', 'paquetes' => 20, 'sueltos' => 10),
);
$ahora = array(1 => '2026-10-29T10:00:00-05:00', 2 => '2026-11-01T12:00:00-05:00', 3 => '2026-11-07T09:00:00-05:00', 4 => '2026-11-13T00:00:01-05:00');
$GLOBALS['MM_FASES_RAW'] = json_encode(array('fases' => $fases));
$GLOBALS['MM_NOW'] = strtotime($ahora[$caso]);
$cat = catalog();
$man = array();
foreach ($cat['items'] as $it) if ($it['cat'] === 'manuales' && count($man) < 7) $man[] = $it['id'];
$carritos = array(
    'suelto' => array('controles-alta-de-proveedores'),
    'paquete' => array('pkg-operacion'),
    'catalogo' => array('catalogo'),
    'bundle' => array('bundle-controles'),
    'manual1' => array_slice($man, 0, 1),
    'manuales2' => array_slice($man, 0, 2),
    'manuales4' => array_slice($man, 0, 4),
    'manuales7' => $man,
    'mixto' => array_merge(array_slice($man, 0, 3), array('controles-alta-de-proveedores', 'pkg-finanzas')),
);
$out = array('caso' => $caso, 'ahora' => $ahora[$caso], 'fase' => phase_active() ? phase_active()['id'] : null, 'carritos' => array());
foreach ($carritos as $k => $ids) {
    $r = price_cart($ids, array());
    $out['carritos'][$k] = array('ids' => $ids, 'total' => $r['total'], 'discount' => $r['discount'], 'other' => $r['other'], 'manualsCost' => $r['manualsCost'], 'lines' => array_map(function ($l) { return $l['price']; }, $r['lines']));
}
echo json_encode($out);
