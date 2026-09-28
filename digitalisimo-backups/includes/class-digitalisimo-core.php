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
	public static function assets() { $page = sanitize_key( $_GET['page'] ?? '' ); if ( false !== strpos( $page, 'digitalisimo' ) ) wp_enqueue_style( 'digitalisimo-backups-ui', plugins_url( '../assets/admin-ui.css', __FILE__ ), array(), DIGITALISIMO_BACKUPS_VERSION ); }
}
