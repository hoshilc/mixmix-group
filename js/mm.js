/* MixMix — dibuja el catálogo (js/catalog.js) en las páginas.
   Atributos que entiende:
     data-mm="list" data-cat="manuales" [data-group="theme|area"]   lista con búsqueda + filtros cruzados
     data-mm="search"                            buscador de todo el catálogo
     data-mm="packages"                          paquetes por perfil
     data-mm="catalog-offer" / data-mm="games"
     data-mm="bundle-offer" data-bundle="bundle-puestos" [data-lead="..."]
     data-mm-price / data-mm-was / data-mm-save / data-mm-count="ID|total"
     data-mm-buy="ID"                            pone el link de compra (Stripe o sandbox)
*/
(function () {
  "use strict";
  var C = window.MM_CATALOG;
  if (!C) return;

  var items = C.items, bundles = C.bundles, packages = C.packages || [];
  var byId = {}, catById = {}, themeName = {}, areaName = {};
  items.forEach(function (i) { byId[i.id] = i; });
  bundles.forEach(function (b) { byId[b.id] = b; });
  packages.forEach(function (p) { byId[p.id] = p; });
  C.categories.forEach(function (c) { catById[c.id] = c; });
  C.themes.forEach(function (t) { themeName[t.id] = t.name; });
  C.areas.forEach(function (a) { areaName[a.id] = a.name; });

  function byTitle(a, b) { return a.title.localeCompare(b.title, "es"); }
  function money(n) { return "$" + Number(n).toLocaleString("en-US") + " MXN"; }
  function short(n) { return "$" + Number(n).toLocaleString("en-US"); }
  function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function itemsOf(catId) { return items.filter(function (i) { return i.cat === catId; }); }
  /* contenido de un kit (por categorías) o de un paquete (lista de ids) */
  function contentsOf(x) {
    if (x.items) return x.items.map(function (id) { return byId[id]; });
    return items.filter(function (i) { return x.cats.indexOf(i.cat) > -1; });
  }
  function total(list) { return list.reduce(function (a, i) { return a + i.price; }, 0); }
  /* precio por documento: "$81 c/u" */
  function per(price, n) { return short(Math.round(price / n)) + " c/u"; }
  /* archivos que se descargan al comprar un documento (los juegos de lotería traen 2) */
  function filesOf(i) { return i.files || [{ t: i.title, file: i.file }]; }
  function buyHref(x) { return x.stripe ? x.stripe : "gracias.html?manual=" + encodeURIComponent(x.id); }
  /* nombre del archivo al descargar: conserva acentos y ñ, solo quita caracteres no permitidos */
  function niceName(title, ext) {
    var n = title.replace(/[\\\/:*?"<>|]+/g, " - ").replace(/\s+/g, " ").trim();
    if (!/mix/i.test(n)) n = "MixMix - " + n;   /* el archivo se identifica solo en la carpeta de descargas */
    return n + (ext || ".pdf");
  }

  window.MM = {
    catalog: C, byId: byId, catById: catById, itemsOf: itemsOf, contentsOf: contentsOf,
    money: money, buyHref: buyHref, niceName: niceName, themeName: themeName, areaName: areaName, filesOf: filesOf
  };

  /* texto sin acentos ni mayúsculas, para buscar */
  function norm(s) { return String(s).toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); }
  function rowHtml(i, tag) {
    return '<span class="mm-buy-title">' + esc(i.title) + (tag ? ' <span class="mm-row-tag">' + esc(tag) + '</span>' : '') + '</span>' +
      '<span class="mm-buy-price">' + short(i.price) + '</span>' +
      addBtn(i.id, "Agregar", "mm-btn-secondary");
  }
  /* botón de carrito: refreshButtons() le pone el estado (agregar / en el carrito / ya lo tienes) */
  function storeOn() { return !window.MM_CONFIG || !!window.MM_CONFIG.store; }
  function addBtn(id, label, cls) {
    /* sin tienda activa: el botón lleva a Contacto con el producto ya elegido */
    if (!storeOn()) {
      var p = byId[id];
      return '<a class="mm-btn ' + (cls || "") + '" href="contacto.html?interes=' + encodeURIComponent(p ? p.title : id) + '">Me interesa</a>';
    }
    return '<button type="button" class="mm-btn ' + (cls || "") + '" data-add="' + esc(id) + '" data-label="' + esc(label) + '">' + esc(label) + '</button>';
  }

  /* ---------- lista de documentos con filtros cruzados: grupo (categoría/área) x búsqueda ---------- */
  function renderList(root) {
    var cat = catById[root.getAttribute("data-cat")];
    var by = root.getAttribute("data-group");               /* "theme" | "area" | null */
    var groups = by === "theme" ? C.themes : by === "area" ? C.areas : null;
    var gName = by === "theme" ? themeName : areaName;
    var gOrder = groups ? groups.map(function (g) { return g.id; }) : [];
    var list = itemsOf(cat.id).sort(function (a, b) {
      return groups ? ((gOrder.indexOf(a.g) - gOrder.indexOf(b.g)) || byTitle(a, b)) : byTitle(a, b);
    });
    var state = { g: "all", q: "" };

    var tools = el("div", "mm-list-tools");
    var search = null;
    if (list.length > 8) {
      search = el("input", "mm-search");
      search.type = "search";
      search.placeholder = "Buscar en " + cat.name.toLowerCase() + "…";
      search.setAttribute("aria-label", "Buscar en " + cat.name);
      tools.appendChild(search);
    }
    var bar = null, buttons = [];
    if (groups) {
      bar = el("div", "mm-filter-bar");
      bar.setAttribute("role", "group");
      bar.setAttribute("aria-label", "Filtrar por " + (by === "theme" ? "categoría" : "área"));
      bar.style.marginTop = "var(--mm-space-3)";
      [{ id: "all", name: "Todos" }].concat(groups).forEach(function (g) {
        var b = el("button", "mm-filter-btn" + (g.id === "all" ? " active" : ""));
        b.type = "button";
        b.setAttribute("data-filter", g.id);
        b.addEventListener("click", function () { state.g = g.id; apply(); });
        buttons.push({ el: b, id: g.id, name: g.name });
        bar.appendChild(b);
      });
      tools.appendChild(bar);
    }
    var count = el("p", "mm-list-count");
    count.setAttribute("aria-live", "polite");
    tools.appendChild(count);

    var offer = el("div", "mm-bundle-offer");
    offer.hidden = true;
    function showOffer() {
      var b = by === "theme" && state.g !== "all" ? byId["bundle-m-" + state.g] : null;
      if (!b) { offer.hidden = true; return; }
      var l = contentsOf(b);
      offer.innerHTML = '<p>¿Toda la categoría? <strong>' + money(b.price) + '</strong><span class="mm-price-old">' + money(total(l)) + '</span>' +
        ' <span class="mm-per">' + l.length + ' manuales · ' + per(b.price, l.length) + '</span></p>' +
        addBtn(b.id, "Agregar categoría", "mm-btn-secondary");
      offer.hidden = false;
      refreshButtons();
    }

    var ul = el("ul", "mm-buy-list mm-buy-flat");
    var rows = list.map(function (i) {
      var li = el("li", "mm-buy-item", rowHtml(i, groups ? gName[i.g] : ""));
      li.setAttribute("data-g", i.g || "");
      li.setAttribute("data-q", norm(i.title + " " + (gName ? gName[i.g] || "" : "")));
      ul.appendChild(li);
      return li;
    });
    var empty = el("p", "mm-list-empty", "Ningún documento coincide. Prueba con otra palabra o quita el filtro.");
    empty.hidden = true;

    function match(li, g) {
      return (g === "all" || li.getAttribute("data-g") === g) && (!state.q || li.getAttribute("data-q").indexOf(state.q) > -1);
    }
    function apply() {
      var shown = 0;
      rows.forEach(function (li) { var ok = match(li, state.g); li.hidden = !ok; if (ok) shown++; });
      /* los contadores de cada botón respetan la búsqueda: así los filtros se cruzan */
      buttons.forEach(function (b) {
        var n = rows.filter(function (li) { return match(li, b.id); }).length;
        b.el.textContent = b.name + " · " + n;
        b.el.classList.toggle("active", b.id === state.g);
        b.el.classList.toggle("is-empty", n === 0 && b.id !== state.g);
      });
      count.textContent = (state.g !== "all" || state.q) ? "Mostrando " + shown + " de " + rows.length : rows.length + " " + cat.unit;
      empty.hidden = shown !== 0;
      ul.classList.toggle("is-filtered", state.g !== "all");   /* con un grupo elegido, la etiqueta sobra */
      showOffer();
    }
    if (search) search.addEventListener("input", function () { state.q = norm(search.value.trim()); apply(); });

    root.appendChild(tools);
    root.appendChild(offer);
    root.appendChild(ul);
    root.appendChild(empty);
    apply();
  }

  /* ---------- buscador general (todo el catálogo) ---------- */
  function renderSearch(root) {
    var input = el("input", "mm-search mm-search-big");
    input.type = "search";
    input.placeholder = "Buscar un documento: “checklist”, “taquería”, “mesero”, “costos”…";
    input.setAttribute("aria-label", "Buscar en todo el catálogo");
    var out = el("div", "mm-search-results");
    out.hidden = true;
    var index = items.map(function (i) { return { i: i, q: norm(i.title) }; });
    input.addEventListener("input", function () {
      var q = norm(input.value.trim());
      if (q.length < 2) { out.hidden = true; out.innerHTML = ""; return; }
      var hit = index.filter(function (x) { return x.q.indexOf(q) > -1; }).map(function (x) { return x.i; });
      var html = '<p class="mm-list-count">' + hit.length + (hit.length === 1 ? " resultado" : " resultados") + '</p>';
      if (hit.length) {
        html += '<ul class="mm-buy-list mm-buy-flat">' + hit.slice(0, 25).map(function (i) {
          return '<li class="mm-buy-item">' + rowHtml(i, catById[i.cat].name) + '</li>';
        }).join("") + '</ul>';
        if (hit.length > 25) html += '<p class="mm-list-count">Mostrando los primeros 25; escribe más para afinar.</p>';
      } else {
        html += '<p class="mm-list-empty">Ningún documento coincide. Prueba con otra palabra.</p>';
      }
      out.innerHTML = html;
      out.hidden = false;
      refreshButtons();
    });
    root.appendChild(input);
    root.appendChild(out);
  }

  /* ---------- catálogo completo: el ancla, va primero ---------- */
  function renderCatalogOffer(root) {
    var b = byId["catalogo"];
    var list = contentsOf(b);
    var was = total(list);
    var label = { manuales: "manuales", puestos: "descripciones de puesto", controles: "controles imprimibles", kpis: "cuadros de mando", mystery: "formatos de Mystery Shopper" };
    var parts = b.cats.map(function (id) { return itemsOf(id).length + " " + label[id]; });
    var lastPart = parts.pop();
    root.className = "mm-catalog-offer";
    root.innerHTML =
      '<div><p class="mm-tag mm-tag-soft">Todo MixMix en un solo pago</p>' +
      '<h2 class="mm-product-title">Catálogo completo</h2>' +
      '<p class="mm-product-desc">' + list.length + ' documentos: ' + esc(parts.join(", ") + " y " + lastPart) + '. Descarga inmediata, con la marca MixMix lista para imprimir o compartir con tu equipo.</p></div>' +
      '<div class="mm-catalog-offer-buy">' +
      '<div class="mm-price-row"><span class="mm-price">' + money(b.price) + '</span><span class="mm-price-old">' + money(was) + '</span></div>' +
      '<p class="mm-per">' + per(b.price, list.length) + ' · ahorras ' + Math.round((1 - b.price / was) * 100) + '%</p>' +
      addBtn(b.id, "Agregar catálogo completo", "") + '</div>';
  }

  /* ---------- home: los paquetes por perfil como mapa de "quién eres" ---------- */
  function renderHomePackages(root) {
    root.className = "mm-grid mm-grid-fixed mm-pillars-grid";
    packages.filter(function (p) { return !p.parent; }).forEach(function (p, i) {
      var count = contentsOf(p).length + (p.choose ? 1 : 0);
      var a = el("a", "mm-cell mm-home-pkg",
        '<span class="mm-pillar-num">' + (i < 9 ? "0" : "") + (i + 1) + '</span>' +
        '<h3 class="mm-h2">' + esc(p.title) + '</h3>' +
        '<p class="mm-body">' + esc(p.who) + '</p>' +
        '<p class="mm-home-pkg-meta">' + count + ' documentos · ' + money(p.price) + '</p>');
      a.href = "descargables.html#" + p.id;
      root.appendChild(a);
    });
  }

  /* ---------- juegos ---------- */
  function renderGames(root) {
    var grid = el("div", "mm-catalog-grid");
    grid.style.marginTop = "var(--mm-space-4)";
    itemsOf("juegos").forEach(function (g) {
      var n = filesOf(g).length;
      grid.appendChild(el("div", "mm-catalog-card",
        '<p class="mm-tag mm-tag-soft">' + n + (n > 1 ? " archivos" : " archivo") + '</p>' +
        '<h2 class="mm-product-title">' + esc(g.title) + '</h2>' +
        '<p class="mm-product-desc">' + esc(g.desc || "") + '</p>' +
        '<div class="mm-price-row"><span class="mm-price">' + money(g.price) + '</span></div>' +
        addBtn(g.id, "Agregar al carrito", "")));
    });
    root.appendChild(grid);
  }

  /* ---------- paquetes por perfil ---------- */
  function renderPackages(root) {
    var grid = el("div", "mm-catalog-grid");
    grid.style.marginTop = "var(--mm-space-4)";
    packages.filter(function (p) { return !p.parent; }).forEach(function (p) {
      var base = contentsOf(p);
      var variants = p.choose ? p.choose.map(function (id) { return byId[id]; }) : null;
      var giroPrice = variants ? byId[variants[0].giro].price : 0;
      var count = base.length + (variants ? 1 : 0);
      var was = total(base) + giroPrice;

      var inner = '<p class="mm-tag mm-tag-soft">' + count + ' documentos</p>' +
        '<h2 class="mm-product-title">' + esc(p.title) + '</h2>' +
        '<p class="mm-product-desc">' + esc(p.who) + '</p>';
      if (variants) {
        inner += '<label class="mm-pkg-choose">Elige tu giro' +
          '<select class="mm-select">' + variants.map(function (v) {
            return '<option value="' + esc(v.id) + '">' + esc(byId[v.giro].title) + '</option>';
          }).join("") + '</select></label>';
      }
      inner += '<div class="mm-price-row"><span class="mm-price">' + money(p.price) + '</span>' +
        '<span class="mm-price-old">' + money(was) + '</span></div>' +
        '<p class="mm-per">' + per(p.price, count) + ' · ahorras ' + Math.round((1 - p.price / was) * 100) + '%</p>' +
        addBtn(variants ? variants[0].id : p.id, "Agregar paquete", "");

      var card = el("div", "mm-catalog-card", inner);
      card.id = p.id;   /* para enlazar un paquete concreto: descargables.html#pkg-operacion */

      /* qué incluye, agrupado por categoría */
      var d = el("details", "mm-pkg-details");
      d.appendChild(el("summary", null, "Ver qué incluye"));
      var html = "";
      if (variants) html += '<li class="mm-buy-group">A tu elección · 1</li><li>1 manual de apertura del giro que elijas</li>';
      C.categories.forEach(function (c) {
        var g = base.filter(function (i) { return i.cat === c.id; }).sort(byTitle);
        if (!g.length) return;
        html += '<li class="mm-buy-group">' + esc(c.name) + ' · ' + g.length + '</li>' +
          g.map(function (i) { return "<li>" + esc(i.title) + "</li>"; }).join("");
      });
      d.appendChild(el("ul", "mm-checklist mm-theme-items", html));
      card.appendChild(d);

      if (variants) {
        var sel = card.querySelector("select"), btn = card.querySelector(".mm-btn");
        sel.addEventListener("change", function () {
          btn.setAttribute("data-add", sel.value);
          if (btn.tagName === "A") btn.href = "contacto.html?interes=" + encodeURIComponent(byId[sel.value].title);
          refreshButtons();
        });
      }
      grid.appendChild(card);
    });
    root.appendChild(grid);
  }

  /* ---------- oferta de paquete de categoría ---------- */
  function renderBundleOffer(root) {
    var b = byId[root.getAttribute("data-bundle")];
    var list = contentsOf(b), was = total(list);
    root.className = "mm-bundle-offer";
    root.innerHTML = '<p>' + esc(root.getAttribute("data-lead") || "¿Los quieres todos?") +
      ' <strong>Paquete completo ' + money(b.price) + '</strong><span class="mm-price-old">' + money(was) + '</span>' +
      ' <span class="mm-per">' + list.length + ' documentos · ' + per(b.price, list.length) + '</span></p>' +
      addBtn(b.id, "Agregar paquete", "mm-btn-secondary");
  }

  /* ---------- botones de carrito ---------- */
  function refreshButtons() {
    var S = window.MMStore;
    document.querySelectorAll("[data-add]").forEach(function (b) {
      var id = b.getAttribute("data-add"), label = b.getAttribute("data-label") || "Agregar";
      var owned = !!(S && S.owns(id)), inCart = !!(S && S.has(id));
      b.classList.toggle("is-in", inCart && !owned);
      b.classList.toggle("is-owned", owned);
      b.textContent = owned ? "Ya lo tienes" : inCart ? "✓ En el carrito" : label;
      b.setAttribute("aria-pressed", inCart ? "true" : "false");
    });
  }
  document.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest("[data-add]");
    if (!b || !window.MMStore) return;
    var id = b.getAttribute("data-add");
    if (window.MMStore.owns(id)) { location.href = "cuenta.html#descargas"; return; }
    window.MMStore.toggle(id);
  });
  document.addEventListener("mmstore", refreshButtons);

  /* ---------- botones de descarga de lo comprado (cuenta.html) ---------- */
  function dlRow(label, file, name) {
    var li = el("li", "mm-dl-item");
    li.appendChild(el("span", null, esc(label)));
    var a = el("a", "mm-btn mm-btn-secondary", "Descargar");
    a.href = file;
    a.setAttribute("download", name);
    li.appendChild(a);
    return li;
  }
  function downloadsFor(x) {
    var wrap = el("div", "mm-dl");
    var top = el("ul", "mm-dl-list");
    wrap.appendChild(top);
    if (!(x.cats || x.items)) {
      filesOf(x).forEach(function (f) { top.appendChild(dlRow(f.t, f.file, niceName(f.t))); });
      return wrap;
    }
    var list = contentsOf(x);
    var all = [];
    list.forEach(function (i) { filesOf(i).forEach(function (f) { all.push(f); }); });
    if (x.zips) {
      x.zips.forEach(function (z) { top.appendChild(dlRow(z.t + " (todo, .zip)", z.file, niceName(z.t, ".zip"))); });
    } else if (x.file) {
      top.appendChild(dlRow("Todo el paquete (.zip)", x.file, niceName(x.title, ".zip")));
    } else {
      var li = el("li", "mm-dl-item");
      li.appendChild(el("span", null, "Los " + all.length + " archivos, uno tras otro"));
      var b = el("button", "mm-btn mm-btn-secondary", "Descargar todos");
      b.type = "button";
      b.addEventListener("click", function () {
        all.forEach(function (f, n) {
          setTimeout(function () {
            var a = document.createElement("a");
            a.href = f.file; a.download = niceName(f.t);
            document.body.appendChild(a); a.click(); a.remove();
          }, n * 700);
        });
      });
      li.appendChild(b);
      top.appendChild(li);
    }
    var d = el("details", "mm-dl-more");
    d.appendChild(el("summary", null, "Descargar uno por uno (" + all.length + " archivos)"));
    var ul = el("ul", "mm-dl-list");
    C.categories.forEach(function (c) {
      var g = list.filter(function (i) { return i.cat === c.id; }).sort(byTitle);
      if (!g.length) return;
      ul.appendChild(el("li", "mm-dl-group", esc(c.name) + " · " + g.length));
      g.forEach(function (i) { filesOf(i).forEach(function (f) { ul.appendChild(dlRow(f.t, f.file, niceName(f.t))); }); });
    });
    d.appendChild(ul);
    wrap.appendChild(d);
    return wrap;
  }
  window.MM.downloadsFor = downloadsFor;
  window.MM.refreshButtons = refreshButtons;


  document.querySelectorAll("[data-mm]").forEach(function (root) {
    var kind = root.getAttribute("data-mm");
    if (kind === "list") renderList(root);
    else if (kind === "search") renderSearch(root);
    else if (kind === "packages") renderPackages(root);
    else if (kind === "catalog-offer") renderCatalogOffer(root);
    else if (kind === "home-packages") renderHomePackages(root);
    else if (kind === "games") renderGames(root);
    else if (kind === "bundle-offer") renderBundleOffer(root);
  });

  /* secciones colapsables (<details class="mm-acc">): los enlaces #ancla las abren */
  function openFromHash(id, scroll) {
    var t = id && document.getElementById(id);
    if (!t) return;
    if (t.tagName === "DETAILS") t.open = true;
    var parent = t.closest && t.closest("details");   /* una tarjeta dentro de una sección colapsada */
    if (parent) parent.open = true;
    if (scroll) t.scrollIntoView({ behavior: "auto" });   /* al cargar con #ancla: salto directo, sin animación */
  }
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener("click", function () { openFromHash(a.getAttribute("href").slice(1), false); });
  });
  window.addEventListener("hashchange", function () { openFromHash(location.hash.slice(1), false); });
  openFromHash(location.hash.slice(1), true);
  refreshButtons();

  document.querySelectorAll("[data-mm-price]").forEach(function (e) {
    e.textContent = money(byId[e.getAttribute("data-mm-price")].price);
  });
  document.querySelectorAll("[data-mm-was]").forEach(function (e) {
    e.textContent = money(total(contentsOf(byId[e.getAttribute("data-mm-was")])));
  });
  document.querySelectorAll("[data-mm-save]").forEach(function (e) {
    var b = byId[e.getAttribute("data-mm-save")];
    e.textContent = Math.round((1 - b.price / total(contentsOf(b))) * 100) + "%";
  });
  document.querySelectorAll("[data-mm-count]").forEach(function (e) {
    var k = e.getAttribute("data-mm-count");
    var n;
    if (k === "total") n = items.filter(function (i) { return i.cat !== "juegos"; }).length;
    else if (k === "packages") n = packages.filter(function (p) { return !p.parent; }).length;
    else if (catById[k]) n = itemsOf(k).length;
    else n = contentsOf(byId[k]).length;
    e.textContent = n;
  });
  document.querySelectorAll("[data-mm-unit]").forEach(function (e) {
    e.textContent = short(catById[e.getAttribute("data-mm-unit")].price) + " c/u";
  });
  document.querySelectorAll("[data-mm-buy]").forEach(function (e) {
    e.setAttribute("href", buyHref(byId[e.getAttribute("data-mm-buy")]));
  });
})();
