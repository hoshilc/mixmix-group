/* MixMix — videos de redes sociales en la sección "Evidencia" (index.html).

   PARA PONER UN VIDEO: pega su enlace en el campo url de la lista de abajo.
     { url: "https://www.instagram.com/reel/XXXXXXXXXXX/", title: "Texto corto" }
   Mientras url esté vacío ("") se muestra un recuadro de ejemplo (placeholder).

   Enlaces que entiende:
     YouTube     https://www.youtube.com/watch?v=ID · https://youtu.be/ID · https://www.youtube.com/shorts/ID
     Instagram   https://www.instagram.com/reel/CODIGO/ · /p/CODIGO/
     TikTok      https://www.tiktok.com/@usuario/video/NUMERO   (el enlace largo, no vm.tiktok.com)
     Facebook    https://www.facebook.com/.../videos/NUMERO · https://fb.watch/...

   Cada video se ve como una tarjeta vertical (los 4 caben en pantalla sin hacer scroll); al dar
   clic se abre ampliado en una ventana. El video NO se carga hasta ese clic: la página abre rápido
   y las redes sociales no rastrean a quien solo está leyendo. Si la lista está vacía, no se muestra nada. */
(function () {
  "use strict";

  var VIDEOS = [
    { url: "", title: "Las marcas que levantamos" },
    { url: "", title: "Los negocios que construimos" },
    { url: "", title: "Las asesorías que impartimos" },
    { url: "", title: "Lo que aprendimos de lo que no funcionó" }
  ];

  function yt(id, short) {
    if (!id || !/^[\w-]{6,15}$/.test(id)) return null;
    return { name: "YouTube", src: "https://www.youtube-nocookie.com/embed/" + id, ratio: short ? 9 / 16 : 16 / 9, autoplay: true };
  }

  function parse(u) {
    var x;
    try { x = new URL(u); } catch (e) { return null; }
    var h = x.hostname.replace(/^(www|m)\./, "");
    var p = x.pathname.split("/").filter(Boolean);
    if (h === "youtu.be") return yt(p[0], false);
    if (h === "youtube.com") {
      if (p[0] === "shorts") return yt(p[1], true);
      if (p[0] === "watch") return yt(x.searchParams.get("v"), false);
      if (p[0] === "embed") return yt(p[1], false);
      return null;
    }
    if (h === "instagram.com") {
      for (var i = 0; i < p.length - 1; i++) {
        if (p[i] === "reel" || p[i] === "p" || p[i] === "tv") {
          return { name: "Instagram", src: "https://www.instagram.com/" + p[i] + "/" + p[i + 1] + "/embed", ratio: 3 / 5 };
        }
      }
      return null;
    }
    if (h === "tiktok.com") {
      var j = p.indexOf("video");
      if (j > -1 && /^\d+$/.test(p[j + 1] || "")) return { name: "TikTok", src: "https://www.tiktok.com/embed/v2/" + p[j + 1], ratio: 9 / 17 };
      return null;
    }
    if (h === "facebook.com" || h === "fb.watch") {
      return { name: "Facebook", src: "https://www.facebook.com/plugins/video.php?show_text=false&href=" + encodeURIComponent(u), ratio: 16 / 9 };
    }
    return null;
  }

  /* ---------- ventana ampliada (un solo <dialog> para todos) ---------- */
  var dlg;
  function modal() {
    if (dlg) return dlg;
    dlg = document.createElement("dialog");
    dlg.className = "mm-vmodal";
    dlg.innerHTML = '<div class="mm-vmodal-bar"><span class="mm-vmodal-title"></span><button type="button" class="mm-vmodal-close" aria-label="Cerrar">Cerrar ✕</button></div><div class="mm-vmodal-stage"></div>';
    dlg.querySelector(".mm-vmodal-close").addEventListener("click", function () { dlg.close(); });
    dlg.addEventListener("click", function (e) { if (e.target === dlg) dlg.close(); });   /* clic fuera */
    dlg.addEventListener("close", function () { dlg.querySelector(".mm-vmodal-stage").innerHTML = ""; });  /* detiene el video */
    document.body.appendChild(dlg);
    return dlg;
  }
  function open(info, title) {
    var d = modal();
    d.querySelector(".mm-vmodal-title").textContent = info.name + (title ? " · " + title : "");
    var stage = d.querySelector(".mm-vmodal-stage");
    stage.innerHTML = "";
    var f = document.createElement("iframe");
    f.src = info.src + (info.autoplay ? "?autoplay=1" : "");
    f.title = title || ("Video de " + info.name);
    f.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
    f.allowFullscreen = true;
    f.referrerPolicy = "strict-origin-when-cross-origin";
    f.style.aspectRatio = String(info.ratio);
    /* vertical: se limita por alto; horizontal: por ancho */
    if (info.ratio < 1) { f.style.height = "min(78vh, 760px)"; f.style.width = "auto"; }
    else { f.style.width = "min(92vw, 960px)"; f.style.height = "auto"; }
    stage.appendChild(f);
    if (d.showModal) d.showModal(); else d.setAttribute("open", "");
  }

  /* ---------- tarjetas ---------- */
  function card(v) {
    var hasUrl = !!(v.url && v.url.trim());
    var info = hasUrl ? parse(v.url) : null;
    if (hasUrl && !info) { if (window.console) console.warn("MixMix: enlace de video no reconocido:", v.url); return null; }

    var b = document.createElement("button");
    b.type = "button";
    b.className = "mm-video" + (info ? "" : " is-placeholder");
    b.innerHTML = '<span class="mm-video-net"></span><span class="mm-video-play" aria-hidden="true"></span>' +
      '<span class="mm-video-title"></span><span class="mm-video-cta"></span>';
    b.querySelector(".mm-video-net").textContent = info ? info.name : "Tu video aquí";
    b.querySelector(".mm-video-title").textContent = v.title || "";
    b.querySelector(".mm-video-cta").textContent = info ? "Ver ampliado" : "Próximamente";
    if (info) b.addEventListener("click", function () { open(info, v.title); });
    else b.disabled = true;
    return b;
  }

  function render(list) {
    var root = document.querySelector('[data-mm="videos"]');
    if (!root) return;
    root.innerHTML = "";
    var cards = (list || []).map(card).filter(Boolean);
    root.hidden = cards.length === 0;
    if (!cards.length) return;
    root.className = "mm-videos";
    cards.forEach(function (c) { root.appendChild(c); });
  }

  window.MMSocial = { render: render, parse: parse };
  render(VIDEOS);
})();
