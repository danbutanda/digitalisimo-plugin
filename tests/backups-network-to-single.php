<?php
/**
 * Restaurar el respaldo de una red en un WordPress individual (rama backups/network-to-single, pedido del propietario del
 * 2026-11-16: «Subí un respaldo y no me deja restaurarlo en otro sitio: me sale la etiqueta “No se restaura aquí”»).
 *
 * Una red por subdirectorios (red.test, prefijo wp_) con el sitio principal y los sitios /dos/ y /tres/: contenido con
 * medios de cada sitio, usuarios con roles distintos en cada uno (miembros, no miembros, un superadministrador, uno
 * bloqueado), un plugin activado en la red y otro sólo en cada sitio, un tema por sitio (el del 2, hijo) y ajustes de red.
 * Se respalda la red con el motor real y el respaldo se lleva a un WordPress individual en otra dirección y con otro
 * prefijo (destino.test, dst_), donde se agrega a la lista como un subido, y se comprueba:
 *
 *   N1  la lista: el respaldo de la red se restaura aquí (sin «No se restaura aquí»), con sus sitios;
 *   N2  verify con `site`: el sitio que se restauraría, los de la red y «origen → este sitio» con su dirección;
 *   N3  restaurar el sitio 2: sus tablas con el prefijo de aquí, sin tablas de la red ni de otros sitios; sus miembros con
 *       su contraseña y su rol (no los demás), quien restaura sigue entrando; los plugins activados en la red y los suyos
 *       activos (active_plugins), su tema, sus medios en uploads/ y sus URL con la dirección de aquí; la configuración de
 *       Backups del destino intacta; la copia previa en el historial;
 *   N4  restaurar después el sitio principal (sin `site`): sus tablas, su tema y sus plugins, sus medios sin sites/;
 *   N5  por partes: sólo los medios del sitio 3 (ninguna tabla cambia) y sólo la base de datos del sitio 2 (ningún archivo);
 *   N6  un sitio que la red no tiene, `site` en un respaldo que no es de una red y `sites` en vez de `site`: rechazados sin
 *       tocar nada;
 *   N7  REST y WP-CLI con `site`;
 *   N8  una falla después del cambio vuelve sola a la copia previa (el sitio queda como estaba);
 *   N9  un respaldo de la red cifrado (DGBK) con su frase;
 *   N10 un punto incremental de la red: su cadena se reconstruye, el sitio se convierte y se restaura;
 *   N11 la migración con clave de una red a este WordPress: compatible y con su restauración marcada.
 *
 * Deben fallar con el código anterior: el respaldo de una red no era restaurable en un WordPress individual
 * (uploaded_restorable_scope() y package_installation_problems() lo rechazaban) y no existía la conversión.
 */
$root = sys_get_temp_dir() . '/digitalisimo-n2s-' . bin2hex( random_bytes( 4 ) );
mkdir( $root . '/public', 0700, true );
$root = realpath( $root );
define( 'ABSPATH', $root . '/public/' );
define( 'WP_CONTENT_DIR', $root . '/public/wp-content' );
define( 'WP_PLUGIN_DIR', $root . '/public/wp-content/plugins' );
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', $root . '/private' );
require __DIR__ . '/backups-site-extract/env.php';
/** La base de datos de la prueba, con una falla que se puede encender: fijar las direcciones tras el cambio (N8). */
class N2s_DB extends Digi_Extract_DB {
	public function query( $sql ) {
		if ( ! empty( $GLOBALS['n2s_fail'] ) && preg_match( '/^UPDATE `[^`]+options` SET option_value/', trim( (string) $sql ) ) ) {
			$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
			if ( $job && 'restore' === $job['kind'] && empty( $job['spec']['rollback'] ) && 'finish' === $job['phase'] ) { $this->last_error = 'disco lleno simulado'; return false; }
		}
		return parent::query( $sql );
	}
}
$GLOBALS['wpdb'] = new N2s_DB();
$S = 'Digitalisimo_Backups';
$db = $GLOBALS['wpdb'];
$public = $root . '/public';
$store = $root . '/private';
$GLOBALS['digi']['filters']['digitalisimo_backups_job_backoff'][] = function() { return 0; };

$failures = 0;
function n2s_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
function n2s_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function n2s_tree( $dir ) {
	$out = array();
	if ( ! is_dir( $dir ) ) return $out;
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) if ( $file->isFile() ) $out[ substr( wp_normalize_path( $file->getPathname() ), strlen( wp_normalize_path( $dir ) ) + 1 ) ] = hash_file( 'sha256', $file->getPathname() );
	ksort( $out );
	return $out;
}
function n2s_tables_print( $db ) {
	$out = array();
	// Sin lo que Backups escribe de sí mismo (historial, avisos, la última reubicación) ni lo que WordPress regenera.
	foreach ( $db->tables as $name => $table ) $out[ $name ] = hash( 'sha256', serialize( array_values( array_filter( $table['rows'], function( $row ) { $option = (string) ( $row['option_name'] ?? '' ); return 0 !== strpos( $option, 'digitalisimo_backups' ) && 0 !== strpos( $option, '_transient' ) && 0 !== strpos( $option, '_site_transient' ) && 'rewrite_rules' !== $option && 'cron' !== $option; } ) ) ) );
	ksort( $out );
	return $out;
}
function n2s_meta( $db, $table, $user ) { $keys = array(); foreach ( $db->rows( $table ) as $row ) if ( (int) $row['user_id'] === (int) $user ) $keys[ $row['meta_key'] ] = $row['meta_value']; ksort( $keys ); return $keys; }

/* ------------------------------------------------------------------ La red */

$cap = function( array $roles ) { return serialize( array_fill_keys( $roles, true ) ); };
$roles = serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ), 'editor' => array( 'name' => 'Editor', 'capabilities' => array( 'edit_others_posts' => true ) ), 'author' => array( 'name' => 'Author', 'capabilities' => array( 'publish_posts' => true ) ), 'customer' => array( 'name' => 'Cliente', 'capabilities' => array( 'read' => true ) ) ) );
$options = array( 'option_id', 'option_name', 'option_value', 'autoload' );
$posts = array( 'ID', 'post_author', 'post_title', 'post_content', 'post_type', 'guid' );
$users = array( 'ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name', 'spam', 'deleted' );
$user = function( $id, $login, $email ) { return array( 'ID' => $id, 'user_login' => $login, 'user_pass' => '$wp$2y$10$hash-de-' . $login, 'user_nicename' => $login, 'user_email' => $email, 'user_url' => '', 'user_registered' => '2026-01-01 00:00:00', 'user_activation_key' => '', 'user_status' => 0, 'display_name' => ucfirst( $login ), 'spam' => 0, 'deleted' => 0 ); };

