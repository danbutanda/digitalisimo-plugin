/* DIGITALÍSIMO Elements · Panel fuera de lienzo en el editor (C3, frente F). Sólo se encola en el editor
   (Module::editor_scripts), con `nested-elements`.
   - Registra el tipo `digitalisimo-nested-off-canvas` sobre `NestedElementBase` (en `elementor/nested-element-type-loaded`
     o al momento si el núcleo ya lo definió), como el Carrusel anidado de C1.
   - Abre el panel en el lienzo al editar su sección «Diseño» y sigue el «Modo de edición» (control de interfaz: no repinta)
     con el evento de ventana que escucha el script del front.
   - Añade los paneles propios del documento a la lista «Elige un panel» de la etiqueta dinámica «Off-Canvas», que el
     editor de PRO sólo llena con los suyos (mismo valor: el id del elemento; mismo texto: el nombre del panel).
   - Oculta la pestaña «Avanzado», como PRO. */
(() => {
const W = window, TYPE = 'digitalisimo-nested-off-canvas', EV = 'elementor-pro/off-canvas/toggle-display-mode';
let done = false;
const fire = (id, mode) => {
	const win = W.elementor && elementor.$preview && elementor.$preview[0] && elementor.$preview[0].contentWindow;
	if (win && id) win.dispatchEvent(new win.CustomEvent(EV, { detail: { id: String(id), displayMode: mode } }));
};
const register = () => {
	const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
	const Base = types && types.NestedElementBase;
	const nested = W.$e && $e.components && $e.components.get('nested-elements');
	const NestedView = nested && nested.exports && nested.exports.NestedView;
	if (done || 'function' !== typeof Base || 'function' !== typeof NestedView) return;
	done = true;
	class View extends NestedView {
		onRender(...args) {
			if (super.onRender) super.onRender(...args);
			const settings = this.model && this.model.get('settings');
			if (settings && !this.digiWatched) {
				this.digiWatched = true;
				this.listenTo(settings, 'change:editing_mode', () => fire(this.model.get('id'), 'yes' === settings.get('editing_mode') ? 'open' : 'close'));
			}
		}
	}
	class OffCanvas extends Base {
		getType() {
			return TYPE;
		}
		getView() {
			return View;
		}
	}
	elementor.elementsManager.registerElementType(new OffCanvas());
	const channel = elementor.channels && elementor.channels.editor;
	if (!channel) return;
	channel.on('editor:widget:' + TYPE + ':section_layout:activated', () => {
		const page = elementor.getPanelView().getCurrentPageView(), view = page && page.getOption('editedElementView');
		if (view && view.getEditModel) fire(view.getEditModel().get('id'), 'open');
	});
	channel.on('section:activated', (name, editor) => {
		const model = editor && editor.model;
		if (!model || TYPE !== model.get('widgetType') || !editor.el) return;
		const tab = editor.el.querySelector('.elementor-tab-control-advanced');
		if (tab) tab.style.display = 'none';
	});
	// Lista de la etiqueta dinámica: después de que el editor de PRO la llene (mismo canal, escuchado antes).
	elementor.listenTo(channel, 'all', (name, view) => {
		if (!/:off-canvas:settings:activated$/.test(String(name)) || !view || !view.collection) return;
		const control = view.collection.findWhere({ name: 'off_canvas' });
		const doc = elementor.$previewContents && elementor.$previewContents[0];
		if (!control || !doc || !elementor.config.document) return;
		const options = Object.assign({}, control.get('options') || {});
		doc.querySelectorAll('[data-elementor-id="' + elementor.config.document.id + '"] .elementor-widget-' + TYPE + '.elementor-element-edit-mode').forEach(el => {
			const panel = el.querySelector('.digi-noc');
			if (el.dataset.id) options[el.dataset.id] = panel ? panel.getAttribute('aria-label') : el.dataset.id;
		});
		control.set({ options });
		const child = view.children && view.children.findByModel(control);
		if (child) child.render();
	});
};
const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
if (types && 'function' === typeof types.NestedElementBase) register();
W.addEventListener('elementor/nested-element-type-loaded', register);
})();
