# Sitio Grupo MixMix

Sitio estático (HTML + CSS + JS sin dependencias), publicado en Netlify desde este repositorio.
Menú: **Nosotros · Descargables · Consultoría · Contacto**.

## Dónde se edita cada cosa

| Qué | Dónde |
|---|---|
| WhatsApp, calendario, redes sociales, analítica, activar la tienda | `js/config.js` |
| Videos de redes (Home > Evidencia) | `js/social.js` |
| Logos de Emprendimientos, reseñas de Clientes y cifras | `js/reputacion.js` (logos en `img/logos/`) |
| Encabezado y pie de todas las páginas | `partials/header.html`, `partials/footer.html` |
| Precios, títulos, categorías y paquetes del catálogo | `tools/catalog-config.json` |
| Textos de cada página | el `.html` correspondiente |

Después de cambiar **partials**, el **título o descripción** de una página, o el **dominio** (`siteUrl` en `js/config.js`), ejecuta:

```
powershell -ExecutionPolicy Bypass -File tools\build-pages.ps1
```

Ese script reescribe el encabezado/pie en todas las páginas, las etiquetas para compartir en redes (imagen, título), el ícono, `sitemap.xml` y `robots.txt`.

## Modo público y modo vista previa

Por defecto el sitio público **no muestra** nada que esté a medias:

- No hay carrito ni cuenta; los botones de compra dicen **Me interesa** y llevan a Contacto con el producto ya elegido.
- Los recuadros de ejemplo (videos, logos de Emprendimientos, reseñas de Clientes) **se ven** mientras llegan los reales. Para ocultar los que sigan vacíos al lanzar, pon `showPlaceholders: false` en `js/config.js`. Las cifras solo aparecen si son reales.

Para ver la tienda de demostración (y las cifras de ejemplo) agrega **`?demo=1`** a cualquier dirección, por ejemplo `index.html?demo=1`. `?demo=0` lo apaga.

## Estrategia de precios (3 capas)

Todo parte de dos precios base; el precio de cualquier paquete es consecuencia de lo que trae.

| Capa | Producto | Precio |
|---|---|---|
| Documento suelto | Manual completo (52): se vende **por volumen** (abajo) | $299 MXN base |
| Documento suelto | Documento corto (63): puestos, controles, KPIs, Mystery | $129 MXN |
| Documento suelto | Juegos: Lotería (2) $249 · Memorama (3) $149 | |
| Volumen | **Manuales por volumen:** 1 → $299 · 3 → $799 · 5 → $1,149 · 7 → $1,599 · 10 → $1,949 · 15 → $2,899 · 20 → $3,849 (de $266 a $192 por manual) | |
| Paquete | Categoría de manuales (8) | $549 – $2,499 |
| Paquete | Por tipo: Puestos $2,249 · Controles $1,149 · KPIs $749 · Mystery $579 · Juegos $699 | |
| Paquete | Por perfil (8): Apertura $3,299 · Operación $1,999 · Talento $3,399 · Servicio $1,849 · Marketing $1,299 · Liderazgo $1,349 · Blindaje $1,799 · Finanzas $799 | |
| Ancla | Catálogo completo (115 documentos, sin juegos) | $6,999 (suma suelta $23,675) |

Descuento por volumen de referencia: 2–4 docs 10% · 5–9 25% · 10–20 35% · 21+ 50%.

**Manuales por volumen.** No hay un paquete con todos los manuales. El cliente marca los que quiera y el precio se ajusta solo: se cobra la combinación de paquetes más barata que cubra esa cantidad (4 = 3 + 1 suelto = $1,098; 9 = paquete de 10 = $1,949, y se avisa que puede llevar 1 más sin pagar más). Los escalones se editan en `tools/catalog-config.json` (`prices.volume.manuales`), se regeneran con `tools\build-catalog.ps1` y el servidor deberá recalcular el mismo precio al cobrar. Para llevar más de 20 manuales conviene el Catálogo completo.

## Catálogo de descargables

Todo lo que se vende sale de `js/catalog.js` (generado). Los PDF oficiales viven en la carpeta `Descargables  MIXMIX 2026` (Descargas) y **no se suben a GitHub** (`descargas/` está en `.gitignore`: serían públicos y pesan ~550 MB).

Para regenerar `descargas/` y `js/catalog.js`:

```
powershell -ExecutionPolicy Bypass -File tools\build-catalog.ps1
```

(opcional `-SrcRoot "C:\ruta\a\Descargables  MIXMIX 2026"`). Los nombres de descarga conservan acentos y ñ y llevan el prefijo `MixMix - `. Si un título tiene un error, corrígelo en `tools/catalog-config.json` y vuelve a ejecutar.

## Tienda en línea (carrito, cuenta, compras)

Existe en modo **demostración** (`js/store.js`, `carrito.html`, `cuenta.html`): el login no tiene contraseña y el pago es simulado; todo se guarda en el navegador. Se muestra solo con `?demo=1` o con `storeLive: true` en `js/config.js`.

Para cobrar de verdad hace falta un servidor:

1. **Cuentas**: Supabase Auth (correo + contraseña o enlace mágico) o similar.
2. **Cobro**: función serverless que cree una *Stripe Checkout Session* con el carrito (precios de `js/catalog.js`, validados en el servidor). Reemplaza los ~150 Payment Links.
3. **Webhook de Stripe** (`checkout.session.completed`): guarda el pedido del usuario.
4. **Descargas protegidas**: mover los PDF a almacenamiento privado y entregarlos con enlaces firmados temporales (hoy no están publicados).
5. En `js/store.js` se sustituyen `login()`, `logout()`, `placeOrder()` y `orders()` por llamadas a esas funciones; luego `storeLive: true`.

## Formulario de contacto

Usa **Netlify Forms**: los mensajes llegan al panel de Netlify (Forms > contacto). Activa el aviso por correo en Netlify: *Site configuration > Forms > Form notifications*. Si el envío falla, se abre el correo del visitante con el mensaje ya escrito.

## Pendientes antes de lanzar

- **Contacto real**: `email`, `whatsapp` y `calendarUrl` en `js/config.js` (y el correo en los textos si cambia).
- **Páginas legales**: completar `[NOMBRE O RAZÓN SOCIAL]`, `[DOMICILIO]`, `[FECHA]`, IVA y plazo de reposición en `privacidad.html` y `terminos.html`, y revisarlas con un abogado.
- Redes sociales: direcciones en `js/config.js`; videos en `js/social.js`; logos en `js/reputacion.js`.
- Si el dominio cambia: `siteUrl` en `js/config.js` y volver a ejecutar `tools\build-pages.ps1`.
- Analítica: `plausibleDomain` en `js/config.js` (Plausible, sin cookies) cuando haya cuenta.
- MIX Games – Survival English no apareció en la carpeta de Juegos; al agregarlo, el paquete de juegos pasaría de 5 a 6 juegos (~$849).
