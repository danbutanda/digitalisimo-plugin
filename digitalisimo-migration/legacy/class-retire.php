<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Plugins que DIGITALÍSIMO Elements sustituye y la migración desactiva: el constructor (Elementor), sus funciones
 * avanzadas (Elementor Pro o PRO Elements) y Element Pack (Pro o Lite). Se reconocen por el archivo principal y la
 * cabecera «Plugin Name» que declaran, estén en la carpeta que estén, y nunca se borran ni se tocan sus archivos:
 * sólo se desactivan, como desde Plugins, y el administrador los elimina cuando ha comprobado el sitio. Element Pack
 * debe seguir instalado mientras dura la conversión (lee sus ajustes y copia sus figuras). Elementor sólo se
 * desactiva si el núcleo incluido en DIGITALÍSIMO Elements puede sustituirlo en la siguiente petición.
 *
 * Un plugin más que sustituya DIGITALÍSIMO Elements se añade a PLUGINS (o con el filtro
 * `digitalisimo_migration_retired_plugins`), en el orden en que debe desactivarse.
 */
final class Retire {
	const PLUGINS = array(
		'bdthemes-element-pack.php'      => array( 'name' => '/^Element Pack/i', 'label' => 'Element Pack Pro' ),
		'bdthemes-element-pack-lite.php' => array( 'name' => '/^Element Pack/i', 'label' => 'Element Pack Lite' ),
		'elementor-pro.php'              => array( 'name' => '/^Elementor Pro/i', 'label' => 'Elementor Pro', 'conflict' => true ),
		'pro-elements.php'               => array( 'name' => '/^PRO Elements/i', 'label' => 'PRO Elements', 'conflict' => true ),
		'elementor.php'                  => array( 'name' => '/^Elementor$/i', 'label' => 'Elementor', 'core' => true ),
	);

