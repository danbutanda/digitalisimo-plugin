/* Carrito del menú (digitalisimo-woo-menu-cart): abrir y cerrar el panel, sin jQuery. Sustituye al manejador de PRO.
 * Abre al pulsar el botón (o al pasar el cursor con «Al pasar el cursor»), cierra con el aspa, con un clic fuera o con
 * Escape (y devuelve el foco al botón si estaba dentro), abre solo al añadir un producto si se pidió y ajusta la altura
 * de la lista del mini carrito a la ventana. El panel cerrado lleva `inert`. Los fragmentos de WooCommerce cambian el
 * subtotal, el contador y el contenido del panel; el botón y el panel no se sustituyen. */
(function () {
  'use strict';
  var d = document, w = window, all = [];
  function conf(tw) { try { return JSON.parse(tw.getAttribute('data-digi-menu-cart')) || {}; } catch (e) { return {}; } }
  function count(btn) { var q = btn.querySelector('.elementor-button-icon-qty'); return q ? parseInt(q.getAttribute('data-counter'), 10) || 0 : 0; }
  function init(tw) {
    if (!tw || tw._digiMc) return;
    var c = conf(tw), el = tw.closest('.elementor-element') || tw, root = tw.closest('.digi-mc') || tw, btn = tw.querySelector('.digi-mc__btn'),
      box = tw.querySelector('.digi-mc__box'), main = tw.querySelector('.digi-mc__main');
    if (!btn || !box || !main) return;
    var api = tw._digiMc = { open: false };
    function height() {
      if (c.type !== 'mini-cart') return;
      var list = main.querySelector('.digi-mc__list');
      if (!list) return;
      list.style.maxHeight = '';
      var top = btn.getBoundingClientRect().top + btn.offsetHeight + (parseInt(getComputedStyle(main).marginTop, 10) || 0),
        rest = main.scrollHeight - list.offsetHeight;
      list.style.maxHeight = Math.max(120, d.documentElement.clientHeight - top - rest - 30) + 'px';
    }
    api.show = function () {
      if (api.open) return;
      api.open = true;
      el.classList.add('digi-mc--shown');
      root.classList.add('digi-mc--shown');
      btn.setAttribute('aria-expanded', 'true');
      box.removeAttribute('inert');
      height();
    };
    api.hide = function (focus) {
      if (!api.open) return;
      var inside = box.contains(d.activeElement);
      api.open = false;
      el.classList.remove('digi-mc--shown');
      root.classList.remove('digi-mc--shown');
      btn.setAttribute('aria-expanded', 'false');
      box.setAttribute('inert', '');
      if (focus || inside) btn.focus();
    };
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      if (c.open === 'mouseover') api.show(); else if (api.open) api.hide(); else api.show();
    });
    if (c.open === 'mouseover') {
      tw.addEventListener('mouseover', api.show);
      tw.addEventListener('mouseleave', function () { api.hide(); });
    }
    tw.addEventListener('click', function (e) {
      if (e.target.closest('.digi-mc__x,.digi-mc__xc')) { e.preventDefault(); api.hide(true); }
    });
    var last = count(btn), armed = false;
    new MutationObserver(function () {
      var now = count(btn);
      if (c.auto && armed && now > last) api.show();
      last = now;
    }).observe(btn, { childList: true, subtree: true });
    d.addEventListener('pointerdown', function () { armed = true; }, true);
    d.addEventListener('keydown', function () { armed = true; }, true);
    new MutationObserver(height).observe(main, { childList: true, subtree: true });
    if (c.auto && c.added) api.show();
    api.height = height;
    api.main = main;
    api.tg = btn.closest('.digi-mc__tg') || btn;
    all.push(api);
  }
  function scan(root) { (root || d).querySelectorAll('[data-digi-menu-cart]').forEach(init); }
  d.addEventListener('click', function (e) {
    all.forEach(function (a) { if (a.open && !a.main.contains(e.target) && !a.tg.contains(e.target)) a.hide(); });
  });
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') all.forEach(function (a) { a.hide(); });
  });
  w.addEventListener('resize', function () { all.forEach(function (a) { if (a.open) a.height(); }); });
  function start() {
    scan(d);
    var ef = w.elementorFrontend;
    if (ef && ef.hooks) ef.hooks.addAction('frontend/element_ready/digitalisimo-woo-menu-cart.default', function ($s) { scan($s && $s[0]); });
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})();
