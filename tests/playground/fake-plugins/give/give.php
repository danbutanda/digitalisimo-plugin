<?php
/**
 * Plugin Name: Digitalisimo A/B fake give
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
digi_ab_fake_register( array( 'donation_history', 'give_donor_wall', 'give_form', 'give_form_grid', 'give_goal', 'give_login', 'give_profile_editor', 'give_receipt', 'give_register', 'give_totals' ) );
