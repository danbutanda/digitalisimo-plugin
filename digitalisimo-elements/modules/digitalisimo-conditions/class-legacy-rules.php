<?php
namespace Digitalisimo\Elements\Conditions;

use DateTime;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-visitor.php';

/**
 * Evalúa las reglas `ep_display_conditions` con la misma semántica que Visibility Controls de
 * Element Pack Pro 9.9.1, incluidas sus particularidades, para que retirar ese plugin no cambie
 * qué ve cada visitante. Una regla que no puede evaluarse devuelve null, que cuenta como «no cumple».
 */
final class Legacy_Rules {
	const GENERAL = array( 'authentication', 'user', 'role', 'post', 'post_type', 'static_page', 'date', 'time_range', 'date_time_before', 'time', 'day', 'os', 'browser', 'ex_url', 'url_parameters', 'url_string', 'search_engine_url', 'visit_count', 'session_count', 'language', 'country', 'shortcode' );
	const WOOCOMMERCE = array( 'products_in_cart', 'categories_in_cart', 'tags_in_cart', 'cart_item_number', 'cart_subtotal_price', 'first_purchased_date', 'purchased_date', 'last_purchased_date', 'purchased_item_number', 'orders_placed', 'current_category_page', 'single_product_price', 'single_product_stock', 'single_product_category', 'single_product_downloadable', 'single_product_virtual', 'single_product_featured', 'single_product_backorder', 'single_product_onsale', 'single_product_sold_individually', 'single_product_type' );
	const ACF = array( 'acf_boolean', 'acf_choice', 'acf_text' );

	const OPERATING_SYSTEMS = array(
		'iphone'   => '(iPhone)',
		'android'  => '(Android)',
		'windows'  => 'Win16|(Windows 95)|(Win95)|(Windows_95)|(Windows 98)|(Win98)|(Windows NT 5.0)|(Windows 2000)|(Windows NT 5.1)|(Windows XP)|(Windows NT 5.2)|(Windows NT 6.0)|(Windows Vista)|(Windows NT 6.1)|(Windows 7)|(Windows NT 4.0)|(WinNT4.0)|(WinNT)|(Windows NT)|Windows ME',
		'open_bsd' => 'OpenBSD',
		'sun_os'   => 'SunOS',
		'linux'    => '(Linux)|(X11)',
		'mac_os'   => '(Mac_PowerPC)|(Macintosh)',
	);

	/** Las mismas claves que Element Pack registraba según los plugins activos. */
	public static function supported_keys() {
		$keys = self::GENERAL;
		if ( class_exists( 'WooCommerce' ) ) {
			$keys = array_merge( $keys, self::WOOCOMMERCE );
		}
		if ( class_exists( 'ACF' ) ) {
			$keys = array_merge( $keys, self::ACF );
		}
		return $keys;
	}

	/**
	 * Element Pack sólo aplicaba las reglas con su extensión encendida en el sitio, y sus ajustes
	 * siguen en la base de datos tras retirarlo. Sin esa opción (por ejemplo, contenido importado)
	 * se aplican, porque un elemento con reglas sólo pudo crearse con la extensión activa.
	 */
	public static function enabled_on_site() {
		$options = get_option( 'element_pack_elementor_extend', null );
		$enabled = ! is_array( $options ) || ( isset( $options['visibility-controls'] ) && 'on' === $options['visibility-controls'] );
		return (bool) apply_filters( 'digitalisimo_elements_legacy_visibility', $enabled );
	}

