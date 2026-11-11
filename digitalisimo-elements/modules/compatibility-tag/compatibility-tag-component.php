<?php
namespace ElementorPro\Modules\CompatibilityTag;

use Elementor\Core\Utils\Version;
use Elementor\Modules\CompatibilityTag\Base_Module as Compatibility_Tag_Base_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Compatibility_Tag_Component extends Compatibility_Tag_Base_Module {
	/**
	 * This is the header used by extensions to show testing.
	 *
	 * @var string
	 */
	const PLUGIN_VERSION_TESTED_HEADER = 'DIGITALÍSIMO Elements tested up to';

	/**
	 * @return string
	 */
	protected function get_plugin_header() {
		return self::PLUGIN_VERSION_TESTED_HEADER;
	}

	/**
	 * @return string
	 */
	protected function get_plugin_label() {
		return esc_html__( 'DIGITALÍSIMO Elements', 'elementor-pro' );
	}

	/**
	 * @return string
	 */
	protected function get_plugin_name() {
		return ELEMENTOR_PRO_PLUGIN_BASE;
	}

	/**
	 * @return string
	 */
	protected function get_plugin_version() {
		return self::three_parts( ELEMENTOR_PRO_VERSION );
	}

	/**
	 * Aviso de compatibilidad en la fila de actualización de DIGITALÍSIMO Elements (pantalla Plugins del sitio y de la
	 * red). DIGITALÍSIMO Elements versiona con cuatro partes (4.3.0.184) y `Version::create_from_string()` del núcleo de
	 * Elementor sólo acepta tres: lanzaba una excepción sin capturar y la pantalla Plugins daba error 500 mientras había
	 * una actualización pendiente (H24). Se compara con las tres primeras partes, como hace Elementor, y el aviso nunca
	 * puede romper la pantalla: ante cualquier error no se pinta nada (ni a medias) y la fila sigue igual.
	 *
	 * @param array $args
	 */
	protected function on_plugin_update_message( array $args ) {
		$level = ob_get_level();
		ob_start();
		try {
			$args['new_version'] = self::three_parts( $args['new_version'] ?? '' );
			$args['Version']     = self::three_parts( $args['Version'] ?? '' );
			if ( Version::is_valid_version( $args['new_version'] ) ) {
				parent::on_plugin_update_message( $args );
			}
			$html = (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			$html = '';
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- vista del núcleo, ya escapada.
	}

	/**
	 * Las tres primeras partes de una versión de cuatro (4.3.0.184 → 4.3.0); cualquier otra, tal cual.
	 *
	 * @param mixed $version
	 * @return string
	 */
	public static function three_parts( $version ) {
		$version = is_scalar( $version ) ? (string) $version : '';
		return preg_match( '/^(\d+\.\d+\.\d+)\.\d+$/D', $version, $parts ) ? $parts[1] : $version;
	}
}
