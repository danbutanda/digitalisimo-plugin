/* DIGITALÍSIMO Elements · slider anidado ligero (F7b, V.7). Desplazamiento y apilado: CSS. */
(() => {
const W = window, D = document, R = 'digi-nslider', moves = () => !(W.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches);
let io;
const init = root => {
	const cl = root && root.classList;
	if (!cl || cl.contains(R + '--ready')) return;
	const kid = n => root.querySelector(':scope>.' + R + '__' + n), attr = n => root.getAttribute('data-digi-' + n);
	const track = kid('track');
	if (!track) return;
	const slides = track.children, box = kid('dots'), dots = box ? box.children : [], pause = kid('pause'), prev = kid('prev'), next = kid('next');
	const slide = !/^(fade|scale)$/.test(attr('effect')), loop = attr('loop') !== null, delay = W.elementorFrontend?.isEditMode?.() ? 0 : +attr('autoplay') || 0;
	let cur = (+attr('start') || 1) - 1, timer = 0, wait = 0, stopped = !delay || !moves(), held = 0, focused = 0, seen = 1;
	const count = () => slides.length;
	const ctl = b => !!b && b.tagName === 'BUTTON' && (b.parentNode === root || b.parentNode === box);
	const paint = () => {
		const c = count(), label = box && box.getAttribute('data-digi-dot');
		if (cur >= c) cur = Math.max(0, c - 1);
		for (let k = 0; k < c; k++) {
			slides[k].toggleAttribute('inert', k !== cur);
			slides[k].classList.toggle(R + '__current', k === cur);
		}
		[].forEach.call(dots, (d, k) => {
			if (k === cur) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
			if (label) d.setAttribute('aria-label', label.replace('%d', k + 1));
		});
		if (prev) prev.setAttribute('aria-disabled', !loop && cur < 1);
		if (next) next.setAttribute('aria-disabled', !loop && cur >= c - 1);
	};
	const go = (i, auto, jump) => {
		const c = count();
		if (!c) return;
		cur = loop || auto ? (i % c + c) % c : Math.max(0, Math.min(c - 1, i));
		if (slide) track.scrollTo({ left: slides[cur].offsetLeft - slides[0].offsetLeft, behavior: jump || !moves() ? 'auto' : 'smooth' });
		else if (!jump) cl.add(R + '--anim');
		paint();
	};
	const run = () => {
		clearTimeout(timer);
		timer = delay && !stopped && !held && !focused && seen && !D.hidden && count() > 1 ? setTimeout(() => { go(cur + 1, 1); run(); }, delay) : 0;
		track.setAttribute('aria-live', timer ? 'off' : 'polite');
		cl.toggle(R + '--paused', stopped);
		if (pause) pause.setAttribute('aria-label', pause.getAttribute(stopped ? 'data-digi-play' : 'data-digi-pause'));
	};
	const on = (type, fn, el) => (el || root).addEventListener(type, fn);
	cl.add(R + '--ready');
	if (!slide) cl.add(R + '--stack');
	if (pause) pause.hidden = false;
	on('click', e => {
		const b = e.target.closest && e.target.closest('button');
		if (!ctl(b)) return;
		if (b === pause) stopped = !stopped;
		else if (b.getAttribute('aria-disabled') !== 'true') go(b === prev ? cur - 1 : b === next ? cur + 1 : [].indexOf.call(dots, b));
		run();
	});
	// Teclado: sólo con el foco en un control.
	on('keydown', e => {
		const b = e.target, k = e.key;
		let i;
		if (!ctl(b)) return;
		if (k === 'Home') i = 0;
		else if (k === 'End') i = count() - 1;
		else if (k === 'ArrowRight' || k === 'ArrowLeft') i = cur + ((k === 'ArrowRight') === (getComputedStyle(root).direction !== 'rtl') ? 1 : -1);
		else return;
		e.preventDefault();
		go(i);
		run();
		if (b.parentNode === box && dots[cur]) dots[cur].focus();
	});
	on('focusin', e => { focused = e.target === pause ? 0 : 1; run(); });
	on('focusout', e => { if (!root.contains(e.relatedTarget)) { focused = 0; run(); } });
	on(R + '-go', e => { go(+e.detail || 0); run(); });
	if (attr('hover') !== null) {
		on('pointerenter', e => { if (e.pointerType === 'mouse') { held = 1; run(); } });
		on('pointerleave', e => { if (e.pointerType === 'mouse') { held = 0; run(); } });
	}
	if (slide) on('scroll', () => {
		clearTimeout(wait);
		wait = setTimeout(() => {
			const w = track.clientWidth, i = w ? Math.round(Math.abs(track.scrollLeft) / w) : cur;
			if (i !== cur && i < count()) { cur = i; paint(); run(); }
		}, 120);
	}, track);
	root.digiNslider = { paint, see: v => { seen = v ? 1 : 0; run(); } };
	if (delay) {
		on('visibilitychange', run, D);
		if (W.IntersectionObserver) {
			io = io || new IntersectionObserver(list => list.forEach(en => en.target.digiNslider && en.target.digiNslider.see(en.isIntersecting)));
			io.observe(root);
		}
	}
	go(cur, 0, 1);
	run();
};
const scan = scope => [].forEach.call((scope && scope.querySelectorAll ? scope : D).querySelectorAll('.' + R), init);
const hook = () => W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-nested-slider.default', s => scan(s && s[0]));
if (W.elementorFrontend && W.elementorFrontend.hooks) hook(); else W.addEventListener('elementor/frontend/init', hook);
// Editor: diapositivas añadidas, quitadas o movidas.
W.addEventListener('elementor/nested-container/atomic-repeater', () => [].forEach.call(D.querySelectorAll('.' + R + '--ready'), r => r.digiNslider && r.digiNslider.paint()));
D.readyState === 'loading' ? D.addEventListener('DOMContentLoaded', () => scan()) : scan();
})();
