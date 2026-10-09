<?php
namespace Digitalisimo\Elements;

use Digitalisimo\Elements\Conditions\Legacy_Rules;
use Elementor\Controls_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-legacy-rules.php';

/**
 * Mantiene las condiciones de visibilidad guardadas por Element Pack al retirar ese plugin.
 *
 * Lee los mismos ajustes (`ep_display_conditions_*`) sin reescribir `_elementor_data` y registra
 * sus controles con los mismos nombres para que el editor los conserve y permita cambiarlos.
 * La sección sólo aparece en elementos que ya la tenían activa: el contenido nuevo usa Display
 * Conditions. Si Element Pack está activo no interviene, para no competir por sus controles.
 */
final class Legacy_Visibility {
	const SECTION = 'section_visibility_control_controls';

	public static function init() {
		if ( self::element_pack_active() ) {
			return;
		}
		foreach ( array( 'common/_section_style', 'section/section_advanced', 'container/section_layout' ) as $place ) {
			add_action( 'elementor/element/' . $place . '/after_section_end', array( __CLASS__, 'register_section' ) );
		}
		foreach ( array( 'common', 'section', 'container' ) as $element ) {
			add_action( 'elementor/element/' . $element . '/' . self::SECTION . '/before_section_end', array( __CLASS__, 'register_controls' ), 10, 2 );
		}
		foreach ( array( 'widget', 'section', 'container' ) as $element ) {
			add_filter( 'elementor/frontend/' . $element . '/should_render', array( __CLASS__, 'should_render' ), 10, 2 );
		}
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic_content' ), 10, 2 );
	}

	public static function element_pack_active() {
		return defined( 'BDTEP_VER' ) || class_exists( '\ElementPack\Element_Pack_Loader' );
	}

	public static function register_section( $element ) {
		$element->start_controls_section( self::SECTION, array(
			'tab'       => Controls_Manager::TAB_ADVANCED,
			'label'     => 'Condiciones de Element Pack',
			'condition' => array( 'ep_display_conditions_enable' => 'yes' ),
		) );
		$element->end_controls_section();
	}

	public static function register_controls( $element, $args = array() ) {
		$element->add_control( 'ep_display_conditions_notice', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => 'Reglas creadas con Element Pack. Siguen aplicándose igual; para reglas nuevas usa Display Conditions.',
			'content_classes' => 'elementor-descriptor',
		) );
		$element->add_control( 'ep_display_conditions_enable', array(
			'label'              => 'Condiciones de visualización',
			'type'               => Controls_Manager::SWITCHER,
			'default'            => '',
			'label_on'           => 'Sí',
			'label_off'          => 'No',
			'return_value'       => 'yes',
			'frontend_available' => true,
		) );
		$element->add_control( 'ep_display_conditions_to', array(
			'label'     => 'Acción',
			'type'      => Controls_Manager::SELECT,
			'default'   => 'show',
			'options'   => array( 'show' => 'Mostrar', 'hide' => 'Ocultar' ),
			'condition' => array( 'ep_display_conditions_enable' => 'yes' ),
		) );
		$element->add_control( 'ep_display_conditions_relation', array(
			'label'     => 'Cuando',
			'type'      => Controls_Manager::SELECT,
			'default'   => 'all',
			'options'   => array( 'all' => 'Se cumplen todas', 'any' => 'Se cumple alguna' ),
			'condition' => array( 'ep_display_conditions_enable' => 'yes' ),
		) );

