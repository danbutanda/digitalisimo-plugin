<?php
/**
 * DIGITALÍSIMO SEO · importador de metas de Yoast SEO y Rank Math sin pantalla (`Digitalisimo_Integrations_SEO::import_legacy()`),
 * el que usa «Migrar» de DIGITALÍSIMO Migración: título, descripción, palabra clave, canonical, noindex y contenido pilar;
 * nunca pisa un valor ya guardado (idempotente), y por lotes con cursor (`next`, `done`).
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['meta']    = array(
	11 => array( '_yoast_wpseo_title' => 'Título Yoast 11', '_yoast_wpseo_metadesc' => 'Descripción 11', '_yoast_wpseo_meta-robots-noindex' => '1' ),
	12 => array( '_yoast_wpseo_focuskw' => 'macetas', '_yoast_wpseo_is_cornerstone' => '1', 'digitalisimo_seo_keywords' => 'propia' ),
	13 => array( '_yoast_wpseo_canonical' => 'https://example.test/c/' ),
	20 => array( 'rank_math_title' => 'Título RM 20', 'rank_math_robots' => array( 'noindex' ), 'rank_math_pillar_content' => 'on' ),
	30 => array( 'otra' => 'x' ),
);
$GLOBALS['filters'] = array();
$GLOBALS['options'] = array();
function add_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ][] = $cb; }
function remove_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ] = array_values( array_filter( $GLOBALS['filters'][ $tag ] ?? array(), static function ( $x ) use ( $cb ) { return $x !== $cb; } ) ); }
function add_action() {}
function add_shortcode() {}
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
/** get_posts con meta_query EXISTS (OR), orden por ID, límite y el filtro posts_where del cursor. */
function get_posts( array $args ) {
	$keys  = array();
	foreach ( $args['meta_query'] as $k => $clause ) {
		if ( is_array( $clause ) ) {
			$keys[] = $clause['key'];
		}
	}
	$where = '';
	if ( empty( $args['suppress_filters'] ) ) {
		foreach ( $GLOBALS['filters']['posts_where'] ?? array() as $cb ) {
			$where = $cb( $where );
		}
	}
	$after = preg_match( '/ID > (\d+)/', $where, $m ) ? (int) $m[1] : 0;
	$only  = preg_match( '/ID IN \((\d+)\)/', $where, $m ) ? (int) $m[1] : 0;
	$ids   = array();
	foreach ( $GLOBALS['meta'] as $id => $meta ) {
		if ( $id > $after && ( ! $only || $id === $only ) && array_intersect( $keys, array_keys( $meta ) ) ) {
			$ids[] = $id;
		}
	}
	sort( $ids );
	return $args['posts_per_page'] > 0 ? array_slice( $ids, 0, $args['posts_per_page'] ) : $ids;
}
final class Fake_Wpdb {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql, ...$args ) {
		$args = is_array( $args[0] ?? null ) ? $args[0] : $args;
		$i    = 0;
		return preg_replace_callback( '/%[sd]/', static function ( $m ) use ( $args, &$i ) {
			$v = $args[ $i++ ];
			return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes( (string) $v ) . "'";
		}, $sql );
	}
	/** La consulta directa del cursor: metas IN (…), post_id > N, orden por ID y LIMIT. Sin filtros de WordPress. */
	public function get_col( $sql ) {
		preg_match( "/meta_key IN \(([^)]*)\)/", $sql, $in );
		preg_match_all( "/'([^']*)'/", $in[1] ?? '', $keys );
		$after = preg_match( '/post_id > (\d+)/', $sql, $m ) ? (int) $m[1] : 0;
		$limit = preg_match( '/LIMIT (\d+)/', $sql, $m ) ? (int) $m[1] : 0;
		$ids   = array();
		foreach ( $GLOBALS['meta'] as $id => $meta ) {
			if ( $id > $after && array_intersect( $keys[1], array_keys( $meta ) ) ) {
				$ids[] = $id;
			}
		}
		sort( $ids );
		return $limit ? array_slice( $ids, 0, $limit ) : $ids;
	}
}
$GLOBALS['wpdb'] = new Fake_Wpdb();

