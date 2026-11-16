<?php
/**
 * Fase «red» de backups-network-to-single.sh (Playground con run-blueprint, un proceso PHP por paso; DIGI_STEP nombra el
 * paso). Una red por subdirectorios en red.test con el sitio principal y los sitios /dos/ y /tres/:
 *   setup   usuarios con roles distintos en cada sitio (una editora y una autora del 2, un suscriptor del 2, una
 *           administradora del 3, una editora del principal y el superadministrador «admin»), con sus contraseñas;
 *           «red-plugin» activado en la red y un plugin propio de cada sitio; un tema por sitio (el del 2, hijo); en cada
 *           sitio una portada con un medio de su carpeta (uploads/ el principal, uploads/sites/N los demás); Backups en la
 *           red y, en mu-plugins, la falla que el destino enciende con /digi-probe/falla (aquí no existe).
 *   backup  respaldo completo de la red con el motor real (la cola avanzada por ticks), y el inventario de los sitios 1 y
 *           2 que la fase «destino» compara (entradas, usuarios con sus roles, medios con su SHA-256, plugins y tema).
 * Cada paso deja en result.json lo que observó y sus problemas.
 */
$digi_step = (string) getenv( 'DIGI_STEP' );
$_SERVER['HTTP_HOST'] = 'red.test';
$_SERVER['REQUEST_URI'] = '/';
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
require_once ABSPATH . 'wp-admin/includes/theme.php';

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
/** PNG de 1×1 píxel con un color propio de cada sitio: medios distintos con su SHA-256. */
function digi_png( $seed ) {
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
	return $png . str_repeat( "\0", (int) $seed ); // Bytes de más tras IEND: el navegador los ignora y el SHA-256 cambia.
}
/** Un tema clásico mínimo que dice su nombre, el título y el contenido de cada entrada (y los plugins, en wp_footer). */
function digi_theme( $slug, $parent = '' ) {
	$dir = get_theme_root() . '/' . $slug;
	wp_mkdir_p( $dir );
	file_put_contents( $dir . '/style.css', "/*\nTheme Name: " . $slug . "\n" . ( '' !== $parent ? 'Template: ' . $parent . "\n" : '' ) . "*/\n" );
	file_put_contents( $dir . '/index.php', "<!doctype html><html><head><meta charset=\"utf-8\"><?php wp_head(); ?></head><body><p id=\"tema\">" . $slug . "</p><?php while ( have_posts() ) { the_post(); echo '<h2>' . esc_html( get_the_title() ) . '</h2>'; the_content(); } wp_footer(); ?></body></html>\n" );
}
function digi_plugin( $slug ) {
	wp_mkdir_p( WP_PLUGIN_DIR . '/' . $slug );
	file_put_contents( WP_PLUGIN_DIR . '/' . $slug . '/' . $slug . '.php', "<?php\n/* Plugin Name: " . $slug . " */\nadd_action( 'wp_footer', static function () { echo '<p class=\"digi-plugin\">" . $slug . "</p>'; } );\n" );
}

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
			foreach ( array( 'editora' => 'editora@red.test', 'autora' => 'autora@red.test', 'suscriptor' => 'suscriptor@cliente.test', 'solo3' => 'solo3@otro.test', 'jefa' => 'jefa@red.test' ) as $login => $email ) $make( $login, $email );
			$two = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'dos/', 'title' => 'Sitio dos', 'user_id' => $users['editora'] ) );
			$three = wp_insert_site( array( 'domain' => $network->domain, 'path' => $network->path . 'tres/', 'title' => 'Sitio tres', 'user_id' => $users['solo3'] ) );
			if ( is_wp_error( $two ) || is_wp_error( $three ) ) throw new RuntimeException( 'No se crearon los sitios.' );
			remove_user_from_blog( $users['admin'], $two );
			remove_user_from_blog( $users['editora'], $two );
			add_user_to_blog( $two, $users['editora'], 'editor' );
			add_user_to_blog( $two, $users['autora'], 'author' );
			add_user_to_blog( $two, $users['suscriptor'], 'subscriber' );
			add_user_to_blog( 1, $users['jefa'], 'editor' );
			// Plugins: uno activado en la red, uno propio de cada sitio; Backups en la red.
			foreach ( array( 'red-plugin', 'plugin-uno', 'plugin-dos', 'plugin-tres' ) as $slug ) digi_plugin( $slug );
			foreach ( array( 'digitalisimo-backups/digitalisimo-backups.php', 'red-plugin/red-plugin.php' ) as $file ) { $on = activate_plugin( $file, '', true ); if ( is_wp_error( $on ) ) throw new RuntimeException( 'No se activó ' . $file . ' en la red: ' . $on->get_error_message() ); }
			// Temas: uno por sitio (el del sitio 2, hijo de tema-base).
			foreach ( array( 'tema-uno' => '', 'tema-base' => '', 'tema-dos' => 'tema-base', 'tema-tres' => '' ) as $slug => $parent ) digi_theme( $slug, $parent );
			update_site_option( 'allowedthemes', array( 'tema-uno' => true, 'tema-base' => true, 'tema-dos' => true, 'tema-tres' => true ) );
			// La falla que el destino enciende (mu-plugin que viaja con la red; aquí /digi-probe/falla no existe).
			wp_mkdir_p( WPMU_PLUGIN_DIR );
			copy( DIGI_DIR . '/falla.php', WPMU_PLUGIN_DIR . '/digi-n2s-falla.php' );
			$sites = array( 1 => array( 'plugin' => 'plugin-uno', 'theme' => 'tema-uno', 'author' => 'jefa' ), $two => array( 'plugin' => 'plugin-dos', 'theme' => 'tema-dos', 'author' => 'editora' ), $three => array( 'plugin' => 'plugin-tres', 'theme' => 'tema-tres', 'author' => 'solo3' ) );
			$pages = array();
			$media = array();
			foreach ( $sites as $blog => $site ) {
				switch_to_blog( $blog );
				$on = activate_plugin( $site['plugin'] . '/' . $site['plugin'] . '.php' );
				if ( is_wp_error( $on ) ) throw new RuntimeException( 'No se activó ' . $site['plugin'] . ': ' . $on->get_error_message() );
				switch_theme( $site['theme'] );
				$upload = wp_upload_dir();
				wp_mkdir_p( $upload['path'] );
				$file = $upload['path'] . '/portada-' . $blog . '.png';
				file_put_contents( $file, digi_png( $blog ) );
				$id = wp_insert_attachment( array( 'post_title' => 'Portada ' . $blog, 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_author' => $users[ $site['author'] ] ), $file );
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
				$url = wp_get_attachment_url( $id );
				$page = wp_insert_post( array( 'post_title' => 'Portada del sitio ' . $blog, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<p>Contenido del sitio ' . $blog . '.</p><img src="' . esc_url( $url ) . '" alt="portada"> <a href="' . esc_url( home_url( '/contacto/' ) ) . '">Contacto</a>', 'post_author' => $users[ $site['author'] ] ) );
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $page );
				wp_insert_post( array( 'post_title' => 'Entrada del sitio ' . $blog, 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => 'Texto del sitio ' . $blog, 'post_author' => $users[ 2 === $blog || $two === $blog ? 'autora' : $site['author'] ] ) );
				$pages[ $blog ] = $page;
				$media[ $blog ] = array( 'id' => $id, 'url' => $url );
				restore_current_blog();
			}
			digi_state( array( 'users' => $users, 'passwords' => $passwords, 'two' => $two, 'three' => $three, 'pages' => $pages, 'media' => $media ) );
			if ( 2 !== (int) $two ) $problems[] = 'El sitio dos no es el 2: ' . $two;
			digi_save( 'setup', $problems, array( 'sites' => array( 1, $two, $three ), 'versions' => 'WordPress ' . $GLOBALS['wp_version'] . ' · PHP ' . PHP_VERSION ) );
			break;

		case 'backup':
			$state = digi_state();
			wp_set_current_user( (int) $state['users']['admin'] );
			$inventory = array();
			foreach ( array( 1, (int) $state['two'] ) as $blog ) {
				switch_to_blog( $blog );
				$site = array( 'posts' => array(), 'users' => array(), 'files' => array(), 'plugins' => (array) get_option( 'active_plugins' ), 'theme' => get_option( 'stylesheet' ), 'template' => get_option( 'template' ), 'blogname' => get_option( 'blogname' ) );
				foreach ( get_posts( array( 'post_type' => array( 'post', 'page', 'attachment' ), 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) $site['posts'][ $p->ID ] = $p->post_title;
				foreach ( get_users( array( 'blog_id' => $blog ) ) as $u ) $site['users'][ $u->user_login ] = array_values( $u->roles );
				$base = wp_upload_dir()['basedir'];
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ) ) as $f ) if ( $f->isFile() && false === strpos( $f->getPathname(), '/sites/' . ( 1 === $blog ? '' : 'x' ) ) ) $site['files'][ substr( $f->getPathname(), strlen( $base ) + 1 ) ] = hash_file( 'sha256', $f->getPathname() );
				$inventory[ $blog ] = $site;
				restore_current_blog();
			}
			$inventory['network_plugins'] = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
			$started = microtime( true );
			$queued = Digitalisimo_Backups::service_create( array( 'network' => true, 'via' => 'cli' ) );
			if ( is_wp_error( $queued ) ) throw new RuntimeException( 'No se encoló el respaldo de la red: ' . $queued->get_error_message() );
			for ( $i = 0; $i < 400; $i++ ) {
				$job = Digitalisimo_Backups_Jobs::get( $queued['id'] );
				if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
				Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
			}
			$view = Digitalisimo_Backups::service_job( $queued['id'] );
			if ( is_wp_error( $view ) || 'success' !== $view['status'] ) throw new RuntimeException( 'El respaldo de la red no terminó: ' . wp_json_encode( is_wp_error( $view ) ? $view->get_error_message() : $view ) );
			file_put_contents( DIGI_DIR . '/inventory.json', wp_json_encode( $inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			digi_state( array( 'backup' => $view['file'] ) );
			digi_save( 'backup', $problems, array( 'file' => $view['file'], 'seconds' => round( microtime( true ) - $started, 1 ), 'size' => (int) @filesize( DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $view['file'] ) ) );
			break;

		default:
			throw new RuntimeException( 'Paso desconocido: ' . $digi_step );
	}
} catch ( Throwable $e ) {
	$problems[] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	digi_save( $digi_step, $problems );
}
