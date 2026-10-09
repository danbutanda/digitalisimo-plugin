<?php
namespace Digitalisimo\Elements\Conditions;

use DateTime;
use Digitalisimo\Elements\Display_Conditions;
use Elementor\Controls_Manager;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider as Comparators;
use ElementorPro\Modules\DisplayConditions\Classes\Comparators_Checker as Checker;
use ElementorPro\Modules\DisplayConditions\Conditions\Base\Condition_Base;
use ElementorPro\Modules\QueryControl\Module as Query;

defined( 'ABSPATH' ) || exit;

/**
 * Base de las condiciones propias. Las opciones sólo se construyen en el editor;
 * el frontend ejecuta `check()` con los valores ya guardados y nunca hace peticiones remotas.
 */
abstract class Base extends Condition_Base {
	const DATE_FORMAT = 'm-d-Y';

	public function __construct() {
		// El padre extrae el adaptador de WordPress del final de los argumentos; no se usa aquí.
		parent::__construct( array( null ) );
	}

	protected function add_comparator( array $keys, array $custom = array() ) {
		$options = $custom + Comparators::get_comparators( $keys );
		$this->add_control( 'comparator', array(
			'type'    => Controls_Manager::SELECT,
			'options' => $options,
			'default' => (string) array_key_first( $options ),
		) );
	}

	protected function add_set_comparator() {
		$this->add_comparator( array( Comparators::COMPARATOR_IS_ONE_OF, Comparators::COMPARATOR_IS_NONE_OF ) );
	}

	protected function add_choices( $key, array $options ) {
		$this->add_control( $key, array(
			'type'     => Controls_Manager::SELECT2,
			'options'  => $options,
			'multiple' => true,
			'required' => true,
			'default'  => array(),
		) );
	}

	protected function add_query( $key, array $autocomplete ) {
		$this->add_control( $key, array(
			'type'         => Query::QUERY_CONTROL_ID,
			'autocomplete' => $autocomplete,
			'multiple'     => true,
			'placeholder'  => 'Escribe para buscar',
			'required'     => true,
		) );
	}

	protected function add_terms_query( $key, $taxonomy ) {
		$this->add_query( $key, array(
			'object'   => Query::QUERY_OBJECT_TAX,
			'by_field' => 'term_id',
			'query'    => array( 'taxonomy' => $taxonomy ),
		) );
	}

	protected function add_text( $key, $placeholder, $required = true ) {
		$this->add_control( $key, array(
			'type'        => Controls_Manager::TEXT,
			'placeholder' => $placeholder,
			'required'    => $required,
		) );
	}

	protected function add_number( $placeholder ) {
		$this->add_comparator( array(
			Comparators::COMPARATOR_IS,
			Comparators::COMPARATOR_IS_NOT,
			Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
			Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
		) );
		$this->add_text( 'value', $placeholder );
	}

	protected function add_date() {
		$this->add_comparator( array(
			Comparators::COMPARATOR_IS,
			Comparators::COMPARATOR_IS_NOT,
			Comparators::COMPARATOR_IS_BEFORE,
			Comparators::COMPARATOR_IS_AFTER,
			Comparators::COMPARATOR_IS_BEFORE_INCLUSIVE,
			Comparators::COMPARATOR_IS_AFTER_INCLUSIVE,
		) );
		$this->add_control( 'date', array(
			'type'     => Controls_Manager::DATE_TIME,
			'variant'  => 'date',
			'required' => true,
		) );
	}

	/** Valores de un control múltiple; el control de consulta guarda `{id,label}`. */
	protected static function values( $value ) {
		$values = array();
		foreach ( (array) $value as $item ) {
			if ( is_array( $item ) ) {
				$item = isset( $item['id'] ) ? $item['id'] : '';
			}
			if ( is_scalar( $item ) && '' !== (string) $item ) {
				$values[] = (string) $item;
			}
		}
		return array_values( array_unique( $values ) );
	}

	protected static function ids( $value ) {
		return array_values( array_filter( array_map( 'absint', self::values( $value ) ) ) );
	}

	protected static function in_set( array $args, $key, array $actual ) {
		$comparator = isset( $args['comparator'] ) ? (string) $args['comparator'] : '';
		$expected   = self::values( isset( $args[ $key ] ) ? $args[ $key ] : array() );
		if ( ! $expected ) {
			return false;
		}
		return Checker::check_array_contains( $comparator, $expected, array_map( 'strval', $actual ) );
	}

