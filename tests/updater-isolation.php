<?php
/** Comprueba que el actualizador compartido nunca sustituya el inventario de WordPress. */
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/sin-plugins-instalados' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$cases = array(
	'seo' => array( 'digitalisimo-seo/includes/class-digitalisimo-updater.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-seo' ),
	'hosting' => array( 'digitalisimo-hosting/includes/class-digitalisimo-updater.php', 'digitalisimo-hosting/digitalisimo-hosting.php', 'digitalisimo-hosting' ),
	'ia' => array( 'digitalisimo.chatbot/includes/class-digitalisimo-updater.php', 'digitalisimo.chatbot/digitalisimo-chatbot.php', 'digitalisimo-ia-tools' ),
);
$case = $cases[ $argv[1] ?? '' ] ?? null;
if ( ! $case ) throw new RuntimeException( 'Indica seo, hosting o ia.' );
list( $source, $file, $slug ) = $case;
function plugin_basename( $file ) { return substr( $file, strlen( '/plugins/' ) ); }
function add_filter() {}
function add_action() {}
function get_site_transient( $key ) { return 'digitalisimo_releases_index' === $key ? array( $GLOBALS['slug'] => array( 'version' => '2.0.0', 'package' => 'https://example.test/update.zip', 'url' => 'https://example.test/release' ) ) : ( $GLOBALS['transients'][ $key ] ?? false ); }
function set_site_transient( $key, $value ) { $GLOBALS['transients'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function get_bloginfo( $key ) { return '6.8'; }
$GLOBALS['slug'] = $slug;
require __DIR__ . '/../' . $source;
Digitalisimo_Updater::register( '/plugins/' . $file, '1.0.0', $slug );
if ( false !== Digitalisimo_Updater::inject( false ) ) throw new RuntimeException( 'Una caché ausente debe continuar ausente.' );
$partial = (object) array( 'last_checked' => time() );
if ( $partial !== Digitalisimo_Updater::inject( $partial ) || isset( $partial->response ) ) throw new RuntimeException( 'No debe convertir el estado parcial de WordPress en una lista propia.' );
$external_update = (object) array( 'new_version' => '3.0.0' );
$external_current = (object) array( 'new_version' => '1.0.0' );
$transient = (object) array(
	'last_checked' => time(),
	'checked' => array( 'tercero/update.php' => '1.0.0', 'tercero/current.php' => '1.0.0' ),
	'response' => array( 'tercero/update.php' => $external_update ),
	'no_update' => array( 'tercero/current.php' => $external_current ),
	'translations' => array( array( 'slug' => 'tercero' ) ),
);
$result = Digitalisimo_Updater::inject( $transient );
if ( $result !== $transient || $result->response['tercero/update.php'] !== $external_update || $result->no_update['tercero/current.php'] !== $external_current || count( $result->translations ) !== 1 ) throw new RuntimeException( 'No debe borrar avisos ni traducciones de terceros.' );
if ( ( $result->response[ $file ]->new_version ?? '' ) !== '2.0.0' ) throw new RuntimeException( 'La actualización propia debe añadirse sin reemplazar las ajenas.' );
$GLOBALS['transients']['update_plugins'] = $transient;
Digitalisimo_Updater::clear( null, array( 'action' => 'update', 'type' => 'plugin', 'plugins' => array( 'tercero/update.php' ) ) );
if ( $GLOBALS['transients']['update_plugins'] !== $transient ) throw new RuntimeException( 'Actualizar un plugin ajeno no debe borrar el inventario global.' );
echo "Actualizador compartido $slug: aislamiento correcto.\n";
