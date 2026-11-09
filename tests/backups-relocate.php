<?php
/**
 * Restaurar en otro dominio, otra ruta y otro prefijo (E1), con una base de datos de verdad (SQLite por
 * tests/lib/backups-sqlite.php): el motor de trabajos real crea el respaldo del «origen», se copia como archivo suelto de
 * otro servidor (con las rutas de su manifiesto cambiadas, como si viniera de /var/www/origen) y el caso de uso de la
 * pantalla, REST y WP-CLI lo restaura en otro «destino». Comprueba:
 *   E1  sitio individual, otro dominio y otra ruta: URL en texto, serializado anidado (longitudes en bytes, multibyte),
 *       objetos que nunca se instancian (__wakeup/__destruct dejarían rastro), JSON escapado una y dos veces (el
 *       _elementor_data de Elements), URL codificadas, http/https, //dominio, www, rutas del servidor, guid intacto,
 *       correos intactos, sin falsos positivos (origen.com.mx, miorigen.com), serializado dañado intacto, cachés
 *       (transitorios, rewrite_rules, CSS de Elementor en la base de datos y en uploads, fuentes locales) y la
 *       comprobación final en 0;
 *   E2  otro prefijo de tablas en una base de datos que también aloja otro WordPress con el prefijo del origen: tablas y
 *       claves con prefijo (user_roles, capabilities…) renombradas, REFERENCES de una clave foránea, el otro WordPress y
 *       una tabla ajena intactos; y la opción «conservar las direcciones del respaldo»;
 *   E3  red por subdirectorios a otro dominio y otra ruta con otro prefijo (blogs, site, sitemeta, opciones de cada
 *       sitio, claves {prefijo}N_);
 *   E4  red por subdominios (x.origen.com → x.destino.com; un dominio propio y un subdominio ajeno no se tocan);
 *   E5  comprobaciones previas: red de otro tipo y wp-config.php que no declara la red, rechazadas antes de tocar nada
 *       con un mensaje que dice qué cambiar;
 *   E6  el reescritor y el guardián por separado (casos límite, claves de arrays, C:, profundidad, rutas) y la pasada
 *       sobre una base ya importada por lotes de clave primaria (compuesta, sin clave, BLOB).
 * Deben fallar con el código anterior (S4): rechaza otro prefijo y deja las URL del origen.
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/lib/backups-sqlite.php';
define( 'E1_RASTRO', $GLOBALS['bk_root'] . '-rastro.txt' );

/** Una clase con efectos: si alguien la instanciara desde el respaldo, dejaría rastro. */
class Digi_Trampa {
	public $u = '';
	public function __wakeup() { $GLOBALS['e1_rastro'][] = 'wakeup'; @file_put_contents( E1_RASTRO, "wakeup\n", FILE_APPEND ); }
	public function __destruct() { $GLOBALS['e1_rastro'][] = 'destruct'; @file_put_contents( E1_RASTRO, "destruct\n", FILE_APPEND ); }
}
$GLOBALS['e1_rastro'] = array();
$GLOBALS['e1_network'] = null;
$GLOBALS['e1_subdomain'] = false;
if ( ! function_exists( 'get_network' ) ) { function get_network() { return $GLOBALS['e1_network'] ? (object) $GLOBALS['e1_network'] : null; } }
if ( ! function_exists( 'is_subdomain_install' ) ) { function is_subdomain_install() { return ! empty( $GLOBALS['e1_subdomain'] ); } }
if ( ! function_exists( 'switch_to_blog' ) ) { function switch_to_blog( $id ) { return true; } }
if ( ! function_exists( 'restore_current_blog' ) ) { function restore_current_blog() { return true; } }

