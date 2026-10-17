<?php
/**
 * Los actualizadores de Elements, Migración, Tools y Backups atienden su plugin en la carpeta donde está instalado: un
 * ZIP subido a mano con otro nombre de carpeta conserva «Buscar actualizaciones» y recibe sus versiones nuevas con su
 * archivo real, y al actualizar la copia nueva se deja en esa misma carpeta.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'WP_PLUGIN_DIR', '/wp/plugins' );
	$GLOBALS['t'] = array( 'filters' => array(), 'cache' => array(), 'fs' => array(), 'moves' => array() );
	$plugins = array(
		'Digitalisimo_Elements_Updater'   => array( 'digitalisimo-elements/digitalisimo-updater.php', 'DIGITALISIMO_ELEMENTS', 'digitalisimo-elements', 'pro-elements.php', '4.3.0.1' ),
		'Digitalisimo\\Migration\\Updater' => array( 'digitalisimo-migration/includes/class-updater.php', 'DIGITALISIMO_MIGRATION', 'digitalisimo-migration', 'digitalisimo-migration.php', '1.0.1' ),
		'Digitalisimo\\Tools\\Updater'     => array( 'digitalisimo-tools/includes/class-updater.php', 'DIGITALISIMO_TOOLS', 'digitalisimo-tools', 'digitalisimo-tools.php', '1.0.1' ),
		'Digitalisimo_Backups_Updater'    => array( 'digitalisimo-backups/includes/class-digitalisimo-updater.php', 'DIGITALISIMO_BACKUPS', 'digitalisimo-backups', 'digitalisimo-backups.php', '1.0.1' ),
	);
	foreach ( $plugins as $class => $spec ) {
		// Cada plugin, instalado en una carpeta con otro nombre.
		define( $spec[1] . '_FILE', WP_PLUGIN_DIR . '/' . $spec[2] . '-subido/' . $spec[3] );
		define( $spec[1] . '_VERSION', $spec[4] );
	}
	function add_filter( $hook, $callback ) { $GLOBALS['t']['filters'][ $hook ][] = $callback; }
	function add_action( $hook, $callback ) { $GLOBALS['t']['filters'][ $hook ][] = $callback; }
	function plugin_basename( $file ) { return substr( $file, strlen( WP_PLUGIN_DIR ) + 1 ); }
	function is_multisite() { return false; }
	function is_main_site() { return true; }
	function wp_next_scheduled() { return true; }
	function get_site_transient( $key ) { return $GLOBALS['t']['cache'][ $key ] ?? false; }
	function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['t']['cache'][ $key ] = $value; }
	function delete_site_transient( $key ) { unset( $GLOBALS['t']['cache'][ $key ] ); }
	function get_site_option( $key, $default = false ) { return $default; }
	function update_site_option() { return true; }
	function wp_remote_get( $url ) { return array( 'code' => 200, 'body' => json_encode( $GLOBALS['t']['manifest'] ) ); }
	function is_wp_error() { return false; }
	function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
	function wp_remote_retrieve_body( $r ) { return $r['body']; }
	function esc_url_raw( $url ) { return $url; }
	function get_bloginfo() { return '7.1'; }
	final class Fake_Filesystem {
		public function exists( $path ) { return in_array( $path, $GLOBALS['t']['fs'], true ); }
		public function move( $from, $to, $overwrite ) { $GLOBALS['t']['moves'][] = array( $from, $to ); return true; }
	}
	$GLOBALS['wp_filesystem'] = new Fake_Filesystem();
	function check_folder( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	$GLOBALS['t']['manifest'] = array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v1', 'plugins' => array() );
	foreach ( $plugins as $class => $spec ) {
		$GLOBALS['t']['manifest']['plugins'][ $spec[2] ] = array( 'version' => '9.0.0', 'url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/' . $spec[2] . '-9.0.0.zip' );
	}
	foreach ( $plugins as $class => $spec ) {
		require __DIR__ . '/../' . $spec[0];
		$file = $spec[2] . '-subido/' . $spec[3];
		check_folder( $file === $class::file(), $class . ': el archivo es el instalado (' . $class::file() . ').' );
		$GLOBALS['t']['filters'] = array();
		method_exists( $class, 'init' ) ? $class::init() : $class::register();
		check_folder( ! empty( $GLOBALS['t']['filters'][ 'plugin_action_links_' . $file ] ) && empty( $GLOBALS['t']['filters'][ 'plugin_action_links_' . $spec[2] . '/' . $spec[3] ] ) && ! empty( $GLOBALS['t']['filters']['upgrader_source_selection'] ), $class . ': «Buscar actualizaciones» en la fila de su carpeta real y conservación de la carpeta registradas.' );
		$tr = $class::check( (object) array( 'checked' => array( $file => $spec[4] ), 'response' => array(), 'no_update' => array() ) );
		check_folder( isset( $tr->response[ $file ] ) && '9.0.0' === $tr->response[ $file ]->new_version && $file === $tr->response[ $file ]->plugin && ! isset( $tr->response[ $spec[2] . '/' . $spec[3] ] ), $class . ': la versión nueva va a su archivo real.' );
		check_folder( is_array( $class::uri_update( false, array(), $file, array() ) ) && true === $class::auto_update( false, (object) array( 'plugin' => $file ) ), $class . ': la consulta por Update URI y la actualización automática, por su archivo real.' );
		// Al actualizar, la copia nueva (carpeta habitual en el ZIP) se deja en la carpeta instalada.
		$GLOBALS['t']['fs']    = array( '/tmp/up/' . $spec[2] . '/' . $spec[3] );
		$GLOBALS['t']['moves'] = array();
		check_folder( '/tmp/up/' . $spec[2] . '-subido/' === $class::keep_folder( '/tmp/up/' . $spec[2] . '/', '', null, array( 'plugin' => $file ) ) && array( array( '/tmp/up/' . $spec[2], '/tmp/up/' . $spec[2] . '-subido' ) ) === $GLOBALS['t']['moves'], $class . ': la copia nueva conserva la carpeta instalada.' );
		check_folder( '/tmp/up/otro/' === $class::keep_folder( '/tmp/up/otro/', '', null, array( 'plugin' => 'otro/otro.php' ) ) && 1 === count( $GLOBALS['t']['moves'] ), $class . ': la actualización de otro plugin no se toca.' );
		$GLOBALS['t']['cache'] = array();
	}
	echo "Actualizadores de Elements, Migración, Tools y Backups: carpeta real atendida y conservada al actualizar.\n";
}
