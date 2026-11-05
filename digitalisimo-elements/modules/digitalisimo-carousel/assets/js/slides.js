/* DIGITALÍSIMO Elements · Diapositivas: animación del contenido al cambiar de diapositiva, como en PRO (se oculta al
   empezar la transición y entra con su animación al terminarla; la diapositiva inicial no se anima). */
(() => {
	const W = window, D = document;
	if (W.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	D.addEventListener('digi-crs-change', e => {
		const r = e.target, c = r.classList;
		if (!c || !c.contains('digi-slides') || !r.hasAttribute('data-digi-animation')) return;
		clearTimeout(r.digiSlidesOut);
		c.add('digi-slides--out');
		r.digiSlidesOut = setTimeout(() => c.remove('digi-slides--out'), parseFloat(getComputedStyle(r).getPropertyValue('--digi-crs-speed')) || 500);
	});
})();
