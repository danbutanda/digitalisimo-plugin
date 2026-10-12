<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Título del panel del Carrito de compras (menú del carrito): un encabezado opcional sobre los productos
 * del panel lateral o desplegable. Un carrito sin título se pinta igual que antes.
 */
final class Cart_Title {
	public static function init() {
		add_action( 'elementor/element/before_section_end', array( __CLASS__, 'before_section_end' ), 10, 2 );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 10, 2 );
	}

	/** Se reconoce por su clase, también cuando se registra con otro nombre sobre la misma clase. */
	private static function applies( $widget ) {
		return $widget instanceof \ElementorPro\Modules\Woocommerce\Widgets\Menu_Cart || ( is_object( $widget ) && method_exists( $widget, 'get_name' ) && 'woocommerce-menu-cart' === $widget->get_name() );
	}

	public static function before_section_end( $element, $section_id ) {
		if ( 'section_cart' === $section_id && self::applies( $element ) ) {
			$element->add_control( 'digitalisimo_cart_title', array( 'label' => 'Título del panel', 'type' => \Elementor\Controls_Manager::TEXT, 'dynamic' => array( 'active' => true ), 'description' => 'Encabezado sobre los productos del carrito.' ) );
		}
	}

	/** Inserta el título antes del contenido del carrito; sin título o sin panel, el HTML queda igual. */
	public static function insert( $content, $title ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );
		if ( '' === $title ) {
			return $content;
		}
		return (string) preg_replace( '#<div class="widget_shopping_cart_content">#', '<p class="digi-cart-title">' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), esc_html( $title ) ) . '</p>$0', (string) $content, 1 );
	}

	public static function render_content( $content, $widget ) {
		return self::applies( $widget ) ? self::insert( $content, $widget->get_settings_for_display( 'digitalisimo_cart_title' ) ) : $content;
	}
}
