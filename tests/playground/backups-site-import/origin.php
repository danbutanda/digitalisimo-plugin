<?php
/**
 * Fase «origen» de backups-site-import.sh (Playground con run-blueprint): un WordPress individual `origen.test` con
 * contenido de cada autor, un medio, usuarios con roles (dueña, editora, autora, suscriptora y el admin de Playground),
 * un plugin y un tema que la red no tiene, y su respaldo completo hecho con el motor de trabajos de Backups. Deja el
 * paquete en /digi-probe/origen.zip y lo que la red debe comprobar en /digi-probe/origen.json; un fallo, en
 * /digi-probe/setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-probe';
try {
	$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups: ' . $activated->get_error_message() );
	update_option( 'blogname', 'Tienda del origen' );
	update_option( 'permalink_structure', '' );
	$users = array();
	$passwords = array();
	foreach ( array( 'duena' => array( 'administrator', 'duena@origen.test' ), 'edita' => array( 'editor', 'edita@ejemplo.test' ), 'autora' => array( 'author', 'autora@origen.test' ), 'socia' => array( 'subscriber', 'socia@origen.test' ) ) as $login => $info ) {
		$passwords[ $login ] = 'Origen-' . $login . '-' . wp_generate_password( 10, false );
		$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $passwords[ $login ], 'user_email' => $info[1], 'role' => $info[0], 'display_name' => ucfirst( $login ) . ' del origen', 'first_name' => ucfirst( $login ) ) );
		if ( is_wp_error( $id ) ) throw new RuntimeException( 'No se creó ' . $login . ': ' . $id->get_error_message() );
		$users[ $login ] = $id;
	}
	// Un medio (PNG de 1×1) en uploads/2026/11 con su adjunto.
	$upload = wp_upload_dir( '2026/11' );
	wp_mkdir_p( $upload['path'] );
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
	file_put_contents( $upload['path'] . '/origen.png', $png );
	$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen del origen', 'post_status' => 'inherit', 'post_author' => $users['autora'] ), $upload['path'] . '/origen.png' );
	wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['path'] . '/origen.png' ) );
	$media = $upload['url'] . '/origen.png';
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $users['autora'], 'post_title' => 'Entrada de la autora del origen', 'post_content' => '<p>Contenido del origen con su imagen:</p><img src="' . esc_url( $media ) . '" alt="origen">' ) );
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $users['duena'], 'post_title' => 'Página de la dueña del origen', 'post_content' => '<p>Página de la dueña.</p>' ) );
	update_post_meta( $post, '_edit_last', (string) $users['edita'] );
	wp_insert_comment( array( 'comment_post_ID' => $post, 'user_id' => $users['socia'], 'comment_author' => 'Socia', 'comment_author_email' => 'socia@origen.test', 'comment_content' => 'Comentario de la socia.', 'comment_approved' => 1 ) );
	// Un plugin y un tema que la red no tiene, activos en el origen.
	wp_mkdir_p( WP_PLUGIN_DIR . '/plugin-origen' );
	file_put_contents( WP_PLUGIN_DIR . '/plugin-origen/plugin-origen.php', "<?php\n/*\nPlugin Name: Plugin del origen\nVersion: 1.0\n*/\nadd_action( 'wp_footer', function() { echo '<!-- plugin-origen activo -->'; } );\n" );
	wp_clean_plugins_cache( false );
	$activated = activate_plugin( 'plugin-origen/plugin-origen.php' );
	if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó el plugin del origen: ' . $activated->get_error_message() );
	$theme = get_theme_root() . '/tema-origen';
	wp_mkdir_p( $theme );
	file_put_contents( $theme . '/style.css', "/*\nTheme Name: Tema del origen\nVersion: 1.0\n*/\n" );
	file_put_contents( $theme . '/index.php', "<!doctype html><html><head><?php wp_head(); ?></head><body><!-- tema-origen --><?php while ( have_posts() ) { the_post(); echo '<h2>'; the_title(); echo '</h2>'; the_content(); } wp_footer(); ?></body></html>\n" );
	if ( function_exists( 'wp_clean_themes_cache' ) ) wp_clean_themes_cache( false );
	switch_theme( 'tema-origen' );
	// El respaldo completo, con el motor de trabajos (como «Respaldar ahora»).
	$queued = Digitalisimo_Backups_Jobs::enqueue( Digitalisimo_Backups_Jobs::spec( array( 'policy' => 'manual', 'destination' => 'local', 'retention' => 'none', 'via' => 'test' ) ) );
	$id = $queued['job']['id'];
	for ( $i = 0; $i < 400; $i++ ) {
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
		$job = Digitalisimo_Backups_Jobs::get( $id );
		if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
	}
	if ( ! $job || 'success' !== $job['status'] ) throw new RuntimeException( 'El respaldo no terminó: ' . wp_json_encode( $job['error'] ?? $job['status'] ?? null ) );
	$file = rtrim( Digitalisimo_Backups::storage_directory(), '/' ) . '/' . basename( (string) $job['spec']['file'] );
	if ( ! copy( $file, $dir . '/origen.zip' ) ) throw new RuntimeException( 'No se copió el paquete.' );
	file_put_contents( $dir . '/origen.json', wp_json_encode( array( 'home' => home_url(), 'passwords' => $passwords, 'media_sha' => hash( 'sha256', $png ), 'post' => 'Entrada de la autora del origen', 'page' => 'Página de la dueña del origen', 'file' => basename( $file ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
