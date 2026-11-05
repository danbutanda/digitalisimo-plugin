/*
 * DIGITALÍSIMO Elements · F7b, campos condicionales del Formulario: avisos de cada campo en el panel del editor
 * (V.9, C2-12 e). Con «Mostrar sólo si…» encendido, el hueco `.digi-cond-warn` del aviso del campo dice si no tiene
 * reglas, si una regla no tiene ID, si mira a un ID que no existe, al propio campo o a un campo posterior (el campo
 * aparecería antes que el que lo controla, 1.3.2; todo ciclo de reglas pasa por uno así). Sólo lee el modelo del
 * editor: no guarda nada ni hace peticiones. Los textos llegan traducidos en `window.digitalisimoFormConditions`.
 */
((w, $) => {
	'use strict';
	const T = (w.digitalisimoFormConditions || {}).i18n || {};
	const fill = (text, id) => String(text || '').replace('%s', id);
	let current = null;
	let timer = 0;

	/** Avisos del campo `i` de la lista de campos (ajustes en JSON del repetidor `form_fields`). */
	function warnings(rows, i) {
		const row = rows[i] || {};
		if ('yes' !== row.digitalisimo_conditional) return [];
		const ids = rows.map((r) => String((r && r.custom_id) || ''));
		const rules = Array.isArray(row.digitalisimo_rules) ? row.digitalisimo_rules : [];
		const out = rules.length ? [] : [T.none];
		rules.forEach((rule) => {
			const id = String((rule && rule.field) || '').replace(/[^A-Za-z0-9_]/g, '');
			const at = ids.indexOf(id);
			if (!id) out.push(T.invalid);
			else if (at < 0) out.push(fill(T.missing, id));
			else if (at === i) out.push(T.self);
			else if (at > i) out.push(fill(T.later, id));
		});
		return [...new Set(out.filter(Boolean))];
	}

	function refresh() {
		const settings = current && current.get('settings');
		const list = settings && settings.get('form_fields');
		if (!list || !list.toJSON || !$) return;
		const rows = list.toJSON();
		$('#elementor-panel .elementor-control-form_fields').first().find('.elementor-repeater-fields-wrapper').first()
			.children('.elementor-repeater-fields').each((i, el) => {
				$(el).find('.digi-cond-warn').first().text(warnings(rows, i).join(' '));
			});
	}

	function later() {
		clearTimeout(timer);
		timer = setTimeout(refresh, 120);
	}

	function boot() {
		const e = w.elementor;
		if (!e || !e.hooks || boot.on) return;
		boot.on = 1;
		guard();
		e.hooks.addAction('panel/open_editor/widget', (panel, model) => {
			current = model && 'form' === model.get('widgetType') ? model : null;
			if (current) later();
		});
		if (e.channels && e.channels.editor) e.channels.editor.on('section:activated', later);
		if ($) $(w.document).on('input change click sortupdate', '#elementor-panel', () => current && later());
	}

	// Repetidor en repetidor: el núcleo escribe la colección de reglas en la fila cruda de `config.elements` y el recorrido
	// de `editor-templates-extended` de PRO agota la pila (f7b.sh, B10). Se convierte una copia de cada fila.
	function guard() {
		const B = w.elementorModules && w.elementorModules.editor && w.elementorModules.editor.elements && w.elementorModules.editor.elements.models && w.elementorModules.editor.elements.models.BaseSettings;
		const p = B && B.prototype;
		if (!p || !p.convertRepeaterValueToCollection || p.convertRepeaterValueToCollection.digi) return;
		const orig = p.convertRepeaterValueToCollection;
		p.convertRepeaterValueToCollection = function (attrs, control) {
			const rows = attrs && attrs[control.name];
			if (Array.isArray(rows) && Object.values(control.fields || {}).some((f) => f && f.is_repeater)) {
				attrs = Object.assign({}, attrs, { [control.name]: rows.map((r) => (r && 'object' === typeof r && !r.cid ? Object.assign({}, r) : r)) });
			}
			return orig.call(this, attrs, control);
		};
		p.convertRepeaterValueToCollection.digi = 1;
	}
	guard();

	w.digitalisimoFormConditionsEditor = { warnings };
	if (w.elementor && w.elementor.hooks) boot();
	else w.addEventListener('elementor/init', boot);
})(window, window.jQuery);
