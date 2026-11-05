<?php
/**
 * Form de PRO → Formulario (`digitalisimo-form`, modules/digitalisimo-forms/widgets/class-form.php; contrato
 * `docs/pro/c6.md`).
 *
 * El Formulario repite los controles del de PRO con el mismo nombre, valor por defecto y condición (también los de
 * cada campo del repetidor, los que añaden los tipos de campo y los de las acciones de envío, que registra cada acción
 * en los dos widgets con el mismo código), así que todo pasa con `keep`: el envío, las acciones, sus destinatarios, los
 * envíos guardados y las integraciones siguen igual. El mismo `id` del elemento conserva el `form_id` de los envíos
 * guardados. Los textos por defecto de PRO van en `defaults` (y las filas por defecto en `default_rows`), para que un
 * formulario que nunca los cambió conserve los suyos.
 *
 * Acciones de terceros: además de las del constructor (lista fija de abajo), cuando WordPress y el constructor ya
 * cargaron se suman los controles que el Formulario propio tiene en ese sitio (`$digi_form_live`): una acción
 * registrada por otro plugin añade los mismos controles a los dos widgets, así que también pasan con `keep`.
 * Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_form_t = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- los textos de PRO, con su dominio.
};

/** Controles del Formulario propio en este sitio (con las acciones de terceros), o [] fuera de WordPress. */
$digi_form_live = static function (): array {
	$class = 'Digitalisimo\\Elements\\Forms\\Widgets\\Form';
	if ( ! function_exists( 'did_action' ) || ! did_action( 'elementor/widgets/register' ) || ! class_exists( '\\Digitalisimo\\Elements\\Forms\\Module' ) ) {
		return array( array(), array() );
	}
	try {
		if ( '' === \Digitalisimo\Elements\Forms\Module::load( 'digitalisimo-form' ) ) {
			return array( array(), array() );
		}
		$controls = ( new $class() )->get_controls();
	} catch ( \Throwable $error ) {
		return array( array(), array() );
	}
	$top = array();
	$row = array();
	foreach ( (array) $controls as $name => $control ) {
		if ( ! is_array( $control ) || in_array( $control['type'] ?? '', array( 'section', 'heading', 'raw_html', 'notice', 'divider', 'tabs', 'tab' ), true ) || ! empty( $control['groupType'] ) ) {
			continue;
		}
		$top[] = (string) $name;
		if ( 'form_fields' === $name && ! empty( $control['fields'] ) && is_array( $control['fields'] ) ) {
			foreach ( $control['fields'] as $field => $spec ) {
				$row[] = (string) ( is_array( $spec ) && isset( $spec['name'] ) ? $spec['name'] : $field );
			}
		}
	}
	return array( $top, $row );
};
list( $digi_form_live_top, $digi_form_live_row ) = $digi_form_live();

