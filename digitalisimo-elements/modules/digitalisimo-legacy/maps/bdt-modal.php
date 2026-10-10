<?php
/** Element Pack Pro 9.9.1 `bdt-modal` → `digitalisimo-offcanvas`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-offcanvas',
	'classes'   => array(
		'.bdt-modal-{{ID}}.bdt-modal .bdt-modal-dialog button.bdt-close' => '{{WRAPPER}} .digi-offcanvas__close',
		'.bdt-modal-{{ID}}.bdt-modal .bdt-modal-dialog' => '{{WRAPPER}} .digi-offcanvas__bar',
		'.bdt-modal-{{ID}}.bdt-modal .bdt-modal-header' => '{{WRAPPER}} .digi-legacy-modal__header',
		'.bdt-modal-{{ID}}.bdt-modal .bdt-modal-title'  => '{{WRAPPER}} .digi-legacy-modal__header',
		'.bdt-modal-{{ID}}.bdt-modal .bdt-modal-footer' => '{{WRAPPER}} .digi-offcanvas__after',
		'.bdt-modal-{{ID}}.bdt-modal .bdt-modal-body'   => '{{WRAPPER}} .digi-offcanvas__bar',
		'.bdt-modal-{{ID}}.bdt-modal'                   => '{{WRAPPER}} .digi-offcanvas__overlay',
		'.bdt-modal-wrapper .bdt-modal-button'          => '.digi-offcanvas__button',
	),
	'defaults'  => array(
		'layout' => 'default',
		'modal_custom_id' => '#bdt-custom-modal',
		'splash_after' => array(
			'size' => 5,
		),
		'display_times' => 3,
		'display_times_expire' => 12,
		'scroll_direction' => 'down',
		'scroll_offset' => array(
			'size' => 40,
		),
		'within_second' => array(
			'size' => 30,
		),
		'show_modal_header' => 'yes',
		'show_modal_footer' => 'yes',
		'button_text' => 'Open Modal',
		'size' => 'sm',
		'button_icon_align' => 'right',
		'button_icon_indent' => array(
			'size' => 8,
		),
		'header' => 'This is your modal header title',
		'header_align' => 'left',
		'source' => 'custom',
		'content' => 'A wonderful serenity has taken possession of my entire soul, like these sweet mornings of spring which I enjoy with my whole heart.',
		'footer' => 'Modal footer goes here',
		'footer_align' => 'left',
		'close_button' => 'default',
		'close_btn_delay_time' => array(
			'size' => 2,
		),
	),
	'filter'    => static function ( array $out ) {
		$layout = (string) ( $out['layout'] ?? 'default' );
		$header = 'yes' === ( $out['show_modal_header'] ?? '' ) && '' !== trim( (string) ( $out['header'] ?? '' ) ) ? '<div class="digi-legacy-modal__header">' . (string) $out['header'] . '</div>' : '';
		$footer = 'yes' === ( $out['show_modal_footer'] ?? '' ) ? (string) ( $out['footer'] ?? '' ) : '';
		$custom = 'custom' === ( $out['source'] ?? 'custom' );
		$set    = array(
			// Sólo la presentación por defecto tenía botón; las demás se abren solas o desde otro elemento.
			'trigger'          => 'default' === $layout ? 'button' : 'selector',
			'trigger_selector' => trim( (string) ( $out['modal_custom_id'] ?? '' ) ),
			// La pantalla de bienvenida se abre sola tras X segundos y un número de veces por visitante.
			'auto_open'        => 'splash' === $layout ? (string) (int) ( $out['splash_after']['size'] ?? 5 ) : '',
			'auto_open_limit'  => (int) ( $out['display_times'] ?? 3 ),
			'source'           => $custom ? 'text' : 'template',
			'template_id'      => 'elementor' === ( $out['source'] ?? '' ) ? (string) ( $out['template_id'] ?? '' ) : '',
			'content_before'   => $header . ( $custom ? (string) ( $out['content'] ?? '' ) : '' ),
			'content_after'    => $footer,
			'side'             => 'center',
			'overlay'          => 'yes',
			'close_button'     => 'none' === ( $out['close_button'] ?? 'default' ) ? '' : 'yes',
			'close_on_overlay' => 'yes',
			'close_on_escape'  => 'yes',
			'button_icon'      => is_array( $out['modal_button_icon'] ?? null ) ? $out['modal_button_icon'] : array( 'value' => '', 'library' => '' ),
			'button_icon_align' => 'left' === ( $out['button_icon_align'] ?? 'right' ) ? 'left' : 'right',
			'button_size'      => (string) ( $out['size'] ?? 'sm' ),
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(layout|modal_custom_id|splash_after|display_times|display_times_expire|cache_on_admin|scroll_.*|after_inactivity|within_second|show_modal_(header|footer)|header|header_align|source|content|template_id|anywhere_id|modal_custom_section_id|content_align|footer|footer_align|content_overflow|close_button|close_btn_.*|modal_size|modal_center|hover_animation|close_button_hover_animation|modal_button_icon|button_icon_align|size)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
