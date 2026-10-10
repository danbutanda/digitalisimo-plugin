(function () {
  'use strict';
  // Las figuras con animación de entrada se animan al aparecer en pantalla.
  function init(root) {
    var shapes = (root || document).querySelectorAll('[data-digi-shape-animation]:not(.is-visible)');
    if (!shapes.length) return;
    if (!('IntersectionObserver' in window)) { shapes.forEach(function (s) { s.classList.add('is-visible'); }); return; }
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.1 });
    shapes.forEach(function (s) { observer.observe(s); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
})();
