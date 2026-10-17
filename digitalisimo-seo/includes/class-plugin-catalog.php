<?php
defined( 'ABSPATH' ) || exit;

/** Catálogo único de los módulos publicados de Digitalisimo. */
final class Digitalisimo_Plugin_Catalog {
	const INSTALL_ACTION = 'digitalisimo_catalog_install';
	const UPDATE_ACTION  = 'digitalisimo_catalog_update';
	const CACHE_KEY      = 'digitalisimo_plugin_catalog_releases';
	const REPOSITORY     = 'danbutanda/digitalisimo-plugin';
	/**
	 * Índice de versiones adjunto a cada Release (el ZIP vigente de los siete plugins). Se descarga como un archivo
	 * de la última Release, no por la API de GitHub, que limita a 60 consultas por hora a cada servidor sin
	 * autenticar y que agotan entre todos los plugins Digitalisimo.
	 */
	const MANIFEST_URL   = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	/** Caché compartida del índice: un solo archivo sirve a todos los plugins que lo lean. */
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';
	/** Último índice encontrado: si GitHub no responde, el catálogo sigue ofreciendo lo último publicado en lugar de vaciarse. */
	const LAST_KNOWN     = 'digitalisimo_catalog_last_known';
	private static $initialized = false;

	public static function init() {
		if ( self::$initialized ) return;
		self::$initialized = true;
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_menu', array( __CLASS__, 'normalize_menu' ), 999 );
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 99 );
			add_action( 'network_admin_menu', array( __CLASS__, 'normalize_network_menu' ), 999 );
		}
		add_action( 'admin_post_' . self::INSTALL_ACTION, array( __CLASS__, 'install' ) );
		add_action( 'admin_post_' . self::UPDATE_ACTION, array( __CLASS__, 'update' ) );
		// «Buscar actualizaciones» de cualquier plugin Digitalisimo (y la limpieza tras actualizar) borra el índice compartido: el catálogo se rehace entonces con el índice nuevo.
		add_action( 'delete_site_transient_' . self::MANIFEST_CACHE, array( __CLASS__, 'clear' ) );
	}

	/** Vacía la caché propia; el índice compartido lo vacía quien lo descarga de nuevo. */
	public static function clear() { delete_site_transient( self::CACHE_KEY ); }

	public static function menu() { add_submenu_page( 'digitalisimo', 'Plugins · Digitalisimo', 'Plugins', 'manage_options', 'digitalisimo-plugins', array( __CLASS__, 'page' ) ); }
	public static function network_menu() { add_submenu_page( 'digitalisimo-network', 'Plugins · Digitalisimo', 'Plugins', 'manage_network_plugins', 'digitalisimo-plugins', array( __CLASS__, 'page' ) ); }
	public static function normalize_menu() { self::sort_menu( 'digitalisimo' ); }
	public static function normalize_network_menu() { self::sort_menu( 'digitalisimo-network' ); }

	private static function sort_menu( $parent ) {
		global $submenu;
		remove_submenu_page( $parent, 'digitalisimo-seo-ai-crawlers' );
		remove_submenu_page( $parent, 'digitalisimo-seo-ai-verify-crawler' );
		if ( empty( $submenu[ $parent ] ) ) return;
		usort( $submenu[ $parent ], function( $left, $right ) { return strcasecmp( wp_strip_all_tags( $left[0] ), wp_strip_all_tags( $right[0] ) ); } );
	}

	private static function plugins() {
		return array(
			'digitalisimo-backups' => array( 'Digitalisimo Backups', 'digitalisimo-backups/digitalisimo-backups.php', 'Respaldos de sitio y red.' ),
			'digitalisimo-hosting' => array( 'Digitalisimo Hosting', 'digitalisimo-hosting/digitalisimo-hosting.php', 'Dominios, Namecheap, WooCommerce y hosting.' ),
			'digitalisimo-ia-tools' => array( 'Digitalisimo IA Tools', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'Chatbot, proveedores IA, RAG y generación de imágenes.' ),
			'digitalisimo-seo' => array( 'Digitalisimo SEO', 'digitalisimo-seo/digitalisimo-integrations.php', 'SEO técnico, schema, contenido y SEO AI.' ),
			'digitalisimo-tools' => array( 'DIGITALÍSIMO Tools', 'digitalisimo-tools/digitalisimo-tools.php', 'Herramientas internas, plantillas de red, WebP y ajustes del sitio.' ),
			'digitalisimo-elements' => array( 'DIGITALÍSIMO Elements', 'digitalisimo-elements/pro-elements.php', 'Constructor visual completo: editor, funciones avanzadas, kits, plantillas y widgets propios.' ),
		);
	}

	private static function releases() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) return (array) $cached;
		$index = self::from_manifest();
		// Cada plugin con entrada válida en el índice la usa; sólo si alguno del catálogo queda sin entrada se completa con la API, de respaldo.
		if ( array_diff_key( self::plugins(), $index ) ) $index += self::from_api();
		if ( $index ) {
			update_site_option( self::LAST_KNOWN, $index );
			set_site_transient( self::CACHE_KEY, $index, 10 * MINUTE_IN_SECONDS );
			return $index;
		}
		// GitHub no respondió o limitó las consultas: se conserva lo último encontrado y se reintenta pronto.
		$known = get_site_option( self::LAST_KNOWN, array() );
		$known = is_array( $known ) ? $known : array();
		set_site_transient( self::CACHE_KEY, $known, 2 * MINUTE_IN_SECONDS );
		return $known;
	}

	/** Índice según el archivo de la última Release; cada entrada sólo se acepta si es un ZIP de su propio slug en este repositorio, y una rechazada no invalida las demás. */
	private static function from_manifest() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 10, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Plugin-Catalog' ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array();
			$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) return array();
			set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS );
		}
		$index = array();
		foreach ( $manifest['plugins'] as $slug => $entry ) {
			$slug    = (string) $slug;
			$version = is_array( $entry ) ? (string) ( $entry['version'] ?? '' ) : '';
			$package = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : '';
			if ( ! preg_match( '/^digitalisimo-[a-z]+(?:-[a-z]+)*$/', $slug ) || ! preg_match( '/^\d+(?:\.\d+)+$/', $version ) ) continue;
			if ( ! preg_match( '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . preg_quote( $slug . '-' . $version, '#' ) . '\.zip$#', $package ) ) continue;
			$index[ $slug ] = array( 'version' => $version, 'package' => esc_url_raw( $package ) );
		}
		return $index;
	}

	/** Respaldo por la API de GitHub (sujeta a su límite de consultas): el asset más reciente de cada plugin. */
	private static function from_api() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Plugin-Catalog' ) ) );
		$index = array();
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
			if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
			foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
				if ( ! preg_match( '/^(digitalisimo-[a-z]+(?:-[a-z]+)*)-([0-9.]+)\\.zip$/', $asset['name'] ?? '', $match ) ) continue;
				if ( isset( $index[ $match[1] ] ) && ! version_compare( $match[2], $index[ $match[1] ]['version'], '>' ) ) continue;
				$index[ $match[1] ] = array( 'version' => $match[2], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ) );
			}
		}
		return $index;
	}

	private static function installed_version( $file ) {
		$path = WP_PLUGIN_DIR . '/' . $file;
		if ( ! file_exists( $path ) ) return '';
		$data = get_file_data( $path, array( 'Version' => 'Version' ) );
		return (string) ( $data['Version'] ?? '' );
	}
	private static function can_manage() { return is_multisite() ? current_user_can( 'manage_network_plugins' ) : current_user_can( 'install_plugins' ); }
	private static function action_url( $action, $slug ) { $base = admin_url( 'admin-post.php' ); return wp_nonce_url( add_query_arg( array( 'action' => $action, 'slug' => $slug, 'digitalisimo_context' => is_network_admin() ? 'network' : 'site' ), $base ), $action . '_' . $slug ); }

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) return;
		$releases = self::releases();
		echo '<div class="wrap"><h1>Plugins · Digitalisimo</h1><p>Instala y actualiza módulos publicados sin descargar ni subir ZIPs manualmente.</p><table class="widefat striped"><thead><tr><th>Plugin</th><th>Descripción</th><th>Instalada</th><th>Publicada</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
		foreach ( self::plugins() as $slug => $plugin ) {
			$installed = self::installed_version( $plugin[1] ); $release = $releases[ $slug ] ?? array(); $available = $release['version'] ?? ''; $update = $installed && $available && version_compare( $available, $installed, '>' );
			echo '<tr><td><strong>' . esc_html( $plugin[0] ) . '</strong></td><td>' . esc_html( $plugin[2] ) . '</td><td>' . esc_html( $installed ?: '—' ) . '</td><td>' . esc_html( $available ?: 'Sin release' ) . '</td><td>' . esc_html( ! $installed ? 'Disponible' : ( $update ? 'Actualización disponible' : 'Actualizado' ) ) . '</td><td>';
			if ( ! $installed && ! empty( $release['package'] ) && self::can_manage() ) echo '<a class="button button-primary" href="' . esc_url( self::action_url( self::INSTALL_ACTION, $slug ) ) . '">Instalar</a>';
			elseif ( $update && self::can_manage() ) echo '<a class="button button-primary" href="' . esc_url( self::action_url( self::UPDATE_ACTION, $slug ) ) . '">Actualizar plugin</a>';
			elseif ( ! self::can_manage() && ( ! $installed || $update ) ) echo 'Disponible para administrador'; else echo '—';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function request_plugin() {
		$slug = sanitize_key( $_GET['slug'] ?? '' ); $plugin = self::plugins()[ $slug ] ?? null; $release = self::releases()[ $slug ] ?? null;
		if ( ! $plugin || empty( $release['package'] ) ) wp_die( 'Paquete no disponible.' );
		return array( $plugin, $release );
	}
	public static function install() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::INSTALL_ACTION . '_' . sanitize_key( $_GET['slug'] ?? '' ) ); list( $plugin, $release ) = self::request_plugin();
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; $result = ( new Plugin_Upgrader( new Automatic_Upgrader_Skin() ) )->install( $release['package'] );
		if ( is_wp_error( $result ) || ! $result ) wp_die( 'No se pudo instalar el plugin.' ); self::redirect();
	}
	public static function update() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' ); check_admin_referer( self::UPDATE_ACTION . '_' . sanitize_key( $_GET['slug'] ?? '' ) ); list( $plugin, $release ) = self::request_plugin();
		if ( ! self::installed_version( $plugin[1] ) ) wp_die( 'El plugin no está instalado.' ); require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; require_once ABSPATH . 'wp-admin/includes/file.php';
		$result = ( new Plugin_Upgrader( new Automatic_Upgrader_Skin() ) )->install( $release['package'], array( 'overwrite_package' => true ) ); if ( is_wp_error( $result ) || ! $result ) wp_die( 'No se pudo actualizar el plugin.' ); self::redirect();
	}
	private static function redirect() { wp_safe_redirect( is_multisite() && 'network' === sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ?? '' ) ) ? network_admin_url( 'admin.php?page=digitalisimo-plugins' ) : admin_url( 'admin.php?page=digitalisimo-plugins' ) ); exit; }
}
