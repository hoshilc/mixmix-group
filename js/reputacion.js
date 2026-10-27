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
    { name: "Reyna Sabor Mexicano", logo: "img/logos/reyna-sabor-mexicano.png", url: "" },
    { name: "Kruji Fiesta", logo: "img/logos/kruji-fiesta.png", url: "" },
    { name: "El Rey Elote", logo: "img/logos/el-rey-elote.png", url: "" },
    { name: "Las Tremendonas", logo: "img/logos/las-tremendonas.png", url: "" }
  ];

  /* logos de clientes: se muestran como una barra que se desplaza sola en bucle (data-mm="logos" data-set="clientes") */
  var CLIENTES_LOGOS = [
    { name: "Rostizados Sinaloa", logo: "img/logos/rostizados-sinaloa.png" },
    { name: "Las Piñas de Cholula", logo: "img/logos/las-pinas-de-cholula.png" },
    { name: "Restaurante y Bar El Mesón de Don Jorge", logo: "img/logos/el-meson-de-don-jorge.png" },
    { name: "Iván Aguilera Eventos", logo: "img/logos/ivan-aguilera-eventos.png" },
    { name: "El Ranchito Restaurante", logo: "img/logos/el-ranchito.png" },
    { name: "Experiencias Líquidas", logo: "img/logos/experiencias-liquidas.png" },
    { name: "RB To Go", logo: "img/logos/rb-to-go.png" },
    { name: "El Playense Mariscos", logo: "img/logos/el-playense.png" }
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
      /* logo a color desde el inicio (los de clientes, en cambio, van en la barra de un solo tono) */
      var img = document.createElement("img");
      img.src = item.logo; img.alt = item.name; img.loading = "lazy"; img.width = 480; img.height = 340;
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
    if (sec) sec.hidden = !sec.querySelector("[data-mm]:not([hidden])");   /* la sección se oculta solo si ninguno de sus bloques tiene contenido */
  }

  /* ---- barra de logos en bucle: la lista se repite varias veces y la pista se mueve el ancho de una copia ---- */
  function marquee(root, list) {
    root.innerHTML = "";
    root.className = "mm-marquee";
    var track = document.createElement("div");
    track.className = "mm-marquee-track";
    var COPIES = 4;   /* con 4 copias la pista siempre es más ancha que cualquier pantalla y el bucle no deja huecos */
    track.style.setProperty("--mm-copies", COPIES);
    track.style.animationDuration = Math.max(24, list.length * 7.5) + "s";   /* misma velocidad sin importar cuántos logos haya */
    for (var copy = 0; copy < COPIES; copy++) (function (copy) {
      var group = document.createElement("ul");
      group.className = "mm-marquee-group";
      if (copy === 1) group.setAttribute("aria-hidden", "true");
      list.forEach(function (item) {
        var li = document.createElement("li");
        /* dos capas: la versión de un solo tono (por defecto) y la de color (al pasar el cursor) */
        var mono = document.createElement("img");
        mono.className = "mm-logo-mono"; mono.src = item.logo.replace(/.png$/, "-mono.png"); mono.alt = copy === 0 ? item.name : ""; mono.loading = "lazy"; mono.width = 400; mono.height = 240;
        var color = document.createElement("img");
        color.className = "mm-logo-color"; color.src = item.logo; color.alt = ""; color.loading = "lazy"; color.width = 400; color.height = 240;
        li.appendChild(mono); li.appendChild(color);
        group.appendChild(li);
      });
      track.appendChild(group);
    })(copy);
    root.appendChild(track);
    toggleSection(root, list.length > 0);
  }

  /* ---- emprendimientos: fila de logos ---- */
  document.querySelectorAll('[data-mm="logos"]').forEach(function (root) {
    if (root.getAttribute("data-set") === "clientes") { marquee(root, CLIENTES_LOGOS.filter(function (i) { return has(i.logo); })); return; }
    var list = EMPRENDIMIENTOS.filter(function (i) { return SHOW_PH || has(i.logo); });
    root.className = "mm-logos" + (list.length === 4 ? " mm-logos-4" : "");
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
