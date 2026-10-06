<?php
/** Comprueba que cada módulo cargue el controlador sólo en Plugins y registre su propio archivo. */
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', '/plugins' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DIGITALISIMO_BACKUPS_VERSION', '1.0.52' );
define( 'DIGITALISIMO_TOOLS_VERSION', '1.0.28' );
define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.10' );

$cases = array(
	'seo' => array( 'digitalisimo-seo/includes/class-digitalisimo-updater.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'Digitalisimo_Updater', 'register', 'digitalisimo-seo' ),
	'ia' => array( 'digitalisimo.chatbot/includes/class-digitalisimo-updater.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'Digitalisimo_Updater', 'register', 'digitalisimo-ia-tools' ),
	'hosting' => array( 'digitalisimo-hosting/includes/class-digitalisimo-updater.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'Digitalisimo_Updater', 'register', 'digitalisimo-hosting' ),
	'backups' => array( 'digitalisimo-backups/includes/class-digitalisimo-updater.php', 'digitalisimo-backups/digitalisimo-backups.php', 'Digitalisimo_Backups_Updater', 'register', '' ),
	'tools' => array( 'digitalisimo-tools/includes/class-updater.php', 'digitalisimo-tools/digitalisimo-tools.php', 'Digitalisimo\\Tools\\Updater', 'init', '' ),
	'elements' => array( 'digitalisimo-elements/digitalisimo-updater.php', 'digitalisimo-elements/pro-elements.php', 'Digitalisimo_Elements_Updater', 'init', '' ),
);
$case = $cases[ $argv[1] ?? '' ] ?? null;
if ( ! $case ) throw new RuntimeException( 'Indica un módulo.' );
list( $source, $file, $class, $method, $slug ) = $case;

$GLOBALS['multisite'] = false;
$GLOBALS['allowed'] = true;
$GLOBALS['scripts'] = array();
$GLOBALS['inline'] = array();
function plugin_basename( $file ) { return str_replace( '/plugins/', '', $file ); }
function add_filter() {}
function add_action() {}
function is_multisite() { return $GLOBALS['multisite']; }
function is_main_site() { return true; }
function wp_next_scheduled() { return true; }
function current_user_can( $capability ) { $GLOBALS['capability'] = $capability; return $GLOBALS['allowed']; }
function plugins_url( $path, $file ) { return 'https://example.test/wp-content/plugins/' . dirname( plugin_basename( $file ) ) . '/' . $path; }
function wp_enqueue_script( $handle, $url, $dependencies, $version, $footer ) { $GLOBALS['scripts'][] = compact( 'handle', 'url', 'dependencies', 'version', 'footer' ); }
function wp_add_inline_script( $handle, $data, $position ) { $GLOBALS['inline'][] = compact( 'handle', 'data', 'position' ); }
function wp_json_encode( $value ) { return json_encode( $value ); }

require __DIR__ . '/../' . $source;
if ( 'register' === $method && $slug ) $class::register( '/plugins/' . $file, '1.0.0', $slug );
else $class::$method();
$class::enqueue_inline_update( 'dashboard' );
if ( $GLOBALS['scripts'] || $GLOBALS['inline'] ) throw new RuntimeException( 'No debe cargar el controlador fuera de Plugins.' );

foreach ( array( false => 'update_plugins', true => 'manage_network_plugins' ) as $network => $expected_capability ) {
	$GLOBALS['multisite'] = (bool) $network;
	$class::enqueue_inline_update( 'plugins.php' );
	if ( $GLOBALS['capability'] !== $expected_capability ) throw new RuntimeException( 'Capacidad incorrecta en sitio o red.' );
	$script = end( $GLOBALS['scripts'] );
	$registration = end( $GLOBALS['inline'] );
	if ( ! $script || 'digitalisimo-inline-plugin-update' !== $script['handle'] || array( 'updates' ) !== $script['dependencies'] || ! $script['footer'] ) throw new RuntimeException( 'Debe depender del actualizador AJAX de WordPress.' );
	if ( false === strpos( $script['url'], dirname( $file ) . '/assets/js/update-in-place.js' ) ) throw new RuntimeException( 'La URL del controlador no corresponde al módulo instalado.' );
	if ( ! $registration || 'before' !== $registration['position'] || false === strpos( $registration['data'], json_encode( $file ) ) ) throw new RuntimeException( 'El módulo no registró su propio archivo antes de cargar el controlador.' );
}
$count = count( $GLOBALS['scripts'] );
$GLOBALS['allowed'] = false;
$class::enqueue_inline_update( 'plugins.php' );
if ( $count !== count( $GLOBALS['scripts'] ) ) throw new RuntimeException( 'Un usuario sin permiso no debe cargar el controlador.' );
echo "Controlador AJAX registrado correctamente: $file.\n";