$source = file_get_contents( dirname( __DIR__ ) . '/digitalisimo-seo/includes/class-seo.php' );
// Sólo la clase (sin su init, que engancha pantallas): se evalúa tal cual.
eval( '?>' . $source );

$problems = array();
$check    = static function ( $ok, $message ) use ( &$problems ) {
	if ( ! $ok ) {
		$problems[] = $message;
	}
};

// Un plugin multilingüe (WPML) filtra las consultas de entradas a un idioma: aquí, sólo el 11. El importador no debe
// depender de esos filtros (antes usaba get_posts() con filtros en los lotes y se dejaba el 12 y el 13).
add_filter( 'posts_where', static function ( $where ) { return $where . ' AND wp_posts.ID IN (11)'; } );
$GLOBALS['wpml'] = true;

// Por lotes de 2 desde Yoast: 11, 12 → 13 → nada.
$first = Digitalisimo_Integrations_SEO::import_legacy( array( 'yoast' ), 0, 2 );
$check( 12 === $first['next'] && false === $first['done'] && 2 === $first['found'], 'Primer lote: hasta el 12 y quedan más: ' . json_encode( $first ) );
$second = Digitalisimo_Integrations_SEO::import_legacy( array( 'yoast' ), $first['next'], 2 );
$check( 13 === $second['next'] && true === $second['done'], 'Segundo lote: el 13 y termina: ' . json_encode( $second ) );
$check( 'Título Yoast 11' === $GLOBALS['meta'][11]['digitalisimo_seo_title'] && 'Descripción 11' === $GLOBALS['meta'][11]['digitalisimo_seo_description'], 'Título y descripción de Yoast importados.' );
$check( array( 'noindex' ) === $GLOBALS['meta'][11]['digitalisimo_seo_robots'], 'Noindex de Yoast importado.' );
$check( 'propia' === $GLOBALS['meta'][12]['digitalisimo_seo_keywords'], 'Un valor ya guardado en DIGITALÍSIMO SEO nunca se pisa.' );
$check( 'on' === $GLOBALS['meta'][12]['digitalisimo_seo_pillar'], 'Contenido cornerstone de Yoast pasa a pilar.' );
$check( 'https://example.test/c/' === $GLOBALS['meta'][13]['digitalisimo_seo_canonical'], 'Canonical de Yoast importado.' );
$check( ! isset( $GLOBALS['meta'][20]['digitalisimo_seo_title'] ), 'Importar Yoast no toca lo de Rank Math.' );
$check( ! empty( $GLOBALS['options']['digitalisimo_seo_legacy_imported'] ), 'Al terminar anota la importación.' );

// Idempotente: una segunda pasada no importa nada.
$again = Digitalisimo_Integrations_SEO::import_legacy( array( 'yoast' ) );
$check( 0 === $again['imported'] && 3 === $again['found'] && $again['done'], 'Una segunda pasada no importa nada: ' . json_encode( $again ) );

// Rank Math, sin lotes (el botón de la pantalla).
$rm = Digitalisimo_Integrations_SEO::import_legacy( array( 'rankmath' ) );
$check( 'Título RM 20' === $GLOBALS['meta'][20]['digitalisimo_seo_title'] && array( 'noindex' ) === $GLOBALS['meta'][20]['digitalisimo_seo_robots'] && 'on' === $GLOBALS['meta'][20]['digitalisimo_seo_pillar'] && $rm['done'], 'Rank Math: título, noindex y pilar.' );
$check( 1 === count( $GLOBALS['filters']['posts_where'] ?? array() ), 'El importador no deja filtros propios en las consultas.' );

if ( $problems ) {
	echo "FALLA seo-legacy-import\n  - " . implode( "\n  - ", $problems ) . "\n";
	exit( 1 );
}
echo "OK seo-legacy-import: importador de metas de Yoast y Rank Math sin pantalla, por lotes e idempotente.\n";
