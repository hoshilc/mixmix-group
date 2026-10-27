<?php
/* PLANTILLA de configuración. NO pongas claves aquí.
   Copia este archivo a  privado/config.php  (carpeta "privado" fuera de public_html, junto a "descargas")
   y llena los datos ahí. Ese archivo no se sube a GitHub ni es accesible desde internet. */
return array(
    /* dirección pública del sitio, sin diagonal final */
    'site_url' => 'https://darkslategray-trout-767454.hostingersite.com',

    /* base de datos MySQL (hPanel > Bases de datos) */
    'db' => array(
        'host' => 'localhost',
        'name' => 'u000000000_mixmix',
        'user' => 'u000000000_mixmix',
        'pass' => 'CAMBIAR',
    ),

    /* Stripe: claves de PRUEBA (empiezan con sk_test_ y whsec_). Para vender de verdad se cambian por las reales. */
    'stripe_secret' => 'sk_test_CAMBIAR',
    'stripe_webhook_secret' => 'whsec_CAMBIAR',

    /* métodos de pago ofrecidos en Stripe Checkout: 'card' siempre; 'oxxo' y 'customer_balance' (SPEI) requieren activarlos en Stripe */
    'payment_methods' => array('card'),
    /* Inicio de sesión con Google: ID de cliente OAuth (termina en .apps.googleusercontent.com). Vacío = el botón no aparece. */
    'google_client_id' => '',

    /* Clave para ver los pedidos en admin-pedidos.html (16 caracteres o mas, solo para ti). Vacia = la vista de pedidos queda apagada. */
    'admin_key' => '',

    /* Correo de recuperación de contraseña. Por defecto usa mail() del hosting desde 'no-reply@tudominio'.
       Si los correos caen en spam, usa un buzón real del dominio con SMTP: */
    // 'mail_from' => 'no-reply@grupomixmix.com',
    // 'smtp' => array('host' => 'smtp.hostinger.com', 'port' => 465, 'secure' => 'ssl', 'user' => 'no-reply@grupomixmix.com', 'pass' => 'CAMBIAR'),
);
