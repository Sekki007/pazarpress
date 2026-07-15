(function () {
  const key = "pazarpress-theme";
  function apply(theme) {
    document.documentElement.classList.toggle("dark", theme === "dark");
    localStorage.setItem(key, theme);
  }
  function toggle() {
    const next = document.documentElement.classList.contains("dark") ? "light" : "dark";
    apply(next);
  }
  document.getElementById("btn-theme")?.addEventListener("click", toggle);
  document.getElementById("btn-theme-article")?.addEventListener("click", toggle);
})();
