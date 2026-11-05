<?php
namespace Digitalisimo\Elements\Woo\Cart;

use Digitalisimo\Elements\Woo\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Rasgo opt-in del Menú del carrito de PRO (D25, V.7.3): la barra de envío gratis y el cupón dentro del panel.
 *
 * - Interruptores `digitalisimo_cart_bar` y `digitalisimo_cart_coupon` **sin `default`** (vacío = apagado); sus textos
 *   y estilos sólo aparecen con su interruptor y ningún control de estilo lleva `default` (D33): un Menú del carrito que
 *   no los activa genera el mismo CSS y el mismo HTML que antes.
 * - Recursos por la declaración `assets` de los interruptores (V.12.2): llegan también con la caché de elementos.
 * - Orden fijo dentro de `.elementor-menu-cart__main`: el título de `Cart_Title`, la barra y el cupón «Arriba» (delante
 *   de `div.widget_shopping_cart_content`); «Abajo», justo antes del cierre de `.elementor-menu-cart__main`, nunca detrás
 *   del contenido del carrito, que en el editor no es un ancla fiable.
 */
final class Mini_Cart_Extras {
	const BAR      = 'digitalisimo_cart_bar';
	const COUPON   = 'digitalisimo_cart_coupon';
	const POSITION = 'digitalisimo_cart_extras_position';
	const PROGRESS = 'digitalisimo_cart_bar_progress';
	const DONE     = 'digitalisimo_cart_bar_done';
	const STYLE    = 'digitalisimo_section_cart_extras_style';

	/** @var int Instancias pintadas en esta petición, para un `uid` único por formulario. */
	private static $count = 0;

	/** Para las pruebas: cada petición empieza a contar instancias desde cero. */
	public static function reset(): void {
		self::$count = 0;
	}

