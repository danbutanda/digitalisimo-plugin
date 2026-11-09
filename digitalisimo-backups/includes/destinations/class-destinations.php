<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registro de los destinos remotos y de sus conexiones.
 *
 * - Cada tipo tiene una conexión principal cuyo identificador es el del tipo (`sftp`, `s3`…) y cuya configuración vive,
 *   como siempre, en `digitalisimo_backups_settings` con el prefijo del tipo.
 * - Se pueden crear más conexiones con nombre del mismo tipo: `<tipo>-<8 hex>` en `digitalisimo_backups_connections`
 *   (sólo valores no secretos; viajan en el SQL como los ajustes). Sus secretos van al almacén cifrado de S4 con el
 *   campo `<id>/<clave>`, así que el dato adicional del AEAD ata cada valor a su conexión y a su campo.
 * - Los trabajos, las automatizaciones y el historial nombran la conexión por su identificador.
 */
final class Digitalisimo_Backups_Destinations {
	const CONNECTIONS = 'digitalisimo_backups_connections';
	const MAX_CONNECTIONS = 50;
	/** Tipos en el orden de la pantalla. */
	const CLASSES = array(
		'sftp' => 'Digitalisimo_Backups_Destination_Sftp',
		'ftp' => 'Digitalisimo_Backups_Destination_Ftp',
		'webdav' => 'Digitalisimo_Backups_Destination_Webdav',
		'google' => 'Digitalisimo_Backups_Destination_Google',
		'onedrive' => 'Digitalisimo_Backups_Destination_Onedrive',
		'dropbox' => 'Digitalisimo_Backups_Destination_Dropbox',
		's3' => 'Digitalisimo_Backups_Destination_S3',
		's3compat' => 'Digitalisimo_Backups_Destination_S3_Compatible',
		'pcloud' => 'Digitalisimo_Backups_Destination_Pcloud',
	);

	public static function types() { return array_keys( self::CLASSES ); }
	public static function class_of( $type ) { return self::CLASSES[ $type ] ?? ''; }
	public static function is_type( $type ) { return is_string( $type ) && isset( self::CLASSES[ $type ] ); }
	/** Identificador de una conexión adicional: `<tipo>-<8 hex>`. */
	public static function is_connection_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^([a-z0-9]+)-[a-f0-9]{8}$/', $id, $match ) && self::is_type( $match[1] );
	}
	/** Tipo de un identificador (el propio tipo o el prefijo de una conexión adicional); '' si no es de un destino remoto. */
	public static function type_of( $id ) {
		if ( self::is_type( $id ) ) return $id;
		if ( self::is_connection_id( $id ) ) return substr( $id, 0, strrpos( $id, '-' ) );
		return '';
	}
	public static function label( $type ) {
		$class = self::class_of( $type );
		return '' !== $class ? $class::label() : '';
	}

	/** Valores por defecto de las conexiones principales de todos los tipos. */
	public static function defaults() {
		$out = array();
		foreach ( self::CLASSES as $class ) $out += $class::defaults();
		return $out;
	}
	/** Claves secretas de todos los tipos (las de S4 primero, como las declara el almacén). */
	public static function secret_fields() {
		$out = array();
		foreach ( self::CLASSES as $class ) foreach ( $class::secrets() as $field ) $out[] = $field;
		return $out;
	}

	/* ------------------------------------------------------------------ Conexiones adicionales */

	/** Conexiones adicionales guardadas: id => array( type, name, settings ). Lo que no es válido se ignora. */
	public static function connections() {
		$saved = get_option( self::CONNECTIONS, array() );
		$out = array();
		foreach ( is_array( $saved ) ? $saved : array() as $id => $row ) {
			if ( ! self::is_connection_id( $id ) || ! is_array( $row ) ) continue;
			$type = self::type_of( $id );
			$settings = array_intersect_key( (array) ( $row['settings'] ?? array() ), self::class_of( $type )::defaults() );
			foreach ( self::class_of( $type )::secrets() as $field ) unset( $settings[ $field ] );
			$out[ $id ] = array( 'type' => $type, 'name' => (string) ( $row['name'] ?? self::label( $type ) ), 'settings' => array_map( 'strval', $settings ) );
		}
		return $out;
	}
	public static function save_connections( array $connections ) {
		update_option( self::CONNECTIONS, $connections, false );
	}
	public static function new_connection_id( $type ) {
		if ( ! self::is_type( $type ) ) throw new Exception( 'Tipo de destino desconocido.' );
		do { $id = $type . '-' . bin2hex( random_bytes( 4 ) ); } while ( isset( self::connections()[ $id ] ) );
		return $id;
	}
	/** Si existe el destino: local, un tipo o una conexión adicional guardada. */
	public static function exists( $id ) {
		return 'local' === $id || self::is_type( $id ) || ( self::is_connection_id( $id ) && isset( self::connections()[ $id ] ) );
	}
	/** Nombre para la pantalla: el de una conexión adicional o el del tipo. */
	public static function name_of( $id, array $settings = array() ) {
		if ( self::is_connection_id( $id ) ) {
			$connections = self::connections();
			return isset( $connections[ $id ] ) ? $connections[ $id ]['name'] : self::label( self::type_of( $id ) );
		}
		$type = self::type_of( $id );
		if ( '' === $type ) return '';
		$class = self::class_of( $type );
		$name = (string) ( $settings[ $class::PREFIX . 'name' ] ?? '' );
		return '' !== $name ? $name : $class::label();
	}
	/** Campo del almacén cifrado para un secreto de una conexión (la principal usa la clave tal cual). */
	public static function secret_key( $id, $field ) { return self::is_connection_id( $id ) ? $id . '/' . $field : $field; }
}
