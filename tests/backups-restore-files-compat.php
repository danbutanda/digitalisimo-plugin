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
 *   l · DISALLOW_FILE_MODS (18): el informe de wp-config.php (verify, el resultado, el correo) lo avisa;
 *   n · (revisión bk-180, H8) con la carpeta de temas fuera de ABSPATH (Bedrock) el tema que existe no se cambia ni se
 *       avisa, y el que falta pasa al tema por defecto de esa carpeta;
 *   o · (H3, F04) Restore_Compat::activate() en una red: activado en la red, sigue activo en la red que entra, no se toca
 *       si la red no entra y otra copia suya activada en la red se desactiva;
 *   p · (H9) el bloque de WordPress de .htaccess se escribe igual si el último tramo corre en WP-CLI o en el cron, decidido
 *       en una petición web o por el .htaccess de aquí; sin ninguna de las dos, el informe dice que no se comprobó.
 *   Además (H3, F10), Jetpack y la CDN de WP Rocket no se avisan al restaurar un respaldo del mismo sitio.
 * Con el código anterior fallan a, b, c, d, e, g, h, i, j, k y l; con el de acda04b2, n y p. Escribe «FALLA ·» si falla.
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
/** get_theme_root() de WordPress: la carpeta de temas (o la de un tema), que en Bedrock queda fuera de ABSPATH (caso n). */
function get_theme_root( $slug = '' ) { return $GLOBALS['fc_theme_root'] ?? WP_CONTENT_DIR . '/themes'; }
/** search_theme_directories(): los temas de las carpetas registradas con register_theme_directory() (caso n). */
function search_theme_directories( $force = false ) { return $GLOBALS['fc_theme_search'] ?? false; }
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
	if ( is_wp_error( $result ) && false !== strpos( $result->get_error_message(), 'RESTAURAR SIN FIRMA' ) ) $result = Digitalisimo_Backups::service_restore( basename( $file ), array( 'confirm' => 'RESTAURAR SIN FIRMA' ) + $args );
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

// Respaldo del mismo sitio (con su fila y su identidad): sin .htaccess, como uno hecho en nginx, y con un tema activo que
// no existe (un tema borrado después, o con otro nombre en este servidor).
fc_set( 'template', 'tema-que-no-existe' );
fc_set( 'stylesheet', 'tema-que-no-existe' );
// Jetpack conectado y la CDN de WP Rocket, de este mismo sitio: al restaurar su respaldo no hay nada que avisar (F10).
fc_set( 'active_plugins', serialize( array( 'demo/demo.php', FC_SELF, 'jetpack/jetpack.php', 'wp-rocket/wp-rocket.php' ) ) );
fc_set( 'jetpack_options', serialize( array( 'id' => 654321, 'version' => '14.0' ) ) );
fc_set( 'wp_rocket_settings', serialize( array( 'cdn' => 1, 'cdn_cnames' => array( 'cdn.este-sitio.test' ) ) ) );
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
	fc_check( false === strpos( (string) $result['notice'], 'Jetpack' ) && false === strpos( (string) $result['notice'], 'WP Rocket' ) && false === strpos( json_encode( $result['compat']['warnings'] ?? array() ), 'Jetpack' ), 'El respaldo del mismo sitio avisó de Jetpack o de la CDN de WP Rocket (son de este sitio): ' . (string) $result['notice'] );
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
	// Un bloque de WordPress correcto con lo que añade un plugin (filtro mod_rewrite_rules): no se reescribe.
	file_put_contents( $root . '/.htaccess', str_replace( "RewriteEngine On\n", "RewriteEngine On\n# regla que añade un plugin\n", (string) file_get_contents( $root . '/.htaccess' ) ) );
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
	fc_check( 1 === substr_count( $text, '# BEGIN WordPress' ) && false !== strpos( $text, '# BEGIN Propias' ) && false !== strpos( $text, '# regla que añade un plugin' ), 'Un .htaccess con el bloque correcto se reescribió o se duplicó: ' . $text );
} );

