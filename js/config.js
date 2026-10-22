/* MixMix — configuración del sitio. Edita SOLO este archivo para activar cosas.

   storeLive   false = el sitio NO muestra carrito ni cuenta, y los botones de compra dicen
                       "Me interesa" (llevan a Contacto con el producto ya elegido).
               true  = tienda real (solo cuando el cobro con Stripe y las cuentas estén conectados).
   whatsapp    solo dígitos con lada de país, p. ej. "528112345678". Vacío = no se muestra.
   calendarUrl enlace de tu calendario (Cal.com, Calendly, Google Calendar). Vacío = "Agendar llamada
               gratis" lleva al formulario de Contacto.
   plausibleDomain  dominio registrado en Plausible (analítica sin cookies). Vacío = sin analítica.

   VISTA PREVIA: agrega ?demo=1 a cualquier dirección del sitio (p. ej. index.html?demo=1) para ver
   la tienda de demostración y los recuadros de ejemplo (videos, logos, testimonios). ?demo=0 lo apaga. */
window.MM_CONFIG = {
  siteUrl: "https://mixmixgroup.netlify.app",
  storeLive: false,
  email: "contacto@grupomixmix.com",
  whatsapp: "",
  calendarUrl: "",
  plausibleDomain: "",
  /* redes sociales: pega la dirección completa de cada perfil; las vacías no se muestran */
  social: { instagram: "", tiktok: "", youtube: "", facebook: "" }
};

(function () {
  "use strict";
  var C = window.MM_CONFIG, on = false;
  try {
    var q = new URLSearchParams(location.search).get("demo");
    if (q === "1") sessionStorage.setItem("mm_preview", "1");
    if (q === "0") sessionStorage.removeItem("mm_preview");
    on = sessionStorage.getItem("mm_preview") === "1";
  } catch (e) {
    on = /[?&]demo=1/.test(location.search);
  }
  C.preview = on;
  C.store = !!C.storeLive || on;     /* ¿se muestran carrito, cuenta y botones "Agregar"? */
  C.showPlaceholders = on;           /* ¿se muestran recuadros de ejemplo? */
})();
