<?php
/**
 * Gallery de PRO → Galería avanzada (`digitalisimo-gallery`, modules/digitalisimo-content/widgets/class-gallery.php).
 *
 * La Galería avanzada repite los controles de la de PRO con el mismo nombre, los mismos valores, las mismas condiciones
 * y las mismas variables CSS en el envoltorio, así que todo pasa con `keep`; el marcado, las clases y los recursos son
 * propios (sin la librería de galería del núcleo ni jQuery). Las aproximaciones: la carga diferida nativa y las
 * animaciones del indicador de los filtros, que se pintan como un fundido. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_gallery_tr = static function ( $text ) {
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- textos de PRO tal cual.
};

return array(
	'source'    => 'gallery',
	'target'    => 'digitalisimo-gallery',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Valores por defecto de PRO de los textos que PRO traduce al pintarlos y de los controles que se aproximan (para
	// informar sólo lo que el sitio cambió).
	'defaults'  => array(
		'show_all_galleries_label' => $digi_gallery_tr( 'All' ),
		'lazyload'                 => 'yes',
		'animation_line'           => 'fade',
		'animation_framed'         => 'fade',
		'animation_background'     => 'fade',
		'animation_text'           => 'grow',
	),
	'keep'      => array(
		'gallery_type',
		'gallery',
		'order_by',
		'lazyload',
		'gallery_layout',
		'columns',
		'ideal_row_height',
		'gap',
		'link_to',
		'url',
		'open_lightbox',
		'aspect_ratio',
		'show_all_galleries',
		'show_all_galleries_label',
		'pointer',
		'animation_line',
		'animation_framed',
		'animation_background',
		'animation_text',
		'overlay_background',
		'overlay_title',
		'overlay_description',
		'image_border_color',
		'image_border_width',
		'image_border_radius',
		'image_border_color_hover',
		'image_border_radius_hover',
		'image_hover_animation',
		'image_animation_duration',
		'image_blend_mode',
		'background_overlay_hover_animation',
		'background_overlay_animation_duration',
		'content_alignment',
		'content_vertical_position',
		'content_padding',
		'title_color',
		'title_spacing',
		'description_color',
		'content_hover_animation',
		'content_animation_duration',
		'content_sequenced_animation',
		'align_filter_bar_items',
		'galleries_title_color_normal',
		'galleries_title_color_hover',
		'galleries_title_color_hover_pointer_bg',
		'galleries_pointer_color_hover',
		'galleries_title_color_active',
		'galleries_pointer_color_active',
		'pointer_width',
		'galleries_titles_space_between',
		'galleries_titles_gap',
	),
	// Grupos con el mismo prefijo: tamaño de imagen, filtros CSS, fondos de la superposición y tipografías. El grupo
	// `overlay_background` (fondo normal) comparte prefijo con el interruptor del mismo nombre y con el fondo al pasar el
	// cursor (`overlay_background_hover`): los tres conservan su nombre.
	'groups'    => array(
		'thumbnail_image'             => 'thumbnail_image',
		'image_css_filters'           => 'image_css_filters',
		'image_css_filters_hover'     => 'image_css_filters_hover',
		'overlay_background'          => 'overlay_background',
		'overlay_background_hover'    => 'overlay_background_hover',
		'title_typography'            => 'title_typography',
		'description_typography'      => 'description_typography',
		'galleries_titles_typography' => 'galleries_titles_typography',
	),
	'approx'    => array(
		'lazyload'             => 'La carga diferida es la nativa del navegador (`loading="lazy"`), sin la aparición con zoom de la librería de galería.',
		'animation_line'       => 'La animación del indicador de los filtros (subrayado, línea superior o doble), también «Ninguna», se pinta como un fundido.',
		'animation_framed'     => 'La animación del marco de los filtros, también «Ninguna», se pinta como un fundido.',
		'animation_background' => 'La animación del fondo de los filtros, también «Ninguna», se pinta como un fundido.',
		'animation_text'       => 'El indicador «Texto» de los filtros sólo cambia el color, sin transformar el texto.',
	),
	'repeaters' => array(
		'galleries' => array(
			'target'       => 'galleries',
			'defaults'     => array( 'gallery_title' => $digi_gallery_tr( 'New Gallery' ) ),
			'default_rows' => array( array( 'gallery_title' => $digi_gallery_tr( 'New Gallery' ) ) ),
			'keep'         => array( 'gallery_title', 'multiple_gallery' ),
		),
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading_title', 'heading_description' ),
	// PRO pintaba cada imagen como fondo de `.e-gallery-image`; la propia es el `<img>` con esa clase (`:is(img)` suma
	// la especificidad de un elemento para ganar, como el CSS de PRO, a la regla de la hoja con la misma clase).
	'img_classes' => array( '.digi-gallery__image' => ':is(img)' ),
	'classes'   => array(
		'.elementor-widget-gallery'                => '.elementor-widget-digitalisimo-gallery',
		'.elementor-gallery__container'            => '.digi-gallery__container',
		'.e-gallery-container'                     => '.digi-gallery__container',
		'.e-gallery-grid'                          => '.digi-gallery__container',
		'.e-gallery-justified'                     => '.digi-gallery__container',
		'.e-gallery-masonry'                       => '.digi-gallery__container',
		'.e-gallery-item'                          => '.digi-gallery__item',
		'.elementor-gallery-item'                  => '.digi-gallery__item',
		'.e-gallery-item--hidden'                  => '[hidden]',
		'.elementor-animated-content'              => '.digi-gallery__item',
		'.e-gallery-image'                         => '.digi-gallery__image',
		'.elementor-gallery-item__image'           => '.digi-gallery__image',
		'.e-gallery-image-loaded'                  => '.digi-gallery__image',
		'.elementor-gallery-item__overlay'         => '.digi-gallery__overlay',
		'.elementor-gallery-item__content'         => '.digi-gallery__content',
		'.elementor-gallery--sequenced-animation'  => '.digi-gallery__content--sequenced',
		'.elementor-gallery-item__title'           => '.digi-gallery__title',
		'.elementor-gallery-item__description'     => '.digi-gallery__description',
		'.elementor-gallery__titles-container'     => '.digi-gallery__filters',
		'.elementor-gallery-title'                 => '.digi-gallery__filter',
		'.elementor-item-active'                   => '.digi-gallery__filter--active',
		'.elementor-gallery--filter-align-left'    => '.digi-gallery--filter-align-left',
		'.elementor-gallery--filter-align-center'  => '.digi-gallery--filter-align-center',
		'.elementor-gallery--filter-align-right'   => '.digi-gallery--filter-align-right',
	),
);
