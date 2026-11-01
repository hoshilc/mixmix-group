/* MixMix — comportamiento común a todas las páginas: menú móvil, enlaces de contacto
   (llamada, WhatsApp, correo) tomados de js/config.js y analítica opcional. */
(function () {
  "use strict";
  var C = window.MM_CONFIG || {};

  /* menú móvil */
  var btn = document.querySelector(".mm-menu-btn"), nav = document.getElementById("mm-nav");
  if (btn && nav) {
    btn.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && nav.classList.contains("is-open")) { nav.classList.remove("is-open"); btn.setAttribute("aria-expanded", "false"); btn.focus(); }
    });
  }

  /* "Agendar llamada gratis": calendario si existe; si no, el formulario de contacto */
  document.querySelectorAll("[data-mm-call]").forEach(function (a) {
    if (C.calendarUrl) { a.href = C.calendarUrl; a.target = "_blank"; a.rel = "noopener"; }
    else if (a.hasAttribute("data-mm-call-needs-calendar")) { a.hidden = true; }
    else { a.href = "contacto.html?interes=llamada"; }
  });

  /* WhatsApp: solo si hay número */
  document.querySelectorAll("[data-mm-whatsapp]").forEach(function (a) {
    if (!C.whatsapp) { a.hidden = true; return; }
    a.href = "https://wa.me/" + C.whatsapp + "?text=" + encodeURIComponent("Hola, vengo de la página de MixMix y quiero información.");
    a.target = "_blank"; a.rel = "noopener";
  });

  /* correo */
  document.querySelectorAll("[data-mm-email]").forEach(function (a) {
    if (!C.email) return;
    a.href = "mailto:" + C.email;
    a.textContent = C.email;
  });

  /* redes sociales (solo las que tengan dirección en config.js) */
  var NETS = [["instagram", "Instagram"], ["tiktok", "TikTok"], ["youtube", "YouTube"], ["facebook", "Facebook"]];
  document.querySelectorAll("[data-mm-social]").forEach(function (box) {
    var list = NETS.filter(function (n) { return C.social && C.social[n[0]]; });
    box.innerHTML = "";
    list.forEach(function (n) {
      var a = document.createElement("a");
      a.href = C.social[n[0]]; a.target = "_blank"; a.rel = "noopener"; a.textContent = n[1];
      box.appendChild(a);
    });
    box.hidden = list.length === 0;
  });

  /* aviso que solo aplica mientras la tienda NO está activa */
  document.querySelectorAll("[data-mm-nostore]").forEach(function (e) { e.hidden = !!C.store; });

  /* texto que solo aplica cuando la tienda está activa */
  document.querySelectorAll("[data-mm-store-only]").forEach(function (e) { e.hidden = !C.store; });

  /* aviso de vista previa (?demo=1) */
  if (C.preview) {
    var b = document.createElement("div");
    b.className = "mm-preview-badge";
    b.innerHTML = 'Vista previa <a href="' + location.pathname + '?demo=0">salir</a>';
    document.body.appendChild(b);
  }

  /* año del pie */
  document.querySelectorAll("[data-mm-year]").forEach(function (e) { e.textContent = new Date().getFullYear(); });

  /* Meta Pixel (solo si hay metaPixelId en config.js). MMTrack.lead / MMTrack.purchase mandan los eventos con un
     identificador (eventID) que el servidor repite en la API de conversiones, para que Meta no los cuente dos veces. */
  var pixelOn = !!(C.metaPixelId && /^[0-9]{5,20}$/.test(String(C.metaPixelId)));
  if (pixelOn) {
    /* código oficial de Meta */
    !function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = "2.0"; n.queue = [];
      t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    }(window, document, "script", "https://connect.facebook.net/en_US/fbevents.js");
    window.fbq("init", String(C.metaPixelId));
    window.fbq("track", "PageView");
  }
  window.MMTrack = {
    lead: function (eventId) {
      if (pixelOn) window.fbq("track", "Lead", {}, { eventID: eventId });
    },
    /* compra: valor en MXN y productos. Solo si el pago es reciente (abrir el enlace días después no cuenta otra compra) y una vez por pedido en este navegador */
    purchase: function (ref, total, lines, paidTs) {
      if (!pixelOn || !ref) return;
      try {
        if (paidTs && Date.now() / 1000 - paidTs > 7200) return;
        var k = "mm_px_p_" + ref;
        if (localStorage.getItem(k)) return;
        localStorage.setItem(k, "1");
      } catch (e) { /* sin almacenamiento: se manda igual; Meta descarta el repetido por eventID */ }
      var L = lines || [];
      window.fbq("track", "Purchase", {
        value: Number(total) || 0, currency: "MXN", content_type: "product",
        content_ids: L.map(function (l) { return String(l.id); }),
        contents: L.map(function (l) { return { id: String(l.id), quantity: 1, item_price: Number(l.price) || 0 }; })
      }, { eventID: "purchase-" + ref });
    }
  };

  /* analítica sin cookies (Plausible), solo si hay dominio configurado */
  if (C.plausibleDomain) {
    var s = document.createElement("script");
    s.defer = true;
    s.setAttribute("data-domain", C.plausibleDomain);
    s.src = "https://plausible.io/js/script.js";
    document.head.appendChild(s);
  }
})();
