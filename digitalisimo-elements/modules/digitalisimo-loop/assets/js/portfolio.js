/* DIGITALÍSIMO Elements · Portafolio: barra de filtro con botones (`aria-pressed`), sin bibliotecas ni la de PRO.
   Oculta los elementos de otros términos (`data-digi-terms`), deja entrar los que vuelven y, con mampostería, recoloca
   la rejilla con `digiLoop.masonry()`. Delegado en el documento: vale también en el editor. */
((W, D) => {
	'use strict';
	if (W.digiPortfolio) return;
	W.digiPortfolio = 1;
	const motion = !(W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches);
	D.addEventListener('click', (e) => {
		const b = e.target.closest && e.target.closest('.digi-portfolio__filter');
		if (!b) return;
		const root = b.closest('.elementor-element');
		const box = root && root.querySelector('.digi-portfolio');
		if (!box) return;
		const f = b.getAttribute('data-filter');
		root.querySelectorAll('.digi-portfolio__filter').forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
		[].forEach.call(box.children, (it) => {
			const on = f === '__all' || (it.getAttribute('data-digi-terms') || '').split(' ').indexOf(f) > -1;
			if (on && it.hidden && motion) {
				it.classList.add('digi-portfolio--in');
				it.addEventListener('animationend', () => it.classList.remove('digi-portfolio--in'), { once: true });
			}
			it.hidden = !on;
		});
		if (box.hasAttribute('data-digi-masonry') && W.digiLoop) W.digiLoop.masonry(box);
	});
})(window, document);
