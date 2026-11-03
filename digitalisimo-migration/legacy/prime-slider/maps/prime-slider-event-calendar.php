<?php
/**
 * Prime Slider Pro 4.7.0 `prime-slider-event-calendar` (Event Calendar, familia L) → `loop-carousel` (respaldo `posts`).
 *
 * Eventos de The Events Calendar: con el plugin activo, el Loop Carousel de PRO sobre `tribe_events` con una plantilla «Loop Item» de origen `post` generada (imagen, título, extracto y el botón de entradas con su enlace fijo). Se pierden las fechas, la cuenta atrás, el lugar y las formas decorativas. Sin The Events Calendar, descarte declarado: el widget queda en un marcador con su original.
 *
 * Origen: P/modules/event-calendar/widgets/event-calendar.php (controles :72-640, consulta propia en :1752-1780) (copia de referencia, sólo lectura). Frente F de F8 (§3.E.4, V.10.7).
 */
defined( 'ABSPATH' ) || exit;

return array(
	'family'   => 'L',
	'plugin'   => 'pro',
	'decision' => 'APROXIMAR',
	'target'   => 'loop-carousel',
	'fallback' => 'posts',
	'reason'   => 'Eventos de The Events Calendar: con el plugin activo, el Loop Carousel de PRO sobre `tribe_events` con una plantilla «Loop Item» de origen `post` generada (imagen, título, extracto y el botón de entradas con su enlace fijo). Se pierden las fechas, la cuenta atrás, el lugar y las formas decorativas. Sin The Events Calendar, descarte declarado: el widget queda en un marcador con su original.',
	'dynamic'  => array(
		'kind'       => 'events',
		'engine'     => 'swiper',
		'effect'     => 'fade',
		'per_view'   => 1,
		'height'     => 'slider_item_height',
		'min_height' => 500,
		'limit'      => 6,
		'nav'        => array( 'select' => 'navigation' ),
		'parts'      => array(
			'title'       => array( 'show_title', 'title_html_tag', 'h1' ),
			'excerpt'     => array( 'show_excerpt', 'excerpt_length' ),
			'button'      => array( 'show_buy_button', '', array( '__t' => 'Buy Ticket Online', 'd' => 'bdthemes-prime-slider' ) ),
			'button_link' => 'button_link',
		),
		'colors'     => array( 'title' => array( 'title_color' ), 'text' => array( 'excerpt_color', 'text_color' ) ),
		'loss'       => array(),
		'defaults'   => array(
			'source'          => '',
			'limit'           => 6,
			'orderby'         => 'event_date',
			'order'           => 'DESC',
			'show_title'      => 'yes',
			'title_html_tag'  => 'h1',
			'show_excerpt'    => 'yes',
			'excerpt_length'  => 30,
			'show_buy_button' => 'yes',
			'button_link'     => array( 'url' => '#' ),
			'navigation'      => 'arrows',
			'autoplay'        => 'yes',
			'autoplay_speed'  => 5000,
			'pauseonhover'    => '',
			'loop'            => 'yes',
			'speed'           => array( 'size' => 500 ),
		),
	),
);
