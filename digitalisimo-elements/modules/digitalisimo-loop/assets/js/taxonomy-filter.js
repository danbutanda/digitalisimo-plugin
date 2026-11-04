/* DIGITALÍSIMO Elements · Filtro de taxonomías (C5): botones con aria-pressed; escribe en la URL los parámetros de PRO
   (e-filter-<bucle>-<taxonomía>) y pide la misma página para sustituir el contenido del bucle. Sin jQuery ni REST. */
((w, d) => {
	'use strict';
	if (w.digiTaxonomyFilter) return;
	const ALL = '__all';
	const fe = () => w.elementorFrontend;
	const edit = () => !!(fe() && fe().isEditMode && fe().isEditMode());
	const items = (s) => [...s.querySelectorAll('.digi-tfilter__item')];
	const pressed = (s) => items(s).filter((b) => b.getAttribute('aria-pressed') === 'true').map((b) => b.dataset.filter);
	const docOf = (el) => (el && el.closest ? el.closest('[data-elementor-id]') : null);
	const loopIn = (root, id, docId) => (docId && root.querySelector(`[data-elementor-id="${docId}"] .elementor-element-${id}`)) || root.querySelector(`.elementor-element-${id}`);
	const ctrl = {};
	// Región que anuncia lo cargado (una por filtro).
	const status = (s) => {
		let r = s.nextElementSibling;
		if (!r || !r.classList.contains('digi-tfilter-status')) {
			r = d.createElement('div');
			r.className = 'digi-tfilter-status';
			r.setAttribute('role', 'status');
			s.after(r);
		}
		return r;
	};
	// URL con los términos de este filtro: los demás parámetros (otros filtros del bucle, F7b) quedan como están.
	const url = (s, terms) => {
		const id = s.dataset.loop, key = `e-filter-${id}-${s.dataset.taxonomy}`, E = encodeURIComponent, parts = [];
		const page = +s.dataset.pageNum || 1;
		let path = location.pathname;
		if (page > 1 && s.dataset.baseUrl) try { path = new URL(s.dataset.baseUrl, location.href).pathname; } catch (e) {}
		new URLSearchParams(location.search).forEach((v, k) => {
			if (k !== key && k !== `e-page-${id}` && !(page > 1 && (k === 'page' || k === 'paged'))) parts.push(`${E(k)}=${E(v)}`);
		});
		if (terms.length) parts.push(`${E(key)}=${terms.map(E).join(s.dataset.join === 'OR' ? '~' : '+')}`);
		return path + (parts.length ? `?${parts.join('&')}` : '') + location.hash;
	};
	// Pide la página y sustituye el contenido del bucle (propio o de PRO) por el de la respuesta.
	const refresh = (s, href, push) => {
		const id = s.dataset.loop, doc = docOf(s), docId = doc ? doc.getAttribute('data-elementor-id') : '';
		const live = loopIn(d, id, docId);
		if (!live) return;
		if (push && href !== location.pathname + location.search + location.hash) history.pushState({ digiFilter: 1 }, '', href);
		if (ctrl[id]) ctrl[id].abort();
		const c = ctrl[id] = new AbortController();
		live.setAttribute('aria-busy', 'true');
		live.setAttribute('data-digi-loading', '');
		const done = () => { live.removeAttribute('aria-busy'); live.removeAttribute('data-digi-loading'); };
		status(s).textContent = s.dataset.loading || '';
		fetch(href, { credentials: 'same-origin', signal: c.signal }).then((r) => (r.ok ? r.text() : Promise.reject(r))).then((html) => {
			const fresh = loopIn(new DOMParser().parseFromString(html, 'text/html'), id, docId);
			if (!fresh) throw new Error('loop');
			const had = live.contains(d.activeElement);
			live.classList.remove('e-load-more-pagination-end');
			live.replaceChildren(...[...fresh.childNodes].map((n) => d.importNode(n, true)));
			done();
			const n = live.querySelectorAll('.e-loop-item').length;
			status(s).textContent = n ? (s.dataset.many || '%d').replace('%d', n) : (s.dataset.none || '');
			if (had) { live.setAttribute('tabindex', '-1'); live.focus(); }
			const h = fe() && fe().elementsHandler;
			if (h) { h.runReadyTrigger(live); live.querySelectorAll('.e-loop-item .elementor-element').forEach((el) => h.runReadyTrigger(el)); }
			d.dispatchEvent(new Event('elementor/lazyload/observe'));
			live.dispatchEvent(new CustomEvent('digi-loop-updated', { bubbles: true }));
		}).catch((e) => {
			if (e && e.name === 'AbortError') return;
			done();
			location.assign(href);
		});
	};
	const click = (b) => {
		const s = b.closest('.digi-tfilter'), f = b.dataset.filter, on = pressed(s), multi = s.dataset.join !== 'DISABLED';
		if (f === ALL && on.includes(ALL)) return;
		const set = (v, x) => items(s).forEach((i) => { if (v === null || i.dataset.filter === v) i.setAttribute('aria-pressed', String(x)); });
		if (on.includes(f)) {
			set(f, false);
			if (!multi || (!on.includes(ALL) && on.length === 1)) { set(null, false); set(ALL, true); }
		} else {
			if (!multi || f === ALL) set(null, false);
			set(f, true);
			if (f !== ALL) set(ALL, false);
		}
		if (edit()) return;
		refresh(s, url(s, pressed(s).filter((t) => t !== ALL)), true);
	};
	d.addEventListener('click', (e) => {
		const b = e.target.closest && e.target.closest('.digi-tfilter__item');
		if (b && b.closest('.digi-tfilter')) click(b);
	});
	// Arrastre con el ratón cuando la fila se desplaza (como PRO); el táctil y la rueda ya desplazan.
	let drag = null;
	d.addEventListener('pointerdown', (e) => {
		const s = e.pointerType === 'mouse' && e.target.closest && e.target.closest('.digi-tfilter');
		if (s && s.scrollWidth > s.clientWidth) drag = { s, x: e.clientX, l: s.scrollLeft, moved: false };
	});
	d.addEventListener('pointermove', (e) => {
		if (!drag) return;
		const dx = e.clientX - drag.x;
		if (Math.abs(dx) > 5) { drag.moved = true; drag.s.classList.add('digi-tfilter--drag'); }
		if (drag.moved) drag.s.scrollLeft = drag.l - dx;
	});
	d.addEventListener('pointerup', () => {
		if (drag && drag.moved) {
			drag.s.classList.remove('digi-tfilter--drag');
			const stop = (e) => { e.stopPropagation(); e.preventDefault(); };
			d.addEventListener('click', stop, { capture: true, once: true });
			setTimeout(() => d.removeEventListener('click', stop, { capture: true }), 0);
		}
		drag = null;
	});
	// Atrás y Adelante: los botones y el bucle siguen a la URL.
	w.addEventListener('popstate', () => {
		const seen = {}, q = new URLSearchParams(location.search);
		d.querySelectorAll('.digi-tfilter[data-loop]').forEach((s) => {
			const v = q.get(`e-filter-${s.dataset.loop}-${s.dataset.taxonomy}`) || '';
			const t = v ? v.split(v.includes('~') ? '~' : ' ') : [];
			items(s).forEach((b) => b.setAttribute('aria-pressed', String(t.length ? t.includes(b.dataset.filter) : b.dataset.filter === ALL)));
			if (!seen[s.dataset.loop] && !edit()) { seen[s.dataset.loop] = 1; refresh(s, location.pathname + location.search + location.hash, false); }
		});
	});
	w.digiTaxonomyFilter = { refresh };
})(window, document);
