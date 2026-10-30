/* MixMix — precio de lanzamiento por fases.
   Lee fases.json (el mismo archivo que usa el servidor al cobrar) con la hora del SERVIDOR, no la del reloj del navegador.
   Sin fase en curso no hace nada: precios regulares y sin barra.
   Se carga antes que el catálogo: la lectura es síncrona para que las tarjetas nazcan ya con el precio correcto.
   El precio que se cobra lo calcula el servidor (api/_lib.php, price_cart); esto solo lo muestra igual. */
(function () {
  "use strict";
  var state = { fase: null, zona: "America/Cancun", offset: 0, fases: [] };
  function now() { return Date.now() + state.offset; }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function day(ts) { return new Date(ts).toLocaleDateString("en-CA", { timeZone: state.zona }); }
  function hora(ts) { return new Date(ts).toLocaleTimeString("es-MX", { timeZone: state.zona, hour: "numeric", minute: "2-digit" }); }

  try {
    var x = new XMLHttpRequest();
    x.open("GET", "fases.json?_=" + Date.now(), false);
    x.send();
    if (x.status === 200) {
      var srv = Date.parse(x.getResponseHeader("Date") || "");
      state.offset = isNaN(srv) ? 0 : srv - Date.now();
      var d = JSON.parse(x.responseText);
      if (d && d.zona) state.zona = d.zona;
      state.fases = (d && d.fases) || [];
      for (var i = 0; i < state.fases.length; i++) {
        if (now() >= Date.parse(state.fases[i].inicio) && now() < Date.parse(state.fases[i].fin)) { state.fase = state.fases[i]; break; }
      }
    }
  } catch (e) { state.fase = null; }

  /* kind: "paquete" (paquetes y bundles) o "suelto" (documentos individuales y manuales) */
  function pct(kind) { var f = state.fase; return f ? (kind === "paquete" ? f.paquetes : f.sueltos) || 0 : 0; }
  /* fin de toda la campaña: el último cierre entre las fases (después de esa fecha el precio vuelve al regular) */
  function finCampana() {
    return state.fases.reduce(function (m, f) { return Math.max(m, Date.parse(f.fin) || 0); }, 0);
  }
  function fecha(ts, estilo) { return new Date(ts).toLocaleDateString("es-MX", { timeZone: state.zona, day: "numeric", month: "long" }); }

  /* mismo cálculo que el servidor: redondeo al peso, mismo orden de operaciones */
  function precio(p, kind) { var q = pct(kind); return q > 0 ? Math.round(p * (100 - q) / 100) : p; }

  /* costo de N manuales con los escalones REGULARES (para saber cuánto se ahorra frente al precio normal) */
  function regVol(n, tiers) {
    if (n <= 0) return 0;
    var T = (tiers || []).slice().sort(function (a, b) { return a.n - b.n; });
    if (!T.length) return 0;
    var lim = n + T[T.length - 1].n, INF = 1e12, dp = [0], k, j;
    for (k = 1; k <= lim; k++) {
      dp[k] = INF;
      for (j = 0; j < T.length; j++) if (k >= T[j].n && dp[k - T[j].n] + T[j].price < dp[k]) dp[k] = dp[k - T[j].n] + T[j].price;
    }
    var best = dp[n];
    for (k = n; k <= lim; k++) if (dp[k] < best) best = dp[k];
    return best;
  }

  window.MMFases = {
    current: function () { return state.fase; },
    activa: function () { return !!state.fase; },
    pct: pct,
    price: precio,
    now: now,
    fin: function () { return state.fase ? Date.parse(state.fase.fin) : 0; },
    finTexto: function () {
      if (!state.fase) return "";
      return new Date(Date.parse(state.fase.fin)).toLocaleString("es-MX", { timeZone: state.zona, dateStyle: "long", timeStyle: "short" });
    },
    /* "5 de noviembre": hasta cuándo dura el precio de lanzamiento (toda la campaña) */
    campanaFecha: function () { return fecha(finCampana()); },
    regVol: function (n) { return regVol(n, (window.MM_CATALOG && window.MM_CATALOG.volumeRegular) || []); },
    /* pone el precio de lanzamiento en el catálogo en memoria; guarda el regular en .regular.
       Los manuales conservan decimales para que el total (volumen + descuento) se redondee una sola vez, igual que el servidor. */
    applyTo: function (C) {
      if (!state.fase || !C || C.__fase) return;
      C.__fase = state.fase.id;
      var ps = state.fase.paquetes || 0, ss = state.fase.sueltos || 0;
      function put(o, p, redondear) { o.regular = o.price; var v = o.price * (100 - p) / 100; o.price = redondear ? Math.round(v) : v; }
      C.items.forEach(function (i) { put(i, ss, i.cat !== "manuales"); });
      (C.bundles || []).concat(C.packages || []).forEach(function (p) { put(p, ps, true); });
      C.categories.forEach(function (c) { if (c.price) put(c, ss, c.id !== "manuales"); });
      var vol = C.volume && C.volume.manuales;
      if (vol) { C.volumeRegular = vol.map(function (t) { return { n: t.n, price: t.price }; }); vol.forEach(function (t) { t.price = t.price * (100 - ss) / 100; }); }
    },
    /* aviso del carrito cuando la fase cambió desde la última vez que se abrió */
    avisoCambio: function () {
      var prev = null, cur = state.fase ? state.fase.id : "regular";
      try { prev = localStorage.getItem("mm_cart_fase"); localStorage.setItem("mm_cart_fase", cur); } catch (e) { return ""; }
      if (!prev || prev === cur) return "";
      var nom = function (id) { return id.replace(/-/g, " "); };
      var hacia = cur === "regular" ? "al precio regular" : "al precio de la " + nom(cur);
      return (prev === "regular" ? "Empezó el precio de lanzamiento." : "Terminó la " + nom(prev) + ".") + " Tu carrito se actualizó " + hacia + ".";
    }
  };

  /* ---- barra arriba del menú + elementos de la página que dependen de la fase ---- */
  function onReady(fn) { if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn); else fn(); }
  onReady(function () {
    var f = state.fase;
    document.querySelectorAll('[data-mm-launch="on"]').forEach(function (e) { e.hidden = !f; });
    document.querySelectorAll('[data-mm-launch="off"]').forEach(function (e) { e.hidden = !!f; });
    if (!f) return;
    document.querySelectorAll("[data-mm-launch-fecha]").forEach(function (e) { e.textContent = window.MMFases.campanaFecha(); });
    document.querySelectorAll("[data-mm-launch-pct]").forEach(function (e) { e.textContent = pct(e.getAttribute("data-mm-launch-pct")); });
    document.querySelectorAll("[data-mm-launch-fin]").forEach(function (e) { e.textContent = window.MMFases.finTexto(); });

    var fin = Date.parse(f.fin), header = document.querySelector(".mm-header"), el = null, text = null, timer = null;
    if (header) {
      el = document.createElement("div");
      el.id = "mm-launch"; el.className = "mm-launch";
      el.setAttribute("role", "region"); el.setAttribute("aria-label", "Precio de lanzamiento");
      el.title = "Termina el " + window.MMFases.finTexto();
      el.innerHTML =
        '<span class="mm-tag"><span class="mm-launch-long">Precio de </span>lanzamiento</span>' +
        '<span class="mm-launch-text" id="mm-launch-text"></span>' +
        '<strong class="mm-launch-timer" id="mm-launch-timer"></strong>' +
        '<a class="mm-launch-link" href="descargables.html?utm_source=sitio&utm_medium=barra&utm_campaign=lanzamiento-descargables">Ver descargables</a>' +
        '<span class="mm-launch-sr">Termina el ' + window.MMFases.finTexto().replace(/\.$/, '') + '.</span>';
      header.parentNode.insertBefore(el, header);
      document.documentElement.classList.add("has-launch");
      el.querySelector("a").addEventListener("click", function () { if (window.plausible) window.plausible("clic_barra_timer"); });
      text = document.getElementById("mm-launch-text"); timer = document.getElementById("mm-launch-timer");
      /* la altura de la barra la mide el navegador: el menú se pega justo debajo sin importar cuántas líneas ocupe */
      var sync = function () { document.documentElement.style.setProperty("--mm-launch-h", el.offsetHeight + "px"); };
      if (window.ResizeObserver) new ResizeObserver(sync).observe(el); else window.addEventListener("resize", sync);
      sync();
    }
    function tick() {
      var ms = fin - now();
      if (ms <= 0) {
        /* terminó la fase: se recarga una vez para pasar a la siguiente (o al precio regular) */
        try { if (Date.now() - Number(sessionStorage.getItem("mm_launch_reload") || 0) > 60000) { sessionStorage.setItem("mm_launch_reload", String(Date.now())); location.reload(); return; } } catch (e) {}
        if (el) el.hidden = true;
        document.documentElement.classList.remove("has-launch");
        return;
      }
      var d = Math.floor(ms / 864e5), h = Math.floor(ms / 36e5) % 24, m = Math.floor(ms / 6e4) % 60;
      var cuenta = pad(d) + " D · " + pad(h) + " H · " + pad(m) + " M";
      if (text) {
        text.textContent = ms < 864e5 && fin >= finCampana()
          ? (day(fin) === day(now()) ? "Hoy" : "Mañana") + " a las " + hora(fin) + " regresa el precio regular. Quedan"
          : f.texto || "Termina en";
        timer.textContent = cuenta;
        timer.setAttribute("aria-label", d + " días, " + h + " horas y " + m + " minutos");
      }
      document.querySelectorAll("[data-mm-launch-timer]").forEach(function (e) { e.textContent = cuenta; });
    }
    tick(); setInterval(tick, 30000);
  });
})();
