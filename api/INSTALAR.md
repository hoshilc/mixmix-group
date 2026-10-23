# Instalación de la tienda en Hostinger (modo de prueba de Stripe)

Todo se hace en hPanel y en Stripe; **ninguna clave se comparte en chats ni se sube a GitHub**.

## 1. Archivos de pago (carpeta privada)
Suben a `privado/descargas/…`, **fuera** de `public_html` (tools\pack-for-upload.ps1 arma los paquetes).

## 2. Base de datos
1. hPanel → **Bases de datos → Administración**: crea una base MySQL (anota nombre, usuario y contraseña).
2. Entra a **phpMyAdmin** de esa base → **Importar** → sube `api/schema.sql`.

## 3. Archivo de configuración (en el servidor, no en GitHub)
1. Administrador de archivos → `privado/` → crea `config.php` copiando el contenido de `api/config.sample.php`.
2. Llena: `site_url`, los datos de la base y `stripe_secret` (clave **sk_test_…** de Stripe).
3. `stripe_webhook_secret` se llena en el paso 4.

## 4. Aviso de pago de Stripe (webhook)
Stripe (modo de prueba) → **Desarrolladores → Webhooks → Agregar punto de conexión**:
- URL: `https://TU-DOMINIO/api/webhook.php`
- Eventos: `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`
- Copia el **Signing secret** (`whsec_…`) a `stripe_webhook_secret` en `privado/config.php`.

## 5. Comprobar
Abre `https://TU-DOMINIO/api/health.php`: debe mostrar `config: true`, `db: true`, `tables` en `true`, `descargas_files: 135`, `stripe_mode: "test"`.

## 6. Probar una compra
`https://TU-DOMINIO/descargables.html?demo=1` → crea una cuenta → agrega algo → paga con la tarjeta de prueba `4242 4242 4242 4242`.

## Para vender de verdad
Cambia `sk_test_…` por la clave real, crea el webhook en el modo real (nuevo `whsec_…`) y pon `storeLive: true` en `js/config.js`.