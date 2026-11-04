/* DIGITALÍSIMO Elements · carrusel anidado en el editor (C1, frente N). Sólo se encola en el editor (Module::editor_scripts).
   - Registra el tipo `digitalisimo-nested-carousel` sobre `NestedElementBase`, como `nested-tabs.js` del núcleo (en
     `elementor/nested-element-type-loaded`, o al momento si el núcleo ya lo definió).
   - Su vista marca cada contenedor hijo como diapositiva del motor `digi-crs` (clase `digi-crs__slide`, `role="group"`,
     `aria-roledescription` y «N de M», lo mismo que el servidor), también cuando el hijo se vuelve a pintar. Los textos
     salen de la lista oculta `.digi-ncarousel__items` de la plantilla (`data-digi-slide`, `data-digi-of`).
   - Al elegir una diapositiva en el panel o en el navegador (`activeItemIndex`), la muestra en el lienzo con el evento
     `digi-crs-go` que escucha el motor.
   - Tras añadir, duplicar, mover o borrar una diapositiva (`elementor/nested-container/atomic-repeater` en la vista
     previa) vuelve a pintar el widget: flechas, puntos y fracción salen otra vez de la plantilla con el número nuevo, y
     el motor se inicia de nuevo con `element_ready`. */
(() => {
const W = window, TYPE = 'digitalisimo-nested-carousel';
let done = false;
const register = () => {
	const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
	const Base = types && types.NestedElementBase;
	const nested = W.$e && $e.components && $e.components.get('nested-elements');
	const NestedView = nested && nested.exports && nested.exports.NestedView;
	if (done || 'function' !== typeof Base || 'function' !== typeof NestedView) return;
	done = true;
	class View extends NestedView {
		onRender(...args) {
			this.digiLabel();
			if (super.onRender) super.onRender(...args);
		}
		onAddChild(child) {
			if (super.onAddChild) super.onAddChild(child);
			if (child && !child.digiWatched) {
				child.digiWatched = true;
				this.listenTo(child, 'render', () => this.digiLabel());
			}
			this.digiLabel();
		}
		onRemoveChild(child) {
			if (super.onRemoveChild) super.onRemoveChild(child);
			this.digiLabel();
		}
		digiLabel() {
			if (!this.children || !this.collection || !this.el) return;
			const list = this.el.querySelector('.digi-ncarousel__items');
			const slide = list ? list.getAttribute('data-digi-slide') || '' : '', of = list ? list.getAttribute('data-digi-of') || '%1$d / %2$d' : '%1$d / %2$d';
			const total = this.collection.length;
			this.children.each(view => {
				const k = view.model ? this.collection.indexOf(view.model) : -1;
				if (k < 0 || !view.el) return;
				view.el.classList.add('digi-crs__slide');
				view.el.setAttribute('role', 'group');
				view.el.setAttribute('aria-roledescription', slide);
				view.el.setAttribute('aria-label', of.replace('%1$d', k + 1).replace('%2$d', total));
			});
		}
	}
	class NestedCarousel extends Base {
		getType() {
			return TYPE;
		}
		getView() {
			return View;
		}
	}
	elementor.elementsManager.registerElementType(new NestedCarousel());
	let bridged = false;
	const preview = () => {
		// Panel o navegador → lienzo: la diapositiva que se edita (`activeItemIndex`, desde 1) se muestra en la vista previa.
		if (!bridged && elementor.channels && elementor.channels.editor) {
			bridged = true;
			elementor.channels.editor.on('change:editSettings', (model, view) => {
				const index = model && model.changed && model.changed.activeItemIndex;
				if (!index || !view || !view.model || TYPE !== view.model.get('widgetType') || !view.el) return;
				const root = view.el.querySelector('.digi-crs');
				if (root) root.dispatchEvent(new root.ownerDocument.defaultView.CustomEvent('digi-crs-go', { detail: index - 1 }));
			});
		}
		// Repetidor: el widget se vuelve a pintar con el número nuevo de diapositivas.
		const win = elementor.$preview && elementor.$preview[0] && elementor.$preview[0].contentWindow;
		if (!win || win.digitalisimoNestedCarouselRepeater) return;
		win.digitalisimoNestedCarouselRepeater = true;
		win.addEventListener('elementor/nested-container/atomic-repeater', e => {
			const container = e.detail && e.detail.container, view = container && container.view;
			if (!view || !view.model || TYPE !== view.model.get('widgetType')) return;
			setTimeout(() => {
				if (!view.isDestroyed && 'function' === typeof view.render) view.render();
			}, 0);
		});
	};
	if (elementor.on) elementor.on('preview:loaded', preview);
	preview();
};
const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
if (types && 'function' === typeof types.NestedElementBase) register();
W.addEventListener('elementor/nested-element-type-loaded', register);
})();
