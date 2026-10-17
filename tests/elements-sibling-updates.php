<?php
/**
 * Actualizaciones de los plugins Digitalisimo que su propio actualizador no atiende: inactivos, o activos en una
 * carpeta con otro nombre (un ZIP subido a mano). DIGITALÍSIMO Elements entrega a WordPress su versión nueva del índice
 * de la Release por la clave de su «Update URI» cuando nadie lo hizo para su archivo real, quita las entradas huérfanas
 * con la carpeta habitual, pone «Buscar actualizaciones» sólo donde falta, deja la copia nueva en la carpeta donde estaba
 * el plugin al actualizarlo, y no toca lo que un actualizador propio ya atiende, a sí mismo, a los ajenos ni a las copias
 * inactivas de un plugin activo. Sólo acepta ZIPs suyos del repositorio oficial.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DIGITALISIMO_ELEMENTS_FILE', '/wp/plugins/digitalisimo-elements/pro-elements.php' );
$GLOBALS['t'] = array( 'cache' => array(), 'calls' => 0, 'active' => array(), 'can' => true, 'manifest' => null, 'filters' => array(), 'current' => '', 'plugins' => array(), 'fs' => array(), 'moves' => array() );
function add_filter( $hook, $callback ) { $GLOBALS['t']['filters'][ $hook ][] = $callback; }
function add_action() {}
function has_filter( $hook ) { return ! empty( $GLOBALS['t']['filters'][ $hook ] ); }
function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['t']['filters'][ $hook ] ?? array() as $cb ) { $value = $cb( $value, ...$args ); } return $value; }
function current_filter() { return $GLOBALS['t']['current']; }
function get_site_transient( $key ) { return $GLOBALS['t']['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl = 0 ) { $GLOBALS['t']['cache'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['t']['cache'][ $key ] ); }
function wp_remote_get( $url ) { $GLOBALS['t']['calls']++; return null === $GLOBALS['t']['manifest'] ? array( 'code' => 404, 'body' => '' ) : array( 'code' => 200, 'body' => json_encode( $GLOBALS['t']['manifest'] ) ); }
function is_wp_error() { return false; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function esc_url_raw( $url ) { return $url; }
function esc_url( $url ) { return $url; }
function plugin_basename( $file ) { return 'digitalisimo-elements/pro-elements.php'; }
function is_multisite() { return false; }
function is_network_admin() { return false; }
function current_user_can( $cap ) { return $GLOBALS['t']['can']; }
function is_plugin_active( $file ) { return in_array( $file, $GLOBALS['t']['active'], true ); }
function admin_url( $p = '' ) { return 'https://sitio.test/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=n'; }
function get_plugins() { return $GLOBALS['t']['plugins']; }
/** Sistema de archivos de WordPress simulado para la conservación de la carpeta. */
final class Fake_Filesystem {
	public function exists( $path ) { return in_array( $path, $GLOBALS['t']['fs'], true ); }
	public function move( $from, $to, $overwrite ) { $GLOBALS['t']['moves'][] = array( $from, $to ); return true; }
}
$GLOBALS['wp_filesystem'] = new Fake_Filesystem();
function check_sibling( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../digitalisimo-elements/digitalisimo-sibling-updates.php';
$s    = 'Digitalisimo_Elements_Sibling_Updates';
$uri  = 'https://github.com/danbutanda/digitalisimo-plugin/';
$repo = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.17.406/';
$GLOBALS['t']['manifest'] = array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.17.406', 'plugins' => array(
	'digitalisimo-migration' => array( 'version' => '1.0.17', 'url' => $repo . 'digitalisimo-migration-1.0.17.zip' ),
	'digitalisimo-tools'     => array( 'version' => '1.0.31', 'url' => $repo . 'digitalisimo-tools-1.0.31.zip' ),
	'digitalisimo-seo'       => array( 'version' => '1.0.226', 'url' => $repo . 'digitalisimo-seo-1.0.226.zip' ),
	'digitalisimo-elements'  => array( 'version' => '9.9.9', 'url' => $repo . 'digitalisimo-elements-9.9.9.zip' ),
) );
$GLOBALS['t']['plugins'] = array(
	'digitalisimo-elements/pro-elements.php'                  => array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.155', 'UpdateURI' => $uri . 'digitalisimo-elements' ),
	// Migración activa en una carpeta con otro nombre (subida a mano), como en el sitio que lo reportó.
	'digitalisimo-migration-1.0.15/digitalisimo-migration.php' => array( 'Name' => 'DIGITALÍSIMO Migración', 'Version' => '1.0.16', 'UpdateURI' => $uri . 'digitalisimo-migration' ),
	'digitalisimo-tools/digitalisimo-tools.php'               => array( 'Name' => 'DIGITALÍSIMO Tools', 'Version' => '1.0.30', 'UpdateURI' => $uri . 'digitalisimo-tools' ),
	'copia-tools/digitalisimo-tools.php'                      => array( 'Name' => 'DIGITALÍSIMO Tools', 'Version' => '1.0.20', 'UpdateURI' => $uri . 'digitalisimo-tools' ),
	'digitalisimo-seo/digitalisimo-integrations.php'          => array( 'Name' => 'DIGITALÍSIMO SEO', 'Version' => '1.0.226', 'UpdateURI' => $uri . 'digitalisimo-seo' ),
	'otro/otro.php'                                           => array( 'Name' => 'Otro', 'Version' => '1.0', 'UpdateURI' => 'https://github.com/otro/plugin/otro' ),
	'sin-uri/sin-uri.php'                                     => array( 'Name' => 'Sin URI', 'Version' => '1.0' ),
);
$migration = 'digitalisimo-migration-1.0.15/digitalisimo-migration.php';
$GLOBALS['t']['active'] = array( 'digitalisimo-elements/pro-elements.php', $migration, 'digitalisimo-tools/digitalisimo-tools.php' );
// El actualizador propio de Tools atiende su fila (enlace y entrada); el de Migración, antiguo, mira la carpeta habitual.
add_filter( 'plugin_action_links_digitalisimo-tools/digitalisimo-tools.php', static function ( $links ) { $links[] = '<a href="#">Buscar actualizaciones</a>'; return $links; } );
add_filter( 'plugin_action_links_digitalisimo-migration/digitalisimo-migration.php', static function ( $links ) { $links[] = '<a href="#">Buscar actualizaciones</a>'; return $links; } );