$digi_form_keep = array(
	'form_name',
	'form_fields',
	'input_size',
	'show_labels',
	'mark_required',
	'label_position',
	'button_size',
	'button_width',
	'step_next_label',
	'step_previous_label',
	'button_text',
	'selected_button_icon',
	'button_icon_align',
	'button_icon_indent',
	'button_css_id',
	'submit_actions',
	'step_type',
	'step_icon_shape',
	'form_id',
	'form_validation',
	'custom_messages',
	'success_message',
	'error_message',
	'server_message',
	'invalid_message',
	'required_field_message',
	'column_gap',
	'row_gap',
	'label_spacing',
	'label_color',
	'mark_required_color',
	'html_spacing',
	'html_color',
	'field_text_color',
	'field_background_color',
	'field_border_color',
	'field_border_width',
	'field_border_radius',
	'button_align',
	'button_content_align',
	'button_background_color',
	'button_text_color',
	'button_border_color',
	'previous_button_background_color',
	'previous_button_text_color',
	'previous_button_border_color',
	'button_background_hover_color',
	'button_hover_color',
	'button_hover_border_color',
	'previous_button_background_color_hover',
	'previous_button_text_color_hover',
	'previous_button_border_color_hover',
	'hover_transition_duration',
	'button_hover_animation',
	'button_border_radius',
	'button_text_padding',
	'success_message_color',
	'error_message_color',
	'inline_message_color',
	'steps_gap',
	'steps_icon_size',
	'steps_padding',
	'step_inactive_primary_color',
	'step_inactive_secondary_color',
	'step_active_primary_color',
	'step_active_secondary_color',
	'step_completed_primary_color',
	'step_completed_secondary_color',
	'step_divider_width',
	'step_divider_gap',
	'step_progress_bar_color',
	'step_progress_bar_background_color',
	'step_progress_bar_height',
	'step_progress_bar_border_radius',
	'step_progress_bar_percentage_color',
	// Acciones de envío del constructor (correo, correo 2, redirección, webhook, listas, Slack, Discord, envíos
	// guardados, popup, Akismet): las registra cada acción en los dos widgets.
	'email_to',
	'email_subject',
	'email_content',
	'email_from',
	'email_from_name',
	'email_reply_to',
	'email_to_cc',
	'email_to_bcc',
	'form_metadata',
	'email_content_type',
	'email_to_2',
	'email_subject_2',
	'email_content_2',
	'email_from_2',
	'email_from_name_2',
	'email_reply_to_2',
	'email_to_cc_2',
	'email_to_bcc_2',
	'form_metadata_2',
	'email_content_type_2',
	'redirect_to',
	'webhooks',
	'webhooks_advanced_data',
	'mailchimp_api_key_source',
	'mailchimp_api_key',
	'mailchimp_list',
	'mailchimp_groups',
	'mailchimp_tags',
	'mailchimp_double_opt_in',
	'mailchimp_fields_map',
	'drip_api_token_source',
	'drip_custom_api_token',
	'drip_account',
	'drip_custom_fields',
	'tags',
	'drip_fields_map',
	'activecampaign_api_credentials_source',
	'activecampaign_api_key',
	'activecampaign_api_url',
	'activecampaign_list',
	'activecampaign_tags',
	'activecampaign_fields_map',
	'getresponse_api_key_source',
	'getresponse_custom_api_key',
	'getresponse_list',
	'getresponse_dayofcycle',
	'getresponse_fields_map',
	'convertkit_api_key_source',
	'convertkit_custom_api_key',
	'convertkit_form',
	'convertkit_tags',
	'convertkit_fields_map',
	'mailerlite_api_key_source',
	'mailerlite_custom_api_key',
	'mailerlite_group',
	'allow_resubscribe',
	'mailerlite_fields_map',
	'mailpoet_lists',
	'mailpoet_fields_map',
	'mailpoet3_lists',
	'mailpoet3_fields_map',
	'slack_webhook',
	'slack_channel',
	'slack_username',
	'slack_pretext',
	'slack_title',
	'slack_text',
	'slack_add_fields',
	'slack_add_ts',
	'slack_webhook_color',
	'discord_webhook',
	'discord_username',
	'discord_avatar_url',
	'discord_title',
	'discord_content',
	'discord_form_data',
	'discord_ts',
	'discord_webhook_color',
	'submissions_metadata',
	'popup_action',
	'popup_action_popup_id',
	'popup_action_do_not_show_again',
	'akismet_enabled',
	'akismet_author',
	'akismet_author_url',
	'akismet_author_email',
	'akismet_content',
);

$digi_form_row = array(
	'field_type',
	'field_label',
	'placeholder',
	'required',
	'field_options',
	'allow_multiple',
	'select_size',
	'inline_list',
	'field_html',
	'width',
	'rows',
	'recaptcha_size',
	'recaptcha_style',
	'recaptcha_badge',
	'css_classes',
	'field_value',
	'custom_id',
	'acceptance_text',
	'checked_by_default',
	'min_date',
	'max_date',
	'use_native_date',
	'field_min',
	'field_max',
	'previous_button',
	'next_button',
	'selected_icon',
	'use_native_time',
	'attachment_type',
	'file_sizes',
	'file_types',
	'allow_multiple_upload',
	'max_files',
);

