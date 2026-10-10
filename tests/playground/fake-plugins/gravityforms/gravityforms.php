<?php
/**
 * Plugin Name: Digitalisimo A/B fake gravityforms
 * Description: Sólo para el A/B en Playground; no se publica.
 */
defined( 'ABSPATH' ) || exit;
require_once WP_CONTENT_DIR . '/digi-tests/fake-plugins/common.php';
if ( ! function_exists( 'gravity_form' ) ) {
	// Element Pack llama a gravity_form(); imprime lo mismo que el shortcode equivalente.
	function gravity_form( $id, $title = true, $description = true, $inactive = false, $values = null, $ajax = false, $tabindex = 0, $echo = true ) {
		echo digi_ab_fake_shortcode( 'gravityform', array( 'id' => (string) (int) $id, 'title' => $title ? 'true' : 'false', 'description' => $description ? 'true' : 'false', 'ajax' => $ajax ? 'true' : 'false', 'tabindex' => (string) $tabindex ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
digi_ab_fake_register( array( 'gravityform' ) );