	/** Compara números con decimales; un valor ausente o no numérico nunca cumple la regla. */
	protected static function number( array $args, $actual ) {
		$expected = isset( $args['value'] ) ? str_replace( ',', '.', trim( (string) $args['value'] ) ) : '';
		if ( null === $actual || '' === $actual || ! is_numeric( $actual ) || ! is_numeric( $expected ) ) {
			return false;
		}
		$actual   = (float) $actual;
		$expected = (float) $expected;
		switch ( isset( $args['comparator'] ) ? $args['comparator'] : '' ) {
			case Comparators::COMPARATOR_IS:
				return abs( $actual - $expected ) < 0.000001;
			case Comparators::COMPARATOR_IS_NOT:
				return abs( $actual - $expected ) >= 0.000001;
			case Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE:
				return $actual <= $expected;
			case Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE:
				return $actual >= $expected;
		}
		return false;
	}

	/** Compara días completos en la zona horaria del sitio. */
	protected static function date( array $args, $actual ) {
		$set = isset( $args['date'] ) ? (string) $args['date'] : '';
		$day = DateTime::createFromFormat( '!' . self::DATE_FORMAT, $set );
		if ( ! $actual instanceof \DateTimeInterface || ! $day || $day->format( self::DATE_FORMAT ) !== $set ) {
			return false;
		}
		$actual = DateTime::createFromFormat( '!Y-m-d', $actual->format( 'Y-m-d' ) );
		return Checker::check_date_time( isset( $args['comparator'] ) ? (string) $args['comparator'] : '', $actual, $day );
	}
}

/** Datos de la petición en curso, con filtros para servidores, proxies y pruebas. */
final class Visitor {
	public static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}

	public static function referer_host() {
		$referer = wp_get_raw_referer();
		$host    = $referer ? wp_parse_url( $referer, PHP_URL_HOST ) : '';
		return is_string( $host ) ? strtolower( $host ) : '';
	}

	public static function request_uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';
	}

	/**
	 * País ISO alfa-2 que entrega el CDN o el servidor. No consulta servicios remotos durante
	 * el render; un sitio puede aportar su propia fuente con `digitalisimo_elements_visitor_country`.
	 * Las cabeceras pueden falsificarse si no las reescribe un proxy: no sirve para control de acceso.
	 */
	public static function country() {
		$country = apply_filters( 'digitalisimo_elements_visitor_country', null );
		if ( null === $country ) {
			$country = '';
			foreach ( array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE' ) as $key ) {
				if ( ! empty( $_SERVER[ $key ] ) ) {
					$country = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
					break;
				}
			}
		}
		$country = strtoupper( trim( (string) $country ) );
		return preg_match( '/^[A-Z]{2}$/', $country ) && ! in_array( $country, array( 'XX', 'T1', 'ZZ' ), true ) ? $country : '';
	}

	public static function browser_language() {
		$header = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) : '';
		return preg_match( '/^\s*([a-z]{2,3})\b/i', $header, $match ) ? strtolower( $match[1] ) : '';
	}
}

final class User_Condition extends Base {
	public function get_name() { return 'digitalisimo_user'; }
	public function get_label() { return 'Usuario específico'; }
	public function get_group() { return 'user'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_query( 'users', array( 'object' => Query::QUERY_OBJECT_USER ) );
	}

	public function check( $args ) : bool {
		$user = get_current_user_id();
		return self::in_set( $args, 'users', $user ? array( $user ) : array() );
	}
}

final class Operating_System_Condition extends Base {
	const SYSTEMS = array(
		'ios'      => 'iOS / iPadOS',
		'android'  => 'Android',
		'chromeos' => 'ChromeOS',
		'windows'  => 'Windows',
		'macos'    => 'macOS',
		'linux'    => 'Linux',
	);

	public function get_name() { return 'digitalisimo_os'; }
	public function get_label() { return 'Sistema operativo'; }
	public function get_group() { return Display_Conditions::GROUP_VISITOR; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'systems', self::SYSTEMS );
	}

	public static function detect( $agent ) {
		$patterns = array(
			'ios'      => '/iPhone|iPad|iPod/i',
			'android'  => '/Android/i',
			'chromeos' => '/CrOS/',
			'windows'  => '/Windows/i',
			'macos'    => '/Macintosh|Mac OS X/i',
			'linux'    => '/Linux|X11/i',
		);
		foreach ( $patterns as $system => $pattern ) {
			if ( preg_match( $pattern, $agent ) ) {
				return $system;
			}
		}
		return '';
	}

	public function check( $args ) : bool {
		$system = self::detect( Visitor::user_agent() );
		return self::in_set( $args, 'systems', $system ? array( $system ) : array() );
	}
}

