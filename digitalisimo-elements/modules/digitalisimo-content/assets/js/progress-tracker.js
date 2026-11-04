/* DIGITALÍSIMO Elements · Indicador de progreso: barra o círculo según el desplazamiento de la página, del contenido de la
   entrada o de un selector, con las fórmulas del widget de PRO; una sola escucha para todos, agrupada por fotograma. */
(() => {
	const W = window, D = document, R = 'digi-progress-tracker', list = [];
	let queued = 0;
	const clamp = v => Math.max(0, Math.min(v, 1)) || 0;
	// Alto del contenido (sin relleno ni borde), como `.height()`.
	const inner = el => { const cs = getComputedStyle(el); return el.getBoundingClientRect().height - ['paddingTop', 'paddingBottom', 'borderTopWidth', 'borderBottomWidth'].reduce((s, k) => s + (parseFloat(cs[k]) || 0), 0); };
	const progress = t => {
		const H = W.innerHeight, b = D.body, mode = t.getAttribute('data-digi-relative');
		let el = b, start = -H;
		if (mode === 'post_content' || mode === 'selector') {
			start = 0;
			try { el = D.querySelector(mode === 'selector' ? t.getAttribute('data-digi-selector') : '.elementor-widget-theme-post-content'); } catch (e) { el = null; }
			if (!el) return 0;
		} else if (b.scrollHeight - b.clientHeight > 1 && /auto|scroll/.test(getComputedStyle(b).overflowY)) {
			// «Scroll Snap»: el desplazamiento es el del propio body.
			return Math.round(clamp(b.scrollTop / (b.scrollHeight - b.clientHeight)) * 1e4) / 100;
		}
		const top = el.getBoundingClientRect().top, y1 = top - H, end = top + start + inner(el) - y1 - H;
		return end > 0 ? Math.round(clamp((start - y1) / end) * 1e4) / 100 : 0;
	};
	const paint = t => {
		const p = progress(t), bar = t.querySelector('.' + R + '__progress'), txt = t.querySelector('.' + R + '__percentage'), pct = t.hasAttribute('data-digi-percentage');
		if (!bar) return;
		if (t.classList.contains(R + '--circular')) {
			const on = p > 0, c = bar.r.baseVal.value * 2 * Math.PI, off = c - p / 100 * c;
			t.classList.toggle(R + '--on', on);
			bar.style.strokeDasharray = c + ' ' + c;
			if (!on) { bar.style.strokeDashoffset = c; return; }
			bar.style.strokeDashoffset = t.getAttribute('data-digi-direction') === 'ltr' ? -off : off;
			if (pct && txt) txt.textContent = Math.round(p) + '%';
			return;
		}
		t.classList.toggle(R + '--on', p >= 1);
		if (p < 1) return;
		bar.style.width = p + '%';
		if (!pct || !txt) return;
		const fits = bar.getBoundingClientRect().width > txt.getBoundingClientRect().width * 1.5;
		if (fits) txt.textContent = Math.round(p) + '%';
		txt.classList.toggle(R + '__percentage--off', !fits);
	};
	const update = () => { queued = 0; for (let i = list.length - 1; i >= 0; i--) list[i].isConnected ? paint(list[i]) : list.splice(i, 1); };
	const request = () => { if (!queued) queued = W.requestAnimationFrame(update); };
	let bound = false;
	const init = t => {
		if (list.includes(t)) return;
		list.push(t);
		if (W.ResizeObserver && t.classList.contains(R + '--circular')) new ResizeObserver(request).observe(t);
		paint(t);
		if (bound) return;
		bound = true;
		W.addEventListener('scroll', request, { passive: true });
		D.body.addEventListener('scroll', request, { passive: true });
		W.addEventListener('resize', request);
	};
	const scan = s => (s && s.querySelectorAll ? s : D).querySelectorAll('.' + R).forEach(init);
	const hook = () => W.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-progress-tracker.default', s => scan(s && s[0]));
	W.elementorFrontend && W.elementorFrontend.hooks ? hook() : W.addEventListener('elementor/frontend/init', hook);
	D.readyState === 'loading' ? D.addEventListener('DOMContentLoaded', () => scan()) : scan();
})();
