<?php
/**
 * Plugin Name: Digitalisimo A/B fake LayerSlider
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
if ( ! class_exists( 'LS_Sliders' ) ) {
	class LS_Sliders { public static function find( $args = array() ) { return array(); } }
}
digi_ab_fake_register( array( 'layerslider' ) );
