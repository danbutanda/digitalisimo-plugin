<?php
/** Element Pack Pro 9.9.1 `bdt-user-register` → `digitalisimo-user-register`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-user-register',
	// Clases de Element Pack más usadas en sus selectores: .bdt-modal-dialog, .bdt-field-group, .bdt-input, .bdt-button, .bdt-button-modal, .bdt-modal-close-default, .bdt-button-dropdown, .bdt-checkbox, .bdt-toggle-pass-wrapper, .bdt-modal-header, .bdt-term-input-wrapper, .bdt-terms-label, .bdt-recaptcha-text, .bdt-modal-title
	'classes'   => array( '.bdt-user-register' => '.digi-user-register' ),
	'defaults'  => array(
		'show_labels' => 'yes',
		'input_size' => 'default',
		'button_text' => 'Register',
		'button_size' => '',
		'terms_link' => array(
			'url' => '#',
		),
		'modal_button_text' => 'Register',
		'modal_button_size' => 'sm',
		'modal_button_align' => '',
		'modal_button_icon_align' => 'right',
		'modal_button_icon_indent' => array(
			'size' => 8,
		),
		'show_logged_in_message' => 'yes',
		'password_strength' => 'yes',
		'force_strong_password' => 'yes',
		'first_name_label' => 'First Name',
		'first_name_placeholder' => 'John',
		'last_name_label' => 'Last Name',
		'last_name_placeholder' => 'Doe',
		'email_label' => 'Email',
		'email_placeholder' => 'example@email.com',
		'password_label' => 'Password',
		'password_placeholder' => 'Enter password',
		'confirm_password_label' => 'Confirm Password',
		'confirm_password_msg' => 'Passwords must be same',
		'confirm_password_placeholder' => 'Confirm your password',
		'terms_label' => 'I agree to the',
		'terms_link_text' => 'Terms and Conditions',
		'additional_message' => 'Note: Your password will be generated automatically and sent to your email address.',
		'toggle_password' => 'yes',
		'modal_custom_width' => 'default',
		'modal_close_button' => 'yes',
		'modal_header' => 'yes',
		'row_gap' => array(
			'size' => '15',
		),
	),
	// El formulario embebido de Element Pack pasa a un enlace al registro nativo del sitio.
	'filter'    => static function ( array $out ) {
		$out['description'] = '';
		$out['note']        = 'yes' === ( $out['show_additional_message'] ?? '' ) ? (string) ( $out['additional_message'] ?? '' ) : '';
		return $out;
	},
);
