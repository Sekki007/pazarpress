(function () {
  var root = document.querySelector(".article-page");
  if (!root) return;

  var images = root.querySelectorAll(".article-cover img, .article-body img");
  if (!images.length) return;

  var overlay = document.createElement("div");
  overlay.className = "img-lightbox";
  overlay.hidden = true;
  overlay.innerHTML =
    '<div class="img-lightbox__backdrop" data-close></div>' +
    '<div class="img-lightbox__panel" role="dialog" aria-modal="true" aria-label="Pregled slike">' +
    '<button type="button" class="img-lightbox__close" data-close aria-label="Zatvori">✕</button>' +
    '<img class="img-lightbox__img" src="" alt="">' +
    '<div class="img-lightbox__actions">' +
    '<a class="img-lightbox__download btn-primary" href="#" download>Preuzmi sliku</a>' +
    "</div></div>";
  document.body.appendChild(overlay);

  var lightImg = overlay.querySelector(".img-lightbox__img");
  var downloadLink = overlay.querySelector(".img-lightbox__download");

  function open(src, alt) {
    lightImg.src = src;
    lightImg.alt = alt || "";
    var fileName = src.split("/").pop() || "sandzak-slika.jpg";
    downloadLink.href = src;
    downloadLink.setAttribute("download", fileName);
    overlay.hidden = false;
    document.body.style.overflow = "hidden";
  }

  function close() {
    overlay.hidden = true;
    lightImg.src = "";
    document.body.style.overflow = "";
  }

  images.forEach(function (img) {
    img.classList.add("article-img-zoom");
    img.setAttribute("tabindex", "0");
    img.setAttribute("role", "button");
    img.setAttribute("aria-label", "Otvori sliku u punoj veličini");
    img.addEventListener("click", function () {
      open(img.currentSrc || img.src, img.alt);
    });
    img.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        open(img.currentSrc || img.src, img.alt);
      }
    });
  });

  overlay.querySelectorAll("[data-close]").forEach(function (el) {
    el.addEventListener("click", close);
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && !overlay.hidden) close();
  });
})();
