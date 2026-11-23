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
	 * Estado: '' (bien), 'salted' (DIGITALÍSIMO agregó la sal: se dice durante un día, también si la agregó Migrar),
	 * 'unsalted' (caché externa sin aislar y sin forma de hacerlo sola: lo que falta, único caso en que se pide algo a
	 * mano) o 'foreign' (lee datos de otro sitio), con el detalle.
	 */
	public static function status() {
		if ( ! class_exists( '\\Digitalisimo_Same_Site', false ) ) return array( 'state' => '', 'message' => '' );
		$salt  = class_exists( '\\Digitalisimo_Cache_Salt', false ) ? \Digitalisimo_Cache_Salt::result() : array();
		$state = (string) ( $salt['state'] ?? '' );
		$file  = basename( (string) ( $salt['file'] ?? '' ) );
		$file  = '' !== $file ? $file : 'wp-config.php';
		$where = 'dropin' === ( $salt['target'] ?? '' ) ? 'al principio de wp-content/object-cache.php (wp-config.php no se puede escribir; si el plugin de caché reescribe ese archivo, DIGITALÍSIMO la vuelve a poner en cuanto lo ve: en el escritorio, en el cron o al terminar la actualización)' : 'a ' . $file;
		// Escrita hace rato y aún sin efecto: se dice por qué, sin pedir que se copie lo que ya está en el archivo.
		if ( 'pending' === $state && time() - (int) ( $salt['at'] ?? 0 ) > 300 ) {
			$frozen = function_exists( 'ini_get' ) && ini_get( 'opcache.enable' ) && ! ini_get( 'opcache.validate_timestamps' );
			$state  = 'stuck';
			$salt['message'] = $frozen ? 'PHP guarda en memoria una copia de ' . $file . ' (opcache.validate_timestamps=0): la sal entra en vigor cuando se reinicie PHP' : $file . ' ya trae el bloque de DIGITALÍSIMO, pero la constante no está en vigor: algo la define antes o el bloque no llega a ejecutarse';
		}
		$manual = '';
		if ( in_array( $state, array( 'manual', 'failed' ), true ) && '' !== (string) ( $salt['line'] ?? '' ) ) {
			$manual = ' DIGITALÍSIMO no pudo agregarla solo (' . (string) ( $salt['message'] ?? '' ) . '): copia en wp-config.php, antes de «That\'s all, stop editing», esta línea: ' . (string) $salt['line'];
		} elseif ( in_array( $state, array( 'manual', 'failed', 'stuck', 'ignored' ), true ) ) {
			$manual = ' ' . ucfirst( (string) ( $salt['message'] ?? '' ) ) . '.';
		}
		$kept   = ' El bloque queda aunque se desinstale DIGITALÍSIMO Tools; si alguien lo quita, hay que vaciar la caché de objetos de este sitio (con DIGITALÍSIMO activo, lo hace solo y vuelve a ponerla).';
		$check  = \Digitalisimo_Same_Site::check( false );
		$fresh  = in_array( $state, array( 'written', 'pending' ), true );
		if ( empty( $check['ok'] ) ) {
			if ( $fresh ) return array( 'state' => 'salted', 'message' => 'La caché de objetos devolvía datos de otro sitio porque no tenía sal propia. DIGITALÍSIMO ya agregó una sal para este sitio (' . (string) $salt['salt'] . ') ' . $where . '; desde la siguiente carga lee sólo sus datos. No hace falta hacer nada.' . $kept );
			return array( 'state' => 'foreign', 'message' => 'L' . substr( (string) $check['message'], 1 ) . '.' . $manual . ( '' !== $manual ? ' Mientras tanto, Migrar trabaja leyendo directo de la base y sin vaciar la caché compartida, y Backups no respalda ni restaura este sitio, para no mezclarlo con el otro.' : ' Mientras tanto, DIGITALÍSIMO no respalda ni restaura este sitio, para no mezclarlo con el otro.' ) );
		}
		if ( $fresh ) return array( 'state' => 'salted', 'message' => 'DIGITALÍSIMO agregó una sal propia para la caché de objetos de este sitio (' . (string) $salt['salt'] . ') ' . $where . ', para que otro sitio del servidor no lea sus datos. Vale desde la siguiente carga; no hace falta hacer nada.' . $kept );
		if ( '' !== $manual && \Digitalisimo_Same_Site::external() && ! ( class_exists( '\\Digitalisimo_Cache_Salt', false ) ? \Digitalisimo_Cache_Salt::isolated() : \Digitalisimo_Same_Site::isolated() ) ) {
			return array( 'state' => 'unsalted', 'message' => 'Este sitio usa una caché de objetos persistente (Redis, Memcached o LSMCD) que no separa sus claves de las de otros sitios del servidor: si otro sitio usa la misma, los dos leerán las opciones del otro (dirección, plugins activos, icono).' . $manual );
		}
		if ( 'active' === $state && time() - (int) ( $salt['done_at'] ?? 0 ) < \Digitalisimo_Cache_Salt::SHOW ) {
			return array( 'state' => 'salted', 'message' => 'DIGITALÍSIMO agregó una sal propia para la caché de objetos de este sitio (' . (string) $salt['salt'] . ') ' . $where . ' y ya está en vigor: otro sitio del servidor no lee sus datos. No hace falta hacer nada.' . $kept );
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
		// Con la X y el cierre recordado (queja del propietario del 2026-11-22: «no poder quitarlas»): «salted» queda
		// cerrado mientras diga lo mismo, «unsalted» vuelve a los 7 días y «foreign» (otro sitio) al día si sigue. Lo
		// importante queda siempre en «Salud del sitio» (health()).
		// La huella de «salted» es la sal: «vale desde la siguiente carga» y «ya está en vigor» son la misma noticia y,
		// cerrada una, la otra no vuelve a salir; otra sal (rotada o repuesta) sí.
		$type = 'foreign' === $status['state'] ? 'error' : ( 'salted' === $status['state'] ? 'success' : 'warning' );
		$salt = 'salted' === $status['state'] && class_exists( '\\Digitalisimo_Cache_Salt', false ) ? (string) ( \Digitalisimo_Cache_Salt::result()['salt'] ?? '' ) : '';
		\Digitalisimo_Notices::show( 'tools-cache', $type, '<p><strong>DIGITALÍSIMO:</strong> ' . esc_html( $status['message'] ) . '</p>', array( 'hash' => array( $status['state'], '' !== $salt ? $salt : $status['message'] ), 'cap' => is_multisite() ? 'manage_network_options' : 'manage_options' ) );
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
