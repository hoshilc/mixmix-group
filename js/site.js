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

  /* analítica sin cookies (Plausible), solo si hay dominio configurado */
  if (C.plausibleDomain) {
    var s = document.createElement("script");
    s.defer = true;
    s.setAttribute("data-domain", C.plausibleDomain);
    s.src = "https://plausible.io/js/script.js";
    document.head.appendChild(s);
  }
})();
