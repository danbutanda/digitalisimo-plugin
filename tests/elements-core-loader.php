<?php
/**
 * Núcleo de Elementor incluido: cuándo se carga, rutas antiguas del plugin Elementor guardadas en el contenido,
 * traducciones (PHP y JSON de scripts) y dependencias de complementos que exigen «elementor».
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	function check_core( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
	function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-core.php';
	$core = 'Digitalisimo_Elements_Core';

	check_core( $core::activating_external( array( 'action' => 'activate', 'plugin' => 'elementor/elementor.php' ) ) && $core::activating_external( array( 'action' => '-1', 'action2' => 'activate-selected', 'checked' => array( 'akismet/akismet.php', 'elementor/elementor.php' ) ) ), 'Al activar el plugin Elementor (solo o en lote) el núcleo incluido no se carga.' );
	check_core( ! $core::activating_external( array( 'action' => 'activate', 'plugin' => 'woocommerce/woocommerce.php' ) ) && ! $core::activating_external( array( 'action' => 'deactivate', 'plugin' => 'elementor/elementor.php' ) ) && ! $core::activating_external( array() ), 'Otras acciones no impiden cargar el núcleo incluido.' );

	$old = '/wp-content/plugins/elementor/';
	$new = '/wp-content/plugins/digitalisimo-elements/elementor/';
	$json = '[{"settings":{"image":{"url":"https:\/\/sitio.test\/wp-content\/plugins\/elementor\/assets\/images\/placeholder.png","id":""}}}]';
	check_core( '[{"settings":{"image":{"url":"https:\/\/sitio.test\/wp-content\/plugins\/digitalisimo-elements\/elementor\/assets\/images\/placeholder.png","id":""}}}]' === $core::replace_asset_urls( $json, $old, $new ), 'El JSON guardado (barras escapadas) apunta al núcleo incluido.' );
	$settings = array( 'background_image' => array( 'url' => 'http://otro.test/wp-content/plugins/elementor/assets/images/placeholder.png' ), 'title' => 'Sin rutas', 'n' => 3 );
	$fixed    = $core::replace_asset_urls( $settings, $old, $new );
	check_core( 'http://otro.test/wp-content/plugins/digitalisimo-elements/elementor/assets/images/placeholder.png' === $fixed['background_image']['url'] && 'Sin rutas' === $fixed['title'] && 3 === $fixed['n'], 'Los ajustes de página se corrigen con cualquier dominio y lo demás queda igual.' );
	check_core( 'https://sitio.test/wp-content/plugins/elementor-pro/x.png' === $core::replace_asset_urls( 'https://sitio.test/wp-content/plugins/elementor-pro/x.png', $old, $new ), 'Otros plugins con nombre parecido no se tocan.' );

	check_core( 'assets/js/editor.js' === $core::script_relative_path( 'elementor/assets/js/editor.js', 'https://sitio.test/wp-content/plugins/digitalisimo-elements/elementor/assets/js/editor.min.js' ), 'Los scripts del núcleo buscan su traducción con la ruta que usa Elementor.' );
	check_core( 'assets/js/x.js' === $core::script_relative_path( 'assets/js/x.js', 'https://sitio.test/wp-content/plugins/digitalisimo-elements/assets/js/x.js' ) && 'elementor/a.js' === $core::script_relative_path( 'elementor/a.js', 'https://sitio.test/wp-content/plugins/otro/elementor/a.js' ), 'Los scripts propios y de otros plugins conservan su ruta.' );

	$own = realpath( __DIR__ . '/../digitalisimo-elements/elementor/languages' ) . '/';
	check_core( $own === realpath( $core::lang_dir( false, 'elementor', 'es_MX' ) ) . '/' && false === $core::lang_dir( false, 'elementor', 'xx_XX' ) && false === $core::lang_dir( false, 'woocommerce', 'es_MX' ), 'Sin traducción global, Elementor usa la del núcleo para el español.' );
	$global = sys_get_temp_dir() . '/digi-lang-' . getmypid() . '/';
	mkdir( $global );
	touch( $global . 'elementor-es_MX.l10n.php' );
	check_core( $global === $core::lang_dir( $global, 'elementor', 'es_MX' ), 'Si WordPress tiene la traducción de Elementor, se usa ésa.' );
	unlink( $global . 'elementor-es_MX.l10n.php' );
	rmdir( $global );
	$json_file = '/no-existe/elementor-es_MX-' . md5( 'assets/js/common.js' ) . '.json';
	check_core( is_readable( $core::script_translation_file( $json_file, 'elementor-common', 'elementor' ) ) && $json_file === $core::script_translation_file( $json_file, 'otro', 'woocommerce' ), 'El JSON de idioma de un script de Elementor sale del núcleo si falta el global.' );

	check_core( 'digitalisimo-elements' === $core::dependency_slug( 'elementor' ) && 'woocommerce' === $core::dependency_slug( 'woocommerce' ), 'Un complemento que exige «elementor» queda satisfecho con DIGITALÍSIMO Elements.' );
	check_core( preg_match( '/^\d+\.\d+\.\d+$/', $core::version() ), 'La versión del núcleo incluido sale de su manifiesto.' );
	echo 'DIGITALÍSIMO Elements: cargador del núcleo de Elementor ' . $core::version() . " validado.\n";
}
