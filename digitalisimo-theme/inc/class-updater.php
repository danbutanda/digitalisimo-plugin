<?php
/** Actualizaciones del tema desde las Releases: ofrece la versión nueva; nunca fuerza la actualización automática. */
defined( 'ABSPATH' ) || exit;

final class Digitalisimo_Theme_Updater {
	const URI            = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme';
	const SLUG           = 'digitalisimo-theme';
	const REPOSITORY     = 'danbutanda/digitalisimo-plugin';
	/** Índice de versiones adjunto a cada Release: se descarga como archivo, sin el límite de consultas de la API. */
	const MANIFEST_URL   = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	/** Caché compartida del índice con los plugins Digitalisimo. */
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';
	const CACHE_KEY      = 'digitalisimo_theme_release';
	/** Última versión encontrada: si GitHub no responde, la actualización sigue a la vista. */
	const LAST_KNOWN     = 'digitalisimo_theme_last_release';
	/** Ficha de «Ver detalles» que pinta DIGITALÍSIMO Tools (admin-post); sin Tools se enlaza la Release. */
	const DETAILS_ACTION = 'digitalisimo_theme_details';

	public static function init() {
		// Filtros en todos los sitios (baratos): un `wp --url=<subsitio>` también rehace el inventario de toda la red.
		if ( self::URI !== (string) wp_get_theme( get_template() )->get( 'UpdateURI' ) ) {
			return;
		}
		add_filter( 'update_themes_github.com', array( __CLASS__, 'uri_update' ), 30, 4 );
		add_filter( 'pre_set_site_transient_update_themes', array( __CLASS__, 'check' ), 30 );
		add_filter( 'site_transient_update_themes', array( __CLASS__, 'check' ), 30 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'keep_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_after_upgrade' ), 10, 2 );
		add_action( 'load-update-core.php', array( __CLASS__, 'native_force_check' ), 1 );
		add_action( 'delete_site_transient_' . self::MANIFEST_CACHE, array( __CLASS__, 'clear_own' ) );
	}

	/** Carpeta real del tema (puede no ser la habitual). */
	public static function stylesheet() {
		return get_template();
	}

	/**
	 * Versión instalada, leída de su style.css. La constante es la del código cargado en esta petición: en la que
	 * actualiza el propio tema sigue siendo la anterior, y con ella la caché de actualizaciones volvería a ofrecer la
	 * versión que se acaba de instalar (WordPress vacía la caché de temas en `upgrader_process_complete`, 9).
	 */
	public static function installed_version() {
		$version = (string) wp_get_theme( self::stylesheet() )->get( 'Version' );
		return '' !== $version ? $version : DIGITALISIMO_THEME_VERSION;
	}

	/** Requisitos de la versión ofrecida; con alguno incompatible, WordPress la muestra pero no la instala sola. */
	private static function offer( $ss, $r, $pending ) {
		$r    = array_merge( array( 'requires' => '6.8', 'requires_php' => '7.4' ), (array) $r );
		$item = array(
			'theme'        => $ss,
			'new_version'  => $pending ? $r['version'] : self::installed_version(),
			'url'          => self::details_url( $ss, $r ),
			'package'      => $pending ? $r['package'] : '',
			'requires'     => $r['requires'],
			'requires_php' => $r['requires_php'],
			'id'           => self::URI,
		);
		if ( ! is_wp_version_compatible( $r['requires'] ) || ! is_php_version_compatible( $r['requires_php'] ) ) {
			$item['disable_autoupdate'] = true;
		}
		return $item;
	}

	/** Respuesta para el `Update URI` (WordPress 6.1+): sólo para este tema como padre. */
	public static function uri_update( $update, $theme_data, $stylesheet, $locales ) {
		if ( $update || self::stylesheet() !== $stylesheet || self::URI !== ( $theme_data['UpdateURI'] ?? '' ) || $stylesheet !== ( $theme_data['Template'] ?? '' ) ) {
			return $update;
		}
		$r = self::release();
		if ( empty( $r['version'] ) || empty( $r['package'] ) ) {
			return $update;
		}
		return array( 'version' => $r['version'] ) + self::offer( $stylesheet, $r, true );
	}

	/** Respaldo en la caché de actualizaciones: sustituye una respuesta de wordpress.org con el mismo slug. */
	public static function check( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}
		if ( ! ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || current_user_can( 'update_themes' ) ) ) {
			return $transient;
		}
		$r = self::release();
		if ( empty( $r['version'] ) || empty( $r['package'] ) ) {
			return $transient;
		}
		foreach ( array( 'response', 'no_update' ) as $list ) {
			if ( ! isset( $transient->{$list} ) || ! is_array( $transient->{$list} ) ) {
				$transient->{$list} = array();
			}
		}
		$ss                        = self::stylesheet();
		$installed                 = self::installed_version();
		$transient->checked[ $ss ] = $installed;
		$pending                   = version_compare( $r['version'], $installed, '>' );
		unset( $transient->response[ $ss ], $transient->no_update[ $ss ] ); // Gana sobre una respuesta homónima de wordpress.org.
		$transient->{ $pending ? 'response' : 'no_update' }[ $ss ] = self::offer( $ss, $r, $pending );
		return $transient;
	}

	/** Versión publicada: índice de la Release; la API sólo si el índice no se pudo descargar. */
	private static function release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return (array) $cached;
		}
		$best = self::from_manifest();
		if ( null === $best ) {
			$best = self::from_api();
		}
		if ( $best ) {
			update_site_option( self::LAST_KNOWN, $best );
			set_site_transient( self::CACHE_KEY, $best, 10 * MINUTE_IN_SECONDS );
			return $best;
		}
		$known = get_site_option( self::LAST_KNOWN, array() );
		$known = is_array( $known ) ? $known : array();
		set_site_transient( self::CACHE_KEY, $known, 2 * MINUTE_IN_SECONDS );
		return $known;
	}

	/** null si no se obtuvo el índice; array() si no trae un tema válido; la entrada si la trae. */
	private static function from_manifest() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get(
				self::MANIFEST_URL,
				array(
					'timeout'     => 10,
					'redirection' => 5,
					'headers'     => array(
						'Accept'     => 'application/json',
						'User-Agent' => 'Digitalisimo-Theme',
					),
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}
			$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) {
				return null;
			}
			set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS );
		}
		$entry = isset( $manifest['themes'] ) && is_array( $manifest['themes'] ) ? ( $manifest['themes'][ self::SLUG ] ?? null ) : null;
		if ( ! is_array( $entry ) ) {
			return array(); // Índice válido sin el tema: aún no se publicó; no se consulta la API.
		}
		$version = (string) ( $entry['version'] ?? '' );
		$package = (string) ( $entry['url'] ?? '' );
		if ( ! self::valid_package( $version, $package ) ) {
			return array();
		}
		$release_url = (string) ( $manifest['release_url'] ?? '' );
		if ( 0 !== strpos( $release_url, 'https://github.com/' . self::REPOSITORY . '/releases' ) ) {
			$release_url = 'https://github.com/' . self::REPOSITORY . '/releases';
		}
		return array(
			'version'      => $version,
			'package'      => esc_url_raw( $package ),
			'url'          => esc_url_raw( $release_url ),
			'requires'     => self::requirement( $entry['requires'] ?? '', '6.8' ),
			'requires_php' => self::requirement( $entry['requires_php'] ?? '', '7.4' ),
		);
	}

	/** Respaldo por la API de GitHub (sujeta a su límite de consultas): el ZIP más reciente del tema. */
	private static function from_api() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Digitalisimo-Theme',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$best = array();
		foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
				continue;
			}
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! is_array( $asset ) || ! preg_match( '/^digitalisimo-theme-([0-9.]+)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) {
					continue;
				}
				$package = (string) ( $asset['browser_download_url'] ?? '' );
				if ( ! self::valid_package( $match[1], $package ) || ( ! empty( $best['version'] ) && ! version_compare( $match[1], $best['version'], '>' ) ) ) {
					continue;
				}
				$url  = (string) ( $release['html_url'] ?? '' );
				$best = array(
					'version'      => $match[1],
					'package'      => esc_url_raw( $package ),
					'url'          => esc_url_raw( 0 === strpos( $url, 'https://github.com/' . self::REPOSITORY . '/releases' ) ? $url : 'https://github.com/' . self::REPOSITORY . '/releases' ),
					'requires'     => '6.8',
					'requires_php' => '7.4',
				);
			}
		}
		return $best;
	}

	/** Sólo un ZIP del tema con esa versión en las Releases de este repositorio. */
	private static function valid_package( $version, $package ) {
		return (bool) preg_match( '/^\d+(?:\.\d+)+$/', (string) $version )
			&& (bool) preg_match( '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . self::SLUG . '-' . preg_quote( (string) $version, '#' ) . '\.zip$#', (string) $package );
	}

	/** Requisito con formato de versión, o el valor por defecto. */
	private static function requirement( $value, $default ) {
		return is_scalar( $value ) && preg_match( '/^\d+(?:\.\d+)*$/', (string) $value ) ? (string) $value : $default;
	}

	/** «Ver detalles»: la ficha de Tools si está, o la página de la Release. */
	private static function details_url( $ss, $r ) {
		if ( has_action( 'admin_post_' . self::DETAILS_ACTION ) ) {
			return add_query_arg(
				array(
					'action' => self::DETAILS_ACTION,
					'theme'  => $ss,
				),
				admin_url( 'admin-post.php' )
			);
		}
		return (string) ( $r['url'] ?? '' );
	}

	/**
	 * El ZIP de la Release trae la carpeta habitual: si el tema está instalado en otra, la versión nueva se deja en esa
	 * misma carpeta. Si no se puede, se detiene antes de que WordPress toque nada.
	 */
	public static function keep_folder( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		global $wp_filesystem;
		$folder = self::stylesheet();
		if ( is_wp_error( $source ) || ! is_array( $hook_extra ) || $folder !== ( $hook_extra['theme'] ?? '' ) ) {
			return $source; // 'theme' llega en upgrade y en bulk_upgrade.
		}
		$trim   = untrailingslashit( (string) $source );
		$header = self::package_header( $trim );
		if ( null !== $header && ( self::URI !== $header['UpdateURI'] || '' !== $header['Template'] ) ) {
			return new WP_Error( 'digitalisimo_theme_package', 'El paquete no es el tema DIGITALÍSIMO.' );
		}
		if ( basename( $trim ) === $folder ) {
			return trailingslashit( $trim );
		}
		// Si no se mueve, WordPress vaciaría la carpeta homónima y, con la copia activa aquí, cambiaría de tema
		// (WP:class-wp-upgrader.php:641-650, WP:class-theme-upgrader.php:727-731): se detiene antes de tocar nada.
		if ( null === $header || ! $wp_filesystem || ! $wp_filesystem->move( $trim, dirname( $trim ) . '/' . $folder, true ) ) {
			return new WP_Error( 'digitalisimo_theme_folder', 'No se pudo dejar la versión nueva en la carpeta del tema; no se cambió nada.' );
		}
		return trailingslashit( dirname( $trim ) . '/' . $folder );
	}

	/** Cabeceras `Update URI` y `Template` del style.css del paquete, o null si no se pudo leer. */
	private static function package_header( $dir ) {
		global $wp_filesystem;
		$text  = '';
		$local = trailingslashit( $dir );
		if ( is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'wp_content_dir' ) && defined( 'WP_CONTENT_DIR' ) ) {
			$local = str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), $local );
		}
		if ( is_readable( $local . 'style.css' ) ) {
			$text = (string) file_get_contents( $local . 'style.css', false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( is_object( $wp_filesystem ) ) {
			$contents = $wp_filesystem->get_contents( trailingslashit( $dir ) . 'style.css' );
			$text     = is_string( $contents ) ? substr( $contents, 0, 8192 ) : '';
		}
		if ( '' === trim( $text ) ) {
			return null;
		}
		return array(
			'UpdateURI' => self::header_value( $text, 'Update URI' ),
			'Template'  => self::header_value( $text, 'Template' ),
		);
	}

	/** Lee una cabecera como get_file_data(). */
	private static function header_value( $text, $name ) {
		$text = str_replace( "\r", "\n", (string) $text );
		if ( preg_match( '/^(?:[ \t]*<\?(?:php)?)?[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $text, $match ) && $match[1] ) {
			return function_exists( '_cleanup_header_comment' ) ? _cleanup_header_comment( $match[1] ) : trim( (string) preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) );
		}
		return '';
	}

	public static function clear_after_upgrade( $upgrader, $o ) {
		$themes = (array) ( $o['themes'] ?? ( isset( $o['theme'] ) ? array( $o['theme'] ) : array() ) );
		if ( 'update' === ( $o['action'] ?? '' ) && in_array( self::stylesheet(), $themes, true ) ) {
			self::clear();
		}
	}

	/** «Buscar actualizaciones de nuevo» de WordPress: se olvidan la versión conocida y el índice compartido. */
	public static function native_force_check() {
		if ( ! empty( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo vacía cachés propias.
			self::clear();
		}
	}

	public static function clear_own() {
		delete_site_transient( self::CACHE_KEY );
	}

	public static function clear() {
		self::clear_own();
		delete_site_transient( self::MANIFEST_CACHE );
	}
}
