<?php
namespace Digitalisimo\Elements\Woo\Single;

defined( 'ABSPATH' ) || exit;

/**
 * Un solo H1 en las fichas de producto (B2 de la auditoría de digitalisimo.lat, 2026-11-24): las siete fichas tenían dos
 * H1, el título de WooCommerce (`<h1 class="product_title entry-title">` de su plantilla, o el widget «Título del
 * producto», que es H1 por defecto) y el del encabezado del sitio, un Encabezado con la etiqueta dinámica «Título de la
 * página» en H1 para que cada página tenga el suyo.
 *
 * Decisión: **opcional por elemento**, nunca automática. Los encabezados derivados del Encabezado del núcleo (`WIDGETS`:
 * Encabezado, Título de la página, Título de la entrada y los dos Títulos del producto) ganan el control «Etiqueta en
 * las fichas de producto» (`CONTROL`), vacío por defecto: un elemento que no lo elige se pinta exactamente igual que
 * antes en todas partes. Si se elige (H2…H6, p, div o span), sólo en una ficha de producto (`is_product()`, en el front,
 * nunca en el editor) ese elemento usa esa etiqueta en lugar de la suya; en las demás páginas sigue igual. Así el dueño
 * decide cuál de los dos títulos cede el H1 (lo normal: el del encabezado del sitio, porque WooCommerce ya pinta el suyo)
 * y nada se adivina: no se lee ni se reescribe el HTML de otros, ni se tocan las plantillas de WooCommerce.
 *
 * Con la opción elegida, el elemento es dinámico para la caché de elementos (`elementor/element/is_dynamic_content`): su
 * etiqueta depende de la página. Un elemento con «Caché del elemento: sí» forzado se cachea igual (lo decide el núcleo
 * antes del filtro), como ya le pasaría al título dinámico de la página.
 *
 * Sólo arranca con WooCommerce compatible (`Woo\Module::BOOT`). Nada de `elementor/` se edita.
 */
final class Single_H1 {
	const CONTROL = 'digitalisimo_product_tag';

	/** Encabezados con `header_size` en `section_title` (heredan el Encabezado del núcleo). */
	const WIDGETS = array( 'heading', 'theme-page-title', 'theme-post-title', 'woocommerce-product-title', 'digitalisimo-woo-product-title' );

	/** Etiquetas que se pueden elegir para la ficha de producto (nunca otro H1). */
	const TAGS = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' );

	const LABEL = 'Etiqueta en las fichas de producto';

	const HELP = 'Para que la ficha de un producto tenga un solo H1. Si este título es H1 y la ficha ya tiene el suyo (el de WooCommerce o el widget «Título del producto»), elige aquí H2 o p: sólo en las fichas de producto este elemento usará esa etiqueta; en las demás páginas sigue igual. Vacío: igual en todas partes.';

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		add_action( 'elementor/element/before_section_end', array( __CLASS__, 'register_control' ), 10, 3 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render' ), 10, 1 );
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic_content' ), 10, 2 );
	}

	/** El control, justo después de «Etiqueta HTML» (`header_size`) en la sección del título. */
	public static function register_control( $element, $section_id = '', $args = array() ): void {
		if ( 'section_title' !== $section_id || ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! in_array( (string) $element->get_name(), self::WIDGETS, true ) ) {
			return;
		}
		$options = array( '' => 'La misma que en las demás páginas' );
		foreach ( self::TAGS as $tag ) {
			$options[ $tag ] = strtoupper( $tag );
		}
		$injected = false;
		if ( method_exists( $element, 'get_controls' ) && method_exists( $element, 'start_injection' ) && $element->get_controls( 'header_size' ) ) {
			$element->start_injection(
				array(
					'type' => 'control',
					'at'   => 'after',
					'of'   => 'header_size',
				)
			);
			$injected = true;
		}
		$element->add_control(
			self::CONTROL,
			array(
				'label'       => self::LABEL,
				'type'        => 'select',
				'default'     => '',
				'options'     => $options,
				'description' => self::HELP,
			)
		);
		if ( $injected ) {
			$element->end_injection();
		}
	}

	/** La etiqueta elegida, o '' si el elemento no la eligió o trae otra cosa. */
	public static function chosen( $element ): string {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) || ! method_exists( $element, 'get_settings' ) || ! in_array( (string) $element->get_name(), self::WIDGETS, true ) ) {
			return '';
		}
		$tag = $element->get_settings( self::CONTROL );
		return is_string( $tag ) && in_array( $tag, self::TAGS, true ) ? $tag : '';
	}

	/** ¿Es la ficha de un producto en el front (no el editor ni su vista previa)? */
	public static function single_product(): bool {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}
		if ( class_exists( '\Elementor\Plugin', false ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				return false;
			}
			if ( isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				return false;
			}
		}
		return true;
	}

	/** `elementor/frontend/widget/before_render`: en una ficha de producto, la etiqueta elegida en lugar de la suya. */
	public static function before_render( $element ): void {
		$tag = self::chosen( $element );
		if ( '' === $tag || ! method_exists( $element, 'set_settings' ) || ! self::single_product() ) {
			return;
		}
		if ( $tag === $element->get_settings( 'header_size' ) ) {
			return;
		}
		$element->set_settings( 'header_size', $tag );
		if ( method_exists( $element, 'reset_render_state' ) ) {
			$element->reset_render_state();
		}
	}

	/** Con la opción elegida, la salida depende de la página: fuera de la caché de elementos. */
	public static function is_dynamic_content( $is_dynamic, $raw = array() ) {
		if ( $is_dynamic || ! is_array( $raw ) || ! isset( $raw['widgetType'] ) || ! in_array( $raw['widgetType'], self::WIDGETS, true ) ) {
			return $is_dynamic;
		}
		$tag = $raw['settings'][ self::CONTROL ] ?? '';
		return is_string( $tag ) && in_array( $tag, self::TAGS, true ) ? true : $is_dynamic;
	}
}
