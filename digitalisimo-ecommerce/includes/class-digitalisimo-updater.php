<?php
defined( 'ABSPATH' ) || exit;

/**
 * Actualizador compartido de los módulos Digitalisimo.
 *
 * Se declara una sola vez aunque haya varios módulos activos: cada uno se
 * registra con su archivo principal, su versión y el prefijo del ZIP publicado
 * como asset en las Releases del repositorio.
 *
 * Además de anunciar la versión disponible, habilita la actualización
 * automática de WordPress para los módulos registrados, de modo que se
 * instalen solos sin intervención manual, y añade un enlace «Buscar
 * actualizaciones» que invalida la caché para no tener que esperarla.
 */
if ( ! class_exists( 'Digitalisimo_Updater' ) ) {
	final class Digitalisimo_Updater {
		const REPOSITORY = 'danbutanda/digitalisimo-plugin';
		const CACHE_KEY  = 'digitalisimo_releases_index';
		const CACHE_TTL  = 3600;
		const ACTION     = 'digitalisimo_check_updates';

		/** Módulos registrados, indexados por su archivo principal relativo. */
		private static $modules = array();
		private static $hooked  = false;

		/**
		 * Registra un módulo para su actualización automática.
		 *
		 * @param string $file    Ruta absoluta del archivo principal del plugin.
		 * @param string $version Versión instalada.
		 * @param string $slug    Identificador del módulo y prefijo del ZIP.
		 */
		public static function register( $file, $version, $slug ) {
			$basename = plugin_basename( $file );
			self::$modules[ $basename ] = array( 'version' => $version, 'slug' => $slug );
			// El enlace se registra por módulo; el resto de hooks es compartido.
			add_filter( 'plugin_action_links_' . $basename, array( __CLASS__, 'action_link' ) );
			add_filter( 'network_admin_plugin_action_links_' . $basename, array( __CLASS__, 'action_link' ) );
			if ( self::$hooked ) return;
			self::$hooked = true;
			// WordPress Multisite guarda esta información como site transient; WordPress
			// individual puede usar el transient normal. Se cubren lectura y escritura
			// para que una caché creada antes de cargar los módulos no oculte actualizaciones.
			add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject' ) );
			add_filter( 'pre_set_transient_update_plugins', array( __CLASS__, 'inject' ) );
			add_filter( 'site_transient_update_plugins', array( __CLASS__, 'inject' ) );
			add_filter( 'transient_update_plugins', array( __CLASS__, 'inject' ) );
			add_filter( 'auto_update_plugin', array( __CLASS__, 'auto_update' ), 10, 2 );
			add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'clear' ), 10, 2 );
			add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'force_check' ) );
			add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
			add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		}

		/** Enlace «Buscar actualizaciones» en la fila del plugin. */
		public static function action_link( $links ) {
			if ( ! current_user_can( 'update_plugins' ) ) return $links;
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Buscar actualizaciones', 'digitalisimo' ) . '</a>';
			return $links;
		}

		/**
		 * Comprobación manual: sin esto habría que esperar a que expire la caché,
		 * porque «Volver a comprobar» de WordPress no la invalida.
		 */
		public static function force_check() {
			if ( ! current_user_can( 'update_plugins' ) ) wp_die( esc_html__( 'No autorizado.', 'digitalisimo' ) );
			check_admin_referer( self::ACTION );
			delete_site_transient( self::CACHE_KEY );
			delete_transient( self::CACHE_KEY );
			delete_site_transient( 'update_plugins' );
			delete_transient( 'update_plugins' );
			wp_update_plugins();
			$back = wp_get_referer();
			wp_safe_redirect( add_query_arg( 'digitalisimo-checked', '1', $back ? $back : admin_url( 'plugins.php' ) ) );
			exit;
		}

		/** Resultado de la comprobación manual. */
		public static function notice() {
			if ( empty( $_GET['digitalisimo-checked'] ) || ! current_user_can( 'update_plugins' ) ) return;
			$pending = array();
			foreach ( self::$modules as $file => $module ) {
				$release = self::pending( $file );
				if ( $release ) $pending[] = $module['slug'] . ' ' . $release['version'];
			}
			$message = $pending
				? sprintf( __( 'Digitalisimo: actualizaciones disponibles · %s', 'digitalisimo' ), implode( ', ', $pending ) )
				: __( 'Digitalisimo está al día: no hay versiones nuevas publicadas.', 'digitalisimo' );
			echo '<div class="notice notice-' . ( $pending ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}

		/** Índice de versiones publicadas, por slug de módulo. */
		private static function releases() {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( false !== $cached ) return (array) $cached;

			$response = wp_remote_get(
				'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=50',
				array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Digitalisimo-WordPress-Updater' ) )
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				// Reintento corto ante fallos de red: un error puntual no debe bloquear la comprobación.
				set_site_transient( self::CACHE_KEY, array(), 5 * MINUTE_IN_SECONDS );
				return array();
			}

			$index = array();
			foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $release ) {
				if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) continue;
				foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
					if ( ! preg_match( '/^(digitalisimo-[a-z]+(?:-[a-z]+)*)-([0-9][0-9.]*)\.zip$/', (string) ( $asset['name'] ?? '' ), $match ) ) continue;
					$slug = $match[1];
					if ( isset( $index[ $slug ]['version'] ) && ! version_compare( $match[2], $index[ $slug ]['version'], '>' ) ) continue;
					$index[ $slug ] = array(
						'version'  => $match[2],
						'package'  => esc_url_raw( $asset['browser_download_url'] ?? '' ),
						'url'      => esc_url_raw( $release['html_url'] ?? 'https://github.com/' . self::REPOSITORY . '/releases' ),
						'notes'    => wp_kses_post( $release['body'] ?? '' ),
					);
				}
			}
			set_site_transient( self::CACHE_KEY, $index, $index ? self::CACHE_TTL : HOUR_IN_SECONDS );
			return $index;
		}

		/** Versión publicada que supera a la instalada, o array vacío. */
		private static function pending( $file ) {
			$module = self::$modules[ $file ] ?? array();
			if ( ! $module ) return array();
			$release = self::releases()[ $module['slug'] ] ?? array();
			if ( empty( $release['version'] ) || empty( $release['package'] ) ) return array();
			return version_compare( $release['version'], $module['version'], '>' ) ? $release : array();
		}

		public static function inject( $transient ) {
			// La primera lectura de WordPress puede no traer "checked" todavía.
			// No se debe perder la actualización publicada por esa condición.
			if ( ! is_object( $transient ) ) $transient = new stdClass();
			if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) $transient->checked = array();
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) $transient->response = array();
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) $transient->no_update = array();
			foreach ( self::$modules as $file => $module ) {
				if ( ! isset( $transient->checked[ $file ] ) ) $transient->checked[ $file ] = $module['version'];
				$release = self::pending( $file );
				if ( ! $release ) continue;
				$transient->response[ $file ] = (object) array(
					'slug'         => $module['slug'],
					'plugin'       => $file,
					'new_version'  => $release['version'],
					'url'          => $release['url'],
					'package'      => $release['package'],
					'tested'       => get_bloginfo( 'version' ),
					'requires'     => '6.0',
					'requires_php' => '7.4',
				);
			}
			return $transient;
		}

		/**
		 * Activa la instalación automática de los módulos Digitalisimo.
		 *
		 * Puede desactivarse por sitio con el filtro digitalisimo_auto_update.
		 */
		public static function auto_update( $update, $item ) {
			$file = is_object( $item ) ? ( $item->plugin ?? '' ) : '';
			if ( ! $file || ! isset( self::$modules[ $file ] ) ) return $update;
			return apply_filters( 'digitalisimo_auto_update', true, $file );
		}

		public static function info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) ) return $result;
			foreach ( self::$modules as $file => $module ) {
				if ( $module['slug'] !== $args->slug ) continue;
				$release = self::releases()[ $module['slug'] ] ?? array();
				return (object) array(
					'name'          => $module['slug'],
					'slug'          => $module['slug'],
					'version'       => $release['version'] ?? $module['version'],
					'author'        => 'Digitalísimo',
					'homepage'      => 'https://github.com/' . self::REPOSITORY,
					'download_link' => $release['package'] ?? '',
					'requires'      => '6.0',
					'requires_php'  => '7.4',
					'sections'      => array( 'changelog' => $release['notes'] ?? 'Sin notas de versión.' ),
				);
			}
			return $result;
		}

		/** Fuerza una comprobación nueva tras instalar cualquier actualización. */
		public static function clear( $upgrader, $options ) {
			if ( 'update' !== ( $options['action'] ?? '' ) || 'plugin' !== ( $options['type'] ?? '' ) ) return;
			delete_site_transient( self::CACHE_KEY );
		}
	}
}
