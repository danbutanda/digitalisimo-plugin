/* DIGITALÍSIMO Elements F7b · sobrescrituras del Plantilla, sólo en el editor (V.2). Textos: window.digitalisimoOverrides. */
((w) => {
	'use strict';
	const C = w.digitalisimoOverrides;
	if (!C || !C.slot) return;
	const T = C.i18n || {};
	const KINDS = C.kinds || {};
	const SLOT = C.slot;
	const RE = new RegExp(C.pattern || '^$');
	const d = w.document;
	const REP = 'digitalisimo_overrides';
	const slotsCache = {};
	const usesCache = {};
	let edited = null;
	let boundRows = null;
	let last = '';
	let timer = 0;

	const fmt = (s, a, b) => String(s || '').replace(/%(?:1\$s|2\$d|s|d)/g, (m) => String('%2$d' === m ? b : a));
	const doc = () => {
		const e = w.elementor;
		return e && e.documents && e.documents.getCurrent ? e.documents.getCurrent() : null;
	};
	const docId = () => {
		const x = doc();
		return x ? String(x.id || (x.config && x.config.id) || '') : '';
	};
	const isLib = () => {
		const x = doc();
		const k = x && x.config && x.config[C.docKey];
		return !!(k && k.library);
	};
	const mark = () => {
		if (d.body) d.body.classList.toggle('digi-ovr-lib', isLib());
	};
	const pageView = () => {
		const e = w.elementor;
		return e && e.getPanelView && e.getPanelView() && e.getPanelView().getCurrentPageView ? e.getPanelView().getCurrentPageView() : null;
	};
	const box = (selector) => {
		const p = pageView();
		return p && p.el && p.el.querySelector ? p.el.querySelector(selector) : null;
	};
	const write = (el, msgs) => {
		if (!el) return;
		const key = msgs.join('\n');
		if (el.getAttribute('data-digi') === key && (key || !el.firstChild)) return;
		el.setAttribute('data-digi', key);
		while (el.firstChild) el.removeChild(el.firstChild);
		msgs.forEach((m) => {
			const x = d.createElement('div');
			x.textContent = m;
			el.appendChild(x);
		});
	};
	const ajax = (action, data, uid, done) => {
		const c = w.elementorCommon;
		if (!c || !c.ajax || !c.ajax.addRequest) return done(null);
		c.ajax.addRequest(action, { unique_id: uid, data, success: (r) => done(r || null), error: () => done(null) });
	};
	const childView = (page, name) => {
		const m = page && page.collection && page.collection.findWhere ? page.collection.findWhere({ name }) : null;
		return m && page.children ? page.children.findByModelCid(m.cid) : null;
	};
	const field = (fields, name) => {
		if (!fields) return null;
		const list = Array.isArray(fields) ? fields : Object.keys(fields).map((k) => fields[k]);
		return list.find((f) => f && f.name === name) || null;
	};
	const plain = (v) => (v && v.toJSON ? v.toJSON() : v);
	const rowsOf = (settings) => {
		const v = plain(settings.get(REP));
		return Array.isArray(v) ? v.filter((r) => r && 'object' === typeof r) : [];
	};

	const countSlot = (value) => {
		let n = 0;
		const x = doc();
		const root = x && x.container && x.container.model && x.container.model.get ? x.container.model.get('elements') : null;
		const walk = (coll) => {
			if (!coll || !coll.each) return;
			coll.each((m) => {
				const s = m.get('settings');
				if (KINDS[m.get('widgetType')] && s && s.get && s.get(SLOT) === value) n++;
				walk(m.get('elements'));
			});
		};
		walk(root || w.elementor.elements);
		return n;
	};

	const refreshSlot = () => {
		const el = box('.digitalisimo-slot-note');
		const value = String(edited.settings.get(SLOT) || '');
		const msgs = [];
		if (!isLib() || !value) return write(el, msgs);
		if (!RE.test(value)) return write(el, [T.invalid]);
		const n = countSlot(value);
		if (n > 1) msgs.push(fmt(T.duplicate, n));
		const id = docId();
		const uses = usesCache[id];
		if (undefined === uses) {
			usesCache[id] = 0;
			ajax(C.actions.uses, {}, C.actions.uses + '-' + id, (r) => {
				usesCache[id] = r && r.ready ? r.slots || {} : null;
				schedule();
			});
		} else if (uses && uses[value]) {
			msgs.push(1 === uses[value] ? T.uses1 : fmt(T.uses, uses[value]));
		}
		write(el, msgs);
	};

	// Opciones de «Clave» en cada fila y, por la configuración del repetidor, en las nuevas.
	const fill = (opts) => {
		const rep = childView(pageView(), REP);
		if (!rep || !rep.model) return;
		const f = field(rep.model.get('fields'), 'slot');
		if (f) f.options = opts;
		const json = JSON.stringify(opts);
		if (json === last) return;
		last = json;
		if (rep.children && rep.children.each) {
			rep.children.each((row) => {
				const v = row.getChildControlView ? row.getChildControlView('slot') : null;
				if (!v || !v.model) return;
				v.model.set('options', opts);
				v.render();
			});
		}
	};

	const refreshTemplate = () => {
		const el = box('.digitalisimo-overrides-notice');
		const tid = String(edited.settings.get('template_id') || '');
		if (!tid) return write(el, []);
		const info = slotsCache[tid];
		if (undefined === info) {
			slotsCache[tid] = 0;
			ajax(C.actions.slots, { template_id: tid }, C.actions.slots + '-' + tid, (r) => {
				slotsCache[tid] = r && Array.isArray(r.slots) ? r.slots : null;
				schedule();
			});
			return;
		}
		if (!info) return write(el, null === info ? [T.loadError] : []);
		const known = {};
		const opts = { '': T.choose };
		info.forEach((x) => {
			known[x.key] = x;
			const label = x.key + ' · ' + (C.types[x.type] || x.type) + (x.label ? ' · ' + x.label : '');
			opts[x.key] = x.count > 1 ? fmt(T.repeated, label, x.count) : label;
		});
		let orphans = 0;
		let wrong = 0;
		rowsOf(edited.settings).forEach((r) => {
			const slot = String(r.slot || '');
			if (!slot) return;
			if (!known[slot]) {
				orphans++;
				opts[slot] = fmt(T.orphanOpt, slot);
			} else if ((KINDS[known[slot].type] || []).indexOf(r.kind || 'text') < 0) {
				wrong++;
			}
		});
		fill(opts);
		const msgs = [];
		if (!info.length) msgs.push(T.noSlots);
		if (orphans) msgs.push(1 === orphans ? T.orphans1 : fmt(T.orphans, orphans));
		if (wrong) msgs.push(1 === wrong ? T.wrongKind1 : fmt(T.wrongKind, wrong));
		write(el, msgs);
	};

	// Widgets Plantilla: el de PRO y el propio (C9 de «sin widgets de PRO»).
	const TPL = { template: 1, 'digitalisimo-template': 1 };
	const refresh = () => {
		if (!edited || !edited.settings || !edited.settings.get) return;
		if (TPL[edited.type]) {
			const rows = edited.settings.get(REP);
			if (rows && rows.on && rows !== boundRows) {
				if (boundRows && boundRows.off) boundRows.off(null, schedule);
				boundRows = rows;
				rows.on('add remove reset change', schedule);
			}
			refreshTemplate();
		} else if (KINDS[edited.type]) {
			refreshSlot();
		}
	};
	const schedule = () => {
		clearTimeout(timer);
		timer = setTimeout(refresh, 60);
	};

	const open = (panel, model) => {
		if (edited && edited.settings && edited.settings.off) edited.settings.off('change', schedule);
		const type = model && model.get ? model.get('widgetType') : '';
		const settings = model && model.get ? model.get('settings') : null;
		edited = { type, settings };
		last = '';
		if (TPL[type] && settings && settings.get) delete slotsCache[String(settings.get('template_id') || '')];
		if (settings && settings.on && (TPL[type] || KINDS[type])) settings.on('change', schedule);
		schedule();
	};

	const boot = () => {
		const e = w.elementor;
		if (!e || !e.hooks) return;
		const st = d.createElement('style');
		const hide = 'body:not(.digi-ovr-lib) .elementor-control-';
		st.textContent = hide + SLOT + ',' + hide + 'digitalisimo_slot_note,.elementor-control-digitalisimo_slot_note:has(.digitalisimo-slot-note:empty),.elementor-control-digitalisimo_overrides_notice:has(.digitalisimo-overrides-notice:empty){display:none!important}';
		d.head.appendChild(st);
		mark();
		if (e.on) e.on('document:loaded', () => { mark(); schedule(); });
		e.hooks.addAction('panel/open_editor/widget', open);
		e.channels.editor.on('section:activated', schedule).on('change', schedule);
	};
	w.digitalisimoOverridesEditor = { refresh, countSlot, isLib };
	if (w.elementor && w.elementor.hooks) boot(); else w.addEventListener('elementor/init', boot, { once: true });
})(window);
