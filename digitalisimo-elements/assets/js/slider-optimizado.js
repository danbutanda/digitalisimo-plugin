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

  function track(slider) {
    return slider.querySelector('.digi-slider__track');
  }

  // En bucle, cada grupo sólo necesita cubrir el viewport más un elemento. Sólo se apartan repeticiones: nunca se baja
  // del número de imágenes distintas (data-digi-sources) y se conservan múltiplos enteros de la secuencia (par con
  // espejo, para que la alternancia siga en la unión). La duración se escala en la misma proporción por una variable
  // CSS, así que la velocidad lineal no cambia y la capa animada es la menor posible. «Ancho natural» no se recorta:
  // sus copias no miden una fracción del viewport. Sin la hoja de estilos aplicada todavía (CSS diferido) no hay
  // animación ni medidas fiables: se devuelve false y se reintenta al arrancar, al cargar la página y al cambiar de tamaño.
  function fit(slider) {
    if (!slider.classList.contains('digi-slider--infinite') || slider.classList.contains('digi-slider--natural')) return false;
    var viewport = slider.querySelector('.digi-slider__viewport');
    var rail = track(slider);
    if (!viewport || !rail) return false;
    var computed = window.getComputedStyle(rail);
    if (!computed || 'none' === computed.animationName || !(parseFloat(computed.animationDuration) > 0)) return false;
    var groups = Array.prototype.filter.call(rail.children, function (child) { return child.classList.contains('digi-slider__group'); });
    if (groups.length !== 2) return false;
    var spare = slider.__digiSpare || (slider.__digiSpare = [[], []]);
    var current = groups[0].children.length;
    var total = current + spare[0].length;
    var available = viewport.clientWidth;
    var groupWidth = width(groups[0]);
    if (current < 1 || total < 2 || !(available > 0) || !(groupWidth > 0)) return false;
    var items = Array.prototype.slice.call(groups[0].children);
    var unit = width(items[0]) + Math.max(0, groupWidth - items.reduce(function (sum, item) { return sum + width(item); }, 0)) / current;
    if (!(unit > 0)) return false;
    var sources = Math.max(1, parseInt(slider.dataset.digiSources, 10) || 1);
    var mirror = slider.classList.contains('digi-slider--mirror');
    var need = Math.min(total, Math.ceil(available / unit) + 1);
    var kept = Math.min(total, Math.ceil(need / sources) * sources);
    while (mirror && kept % 2 && kept < total) kept += sources;
    kept = Math.max(2, Math.min(total, kept));
    groups.forEach(function (group, index) {
      while (group.children.length > kept) {
        var last = group.children[group.children.length - 1];
        group.removeChild(last);
        spare[index].unshift(last);
      }
      while (group.children.length < kept && spare[index].length) group.appendChild(spare[index].shift());
    });
    if (kept < total) rail.style.setProperty('--digi-fit-scale', (kept / total).toFixed(4));
    else rail.style.removeProperty('--digi-fit-scale');
    slider.dataset.digiFit = '1';
    return true;
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
  // ejecutarse); las instancias lejanas quedan además en pausa real hasta que se preparan. Al liberar, el retraso y la
  // pausa desaparecen y la animación conserva su posición: si aún no había empezado parte del inicio; si el retraso ya
  // venció, sigue donde estaba, sin salto.
  function start(slider) {
    var rail = track(slider);
    if (slider.dataset.digiFit !== '1') fit(slider);
    var animations = rail && typeof rail.getAnimations === 'function' ? rail.getAnimations() : [];
    var delay = rail ? parseFloat(window.getComputedStyle(rail).animationDelay) * 1000 : 0;
    var positions = animations.map(function (animation) { return Math.max(0, (animation.currentTime || 0) - (delay > 0 ? delay : 0)); });
    slider.classList.remove('digi-slider--preparing');
    if (rail) rail.style.animationPlayState = '';
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
        watch(ready);
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
        // Lejos del viewport: en pausa real hasta prepararse, para no entrar en vista en marcha y sin imágenes.
        var rail = track(slider);
        if (rail) rail.style.animationPlayState = 'paused';
        observer.observe(slider);
      }
    });
  }

  // Cambios de tamaño del viewport o de los elementos (ventana, orientación, controles del editor): se vuelve a medir.
  var refitTimer = 0;
  function refit() {
    fitted.forEach(function (slider) {
      if ('isConnected' in slider && !slider.isConnected) { fitted.delete(slider); return; }
      fit(slider);
    });
  }
  function scheduleRefit() {
    window.clearTimeout(refitTimer);
    refitTimer = window.setTimeout(refit, 150);
  }
  var resizer = 'ResizeObserver' in window ? new ResizeObserver(scheduleRefit) : null;
  function watch(slider) {
    if (!resizer || slider.dataset.digiWatched === '1') return;
    slider.dataset.digiWatched = '1';
    var viewport = slider.querySelector('.digi-slider__viewport');
    var first = slider.querySelector('.digi-slider__item');
    if (viewport) resizer.observe(viewport);
    if (first) resizer.observe(first);
  }
  window.addEventListener('resize', scheduleRefit);
  window.addEventListener('load', scheduleRefit);

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