	/**
	 * @return bool|null null si el elemento no usa condiciones; si no, si debe mostrarse.
	 */
	public static function should_show( array $settings, $element_id ) {
		if ( empty( $settings['ep_display_conditions_enable'] ) || 'yes' !== $settings['ep_display_conditions_enable'] ) {
			return null;
		}
		$results   = array();
		$supported = self::supported_keys();
		foreach ( is_array( $settings['ep_display_conditions'] ?? null ) ? $settings['ep_display_conditions'] : array() as $row ) {
			$key = is_array( $row ) && isset( $row['ep_condition_key'] ) ? (string) $row['ep_condition_key'] : '';
			if ( ! in_array( $key, $supported, true ) ) {
				continue;
			}
			$results[] = self::check( $key, $row, (string) $element_id );
		}
		$relation = $settings['ep_display_conditions_relation'] ?? 'all';
		// Comparación flexible a propósito: Element Pack trata null como «no cumple».
		if ( ! $results ) {
			$met = false;
		} elseif ( 'any' === $relation ) {
			$met = in_array( true, $results ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		} else {
			$met = ! in_array( false, $results ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		}
		$hide = 'hide' === ( $settings['ep_display_conditions_to'] ?? 'show' );
		return $hide ? ! $met : $met;
	}

	public static function check( $key, array $row, $element_id = '' ) {
		$relation = $row['ep_condition_operator'] ?? 'is';
		$val      = $row[ 'ep_condition_' . $key . '_value' ] ?? null;
		$method   = 'rule_' . $key;
		return method_exists( __CLASS__, $method ) ? self::$method( $relation, $val, $row, $element_id ) : null;
	}

	private static function compare( $left, $relation ) {
		switch ( $relation ) {
			case 'is':
				return $left == true; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			case 'not':
				return $left != true; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
		}
		return true === $left;
	}

	private static function any( $val, callable $test ) {
		if ( is_array( $val ) && ! empty( $val ) ) {
			foreach ( $val as $item ) {
				if ( $test( is_array( $item ) && isset( $item['id'] ) ? $item['id'] : $item ) ) {
					return true;
				}
			}
			return false;
		}
		return (bool) $test( $val );
	}

	private static function offset() {
		return (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
	}

	private static function rule_authentication( $relation ) {
		return self::compare( is_user_logged_in(), $relation );
	}

	private static function rule_user( $relation, $val ) {
		$user = get_current_user_id();
		return self::compare( self::any( $val, static function ( $id ) use ( $user ) {
			return $id == $user; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		} ), $relation );
	}

	private static function rule_role( $relation, $val ) {
		return self::compare( is_user_logged_in() && in_array( $val, (array) wp_get_current_user()->roles ), $relation ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
	}

	private static function rule_post( $relation, $val ) {
		return self::compare( self::any( $val, static function ( $id ) {
			return is_single( $id ) || is_singular( $id );
		} ), $relation );
	}

	private static function rule_post_type( $relation, $val ) {
		return self::compare( self::any( $val, 'is_singular' ), $relation );
	}

	private static function rule_static_page( $relation, $val, $row ) {
		switch ( $val ) {
			case 'home':
				return self::compare( is_front_page() && is_home(), $relation );
			case 'static':
				return self::compare( is_front_page() && ! is_home(), $relation );
			case 'blog':
				return self::compare( ! is_front_page() && is_home(), $relation );
			case '404':
				return self::compare( is_404(), $relation );
			case 'custom':
				$page = (int) ( $row['ep_condition_custom_page_id'] ?? 0 );
				return self::compare( 0 !== $page && $page === (int) get_the_ID(), $relation );
		}
		return null;
	}

	private static function rule_date( $relation, $val ) {
		$range = $val ? explode( 'to', preg_replace( '/\s+/', '', (string) $val ) ) : array();
		if ( 2 !== count( $range ) || false === DateTime::createFromFormat( 'Y-m-d', $range[0] ) || false === DateTime::createFromFormat( 'Y-m-d', $range[1] ) ) {
			return null;
		}
		$today = strtotime( gmdate( 'Y-m-d' ) );
		return self::compare( $today >= strtotime( $range[0] ) && $today <= strtotime( $range[1] ), $relation );
	}

	private static function rule_time_range( $relation, $val, $row ) {
		$start = gmdate( 'H:i', strtotime( preg_replace( '/\s+/', '', (string) $val ) ) );
		$end   = gmdate( 'H:i', strtotime( preg_replace( '/\s+/', '', (string) ( $row['ep_condition_time_range_end_time'] ?? '' ) ) ) );
		$now   = strtotime( gmdate( 'H:i', time() + self::offset() ) );
		return self::compare( $now >= strtotime( $start ) && $now <= strtotime( $end ), $relation );
	}

	private static function rule_date_time_before( $relation, $val ) {
		if ( ! $val ) {
			return null;
		}
		return self::compare( current_time( 'timestamp' ) <= strtotime( (string) $val ), $relation ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
	}

	private static function rule_time( $relation, $val ) {
		$time = gmdate( 'H:i', strtotime( preg_replace( '/\s+/', '', (string) $val ) ) );
		return self::compare( strtotime( gmdate( 'H:i', time() + self::offset() ) ) <= strtotime( $time ), $relation );
	}

	private static function rule_day( $relation, $val ) {
		$day  = gmdate( 'w', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$show = is_array( $val ) && ! empty( $val ) ? in_array( $day, $val ) : $val === $day; // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		return self::compare( $show, $relation );
	}

	private static function rule_os( $relation, $val ) {
		$pattern = is_string( $val ) && isset( self::OPERATING_SYSTEMS[ $val ] ) ? self::OPERATING_SYSTEMS[ $val ] : '';
		return self::compare( preg_match( '@' . $pattern . '@', Visitor::user_agent() ), $relation );
	}

	private static function rule_browser( $relation, $val ) {
		$agent = Visitor::user_agent();
		$has   = static function ( $needle ) use ( $agent ) {
			return false !== strpos( $agent, $needle );
		};
		switch ( $val ) {
			case 'ie':
				$show = $has( 'MSIE' ) || $has( 'Trident' );
				break;
			case 'edge':
				$show = $has( 'Edg' ) || $has( 'Edge' );
				break;
			case 'opera':
				$show = $has( 'OPR' );
				break;
			case 'chrome':
				$show = $has( 'Chrome' ) && ! $has( 'OPR' ) && ! $has( 'Edg' );
				break;
			case 'firefox':
				$show = $has( 'Firefox' );
				break;
			case 'safari':
				$show = $has( 'Safari' ) && ! $has( 'Chrome' );
				break;
			default:
				$show = false;
		}
		return self::compare( $show, $relation );
	}

	private static function rule_ex_url( $relation, $val ) {
		$show = false;
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$site = str_ireplace( 'www.', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			$url  = ! empty( $val ) ? (string) $val : sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( '' === $host || 0 === strcasecmp( $host, $site ) ) {
				return false;
			}
			$show = strrpos( strtolower( $host ), '.' . $site ) !== strlen( $host ) - strlen( '.' . $site );
		}
		return self::compare( $show, $relation );
	}

	private static function rule_url_parameters( $relation, $val ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri ) {
			return null;
		}
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		if ( '' === $query ) {
			return false;
		}
		$params = explode( "\n", sanitize_textarea_field( (string) $val ) );
		foreach ( $params as $index => $param ) {
			if ( ! strpos( $param, '=' ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide la visibilidad.
				$ref              = isset( $_GET[ $param ] ) && is_scalar( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
				$params[ $index ] = $param . '=' . rawurlencode( $ref );
			}
		}
		return self::compare( ! empty( array_intersect( $params, explode( '&', $query ) ) ), $relation );
	}

	private static function rule_url_string( $relation, $val ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( '' === $uri ) {
			return null;
		}
		return self::compare( false !== strpos( $uri, (string) $val ), $relation );
	}

	/** Element Pack sólo usa el primer buscador elegido y, sin selección, acepta cualquier referencia. */
	private static function rule_search_engine_url( $relation, $val ) {
		$show = false;
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$url    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$engine = '';
			foreach ( is_array( $val ) ? $val : array() as $value ) {
				if ( in_array( $value, array( 'google.com', 'yahoo.com', 'bing.com', 'yandex.com', 'baidu.com' ), true ) ) {
					$engine = $value;
					break;
				}
			}
			$show = false !== strpos( $url, $engine );
		}
		return self::compare( $show, $relation );
	}

	private static function rule_visit_count( $relation, $val, $row, $element_id ) {
		$cookie = 'bdt-visit-count-' . $element_id;
		$count  = isset( $_COOKIE[ $cookie ] ) ? absint( $_COOKIE[ $cookie ] ) + 1 : 0;
		if ( ! headers_sent() ) {
			setcookie( $cookie, (string) $count ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		}
		$show = is_array( $val ) && ! empty( $val ) ? in_array( $count, $val, true ) : $val > $count;
		return self::compare( $show, $relation );
	}

	/** Sin sesiones PHP en páginas públicas: se lee el contador guardado, pero no se incrementa. */
	private static function rule_session_count( $relation, $val, $row, $element_id ) {
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$cookie = '_ep_' . md5( $host ) . $element_id;
		$count  = false;
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			$data  = json_decode( stripslashes( sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) ) ), true );
			$count = is_array( $data ) && isset( $data['count'] ) ? $data['count'] : false;
		}
		$show = is_array( $val ) && ! empty( $val ) ? in_array( $count, $val, true ) : $val >= $count;
		return self::compare( $show, $relation );
	}

	private static function rule_language( $relation, $val ) {
		if ( empty( $val ) ) {
			return null;
		}
		return self::compare( in_array( get_locale(), (array) $val, true ), $relation );
	}

	private static function rule_country( $relation, $val ) {
		$country = strtolower( Visitor::country() );
		$codes   = array_filter( array_map( array( __CLASS__, 'country_code' ), (array) $val ) );
		return self::compare( '' !== $country && in_array( $country, $codes, true ), $relation );
	}

	/** Acepta el código guardado por versiones actuales o el nombre en inglés de las antiguas. */
	public static function country_code( $value ) {
		static $names;
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^[a-z]{2}$/', $value ) ) {
			return $value;
		}
		if ( null === $names ) {
			$names = require __DIR__ . '/countries-legacy.php';
		}
		return $names[ $value ] ?? '';
	}

	private static function rule_shortcode( $relation, $val, $row ) {
		$shortcode = $row['ep_condition_shortcode_name'] ?? '';
		if ( ! $shortcode ) {
			return null;
		}
		return self::compare( strval( do_shortcode( shortcode_unautop( (string) $shortcode ) ) ) === $val, $relation );
	}

	private static function cart() {
		$woo = function_exists( 'WC' ) ? WC() : null;
		return $woo && isset( $woo->cart ) && is_object( $woo->cart ) ? $woo->cart : null;
	}

	/** Producto principal de cada línea; las variaciones cuentan como su producto padre. */
	private static function cart_products() {
		$products = array();
		foreach ( self::cart()->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product && $product->is_type( 'variation' ) ) {
				$product = wc_get_product( $product->get_parent_id() );
			}
			if ( $product ) {
				$products[] = $product;
			}
		}
		return $products;
	}

	private static function cart_ids( $relation, $val, callable $ids ) {
		$cart = self::cart();
		if ( ! $cart || $cart->is_empty() ) {
			return false;
		}
		$found = array();
		foreach ( self::cart_products() as $product ) {
			$found = array_merge( $found, (array) $ids( $product ) );
		}
		return self::compare( ! empty( array_intersect( (array) $val, $found ) ), $relation );
	}

	private static function rule_products_in_cart( $relation, $val ) {
		return self::cart_ids( $relation, $val, static function ( $product ) {
			return $product->get_id();
		} );
	}

	private static function rule_categories_in_cart( $relation, $val ) {
		return self::cart_ids( $relation, $val, static function ( $product ) {
			return $product->get_category_ids();
		} );
	}

	private static function rule_tags_in_cart( $relation, $val ) {
		return self::cart_ids( $relation, $val, static function ( $product ) {
			return $product->get_tag_ids();
		} );
	}

	private static function threshold( $relation, $val, $actual ) {
		if ( '' === $val || null === $val ) {
			return false;
		}
		$show = 0 === (int) $val ? (int) $val === $actual : (int) $val <= $actual;
		return self::compare( $show, $relation );
	}

	private static function rule_cart_item_number( $relation, $val ) {
		$cart = self::cart();
		return $cart ? self::threshold( $relation, $val, $cart->get_cart_contents_count() ) : false;
	}

	private static function rule_cart_subtotal_price( $relation, $val ) {
		$cart = self::cart();
		return $cart ? self::threshold( $relation, $val, $cart->get_displayed_subtotal() ) : false;
	}

	/** Pedidos completados del usuario actual, como en Element Pack. */
	private static function completed_orders( array $args = array() ) {
		return wc_get_orders( $args + array(
			'customer_id' => get_current_user_id(),
			'status'      => array( 'wc-completed' ),
		) );
	}

	private static function completed_day( $order ) {
		return $order ? gmdate( 'Y-m-d', strtotime( (string) $order->get_date_completed() ) ) : false;
	}

	private static function rule_first_purchased_date( $relation, $val ) {
		if ( ! $val ) {
			return null;
		}
		$orders = self::completed_orders( array( 'order' => 'ASC', 'limit' => 1, 'orderby' => 'date_completed' ) );
		$day    = $orders ? self::completed_day( $orders[0] ) : false;
		return self::compare( $day ? $val <= $day : false, $relation );
	}

	private static function rule_last_purchased_date( $relation, $val ) {
		if ( ! $val ) {
			return null;
		}
		$orders = self::completed_orders( array( 'order' => 'DESC', 'limit' => 1, 'orderby' => 'date_completed' ) );
		$day    = $orders ? self::completed_day( $orders[0] ) : false;
		return self::compare( $day ? $val >= $day : false, $relation );
	}

	private static function rule_purchased_date( $relation, $val ) {
		if ( ! $val ) {
			return null;
		}
		$show = false;
		foreach ( self::completed_orders( array( 'order' => 'DESC', 'limit' => -1, 'orderby' => 'date_completed' ) ) as $order ) {
			$day = self::completed_day( $order );
			if ( $day && $val == $day ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
				$show = true;
			}
		}
		return self::compare( $show, $relation );
	}

	private static function rule_purchased_item_number( $relation, $val ) {
		if ( '' === $val || null === $val ) {
			return false;
		}
		return self::threshold( $relation, $val, count( self::completed_orders() ) );
	}

	private static function rule_orders_placed( $relation, $val, $row ) {
		if ( ! $val ) {
			return null;
		}
		$count    = count( self::completed_orders() );
		$operator = $row['ep_condition_addition_operator'] ?? 'equal';
		$show     = ( 'equal' === $operator && $count === $val ) || ( 'greater_or_equal' === $operator && $count >= $val );
		return self::compare( $show, $relation );
	}

	private static function rule_current_category_page( $relation, $val ) {
		if ( ! $val ) {
			return null;
		}
		$term = get_queried_object();
		if ( ! isset( $term->taxonomy, $term->term_id ) || ! in_array( $term->taxonomy, array( 'product_cat', 'yith_product_brand' ), true ) ) {
			return null;
		}
		return self::compare( false !== array_search( $term->term_id, (array) $val ), $relation ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
	}

	/** Producto de la página consultada; Element Pack no evalúa productos dentro de bucles. */
	private static function queried_product( $val ) {
		$id = get_queried_object_id();
		if ( '' === $val || 'product' !== get_post_type() || ! $id ) {
			return null;
		}
		$product = wc_get_product( $id );
		return $product ? $product : null;
	}

	private static function rule_single_product_price( $relation, $val ) {
		$product = self::queried_product( $val );
		return $product ? self::threshold( $relation, $val, $product->get_price() ) : false;
	}

	private static function rule_single_product_stock( $relation, $val ) {
		$product = self::queried_product( $val );
		if ( ! $product ) {
			return false;
		}
		if ( 0 === (int) $val ) {
			return self::compare( 0 == ( $product->is_in_stock() || $product->backorders_allowed() ), $relation ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		}
		return self::compare( (int) $val <= $product->get_stock_quantity(), $relation );
	}

	private static function rule_single_product_category( $relation, $val ) {
		$product = self::queried_product( $val );
		return $product ? self::compare( ! empty( array_intersect( (array) $val, $product->get_category_ids() ) ), $relation ) : null;
	}

	private static function rule_single_product_type( $relation, $val ) {
		$product = self::queried_product( $val );
		return $product ? self::compare( $val === $product->get_type(), $relation ) : false;
	}

	private static function product_flag( $val, $relation, $method ) {
		$product = self::queried_product( $val );
		return $product ? self::compare( $product->$method(), $relation ) : false;
	}

	private static function rule_single_product_downloadable( $relation, $val ) {
		return self::product_flag( $val, $relation, 'get_downloadable' );
	}

	private static function rule_single_product_virtual( $relation, $val ) {
		return self::product_flag( $val, $relation, 'get_virtual' );
	}

	private static function rule_single_product_featured( $relation, $val ) {
		return self::product_flag( $val, $relation, 'get_featured' );
	}

	private static function rule_single_product_backorder( $relation, $val ) {
		return self::product_flag( $val, $relation, 'is_on_backorder' );
	}

	private static function rule_single_product_onsale( $relation, $val ) {
		return self::product_flag( $val, $relation, 'is_on_sale' );
	}

	private static function rule_single_product_sold_individually( $relation, $val ) {
		return self::product_flag( $val, $relation, 'is_sold_individually' );
	}

	/** Valor del campo ACF en la entrada actual o en una página de opciones. */
	private static function acf_field( $row ) {
		$key = $row['ep_condition_' . $row['ep_condition_key'] . '_name'] ?? '';
		if ( ! $key || ! function_exists( 'get_field_object' ) ) {
			return null;
		}
		$field = get_field_object( $key );
		if ( ! is_array( $field ) ) {
			return null;
		}
		$options = array();
		if ( function_exists( 'acf_options_page' ) && function_exists( 'acf_get_field_groups' ) ) {
			foreach ( array_keys( (array) acf_options_page()->get_pages() ) as $slug ) {
				foreach ( acf_get_field_groups( array( 'options_page' => $slug ) ) as $group ) {
					$options[] = $group['ID'];
				}
			}
		}
		$source = in_array( $field['parent'] ?? null, $options, true ) ? get_field_object( $key, 'option' ) : $field;
		return array( $field, is_array( $source ) ? ( $source['value'] ?? null ) : null );
	}

	private static function rule_acf_boolean( $relation, $val, $row ) {
		$data = self::acf_field( $row );
		if ( ! $data ) {
			return null;
		}
		return self::compare( $data[1] === ( 'true' === sanitize_text_field( (string) $val ) ), $relation );
	}

	private static function rule_acf_text( $relation, $val, $row ) {
		$data = self::acf_field( $row );
		if ( ! $data ) {
			return null;
		}
		return self::compare( $data[1] === sanitize_text_field( (string) $val ), $relation );
	}

	private static function rule_acf_choice( $relation, $val, $row ) {
		$data = self::acf_field( $row );
		if ( ! $data ) {
			return null;
		}
		list( $field, $value ) = $data;
		$format = $field['return_format'] ?? 'value';
		$single = 'radio' === ( $field['type'] ?? '' ) || ( 'select' === ( $field['type'] ?? '' ) && empty( $field['multiple'] ) );
		$values = array();
		foreach ( $single ? array( $value ) : ( function_exists( 'acf_decode_choices' ) ? acf_decode_choices( $value ) : (array) $value ) as $item ) {
			$values[] = 'array' === $format ? ( $item['value'] ?? '' ) . ' : ' . ( $item['label'] ?? '' ) : $item . ' : ' . $item;
		}
		$val      = (string) $val;
		$accepted = false !== strpos( $val, ' : ' ) ? explode( "\n", $val ) : array( $val . ' : ' . $val );
		return self::compare( array_intersect( array_map( 'strtolower', $values ), array_map( 'strtolower', $accepted ) ), $relation );
	}
}
