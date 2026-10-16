(function () {
  'use strict';

  var selector = '.digi-slider--preparing';
  // Una imagen lenta no retiene el arranque más de esto: el movimiento empieza y la imagen aparece al llegar.
  var TIMEOUT = window.DIGI_SLIDER_TIMEOUT || 4000;
  var queued = new Set();
  var queuedFrame = 0;
  var releasing = new Set();
  var releaseFrame = 0;
  var fitted = new Set();
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

  function width(element) {
    return element.getBoundingClientRect().width;
  }

  // En bucle, cada grupo sólo necesita cubrir el viewport más un elemento (par con espejo, para conservar la
  // alternancia en la unión). Los elementos sobrantes se apartan de la pista y la duración se escala en la misma
  // proporción, así que la velocidad lineal no cambia y la capa animada es la menor posible. Al cambiar el tamaño
  // de la ventana se vuelve a medir y, si hacen falta, los elementos apartados regresan.
  function fit(slider) {
    if (!slider.classList.contains('digi-slider--infinite')) return;
    var viewport = slider.querySelector('.digi-slider__viewport');
    var track = slider.querySelector('.digi-slider__track');
    if (!viewport || !track) return;
    var groups = Array.prototype.filter.call(track.children, function (child) { return child.classList.contains('digi-slider__group'); });
    if (groups.length !== 2) return;
    var spare = slider.__digiSpare || (slider.__digiSpare = [[], []]);
    groups.forEach(function (group, index) {
      spare[index].forEach(function (item) { group.appendChild(item); });
      spare[index] = [];
    });
    var items = Array.prototype.slice.call(groups[0].children);
    var available = viewport.clientWidth;
    var full = width(groups[0]);
    if (items.length < 2 || !(available > 0) || !(full > 0) || !slider.dataset.digiDuration) {
      if (!slider.dataset.digiDuration) slider.dataset.digiDuration = window.getComputedStyle(track).animationDuration;
      if (items.length < 2 || !(available > 0) || !(full > 0)) return;
    }
    var mirror = slider.classList.contains('digi-slider--mirror');
    var gapEach = Math.max(0, full - items.reduce(function (sum, item) { return sum + width(item); }, 0)) / items.length;
    var covered = 0;
    var need = items.length;
    for (var i = 0; i < items.length; i++) {
      covered += width(items[i]) + gapEach;
      if (covered >= available) { need = Math.min(items.length, i + 2); break; }
    }
    if (mirror && need % 2 && need < items.length) need++;
    if (need < items.length) {
      groups.forEach(function (group, index) {
        Array.prototype.slice.call(group.children, need).forEach(function (item) {
          spare[index].push(item);
          group.removeChild(item);
        });
      });
      var base = parseFloat(slider.dataset.digiDuration);
      var kept = width(groups[0]);
      track.style.animationDuration = base > 0 && kept > 0 ? (base * kept / full).toFixed(3) + 's' : '';
    } else {
      track.style.animationDuration = '';
    }
  }

  function prepare(slider) {
    slider.dataset.digiPreparing = '1';
    fit(slider);
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
    return Promise.all(loaded).catch(function () {});
  }

  // Mientras se prepara, el CSS retrasa el movimiento tres segundos (así arranca solo aunque este script no llegue a
  // ejecutarse). Al liberar, el retraso desaparece y la animación conserva su posición: si aún no había empezado,
  // parte del inicio; si el retraso ya venció, sigue donde estaba, sin salto.
  function start(slider) {
    var track = slider.querySelector('.digi-slider__track');
    var animations = track && typeof track.getAnimations === 'function' ? track.getAnimations() : [];
    var delay = track ? parseFloat(window.getComputedStyle(track).animationDelay) * 1000 : 0;
    var positions = animations.map(function (animation) { return Math.max(0, (animation.currentTime || 0) - (delay > 0 ? delay : 0)); });
    slider.classList.remove('digi-slider--preparing');
    animations.forEach(function (animation, index) {
      try { animation.currentTime = positions[index]; } catch (error) { /* la animación ya no existe */ }
    });
  }

  // Las instancias listas en el mismo ciclo arrancan en el mismo fotograma; una lenta no retiene a las demás.
  function release(slider) {
    releasing.add(slider);
    if (releaseFrame) return;
    releaseFrame = window.requestAnimationFrame(function () {
      releaseFrame = 0;
      releasing.forEach(function (ready) {
        if ('isConnected' in ready && !ready.isConnected) return;
        start(ready);
        delete ready.dataset.digiPreparing;
        fitted.add(ready);
      });
      releasing.clear();
    });
  }

  function startBatch(sliders) {
    sliders.forEach(function (slider) {
      if (!slider.classList.contains('digi-slider--preparing') || slider.dataset.digiPreparing === '1') return;
      if (observer) observer.unobserve(slider);
      var timeout;
      Promise.race([
        prepare(slider),
        new Promise(function (resolve) { timeout = window.setTimeout(resolve, TIMEOUT); })
      ]).then(function () {
        window.clearTimeout(timeout);
        release(slider);
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

  var resizeTimer = 0;
  window.addEventListener('resize', function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(function () {
      fitted.forEach(function (slider) {
        if ('isConnected' in slider && !slider.isConnected) { fitted.delete(slider); return; }
        fit(slider);
      });
    }, 250);
  });

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
