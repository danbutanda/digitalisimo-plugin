<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Plugins que DIGITALÍSIMO Elements sustituye y la migración desactiva: Element Pack (el Addon, Pro y Lite), sus
 * funciones avanzadas (Elementor Pro o PRO Elements), el constructor (Elementor) y, al final, Prime Slider (Pro y Lite).
 * Se reconocen por el archivo principal y la cabecera «Plugin Name» que declaran, estén en la carpeta que estén, y nunca
 * se borran ni se tocan sus archivos: sólo se desactivan, como desde Plugins, y el administrador los elimina cuando ha
 * comprobado el sitio. Element Pack debe seguir instalado mientras dura la conversión (lee sus ajustes y copia sus
 * figuras). Elementor sólo se desactiva si el núcleo incluido en DIGITALÍSIMO Elements puede sustituirlo.
 *
 * Reglas de PLUGINS (V.4, F8-D3):
 * - `group`: cada grupo se desactiva con una sola llamada a deactivate_plugins() por ámbito (una escritura de la
 *   opción), en el orden de la constante. Así el Addon nunca queda activo sin Pro en el mismo ámbito.
 * - `late`: el grupo se desactiva en la etapa `retire_late` (Prime Slider se convierte activo).
 * - `match` = `name`: se reconoce por «Plugin Name» en cualquier archivo, siempre que la carpeta empiece por
 *   `folder` o el «Author» case con `vendor` (el archivo del Addon no está confirmado). Sin él, por archivo y nombre.
 * - `requires`: archivo del que depende dentro del grupo. Un miembro del que otro depende (Pro) nunca se desactiva en un
 *   ámbito mientras el dependiente (Addon) siga activo en otro; el dependiente se desactiva con él o antes.
 *
 * Un plugin más que sustituya DIGITALÍSIMO Elements se añade a PLUGINS (o con el filtro
 * `digitalisimo_migration_retired_plugins`), en el orden en que debe desactivarse.
 *
 * KEPT queda vacío: el Addon y Prime Slider pasaron a PLUGINS. Se conserva la constante (y kept_status()) para quien la
 * lea.
 */
final class Retire {
	const PLUGINS = array(
		'bdthemes-element-pack-addon.php' => array( 'name' => '/^Element Pack Addon\b/i', 'match' => 'name', 'folder' => 'bdthemes-', 'vendor' => '/BdThemes/i', 'label' => 'Element Pack Addon', 'group' => 'element-pack', 'element_pack' => true, 'requires' => 'bdthemes-element-pack.php' ),
		'bdthemes-element-pack.php'       => array( 'name' => '/^Element Pack/i', 'label' => 'Element Pack Pro', 'group' => 'element-pack', 'element_pack' => true ),
		'bdthemes-element-pack-lite.php'  => array( 'name' => '/^Element Pack/i', 'label' => 'Element Pack Lite', 'group' => 'element-pack', 'element_pack' => true ),
		'elementor-pro.php'               => array( 'name' => '/^Elementor Pro/i', 'label' => 'Elementor Pro', 'conflict' => true, 'group' => 'pro' ),
		'pro-elements.php'                => array( 'name' => '/^PRO Elements/i', 'label' => 'PRO Elements', 'conflict' => true, 'group' => 'pro' ),
		'elementor.php'                   => array( 'name' => '/^Elementor$/i', 'label' => 'Elementor', 'core' => true, 'group' => 'core' ),
		'bdthemes-prime-slider.php'       => array( 'name' => '/^Prime Slider\b/i', 'label' => 'Prime Slider', 'pro' => true, 'late' => true, 'group' => 'prime-slider' ),
	);

	/** Plugins de la familia que sólo se detectaban (F0.1). Vacío desde F8: todos se retiran. */
	const KEPT = array();

	/** Nombre de un plugin que parece el Addon (para fallar cerrado si ninguna regla lo reconoce). */
	const ADDON_LIKE = '/\bElement Pack\b.*\bAdd-?on\b/i';