$failures = 0;
function e1_check( $ok, $what ) { if ( ! $ok ) throw new RuntimeException( $what ); }
function e1_case( $label, callable $test ) {
	try {
		$test();
		echo "ok · " . $label . "\n";
	} catch ( Throwable $e ) {
		$GLOBALS['failures']++;
		echo 'FALLA · ' . $label . ': ' . $e->getMessage() . ( getenv( 'BK_VERBOSE' ) ? ' @ ' . $e->getFile() . ':' . $e->getLine() : '' ) . "\n";
	}
}
/** Base de datos SQLite nueva en el temporal de la prueba. */
function e1_db( $name, $prefix ) { return new Bk_Sqlite_DB( $GLOBALS['bk_root'] . '-' . $name . '.sqlite', $prefix ); }
/** Tablas de un sitio ($blog = '' o '2_') y, con $global, las de usuarios. */
function e1_tables( Bk_Sqlite_DB $db, $prefix, $blog = '', $global = true ) {
	$b = $prefix . $blog;
	$db->pdo->exec( "CREATE TABLE `{$b}options` (`option_id` INTEGER PRIMARY KEY, `option_name` varchar(191) NOT NULL UNIQUE, `option_value` longtext NOT NULL, `autoload` varchar(20) NOT NULL DEFAULT 'yes')" );
	$db->pdo->exec( "CREATE TABLE `{$b}posts` (`ID` INTEGER PRIMARY KEY, `post_title` text NOT NULL, `post_content` longtext NOT NULL, `guid` varchar(255) NOT NULL DEFAULT '', `post_type` varchar(20) NOT NULL DEFAULT 'post')" );
	$db->pdo->exec( "CREATE TABLE `{$b}postmeta` (`meta_id` INTEGER PRIMARY KEY, `post_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
	if ( ! $global ) return;
	$db->pdo->exec( "CREATE TABLE `{$prefix}users` (`ID` INTEGER PRIMARY KEY, `user_login` varchar(60) NOT NULL, `user_email` varchar(100) NOT NULL, `user_url` varchar(100) NOT NULL DEFAULT '')" );
	$db->pdo->exec( "CREATE TABLE `{$prefix}usermeta` (`umeta_id` INTEGER PRIMARY KEY, `user_id` bigint(20) NOT NULL DEFAULT 0, `meta_key` varchar(255), `meta_value` longtext)" );
}
/** Opciones mínimas de un sitio. */
function e1_options( Bk_Sqlite_DB $db, $table, $home, $siteurl, array $more = array() ) {
	$rows = array( array( 'option_name' => 'siteurl', 'option_value' => $siteurl ), array( 'option_name' => 'home', 'option_value' => $home ), array( 'option_name' => 'blogname', 'option_value' => 'Sitio' ) );
	foreach ( $more as $name => $value ) $rows[] = array( 'option_name' => $name, 'option_value' => $value );
	$db->seed( $table, $rows );
}
/** Instalación «actual» de la prueba: base de datos, direcciones y, en una red, su dominio y su ruta. */
function e1_use( Bk_Sqlite_DB $db, $home, ?array $network = null, $subdomain = false ) {
	$GLOBALS['wpdb'] = $db;
	$GLOBALS['bk_options']['home'] = $home;
	$GLOBALS['bk_options']['siteurl'] = $home;
	$GLOBALS['bk_multisite'] = null !== $network;
	$GLOBALS['e1_network'] = $network;
	$GLOBALS['e1_subdomain'] = $subdomain;
}
/** Respaldo del origen con el motor real, como archivo suelto de otro servidor en /var/www/origen. */
function e1_backup( $origin_root = '/var/www/origen' ) {
	$file = bk_backup();
	$zip = new ZipArchive();
	if ( true !== $zip->open( $file ) ) throw new RuntimeException( 'No se abrió el respaldo.' );
	$manifest = json_decode( $zip->getFromName( 'manifest.json' ), true );
	$manifest['origin'] = array_merge( (array) ( $manifest['origin'] ?? array() ), array( 'abspath' => $origin_root, 'abspath_real' => '', 'content_dir' => $origin_root . '/wp-content', 'uploads_dir' => $origin_root . '/wp-content/uploads' ) );
	$zip->addFromString( 'manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	$zip->close();
	return basename( $file );
}
function e1_restore( $file, array $args = array() ) {
	$GLOBALS['e1_rastro'] = array();
	$result = Digitalisimo_Backups::service_restore( $file, $args + array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => ! empty( $GLOBALS['bk_multisite'] ) ) );
	if ( is_wp_error( $result ) ) throw new RuntimeException( 'La restauración falló: ' . $result->get_error_message() );
	return $result;
}
function e1_no_trap() {
	e1_check( ! $GLOBALS['e1_rastro'] && ! file_exists( E1_RASTRO ), 'Se instanció un objeto del respaldo (__wakeup/__destruct dejaron rastro).' );
}
function e1_unserializes( $value, $what ) {
	$data = @unserialize( $value, array( 'allowed_classes' => false ) );
	e1_check( false !== $data || 'b:0;' === $value, $what . ': el serializado reescrito no se lee (longitudes rotas): ' . substr( $value, 0, 200 ) );
	return $data;
}
$abspath = rtrim( ABSPATH, '/' );
$trap_url = 'https://origen.com/trampa?x=ñ';
$trap = 'O:11:"Digi_Trampa":1:{s:1:"u";s:' . strlen( $trap_url ) . ':"' . $trap_url . '";}';
$widget = serialize( array( 2 => array( 'title' => 'Año ñandú €', 'text' => '<a href="https://origen.com/contacto/">Contacto</a> y <img src="http://www.origen.com/wp-content/uploads/foto.jpg">', 'inner' => serialize( array( 'url' => 'https://origen.com/€uro', 'deep' => serialize( array( 'x' => '//origen.com/profundo' ) ) ) ) ), '_multiwidget' => 1 ) );
$elementor = '[{"id":"a1","settings":{"background_image":{"url":"https:\/\/origen.com\/wp-content\/uploads\/fondo.jpg","id":7},"link":{"url":"https:\/\/origen.com\/tienda\/"}},"elements":[],"widgetType":"image","html":"<a href=\"https:\\\\\/\\\\\/origen.com\\\\\/doble\">"}]';
$malformed = 'a:1:{s:1:"u";s:99:"https://origen.com/roto";}';

/* ---------------------------------------------------------------------------------------- Origen: sitio individual */
$origin = e1_db( 'origen', 'wp_' );
e1_tables( $origin, 'wp_' );
$origin->pdo->exec( "CREATE TABLE `wp_hijos` (`id` INTEGER PRIMARY KEY, `padre` bigint(20) REFERENCES `wp_posts` (`ID`), `nota` text)" );
$origin->pdo->exec( "CREATE TABLE `otra_app` (`id` INTEGER PRIMARY KEY, `url` text)" );
e1_options( $origin, 'wp_options', 'https://origen.com', 'https://origen.com', array(
	'wp_user_roles' => serialize( array( 'administrator' => array( 'name' => 'Administrator', 'capabilities' => array( 'manage_options' => true ) ) ) ),
	'wp_page_for_privacy_policy' => '3',
	'upload_path' => '/var/www/origen/wp-content/uploads',
	'widget_text' => $widget,
	'digi_trampa' => $trap,
	'digi_json' => '{"a":"https:\/\/origen.com\/x","b":"https:\\\\\/\\\\\/origen.com\\\\\/y","c":"\/var\/www\/origen\/wp-content\/z"}',
	'digi_codificada' => 'https://pago.test/?vuelta=https%3A%2F%2Forigen.com%2Fgracias&b=https%3a%2f%2forigen.com%2fb',
	'digi_dañada' => $malformed,
	'digi_parecidos' => 'https://origen.com.mx/ https://miorigen.com/ https://origen.com:8443/ ftp://origen.com/ https://cdn.origen.com/a.png',
	'digi_redirecciones' => serialize( array( 'https://origen.com/vieja' => 'https://origen.com/nueva', 'http://origen.com/vieja' => 'otra' ) ),
	'admin_email' => 'admin@origen.com',
	'_transient_feed_1' => 'https://origen.com/feed',
	'rewrite_rules' => serialize( array( 'x' => 'index.php' ) ),
	'_elementor_assets_data' => serialize( array( 'css' => 'https://origen.com/a.css' ) ),
) );
$origin->seed( 'wp_posts', array(
	array( 'ID' => 1, 'post_title' => 'Inicio', 'post_content' => "Texto con 'comillas', \"dobles\", \\ barra, \0 nulo; <a href=\"https://origen.com/blog/\">blog</a> <img src=\"//origen.com/wp-content/uploads/a.jpg\"> visita origen.com o escribe a hola@origen.com", 'guid' => 'https://origen.com/?p=1', 'post_type' => 'page' ),
	array( 'ID' => 2, 'post_title' => 'Elementor', 'post_content' => '', 'guid' => 'https://origen.com/?page_id=2', 'post_type' => 'page' ),
) );
$origin->seed( 'wp_postmeta', array(
	array( 'post_id' => 2, 'meta_key' => '_elementor_data', 'meta_value' => $elementor ),
	array( 'post_id' => 2, 'meta_key' => '_elementor_css', 'meta_value' => serialize( array( 'status' => 'file' ) ) ),
	array( 'post_id' => 2, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/11/foto.jpg' ),
) );
$origin->seed( 'wp_users', array( array( 'ID' => 1, 'user_login' => 'admin', 'user_email' => 'admin@origen.com', 'user_url' => 'https://origen.com' ) ) );
$origin->seed( 'wp_usermeta', array(
	array( 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => serialize( array( 'administrator' => true ) ) ),
	array( 'user_id' => 1, 'meta_key' => 'wp_user_level', 'meta_value' => '10' ),
	array( 'user_id' => 1, 'meta_key' => 'nickname', 'meta_value' => 'admin' ),
) );
$origin->seed( 'wp_hijos', array( array( 'id' => 1, 'padre' => 1, 'nota' => 'https://origen.com/hijo' ) ) );
$origin->seed( 'otra_app', array( array( 'id' => 1, 'url' => 'https://origen.com/otra-aplicacion' ) ) );
// Archivos que restaurar: el CSS generado de Elementor (se borra) y el de las fuentes locales (se reescribe).
@mkdir( $abspath . '/wp-content/uploads/elementor/css', 0700, true );
@mkdir( $abspath . '/wp-content/uploads/elementor/google-fonts/css', 0700, true );
file_put_contents( $abspath . '/wp-content/uploads/elementor/css/post-2.css', '.e{background:url(https://origen.com/wp-content/uploads/fondo.jpg)}' );
file_put_contents( $abspath . '/wp-content/uploads/elementor/google-fonts/css/roboto.css', '@font-face{src:url(https://origen.com/wp-content/uploads/elementor/google-fonts/fonts/r.woff2)}' );
e1_use( $origin, 'https://origen.com' );
$single = e1_backup();

/* -------------------------------------------------------------------- E1: otro dominio y otra ruta, mismo prefijo */
e1_case( 'E1 sitio individual en otro dominio y otra ruta', function() use ( $single, $abspath, $malformed ) {
	$dest = e1_db( 'destino1', 'wp_' );
	e1_tables( $dest, 'wp_' );
	e1_options( $dest, 'wp_options', 'https://destino.mx/sitio', 'https://destino.mx/sitio' );
	e1_use( $dest, 'https://destino.mx/sitio' );
	$result = e1_restore( $single );
	e1_no_trap();
	$opt = function( $name ) use ( $dest ) { return $dest->value( 'wp_options', 'option_value', "option_name = '" . $name . "'" ); };
	e1_check( 'https://destino.mx/sitio' === $opt( 'home' ) && 'https://destino.mx/sitio' === $opt( 'siteurl' ), 'home/siteurl no quedaron con la dirección de este sitio: ' . $opt( 'home' ) );
	$content = $dest->value( 'wp_posts', 'post_content', 'ID = 1' );
	e1_check( false !== strpos( $content, 'href="https://destino.mx/sitio/blog/"' ) && false !== strpos( $content, 'src="//destino.mx/sitio/wp-content/uploads/a.jpg"' ), 'El contenido conserva la URL del origen: ' . $content );
	e1_check( false !== strpos( $content, "Texto con 'comillas', \"dobles\", \\ barra, \0 nulo;" ), 'Los caracteres especiales del contenido no sobrevivieron a la reescritura.' );
	e1_check( false !== strpos( $content, 'visita origen.com o escribe a hola@origen.com' ), 'Se cambió texto sin esquema o un correo.' );
	e1_check( 'https://origen.com/?p=1' === $dest->value( 'wp_posts', 'guid', 'ID = 1' ), 'Se cambió la columna guid.' );
	$widget = e1_unserializes( $opt( 'widget_text' ), 'widget_text' );
	e1_check( 'Año ñandú €' === $widget[2]['title'] && false !== strpos( $widget[2]['text'], 'https://destino.mx/sitio/contacto/' ) && false !== strpos( $widget[2]['text'], 'https://destino.mx/sitio/wp-content/uploads/foto.jpg' ), 'El serializado no se reescribió bien (www, http): ' . json_encode( $widget ) );
	$inner = e1_unserializes( $widget[2]['inner'], 'serializado dentro de serializado' );
	$deep = e1_unserializes( $inner['deep'], 'tercer nivel' );
	e1_check( 'https://destino.mx/sitio/€uro' === $inner['url'] && '//destino.mx/sitio/profundo' === $deep['x'], 'El serializado anidado no se reescribió: ' . json_encode( array( $inner, $deep ) ) );
	$trap = $opt( 'digi_trampa' );
	e1_check( 0 === strpos( $trap, 'O:11:"Digi_Trampa":1:{' ) && false !== strpos( $trap, 'https://destino.mx/sitio/trampa?x=ñ' ), 'El objeto serializado no se reescribió sin instanciarlo: ' . $trap );
	$object = e1_unserializes( $trap, 'objeto' );
	e1_check( $object instanceof __PHP_Incomplete_Class, 'El objeto reescrito no conserva su clase.' );
	e1_no_trap();
	$json = json_decode( $opt( 'digi_json' ), true );
	e1_check( is_array( $json ) && 'https://destino.mx/sitio/x' === $json['a'] && 'https:\/\/destino.mx\/sitio\/y' === $json['b'] && $abspath . '/wp-content/z' === $json['c'], 'JSON escapado (una y dos veces) o la ruta en JSON no se reescribieron: ' . $opt( 'digi_json' ) );
	$data = json_decode( $dest->value( 'wp_postmeta', 'meta_value', "meta_key = '_elementor_data'" ), true );
	e1_check( is_array( $data ) && 'https://destino.mx/sitio/wp-content/uploads/fondo.jpg' === $data[0]['settings']['background_image']['url'] && 'https://destino.mx/sitio/tienda/' === $data[0]['settings']['link']['url'] && false !== strpos( $data[0]['html'], 'https:\/\/destino.mx\/sitio\/doble' ), '_elementor_data no se reescribió: ' . json_encode( $data ) );
	e1_check( 'https://pago.test/?vuelta=https%3A%2F%2Fdestino.mx%2Fsitio%2Fgracias&b=https%3a%2f%2fdestino.mx%2fsitio%2fb' === $opt( 'digi_codificada' ), 'URL codificada: ' . $opt( 'digi_codificada' ) );
	e1_check( $malformed === $opt( 'digi_dañada' ), 'Un serializado dañado no quedó intacto.' );
	e1_check( 'https://origen.com.mx/ https://miorigen.com/ https://origen.com:8443/ ftp://origen.com/ https://cdn.origen.com/a.png' === $opt( 'digi_parecidos' ), 'Falso positivo: ' . $opt( 'digi_parecidos' ) );
	e1_check( $abspath . '/wp-content/uploads' === $opt( 'upload_path' ), 'La ruta del servidor no se reescribió: ' . $opt( 'upload_path' ) );
	$redirects = e1_unserializes( $opt( 'digi_redirecciones' ), 'redirecciones' );
	e1_check( array( 'https://destino.mx/sitio/vieja' => 'https://destino.mx/sitio/nueva', 'http://origen.com/vieja' => 'otra' ) === $redirects, 'Las claves del array: una URL cambia y la que chocaría se queda: ' . json_encode( $redirects ) );
	e1_check( 'admin@origen.com' === $opt( 'admin_email' ) && 'admin@origen.com' === $dest->value( 'wp_users', 'user_email', 'ID = 1' ), 'Se cambió un correo.' );
	e1_check( 'https://destino.mx/sitio' === $dest->value( 'wp_users', 'user_url', 'ID = 1' ), 'La web del usuario no se reescribió.' );
	e1_check( null === $opt( '_transient_feed_1' ) && null === $opt( 'rewrite_rules' ) && null === $opt( '_elementor_assets_data' ) && null === $dest->value( 'wp_postmeta', 'meta_value', "meta_key = '_elementor_css'" ), 'No se vaciaron transitorios, reglas de reescritura o la caché de Elementor.' );
	e1_check( ! file_exists( $abspath . '/wp-content/uploads/elementor/css/post-2.css' ), 'El CSS generado de Elementor no se borró.' );
	e1_check( false !== strpos( file_get_contents( $abspath . '/wp-content/uploads/elementor/google-fonts/css/roboto.css' ), 'url(https://destino.mx/sitio/wp-content/uploads/elementor/google-fonts/fonts/r.woff2)' ), 'El CSS de las fuentes locales no se reescribió.' );
	$report = $result['relocation'];
	e1_check( is_array( $report ) && 0 === $report['check']['urls'] && 0 === $report['check']['paths'] && 2 === $report['check']['guid'] && 2 === $report['check']['kept'] && $report['check']['emails'] >= 2 && ! empty( $report['tables']['wp_options']['rows'] ) && 1 === $report['tables']['wp_options']['malformed'], 'El informe o la comprobación final no son los esperados (0 restos; guid, el serializado dañado y la clave que chocaría, declarados): ' . json_encode( $report['check'] ?? $report ) );
	e1_check( false !== strpos( $result['notice'], 'Origen → este sitio' ) && false !== strpos( $result['notice'], '0 URL y 0 rutas del origen pendientes' ), 'El aviso no dice qué se reescribió: ' . $result['notice'] );
	e1_check( $GLOBALS['bk_options'][ Digitalisimo_Backups::LAST_RELOCATION ]['file'] === $single, 'No quedó el informe para la pantalla.' );
} );

/* --------------------------------------------- E2: otro prefijo, en una base de datos con otro WordPress con wp_ */
e1_case( 'E2 otro prefijo de tablas sin tocar otro WordPress de la misma base', function() use ( $single, $abspath ) {
	$dest = e1_db( 'destino2', 'dst_' );
	e1_tables( $dest, 'dst_' );
	e1_options( $dest, 'dst_options', 'https://destino.mx', 'https://destino.mx' );
	// Otro WordPress en la misma base de datos, con el prefijo que traía el origen: nada suyo puede cambiar.
	e1_tables( $dest, 'wp_' );
	e1_options( $dest, 'wp_options', 'https://otro.test', 'https://otro.test', array( 'blogname_otro' => 'Otro WordPress' ) );
	$dest->seed( 'wp_posts', array( array( 'ID' => 9, 'post_title' => 'Del otro', 'post_content' => 'https://origen.com/no-es-mio', 'guid' => 'x' ) ) );
	$before = array( $dest->rows( 'wp_options' ), $dest->rows( 'wp_posts' ), $dest->rows( 'wp_users' ), $dest->rows( 'wp_usermeta' ) );
	e1_use( $dest, 'https://destino.mx' );
	$verify = Digitalisimo_Backups::service_verify( $single );
	e1_check( ! is_wp_error( $verify ) && true === $verify['compatible'] && false !== strpos( $verify['relocation']['text'], 'Origen https://origen.com (prefijo wp_) → este sitio https://destino.mx (prefijo dst_)' ), 'verify no anunció «Origen → este sitio»: ' . json_encode( is_wp_error( $verify ) ? $verify->get_error_message() : $verify['relocation'] ) );
	$result = e1_restore( $single );
	e1_no_trap();
	e1_check( $before === array( $dest->rows( 'wp_options' ), $dest->rows( 'wp_posts' ), $dest->rows( 'wp_users' ), $dest->rows( 'wp_usermeta' ) ), 'Cambiaron tablas del otro WordPress (prefijo wp_).' );
	foreach ( array( 'dst_options', 'dst_posts', 'dst_postmeta', 'dst_users', 'dst_usermeta', 'dst_hijos', 'otra_app' ) as $table ) e1_check( in_array( $table, $dest->names(), true ), 'Falta la tabla ' . $table );
	e1_check( ! in_array( 'wp_hijos', $dest->names(), true ), 'Se creó una tabla con el prefijo del origen.' );
	e1_check( false !== strpos( $dest->create_sql( 'dst_hijos' ), 'REFERENCES `dst_posts`' ), 'La clave foránea sigue apuntando al prefijo del origen: ' . $dest->create_sql( 'dst_hijos' ) );
	e1_check( 'https://destino.mx' === $dest->value( 'dst_options', 'option_value', "option_name = 'home'" ), 'home no quedó con la dirección de este sitio.' );
	e1_check( null !== $dest->value( 'dst_options', 'option_value', "option_name = 'dst_user_roles'" ) && null === $dest->value( 'dst_options', 'option_value', "option_name = 'wp_user_roles'" ), 'user_roles no tomó el prefijo nuevo.' );
	e1_check( '3' === $dest->value( 'dst_options', 'option_value', "option_name = 'wp_page_for_privacy_policy'" ), 'Se renombró una opción del núcleo que empieza por wp_ sin ser del prefijo.' );
	$keys = array_column( $dest->rows( 'dst_usermeta' ), 'meta_key' );
	sort( $keys );
	e1_check( array( 'dst_capabilities', 'dst_user_level', 'nickname' ) === $keys, 'Claves de usermeta: ' . implode( ', ', $keys ) );
	e1_check( 'https://origen.com/otra-aplicacion' === $dest->value( 'otra_app', 'url', 'id = 1' ), 'Se reescribió una tabla ajena a esta instalación.' );
	e1_check( 'https://destino.mx/hijo' === $dest->value( 'dst_hijos', 'nota', 'id = 1' ), 'Una tabla de un plugin no se reescribió.' );
	e1_check( 0 === $result['relocation']['check']['urls'] && ! empty( $result['relocation']['tables']['dst_usermeta']['keys'] ), 'Informe de prefijo: ' . json_encode( $result['relocation']['tables'] ) );
	// Conservar las direcciones del respaldo: el prefijo y las rutas del servidor siguen al destino; las URL no cambian.
	$result = e1_restore( $single, array( 'addresses' => 'backup' ) );
	e1_check( 'https://origen.com' === $dest->value( 'dst_options', 'option_value', "option_name = 'home'" ) && false !== strpos( $dest->value( 'dst_posts', 'post_content', 'ID = 1' ), 'https://origen.com/blog/' ), 'Con «conservar las del respaldo» cambiaron las URL.' );
	e1_check( null !== $dest->value( 'dst_options', 'option_value', "option_name = 'dst_user_roles'" ) && $abspath . '/wp-content/uploads' === $dest->value( 'dst_options', 'option_value', "option_name = 'upload_path'" ), 'Con «conservar las del respaldo» no se cambiaron prefijo o rutas.' );
	e1_check( 'backup' === $result['relocation']['addresses'] && false !== strpos( $result['relocation']['text'], 'Se conservan las direcciones del respaldo' ), 'El informe no dice que se conservaron las direcciones del respaldo.' );
	$bad = Digitalisimo_Backups::service_restore( $single, array( 'confirm' => 'RESTAURAR SIN FIRMA', 'addresses' => 'otra' ) );
	e1_check( is_wp_error( $bad ) && 'digitalisimo_backups_addresses' === $bad->get_error_code() && 400 === $bad->get_error_data()['status'], 'Un valor de addresses inválido no se rechazó.' );
} );

/* ---------------------------------------------------------------------------------------- E3 y E4: redes */
/** Red de origen con el sitio principal, un subsitio y, si se pide, un sitio con dominio propio. */
function e1_network_origin( $name, $subdomain ) {
	$db = e1_db( $name, 'wp_' );
	e1_tables( $db, 'wp_' );
	e1_tables( $db, 'wp_', '2_', false );
	$db->pdo->exec( 'CREATE TABLE `wp_blogs` (`blog_id` INTEGER PRIMARY KEY, `site_id` bigint(20) NOT NULL DEFAULT 1, `domain` varchar(200) NOT NULL, `path` varchar(100) NOT NULL)' );
	$db->pdo->exec( 'CREATE TABLE `wp_site` (`id` INTEGER PRIMARY KEY, `domain` varchar(200) NOT NULL, `path` varchar(100) NOT NULL)' );
	$db->pdo->exec( 'CREATE TABLE `wp_sitemeta` (`meta_id` INTEGER PRIMARY KEY, `site_id` bigint(20) NOT NULL DEFAULT 1, `meta_key` varchar(255), `meta_value` longtext)' );
	$sub = $subdomain ? 'https://uno.origen.com' : 'https://origen.com/uno';
	e1_options( $db, 'wp_options', 'https://origen.com', 'https://origen.com', array( 'wp_user_roles' => serialize( array( 'administrator' => array() ) ) ) );
	e1_options( $db, 'wp_2_options', $sub, $sub, array( 'wp_2_user_roles' => serialize( array( 'editor' => array() ) ), 'enlaces' => serialize( array( 'raiz' => 'https://origen.com/', 'propio' => $sub . '/pagina', 'cdn' => 'https://cdn.origen.com/x.png', 'cliente' => 'https://cliente.com/' ) ) ) );
	$db->seed( 'wp_2_posts', array( array( 'ID' => 1, 'post_title' => 'Uno', 'post_content' => '<a href="' . $sub . '/hola/">hola</a> <a href="https://origen.com/">red</a>', 'guid' => $sub . '/?p=1' ) ) );
	$blogs = array( array( 'blog_id' => 1, 'site_id' => 1, 'domain' => 'origen.com', 'path' => '/' ), array( 'blog_id' => 2, 'site_id' => 1, 'domain' => $subdomain ? 'uno.origen.com' : 'origen.com', 'path' => $subdomain ? '/' : '/uno/' ) );
	if ( $subdomain ) $blogs[] = array( 'blog_id' => 3, 'site_id' => 1, 'domain' => 'cliente.com', 'path' => '/' );
	$db->seed( 'wp_blogs', $blogs );
	$db->seed( 'wp_site', array( array( 'id' => 1, 'domain' => 'origen.com', 'path' => '/' ) ) );
	$db->seed( 'wp_sitemeta', array( array( 'site_id' => 1, 'meta_key' => 'siteurl', 'meta_value' => 'https://origen.com/' ), array( 'site_id' => 1, 'meta_key' => 'subdomain_install', 'meta_value' => $subdomain ? '1' : '0' ), array( 'site_id' => 1, 'meta_key' => '_site_transient_x', 'meta_value' => 'https://origen.com/' ) ) );
	$db->seed( 'wp_users', array( array( 'ID' => 1, 'user_login' => 'admin', 'user_email' => 'admin@origen.com', 'user_url' => '' ) ) );
	$db->seed( 'wp_usermeta', array( array( 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:0:{}' ), array( 'user_id' => 1, 'meta_key' => 'wp_2_capabilities', 'meta_value' => 'a:0:{}' ) ) );
	e1_use( $db, 'https://origen.com', array( 'domain' => 'origen.com', 'path' => '/' ), $subdomain );
	return e1_backup();
}
function e1_network_destination( $name, $prefix, array $network, $subdomain ) {
	$db = e1_db( $name, $prefix );
	e1_tables( $db, $prefix );
	e1_options( $db, $prefix . 'options', 'https://' . $network['domain'] . rtrim( $network['path'], '/' ), 'https://' . $network['domain'] . rtrim( $network['path'], '/' ) );
	e1_use( $db, 'https://' . $network['domain'] . rtrim( $network['path'], '/' ), $network, $subdomain );
	return $db;
}
e1_case( 'E3 red por subdirectorios a otro dominio, otra ruta y otro prefijo', function() {
	$file = e1_network_origin( 'red-dir', false );
	$dest = e1_network_destination( 'red-dir-destino', 'red_', array( 'domain' => 'destino.mx', 'path' => '/red/' ), false );
	$result = e1_restore( $file );
	$blogs = $dest->rows( 'red_blogs' );
	e1_check( array( array( '1', 'destino.mx', '/red/' ), array( '2', 'destino.mx', '/red/uno/' ) ) === array_map( function( $b ) { return array( (string) $b['blog_id'], $b['domain'], $b['path'] ); }, $blogs ), 'blogs: ' . json_encode( $blogs ) );
	e1_check( 'destino.mx' === $dest->value( 'red_site', 'domain', 'id = 1' ) && '/red/' === $dest->value( 'red_site', 'path', 'id = 1' ), 'La tabla site no tomó el dominio y la ruta nuevos.' );
	e1_check( 'https://destino.mx/red/' === $dest->value( 'red_sitemeta', 'meta_value', "meta_key = 'siteurl'" ), 'sitemeta siteurl: ' . $dest->value( 'red_sitemeta', 'meta_value', "meta_key = 'siteurl'" ) );
	e1_check( 'https://destino.mx/red' === $dest->value( 'red_options', 'option_value', "option_name = 'home'" ) && 'https://destino.mx/red/uno' === $dest->value( 'red_2_options', 'option_value', "option_name = 'home'" ) && 'https://destino.mx/red/uno' === $dest->value( 'red_2_options', 'option_value', "option_name = 'siteurl'" ), 'home de cada sitio.' );
	e1_check( '<a href="https://destino.mx/red/uno/hola/">hola</a> <a href="https://destino.mx/red/">red</a>' === $dest->value( 'red_2_posts', 'post_content', 'ID = 1' ), 'Contenido del subsitio: ' . $dest->value( 'red_2_posts', 'post_content', 'ID = 1' ) );
	$links = e1_unserializes( $dest->value( 'red_2_options', 'option_value', "option_name = 'enlaces'" ), 'enlaces' );
	e1_check( 'https://destino.mx/red/' === $links['raiz'] && 'https://destino.mx/red/uno/pagina' === $links['propio'] && 'https://cdn.origen.com/x.png' === $links['cdn'], 'Enlaces serializados del subsitio: ' . json_encode( $links ) );
	e1_check( null !== $dest->value( 'red_2_options', 'option_value', "option_name = 'red_2_user_roles'" ) && null !== $dest->value( 'red_options', 'option_value', "option_name = 'red_user_roles'" ), 'user_roles de cada sitio sin el prefijo nuevo.' );
	$keys = array_column( $dest->rows( 'red_usermeta' ), 'meta_key' );
	sort( $keys );
	e1_check( array( 'red_2_capabilities', 'red_capabilities' ) === $keys, 'usermeta de la red: ' . implode( ', ', $keys ) );
	e1_check( null === $dest->value( 'red_sitemeta', 'meta_value', "meta_key = '_site_transient_x'" ), 'No se vaciaron los transitorios de la red.' );
	e1_check( 'https://origen.com/uno/?p=1' === $dest->value( 'red_2_posts', 'guid', 'ID = 1' ), 'Se cambió guid en un subsitio.' );
	e1_check( 0 === $result['relocation']['check']['urls'] && 2 === (int) ( $result['relocation']['network']['blogs'] ?? 0 ), 'Informe de la red: ' . json_encode( $result['relocation']['network'] + array( 'check' => $result['relocation']['check'] ) ) );
} );
e1_case( 'E4 red por subdominios: x.origen.com → x.destino.com', function() {
	$file = e1_network_origin( 'red-sub', true );
	$dest = e1_network_destination( 'red-sub-destino', 'wp_', array( 'domain' => 'destino.mx', 'path' => '/' ), true );
	$result = e1_restore( $file );
	$blogs = array_map( function( $b ) { return $b['domain'] . $b['path']; }, $dest->rows( 'wp_blogs' ) );
	e1_check( array( 'destino.mx/', 'uno.destino.mx/', 'cliente.com/' ) === $blogs, 'blogs: ' . json_encode( $blogs ) );
	e1_check( 'https://uno.destino.mx' === $dest->value( 'wp_2_options', 'option_value', "option_name = 'home'" ), 'home del subdominio: ' . $dest->value( 'wp_2_options', 'option_value', "option_name = 'home'" ) );
	e1_check( '<a href="https://uno.destino.mx/hola/">hola</a> <a href="https://destino.mx/">red</a>' === $dest->value( 'wp_2_posts', 'post_content', 'ID = 1' ), 'Contenido: ' . $dest->value( 'wp_2_posts', 'post_content', 'ID = 1' ) );
	$links = e1_unserializes( $dest->value( 'wp_2_options', 'option_value', "option_name = 'enlaces'" ), 'enlaces' );
	e1_check( 'https://cdn.origen.com/x.png' === $links['cdn'] && 'https://cliente.com/' === $links['cliente'] && 'https://uno.destino.mx/pagina' === $links['propio'], 'Un subdominio ajeno o un dominio propio cambiaron: ' . json_encode( $links ) );
	e1_check( 0 === $result['relocation']['check']['urls'], 'Comprobación: ' . json_encode( $result['relocation']['check'] ) );
} );

/* ---------------------------------------------------------------------------------- E5: comprobaciones previas */
e1_case( 'E5 comprobaciones previas con qué cambiar, antes de tocar nada', function() {
	$file = e1_network_origin( 'red-sub-2', true );
	$dest = e1_network_destination( 'red-dir-2', 'wp_', array( 'domain' => 'destino.mx', 'path' => '/' ), false );
	$before = array( $dest->names(), $dest->rows( 'wp_options' ) );
	$copies = count( glob( bk_storage() . '/safety-before-restore-*' ) ?: array() );
	$error = Digitalisimo_Backups::service_restore( $file, array( 'confirm' => 'RESTAURAR SIN FIRMA', 'network' => true ) );
	e1_check( is_wp_error( $error ) && 422 === $error->get_error_data()['status'] && false !== strpos( $error->get_error_message(), 'El respaldo es una red por subdominios y esta red es por subdirectorios' ) && false !== strpos( $error->get_error_message(), 'SUBDOMAIN_INSTALL como true' ), 'Red de otro tipo: ' . ( is_wp_error( $error ) ? $error->get_error_message() : 'se restauró' ) );
	e1_check( $before === array( $dest->names(), $dest->rows( 'wp_options' ) ) && count( glob( bk_storage() . '/safety-before-restore-*' ) ?: array() ) === $copies, 'El rechazo tocó la base de datos o creó una copia previa.' );
	$origin = array( 'home' => 'https://origen.com', 'prefix' => 'wp_', 'multisite' => true, 'subdomain' => false, 'network' => array( 'domain' => 'origen.com', 'path' => '/' ), 'networks' => array( 1 => array( 'domain' => 'origen.com', 'path' => '/' ) ), 'sites' => array( 1 => array( 'domain' => 'origen.com', 'path' => '/' ) ) );
	$destination = array( 'home' => 'https://destino.mx', 'prefix' => 'wp_', 'multisite' => true, 'subdomain' => false, 'network' => array( 'domain' => 'destino.mx', 'path' => '/' ), 'config' => array( 'DOMAIN_CURRENT_SITE' => 'origen.com', 'PATH_CURRENT_SITE' => '/' ) );
	$plan = Digitalisimo_Backups_Relocate::plan( $origin, $destination );
	e1_check( 1 === count( $plan->problems() ) && false !== strpos( $plan->problems()[0], "DOMAIN_CURRENT_SITE debe ser 'destino.mx' y PATH_CURRENT_SITE '/' (hoy son 'origen.com' y '/')" ), 'wp-config.php con el dominio anterior: ' . json_encode( $plan->problems() ) );
	$plan = Digitalisimo_Backups_Relocate::plan( $origin, $destination, array( 'addresses' => 'backup' ) );
	e1_check( ! $plan->problems(), 'Conservar las direcciones del respaldo con wp-config.php declarándolas no debe fallar: ' . json_encode( $plan->problems() ) );
	$destination['config'] = array( 'DOMAIN_CURRENT_SITE' => 'destino.mx', 'PATH_CURRENT_SITE' => '/', 'BLOG_ID_CURRENT_SITE' => 5 );
	$plan = Digitalisimo_Backups_Relocate::plan( $origin, $destination );
	e1_check( $plan->problems() && false !== strpos( implode( ' ', $plan->problems() ), 'BLOG_ID_CURRENT_SITE es 5' ), 'BLOG_ID_CURRENT_SITE inexistente.' );
	$plan = Digitalisimo_Backups_Relocate::plan( $origin, array( 'home' => 'https://destino.mx', 'prefix' => 'wp_', 'multisite' => false ) );
	e1_check( false !== strpos( implode( ' ', $plan->problems() ), 'convierte esta instalación en una red por subdirectorios' ), 'Red en un sitio individual: ' . json_encode( $plan->problems() ) );
	$plan = Digitalisimo_Backups_Relocate::plan( array( 'home' => 'https://a.test', 'prefix' => 'wp_' ), array( 'home' => 'https://b.test', 'prefix' => 'x_' ), array( 'tables' => null ) );
	e1_check( false !== strpos( implode( ' ', $plan->problems() ), 'no lista sus tablas' ), 'Formato sin lista de tablas con otro prefijo.' );
	$plan = Digitalisimo_Backups_Relocate::plan( array( 'home' => 'https://a.test', 'prefix' => 'wp_' ), array( 'home' => 'https://b.test', 'prefix' => 'x_' ), array( 'tables' => array( 'wp_posts', 'x_posts' ) ) );
	e1_check( false !== strpos( implode( ' ', $plan->problems() ), 'acabarían como «x_posts»' ), 'Tablas que chocarían al cambiar el prefijo.' );
} );

/* ----------------------------------------------------------------- E6: reescritor, guardián y base ya importada */
e1_case( 'E6 reescritor: límites, estilos, serializados y objetos', function() {
	$r = new Digitalisimo_Backups_Relocate();
	e1_check( false === $r->add_url( 'origen.com', 'https://b.test' ) && false === $r->add_path( 'relativa', '/x' ), 'Se aceptó un par no válido.' );
	$r->add_url( 'http://www.origen.com/blog', 'https://destino.mx' );
	$r->add_path( '/var/www/origen', '/srv/destino' );
	$cases = array(
		'https://origen.com/blog/x' => 'https://destino.mx/x',
		'HTTPS://WWW.ORIGEN.COM/blog' => 'https://destino.mx',
		'//origen.com/blog?x=1' => '//destino.mx?x=1',
		'https://origen.com/blogs y https://origen.com/blog.html y https://origen.com/' => 'https://origen.com/blogs y https://origen.com/blog.html y https://origen.com/',
		'Fin en https://origen.com/blog.' => 'Fin en https://destino.mx.',
		'"https:\/\/origen.com\/blog\/a"' => '"https:\/\/destino.mx\/a"',
		'x%3A%2F%2Forigen.com%2Fblog%2Fa https%3A%2F%2Forigen.com%2Fblog%2Fa' => 'x%3A%2F%2Forigen.com%2Fblog%2Fa https%3A%2F%2Fdestino.mx%2Fa',
		'/var/www/origen/a /var/www/origen2 /x/var/www/origen /var/www/origen.bak \/var\/www\/origen\/b' => '/srv/destino/a /var/www/origen2 /x/var/www/origen /var/www/origen.bak \/srv\/destino\/b',
		'mailto:hola@origen.com' => 'mailto:hola@origen.com',
	);
	foreach ( $cases as $in => $out ) e1_check( $out === $r->rewrite( $in ), 'rewrite(' . $in . ') = ' . $r->rewrite( $in ) );
	$long = serialize( array( 'k' => str_repeat( 'ñ', 3 ) . 'https://origen.com/blog/€' ) );
	$expected = 'ñññhttps://destino.mx/€';
	e1_check( 'a:1:{s:1:"k";s:' . strlen( $expected ) . ':"' . $expected . '";}' === $r->rewrite( $long ) && 28 === strlen( $expected ), 'Longitud en bytes con multibyte: ' . $r->rewrite( $long ) );
	$payload = 'x:i:0;s:23:"https://origen.com/blog";';
	$opaque = 'C:11:"ArrayObject":' . strlen( $payload ) . ':{' . $payload . '}';
	e1_check( $opaque === $r->rewrite( $opaque ) && ! empty( $r->flags()['opaque'] ), 'Un objeto C: (formato propio de su clase) debe copiarse igual e informarse.' );
	$nested = 'https://origen.com/blog';
	for ( $i = 0; $i < 20; $i++ ) $nested = serialize( array( $nested ) );
	e1_check( $nested === $r->rewrite( $nested ) && ! empty( $r->flags()['deep'] ), 'Más de 16 serializados anidados no se recorren.' );
	$excess = 'a:1:{s:1:"x";' . str_repeat( 'a:1:{i:0;', 600 ) . 's:23:"https://origen.com/blog";' . str_repeat( '}', 601 );
	e1_check( $excess === $r->rewrite( $excess ) && ! empty( $r->flags()['malformed'] ), 'Un anidamiento excesivo debe dejarse intacto e informarse.' );
	$small = new Digitalisimo_Backups_Relocate( array( 'max_value' => 64 ) );
	$small->add_url( 'https://origen.com', 'https://destino.mx' );
	$big = str_repeat( 'x', 80 ) . 'https://origen.com';
	e1_check( $big === $small->rewrite( $big ) && ! empty( $small->flags()['too_large'] ), 'Un valor mayor que max_value debe quedar intacto e informarse.' );
	$check = new Digitalisimo_Backups_Relocate();
	$check->add_url( 'https://origen.com', 'https://destino.mx' );
	$text = 'https://origen.com/x hola@origen.com origen.com https://origen.com.mx';
	e1_check( array( 'urls' => 1, 'paths' => 0, 'kept' => 0, 'emails' => 1, 'other' => 1 ) === $check->classify( $text ), 'classify(): ' . json_encode( $check->classify( $text ) ) );
	e1_check( array( 'urls' => 0, 'paths' => 0, 'kept' => 1, 'emails' => 0, 'other' => 0 ) === $check->classify( 'a:1:{s:1:"u";s:9:"https://origen.com/x";}' ), 'Un serializado dañado cuenta como «kept».' );
	$same = new Digitalisimo_Backups_Relocate();
	$same->add_url( 'https://a.test', 'https://a.test/staging' );
	e1_check( 'https://a.test/staging/x' === $same->rewrite( 'https://a.test/x' ) && 0 === $same->classify( 'https://a.test/staging/x' )['urls'], 'Mismo host con otra ruta: la URL del destino no cuenta como resto del origen.' );
	$keys = new Digitalisimo_Backups_Relocate();
	$keys->set_prefixes( array( 'wp_2_' => 'n_', 'wp_' => 'n_' ), 'n_' );
	e1_check( 'n_capabilities' === $keys->rename_key( 'wp_2_capabilities', 'usermeta' ) && 'n_user_roles' === $keys->rename_key( 'wp_2_user_roles', 'options' ) && 'wp_2_otra' === $keys->rename_key( 'wp_2_otra', 'options' ) && 'wp_x' === $keys->rename_key( 'wp_x', 'postmeta' ), 'Claves con prefijo (extraer un sitio).' );
	e1_check( array( 'wp_2_posts' => 'n_posts', 'wp_users' => 'n_users', 'otra' => 'otra' ) === $keys->table_map( array( 'wp_2_posts', 'wp_users', 'otra' ) ), 'Mapa de tablas por prefijos.' );
	e1_no_trap();
} );
e1_case( 'E6 guardián: renombra por identificador y rechaza lo que no puede renombrar', function() {
	$guard = function( $trusted ) { return ( new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, $trusted, array( 'wp_a', 'wp_b', 'v' ) ) )->rename_tables( array( 'wp_a' => 'n_a', 'wp_b' => 'n_b' ) ); };
	$run = function( $guard, $sql ) { $out = array(); $guard->scan( function() use ( &$sql ) { $chunk = $sql; $sql = null; return $chunk; }, function( $s ) use ( &$out ) { $out[] = $s; } ); return $out; };
	$out = $run( $guard( false ), "DROP TABLE IF EXISTS `wp_a`;\nCREATE TABLE `wp_a` (`id` int, `b` int REFERENCES `wp_b` (`id`), `c` text COMMENT 'wp_a `wp_b`');\nINSERT INTO `wp_a` (`id`,`c`) VALUES ('1','`wp_a`');" );
	e1_check( array( 'DROP TABLE IF EXISTS `n_a`', "CREATE TABLE `n_a` (`id` int, `b` int REFERENCES `n_b` (`id`), `c` text COMMENT 'wp_a `wp_b`')", "INSERT INTO `n_a` (`id`,`c`) VALUES ('1','`wp_a`')" ) === $out, 'Renombrado: ' . json_encode( $out ) );
	foreach ( array( "CREATE ALGORITHM=UNDEFINED VIEW `v` AS select 1" => 'vista', 'CREATE TABLE `wp_a` (`id` int /*!50100 REFERENCES `wp_b` */)' => 'clave foránea dentro' ) as $sql => $needle ) {
		bk_expect_failure( $needle, function() use ( $run, $guard, $sql ) { $run( $guard( true ), $sql . ';' ); }, 'no' );
	}
	// Sin renombrado, el guardián acepta y ejecuta exactamente lo mismo que antes.
	$plain = new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, true, array( 'wp_a' ) );
	$sql = "INSERT INTO `wp_a` (`x`) VALUES ('a\\'b');";
	e1_check( array( "INSERT INTO `wp_a` (`x`) VALUES ('a\\'b')" ) === $run( $plain, $sql ), 'Sin renombrado cambió la sentencia.' );
	// El literal reescrito vuelve con los escapes de MySQL y el resto de la sentencia no cambia.
	$plan = Digitalisimo_Backups_Relocate::plan( array( 'home' => 'https://origen.com', 'prefix' => 'wp_' ), array( 'home' => 'https://destino.mx', 'prefix' => 'wp_' ) );
	$g = $plan->attach( new Digitalisimo_Backups_Sql_Guard( Digitalisimo_Backups_Sql_Guard::EXPORTER, false, array( 'wp_posts' ) ), array( 'wp_posts' ) );
	$out = $run( $g, "INSERT INTO `wp_posts` (`ID`,`post_content`,`guid`) VALUES ('1','it\\'s \\\\ https://origen.com/a \\0 \"q\"','https://origen.com/?p=1');" );
	e1_check( array( "INSERT INTO `wp_posts` (`ID`,`post_content`,`guid`) VALUES ('1','it\\'s \\\\ https://destino.mx/a \\0 \\\"q\\\"','https://origen.com/?p=1')" ) === $out, 'Literal reescrito: ' . json_encode( $out ) );
} );
e1_case( 'E6 base ya importada: lotes por clave primaria, compuesta, sin clave y BLOB', function() {
	$db = e1_db( 'importada', 'n_' );
	$db->pdo->exec( 'CREATE TABLE `n_postmeta` (`meta_id` INTEGER PRIMARY KEY, `meta_key` varchar(255), `meta_value` longtext)' );
	$db->pdo->exec( 'CREATE TABLE `n_term_relationships` (`object_id` bigint(20) NOT NULL, `term_taxonomy_id` bigint(20) NOT NULL, `nota` text, PRIMARY KEY (`object_id`, `term_taxonomy_id`))' );
	$db->pdo->exec( 'CREATE TABLE `n_sinclave` (`v` text)' );
	$db->pdo->exec( 'CREATE TABLE `n_binario` (`id` INTEGER PRIMARY KEY, `datos` longblob)' );
	$db->pdo->exec( 'CREATE TABLE `n_posts` (`ID` INTEGER PRIMARY KEY, `guid` varchar(255), `post_content` longtext)' );
	$db->pdo->exec( 'CREATE TABLE `n_usermeta` (`umeta_id` INTEGER PRIMARY KEY, `user_id` bigint(20), `meta_key` varchar(255), `meta_value` longtext)' );
	$db->pdo->beginTransaction();
	for ( $i = 1; $i <= 1203; $i++ ) $db->seed( 'n_postmeta', array( array( 'meta_id' => $i, 'meta_key' => 'k', 'meta_value' => 0 === $i % 3 ? serialize( array( 'u' => 'https://origen.com/' . $i ) ) : 'nada ' . $i ) ) );
	for ( $i = 1; $i <= 3; $i++ ) for ( $j = 1; $j <= 250; $j++ ) $db->seed( 'n_term_relationships', array( array( 'object_id' => $i, 'term_taxonomy_id' => $j, 'nota' => 'https://origen.com/t/' . $i . '/' . $j ) ) );
	$db->pdo->commit();
	$db->seed( 'n_sinclave', array( array( 'v' => 'https://origen.com/sin-clave' ) ) );
	$db->seed( 'n_binario', array( array( 'id' => 1, 'datos' => "\xff\xfe\x01" . str_repeat( 'B', 5 * 1048576 ) . 'https://origen.com/blob' ), array( 'id' => 2, 'datos' => 'https://origen.com/b2' ) ) );
	$db->seed( 'n_posts', array( array( 'ID' => 1, 'guid' => 'https://origen.com/?p=1', 'post_content' => 'https://origen.com/p' ) ) );
	$db->seed( 'n_usermeta', array( array( 'user_id' => 1, 'meta_key' => 'wp_capabilities', 'meta_value' => 'a:0:{}' ) ) );
	$GLOBALS['wpdb'] = $db;
	$plan = Digitalisimo_Backups_Relocate::plan( array( 'home' => 'https://origen.com', 'prefix' => 'wp_' ), array( 'home' => 'https://destino.mx', 'prefix' => 'n_' ) );
	$tables = array( 'n_postmeta', 'n_term_relationships', 'n_sinclave', 'n_binario', 'n_posts', 'n_usermeta' );
	$report = $plan->rewrite_tables( $tables );
	e1_check( 401 === $report['n_postmeta']['rows'] && 750 === $report['n_term_relationships']['rows'] && 1 === $report['n_sinclave']['no_primary_key'] && 2 === $report['n_binario']['rows'] && 1 === $report['n_usermeta']['keys'], 'Informe por tabla: ' . json_encode( $report ) );
	$value = e1_unserializes( $db->value( 'n_postmeta', 'meta_value', 'meta_id = 1203' ), 'postmeta' );
	e1_check( 'https://destino.mx/1203' === $value['u'] && 'nada 1202' === $db->value( 'n_postmeta', 'meta_value', 'meta_id = 1202' ), 'postmeta reescrita: ' . json_encode( $value ) );
	e1_check( 'https://destino.mx/t/3/250' === $db->value( 'n_term_relationships', 'nota', 'object_id = 3 AND term_taxonomy_id = 250' ), 'Clave primaria compuesta.' );
	$blob = $db->value( 'n_binario', 'datos', 'id = 1' );
	e1_check( "\xff\xfe\x01" === substr( $blob, 0, 3 ) && 'https://destino.mx/blob' === substr( $blob, -23 ) && 5 * 1048576 + 26 === strlen( $blob ), 'BLOB grande (5 MiB, UTF-8 no válido).' );
	e1_check( 'https://origen.com/?p=1' === $db->value( 'n_posts', 'guid', 'ID = 1' ) && 'https://destino.mx/p' === $db->value( 'n_posts', 'post_content', 'ID = 1' ), 'guid en la pasada sobre base importada.' );
	e1_check( 'n_capabilities' === $db->value( 'n_usermeta', 'meta_key', 'user_id = 1' ), 'Clave de usermeta en la pasada sobre base importada.' );
	$left = $plan->leftovers( $tables );
	e1_check( 1 === $left['urls'] && 1 === $left['guid'] && array( 'n_sinclave', 'n_posts' ) === array_keys( $left['tables'] ) && 1 === $left['tables']['n_sinclave']['urls'], 'leftovers() (sólo queda la tabla sin clave primaria, que se informa): ' . json_encode( $left ) );
} );

bk_cleanup();
foreach ( glob( $GLOBALS['bk_root'] . '-*' ) ?: array() as $file ) @unlink( $file );
if ( $failures ) { echo "DIGITALÍSIMO Backups: restaurar en otro dominio con " . $failures . " fallos.\n"; exit( 1 ); }
echo "DIGITALÍSIMO Backups: restaurar en otro dominio, otra ruta y otro prefijo (sitio individual, red por subdirectorios y por subdominios) con datos serializados seguros.\n";
