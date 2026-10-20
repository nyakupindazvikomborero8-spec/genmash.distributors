/* Mobile menu. Adds .js early so the collapsed menu only applies when this script runs. */
document.documentElement.classList.add("js");
document.addEventListener("DOMContentLoaded", function () {
  var header = document.querySelector(".site-header");
  var btn = document.querySelector(".menu-btn");
  if (!header || !btn) return;
  function set(open) {
    header.classList.toggle("open", open);
    btn.setAttribute("aria-expanded", open);
    btn.textContent = open ? "Close" : "Menu";
  }
  btn.addEventListener("click", function () { set(!header.classList.contains("open")); });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && header.classList.contains("open")) { set(false); btn.focus(); }
  });
});
