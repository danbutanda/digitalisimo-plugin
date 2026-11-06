<?php
defined( 'ABSPATH' ) || exit;

/**
 * libsodium nativo o, si el servidor no lo tiene, el sodium_compat que WordPress trae en wp-includes. Con los dos se
 * cifra y se descifra el mismo formato: un respaldo cifrado en un servidor se abre en otro sin la extensión.
 */
final class Digitalisimo_Backups_Sodium {
	/** Valores fijos de libsodium para crypto_secretstream_xchacha20poly1305. */
	const TAG_MESSAGE = 0;
	const TAG_FINAL = 3;
	const ABYTES = 17;
	const HEADERBYTES = 24;
	const KEYBYTES = 32;
	private static $loaded = false;

	private static function functions() {
		return array(
			'sodium_crypto_secretstream_xchacha20poly1305_init_push',
			'sodium_crypto_secretstream_xchacha20poly1305_push',
			'sodium_crypto_secretstream_xchacha20poly1305_init_pull',
			'sodium_crypto_secretstream_xchacha20poly1305_pull',
			'sodium_crypto_generichash',
			'sodium_crypto_secretbox',
			'sodium_crypto_secretbox_open',
			'sodium_crypto_auth',
			'sodium_crypto_auth_verify',
		);
	}
	private static function load() {
		if ( self::$loaded ) return;
		self::$loaded = true;
		foreach ( self::functions() as $name ) {
			if ( function_exists( $name ) ) continue;
			$compat = defined( 'WPINC' ) ? ABSPATH . WPINC . '/sodium_compat/autoload.php' : '';
			if ( $compat && is_readable( $compat ) ) require_once $compat;
			return;
		}
	}
	/** Funciones que faltan; vacío si se puede firmar y cifrar. */
	public static function missing() {
		self::load();
		return array_values( array_filter( self::functions(), function( $name ) { return ! function_exists( $name ); } ) );
	}
	public static function available() { return ! self::missing(); }
	public static function native() { return extension_loaded( 'sodium' ); }
	/** Mejor esfuerzo: PHP puro no puede garantizar el borrado de la memoria. */
	public static function memzero( &$value ) {
		if ( ! is_string( $value ) ) { $value = null; return; }
		if ( self::native() && function_exists( 'sodium_memzero' ) ) {
			try { sodium_memzero( $value ); return; } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		}
		$value = null;
	}
}
