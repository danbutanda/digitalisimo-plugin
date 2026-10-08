(function ($) {
  'use strict';
  function render(root) {
    if (!root || !$.fn.qrcode) return;
    root.querySelectorAll('[data-digi-qr]').forEach(function (figure) {
      var target = figure.querySelector('.digi-qr-code__canvas');
      var text = figure.dataset.text || '';
      if (!target || !text) return;
      var size = Math.max(64, Math.min(512, parseInt(figure.dataset.size, 10) || 200));
      var signature = [text, size, figure.dataset.fill, figure.dataset.background].join('|');
      if (target.dataset.ready === signature) return;
      target.replaceChildren();
      try {
        $(target).qrcode({render: 'canvas', text: text, size: size, fill: figure.dataset.fill || '#111111', background: figure.dataset.background || '#ffffff', quiet: 4});
        target.dataset.ready = signature;
        figure.classList.add('digi-qr-code--ready');
      } catch (error) {
        target.replaceChildren();
        figure.classList.remove('digi-qr-code--ready');
      }
    });
  }
  function init() { render(document); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once: true});
  else init();
  $(window).on('elementor/frontend/init', function () {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-qr-code.default', function ($scope) { render($scope[0] || $scope); });
    }
  });
})(jQuery);
