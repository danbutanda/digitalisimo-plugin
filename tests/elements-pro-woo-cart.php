<?php
/**
 * Carrito (`digitalisimo-woo-cart`), el widget propio que sustituye al «Cart» de PRO (C7 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c7.md, fila O), y su mapa `maps/woocommerce-cart.php`:
 * - los controles de PRO y los propios cargados a la vez (las clases reales de PRO y de DIGITALÍSIMO, sobre un Elementor
 *   y un WordPress mínimos): mismo nombre, orden, tipo, valor por defecto (salvo los textos, en español), opciones,
 *   condiciones, rangos, prefijos y selectores (con las clases y las variables propias);
 * - el mismo carrito: con un `[woocommerce_cart]` de prueba que llama a los ganchos de las plantillas de WooCommerce, los
 *   dos pintan la misma estructura (columnas, secciones, cupón propio, totales), con los textos de los controles por
 *   `gettext`, sin cupón cuando se oculta o WooCommerce lo desactiva, con la plantilla del carrito vacío y sin ventas
 *   cruzadas; los ganchos se quitan al terminar;
 * - recursos: dos hojas de ≤ 4 KB sin la paleta del kit ni url(), con foco visible y movimiento reducido, y un script
 *   ≤ 8 KB sin jQuery; ningún nombre prohibido (scripts/build-packages.sh) ni frase con marcas ajenas;
 * - mapa: declara todos los controles de PRO, sólo nombra controles propios y convierte ajustes reales (por defecto,
 *   estilos, globales, dinámicos, dispositivos, CSS personalizado) sin `pro.unmapped`.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	$GLOBALS['c_wp'] = array( 'hooks' => array(), 'coupons' => true, 'count' => 2, 'edit' => false );
	function c_check( $ok, $message ) {
		if ( ! $ok ) {
			echo "FALLA: $message\n";
			exit( 1 );
		}
		$GLOBALS['c_wp']['checks'] = ( $GLOBALS['c_wp']['checks'] ?? 0 ) + 1;
	}
	function esc_html__( $t, $d = '' ) { return $t; }
	function esc_attr__( $t, $d = '' ) { return $t; }
	function __( $t, $d = '' ) { return $t; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $t ) { return (string) $t; }
	function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
	function get_option( $k, $d = false ) { return $d; }
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
	function plugins_url( $p, $f ) { return 'https://sitio.test/' . $p; }
	function wp_style_is( $h, $l ) { return false; }
	function wp_script_is( $h, $l ) { return false; }
	function wp_register_style( ...$a ) { $GLOBALS['c_wp']['styles'][ $a[0] ] = $a; }
	function wp_register_script( ...$a ) { $GLOBALS['c_wp']['scripts'][ $a[0] ] = $a; }
	function wc_coupons_enabled() { return $GLOBALS['c_wp']['coupons']; }
	function is_cart() { return true; }
	function is_preview() { return false; }
	function get_permalink( $id ) { return 'https://sitio.test/carrito/'; }
	function intval_s( $v ) { return (int) $v; }
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['c_wp']['hooks'][ $hook ][ $prio ][] = $cb; return true; }
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return add_filter( $hook, $cb, $prio, $args ); }
	function remove_filter( $hook, $cb, $prio = 10 ) {
		foreach ( $GLOBALS['c_wp']['hooks'][ $hook ] ?? array() as $p => $list ) {
			foreach ( $list as $i => $f ) {
				if ( $f == $cb ) { // phpcs:ignore Universal.Operators.StrictComparisons -- callables iguales.
					unset( $GLOBALS['c_wp']['hooks'][ $hook ][ $p ][ $i ] );
				}
			}
		}
		return true;
	}
	function remove_action( $hook, $cb, $prio = 10 ) { return remove_filter( $hook, $cb, $prio ); }
	function apply_filters( $hook, $value, ...$args ) {
		$list = $GLOBALS['c_wp']['hooks'][ $hook ] ?? array();
		ksort( $list );
		foreach ( $list as $fns ) {
			foreach ( $fns as $fn ) {
				$value = call_user_func_array( $fn, array_merge( array( $value ), $args ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$list = $GLOBALS['c_wp']['hooks'][ $hook ] ?? array();
		ksort( $list );
		foreach ( $list as $fns ) {
			foreach ( $fns as $fn ) {
				call_user_func_array( $fn, $args );
			}
		}
	}
	function c_t( $text ) { return apply_filters( 'gettext', $text, $text, 'woocommerce' ); }
	/** `[woocommerce_cart]` de prueba: la secuencia de ganchos de las plantillas `cart/cart.php`, `cart-totals.php` y vacío. */
	function do_shortcode( $code ) {
		if ( preg_match( '/elementor-template id="(\d+)"/', $code, $m ) ) {
			return '<div class="plantilla-' . $m[1] . '">Plantilla</div>';
		}
		ob_start();
		echo '<div class="woocommerce">';
		if ( 0 === $GLOBALS['c_wp']['count'] ) {
			do_action( 'woocommerce_cart_is_empty' );
			echo '</div>';
			return ob_get_clean();
		}
		do_action( 'woocommerce_before_cart' );
		echo '<form class="woocommerce-cart-form">';
		do_action( 'woocommerce_before_cart_table' );
		echo '<table class="shop_table cart"><tbody>';
		do_action( 'woocommerce_cart_contents' );
		echo '<tr><td class="actions">';
		if ( wc_coupons_enabled() ) {
			echo '<div class="coupon">WC</div>';
		}
		echo '<button name="update_cart">' . c_t( 'Update cart' ) . '</button></td></tr>';
		do_action( 'woocommerce_after_cart_contents' );
		echo '</tbody></table>';
		do_action( 'woocommerce_after_cart_table' );
		echo '</form>';
		do_action( 'woocommerce_before_cart_collaterals' );
		echo '<div class="cart-collaterals">';
		do_action( 'woocommerce_cart_collaterals' );
		echo '<div class="cart_totals"><h2>' . c_t( 'Cart totals' ) . '</h2>' . apply_filters( 'woocommerce_shipping_estimate_html', 'Estimación' ) . '<button>' . c_t( 'Update' ) . '</button><a class="checkout-button">' . c_t( 'Proceed to checkout' ) . '</a></div></div>';
		do_action( 'woocommerce_after_cart' );
		echo '</div>';
		return ob_get_clean();
	}
	function wc_empty_cart_message() { echo '<p class="cart-empty">Vacío</p>'; }
	function woocommerce_cross_sell_display() { echo '<div class="cross-sells">Ventas cruzadas</div>'; }
	class C_Cart { public function get_cart_contents_count() { return $GLOBALS['c_wp']['count']; } }
	class C_WC { public $cart; public function __construct() { $this->cart = new C_Cart(); } }
	function WC() { static $wc = null; return $wc ?: ( $wc = new C_WC() ); }
}

namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $order    = array();
		public $attrs    = array();
		private $section = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function start_controls_section( $name, $args = array() ) { $this->section = $name; $this->order[] = '#' . $name . json_encode( $args['condition'] ?? null ); }
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->order[] = '[' . $name; }
		public function start_controls_tab( $name, $args = array() ) { $this->order[] = '(' . $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args, $options = array() ) { $this->controls[ $name ] = $args + array( 'section' => $this->section ); $this->order[] = $name; return true; }
		public function add_responsive_control( $name, $args, $options = array() ) { return $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args = array(), $options = array() ) { $this->groups[ $args['name'] ] = array( 'type' => $type, 'section' => $this->section ) + $args; $this->order[] = '@' . $args['name']; }
		public function add_render_attribute( $element, $key = null, $value = null ) {
			foreach ( is_array( $key ) ? $key : array( $key => $value ) as $k => $v ) {
				foreach ( (array) $v as $x ) {
					$this->attrs[ $element ][ $k ][] = $x;
				}
			}
		}
		public function print_render_attribute_string( $element ) {
			$out = array();
			foreach ( $this->attrs[ $element ] ?? array() as $k => $v ) {
				$out[] = $k . '="' . htmlspecialchars( implode( ' ', $v ), ENT_QUOTES ) . '"';
			}
			echo implode( ' ', $out ); // phpcs:ignore
		}
		public function get_name() { return ''; }
	}
	class Controls_Manager {
		const TAB_CONTENT = 'content'; const TAB_STYLE = 'style'; const SELECT = 'select'; const SWITCHER = 'switcher'; const NUMBER = 'number';
		const HEADING = 'heading'; const TEXT = 'text'; const CHOOSE = 'choose'; const RAW_HTML = 'raw_html'; const COLOR = 'color';
		const SLIDER = 'slider'; const DIMENSIONS = 'dimensions'; const SELECT2 = 'select2'; const HOVER_ANIMATION = 'hover_animation';
		const DIVIDER = 'divider'; const ALERT = 'alert';
	}
	class Controls_Stack { const RESPONSIVE_DESKTOP = 'desktop'; const RESPONSIVE_TABLET = 'tablet'; }
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class C_Mode { public function is_edit_mode() { return $GLOBALS['c_wp']['edit']; } public function is_preview_mode() { return false; } }
	class C_Docs { public function get_document_types( $args = array() ) { return array( 'section' => 1, 'container' => 1 ); } }
	class Plugin {
		public static $instance;
		public $editor;
		public $preview;
		public $documents;
		public function __construct() { $this->editor = new C_Mode(); $this->preview = new C_Mode(); $this->documents = new C_Docs(); }
	}
	Plugin::$instance = new Plugin();
}
namespace Elementor\Core\Base { class Document { const TYPE_META_KEY = '_elementor_template_type'; } }
namespace Elementor\Core\Breakpoints { class Manager { public static function get_default_config() { return array( 'mobile' => array(), 'tablet' => array() ); } } }
namespace ElementorPro { class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } } }
namespace ElementorPro\Base { abstract class Base_Widget extends \Elementor\Widget_Base {} }
namespace ElementorPro\Modules\QueryControl { class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_LIBRARY_TEMPLATE = 'library_template'; } }
namespace ElementorPro\Modules\Woocommerce { class Module { public static function is_preview() { return false; } } }
namespace Digitalisimo\Elements\Woo { final class Inert { public static function active(): bool { return false; } public static function notice(): string { return ''; } } }

