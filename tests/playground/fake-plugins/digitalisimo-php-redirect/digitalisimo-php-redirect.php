<?php
/**
 * Plugin Name: Digitalisimo · portada que redirige (prueba)
 * Description: Sólo para tests/playground/tools-php.sh: con la bandera `redirect` de su sonda, la portada redirige una vez a /?digi_landing=1, como un plugin de «próximamente» o de idioma (el único reintento de la prueba previa); con la bandera `private`, la portada exige iniciar sesión, como Force Login (una portada privada pasa con aviso).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'template_redirect', static function () {
	$flags = (array) get_option( 'digi_php_probe_flags', array() );
	if ( empty( $flags['private'] ) || is_user_logged_in() ) return;
	auth_redirect();
}, 1 );

add_action( 'template_redirect', static function () {
	$flags = (array) get_option( 'digi_php_probe_flags', array() );
	if ( empty( $flags['redirect'] ) || ! is_front_page() || isset( $_GET['digi_landing'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification
	wp_safe_redirect( add_query_arg( 'digi_landing', '1', home_url( '/' ) ) );
	exit;
} );
