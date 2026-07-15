(function () {
  var READ_KEY = "pazarpress-read-later";
  var HISTORY_KEY = "pazarpress-history";

  function getList(key) {
    try {
      return JSON.parse(localStorage.getItem(key) || "[]");
    } catch (e) {
      return [];
    }
  }

  function cardHtml(item) {
    var thumb = item.cover
      ? '<img src="' + item.cover + '" alt="" class="news-card__thumb news-card__thumb--img" loading="lazy" width="110" height="82">'
      : '<span class="news-card__thumb thumb-a"></span>';
    return (
      '<article class="news-card">' +
      '<a href="/vijest/' + encodeURIComponent(item.slug) + '" class="news-card__thumb-link">' + thumb + "</a>" +
      '<div class="news-card__body">' +
      '<a href="/vijest/' + encodeURIComponent(item.slug) + '"><h3 class="news-card__title">' +
      escapeHtml(item.title || item.slug) +
      "</h3></a>" +
      '<div class="news-card__meta"><span class="chip">Vijest</span></div>' +
      "</div></article>"
    );
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function render() {
    var saved = getList(READ_KEY);
    var history = getList(HISTORY_KEY);
    var savedList = document.getElementById("saved-list");
    var historyList = document.getElementById("history-list");
    var savedEmpty = document.getElementById("saved-empty");
    var historyEmpty = document.getElementById("history-empty");

    if (savedList) {
      savedList.innerHTML = saved.map(cardHtml).join("");
      if (savedEmpty) savedEmpty.hidden = saved.length > 0;
    }
    if (historyList) {
      historyList.innerHTML = history.map(cardHtml).join("");
      if (historyEmpty) historyEmpty.hidden = history.length > 0;
    }
  }

  document.querySelectorAll(".saved-tabs__btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var tab = btn.dataset.tab;
      document.querySelectorAll(".saved-tabs__btn").forEach(function (b) {
        b.classList.toggle("is-active", b === btn);
        b.setAttribute("aria-selected", b === btn ? "true" : "false");
      });
      document.querySelectorAll(".saved-panel").forEach(function (panel) {
        panel.hidden = panel.dataset.panel !== tab;
      });
    });
  });

  render();
})();
