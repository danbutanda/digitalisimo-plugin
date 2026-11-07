<?php
namespace Digitalisimo\Elements\Woo\Store;

use Digitalisimo\Elements\Woo\Registry;
use Digitalisimo\Elements\Woo\Widget;
use Elementor\Controls_Manager;
use Elementor\Controls_Stack;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los widgets de tienda de C7 (frentes N y O, `docs/pro/c7.md`): listados y archivo, carrito, pago, cuenta,
 * resumen de la compra, carrito del menú y páginas de la tienda. Repite las utilidades que los widgets de PRO heredan de
 * `modules/woocommerce/widgets/base-widget.php` (columnas por dispositivo, tipos de borde, textos sustituidos de
 * WooCommerce, funciones de WooCommerce activas), sin cargar ninguna clase de PRO, y sigue el contrato de `Woo\Widget`:
 * la subclase implementa `render_widget()`; sin WooCommerce, `Inert` la registra oculta con sus mismos controles.
 *
 * Diferencia con el contrato de F4-Woo (D21, «el HTML no depende del visitante»): estos widgets pintan lo mismo que los
 * de PRO, que sí depende de la persona (carrito, cuenta, pedido, avisos). WooCommerce pinta lo suyo: carrito, pago,
 * cuenta, avisos y migas salen de sus plantillas y ganchos, con sus filtros; el widget pone envoltura, estilos y controles.
 *
 * Recursos: la hoja y el script del widget (`store/assets/<widget sin digitalisimo-woo->.css|.js`), si existen, por
 * `Registry::style_depends()`/`script_depends()`.
 */
abstract class Base extends Widget {
	/** Columnas y filas por defecto de las cuadrículas de productos (`Products_Renderer::DEFAULT_COLUMNS_AND_ROWS` de PRO). */
	const DEFAULT_COLUMNS_AND_ROWS = 4;

	/** @var array<string,string>|null Textos de WooCommerce sustituidos por los de los controles (`filter_gettext()`). */
	protected $gettext_modifications;

	public function get_categories() {
		return array( 'woocommerce-elements' );
	}

