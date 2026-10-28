<?php
/**
 * Un filtro de WordPress enganchado con add_action() pierde su valor: wp_update_attachment_metadata
 * enganchado así borraba la metadata de cada adjunto. Comprueba por tokens que los filtros de núcleo
 * que transforman datos sólo se enganchan con add_filter().
 */
$filters = array( 'wp_update_attachment_metadata', 'wp_generate_attachment_metadata', 'wp_insert_post_data', 'wp_insert_attachment_data', 'pre_update_option', 'the_content', 'wp_robots', 'robots_txt', 'wp_handle_upload', 'wp_handle_upload_prefilter', 'sanitize_file_name', 'upload_mimes', 'wp_get_attachment_image_attributes', 'wp_calculate_image_srcset' );
$bad = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../digitalisimo-seo', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( 'php' !== $file->getExtension() ) continue;
	$code = file_get_contents( $file->getPathname() );
	foreach ( preg_split( '/\R/', $code ) as $n => $line ) {
		$hooks = array();
		// add_action( 'gancho', … ) y foreach ( array( 'a', 'b' ) as $hook ) add_action( $hook, … ).
		if ( preg_match_all( "/add_action\\(\\s*'([\\w\\/-]+)'/", $line, $m ) ) $hooks = array_merge( $hooks, $m[1] );
		if ( preg_match_all( '/foreach\\s*\\(\\s*array\\(([^)]*)\\)\\s*as\\s*\\$\\w+\\s*\\)\\s*add_action/', $line, $m ) ) foreach ( $m[1] as $list ) if ( preg_match_all( "/'([\\w\\/-]+)'/", $list, $names ) ) $hooks = array_merge( $hooks, $names[1] );
		foreach ( array_intersect( $hooks, $filters ) as $filter ) $bad[] = basename( $file->getPathname() ) . ':' . ( $n + 1 ) . ' ' . $filter;
	}
}
if ( $bad ) { fwrite( STDERR, "Filtros enganchados como acción:\n" . implode( "\n", $bad ) . "\n" ); throw new RuntimeException( 'Un filtro de núcleo se engancha con add_action().' ); }
echo "Ganchos: ningún filtro de núcleo se engancha como acción.\n";
