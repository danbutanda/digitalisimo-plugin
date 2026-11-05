/* DIGITALÍSIMO Elements · presets de estilo en el editor (F7a). */
(function (w, $) {
	'use strict';
	const cfg = w.digitalisimoPresets;
	if (!cfg || !$) return;
	const C = cfg.control, T = cfg.i18n || {}, ES = 'document/elements/settings', GS = 'document/globals/settings', SUF = /_(widescreen|laptop|(tablet|mobile)(_extra)?)$/;
	const P = () => cfg.presets || {}, ks = (o) => Object.keys(o || {}), own = (k) => '__' !== k.slice(0, 2), as = Object.assign;
	const kind = (m) => m.get('widgetType') || m.get('elType'), saved = (c) => c.settings.toJSON({ remove: ['default'] }), cs = (c) => c.settings.controls;
	function root(k, ctl) {
		while (ctl[k] && ctl[k].parent) k = ctl[k].parent;
		return ctl[k] ? k : k.replace(SUF, '');
	}
	function roots(st, ctl, out, keep) {
		const r = {}, mark = (n, o, all) => ks(o).forEach((k) => { if ((all ? own(k) : o[k]) && (!keep || keep[root(k, ctl)])) { r[root(k, ctl)] = 1; if (out) out[n].push(k); } });
		mark('values', st, 1); mark('globals', st.__globals__); mark('dynamic', st.__dynamic__);
		return r;
	}
	function flatten(p, st, ctl) {
		const loc = roots(st, ctl), out = { values: {}, globals: {} }, g = p.__globals__ || {};
		ks(p).forEach((k) => { if (own(k) && !loc[root(k, ctl)]) out.values[k] = p[k]; });
		ks(g).forEach((k) => { if (g[k] && !loc[root(k, ctl)]) out.globals[k] = g[k]; });
		return out;
	}
	const linked = (c) => { const s = c.settings.get(C), p = s && P()[s]; return p && !p.error && p.type === kind(c.model) ? s : ''; };
	const fill = (c, s = linked(c)) => flatten(s ? P()[s].settings : {}, saved(c), cs(c));
	function covered(c) {
		const out = { values: [], globals: [], dynamic: [] };
		if (linked(c)) roots(saved(c), cs(c), out, roots(P()[linked(c)].settings, cs(c)));
		return out;
	}
	const count = (c) => [].concat(...Object.values(covered(c))).length;
	function preview(view) {
		const m = view && view.getEditModel && view.getEditModel(), s = m && m.get('settings');
		if (!s || !view.controlsCSSParser) return '';
		const f = fill({ model: m, settings: s }), names = ks(f.values).concat(ks(f.globals)), v = as({}, s.attributes, f.values), id = m.get('id');
		const g = v.__globals__ = as({}, s.get('__globals__'), f.globals), act = s.getActiveControls(s.controls, v);
		const list = Object.values(act).filter((x) => names.indexOf(x.name) > -1 || (x.groupType && f.globals[x.groupPrefix + x.groupType]));
		if (!list.length) return '';
		const p = new (Object.getPrototypeOf(view.controlsCSSParser).constructor)({ settingsModel: s, context: { model: { get: () => ({ get: () => g }) }, container: { isGlobalApplied: () => false } } });
		p.addStyleRules(list.map((x) => as({}, elementor.config.controls[x.type], x)), v, s.controls, [/{{ID}}/g, /{{WRAPPER}}/g], [id, `.elementor-${elementor.config.document.id} .elementor-element.elementor-element-${id}`]);
		return String(p.stylesheet);
	}
	function sync(page) {
		const cm = page && page.getControlModel && page.getControlModel(C), ev = cm && page.getOption('editedElementView'), c = ev && ev.getContainer(), all = P();
		if (!c) return;
		if (undefined === cm.digiNote) cm.digiNote = cm.get('description') || '';
		const n = count(c), options = { '': T.none }, view = page.getControlViewByModel(cm);
		ks(all).forEach((k) => { if (all[k].type === kind(c.model)) options[k] = all[k].label + (all[k].error ? ' · ' + T.error : ''); });
		cm.set({ options, description: [cm.digiNote, 1 === n ? T.covered1 : (n ? T.covered.replace('%d', n) : '')].join(' ').trim() });
		if (view && cm.hasChanged()) view.render();
	}
	function refresh(slug) {
		const doc = elementor.documents.getCurrent();
		sync(elementor.getPanelView().getCurrentPageView());
		if (doc && doc.container) doc.container.children.forEachRecursive((c) => { if (c.view && (!slug || slug === c.settings.get(C))) c.view.renderStyles(); });
	}
	const run = (cmd, c, settings) => { if (ks(settings).length) $e.run(cmd, { container: c, settings, options: { external: true } }); };
	const toast = (message) => elementor.notifications.showToast({ message });
	const send = (action, data, ok) => elementorCommon.ajax.addRequest(action, { data, error: (e) => toast(('string' === typeof e && e) || T.failed), success: (d) => {
		d = d || {};
		if (d.presets) cfg.presets = d.presets;
		ok(d);
		refresh(d.slug || data.slug);
	} });
	function dialog(t, d, v) {
		const i = undefined === v ? '' : $('<input type="text" class="elementor-input" maxlength="60">').val(v);
		elementorCommon.dialogsManager.createWidget('confirm', { headerMessage: t, message: i, strings: { confirm: T.ok, cancel: T.cancel },
			onConfirm: () => { const x = i ? i.val().trim() : 1; if (x) d(x); } }).show();
	}
	const style = (x, k) => !!x && C !== k && (undefined !== x.style_transfer ? !!x.style_transfer : ('content' !== x.tab || !!x.selectors || !!x.prefix_class));
	function extract(c) {
		const ctl = cs(c), st = saved(c), f = fill(c), out = as({}, f.values), g = as({}, f.globals), lg = st.__globals__ || {};
		ks(st).forEach((k) => { if (own(k) && style(ctl[k], k)) out[k] = st[k]; });
		ks(lg).forEach((k) => { if (lg[k] && style(ctl[k], k)) g[k] = lg[k]; });
		if (ks(g).length) out.__globals__ = g;
		return out;
	}
	function only(c) {
		const k = covered(c), ctl = cs(c), set = (l, fn) => l.reduce((o, n) => as(o, { [n]: fn ? fn(n) : '' }), {});
		run(ES, c, set(k.values, (n) => { const x = (ctl[n] || {}).default; return JSON.parse(JSON.stringify(undefined === x ? '' : x)); }));
		run(GS, c, set(k.globals));
		run('document/dynamic/disable', c, set(k.dynamic));
	}
	function unlink(c) { const f = fill(c); run(ES, c, as({}, f.values, { [C]: '' })); run(GS, c, f.globals); }
	function save(c, slug) {
		const go = (label) => send('digitalisimo_preset_save', { slug, type: kind(c.model), label, settings: extract(c) }, (d) => {
			if (!P()[d.slug]) return;
			run(ES, c, { [C]: d.slug });
			only(c);
			const left = [...new Set((d.local || []).filter(own).map((k) => (cs(c)[k] || {}).label || k))];
			toast(T.saved + (left.length ? ` ${T.left} ${left.slice(0, 8).join(', ')}` : ''));
		});
		if (slug) go(P()[slug].label); else dialog(T.name, go, '');
	}
	function menu(groups, view) {
		const s = (view.getEditModel() || view.options.model).get('settings') || {}, c = () => view.getContainer();
		if (!(s.controls || {})[C]) return groups;
		const can = (k) => !!cfg[k] && !c().isLocked(), at = groups.findIndex((g) => 'delete' === g.name);
		const a = (n, e, f) => ({ name: 'digitalisimo-preset-' + n, title: T[n], isEnabled: e, callback: () => f(c()) });
		groups.splice(at < 0 ? groups.length : at, 0, { name: 'digitalisimo-preset', actions: [
			a('save', () => can('canManage'), (x) => save(x, '')),
			a('update', () => can('canManage') && !!linked(c()), (x) => save(x, linked(x))),
			a('only', () => can('canDesign') && count(c()) > 0, only),
			a('unlink', () => can('canDesign') && !!c().settings.get(C), unlink),
		] });
		return groups;
	}
	function tab(e) {
		const b = $(e.currentTarget), slug = String(b.data('digiPreset')), row = b.closest('[data-digi-preset-row]');
		if ('rename' === b.data('digiPresetAction')) dialog(T.name, (label) => send('digitalisimo_preset_rename', { slug, label }, () => row.find('[data-digi-preset-label]').text(label)), (P()[slug] || {}).label || '');
		else dialog(T.remove, () => send('digitalisimo_preset_delete', { slug }, () => row.remove()));
	}
	function boot() {
		const e = w.elementor;
		if (!e || !e.hooks) return;
		e.hooks.addFilter('editor/style/styleText', (css, view) => { try { return css + preview(view); } catch (x) { return css; } });
		['widget', 'container'].forEach((t) => e.hooks.addFilter(`elements/${t}/contextMenuGroups`, menu));
		e.channels.editor.on('section:activated', (name, page) => sync(page)).on('change', (cv, ev) => {
			if (C === cv.model.get('name')) ev.renderStyles();
			sync(e.getPanelView().getCurrentPageView());
		});
		$(w.document).on('click', '[data-digi-preset-action]', tab);
	}
	w.digitalisimoPresetsEditor = { flatten, covered, extract, refresh };
	if (w.elementor && w.elementor.hooks) boot(); else w.addEventListener('elementor/init-components', boot);
}(window, window.jQuery));
