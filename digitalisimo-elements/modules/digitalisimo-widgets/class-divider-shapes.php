<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Separadores de forma propios de cada sitio.
 *
 * Los SVG guardados en `uploads/digitalisimo-elements/shapes/` del sitio se ofrecen en el separador de
 * forma de secciones y contenedores como `digi-<nombre>`; `<nombre>-negative.svg`, si existe, es su
 * versión negativa. Viven en los archivos del sitio, no en la carpeta del plugin: así sobreviven a las
 * actualizaciones y cada sitio de una red tiene los suyos.
 */
final class Divider_Shapes {
	const DIR    = 'digitalisimo-elements/shapes';
	const PREFIX = 'digi-';

	public static function init() {
		add_filter( 'elementor/shapes/additional_shapes', array( __CLASS__, 'register' ) );
	}

	/** Carpeta y URL de los separadores del sitio actual, o null si no hay carpeta de archivos. */
	public static function location() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		return array(
			'dir' => trailingslashit( $uploads['basedir'] ) . self::DIR . '/',
			'url' => trailingslashit( $uploads['baseurl'] ) . self::DIR . '/',
		);
	}

	public static function register( $shapes ) {
		$place = self::location();
		if ( ! $place || ! is_dir( $place['dir'] ) ) {
			return $shapes;
		}
		foreach ( glob( $place['dir'] . '*.svg' ) ?: array() as $file ) {
			$name = basename( $file, '.svg' );
			if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name ) || '-negative' === substr( $name, -9 ) ) {
				continue;
			}
			$shapes[ self::PREFIX . $name ] = array(
				'title'        => ucfirst( str_replace( '-', ' ', $name ) ),
				'path'         => $file,
				'url'          => $place['url'] . $name . '.svg',
				'has_flip'     => true,
				'has_negative' => is_file( $place['dir'] . $name . '-negative.svg' ),
			);
		}
		return (array) $shapes;
	}
}
