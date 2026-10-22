<?php
namespace Digitalisimo\Elements\Conditions;

use DateTime;
use DateTimeZone;
use Digitalisimo\Elements\Display_Conditions;
use Elementor\Controls_Manager;
use ElementorPro\Modules\DisplayConditions\Classes\Comparator_Provider as Comparators;
use ElementorPro\Modules\DisplayConditions\Classes\Comparators_Checker as Checker;
use ElementorPro\Modules\QueryControl\Module as Query;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-conditions.php';
require_once __DIR__ . '/class-conditions-guard.php';

/**
 * Condiciones de F7a (3.4.1, V.4): sólo lo que el gestor nativo no tenía. Mismo gestor, mismo JSON
 * (`e_display_conditions`), mismos grupos Y/O y la misma interfaz: no hay otro motor.
 *
 * Reglas comunes, además de las de `Conditions_Guard` (centinela y permiso de la cookie):
 * - se registran siempre; sin WooCommerce, las de existencias no se cumplen;
 * - una lista vacía y un valor esperado vacío nunca se cumplen, con ningún comparador;
 * - `check()` nunca imprime nada ni lee opciones del sitio;
 * - cada una lleva en su configuración del editor el aviso común (`Conditions_Guard::NOTICE`): una condición decide
 *   qué se muestra, no quién puede leerlo.
 */
trait More_Condition {
	protected function get_initial_config() {
		$config                        = parent::get_initial_config();
		$config['digitalisimo_notice'] = Conditions_Guard::NOTICE;
		return $config;
	}

	/** Aviso común (3.4.1). */
	public static function notice(): string {
		return Conditions_Guard::NOTICE;
	}

	/** Texto comparable: un escalar tal cual; una lista de escalares (ACF), unida por comas; lo demás, vacío. */
	protected static function as_text( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( is_scalar( $value ) ) {
			return trim( (string) $value );
		}
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
					$parts[] = trim( (string) $item );
				}
			}
			return implode( ', ', $parts );
		}
		return '';
	}

	/**
	 * Compara un campo con el valor esperado: texto (sin mayúsculas), número con decimales o fecha `AAAA-MM-DD`.
	 * Un valor esperado vacío sólo sirve para «está vacío» y «no está vacío».
	 */
	protected static function compare_field( array $args, $raw, bool $dates ): bool {
		$comparator = isset( $args['comparator'] ) && is_scalar( $args['comparator'] ) ? (string) $args['comparator'] : '';
		$actual     = self::as_text( $raw );
		$expected   = isset( $args['value'] ) && is_scalar( $args['value'] ) ? trim( (string) $args['value'] ) : '';
		switch ( $comparator ) {
			case Comparators::COMPARATOR_IS_EMPTY:
				return '' === $actual;
			case Comparators::COMPARATOR_IS_NOT_EMPTY:
				return '' !== $actual;
			case Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE:
			case Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE:
				return self::number( $args, $actual );
			case Comparators::COMPARATOR_IS_BEFORE:
			case Comparators::COMPARATOR_IS_AFTER:
				$day = $dates ? self::field_day( $actual ) : null;
				$set = self::ymd( $expected );
				if ( null === $day || null === $set ) {
					return false;
				}
				return Comparators::COMPARATOR_IS_BEFORE === $comparator ? $day < $set : $day > $set;
			case Comparators::COMPARATOR_IS:
			case Comparators::COMPARATOR_IS_NOT:
			case Comparators::COMPARATOR_CONTAINS:
			case Comparators::COMPARATOR_NOT_CONTAIN:
				return '' !== $expected && Checker::check_string_contains( $comparator, $expected, $actual );
		}
		return false;
	}

	/** Fecha escrita por el administrador: sólo `AAAA-MM-DD`. */
	protected static function ymd( string $value ): ?string {
		$day = DateTime::createFromFormat( '!Y-m-d', $value, self::zone() );
		return $day && $day->format( 'Y-m-d' ) === $value ? $value : null;
	}

	/**
	 * Día de un campo en la zona horaria del sitio: `AAAA-MM-DD` (con hora o sin ella), `AAAAMMDD` (el selector de
	 * fecha de ACF) o una marca de tiempo Unix.
	 */
	protected static function field_day( string $value ): ?string {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value, $m ) || preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : null;
		}
		if ( preg_match( '/^\d{9,11}$/', $value ) ) {
			$day = new DateTime( '@' . $value );
			$day->setTimezone( self::zone() );
			return $day->format( 'Y-m-d' );
		}
		return null;
	}

	protected static function zone(): DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	}

	protected static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Clave de un campo que se puede comparar, o ''. Nunca una meta protegida (con guion bajo) ni una clave que suene a
	 * secreto (R14); la lista sólo se amplía con `digitalisimo_elements_field_condition_denied`.
	 */
	public static function field_key( $raw, string $type = 'post' ): string {
		$key = trim( is_scalar( $raw ) ? (string) $raw : '' );
		if ( ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $key ) || '_' === $key[0] ) {
			return '';
		}
		if ( function_exists( 'is_protected_meta' ) && is_protected_meta( $key, $type ) ) {
			return '';
		}
		$lower = strtolower( $key );
		$extra = function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_elements_field_condition_denied', array(), $key ) : array();
		foreach ( array_merge( array( 'pass', 'secret', 'token', 'key', 'nonce', 'hash', 'salt' ), $extra ) as $fragment ) {
			$fragment = strtolower( trim( is_scalar( $fragment ) ? (string) $fragment : '' ) );
			if ( '' !== $fragment && false !== strpos( $lower, $fragment ) ) {
				return '';
			}
		}
		return $key;
	}
}

