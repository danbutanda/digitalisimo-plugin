<?php
namespace Digitalisimo\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Actualizaciones del tema DIGITALÍSIMO (`digitalisimo-theme`) en las copias que su propio actualizador no atiende.
 *
 * El tema trae su actualizador (`inc/class-updater.php`), pero WordPress sólo carga el código del tema activo: una copia
 * instalada sin activar, activa sólo en otro sitio de la red o subida con otro nombre de carpeta no se atiende a sí
 * misma. Tools (`Network: true`) la cubre: lee del mismo índice de la Release (`digitalisimo-plugins.json`, con la caché
 * compartida) la versión del tema, la entrega a WordPress como array y sin `autoupdate` (la actualización automática sólo
 * la decide la lista `auto_update_themes`; una versión incompatible lleva `disable_autoupdate`), deja la copia nueva en
 * la carpeta donde estaba, pone «Buscar actualizaciones» en su fila de Temas de la red y sirve la ficha de «Ver
 * detalles». Para la copia que sí está cargada es sólo un respaldo: escribe su entrada únicamente si el actualizador del
 * tema no lo hizo. Nunca activa, habilita ni borra un tema.
 */
final class Theme_Updates {
	const PREFIX         = 'https://github.com/danbutanda/digitalisimo-plugin/';
	const SLUG           = 'digitalisimo-theme';
	const REPOSITORY     = 'danbutanda/digitalisimo-plugin';
	const MANIFEST_URL   = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';
	const CACHE          = 'digitalisimo_tools_theme_release';
	const LAST_KNOWN     = 'digitalisimo_tools_theme_last_release';
	const DETAILS        = 'digitalisimo_theme_details';
	const CHECK          = 'digitalisimo_tools_theme_check';
	/** Requisitos que se suponen si el índice no los trae (los de la cabecera del tema 1.0.0). */
	const REQUIRES       = '6.8';
	const REQUIRES_PHP   = '7.4';

	/** @var array|null copias del tema instaladas en esta petición (se vuelve a leer al cambiar, borrar o actualizar un tema). */
	private static $installed = null;

