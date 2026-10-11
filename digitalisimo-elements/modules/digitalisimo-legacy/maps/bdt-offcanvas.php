<?php
/** Element Pack Pro 9.9.1 `bdt-offcanvas` → `digitalisimo-offcanvas`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-offcanvas',
	'classes'   => array(
		// El ancho y el alto de Element Pack desplazaban el panel con left/right: el ancho pasa al control propio.
		'body:not(.bdt-offcanvas-flip) '                 => '.bdt-skip ',
		'.bdt-offcanvas .bdt-offcanvas-close'             => '.digi-offcanvas__close',
		'.bdt-offcanvas .bdt-offcanvas-bar'               => '.digi-offcanvas__bar',
		'.bdt-offcanvas > div'                            => '.digi-offcanvas__bar',
		'.bdt-offcanvas-overlay::before'                  => '.digi-offcanvas__overlay',
		'.bdt-offcanvas-button-icon'                      => '.digi-offcanvas__icon',
		'.bdt-offcanvas-button'                           => '.digi-offcanvas__button',
	),
	'defaults'  => array(
		'layout' => 'default',
		'offcanvas_custom_id' => '#bdt-custom-offcanvas',
		'source' => 'sidebar',
		'sidebars' => 0,
		'anywhere_id' => '0',
		'offcanvas_animations' => 'slide',
		'offcanvas_close_button' => 'yes',
		'offcanvas_bg_close' => 'yes',
		'offcanvas_esc_close' => 'yes',
		'custom_content_before' => 'This is your custom content for before of your offcanvas.',
		'custom_content_after' => 'This is your custom content for after of your offcanvas.',
		'button_text' => 'Offcanvas',
		'button_align' => 'left',
		'button_offset' => array(
			'size' => 0,
		),
		'button_offset_tablet' => array(
			'size' => 0,
		),
		'button_offset_mobile' => array(
			'size' => 0,
		),
		'button_vertical_offset' => array(
			'size' => 0,
		),
		'button_vertical_offset_tablet' => array(
			'size' => 0,
		),
		'button_vertical_offset_mobile' => array(
			'size' => 0,
		),
		'button_rotate' => array(
			'size' => 0,
		),
		'button_rotate_tablet' => array(
			'size' => 0,
		),
		'button_rotate_mobile' => array(
			'size' => 0,
		),
		'size' => 'sm',
		'offcanvas_button_icon' => array(
			'value' => 'fas fa-bars',
			'library' => 'fa-solid',
		),
		'button_icon_align' => 'left',
		'button_icon_indent' => array(
			'size' => 8,
		),
	),
	'rename'    => array( 'offcanvas_button_icon' => 'button_icon', 'size' => 'button_size', 'offcanvas_close_button_text' => 'close_text' ),
	'filter'    => static function ( array $out ) {
		$yes    = static function ( $key ) use ( $out ) {
			return 'yes' === ( $out[ $key ] ?? '' ) ? 'yes' : '';
		};
		$source = (string) ( $out['source'] ?? 'sidebar' );
		$set    = array(
			'trigger'          => 'custom' === ( $out['layout'] ?? 'default' ) ? 'selector' : 'button',
			'trigger_selector' => trim( (string) ( $out['offcanvas_custom_id'] ?? '' ) ),
			'source'           => 'sidebar' === $source ? 'sidebar' : 'template',
			// Una plantilla de AnyWhere Elementor no es de la biblioteca de Elementor y no se muestra.
			'template_id'      => 'elementor' === $source ? (string) ( $out['template_id'] ?? '' ) : '',
			'sidebar'          => '0' === (string) ( $out['sidebars'] ?? '' ) ? '' : (string) ( $out['sidebars'] ?? '' ),
			'content_before'   => 'yes' === $yes( 'custom_content_before_switcher' ) ? (string) ( $out['custom_content_before'] ?? '' ) : '',
			'content_after'    => 'yes' === $yes( 'custom_content_after_switcher' ) ? (string) ( $out['custom_content_after'] ?? '' ) : '',
			'side'             => 'right' === ( $out['offcanvas_flip'] ?? '' ) ? 'right' : 'left',
			'panel_animation'  => 'none' === ( $out['offcanvas_animations'] ?? 'slide' ) ? 'none' : 'slide',
			'overlay'          => $yes( 'offcanvas_overlay' ),
			'close_button'     => $yes( 'offcanvas_close_button' ),
			'close_on_overlay' => $yes( 'offcanvas_bg_close' ),
			'close_on_escape'  => $yes( 'offcanvas_esc_close' ),
			'button_icon_align' => 'right' === ( $out['button_icon_align'] ?? 'left' ) ? 'right' : 'left',
		);
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			if ( isset( $out[ 'offcanvas_width' . $device ] ) ) {
				$set[ 'panel_width' . $device ] = $out[ 'offcanvas_width' . $device ];
			}
			$align = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
			if ( isset( $align[ (string) ( $out[ 'button_align' . $device ] ?? '' ) ] ) ) {
				$set[ 'button_align' . $device ] = $align[ $out[ 'button_align' . $device ] ];
			} else {
				unset( $out[ 'button_align' . $device ] );
			}
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(layout|offcanvas_custom_id|template_id|sidebars|anywhere_id|custom_content_(before|after)(_switcher)?|offcanvas_(overlay|animations|flip|close_button|bg_close|esc_close)|offcanvas_(width|height)(_tablet|_mobile)?|button_offset_toggle|hover_animation)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
