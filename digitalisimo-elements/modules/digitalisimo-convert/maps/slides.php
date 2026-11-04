<?php
/**
 * Slides de PRO (`slides`) → Diapositivas (`digitalisimo-slides`, modules/digitalisimo-carousel/widgets/class-slides.php).
 *
 * Las Diapositivas repiten los controles del widget de PRO con el mismo nombre, los mismos valores por defecto y las
 * mismas condiciones, también los del repetidor (fondo, contenido y estilo por diapositiva), así que el mapa es todo
 * `keep` y `groups`. El campo «Plantilla en lugar del texto» (`digitalisimo_template_id`) es un rasgo `digitalisimo_*` y
 * pasa tal cual en cada fila. Las posiciones de flechas y paginación pasan a modificadores de la raíz del carrusel
 * (`digi-crs--arrows-out`, `digi-crs--pag-out`) con el mismo valor. Formato: cabecera de `class-translator.php`.
 *
 * Diferencias que no son de clave (no generan código): el ajuste «Automático» del fondo (`background_size = auto`) se
 * pinta con `object-fit: none` sobre la imagen servida por `srcset`, que puede no ser la original completa; el contenido
 * de una diapositiva única no se anima al cargar (en PRO, sí) y la flecha, los puntos
 * y el botón de pausa son los del motor `digi-crs` (`_carousel-classes.php`).
 */
defined( 'ABSPATH' ) || exit;

