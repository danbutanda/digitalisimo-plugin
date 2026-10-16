<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Un solo generador de imágenes para los widgets propios (ver docs/ELEMENTS_FRONT_OBJECTIVE.md).
 *
 * Con adjunto, la imagen sale de wp_get_attachment_image(): srcset, ancho y alto, ALT de Medios y las decisiones de
 * carga de WordPress (loading, fetchpriority, decoding). Sin adjunto, el <img> por URL recibe las mismas decisiones
 * por wp_get_loading_optimization_attributes() y sus dimensiones si se conocen. Ningún widget fuerza `loading`: lo
 * decide WordPress según la posición real en la página, y el módulo de imágenes de DIGITALÍSIMO SEO puede afinarlo.
 * `sizes` se calcula por columnas y breakpoint cuando el widget las conoce; si no, WordPress pone el suyo.
 */
final class Image_Markup {
	/** Ancho de contenido de referencia para el `sizes` de escritorio. */
	const CONTENT_WIDTH = 1140;

	/**
	 * Texto alternativo: el de Medios, el nombre que da el widget, el ALT guardado en el control y, sólo si se pide
	 * ($title_fallback, logotipos), el título del adjunto; un título como «IMG_1234» no es un ALT para una foto.
	 */
	public static function alt( array $image, $fallback = '', $title_fallback = false ) {
		$id  = absint( $image['id'] ?? 0 );
		$alt = $id && function_exists( 'get_post_meta' ) ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
		if ( '' === $alt ) { $alt = trim( (string) $fallback ); }
		if ( '' === $alt && ! empty( $image['alt'] ) ) { $alt = trim( wp_strip_all_tags( (string) $image['alt'] ) ); }
		if ( '' === $alt && $title_fallback && $id && function_exists( 'get_the_title' ) ) { $alt = trim( (string) get_the_title( $id ) ); }
		return $alt;
	}

	/** Ancho y alto de la imagen en ese tamaño: del adjunto, o los guardados en el control; array( 0, 0 ) si no se conocen. */
	public static function dimensions( array $image, $size = 'full' ) {
		$id = absint( $image['id'] ?? 0 );
		if ( $id && function_exists( 'wp_get_attachment_image_src' ) ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( is_array( $src ) && ! empty( $src[1] ) && ! empty( $src[2] ) ) { return array( (int) $src[1], (int) $src[2] ); }
		}
		return array( absint( $image['width'] ?? 0 ), absint( $image['height'] ?? 0 ) );
	}

