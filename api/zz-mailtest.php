<?php
/* TEMPORAL: prueba de envío de correo. Se borra enseguida. */
require __DIR__ . '/_lib.php';
if (!isset($_GET['k']) || !hash_equals('4f1c9a07d2b84e65a3c0b7d1', (string)$_GET['k'])) { http_response_code(404); exit; }
$to = 'hoshicoetowork@gmail.com';
$cfg = config();
$domain = preg_replace('/^www\./', '', parse_url($cfg['site_url'], PHP_URL_HOST));
$res = array('smtp_configurado' => !empty($cfg['smtp']), 'mail_from_config' => isset($cfg['mail_from']) ? $cfg['mail_from'] : null);
foreach (array('A' => 'no-reply@' . $domain, 'B' => 'contacto@' . $domain) as $tag => $from) {
    $boundary = 'mm_' . bin2hex(random_bytes(8));
    $subject = 'Prueba ' . $tag . ' de Grupo MIXMIX (remitente ' . $from . ')';
    $text = "Prueba de envío desde el sitio.\nRemitente: " . $from . "\nSi lo ves, dime en qué carpeta llegó (Principal, Promociones o Spam).";
    $html = '<p>Prueba de envío desde el sitio.</p><p>Remitente: <strong>' . htmlspecialchars($from) . '</strong></p><p>Si lo ves, dime en qué carpeta llegó (Principal, Promociones o Spam).</p>';
    $headers = array(
        'From: =?UTF-8?B?' . base64_encode('Grupo MixMix') . '?= <' . $from . '>',
        'Reply-To: contacto@' . $domain,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    );
    $body = '--' . $boundary . "\r\n" . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) .
            '--' . $boundary . "\r\n" . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) .
            '--' . $boundary . "--\r\n";
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $from);
    $res['mail_' . $tag] = array('from' => $from, 'mail_devolvio' => $ok);
}
header('Content-Type: application/json');
echo json_encode($res);
