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
    // Opcionales, por atributo del carrusel (Slider de dispositivos): puntos y arrastre con el ratón. Con ellos, el
    // carrusel se desplaza sólo dentro de su pista, sin mover la página.
    var dots = root.dataset.digiCarouselDots !== undefined ? Array.prototype.slice.call(root.querySelectorAll('[data-digi-carousel-dot]')) : [];
    var drag = root.dataset.digiCarouselDrag !== undefined && !!window.PointerEvent;
    if (!previous && !next && !dots.length && !drag) return;
    var index = 0;
    function rtl() { return document.documentElement.dir === 'rtl' || getComputedStyle(track).direction === 'rtl'; }
    function behavior() { return matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'; }
    function visible() {
      var value = parseInt(getComputedStyle(root).getPropertyValue('--digi-carousel-visible'), 10);
      return Math.max(1, Math.min(slides.length, isFinite(value) ? value : 1));
    }
    function sync() {
      var end = Math.max(0, slides.length - visible());
      index = Math.max(0, Math.min(index, end));
      if (previous) { previous.disabled = index === 0; previous.setAttribute('aria-disabled', previous.disabled ? 'true' : 'false'); }
      if (next) { next.disabled = index === end; next.setAttribute('aria-disabled', next.disabled ? 'true' : 'false'); }
      dots.forEach(function (dot, dotIndex) { dot.setAttribute('aria-current', dotIndex === index ? 'true' : 'false'); });
    }
    function show(target) {
      index = Math.max(0, Math.min(slides.length - visible(), target));
      if ((dots.length || drag) && track.scrollTo) {
        var box = track.getBoundingClientRect(), rect = slides[index].getBoundingClientRect();
        track.scrollTo({ left: track.scrollLeft + (rtl() ? rect.right - box.right : rect.left - box.left), behavior: behavior() });
      } else {
        slides[index].scrollIntoView({ behavior: behavior(), block: 'nearest', inline: 'start' });
      }
      sync();
    }
    function move(step) { show(index + step); }
    if (previous) previous.addEventListener('click', function () { move(-1); });
    if (next) next.addEventListener('click', function () { move(1); });
    dots.forEach(function (dot) { dot.addEventListener('click', function () { show(parseInt(dot.getAttribute('data-digi-carousel-dot'), 10) || 0); }); });
    if (drag) {
      // Arrastre con el ratón (el dedo y el panel táctil ya desplazan la pista de forma nativa).
      var start = null, moved = false, from = 0;
      track.addEventListener('pointerdown', function (event) {
        moved = false;
        if (event.pointerType !== 'mouse' || event.button !== 0 || (event.target.closest && event.target.closest('video, iframe, button'))) return;
        start = { x: event.clientX, left: track.scrollLeft, id: event.pointerId };
        from = index;
      });
      track.addEventListener('pointermove', function (event) {
        if (!start) return;
        // Botón ya suelto (se soltó fuera de la pista antes de capturar el puntero): no hay arrastre.
        if (!event.buttons) { start = null; if (moved) { moved = false; root.classList.remove('digi-carousel--dragging'); show(from); } return; }
        var dx = event.clientX - start.x;
        if (!moved && Math.abs(dx) < 6) return;
        if (!moved) { moved = true; root.classList.add('digi-carousel--dragging'); try { track.setPointerCapture(start.id); } catch (error) { /* sin captura, sigue */ } }
        track.scrollLeft = start.left - dx;
        event.preventDefault();
      });
      var end = function (event) {
        if (!start) return;
        var dx = event.clientX - start.x, width = slides[0].getBoundingClientRect().width || 1;
        start = null;
        if (!moved) return;
        root.classList.remove('digi-carousel--dragging');
        var forward = rtl() ? dx > 0 : dx < 0;
        // Tras pointercancel no llega ningún clic que suprimir.
        if (event.type === 'pointercancel') { moved = false; dx = 0; }
        show(Math.abs(dx) > width * 0.15 ? from + (forward ? 1 : -1) : from);
      };
      track.addEventListener('pointerup', end);
      track.addEventListener('pointercancel', end);
      // Tras arrastrar, el clic no abre el enlace de la captura; la imagen no se arrastra como archivo.
      track.addEventListener('click', function (event) { if (moved) { event.preventDefault(); event.stopPropagation(); moved = false; } }, true);
      track.addEventListener('dragstart', function (event) { event.preventDefault(); });
    }
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
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-logos.default', function (element) { scan(element[0]); });
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-brand-carousel.default', function (element) { scan(element[0]); });
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-logo-carousel.default', function (element) { scan(element[0]); });
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-device-slider.default', function (element) { scan(element[0]); });
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-fancy-slider.default', function (element) { scan(element[0]); });
      elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-products.default', function (element) { scan(element[0]); });
      elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-woo-products.default', function (element) { scan(element[0]); });
    }
  }
  if (window.elementorFrontend && elementorFrontend.hooks) registerEditor();
  else if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', registerEditor);
}());
