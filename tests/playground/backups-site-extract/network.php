<?php
/**
 * Fase «red» de backups-site-extract.sh (Playground con run-blueprint, un proceso PHP por paso). DIGI_STEP nombra el
 * paso y DIGI_BLOG el sitio que carga la petición (1 principal, 2 en /dos/, 3 en /tres/):
 *   setup:1    red por subdirectorios con los sitios 2 y 3; Backups activo en la red y DIGITALÍSIMO Elements en los
 *              sitios 2 y 3; usuarios en varios roles (una dueña y una editora del sitio 2, un usuario sólo del sitio
 *              2, una administradora sólo del sitio 3, un superadministrador que no es miembro y otro que es autor
 *              del sitio 2); un medio en uploads/sites/2 y una página de Elements con imagen, fondo y enlace.
 *   css:2      una petición del sitio 2: Elements genera el CSS de la página (con la URL del medio en la red).
 *   extract:1  la red: «Extraer como WordPress individual» del sitio 2 por el handler admin-post real (superadministrador,
 *              nonce, alcance de red), la cola avanzada por ticks como WP-Cron, y el inventario del sitio 2 que la
 *              fase «destino» compara.
 * Cada paso deja en result.json lo que observó y sus problemas; nada de esto se publica.
 */
$digi_step = (string) getenv( 'DIGI_STEP' );
$digi_blog = (int) getenv( 'DIGI_BLOG' );
$digi_path = array( 1 => '/', 2 => '/dos/', 3 => '/tres/' )[ $digi_blog ] ?? '/';
$_SERVER['HTTP_HOST'] = 'red.test';
if ( 'extract' === $digi_step ) {
	define( 'WP_ADMIN', true );
	define( 'WP_NETWORK_ADMIN', true );
	$_SERVER['REQUEST_URI'] = '/wp-admin/network/admin-post.php';
} else {
	$_SERVER['REQUEST_URI'] = $digi_path;
}
const DIGI_DIR = '/digi-probe';
register_shutdown_function( static function () use ( $digi_step ) {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) file_put_contents( DIGI_DIR . '/fatal.log', $digi_step . ' ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line'] . "\n", FILE_APPEND );
} );
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';

class Digi_Extract_Redirect extends Exception {}
function digi_save( $key, array $problems, array $data = array() ) {
	$file = DIGI_DIR . '/result.json';
	$result = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	$result[ $key ] = array( 'problems' => array_values( $problems ), 'data' => $data );
	file_put_contents( $file, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}
function digi_state( array $add = null ) {
	$file = DIGI_DIR . '/state.json';
	$state = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array();
	if ( null !== $add ) { $state = array_merge( $state, $add ); file_put_contents( $file, wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); }
	return $state;
}
/** PNG de 1×1 píxel: un medio real que el destino sirve tal cual. */
function digi_png() { return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ); }

