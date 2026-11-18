<?php
/**
 * Escritorio sin trabajo de más por DIGITALÍSIMO SEO (auditoría admin-perf, 2026-11-18), sin WordPress:
 *   1. el selector de pilar del editor (cluster_candidates) lee sólo ID, título, tipo y slug, sin get_posts(-1) del
 *      contenido completo con sus metadatos y términos, y sigue ordenado por título con los mismos candidatos;
 *   2. con el sitemap de vídeo activo, admin_init ya no recorre el postmeta de todo lo publicado en cada petición del
 *      escritorio (heartbeat y admin-ajax incluidos): sin nada pendiente lo comprueba como mucho una vez por hora, y
 *      con algo pendiente lo encola como antes;
 *   3. subir una imagen (un wp_update_attachment_metadata por cada tamaño intermedio) sube la generación del mapa de
 *      imágenes dos veces como mucho, no una escritura con autoload por tamaño, y el resultado sigue invalidando todo.
 */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WEEK_IN_SECONDS', 604800 );
$GLOBALS['get_posts_calls'] = array(); $GLOBALS['options'] = array(); $GLOBALS['scheduled'] = array(); $GLOBALS['get_posts_result'] = array();
function add_action( $hook = '', $callback = null, $priority = 10 ) { $GLOBALS['actions'][ $hook ][] = $callback; }
function add_filter() {}
function add_shortcode() {}
function get_post_types( $args = array(), $output = 'names' ) {
	$all = array( 'post' => (object) array( 'name' => 'post' ), 'page' => (object) array( 'name' => 'page' ), 'attachment' => (object) array( 'name' => 'attachment' ), 'elementor_library' => (object) array( 'name' => 'elementor_library' ), 'product' => (object) array( 'name' => 'product' ) );
	return 'objects' === $output ? $all : array_keys( $all );
}
function get_posts( $args ) { $GLOBALS['get_posts_calls'][] = $args; return $GLOBALS['get_posts_result']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; $GLOBALS['autoload'][ $key ] = $autoload; $GLOBALS['writes'][ $key ] = ( $GLOBALS['writes'][ $key ] ?? 0 ) + 1; return true; }
function get_current_blog_id() { return $GLOBALS['blog'] ?? 1; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['scheduled'][ $hook ] = $time; return true; }
/** Lo mínimo de otras clases de SEO que usa la del vídeo. */
class Digitalisimo_Integrations_Settings { const OPTION = 'digitalisimo_integrations_options'; public static function get( $k, $d = null ) { return $d; } }
class Digitalisimo_Integrations_Sitemap { public static function enabled() { return true; } }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $k, $d = null ) { return 'seo_sitemap_video_enabled' === $k ? 1 : $d; } }
class Digitalisimo_Integrations_SEO_Suite { public static function sitemap_posts( $types ) { return array( 'post' => true, 'page' => true ); } }
/** Las fallas se acumulan: sin los arreglos, cada parte informa la suya (no sólo la primera). */
function fail( $message ) { $GLOBALS['failures'][] = $message; fwrite( STDERR, "FALLA: $message\n" ); }

/** $wpdb de prueba: guarda cada consulta y responde a la de candidatos con filas de wp_posts. */
class Digi_Test_WPDB {
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $queries = array();
	public $rows = array();
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) $args = $args[0];
		foreach ( $args as $a ) $sql = preg_replace( '/%[sd]/', is_int( $a ) ? (string) $a : "'" . addslashes( (string) $a ) . "'", $sql, 1 );
		return $sql;
	}
	public function get_results( $sql, $output = 'OBJECT' ) {
		$this->queries[] = $sql;
		if ( ! preg_match( "/^SELECT ID, post_title, post_type, post_name FROM wp_posts WHERE post_type IN \\(([^)]*)\\) AND post_status = 'publish' ORDER BY post_title ASC/", $sql, $m ) ) return array();
		$types = array_map( function( $t ) { return trim( $t, " '" ); }, explode( ',', $m[1] ) );
		$out = array();
		foreach ( $this->rows as $row ) if ( 'publish' === $row['post_status'] && in_array( $row['post_type'], $types, true ) ) $out[] = (object) array( 'ID' => (string) $row['ID'], 'post_title' => $row['post_title'], 'post_type' => $row['post_type'], 'post_name' => $row['post_name'] );
		usort( $out, function( $a, $b ) { return strcmp( $a->post_title, $b->post_title ) ?: ( (int) $a->ID <=> (int) $b->ID ); } );
		return $out;
	}
}
$GLOBALS['wpdb'] = new Digi_Test_WPDB();
$GLOBALS['wpdb']->rows = array(
	array( 'ID' => 3, 'post_title' => 'Zapatos', 'post_type' => 'post', 'post_name' => 'zapatos', 'post_status' => 'publish' ),
	array( 'ID' => 4, 'post_title' => 'Borrador', 'post_type' => 'post', 'post_name' => 'borrador', 'post_status' => 'draft' ),
	array( 'ID' => 5, 'post_title' => 'Acerca', 'post_type' => 'page', 'post_name' => 'acerca', 'post_status' => 'publish' ),
	array( 'ID' => 6, 'post_title' => 'Plantilla', 'post_type' => 'elementor_library', 'post_name' => 'plantilla', 'post_status' => 'publish' ),
	array( 'ID' => 7, 'post_title' => 'Imagen', 'post_type' => 'attachment', 'post_name' => 'imagen', 'post_status' => 'publish' ),
	array( 'ID' => 8, 'post_title' => 'Botas', 'post_type' => 'product', 'post_name' => 'botas', 'post_status' => 'publish' ),
);
require __DIR__ . '/../digitalisimo-seo/includes/class-editorial.php';

