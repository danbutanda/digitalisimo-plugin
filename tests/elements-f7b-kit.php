<?php
/**
 * F7b · frente K: kits (D22 y V.10 de la especificación), con las clases reales de K (`References`, `Remapper`,
 * `Parts`, contextos y `Report`), del almacén de consultas del frente C (`Queries\Store`, con la lista negra real de
 * `Filters\Query`), de la base del frente A (`F7b\Module`, `Usages`, `Scan`) y WordPress en memoria
 * (`tests/elements-kit/wp-stubs.php`, más los tipos y metas registrados con su `sanitize_callback`, como hace
 * `update_post_meta()`).
 *
 *  1. Registro: `Kit\Module::init()` engancha partes, referencias y textos; `saved-queries` queda detrás de las de K con
 *     versión 1, sin metas del kit e `implements Part` (no `Early_Part`: importa en la petición final, antes del
 *     remapeo); sin K (su interfaz) no se registra.
 *  2. Referencias: cada una con su tipo; `gate` sólo en la consulta guardada; lo que ya traduce el núcleo no se declara.
 *  3. Remapeo de documentos con las referencias reales: consulta (cadena; sin destino, vacía y el documento a
 *     «revisar»), acciones (destino del documento, popup), sobrescrituras (imagen, enlace, HTML) y filtros; las agujas
 *     de `Flags` y `_digitalisimo_uses` sobre el resultado.
 *  4. Importar: crear (mapa en cadenas, `Store::register()` antes de escribir, metas saneadas), términos con
 *     `term_taxonomy_id` distinto de `term_id`, autores que nunca resuelven, entradas, listas que la consulta no usa,
 *     tipo, relación y contexto que no valen → borrador; reutilizar por UUID sin escribir (R5: 0 filas) y avisar si
 *     difiere; formato no válido; fallo al crear; sin consultas, nada.
 *  5. Revertir: borra lo que sigue igual y sin usos; conserva lo editado y lo que usa contenido del sitio.
 *  6. Exportar: publicadas y borradores (nunca la papelera), UUID (derivado y estable si falta) y la pareja de cada
 *     término.
 *  7. `_digitalisimo_uses`: no viaja (`Usages::skip_export()` con `Meta_Blacklist::skip()`), se recalcula al guardar
 *     (`Usages::refs()` del documento remapeado) y el recorrido de A se programa al terminar una importación.
 *  8. Informe: cada código de `kit/` tiene sección en `Report::SECTION_PREFIXES` y texto; K no nombra F7b.
 */

namespace ElementorPro\Core {
	/** Tipos públicos del grupo de consulta de PRO (`Queries\Store::post_types()`). */
	class Utils {
		public static function get_public_post_types( $args = array() ) {
			return $GLOBALS['f7bk']['types'];
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	if ( ! defined( 'ARRAY_A' ) ) {
		define( 'ARRAY_A', 'ARRAY_A' );
	}

	set_error_handler(
		function ( $severity, $message, $file, $line ) {
			throw new \ErrorException( $message, 0, $severity, $file, $line );
		}
	);

	$GLOBALS['f7bk'] = array(
		'types'       => array(
			'post' => 'Entradas',
			'page' => 'Páginas',
		),
		'meta'        => array(),
		'log'         => array(),
		'fail_insert' => false,
		'checks'      => 0,
	);

	function f7bk_check( $ok, string $message ): void {
		if ( ! $ok ) {
			fwrite( STDERR, 'FALLO: ' . $message . PHP_EOL );
			exit( 1 );
		}
		++$GLOBALS['f7bk']['checks'];
	}

	// WordPress que no traen los stubs de K y que usan el almacén de consultas y la parte.
	function register_post_type( $type, $args = array() ) {
		$GLOBALS['f7bk']['log'][]       = 'register_post_type:' . $type;
		$GLOBALS['f7bk']['post_type'][] = $type;
		return (object) array( 'name' => $type );
	}
	function register_post_meta( $type, $key, $args = array() ) {
		$GLOBALS['f7bk']['meta'][ $type ][ $key ] = $args;
		return true;
	}
	function post_type_exists( $type ) {
		return in_array( $type, array_merge( array( 'post', 'page', 'attachment', 'elementor_library' ), $GLOBALS['f7bk']['post_type'] ?? array() ), true );
	}
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'category', 'post_tag', 'genero' ), true );
	}
	function is_taxonomy_viewable( $taxonomy ) {
		return true;
	}
	function is_protected_meta( $key, $type = '' ) {
		return '' !== $key && '_' === $key[0];
	}
	function get_post_types( $args = array(), $output = 'names' ) {
		return $GLOBALS['f7bk']['types'];
	}
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function maybe_unserialize( $value ) {
		return is_string( $value ) && is_serialized( $value ) ? unserialize( $value ) : $value;
	}
	function __( $text, $domain = '' ) {
		return $text;
	}
	function _n( $single, $plural, $number, $domain = '' ) {
		return 1 === (int) $number ? $single : $plural;
	}
	function wp_generate_uuid4() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
	}
	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			public $code;
			public function __construct( $code = '', $message = '' ) {
				$this->code = $code;
			}
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	/** `get_term_by()` por `term_taxonomy_id` (sin taxonomía, como lo usa el almacén) o por slug. */
	function get_term_by( $field, $value, $taxonomy = '' ) {
		foreach ( $GLOBALS['wp']['terms'] as $term ) {
			if ( 'term_taxonomy_id' === $field && (int) ( $term->term_taxonomy_id ?? 0 ) === (int) $value ) {
				return $term;
			}
			if ( 'slug' === $field && $term->slug === $value && ( '' === $taxonomy || $term->taxonomy === $taxonomy ) ) {
				return $term;
			}
		}
		return false;
	}
	/**
	 * `wp_insert_post()` con `meta_input`: cada meta pasa por el `sanitize_callback` registrado para su tipo (lo que hace
	 * `update_post_meta()` con `sanitize_meta()`), así una meta escrita antes de `Store::register()` llegaría sin sanear.
	 */
	function wp_insert_post( $args, $wp_error = false ) {
		$GLOBALS['f7bk']['log'][] = 'wp_insert_post:' . ( $args['post_type'] ?? 'post' );
		if ( $GLOBALS['f7bk']['fail_insert'] ) {
			return $wp_error ? new WP_Error( 'db_insert_error' ) : 0;
		}
		$id = kit_stub_post(
			array(
				'post_type'   => $args['post_type'] ?? 'post',
				'post_title'  => $args['post_title'] ?? '',
				'post_status' => $args['post_status'] ?? 'draft',
				'post_name'   => sanitize_title( (string) ( $args['post_title'] ?? '' ) ),
			)
		);
		foreach ( (array) ( $args['meta_input'] ?? array() ) as $key => $value ) {
			$callback = $GLOBALS['f7bk']['meta'][ $args['post_type'] ?? 'post' ][ $key ]['sanitize_callback'] ?? null;
			update_post_meta( $id, $key, $callback ? call_user_func( $callback, $value, $key, 'post' ) : $value );
		}
		return $id;
	}
	function wp_delete_post( $id, $force = false ) {
		$post = $GLOBALS['wp']['posts'][ (int) $id ] ?? null;
		unset( $GLOBALS['wp']['posts'][ (int) $id ], $GLOBALS['wp']['meta'][ (int) $id ] );
		$GLOBALS['f7bk']['log'][] = 'wp_delete_post:' . (int) $id;
		return $post ? $post : false;
	}
	function sanitize_title( $title ) {
		return trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( (string) $title ) ), '-' );
	}

	/** `$wpdb` mínimo para `F7b\Usages::prime()`: busca las filas de `_digitalisimo_uses` en las metas en memoria. */
	class F7bk_Wpdb {
		public $postmeta = 'wp_postmeta';
		public $posts    = 'wp_posts';
		public $log      = array();
		public function prepare( $sql, ...$args ) {
			return array( $sql, $args );
		}
		public function get_results( $prepared, $output = ARRAY_A ) {
			list( $sql, $args ) = $prepared;
			$this->log[]        = $sql;
			$key                = array_shift( $args );
			array_pop( $args );
			$rows = array();
			foreach ( $GLOBALS['wp']['meta'] as $id => $metas ) {
				$post = $GLOBALS['wp']['posts'][ $id ] ?? null;
				if ( ! $post || 'revision' === $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future', 'private', 'draft', 'pending' ), true ) ) {
					continue;
				}
				foreach ( (array) ( $metas[ $key ] ?? array() ) as $value ) {
					if ( in_array( $value, $args, true ) ) {
						$rows[] = array( 'ref' => $value, 'id' => $id, 'status' => $post->post_status );
					}
				}
			}
			return $rows;
		}
	}
	$GLOBALS['wpdb'] = new F7bk_Wpdb();

	require_once __DIR__ . '/elements-kit/wp-stubs.php';
	$E = realpath( __DIR__ . '/../digitalisimo-elements' );
	require $E . '/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();
	require $E . '/modules/digitalisimo-filters/class-query.php';
	require $E . '/modules/digitalisimo-f7b/class-module.php';
	\Digitalisimo\Elements\F7b\Module::register_autoloader();
}

