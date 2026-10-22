<?php
namespace Digitalisimo\Elements\Woo\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de tipos de filtro del Loop Grid (F4-Woo, V.8.1 y V.15.2; F7b añadirá los suyos con la misma API).
 *
 * Un tipo tiene un nombre (`digitalisimo_price`), un prefijo reservado para sus nombres en la URL (`digi_price_`, en el
 * parámetro `e-filter-{bucle}-{nombre}`) y una función que recibe los argumentos de la consulta del bucle y los filtros
 * de ese tipo (`{ nombre: { terms, logicalJoin } }`) y devuelve los argumentos nuevos.
 *
 * Se apoya en los tres filtros que añade el derivado `modules/loop-filter/module.php` (V.8.2):
 * - `digitalisimo_elements_loop_filter_types`: los nombres de tipo registrados, que el derivado aparta del camino de
 *   taxonomía de PRO;
 * - `digitalisimo_elements_loop_filter_param_type`: el tipo de un nombre de la URL, sólo por su prefijo y sin consultar
 *   taxonomías (el módulo lee la URL en `init` 0, antes de que WooCommerce registre los `pa_*`, V-M3). Una taxonomía
 *   cuyo nombre empiece por un prefijo reservado no se puede filtrar con el filtro de PRO;
 * - `digitalisimo_elements_loop_filter_query`: aplica el tipo.
 *
 * Los tres se enganchan al registrar el primer tipo, en `plugins_loaded` (antes de que PRO construya sus módulos en
 * `elementor/init`). El tipo `taxonomy` de PRO no se puede registrar ni sustituir.
 *
 * Cuando el filtro de taxonomía de PRO calcula sus «elementos vacíos» repite la consulta del bucle con
 * `include_field_ids_arg` y quita sus propios filtros (`traits/taxonomy-filter-trait.php:77-110`): los tipos
 * registrados tampoco se aplican entonces, así la lista de términos de PRO no depende de un precio o de un atributo.
 */
final class Types {
	const PRICE            = 'digitalisimo_price';
	const PRICE_PREFIX     = 'digi_price_';
	const ATTRIBUTE        = 'digitalisimo_attribute';
	const ATTRIBUTE_PREFIX = 'digiattr_';

	/** @var array<string,array{prefix:string,apply:callable}> */
	private static $types = array();

	/** @var bool */
	private static $hooked = false;

	/**
	 * Registra un tipo. Un nombre o un prefijo inválidos, o `taxonomy`, se ignoran.
	 *
	 * @param string   $type            `[a-z][a-z0-9_]*`, distinto de `taxonomy`.
	 * @param string   $reserved_prefix `[a-z][a-z0-9_]*_` (termina en guion bajo).
	 * @param callable $apply           `function( array $query_args, array $filters, $widget ): array`.
	 */
	public static function register( string $type, string $reserved_prefix, callable $apply ): void {
		if ( 'taxonomy' === $type || ! preg_match( '/^[a-z][a-z0-9_]{0,40}$/', $type ) || ! preg_match( '/^[a-z][a-z0-9_]{0,20}_$/', $reserved_prefix ) ) {
			return;
		}
		self::$types[ $type ] = array( 'prefix' => $reserved_prefix, 'apply' => $apply );
		self::hook();
	}

	/** Engancha los tres filtros del derivado de PRO una sola vez. */
	private static function hook(): void {
		if ( self::$hooked || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$hooked = true;
		add_filter( 'digitalisimo_elements_loop_filter_types', array( __CLASS__, 'types' ) );
		add_filter( 'digitalisimo_elements_loop_filter_param_type', array( __CLASS__, 'param_type' ), 10, 2 );
		add_filter( 'digitalisimo_elements_loop_filter_query', array( __CLASS__, 'query' ), 10, 4 );
	}

	/** Tipos registrados (nombre => prefijo). */
	public static function registered(): array {
		return array_map(
			function ( $type ) {
				return $type['prefix'];
			},
			self::$types
		);
	}

	/**
	 * Tipo de un nombre de la URL por su prefijo reservado, o '' si no es de ningún tipo registrado.
	 *
	 * @param string $name Lo que sigue a `e-filter-{bucle}-`.
	 */
	public static function type_for( string $name ): string {
		foreach ( self::$types as $type => $data ) {
			if ( 0 === strpos( $name, $data['prefix'] ) && strlen( $name ) > strlen( $data['prefix'] ) ) {
				return $type;
			}
		}
		return '';
	}

	/**
	 * `digitalisimo_elements_loop_filter_types`.
	 *
	 * @param mixed $types Tipos de otros registros.
	 * @return string[]
	 */
	public static function types( $types ): array {
		return array_values( array_unique( array_merge( is_array( $types ) ? $types : array(), array_keys( self::$types ) ) ) );
	}

	/**
	 * `digitalisimo_elements_loop_filter_param_type`: un nombre reservado da su tipo; cualquier otro, lo recibido.
	 *
	 * @param mixed $type Tipo propuesto (`taxonomy`).
	 * @param mixed $name Nombre leído de la URL.
	 * @return mixed
	 */
	public static function param_type( $type, $name ) {
		if ( 'taxonomy' !== $type || ! is_string( $name ) ) {
			return $type;
		}
		$found = self::type_for( $name );
		return '' !== $found ? $found : $type;
	}

	/**
	 * `digitalisimo_elements_loop_filter_query`: aplica un tipo registrado a la consulta del bucle.
	 *
	 * @param mixed  $query_args Argumentos de la consulta.
	 * @param mixed  $type       Tipo.
	 * @param mixed  $filters    Filtros de ese tipo.
	 * @param object $widget     Bucle.
	 * @return mixed
	 */
	public static function query( $query_args, $type, $filters, $widget = null ) {
		if ( ! is_array( $query_args ) || ! is_string( $type ) || ! isset( self::$types[ $type ] ) || ! is_array( $filters ) ) {
			return $query_args;
		}
		if ( ! empty( $GLOBALS['wp_query'] ) && is_object( $GLOBALS['wp_query'] ) && ! empty( $GLOBALS['wp_query']->include_field_ids_arg ) ) {
			return $query_args;
		}
		$result = call_user_func( self::$types[ $type ]['apply'], $query_args, $filters, $widget );
		return is_array( $result ) ? $result : $query_args;
	}

	/** Para las pruebas: olvida los tipos (los filtros enganchados quedan, sin tipos no hacen nada). */
	public static function reset(): void {
		self::$types = array();
	}
}