	/** Reglas en vigor (con el filtro), completadas con sus valores por defecto. */
	public static function rules() {
		$rules = (array) apply_filters( 'digitalisimo_migration_retired_plugins', self::PLUGINS );
		$out   = array();
		foreach ( $rules as $basename => $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['name'] ) ) {
				continue;
			}
			$out[ (string) $basename ] = $rule + array( 'label' => (string) $basename, 'group' => (string) $basename, 'late' => false, 'match' => 'file', 'requires' => '', 'core' => false, 'conflict' => false, 'element_pack' => 0 === strpos( (string) $basename, 'bdthemes-element-pack' ), 'pro' => false );
		}
		return $out;
	}

	/** ¿Casa un plugin instalado con una regla? */
	private static function matches( $basename, array $rule, $file, array $data ) {
		$name = (string) ( $data['Name'] ?? '' );
		if ( ! preg_match( (string) $rule['name'], $name ) ) {
			return false;
		}
		if ( 'name' === $rule['match'] ) {
			$folder = false !== strpos( $file, '/' ) ? strstr( $file, '/', true ) : '';
			return ( ! empty( $rule['folder'] ) && 0 === strpos( (string) $folder, (string) $rule['folder'] ) )
				|| ( ! empty( $rule['vendor'] ) && preg_match( (string) $rule['vendor'], (string) ( $data['Author'] ?? '' ) . ' ' . (string) ( $data['AuthorName'] ?? '' ) ) );
		}
		return basename( $file ) === $basename;
	}

	/**
	 * Plugins instalados que la migración retira, en el orden en que se desactivan. `conflict`: mientras está activo,
	 * DIGITALÍSIMO Elements no carga sus funciones ni sus widgets (Elementor Pro, PRO Elements). `element_pack`: la
	 * conversión aún lee sus ajustes y copia sus figuras.
	 *
	 * @return array<string, array{label:string, core:bool, conflict:bool, element_pack:bool, version:string, group:string, late:bool, requires:string, rule:string}> archivo => datos.
	 */
	public static function installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$own   = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
		$rules = self::rules();
		$all   = (array) get_plugins();
		$out   = array();
		// En el orden de las reglas; un archivo casa con la primera.
		foreach ( $rules as $basename => $rule ) {
			foreach ( $all as $file => $data ) {
				$file = (string) $file;
				if ( $file === $own || isset( $out[ $file ] ) || ! self::matches( $basename, $rule, $file, (array) $data ) ) {
					continue;
				}
				$label        = (string) $rule['label'] . ( ! empty( $rule['pro'] ) && preg_match( '/\bPro\b/', (string) ( $data['Name'] ?? '' ) ) ? ' Pro' : '' );
				$out[ $file ] = array(
					'label'        => $label,
					'core'         => ! empty( $rule['core'] ),
					'conflict'     => ! empty( $rule['conflict'] ),
					'element_pack' => ! empty( $rule['element_pack'] ),
					'version'      => (string) ( $data['Version'] ?? '' ),
					'group'        => (string) $rule['group'],
					'late'         => ! empty( $rule['late'] ),
					'requires'     => (string) $rule['requires'],
					'rule'         => (string) $basename,
				);
			}
		}
		return $out;
	}

	/**
	 * Plugins instalados que parecen el Addon pero ninguna regla reconoce: con alguno, el grupo `element-pack` no se
	 * desactiva (un Addon sin Pro podría dar un error fatal).
	 *
	 * @return string[] archivos.
	 */
	public static function unknown_addons() {
		$known = self::installed();
		$out   = array();
		foreach ( (array) get_plugins() as $file => $data ) {
			if ( ! isset( $known[ (string) $file ] ) && preg_match( self::ADDON_LIKE, (string) ( $data['Name'] ?? '' ) ) ) {
				$out[] = (string) $file;
			}
		}
		return $out;
	}

	/**
	 * Plugins de la familia instalados que la migración no desactiva (KEPT), con su estado en este sitio y en la red.
	 * Desde F8, KEPT está vacío y esto no devuelve nada.
	 *
	 * @return array<string, array{slug:string, label:string, version:string, element_pack:bool, site:bool, network:bool}> archivo => datos.
	 */
	public static function kept_status() {
		if ( ! self::KEPT ) {
			return array();
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$own     = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
		$retired = self::installed();
		$site    = (array) get_option( 'active_plugins', array() );
		$network = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		$out     = array();
		foreach ( (array) get_plugins() as $file => $data ) {
			$file = (string) $file;
			$name = (string) ( $data['Name'] ?? '' );
			if ( $file === $own || isset( $retired[ $file ] ) ) {
				continue;
			}
			foreach ( self::KEPT as $slug => $rule ) {
				if ( ! preg_match( $rule['file'], basename( $file ) ) && ! preg_match( $rule['name'], $name ) ) {
					continue;
				}
				$label        = $rule['label'] . ( ! empty( $rule['pro'] ) && preg_match( '/\bPro\b/', $name ) ? ' Pro' : '' );
				$out[ $file ] = array( 'slug' => $slug, 'label' => $label, 'version' => (string) ( $data['Version'] ?? '' ), 'element_pack' => false, 'site' => in_array( $file, $site, true ), 'network' => in_array( $file, $network, true ) );
				break;
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
	 * ámbito: la desactivación de red no toca las activaciones por sitio, y la de sitio no toca la red), grupo a grupo
	 * con una sola llamada por grupo. Con $late sólo los grupos tardíos (Prime Slider); sin él, los demás. `$skip`:
	 * grupos que no se tocan ahora (el de Element Pack mientras no pueda garantizarse la cabecera; en la red, los que se
	 * desactivan en la liberación o al final). Elementor sólo se desactiva si el núcleo incluido puede sustituirlo.
	 *
	 * @return array{deactivated: array<string,string>, warnings: string[], codes: array<int, array{0:string,1:array}>, kept: string[]}
	 *         archivo => etiqueta desactivada, avisos, códigos del informe y grupos que se quedaron por una dependencia.
	 */
	public static function deactivate( $network = false, $late = false, array $skip = array() ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$done     = array();
		$warnings = array();
		$codes    = array();
		$kept     = array();
		$status   = self::status();
		$groups   = array();
		foreach ( $status as $file => $info ) {
			if ( (bool) $info['late'] === (bool) $late ) {
				$groups[ $info['group'] ][ $file ] = $info;
			}
		}
		$unknown = null;
		foreach ( $groups as $group => $members ) {
			if ( in_array( $group, $skip, true ) ) {
				continue;
			}
			$files = array();
			foreach ( $members as $file => $info ) {
				if ( $network ? $info['network'] : $info['site'] ) {
					$files[ $file ] = $info;
				}
			}
			if ( ! $files ) {
				continue;
			}
			if ( 'element-pack' === $group ) {
				$unknown = null === $unknown ? self::unknown_addons() : $unknown;
				if ( $unknown ) {
					$warnings[] = 'Hay un plugin que parece el Element Pack Addon y no se reconoce (' . implode( ', ', $unknown ) . '): Element Pack no se desactivó, para no dejar el Addon cargado sin él.';
					$codes[]    = array( 'core.addon_unknown', array( 'name' => implode( ', ', $unknown ) ) );
					$kept[]     = $group;
					continue;
				}
			}
			list( $files, $code, $blocked ) = self::dependencies( $members, $files, (bool) $network );
			if ( $code ) {
				$codes[] = $code;
			}
			if ( $blocked ) {
				$kept[] = $group;
				continue;
			}
			foreach ( $files as $file => $info ) {
				if ( ! $info['core'] ) {
					continue;
				}
				if ( ! self::core_ready() ) {
					$warnings[] = 'Elementor sigue activo: el núcleo incluido en DIGITALÍSIMO Elements no está disponible en esta instalación, así que no se desactivó.';
					unset( $files[ $file ] );
					continue;
				}
				$bundled = self::core_version();
				if ( '' !== $bundled && '' !== $info['version'] && version_compare( $info['version'], $bundled, '>' ) ) {
					$warnings[] = sprintf( 'El plugin Elementor %s era más nuevo que el núcleo incluido en DIGITALÍSIMO Elements (%s): revisa el editor y actualiza DIGITALÍSIMO Elements en cuanto incluya esa versión.', $info['version'], $bundled );
				}
			}
			if ( ! $files ) {
				continue;
			}
			// Una sola escritura por grupo y ámbito: WordPress guarda la opción una vez.
			deactivate_plugins( array_keys( $files ), false, (bool) $network );
			foreach ( $files as $file => $info ) {
				$done[ $file ] = $info['label'];
			}
		}
		return array( 'deactivated' => $done, 'warnings' => $warnings, 'codes' => $codes, 'kept' => $kept );
	}

	/**
	 * Regla de dependencias entre ámbitos (V.4). Devuelve los archivos que pueden desactivarse en este ámbito, el código
	 * del informe (o null) y si el grupo entero se queda.
	 *
	 * | Dependiente (Addon) | Del que depende (Pro) | Sitio                         | Red                                 |
	 * |---------------------|-----------------------|-------------------------------|-------------------------------------|
	 * | sitio               | sitio                 | ambos, una escritura          | —                                   |
	 * | red                 | red                   | —                             | ambos, una escritura                |
	 * | red                 | sitio                 | nada del grupo (addon_scope)  | el Addon; Pro, después, cada sitio  |
	 * | sitio               | red                   | el Addon (addon_scope_info)   | Pro sólo si ningún sitio tiene el Addon |
	 *
	 * @return array{0:array,1:?array,2:bool}
	 */
	private static function dependencies( array $members, array $files, $network ) {
		$code = null;
		foreach ( $members as $dependent => $info ) {
			if ( '' === $info['requires'] ) {
				continue;
			}
			foreach ( $members as $provider => $pinfo ) {
				if ( $pinfo['rule'] !== $info['requires'] && basename( $provider ) !== $info['requires'] ) {
					continue;
				}
				if ( $network ) {
					// Pro en toda la red no cae mientras algún sitio conserve el Addon activado por sitio.
					if ( isset( $files[ $provider ] ) && ! isset( $files[ $dependent ] ) && self::active_on_any_site( $dependent ) ) {
						unset( $files[ $provider ] );
						$code = array( 'core.addon_scope', array( 'name' => $pinfo['label'] ) );
					}
					continue;
				}
				if ( isset( $files[ $provider ] ) && ! empty( $info['network'] ) && empty( $files[ $dependent ] ) ) {
					// El Addon está activo en toda la red y Pro en este sitio: quitar Pro dejaría el Addon sin él.
					return array( array(), array( 'core.addon_scope', array( 'name' => $info['label'] ) ), true );
				}
				if ( isset( $files[ $dependent ] ) && ! empty( $pinfo['network'] ) ) {
					// El Addon del sitio cae; Pro sigue en toda la red (lo desactiva la red).
					$code = array( 'core.addon_scope_info', array( 'name' => $pinfo['label'] ) );
				}
			}
		}
		return array( $files, $code, false );
	}

	/** ¿Está activado por sitio en algún sitio de la red? */
	public static function active_on_any_site( $file ) {
		if ( ! is_multisite() ) {
			return in_array( $file, (array) get_option( 'active_plugins', array() ), true );
		}
		foreach ( self::sites() as $site_id ) {
			if ( in_array( $file, (array) get_blog_option( (int) $site_id, 'active_plugins', array() ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Algún miembro del grupo está activo (en este sitio o en toda la red)? */
	public static function group_active( $group, $scope = 'any' ) {
		foreach ( self::status() as $info ) {
			if ( $info['group'] !== $group ) {
				continue;
			}
			if ( ( 'site' === $scope && $info['site'] ) || ( 'network' === $scope && $info['network'] ) || ( 'any' === $scope && ( $info['site'] || $info['network'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Algún miembro del grupo está instalado? */
	public static function group_installed( $group ) {
		foreach ( self::installed() as $info ) {
			if ( $info['group'] === $group ) {
				return true;
			}
		}
		return false;
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
	 * En una red: en cuántos sitios sigue activo por sitio cada plugin retirable (y si lo está en toda la red). Con
	 * $kept, lo mismo para los plugins de la familia que la migración no desactiva.
	 *
	 * @return array<string, array{label:string, network:bool, sites:int, element_pack:bool}>
	 */
	public static function network_summary( $kept = false ) {
		$out = array();
		foreach ( ( $kept ? self::kept_status() : self::status() ) as $file => $info ) {
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
