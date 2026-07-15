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

  document.addEventListener("submit", async function (e) {
    var form = e.target && e.target.closest ? e.target.closest(".poll-form") : null;
    if (!form) return;
    e.preventDefault();
    e.stopPropagation();
    var optionId = new FormData(form).get("optionId");
    if (!optionId) {
      alert("Izaberite opciju.");
      return;
    }
    var btn = form.querySelector('button[type="submit"]');
    if (btn) btn.disabled = true;
    try {
      var res = await fetch("/api/poll/vote", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ optionId: optionId }),
      });
      var data = await res.json().catch(function () { return {}; });
      if (res.ok) {
        alert(data.message || "Hvala na glasu!");
        location.reload();
      } else {
        alert(data.error || "Već ste glasali ili greška.");
        if (btn) btn.disabled = false;
      }
    } catch (err) {
      alert("Greška pri glasanju. Pokušajte ponovo.");
      if (btn) btn.disabled = false;
    }
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
    var ticking = false;
    window.addEventListener(
      "scroll",
      function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
          var h = document.documentElement;
          var max = h.scrollHeight - h.clientHeight;
          progress.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + "%";
          ticking = false;
        });
      },
      { passive: true }
    );
  }
})();
