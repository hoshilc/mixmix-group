# Sitio Grupo MixMix

Sitio estático (HTML + CSS + JS sin dependencias). Menú: **Nosotros · Descargables · Consultoría · Contacto**.

## Cómo está organizado el catálogo

Todo lo que se vende sale de un solo archivo: `js/catalog.js` (generado). `descargables.html`, `gracias.html` e `index.html` lo leen con `js/mm.js`; no hay precios ni títulos escritos a mano en el HTML.

## Estrategia de precios (3 capas)

Todo parte de dos precios base; el precio de cualquier paquete es consecuencia de lo que trae.

| Capa | Producto | Precio |
|---|---|---|
| Documento suelto | Manual completo (52) | $299 MXN |
| Documento suelto | Documento corto (63): puestos, controles, KPIs, Mystery | $129 MXN |
| Documento suelto | Juegos: Lotería (2) $249 · Memorama (3) $149 | |
| Paquete | Categoría de manuales (8) | $549 – $2,499 |
| Paquete | Por tipo: Puestos $2,249 · Controles $1,149 · KPIs $749 · Mystery $579 · Juegos $699 | |
| Paquete | Por perfil (8): Apertura $3,299 · Operación $1,999 · Talento $3,399 · Servicio $1,849 · Marketing $1,299 · Liderazgo $1,349 · Blindaje $1,799 · Finanzas $799 | |
| Ancla | Catálogo completo (115 documentos, sin juegos) | $6,999 (suma suelta $23,675) |

Descuento por volumen de referencia: 2–4 docs 10% · 5–9 25% · 10–20 35% · 21+ 50%. Los precios de categorías, perfiles y juegos son los de la tabla de estrategia; los paquetes por tipo salen de esa fórmula redondeada a …49/…99. El precio del catálogo completo es decisión de posicionamiento (el cálculo mecánico daría $9,499).

Todas las tarjetas muestran el precio por documento ("$81 c/u") y el ahorro frente a la suma de precios sueltos. Los Kit 360 / Kit 1000 ya no existen.

## Actualizar archivos o precios

Los PDF oficiales viven en la carpeta `Descargables  MIXMIX 2026` (Descargas). Para regenerar:

1. Edita precios (`prices`), títulos, categorías, paquetes o juegos en `catalog-config.json` (junto al sitio, no dentro).
2. Ejecuta `build-catalog.ps1`: copia los PDF a `descargas/`, arma los .zip y reescribe `js/catalog.js`.

Los nombres de descarga conservan acentos y ñ. Si un título tiene un error, corrígelo en `catalog-config.json` (campo `t` del manual) y vuelve a ejecutar el script.

## Tienda en línea: carrito, cuenta y compras (modo demostración)

Qué hay hoy (`js/store.js`, `carrito.html`, `cuenta.html`):

- Botones **Agregar** en cada documento, paquete, categoría, juego y en el catálogo completo; el encabezado muestra **Mi cuenta** y **Carrito (n)** en todas las páginas.
- Carrito con quitar, total, aviso si algo ya viene dentro de otro paquete o ya lo compraste, y sugerencia de **paquete** cuando llevas varios documentos sueltos de él.
- Cuenta: crear cuenta / entrar, **Mis compras** (cada pedido con sus descargas) y **Mis descargas** (todo lo comprado, sin repetidos, siempre disponible).
- Productos ya comprados muestran "Ya lo tienes" en lugar de "Agregar".

**Es una demostración:** el login no tiene contraseña y el pago es simulado; carrito, sesión y pedidos se guardan solo en el `localStorage` del navegador. No hay cobro real ni protección de archivos.

### Para cobrar de verdad hace falta un servidor

Los Payment Links de Stripe no sirven para un carrito con varios productos. Lo recomendado:

1. **Cuentas**: Supabase Auth (correo + contraseña o enlace mágico) o similar.
2. **Cobro**: una función serverless que cree una *Stripe Checkout Session* con los productos del carrito (precios de `js/catalog.js`, validados en el servidor). Con esto ya **no se necesitan los ~150 Payment Links**: un solo código cobra cualquier combinación.
3. **Webhook de Stripe** (`checkout.session.completed`): guarda el pedido del usuario en la base de datos.
4. **Descargas protegidas**: mover los PDF/zip de `descargas/` a un almacenamiento privado y entregarlos con enlaces firmados temporales solo a quien los compró (hoy las URL son públicas).
5. En `js/store.js` se sustituyen `login()`, `logout()`, `placeOrder()` y la lectura de `orders()` por llamadas a esas funciones; las páginas no cambian.

Pasos previos del lado de la marca: cuenta de Stripe (modo prueba primero), proyecto en Supabase (o el proveedor que elijas) y dónde se alojarán los archivos (≈ 550 MB, `manuales.zip` pesa 502 MB).
## Pendientes antes de publicar

- Sustituir `contacto@grupomixmix.com` y el WhatsApp `[agregar número]` en `contacto.html`.
- Peso de `descargas/` ≈ 550 MB (manuales.zip pesa 502 MB): revisar el límite del hosting.
- Falta MIX Games – Survival English (no apareció en la carpeta de Juegos); al agregarlo, el paquete de juegos pasaría de 5 a 6 juegos (~$849).