$problems = array();
try {
	switch ( $digi_step ) {
		case 'setup':
			$network = get_network();
			$users = array();
			$passwords = array();
			$make = function( $login, $email ) use ( &$users, &$passwords ) {
				$password = 'Clave-' . $login . '-' . wp_generate_password( 12, false );
				$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'user_email' => $email, 'display_name' => ucfirst( $login ), 'role' => '' ) );
				if ( is_wp_error( $id ) ) throw new RuntimeException( 'No se creó ' . $login . ': ' . $id->get_error_message() );
				$users[ $login ] = $id;
				$passwords[ $login ] = $password;
				return $id;
			};
			$admin = get_user_by( 'login', 'admin' );
			wp_set_password( $passwords['admin'] = 'Clave-admin-' . wp_generate_password( 12, false ), $admin->ID );
			$users['admin'] = $admin->ID;
			$make( 'duena', 'duena@cliente.test' );
			$make( 'editora', 'editora@red.test' );
			$make( 'solo2', 'solo2@cliente.test' );
			$make( 'solo3', 'solo3@otro.test' );
			$make( 'otro', 'otro@red.test' );
			grant_super_admin( $users['otro'] );
			// La dueña administra el sitio 2 (su correo de administración); el superadministrador «admin» no es miembro.
			$two = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'dos/', 'title' => 'Sitio dos', 'user_id' => $users['duena'] ) );
			$three = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'tres/', 'title' => 'Sitio tres', 'user_id' => $users['solo3'] ) );
			if ( is_wp_error( $two ) || is_wp_error( $three ) ) throw new RuntimeException( 'No se crearon los sitios.' );
			remove_user_from_blog( $users['admin'], $two );
			add_user_to_blog( $two, $users['editora'], 'editor' );
			add_user_to_blog( $three, $users['editora'], 'author' );
			add_user_to_blog( $two, $users['solo2'], 'subscriber' );
			$activated = activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php', '', true );
			if ( is_wp_error( $activated ) ) throw new RuntimeException( 'No se activó Backups en la red: ' . $activated->get_error_message() );
			// Ajuste de red de DIGITALÍSIMO que el sitio 2 hereda (Tools): se materializa en el paquete.
			update_site_option( 'digitalisimo_tools_site_defaults', array( 'redirect_attachments' => 1, 'mobile_scroll' => 1, 'login_slug' => '' ) );
			update_site_option( 'otro_plugin_de_red', 'x' );
			foreach ( array( $two, $three ) as $blog ) {
				switch_to_blog( $blog );
				$on = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
				if ( is_wp_error( $on ) ) throw new RuntimeException( 'No se activó Elements en el sitio ' . $blog . ': ' . $on->get_error_message() );
				restore_current_blog();
			}
			// Contenido del sitio 2: un medio en uploads/sites/2 y una página de Elements que lo usa.
			switch_to_blog( $two );
			$upload = wp_upload_dir();
			$file = $upload['path'] . '/foto-dos.png';
			wp_mkdir_p( $upload['path'] );
			file_put_contents( $file, digi_png() );
			$media = wp_insert_attachment( array( 'post_title' => 'Foto dos', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_author' => $users['duena'] ), $file );
			wp_update_attachment_metadata( $media, wp_generate_attachment_metadata( $media, $file ) );
			$url = wp_get_attachment_url( $media );
			$contact = wp_insert_post( array( 'post_title' => 'Contacto', 'post_name' => 'contacto', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'Escríbenos.', 'post_author' => $users['editora'] ) );
			$link = get_permalink( $contact );
			$data = array( array( 'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => array( 'background_background' => 'classic', 'background_image' => array( 'url' => $url, 'id' => $media, 'source' => 'library' ) ), 'elements' => array(
				array( 'id' => 'e4f5a6b', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'image' => array( 'url' => $url, 'id' => $media, 'source' => 'library' ), 'link_to' => 'custom', 'link' => array( 'url' => $link ) ), 'elements' => array() ),
				array( 'id' => 'c7d8e9f', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Visítanos en <a href="' . esc_url( $link ) . '">contacto</a> o en <a href="' . esc_url( network_home_url( '/' ) ) . '">la red</a>.</p>' ), 'elements' => array() ),
			) ) );
			$page = wp_insert_post( array( 'post_title' => 'Inicio Elements', 'post_name' => 'inicio-elements', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<img src="' . esc_url( $url ) . '">', 'post_author' => $users['duena'] ) );
			update_post_meta( $page, '_elementor_edit_mode', 'builder' );
			update_post_meta( $page, '_elementor_template_type', 'wp-page' );
			update_post_meta( $page, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.30.0' );
			update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			// El superadministrador «admin» escribe en el sitio sin ser miembro: lo administra.
			$by_admin = wp_insert_post( array( 'post_title' => 'Aviso de la red', 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'Mantenimiento.', 'post_author' => $users['admin'] ) );
			restore_current_blog();
			switch_to_blog( $three );
			wp_insert_post( array( 'post_title' => 'Del sitio tres', 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'secreto del sitio tres', 'post_author' => $users['solo3'] ) );
			restore_current_blog();
			digi_state( array( 'users' => $users, 'passwords' => $passwords, 'two' => $two, 'three' => $three, 'media' => $media, 'media_url' => $url, 'page' => $page, 'contact' => $contact, 'by_admin' => $by_admin ) );
			if ( ! is_super_admin( $users['admin'] ) || is_user_member_of_blog( $users['admin'], $two ) ) $problems[] = 'admin debía ser superadministrador sin ser miembro del sitio 2.';
			digi_save( 'setup', $problems, array( 'sites' => array( $two, $three ), 'media_url' => $url, 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) );
			break;

		case 'css':
			$state = digi_state();
			// Desde una petición del propio sitio, WordPress da la URL de los medios con su dirección (red.test/dos/…),
			// no con la del principal: contenido con esa forma, que también debe pasar al destino.
			$site_url = wp_get_attachment_url( (int) $state['media'] );
			if ( false === strpos( $site_url, '/dos/wp-content/uploads/sites/2/' ) ) $problems[] = 'La URL del medio desde el sitio no es la esperada: ' . $site_url;
			wp_insert_post( array( 'post_title' => 'Desde el sitio', 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => '<img src="' . esc_url( $site_url ) . '">', 'post_author' => (int) $state['users']['editora'] ) );
			digi_state( array( 'media_url_site' => $site_url ) );
			$css = '';
			if ( ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) $problems[] = 'Elements no está cargado en el sitio 2.';
			else {
				$post_css = \Elementor\Core\Files\CSS\Post::create( (int) $state['page'] );
				$post_css->update();
				$path = wp_upload_dir()['basedir'] . '/elementor/css/post-' . (int) $state['page'] . '.css';
				$css = is_file( $path ) ? (string) file_get_contents( $path ) : '';
				if ( false === strpos( $css, '/sites/2/' ) ) $problems[] = 'El CSS generado no lleva la URL del medio del sitio 2: ' . substr( $css, 0, 300 );
			}
			digi_save( 'css', $problems, array( 'css' => substr( $css, 0, 400 ) ) );
			break;

		case 'extract':
			$state = digi_state();
			wp_set_current_user( (int) $state['users']['admin'] );
			// Inventario del sitio 2 que la fase «destino» compara.
			switch_to_blog( (int) $state['two'] );
			$inventory = array( 'posts' => array(), 'users' => array(), 'files' => array(), 'plugins' => (array) get_option( 'active_plugins' ), 'blogname' => get_option( 'blogname' ) );
			foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) $inventory['posts'][ $p->ID ] = array( 'title' => $p->post_title, 'type' => $p->post_type, 'author' => get_userdata( $p->post_author )->user_login, 'content' => $p->post_content );
			foreach ( get_users( array( 'blog_id' => (int) $state['two'] ) ) as $u ) $inventory['users'][ $u->user_login ] = array_values( $u->roles );
			$base = wp_upload_dir()['basedir'];
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) ) as $f ) if ( $f->isFile() && false === strpos( $f->getPathname(), '/elementor/css/' ) ) $inventory['files'][ substr( $f->getPathname(), strlen( $base ) + 1 ) ] = hash_file( 'sha256', $f->getPathname() );
			restore_current_blog();
			// «Extraer como WordPress individual» por el handler admin-post real, como lo envía la tarjeta de la red.
			add_filter( 'wp_redirect', static function ( $location ) { throw new Digi_Extract_Redirect( $location ); }, PHP_INT_MAX );
			$_POST = wp_slash( array( '_wpnonce' => wp_create_nonce( Digitalisimo_Backups_Site_Extract::ACTION ), 'action' => Digitalisimo_Backups_Site_Extract::ACTION, 'site_id' => (string) $state['two'], 'super_admins' => 'managing', 'digitalisimo_backups_network' => '1', 'digitalisimo_backup_destination' => 'local' ) );
			$_REQUEST = $_POST;
			$redirect = '';
			try { Digitalisimo_Backups::extract_request(); } catch ( Digi_Extract_Redirect $r ) { $redirect = $r->getMessage(); }
			$query = array();
			parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
			if ( 'ok' !== ( $query['backup'] ?? '' ) ) throw new RuntimeException( 'La red no encoló la extracción: ' . rawurldecode( (string) ( $query['message'] ?? $redirect ) ) );
			$jobs = Digitalisimo_Backups_Jobs::active();
			$id = $jobs ? $jobs[0]['id'] : '';
			$started = microtime( true );
			for ( $i = 0; $i < 60; $i++ ) {
				$job = Digitalisimo_Backups_Jobs::get( $id );
				if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
				Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
			}
			$view = Digitalisimo_Backups::service_job( $id );
			if ( is_wp_error( $view ) || 'success' !== $view['status'] ) throw new RuntimeException( 'La extracción no terminó: ' . wp_json_encode( is_wp_error( $view ) ? $view->get_error_message() : $view ) );
			$verify = Digitalisimo_Backups::service_verify( $view['file'] );
			if ( is_wp_error( $verify ) || 'extracted-site' !== $verify['scope'] || ! empty( $verify['compatible'] ) ) $problems[] = 'verify en la red no describe el sitio extraído como no restaurable aquí: ' . wp_json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $verify );
			file_put_contents( DIGI_DIR . '/inventory.json', wp_json_encode( $inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			digi_state( array( 'package' => $view['file'] ) );
			digi_save( 'extract', $problems, array( 'file' => $view['file'], 'seconds' => round( microtime( true ) - $started, 1 ), 'size' => (int) @filesize( DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $view['file'] ), 'extract' => is_wp_error( $verify ) ? null : $verify['extract'] ) );
			break;

		default:
			throw new RuntimeException( 'Paso desconocido: ' . $digi_step );
	}
} catch ( Throwable $e ) {
	$problems[] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	digi_save( $digi_step, $problems );
}
