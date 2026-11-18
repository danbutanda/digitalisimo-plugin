<?php
/**
 * Archivos y lo de después al restaurar (estudio de UpdraftPlus del 2026-11-16, brechas 5, 6, 9, 12, 17 a 22), con el
 * motor real de respaldo y de restauración por pasos sobre una base SQLite de verdad (tests/lib/backups-sqlite.php) y la
 * wp_mkdir_p() de WordPress (hereda los permisos de la primera carpeta que existe), en un sitio con permisos de hosting
 * (carpetas 0755, archivos 0644):
 *   a · permisos (6): una carpeta de plugin que cambia entera vuelve con 0755 (no con el 0700 del área de trabajo privada),
 *       sus archivos con 0644, sumados a los que tenían (una carpeta 0775 y un archivo 0664 los conservan), y un medio
 *       preparado con un umask estricto (077) vuelve legible (0644);
 *   b · permisos tras la vuelta a la copia previa (6): un fallo después del cambio vuelve a la copia previa y lo que ésta
 *       pone tampoco queda en 0700;
 *   c · caché de páginas (9): al terminar una restauración del mismo sitio, wp-content/cache, et-cache (Divi) y
 *       uploads/fusion-styles (Avada) quedan vacías (los .htaccess de la caché se quedan) y el informe lo dice;
 *   d · .htaccess (12): el respaldo de un servidor nginx (sin .htaccess) restaurado en Apache con enlaces permanentes deja el
 *       bloque de WordPress (sin él las páginas internas dan 404) sin pisar las reglas propias del sitio; un bloque correcto
 *       no se reescribe;
 *   e · tema (19): el tema activo que no existe vuelve al tema por defecto de WordPress que sí está (nunca al tema
 *       DIGITALÍSIMO, aunque WP_DEFAULT_THEME lo nombre) y el informe lo dice;
 *   f · drop-ins en el mismo sitio: como siempre, vuelven los del respaldo;
 *   g · drop-ins de otra instalación (5): object-cache.php del origen no se instala y advanced-cache.php de este servidor se
 *       conserva; el informe los nombra;
 *   h · este plugin (22): un paquete sin DIGITALÍSIMO Backups (como uno importado de Backuply) no lo retira y la base de
 *       datos restaurada lo deja activo; otra copia suya activa en el respaldo se desactiva (dos a la vez, error fatal);
 *   i · servicios del origen (21): Jetpack conectado y la CDN de WP Rocket del origen se avisan;
 *   j · tablas de este WordPress que el respaldo de otra instalación no trae (17): se quedan y se informan;
 *   k · CSS de constructores (20): restaurar sólo la base de datos vacía el CSS generado de Elements/Elementor (archivos y
 *       metadatos); con los medios, no;
 *   l · DISALLOW_FILE_MODS (18): el informe de wp-config.php (verify, el resultado, el correo) lo avisa.
 * Con el código anterior fallan a, b, c, d, e, g, h, i, j, k y l. Escribe «FALLA ·» si falla.
 */
umask( 022 );
/** wp_mkdir_p() de WordPress: hereda los permisos de la primera carpeta que existe (la del entorno común crea 0700). */
function wp_mkdir_p( $target ) {
	if ( isset( $GLOBALS['bk_on_mkdir'] ) ) call_user_func( $GLOBALS['bk_on_mkdir'], $target );
	$target = str_replace( '//', '/', (string) $target );
	$target = '/' === $target ? $target : rtrim( $target, '/' );
	if ( file_exists( $target ) ) return @is_dir( $target );
	$parent = dirname( $target );
	while ( '.' !== $parent && ! is_dir( $parent ) && dirname( $parent ) !== $parent ) $parent = dirname( $parent );
	$stat = @stat( $parent );
	$perms = $stat ? $stat['mode'] & 07777 : 0777;
	if ( ! @mkdir( $target, $perms, true ) ) return false;
	if ( ( $perms & ~umask() ) != $perms ) {
		$parts = explode( '/', substr( $target, strlen( $parent ) + 1 ) );
		for ( $i = 1, $c = count( $parts ); $i <= $c; $i++ ) @chmod( $parent . '/' . implode( '/', array_slice( $parts, 0, $i ) ), $perms );
	}
	return true;
}
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
$GLOBALS['bk_filters']['digitalisimo_backups_job_backoff'] = 0;
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 0; } }
define( 'WP_DEFAULT_THEME', 'digitalisimo-theme' );