// 1. Candidatos a pilar.
$candidates = Digitalisimo_Integrations_Editorial::cluster_candidates();
foreach ( $GLOBALS['get_posts_calls'] as $args ) if ( -1 === (int) ( $args['posts_per_page'] ?? 0 ) && 'ids' !== ( $args['fields'] ?? '' ) ) fail( 'cluster_candidates() no debe traer con get_posts(-1) cada entrada publicada completa (con su contenido, metadatos y términos).' );
$ids = array_map( function( $p ) { return $p->ID; }, $candidates );
if ( array( 5, 8, 3 ) !== $ids ) fail( 'Candidatos: publicados de tipos públicos (sin adjuntos ni plantillas), por título; obtenidos ' . json_encode( $ids ) );
foreach ( $candidates as $c ) if ( ! is_int( $c->ID ) || '' === $c->post_title || '' === $c->post_type || '' === $c->post_name ) fail( 'Cada candidato trae ID entero, título, tipo y slug (el editor compara el ID con ===).' );
if ( 1 !== count( $GLOBALS['wpdb']->queries ) || false !== stripos( $GLOBALS['wpdb']->queries[0], 'post_content' ) ) fail( 'Una sola consulta y sin post_content.' );

// 2. Sitemap de vídeo: comprobación del backlog en admin_init.
require __DIR__ . '/../digitalisimo-seo/includes/class-video.php';
$video = Digitalisimo_Integrations_Video::class;
$GLOBALS['get_posts_calls'] = array();
$video::admin_init();
if ( 2 !== count( $GLOBALS['get_posts_calls'] ) ) fail( 'La primera comprobación busca contenido sin analizar y pendiente de Vimeo.' );
if ( isset( $GLOBALS['scheduled'][ $video::HOOK ] ) ) fail( 'Sin nada pendiente no se encola nada.' );
$GLOBALS['get_posts_calls'] = array();
for ( $i = 0; $i < 5; $i++ ) $video::admin_init();
if ( $GLOBALS['get_posts_calls'] ) fail( 'Sin nada pendiente, cada petición del escritorio repetía la consulta del postmeta (' . count( $GLOBALS['get_posts_calls'] ) . ' consultas en 5 peticiones).' );
// Pasada una hora se vuelve a comprobar (se recupera lo publicado sin guardar, p. ej. por una importación).
$GLOBALS['options'][ 'digitalisimo_seo_video_checked' ] = time() - 2 * HOUR_IN_SECONDS;
$GLOBALS['get_posts_result'] = array( 42 );
$video::admin_init();
if ( empty( $GLOBALS['scheduled'][ $video::HOOK ] ) ) fail( 'Con contenido sin analizar, admin_init encola el lote como antes.' );
// Con la cola programada no se consulta nada.
$GLOBALS['get_posts_calls'] = array();
$video::admin_init();
if ( $GLOBALS['get_posts_calls'] ) fail( 'Con el lote ya programado no hace falta consultar.' );
// Cambiar los ajustes borra la marca: la próxima petición comprueba en seguida.
$GLOBALS['options'][ 'digitalisimo_seo_video_checked' ] = time();
if ( method_exists( $video, 'forget_checked' ) ) $video::forget_checked(); else fail( 'Falta forget_checked() para borrar la marca al cambiar los ajustes.' );
if ( isset( $GLOBALS['options'][ 'digitalisimo_seo_video_checked' ] ) ) fail( 'Guardar los ajustes debe borrar la marca de comprobación.' );

// 3. Generación del mapa de imágenes al subir una imagen con seis tamaños.
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_Performance_Cache { public static function get( $n, &$f = null ) { $f = false; return false; } public static function set() { return true; } }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-images.php';
$images = Digitalisimo_Integrations_Performance_Images::class;
$GLOBALS['options'][ $images::GENERATION ] = 7; $GLOBALS['writes'] = array(); $GLOBALS['actions']['shutdown'] = array();
for ( $i = 0; $i < 6; $i++ ) $images::invalidate_metadata( array( 'sizes' => array() ) );
$n_writes = $GLOBALS['writes'][ $images::GENERATION ] ?? 0;
if ( $n_writes > 1 ) fail( "Una subida con seis tamaños escribió la generación del mapa de imágenes $n_writes veces en la petición." );
if ( 8 !== $GLOBALS['options'][ $images::GENERATION ] ) fail( 'El primer cambio sube la generación en el acto.' );
foreach ( $GLOBALS['actions']['shutdown'] as $callback ) call_user_func( $callback );
if ( 9 !== $GLOBALS['options'][ $images::GENERATION ] || 2 !== ( $GLOBALS['writes'][ $images::GENERATION ] ?? 0 ) ) fail( 'Al terminar la petición sube una vez más (lo calculado entre medias queda invalidado): dos escrituras en total.' );
if ( true !== $GLOBALS['autoload'][ $images::GENERATION ] ) fail( 'La generación conserva su autoload.' );
// Un cambio suelto en otra petición del mismo sitio no deja nada pendiente para el final.
$GLOBALS['actions']['shutdown'] = array();
foreach ( $GLOBALS['actions']['shutdown'] as $callback ) call_user_func( $callback );
if ( 9 !== $GLOBALS['options'][ $images::GENERATION ] ) fail( 'Sin cambios pendientes, el final de la petición no escribe nada.' );
if ( ! empty( $GLOBALS['failures'] ) ) exit( 1 );
echo "SEO: el selector de pilar lee sólo lo que pinta, el sitemap de vídeo no recorre el postmeta en cada petición y subir una imagen no reescribe la generación por cada tamaño.\n";