fc_case( 'k · CSS de constructores: restaurar sólo la base de datos vacía el CSS generado de Elements/Elementor', function() use ( $root, $same ) {
	$result = fc_restored( fc_restore( $same, array( 'parts' => 'database' ) ) );
	fc_check( ! file_exists( $root . '/wp-content/uploads/elementor/css/post-1.css' ), 'Quedó el CSS generado de otro momento (uploads/elementor/css).' );
	fc_check( null === $GLOBALS['wpdb']->value( 'wp_postmeta', 'meta_value', "meta_key = '_elementor_css'" ) && null === fc_option( '_elementor_global_css' ), 'Quedaron los metadatos del CSS de Elementor del respaldo (servirían un archivo que no es el suyo).' );
	fc_check( ! empty( $result['compat']['builder_css'] ) && false !== strpos( (string) $result['notice'], 'CSS generado' ), 'El informe no dice que se vació el CSS de constructores: ' . (string) $result['notice'] );
} );

// Otra instalación: su respaldo (sin fila aquí) trae drop-ins de su servidor, Jetpack conectado, la CDN de WP Rocket, otra
// copia de Backups activa en otra carpeta y no trae la carpeta de este plugin (como un paquete importado de Backuply).
fc_case( 'otra instalación · su respaldo se restaura aquí', function() use ( $root ) {
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
	$GLOBALS['fc_foreign_result'] = fc_restored( fc_restore( $foreign ) );
} );
fc_case( 'g · drop-ins de otra instalación: no se instalan, se conservan los de este servidor y se dicen', function() use ( $root ) {
	$result = fc_restored( $GLOBALS['fc_foreign_result'] ?? null );
	$notice = (string) $result['notice'];
	fc_check( ! file_exists( $root . '/wp-content/object-cache.php' ), 'Se instaló el object-cache.php del servidor de origen (error fatal en cada petición si su Redis no existe aquí).' );
	fc_check( false !== strpos( (string) @file_get_contents( $root . '/wp-content/advanced-cache.php' ), 'ESTE servidor' ), 'El advanced-cache.php de este servidor se reemplazó por el del origen.' );
	fc_check( array( 'object-cache.php' ) === ( $result['compat']['dropins']['not_installed'] ?? null ) && array( 'advanced-cache.php' ) === ( $result['compat']['dropins']['kept_here'] ?? null ) && false !== strpos( $notice, 'drop-ins' ), 'El informe no nombra los drop-ins: ' . json_encode( $result['compat']['dropins'] ?? null ) . ' · ' . $notice );
} );
fc_case( 'h · este plugin: un paquete sin DIGITALÍSIMO Backups no lo retira, queda activo y otra copia suya se desactiva', function() use ( $root ) {
	$result = fc_restored( $GLOBALS['fc_foreign_result'] ?? null );
	$notice = (string) $result['notice'];
	fc_check( is_file( $root . '/wp-content/plugins/digitalisimo-backups/digitalisimo-backups.php' ), 'Se retiró DIGITALÍSIMO Backups porque el respaldo no lo traía.' );
	$active = (array) unserialize( (string) fc_option( 'active_plugins' ) );
	fc_check( in_array( FC_SELF, $active, true ), 'DIGITALÍSIMO Backups no quedó activo en la base de datos restaurada (los ticks no lo cargarían): ' . json_encode( $active ) );
	fc_check( ! in_array( 'digitalisimo-backups-vieja/digitalisimo-backups.php', $active, true ) && in_array( 'jetpack/jetpack.php', $active, true ), 'La otra copia de Backups sigue activa (error fatal) o se tocaron otros plugins: ' . json_encode( $active ) );
	fc_check( false !== strpos( $notice, 'no traía DIGITALÍSIMO Backups' ) && false !== strpos( $notice, 'otra copia' ), 'El informe no dice qué pasó con DIGITALÍSIMO Backups: ' . $notice );
} );
fc_case( 'i · servicios del origen: Jetpack conectado y la CDN de WP Rocket se avisan', function() {
	$notice = (string) fc_restored( $GLOBALS['fc_foreign_result'] ?? null )['notice'];
	fc_check( false !== strpos( $notice, 'Jetpack' ) && false !== strpos( $notice, 'cdn.origen.test' ), 'El informe no avisa de Jetpack ni de la CDN de WP Rocket del origen: ' . $notice );
} );
fc_case( 'j · tablas de este WordPress que el respaldo de otra instalación no trae: se quedan y se informan', function() {
	$db = $GLOBALS['wpdb'];
	$notice = (string) fc_restored( $GLOBALS['fc_foreign_result'] ?? null )['notice'];
	fc_check( in_array( 'wp_tabla_de_aqui', $db->names(), true ) && false !== strpos( $notice, 'wp_tabla_de_aqui' ), 'La tabla propia de este sitio se borró o no se informa: ' . $notice );
} );

