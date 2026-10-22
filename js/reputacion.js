/* MixMix — reputación en Home: logos de "Emprendimientos", reseñas de "Nuestros clientes" y cifras.

   CÓMO FUNCIONA: mientras una lista tenga recuadros de ejemplo (sin logo ni reseña) se muestran como
   placeholders. Cuando pongas los reales, reemplaza el ejemplo. Para ocultar los que queden vacíos al
   lanzar, pon showPlaceholders: false en js/config.js.

   EMPRENDIMIENTOS (logos)
     1. Guarda el archivo en img/logos/ (mejor PNG con fondo transparente o SVG).
     2. Escribe su ruta en logo y el nombre en name:
          { name: "Nombre del negocio", logo: "img/logos/nombre.png", url: "" }
        url es opcional: si lo pones, el logo enlaza a esa página (por ejemplo su Instagram).
   CLIENTES (reseñas)
          { name: "Nombre de la persona", role: "Cargo, Restaurante", logo: "img/logos/restaurante.png",
            quote: "Lo que dijo del trabajo con MixMix." }
        Pon solo reseñas reales y con permiso de la persona.
   CIFRAS        { num: "120+", label: "restaurantes asesorados" }   (solo cifras reales; se muestran si hay 2 o más) */
(function () {
  "use strict";
  var C = window.MM_CONFIG || {};
  var SHOW_PH = !!C.showPlaceholders;

  var EMPRENDIMIENTOS = [
    { name: "Emprendimiento 1", logo: "", url: "" },
    { name: "Emprendimiento 2", logo: "", url: "" },
    { name: "Emprendimiento 3", logo: "", url: "" },
    { name: "Emprendimiento 4", logo: "", url: "" },
    { name: "Emprendimiento 5", logo: "", url: "" }
  ];

  var CLIENTES = [
    { name: "Nombre del cliente", role: "Cargo, Restaurante 1", logo: "", quote: "" },
    { name: "Nombre del cliente", role: "Cargo, Restaurante 2", logo: "", quote: "" },
    { name: "Nombre del cliente", role: "Cargo, Restaurante 3", logo: "", quote: "" },
    { name: "Nombre del cliente", role: "Cargo, Restaurante 4", logo: "", quote: "" },
    { name: "Nombre del cliente", role: "Cargo, Restaurante 5", logo: "", quote: "" }
  ];

  var CIFRAS = [
    { num: "2015", label: "empezamos en el oficio" }
    /* { num: "120+", label: "restaurantes asesorados" }, */
  ];

  function has(s) { return !!(s && String(s).trim()); }

  function logoBox(item, cls) {
    var wrap = document.createElement(has(item.logo) && item.url ? "a" : "div");
    wrap.className = cls + (has(item.logo) ? "" : " is-placeholder");
    if (has(item.logo) && item.url) { wrap.href = item.url; wrap.target = "_blank"; wrap.rel = "noopener"; }
    if (has(item.logo)) {
      var img = document.createElement("img");
      img.src = item.logo; img.alt = item.name; img.loading = "lazy";
      wrap.appendChild(img);
    } else {
      wrap.innerHTML = '<span class="mm-logo-ph">Logo</span><span class="mm-logo-ph-name"></span>';
      wrap.querySelector(".mm-logo-ph-name").textContent = item.name;
    }
    return wrap;
  }

  /* oculta la sección entera (la <section data-hide-empty> que lo contiene) si no hay nada que mostrar */
  function toggleSection(root, show) {
    root.hidden = !show;
    var sec = root.closest("section[data-hide-empty]");
    if (sec) sec.hidden = !show;
  }

  /* ---- emprendimientos: fila de logos ---- */
  document.querySelectorAll('[data-mm="logos"]').forEach(function (root) {
    var list = EMPRENDIMIENTOS.filter(function (i) { return SHOW_PH || has(i.logo); });
    root.className = "mm-logos";
    list.forEach(function (item) { root.appendChild(logoBox(item, "mm-logo-cell")); });
    toggleSection(root, list.length > 0);
  });

  /* ---- clientes: reseñas ---- */
  document.querySelectorAll('[data-mm="reviews"]').forEach(function (root) {
    var list = CLIENTES.filter(function (i) { return SHOW_PH || has(i.quote) || has(i.logo); });
    root.className = "mm-reviews";
    list.forEach(function (c) {
      var real = has(c.quote);
      var card = document.createElement("article");
      card.className = "mm-review" + (real ? "" : " is-placeholder");
      card.appendChild(logoBox({ name: c.role || c.name, logo: c.logo, url: "" }, "mm-review-logo"));
      var q = document.createElement("p");
      q.className = "mm-review-quote";
      q.textContent = real ? "“" + c.quote + "”" : "“Aquí va la reseña del cliente, con su permiso.”";
      card.appendChild(q);
      var by = document.createElement("p");
      by.className = "mm-review-by";
      by.innerHTML = "<strong></strong><span></span>";
      by.querySelector("strong").textContent = c.name;
      by.querySelector("span").textContent = c.role || "";
      card.appendChild(by);
      root.appendChild(card);
    });
    toggleSection(root, list.length > 0);
  });

  /* ---- cifras (solo reales; con ?demo=1 se ven de ejemplo) ---- */
  document.querySelectorAll('[data-mm="stats"]').forEach(function (root) {
    var list = CIFRAS.length >= 2 ? CIFRAS : (C.preview ? CIFRAS.concat([{ num: "00+", label: "dato de ejemplo" }, { num: "00+", label: "dato de ejemplo" }]) : []);
    root.className = "mm-stats mm-stats-strip";
    list.forEach(function (s) {
      var d = document.createElement("div");
      d.className = "mm-stat";
      d.innerHTML = '<span class="mm-stat-num"></span><p class="mm-body" style="font-size:14px"></p>';
      d.querySelector(".mm-stat-num").textContent = s.num;
      d.querySelector("p").textContent = s.label;
      root.appendChild(d);
    });
    toggleSection(root, list.length > 0);
  });
})();