	public static function init() {
		add_action( 'admin_post_' . self::DETAILS, array( __CLASS__, 'details' ) );
		add_action( 'admin_post_' . self::CHECK, array( __CLASS__, 'force_check' ) );
		// En todos los sitios: un `wp --url=<subsitio>` o el cron de un subsitio rehacen el inventario de toda la red.
		add_filter( 'update_themes_github.com', array( __CLASS__, 'uri_update' ), 31, 4 );
		add_filter( 'pre_set_site_transient_update_themes', array( __CLASS__, 'check' ), 31 );
		add_filter( 'site_transient_update_themes', array( __CLASS__, 'check' ), 31 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'keep_folder' ), 11, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_after_upgrade' ), 10, 2 );
		// «Buscar actualizaciones» de cualquier plugin Digitalisimo borra el índice compartido: la versión del tema se vuelve a leer.
		add_action( 'delete_site_transient_' . self::MANIFEST_CACHE, array( __CLASS__, 'clear_own' ) );
		add_action( 'switch_theme', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_theme', array( __CLASS__, 'forget' ) );
		if ( is_multisite() ) add_filter( 'theme_action_links', array( __CLASS__, 'theme_action_links' ), 10, 3 );
	}

	public static function forget() {
		self::$installed = null;
	}

	/**
	 * Copias del tema instaladas: todo tema padre con el «Update URI» del tema, sea cual sea su carpeta.
	 *
	 * @return array<string, array{version:string, name:string}> carpeta (stylesheet) => datos.
	 */
	public static function installed() {
		if ( null !== self::$installed ) return self::$installed;
		self::$installed = array();
		if ( ! function_exists( 'wp_get_themes' ) ) return self::$installed;
		foreach ( (array) wp_get_themes() as $stylesheet => $theme ) {
			if ( ! is_object( $theme ) || self::PREFIX . self::SLUG !== (string) $theme->get( 'UpdateURI' ) || $theme->get_template() !== $theme->get_stylesheet() ) continue;
			self::$installed[ (string) $stylesheet ] = array( 'version' => (string) $theme->get( 'Version' ), 'name' => (string) $theme->get( 'Name' ) );
		}
		return self::$installed;
	}

	/** La copia que atiende el propio actualizador del tema en esta petición (es la plantilla activa), o ''. */
	public static function loaded() {
		if ( ! class_exists( 'Digitalisimo_Theme_Updater', false ) ) return '';
		$template = (string) get_template();
		return isset( self::installed()[ $template ] ) ? $template : '';
	}

	/** Versión, ZIP y requisitos del tema en la última Release (caché de 10 minutos; 2 tras un fallo). */
	public static function release() {
		$cached = get_site_transient( self::CACHE );
		if ( false !== $cached ) return is_array( $cached ) ? $cached : array();
		$entry = self::from_manifest();
		// Sólo si no se obtuvo el índice se consulta la API: un índice válido sin tema es «tema sin publicar».
		if ( null === $entry ) $entry = self::from_api();
		if ( $entry ) {
			update_site_option( self::LAST_KNOWN, $entry );
			set_site_transient( self::CACHE, $entry, 10 * MINUTE_IN_SECONDS );
			return $entry;
		}
		// GitHub no respondió, limitó las consultas o el índice no trae un tema válido: se conserva lo último encontrado.
		$known = get_site_option( self::LAST_KNOWN, array() );
		$known = is_array( $known ) && ! empty( $known['version'] ) ? $known : array();
		set_site_transient( self::CACHE, $known, 2 * MINUTE_IN_SECONDS );
		return $known;
	}

	/** Expresión de un ZIP del tema en las Releases del repositorio oficial. */
	private static function package_pattern( $version ) {
		return '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . self::SLUG . '-' . preg_quote( $version, '#' ) . '\.zip$#';
	}

	/** La página de Releases indicada si es del repositorio oficial; si no, la raíz de sus Releases. */
	private static function release_page( $url ) {
		$url = (string) $url;
		return esc_url_raw( 0 === strpos( $url, 'https://github.com/' . self::REPOSITORY . '/releases' ) ? $url : 'https://github.com/' . self::REPOSITORY . '/releases' );
	}

	/** Requisito con formato de versión, o el supuesto. */
	private static function requirement( $value, $fallback ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return preg_match( '/^\d+(?:\.\d+)*$/', $value ) ? $value : $fallback;
	}

	/**
	 * Entrada del tema en el índice de la última Release.
	 *
	 * @return array|null null si no se obtuvo el índice; array() si no trae un tema válido; la entrada si lo trae.
	 */
	private static function from_manifest() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 10, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Tools' ) ) );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return null;
			$manifest = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			// Sólo se guarda (y se comparte) un índice con plugins, igual que en los actualizadores de los plugins.
			if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) return null;
			set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS );
		}
		$entry   = isset( $manifest['themes'] ) && is_array( $manifest['themes'] ) ? ( $manifest['themes'][ self::SLUG ] ?? null ) : null;
		$version = is_array( $entry ) ? (string) ( $entry['version'] ?? '' ) : '';
		$package = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : '';
		if ( ! preg_match( '/^\d+(?:\.\d+)+$/', $version ) || ! preg_match( self::package_pattern( $version ), $package ) ) return array();
		$tag  = (string) ( $manifest['tag'] ?? '' );
		$date = (string) ( $manifest['generated'] ?? '' );
		return array(
			'version'      => $version,
			'package'      => esc_url_raw( $package ),
			'url'          => self::release_page( $manifest['release_url'] ?? '' ),
			'requires'     => self::requirement( $entry['requires'] ?? '', self::REQUIRES ),
			'requires_php' => self::requirement( $entry['requires_php'] ?? '', self::REQUIRES_PHP ),
			'tag'          => preg_match( '/^[A-Za-z0-9._-]{1,64}$/', $tag ) ? $tag : '',
			'date'         => preg_match( '/^\d{4}-\d{2}-\d{2}T[0-9:.]+(?:Z|[+-]\d{2}:\d{2})$/', $date ) ? $date : '',
		);
	}

	/** Respaldo por la API de GitHub (sujeta a su límite de consultas): el ZIP más reciente del tema. */
	private static function from_api() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Tools' ) ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) return array();
		$best = array();
		foreach ( (array) json_decode( (string) wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! is_array( $asset ) || ! preg_match( '/^digitalisimo-theme-(\d+(?:\.\d+)+)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
				$package = (string) ( $asset['browser_download_url'] ?? '' );
				if ( ! preg_match( self::package_pattern( $match[1] ), $package ) ) continue;
				if ( ! empty( $best['version'] ) && ! version_compare( $match[1], $best['version'], '>' ) ) continue;
				$tag  = (string) ( $release['tag_name'] ?? '' );
				$date = (string) ( $release['published_at'] ?? '' );
				$best = array(
					'version'      => $match[1],
					'package'      => esc_url_raw( $package ),
					'url'          => self::release_page( $release['html_url'] ?? '' ),
					'requires'     => self::REQUIRES,
					'requires_php' => self::REQUIRES_PHP,
					'tag'          => preg_match( '/^[A-Za-z0-9._-]{1,64}$/', $tag ) ? $tag : '',
					'date'         => preg_match( '/^\d{4}-\d{2}-\d{2}T[0-9:.]+(?:Z|[+-]\d{2}:\d{2})$/', $date ) ? $date : '',
				);
			}
		}
		return $best;
	}

	/** ¿Esta instalación cumple los requisitos de la versión publicada? */
	public static function compatible( array $release ) {
		return is_wp_version_compatible( $release['requires'] ?? self::REQUIRES ) && is_php_version_compatible( $release['requires_php'] ?? self::REQUIRES_PHP );
	}

	/** URL de «Ver detalles»: la ficha de sólo lectura de Tools, en la administración donde se mira. */
	public static function details_url( $stylesheet ) {
		return add_query_arg( array( 'action' => self::DETAILS, 'theme' => rawurlencode( (string) $stylesheet ) ), admin_url( 'admin-post.php' ) );
	}

	/**
	 * Entrada de `update_themes` para una copia (siempre array, como las de wordpress.org). Sin `autoupdate`: con un
	 * requisito incompatible lleva `disable_autoupdate`, que sólo puede apagar la actualización automática.
	 */
	public static function offer( $stylesheet, $installed, array $release ) {
		$pending = version_compare( $release['version'], (string) $installed, '>' );
		$item    = array(
			'theme'        => (string) $stylesheet,
			'new_version'  => $pending ? $release['version'] : (string) $installed,
			'url'          => self::details_url( $stylesheet ),
			'package'      => $pending ? $release['package'] : '',
			'requires'     => $release['requires'] ?? self::REQUIRES,
			'requires_php' => $release['requires_php'] ?? self::REQUIRES_PHP,
			'id'           => self::PREFIX . self::SLUG,
		);
		if ( ! self::compatible( $release ) ) $item['disable_autoupdate'] = true;
		return $item;
	}

	/** La consulta de WordPress por «Update URI», si nadie respondió antes por esa copia. */
	public static function uri_update( $update, $theme_data, $stylesheet, $locales = array() ) {
		$installed  = self::installed();
		$stylesheet = (string) $stylesheet;
		if ( $update || ! isset( $installed[ $stylesheet ] ) || self::PREFIX . self::SLUG !== (string) ( is_array( $theme_data ) ? ( $theme_data['UpdateURI'] ?? '' ) : '' ) ) return $update;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) return $update;
		return array( 'version' => $release['version'] ) + self::offer( $stylesheet, $installed[ $stylesheet ]['version'], $release );
	}

	/** ¿La entrada la escribió un actualizador del tema (el propio o este)? */
	private static function own( $item ) {
		$id = is_array( $item ) ? ( $item['id'] ?? '' ) : ( is_object( $item ) ? ( $item->id ?? '' ) : '' );
		return self::PREFIX . self::SLUG === (string) $id;
	}

	/**
	 * En la caché de actualizaciones de WordPress (corre después del actualizador del tema, en 30): cada copia no
	 * cargada se vuelve a escribir siempre, aunque ya tuviera entrada (una de wordpress.org con el mismo nombre, o una
	 * anterior de Tools con una versión vieja); la cargada, sólo si su actualizador no dejó la suya.
	 */
	public static function check( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) return $transient;
		if ( ! ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || current_user_can( 'update_themes' ) ) ) return $transient;
		$installed = self::installed();
		if ( ! $installed ) return $transient;
		$release = self::release();
		if ( empty( $release['version'] ) || empty( $release['package'] ) ) return $transient;
		foreach ( array( 'response', 'no_update' ) as $list ) {
			if ( ! isset( $transient->{$list} ) || ! is_array( $transient->{$list} ) ) $transient->{$list} = array();
		}
		$loaded = self::loaded();
		foreach ( $installed as $stylesheet => $info ) {
			$stylesheet = (string) $stylesheet;
			if ( $stylesheet === $loaded && ( self::own( $transient->response[ $stylesheet ] ?? null ) || self::own( $transient->no_update[ $stylesheet ] ?? null ) ) ) continue;
			$item = self::offer( $stylesheet, $info['version'], $release );
			$transient->checked[ $stylesheet ] = $info['version'];
			unset( $transient->response[ $stylesheet ], $transient->no_update[ $stylesheet ] );
			if ( '' !== $item['package'] ) {
				$transient->response[ $stylesheet ] = $item;
			} else {
				$transient->no_update[ $stylesheet ] = $item;
			}
		}
		return $transient;
	}

	/**
	 * Al actualizar una copia, el ZIP de la Release trae la carpeta `digitalisimo-theme/`: la versión nueva se deja en la
	 * carpeta de esa copia. Si no puede dejarse ahí, la actualización se detiene antes de que WordPress vacíe otra carpeta
	 * con el nombre del ZIP (y, si esa copia fuera la activa, cambiara de tema). Detrás del filtro del tema (10) no hace
	 * nada si ya movió la carpeta.
	 */
	public static function keep_folder( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( is_wp_error( $source ) || ! is_array( $hook_extra ) ) return $source;
		// `theme` llega al actualizar una copia, sola o en lote (Theme_Upgrader::upgrade() y ::bulk_upgrade()); nunca al instalar.
		$folder = (string) ( $hook_extra['theme'] ?? '' );
		if ( '' === $folder || ! isset( self::installed()[ $folder ] ) ) return $source;
		$trim   = untrailingslashit( (string) $source );
		$header = self::package_header( $trim );
		if ( null !== $header && ( self::PREFIX . self::SLUG !== $header['UpdateURI'] || '' !== $header['Template'] ) ) {
			return new \WP_Error( 'digitalisimo_theme_package', 'El paquete no es el tema DIGITALÍSIMO.' );
		}
		if ( basename( $trim ) === $folder ) return trailingslashit( $trim );
		if ( null === $header || ! $wp_filesystem || ! $wp_filesystem->move( $trim, dirname( $trim ) . '/' . $folder, true ) ) {
			return new \WP_Error( 'digitalisimo_theme_folder', 'No se pudo dejar la versión nueva en la carpeta del tema; no se cambió nada.' );
		}
		return trailingslashit( dirname( $trim ) . '/' . $folder );
	}

	/**
	 * Cabeceras `Update URI` y `Template` del style.css del paquete: de la copia local si es legible (como
	 * Theme_Upgrader::check_package) y, si no (FTP), por el sistema de archivos de WordPress.
	 *
	 * @return array{UpdateURI:string, Template:string}|null null si no se pudo leer.
	 */
	private static function package_header( $trim ) {
		global $wp_filesystem;
		$text  = '';
		$local = $trim;
		if ( $wp_filesystem && is_object( $wp_filesystem ) && method_exists( $wp_filesystem, 'wp_content_dir' ) && defined( 'WP_CONTENT_DIR' ) ) {
			$local = untrailingslashit( str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), trailingslashit( $trim ) ) );
		}
		if ( is_file( $local . '/style.css' ) && is_readable( $local . '/style.css' ) ) {
			$text = (string) file_get_contents( $local . '/style.css', false, null, 0, 8192 );
		} elseif ( $wp_filesystem && is_object( $wp_filesystem ) ) {
			$text = substr( (string) $wp_filesystem->get_contents( $trim . '/style.css' ), 0, 8192 );
		}
		if ( '' === trim( $text ) ) return null;
		$text = str_replace( "\r", "\n", $text );
		$read = static function ( $name ) use ( $text ) {
			if ( ! preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $text, $match ) ) return '';
			return trim( (string) preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) );
		};
		return array( 'UpdateURI' => $read( 'Update URI' ), 'Template' => $read( 'Template' ) );
	}

	/** Tras instalar o actualizar un tema se vuelven a leer las copias; si se actualizó una, también la Release. */
	public static function clear_after_upgrade( $upgrader, $options ) {
		if ( ! is_array( $options ) || 'theme' !== ( $options['type'] ?? '' ) ) return;
		$themes = array_map( 'strval', (array) ( $options['themes'] ?? ( isset( $options['theme'] ) ? array( $options['theme'] ) : array() ) ) );
		$ours   = array_intersect( $themes, array_keys( self::installed() ) );
		self::forget();
		if ( 'update' === ( $options['action'] ?? '' ) && $ours ) self::clear_own();
	}

	/** Vacía sólo la caché propia; el índice compartido lo vacía quien lo vuelve a descargar. */
	public static function clear_own() {
		delete_site_transient( self::CACHE );
	}

	/** «Buscar actualizaciones» en la fila de cada copia, en Temas de la red. */
	public static function theme_action_links( $actions, $theme = null, $context = '' ) {
		if ( ! is_network_admin() || ! is_object( $theme ) || ! current_user_can( 'update_themes' ) ) return $actions;
		$stylesheet = (string) $theme->get_stylesheet();
		if ( ! isset( self::installed()[ $stylesheet ] ) ) return $actions;
		$actions = (array) $actions;
		$actions['digitalisimo-check'] = '<a href="' . esc_url( self::check_url( $stylesheet, 'network' ) ) . '">Buscar actualizaciones</a>';
		return $actions;
	}

	/** Enlace firmado de «Buscar actualizaciones» para una copia (nonce por copia). */
	public static function check_url( $stylesheet, $context ) {
		return wp_nonce_url( add_query_arg( array( 'action' => self::CHECK, 'theme' => rawurlencode( (string) $stylesheet ), 'digitalisimo_context' => 'network' === $context ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), self::CHECK . '_' . $stylesheet );
	}

	/** Carpeta pedida tal cual (sin normalizar: sólo vale una coincidencia exacta con una copia instalada). */
	public static function requested_theme() {
		return isset( $_GET['theme'] ) && is_string( $_GET['theme'] ) ? (string) wp_unslash( $_GET['theme'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se compara con las claves exactas de installed() y se verifica el nonce.
	}

	/** Contexto de la pantalla donde empezó la acción (`network` o `site`). */
	public static function context() {
		return isset( $_GET['digitalisimo_context'] ) && is_string( $_GET['digitalisimo_context'] ) ? sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el nonce lo verifica cada handler.
	}

	/**
	 * Vuelve a calcular la entrada de una copia con el índice nuevo: se borra el índice compartido (y con él las cachés
	 * de quienes lo leen) y la entrada; al guardar la caché de WordPress, los filtros `pre_set` la rehacen.
	 */
	public static function refresh( $stylesheet ) {
		delete_site_transient( self::MANIFEST_CACHE );
		self::clear_own();
		$updates = get_site_transient( 'update_themes' );
		if ( is_object( $updates ) && isset( $updates->checked ) && is_array( $updates->checked ) ) {
			if ( isset( $updates->response ) && is_array( $updates->response ) ) unset( $updates->response[ $stylesheet ] );
			if ( isset( $updates->no_update ) && is_array( $updates->no_update ) ) unset( $updates->no_update[ $stylesheet ] );
			set_site_transient( 'update_themes', $updates );
		} else {
			if ( ! function_exists( 'wp_update_themes' ) ) require_once ABSPATH . 'wp-includes/update.php';
			wp_update_themes();
		}
	}

	/** Handler de «Buscar actualizaciones» de una copia. */
	public static function force_check() {
		$stylesheet = self::requested_theme();
		if ( ! current_user_can( 'update_themes' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( self::CHECK . '_' . $stylesheet );
		if ( is_multisite() && 'network' !== self::context() ) wp_die( 'No autorizado.' );
		if ( ! isset( self::installed()[ $stylesheet ] ) ) wp_die( 'El tema indicado no es una copia del tema DIGITALÍSIMO.' );
		self::refresh( $stylesheet );
		$default = is_multisite() ? network_admin_url( 'themes.php' ) : admin_url( 'admin.php?page=digitalisimo-plugins' );
		$back    = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'digitalisimo-theme-checked', '1', $back ? $back : $default ) );
		exit;
	}

	/** Clase de body del diseño compartido: `digitalisimo_ui_screen()` no reconoce admin-post.php. */
	public static function details_body_class( $classes ) {
		return false === strpos( ' ' . $classes . ' ', ' digitalisimo-admin ' ) ? $classes . ' digitalisimo-admin' : $classes;
	}

	/** Fecha de la Release en el formato del sitio, o ''. */
	private static function release_date( $date ) {
		$time = '' !== (string) $date ? strtotime( (string) $date ) : false;
		return $time ? (string) wp_date( (string) get_option( 'date_format', 'Y-m-d' ), $time ) : '';
	}

	/** Contenido de la ficha (todo escapado; sin botones). */
	public static function details_html( $stylesheet, $info, array $release ) {
		$rows = array();
		$rows['Instalada'] = $info ? (string) $info['version'] . ( self::SLUG !== $stylesheet ? ' · carpeta ' . $stylesheet : '' ) : 'No instalado';
		$rows['Publicada'] = ! empty( $release['version'] ) ? (string) $release['version'] : 'Sin publicar';
		if ( ! empty( $release['tag'] ) ) $rows['Etiqueta'] = (string) $release['tag'];
		$date = self::release_date( $release['date'] ?? '' );
		if ( '' !== $date ) $rows['Fecha'] = $date;
		if ( ! empty( $release['version'] ) ) {
			$wp  = (string) ( $release['requires'] ?? self::REQUIRES );
			$php = (string) ( $release['requires_php'] ?? self::REQUIRES_PHP );
			$rows['Requisitos'] = sprintf( 'WordPress %1$s o superior: %2$s · PHP %3$s o superior: %4$s', $wp, is_wp_version_compatible( $wp ) ? 'se cumple' : 'no se cumple', $php, is_php_version_compatible( $php ) ? 'se cumple' : 'no se cumple' );
		}
		$rows['Actualización automática'] = 'No se actualiza solo: la actualización automática sólo se aplica si la activas en Temas.';
		$html = '<div class="wrap digitalisimo-theme-details"><h1>Tema DIGITALÍSIMO</h1><table class="form-table" role="presentation"><tbody>';
		foreach ( $rows as $label => $value ) $html .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		$html .= '</tbody></table>';
		$html .= '<p><a href="' . esc_url( self::release_page( $release['url'] ?? '' ) ) . '" target="_blank" rel="noopener noreferrer">Ver la Release en GitHub</a></p></div>';
		return $html;
	}

	/** Ficha de «Ver detalles» (WordPress la abre en un iframe con la URL de la entrada): sólo lectura. */
	public static function details() {
		if ( ! current_user_can( 'switch_themes' ) ) wp_die( 'No autorizado.' );
		$stylesheet = self::requested_theme();
		$installed  = self::installed();
		if ( ! isset( $installed[ $stylesheet ] ) && self::SLUG !== $stylesheet ) wp_die( 'El tema indicado no es una copia del tema DIGITALÍSIMO.' );
		$release = self::release();
		// iframe_header() lee la pantalla actual y $hook_suffix: sin ellos, admin-post.php imprimiría avisos de PHP.
		set_current_screen( 'digitalisimo-theme-details' );
		$GLOBALS['hook_suffix'] = 'digitalisimo-theme-details';
		if ( function_exists( 'digitalisimo_ui_register' ) ) {
			digitalisimo_ui_register();
			wp_enqueue_style( 'digitalisimo-ui' );
		}
		add_filter( 'admin_body_class', array( __CLASS__, 'details_body_class' ) );
		iframe_header( 'DIGITALÍSIMO' );
		echo self::details_html( $stylesheet, $installed[ $stylesheet ] ?? null, $release ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- details_html() escapa cada valor.
		iframe_footer();
		exit;
	}
}