fc_case( 'm · una carpeta preparada en otro disco se copia junto a su sitio con sus permisos (no con los del umask)', function() use ( $root ) {
	$from = $GLOBALS['bk_root'] . '-copia-de';
	$to = $root . '/.digitalisimo-new-prueba';
	mkdir( $from . '/css', 0755, true );
	chmod( $from, 0755 );
	chmod( $from . '/css', 0755 );
	file_put_contents( $from . '/css/a.css', 'a{}' );
	chmod( $from . '/css/a.css', 0644 );
	$mask = umask( 077 );
	try {
		$ok = bk_call( 'Digitalisimo_Backups', 'restore_copy_tree', $from, $to );
	} finally {
		umask( $mask );
	}
	fc_check( $ok && '0755' === fc_mode( $to ) && '0755' === fc_mode( $to . '/css' ) && '0644' === fc_mode( $to . '/css/a.css' ), 'La copia quedó con ' . fc_mode( $to ) . ', ' . fc_mode( $to . '/css' ) . ' y ' . fc_mode( $to . '/css/a.css' ) . '.' );
	bk_remove_tree( $from );
	bk_remove_tree( $to );
} );

/** Restaura por ticks cortos con $is_apache según la fase (`enqueue` al pedirla; luego la del trabajo): la web o PHP CLI. */
function fc_restore_phased( $file, callable $apache ) {
	$GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] = 0;
	$before = array_column( Digitalisimo_Backups_Jobs::all(), 'id' );
	try {
		$GLOBALS['is_apache'] = $apache( 'enqueue' );
		$queued = fc_restore( $file, array( 'sync' => false, 'drive_cli' => false ) );
		fc_check( ! is_wp_error( $queued ), 'No se encoló la restauración: ' . ( is_wp_error( $queued ) ? $queued->get_error_message() : '' ) );
		$id = '';
		foreach ( Digitalisimo_Backups_Jobs::all() as $job ) if ( 'restore' === $job['kind'] && ! in_array( $job['id'], $before, true ) && empty( $job['spec']['rollback'] ) ) $id = $job['id'];
		fc_check( '' !== $id, 'No aparece el trabajo de restauración.' );
		for ( $i = 0; $i < 5000; $i++ ) {
			$job = Digitalisimo_Backups_Jobs::get( $id );
			if ( ! $job || Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
			$GLOBALS['is_apache'] = $apache( (string) $job['phase'] );
			Digitalisimo_Backups_Jobs::tick( 0.0 );
		}
	} finally {
		unset( $GLOBALS['bk_filters']['digitalisimo_backups_restore_respond_after'] );
		$GLOBALS['is_apache'] = true;
	}
	fc_check( $job && 'success' === $job['status'], 'La restauración por tramos no terminó bien: ' . json_encode( $job['error'] ?? null ) );
	return (array) ( $job['result']['restore'] ?? array() );
}

