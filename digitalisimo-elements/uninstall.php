<?php
/**
 * Al desinstalar DIGITALÍSIMO Elements (borrar el plugin, no desactivarlo): se quita lo que deja la marca propia
 * (docs/MARCA_PROPIA.md), en cada sitio y en la red, con el mismo patrón que IA Tools (uninstall.php, sitio por sitio):
 * - el interruptor: `digitalisimo_elements_marca` (cada sitio) y `digitalisimo_elements_marca_network` (la red);
 * - la cuenta de uso del MCP viejo: `digitalisimo_elements_mcp_legacy`;
 * - el transitorio de complementos de terceros: `digitalisimo_elements_marca_addons`;
 * - del paquete transformado, el registro de despliegues fallidos (`digitalisimo_elements_marca_despliegue`) y su tarea
 *   de limpieza programada (`digitalisimo_elements_marca_limpiar`). La carpeta de originales desplegados
 *   (`uploads/digitalisimo-elements/base-*`) es de la fase del paquete transformado y no se toca aquí.
 * Lo demás (páginas, plantillas, ajustes del constructor y de Elements) se conserva, como siempre: volver a instalar el
 * plugin deja el sitio igual. Nunca carga el plugin ni el núcleo.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Lo de la marca propia en el sitio en curso. */
function digitalisimo_elements_marca_uninstall_site() {
	foreach ( array( 'digitalisimo_elements_marca', 'digitalisimo_elements_mcp_legacy', 'digitalisimo_elements_marca_despliegue' ) as $option ) {
		delete_option( $option );
	}
	delete_transient( 'digitalisimo_elements_marca_addons' );
	wp_clear_scheduled_hook( 'digitalisimo_elements_marca_limpiar' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $digitalisimo_elements_blog ) {
		switch_to_blog( (int) $digitalisimo_elements_blog );
		digitalisimo_elements_marca_uninstall_site();
		restore_current_blog();
	}
	delete_site_option( 'digitalisimo_elements_marca_network' );
} else {
	digitalisimo_elements_marca_uninstall_site();
}
