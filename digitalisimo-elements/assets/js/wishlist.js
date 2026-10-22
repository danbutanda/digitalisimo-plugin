(function () {
  'use strict';
  // Lista de deseos (F4-Woo, V.4): el HTML es igual para todos y el estado se hidrata aquí. Invitado: localStorage del
  // sitio, escrito sólo tras un clic. Con sesión: almacén propio (woo/wishlist) o la Store API de WooCommerce (modo store).
  var w = window, d = document, C = w.digitalisimoWishlist || {};
  var KEY = C.key || 'digi_wishlist', LIMIT = C.limit || 100, MAX = C.max || 100, YEAR = 31536000;
  var logged = !!C.loggedIn, store = C.mode === 'store', ids = null, keys = {}, bad = {}, busy = {};
  var sNonce = '', wpNonce = C.nonce || '', live, timer;
  function first(r) { return r[0]; }
  function now() { return Math.floor(Date.now() / 1000); }
  function ls() { try { return w.localStorage; } catch (e) { return null; } }
  function seq(list, fn) { return list.reduce(function (p, x) { return p.then(function () { return fn(x); }); }, Promise.resolve()); }
  function readLocal() {
    var s = ls(), v = null, out = [], min = now() - YEAR;
    try { v = s && JSON.parse(s.getItem(KEY) || 'null'); } catch (e) { v = null; }
    if (!v || !Array.isArray(v.items)) return out;
    v.items.forEach(function (r) {
      var id = Array.isArray(r) ? +r[0] : 0, at = Array.isArray(r) ? +r[1] : 0;
      if (id > 0 && at >= min && !bad[id] && out.length < LIMIT && !out.some(function (x) { return x[0] === id; })) out.push([id, at]);
    });
    if (out.length !== v.items.length) writeLocal(out); // purga: más de 365 días o productos que ya no existen
    return out;
  }
  function writeLocal(rows) {
    var s = ls();
    try { if (rows.length) s.setItem(KEY, JSON.stringify({ v: 1, items: rows })); else if (s) s.removeItem(KEY); } catch (e) {}
  }
  function api(url, o, again) {
    var h = { 'Content-Type': 'application/json' };
    if (o.wp && wpNonce) h['X-WP-Nonce'] = wpNonce;
    if (o.store && sNonce) h.Nonce = sNonce;
    return fetch(url, { method: o.method || 'GET', credentials: 'same-origin', headers: h, body: o.body ? JSON.stringify(o.body) : undefined }).then(function (r) {
      var n = r.headers.get('Nonce');
      if (n) sNonce = n;
      return r.status === 204 ? {} : r.json().catch(function () { return {}; }).then(function (j) {
        if (r.ok) return j;
        var code = String(j && j.code || '');
        if (!again && o.store && /nonce/.test(code)) return api(url, o, 1);
        if (!again && o.wp && code === 'rest_cookie_invalid_nonce' && C.ajax) {
          return fetch(C.ajax + '?action=rest-nonce', { credentials: 'same-origin' }).then(function (x) { return x.ok ? x.text() : ''; }).then(function (t) {
            if (!/^[a-f0-9]{10}$/.test(t)) throw j;
            wpNonce = t;
            return api(url, o, 1);
          });
        }
        throw j;
      });
    });
  }
  function own(j) { ids = (j && j.items || []).map(function (x) { return +x.id; }); }
  function addStore(id) {
    return api(C.store, { store: 1, method: 'POST', body: { product_id: id, quantity: 1 } }).then(function (it) {
      if (ids.indexOf(id) < 0) ids.push(id);
      (keys[id] = keys[id] || []).push(it.key);
    });
  }
  function removeStore(id) {
    return seq((keys[id] || []).slice(), function (k) { return api(C.store + '/' + k, { store: 1, method: 'DELETE' }); }).then(function () {
      delete keys[id];
      ids = ids.filter(function (x) { return x !== id; });
    });
  }
  function load() {
    if (!logged) { ids = readLocal().map(first); return Promise.resolve(); }
    if (!store) { ids = (C.items || []).slice(); return Promise.resolve(); }
    return api(C.store, { store: 1 }).then(function (list) {
      ids = []; keys = {};
      (Array.isArray(list) ? list : []).forEach(function (it) {
        var id = +it.id;
        if (!id) return;
        if (!keys[id]) { keys[id] = []; ids.push(id); }
        keys[id].push(it.key);
      });
      if (!C.legacy) return;
      // Mudanza (D34): lo del almacén propio pasa a WooCommerce; con todo aceptado, se vacía el propio.
      return seq((C.items || []).filter(function (id) { return !keys[id]; }), addStore).then(function () {
        return api(C.rest + 'wishlist', { wp: 1, method: 'POST', body: { op: 'clear', moved: true } });
      }).catch(function () {});
    }, function () { ids = null; });
  }
  function say(msg) {
    if (!live) { live = d.createElement('p'); live.className = 'digi-wish-status'; live.setAttribute('role', 'status'); }
    var host = d.querySelector('dialog[open]') || d.body;
    if (live.parentNode !== host) host.appendChild(live);
    live.textContent = '';
    setTimeout(function () { live.textContent = msg; }, 30);
  }
  function fail(e) { say(e && /^digitalisimo_/.test(e.code || '') && e.message ? e.message : 'No se pudo actualizar tu lista de deseos.'); }
  function changed() { render(); d.dispatchEvent(new CustomEvent('digitalisimo:wishlist', { detail: { ids: ids.slice() } })); }
  function toggle(id) {
    if (busy[id]) return Promise.resolve();
    var on = ids.indexOf(id) < 0, p;
    busy[id] = 1;
    if (!logged) {
      var rows = readLocal();
      if (on && rows.length >= LIMIT) { delete busy[id]; say('Tu lista de deseos está llena.'); return Promise.resolve(); }
      rows = on ? rows.concat([[id, now()]]) : rows.filter(function (r) { return r[0] !== id; });
      writeLocal(rows);
      ids = rows.map(first);
      p = Promise.resolve();
    } else if (store) {
      p = on ? addStore(id) : removeStore(id);
    } else {
      p = api(C.rest + 'wishlist', { wp: 1, method: 'POST', body: { op: on ? 'add' : 'remove', product: id } }).then(own);
    }
    return p.then(function () { delete busy[id]; changed(); say(on ? 'Guardado en tu lista de deseos' : 'Quitado de tu lista de deseos'); }, function (e) { delete busy[id]; fail(e); });
  }
  function clear() {
    var p = !logged ? Promise.resolve(writeLocal([])).then(function () { ids = []; })
      : store ? seq(ids.slice(), removeStore)
      : api(C.rest + 'wishlist', { wp: 1, method: 'POST', body: { op: 'clear' } }).then(own);
    return p.then(function () { changed(); say('Lista vaciada'); }, fail);
  }
  function loginMode() { return !logged && (!C.guest || !!d.querySelector('[data-digi-wish][data-guest="login"]')); }
  function toLogin(b) {
    if (!C.login) return;
    var a = d.createElement('a'), sr = b.querySelector('.screen-reader-text'), s = d.createElement('span');
    a.className = b.className + ' digi-wish--login';
    a.href = C.login;
    a.textContent = 'Inicia sesión para guardar';
    s.className = 'screen-reader-text';
    s.textContent = ' ' + (sr ? sr.textContent.replace(/^\s*Guardar\s+/, '').trim() : 'en la lista de deseos');
    a.appendChild(s);
    b.replaceWith(a);
  }
  function show(el, has) {
    var e = el.querySelector('[data-digi-wish-empty]'), c = el.querySelector('[data-digi-wish-clear]');
    if (e) e.hidden = has;
    if (c) c.hidden = !has;
  }
  function list(el, cur) {
    var ul = el.querySelector('[data-digi-wish-items]'), req = el._digiReq;
    if (!ul) return;
    if (!el._digiBound) { el._digiBound = 1; el.addEventListener('click', onList); }
    if (req && cur.every(function (id) { return req[id]; })) {
      // Sólo se quitaron productos: se retiran sus fichas sin pedir nada (el foco no se pierde).
      [].slice.call(ul.children).forEach(function (li) { if (cur.indexOf(+li.getAttribute('data-digi-wish-item')) < 0) li.remove(); });
      return show(el, ul.children.length > 0);
    }
    el._digiReq = {};
    cur.forEach(function (id) { el._digiReq[id] = 1; });
    if (!cur.length) { ul.textContent = ''; return show(el, false); }
    var doc = el.closest('[data-elementor-id]'), post = +(doc && doc.getAttribute('data-elementor-id')) || +el.getAttribute('data-post');
    ul.setAttribute('aria-busy', 'true');
    api(C.cards || '', { method: 'POST', body: { post_id: post, widget_id: el.getAttribute('data-widget'), ids: cur.slice().reverse().slice(0, MAX) } }).then(function (j) {
      ul.innerHTML = j.html || '';
      if (!logged) { cur.forEach(function (id) { if ((j.ids || []).indexOf(id) < 0) bad[id] = 1; }); readLocal(); }
      show(el, ul.children.length > 0);
    }, function () { el._digiReq = null; }).then(function () { ul.setAttribute('aria-busy', 'false'); });
  }
  function onList(e) {
    var el = e.currentTarget, t = e.target.closest && e.target.closest('[data-digi-wish-remove],[data-digi-wish-clear]'), title = el.querySelector('.digi-wishlist__title');
    if (!t || !ids) return;
    function back(n) { var f = n && n.isConnected && n.querySelector('a[href],button'); (f || title).focus(); }
    if (t.hasAttribute('data-digi-wish-clear')) { clear().then(function () { back(); }); return; }
    var li = t.closest('li'), next = li && (li.nextElementSibling || li.previousElementSibling);
    toggle(+t.getAttribute('data-digi-wish-remove')).then(function () { back(next); });
  }
  function render() {
    if (!ids) return;
    var login = loginMode(), cur = login ? [] : ids;
    d.querySelectorAll('button[data-digi-wish]').forEach(function (b) {
      if (store && b.hasAttribute('data-variable')) return; // WooCommerce sólo guarda variaciones, no el producto variable
      if (login) return toLogin(b);
      b.setAttribute('aria-pressed', cur.indexOf(+b.getAttribute('data-digi-wish')) > -1 ? 'true' : 'false');
      b.hidden = false;
    });
    d.querySelectorAll('[data-digi-wish-count]').forEach(function (n) { n.textContent = String(cur.length); });
    d.querySelectorAll('[data-digi-wishlist]').forEach(function (el) { list(el, cur); });
  }
  function offerMerge() {
    var todo = logged ? readLocal().map(first).filter(function (id) { return ids.indexOf(id) < 0; }) : [];
    if (!logged || !ls() || !ls().getItem(KEY)) return;
    if (!todo.length) return writeLocal([]); // ya está todo en la cuenta: nada que enviar
    var box = d.createElement('div'), n = todo.length, failed = 0;
    box.className = 'digi-wish-merge';
    box.setAttribute('role', 'status');
    d.body.appendChild(box);
    setTimeout(function () {
      box.innerHTML = '<p></p><button type="button" data-add>Añadir</button><button type="button">Descartar</button>';
      box.firstChild.textContent = n === 1 ? 'Tienes 1 producto guardado en este navegador. ¿Añadirlo a tu lista?' : 'Tienes ' + n + ' productos guardados en este navegador. ¿Añadirlos a tu lista?';
    }, 30);
    box.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('button');
      if (!b) return;
      box.remove();
      if (!b.hasAttribute('data-add')) { writeLocal([]); return say('Se borró la lista de este navegador.'); }
      (store ? seq(todo, function (id) { return addStore(id).catch(function () { failed++; }); })
        : api(C.rest + 'wishlist', { wp: 1, method: 'POST', body: { op: 'merge', items: todo } }).then(own)).then(function () {
        writeLocal([]);
        changed();
        say(failed ? failed + ' de ' + n + ' no se pudieron añadir: si tienen opciones, guárdalos desde su ficha.' : 'Añadidos a tu lista de deseos');
      }, fail);
    });
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(render, 0); }
  function start() {
    d.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('button[data-digi-wish]');
      if (b && ids) { e.preventDefault(); toggle(+b.getAttribute('data-digi-wish')); }
    });
    w.addEventListener('storage', function (e) { if (!logged && e.key === KEY) { ids = readLocal().map(first); render(); } });
    var ef = w.elementorFrontend;
    if (ef && ef.hooks) ef.hooks.addAction('frontend/element_ready/global', schedule);
    load().then(function () { render(); if (ids) offerMerge(); });
  }
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})();
