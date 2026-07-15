(function () {
  var feed = document.getElementById("news-feed");
  var btn = document.getElementById("btn-load-more");
  if (!feed || !btn) return;

  var cursor = feed.dataset.cursor || "";
  var grad = feed.dataset.grad || "";
  var loading = false;
  var ptr = document.getElementById("ptr-indicator");
  var ptrText = ptr ? ptr.querySelector(".ptr-indicator__text") : null;

  if (!cursor) {
    btn.disabled = true;
    btn.textContent = "Nema više vijesti";
  }

  function skeletonCard() {
    var el = document.createElement("article");
    el.className = "news-card news-card--skeleton";
    el.innerHTML =
      '<a class="news-card__thumb-link"><div class="skeleton skeleton--thumb" style="border-radius:11px"></div></a>' +
      '<div class="news-card__body" style="padding-top:4px">' +
      '<div class="skeleton skeleton--line"></div>' +
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
          ? '<img src="' + a.coverImage + '" alt="" class="news-card__thumb news-card__thumb--img" loading="lazy" width="110" height="82">'
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
  var pulling = false;

  function showPtr(msg, spinning) {
    if (!ptr) return;
    ptr.hidden = false;
    ptr.classList.toggle("is-spinning", !!spinning);
    if (ptrText) ptrText.textContent = msg;
  }

  function hidePtrSoon() {
    if (!ptr) return;
    setTimeout(function () {
      ptr.hidden = true;
      ptr.classList.remove("is-spinning");
    }, 1200);
  }

  window.addEventListener(
    "touchstart",
    function (e) {
      if (window.scrollY <= 2) {
        startY = e.touches[0].clientY;
        pulling = true;
      } else {
        pulling = false;
      }
    },
    { passive: true }
  );

  window.addEventListener(
    "touchmove",
    function (e) {
      if (!pulling || window.scrollY > 2) return;
      var dy = e.touches[0].clientY - startY;
      if (dy > 40) showPtr("Pusti za osvežavanje", false);
    },
    { passive: true }
  );

  window.addEventListener(
    "touchend",
    function (e) {
      if (!pulling) return;
      pulling = false;
      if (window.scrollY > 2) {
        if (ptr) ptr.hidden = true;
        return;
      }
      var dy = e.changedTouches[0].clientY - startY;
      if (dy > 120) {
        showPtr("Osvežavanje…", true);
        sessionStorage.setItem("pazarpress-ptr", "1");
        location.reload();
      } else if (ptr) {
        ptr.hidden = true;
      }
    },
    { passive: true }
  );

  if (sessionStorage.getItem("pazarpress-ptr") === "1") {
    sessionStorage.removeItem("pazarpress-ptr");
    showPtr("Osveženo upravo", false);
    hidePtrSoon();
  }
})();
