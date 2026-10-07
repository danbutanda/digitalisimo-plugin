(function () {
  'use strict';

  var selector = '.digi-slider--preparing';
  var queued = new Set();
  var queuedFrame = 0;
  var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) queue(entry.target);
    });
  }, { rootMargin: '600px 0px' }) : null;

  function nearViewport(slider) {
    var rect = slider.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0 && rect.bottom >= -600 &&
      rect.top <= window.innerHeight + 600 && rect.right > 0 && rect.left < window.innerWidth;
  }

  function queue(slider) {
    if (!slider.classList.contains('digi-slider--preparing') || slider.dataset.digiPreparing === '1') return;
    queued.add(slider);
    if (queuedFrame) return;
    queuedFrame = window.requestAnimationFrame(function () {
      queuedFrame = 0;
      var batch = Array.from(queued);
      queued.clear();
      startBatch(batch);
    });
  }

  function prepare(slider) {
    slider.dataset.digiPreparing = '1';
    var viewport = slider.querySelector('.digi-slider__viewport');
    var bounds = viewport ? viewport.getBoundingClientRect() : slider.getBoundingClientRect();
    var margin = Math.max(bounds.width, 320);
    var allImages = slider.querySelectorAll('.digi-slider__item img');
    var images = Array.prototype.filter.call(allImages, function (image) {
      if (slider.classList.contains('digi-slider--mirror-repeat')) return true;
      var rect = image.getBoundingClientRect();
      return rect.width > 0 && rect.right > bounds.left - margin && rect.left < bounds.right + margin;
    });
    if (!images.length) images = Array.prototype.slice.call(allImages, 0, 1);

    var loaded = images.map(function (image) {
      image.loading = 'eager';
      if (image.complete && image.naturalWidth > 0) return Promise.resolve();
      if (typeof image.decode === 'function') {
        try { return Promise.resolve(image.decode()).catch(function () {}); }
        catch (error) { return Promise.resolve(); }
      }
      return new Promise(function (resolve) {
        image.addEventListener('load', resolve, { once: true });
        image.addEventListener('error', resolve, { once: true });
      });
    });
    return Promise.all(loaded);
  }

  function startBatch(sliders) {
    var batch = sliders.filter(function (slider) {
      return slider.classList.contains('digi-slider--preparing') && slider.dataset.digiPreparing !== '1';
    });
    if (!batch.length) return;
    batch.forEach(function (slider) { if (observer) observer.unobserve(slider); });
    var timeout;
    Promise.race([
      Promise.all(batch.map(prepare)),
      new Promise(function (resolve) { timeout = window.setTimeout(resolve, 12000); })
    ]).then(function () {
      window.clearTimeout(timeout);
      // Todas las instancias visibles empiezan en el mismo fotograma.
      window.requestAnimationFrame(function () {
        batch.forEach(function (slider) {
          if ('isConnected' in slider && !slider.isConnected) return;
          slider.classList.remove('digi-slider--preparing');
          delete slider.dataset.digiPreparing;
        });
      });
    });
  }

  function scan(root) {
    if (!root) return;
    var sliders = [];
    if (root.matches && root.matches(selector)) sliders.push(root);
    if (root.querySelectorAll) Array.prototype.forEach.call(root.querySelectorAll(selector), function (slider) { sliders.push(slider); });
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    sliders.forEach(function (slider) {
      if (reduced) {
        slider.classList.remove('digi-slider--preparing');
      } else if (!observer || nearViewport(slider)) {
        queue(slider);
      } else {
        observer.observe(slider);
      }
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
