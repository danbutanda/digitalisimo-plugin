/* Páginas de la tienda en el editor (`Woo\Store\Shop_Pages`): el módulo de WooCommerce del editor sólo conoce los widgets
   de PRO. Con los propios, como con aquéllos: al guardar una página con uno de los cuatro recién soltado, el aviso
   «¿Guardar esto como página del carrito / del pago / de Mi cuenta / del resumen?» (sus mismos textos y su misma
   petición), y en el Carrito, el botón «Editar plantilla» de «Personalizar el carrito vacío» con su enlace. */
(function (w) {
	'use strict';
	var OWN = {
		'digitalisimo-woo-cart': 'woocommerce-cart',
		'digitalisimo-woo-checkout': 'woocommerce-checkout-page',
		'digitalisimo-woo-my-account': 'woocommerce-my-account',
		'digitalisimo-woo-purchase-summary': 'woocommerce-purchase-summary'
	};
	function wc() {
		var p = w.elementorPro;
		return p && p.modules && p.modules.woocommerce;
	}
	function pages() {
		var m = wc();
		if (!m || !m.pageSettingsWidgets) return;
		Object.keys(OWN).forEach(function (own) {
			if (!m.pageSettingsWidgets[own] && m.pageSettingsWidgets[OWN[own]]) m.pageSettingsWidgets[own] = m.pageSettingsWidgets[OWN[own]];
		});
	}
	function boot() {
		pages();
		var m = wc();
		if (!m || !w.elementor || !w.elementor.channels || w.digiShopPages) return;
		w.digiShopPages = true;
		w.elementor.channels.editor.on('editor:widget:digitalisimo-woo-cart:section_additional_options:activated', function () {
			m.onTemplateIdChange('additional_template_select');
		});
	}
	pages();
	if (w.elementor && w.elementor.channels) boot(); else w.addEventListener('elementor/init', boot, { once: true });
}(window));
