/* DIGITALÍSIMO Elements · Portafolio: barra de filtro con botones (`aria-pressed`), sin bibliotecas ni la de PRO.
   Oculta los elementos de otros términos (`data-digi-terms`) como `arrangeGrid()` de PRO: en 450 ms los que salen encogen
   y se desvanecen, los que se quedan se deslizan a su sitio y los que vuelven crecen desde el suyo (FLIP con
   `el.animate()`); al terminar, los que salen se ocultan. Con mampostería, como PRO, sin animación y la rejilla se
   recoloca con `digiLoop.masonry()`; con movimiento reducido, sin animación.
   Imagen: como `fitImage()` de PRO, con «Proporción del elemento» la imagen más apaisada que su caja se ajusta al alto
   (`digi-portfolio__img--fit-height`). El servidor ya la pone; aquí se repite al cargar la imagen y al cambiar la caja
   (también en el editor, donde la proporción cambia sin repintar). Delegado en el documento: vale también en el editor. */
((W, D) => {
	'use strict';
	if (W.digiPortfolio) return;
	W.digiPortfolio = 1;
	const T = 450, FIT = 'digi-portfolio__img--fit-height', SMALL = 'scale3d(.2,.2,1)';
	const still = () => W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const fit = (img) => {
		const p = img.parentNode;
		if (p && img.naturalWidth && p.offsetWidth) p.classList.toggle(FIT, img.naturalHeight / img.naturalWidth < p.offsetHeight / p.offsetWidth);
	};
	const ro = W.ResizeObserver && new W.ResizeObserver((list) => list.forEach((e) => { const i = e.target.querySelector('img'); if (i) fit(i); }));
	const watch = (root) => (root.querySelectorAll ? root : D).querySelectorAll('.digi-portfolio__img').forEach((p) => {
		const i = p.querySelector('img');
		if (p.digiFit || !i) return;
		p.digiFit = 1;
		if (ro) ro.observe(p);
		i.addEventListener('load', () => fit(i));
	});
	watch(D);
	const hook = () => { const h = (W.elementorFrontend || {}).hooks; if (h) h.addAction('frontend/element_ready/digitalisimo-portfolio.default', (e) => watch(e[0] || e)); };
	(W.elementorFrontend || {}).hooks ? hook() : W.addEventListener('elementor/frontend/init', hook);
	D.addEventListener('click', (e) => {
		const b = e.target.closest && e.target.closest('.digi-portfolio__filter');
		if (!b) return;
		const root = b.closest('.elementor-element');
		const box = root && root.querySelector('.digi-portfolio');
		if (!box) return;
		const f = b.getAttribute('data-filter');
		root.querySelectorAll('.digi-portfolio__filter').forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
		// Un filtro aún en curso termina antes de empezar el siguiente.
		if (box.digiDone) box.digiDone();
		const all = [].slice.call(box.children);
		const on = (it) => f === '__all' || (it.getAttribute('data-digi-terms') || '').split(' ').indexOf(f) > -1;
		const out = all.filter((it) => !it.hidden && !on(it)), back = all.filter((it) => it.hidden && on(it)), act = all.filter(on);
		back.forEach((it) => { it.hidden = false; });
		const masonry = box.hasAttribute('data-digi-masonry');
		if (masonry || still() || !box.animate) {
			out.forEach((it) => { it.hidden = true; });
			if (masonry && W.digiLoop) W.digiLoop.masonry(box);
			return;
		}
		// FLIP: dónde está cada uno ahora (los que salen aún ocupan su sitio, como en PRO) y dónde quedará.
		const rect = (l) => l.map((it) => it.getBoundingClientRect());
		const now = rect(act);
		out.forEach((it) => { it.hidden = true; });
		const end = rect(act);
		out.forEach((it) => { it.hidden = false; });
		const opt = { duration: T, easing: 'ease-in-out', fill: 'forwards' };
		const anims = out.map((it) => it.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: SMALL }], opt));
		act.forEach((it, i) => {
			const to = 'translate3d(' + (end[i].left - now[i].left) + 'px,' + (end[i].top - now[i].top) + 'px,0)';
			anims.push(it.animate(back.indexOf(it) > -1 ? [{ opacity: 0, transform: SMALL }, { opacity: 1, transform: to }] : [{ transform: 'none' }, { transform: to }], opt));
		});
		box.digiDone = () => {
			box.digiDone = null;
			out.forEach((it) => { it.hidden = true; });
			anims.forEach((a) => a.cancel());
		};
		const done = box.digiDone;
		Promise.all(anims.map((a) => a.finished)).then(() => { if (box.digiDone === done) done(); }, () => {});
	});
})(window, document);