/** Cookie del navegador: existe, no existe, es o no es. Nunca «contiene», que permitiría leer el valor letra a letra. */
final class Cookie_Condition extends Base {
	use More_Condition;

	public function get_name() { return 'digitalisimo_cookie'; }
	public function get_label() { return 'Cookie del navegador'; }
	public function get_group() { return Display_Conditions::GROUP_VISITOR; }

	public function get_options() {
		$this->add_text( 'name', 'nombre_de_la_cookie' );
		$this->add_comparator(
			array( Comparators::COMPARATOR_IS, Comparators::COMPARATOR_IS_NOT ),
			array(
				Comparators::COMPARATOR_IS_NOT_EMPTY => 'Existe',
				Comparators::COMPARATOR_IS_EMPTY     => 'No existe',
			)
		);
		$this->add_text( 'value', 'Valor (sólo para «es» y «no es»)', false );
	}

	public function check( $args ) : bool {
		$name = Conditions_Guard::cookie_name( isset( $args['name'] ) ? $args['name'] : '' );
		if ( '' === $name ) {
			return false;
		}
		if ( isset( $_COOKIE[ $name ] ) && ! is_string( $_COOKIE[ $name ] ) ) {
			return false; // Una cookie con corchetes llega como lista: no se compara.
		}
		$value      = isset( $_COOKIE[ $name ] ) ? (string) wp_unslash( $_COOKIE[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sólo se compara, nunca se imprime.
		$comparator = isset( $args['comparator'] ) && is_scalar( $args['comparator'] ) ? (string) $args['comparator'] : '';
		switch ( $comparator ) {
			case Comparators::COMPARATOR_IS_NOT_EMPTY:
				return '' !== $value;
			case Comparators::COMPARATOR_IS_EMPTY:
				return '' === $value;
			case Comparators::COMPARATOR_IS:
			case Comparators::COMPARATOR_IS_NOT:
				$expected = isset( $args['value'] ) && is_scalar( $args['value'] ) ? (string) $args['value'] : '';
				if ( '' === $expected ) {
					return false;
				}
				return ( Comparators::COMPARATOR_IS === $comparator ) === hash_equals( $expected, $value );
		}
		return false;
	}
}

/** Visitó (o no) alguna de estas páginas, según la cookie de visitas del sitio (`Page_Visits`). */
final class Page_Visit_Condition extends Base {
	use More_Condition;

	public function get_name() { return 'digitalisimo_page_visit'; }
	public function get_label() { return 'Visitó una página'; }
	public function get_group() { return Display_Conditions::GROUP_VISITOR; }

	public function get_options() {
		$this->add_comparator(
			array(),
			array(
				Comparators::COMPARATOR_IS_ONE_OF  => 'Visitó alguna de',
				Comparators::COMPARATOR_IS_NONE_OF => 'No visitó ninguna de',
			)
		);
		$this->add_query( 'pages', array(
			'object' => Query::QUERY_OBJECT_POST,
			'query'  => array( 'post_type' => 'any', 'post_status' => 'publish' ),
		) );
	}

	/** Sin cookie (sin consentimiento o sin visitas), «visitó» no se cumple y «no visitó» sí. */
	public function check( $args ) : bool {
		$expected = self::ids( isset( $args['pages'] ) ? $args['pages'] : array() );
		if ( ! $expected ) {
			return false;
		}
		$seen = class_exists( __NAMESPACE__ . '\\Page_Visits' ) ? Page_Visits::seen() : array();
		$hit  = (bool) array_intersect( $expected, $seen );
		switch ( isset( $args['comparator'] ) ? (string) $args['comparator'] : '' ) {
			case Comparators::COMPARATOR_IS_ONE_OF:
				return $hit;
			case Comparators::COMPARATOR_IS_NONE_OF:
				return ! $hit;
		}
		return false;
	}
}

/** Campo personalizado (meta o ACF) del contenido o del término que se está viendo. */
final class Field_Condition extends Base {
	use More_Condition;

	public function get_name() { return 'digitalisimo_field'; }
	public function get_label() { return 'Campo personalizado'; }
	public function get_group() { return 'page'; }

	public function get_options() {
		$this->add_control( 'source', array(
			'type'    => Controls_Manager::SELECT,
			'options' => array(
				'post' => 'Del contenido que se ve',
				'term' => 'Del término que se ve (categoría, etiqueta…)',
			),
			'default' => 'post',
		) );
		$this->add_text( 'key', 'nombre_del_campo' );
		$this->add_comparator( array(
			Comparators::COMPARATOR_IS,
			Comparators::COMPARATOR_IS_NOT,
			Comparators::COMPARATOR_CONTAINS,
			Comparators::COMPARATOR_NOT_CONTAIN,
			Comparators::COMPARATOR_IS_EMPTY,
			Comparators::COMPARATOR_IS_NOT_EMPTY,
			Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
			Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
			Comparators::COMPARATOR_IS_BEFORE,
			Comparators::COMPARATOR_IS_AFTER,
		) );
		$this->add_text( 'value', 'Valor (fechas: AAAA-MM-DD)', false );
	}

	/** Siempre el objeto consultado (no `get_the_ID()`): dentro de un bucle no compara otra entrada. */
	public function check( $args ) : bool {
		$type = isset( $args['source'] ) && 'term' === $args['source'] ? 'term' : 'post';
		$key  = self::field_key( isset( $args['key'] ) ? $args['key'] : '', $type );
		if ( '' === $key ) {
			return false;
		}
		if ( 'term' === $type ) {
			$term = get_queried_object();
			if ( ! ( is_category() || is_tag() || is_tax() ) || ! $term instanceof \WP_Term ) {
				return false;
			}
			return self::compare_field( $args, self::read( 'term', (int) $term->term_id, $key ), true );
		}
		$id = is_singular() ? (int) get_queried_object_id() : 0;
		return $id > 0 && self::compare_field( $args, self::read( 'post', $id, $key ), true );
	}

	/** Con ACF, su valor sin formato (`'term_' . $id` en un término); sin él, o si ACF no lo conoce, la meta. */
	private static function read( string $type, int $id, string $key ) {
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $key, 'term' === $type ? 'term_' . $id : $id, false );
			if ( null !== $value ) {
				return $value;
			}
		}
		return 'term' === $type ? get_term_meta( $id, $key, true ) : get_post_meta( $id, $key, true );
	}
}