namespace F7b_Kit_Test {
	use Digitalisimo\Elements\F7b\Kit\Module as Kit;
	use Digitalisimo\Elements\F7b\Kit\Queries_Part;
	use Digitalisimo\Elements\F7b\Queries\Store;
	use Digitalisimo\Elements\F7b\Scan;
	use Digitalisimo\Elements\F7b\Usages;
	use Digitalisimo\Elements\Import\Kits\Export\Meta_Blacklist;
	use Digitalisimo\Elements\Import\Kits\Export_Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\Parts;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Remapper;
	use Digitalisimo\Elements\Import\Kits\Report;
	use Digitalisimo\Elements\Import\Kits\Revert_Context;

	$check = 'f7bk_check';
	$E     = realpath( __DIR__ . '/../digitalisimo-elements' );
	$tmp   = sys_get_temp_dir() . '/digi-f7b-kit-' . bin2hex( random_bytes( 6 ) );
	$up    = 'https://a.test/wp-content/uploads';

	$site = function () use ( $tmp ) {
		kit_stub_reset( $tmp );
		References::reset();
		Parts::reset();
		Remapper::reset();
		Usages::forget();
		Store::forget();
		$GLOBALS['f7bk']['types']       = array(
			'post' => 'Entradas',
			'page' => 'Páginas',
		);
		$GLOBALS['f7bk']['fail_insert'] = false;
		$GLOBALS['wp']['users']         = array( 1 => true );
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
	$import_ctx = function ( array $maps = array(), string $mode = 'add', int $max = 1000, string $session = 'sesF7b' ) use ( $up ) {
		return new Import_Context(
			array(
				'session'     => $session,
				'mode'        => $mode,
				'origin'      => array(
					'home'    => 'https://a.test',
					'site'    => 'https://a.test',
					'uploads' => $up,
				),
				'maps'        => $maps,
				'max_post_id' => $max,
			)
		);
	};
	$run = function ( $part, array $data, Import_Context $context ) {
		$context->begin_part( $part->key() );
		$summary = $part->import( $data, 1, $context );
		$context->end_part();
		return $summary;
	};
	$query_meta = function ( int $id, string $key ) {
		return get_post_meta( $id, $key, true );
	};
	/** Una consulta del sitio como la deja la pantalla «Consultas» (`Store::save()` escribe estas metas). */
	$make_query = function ( string $title, array $settings, array $extra = array() ) {
		Store::register();
		$id = wp_insert_post(
			array(
				'post_type'   => Store::POST_TYPE,
				'post_title'  => $title,
				'post_status' => $extra['status'] ?? 'publish',
				'meta_input'  => array_filter(
					array(
						Store::META_UID      => $extra['uid'] ?? wp_generate_uuid4(),
						Store::META_SETTINGS => $settings,
						Store::META_CONTEXT  => $extra['context'] ?? 'none',
						Store::META_RELATION => $extra['relation'] ?? 'none',
						Store::META_LIMITS   => $extra['limits'] ?? array( 'per_page' => 6, 'depth' => 2 ),
					),
					function ( $value ) {
						return null !== $value && '' !== $value;
					}
				),
			)
		);
		Store::forget( (int) $id );
		return (int) $id;
	};

	require_once $E . '/modules/digitalisimo-f7b/kit/class-module.php';
	$K = $E . '/modules/digitalisimo-import';

	try {
		// ── 1. Registro ─────────────────────────────────────────────────────────────────────────────────────────
		$site();
		Kit::reset();
		Kit::init();
		Kit::init();
		$check( array( array( Kit::class, 'parts' ) ) === kit_stub_filters( 'digitalisimo_elements_kit_parts' ), '1: init() engancha la parte una sola vez (idempotente).' );
		$check( array( array( Kit::class, 'references' ) ) === kit_stub_filters( 'digitalisimo_elements_kit_references' ), '1: init() engancha las referencias.' );
		$check( array( array( Kit::class, 'labels' ) ) === kit_stub_filters( 'digitalisimo_elements_kit_report_labels' ), '1: init() engancha los textos del informe.' );
		$check( in_array( 'Kit\\Module', \Digitalisimo\Elements\F7b\Module::BOOT, true ) && '' !== \Digitalisimo\Elements\F7b\Module::file_for( Kit::class ), '1: Kit\\Module está en BOOT y la autocarga de A encuentra kit/class-module.php.' );
		$parts = Parts::all();
		$keys  = array_keys( $parts );
		$check( 'saved-queries' === end( $keys ) && array( 'origin', 'element-defaults', 'kit-settings', 'files', 'media', 'theme-builder', 'term-meta' ) === array_slice( $keys, 0, 7 ), '1: saved-queries queda detrás de las partes de K: ' . implode( ', ', $keys ) );
		$part = $parts['saved-queries'];
		$check( $part instanceof Queries_Part && $part instanceof \Digitalisimo\Elements\Import\Kits\Part && ! $part instanceof \Digitalisimo\Elements\Import\Kits\Early_Part, '1: Queries_Part implementa Part y no Early_Part.' );
		$check( 1 === $part->version() && array() === $part->kit_meta_keys() && 'saved-queries' === Queries_Part::KEY && Parts::accepts( 'saved-queries', 1 ) && ! Parts::accepts( 'saved-queries', 2 ), '1: versión 1, sin metas del kit; K no acepta una versión mayor.' );
		$check( array() === $GLOBALS['wp']['wrong'], '1: ningún _doing_it_wrong al registrar: ' . implode( ' | ', $GLOBALS['wp']['wrong'] ) );
		$source = (string) file_get_contents( $E . '/modules/digitalisimo-f7b/kit/class-queries-part.php' );
		$check( 1 === preg_match( '/^use Digitalisimo\\\\Elements\\\\Import\\\\Kits\\\\Part;$/m', $source ) && 1 === preg_match( "/const KEY\s*=\s*'saved-queries';/", $source ), '1: el inventario de kits la encuentra (use … Part y const KEY).' );
		$check( array( Queries_Part::class ) === Kit::parts( 'x' ) && array( 'a', Queries_Part::class ) === Kit::parts( array( 'a' ) ), '1: un filtro que no devuelve array no rompe nada y la parte se añade al final.' );
		// Sin K: la interfaz no existe y la parte no se registra (se comprueba la condición, no se descarga K).
		$check( false !== strpos( (string) file_get_contents( $E . '/modules/digitalisimo-f7b/kit/class-module.php' ), 'interface_exists( self::PART_INTERFACE )' ), '1: sin la interfaz de K la parte no se registra.' );

		// ── 2. Referencias ──────────────────────────────────────────────────────────────────────────────────────
		$index = References::all();
		$find  = function ( string $type, string $path ) use ( $index ) {
			foreach ( $index['element'][ $type ] ?? array() as $ref ) {
				if ( $path === $ref['path'] ) {
					return $ref;
				}
			}
			return null;
		};
		$query_ref = $find( '*', 'digitalisimo_saved_query' );
		$check( null !== $query_ref && 'map:queries' === $query_ref['kind'] && true === $query_ref['gate'] && null === $query_ref['never'], '2: digitalisimo_saved_query → map:queries con gate (sin destino: vacío y «revisar»).' );
		$target = $find( '*', '_digitalisimo_actions.*.target' );
		$popup  = $find( '*', '_digitalisimo_actions.*.popup_id' );
		$check( null !== $target && 'element' === $target['kind'] && false === $target['gate'] && null !== $popup && 'post' === $popup['kind'] && false === $popup['gate'], '2: acciones: target → element y popup_id → post, sin gate.' );
		foreach ( array( 'digitalisimo_overrides.*.image' => 'media', 'digitalisimo_overrides.*.link.url' => 'url', 'digitalisimo_overrides.*.html' => 'html' ) as $path => $kind ) {
			$ref = $find( 'template', $path );
			$check( null !== $ref && $kind === $ref['kind'] && false === $ref['gate'], "2: sobrescrituras del Plantilla: {$path} → {$kind}." );
		}
		foreach ( Kit::FILTER_WIDGETS as $widget ) {
			$ref = $find( $widget, 'selected_element' );
			$check( null !== $ref && 'element' === $ref['kind'] && false === $ref['gate'], "2: {$widget} · selected_element → element." );
		}
		$check( null === $find( 'template', 'template_id' ) && null === $find( 'loop-grid', 'template_id' ), '2: lo que ya traduce el núcleo (template_id del Plantilla y de Loop Grid) no se declara.' );
		$filters_registry = (string) file_get_contents( $E . '/modules/digitalisimo-filters/class-registry.php' );
		preg_match_all( "/^\t\t'(digitalisimo-loop-[a-z-]+)' => array\(/m", $filters_registry, $registered_filters );
		$check( Kit::FILTER_WIDGETS === $registered_filters[1], '2: los cuatro filtros son los de Filters\\Registry::WIDGETS.' );
		$matching = References::matching( 'element', 'loop-grid' );
		$check( in_array( 'digitalisimo_saved_query', array_column( $matching, 'path' ), true ) && in_array( '_digitalisimo_actions.*.target', array_column( $matching, 'path' ), true ), '2: matching() las aplica a cualquier elemento.' );
		$check( array() === $GLOBALS['wp']['wrong'], '2: todas las referencias son válidas para K: ' . implode( ' | ', $GLOBALS['wp']['wrong'] ) );

		// ── 3. Remapeo de documentos con las referencias reales ─────────────────────────────────────────────────
		$site();
		Kit::reset();
		Kit::init();
		kit_stub_post( array( 'ID' => 2001, 'post_type' => 'elementor_library', 'post_title' => 'Popup' ) );
		kit_stub_post( array( 'ID' => 1200, 'post_type' => Store::POST_TYPE, 'post_title' => 'Consulta' ) );
		kit_stub_post( array( 'ID' => 1500, 'post_type' => 'page', 'post_title' => 'Página importada' ) );
		$photo = kit_stub_post( array( 'ID' => 1100, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		update_post_meta( $photo, '_wp_attached_file', '2026/10/foto.jpg' );
		$maps = array(
			'post'       => array( 77 => 2001, 15 => 1500 ),
			'template'   => array( 77 => 2001 ),
			'url'        => array(
				$up              => 'https://b.test/wp-content/uploads',
				'https://a.test' => 'https://b.test',
			),
			'attachment' => array( $up . '/2026/01/foto.jpg' => 1100, 60 => 1100 ),
			'queries'    => array( 900 => '1200' ),
		);
		$ctx      = $import_ctx( $maps );
		$elements = array(
			array(
				'id'       => 'c000001',
				'elType'   => 'container',
				'settings' => array(
					'_digitalisimo_actions_on' => 'yes',
					'_digitalisimo_actions'    => array(
						array( '_id' => 'a1', 'trigger' => 'click', 'action' => 'show', 'target_kind' => 'element', 'target' => 'c000002' ),
						array( '_id' => 'a2', 'trigger' => 'click', 'action' => 'hide', 'target_kind' => 'element', 'target' => 'zz00000' ),
						array( '_id' => 'a3', 'trigger' => 'click', 'action' => 'popup_open', 'popup_id' => '77' ),
						array( '_id' => 'a4', 'trigger' => 'click', 'action' => 'popup_open', 'popup_id' => '78' ),
						array( '_id' => 'a5', 'trigger' => 'click', 'action' => 'toggle_class', 'target_kind' => 'self', 'class_name' => 'abierto' ),
					),
				),
				'elements' => array(
					array( 'id' => 'g000001', 'elType' => 'widget', 'widgetType' => 'loop-grid', 'settings' => array( 'digitalisimo_saved_query' => '900', 'template_id' => '77' ), 'elements' => array() ),
					array( 'id' => 'g000002', 'elType' => 'widget', 'widgetType' => 'posts', 'settings' => array( 'digitalisimo_saved_query' => '' ), 'elements' => array() ),
					array( 'id' => 'f000001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-loop-search', 'settings' => array( 'destination' => 'loop', 'selected_element' => 'g000001' ), 'elements' => array() ),
					array( 'id' => 'f000002', 'elType' => 'widget', 'widgetType' => 'digitalisimo-loop-orderby', 'settings' => array( 'destination' => 'loop', 'selected_element' => 'q999999' ), 'elements' => array() ),
					array( 'id' => 'f000003', 'elType' => 'widget', 'widgetType' => 'digitalisimo-loop-per-page', 'settings' => array( 'destination' => 'archive' ), 'elements' => array() ),
					array(
						'id'         => 't000001',
						'elType'     => 'widget',
						'widgetType' => 'template',
						'settings'   => array(
							'template_id'            => '77',
							'digitalisimo_overrides' => array(
								array( '_id' => 'o1', 'slot' => 'titulo', 'kind' => 'text', 'text' => 'Nuevo título' ),
								array( '_id' => 'o2', 'slot' => 'foto', 'kind' => 'image', 'image' => array( 'id' => 60, 'url' => $up . '/2026/01/foto.jpg' ) ),
								array( '_id' => 'o3', 'slot' => 'boton', 'kind' => 'link', 'link' => array( 'url' => 'https://a.test/contacto/', 'is_external' => 'on' ) ),
								array( '_id' => 'o4', 'slot' => 'texto', 'kind' => 'html', 'html' => '<p>Rico <a href="https://a.test/contacto/">enlace</a> <img src="' . $up . '/2026/01/foto.jpg"></p>' ),
								array( '_id' => 'o5', 'slot' => 'oculto', 'kind' => 'hide' ),
							),
						),
						'elements'   => array(),
					),
				),
			),
			array( 'id' => 'c000002', 'elType' => 'container', 'settings' => array( '_digitalisimo_hidden_initial' => 'yes' ), 'elements' => array() ),
		);
		$data    = Remapper::apply( array( 'elements' => $elements ), $ctx, 1500 );
		$out     = $data['elements'];
		$actions = $out[0]['settings']['_digitalisimo_actions'];
		$check( 'c000002' === $actions[0]['target'], '3: el destino de una acción que está en el documento se conserva.' );
		$check( '' === $actions[1]['target'], '3: un destino que no está en el documento queda vacío (la acción no se registra al pintar).' );
		$check( '2001' === $actions[2]['popup_id'] && '' === $actions[3]['popup_id'], '3: popup_id pasa al popup importado (como cadena) y, sin destino, queda vacío.' );
		$check( 'abierto' === $actions[4]['class_name'] && 'self' === $actions[4]['target_kind'] && ! isset( $actions[4]['target'] ), '3: lo demás de las acciones no cambia.' );
		$grid = $out[0]['elements'][0]['settings'];
		$check( '1200' === $grid['digitalisimo_saved_query'], '3: digitalisimo_saved_query pasa a la consulta del sitio y sigue siendo cadena (aguja de Flags).' );
		$check( '' === $out[0]['elements'][1]['settings']['digitalisimo_saved_query'], '3: «Ninguna» (vacío) no cambia.' );
		$check( 'g000001' === $out[0]['elements'][2]['settings']['selected_element'] && '' === $out[0]['elements'][3]['settings']['selected_element'] && ! isset( $out[0]['elements'][4]['settings']['selected_element'] ), '3: selected_element de los filtros: el bucle del documento se conserva; uno que falta queda vacío; «Archivo» sin bucle no cambia.' );
		$over = $out[0]['elements'][5]['settings']['digitalisimo_overrides'];
		$check( 1100 === $over[1]['image']['id'] && 'https://b.test/wp-content/uploads/2026/10/foto.jpg' === $over[1]['image']['url'], '3: la imagen de una sobrescritura pasa al adjunto importado.' );
		$check( 'https://b.test/contacto/' === $over[2]['link']['url'] && 'on' === $over[2]['link']['is_external'], '3: el enlace de una sobrescritura pasa al sitio de destino.' );
		$check( false !== strpos( $over[3]['html'], 'href="https://b.test/contacto/"' ) && false !== strpos( $over[3]['html'], 'src="https://b.test/wp-content/uploads/2026/10/foto.jpg"' ) && false === strpos( $over[3]['html'], 'a.test' ), '3: el HTML de una sobrescritura lleva enlaces e imágenes del destino.' );
		$check( 'titulo' === $over[0]['slot'] && 'Nuevo título' === $over[0]['text'] && 'hide' === $over[4]['kind'], '3: claves, tipos y texto de las sobrescrituras no cambian (son claves, no IDs).' );
		$check( array() === $ctx->review_ids() && array() === $codes( $ctx, 'map.miss' ), '3: con todos los destinos, nada queda para revisar ni sin mapa.' );
		$check( 3 === count( $codes( $ctx, 'unresolved' ) ), '3: el destino, el popup y el bucle sin destino se informan (unresolved).' );
		$json = json_encode( $out );
		$check( false !== strpos( $json, '"digitalisimo_saved_query":"1200"' ) && false !== strpos( $json, '"_digitalisimo_actions_on":"yes"' ) && false !== strpos( $json, '"_digitalisimo_hidden_initial":"yes"' ) && false !== strpos( $json, '"digitalisimo_overrides":[{' ), '3: las agujas de Flags siguen en los datos remapeados (saved_queries, actions, hidden, overrides).' );
		$check( array( 'q:1200', 't:77' ) === Usages::refs( $out ), '3: _digitalisimo_uses del documento remapeado (Usages::refs) nombra la consulta del sitio (el template_id del Plantilla lo traduce el núcleo, no K).' );

		// Consulta sin destino: vacía (el widget vuelve a sus propios ajustes, D24) y el documento a «revisar».
		$ctx  = $import_ctx( array( 'queries' => array( 900 => '1200' ) ) );
		$data = Remapper::apply(
			array(
				'elements' => array(
					array( 'id' => 'g000003', 'elType' => 'widget', 'widgetType' => 'loop-grid', 'settings' => array( 'digitalisimo_saved_query' => '901' ), 'elements' => array() ),
					array( 'id' => 'g000004', 'elType' => 'widget', 'widgetType' => 'portfolio', 'settings' => array( 'digitalisimo_saved_query' => 900 ), 'elements' => array() ),
				),
			),
			$ctx,
			1501
		);
		$check( '' === $data['elements'][0]['settings']['digitalisimo_saved_query'], '3: una consulta sin destino queda vacía: nunca el ID del origen, que aquí podría ser otra consulta.' );
		$check( array( 1501 ) === $ctx->review_ids() && 'gate-unresolved' === $ctx->review()[1501][0]['reason'], '3: el documento con una consulta sin destino queda en borrador con «revisar antes de publicar».' );
		$check( '1200' === (string) $data['elements'][1]['settings']['digitalisimo_saved_query'], '3: un ID entero también se traduce.' );
		$ctx  = $import_ctx( array() );
		$data = Remapper::apply( array( 'elements' => array( array( 'id' => 'g000005', 'elType' => 'widget', 'widgetType' => 'loop-grid', 'settings' => array( 'digitalisimo_saved_query' => '900' ), 'elements' => array() ) ) ), $ctx, 1502 );
		$check( '' === $data['elements'][0]['settings']['digitalisimo_saved_query'] && array( 1502 ) === $ctx->review_ids(), '3: un kit sin la parte (sin mapa queries) tampoco deja IDs del origen.' );

		// ── 4. Importar ─────────────────────────────────────────────────────────────────────────────────────────
		$site();
		// Destino: los términos tienen otro term_id y otro term_taxonomy_id (el mapa de K va por term_id).
		kit_stub_term( array( 'term_id' => 540, 'term_taxonomy_id' => 777, 'taxonomy' => 'category', 'slug' => 'noticias' ) );
		kit_stub_term( array( 'term_id' => 541, 'term_taxonomy_id' => 778, 'taxonomy' => 'post_tag', 'slug' => 'destacado' ) );
		kit_stub_post( array( 'ID' => 1300, 'post_type' => 'post', 'post_title' => 'Entrada importada' ) );
		$GLOBALS['f7bk']['log'] = array();
		$maps                   = array(
			'term' => array( 40 => 540, 41 => 541 ),
			'post' => array( 30 => 1300 ),
		);
		$kit_rows = array(
			// 1: todo resuelve → se crea publicada.
			array(
				'id'       => 101,
				'uid'      => 'AAAAAAAA-0000-4000-8000-000000000001',
				'title'    => 'Noticias del kit',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 140 ), 'exclude' => array( 'terms', 'manual_selection' ), 'exclude_term_ids' => array( 141 ), 'exclude_ids' => array( 30 ), 'orderby' => 'post_title', 'order' => 'ASC' ),
				'context'  => 'none',
				'relation' => 'none',
				'limits'   => array( 'per_page' => 6, 'depth' => 1 ),
				'terms'    => array(
					'140' => array( 'term' => 40, 'taxonomy' => 'category' ),
					'141' => array( 'term' => 41, 'taxonomy' => 'post_tag' ),
				),
			),
			// 2: autora excluida (los usuarios no viajan) → borrador.
			array(
				'id'       => 102,
				'uid'      => '00000000-0000-4000-8000-000000000002',
				'title'    => 'Sin la autora',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'post', 'exclude' => array( 'authors' ), 'exclude_authors' => array( 3 ) ),
				'relation' => 'author',
				'context'  => 'current',
			),
			// 3: autores en una lista que no usa → se vacía en silencio y sigue publicada.
			array(
				'id'       => 103,
				'uid'      => '00000000-0000-4000-8000-000000000003',
				'title'    => 'Lista sin usar',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'post', 'include_authors' => array( 3 ) ),
			),
			// 4: tipo que el sitio no publica → borrador.
			array(
				'id'       => 104,
				'uid'      => '00000000-0000-4000-8000-000000000004',
				'title'    => 'Productos',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'product' ),
			),
			// 5: relación con una taxonomía que no existe y 6: contexto retirado (parent) → borrador.
			array(
				'id'       => 105,
				'uid'      => '00000000-0000-4000-8000-000000000005',
				'title'    => 'Mismo color',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'post' ),
				'relation' => 'terms:color',
				'context'  => 'current',
			),
			array(
				'id'       => 106,
				'uid'      => '00000000-0000-4000-8000-000000000006',
				'title'    => 'Contexto del padre',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'post' ),
				'relation' => 'author',
				'context'  => 'parent',
			),
			// 7: borrador en el origen → sigue en borrador sin aviso; 8: incluye un término sin pareja → borrador.
			array(
				'id'       => 107,
				'uid'      => '00000000-0000-4000-8000-000000000007',
				'title'    => 'Borrador del origen',
				'status'   => 'draft',
				'settings' => array( 'post_type' => 'page' ),
			),
			array(
				'id'       => 108,
				'uid'      => '00000000-0000-4000-8000-000000000008',
				'title'    => 'Término perdido',
				'status'   => 'publish',
				'settings' => array( 'post_type' => 'post', 'include' => array( 'terms' ), 'include_term_ids' => array( 140, 555 ) ),
				'terms'    => array( '140' => array( 'term' => 40, 'taxonomy' => 'category' ) ),
			),
			// Formato no válido: sin UUID, sin título y no un array.
			array( 'id' => 109, 'title' => 'Sin UUID', 'settings' => array() ),
			array( 'id' => 110, 'uid' => '00000000-0000-4000-8000-000000000010', 'settings' => array() ),
			'cadena',
		);
		$check( ! isset( $GLOBALS['f7bk']['meta'][ Store::POST_TYPE ] ), '4: nada registró aún el tipo ni sus metas: lo hace la parte.' );
		$ctx     = $import_ctx( $maps );
		$part    = new Queries_Part();
		$summary = $run( $part, array( 'v' => 1, 'queries' => $kit_rows ), $ctx );
		$check( 11 === $summary['queries'] && 8 === $summary['created'] && 0 === $summary['reused'] && 5 === $summary['drafts'] && 3 === $summary['invalid'] && 0 === $summary['failed'], '4: resumen: 8 creadas, 5 en borrador, 3 no válidas: ' . json_encode( $summary ) );
		$first_insert = array_search( 'wp_insert_post:' . Store::POST_TYPE, $GLOBALS['f7bk']['log'], true );
		$register     = array_search( 'register_post_type:' . Store::POST_TYPE, $GLOBALS['f7bk']['log'], true );
		$check( false !== $register && false !== $first_insert && $register < $first_insert, '4: Store::register() antes de escribir la primera consulta.' );
		$qmap = $ctx->map( 'queries' );
		$check( 8 === count( $qmap ) && array_values( $qmap ) === array_map( 'strval', array_values( $qmap ) ), '4: el mapa queries tiene las 8 creadas, con IDs del sitio como cadena.' );
		$by_title = array();
		foreach ( $qmap as $old => $new ) {
			$by_title[ get_post( (int) $new )->post_title ] = (int) $new;
		}
		$n1 = $by_title['Noticias del kit'];
		$s1 = $query_meta( $n1, Store::META_SETTINGS );
		$check( 'publish' === get_post( $n1 )->post_status && 'aaaaaaaa-0000-4000-8000-000000000001' === $query_meta( $n1, Store::META_UID ), '4: se crea publicada, con el UUID del kit (en minúsculas).' );
		$check( array( 777 ) === $s1['include_term_ids'] && array( 778 ) === $s1['exclude_term_ids'], '4: términos: term_taxonomy_id del sitio a partir del term_id del mapa de K (140 → 40 → 540 → 777).' );
		$check( array( 1300 ) === $s1['exclude_ids'] && 'post_title' === $s1['orderby'] && 'asc' === $s1['order'] && array( 'per_page' => 6, 'depth' => 1 ) === $query_meta( $n1, Store::META_LIMITS ), '4: entradas excluidas traducidas; orden y topes saneados por Store.' );
		$check( (string) $n1 === $qmap[101] && 'none' === $query_meta( $n1, Store::META_RELATION ) && 'none' === $query_meta( $n1, Store::META_CONTEXT ), '4: el mapa lleva el ID de origen a la consulta creada.' );
		$n2 = $by_title['Sin la autora'];
		$s2 = $query_meta( $n2, Store::META_SETTINGS );
		$check( 'draft' === get_post( $n2 )->post_status && array() === $s2['exclude_authors'] && array( 'authors' ) === $s2['exclude'], '4: un autor excluido nunca tiene destino: la lista queda vacía, «excluir por autores» se conserva y la consulta entra en borrador.' );
		$n3 = $by_title['Lista sin usar'];
		$check( 'publish' === get_post( $n3 )->post_status && array() === $query_meta( $n3, Store::META_SETTINGS )['include_authors'], '4: una lista que la consulta no usa se vacía sin borrador.' );
		$check( 'draft' === get_post( $by_title['Productos'] )->post_status && '' === $query_meta( $by_title['Productos'], Store::META_SETTINGS )['post_type'], '4: un tipo que el sitio no publica deja la consulta no válida y en borrador.' );
		$check( 'draft' === get_post( $by_title['Mismo color'] )->post_status && 'none' === $query_meta( $by_title['Mismo color'], Store::META_RELATION ), '4: una relación con una taxonomía que no existe: borrador (sin relación mostraría de más).' );
		$check( 'draft' === get_post( $by_title['Contexto del padre'] )->post_status && 'none' === $query_meta( $by_title['Contexto del padre'], Store::META_CONTEXT ), '4: el contexto retirado (parent) se sanea a none y la consulta entra en borrador (Store la da por no válida).' );
		$n7 = $by_title['Borrador del origen'];
		$check( 'draft' === get_post( $n7 )->post_status, '4: un borrador del origen sigue en borrador.' );
		$n8 = $by_title['Término perdido'];
		$check( 'draft' === get_post( $n8 )->post_status && array( 777 ) === $query_meta( $n8, Store::META_SETTINGS )['include_term_ids'] && array( 'terms' ) === $query_meta( $n8, Store::META_SETTINGS )['include'], '4: un término sin pareja en el kit se quita (nunca se traduce como term_id) y «incluir por términos» se conserva: borrador.' );
		$drafts = $codes( $ctx, 'draft.saved-query' );
		$reasons = array();
		foreach ( $drafts as $entry ) {
			$reasons[ $entry['data']['title'] ] = $entry['data']['reasons'];
		}
		$check(
			array(
				'Sin la autora'      => array( 'authors' ),
				'Productos'          => array( 'post_type' ),
				'Mismo color'        => array( 'relation' ),
				'Contexto del padre' => array( 'context' ),
				'Término perdido'    => array( 'terms' ),
			) === $reasons,
			'4: cada borrador con su motivo en el informe: ' . json_encode( $reasons )
		);
		$check( 3 === count( $codes( $ctx, 'reference.saved-query-invalid' ) ), '4: las tres entradas sin formato se informan y se descartan.' );
		$unresolved = $codes( $ctx, 'unresolved' );
		$paths      = array_column( array_column( $unresolved, 'data' ), 'path' );
		sort( $paths );
		$check( array( 'exclude_authors', 'include_term_ids' ) === $paths, '4: lo que no resuelve en una lista que se usa se informa (unresolved); la lista sin usar, no: ' . json_encode( $paths ) );
		$check( array( 'created' ) === array_keys( $ctx->stored( 'saved-queries' ) ) && 8 === count( $ctx->stored( 'saved-queries' )['created'] ), '4: guarda lo creado (con su huella) para revertir.' );

		// R5: reimportar el mismo kit en el sitio que ya lo tiene no crea filas ni escribe nada.
		$posts_before = $GLOBALS['wp']['posts'];
		$meta_before  = $GLOBALS['wp']['meta'];
		$GLOBALS['f7bk']['log'] = array();
		$ctx2     = $import_ctx( $maps, 'add', 5000, 'sesF7b2' );
		$summary2 = $run( $part, array( 'v' => 1, 'queries' => $kit_rows ), $ctx2 );
		$check( 0 === $summary2['created'] && 8 === $summary2['reused'] && 0 === $summary2['differs'], '4 (R5): la segunda vez reutiliza las 8 por UUID sin diferencias: ' . json_encode( $summary2 ) );
		$check( $posts_before == $GLOBALS['wp']['posts'] && $meta_before == $GLOBALS['wp']['meta'] && array() === array_filter( $GLOBALS['f7bk']['log'], function ( $line ) { return 0 === strpos( $line, 'wp_insert_post' ); } ), '4 (R5): 0 filas nuevas y ninguna escritura.' );
		$check( $qmap == $ctx2->map( 'queries' ) && array() === $ctx2->stored( 'saved-queries' ), '4 (R5): el mismo mapa y nada de la sesión que revertir.' );
		$check( array() === $codes( $ctx2, 'unresolved' ) && array() === $codes( $ctx2, 'draft.saved-query' ), '4 (R5): reutilizar no repite avisos de referencias.' );

		// Reutilizar en «nuevo» con otros ajustes: se conserva la del sitio y se avisa.
		$changed                                  = $kit_rows[0];
		$changed['settings']['orderby']           = 'modified';
		$GLOBALS['f7bk']['log']                   = array();
		$ctx3                                     = $import_ctx( $maps, 'fresh', 5000, 'sesF7b3' );
		$summary3                                 = $run( $part, array( 'v' => 1, 'queries' => array( $changed ) ), $ctx3 );
		$check( 1 === $summary3['reused'] && 1 === $summary3['differs'] && 1 === count( $codes( $ctx3, 'requested.saved-query' ) ) && 'post_title' === $query_meta( $n1, Store::META_SETTINGS )['orderby'], '4: el sitio ya tenía la consulta con otros ajustes: se conserva la suya (también en «nuevo») y se informa.' );
		$check( (string) $n1 === $ctx3->map( 'queries' )[101], '4: el mapa apunta a la del sitio.' );

		// Sin consultas en el kit (o kit de terceros): nada.
		$GLOBALS['f7bk']['log'] = array();
		$ctx4                   = $import_ctx( $maps );
		$check( array() === $run( $part, array(), $ctx4 ) && array() === $ctx4->map( 'queries' ) && array() === $GLOBALS['f7bk']['log'], '4: un kit sin la parte no cambia nada.' );

		// Fallo al crear: se informa y no entra en el mapa (la referencia queda sin destino y cierra).
		$GLOBALS['f7bk']['fail_insert'] = true;
		$fresh                          = $kit_rows[0];
		$fresh['uid']                   = '00000000-0000-4000-8000-0000000000ff';
		$ctx5                           = $import_ctx( $maps );
		$summary5                       = $run( $part, array( 'queries' => array( $fresh ) ), $ctx5 );
		$check( 1 === $summary5['failed'] && array() === $ctx5->map( 'queries' ) && 1 === count( $codes( $ctx5, 'reference.saved-query-failed' ) ), '4: una consulta que no se puede crear se informa y no entra en el mapa.' );
		$GLOBALS['f7bk']['fail_insert'] = false;

		// ── 5. Revertir ─────────────────────────────────────────────────────────────────────────────────────────
		$stored = $ctx->stored( 'saved-queries' );
		// Editada después de importar: se conserva.
		$edited                        = get_post_meta( $n3, Store::META_SETTINGS, true );
		$edited['orderby']             = 'modified';
		update_post_meta( $n3, Store::META_SETTINGS, $edited );
		// Usada por un documento del sitio (publicado) y otra por un borrador del sitio: se conservan.
		kit_stub_post( array( 'ID' => 1600, 'post_type' => 'page', 'post_status' => 'publish' ) );
		add_post_meta( 1600, Usages::META, 'q:' . $n1 );
		kit_stub_post( array( 'ID' => 1601, 'post_type' => 'page', 'post_status' => 'draft' ) );
		add_post_meta( 1601, Usages::META, 'q:' . $n7 );
		// Usada sólo por un documento de la sesión, ya en la papelera: no cuenta.
		kit_stub_post( array( 'ID' => 1602, 'post_type' => 'page', 'post_status' => 'trash' ) );
		add_post_meta( 1602, Usages::META, 'q:' . $n2 );
		Usages::forget();
		Store::forget();
		$GLOBALS['f7bk']['log'] = array();
		$revert                 = new Revert_Context( array( 'session' => 'sesF7b' ) );
		$revert->begin_part( 'saved-queries' );
		$part->revert( $stored, $revert );
		$revert->end_part();
		$deleted = array_map(
			function ( $line ) {
				return (int) substr( $line, strlen( 'wp_delete_post:' ) );
			},
			array_values( array_filter( $GLOBALS['f7bk']['log'], function ( $line ) { return 0 === strpos( $line, 'wp_delete_post:' ); } ) )
		);
		sort( $deleted );
		$expected = array_values( array_diff( array_map( 'intval', array_keys( $stored['created'] ) ), array( $n1, $n3, $n7 ) ) );
		sort( $expected );
		$check( $expected === $deleted && 5 === count( $deleted ), '5: borra las 5 que siguen igual y sin usos (también la que sólo usaba un documento ya en la papelera).' );
		$check( null !== get_post( $n1 ) && null !== get_post( $n3 ) && null !== get_post( $n7 ), '5: conserva la editada y las que usa contenido del sitio.' );
		$check( 1 === count( $codes( $revert, 'revert.saved-query-edited' ) ) && 2 === count( $codes( $revert, 'revert.saved-query-in-use' ) ) && 1 === count( $codes( $revert, 'revert.saved-queries' ) ), '5: lo conservado y lo borrado se informa.' );
		$GLOBALS['f7bk']['log'] = array();
		$part->revert( array(), $revert );
		$part->revert( array( 'created' => array( '999999' => 'x' ) ), $revert );
		$check( array() === $GLOBALS['f7bk']['log'], '5: sin nada guardado, o con algo que ya no existe, no borra nada.' );
		kit_stub_post( array( 'ID' => 1700, 'post_type' => 'page' ) );
		$part->revert( array( 'created' => array( '1700' => Part_Context::hash( array() ) ) ), $revert );
		$check( null !== get_post( 1700 ), '5: nunca borra algo que no es una consulta guardada.' );

		// ── 6. Exportar ─────────────────────────────────────────────────────────────────────────────────────────
		$site();
		kit_stub_term( array( 'term_id' => 40, 'term_taxonomy_id' => 140, 'taxonomy' => 'category', 'slug' => 'noticias' ) );
		kit_stub_term( array( 'term_id' => 41, 'term_taxonomy_id' => 141, 'taxonomy' => 'post_tag', 'slug' => 'destacado' ) );
		$GLOBALS['wp']['users'][3] = true;
		$uid_a                     = '0f1e2d3c-4b5a-4968-8776-a5b4c3d2e1f0';
		$qa                        = $make_query(
			'Noticias sin destacado',
			array(
				'post_type'        => 'post',
				'include'          => array( 'terms' ),
				'include_term_ids' => array( 140 ),
				'exclude'          => array( 'terms', 'authors' ),
				'exclude_term_ids' => array( 141, 999 ),
				'exclude_authors'  => array( 3 ),
				'orderby'          => 'post_title',
				'order'            => 'asc',
			),
			array(
				'uid'      => $uid_a,
				'relation' => 'terms:category',
				'context'  => 'current',
			)
		);
		$qb = $make_query( 'Borrador', array( 'post_type' => 'page' ), array( 'status' => 'draft' ) );
		$qc = $make_query( 'En la papelera', array( 'post_type' => 'post' ), array( 'status' => 'trash' ) );
		$qd = $make_query( 'Sin UUID', array( 'post_type' => 'post' ) );
		delete_post_meta( $qd, Store::META_UID );
		Store::forget();
		$export_ctx = new Export_Context( array( 'origin' => array( 'home' => 'https://a.test', 'site' => 'https://a.test', 'uploads' => $up ) ) );
		$exported   = ( new Queries_Part() )->export( $export_ctx );
		$check( is_array( $exported ) && 1 === $exported['v'] && 3 === count( $exported['queries'] ), '6: exporta las publicadas y los borradores, nunca la papelera.' );
		$rows = array_column( $exported['queries'], null, 'title' );
		$check( ! isset( $rows['En la papelera'] ) && 'draft' === $rows['Borrador']['status'] && 'publish' === $rows['Noticias sin destacado']['status'], '6: cada consulta con su estado.' );
		$row = $rows['Noticias sin destacado'];
		$check( $qa === $row['id'] && $uid_a === $row['uid'] && 'terms:category' === $row['relation'] && 'current' === $row['context'] && array( 'per_page' => 6, 'depth' => 2 ) === $row['limits'], '6: ID de origen, UUID, relación, contexto y topes.' );
		$check( array( 140 ) === $row['settings']['include_term_ids'] && array( 141, 999 ) === $row['settings']['exclude_term_ids'] && array( 3 ) === $row['settings']['exclude_authors'] && 'post_title' === $row['settings']['orderby'], '6: los ajustes saneados de Store (term_taxonomy_id, autores).' );
		$check(
			array(
				'140' => array( 'term' => 40, 'taxonomy' => 'category' ),
				'141' => array( 'term' => 41, 'taxonomy' => 'post_tag' ),
			) == $row['terms'],
			'6: cada term_taxonomy_id con su term_id y su taxonomía; el que ya no existe no lleva pareja.'
		);
		$derived = $rows['Sin UUID']['uid'];
		$check( Store::is_uuid( $derived ) && $derived === ( new Queries_Part() )->export( $export_ctx )['queries'][2]['uid'] && '' === (string) get_post_meta( $qd, Store::META_UID, true ), '6: una consulta sin UUID exporta uno v4 estable, sin escribir en el origen.' );
		$check( false === strpos( json_encode( $exported ), '_digitalisimo_uses' ) && array() === $export_ctx->files(), '6: la parte no lleva la meta de usos ni archivos.' );
		$site();
		$check( null === ( new Queries_Part() )->export( $export_ctx ), '6: sin consultas, la parte no escribe archivo.' );

		// ── 7. `_digitalisimo_uses` ─────────────────────────────────────────────────────────────────────────────
		$site();
		Usages::reset();
		Usages::init();
		Scan::init();
		$skip = false;
		foreach ( kit_stub_filters( 'wxr_export_skip_postmeta' ) as $callback ) {
			$skip = call_user_func( $callback, $skip, Usages::META );
		}
		$check( true === $skip && true === Meta_Blacklist::skip( $skip, Usages::META ) && false === Meta_Blacklist::skip( false, Usages::META ), '7: _digitalisimo_uses no sale en el WXR (filtro de A) y la lista negra de K respeta ese true.' );
		$check( false === Usages::skip_export( false, '_thumbnail_id' ), '7: el filtro de A no quita otras metas.' );
		$check( in_array( array( Scan::class, 'schedule' ), kit_stub_filters( 'digitalisimo_elements_kit_imported' ), true ), '7: al terminar una importación (digitalisimo_elements_kit_imported) se programa el recorrido de A.' );
		$check( in_array( array( Usages::class, 'after_save' ), kit_stub_filters( 'elementor/document/after_save' ), true ), '7: cada documento importado reescribe sus usos al guardarse (elementor/document/after_save).' );
		$finish = (string) file_get_contents( $K . '/kits/runners/class-finish.php' );
		$check( 'digitalisimo_elements_kit_imported' === \Digitalisimo\Elements\Import\Module::ACTION_IMPORTED && false !== strpos( $finish, 'do_action( Module::ACTION_IMPORTED, $session, $report );' ), '7: el runner final de K dispara digitalisimo_elements_kit_imported (kits/runners/class-finish.php).' );
		$document = (string) file_get_contents( $E . '/elementor/core/base/document.php' );
		$check( false !== strpos( $document, "is_protected_meta( \$meta_key, 'post' )" ), '7: las metas de un documento exportado omiten las protegidas (_digitalisimo_uses empieza por _).' );

		// ── 8. Informe y K sin cambios ─────────────────────────────────────────────────────────────────────────
		Kit::reset();
		Kit::init();
		Report::reset_labels();
		$labels = Report::labels();
		$found  = array();
		foreach ( glob( $E . '/modules/digitalisimo-f7b/kit/*.php' ) as $file ) {
			preg_match_all( "/->report\(\s*'([a-z0-9_:.-]+)'/", (string) file_get_contents( $file ), $m );
			foreach ( $m[1] as $code ) {
				$found[ $code ] = basename( $file );
			}
		}
		$check( count( $found ) >= 8, '8: se encontraron los códigos de la parte (' . count( $found ) . ').' );
		foreach ( $found as $code => $file ) {
			$check( 'other' !== Report::section_for( $code ), "8: el código {$code} ({$file}) tiene sección en el informe." );
			$check( isset( $labels[ $code ] ) || 'unresolved' === $code, "8: el código {$code} tiene texto." );
		}
		foreach ( Kit::LABELS as $code => $text ) {
			$check( isset( $found[ $code ] ) && 'other' !== Report::section_for( $code ) && $text === $labels[ $code ], "8: el texto de {$code} es de un código que se emite y K lo acepta." );
		}
		$check( 'drafts' === Report::section_for( 'draft.saved-query' ) && 'unresolved' === Report::section_for( 'reference.saved-query-failed' ) && 'requested' === Report::section_for( 'requested.saved-query' ) && 'trash' === Report::section_for( 'revert.saved-queries' ), '8: borradores, referencias sin destino, lo que el kit pedía y la reversión.' );
		$k_code = '';
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $K, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( '.php' === substr( (string) $file, -4 ) ) {
				$k_code .= (string) file_get_contents( (string) $file );
			}
		}
		// Los widgets de F7b por nombre: la Cuadrícula y el Carrusel de bucle propios (C5) comparten el prefijo
		// `digitalisimo-loop-`, pero son widgets del programa sin PRO y K declara sus referencias como las de los demás propios.
		$named = array_filter(
			array( 'Elements\\F7b', 'digitalisimo-f7b', 'digitalisimo_saved_query', 'digitalisimo_query', '_digitalisimo_actions', 'digitalisimo_overrides', 'digitalisimo-loop-search', 'digitalisimo-loop-orderby', 'digitalisimo-loop-per-page', 'digitalisimo-loop-meta-filter', '_digitalisimo_uses' ),
			function ( $needle ) use ( $k_code ) {
				return false !== strpos( $k_code, $needle );
			}
		);
		$check( array() === $named, '8: el código de K no nombra nada de F7b (todo va por el registro): ' . implode( ', ', $named ) );
		$check( array() === $GLOBALS['wp']['wrong'], '8: ningún _doing_it_wrong: ' . implode( ' | ', $GLOBALS['wp']['wrong'] ) );
	} catch ( \Throwable $e ) {
		fwrite( STDERR, 'FALLO: ' . get_class( $e ) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString() . PHP_EOL );
		exit( 1 );
	}

	echo 'OK: ' . $GLOBALS['f7bk']['checks'] . " comprobaciones de los kits de F7b (parte saved-queries, referencias y _digitalisimo_uses).\n";
}
