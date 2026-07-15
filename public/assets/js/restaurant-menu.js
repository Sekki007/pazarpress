(function () {
  var screensRoot = document.getElementById("rst-screens");
  if (!screensRoot) return;

  var screens = screensRoot.querySelectorAll(".rst-screen");
  var tabButtons = document.querySelectorAll(".rst-tabbar__item[data-screen]");
  var topbarTitle = document.getElementById("rst-topbar-title");
  var tabbar = document.getElementById("rst-tabbar");
  var restaurantName = topbarTitle
    ? (topbarTitle.getAttribute("data-restaurant-name") || topbarTitle.textContent).trim()
    : "";

  function stickyOffset() {
    var sticky = document.getElementById("rst-sticky-top");
    var topbar = document.querySelector(".rst-topbar");
    return (sticky && screensRoot.dataset.active === "menu" ? sticky.offsetHeight : 0) +
      (topbar ? topbar.offsetHeight : 0) + 6;
  }

  function scrollToEl(el) {
    if (!el) return;
    var top = el.getBoundingClientRect().top + window.scrollY - stickyOffset();
    window.scrollTo({ top: top, behavior: "smooth" });
  }

  function screenHash(name) {
    if (name === "menu") return "meni";
    if (name === "info") return "info";
    if (name === "location") return "lokacija";
    return "meni";
  }

  function hashToScreen(hash) {
    hash = (hash || "").replace("#", "").toLowerCase();
    if (hash === "recenzije" || hash === "info") return "info";
    if (hash === "lokacija" || hash === "location") return "location";
    return "menu";
  }

  function updateTopbarTitle(screenName) {
    if (!topbarTitle || !tabbar) return;
    if (screenName === "menu") {
      topbarTitle.textContent = restaurantName;
    } else if (screenName === "info") {
      topbarTitle.textContent = tabbar.getAttribute("data-label-about") || "Info";
    } else if (screenName === "location") {
      topbarTitle.textContent = tabbar.getAttribute("data-label-location") || "Lokacija";
    }
  }

  function showScreen(name, opts) {
    opts = opts || {};
    var exists = false;
    screens.forEach(function (s) {
      if (s.dataset.screen === name) exists = true;
    });
    if (!exists) name = "menu";

    screens.forEach(function (s) {
      s.classList.toggle("rst-screen--active", s.dataset.screen === name);
    });
    tabButtons.forEach(function (t) {
      var on = t.dataset.screen === name;
      t.classList.toggle("rst-tabbar__item--active", on);
      if (on) t.setAttribute("aria-current", "page");
      else t.removeAttribute("aria-current");
    });
    screensRoot.dataset.active = name;
    updateTopbarTitle(name);
    window.scrollTo(0, 0);

    if (!opts.silentHash) {
      var h = opts.hash || screenHash(name);
      if (location.hash !== "#" + h) {
        history.replaceState(null, "", "#" + h);
      }
    }

    if (opts.scrollTo) {
      setTimeout(function () {
        scrollToEl(document.querySelector(opts.scrollTo));
      }, 50);
    }
  }

  tabButtons.forEach(function (tab) {
    tab.addEventListener("click", function () {
      showScreen(tab.dataset.screen || "menu");
    });
  });

  window.addEventListener("hashchange", function () {
    var target = hashToScreen(location.hash);
    if (screensRoot.dataset.active !== target) {
      showScreen(target, {
        silentHash: true,
        scrollTo: location.hash === "#recenzije" ? "#recenzije" : null,
      });
    }
  });

  var initialHash = location.hash;
  var initialScreen = hashToScreen(initialHash);
  showScreen(initialScreen, {
    silentHash: true,
    scrollTo: initialHash === "#recenzije" ? "#recenzije" : null,
  });

  document.querySelectorAll("[data-cat-jump]").forEach(function (link) {
    link.addEventListener("click", function (e) {
      var id = link.getAttribute("data-cat-jump");
      var el = document.getElementById(id);
      if (!el) return;
      e.preventDefault();
      scrollToEl(el);
    });
  });

  document.querySelectorAll(".rst-lang-bar__pill").forEach(function (pill) {
    pill.addEventListener("click", function () {
      var lang = pill.getAttribute("data-lang");
      if (!lang) return;
      try {
        localStorage.setItem("menu_lang", lang);
        document.cookie =
          "menu_lang=" +
          encodeURIComponent(lang) +
          ";path=/;max-age=31536000;samesite=lax";
      } catch (e) {}
    });
  });

  function shareUrl(url, title) {
    if (navigator.share) {
      return navigator.share({ title: title || document.title, url: url });
    }
    return navigator.clipboard.writeText(url);
  }

  var shareBtn = document.getElementById("rst-share-btn");
  if (shareBtn) {
    var shareLabel = shareBtn.getAttribute("data-label") || "Podijeli";
    shareBtn.addEventListener("click", function () {
      var url = shareBtn.getAttribute("data-url") || window.location.href;
      var title = shareBtn.getAttribute("data-title") || document.title;
      shareUrl(url, title)
        .then(function () {
          shareBtn.textContent = "✓ " + shareLabel;
          setTimeout(function () {
            shareBtn.textContent = "↗ " + shareLabel;
          }, 2000);
        })
        .catch(function () {
          prompt("Kopirajte link:", url);
        });
    });
  }

  var shareTab = document.getElementById("rst-share-tab");
  if (shareTab) {
    shareTab.addEventListener("click", function () {
      var url = shareTab.getAttribute("data-url") || window.location.href;
      shareUrl(url).catch(function () {
        prompt("Kopirajte link:", url);
      });
    });
  }

  var nav = document.getElementById("rst-cat-nav");
  if (nav) {
    var links = nav.querySelectorAll("[data-cat-jump]");
    var sections = [];
    links.forEach(function (a) {
      var id = a.getAttribute("data-cat-jump");
      var el = document.getElementById(id);
      if (el) sections.push({ link: a, el: el });
    });
    if (sections.length) {
      var observer = new IntersectionObserver(
        function (entries) {
          if (screensRoot.dataset.active !== "menu") return;
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            sections.forEach(function (s) {
              s.link.classList.toggle("rst-cat-nav__link--active", s.el === entry.target);
            });
          });
        },
        {
          rootMargin: "-" + Math.round(stickyOffset() + window.innerHeight * 0.25) + "px 0px -55% 0px",
          threshold: 0,
        }
      );
      sections.forEach(function (s) {
        observer.observe(s.el);
      });
    }
  }
})();