function n2s_network( $public, $db, $cap, $roles, $options, $posts, $users, $user ) {
	foreach ( scandir( $public ) as $name ) if ( '.' !== $name && '..' !== $name ) digi_rmtree( $public . '/' . $name );
	$files = array(
		'wp-config.php' => "<?php define( 'MULTISITE', true ); define( 'DB_PASSWORD', 'clave-de-la-red' );\n",
		'index.php' => "<?php // index\n", 'wp-load.php' => "<?php // load\n", 'wp-settings.php' => "<?php // settings\n", 'wp-cron.php' => "<?php // cron\n",
		'wp-admin/index.php' => "<?php // admin\n", 'wp-includes/version.php' => "<?php \$wp_version = '6.8';\n",
		'wp-content/index.php' => "<?php // Silence\n",
		'wp-content/object-cache.php' => "<?php // caché de objetos de la red\n",
		'wp-content/mu-plugins/mu.php' => "<?php // obligatorio\n",
		'wp-content/plugins/index.php' => "<?php // Silence\n",
		'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php /* Plugin Name: DIGITALÍSIMO Backups */\n",
		'wp-content/plugins/red-plugin/red-plugin.php' => "<?php /* Plugin Name: De toda la red */\n",
		'wp-content/plugins/plugin-uno/plugin-uno.php' => "<?php /* Plugin Name: Del principal */\n",
		'wp-content/plugins/plugin-dos/plugin-dos.php' => "<?php /* Plugin Name: Del sitio dos */\n",
		'wp-content/plugins/plugin-tres/plugin-tres.php' => "<?php /* Plugin Name: Del sitio tres */\n",
		'wp-content/plugins/hello.php' => "<?php /* Plugin Name: Hello (inactivo) */\n",
		'wp-content/themes/index.php' => "<?php // Silence\n",
		'wp-content/themes/tema-uno/style.css' => "/* Theme Name: Tema uno */\n",
		'wp-content/themes/tema/style.css' => "/* Theme Name: Tema */\n",
		'wp-content/themes/tema-hijo/style.css' => "/* Theme Name: Tema hijo\nTemplate: tema */\n",
		'wp-content/themes/otro-tema/style.css' => "/* Theme Name: Otro */\n",
		'wp-content/uploads/2026/01/principal.jpg' => 'medio del sitio principal',
		'wp-content/uploads/sites/2/2026/11/foto.jpg' => str_repeat( "\xff\xd8\xff\xe0JFIF", 512 ),
		'wp-content/uploads/sites/3/2026/11/tres.jpg' => 'medio del sitio tres',
	);
	foreach ( $files as $name => $content ) digi_write( $public . '/' . $name, $content );
	$db->tables = array();
	$db->base_prefix = $db->prefix = 'wp_';
	$db->create( 'wp_blogs', array( 'blog_id', 'site_id', 'domain', 'path' ), array( array( 'blog_id' => 1, 'site_id' => 1, 'domain' => 'red.test', 'path' => '/' ), array( 'blog_id' => 2, 'site_id' => 1, 'domain' => 'red.test', 'path' => '/dos/' ), array( 'blog_id' => 3, 'site_id' => 1, 'domain' => 'red.test', 'path' => '/tres/' ) ) );
	$db->create( 'wp_site', array( 'id', 'domain', 'path' ), array( array( 'id' => 1, 'domain' => 'red.test', 'path' => '/' ) ) );
	$db->create( 'wp_sitemeta', array( 'meta_id', 'site_id', 'meta_key', 'meta_value' ), array(
		array( 'site_id' => 1, 'meta_key' => 'site_admins', 'meta_value' => serialize( array( 'red' ) ) ),
		array( 'site_id' => 1, 'meta_key' => 'active_sitewide_plugins', 'meta_value' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php' => 1, 'red-plugin/red-plugin.php' => 1 ) ) ),
		array( 'site_id' => 1, 'meta_key' => 'subdomain_install', 'meta_value' => '0' ),
		array( 'site_id' => 1, 'meta_key' => 'siteurl', 'meta_value' => 'https://red.test/' ),
		array( 'site_id' => 1, 'meta_key' => 'main_site', 'meta_value' => '1' ),
		array( 'site_id' => 1, 'meta_key' => 'blog_count', 'meta_value' => '3' ),
	) );
	$db->create( 'wp_users', $users, array( $user( 1, 'red', 'red@red.test' ), $user( 2, 'editora', 'editora@red.test' ), $user( 3, 'solo2', 'solo2@cliente.test' ), $user( 4, 'solo3', 'solo3@otro.test' ), $user( 5, 'cliente', 'cliente@cliente.test' ), array( 'spam' => 1 ) + $user( 6, 'spammer', 'spammer@red.test' ), $user( 7, 'jefa', 'jefa@red.test' ) ) );
	$meta = array();
	$add = function( $id, $key, $value ) use ( &$meta ) { $meta[] = array( 'user_id' => $id, 'meta_key' => $key, 'meta_value' => $value ); };
	$add( 1, 'wp_capabilities', $cap( array( 'administrator' ) ) ); $add( 1, 'wp_user_level', '10' ); $add( 1, 'first_name', 'Red' ); $add( 1, 'primary_blog', '1' );
	$add( 2, 'wp_capabilities', $cap( array( 'subscriber' ) ) ); $add( 2, 'wp_2_capabilities', $cap( array( 'editor' ) ) ); $add( 2, 'wp_2_user_level', '7' ); $add( 2, 'wp_user-settings', 'del principal' ); $add( 2, 'wp_2_user-settings', 'del dos' ); $add( 2, 'first_name', 'Editora' );
	$add( 3, 'wp_2_capabilities', $cap( array( 'author' ) ) ); $add( 3, 'wp_2_user_level', '2' );
	$add( 4, 'wp_3_capabilities', $cap( array( 'administrator' ) ) ); $add( 4, 'wp_3_user_level', '10' );
	$add( 5, 'wp_2_capabilities', $cap( array( 'customer' ) ) ); $add( 5, 'wp_2_user_level', '0' );
	$add( 6, 'wp_2_capabilities', $cap( array( 'subscriber' ) ) );
	$add( 7, 'wp_capabilities', $cap( array( 'editor' ) ) ); $add( 7, 'wp_user_level', '7' );
	$db->create( 'wp_usermeta', array( 'umeta_id', 'user_id', 'meta_key', 'meta_value' ), $meta );
	$db->create( 'wp_options', $options, array(
		array( 'option_name' => 'siteurl', 'option_value' => 'https://red.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'home', 'option_value' => 'https://red.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'blogname', 'option_value' => 'La red', 'autoload' => 'yes' ),
		array( 'option_name' => 'admin_email', 'option_value' => 'red@red.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'wp_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
		array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'plugin-uno/plugin-uno.php' ) ), 'autoload' => 'yes' ),
		array( 'option_name' => 'stylesheet', 'option_value' => 'tema-uno', 'autoload' => 'yes' ),
		array( 'option_name' => 'template', 'option_value' => 'tema-uno', 'autoload' => 'yes' ),
		array( 'option_name' => 'digitalisimo_backups_policies', 'option_value' => serialize( array( array( 'id' => 'de-la-red', 'type' => 'full', 'enabled' => true ) ) ), 'autoload' => 'no' ),
	) );
	$db->create( 'wp_posts', $posts, array( array( 'ID' => 1, 'post_author' => 1, 'post_title' => 'Portada de la red', 'post_content' => '<img src="https://red.test/wp-content/uploads/2026/01/principal.jpg">', 'post_type' => 'page', 'guid' => 'https://red.test/?p=1' ) ) );
	$db->create( 'wp_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ) );
	$db->create( 'wp_comments', array( 'comment_ID', 'comment_post_ID', 'user_id', 'comment_content' ) );
	$db->create( 'wp_global_plugin_log', array( 'id', 'entry' ), array( array( 'entry' => 'de toda la red' ) ) );
	$elements = wp_json_encode( array( array( 'elType' => 'widget', 'settings' => array( 'image' => array( 'url' => 'https://red.test/dos/wp-content/uploads/sites/2/2026/11/foto.jpg' ) ) ) ) );
	$db->create( 'wp_2_options', $options, array(
		array( 'option_name' => 'siteurl', 'option_value' => 'https://red.test/dos', 'autoload' => 'yes' ),
		array( 'option_name' => 'home', 'option_value' => 'https://red.test/dos', 'autoload' => 'yes' ),
		array( 'option_name' => 'blogname', 'option_value' => 'Sitio dos', 'autoload' => 'yes' ),
		array( 'option_name' => 'admin_email', 'option_value' => 'red@red.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'wp_2_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
		array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'plugin-dos/plugin-dos.php' ) ), 'autoload' => 'yes' ),
		array( 'option_name' => 'stylesheet', 'option_value' => 'tema-hijo', 'autoload' => 'yes' ),
		array( 'option_name' => 'template', 'option_value' => 'tema', 'autoload' => 'yes' ),
		array( 'option_name' => 'upload_path', 'option_value' => '', 'autoload' => 'yes' ),
	) );
	$db->create( 'wp_2_posts', $posts, array(
		array( 'ID' => 5, 'post_author' => 2, 'post_title' => 'Inicio dos', 'post_content' => '<img src="https://red.test/dos/wp-content/uploads/sites/2/2026/11/foto.jpg"> <a href="https://red.test/dos/contacto/">Contacto</a> <img src="https://red.test/wp-content/uploads/sites/2/2026/11/foto.jpg">', 'post_type' => 'page', 'guid' => 'https://red.test/dos/?page_id=5' ),
		array( 'ID' => 6, 'post_author' => 3, 'post_title' => 'Entrada de solo2', 'post_content' => 'texto', 'post_type' => 'post', 'guid' => 'https://red.test/dos/?p=6' ),
		array( 'ID' => 7, 'post_author' => 2, 'post_title' => 'foto', 'post_content' => '', 'post_type' => 'attachment', 'guid' => 'https://red.test/dos/wp-content/uploads/sites/2/2026/11/foto.jpg' ),
	) );
	$db->create( 'wp_2_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ), array(
		array( 'post_id' => 5, 'meta_key' => '_elementor_data', 'meta_value' => $elements ),
		array( 'post_id' => 7, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/11/foto.jpg' ),
		array( 'post_id' => 7, 'meta_key' => '_wp_attachment_metadata', 'meta_value' => serialize( array( 'file' => '2026/11/foto.jpg', 'path' => WP_CONTENT_DIR . '/uploads/sites/2/2026/11/foto.jpg' ) ) ),
	) );
	$db->create( 'wp_2_comments', array( 'comment_ID', 'comment_post_ID', 'user_id', 'comment_content' ), array( array( 'comment_post_ID' => 6, 'user_id' => 5, 'comment_content' => 'Comentario de cliente' ) ) );
	$db->create( 'wp_3_options', $options, array(
		array( 'option_name' => 'siteurl', 'option_value' => 'https://red.test/tres', 'autoload' => 'yes' ),
		array( 'option_name' => 'home', 'option_value' => 'https://red.test/tres', 'autoload' => 'yes' ),
		array( 'option_name' => 'admin_email', 'option_value' => 'solo3@otro.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'wp_3_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
		array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'plugin-tres/plugin-tres.php' ) ), 'autoload' => 'yes' ),
		array( 'option_name' => 'stylesheet', 'option_value' => 'otro-tema', 'autoload' => 'yes' ),
		array( 'option_name' => 'template', 'option_value' => 'otro-tema', 'autoload' => 'yes' ),
	) );
	$db->create( 'wp_3_posts', $posts, array( array( 'ID' => 1, 'post_author' => 4, 'post_title' => 'Tres', 'post_content' => 'secreto del sitio tres <img src="https://red.test/tres/wp-content/uploads/sites/3/2026/11/tres.jpg">', 'post_type' => 'post', 'guid' => 'https://red.test/tres/?p=1' ) ) );
	$db->create( 'wp_3_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ) );
	$GLOBALS['digi']['sites'] = array( 1 => array( 'domain' => 'red.test', 'path' => '/' ), 2 => array( 'domain' => 'red.test', 'path' => '/dos/' ), 3 => array( 'domain' => 'red.test', 'path' => '/tres/' ) );
	$GLOBALS['digi']['multisite'] = true;
	$GLOBALS['digi']['blog'] = 1;
	$GLOBALS['digi']['caps'] = array( 'manage_network_options' => true );
}
/** Respaldo completo de la red con el motor real; devuelve su archivo (en el almacén). */
function n2s_backup( array $args = array() ) {
	$queued = Digitalisimo_Backups::service_create( array( 'network' => true ) + $args );
	n2s_check( ! is_wp_error( $queued ), 'No se encoló el respaldo de la red: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
	$done = digi_drive( $queued['id'] );
	n2s_check( 'success' === $done['status'], 'El respaldo de la red no terminó: ' . json_encode( $done ) );
	return (string) $done['file'];
}
/** El WordPress individual de destino (otra dirección, prefijo dst_, su usuario «destino» y su configuración de Backups). */
function n2s_destination( $public, $db, $cap, $roles, $options, $posts, $users, $user ) {
	$GLOBALS['digi']['multisite'] = false;
	$GLOBALS['digi']['caps'] = array( 'manage_options' => true );
	$GLOBALS['digi']['sites'] = array();
	foreach ( scandir( $public ) as $name ) if ( '.' !== $name && '..' !== $name ) digi_rmtree( $public . '/' . $name );
	foreach ( array( 'wp-config.php' => "<?php // destino\n", 'index.php' => "<?php // index\n", 'wp-load.php' => '<?php', 'wp-settings.php' => '<?php', 'wp-admin/index.php' => '<?php', 'wp-includes/version.php' => '<?php', 'wp-content/index.php' => '<?php', 'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php /* Plugin Name: DIGITALÍSIMO Backups */\n", 'wp-content/plugins/del-destino/del-destino.php' => "<?php /* Plugin Name: Del destino */\n", 'wp-content/themes/twentytwentysix/style.css' => '/* destino */', 'wp-content/uploads/2025/05/del-destino.jpg' => 'medio del destino' ) as $name => $content ) digi_write( $public . '/' . $name, $content );
	$db->tables = array();
	$db->base_prefix = $db->prefix = 'dst_';
	$db->create( 'dst_options', $options, array(
		array( 'option_name' => 'siteurl', 'option_value' => 'https://destino.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'home', 'option_value' => 'https://destino.test', 'autoload' => 'yes' ),
		array( 'option_name' => 'dst_user_roles', 'option_value' => $roles, 'autoload' => 'yes' ),
		array( 'option_name' => 'active_plugins', 'option_value' => serialize( array( 'digitalisimo-backups/digitalisimo-backups.php', 'del-destino/del-destino.php' ) ), 'autoload' => 'yes' ),
		array( 'option_name' => 'stylesheet', 'option_value' => 'twentytwentysix', 'autoload' => 'yes' ),
		array( 'option_name' => 'template', 'option_value' => 'twentytwentysix', 'autoload' => 'yes' ),
		array( 'option_name' => Digitalisimo_Backups::OPTION, 'option_value' => serialize( array( 'retention' => '9' ) ), 'autoload' => 'no' ),
		array( 'option_name' => 'digitalisimo_backups_policies', 'option_value' => serialize( array( array( 'id' => 'del-destino', 'type' => 'full', 'enabled' => true ) ) ), 'autoload' => 'no' ),
	) );
	$db->create( 'dst_users', $users, array( $user( 1, 'destino', 'admin@destino.test' ) ) );
	$db->create( 'dst_usermeta', array( 'umeta_id', 'user_id', 'meta_key', 'meta_value' ), array( array( 'user_id' => 1, 'meta_key' => 'dst_capabilities', 'meta_value' => $cap( array( 'administrator' ) ) ) ) );
	$db->create( 'dst_posts', $posts, array( array( 'ID' => 1, 'post_author' => 1, 'post_title' => 'Hola mundo', 'post_content' => '', 'post_type' => 'post', 'guid' => 'https://destino.test/?p=1' ) ) );
	$db->create( 'dst_postmeta', array( 'meta_id', 'post_id', 'meta_key', 'meta_value' ) );
}
/** Lleva un archivo de la red al almacén del destino y lo agrega a la lista (como un subido sin firma de aquí). */
function n2s_add( $saved, $name ) {
	copy( $saved, DIGITALISIMO_BACKUPS_STORAGE_DIR . '/' . $name );
	$added = Digitalisimo_Backups::service_register( $name, array( 'confirm' => 'AGREGAR SIN FIRMA', 'via' => 'rest' ) );
	n2s_check( ! is_wp_error( $added ), 'No se agregó a la lista: ' . ( is_wp_error( $added ) ? $added->get_error_message() : '' ) );
	return $added;
}
function n2s_row( $file ) {
	foreach ( Digitalisimo_Backups::service_backups() as $row ) if ( $row['file'] === $file ) return $row;
	return null;
}

n2s_network( $public, $db, $cap, $roles, $options, $posts, $users, $user );
$network_file = n2s_backup();
$saved = $root . '/red.zip';
copy( $store . '/' . $network_file, $saved );
$network_tree = n2s_tree( $public );

try {
	n2s_destination( $public, $db, $cap, $roles, $options, $posts, $users, $user );
	foreach ( scandir( $store ) as $name ) if ( preg_match( '/\.(zip|dgbk)$/', $name ) ) @unlink( $store . '/' . $name );
	$file = 'digitalisimo-upload-20261116-101500-0a1b2c3d.zip';

	n2s_case( 'N1 la lista: el respaldo de la red se restaura aquí, con sus sitios', function() use ( $saved, $file ) {
		$added = n2s_add( $saved, $file );
		n2s_check( true === $added['restorable'], 'El respaldo de la red no se restaura en un WordPress individual: ' . json_encode( array( $added['restorable'], $added['problems'] ?? null ) ) );
		$row = n2s_row( $file );
		n2s_check( $row && true === $row['restorable'] && true === $row['uploaded']['restorable_here'] && 'network' === $row['scope'], 'La fila no es restaurable aquí: ' . json_encode( $row ) );
		$sites = (array) $row['network_sites'];
		n2s_check( array( 1 => 'red.test', 2 => 'red.test/dos', 3 => 'red.test/tres' ) === $sites, 'La fila no lista los sitios de la red: ' . json_encode( $sites ) );
		$item = Digitalisimo_Backups::history_item( $file );
		$text = new ReflectionMethod( 'Digitalisimo_Backups', 'uploaded_blocked_text' );
		if ( PHP_VERSION_ID < 80100 ) $text->setAccessible( true );
		n2s_check( '' === $text->invoke( null, $item ), 'Sigue «No se restaura aquí»: ' . $text->invoke( null, $item ) );
		// La pantalla: sin la etiqueta, con «Restaurar» y la frase que dice qué pasa y el sitio elegido.
		$_GET['restore'] = $file;
		ob_start();
		$history = new ReflectionMethod( 'Digitalisimo_Backups', 'history_row' );
		if ( PHP_VERSION_ID < 80100 ) $history->setAccessible( true );
		$history->invoke( null, $item, 0, false, $file );
		$html = ob_get_clean();
		unset( $_GET['restore'] );
		n2s_check( false === strpos( $html, 'No se restaura aquí' ) && false !== strpos( $html, 'data-restore-toggle' ) && false !== strpos( $html, 'se restaura <strong data-network-site-label>el sitio red.test</strong> de la red como este WordPress' ) && false !== strpos( $html, 'name="restore_site"' ) && false !== strpos( $html, '<option value="2">red.test/dos (ID 2)</option>' ) && false !== strpos( $html, 'value="1" selected>red.test (ID 1, principal)' ), 'La pantalla no ofrece restaurar el sitio de la red: ' . substr( strip_tags( $html ), 0, 400 ) );
	} );

	n2s_case( 'N2 verify con site: el sitio, los de la red y «origen → este sitio»', function() use ( $file ) {
		$check = Digitalisimo_Backups::service_verify( $file, array( 'site' => 2 ) );
		n2s_check( ! is_wp_error( $check ), 'verify falló: ' . ( is_wp_error( $check ) ? $check->get_error_message() : '' ) );
		n2s_check( true === $check['compatible'] && 'RESTAURAR' === $check['confirmation'] && 2 === $check['convert']['site'] && 'red.test/dos' === $check['convert']['label'] && array( 1, 2, 3 ) === array_keys( $check['convert']['sites'] ), 'verify no describe el sitio que se restaura: ' . json_encode( $check ) );
		n2s_check( false !== strpos( (string) $check['relocation']['text'], 'https://red.test/dos' ) && false !== strpos( (string) $check['relocation']['text'], 'wp_2_ → dst_' ), 'verify no dice qué se reescribirá: ' . json_encode( $check['relocation'] ) );
		n2s_check( in_array( 'red-plugin/red-plugin.php', $check['convert']['plugins'], true ) && in_array( 'plugin-dos/plugin-dos.php', $check['convert']['plugins'], true ) && ! in_array( 'plugin-tres/plugin-tres.php', $check['convert']['plugins'], true ) && 'tema-hijo' === $check['convert']['theme'], 'verify no dice qué plugins y tema viajan: ' . json_encode( $check['convert'] ) );
		$main = Digitalisimo_Backups::service_verify( $file );
		n2s_check( ! is_wp_error( $main ) && 1 === $main['convert']['site'] && true === $main['compatible'], 'Sin site, verify no elige el principal: ' . json_encode( is_wp_error( $main ) ? $main->get_error_message() : $main['convert'] ) );
	} );

	$destination_tree = n2s_tree( $public );
	n2s_case( 'N3 restaurar el sitio 2 de la red como este WordPress', function() use ( $file, $db, $public, $store, $destination_tree ) {
		$restored = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'site' => 2 ) );
		n2s_check( ! is_wp_error( $restored ) && true === $restored['restored'], 'La restauración del sitio 2 falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
		n2s_check( false !== strpos( $restored['notice'], 'Se restauró el sitio red.test/dos (ID 2) del respaldo de la red como este WordPress individual' ), 'El aviso no dice qué sitio se restauró: ' . $restored['notice'] );
		// Tablas: las del sitio con el prefijo de aquí; ninguna de la red ni de otros sitios.
		$tables = array_keys( $db->tables );
		sort( $tables );
		n2s_check( array( 'dst_comments', 'dst_options', 'dst_postmeta', 'dst_posts', 'dst_usermeta', 'dst_users' ) === $tables, 'Las tablas no son las del sitio 2 con el prefijo de aquí: ' . json_encode( $tables ) );
		// Usuarios: los miembros del sitio 2 con su contraseña y su rol, el autor bloqueado sin contraseña, quien restaura.
		$logins = array();
		foreach ( $db->rows( 'dst_users' ) as $u ) $logins[ $u['user_login'] ] = $u['user_pass'];
		ksort( $logins );
		n2s_check( array( 'cliente' => '$wp$2y$10$hash-de-cliente', 'destino' => '$wp$2y$10$hash-de-destino', 'editora' => '$wp$2y$10$hash-de-editora', 'solo2' => '$wp$2y$10$hash-de-solo2' ) === $logins, 'Los usuarios no son los miembros del sitio 2 (y quien restaura): ' . json_encode( $logins ) );
		n2s_check( array( 'editor' => true ) === maybe_unserialize( n2s_meta( $db, 'dst_usermeta', 2 )['dst_capabilities'] ?? '' ) && '7' === ( n2s_meta( $db, 'dst_usermeta', 2 )['dst_user_level'] ?? '' ) && array( 'author' => true ) === maybe_unserialize( n2s_meta( $db, 'dst_usermeta', 3 )['dst_capabilities'] ?? '' ) && array( 'customer' => true ) === maybe_unserialize( n2s_meta( $db, 'dst_usermeta', 5 )['dst_capabilities'] ?? '' ), 'Los roles del sitio 2 no quedaron con el prefijo de aquí.' );
		n2s_check( 'del dos' === ( n2s_meta( $db, 'dst_usermeta', 2 )['dst_user-settings'] ?? '' ), 'La meta propia del sitio no ganó a la del principal: ' . json_encode( n2s_meta( $db, 'dst_usermeta', 2 ) ) );
		foreach ( $db->rows( 'dst_usermeta' ) as $m ) n2s_check( 0 !== strpos( $m['meta_key'], 'wp_' ), 'Quedó una meta con el prefijo de la red: ' . $m['meta_key'] );
		n2s_check( ! empty( $restored['access']['kept'] ) && 'destino' === $restored['access']['user'], 'Quien restauró no sigue entrando: ' . json_encode( $restored['access'] ?? null ) );
		// Plugins: los activados en la red y los del sitio (regla dura: active_sitewide_plugins → active_plugins), su tema.
		n2s_check( array( 'digitalisimo-backups/digitalisimo-backups.php', 'plugin-dos/plugin-dos.php', 'red-plugin/red-plugin.php' ) === get_option( 'active_plugins' ) && 'tema-hijo' === get_option( 'stylesheet' ) && 'tema' === get_option( 'template' ), 'Los plugins de la red y del sitio, o su tema, no quedaron activos: ' . json_encode( array( get_option( 'active_plugins' ), get_option( 'stylesheet' ) ) ) );
		n2s_check( is_array( get_option( 'dst_user_roles' ) ) && isset( get_option( 'dst_user_roles' )['customer'] ), 'Los roles del sitio no quedaron en dst_user_roles.' );
		// Direcciones: las de aquí, también las de los medios (de uploads/sites/2 a uploads/), en serializados y en JSON.
		n2s_check( 'https://destino.test' === get_option( 'home' ) && 'https://destino.test' === get_option( 'siteurl' ), 'El sitio no quedó con la URL de aquí.' );
		$page = null;
		foreach ( $db->rows( 'dst_posts' ) as $p ) if ( 5 === (int) $p['ID'] ) $page = $p;
		n2s_check( $page && 2 === substr_count( $page['post_content'], 'https://destino.test/wp-content/uploads/2026/11/foto.jpg' ) && false !== strpos( $page['post_content'], 'https://destino.test/contacto/' ), 'El contenido no quedó con las URL de aquí: ' . ( $page['post_content'] ?? '' ) );
		foreach ( $db->rows( 'dst_postmeta' ) as $pm ) {
			if ( '_elementor_data' === $pm['meta_key'] ) n2s_check( false !== strpos( $pm['meta_value'], 'https:\/\/destino.test\/wp-content\/uploads\/2026\/11\/foto.jpg' ) || false !== strpos( $pm['meta_value'], 'https://destino.test/wp-content/uploads/2026/11/foto.jpg' ), 'El JSON de Elements no quedó con los medios de aquí: ' . $pm['meta_value'] );
			if ( '_wp_attachment_metadata' === $pm['meta_key'] ) n2s_check( WP_CONTENT_DIR . '/uploads/2026/11/foto.jpg' === maybe_unserialize( $pm['meta_value'] )['path'], 'La ruta de un medio no pasó a uploads/: ' . $pm['meta_value'] );
		}
		foreach ( $db->tables as $name => $table ) foreach ( $table['rows'] as $r ) foreach ( $r as $column => $value ) {
			if ( 'guid' === $column || null === $value || Digitalisimo_Backups::LAST_RELOCATION === ( $r['option_name'] ?? '' ) ) continue;
			$value = str_replace( '@red.test', '', (string) $value );
			n2s_check( false === strpos( $value, 'red.test/dos' ) && false === strpos( $value, 'uploads/sites/' ) && false === strpos( $value, 'secreto del sitio tres' ), 'Quedó algo de la red en ' . $name . '.' . $column . ': ' . substr( $value, 0, 160 ) );
		}
		// Archivos: los del sitio y los del destino que el respaldo no trae fuera de lo que gestiona (wp-config.php).
		n2s_check( is_file( $public . '/wp-content/uploads/2026/11/foto.jpg' ) && ! is_dir( $public . '/wp-content/uploads/sites' ) && ! is_file( $public . '/wp-content/uploads/2026/01/principal.jpg' ), 'Los medios no son los del sitio 2 en uploads/.' );
		n2s_check( is_file( $public . '/wp-content/themes/tema-hijo/style.css' ) && is_file( $public . '/wp-content/themes/tema/style.css' ) && ! is_dir( $public . '/wp-content/themes/otro-tema' ) && ! is_dir( $public . '/wp-content/themes/tema-uno' ), 'Los temas no son los del sitio 2.' );
		n2s_check( is_file( $public . '/wp-content/plugins/red-plugin/red-plugin.php' ) && is_file( $public . '/wp-content/plugins/plugin-dos/plugin-dos.php' ) && ! is_dir( $public . '/wp-content/plugins/plugin-tres' ) && ! is_dir( $public . '/wp-content/plugins/plugin-uno' ), 'Los plugins no son los del sitio 2 y de la red.' );
		n2s_check( "<?php // destino\n" === file_get_contents( $public . '/wp-config.php' ) && ! is_file( $public . '/wp-content/object-cache.php' ), 'La restauración tocó el wp-config.php de aquí o trajo un drop-in de la red.' );
		// La configuración de Backups es la de aquí; la copia previa está en el historial.
		n2s_check( '9' === get_option( Digitalisimo_Backups::OPTION )['retention'] && 'del-destino' === get_option( 'digitalisimo_backups_policies' )[0]['id'], 'Se perdió la configuración de Backups de aquí.' );
		$safety = null;
		foreach ( (array) get_option( Digitalisimo_Backups::HISTORY, array() ) as $row ) if ( 'pre-restore' === ( $row['policy'] ?? '' ) ) $safety = $row;
		n2s_check( $safety && is_file( $store . '/' . $safety['file'] ) && ! empty( $safety['locked'] ), 'La copia previa no quedó bloqueada en el historial.' );
		n2s_check( null !== Digitalisimo_Backups::history_item( $file ), 'El respaldo de la red salió de la lista.' );
		$GLOBALS['n2s_safety'] = $safety['file'];
	} );

	n2s_case( 'N4 restaurar después el sitio principal (sin site)', function() use ( $file, $db, $public ) {
		$restored = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR' ) );
		n2s_check( ! is_wp_error( $restored ) && true === $restored['restored'], 'La restauración del principal falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
		$tables = array_keys( $db->tables );
		sort( $tables );
		n2s_check( array( 'dst_comments', 'dst_global_plugin_log', 'dst_options', 'dst_postmeta', 'dst_posts', 'dst_usermeta', 'dst_users' ) === $tables, 'Las tablas no son las del principal: ' . json_encode( $tables ) );
		n2s_check( array( 'digitalisimo-backups/digitalisimo-backups.php', 'plugin-uno/plugin-uno.php', 'red-plugin/red-plugin.php' ) === get_option( 'active_plugins' ) && 'tema-uno' === get_option( 'stylesheet' ), 'Los plugins o el tema no son los del principal: ' . json_encode( get_option( 'active_plugins' ) ) );
		$logins = array();
		foreach ( $db->rows( 'dst_users' ) as $u ) $logins[] = $u['user_login'];
		sort( $logins );
		n2s_check( array( 'destino', 'editora', 'jefa', 'red' ) === $logins, 'Los usuarios no son los del principal: ' . json_encode( $logins ) );
		n2s_check( is_file( $public . '/wp-content/uploads/2026/01/principal.jpg' ) && ! is_dir( $public . '/wp-content/uploads/sites' ) && ! is_file( $public . '/wp-content/uploads/2026/11/foto.jpg' ), 'Los medios del principal no quedaron sin sites/.' );
		$page = $db->rows( 'dst_posts' )[0] ?? array();
		n2s_check( false !== strpos( (string) ( $page['post_content'] ?? '' ), 'https://destino.test/wp-content/uploads/2026/01/principal.jpg' ), 'El contenido del principal no quedó con la URL de aquí.' );
	} );

	n2s_case( 'N5 por partes: sólo los medios del sitio 3 y sólo la base de datos del sitio 2', function() use ( $file, $db, $public ) {
		$before_tables = n2s_tables_print( $db );
		$before_files = n2s_tree( $public );
		$restored = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'site' => 3, 'parts' => 'uploads' ) );
		n2s_check( ! is_wp_error( $restored ) && true === $restored['restored'], 'Sólo los medios del sitio 3 falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
		$after = n2s_tree( $public );
		n2s_check( isset( $after['wp-content/uploads/2026/11/tres.jpg'] ) && ! isset( $after['wp-content/uploads/sites/3/2026/11/tres.jpg'] ), 'Los medios del sitio 3 no llegaron a uploads/.' );
		foreach ( $before_files as $path => $hash ) if ( 0 !== strpos( $path, 'wp-content/uploads/' ) ) n2s_check( ( $after[ $path ] ?? null ) === $hash, 'Restaurar sólo medios cambió ' . $path );
		$now = n2s_tables_print( $db );
		foreach ( $before_tables as $table => $hash ) if ( 'dst_options' !== $table ) n2s_check( ( $now[ $table ] ?? null ) === $hash, 'Restaurar sólo medios cambió la tabla ' . $table );
		n2s_check( 'tema-uno' === get_option( 'stylesheet' ), 'Restaurar sólo medios cambió las opciones del sitio.' );
		$before_files = n2s_tree( $public );
		$restored = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'site' => 2, 'parts' => array( 'database' ) ) );
		n2s_check( ! is_wp_error( $restored ) && true === $restored['restored'], 'Sólo la base de datos del sitio 2 falló: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
		n2s_check( n2s_tree( $public ) === $before_files, 'Restaurar sólo la base de datos cambió archivos.' );
		n2s_check( 'tema-hijo' === get_option( 'stylesheet' ) && isset( $db->tables['dst_comments'] ) && ! isset( $db->tables['dst_global_plugin_log'] ), 'La base de datos no es la del sitio 2.' );
	} );

	n2s_case( 'N6 rechazos sin tocar nada: sitio que no existe, site en otro respaldo, sites en vez de site', function() use ( $file, $db, $public, $store ) {
		$before = array( n2s_tables_print( $db ), n2s_tree( $public ) );
		$error = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'site' => 9 ) );
		n2s_check( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'no tiene el sitio 9' ) && false !== strpos( $error->get_error_message(), '2 (red.test/dos)' ) && false !== strpos( $error->get_error_message(), 'No se cambió nada del sitio' ), 'Un sitio que la red no tiene no se rechazó con la lista: ' . ( is_wp_error( $error ) ? $error->get_error_message() : json_encode( $error ) ) );
		$error = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'site' => 'dos' ) );
		n2s_check( is_wp_error( $error ) && 400 === $error->get_error_data()['status'] && 'digitalisimo_backups_site' === $error->get_error_code(), 'Un sitio que no es un número no se rechazó.' );
		$error = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'sites' => '2' ) );
		n2s_check( is_wp_error( $error ) && 400 === $error->get_error_data()['status'], 'Los medios de un sitio de la red (sites) se aceptaron en un WordPress individual.' );
		$safety = (string) $GLOBALS['n2s_safety'];
		$error = Digitalisimo_Backups::service_restore( $safety, array( 'confirm' => 'RESTAURAR', 'site' => 2 ) );
		n2s_check( is_wp_error( $error ) && 'digitalisimo_backups_site' === $error->get_error_code(), 'site se aceptó en un respaldo que no es de una red.' );
		n2s_check( array( n2s_tables_print( $db ), n2s_tree( $public ) ) === $before, 'Un rechazo cambió el sitio.' );
		n2s_check( array() === Digitalisimo_Backups_Jobs::active(), 'Un rechazo dejó un trabajo.' );
	} );

	n2s_case( 'N7 REST y WP-CLI con site', function() use ( $file, $db ) {
		$rest = digi_rest( 'POST', 'digitalisimo-backups/v1/backups/' . $file . '/verify', array( 'site' => '3' ) );
		n2s_check( 200 === $rest[0] && 3 === $rest[1]['convert']['site'] && true === $rest[1]['compatible'], 'verify por REST no toma site: ' . json_encode( $rest ) );
		$rest = digi_rest( 'POST', 'digitalisimo-backups/v1/backups/' . $file . '/restore', array( 'confirm' => 'RESTAURAR', 'site' => '3' ) );
		n2s_check( in_array( $rest[0], array( 200, 202 ), true ), 'restore por REST con site falló: ' . json_encode( $rest ) );
		if ( 202 === $rest[0] ) digi_drive( $rest[1]['job']['id'] );
		for ( $i = 0; $i < 30 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups::service_tick();
		n2s_check( 'otro-tema' === get_option( 'stylesheet' ) && array( 'digitalisimo-backups/digitalisimo-backups.php', 'plugin-tres/plugin-tres.php', 'red-plugin/red-plugin.php' ) === get_option( 'active_plugins' ), 'REST no restauró el sitio 3: ' . json_encode( get_option( 'active_plugins' ) ) );
		$routes = $GLOBALS['digi']['routes']['digitalisimo-backups/v1/backups/(?P<file>[A-Za-z0-9._-]+)/restore'] ?? null;
		n2s_check( null === $routes || isset( $routes[0]['args']['site'] ), 'La ruta de restore no declara site.' );
		$doc = ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'restore' ) )->getDocComment();
		n2s_check( false !== strpos( $doc, '[--site=<id>]' ) && false !== strpos( ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'verify' ) )->getDocComment(), '[--site=<id>]' ) && false !== strpos( ( new ReflectionMethod( 'Digitalisimo_Backups_Cli', 'migrate' ) )->getDocComment(), '[--site=<id>]' ), 'WP-CLI no documenta --site.' );
		n2s_check( array( 'site' => '2', 'super_admins' => 'all', 'network_secrets' => true ) === Digitalisimo_Backups_Cli::network( array( 'site' => '2', 'super-admins' => 'all', 'network-secrets' => true ) ), 'WP-CLI no pasa --site y sus opciones.' );
	} );

	n2s_case( 'N8 una falla después del cambio vuelve sola a la copia previa', function() use ( $file, $db, $public ) {
		$before = array( n2s_tables_print( $db ), n2s_tree( $public ) );
		$GLOBALS['digi']['filters']['digitalisimo_backups_job_max_attempts'][] = function( $max ) { return 1; };
		// La falla: tras el cambio de tablas y archivos, la reubicación no puede fijar las direcciones (N2s_DB).
		$GLOBALS['n2s_fail'] = true;
		$result = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR', 'site' => 2 ) );
		$GLOBALS['n2s_fail'] = false;
		array_pop( $GLOBALS['digi']['filters']['digitalisimo_backups_job_max_attempts'] );
		for ( $i = 0; $i < 60 && Digitalisimo_Backups_Jobs::active(); $i++ ) Digitalisimo_Backups::service_tick();
		n2s_check( is_wp_error( $result ) && 'digitalisimo_backups_restore_failed' === $result->get_error_code(), 'La restauración con la falla no dice que falló: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
		n2s_check( array( n2s_tables_print( $db ), n2s_tree( $public ) ) === $before, 'Tras la falla el sitio no volvió a como estaba (la copia previa).' );
	} );

	n2s_case( 'N9 un respaldo de la red cifrado (DGBK), con su frase', function() use ( $public, $db, $cap, $roles, $options, $posts, $users, $user, $root, $store ) {
		n2s_network( $public, $db, $cap, $roles, $options, $posts, $users, $user );
		$phrase = 'frase de toda la red 0123456789';
		Digitalisimo_Backups_Keys::set_passphrase( $phrase );
		update_site_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => true ) );
		$sealed = n2s_backup();
		n2s_check( '.dgbk' === substr( $sealed, -5 ), 'El respaldo de la red no salió cifrado: ' . $sealed );
		copy( $store . '/' . $sealed, $root . '/red.dgbk' );
		n2s_destination( $public, $db, $cap, $roles, $options, $posts, $users, $user );
		copy( $root . '/red.dgbk', $store . '/' . $sealed );
		$need = Digitalisimo_Backups::service_restore( $sealed, array( 'confirm' => 'RESTAURAR', 'site' => 2 ) );
		n2s_check( is_wp_error( $need ) && 'digitalisimo_backups_secret_required' === $need->get_error_code(), 'El respaldo cifrado se abrió sin su frase.' );
		$restored = Digitalisimo_Backups::service_restore( $sealed, array( 'confirm' => 'RESTAURAR', 'site' => 2, 'secret' => $phrase ) );
		n2s_check( ! is_wp_error( $restored ) && true === $restored['restored'], 'El sitio 2 del respaldo cifrado no se restauró: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
		n2s_check( 'tema-hijo' === get_option( 'stylesheet' ) && is_file( $public . '/wp-content/uploads/2026/11/foto.jpg' ), 'El sitio del respaldo cifrado no quedó como el sitio 2.' );
		foreach ( glob( $store . '/.staging/*' ) ?: array() as $left ) n2s_check( false, 'Quedó algo descifrado en .staging: ' . basename( $left ) );
	} );

	n2s_case( 'N10 un punto incremental de la red: cadena, conversión y restauración', function() use ( $public, $db, $cap, $roles, $options, $posts, $users, $user, $root, $store ) {
		n2s_network( $public, $db, $cap, $roles, $options, $posts, $users, $user );
		update_site_option( Digitalisimo_Backups_Security::OPTION, array( 'encrypt' => false ) );
		foreach ( scandir( $store ) as $name ) if ( preg_match( '/\.(zip|dgbk)$/', $name ) ) @unlink( $store . '/' . $name );
		$base = n2s_backup( array( 'incremental' => true ) );
		digi_write( $public . '/wp-content/uploads/sites/2/2026/11/nueva.jpg', 'medio nuevo del sitio dos' );
		$db->insert( 'wp_2_posts', array( 'ID' => 8, 'post_author' => 3, 'post_title' => 'Después de la base', 'post_content' => 'nuevo', 'post_type' => 'post', 'guid' => 'https://red.test/dos/?p=8' ) );
		$point = n2s_backup( array( 'incremental' => true ) );
		n2s_check( $base !== $point, 'No salió un punto incremental.' );
		copy( $store . '/' . $base, $root . '/base.zip' );
		copy( $store . '/' . $point, $root . '/punto.zip' );
		n2s_destination( $public, $db, $cap, $roles, $options, $posts, $users, $user );
		foreach ( scandir( $store ) as $name ) if ( preg_match( '/\.(zip|dgbk)$/', $name ) ) @unlink( $store . '/' . $name );
		n2s_add( $root . '/base.zip', $base );
		n2s_add( $root . '/punto.zip', $point );
		$row = n2s_row( $point );
		n2s_check( $row && true === $row['restorable'] && 'incremental' === $row['type'], 'El punto incremental de la red no se restaura aquí: ' . json_encode( $row ) );
		$restored = Digitalisimo_Backups::service_restore( $point, array( 'confirm' => 'RESTAURAR', 'site' => 2 ) );
		n2s_check( ! is_wp_error( $restored ) && true === $restored['restored'], 'El sitio 2 del punto incremental no se restauró: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : json_encode( $restored ) ) );
		$titles = array_column( $db->rows( 'dst_posts' ), 'post_title' );
		n2s_check( in_array( 'Después de la base', $titles, true ) && is_file( $public . '/wp-content/uploads/2026/11/nueva.jpg' ) && 'tema-hijo' === get_option( 'stylesheet' ), 'El sitio no es el del punto (su entrada y su medio nuevos): ' . json_encode( $titles ) );
	} );

	n2s_case( 'N11 migración con clave de una red a este WordPress: compatible y marcada', function() use ( $public, $db, $cap, $roles, $options, $posts, $users, $user, $saved, $store ) {
		$hello = array( 'version' => 1, 'instance' => 'aaaaaaaaaaaaaaaa', 'ready' => true, 'multisite' => true, 'subdomain_install' => false );
		n2s_check( true === Digitalisimo_Backups_Migrate::compatible( $hello ), 'La migración de una red a un WordPress individual se rechaza.' );
		$GLOBALS['digi']['multisite'] = true;
		try {
			Digitalisimo_Backups_Migrate::compatible( array( 'multisite' => false ) + $hello );
			$single_to_network = false;
		} catch ( Exception $e ) {
			$single_to_network = true;
		}
		$GLOBALS['digi']['multisite'] = false;
		n2s_check( $single_to_network, 'Se aceptó migrar un individual a una red (es de la otra rama).' );
		n2s_destination( $public, $db, $cap, $roles, $options, $posts, $users, $user );
		copy( $saved, $store . '/paquete.zip' );
		$report = Digitalisimo_Backups_Security::open( $store . '/paquete.zip' );
		try {
			$convert = Digitalisimo_Backups::migration_convert( $report, array( 'job' => 'migracion-de-prueba', 'from' => 'https://red.test', 'state' => 'restoring' ), 'migracion-de-prueba', 2 );
		} finally {
			Digitalisimo_Backups_Security::close( $report );
		}
		n2s_check( 'convert' === $convert['spec']['kind'] && 2 === $convert['spec']['convert']['site'] && true === $convert['spec']['restore']['exact_tables'] && 'migracion-de-prueba' === $convert['spec']['restore']['safety_extra']['migration']['job'], 'La conversión de la migración no lleva lo de la migración: ' . json_encode( $convert['spec'] ) );
		$done = digi_drive( $convert['id'] );
		n2s_check( 'success' === $done['status'], 'La conversión de la migración no terminó: ' . json_encode( $done ) );
		$restore = Digitalisimo_Backups_Jobs::get( (string) Digitalisimo_Backups_Jobs::get( $convert['id'] )['result']['restore_job'] );
		n2s_check( $restore && 'migration' === $restore['spec']['origin']['via'] && 'migracion-de-prueba' === $restore['spec']['safety_extra']['migration']['job'] && ! empty( $restore['spec']['exact_tables'] ), 'La restauración de la migración no quedó marcada: ' . json_encode( $restore['spec'] ?? null ) );
		digi_drive( $restore['id'] );
		n2s_check( 'tema-hijo' === get_option( 'stylesheet' ), 'La migración no dejó el sitio 2 de la red.' );
	} );

	echo ( $failures ? 'FALLA' : 'ok' ) . ' · red → individual: ' . ( 11 - $failures ) . ' de 11 casos' . "\n";
} finally {
	digi_rmtree( $root );
}
exit( $failures ? 1 : 0 );
