(() => {
// Lista de deseos (F4-Woo V.4): invitado en localStorage (sólo tras un clic); con sesión, woo/wishlist o la Store API.
const w = window, d = document, C = w.digitalisimoWishlist || {}, K = C.key || 'digi_wishlist', LIM = C.limit || 100, MAX = C.max || 100;
const logged = !!C.loggedIn, store = C.mode === 'store', T = C.t || {}, keys = {}, bad = {}, busy = {}, P = Promise.resolve();
let ids = null, sn = '', wn = C.nonce || '', live, tm;
const q = (s, r) => (r || d).querySelector(s), qa = (s, r) => [...(r || d).querySelectorAll(s)], at = (e, a) => e.getAttribute(a);
const first = r => r[0], now = () => Date.now() / 1e3 | 0, seq = (a, fn) => a.reduce((p, x) => p.then(() => fn(x)), P);
const ls = () => { try { return w.localStorage; } catch {} };
const wl = rows => { const s = ls(); try { if (rows.length) s.setItem(K, JSON.stringify({ v: 1, items: rows })); else if (s) s.removeItem(K); } catch {} };
const rl = () => {
	const s = ls(), o = [], min = now() - 31536000;
	let v = null;
	try { v = s && JSON.parse(s.getItem(K) || 'null'); } catch {}
	if (!v || !Array.isArray(v.items)) return o;
	v.items.forEach(r => {
		const id = r && +r[0], t = r && +r[1];
		if (id > 0 && t >= min && !bad[id] && o.length < LIM && !o.some(x => x[0] === id)) o.push([id, t]);
	});
	if (o.length !== v.items.length) wl(o);
	return o;
};
const api = (url, o, again) => {
	const h = { 'Content-Type': 'application/json' };
	if (o.wp && wn) h['X-WP-Nonce'] = wn;
	if (o.st && sn) h.Nonce = sn;
	return fetch(url, { method: o.m || 'GET', headers: h, body: o.b && JSON.stringify(o.b) }).then(r => {
		sn = r.headers.get('Nonce') || sn;
		return (r.status === 204 ? P : r.json()).catch(() => 0).then(j => {
			j = j || {};
			const c = j.code;
			if (r.ok) return j;
			if (!again && o.st && /nonce/.test(c)) return api(url, o, 1);
			if (again || !o.wp || c !== 'rest_cookie_invalid_nonce' || !C.ajax) throw j;
			return fetch(C.ajax + '?action=rest-nonce').then(x => (x.ok ? x.text() : '')).then(t => {
				if (!/^[a-f0-9]{10}$/.test(t)) throw j;
				wn = t;
				return api(url, o, 1);
			});
		});
	});
};
const own = j => { ids = (j.items || []).map(x => +x.id); };
const post = b => api(C.rest + 'wishlist', { wp: 1, m: 'POST', b });
const add = id => api(C.store, { st: 1, m: 'POST', b: { product_id: id, quantity: 1 } }).then(it => {
	if (!ids.includes(id)) ids.push(id);
	(keys[id] = keys[id] || []).push(it.key);
});
const del = id => seq((keys[id] || []).slice(), k => api(C.store + '/' + k, { st: 1, m: 'DELETE' })).then(() => {
	delete keys[id];
	ids = ids.filter(x => x !== id);
});
const load = () => {
	if (!logged) { ids = rl().map(first); return P; }
	if (!store) { ids = (C.items || []).slice(); return P; }
	return api(C.store, { st: 1 }).then(list => {
		ids = [];
		[].concat(list || []).forEach(it => {
			const id = +it.id;
			if (id) (keys[id] || (ids.push(id), keys[id] = [])).push(it.key);
		});
		if (C.legacy) return seq((C.items || []).filter(id => !keys[id]), add).then(() => post({ op: 'clear', moved: true })).catch(() => {});
	}, () => { ids = null; });
};
const say = m => {
	if (!live) (live = d.createElement('p')).className = 'digi-wish-status', live.setAttribute('role', 'status');
	const host = q('dialog[open]') || d.body;
	if (live.parentNode !== host) host.appendChild(live);
	live.textContent = '';
	setTimeout(() => { live.textContent = m; }, 30);
};
const fail = e => say(e && e.message && /^digitalisimo_/.test(e.code) ? e.message : T.error);
const changed = () => { render(); d.dispatchEvent(new CustomEvent('digitalisimo:wishlist', { detail: { ids: ids.slice() } })); };
const toggle = id => {
	const on = !ids.includes(id), off = () => delete busy[id];
	let p = P, rows;
	if (busy[id]) return P;
	if (!logged) {
		rows = rl();
		if (on && rows.length >= LIM) return say(T.full), P;
		wl(rows = on ? rows.concat([[id, now()]]) : rows.filter(r => r[0] !== id));
		ids = rows.map(first);
	} else p = store ? (on ? add(id) : del(id)) : post({ op: on ? 'add' : 'remove', product: id }).then(own);
	busy[id] = 1;
	return p.then(() => { off(); changed(); say(on ? T.saved : T.removed); }, e => { off(); fail(e); });
};
const clear = () => (!logged ? P.then(() => { wl([]); ids = []; }) : store ? seq(ids.slice(), del) : post({ op: 'clear' }).then(own))
	.then(() => { changed(); say(T.cleared); }, fail);
const show = (el, has) => {
	const e = q('[data-digi-wish-empty]', el), c = q('[data-digi-wish-clear]', el);
	if (e) e.hidden = has;
	if (c) c.hidden = !has;
};
const list = (el, cur) => {
	const ul = q('[data-digi-wish-items]', el), req = el._dr, doc = el.closest('[data-elementor-id]');
	if (!ul) return;
	if (req && cur.every(id => req[id])) {
		qa('li', ul).forEach(li => { if (!cur.includes(+at(li, 'data-digi-wish-item'))) li.remove(); });
		return show(el, ul.children.length > 0);
	}
	el._dr = {};
	cur.forEach(id => { el._dr[id] = 1; });
	if (!cur.length) { ul.textContent = ''; return show(el, false); }
	ul.setAttribute('aria-busy', 'true');
	api(C.cards || '', { m: 'POST', b: { post_id: +(doc && at(doc, 'data-elementor-id')) || +at(el, 'data-post'), widget_id: at(el, 'data-widget'), ids: cur.slice().reverse().slice(0, MAX) } }).then(j => {
		ul.innerHTML = j.html || '';
		if (!logged) { cur.forEach(id => { if (!(j.ids || []).includes(id)) bad[id] = 1; }); rl(); }
		show(el, ul.children.length > 0);
	}, () => { el._dr = null; }).then(() => ul.setAttribute('aria-busy', 'false'));
};
function render() {
	if (!ids) return;
	const login = !logged && (!C.guest || !!q('[data-digi-wish-login]')), cur = login ? [] : ids;
	qa('button[data-digi-wish]').forEach(b => {
		b.hidden = login || (store && b.hasAttribute('data-variable'));
		b.setAttribute('aria-pressed', cur.includes(+at(b, 'data-digi-wish')) ? 'true' : 'false');
	});
	qa('[data-digi-wish-login]').forEach(a => { a.hidden = !login; });
	qa('[data-digi-wish-count]').forEach(n => { n.textContent = String(cur.length); });
	qa('[data-digi-wishlist]').forEach(el => list(el, cur));
}
const merge = () => {
	const s = ls();
	if (!logged || !s || !s.getItem(K)) return;
	const todo = rl().map(first).filter(id => !ids.includes(id)), n = todo.length, one = n < 2, box = d.createElement('div');
	let failed = 0;
	if (!n) return wl([]);
	box.className = 'digi-wish-merge';
	box.setAttribute('role', 'status');
	d.body.appendChild(box);
	setTimeout(() => {
		box.innerHTML = '<p></p><button type="button" data-add></button><button type="button"></button>';
		[box.firstChild.textContent, box.children[1].textContent, box.children[2].textContent] = [(one ? T.ask1 : T.ask).replace('%d', n), T.add, T.discard];
	}, 30);
	box.addEventListener('click', e => {
		const b = e.target.closest('button');
		if (!b) return;
		box.remove();
		if (!b.hasAttribute('data-add')) { wl([]); say(T.discarded); return; }
		(store ? seq(todo, id => add(id).catch(() => { failed++; })) : post({ op: 'merge', items: todo }).then(own)).then(() => {
			wl([]);
			changed();
			say(failed ? T.failed.replace('%d', failed) : T.added);
		}, fail);
	});
};
const start = () => {
	const ef = w.elementorFrontend;
	d.addEventListener('click', e => {
		const t = e.target.closest('button[data-digi-wish],[data-digi-wish-remove],[data-digi-wish-clear]');
		if (!t || !ids) return;
		const el = t.closest('[data-digi-wishlist]'), li = t.closest('li'), next = li && (li.nextElementSibling || li.previousElementSibling);
		const back = n => { const f = n && n.isConnected && qa('a[href],button', n).find(x => at(x, 'tabindex') != -1); (f || q('.digi-wishlist__title', el)).focus(); };
		if (t.hasAttribute('data-digi-wish-clear')) return void clear().then(() => back());
		toggle(+(at(t, 'data-digi-wish') || at(t, 'data-digi-wish-remove'))).then(() => { if (el) back(next); });
	});
	w.addEventListener('storage', e => { if (!logged && e.key === K) { ids = rl().map(first); render(); } });
	ef && ef.hooks && ef.hooks.addAction('frontend/element_ready/global', () => { clearTimeout(tm); tm = setTimeout(render, 0); });
	load().then(() => { render(); if (ids) merge(); });
};
if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
})();
