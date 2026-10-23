/* DIGITALÍSIMO Elements · vista rápida (F4-Woo, V.5). Puente a WooCommerce con window.jQuery, sin declararlo. */
(() => {
const w = window, d = document, C = w.digitalisimoQuickView || {}, nonce = d.currentScript && d.currentScript.nonce || '', P = Promise;
const VARS = ['width', 'bg', 'color', 'padding', 'radius'].map(v => '--digi-qv-' + v), FB = 'fallback', seen = [];
let dlg, head, body, stat, opener, home, ctl, timer;
const mk = (t, c) => { const n = d.createElement(t); if (c) n.className = c; return n; };
const at = (n, k) => n.getAttribute(k) || '';
const add = h => d.body.insertAdjacentHTML('beforeend', h);
const vReady = () => !!(w.wc_add_to_cart_variation_params || (C.variation && C.variation.params));
const has = (t, h) => !!(d.getElementById(h + '-' + t) || (t === 'css' && d.getElementById(h + '-inline-css')));
const target = () => dlg.querySelector('#digi-qv-title') || head;
const shut = () => { if (dlg && dlg.open) dlg.close(); };
const busy = on => { body.setAttribute('aria-busy', String(on)); stat.textContent = on ? 'Cargando…' : ''; };

function build() {
	dlg = mk('dialog', 'digi-qv');
	dlg.setAttribute('aria-labelledby', 'digi-qv-title');
	dlg.innerHTML = '<button type="button" class="digi-qv__close">Cerrar<span class="screen-reader-text"> la vista rápida</span></button><h2 class="screen-reader-text" tabindex="-1"></h2><div class="digi-qv__body woocommerce"></div><p class="digi-qv__status screen-reader-text" role="status"></p>';
	[, head, body, stat] = dlg.children;
	dlg.children[0].addEventListener('click', shut);
	dlg.addEventListener('click', e => {
		if (e.target !== dlg) return thumb(e);
		const r = dlg.getBoundingClientRect();
		if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) shut();
	});
	dlg.addEventListener('close', cleanup);
	d.body.appendChild(dlg);
}

function open(link) {
	if (!dlg) build();
	if (ctl) { ctl.abort(); ctl = null; }
	home = opener.closest('.e-loop-item, .elementor-widget, .products');
	const cs = w.getComputedStyle(link);
	VARS.forEach(v => { const val = cs.getPropertyValue(v).trim(); if (val) dlg.style.setProperty(v, val); else dlg.style.removeProperty(v); });
	Object.assign(head, { id: 'digi-qv-title', hidden: false, textContent: link.textContent.trim() });
	body.textContent = '';
	busy(true);
	if (!dlg.open) { d.documentElement.classList.add('digi-qv-open'); dlg.showModal(); }
	head.focus();
	const c = ctl = new AbortController(), q = new URLSearchParams({ post_id: at(link, 'data-post'), widget_id: at(link, 'data-widget'), product: at(link, 'data-digi-qv'), return: w.location.href.split('#')[0] });
	timer = setTimeout(() => c.abort(), 10000);
	w.fetch(C.rest + (C.rest.indexOf('?') < 0 ? '?' : '&') + q, { signal: c.signal })
		.then(r => (r.ok ? r.json() : P.reject(r)))
		.then(o => {
			if (c !== ctl) return;
			if (!o || o.fallback || (o.variable && o.mode !== 'template' && !vReady())) throw FB;
			return prepare(o).then(() => new P(ok => (w.jQuery ? w.jQuery(() => ok()) : ok()))).then(() => { if (c === ctl) show(o); });
		})
		.catch(err => {
			clearTimeout(timer);
			if (c !== ctl) return;
			ctl = null;
			if (err === FB) { shut(); w.location.assign(link.href); return; }
			body.innerHTML = '<p class="digi-qv__error">No se pudo cargar la vista rápida. <a href="">Abrir la ficha del producto</a></p>';
			const a = body.querySelector('a');
			a.href = link.href;
			busy(false);
			a.focus();
		});
}

function prepare(o) {
	const A = o.assets || {};
	let js = (A.scripts || []).slice(), params = A.params || {};
	if (o.variable && o.mode !== 'template' && C.variation) { js = js.concat(C.variation.scripts || []); params = Object.assign({}, C.variation.params || {}, params); }
	if (js.some(s => s.late === false && !has('js', s.handle))) return P.reject(FB);
	Object.keys(params).forEach(k => { if (w[k] === undefined) w[k] = params[k]; });
	if (o.templates && !d.getElementById('tmpl-variation-template')) add(o.templates);
	if (o.gallery_defaults) (w.wc_variation_gallery_defaults = w.wc_variation_gallery_defaults || {})[o.product] = o.gallery_defaults;
	if (o.footer_html && seen.indexOf(o.footer_html) < 0) { seen.push(o.footer_html); add(o.footer_html); }
	return P.all((A.styles || []).map(css)).then(() => js.reduce((p, s) => p.then(() => script(s)), P.resolve()));
}

const css = s => (has('css', s.handle) ? P.resolve() : new P(ok => {
	if (s.src) {
		const l = Object.assign(mk('link'), { rel: 'stylesheet', id: s.handle + '-css', href: s.src, media: s.media || 'all' });
		l.onload = l.onerror = () => ok();
		d.head.appendChild(l);
		setTimeout(ok, 3000);
	} else ok();
	if (s.after) d.head.appendChild(Object.assign(mk('style'), { id: s.handle + '-inline-css', textContent: s.after }));
}));

const tag = o => { const t = Object.assign(mk('script'), o); if (nonce) t.nonce = nonce; return t; };
function inline(code, id) {
	w.digitalisimoQuickViewInline = 0;
	d.body.appendChild(tag({ id, textContent: code + '\n;window.digitalisimoQuickViewInline=1;' }));
	return w.digitalisimoQuickViewInline === 1;
}

function script(s) {
	if (has('js', s.handle)) return P.resolve();
	if (s.before && !inline(s.before, s.handle + '-js-before')) return P.reject(FB);
	return new P((ok, no) => {
		const t = tag({ id: s.handle + '-js', src: s.src, async: false });
		t.onload = () => (s.after && !inline(s.after, s.handle + '-js-after') ? no(FB) : ok());
		t.onerror = () => no(FB);
		d.body.appendChild(t);
	});
}

function show(o) {
	clearTimeout(timer);
	ctl = null;
	body.innerHTML = o.html || '';
	head.textContent = o.title || head.textContent;
	const vis = body.querySelector('[data-digi-qv-title]');
	if (vis) { head.removeAttribute('id'); head.hidden = true; vis.id = 'digi-qv-title'; vis.tabIndex = -1; }
	busy(false);
	d.dispatchEvent(new CustomEvent('digitalisimo:content', { detail: { root: body } }));
	const $ = w.jQuery, fn = $ && $.fn || {}, S = w.wc_single_product_params, F = w.elementorFrontend;
	if (fn.wc_variation_form) $(body).find('.variations_form').each(function () { $(this).wc_variation_form(); });
	if (fn.wc_product_gallery && S) $(body).find('.woocommerce-product-gallery').each(function () { const g = $(this); if (!g.data('product_gallery')) g.trigger('wc-product-gallery-before-init', [this, S]).wc_product_gallery(S).trigger('wc-product-gallery-after-init', [this, S]); });
	if (o.mode === 'template' && F && F.elementsHandler) body.querySelectorAll('.elementor-element').forEach(n => F.elementsHandler.runReadyTrigger(n));
	target().focus();
}

function thumb(e) {
	const b = e.target.closest && e.target.closest('.digi-qv__thumb'), img = b && body.querySelector('.digi-qv__media .wp-post-image');
	if (!img) return;
	['src', 'srcset', 'width', 'height', 'alt'].forEach(k => { const v = at(b, 'data-' + k); if ((v && v !== '0') || k === 'alt') img.setAttribute(k, v); else if (k !== 'src') img.removeAttribute(k); });
	body.querySelectorAll('.digi-qv__thumb').forEach(n => n.setAttribute('aria-pressed', String(n === b)));
}

function cleanup() {
	clearTimeout(timer);
	if (ctl) { const c = ctl; ctl = null; c.abort(); }
	body.textContent = '';
	d.documentElement.classList.remove('digi-qv-open');
	const back = [opener, home].find(n => n && d.contains(n));
	opener = null;
	if (!back) return;
	if (back === home && !back.hasAttribute('tabindex')) back.tabIndex = -1;
	back.focus();
}

d.addEventListener('focusin', e => { if (dlg && dlg.open && !dlg.contains(e.target)) target().focus(); });

d.addEventListener('click', e => {
	const link = e.target.closest && e.target.closest('a[data-digi-qv]');
	if (!link || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
	if (!C.rest || typeof w.HTMLDialogElement !== 'function' || d.body.classList.contains('elementor-editor-active')) return;
	if (link.hasAttribute('data-variable') && !link.hasAttribute('data-digi-qv-template') && !vReady()) return;
	e.preventDefault();
	if (!(dlg && dlg.open && opener)) opener = link;
	open(link);
});
})();
