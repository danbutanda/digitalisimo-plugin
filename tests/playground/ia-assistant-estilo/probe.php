<?php
/** Sonda de las capturas (mu-plugin, sólo en Playground): `?digi_st=login&token=…` inicia la sesión del admin. */
defined( 'ABSPATH' ) || exit;
add_action(
	'wp_loaded',
	static function () {
		if ( ! isset( $_GET['digi_st'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = trim( (string) @file_get_contents( '/digi-st/token.txt' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( '' === $token || ! hash_equals( $token, (string) ( $_GET['token'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
			wp_send_json( array( 'error' => 'token' ), 403 );
		}
		$ready = json_decode( (string) @file_get_contents( '/digi-st/ready.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		wp_set_auth_cookie( (int) $ready['admin'], false );
		wp_send_json( array( 'ok' => true ) );
	},
	1
);
