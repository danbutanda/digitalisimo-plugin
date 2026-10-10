(function () {
  'use strict';
  var initialized = new WeakSet();
  var FOCUSABLE = 'a[href],area[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),iframe,[tabindex]:not([tabindex="-1"]),[contenteditable="true"]';
  function visible(el) { return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length); }
  function init(root) {
    (root || document).querySelectorAll('[data-digi-offcanvas]').forEach(function (widget) {
      if (initialized.has(widget)) return;
      initialized.add(widget);
      var panel = widget.querySelector('.digi-offcanvas__panel');
      if (!panel) return;
      var bar = panel.querySelector('.digi-offcanvas__bar');
      var config = {};
      try { config = JSON.parse(widget.getAttribute('data-digi-offcanvas') || '{}'); } catch (e) { config = {}; }
      var own = Array.prototype.slice.call(widget.querySelectorAll('[data-digi-offcanvas-open]'));
      var last = null;
      var timer = null;
      function setExpanded(value) { own.forEach(function (b) { b.setAttribute('aria-expanded', value ? 'true' : 'false'); }); }
      function open(trigger) {
        if (!panel.hidden && panel.classList.contains('is-open')) return;
        clearTimeout(timer);
        last = trigger || document.activeElement;
        panel.hidden = false;
        document.documentElement.classList.add('digi-offcanvas-open');
        setExpanded(true);
        requestAnimationFrame(function () {
          panel.classList.add('is-open');
          var first = Array.prototype.slice.call(bar.querySelectorAll(FOCUSABLE)).filter(visible)[0];
          (first || bar).focus();
        });
      }
      function close() {
        if (panel.hidden) return;
        panel.classList.remove('is-open');
        setExpanded(false);
        document.documentElement.classList.remove('digi-offcanvas-open');
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        timer = setTimeout(function () { panel.hidden = true; }, panel.classList.contains('digi-offcanvas__panel--static') || reduce ? 0 : 300);
        if (last && typeof last.focus === 'function') last.focus();
      }
      own.forEach(function (button) { button.addEventListener('click', function () { open(button); }); });
      // Apertura automática, con un máximo de veces por visitante guardado en el navegador.
      if (typeof config.auto === 'number') {
        var key = 'digi-offcanvas-' + (config.id || '');
        var seen = 0;
        try { seen = parseInt(window.localStorage.getItem(key) || '0', 10) || 0; } catch (e) { seen = 0; }
        if (!config.limit || seen < config.limit) {
          setTimeout(function () {
            try { window.localStorage.setItem(key, String(seen + 1)); } catch (e) { /* sin almacenamiento */ }
            open(null);
          }, config.auto * 1000);
        }
      }
      // Elementos externos indicados por selector (la presentación «custom» de Element Pack).
      if (config.selector) {
        document.addEventListener('click', function (event) {
          var target = null;
          try { target = event.target.closest(config.selector); } catch (e) { return; }
          if (!target || panel.contains(target)) return;
          event.preventDefault();
          open(target);
        });
      }
      panel.addEventListener('click', function (event) {
        if (event.target.closest('[data-digi-offcanvas-close]')) { close(); return; }
        if (config.overlay && event.target.hasAttribute('data-digi-offcanvas-backdrop')) close();
      });
      panel.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && config.escape) { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        var items = Array.prototype.slice.call(bar.querySelectorAll(FOCUSABLE)).filter(visible);
        if (!items.length) { event.preventDefault(); bar.focus(); return; }
        var first = items[0];
        var lastItem = items[items.length - 1];
        if (event.shiftKey && (document.activeElement === first || document.activeElement === bar)) { event.preventDefault(); lastItem.focus(); }
        else if (!event.shiftKey && document.activeElement === lastItem) { event.preventDefault(); first.focus(); }
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  function hook() {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-offcanvas.default', function ($scope) { init($scope[0] || document); });
  }
  if (window.elementorFrontend && window.elementorFrontend.hooks) hook(); else window.addEventListener('elementor/frontend/init', hook);
})();
