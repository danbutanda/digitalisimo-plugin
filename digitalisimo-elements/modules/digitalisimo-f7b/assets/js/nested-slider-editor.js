/* DIGITALÍSIMO Elements · slider anidado ligero en el editor (F7b, C1-12). Sólo se encola en el editor.
   - Registra el tipo `digitalisimo-nested-slider` sobre `NestedElementBase`, como `nested-tabs.js` del núcleo (en
     `elementor/nested-element-type-loaded`, o al momento si el núcleo ya lo definió).
   - Su vista etiqueta cada contenedor hijo como diapositiva («N de M»), lo mismo que el servidor.
   - Al elegir una diapositiva en el panel o en el navegador (`activeItemIndex`), la muestra en el lienzo con el evento
     `digi-nslider-go` que escucha `nested-slider.js`. */
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
	if (elementor.channels && elementor.channels.editor) {
		elementor.channels.editor.on('change:editSettings', (model, view) => {
			const index = model && model.changed && model.changed.activeItemIndex;
			if (!index || !view || !view.model || TYPE !== view.model.get('widgetType') || !view.el) return;
			const root = view.el.querySelector('.digi-nslider');
			if (root) root.dispatchEvent(new root.ownerDocument.defaultView.CustomEvent('digi-nslider-go', { detail: index - 1 }));
		});
	}
};
const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
if (types && 'function' === typeof types.NestedElementBase) register();
W.addEventListener('elementor/nested-element-type-loaded', register);
})();
