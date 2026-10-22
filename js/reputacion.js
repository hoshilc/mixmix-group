/* MixMix — reputación en Home: logos de "Emprendimientos" y "Nuestros clientes", cifras y testimonios.

   CÓMO FUNCIONA: solo se muestra lo que tenga contenido real. Si una lista está vacía (o solo tiene
   ejemplos), su sección completa se oculta. Con ?demo=1 en la dirección se ven recuadros de ejemplo.

   LOGOS
     1. Guarda el archivo en img/logos/ (mejor PNG con fondo transparente o SVG).
     2. Escribe su ruta en logo y el nombre en name:
          { name: "Nombre del negocio", logo: "img/logos/nombre.png", url: "" }
        url es opcional: si lo pones, el logo enlaza a esa página (por ejemplo su Instagram).
   CIFRAS        { num: "120+", label: "restaurantes asesorados" }      (solo cifras reales y comprobables)
   TESTIMONIOS   { quote: "Lo que dijo el cliente.", name: "Nombre", role: "Cargo, Restaurante" }  (con permiso de la persona) */
(function () {
  "use strict";
  var C = window.MM_CONFIG || {};
  var DEMO = !!C.showPlaceholders;

  var LOGOS = {
    emprendimientos: [
      { name: "Emprendimiento 1", logo: "", url: "" },
      { name: "Emprendimiento 2", logo: "", url: "" },
      { name: "Emprendimiento 3", logo: "", url: "" },
      { name: "Emprendimiento 4", logo: "", url: "" },
      { name: "Emprendimiento 5", logo: "", url: "" }
    ],
    clientes: [
      { name: "Cliente 1", logo: "", url: "" },
      { name: "Cliente 2", logo: "", url: "" },
      { name: "Cliente 3", logo: "", url: "" },
      { name: "Cliente 4", logo: "", url: "" },
      { name: "Cliente 5", logo: "", url: "" }
    ]
  };

  var CIFRAS = [
    { num: "2015", label: "empezamos en el oficio" }
    /* { num: "120+", label: "restaurantes asesorados" }, */
  ];

  var TESTIMONIOS = [
    /* { quote: "Lo que dijo el cliente.", name: "Nombre", role: "Cargo, Restaurante" }, */
  ];
  var TESTIMONIOS_EJEMPLO = [
    { quote: "Aquí va el testimonio de un cliente, con su permiso.", name: "Nombre del cliente", role: "Cargo, Restaurante" },
    { quote: "Aquí va el testimonio de un cliente, con su permiso.", name: "Nombre del cliente", role: "Cargo, Restaurante" },
    { quote: "Aquí va el testimonio de un cliente, con su permiso.", name: "Nombre del cliente", role: "Cargo, Restaurante" }
  ];

  function hasLogo(i) { return !!(i.logo && i.logo.trim()); }

  function logoCell(item) {
    var has = hasLogo(item);
    var box = document.createElement(has && item.url ? "a" : "div");
    box.className = "mm-logo-cell" + (has ? "" : " is-placeholder");
    if (has && item.url) { box.href = item.url; box.target = "_blank"; box.rel = "noopener"; }
    if (has) {
      var img = document.createElement("img");
      img.src = item.logo;
      img.alt = item.name;
      img.loading = "lazy";
      box.appendChild(img);
    } else {
      box.innerHTML = '<span class="mm-logo-ph">Logo</span><span class="mm-logo-ph-name"></span>';
      box.querySelector(".mm-logo-ph-name").textContent = item.name;
    }
    return box;
  }

  /* oculta la sección entera (la <section> que contiene el bloque) si no hay nada que mostrar */
  function toggleSection(root, show) {
    root.hidden = !show;
    var sec = root.closest("section[data-hide-empty]");
    if (sec) sec.hidden = !show;
  }

  document.querySelectorAll('[data-mm="logos"]').forEach(function (root) {
    var all = LOGOS[root.getAttribute("data-set")] || [];
    var list = DEMO ? all : all.filter(hasLogo);
    root.className = "mm-logos";
    list.forEach(function (item) { root.appendChild(logoCell(item)); });
    toggleSection(root, list.length > 0);
  });

  document.querySelectorAll('[data-mm="stats"]').forEach(function (root) {
    var list = CIFRAS.length >= 2 || DEMO ? CIFRAS : [];
    if (DEMO && list.length < 3) list = list.concat([{ num: "00+", label: "dato de ejemplo" }, { num: "00+", label: "dato de ejemplo" }]);
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

  document.querySelectorAll('[data-mm="testimonials"]').forEach(function (root) {
    var list = TESTIMONIOS.length ? TESTIMONIOS : (DEMO ? TESTIMONIOS_EJEMPLO : []);
    root.className = "mm-testimonials";
    list.forEach(function (t) {
      var q = document.createElement("blockquote");
      q.className = "mm-quote mm-testimonial";
      q.innerHTML = '<p></p><cite></cite>';
      q.querySelector("p").textContent = "“" + t.quote + "”";
      q.querySelector("cite").textContent = t.name + (t.role ? " · " + t.role : "");
      root.appendChild(q);
    });
    toggleSection(root, list.length > 0);
  });
})();
