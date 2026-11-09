<?php
/**
 * Inventario y cambios del ensayo de la restauración robusta (lo carga el mu-plugin con el testigo del ensayo).
 * El inventario es comparable entre dos momentos: por tabla, filas y SHA-256 de las filas ordenadas (sin lo volátil:
 * transitorios, cron, sesiones, reglas de reescritura y las opciones de Backups, que una restauración repone a propósito)
 * y, por archivo bajo la raíz, su SHA-256 (sin la base de datos SQLite activa, el mantenimiento ni áreas de trabajo).
 */
function digi_lab_inventory() {
	global $wpdb;
	$volatile = '/^(_site_transient_|_transient_|cron$|digitalisimo_backups_|rewrite_rules$|recently_activated$|doing_cron$|session_tokens$|wp_[0-9]*_?user-settings|auto_core_update|_wp_suggested_policy|can_compress_scripts$|db_upgraded$|wp_user_roles_update$|finished_updating_comment_type$|user_count$|_wp_session_|admin_email_lifespan$)/';
	$tables = array();
	foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) {
		if ( preg_match( '/dgbk[ro]_/', $table ) ) { $tables[ $table ] = 'TABLA TEMPORAL O VIEJA'; continue; }
		$lines = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM `' . str_replace( '`', '``', $table ) . '`', ARRAY_A ) as $row ) {
			$key = $row['option_name'] ?? $row['meta_key'] ?? null;
			if ( null !== $key && preg_match( $volatile, (string) $key ) ) continue;
			ksort( $row );
			$lines[] = json_encode( $row );
		}
		sort( $lines );
		$tables[ $table ] = array( 'rows' => count( $lines ), 'sha256' => hash( 'sha256', implode( "\n", $lines ) ) );
	}
	ksort( $tables );
	$root = rtrim( ABSPATH, '/' );
	$files = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
	foreach ( $iterator as $entry ) {
		if ( $entry->isLink() || ! $entry->isFile() ) continue;
		$relative = substr( $entry->getPathname(), strlen( $root ) + 1 );
		if ( 0 === strpos( $relative, 'wp-content/database/' ) || '.maintenance' === $relative || 0 === strpos( $relative, '.digitalisimo-restore-' ) || false !== strpos( $relative, '/cache/' ) ) continue;
		$files[ $relative ] = hash_file( 'sha256', $entry->getPathname() );
	}
	ksort( $files );
	return array( 'tables' => $tables, 'files' => $files, 'blogname' => get_option( 'blogname' ) );
}
/** Lo que cambia después del respaldo: base de datos y archivos, dentro y fuera de las raíces que gestiona. */
function digi_lab_mutate( $round ) {
	update_option( 'blogname', 'Cambiado después del respaldo ' . $round );
	add_option( 'digi_lab_intrusa_' . $round, 'no estaba en el respaldo' );
	wp_update_post( array( 'ID' => 1, 'post_title' => 'Título cambiado ' . $round ) );
	wp_insert_post( array( 'post_title' => 'Entrada nueva ' . $round, 'post_status' => 'publish', 'post_content' => 'después del respaldo' ) );
	$uploads = wp_upload_dir( null, false )['basedir'];
	wp_mkdir_p( $uploads . '/2026/11' );
	file_put_contents( $uploads . '/2026/11/inyectado-' . $round . '.php', "<?php system( \$_GET['c'] ?? '' );\n" );
	@unlink( $uploads . '/2026/03/medio-2.jpg' );
	wp_mkdir_p( WP_PLUGIN_DIR . '/nuevo-' . $round );
	file_put_contents( WP_PLUGIN_DIR . '/nuevo-' . $round . '/nuevo.php', "<?php /* Plugin Name: Nuevo */\n" );
	file_put_contents( WP_PLUGIN_DIR . '/demo/inc/a.php', "<?php // a cambiado " . $round . "\n" );
	@unlink( get_stylesheet_directory() . '/extra-ensayo.txt' );
	file_put_contents( ABSPATH . 'robots-ensayo.txt', "fuera de las raíces gestionadas\n" );
	if ( is_multisite() ) {
		foreach ( get_sites( array( 'number' => 10 ) ) as $site ) {
			if ( (int) $site->blog_id === (int) get_main_site_id() ) continue;
			switch_to_blog( $site->blog_id );
			update_option( 'blogname', 'Subsitio cambiado ' . $round );
			restore_current_blog();
		}
	}
	return array( 'round' => $round, 'blogname' => get_option( 'blogname' ) );
}
