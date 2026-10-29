<?php
require __DIR__ . '/_lib.php';
/* Pide un enlace para restablecer la contraseña. Siempre responde igual (no revela si el correo existe). */
require_method('POST');
$in = read_json();
$email = strtolower(trim(isset($in['email']) ? (string)$in['email'] : ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('invalid_email');
$sent = null;
try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, name FROM users WHERE email = ?');
    $st->execute(array($email));
    $usr = $st->fetch();
    if ($usr) {
        $st = $pdo->prepare('SELECT COUNT(*) AS n FROM password_resets WHERE user_id = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)');
        $st->execute(array($usr['id']));
        $recent = (int)$st->fetch()['n'];
        if ($recent < 3) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 1 HOUR, UTC_TIMESTAMP())')
                ->execute(array($usr['id'], hash('sha256', $token)));
            $cfg = config();
            $link = $cfg['site_url'] . '/cuenta.html?reset=' . $token;
            $nombre = htmlspecialchars($usr['name'], ENT_QUOTES, 'UTF-8');
            $html = mail_layout('Restablecer tu contraseña',
                '<p style="margin:0 0 8px">Hola ' . $nombre . ', recibimos una solicitud para cambiar tu contraseña.</p>' .
                mail_button($link, 'Elegir una contraseña nueva') .
                '<p style="font-size:13px;color:#555555;margin:20px 0 16px">El enlace funciona una sola vez y vence en 1 hora. Si tú no lo pediste, ignora este correo: tu contraseña no cambia.</p>');
            $text = "Hola " . $usr['name'] . ",\n\nPara elegir una contraseña nueva abre este enlace (funciona una sola vez y vence en 1 hora):\n" . $link . "\n\nSi tú no lo pediste, ignora este correo.\n\nGrupo MixMix";
            $sent = mail_send($email, 'Restablece tu contraseña de Grupo MixMix', $html, $text);
            if (!$sent) error_log('forgot: no se pudo enviar el correo');
        }
    }
} catch (PDOException $e) {
    error_log('forgot: ' . $e->getMessage());
    fail('server', 500);
}
$res = array();
if (stripe_is_test() && $sent === false) $res['mail_failed'] = true;   /* aviso solo en modo de prueba */
ok($res);