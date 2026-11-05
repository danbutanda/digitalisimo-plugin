(function () {
  'use strict';
  // Generador local (qrcode-generator, MIT): dibuja un SVG escalable sin jQuery ni servicios externos.
  function render(root) {
    if (!root || typeof window.qrcode !== 'function') return;
    root.querySelectorAll('[data-digi-qr]').forEach(function (figure) {
      var target = figure.querySelector('.digi-qr-code__canvas');
      var text = figure.dataset.text || '';
      if (!target || !text) return;
      var size = Math.max(64, Math.min(512, parseInt(figure.dataset.size, 10) || 200));
      var fill = figure.dataset.fill || '#111111';
      var background = figure.dataset.background || '#ffffff';
      var signature = [text, size, fill, background].join('|');
      if (target.dataset.ready === signature) return;
      target.replaceChildren();
      try {
        var code = window.qrcode(0, 'M');
        code.addData(text);
        code.make();
        var wrapper = document.createElement('div');
        wrapper.innerHTML = code.createSvgTag({ cellSize: 4, margin: 16, scalable: true });
        var svg = wrapper.firstElementChild;
        if (!svg) throw new Error('sin svg');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.style.width = size + 'px';
        svg.style.height = size + 'px';
        svg.style.display = 'block';
        svg.querySelectorAll('rect').forEach(function (rect) { rect.setAttribute('fill', background); });
        svg.querySelectorAll('path').forEach(function (path) { path.setAttribute('fill', fill); path.setAttribute('stroke', 'transparent'); });
        target.appendChild(svg);
        target.dataset.ready = signature;
        figure.classList.add('digi-qr-code--ready');
      } catch (error) {
        target.replaceChildren();
        figure.classList.remove('digi-qr-code--ready');
      }
    });
  }
  function init() { render(document); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
  function registerEditor() {
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-qr-code.default', function (scope) { render(scope && scope[0] ? scope[0] : scope); });
    }
  }
  if (window.elementorFrontend && window.elementorFrontend.hooks) registerEditor();
  else if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', registerEditor);
  else window.addEventListener('elementor/frontend/init', registerEditor);
}());
