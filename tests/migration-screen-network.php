<?php
/**
 * «Migrar a DIGITALÍSIMO» con Migración instalada sola (includes/class-screen.php) en las tres superficies (paridad
 * sitio/red, 2026-11-22):
 *   1. WordPress individual: el botón «Migrar» y el análisis;
 *   2. administración de la red: el botón «Migrar toda la red» y el análisis de la red;
 *   3. un sitio de la red sin DIGITALÍSIMO Elements: no hay botón y la pantalla dice por qué y qué hacer (antes sólo
 *      quedaba la frase «Aquí, Migrar convierte el contenido de este sitio…», que prometía un botón que no aparece); quien
 *      administra la red tiene el enlace a su pantalla y un administrador del sitio no.
 * WordPress de f8-wp.php.
 */
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration();
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/includes/class-full.php';
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/includes/class-screen.php';
	use Digitalisimo\Migration\Screen;

	if ( ! function_exists( 'get_template' ) ) {
		function get_template() {
			return (string) get_option( 'template', '' );
		}
	}
	if ( ! function_exists( 'get_stylesheet' ) ) {
		function get_stylesheet() {
			return (string) get_option( 'stylesheet', '' );
		}
	}

	$checks = 0;
	function msn_check( $ok, $what ) {
		global $checks;
		if ( ! $ok ) {
			echo 'FALLA: ' . $what . "\n";
			exit( 1 );
		}
		++$checks;
	}
	function msn_render( $network ) {
		ob_start();
		$network ? Screen::network_page() : Screen::page();
		return (string) ob_get_clean();
	}
	$network_link = 'href="' . network_admin_url( 'settings.php?page=' . Screen::PAGE ) . '"';
	$waiting      = 'DIGITALÍSIMO Elements no está activo en este sitio';

	// 1. WordPress individual.
	f8_reset( array( 'multisite' => false ) );
	$html = msn_render( false );
	msn_check( false !== strpos( $html, 'digi-migrate__button' ) && false !== strpos( $html, '>Migrar</button>' ), 'Un WordPress individual no tiene el botón «Migrar».' );
	msn_check( false !== strpos( $html, 'Lo que hará Migrar' ), 'Un WordPress individual no tiene el análisis.' );
	msn_check( false === strpos( $html, $waiting ), 'Un WordPress individual dice que espera a la red.' );

	// 2. Administración de la red.
	f8_reset( array( 'multisite' => true, 'sites' => 2, 'network_admin' => true ) );
	$html = msn_render( true );
	msn_check( false !== strpos( $html, '>Migrar toda la red</button>' ) && false !== strpos( $html, 'data-network="1"' ), 'La red no tiene el botón «Migrar toda la red».' );
	msn_check( false !== strpos( $html, 'Lo que hará Migrar' ), 'La red no tiene el análisis.' );

	// 3. Un sitio de la red sin DIGITALÍSIMO Elements: superadministrador y administrador del sitio.
	f8_reset( array( 'multisite' => true, 'sites' => 2 ) );
	f8_user( 2, array( 'caps' => array( 'administrator' => true ) ) );
	$GLOBALS['wp']['blog'] = 2;
	$html = msn_render( false );
	msn_check( false === strpos( $html, 'digi-migrate__button' ), 'Un sitio de la red sin Elements pinta un botón que no puede hacer nada.' );
	msn_check( false !== strpos( $html, $waiting ), 'Un sitio de la red sin Elements no dice por qué no hay botón: ' . strip_tags( $html ) );
	msn_check( false !== strpos( $html, $network_link ), 'Un superadministrador no tiene el enlace a «Migrar a DIGITALÍSIMO» de la red.' );
	$GLOBALS['wp']['current_user'] = 2;
	msn_check( current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_network_plugins' ), 'El usuario 2 debería administrar el sitio y no la red.' );
	$html = msn_render( false );
	msn_check( false !== strpos( $html, $waiting ) && false === strpos( $html, $network_link ), 'Un administrador del sitio ve el motivo sin el enlace a la red (que no puede abrir).' );

	echo "Migración, pantalla en sitio, red y subsitio: $checks comprobaciones correctas.\n";
}
