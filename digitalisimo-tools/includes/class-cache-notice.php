<?php
namespace Digitalisimo\Tools;
defined( 'ABSPATH' ) || exit;

/**
 * Aviso de la caché de objetos compartida: con una caché persistente (Redis, Memcached, LSMCD) sin sal propia
 * (`WP_CACHE_KEY_SALT` o `WP_REDIS_PREFIX`), dos sitios del mismo servidor pueden leer las claves del otro; y si el
 * candado «mismo sitio» (includes/class-digitalisimo-same-site.php) ve que este WordPress ya está leyendo los datos de
 * otro sitio, lo dice como error. En el Escritorio, en las pantallas Digitalisimo y en Plugins, para quien gestiona el
 * sitio (en una red, la administración de red), y como prueba de «Salud del sitio».
 */
final class Cache_Notice {
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'notice' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
	}

	/** Estado: '' (bien), 'unsalted' (caché externa sin sal) o 'foreign' (lee datos de otro sitio), con el detalle. */
	public static function status() {
		if ( ! class_exists( '\\Digitalisimo_Same_Site', false ) ) return array( 'state' => '', 'message' => '' );
		$check = \Digitalisimo_Same_Site::check( false );
		if ( empty( $check['ok'] ) ) {
			return array( 'state' => 'foreign', 'message' => 'L' . substr( (string) $check['message'], 1 ) . ' (en wp-config.php de cada sitio). Mientras tanto, DIGITALÍSIMO no migra, no respalda ni restaura este sitio, para no mezclarlo con el otro.' );
		}
		if ( \Digitalisimo_Same_Site::external() && ! \Digitalisimo_Same_Site::isolated() ) {
			$host = \Digitalisimo_Same_Site::host( (string) \Digitalisimo_Same_Site::db_option( 'home' ) );
			return array( 'state' => 'unsalted', 'message' => 'Este sitio usa una caché de objetos persistente (Redis, Memcached o LSMCD) sin sal propia. Si otro sitio del servidor usa la misma, los dos leerán las opciones del otro (dirección, plugins activos, icono). Define en wp-config.php una sal para este sitio, por ejemplo define( \'WP_CACHE_KEY_SALT\', \'' . ( '' !== $host ? $host : 'este-sitio' ) . ':\' );, o usa una base de caché distinta para cada sitio.' );
		}
		return array( 'state' => '', 'message' => '' );
	}

	private static function screen_wanted() {
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) return false;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se muestra el aviso.
		return in_array( $id, array( 'dashboard', 'dashboard-network', 'plugins', 'plugins-network' ), true ) || 0 === strpos( $page, 'digitalisimo' );
	}

	public static function notice() {
		if ( ! self::screen_wanted() ) return;
		$status = self::status();
		if ( '' === $status['state'] ) return;
		$class = 'foreign' === $status['state'] ? 'notice-error' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>DIGITALÍSIMO:</strong> ' . esc_html( $status['message'] ) . '</p></div>';
	}

	public static function tests( $tests ) {
		$tests['direct']['digitalisimo_object_cache'] = array( 'label' => 'Caché de objetos propia de este sitio', 'test' => array( __CLASS__, 'health' ) );
		return $tests;
	}

	public static function health() {
		$status = self::status();
		$result = array(
			'label'       => 'La caché de objetos es propia de este sitio',
			'status'      => 'good',
			'badge'       => array( 'label' => 'DIGITALÍSIMO', 'color' => 'blue' ),
			'description' => '<p>' . esc_html( 'Este sitio lee sus propios datos de la caché de objetos.' ) . '</p>',
			'actions'     => '',
			'test'        => 'digitalisimo_object_cache',
		);
		if ( 'foreign' === $status['state'] ) {
			$result['label']  = 'La caché de objetos está devolviendo datos de otro sitio';
			$result['status'] = 'critical';
		} elseif ( 'unsalted' === $status['state'] ) {
			$result['label']  = 'La caché de objetos persistente no tiene sal propia';
			$result['status'] = 'recommended';
		}
		if ( '' !== $status['state'] ) $result['description'] = '<p>' . esc_html( $status['message'] ) . '</p>';
		return $result;
	}
}
