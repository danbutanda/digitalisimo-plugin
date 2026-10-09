(function () {
  'use strict';
  var initialized = new WeakMap();
  var active = new Set();
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  function refresh() {
    active.forEach(function (instance) {
      if (!instance.heading.isConnected) {
        instance.stop();
        active.delete(instance);
      } else {
        instance.schedule();
      }
    });
  }
  function init(root) {
    (root || document).querySelectorAll('.digi-animated-heading[data-digi-phrases]').forEach(function (heading) {
      var previous = initialized.get(heading);
      if (previous) {
        active.add(previous);
        previous.schedule();
        return;
      }
      var phrase = heading.querySelector('.digi-animated-heading__phrase');
      var slot = heading.querySelector('.digi-animated-heading__slot');
      if (!phrase || !slot) return;
      var words;
      try { words = JSON.parse(heading.dataset.digiPhrases); } catch (e) { return; }
      if (!Array.isArray(words) || words.length < 2 || words.some(function (word) { return typeof word !== 'string'; })) return;
      var delay = Math.max(1500, Math.min(15000, Number(heading.dataset.digiInterval) || 3000));
      var index = 0;
      var timer;
      var instance;
      function fits() {
        if (reduced && reduced.matches) return false;
        if (!heading.isConnected || slot.clientWidth === 0) return false;
        var measure = document.createElement('span');
        measure.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;width:max-content;max-width:none;';
        measure.style.font = window.getComputedStyle(phrase).font;
        document.body.appendChild(measure);
        var max = words.every(function (word) { measure.textContent = word; return measure.getBoundingClientRect().width <= slot.clientWidth - 1; });
        measure.remove();
        return max;
      }
      function schedule() {
        clearTimeout(timer);
        if (!heading.isConnected) {
          active.delete(instance);
          return;
        }
        if (!fits()) return;
        timer = setTimeout(function () {
          if (!fits()) return;
          phrase.classList.add('is-changing');
          setTimeout(function () {
            if (!heading.isConnected || (reduced && reduced.matches)) { phrase.classList.remove('is-changing'); if (!heading.isConnected) active.delete(instance); return; }
            index = (index + 1) % words.length;
            phrase.textContent = words[index];
            phrase.classList.remove('is-changing');
            schedule();
          }, 180);
        }, delay);
      }
      instance = { heading: heading, schedule: schedule, stop: function () { clearTimeout(timer); } };
      initialized.set(heading, instance);
      active.add(instance);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule); else schedule();
    });
  }
  if (reduced && reduced.addEventListener) reduced.addEventListener('change', refresh);
  window.addEventListener('resize', refresh, { passive: true });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-animated-heading.default', function ($scope) { init($scope[0] || document); });
  }
})();
