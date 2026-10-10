<?php
/**
 * Plugin Name: Digitalisimo A/B fake wpforms-lite
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
if ( ! function_exists( 'wpforms' ) ) {
	function wpforms() { return (object) array( 'form' => new class() { public function get() { return array(); } } ); }
}
digi_ab_fake_register( array( 'wpforms' ) );
