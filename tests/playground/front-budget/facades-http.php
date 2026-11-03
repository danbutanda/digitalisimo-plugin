<?php
/**
 * Plugin Name: DIGITALÍSIMO · front-budget · HTTP de las fachadas
 * Description: FACADES=1 de tests/playground/front-budget.sh. Sirve, sin salir del Playground, las teselas de
 * tile.openstreetmap.org (cuadrícula de 256 px), las portadas de i.ytimg.com (1 280 × 720) y el oEmbed de Vimeo con
 * una miniatura de i.vimeocdn.com; anota cada petición en wp-content/probe/facades-http.log. Lo demás no cambia.
 */
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$png  = static function ( $w, $h, $grid ) {
			$image = imagecreatetruecolor( $w, $h );
			imagefill( $image, 0, 0, imagecolorallocate( $image, $grid ? 0xf2 : 0x2b, $grid ? 0xef : 0x4a, $grid ? 0xe9 : 0x6f ) );
			$line = imagecolorallocate( $image, 0xc8, 0xc4, 0xbc );
			for ( $i = 0; $grid && $i < $w; $i += 32 ) { imageline( $image, $i, 0, $i, $h, $line ); imageline( $image, 0, $i, $w, $i, $line ); }
			ob_start();
			imagepng( $image );
			return ob_get_clean();
		};
		$body = null;
		if ( 'tile.openstreetmap.org' === $host ) {
			$body = $png( 256, 256, true );
		} elseif ( 'i.ytimg.com' === $host && str_ends_with( $url, '/maxresdefault.jpg' ) ) {
			$body = $png( 1280, 720, false );
		} elseif ( 'vimeo.com' === $host && str_contains( $url, '/api/oembed.json' ) ) {
			$body = wp_json_encode( array( 'thumbnail_url' => 'https://i.vimeocdn.com/video/digi-fb_1280' ) );
		} elseif ( 'i.vimeocdn.com' === $host ) {
			$body = $png( 1280, 720, false );
		}
		if ( null === $body ) {
			return $pre;
		}
		file_put_contents( '/wordpress/wp-content/probe/facades-http.log', $url . ' ' . ( $args['user-agent'] ?? '' ) . "\n", FILE_APPEND );
		return array( 'headers' => array(), 'body' => $body, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	},
	10,
	3
);
