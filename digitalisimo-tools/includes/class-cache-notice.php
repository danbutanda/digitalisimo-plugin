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

	/**
	 * Estado: '' (bien), 'salted' (Tools acaba de agregar la sal a wp-config.php: vale desde la carga siguiente),
	 * 'unsalted' (caché externa sin sal y Tools no pudo agregarla: la línea exacta para copiar, único caso en que se pide
	 * algo a mano) o 'foreign' (lee datos de otro sitio), con el detalle.
	 */
	public static function status() {
		if ( ! class_exists( '\\Digitalisimo_Same_Site', false ) ) return array( 'state' => '', 'message' => '' );
		$salt    = class_exists( __NAMESPACE__ . '\\Cache_Salt', false ) && Cache_Salt::needed() ? Cache_Salt::result() : array();
		// El bloque puesto hace rato y aún sin efecto (wp-config.php no lo alcanza) se trata como «no pudo».
		if ( 'pending' === ( $salt['state'] ?? '' ) && time() - (int) ( $salt['at'] ?? 0 ) > 300 ) $salt = array( 'state' => 'manual', 'message' => 'wp-config.php ya trae el bloque de DIGITALÍSIMO, pero la constante no está en vigor: revisa que esté antes del require de wp-settings.php y fuera de una condición' ) + $salt;
		$written = in_array( $salt['state'] ?? '', array( 'written', 'pending' ), true );
		$manual  = in_array( $salt['state'] ?? '', array( 'manual', 'failed' ), true ) ? ' DIGITALÍSIMO no pudo agregarla solo (' . (string) ( $salt['message'] ?? '' ) . '): copia en ' . (string) ( '' !== (string) ( $salt['file'] ?? '' ) ? $salt['file'] : 'wp-config.php' ) . ', antes de «That\'s all, stop editing», esta línea: ' . (string) ( $salt['line'] ?? '' ) : '';
		$check   = \Digitalisimo_Same_Site::check( false );
		if ( empty( $check['ok'] ) ) {
			if ( $written ) return array( 'state' => 'salted', 'message' => 'La caché de objetos devolvía datos de otro sitio porque no tenía sal propia. DIGITALÍSIMO ya agregó a wp-config.php una sal para este sitio (' . (string) $salt['salt'] . '); desde la siguiente carga lee sólo sus datos. No hace falta hacer nada.' );
			return array( 'state' => 'foreign', 'message' => 'L' . substr( (string) $check['message'], 1 ) . '.' . $manual . ' Mientras tanto, DIGITALÍSIMO no migra, no respalda ni restaura este sitio, para no mezclarlo con el otro.' );
		}
		if ( \Digitalisimo_Same_Site::external() && ! \Digitalisimo_Same_Site::isolated() ) {
			if ( $written ) return array( 'state' => 'salted', 'message' => 'DIGITALÍSIMO agregó a wp-config.php una sal propia para la caché de objetos de este sitio (' . (string) $salt['salt'] . '), para que otro sitio del servidor no lea sus datos. Vale desde la siguiente carga; no hace falta hacer nada. El bloque queda aunque se desinstale Tools, y quitarlo a mano es seguro.' );
			if ( '' !== $manual ) return array( 'state' => 'unsalted', 'message' => 'Este sitio usa una caché de objetos persistente (Redis, Memcached o LSMCD) sin sal propia: si otro sitio del servidor usa la misma, los dos leerán las opciones del otro (dirección, plugins activos, icono).' . $manual );
			return array( 'state' => '', 'message' => '' ); // Tools la agrega en la siguiente carga del escritorio.
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
		$class = 'foreign' === $status['state'] ? 'notice-error' : ( 'salted' === $status['state'] ? 'notice-success' : 'notice-warning' );
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
		} elseif ( 'salted' === $status['state'] ) {
			$result['label'] = 'DIGITALÍSIMO agregó una sal propia a la caché de objetos';
		} elseif ( 'unsalted' === $status['state'] ) {
			$result['label']  = 'La caché de objetos persistente no tiene sal propia';
			$result['status'] = 'recommended';
		}
		if ( '' !== $status['state'] ) $result['description'] = '<p>' . esc_html( $status['message'] ) . '</p>';
		return $result;
	}
}
