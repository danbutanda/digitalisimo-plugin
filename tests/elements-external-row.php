<?php
/**
 * El plugin Elementor original instalado junto al núcleo incluido no se toca: sus cabeceras no cambian de marca en ninguna
 * pantalla que las traduzca (lista de plugins, Actualizaciones, borrado, correos), su fila recibe una nota aditiva acorde a
 * su estado, las demás filas no cambian, borrarlo no ejecuta su rutina de desinstalación, y dos carpetas instaladas de un
 * mismo plugin propio se avisan.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$dir = sys_get_temp_dir() . '/digi-external-row-' . getmypid();
	@mkdir( $dir . '/elementor', 0777, true );
	file_put_contents( $dir . '/elementor/elementor.php', "<?php\n/**\n * Plugin Name: Elementor\n * Description: The Elementor Website Builder has it all\n * Author: Elementor.com\n * Author URI: https://elementor.com/\n */\n" );
	define( 'WP_PLUGIN_DIR', $dir );
	function check_row( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['brand_on'] = true;
	$GLOBALS['opt']      = array( 'active_plugins' => array( 'digitalisimo-elements/pro-elements.php' ) );
	function add_action() {}
	function add_filter( $hook ) { if ( 'gettext' === $hook ) { $GLOBALS['brand_on'] = true; } }
	function remove_filter( $hook ) { if ( 'gettext' === $hook ) { $GLOBALS['brand_on'] = false; return true; } return false; }
	function is_admin() { return true; }
	function is_multisite() { return false; }
	function is_network_admin() { return false; }
	function get_option( $name, $default = false ) { return $GLOBALS['opt'][ $name ] ?? $default; }
	function get_site_option( $name, $default = false ) { return $default; }
	function is_textdomain_loaded() { return true; }
	// Como WordPress: get_file_data() lee las cabeceras del archivo.
	function get_file_data( $file, $headers ) { $data = (string) file_get_contents( $file ); $out = array(); foreach ( $headers as $key => $name ) { $out[ $key ] = preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $data, $m ) ? trim( $m[1] ) : ''; } return $out; }
	// Como WordPress: traduce (aquí, identidad) y aplica el filtro gettext si está enganchado.
	function translate( $text, $domain ) { $t = 'The Elementor Website Builder has it all' === $text ? 'El maquetador Elementor lo tiene todo' : $text; return $GLOBALS['brand_on'] ? Digitalisimo_Elements_Identity::gettext( $t, $text, $domain ) : $t; }
	// Como WordPress: traduce las cabeceras de un plugin con su propio dominio (lista, Actualizaciones, borrado, correos).
	function _get_plugin_data_markup_translate( $plugin_file, $plugin_data, $markup = true, $translate = true ) { foreach ( array( 'Name', 'Description', 'Author' ) as $f ) { if ( isset( $plugin_data[ $f ] ) ) { $plugin_data[ $f ] = translate( $plugin_data[ $f ], $plugin_data['TextDomain'] ); } } return $plugin_data; }
	function current_user_can() { return true; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
	function get_current_screen() { return (object) array( 'id' => 'plugins' ); }
	function _get_list_table() { return new class() { public function get_column_count() { return 3; } }; }
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-core.php';
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-identity.php';
	$core = 'Digitalisimo_Elements_Core';
	$id   = 'Digitalisimo_Elements_Identity';

	// La marca sigue aplicándose a los textos del núcleo, también al texto «Elementor» fuera de una cabecera.
	check_row( 'DIGITALÍSIMO Elements' === translate( 'Elementor', 'elementor' ) && 'DIGITALÍSIMO Elements.com' === translate( 'Elementor.com', 'elementor' ) && 'Editar con DIGITALÍSIMO Elements' === translate( 'Editar con Elementor', 'elementor' ), 'Fuera de las cabeceras, la identidad reescribe el dominio elementor.' );
	// Las cabeceras del plugin externo, traducidas como lo hace WordPress, no cambian de marca (ruta relativa y absoluta).
	$header = array( 'Name' => 'Elementor', 'Description' => 'The Elementor Website Builder has it all', 'Author' => 'Elementor.com', 'TextDomain' => 'elementor' );
	foreach ( array( 'elementor/elementor.php', WP_PLUGIN_DIR . '/elementor/elementor.php' ) as $file ) {
		$translated = _get_plugin_data_markup_translate( $file, $header );
		check_row( 'Elementor' === $translated['Name'] && 'El maquetador Elementor lo tiene todo' === $translated['Description'] && 'Elementor.com' === $translated['Author'], 'Las cabeceras del plugin Elementor original se traducen sin la marca (' . $file . ').' );
	}
	$other = _get_plugin_data_markup_translate( 'otro/otro.php', array( 'Name' => 'Elementor', 'TextDomain' => 'elementor' ) );
	check_row( 'DIGITALÍSIMO Elements' === $other['Name'], 'La guarda sólo cubre los plugins externos del constructor, no cualquier plugin con ese texto.' );

	$plugins = array(
		'elementor/elementor.php'                => $header + array( 'AuthorURI' => 'https://elementor.com/', 'Version' => '4.3.5', 'DomainPath' => '/languages' ),
		'elementor-pro/elementor-pro.php'        => array( 'Name' => 'Elementor Pro', 'TextDomain' => 'elementor-pro', 'Version' => '3.0' ),
		'digitalisimo-elements/pro-elements.php' => array( 'Name' => 'DIGITALÍSIMO Elements', 'TextDomain' => 'pro-elements', 'Version' => '4.3.0.148' ),
	);
	$rows = $core::external_rows( $plugins );
	$row  = $rows['elementor/elementor.php'];
	check_row( 'Elementor' === $row['Name'] && 'El maquetador Elementor lo tiene todo' === $row['Description'] && 'Elementor.com' === $row['Author'] && '4.3.5' === $row['Version'] && '' === $row['TextDomain'] && '' === $row['DomainPath'], 'La fila de Elementor muestra sus cabeceras traducidas con su dominio y sin la marca, y sin dominio para que WordPress no las vuelva a traducir.' );
	check_row( 'Elementor Pro' === $rows['elementor-pro/elementor-pro.php']['Name'] && '' === $rows['elementor-pro/elementor-pro.php']['TextDomain'], 'La fila de Elementor Pro también.' );
	check_row( $plugins['digitalisimo-elements/pro-elements.php'] === $rows['digitalisimo-elements/pro-elements.php'], 'Las demás filas no cambian.' );
	check_row( true === $GLOBALS['brand_on'], 'La identidad vuelve a quedar enganchada después.' );
	check_row( array( 'x' => 1 ) === $core::external_rows( array( 'x' => 1 ) ) && 'no' === $core::external_rows( 'no' ), 'Sin esas filas no se toca nada.' );

	ob_start();
	$core::external_row_note( 'elementor/elementor.php', $row );
	$note = ob_get_clean();
	check_row( false !== strpos( $note, 'plugin-update-tr digitalisimo-plugin-note' ) && false !== strpos( $note, 'data-plugin="elementor/elementor.php"' ) && false === strpos( $note, 'update-message' ) && false !== strpos( $note, 'colspan="3"' ) && false !== strpos( $note, 'plugin Elementor original' ) && false !== strpos( $note, 'puedes borrarlo' ), 'La nota es una fila propia (sin la clase que updates.js sobrescribe), con el ancho real de la tabla, y dice que es el original y que puede borrarse.' );
	ob_start();
	$core::external_row_note( 'otro/otro.php', array() );
	check_row( '' === ob_get_clean(), 'Otra fila no recibe nota.' );
	$GLOBALS['opt']['active_plugins'][] = 'elementor-pro/elementor-pro.php';
	check_row( false !== strpos( $core::external_note_text( 'elementor-pro/elementor-pro.php' ), 'Está activo' ) && false === strpos( $core::external_note_text( 'elementor-pro/elementor-pro.php' ), 'puedes borrarlo' ), 'Con Elementor Pro activo la nota no lo da por borrable.' );

	// Borrar el plugin externo no ejecuta su rutina de desinstalación (que incluiría su archivo principal sobre el núcleo).
	$uninstall = array( 'elementor/elementor.php' => array( 'Elementor\\Maintenance', 'uninstall' ), 'elementor-pro/elementor-pro.php' => 'x', 'otro/otro.php' => 'y' );
	check_row( array( 'otro/otro.php' => 'y' ) === $core::skip_external_uninstall( $uninstall ) && false === $core::skip_external_uninstall( false ), 'Las rutinas de desinstalación de Elementor y Elementor Pro se saltan; las demás siguen.' );

	// Dos carpetas del mismo plugin propio (misma Update URI) se detectan; una sola, o plugins ajenos, no.
	$uri  = 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-seo';
	$dups = $core::duplicate_copies( array( 'digitalisimo-seo/digitalisimo-integrations.php' => array( 'UpdateURI' => $uri ), 'digitalisimo-seo-1.0.226/digitalisimo-integrations.php' => array( 'UpdateURI' => $uri ), 'digitalisimo-elements/pro-elements.php' => array( 'UpdateURI' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-elements' ), 'akismet/akismet.php' => array( 'UpdateURI' => 'https://wordpress.org/plugins/akismet/' ), 'otro/otro.php' => array() ) );
	check_row( array( $uri => array( 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-seo-1.0.226/digitalisimo-integrations.php' ) ) === $dups, 'Sólo el plugin con dos carpetas instaladas se avisa.' );
	echo "DIGITALÍSIMO Elements: el plugin Elementor original se lista con sus cabeceras en toda la administración, con nota según su estado, se puede borrar, y las copias duplicadas de plugins propios se avisan.\n";
	unlink( $dir . '/elementor/elementor.php' ); rmdir( $dir . '/elementor' ); rmdir( $dir );
}