	/**
	 * Hoja del widget y, si existen, su segunda y su tercera hoja (`store/assets/<widget sin digitalisimo-woo->-2.css` y
	 * `-3.css`, handles `<widget>-2` y `<widget>-3`, cada una detrás de la anterior): las páginas de WooCommerce de PRO
	 * (carrito, pago, cuenta, resumen y carrito del menú) traen de 11 a 36 KB de estilos y no caben en una hoja de 4 KB.
	 * Cada hoja sigue en su tope de 4 KB; las adicionales se registran aquí, la primera vez que el núcleo pide los
	 * recursos del widget. Sólo el carrito y la cuenta llevan tercera hoja (excepción declarada en docs/pro/c7.md).
	 */
	public function get_style_depends(): array {
		$handles = Registry::style_depends( $this->get_name() );
		$slug    = substr( $this->get_name(), strlen( 'digitalisimo-woo-' ) );
		foreach ( array( 2, 3 ) as $n ) {
			$extra = 'modules/digitalisimo-woo/store/assets/' . $slug . '-' . $n . '.css';
			if ( ! $handles || ! is_file( \Digitalisimo\Elements\Woo\Assets::path( $extra ) ) || ! function_exists( 'wp_register_style' ) ) {
				break;
			}
			$handle = $this->get_name() . '-' . $n;
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, \Digitalisimo\Elements\Woo\Assets::url( $extra ), array( end( $handles ) ), defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false );
			}
			$handles[] = $handle;
		}
		return $handles;
	}

	public function get_script_depends(): array {
		return Registry::script_depends( $this->get_name() );
	}

	public function render_plain_content() {}

	public function get_group_name() {
		return 'woocommerce';
	}

	/** ¿Está activo el marcado optimizado del núcleo (`e_optimized_markup`)? */
	public static function optimized_markup(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$experiments = isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
		return is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && (bool) $experiments->is_feature_active( 'e_optimized_markup' );
	}

	/**
	 * ¿Es lo convertido de un widget de PRO? Lo dice la marca del traductor (`Convert\Translator::MARKER`), que dejan
	 * «Migrar» y los kits, también en lo ya migrado. Los textos fijos que no son controles usan entonces los de PRO, con su
	 * msgid y su dominio (o la traducción del paquete de idioma de PRO); en lo nuevo, los propios.
	 */
	protected function from_pro(): bool {
		return '' !== (string) $this->get_settings( '_digitalisimo_pro' );
	}

	/** ¿Se está editando o previsualizando en el editor? */
	public static function is_editor(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	/** Argumentos de dispositivo de PRO (`get_devices_default_args()`): cada punto de corte hereda del mayor. */
	protected function get_devices_default_args() {
		$devices = array();
		if ( class_exists( '\Elementor\Core\Breakpoints\Manager' ) ) {
			foreach ( \Elementor\Core\Breakpoints\Manager::get_default_config() as $breakpoint => $config ) {
				$devices[ $breakpoint ] = array( 'required' => false );
			}
		}
		return $devices;
	}

	/** Columnas por dispositivo (`columns`), como `add_columns_responsive_control()` de PRO. */
	protected function add_columns_responsive_control() {
		$this->add_responsive_control(
			'columns',
			array(
				'label'               => esc_html__( 'Columnas', 'pro-elements' ),
				'type'                => Controls_Manager::NUMBER,
				'prefix_class'        => 'elementor-grid%s-',
				'min'                 => 1,
				'max'                 => 12,
				'default'             => self::DEFAULT_COLUMNS_AND_ROWS,
				'tablet_default'      => '3',
				'mobile_default'      => '2',
				'required'            => true,
				'device_args'         => $this->get_devices_default_args(),
				'min_affected_device' => array(
					Controls_Stack::RESPONSIVE_DESKTOP => Controls_Stack::RESPONSIVE_TABLET,
					Controls_Stack::RESPONSIVE_TABLET  => Controls_Stack::RESPONSIVE_TABLET,
				),
			)
		);
	}

	/** Las mismas comprobaciones que `is_wc_feature_active()` de PRO. */
	protected function is_wc_feature_active( $feature ) {
		switch ( $feature ) {
			case 'checkout_login_reminder':
				return 'yes' === get_option( 'woocommerce_enable_checkout_login_reminder' );
			case 'shipping':
				return class_exists( 'WC_Shipping_Zones' ) && count( \WC_Shipping_Zones::get_zones() ) > 0;
			case 'coupons':
				return function_exists( 'wc_coupons_enabled' ) && wc_coupons_enabled();
			case 'signup_and_login_from_checkout':
				return 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' );
			case 'ship_to_billing_address_only':
				return function_exists( 'wc_ship_to_billing_address_only' ) && wc_ship_to_billing_address_only();
		}
		return false;
	}

	/** Opciones de tipo de borde de PRO (`get_custom_border_type_options()`), con los mismos valores. */
	public static function get_custom_border_type_options() {
		return array(
			'none'   => esc_html__( 'Ninguno', 'pro-elements' ),
			'solid'  => esc_html__( 'Sólido', 'pro-elements' ),
			'double' => esc_html__( 'Doble', 'pro-elements' ),
			'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
			'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
			'groove' => esc_html__( 'Ranura', 'pro-elements' ),
		);
	}

	/** Textos de WooCommerce que cambia el widget (`texto original => texto del control`); la subclase lo rellena. */
	protected function init_gettext_modifications() {
		$this->gettext_modifications = array();
	}

	/**
	 * Filtro `gettext` mientras se pinta el widget: sustituye los textos de WooCommerce que el widget deja cambiar
	 * (botones, títulos), como `filter_gettext()` de PRO.
	 */
	public function filter_gettext( $translation, $text, $domain ) {
		if ( 'woocommerce' !== $domain && 'pro-elements' !== $domain ) {
			return $translation;
		}
		if ( ! isset( $this->gettext_modifications ) ) {
			$this->init_gettext_modifications();
		}
		return array_key_exists( $text, $this->gettext_modifications ) ? $this->gettext_modifications[ $text ] : $translation;
	}
}
