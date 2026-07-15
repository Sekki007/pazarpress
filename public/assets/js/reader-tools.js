(function () {
  var READ_KEY = "pazarpress-read-later";
  var SCROLL_KEY = "pazarpress-scroll";

  function getList() {
    try {
      return JSON.parse(localStorage.getItem(READ_KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function setList(list) {
    localStorage.setItem(READ_KEY, JSON.stringify(list.slice(0, 50)));
  }

  function updateReadLaterBtn() {
    var btn = document.getElementById("btn-read-later");
    var article = document.querySelector(".article-page[data-article-slug]");
    if (!btn || !article) return;
    var slug = article.dataset.articleSlug;
    var saved = getList().some(function (x) {
      return x.slug === slug;
    });
    btn.classList.toggle("is-saved", saved);
    var star = btn.querySelector(".share-bar__icon--star");
    if (star) star.textContent = saved ? "★" : "☆";
    var label = btn.querySelector(".share-bar__later-text");
    if (label) label.textContent = saved ? "Sačuvano" : "Za kasnije";
    btn.setAttribute("aria-pressed", saved ? "true" : "false");
    btn.setAttribute("aria-label", saved ? "Ukloni sa liste za kasnije" : "Sačuvaj za kasnije");
  }

  document.getElementById("btn-read-later")?.addEventListener("click", function () {
    var article = document.querySelector(".article-page[data-article-slug]");
    if (!article) return;
    var slug = article.dataset.articleSlug;
    var title = document.querySelector(".article-title")?.textContent || slug;
    var list = getList();
    var exists = list.some(function (x) {
      return x.slug === slug;
    });
    if (exists) {
      setList(list.filter(function (x) {
        return x.slug !== slug;
      }));
    } else {
      list.unshift({ slug: slug, title: title, savedAt: Date.now() });
      setList(list);
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
      var label = btn.querySelector("span:last-child");
      var old = label ? label.textContent : "";
      if (label) label.textContent = "Kopirano!";
      setTimeout(function () {
        btn.classList.remove("is-copied");
        if (label) label.textContent = old;
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

  updateReadLaterBtn();
})();
