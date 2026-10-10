<?php
/** Element Pack Pro 9.9.1 `bdt-user-login` → `login`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'login',
	// Clases de Element Pack más usadas en sus selectores: .bdt-user-login, .bdt-field-group, .bdt-button, .bdt-input, .bdt-button-dropdown, .bdt-dropdown, .bdt-button-modal, .bdt-logout-button, .bdt-user-logged-out, .bdt-toggle-pass-wrapper, .bdt-user-login-button-avatar, .bdt-form-stacked, .bdt-checkbox, .bdt-form-label
	'classes'   => array(
		'.bdt-user-login .bdt-user-logged-out a.bdt-button' => '.elementor-login .elementor-button',
		'.bdt-user-login'                                   => '.elementor-login',
	),
	'defaults'  => array(
		'show_labels' => 'yes',
		'input_size' => 'default',
		'button_text' => 'Log In',
		'button_size' => '',
		'align' => '',
		'show_domain_link' => 'yes',
		'show_edit_profile' => 'yes',
		'show_lost_password' => 'yes',
		'show_register' => 'yes',
		'show_remember_me' => 'yes',
		'logout_text' => 'Logout',
		'toggle_password' => 'yes',
		'show_logged_in_content' => 'yes',
		'show_logged_in_message' => 'yes',
		'show_user_name' => 'yes',
		'avatar_icon' => array(
			'value' => 'fas fa-level-down-alt',
			'library' => 'fa-solid',
		),
		'show_separator' => 'yes',
		'user_label' => 'Username or Email',
		'user_placeholder' => 'Username or Email',
		'password_label' => 'Password',
		'password_placeholder' => 'Password',
		'custom_password_text' => 'Lost Password?',
		'custom_register_text' => 'Register',
		'custom_remember_text' => 'Remember Me',
		'logged_in_custom_message' => 'Hey,',
		'dropdown_width' => array(
			'size' => '400',
		),
		'dropdown_position' => 'bottom-right',
		'dropdown_mode' => 'hover',
		'modal_avatar_size' => array(
			'size' => 24,
		),
		'row_gap' => array(
			'size' => '15',
		),
		'dropdown_avatar_size' => array(
			'size' => 24,
		),
	),
	'repeaters' => array(
		'custom_navs' => array(
			'defaults'     => array(
				'custom_nav_title' => 'Title',
				'custom_nav_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'custom_nav_title' => 'Billing',
					'custom_nav_icon' => array(
						'value' => 'fas fa-dollar-sign',
						'library' => 'fa-solid',
					),
					'custom_nav_link' => array(
						'url' => '#',
					),
				), array(
					'custom_nav_title' => 'Settings',
					'custom_nav_icon' => array(
						'value' => 'fas fa-cog',
						'library' => 'fa-solid',
					),
					'custom_nav_link' => array(
						'url' => '#',
					),
				), array(
					'custom_nav_title' => 'Support',
					'custom_nav_icon' => array(
						'value' => 'far fa-life-ring',
						'library' => 'fa-regular',
					),
					'custom_nav_link' => array(
						'url' => '#',
					),
				) ),
		),
	),
	// El formulario se conserva en el widget de acceso; los menús desplegables y modales del usuario no tienen equivalente.
	'rename'    => array( 'redirect_after_logOut' => 'redirect_after_logout', 'redirect_logOut_url' => 'redirect_logout_url' ),
	'values'    => array( 'input_size' => array( 'default' => 'sm' ), 'button_size' => array( '' => 'sm' ) ),
	'filter'    => static function ( array $out ) {
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(show_domain_link|show_edit_profile|custom_lost_password|custom_register|logout_text|toggle_password|show_logged_in_content|show_user_name|show_avatar|avatar_icon|show_separator|custom_password_text|custom_remember_text|logged_in_custom_message|dropdown_|modal_|custom_navs)/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
