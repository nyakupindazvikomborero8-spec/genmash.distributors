/* Shared behaviour: mobile menu, progress bar, sticky nav, scroll reveal, gallery and parallax.
   The .js class is added immediately so reveal animations only apply when this script runs. */
document.documentElement.classList.add("js");
document.addEventListener("DOMContentLoaded", function () {
  var $ = function (s) { return document.querySelector(s); };
  var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };
  var calm = matchMedia("(prefers-reduced-motion:reduce)").matches;

  /* Inner pages: reveal common blocks without needing extra markup */
  $$(".page-hero>div,.section-head,.incl,.photos figure,.cta>*,.facts,.stat,.split>div").forEach(function (el, i) {
    if (!el.classList.contains("rv")) { el.classList.add("rv"); el.style.setProperty("--d", (i % 4) * 0.1 + "s"); }
  });
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); } });
  }, { threshold: 0.15, rootMargin: "0px 0px -6% 0px" });
  $$(".rv").forEach(function (el) { io.observe(el); });

  var gal = $(".gal");
  if (gal) {
    var gio = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { $$(".gal figure").forEach(function (f) { f.classList.add("in"); }); gio.disconnect(); } });
    }, { threshold: 0.2 });
    gio.observe(gal);
  }

  /* Menu */
  var links = $("#links"), ov = $("#ov"), burger = $("#burger");
  function menu(o) {
    if (!links || !burger) return;
    if (!o) closeDD();
    links.classList.toggle("open", o);
    if (ov) ov.classList.toggle("show", o);
    burger.innerHTML = o ? "&#10005;" : "&#9776;";
    burger.setAttribute("aria-expanded", o);
  }
  function closeDD(except) { $$(".dd.open").forEach(function (d) { if (d !== except) { d.classList.remove("open"); d.querySelector(".dd-btn").setAttribute("aria-expanded", "false"); } }); }
  $$(".dd-btn").forEach(function (b) {
    b.addEventListener("click", function (e) {
      e.stopPropagation();
      var d = b.parentElement, o = !d.classList.contains("open");
      closeDD(d); d.classList.toggle("open", o); b.setAttribute("aria-expanded", o);
    });
  });
  document.addEventListener("click", function () { closeDD(); });
  if (burger) burger.onclick = function () { menu(!links.classList.contains("open")); };
  if (ov) ov.onclick = function () { menu(false); };
  $$("#links a").forEach(function (a) { a.addEventListener("click", function () { menu(false); }); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") { closeDD(); menu(false); } });

  /* Scroll: progress bar, solid nav, home/about highlight, gallery parallax */
  var bar = $("#bar"), nav = $("#nav"), pars = $$("[data-par]");
  var home = $('.links a[data-nav="home"]'), about = $('.links [data-nav="about"]');
  var aboutEl = $("#about"), servEl = $("#services"), tick = false;
  function frame() {
    var y = window.scrollY, h = document.documentElement.scrollHeight - innerHeight;
    if (bar && h > 0) bar.style.width = (y / h * 100) + "%";
    if (nav) nav.classList.toggle("solid", y > 40);
    if (aboutEl && servEl && home && about) {
      var at = aboutEl.getBoundingClientRect().top < innerHeight * 0.5 && servEl.getBoundingClientRect().top > innerHeight * 0.5;
      about.classList.toggle("on", at);
      home.classList.toggle("on", !at && y < innerHeight);
    }
    if (!calm) pars.forEach(function (img) {
      var r = img.parentElement.getBoundingClientRect();
      if (r.bottom > 0 && r.top < innerHeight)
        img.style.transform = "translateY(" + Math.max(-48, Math.min(48, -((r.top + r.height / 2) - innerHeight / 2) * 0.1)) + "px)";
    });
    tick = false;
  }
  addEventListener("scroll", function () { if (!tick) { tick = true; requestAnimationFrame(frame); } }, { passive: true });
  frame();
});
