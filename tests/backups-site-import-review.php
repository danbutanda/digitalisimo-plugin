<?php
/**
 * Revisión adversarial de Backups 1.0.79: arreglos de la importación de un sitio en una red (hallazgos A1, A2, M3, M4,
 * M6, B1, B3 y B5 de la revisión de la rama de publicación). Base de datos SQLite de verdad (tests/lib/backups-sqlite.php)
 * con una red por subdirectorios: el principal (1, su superadministrador «admin») y el sitio 2 «tienda» (su administradora
 * «ana»).
 *
 *   A1 · ni el plan de E1 ni la fusión dan a un usuario del respaldo (creado o fundido) una meta con el prefijo de la red
 *        que no sea la del sitio de destino (`wp_capabilities`, `wp_user_level`, `wp_2_capabilities`…): ningún privilegio
 *        en otro sitio de la red;
 *   A2 · las capacidades de red (`manage_network*`, `manage_sites`, `create_sites`, `delete_sites`, `upgrade_network`,
 *        `setup_network`) salen de `<base>N_user_roles` y de cada `<base>N_capabilities`, y el informe lo dice;
 *   M3 · restaurar desde la lista la copia de un sitio (`network-site`, sin `rollback`) nunca recrea a quien ya no está en
 *        la red; el informe dice quiénes;
 *   M4 · esa copia (también la de otra red) reasigna la autoría con el mapa de la fusión; sólo la vuelta atrás la deja;
 *   M6 · «Migrar» de la red pinta «sitio nuevo / reemplazando» dentro de su formulario, y llega a from_request();
 *   B1 · la recuperación de un usuario «pendiente» sólo adopta la fila con la marca de esta importación;
 *   B3 · la respuesta dice que los superadministradores y los administradores del sitio reemplazado se conservan siempre y
 *        qué se hizo con keep_access / keep_admins;
 *   B5 · la migración con clave que importa en una red marca su restauración como `aside` (y lleva lo que pidió).
 * Los casos A1 y M3 son los 1 y 2 de la revisión (rev-pruebas.php). Con el código de aebdc90f cada caso escribe «FALLA ·».
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
/** Con el prefijo de cada sitio de la red (como wpdb en una red). */
class Sr_DB extends Bk_Sqlite_DB {
	public function get_blog_prefix( $blog_id = null ) { $blog_id = null === $blog_id ? 1 : (int) $blog_id; return 1 === $blog_id ? $this->base_prefix : $this->base_prefix . $blog_id . '_'; }
}
$GLOBALS['bk_multisite'] = true;
if ( ! function_exists( 'is_subdomain_install' ) ) { function is_subdomain_install() { return false; } }
if ( ! function_exists( 'get_main_network_id' ) ) { function get_main_network_id() { return 1; } }
if ( ! function_exists( 'get_network' ) ) { function get_network() { return (object) array( 'id' => 1, 'domain' => 'red.test', 'path' => '/' ); } }
if ( ! function_exists( 'switch_to_blog' ) ) { function switch_to_blog( $id ) { return true; } }
if ( ! function_exists( 'restore_current_blog' ) ) { function restore_current_blog() { return true; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
$GLOBALS['bk_filters']['digitalisimo_backups_site_import_uploads'] = function( $uploads, $id ) { return array( 'basedir' => WP_CONTENT_DIR . '/uploads/sites/' . (int) $id, 'baseurl' => 'http://red.test/x/wp-content/uploads/sites/' . (int) $id ); };

$I = 'Digitalisimo_Backups_Site_Import';
$failures = 0;
function sr_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function sr_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
function sr_caps( $caps ) { return serialize( is_array( $caps ) ? $caps : array( $caps => true ) ); }
function sr_meta( $db, $id, $key ) { $rows = $db->rows( 'wp_usermeta', 'user_id = ' . (int) $id . " AND meta_key = '" . $key . "'" ); return $rows ? $rows[0]['meta_value'] : null; }
function sr_user( $db, $login ) { $rows = $db->rows( 'wp_users', "user_login = '" . $login . "'" ); return $rows ? $rows[0] : null; }
const SR_NETWORK_CAPS = array( 'manage_network', 'manage_sites', 'manage_network_users', 'manage_network_plugins', 'manage_network_themes', 'manage_network_options', 'upgrade_network', 'setup_network', 'create_sites', 'delete_sites' );

/** La red: principal (1) con su superadministrador «admin», el sitio 2 «tienda» con su administradora «ana» y «luis», autor del sitio 2. */
function sr_network( $name = 'red' ) {
	$path = $GLOBALS['bk_root'] . '-' . $name . '.sqlite';
	@unlink( $path );
	$db = new Sr_DB( $path, 'wp_' );
	$db->pdo->exec( "CREATE TABLE `wp_blogs` (`blog_id` INTEGER PRIMARY KEY, `site_id` bigint NOT NULL DEFAULT 1, `domain` varchar(200) NOT NULL DEFAULT '', `path` varchar(100) NOT NULL DEFAULT '', `deleted` tinyint NOT NULL DEFAULT 0)" );
	$db->seed( 'wp_blogs', array( array( 'blog_id' => 1, 'domain' => 'red.test', 'path' => '/' ), array( 'blog_id' => 2, 'domain' => 'red.test', 'path' => '/tienda/' ) ) );
	$db->pdo->exec( "CREATE TABLE `wp_users` (`ID` INTEGER PRIMARY KEY AUTOINCREMENT, `user_login` varchar(60) NOT NULL DEFAULT '', `user_pass` varchar(255) NOT NULL DEFAULT '', `user_nicename` varchar(50) NOT NULL DEFAULT '', `user_email` varchar(100) NOT NULL DEFAULT '', `user_url` varchar(100) NOT NULL DEFAULT '', `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `user_activation_key` varchar(255) NOT NULL DEFAULT '', `user_status` int NOT NULL DEFAULT 0, `display_name` varchar(250) NOT NULL DEFAULT '', `spam` tinyint NOT NULL DEFAULT 0, `deleted` tinyint NOT NULL DEFAULT 0)" );
	$db->pdo->exec( "CREATE TABLE `wp_usermeta` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	foreach ( array( 'wp_', 'wp_2_' ) as $prefix ) $db->pdo->exec( "CREATE TABLE `{$prefix}options` (`option_id` INTEGER PRIMARY KEY AUTOINCREMENT, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$db->seed( 'wp_users', array(
		array( 'ID' => 1, 'user_login' => 'admin', 'user_pass' => '$P$Badmin', 'user_email' => 'admin@red.test' ),
		array( 'ID' => 2, 'user_login' => 'ana', 'user_pass' => '$P$Bana-actual', 'user_email' => 'ana@red.test' ),
		array( 'ID' => 3, 'user_login' => 'luis', 'user_pass' => '$P$Bluis-actual', 'user_email' => 'luis@red.test' ),
	) );
	$db->seed( 'wp_usermeta', array(
		array( 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => sr_caps( 'administrator' ) ),
		array( 'user_id' => 2, 'meta_key' => 'wp_2_capabilities', 'meta_value' => sr_caps( 'administrator' ) ),
		array( 'user_id' => 3, 'meta_key' => 'wp_2_capabilities', 'meta_value' => sr_caps( 'author' ) ),
	) );
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_site_options'] = array( 'site_admins' => array( 'admin' ) );
	return $db;
}
/** Los usuarios del respaldo en sus temporales (con las claves del sitio ya en su prefijo, como las deja el plan de E1). */
function sr_temp_users( $db, array $users, $tag = 'rv' ) {
	$db->pdo->exec( "CREATE TABLE `wp_dgbkr_{$tag}_090` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60), `user_pass` varchar(255), `user_nicename` varchar(50), `user_email` varchar(100), `user_url` varchar(100), `user_registered` datetime, `user_activation_key` varchar(255), `user_status` int, `display_name` varchar(250))" );
	$db->pdo->exec( "CREATE TABLE `wp_dgbkr_{$tag}_091` (`umeta_id` INTEGER PRIMARY KEY AUTOINCREMENT, `user_id` bigint, `meta_key` varchar(255), `meta_value` longtext)" );
	foreach ( $users as $u ) {
		$db->seed( "wp_dgbkr_{$tag}_090", array( array( 'ID' => $u[0], 'user_login' => $u[1], 'user_pass' => $u[2], 'user_nicename' => $u[1], 'user_email' => $u[3], 'user_url' => '', 'user_registered' => '2026-01-01 00:00:00', 'user_activation_key' => '', 'user_status' => 0, 'display_name' => $u[1] ) ) );
		foreach ( $u[4] as $key => $value ) $db->seed( "wp_dgbkr_{$tag}_091", array( array( 'user_id' => $u[0], 'meta_key' => $key, 'meta_value' => $value ) ) );
	}
}
function sr_merge( $site, $mode, array $journal = array(), $rollback = false ) {
	return Digitalisimo_Backups_Site_Import::merge_users( array( 'users' => 'wp_dgbkr_rv_090', 'usermeta' => 'wp_dgbkr_rv_091', 'site_id' => $site, 'domain' => 'red.test', 'mode' => $mode, 'rollback' => $rollback ), $journal, function() {} );
}
/** Un trabajo de importación con su área de trabajo (los JSON que deja la verificación). */
function sr_job( array $spec, array $files ) {
	$id = Digitalisimo_Backups_Job_Store::new_id();
	@mkdir( Digitalisimo_Backups_Job_Store::staging( $id ), 0700, true );
	foreach ( $files as $name => $data ) file_put_contents( Digitalisimo_Backups_Job_Store::staging( $id ) . '/' . $name, json_encode( $data ) );
	return array( 'id' => $id, 'phase' => 'switch', 'checkpoint' => array(), 'spec' => $spec + array( 'file' => 'site-copy-red-test-tienda-20261101-000000-abcdef12.zip' ) );
}
function sr_staged( array $job, $name ) { return json_decode( (string) @file_get_contents( Digitalisimo_Backups_Job_Store::staging( $job['id'] ) . '/' . $name ), true ); }

/* ------------------------------------------------------------------ A1 · metas de otros sitios de la red */

sr_case( 'A1 · el plan de E1 de un individual descarta las claves de permisos de la red que no son del sitio de destino', function() use ( $I ) {
	sr_network();
	$target = array( 'mode' => 'new', 'site_id' => 7, 'domain' => 'red.test', 'path' => '/cliente/', 'url' => 'http://red.test/cliente', 'siteurl' => 'http://red.test/cliente', 'label' => 'red.test/cliente' );
	$verdicts = function( $prefix, array $keys ) use ( $I, $target ) {
		$origin = array( 'home' => 'https://cliente.test', 'siteurl' => 'https://cliente.test', 'abspath' => '/srv/c', 'content_dir' => '/srv/c/wp-content', 'uploads_dir' => '/srv/c/wp-content/uploads', 'prefix' => $prefix, 'multisite' => false );
		$plan = $I::plan( $origin, $I::destination( $target ), array( 'tables' => array( $prefix . 'options', $prefix . 'users', $prefix . 'usermeta' ) ), array() );
		$foreign = new ReflectionMethod( $plan, 'foreign_key' );
		if ( PHP_VERSION_ID < 80100 ) $foreign->setAccessible( true );
		$out = array();
		foreach ( $keys as $key ) $out[ $key ] = 'drop' === $foreign->invoke( $plan, $key, 'usermeta' ) ? 'se descarta' : $plan->rename_key( $key, 'usermeta' );
		return $out;
	};
	// Un individual con prefijo `wpx_` que trae (sembradas por su administrador o de un cambio de prefijo anterior) las claves
	// de permisos del principal y del sitio 2 de la red.
	$keys = $verdicts( 'wpx_', array( 'wpx_capabilities', 'wpx_user-settings', 'wp_capabilities', 'wp_user_level', 'wp_2_capabilities', 'wp_2_user_level', 'wp_7_capabilities', 'nickname' ) );
	$want = array( 'wpx_capabilities' => 'wp_7_capabilities', 'wpx_user-settings' => 'wp_7_user-settings', 'wp_capabilities' => 'se descarta', 'wp_user_level' => 'se descarta', 'wp_2_capabilities' => 'se descarta', 'wp_2_user_level' => 'se descarta', 'wp_7_capabilities' => 'se descarta', 'nickname' => 'nickname' );
	sr_check( $want === $keys, 'E1 deja pasar claves de permisos de otros sitios de la red: ' . json_encode( $keys ) );
	// Con el prefijo de la red en el origen (lo habitual), las suyas pasan al sitio: nada se descarta de más.
	$keys = $verdicts( 'wp_', array( 'wp_capabilities', 'wp_user_level' ) );
	sr_check( array( 'wp_capabilities' => 'wp_7_capabilities', 'wp_user_level' => 'wp_7_user_level' ) === $keys, 'Con el prefijo de la red, las claves del origen ya no pasan al sitio: ' . json_encode( $keys ) );
} );

sr_case( 'A1 · la fusión no escribe a un usuario creado ni a uno fundido ninguna meta de otro sitio de la red', function() {
	$db = sr_network();
	sr_temp_users( $db, array(
		// «mallory», nueva en la red: además de su rol en el sitio 7 trae los del principal y del sitio 2.
		array( 1, 'mallory', '$P$Bmallory', 'mallory@cliente.test', array( 'wp_7_capabilities' => sr_caps( 'subscriber' ), 'wp_7_user_level' => '0', 'wp_capabilities' => sr_caps( 'administrator' ), 'wp_user_level' => '10', 'wp_2_capabilities' => sr_caps( 'administrator' ), 'wp_2_user_level' => '10', 'wp_user-settings' => 'mfold=o', 'wp_7_2_capabilities' => sr_caps( 'administrator' ), 'nickname' => 'mallory' ) ),
		// «ana», la misma cuenta de la red: trae el principal y el sitio 2 con otros roles.
		array( 2, 'ana', '$P$Bana-respaldo', 'ana@red.test', array( 'wp_7_capabilities' => sr_caps( 'editor' ), 'wp_capabilities' => sr_caps( 'administrator' ), 'wp_2_capabilities' => sr_caps( 'subscriber' ) ) ),
	) );
	$journal = sr_merge( 7, 'new' );
	sr_check( 'done' === $journal['step'] && 'created' === $journal['kind'][1] && 'matched' === $journal['kind'][2], 'La fusión no terminó como se esperaba: ' . json_encode( $journal['kind'] ) );
	$id = (int) $journal['map'][1];
	$leaked = array();
	foreach ( array( 'wp_capabilities', 'wp_user_level', 'wp_2_capabilities', 'wp_2_user_level', 'wp_user-settings', 'wp_7_2_capabilities' ) as $key ) if ( null !== sr_meta( $db, $id, $key ) ) $leaked[ $key ] = sr_meta( $db, $id, $key );
	sr_check( ! $leaked, 'merge_users() escribe a mallory (creada, ID ' . $id . ') metas de otros sitios de la red: ' . json_encode( $leaked ) );
	sr_check( sr_caps( 'subscriber' ) === sr_meta( $db, $id, 'wp_7_capabilities' ) && '0' === sr_meta( $db, $id, 'wp_7_user_level' ) && 'mallory' === sr_meta( $db, $id, 'nickname' ) && '7' === sr_meta( $db, $id, 'primary_blog' ), 'A mallory le faltan su rol del sitio o sus metas globales: ' . json_encode( $db->rows( 'wp_usermeta', 'user_id = ' . $id ) ) );
	sr_check( null === sr_meta( $db, 2, 'wp_capabilities' ) && sr_caps( 'administrator' ) === sr_meta( $db, 2, 'wp_2_capabilities' ) && sr_caps( 'editor' ) === sr_meta( $db, 2, 'wp_7_capabilities' ), 'ana (fundida) cambió en otro sitio de la red: ' . json_encode( $db->rows( 'wp_usermeta', 'user_id = 2' ) ) );
} );

/* ------------------------------------------------------------------ A2 · capacidades de red */

sr_case( 'A2 · los roles del sitio y las capacidades de cada usuario se importan sin capacidades de red', function() use ( $I ) {
	$db = sr_network();
	$db->pdo->exec( "CREATE TABLE `t_options` (`option_id` INTEGER PRIMARY KEY AUTOINCREMENT, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$roles = array(
		'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true, 'edit_users' => true, 'manage_network_users' => true, 'manage_network' => true ) ),
		'jefe' => array( 'name' => 'Jefe', 'capabilities' => array_fill_keys( SR_NETWORK_CAPS, true ) + array( 'read' => true, 'manage_network_custom' => true ) ),
		'editor' => array( 'name' => 'Editor', 'capabilities' => array( 'edit_posts' => true, 'edit_others_posts' => true ) ),
	);
	$db->seed( 't_options', array( array( 'option_name' => 'wp_7_user_roles', 'option_value' => serialize( $roles ) ), array( 'option_name' => 'blogname', 'option_value' => 'Cliente' ) ) );
	$report = $I::prepare_options( 't_options', '', array(), 'wp_7_' );
	$after = $I::unserialize( (string) $db->value( 't_options', 'option_value', "option_name = 'wp_7_user_roles'" ) );
	sr_check( is_array( $after ) && array( 'manage_options' => true, 'edit_users' => true ) === $after['administrator']['capabilities'] && array( 'read' => true ) === $after['jefe']['capabilities'] && $roles['editor'] === $after['editor'] && 'Jefe' === $after['jefe']['name'], 'Los roles del sitio conservan capacidades de red: ' . json_encode( $after ) );
	$listed = (array) ( $report['network_caps'] ?? array() );
	sr_check( array( 'administrator', 'jefe' ) === array_keys( $listed ) && in_array( 'manage_network_users', (array) $listed['administrator'], true ) && 11 === count( (array) $listed['jefe'] ), 'El informe de las opciones no dice qué capacidades de red se quitaron: ' . json_encode( $report ) );
	// Cada usuario: la creada y la fundida, con capacidades de red sueltas en su rol del sitio (`manage_network_users`
	// bastaría para cambiar la contraseña de cualquier usuario de la red que no sea superadministrador).
	sr_temp_users( $db, array(
		array( 1, 'mallory', '$P$Bmallory', 'mallory@cliente.test', array( 'wp_7_capabilities' => sr_caps( array( 'administrator' => true, 'manage_network_users' => true, 'manage_sites' => true ) ) ) ),
		array( 2, 'ana', '$P$Bana-respaldo', 'ana@red.test', array( 'wp_7_capabilities' => sr_caps( array( 'editor' => true, 'upgrade_network' => true, 'manage_network_whatever' => true ) ) ) ),
	) );
	$journal = sr_merge( 7, 'new' );
	$id = (int) $journal['map'][1];
	sr_check( sr_caps( 'administrator' ) === sr_meta( $db, $id, 'wp_7_capabilities' ) && sr_caps( 'editor' ) === sr_meta( $db, 2, 'wp_7_capabilities' ), 'Las capacidades del sitio de cada usuario conservan capacidades de red: ' . json_encode( array( sr_meta( $db, $id, 'wp_7_capabilities' ), sr_meta( $db, 2, 'wp_7_capabilities' ) ) ) );
	$noted = (array) ( $journal['network_caps'] ?? array() );
	sr_check( array( 'mallory', 'ana' ) === array_keys( $noted ) && array( 'manage_network_users', 'manage_sites' ) === $noted['mallory'], 'El diario de la fusión no dice a quién se le quitaron capacidades de red: ' . json_encode( $noted ) );
} );

/* ------------------------------------------------------------------ M3, M4, A2 y B3 · la copia de un sitio por el motor */

/**
 * La copia del sitio 2 de hace un mes (alcance `network-site`) restaurada desde la lista sobre el sitio 2, por el paso del
 * cambio del motor (import_swap()): en la copia, «ana» (2), «pedro» (5, editor; después lo borraron de la red) y «luis»,
 * que en la copia tiene el ID 4 (en la red, el 3: así llega una copia de otra red). Las entradas: una de cada uno.
 */
function sr_copy_scenario( $rollback ) {
	$db = sr_network( $rollback ? 'copia-vuelta' : 'copia' );
	$db->pdo->exec( 'CREATE TABLE `wp_2_posts` (`ID` INTEGER PRIMARY KEY, `post_author` bigint NOT NULL DEFAULT 0, `post_title` text)' );
	$db->seed( 'wp_2_posts', array( array( 'ID' => 1, 'post_author' => 2, 'post_title' => 'de ahora' ) ) );
	sr_temp_users( $db, array(
		array( 2, 'ana', '$P$Bana-de-hace-un-mes', 'ana@red.test', array( 'wp_2_capabilities' => sr_caps( 'administrator' ) ) ),
		array( 4, 'luis', '$P$Bluis-de-hace-un-mes', 'luis@red.test', array( 'wp_2_capabilities' => sr_caps( array( 'author' => true, 'manage_network_users' => true ) ) ) ),
		array( 5, 'pedro', '$P$Bpedro-de-hace-un-mes', 'pedro@red.test', array( 'wp_2_capabilities' => sr_caps( 'editor' ) ) ),
	), 'cp' );
	$db->pdo->exec( 'CREATE TABLE `wp_dgbkr_cp_001` (`ID` INTEGER PRIMARY KEY, `post_author` bigint NOT NULL DEFAULT 0, `post_title` text)' );
	$db->seed( 'wp_dgbkr_cp_001', array( array( 'ID' => 1, 'post_author' => 2, 'post_title' => 'de ana' ), array( 'ID' => 2, 'post_author' => 5, 'post_title' => 'de pedro' ), array( 'ID' => 3, 'post_author' => 4, 'post_title' => 'de luis' ) ) );
	$db->pdo->exec( "CREATE TABLE `wp_dgbkr_cp_002` (`option_id` INTEGER PRIMARY KEY AUTOINCREMENT, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$db->seed( 'wp_dgbkr_cp_002', array( array( 'option_name' => 'wp_2_user_roles', 'option_value' => serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true, 'manage_network_users' => true ) ) ) ) ), array( 'option_name' => 'blogname', 'option_value' => 'Tienda' ) ) );
	$into = array( 'mode' => 'replace', 'site_id' => 2, 'domain' => 'red.test', 'path' => '/tienda/', 'url' => 'http://red.test/tienda', 'label' => 'red.test/tienda' );
	$spec = array( 'into' => $into, 'keep_access' => false, 'keep_admins' => false );
	if ( $rollback ) $spec['rollback'] = array( 'job' => 'x', 'undo' => array( 'created' => array(), 'units' => array() ) );
	$job = sr_job( $spec, array(
		'metadata.json' => array( 'scope' => 'network-site', 'site_copy' => array( 'site_id' => 2, 'label' => 'red.test/tienda' ) ),
		'into.json' => array( 'target' => $into, 'rules' => array( 'installed' => array( 'plugins' => array(), 'themes' => array() ), 'kept' => array(), 'versions' => array(), 'units' => array() ) ),
		'tables.json' => array(
			array( 'backup' => 'wp_2_posts', 'dest' => 'wp_2_posts', 'temp' => 'wp_dgbkr_cp_001', 'old' => 'wp_dgbko_cp_001' ),
			array( 'backup' => 'wp_2_options', 'dest' => 'wp_2_options', 'temp' => 'wp_dgbkr_cp_002', 'old' => 'wp_dgbko_cp_002' ),
		),
		'merge.json' => array( 'users' => array( 'backup' => 'wp_2_users', 'dest' => 'wp_2_users', 'temp' => 'wp_dgbkr_cp_090' ), 'usermeta' => array( 'backup' => 'wp_2_usermeta', 'dest' => 'wp_2_usermeta', 'temp' => 'wp_dgbkr_cp_091' ) ),
	) );
	$cursor = bk_call( 'Digitalisimo_Backups', 'import_swap', $job, array(), INF );
	sr_check( ! empty( $cursor['swapped_at'] ), 'El cambio no terminó: ' . json_encode( $cursor ) );
	return array( $db, $job );
}

sr_case( 'M3 · restaurar desde la lista la copia de un sitio no recrea a quien ya no está en la red, y el informe dice quién', function() {
	list( $db, $job ) = sr_copy_scenario( false );
	$pedro = sr_user( $db, 'pedro' );
	sr_check( null === $pedro, 'Restaurar la copia de un sitio (sin `rollback`) revive una cuenta borrada de la red: pedro vuelve con ID ' . ( $pedro ? $pedro['ID'] . ( '$P$Bpedro-de-hace-un-mes' === $pedro['user_pass'] ? ' y su contraseña de entonces' : '' ) : '' ) );
	$journal = sr_staged( $job, 'users.json' );
	sr_check( 'missing' === ( $journal['kind'][5] ?? '' ) && array( 'pedro' ) === array_values( (array) $journal['skipped'] ) && ! $journal['created'], 'La fusión de la copia no anota a pedro como que no se recreó: ' . json_encode( array( $journal['kind'], $journal['skipped'], $journal['created'] ) ) );
	$result = bk_call( 'Digitalisimo_Backups', 'import_result', $job );
	sr_check( array( 'pedro' ) === $result['skipped'] && false !== strpos( $result['text'], 'pedro' ) && false !== strpos( $result['text'], 'no se recre' ), 'El informe no dice quién no se recreó: ' . $result['text'] );
	sr_check( 'Sitio de la red reemplazado: ' === substr( $result['text'], 0, strlen( 'Sitio de la red reemplazado: ' ) ), 'El informe de la copia restaurada desde la lista no es el de un reemplazo: ' . $result['text'] );
} );

sr_case( 'M4 · la copia de un sitio (de esta red o de otra) reasigna la autoría con el mapa de la fusión; la vuelta atrás, no', function() {
	list( $db, $job ) = sr_copy_scenario( false );
	$authors = array_map( 'intval', array_column( $db->rows( 'wp_2_posts' ), 'post_author', 'ID' ) );
	// ana → 2; pedro (no está en la red) → sin autor; luis (4 en la copia, 3 en la red) → 3. Sin reasignar, la entrada de
	// luis quedaría a nombre del ID 4, que en la red es de otra persona.
	sr_check( array( 1 => 2, 2 => 0, 3 => 3 ) === $authors, 'La autoría de la copia no pasa a los ID de la red: ' . json_encode( $authors ) );
	$remap = sr_staged( $job, 'remap.json' );
	sr_check( isset( $remap['report']['posts.post_author'], $remap['options'] ), 'La copia no pasó por la reasignación ni por las opciones del sitio: ' . json_encode( $remap ) );
	sr_check( false !== strpos( bk_call( 'Digitalisimo_Backups', 'import_result', $job )['text'], 'queda sin autor' ), 'El informe no dice que el contenido de quien no se recreó queda sin autor.' );
	// La vuelta atrás a la copia previa vuelve exacta: sus ID son los de la red y no se reasignan.
	list( $db ) = sr_copy_scenario( true );
	$authors = array_map( 'intval', array_column( $db->rows( 'wp_2_posts' ), 'post_author', 'ID' ) );
	sr_check( array( 1 => 2, 2 => 5, 3 => 4 ) === $authors, 'La vuelta atrás reasignó la autoría de la copia: ' . json_encode( $authors ) );
} );

sr_case( 'A2 · por el motor: el rol y las capacidades del sitio llegan sin capacidades de red, y el informe lo dice', function() use ( $I ) {
	list( $db, $job ) = sr_copy_scenario( false );
	$roles = $I::unserialize( (string) $db->value( 'wp_2_options', 'option_value', "option_name = 'wp_2_user_roles'" ) );
	sr_check( is_array( $roles ) && array( 'manage_options' => true ) === $roles['administrator']['capabilities'], 'wp_2_user_roles conserva capacidades de red: ' . json_encode( $roles ) );
	sr_check( sr_caps( 'author' ) === sr_meta( $db, 3, 'wp_2_capabilities' ), 'luis conserva manage_network_users en el sitio: ' . sr_meta( $db, 3, 'wp_2_capabilities' ) );
	$result = bk_call( 'Digitalisimo_Backups', 'import_result', $job );
	sr_check( false !== strpos( $result['text'], 'capacidades de red' ) && false !== strpos( $result['text'], 'manage_network_users' ) && false !== strpos( $result['text'], 'luis' ), 'El informe no dice que se quitaron capacidades de red: ' . $result['text'] );
	sr_check( array( 'administrator' ) === array_keys( (array) ( $result['network_caps']['roles'] ?? array() ) ) && array( 'luis' ) === array_keys( (array) ( $result['network_caps']['users'] ?? array() ) ), 'La respuesta no lista los roles y usuarios sin capacidades de red: ' . json_encode( $result['network_caps'] ?? null ) );
} );

sr_case( 'B3 · la respuesta dice que superadministradores y administradores del sitio reemplazado se conservan siempre, y qué pasó con keep_access/keep_admins', function() use ( $I ) {
	list( $db, $job ) = sr_copy_scenario( false );
	$result = bk_call( 'Digitalisimo_Backups', 'import_result', $job );
	$access = (array) ( $result['access'] ?? array() );
	sr_check( ! empty( $access['super_admins_kept'] ) && ! empty( $access['site_admins_kept'] ) && 'not_applied' === ( $access['keep_access'] ?? '' ) && 'not_applied' === ( $access['keep_admins'] ?? '' ) && false === ( $access['requested']['keep_access'] ?? null ) && false === ( $access['requested']['keep_admins'] ?? null ), 'La respuesta no dice qué pasó con el acceso: ' . json_encode( $access ) );
	foreach ( array( 'superadministradores', 'administradores del sitio', 'Conservar mi acceso', 'Conservar los administradores', 'Se pidió no conservar' ) as $phrase ) sr_check( false !== strpos( $result['text'], $phrase ), 'El texto de la respuesta (pantalla, REST y WP-CLI) no dice «' . $phrase . '»: ' . $result['text'] );
	sr_check( ! empty( sr_meta( $db, 2, 'wp_2_capabilities' ) ) && ! empty( $I::unserialize( (string) sr_meta( $db, 2, 'wp_2_capabilities' ) )['administrator'] ), 'ana dejó de administrar el sitio que se reemplazó.' );
	// La confirmación y verify (summary()) lo dicen antes; un sitio nuevo no tiene administradores que conservar.
	$summary = $I::summary( array( 'mode' => 'replace', 'site_id' => 2, 'label' => 'red.test/tienda' ) );
	$new = $I::summary( array( 'mode' => 'new', 'site_id' => 0, 'label' => 'red.test/nuevo' ) );
	sr_check( false !== strpos( $summary, 'superadministradores' ) && false !== strpos( $summary, 'Conservar los administradores' ) && false !== strpos( $new, 'superadministradores' ) && false === strpos( $new, 'administradores del sitio que se reemplaza' ), 'La confirmación no lo dice: ' . json_encode( array( $summary, $new ) ) );
	sr_check( false !== strpos( $I::field( 'nuevo', 'x' ), 'Conservar mi acceso' ), 'El campo de la confirmación en la pantalla no lo dice.' );
} );

/* ------------------------------------------------------------------ B1 · el «pendiente» de otra persona */

sr_case( 'B1 · un «pendiente» sólo se adopta con la marca de esta importación: la cuenta de otra persona con ese nombre, nunca', function() {
	$db = sr_network();
	sr_temp_users( $db, array( array( 4, 'nuevo', '$P$Bnuevo-respaldo', 'nuevo@cliente.test', array( 'wp_7_capabilities' => sr_caps( 'editor' ), 'nickname' => 'nuevo-del-respaldo' ) ) ) );
	// La pasada anterior anotó «nuevo» como pendiente y murió antes del INSERT; mientras tanto otra persona se registró con
	// ese nombre en la red.
	$db->seed( 'wp_users', array( array( 'ID' => 40, 'user_login' => 'nuevo', 'user_pass' => '$P$Bde-otra-persona', 'user_email' => 'otra@persona.test', 'user_activation_key' => '' ) ) );
	$db->seed( 'wp_usermeta', array( array( 'user_id' => 40, 'meta_key' => 'nickname', 'meta_value' => 'la-de-la-red' ), array( 'user_id' => 40, 'meta_key' => 'wp_2_capabilities', 'meta_value' => sr_caps( 'subscriber' ) ) ) );
	$pending = array( 'step' => 'users', 'cursor' => 0, 'pending' => array( 4 => 'nuevo' ), 'tokens' => array( 4 => 'dgbk-' . str_repeat( 'a', 32 ) ) );
	// Lo que la vuelta borraría si la importación fallara ahora.
	$job = sr_job( array( 'into' => array( 'mode' => 'new', 'site_id' => 7 ) ), array( 'users.json' => $pending ) );
	$undo = bk_call( 'Digitalisimo_Backups', 'import_undo_list', $job );
	sr_check( ! in_array( 40, $undo['created'], true ), 'Deshacer la importación borraría la cuenta de otra persona con el nombre pendiente: ' . json_encode( $undo ) );
	$journal = sr_merge( 7, 'new', $pending );
	sr_check( 40 !== (int) $journal['map'][4] && ! in_array( 40, (array) $journal['created'], true ), 'La fusión adoptó como creada la cuenta de otra persona con el nombre pendiente: ' . json_encode( $journal['map'] ) );
	sr_check( 'la-de-la-red' === sr_meta( $db, 40, 'nickname' ) && sr_caps( 'subscriber' ) === sr_meta( $db, 40, 'wp_2_capabilities' ) && null === sr_meta( $db, 40, 'wp_7_capabilities' ) && '$P$Bde-otra-persona' === $db->value( 'wp_users', 'user_pass', 'ID = 40' ), 'La cuenta de la otra persona cambió: ' . json_encode( $db->rows( 'wp_usermeta', 'user_id = 40' ) ) );
	$id = (int) $journal['map'][4];
	sr_check( 'nuevo-2' === $db->value( 'wp_users', 'user_login', 'ID = ' . $id ) && '$P$Bnuevo-respaldo' === $db->value( 'wp_users', 'user_pass', 'ID = ' . $id ) && '' === $db->value( 'wp_users', 'user_activation_key', 'ID = ' . $id ), 'La del respaldo no entró con un nombre libre (o quedó con la marca): ' . json_encode( $db->rows( 'wp_users', 'ID = ' . $id ) ) );
	// La propia: la fila que la pasada anterior insertó lleva la marca; se adopta y no se crea otra.
	$db = sr_network();
	sr_temp_users( $db, array( array( 4, 'nuevo', '$P$Bnuevo-respaldo', 'nuevo@cliente.test', array( 'wp_7_capabilities' => sr_caps( 'editor' ) ) ) ) );
	$db->seed( 'wp_users', array( array( 'ID' => 50, 'user_login' => 'nuevo', 'user_pass' => '$P$Bnuevo-respaldo', 'user_email' => 'nuevo@cliente.test', 'user_activation_key' => $pending['tokens'][4] ) ) );
	$job = sr_job( array( 'into' => array( 'mode' => 'new', 'site_id' => 7 ) ), array( 'users.json' => $pending ) );
	sr_check( in_array( 50, bk_call( 'Digitalisimo_Backups', 'import_undo_list', $job )['created'], true ), 'Deshacer no quitaría la cuenta que sí creó la importación.' );
	$journal = sr_merge( 7, 'new', $pending );
	sr_check( 50 === (int) $journal['map'][4] && in_array( 50, (array) $journal['created'], true ) && 1 === count( $db->rows( 'wp_users', "user_login LIKE 'nuevo%'" ) ) && '' === $db->value( 'wp_users', 'user_activation_key', 'ID = 50' ), 'La fila propia (con la marca) no se adoptó o conserva la marca: ' . json_encode( array( $journal['map'], $db->rows( 'wp_users', "user_login LIKE 'nuevo%'" ) ) ) );
} );

/* ------------------------------------------------------------------ M6 · la pantalla «Migrar» de la red */

sr_case( 'M6 · «Migrar» de la red: «sitio nuevo / reemplazando» va dentro del formulario y llega a from_request()', function() use ( $I ) {
	sr_network();
	$status = array( 'pull_problem' => '', 'active' => null, 'undo' => null, 'key_problem' => '', 'keys' => array(), 'kept_tables' => array(), 'allow_http' => false, 'rejected' => array(), 'log' => array() );
	ob_start();
	Digitalisimo_Backups_Migrate::panel( true, $status );
	$html = (string) ob_get_clean();
	preg_match_all( '#<form\b[^>]*>.*?</form>#s', $html, $forms );
	$pull = '';
	foreach ( $forms[0] as $form ) if ( false !== strpos( $form, 'value="' . Digitalisimo_Backups_Migrate::ACTION_PULL . '"' ) ) $pull = $form;
	sr_check( '' !== $pull, 'No está el formulario de «Migrar a este sitio».' );
	foreach ( array( 'name="into" value="new"', 'name="into" value="replace"', 'name="address"', 'name="into_site"' ) as $field ) sr_check( false !== strpos( $pull, $field ), 'El campo ' . $field . ' queda fuera del formulario de «Migrar a este sitio».' );
	sr_check( strpos( $pull, 'name="into"' ) < strpos( $pull, 'Migrar a este sitio</button>' ), 'La elección va después del botón de «Migrar».' );
	sr_check( 1 === substr_count( $html, 'name="into" value="new"' ), 'La elección se pinta más de una vez.' );
	// Lo que manda el navegador al elegir «Reemplazando el sitio» 2: lo recibe from_request() como el sitio que se reemplaza.
	preg_match_all( '#<(?:input|select|textarea)\b[^>]*\bname="([a-z_]+)"#', $pull, $names );
	$post = array_intersect_key( array( 'into' => 'replace', 'into_site' => '2', 'address' => 'otra-direccion', 'migration_key' => 'x' ), array_flip( $names[1] ) );
	sr_check( array( 'into' => '2', 'address' => 'otra-direccion' ) === $I::from_request( $post ), 'from_request() no recibe la elección del formulario: ' . json_encode( $I::from_request( $post ) ) );
} );

/* ------------------------------------------------------------------ B5 · la migración con clave que importa */

sr_case( 'B5 · la migración con clave que importa en una red marca su restauración como `aside` y lleva lo que se pidió', function() {
	sr_network();
	$migration = bk_call( 'Digitalisimo_Backups_Jobs', 'blank', Digitalisimo_Backups_Job_Store::new_id(), array( 'type' => 'migration', 'policy' => 'migration', 'into' => array( 'mode' => 'new', 'site_id' => 0, 'domain' => 'red.test', 'path' => '/migrado/', 'url' => 'http://red.test/migrado', 'label' => 'red.test/migrado' ), 'keep_access' => false, 'keep_admins' => false, 'origin' => array( 'user_id' => 1, 'via' => 'rest' ) ), 'running' );
	Digitalisimo_Backups_Job_Store::create( $migration );
	$path = rtrim( bk_storage(), '/' ) . '/digitalisimo-backup-migrado.zip';
	file_put_contents( $path, 'paquete' );
	$job = null;
	try {
		$job = Digitalisimo_Backups::migration_enqueue( array( 'zip' => null, 'source' => $path, 'path' => $path, 'container' => 'zip', 'trust' => 'signed', 'auth' => 'hmac', 'key' => null ), array( 'from' => 'https://origen.test', 'job' => $migration['id'] ), $migration['id'] );
		$spec = (array) $job['spec'];
		sr_check( 'migration' === ( $spec['aside']['kind'] ?? '' ) && $migration['id'] === ( $spec['aside']['migration'] ?? '' ) && 'https://origen.test' === ( $spec['aside']['from'] ?? '' ), 'La restauración de la migración que importa no va aparte: ' . json_encode( $spec['aside'] ?? null ) );
		sr_check( is_array( $spec['into'] ?? null ) && '/migrado/' === $spec['into']['path'], 'La restauración de la migración perdió el destino.' );
		sr_check( false === $spec['keep_access'] && false === $spec['keep_admins'], 'La restauración no lleva lo que la migración pidió de keep_access/keep_admins (para decirlo en su informe).' );
		sr_check( empty( $spec['exact_tables'] ) && '' === Digitalisimo_Backups_Migrate::kept_kind( $job ), 'La importación apartaría tablas de la red como una migración entera.' );
	} finally {
		if ( $job ) Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $job['id'] ) );
		Digitalisimo_Backups_Job_Store::remove_tree( Digitalisimo_Backups_Job_Store::directory( $migration['id'] ) );
		@unlink( $path );
	}
} );

bk_cleanup();
foreach ( glob( $GLOBALS['bk_root'] . '-*.sqlite' ) ?: array() as $file ) @unlink( $file );
echo $failures ? 'Revisión de la importación en una red: ' . $failures . " fallos.\n" : "Revisión de la importación en una red: todo correcto.\n";
exit( $failures ? 1 : 0 );
