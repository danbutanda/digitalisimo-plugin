(function () {
  'use strict';
  // Barra de envío gratis y cupón (F4-Woo, V.7). WooCommerce reemplaza los nodos de estado con sus fragmentos
  // ($( clave ).replaceWith(), que nunca llega a un addEventListener): aquí se observan con MutationObserver. El
  // formulario del cupón vive en el envoltorio, que nunca se reemplaza: conserva el foco y lo escrito.
  var d = document, w = window, started = false;
  function url(ep) {
    var p = w.wc_cart_fragments_params;
    return p && p.wc_ajax_url ? String(p.wc_ajax_url).replace('%%endpoint%%', ep) : '';
  }
  function refresh() { d.body.dispatchEvent(new Event('wc_fragment_refresh')); }
  function state(box, cls) { return box.querySelector(cls); }
  function ship(box) {
    var st = state(box, '.digi-ship__state'), s = st && st.getAttribute('data-state');
    if (!st || st.hasAttribute('data-digi-initial')) return;
    if (s !== 'progress' && s !== 'done') { box.hidden = true; return; }
    var t = box.getAttribute(s === 'done' ? 'data-t-done' : 'data-t-progress') || '', p = st.querySelector('.digi-ship__text'), bar = st.querySelector('.digi-ship__bar');
    if (p && t) p.textContent = t.replace(/\{restante\}/g, st.getAttribute('data-remaining') || '').replace(/\{umbral\}/g, st.getAttribute('data-threshold') || '');
    if (bar) bar.style.setProperty('--digi-ship', Math.max(0, Math.min(100, +st.getAttribute('data-percent') || 0)) + '%');
    box.hidden = false;
  }
  function say(box, msg) {
    var r = box.querySelector('.digi-coupon__status');
    if (!r) return;
    r.textContent = '';
    setTimeout(function () { r.textContent = msg; }, 30);
  }
  function coupon(box, replaced) {
    var st = state(box, '.digi-coupon__state'), f = box._digiFocus, a = d.activeElement;
    if (!st || st.hasAttribute('data-digi-initial')) return;
    box.hidden = !st.hasAttribute('data-apply') || !url('apply_coupon');
    // El foco estaba en un «Quitar» que el fragmento retiró: pasa al campo.
    if (replaced && f && !f.isConnected && (!a || a === d.body)) { var i = state(box, '.digi-coupon__input'); if (i && !box.hidden) i.focus(); }
    if (replaced && box._digiWait) box._digiWait();
  }
  // Nonce caducado (-1 o 403): un wc_fragment_refresh, esperar el estado nuevo y repetir una sola vez.
  function renew(box) {
    return new Promise(function (ok, ko) {
      var t = setTimeout(function () { box._digiWait = null; ko(new Error('timeout')); }, 8000);
      box._digiWait = function () { clearTimeout(t); box._digiWait = null; ok(); };
      refresh();
    });
  }
  function send(box, ep, data, again) {
    var st = state(box, '.digi-coupon__state'), nonce = st && st.getAttribute(ep === 'apply_coupon' ? 'data-apply' : 'data-remove'), u = url(ep), body = new URLSearchParams(data);
    if (!nonce || !u) return Promise.reject(new Error('off'));
    body.set('security', nonce);
    return fetch(u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() }).then(function (r) {
      return r.text().then(function (t) {
        if (r.status === 403 || String(t).trim() === '-1') {
          if (again) throw new Error('nonce');
          return renew(box).then(function () { return send(box, ep, data, 1); });
        }
        if (!r.ok) throw new Error('http');
        return t;
      });
    });
  }
  // La respuesta es el HTML de los avisos de WooCommerce: se lee en un documento inerte y se pasa a texto.
  function result(box, html, fallback) {
    var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html'), err = !!doc.querySelector('.woocommerce-error,.is-error'), text = String(doc.body.textContent || '').replace(/\s+/g, ' ').trim();
    say(box, text || fallback);
    return !err;
  }
  function bind(box) {
    var form = box.querySelector('.digi-coupon__form'), input = state(box, '.digi-coupon__input');
    box.addEventListener('focusin', function (e) { box._digiFocus = e.target; });
    if (form && input) form.addEventListener('submit', function (e) {
      var code = input.value.trim();
      e.preventDefault();
      if (!code) { say(box, 'Escribe un código de cupón.'); input.focus(); return; }
      if (box._digiBusy) return;
      box._digiBusy = 1;
      send(box, 'apply_coupon', { coupon_code: code }).then(function (html) {
        if (result(box, html, 'Cupón aplicado')) { input.value = ''; refresh(); }
      }, function () { say(box, 'No se pudo aplicar el cupón. Recarga la página.'); }).then(function () { box._digiBusy = 0; });
    });
    box.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('.digi-coupon__remove');
      if (!b || box._digiBusy) return;
      box._digiBusy = 1;
      send(box, 'remove_coupon', { coupon: b.getAttribute('data-coupon') || '' }).then(function (html) {
        if (result(box, html, 'Cupón quitado')) refresh();
      }, function () { say(box, 'No se pudo quitar el cupón. Recarga la página.'); }).then(function () { box._digiBusy = 0; });
    });
  }
  function init(root) {
    (root || d).querySelectorAll('[data-digi-ship],[data-digi-coupon]').forEach(function (box) {
      if (box._digiReady) return;
      box._digiReady = 1;
      var bar = box.hasAttribute('data-digi-ship');
      if (!bar) bind(box);
      new MutationObserver(function () { if (bar) ship(box); else coupon(box, true); }).observe(box, { childList: true });
      if (bar) ship(box); else coupon(box, false);
    });
  }
  // Una copia de fragmentos anterior a la barra no trae sus nodos: un solo refresco si siguen sin estado tras cargar.
  function late() {
    setTimeout(function () { if (url('get_refreshed_fragments') && d.querySelector('.digi-ship__state[data-digi-initial],.digi-coupon__state[data-digi-initial]')) refresh(); }, 3000);
  }
  function start() {
    if (started) return;
    started = true;
    init(d);
    if (d.readyState === 'complete') late(); else w.addEventListener('load', late);
    var ef = w.elementorFrontend;
    if (ef && ef.hooks) {
      ef.hooks.addAction('frontend/element_ready/woocommerce-menu-cart.default', function ($s) { init($s && $s[0]); });
      ef.hooks.addAction('frontend/element_ready/digitalisimo-woo-shipping-bar.default', function ($s) { init($s && $s[0]); });
    }
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})();
