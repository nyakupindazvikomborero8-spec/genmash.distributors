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
const hero = document.querySelector('.hero');
if (hero) {
  hero.addEventListener('pointermove', (e) => {
    const rect = hero.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    hero.style.setProperty('--mx', x + '%');
    hero.style.setProperty('--my', y + '%');
  });
}

/* Site-wide cursor blob trail */
(function(){
  var svgNS = "http://www.w3.org/2000/svg";
  var svg = document.createElementNS(svgNS, "svg");
  svg.setAttribute("width", "0");
  svg.setAttribute("height", "0");
  svg.style.position = "absolute";
  svg.innerHTML = '<filter id="cursor-goo"><feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 22 -9" result="goo"/><feBlend in="SourceGraphic" in2="goo"/></filter>';
  document.body.appendChild(svg);

  var wrap = document.createElement('div');
  wrap.id = 'cursor-blobs';
  wrap.setAttribute('aria-hidden', 'true');
  var b1 = document.createElement('div');
  b1.className = 'cursor-blob cb1';
  var b2 = document.createElement('div');
  b2.className = 'cursor-blob cb2';
  wrap.appendChild(b1);
  wrap.appendChild(b2);
  document.body.appendChild(wrap);

  var mx=0,my=0,b1x=0,b1y=0,b2x=0,b2y=0,started=false;

  window.addEventListener('pointermove', function(e){
    mx = e.clientX; my = e.clientY;
    if(!started){ b1x=b2x=mx; b1y=b2y=my; started=true; }
    document.body.classList.add('cursor-active');
  });
  document.addEventListener('mouseleave', function(){
    document.body.classList.remove('cursor-active');
  });

  function raf(){
    b1x += (mx-b1x)*0.18; b1y += (my-b1y)*0.18;
    b2x += (b1x-b2x)*0.12; b2y += (b1y-b2y)*0.12;
    b1.style.transform = 'translate('+b1x+'px,'+b1y+'px) translate(-50%,-50%)';
    b2.style.transform = 'translate('+b2x+'px,'+b2y+'px) translate(-50%,-50%)';
    requestAnimationFrame(raf);
  }
  raf();
})();
