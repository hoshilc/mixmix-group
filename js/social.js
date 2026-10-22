/* MixMix — videos de redes sociales en la sección "Evidencia" (index.html).

   PARA AGREGAR UN VIDEO: pega su enlace en la lista de abajo.
     { url: "https://www.instagram.com/reel/XXXXXXXXXXX/", title: "Texto corto opcional" }

   Enlaces que entiende:
     YouTube     https://www.youtube.com/watch?v=ID · https://youtu.be/ID · https://www.youtube.com/shorts/ID
     Instagram   https://www.instagram.com/reel/CODIGO/ · /p/CODIGO/
     TikTok      https://www.tiktok.com/@usuario/video/NUMERO   (el enlace largo, no vm.tiktok.com)
     Facebook    https://www.facebook.com/.../videos/NUMERO · https://fb.watch/...

   El video NO se carga hasta que la persona le da clic: así la página abre rápido y las
   redes sociales no rastrean a quien solo está leyendo. Si la lista está vacía, no se muestra nada. */
(function () {
  "use strict";

  var VIDEOS = [
    /* { url: "https://www.instagram.com/reel/XXXXXXXXXXX/", title: "Remodelación de cocina" }, */
  ];

  function yt(id, short) {
    if (!id || !/^[\w-]{6,15}$/.test(id)) return null;
    return { name: "YouTube", src: "https://www.youtube-nocookie.com/embed/" + id, ratio: short ? "9/16" : "16/9", autoplay: true };
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
          return { name: "Instagram", src: "https://www.instagram.com/" + p[i] + "/" + p[i + 1] + "/embed", ratio: "3/5" };
        }
      }
      return null;
    }
    if (h === "tiktok.com") {
      var j = p.indexOf("video");
      if (j > -1 && /^\d+$/.test(p[j + 1] || "")) return { name: "TikTok", src: "https://www.tiktok.com/embed/v2/" + p[j + 1], ratio: "9/17" };
      return null;
    }
    if (h === "facebook.com" || h === "fb.watch") {
      return { name: "Facebook", src: "https://www.facebook.com/plugins/video.php?show_text=false&href=" + encodeURIComponent(u), ratio: "16/9" };
    }
    return null;
  }

  function card(v) {
    var info = parse(v.url);
    if (!info) { if (window.console) console.warn("MixMix: enlace de video no reconocido:", v.url); return null; }
    var box = document.createElement("div");
    box.className = "mm-video";
    var frame = document.createElement("div");
    frame.className = "mm-video-frame";
    frame.style.aspectRatio = info.ratio;

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "mm-video-play";
    btn.innerHTML = '<span class="mm-video-net"></span><span class="mm-video-title"></span><span class="mm-video-cta">Ver video</span>';
    btn.querySelector(".mm-video-net").textContent = info.name;
    btn.querySelector(".mm-video-title").textContent = v.title || "";
    btn.addEventListener("click", function () {
      var f = document.createElement("iframe");
      f.src = info.src + (info.autoplay ? "?autoplay=1" : "");
      f.title = v.title || ("Video de " + info.name);
      f.loading = "lazy";
      f.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
      f.allowFullscreen = true;
      f.referrerPolicy = "strict-origin-when-cross-origin";
      frame.innerHTML = "";
      frame.appendChild(f);
    });
    frame.appendChild(btn);
    box.appendChild(frame);
    return box;
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
