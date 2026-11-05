/* DIGITALÍSIMO Elements · slider anidado ligero en el editor (F7b, C1-12). Sólo se encola en el editor.
   - Registra el tipo `digitalisimo-nested-slider` sobre `NestedElementBase`, como `nested-tabs.js` del núcleo (en
     `elementor/nested-element-type-loaded`, o al momento si el núcleo ya lo definió).
   - Su vista etiqueta cada contenedor hijo como diapositiva («N de M»), lo mismo que el servidor.
   - Al elegir una diapositiva en el panel o en el navegador (`activeItemIndex`), la muestra en el lienzo con el evento
     `digi-nslider-go` que escucha `nested-slider.js`.
   - Tras cada cambio del repetidor (`elementor/nested-container/atomic-repeater` en la vista previa), el slider repinta
     su estado y renumera los puntos con `root.digiNslider.paint()`. */
(() => {
const W = window, TYPE = 'digitalisimo-nested-slider', T = W.digitalisimoNestedSlider || {};
let done = false;
const register = () => {
	const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
	const Base = types && types.NestedElementBase;
	const nested = W.$e && $e.components && $e.components.get('nested-elements');
	const NestedView = nested && nested.exports && nested.exports.NestedView;
	if (done || 'function' !== typeof Base || 'function' !== typeof NestedView) return;
	done = true;
	class View extends NestedView {
		onAddChild(child) {
			if (super.onAddChild) super.onAddChild(child);
			this.digiLabel();
		}
		onRemoveChild(child) {
			if (super.onRemoveChild) super.onRemoveChild(child);
			this.digiLabel();
		}
		digiLabel() {
			const total = this.collection ? this.collection.length : 0, of = T.of || '%1$s / %2$s';
			if (!this.children) return;
			this.children.each(view => {
				const k = view.model && this.collection ? this.collection.indexOf(view.model) : -1;
				if (k < 0 || !view.$el) return;
				view.$el.attr({ role: 'group', 'aria-roledescription': T.slide || '', 'aria-label': of.replace('%1$s', k + 1).replace('%2$s', total) });
			});
		}
	}
	class NestedSlider extends Base {
		getType() {
			return TYPE;
		}
		getView() {
			return View;
		}
	}
	elementor.elementsManager.registerElementType(new NestedSlider());
	let bridged = false;
	const preview = () => {
		// Panel o navegador → lienzo: la diapositiva que se edita (`activeItemIndex`, desde 1) se muestra en la vista previa.
		if (!bridged && elementor.channels && elementor.channels.editor) {
			bridged = true;
			elementor.channels.editor.on('change:editSettings', (model, view) => {
				const index = model && model.changed && model.changed.activeItemIndex;
				if (!index || !view || !view.model || TYPE !== view.model.get('widgetType') || !view.el) return;
				const root = view.el.querySelector('.digi-nslider');
				if (root) root.dispatchEvent(new root.ownerDocument.defaultView.CustomEvent('digi-nslider-go', { detail: index - 1 }));
			});
		}
		// Repetidor: al añadir, quitar, mover o duplicar diapositivas, el slider renumera sus puntos y repinta.
		const win = elementor.$preview && elementor.$preview[0] && elementor.$preview[0].contentWindow;
		if (!win || win.digitalisimoNestedSliderRepeater) return;
		win.digitalisimoNestedSliderRepeater = true;
		win.addEventListener('elementor/nested-container/atomic-repeater', e => {
			const container = e.detail && e.detail.container, el = container && container.view && container.view.el;
			const root = el && el.querySelector('.digi-nslider');
			if (root && root.digiNslider) root.digiNslider.paint();
		});
	};
	if (elementor.on) elementor.on('preview:loaded', preview);
	preview();
};
const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
if (types && 'function' === typeof types.NestedElementBase) register();
W.addEventListener('elementor/nested-element-type-loaded', register);
})();
