<?php
/** Element Pack Pro 9.9.1 `bdt-trailer-box` → `digitalisimo-fancy-card`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-fancy-card',
	// Clases del render de Element Pack (trailer-box.php) → tarjeta propia. `{{WRAPPER}}` ya es el widget, así que
	// `.elementor-widget-bdt-trailer-box` desaparece; la caja interior (`-desc`, `-title-wrap`) no tiene equivalente.
	'classes'   => array(
		'.elementor-widget-bdt-trailer-box'                      => '',
		'.bdt-trailer-box-desc-inner .bdt-trailer-box-pre-title' => '.digi-fancy-card__badge',
		'.bdt-trailer-box-desc-inner .bdt-trailer-box-title'     => '.digi-fancy-card__title',
		'.bdt-trailer-box-pre-title'                             => '.digi-fancy-card__badge',
		'.bdt-trailer-box-title'                                 => '.digi-fancy-card__title',
		'.bdt-trailer-box-text'                                  => '.digi-fancy-card__description',
		'a.bdt-trailer-box-button'                               => 'a.digi-fancy-card__link',
		'.bdt-trailer-box-button'                                => '.digi-fancy-card__link',
		'.bdt-trailer-box'                                       => '.digi-fancy-card',
	),
	'defaults'  => array(
		'title' => 'Trailer Box Title',
		'content' => 'I am Trailer Box Description Text. You can change me anytime from settings.',
		'origin' => 'bottom-left',
		'align' => '',
		'height' => array(
			'size' => 400,
		),
		'link_type' => '',
		'button' => array(
			'url' => '#',
		),
		'title_tags' => 'h3',
		'button_text' => 'View Details',
		'icon_align' => 'right',
		'icon_indent' => array(
			'size' => 8,
		),
		'button_position' => '',
		'button_css_id' => '',
		'item_animation' => 'content',
		'pre_title_x_position' => array(
			'size' => 0,
		),
		'pre_title_x_position_tablet' => array(
			'size' => 0,
		),
		'pre_title_x_position_mobile' => array(
			'size' => 0,
		),
		'pre_title_y_position' => array(
			'size' => 0,
		),
		'pre_title_y_position_tablet' => array(
			'size' => 0,
		),
		'pre_title_y_position_mobile' => array(
			'size' => 0,
		),
		'pre_title_rotate' => array(
			'size' => 0,
		),
		'pre_title_rotate_tablet' => array(
			'size' => 0,
		),
		'pre_title_rotate_mobile' => array(
			'size' => 0,
		),
		'pre_title_hide' => 'm',
	),
	'filter'    => static function ( array $out ) {
		$set = array(
			'icon_type'        => 'none',
			'badge_text'       => trim( wp_strip_all_tags( (string) ( $out['pre_title'] ?? '' ) ) ),
			'title_text'       => trim( wp_strip_all_tags( (string) ( $out['title'] ?? '' ) ) ),
			'title_size'       => (string) ( $out['title_tags'] ?? 'h3' ),
			'description_text' => trim( wp_strip_all_tags( (string) ( $out['content'] ?? '' ) ) ),
			'button_text'      => 'button' === ( $out['link_type'] ?? '' ) ? trim( wp_strip_all_tags( (string) ( $out['button_text'] ?? '' ) ) ) : '',
			'link'             => is_array( $out['button'] ?? null ) ? $out['button'] : array( 'url' => '' ),
		);
		// `background_color` es el fondo del botón en el origen y el de la tarjeta en el destino (mismo nombre): el
		// adaptador y la conversión pintaban la tarjeta y dejaban el botón transparente (Playground P10 w2, frente J).
		// Pasa al enlace como CSS del elemento (un color o un color global) y deja de ser el fondo de la tarjeta.
		$bg     = isset( $out['background_color'] ) ? trim( (string) $out['background_color'] ) : '';
		$global = (string) ( $out['__globals__']['background_color'] ?? '' );
		unset( $out['background_color'], $out['__globals__']['background_color'] );
		if ( '' !== $global && preg_match( '/^globals\/colors\?id=([a-z0-9_-]+)$/i', $global, $m ) ) {
			$bg = 'var(--e-global-color-' . $m[1] . ')';
		}
		if ( '' !== $bg && preg_match( '/^(#[0-9a-f]{3,8}|rgba?\([0-9.,\s%]+\)|hsla?\([0-9.,\s%deg]+\)|var\(--e-global-color-[a-z0-9_-]+\))$/i', $bg ) ) {
			$set['custom_css'] = trim( (string) ( $out['custom_css'] ?? '' ) . "\nselector a.digi-fancy-card__link{background-color:" . $bg . '}' );
			unset( $out['custom_css'] );
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(pre_title|title|content|origin|align|link_type|button|title_tags|button_text|button_icon|icon_align|button_position|button_css_id|item_animation|pre_title_hide|title_advanced_style|button_hover_animation)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