final class Browser_Condition extends Base {
	const BROWSERS = array(
		'chrome'  => 'Google Chrome',
		'safari'  => 'Safari',
		'firefox' => 'Mozilla Firefox',
		'edge'    => 'Microsoft Edge',
		'opera'   => 'Opera',
		'samsung' => 'Samsung Internet',
		'ie'      => 'Internet Explorer',
	);

	public function get_name() { return 'digitalisimo_browser'; }
	public function get_label() { return 'Navegador'; }
	public function get_group() { return Display_Conditions::GROUP_VISITOR; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'browsers', self::BROWSERS );
	}

	/** El orden importa: Edge, Opera y Samsung también se anuncian como Chrome y Safari. */
	public static function detect( $agent ) {
		$patterns = array(
			'ie'      => '/MSIE |Trident\//',
			'edge'    => '/Edg(e|A|iOS)?\//',
			'opera'   => '/OPR\/|OPT\/|Opera/',
			'samsung' => '/SamsungBrowser\//',
			'firefox' => '/Firefox\/|FxiOS\//',
			'chrome'  => '/Chrome\/|CriOS\//',
			'safari'  => '/Safari\//',
		);
		foreach ( $patterns as $browser => $pattern ) {
			if ( preg_match( $pattern, $agent ) ) {
				return $browser;
			}
		}
		return '';
	}

	public function check( $args ) : bool {
		$browser = self::detect( Visitor::user_agent() );
		return self::in_set( $args, 'browsers', $browser ? array( $browser ) : array() );
	}
}

final class Language_Condition extends Base {
	public function get_name() { return 'digitalisimo_language'; }
	public function get_label() { return 'Idioma'; }
	public function get_group() { return Display_Conditions::GROUP_VISITOR; }

	public function get_options() {
		$this->add_control( 'source', array(
			'type'    => Controls_Manager::SELECT,
			'options' => array(
				'site'    => 'Idioma del sitio',
				'browser' => 'Idioma del navegador',
			),
			'default' => 'site',
		) );
		$this->add_set_comparator();
		$this->add_choices( 'languages', self::languages() );
	}

	private static function languages() {
		$languages = require __DIR__ . '/languages.php';
		foreach ( array_merge( array( 'en_US', get_locale() ), get_available_languages() ) as $locale ) {
			if ( ! isset( $languages[ $locale ] ) ) {
				$languages[ $locale ] = $locale;
			}
		}
		return $languages;
	}

	private static function language_part( $locale ) {
		return strtolower( strtok( (string) $locale, '_-' ) );
	}

	/** Con Polylang o WPML, `get_locale()` ya devuelve el idioma de la página servida. */
	public function check( $args ) : bool {
		if ( isset( $args['source'] ) && 'browser' === $args['source'] ) {
			$language = Visitor::browser_language();
			if ( '' === $language ) {
				return false;
			}
			$args['languages'] = array_map( array( __CLASS__, 'language_part' ), self::values( isset( $args['languages'] ) ? $args['languages'] : array() ) );
			return self::in_set( $args, 'languages', array( $language ) );
		}
		return self::in_set( $args, 'languages', array( get_locale() ) );
	}
}

final class Country_Condition extends Base {
	public function get_name() { return 'digitalisimo_country'; }
	public function get_label() { return 'País'; }
	public function get_group() { return Display_Conditions::GROUP_VISITOR; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'countries', require __DIR__ . '/countries.php' );
	}

	/** Si el país no se conoce, la regla no se cumple con ningún comparador. */
	public function check( $args ) : bool {
		$country = Visitor::country();
		return '' !== $country && self::in_set( $args, 'countries', array( $country ) );
	}
}

final class Url_Parameter_Condition extends Base {
	public function get_name() { return 'digitalisimo_url_parameter'; }
	public function get_label() { return 'Parámetro de URL'; }
	public function get_group() { return Display_Conditions::GROUP_URL; }