/** Campo del perfil del usuario con sesión, de una lista blanca (D25); sin sesión no se cumple. */
final class User_Field_Condition extends Base {
	use More_Condition;

	const KEYS = array(
		'first_name'      => 'Nombre',
		'last_name'       => 'Apellidos',
		'nickname'        => 'Alias',
		'description'     => 'Biografía',
		'locale'          => 'Idioma del perfil',
		'billing_country' => 'País de facturación',
	);

	public function get_name() { return 'digitalisimo_user_field'; }
	public function get_label() { return 'Campo del usuario'; }
	public function get_group() { return 'user'; }

	/** Claves que se pueden comparar: la lista blanca o la que devuelva `digitalisimo_elements_field_condition_user_keys`, sin metas protegidas ni secretas. */
	public static function keys(): array {
		$keys = function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_elements_field_condition_user_keys', array_keys( self::KEYS ) ) : array_keys( self::KEYS );
		$out  = array();
		foreach ( $keys as $key ) {
			$key = self::field_key( $key, 'user' );
			if ( '' !== $key ) {
				$out[ $key ] = isset( self::KEYS[ $key ] ) ? self::KEYS[ $key ] : $key;
			}
		}
		return $out;
	}

	public function get_options() {
		$keys = self::keys();
		$this->add_control( 'key', array(
			'type'    => Controls_Manager::SELECT,
			'options' => $keys,
			'default' => (string) array_key_first( $keys ),
		) );
		$this->add_comparator( array(
			Comparators::COMPARATOR_IS,
			Comparators::COMPARATOR_IS_NOT,
			Comparators::COMPARATOR_CONTAINS,
			Comparators::COMPARATOR_NOT_CONTAIN,
			Comparators::COMPARATOR_IS_EMPTY,
			Comparators::COMPARATOR_IS_NOT_EMPTY,
			Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE,
			Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE,
		) );
		$this->add_text( 'value', 'Valor', false );
	}

