<?php
/**
 * Plugin Name: Digitalisimo A/B fake easy-digital-downloads
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
digi_ab_fake_register( array( 'download_history', 'edd_profile_editor', 'purchase_history', 'edd_login', 'edd_register' ) );
