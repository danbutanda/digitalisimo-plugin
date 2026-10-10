<?php
/**
 * Plugin Name: Digitalisimo A/B fake revslider
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
digi_ab_fake_register( array( 'rev_slider' ) );
