<?php
/**
 * Lista de plugins con el plugin Elementor instalado pero desactivado y el núcleo incluido cargado: la fila de Elementor
 * muestra sus propias cabeceras (los filtros de identidad, activos en esta petición, no la convierten en un segundo
 * «DIGITALÍSIMO Elements» por «DIGITALÍSIMO Elements.com»), lleva la nota de que no hace falta, y el plugin no cuenta
 * como desinstalable (borrarlo no cargará su archivo principal sobre el núcleo incluido).
 */
// Los errores se muestran tal cual: el gestor de errores fatales de WordPress los ocultaría.
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );
// Si el paso falla, su último error queda en digi-core para poder leerlo.
register_shutdown_function( static function () {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/digi-core/error-rows.json', json_encode( $error ) );
	}
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/update.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-plugins-list-table.php';
if ( file_exists( ABSPATH . 'wp-admin/includes/class-wp-plugin-dependencies.php' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-plugin-dependencies.php';
}

$result = array(
	'bundled'         => defined( 'DIGITALISIMO_ELEMENTS_CORE' ),
	'external_active' => is_plugin_active( 'elementor/elementor.php' ) || is_plugin_active_for_network( 'elementor/elementor.php' ),
	'installed'       => file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ),
	'brand_active'    => translate( 'Elementor', 'elementor' ), // phpcs:ignore WordPress.WP.I18n.LowLevelTranslationFunction
	'uninstallable'   => is_uninstallable_plugin( 'elementor/elementor.php' ),
);
try {
	wp_set_current_user( 1 );
	set_current_screen( 'plugins' );
	$GLOBALS['status'] = 'all';
	$GLOBALS['page']   = 1;
	$GLOBALS['s']      = '';
	$table = new WP_Plugins_List_Table( array( 'screen' => 'plugins' ) );
	$table->prepare_items();
	ob_start();
	$table->display();
	$html = (string) ob_get_clean();
	$result['row_name']       = (string) ( $table->items['elementor/elementor.php']['Name'] ?? '' );
	$result['row_author']     = (string) ( $table->items['elementor/elementor.php']['Author'] ?? '' );
	$result['html_author']    = false !== strpos( $html, '>Elementor.com</a>' );
	$result['html_duplicate'] = false !== strpos( $html, 'DIGITALÍSIMO Elements.com' );
	$result['html_note']      = false !== strpos( $html, 'plugin Elementor original' ) && false !== strpos( $html, 'puedes borrarlo' );
	$result['html_own']       = false !== strpos( $html, 'DIGITALÍSIMO Elements</strong>' );
} catch ( Throwable $e ) {
	$result['error'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
file_put_contents( '/wordpress/wp-content/digi-core/rows.json', wp_json_encode( $result ) );
