/* DIGITALÍSIMO Elements · Panel fuera de lienzo (C3). Ver widgets/class-nested-off-canvas.php. */
(w => {
'use strict';
const d = w.document, EV = 'elementor-pro/off-canvas/toggle-display-mode', NAME = 'digitalisimo-nested-off-canvas',
	FOC = 'a[href],area[href],button,input,select,textarea,iframe,summary,audio,video,[tabindex],[contenteditable]',
	all = new Map(), seen = new WeakSet(), stack = [],
	F = () => w.elementorFrontend, edit = () => !!(F() && F().isEditMode && F().isEditMode()),
	still = () => !!(w.matchMedia && w.matchMedia('(prefers-reduced-motion: reduce)').matches);
const pick = map => {
	const u = F() && F().utils && F().utils.controls, s = {};
	Object.keys(map || {}).forEach(k => { s[k ? 'a_' + k : 'a'] = map[k]; });
	try { if (u && F().breakpoints) return u.getResponsiveControlValue(s, 'a') || ''; } catch (e) {}
	return s.a || '';
};
const target = h => {
	try {
		const m = decodeURIComponent(h || '').match(/action=off_canvas:[a-z]+&settings=([^&#]+)/);
		return m ? String(JSON.parse(atob(m[1])).id) : '';
	} catch (e) { return ''; }
};
const expanded = (p, v) => d.querySelectorAll('[aria-controls="' + p.el.id + '"]').forEach(a => a.setAttribute('aria-expanded', v));
const focusables = p => [...p.content.querySelectorAll(FOC)].filter(n => !n.disabled && n.tabIndex > -1 && !n.closest('[inert]') && n.getClientRects().length);
const lock = () => d.body.classList.toggle('digi-noc-lock', stack.some(p => p.cfg.s));
const clean = p => {
	p.main.classList.remove('animated', 'reverse', p.a || 'x');
	p.el.classList.remove('digi-noc--closing');
	p.a = '';
};
const animate = (p, name, out) => {
	clean(p);
	if (!name || still()) return;
	p.a = name;
	p.main.classList.add('animated', name);
	if (out) {
		p.el.classList.add('digi-noc--closing');
		p.main.classList.add('reverse');
	}
};
const open = (p, ev) => {
	if (p.open) return;
	p.open = true;
	const t = ev && ev.target && ev.target.closest ? ev.target.closest('a,button,[tabindex]') : null, a = d.activeElement;
	p.back = t || (a !== d.body ? a : null);
	['inert', 'data-delay-child-handlers'].forEach(k => p.el.removeAttribute(k));
	p.el.setAttribute('aria-hidden', 'false');
	expanded(p, 'true');
	animate(p, pick(p.cfg.in));
	stack.push(p);
	lock();
	const h = F() && F().elementsHandler;
	if (!p.ran && h && h.runReadyTrigger) p.content.querySelectorAll('.elementor-element').forEach(n => { try { h.runReadyTrigger(n); } catch (e) {} });
	p.ran = true;
	if (!edit()) enter(p, 0);
};
// Foco dentro: un control con `transition` sigue invisible un instante (no admite el foco); se reintenta.
const enter = (p, n) => {
	if (!p.open || p.el.contains(d.activeElement)) return;
	const f = focusables(p)[0] || p.content;
	if (f === p.content) f.setAttribute('tabindex', '-1');
	f.focus();
	if (d.activeElement !== f && n < 4) setTimeout(() => enter(p, n + 1), 100);
};
const close = p => {
	if (!p.open) return;
	p.open = false;
	const inside = p.el.contains(d.activeElement);
	p.el.setAttribute('aria-hidden', 'true');
	p.el.setAttribute('inert', '');
	expanded(p, 'false');
	animate(p, pick(p.cfg.out), 1);
	stack.splice(stack.indexOf(p), 1);
	lock();
	if (inside && p.back && p.back.isConnected && !edit()) p.back.focus();
};
const init = el => {
	if (seen.has(el)) return;
	seen.add(el);
	let cfg = {};
	try { cfg = JSON.parse(el.getAttribute('data-digi-noc')) || {}; } catch (e) {}
	const p = { el, id: el.id.replace(/^off-canvas-/, ''), cfg, main: el.querySelector('.digi-noc__main'), content: el.querySelector('.digi-noc__content'), a: '' };
	if (!p.main || !p.content) return;
	const old = all.get(p.id);
	if (old && stack.indexOf(old) > -1) stack.splice(stack.indexOf(old), 1);
	all.set(p.id, p);
	d.querySelectorAll('a[href*="elementor-action"]').forEach(a => {
		if (target(a.getAttribute('href')) !== p.id) return;
		a.setAttribute('aria-controls', el.id);
		a.setAttribute('aria-expanded', 'false');
	});
	el.querySelector('.digi-noc__overlay').addEventListener('click', e => {
		e.preventDefault();
		if (cfg.o !== false && !edit()) close(p);
	});
	['animationend', 'animationcancel'].forEach(t => p.main.addEventListener(t, e => { if (e.target === p.main) clean(p); }));
	if ('false' === el.getAttribute('aria-hidden')) {
		p.open = p.ran = true;
		stack.push(p);
		lock();
	}
};
const scan = r => {
	if (!r || !r.querySelectorAll) return;
	if (r.matches && r.matches('.digi-noc')) init(r);
	r.querySelectorAll('.digi-noc').forEach(init);
};
w.addEventListener(EV, e => {
	const s = e.detail || {}, p = all.get(String(s.id));
	if (!p || !p.el.isConnected) return;
	const m = 'toggle' === s.displayMode ? (p.open ? 'close' : 'open') : s.displayMode;
	if ('open' === m) open(p, s.previousEvent);
	else if ('close' === m) close(p);
});
d.addEventListener('keydown', e => {
	const top = stack[stack.length - 1];
	if (!top) return;
	if ('Escape' === e.key) return stack.slice().reverse().forEach(p => { if (p.cfg.e !== false) close(p); });
	if ('Tab' !== e.key || edit() || !top.el.isConnected) return;
	const l = focusables(top), first = l[0], last = l[l.length - 1], a = d.activeElement;
	if (!first) return e.preventDefault();
	const to = !top.el.contains(a) ? (e.shiftKey ? last : first) : e.shiftKey && a === first ? last : !e.shiftKey && a === last ? first : null;
	if (to) {
		e.preventDefault();
		to.focus();
	}
});
const fire = s => w.dispatchEvent(new w.CustomEvent(EV, { detail: s }));
const actions = () => {
	const u = F() && F().utils && F().utils.urlActions;
	if (u) ['open', 'close', 'toggle'].forEach(m => { if (!(u.actions || {})['off_canvas:' + m]) u.addAction('off_canvas:' + m, fire); });
};
let booted = 0;
const boot = () => {
	const f = F();
	if (booted || !f || !f.hooks) return;
	booted = 1;
	f.hooks.addAction('frontend/element_ready/' + NAME + '.default', $ => scan($ && $[0] ? $[0] : $));
	if (f.on) f.on('components:init', actions);
	actions();
	scan(d);
};
if (F() && F().hooks) boot();
else w.addEventListener('elementor/frontend/init', boot);
d.addEventListener('DOMContentLoaded', () => F() || scan(d));
})(window);