		$repeater = new Repeater();
		$repeater->add_control( 'ep_condition_key', array(
			'label'       => 'Condición',
			'type'        => Controls_Manager::SELECT,
			'default'     => 'authentication',
			'label_block' => true,
			'groups'      => self::condition_groups(),
		) );
		foreach ( array( 'shortcode' => 'Shortcode', 'acf_boolean' => 'Campo ACF', 'acf_choice' => 'Campo ACF', 'acf_text' => 'Campo ACF' ) as $key => $label ) {
			if ( in_array( $key, Legacy_Rules::supported_keys(), true ) ) {
				$repeater->add_control( 'ep_condition_' . $key . '_name', array(
					'label'       => $label,
					'type'        => 'shortcode' === $key ? Controls_Manager::TEXT : Controls_Manager::SELECT,
					'options'     => 'shortcode' === $key || self::lean() ? array() : self::acf_fields( $key ),
					'label_block' => true,
					'condition'   => array( 'ep_condition_key' => $key ),
				) );
			}
		}
		$repeater->add_control( 'ep_condition_operator', array(
			'type'        => Controls_Manager::SELECT,
			'default'     => 'is',
			'label_block' => true,
			'options'     => array( 'is' => 'Es', 'not' => 'No es' ),
		) );
		$repeater->add_control( 'ep_condition_addition_operator', array(
			'type'        => Controls_Manager::SELECT,
			'default'     => 'equal',
			'options'     => array( 'equal' => 'Igual a', 'greater_or_equal' => 'Mayor o igual que' ),
			'label_block' => true,
			'condition'   => array( 'ep_condition_key' => 'orders_placed' ),
		) );
		foreach ( self::value_controls() as $key => $control ) {
			if ( in_array( $key, Legacy_Rules::supported_keys(), true ) ) {
				$repeater->add_control( 'ep_condition_' . $key . '_value', $control + array(
					'label_block' => true,
					'condition'   => array( 'ep_condition_key' => $key ),
				) );
			}
		}
		$repeater->add_control( 'ep_condition_custom_page_id', array(
			'label'     => 'ID de la página',
			'type'      => Controls_Manager::NUMBER,
			'default'   => '10',
			'condition' => array( 'ep_condition_key' => 'static_page', 'ep_condition_static_page_value' => 'custom' ),
		) );
		$repeater->add_control( 'ep_condition_time_range_end_time', array(
			'label'          => 'Hora final',
			'type'           => Controls_Manager::DATE_TIME,
			'picker_options' => array( 'noCalendar' => true, 'dateFormat' => 'H:i' ),
			'label_block'    => true,
			'condition'      => array( 'ep_condition_key' => 'time_range' ),
		) );

