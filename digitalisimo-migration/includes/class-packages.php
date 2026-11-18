<?php
namespace Digitalisimo\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Paquetes DIGITALÍSIMO para «Migrar»: el índice de la Release (`digitalisimo-plugins.json`, el mismo que leen los
 * catálogos de Tools y SEO y comparten en su caché) y su instalación con el instalador de WordPress. Sólo se acepta un
 * ZIP de una Release del repositorio oficial cuyo nombre sea el de su slug y su versión, igual que validan los catálogos:
 * nunca otra dirección, aunque el índice la traiga.
 */
final class Packages {
	const REPOSITORY     = 'danbutanda/digitalisimo-plugin';
	const MANIFEST_URL   = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';

	/** @var callable|null pruebas: sustituye la instalación (slug, paquete, tipo) y devuelve true o WP_Error. */
	public static $installer = null;

	/** ¿Es la dirección de un ZIP oficial de ese slug y esa versión? */
	public static function package_ok( $slug, $version, $url ) {
		return 1 === preg_match( '/^digitalisimo-[a-z]+(?:-[a-z]+)*$/', (string) $slug )
			&& 1 === preg_match( '/^\d+(?:\.\d+)+$/', (string) $version )
			&& 1 === preg_match( '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . preg_quote( $slug . '-' . $version, '#' ) . '\.zip$#', (string) $url );
	}

	/**
	 * Índice validado: `plugins` y `themes`, slug => [version, package]. Una entrada rechazada no invalida las demás. Pura.
	 *
	 * @return array{plugins:array,themes:array}
	 */
	public static function validate( $manifest ) {
		$out = array( 'plugins' => array(), 'themes' => array() );
		if ( ! is_array( $manifest ) ) {
			return $out;
		}
		foreach ( array( 'plugins', 'themes' ) as $kind ) {
			foreach ( (array) ( $manifest[ $kind ] ?? array() ) as $slug => $entry ) {
				$version = is_array( $entry ) ? (string) ( $entry['version'] ?? '' ) : '';
				$package = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : '';
				if ( self::package_ok( (string) $slug, $version, $package ) ) {
					$out[ $kind ][ (string) $slug ] = array( 'version' => $version, 'package' => $package );
				}
			}
		}
		return $out;
	}

	/** Índice de la Release más reciente (caché compartida de 10 minutos con los catálogos y actualizadores). */
	public static function index() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 12, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Migration' ) ) );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return self::validate( null );
			}
			$manifest = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) {
				return self::validate( null );
			}
			set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS );
		}
		return self::validate( $manifest );
	}

	/** Instala un plugin DIGITALÍSIMO del mapa desde el índice (no lo activa). true o WP_Error. */
	public static function install_plugin( $slug ) {
		$file = Replacements::own_file( $slug );
		if ( '' === $file ) {
			return new \WP_Error( 'digitalisimo_migration_unknown', sprintf( '%s no es un plugin que Migrar instale.', $slug ) );
		}
		if ( Replacements::installed( $file ) ) {
			return true;
		}
		$entry = self::index()['plugins'][ $slug ] ?? null;
		if ( ! $entry ) {
			return new \WP_Error( 'digitalisimo_migration_no_package', sprintf( 'No se encontró %s en la Release publicada (GitHub no respondió o el índice no lo trae). Vuelve a pulsar Migrar en unos minutos.', Replacements::own_label( $slug ) ) );
		}
		$result = self::run_installer( $slug, $entry['package'], 'plugin' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		return Replacements::installed( $file ) ? true : new \WP_Error( 'digitalisimo_migration_install', sprintf( 'El paquete de %s se descomprimió, pero no quedó en %s.', Replacements::own_label( $slug ), $file ) );
	}

	/** Instala el tema propio desde el índice si no está. true o WP_Error. */
	public static function install_theme() {
		if ( function_exists( 'wp_get_theme' ) && wp_get_theme( Replacements::THEME )->exists() ) {
			return true;
		}
		$entry = self::index()['themes'][ Replacements::THEME ] ?? null;
		if ( ! $entry ) {
			return new \WP_Error( 'digitalisimo_migration_no_package', 'No se encontró el tema DIGITALÍSIMO en la Release publicada.' );
		}
		$result = self::run_installer( Replacements::THEME, $entry['package'], 'theme' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache( false );
		}
		return wp_get_theme( Replacements::THEME )->exists() ? true : new \WP_Error( 'digitalisimo_migration_install', 'El paquete del tema DIGITALÍSIMO se descomprimió, pero no quedó en su carpeta.' );
	}

	/** El instalador de WordPress, sin pantalla: con permisos de escritura directos o falla con el motivo. */
	private static function run_installer( $slug, $package, $type ) {
		if ( ! self::package_ok( $slug, preg_replace( '/^.*-(\d+(?:\.\d+)+)\.zip$/', '$1', (string) $package ), $package ) ) {
			return new \WP_Error( 'digitalisimo_migration_package', 'El paquete no es una Release del repositorio oficial.' );
		}
		if ( is_callable( self::$installer ) ) {
			return call_user_func( self::$installer, $slug, $package, $type );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = 'theme' === $type ? new \Theme_Upgrader( $skin ) : new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $package );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			$errors = $skin->get_errors();
			if ( is_wp_error( $errors ) && $errors->has_errors() ) {
				return $errors;
			}
			return new \WP_Error( 'digitalisimo_migration_install', 'WordPress no pudo instalar el paquete: suele pasar si no puede escribir en wp-content (pide datos de FTP) o si ya existe una carpeta con ese nombre.' );
		}
		return true;
	}
}
