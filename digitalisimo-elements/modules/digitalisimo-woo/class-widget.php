<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los siete widgets `digitalisimo-woo-*` (contrato del frente A).
 *
 * Una subclase implementa `get_name()`, `get_title()`, `get_icon()`, `register_controls()`, `get_style_depends()`/
 * `get_script_depends()` y **`render_widget()`** (no `render()`), y no define `content_template()`. Así, sin
 * WooCommerce, `Inert` registra la misma clase con los mismos controles (guardar no borra sus datos, V.10.1), oculta del
 * panel y con el aviso «Necesita WooCommerce» en lugar de su salida; `render_widget()` nunca se llama sin WooCommerce.
 *
 * Reglas de la subclase: `register_controls()` no llama a funciones de WooCommerce (las opciones que dependen de
 * WooCommerce se construyen sólo con WooCommerce y si `! Performance::should_optimize_controls()`, V.12.1); el HTML no
 * depende del visitante (D21); ningún `<script>` ejecutable, ninguna imagen fuera de `Image_Markup`.
 */
abstract class Widget extends \Elementor\Widget_Base {
	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'woocommerce', 'tienda', 'digitalisimo' );
	}

	/** Oculto del panel en el modo `Inert` (sin WooCommerce). */
	public function show_in_panel() {
		return ! Inert::active();
	}

	/** Sin WooCommerce, el aviso (sólo en el editor y su vista previa); con él, `render_widget()`. */
	protected function render() {
		if ( Inert::active() ) {
			echo Inert::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texto propio escapado.
			return;
		}
		$this->render_widget();
	}

	/** Salida del widget con WooCommerce. */
	protected function render_widget() {}
}
