/* DIGITALÍSIMO Elements · Búsqueda (C3). Ver widgets/class-search.php y class-search-rest.php. */
(w => {
'use strict';
const d = w.document, seen = new WeakSet(), F = () => w.elementorFrontend, edit = () => !!F()?.isEditMode?.(),
	FOC = 'a[href],button,input,select,textarea,[tabindex]', W = el => (el ? el.getBoundingClientRect().width : 0);
const init = root => {
	if (seen.has(root)) return;
	seen.add(root);
	const q = s => root.querySelector(s), input = q('.digi-search__input'), clear = q('.digi-search__clear'),
		res = q('.digi-search__results'), box = res && res.querySelector('.digi-search__box'), loader = q('.digi-search__loader'),
		status = q('[role=status]'), el = root.closest('.elementor-element');
	if (!input) return;
	let cfg = {};
	try { cfg = JSON.parse(root.getAttribute('data-digi-search')) || {}; } catch (e) {}
	const t = cfg.t || {};
	let timer, req = 0;
	const measure = () => {
		if (!root.getClientRects().length) return;
		const s = root.style, lw = W(q('.digi-search__label>:is(i,svg)')), cw = W(clear && clear.querySelector('i,svg'));
		s.setProperty('--dgs-iw', lw ? 'calc(' + lw + 'px + var(--dgs-gap,4px))' : '0px');
		s.setProperty('--dgs-cw', cw ? 'calc(' + cw + 'px + var(--dgs-gap,4px))' : '0px');
		if (cw) s.setProperty('--dgs-xw', cw + 'px');
	};
	const show = v => { if (res) res.hidden = !v; };
	const say = text => { if (status) status.textContent = text || ''; };
	const empty = () => {
		req++;
		if (box) box.replaceChildren();
		show(false);
		say('');
	};
	const others = () => d.querySelectorAll('.digi-search__results').forEach(r => { if (r !== res) r.hidden = true; });
	const items = () => [...box.querySelectorAll(FOC)].filter(n => n.tabIndex > -1 && !n.disabled && n.getClientRects().length);
	const page = n => {
		if (!cfg.rest || !box || !el) return;
		const id = ++req, doc = root.closest('[data-elementor-id]'), headers = { 'Content-Type': 'application/json' }, body = {
			post_id: doc ? +doc.getAttribute('data-elementor-id') : 0,
			widget_id: el.getAttribute('data-id'),
			search_term: input.value,
			page_number: n,
			breakpoint: F()?.getCurrentDeviceMode?.() || 'desktop'
		};
		if (edit()) {
			const top = w.top;
			try { body.widget_model = top.$e.components.get('document').utils.findContainerById(body.widget_id).model.toJSON({ remove: ['default', 'editSettings', 'defaultEditSettings'] }); } catch (e) {}
			const nonce = top.wpApiSettings?.nonce || top.elementorPro?.config?.eSearch?.nonce || top.wp?.apiFetch?.nonceMiddleware?.nonce;
			if (nonce) headers['X-WP-Nonce'] = nonce;
		}
		show(true);
		box.hidden = !!loader;
		if (loader) loader.hidden = false;
		fetch(cfg.rest, { method: 'POST', credentials: 'same-origin', headers, body: JSON.stringify(body) })
			.then(r => (r.ok ? r.json() : null)).catch(() => null).then(r => {
				if (id !== req) return;
				if (loader) loader.hidden = true;
				box.hidden = false;
				if (!r || !r.data) {
					box.replaceChildren();
					show(false);
					return say(r ? t.none : '');
				}
				const list = d.createElement('div'), nav = d.createElement('nav');
				list.className = 'digi-search__list';
				list.innerHTML = r.data;
				nav.className = 'digi-search__pages';
				nav.setAttribute('aria-label', t.pages || '');
				nav.innerHTML = r.pagination || '';
				box.replaceChildren(list, nav);
				const count = box.querySelectorAll('.e-loop-item').length;
				res.classList.toggle('digi-search--none', !count);
				say(!count ? list.textContent.trim() || t.none : 1 === count ? t.one : (t.found || '%d').replace('%d', count));
				others();
				const h = F()?.elementsHandler;
				if (h) box.querySelectorAll('.e-loop-item .elementor-element').forEach(e => h.runReadyTrigger(e));
				d.dispatchEvent(new Event('elementor/lazyload/observe'));
			});
	};
	const toggle = () => clear && clear.classList.toggle('digi-search__clear--off', !input.value);
	input.addEventListener('input', () => {
		toggle();
		clearTimeout(timer);
		timer = setTimeout(() => {
			if (!input.value) return empty();
			if (input.value.length >= (cfg.min || 3)) page(1);
		}, 300);
	});
	input.addEventListener('focus', () => {
		measure();
		if (box && box.children.length) {
			show(true);
			others();
		}
	});
	input.addEventListener('keydown', e => {
		const k = e.key;
		if ('Enter' === k) return empty();
		if (('ArrowDown' === k || 'ArrowUp' === k) && res && !res.hidden) {
			const l = items();
			if (!l.length) return;
			e.preventDefault();
			l['ArrowDown' === k ? 0 : l.length - 1].focus();
		}
	});
	if (clear) clear.addEventListener('click', e => {
		e.preventDefault();
		input.value = '';
		empty();
		toggle();
		input.focus();
	});
	if (res) {
		res.addEventListener('keydown', e => {
			if ('ArrowDown' !== e.key && 'ArrowUp' !== e.key) return;
			const l = items(), i = l.indexOf(d.activeElement) + ('ArrowDown' === e.key ? 1 : -1);
			e.preventDefault();
			(i < 0 || i >= l.length ? input : l[i]).focus();
		});
		res.addEventListener('click', e => {
			const a = e.target.closest('a.page-numbers');
			if (!a || a.classList.contains('current')) return;
			e.preventDefault();
			page(+new URL(a.href, w.location.href).searchParams.get('e-search-page') || 1);
		});
		d.addEventListener('click', e => { if (!res.hidden && !root.contains(e.target)) show(false); });
		d.addEventListener('keydown', e => {
			if ('Escape' !== e.key || res.hidden) return;
			if (res.contains(d.activeElement)) input.focus();
			show(false);
		});
	}
	q('form').addEventListener('submit', e => { if (edit()) e.preventDefault(); });
	measure();
	d.fonts?.ready.then(measure);
};
const scan = r => {
	if (!r || !r.querySelectorAll) return;
	if (r.matches('.digi-search')) init(r);
	r.querySelectorAll('.digi-search').forEach(init);
};
const boot = () => F()?.hooks?.addAction('frontend/element_ready/digitalisimo-search.default', $ => scan($ && $[0] ? $[0] : $));
if (F()?.hooks) boot();
else w.addEventListener('elementor/frontend/init', boot);
if ('loading' === d.readyState) d.addEventListener('DOMContentLoaded', () => scan(d));
else scan(d);
})(window);
