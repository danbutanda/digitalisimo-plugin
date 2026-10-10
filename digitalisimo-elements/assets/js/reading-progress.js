(function () {
  'use strict';
  // Ancho de la barra según el desplazamiento de la página; una sola escucha para todas las barras.
  var bars = [];
  var ticking = false;
  function update() {
    ticking = false;
    var doc = document.documentElement;
    var max = Math.max(1, doc.scrollHeight - window.innerHeight);
    var ratio = Math.min(1, Math.max(0, (window.scrollY || doc.scrollTop) / max));
    bars.forEach(function (fill) { fill.style.width = (ratio * 100).toFixed(2) + '%'; });
  }
  function request() { if (!ticking) { ticking = true; window.requestAnimationFrame(update); } }
  function init(root) {
    (root || document).querySelectorAll('[data-digi-reading-progress] .digi-reading-progress__fill').forEach(function (fill) {
      if (bars.indexOf(fill) < 0) bars.push(fill);
    });
    if (bars.length) { window.addEventListener('scroll', request, { passive: true }); window.addEventListener('resize', request); update(); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
})();
