(function () {
  'use strict';
  var initialized = new WeakSet();
  function item(button) { return button.closest('.digi-vertical-menu__item'); }
  function setOpen(li, open) {
    li.classList.toggle('is-open', open);
    li.querySelectorAll(':scope > .digi-vertical-menu__row [data-digi-menu-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }
  function init(root) {
    (root || document).querySelectorAll('[data-digi-vertical-menu]').forEach(function (menu) {
      if (initialized.has(menu)) return;
      initialized.add(menu);
      var drill = menu.classList.contains('digi-vertical-menu--drill');
      menu.classList.add('is-ready');
      menu.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-digi-menu-toggle]');
        var back = event.target.closest('[data-digi-menu-back]');
        if (toggle && menu.contains(toggle)) {
          var li = item(toggle);
          var open = !li.classList.contains('is-open');
          setOpen(li, open);
          if (drill) {
            li.parentElement.classList.toggle('is-drilled', open);
            if (open) { var first = li.querySelector(':scope > .digi-vertical-menu__sub [data-digi-menu-back]'); if (first) first.focus(); }
          }
        } else if (back && menu.contains(back)) {
          var parent = back.closest('.digi-vertical-menu__sub').closest('.digi-vertical-menu__item');
          setOpen(parent, false);
          parent.parentElement.classList.remove('is-drilled');
          var again = parent.querySelector(':scope > .digi-vertical-menu__row [data-digi-menu-toggle]');
          if (again) again.focus();
        }
      });
      menu.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var open = event.target.closest('.digi-vertical-menu__item.is-open');
        if (!open || !menu.contains(open)) return;
        setOpen(open, false);
        if (drill) open.parentElement.classList.remove('is-drilled');
        var t = open.querySelector(':scope > .digi-vertical-menu__row [data-digi-menu-toggle]');
        if (t) t.focus();
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }); else init(document);
  if (window.elementorFrontend && window.elementorFrontend.hooks) {
    window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-vertical-menu.default', function ($scope) { init($scope[0] || document); });
  } else {
    window.addEventListener('elementor/frontend/init', function () {
      window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-vertical-menu.default', function ($scope) { init($scope[0] || document); });
    });
  }
})();
