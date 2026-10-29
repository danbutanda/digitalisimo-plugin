<?php
namespace Digitalisimo\Elements\Woo\Filters;

use Digitalisimo\Elements\Woo\Widget;

defined( 'ABSPATH' ) || exit;

if ( ! trait_exists( '\Digitalisimo\Elements\Filters\Filter_Form', false ) ) {
	require_once dirname( __DIR__, 2 ) . '/digitalisimo-filters/class-filter-form.php';
}

/**
 * Base de los filtros de precio y de atributo (F4-Woo, v1 §5.5 y V.8). Sigue el contrato de `Woo\Widget`: sólo
 * `render_widget()`, y `register_controls()` sin funciones de WooCommerce (las opciones que dependen de él se construyen
 * con WooCommerce y fuera de la pila optimizada del front, V.12.1).
 *
 * - **Destino** «Loop Grid de este documento» (por defecto) o «Tienda». En el primero los nombres son
 *   `e-filter-{bucle}-{nombre}`: el servidor los lee al pintar (sin JS, al recargar) y el coordinador los manda en el
 *   refresco. En «Tienda», los parámetros nativos de WooCommerce, que sólo filtran la consulta principal (V.8.5).
 * - **Marcado:** un `<form method="get">` con su `fieldset` y `legend`, un `<span class="e-filter" hidden>` con los mismos
 *   `data-base-url` y `data-page-num` que pinta el filtro de PRO (V.8.3), campos ocultos con los demás parámetros
 *   vigentes (V.8.6, V-M11) y enlaces «Quitar filtro»/«Limpiar» con `rel="nofollow"` (V.13).
 * - El HTML no depende del visitante (sólo de la URL): una página filtrada es otra entrada de la caché de página.
 *
 * Lo común (estilo, bucle, URL, formulario) vive en el rasgo neutro `Filters\Filter_Form` (F7b, V.5 y C1-13), con el
 * mismo código: esta clase sigue extendiendo `Woo\Widget`, como exige `Inert::cooperates()`.
 */
abstract class Filter_Widget extends Widget {
	use \ElementorPro\Modules\Posts\Traits\Pagination_Trait;
	use \Digitalisimo\Elements\Filters\Filter_Form;

	const HANDLE      = 'digitalisimo-woo-filters';
	const FORM_HANDLE = 'digitalisimo-woo-filters-form';

	/** Parámetros de paginación que un filtro nuevo descarta. */
	const PAGINATION = array( 'paged', 'page', 'product-page' );

	public function get_style_depends() {
		return array( 'digitalisimo-woo-filters' );
	}

	public function get_script_depends() {
		return array( 'digitalisimo-woo-filters', 'digitalisimo-woo-filters-form' );
	}

	public function get_keywords() {
		return array( 'filtro', 'woocommerce', 'tienda', 'loop grid', 'digitalisimo' );
	}

	/** Destino, bucle y los avisos del editor, al principio de la sección `section_filter`. */
	protected function register_target_controls(): void {
		$c = '\\Elementor\\Controls_Manager';
		$this->add_control( 'destination', array( 'label' => 'Destino', 'type' => $c::SELECT, 'default' => 'loop', 'options' => array( 'loop' => 'Loop Grid de este documento', 'shop' => 'Tienda' ) ) );
		$this->add_control( 'selected_element', array( 'label' => 'Loop Grid', 'type' => $c::SELECT, 'options' => array( '' => 'Elige un Loop Grid' ), 'label_block' => true, 'condition' => array( 'destination' => 'loop' ) ) );
		$this->add_control( 'loop_note', array( 'type' => $c::RAW_HTML, 'raw' => 'Filtra un Loop Grid de este documento sin recargar la página. El Loop Carousel no se puede filtrar, y un Loop Grid con «Consulta actual» tampoco: dale su propia consulta de productos.', 'content_classes' => 'elementor-descriptor', 'condition' => array( 'destination' => 'loop' ) ) );
		$this->add_control( 'shop_note', array( 'type' => $c::RAW_HTML, 'raw' => 'Filtra la consulta principal de la tienda con los parámetros de WooCommerce: el archivo de productos, el widget Productos con «Consulta actual» y las plantillas de la tienda. Este destino no filtra un Loop Grid; usa el destino Loop Grid. Uno con «Consulta actual» a veces hereda un atributo de la consulta principal, nunca el precio: no cuentes con ello.', 'content_classes' => 'elementor-descriptor', 'condition' => array( 'destination' => 'shop' ) ) );
	}

	/** `loop` o `shop`. */
	protected function destination(): string {
		return 'shop' === $this->get_settings_for_display( 'destination' ) ? 'shop' : 'loop';
	}

	/** Textos de la región viva del coordinador. */
	protected function status_texts(): array {
		return array( 'updated' => 'Resultados actualizados', 'none' => 'Ningún producto con estos filtros', 'one' => '1 producto', 'many' => '%d productos' );
	}

	/** Motivo con el que se piden los paquetes de PRO (`Pro_Gate::require()`). */
	protected function gate_reason(): string {
		return 'woo-filter';
	}
}
