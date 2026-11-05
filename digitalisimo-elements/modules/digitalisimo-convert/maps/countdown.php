<?php
/**
 * Countdown de PRO (`countdown`) → Cuenta regresiva (`digitalisimo-countdown`,
 * modules/digitalisimo-content/widgets/class-countdown.php).
 *
 * La Cuenta regresiva repite los controles del widget de PRO con el mismo nombre, los mismos valores por defecto y las
 * mismas condiciones, así que el mapa es todo `keep` y `groups`. La fecha límite sin cambiar no se guarda y en los dos
 * widgets vale «dentro de un mes» desde cada visita, así que no se completa aquí. La disposición pasa a la clase propia
 * del envoltorio (`digi-countdown--label-block`/`-inline`) con el mismo valor. El temporizador perpetuo usa las mismas
 * claves del navegador que PRO con el mismo `id` de elemento: el visitante conserva su plazo.
 *
 * Diferencias que no son de clave (no generan código): los dígitos iniciales los pinta el servidor, el mensaje oculto usa
 * `hidden`, las acciones de una cuenta ya vencida corren una sola vez y las etiquetas sin «Etiquetas personalizadas» son
 * las del widget propio, en español. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_cd_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

return array(
	'source'   => 'countdown',
	'target'   => 'digitalisimo-countdown',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de PRO que el traductor necesita para saber si el sitio los cambió.
	'defaults' => array(
		'countdown_type'            => 'due_date',
		'evergreen_counter_hours'   => 47,
		'evergreen_counter_minutes' => 59,
		'show_days'                 => 'yes',
		'show_hours'                => 'yes',
		'show_minutes'              => 'yes',
		'show_seconds'              => 'yes',
		'show_labels'               => 'yes',
		'label_days'                => $digi_cd_t( 'Days' ),
		'label_hours'               => $digi_cd_t( 'Hours' ),
		'label_minutes'             => $digi_cd_t( 'Minutes' ),
		'label_seconds'             => $digi_cd_t( 'Seconds' ),
		'label_display'             => 'block',
		'container_width'           => array( 'unit' => '%', 'size' => 100 ),
		'container_width_tablet'    => array( 'unit' => '%' ),
		'container_width_mobile'    => array( 'unit' => '%' ),
		'box_spacing'               => array( 'size' => 10 ),
		'text_color'                => '',
	),
	'keep'     => array(
		'countdown_type',
		'due_date',
		'evergreen_counter_hours',
		'evergreen_counter_minutes',
		'show_days',
		'show_hours',
		'show_minutes',
		'show_seconds',
		'show_labels',
		'custom_labels',
		'label_days',
		'label_hours',
		'label_minutes',
		'label_seconds',
		'expire_actions',
		'message_after_expire',
		'expire_redirect_url',
		'label_display',
		'container_width',
		'boxes_alignment',
		'box_spacing',
		'box_padding',
		'box_background_color',
		'box_border_radius',
		'digits_color',
		'label_color',
		'align',
		'text_color',
		'message_padding',
	),
	// Grupos con el mismo prefijo: borde y sombra de las cajas, tipografías y sombras, y el contorno de las etiquetas.
	'groups'   => array(
		'box_border'          => 'box_border',
		'box_shadow'          => 'box_shadow',
		'digits_typography'   => 'digits_typography',
		'digits_text_shadow'  => 'digits_text_shadow',
		'label_typography'    => 'label_typography',
		'label_text_shadow'   => 'label_text_shadow',
		'text_stroke'         => 'text_stroke',
		'typography'          => 'typography',
		'message_text_shadow' => 'message_text_shadow',
	),
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_container', 'heading_boxes', 'heading_digits', 'heading_label' ),
	'classes'  => array(
		'.elementor-widget-countdown'         => '.elementor-widget-digitalisimo-countdown',
		'.elementor-countdown-wrapper'        => '.digi-countdown',
		'.elementor-countdown-item'           => '.digi-countdown__item',
		'.elementor-countdown-digits'         => '.digi-countdown__digits',
		'.elementor-countdown-label'          => '.digi-countdown__label',
		'.elementor-countdown-days'           => '.digi-countdown__days',
		'.elementor-countdown-hours'          => '.digi-countdown__hours',
		'.elementor-countdown-minutes'        => '.digi-countdown__minutes',
		'.elementor-countdown-seconds'        => '.digi-countdown__seconds',
		'.elementor-countdown-expire--message' => '.digi-countdown__message',
		'.elementor-countdown--label-block'   => '.digi-countdown--label-block',
		'.elementor-countdown--label-inline'  => '.digi-countdown--label-inline',
	),
);
