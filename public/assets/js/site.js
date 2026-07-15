(function () {
  var overlay = document.getElementById("search-overlay");
  var input = document.getElementById("search-input");
  var drawer = document.getElementById("mobile-drawer");
  var drawerBackdrop = document.getElementById("drawer-backdrop");

  function openSearch() {
    if (!overlay) return;
    closeDrawer();
    overlay.classList.add("is-open");
    overlay.removeAttribute("inert");
    overlay.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    setTimeout(function () {
      if (input) input.focus();
    }, 120);
  }

  function closeSearch() {
    if (!overlay) return;
    overlay.classList.remove("is-open");
    overlay.setAttribute("inert", "");
    overlay.setAttribute("aria-hidden", "true");
    if (!drawer || !drawer.classList.contains("is-open")) {
      document.body.classList.remove("modal-open");
    }
  }

  function openDrawer() {
    if (!drawer) return;
    closeSearch();
    drawer.classList.add("is-open");
    drawer.removeAttribute("inert");
    drawer.setAttribute("aria-hidden", "false");
    if (drawerBackdrop) drawerBackdrop.hidden = false;
    document.body.classList.add("modal-open");
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove("is-open");
    drawer.setAttribute("inert", "");
    drawer.setAttribute("aria-hidden", "true");
    if (drawerBackdrop) drawerBackdrop.hidden = true;
    if (!overlay || !overlay.classList.contains("is-open")) {
      document.body.classList.remove("modal-open");
    }
  }

  function goSearch(q) {
    q = (q || "").trim();
    if (!q) return;
    window.location.href = "/pretraga?q=" + encodeURIComponent(q);
  }

  document.getElementById("btn-search")?.addEventListener("click", openSearch);
  document.getElementById("btn-search-m")?.addEventListener("click", openSearch);
  document.getElementById("search-close")?.addEventListener("click", closeSearch);
  document.getElementById("btn-menu")?.addEventListener("click", openDrawer);
  document.getElementById("btn-menu-bottom")?.addEventListener("click", openDrawer);
  document.getElementById("drawer-close")?.addEventListener("click", closeDrawer);

  overlay?.addEventListener("click", function (e) {
    if (e.target === overlay) closeSearch();
  });

  drawerBackdrop?.addEventListener("click", closeDrawer);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeSearch();
      closeDrawer();
    }
  });

  input?.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      goSearch(input.value);
    }
  });

  document.querySelectorAll("[data-search-hint]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      goSearch(btn.textContent);
    });
  });

  async function submitNewsletter(e) {
    e.preventDefault();
    var email = new FormData(e.target).get("email");
    var res = await fetch("/api/newsletter", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: email }),
    });
    var data = await res.json().catch(function () { return {}; });
    alert(data.message || data.error || (res.ok ? "Hvala na prijavi!" : "Greška pri prijavi."));
  }
  document.querySelectorAll(".newsletter-form").forEach(function (form) {
    form.addEventListener("submit", submitNewsletter);
  });

  document.querySelectorAll(".poll-form").forEach(function (form) {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      var optionId = new FormData(e.target).get("optionId");
      var res = await fetch("/api/poll/vote", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ optionId: optionId }),
      });
      alert(res.ok ? "Hvala na glasu!" : "Već ste glasali ili greška.");
      if (res.ok) location.reload();
    });
  });

  document.querySelectorAll(".avc-faq-item .avc-faq-q").forEach(function (q) {
    q.addEventListener("click", function () {
      q.closest(".avc-faq-item")?.classList.toggle("open");
    });
  });
  document.querySelectorAll(".avc-video-wrap iframe").forEach(function (f) {
    if (!f.src || f.src === "about:blank") f.closest(".avc-video-wrap").style.display = "none";
  });
  document.querySelectorAll(".avc-mp4-wrap video").forEach(function (v) {
    var s = v.querySelector("source");
    if (!s || !s.src) v.closest(".avc-mp4-wrap").style.display = "none";
  });

  var progress = document.getElementById("read-progress");
  if (progress) {
    window.addEventListener(
      "scroll",
      function () {
        var h = document.documentElement;
        var p = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
        progress.style.width = p + "%";
      },
      { passive: true }
    );
  }
})();
