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

	/** Versión mínima del tema DIGITALÍSIMO que necesita la etapa del tema: desde 1.0.1 trae el lector del hijo (`inc/child.php`). */
	const THEME_MIN = '1.0.1';

	/** @var callable|null pruebas: sustituye la instalación (slug, paquete, tipo: plugin|theme|theme-update) y devuelve true o WP_Error. */
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

	/**
	 * Deja el tema DIGITALÍSIMO instalado y al día en su carpeta habitual, como Migrar deja los plugins: si falta, lo
	 * instala; si la copia de esa carpeta es anterior a la de la Release (o a THEME_MIN, la que necesita la etapa del tema),
	 * la actualiza con el mismo paquete y Theme_Upgrader::upgrade(), que guarda la copia anterior y la restaura si algo
	 * falla (no la activa, ni a ella ni a nada). Una carpeta con otro tema no se toca. Sin la Release, una copia que ya
	 * cumple THEME_MIN se queda como está.
	 *
	 * @param bool $may_update Quien pulsó Migrar puede actualizar temas.
	 * @return array{action:string,from:string,to:string}|\WP_Error action: none|installed|updated.
	 */
	public static function ensure_theme( $may_update = true ) {
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme( Replacements::THEME ) : null;
		$entry = null;
		if ( ! $theme || ! $theme->exists() ) {
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
			return wp_get_theme( Replacements::THEME )->exists() ? array( 'action' => 'installed', 'from' => '', 'to' => (string) wp_get_theme( Replacements::THEME )->get( 'Version' ) ) : new \WP_Error( 'digitalisimo_migration_install', 'El paquete del tema DIGITALÍSIMO se descomprimió, pero no quedó en su carpeta.' );
		}
		$from = (string) $theme->get( 'Version' );
		if ( Replacements::THEME_URI !== (string) $theme->get( 'UpdateURI' ) ) {
			// La carpeta habitual la ocupa otro tema: no se pisa.
			return new \WP_Error( 'digitalisimo_migration_foreign_theme', sprintf( 'La carpeta %s/ la ocupa otro tema («%s»), no el tema DIGITALÍSIMO; no se tocó.', Replacements::THEME, (string) $theme->get( 'Name' ) ) );
		}
		$entry = self::index()['themes'][ Replacements::THEME ] ?? null;
		$old   = '' === $from || version_compare( $from, self::THEME_MIN, '<' );
		if ( ! $entry || ! version_compare( $from, $entry['version'], '<' ) ) {
			if ( $old ) {
				return new \WP_Error( 'digitalisimo_migration_no_package', sprintf( 'El tema DIGITALÍSIMO instalado (%s) es anterior a %s y no se encontró una versión nueva en la Release publicada.', '' === $from ? 'sin versión' : $from, self::THEME_MIN ) );
			}
			return array( 'action' => 'none', 'from' => $from, 'to' => $from );
		}
		if ( version_compare( $entry['version'], self::THEME_MIN, '<' ) ) {
			return new \WP_Error( 'digitalisimo_migration_no_package', sprintf( 'La Release publicada trae el tema DIGITALÍSIMO %s, anterior a %s.', $entry['version'], self::THEME_MIN ) );
		}
		if ( ! $may_update ) {
			// Sin permiso, una copia que ya sirve a la etapa se queda como está.
			return $old ? new \WP_Error( 'digitalisimo_migration_caps', 'Quien pulsó Migrar no puede actualizar temas.' ) : array( 'action' => 'none', 'from' => $from, 'to' => $from );
		}
		if ( version_compare( (string) ( $GLOBALS['wp_version'] ?? '6.3' ), '6.3', '<' ) ) {
			// Antes de 6.3, WordPress vacía la carpeta antes de copiar la versión nueva y no guarda la anterior.
			return new \WP_Error( 'digitalisimo_migration_old_wp', 'Con WordPress anterior a 6.3 una actualización de tema no guarda la copia anterior: actualiza WordPress (o el tema, a mano).' );
		}
		// Copia propia de la carpeta antes de tocarla: si la actualización falla o la petición muere a mitad de la copia,
		// la siguiente pasada (recover_theme()) la devuelve a su sitio.
		if ( ! self::keep_copy() ) {
			return new \WP_Error( 'digitalisimo_migration_copy', 'No se pudo guardar una copia del tema instalado antes de actualizarlo; no se cambió nada.' );
		}
		$result = self::run_installer( Replacements::THEME, $entry['package'], 'theme-update' );
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache( false );
		}
		$to = (string) wp_get_theme( Replacements::THEME )->get( 'Version' );
		if ( is_wp_error( $result ) || '' === $to || version_compare( $to, $entry['version'], '<' ) ) {
			// La carpeta vuelve a ser la de antes, pase lo que pase con la de WordPress.
			self::restore_copy();
		}
		self::drop_copy();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( '' === $to || version_compare( $to, $entry['version'], '<' ) ) {
			return new \WP_Error( 'digitalisimo_migration_install', sprintf( 'WordPress terminó sin dejar la versión %s en la carpeta del tema (quedó %s).', $entry['version'], '' === $to ? 'sin versión' : $to ) );
		}
		return array( 'action' => 'updated', 'from' => $from, 'to' => $to );
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
		$skin = new \Automatic_Upgrader_Skin();
		if ( 'theme-update' === $type ) {
			$result = self::upgrade_theme( $skin, $slug, $package );
		} else {
			$upgrader = 'theme' === $type ? new \Theme_Upgrader( $skin ) : new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $package );
		}
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

	/**
	 * Actualiza la copia de la carpeta $slug con Theme_Upgrader::upgrade(), la vía de WordPress para una actualización
	 * (`theme` y `temp_backup` en hook_extra: guarda la copia anterior y la restaura si la nueva no queda; la carpeta se
	 * conserva). WordPress sólo actualiza lo que su caché ofrece: mientras dura, la entrada de este tema apunta al paquete
	 * validado. Antes de sustituir nada, el paquete descomprimido debe ser el tema DIGITALÍSIMO (su `Update URI`, sin
	 * `Template`).
	 */
	private static function upgrade_theme( $skin, $slug, $package ) {
		$version = preg_replace( '/^.*-(\d+(?:\.\d+)+)\.zip$/', '$1', (string) $package );
		$offer   = static function ( $value ) use ( $slug, $package, $version ) {
			$value = is_object( $value ) ? $value : new \stdClass();
			if ( ! isset( $value->response ) || ! is_array( $value->response ) ) {
				$value->response = array();
			}
			$value->response[ $slug ] = array( 'theme' => $slug, 'new_version' => $version, 'package' => $package, 'url' => '' );
			return $value;
		};
		$check = static function ( $source, $remote = '', $upgrader = null, $extra = array() ) use ( $slug ) {
			if ( is_wp_error( $source ) || ! is_array( $extra ) || $slug !== (string) ( $extra['theme'] ?? '' ) ) {
				return $source;
			}
			$file = trailingslashit( (string) $source ) . 'style.css';
			$data = is_readable( $file ) ? get_file_data( $file, array( 'uri' => 'Update URI', 'template' => 'Template' ) ) : array();
			if ( Replacements::THEME_URI !== (string) ( $data['uri'] ?? '' ) || '' !== (string) ( $data['template'] ?? '' ) ) {
				return new \WP_Error( 'digitalisimo_migration_package', 'El paquete descargado no es el tema DIGITALÍSIMO; no se cambió nada.' );
			}
			return $source;
		};
		add_filter( 'site_transient_update_themes', $offer, PHP_INT_MAX );
		add_filter( 'upgrader_source_selection', $check, 1, 4 );
		try {
			return ( new \Theme_Upgrader( $skin ) )->upgrade( $slug );
		} finally {
			remove_filter( 'site_transient_update_themes', $offer, PHP_INT_MAX );
			remove_filter( 'upgrader_source_selection', $check, 1 );
		}
	}

	/** Carpeta de la copia propia del tema mientras se actualiza (fuera de themes/, junto a la de WordPress). */
	public static function copy_dir() {
		return dirname( get_theme_root( Replacements::THEME ) ) . '/upgrade/digitalisimo-migration-theme';
	}

	/** Copia la carpeta del tema a copy_dir(), entera; false si no pudo (entonces no se toca nada). */
	private static function keep_copy() {
		self::drop_copy();
		$from = get_theme_root( Replacements::THEME ) . '/' . Replacements::THEME;
		return is_dir( $from ) && self::copy_tree( $from, self::copy_dir() ) && is_file( self::copy_dir() . '/style.css' );
	}

	/**
	 * Devuelve la copia propia a la carpeta del tema: la carpeta que haya (a medias o rota) se aparta y se borra. true si
	 * la carpeta quedó como la copia.
	 */
	private static function restore_copy() {
		$copy = self::copy_dir();
		$dir  = get_theme_root( Replacements::THEME ) . '/' . Replacements::THEME;
		if ( ! is_file( $copy . '/style.css' ) ) {
			return false;
		}
		$aside = $dir . '.digitalisimo-broken';
		self::remove_tree( $aside );
		if ( is_dir( $dir ) && ! @rename( $dir, $aside ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}
		$ok = self::copy_tree( $copy, $dir );
		self::remove_tree( $aside );
		if ( function_exists( 'wp_clean_themes_cache' ) ) {
			wp_clean_themes_cache( false );
		}
		return $ok;
	}

	private static function drop_copy() {
		self::remove_tree( self::copy_dir() );
	}

	/**
	 * Al empezar la etapa del tema: si quedó la copia propia, una actualización anterior se cortó a mitad (la petición
	 * murió): la carpeta vuelve a ser la copia. true si hubo que hacerlo.
	 */
	public static function recover_theme() {
		if ( ! is_file( self::copy_dir() . '/style.css' ) ) {
			self::drop_copy();
			return false;
		}
		self::restore_copy();
		self::drop_copy();
		return true;
	}

	private static function copy_tree( $from, $to ) {
		if ( ! is_dir( $to ) && ! @mkdir( $to, 0755, true ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return false;
		}
		foreach ( (array) scandir( $from ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$ok = is_dir( "$from/$name" ) ? self::copy_tree( "$from/$name", "$to/$name" ) : @copy( "$from/$name", "$to/$name" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! $ok ) {
				return false;
			}
		}
		return true;
	}

	private static function remove_tree( $dir ) {
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			is_dir( "$dir/$name" ) && ! is_link( "$dir/$name" ) ? self::remove_tree( "$dir/$name" ) : @unlink( "$dir/$name" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
