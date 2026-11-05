/* DIGITALÍSIMO Elements · «Insertar dato» para las etiquetas dentro del texto (F7b, frente I). */
(function (w, $) {
	'use strict';
	const cfg = w.digitalisimoInlineTags;
	if (!cfg || !$) return;
	const T = cfg.i18n || {}, ROOT = '[data-digi-inline-tags]';
	let timer = 0, seq = 0;
	const root = (el) => $(el).closest(ROOT);
	const picked = (r) => r.find('[data-digi-inline-tag] option:selected');
	function sync(r) {
		const hint = String(picked(r).data('arg') || '');
		r.find('[data-digi-inline-arg]').prop('hidden', !hint);
		r.find('[data-digi-inline-arg-label]').text(hint);
	}
	// La etiqueta con llaves, o '' si falta un argumento obligatorio. Nada se valida aquí: lo decide el servidor.
	function token(r) {
		const o = picked(r), value = String(o.val() || ''), arg = String(r.find('[data-digi-inline-arg-input]').val() || '').trim();
		if (!value || (o.data('required') && !arg)) return '';
		return '{' + value + (arg ? String(o.data('sep') || ':') + arg : '') + '}';
	}
	function edited() {
		const page = w.elementor.getPanelView().getCurrentPageView(), view = page && page.getOption && page.getOption('editedElementView');
		return view && view.getContainer ? view.getContainer() : null;
	}
	function show(r, text) { r.find('[data-digi-inline-preview]').text(text); }
	function preview(r) {
		const tok = token(r), id = ++seq;
		if (!tok) { show(r, ''); return; }
		show(r, T.loading);
		w.elementorCommon.ajax.addRequest(cfg.action, {
			data: { token: tok },
			success: (d) => {
				if (id !== seq) return;
				d = d || {};
				show(r, [T[d.state] || T.unknown, d.value || ''].join(' ').trim());
			},
			error: () => { if (id === seq) show(r, T.failed); },
		});
	}
	// Al final del texto; en un texto con párrafos, dentro del último.
	function append(value, tok) {
		const v = String(value || ''), end = /<\/p>\s*$/.exec(v), head = end ? v.slice(0, end.index) : v, sp = head && !/\s$/.test(head) ? ' ' : '';
		return head + sp + tok + (end ? v.slice(end.index) : '');
	}
	function insert(e) {
		const r = root(e.currentTarget), tok = token(r), c = edited(), target = String(r.find('[data-digi-inline-target]').val() || '');
		if (!tok) { show(r, T.missing); return; }
		if (!c || !target) return;
		const settings = {};
		settings[target] = append(c.settings.get(target), tok);
		w.$e.run('document/elements/settings', { container: c, settings, options: { external: true } });
		preview(r);
	}
	$(w.document)
		.on('change', ROOT + ' [data-digi-inline-tag]', (e) => { const r = root(e.currentTarget); sync(r); preview(r); })
		.on('input', ROOT + ' [data-digi-inline-arg-input]', (e) => { const r = root(e.currentTarget); clearTimeout(timer); timer = setTimeout(() => preview(r), 500); })
		.on('click', ROOT + ' [data-digi-inline-insert]', insert);
	w.digitalisimoInlineTagsEditor = { token, append, sync };
}(window, window.jQuery));
