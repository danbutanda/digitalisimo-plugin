<?php
defined( 'ABSPATH' ) || exit;

/**
 * Consulta el vocabulario oficial de schema.org (schema-vocabulary.php, generado
 * por scripts/schema-vocabulary.mjs). Ningún tipo ni propiedad se da por válido
 * si no figura allí: es lo que impide publicar tipos inventados.
 */
class Digitalisimo_Integrations_Schema_Vocabulary {
	private static $data = null;
	private static $ancestors = array();

	private static function data() {
		if ( null === self::$data ) self::$data = require __DIR__ . '/schema-vocabulary.php';
		return self::$data;
	}

	/** «https://schema.org/Thing», «schema:Thing» y «Thing» son el mismo tipo. */
	public static function short( $name ) {
		return preg_replace( '#^(?:https?://schema\.org/|schema:)#i', '', trim( (string) $name ) );
	}

	/** official, pending, superseded o unknown. */
	public static function status( $name ) {
		$types = self::data()['types'];
		$name  = self::short( $name );
		if ( ! isset( $types[ $name ] ) ) return 'unknown';
		return $types[ $name ][1] ?: 'official';
	}

	public static function replacement( $name ) {
		$types = self::data()['types'];
		$name  = self::short( $name );
		return isset( $types[ $name ] ) ? (string) $types[ $name ][2] : '';
	}

	/** El tipo y todos sus antecesores. */
	public static function ancestors( $name ) {
		$name = self::short( $name );
		if ( isset( self::$ancestors[ $name ] ) ) return self::$ancestors[ $name ];
		$types = self::data()['types'];
		$seen  = array();
		$queue = array( $name );
		while ( $queue ) {
			$current = array_shift( $queue );
			if ( isset( $seen[ $current ] ) || ! isset( $types[ $current ] ) ) continue;
			$seen[ $current ] = true;
			foreach ( array_filter( explode( ',', $types[ $current ][0] ) ) as $parent ) $queue[] = $parent;
		}
		return self::$ancestors[ $name ] = array_keys( $seen );
	}

	public static function is_a( $types, $parent ) {
		foreach ( (array) $types as $type ) if ( in_array( $parent, self::ancestors( $type ), true ) ) return true;
		return false;
	}

	/** Subtipos vigentes de $parent, incluido él mismo, en orden alfabético. */
	public static function subtypes( $parent ) {
		$out = array();
		foreach ( self::data()['types'] as $name => $row ) if ( '' === $row[1] && self::is_a( $name, $parent ) ) $out[] = $name;
		sort( $out );
		return $out;
	}

	/** official, pending, superseded o unknown. */
	public static function property_status( $name ) {
		$props = self::data()['properties'];
		if ( ! isset( $props[ $name ] ) ) return 'unknown';
		return $props[ $name ][1] ?: 'official';
	}

	public static function property_replacement( $name ) {
		$props = self::data()['properties'];
		return isset( $props[ $name ] ) ? (string) $props[ $name ][2] : '';
	}

	/** La propiedad admite alguno de los tipos (por herencia). Desconocida: false. */
	public static function property_fits( $types, $name ) {
		$props = self::data()['properties'];
		if ( ! isset( $props[ $name ] ) ) return false;
		$domains = array_filter( explode( ',', $props[ $name ][0] ) );
		if ( ! $domains ) return true;
		foreach ( (array) $types as $type ) if ( array_intersect( $domains, self::ancestors( $type ) ) ) return true;
		return false;
	}

	/**
	 * Un tipo oficial parecido: misma palabra con otra capitalización o con
	 * espacios («Local Business» → LocalBusiness). Vacío si no hay ninguno.
	 */
	public static function suggest( $name ) {
		$key = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', self::short( $name ) ) );
		if ( '' === $key ) return '';
		foreach ( self::data()['types'] as $type => $row ) if ( '' === $row[1] && strtolower( $type ) === $key ) return $type;
		return '';
	}

	/** Un tipo de LocalBusiness válido; cualquier otro valor cae en LocalBusiness. */
	public static function local_type( $value ) {
		$value = self::short( $value );
		if ( 'official' !== self::status( $value ) ) $value = self::suggest( $value );
		return $value && 'official' === self::status( $value ) && self::is_a( $value, 'LocalBusiness' ) ? $value : 'LocalBusiness';
	}
}