fc_case( 'n · tema con la carpeta de temas fuera de ABSPATH (Bedrock): el que existe no se cambia ni se avisa; el que falta pasa al por defecto de esa carpeta', function() use ( $root ) {
	$outside = $GLOBALS['bk_root'] . '-app/themes';
	$other = $GLOBALS['bk_root'] . '-otra/themes';
	$theme = function( $dir, $slug, $name, $block = false ) {
		mkdir( $dir . '/' . $slug . ( $block ? '/templates' : '' ), 0755, true );
		file_put_contents( $dir . '/' . $slug . '/style.css', "/*\nTheme Name: " . $name . "\n*/\n" );
		file_put_contents( $dir . '/' . $slug . ( $block ? '/templates/index.html' : '/index.php' ), $block ? "<!-- wp:post-content /-->\n" : "<?php // tema\n" );
	};
	$theme( $outside, 'externo', 'Externo' );
	$theme( $outside, 'twentytwentyfour', 'Twenty Twenty-Four', true );
	$theme( $other, 'registrado', 'Registrado' );
	$staging = $GLOBALS['bk_root'] . '-compat-staging';
	@mkdir( $staging, 0700, true );
	// La disposición de la restauración por pasos en Bedrock: los temas fuera de la raíz de WordPress (sin carpeta de temas).
	$layout = array( 'root' => trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) ), 'themes' => '', 'plugins' => 'wp-content/plugins', 'mu' => 'wp-content/mu-plugins', 'uploads' => 'wp-content/uploads', 'content' => 'wp-content', 'self' => '' );
	$run = function( $slug ) use ( $layout, $staging ) {
		fc_set( 'template', $slug );
		fc_set( 'stylesheet', $slug );
		return Digitalisimo_Backups_Restore_Compat::cleanup( array( 'step' => 'theme' ), INF, array( 'id' => 'prueba', 'spec' => array() ), $layout, $staging, true, null );
	};
	$template = fc_option( 'template' );
	$stylesheet = fc_option( 'stylesheet' );
	$GLOBALS['fc_theme_root'] = $outside;
	$GLOBALS['fc_theme_search'] = array( 'registrado' => array( 'theme_file' => 'registrado/style.css', 'theme_root' => $other ) );
	try {
		// En la carpeta de get_theme_root() (la de Bedrock) y en otra registrada que sólo da search_theme_directories().
		foreach ( array( 'externo', 'registrado' ) as $slug ) {
			$state = $run( $slug );
			fc_check( $slug === fc_option( 'template' ) && ! $state['notes'] && ! $state['warnings'], 'El tema «' . $slug . '», que existe fuera de ABSPATH, se dio por inexistente: ' . json_encode( array( fc_option( 'template' ), $state['notes'], $state['warnings'] ) ) );
		}
		$state = $run( 'no-existe' );
		fc_check( 'twentytwentyfour' === fc_option( 'template' ) && 'twentytwentyfour' === fc_option( 'stylesheet' ) && ! $state['warnings'] && false !== strpos( implode( ' ', $state['notes'] ), '«no-existe»' ), 'El tema que falta no pasó al tema por defecto de la carpeta de temas de WordPress: ' . json_encode( array( fc_option( 'template' ), $state['notes'], $state['warnings'] ) ) );
	} finally {
		unset( $GLOBALS['fc_theme_root'], $GLOBALS['fc_theme_search'] );
		fc_set( 'template', $template );
		fc_set( 'stylesheet', $stylesheet );
		bk_remove_tree( dirname( $outside ) );
		bk_remove_tree( dirname( $other ) );
		bk_remove_tree( $staging );
	}
} );

