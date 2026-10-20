<?php
/** Despachador único: cada tipo de página cede primero a la plantilla del constructor y, si no la hay, pinta su respaldo. */
defined( 'ABSPATH' ) || exit;
get_header();
if ( is_singular() ) {
	if ( ! digitalisimo_theme_location( 'single' ) ) {
		get_template_part( 'template-parts/single' );
	}
} elseif ( is_archive() || is_home() ) {
	if ( ! digitalisimo_theme_location( 'archive' ) ) {
		get_template_part( 'template-parts/archive' );
	}
} elseif ( is_search() ) {
	if ( ! digitalisimo_theme_location( 'archive' ) ) {
		get_template_part( 'template-parts/search' );
	}
} elseif ( ! digitalisimo_theme_location( 'single' ) ) {
	get_template_part( 'template-parts/404' );
}
get_footer();
