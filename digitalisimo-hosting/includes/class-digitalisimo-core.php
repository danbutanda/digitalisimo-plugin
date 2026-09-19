<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'Digitalisimo_Core' ) ) {
	final class Digitalisimo_Core {
		private static $modules = array();
		public static function boot( $id, $label, $callback = null, $network_callback = null ) { self::$modules[ $id ] = array( 'label' => $label, 'callback' => $callback, 'network_callback' => $network_callback ); add_action( 'admin_menu', array( __CLASS__, 'menu' ), 1 ); add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) ); add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) ); if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 1 ); }
		public static function assets() { if ( 0 === strpos( sanitize_key( $_GET['page'] ?? '' ), 'digitalisimo' ) ) wp_enqueue_style( 'digitalisimo-shared-ui', plugins_url( '../assets/admin-ui.css', __FILE__ ), array(), '1.0.0' ); }
		public static function body_class( $classes ) { return 0 === strpos( sanitize_key( $_GET['page'] ?? '' ), 'digitalisimo' ) ? $classes . ' digitalisimo-admin' : $classes; }
		public static function menu() { add_menu_page( 'Digitalisimo', 'Digitalisimo', 'manage_options', 'digitalisimo', array( __CLASS__, 'dashboard' ), 'dashicons-chart-line', 56 ); foreach ( self::$modules as $id => $module ) if ( $module['callback'] ) add_submenu_page( 'digitalisimo', $module['label'], $module['label'], 'manage_options', 'digitalisimo-' . $id, $module['callback'] ); remove_submenu_page( 'digitalisimo', 'digitalisimo' ); }
		public static function network_menu() { add_menu_page( 'Digitalisimo', 'Digitalisimo', 'manage_network_options', 'digitalisimo-network', array( __CLASS__, 'network_dashboard' ), 'dashicons-chart-line', 56 ); foreach ( self::$modules as $id => $module ) if ( $module['network_callback'] ) add_submenu_page( 'digitalisimo-network', $module['label'], $module['label'], 'manage_network_options', 'digitalisimo-network-' . $id, $module['network_callback'] ); remove_submenu_page( 'digitalisimo-network', 'digitalisimo-network' ); }
		public static function dashboard() { echo '<div class="wrap"><h1>Digitalisimo</h1><p>Módulos activos</p><ul>'; foreach ( self::$modules as $module ) echo '<li>✓ ' . esc_html( $module['label'] ) . '</li>'; echo '</ul></div>'; }
		public static function network_dashboard() { echo '<div class="wrap"><h1>Digitalisimo · Red</h1><p>Configura valores globales y herencia por sitio.</p></div>'; }
	}
}
