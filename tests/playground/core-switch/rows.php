<?php
/**
 * Lista de plugins (pantalla de sitio y de red) con el plugin Elementor instalado pero desactivado y el núcleo incluido
 * cargado: la fila de Elementor muestra sus propias cabeceras (los filtros de identidad, activos en esta petición, no la
 * convierten en un segundo «DIGITALÍSIMO Elements» por «DIGITALÍSIMO Elements.com»), lleva la nota propia de que no hace
 * falta con el ancho real de cada tabla, get_plugin_data() (Actualizaciones, borrado, correos) devuelve también el nombre
 * real, y el plugin no cuenta como desinstalable (borrarlo no cargará su archivo principal sobre el núcleo incluido).
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
	// Como Escritorio → Actualizaciones, la confirmación de borrado y los correos: cabeceras traducidas con su dominio.
	$data                = get_plugin_data( WP_PLUGIN_DIR . '/elementor/elementor.php', false, true );
	$result['data_name'] = (string) $data['Name'];
	foreach ( array( 'plugins', 'plugins-network' ) as $screen ) {
		set_current_screen( $screen );
		$GLOBALS['status'] = 'all';
		$GLOBALS['page']   = 1;
		$GLOBALS['s']      = '';
		$table = new WP_Plugins_List_Table( array( 'screen' => $screen ) );
		$table->prepare_items();
		ob_start();
		$table->display();
		$html = (string) ob_get_clean();
		$result[ $screen ] = array(
			'row_name'       => (string) ( $table->items['elementor/elementor.php']['Name'] ?? '' ),
			'row_author'     => (string) ( $table->items['elementor/elementor.php']['Author'] ?? '' ),
			'html_author'    => false !== strpos( $html, '>Elementor.com</a>' ),
			'html_duplicate' => false !== strpos( $html, 'DIGITALÍSIMO Elements.com' ),
			'html_note'      => false !== strpos( $html, 'digitalisimo-plugin-note' ) && false !== strpos( $html, 'plugin Elementor original' ) && false === strpos( $html, 'update-message notice inline notice-warning notice-alt"><p>Este es' ),
			'own_rows'       => preg_match_all( '/plugin-title column-primary[^>]*><strong>DIGITALÍSIMO Elements<\/strong>/u', $html ),
			'elementor_rows' => preg_match_all( '/plugin-title column-primary[^>]*><strong>Elementor<\/strong>/u', $html ),
			'colspan'        => preg_match( '/digitalisimo-plugin-note" data-plugin="elementor\/elementor\.php"><td colspan="(\d+)"/', $html, $m ) ? (int) $m[1] : 0,
		);
	}
} catch ( Throwable $e ) {
	$result['error'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
file_put_contents( '/wordpress/wp-content/digi-core/rows.json', wp_json_encode( $result ) );
