/* DIGITALÍSIMO Elements · listados (Entradas, Entradas del archivo, Portafolio y bucles): mampostería como la de PRO
   (cada elemento sube lo que le sobra a su columna, con la separación de `--digi-masonry-gap` en px como el número del
   ajuste en PRO; columnas leídas de la rejilla calculada), «Cargar más» (el enlace de PRO, con su contenido como botón
   para el teclado: Intro y Espacio) y desplazamiento infinito, sin bibliotecas: piden la página siguiente
   (`data-next-page`, del mismo sitio), añaden los elementos del mismo widget y lo anuncian en la región viva. API: `window.digiLoop = { masonry(box), scan(root) }`;
   evento `digi-loop-loaded` (con burbuja, `detail.items`) en el widget tras añadir elementos. */
((W, D) => {
	'use strict';
	if (W.digiLoop) return;
	const C = 'digi-loop--';
	const each = (l, f) => Array.prototype.forEach.call(l, f);
	const masonry = (box) => {
		const items = [].filter.call(box.children, (i) => !i.hidden && i.offsetParent !== null);
		each(items, (i) => { i.style.marginTop = ''; });
		const cs = getComputedStyle(box);
		const cols = cs.gridTemplateColumns.split(' ').filter(Boolean).length;
		box.classList.toggle(C + 'masonry', cols > 1);
		if (cols < 2) return;
		// Como PRO: el número del ajuste en px (0 sin valor); con una expresión (unidad «custom») no sube nada. Se mide
		// desde el borde exterior del contenedor, como `Masonry` del núcleo.
		const raw = cs.getPropertyValue('--digi-masonry-gap').trim();
		const gap = raw === '' ? 0 : /^-?(\d+\.?\d*|\.\d+)px$/.test(raw) ? parseFloat(raw) : NaN;
		if (gap !== gap) return;
		const top = box.getBoundingClientRect().top;
		const heights = [];
		each(items, (it, n) => {
			const col = n % cols;
			const h = it.getBoundingClientRect().height + gap;
			if (n < cols) { heights[col] = h; return; }
			it.style.marginTop = -(it.getBoundingClientRect().top - top - heights[col]) + 'px';
			heights[col] += h;
		});
	};
	const layout = (box) => {
		if (!box.hasAttribute('data-digi-masonry')) return;
		masonry(box);
		each(box.querySelectorAll('img'), (img) => { if (!img.complete) img.addEventListener('load', () => masonry(box), { once: true }); });
	};
	const ro = W.ResizeObserver ? new ResizeObserver((l) => l.forEach((e) => layout(e.target))) : null;
	const more = (anchor) => {
		if (anchor.digiLoop) return;
		anchor.digiLoop = 1;
		const root = anchor.closest('.elementor-element');
		const box = root && root.querySelector('[data-digi-items]');
		if (!box) return;
		const id = root.getAttribute('data-id');
		const btn = root.querySelector('.digi-loop__more .elementor-button');
		const ctl = btn && (btn.querySelector('[role=button]') || btn);
		const spin = root.querySelector('.digi-loop__spinner');
		const status = root.querySelector('.digi-loop__status');
		const infinite = anchor.getAttribute('data-digi-more') === 'infinite';
		let page = parseInt(anchor.getAttribute('data-page'), 10) || 0;
		const max = parseInt(anchor.getAttribute('data-max-page'), 10) || 0;
		let busy = false;
		const end = () => root.classList.add(C + 'end');
		if (!page || page >= max) return end();
		if (btn && spin) btn.append(spin);
		const load = (focus) => {
			if (busy || page >= max) return Promise.resolve();
			busy = true;
			root.classList.add(C + 'loading');
			if (ctl) ctl.setAttribute('aria-disabled', 'true');
			return fetch(anchor.getAttribute('data-next-page'), { credentials: 'same-origin' }).then((r) => (r.ok ? r.text() : Promise.reject(r))).then((html) => {
				const doc = new DOMParser().parseFromString(html, 'text/html');
				const other = doc.querySelector('[data-id="' + id + '"]');
				const list = other && other.querySelector('[data-digi-items]');
				const items = list ? [].map.call(list.children, (n) => D.importNode(n, true)) : [];
				items.forEach((n) => box.append(n));
				page++;
				const next = other && other.querySelector('.digi-loop__anchor');
				anchor.setAttribute('data-page', page);
				if (next) anchor.setAttribute('data-next-page', next.getAttribute('data-next-page'));
				if (status) status.textContent = (status.getAttribute('data-digi-loaded') || '%d').replace('%d', items.length);
				if (page >= max || !items.length) { page = max; end(); }
				layout(box);
				root.dispatchEvent(new CustomEvent('digi-loop-loaded', { bubbles: true, detail: { items } }));
				const first = focus && items[0] && items[0].querySelector('a[href]:not([tabindex="-1"]),button');
				if (first) first.focus({ preventScroll: true });
			}).catch(() => {}).finally(() => {
				busy = false;
				root.classList.remove(C + 'loading');
				if (ctl) ctl.removeAttribute('aria-disabled');
			});
		};
		if (infinite) {
			if (W.elementorFrontend && W.elementorFrontend.isEditMode && W.elementorFrontend.isEditMode()) return;
			if (!W.IntersectionObserver) return;
			const io = new IntersectionObserver((l) => {
				if (!l.some((e) => e.isIntersecting) || busy) return;
				io.unobserve(anchor);
				load(false).then(() => { if (page < max) io.observe(anchor); });
			});
			io.observe(anchor);
		} else if (btn) {
			btn.addEventListener('click', (e) => { e.preventDefault(); load(true); });
			ctl.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); load(true); } });
		}
	};
	const scan = (root) => {
		const r = root && root.querySelectorAll ? root : D;
		each(r.querySelectorAll('[data-digi-masonry]'), (box) => {
			layout(box);
			if (ro && !box.digiLoopRo) { box.digiLoopRo = 1; ro.observe(box); }
		});
		each(r.querySelectorAll('.digi-loop__anchor'), more);
	};
	W.digiLoop = { masonry, scan };
	const hook = () => W.elementorFrontend.hooks.addAction('frontend/element_ready/global', (s) => scan(s && s[0]));
	if (W.elementorFrontend && W.elementorFrontend.hooks) hook(); else W.addEventListener('elementor/frontend/init', hook);
	if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', () => scan()); else scan();
})(window, document);
