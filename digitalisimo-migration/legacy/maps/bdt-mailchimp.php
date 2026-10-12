<?php
/** Element Pack Pro 9.9.1 `bdt-mailchimp` → `form`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'form',
	// Clases de Element Pack más usadas en sus selectores: .bdt-newsletter-wrapper, .bdt-newsletter-btn, .bdt-newsletter-before-icon, .bdt-newsletter-btn-icon, .bdt-button, .bdt-button-primary, .bdt-input, .bdt-mailchimp, .bdt-newsletter-signup-wrapper, .bdt-newsletter-before-text, .bdt-newsletter-after-text, .bdt-flex-align-right, .bdt-flex-align-left, .bdt-flex-align-top
	'classes'   => array(),
	'defaults'  => array(
		'mailchimp_before_icon' => array(
			'value' => 'far fa-envelope-open',
			'library' => 'fa-regular',
		),
		'fname_field_placeholder' => 'Name ',
		'email_field_placeholder' => 'Email *',
		'align' => '',
		'flex_direction' => 'column',
		'space' => '',
		'button_text' => 'SIGNUP',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
	),
	// Formulario de PRO Elements con la acción Mailchimp: la clave de API y la audiencia que Element Pack
	// guardaba en sus ajustes pasan a la acción («clave personalizada»), y el correo y el nombre se asignan
	// a EMAIL y FNAME. El icono previo y el texto de éxito propio de Element Pack no se trasladan.
	'filter'    => static function ( array $out ) {
		$settings = function_exists( 'get_option' ) ? (array) get_option( 'element_pack_api_settings', array() ) : array();
		$row      = 'row' === ( $out['flex_direction'] ?? 'column' );
		$fname    = 'yes' === ( $out['show_fname'] ?? '' );
		$fields   = array();
		if ( '' !== trim( (string) ( $out['before_text'] ?? '' ) ) ) {
			$fields[] = array( '_id' => 'epbefore', 'custom_id' => 'before', 'field_type' => 'html', 'field_html' => wp_kses_post( (string) $out['before_text'] ), 'width' => '100' );
		}
		if ( $fname ) {
			$fields[] = array( '_id' => 'epfname', 'custom_id' => 'fname', 'field_type' => 'text', 'field_label' => 'Name', 'placeholder' => (string) ( $out['fname_field_placeholder'] ?? 'Name ' ), 'required' => '', 'width' => $row ? '50' : '100' );
		}
		$fields[] = array( '_id' => 'epemail', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Email', 'placeholder' => (string) ( $out['email_field_placeholder'] ?? 'Email *' ), 'required' => 'true', 'width' => $row ? '50' : '100' );
		if ( '' !== trim( (string) ( $out['after_text'] ?? '' ) ) ) {
			$fields[] = array( '_id' => 'epafter', 'custom_id' => 'after', 'field_type' => 'html', 'field_html' => wp_kses_post( (string) $out['after_text'] ), 'width' => '100' );
		}
		$map = array( array( '_id' => 'epmapemail', 'remote_id' => 'email', 'local_id' => 'email' ) );
		if ( $fname ) {
			$map[] = array( '_id' => 'epmapfname', 'remote_id' => 'FNAME', 'local_id' => 'fname' );
		}
		$align = array( '' => 'start', 'left' => 'start', 'center' => 'center', 'right' => 'end', 'justify' => 'stretch' );
		$set   = array(
			'form_name'               => 'Mailchimp',
			'form_fields'             => $fields,
			'show_labels'             => '',
			'mark_required'           => '',
			'input_size'              => 'sm',
			'button_text'             => (string) ( $out['button_text'] ?? 'SIGNUP' ),
			'button_size'             => 'sm',
			'button_width'            => $row && ! $fname ? '50' : '100',
			'button_align'            => $align[ (string) ( $out['button_alignment'] ?? $out['align'] ?? '' ) ] ?? 'start',
			'selected_button_icon'    => is_array( $out['mailchimp_button_icon'] ?? null ) ? $out['mailchimp_button_icon'] : array( 'value' => '', 'library' => '' ),
			'button_icon_align'       => 'left' === ( $out['icon_align'] ?? 'right' ) ? 'left' : 'right',
			'submit_actions'          => array( 'mailchimp' ),
			'mailchimp_api_key_source' => 'custom',
			'mailchimp_api_key'       => (string) ( $settings['mailchimp_api_key'] ?? '' ),
			'mailchimp_list'          => (string) ( $settings['mailchimp_list_id'] ?? '' ),
			'mailchimp_double_opt_in' => '',
			'mailchimp_fields_map'    => $map,
		);
		if ( $row && ! $fname ) {
			$set['form_fields'][ count( $fields ) - ( '' !== trim( (string) ( $out['after_text'] ?? '' ) ) ? 2 : 1 ) ]['width'] = '50';
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(show_fname|fname_field_placeholder|email_field_placeholder|before_text|after_text|flex_direction|fullwidth_input|fullwidth_button|button_alignment|mailchimp_button_icon|icon_align|show_before_icon|before_icon_inline|mailchimp_before_icon|space|input_border_show|align)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
