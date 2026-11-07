/* DIGITALÍSIMO Elements · Cuadrícula y Carrusel de bucle propios en la vista previa del editor (C5, como el manejador
   de PRO en modo edición: `attachEditDocumentHandle()` y `handleCTA()`), sin los paquetes de PRO del sitio:
   - con plantilla, el asa «Editar plantilla» (`elementor-document-handle`, la de PRO, con su hoja de la vista previa)
     sobre el primer Elemento de bucle (en el carrusel, sobre la pista), que abre la plantilla en su sitio con
     `editor/documents/switch`;
   - sin plantilla, la llamada de PRO para crearla (`elementorPro.modules.loopBuilder`, su plantilla `#tmpl-<widget>-cta`
     y su hoja, en un shadow root), que crea la plantilla y la asigna con el control «Elige una plantilla».
   Sólo se carga en la vista previa del editor. */
((w, d) => {
	'use strict';
	if (w.digiLoopPreview) return;
	w.digiLoopPreview = 1;
	const TYPES = { 'digitalisimo-loop-grid': 'loop-grid', 'digitalisimo-loop-carousel': 'loop-carousel' };
	const top = () => { try { return w.top; } catch (e) { return null; } };
	const settings = (id) => { try { return top().$e.components.get('document').utils.findContainerById(id).settings; } catch (e) { return null; } };
	const loopBuilder = () => { try { return top().elementorPro.modules.loopBuilder; } catch (e) { return null; } };
	const handle = (el, type, id, tid) => {
		const own = `.elementor-element-${id}`;
		let sel = (settings(id) && settings(id).get('edit_handle_selector')) || ('loop-grid' === type ? '[data-elementor-type="loop-item"]' : '.digi-crs__track');
		if ('[data-elementor-type="loop-item"]' === sel) sel += `.elementor-${tid}`;
		const target = el.querySelector(sel);
		if (!target || target.classList.contains('elementor-edit-mode') || target.querySelector(':scope > .elementor-document-handle')) return;
		const box = d.createElement('div'), inner = d.createElement('div'), icon = d.createElement('i'), title = d.createElement('div');
		box.className = 'elementor-document-handle';
		inner.className = 'elementor-document-handle__inner';
		icon.className = 'eicon-edit';
		title.className = 'elementor-document-handle__title';
		title.textContent = 'Editar plantilla';
		inner.append(icon, title);
		box.append(inner);
		// Como `onInPlaceEditTemplate()` de PRO: al editarla en su sitio, fuera los estilos del bucle (los pone el editor).
		const onClose = () => {
			el.classList.add('elementor-in-place-template-editable');
			el.querySelectorAll(`style#loop-${tid},link#font-loop-${tid},style#loop-dynamic-${tid}` + ('loop-carousel' === type ? ',.elementor-document-handle' : '')).forEach((n) => n.remove());
		};
		box.addEventListener('click', () => {
			const t = top();
			if (!t || !t.$e) return;
			t.$e.internal('panel/state-loading');
			Promise.resolve(t.$e.run('editor/documents/switch', { id: parseInt(tid, 10), onClose, selector: `${own} .elementor-${tid}` })).finally(() => t.$e.internal('panel/state-ready'));
		});
		target.prepend(box);
		target.dataset.editableElementorDocument = tid;
	};
	const cta = (el, type) => {
		const box = el.querySelector('.e-loop-empty-view__wrapper'), lb = loopBuilder();
		if (!box || box.shadowRoot || !lb) return;
		const root = box.attachShadow({ mode: 'open' });
		root.appendChild(lb.getCtaStyles());
		root.appendChild(lb.getCtaContent(type));
		const go = root.querySelector('.e-loop-empty-view__box-cta');
		if (go) go.addEventListener('click', (e) => { e.preventDefault(); lb.createTemplate(); });
	};
	const ready = (scope) => {
		const el = scope && (scope[0] || scope);
		if (!el || !el.dataset) return;
		const type = TYPES[el.dataset.widget_type ? el.dataset.widget_type.split('.')[0] : ''], id = el.dataset.id;
		if (!type || !id) return;
		const s = settings(id), tid = s && s.get('template_id');
		if (tid) handle(el, type, id, tid); else cta(el, type);
	};
	const scan = () => d.querySelectorAll(Object.keys(TYPES).map((t) => `[data-widget_type="${t}.default"]`).join(',')).forEach(ready);
	const hook = () => {
		const h = (w.elementorFrontend || {}).hooks;
		if (!h) return;
		Object.keys(TYPES).forEach((t) => h.addAction(`frontend/element_ready/${t}.default`, ready));
		scan();
	};
	(w.elementorFrontend || {}).hooks ? hook() : w.addEventListener('elementor/frontend/init', hook);
})(window, document);
