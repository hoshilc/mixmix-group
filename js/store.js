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

  /* ---------- modo real: cuentas y pagos en el servidor (carpeta api/) ----------
     Se activa solo cuando js/config.js lo permite (useApi). La sesión vive en una cookie del servidor;
     aquí solo se guarda una copia para pintar rápido y se vuelve a pedir al cargar cada página. */
  function live() { return !!(window.MM_CONFIG && window.MM_CONFIG.useApi); }
  S.live = live;
  var LIVE = read("mm_live", { user: null, orders: [], mode: "test" });
  function api(path, body) {
    var opt = { credentials: "same-origin", headers: {} };
    if (body !== undefined) { opt.method = "POST"; opt.headers["Content-Type"] = "application/json"; opt.body = JSON.stringify(body); }
    return fetch(window.MM_CONFIG.api + path, opt)
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: "server" }; }); })
      .catch(function () { return { ok: false, error: "network" }; });
  }
  S.refresh = function () {
    if (!live()) return Promise.resolve(null);
    return api("me.php").then(function (d) {
      if (d && d.ok) { LIVE = { user: d.user, orders: d.orders || [], mode: d.stripe_mode || "test", google: d.google_client_id || "" }; write("mm_live", LIVE); }
      changed(null, "sync");
      return d;
    });
  };
  S.apiRegister = function (name, email, password) {
    return api("register.php", { name: name, email: email, password: password })
      .then(function (d) { return d.ok ? S.refresh().then(function () { changed(null, "login"); return d; }) : d; });
  };
  S.apiLogin = function (email, password) {
    return api("login.php", { email: email, password: password })
      .then(function (d) { return d.ok ? S.refresh().then(function () { changed(null, "login"); return d; }) : d; });
  };
  S.apiLogout = function () {
    return api("logout.php", {}).then(function () {
      LIVE = { user: null, orders: [], mode: LIVE.mode }; write("mm_live", LIVE); changed(null, "logout");
    });
  };
  S.apiForgot = function (email) { return api("forgot.php", { email: email }); };
  S.apiReset = function (token, password) {
    return api("reset.php", { token: token, password: password })
      .then(function (d) { return d.ok ? S.refresh().then(function () { changed(null, "login"); return d; }) : d; });
  };
  S.apiGoogle = function (credential) {
    return api("google.php", { credential: credential })
      .then(function (d) { return d.ok ? S.refresh().then(function () { changed(null, "login"); return d; }) : d; });
  };
  S.googleId = function () { return LIVE.google || ""; };  S.checkout = function () { return api("checkout.php", { ids: S.cart() }); };
  S.confirm = function (sessionId) { return api("confirm.php?session_id=" + encodeURIComponent(sessionId)); };
  S.stripeMode = function () { return LIVE.mode; };

  /* ---------- cuenta (demo: sin contraseña) ---------- */
  S.user = function () { return live() ? LIVE.user : read(K_USER, null); };
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
    if (live()) return LIVE.orders.slice();                            /* el servidor ya las manda de la más reciente a la más antigua */
    return (read(K_ORDERS, {})[u.email] || []).slice().reverse();   /* la más reciente primero */
  };
  /* precio del carrito: los manuales sueltos se cobran por volumen (paquetes de 1, 3, 5, 7, 10, 15 y 20);
     lo demás (documentos cortos, juegos, paquetes) va a su precio. */
  S.pricing = function (ids) {
    ids = ids || S.cart();
    var prods = ids.map(product).filter(Boolean);
    var man = prods.filter(function (p) { return p.cat === "manuales"; });
    var other = prods.filter(function (p) { return p.cat !== "manuales"; }).reduce(function (a, p) { return a + p.price; }, 0);
    var base = man.reduce(function (a, p) { return a + p.price; }, 0);
    var vc = (M() && man.length) ? M().volumeCost(man.length) : { cost: base, slots: man.length, plan: [] };
    var cost = man.length ? Math.min(vc.cost, base) : 0;
    return { manuals: man.length, manualsBase: base, manualsCost: cost, discount: base - cost, other: other, total: other + cost,
             extra: (M() && man.length) ? M().volumeExtra(man.length) : 0, plan: vc.plan };
  };
  S.placeOrder = function () {
    var u = S.user(), c = S.cart();
    if (!u || !c.length || !M()) return null;
    var all = read(K_ORDERS, {});
    var list = all[u.email] || [];
    var lines = c.map(function (id) { var p = product(id); return { id: id, title: p.title, price: p.price }; });
    var pr = S.pricing(c);
    var order = {
      id: "MM-" + Date.now().toString(36).toUpperCase(),
      date: new Date().toISOString(),
      lines: lines,
      discount: pr.discount,
      total: pr.total
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
  function accountOn() { return !(window.MM_CONFIG) || !!window.MM_CONFIG.account; }
  function injectHeader() {
    var box = document.querySelector(".mm-header .mm-container");
    var util = box && box.querySelector(".mm-header-util");
    if (!box) return;
    var here = location.pathname.split("/").pop();
    function cur(href) { return here === href ? ' aria-current="page"' : ""; }
    if (!util) {                                   /* página sin el icono en su HTML: se crea */
      util = document.createElement("div");
      util.className = "mm-header-util";
      util.innerHTML = '<a href="cuenta.html" data-mm-account aria-label="Mi cuenta" title="Mi cuenta"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/></svg></a>';
      box.insertBefore(util, box.querySelector(".mm-menu-btn") || null);
    }
    util.hidden = !accountOn();                    /* fuera del sitio real (vista previa, local) el icono solo sale con ?demo=1 */
    var acct = util.querySelector("[data-mm-account]");
    if (acct && here === "cuenta.html") acct.setAttribute("aria-current", "page");
    if (storeOn() && !util.querySelector("[data-mm-cartlink]")) {
      var bagSvg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>';
      util.insertAdjacentHTML("beforeend", '<a href="carrito.html" data-mm-cartlink aria-label="Carrito"' + cur("carrito.html") + '>' + bagSvg + '<span class="mm-cart-count" hidden>0</span></a>');
    }
    paintHeader();
  }
  window.MMStore = S;
  document.addEventListener("DOMContentLoaded", function () {
    injectHeader();
    document.dispatchEvent(new CustomEvent("mmstore", { detail: { what: "ready" } }));
    if (live() && !window.__mmNoAutoRefresh) S.refresh();
  });
  if (document.readyState !== "loading") {
    injectHeader();
    document.dispatchEvent(new CustomEvent("mmstore", { detail: { what: "ready" } }));
    if (live() && !window.__mmNoAutoRefresh) S.refresh();
  }
  /* si el carrito cambia en otra pestaña */
  window.addEventListener("storage", function () { paintHeader(); document.dispatchEvent(new CustomEvent("mmstore", { detail: { what: "sync" } })); });
})();
