<?php
/**
 * Origen del ensayo de UpdraftPlus (paso runPHP del blueprint de backups-updraft.sh): la sonda como mu-plugin,
 * UpdraftPlus 1.26.14 activo (en toda la red si es Multisite) y contenido que lleva la dirección del sitio: una entrada
 * con un enlace y una imagen de la biblioteca, una página, un plugin propio activo, un mu-plugin y, en una red, el
 * subsitio «uno» con su entrada y su imagen. Deja /digi-probe/setup.json; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-probe';
try {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( $dir . '/probe.php', WPMU_PLUGIN_DIR . '/digi-ud-probe.php' ) ) throw new RuntimeException( 'No se copió la sonda.' );
	file_put_contents( WPMU_PLUGIN_DIR . '/mu-origen.php', "<?php\n// mu-plugin del sitio de origen de UpdraftPlus\n" );
	$network = is_multisite();
	wp_mkdir_p( WP_PLUGIN_DIR . '/demo-origen' );
	file_put_contents( WP_PLUGIN_DIR . '/demo-origen/demo-origen.php', "<?php\n/* Plugin Name: Demo del origen de UpdraftPlus */\n" );
	foreach ( array( 'updraftplus/updraftplus.php', 'demo-origen/demo-origen.php' ) as $plugin ) {
		$activated = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $activated->get_error_message() );
	}
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	// Un PNG de 1×1 (sin GD): la imagen de la biblioteca.
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
	$content = function( $label ) use ( $png ) {
		$uploads = wp_upload_dir( '2026/11' );
		wp_mkdir_p( $uploads['path'] );
		$file = $uploads['path'] . '/ud-' . sanitize_title( $label ) . '.png';
		file_put_contents( $file, $png );
		$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Imagen ' . $label, 'post_status' => 'inherit' ), $file );
		if ( ! $attachment || is_wp_error( $attachment ) ) throw new RuntimeException( 'No se creó la imagen de ' . $label . '.' );
		update_post_meta( $attachment, '_wp_attached_file', _wp_relative_upload_path( $file ) );
		$home = home_url();
		$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Entrada de UpdraftPlus ' . $label, 'post_content' => '<p><a href="' . $home . '/contacto/">Contacto en ' . $home . '</a></p><p><img src="' . $uploads['url'] . '/ud-' . sanitize_title( $label ) . '.png" alt=""></p>' ) );
		if ( ! $post || is_wp_error( $post ) ) throw new RuntimeException( 'No se creó la entrada de ' . $label . '.' );
		wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Contacto', 'post_name' => 'contacto', 'post_content' => 'Escríbenos.' ) );
		return array( 'post' => $post, 'image' => $uploads['url'] . '/ud-' . sanitize_title( $label ) . '.png' );
	};
	update_option( 'blogname', 'Sitio de origen UpdraftPlus' );
	$main = $content( 'principal' );
	$sites = array();
	if ( $network ) {
		$site = wpmu_create_blog( parse_url( home_url(), PHP_URL_HOST ), '/uno/', 'Subsitio uno', $admin->ID );
		if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
		switch_to_blog( $site );
		$sites['uno'] = $content( 'uno' );
		restore_current_blog();
	}
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'home' => home_url(), 'network' => $network, 'main' => $main, 'sites' => $sites ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
