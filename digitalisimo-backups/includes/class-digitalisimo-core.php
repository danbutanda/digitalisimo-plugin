<?php
defined( 'ABSPATH' ) || exit;
/** Núcleo mínimo para que Backups pueda instalarse sin los demás módulos. */
final class Digitalisimo_Backups_Core {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 25 );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}
	public static function menu() { if ( ! class_exists( 'Digitalisimo_Core' ) ) add_menu_page( 'Digitalisimo', 'Digitalisimo', 'manage_options', 'digitalisimo', array( __CLASS__, 'dashboard' ), 'dashicons-chart-line', 56 ); add_submenu_page( 'digitalisimo', 'Backups', 'Backups', 'manage_options', 'digitalisimo-backups', array( 'Digitalisimo_Backups', 'page' ) ); remove_submenu_page( 'digitalisimo', 'digitalisimo' ); }
	public static function network_menu() { if ( ! class_exists( 'Digitalisimo_Core' ) ) add_menu_page( 'Digitalisimo', 'Digitalisimo', 'manage_network_options', 'digitalisimo-network', array( __CLASS__, 'dashboard' ), 'dashicons-chart-line', 56 ); add_submenu_page( 'digitalisimo-network', 'Backups', 'Backups', 'manage_network_options', 'digitalisimo-network-backups', array( 'Digitalisimo_Backups', 'network_page' ) ); }
	public static function dashboard() { echo '<div class="wrap"><h1>Digitalisimo</h1><p>Activa el módulo Backups desde este menú para crear un respaldo completo.</p></div>'; }
	public static function assets() {
		$page = sanitize_key( $_GET['page'] ?? '' );
		if ( false === strpos( $page, 'digitalisimo' ) ) return;
		wp_enqueue_style( 'digitalisimo-backups-ui', plugins_url( '../assets/backups-admin.css', __FILE__ ), function_exists( 'digitalisimo_ui_dependency' ) ? digitalisimo_ui_dependency() : array(), DIGITALISIMO_BACKUPS_VERSION );
		if ( ! in_array( $page, array( 'digitalisimo-backups', 'digitalisimo-network-backups' ), true ) ) return;
		wp_enqueue_script( 'digitalisimo-backups-manual', plugins_url( '../assets/manual-progress.js', __FILE__ ), array(), DIGITALISIMO_BACKUPS_VERSION, true );
		// «Servidores»: campos de cada destino y «Probar conexión» por AJAX (sin JavaScript, el botón envía el formulario).
		wp_enqueue_script( 'digitalisimo-backups-destinations', plugins_url( '../assets/destinations.js', __FILE__ ), array(), DIGITALISIMO_BACKUPS_VERSION, true );
		wp_add_inline_script( 'digitalisimo-backups-destinations', 'window.digitalisimoBackupsDestinations=' . wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'action' => 'digitalisimo_backups_test_destination' ) ) . ';', 'before' );
		wp_add_inline_script( 'digitalisimo-backups-manual', 'window.digitalisimoBackupsManual=' . wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'digitalisimo_backups_manual' ), 'jobsNonce' => wp_create_nonce( 'digitalisimo_backups_jobs' ), 'context' => is_network_admin() ? 'network' : 'site', 'login' => wp_login_url( is_network_admin() ? network_admin_url( 'admin.php?page=digitalisimo-network-backups&tab=migrate' ) : admin_url( 'admin.php?page=digitalisimo-backups&tab=migrate' ) ) ) ) . ';', 'before' );
		// Subida de un respaldo por fragmentos (tarjeta «Subir un respaldo»).
		wp_enqueue_script( 'digitalisimo-backups-upload', plugins_url( '../assets/upload.js', __FILE__ ), array(), DIGITALISIMO_BACKUPS_VERSION, true );
		wp_add_inline_script( 'digitalisimo-backups-upload', 'window.digitalisimoBackupsUpload=' . wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'digitalisimo_backups_upload' ), 'context' => is_network_admin() ? 'network' : 'site' ) ) . ';', 'before' );
	}
}
