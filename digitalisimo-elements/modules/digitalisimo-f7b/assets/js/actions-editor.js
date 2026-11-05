/* DIGITALÍSIMO Elements F7b (V.3): en el editor, el campo «Elemento» de las acciones lista los elementos del documento
   abierto, leídos del modelo del editor (sin AJAX), y «Al hacer clic» sólo se ofrece si el elemento es un enlace o un
   botón, no tiene ningún descendiente interactivo o tiene uno solo que es enlace o botón: la misma regla que
   Actions\Render::mode() en el servidor. No cambia lo guardado. */
(w => {
'use strict';
const LIST = '_digitalisimo_actions', SECTION = 'digitalisimo_actions_section';
const Q = 'a,button,input,select,textarea,summary,iframe,video,audio,[tabindex],[contenteditable]';
const EDITOR_UI = '.elementor-element-overlay,.elementor-editor-element-settings,.elementor-empty-view,.elementor-add-section,.e-con-select-preset,.ui-resizable-handle';
const TX = w.digitalisimoActionsTexts || {};
const NONE = TX.none || 'Elige un elemento', GONE = TX.gone || 'Elemento que ya no está en este documento', NO_CLICK = TX.noClick || ' (no disponible)';

// Descendiente interactivo: la regla de Actions\Rules::interactive() y de actions.js.
const interactive = n => {
	const t = n.localName, e = n.getAttribute('contenteditable');
	return /^(a|button|select|textarea|summary|iframe)$/.test(t) || (t === 'input' && String(n.getAttribute('type') || '').toLowerCase() !== 'hidden') ||
		(/^(video|audio)$/.test(t) && n.hasAttribute('controls')) || /^\s*\d/.test(n.getAttribute('tabindex') || '') || (e !== null && e !== 'false');
};

// ¿Admite `click` el elemento de la vista previa? Sin vista, se ofrece (el servidor y el front vuelven a decidir).
const clickable = el => {
	if (!el || !el.querySelectorAll) return true;
	if (el.matches('a[href],button')) return true;
	const found = [...el.querySelectorAll(Q)].filter(n => !n.closest(EDITOR_UI) && interactive(n));
	return !found.length || (found.length === 1 && /^(a|button)$/.test(found[0].localName));
};

const title = c => {
	const m = c.model;
	const name = (m && m.getTitle && m.getTitle()) || c.label || (m && (m.get('widgetType') || m.get('elType'))) || '';
	return String(name) + ' · ' + c.id;
};

// Elementos del documento abierto, con sangría por nivel; sin el propio elemento (para él está «Este elemento»).
const targets = self => {
	const doc = w.elementor.documents && w.elementor.documents.getCurrent();
	const out = { '': NONE };
	const walk = (list, depth) => (list || []).forEach(c => {
		if (!c || !c.id || !c.model) return;
		if (c.id !== self && /^[a-z0-9]{1,24}$/i.test(c.id)) out[c.id] = '– '.repeat(depth) + title(c);
		walk(c.children, depth + 1);
	});
	if (doc && doc.container) walk(doc.container.children, 0);
	return out;
};

const field = (fields, name) => (Array.isArray(fields) ? fields.find(f => f && f.name === name) : fields && fields[name]);

const fill = page => {
	if (!page || !page.getControlModel || !page.getOption) return;
	const edited = page.getOption('editedElementView');
	const container = edited && edited.getContainer && edited.getContainer();
	const repeater = page.getControlModel(LIST);
	if (!container || !repeater) return;
	const options = targets(container.id);
	const fields = repeater.get('fields');
	const target = field(fields, 'target'), trigger = field(fields, 'trigger');
	if (!target || !trigger) return;
	if (!repeater.digiTriggers) repeater.digiTriggers = Object.assign({}, trigger.options);
	const all = repeater.digiTriggers, allowed = clickable(edited.$el && edited.$el[0]);
	const triggers = Object.assign({}, all);
	if (!allowed) delete triggers.click;
	// Filas nuevas: las crea el repetidor con estos campos.
	target.options = options;
	trigger.options = triggers;
	// Filas ya pintadas.
	const view = page.getControlViewByModel(repeater);
	if (!view || !view.children) return;
	view.children.each(row => {
		if (!row.collection || !row.model) return;
		const value = row.model.get('target'), saved = row.model.get('trigger');
		const own = Object.assign({}, options);
		if (value && !own[value]) own[value] = GONE;
		const rowTriggers = Object.assign({}, triggers);
		if (!allowed && saved === 'click') rowTriggers.click = all.click + NO_CLICK;
		[['target', own], ['trigger', rowTriggers]].forEach(([name, list]) => {
			const model = row.collection.findWhere({ name });
			if (!model) return;
			model.set('options', list);
			const control = row.getControlViewByModel ? row.getControlViewByModel(model) : row.children && row.children.findByModel(model);
			if (control && control.render) control.render();
		});
	});
};

const bind = () => w.elementor.channels.editor.on('section:activated', (name, page) => {
	if (SECTION === name) fill(page);
});
if (w.elementor && w.elementor.channels && w.elementor.channels.editor) bind();
else w.addEventListener('elementor/init', bind, { once: true });
})(window);