$digi_sl_t = static function ( $text ) {
	// El valor por defecto que PRO guardaba en el idioma del sitio (Elementor no guarda lo que coincide con él).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};

$digi_sl_classes = is_file( __DIR__ . '/_carousel-classes.php' ) ? (array) require __DIR__ . '/_carousel-classes.php' : array();

$digi_sl_row = static function ( $n, $color ) use ( $digi_sl_t ) {
	return array(
		'heading'          => $digi_sl_t( 'Slide ' . $n . ' Heading' ),
		'description'      => $digi_sl_t( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor' ),
		'button_text'      => $digi_sl_t( 'Click Here' ),
		'background_color' => $color,
		'background_image' => array( 'url' => '' ),
	);
};

return array(
	'source'    => 'slides',
	'target'    => 'digitalisimo-slides',
	'status'    => 'draft',
	'panel'     => 'hide',
	// Valores por defecto de PRO que el traductor necesita para saber si el sitio los cambió.
	'defaults'  => array(
		'slides_name'                      => $digi_sl_t( 'Slides' ),
		'slides_height'                    => array( 'unit' => 'px', 'size' => 400, 'sizes' => array() ),
		'slides_title_tag'                 => 'div',
		'slides_description_tag'           => 'div',
		'navigation'                       => 'both',
		'autoplay'                         => 'yes',
		'pause_on_hover'                   => 'yes',
		'pause_on_interaction'             => 'yes',
		'autoplay_speed'                   => 5000,
		'infinite'                         => 'yes',
		'transition'                       => 'slide',
		'transition_speed'                 => 500,
		'content_animation'                => 'fadeInUp',
		'content_max_width'                => array( 'unit' => '%', 'size' => 66, 'sizes' => array() ),
		'slides_horizontal_position'       => 'center',
		'slides_vertical_position'         => 'middle',
		'slides_text_align'                => 'center',
		'button_size'                      => 'sm',
		'button_hover_transition_duration' => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
		'arrows_position'                  => 'inside',
		'dots_position'                    => 'inside',
	),
	'keep'      => array(
		'slides_name',
		'slides_height',
		'slides_title_tag',
		'slides_description_tag',
		'navigation',
		'autoplay',
		'pause_on_hover',
		'pause_on_interaction',
		'autoplay_speed',
		'infinite',
		'transition',
		'transition_speed',
		'content_animation',
		'content_max_width',
		'slides_padding',
		'slides_horizontal_position',
		'slides_vertical_position',
		'slides_text_align',
		'heading_spacing',
		'heading_color',
		'description_spacing',
		'description_color',
		'button_size',
		'button_border_width',
		'button_border_radius',
		'button_text_color',
		'button_border_color',
		'button_hover_text_color',
		'button_hover_border_color',
		'button_hover_transition_duration',
		'arrows_position',
		'arrows_size',
		'arrows_color',
		'dots_position',
		'dots_gap',
		'dots_size',
		'dots_color_inactive',
		'dots_color',
	),
	// Grupos con el mismo prefijo: sombra del texto, tipografías y fondos del botón.
	'groups'    => array(
		'text_shadow'             => 'text_shadow',
		'heading_typography'      => 'heading_typography',
		'description_typography'  => 'description_typography',
		'button_typography'       => 'button_typography',
		'button_background'       => 'button_background',
		'button_hover_background' => 'button_hover_background',
	),
	'repeaters' => array(
		'slides' => array(
			'target'       => 'slides',
			'defaults'     => array(
				'background_color'         => '#bbbbbb',
				'background_image'         => array( 'url' => '', 'id' => '' ),
				'background_size'          => 'cover',
				'background_ken_burns'     => '',
				'zoom_direction'           => 'in',
				'background_overlay'       => '',
				'background_overlay_color' => 'rgba(0,0,0,0.5)',
				'heading'                  => $digi_sl_t( 'Slide Heading' ),
				'description'              => $digi_sl_t( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.' ),
				'button_text'              => $digi_sl_t( 'Click Here' ),
				'link_click'               => 'slide',
			),
			'default_rows' => array(
				$digi_sl_row( 1, '#833ca3' ),
				$digi_sl_row( 2, '#4054b2' ),
				$digi_sl_row( 3, '#1abc9c' ),
			),
			'keep'         => array(
				'background_color',
				'background_image',
				'background_size',
				'background_ken_burns',
				'zoom_direction',
				'background_overlay',
				'background_overlay_color',
				'background_overlay_blend_mode',
				'heading',
				'description',
				'button_text',
				'link',
				'link_click',
				'custom_style',
				'horizontal_position',
				'vertical_position',
				'text_align',
				'content_color',
			),
			'groups'       => array( 'repeater_text_shadow' => 'repeater_text_shadow' ),
		),
	),
	'approx'    => array(
		'infinite'          => 'El bucle rebobina al llegar al final o al principio, sin clonar diapositivas como hacía PRO.',
		'content_animation' => 'La animación del contenido es una transición CSS al terminar el cambio de diapositiva (la de «Zoom», sin su fase intermedia), y una diapositiva única no se anima al cargar.',
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading_style_arrows', 'heading_style_dots' ),
	// Clases de PRO en el CSS personalizado del elemento: las comunes de los carruseles más las de las diapositivas.
	'classes'   => array_merge(
		$digi_sl_classes,
		array(
			'.elementor-widget-slides'      => '.elementor-widget-digitalisimo-slides',
			'.elementor-swiper'             => '.digi-slides',
			'.elementor-slides-wrapper'     => '.digi-slides',
			'.elementor-main-swiper'        => '.digi-slides',
			'.elementor-slides'             => '.digi-crs__track',
			'.swiper-slide-bg'              => '.digi-slides__bg',
			'.swiper-slide-inner'           => '.digi-slides__inner',
			'.swiper-slide-contents'        => '.digi-slides__contents',
			'.elementor-slide-heading'      => '.digi-slides__heading',
			'.elementor-slide-description'  => '.digi-slides__description',
			'.elementor-slide-button'       => '.digi-slides__button',
			'.elementor-background-overlay' => '.digi-slides__overlay',
			'.elementor-ken-burns'          => '.digi-slides__bg--kb',
			'.elementor-ken-burns--in'      => '.digi-slides__bg--kb-in',
			'.elementor-ken-burns--out'     => '.digi-slides__bg--kb-out',
			'.elementor-ken-burns--active'  => '.digi-slides__bg--kb:is(.digi-crs__current>*)',
			'.elementor--h-position-left'   => '.digi-slides--h-left',
			'.elementor--h-position-center' => '.digi-slides--h-center',
			'.elementor--h-position-right'  => '.digi-slides--h-right',
			'.elementor--v-position-top'    => '.digi-slides--v-top',
			'.elementor--v-position-middle' => '.digi-slides--v-middle',
			'.elementor--v-position-bottom' => '.digi-slides--v-bottom',
		)
	),
);
