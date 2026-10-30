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

/* ---- Precio de lanzamiento: barra con cuenta regresiva y funciones de precio para el resto del sitio ----
   Lee fases.json y usa la hora del SERVIDOR (encabezado Date), no la del reloj del navegador. Sin fase en curso no muestra nada.
   El precio que se cobra lo calcula el servidor (api/_lib.php, price_cart); esto solo informa. */
(function () {
  "use strict";
  var state = { fase: null, zona: "America/Cancun", offset: 0 };
  function now() { return Date.now() + state.offset; }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function day(ts) { return new Date(ts).toLocaleDateString("en-CA", { timeZone: state.zona }); }
  function hora(ts) { return new Date(ts).toLocaleTimeString("es-MX", { timeZone: state.zona, hour: "numeric", minute: "2-digit" }); }

  /* kind: "paquete" (paquetes y bundles) o "suelto" (documentos individuales y manuales) */
  function pct(kind) { var f = state.fase; return f ? (kind === "paquete" ? f.paquetes : f.sueltos) || 0 : 0; }
  window.MMFases = {
    ready: null,
    current: function () { return state.fase; },
    pct: pct,
    price: function (precio, kind) { var p = pct(kind); return p > 0 ? Math.round(precio * (100 - p) / 100) : precio; },
    fin: function () { return state.fase ? Date.parse(state.fase.fin) : 0; },
    finTexto: function () {
      if (!state.fase) return "";
      return new Date(Date.parse(state.fase.fin)).toLocaleString("es-MX", { timeZone: state.zona, dateStyle: "long", timeStyle: "short" });
    },
    now: now
  };

  function bar(f) {
    var header = document.querySelector(".mm-header");
    if (!header) return;
    var fin = Date.parse(f.fin);
    var el = document.createElement("div");
    el.id = "mm-launch"; el.className = "mm-launch";
    el.setAttribute("role", "region"); el.setAttribute("aria-label", "Precio de lanzamiento");
    el.title = "Termina el " + window.MMFases.finTexto();
    el.innerHTML =
      '<span class="mm-tag"><span class="mm-launch-long">Precio de </span>lanzamiento</span>' +
      '<span class="mm-launch-text" id="mm-launch-text"></span>' +
      '<strong class="mm-launch-timer" id="mm-launch-timer"></strong>' +
      '<a class="mm-launch-link" href="descargables.html?utm_source=sitio&utm_medium=barra&utm_campaign=lanzamiento-descargables">Ver descargables</a>' +
      '<span class="mm-launch-sr">Termina el ' + window.MMFases.finTexto() + '.</span>';
    header.parentNode.insertBefore(el, header);
    document.documentElement.classList.add("has-launch");
    el.querySelector("a").addEventListener("click", function () { if (window.plausible) window.plausible("clic_barra_timer"); });
    var text = document.getElementById("mm-launch-text"), timer = document.getElementById("mm-launch-timer");
    function tick() {
      var ms = fin - now();
      if (ms <= 0) {
        /* terminó la fase: se recarga una vez para quitar la barra y volver a precio regular */
        try { if (Date.now() - Number(sessionStorage.getItem("mm_launch_reload") || 0) > 60000) { sessionStorage.setItem("mm_launch_reload", String(Date.now())); location.reload(); return; } } catch (e) {}
        el.hidden = true; document.documentElement.classList.remove("has-launch"); return;
      }
      var d = Math.floor(ms / 864e5), h = Math.floor(ms / 36e5) % 24, m = Math.floor(ms / 6e4) % 60;
      if (ms < 864e5) {
        text.textContent = (day(fin) === day(now()) ? "Hoy" : "Mañana") + " a las " + hora(fin) + " regresa el precio regular. Quedan";
      } else {
        text.textContent = f.texto || "Termina en";
      }
      timer.textContent = pad(d) + " D · " + pad(h) + " H · " + pad(m) + " M";
      timer.setAttribute("aria-label", d + " días, " + h + " horas y " + m + " minutos");
    }
    /* la altura de la barra la mide el navegador: el menú se pega justo debajo sin importar cuántas líneas ocupe */
    function sync() { document.documentElement.style.setProperty("--mm-launch-h", el.offsetHeight + "px"); }
    if (window.ResizeObserver) new ResizeObserver(sync).observe(el); else window.addEventListener("resize", sync);
    tick(); sync(); setInterval(tick, 30000);
  }

  window.MMFases.ready = fetch("fases.json", { cache: "no-store" })
    .then(function (res) {
      var srv = Date.parse(res.headers.get("Date") || "");
      state.offset = isNaN(srv) ? 0 : srv - Date.now();
      return res.json();
    })
    .then(function (d) {
      if (d && d.zona) state.zona = d.zona;
      var fases = (d && d.fases) || [];
      for (var i = 0; i < fases.length; i++) {
        if (now() >= Date.parse(fases[i].inicio) && now() < Date.parse(fases[i].fin)) { state.fase = fases[i]; break; }
      }
      if (state.fase) bar(state.fase);
      return state.fase;
    })
    .catch(function () { return null; });
})();