	public static function init(): void {
		add_action( 'elementor/element/before_section_end', array( __CLASS__, 'before_section_end' ), 10, 2 );
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'after_section_end' ), 10, 2 );
		// Después de Cart_Title (10): su título queda delante de la barra y del cupón.
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'render_content' ), 11, 2 );
	}

	/** Se reconoce por su clase, también cuando se registra con otro nombre sobre la misma clase. */
	public static function applies( $widget ): bool {
		return $widget instanceof \ElementorPro\Modules\Woocommerce\Widgets\Menu_Cart || ( is_object( $widget ) && method_exists( $widget, 'get_name' ) && 'woocommerce-menu-cart' === $widget->get_name() );
	}

	/** Recursos de la capacidad, sólo si el interruptor está en `yes`. */
	private static function assets( string $control ): array {
		$handle     = Assets::handle( 'cart-extras' );
		$conditions = array( 'terms' => array( array( 'name' => $control, 'operator' => '===', 'value' => 'yes' ) ) );
		return array(
			'styles'  => array( array( 'name' => $handle, 'conditions' => $conditions ) ),
			'scripts' => array( array( 'name' => $handle, 'conditions' => $conditions ) ),
		);
	}

	private static function either(): array {
		return array(
			'relation' => 'or',
			'terms'    => array(
				array( 'name' => self::BAR, 'operator' => '===', 'value' => 'yes' ),
				array( 'name' => self::COUPON, 'operator' => '===', 'value' => 'yes' ),
			),
		);
	}

	public static function before_section_end( $element, $section_id ): void {
		if ( 'section_cart' !== $section_id || ! self::applies( $element ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( self::BAR ) ) ) {
			return;
		}
		$c = '\\Elementor\\Controls_Manager';
		$element->add_control( 'digitalisimo_cart_extras_heading', array( 'label' => 'Envío gratis y cupón', 'type' => $c::HEADING, 'separator' => 'before' ) );
		$element->add_control( self::BAR, array( 'label' => 'Barra de envío gratis', 'type' => $c::SWITCHER, 'description' => 'Cuánto falta para el envío gratis, con las tarifas reales de WooCommerce. Si no se puede saber, no aparece.', 'assets' => self::assets( self::BAR ) ) );
		$element->add_control( self::PROGRESS, array( 'label' => 'Texto con lo que falta', 'type' => $c::TEXT, 'label_block' => true, 'placeholder' => Markup::T_PROGRESS, 'description' => '{restante} es lo que falta y {umbral}, el mínimo del envío gratuito.', 'condition' => array( self::BAR => 'yes' ) ) );
		$element->add_control( self::DONE, array( 'label' => 'Texto con envío gratis', 'type' => $c::TEXT, 'label_block' => true, 'placeholder' => Markup::T_DONE, 'condition' => array( self::BAR => 'yes' ) ) );
		$element->add_control( self::COUPON, array( 'label' => 'Cupón', 'type' => $c::SWITCHER, 'description' => 'Aplicar o quitar un cupón sin salir del panel. Sólo aparece si los cupones están activos en WooCommerce.', 'assets' => self::assets( self::COUPON ) ) );
		$element->add_control( self::POSITION, array( 'label' => 'Posición', 'type' => $c::SELECT, 'options' => array( '' => 'Arriba', 'bottom' => 'Abajo' ), 'conditions' => self::either() ) );
	}

	/** Estilos propios, en una sección aparte tras la última de estilo del widget, visible sólo con un interruptor. */
	public static function after_section_end( $element, $section_id ): void {
		if ( 'section_style_messages' !== $section_id || ! self::applies( $element ) || ( method_exists( $element, 'get_controls' ) && null !== $element->get_controls( 'digitalisimo_cart_extras_text' ) ) ) {
			return;
		}
		$c = '\\Elementor\\Controls_Manager';
		$element->start_controls_section( self::STYLE, array( 'label' => 'Envío gratis y cupón', 'tab' => $c::TAB_STYLE, 'conditions' => self::either() ) );
		$element->add_control( 'digitalisimo_cart_extras_text', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-cart-extras' => 'color: {{VALUE}};' ) ) );
		$element->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'digitalisimo_cart_extras_typography', 'selector' => '{{WRAPPER}} .digi-cart-extras' ) );
		$element->add_control( 'digitalisimo_cart_bar_fill', array( 'label' => 'Color de la barra', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-ship' => '--digi-ship-fill: {{VALUE}};' ), 'condition' => array( self::BAR => 'yes' ) ) );
		$element->add_control( 'digitalisimo_cart_bar_track', array( 'label' => 'Fondo de la barra', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-ship' => '--digi-ship-track: {{VALUE}};' ), 'condition' => array( self::BAR => 'yes' ) ) );
		$element->add_control( 'digitalisimo_cart_extras_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .digi-cart-extras' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$element->end_controls_section();
	}

	/**
	 * Inserta los envoltorios en el panel. Sin los interruptores, sin panel o sin las anclas de PRO, el HTML queda igual.
	 *
	 * @param string $content Salida del widget.
	 */
	public static function insert( $content, array $settings, string $uid, bool $editor = false ): string {
		$content = (string) $content;
		$bar     = 'yes' === ( $settings[ self::BAR ] ?? '' );
		$coupon  = 'yes' === ( $settings[ self::COUPON ] ?? '' );
		if ( ( ! $bar && ! $coupon ) || false === strpos( $content, 'elementor-menu-cart__main' ) ) {
			return $content;
		}
		$extras = '<div class="digi-cart-extras">';
		if ( $bar ) {
			$extras .= Markup::bar( (string) ( $settings[ self::PROGRESS ] ?? '' ), (string) ( $settings[ self::DONE ] ?? '' ), $editor );
		}
		if ( $coupon ) {
			$extras .= Markup::coupon( $uid, $editor );
		}
		$extras  .= '</div>';
		$safe     = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $extras );
		$position = 'bottom' === ( $settings[ self::POSITION ] ?? '' ) ? 'bottom' : 'top';
		if ( 'bottom' === $position ) {
			// La primera </div> cierra .elementor-menu-cart__main; la segunda, su contenedor; después va el botón.
			return (string) preg_replace( '#</div>(\s*</div>\s*<div class="elementor-menu-cart__toggle elementor-button-wrapper">)#', $safe . '</div>$1', $content, 1 );
		}
		return (string) preg_replace( '#<div class="widget_shopping_cart_content">#', $safe . '$0', $content, 1 );
	}

	public static function render_content( $content, $widget ) {
		if ( ! self::applies( $widget ) ) {
			return $content;
		}
		$settings = (array) $widget->get_settings_for_display();
		if ( 'yes' !== ( $settings[ self::BAR ] ?? '' ) && 'yes' !== ( $settings[ self::COUPON ] ?? '' ) ) {
			return $content;
		}
		$uid    = ( method_exists( $widget, 'get_id' ) ? (string) $widget->get_id() : 'menu' ) . ( self::$count++ ? '-' . self::$count : '' );
		$editor = isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
		return self::insert( $content, $settings, $uid, $editor );
	}
}
