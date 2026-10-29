<?php
require __DIR__ . '/_lib.php';
/* Formulario de Contacto: manda el mensaje por correo a la bandeja del negocio.
   Destino: 'contact_to' de privado/config.php; si no existe, contacto@ + el dominio del sitio. */
require_method('POST');
$in = read_json();
if (!empty($in['bot-field'])) ok();   /* trampa para robots: se responde como si hubiera funcionado */
if (!rate_limit('contacto:' . client_ip(), 5, 3600)) fail('too_many', 429);

/* texto limpio de una sola línea (sin saltos que puedan colarse en cabeceras) */
$line = function ($k, $max) use ($in) {
    $v = isset($in[$k]) ? (string)$in[$k] : '';
    $v = trim(preg_replace('/\s+/u', ' ', $v));
    return mb_substr($v, 0, $max);
};
$nombre = $line('nombre', 120);
$telefono = $line('telefono', 40);
$correo = $line('correo', 160);
$restaurante = $line('restaurante', 160);
$mensaje = isset($in['mensaje']) ? trim((string)$in['mensaje']) : '';
$mensaje = mb_substr(str_replace("\r", '', $mensaje), 0, 4000);
if ($nombre === '' || $telefono === '') fail('missing_fields');
if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) fail('invalid_email');

$intereses = array(
    'llamada' => 'Agendar una llamada gratis',
    'individual' => 'Capacitación y consultoría individual (particulares)',
    'area' => 'Auditoría por área (restaurantes)',
    'integral' => 'Auditoría integral (restaurantes)',
    'intensiva' => 'MixMix Intensiva',
    'reingenieria' => 'Reingeniería 360',
    'descargables' => 'Descargables (manuales, formatos, paquetes)',
    'otro' => 'Otro / no estoy seguro',
);
$k = $line('interes', 40);
$interes = isset($intereses[$k]) ? $intereses[$k] : 'Contacto';
$con = $line('con', 40);

$cfg = config();
$host = preg_replace('/^www\./', '', (string)parse_url($cfg['site_url'], PHP_URL_HOST));
$to = !empty($cfg['contact_to']) ? $cfg['contact_to'] : 'contacto@' . $host;

$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$rows = array('Interés' => $interes, 'Nombre' => $nombre, 'Teléfono' => $telefono, 'Correo' => $correo !== '' ? $correo : '(no lo dejó)', 'Restaurante' => $restaurante !== '' ? $restaurante : '(no lo dejó)');
if ($con !== '') $rows['Pidió hablar con'] = $con;
$table = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">';
$text = '';
foreach ($rows as $label => $val) {
    $table .= '<tr><td style="padding:6px 12px 6px 0;font-size:13px;color:#555555;vertical-align:top;white-space:nowrap">' . $esc($label) . '</td><td style="padding:6px 0;font-weight:bold">' . $esc($val) . '</td></tr>';
    $text .= $label . ': ' . $val . "\n";
}
$table .= '</table>';
$html = mail_layout('Nuevo mensaje desde la página',
    $table .
    ($mensaje !== '' ? '<p style="margin:20px 0 4px;font-weight:bold">Mensaje</p><p style="margin:0;white-space:pre-wrap">' . $esc($mensaje) . '</p>' : '') .
    '<p style="font-size:13px;color:#555555;margin:20px 0 16px">' . ($correo !== '' ? 'Si respondes a este correo, tu respuesta le llega directo a esta persona.' : 'No dejó correo: contáctala por teléfono.') . '</p>');
$text = "Nuevo mensaje desde la página\n\n" . $text . ($mensaje !== '' ? "\nMensaje:\n" . $mensaje . "\n" : '');

if (!mail_send($to, 'Contacto web: ' . $interes . ' — ' . $nombre, $html, $text, $correo !== '' ? $correo : null)) {
    error_log('contacto: no se pudo enviar el mensaje de ' . $nombre);
    fail('mail_failed', 500);
}
ok();
