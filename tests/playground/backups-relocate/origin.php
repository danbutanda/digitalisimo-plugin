<?php
/**
 * Origen del ensayo de restaurar en otro dominio (paso runPHP del blueprint de backups-relocate.sh): activa Backups y
 * Elements (en toda la red si es Multisite), sube una imagen, crea una página de Elements con esa imagen de fondo, un
 * widget de imagen, un enlace interno y una opción serializada con direcciones del sitio y, en una red, lo mismo en el
 * subsitio «uno». Corta el loopback de WP-Cron (la cola sólo avanza por POST /jobs/tick). Deja /digi-probe/setup.json
 * con lo que el ensayo necesita; un fallo queda en setup-error.txt.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$dir = '/digi-probe';
try {
	$network = is_multisite();
	foreach ( array( 'digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php' ) as $plugin ) {
		$activated = activate_plugin( $plugin, '', $network );
		if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó ' . $plugin . ': ' . $activated->get_error_message() );
	}
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	file_put_contents( WPMU_PLUGIN_DIR . '/digi-relocate-no-cron.php', "<?php\nadd_filter( 'pre_http_request', static function ( \$pre, \$args, \$url ) { return false !== strpos( (string) \$url, 'wp-cron.php' ) ? new WP_Error( 'digi_no_cron', 'WP-Cron por loopback cortado en el ensayo.' ) : \$pre; }, 10, 3 );\n" );
	$admin = get_user_by( 'login', 'admin' );
	if ( ! $admin ) throw new RuntimeException( 'Falta el usuario admin de Playground.' );
	wp_set_current_user( $admin->ID );
	$page = function( $title ) {
		update_option( 'elementor_onboarded', true );
		$image = imagecreatetruecolor( 64, 64 );
		imagefill( $image, 0, 0, imagecolorallocate( $image, 30, 90, 200 ) );
		ob_start();
		imagepng( $image );
		$upload = wp_upload_bits( 'fondo-' . get_current_blog_id() . '.png', null, ob_get_clean() );
		if ( ! empty( $upload['error'] ) ) throw new RuntimeException( 'No se subió la imagen: ' . $upload['error'] );
		$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Fondo', 'post_status' => 'inherit' ), $upload['file'] );
		wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['file'] ) );
		$url = wp_get_attachment_url( $attachment );
		$data = array( array( 'id' => 'a1b2c3d', 'elType' => 'section', 'settings' => array( 'background_background' => 'classic', 'background_image' => array( 'url' => $url, 'id' => $attachment, 'size' => '' ), 'min_height' => array( 'unit' => 'px', 'size' => 200 ) ), 'elements' => array( array( 'id' => 'e4f5a6b', 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array(
			array( 'id' => 'c7d8e9f', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola desde el origen', 'link' => array( 'url' => home_url( '/contacto/' ), 'is_external' => '', 'nofollow' => '' ) ), 'elements' => array() ),
			array( 'id' => 'f1a2b3c', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => $url, 'id' => $attachment ) ), 'elements' => array() ),
		) ) ) ) );
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'portada-elements', 'post_content' => '<a href="' . esc_url( home_url( '/contacto/' ) ) . '">Contacto</a>' ) );
		if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'No se creó la página.' );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		update_option( 'digi_relocate_probe', array( 'home' => home_url( '/' ), 'nested' => serialize( array( 'imagen' => $url ) ), 'json' => wp_json_encode( array( 'u' => $url ) ) ) );
		return array( 'id' => $id, 'path' => wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) . ( false === strpos( (string) get_permalink( $id ), '?' ) ? '' : '' ), 'link' => get_permalink( $id ), 'image' => wp_parse_url( $url, PHP_URL_PATH ) );
	};
	$pages = array( 'main' => $page( 'Portada Elements' ) );
	if ( $network ) {
		$row = get_network();
		$site = wp_insert_site( array( 'domain' => $row->domain, 'path' => $row->path . 'uno/', 'title' => 'Uno', 'user_id' => $admin->ID ) );
		if ( is_wp_error( $site ) ) throw new RuntimeException( 'No se creó el subsitio: ' . $site->get_error_message() );
		switch_to_blog( $site );
		$pages['uno'] = $page( 'Portada del subsitio' );
		restore_current_blog();
	}
	$created = WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 'digi-relocate-origen' ) );
	if ( is_wp_error( $created ) ) throw new RuntimeException( 'Sin contraseña de aplicación: ' . $created->get_error_message() );
	global $wpdb;
	file_put_contents( $dir . '/setup.json', wp_json_encode( array( 'network' => $network, 'password' => $created[0], 'pages' => $pages, 'prefix' => $wpdb->base_prefix, 'home' => home_url(), 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION . ( defined( 'ELEMENTOR_VERSION' ) ? ' · núcleo de Elements ' . ELEMENTOR_VERSION : '' ) ) ) );
} catch ( Throwable $e ) {
	file_put_contents( $dir . '/setup-error.txt', $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}
