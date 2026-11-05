<?php
/**
 * Mi cuenta y Resumen de la compra propios (C7 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c7.md, frente N, fila
 * O): `digitalisimo-woo-my-account` y `digitalisimo-woo-purchase-summary` (`modules/digitalisimo-woo/store/`) frente a los
 * widgets de PRO que sustituyen (`modules/woocommerce/widgets/my-account.php`, `purchase-summary.php`) y sus mapas.
 *
 * Carga a la vez los archivos reales de PRO y los propios sobre un Elementor mínimo que anota los controles, y comprueba:
 * - registro y recursos: nombre, título, categoría de PRO, línea en `Woo\Registry`, hojas ≤ 4 KB (dos por widget) sin el
 *   kit ni `url()`, con foco visible y sin colores que PRO no usara; scripts ≤ 8 KB sin jQuery; nada vetado por el
 *   empaquetado ni dominios de proveedores;
 * - controles y grupos: los mismos que PRO, en el mismo orden, con el mismo tipo, valor por defecto (también por
 *   dispositivo), condiciones, opciones, rangos y selectores, con las clases y variables de PRO traducidas a las propias;
 *   sólo cambian los textos (en español) y los textos por defecto que el mapa completa;
 * - render: envolturas propias, los mismos ganchos que PRO y ninguno colgado al terminar, textos de `gettext`;
 * - mapas: cada control de PRO declarado y conversión de ajustes reales sin `pro.unmapped`, con cada clave en un control
 *   propio.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['ac'] = array( 'filters' => array(), 'shortcodes' => array(), 'edit' => false, 'logged' => true, 'qv' => array(), 'orders' => 1 );
	function check_ac( $ok, $message ) {
		if ( ! $ok ) {
			echo "FALLA: $message\n";
			throw new RuntimeException( $message );
		}
	}
}
namespace Elementor {
	$src = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/elementor/includes/managers/controls.php' );
	preg_match_all( "/const ([A-Z_]+) = '([^']*)';/", $src, $m, PREG_SET_ORDER );
	$consts = '';
	foreach ( $m as $c ) {
		$consts .= "const {$c[1]} = '{$c[2]}';";
	}
	eval( 'namespace Elementor; class Controls_Manager { ' . $consts . ' }' ); // phpcs:ignore -- constantes reales del núcleo.
	class Controls_Stack { const RESPONSIVE_DESKTOP = 'desktop'; const RESPONSIVE_TABLET = 'tablet'; const RESPONSIVE_MOBILE = 'mobile'; }
	class Repeater {
		public $controls = array();
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function get_controls() { return $this->controls; }
	}
	foreach ( array( 'Typography', 'Text_Shadow', 'Box_Shadow', 'Border', 'Background' ) as $g ) {
		eval( "namespace Elementor; class Group_Control_$g { public static function get_type() { return '" . strtolower( $g ) . "'; } }" ); // phpcs:ignore
	}
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $order    = array();
		private $section = '';
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function get_settings( $key = null ) { return $this->get_settings_for_display( $key ); }
		public function start_controls_section( $name, $args ) { $this->section = $name; $this->order[] = 'section:' . $name; }
		public function end_controls_section() { $this->section = ''; }
		public function start_controls_tabs( $name, $args = array() ) { $this->order[] = 'tabs:' . $name; }
		public function start_controls_tab( $name, $args ) { $this->order[] = 'tab:' . $name; }
		public function end_controls_tab() {}
		public function end_controls_tabs() {}
		public function add_control( $name, $args, $options = array() ) { $this->controls[ $name ] = $args + array( '_section' => $this->section ); $this->order[] = $name; }
		public function add_responsive_control( $name, $args, $options = array() ) { $this->add_control( $name, $args + array( '_responsive' => true ) ); }
		public function add_group_control( $type, $args, $options = array() ) { $this->groups[ $args['name'] ] = array( '_type' => $type, '_section' => $this->section ) + $args; $this->order[] = 'group:' . $args['name']; }
		public function get_id() { return 'abc1234'; }
		public function init() { $this->register_controls(); return $this; }
	}
	class Plugin {
		public static $instance;
		public $documents;
		public $experiments;
		public $editor;
		public $preview;
	}
}
namespace Elementor\Core\Base {
	class Document { const TYPE_META_KEY = '_elementor_template_type'; }
}
namespace Elementor\Core\Kits\Documents\Tabs {
	class Global_Typography { const TYPOGRAPHY_PRIMARY = 'globals/typography?id=primary'; const TYPOGRAPHY_SECONDARY = 'globals/typography?id=secondary'; const TYPOGRAPHY_TEXT = 'globals/typography?id=text'; const TYPOGRAPHY_ACCENT = 'globals/typography?id=accent'; }
	class Global_Colors { const COLOR_PRIMARY = 'globals/colors?id=primary'; const COLOR_SECONDARY = 'globals/colors?id=secondary'; const COLOR_TEXT = 'globals/colors?id=text'; const COLOR_ACCENT = 'globals/colors?id=accent'; }
}
namespace ElementorPro\Modules\QueryControl {
	class Module { const QUERY_CONTROL_ID = 'query'; const QUERY_OBJECT_LIBRARY_TEMPLATE = 'library_template'; }
}
namespace ElementorPro\Base {
	abstract class Base_Widget extends \Elementor\Widget_Base {}
}
namespace ElementorPro {
	class Plugin { public static function elementor() { return \Elementor\Plugin::$instance; } }
}
namespace ElementorPro\Modules\Woocommerce {
	class Module { public static function is_preview() { return false; } }
}
namespace ElementorPro\Modules\Woocommerce\Widgets {
	// Base de PRO reducida a lo que usan estos dos widgets (`base-widget.php`: tipos de borde y `filter_gettext()`).
	abstract class Base_Widget extends \ElementorPro\Base\Base_Widget {
		protected $gettext_modifications;
		public static function get_custom_border_type_options() { return array( 'none' => 'None', 'solid' => 'Solid', 'double' => 'Double', 'dotted' => 'Dotted', 'dashed' => 'Dashed', 'groove' => 'Groove' ); }
		protected function init_gettext_modifications() { $this->gettext_modifications = array(); }
		public function filter_gettext( $translation, $text, $domain ) {
			if ( ! isset( $this->gettext_modifications ) ) {
				$this->init_gettext_modifications();
			}
			return array_key_exists( $text, $this->gettext_modifications ) ? $this->gettext_modifications[ $text ] : $translation;
		}
	}
}
namespace Digitalisimo\Elements\Woo {
	class Widget extends \Elementor\Widget_Base {
		protected function render() { $this->render_widget(); }
		public function run() { ob_start(); $this->render(); return preg_replace( '/>\s+</', '><', trim( (string) ob_get_clean() ) ); }
	}
	class Registry {
		const WIDGETS = REGISTRY_WIDGETS;
		public static function style_depends( string $name ): array { return is_file( Assets::path( self::WIDGETS[ $name ]['css'] ) ) ? array( $name ) : array(); }
		public static function script_depends( string $name ): array { return is_file( Assets::path( self::WIDGETS[ $name ]['js'] ) ) ? array( $name ) : array(); }
	}
	class Assets {
		public static function path( string $p ): string { return dirname( __DIR__ ) . '/digitalisimo-elements/' . $p; }
		public static function url( string $p ): string { return 'https://sitio.test/' . $p; }
	}
}
namespace {
	// Registro real (sólo las entradas de este frente; la clase de `Woo\Registry` no se carga entera).
	$registry_src = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/class-registry.php' );
	$entries      = array();
	foreach ( array( 'my-account' => 'My_Account', 'purchase-summary' => 'Purchase_Summary' ) as $slug => $class ) {
		check_ac( preg_match( "/'digitalisimo-woo-$slug' => array\( 'file' => '([^']+)', 'class' => Store\\\\$class::class, 'css' => '([^']+)', 'js' => '([^']+)' \)/", $registry_src, $e ), "Woo\\Registry: falta la línea de digitalisimo-woo-$slug." );
		$entries[ "digitalisimo-woo-$slug" ] = array( 'file' => $e[1], 'css' => $e[2], 'js' => $e[3] );
		check_ac( preg_match( "/'digitalisimo-woo-$slug' => '([^']+)',/", $registry_src, $t ), "Woo\\Registry: falta el título de digitalisimo-woo-$slug." );
		$entries[ "digitalisimo-woo-$slug" ]['title'] = $t[1];
	}
	define( 'REGISTRY_WIDGETS', $entries );

	// WordPress y WooCommerce mínimos.
	function is_rtl() { return false; }
	function __( $t, $d = '' ) { return $t; }
	function esc_html__( $t, $d = '' ) { return $t; }
	function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $t ) { return (string) $t; }
	function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
	function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
	function get_permalink() { return 'https://sitio.test/cuenta/'; }
	function is_user_logged_in() { return $GLOBALS['ac']['logged']; }
	function get_current_user_id() { return 7; }
	function get_user_by( $f, $v ) { return (object) array( 'ID' => 7 ); }
	function is_preview() { return false; }
	function function_exists_stub() {}
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['ac']['filters'][ $hook ][ $prio ][] = $cb; return true; }
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return add_filter( $hook, $cb, $prio, $args ); }
	function remove_filter( $hook, $cb, $prio = 10 ) {
		foreach ( $GLOBALS['ac']['filters'][ $hook ][ $prio ] ?? array() as $i => $f ) {
			if ( $f === $cb ) {
				unset( $GLOBALS['ac']['filters'][ $hook ][ $prio ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function remove_action( $hook, $cb, $prio = 10 ) { return remove_filter( $hook, $cb, $prio ); }
	function ac_hooks() {
		$out = array();
		foreach ( $GLOBALS['ac']['filters'] as $hook => $by ) {
			foreach ( $by as $prio => $list ) {
				foreach ( $list as $cb ) {
					$out[] = $hook . '@' . $prio . ':' . ( is_array( $cb ) ? ( is_object( $cb[0] ) ? 'this' : $cb[0] ) . '->' . $cb[1] : (string) $cb );
				}
			}
		}
		sort( $out );
		return $out;
	}
	function apply_filters( $hook, $value, ...$args ) {
		ksort( $GLOBALS['ac']['filters'][ $hook ] );
		foreach ( $GLOBALS['ac']['filters'][ $hook ] ?? array() as $list ) {
			foreach ( $list as $cb ) {
				$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
			}
		}
		return $value;
	}
	function do_action( $hook, ...$args ) {
		$by = $GLOBALS['ac']['filters'][ $hook ] ?? array();
		ksort( $by );
		foreach ( $by as $list ) {
			foreach ( $list as $cb ) {
				call_user_func_array( $cb, $args );
			}
		}
	}
	function woocommerce_account_content() { echo '<p>contenido</p>'; }
	// `[woocommerce_my_account]` y `[woocommerce_checkout]` simulados: los mismos ganchos que las plantillas.
	function do_shortcode( $code ) {
		$GLOBALS['ac']['shortcodes'][] = $code;
		ob_start();
		if ( '[woocommerce_my_account]' === $code ) {
			echo '<div class="woocommerce">';
			do_action( 'woocommerce_account_navigation' );
			echo '<nav class="woocommerce-MyAccount-navigation"><ul>';
			foreach ( apply_filters( 'woocommerce_account_menu_items', array( 'dashboard' => 'Dashboard', 'orders' => 'Orders', 'customer-logout' => 'Log out' ), array() ) as $k => $v ) {
				echo '<li class="woocommerce-MyAccount-navigation-link--' . $k . '"><a href="' . apply_filters( 'woocommerce_get_myaccount_page_permalink', '/x/' ) . '">' . $v . '</a></li>';
			}
			echo '</ul></nav><div class="woocommerce-MyAccount-content">';
			do_action( 'woocommerce_account_content' );
			echo '</div></div>';
		} elseif ( '[woocommerce_checkout]' === $code ) {
			echo '<div class="woocommerce"><p class="woocommerce-thankyou-order-received">' . apply_filters( 'woocommerce_thankyou_order_received_text', 'Thank you. Your order has been received.' ) . '</p>';
			echo '<li>' . apply_filters( 'gettext', 'Order number:', 'Order number:', 'woocommerce' ) . '</li></div>';
		}
		return (string) ob_get_clean();
	}
	function wc_get_customer_order_count( $id ) { return $GLOBALS['ac']['orders']; }
	function wc_get_customer_available_downloads( $id ) { return array(); }
	function wc_get_orders( $a ) { return array(); }
	function wc_get_order( $id ) { return $id ? (object) array( 'id' => $id ) : false; }
	function wc_get_template( $t, $a = array() ) { echo '<nav class="tpl"></nav>'; }
	function WC() { return (object) array( 'payment_gateways' => new class() { public function get_available_payment_gateways() { return array(); } } ); }
	class WP_Query_Stub { public $query = array( 'pagename' => 'cuenta' ); public $query_vars = array( 'page' => '' ); }
	$GLOBALS['wp_query'] = new WP_Query_Stub();
	add_action( 'woocommerce_account_content', 'woocommerce_account_content' );
	$GLOBALS['wp']       = (object) array( 'query_vars' => array() );

	\Elementor\Plugin::$instance              = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->documents   = new class() { public function get_document_types( $a = array() ) { return array( 'page' => 1, 'section' => 1 ); } };
	\Elementor\Plugin::$instance->experiments = new class() { public function is_feature_active( $f ) { return false; } };
	\Elementor\Plugin::$instance->editor      = new class() { public function is_edit_mode() { return $GLOBALS['ac']['edit']; } };
	\Elementor\Plugin::$instance->preview     = new class() { public function is_preview_mode() { return false; } };

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/woocommerce/widgets/my-account.php';
	require $root . 'modules/woocommerce/widgets/purchase-summary.php';
	require $root . 'modules/digitalisimo-woo/store/class-base.php';
	require $root . 'modules/digitalisimo-woo/store/class-my-account.php';
	require $root . 'modules/digitalisimo-woo/store/class-purchase-summary.php';

	$checks = 0;
	$check  = static function ( $ok, $message ) use ( &$checks ) {
		++$checks;
		check_ac( $ok, $message );
	};

	/** Traducciones de PRO a lo propio: clases y variables (las de la hoja de cada widget). */
	$var_maps = array();
	foreach ( array( 'my-account', 'purchase-summary' ) as $slug ) {
		$var_maps[ $slug ] = array();
		foreach ( array( '', '-2' ) as $part ) {
			$file = $root . "modules/digitalisimo-woo/store/assets/$slug$part.css";
			if ( is_file( $file ) ) {
				preg_match_all( '/(?<![a-z0-9_-])--d[ap]-[a-z0-9]+/', (string) file_get_contents( $file ), $found );
				$var_maps[ $slug ] = array_merge( $var_maps[ $slug ], $found[0] );
			}
		}
	}
	$classes = array(
		'my-account'       => array( 'e-my-account-tab__dashboard--custom' => 'digi-acc--custom', 'e-my-account-tabs-' => 'digi-acc-tabs-', 'e-my-account-tab__' => 'digi-acc--', 'e-my-account-tab' => 'digi-acc', 'e-wc-account-tabs-nav' => 'digi-acc__nav', 'woocommerce-MyAccount-content-wrapper' => 'digi-acc__c' ),
		'purchase-summary' => array(),
	);
	/** Quita los textos (etiquetas, títulos, descripciones) y traduce clases; las variables se comparan por su uso. */
	$normalize = static function ( $value, $slug, $key = '' ) use ( &$normalize, $classes ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				if ( in_array( (string) $k, array( 'label', 'title', 'description', 'raw', 'content', 'heading', 'label_on', 'label_off', 'placeholder', 'separator_label', 'field_label', 'tab_name', '_responsive' ), true ) ) {
					continue;
				}
				$out[ is_string( $k ) ? strtr( $k, $classes[ $slug ] ) : $k ] = ( 'options' === $key && is_string( $v ) ) ? 'OPCIÓN' : $normalize( $v, $slug, (string) $k );
			}
			return $out;
		}
		if ( is_string( $value ) ) {
			$value = strtr( $value, $classes[ $slug ] );
			// Variables: `--da-xyz`/`--dp-xyz` propias y `--nombre-largo` de PRO se comparan por su forma (VAR).
			$value = preg_replace( '/(?<![a-z0-9_-])--[a-z][a-z0-9-]*[a-z0-9]/', 'VAR', $value );
		}
		return $value;
	};

	$pairs = array(
		'my-account'       => array( \ElementorPro\Modules\Woocommerce\Widgets\My_Account::class, \Digitalisimo\Elements\Woo\Store\My_Account::class, 'woocommerce-my-account' ),
		'purchase-summary' => array( \ElementorPro\Modules\Woocommerce\Widgets\Purchase_Summary::class, \Digitalisimo\Elements\Woo\Store\Purchase_Summary::class, 'woocommerce-purchase-summary' ),
	);
	// Textos por defecto propios en español (el mapa completa los de PRO en lo convertido).
	$spanish_defaults = array( 'confirmation_message_text', 'payment_details_number', 'payment_details_date', 'payment_details_email', 'payment_details_total', 'payment_details_payment', 'bank_details_text', 'downloads_text', 'order_summary_text', 'billing_details_text', 'shipping_details_text' );
	$widgets = array();
	foreach ( $pairs as $slug => list( $pro_class, $own_class, $source ) ) {
		$pro  = ( new $pro_class() )->init();
		$own  = ( new $own_class() )->init();
		$name = 'digitalisimo-woo-' . $slug;
		$widgets[ $slug ] = array( $pro, $own );
		$entry = REGISTRY_WIDGETS[ $name ];
		$check( $name === $own->get_name() && $entry['title'] === $own->get_title() && $pro->get_categories() === $own->get_categories() && 'store/class-' . $slug . '.php' === $entry['file'], "$name: nombre, título, categoría de PRO y línea en Woo\\Registry." );
		$check( $pro->order === $own->order, "$name: el mismo orden de secciones, pestañas, controles y grupos que PRO." );
		$check( count( $pro->controls ) === count( $own->controls ) && count( $pro->groups ) === count( $own->groups ), "$name: el mismo número de controles (" . count( $own->controls ) . ') y grupos (' . count( $own->groups ) . ').' );
		foreach ( $pro->controls as $key => $args ) {
			$mine = $own->controls[ $key ] ?? null;
			$check( null !== $mine, "$name: falta el control $key." );
			$a = $normalize( $args, $slug );
			$b = $normalize( $mine, $slug );
			if ( in_array( $key, $spanish_defaults, true ) ) {
				$check( is_string( $mine['default'] ) && '' !== $mine['default'] && $mine['default'] !== $args['default'], "$name: $key con su texto por defecto propio." );
				unset( $a['default'], $b['default'] );
			}
			if ( 'tabs' === $key ) {
				$check( array_column( $args['default'], 'field_key' ) === array_column( $mine['default'], 'field_key' ), "$name: las mismas pestañas fijas." );
				unset( $a['default'], $b['default'] );
			}
			$check( $a === $b, "$name: control $key distinto de PRO:\nPRO " . json_encode( $a ) . "\nPROPIO " . json_encode( $b ) );
		}
		foreach ( $pro->groups as $key => $args ) {
			$check( isset( $own->groups[ $key ] ) && $normalize( $args, $slug ) === $normalize( $own->groups[ $key ], $slug ), "$name: grupo $key distinto de PRO." );
		}
		// Las variables de PRO pasan a las propias de la hoja, y cada variable propia que fija un control la lee la hoja.
		$own_vars = array();
		foreach ( array_merge( $own->controls, $own->groups ) as $args ) {
			preg_match_all( '/(?<![a-z0-9_-])--[a-z][a-z0-9-]*[a-z0-9]/', json_encode( $args ), $found );
			$own_vars = array_merge( $own_vars, $found[0] );
		}
		$own_vars = array_unique( $own_vars );
		$check( ! array_diff( $own_vars, $var_maps[ $slug ] ), "$name: variables de los controles que la hoja no lee: " . implode( ', ', array_diff( $own_vars, $var_maps[ $slug ] ) ) );
		$check( ! preg_grep( '/^--(?!d[ap]-)/', $own_vars ), "$name: los controles sólo fijan variables propias (--da-/--dp-)." );

		// Recursos.
		$css_total = 0;
		foreach ( array( '', '-2' ) as $part ) {
			$file = $root . "modules/digitalisimo-woo/store/assets/$slug$part.css";
			if ( ! is_file( $file ) ) {
				continue;
			}
			$css        = (string) file_get_contents( $file );
			$css_total += strlen( $css );
			$check( strlen( $css ) <= 4096, "$slug$part.css pesa " . strlen( $css ) . ' B (tope 4 096).' );
			$check( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $css, '.e-my-account' ) && ! str_contains( $css, 'elementor-widget-woocommerce' ), "$slug$part.css: sin el kit, url() ni clases de PRO." );
			preg_match_all( '/#[0-9a-f]{3,8}\b/i', $css, $hex );
			$check( ! array_diff( array_map( 'strtolower', $hex[0] ), array( '#69727d', '#000', '#fff', '#5bc0de', '#d5d8dc', '#f9fafa', '#f1f2f3' ) ), "$slug$part.css: sólo colores que PRO usaba por defecto." );
		}
		$all_css = (string) file_get_contents( $root . "modules/digitalisimo-woo/store/assets/$slug.css" ) . (string) @file_get_contents( $root . "modules/digitalisimo-woo/store/assets/$slug-2.css" );
		$check( str_contains( $all_css, ':focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $all_css, 'prefers-reduced-motion' ), "$name: foco visible de 2 px con separación de 2 px y movimiento reducido." );
		$js = (string) file_get_contents( $root . $entry['js'] );
		$check( strlen( $js ) <= 8192 && ! preg_match( '/jQuery|\$\(|\$\./', $js ), "$name: script ≤ 8 KB y sin jQuery." );
		foreach ( array( $root . 'modules/digitalisimo-woo/' . $entry['file'], $root . $entry['css'], $root . $entry['js'], $root . 'modules/digitalisimo-convert/maps/' . $source . '.php' ) as $file ) {
			$text = (string) file_get_contents( $file );
			$check( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $text ), basename( $file ) . ': nombra algo que el empaquetado veta.' );
			$check( ! preg_match( '/elementor\.com|go\.elementor/i', $text ), basename( $file ) . ': sin dominios del proveedor.' );
		}
		$code = (string) file_get_contents( $root . 'modules/digitalisimo-woo/' . $entry['file'] );
		$check( ! preg_match( '/<script|wp_register_script|pro-elements-handlers|elementor-pro-frontend|ElementorPro\\\\Modules\\\\Woocommerce|\bPlugin::elementor/', $code ), "$name: sin <script>, sin paquetes ni clases del módulo de PRO." );
	}
	$check( 8192 >= filesize( $root . 'modules/digitalisimo-woo/store/assets/my-account.css' ) + filesize( $root . 'modules/digitalisimo-woo/store/assets/my-account-2.css' ), 'Mi cuenta: dos hojas como mucho.' );

	// 2. Render de Mi cuenta: envoltura, ganchos de PRO y ninguno colgado al terminar.
	list( $pro, $own ) = $widgets['my-account'];
	$own->settings = array( 'tabs_layout' => 'horizontal', 'tabs' => array( array( 'field_key' => 'dashboard', 'tab_name' => 'Inicio' ), array( 'field_key' => 'orders', 'tab_name' => 'Mis pedidos' ) ), 'customize_dashboard_check' => '', 'customize_dashboard_select' => '' );
	$before = ac_hooks();
	$html   = $own->run();
	$check( ac_hooks() === $before, 'Mi cuenta: ningún gancho queda colgado al terminar (PRO quitaba tres con otra prioridad).' );
	$check( str_starts_with( $html, '<div class="digi-acc digi-acc--dashboard digi-acc-s">' ) && str_contains( $html, '<div class="digi-acc__nav"><nav class="tpl"></nav>' ), 'Mi cuenta: envoltura propia en el escritorio y navegación horizontal.' );
	$check( str_contains( $html, '>Inicio</a>' ) && str_contains( $html, '>Mis pedidos</a>' ) && str_contains( $html, 'href="https://sitio.test/cuenta/"' ), 'Mi cuenta: nombres de las pestañas y enlaces a la página del widget, como PRO.' );
	$check( str_contains( $html, '<div class="digi-acc__c digi-acc__box"><p>contenido</p></div>' ), 'Mi cuenta: contenido en su caja.' );
	$GLOBALS['wp_query']->query['orders'] = $GLOBALS['wp_query']->query_vars['orders'] = '';
	$GLOBALS['ac']['orders']               = 0;
	$html = $own->run();
	$check( str_contains( $html, 'digi-acc digi-acc--orders digi-acc-s' ) && str_contains( $html, '<div class="digi-acc__c--empty">' ), 'Mi cuenta: pedidos sin pedidos, sin caja (`-no-data` de PRO).' );
	unset( $GLOBALS['wp_query']->query['orders'], $GLOBALS['wp_query']->query_vars['orders'] );
	$GLOBALS['ac']['orders'] = 1;
	$own->settings['customize_dashboard_check']  = 'yes';
	$own->settings['customize_dashboard_select'] = '45';
	$html = $own->run();
	$check( str_contains( $html, 'digi-acc digi-acc--dashboard digi-acc--custom' ) && in_array( '[elementor-template id="45"]', $GLOBALS['ac']['shortcodes'], true ) && str_contains( $html, '<div class="digi-acc__c">' ), 'Mi cuenta: plantilla propia en el escritorio, sin caja.' );
	$check( ac_hooks() === $before, 'Mi cuenta con plantilla: ningún gancho colgado y `woocommerce_account_content` de vuelta.' );
	$check( 'https://sitio.test/x/?elementor_wc_logout=true&elementor_my_account_redirect=https://sitio.test/cuenta/' === $own->woocommerce_logout_default_redirect_url( 'https://sitio.test/x/' ), 'Mi cuenta: salida con los mismos parámetros que PRO (los atiende el módulo de tienda del constructor).' );

	// 3. Render del Resumen de la compra.
	list( , $sum ) = $widgets['purchase-summary'];
	$sum->settings = array( 'confirmation_message_text' => 'Gracias de prueba', 'payment_details_number' => 'Número:', 'preview_order_type' => '' );
	$before        = ac_hooks();
	$check( '' === $sum->run(), 'Resumen: sin pedido recibido no pinta nada en el sitio (como PRO).' );
	$GLOBALS['wp']->query_vars['order-received'] = '30';
	$html = $sum->run();
	$check( str_starts_with( $html, '<div class="digi-psum"><div class="woocommerce">' ) && str_contains( $html, '>Gracias de prueba</p>' ) && str_contains( $html, '<li>Número:</li>' ), 'Resumen: envoltura propia, mensaje y títulos del widget.' );
	$check( ac_hooks() === $before, 'Resumen: ningún gancho colgado.' );

	// 4. Mapas: cobertura y conversión.
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	foreach ( $pairs as $slug => list( $pro_class, $own_class, $source ) ) {
		list( $pro, $own ) = $widgets[ $slug ];
		$map      = \Digitalisimo\Elements\Convert\Registry::map( $source, true );
		$check( null !== $map && 'digitalisimo-woo-' . $slug === $map['target'] && 'hide' === $map['panel'], "$source: mapa hacia el propio, oculto del panel." );
		$declared = array_merge( (array) $map['keep'], array_keys( (array) $map['groups'] ), (array) ( $map['ui'] ?? array() ), array_keys( (array) ( $map['repeaters'] ?? array() ) ) );
		foreach ( (array) ( $map['repeaters'] ?? array() ) as $spec ) {
			$declared = array_merge( $declared, (array) $spec['keep'] );
		}
		$names = array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ), array( 'tab_name', 'order_display_description' ) );
		$names = 'my-account' === $slug ? $names : array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) );
		$check( ! array_diff( $names, $declared ), "$source: el mapa no declara " . implode( ', ', array_diff( $names, $declared ) ) );
		// Ajustes completos: un valor por control (y por dispositivo), grupos, globales, dinámicos y CSS personalizado.
		$settings = array( 'custom_css' => 'selector .e-my-account-tab__orders .woocommerce-MyAccount-content-wrapper{color:red} selector .e-wc-account-tabs-nav{margin:0}' );
		foreach ( $pro->controls as $key => $args ) {
			if ( in_array( $args['type'], array( 'heading', 'raw_html', 'divider', 'alert' ), true ) ) {
				continue;
			}
			$settings[ $key ] = 'repeater' === $args['type'] ? array( array( '_id' => 'r1', 'field_key' => 'orders', 'tab_name' => 'Pedidos' ) ) : 'x';
			if ( ! empty( $args['_responsive'] ) ) {
				$settings[ $key . '_mobile' ] = 'y';
			}
		}
		foreach ( array_keys( $pro->groups ) as $group ) {
			$settings[ $group . '_typography' ] = 'custom';
			$settings[ $group . '_font_size_tablet' ] = array( 'size' => 12, 'unit' => 'px' );
		}
		$first_group                = array_key_first( $pro->groups );
		$settings['__globals__']    = array( $first_group . '_typography' => 'globals/typography?id=primary' );
		$settings['__dynamic__']    = array( array_key_first( $pro->controls ) => '[elementor-tag x]' );
		list( $out, $codes )        = \Digitalisimo\Elements\Convert\Translator::translate( $settings, $map );
		$unmapped                   = array_filter( $codes, static function ( $c ) { return 'pro.unmapped' === $c['code']; } );
		$check( ! $unmapped, "$source: conversión sin claves sin declarar: " . json_encode( array_values( $unmapped ) ) );
		foreach ( array_keys( $out ) as $key ) {
			if ( in_array( $key, array( 'custom_css', '__globals__', '__dynamic__', '_digitalisimo_pro' ), true ) ) {
				continue;
			}
			$base = preg_replace( '/_mobile$/', '', $key );
			$in   = isset( $own->controls[ $base ] ) || (bool) array_filter( array_keys( $own->groups ), static function ( $g ) use ( $base ) { return 0 === strpos( $base, $g . '_' ); } );
			$check( $in, "$source: «{$key}» no es un control propio." );
		}
		if ( 'my-account' === $slug ) {
			$check( 'selector .digi-acc--orders .digi-acc__c{color:red} selector .digi-acc__nav{margin:0}' === $out['custom_css'], 'Mi cuenta: el CSS personalizado pasa a las clases propias.' );
			list( $bare ) = \Digitalisimo\Elements\Convert\Translator::translate( array(), $map );
			$check( array( 'dashboard', 'orders', 'downloads', 'edit-address', 'edit-account', 'customer-logout' ) === array_column( $bare['tabs'], 'field_key' ) && 'Dashboard' === $bare['tabs'][0]['tab_name'], 'Mi cuenta: sin pestañas guardadas, llegan las de PRO con sus textos.' );
		} else {
			list( $bare ) = \Digitalisimo\Elements\Convert\Translator::translate( array(), $map );
			$check( 'Thank You. Your order has been received.' === $bare['confirmation_message_text'] && 'Order Number:' === $bare['payment_details_number'], 'Resumen: sin textos guardados, llegan los de PRO.' );
		}
	}
	echo "DIGITALÍSIMO Elements: Mi cuenta y Resumen de la compra propios frente a PRO ($checks comprobaciones) en verde.\n";
}
