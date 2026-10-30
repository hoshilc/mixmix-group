<?php
require __DIR__ . '/_lib.php';
require_method('POST');
$in = read_json();
/* con sesión: compra a nombre de la cuenta. Sin sesión: compra como invitado (solo pide el correo). */
$u = current_user();
$guestEmail = null;
if (!$u) {
    $guestEmail = strtolower(trim(isset($in['email']) ? (string)$in['email'] : ''));
    if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL) || strlen($guestEmail) > 190) fail('invalid_email');
}
$ids = (isset($in['ids']) && is_array($in['ids'])) ? array_slice($in['ids'], 0, 200) : array();
/* límites: cada pago abre una sesión en Stripe, así que se frena a quien intente muchos seguidos */
if ($u) {
    if (!rate_limit('co-user|' . $u['id'], 20, 3600)) fail('too_many', 429);
} else {
    if (!rate_limit('co-ip|' . client_ip(), 30, 3600) || !rate_limit('co-mail|' . $guestEmail, 6, 3600)) fail('too_many', 429);
}
expire_old_pending();
/* pasarela: Stripe por defecto; 'mp' = Mercado Pago (solo si hay mp_access_token en privado/config.php) */
$gateway = (isset($in['gateway']) && $in['gateway'] === 'mp') ? 'mp' : 'stripe';
if ($gateway === 'mp' && !mp_enabled()) fail('mp_not_available');
try {
    $pdo = db();
    $cfg = config();
    $cart = price_cart($ids, $u ? owned_item_ids($u['id']) : array());        /* el precio sale de aquí, nunca del navegador */
    if (!$cart['lines'] || $cart['total'] <= 0) fail('empty_cart');

    $ref = 'MM-' . strtoupper(bin2hex(random_bytes(4)));
    $linesJson = json_encode($cart['lines'], JSON_UNESCAPED_UNICODE);
    if ($u) {   /* con cuenta: igual que siempre (no depende de la migración 3) */
        $st = $pdo->prepare("INSERT INTO orders (ref, user_id, status, lines_json, discount, total) VALUES (?, ?, 'pending', ?, ?, ?)");
        $st->execute(array($ref, $u['id'], $linesJson, $cart['discount'], $cart['total']));
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO orders (ref, user_id, guest_email, status, lines_json, discount, total) VALUES (?, NULL, ?, 'pending', ?, ?, ?)");
            $st->execute(array($ref, $guestEmail, $linesJson, $cart['discount'], $cart['total']));
        } catch (PDOException $e) {
            error_log('checkout (invitado; ¿falta migracion-3?): ' . $e->getMessage());
            fail('migration_needed', 500);
        }
    }

    /* líneas de Stripe: los manuales van juntos en una sola línea con el precio por volumen */
    $cat = catalog();
    $items = array();
    foreach ($cart['lines'] as $l) {
        $p = $cat['byId'][$l['id']];
        if (isset($p['cat']) && $p['cat'] === 'manuales') continue;
        $items[] = array('name' => $l['title'], 'amount' => $l['price'] * 100);
    }
    if ($cart['manuals'] > 0) {
        $items[] = array('name' => 'Manuales MixMix (' . $cart['manuals'] . ($cart['manuals'] === 1 ? ' manual' : ' manuales') . ', precio por volumen)', 'amount' => $cart['manualsCost'] * 100);
    }
    if ($gateway === 'mp') {
        /* Mercado Pago agrega sus datos (payment_id, status…) al final de la liga de regreso: por eso va sin #ancla */
        $back = $u
            ? $cfg['site_url'] . '/cuenta.html?pedido=' . $ref
            : $cfg['site_url'] . '/pedido.html?pedido=' . $ref . '&t=' . guest_token($ref);
        $mpItems = array();
        foreach ($items as $it) {
            $mpItems[] = array('title' => mb_substr($it['name'], 0, 250), 'quantity' => 1, 'currency_id' => 'MXN', 'unit_price' => $it['amount'] / 100);
        }
        $pref = array(
            'items' => $mpItems,
            'payer' => array('email' => $u ? $u['email'] : $guestEmail),
            'external_reference' => $ref,
            'metadata' => array('order_ref' => $ref, 'phase' => $cart['phase']),
            'back_urls' => array('success' => $back, 'pending' => $back, 'failure' => $cfg['site_url'] . '/carrito.html'),
            'auto_return' => 'approved',
            'notification_url' => $cfg['site_url'] . '/api/mp-webhook.php',
            'statement_descriptor' => 'GRUPO MIXMIX',
        );
        /* la liga de pago vence a los 2 días o al cerrar la fase de lanzamiento (lo que ocurra primero) */
        $endTs = time() + 2 * 86400;
        if ($cart['phase'] !== null) $endTs = min($endTs, max($cart['phaseEnd'], time() + 1800));
        $pref['expires'] = true;
        $pref['expiration_date_from'] = gmdate('Y-m-d\TH:i:s') . '.000+00:00';
        $pref['expiration_date_to'] = gmdate('Y-m-d\TH:i:s', $endTs) . '.000+00:00';
        $r = mp_request('POST', '/checkout/preferences', $pref, true);
        if (isset($r['_error']) && !(isset($r['_code']) && $r['_code'] === 401)) {   /* 401 = token inválido: reintentar no sirve */
            error_log('checkout mp: se reintenta sin vencimiento (' . $r['_error'] . ')');
            unset($pref['expires'], $pref['expiration_date_from'], $pref['expiration_date_to']);
            $r = mp_request('POST', '/checkout/preferences', $pref);
        } elseif (isset($r['_error'])) {
            fail('mp_error', 502);
        }
        $url = (strpos($cfg['mp_access_token'], 'TEST-') === 0 && !empty($r['sandbox_init_point'])) ? $r['sandbox_init_point'] : (isset($r['init_point']) ? $r['init_point'] : '');
        if ($url === '') { error_log('checkout mp: la respuesta no trae init_point'); fail('mp_error', 502); }
        $pdo->prepare('UPDATE orders SET stripe_session_id = ? WHERE ref = ?')->execute(array('mp_' . $ref, $ref));
        ok(array('url' => $url, 'ref' => $ref, 'methods' => array('mercadopago')));
    }
    $success = $u
        ? $cfg['site_url'] . '/cuenta.html?pedido=' . $ref . '&session_id={CHECKOUT_SESSION_ID}#compras'
        : $cfg['site_url'] . '/pedido.html?pedido=' . $ref . '&t=' . guest_token($ref) . '&session_id={CHECKOUT_SESSION_ID}';
    $params = array(
        'mode' => 'payment',
        'success_url' => $success,
        'cancel_url' => $cfg['site_url'] . '/carrito.html',
        'customer_email' => $u ? $u['email'] : $guestEmail,
        'client_reference_id' => $ref,
        'metadata' => array('order_ref' => $ref),
        'locale' => 'es-419',
        'allow_promotion_codes' => 'true',   /* campo "Agregar código promocional" en la página de pago; los códigos se crean en Stripe */
    );
    /* precio de lanzamiento: se anota la fase y la sesión de pago vence al cerrar la fase, para que nadie pague un precio ya terminado.
       Stripe exige que venza entre 30 minutos y 24 horas después de crearse; en los últimos 31 minutos de una fase puede quedar
       hasta ~30 min abierta después del cierre (límite de Stripe). */
    if ($cart['phase'] !== null) {
        $params['metadata']['phase'] = $cart['phase'];
        $left = $cart['phaseEnd'] - time();
        if ($left < 86400) $params['expires_at'] = time() + max(1860, $left);
    }
    foreach ($items as $i => $it) {
        $params['line_items'][$i] = array(
            'quantity' => 1,
            'price_data' => array('currency' => 'mxn', 'unit_amount' => $it['amount'], 'product_data' => array('name' => mb_substr($it['name'], 0, 250))),
        );
    }

    /* métodos de pago: 'payment_methods' de privado/config.php ('card', y si ya están activados en Stripe: 'oxxo' y 'customer_balance' = transferencia SPEI).
       Si Stripe rechaza alguno (p. ej. todavía no está activado), se reintenta solo con tarjeta: nadie se queda sin poder pagar. */
    $methods = (isset($cfg['payment_methods']) && is_array($cfg['payment_methods']) && $cfg['payment_methods']) ? array_values($cfg['payment_methods']) : array('card');
    $payParams = function ($methods) use ($params, $cart, $ref, $u, $guestEmail) {
        $p = $params;
        foreach ($methods as $i => $m) $p['payment_method_types'][$i] = $m;
        if (in_array('oxxo', $methods, true)) {
            /* la ficha de OXXO no vence con la sesión de pago: se acorta para que no sobreviva mucho al precio de lanzamiento */
            $days = 2;
            if ($cart['phase'] !== null) $days = max(1, min(2, (int)ceil(($cart['phaseEnd'] - time()) / 86400)));
            $p['payment_method_options']['oxxo']['expires_after_days'] = $days;
        }
        if (in_array('customer_balance', $methods, true)) {
            /* la transferencia SPEI necesita un cliente en Stripe (en lugar de solo el correo) */
            $c = stripe_request('POST', '/customers', array('email' => $u ? $u['email'] : $guestEmail, 'metadata' => array('order_ref' => $ref)), true);
            if (isset($c['id'])) {
                unset($p['customer_email']);
                $p['customer'] = $c['id'];
                $p['payment_method_options']['customer_balance'] = array('funding_type' => 'bank_transfer', 'bank_transfer' => array('type' => 'mx_bank_transfer'));
            } else {
                $p['payment_method_types'] = array_values(array_diff($methods, array('customer_balance')));
            }
        }
        return $p;
    };
    $s = stripe_request('POST', '/checkout/sessions', $payParams($methods), count($methods) > 1);
    $used = $methods;
    if (isset($s['_error'])) {
        error_log('checkout: Stripe rechazó los métodos ' . implode(',', $methods) . ' (' . $s['_error'] . '); se reintenta solo con tarjeta');
        $used = array('card');
        $s = stripe_request('POST', '/checkout/sessions', $payParams($used));
    }
    $pdo->prepare('UPDATE orders SET stripe_session_id = ? WHERE ref = ?')->execute(array($s['id'], $ref));
    ok(array('url' => $s['url'], 'ref' => $ref, 'methods' => $used));
} catch (PDOException $e) {
    error_log('checkout: ' . $e->getMessage());
    fail('server', 500);
}
