/* DIGITALÍSIMO Elements · Filtro de taxonomías (C5), editor: rellena «Loop Grid» con los Loop Grid de este documento
   (propios y de PRO) y «Taxonomía» con las del tipo de contenido del bucle elegido, como el editor de PRO. No cambia el
   valor guardado. Al pulsar un término en la vista previa, como PRO (`prepareLoopUpdateRequestData()`), repinta la
   Cuadrícula con su modelo del editor por la REST de PRO (`refresh-loop`): los términos de todos los filtros de ese
   bucle, el nonce de la configuración del editor, y sustituye su `.elementor-widget-container`. */
((w) => {
	'use strict';
	const NAME = 'digitalisimo-taxonomy-filter', cache = {};
	const page = () => { const e = w.elementor; return e && e.getPanelView && e.getPanelView().getCurrentPageView(); };
	const fill = (p, name, opts, missing) => {
		const m = p && p.collection && p.collection.findWhere({ name });
		if (!m) return;
		const v = p.model && p.model.getSetting ? p.model.getSetting(name) : '';
		if (v && !opts[v]) opts[v] = missing;
		m.set('options', opts);
		const c = p.children && p.children.findByModelCid(m.cid);
		if (c) c.render();
	};
	const loops = () => {
		const e = w.elementor, doc = e.$previewContents && e.$previewContents[0], o = { '': 'Elige un widget' };
		if (doc) doc.querySelectorAll(`[data-elementor-id="${e.config.document.id}"] :is(.elementor-widget-loop-grid,.elementor-widget-digitalisimo-loop-grid)`).forEach((el, i) => { o[el.getAttribute('data-id')] = `Loop Grid ${i + 1}`; });
		return o;
	};
	// Tipo de contenido del bucle, con las mismas reglas que el editor de PRO.
	const postType = (id) => {
		const c = id && w.elementor.getContainer && w.elementor.getContainer(id), s = c && c.settings;
		if (!s) return 'post';
		const skin = s.get('_skin') || 'post';
		if (skin === 'post_taxonomy') return 'post';
		if (skin !== 'post') return 'product';
		const t = s.get('post_query_post_type');
		if (t === 'current_query') return (((w.elementorPro || {}).config || {}).loopFilter || {}).mainQueryPostType || 'post';
		return !t || t === 'by_id' || t === 'related' ? 'post' : t;
	};
	const taxonomies = (p) => {
		const id = p.model.getSetting('selected_element');
		if (!id) return fill(p, 'taxonomy', { '': 'Elige una taxonomía' }, '');
		const t = postType(id), done = (data) => {
			const keys = Object.keys(data || {});
			fill(p, 'taxonomy', keys.length ? Object.assign({ '': 'Elige una taxonomía' }, data) : { '': 'No hay taxonomías' }, 'Taxonomía que ya no existe');
		};
		if (cache[t]) return done(cache[t]);
		const c = w.elementorCommon && w.elementorCommon.config, rest = c && c.urls && c.urls.rest;
		const nonce = (w.wpApiSettings && w.wpApiSettings.nonce) || (w.elementorWebCliConfig && w.elementorWebCliConfig.nonce) || '';
		if (!rest) return;
		fetch(`${rest}elementor-pro/v1/get-post-type-taxonomies`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify({ post_type: t }) })
			.then((r) => (r.ok ? r.json() : {})).catch(() => ({}))
			.then((r) => { const data = (r && r.data) || r || {}; cache[t] = data; if (page() === p) done(data); });
	};
	const rest = () => { const c = w.elementorCommon && w.elementorCommon.config; return (c && c.urls && c.urls.rest) || ''; };
	const nonce = () => ((((w.elementorPro || {}).config || {}).loopFilter) || {}).nonce || (w.wpApiSettings && w.wpApiSettings.nonce) || (w.elementorWebCliConfig && w.elementorWebCliConfig.nonce) || '';
	w.digiTaxonomyFilterEdit = (s) => {
		const id = s.dataset.loop, d = s.ownerDocument, pw = d.defaultView, doc = s.closest('[data-elementor-id]');
		const live = (doc && doc.querySelector(`.elementor-element-${id}`)) || d.querySelector(`.elementor-element-${id}`);
		const box = live && live.querySelector('.elementor-widget-container'), c = w.$e && w.$e.components.get('document').utils.findContainerById(id);
		if (!box || !c || !rest()) return;
		const taxonomy = {};
		d.querySelectorAll(`.digi-tfilter[data-loop="${id}"]`).forEach((f) => {
			const terms = [...f.querySelectorAll('.digi-tfilter__item[aria-pressed="true"]')].map((b) => b.dataset.filter).filter((t) => t !== '__all');
			if (terms.length) taxonomy[f.dataset.taxonomy] = Object.assign({ terms }, f.dataset.join !== 'DISABLED' ? { logicalJoin: f.dataset.join } : {});
		});
		const body = { post_id: +(live.closest('[data-elementor-id]') || live).getAttribute('data-elementor-id') || w.elementor.config.document.id, widget_id: id, widget_filters: Object.keys(taxonomy).length ? { taxonomy } : {}, widget_model: c.model.toJSON({ remove: ['default', 'editSettings', 'defaultEditSettings'] }), is_edit_mode: true };
		if (s.dataset.baseUrl) body.pagination_base_url = s.dataset.baseUrl;
		live.classList.add('e-loading');
		fetch(`${rest()}elementor-pro/v1/refresh-loop`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce() }, body: JSON.stringify(body) })
			.then((r) => (r.ok ? r.json() : {})).catch(() => ({}))
			.then((r) => {
				live.classList.remove('e-loading');
				if (!r || typeof r.data !== 'string') return;
				const t = d.createElement('div');
				t.innerHTML = r.data.trim();
				box.replaceWith(t.firstElementChild || Object.assign(d.createElement('div'), { className: 'elementor-widget-container' }));
				const h = pw.elementorFrontend && pw.elementorFrontend.elementsHandler;
				if (h) { live.querySelectorAll('.e-loop-item .elementor-element').forEach((el) => h.runReadyTrigger(el)); h.runReadyTrigger(live); }
			});
	};
	const run = () => {
		const p = page();
		if (!p || !p.model || p.model.get('widgetType') !== NAME) return;
		fill(p, 'selected_element', loops(), 'Loop Grid que ya no está en este documento');
		taxonomies(p);
	};
	const bind = () => {
		const ch = w.elementor.channels.editor;
		ch.on(`editor:widget:${NAME}:section_taxonomy_filter:activated`, run);
		ch.on(`change:${NAME}`, (view) => { if (view && view.model && view.model.get('name') === 'selected_element') { const p = page(); if (p) taxonomies(p); } });
	};
	if (w.elementor && w.elementor.channels && w.elementor.channels.editor) bind(); else w.addEventListener('elementor/init', bind, { once: true });
})(window);
