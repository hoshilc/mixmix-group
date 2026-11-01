<?php
require __DIR__ . '/_lib.php';
/* Registro del manual gratuito (/manual). Crea o actualiza el contacto (el WhatsApp es único), guarda de dónde llegó
   y manda el correo con el enlace al PDF si dejó correo. La descarga principal es el botón de /manual/gracias. */
require_method('POST');
$in = read_json();
if (!rate_limit('mf-ip|' . client_ip(), 30, 3600)) fail('too_many', 429);
/* campo trampa para robots: una persona nunca lo ve ni lo llena; se responde "bien" sin guardar nada */
if (!empty($in['web'])) ok(array('next' => '/manual/gracias'));

function clean_text($v, $max) {
    $v = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$v));
    return mb_substr($v, 0, $max);
}
$nombre = clean_text(isset($in['nombre']) ? $in['nombre'] : '', 120);
$phone = norm_phone(isset($in['whatsapp']) ? $in['whatsapp'] : '');
$correo = strtolower(clean_text(isset($in['correo']) ? $in['correo'] : '', 190));
$tipo = isset($in['tipo']) ? (string)$in['tipo'] : '';
$suc = isset($in['sucursales']) ? (string)$in['sucursales'] : '';
$errors = array();
if (preg_match_all('/\p{L}/u', $nombre) < 2) $errors['nombre'] = 'Escribe tu nombre (mínimo 2 letras)';
if (!$phone) $errors['whatsapp'] = 'Escribe tu WhatsApp a 10 dígitos';
if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errors['correo'] = 'Revisa tu correo: parece que tiene un error';
if (!in_array($tipo, array('Restaurante', 'Cafetería', 'Bar', 'Taquería', 'Otro'), true)) $errors['tipo'] = 'Elige el tipo de negocio';
if (!in_array($suc, array('1', '2-3', '4+'), true)) $errors['sucursales'] = 'Elige cuántas sucursales tienes';
if (empty($in['consentimiento'])) $errors['consentimiento'] = 'Marca la casilla para recibir tu manual';
if ($errors) json_out(array('ok' => false, 'error' => 'validation', 'errors' => $errors), 422);
if (!rate_limit('mf-wa|' . $phone, 8, 3600)) fail('too_many', 429);

$utm = array();
foreach (array('utm_source', 'utm_medium', 'utm_campaign') as $k) {
    $v = clean_text(isset($in[$k]) ? $in[$k] : '', 120);
    $utm[$k] = $v === '' ? null : $v;
}
$wa = '+52' . $phone;
try {
    contacts_ensure();
    $pdo = db();
    $st = $pdo->prepare('SELECT id, correo, consentimiento_at, utm_source, utm_medium, utm_campaign FROM contacts WHERE whatsapp = ?');
    $st->execute(array($wa));
    $row = $st->fetch();
    $isNew = !$row;
    if ($row) {
        /* ya existía: se actualiza, no se duplica. Conserva la fecha de registro y de dónde llegó la primera vez */
        $newMail = $correo !== '' ? $correo : $row['correo'];
        $pdo->prepare('UPDATE contacts SET nombre = ?, correo = ?, tipo_negocio = ?, sucursales = ?, consentimiento = 1,
                consentimiento_at = COALESCE(consentimiento_at, UTC_TIMESTAMP()), baja = 0, fecha_baja = NULL,
                utm_source = COALESCE(utm_source, ?), utm_medium = COALESCE(utm_medium, ?), utm_campaign = COALESCE(utm_campaign, ?),
                ultima_actualizacion = UTC_TIMESTAMP() WHERE id = ?')
            ->execute(array($nombre, $newMail, $tipo, $suc, $utm['utm_source'], $utm['utm_medium'], $utm['utm_campaign'], $row['id']));
        $id = (int)$row['id'];
        $mailChanged = $correo !== '' && $correo !== $row['correo'];
    } else {
        $pdo->prepare('INSERT INTO contacts (nombre, whatsapp, correo, tipo_negocio, sucursales, consentimiento, consentimiento_at, fecha_registro, utm_source, utm_medium, utm_campaign, ultima_actualizacion)
                VALUES (?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?, ?, UTC_TIMESTAMP())')
            ->execute(array($nombre, $wa, $correo !== '' ? $correo : null, $tipo, $suc, $utm['utm_source'], $utm['utm_medium'], $utm['utm_campaign']));
        $id = (int)$pdo->lastInsertId();
        $mailChanged = $correo !== '';
    }
} catch (PDOException $e) {
    error_log('manual: ' . $e->getMessage());
    fail('server', 500);
}

/* para que /manual/gracias sepa quién es (y su nombre) sin pedir nada más */
start_session();
$_SESSION['manual_cid'] = $id;

/* correo con el enlace al PDF: solo si dejó correo y es nuevo o cambió (no se manda en cada registro repetido) */
if ($correo !== '' && ($isNew || $mailChanged)) {
    $cfg = config();
    $pdf = $cfg['site_url'] . '/descargas/manual-donde-pierde-dinero.pdf';
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $first = mb_substr($nombre, 0, (int)mb_strpos($nombre . ' ', ' '));
    $baja = contact_baja_link($id);
    $html = mail_layout('Tu manual está listo, ' . $first,
        '<p style="margin:0 0 8px">Aquí está <strong>Descubre en 7 días dónde pierde dinero tu restaurante</strong>, el manual gratuito de Grupo MIXMIX.</p>' .
        mail_button($pdf, 'Descargar el manual (PDF)') .
        '<p style="margin:16px 0 8px"><strong>Empieza hoy con el Día 1:</strong> solo te toma 30 minutos.</p>' .
        '<p style="font-size:12px;color:#8A8A87;margin:20px 0 4px">Recibes este correo porque pediste el manual en grupomixmix.com. Si ya no quieres recibir mensajes, <a href="' . $esc($baja) . '" style="color:#555555">date de baja aquí</a>.</p>');
    $text = 'Tu manual está listo, ' . $first . ".\n\nDescárgalo aquí: " . $pdf .
        "\n\nEmpieza hoy con el Día 1: solo te toma 30 minutos." .
        "\n\nSi ya no quieres recibir mensajes, date de baja aquí: " . $baja . "\n\nGrupo MIXMIX";
    if (!mail_send($correo, 'Tu manual: dónde pierde dinero tu restaurante', $html, $text)) error_log('manual: no se pudo enviar el correo al contacto ' . $id);
}
ok(array('next' => '/manual/gracias', 'ct' => contact_token($id)));