	public function check( $args ) : bool {
		$user = (int) get_current_user_id();
		$key  = isset( $args['key'] ) && is_scalar( $args['key'] ) ? (string) $args['key'] : '';
		if ( $user <= 0 || ! isset( self::keys()[ $key ] ) ) {
			return false;
		}
		return self::compare_field( $args, get_user_meta( $user, $key, true ), false );
	}
}

/** Autor del contenido que se ve (o del archivo de autor): es el usuario actual, o tiene uno de estos roles. */
final class Author_Condition extends Base {
	use More_Condition;

	public function get_name() { return 'digitalisimo_author'; }
	public function get_label() { return 'Autor del contenido'; }
	public function get_group() { return 'page'; }

	public function get_options() {
		$this->add_comparator(
			array(),
			array(
				'is_current_user'     => 'Es el usuario actual',
				'is_not_current_user' => 'No es el usuario actual',
				'role_in'             => 'Tiene uno de estos roles',
				'role_not_in'         => 'No tiene ninguno de estos roles',
			)
		);
		$this->add_control( 'roles', array(
			'type'        => Controls_Manager::SELECT2,
			'options'     => self::roles(),
			'multiple'    => true,
			'required'    => false,
			'default'     => array(),
			'placeholder' => 'Roles (sólo para «tiene» y «no tiene»)',
		) );
	}

	private static function roles(): array {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}
		$roles = array();
		foreach ( (array) wp_roles()->get_names() as $role => $name ) {
			$roles[ $role ] = function_exists( 'translate_user_role' ) ? translate_user_role( $name ) : $name;
		}
		return $roles;
	}

	/** Autor del contenido consultado en una vista individual, o el del archivo de autor; 0 en lo demás. */
	public static function author(): int {
		if ( is_singular() ) {
			$id = (int) get_queried_object_id();
			return $id > 0 ? (int) get_post_field( 'post_author', $id ) : 0;
		}
		return is_author() ? (int) get_queried_object_id() : 0;
	}

	public function check( $args ) : bool {
		$author = self::author();
		if ( $author <= 0 ) {
			return false;
		}
		$comparator = isset( $args['comparator'] ) ? (string) $args['comparator'] : '';
		switch ( $comparator ) {
			case 'is_current_user':
				return (int) get_current_user_id() === $author;
			case 'is_not_current_user':
				return (int) get_current_user_id() !== $author;
			case 'role_in':
			case 'role_not_in':
				$expected = self::values( isset( $args['roles'] ) ? $args['roles'] : array() );
				if ( ! $expected ) {
					return false;
				}
				$user = get_userdata( $author );
				$hit  = (bool) array_intersect( $expected, $user && isset( $user->roles ) ? array_map( 'strval', (array) $user->roles ) : array() );
				return 'role_in' === $comparator ? $hit : ! $hit;
		}
		return false;
	}
}

/** Estado o cantidad en existencia de productos concretos, desde cualquier página. Sin WooCommerce no se cumple. */
final class Product_Stock_Of_Condition extends Woo_Base {
	use More_Condition;

	const STATUSES = array( 'instock', 'outofstock', 'onbackorder' );

	public function get_name() { return 'digitalisimo_product_stock_of'; }
	public function get_label() { return 'Existencias de productos concretos'; }

