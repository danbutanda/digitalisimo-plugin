<?php
/** Element Pack Pro 9.9.1 `bdt-notification` → `digitalisimo-notification`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-notification',
	// Clases de Element Pack más usadas en sus selectores: .bdt-alert-close, .bdt-notify-wrapper-container, .bdt-notify-wrapper
	'classes'   => array(
		'#bdt-notify-{{ID}} .bdt-alert-close' => '{{WRAPPER}} .digi-notification__close',
		'#bdt-notify-{{ID}}'                   => '{{WRAPPER}} .digi-notification__panel',
		'.bdt-notify-wrapper .bdt-notify-wrapper-container' => '{{WRAPPER}} .digi-notification__content',
	),
	'defaults'  => array(
		'notification_type' => 'popup',
		'notification_event' => 'onload',
		'notification_timeout' => array(
			'size' => 5000,
		),
		'source' => 'custom',
		'notification_content' => 'Notification message here',
		'notification_position' => 'top-center',
		'notification_pos_fixed' => 'relative',
		'display_times_expire' => 12,
		'notification_popup_style' => 'primary',
	),
	'values'    => array(
		// El aviso propio sólo se coloca en las esquinas o en una barra superior o inferior.
		'notification_position'  => array( 'top-center' => 'top-right', 'bottom-center' => 'bottom-right' ),
		'notification_pos_fixed' => array( 'relative' => 'top', 'static' => 'top' ),
		'notification_timeout'   => static function ( $value ) {
			return is_array( $value ) ? (int) ( $value['size'] ?? 0 ) : (int) $value;
		},
		'notification_in_delay'  => static function ( $value ) {
			return is_array( $value ) ? (int) ( $value['size'] ?? 0 ) : (int) $value;
		},
	),
	'drop'      => array( 'source', 'template_id', 'curly_to_params', 'display_times', 'display_times_expire', 'link_with_confetti', 'ex_system_url_as_same', 'ex_system', 'ex_system_url', 'notification_popup_style' ),
);
