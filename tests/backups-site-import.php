<?php
/**
 * Importar un WordPress individual en una red (pedido del propietario del 2026-11-16: «Subí un respaldo y me sale “No se
 * restaura aquí”… déjalo igual que UpdraftPlus, sin importar multisite o WP normal»). Base de datos SQLite de verdad
 * (tests/lib/backups-sqlite.php) con una red por subdirectorios (y por subdominios donde se dice):
 *
 *   a · el paquete de un sitio (individual, extraído o la copia de un sitio) se importa en una red: la fila subida ya no
 *       dice «No se restaura aquí», la restauración lo acepta con un destino y sin destino pide elegirlo; un respaldo de
 *       red sigue igual y la copia de un sitio no se restaura en un individual;
 *   b · el destino: sitio nuevo (dirección sugerida desde el respaldo, ruta o subdominio, nombres reservados, dirección
 *       ocupada) o reemplazo (no el principal, sólo de la red);
 *   c · el plan de E1 hacia el sitio: prefijo `wp_N_` (también `user_roles`), dirección, rutas y la URL de los medios hacia
 *       `uploads/sites/N` antes que la del sitio (también en serializado y JSON), en subdirectorios y subdominios, y de un
 *       sitio extraído (sus medios `/sites/K` de su red);
 *   d · los archivos: medios a `uploads/sites/N`, plugins y temas que faltan, los que ya están (y su versión) intactos, y
 *       núcleo, mu-plugins, drop-ins, idiomas y el resto de wp-content fuera;
 *   e · los usuarios: misma cuenta (usuario y correo), por correo, nombre ocupado por otra persona, nuevos; ninguna
 *       contraseña, correo ni meta global de la red cambia; roles en `wp_N_capabilities`; los administradores del sitio
 *       que se reemplaza siguen siéndolo; los superadministradores no cambian; las sesiones del origen no viajan;
 *       repetible tras una muerte (un INSERT sin anotar); la vuelta a la copia (exacta) y deshacer lo añadido;
 *   f · la autoría: entradas, comentarios, enlaces, metas y WooCommerce a los ID de la red; los huérfanos a 0; repetible;
 *   g · las opciones del sitio: su carpeta de medios, sin la configuración ni los eventos de Backups del respaldo, y su
 *       tema habilitado sólo para él;
 *   h · el mantenimiento: sólo el sitio que cambia (el resto de la red, la administración de la red, WP-Cron y WP-CLI
 *       pasan), y WP-Cron de la red sin limitar;
 *   i · REST y WP-CLI: `into` y `address`; la copia de un sitio para database_sql_to_file();
 *   j · la falla inyectada justo después de fundir los usuarios: lo deshecho deja los usuarios de la red como estaban.
 * Con el código anterior la clase Digitalisimo_Backups_Site_Import no existe y la fila subida dice «No se restaura aquí».
 * Escribe «FALLA ·».
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
/** Con el prefijo de cada sitio de la red (como wpdb en una red). */
class Si_DB extends Bk_Sqlite_DB {
	public function get_blog_prefix( $blog_id = null ) { $blog_id = null === $blog_id ? 1 : (int) $blog_id; return 1 === $blog_id ? $this->base_prefix : $this->base_prefix . $blog_id . '_'; }
}
$GLOBALS['bk_multisite'] = true;
$GLOBALS['si_subdomain'] = false;
if ( ! function_exists( 'is_subdomain_install' ) ) { function is_subdomain_install() { return ! empty( $GLOBALS['si_subdomain'] ); } }
if ( ! function_exists( 'get_main_network_id' ) ) { function get_main_network_id() { return 1; } }
if ( ! function_exists( 'get_network' ) ) { function get_network() { return (object) array( 'id' => 1, 'domain' => 'red.test', 'path' => '/' ); } }
if ( ! function_exists( 'switch_to_blog' ) ) { function switch_to_blog( $id ) { $GLOBALS['si_stack'][] = $GLOBALS['wpdb']->prefix; $GLOBALS['wpdb']->prefix = 1 === (int) $id ? $GLOBALS['wpdb']->base_prefix : $GLOBALS['wpdb']->base_prefix . (int) $id . '_'; return true; } }
if ( ! function_exists( 'restore_current_blog' ) ) { function restore_current_blog() { $GLOBALS['wpdb']->prefix = array_pop( $GLOBALS['si_stack'] ) ?? $GLOBALS['wpdb']->base_prefix; return true; } }
if ( ! function_exists( 'do_action' ) ) { function do_action( $hook, ...$args ) { foreach ( (array) ( $GLOBALS['si_actions'][ $hook ] ?? array() ) as $callback ) call_user_func_array( $callback, $args ); } }
$GLOBALS['bk_filters']['digitalisimo_backups_site_import_uploads'] = function( $uploads, $id ) { return array( 'basedir' => WP_CONTENT_DIR . '/uploads/sites/' . (int) $id, 'baseurl' => 'http://red.test/' . ( $GLOBALS['si_subdomain'] ? '' : 'tienda/' ) . 'wp-content/uploads/sites/' . (int) $id ); };

