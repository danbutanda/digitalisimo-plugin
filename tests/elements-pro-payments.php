<?php
/**
 * Botón de PayPal (`digitalisimo-paypal-button`) y Botón de Stripe (`digitalisimo-stripe-button`), C9 (docs/pro/c9.md),
 * los widgets propios que sustituyen a PayPal Button y Stripe Button de PRO, y sus mapas
 * `modules/digitalisimo-convert/maps/paypal-button.php` y `stripe-button.php`:
 * - registro y recursos (hoja y script compartidos `digitalisimo-payment-button`: hoja ≤ 4 KB y script ≤ 6 KB sin jQuery,
 *   sin `--e-global-*` ni `url()`; guarda de build-packages.sh);
 * - controles: con el `Button_Trait` real del núcleo y dobles de `Widget_Base`, se ejecutan los `register_controls()` de
 *   PRO y de los widgets propios y se comparan uno a uno (orden, sección, pestaña, tipo, valor por defecto, opciones,
 *   condiciones, selectores, grupos), también las quitas y los cambios sobre el botón del núcleo; los textos por defecto
 *   propios (en español) los completa el mapa con los de PRO;
 * - marcado: el formulario de PayPal tiene los mismos campos ocultos y el mismo destino que el de PRO (pintado con los
 *   mismos ajustes) en pago, donación, suscripción, modo de prueba y redirección; Stripe lleva la acción, el id del
 *   elemento y el nonce del punto de PRO; `<button type="submit">` sin `role`; mensajes ocultos; nada en el editor;
 * - ningún secreto en el navegador: las claves de Stripe de las opciones y el token SDK de PayPal nunca salen en el HTML;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, `__globals__`, `__dynamic__`, CSS
 *   personalizado con clases de PRO): ningún `pro.unmapped` y cada clave cae en un control propio;
 * - el punto de PRO que crea la sesión de Stripe lee los ajustes por los nombres que el widget propio conserva.
 *
 * Uso: php-wasm-cli tests/elements-pro-payments.php. Se ejecuta con php-wasm: sale con 0 aunque haya un error fatal, así
 * que se juzga por la línea final.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public $tabs     = array();
		public $ops      = array();
		public $attrs    = array();
		public $section  = '';
		public $tab      = 'content';
		public $id       = 'abc1234';
		public function __construct() {
			$this->register_controls();
		}
		protected function register_controls() {}
		public function get_id() { return $this->id; }
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function start_controls_section( $name, $args ) {
			$this->sections[ $name ] = $args;
			$this->section           = $name;
			$this->tab               = $args['tab'] ?? 'content';
			$this->ops[]             = array( 'section', $name, $this->tab );
		}
		public function end_controls_section() {}
		public function start_controls_tabs( $name, $args = array() ) { $this->tabs[] = $name; $this->ops[] = array( 'tabs', $name ); }
		public function start_controls_tab( $name, $args ) { $this->tabs[] = $name; $this->ops[] = array( 'tab', $name ); }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args ) {
			$this->controls[ $name ] = $args + array( '_section' => $this->section, '_tab' => $this->tab );
			$this->ops[]             = array( 'add', $name );
		}
		public function add_responsive_control( $name, $args ) { $this->add_control( $name, $args + array( 'responsive' => true ) ); }
		public function add_group_control( $type, $args ) {
			$this->groups[ $args['name'] ] = array( 'type' => $type, '_section' => $this->section, '_tab' => $this->tab ) + $args;
			$this->ops[]                   = array( 'group', $args['name'] );
		}
		public function update_control( $name, $args ) {
			$this->ops[] = array( 'update', $name, array_keys( $args ) );
			if ( isset( $this->controls[ $name ] ) ) {
				$this->controls[ $name ] = array_merge( $this->controls[ $name ], $args );
				return true;
			}
			// Un subcontrol de un grupo (`background_color`, `border_border`): se guarda en el grupo.
			foreach ( $this->groups as $group => $data ) {
				if ( 0 === strpos( $name, $group . '_' ) ) {
					$this->groups[ $group ]['_updates'][ $name ] = array_merge( $this->groups[ $group ]['_updates'][ $name ] ?? array(), $args );
					return true;
				}
			}
			return false;
		}
		public function remove_control( $name ) {
			$this->ops[] = array( 'remove', $name );
			unset( $this->controls[ $name ] );
		}
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function add_render_attribute( $element, $key = null, $value = null ) {
			if ( is_array( $element ) ) {
				foreach ( $element as $el => $pairs ) {
					foreach ( $pairs as $k => $v ) {
						$this->add_render_attribute( $el, $k, $v );
					}
				}
				return;
			}
			foreach ( (array) $value as $v ) {
				$this->attrs[ $element ][ $key ][] = $v;
			}
		}
		public function set_render_attribute( $element, $key, $value ) { $this->attrs[ $element ][ $key ] = (array) $value; }
		public function get_render_attribute_string( $element ) {
			$out = '';
			foreach ( $this->attrs[ $element ] ?? array() as $k => $v ) {
				$out .= ' ' . $k . '="' . htmlspecialchars( implode( ' ', $v ), ENT_QUOTES ) . '"';
			}
			return ltrim( $out );
		}
		public function print_render_attribute_string( $element ) { echo $this->get_render_attribute_string( $element ); }
	}
	class Controls_Manager {
		const TEXT = 'text'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const CHOOSE = 'choose'; const COLOR = 'color'; const SLIDER = 'slider'; const NUMBER = 'number'; const URL = 'url'; const DIMENSIONS = 'dimensions'; const RAW_HTML = 'raw_html'; const ALERT = 'alert'; const HIDDEN = 'hidden'; const ICONS = 'icons'; const HOVER_ANIMATION = 'hover_animation';
		const TAB_STYLE = 'style'; const TAB_CONTENT = 'content';
	}
	class Group_Control_Typography { public static function get_type() { return 'typography'; } }
	class Group_Control_Text_Shadow { public static function get_type() { return 'text-shadow'; } }
	class Group_Control_Border { public static function get_type() { return 'border'; } }
	class Group_Control_Box_Shadow { public static function get_type() { return 'box-shadow'; } }
	class Group_Control_Background { public static function get_type() { return 'background'; } }
	class Icons_Manager {
		public static function is_migration_allowed() { return true; }
		public static function render_icon( $icon, $attrs = array() ) { echo '<i class="' . htmlspecialchars( (string) ( $icon['value'] ?? '' ), ENT_QUOTES ) . '" aria-hidden="true"></i>'; }
	}
	class Utils {
		public static function print_validated_html_tag( $tag ) { echo preg_match( '/^[a-z]+$/', $tag ) ? $tag : 'div'; }
		public static function print_unescaped_internal_string( $s ) { echo $s; }
		public static function has_pro() { return true; }
	}
	class Settings { public static function get_settings_tab_url( $tab ) { return 'https://sitio.test/wp-admin/admin.php?page=elementor-settings#tab-' . $tab; } }
	class Plugin {
		public static $instance;
		public $editor;
		public $experiments;
		public static function instance() { return self::$instance; }
	}
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
}
namespace ElementorPro\Base {
	trait Base_Widget_Trait {
		public function get_categories() { return array( 'pro-elements' ); }
	}
	class Markdown_Utils {}
}
namespace ElementorPro\Core {
	class Utils {
		public static function _unstable_get_super_global_value( $arr, $key ) { return $arr[ $key ] ?? null; }
	}
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return \Elementor\Plugin::$instance; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$GLOBALS['pay_edit']      = false;
	$GLOBALS['pay_optimized'] = false;
	$GLOBALS['pay_options']   = array(
		'elementor_pro_stripe_test_secret_key' => 'sk_test_SECRETO_DE_PRUEBA_123',
		'elementor_pro_stripe_live_secret_key' => 'sk_live_SECRETO_REAL_456',
		'elementor_pro_facebook_app_id'        => '',
	);
	function __( $v ) { return $v; }
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function _x( $v ) { return $v; }
	function is_rtl() { return false; }
	function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
	function get_option( $name, $default = false ) { return $GLOBALS['pay_options'][ $name ] ?? $default; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return preg_match( '~^(https?://|/|#|mailto:)~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function esc_url_raw( $v ) { return preg_match( '~^(https?://|/|#|mailto:)~', (string) $v ) ? (string) $v : ''; }
	function wp_kses_post( $v ) { return preg_replace( '@<script[^>]*?>.*?</script>@si', '', (string) $v ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); }
	function admin_url( $path = '' ) { return 'https://sitio.test/wp-admin/' . $path; }
	function wp_create_nonce( $action ) { return 'n0nce' . substr( md5( $action ), 0, 5 ); }
	function wp_nonce_field( $action, $name ) { echo '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" /><input type="hidden" name="_wp_http_referer" value="/" />'; }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function add_action() {}
	function add_filter() {}
	function check_pay( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->editor      = new class() { public function is_edit_mode() { return $GLOBALS['pay_edit']; } };
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return 'e_optimized_markup' === $f && $GLOBALS['pay_optimized']; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'elementor/includes/widgets/traits/button-trait.php';
	require $root . 'elementor/includes/widgets/button.php';
	require $root . 'modules/payments/classes/payment-button.php';
	require $root . 'modules/payments/widgets/paypal-button.php';
	require $root . 'modules/payments/widgets/stripe-button.php';
	require $root . 'modules/digitalisimo-services/class-module.php';
	\Digitalisimo\Elements\Services\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Services\Module as Services;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;

	$guard  = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$checks = 0;
	$check  = static function ( $ok, $message ) use ( &$checks ) {
		check_pay( $ok, $message );
		++$checks;
	};
	$render = static function ( $widget, array $settings, string $method = 'render' ) {
		$widget->settings = $settings;
		$widget->attrs    = array();
		ob_start();
		( digi_test_accessible( new ReflectionMethod( $widget, $method ) ) )->invoke( $widget );
		return (string) ob_get_clean();
	};
	/** Valores por defecto de una pila (controles y subcontroles actualizados de grupo). */
	$defaults = static function ( $widget ) {
		$out = array();
		// Como el núcleo: un control sin valor por defecto vale ''.
		foreach ( $widget->controls as $name => $control ) {
			$out[ $name ] = array_key_exists( 'default', $control ) ? $control['default'] : '';
		}
		return $out;
	};
	/** Campos ocultos de un formulario, en orden: `nombre=valor`. */
	$hidden = static function ( string $html ) {
		preg_match_all( '/<input type="hidden"(?: id="[^"]*")? name="([^"]+)" value="([^"]*)"/', $html, $m, PREG_SET_ORDER );
		return array_map( static function ( $x ) { return $x[1] . '=' . html_entity_decode( $x[2], ENT_QUOTES ); }, $m );
	};
	$texts = array( 'label', 'description', 'placeholder', 'content', 'raw', 'title', 'label_on', 'label_off', 'select2options' );
	$norm  = static function ( array $control, array $classes ) use ( $texts ) {
		foreach ( $texts as $key ) {
			unset( $control[ $key ] );
		}
		if ( isset( $control['options'] ) && is_array( $control['options'] ) ) {
			$control['options'] = array_keys( $control['options'] );
		}
		foreach ( array( 'selectors', 'selector' ) as $key ) {
			if ( isset( $control[ $key ] ) ) {
				$control[ $key ] = json_decode( strtr( json_encode( $control[ $key ] ), $classes ), true );
			}
		}
		return $control;
	};

	$pairs = array(
		'paypal-button' => array( 'digitalisimo-paypal-button', 'Botón de PayPal', \Digitalisimo\Elements\Services\Widgets\Paypal_Button::class, \ElementorPro\Modules\Payments\Widgets\Paypal_Button::class, '#032E82', 'fab fa-paypal' ),
		'stripe-button' => array( 'digitalisimo-stripe-button', 'Botón de Stripe', \Digitalisimo\Elements\Services\Widgets\Stripe_Button::class, \ElementorPro\Modules\Payments\Widgets\Stripe_Button::class, '#635bff', 'fab fa-stripe-s' ),
	);
	// Selectores de PRO → propios (los mensajes) para comparar.
	$selector_classes = array( '.elementor-message.elementor-error-message-' => '.digi-pay__message--', '.elementor-message' => '.digi-pay__message' );
	$widgets          = array();
	$pro_widgets      = array();

	// 1. Registro, recursos y guarda.
	$css = (string) file_get_contents( $root . 'modules/digitalisimo-services/assets/css/payment-button.css' );
	$js  = (string) file_get_contents( $root . 'modules/digitalisimo-services/assets/js/payment-button.js' );
	$check( strlen( $css ) > 0 && strlen( $css ) <= 4096 && ! preg_match( '/url\(|--e-global/i', $css ) && array( '#1d2327', '#fff' ) === array_values( array_unique( preg_match_all( '/#[0-9a-f]{3,8}\b/i', $css, $hex ) ? $hex[0] : array() ) ), 'payment-button.css: ' . strlen( $css ) . ' B (tope 4 096), sin url() ni --e-global-*; sólo los colores del foco.' );
	$check( str_contains( $css, 'outline:2px solid' ) && str_contains( $css, 'outline-offset:2px' ) && str_contains( $css, 'min-height:24px' ) && str_contains( $css, 'prefers-reduced-motion' ), 'payment-button.css: foco de 2 px a 2 px, objetivo de 24 px y movimiento reducido.' );
	$check( strlen( $js ) > 0 && strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|swiper|innerHTML|DOMPurify|purify|eval\(|new Function/i', $js ), 'payment-button.js: ' . strlen( $js ) . ' B (tope 6 KB), sin jQuery, sin innerHTML (los mensajes van con textContent).' );
	$check( str_contains( $js, "'submit_stripe_form'" ) === false && str_contains( $js, "data[postId]" ) && str_contains( $js, 'data[widgetId]' ) && str_contains( $js, 'data[pageUrl]' ) && str_contains( $js, 'data[nonce]' ) && str_contains( $js, 'textContent' ), 'payment-button.js envía los campos del script de PRO (la acción sale del formulario) y pinta los mensajes como texto.' );
	foreach ( array( 'modules/digitalisimo-services/class-payment-button.php', 'modules/digitalisimo-services/widgets/class-paypal-button.php', 'modules/digitalisimo-services/widgets/class-stripe-button.php', 'modules/digitalisimo-services/assets/css/payment-button.css', 'modules/digitalisimo-services/assets/js/payment-button.js', 'modules/digitalisimo-convert/maps/paypal-button.php', 'modules/digitalisimo-convert/maps/stripe-button.php' ) as $file ) {
		$check( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	foreach ( $pairs as $source => list( $name, $title, $class, $pro_class, $color, $icon ) ) {
		$check( $class === Services::load( $name ), "$name: el módulo de servicios lo carga." );
		$widget = new $class();
		$pro    = new $pro_class();
		$check( $name === $widget->get_name() && $title === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories() && $title === Services::TITLES[ $name ], "$name: nombre, título y categoría propios." );
		$check( array( 'digitalisimo-payment-button' ) === $widget->get_style_depends() && array( 'digitalisimo-payment-button' ) === $widget->get_script_depends(), "$name: sólo la hoja y el script compartidos de los botones de pago." );
		$check( $widget instanceof \Elementor\Widget_Button && ! ( $widget instanceof \ElementorPro\Modules\Payments\Classes\Payment_Button ), "$name: hereda del Botón del núcleo, no de la base de PRO." );
		$widgets[ $source ]     = $widget;
		$pro_widgets[ $source ] = $pro;

		// 2. Controles, uno a uno.
		$check( array_keys( $pro->controls ) === array_keys( $widget->controls ), "$name: mismos controles en el mismo orden. PRO: " . implode( ',', array_keys( $pro->controls ) ) . ' · propio: ' . implode( ',', array_keys( $widget->controls ) ) );
		$check( array_keys( $pro->groups ) === array_keys( $widget->groups ), "$name: mismos grupos." );
		$check( array_keys( $pro->sections ) === array_keys( $widget->sections ), "$name: mismas secciones." );
		$check( $pro->tabs === $widget->tabs, "$name: mismas pestañas." );
		$strip_ops = static function ( array $ops ) {
			return array_map( static function ( $op ) { return 'update' === $op[0] ? array( $op[0], $op[1], array_values( array_diff( $op[2], array( 'label', 'placeholder', 'description' ) ) ) ) : $op; }, $ops );
		};
		$check( $strip_ops( $pro->ops ) === $strip_ops( $widget->ops ), "$name: mismas altas, quitas y cambios, en el mismo orden." );
		$own_defaults = array( 'text', 'error_message_global', 'error_message_payment' );
		foreach ( $pro->controls as $control_name => $control ) {
			$a = $norm( $control, $selector_classes );
			$b = $norm( $widget->controls[ $control_name ], array() );
			if ( in_array( $control_name, $own_defaults, true ) ) {
				unset( $a['default'], $b['default'] );
			}
			$check( $a === $b, "$name: control $control_name igual al de PRO (sin textos).\nPRO: " . json_encode( $a ) . "\npropio: " . json_encode( $b ) );
		}
		foreach ( $pro->groups as $group => $args ) {
			$check( $norm( $args, $selector_classes ) === $norm( $widget->groups[ $group ], array() ), "$name: grupo $group igual al de PRO." );
		}
		$check( 'Buy Now' === $pro->controls['text']['default'] && 'Comprar ahora' === $widget->controls['text']['default'], "$name: texto por defecto propio en español." );
		$check( $color === $pro->groups['background']['_updates']['background_color']['default'] && $color === $widget->groups['background']['_updates']['background_color']['default'], "$name: color de fondo por defecto de PRO ($color)." );
		$check( '#FFF' === $widget->controls['button_text_color']['default'] && 'none' === $widget->groups['border']['_updates']['border_border']['default'], "$name: texto blanco y sin borde, como PRO." );
		$check( $icon === $widget->controls['selected_icon']['default']['value'], "$name: icono por defecto de PRO ($icon)." );
		$check( ! isset( $widget->controls['button_type'] ) && ! isset( $widget->controls['link'] ) && ! isset( $widget->controls['size'] ), "$name: sin tipo, enlace ni tamaño del botón del núcleo." );

		// 3. Mapa.
		$map = Registry::map( $source, true );
		$check( null !== $map && $name === $map['target'], "$name: mapa $source → $name." );
		$declared = array_merge( (array) $map['keep'], array_keys( (array) ( $map['rename'] ?? array() ) ), array_keys( (array) ( $map['drop'] ?? array() ) ) );
		foreach ( array_keys( $pro->controls ) as $control_name ) {
			$check( in_array( $control_name, $declared, true ), "$name: el mapa declara $control_name." );
		}
		foreach ( array_keys( $pro->groups ) as $group ) {
			$check( ( $map['groups'][ $group ] ?? '' ) === $group, "$name: el mapa declara el grupo $group." );
		}
		foreach ( $own_defaults as $key ) {
			$check( ( $map['defaults'][ $key ] ?? null ) === $pro->controls[ $key ]['default'], "$name: el mapa completa el texto por defecto de PRO de $key." );
		}
		$check( ! array_diff( (array) $map['keep'], array_keys( $widget->controls ) ), "$name: cada clave conservada es un control propio." );
	}

	// 4. Marcado de PayPal frente al de PRO, con los mismos ajustes.
	$pp     = $widgets['paypal-button'];
	$pp_pro = $pro_widgets['paypal-button'];
	$base   = $defaults( $pp_pro ) + array( 'hover_animation' => '' );
	$cases  = array(
		'pago'          => array( 'email' => 'ventas@ejemplo.test', 'product_name' => 'Camiseta "azul" & roja', 'product_sku' => 'CAM-1', 'product_price' => '25.50', 'shipping_price' => '4', 'tax_type' => 'percentage', 'tax_rate' => '16', 'quantity' => '3' ),
		'donación fija' => array( 'email' => 'ventas@ejemplo.test', 'type' => 'donation', 'donation_amount' => '10' ),
		'donación libre' => array( 'email' => 'ventas@ejemplo.test', 'type' => 'donation', 'donation_type' => 'any' ),
		'suscripción'   => array( 'email' => 'ventas@ejemplo.test', 'type' => 'subscription', 'product_price' => '9', 'billing_cycle' => 'weeks', 'auto_renewal' => '' ),
		'prueba'        => array( 'email' => 'ventas@ejemplo.test', 'sandbox_mode' => 'yes', 'sandbox_email' => 'pruebas@ejemplo.test', 'product_price' => '5', 'open_in_new_window' => '', 'redirect_after_success' => array( 'url' => 'https://ejemplo.test/gracias?x=1&y=2' ) ),
		'cantidad cero' => array( 'email' => 'ventas@ejemplo.test', 'product_price' => '1', 'quantity' => '0', 'shipping_price' => '-3' ),
	);
	foreach ( $cases as $label => $over ) {
		$settings = array_merge( $base, array( 'text' => 'Buy Now', 'selected_icon' => array( 'value' => 'fab fa-paypal', 'library' => 'fa-brands' ) ), $over );
		$a        = $render( $pp_pro, $settings );
		$b        = $render( $pp, $settings );
		$check( $hidden( $a ) === $hidden( $b ), "PayPal ($label): mismos campos ocultos que PRO.\nPRO: " . implode( ' | ', $hidden( $a ) ) . "\npropio: " . implode( ' | ', $hidden( $b ) ) );
		preg_match( '/<form action="([^"]+)" method="post" target="([^"]+)"/', $a, $fa );
		preg_match( '/<form class="digi-pay digi-pay--paypal" action="([^"]+)" method="post" target="([^"]+)" data-digi-pay="paypal">/', $b, $fb );
		$check( $fa && $fb && $fa[1] === $fb[1] && $fa[2] === $fb[2], "PayPal ($label): mismo destino ({$fb[1]}) y ventana ({$fb[2]}) que PRO." );
		$check( preg_match( '/<button class="elementor-button digi-pay__button elementor-size-sm[^"]*"( id="[^"]*")? type="submit">/', $b ) && ! str_contains( $b, 'role="button"' ), "PayPal ($label): un <button type=\"submit\"> real, sin role." );
		$check( str_contains( $b, '<span class="elementor-button-text">Buy Now</span>' ) && str_contains( $b, '<i class="fab fa-paypal" aria-hidden="true"></i>' ), "PayPal ($label): texto e icono del botón del núcleo." );
		$check( str_starts_with( $b, '<div class="elementor-button-wrapper">' ) && '</div>' === substr( $b, -6 ), "PayPal ($label): envoltorio del botón del núcleo, como PRO." );
	}
	$sandbox = $render( $pp, array_merge( $base, $cases['prueba'] ) );
	$check( str_contains( $sandbox, 'https://sandbox.paypal.com/cgi-bin/webscr' ) && str_contains( $sandbox, 'digi-pay__button--sandbox' ) && str_contains( $sandbox, 'name="business" value="pruebas@ejemplo.test"' ) && str_contains( $sandbox, 'target="_top"' ) && str_contains( $sandbox, 'name="return" value="https://ejemplo.test/gracias?x=1&amp;y=2"' ), 'PayPal: modo de prueba con la cuenta de pruebas, ventana actual y redirección.' );
	$pp->id = 'pp00001';
	$out    = $render( $pp, array_merge( $base, array( 'email' => 'v@ejemplo.test', 'product_price' => '3', 'button_css_id' => 'mi-boton', 'hover_animation' => 'grow' ) ) );
	$check( str_contains( $out, 'id="mi-boton"' ) && str_contains( $out, 'elementor-animation-grow' ), 'PayPal: id del botón y animación al pasar el cursor.' );
	// Errores: ocultos, con role="alert"; reducidos a uno global si hay varios; ninguno en el editor.
	$errs = $render( $pp, array_merge( $base, array( 'email' => '', 'text' => 'Pagar' ) ) );
	$check( 1 === preg_match_all( '/class="elementor-message elementor-message-danger digi-pay__message digi-pay__message--payment" role="alert" hidden>\nNo payment method connected\. Contact seller\.<\/div>/', $errs ) && ! str_contains( $errs, 'digi-pay__message--global' ), 'PayPal sin cuenta: mensaje de método de pago oculto (precio por defecto 0.00 no vacío).' );
	$errs = $render( $pp, array_merge( $base, array( 'email' => '', 'product_price' => '' ) ) );
	$check( 1 === substr_count( $errs, 'role="alert"' ) && str_contains( $errs, 'digi-pay__message--global' ), 'PayPal: dos errores se reducen al global, como PRO.' );
	$pro_errs = $render( $pp_pro, array_merge( $base, array( 'email' => '', 'product_price' => '' ) ) );
	$check( str_contains( $pro_errs, 'elementor-error-message-global' ) && 1 === substr_count( $pro_errs, 'elementor-message-danger' ), 'PayPal de PRO: el mismo error global (referencia).' );
	// Corrección de fidelidad (docs/pro/c9.md): como la plantilla de PRO, un blanco antes del texto del mensaje, que tras el
	// `::before` del núcleo (el aspa, `inline-block`) deja el mismo espacio; sin él, el texto quedaba 5 px más a la izquierda.
	$check( 1 === preg_match( '/elementor-message-danger[^>]*>\s+\S/', $pro_errs ) && 1 === preg_match( '/digi-pay__message--global" role="alert" hidden>\n[^\s<]/', $errs ), 'Mensaje de error con un blanco antes del texto, como PRO.' );
	$errs = $render( $pp, array_merge( $base, array( 'email' => 'v@ejemplo.test', 'custom_messages' => 'yes', 'error_message_global' => 'Falta el precio', 'product_price' => '' ) ) );
	$check( str_contains( $errs, ">\nFalta el precio</div>" ), 'PayPal: mensaje personalizado.' );
	$GLOBALS['pay_edit'] = true;
	$check( ! str_contains( $render( $pp, array_merge( $base, array( 'email' => '' ) ) ), 'digi-pay__message' ), 'PayPal: ningún mensaje en el editor, como PRO.' );
	$GLOBALS['pay_edit'] = false;
	$check( '' === trim( str_replace( array( '<div class="elementor-button-wrapper">', '</div>' ), '', $render( $pp, array_merge( $base, array( 'merchant_account' => 'advanced', 'sdk_token' => 'TOKEN_SECRETO_SDK' ) ) ) ) ), 'PayPal con cuenta avanzada: no pinta formulario (como PRO) ni el token SDK.' );
	$GLOBALS['pay_optimized'] = true;
	$opt                      = $render( $pp, array_merge( $base, $cases['pago'] ) );
	$check( 0 === strpos( $opt, '<form ' ) && ! str_contains( $opt, 'elementor-button-wrapper' ), 'PayPal: con el marcado optimizado, sin envoltorio.' );
	$GLOBALS['pay_optimized'] = false;

	// 5. Stripe: el formulario sólo lleva el punto de PRO, el id y un nonce.
	$st     = $widgets['stripe-button'];
	$st_pro = $pro_widgets['stripe-button'];
	$st->id = 'st00001';
	$sbase  = $defaults( $st_pro ) + array( 'hover_animation' => '' );
	$s_ok   = array_merge( $sbase, array( 'text' => 'Buy Now', 'product_name' => 'Curso', 'stripe_product_price' => '99', 'selected_icon' => array( 'value' => 'fab fa-stripe-s', 'library' => 'fa-brands' ) ) );
	$sout   = $render( $st, $s_ok );
	$check( str_contains( $sout, '<form class="digi-pay digi-pay--stripe" data-digi-pay="stripe" data-digi-pay-url="https://sitio.test/wp-admin/admin-ajax.php" data-digi-pay-action="submit_stripe_form" data-digi-pay-widget="st00001" data-digi-pay-nonce="' . wp_create_nonce( 'stripe_form_submit' ) . '" data-digi-pay-target="_blank"' ), 'Stripe: acción submit_stripe_form, id del elemento, nonce stripe_form_submit y pestaña nueva por defecto.' );
	$pro_s  = $render( $st_pro, $s_ok );
	$check( str_contains( $pro_s, 'name="action" value="submit_stripe_form"' ) && str_contains( $pro_s, 'name="stripe_form_submit_nonce" value="' . wp_create_nonce( 'stripe_form_submit' ) . '"' ), 'Stripe de PRO: la misma acción y el mismo nonce (referencia).' );
	$check( ! str_contains( $sout, 'digi-pay__message' ) && str_contains( $sout, 'type="submit"' ) && ! str_contains( $sout, 'role="button"' ), 'Stripe con nombre y precio: sin errores, botón de envío real.' );
	$serr = $render( $st, array_merge( $sbase, array( 'product_name' => '' ) ) );
	$check( str_contains( $serr, 'digi-pay__message--global" role="alert" hidden>' . "\n" . 'Something went wrong</div>' ), 'Stripe sin nombre: mensaje global oculto (con el texto de los ajustes).' );
	$own  = $defaults( $st ) + array( 'hover_animation' => '' );
	$check( str_contains( $render( $st, array_merge( $own, array( 'product_name' => '' ) ) ), 'hidden>' . "\n" . 'Algo salió mal.</div>' ), 'Stripe nuevo: mensaje por defecto propio en español.' );
	$scus = $render( $st, array_merge( $s_ok, array( 'custom_messages' => 'yes', 'error_message_global' => 'Error general', 'error_message_payment' => 'Sin pasarela', 'open_in_new_window' => '' ) ) );
	$check( str_contains( $scus, 'data-digi-pay-target="_self" data-digi-pay-custom="yes" data-digi-pay-global="Error general" data-digi-pay-payment="Sin pasarela"' ), 'Stripe: misma ventana y mensajes personalizados para el script.' );
	$snull = $render( $st, array_merge( $s_ok, array( 'custom_messages' => '', 'error_message_global' => null, 'error_message_payment' => null ) ) );
	$check( str_contains( $snull, 'data-digi-pay-custom="" data-digi-pay-global="Se produjo un error." data-digi-pay-payment="No hay ningún método de pago conectado. Contacta con el vendedor."' ), 'Stripe sin mensajes personalizados (el núcleo los da vacíos): el script recibe los de por defecto.' );
	$check( ( digi_test_accessible( new ReflectionMethod( $st, 'is_dynamic_content' ) ) )->invoke( $st ) === true, 'Stripe: no entra en la caché de elementos (lleva un nonce).' );

	// 6. Ningún secreto en el navegador.
	$all = $sout . $scus . $serr . $render( $st, array_merge( $s_ok, array( 'sandbox_mode' => 'yes' ) ) ) . $render( $pp, array_merge( $base, $cases['pago'], array( 'sdk_token' => 'TOKEN_SECRETO_SDK' ) ) );
	foreach ( array( 'sk_test_SECRETO_DE_PRUEBA_123', 'sk_live_SECRETO_REAL_456', 'TOKEN_SECRETO_SDK', 'Bearer', 'secret' ) as $secret ) {
		$check( ! str_contains( $all, $secret ), "Ningún secreto en el HTML ($secret)." );
	}
	foreach ( array( 'class-payment-button.php', 'widgets/class-paypal-button.php', 'widgets/class-stripe-button.php' ) as $file ) {
		$php = (string) file_get_contents( $root . 'modules/digitalisimo-services/' . $file );
		$check( ! preg_match( "/get_option\(|stripe_(test|live)_secret_key|wp_remote_|sdk_token'\s*\]/", $php ), "$file no lee claves ni llama a servicios: lo hace el punto de PRO en el servidor." );
	}

	// 7. El punto de PRO que crea la sesión lee los ajustes por nombre: los mismos que conserva el widget propio.
	$module = (string) file_get_contents( $root . 'modules/payments/module.php' );
	preg_match_all( "/\\\$(?:widget_settings|widget\['settings'\])\['([a-z_]+)'\]/", $module, $read );
	$read = array_unique( $read[1] );
	$check( count( $read ) >= 8, 'El punto de PRO lee al menos 8 ajustes del widget (' . implode( ', ', $read ) . ').' );
	foreach ( $read as $key ) {
		$check( isset( $st->controls[ $key ] ) || 'redirect_after_success' === $key && isset( $st->controls[ $key ] ), "Stripe: el punto de PRO encuentra el ajuste $key en el widget propio." );
	}
	$check( str_contains( $module, "add_action( 'wp_ajax_nopriv_submit_stripe_form'" ) && str_contains( $module, "'stripe_form_submit'" ) && str_contains( $module, "\$data['widgetId']" ) && str_contains( $module, "\$data['postId']" ) && str_contains( $module, "\$data['pageUrl']" ) && str_contains( $module, "\$data['nonce']" ), 'El punto de PRO espera la acción, el nonce y los campos que envía el script propio.' );

	// 8. Conversión de ajustes reales.
	foreach ( array( 'paypal-button', 'stripe-button' ) as $source ) {
		Registry::set( $source, null );
		Registry::reset();
	}
	$pro_tree = array(
		array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'pp1', 'elType' => 'widget', 'widgetType' => 'paypal-button', 'settings' => array() ),
			array( 'id' => 'pp2', 'elType' => 'widget', 'widgetType' => 'paypal-button', 'settings' => array(
				'email' => 'ventas@ejemplo.test', 'type' => 'subscription', 'product_name' => 'Club', 'product_price' => '12', 'billing_cycle' => 'years', 'auto_renewal' => '', 'currency' => 'MXN',
				'text' => 'Suscribirme', 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ), 'icon_align' => 'row-reverse', 'icon_indent' => array( 'unit' => 'px', 'size' => 8 ),
				'align' => 'justify', 'align_mobile' => 'center', 'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 15 ),
				'background_background' => 'classic', 'background_color' => '#111111', 'button_background_hover_background' => 'classic', 'button_background_hover_color' => '#222222', 'hover_color' => '#eeeeee',
				'border_border' => 'solid', 'border_width' => array( 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ), 'border_color' => '#333333', 'border_radius' => array( 'unit' => 'px', 'top' => '30', 'right' => '30', 'bottom' => '30', 'left' => '30', 'isLinked' => true ),
				'text_padding' => array( 'unit' => 'px', 'top' => '14', 'right' => '28', 'bottom' => '14', 'left' => '28', 'isLinked' => false ), 'button_box_shadow_box_shadow_type' => 'yes', 'hover_animation' => 'grow',
				'sandbox_mode' => 'yes', 'sandbox_email' => 'pruebas@ejemplo.test', 'open_in_new_window' => '', 'redirect_after_success' => array( 'url' => 'https://ejemplo.test/gracias' ),
				'custom_messages' => 'yes', 'error_message_global' => 'Ups', 'error_message_payment' => 'Sin cuenta', 'message_typography_typography' => 'custom', 'message_typography_font_weight' => '700', 'message_color_global' => '#aa0000', 'message_color_payment' => '#bb0000',
				'__globals__' => array( 'background_color' => 'globals/colors?id=secondary', 'typography_typography' => 'globals/typography?id=primary', 'message_color_global' => 'globals/colors?id=accent' ),
				'__dynamic__' => array( 'product_name' => '[elementor-tag id="t1" name="post-title" settings="%7B%7D"]', 'text' => '[elementor-tag id="t2" name="site-title" settings="%7B%7D"]' ),
				'_css_classes' => 'mi-pago', '_margin' => array( 'unit' => 'px', 'top' => '10', 'right' => '0', 'bottom' => '10', 'left' => '0', 'isLinked' => false ),
				'custom_css' => 'selector .elementor-paypal-legacy{letter-spacing:1px} selector .elementor-message.elementor-error-message-global{font-style:italic} selector .elementor-payment-sandbox-mode{opacity:.9}',
			) ),
			array( 'id' => 'st1', 'elType' => 'widget', 'widgetType' => 'stripe-button', 'settings' => array() ),
			array( 'id' => 'st2', 'elType' => 'widget', 'widgetType' => 'stripe-button', 'settings' => array(
				'product_name' => 'Curso', 'stripe_product_price' => '149.9', 'stripe_currency' => 'EUR', 'stripe_quantity' => '2', 'shipping_amount' => '5', 'stripe_test_env_tax_rates_list' => '["txr_1","inclusive"]',
				'sandbox_mode' => 'yes', 'open_in_new_window' => 'yes', 'custom_messages' => 'yes', 'error_message_global' => 'Fallo', 'error_message_payment' => 'Sin pasarela', 'redirect_after_success' => array( 'url' => 'https://ejemplo.test/ok' ),
				'text' => 'Pagar con tarjeta', 'button_text_color' => '#fefefe', 'background_color' => '#000000', 'button_css_id' => 'pagar',
				'custom_css' => 'selector .elementor-stripe-form .elementor-stripe-error-message{color:red} selector .elementor-payment-button{border-radius:0}',
			) ),
		) ),
	);
	$log       = array();
	$converted = Converter::tree( $pro_tree, $log, true );
	$check( 4 === count( $log ), 'Converter::tree() convierte los cuatro botones (mapas con borradores): ' . count( $log ) );
	$codes = array();
	foreach ( $log as $row ) {
		foreach ( $row['codes'] as $code ) {
			$codes[] = $row['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	sort( $codes );
	$check( array( 'pp2:pro.css:custom_css', 'st2:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni pro.approx; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	$out = array();
	foreach ( $converted[0]['elements'] as $el ) {
		$out[ $el['id'] ] = $el;
	}
	$check( 'digitalisimo-paypal-button' === $out['pp1']['widgetType'] && 'digitalisimo-stripe-button' === $out['st2']['widgetType'], 'Mismo id y widget propio.' );
	$check( 'Buy Now' === $out['pp1']['settings']['text'] && 'An error occurred.' === $out['pp1']['settings']['error_message_global'] && 'Something went wrong' === $out['st1']['settings']['error_message_global'] && 'Gateway not connected. Contact seller' === $out['st1']['settings']['error_message_payment'], 'Un botón de PRO sin cambios conserva los textos de PRO.' );
	$s = $out['pp2']['settings'];
	$check( 'globals/colors?id=secondary' === $s['__globals__']['background_color'] && 'globals/typography?id=primary' === $s['__globals__']['typography_typography'] && str_contains( $s['__dynamic__']['product_name'], 'post-title' ) && array( 'unit' => 'px', 'size' => 15 ) === $s['typography_font_size_mobile'] && 'center' === $s['align_mobile'], 'Globales, etiquetas dinámicas y valores por dispositivo pasan tal cual.' );
	$check( 'selector .digi-pay__button{letter-spacing:1px} selector .elementor-message.digi-pay__message--global{font-style:italic} selector .digi-pay__button--sandbox{opacity:.9}' === $s['custom_css'], 'CSS personalizado con las clases propias: ' . $s['custom_css'] );
	$check( 'selector .digi-pay--stripe .digi-pay__message--remote{color:red} selector .digi-pay__button{border-radius:0}' === $out['st2']['settings']['custom_css'], 'CSS personalizado de Stripe con las clases propias: ' . $out['st2']['settings']['custom_css'] );
	foreach ( array( 'pp2' => 'paypal-button', 'st2' => 'stripe-button' ) as $id => $source ) {
		$widget = $widgets[ $source ];
		foreach ( array_keys( $out[ $id ]['settings'] ) as $key ) {
			if ( in_array( $key, array( '__globals__', '__dynamic__', 'custom_css', '_digitalisimo_pro' ), true ) || '_' === $key[0] ) {
				continue;
			}
			$base_key = preg_replace( '/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', '', $key );
			$in_group = false;
			foreach ( array_keys( $widget->groups ) as $group ) {
				$in_group = $in_group || 0 === strpos( $key, $group . '_' );
			}
			$check( isset( $widget->controls[ $base_key ] ) || $in_group, "$id: $key cae en un control propio." );
		}
	}
	$again = array();
	$check( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	// El mismo widget convertido pinta lo mismo que PRO con esos ajustes (campos ocultos de PayPal).
	$pp_settings = array_merge( $defaults( $pp ), array( 'hover_animation' => '' ), $out['pp2']['settings'] );
	unset( $pp_settings['__dynamic__'], $pp_settings['__globals__'] );
	$pro_settings = array_merge( $defaults( $pp_pro ), array( 'hover_animation' => '' ), $pro_tree[0]['elements'][1]['settings'] );
	unset( $pro_settings['__dynamic__'], $pro_settings['__globals__'] );
	$check( $hidden( $render( $pp, $pp_settings ) ) === $hidden( $render( $pp_pro, $pro_settings ) ), 'PayPal convertido: los mismos campos ocultos que el de PRO.' );

	// 9. Página de prueba de Chrome (tests/elements-pro-payments-chrome.mjs): el marcado del servidor de hoy, dentro de un
	// documento con `data-elementor-id` (el script lo envía como `postId`). Con `--write`, se regenera.
	$wrap = static function ( $name, $id, $html ) {
		return '<div class="elementor-element elementor-widget elementor-widget-' . $name . '" data-id="' . $id . '" data-widget_type="' . $name . '.default"><div class="elementor-widget-container">' . $html . "</div></div>\n";
	};
	$fp      = 'digitalisimo-paypal-button';
	$fs      = 'digitalisimo-stripe-button';
	$own_pp  = $defaults( $pp ) + array( 'hover_animation' => '' );
	$own_st  = $defaults( $st ) + array( 'hover_animation' => '' );
	$pp->id  = 'pp1';
	$a1      = $wrap( $fp, 'pp1', $render( $pp, array_merge( $own_pp, array( 'email' => 'ventas@ejemplo.test', 'product_name' => 'Camiseta', 'product_price' => '25', 'open_in_new_window' => '' ) ) ) );
	$pp->id  = 'pp2';
	$a2      = $wrap( $fp, 'pp2', $render( $pp, array_merge( $own_pp, array( 'email' => '' ) ) ) );
	$st->id  = 'st1';
	$a3      = $wrap( $fs, 'st1', $render( $st, array_merge( $own_st, array( 'product_name' => 'Curso', 'stripe_product_price' => '99' ) ) ) );
	$st->id  = 'st2';
	$a4      = $wrap( $fs, 'st2', $render( $st, array_merge( $own_st, array( 'product_name' => 'Curso', 'stripe_product_price' => '99', 'open_in_new_window' => '', 'custom_messages' => 'yes', 'error_message_global' => 'Error general', 'error_message_payment' => 'Sin pasarela' ) ) ) );
	$st->id  = 'st3';
	$a5      = $wrap( $fs, 'st3', $render( $st, array_merge( $own_st, array( 'product_name' => '' ) ) ) );
	$fixture  = "<!doctype html>\n<html lang=\"es\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width\"><title>Pagos</title>\n";
	$fixture .= "<!-- Generada por tests/elements-pro-payments.php (--write); la usa tests/elements-pro-payments-chrome.mjs. -->\n";
	$fixture .= "<style>body{margin:0;font:16px/1.5 sans-serif}.elementor-widget{margin:0 0 20px}.elementor-button{display:inline-block;padding:12px 24px;background:#032E82;color:#fff;border:0;font:inherit}</style>\n";
	$fixture .= "<link rel=\"stylesheet\" href=\"../../digitalisimo-elements/modules/digitalisimo-services/assets/css/payment-button.css\">\n</head><body>\n";
	$fixture .= '<div data-elementor-id="321" class="elementor elementor-321">' . "\n" . $a1 . $a2 . $a3 . $a4 . $a5 . "</div>\n";
	$fixture .= "<script src=\"../../digitalisimo-elements/modules/digitalisimo-services/assets/js/payment-button.js\" defer></script>\n</body></html>\n";
	$path = __DIR__ . '/fixtures/pro-payments.html';
	if ( in_array( '--write', $argv ?? array(), true ) ) {
		file_put_contents( $path, $fixture );
	}
	$check( is_file( $path ) && file_get_contents( $path ) === $fixture, 'tests/fixtures/pro-payments.html es el marcado de hoy (regenerar con --write).' );

	echo "OK elements-pro-payments: $checks comprobaciones; hoja " . strlen( $css ) . ' B, script ' . strlen( $js ) . " B.\n";
}
