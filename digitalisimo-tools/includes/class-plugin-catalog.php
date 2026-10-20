<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/** Catálogo instalable sin exponer al administrador al flujo de GitHub. */
final class Plugin_Catalog {
	const ACTION = 'digitalisimo_tools_install_plugin';
	const CACHE = 'digitalisimo_tools_catalog_releases';
	const REPOSITORY = 'danbutanda/digitalisimo-plugin';
	/** Índice de versiones adjunto a cada Release (el ZIP vigente de los siete plugins): se descarga como un archivo, sin el límite de 60 consultas por hora de la API de GitHub que los plugins Digitalisimo agotan entre todos. */
	const MANIFEST_URL = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	/** Caché compartida del índice: un solo archivo sirve a todos los plugins que lo lean. */
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';
	/** Último índice encontrado: si GitHub no responde, el catálogo sigue ofreciendo lo último publicado en lugar de vaciarse. */
	const LAST_KNOWN = 'digitalisimo_tools_catalog_last_known';
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 99 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'install' ) );
		// «Buscar actualizaciones» de cualquier plugin Digitalisimo (y la limpieza tras actualizar) borra el índice compartido: el catálogo se rehace entonces con el índice nuevo.
		add_action( 'delete_site_transient_' . self::MANIFEST_CACHE, array( __CLASS__, 'clear' ) );
	}
	/** Vacía la caché propia; el índice compartido lo vacía quien lo descarga de nuevo. */
	public static function clear() { delete_site_transient( self::CACHE ); }
	private static function can_manage() { return is_multisite() ? current_user_can( 'manage_network_plugins' ) : current_user_can( 'install_plugins' ); }
	/** El menú Digitalisimo lo crea el primer módulo que lo registra (el núcleo de SEO, las herramientas de Tools…): sólo se añade si aún no existe. */
	private static function ensure_parent( $slug, $capability ) {
		if ( empty( $GLOBALS['admin_page_hooks'][ $slug ] ) ) add_menu_page( 'Digitalisimo', 'Digitalisimo', $capability, $slug, array( __CLASS__, 'page' ), 'dashicons-chart-line', 56 );
	}
	public static function menu() {
		if ( class_exists( 'Digitalisimo_Plugin_Catalog' ) ) return;
		self::ensure_parent( 'digitalisimo', 'manage_options' );
		add_submenu_page( 'digitalisimo', 'Plugins · Digitalisimo', 'Plugins', 'install_plugins', 'digitalisimo-plugins', array( __CLASS__, 'page' ) );
	}
	public static function network_menu() {
		if ( class_exists( 'Digitalisimo_Plugin_Catalog' ) ) return;
		self::ensure_parent( 'digitalisimo-network', 'manage_network_options' );
		add_submenu_page( 'digitalisimo-network', 'Plugins · Digitalisimo', 'Plugins', 'manage_network_plugins', 'digitalisimo-plugins', array( __CLASS__, 'page' ) );
	}
	private static function catalog() { return array(
		'digitalisimo-seo' => array( 'name' => 'DIGITALÍSIMO SEO', 'file' => 'digitalisimo-seo/digitalisimo-integrations.php', 'description' => 'SEO técnico, schema, contenido y SEO AI.' ),
		'digitalisimo-ia-tools' => array( 'name' => 'DIGITALÍSIMO IA Tools', 'file' => 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'description' => 'Chatbot, proveedores IA, RAG y generación de imágenes.' ),
		'digitalisimo-hosting' => array( 'name' => 'DIGITALÍSIMO Hosting', 'file' => 'digitalisimo-hosting/digitalisimo-hosting.php', 'description' => 'Dominios, Namecheap, WooCommerce y hosting.' ),
		'digitalisimo-backups' => array( 'name' => 'DIGITALÍSIMO Backups', 'file' => 'digitalisimo-backups/digitalisimo-backups.php', 'description' => 'Respaldos de sitio y red.' ),
		'digitalisimo-tools' => array( 'name' => 'DIGITALÍSIMO Tools', 'file' => 'digitalisimo-tools/digitalisimo-tools.php', 'description' => 'Herramientas internas, plantillas de red, WebP, ajustes del sitio y código PHP propio.' ),
		'digitalisimo-elements' => array( 'name' => 'DIGITALÍSIMO Elements', 'file' => 'digitalisimo-elements/pro-elements.php', 'description' => 'Constructor visual completo: editor, funciones avanzadas, kits, plantillas y widgets propios.' ),
	); }
	private static function releases() {
		$cached = get_site_transient( self::CACHE ); if ( false !== $cached ) return (array) $cached;
		// Cada plugin con entrada válida en el índice la usa; sólo si alguno del catálogo queda sin entrada se completa con la API, de respaldo.
		$index = self::from_manifest(); if ( array_diff_key( self::catalog(), $index ) ) $index += self::from_api();
		if ( $index ) { update_site_option( self::LAST_KNOWN, $index ); set_site_transient( self::CACHE, $index, 10 * MINUTE_IN_SECONDS ); return $index; }
		// GitHub no respondió o limitó las consultas: se conserva lo último encontrado y se reintenta pronto.
		$known = get_site_option( self::LAST_KNOWN, array() ); $known = is_array( $known ) ? $known : array(); set_site_transient( self::CACHE, $known, 2 * MINUTE_IN_SECONDS ); return $known;
	}
	/** Índice según el archivo de la última Release; cada entrada sólo se acepta si es un ZIP de su propio slug en este repositorio, y una rechazada no invalida las demás. */
	private static function from_manifest() {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) { $response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 12, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Plugin-Catalog' ) ) ); if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array(); $manifest = json_decode( wp_remote_retrieve_body( $response ), true ); if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) return array(); set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS ); }
		$index = array(); foreach ( $manifest['plugins'] as $slug => $entry ) { $slug = (string) $slug; $version = is_array( $entry ) ? (string) ( $entry['version'] ?? '' ) : ''; $package = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : ''; if ( preg_match( '/^digitalisimo-[a-z]+(?:-[a-z]+)*$/', $slug ) && preg_match( '/^\d+(?:\.\d+)+$/', $version ) && preg_match( '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . preg_quote( $slug . '-' . $version, '#' ) . '\.zip$#', $package ) ) $index[ $slug ] = array( 'version' => $version, 'package' => esc_url_raw( $package ) ); }
		return $index;
	}
	/** Respaldo por la API de GitHub (sujeta a su límite de consultas): el asset más reciente de cada plugin. */
	private static function from_api() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50', array( 'timeout' => 12, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-Plugin-Catalog' ) ) );
		$index = array(); if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) { if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue; foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) if ( preg_match( '/^(digitalisimo-[a-z]+(?:-[a-z]+)*)-([0-9.]+)\.zip$/', $asset['name'] ?? '', $match ) && ( empty( $index[ $match[1] ] ) || version_compare( $match[2], $index[ $match[1] ]['version'], '>' ) ) ) $index[ $match[1] ] = array( 'version' => $match[2], 'package' => esc_url_raw( $asset['browser_download_url'] ?? '' ) ); }
		return $index;
	}
	public static function page() {
		if ( ! self::can_manage() ) return; $releases = self::releases(); echo '<div class="wrap"><h1>Plugins · Digitalisimo</h1><p>Instala módulos publicados de Digitalisimo sin descargar ni subir ZIPs manualmente.</p><table class="widefat striped"><thead><tr><th>Plugin</th><th>Descripción</th><th>Versión publicada</th><th>Estado</th><th>Acción</th></tr></thead><tbody>';
		foreach ( self::catalog() as $slug => $plugin ) { $installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin['file'] ); $release = $releases[ $slug ] ?? array(); echo '<tr><td><strong>' . esc_html( $plugin['name'] ) . '</strong></td><td>' . esc_html( $plugin['description'] ) . '</td><td>' . esc_html( $release['version'] ?? 'Sin Release publicada' ) . '</td><td>' . ( $installed ? 'Instalado' : 'Disponible' ) . '</td><td>'; if ( ! $installed && ! empty( $release['package'] ) ) { $url = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'slug' => $slug, 'digitalisimo_context' => is_network_admin() ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), self::ACTION . '_' . $slug ); echo '<a class="button button-primary" href="' . esc_url( $url ) . '">Instalar</a>'; } elseif ( $installed ) echo '<a class="button" href="' . esc_url( is_network_admin() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ) . '">Ver plugin</a>'; else echo 'Pendiente de publicar'; echo '</td></tr>'; }
		echo '</tbody></table></div>';
	}
	public static function install() {
		$slug = sanitize_key( $_GET['slug'] ?? '' ); if ( ! self::can_manage() ) wp_die( esc_html__( 'No autorizado.', 'digitalisimo-tools' ) ); check_admin_referer( self::ACTION . '_' . $slug ); $plugin = self::catalog()[ $slug ] ?? null; $release = self::releases()[ $slug ] ?? null; if ( ! $plugin || empty( $release['package'] ) ) wp_die( esc_html__( 'El paquete solicitado no está disponible.', 'digitalisimo-tools' ) );
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; $upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() ); $result = $upgrader->install( $release['package'] ); if ( is_wp_error( $result ) || ! $result ) wp_die( esc_html__( 'No se pudo instalar el plugin.', 'digitalisimo-tools' ) ); wp_safe_redirect( is_multisite() && 'network' === sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ?? '' ) ) ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ); exit;
	}
}
