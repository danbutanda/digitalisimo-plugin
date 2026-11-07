<?php
/**
 * Reviews de PRO → Reseñas (`digitalisimo-reviews`, modules/digitalisimo-carousel/widgets/class-reviews.php).
 *
 * El widget propio repite los controles del de PRO y los de su base (`modules/carousel/widgets/base.php`) con el mismo
 * nombre, los mismos valores y las mismas condiciones, y quita los mismos que PRO (efecto, altura y posición de la
 * paginación), así que el mapa es `keep` y `groups` (también el icono FA4 heredado de cada fila, `social_icon`). El
 * marcado y las clases son propios (motor `digi-crs`, sin Swiper ni la fuente de iconos de las estrellas): la opción
 * `star_fontawesome` de PRO pinta ahora la estrella propia en SVG, con las mismas medidas y colores.
 *
 * Aproximaciones: ninguna desde la corrección de fidelidad (bucle real, indicador de la carga diferida).
 * Diferencias sin clave propia: el icono social es decorativo (PRO le ponía `aria-label="Read More"`), la valoración
 * tiene nombre accesible y una valoración con más de un decimal se recorta a la décima (PRO pintaba la estrella
 * entera). Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_rv_t = static function ( $text ) {
	// Los textos por defecto que PRO pintaba en el idioma del sitio (Elementor no guarda lo que coincide con ellos).
	return function_exists( 'esc_html__' ) ? esc_html__( $text, 'elementor-pro' ) : $text; // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
};
$digi_rv_placeholder = class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'get_placeholder_image_src' ) ? (string) \Elementor\Utils::get_placeholder_image_src() : '';
$digi_rv_lorem       = $digi_rv_t( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.' );
$digi_rv_row         = array(
	'content' => $digi_rv_lorem,
	'name'    => $digi_rv_t( 'John Doe' ),
	'title'   => '@username',
	'image'   => array( 'url' => $digi_rv_placeholder ),
);

$digi_rv_classes = array(
	'.elementor-widget-reviews'                => '.elementor-widget-digitalisimo-reviews',
	'.elementor-main-swiper'                   => '.digi-reviews',
	'.elementor-testimonial'                   => '.digi-review',
	'.elementor-testimonial__header'           => '.digi-review__header',
	'.elementor-testimonial__image'            => '.digi-review__image',
	'.elementor-testimonial__cite'             => '.digi-review__cite',
	'.elementor-testimonial__name'             => '.digi-review__name',
	'.elementor-testimonial__title'            => '.digi-review__title',
	'.elementor-testimonial__icon'             => '.digi-review__icon',
	'.elementor-testimonial__content'          => '.digi-review__content',
	'.elementor-testimonial__text'             => '.digi-review__text',
	'.elementor-star-rating'                   => '.digi-review__stars',
	'.elementor-star-full'                     => '.digi-review__star--full',
	'.elementor-star-empty'                    => '.digi-review__star--empty',
	'.elementor-review--has-separator'         => '.digi-review--has-separator',
	'.elementor--star-style-star_fontawesome'  => '.digi-review--star-style-star_fontawesome',
	'.elementor--star-style-star_unicode'      => '.digi-review--star-style-star_unicode',
);
for ( $digi_rv_i = 1; $digi_rv_i <= 9; $digi_rv_i++ ) {
	$digi_rv_classes[ '.elementor-star-' . $digi_rv_i ] = '.digi-review__star--' . $digi_rv_i;
}
foreach ( array( 'android', 'apple', 'behance', 'bitbucket', 'codepen', 'delicious', 'digg', 'dribbble', 'envelope', 'facebook', 'flickr', 'foursquare', 'github', 'google-plus', 'houzz', 'instagram', 'jsfiddle', 'linkedin', 'medium', 'meetup', 'mixcloud', 'odnoklassniki', 'pinterest', 'product-hunt', 'reddit', 'rss', 'shopping-cart', 'skype', 'slideshare', 'snapchat', 'soundcloud', 'spotify', 'stack-overflow', 'steam', 'stumbleupon', 'telegram', 'thumb-tack', 'thumbtack', 'threads', 'tripadvisor', 'tumblr', 'twitch', 'twitter', 'vimeo', 'vk', 'weibo', 'weixin', 'whatsapp', 'wordpress', 'x', 'x-twitter', 'xing', 'yelp', 'youtube', '500px' ) as $digi_rv_social ) {
	$digi_rv_classes[ '.elementor-icon-' . $digi_rv_social ] = '.digi-review__icon--' . $digi_rv_social;
}
// Clases comunes de los carruseles de PRO (Swiper, flechas y paginación) → motor propio (frente E).
$digi_rv_common = is_file( __DIR__ . '/_carousel-classes.php' ) ? (array) require __DIR__ . '/_carousel-classes.php' : array();

return array(
	'source'    => 'reviews',
	'target'    => 'digitalisimo-reviews',
	'status'    => 'ready',
	'panel'     => 'hide',
	// Valores por defecto de PRO: los textos que PRO pinta traducidos y los de los controles aproximados.
	'defaults'  => array(
		'slides_name' => $digi_rv_t( 'Slides' ),
		'loop'        => 'yes',
		'lazyload'    => '',
	),
	'keep'      => array(
		// Base de los carruseles de PRO (modules/carousel/widgets/base.php).
		'slides_name',
		'slides_per_view',
		'slides_to_scroll',
		'width',
		'show_arrows',
		'pagination',
		'speed',
		'autoplay',
		'autoplay_speed',
		'loop',
		'pause_on_hover',
		'pause_on_interaction',
		'lazyload',
		'space_between',
		'slide_background_color',
		'slide_border_size',
		'slide_border_radius',
		'slide_border_color',
		'slide_padding',
		'arrows_size',
		'arrows_color',
		'pagination_gap',
		'pagination_size',
		'pagination_color_inactive',
		'pagination_color',
		// Reseñas.
		'header_background_color',
		'content_gap',
		'show_separator',
		'separator_color',
		'separator_size',
		'name_color',
		'title_color',
		'content_color',
		'image_size',
		'image_gap',
		'image_border_radius',
		'icon_color',
		'icon_custom_color',
		'icon_size',
		'star_style',
		'unmarked_star_style',
		'star_size',
		'star_space',
		'stars_color',
		'stars_unmarked_color',
	),
	// Grupos con el mismo prefijo: tamaño de la imagen (base) y tipografías.
	'groups'    => array(
		'image_size'         => 'image_size',
		'name_typography'    => 'name_typography',
		'title_typography'   => 'title_typography',
		'content_typography' => 'content_typography',
	),
	'drop'      => array(
		'effect'              => 'PRO quita el efecto de este widget (siempre desliza): un valor guardado no tenía efecto.',
		'height'              => 'PRO quita la altura de este widget: un valor guardado no tenía efecto.',
		'pagination_position' => 'PRO quita la posición de la paginación de este widget (va siempre debajo): un valor guardado no tenía efecto.',
	),
	'repeaters' => array(
		'slides' => array(
			'target'       => 'slides',
			'defaults'     => array(
				'image'                => array( 'url' => $digi_rv_placeholder ),
				'name'                 => $digi_rv_t( 'John Doe' ),
				'title'                => '@username',
				'selected_social_icon' => array( 'value' => 'fab fa-twitter', 'library' => 'fa-brands' ),
				'content'              => $digi_rv_lorem,
			),
			'default_rows' => array( $digi_rv_row, $digi_rv_row, $digi_rv_row ),
			// `social_icon`: el icono FA4 que guardaban las versiones antiguas (fa4compatibility), que el widget propio lee igual.
			'keep'         => array( 'image', 'name', 'title', 'rating', 'selected_social_icon', 'social_icon', 'link', 'content' ),
		),
	),
	// Encabezados del panel: no guardan valor.
	'ui'        => array( 'heading_arrows', 'heading_pagination', 'heading_header', 'name_title_style', 'heading_title_style', 'heading_review_style' ),
	'classes'   => array_merge( $digi_rv_common, $digi_rv_classes ),
);