fc_case( 'o · activación en una red: activado en la red, sigue activo en la red que entra, no se toca si la red no entra y otra copia se desactiva', function() {
	$db = $GLOBALS['wpdb'];
	$staging = $GLOBALS['bk_root'] . '-activa-staging';
	@mkdir( $staging, 0700, true );
	$compat = function( $was ) use ( $staging ) { file_put_contents( $staging . '/compat.json', json_encode( array( 'v' => 1, 'self' => array( 'plugin' => FC_SELF, 'in_backup' => true, 'was' => $was ) ) ) ); @unlink( $staging . '/activation.json' ); };
	$old = 'digitalisimo-backups-vieja/digitalisimo-backups.php';
	$db->pdo->exec( "CREATE TABLE `dgbkr_t_options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$db->pdo->exec( "CREATE TABLE `dgbkr_t_sitemeta` (`meta_id` INTEGER PRIMARY KEY, `site_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	$seed = function() use ( $db, $old ) {
		$db->pdo->exec( 'DELETE FROM `dgbkr_t_options`' );
		$db->pdo->exec( 'DELETE FROM `dgbkr_t_sitemeta`' );
		$db->pdo->prepare( 'INSERT INTO `dgbkr_t_options` (option_name, option_value) VALUES (?, ?)' )->execute( array( 'active_plugins', serialize( array( 'demo/demo.php' ) ) ) );
		$db->pdo->prepare( 'INSERT INTO `dgbkr_t_sitemeta` (site_id, meta_key, meta_value) VALUES (1, ?, ?)' )->execute( array( 'active_sitewide_plugins', serialize( array( $old => 1, 'otro/otro.php' => 1 ) ) ) );
	};
	$site = function() use ( $db ) { return (array) unserialize( (string) $db->value( 'dgbkr_t_options', 'option_value', "option_name = 'active_plugins'" ) ); };
	$wide = function() use ( $db ) { return (array) unserialize( (string) $db->value( 'dgbkr_t_sitemeta', 'meta_value', "meta_key = 'active_sitewide_plugins'" ) ); };
	$both = array( array( 'dest' => 'wp_options', 'temp' => 'dgbkr_t_options' ), array( 'dest' => 'wp_sitemeta', 'temp' => 'dgbkr_t_sitemeta' ) );
	$GLOBALS['bk_multisite'] = true;
	try {
		// Activado en la red y la red entra (sitemeta está en el cambio): activo en la red que entra, la otra copia fuera.
		$seed();
		$compat( 'network' );
		Digitalisimo_Backups_Restore_Compat::activate( $staging, $both );
		$activation = json_decode( (string) @file_get_contents( $staging . '/activation.json' ), true );
		fc_check( isset( $wide()[ FC_SELF ], $wide()['otro/otro.php'] ) && ! isset( $wide()[ $old ] ), 'En la red que entra, DIGITALÍSIMO Backups no quedó activo en la red o la otra copia siguió: ' . json_encode( $wide() ) );
		fc_check( array( 'demo/demo.php' ) === $site() && 'network' === ( $activation['activated'] ?? '' ) && array( $old ) === ( $activation['replaced'] ?? null ), 'Activado en la red, se escribió en el sitio o no se anotó: ' . json_encode( array( $site(), $activation ) ) );
		// Activado en la red y la red no entra (sólo las tablas de un sitio): sigue activo en la red de aquí; nada que escribir.
		$seed();
		$compat( 'network' );
		Digitalisimo_Backups_Restore_Compat::activate( $staging, array( $both[0] ) );
		fc_check( array( 'demo/demo.php' ) === $site() && ! is_file( $staging . '/activation.json' ), 'Con la red que no entra, se activó en el sitio (ya lo está en la red): ' . json_encode( $site() ) );
		// Activado sólo en el sitio, en una red que entra: en el sitio; de la red sólo sale la otra copia.
		$seed();
		$compat( 'site' );
		Digitalisimo_Backups_Restore_Compat::activate( $staging, $both );
		fc_check( in_array( FC_SELF, $site(), true ) && ! isset( $wide()[ FC_SELF ] ) && ! isset( $wide()[ $old ] ) && isset( $wide()['otro/otro.php'] ), 'Activado en el sitio de una red, no quedó así: ' . json_encode( array( $site(), $wide() ) ) );
	} finally {
		$GLOBALS['bk_multisite'] = false;
		$db->pdo->exec( 'DROP TABLE IF EXISTS `dgbkr_t_options`' );
		$db->pdo->exec( 'DROP TABLE IF EXISTS `dgbkr_t_sitemeta`' );
		bk_remove_tree( $staging );
	}
} );

fc_case( 'p · .htaccess: el bloque se escribe igual si el último tramo corre en WP-CLI o en el cron; sin ninguna petición web ni bloque de aquí, se dice', function() use ( $root, $same ) {
	$own = "# BEGIN Propias\nHeader set X-Prueba 1\n# END Propias\n";
	$block = function() use ( $root ) { $text = (string) @file_get_contents( $root . '/.htaccess' ); return false !== strpos( $text, '# BEGIN WordPress' ) && false !== strpos( $text, 'RewriteRule . /index.php [L]' ) && false !== strpos( $text, '# BEGIN Propias' ) ? $text : ''; };
	// Pedida desde la web (Apache) y terminada en PHP CLI ($is_apache es falso en WP-CLI y en el cron del sistema).
	file_put_contents( $root . '/.htaccess', $own );
	$result = fc_restore_phased( $same, function( $phase ) { return 'enqueue' === $phase; } );
	fc_check( '' !== $block() && 'added' === ( $result['compat']['htaccess'] ?? '' ), 'Pedida en la web y terminada en PHP CLI, no se escribió el bloque: ' . json_encode( $result['compat']['htaccess'] ?? null ) . ' · ' . (string) @file_get_contents( $root . '/.htaccess' ) );
	// Pedida por WP-CLI, verificada en la web (la pantalla de avance) y terminada en PHP CLI.
	file_put_contents( $root . '/.htaccess', $own );
	$result = fc_restore_phased( $same, function( $phase ) { return 'inspect' === $phase || '' === $phase; } );
	fc_check( '' !== $block() && 'added' === ( $result['compat']['htaccess'] ?? '' ), 'Verificada en la web y terminada en PHP CLI, no se escribió el bloque: ' . json_encode( $result['compat']['htaccess'] ?? null ) );
	// Toda en PHP CLI, con el bloque de WordPress de otra ruta en el .htaccess de aquí: lo escribió WordPress, el servidor lo usa.
	file_put_contents( $root . '/.htaccess', $own . "\n# BEGIN WordPress\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /viejo/\nRewriteRule . /viejo/index.php [L]\n</IfModule>\n# END WordPress\n" );
	$result = fc_restore_phased( $same, function( $phase ) { return false; } );
	fc_check( '' !== $block() && false === strpos( $block(), '/viejo/' ) && 'fixed' === ( $result['compat']['htaccess'] ?? '' ), 'Toda en PHP CLI con el bloque de aquí de otra ruta, no se corrigió: ' . json_encode( $result['compat']['htaccess'] ?? null ) );
	// Toda en PHP CLI y sin el bloque de aquí: no se sabe si el servidor usa .htaccess; no se toca y el informe lo dice.
	file_put_contents( $root . '/.htaccess', $own );
	$result = fc_restore_phased( $same, function( $phase ) { return false; } );
	fc_check( $own === (string) @file_get_contents( $root . '/.htaccess' ) && 'skipped_cli' === ( $result['compat']['htaccess'] ?? '' ) && false !== strpos( (string) $result['notice'], 'No se comprobó el bloque de WordPress de .htaccess' ), 'Toda en PHP CLI sin pistas, no se dijo que el bloque no se comprobó: ' . json_encode( $result['compat']['htaccess'] ?? null ) . ' · ' . (string) ( $result['notice'] ?? '' ) );
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
