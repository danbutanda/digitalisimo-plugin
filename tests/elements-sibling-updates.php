<?php
/**
 * Actualizaciones de los plugins Digitalisimo inactivos: DIGITALÍSIMO Elements entrega a WordPress la versión nueva de
 * cada uno (del índice de la Release, por la clave de su «Update URI») y pinta «Buscar actualizaciones» en su fila; deja
 * a los activos con su propio actualizador, se ignora a sí mismo, a los plugins ajenos y a las copias inactivas de uno
 * activo, y sólo acepta ZIPs suyos del repositorio oficial.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DIGITALISIMO_ELEMENTS_FILE', '/wp/plugins/digitalisimo-elements/pro-elements.php' );
$GLOBALS['t'] = array( 'cache' => array(), 'calls' => 0, 'active' => array(), 'can' => true, 'manifest' => null );
function add_filter() {} function add_action() {}
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
function get_plugins() {
	$repo = 'https://github.com/danbutanda/digitalisimo-plugin/';
	return array(
		'digitalisimo-elements/pro-elements.php'           => array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.154', 'UpdateURI' => $repo . 'digitalisimo-elements' ),
		'digitalisimo-migration/digitalisimo-migration.php' => array( 'Name' => 'DIGITALÍSIMO Migración', 'Version' => '1.0.16', 'UpdateURI' => $repo . 'digitalisimo-migration' ),
		'digitalisimo-tools/digitalisimo-tools.php'        => array( 'Name' => 'DIGITALÍSIMO Tools', 'Version' => '1.0.30', 'UpdateURI' => $repo . 'digitalisimo-tools' ),
		'copia-tools/digitalisimo-tools.php'               => array( 'Name' => 'DIGITALÍSIMO Tools', 'Version' => '1.0.20', 'UpdateURI' => $repo . 'digitalisimo-tools' ),
		'digitalisimo-seo/digitalisimo-integrations.php'   => array( 'Name' => 'DIGITALÍSIMO SEO', 'Version' => '1.0.226', 'UpdateURI' => $repo . 'digitalisimo-seo' ),
		'otro/otro.php'                                    => array( 'Name' => 'Otro', 'Version' => '1.0', 'UpdateURI' => 'https://github.com/otro/plugin/otro' ),
		'sin-uri/sin-uri.php'                              => array( 'Name' => 'Sin URI', 'Version' => '1.0' ),
	);
}
function check_sibling( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
require __DIR__ . '/../digitalisimo-elements/digitalisimo-sibling-updates.php';
$s    = 'Digitalisimo_Elements_Sibling_Updates';
$repo = 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v2026.10.17.405/';
$GLOBALS['t']['manifest'] = array( 'release_url' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/tag/v2026.10.17.405', 'plugins' => array(
	'digitalisimo-migration' => array( 'version' => '1.0.17', 'url' => $repo . 'digitalisimo-migration-1.0.17.zip' ),
	'digitalisimo-tools'     => array( 'version' => '1.0.31', 'url' => $repo . 'digitalisimo-tools-1.0.31.zip' ),
	'digitalisimo-seo'       => array( 'version' => '1.0.226', 'url' => $repo . 'digitalisimo-seo-1.0.226.zip' ),
	'digitalisimo-elements'  => array( 'version' => '9.9.9', 'url' => $repo . 'digitalisimo-elements-9.9.9.zip' ),
) );
$GLOBALS['t']['active'] = array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-tools/digitalisimo-tools.php' );

$siblings = $s::siblings();
check_sibling( array( 'digitalisimo-migration/digitalisimo-migration.php', 'digitalisimo-seo/digitalisimo-integrations.php' ) === array_keys( $siblings ) && 'digitalisimo-migration' === $siblings['digitalisimo-migration/digitalisimo-migration.php']['slug'], 'Sólo los plugins Digitalisimo inactivos, sin Elements, sin ajenos y sin la copia inactiva de uno activo: ' . implode( ', ', array_keys( $siblings ) ) );

// La caché de actualizaciones de WordPress recibe la versión nueva de los inactivos; los activos no se tocan.
$tr = $s::check( (object) array( 'checked' => array( 'digitalisimo-tools/digitalisimo-tools.php' => '1.0.30' ), 'response' => array(), 'no_update' => array() ) );
$item = $tr->response['digitalisimo-migration/digitalisimo-migration.php'] ?? null;
check_sibling( $item && '1.0.17' === $item->new_version && $repo . 'digitalisimo-migration-1.0.17.zip' === $item->package && 'digitalisimo-migration' === $item->slug && '1.0.16' === $tr->checked['digitalisimo-migration/digitalisimo-migration.php'], 'Migración inactiva recibe su versión nueva con su ZIP.' );
check_sibling( isset( $tr->no_update['digitalisimo-seo/digitalisimo-integrations.php'] ) && ! isset( $tr->response['digitalisimo-seo/digitalisimo-integrations.php'] ), 'Un inactivo al día queda en no_update.' );
check_sibling( ! isset( $tr->response['digitalisimo-tools/digitalisimo-tools.php'] ) && ! isset( $tr->no_update['digitalisimo-tools/digitalisimo-tools.php'] ) && ! isset( $tr->response['copia-tools/digitalisimo-tools.php'] ) && ! isset( $tr->response['digitalisimo-elements/pro-elements.php'] ), 'Tools activo (y su copia), y Elements, quedan en manos de su propio actualizador.' );
check_sibling( 1 === $GLOBALS['t']['calls'], 'El índice se descarga una sola vez y se comparte.' );
// La consulta por «Update URI».
$update = $s::uri_update( false, array(), 'digitalisimo-migration/digitalisimo-migration.php', array() );
check_sibling( is_array( $update ) && '1.0.17' === $update['version'] && $repo . 'digitalisimo-migration-1.0.17.zip' === $update['package'], 'La consulta de WordPress por Update URI responde para el inactivo.' );
check_sibling( 'ajeno' === $s::uri_update( 'ajeno', array(), 'digitalisimo-tools/digitalisimo-tools.php', array() ) && false === $s::uri_update( false, array(), 'digitalisimo-seo/digitalisimo-integrations.php', array() ), 'Para un activo no responde; para un inactivo al día, sin actualización.' );
// «Buscar actualizaciones» en la fila del inactivo.
$links = $s::action_link( array( 'Activar' ), 'digitalisimo-migration/digitalisimo-migration.php' );
check_sibling( 2 === count( $links ) && str_contains( $links[1], 'Buscar actualizaciones' ) && str_contains( $links[1], 'action=digitalisimo_elements_check_sibling' ) && str_contains( $links[1], 'digitalisimo-migration' ), 'La fila de Migración inactiva tiene «Buscar actualizaciones».' );
check_sibling( array( 'Desactivar' ) === $s::action_link( array( 'Desactivar' ), 'digitalisimo-tools/digitalisimo-tools.php' ) && array( 'Activar' ) === $s::action_link( array( 'Activar' ), 'otro/otro.php' ), 'Los activos (que ya tienen el suyo) y los ajenos no reciben otro.' );
$GLOBALS['t']['can'] = false;
check_sibling( array( 'Activar' ) === $s::action_link( array( 'Activar' ), 'digitalisimo-migration/digitalisimo-migration.php' ), 'Sin permiso para actualizar plugins no hay enlace.' );
$GLOBALS['t']['can'] = true;
// Sólo ZIPs suyos del repositorio oficial.
$GLOBALS['t']['cache'] = array();
$GLOBALS['t']['manifest']['plugins']['digitalisimo-migration']['url'] = 'https://evil.example/digitalisimo-migration-1.0.17.zip';
check_sibling( array() === $s::release( 'digitalisimo-migration' ), 'Un ZIP fuera del repositorio oficial se rechaza.' );
$GLOBALS['t']['manifest']['plugins']['digitalisimo-migration']['url'] = $repo . 'digitalisimo-tools-1.0.17.zip';
$GLOBALS['t']['cache'] = array();
check_sibling( array() === $s::release( 'digitalisimo-migration' ), 'Un ZIP de otro plugin se rechaza.' );
// Al activarse, su actualizador vuelve a encargarse.
$GLOBALS['t']['active'][] = 'digitalisimo-migration/digitalisimo-migration.php';
$s::forget();
check_sibling( ! isset( $s::siblings()['digitalisimo-migration/digitalisimo-migration.php'] ), 'Activado, deja de atenderlo.' );
echo "DIGITALÍSIMO Elements: actualizaciones de los plugins Digitalisimo inactivos validadas.\n";
