/* DIGITALÍSIMO Elements · Filtro de taxonomías (C5), editor: rellena «Loop Grid» con los Loop Grid de este documento
   (propios y de PRO) y «Taxonomía» con las del tipo de contenido del bucle elegido, como el editor de PRO. No cambia el
   valor guardado. */
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
