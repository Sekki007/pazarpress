(function () {
  var feed = document.getElementById("news-feed");
  var btn = document.getElementById("btn-load-more");
  if (!feed || !btn) return;

  var cursor = feed.dataset.cursor || "";
  var grad = feed.dataset.grad || "";
  var loading = false;

  if (!cursor) {
    btn.disabled = true;
    btn.textContent = "Nema više vijesti";
  }

  function skeletonCard() {
    var el = document.createElement("article");
    el.className = "news-card news-card--skeleton";
    el.innerHTML =
      '<a class="news-card__thumb-link"><div class="skeleton skeleton--thumb" style="width:72px;height:72px;border-radius:9px"></div></a>' +
      '<div class="news-card__body">' +
      '<div class="skeleton skeleton--line"></div>' +
      '<div class="skeleton skeleton--line skeleton--short"></div>' +
      "</div>";
    return el;
  }

  function showSkeletons(n) {
    var frag = document.createDocumentFragment();
    for (var i = 0; i < n; i++) frag.appendChild(skeletonCard());
    feed.appendChild(frag);
  }

  function clearSkeletons() {
    feed.querySelectorAll(".news-card--skeleton").forEach(function (el) {
      el.remove();
    });
  }

  btn.addEventListener("click", async function () {
    if (loading || !cursor) return;
    loading = true;
    btn.textContent = "Učitavanje…";
    btn.disabled = true;
    showSkeletons(3);
    var url = new URL("/api/articles/more", location.origin);
    url.searchParams.set("cursor", cursor);
    if (grad) url.searchParams.set("grad", grad);
    try {
      var res = await fetch(url);
      var data = await res.json();
      clearSkeletons();
      data.items.forEach(function (a, i) {
        var art = document.createElement("article");
        art.className = "news-card";
        var thumb = a.coverImage
          ? '<img src="' + a.coverImage + '" alt="" class="news-card__thumb news-card__thumb--img" loading="lazy" width="72" height="72">'
          : '<span class="news-card__thumb thumb-' + "abcd"[i % 4] + '"></span>';
        art.innerHTML =
          '<a href="/vijest/' + encodeURIComponent(a.slug) + '" class="news-card__thumb-link">' + thumb + "</a>" +
          '<div class="news-card__body"><a href="/vijest/' + encodeURIComponent(a.slug) + '"><h3 class="news-card__title">' + a.title + "</h3></a>" +
          '<div class="news-card__meta"><span class="chip">' + (a.category && a.category.name ? a.category.name : "") + "</span></div></div>";
        feed.appendChild(art);
      });
      cursor = data.nextCursor || "";
      feed.dataset.cursor = cursor;
      btn.textContent = cursor ? "Učitaj još" : "Nema više vijesti";
      if (!cursor) btn.disabled = true;
    } catch (e) {
      clearSkeletons();
      btn.textContent = "Pokušaj ponovo";
    }
    loading = false;
    btn.disabled = !cursor;
  });

  var startY = 0;
  window.addEventListener(
    "touchstart",
    function (e) {
      if (window.scrollY === 0) startY = e.touches[0].clientY;
    },
    { passive: true }
  );
  window.addEventListener(
    "touchend",
    function (e) {
      if (window.scrollY > 0) return;
      var dy = e.changedTouches[0].clientY - startY;
      if (dy > 120) location.reload();
    },
    { passive: true }
  );
})();
