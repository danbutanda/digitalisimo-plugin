<?php
defined( 'ABSPATH' ) || exit;

/**
 * Análisis de contenido (RM9 de docs/SEO_RANKMATH_OBJECTIVE.md).
 *
 * Sólo informa: nunca cambia el contenido ni presenta la puntuación como garantía. `analyze()` es
 * pura (recibe los datos y devuelve las comprobaciones); `data_for()` los reúne en WordPress. La
 * puntuación se guarda al publicar para pintar la columna de los listados sin recalcular.
 */
class Digitalisimo_Integrations_Content_Analysis {
	const SCORE = 'digitalisimo_seo_score';

	public static function init() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'store_score' ), 30, 4 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'admin_init', array( __CLASS__, 'columns' ) );
	}

	private static function words( $text ) { return preg_match_all( '/[\p{L}\p{N}]+/u', (string) $text ); }
	private static function length( $text ) { return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text ); }
	private static function check( $id, $label, $status, $detail ) { return array( 'id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail ); }

	/**
	 * Comprobaciones de un contenido.
	 *
	 * @param array $d keyword, title, description, url, html, duplicates (títulos de otros contenidos con la misma keyword principal).
	 * @return array{checks:array,score:int}
	 */
	public static function analyze( array $d ) {
		$kw     = trim( (string) ( $d['keyword'] ?? '' ) );
		$html   = (string) ( $d['html'] ?? '' );
		$text   = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( preg_replace( '/<(script|style)\b.*?<\/\1>/is', ' ', $html ) ), ENT_QUOTES, 'UTF-8' ) ) );
		$words  = self::words( $text );
		$title  = (string) ( $d['title'] ?? '' );
		$desc   = (string) ( $d['description'] ?? '' );
		$match  = function( $haystack ) use ( $kw ) { return '' !== $kw && Digitalisimo_Integrations_Keyword_Match::contains( $haystack, $kw ); };
		$checks = array();

		if ( '' !== $kw ) {
			$checks[] = self::check( 'keyword_title', 'Keyword en el título SEO', $match( $title ) ? 'pass' : 'fail', $match( $title ) ? 'El título incluye la keyword principal.' : 'Añade la keyword principal al título SEO.' );
			$half     = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, (int) ceil( self::length( $title ) / 2 ) ) : substr( $title, 0, (int) ceil( strlen( $title ) / 2 ) );
			if ( $match( $title ) ) $checks[] = self::check( 'keyword_title_start', 'Keyword al inicio del título', $match( $half ) ? 'pass' : 'warn', $match( $half ) ? 'Aparece en la primera mitad del título.' : 'Acercarla al inicio del título la hace más visible en los resultados.' );
			$checks[] = self::check( 'keyword_description', 'Keyword en la meta descripción', $match( $desc ) ? 'pass' : 'warn', $match( $desc ) ? 'La descripción la menciona.' : 'Menciónala de forma natural en la meta descripción.' );
			$slug_ok  = Digitalisimo_Integrations_Keyword_Match::slug_matches( (string) ( $d['url'] ?? '' ), array( $kw ) );
			$checks[] = self::check( 'keyword_slug', 'Keyword en la URL', $slug_ok ? 'pass' : 'warn', $slug_ok ? 'La URL contiene sus términos.' : 'Usa los términos de la keyword en el enlace permanente.' );
			$intro    = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, max( 200, (int) ( self::length( $text ) / 10 ) ) ) : substr( $text, 0, max( 200, (int) ( strlen( $text ) / 10 ) ) );
			$checks[] = self::check( 'keyword_intro', 'Keyword al principio del contenido', $match( $intro ) ? 'pass' : 'warn', $match( $intro ) ? 'Aparece en el primer 10 % del texto.' : 'Inclúyela en el primer párrafo.' );
			preg_match_all( '/<h[2-4]\b[^>]*>(.*?)<\/h[2-4]>/is', $html, $headings );
			$in_heading = $match( implode( ' ', $headings[1] ) );
			$checks[] = self::check( 'keyword_subheadings', 'Keyword en un subtítulo', $headings[1] ? ( $in_heading ? 'pass' : 'warn' ) : 'warn', ! $headings[1] ? 'El contenido no tiene subtítulos H2–H4.' : ( $in_heading ? 'Al menos un subtítulo la incluye.' : 'Inclúyela en algún H2 o H3.' ) );
			preg_match_all( '/<img\b[^>]*>/i', $html, $images );
			if ( $images[0] ) {
				$alts = array_map( function( $tag ) { return preg_match( '/\salt\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? $m[2] : ''; }, $images[0] );
				$checks[] = self::check( 'keyword_alt', 'Keyword en el texto alternativo de una imagen', $match( implode( ' ', $alts ) ) ? 'pass' : 'warn', $match( implode( ' ', $alts ) ) ? 'Una imagen la describe.' : 'Si una imagen trata del tema, describe en su ALT lo que muestra usando la keyword.' );
			}
			if ( $words ) {
				$count   = Digitalisimo_Integrations_Keyword_Match::count( $text, $kw );
				$density = round( 100 * $count * max( 1, self::words( $kw ) ) / $words, 1 );
				$status  = $density < 0.5 ? 'warn' : ( $density > 3 ? 'warn' : 'pass' );
				$checks[] = self::check( 'keyword_density', 'Densidad de la keyword', $status, sprintf( 'Aparece %d %s (%s %%). %s', $count, 1 === $count ? 'vez' : 'veces', number_format( $density, 1 ), $density < 0.5 ? 'Es poco: el tema puede no quedar claro.' : ( $density > 3 ? 'Es mucho: escribe con naturalidad y usa sinónimos.' : 'Es un uso natural.' ) ) );
			}
			$duplicates = array_filter( (array) ( $d['duplicates'] ?? array() ) );
			$checks[] = self::check( 'keyword_unique', 'Keyword no usada en otro contenido', $duplicates ? 'warn' : 'pass', $duplicates ? 'También es la keyword principal de: ' . implode( ', ', array_slice( $duplicates, 0, 3 ) ) . '. Dos páginas compiten por la misma búsqueda.' : 'Ningún otro contenido la usa como principal.' );
		}

		$checks[] = self::check( 'content_length', 'Extensión del contenido', $words >= 300 ? 'pass' : 'fail', sprintf( '%d palabras. %s', $words, $words >= 300 ? 'Suficiente para tratar el tema.' : 'Menos de 300 palabras suele quedarse corto.' ) );
		$tl = self::length( $title );
		$checks[] = self::check( 'title_length', 'Longitud del título SEO', $tl >= 30 && $tl <= 60 ? 'pass' : 'warn', sprintf( '%d caracteres; lo ideal son 30–60.', $tl ) );
		$dl = self::length( $desc );
		$checks[] = self::check( 'description_length', 'Longitud de la meta descripción', 0 === $dl ? 'fail' : ( $dl >= 120 && $dl <= 160 ? 'pass' : 'warn' ), 0 === $dl ? 'No hay meta descripción ni extracto.' : sprintf( '%d caracteres; lo ideal son 120–160.', $dl ) );
		$slug = trim( (string) parse_url( (string) ( $d['url'] ?? '' ), PHP_URL_PATH ), '/' );
		$sl   = self::length( rawurldecode( basename( $slug ) ) );
		if ( '' !== $slug ) $checks[] = self::check( 'slug_length', 'Longitud de la URL', $sl <= 75 ? 'pass' : 'warn', $sl <= 75 ? 'El enlace permanente es breve.' : sprintf( 'El enlace tiene %d caracteres: acórtalo a lo esencial.', $sl ) );
		$links = class_exists( 'Digitalisimo_Integrations_Links' ) ? Digitalisimo_Integrations_Links::extract( $html, (string) ( $d['home'] ?? '' ) ) : array();
		$internal = count( array_filter( $links, function( $l ) { return $l['internal']; } ) );
		$checks[] = self::check( 'links_internal', 'Enlaces internos', $internal ? 'pass' : 'warn', $internal ? sprintf( 'Enlaza a %d páginas del sitio.', $internal ) : 'Enlaza al menos a un contenido relacionado del sitio.' );
		$checks[] = self::check( 'links_external', 'Enlaces externos', 'info', count( $links ) - $internal ? sprintf( '%d enlaces a otros sitios.', count( $links ) - $internal ) : 'Sin enlaces a otros sitios; cita fuentes cuando aporten.' );
		preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $paragraphs );
		$long = count( array_filter( $paragraphs[1], function( $p ) { return preg_match_all( '/[\p{L}\p{N}]+/u', strip_tags( $p ) ) > 150; } ) );
		if ( $paragraphs[1] ) $checks[] = self::check( 'short_paragraphs', 'Párrafos breves', $long ? 'warn' : 'pass', $long ? sprintf( '%d %s más de 150 palabras: divídelos para leerlos mejor.', $long, 1 === $long ? 'párrafo tiene' : 'párrafos tienen' ) : 'Ningún párrafo supera las 150 palabras.' );
		$media = preg_match( '/<(img|video|iframe)\b/i', $html );
		$checks[] = self::check( 'media', 'Imágenes o vídeo', $media ? 'pass' : 'warn', $media ? 'El contenido incluye recursos visuales.' : 'Una imagen o un vídeo ayuda a entender el tema.' );

		$points = 0;
		$total  = 0;
		foreach ( $checks as $c ) { if ( 'info' === $c['status'] ) continue; $total++; $points += 'pass' === $c['status'] ? 1 : ( 'warn' === $c['status'] ? 0.5 : 0 ); }
		return array( 'checks' => $checks, 'score' => $total ? (int) round( 100 * $points / $total ) : 0 );
	}

	/* ------------------------------------------------------------------ */
	/* WordPress.                                                           */
	/* ------------------------------------------------------------------ */

	public static function types() { return Digitalisimo_Integrations_Editorial::content_types(); }

	public static function primary_keyword( $post_id ) { $all = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post_id, 'digitalisimo_seo_keywords', true ) ) ) ); return $all ? reset( $all ) : ''; }

	/** Otros contenidos publicados con la misma keyword principal (normalizada). */
	private static function duplicates( $post_id, $keyword ) {
		global $wpdb;
		if ( '' === $keyword ) return array();
		// Sin prefiltro LIKE: tildes y mayúsculas sólo se comparan bien normalizadas en PHP, sea cual sea la colación.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = 'digitalisimo_seo_keywords' AND m.meta_value <> '' AND p.post_status = 'publish' AND p.ID <> %d ORDER BY p.post_date DESC LIMIT 2000", (int) $post_id ), ARRAY_A );
		$norm = Digitalisimo_Integrations_Keyword_Match::normalize( $keyword );
		$out  = array();
		foreach ( (array) $rows as $row ) if ( Digitalisimo_Integrations_Keyword_Match::normalize( trim( explode( ',', (string) $row['meta_value'] )[0] ) ) === $norm ) $out[] = get_the_title( (int) $row['post_id'] );
		return $out;
	}

	public static function data_for( $post ) {
		$snippet = Digitalisimo_Integrations_SEO_Suite::snippet_for( $post );
		$keyword = self::primary_keyword( $post->ID );
		return array( 'keyword' => $keyword, 'title' => $snippet['title'], 'description' => $snippet['description'], 'url' => get_permalink( $post ), 'home' => home_url( '/' ), 'html' => (string) $post->post_content, 'duplicates' => self::duplicates( $post->ID, $keyword ) );
	}

	public static function store_score( $post_id, $post, $update, $post_before ) {
		if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, self::types(), true ) ) return;
		if ( 'publish' !== $post->post_status ) { delete_post_meta( $post_id, self::SCORE ); return; }
		update_post_meta( $post_id, self::SCORE, self::analyze( self::data_for( $post ) )['score'] );
	}

	public static function metabox() { foreach ( self::types() as $type ) add_meta_box( 'digitalisimo_analysis', 'Análisis SEO', array( __CLASS__, 'metabox_html' ), $type, 'side', 'default' ); }

	public static function badge( $score ) {
		$class = $score >= 80 ? 'is-active' : 'is-inactive';
		return '<span class="digitalisimo-effective-state ' . $class . '">' . (int) $score . '/100</span>';
	}

	public static function metabox_html( $post ) {
		$result = self::analyze( self::data_for( $post ) );
		$icons  = array( 'pass' => '✓', 'warn' => '!', 'fail' => '✗', 'info' => 'i' );
		echo '<p><strong>Puntuación:</strong> ' . self::badge( $result['score'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- marcado propio con un entero.
		if ( '' === self::primary_keyword( $post->ID ) ) echo '<p class="description">Añade una keyword principal en el panel Keywords para analizarla.</p>';
		echo '<ul style="margin:0">';
		foreach ( $result['checks'] as $c ) echo '<li><span aria-hidden="true">' . esc_html( $icons[ $c['status'] ] ) . '</span> <strong>' . esc_html( $c['label'] ) . '</strong><br><span class="description">' . esc_html( $c['detail'] ) . '</span></li>';
		echo '</ul><p class="description">Se actualiza al guardar. Es una guía de redacción, no una garantía de posicionamiento.</p>';
	}

	public static function columns() {
		foreach ( self::types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", function( $columns ) { $columns['digitalisimo_score'] = 'Análisis'; return $columns; } );
			add_action( "manage_{$type}_posts_custom_column", function( $column, $post_id ) { if ( 'digitalisimo_score' !== $column ) return; $score = get_post_meta( $post_id, self::SCORE, true ); echo ( '' === $score ? '—' : self::badge( (int) $score ) ) . Digitalisimo_Integrations_SEO_Bulk::row_data( $post_id ); }, 10, 2 ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
