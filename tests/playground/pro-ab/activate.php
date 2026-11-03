<?php
/**
 * Primer paso del blueprint de pro-ab.sh: registro de errores fatales (mu-plugin), ajustes del sitio y activación de
 * DIGITALÍSIMO Elements con el núcleo incluido. Las páginas se crean en el paso siguiente (`setup.php`), en otra
 * petición, con el constructor ya cargado.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

const DIGI_AB_PROBE = '/wordpress/wp-content/probe';

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-fatal-log.php', '<?php register_shutdown_function( static function () { $e = error_get_last(); if ( $e && in_array( $e["type"], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) { file_put_contents( "/wordpress/wp-content/probe/fatal.log", ( $_SERVER["REQUEST_URI"] ?? "" ) . " " . $e["message"] . " en " . $e["file"] . ":" . $e["line"] . "\n", FILE_APPEND ); } } );' );

// WP-Cron no se dispara desde las visitas (como la sonda de front-budget): con un solo proceso de PHP, su petición en
// lazo hacía que Playground sirviera alguna hoja `post-N.css` como redirección a una página de WordPress.
file_put_contents( WPMU_PLUGIN_DIR . '/digi-ab-no-cron.php', "<?php\nif ( ! defined( 'DISABLE_WP_CRON' ) ) {\n\tdefine( 'DISABLE_WP_CRON', true );\n}\n" );

$result = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
if ( is_wp_error( $result ) ) {
	file_put_contents( DIGI_AB_PROBE . '/setup-error.txt', 'No se pudo activar DIGITALÍSIMO Elements: ' . $result->get_error_message() . "\n", FILE_APPEND );
}
update_option( 'elementor_onboarded', true );
// Sin caché de elementos: cada visita pinta con los ajustes guardados.
update_option( 'elementor_element_cache_ttl', 'disable' );
update_option( 'blogname', 'Sitio de la comparación' );
update_option( 'blogdescription', 'A/B de widgets' );
update_option( 'permalink_structure', '/%postname%/' );