	public function get_options() {
		$this->add_text( 'parameter', 'utm_source' );
		$this->add_comparator(
			array( Comparators::COMPARATOR_IS, Comparators::COMPARATOR_IS_NOT, Comparators::COMPARATOR_CONTAINS, Comparators::COMPARATOR_NOT_CONTAIN ),
			array( 'exists' => 'Existe', 'not_exists' => 'No existe' )
		);
		$this->add_text( 'value', 'Valor', false );
	}

	public function check( $args ) : bool {
		$name = isset( $args['parameter'] ) ? trim( (string) $args['parameter'] ) : '';
		if ( '' === $name ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo se lee para decidir la visibilidad.
		$present    = isset( $_GET[ $name ] );
		$comparator = isset( $args['comparator'] ) ? (string) $args['comparator'] : '';
		if ( 'exists' === $comparator || 'not_exists' === $comparator ) {
			return ( 'exists' === $comparator ) === $present;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$value = $present && is_scalar( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';
		return Checker::check_string_contains( $comparator, isset( $args['value'] ) ? (string) $args['value'] : '', $value );
	}
}

final class Url_Path_Condition extends Base {
	public function get_name() { return 'digitalisimo_url_path'; }
	public function get_label() { return 'URL actual'; }
	public function get_group() { return Display_Conditions::GROUP_URL; }

	public function get_options() {
		$this->add_comparator( array( Comparators::COMPARATOR_IS, Comparators::COMPARATOR_IS_NOT, Comparators::COMPARATOR_CONTAINS, Comparators::COMPARATOR_NOT_CONTAIN ) );
		$this->add_text( 'url', '/tienda/ o un fragmento' );
	}

	/** Ruta relativa al sitio en curso, para que funcione igual en subdominios y subdirectorios. */
	public static function relative_path( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		$path = '/' . ltrim( is_string( $path ) ? strtolower( $path ) : '', '/' );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = '/' . trim( is_string( $home ) ? strtolower( $home ) : '', '/' );
		if ( '/' !== $home && ( $path === $home || 0 === strpos( $path, $home . '/' ) ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		$path = '/' . trim( $path, '/' );
		return '/' === $path ? '/' : $path . '/';
	}

	public function check( $args ) : bool {
		$comparator = isset( $args['comparator'] ) ? (string) $args['comparator'] : '';
		$expected   = isset( $args['url'] ) ? trim( (string) $args['url'] ) : '';
		if ( '' === $expected ) {
			return false;
		}
		$uri = Visitor::request_uri();
		if ( Comparators::COMPARATOR_IS === $comparator || Comparators::COMPARATOR_IS_NOT === $comparator ) {
			return Checker::check_string_contains( $comparator, self::relative_path( $expected ), self::relative_path( $uri ) );
		}
		return Checker::check_string_contains( $comparator, $expected, $uri );
	}
}

final class Search_Engine_Condition extends Base {
	const ENGINES = array(
		'google'     => array( 'Google', '/(^|\.)google\.(com|co|[a-z]{2})(\.[a-z]{2})?$/' ),
		'bing'       => array( 'Bing', '/(^|\.)bing\.com$/' ),
		'yahoo'      => array( 'Yahoo', '/(^|\.)search\.yahoo\.(com|co|[a-z]{2})(\.[a-z]{2})?$/' ),
		'duckduckgo' => array( 'DuckDuckGo', '/(^|\.)duckduckgo\.com$/' ),
		'brave'      => array( 'Brave Search', '/^search\.brave\.com$/' ),
		'ecosia'     => array( 'Ecosia', '/(^|\.)ecosia\.org$/' ),
		'yandex'     => array( 'Yandex', '/(^|\.)yandex\.(com|[a-z]{2})(\.[a-z]{2})?$/' ),
		'baidu'      => array( 'Baidu', '/(^|\.)baidu\.com$/' ),
	);

	public function get_name() { return 'digitalisimo_search_engine'; }
	public function get_label() { return 'Llegó desde un buscador'; }
	public function get_group() { return Display_Conditions::GROUP_URL; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'engines', array_map( static function ( $engine ) {
			return $engine[0];
		}, self::ENGINES ) );
	}

	public static function detect( $host ) {
		foreach ( self::ENGINES as $engine => $data ) {
			if ( '' !== $host && preg_match( $data[1], $host ) ) {
				return $engine;
			}
		}
		return '';
	}

	public function check( $args ) : bool {
		$engine = self::detect( Visitor::referer_host() );
		return self::in_set( $args, 'engines', $engine ? array( $engine ) : array() );
	}
}

final class Post_Type_Condition extends Base {
	public function get_name() { return 'digitalisimo_post_type'; }
	public function get_label() { return 'Tipo de contenido'; }
	public function get_group() { return 'page'; }

	public function get_options() {
		$types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $type ) {
			if ( 'attachment' !== $name ) {
				$types[ $name ] = $type->labels->singular_name;
			}
		}
		$this->add_set_comparator();
		$this->add_choices( 'types', $types );
	}

	public function check( $args ) : bool {
		$type = is_singular() ? get_post_type( get_queried_object_id() ) : '';
		return self::in_set( $args, 'types', $type ? array( $type ) : array() );
	}
}

final class Specific_Content_Condition extends Base {
	public function get_name() { return 'digitalisimo_specific_content'; }
	public function get_label() { return 'Contenido específico'; }
	public function get_group() { return 'page'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_query( 'items', array(
			'object' => Query::QUERY_OBJECT_POST,
			'query'  => array( 'post_type' => 'any', 'post_status' => 'publish' ),
		) );
	}

	public function check( $args ) : bool {
		$object = get_queried_object();
		return self::in_set( $args, 'items', $object instanceof \WP_Post ? array( $object->ID ) : array() );
	}
}

final class Special_Page_Condition extends Base {
	public function get_name() { return 'digitalisimo_special_page'; }
	public function get_label() { return 'Página especial'; }
	public function get_group() { return 'page'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'pages', array(
			'front_page' => 'Página de inicio',
			'blog'       => 'Página de entradas',
			'singular'   => 'Cualquier contenido individual',
			'archive'    => 'Cualquier archivo',
			'search'     => 'Resultados de búsqueda',
			'not_found'  => 'Error 404',
			'privacy'    => 'Política de privacidad',
		) );
	}

	public function check( $args ) : bool {
		$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
		$flags   = array(
			'front_page' => is_front_page(),
			'blog'       => is_home(),
			'singular'   => is_singular(),
			'archive'    => is_archive(),
			'search'     => is_search(),
			'not_found'  => is_404(),
			'privacy'    => $privacy && is_page( $privacy ),
		);
		return self::in_set( $args, 'pages', array_keys( array_filter( $flags ) ) );
	}
}

final class Shortcode_Condition extends Base {
	public function get_name() { return 'digitalisimo_shortcode'; }
	public function get_label() { return 'Resultado de un shortcode'; }

	public function get_options() {
		$this->add_text( 'shortcode', '[mi_shortcode]' );
		$this->add_comparator( array(
			Comparators::COMPARATOR_IS,
			Comparators::COMPARATOR_IS_NOT,
			Comparators::COMPARATOR_CONTAINS,
			Comparators::COMPARATOR_NOT_CONTAIN,
			Comparators::COMPARATOR_IS_EMPTY,
			Comparators::COMPARATOR_IS_NOT_EMPTY,
		) );
		$this->add_text( 'value', 'Valor esperado', false );
	}

	public function check( $args ) : bool {
		$shortcode = isset( $args['shortcode'] ) ? trim( (string) $args['shortcode'] ) : '';
		if ( ! preg_match( '/^\[[^\[\]]+\]/', $shortcode ) ) {
			return false;
		}
		$output = trim( (string) do_shortcode( $shortcode ) );
		return Checker::check_string_contains_and_empty( isset( $args['comparator'] ) ? (string) $args['comparator'] : '', isset( $args['value'] ) ? (string) $args['value'] : '', $output );
	}
}

/** Acceso a carrito, cliente y producto actual sin suponer que WooCommerce los inicializó. */
abstract class Woo_Base extends Base {
	private static $orders = array();

	public function get_group() { return Display_Conditions::GROUP_WOOCOMMERCE; }

	protected static function cart() {
		$woo = function_exists( 'WC' ) ? WC() : null;
		return $woo && isset( $woo->cart ) && is_object( $woo->cart ) ? $woo->cart : null;
	}

	/** IDs del producto principal de cada línea del carrito. */
	protected static function cart_products() {
		$cart = self::cart();
		$ids  = array();
		foreach ( $cart ? (array) $cart->get_cart() : array() as $item ) {
			if ( ! empty( $item['product_id'] ) ) {
				$ids[] = (int) $item['product_id'];
			}
		}
		return array_values( array_unique( $ids ) );
	}

	protected static function cart_terms( $taxonomy ) {
		$terms = array();
		foreach ( self::cart_products() as $product ) {
			$terms = array_merge( $terms, wc_get_product_term_ids( $product, $taxonomy ) );
		}
		return array_values( array_unique( $terms ) );
	}

	/** Pedidos pagados del usuario actual, ordenados del más antiguo al más reciente. */
	protected static function paid_orders() {
		$user = get_current_user_id();
		if ( ! $user ) {
			return array();
		}
		$key = get_current_blog_id() . ':' . $user;
		if ( ! isset( self::$orders[ $key ] ) ) {
			self::$orders[ $key ] = wc_get_orders( array(
				'customer_id' => $user,
				'status'      => wc_get_is_paid_statuses(),
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'ASC',
			) );
		}
		return self::$orders[ $key ];
	}

	protected static function order_day( $order ) {
		$date = $order->get_date_paid() ?: $order->get_date_created();
		if ( ! $date ) {
			return null;
		}
		$date = clone $date;
		$date->setTimezone( wp_timezone() );
		return $date;
	}

	protected static function product() {
		$id = get_the_ID();
		return $id && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
	}
}

final class Cart_Products_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_cart_products'; }
	public function get_label() { return 'Productos en el carrito'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_query( 'products', array(
			'object' => Query::QUERY_OBJECT_POST,
			'query'  => array( 'post_type' => 'product', 'post_status' => 'publish' ),
		) );
	}

	public function check( $args ) : bool {
		return self::in_set( $args, 'products', self::cart_products() );
	}
}

final class Cart_Categories_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_cart_categories'; }
	public function get_label() { return 'Categorías en el carrito'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_terms_query( 'categories', 'product_cat' );
	}

