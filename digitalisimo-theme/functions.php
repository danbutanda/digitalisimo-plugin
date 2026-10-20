<?php
/** DIGITALÍSIMO: carga las piezas del tema. Sin pantallas, sin JavaScript propio y sin opciones. */
defined( 'ABSPATH' ) || exit;
define( 'DIGITALISIMO_THEME_VERSION', '1.0.0' ); // Igual que « * Version:» de style.css (tests/theme-structure.php).
foreach ( array( 'switch', 'setup', 'template-tags', 'elements', 'assets', 'fallbacks' ) as $digitalisimo_part ) {
	require get_template_directory() . '/inc/' . $digitalisimo_part . '.php';
}
unset( $digitalisimo_part );
// El actualizador sólo hace falta donde WordPress revisa o instala actualizaciones: la administración, el cron y WP-CLI.
if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require get_template_directory() . '/inc/class-updater.php';
	Digitalisimo_Theme_Updater::init();
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require get_template_directory() . '/inc/cli.php';
}
