(function () {
  'use strict';
  // El control deslizante nativo mueve el divisor; sin JavaScript se queda en la posición inicial.
  function init(root) {
    (root || document).querySelectorAll('[data-digi-compare]').forEach(function (box) {
      var range = box.querySelector('.digi-compare__range');
      if (!range || range.dataset.ready) return;
      range.dataset.ready = '1';
      range.addEventListener('input', function () { box.style.setProperty('--digi-compare-pos', range.value + '%'); });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  window.addEventListener('elementor/frontend/init', function () {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-image-compare.default', function ($scope) { init($scope[0]); });
  });
})();