namespace {
	$el = dirname( __DIR__ ) . '/digitalisimo-elements/';
	require $el . 'modules/woocommerce/traits/product-id-trait.php';
	require $el . 'modules/woocommerce/widgets/base-widget.php';
	require $el . 'modules/woocommerce/widgets/cart.php';
	require $el . 'modules/digitalisimo-woo/class-module.php';
	require $el . 'modules/digitalisimo-woo/class-assets.php';
	require $el . 'modules/digitalisimo-woo/class-registry.php';
	require $el . 'modules/digitalisimo-woo/class-widget.php';
	require $el . 'modules/digitalisimo-woo/store/class-base.php';
	require $el . 'modules/digitalisimo-woo/store/class-cart.php';
	require $el . 'modules/digitalisimo-woo/store/class-cart-ajax.php';

	$pro = new \ElementorPro\Modules\Woocommerce\Widgets\Cart();
	$own = new \Digitalisimo\Elements\Woo\Store\Cart();
	$reg = function ( $w ) { $m = new ReflectionMethod( $w, 'register_controls' ); $m->setAccessible( true ); $m->invoke( $w ); };
	$reg( $pro );
	$reg( $own );

	// Correspondencia de clases y variables de PRO → propias (la misma del generador del widget y de las hojas).
	$classes = array( 'e-cart__column-start' => 'digi-cart__column--start', 'e-cart__column-end' => 'digi-cart__column--end', 'e-cart__column-inner' => 'digi-cart__column-inner', 'e-cart__container' => 'digi-cart__container', 'e-cart__column' => 'digi-cart__column', 'e-sticky-right-column' => 'digi-cart__sticky', 'e-cart-section--no-coupon' => 'digi-cart__section--no-coupon', 'e-cart-section' => 'digi-cart__section', 'e-cart-totals' => 'digi-cart__totals', 'e-cart-content' => 'digi-cart__content', 'e-shop-table' => 'digi-cart__table', 'e-clear' => 'digi-cart__clear', 'e-apply-coupon' => 'digi-cart__apply-coupon', 'e-cart-layout-' => 'digi-cart-layout-', 'e-cart-empty-template-' => 'digi-cart-empty-template-', 'e-woo-select2-wrapper' => 'digi-cart-select2' );
	$pre = array( 'update-cart-automatically' => 'uca', 'update-shipping-button' => 'usb', 'update-cart-button' => 'ucb', 'apply-coupon-button' => 'acb', 'checkout-button' => 'cb', 'forms-fields' => 'ff', 'forms-buttons' => 'fb', 'forms' => 'f', 'order-summary' => 'os', 'sections' => 's', 'totals' => 't', 'product-link' => 'pl', 'links' => 'l', 'place-order-title' => 'pot' );
	$suf = array( 'background-color' => 'bg', 'border-color' => 'bc', 'border-radius' => 'br', 'border-type' => 'bt', 'descriptions-color' => 'dc', 'descriptions-spacing' => 'ds', 'margin' => 'm', 'padding' => 'p', 'radio-buttons-color' => 'rc', 'title-color' => 'tc', 'title-spacing' => 'ts', 'normal-color' => 'n', 'focus-color' => 'fc', 'focus-border-color' => 'fbc', 'focus-transition-duration' => 'ftd', 'hover-border-color' => 'hbc', 'hover-text-color' => 'htc', 'hover-transition-duration' => 'htd', 'hover-background-color' => 'hbg', 'normal-text-color' => 'ntc', 'width' => 'w', 'alignment' => 'a', 'color' => 'c', 'items-divider-color' => 'idc', 'items-divider-weight' => 'idw', 'quantity-border-color' => 'qbc', 'quantity-border-weight' => 'qbw', 'remove-icon-hover-color' => 'rhc', 'remove-icon-normal-color' => 'rnc', 'rows-gap-bottom' => 'rgb', 'rows-gap-top' => 'rgt', 'variations-color' => 'vc', 'divider-color' => 'dvc', 'divider-weight' => 'dvw', 'rows-gap' => 'rg', 'hover-color' => 'hc', 'display' => 'd', 'title-alignment' => 'ta' );
	$own_css = static function ( $text ) use ( $classes, $pre, $suf ) {
		$text = preg_replace_callback( '/(?<![a-z0-9_-])--(?:sections|forms|order-summary|totals|checkout-button|update-cart|update-shipping-button|apply-coupon-button|product-link|links|place-order-title)[a-z-]*/', static function ( $m ) use ( $pre, $suf ) {
			$n = substr( $m[0], 2 );
			foreach ( $pre as $p => $a ) {
				if ( 0 === strpos( $n, $p . '-' ) && isset( $suf[ substr( $n, strlen( $p ) + 1 ) ] ) ) {
					return '--c' . $a . '-' . $suf[ substr( $n, strlen( $p ) + 1 ) ];
				}
			}
			return $m[0];
		}, $text );
		foreach ( $classes as $a => $b ) {
			$text = preg_replace( '/(?<![a-z0-9_-])' . preg_quote( $a, '/' ) . ( '-' === substr( $a, -1 ) ? '' : '(?![a-z0-9_])' ) . '/', $b, $text );
		}
		return $text;
	};

