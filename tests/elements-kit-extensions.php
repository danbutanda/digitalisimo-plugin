<?php
/**
 * F4-Woo · frente H, V.11.4: el punto de extensión genérico de K, con las clases reales de K y WordPress en memoria
 * (`tests/elements-kit/wp-stubs.php`).
 *
 *  1. Contadores no exportados: `Export_Context::get_not_exported()` pasa por `digitalisimo_elements_kit_not_exported`
 *     acotado. Sin funciones enganchadas, el mapa es el de siempre; el filtro sólo baja (un contador mayor, negativo,
 *     que no es un entero, un motivo nuevo o un filtro que no devuelve un array no tienen efecto).
 *  2. Referencias `term_meta` con `taxonomies`: validación y normalización (con claves, posicional y mixta), sin la
 *     clave el índice tiene la forma de siempre, `matching()` sólo cuenta una acotada dentro de `within_taxonomy()`
 *     con una de sus taxonomías (nombre exacto o prefijo con `*`), y el valor anterior vuelve también tras una
 *     excepción.
 *  3. Parte `term-meta`: sin la clave, la traducción y los adjuntos que viajan son los de siempre (y no se consulta la
 *     taxonomía de ningún término); con `[ 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) ]`,
 *     `image` se traduce y se incrusta sólo en los términos `pa_*`; fuera, la meta queda como hoy una clave sin
 *     referencia; una imagen sin destino no se escribe y se informa.
 *  4. Por tokens: K aplica el filtro una sola vez, en `get_not_exported()`, que es lo que lee `Export_Finish`; y las
 *     dos piezas no tocan otro archivo de K.
 */
namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['ext'] = array( 'get_term' => 0 );

	// Taxonomías del sitio en memoria: las de los stubs de K más un atributo de WooCommerce.
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'category', 'post_tag', 'product_cat', 'product_tag', 'pa_color', 'pa_talla' ), true );
	}
	function get_object_taxonomies( $type ) {
		return 'product' === $type ? array( 'product_cat', 'pa_color', 'pa_talla' ) : ( 'post' === $type ? array( 'category', 'post_tag' ) : array() );
	}
	function get_term( $id ) {
		++$GLOBALS['ext']['get_term'];
		return $GLOBALS['wp']['terms'][ (int) $id ] ?? null;
	}

	require_once __DIR__ . '/elements-kit/wp-stubs.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			fwrite( STDERR, 'FALLO: ' . $message . PHP_EOL );
			exit( 1 );
		}
	};
	$wrongs = function ( callable $fn ) {
		$before = count( $GLOBALS['wp']['wrong'] );
		$fn();
		return array_slice( $GLOBALS['wp']['wrong'], $before );
	};
	$has = function ( array $list, string $needle ) {
		foreach ( $list as $item ) {
			if ( false !== strpos( $item, $needle ) ) {
				return true;
			}
		}
		return false;
	};
}

namespace Ext_Test {
	use Digitalisimo\Elements\Import\Kits\Export_Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Parts\Term_Meta;

	$check  = $GLOBALS['check'];
	$wrongs = $GLOBALS['wrongs'];
	$has    = $GLOBALS['has'];

	$tmp = sys_get_temp_dir() . '/digi-kit-ext-' . bin2hex( random_bytes( 6 ) );
	$site = function () use ( $tmp ) {
		kit_stub_reset( $tmp );
		References::reset();
		$GLOBALS['ext']['get_term'] = 0;
	};
	$codes = function ( Part_Context $context, string $code ) {
		return array_values(
			array_filter(
				$context->get_report()['entries'],
				function ( $entry ) use ( $code ) {
					return $code === $entry['code'];
				}
			)
		);
	};
	$filter = function ( string $hook, callable $callback ) {
		$GLOBALS['wp']['hooks'][ $hook ] = array( 10 => array( $callback ) );
	};

	// ── 1. Contadores no exportados ───────────────────────────────────────────────────────────────────────────────
	$site();
	$check( 'digitalisimo_elements_kit_not_exported' === Export_Context::FILTER_NOT_EXPORTED, '1: el filtro se llama digitalisimo_elements_kit_not_exported.' );
	$fill = function () {
		$export = new Export_Context();
		$export->not_exported( 'drafts', 3 );
		$export->not_exported( 'variations', 5 );
		$export->not_exported( 'webhooks' );
		return $export;
	};
	$export = $fill();
	$plain  = array( 'drafts' => 3, 'variations' => 5, 'webhooks' => 1 );
	$check( $plain === $export->get_not_exported(), '1: sin funciones enganchadas, el mapa es el de siempre, en el mismo orden.' );
	$check( array() === ( new Export_Context() )->get_not_exported(), '1: sin nada no exportado, el mapa sigue vacío.' );

