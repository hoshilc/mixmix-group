/* MixMix — logos de "Emprendimientos" y "Nuestros clientes" (index.html).

   PARA PONER UN LOGO:
     1. Guarda el archivo en img/logos/ (mejor PNG con fondo transparente o SVG).
     2. En la lista de abajo, escribe su ruta en logo y el nombre en name:
          { name: "Nombre del negocio", logo: "img/logos/nombre.png", url: "" }
        url es opcional: si lo pones, el logo enlaza a esa página (por ejemplo su Instagram).
   Mientras logo esté vacío ("") se muestra un recuadro de ejemplo (placeholder). */
(function () {
  "use strict";

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

  function cell(item) {
    var has = !!(item.logo && item.logo.trim());
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

  document.querySelectorAll('[data-mm="logos"]').forEach(function (root) {
    var list = LOGOS[root.getAttribute("data-set")] || [];
    root.className = "mm-logos";
    list.forEach(function (item) { root.appendChild(cell(item)); });
    root.hidden = list.length === 0;
  });
})();