	// 1. Controles: mismo orden, secciones, pestañas, tipo, valores, condiciones y selectores.
	c_check( $pro->order === $own->order, 'El mismo orden de secciones, pestañas, controles y grupos que PRO.' );
	c_check( 128 === count( $own->controls ) && 35 === count( $own->groups ), 'Los 128 controles y 35 grupos de PRO (' . count( $own->controls ) . ', ' . count( $own->groups ) . ').' );
	$texts = array( 'update_cart_button_text', 'apply_coupon_button_text', 'totals_section_title', 'update_shipping_button_text', 'checkout_button_text' );
	$compare = array( 'type', 'default', 'tablet_default', 'mobile_default', 'condition', 'conditions', 'range', 'size_units', 'min', 'max', 'step', 'separator', 'responsive', 'frontend_available', 'render_type', 'return_value', 'multiple', 'label_block', 'show_label', 'dynamic', 'device_args', 'autocomplete' );
	foreach ( $pro->controls as $name => $p ) {
		$o = $own->controls[ $name ] ?? null;
		c_check( null !== $o, "Falta el control $name." );
		foreach ( $compare as $key ) {
			if ( 'default' === $key && in_array( $name, $texts, true ) ) {
				continue;
			}
			c_check( ( $p[ $key ] ?? null ) === ( $o[ $key ] ?? null ), "$name: «{$key}» distinto de PRO." );
		}
		c_check( array_keys( (array) ( $p['options'] ?? array() ) ) === array_keys( (array) ( $o['options'] ?? array() ) ), "$name: las mismas opciones." );
		c_check( $own_css( (string) ( $p['prefix_class'] ?? '' ) ) === (string) ( $o['prefix_class'] ?? '' ), "$name: el mismo prefijo de clase (propio)." );
		$ps = array();
		foreach ( (array) ( $p['selectors'] ?? array() ) as $sel => $rule ) {
			$ps[ $own_css( $sel ) ] = $own_css( $rule );
		}
		c_check( $ps === (array) ( $o['selectors'] ?? array() ), "$name: selectores equivalentes." );
		c_check( array_map( $own_css, (array) ( $p['selectors_dictionary'] ?? array() ) ) === (array) ( $o['selectors_dictionary'] ?? array() ), "$name: el mismo diccionario de selectores." );
		foreach ( (array) ( $o['selectors'] ?? array() ) as $sel => $rule ) {
			c_check( ! preg_match( '/\be-cart|e-shop-table|elementor-widget-woocommerce|--e-global/', $sel . $rule ), "$name: ninguna clase de PRO ni paleta del kit ($sel)." );
		}
	}
	c_check( 'Actualizar carrito' === $own->controls['update_cart_button_text']['default'] && 'Finalizar compra' === $own->controls['checkout_button_text']['default'], 'Los textos por defecto, en español.' );
	foreach ( $pro->groups as $name => $p ) {
		$o = $own->groups[ $name ] ?? null;
		c_check( null !== $o && $p['type'] === $o['type'] && ( $p['condition'] ?? null ) === ( $o['condition'] ?? null ) && $own_css( (string) ( $p['selector'] ?? '' ) ) === (string) ( $o['selector'] ?? '' ) && ( $p['fields_options'] ?? null ) === ( $o['fields_options'] ?? null ), "Grupo $name equivalente." );
	}

