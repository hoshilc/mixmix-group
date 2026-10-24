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

## 7. Recuperar contraseña e inicio de sesión con Google (migración 2)
1. phpMyAdmin (base de la tienda) → **Importar** → `api/migracion-2.sql`. **Una sola vez** (si la base se creó después de esta versión con `schema.sql`, ya trae todo y no se importa).
2. **Correo de recuperación:** funciona de entrada con `mail()` del hosting. Si los mensajes caen en spam o no llegan, crea un buzón en hPanel (p. ej. `no-reply@grupomixmix.com`) y en `privado/config.php` activa las líneas `mail_from` y `smtp` de la plantilla.
3. **Google:**
   - Google Cloud Console → *APIs y servicios → Credenciales → Crear credenciales → ID de cliente de OAuth* → tipo **Aplicación web**.
   - **Orígenes autorizados de JavaScript:** `https://grupomixmix.com` (y `https://www.grupomixmix.com` si lo usas). No hace falta URI de redireccionamiento.
   - Pantalla de consentimiento: nombre "Grupo MixMix", correo de soporte y dominio `grupomixmix.com`.
   - Copia el **ID de cliente** (termina en `.apps.googleusercontent.com`; no es secreto) en `privado/config.php` → `'google_client_id'`.
4. Comprobar en `api/health.php`: `tables.password_resets: true`, `users_google_column: true`, `google_client_id: true`.
## 8. Comprar sin iniciar sesión (migración 3)
1. phpMyAdmin (base de la tienda) → **Importar** → `api/migracion-3.sql`. **Una sola vez** (si la base se creó con el `schema.sql` más reciente, ya trae todo). Sin esto, el pago como invitado responde "migration_needed".
2. Comprobar en `api/health.php`: `orders_guest_columns: true`.
3. Cómo funciona: el invitado solo da su correo. Al volver de Stripe cae en `pedido.html` (con un código personal en el enlace, calculado con el secreto del webhook; no se guarda en la base) donde descarga sus archivos, y se le manda el mismo enlace por correo. Ahí mismo puede crear su cuenta con ese correo y la compra queda guardada. Si entra con Google o restablece su contraseña con ese correo, sus compras como invitado se agregan solas.
4. Si cambias `stripe_webhook_secret` (p. ej. al pasar a modo real), los enlaces de compras anteriores como invitado dejan de funcionar; esas personas pueden entrar con Google o recuperar contraseña para recibirlas en su cuenta.