$siblings = $s::siblings();
check_sibling( array( $migration, 'digitalisimo-tools/digitalisimo-tools.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) === array_keys( $siblings ), 'Plugins Digitalisimo menos Elements, ajenos y la copia inactiva de uno activo: ' . implode( ', ', array_keys( $siblings ) ) );

// Caché de actualizaciones tras los actualizadores propios: Tools ya tiene la suya; Migración antigua dejó la suya en
// la carpeta habitual, que aquí no existe.
$tr = (object) array(
	'checked'   => array( 'digitalisimo-tools/digitalisimo-tools.php' => '1.0.30', 'digitalisimo-migration/digitalisimo-migration.php' => '1.0.16' ),
	'response'  => array( 'digitalisimo-tools/digitalisimo-tools.php' => (object) array( 'slug' => 'digitalisimo-tools', 'new_version' => '1.0.31', 'package' => 'propio' ) ),
	'no_update' => array( 'digitalisimo-migration/digitalisimo-migration.php' => (object) array( 'slug' => 'digitalisimo-migration' ) ),
);
$tr   = $s::check( $tr );
$item = $tr->response[ $migration ] ?? null;
check_sibling( $item && '1.0.17' === $item->new_version && $repo . 'digitalisimo-migration-1.0.17.zip' === $item->package && $migration === $item->plugin && '1.0.16' === $tr->checked[ $migration ], 'Migración en otra carpeta recibe su versión nueva con su ZIP, con su archivo real.' );
check_sibling( ! isset( $tr->no_update['digitalisimo-migration/digitalisimo-migration.php'], $tr->checked['digitalisimo-migration/digitalisimo-migration.php'] ), 'La entrada huérfana con la carpeta habitual se quita.' );
check_sibling( 'propio' === $tr->response['digitalisimo-tools/digitalisimo-tools.php']->package && isset( $tr->no_update['digitalisimo-seo/digitalisimo-integrations.php'] ) && ! isset( $tr->response['copia-tools/digitalisimo-tools.php'] ) && ! isset( $tr->response['digitalisimo-elements/pro-elements.php'] ), 'Lo que atiende el actualizador propio no se toca; un inactivo al día queda en no_update; ni la copia ni Elements.' );
check_sibling( 1 === $GLOBALS['t']['calls'], 'El índice se descarga una sola vez y se comparte.' );
// La consulta por «Update URI».
$update = $s::uri_update( false, array(), $migration, array() );
check_sibling( is_array( $update ) && '1.0.17' === $update['version'] && $repo . 'digitalisimo-migration-1.0.17.zip' === $update['package'], 'Responde por el archivo real si nadie lo hizo.' );
check_sibling( array( 'propio' ) === $s::uri_update( array( 'propio' ), array(), 'digitalisimo-tools/digitalisimo-tools.php', array() ) && false === $s::uri_update( false, array(), 'otro/otro.php', array() ), 'Una respuesta propia no se toca; los ajenos tampoco.' );
// «Buscar actualizaciones» sólo donde falta.
$GLOBALS['t']['current'] = 'plugin_action_links';
$links = $s::action_link( array( 'Desactivar' ), $migration, array(), 'all' );
check_sibling( 2 === count( $links ) && str_contains( $links[1], 'Buscar actualizaciones' ) && str_contains( $links[1], 'digitalisimo-migration-1.0.15' ), 'La fila de Migración en otra carpeta recibe el enlace.' );
check_sibling( array( 'Desactivar' ) === $s::action_link( array( 'Desactivar' ), 'digitalisimo-tools/digitalisimo-tools.php', array(), 'all' ), 'Tools, que ya pone el suyo, no recibe otro.' );
check_sibling( 2 === count( $s::action_link( array( 'Activar' ), 'digitalisimo-seo/digitalisimo-integrations.php', array(), 'all' ) ), 'Un inactivo recibe el enlace.' );
check_sibling( array( 'Activar' ) === $s::action_link( array( 'Activar' ), 'otro/otro.php', array(), 'all' ) && array( 'Activar' ) === $s::action_link( array( 'Activar' ), 'copia-tools/digitalisimo-tools.php', array(), 'all' ), 'Ni los ajenos ni la copia inactiva.' );
$GLOBALS['t']['can'] = false;
check_sibling( array( 'Desactivar' ) === $s::action_link( array( 'Desactivar' ), $migration, array(), 'all' ), 'Sin permiso para actualizar plugins no hay enlace.' );
$GLOBALS['t']['can'] = true;
// Al actualizar, la copia nueva se queda en la carpeta donde estaba el plugin.
$GLOBALS['t']['fs'] = array( '/tmp/upgrade/abc/digitalisimo-migration/digitalisimo-migration.php' );
$kept = $s::keep_folder( '/tmp/upgrade/abc/digitalisimo-migration/', '/tmp/upgrade/abc', null, array( 'plugin' => $migration ) );
check_sibling( '/tmp/upgrade/abc/digitalisimo-migration-1.0.15/' === $kept && array( array( '/tmp/upgrade/abc/digitalisimo-migration', '/tmp/upgrade/abc/digitalisimo-migration-1.0.15' ) ) === $GLOBALS['t']['moves'], 'La copia nueva conserva la carpeta instalada: ' . $kept );
check_sibling( '/tmp/upgrade/abc/digitalisimo-tools/' === $s::keep_folder( '/tmp/upgrade/abc/digitalisimo-tools/', '', null, array( 'plugin' => 'digitalisimo-tools/digitalisimo-tools.php' ) ) && '/x/otro/' === $s::keep_folder( '/x/otro/', '', null, array( 'plugin' => 'otro/otro.php' ) ) && '/x/y/' === $s::keep_folder( '/x/y/', '', null, array() ) && 1 === count( $GLOBALS['t']['moves'] ), 'En su carpeta habitual, ajeno o en una instalación nueva, no se mueve nada.' );
// Sólo ZIPs suyos del repositorio oficial.
$GLOBALS['t']['cache'] = array();
$GLOBALS['t']['manifest']['plugins']['digitalisimo-migration']['url'] = 'https://evil.example/digitalisimo-migration-1.0.17.zip';
check_sibling( array() === $s::release( 'digitalisimo-migration' ), 'Un ZIP fuera del repositorio oficial se rechaza.' );
$GLOBALS['t']['cache'] = array();
$GLOBALS['t']['manifest']['plugins']['digitalisimo-migration']['url'] = $repo . 'digitalisimo-tools-1.0.17.zip';
check_sibling( array() === $s::release( 'digitalisimo-migration' ), 'Un ZIP de otro plugin se rechaza.' );
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); } }
// «Ver detalles» de un hermano inactivo: si su actualizador no respondió, la ficha la da Elements, sin GitHub.
$info = $s::info( false, 'plugin_information', (object) array( 'slug' => 'digitalisimo-migration' ) );
check_sibling( is_object( $info ) && 'digitalisimo-migration' === $info->slug && '' !== $info->version && false === stripos( $info->homepage . implode( '', $info->sections ), 'github' ), 'Elements da la ficha de un plugin Digitalisimo inactivo.' );
$own = (object) array( 'slug' => 'digitalisimo-migration' );
check_sibling( $own === $s::info( $own, 'plugin_information', (object) array( 'slug' => 'digitalisimo-migration' ) ) && false === $s::info( false, 'plugin_information', (object) array( 'slug' => 'otro-plugin' ) ), 'La ficha de su propio actualizador y la de otros plugins no se tocan.' );
echo "DIGITALÍSIMO Elements: actualizaciones de los plugins Digitalisimo inactivos o en otra carpeta validadas.\n";