	public function check( $args ) : bool {
		return self::in_set( $args, 'categories', self::cart_terms( 'product_cat' ) );
	}
}

final class Cart_Tags_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_cart_tags'; }
	public function get_label() { return 'Etiquetas en el carrito'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_terms_query( 'tags', 'product_tag' );
	}

	public function check( $args ) : bool {
		return self::in_set( $args, 'tags', self::cart_terms( 'product_tag' ) );
	}
}

final class Cart_Count_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_cart_count'; }
	public function get_label() { return 'Artículos en el carrito'; }

	public function get_options() {
		$this->add_number( 'Cantidad' );
	}

	public function check( $args ) : bool {
		$cart = self::cart();
		return self::number( $args, $cart ? $cart->get_cart_contents_count() : 0 );
	}
}

final class Cart_Subtotal_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_cart_subtotal'; }
	public function get_label() { return 'Subtotal del carrito'; }

	public function get_options() {
		$this->add_number( 'Importe' );
	}

	public function check( $args ) : bool {
		$cart = self::cart();
		return self::number( $args, $cart ? (float) $cart->get_displayed_subtotal() : 0 );
	}
}

final class Customer_Bought_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_customer_bought'; }
	public function get_label() { return 'El cliente compró'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_query( 'products', array(
			'object' => Query::QUERY_OBJECT_POST,
			'query'  => array( 'post_type' => 'product', 'post_status' => 'publish' ),
		) );
	}

	public function check( $args ) : bool {
		$user   = get_current_user_id();
		$bought = array();
		foreach ( $user ? self::ids( isset( $args['products'] ) ? $args['products'] : array() ) : array() as $product ) {
			if ( wc_customer_bought_product( '', $user, $product ) ) {
				$bought[] = $product;
			}
		}
		return self::in_set( $args, 'products', $bought );
	}
}

