/* DIGITALÍSIMO Elements · Cuadrícula de bucle (C5): paginación sin recargar (pagination_load_type = ajax). Pide el
   enlace de la página (la misma URL que PRO) y sustituye el contenido del widget; sin jQuery. */
((w, d) => {
	'use strict';
	if (w.digiLoopGrid) return;
	const busy = {};
	const swap = (el, href, push) => {
		const id = el.getAttribute('data-id'), doc = el.closest('[data-elementor-id]'), docId = doc ? doc.getAttribute('data-elementor-id') : '';
		if (!id || busy[id]) return;
		const nav = el.querySelector('[data-digi-ajax]'), scroll = nav && nav.getAttribute('data-digi-ajax') === 'scroll', msg = nav ? nav.getAttribute('data-digi-loaded') || '' : '';
		const had = el.contains(d.activeElement);
		if (push) {
			// Como PRO: los parámetros del enlace se suman a los de la página; sin `e-page-<id>`, se quita.
			try {
				const cur = new URL(location.href), to = new URL(href, location.href);
				to.searchParams.forEach((v, k) => cur.searchParams.set(k, v));
				if (!to.searchParams.has(`e-page-${id}`)) cur.searchParams.delete(`e-page-${id}`);
				history.pushState({ digiLoop: id }, '', cur.href);
			} catch (e) {}
		}
		busy[id] = 1;
		el.setAttribute('aria-busy', 'true');
		el.setAttribute('data-digi-loading', '');
		if (scroll) el.scrollIntoView({ behavior: w.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
		fetch(href, { credentials: 'same-origin' }).then((r) => (r.ok ? r.text() : Promise.reject(r))).then((html) => {
			const p = new DOMParser().parseFromString(html, 'text/html');
			const fresh = (docId && p.querySelector(`[data-elementor-id="${docId}"] [data-id="${id}"]`)) || p.querySelector(`[data-id="${id}"]`);
			if (!fresh) throw new Error('loop');
			el.replaceChildren(...[...fresh.childNodes].map((n) => d.importNode(n, true)));
			const page = (href.match(new RegExp(`e-page-${id}=(\\d+)`)) || href.match(/\/(\d+)\/?(?:[?#]|$)/) || href.match(/[?&]page=(\d+)/) || [0, 1])[1];
			const s = d.createElement('span');
			s.className = 'elementor-screen-only';
			s.setAttribute('role', 'status');
			el.append(s);
			setTimeout(() => { s.textContent = msg.replace('%d', page); }, 50);
			if (had) { el.setAttribute('tabindex', '-1'); el.focus({ preventScroll: scroll }); }
			const h = w.elementorFrontend && w.elementorFrontend.elementsHandler;
			if (h) { h.runReadyTrigger(el); el.querySelectorAll('.e-loop-item .elementor-element').forEach((x) => h.runReadyTrigger(x)); }
			d.dispatchEvent(new Event('elementor/lazyload/observe'));
			el.dispatchEvent(new CustomEvent('digi-loop-updated', { bubbles: true }));
		}).catch(() => { location.assign(href); }).finally(() => {
			delete busy[id];
			el.removeAttribute('aria-busy');
			el.removeAttribute('data-digi-loading');
		});
	};
	d.addEventListener('click', (e) => {
		const a = e.target.closest && e.target.closest('[data-digi-ajax] a.page-numbers');
		const el = a && a.closest('.elementor-widget-digitalisimo-loop-grid');
		if (!el || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		e.preventDefault();
		swap(el, a.href, true);
	});
	w.addEventListener('popstate', () => {
		d.querySelectorAll('.elementor-widget-digitalisimo-loop-grid').forEach((el) => {
			const id = el.getAttribute('data-id');
			if (el.querySelector('[data-digi-ajax]') && !d.querySelector(`.digi-tfilter[data-loop="${id}"]`)) swap(el, location.href, false);
		});
	});
	w.digiLoopGrid = { swap };
})(window, document);
