<?php
/** Element Pack Pro 9.9.1 `bdt-webhook-form` → `form`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'form',
	// Clases de Element Pack más usadas en sus selectores: .bdt-field-group, .bdt-input, .bdt-ep-webhook-form-button, .bdt-select, .bdt-ep-webhook-form-result, .bdt-radio-wrap, .bdt-checkbox-wrap, .bdt-ep-webhook-form-form, .bdt-radio, .bdt-checkbox, .bdt-form-label, .bdt-name-email-inline, .bdt-all-field-inline--yes, .bdt-ep-webhook-form-field-wrap
	'classes'   => array(),
	'defaults'  => array(
		'connection_type' => 'webhook',
		'send_as_json' => '',
		'enable_entity_id' => '',
		'entity_field_name' => 'entity',
		'show_recaptcha' => 'yes',
		'show_labels' => 'yes',
		'input_size' => 'default',
		'text_align' => 'left',
		'button_text' => 'Submit',
		'button_size' => '',
		'align' => '',
		'row_gap' => array(
			'size' => '15',
		),
		'col_gap' => array(
			'size' => '12',
		),
	),
	'repeaters' => array(
		'secuirty_fields' => array(
			'defaults'     => array(
				'security_data_position' => 'body',
			),
			'default_rows' => array( array(
					'security_field_name' => 'X-Auth-Token',
					'security_field_value' => '1234567890',
					'security_data_position' => 'header',
				) ),
		),
		'form_fields' => array(
			'defaults'     => array(
				'field_type' => 'number',
				'placeholder' => '',
				'field_options' => '',
				'width' => '100',
				'rows' => 4,
				'field_value' => '',
				'required' => '',
			),
			'default_rows' => array( array(
					'field_type' => 'text',
					'field_label' => 'Full Name',
					'field_name' => 'full_name',
					'placeholder' => 'Enter your name',
					'width' => '100',
					'required' => 'true',
				), array(
					'field_type' => 'email',
					'field_label' => 'Your Email',
					'field_name' => 'email',
					'placeholder' => 'Enter your email address',
					'width' => '100',
					'required' => 'true',
				) ),
		),
	),
	// Formulario de PRO Elements con la acción webhook hacia la misma URL. Los campos conservan su nombre como
	// ID; los campos de seguridad «body» viajan como campos ocultos. Google Sheets y las cabeceras de seguridad
	// no tienen equivalente en la acción de PRO Elements.
	'filter'    => static function ( array $out ) {
		$types  = array( 'text', 'email', 'textarea', 'url', 'tel', 'radio', 'select', 'checkbox', 'number', 'date', 'time', 'hidden', 'password' );
		$fields = array();
		foreach ( is_array( $out['form_fields'] ?? null ) ? $out['form_fields'] : array() as $i => $row ) {
			$type     = in_array( (string) ( $row['field_type'] ?? 'text' ), $types, true ) ? (string) $row['field_type'] : 'text';
			$id       = sanitize_key( (string) ( $row['field_name'] ?? '' ) );
			$fields[] = array(
				'_id'           => (string) ( $row['_id'] ?? 'epw' . $i ),
				'custom_id'     => '' !== $id ? str_replace( '-', '_', $id ) : 'field_' . $i,
				'field_type'    => $type,
				'field_label'   => (string) ( $row['field_label'] ?? '' ),
				'placeholder'   => (string) ( $row['placeholder'] ?? '' ),
				'field_options' => (string) ( $row['field_options'] ?? '' ),
				'inline_list'   => 'yes' === ( $row['inline_list'] ?? '' ) ? 'elementor-subgroup-inline' : '',
				'width'         => (string) ( $row['width'] ?? '100' ),
				'rows'          => (int) ( $row['rows'] ?? 4 ),
				'field_value'   => (string) ( $row['field_value'] ?? '' ),
				'required'      => in_array( $row['required'] ?? '', array( 'true', 'yes' ), true ) ? 'true' : '',
			);
		}
		foreach ( is_array( $out['secuirty_fields'] ?? null ) ? $out['secuirty_fields'] : array() as $i => $row ) {
			if ( 'body' === ( $row['security_data_position'] ?? 'body' ) && '' !== (string) ( $row['security_field_name'] ?? '' ) ) {
				$fields[] = array( '_id' => 'eps' . $i, 'custom_id' => str_replace( '-', '_', sanitize_key( (string) $row['security_field_name'] ) ), 'field_type' => 'hidden', 'field_value' => (string) ( $row['security_field_value'] ?? '' ), 'width' => '100' );
			}
		}
		$sizes = array( '' => 'sm', 'small' => 'xs', 'large' => 'lg' );
		$align = array( '' => 'start', 'left' => 'start', 'center' => 'center', 'right' => 'end', 'justify' => 'stretch' );
		$set   = array(
			'form_name'              => 'Webhook Form',
			'form_fields'            => $fields,
			'show_labels'            => 'yes' === ( $out['show_labels'] ?? 'yes' ) ? 'yes' : '',
			'mark_required'          => 'yes',
			'input_size'             => $sizes[ 'default' === ( $out['input_size'] ?? 'default' ) ? '' : (string) $out['input_size'] ] ?? 'sm',
			'button_text'            => (string) ( $out['button_text'] ?? 'Submit' ),
			'button_size'            => $sizes[ (string) ( $out['button_size'] ?? '' ) ] ?? (string) $out['button_size'],
			'button_align'           => $align[ (string) ( $out['align'] ?? '' ) ] ?? 'start',
			'submit_actions'         => 'webhook' === ( $out['connection_type'] ?? 'webhook' ) ? array( 'webhook' ) : array(),
			'webhooks'               => trim( (string) ( $out['webhook_url'] ?? '' ) ),
			'webhooks_advanced_data' => 'yes' === ( $out['send_as_json'] ?? '' ) ? 'yes' : '',
		);
		if ( '' !== trim( (string) ( $out['success_text'] ?? '' ) ) ) {
			$set['custom_messages'] = 'yes';
			$set['success_message'] = wp_strip_all_tags( (string) $out['success_text'] );
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(connection_type|webhook_url|google_.*|send_as_json|enable_entity_id|entity_field_name|secuirty_fields|show_recaptcha|hide_recaptcha_badge|success_text|align)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