final class Customer_Orders_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_customer_orders'; }
	public function get_label() { return 'Pedidos pagados del cliente'; }

	public function get_options() {
		$this->add_number( 'Número de pedidos' );
	}

	public function check( $args ) : bool {
		return self::number( $args, count( self::paid_orders() ) );
	}
}

final class Customer_First_Purchase_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_customer_first_purchase'; }
	public function get_label() { return 'Fecha de la primera compra'; }

	public function get_options() {
		$this->add_date();
	}

	public function check( $args ) : bool {
		$orders = self::paid_orders();
		return $orders ? self::date( $args, self::order_day( reset( $orders ) ) ) : false;
	}
}

final class Customer_Last_Purchase_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_customer_last_purchase'; }
	public function get_label() { return 'Fecha de la última compra'; }

	public function get_options() {
		$this->add_date();
	}

	public function check( $args ) : bool {
		$orders = self::paid_orders();
		return $orders ? self::date( $args, self::order_day( end( $orders ) ) ) : false;
	}
}

final class Customer_Purchase_Date_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_customer_purchase_date'; }
	public function get_label() { return 'Compró en la fecha'; }

	public function get_options() {
		$this->add_comparator( array( Comparators::COMPARATOR_IS, Comparators::COMPARATOR_IS_NOT ) );
		$this->add_control( 'date', array(
			'type'     => Controls_Manager::DATE_TIME,
			'variant'  => 'date',
			'required' => true,
		) );
	}

	public function check( $args ) : bool {
		$set = DateTime::createFromFormat( '!' . self::DATE_FORMAT, isset( $args['date'] ) ? (string) $args['date'] : '' );
		if ( ! $set ) {
			return false;
		}
		$found = false;
		foreach ( self::paid_orders() as $order ) {
			$day = self::order_day( $order );
			if ( $day && $day->format( 'Y-m-d' ) === $set->format( 'Y-m-d' ) ) {
				$found = true;
				break;
			}
		}
		return Comparators::COMPARATOR_IS_NOT === ( isset( $args['comparator'] ) ? $args['comparator'] : '' ) ? ! $found : $found;
	}
}

