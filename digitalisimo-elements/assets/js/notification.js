(function () {
  'use strict';
  var initialized = new WeakSet();
  function init(root) {
    (root || document).querySelectorAll('[data-digi-notification]').forEach(function (panel) {
      if (initialized.has(panel)) return;
      initialized.add(panel);
      var timer;
      var timeout = Math.max(0, Math.min(60000, Number(panel.dataset.timeout) || 0));
      function stopTimer() { if (timer) clearTimeout(timer); timer = null; }
      function hide() { stopTimer(); panel.hidden = true; }
      function startTimer() { stopTimer(); if (timeout) timer = setTimeout(hide, timeout); }
      function show() { panel.hidden = false; startTimer(); }
      var close = panel.querySelector('.digi-notification__close');
      if (close) close.addEventListener('click', hide);
      panel.addEventListener('mouseenter', stopTimer);
      panel.addEventListener('mouseleave', function () { if (!panel.hidden) startTimer(); });
      panel.addEventListener('focusin', stopTimer);
      panel.addEventListener('focusout', function (event) { if (!panel.hidden && !panel.contains(event.relatedTarget)) startTimer(); });
      var event = panel.dataset.event || 'onload';
      var selector = panel.dataset.selector || '';
      var trigger = selector && /^[#.][A-Za-z][A-Za-z0-9_-]{0,80}$/.test(selector) ? document.querySelector(selector) : null;
      if (event === 'click') {
        if (trigger) trigger.addEventListener('click', show);
      } else if (event === 'mouseover') {
        if (trigger) {
          trigger.addEventListener('mouseenter', show);
          trigger.addEventListener('focusin', show);
        }
      } else if (event === 'inDelay') {
        setTimeout(show, Math.max(0, Math.min(30000, Number(panel.dataset.delay) || 0)));
      } else {
        show();
      }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-notification.default', function ($scope) { init($scope[0] || document); });
})();
