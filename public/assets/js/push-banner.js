(function () {
  var DISMISS_KEY = "pazarpress-push-dismiss";
  var banner = document.getElementById("push-banner");
  if (!banner) return;

  var canNotify = "Notification" in window;
  var dismissed = false;
  try {
    dismissed = localStorage.getItem(DISMISS_KEY) === "1";
  } catch (e) {}

  function hide() {
    banner.hidden = true;
  }

  function show() {
    banner.hidden = false;
  }

  if (!canNotify || Notification.permission !== "default" || dismissed) {
    hide();
  } else {
    setTimeout(function () {
      if (window.scrollY < 180) return;
      show();
    }, 9000);
    window.addEventListener(
      "scroll",
      function onScroll() {
        if (dismissed || Notification.permission !== "default") return;
        if (window.scrollY > 320) {
          show();
          window.removeEventListener("scroll", onScroll);
        }
      },
      { passive: true }
    );
  }

  document.getElementById("push-dismiss")?.addEventListener("click", function () {
    try {
      localStorage.setItem(DISMISS_KEY, "1");
    } catch (e) {}
    hide();
  });

  document.getElementById("push-enable")?.addEventListener("click", async function () {
    try {
      var perm = await Notification.requestPermission();
      if (perm === "granted") {
        try {
          new Notification("Pazar Press", {
            body: "Obaveštenja su uključena. Pratite hitne vesti.",
            icon: "/assets/img/icon-192.png",
            tag: "pp-welcome",
          });
        } catch (e) {}
      }
    } catch (e) {}
    try {
      localStorage.setItem(DISMISS_KEY, "1");
    } catch (e2) {}
    hide();
  });
})();
