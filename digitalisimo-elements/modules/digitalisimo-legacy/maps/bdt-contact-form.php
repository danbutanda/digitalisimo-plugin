<?php
/** Element Pack Pro 9.9.1 `bdt-contact-form` → `form`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'form',
	// Clases de Element Pack más usadas en sus selectores: .bdt-field-group, .bdt-input, .bdt-button, .bdt-contact-form, .bdt-grid-small, .bdt-contact-form-form, .bdt-form-label, .bdt-contact-form-additional-message, .bdt-contact-form-success-message-, .bdt-text-success, .bdt-all-field-inline--yes, .bdt-grid, .bdt-grid-margin
	'classes'   => array(),
	'defaults'  => array(
		'show_labels' => 'yes',
		'contact_number_required' => 'yes',
		'show_subject' => 'yes',
		'show_message' => 'yes',
		'input_size' => 'default',
		'text_align' => 'start',
		'button_text' => 'Send Message',
		'button_size' => '',
		'align' => '',
		'user_name_label' => 'Name*',
		'user_name_placeholder' => 'Your Name',
		'contact_label' => 'Contact Number',
		'contact_placeholder' => 'Your contact number',
		'subject_label' => 'Subject*',
		'subject_placeholder' => 'Your message subject',
		'email_address_label' => 'Email*',
		'email_placeholder' => 'example@email.com',
		'message_label' => 'Your Message*',
		'message_placeholder' => 'Your Message Here',
		'additional_message' => 'Note: You have to fill-up above all respective field, then click below button for send your message',
		'show_recaptcha' => 'yes',
		'message_rows' => '5',
		'success_message' => 'Hi [name], We got your e-mail. We will reply to [email] very soon. Thanks for being with us...',
		'row_gap' => array(
			'size' => '15',
		),
	),
	// Formulario de PRO Elements con los mismos campos (IDs name, email, contact, subject, message) y la acción
	// de correo hacia el mismo destinatario: el de los ajustes de Element Pack o el administrador del sitio.
	'filter'    => static function ( array $out ) {
		$custom = 'yes' === ( $out['custom_text'] ?? '' );
		$text   = static function ( $key, $fallback ) use ( $out, $custom ) {
			return $custom ? (string) ( $out[ $key ] ?? $fallback ) : $fallback;
		};
		$inline = 'yes' === ( $out['name_email_field_inline'] ?? '' ) || 'yes' === ( $out['all_field_inline'] ?? '' );
		$fields = array(
			array( 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => $text( 'user_name_label', 'Name*' ), 'placeholder' => $text( 'user_name_placeholder', 'Your Name' ), 'required' => 'true', 'width' => $inline ? '50' : '100' ),
			array( 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => $text( 'email_address_label', 'Email*' ), 'placeholder' => $text( 'email_placeholder', 'example@email.com' ), 'required' => 'true', 'width' => $inline ? '50' : '100' ),
		);
		if ( 'yes' === ( $out['contact_number'] ?? '' ) ) {
			$required = 'yes' === ( $out['contact_number_required'] ?? 'yes' );
			$fields[] = array( 'custom_id' => 'contact', 'field_type' => 'tel', 'field_label' => $text( 'contact_label', $required ? 'Contact Number*' : 'Contact Number' ), 'placeholder' => $text( 'contact_placeholder', 'Your contact number' ), 'required' => $required ? 'true' : '', 'width' => 'yes' === ( $out['all_field_inline'] ?? '' ) ? '50' : '100' );
		}
		if ( 'yes' === ( $out['show_subject'] ?? '' ) ) {
			$fields[] = array( 'custom_id' => 'subject', 'field_type' => 'text', 'field_label' => $text( 'subject_label', 'Subject*' ), 'placeholder' => $text( 'subject_placeholder', 'Your message subject' ), 'required' => 'true', 'width' => 'yes' === ( $out['all_field_inline'] ?? '' ) ? '50' : '100' );
		}
		if ( 'yes' === ( $out['show_message'] ?? '' ) ) {
			$fields[] = array( 'custom_id' => 'message', 'field_type' => 'textarea', 'field_label' => $text( 'message_label', 'Your Message*' ), 'placeholder' => $text( 'message_placeholder', 'Your Message Here' ), 'required' => 'true', 'rows' => (int) ( $out['message_rows'] ?? 5 ), 'width' => '100' );
		}
		if ( 'yes' === ( $out['show_additional_message'] ?? '' ) && '' !== trim( (string) ( $out['additional_message'] ?? '' ) ) ) {
			$fields[] = array( 'custom_id' => 'note', 'field_type' => 'html', 'field_html' => wp_kses_post( (string) $out['additional_message'] ), 'width' => '100' );
		}
		foreach ( $fields as $i => $field ) {
			$fields[ $i ]['_id'] = 'ep' . $field['custom_id'];
		}
		$settings = function_exists( 'get_option' ) ? (array) get_option( 'element_pack_api_settings', array() ) : array();
		$sizes    = array( '' => 'sm', 'small' => 'xs', 'large' => 'lg' );
		$align    = array( '' => 'start', 'left' => 'start', 'center' => 'center', 'right' => 'end', 'justify' => 'stretch' );
		$set      = array(
			'form_name'      => 'Contact Form',
			'form_fields'    => $fields,
			'show_labels'    => 'yes' === ( $out['show_labels'] ?? 'yes' ) ? 'yes' : '',
			'mark_required'  => '',
			'input_size'     => $sizes[ 'default' === ( $out['input_size'] ?? 'default' ) ? '' : (string) $out['input_size'] ] ?? 'sm',
			'button_text'    => (string) ( $out['button_text'] ?? 'Send Message' ),
			'button_size'    => $sizes[ (string) ( $out['button_size'] ?? '' ) ] ?? (string) $out['button_size'],
			'button_align'   => $align[ (string) ( $out['align'] ?? '' ) ] ?? 'start',
			'submit_actions' => array_merge( array( 'email' ), 'yes' === ( $out['redirect_after_submit'] ?? '' ) && ! empty( $out['redirect_url']['url'] ) ? array( 'redirect' ) : array() ),
			'email_subject'  => 'yes' === ( $out['show_subject'] ?? '' ) ? '[field id="subject"]' : 'New message',
			'email_reply_to' => 'email',
		);
		if ( ! empty( $settings['contact_form_email'] ) && ( ! function_exists( 'is_email' ) || is_email( $settings['contact_form_email'] ) ) ) {
			$set['email_to'] = (string) $settings['contact_form_email'];
		}
		if ( in_array( 'redirect', $set['submit_actions'], true ) ) {
			$set['redirect_to'] = (string) $out['redirect_url']['url'];
		}
		if ( 'yes' === ( $out['custom_success_message'] ?? '' ) ) {
			$set['custom_messages'] = 'yes';
			$set['success_message'] = trim( str_replace( '[name]', '', wp_strip_all_tags( (string) ( $out['success_message'] ?? '' ) ) ) );
		}
		unset( $out['success_message'] );
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(custom_text|user_name_.*|email_address_label|email_placeholder|contact_.*|subject_.*|message_.*|show_subject|show_message|show_additional_message|additional_message|show_recaptcha|hide_recaptcha_badge|redirect_after_submit|redirect_url|reset_after_submit|custom_success_message|name_email_field_inline|all_field_inline|two_columns|align|notification_z_index)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
