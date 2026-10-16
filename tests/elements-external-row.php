<?php
/**
 * El plugin Elementor original instalado junto al núcleo incluido no se toca: su fila en la lista de plugins muestra sus
 * propias cabeceras (traducidas con su dominio, sin los filtros de identidad que la convertían en un segundo
 * «DIGITALÍSIMO Elements»), recibe una nota aditiva, las demás filas no cambian, y borrarlo no ejecuta su rutina de
 * desinstalación (que cargaría su archivo principal sobre el núcleo incluido).
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	function check_row( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['brand_on'] = true;
	function add_action() {}
	function add_filter( $hook ) { if ( 'gettext' === $hook ) { $GLOBALS['brand_on'] = true; } }
	function remove_filter( $hook ) { if ( 'gettext' === $hook ) { $GLOBALS['brand_on'] = false; return true; } return false; }
	// Como WordPress: traduce (aquí, identidad) y aplica el filtro gettext si está enganchado.
	function translate( $text, $domain ) { $t = 'The Elementor Website Builder has it all' === $text ? 'El maquetador Elementor lo tiene todo' : $text; return $GLOBALS['brand_on'] ? Digitalisimo_Elements_Identity::gettext( $t, $text, $domain ) : $t; }
	function current_user_can() { return true; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
	function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-core.php';
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-identity.php';
	$core = 'Digitalisimo_Elements_Core';
	$id   = 'Digitalisimo_Elements_Identity';

	// El origen del falso duplicado: WordPress traduce las cabeceras con el dominio del plugin y la identidad las reescribe.
	check_row( 'DIGITALÍSIMO Elements' === translate( 'Elementor', 'elementor' ) && 'DIGITALÍSIMO Elements.com' === translate( 'Elementor.com', 'elementor' ), 'Con la identidad enganchada, las cabeceras de Elementor se leerían como DIGITALÍSIMO Elements.' );

	$plugins = array(
		'elementor/elementor.php'                => array( 'Name' => 'Elementor', 'Description' => 'The Elementor Website Builder has it all', 'Author' => 'Elementor.com', 'AuthorURI' => 'https://elementor.com/', 'Version' => '4.3.5', 'TextDomain' => 'elementor', 'DomainPath' => '/languages' ),
		'elementor-pro/elementor-pro.php'        => array( 'Name' => 'Elementor Pro', 'TextDomain' => 'elementor-pro', 'Version' => '3.0' ),
		'digitalisimo-elements/pro-elements.php' => array( 'Name' => 'DIGITALÍSIMO Elements', 'TextDomain' => 'pro-elements', 'Version' => '4.3.0.147' ),
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
	check_row( false !== strpos( $note, 'plugin-update-tr' ) && false !== strpos( $note, 'puedes borrarlo' ) && false !== strpos( $note, 'plugin Elementor original' ) && false !== strpos( $note, 'colspan="4"' ), 'La nota bajo la fila dice que es el plugin original y que no hace falta.' );
	ob_start();
	$core::external_row_note( 'otro/otro.php', array() );
	check_row( '' === ob_get_clean(), 'Otra fila no recibe nota.' );

	// Borrar el plugin externo no ejecuta su rutina de desinstalación (que incluiría su archivo principal sobre el núcleo).
	$uninstall = array( 'elementor/elementor.php' => array( 'Elementor\\Maintenance', 'uninstall' ), 'elementor-pro/elementor-pro.php' => 'x', 'otro/otro.php' => 'y' );
	check_row( array( 'otro/otro.php' => 'y' ) === $core::skip_external_uninstall( $uninstall ) && false === $core::skip_external_uninstall( false ), 'Las rutinas de desinstalación de Elementor y Elementor Pro se saltan; las demás siguen.' );
	echo "DIGITALÍSIMO Elements: el plugin Elementor original se lista con sus cabeceras, con nota, y se puede borrar.\n";
}