		$element->add_control( 'ep_display_conditions', array(
			'label'         => 'Condiciones',
			'type'          => Controls_Manager::REPEATER,
			'prevent_empty' => false,
			'condition'     => array( 'ep_display_conditions_enable' => 'yes' ),
			'fields'        => $repeater->get_controls(),
			'title_field'   => '{{{ ep_condition_key }}}',
		) );
	}

	/** El editor siempre muestra el elemento; el frontend aplica las reglas como Element Pack. */
	public static function should_render( $should_render, $element ) {
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || ! Legacy_Rules::enabled_on_site() ) {
			return $should_render;
		}
		$show = Legacy_Rules::should_show( (array) $element->get_settings(), $element->get_id() );
		return null === $show ? $should_render : $show;
	}

	public static function is_dynamic_content( $is_dynamic, $raw_data ) {
		return $is_dynamic || 'yes' === ( $raw_data['settings']['ep_display_conditions_enable'] ?? '' );
	}

	private static function condition_groups() {
		$titles = array(
			'user'        => array( 'Usuario', array( 'authentication' => 'Sesión iniciada', 'user' => 'Usuario', 'role' => 'Rol', 'country' => 'País', 'visit_count' => 'Número de visitas', 'session_count' => 'Número de sesiones' ) ),
			'system'      => array( 'Sistema', array( 'os' => 'Sistema operativo', 'browser' => 'Navegador', 'language' => 'Idioma' ) ),
			'date_time'   => array( 'Fecha y hora', array( 'date' => 'Rango de fechas', 'time_range' => 'Rango horario', 'date_time_before' => 'Antes de la fecha', 'time' => 'Hasta la hora', 'day' => 'Día de la semana' ) ),
			'url'         => array( 'URL', array( 'ex_url' => 'Referencia externa', 'url_parameters' => 'Parámetros de URL', 'url_string' => 'Texto en la URL', 'search_engine_url' => 'Buscador de origen' ) ),
			'post'        => array( 'Contenido', array( 'post' => 'Entrada', 'post_type' => 'Tipo de contenido', 'static_page' => 'Página especial' ) ),
			'woocommerce' => array( 'WooCommerce', array( 'products_in_cart' => 'Productos en el carrito', 'categories_in_cart' => 'Categorías en el carrito', 'tags_in_cart' => 'Etiquetas en el carrito', 'cart_item_number' => 'Artículos en el carrito', 'cart_subtotal_price' => 'Subtotal del carrito', 'first_purchased_date' => 'Primera compra', 'purchased_date' => 'Fecha de compra', 'last_purchased_date' => 'Última compra', 'purchased_item_number' => 'Compras realizadas', 'orders_placed' => 'Pedidos realizados', 'current_category_page' => 'Categoría de producto actual', 'single_product_price' => 'Precio del producto', 'single_product_stock' => 'Existencias del producto', 'single_product_category' => 'Categoría del producto', 'single_product_downloadable' => 'Producto descargable', 'single_product_virtual' => 'Producto virtual', 'single_product_featured' => 'Producto destacado', 'single_product_backorder' => 'Producto bajo pedido', 'single_product_onsale' => 'Producto en oferta', 'single_product_sold_individually' => 'Se vende individualmente', 'single_product_type' => 'Tipo de producto' ) ),
			'acf'         => array( 'Advanced Custom Fields', array( 'acf_boolean' => 'ACF verdadero/falso', 'acf_choice' => 'ACF elección', 'acf_text' => 'ACF texto' ) ),
			'misc'        => array( 'Otros', array( 'shortcode' => 'Shortcode' ) ),
		);
		$groups = array();
		foreach ( $titles as $name => $group ) {
			$options = array_intersect_key( $group[1], array_flip( Legacy_Rules::supported_keys() ) );
			if ( $options ) {
				$groups[ $name ] = array( 'label' => $group[0], 'options' => $options );
			}
		}
		return $groups;
	}

	/** Mismos tipos y valores por defecto que Element Pack: Elementor los usa para completar filas guardadas. */
	private static function value_controls() {
		static $controls;
		if ( null !== $controls ) {
			return $controls;
		}
		// En el frontend Elementor descarta las opciones de los controles; no hace falta consultarlas.
		$lean     = self::lean();
		$offset   = (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		$range    = gmdate( 'Y-m-d', strtotime( '-3 day' ) + $offset ) . ' to ' . gmdate( 'Y-m-d', strtotime( '+3 day' ) + $offset );
		$today    = gmdate( 'Y/m/d' );
		$yes      = array( 'type' => Controls_Manager::SELECT, 'default' => 'yes', 'options' => array( 'yes' => 'Sí' ) );
		$posts    = array( 'type' => 'query', 'default' => '', 'multiple' => true, 'autocomplete' => array( 'object' => 'post', 'query' => array( 'post_type' => 'any' ) ) );
		$products = array( 'type' => 'query', 'default' => '', 'multiple' => true, 'autocomplete' => array( 'object' => 'post', 'query' => array( 'post_type' => 'product' ) ) );
		$date     = array( 'type' => Controls_Manager::DATE_TIME, 'default' => $today, 'picker_options' => array( 'enableTime' => false ) );
		$controls = array(
			'authentication'    => array( 'type' => Controls_Manager::SELECT, 'default' => 'authenticated', 'options' => array( 'authenticated' => 'Con sesión iniciada' ) ),
			'user'              => array( 'type' => 'query', 'default' => '', 'multiple' => true, 'autocomplete' => array( 'object' => 'user' ), 'description' => 'Vacío: cualquier usuario con sesión.' ),
			'role'              => array( 'type' => Controls_Manager::SELECT, 'default' => 'subscriber', 'options' => $lean ? array() : wp_roles()->get_names() ),
			'post'              => $posts,
			'post_type'         => array( 'type' => Controls_Manager::SELECT2, 'default' => '', 'multiple' => true, 'options' => $lean ? array() : self::post_types() ),
			'static_page'       => array( 'type' => Controls_Manager::SELECT, 'default' => 'home', 'options' => array( 'home' => 'Inicio con entradas', 'static' => 'Página de inicio estática', 'blog' => 'Página de entradas', '404' => 'Error 404', 'custom' => 'Página por ID' ) ),
			'date'              => array( 'type' => Controls_Manager::DATE_TIME, 'default' => $range, 'picker_options' => array( 'enableTime' => false, 'mode' => 'range' ) ),
			'time_range'        => array( 'label' => 'Hora inicial', 'type' => Controls_Manager::DATE_TIME, 'picker_options' => array( 'noCalendar' => true, 'dateFormat' => 'H:i' ) ),
			'date_time_before'  => array( 'type' => Controls_Manager::DATE_TIME, 'default' => gmdate( 'Y-m-d', strtotime( '+3 day' ) + $offset ), 'picker_options' => array( 'enableTime' => false ) ),
			'time'              => array( 'type' => Controls_Manager::DATE_TIME, 'default' => '', 'picker_options' => array( 'dateFormat' => 'H:i', 'enableTime' => true, 'noCalendar' => true ) ),
			'day'               => array( 'type' => Controls_Manager::SELECT2, 'default' => '1', 'multiple' => true, 'options' => array( '0' => 'Domingo', '1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado' ) ),
			'os'                => array( 'type' => Controls_Manager::SELECT, 'default' => 'iphone', 'options' => array( 'iphone' => 'iPhone', 'android' => 'Android', 'windows' => 'Windows', 'open_bsd' => 'OpenBSD', 'sun_os' => 'SunOS', 'linux' => 'Linux', 'mac_os' => 'Mac OS' ) ),
			'browser'           => array( 'type' => Controls_Manager::SELECT, 'default' => 'ie', 'options' => array( 'ie' => 'Internet Explorer', 'firefox' => 'Mozilla Firefox', 'chrome' => 'Google Chrome', 'opera' => 'Opera', 'safari' => 'Safari', 'edge' => 'Microsoft Edge' ) ),
			'ex_url'            => array( 'type' => Controls_Manager::TEXT, 'placeholder' => 'www.ejemplo.com' ),
			'url_parameters'    => array( 'type' => Controls_Manager::TEXTAREA, 'placeholder' => "param1=valor1\nparam2=valor2", 'description' => 'Un par parámetro=valor por línea.' ),
			'url_string'        => array( 'type' => Controls_Manager::TEXT, 'description' => 'Texto que debe aparecer en la URL de la página.' ),
			'search_engine_url' => array( 'type' => Controls_Manager::SELECT2, 'default' => 'google.com', 'multiple' => true, 'options' => array( 'google.com' => 'Google', 'yahoo.com' => 'Yahoo', 'bing.com' => 'Bing', 'yandex.com' => 'Yandex', 'baidu.com' => 'Baidu' ) ),
			'visit_count'       => array( 'label' => 'Hasta n visitas', 'type' => Controls_Manager::NUMBER, 'default' => '1' ),
			'session_count'     => array( 'label' => 'Hasta n sesiones', 'type' => Controls_Manager::NUMBER, 'default' => '1' ),
			'language'          => array( 'type' => Controls_Manager::SELECT2, 'default' => array(), 'multiple' => true, 'options' => $lean ? array() : self::languages() ),
			'country'           => array( 'type' => Controls_Manager::SELECT2, 'default' => array(), 'multiple' => true, 'options' => $lean ? array() : array_change_key_case( require __DIR__ . '/countries.php', CASE_LOWER ) ),
			'shortcode'         => array( 'label' => 'Resultado esperado', 'type' => Controls_Manager::TEXTAREA, 'placeholder' => 'hola' ),
			'products_in_cart'  => $products,
			'categories_in_cart' => array( 'type' => Controls_Manager::SELECT2, 'default' => '', 'multiple' => true, 'options' => $lean ? array() : self::terms( 'product_cat' ) ),
			'tags_in_cart'      => array( 'type' => Controls_Manager::SELECT2, 'default' => '', 'multiple' => true, 'options' => $lean ? array() : self::terms( 'product_tag' ) ),
			'cart_item_number'  => array( 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 1, 'description' => '0 comprueba el carrito vacío.' ),
			'cart_subtotal_price' => array( 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 50 ),
			'first_purchased_date' => $date,
			'purchased_date'    => $date,
			'last_purchased_date' => $date,
			'purchased_item_number' => array( 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 1 ),
			'orders_placed'     => array( 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 1 ),
			'current_category_page' => array( 'type' => Controls_Manager::SELECT2, 'default' => '', 'multiple' => true, 'options' => $lean ? array() : self::terms( 'product_cat' ) ),
			'single_product_price' => array( 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 50 ),
			'single_product_stock' => array( 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 5 ),
			'single_product_category' => array( 'type' => Controls_Manager::SELECT2, 'default' => '', 'multiple' => true, 'options' => $lean ? array() : self::terms( 'product_cat' ) ),
			'single_product_downloadable' => $yes,
			'single_product_virtual' => $yes,
			'single_product_featured' => $yes,
			'single_product_backorder' => $yes,
			'single_product_onsale' => $yes,
			'single_product_sold_individually' => $yes,
			'single_product_type' => array( 'type' => Controls_Manager::SELECT, 'default' => 'simple', 'options' => array( 'simple' => 'Simple', 'grouped' => 'Agrupado', 'external' => 'Externo/afiliado', 'variable' => 'Variable' ) ),
			'acf_boolean'       => array( 'type' => Controls_Manager::SELECT, 'default' => 'true', 'options' => array( 'true' => 'Verdadero', 'false' => 'Falso' ) ),
			'acf_choice'        => array( 'type' => Controls_Manager::TEXTAREA, 'placeholder' => 'rojo : Rojo', 'description' => 'Una opción por línea, como en ACF: valor : Etiqueta.' ),
			'acf_text'          => array( 'type' => Controls_Manager::TEXT ),
		);
		return $controls;
	}

	private static function lean() {
		return class_exists( '\\Elementor\\Core\\Frontend\\Performance' ) && \Elementor\Core\Frontend\Performance::should_optimize_controls();
	}

	private static function post_types() {
		$types = array();
		foreach ( get_post_types( array( 'show_in_nav_menus' => true ), 'objects' ) as $name => $type ) {
			$types[ $name ] = $type->label;
		}
		return $types;
	}

	private static function terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		return is_wp_error( $terms ) ? array() : wp_list_pluck( $terms, 'name', 'term_id' );
	}

	private static function languages() {
		$languages = require __DIR__ . '/languages.php';
		foreach ( array( 'ga', 'lb_LU', 'ru_UA', get_locale() ) as $locale ) {
			$languages += array( $locale => $locale );
		}
		return $languages;
	}

	/** Campos ACF del tipo que admite cada condición, con la clave `field_*` que Element Pack guardaba. */
	private static function acf_fields( $key ) {
		$types = array(
			'acf_boolean' => array( 'true_false' ),
			'acf_choice'  => array( 'select', 'checkbox', 'radio' ),
			'acf_text'    => array( 'text', 'textarea', 'number', 'range', 'email', 'url', 'password', 'wysiwyg' ),
		);
		$fields = array();
		foreach ( get_posts( array( 'post_type' => 'acf-field', 'posts_per_page' => -1, 'post_status' => 'any' ) ) as $field ) {
			$settings = is_serialized( $field->post_content ) ? unserialize( $field->post_content, array( 'allowed_classes' => false ) ) : null; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			if ( is_array( $settings ) && in_array( $settings['type'] ?? '', $types[ $key ], true ) ) {
				$fields[ $field->post_name ] = $field->post_title . ' ( ' . get_the_title( $field->post_parent ) . ' )';
			}
		}
		return $fields;
	}
}