	$seen = null;
	$filter(
		Export_Context::FILTER_NOT_EXPORTED,
		function ( $map, $context ) use ( &$seen ) {
			$seen = array( $map, $context );
			$map['variations'] = 2;
			return $map;
		}
	);
	$export = $fill();
	$check( array( 'drafts' => 3, 'variations' => 2, 'webhooks' => 1 ) === $export->get_not_exported(), '1: una parte puede bajar un contador (variaciones cuyo padre se exportó).' );
	$check( $plain === $seen[0] && $seen[1] === $export, '1: el filtro recibe el mapa de K y el contexto de exportación.' );

	$seen = null;
	$filter(
		Export_Context::FILTER_NOT_EXPORTED,
		function ( $map ) use ( &$seen ) {
			$seen = true;
			return array( 'nuevo' => 9 );
		}
	);
	$check( array() === ( new Export_Context() )->get_not_exported() && null === $seen, '1: con el mapa vacío el filtro ni se aplica: no puede inventar un motivo.' );

	$attempts = array(
		'subir'       => array( 'variations' => 9 ),
		'negativo'    => array( 'variations' => -1 ),
		'texto'       => array( 'variations' => '2' ),
		'decimal'     => array( 'variations' => 2.0 ),
		'null'        => array( 'variations' => null ),
		'motivo'      => array( 'variations' => 5, 'inventado' => 4 ),
		'quitar'      => array( 'drafts' => 3 ),
		'no es array' => 'nada',
		'objeto'      => (object) array( 'variations' => 1 ),
	);
	foreach ( $attempts as $label => $value ) {
		$filter(
			Export_Context::FILTER_NOT_EXPORTED,
			function () use ( $value ) {
				return $value;
			}
		);
		$check( $plain === $fill()->get_not_exported(), "1: un filtro que intenta «{$label}» no tiene efecto." );
	}
	$filter(
		Export_Context::FILTER_NOT_EXPORTED,
		function ( $map ) {
			$map['variations'] = 0;
			$map['drafts']     = 7;
			$map['webhooks']   = 1;
			return $map;
		}
	);
	$check( array( 'drafts' => 3, 'variations' => 0, 'webhooks' => 1 ) === $fill()->get_not_exported(), '1: bajar a 0 vale; subir otro motivo en el mismo filtro no.' );
	$check( array( 'a' => 1, 'b' => 4 ) === Export_Context::lower_only( array( 'a' => 3, 'b' => 4 ), array( 'b' => 5, 'a' => 1, 'c' => 0 ) ), '1: lower_only() conserva el orden de K y sólo baja.' );
	unset( $GLOBALS['wp']['hooks'][ Export_Context::FILTER_NOT_EXPORTED ] );
	$check( $plain === $fill()->get_not_exported(), '1: al quitar el filtro vuelve el mapa de siempre.' );

	// ── 2. Referencias `term_meta` acotadas por taxonomía ─────────────────────────────────────────────────────────
	$site();
	$shape = array( 'scope', 'type', 'path', 'kind', 'gate', 'never' );
	$bad   = array();
	$warned = $wrongs(
		function () use ( $shape, &$bad ) {
			foreach ( References::builtin() as $ref ) {
				$norm = References::normalize( $ref );
				if ( null === $norm || array_keys( $norm ) !== $shape ) {
					$bad[] = json_encode( $ref );
				}
			}
		}
	);
	$check( array() === $bad && array() === $warned, '2: sin la clave, toda referencia integrada tiene la forma de siempre y ningún aviso: ' . implode( ' | ', $bad ) );

