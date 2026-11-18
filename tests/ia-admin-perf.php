<?php
/**
 * Guardar contenido sin reescribir la base de conocimiento de IA Tools cuando no cambia (auditoría admin-perf,
 * 2026-11-18), sin WordPress. El índice vive en una sola opción que crece con todo el contenido público; antes cada
 * save_post (de cualquier tipo: menús, pedidos, borradores, autoguardados) lo leía entero y cada guardado de una
 * entrada pública lo reescribía, dos veces con el editor de bloques (REST y metaboxes), y cada revisión borrada lo leía
 * y lo volvía a guardar. Ahora:
 *   1. una entrada que no se puede indexar y no estaba no lee el índice;
 *   2. guardar dos veces el mismo contenido escribe el índice una sola vez y conserva el embedding;
 *   3. lo que deja de ser público (borrador, contraseña, privado) sale del índice en el acto, como antes;
 *   4. borrar una revisión o una entrada no indexada no lo lee; borrar una indexada la quita;
 *   5. un índice de una versión anterior (sin la lista de IDs) sigue funcionando y crea la lista una vez.
 */
define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['options'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['reads'] = 0; $GLOBALS['writes'] = 0;
function add_action() {}
function add_filter() {}
function get_option( $key, $default = false ) { if ( Digitalisimo_AI_Knowledge::OPTION === $key ) $GLOBALS['reads']++; return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	if ( array_key_exists( $key, $GLOBALS['options'] ) && serialize( $GLOBALS['options'][ $key ] ) === serialize( $value ) ) return false; // como WordPress: sin cambios no escribe
	if ( Digitalisimo_AI_Knowledge::OPTION === $key ) $GLOBALS['writes']++;
	$GLOBALS['options'][ $key ] = $value; return true;
}
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_is_post_revision( $id ) { return 'revision' === ( $GLOBALS['posts'][ $id ]->post_type ?? '' ) ? 1 : false; }
function get_post_type_object( $type ) { return in_array( $type, array( 'post', 'page' ), true ) ? (object) array( 'public' => true ) : ( 'nav_menu_item' === $type ? (object) array( 'public' => false ) : null ); }
function post_type_supports( $type, $feature ) { return in_array( $type, array( 'post', 'page' ), true ); }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function strip_shortcodes( $text ) { return $text; }
function remove_accents( $text ) { return $text; }
function get_permalink( $post ) { return 'https://sitio.test/?p=' . $post->ID; }
function get_post_modified_time( $format, $gmt, $post ) { return $post->post_modified; }
function wp_trim_words( $text, $num, $more = '' ) { return implode( ' ', array_slice( preg_split( '/\s+/', (string) $text ), 0, $num ) ); }
function fail( $message ) { fwrite( STDERR, "FALLA: $message\n" ); exit( 1 ); }
class WP_Post { public function __construct( array $data ) { foreach ( $data as $k => $v ) $this->$k = $v; } }
class Digitalisimo_AI { public static function get( $key ) { return in_array( $key, array( 'knowledge_enabled', 'knowledge_auto_index' ), true ) ? 1 : 0; } }
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-knowledge.php';
$k = Digitalisimo_AI_Knowledge::class;
$ids_key = 'digitalisimo_ai_knowledge_ids';
function post( $id, $type = 'post', $status = 'publish', $content = 'Contenido público sobre hosting y dominios.', $modified = '2026-11-18T10:00:00+00:00' ) {
	return $GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_password' => '', 'post_title' => 'Entrada ' . $id, 'post_content' => $content, 'post_modified' => $modified ) );
}
// Un sitio con un índice de 200 entradas ya construido.
$index = array(); for ( $i = 1; $i <= 200; $i++ ) $index[ $i ] = array( 'id' => $i, 'title' => 'Entrada ' . $i, 'content' => str_repeat( 'texto ', 300 ), 'tokens' => array( 'texto' ) );
$GLOBALS['options'][ $k::OPTION ] = $index;
// 1. Guardar elementos que no se indexan (menú, borrador nuevo) no lee el índice: en un sitio que viene de una versión
// anterior (sin la lista de IDs) sólo el primero lo lee, una vez, para crear la lista.
$k::index_post( 899, post( 899, 'nav_menu_item', 'publish' ), true );
$GLOBALS['reads'] = 0; $GLOBALS['writes'] = 0;
for ( $i = 0; $i < 10; $i++ ) $k::index_post( 900 + $i, post( 900 + $i, 0 === $i % 2 ? 'nav_menu_item' : 'post', 0 === $i % 2 ? 'publish' : 'draft' ), true );
if ( $GLOBALS['reads'] || $GLOBALS['writes'] ) fail( "Guardar 10 menús y borradores no indexados leyó el índice {$GLOBALS['reads']} veces y lo escribió {$GLOBALS['writes']}." );
// 5. La lista de IDs se creó a partir del índice anterior.
if ( ! isset( $GLOBALS['options'][ $ids_key ] ) || 200 !== count( $GLOBALS['options'][ $ids_key ] ) ) fail( 'Con un índice anterior, la lista de IDs se crea a partir de él.' );
// 2. Mismo contenido guardado dos veces (REST y metaboxes): una sola escritura, y el embedding se conserva.
$GLOBALS['reads'] = 0; $GLOBALS['writes'] = 0;
$k::index_post( 1, post( 1, 'post', 'publish', 'Nuevo texto de la entrada uno.', '2026-11-18T10:00:00+00:00' ), true );
$GLOBALS['options'][ $k::OPTION ][1]['embedding'] = array( 0.1, 0.2 );
$k::index_post( 1, post( 1, 'post', 'publish', 'Nuevo texto de la entrada uno.', '2026-11-18T10:00:01+00:00' ), true );
if ( 1 !== $GLOBALS['writes'] ) fail( "Dos guardados con el mismo contenido escribieron el índice {$GLOBALS['writes']} veces." );
if ( array( 0.1, 0.2 ) !== ( $GLOBALS['options'][ $k::OPTION ][1]['embedding'] ?? null ) ) fail( 'Un guardado sin cambios conserva el embedding de la entrada.' );
if ( false === strpos( $GLOBALS['options'][ $k::OPTION ][1]['content'], 'Nuevo texto' ) ) fail( 'El primer guardado sí actualiza el registro.' );
// Un cambio de contenido sí reescribe.
$k::index_post( 1, post( 1, 'post', 'publish', 'Texto distinto.', '2026-11-18T10:05:00+00:00' ), true );
if ( 2 !== $GLOBALS['writes'] || false === strpos( $GLOBALS['options'][ $k::OPTION ][1]['content'], 'Texto distinto' ) ) fail( 'Un cambio de contenido reescribe el registro.' );
// 3. Lo que deja de ser público sale del índice en el acto.
foreach ( array( 'draft', 'private' ) as $n => $status ) {
	$id = 2 + $n;
	$k::index_post( $id, post( $id, 'post', $status ), true );
	if ( isset( $GLOBALS['options'][ $k::OPTION ][ $id ] ) || in_array( $id, $GLOBALS['options'][ $ids_key ], true ) ) fail( "Una entrada que pasa a $status debe salir del índice y de la lista." );
}
$p = post( 4 ); $p->post_password = 'secreta';
$k::index_post( 4, $p, true );
if ( isset( $GLOBALS['options'][ $k::OPTION ][4] ) ) fail( 'Una entrada con contraseña debe salir del índice.' );
// 4. Borrar revisiones o entradas no indexadas no lee el índice; borrar una indexada la quita.
$GLOBALS['reads'] = 0; $GLOBALS['writes'] = 0;
for ( $i = 0; $i < 20; $i++ ) { post( 1000 + $i, 'revision', 'inherit' ); $k::remove_post( 1000 + $i ); }
$k::remove_post( 950 );
if ( $GLOBALS['reads'] || $GLOBALS['writes'] ) fail( "Borrar 20 revisiones y una entrada no indexada leyó el índice {$GLOBALS['reads']} veces." );
$k::remove_post( 5 );
if ( isset( $GLOBALS['options'][ $k::OPTION ][5] ) || in_array( 5, $GLOBALS['options'][ $ids_key ], true ) ) fail( 'Borrar una entrada indexada la quita del índice y de la lista.' );
echo "IA Tools: la base de conocimiento sólo se lee y se reescribe cuando una entrada indexada cambia.\n";
