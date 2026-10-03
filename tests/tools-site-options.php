<?php
/**
 * DIGITALÍSIMO Tools · Ajustes del sitio: importación desde SEO sin perder la
 * ruta de acceso, herencia de red y validación de la ruta privada.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'OBJECT', 'OBJECT' );
	$GLOBALS['multisite'] = true;
	$GLOBALS['options']   = array();
	$GLOBALS['network']   = array();
	$GLOBALS['taken']     = array( 'contacto' );
	function is_multisite() { return $GLOBALS['multisite']; }
	function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
	function get_site_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['network'] ) ? $GLOBALS['network'][ $key ] : $default; }
	function update_site_option( $key, $value ) { $GLOBALS['network'][ $key ] = $value; return true; }
	function remove_accents( $v ) { return strtr( $v, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n' ) ); }
	function sanitize_title_with_dashes( $v ) { return trim( preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $v ) ), '-' ); }
	function get_page_by_path( $slug ) { return in_array( $slug, $GLOBALS['taken'], true ) ? (object) array() : null; }
	function get_term_by() { return false; }
	function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }
	function reset_site( $site, $inherit, $network ) {
		$GLOBALS['options'] = array( 'digitalisimo_integrations_options' => $site, 'digitalisimo_seo_network_inherit' => $inherit );
		$GLOBALS['network'] = array( 'digitalisimo_integrations_options' => $network );
	}
	require __DIR__ . '/../digitalisimo-tools/modules/site-options/class-hide-login.php';
	require __DIR__ . '/../digitalisimo-tools/modules/site-options/class-site-options.php';
	$O = 'Digitalisimo\\Tools\\Site_Options';
	$H = 'Digitalisimo\\Tools\\Hide_Login';

	// Red con ruta «01» y sitio que hereda (lo que hay hoy en producción).
	reset_site( array(), array(), array( 'seo_hide_login_slug' => '01', 'seo_redirect_attachments' => 1, 'seo_mobile_prevent_horizontal_scroll' => 1 ) );
	check( '01' === $O::get( 'login_slug' ) && 1 === $O::get( 'mobile_scroll' ), 'el sitio hereda la red importada' );
	check( '01' === $H::slug(), 'la ruta privada sigue activa tras la migración' );
	check( isset( $GLOBALS['network']['digitalisimo_tools_site_defaults'] ) && isset( $GLOBALS['options']['digitalisimo_tools_site_options'] ), 'la importación se guarda una sola vez' );

	// Sitio con ruta propia y herencia por defecto, pero la red sin esa clave: SEO usaba la del sitio.
	reset_site( array( 'seo_hide_login_slug' => 'acceso-sitio' ), array(), array() );
	check( 'acceso-sitio' === $O::get( 'login_slug' ), 'se conserva la ruta del sitio cuando la red no la tenía' );
	check( false === $O::inherits( 'login_slug' ) && true === $O::inherits( 'mobile_scroll' ), 'sólo esa clave queda personalizada' );

	// Sitio que había desmarcado «Heredar de la red».
	reset_site( array( 'seo_redirect_attachments' => 0 ), array( 'seo_redirect_attachments' => 0 ), array( 'seo_redirect_attachments' => 1 ) );
	check( 0 === $O::get( 'redirect_attachments' ), 'la personalización del sitio se respeta' );

	// Sin datos en SEO: valores predeterminados.
	reset_site( array(), array(), array() );
	check( '' === $O::get( 'login_slug' ) && 1 === $O::get( 'redirect_attachments' ) && 0 === $O::get( 'mobile_scroll' ), 'valores predeterminados' );

	// WordPress individual: no hay herencia.
	$GLOBALS['multisite'] = false;
	reset_site( array( 'seo_hide_login_slug' => 'entrar', 'seo_mobile_prevent_horizontal_scroll' => 1 ), array(), array() );
	check( 'entrar' === $O::get( 'login_slug' ) && 1 === $O::get( 'mobile_scroll' ) && false === $O::inherits( 'login_slug' ), 'sitio individual' );

	// Validación de la ruta privada.
	check( array( 'mi-acceso', '' ) === $H::sanitize_option( 'Mi Acceso', 'x' ), 'normaliza la ruta' );
	check( 'x' === $H::sanitize_option( 'wp-admin', 'x' )[0] && '' !== $H::sanitize_option( 'wp-admin', 'x' )[1], 'rechaza rutas reservadas' );
	check( 'x' === $H::sanitize_option( 'contacto', 'x' )[0], 'rechaza rutas ocupadas por contenido' );
	check( array( '', '' ) === $H::sanitize_option( '', 'x' ), 'vacío desactiva' );
	check( array( 1, '' ) === $O::sanitize( 'mobile_scroll', '1', 0 ) && array( 0, '' ) === $O::sanitize( 'mobile_scroll', '0', 1 ), 'casillas' );
	echo "Tools · ajustes del sitio: OK\n";
}