$failures = 0;
function fc_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function fc_case( $label, callable $test ) {
	try {
		$test();
		echo 'ok · ' . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
function fc_mode( $path ) { clearstatcache( true, $path ); return file_exists( $path ) ? sprintf( '%04o', fileperms( $path ) & 07777 ) : 'no existe'; }
function fc_put( $relative, $content ) {
	$path = ABSPATH . $relative;
	wp_mkdir_p( dirname( $path ) );
	file_put_contents( $path, $content );
	chmod( $path, 0644 );
}
/** Un sitio instalado como en un hosting: carpetas 0755 y archivos 0644. */
function fc_perms( $path ) {
	chmod( $path, 0755 );
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST ) as $entry ) if ( ! $entry->isLink() ) chmod( $entry->getPathname(), $entry->isDir() ? 0755 : 0644 );
}
function fc_option( $name ) { return $GLOBALS['wpdb']->value( 'wp_options', 'option_value', "option_name = '" . $name . "'" ); }
function fc_set( $name, $value ) {
	$db = $GLOBALS['wpdb'];
	$value = is_scalar( $value ) ? (string) $value : serialize( $value );
	if ( null === fc_option( $name ) ) $db->seed( 'wp_options', array( array( 'option_name' => $name, 'option_value' => $value ) ) );
	else $db->pdo->prepare( 'UPDATE wp_options SET option_value = ? WHERE option_name = ?' )->execute( array( $value, $name ) );
}
function fc_unset( $name ) { $GLOBALS['wpdb']->pdo->prepare( 'DELETE FROM wp_options WHERE option_name = ?' )->execute( array( $name ) ); }
/** Restaura por el caso de uso de la pantalla y REST, de punta a punta en este proceso (con «RESTAURAR SIN FIRMA» si el archivo no está firmado). */
function fc_restore( $file, array $args = array() ) {
	$args += array( 'network' => false, 'sync' => true, 'drive_cli' => true, 'via' => 'test' );
	$result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR' ) + $args );
	if ( is_wp_error( $result ) && 'confirm_unsigned' === $result->get_error_code() ) $result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR SIN FIRMA' ) + $args );
	return $result;
}
function fc_restored( $result ) {
	fc_check( ! is_wp_error( $result ) && ! empty( $result['restored'] ), 'La restauración no terminó: ' . ( is_wp_error( $result ) ? $result->get_error_message() : json_encode( $result ) ) );
	return $result;
}
/** Hace avanzar la cola hasta que la restauración (o su vuelta a la copia previa) termina. */
function fc_finish() {
	for ( $i = 0; $i < 2000; $i++ ) {
		if ( ! Digitalisimo_Backups_Jobs::active() ) return;
		Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
	}
	throw new RuntimeException( 'La restauración no terminó.' );
}
/** WP_Rewrite mínimo: las reglas que WordPress escribe con /%postname%/ en la raíz. */
class Fc_Rewrite {
	public $inits = 0;
	public function init() { $this->inits++; }
	public function mod_rewrite_rules() {
		$base = '/' . trim( (string) parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		$base = '/' === $base ? '/' : $base . '/';
		return "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase " . $base . "\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . " . $base . "index.php [L]\n</IfModule>\n";
	}
}

// El sitio: SQLite con opciones, entradas y metadatos; archivos de un hosting.
$root = rtrim( ABSPATH, '/' );
$db = new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-compat.sqlite', 'wp_' );
$db->pdo->exec( "CREATE TABLE `wp_options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
$db->pdo->exec( "CREATE TABLE `wp_posts` (`ID` INTEGER PRIMARY KEY, `post_content` longtext NOT NULL DEFAULT '', `guid` varchar(255) NOT NULL DEFAULT '')" );
$db->pdo->exec( "CREATE TABLE `wp_postmeta` (`meta_id` INTEGER PRIMARY KEY, `post_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
$GLOBALS['wpdb'] = $db;
const FC_SELF = 'digitalisimo-backups/digitalisimo-backups.php';
foreach ( array( 'siteurl' => 'https://example.test', 'home' => 'https://example.test', 'blogname' => 'Sitio', 'permalink_structure' => '/%postname%/', 'template' => 'tema', 'stylesheet' => 'tema', 'active_plugins' => serialize( array( 'demo/demo.php', FC_SELF ) ), '_elementor_global_css' => serialize( array( 'time' => 1 ) ) ) as $name => $value ) fc_set( $name, $value );
$db->seed( 'wp_posts', array( array( 'ID' => 1, 'post_content' => 'Hola' ) ) );
$db->seed( 'wp_postmeta', array( array( 'post_id' => 1, 'meta_key' => '_elementor_css', 'meta_value' => serialize( array( 'time' => 1, 'status' => 'file' ) ) ) ) );
$GLOBALS['bk_options'] += array( 'home' => 'https://example.test', 'siteurl' => 'https://example.test', 'permalink_structure' => '/%postname%/', 'active_plugins' => array( 'demo/demo.php', FC_SELF ) );
foreach ( array(
	'index.php' => "<?php // portada\n",
	'wp-content/index.php' => "<?php // Silence is golden.\n",
	'wp-content/plugins/demo/demo.php' => "<?php /* Plugin Name: Demo */\n",
	'wp-content/plugins/demo/assets/demo.css' => "body{color:#123}\n",
	'wp-content/plugins/demo/assets/demo.js' => "console.log(1);\n",
	'wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' => "<?php /* Plugin Name: DIGITALÍSIMO Backups */\n",
	'wp-content/themes/tema/style.css' => "/*\nTheme Name: Tema\n*/\n",
	'wp-content/themes/tema/index.php' => "<?php // tema\n",
	'wp-content/themes/twentytwentyfive/style.css' => "/*\nTheme Name: Twenty Twenty-Five\n*/\n",
	'wp-content/themes/twentytwentyfive/templates/index.html' => "<!-- wp:post-content /-->\n",
	'wp-content/themes/digitalisimo-theme/style.css' => "/*\nTheme Name: DIGITALÍSIMO\nUpdate URI: https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme\n*/\n",
	'wp-content/themes/digitalisimo-theme/index.php' => "<?php // tema propio\n",
	'wp-content/uploads/2026/11/foto.png' => str_repeat( "\x89PNG", 400 ),
	'wp-content/uploads/elementor/css/post-1.css' => ".elementor-1{color:red}\n",
	'wp-content/uploads/fusion-styles/avada.css' => "body{}\n",
	'wp-content/object-cache.php' => "<?php // caché de objetos de ESTE servidor (mismo sitio)\n",
) as $relative => $content ) fc_put( $relative, $content );
fc_perms( $root );
// Apache con enlaces permanentes (lo que dice WordPress en vars.php y lo que genera WP_Rewrite).
$GLOBALS['is_apache'] = true;
$GLOBALS['wp_rewrite'] = new Fc_Rewrite();

// Respaldo del mismo sitio (con su fila y su identidad): sin .htaccess, como uno hecho en nginx.
$same = bk_backup( true );
$demo_dir = $root . '/wp-content/plugins/demo';

fc_case( 'a · permisos: lo restaurado lleva los de WordPress sumados a los que tenía (nunca el 0700 del área privada)', function() use ( $root, $same, $demo_dir ) {
	file_put_contents( $demo_dir . '/assets/demo.css', "body{color:#f00}\n" );
	file_put_contents( $root . '/wp-content/uploads/2026/11/foto.png', 'cambiada' );
	chmod( $demo_dir . '/assets', 0775 );
	chmod( $demo_dir . '/assets/demo.js', 0664 );
	// Lo que hay del caso c (caché) y d (.htaccess) entra en esta misma restauración.
	fc_put( 'wp-content/cache/supercache/example.test/index.html', '<html>página de antes de restaurar, quizá infectada</html>' );
	fc_put( 'wp-content/cache/.htaccess', "Options -Indexes\n" );
	fc_put( 'wp-content/et-cache/1/et-core-unified-1.min.css', 'body{}' );
	fc_put( 'wp-content/wphb-cache/cache/example.test/index.html', '<html>vieja</html>' );
	fc_put( '.htaccess', "# BEGIN Propias\nHeader set X-Prueba 1\n# END Propias\n" );
	fc_set( 'template', 'tema-que-no-existe' );
	fc_set( 'stylesheet', 'tema-que-no-existe' );
	@unlink( $root . '/wp-content/object-cache.php' );
	$mask = umask( 077 );
	try {
		$GLOBALS['fc_same_result'] = fc_restore( $same );
	} finally {
		umask( $mask );
	}
	fc_restored( $GLOBALS['fc_same_result'] );
	fc_check( "body{color:#123}\n" === file_get_contents( $demo_dir . '/assets/demo.css' ), 'El plugin no volvió al respaldo.' );
	fc_check( '0755' === fc_mode( $demo_dir ), 'La carpeta del plugin restaurado quedó con ' . fc_mode( $demo_dir ) . ' (el servidor web no la leería: CSS, JS e imágenes en 403).' );
	fc_check( '0775' === fc_mode( $demo_dir . '/assets' ), 'Una carpeta 0775 quedó con ' . fc_mode( $demo_dir . '/assets' ) . ' (se quitaron permisos o no se pusieron los de WordPress).' );
	fc_check( '0644' === fc_mode( $demo_dir . '/assets/demo.css' ) && '0664' === fc_mode( $demo_dir . '/assets/demo.js' ), 'Archivos del plugin con ' . fc_mode( $demo_dir . '/assets/demo.css' ) . ' y ' . fc_mode( $demo_dir . '/assets/demo.js' ) . ' (se esperaba 0644 y el 0664 que tenía).' );
	fc_check( '0644' === fc_mode( $root . '/wp-content/uploads/2026/11/foto.png' ), 'Un medio preparado con umask 077 quedó con ' . fc_mode( $root . '/wp-content/uploads/2026/11/foto.png' ) . '.' );
} );

fc_case( 'c · la caché de páginas (y la de Divi y Avada) se vacía al terminar una restauración del mismo sitio', function() use ( $root ) {
	$result = fc_restored( $GLOBALS['fc_same_result'] ?? null );
	foreach ( array( 'wp-content/cache/supercache/example.test/index.html', 'wp-content/et-cache/1/et-core-unified-1.min.css', 'wp-content/wphb-cache/cache/example.test/index.html', 'wp-content/uploads/fusion-styles/avada.css' ) as $stale ) fc_check( ! file_exists( $root . '/' . $stale ), 'Sigue la caché de antes de restaurar: ' . $stale );
	fc_check( is_file( $root . '/wp-content/cache/.htaccess' ) && is_dir( $root . '/wp-content/cache' ), 'Se borró la carpeta de caché o su .htaccess (sólo se vacían las páginas guardadas).' );
	fc_check( false !== strpos( (string) $result['notice'], 'caché de páginas' ) && (int) ( $result['compat']['page_cache']['files'] ?? 0 ) >= 4, 'El informe no dice que se vació la caché de páginas: ' . (string) $result['notice'] );
	fc_check( is_file( $root . '/wp-content/uploads/elementor/css/post-1.css' ) && null !== $GLOBALS['wpdb']->value( 'wp_postmeta', 'meta_value', "meta_key = '_elementor_css'" ), 'Con la base de datos y los medios juntos se vació el CSS de Elementor (no hacía falta).' );
} );

fc_case( 'd · .htaccess: el bloque de WordPress que faltaba (respaldo de nginx) se escribe sin pisar las reglas propias', function() use ( $root ) {
	$result = fc_restored( $GLOBALS['fc_same_result'] ?? null );
	$text = (string) @file_get_contents( $root . '/.htaccess' );
	fc_check( false !== strpos( $text, "# BEGIN WordPress" ) && false !== strpos( $text, 'RewriteRule . /index.php [L]' ) && false !== strpos( $text, "# END WordPress" ), 'No se escribió el bloque de WordPress (las páginas internas darían 404): ' . $text );
	fc_check( false !== strpos( $text, "# BEGIN Propias\nHeader set X-Prueba 1\n# END Propias" ) && strpos( $text, '# BEGIN Propias' ) < strpos( $text, '# BEGIN WordPress' ), 'Se tocaron las reglas propias del sitio: ' . $text );
	fc_check( 'added' === ( $result['compat']['htaccess'] ?? '' ) && false !== strpos( (string) $result['notice'], 'bloque de WordPress' ), 'El informe no dice que se escribió el bloque: ' . (string) $result['notice'] );
	fc_check( '0644' === fc_mode( $root . '/.htaccess' ), '.htaccess quedó con ' . fc_mode( $root . '/.htaccess' ) . '.' );
} );

fc_case( 'e · el tema que no existe vuelve al tema por defecto de WordPress (nunca al tema DIGITALÍSIMO) y se dice', function() {
	$result = fc_restored( $GLOBALS['fc_same_result'] ?? null );
	fc_check( 'twentytwentyfive' === fc_option( 'template' ) && 'twentytwentyfive' === fc_option( 'stylesheet' ), 'El tema activo quedó en «' . fc_option( 'stylesheet' ) . '» (que no existe, o el tema DIGITALÍSIMO activado solo).' );
	fc_check( 'Twenty Twenty-Five' === fc_option( 'current_theme' ) || null === fc_option( 'current_theme' ), 'current_theme no es el del tema nuevo: ' . var_export( fc_option( 'current_theme' ), true ) );
	fc_check( false !== strpos( (string) $result['notice'], '«tema-que-no-existe»' ) && false !== strpos( (string) $result['notice'], 'twentytwentyfive' ), 'El informe no dice que cambió el tema: ' . (string) $result['notice'] );
} );

fc_case( 'f · drop-ins en el mismo sitio: vuelven los del respaldo, como siempre', function() use ( $root ) {
	fc_restored( $GLOBALS['fc_same_result'] ?? null );
	fc_check( is_file( $root . '/wp-content/object-cache.php' ) && false !== strpos( file_get_contents( $root . '/wp-content/object-cache.php' ), 'ESTE servidor' ), 'El drop-in del respaldo del mismo sitio no volvió.' );
} );

fc_case( 'b · permisos tras la vuelta a la copia previa: lo que repone tampoco queda en 0700', function() use ( $root, $same, $demo_dir ) {
	file_put_contents( $demo_dir . '/assets/demo.css', "body{color:#0f0}\n" );
	file_put_contents( $root . '/wp-content/uploads/2026/11/foto.png', 'otra vez cambiada' );
	chmod( $demo_dir, 0755 );
	$GLOBALS['wpdb']->queries = array();
	// Después del cambio de tablas y del código, al poner los medios: falla y vuelve a la copia previa.
	$GLOBALS['bk_on_mkdir'] = function( $path ) {
		$job = Digitalisimo_Backups_Jobs::get( Digitalisimo_Backups_Jobs::current_id() );
		if ( $job && 'restore' === $job['kind'] && empty( $job['spec']['rollback'] ) && 'switch' === $job['phase'] && false !== strpos( (string) $path, '/uploads' ) && preg_grep( '/^RENAME TABLE /', $GLOBALS['wpdb']->queries ) ) throw new RuntimeException( 'disco lleno simulado al mover los medios' );
	};
	try {
		$result = fc_restore( $same, array( 'sync' => false, 'drive_cli' => false ) );
		fc_finish();
	} finally {
		unset( $GLOBALS['bk_on_mkdir'] );
	}
	$rollback = null;
	foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ! empty( $job['spec']['rollback'] ) ) $rollback = $job;
	fc_check( $rollback && 'success' === $rollback['status'], 'No hubo vuelta a la copia previa: ' . json_encode( is_wp_error( $result ) ? $result->get_error_message() : $result ) );
	fc_check( "body{color:#0f0}\n" === file_get_contents( $demo_dir . '/assets/demo.css' ), 'La vuelta a la copia previa no repuso el plugin.' );
	fc_check( '0755' === fc_mode( $demo_dir ) && '0644' === fc_mode( $demo_dir . '/assets/demo.css' ), 'Tras la vuelta a la copia previa la carpeta del plugin quedó con ' . fc_mode( $demo_dir ) . ' y su CSS con ' . fc_mode( $demo_dir . '/assets/demo.css' ) . '.' );
	$text = (string) @file_get_contents( $root . '/.htaccess' );
	fc_check( 1 === substr_count( $text, '# BEGIN WordPress' ) && false !== strpos( $text, '# BEGIN Propias' ), 'Un .htaccess con el bloque correcto se reescribió o se duplicó: ' . $text );
} );

fc_case( 'k · CSS de constructores: restaurar sólo la base de datos vacía el CSS generado de Elements/Elementor', function() use ( $root, $same ) {
	$result = fc_restored( fc_restore( $same, array( 'parts' => 'database' ) ) );
	fc_check( ! file_exists( $root . '/wp-content/uploads/elementor/css/post-1.css' ), 'Quedó el CSS generado de otro momento (uploads/elementor/css).' );
	fc_check( null === $GLOBALS['wpdb']->value( 'wp_postmeta', 'meta_value', "meta_key = '_elementor_css'" ) && null === fc_option( '_elementor_global_css' ), 'Quedaron los metadatos del CSS de Elementor del respaldo (servirían un archivo que no es el suyo).' );
	fc_check( ! empty( $result['compat']['builder_css'] ) && false !== strpos( (string) $result['notice'], 'CSS generado' ), 'El informe no dice que se vació el CSS de constructores: ' . (string) $result['notice'] );
} );

// Otra instalación: su respaldo (sin fila aquí) trae drop-ins de su servidor, Jetpack conectado, la CDN de WP Rocket, otra
// copia de Backups activa en otra carpeta y no trae la carpeta de este plugin (como un paquete importado de Backuply).
fc_case( 'g/h/i/j · el respaldo de otra instalación: drop-ins, este plugin, servicios del origen y tablas de aquí', function() use ( $root ) {
	$db = $GLOBALS['wpdb'];
	fc_put( 'wp-content/object-cache.php', "<?php require '/servidor-de-origen/redis-config.php'; // del ORIGEN\n" );
	fc_put( 'wp-content/advanced-cache.php', "<?php // advanced-cache del ORIGEN con sus rutas\n" );
	fc_put( 'wp-content/plugins/digitalisimo-backups-vieja/digitalisimo-backups.php', "<?php /* Plugin Name: DIGITALÍSIMO Backups (otra copia) */\n" );
	rename( $root . '/wp-content/plugins/digitalisimo-backups', $GLOBALS['bk_root'] . '-fuera' );
	fc_set( 'active_plugins', serialize( array( 'demo/demo.php', 'digitalisimo-backups-vieja/digitalisimo-backups.php', 'jetpack/jetpack.php', 'wp-rocket/wp-rocket.php' ) ) );
	fc_set( 'jetpack_options', serialize( array( 'id' => 123456, 'version' => '14.0' ) ) );
	fc_set( 'wp_rocket_settings', serialize( array( 'cdn' => 1, 'cdn_cnames' => array( 'cdn.origen.test' ) ) ) );
	$foreign = bk_backup( false );
	// Este servidor: su advanced-cache.php, ningún object-cache.php, este plugin activo y una tabla propia.
	rename( $GLOBALS['bk_root'] . '-fuera', $root . '/wp-content/plugins/digitalisimo-backups' );
	bk_remove_tree( $root . '/wp-content/plugins/digitalisimo-backups-vieja' );
	@unlink( $root . '/wp-content/object-cache.php' );
	fc_put( 'wp-content/advanced-cache.php', "<?php // advanced-cache de ESTE servidor\n" );
	fc_set( 'active_plugins', serialize( array( 'demo/demo.php', FC_SELF ) ) );
	fc_unset( 'jetpack_options' );
	fc_unset( 'wp_rocket_settings' );
	$db->pdo->exec( 'CREATE TABLE `wp_tabla_de_aqui` (`id` INTEGER PRIMARY KEY)' );
	$result = fc_restored( fc_restore( $foreign ) );
	$notice = (string) $result['notice'];
	// g · drop-ins (5).
	fc_check( ! file_exists( $root . '/wp-content/object-cache.php' ), 'Se instaló el object-cache.php del servidor de origen (error fatal en cada petición si su Redis no existe aquí).' );
	fc_check( false !== strpos( (string) @file_get_contents( $root . '/wp-content/advanced-cache.php' ), 'ESTE servidor' ), 'El advanced-cache.php de este servidor se reemplazó por el del origen.' );
	fc_check( array( 'object-cache.php' ) === ( $result['compat']['dropins']['not_installed'] ?? null ) && array( 'advanced-cache.php' ) === ( $result['compat']['dropins']['kept_here'] ?? null ) && false !== strpos( $notice, 'drop-ins' ), 'El informe no nombra los drop-ins: ' . json_encode( $result['compat']['dropins'] ?? null ) . ' · ' . $notice );
	// h · este plugin (22).
	fc_check( is_file( $root . '/wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' ), 'Se retiró DIGITALÍSIMO Backups porque el respaldo no lo traía.' );
	$active = (array) unserialize( (string) fc_option( 'active_plugins' ) );
	fc_check( in_array( FC_SELF, $active, true ), 'DIGITALÍSIMO Backups no quedó activo en la base de datos restaurada (los ticks no lo cargarían): ' . json_encode( $active ) );
	fc_check( ! in_array( 'digitalisimo-backups-vieja/digitalisimo-backups.php', $active, true ) && in_array( 'jetpack/jetpack.php', $active, true ), 'La otra copia de Backups sigue activa (error fatal) o se tocaron otros plugins: ' . json_encode( $active ) );
	fc_check( false !== strpos( $notice, 'no traía DIGITALÍSIMO Backups' ) && false !== strpos( $notice, 'otra copia' ), 'El informe no dice qué pasó con DIGITALÍSIMO Backups: ' . $notice );
	// i · servicios del origen (21).
	fc_check( false !== strpos( $notice, 'Jetpack' ) && false !== strpos( $notice, 'cdn.origen.test' ), 'El informe no avisa de Jetpack ni de la CDN de WP Rocket del origen: ' . $notice );
	// j · tablas de aquí (17).
	fc_check( in_array( 'wp_tabla_de_aqui', $db->names(), true ) && false !== strpos( $notice, 'wp_tabla_de_aqui' ), 'La tabla propia de este sitio se borró o no se informa: ' . $notice );
} );

fc_case( 'l · DISALLOW_FILE_MODS se avisa en el informe de wp-config.php (verify, resultado, correo)', function() use ( $same ) {
	define( 'DISALLOW_FILE_MODS', true );
	$zip = new ZipArchive();
	fc_check( true === $zip->open( $same ), 'No se abrió el respaldo.' );
	try {
		$report = bk_call( 'Digitalisimo_Backups', 'config_report', $zip );
	} finally {
		$zip->close();
	}
	fc_check( ! empty( $report['disallow_file_mods'] ) && false !== strpos( (string) $report['text'], 'DISALLOW_FILE_MODS' ) && false !== strpos( (string) $report['text'], 'restaura por partes' ), 'El informe no avisa de DISALLOW_FILE_MODS: ' . json_encode( $report ) );
} );

bk_cleanup();
@unlink( $GLOBALS['bk_root'] . '-compat.sqlite' );
echo $failures ? "Archivos y lo de después al restaurar: {$failures} caso(s) con fallos.\n" : "DIGITALÍSIMO Backups: lo restaurado lleva permisos de WordPress, los drop-ins de otra instalación no se instalan, este plugin sigue activo, la caché se vacía, .htaccess tiene su bloque, el tema existe y lo del origen se avisa.\n";
exit( $failures ? 1 : 0 );
