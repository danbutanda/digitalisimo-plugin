(function () {
  'use strict';

  var selector = '.digi-slider--preparing';
  var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      observer.unobserve(entry.target);
      prepare(entry.target);
    });
  }, { rootMargin: '600px 0px' }) : null;

  function prepare(slider) {
    if (!slider.classList.contains('digi-slider--preparing') || slider.dataset.digiPreparing === '1') return;
    slider.dataset.digiPreparing = '1';
    var viewport = slider.querySelector('.digi-slider__viewport');
    var bounds = viewport ? viewport.getBoundingClientRect() : slider.getBoundingClientRect();
    var margin = Math.max(bounds.width, 320);
    var images = Array.prototype.filter.call(slider.querySelectorAll('.digi-slider__item img'), function (image) {
      var rect = image.getBoundingClientRect();
      return rect.width > 0 && rect.right > bounds.left - margin && rect.left < bounds.right + margin;
    });
    if (!images.length) images = Array.prototype.slice.call(slider.querySelectorAll('.digi-slider__item img'), 0, 1);

    var loaded = images.map(function (image) {
      image.loading = 'eager';
      if (image.complete && image.naturalWidth > 0) return Promise.resolve();
      if (typeof image.decode === 'function') return image.decode().catch(function () {});
      return new Promise(function (resolve) {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      });
    });
    var timeout;
    Promise.race([
      Promise.all(loaded),
      new Promise(function (resolve) { timeout = window.setTimeout(resolve, 12000); })
    ]).then(function () {
      window.clearTimeout(timeout);
      window.requestAnimationFrame(function () {
        slider.classList.remove('digi-slider--preparing');
        delete slider.dataset.digiPreparing;
      });
    });
  }

  function scan(root) {
    if (!root) return;
    var sliders = root.matches && root.matches(selector) ? [root] : root.querySelectorAll(selector);
    Array.prototype.forEach.call(sliders, function (slider) {
      if (observer) observer.observe(slider);
      else prepare(slider);
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(document); });
  else scan(document);
  var hookRegistered = false;
  function registerElementorHook() {
    if (hookRegistered || !window.elementorFrontend || !window.elementorFrontend.hooks) return;
    hookRegistered = true;
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-slider-optimizado.default', function (scope) {
      scan(scope && scope[0] ? scope[0] : scope);
    });
  }
  registerElementorHook();
  if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', registerElementorHook);
}());
