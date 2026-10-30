<?php
require __DIR__ . '/_lib.php';
/* Solicitud de factura (CFDI) de una compra hecha sin cuenta. Stripe cobra pero no timbra: los datos fiscales llegan por correo
   al buzón del negocio para emitir la factura aparte. Se valida con el número de pedido + el código del enlace de la compra. */
require_method('POST');
$in = read_json();
if (!rate_limit('factura:' . client_ip(), 6, 3600)) fail('too_many', 429);

$o = guest_order(isset($in['pedido']) ? $in['pedido'] : '', isset($in['t']) ? $in['t'] : '');
if (!$o || $o['status'] !== 'paid') fail('not_found', 404);

/* texto limpio de una sola línea */
$line = function ($k, $max) use ($in) {
    $v = isset($in[$k]) ? (string)$in[$k] : '';
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $v)), 0, $max);
};
$rfc = mb_strtoupper(str_replace(' ', '', $line('rfc', 13)));
$razon = $line('razon', 200);
$regimen = $line('regimen', 120);
$cp = $line('cp', 5);
$uso = $line('uso', 120);
if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) fail('invalid_rfc');
if ($razon === '' || $regimen === '' || $uso === '') fail('missing_fields');
if (!preg_match('/^[0-9]{5}$/', $cp)) fail('invalid_cp');

$cfg = config();
$host = preg_replace('/^www\./', '', (string)parse_url($cfg['site_url'], PHP_URL_HOST));
$to = !empty($cfg['contact_to']) ? $cfg['contact_to'] : 'contacto@' . $host;
$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$rows = array(
    'Pedido' => $o['ref'],
    'Total pagado' => '$' . number_format((int)$o['total']) . ' MXN',
    'Correo del comprador' => (string)$o['guest_email'],
    'RFC' => $rfc,
    'Razón social' => $razon,
    'Régimen fiscal' => $regimen,
    'Código postal' => $cp,
    'Uso del CFDI' => $uso,
);
$table = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">';
$text = '';
foreach ($rows as $label => $val) {
    $table .= '<tr><td style="padding:6px 12px 6px 0;font-size:13px;color:#555555;vertical-align:top;white-space:nowrap">' . $esc($label) . '</td><td style="padding:6px 0;font-weight:bold">' . $esc($val) . '</td></tr>';
    $text .= $label . ': ' . $val . "\n";
}
$table .= '</table>';
$html = mail_layout('Solicitud de factura', $table .
    '<p style="font-size:13px;color:#555555;margin:20px 0 16px">Emite el CFDI con estos datos y envíalo al correo del comprador. Si respondes a este correo, le llega directo.</p>');
$replyTo = filter_var($o['guest_email'], FILTER_VALIDATE_EMAIL) ? $o['guest_email'] : null;
if (!mail_send($to, 'Solicitud de factura — pedido ' . $o['ref'], $html, "Solicitud de factura\n\n" . $text, $replyTo)) {
    error_log('factura: no se pudo enviar la solicitud del pedido ' . $o['ref']);
    fail('mail_failed', 500);
}
ok();
