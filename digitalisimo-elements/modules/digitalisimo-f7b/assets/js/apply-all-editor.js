/* DIGITALÍSIMO Elements · «Aplicar a todos los de este tipo» en el editor (F7b, frente H). */
(function (w) {
	'use strict';
	const cfg = w.digitalisimoApplyAll;
	if (!cfg) return;
	const T = cfg.i18n || {}, KEY = cfg.key || 'digitalisimo-apply-all', PASTE = 'document/elements/paste-style';
	const E = () => w.elementor;
	const esc = (s) => String(s).replace(/[&<>"']/g, (c) => '&#' + c.charCodeAt(0) + ';');
	const fill = (s, k, v) => String(s || '').replace(k, () => v);
	const type = (c) => c && c.model && 'widget' === c.model.get('elType') ? c.model.get('widgetType') : '';
	const atomic = (m) => { const h = E().helpers; return !!(h && h.isAtomicWidget && h.isAtomicWidget(m)); };
	// Como el núcleo (editor.js:9703): sin el permiso «design» no se ofrece.
	const can = () => 'function' === typeof E().userCan && !!E().userCan('design');
	function name(c) {
		const e = E(), data = e.getElementData ? e.getElementData(c.model) : null, cache = e.widgetsCache || {};
		return (data && data.title) || (cache[type(c)] || {}).title || type(c);
	}
	// Widgets del mismo tipo en el documento actual, sin el origen ni los globales (modelo con templateID).
	function targets(src) {
		const out = { list: [], globals: 0 }, t = type(src), doc = E().documents && E().documents.getCurrent();
		if (!t || !doc || !doc.container) return out;
		doc.container.children.forEachRecursive((c) => {
			if (!c || !c.model || c.id === src.id || type(c) !== t) return;
			if (c.model.get('templateID')) out.globals++; else out.list.push(c);
		});
		return out;
	}
	function apply(src) {
		const t = targets(src), store = w.elementorCommon.storage, urls = (w.elementorCommon.config || {}).urls || {};
		if (!t.list.length) return 0;
		// El mismo formato que «Copiar» (document/elements/copy), en una clave propia: el portapapeles no cambia.
		store.set(KEY, { type: 'elementor', siteurl: urls.rest, elements: [src.model.toJSON({ copyHtmlCache: true })] });
		try {
			w.$e.run(PASTE, { containers: t.list, storageKey: KEY });
		} finally {
			store.set(KEY, undefined);
		}
		if (E().notifications) E().notifications.showToast({ message: fill(T.done, '%d', t.list.length) });
		return t.list.length;
	}
	function confirm(src) {
		const t = targets(src), label = esc(name(src));
		if (!t.list.length) return;
		let message = fill(esc(fill(T.message, '%1$d', t.list.length)), '%2$s', label);
		if (t.globals) message += ' ' + esc(1 === t.globals ? T.global1 : fill(T.globals, '%d', t.globals));
		w.elementorCommon.dialogsManager.createWidget('confirm', {
			headerMessage: fill(esc(T.title || ''), '%s', label),
			message,
			strings: { confirm: T.ok, cancel: T.cancel },
			onConfirm: () => apply(src),
		}).show();
	}
	function menu(groups, view) {
		const m = view && view.options && view.options.model;
		if (!m || 'widget' !== m.get('elType') || atomic(m)) return groups;
		const c = () => view.getContainer(), at = groups.findIndex((g) => 'delete' === g.name);
		groups.splice(at < 0 ? groups.length : at, 0, { name: 'digitalisimo-apply-all', actions: [{
			name: 'digitalisimo-apply-all',
			icon: 'eicon-copy',
			title: () => fill(T.menu, '%d', targets(c()).list.length),
			isVisible: can,
			isEnabled: () => can() && !c().isLocked() && targets(c()).list.length > 0,
			callback: () => confirm(c()),
		}] });
		return groups;
	}
	function boot() {
		if (E() && E().hooks) E().hooks.addFilter('elements/widget/contextMenuGroups', menu);
	}
	w.digitalisimoApplyAllEditor = { targets, apply, confirm, menu };
	if (E() && E().hooks) boot(); else w.addEventListener('elementor/init-components', boot);
}(window));
