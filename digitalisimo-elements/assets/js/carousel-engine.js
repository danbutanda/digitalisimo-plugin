(function () {
  'use strict';
  function init(root) {
    if (!root || root.dataset.digiCarouselReady) return;
    var track = root.querySelector('[data-digi-carousel-track]');
    var list = track && track.querySelector('.digi-carousel__slides');
    if (!list || !list.children.length) return;
    root.dataset.digiCarouselReady = '1';
    var slides = Array.prototype.slice.call(list.children);
    var previous = root.querySelector('[data-digi-carousel-prev]');
    var next = root.querySelector('[data-digi-carousel-next]');
    if (!previous && !next) return;
    var index = 0;
    function visible() {
      var value = parseInt(getComputedStyle(root).getPropertyValue('--digi-carousel-visible'), 10);
      return Math.max(1, Math.min(slides.length, isFinite(value) ? value : 1));
    }
    function sync() {
      var end = Math.max(0, slides.length - visible());
      index = Math.max(0, Math.min(index, end));
      if (previous) { previous.disabled = index === 0; previous.setAttribute('aria-disabled', previous.disabled ? 'true' : 'false'); }
      if (next) { next.disabled = index === end; next.setAttribute('aria-disabled', next.disabled ? 'true' : 'false'); }
    }
    function move(step) {
      index = Math.max(0, Math.min(slides.length - visible(), index + step));
      slides[index].scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest', inline: 'start' });
      sync();
    }
    if (previous) previous.addEventListener('click', function () { move(-1); });
    if (next) next.addEventListener('click', function () { move(1); });
    var frame = 0;
    track.addEventListener('scroll', function () {
      if (frame) return;
      frame = requestAnimationFrame(function () {
        frame = 0;
        var edge = document.documentElement.dir === 'rtl' ? track.getBoundingClientRect().right : track.getBoundingClientRect().left;
        var closest = Infinity;
        slides.forEach(function (slide, slideIndex) {
          var rect = slide.getBoundingClientRect();
          var distance = Math.abs((document.documentElement.dir === 'rtl' ? rect.right : rect.left) - edge);
          if (distance < closest) { closest = distance; index = slideIndex; }
        });
        sync();
      });
    }, { passive: true });
    if (window.ResizeObserver) new ResizeObserver(sync).observe(root);
    sync();
  }
  function scan(scope) {
    (scope || document).querySelectorAll('.digi-carousel').forEach(init);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(document); });
  else scan(document);
  function registerEditor() {
    if (window.elementorFrontend && elementorFrontend.hooks) {
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-brand-carousel.default', function (element) { scan(element[0]); });
    }
  }
  if (window.elementorFrontend && elementorFrontend.hooks) registerEditor();
  else if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', registerEditor);
}());