	public function get_options() {
		$this->add_query( 'products', array(
			'object' => Query::QUERY_OBJECT_POST,
			'query'  => array( 'post_type' => 'product', 'post_status' => 'publish' ),
		) );
		$this->add_control( 'match', array(
			'type'    => Controls_Manager::SELECT,
			'options' => array(
				'any' => 'Alguno',
				'all' => 'Todos',
			),
			'default' => 'any',
		) );
		$this->add_comparator(
			array( Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE, Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE ),
			array(
				'instock'     => 'Está en existencia',
				'outofstock'  => 'Está agotado',
				'onbackorder' => 'Está bajo pedido',
			)
		);
		$this->add_text( 'value', 'Unidades (sólo al comparar cantidades)', false );
	}

	public function check( $args ) : bool {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$ids = self::ids( isset( $args['products'] ) ? $args['products'] : array() );
		if ( ! $ids ) {
			return false;
		}
		$all = isset( $args['match'] ) && 'all' === $args['match'];
		foreach ( $ids as $id ) {
			$ok = self::matches( $args, wc_get_product( $id ) );
			if ( $all && ! $ok ) {
				return false;
			}
			if ( ! $all && $ok ) {
				return true;
			}
		}
		return $all;
	}

	/** Sin gestión de inventario no hay cantidad: una comparación de unidades no se cumple. */
	private static function matches( array $args, $product ): bool {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_stock_status' ) ) {
			return false;
		}
		$comparator = isset( $args['comparator'] ) ? (string) $args['comparator'] : '';
		if ( in_array( $comparator, self::STATUSES, true ) ) {
			return $comparator === $product->get_stock_status();
		}
		if ( Comparators::COMPARATOR_IS_LESS_THAN_INCLUSIVE === $comparator || Comparators::COMPARATOR_IS_GREATER_THAN_INCLUSIVE === $comparator ) {
			$quantity = $product->get_stock_quantity();
			return null !== $quantity && self::number( $args, $quantity );
		}
		return false;
	}
}

/** Término buscado o búsqueda con o sin resultados. Sólo en la página de resultados y sin expresiones regulares. */
final class Search_Condition extends Base {
	use More_Condition;

	public function get_name() { return 'digitalisimo_search'; }
	public function get_label() { return 'Búsqueda del visitante'; }
	public function get_group() { return 'page'; }

	public function get_options() {
		$this->add_comparator(
			array(),
			array(
				Comparators::COMPARATOR_IS          => 'El término es',
				Comparators::COMPARATOR_IS_NOT      => 'El término no es',
				Comparators::COMPARATOR_CONTAINS    => 'El término contiene',
				Comparators::COMPARATOR_NOT_CONTAIN => 'El término no contiene',
				Comparators::COMPARATOR_IS_ONE_OF   => 'El término es uno de (separados por comas)',
				'no_results'                        => 'No encontró resultados',
				'has_results'                       => 'Encontró resultados',
			)
		);
		$this->add_text( 'value', 'Término (no hace falta para los resultados)', false );
	}

	public function check( $args ) : bool {
		if ( ! is_search() ) {
			return false;
		}
		$comparator = isset( $args['comparator'] ) ? (string) $args['comparator'] : '';
		if ( 'no_results' === $comparator || 'has_results' === $comparator ) {
			global $wp_query;
			if ( ! is_object( $wp_query ) || ! isset( $wp_query->found_posts ) ) {
				return false;
			}
			$found = (int) $wp_query->found_posts;
			return 'no_results' === $comparator ? 0 === $found : $found > 0;
		}
		$term     = self::lower( trim( (string) get_search_query( false ) ) );
		$expected = self::lower( trim( isset( $args['value'] ) && is_scalar( $args['value'] ) ? (string) $args['value'] : '' ) );
		if ( '' === $expected ) {
			return false;
		}
		switch ( $comparator ) {
			case Comparators::COMPARATOR_IS:
				return $term === $expected;
			case Comparators::COMPARATOR_IS_NOT:
				return $term !== $expected;
			case Comparators::COMPARATOR_CONTAINS:
				return false !== strpos( $term, $expected );
			case Comparators::COMPARATOR_NOT_CONTAIN:
				return false === strpos( $term, $expected );
			case Comparators::COMPARATOR_IS_ONE_OF:
				$list = array();
				foreach ( explode( ',', $expected ) as $item ) {
					if ( '' !== trim( $item ) ) {
						$list[] = trim( $item );
					}
				}
				return in_array( $term, $list, true );
		}
		return false;
	}
}
