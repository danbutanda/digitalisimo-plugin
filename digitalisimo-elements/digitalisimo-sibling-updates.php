<?php
/**
 * Actualizaciones de los plugins Digitalisimo instalados pero inactivos.
 *
 * Cada plugin Digitalisimo trae su propio actualizador, pero WordPress sólo ejecuta el código de los plugins activos:
 * uno instalado y sin activar (DIGITALÍSIMO Migración antes de usarse, por ejemplo) no tenía «Buscar actualizaciones»
 * ni recibía sus versiones nuevas. DIGITALÍSIMO Elements, siempre activo donde se usan los demás, se encarga de ellos
 * mientras estén inactivos: lee del mismo índice de la Release (`digitalisimo-plugins.json`, con la misma caché
 * compartida) la versión de cada uno, por la clave que da su «Update URI», y la entrega a WordPress como haría su propio
 * actualizador. En cuanto un plugin se activa, su actualizador vuelve a encargarse y éste lo deja.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Digitalisimo_Elements_Sibling_Updates {
	const PREFIX         = 'https://github.com/danbutanda/digitalisimo-plugin/';
	const REPOSITORY     = 'danbutanda/digitalisimo-plugin';
	const ACTION         = 'digitalisimo_elements_check_sibling';
	const MANIFEST_URL   = 'https://github.com/danbutanda/digitalisimo-plugin/releases/latest/download/digitalisimo-plugins.json';
	const MANIFEST_CACHE = 'digitalisimo_plugins_manifest';
	const OWN_SLUG       = 'digitalisimo-elements';

	/** @var array|null plugins inactivos de esta petición (se vuelve a leer al activar o desactivar uno). */
	private static $siblings = null;

	public static function init() {
		foreach ( array( 'pre_set_site_transient_update_plugins', 'pre_set_transient_update_plugins', 'site_transient_update_plugins', 'transient_update_plugins' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'check' ), 31 );
		}
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'uri_update' ), 31, 4 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links', array( __CLASS__, 'action_link' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( __CLASS__, 'action_link' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'force_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_inline_update' ), 11 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_after_upgrade' ), 10, 2 );
		foreach ( array( 'activated_plugin', 'deactivated_plugin', 'deleted_plugin' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'forget' ) );
		}
	}

	public static function forget() {
		self::$siblings = null;
	}

	private static function can_update() {
		return is_multisite() ? current_user_can( 'manage_network_plugins' ) : current_user_can( 'update_plugins' );
	}

	/**
	 * Plugins Digitalisimo instalados e inactivos en esta petición (los activos tienen su propio actualizador cargado),
	 * salvo DIGITALÍSIMO Elements y las copias inactivas de un plugin que ya está activo en otra carpeta.
	 *
	 * @return array<string, array{slug:string, version:string, name:string}> archivo => datos.
	 */
	public static function siblings() {
		if ( null !== self::$siblings ) {
			return self::$siblings;
		}
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$own      = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
		$active   = array();
		$inactive = array();
		foreach ( (array) get_plugins() as $file => $data ) {
			$uri = (string) ( $data['UpdateURI'] ?? '' );
			if ( 0 !== strpos( $uri, self::PREFIX ) ) {
				continue;
			}
			$slug = substr( $uri, strlen( self::PREFIX ) );
			if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) || self::OWN_SLUG === $slug || $file === $own ) {
				continue;
			}
			if ( is_plugin_active( $file ) ) {
				$active[ $slug ] = true;
				continue;
			}
			$inactive[ (string) $file ] = array( 'slug' => $slug, 'version' => (string) ( $data['Version'] ?? '' ), 'name' => (string) ( $data['Name'] ?? $slug ) );
		}
		self::$siblings = array_filter( $inactive, static function ( $info ) use ( $active ) {
			return ! isset( $active[ $info['slug'] ] );
		} );
		return self::$siblings;
	}

	/** Versión y ZIP de un plugin en el índice de la última Release; sólo acepta un ZIP suyo de este repositorio. */
	public static function release( $slug ) {
		$manifest = get_site_transient( self::MANIFEST_CACHE );
		if ( ! is_array( $manifest ) ) {
			$response = wp_remote_get( self::MANIFEST_URL, array( 'timeout' => 10, 'redirection' => 5, 'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Digitalisimo-Elements' ) ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return array();
			}
			$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $manifest ) || empty( $manifest['plugins'] ) || ! is_array( $manifest['plugins'] ) ) {
				return array();
			}
			set_site_transient( self::MANIFEST_CACHE, $manifest, 10 * MINUTE_IN_SECONDS );
		}
		$entry   = $manifest['plugins'][ $slug ] ?? null;
		$version = is_array( $entry ) ? (string) ( $entry['version'] ?? '' ) : '';
		$package = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : '';
		if ( ! preg_match( '/^\d+(?:\.\d+)+$/', $version ) || ! preg_match( '#^https://github\.com/' . preg_quote( self::REPOSITORY, '#' ) . '/releases/download/[A-Za-z0-9._-]+/' . preg_quote( $slug, '#' ) . '-' . preg_quote( $version, '#' ) . '\.zip$#', $package ) ) {
			return array();
		}
		$release_url = (string) ( $manifest['release_url'] ?? '' );
		if ( 0 !== strpos( $release_url, 'https://github.com/' . self::REPOSITORY . '/releases' ) ) {
			$release_url = 'https://github.com/' . self::REPOSITORY . '/releases';
		}
		return array( 'version' => $version, 'package' => esc_url_raw( $package ), 'url' => esc_url_raw( $release_url ) );
	}

	/** Las versiones nuevas de los plugins inactivos, en la caché de actualizaciones de WordPress. */
	public static function check( $transient ) {
		if ( ! is_object( $transient ) || ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}
		foreach ( self::siblings() as $file => $info ) {
			$release = self::release( $info['slug'] );
			if ( empty( $release['version'] ) || '' === $info['version'] ) {
				continue;
			}
			$transient->checked[ $file ] = $info['version'];
			$pending                     = version_compare( $release['version'], $info['version'], '>' );
			$item                        = (object) array( 'id' => self::PREFIX . $info['slug'], 'slug' => $info['slug'], 'plugin' => $file, 'new_version' => $pending ? $release['version'] : $info['version'], 'url' => $release['url'], 'package' => $pending ? $release['package'] : '', 'requires_php' => '7.4', 'icons' => array(), 'banners' => array(), 'banners_rtl' => array(), 'tested' => '', 'compatibility' => new stdClass() );
			if ( $pending ) {
				unset( $transient->no_update[ $file ] );
				$transient->response[ $file ] = $item;
			} else {
				unset( $transient->response[ $file ] );
				$transient->no_update[ $file ] = $item;
			}
		}
		return $transient;
	}

	/** La consulta de WordPress por «Update URI» para un plugin inactivo. */
	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		$siblings = self::siblings();
		if ( ! isset( $siblings[ $plugin_file ] ) ) {
			return $update;
		}
		$info    = $siblings[ $plugin_file ];
		$release = self::release( $info['slug'] );
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], $info['version'], '>' ) ) {
			return false;
		}
		return array( 'slug' => $info['slug'], 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4', 'autoupdate' => true );
	}

	/** Como su propio actualizador: los plugins Digitalisimo se actualizan solos. */
	public static function auto_update( $update, $item ) {
		return isset( $item->plugin ) && isset( self::siblings()[ $item->plugin ] ) ? true : $update;
	}

	/** «Buscar actualizaciones» en la fila de cada plugin Digitalisimo inactivo. */
	public static function action_link( $links, $plugin_file ) {
		if ( ! self::can_update() || ! isset( self::siblings()[ $plugin_file ] ) ) {
			return $links;
		}
		$url     = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'plugin' => rawurlencode( $plugin_file ), 'digitalisimo_context' => is_network_admin() ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), self::ACTION );
		$links   = (array) $links;
		$links[] = '<a href="' . esc_url( $url ) . '">Buscar actualizaciones</a>';
		return $links;
	}

	public static function force_check() {
		if ( ! self::can_update() ) {
			wp_die( 'No autorizado.' );
		}
		check_admin_referer( self::ACTION );
		$plugin = sanitize_text_field( rawurldecode( wp_unslash( $_GET['plugin'] ?? '' ) ) );
		delete_site_transient( self::MANIFEST_CACHE );
		$updates = get_site_transient( 'update_plugins' );
		if ( is_object( $updates ) && isset( $updates->checked ) && is_array( $updates->checked ) ) {
			set_site_transient( 'update_plugins', self::check( $updates ) );
		} else {
			wp_update_plugins();
		}
		$context = sanitize_key( wp_unslash( $_GET['digitalisimo_context'] ?? '' ) );
		$default = is_multisite() && 'network' === $context ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' );
		$back    = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'digitalisimo-sibling-checked', rawurlencode( $plugin ), $back ? $back : $default ) );
		exit;
	}

	public static function notice() {
		if ( empty( $_GET['digitalisimo-sibling-checked'] ) || ! self::can_update() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo muestra el resultado.
			return;
		}
		$plugin   = sanitize_text_field( rawurldecode( wp_unslash( $_GET['digitalisimo-sibling-checked'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$siblings = self::siblings();
		if ( ! isset( $siblings[ $plugin ] ) ) {
			return;
		}
		$info    = $siblings[ $plugin ];
		$release = self::release( $info['slug'] );
		$pending = ! empty( $release['version'] ) && version_compare( $release['version'], $info['version'], '>' );
		$message = $pending ? sprintf( '%s %s está disponible para actualizar.', $info['name'], $release['version'] ) : ( empty( $release['version'] ) ? sprintf( 'No se pudo consultar la versión de %s: inténtalo de nuevo en unos minutos.', $info['name'] ) : sprintf( '%s está al día.', $info['name'] ) );
		echo '<div class="notice notice-' . ( $pending ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/** La actualización en la propia fila (el mismo script que usan todos los plugins Digitalisimo). */
	public static function enqueue_inline_update( $hook ) {
		if ( 'plugins.php' !== $hook || ! self::can_update() || ! wp_script_is( 'digitalisimo-inline-plugin-update', 'enqueued' ) ) {
			return;
		}
		$files = array_keys( self::siblings() );
		if ( $files ) {
			wp_add_inline_script( 'digitalisimo-inline-plugin-update', 'window.digitalisimoPluginUpdateFiles=window.digitalisimoPluginUpdateFiles||{};' . implode( '', array_map( static function ( $file ) {
				return 'window.digitalisimoPluginUpdateFiles[' . wp_json_encode( $file ) . ']=true;';
			}, $files ) ), 'before' );
		}
	}

	/** Tras actualizar un plugin Digitalisimo inactivo, el índice se vuelve a leer. */
	public static function clear_after_upgrade( $upgrader, $options ) {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) && array_intersect( (array) ( $options['plugins'] ?? array() ), array_keys( self::siblings() ) ) ) {
			delete_site_transient( self::MANIFEST_CACHE );
		}
	}
}
