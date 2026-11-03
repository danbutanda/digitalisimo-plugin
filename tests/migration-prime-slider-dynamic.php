<?php
/**
 * F8 · Prime Slider dinámico (frente F; §3.E.4, V.10, V.14 C15, V.16): familias E (entradas UIkit), F (entradas Swiper),
 * G (WooCommerce) y L (eventos).
 *
 * - `Query`: los `term_taxonomy_id` de Prime Slider pasan tal cual (no por `Translator::posts_query()`, que los
 *   estropearía), fuentes, inclusión y exclusión, fechas, orden, productos y eventos de The Events Calendar.
 * - `Loop_Item`: creado una vez por huella, publicado sólo con la huella releída correcta, reutilizado, nunca creado
 *   en modo puro (`Context::pure()`), eventos con `source = post`, falla cerrado y reversión por huella.
 * - `Dynamic::build()`: los 19 widgets con los casos de `tests/legacy/prime-slider-<id>.json`, respaldos, productos en
 *   sus dos destinos, woohotspot (slider anidado y fragmento), redes y «Scroll Down» como hermanos, claves comunes,
 *   pureza, `state()`, informe y nada del proveedor en lo convertido.
 * - Mapas y catálogo `ps.dyn.*`.
 *
 * Carga el WordPress mínimo de A (`tests/fixtures/f8-wp.php`) y las clases reales de Migración con su autocarga; el
 * registro y los documentos de A (`Platform\Registry`, `Platform\Documents`) se usan si existen y, si no, un doble con
 * la misma firma (C6). Lo mismo `Builder` de E para el slider anidado de woohotspot.
 */
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	if ( ! function_exists( 'post_type_exists' ) ) {
		function post_type_exists( $type ) {
			return in_array( $type, (array) ( $GLOBALS['wp']['post_types'] ?? array( 'post', 'page' ) ), true );
		}
	}
	f8_load_migration( array( 'translator', 'context', 'report' ) );
}

/* ------------------------------------------------------------------ Dobles de A (C6), sólo si no existen */
namespace Digitalisimo\Elements\Legacy\Platform {
	if ( ! class_exists( __NAMESPACE__ . '\\Registry' ) ) {
		final class Registry {
			public static function get( string $kind, string $source ): ?array {
				$rows = get_option( 'digitalisimo_migration_reg_' . $kind, array() );
				return is_array( $rows[ $source ] ?? null ) ? $rows[ $source ] : null;
			}
			public static function put( string $kind, string $source, array $row ): void {
				$GLOBALS['f8f']['reg_writes'] = ( $GLOBALS['f8f']['reg_writes'] ?? 0 ) + 1;
				$rows            = (array) get_option( 'digitalisimo_migration_reg_' . $kind, array() );
				$rows[ $source ] = $row + array( 'dest' => 0, 'hash' => '', 'state' => 'pending', 'extra' => array() );
				update_option( 'digitalisimo_migration_reg_' . $kind, $rows, false );
			}
			public static function all( string $kind ): array {
				return (array) get_option( 'digitalisimo_migration_reg_' . $kind, array() );
			}
			public static function forget( string $kind, string $source ): void {
				$rows = (array) get_option( 'digitalisimo_migration_reg_' . $kind, array() );
				unset( $rows[ $source ] );
				update_option( 'digitalisimo_migration_reg_' . $kind, $rows, false );
			}
		}
		$GLOBALS['f8f']['double_registry'] = true;
	}
	if ( ! class_exists( __NAMESPACE__ . '\\Documents' ) ) {
		final class Documents {
			public static function create( string $type, array $post, array $meta, array $origin ): int {
				$GLOBALS['f8f']['creates'] = ( $GLOBALS['f8f']['creates'] ?? 0 ) + 1;
				$id = wp_insert_post( array( 'post_status' => 'draft' ) + $post );
				if ( ! $id ) {
					return 0;
				}
				foreach ( $meta as $key => $value ) {
					update_post_meta( $id, $key, wp_slash( $value ) );
				}
				if ( ! empty( $GLOBALS['f8f']['corrupt'] ) ) {
					update_post_meta( $id, '_elementor_data', '[]' );
				}
				update_post_meta( $id, '_digitalisimo_import_origin', $origin );
				return md5( (string) ( $meta['_elementor_data'] ?? '' ) ) === self::hash( $id ) ? (int) $id : 0;
			}
			public static function hash( int $id ): string {
				$data = get_post_meta( $id, '_elementor_data', true );
				return is_string( $data ) && '' !== $data ? md5( $data ) : '';
			}
			public static function origin( int $id ): ?array {
				$o = get_post_meta( $id, '_digitalisimo_import_origin', true );
				return is_array( $o ) ? $o : null;
			}
		}
		$GLOBALS['f8f']['double_documents'] = true;
	}
}

namespace {
	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Translator;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Dynamic;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Loop_Item;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Query;

	$check = 'f8_check';
	$root  = dirname( __DIR__ );

	/* -------------------------------------------------------------- Ayudas */

	$vendors = json_decode( (string) file_get_contents( $root . '/tests/playground/brand-audit/vendors.json' ), true );
	// El nombre del proveedor sale de vendors.json (el host cuyo nombre empieza una carpeta de Prime Slider), no se copia.
	$vendor = '';
	foreach ( (array) $vendors['hosts'] as $host ) {
		$label = explode( '.', $host )[0];
		foreach ( (array) $vendors['originalSlugs'] as $slug ) {
			if ( false !== strpos( $slug, 'prime-slider' ) && 0 === strpos( $slug, $label ) ) {
				$vendor = $label;
			}
		}
	}
	$check( '' !== $vendor, 'vendors.json da el nombre del proveedor' );