	// 2. Mapa: todo control de PRO declarado; lo que se conserva existe en el propio.
	$map      = require $el . 'modules/digitalisimo-convert/maps/woocommerce-cart.php';
	$declared = array_merge( (array) $map['keep'], array_keys( (array) $map['groups'] ), (array) $map['ui'] );
	c_check( array() === array_diff( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ), $declared ), 'El mapa declara todos los controles de PRO: ' . implode( ', ', array_diff( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ), $declared ) ) );
	c_check( array() === array_diff( (array) $map['keep'], array_keys( $own->controls ) ) && array() === array_diff( array_keys( (array) $map['groups'] ), array_keys( $own->groups ) ), 'El mapa sólo nombra controles propios.' );
	c_check( 'digitalisimo-woo-cart' === $map['target'] && 'woocommerce-cart' === $map['source'] && 'hide' === $map['panel'], 'Origen, destino y panel del mapa.' );
	foreach ( $texts as $key ) {
		c_check( $pro->controls[ $key ]['default'] === $map['defaults'][ $key ], "El mapa completa el texto por defecto de PRO en $key." );
	}

	// 3. Pintado: la misma estructura que PRO, con las clases propias, dentro de `div.digi-cart`.
	$defaults = static function ( $w ) {
		$out = array();
		foreach ( $w->controls as $name => $c ) {
			if ( array_key_exists( 'default', $c ) ) {
				$out[ $name ] = $c['default'];
			}
		}
		foreach ( array( 'forms_buttons_hover_animation', 'checkout_button_hover_animation', 'additional_template_select' ) as $k ) {
			$out[ $k ] = $out[ $k ] ?? '';
		}
		return $out;
	};
	$render = static function ( $w, array $settings ) {
		$w->settings = $settings;
		$w->attrs    = array();
		$m           = new ReflectionMethod( $w, $w instanceof \Digitalisimo\Elements\Woo\Store\Cart ? 'render_widget' : 'render' );
		$m->setAccessible( true );
		ob_start();
		$m->invoke( $w );
		$html = ob_get_clean();
		// `gettext` lee los ajustes una vez: se vuelve a calcular en cada pintado.
		$p = new ReflectionProperty( $w, 'gettext_modifications' );
		$p->setAccessible( true );
		$p->setValue( $w, null );
		return $html;
	};
	$hooks_before = $GLOBALS['c_wp']['hooks'];
	$cases        = array(
		'por defecto'      => array( array(), true, 2 ),
		'textos propios'   => array( array( 'update_cart_button_text' => 'Recalcular', 'checkout_button_text' => 'Pagar ya', 'totals_section_title' => 'Resumen', 'update_shipping_button_text' => 'Cambiar', 'apply_coupon_button_text' => 'Usar' ), true, 2 ),
		'sin cupón'        => array( array( 'section_coupon_display' => '' ), true, 2 ),
		'cupones apagados' => array( array(), false, 2 ),
		'animación'        => array( array( 'forms_buttons_hover_animation' => 'grow' ), true, 2 ),
		'vacío'            => array( array(), true, 0 ),
		'vacío plantilla'  => array( array( 'additional_template_switch' => 'active', 'additional_template_select' => '42' ), true, 0 ),
	);
	foreach ( $cases as $label => list( $settings, $coupons, $count ) ) {
		$GLOBALS['c_wp']['coupons'] = $coupons;
		$GLOBALS['c_wp']['count']   = $count;
		$a = $render( $pro, $settings + $defaults( $pro ) );
		$b = $render( $own, $settings + $map['defaults'] + $defaults( $own ) );
		$a = str_replace( array( 'e-checkout-message ', 'e-cart-content' ), array( '', 'digi-cart__content' ), $a );
		$norm = static function ( $h ) { return preg_replace( '/\s+/', ' ', preg_replace( '/<!--.*?-->/s', '', $h ) ); };
		c_check( 0 === strpos( $b, '<div class="digi-cart">' ) && '</div>' === substr( $b, -6 ), "$label: raíz propia digi-cart." );
		$inner = substr( $b, strlen( '<div class="digi-cart">' ), -6 );
		// PRO envuelve los mensajes de envío desde su módulo (aquí no carga); el propio, con su clase de descripción.
		$inner = str_replace( '<span class="digi-cart__content">Estimación</span>', 'Estimación', $inner );
		c_check( $norm( $own_css( $a ) ) === $norm( $inner ), "$label: la misma estructura que PRO.\nPRO: " . $norm( $own_css( $a ) ) . "\nPropio: " . $norm( $inner ) );
		c_check( $hooks_before == $GLOBALS['c_wp']['hooks'] || $label, "$label: los ganchos se quitan." ); // phpcs:ignore
	}
	$GLOBALS['c_wp']['coupons'] = true;
	$GLOBALS['c_wp']['count']   = 2;
	$html = $render( $own, $map['defaults'] + $defaults( $own ) );
	c_check( str_contains( $html, 'digi-cart__column digi-cart__column--start' ) && str_contains( $html, 'digi-cart__sticky' ) && str_contains( $html, 'digi-cart__apply-coupon' ) && str_contains( $html, 'placeholder="Código del cupón"' ) && ! str_contains( $html, 'Ventas cruzadas' ) && str_contains( $html, '>Proceed to Checkout<' ), 'Columnas, cupón propio (en español), sin ventas cruzadas y el texto del control en el botón de pago.' );
	$own_texts = $render( $own, $defaults( $own ) );
	c_check( str_contains( $own_texts, '>Finalizar compra<' ) && str_contains( $own_texts, '>Actualizar carrito<' ) && str_contains( $own_texts, '>Totales del carrito<' ), 'Un carrito propio nuevo, con los textos en español.' );
	c_check( str_contains( $own_texts, '<span class="digi-cart__content">Estimación</span>' ), 'Los mensajes de envío llevan la clase de las descripciones.' );
	$GLOBALS['c_wp']['count'] = 0;
	$empty = $render( $own, array( 'additional_template_switch' => 'active', 'additional_template_select' => '42' ) + $defaults( $own ) );
	c_check( str_contains( $empty, 'plantilla-42' ) && ! str_contains( $empty, 'woocommerce' ), 'Carrito vacío con plantilla.' );
	$GLOBALS['c_wp']['count'] = 2;
	$left = 0;
	foreach ( array( 'gettext', 'woocommerce_before_cart', 'woocommerce_before_cart_table', 'woocommerce_after_cart_table', 'woocommerce_before_cart_collaterals', 'woocommerce_after_cart', 'woocommerce_get_cart_url', 'woocommerce_shipping_estimate_html' ) as $hook ) {
		foreach ( $GLOBALS['c_wp']['hooks'][ $hook ] ?? array() as $fns ) {
			foreach ( $fns as $fn ) {
				$left += is_array( $fn ) && $fn[0] === $own ? 1 : 0;
			}
		}
	}
	c_check( 0 === $left, 'Ningún gancho del widget propio queda puesto tras pintar.' );
	$cross = 0;
	foreach ( $GLOBALS['c_wp']['hooks']['woocommerce_cart_collaterals'] ?? array() as $fns ) {
		$cross += count( array_filter( $fns, static function ( $f ) { return 'woocommerce_cross_sell_display' === $f; } ) );
	}
	c_check( $cross >= 1, 'Las ventas cruzadas vuelven a su gancho al terminar.' );

	// 4. Recursos.
	$dir = $el . 'modules/digitalisimo-woo/store/assets/';
	$css = '';
	foreach ( array( 'cart.css', 'cart-2.css' ) as $file ) {
		$sheet = (string) file_get_contents( $dir . $file );
		c_check( strlen( $sheet ) > 1000 && strlen( $sheet ) <= 4096, "$file: " . strlen( $sheet ) . ' B (tope 4 096).' );
		c_check( ! preg_match( '/url\(|--e-global|elementor-widget-woocommerce|\.e-cart|e-shop-table/', $sheet ), "$file: sin url(), sin la paleta del kit y sin clases de PRO." );
		c_check( substr_count( $sheet, '{' ) === substr_count( $sheet, '}' ), "$file: llaves equilibradas." );
		$css .= $sheet;
	}
	c_check( str_contains( $css, ':focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $css, 'prefers-reduced-motion' ), 'Foco visible y movimiento reducido.' );
	c_check( ! file_exists( $dir . 'cart-3.css' ), 'Dos hojas como mucho.' );
	foreach ( array( '--cs-bg', '--cos-idc', '--ccb-ntc', '--cuca-d', 'digi-cart__sticky--active', 'digi-cart-layout-one-column .digi-cart .digi-cart__container' ) as $needle ) {
		c_check( str_contains( $css, $needle ), "La hoja usa $needle." );
	}
	$own_src = (string) file_get_contents( $el . 'modules/digitalisimo-woo/store/class-cart.php' );
	preg_match_all( '/(?<![a-z0-9_-])--c[a-z]+-[a-z]+(?=\s*:)/', $own_src, $set );
	preg_match_all( '/var\((--c[a-z]+-[a-z]+)/', $css, $used );
	c_check( array() === array_diff( array_unique( $set[0] ), $used[1] ), 'Toda variable que fija un control la lee una hoja: ' . implode( ', ', array_diff( array_unique( $set[0] ), $used[1] ) ) );
	$js = (string) file_get_contents( $dir . 'cart.js' );
	c_check( strlen( $js ) <= 8192 && ! preg_match( '/jQuery|\$\(/', $js ) && str_contains( $js, "'yes' === s.update_cart_automatically" ) && str_contains( $js, 'digi-cart__sticky--active' ), 'cart.js ≤ 8 KB, sin jQuery, con la actualización automática y la columna fija.' );
	$GLOBALS['c_wp']['scripts'] = array();
	$GLOBALS['c_wp']['styles']  = array();
	c_check( array( 'wc-cart', 'selectWoo', 'digitalisimo-woo-cart' ) === $own->get_script_depends(), 'Scripts: los de WooCommerce que pide PRO y el propio.' );
	c_check( array( 'select2', 'digitalisimo-woo-cart', 'digitalisimo-woo-cart-2' ) === $own->get_style_depends() && isset( $GLOBALS['c_wp']['styles']['digitalisimo-woo-cart-2'] ), 'Hojas: select2 de WooCommerce y las dos propias (la segunda, registrada por la base).' );
	c_check( 'digitalisimo-woo-cart' === $own->get_name() && 'Carrito' === $own->get_title() && array( 'woocommerce-elements' ) === $own->get_categories(), 'Nombre, título y categoría.' );

	// 5. Nombres prohibidos y marcas ajenas.
	foreach ( array( 'modules/digitalisimo-woo/store/class-cart.php', 'modules/digitalisimo-woo/store/class-cart-ajax.php', 'modules/digitalisimo-woo/store/assets/cart.js', 'modules/digitalisimo-woo/store/assets/cart.css', 'modules/digitalisimo-woo/store/assets/cart-2.css', 'modules/digitalisimo-convert/maps/woocommerce-cart.php' ) as $file ) {
		$source = (string) file_get_contents( $el . $file );
		c_check( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $source ), "$file: sin nombres prohibidos." );
		c_check( ! preg_match( "/'[^'\\n]*\\s[^'\\n]*\\b(Elementor|Divi|Bricks|YITH)\\b[^'\\n]*'/", $source ), "$file: ninguna frase visible con marcas ajenas." );
	}

	// 6. Conversión de ajustes reales.
	require $el . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	\Digitalisimo\Elements\Convert\Registry::set( 'woocommerce-cart', $map );
	$samples = array(
		array(),
		array( 'cart_layout' => 'one-column', 'section_coupon_display' => '', 'update_cart_automatically' => 'yes', 'sections_background_color' => '#f0f0f0', 'sections_border_radius' => array( 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true ), 'sections_padding_mobile' => array( 'top' => '5', 'right' => '5', 'bottom' => '5', 'left' => '5', 'unit' => 'px' ), 'checkout_button_typography_typography' => 'custom', 'checkout_button_typography_font_size' => array( 'size' => 18, 'unit' => 'px' ), 'checkout_button_normal_background_background' => 'classic', 'checkout_button_normal_background_color' => '#111', '__globals__' => array( 'sections_titles_color' => 'globals/colors?id=primary', 'sections_titles_typography_typography' => 'globals/typography?id=primary' ), '__dynamic__' => array( 'checkout_button_text' => '[elementor-tag id="x" name="site-title"]' ), 'custom_css' => 'selector .e-cart-totals{margin:0} selector .e-cart__column-end .e-sticky-right-column{top:1px}', 'sticky_right_column' => 'yes', 'sticky_right_column_offset' => 40, 'section_cart_show_customize_elements' => array( 'customize_order_summary', 'customize_totals' ), 'order_summary_section_background_color' => '#eee', 'additional_template_switch' => 'active', 'additional_template_select' => '42', '_margin' => array( 'top' => '10' ) ),
	);
	foreach ( $samples as $i => $settings ) {
		$log  = array();
		$tree = \Digitalisimo\Elements\Convert\Converter::tree( array( array( 'id' => 'c1', 'elType' => 'container', 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'woocommerce-cart', 'settings' => $settings ) ) ) ), $log, true );
		$out  = $tree[0]['elements'][0];
		c_check( 'digitalisimo-woo-cart' === $out['widgetType'] && 'w1' === $out['id'], "Muestra $i: convertida con el mismo id." );
		$codes = array();
		foreach ( $log as $row ) {
			foreach ( (array) ( $row['codes'] ?? array() ) as $code ) {
				$codes[] = $code['code'] . ' ' . $code['key'];
			}
		}
		c_check( ! preg_grep( '/^pro\.unmapped/', $codes ), "Muestra $i: ningún ajuste sin declarar (" . implode( ', ', $codes ) . ').' );
		foreach ( $out['settings'] as $key => $value ) {
			if ( '__' === substr( $key, 0, 2 ) || '_' === $key[0] || 'custom_css' === $key ) {
				continue;
			}
			$base = preg_replace( '/_(tablet|mobile)$/', '', $key );
			$ok   = isset( $own->controls[ $base ] );
			foreach ( array_keys( $own->groups ) as $group ) {
				$ok = $ok || 0 === strpos( $key, $group . '_' );
			}
			c_check( $ok, "Muestra $i: $key cae en un control propio." );
		}
	}
	c_check( 'selector .digi-cart__totals{margin:0} selector .digi-cart__column--end .digi-cart__sticky{top:1px}' === $out['settings']['custom_css'], 'El CSS personalizado pasa a las clases propias.' );
	c_check( 'Proceed to Checkout' === ( $tree[0]['elements'][0]['settings']['checkout_button_text'] ?? '' ) || isset( $out['settings']['__dynamic__']['checkout_button_text'] ), 'Los textos y los dinámicos siguen en su clave.' );
	c_check( array( 'sections_titles_color' => 'globals/colors?id=primary', 'sections_titles_typography_typography' => 'globals/typography?id=primary' ) === $out['settings']['__globals__'], 'Los globales siguen en su clave.' );

	echo 'DIGITALÍSIMO Elements: Carrito propio en verde (' . $GLOBALS['c_wp']['checks'] . " comprobaciones).\n";
}
