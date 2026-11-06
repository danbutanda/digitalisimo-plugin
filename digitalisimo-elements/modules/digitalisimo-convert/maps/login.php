<?php
/**
 * Login de PRO → Inicio de sesión (`digitalisimo-login`, modules/digitalisimo-forms/widgets/class-login.php).
 *
 * El Inicio de sesión repite los controles del de PRO con el mismo nombre, los mismos valores y las mismas condiciones,
 * y su marcado conserva el vocabulario de formularios del núcleo (`elementor-form`, `elementor-field-group`,
 * `elementor-button`…), así que todo pasa con `keep` y el CSS personalizado sólo cambia la clase del envoltorio. Los
 * textos por defecto de PRO van en `defaults`, para que un widget que nunca los cambió conserve los suyos.
 * Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_login_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- los textos de PRO, con su dominio.
};

return array(
	'source'   => 'login',
	'target'   => 'digitalisimo-login',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Textos por defecto de PRO (Elementor no guarda lo que coincide con el default).
	'defaults' => array(
		'button_text'          => $digi_login_t( 'Log In' ),
		'user_label'           => $digi_login_t( 'Username or Email Address' ),
		'user_placeholder'     => $digi_login_t( 'Username or Email Address' ),
		'password_label'       => $digi_login_t( 'Password' ),
		'password_placeholder' => $digi_login_t( 'Password' ),
	),
	'keep'     => array(
		'show_labels',
		'input_size',
		'button_text',
		'button_size',
		'align',
		'redirect_after_login',
		'redirect_url',
		'redirect_after_logout',
		'redirect_logout_url',
		'show_lost_password',
		'show_register',
		'show_remember_me',
		'show_logged_in_message',
		'custom_labels',
		'user_label',
		'user_placeholder',
		'password_label',
		'password_placeholder',
		'row_gap',
		'links_color',
		'links_hover_color',
		'label_spacing',
		'label_color',
		'field_text_color',
		'field_background_color',
		'field_border_color',
		'field_border_width',
		'field_border_radius',
		'button_text_color',
		'button_border_radius',
		'button_text_padding',
		'button_hover_color',
		'button_hover_border_color',
		'button_hover_animation',
		'button_hover_transition_duration',
		'message_color',
	),
	// Grupos con el mismo prefijo: tipografías, fondos y borde del botón.
	'groups'   => array(
		'label_typography'        => 'label_typography',
		'field_typography'        => 'field_typography',
		'button_typography'       => 'button_typography',
		'button_background'       => 'button_background',
		'button_border'           => 'button_border',
		'button_background_hover' => 'button_background_hover',
		'message_typography'      => 'message_typography',
	),
	'ui'       => array(),
	'classes'  => array(
		'.elementor-widget-login' => '.elementor-widget-digitalisimo-login',
	),
);
