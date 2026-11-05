/* DIGITALÍSIMO Elements · Menú con submenús de contenido en el editor (C3, frente E). Sólo se encola en el editor
   (Module::editor_scripts).
   - Registra el tipo `digitalisimo-mega-menu` sobre `NestedElementBase`, como el carrusel anidado propio.
   - Entrelazado (como PRO): cada contenedor hijo va dentro del `.digi-mm__content` de su elemento (`li` de
     `.digi-mm__heading`), en el mismo orden; los que sobran quedan en la lista y la hoja los oculta.
   - Cada hijo recibe el id, el número y `aria-labelledby` que le da el servidor (`print_child()`).
   - Al elegir un elemento en el panel o en el navegador (`activeItemIndex`) se abre su contenido en el lienzo con el
     evento `digi-mm-go`; tras añadir, duplicar, mover o borrar un elemento (`elementor/nested-container/atomic-repeater`)
     el widget se vuelve a pintar y el script del front lo inicia otra vez con `element_ready`. */
(() => {
const W = window, TYPE = 'digitalisimo-mega-menu';
let done = false;
const register = () => {
	const types = W.elementor && elementor.modules && elementor.modules.elements && elementor.modules.elements.types;
	const Base = types && types.NestedElementBase;
	const nested = W.$e && $e.components && $e.components.get('nested-elements');
	const NestedView = nested && nested.exports && nested.exports.NestedView;
	if (done || 'function' !== typeof Base || 'function' !== typeof NestedView) return;
	done = true;
	// Los `.digi-mm__content` del propio widget (no los de un menú anidado dentro de un contenido).
	const slots = view => {
		const head = view.el && view.el.querySelector('.digi-mm__heading');
		return head ? [...head.children].map(li => li.querySelector(':scope>.digi-mm__content')) : [];
	};
	class View extends NestedView {
		getChildViewContainer(containerView, childView) {
			if (childView && undefined !== childView._index) {
				const slot = slots(containerView)[childView._index];
				if (slot) return containerView.$el.constructor(slot);
			}
			return super.getChildViewContainer(containerView, childView);
		}
		attachBuffer(compositeView, buffer) {
			const list = slots(compositeView), $c = this.getChildViewContainer(compositeView);
			[...buffer.childNodes].forEach((node, i) => (list[i] ? list[i].appendChild(node) : $c.append(node)));
		}
		onAddChild(child) {
			if (super.onAddChild) super.onAddChild(child);
			this.digiLabel();
		}
		onRender(...args) {
			if (super.onRender) super.onRender(...args);
			this.digiLabel();
		}
		digiLabel() {
			if (!this.children || !this.collection || !this.el) return;
			const nav = this.el.querySelector('.digi-mm'), n = nav ? nav.getAttribute('data-widget-number') : '';
			this.children.each(view => {
				const k = view.model ? this.collection.indexOf(view.model) + 1 : 0;
				if (!k || !view.el) return;
				view.el.id = 'digi-mm-content-' + n + k;
				view.el.setAttribute('data-tab-index', k);
				view.el.setAttribute('aria-labelledby', 'digi-mm-dropdown-icon-' + n + k);
			});
		}
	}
	class MegaMenu extends Base {
		getType() {
			return TYPE;
		}
		getView() {
			return View;
		}
	}
	elementor.elementsManager.registerElementType(new MegaMenu());
	let bridged = false;
	const preview = () => {
		// Panel o navegador → lienzo: el elemento que se edita (`activeItemIndex`, desde 1) abre su contenido.
		if (!bridged && elementor.channels && elementor.channels.editor) {
			bridged = true;
			elementor.channels.editor.on('change:editSettings', (model, view) => {
				const index = model && model.changed && model.changed.activeItemIndex;
				if (!index || !view || !view.model || TYPE !== view.model.get('widgetType') || !view.el) return;
				const root = view.el.querySelector('.digi-mm');
				if (root) root.dispatchEvent(new root.ownerDocument.defaultView.CustomEvent('digi-mm-go', { detail: index }));
			});
		}
		const win = elementor.$preview && elementor.$preview[0] && elementor.$preview[0].contentWindow;
		if (!win || win.digitalisimoMegaMenuRepeater) return;
		win.digitalisimoMegaMenuRepeater = true;
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