	/**
	 * Plugins instalados que la migración retira, en el orden en que se desactivan. `conflict`: mientras está activo,
	 * DIGITALÍSIMO Elements no carga sus funciones ni sus widgets (Elementor Pro, PRO Elements). `element_pack`: la
	 * conversión aún lee sus ajustes y copia sus figuras.
	 *
	 * @return array<string, array{label:string, core:bool, conflict:bool, element_pack:bool, version:string}> archivo => datos.
	 */
	public static function installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$own   = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
		$rules = (array) apply_filters( 'digitalisimo_migration_retired_plugins', self::PLUGINS );
		$all   = (array) get_plugins();
		$out   = array();
		foreach ( $rules as $basename => $rule ) {
			foreach ( $all as $file => $data ) {
				if ( $file === $own || basename( (string) $file ) !== $basename || ! preg_match( (string) $rule['name'], (string) ( $data['Name'] ?? '' ) ) ) {
					continue;
				}
				$out[ (string) $file ] = array( 'label' => (string) $rule['label'], 'core' => ! empty( $rule['core'] ), 'conflict' => ! empty( $rule['conflict'] ), 'element_pack' => 0 === strpos( $basename, 'bdthemes-element-pack' ), 'version' => (string) ( $data['Version'] ?? '' ) );
			}
		}
		return $out;
	}

	/** ¿Puede el núcleo incluido en DIGITALÍSIMO Elements sustituir al plugin Elementor? */
	public static function core_ready() {
		return class_exists( '\\Digitalisimo_Elements_Core' ) && is_readable( \Digitalisimo_Elements_Core::dir() . 'elementor.php' );
	}

	/** Versión del núcleo incluido, o cadena vacía. */
	public static function core_version() {
		return class_exists( '\\Digitalisimo_Elements_Core' ) ? (string) \Digitalisimo_Elements_Core::version() : '';
	}

	/**
	 * Estado de cada plugin retirable instalado: si está activo en este sitio o en toda la red.
	 *
	 * @return array<string, array{label:string, core:bool, conflict:bool, element_pack:bool, version:string, site:bool, network:bool}>
	 */
	public static function status() {
		$site    = (array) get_option( 'active_plugins', array() );
		$network = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		$out     = array();
		foreach ( self::installed() as $file => $info ) {
			$out[ $file ] = $info + array( 'site' => in_array( $file, $site, true ), 'network' => in_array( $file, $network, true ) );
		}
		return $out;
	}

	/**
	 * Desactiva los plugins retirables activos en este sitio o, con $network, los activados en toda la red (sólo ese
	 * ámbito: la desactivación de red no toca las activaciones por sitio, y la de sitio no toca la red). Elementor sólo
	 * se desactiva si el núcleo incluido puede sustituirlo.
	 *
	 * @return array{deactivated: array<string,string>, warnings: string[]} archivo => etiqueta desactivada, y avisos.
	 */
	public static function deactivate( $network = false ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$done     = array();
		$warnings = array();
		foreach ( self::status() as $file => $info ) {
			if ( ! ( $network ? $info['network'] : $info['site'] ) ) {
				continue;
			}
			if ( $info['core'] ) {
				if ( ! self::core_ready() ) {
					$warnings[] = 'Elementor sigue activo: el núcleo incluido en DIGITALÍSIMO Elements no está disponible en esta instalación, así que no se desactivó.';
					continue;
				}
				$bundled = self::core_version();
				if ( '' !== $bundled && '' !== $info['version'] && version_compare( $info['version'], $bundled, '>' ) ) {
					$warnings[] = sprintf( 'El plugin Elementor %s era más nuevo que el núcleo incluido en DIGITALÍSIMO Elements (%s): revisa el editor y actualiza DIGITALÍSIMO Elements en cuanto incluya esa versión.', $info['version'], $bundled );
				}
			}
			deactivate_plugins( $file, false, $network );
			$done[ $file ] = $info['label'];
		}
		return array( 'deactivated' => $done, 'warnings' => $warnings );
	}

	/**
	 * Desactiva en el sitio en curso, sin ejecutar sus rutinas (no están cargadas en esta petición), los plugins que
	 * impiden a DIGITALÍSIMO Elements cargar sus widgets (Elementor Pro, PRO Elements). La migración de red lo hace en
	 * cada sitio antes de desactivar Element Pack en toda la red: sin ello, en un sitio con Elementor Pro activo los
	 * adaptadores no tendrían widgets de los que heredar y las páginas de Element Pack se quedarían sin pintar hasta
	 * que llegara su turno.
	 *
	 * @return string[] etiquetas desactivadas.
	 */
	public static function deactivate_conflicts() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$done = array();
		foreach ( self::status() as $file => $info ) {
			if ( $info['conflict'] && $info['site'] ) {
				deactivate_plugins( $file, true, false );
				$done[] = $info['label'];
			}
		}
		return $done;
	}

	/** Sitios de la red en curso (no los de otras redes de la misma instalación). */
	public static function sites() {
		if ( ! is_multisite() || ! function_exists( 'get_sites' ) ) {
			return array( function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1 );
		}
		return array_map( 'intval', (array) get_sites( array( 'number' => 0, 'fields' => 'ids', 'network_id' => function_exists( 'get_current_network_id' ) ? get_current_network_id() : null ) ) );
	}

	/**
	 * En una red: en cuántos sitios sigue activo por sitio cada plugin retirable (y si lo está en toda la red).
	 *
	 * @return array<string, array{label:string, network:bool, sites:int, element_pack:bool}>
	 */
	public static function network_summary() {
		$out = array();
		foreach ( self::status() as $file => $info ) {
			$out[ $file ] = array( 'label' => $info['label'], 'network' => $info['network'], 'sites' => 0, 'element_pack' => $info['element_pack'] );
		}
		if ( ! $out || ! is_multisite() || ! function_exists( 'get_sites' ) ) {
			return $out;
		}
		foreach ( self::sites() as $site_id ) {
			$active = (array) get_blog_option( (int) $site_id, 'active_plugins', array() );
			foreach ( $out as $file => $row ) {
				$out[ $file ]['sites'] += in_array( $file, $active, true ) ? 1 : 0;
			}
		}
		return $out;
	}
}