$I = 'Digitalisimo_Backups_Site_Import';
$GLOBALS['si_have'] = class_exists( $I );
$failures = 0;
function si_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function si_case( $label, callable $test ) {
	try {
		if ( ! $GLOBALS['si_have'] ) throw new RuntimeException( 'No existe Digitalisimo_Backups_Site_Import (código anterior: un respaldo individual no se importa en una red).' );
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
const SI_HASH_ANA = '$wp$2y$10$anaDeLaRedanaDeLaRedanaDeLaRedanaDeLaRedanaDeLa';
const SI_HASH_BETO = '$P$Bbeto.red.beto.red.beto.red.';
const SI_HASH_CARLA = '$P$Bcarla.red.carla.red.carla.r';
const SI_HASH_ADMIN = '$P$Badmin.red.admin.red.admin.re';
const SI_HASH_DUENO = '$P$Bdueno.red.dueno.red.dueno.re';
const SI_HASH_VIEJO = '$P$Bviejo.red.viejo.red.viejo.re';
const SI_HASH_BK = '$P$Bbackup.backup.backup.backup.';

/** Una red por subdirectorios en SQLite: el principal, el sitio 2 (tienda) y sus usuarios. */
function si_network() {
	@unlink( $GLOBALS['bk_root'] . '-red.sqlite' );
	$db = new Si_DB( $GLOBALS['bk_root'] . '-red.sqlite', 'wp_' );
	$db->pdo->exec( "CREATE TABLE `wp_blogs` (`blog_id` INTEGER PRIMARY KEY, `site_id` bigint NOT NULL DEFAULT 1, `domain` varchar(200) NOT NULL DEFAULT '', `path` varchar(100) NOT NULL DEFAULT '', `registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `last_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `public` tinyint NOT NULL DEFAULT 1, `archived` tinyint NOT NULL DEFAULT 0, `mature` tinyint NOT NULL DEFAULT 0, `spam` tinyint NOT NULL DEFAULT 0, `deleted` tinyint NOT NULL DEFAULT 0, `lang_id` int NOT NULL DEFAULT 0)" );
	$db->seed( 'wp_blogs', array( array( 'blog_id' => 1, 'domain' => 'red.test', 'path' => '/' ), array( 'blog_id' => 2, 'domain' => 'red.test', 'path' => '/tienda/' ), array( 'blog_id' => 3, 'domain' => 'red.test', 'path' => '/blog-viejo/' ) ) );
	$db->pdo->exec( "CREATE TABLE `wp_users` (`ID` INTEGER PRIMARY KEY AUTOINCREMENT, `user_login` varchar(60) NOT NULL DEFAULT '', `user_pass` varchar(255) NOT NULL DEFAULT '', `user_nicename` varchar(50) NOT NULL DEFAULT '', `user_email` varchar(100) NOT NULL DEFAULT '', `user_url` varchar(100) NOT NULL DEFAULT '', `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `user_activation_key` varchar(255) NOT NULL DEFAULT '', `user_status` int NOT NULL DEFAULT 0, `display_name` varchar(250) NOT NULL DEFAULT '', `spam` tinyint NOT NULL DEFAULT 0, `deleted` tinyint NOT NULL DEFAULT 0)" );
	$db->pdo->exec( "CREATE TABLE `wp_usermeta` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	foreach ( array( 'wp_', 'wp_2_', 'wp_3_' ) as $prefix ) $db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY AUTOINCREMENT, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$db->seed( 'wp_2_options', array( array( 'option_name' => 'home', 'option_value' => 'http://red.test/tienda' ), array( 'option_name' => 'upload_path', 'option_value' => '' ), array( 'option_name' => 'blogname', 'option_value' => 'Tienda de la red' ) ) );
	$caps = function( $role ) { return serialize( array( $role => true ) ); };
	// 1 admin (superadministrador), 2 ana (miembro y administradora del sitio 2), 3 beto, 4 carla (sólo del principal), 5 dueno
	// (administrador del sitio 2, no viene en el respaldo), 6 viejo (editor del sitio 2, no viene en el respaldo).
	foreach ( array(
		array( 1, 'admin', SI_HASH_ADMIN, 'admin@red.test', array( 'wp_capabilities' => $caps( 'administrator' ), 'nickname' => 'admin' ) ),
		array( 2, 'ana', SI_HASH_ANA, 'ana@ejemplo.test', array( 'wp_2_capabilities' => $caps( 'administrator' ), 'wp_2_user_level' => '10', 'first_name' => 'Ana de la red', 'session_tokens' => 'sesiones-de-la-red' ) ),
		array( 3, 'beto', SI_HASH_BETO, 'beto@ejemplo.test', array( 'wp_capabilities' => $caps( 'subscriber' ), 'first_name' => 'Beto de la red' ) ),
		array( 4, 'carla', SI_HASH_CARLA, 'carla@red.test', array( 'wp_capabilities' => $caps( 'author' ) ) ),
		array( 5, 'dueno', SI_HASH_DUENO, 'dueno@red.test', array( 'wp_2_capabilities' => $caps( 'administrator' ), 'wp_2_user_level' => '10' ) ),
		array( 6, 'viejo', SI_HASH_VIEJO, 'viejo@red.test', array( 'wp_2_capabilities' => $caps( 'editor' ), 'wp_2_user_level' => '7' ) ),
	) as $user ) {
		$db->seed( 'wp_users', array( array( 'ID' => $user[0], 'user_login' => $user[1], 'user_pass' => $user[2], 'user_nicename' => $user[1], 'user_email' => $user[3], 'display_name' => ucfirst( $user[1] ) ) ) );
		foreach ( $user[4] as $key => $value ) $db->seed( 'wp_usermeta', array( array( 'user_id' => $user[0], 'meta_key' => $key, 'meta_value' => $value ) ) );
	}
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_site_options'] = array( 'allowedthemes' => array( 'twentytwentyfive' => true ), 'site_admins' => array( 'admin' ) );
	return $db;
}
/** Los usuarios del respaldo en sus temporales (con las claves del sitio ya en `wp_2_`, como las deja el plan de E1). */
function si_backup_users( Bk_Sqlite_DB $db ) {
	$db->pdo->exec( "CREATE TABLE `wp_dgbkr_aaaaaa_090` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60), `user_pass` varchar(255), `user_nicename` varchar(50), `user_email` varchar(100), `user_url` varchar(100), `user_registered` datetime, `user_activation_key` varchar(255), `user_status` int, `display_name` varchar(250))" );
	$db->pdo->exec( "CREATE TABLE `wp_dgbkr_aaaaaa_091` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint, `meta_key` varchar(255), `meta_value` longtext)" );
	$caps = function( $role ) { return serialize( array( $role => true ) ); };
	// 1 ana (misma cuenta que en la red, editora en el respaldo), 2 betito (el correo de beto), 3 carla (otra persona: otro
	// correo), 4 nuevo, 5 autora.
	foreach ( array(
		array( 1, 'ana', SI_HASH_BK, 'ANA@ejemplo.test', array( 'wp_2_capabilities' => $caps( 'editor' ), 'wp_2_user_level' => '7', 'first_name' => 'Ana del respaldo', 'session_tokens' => 'sesiones-del-origen' ) ),
		array( 2, 'betito', SI_HASH_BK, 'beto@ejemplo.test', array( 'wp_2_capabilities' => $caps( 'author' ), 'first_name' => 'Beto del respaldo' ) ),
		array( 3, 'carla', SI_HASH_BK, 'carla@otra.test', array( 'wp_2_capabilities' => $caps( 'contributor' ), 'nickname' => 'carla' ) ),
		array( 4, 'nuevo', SI_HASH_BK, 'nuevo@ejemplo.test', array( 'wp_2_capabilities' => $caps( 'administrator' ), 'wp_2_user_level' => '10', 'nickname' => 'nuevo', 'session_tokens' => 'x', 'primary_blog' => '1', 'wp_2_user-settings' => 'mfold=o' ) ),
		array( 5, 'autora', SI_HASH_BK, 'autora@ejemplo.test', array( 'wp_2_capabilities' => $caps( 'author' ) ) ),
	) as $user ) {
		$db->seed( 'wp_dgbkr_aaaaaa_090', array( array( 'ID' => $user[0], 'user_login' => $user[1], 'user_pass' => $user[2], 'user_nicename' => $user[1], 'user_email' => $user[3], 'user_url' => '', 'user_registered' => '2026-01-01 00:00:00', 'user_activation_key' => 'clave-vieja', 'user_status' => 0, 'display_name' => ucfirst( $user[1] ) ) ) );
		foreach ( $user[4] as $key => $value ) $db->seed( 'wp_dgbkr_aaaaaa_091', array( array( 'user_id' => $user[0], 'meta_key' => $key, 'meta_value' => $value ) ) );
	}
}
function si_meta( Bk_Sqlite_DB $db, $id, $key ) { $rows = $db->rows( 'wp_usermeta', 'user_id = ' . (int) $id . " AND meta_key = '" . $key . "'" ); return $rows ? $rows[0]['meta_value'] : null; }
function si_user( Bk_Sqlite_DB $db, $login ) { $rows = $db->rows( 'wp_users', "user_login = '" . $login . "'" ); return $rows ? $rows[0] : null; }
/** Huella de una tabla (sus filas en orden). */
function si_print( Bk_Sqlite_DB $db, $table, $order = 'rowid' ) { return hash( 'sha256', json_encode( $db->pdo->query( 'SELECT * FROM "' . $table . '" ORDER BY ' . $order )->fetchAll( PDO::FETCH_ASSOC ) ) ); }
function si_merge( Bk_Sqlite_DB $db, $mode, array $journal = array(), $rollback = false ) {
	$saved = array();
	$out = Digitalisimo_Backups_Site_Import::merge_users( array( 'users' => 'wp_dgbkr_aaaaaa_090', 'usermeta' => 'wp_dgbkr_aaaaaa_091', 'site_id' => 2, 'domain' => 'red.test', 'mode' => $mode, 'rollback' => $rollback ), $journal, function( array $journal ) use ( &$saved ) { $saved = $journal; } );
	return array( $out, $saved );
}

/* ------------------------------------------------------------------ a · alcance */

si_case( 'a · el respaldo de un sitio se importa en una red: la fila subida ya no dice «No se restaura aquí»', function() use ( $I ) {
	si_network();
	$row = array( 'file' => 'digitalisimo-backup-tienda-com.zip', 'scope' => 'installation', 'origin' => 'uploaded', 'upload' => array( 'home' => 'https://tienda.com' ), 'backup_type' => 'full' );
	si_check( true === bk_call( 'Digitalisimo_Backups', 'uploaded_restorable_scope', $row ), 'Un individual subido a una red sigue sin restaurarse aquí.' );
	si_check( '' === bk_call( 'Digitalisimo_Backups', 'uploaded_blocked_text', $row ), 'Un individual subido a una red sigue con el aviso de «No se restaura aquí».' );
	foreach ( array( 'extracted-site', 'network-site' ) as $scope ) si_check( true === bk_call( 'Digitalisimo_Backups', 'uploaded_restorable_scope', array( 'scope' => $scope ) + $row ), 'Un paquete ' . $scope . ' no se importa en una red.' );
	si_check( true === bk_call( 'Digitalisimo_Backups', 'uploaded_restorable_scope', array( 'scope' => 'network' ) + $row ), 'El respaldo de una red dejó de restaurarse en una red.' );
	$single = array( 'scope' => 'installation', 'multisite' => false, 'home_url' => 'https://tienda.com' );
	si_check( array() === bk_call( 'Digitalisimo_Backups', 'package_installation_problems', $single ), 'verify y la lista rechazan el individual en la red.' );
	$problems = bk_call( 'Digitalisimo_Backups', 'package_installation_problems', $single, null );
	si_check( 1 === count( $problems ) && false !== strpos( $problems[0], 'into=new' ), 'La restauración sin destino no pide elegirlo: ' . json_encode( $problems ) );
	si_check( array() === bk_call( 'Digitalisimo_Backups', 'package_installation_problems', $single, array( 'mode' => 'new' ) ), 'La restauración con destino lo rechaza.' );
	si_check( $I::is_site_package( array( 'scope' => 'extracted-site' ) ) && $I::is_site_package( array( 'scope' => 'network-site' ) ) && ! $I::is_site_package( array( 'scope' => 'installation', 'multisite' => true ) ), 'Mal reconocido el paquete de un sitio.' );
	$GLOBALS['bk_multisite'] = false;
	try {
		si_check( false === bk_call( 'Digitalisimo_Backups', 'uploaded_restorable_scope', array( 'scope' => 'network-site' ) + $row ), 'La copia de un sitio de una red se ofrece en un individual.' );
		si_check( (bool) bk_call( 'Digitalisimo_Backups', 'package_installation_problems', array( 'scope' => 'network-site', 'multisite' => false ) ), 'La copia de un sitio se restauraría en un individual (sin núcleo).' );
		si_check( array() === bk_call( 'Digitalisimo_Backups', 'package_installation_problems', $single ), 'Un individual dejó de restaurarse en un individual.' );
	} finally {
		$GLOBALS['bk_multisite'] = true;
	}
	si_check( $I::required_entries( array( 'scope' => 'network-site' ) ) === array( 'database.sql' ), 'La copia de un sitio exige el núcleo.' );
} );

/* ------------------------------------------------------------------ b · destino */

si_case( 'b · sitio nuevo: dirección sugerida, ruta o subdominio, reservados y ocupados; reemplazo: no el principal', function() use ( $I ) {
	si_network();
	$new = $I::request( array( 'into' => 'new', 'address' => 'Mi-Tienda' ) );
	si_check( is_array( $new ) && 'new' === $new['mode'] && 'red.test' === $new['domain'] && '/mi-tienda/' === $new['path'] && 'http://example.test/mi-tienda' !== $new['url'], 'Sitio nuevo en una red por subdirectorios: ' . json_encode( $new ) );
	si_check( 'tienda-2' === $I::suggest( 'https://www.tienda.com' ), 'La sugerencia no evita la dirección ocupada (tienda): ' . $I::suggest( 'https://www.tienda.com' ) );
	si_check( 'blog-2' !== $I::suggest( 'https://ejemplo.com/blog' ) && 'importado' === $I::suggest( 'https://ejemplo.com/blog' ), 'Un nombre reservado de WordPress (blog) se sugirió: ' . $I::suggest( 'https://ejemplo.com/blog' ) );
	si_check( 'importado' === $I::suggest( 'http://127.0.0.1:8080' ), 'Una IP no da una dirección legible.' );
	$taken = $I::request( array( 'into' => 'new', 'address' => 'tienda' ) );
	si_check( is_wp_error( $taken ) && 409 === (int) $taken->get_error_data()['status'], 'Una dirección ocupada se aceptó.' );
	si_check( is_wp_error( $I::request( array( 'into' => 'new', 'address' => 'wp-admin' ) ) ), 'Un nombre reservado se aceptó.' );
	$GLOBALS['si_subdomain'] = true;
	try {
		$sub = $I::request( array( 'into' => 'new', 'address' => 'tienda' ) );
		si_check( is_array( $sub ) && 'tienda.red.test' === $sub['domain'] && '/' === $sub['path'], 'Sitio nuevo en una red por subdominios: ' . json_encode( $sub ) );
		si_check( is_array( $I::request( array( 'into' => 'new', 'address' => 'blog' ) ) ), 'En una red por subdominios «blog» no es reservado.' );
	} finally {
		$GLOBALS['si_subdomain'] = false;
	}
	$replace = $I::request( array( 'into' => '2' ) );
	si_check( is_array( $replace ) && 'replace' === $replace['mode'] && 2 === $replace['site_id'] && '/tienda/' === $replace['path'], 'Reemplazo del sitio 2: ' . json_encode( $replace ) );
	si_check( is_wp_error( $I::request( array( 'into' => '1' ) ) ), 'Se ofreció reemplazar el sitio principal.' );
	si_check( is_wp_error( $I::request( array( 'into' => '99' ) ) ), 'Se aceptó un sitio que no existe.' );
	si_check( is_wp_error( $I::request( array( 'into' => 'algo' ) ) ), 'Se aceptó un destino sin sentido.' );
	si_check( array( 2 => 'red.test/tienda', 3 => 'red.test/blog-viejo' ) === $I::sites(), 'La lista de sitios que se pueden reemplazar: ' . json_encode( $I::sites() ) );
	$GLOBALS['bk_multisite'] = false;
	try { si_check( is_wp_error( $I::request( array( 'into' => 'new' ) ) ), 'Un individual aceptó importar como sitio de una red.' ); } finally { $GLOBALS['bk_multisite'] = true; }
	$post = $I::from_request( array( 'into' => 'replace', 'into_site' => '3', 'address' => 'x' ) );
	si_check( '3' === $post['into'], 'La confirmación con «reemplazando» no da el sitio elegido.' );
} );

/* ------------------------------------------------------------------ c · plan de E1 */

si_case( 'c · el plan: prefijo wp_N_, dirección, rutas y los medios a uploads/sites/N antes que la URL del sitio', function() use ( $I ) {
	si_network();
	$origin = array( 'home' => 'https://tienda.com', 'siteurl' => 'https://tienda.com', 'abspath' => '/srv/tienda', 'content_dir' => '/srv/tienda/wp-content', 'uploads_dir' => '/srv/tienda/wp-content/uploads', 'prefix' => 'wp_', 'multisite' => false );
	$target = array( 'mode' => 'new', 'site_id' => 7, 'domain' => 'red.test', 'path' => '/tienda/', 'url' => 'http://red.test/tienda', 'siteurl' => 'http://red.test/tienda', 'label' => 'red.test/tienda' );
	$uploads = $I::uploads( 7, $target );
	$destination = $I::destination( $target );
	si_check( 'wp_7_' === $destination['prefix'] && false === $destination['multisite'] && '/uploads/sites/7' === substr( $destination['uploads_dir'], -16 ), 'El destino no es el sitio 7: ' . json_encode( $destination ) );
	$pairs = $I::media_pairs( array( 'scope' => 'installation' ), $origin, $uploads );
	si_check( array( 'https://tienda.com/wp-content/uploads', 'http://red.test/tienda/wp-content/uploads/sites/7' ) === $pairs[0], 'Falta el par propio de los medios: ' . json_encode( $pairs ) );
	$plan = $I::plan( $origin, $destination, array( 'tables' => array( 'wp_options', 'wp_posts', 'wp_users', 'wp_usermeta', 'wp_wc_orders' ) ), $pairs );
	si_check( ! $plan->problems(), 'El plan tiene problemas: ' . implode( ' ', $plan->problems() ) );
	$map = $plan->table_map( array( 'wp_options', 'wp_posts', 'wp_users', 'wp_wc_orders' ) );
	si_check( 'wp_7_options' === $map['wp_options'] && 'wp_7_wc_orders' === $map['wp_wc_orders'] && 'wp_7_users' === $map['wp_users'], 'Las tablas no pasan al prefijo del sitio: ' . json_encode( $map ) );
	si_check( 'wp_7_user_roles' === $plan->rename_key( 'wp_user_roles', 'options' ) && 'wp_7_capabilities' === $plan->rename_key( 'wp_capabilities', 'usermeta' ), 'Las claves con prefijo no pasan al del sitio.' );
	$media = 'https://tienda.com/wp-content/uploads/2026/11/foto.jpg';
	si_check( 'http://red.test/tienda/wp-content/uploads/sites/7/2026/11/foto.jpg' === $plan->rewrite( $media ), 'La URL de un medio no va a uploads/sites/7: ' . $plan->rewrite( $media ) );
	si_check( 'http://red.test/tienda/contacto/' === $plan->rewrite( 'https://tienda.com/contacto/' ), 'La URL de una página no va al sitio: ' . $plan->rewrite( 'https://tienda.com/contacto/' ) );
	$serialized = serialize( array( 'url' => $media, 'json' => json_encode( array( 'src' => $media ) ) ) );
	$out = $I::unserialize( $plan->rewrite( $serialized ) );
	si_check( is_array( $out ) && 'http://red.test/tienda/wp-content/uploads/sites/7/2026/11/foto.jpg' === $out['url'] && false !== strpos( $out['json'], 'uploads\/sites\/7\/2026' ), 'En serializado y JSON los medios no van a sites/7: ' . json_encode( $out ) );
	si_check( '/srv/red/wp-content/uploads/sites/7/2026/x.pdf' !== '' && 0 === strpos( $plan->rewrite( '/srv/tienda/wp-content/uploads/2026/x.pdf' ), rtrim( WP_CONTENT_DIR, '/' ) . '/uploads/sites/7/2026/x.pdf' ), 'La ruta de un medio no va a la carpeta del sitio: ' . $plan->rewrite( '/srv/tienda/wp-content/uploads/2026/x.pdf' ) );
	// Por subdominios.
	$GLOBALS['si_subdomain'] = true;
	try {
		$sub = array( 'site_id' => 8, 'url' => 'http://tienda.red.test', 'siteurl' => 'http://tienda.red.test', 'domain' => 'tienda.red.test', 'path' => '/' );
		$uploads = $I::uploads( 8, $sub );
		$plan = $I::plan( $origin, $I::destination( $sub ), array(), $I::media_pairs( array( 'scope' => 'installation' ), $origin, $uploads ) );
		si_check( 'http://tienda.red.test/2026/11/hola/' === $plan->rewrite( 'https://tienda.com/2026/11/hola/' ) && 'http://red.test/wp-content/uploads/sites/8/a.png' === $plan->rewrite( 'https://tienda.com/wp-content/uploads/a.png' ), 'En una red por subdominios: ' . $plan->rewrite( 'https://tienda.com/wp-content/uploads/a.png' ) );
	} finally {
		$GLOBALS['si_subdomain'] = false;
	}
	// Un sitio extraído de otra red: sus medios /sites/K (y su alias del dominio de la red) van a los del sitio.
	$extracted = array( 'scope' => 'extracted-site', 'extract' => array( 'uploads' => array( 'baseurl' => 'https://otra.red/dos/wp-content/uploads/sites/2', 'aliases' => array( 'https://otra.red/wp-content/uploads/sites/2' ) ) ) );
	$pairs = $I::media_pairs( $extracted, array( 'home' => 'https://otra.red/dos' ), array( 'baseurl' => 'http://red.test/tienda/wp-content/uploads/sites/7' ) );
	si_check( 2 === count( $pairs ) && 'https://otra.red/wp-content/uploads/sites/2' === $pairs[1][0], 'Los medios de un sitio extraído: ' . json_encode( $pairs ) );
	$ex = $I::plan( array( 'home' => 'https://otra.red/dos', 'siteurl' => 'https://otra.red/dos', 'prefix' => 'wp_2_', 'multisite' => false ), $I::destination( $target ), array(), $pairs );
	si_check( 'http://red.test/tienda/wp-content/uploads/sites/7/x.jpg' === $ex->rewrite( 'https://otra.red/wp-content/uploads/sites/2/x.jpg' ) && 'wp_7_options' === $ex->table_map( array( 'wp_2_options' ) )['wp_2_options'], 'Un sitio extraído no pasa a su sitio de la red.' );
} );

/* ------------------------------------------------------------------ d · archivos */

si_case( 'd · archivos: medios a uploads/sites/N, plugins y temas que faltan, y nada más de la red', function() use ( $I ) {
	si_network();
	$root = rtrim( ABSPATH, '/' );
	foreach ( array( 'wp-content/plugins/compartido/compartido.php' => "<?php\n/* Plugin Name: Compartido\nVersion: 2.0 */", 'wp-content/themes/twentytwentyfive/style.css' => "/*\nTheme Name: TT5\nVersion: 1.3\n*/", 'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php\n/* Plugin Name: DIGITALÍSIMO Backups\nVersion: 9.9.9 */" ) as $name => $body ) bk_write_file( $root . '/' . $name, $body );
	$zip_path = $GLOBALS['bk_root'] . '-sitio.zip';
	$zip = new ZipArchive();
	$zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( array(
		'database.sql' => '', 'manifest.json' => '{}',
		'wordpress/wp-config.php' => '<?php', 'wordpress/wp-load.php' => '<?php', 'wordpress/index.php' => '<?php', 'wordpress/wp-admin/index.php' => '<?php', 'wordpress/wp-includes/version.php' => '<?php', 'wordpress/.htaccess' => '# reglas',
		'wordpress/wp-content/index.php' => '<?php', 'wordpress/wp-content/object-cache.php' => '<?php', 'wordpress/wp-content/cache/page.html' => 'x', 'wordpress/wp-content/languages/es_MX.mo' => 'x',
		'wordpress/wp-content/mu-plugins/agencia.php' => '<?php', 'wordpress/wp-content/plugins/index.php' => '<?php',
		'wordpress/wp-content/plugins/nuevo-plugin/nuevo-plugin.php' => "<?php\n/* Plugin Name: Nuevo */", 'wordpress/wp-content/plugins/nuevo-plugin/inc/a.php' => '<?php',
		'wordpress/wp-content/plugins/compartido/compartido.php' => "<?php\n/* Plugin Name: Compartido\nVersion: 1.0 */",
		'wordpress/wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php\n/* Plugin Name: DIGITALÍSIMO Backups\nVersion: 9.9.9 */",
		'wordpress/wp-content/plugins/hola.php' => "<?php\n/* Plugin Name: Hola */",
		'wordpress/wp-content/themes/tema-tienda/style.css' => "/*\nTheme Name: Tienda\n*/", 'wordpress/wp-content/themes/twentytwentyfive/style.css' => "/*\nTheme Name: TT5\nVersion: 1.2\n*/",
		'wordpress/wp-content/uploads/2026/11/foto.jpg' => 'jpg', 'wordpress/wp-content/uploads/elementor/css/post-5.css' => 'css',
	) as $name => $body ) $zip->addFromString( $name, $body );
	$zip->close();
	$zip->open( $zip_path );
	$layout = bk_call( 'Digitalisimo_Backups', 'restore_layout' );
	$origin = array( 'abspath' => '/srv/tienda', 'content_dir' => '/srv/tienda/wp-content', 'uploads_dir' => '/srv/tienda/wp-content/uploads' );
	$rules = $I::file_rules( $zip, array( 'scope' => 'installation', 'multisite' => false ), $origin, $layout, WP_CONTENT_DIR . '/uploads/sites/7' );
	$zip->close();
	$map = array();
	foreach ( array( 'wp-content/uploads/2026/11/foto.jpg', 'wp-content/plugins/nuevo-plugin/inc/a.php', 'wp-content/plugins/hola.php', 'wp-content/themes/tema-tienda/style.css', 'wp-content/plugins/compartido/compartido.php', 'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php', 'wp-content/themes/twentytwentyfive/style.css', 'wp-config.php', 'index.php', '.htaccess', 'wp-admin/index.php', 'wp-content/mu-plugins/agencia.php', 'wp-content/object-cache.php', 'wp-content/languages/es_MX.mo', 'wp-content/cache/page.html' ) as $relative ) $map[ $relative ] = $I::target_of( $relative, $rules );
	si_check( 'wp-content/uploads/sites/7/2026/11/foto.jpg' === $map['wp-content/uploads/2026/11/foto.jpg'], 'Los medios no van a uploads/sites/7: ' . json_encode( $map ) );
	si_check( 'wp-content/plugins/nuevo-plugin/inc/a.php' === $map['wp-content/plugins/nuevo-plugin/inc/a.php'] && 'wp-content/plugins/hola.php' === $map['wp-content/plugins/hola.php'] && 'wp-content/themes/tema-tienda/style.css' === $map['wp-content/themes/tema-tienda/style.css'], 'Los plugins y temas que faltan no se instalan: ' . json_encode( $map ) );
	foreach ( array( 'wp-content/plugins/compartido/compartido.php', 'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php', 'wp-content/themes/twentytwentyfive/style.css', 'wp-config.php', 'index.php', '.htaccess', 'wp-admin/index.php', 'wp-content/mu-plugins/agencia.php', 'wp-content/object-cache.php', 'wp-content/languages/es_MX.mo', 'wp-content/cache/page.html' ) as $relative ) si_check( null === $map[ $relative ], $relative . ' se mezclaría en la red.' );
	si_check( array( 'hola.php', 'nuevo-plugin' ) === $rules['installed']['plugins'] && array( 'tema-tienda' ) === $rules['installed']['themes'], 'Lo instalado: ' . json_encode( $rules['installed'] ) );
	$versions = array_column( $rules['versions'], 'backup', 'name' );
	si_check( '1.0' === ( $versions['compartido'] ?? '' ) && '1.2' === ( $versions['twentytwentyfive'] ?? '' ) && ! isset( $versions['digitalisimo-backups'] ), 'Las versiones distintas no se avisan: ' . json_encode( $rules['versions'] ) );
	si_check( array( 'agencia.php' ) === $rules['mu_plugins'] && array( 'object-cache.php' ) === $rules['drop_ins'] && 1 === (int) $rules['skipped']['languages'], 'Lo que no se mezcla: ' . json_encode( $rules ) );
	si_check( 'wp-content/uploads/2026/11/foto.jpg' === $I::source_of( 'wp-content/uploads/sites/7/2026/11/foto.jpg', $rules ) && null === $I::source_of( 'wp-content/uploads/sites/2/x.jpg', $rules ) && null === $I::source_of( 'wp-content/plugins/compartido/compartido.php', $rules ), 'El camino de vuelta (para lo que el respaldo no trae) está mal.' );
	$warnings = implode( ' ', $I::warnings( $rules ) );
	si_check( false !== strpos( $warnings, 'compartido' ) && false !== strpos( $warnings, 'agencia.php' ) && false !== strpos( $warnings, 'object-cache.php' ), 'Los avisos no nombran lo que no se instala: ' . $warnings );
	// Las raíces del recorrido de la importación: sólo los medios del sitio (nunca un PHP de la raíz ni otro sitio).
	bk_set_static( 'Digitalisimo_Backups', 'import_rules', $rules );
	try {
		$layout = bk_call( 'Digitalisimo_Backups', 'restore_layout' );
		si_check( ! empty( $layout['import'] ) && 'wp-content/uploads/sites/7' === $layout['uploads'] && '' === $layout['mu'], 'Las raíces de la importación: ' . json_encode( $layout ) );
		si_check( ! bk_call( 'Digitalisimo_Backups', 'restore_managed', 'index.php', $layout ) && ! bk_call( 'Digitalisimo_Backups', 'restore_managed', 'wp-content/uploads/sites/2/x.jpg', $layout ) && ! bk_call( 'Digitalisimo_Backups', 'restore_managed', 'wp-content/plugins/compartido/compartido.php', $layout ) && bk_call( 'Digitalisimo_Backups', 'restore_managed', 'wp-content/uploads/sites/7/x.jpg', $layout ), 'La importación retiraría algo fuera de los medios del sitio.' );
		si_check( ! bk_call( 'Digitalisimo_Backups', 'restore_walk_into', 'wp-admin', $layout ) && ! bk_call( 'Digitalisimo_Backups', 'restore_walk_into', 'wp-content/plugins', $layout ) && bk_call( 'Digitalisimo_Backups', 'restore_walk_into', 'wp-content/uploads', $layout ), 'El recorrido entraría en el núcleo o en los plugins de la red.' );
		si_check( 'wp-content/uploads/sites/7/2026/11/foto.jpg' === bk_call( 'Digitalisimo_Backups', 'restore_target', 'wordpress/wp-content/uploads/2026/11/foto.jpg', array() ) && null === bk_call( 'Digitalisimo_Backups', 'restore_target', 'wordpress/wp-admin/index.php', array() ), 'restore_target() no sigue las reglas de la importación.' );
	} finally {
		bk_set_static( 'Digitalisimo_Backups', 'import_rules', null );
	}
} );

/* ------------------------------------------------------------------ e · usuarios */

si_case( 'e · usuarios (reemplazo): misma cuenta, por correo, otra persona con su nombre ocupado, nuevos; nada de la red cambia', function() use ( $I ) {
	$db = si_network();
	si_backup_users( $db );
	$before = array( 'admin' => si_user( $db, 'admin' ), 'beto' => si_user( $db, 'beto' ), 'carla' => si_user( $db, 'carla' ), 'ana' => si_user( $db, 'ana' ) );
	list( $journal ) = si_merge( $db, 'replace' );
	si_check( 'done' === $journal['step'], 'La fusión no terminó: ' . $journal['step'] );
	$map = $journal['map'];
	si_check( 2 === (int) $map[1] && 'matched' === $journal['kind'][1], 'ana no es la misma cuenta de la red.' );
	si_check( 3 === (int) $map[2] && 'email' === $journal['kind'][2], 'betito (el correo de beto) no es la cuenta de beto.' );
	si_check( 'renamed' === $journal['kind'][3] && 'carla-2' === $db->value( 'wp_users', 'user_login', 'ID = ' . (int) $map[3] ), 'carla del respaldo (otra persona) no entra con un nombre libre.' );
	si_check( 'created' === $journal['kind'][4] && 'nuevo' === $db->value( 'wp_users', 'user_login', 'ID = ' . (int) $map[4] ), 'nuevo no se creó.' );
	foreach ( $before as $login => $row ) si_check( si_user( $db, $login ) === $row, 'La cuenta ' . $login . ' de la red cambió (contraseña, correo o datos).' );
	si_check( SI_HASH_BK === $db->value( 'wp_users', 'user_pass', 'ID = ' . (int) $map[4] ) && SI_HASH_BK === $db->value( 'wp_users', 'user_pass', 'ID = ' . (int) $map[3] ), 'Los creados no entran con su contraseña del respaldo.' );
	si_check( '' === $db->value( 'wp_users', 'user_activation_key', 'ID = ' . (int) $map[4] ), 'La clave de activación del origen viajó.' );
	si_check( 'Ana de la red' === si_meta( $db, 2, 'first_name' ) && 'sesiones-de-la-red' === si_meta( $db, 2, 'session_tokens' ) && 'Beto de la red' === si_meta( $db, 3, 'first_name' ), 'Una meta global de un usuario de la red cambió.' );
	$caps = function( $id ) use ( $db ) { return (array) $GLOBALS['si_I']::unserialize( (string) si_meta( $db, $id, 'wp_2_capabilities' ) ); };
	$GLOBALS['si_I'] = $I;
	si_check( ! empty( $caps( 2 )['administrator'] ) && ! empty( $caps( 2 )['editor'] ) && '10' === si_meta( $db, 2, 'wp_2_user_level' ), 'ana era administradora del sitio que se reemplaza y ya no lo es.' );
	si_check( ! empty( $caps( 5 )['administrator'] ), 'dueno (administrador del sitio, no viene en el respaldo) perdió el sitio.' );
	si_check( null === si_meta( $db, 6, 'wp_2_capabilities' ) && null === si_meta( $db, 6, 'wp_2_user_level' ), 'viejo (editor del sitio, no viene en el respaldo) sigue siendo miembro.' );
	si_check( ! empty( $caps( 3 )['author'] ) && ! empty( $caps( (int) $map[4] )['administrator'] ) && ! empty( $caps( (int) $map[3] )['contributor'] ), 'Los roles del respaldo no llegan al sitio.' );
	si_check( null === si_meta( $db, 1, 'wp_2_capabilities' ) && serialize( array( 'administrator' => true ) ) === si_meta( $db, 1, 'wp_capabilities' ), 'El superadministrador cambió.' );
	si_check( 'nuevo' === si_meta( $db, (int) $map[4], 'nickname' ) && '2' === si_meta( $db, (int) $map[4], 'primary_blog' ) && 'red.test' === si_meta( $db, (int) $map[4], 'source_domain' ) && 'mfold=o' === si_meta( $db, (int) $map[4], 'wp_2_user-settings' ), 'Las metas de un creado: ' . json_encode( $db->rows( 'wp_usermeta', 'user_id = ' . (int) $map[4] ) ) );
	si_check( null === si_meta( $db, (int) $map[4], 'session_tokens' ), 'Las sesiones del origen viajaron.' );
	// Repetible: otra pasada con el diario terminado no crea a nadie dos veces.
	$users = count( $db->rows( 'wp_users' ) );
	si_merge( $db, 'replace', $journal );
	si_check( $users === count( $db->rows( 'wp_users' ) ), 'Repetir la fusión duplica usuarios.' );
	// Deshacer lo añadido (la vuelta de un reemplazo): los creados salen con sus metas; nadie de la red.
	$undo = $I::undo_additions( $journal['created'], array(), ABSPATH );
	si_check( 3 === $undo['users'] && null === si_user( $db, 'nuevo' ) && null === si_user( $db, 'carla-2' ) && null === si_user( $db, 'autora' ) && null !== si_user( $db, 'carla' ) && null !== si_user( $db, 'ana' ) && ! $db->rows( 'wp_usermeta', 'user_id = ' . (int) $map[4] ), 'Deshacer no quita (sólo) a los creados: ' . json_encode( $undo ) );
} );

si_case( 'e · usuarios: una muerte entre el INSERT y su anotación no crea dos veces; sitio nuevo; vuelta exacta a la copia', function() use ( $I ) {
	$db = si_network();
	si_backup_users( $db );
	// La pasada anterior anotó «nuevo» como pendiente (con la marca de esta importación), lo insertó y murió.
	$mark = 'dgbk-' . str_repeat( '0f', 16 );
	$db->seed( 'wp_users', array( array( 'ID' => 50, 'user_login' => 'nuevo', 'user_pass' => SI_HASH_BK, 'user_email' => 'nuevo@ejemplo.test', 'user_activation_key' => $mark ) ) );
	list( $journal ) = si_merge( $db, 'new', array( 'step' => 'users', 'cursor' => 3, 'pending' => array( 4 => 'nuevo' ), 'tokens' => array( 4 => $mark ), 'map' => array( 1 => 2, 2 => 3, 3 => 7 ), 'kind' => array( 1 => 'matched', 2 => 'email', 3 => 'renamed' ), 'created' => array( 7 ) ) );
	si_check( 50 === (int) $journal['map'][4] && in_array( 50, $journal['created'], true ) && 1 === count( $db->rows( 'wp_users', "user_login LIKE 'nuevo%'" ) ), 'Tras la muerte se creó otra vez a «nuevo»: ' . json_encode( $journal['map'] ) );
	si_check( '' === $db->value( 'wp_users', 'user_activation_key', 'ID = 50' ) && array() === $journal['tokens'], 'La marca del pendiente sigue en la fila o en el diario.' );
	// Sitio nuevo: los miembros de la red que no vienen en el respaldo no cambian (no eran del sitio).
	si_check( null !== si_meta( $db, 6, 'wp_2_capabilities' ), 'En un sitio nuevo se quitó a quien no viene en el respaldo.' );
	// La vuelta a la copia de un sitio: exacta (sólo quienes existen, sin crear a nadie ni conservar administradores).
	$db = si_network();
	$db->pdo->exec( 'DROP TABLE IF EXISTS wp_dgbkr_aaaaaa_090' );
	$db->pdo->exec( 'DROP TABLE IF EXISTS wp_dgbkr_aaaaaa_091' );
	si_backup_users( $db );
	$db->pdo->exec( "DELETE FROM wp_dgbkr_aaaaaa_090 WHERE ID <> 1" );
	$db->pdo->exec( "UPDATE wp_dgbkr_aaaaaa_090 SET user_email = 'ana@ejemplo.test', ID = 2 WHERE ID = 1" );
	$db->pdo->exec( "UPDATE wp_dgbkr_aaaaaa_091 SET user_id = 2 WHERE user_id = 1" );
	$db->seed( 'wp_dgbkr_aaaaaa_090', array( array( 'ID' => 9, 'user_login' => 'ya-no-existe', 'user_pass' => 'x', 'user_email' => 'nadie@ejemplo.test' ) ) );
	list( $journal ) = si_merge( $db, 'replace', array(), true );
	si_check( 'missing' === $journal['kind'][9] && ! $journal['created'] && null === si_user( $db, 'ya-no-existe' ), 'La vuelta a la copia creó un usuario.' );
	$GLOBALS['si_I'] = $I;
	$ana = (array) $I::unserialize( (string) si_meta( $db, 2, 'wp_2_capabilities' ) );
	si_check( array( 'editor' => true ) === $ana && null === si_meta( $db, 5, 'wp_2_capabilities' ) && null === si_meta( $db, 6, 'wp_2_capabilities' ), 'La vuelta no deja las metas del sitio exactamente como en la copia: ' . json_encode( $ana ) );
} );

/* ------------------------------------------------------------------ f · autoría */

si_case( 'f · la autoría pasa a los ID de la red (los huérfanos a 0) y repetirlo no la cambia otra vez', function() use ( $I ) {
	$db = si_network();
	$db->pdo->exec( 'CREATE TABLE t_posts (`ID` INTEGER PRIMARY KEY, `post_author` bigint NOT NULL DEFAULT 0, `post_title` text)' );
	$db->pdo->exec( 'CREATE TABLE t_comments (`comment_ID` INTEGER PRIMARY KEY, `user_id` bigint NOT NULL DEFAULT 0)' );
	$db->pdo->exec( 'CREATE TABLE t_postmeta (`meta_id` INTEGER PRIMARY KEY, `post_id` bigint, `meta_key` varchar(255), `meta_value` longtext)' );
	$db->pdo->exec( 'CREATE TABLE t_links (`link_id` INTEGER PRIMARY KEY, `link_owner` bigint NOT NULL DEFAULT 1)' );
	$db->pdo->exec( 'CREATE TABLE t_orders (`id` INTEGER PRIMARY KEY, `customer_id` bigint)' );
	// El respaldo: 1 → 2, 2 → 1 (un intercambio: nada se reasigna dos veces), 3 → 9, 4 → 3; 77 no existe en el respaldo.
	$map = array( 1 => 2, 2 => 1, 3 => 9, 4 => 3 );
	$db->seed( 't_posts', array( array( 'ID' => 1, 'post_author' => 1 ), array( 'ID' => 2, 'post_author' => 2 ), array( 'ID' => 3, 'post_author' => 3 ), array( 'ID' => 4, 'post_author' => 77 ), array( 'ID' => 5, 'post_author' => 0 ) ) );
	$db->seed( 't_comments', array( array( 'comment_ID' => 1, 'user_id' => 4 ), array( 'comment_ID' => 2, 'user_id' => 0 ) ) );
	$db->seed( 't_postmeta', array( array( 'meta_id' => 1, 'post_id' => 1, 'meta_key' => '_edit_last', 'meta_value' => '1' ), array( 'meta_id' => 2, 'post_id' => 1, 'meta_key' => '_edit_lock', 'meta_value' => '1700000000:1' ), array( 'meta_id' => 3, 'post_id' => 9, 'meta_key' => '_customer_user', 'meta_value' => '2' ), array( 'meta_id' => 4, 'post_id' => 9, 'meta_key' => '_customer_user', 'meta_value' => '0' ), array( 'meta_id' => 5, 'post_id' => 1, 'meta_key' => 'precio', 'meta_value' => '1' ) ) );
	$db->seed( 't_links', array( array( 'link_id' => 1, 'link_owner' => 3 ) ) );
	$db->seed( 't_orders', array( array( 'id' => 1, 'customer_id' => 4 ), array( 'id' => 2, 'customer_id' => null ) ) );
	$temp = array( 'wp_7_posts' => 't_posts', 'wp_7_comments' => 't_comments', 'wp_7_postmeta' => 't_postmeta', 'wp_7_links' => 't_links', 'wp_7_wc_orders' => 't_orders' );
	$journal = array();
	$save = function( array $j ) use ( &$journal ) { $journal = $j; };
	$out = $I::remap( $map, $temp, 'wp_7_', array(), $save );
	$authors = array_column( $db->rows( 't_posts' ), 'post_author', 'ID' );
	si_check( array( 1 => 2, 2 => 1, 3 => 9, 4 => 0, 5 => 0 ) === array_map( 'intval', $authors ), 'Las entradas no tienen los autores de la red: ' . json_encode( $authors ) );
	si_check( 3 === (int) $db->value( 't_comments', 'user_id', 'comment_ID = 1' ) && 0 === (int) $db->value( 't_comments', 'user_id', 'comment_ID = 2' ) && 9 === (int) $db->value( 't_links', 'link_owner', 'link_id = 1' ) && 3 === (int) $db->value( 't_orders', 'customer_id', 'id = 1' ) && null === $db->value( 't_orders', 'customer_id', 'id = 2' ), 'Comentarios, enlaces o pedidos sin reasignar.' );
	si_check( '2' === $db->value( 't_postmeta', 'meta_value', 'meta_id = 1' ) && '1' === $db->value( 't_postmeta', 'meta_value', 'meta_id = 3' ) && '0' === $db->value( 't_postmeta', 'meta_value', 'meta_id = 4' ) && '1' === $db->value( 't_postmeta', 'meta_value', 'meta_id = 5' ) && ! $db->rows( 't_postmeta', "meta_key = '_edit_lock'" ), 'Las metas con un ID de usuario: ' . json_encode( $db->rows( 't_postmeta' ) ) );
	si_check( 1 === (int) $out['report']['posts.post_author']['orphans'], 'El informe no cuenta los huérfanos.' );
	// Una muerte a mitad: con el diario, lo ya reasignado no se reasigna otra vez.
	$again = $I::remap( $map, $temp, 'wp_7_', $out, $save );
	si_check( array_map( 'intval', $authors ) === array_map( 'intval', array_column( $db->rows( 't_posts' ), 'post_author', 'ID' ) ), 'Repetir la reasignación la cambió otra vez.' );
	$db->pdo->exec( 'UPDATE t_posts SET post_author = 1 WHERE ID = 1' );
	$partial = $out;
	unset( $partial['done']['posts.post_author'] );
	$partial['offsets']['posts.post_author'] = 1000;
	$db->pdo->exec( 'UPDATE t_posts SET post_author = post_author + 1000 WHERE ID = 2' ); // ya reasignada en la pasada que murió
	$I::remap( $map, $temp, 'wp_7_', $partial, $save );
	si_check( 2 === (int) $db->value( 't_posts', 'post_author', 'ID = 1' ) && 1 === (int) $db->value( 't_posts', 'post_author', 'ID = 2' ), 'Tras una muerte a mitad, una fila se reasignó dos veces: ' . json_encode( $db->rows( 't_posts' ) ) );
} );

/* ------------------------------------------------------------------ g · opciones del sitio */

si_case( 'g · opciones del sitio: su carpeta de medios, sin la configuración ni los eventos de Backups del respaldo, su tema habilitado', function() use ( $I ) {
	$db = si_network();
	$db->pdo->exec( "CREATE TABLE t_options (`option_id` INTEGER PRIMARY KEY AUTOINCREMENT, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$cron = array( 1700000000 => array( 'digitalisimo_backups_job_tick' => array( 'x' => array() ), 'wp_version_check' => array( 'y' => array() ) ), 1700000100 => array( 'digitalisimo_backups_watchdog' => array( 'z' => array() ) ) );
	$db->seed( 't_options', array(
		array( 'option_name' => 'upload_path', 'option_value' => '/srv/tienda/wp-content/uploads' ),
		array( 'option_name' => 'upload_url_path', 'option_value' => 'https://cdn.tienda.com' ),
		array( 'option_name' => 'digitalisimo_backups_settings', 'option_value' => 'a:0:{}' ),
		array( 'option_name' => 'digitalisimo_backups_history', 'option_value' => 'a:0:{}' ),
		array( 'option_name' => 'digitalisimo_backups_automations', 'option_value' => 'a:0:{}' ),
		array( 'option_name' => 'cron', 'option_value' => serialize( $cron ) ),
		array( 'option_name' => 'stylesheet', 'option_value' => 'tema-hijo' ),
		array( 'option_name' => 'template', 'option_value' => 'tema-tienda' ),
		array( 'option_name' => 'blogname', 'option_value' => 'Tienda' ),
	) );
	$report = $I::prepare_options( 't_options', 'wp_2_options', $I::themes_of( 't_options' ) );
	si_check( '' === $db->value( 't_options', 'option_value', "option_name = 'upload_path'" ) && null === $db->value( 't_options', 'option_value', "option_name = 'upload_url_path'" ), 'La carpeta de medios del respaldo quedó en el sitio.' );
	si_check( 3 === $report['backups_options'] && ! $db->rows( 't_options', "option_name LIKE 'digitalisimo_backups_%'" ), 'La configuración de Backups del respaldo quedó en el sitio.' );
	$left = $I::unserialize( (string) $db->value( 't_options', 'option_value', "option_name = 'cron'" ) );
	si_check( 2 === $report['cron'] && array( 1700000000 => array( 'wp_version_check' => array( 'y' => array() ) ) ) === $left, 'Los eventos de Backups del respaldo siguen en el cron del sitio: ' . json_encode( $left ) );
	$allowed = $I::unserialize( (string) $db->value( 't_options', 'option_value', "option_name = 'allowedthemes'" ) );
	si_check( array( 'tema-hijo' => true, 'tema-tienda' => true ) === $allowed && array( 'tema-hijo', 'tema-tienda' ) === $report['themes'], 'El tema del respaldo no queda habilitado para el sitio: ' . json_encode( $allowed ) );
	si_check( 'Tienda' === $db->value( 't_options', 'option_value', "option_name = 'blogname'" ), 'Se tocó una opción del sitio que no tocaba.' );
	si_check( array( 'twentytwentyfive' => true ) === $GLOBALS['bk_site_options']['allowedthemes'], 'Se habilitó un tema en toda la red.' );
} );

/* ------------------------------------------------------------------ h · mantenimiento */

si_case( 'h · mantenimiento sólo del sitio que cambia: la red, su administración, WP-Cron y WP-CLI siguen', function() {
	Digitalisimo_Backups_Restore_Maintenance::hold( str_repeat( 'a', 64 ), true, 'job', array(), array( 'domain' => 'red.test', 'path' => '/tienda/' ) );
	$path = Digitalisimo_Backups_Restore_Maintenance::path();
	try {
		si_check( Digitalisimo_Backups_Restore_Maintenance::site_only() && Digitalisimo_Backups_Restore_Maintenance::until_done(), 'El mantenimiento no dice que es de un sitio.' );
		$upgrading_for = function( $host, $uri, $script = 'index.php' ) use ( $path ) {
			$_SERVER['HTTP_HOST'] = $host;
			$_SERVER['REQUEST_URI'] = $uri;
			$_SERVER['SCRIPT_FILENAME'] = ABSPATH . $script;
			$_COOKIE = array();
			$upgrading = null;
			include $path;
			return $upgrading;
		};
		si_check( 0 !== $upgrading_for( 'red.test', '/tienda/' ) && 0 !== $upgrading_for( 'red.test', '/tienda/wp-admin/', 'wp-admin/index.php' ) && 0 !== $upgrading_for( 'red.test', '/tienda' ), 'El sitio que cambia no está en mantenimiento.' );
		si_check( 0 === $upgrading_for( 'red.test', '/' ) && 0 === $upgrading_for( 'red.test', '/tiendas/' ) && 0 === $upgrading_for( 'red.test', '/wp-admin/network/' ) && 0 === $upgrading_for( 'otro.test', '/tienda/' ), 'El resto de la red quedó en mantenimiento.' );
		si_check( 0 === $upgrading_for( 'red.test', '/wp-cron.php', 'wp-cron.php' ) && ! defined( 'DIGITALISIMO_BACKUPS_RESTORE_MINIMAL' ), 'WP-Cron de la red carga WordPress mínimo.' );
		if ( ! function_exists( '_get_cron_array' ) ) { function _get_cron_array() { return array( 1 => array( 'otro_evento' => array() ) ); } }
		si_check( null === Digitalisimo_Backups_Jobs::restore_cron_jobs( null ), 'WP-Cron de la red quedó limitado a la importación.' );
		Digitalisimo_Backups_Restore_Maintenance::hold( str_repeat( 'a', 64 ), true, 'job', array() );
		si_check( ! Digitalisimo_Backups_Restore_Maintenance::site_only(), 'Una restauración de siempre dice ser de un sitio.' );
	} finally {
		Digitalisimo_Backups_Restore_Maintenance::release();
		unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
	}
} );

/* ------------------------------------------------------------------ i · REST, WP-CLI y la copia de un sitio */

si_case( 'i · REST y WP-CLI piden into y address; la copia del sitio lleva sus tablas y sus miembros con sólo sus metas', function() use ( $I ) {
	$rest = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-rest.php' );
	$cli = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-cli.php' );
	si_check( false !== strpos( $rest, "'into' => (string) \$request['into'], 'address' => (string) \$request['address']" ) && 2 <= substr_count( $rest, "+ \$import +" ) + substr_count( $rest, "\$import + \$parts" ), 'REST no pasa into y address a restore y verify.' );
	si_check( false !== strpos( $cli, '[--into=<into>]' ) && false !== strpos( $cli, "'into' => (string) ( \$assoc_args['into'] ?? '' )" ), 'WP-CLI no tiene --into ni --address.' );
	$db = si_network();
	$db->pdo->exec( "CREATE TABLE `wp_2_posts` (`ID` INTEGER PRIMARY KEY, `post_author` bigint)" );
	$export = new Digitalisimo_Backups_Site_Copy_Export( 2 );
	$export->prepare();
	si_check( array( 'wp_2_options', 'wp_2_posts', 'wp_users', 'wp_usermeta' ) === $export->tables(), 'Las tablas de la copia: ' . json_encode( $export->tables() ) );
	si_check( 'wp_2_users' === $export->name( 'wp_users' ) && 'wp_2_usermeta' === $export->name( 'wp_usermeta' ) && 'wp_2_posts' === $export->name( 'wp_2_posts' ), 'Los nombres de la copia.' );
	si_check( array( 2, 5, 6 ) === $export->members() && 'ID IN (2,5,6)' === $export->where( 'wp_users' ) && false !== strpos( (string) $export->where( 'wp_usermeta' ), "wp\\_2\\_%" ), 'La copia no lleva sólo a los miembros con sus metas del sitio: ' . json_encode( array( $export->members(), $export->where( 'wp_usermeta' ) ) ) );
	si_check( 0 === strpos( $export->create( 'wp_users', 'CREATE TABLE `wp_users` (x)' ), 'CREATE TABLE `wp_2_users`' ), 'La copia crea la tabla de usuarios con el nombre de la red.' );
} );

/* ------------------------------------------------------------------ j · falla tras fundir los usuarios */

si_case( 'j · falla inyectada justo después de fundir los usuarios: deshacer deja los usuarios de la red como estaban', function() use ( $I ) {
	$db = si_network();
	si_backup_users( $db );
	$users = si_print( $db, 'wp_users', 'ID' );
	$meta = hash( 'sha256', json_encode( $db->pdo->query( 'SELECT user_id, meta_key, meta_value FROM wp_usermeta ORDER BY user_id, meta_key' )->fetchAll( PDO::FETCH_ASSOC ) ) );
	$site_meta = $db->pdo->query( "SELECT user_id, meta_key, meta_value FROM wp_usermeta WHERE meta_key LIKE 'wp\\_2\\_%' ESCAPE '\\' ORDER BY user_id, meta_key" )->fetchAll( PDO::FETCH_ASSOC );
	// La fusión termina y algo falla antes del cambio de tablas (el gancho que usan las pruebas lanza).
	$GLOBALS['si_actions']['digitalisimo_backups_site_import_merged'][] = function() { throw new Exception( 'falla inyectada' ); };
	list( $journal ) = si_merge( $db, 'replace' );
	try { do_action( 'digitalisimo_backups_site_import_merged', 'job', $journal ); $failed = false; } catch ( Exception $e ) { $failed = 'falla inyectada' === $e->getMessage(); }
	unset( $GLOBALS['si_actions'] );
	si_check( $failed && 'done' === $journal['step'] && $users !== si_print( $db, 'wp_users', 'ID' ), 'La prueba no fundió antes de fallar.' );
	// La vuelta: la copia del sitio (sus miembros con sus metas del sitio) vuelve exacta y se quitan los creados.
	$db->pdo->exec( 'DELETE FROM wp_dgbkr_aaaaaa_090' );
	$db->pdo->exec( 'DELETE FROM wp_dgbkr_aaaaaa_091' );
	foreach ( array( 2, 5, 6 ) as $id ) {
		$row = $db->rows( 'wp_users', 'ID = ' . $id )[0];
		$db->seed( 'wp_dgbkr_aaaaaa_090', array( array_intersect_key( $row, array_flip( array( 'ID', 'user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'user_status', 'display_name' ) ) ) ) );
	}
	foreach ( $site_meta as $row ) $db->seed( 'wp_dgbkr_aaaaaa_091', array( $row ) );
	si_merge( $db, 'replace', array(), true );
	$I::undo_additions( $journal['created'], array(), ABSPATH );
	si_check( $users === si_print( $db, 'wp_users', 'ID' ), 'La tabla de usuarios de la red no quedó como estaba.' );
	si_check( $meta === hash( 'sha256', json_encode( $db->pdo->query( 'SELECT user_id, meta_key, meta_value FROM wp_usermeta ORDER BY user_id, meta_key' )->fetchAll( PDO::FETCH_ASSOC ) ) ), 'Las metas de los usuarios de la red no quedaron como estaban: ' . json_encode( $db->rows( 'wp_usermeta', 'user_id IN (2,5,6)' ) ) );
	// Y el motor lo ve como un cambio: una falla a esta altura vuelve a la copia previa (no se da por «antes del cambio»).
	$id = Digitalisimo_Backups_Job_Store::new_id();
	@mkdir( Digitalisimo_Backups_Job_Store::staging( $id ), 0700, true );
	file_put_contents( Digitalisimo_Backups_Job_Store::staging( $id ) . '/users.json', json_encode( $journal ) );
	$job = array( 'id' => $id, 'phase' => 'switch', 'checkpoint' => array(), 'spec' => array( 'into' => array( 'mode' => 'replace', 'site_id' => 2 ) ) );
	si_check( true === Digitalisimo_Backups::restore_changed( $job ), 'Con los usuarios ya fundidos, el motor cree que no cambió nada.' );
} );

/* ------------------------------------------------------------------ k · por partes, migración e incrementales */

si_case( 'k · por partes (sólo base, medios, plugins y temas), la migración con clave de individual a red y el punto incremental', function() use ( $I ) {
	si_network();
	$path = $GLOBALS['bk_root'] . '-partes.zip';
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( array( 'database.sql' => 'SELECT 1;', 'manifest.json' => json_encode( array( 'format' => 'digitalisimo-backup/v3', 'scope' => 'installation', 'type' => 'full', 'multisite' => false, 'home_url' => 'https://tienda.com', 'table_prefix' => 'wp_' ) ), 'wordpress/wp-admin/index.php' => '<?php', 'wordpress/wp-content/uploads/a.jpg' => 'jpg', 'wordpress/wp-content/plugins/x/x.php' => '<?php', 'wordpress/wp-content/themes/t/style.css' => '/* */', 'wordpress/wp-content/mu-plugins/m.php' => '<?php' ) as $name => $body ) $zip->addFromString( $name, $body );
	$zip->close();
	$zip->open( $path );
	$new = array( 'mode' => 'new' );
	$replace = array( 'mode' => 'replace', 'site_id' => 2 );
	$parts = function( array $list ) { return Digitalisimo_Backups_Restore_Parts::normalize( array( 'parts' => $list ) ); };
	try {
		$ok = function( $selection, $into ) use ( $zip ) { try { bk_call( 'Digitalisimo_Backups', 'restore_precheck', $zip, $selection, $into ); return ''; } catch ( Exception $e ) { return $e->getMessage(); } };
		si_check( '' === $ok( null, $new ) && '' === $ok( $parts( array( 'database', 'uploads' ) ), $new ) && '' === $ok( $parts( array( 'uploads', 'plugins' ) ), $replace ), 'Una selección válida de la importación se rechazó.' );
		$core = $ok( $parts( array( 'core' ) ), $replace );
		$mu = $ok( $parts( array( 'database', 'mu-plugins' ) ), $new );
		si_check( false !== strpos( $core, 'nunca se mezclan en la red' ) && false !== strpos( $mu, 'nunca se mezclan en la red' ), 'Se aceptó mezclar en la red el núcleo o los mu-plugins del respaldo: ' . json_encode( array( $core, $mu ) ) );
		si_check( false !== strpos( $ok( $parts( array( 'uploads' ) ), $new ), 'base de datos' ), 'Un sitio nuevo se aceptó sin su base de datos.' );
		si_check( false !== strpos( $ok( null, null ), 'into=new' ), 'Sin destino, la restauración de un individual en una red no pide elegirlo.' );
	} finally {
		$zip->close();
		@unlink( $path );
	}
	// La migración con clave: de un individual a una red ya no se rechaza (se importa); sin pedirlo, se dice qué elegir.
	$hello = array( 'version' => 1, 'instance' => '0123456789abcdef', 'ready' => true, 'multisite' => false, 'home' => 'https://tienda.com' );
	si_check( true === Digitalisimo_Backups_Migrate::compatible( $hello ), 'La migración con clave de un individual a una red se rechaza.' );
	$refused = '';
	try { Digitalisimo_Backups_Migrate::compatible( $hello, false ); } catch ( Exception $e ) { $refused = $e->getMessage(); }
	si_check( false !== strpos( $refused, 'sitio nuevo' ), 'Sin importar, la migración no dice qué elegir: ' . $refused );
	$migrate = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups-migrate.php' );
	si_check( false !== strpos( $migrate, "\$spec['into'] = \$into;" ) && false !== strpos( (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups.php' ), "'into' => \$into," ), 'La migración no lleva el destino a su restauración.' );
	// El punto incremental de un individual: su destino viaja a la restauración que encola la reconstrucción.
	$service = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/trait-backups-service.php' );
	$core = (string) file_get_contents( __DIR__ . '/../digitalisimo-backups/includes/class-backups.php' );
	si_check( false !== strpos( $service, "'into' => is_array( \$args['into_spec'] ?? null )" ) && false !== strpos( $core, "'into' => is_array( \$restore['into'] ?? null ) ? \$restore['into'] : null" ), 'El punto incremental no lleva su destino a la restauración.' );
	$into = bk_call( 'Digitalisimo_Backups', 'service_import_into', array( 'into' => 'new', 'address' => 'puntos' ), array( 'scope' => 'installation', 'multisite' => false, 'home_url' => 'https://tienda.com' ) );
	si_check( is_array( $into ) && '/puntos/' === $into['path'], 'El destino de un punto de un individual no se calcula: ' . json_encode( $into ) );
	si_check( null === bk_call( 'Digitalisimo_Backups', 'service_import_into', array(), array( 'scope' => 'installation', 'multisite' => true ) ), 'El respaldo de una red se trató como importación.' );
} );

bk_cleanup();
@unlink( $GLOBALS['bk_root'] . '-red.sqlite' );
@unlink( $GLOBALS['bk_root'] . '-sitio.zip' );
echo $failures ? 'Importar un sitio en una red: ' . $failures . " fallos.\n" : "Importar un sitio en una red: todo correcto.\n";
exit( $failures ? 1 : 0 );

function bk_write_file( $path, $body ) { if ( ! is_dir( dirname( $path ) ) ) mkdir( dirname( $path ), 0700, true ); file_put_contents( $path, $body ); }
function bk_set_static( $class, $name, $value ) {
	$property = new ReflectionProperty( $class, $name );
	if ( PHP_VERSION_ID < 80100 ) $property->setAccessible( true );
	$property->setValue( null, $value );
}