	/** Un caso de `tests/legacy/prime-slider-<id>.json`, con los marcadores expandidos. */
	function fx( string $id, string $case ): array {
		global $vendor;
		$json = (string) file_get_contents( dirname( __DIR__ ) . '/tests/legacy/prime-slider-' . $id . '.json' );
		$json = preg_replace_callback( '/\{\{vendor_profile:([a-z]+)\}\}/', static function ( $m ) use ( $vendor ) {
			return 'https://www.' . $m[1] . '.com/' . $vendor . '/';
		}, $json );
		$data = json_decode( $json, true );
		return (array) ( $data[ $case ] ?? array() );
	}
	function el( string $id, array $settings, string $eid = 'a1b2c3d' ): array {
		return array( 'id' => $eid, 'elType' => 'widget', 'widgetType' => 'prime-slider-' . $id, 'settings' => $settings, 'elements' => array() );
	}
	function build( string $id, array $settings, int $post = 0, string $eid = 'a1b2c3d' ) {
		return Dynamic::build( $id, $settings, el( $id, $settings, $eid ), $post );
	}
	function main_of( $out ): array {
		return is_array( $out ) && '__fragment' === ( $out['elType'] ?? '' ) ? (array) $out['elements'][0] : (array) $out;
	}
	/** Códigos informados para un documento (y vacía el informe). */
	function codes( int $post ): array {
		$report = Report::take();
		$out    = array();
		foreach ( (array) ( $report['docs'][ $post ]['items'] ?? array() ) as $item ) {
			$out[] = $item['code'];
		}
		return $out;
	}
	function site_codes(): array {
		$report = Report::take();
		return array_map( static function ( $item ) {
			return $item['code'];
		}, (array) ( $report['site']['items'] ?? array() ) );
	}
	/** Entorno estándar: Elementor con contenedores, PRO, WooCommerce y The Events Calendar. */
	function env( array $over = array() ): void {
		$base = array(
			'ready'       => array(),
			'features'    => array( 'container' => true, 'loop-item' => true, 'nested' => true ),
			'tags'        => array(),
			'woocommerce' => true,
			'events'      => true,
		);
		foreach ( $over as $key => $value ) {
			$base[ $key ] = is_array( $value ) && is_array( $base[ $key ] ?? null ) ? array_merge( $base[ $key ], $value ) : $value;
		}
		Dynamic::set_env( $base );
	}
	function reset_all(): void {
		f8_reset();
		$GLOBALS['wp']['post_types'] = array( 'post', 'page', 'product', 'tribe_events' );
		$GLOBALS['f8f']              = array_intersect_key( (array) ( $GLOBALS['f8f'] ?? array() ), array_flip( array( 'double_registry', 'double_documents' ) ) );
		Context::reset();
		Loop_Item::reset();
		Report::clear();
		Report::begin( 'run' );
		env();
		// Términos con term_id distinto de su term_taxonomy_id (y uno cuyo term_id coincide con el tt_id del otro).
		$GLOBALS['wp']['terms'][1][37] = array( 'term_id' => 37, 'term_taxonomy_id' => 52, 'taxonomy' => 'category', 'slug' => 'noticias', 'name' => 'Noticias' );
		$GLOBALS['wp']['terms'][1][52] = array( 'term_id' => 52, 'term_taxonomy_id' => 70, 'taxonomy' => 'post_tag', 'slug' => 'otra', 'name' => 'Otra' );
		$GLOBALS['wp']['terms'][1][60] = array( 'term_id' => 60, 'term_taxonomy_id' => 61, 'taxonomy' => 'tribe_events_cat', 'slug' => 'conciertos', 'name' => 'Conciertos' );
	}
	/** Todo el JSON de una salida (y de los Loop Items creados) sin rastro del proveedor. */
	function vendor_free( $data, bool $names = true ): bool {
		global $vendors, $vendor;
		$json = strtolower( (string) json_encode( $data, JSON_UNESCAPED_SLASHES ) );
		foreach ( (array) $vendors['hosts'] as $host ) {
			if ( false !== strpos( $json, strtolower( $host ) ) ) {
				return false;
			}
		}
		foreach ( (array) $vendors['vendorVideos'] as $video ) {
			if ( false !== strpos( $json, strtolower( $video ) ) ) {
				return false;
			}
		}
		foreach ( $names ? array( $vendor, '/plugins/' . $vendor, 'prime-slider', 'prime slider', '"bdt-', ' bdt-', '.bdt-' ) : array( '/plugins/' . $vendor ) as $needle ) {
			if ( false !== strpos( $json, strtolower( $needle ) ) ) {
				return false;
			}
		}
		return true;
	}
	/** Recorre un árbol de elementos. */
	function walk( array $elements, callable $fn ): void {
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$fn( $element );
				walk( (array) ( $element['elements'] ?? array() ), $fn );
			}
		}
	}
	function loop_data( int $id ): array {
		return (array) json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
	}
	function widgets_of( array $tree ): array {
		$out = array();
		walk( $tree, static function ( $e ) use ( &$out ) {
			if ( 'widget' === ( $e['elType'] ?? '' ) ) {
				$out[] = $e['widgetType'];
			}
		} );
		return $out;
	}

	reset_all();

	/* ============================================================== 1. Mapas y catálogo */

	$catalog = include $root . '/digitalisimo-migration/legacy/report/codes-ps-dynamic.php';
	$check( is_array( $catalog ) && count( $catalog ) >= 40, 'catálogo ps.dyn.* con sus códigos' );
	$bad = array();
	foreach ( $catalog as $code => $spec ) {
		if ( 0 !== strpos( $code, 'ps.dyn.' ) || ! preg_match( '/^ps\.dyn\.[a-z0-9_.]+$/', $code ) || ! in_array( $spec['section'] ?? '', Report::SECTIONS, true ) || strlen( (string) ( $spec['label'] ?? '' ) ) < 20 || isset( $spec['review_if_published'] ) && ! is_bool( $spec['review_if_published'] ) ) {
			$bad[] = $code;
		}
		// Migración puede nombrar, sin enlace, los plugins que retira; nunca un dominio del proveedor.
		if ( ! vendor_free( array( $spec['label'] ), false ) ) {
			$bad[] = $code . ' (proveedor)';
		}
	}
	$check( ! $bad, 'códigos con prefijo, sección válida, etiqueta y sin proveedor: ' . implode( ', ', $bad ) );
	$all = Report::catalog();
	$check( isset( $all['ps.dyn.loop'], $all['ps.dyn.loss.event_dates'], $all['ps.dyn.events_unavailable'] ), 'Report::catalog() carga codes-ps-dynamic.php' );
	$check( 'dropped' === $all['ps.dyn.events_unavailable']['section'], 'event-calendar sin The Events Calendar es un descarte declarado (no «Pendiente de revisar»)' );
	$check( 'review' === $all['ps.dyn.blocked']['section'] && 'created' === $all['ps.dyn.loop_created']['section'], 'bloqueado → revisar; Loop Item → creado' );
	$check( ! empty( $all['ps.dyn.fallback_posts']['review_if_published'] ), 'el respaldo en rejilla se revisa si la página está publicada' );

	$check( 19 === count( Dynamic::IDS ) && array( 'E', 'F', 'G', 'L' ) === Dynamic::FAMILIES, '19 widgets de F y sus 4 familias (V.10.7)' );
	$kinds = array( 'posts', 'products', 'wc', 'hotspot', 'events' );
	foreach ( Dynamic::IDS as $id ) {
		$file = $root . '/digitalisimo-migration/legacy/prime-slider/maps/prime-slider-' . $id . '.php';
		$map  = is_file( $file ) ? ( static function ( $f ) { return require $f; } )( $file ) : null;
		$ok   = is_array( $map ) && in_array( $map['family'] ?? '', Dynamic::FAMILIES, true ) && 'APROXIMAR' === ( $map['decision'] ?? '' )
			&& is_string( $map['target'] ?? null ) && '' !== $map['target'] && is_string( $map['fallback'] ?? null ) && strlen( (string) ( $map['reason'] ?? '' ) ) >= 20
			&& in_array( $map['dynamic']['kind'] ?? '', $kinds, true );
		$check( $ok, 'mapa válido de ' . $id );
		foreach ( array_keys( (array) ( $map['dynamic']['loss'] ?? array() ) ) as $code ) {
			$check( isset( $catalog[ $code ] ), $id . ': la pérdida ' . $code . ' está en el catálogo' );
		}
		$loaded = Dynamic::map( 'prime-slider-' . $id );
		$check( is_array( $loaded ) && ( $loaded['family'] ?? '' ) === $map['family'], 'Dynamic::map() lee ' . $id . ' (también con el widgetType)' );
		$check( vendor_free( array( $map['reason'] ), false ) && vendor_free( array( $map['target'], $map['fallback'] ) ), $id . ': motivo y destino sin proveedor' );
		$fx = $root . '/tests/legacy/prime-slider-' . $id . '.json';
		$check( is_file( $fx ) && is_array( json_decode( (string) file_get_contents( $fx ), true )['defaults'] ?? null ), 'fixture sintético de ' . $id );
	}
	$check( 'G' === Dynamic::map( 'woohotspot' )['family'] && 'L' === Dynamic::map( 'event-calendar' )['family'], 'familias de woohotspot y event-calendar' );
	$check( null === Dynamic::map( 'general' ) && null === Dynamic::map( '../blog' ) && null === build( 'general', array() ), 'Dynamic no toca mapas de E ni IDs con forma de ruta' );
	$check( 'blog' === Dynamic::short( 'Prime-Slider-Blog' ) && '' === Dynamic::short( 'blog/../x' ), 'short() normaliza y rechaza rutas' );

	/* ============================================================== 2. Query */

	// Los term_taxonomy_id pasan tal cual: 52 es el tt_id de «Noticias» (term_id 37); Translator los trataría como term_id.
	$ps = array( 'posts_source' => 'post', 'posts_include_by' => array( 'terms' ), 'posts_include_term_ids' => array( '52' ), 'posts_exclude_by' => array( 'terms', 'current_post' ), 'posts_exclude_term_ids' => array( 52 ) );
	list( $q, $qc ) = Query::posts( $ps, 'post_query_' );
	$check( array( '52' ) === $q['post_query_include_term_ids'] && array( '52' ) === $q['post_query_exclude_term_ids'], 'term_taxonomy_id sin conversión' );
	$tr = Translator::posts_query( $ps );
	$check( array( '70' ) === $tr['posts_include_term_ids'], 'control: Translator::posts_query() los estropearía (52 → 70), por eso no se usa' );
	$check( array( 'terms' ) === $q['post_query_include'] && array( 'current_post', 'terms' ) === $q['post_query_exclude'], 'inclusión y exclusión' );
	$check( 'post' === $q['post_query_post_type'] && 'post_date' === $q['post_query_orderby'] && 'desc' === $q['post_query_order'] && 'yes' === $q['post_query_ignore_sticky_posts'], 'valores por defecto de la consulta' );
	$check( ! array_filter( array_keys( $q ), static function ( $k ) { return 0 !== strpos( $k, 'post_query_' ); } ), 'sólo claves con el prefijo pedido' );
	list( $q ) = Query::posts( array( 'posts_source' => 'manual_selection', 'posts_selected_ids' => array( '11', 12, 'x', '-3' ), 'posts_orderby' => 'title', 'posts_order' => 'ASC' ), 'posts_' );
	$check( 'by_id' === $q['posts_post_type'] && array( '11', '12' ) === $q['posts_posts_ids'] && 'post_title' === $q['posts_orderby'] && 'asc' === $q['posts_order'] && ! isset( $q['posts_include'] ), 'selección manual (IDs saneados), orden por título' );
	list( $q, $qc ) = Query::posts( array( 'posts_source' => '_related_post_type', 'posts_orderby' => 'ID', 'query_id' => 'mi_consulta' ), 'post_query_' );
	$check( 'related' === $q['post_query_post_type'] && 'ID' === $q['post_query_orderby'] && 'mi_consulta' === $q['post_query_query_id'], 'relacionadas, orden por ID y query_id' );
	$check( in_array( array( 'ps.dyn.related', array() ), $qc, true ) && 'ps.dyn.query_id' === $qc[1][0], 'relacionadas y query_id se informan' );
	list( $q ) = Query::posts( array( 'posts_source' => 'current_query', 'posts_orderby' => 'title' ), 'post_query_' );
	$check( 'current_query' === $q['post_query_post_type'] && ! isset( $q['post_query_orderby'] ), 'consulta actual sin orden propio' );
	list( $q ) = Query::posts( array( 'posts_select_date' => 'exact', 'posts_date_after' => '2026-01-01', 'posts_date_before' => '2026-06-30 23:59', 'posts_source' => 'page' ), 'post_query_' );
	$check( 'exact' === $q['post_query_select_date'] && '2026-01-01' === $q['post_query_date_after'] && '2026-06-30 23:59' === $q['post_query_date_before'] && ! isset( $q['post_query_ignore_sticky_posts'] ), 'fechas exactas; «ignorar fijas» sólo con entradas' );
	list( $q ) = Query::posts( array( 'posts_select_date' => 'exact', 'posts_date_after' => '2026-01-01"><script>', 'posts_source' => 'x"y' ), 'post_query_' );
	$check( ! isset( $q['post_query_date_after'] ) && 'post' === $q['post_query_post_type'], 'fechas y tipos con caracteres extraños se descartan' );
	list( $q, $qc ) = Query::products( array( 'posts_source' => 'product', 'product_show_product_type' => 'onsale', 'posts_include_by' => array( 'terms', 'authors' ), 'posts_include_term_ids' => array( '52', '999' ), 'product_orderby' => 'sales', 'product_hide_out_stock' => 'yes' ), 'product_query_' );
	$check( 'sale' === $q['product_query_post_type'] && array( '52' ) === $q['product_query_include_term_ids'] && 'popularity' === $q['product_query_orderby'], 'productos en oferta, términos existentes y orden por ventas' );
	$codes = array_column( $qc, 0 );
	$check( in_array( 'ps.dyn.query_authors', $codes, true ) && in_array( 'ps.dyn.loss.product_filters', $codes, true ), 'autores y filtros de productos sin equivalente se informan' );
	list( $q, $qc ) = Query::products( array( 'product_show_product_type' => 'featured', 'product_orderby' => 'ID' ), 'query_' );
	$check( 'featured' === $q['query_post_type'] && 'date' === $q['query_orderby'] && 'ps.dyn.orderby' === $qc[0][0], 'destacados; orden por ID → fecha (informado)' );
	list( $q, $qc ) = Query::products( array( 'posts_source' => 'post' ), 'query_' );
	$check( 'product' === $q['query_post_type'] && 'ps.dyn.query_source' === $qc[0][0], 'un widget de productos con otra fuente consulta productos' );
	list( $q ) = Query::products( array( 'posts_source' => 'manual_selection', 'posts_selected_ids' => array( '31' ) ), 'query_' );
	$check( 'by_id' === $q['query_post_type'] && array( '31' ) === $q['query_posts_ids'], 'productos elegidos a mano' );
	list( $q, $qc ) = Query::events( array( 'source' => 'by_name', 'event_categories' => array( 'conciertos', 'no-existe', '<b>' ), 'orderby' => 'event_date', 'order' => 'DESC' ), 'post_query_' );
	$check( 'tribe_events' === $q['post_query_post_type'] && array( '61' ) === $q['post_query_include_term_ids'] && 'post_date' === $q['post_query_orderby'] && ! $qc, 'eventos: slug → term_taxonomy_id, fecha del evento → fecha' );
	list( $q, $qc ) = Query::events( array( 'source' => 'by_name', 'event_categories' => array( 'no-existe' ) ), 'post_query_' );
	$check( 'by_id' === $q['post_query_post_type'] && array() === $q['post_query_posts_ids'] && 'ps.dyn.events_terms' === $qc[0][0], 'categorías inexistentes: selección vacía (falla cerrado, nunca todos los eventos)' );
	update_option( 'posts_per_page', 12 );
	$check( 12 === Query::limit( array( 'posts_limit' => 0 ), 5 ) && 100 === Query::limit( array( 'posts_limit' => -1 ), 5 ) && 3 === Query::limit( array(), 3 ) && 100 === Query::limit( array( 'posts_limit' => 500 ), 5 ) && 4 === Query::limit( array( 'limit' => '4' ), 6, 'limit' ), 'límites (0 = el del sitio, negativo = todas acotadas a 100)' );

	/* ============================================================== 3. Loop_Item */

	reset_all();
	$parts = array( 'title' => 'h2', 'excerpt' => 20, 'terms' => 'category', 'meta' => array( 'date', 'author', 'x' ), 'button' => '<b>Leer</b>', 'align' => 'center', 'height' => array( '' => array( 'unit' => 'vh', 'size' => 70 ) ), 'ratio' => '16/9', 'overlay' => array( 'color' => '#000000', 'opacity' => 0.5 ), 'colors' => array( 'title' => '#fff', 'text' => 'red;}', 'bogus' => '#000' ) );
	$norm  = Loop_Item::normalize( 'post', $parts );
	$check( array( 'author', 'date' ) === $norm['meta'] && 'Leer' === $norm['button'] && array( 'title' => '#fff' ) === $norm['colors'], 'partes normalizadas: metadatos en su orden, texto plano y colores validados' );
	$check( $norm === Loop_Item::normalize( 'post', $norm ) && Loop_Item::key( 'post', $parts ) === Loop_Item::key( 'post', $norm ), 'normalizar es idempotente y la huella no cambia' );
	$check( Loop_Item::key( 'post', $parts ) === Loop_Item::key( 'tribe_events', $parts ) && Loop_Item::key( 'post', $parts ) !== Loop_Item::key( 'product', $parts ), 'eventos comparten el Loop Item de entradas; productos no' );
	$tree = Loop_Item::elements( 'post', $parts );
	$box  = $tree[0];
	$check( 'container' === $box['elType'] && false === $box['isInner'] && 'full' === $box['settings']['content_width'], 'un contenedor exterior (isInner falso)' );
	$check( false !== strpos( $box['settings']['__dynamic__']['background_image'], 'name="post-featured-image"' ) && 'cover' === $box['settings']['background_size'], 'imagen destacada de fondo con etiqueta dinámica' );
	$check( 'selector{aspect-ratio:16/9}' === $box['settings']['custom_css'] && 70 === $box['settings']['min_height']['size'] && 'center' === $box['settings']['flex_align_items'], 'proporción, altura mínima y alineación' );
	$check( '#000000' === $box['settings']['background_overlay_color'] && 0.5 === $box['settings']['background_overlay_opacity']['size'], 'superposición' );
	$check( array( 'post-info', 'theme-post-title', 'post-info', 'theme-post-excerpt', 'button' ) === widgets_of( $tree[0]['elements'] ), 'términos, título, metadatos, extracto y botón, en ese orden' );
	$title = $box['elements'][1]['settings'];
	$check( 'h2' === $title['header_size'] && false !== strpos( $title['__dynamic__']['title'], 'name="post-title"' ) && false !== strpos( $title['__dynamic__']['link'], 'name="post-url"' ) && 'center' === $title['align'], 'título dinámico enlazado a la entrada' );
	$check( 'category' === $box['elements'][0]['settings']['icon_list'][0]['taxonomy'][0] && 'terms' === $box['elements'][0]['settings']['icon_list'][0]['type'], 'términos de la categoría' );
	$excerpt = $box['elements'][3]['settings']['__dynamic__']['excerpt'];
	$check( false !== strpos( $excerpt, 'name="post-excerpt"' ) && false !== strpos( urldecode( $excerpt ), '"max_length":20' ) && false !== strpos( urldecode( $excerpt ), '"apply_to_post_content":"yes"' ), 'extracto con su número de palabras, también del contenido' );
	$check( 'Leer' === $box['elements'][4]['settings']['text'] && false !== strpos( $box['elements'][4]['settings']['__dynamic__']['link'], 'post-url' ), 'botón al enlace de la entrada' );
	$ids = array();
	walk( $tree, static function ( $e ) use ( &$ids ) {
		$ids[] = $e['id'];
	} );
	$check( count( $ids ) === count( array_unique( $ids ) ) && ! array_filter( $ids, static function ( $i ) { return ! preg_match( '/^[0-9a-f]{7}$/', $i ); } ), 'IDs de 7 cifras hexadecimales, únicos' );
	$check( $tree === Loop_Item::elements( 'post', $parts ), 'árbol determinista' );
	$ptree = Loop_Item::elements( 'product', array( 'title' => 'h1', 'terms' => 'product_cat', 'excerpt' => 0, 'price' => true, 'cart' => true, 'meta' => array( 'author' ) ) );
	$check( array( 'post-info', 'woocommerce-product-title', 'theme-post-excerpt', 'woocommerce-product-price', 'woocommerce-product-add-to-cart' ) === widgets_of( $ptree[0]['elements'] ), 'Loop Item de producto: términos, título, extracto, precio y añadir al carrito (sin metadatos de entrada)' );
	$check( false !== strpos( $ptree[0]['elements'][1]['settings']['__dynamic__']['title'], 'woocommerce-product-title-tag' ), 'título de producto dinámico' );
	$check( array( 'post-info', 'theme-post-title', 'button' ) === Loop_Item::widgets( 'post', array( 'title' => 'h3', 'terms' => 'category', 'button' => 'Ver' ) ), 'widgets usados según las partes' );
	$check( in_array( 'post-url', Loop_Item::tags( 'post', array( 'title' => 'h3' ) ), true ) && ! in_array( 'post-excerpt', Loop_Item::tags( 'post', array( 'title' => 'h3' ) ), true ), 'etiquetas usadas según las partes' );
	$static = Loop_Item::elements( 'tribe_events', array( 'button' => 'Entradas', 'button_url' => 'https://entradas.example.test/' ) );
	$check( 'https://entradas.example.test/' === $static[0]['elements'][0]['settings']['link']['url'] && ! isset( $static[0]['elements'][0]['settings']['__dynamic__'] ), 'botón con enlace fijo (eventos)' );
	$check( '' === Loop_Item::normalize( 'post', array( 'button' => 'X', 'button_url' => 'javascript:alert(1)' ) )['button_url'], 'enlace fijo javascript: rechazado' );

	// Creación, publicación y reutilización.
	$created = Loop_Item::ensure( 'post', $parts );
	$check( $created > 0 && 'publish' === get_post_status( $created ), 'ensure() crea el Loop Item y lo publica' );
	$check( 'loop-item' === get_post_meta( $created, '_elementor_template_type', true ) && 'post' === get_post_meta( $created, '_elementor_source', true ) && array( 'source' => 'post' ) === get_post_meta( $created, '_elementor_page_settings', true ), 'tipo loop-item, origen post en la meta y en los ajustes de página' );
	$check( $tree === loop_data( $created ), 'el contenido guardado es el árbol (con sus barras intactas)' );
	$check( Loop_Item::key( 'post', $parts ) === get_post_meta( $created, Loop_Item::META, true ), 'meta con la huella' );
	$check( in_array( 'loop-item', array_map( static function ( $id ) { return get_term( $id )->slug ?? ''; }, (array) ( $GLOBALS['wp']['object_terms'][1][ $created ]['elementor_library_type'] ?? array() ) ), true ), 'término loop-item de la biblioteca' );
	$title_text = get_post( $created )->post_title;
	$check( vendor_free( array( $title_text ) ) && false !== strpos( $title_text, 'entradas' ), 'título de la plantilla sin proveedor' );
	$row = Digitalisimo\Elements\Legacy\Platform\Registry::get( 'loop', Loop_Item::key( 'post', $parts ) );
	$check( is_array( $row ) && 'done' === $row['state'] && $created === (int) $row['dest'] && md5( (string) json_encode( $tree ) ) === $row['hash'], 'fila del registro `loop` en done con su huella' );
	$check( in_array( 'ps.dyn.loop_created', site_codes(), true ), 'creación informada en el sitio' );
	Loop_Item::reset();
	$check( $created === Loop_Item::ensure( 'post', $parts ) && $created === Loop_Item::ensure( 'tribe_events', $parts ), 'reutilizado por la huella (también para eventos)' );
	$other = Loop_Item::ensure( 'post', array( 'title' => 'h3' ) );
	$check( $other > 0 && $other !== $created, 'otras partes, otro Loop Item' );
	$ev_source = Loop_Item::ensure( 'tribe_events', array( 'title' => 'h4' ) );
	$check( 'post' === get_post_meta( $ev_source, '_elementor_source', true ), 'eventos: source = post, nunca tribe_events (C1-29)' );
	$check( 0 === Loop_Item::ensure( 'tribe_events_x', $parts ), 'origen desconocido: 0' );

	// Pureza: ni Loop Items ni filas del registro en el editor ni en el análisis.
	foreach ( array( 'editor', 'analysis' ) as $mode ) {
		Loop_Item::reset();
		$before = count( $GLOBALS['wp']['posts'][1] );
		$reg    = get_option( 'digitalisimo_migration_reg_loop' );
		Context::begin( 7, $mode );
		$check( Context::pure() && 0 === Loop_Item::ensure( 'post', array( 'title' => 'h5' ) ), $mode . ': un Loop Item nuevo no se crea (0)' );
		$check( $created === Loop_Item::ensure( 'post', $parts ), $mode . ': el ya creado se reutiliza (sólo lectura)' );
		Context::end();
		$check( count( $GLOBALS['wp']['posts'][1] ) === $before && get_option( 'digitalisimo_migration_reg_loop' ) === $reg, $mode . ': nada escrito' );
	}

	// Falla cerrado: sin contenedores, sin el documento o sin un widget, nada se crea.
	foreach ( array( array( 'features' => array( 'container' => false ) ), array( 'features' => array( 'loop-item' => false ) ), array( 'ready' => array( 'theme-post-excerpt' => false ) ), array( 'tags' => array( 'post-featured-image' => false ) ) ) as $i => $over ) {
		env( $over );
		$check( ! Loop_Item::ready( 'post', array( 'title' => 'h6', 'excerpt' => 5 ) ) && 0 === Loop_Item::ensure( 'post', array( 'title' => 'h6', 'excerpt' => 5 ) ), 'sin requisito ' . $i . ': no se crea' );
	}
	env( array( 'woocommerce' => false ) );
	$check( ! Loop_Item::ready( 'product', array( 'title' => 'h2' ) ), 'productos sin WooCommerce: no' );
	env();
	if ( ! empty( $GLOBALS['f8f']['double_documents'] ) ) {
		$GLOBALS['f8f']['corrupt'] = true;
		$check( 0 === Loop_Item::ensure( 'post', array( 'title' => 'p' ) ), 'huella releída distinta: no se usa' );
		$GLOBALS['f8f']['corrupt'] = false;
		$drafts = array_filter( $GLOBALS['wp']['posts'][1], static function ( $p ) { return 'draft' === $p['post_status'] && 'elementor_library' === $p['post_type']; } );
		$check( ! array_filter( $GLOBALS['wp']['posts'][1], static function ( $p ) { return 'publish' === $p['post_status'] && '[]' === get_post_meta( $p['ID'], '_elementor_data', true ); } ), 'nunca un bucle vacío publicado' );
	}
	$GLOBALS['wp']['fail_insert'] = true;
	$check( 0 === Loop_Item::ensure( 'post', array( 'title' => 'span' ) ), 'inserción fallida: 0' );
	$GLOBALS['wp']['fail_insert'] = false;
	// Una petición que murió tras insertar el borrador y antes de publicarlo: la siguiente reutiliza ese borrador íntegro.
	$crash = Loop_Item::ensure( 'post', array( 'title' => 'h2', 'meta' => array( 'comments' ) ) );
	wp_update_post( array( 'ID' => $crash, 'post_status' => 'draft' ) );
	Digitalisimo\Elements\Legacy\Platform\Registry::put( 'loop', Loop_Item::key( 'post', array( 'title' => 'h2', 'meta' => array( 'comments' ) ) ), array( 'dest' => 0, 'hash' => '', 'state' => 'pending' ) );
	Loop_Item::reset();
	$posts = count( $GLOBALS['wp']['posts'][1] );
	$check( $crash === Loop_Item::ensure( 'post', array( 'title' => 'h2', 'meta' => array( 'comments' ) ) ) && 'publish' === get_post_status( $crash ) && count( $GLOBALS['wp']['posts'][1] ) === $posts, 'reanudación: el borrador íntegro de la petición anterior se publica, sin duplicar' );

	// Reversión por huella: lo editado se omite; sin EMPTY_TRASH_DAYS, a borrador; nunca se borra.
	update_post_meta( $other, '_elementor_data', '[{"editado":true}]' );
	$codes = Loop_Item::revert();
	$names = array_column( $codes, 0 );
	$check( 'draft' === get_post_status( $created ) && 'publish' === get_post_status( $other ), 'sin papelera: a borrador lo que no cambió; lo editado sigue' );
	$check( in_array( 'ps.dyn.revert_skipped', $names, true ) && in_array( 'ps.dyn.revert_loop', $names, true ), 'reversión informada (omitido y retirado)' );
	$check( ! $GLOBALS['wp']['deleted'] && null === Digitalisimo\Elements\Legacy\Platform\Registry::get( 'loop', Loop_Item::key( 'post', $parts ) ) && null !== Digitalisimo\Elements\Legacy\Platform\Registry::get( 'loop', Loop_Item::key( 'post', array( 'title' => 'h3' ) ) ), 'nada borrado; la fila de lo revertido se olvida y la de lo omitido queda' );
	Loop_Item::revert( true );
	$check( 'draft' === get_post_status( $other ) && array() === Digitalisimo\Elements\Legacy\Platform\Registry::all( 'loop' ), 'con --force también lo editado; registro vacío' );
	define( 'EMPTY_TRASH_DAYS', 30 );
	$again = Loop_Item::ensure( 'post', array( 'title' => 'h2', 'terms' => 'post_tag' ) );
	Loop_Item::revert();
	$check( 'trash' === get_post_status( $again ) && in_array( array( 1, $again ), $GLOBALS['wp']['trashed'], true ), 'con papelera: a la papelera' );
	$foreign = f8_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Ajeno' ), array( '_elementor_template_type' => 'loop-item' ) );
	Digitalisimo\Elements\Legacy\Platform\Registry::put( 'loop', 'clave-falsa', array( 'dest' => $foreign, 'hash' => '', 'state' => 'done' ) );
	Loop_Item::revert( true );
	$check( 'publish' === get_post_status( $foreign ), 'una plantilla sin nuestra meta nunca se revierte' );

	/* ============================================================== 4. Familias E y F: entradas */

	reset_all();
	$page = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio' ) );

	// blog con los valores que deja el editor.
	$out  = build( 'blog', fx( 'blog', 'defaults' ), $page );
	$main = main_of( $out );
	$check( 'loop-carousel' === $main['widgetType'] && 'a1b2c3d' === $main['id'] && 'post' === $main['settings']['_skin'], 'blog → loop-carousel (piel de entradas) con el ID original' );
	$loop = (int) $main['settings']['template_id'];
	$check( $loop > 0 && 'publish' === get_post_status( $loop ) && 'loop-item' === get_post_meta( $loop, '_elementor_template_type', true ), 'plantilla Loop Item generada y publicada' );
	$check( 3 === $main['settings']['posts_per_page'] && 'post' === $main['settings']['post_query_post_type'], 'valores por defecto del widget: 3 entradas de `post` (§3.E.4)' );
	$check( '1' === $main['settings']['slides_to_show'] && '1' === $main['settings']['slides_to_show_tablet'] && '1' === $main['settings']['slides_to_show_mobile'] && '1' === $main['settings']['slides_to_show_laptop'], 'una por vista en todos los dispositivos' );
	$check( 'yes' === $main['settings']['autoplay'] && 7000 === $main['settings']['autoplay_speed'] && '' === $main['settings']['pause_on_hover'] && 'yes' === $main['settings']['infinite'], 'reproducción de UIkit: 7000 ms, sin pausa al pasar, en bucle (finite = «Loop»)' );
	$check( 'yes' === $main['settings']['arrows'] && 'bullets' === $main['settings']['pagination'], 'flechas y puntos' );
	$check( is_array( $out ) && '__fragment' !== ( $out['elType'] ?? '' ), 'sin redes de usuario ni sección propia: sin hermanos (los perfiles del proveedor no pasan)' );
	$tree = loop_data( $loop );
	$check( array( 'post-info', 'theme-post-title', 'post-info', 'theme-post-excerpt', 'button' ) === widgets_of( $tree[0]['elements'] ), 'blog: términos, título, metadatos, extracto y botón' );
	$check( 'h1' === $tree[0]['elements'][1]['settings']['header_size'] && 'Read More' === $tree[0]['elements'][4]['settings']['text'], 'título h1 y «Read More» de los valores por defecto' );
	$check( array( 'author', 'date', 'comments' ) === array_column( $tree[0]['elements'][2]['settings']['icon_list'], 'type' ), 'autor, fecha y comentarios' );
	// Segunda vuelta de J (P0.B1.9): el origen sólo enlaza al autor; la fecha y los comentarios, sin enlace.
	$check( array( 'yes', '', '' ) === array_column( $tree[0]['elements'][2]['settings']['icon_list'], 'link' ), 'sólo el autor enlaza (la fecha y los comentarios del origen son texto)' );
	$check( 'selector{aspect-ratio:16/9}' === $tree[0]['settings']['custom_css'] && 440 === $tree[0]['settings']['min_height']['size'], 'proporción 16:9 con el mínimo de 440 px de blog' );
	$c = codes( $page );
	$check( in_array( 'ps.dyn.loop', $c, true ) && in_array( 'ps.dyn.loss.effect', $c, true ) && in_array( 'ps.dyn.h1', $c, true ), 'informe: carrusel, efecto fundido perdido y h1' );
	$check( ! array_filter( $c, static function ( $x ) { return 0 !== strpos( $x, 'ps.dyn.' ); } ), 'sólo códigos ps.dyn.*' );
	$check( vendor_free( $out ) && vendor_free( $tree ), 'blog por defecto: nada del proveedor' );

	// blog editado: skin, consulta, alturas responsivas, redes del usuario y «Scroll Down».
	$out  = build( 'blog', fx( 'blog', 'custom' ), $page, 'b2c3d4e' );
	$check( '__fragment' === $out['elType'] && 3 === count( $out['elements'] ), 'blog con redes y sección propia: fragmento (carrusel, redes y botón)' );
	$main = main_of( $out );
	$check( 'loop-carousel' === $main['widgetType'] && 6 === $main['settings']['posts_per_page'] && array( '52' ) === $main['settings']['post_query_include_term_ids'] && 'post_title' === $main['settings']['post_query_orderby'] && 'asc' === $main['settings']['post_query_order'], 'consulta del usuario: 6, términos por tt_id, por título ascendente' );
	$check( '' === $main['settings']['autoplay'] && '' === $main['settings']['infinite'], 'sin reproducción ni bucle' );
	$check( 'post' === $main['settings']['_skin'] && ! isset( $main['settings']['_title'] ) && 'mi-carrusel' === $main['settings']['_css_classes'] && 'portada' === $main['settings']['_element_id'] && '10' === $main['settings']['_margin']['top'] && 'hidden-mobile' === $main['settings']['hide_mobile'], 'claves comunes de la lista blanca; nunca _skin del origen ni _title' );
	$social = $out['elements'][1];
	$check( 'social-icons' === $social['widgetType'] && 1 === count( $social['settings']['social_icon_list'] ) && 'https://www.facebook.com/negocio-de-prueba' === $social['settings']['social_icon_list'][0]['link']['url'], 'redes: sólo la fila del usuario' );
	$scroll = $out['elements'][2];
	$check( 'digitalisimo-scroll-button' === $scroll['widgetType'] && 'contenido' === $scroll['settings']['section_id'] && 'Bajar' === $scroll['settings']['scroll_button_text'] && 600 === $scroll['settings']['duration']['size'], '«Scroll Down» con su sección' );
	$check( preg_match( '/^[0-9a-f]{7}$/', $social['id'] ) && $social['id'] !== $scroll['id'], 'IDs de los hermanos' );
	$loop2 = (int) $main['settings']['template_id'];
	$tree  = loop_data( $loop2 );
	$check( $loop2 !== $loop && array( 'post-info', 'theme-post-title', 'post-info', 'theme-post-excerpt', 'button' ) === widgets_of( $tree[0]['elements'] ), 'otras partes, otro Loop Item' );
	$check( 'Leer la nota' === $tree[0]['elements'][4]['settings']['text'] && array( 'author', 'date' ) === array_column( $tree[0]['elements'][2]['settings']['icon_list'], 'type' ) && 'center' === $tree[0]['elements'][1]['settings']['align'], 'botón propio, sin comentarios, centrado' );
	$check( 80 === $tree[0]['settings']['min_height']['size'] && 'vh' === $tree[0]['settings']['min_height']['unit'] && 60 === $tree[0]['settings']['min_height_mobile']['size'] && ! isset( $tree[0]['settings']['custom_css'] ), 'altura responsiva en vh, sin proporción' );
	$c = codes( $page );
	$check( in_array( 'ps.dyn.loss.skin', $c, true ) && ! in_array( 'ps.dyn.loss.kenburns', $c, true ) && in_array( 'ps.dyn.social', $c, true ) && in_array( 'ps.dyn.scroll', $c, true ), 'skin, redes y botón informados (Ken Burns lo informa Converter: ps.dropped.kenburns)' );
	$check( ! in_array( 'ps.dyn.loss.styles', $c, true ), 'ajustes de contenido (altura, alineación, duración) no cuentan como estilos perdidos' );

	// Extensiones de Prime Slider: las informa Converter (E) con ps.dropped.*; F no las repite ni las cuenta como estilos.
	$psx = array( 'reveal_effects_enable' => 'yes', 'reveal_effects_color' => '#FF0000', 'reveal_effects_direction' => 'tb', 'reveal_effects_speed' => array( 'size' => 800 ), 'animation_status' => 'yes', 'animation_of' => array( '.x' ), 'anim_duration' => array( 'size' => 2 ), 'anim_scale' => array( 'size' => 1.2 ), 'ps_widget_abg' => 'yes', 'ps_widget_abg_parent' => '.hero', 'kenburns_animation' => 'yes', 'kenburns_reverse' => 'yes' );
	foreach ( array( 'blog', 'fluent', 'mercury', 'pacific', 'woostand', 'event-calendar' ) as $id ) {
		$out  = build( $id, fx( $id, 'defaults' ) + $psx, $page );
		$c    = codes( $page );
		$dupe = array_filter( $c, static function ( $x ) {
			return 0 === strpos( $x, 'ps.dropped.' ) || 1 === preg_match( '/^ps\.dyn\..*(reveal|animation|abg|adaptive|kenburns)/', $x );
		} );
		$check( is_array( $out ) && ! $dupe && ! in_array( 'ps.dyn.loss.styles', $c, true ), $id . ': las extensiones de Prime Slider no dejan códigos ps.dyn.* ni cuentan como estilos perdidos' );
		$s = (array) main_of( $out )['settings'];
		$check( ! array_intersect_key( $s, $psx ), $id . ': sus claves no pasan al destino' );
	}

	// Rasgos propios del elemento siguen en el principal; las extensiones de Element Pack las añade y convierte Converter (§3.E.0.5).
	$ext  = array( 'ep_display_conditions_enable' => 'yes', 'element_pack_wrapper_link' => array( 'url' => '/x/' ), 'digitalisimo_tooltip' => 'yes', '_padding' => array( 'unit' => 'px', 'top' => '5' ), '__dynamic__' => array( 'digitalisimo_tooltip_text' => '[elementor-tag id="1" name="post-title" settings="%7B%7D"]', 'button_text' => 'x' ), '__globals__' => array( '_background_color' => 'globals/colors?id=primary' ) );
	$main = main_of( build( 'blog', $ext, $page, 'e1e2e3e' ) );
	$check( ! isset( $main['settings']['ep_display_conditions_enable'] ) && ! isset( $main['settings']['element_pack_wrapper_link'] ) && 'yes' === $main['settings']['digitalisimo_tooltip'] && '5' === $main['settings']['_padding']['top'], 'rasgos propios y claves comunes en el principal; las extensiones de Element Pack, no (Converter las añade si faltan)' );
	$check( isset( $main['settings']['__dynamic__']['digitalisimo_tooltip_text'] ) && ! isset( $main['settings']['__dynamic__']['button_text'] ) && 'globals/colors?id=primary' === $main['settings']['__globals__']['_background_color'], 'sus dinámicos y globales, sin los del contenido del origen' );
	codes( $page );
	$check( vendor_free( $out ), 'blog editado: nada del proveedor (el perfil del proveedor se quitó)' );

	// blog con estilos (y valores maliciosos).
	$out  = build( 'blog', fx( 'blog', 'styled' ), $page, 'c3d4e5f' );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$json = json_encode( $tree );
	$check( '#FF0000' === $tree[0]['elements'][1]['settings']['title_color'] && 'rgba(255,255,255,0.8)' === $tree[0]['elements'][3]['settings']['title_color'], 'colores del título y del extracto' );
	$check( false === strpos( $json, 'display:none' ) && false === strpos( $json, 'expression' ) && '#0000FF' === $tree[0]['elements'][4]['settings']['background_color'] && ! isset( $tree[0]['elements'][4]['settings']['button_text_color'] ), 'valores CSS maliciosos rechazados; el fondo válido del botón pasa' );
	$check( 'selector{aspect-ratio:21/9}' === $tree[0]['settings']['custom_css'] && 500 === $tree[0]['settings']['min_height']['size'], 'proporción 21:9 y mínimo de 500 px' );
	$check( ! isset( $tree[0]['settings']['background_overlay_color'] ), 'una superposición con color inválido no se aplica' );
	$c = codes( $page );
	$check( in_array( 'ps.dyn.loss.styles', $c, true ) && ( in_array( 'ps.dyn.review.custom_css', $c, true ) || class_exists( 'Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Builder' ) ), 'estilos sin equivalente contados; CSS propio con clases del original a revisar' );
	$check( 'selector .bdt-title-tag{color:red}' === $main['settings']['custom_css'], 'el CSS propio se copia tal cual (y se revisa)' );
	$check( '__fragment' !== ( $out['elType'] ?? '' ), 'sin redes ni botón si estaban ocultos' );

	// flogia y fluent.
	$out  = build( 'flogia', fx( 'flogia', 'defaults' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( 5 === $main['settings']['posts_per_page'] && 'yes' === $main['settings']['arrows'] && 'bullets' === $main['settings']['pagination'] && 520 === $tree[0]['settings']['min_height']['size'], 'flogia: 5 entradas, flechas y puntos juntos, mínimo de 520 px' );
	$check( in_array( 'ps.dyn.loss.thumbs', codes( $page ), true ), 'flogia: miniaturas perdidas' );
	$out  = build( 'flogia', fx( 'flogia', 'custom' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( 'page' === $main['settings']['post_query_post_type'] && 4 === $main['settings']['posts_per_page'] && 3000 === $main['settings']['autoplay_speed'] && 'yes' === $main['settings']['pause_on_hover'], 'flogia editado: páginas, 4, 3000 ms y pausa' );
	$check( 'bullets' === $main['settings']['pagination'] && '' === $main['settings']['arrows'] && in_array( 'ps.dyn.nav_none', codes( $page ), true ), 'sin navegación: puntos (WCAG) e informado' );
	$check( ! in_array( 'post-info', array_slice( widgets_of( $tree[0]['elements'] ), 0, 1 ), true ) || 'author' === ( $tree[0]['elements'][1]['settings']['icon_list'][0]['type'] ?? '' ), 'flogia sin categoría' );
	$check( false !== strpos( urldecode( $tree[0]['elements'][array_search( 'theme-post-excerpt', widgets_of( $tree[0]['elements'] ), true )]['settings']['__dynamic__']['excerpt'] ), '"max_length":12' ), 'flogia: 12 palabras' );
	$out  = build( 'fluent', fx( 'fluent', 'defaults' ), $page );
	$main = main_of( $out );
	$check( 9 === $main['settings']['posts_per_page'] && 'yes' === $main['settings']['arrows'] && '' === $main['settings']['pagination'], 'fluent: 9 entradas (update_control), sólo flechas' );
	$check( '__fragment' !== ( $out['elType'] ?? '' ), 'fluent por defecto: sin hermanos (sólo perfiles del proveedor y my-header)' );
	codes( $page );
	$out  = build( 'fluent', fx( 'fluent', 'custom' ), $page );
	$main = main_of( $out );
	$check( 'by_id' === $main['settings']['post_query_post_type'] && array( '11', '12' ) === $main['settings']['post_query_posts_ids'], 'fluent: selección manual' );
	$c = codes( $page );
	$check( ! in_array( 'ps.dyn.loss.thumbs', $c, true ) && ! in_array( 'ps.dyn.loss.avatar', $c, true ), 'fluent sin miniaturas ni avatar: nada que informar' );

	// Familia F: una por vista.
	$out  = build( 'mercury', fx( 'mercury', 'custom' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( 'loop-carousel' === $main['widgetType'] && 900 === $main['settings']['speed'] && 'yes' === $main['settings']['autoplay'] && 5000 === $main['settings']['autoplay_speed'] && 'yes' === $main['settings']['infinite'], 'mercury: velocidad de Swiper, reproducción y bucle' );
	$check( array( 'current_post', 'terms' ) === $main['settings']['post_query_exclude'] && array( '52' ) === $main['settings']['post_query_exclude_term_ids'] && 'exact' === $main['settings']['post_query_select_date'], 'mercury: exclusiones y fechas' );
	$check( 640 === $tree[0]['settings']['min_height']['size'] && 420 === $tree[0]['settings']['min_height_mobile']['size'] && ! in_array( 'theme-post-excerpt', widgets_of( $tree[0]['elements'] ), true ) && 'h3' === $tree[0]['elements'][1]['settings']['header_size'], 'mercury: altura del elemento, sin extracto, título h3' );
	$check( in_array( 'ps.dyn.loss.effect', codes( $page ), true ), 'mercury: el fundido se informa' );
	$out  = build( 'storker', fx( 'storker', 'defaults' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( 3 === $main['settings']['posts_per_page'] && '' === $main['settings']['arrows'] && 'bullets' === $main['settings']['pagination'] && 'More Details' === $tree[0]['elements'][ count( $tree[0]['elements'] ) - 1 ]['settings']['text'], 'storker: 3 entradas, puntos y «More Details»' );
	codes( $page );
	$out  = build( 'storker', fx( 'storker', 'custom' ), $page );
	$main = main_of( $out );
	$check( 'yes' === $main['settings']['pause_on_hover'] && 3000 === $main['settings']['autoplay_speed'] && '' === $main['settings']['infinite'] && 1200 === $main['settings']['speed'], 'storker editado' );
	$check( ! in_array( 'ps.dyn.loss.effect', codes( $page ), true ), 'storker con «slide»: sin pérdida de efecto' );
	$out  = build( 'knily', fx( 'knily', 'defaults' ), $page );
	$c    = codes( $page );
	$check( in_array( 'ps.dyn.loss.thumbs', $c, true ) && in_array( 'ps.dyn.loss.share', $c, true ), 'knily: miniaturas y compartir' );
	$out  = build( 'knily', fx( 'knily', 'custom' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$c    = codes( $page );
	$check( 'related' === $main['settings']['post_query_post_type'] && in_array( 'ps.dyn.related', $c, true ) && ! in_array( 'ps.dyn.loss.thumbs', $c, true ) && '' === $main['settings']['pagination'], 'knily: relacionadas, miniaturas ocultas' );
	$check( 'Más detalles' === $tree[0]['elements'][ count( $tree[0]['elements'] ) - 1 ]['settings']['text'], 'texto del botón sin HTML' );

	// Familia F: columnas.
	$out  = build( 'pacific', fx( 'pacific', 'defaults' ), $page );
	$main = main_of( $out );
	$check( '3' === $main['settings']['slides_to_show'] && '2' === $main['settings']['slides_to_show_tablet'] && '1' === $main['settings']['slides_to_show_mobile'] && '3' === $main['settings']['slides_to_show_widescreen'] && '2' === $main['settings']['slides_to_show_mobile_extra'], 'pacific: 3/2/1 columnas en todos los puntos de corte' );
	$check( 5 === $main['settings']['image_spacing_custom']['size'] && 0 === $main['settings']['image_spacing_custom_mobile']['size'], 'pacific: separación' );
	$c = codes( $page );
	$check( in_array( 'ps.dyn.loss.modal', $c, true ) && in_array( 'ps.dyn.loss.centered', $c, true ), 'pacific: ventana modal y centradas' );
	$out  = build( 'pacific', fx( 'pacific', 'custom' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( '4' === $main['settings']['slides_to_show'] && '2' === $main['settings']['slides_to_scroll'] && 10 === $main['settings']['image_spacing_custom']['size'] && 'bullets' === $main['settings']['pagination'], 'pacific editado: 4 por vista, paso 2, sin navegación → puntos' );
	$check( 'Seguir leyendo' === $tree[0]['elements'][ count( $tree[0]['elements'] ) - 1 ]['settings']['text'] && 420 === $tree[0]['settings']['min_height']['size'], 'pacific: «Leer más» propio y altura por defecto' );
	$c = codes( $page );
	$check( ! in_array( 'ps.dyn.loss.centered', $c, true ) && in_array( 'ps.dyn.nav_none', $c, true ), 'pacific editado: sin centradas; sin navegación informado' );
	$out  = build( 'rubix', fx( 'rubix', 'custom' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( '3' === $main['settings']['slides_to_show'] && '2' === $main['settings']['slides_to_show_tablet'] && '1' === $main['settings']['slides_to_show_mobile'] && 'button' !== end( $tree[0]['elements'] )['widgetType'], 'rubix: columnas del usuario, sin «Leer más»' );
	codes( $page );
	$out  = build( 'vertex', fx( 'vertex', 'custom' ), $page );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( '5' === $main['settings']['slides_to_show'] && 'mi_consulta' === $main['settings']['post_query_query_id'] && in_array( 'ps.dyn.query_id', codes( $page ), true ), 'vertex: 5 por vista y query_id a revisar' );
	$check( array( 'theme-post-title' ) === widgets_of( $tree[0]['elements'] ) && 380 === $tree[0]['settings']['min_height']['size'], 'vertex: sólo título, altura del slider' );
	$out  = build( 'fiestar', fx( 'fiestar', 'custom' ), $page );
	$main = main_of( $out );
	$check( 30 === $main['settings']['image_spacing_custom']['size'] && in_array( 'ps.dyn.loss.wrapper_link', codes( $page ), true ), 'fiestar: separación y enlace de la diapositiva' );
	$out  = build( 'marble', fx( 'marble', 'defaults' ), $page );
	$check( in_array( 'ps.dyn.loss.count', codes( $page ), true ), 'marble: contador' );
	$out  = build( 'marble', fx( 'marble', 'custom' ), $page );
	$check( '2' === main_of( $out )['settings']['slides_to_show_mobile'] && ! in_array( 'ps.dyn.loss.count', codes( $page ), true ), 'marble editado' );
	$out  = build( 'material', fx( 'material', 'defaults' ), $page );
	$main = main_of( $out );
	$check( '2' === $main['settings']['slides_to_show'] && '1' === $main['settings']['slides_to_show_mobile'] && 300 === $main['settings']['speed'] && in_array( 'ps.dyn.loss.effect_material', codes( $page ), true ), 'material: 2/2/1, 300 ms y efecto Material perdido' );
	$out = build( 'material', fx( 'material', 'custom' ), $page );
	$check( in_array( 'ps.dyn.loss.featured_only', codes( $page ), true ), 'material: «sólo con imagen destacada» informado' );

	// Respaldo: sin contenedores → rejilla del widget Entradas (y revisar en una página publicada).
	env( array( 'features' => array( 'container' => false ) ) );
	$out  = build( 'blog', fx( 'blog', 'custom' ), $page, 'd4e5f6a' );
	$main = main_of( $out );
	$check( 'posts' === $main['widgetType'] && 'classic' === $main['settings']['_skin'] && 6 === $main['settings']['classic_posts_per_page'] && '1' === $main['settings']['classic_columns'], 'blog sin contenedores: rejilla clásica de Entradas (una columna)' );
	$check( array( '52' ) === $main['settings']['posts_include_term_ids'] && 'post' === $main['settings']['posts_post_type'] && array( 'author', 'date' ) === $main['settings']['classic_meta_data'] && 'Leer la nota' === $main['settings']['classic_read_more_text'] && array( 'category' ) === $main['settings']['digitalisimo_post_terms'], 'respaldo con la consulta (tt_id intactos), metadatos, botón y categorías' );
	$check( ! isset( $main['settings']['posts_source'] ) && ! isset( $main['settings']['posts_limit'] ) && ! isset( $main['settings']['template_id'] ), 'sin claves de Prime Slider en el respaldo' );
	$report = Report::take();
	$items  = (array) ( $report['docs'][ $page ]['items'] ?? array() );
	$fall   = array_values( array_filter( $items, static function ( $i ) { return 'ps.dyn.fallback_posts' === $i['code']; } ) );
	$check( $fall && ! empty( $fall[0]['review'] ) && Report::pending( $report ), 'respaldo en una página publicada: «Pendiente de revisar»' );
	$draft = f8_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Borrador' ) );
	build( 'mercury', array(), $draft );
	$report = Report::take();
	$fall   = array_values( array_filter( (array) ( $report['docs'][ $draft ]['items'] ?? array() ), static function ( $i ) { return 'ps.dyn.fallback_posts' === $i['code']; } ) );
	$check( $fall && empty( $fall[0]['review'] ), 'en un borrador, sólo «con diferencias»' );
	env( array( 'features' => array( 'container' => false ), 'ready' => array( 'posts' => false ) ) );
	$check( null === build( 'mercury', array(), $page ) && in_array( 'ps.dyn.blocked', codes( $page ), true ), 'sin destino ni respaldo: null y «bloqueado»' );
	$check( 'blocked' === Dynamic::state( 'mercury' ), 'state(): bloqueado' );
	env( array( 'ready' => array( 'loop-carousel' => false ) ) );
	$check( 'posts' === main_of( build( 'pacific', array(), $page ) )['widgetType'] && 'fallback' === Dynamic::state( 'pacific' ), 'sin Loop Carousel: respaldo' );
	env();
	$check( 'target' === Dynamic::state( 'blog' ) && 'target' === Dynamic::state( 'prime-slider-material' ), 'state(): destino' );
	$check( array( 'ps.dyn.loop' => 1 ) === Dynamic::describe( 'blog' ) && array( 'ps.dyn.products_carousel' => 1 ) === Dynamic::describe( 'woocircle' ) && array( 'ps.dyn.events' => 1 ) === Dynamic::describe( 'event-calendar' ), 'describe(): código principal previsto' );
	env( array( 'features' => array( 'container' => false ), 'woocommerce' => false, 'events' => false ) );
	$check( array( 'ps.dyn.fallback_posts' => 1 ) === Dynamic::describe( 'pacific' ) && array( 'ps.dyn.no_woocommerce' => 1 ) === Dynamic::describe( 'woostand' ) && array( 'ps.dyn.events_unavailable' => 1 ) === Dynamic::describe( 'event-calendar' ), 'describe(): respaldo, sin WooCommerce y descarte declarado' );
	env();

	// Pureza en el editor: sin Loop Item nuevo → null (el marcador espera a la etapa `markers`); con uno, se reutiliza.
	reset_all();
	$page   = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Inicio' ) );
	$before = count( $GLOBALS['wp']['posts'][1] );
	Report::begin( 'editor' );
	Context::begin( $page, 'editor' );
	$check( null === build( 'blog', fx( 'blog', 'defaults' ), $page ) && null === build( 'woocommerce', fx( 'woocommerce', 'defaults' ), $page ) && null === build( 'event-calendar', array(), $page ), 'editor sin Loop Item: null (marcador para la etapa markers)' );
	$check( count( $GLOBALS['wp']['posts'][1] ) === $before && false === get_option( 'digitalisimo_migration_reg_loop', false ), 'editor: ni plantillas ni registro' );
	$check( 'woocommerce-products' === main_of( build( 'woocircle', fx( 'woocircle', 'defaults' ), $page ) )['widgetType'], 'editor: lo que no necesita efectos se convierte igual' );
	Context::end();
	$check( Report::is_empty( Report::take() ), 'editor: el informe descarta todo' );
	Report::begin( 'run' );
	Context::begin( $page, 'run' );
	$run = build( 'blog', fx( 'blog', 'defaults' ), $page );
	Context::end();
	Context::begin( $page, 'editor' );
	$ed = build( 'blog', fx( 'blog', 'defaults' ), $page );
	Context::end();
	$check( is_array( $ed ) && $run === $ed, 'editor tras la tarea: el mismo resultado con el Loop Item ya creado' );
	env( array( 'features' => array( 'container' => false ) ) );
	Context::begin( $page, 'editor' );
	$check( 'posts' === main_of( build( 'mercury', array(), $page ) )['widgetType'], 'editor sin contenedores: el mismo respaldo que la tarea' );
	Context::end();
	env();

	/* ============================================================== 5. Familia G: WooCommerce */

	reset_all();
	$shop = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Tienda' ) );
	$out  = build( 'woocommerce', fx( 'woocommerce', 'defaults' ), $shop );
	$main = main_of( $out );
	$check( 'loop-carousel' === $main['widgetType'] && 'product' === $main['settings']['_skin'] && 'product' === $main['settings']['product_query_post_type'] && 3 === $main['settings']['posts_per_page'], 'woocommerce → loop-carousel con la piel de productos, 3 productos' );
	$check( ! isset( $main['settings']['post_query_post_type'] ) && '1' === $main['settings']['slides_to_show'], 'consulta de productos y una por vista' );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( 'product' === get_post_meta( (int) $main['settings']['template_id'], '_elementor_source', true ), 'Loop Item de producto (source = product)' );
	$check( array( 'post-info', 'woocommerce-product-title', 'theme-post-excerpt', 'woocommerce-product-price', 'woocommerce-product-add-to-cart' ) === widgets_of( $tree[0]['elements'] ) && 'product_cat' === $tree[0]['elements'][0]['settings']['icon_list'][0]['taxonomy'][0], 'categorías, título, extracto, precio y formulario de compra' );
	$check( '__fragment' !== ( $out['elType'] ?? '' ) && in_array( 'ps.dyn.loss.layout', codes( $shop ), true ), 'sin redes del usuario; composición informada' );
	$out  = build( 'woocommerce', fx( 'woocommerce', 'custom' ), $shop );
	$main = main_of( $out );
	$check( 'sale' === $main['settings']['product_query_post_type'] && array( '52' ) === $main['settings']['product_query_include_term_ids'] && 'popularity' === $main['settings']['product_query_orderby'] && 8 === $main['settings']['posts_per_page'], 'woocommerce editado: ofertas, términos que existen, por ventas, 8' );
	$check( '__fragment' === $out['elType'] && 'fab fa-instagram' === $out['elements'][1]['settings']['social_icon_list'][0]['social_icon']['value'], 'redes de texto: icono deducido del dominio' );
	$c = codes( $shop );
	$check( in_array( 'ps.dyn.loss.product_filters', $c, true ) && in_array( 'ps.dyn.social', $c, true ), 'filtros de productos y redes informados' );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( false !== strpos( urldecode( $tree[0]['elements'][2]['settings']['__dynamic__']['excerpt'] ), '"max_length":12' ), 'límite de palabras del extracto' );
	$out  = build( 'woolamp', fx( 'woolamp', 'custom' ), $shop );
	$main = main_of( $out );
	$tree = loop_data( (int) $main['settings']['template_id'] );
	$check( 'by_id' === $main['settings']['product_query_post_type'] && ! in_array( 'woocommerce-product-add-to-cart', widgets_of( $tree[0]['elements'] ), true ) && '' === $main['settings']['arrows'], 'woolamp: selección manual, sin carrito, sólo puntos' );
	$out  = build( 'woostand', fx( 'woostand', 'custom' ), $shop );
	$main = main_of( $out );
	$c    = codes( $shop );
	$check( 'date' === $main['settings']['product_query_orderby'] && in_array( 'ps.dyn.orderby', $c, true ) && in_array( 'ps.dyn.loss.glass', $c, true ) && in_array( 'ps.dyn.nav_none', $c, true ), 'woostand: orden por ID → fecha, vidrio y navegación' );

	// Respaldo de G: el widget Productos en carrusel.
	env( array( 'features' => array( 'container' => false ) ) );
	$out  = build( 'woocommerce', fx( 'woocommerce', 'custom' ), $shop );
	$main = main_of( $out );
	$check( 'woocommerce-products' === $main['widgetType'] && 'carousel' === $main['settings']['digitalisimo_wc_layout'] && 'yes' === $main['settings']['digitalisimo_wc_custom'], 'woocommerce sin Loop Item: Productos en carrusel' );
	$check( array( 'image', 'title', 'excerpt', 'category', 'price', 'cart' ) === $main['settings']['digitalisimo_wc_parts'] && 'h1' === $main['settings']['digitalisimo_wc_title_tag'] && 12 === $main['settings']['digitalisimo_wc_excerpt_length'], 'partes del producto (lista) y etiqueta' );
	$check( 8 === $main['settings']['digitalisimo_products_limit'] && 'sale' === $main['settings']['query_post_type'] && '1' === $main['settings']['columns'] && 8 === $main['settings']['rows'], 'consulta y límite del respaldo' );
	$check( 'yes' === $main['settings']['digitalisimo_wc_navigation'], 'flechas en el carrusel propio' );
	$c = codes( $shop );
	$check( in_array( 'ps.dyn.fallback_products', $c, true ) && in_array( 'ps.dyn.loss.product_form', $c, true ) && in_array( 'ps.dyn.loss.dots', $c, true ), 'respaldo, formulario de cantidad y puntos informados' );
	env();
	$out  = build( 'woocircle', fx( 'woocircle', 'custom' ), $shop );
	$main = main_of( $out );
	$check( 'woocommerce-products' === $main['widgetType'] && 6 === $main['settings']['digitalisimo_products_limit'] && '3' === $main['settings']['columns'] && 2 === $main['settings']['rows'], 'woocircle → Productos en carrusel, 6 en 3 columnas' );
	$check( array( 'terms' ) === $main['settings']['query_exclude'] && array( '52' ) === $main['settings']['query_exclude_term_ids'] && ! in_array( 'excerpt', $main['settings']['digitalisimo_wc_parts'], true ), 'woocircle: exclusión por términos, sin extracto' );
	$c = codes( $shop );
	$check( in_array( 'ps.dyn.loss.circular', $c, true ) && in_array( 'ps.dyn.query_authors', $c, true ) && in_array( 'ps.dyn.nav_none', $c, true ) && 'yes' === $main['settings']['digitalisimo_wc_navigation'], 'woocircle: disposición circular, autores y navegación añadida' );
	$out  = build( 'wooexpand', fx( 'wooexpand', 'defaults' ), $shop );
	$main = main_of( $out );
	$check( array( 'image', 'title', 'excerpt', 'category', 'rating', 'price', 'cart', 'badge' ) === $main['settings']['digitalisimo_wc_parts'] && 'yes' === $main['settings']['show_onsale_flash'] && '4' === $main['settings']['columns'] && 30 === $main['settings']['digitalisimo_wc_excerpt_length'], 'wooexpand: todas sus partes, 4 columnas' );
	$main = main_of( build( 'wooexpand', fx( 'wooexpand', 'custom' ), $shop ) );
	$check( array( 'image', 'title', 'excerpt', 'price', 'cart' ) === $main['settings']['digitalisimo_wc_parts'] && 'featured' === $main['settings']['query_post_type'] && '' === $main['settings']['show_onsale_flash'], 'wooexpand editado: destacados, sin valoración, insignia ni categoría' );
	env( array( 'woocommerce' => false ) );
	$check( null === build( 'woocommerce', array(), $shop ) && null === build( 'woocircle', array(), $shop ) && in_array( 'ps.dyn.no_woocommerce', codes( $shop ), true ), 'sin WooCommerce: null y a revisar' );
	$check( 'blocked' === Dynamic::state( 'woolamp' ) && 'blocked' === Dynamic::state( 'wooexpand' ), 'state() sin WooCommerce: bloqueado' );
	env();
	$check( 'target' === Dynamic::state( 'woostand' ) && 'target' === Dynamic::state( 'woocircle' ), 'state() con WooCommerce: destino' );
	foreach ( array( 'woocommerce', 'woolamp', 'woostand', 'woocircle', 'wooexpand' ) as $id ) {
		foreach ( array( 'defaults', 'custom' ) as $case ) {
			$out = build( $id, fx( $id, $case ), $shop );
			$check( is_array( $out ) && vendor_free( $out ), $id . '/' . $case . ': convertido y sin proveedor' );
		}
	}

	/* ============================================================== 6. woohotspot */

	reset_all();
	$shop = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Tienda' ) );
	$builder = class_exists( 'Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Builder' );
	env( array( 'features' => array( 'nested' => false ) ) );
	$out = build( 'woohotspot', fx( 'woohotspot', 'custom' ), $shop, 'e5f6a7b' );
	$check( '__fragment' === $out['elType'] && 3 === count( $out['elements'] ) && array( 'hotspot', 'hotspot', 'hotspot' ) === array_column( $out['elements'], 'widgetType' ), 'sin el slider anidado: fragmento con 3 hotspot' );
	$check( 'e5f6a7b' === $out['elements'][0]['id'] && 'mi-carrusel' !== ( $out['elements'][0]['settings']['_css_classes'] ?? '' ), 'el primero conserva el ID del original' );
	$first = $out['elements'][0]['settings'];
	$check( 'https://s1.test/wp-content/uploads/2026/10/sala.jpg' === $first['image']['url'] && 501 === $first['image']['id'] && 'full' === $first['image_size'], 'imagen de cada diapositiva' );
	$spot = $first['hotspot'][0];
	$check( 'Sofá' === $spot['hotspot_label'] && '' === $spot['hotspot_icon']['value'] && 40 === $spot['hotspot_offset_x']['size'] && 60 === $spot['hotspot_offset_y']['size'] && 35 === $spot['hotspot_offset_x_mobile']['size'] && '%' === $spot['hotspot_offset_x']['unit'], 'punto de texto con su posición responsiva' );
	$check( 'yes' === $spot['hotspot_tooltip_position'] && 'bottom' === $spot['hotspot_position'] && 'left' === $spot['hotspot_horizontal'] && 'top' === $spot['hotspot_vertical'], 'globo abajo (bottom-start → bottom)' );
	$tip = $spot['hotspot_tooltip_content'];
	$check( '<h4>Sofá</h4><p>Tres plazas</p>' === $tip, 'globo: título en texto plano (sin la etiqueta inyectada), texto sin script' );
	$check( 0 === strpos( $tip, '<h4>' ) && false === strpos( $tip, 'javascript' ) && false === strpos( $tip, '<a ' ) && false === strpos( $tip, '1,200' ), 'globo h4, sin enlace javascript: y sin precio (oculto)' );
	$spot2 = $first['hotspot'][1];
	$check( 'fas fa-plus' === $spot2['hotspot_icon']['value'] && false !== strpos( $spot2['hotspot_tooltip_content'], '<a href="/tienda/lampara/" target="_blank" rel="noopener">Ver</a>' ) && ! isset( $spot2['hotspot_tooltip_position'] ), 'punto con icono y enlace real' );
	$third = $out['elements'][2]['settings']['hotspot'][0];
	$check( '' === $third['hotspot_label'] && '' === $third['hotspot_icon']['value'], 'punto de imagen → círculo (sin imagen propia)' );
	$check( array() === $out['elements'][1]['settings']['hotspot'], 'diapositiva sin puntos' );
	$c = codes( $shop );
	$check( in_array( 'ps.dyn.hotspot_fragment', $c, true ) && in_array( 'ps.dyn.loss.tooltip', $c, true ) && ! in_array( 'ps.dyn.hotspot_no_image', $c, true ), 'fragmento informado; todas con imagen' );
	$check( 'fallback' === Dynamic::state( 'woohotspot' ), 'state(): respaldo sin anidados' );
	$out = build( 'woohotspot', fx( 'woohotspot', 'defaults' ), $shop );
	$check( 'Product 01' === strip_tags( explode( '</h3>', $out['elements'][0]['settings']['hotspot'][0]['hotspot_tooltip_content'] )[0] ) && in_array( 'ps.dyn.hotspot_no_image', codes( $shop ), true ), 'valores por defecto: textos de ejemplo y diapositivas sin imagen a revisar' );
	$check( '' === $out['elements'][0]['settings']['image']['url'] && vendor_free( $out ), 'sin imagen por defecto ni rastro del proveedor' );
	env( array( 'ready' => array( 'hotspot' => false ) ) );
	$check( null === build( 'woohotspot', array(), $shop ) && 'blocked' === Dynamic::state( 'woohotspot' ), 'sin el widget de puntos: null' );
	env();
}

/* ------------------------------------------------------------------ Builder de E (doble, sólo si no existe) */
namespace Digitalisimo\Elements\Legacy\Prime_Slider {
	if ( ! class_exists( __NAMESPACE__ . '\\Builder' ) ) {
		final class Builder {
			public static function nested_slider( array $settings, array $children ): array {
				$slides = array();
				foreach ( $children as $i => $child ) {
					$slides[] = array( '_id' => 's' . $i, 'slide_title' => 'Diapositiva ' . ( $i + 1 ) );
				}
				$nav = 'yes' === ( $settings['show_navigation_arrows'] ?? '' ) ? ( 'yes' === ( $settings['show_navigation_dots'] ?? '' ) ? 'both' : 'arrows' ) : 'dots';
				return array( 'id' => 'tmp0001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-nested-slider', 'settings' => array( 'slides' => $slides, 'navigation' => $nav, 'effect' => 'fade' === ( $settings['transition'] ?? '' ) ? 'fade' : 'slide' ), 'elements' => $children );
			}
			public static function container( array $settings, array $widgets ): array {
				return array( 'id' => substr( md5( json_encode( $widgets ) ), 0, 7 ), 'elType' => 'container', 'isInner' => true, 'settings' => $settings + array( 'content_width' => 'full' ), 'elements' => $widgets );
			}
		}
		$GLOBALS['f8f']['double_builder'] = true;
	}
}

namespace {
	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Dynamic;
	use Digitalisimo\Elements\Legacy\Prime_Slider\Loop_Item;

	$check = 'f8_check';

	// woohotspot con el slider anidado (Builder de E o su doble).
	$out = build( 'woohotspot', fx( 'woohotspot', 'custom' ), $shop, 'f6a7b8c' );
	$check( 'digitalisimo-nested-slider' === $out['widgetType'] && 'f6a7b8c' === $out['id'] && 3 === count( $out['elements'] ), 'woohotspot → slider anidado con el ID original y 3 diapositivas' );
	$hotspots = 0;
	walk( $out['elements'], static function ( $e ) use ( &$hotspots ) {
		$hotspots += 'hotspot' === ( $e['widgetType'] ?? '' ) ? 1 : 0;
	} );
	$check( 3 === $hotspots && 'container' === $out['elements'][0]['elType'], 'un hotspot por contenedor' );
	$check( 3 === count( $out['settings']['slides'] ?? array() ), 'una fila de slides por contenedor' );
	$ns = $out['settings'];
	$check( 'fade' === $ns['effect'] && '' === $ns['autoplay'] && 5000 === $ns['autoplay_delay'] && 'both' === $ns['navigation'] && '' === $ns['loop'] && 'Tienda' === $ns['carousel_label'], 'ajustes del slider anidado traducidos (fundido, sin reproducción, ambos, sin bucle, nombre del documento)' );
	$check( ! isset( $ns['transition'] ) && ! isset( $ns['autoplay_speed'] ) && ! isset( $ns['markers'] ) && ! isset( $ns['show_navigation_arrows'] ), 'sin claves de Prime Slider en el slider anidado' );
	$check( true === ( $out['elements'][0]['isInner'] ?? null ), 'contenedores hijos con isInner' );
	$check( in_array( 'ps.dyn.hotspot', codes( $shop ), true ) && 'target' === Dynamic::state( 'woohotspot' ), 'slider anidado informado; state() destino' );
	$hs = fx( 'woohotspot', 'custom' );
	$hs['autoplay'] = 'yes';
	$hs['autoplay_speed'] = 500;
	$hs['transition'] = 'cube';
	$ns = build( 'woohotspot', $hs, $shop )['settings'];
	$check( 'yes' === $ns['autoplay'] && 2000 === $ns['autoplay_delay'] && 'slide' === $ns['effect'] && in_array( 'ps.dyn.loss.effect', codes( $shop ), true ), 'retraso acotado a 2000 ms; el cubo pasa a deslizar (informado)' );
	$check( vendor_free( $out ), 'woohotspot: nada del proveedor' );

	/* ============================================================== 7. Familia L: eventos */

	reset_all();
	$page = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Agenda' ) );
	env( array( 'events' => false ) );
	$check( null === build( 'event-calendar', fx( 'event-calendar', 'custom' ), $page ), 'sin The Events Calendar: null (marcador)' );
	$c = codes( $page );
	$check( array( 'ps.dyn.events_unavailable' ) === $c && 'declared' === Dynamic::state( 'event-calendar' ), 'descarte declarado (no bloquea la retirada tardía)' );
	env();
	$out  = build( 'event-calendar', fx( 'event-calendar', 'defaults' ), $page );
	$main = main_of( $out );
	$check( 'loop-carousel' === $main['widgetType'] && 'post' === $main['settings']['_skin'] && 'tribe_events' === $main['settings']['post_query_post_type'] && 6 === $main['settings']['posts_per_page'], 'eventos → loop-carousel sobre tribe_events, 6' );
	$loop = (int) $main['settings']['template_id'];
	$check( 'post' === get_post_meta( $loop, '_elementor_source', true ) && array( 'source' => 'post' ) === get_post_meta( $loop, '_elementor_page_settings', true ), 'Loop Item con source = post (C1-29)' );
	$check( 'post_date' === $main['settings']['post_query_orderby'] && 'desc' === $main['settings']['post_query_order'] && 'yes' === $main['settings']['arrows'] && '' === $main['settings']['pagination'], 'orden y navegación (flechas)' );
	$tree = loop_data( $loop );
	$check( array( 'theme-post-title', 'theme-post-excerpt' ) === widgets_of( $tree[0]['elements'] ), 'sin enlace propio, el botón de entradas no se pinta' );
	$c = codes( $page );
	$check( in_array( 'ps.dyn.events', $c, true ) && in_array( 'ps.dyn.loss.event_dates', $c, true ) && in_array( 'ps.dyn.loss.effect', $c, true ), 'eventos, fechas y fundido informados' );
	$check( 'target' === Dynamic::state( 'event-calendar' ), 'state() con The Events Calendar: destino' );
	$out  = build( 'event-calendar', fx( 'event-calendar', 'custom' ), $page );
	$main = main_of( $out );
	$check( array( '61' ) === $main['settings']['post_query_include_term_ids'] && 'post_title' === $main['settings']['post_query_orderby'] && 'asc' === $main['settings']['post_query_order'] && 4 === $main['settings']['posts_per_page'], 'categoría existente por tt_id, por título ascendente, 4' );
	$check( 'yes' === $main['settings']['arrows'] && 'bullets' === $main['settings']['pagination'], 'navegación «both»' );
	$tree   = loop_data( (int) $main['settings']['template_id'] );
	$button = end( $tree[0]['elements'] );
	$check( 'button' === $button['widgetType'] && 'Buy Ticket Online' === $button['settings']['text'] && 'https://entradas.example.test/' === $button['settings']['link']['url'] && ! in_array( 'theme-post-excerpt', widgets_of( $tree[0]['elements'] ), true ), 'botón de entradas con su enlace fijo; sin extracto' );
	$out  = build( 'event-calendar', fx( 'event-calendar', 'styled' ), $page );
	$main = main_of( $out );
	$check( 'by_id' === $main['settings']['post_query_post_type'] && array() === $main['settings']['post_query_posts_ids'] && in_array( 'ps.dyn.events_terms', codes( $page ), true ), 'categorías inexistentes: selección vacía, a revisar' );
	env( array( 'features' => array( 'container' => false ) ) );
	$main = main_of( build( 'event-calendar', fx( 'event-calendar', 'custom' ), $page ) );
	$check( 'posts' === $main['widgetType'] && 'tribe_events' === $main['settings']['posts_post_type'] && 4 === $main['settings']['classic_posts_per_page'], 'eventos sin contenedores: rejilla de Entradas sobre tribe_events' );
	env();

	/**
	 * Problemas de estructura de un resultado, como los vería el editor (segunda vuelta de J, P0.C2): IDs no vacíos y
	 * únicos, `elType` conocido, `widgetType` en los widgets, `settings` objeto (array asociativo o vacío) y `elements`
	 * lista; los hijos del slider anidado, contenedores; `isInner` sólo en contenedores.
	 *
	 * @return string[]
	 */
	function dyn_structure( $out ) {
		$problems = array();
		$seen     = array();
		$walk     = static function ( array $elements, string $parent ) use ( &$walk, &$problems, &$seen ) {
			if ( array_keys( $elements ) !== range( 0, count( $elements ) - 1 ) && $elements ) {
				$problems[] = 'elements no es una lista bajo ' . $parent;
			}
			foreach ( $elements as $element ) {
				$id   = is_array( $element ) ? (string) ( $element['id'] ?? '' ) : '';
				$type = is_array( $element ) ? (string) ( $element['elType'] ?? '' ) : '';
				if ( '' === $id ) {
					$problems[] = 'elemento sin id bajo ' . $parent;
				} elseif ( isset( $seen[ $id ] ) ) {
					$problems[] = 'id repetido ' . $id;
				}
				$seen[ $id ] = true;
				if ( ! in_array( $type, array( 'widget', 'container', 'section', 'column' ), true ) ) {
					$problems[] = 'elType «' . $type . '» en ' . $id;
				}
				if ( 'widget' === $type && '' === (string) ( $element['widgetType'] ?? '' ) ) {
					$problems[] = 'widget sin widgetType ' . $id;
				}
				$settings = $element['settings'] ?? null;
				if ( ! is_array( $settings ) || ( $settings && array_keys( $settings ) === range( 0, count( $settings ) - 1 ) ) ) {
					$problems[] = 'settings no es un objeto en ' . $id;
				}
				if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
					$problems[] = 'elements falta en ' . $id;
				}
				if ( ! empty( $element['isInner'] ) && 'container' !== $type && 'section' !== $type ) {
					$problems[] = 'isInner en un ' . $type . ' ' . $id;
				}
				if ( 'digitalisimo-nested-slider' === ( $element['widgetType'] ?? '' ) ) {
					foreach ( (array) ( $element['elements'] ?? array() ) as $child ) {
						if ( 'container' !== ( $child['elType'] ?? '' ) ) {
							$problems[] = 'hijo del slider anidado que no es un contenedor en ' . $id;
						}
					}
				}
				$walk( (array) ( $element['elements'] ?? array() ), $id );
			}
		};
		if ( ! is_array( $out ) ) {
			return array( 'sin resultado' );
		}
		$walk( '__fragment' === ( $out['elType'] ?? '' ) ? (array) $out['elements'] : array( $out ), 'raíz' );
		return $problems;
	}

	/* ============================================================== 8. Los 19, todos los casos */

	reset_all();
	$page = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Todo' ) );
	$all  = Report::catalog();
	foreach ( Dynamic::IDS as $id ) {
		foreach ( array( 'defaults', 'custom', 'styled' ) as $case ) {
			$settings = fx( $id, $case );
			if ( 'styled' === $case && ! $settings ) {
				continue;
			}
			$out = build( $id, $settings, $page );
			if ( is_array( $out ) ) {
				$structure = dyn_structure( $out );
				$check( ! $structure, $id . ' (' . $case . '): estructura que el editor admite (ids únicos, settings objeto, elements lista): ' . implode( '; ', $structure ) );
			}
			// El CSS propio del usuario se copia tal cual y, si nombra clases del original, va a «Pendiente de revisar».
			$scan = $out;
			if ( is_array( $scan ) && isset( $settings['custom_css'] ) ) {
				if ( '__fragment' === ( $scan['elType'] ?? '' ) ) {
					unset( $scan['elements'][0]['settings']['custom_css'] );
				} else {
					unset( $scan['settings']['custom_css'] );
				}
			}
			$ok = is_array( $out ) && vendor_free( $scan );
			$ids = array();
			if ( is_array( $out ) ) {
				walk( '__fragment' === ( $out['elType'] ?? '' ) ? $out['elements'] : array( $out ), static function ( $e ) use ( &$ids, &$ok ) {
					$ids[] = $e['id'] ?? '';
					$s     = (array) ( $e['settings'] ?? array() );
					if ( isset( $s['_digitalisimo_legacy'] ) || in_array( $s['_skin'] ?? 'post', array( 'coral', 'zinest', 'folio' ), true ) || isset( $s['_title'] ) && 'container' !== ( $e['elType'] ?? '' ) ) {
						$ok = false;
					}
				} );
			}
			$check( $ok && count( $ids ) === count( array_unique( $ids ) ), $id . '/' . $case . ': convertido, sin proveedor, sin marcas heredadas y con IDs únicos' );
			foreach ( codes( $page ) as $code ) {
				$check( isset( $all[ $code ] ), $id . '/' . $case . ': el código ' . $code . ' está en el catálogo' );
			}
		}
	}
	foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $tpl ) {
		$check( vendor_free( loop_data( $tpl->ID ) ) && vendor_free( array( $tpl->post_title ) ), 'Loop Item ' . $tpl->ID . ' sin proveedor' );
	}
	$check( count( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) ) >= 10, 'Loop Items reutilizados entre casos con las mismas partes' );

	/* ============================================================== 9. Por Converter (E) y Native (A), si existen */

	f8_load_migration( array( 'native' ) );
	if ( class_exists( 'Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Converter' ) && class_exists( 'Digitalisimo\\Elements\\Legacy\\Native', false ) ) {
		reset_all();
		$page = f8_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Portada' ) );
		list( $conv ) = Digitalisimo\Elements\Legacy\Native::element( el( 'blog', fx( 'blog', 'defaults' ) ), $page, 'container' );
		$main = main_of( $conv );
		$check( 'loop-carousel' === ( $main['widgetType'] ?? '' ) && 'a1b2c3d' === $main['id'] && 3 === $main['settings']['posts_per_page'], 'Native → Converter → Dynamic: blog en el Loop Carousel' );
		$check( vendor_free( $conv ), 'por la tubería completa: nada del proveedor' );
		$c = codes( $page );
		$check( in_array( 'ps.dyn.loop', $c, true ) && in_array( 'ps.vendor_removed', $c, true ), 'informe de F y de E juntos (perfiles retirados por el Sanitizer)' );
		$ext = fx( 'blog', 'defaults' ) + array( 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions' => array( array( '_id' => 'r1', 'ep_condition_key' => 'login_status', 'ep_condition_login_status_value' => 'logged' ) ) );
		list( $conv ) = Digitalisimo\Elements\Legacy\Native::element( el( 'blog', $ext ), $page, 'container' );
		$main = main_of( $conv );
		$check( ! isset( $main['settings']['ep_display_conditions_enable'] ) && isset( $main['settings']['digitalisimo_visibility_rules'] ), 'extensiones de Element Pack del widget convertidas sobre el principal' );
		codes( $page );
		list( $conv ) = Digitalisimo\Elements\Legacy\Native::element( el( 'woohotspot', fx( 'woohotspot', 'custom' ) ), $page, 'container' );
		$check( 'digitalisimo-nested-slider' === ( main_of( $conv )['widgetType'] ?? '' ) && vendor_free( $conv ), 'woohotspot por la tubería completa' );
		$psx = array( 'reveal_effects_enable' => 'yes', 'reveal_effects_color' => '#FF0000', 'animation_status' => 'yes', 'anim_duration' => array( 'size' => 2 ), 'ps_widget_abg' => 'yes', 'ps_widget_abg_parent' => '.hero', 'kenburns_animation' => 'yes' );
		foreach ( array( 'blog', 'storker', 'woocommerce', 'woohotspot' ) as $id ) {
			codes( $page );
			list( $conv ) = Digitalisimo\Elements\Legacy\Native::element( el( $id, fx( $id, 'defaults' ) + $psx, 'p' . substr( md5( $id ), 0, 6 ) ), $page, 'container' );
			$c      = codes( $page );
			$counts = array_count_values( $c );
			$once   = 1 === ( $counts['ps.dropped.reveal'] ?? 0 ) && 1 === ( $counts['ps.dropped.advanced_animation'] ?? 0 ) && 1 === ( $counts['ps.dropped.adaptive_bg'] ?? 0 ) && 1 === ( $counts['ps.dropped.kenburns'] ?? 0 );
			$dyn    = preg_grep( '/^ps\.dyn\..*(reveal|animation|abg|adaptive|kenburns)|^ps\.dyn\.loss\.styles$/', $c );
			$check( is_array( $conv ) && $once && ! $dyn, $id . ': por la tubería, cada ps.dropped.* de E una sola vez y ningún ps.dyn.* que lo repita' );
		}
		env( array( 'events' => false ) );
		$orig = el( 'event-calendar', fx( 'event-calendar', 'custom' ) );
		list( $conv ) = Digitalisimo\Elements\Legacy\Native::element( $orig, $page, 'container' );
		$check( 'prime-slider-event-calendar' === ( $conv['widgetType'] ?? '' ) || 'digitalisimo-unconverted' === ( $conv['widgetType'] ?? '' ), 'event-calendar sin The Events Calendar: sin convertir o en su marcador' );
		$check( in_array( 'ps.dyn.events_unavailable', codes( $page ), true ), 'descarte declarado informado por la tubería' );
		env();
	}

	// Un mapa que no es de F o un descarte nunca se construye aquí.
	$check( null === Dynamic::build( 'remote-arrows', array(), el( 'remote-arrows', array() ), $page ) && null === Dynamic::build( '', array(), array(), $page ), 'IDs ajenos: null' );

	f8_done( 'migration-prime-slider-dynamic', 100 );
}
