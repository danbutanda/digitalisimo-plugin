<?php
/**
 * Plugin Name: Digitalisimo A/B fake charitable
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
digi_ab_fake_register( array( 'campaigns', 'charitable_donation_form', 'charitable_my_donations', 'charitable_donors', 'charitable_login', 'charitable_profile', 'charitable_registration', 'charitable_stat' ) );
