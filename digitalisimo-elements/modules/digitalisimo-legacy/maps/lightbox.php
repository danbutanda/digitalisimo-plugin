<?php
/** Element Pack Pro 9.9.1 `lightbox` → `digitalisimo-offcanvas`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'digitalisimo-offcanvas',
	'classes'   => array( '.bdt-toggle-button' => '.digi-offcanvas__button' ),
	'defaults'  => array(
		'lightbox_toggler' => 'poster',
		'poster_image' => array(
			'url' => $placeholder_url,
		),
		'poster_image_size_size' => 'large',
		'poster_height' => array(
			'size' => 400,
		),
		'button_text' => 'Open Lightbox',
		'lightbox_toggler_icon' => array(
			'value' => 'fas fa-play',
			'library' => 'fa-solid',
		),
		'align' => '',
		'icon_align' => 'left',
		'lightbox_content' => 'image',
		'content_image' => array(
			'url' => $placeholder_url,
		),
		'content_video' => array(
			'url' => '//test-videos.co.uk/vids/bigbuckbunny/mp4/av1/1080/Big_Buck_Bunny_1080_10s_1MB.mp4',
		),
		'content_youtube' => array(
			'url' => 'https://www.youtube.com/watch?v=YE7VzlLtp-4',
		),
		'content_vimeo' => array(
			'url' => 'https://vimeo.com/1084537',
		),
		'content_google_map' => array(
			'url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d4740.819266853735!2d9.99008871708242!3d53.550454675412404!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x3f9d24afe84a0263!2sRathaus!5e0!3m2!1sde!2sde!4v1499675200938',
		),
		'content_caption' => 'This is a image',
		'glassmorphism_blur_level' => array(
			'size' => 5,
		),
		'fancy_animation' => 'shadow-pulse',
		'lightbox_animation' => 'slide',
		'video_autoplay' => 'yes',
	),
	// La imagen, el vídeo o el mapa se muestran en una ventana centrada; el póster es el botón.
	'filter'    => static function ( array $out ) {
		$type   = (string) ( $out['lightbox_content'] ?? 'image' );
		$keys   = array( 'image' => 'content_image', 'video' => 'content_video', 'youtube' => 'content_youtube', 'vimeo' => 'content_vimeo', 'google-map' => 'content_google_map' );
		$source = (string) ( $out[ $keys[ $type ] ?? 'content_image' ]['url'] ?? '' );
		$caption = trim( (string) ( $out['content_caption'] ?? '' ) );
		$toggler = (string) ( $out['lightbox_toggler'] ?? 'poster' );
		$set     = array(
			'trigger'          => 'button',
			'trigger_image'    => 'poster' === $toggler && is_array( $out['poster_image'] ?? null ) ? $out['poster_image'] : array( 'url' => '' ),
			'button_text'      => 'icon' === $toggler ? (string) ( $out['icon_text'] ?? '' ) : ( 'poster' === $toggler ? '' : (string) ( $out['button_text'] ?? '' ) ),
			'button_icon'      => 'icon' === $toggler && is_array( $out['lightbox_toggler_icon'] ?? null ) ? $out['lightbox_toggler_icon'] : ( is_array( $out['button_icon'] ?? null ) ? $out['button_icon'] : array( 'value' => '', 'library' => '' ) ),
			'button_icon_align' => 'right' === ( $out['icon_align'] ?? 'left' ) ? 'right' : 'left',
			'panel_label'      => '' !== $caption ? wp_strip_all_tags( $caption ) : 'Contenido',
			'source'           => 'media',
			'media_type'       => in_array( $type, array( 'image', 'video' ), true ) ? $type : 'embed',
			'media_url'        => array( 'url' => $source ),
			'content_after'    => '' !== $caption ? '<p>' . esc_html( $caption ) . '</p>' : '',
			'side'             => 'center',
			'overlay'          => 'yes',
			'close_button'     => 'yes',
			'close_on_overlay' => 'yes',
			'close_on_escape'  => 'yes',
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(lightbox_.*|poster_image|icon_text|button_icon|icon_align|align|content_.*|glassmorphism_effect|hover_animation|show_fancy_animation|fancy_animation|video_autoplay)$/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $set + $out;
	},
);
