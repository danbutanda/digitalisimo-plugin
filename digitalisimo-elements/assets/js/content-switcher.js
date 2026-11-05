(function () {
  'use strict';
  var initialized = new WeakSet();
  function init(root) {
    (root || document).querySelectorAll('[data-digi-content-switcher]').forEach(function (widget) {
      if (initialized.has(widget)) return;
      initialized.add(widget);
      var tabs = Array.prototype.slice.call(widget.querySelectorAll('[role="tab"]'));
      var panels = Array.prototype.slice.call(widget.querySelectorAll('[role="tabpanel"]'));
      if (!tabs.length || tabs.length !== panels.length) return;
      function select(index, focus) {
        tabs.forEach(function (tab, i) {
          tab.setAttribute('aria-selected', i === index ? 'true' : 'false');
          tab.tabIndex = i === index ? 0 : -1;
          panels[i].hidden = i !== index;
          // Una opción puede mostrar otro elemento de la página por su ID.
          var target = panels[i].getAttribute('data-digi-target');
          var element = target ? document.getElementById(target) : null;
          if (element) element.hidden = i !== index;
        });
        if (focus) tabs[index].focus();
      }
      if (panels.some(function (panel) { return panel.hasAttribute('data-digi-target'); })) {
        select(Math.min(tabs.length - 1, parseInt(widget.getAttribute('data-digi-selected') || '0', 10) || 0), false);
      }
      tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { select(index, false); });
        tab.addEventListener('keydown', function (event) {
          var next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index - 1 + tabs.length) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : -1;
          if (next < 0) return;
          event.preventDefault();
          select(next, true);
        });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    ['digitalisimo-content-switcher', 'digitalisimo-fancy-tabs'].forEach(function (name) {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/' + name + '.default', function ($scope) { init($scope[0] || document); });
    });
  }
})();