	$named = References::normalize( array( 'scope' => 'term_meta', 'type' => 'image', 'path' => '', 'kind' => 'attachment', 'taxonomies' => array( 'pa_*', 'pa_color', 'pa_*' ) ) );
	$check( array( 'scope' => 'term_meta', 'type' => 'image', 'path' => '', 'kind' => 'attachment', 'gate' => false, 'never' => null, 'taxonomies' => array( 'pa_*', 'pa_color' ) ) === $named, '2: con claves, `taxonomies` se normaliza sin repetir y ordenada: ' . json_encode( $named ) );
	$mixed = References::normalize( array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) ) );
	$check( null !== $mixed && array( 'pa_*' ) === $mixed['taxonomies'] && 'attachment' === $mixed['kind'] && false === $mixed['gate'], '2: la forma de V.6.6 (posicional más `taxonomies` por nombre) es válida.' );
	$single = References::normalize( array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => 'product_cat' ) );
	$check( array( 'product_cat' ) === $single['taxonomies'], '2: una cadena vale como lista de una.' );
	$none = References::normalize( array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => null ) );
	$check( null !== $none && ! array_key_exists( 'taxonomies', $none ) && array_keys( $none ) === $shape, '2: `taxonomies` nulo es como no declararla.' );
	$invalid = array(
		'fuera de term_meta' => array( 'element', 'digitalisimo-x', 'image', 'attachment', 'taxonomies' => array( 'pa_*' ) ),
		'post_meta'          => array( 'post_meta', '_x', '', 'post', 'taxonomies' => array( 'pa_*' ) ),
		'vacía'              => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array() ),
		'todas'              => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( '*' ) ),
		'mayúsculas'         => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'PA_*' ) ),
		'asterisco medio'    => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*x' ) ),
		'no cadena'          => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*', 3 ) ),
		'larga'              => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( str_repeat( 'a', 33 ) ) ),
		'número'             => array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => 5 ),
	);
	foreach ( $invalid as $label => $ref ) {
		$result = null;
		$warned = $wrongs(
			function () use ( $ref, &$result ) {
				$result = References::normalize( $ref );
			}
		);
		$check( null === $result && 1 === count( $warned ) && $has( $warned, 'taxonom' ), "2: `taxonomies` {$label}: la referencia se rechaza con un aviso." );
	}
	$check( References::normalize( array( 'term_meta', 'x', '', 'attachment', 'taxonomies' => array( str_repeat( 'a', 32 ), str_repeat( 'b', 31 ) . '*' ) ) ) !== null, '2: hasta 32 caracteres (31 y el asterisco en un prefijo).' );

	// matching() con el filtro de referencias: una acotada a `pa_*` y otra sin acotar con otra clave.
	$site();
	$filter(
		'digitalisimo_elements_kit_references',
		function ( $refs ) {
			$refs[] = array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*', 'product_tag' ) );
			$refs[] = array( 'term_meta', 'icono', '', 'attachment' );
			return $refs;
		}
	);
	$paths = function ( array $refs ) {
		return array_map(
			function ( $ref ) {
				return $ref['type'] . '|' . $ref['kind'];
			},
			$refs
		);
	};
	$check( array() === References::matching( 'term_meta', 'image' ), '2: fuera de within_taxonomy(), una referencia acotada nunca se aplica.' );
	$check( array( 'icono|attachment' ) === $paths( References::matching( 'term_meta', 'icono' ) ) && array( 'thumbnail_id|attachment' ) === $paths( References::matching( 'term_meta', 'thumbnail_id' ) ), '2: las no acotadas, como siempre.' );
	$in = function ( string $taxonomy, string $key ) use ( $paths ) {
		return References::within_taxonomy(
			$taxonomy,
			function () use ( $key, $paths ) {
				return $paths( References::matching( 'term_meta', $key ) );
			}
		);
	};
	$check( array( 'image|attachment' ) === $in( 'pa_color', 'image' ) && array( 'image|attachment' ) === $in( 'pa_talla-zapato', 'image' ), '2: dentro de un atributo `pa_*`, cuenta (prefijo).' );
	$check( array( 'image|attachment' ) === $in( 'product_tag', 'image' ), '2: y en una taxonomía nombrada exacta.' );
	$check( array() === $in( 'product_cat', 'image' ) && array() === $in( 'product_tags', 'image' ) && array() === $in( 'pa', 'image' ) && array() === $in( '', 'image' ), '2: en otra taxonomía, en una que sólo empieza igual que un nombre exacto, sin el prefijo entero o sin taxonomía, no.' );
	$check( array( 'thumbnail_id|attachment' ) === $in( 'product_cat', 'thumbnail_id' ), '2: dentro de within_taxonomy(), las no acotadas siguen.' );
	$check( References::term_meta_restricted( 'image' ) && ! References::term_meta_restricted( 'thumbnail_id' ) && ! References::term_meta_restricted( 'nada' ), '2: term_meta_restricted() sólo es cierto con una referencia acotada.' );
	$nested = References::within_taxonomy(
		'pa_color',
		function () use ( $in, $paths ) {
			$inner = $in( 'product_cat', 'image' );
			return array( $inner, $paths( References::matching( 'term_meta', 'image' ) ) );
		}
	);
	$check( array( array(), array( 'image|attachment' ) ) === $nested, '2: anidado, al salir vuelve la taxonomía de fuera.' );
	try {
		References::within_taxonomy(
			'pa_color',
			function () {
				throw new \RuntimeException( 'x' );
			}
		);
	} catch ( \RuntimeException $error ) {
		unset( $error );
	}
	$check( array() === References::matching( 'term_meta', 'image' ), '2: tras una excepción, fuera otra vez (finally).' );
	$check( References::applies_to_taxonomy( array( 'kind' => 'post' ), '' ) && ! References::applies_to_taxonomy( array( 'taxonomies' => array( 'pa_*' ) ), '' ), '2: applies_to_taxonomy(): siempre sin `taxonomies`; nunca con una taxonomía desconocida.' );

	// Una acotada y una sin acotar con la misma clave: la del tipo que se registró primero gana dentro de su
	// taxonomía y la otra sigue fuera.
	$site();
	$filter(
		'digitalisimo_elements_kit_references',
		function ( $refs ) {
			$refs[] = array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) );
			$refs[] = array( 'term_meta', 'image', '', 'url' );
			return $refs;
		}
	);
	$check( array( 'image|attachment' ) === $in( 'pa_color', 'image' ) && array( 'image|url' ) === $in( 'product_cat', 'image' ) && array( 'image|url' ) === $paths( References::matching( 'term_meta', 'image' ) ), '2: con la misma clave, la acotada sólo gana en sus taxonomías.' );

	// ── 3. Parte `term-meta` ─────────────────────────────────────────────────────────────────────────────────────
	$seed_origin = function () {
		kit_stub_post( array( 'ID' => 10, 'post_type' => 'product' ) );
		kit_stub_post( array( 'ID' => 207, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		kit_stub_post( array( 'ID' => 208, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		kit_stub_term( array( 'term_id' => 40, 'taxonomy' => 'pa_color', 'slug' => 'azul' ) );
		add_term_meta( 40, 'image', '207' );
		add_term_meta( 40, 'color', '#0000ff' );
		kit_stub_term( array( 'term_id' => 41, 'taxonomy' => 'product_cat', 'slug' => 'camisetas' ) );
		add_term_meta( 41, 'thumbnail_id', '207' );
		add_term_meta( 41, 'image', '208' );
		kit_stub_term( array( 'term_id' => 42, 'taxonomy' => 'pa_talla', 'slug' => 'm' ) );
		add_term_meta( 42, 'image', '208' );
	};
	$export_ctx = function () {
		$export = new Export_Context( array( 'include' => array( 'content' ) ) );
		$export->add_exported( 'post', array( 10 ) );
		return $export;
	};
	$with_ref = function () use ( $filter ) {
		$filter(
			'digitalisimo_elements_kit_references',
			function ( $refs ) {
				$refs[] = array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) );
				return $refs;
			}
		);
		References::reset();
	};

	// Exportar: las metas viajan igual con y sin la referencia; los adjuntos que viajan, sólo cambian con ella.
	$site();
	$seed_origin();
	$plain_export = ( new Term_Meta() )->export( $export_ctx() );
	$plain_ids    = Term_Meta::attachment_ids( $export_ctx() );
	sort( $plain_ids );
	$check( array( 207 ) === $plain_ids, '3: sin la clave, viaja sólo el adjunto de `thumbnail_id`, como siempre: ' . json_encode( $plain_ids ) );
	$check( array( '40', '41', '42' ) === array_map( 'strval', array_keys( $plain_export ) ) && array( 'color' => array( '#0000ff' ), 'image' => array( '207' ) ) === $plain_export[40]['meta'], '3: las metas de los términos viajan con su taxonomía (también `image`).' );
	$with_ref();
	$ids = Term_Meta::attachment_ids( $export_ctx() );
	sort( $ids );
	$check( array( 207, 208 ) === $ids, '3: con la referencia acotada, viajan también las imágenes de los términos `pa_*`: ' . json_encode( $ids ) );
	$only_cat = Term_Meta::attachment_ids( ( function () {
		$export = new Export_Context( array( 'include' => array( 'content' ) ) );
		return $export;
	} )() );
	$check( array() === $only_cat, '3: sin contenido exportado no viaja nada.' );
	$check( $plain_export === ( new Term_Meta() )->export( $export_ctx() ), '3: el archivo de la parte es idéntico con y sin la referencia.' );
	// El 208 viaja por pa_talla (42), no por product_cat (41): se comprueba quitando el término del atributo.
	unset( $GLOBALS['wp']['terms'][42] );
	$check( array( 207 ) === Term_Meta::attachment_ids( $export_ctx() ), '3: la imagen de un término de otra taxonomía (product_cat) no viaja por la referencia acotada.' );

	// Importar en un sitio B: términos creados por la sesión en pa_color, product_cat y pa_talla.
	$destination = function () use ( $site ) {
		$site();
		foreach ( array( 540 => 'pa_color', 541 => 'product_cat', 542 => 'pa_talla' ) as $id => $taxonomy ) {
			kit_stub_term( array( 'term_id' => $id, 'taxonomy' => $taxonomy ) );
			add_term_meta( $id, '_elementor_import_session_id', 'sesExt' );
		}
		kit_stub_post( array( 'ID' => 907, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		return new Import_Context(
			array(
				'session' => 'sesExt',
				'mode'    => 'add',
				'maps'    => array(
					'term'       => array( 40 => 540, 41 => 541, 42 => 542 ),
					'attachment' => array( 207 => 907 ),
				),
			)
		);
	};
	$data = array(
		'40' => array( 'taxonomy' => 'pa_color', 'slug' => 'azul', 'meta' => array( 'color' => array( '#0000ff' ), 'image' => array( '207' ) ) ),
		'41' => array( 'taxonomy' => 'product_cat', 'slug' => 'camisetas', 'meta' => array( 'image' => array( '208' ), 'thumbnail_id' => array( '207' ) ) ),
		'42' => array( 'taxonomy' => 'pa_talla', 'slug' => 'm', 'meta' => array( 'image' => array( '208' ) ) ),
	);
	$run = function ( Import_Context $context ) use ( $data ) {
		$context->begin_part( 'term-meta' );
		$summary = ( new Term_Meta() )->import( $data, 1, $context );
		$context->end_part();
		return $summary;
	};
	$meta_of = function ( int $id ) {
		return array_diff_key( $GLOBALS['wp']['termmeta'][ $id ] ?? array(), array( '_elementor_import_session_id' => 1 ) );
	};

	$context = $destination();
	$summary = $run( $context );
	$before  = array( $meta_of( 540 ), $meta_of( 541 ), $meta_of( 542 ), $summary );
	$check( array( 'color' => array( '#0000ff' ), 'image' => array( '207' ) ) === $before[0], '3: sin la clave, `image` se copia tal cual (hoy no tiene referencia).' );
	$check( array( 'image' => array( '208' ), 'thumbnail_id' => array( '907' ) ) === $before[1] && array( 'image' => array( '208' ) ) === $before[2], '3: sin la clave, `thumbnail_id` se traduce como siempre.' );
	$check( 0 === $GLOBALS['ext']['get_term'], '3: sin referencias acotadas, la parte no consulta la taxonomía de ningún término (mismo coste que hoy).' );
	$check( array() === $codes( $context, 'unresolved' ), '3: sin la clave, nada queda sin destino.' );

	$context = $destination();
	$filter(
		'digitalisimo_elements_kit_references',
		function ( $refs ) {
			$refs[] = array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) );
			return $refs;
		}
	);
	$summary = $run( $context );
	$check( array( 'color' => array( '#0000ff' ), 'image' => array( '907' ) ) === $meta_of( 540 ), '3: con la referencia acotada, `image` de un término `pa_*` → adjunto nuevo.' );
	$check( array( 'image' => array( '208' ), 'thumbnail_id' => array( '907' ) ) === $meta_of( 541 ), '3: en product_cat, `image` queda como hoy (la referencia no existe ahí) y `thumbnail_id` igual.' );
	$check( array() === $meta_of( 542 ), '3: una imagen de muestra sin destino no se escribe (nunca un ID del origen)…' );
	$unresolved = $codes( $context, 'unresolved' );
	$check( 1 === count( $unresolved ) && 'attachment' === $unresolved[0]['data']['kind'] && array( '208' ) === $unresolved[0]['data']['values'] && 'unresolved' === \Digitalisimo\Elements\Import\Kits\Report::section_for( 'unresolved' ), '3: …y se informa en «Referencias sin destino».' );
	$check( $GLOBALS['ext']['get_term'] <= 3, '3: la taxonomía se consulta como mucho una vez por término (' . $GLOBALS['ext']['get_term'] . ').' );
	$check( 3 === $summary['terms'] && 4 === $summary['metas'], '3: el resumen cuenta lo escrito: ' . json_encode( $summary ) );

	// Un término sin taxonomía conocida (no existe) no recibe la traducción acotada.
	$context = $destination();
	$filter(
		'digitalisimo_elements_kit_references',
		function ( $refs ) {
			$refs[] = array( 'term_meta', 'image', '', 'attachment', 'taxonomies' => array( 'pa_*' ) );
			return $refs;
		}
	);
	References::reset();
	$GLOBALS['wp']['terms'][540]->taxonomy = '';
	$run( $context );
	$check( array( 'color' => array( '#0000ff' ), 'image' => array( '207' ) ) === $meta_of( 540 ), '3: un término cuya taxonomía no se conoce, como sin la referencia.' );

	// ── 4. Por tokens ────────────────────────────────────────────────────────────────────────────────────────────
	$K       = realpath( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import' );
	$sources = '';
	foreach ( glob( $K . '/{,*/,*/*/}*.php', GLOB_BRACE ) as $file ) {
		$sources .= file_get_contents( $file );
	}
	$check( 1 === substr_count( $sources, 'apply_filters( self::FILTER_NOT_EXPORTED' ) && 1 === substr_count( $sources, "'digitalisimo_elements_kit_not_exported'" ), '4: K aplica el filtro de contadores exactamente una vez y lo nombra en una sola constante.' );
	$context_src = file_get_contents( $K . '/kits/class-export-context.php' );
	$at          = strpos( $context_src, 'public function get_not_exported()' );
	$check( false !== $at && false !== strpos( substr( $context_src, $at, 600 ), 'self::lower_only(' ), '4: el filtro va dentro de get_not_exported(), acotado con lower_only().' );
	$finish = file_get_contents( $K . '/kits/runners/class-export-finish.php' );
	$check( 1 === substr_count( $finish, "'not_exported' => \$context->get_not_exported()" ), '4: Export_Finish cierra el manifiesto con get_not_exported().' );
	$check( false === strpos( file_get_contents( $K . '/kits/export/class-wp-content.php' ), 'variations_not_exported' ), '4: sin la línea específica de D27 en export/class-wp-content.php.' );
	$check( 1 === substr_count( $sources, 'References::within_taxonomy(' ) && false !== strpos( file_get_contents( $K . '/kits/parts/class-term-meta.php' ), 'References::within_taxonomy(' ), '4: sólo la parte term-meta traduce dentro de within_taxonomy().' );
	$tokens = token_get_all( $sources );
	foreach ( $tokens as $token ) {
		if ( is_array( $token ) && in_array( token_name( $token[0] ), array( 'T_MATCH', 'T_NULLSAFE_OBJECT_OPERATOR' ), true ) ) {
			$check( false, '4: K sigue siendo PHP 7.4 (sin ' . token_name( $token[0] ) . ').' );
		}
	}
	$check( false === strpos( $sources, 'str_contains(' ) && false === strpos( $sources, 'str_starts_with(' ), '4: sin funciones de PHP 8 en K.' );

	unset( $GLOBALS['wp']['hooks']['digitalisimo_elements_kit_references'] );
	echo "DIGITALÍSIMO Elements: punto de extensión genérico de K validado (contadores que sólo bajan y referencias term_meta acotadas por taxonomía, sin efecto cuando no se usan).\n";
}