final class Product_Status_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_product_status'; }
	public function get_label() { return 'Estado del producto'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'statuses', array(
			'on_sale'           => 'En oferta',
			'in_stock'          => 'En existencia',
			'out_of_stock'      => 'Agotado',
			'on_backorder'      => 'Bajo pedido',
			'featured'          => 'Destacado',
			'virtual'           => 'Virtual',
			'downloadable'      => 'Descargable',
			'sold_individually' => 'Se vende individualmente',
		) );
	}

	public function check( $args ) : bool {
		$product = self::product();
		if ( ! $product ) {
			return false;
		}
		$status = $product->get_stock_status();
		$flags  = array(
			'on_sale'           => $product->is_on_sale(),
			'in_stock'          => 'instock' === $status,
			'out_of_stock'      => 'outofstock' === $status,
			'on_backorder'      => 'onbackorder' === $status,
			'featured'          => $product->is_featured(),
			'virtual'           => $product->is_virtual(),
			'downloadable'      => $product->is_downloadable(),
			'sold_individually' => $product->is_sold_individually(),
		);
		return self::in_set( $args, 'statuses', array_keys( array_filter( $flags ) ) );
	}
}

final class Product_Type_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_product_type'; }
	public function get_label() { return 'Tipo de producto'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_choices( 'types', wc_get_product_types() );
	}

	public function check( $args ) : bool {
		$product = self::product();
		return $product ? self::in_set( $args, 'types', array( $product->get_type() ) ) : false;
	}
}

final class Product_Category_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_product_category'; }
	public function get_label() { return 'Categoría del producto'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_terms_query( 'categories', 'product_cat' );
	}

	public function check( $args ) : bool {
		$product = self::product();
		return $product ? self::in_set( $args, 'categories', wc_get_product_term_ids( $product->get_id(), 'product_cat' ) ) : false;
	}
}

final class Product_Price_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_product_price'; }
	public function get_label() { return 'Precio del producto'; }

	public function get_options() {
		$this->add_number( 'Precio' );
	}

	public function check( $args ) : bool {
		$product = self::product();
		return $product ? self::number( $args, $product->get_price() ) : false;
	}
}

final class Product_Stock_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_product_stock'; }
	public function get_label() { return 'Existencias del producto'; }

	public function get_options() {
		$this->add_number( 'Unidades' );
	}

	/** Sólo aplica a productos que gestionan inventario; los demás no tienen cantidad. */
	public function check( $args ) : bool {
		$product = self::product();
		return $product ? self::number( $args, $product->get_stock_quantity() ) : false;
	}
}

final class Product_Category_Archive_Condition extends Woo_Base {
	public function get_name() { return 'digitalisimo_product_category_archive'; }
	public function get_label() { return 'Archivo de categoría de producto'; }

	public function get_options() {
		$this->add_set_comparator();
		$this->add_terms_query( 'categories', 'product_cat' );
	}

	public function check( $args ) : bool {
		return self::in_set( $args, 'categories', is_tax( 'product_cat' ) ? array( get_queried_object_id() ) : array() );
	}
}