	/**
	 * `sizes` por columnas: array( escritorio, tableta, móvil ) de elementos por fila. El escritorio se acota al ancho de
	 * contenido; tableta y móvil van en vw. Un valor no numérico o cero deja a WordPress decidir.
	 */
	public static function sizes_for_columns( $desktop, $tablet = null, $mobile = null, $content_width = self::CONTENT_WIDTH ) {
		$desktop = max( 0, (int) $desktop );
		if ( ! $desktop ) { return ''; }
		$tablet = max( 0, (int) $tablet ) ?: $desktop;
		$mobile = max( 0, (int) $mobile ) ?: $tablet;
		$mobile_bp = 767;
		$tablet_bp = 1024;
		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ) {
			$points = \Elementor\Plugin::$instance->breakpoints->get_breakpoints_config();
			$mobile_bp = (int) ( $points['mobile']['value'] ?? $mobile_bp );
			$tablet_bp = (int) ( $points['tablet']['value'] ?? $tablet_bp );
		}
		return sprintf( '(max-width: %dpx) %dvw, (max-width: %dpx) %dvw, %dpx', $mobile_bp, (int) ceil( 100 / $mobile ), $tablet_bp, (int) ceil( 100 / $tablet ), (int) ceil( $content_width / $desktop ) );
	}

	/** Valores de un control responsivo de columnas: array( escritorio, tableta, móvil ). */
	public static function columns( array $settings, $key = 'columns', $default = 1 ) {
		return array( (int) ( $settings[ $key ] ?? $default ) ?: $default, (int) ( $settings[ $key . '_tablet' ] ?? 0 ), (int) ( $settings[ $key . '_mobile' ] ?? 0 ) );
	}

	/**
	 * Marcado <img> de un valor de control MEDIA o GALLERY.
	 *
	 * @param array $image  array( 'id' => int, 'url' => string, 'alt' => string, 'width' => int, 'height' => int ).
	 * @param array $args   size (tamaño de WordPress, 'medium' por defecto), class, alt (respaldo tras el de Medios),
	 *                      alt_exact (ALT literal, sin consultar Medios), title_fallback (título del adjunto como último
	 *                      recurso), decorative (ALT vacío y aria-hidden), sizes (texto) o columns (array para
	 *                      sizes_for_columns), attrs (atributos extra).
	 * @return string '' si no hay imagen.
	 */
	public static function img( array $image, array $args = array() ) {
		$id    = absint( $image['id'] ?? 0 );
		$url   = (string) ( $image['url'] ?? '' );
		$size  = (string) ( $args['size'] ?? 'medium' );
		$attrs = array();
		if ( ! empty( $args['class'] ) ) { $attrs['class'] = (string) $args['class']; }
		$attrs['alt']      = ! empty( $args['decorative'] ) ? '' : ( array_key_exists( 'alt_exact', $args ) ? trim( (string) $args['alt_exact'] ) : self::alt( $image, (string) ( $args['alt'] ?? '' ), ! empty( $args['title_fallback'] ) ) );
		$attrs['decoding'] = 'async';
		if ( ! empty( $args['decorative'] ) ) { $attrs['aria-hidden'] = 'true'; }
		$sizes = (string) ( $args['sizes'] ?? '' );
		if ( '' === $sizes && ! empty( $args['columns'] ) && is_array( $args['columns'] ) ) {
			$sizes = self::sizes_for_columns( ...array_values( $args['columns'] ) );
		}
		if ( '' !== $sizes ) { $attrs['sizes'] = $sizes; }
		foreach ( (array) ( $args['attrs'] ?? array() ) as $name => $value ) { $attrs[ $name ] = (string) $value; }
		if ( $id && function_exists( 'wp_get_attachment_image' ) ) {
			$html = (string) wp_get_attachment_image( $id, $size, false, $attrs );
			if ( '' !== $html ) { return $html; }
			if ( '' === $url && function_exists( 'wp_get_attachment_url' ) ) { $url = (string) wp_get_attachment_url( $id ); }
		}
		$src = esc_url( $url );
		if ( '' === $src ) { return ''; }
		// Sin srcset, un sizes no aporta nada.
		unset( $attrs['sizes'] );
		$attrs['src'] = $src;
		$width  = absint( $image['width'] ?? 0 );
		$height = absint( $image['height'] ?? 0 );
		if ( $width && $height ) { $attrs['width'] = (string) $width; $attrs['height'] = (string) $height; }
		if ( function_exists( 'wp_get_loading_optimization_attributes' ) ) {
			foreach ( wp_get_loading_optimization_attributes( 'img', $attrs, 'digitalisimo_elements' ) as $name => $value ) { $attrs[ $name ] = (string) $value; }
		}
		$out = '<img';
		foreach ( $attrs as $name => $value ) {
			$out .= ' ' . $name . '="' . ( 'src' === $name ? $value : esc_attr( $value ) ) . '"';
		}
		return $out . '>';
	}

	/** Enlace de preconexión al host de un iframe de terceros, impreso una vez por página justo antes del iframe. */
	public static function preconnect( $url ) {
		static $printed = array();
		$parse = function_exists( 'wp_parse_url' ) ? 'wp_parse_url' : 'parse_url';
		$host  = strtolower( (string) $parse( (string) $url, PHP_URL_HOST ) );
		$site  = function_exists( 'home_url' ) ? strtolower( (string) $parse( home_url( '/' ), PHP_URL_HOST ) ) : '';
		if ( '' === $host || $host === $site || isset( $printed[ $host ] ) ) { return ''; }
		$printed[ $host ] = true;
		return '<link rel="preconnect" href="' . esc_url( 'https://' . $host ) . '" crossorigin>';
	}
}