return array(
	'source'    => 'form',
	'target'    => 'digitalisimo-form',
	'status'    => 'draft',
	'panel'     => 'hide',
	// Textos por defecto de PRO (Elementor no guarda lo que coincide con el default).
	'defaults'  => array(
		'form_name'               => $digi_form_t( 'New Form' ),
		'button_text'             => $digi_form_t( 'Send' ),
		'step_next_label'         => $digi_form_t( 'Next' ),
		'step_previous_label'     => $digi_form_t( 'Previous' ),
		'success_message'         => $digi_form_t( 'Your submission was successful.' ),
		'error_message'           => $digi_form_t( 'Your submission failed because of an error.' ),
		'server_message'          => $digi_form_t( 'Your submission failed because of a server error.' ),
		'invalid_message'         => $digi_form_t( 'Your submission failed because the form is invalid.' ),
		'required_field_message'  => $digi_form_t( 'This field is required.' ),
	),
	'keep'      => array_values( array_unique( array_merge( $digi_form_keep, array_diff( $digi_form_live_top, array( 'form_fields' ) ) ) ) ),
	'groups'    => array(
		'label_typography' => 'label_typography',
		'html_typography' => 'html_typography',
		'field_typography' => 'field_typography',
		'button_typography' => 'button_typography',
		'button_border' => 'button_border',
		'message_typography' => 'message_typography',
		'steps_typography' => 'steps_typography',
		'step_progress_bar_percentage__typography' => 'step_progress_bar_percentage__typography',
	),
	// Controles sin valor guardado (títulos, avisos, códigos cortos): sólo para la cobertura.
	'ui'        => array(
		'atomic_form_v3_promotion',
		'heading_steps_buttons',
		'heading_submit_button',
		'heading_label',
		'heading_html',
		'heading_next_submit_button',
		'heading_previous_button',
		'heading_next_submit_button_hover',
		'heading_previous_button_hover',
		'step_progress_bar_percentage_heading',
		'shortcode',
		'display_percentage',
		'site_mailer_promo',
		'site_mailer_promo_2',
		'submissions_action_message',
		'akismet_info',
		'drip_custom_field_heading',
		'mailchimp_api_key_msg',
		'drip_api_key_msg',
		'activecampaign_api_key_msg',
		'getresponse_api_key_msg',
		'convertkit_api_key_msg',
		'mailerlite_api_key_msg',
	),
	'repeaters' => array(
		'form_fields' => array(
			'target'       => 'form_fields',
			'default_rows' => array(
				array(
					'custom_id'   => 'name',
					'field_type'  => 'text',
					'field_label' => $digi_form_t( 'Name' ),
					'placeholder' => $digi_form_t( 'Name' ),
					'width'       => '100',
				),
				array(
					'custom_id'   => 'email',
					'field_type'  => 'email',
					'required'    => 'true',
					'field_label' => $digi_form_t( 'Email' ),
					'placeholder' => $digi_form_t( 'Email' ),
					'width'       => '100',
				),
				array(
					'custom_id'   => 'message',
					'field_type'  => 'textarea',
					'field_label' => $digi_form_t( 'Message' ),
					'placeholder' => $digi_form_t( 'Message' ),
					'width'       => '100',
				),
			),
			// Los de cada campo y los que añaden los tipos de campo (fecha, hora, número, aceptación, subida, paso). Las
			// condiciones de F7b (`digitalisimo_*`) pasan como comunes.
			'keep'         => array_values( array_unique( array_merge( $digi_form_row, $digi_form_live_row ) ) ),
		),
	),
	// Los indicadores de los pasos son propios y los pinta el servidor; el resto del marcado conserva las clases de
	// formulario del núcleo, así que el CSS personalizado del elemento sólo cambia en estas.
	'classes'   => array(
		'.elementor-widget-form'                                    => '.elementor-widget-digitalisimo-form',
		'.e-form__indicators__indicator__progress__meter'           => '.digi-steps__meter',
		'.e-form__indicators__indicator__progress'                  => '.digi-steps__bar',
		'.e-form__indicators__indicator__separator'                 => '.digi-step-sep',
		'.e-form__indicators__indicator__label'                     => '.digi-step__label',
		'.e-form__indicators__indicator__icon'                      => '.digi-step__icon',
		'.e-form__indicators__indicator__number'                    => '.digi-step__badge',
		'.e-form__indicators__indicator--state-active'              => '.digi-step--on',
		'.e-form__indicators__indicator--state-inactive'            => '.digi-step--off',
		'.e-form__indicators__indicator--state-completed'           => '.digi-step--done',
		'.e-form__indicators__indicator--shape-circle'              => '.digi-step__badge--circle',
		'.e-form__indicators__indicator--shape-square'              => '.digi-step__badge--square',
		'.e-form__indicators__indicator--shape-rounded'             => '.digi-step__badge--rounded',
		'.e-form__indicators__indicator--shape-none'                => '.digi-step__badge--none',
		'.e-form__indicators__indicator'                            => '.digi-step',
		'.e-form__indicators'                                       => '.digi-steps',
	),
);
