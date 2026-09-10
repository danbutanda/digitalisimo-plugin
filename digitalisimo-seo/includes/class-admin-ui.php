<?php
defined( 'ABSPATH' ) || exit;

class Digitalisimo_Admin_UI {
	public static function init() { add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) ); add_action( 'network_admin_enqueue_scripts', array( __CLASS__, 'assets' ) ); add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) ); }
	public static function body_class( $classes ) { $page = sanitize_key( $_GET['page'] ?? '' ); return 0 === strpos( $page, 'digitalisimo' ) ? $classes . ' digitalisimo-admin' : $classes; }
	public static function assets() { $page = sanitize_key( $_GET['page'] ?? '' ); if ( 0 !== strpos( $page, 'digitalisimo' ) ) return; wp_enqueue_style( 'digitalisimo-admin-ui', DIGITALISIMO_INTEGRATIONS_URL . 'assets/admin-ui.css', array(), DIGITALISIMO_INTEGRATIONS_VERSION ); }
}
