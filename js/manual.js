/* MIXMIX — formulario de /manual: valida en español junto a cada campo, evita envíos dobles, guarda los UTM y manda a /manual/gracias.
   El servidor vuelve a validar todo (api/manual.php); esto solo avisa rápido. */
(function () {
  "use strict";
  var form = document.getElementById("mf-form");
  if (!form) return;
  function $(id) { return document.getElementById(id); }
  var FIELDS = { nombre: "f-nombre", whatsapp: "f-whatsapp", correo: "f-correo", tipo: "f-tipo", sucursales: "f-sucursales", consentimiento: "f-consent" };
  var MSG = {
    nombre: "Escribe tu nombre (mínimo 2 letras)",
    whatsapp: "Escribe tu WhatsApp a 10 dígitos",
    correo: "Revisa tu correo: parece que tiene un error",
    tipo: "Elige el tipo de negocio",
    sucursales: "Elige cuántas sucursales tienes",
    consentimiento: "Marca la casilla para recibir tu manual"
  };
  var ERR = {
    too_many: "Hiciste demasiados intentos seguidos. Espera un rato e inténtalo de nuevo.",
    network: "No pudimos conectar. Revisa tu internet e inténtalo de nuevo.",
    server: "Algo falló de nuestro lado. Inténtalo de nuevo en un momento."
  };

  /* UTM: se guardan la primera vez que llegan en esta visita, aunque la persona navegue antes de llenar el formulario */
  var utm = {};
  ["utm_source", "utm_medium", "utm_campaign"].forEach(function (k) {
    var v = "";
    try { v = new URLSearchParams(location.search).get(k) || ""; } catch (e) { /* navegador sin URLSearchParams */ }
    try {
      if (v) sessionStorage.setItem("mm_" + k, v); else v = sessionStorage.getItem("mm_" + k) || "";
    } catch (e) { /* sin almacenamiento */ }
    utm[k] = v.slice(0, 120);
  });

  function setErr(key, text) {
    var input = $(FIELDS[key]), box = $("e-" + (key === "consentimiento" ? "consent" : key));
    if (!box) return;
    box.textContent = text || "";
    box.hidden = !text;
    if (input) { if (text) input.setAttribute("aria-invalid", "true"); else input.removeAttribute("aria-invalid"); }
  }
  function digits(s) {
    var d = String(s).replace(/\D+/g, "");
    if (d.length === 13 && d.indexOf("521") === 0) d = d.slice(3);
    else if (d.length === 12 && d.indexOf("52") === 0) d = d.slice(2);
    return d;
  }
  function validate() {
    var bad = [];
    function check(key, ok) { setErr(key, ok ? "" : MSG[key]); if (!ok) bad.push(key); }
    var nombre = $("f-nombre").value.trim();
    check("nombre", (nombre.match(/[A-Za-zÀ-ÖØ-öø-ÿ]/g) || []).length >= 2);
    check("whatsapp", /^[0-9]{10}$/.test(digits($("f-whatsapp").value)));
    var correo = $("f-correo").value.trim();
    check("correo", correo === "" || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(correo));
    check("tipo", !!$("f-tipo").value);
    check("sucursales", !!$("f-sucursales").value);
    check("consentimiento", $("f-consent").checked);
    return bad;
  }
  /* al corregir un campo, su error desaparece */
  Object.keys(FIELDS).forEach(function (k) {
    var el = $(FIELDS[k]);
    el.addEventListener(k === "tipo" || k === "sucursales" || k === "consentimiento" ? "change" : "input", function () { setErr(k, ""); });
  });

  var btn = $("mf-submit"), note = $("mf-msg"), sending = false;
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (sending) return;
    note.hidden = true;
    var bad = validate();
    if (bad.length) { $(FIELDS[bad[0]]).focus(); return; }
    sending = true; btn.disabled = true; btn.textContent = "Enviando…";
    var body = {
      nombre: $("f-nombre").value.trim(), whatsapp: digits($("f-whatsapp").value), correo: $("f-correo").value.trim(),
      tipo: $("f-tipo").value, sucursales: $("f-sucursales").value, consentimiento: $("f-consent").checked, web: $("f-web").value,
      utm_source: utm.utm_source, utm_medium: utm.utm_medium, utm_campaign: utm.utm_campaign
    };
    fetch("/api/manual.php", { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: "server" }; }); })
      .catch(function () { return { ok: false, error: "network" }; })
      .then(function (d) {
        if (d && d.ok) {
          try { if (d.ct) localStorage.setItem("mm_ct", d.ct); } catch (e) { /* sin almacenamiento */ }   /* liga una compra futura con este contacto */
          location.href = d.next || "/manual/gracias";
          return;
        }
        sending = false; btn.disabled = false; btn.textContent = "Quiero el manual";
        if (d && d.error === "validation" && d.errors) {
          var first = null;
          Object.keys(d.errors).forEach(function (k) { if (FIELDS[k]) { setErr(k, d.errors[k]); if (!first) first = k; } });
          if (first) { $(FIELDS[first]).focus(); return; }
        }
        note.textContent = ERR[d && d.error] || ERR.server;
        note.hidden = false;
      });
  });

  /* botón fijo (celular): baja hasta el formulario; se esconde cuando el formulario ya está a la vista */
  var sticky = $("mf-sticky"), card = $("registro");
  $("mf-sticky-btn").addEventListener("click", function () {
    card.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block: "start" });
    setTimeout(function () { try { $("f-nombre").focus({ preventScroll: true }); } catch (e) { $("f-nombre").focus(); } }, 450);
  });
  if ("IntersectionObserver" in window) {
    new IntersectionObserver(function (en) { sticky.classList.toggle("is-on", !en[0].isIntersecting); }, { threshold: 0.12 }).observe(card);
  } else {
    sticky.classList.add("is-on");
  }
})();
