(function () {
  var READ_KEY = "pazarpress-read-later";
  var HISTORY_KEY = "pazarpress-history";
  var SCROLL_KEY = "pazarpress-scroll";
  var FONT_KEY = "pazarpress-font-size";
  var FONT_STEPS = [16, 18, 20, 22];

  function getList(key) {
    try {
      return JSON.parse(localStorage.getItem(key) || "[]");
    } catch (e) {
      return [];
    }
  }

  function setList(key, list, max) {
    localStorage.setItem(key, JSON.stringify(list.slice(0, max || 50)));
  }

  function updateReadLaterBtn() {
    var btn = document.getElementById("btn-read-later");
    var article = document.querySelector(".article-page[data-article-slug]");
    if (!btn || !article) return;
    var slug = article.dataset.articleSlug;
    var saved = getList(READ_KEY).some(function (x) {
      return x.slug === slug;
    });
    btn.classList.toggle("is-saved", saved);
    var star = btn.querySelector(".share-bar__star");
    if (star) {
      if (saved) star.setAttribute("fill", "currentColor");
      else star.setAttribute("fill", "none");
    }
    var label = btn.querySelector(".share-bar__later-text");
    if (label) label.textContent = saved ? "Sačuvano" : "Za kasnije";
    btn.setAttribute("aria-pressed", saved ? "true" : "false");
    btn.setAttribute("aria-label", saved ? "Ukloni sa liste za kasnije" : "Sačuvaj za kasnije");
    btn.title = saved ? "Sačuvano" : "Za kasnije";
  }

  function trackHistory() {
    var article = document.querySelector(".article-page[data-article-slug]");
    if (!article) return;
    var slug = article.dataset.articleSlug;
    var title = article.dataset.articleTitle || document.querySelector(".article-title")?.textContent || slug;
    var cover = article.dataset.articleCover || "";
    var list = getList(HISTORY_KEY).filter(function (x) {
      return x.slug !== slug;
    });
    list.unshift({ slug: slug, title: title, cover: cover, viewedAt: Date.now() });
    setList(HISTORY_KEY, list, 40);
  }

  function applyFontSize(px) {
    document.documentElement.style.setProperty("--read-size", px + "px");
    document.querySelectorAll(".article-body").forEach(function (el) {
      el.style.fontSize = px + "px";
    });
    localStorage.setItem(FONT_KEY, String(px));
  }

  function currentFontIndex() {
    var saved = parseInt(localStorage.getItem(FONT_KEY) || "18", 10);
    var idx = FONT_STEPS.indexOf(saved);
    if (idx !== -1) return idx;
    var best = 0;
    var bestDiff = Math.abs(FONT_STEPS[0] - saved);
    for (var i = 1; i < FONT_STEPS.length; i++) {
      var d = Math.abs(FONT_STEPS[i] - saved);
      if (d < bestDiff) {
        best = i;
        bestDiff = d;
      }
    }
    return best;
  }

  function bindFontButtons() {
    var up = document.getElementById("btn-font-up");
    var down = document.getElementById("btn-font-down");
    if (!up && !down) return;
    down?.addEventListener("click", function (e) {
      e.preventDefault();
      applyFontSize(FONT_STEPS[Math.max(0, currentFontIndex() - 1)]);
    });
    up?.addEventListener("click", function (e) {
      e.preventDefault();
      applyFontSize(FONT_STEPS[Math.min(FONT_STEPS.length - 1, currentFontIndex() + 1)]);
    });
    if (document.querySelector(".article-body")) {
      applyFontSize(FONT_STEPS[currentFontIndex()]);
    }
  }

  document.getElementById("btn-read-later")?.addEventListener("click", function () {
    var article = document.querySelector(".article-page[data-article-slug]");
    if (!article) return;
    var slug = article.dataset.articleSlug;
    var title = article.dataset.articleTitle || document.querySelector(".article-title")?.textContent || slug;
    var cover = article.dataset.articleCover || "";
    var list = getList(READ_KEY);
    var exists = list.some(function (x) {
      return x.slug === slug;
    });
    if (exists) {
      setList(READ_KEY, list.filter(function (x) {
        return x.slug !== slug;
      }));
    } else {
      list.unshift({ slug: slug, title: title, cover: cover, savedAt: Date.now() });
      setList(READ_KEY, list);
    }
    updateReadLaterBtn();
  });

  document.getElementById("btn-copy-link")?.addEventListener("click", function () {
    var bar = document.querySelector(".share-bar");
    var url = bar?.dataset.shareUrl || location.href;
    var done = function () {
      var btn = document.getElementById("btn-copy-link");
      if (!btn) return;
      btn.classList.add("is-copied");
      var oldTitle = btn.getAttribute("title") || "Kopiraj link";
      btn.setAttribute("title", "Kopirano!");
      btn.setAttribute("aria-label", "Kopirano!");
      setTimeout(function () {
        btn.classList.remove("is-copied");
        btn.setAttribute("title", oldTitle);
        btn.setAttribute("aria-label", "Kopiraj link");
      }, 1500);
    };
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function () {
        window.prompt("Kopiraj link:", url);
      });
    } else {
      window.prompt("Kopiraj link:", url);
    }
  });

  document.getElementById("comment-form")?.addEventListener("submit", async function (e) {
    e.preventDefault();
    var form = e.target;
    var articleId = form.dataset.articleId;
    var fd = new FormData(form);
    var res = await fetch("/api/comments", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        articleId: articleId,
        name: fd.get("name"),
        body: fd.get("body"),
      }),
    });
    var data = await res.json().catch(function () {
      return {};
    });
    alert(data.message || data.error || (res.ok ? "Poslano." : "Greška."));
    if (res.ok) form.reset();
  });

  if (location.pathname === "/") {
    var savedY = sessionStorage.getItem(SCROLL_KEY);
    if (savedY) {
      var y = parseInt(savedY, 10);
      if (!isNaN(y) && y > 0) {
        requestAnimationFrame(function () {
          window.scrollTo(0, y);
        });
      }
      sessionStorage.removeItem(SCROLL_KEY);
    }
    window.addEventListener(
      "beforeunload",
      function () {
        sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
      },
      { passive: true }
    );
  }

  trackHistory();
  updateReadLaterBtn();
  bindFontButtons();
})();
