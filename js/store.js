/* MixMix — carrito, cuenta y compras.
   MODO DEMOSTRACIÓN: todo vive en este navegador (localStorage); el "pago" y el inicio de sesión
   son simulados para poder ver y probar el diseño. Para cobrar de verdad hace falta un servidor
   (ver README: "Tienda en línea"). La interfaz (carrito.html, cuenta.html, botones) no cambia:
   solo se sustituyen login(), placeOrder() y la lista de compras por llamadas al servidor.

   API pública: window.MMStore
     cart() has(id) add(id) remove(id) toggle(id) clear()
     user() login(email, name) logout()
     orders() placeOrder() owns(id) ownedItems()
   Evento: "mmstore" en document cada vez que cambia algo.
*/
(function () {
  "use strict";

  /* localStorage puede fallar (navegación privada): se cae a memoria */
  var mem = {};
  function read(k, d) {
    try { var v = window.localStorage.getItem(k); return v ? JSON.parse(v) : d; }
    catch (e) { return mem[k] !== undefined ? mem[k] : d; }
  }
  function write(k, v) {
    mem[k] = v;
    try { window.localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* sin almacenamiento */ }
  }
  var K_CART = "mm_cart", K_USER = "mm_user", K_ORDERS = "mm_orders";

  function M() { return window.MM; }            /* lo define mm.js (necesita catalog.js) */
  function product(id) { return M() ? M().byId[id] : null; }

  var S = {};

  /* ---------- carrito ---------- */
  S.cart = function () { return read(K_CART, []).filter(function (id) { return !M() || product(id); }); };
  S.has = function (id) { return S.cart().indexOf(id) > -1; };
  S.add = function (id) {
    if (!storeOn()) return;
    var c = S.cart();
    if (c.indexOf(id) === -1) { c.push(id); write(K_CART, c); changed(id, "add"); }
  };
  S.remove = function (id) {
    write(K_CART, S.cart().filter(function (x) { return x !== id; }));
    changed(id, "remove");
  };
  S.toggle = function (id) { if (S.has(id)) S.remove(id); else S.add(id); };
  S.clear = function () { write(K_CART, []); changed(null, "clear"); };

  /* ---------- cuenta (demo: sin contraseña) ---------- */
  S.user = function () { return read(K_USER, null); };
  S.login = function (email, name) {
    email = String(email || "").trim().toLowerCase();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return { ok: false, error: "Escribe un correo válido." };
    var known = read("mm_users", {});
    var nm = String(name || "").trim() || (known[email] && known[email].name) || email.split("@")[0];
    known[email] = { name: nm };
    write("mm_users", known);
    write(K_USER, { email: email, name: nm });
    changed(null, "login");
    return { ok: true };
  };
  S.logout = function () { write(K_USER, null); changed(null, "logout"); };

  /* ---------- compras ---------- */
  S.orders = function () {
    var u = S.user();
    if (!u) return [];
    return (read(K_ORDERS, {})[u.email] || []).slice().reverse();   /* la más reciente primero */
  };
  S.placeOrder = function () {
    var u = S.user(), c = S.cart();
    if (!u || !c.length || !M()) return null;
    var all = read(K_ORDERS, {});
    var list = all[u.email] || [];
    var lines = c.map(function (id) { var p = product(id); return { id: id, title: p.title, price: p.price }; });
    var order = {
      id: "MM-" + Date.now().toString(36).toUpperCase(),
      date: new Date().toISOString(),
      lines: lines,
      total: lines.reduce(function (a, l) { return a + l.price; }, 0)
    };
    list.push(order);
    all[u.email] = list;
    write(K_ORDERS, all);
    write(K_CART, []);
    changed(null, "order");
    return order;
  };
  /* ids de documentos que ya compró el usuario (incluye lo que trae cada paquete) */
  S.ownedItems = function () {
    var set = {};
    if (!M()) return set;
    S.orders().forEach(function (o) {
      o.lines.forEach(function (l) {
        var p = product(l.id);
        if (!p) return;
        if (p.cat) set[p.id] = true;
        else M().contentsOf(p).forEach(function (i) { set[i.id] = true; });
      });
    });
    return set;
  };
  /* ¿ya tiene todo lo que trae este producto? */
  S.owns = function (id) {
    var p = product(id);
    if (!p || !S.user()) return false;
    var own = S.ownedItems();
    if (p.cat) return !!own[p.id];
    var list = M().contentsOf(p);
    return list.length > 0 && list.every(function (i) { return own[i.id]; });
  };

  /* ---------- eventos y encabezado ---------- */
  function changed(id, what) {
    paintHeader();
    document.dispatchEvent(new CustomEvent("mmstore", { detail: { id: id, what: what } }));
    if (what === "add") toast(id);
  }

  var toastTimer;
  function toast(id) {
    var p = product(id);
    var t = document.getElementById("mm-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "mm-toast";
      t.className = "mm-toast";
      t.setAttribute("role", "status");
      document.body.appendChild(t);
    }
    t.innerHTML = '<span>' + (p ? esc(p.title) : "Producto") + ' agregado al carrito</span><a href="carrito.html">Ver carrito</a>';
    t.classList.add("is-on");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove("is-on"); }, 3500);
  }
  function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }

  /* la tienda (carrito + cuenta) solo se muestra si js/config.js la activa (storeLive o ?demo=1) */
  function storeOn() { return !(window.MM_CONFIG) || !!window.MM_CONFIG.store; }

  function paintHeader() {
    var u = S.user(), n = read(K_CART, []).length;
    var acct = document.querySelector("[data-mm-account]");
    var cart = document.querySelector("[data-mm-cartlink]");
    if (acct) { acct.title = u ? "Mi cuenta (" + u.name + ")" : "Mi cuenta"; acct.classList.toggle("is-in", !!u); }
    if (cart) {
      cart.setAttribute("aria-label", "Carrito" + (n ? ", " + n + (n === 1 ? " producto" : " productos") : ""));
      var badge = cart.querySelector(".mm-cart-count");
      if (badge) { badge.textContent = n; badge.hidden = !n; }
    }
  }
  function injectHeader() {
    var box = document.querySelector(".mm-header .mm-container");
    if (!box || box.querySelector("[data-mm-account]") || !storeOn()) return;
    var here = location.pathname.split("/").pop();
    function cur(href) { return here === href ? ' aria-current="page"' : ""; }
    var userSvg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/></svg>';
    var bagSvg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>';
    var util = document.createElement("div");
    util.className = "mm-header-util";
    util.innerHTML =
      '<a href="cuenta.html" data-mm-account aria-label="Mi cuenta" title="Mi cuenta"' + cur("cuenta.html") + '>' + userSvg + '</a>' +
      '<a href="carrito.html" data-mm-cartlink aria-label="Carrito"' + cur("carrito.html") + '>' + bagSvg + '<span class="mm-cart-count" hidden>0</span></a>';
    var btn = box.querySelector(".mm-menu-btn");
    box.insertBefore(util, btn || null);
    paintHeader();
  }

  window.MMStore = S;
  document.addEventListener("DOMContentLoaded", function () {
    injectHeader();
    document.dispatchEvent(new CustomEvent("mmstore", { detail: { what: "ready" } }));
  });
  if (document.readyState !== "loading") {
    injectHeader();
    document.dispatchEvent(new CustomEvent("mmstore", { detail: { what: "ready" } }));
  }
  /* si el carrito cambia en otra pestaña */
  window.addEventListener("storage", function () { paintHeader(); document.dispatchEvent(new CustomEvent("mmstore", { detail: { what: "sync" } })); });
})();
