<?php
/**
 * A4 de la auditoría SEO (2026-11-24): el monitor de 404 no escribe en la base en cada 404, sin WordPress.
 *   - Una visita a un 404 sólo añade una línea a un búfer en disco con tope: con el búfer lleno se descarta (un bot que
 *     barre miles de URLs no satura nada) y sin la carpeta del búfer no se anota nada; nunca hay consulta.
 *   - El volcado agrega por URI (visitas, primera y última vez, última procedencia) y sólo las más visitadas.
 *   - El búfer es un .php que se corta en su primera línea: no se puede leer desde la web.
 *   - Para cada URL se propone un destino a partir de su último tramo.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array( 'seo_404_enabled' => 1, 'seo_404_ignore_query' => 1, 'seo_404_exclude' => '' );
$GLOBALS['dir'] = sys_get_temp_dir() . '/digi-404-' . getmypid();
@mkdir( $GLOBALS['dir'] . '/uploads', 0777, true );
function add_action() {}
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_upload_dir( $time = null, $create = true ) { return array( 'basedir' => $GLOBALS['dir'] . '/uploads' ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function wp_salt( $scheme ) { return 'sal-de-prueba'; }
function get_current_blog_id() { return 1; }
function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function is_404() { return true; }
function wp_doing_ajax() { return false; }
function get_option( $key, $default = false ) { return 'digitalisimo_seo_404_db' === $key ? 1 : $default; }
function wp_unslash( $value ) { return $value; }
function esc_url_raw( $url ) { return (string) $url; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_title( $value ) { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) ); }
function absint( $value ) { return abs( (int) $value ); }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $default = null ) { return $GLOBALS['options'][ $key ] ?? $default; } }
/** Cualquier consulta durante una visita es una falla. */
class Digi_No_DB { public function __call( $name, $args ) { throw new RuntimeException( 'Una visita a un 404 tocó la base de datos: ' . $name ); } }
$GLOBALS['wpdb'] = new Digi_No_DB();
require __DIR__ . '/../digitalisimo-seo/includes/class-404-monitor.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$m = Digitalisimo_Integrations_404_Monitor::class;

$file = $m::buffer_file();
check( '.php' === substr( $file, -4 ) && false === strpos( basename( $file ), 'sal' ), 'El búfer es un .php con nombre no adivinable.' );

// Sin la carpeta del búfer (el escritorio aún no la creó), la visita no anota nada.
$_SERVER['REQUEST_URI'] = '/buen-fin/';
$m::record();
check( ! file_exists( $file ), 'Sin carpeta no se crea nada en una visita.' );

// Con la carpeta: cada visita es una línea en disco, ninguna consulta.
mkdir( dirname( $file ), 0777, true );
foreach ( array( '/buen-fin/', '/buen-fin/?utm_source=x', '/contacto/' ) as $uri ) { $_SERVER['REQUEST_URI'] = $uri; $_SERVER['HTTP_REFERER'] = '/contacto/' === $uri ? 'https://google.test/' : ''; $m::record(); }
$text = file_get_contents( $file );
check( 0 === strpos( $text, "<?php exit; ?>\n" ), 'La primera línea corta la ejecución: el búfer no se lee desde la web.' );
check( 4 === count( array_filter( explode( "\n", $text ) ) ), 'Una línea por visita más la cabecera.' );

// Agregación por URI.
$rows = array_values( $m::aggregate( $text ) );
check( 2 === count( $rows ) && '/buen-fin/' === $rows[0]['uri'] && 2 === $rows[0]['hits'] && 1 === $rows[1]['hits'] && 'https://google.test/' === $rows[1]['referer'], 'Se agrega por URI, la más visitada primero: ' . json_encode( $rows ) );
$many = '';
for ( $i = 0; $i < 30; $i++ ) $many .= $m::buffer_line( '/bot-' . $i, '', 'bot', 100 + $i );
$many .= $m::buffer_line( '/bot-7', '', 'bot', 200 );
$top = $m::aggregate( $many, 5 );
check( 5 === count( $top ) && '/bot-7' === reset( $top )['uri'] && 100 + 7 === reset( $top )['first'] && 200 === reset( $top )['last'], 'Por volcado sólo entran las más visitadas, con su primera y última vez.' );
check( array() === $m::aggregate( "<?php exit; ?>\nbasura\n{\"x\":1}\n" ), 'Las líneas que no son de una visita se ignoran.' );

// Tope: con el búfer lleno, la visita se descarta sin crecer el archivo.
file_put_contents( $file, str_repeat( 'x', $m::BUFFER_MAX ) );
$_SERVER['REQUEST_URI'] = '/otra/';
$m::record();
check( $m::BUFFER_MAX === filesize( $file ), 'Con el búfer lleno no se anota nada más.' );

// Apagado, nada.
unlink( $file );
$GLOBALS['options']['seo_404_enabled'] = 0;
$m::record();
check( ! file_exists( $file ), 'Con el registro apagado no se anota nada.' );

// Último tramo para sugerir un destino.
check( 'buen-fin' === $m::last_slug( '/buen-fin/' ) && 'hijo' === $m::last_slug( '/padre-viejo/hijo/?a=1' ) && 'pagina' === $m::last_slug( '/pagina.html' ) && '' === $m::last_slug( '/' ), 'El último tramo de la ruta es el slug que se busca.' );

// El volcado y la limpieza son tareas de WP-Cron, no de la visita.
$src = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-404-monitor.php' );
check( false !== strpos( $src, "self::FLUSH => 'hourly'" ) && false !== strpos( $src, "self::CRON => 'daily'" ), 'Volcado cada hora y limpieza diaria.' );
check( 1 === preg_match( '/public static function record\(\).*?\n\t}/s', $src, $record ) && false === strpos( $record[0], '$wpdb' ), 'record() no usa la base de datos.' );

array_map( 'unlink', glob( $GLOBALS['dir'] . '/uploads/digitalisimo-seo/*' ) ?: array() );
echo "Monitor de 404 con búfer, tope, agregación y sugerencia: correcto.\n";
