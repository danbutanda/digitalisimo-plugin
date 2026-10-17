<?php
/**
 * Actualizaciones de los plugins Digitalisimo que su propio actualizador no atiende.
 *
 * Cada plugin Digitalisimo trae su propio actualizador, pero hay dos casos en que no puede atenderse a sí mismo:
 *
 *  - Inactivo: WordPress sólo ejecuta el código de los plugins activos, así que DIGITALÍSIMO Migración instalado y sin
 *    activar no tenía «Buscar actualizaciones» ni recibía sus versiones nuevas.
 *  - En otra carpeta: hasta Migración 1.0.17, Tools 1.0.31, Backups 1.0.55 y Elements 4.3.0.155 cada actualizador
 *    buscaba su plugin en la carpeta habitual (`digitalisimo-migration/…`). Instalado en otra (un ZIP subido a mano con
 *    otro nombre), su fila sólo decía «Desactivar» y sus versiones nuevas iban a una entrada de un plugin inexistente.
 *
 * DIGITALÍSIMO Elements, siempre activo donde se usan los demás, cubre lo que falte: lee del mismo índice de la Release
 * (`digitalisimo-plugins.json`, con la misma caché compartida) la versión de cada plugin por la clave de su «Update
 * URI», la entrega a WordPress si nadie lo hizo para ese archivo, quita las entradas huérfanas que los actualizadores
 * antiguos dejan con la carpeta habitual, pone «Buscar actualizaciones» en la fila que no lo tiene y, al actualizar un
 * plugin Digitalisimo, deja la copia nueva en la carpeta donde estaba instalado (el ZIP trae la habitual), para que
 * siga activo. Un plugin que se atiende bien a sí mismo no nota nada.
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
	const LINK_TEXT      = 'Buscar actualizaciones';

	/** @var array|null plugins Digitalisimo instalados en esta petición (se vuelve a leer al activar, desactivar o borrar uno). */
	private static $installed = null;

	public static function init() {
		foreach ( array( 'pre_set_site_transient_update_plugins', 'pre_set_transient_update_plugins', 'site_transient_update_plugins', 'transient_update_plugins' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'check' ), 31 );
		}
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'uri_update' ), 31, 4 );
		add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links', array( __CLASS__, 'action_link' ), 10, 4 );
		add_filter( 'network_admin_plugin_action_links', array( __CLASS__, 'action_link' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'keep_folder' ), 11, 4 );
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
		self::$installed = null;
	}

	private static function can_update() {
		return is_multisite() ? current_user_can( 'manage_network_plugins' ) : current_user_can( 'update_plugins' );
	}

	/**
	 * Plugins Digitalisimo instalados (por su «Update URI»), con su clave en el índice y si están activos.
	 *
	 * @return array<string, array{slug:string, version:string, name:string, active:bool}> archivo => datos.
	 */
	public static function installed() {
		if ( null !== self::$installed ) {
			return self::$installed;
		}
		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		self::$installed = array();
		foreach ( (array) get_plugins() as $file => $data ) {
			$uri = (string) ( $data['UpdateURI'] ?? '' );
			if ( 0 !== strpos( $uri, self::PREFIX ) ) {
				continue;
			}
			$slug = substr( $uri, strlen( self::PREFIX ) );
			if ( preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
				self::$installed[ (string) $file ] = array( 'slug' => $slug, 'version' => (string) ( $data['Version'] ?? '' ), 'name' => (string) ( $data['Name'] ?? $slug ), 'active' => is_plugin_active( $file ) );
			}
		}
		return self::$installed;
	}

	/**
	 * Los que este servicio puede atender: todos menos DIGITALÍSIMO Elements (el que lo ejecuta) y las copias inactivas
	 * de un plugin activo en otra carpeta (el aviso de copias duplicadas pide borrarlas).
	 *
	 * @return array<string, array{slug:string, version:string, name:string, active:bool}>
	 */
	public static function siblings() {
		$own    = defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ? plugin_basename( DIGITALISIMO_ELEMENTS_FILE ) : '';
		$active = array();
		foreach ( self::installed() as $info ) {
			if ( $info['active'] ) {
				$active[ $info['slug'] ] = true;
			}
		}
		return array_filter( self::installed(), static function ( $info, $file ) use ( $own, $active ) {
			return self::OWN_SLUG !== $info['slug'] && $file !== $own && ( $info['active'] || ! isset( $active[ $info['slug'] ] ) );
		}, ARRAY_FILTER_USE_BOTH );
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

	/**
	 * En la caché de actualizaciones de WordPress: la versión de cada plugin Digitalisimo que nadie entregó para su
	 * archivo real (corre después de los actualizadores propios), y fuera las entradas huérfanas que un actualizador
	 * antiguo deja con la carpeta habitual cuando el plugin está instalado en otra.
	 */
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
		$installed = self::installed();
		$slugs     = array_flip( array_column( $installed, 'slug' ) );
		foreach ( array( 'response', 'no_update' ) as $list ) {
			foreach ( $transient->{$list} as $file => $item ) {
				$slug = is_object( $item ) ? (string) ( $item->slug ?? '' ) : ( is_array( $item ) ? (string) ( $item['slug'] ?? '' ) : '' );
				if ( isset( $slugs[ $slug ] ) && ! isset( $installed[ $file ] ) ) {
					unset( $transient->{$list}[ $file ], $transient->checked[ $file ] );
				}
			}
		}
		foreach ( self::siblings() as $file => $info ) {
			if ( isset( $transient->response[ $file ] ) || isset( $transient->no_update[ $file ] ) || '' === $info['version'] ) {
				continue;
			}
			$release = self::release( $info['slug'] );
			if ( empty( $release['version'] ) ) {
				continue;
			}
			$transient->checked[ $file ] = $info['version'];
			$pending                     = version_compare( $release['version'], $info['version'], '>' );
			$item                        = (object) array( 'id' => self::PREFIX . $info['slug'], 'slug' => $info['slug'], 'plugin' => $file, 'new_version' => $pending ? $release['version'] : $info['version'], 'url' => $release['url'], 'package' => $pending ? $release['package'] : '', 'requires_php' => '7.4', 'icons' => array(), 'banners' => array(), 'banners_rtl' => array(), 'tested' => '', 'compatibility' => new stdClass() );
			if ( $pending ) {
				$transient->response[ $file ] = $item;
			} else {
				$transient->no_update[ $file ] = $item;
			}
		}
		return $transient;
	}

	/** La consulta de WordPress por «Update URI», si ningún actualizador respondió por ese archivo. */
	public static function uri_update( $update, $plugin_data, $plugin_file, $locales ) {
		$siblings = self::siblings();
		if ( $update || ! isset( $siblings[ $plugin_file ] ) ) {
			return $update;
		}
		$info    = $siblings[ $plugin_file ];
		$release = self::release( $info['slug'] );
		if ( empty( $release['version'] ) || empty( $release['package'] ) || ! version_compare( $release['version'], $info['version'], '>' ) ) {
			return false;
		}
		return array( 'slug' => $info['slug'], 'version' => $release['version'], 'package' => $release['package'], 'url' => $release['url'], 'requires_php' => '7.4', 'autoupdate' => true );
	}

	/** Como sus propios actualizadores: los plugins Digitalisimo se actualizan solos. */
	public static function auto_update( $update, $item ) {
		return isset( $item->plugin ) && isset( self::siblings()[ $item->plugin ] ) ? true : $update;
	}

	/**
	 * «Buscar actualizaciones» en la fila de un plugin Digitalisimo que no lo tenga: el filtro propio de la fila (el de
	 * su actualizador) se consulta antes, y si ya pone el enlace no se añade otro.
	 */
	public static function action_link( $links, $plugin_file, $plugin_data = array(), $context = '' ) {
		if ( ! self::can_update() || ! isset( self::siblings()[ $plugin_file ] ) ) {
			return $links;
		}
		$specific = ( 'network_admin_plugin_action_links' === current_filter() ? 'network_admin_' : '' ) . 'plugin_action_links_' . $plugin_file;
		$own      = has_filter( $specific ) ? (array) apply_filters( $specific, array(), $plugin_file, $plugin_data, $context ) : array();
		foreach ( $own as $link ) {
			if ( false !== strpos( (string) $link, self::LINK_TEXT ) ) {
				return $links;
			}
		}
		$url     = wp_nonce_url( add_query_arg( array( 'action' => self::ACTION, 'plugin' => rawurlencode( $plugin_file ), 'digitalisimo_context' => is_network_admin() ? 'network' : 'site' ), admin_url( 'admin-post.php' ) ), self::ACTION );
		$links   = (array) $links;
		$links[] = '<a href="' . esc_url( $url ) . '">' . self::LINK_TEXT . '</a>';
		return $links;
	}

	/**
	 * Al actualizar un plugin Digitalisimo, el ZIP de la Release trae la carpeta habitual: si el plugin está instalado en
	 * otra, la copia nueva se deja en esa misma carpeta (WordPress borra la antigua y, si la carpeta cambiara, el plugin
	 * quedaría desactivado por no encontrarse su archivo).
	 */
	public static function keep_folder( $source, $remote_source = '', $upgrader = null, $hook_extra = array() ) {
		global $wp_filesystem;
		$plugin = (string) ( $hook_extra['plugin'] ?? '' );
		if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $source ) ) || '' === $plugin || ! isset( self::installed()[ $plugin ] ) ) {
			return $source;
		}
		$folder = dirname( $plugin );
		$trim   = rtrim( (string) $source, '/\\' );
		if ( '.' === $folder || basename( $trim ) === $folder || ! $wp_filesystem || ! $wp_filesystem->exists( $trim . '/' . basename( $plugin ) ) ) {
			return $source;
		}
		$target = dirname( $trim ) . '/' . $folder;
		return $wp_filesystem->move( $trim, $target, true ) ? $target . '/' : $source;
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
			// La entrada de este plugin se vuelve a calcular con el índice nuevo.
			unset( $updates->response[ $plugin ], $updates->no_update[ $plugin ] );
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

	/** Tras actualizar un plugin Digitalisimo, el índice se vuelve a leer. */
	public static function clear_after_upgrade( $upgrader, $options ) {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) && array_intersect( (array) ( $options['plugins'] ?? array() ), array_keys( self::installed() ) ) ) {
			delete_site_transient( self::MANIFEST_CACHE );
			self::forget();
		}
	}
}
