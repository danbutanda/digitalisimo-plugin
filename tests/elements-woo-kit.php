<?php
/**
 * F4-Woo · frente H: kits de WooCommerce (V.11 de la especificación), con las clases reales de K, de
 * `Swatches\Data` (frente D) y del cerrojo compartido, WordPress en memoria (`tests/elements-kit/wp-stubs.php`) y
 * dobles de WooCommerce 11.2.1 con sus reglas (`wc_create_attribute()` valida el tipo; `set_sku()` lanza con un SKU
 * repetido; `has_callable()` del almacén).
 *
 *  0. Sin WooCommerce: las partes no exportan nada e informan `woocommerce.inactive` si el kit trae sus datos.
 *  1. Contrato y registro: `Attributes_Part implements Early_Part` (con su `use`), `Variations_Part implements Part`,
 *     claves, versiones, `kit_meta_keys()` vacío; `Kit\Module::init()` engancha partes, referencias, el contador y el
 *     runner de medios; las partes quedan detrás de las de K y sólo `woo-attributes` es temprana.
 *  2. Referencias (`References::matching()`): los IDs de los widgets nuevos sin `gate`, `product_id` con `never`, y
 *     `image` sólo en términos `pa_*`.
 *  3. `woo-attributes` · exportar: todos los atributos cuya taxonomía exporta el núcleo, con su huella y sus muestras.
 *  4. `woo-attributes` · `import_early()`: crear en la misma petición (`register_taxonomy()` y la global de
 *     WooCommerce), reutilizar sin cambiar (`attribute-differs`), `has_archives` a 0 en «agregar» y tal cual en
 *     «nuevo», el tipo que el destino no ofrece vuelve a `select`, fallos, reintento sin duplicar.
 *  5. `woo-attributes` · `import()`: muestras sólo en los atributos creados, nunca sobre lo del sitio en «agregar».
 *  6. `woo-attributes` · `revert()`: «no pisar» con la huella del atributo (R8) y de sus muestras, y en uso ajeno.
 *  7. `woo-variations` · exportar: sólo de variables exportados, con sus metas sin las internas ni la lista negra,
 *     `downloads` no exportado, imágenes para `media` y el contador `variations` que sólo baja (V.11.4).
 *  8. `woo-variations` · importar: sólo bajo padres creados, SKU repetido, imagen traducida o sin destino, término que
 *     falta (no se amplía a «cualquiera»), `sync()`, tabla de búsqueda de todos los productos creados (también los
 *     simples), reintento sin duplicar.
 *  9. `woo-variations` · revertir con «no pisar».
 * 10. Runner de medios: sólo con el contexto de exportación de K; anota las imágenes de las variaciones.
 * 11. Cada código del informe tiene sección; PHP 7.4; sin clases `Automattic\WooCommerce\Internal\`.
 */

namespace Elementor\App\Modules\ImportExportCustomization\Runners {
	interface Runner_Interface {
		public static function get_name(): string;
	}
}

namespace Elementor\App\Modules\ImportExportCustomization\Runners\Export {
	abstract class Export_Runner_Base implements \Elementor\App\Modules\ImportExportCustomization\Runners\Runner_Interface {
		abstract public function should_export( array $data );
		abstract public function export( array $data );
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['wc'] = array();

	/** Estado de WooCommerce en memoria. */
	function woo_kit_reset(): void {
		$GLOBALS['wc'] = array(
			'attributes'    => array(),
			'next_attr'     => 1,
			'registered'    => array(),
			'created'       => array(),
			'deleted'       => array(),
			'fail_create'   => false,
			'types'         => array(),
			'variations'    => array(),
			'skus'          => array(),
			'synced'        => array(),
			'transients'    => array(),
			'lookup'        => array(),
			'saved'         => array(),
			'direct'        => true,
			'relationships' => array(),
			'visual'        => false,
		);
		$GLOBALS['wc_product_attributes'] = array();
	}
	woo_kit_reset();

	// WordPress que no traen los stubs de K (o que aquí necesitan las taxonomías registradas).
	function taxonomy_exists( $taxonomy ) {
		return in_array( $taxonomy, array( 'category', 'post_tag', 'product_cat', 'product_tag', 'product_shipping_class' ), true ) || isset( $GLOBALS['wc']['registered'][ $taxonomy ] );
	}
	function get_object_taxonomies( $type ) {
		if ( 'product' === $type ) {
			return array_merge( array( 'product_cat', 'product_tag' ), array_keys( $GLOBALS['wc']['registered'] ) );
		}
		return 'post' === $type ? array( 'category', 'post_tag' ) : array();
	}
	function register_taxonomy( $taxonomy, $objects, $args ) {
		$GLOBALS['wc']['registered'][ $taxonomy ] = array( 'objects' => $objects, 'args' => $args );
	}
	function get_term_by( $field, $value, $taxonomy ) {
		foreach ( $GLOBALS['wp']['terms'] as $term ) {
			if ( $term->taxonomy === $taxonomy && 'slug' === $field && $term->slug === $value ) {
				return $term;
			}
		}
		return false;
	}
	function get_objects_in_term( $terms, $taxonomy ) {
		$out = array();
		foreach ( (array) $terms as $term ) {
			foreach ( $GLOBALS['wc']['relationships'][ (int) $term ] ?? array() as $object ) {
				$out[] = $object;
			}
		}
		return $out;
	}
	/** `get_posts()` con lo que usan las partes: `post__in`, `post_parent__in` y `fields` `ids` o `id=>parent`. */
	function get_posts( $args = array() ) {
		$types    = (array) ( $args['post_type'] ?? 'post' );
		$statuses = $args['post_status'] ?? 'publish';
		$found    = array();
		foreach ( $GLOBALS['wp']['posts'] as $id => $post ) {
			if ( ! in_array( $post->post_type, $types, true ) ) {
				continue;
			}
			if ( 'any' === $statuses ? in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) : ! in_array( $post->post_status, (array) $statuses, true ) ) {
				continue;
			}
			if ( isset( $args['post__in'] ) && ! in_array( (int) $id, array_map( 'intval', $args['post__in'] ), true ) ) {
				continue;
			}
			if ( isset( $args['post_parent__in'] ) && ! in_array( (int) $post->post_parent, array_map( 'intval', $args['post_parent__in'] ), true ) ) {
				continue;
			}
			$found[] = $post;
		}
		usort(
			$found,
			function ( $a, $b ) {
				return array( (int) ( $a->menu_order ?? 0 ), $a->ID ) <=> array( (int) ( $b->menu_order ?? 0 ), $b->ID );
			}
		);
		$fields = $args['fields'] ?? '';
		return array_map(
			function ( $post ) use ( $fields ) {
				if ( 'ids' === $fields ) {
					return (int) $post->ID;
				}
				return 'id=>parent' === $fields ? (object) array( 'ID' => (int) $post->ID, 'post_parent' => (int) $post->post_parent ) : $post;
			},
			$found
		);
	}
	function sanitize_title( $title ) {
		return trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( (string) $title ) ), '-' );
	}
	function sanitize_text_field( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function wp_kses_post( $text ) {
		return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $text );
	}
	function wp_load_alloptions() {
		return $GLOBALS['wp']['options'];
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['wp']['options'][ $name ] = $value;
		return true;
	}
	function maybe_unserialize( $value ) {
		return is_string( $value ) && is_serialized( $value ) ? unserialize( $value ) : $value;
	}
	function wp_cache_delete( $key, $group = '' ) {
		return true;
	}
	function wp_generate_password( $length = 12, $special = true ) {
		return substr( bin2hex( random_bytes( 16 ) ), 0, $length );
	}
	function absint( $value ) {
		return abs( (int) $value );
	}
	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			public $code;
			public function __construct( $code = '', $message = '' ) {
				$this->code = $code;
			}
			public function get_error_code() {
				return $this->code;
			}
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	/** `$wpdb` mínimo: el cerrojo compartido y la opción de muestras sobre las opciones en memoria, y la búsqueda de `_product_attributes`. */
	class Woo_Kit_Wpdb {
		public $options  = 'wp_options';
		public $postmeta = 'wp_postmeta';
		public $queries  = array();
		public function prepare( $query, ...$args ) {
			$args = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args;
			$i    = 0;
			return preg_replace_callback(
				'/%[sd]/',
				function ( $m ) use ( $args, &$i ) {
					$value = $args[ $i++ ] ?? '';
					return '%d' === $m[0] ? (string) (int) $value : "'" . str_replace( "'", "\\'", (string) $value ) . "'";
				},
				$query
			);
		}
		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
		private static function unquote( string $value ): string {
			return str_replace( "\\'", "'", $value );
		}
		public function query( $sql ) {
			$this->queries[] = $sql;
			$o               = &$GLOBALS['wp']['options'];
			if ( preg_match( "/^INSERT IGNORE INTO wp_options .* VALUES \( '(.*?)', '(.*?)', 'off' \)$/s", $sql, $m ) ) {
				if ( array_key_exists( $m[1], $o ) ) {
					return 0;
				}
				$o[ $m[1] ] = self::unquote( $m[2] );
				return 1;
			}
			if ( preg_match( "/^UPDATE wp_options SET option_value = '(.*?)' WHERE option_name = '(.*?)' AND option_value = '(.*?)'$/s", $sql, $m ) ) {
				if ( isset( $o[ $m[2] ] ) && $o[ $m[2] ] === self::unquote( $m[3] ) ) {
					$o[ $m[2] ] = self::unquote( $m[1] );
					return 1;
				}
				return 0;
			}
			if ( preg_match( "/^DELETE FROM wp_options WHERE option_name = '(.*?)' AND option_value LIKE '%\|(.*?)'$/s", $sql, $m ) ) {
				$token = stripslashes( $m[2] );
				if ( isset( $o[ $m[1] ] ) && substr( (string) $o[ $m[1] ], -strlen( $token ) ) === $token ) {
					unset( $o[ $m[1] ] );
					return 1;
				}
				return 0;
			}
			return 0;
		}
		public function get_var( $sql ) {
			$this->queries[] = $sql;
			if ( preg_match( "/^SELECT option_value FROM wp_options WHERE option_name = '(.*?)'/", $sql, $m ) ) {
				$value = $GLOBALS['wp']['options'][ $m[1] ] ?? null;
				return null === $value ? null : ( is_array( $value ) ? serialize( $value ) : (string) $value );
			}
			if ( preg_match( "/meta_key = '_product_attributes' AND pm\.meta_value LIKE '(.*?)' AND .* <> '(.*?)' \)/s", $sql, $m ) ) {
				$needle  = stripslashes( trim( $m[1], '%' ) );
				$session = $m[2];
				foreach ( $GLOBALS['wp']['meta'] as $id => $metas ) {
					foreach ( $metas['_product_attributes'] ?? array() as $value ) {
						$text = is_array( $value ) ? serialize( $value ) : (string) $value;
						if ( false !== strpos( $text, $needle ) && (string) ( $metas['_elementor_import_session_id'][0] ?? '' ) !== $session ) {
							return (string) $id;
						}
					}
				}
				return null;
			}
			return null;
		}
	}
	$GLOBALS['wpdb'] = new Woo_Kit_Wpdb();

	require_once __DIR__ . '/elements-kit/wp-stubs.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();
	require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			fwrite( STDERR, 'FALLO: ' . $message . PHP_EOL );
			exit( 1 );
		}
	};
}

namespace Woo_Kit_Test {
	use Digitalisimo\Elements\Import\Kits\Export_Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Revert_Context;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\Parts;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Report;
	use Digitalisimo\Elements\Woo\Kit\Module as Kit;
	use Digitalisimo\Elements\Woo\Kit\Attributes_Part;
	use Digitalisimo\Elements\Woo\Kit\Variations_Part;
	use Digitalisimo\Elements\Woo\Swatches\Data;

	$check = $GLOBALS['check'];
	$tmp   = sys_get_temp_dir() . '/digi-woo-kit-' . bin2hex( random_bytes( 6 ) );
	$E     = realpath( __DIR__ . '/../digitalisimo-elements' );

	$site = function () use ( $tmp ) {
		kit_stub_reset( $tmp );
		woo_kit_reset();
		References::reset();
		Parts::reset();
		Data::flush();
		$GLOBALS['wp']['options']['digitalisimo_elements_swatches'] = array();
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
	$import_ctx = function ( string $mode, array $maps = array(), int $max = 1000, array $stored = array(), string $session = 'sesWoo' ) {
		return new Import_Context(
			array(
				'session'     => $session,
				'mode'        => $mode,
				'maps'        => $maps,
				'max_post_id' => $max,
				'stored'      => $stored,
			)
		);
	};
	$run = function ( $part, string $method, array $data, Import_Context $context ) {
		$context->begin_part( $part->key() );
		$summary = $part->$method( $data, 1, $context );
		$context->end_part();
		return $summary;
	};

	require_once $E . '/modules/digitalisimo-woo/kit/class-module.php';
	require_once $E . '/modules/digitalisimo-woo/kit/class-attributes-part.php';
	require_once $E . '/modules/digitalisimo-woo/kit/class-variations-part.php';

	// ── 0. Sin WooCommerce ───────────────────────────────────────────────────────────────────────────────────────
	$site();
	$check( ! Attributes_Part::available() && ! Variations_Part::available(), '0: sin WooCommerce, las partes lo detectan.' );
	$export = new Export_Context( array( 'include' => array( 'content' ) ) );
	$check( null === ( new Attributes_Part() )->export( $export ) && null === ( new Variations_Part() )->export( $export ), '0: sin WooCommerce no se exporta nada.' );
	$context = $import_ctx( 'add' );
	$run( new Attributes_Part(), 'import_early', array( 'attributes' => array( 'color' => array( 'label' => 'Color' ) ) ), $context );
	$run( new Variations_Part(), 'import', array( 'variations' => array( '10' => array( array( 'id' => 1 ) ) ) ), $context );
	$inactive = $codes( $context, 'woocommerce.inactive' );
	$check( 2 === count( $inactive ) && 'woo-attributes' === $inactive[0]['data']['part'] && 'woo-variations' === $inactive[1]['data']['part'] && 'requested' === Report::section_for( 'woocommerce.inactive' ), '0: el informe dice woocommerce.inactive en «Lo que el kit pedía».' );
	$context = $import_ctx( 'add' );
	$run( new Attributes_Part(), 'import_early', array(), $context );
	$run( new Variations_Part(), 'import', array(), $context );
	$check( array() === $context->get_report()['entries'], '0: sin datos del kit, nada que decir.' );
	Kit::reset();
	Kit::init_inactive();
	$check( array( 'digitalisimo_elements_kit_parts', 'digitalisimo_elements_kit_report_labels' ) === array_keys( $GLOBALS['wp']['hooks'] ), '0: init_inactive() sólo engancha las partes y los textos del informe.' );
	Report::reset_labels();
	$check( 'El kit trae atributos o variaciones de WooCommerce y este sitio no tiene WooCommerce activo: no se importaron' === Report::code_label( 'woocommerce.inactive' ), '0: y el informe dice woocommerce.inactive con su texto, no con el de las páginas de WooCommerce.' );

	// ── Dobles de WooCommerce 11.2.1 ─────────────────────────────────────────────────────────────────────────────
	if ( ! function_exists( 'wc_create_attribute' ) ) {
		require __DIR__ . '/elements-woo-kit/woocommerce-stubs.php';
	}

	// ── 1. Contrato y registro ───────────────────────────────────────────────────────────────────────────────────
	$site();
	$attributes = new Attributes_Part();
	$variations = new Variations_Part();
	$check( $attributes instanceof \Digitalisimo\Elements\Import\Kits\Early_Part && ! $variations instanceof \Digitalisimo\Elements\Import\Kits\Early_Part && $variations instanceof \Digitalisimo\Elements\Import\Kits\Part, '1: woo-attributes es Early_Part; woo-variations, Part.' );
	$check( 'woo-attributes' === $attributes->key() && 'woo-variations' === $variations->key() && 1 === $attributes->version() && 1 === $variations->version() && array() === $attributes->kit_meta_keys() && array() === $variations->kit_meta_keys(), '1: claves, versión 1 y sin metas del kit.' );
	$part_src = file_get_contents( $E . '/modules/digitalisimo-woo/kit/class-attributes-part.php' );
	$check( 1 === preg_match( '/^use Digitalisimo\\\\Elements\\\\Import\\\\Kits\\\\Early_Part;$/m', $part_src ) && 1 === preg_match( '/final class Attributes_Part implements Early_Part\b/', $part_src ) && 1 === preg_match( "/const KEY\s*=\s*'woo-attributes';/", $part_src ), '1: `use …\\Kits\\Early_Part;`, `implements Early_Part` y `const KEY` (lo que lee el inventario).' );
	$check( Attributes_Part::KEY === $attributes->key() && Variations_Part::KEY === $variations->key(), '1: const KEY coincide con key().' );
	Kit::reset();
	Kit::init();
	$hooks = $GLOBALS['wp']['hooks'];
	$check( isset( $hooks['digitalisimo_elements_kit_parts'][10], $hooks['digitalisimo_elements_kit_references'][10], $hooks['digitalisimo_elements_kit_not_exported'][10], $hooks['digitalisimo_elements_kit_report_labels'][10], $hooks['digitalisimo_elements_kit_needs'][10], $hooks[ Kit::EXPORT_ACTION ][30] ), '1: init() engancha partes, referencias, el contador, los textos, needs y el runner de medios (export-kit, 30).' );
	$base = array( 'woocommerce' => false, 'acf' => array(), 'google_places' => false );
	$check( true === \Digitalisimo\Elements\Import\Kits\Runners\Export_Finish::extend_needs( $base, array( 'digitalisimo-woo-wishlist-button' => 1 ), new Export_Context() )['woocommerce'] && $base === \Digitalisimo\Elements\Import\Kits\Runners\Export_Finish::extend_needs( $base, array( 'heading' => 1, 'digitalisimo-heading' => 1 ), new Export_Context() ), '1: un kit con un widget digitalisimo-woo-* necesita WooCommerce; uno sin ellos, no cambia.' );
	Kit::init();
	$check( 1 === count( $GLOBALS['wp']['hooks']['digitalisimo_elements_kit_parts'][10] ), '1: init() es idempotente.' );
	Parts::reset();
	$keys = Parts::keys();
	$check( array_slice( $keys, 0, 7 ) === array_keys( Parts::BUILTIN ) && array( 'woo-attributes', 'woo-variations' ) === array_slice( $keys, 7 ), '1: las partes van detrás de las de K: ' . implode( ', ', $keys ) );
	$check( array( 'woo-attributes' ) === array_keys( Parts::early() ), '1: sólo woo-attributes corre en el runner plugins.' );
	$check( 1 === Parts::versions()['woo-attributes'] && 1 === Parts::versions()['woo-variations'] && array() === $GLOBALS['wp']['wrong'], '1: registradas sin avisos.' );

	// ── 2. Referencias ───────────────────────────────────────────────────────────────────────────────────────────
	$by_path = function ( string $type ) {
		$out = array();
		foreach ( References::matching( 'element', $type ) as $ref ) {
			if ( empty( $ref['derived'] ) ) {
				$out[ $ref['path'] ] = $ref;
			}
		}
		return $out;
	};
	$button = $by_path( 'digitalisimo-woo-wishlist-button' );
	$check( isset( $button['product_id'], $button['wishlist_page'] ) && 'post' === $button['product_id']['kind'] && References::NEVER_ID === $button['product_id']['never'] && null === $button['wishlist_page']['never'], '2: botón de lista de deseos: producto con el valor que nunca coincide y página de la lista.' );
	$quick = $by_path( 'digitalisimo-woo-quick-view' );
	$check( References::NEVER_ID === $quick['product_id']['never'] && 'template' === $quick['digitalisimo_template_id']['kind'], '2: vista rápida: producto (never) y plantilla.' );
	foreach ( array( 'digitalisimo-woo-price-filter', 'digitalisimo-woo-attribute-filter' ) as $type ) {
		$refs = $by_path( $type );
		$check( isset( $refs['selected_element'] ) && 'element' === $refs['selected_element']['kind'], "2: {$type}: bucle del mismo documento." );
	}
	$all_gates = array();
	foreach ( Kit::references_list() as $ref ) {
		$norm = References::normalize( $ref );
		$check( null !== $norm, '2: referencia válida: ' . json_encode( $ref ) );
		$all_gates[] = $norm['gate'];
	}
	$check( array( false ) === array_values( array_unique( $all_gates ) ), '2: ninguna referencia de F4-Woo decide quién ve algo (gate falso).' );
	$check( array() === References::matching( 'term_meta', 'image' ), '2: `image` no se aplica fuera de un término…' );
	$in_pa  = References::within_taxonomy( 'pa_color', function () { return References::matching( 'term_meta', 'image' ); } );
	$in_cat = References::within_taxonomy( 'product_cat', function () { return References::matching( 'term_meta', 'image' ); } );
	$check( 1 === count( $in_pa ) && 'attachment' === $in_pa[0]['kind'] && array( 'pa_*' ) === $in_pa[0]['taxonomies'] && array() === $in_cat, '2: …y sólo en los términos de atributos `pa_*`.' );
	$check( ! isset( References::all()['term_meta']['digitalisimo_swatch_image'] ), '2: sin la referencia retirada de la v1 (digitalisimo_swatch_image).' );

	// ── 3. woo-attributes · exportar ─────────────────────────────────────────────────────────────────────────────
	$origin = function () use ( $site ) {
		$site();
		Kit::reset();
		Kit::init();
		woo_attribute( 'color', 'Color', 'select', 'menu_order', 1 );
		woo_attribute( 'talla', 'Talla', 'select', 'name', 0 );
		woo_attribute( 'vacio', 'Vacío', 'select', 'menu_order', 0 );
		kit_stub_post( array( 'ID' => 10, 'post_type' => 'product' ) );
		kit_stub_term( array( 'term_id' => 40, 'taxonomy' => 'pa_color', 'slug' => 'rojo' ) );
		kit_stub_term( array( 'term_id' => 41, 'taxonomy' => 'pa_color', 'slug' => 'azul' ) );
		kit_stub_term( array( 'term_id' => 42, 'taxonomy' => 'pa_talla', 'slug' => 'm' ) );
		$GLOBALS['wp']['options']['digitalisimo_elements_swatches'] = array( 'v' => 1, 'attributes' => array( 'color' => array( 'type' => 'visual', 'shape' => 'circle', 'size' => 32 ) ) );
		Data::flush();
	};
	$origin();
	$export = new Export_Context( array( 'include' => array( 'content' ) ) );
	$export->add_exported( 'post', array( 10 ) );
	$out = $attributes->export( $export );
	$check( 1 === $out['v'] && array( 'color', 'talla' ) === array_keys( $out['attributes'] ), '3: todos los atributos cuya taxonomía exporta el núcleo (con términos), por slug: ' . json_encode( array_keys( $out['attributes'] ?? array() ) ) );
	$color = $out['attributes']['color'];
	$check( 'Color' === $color['label'] && 'select' === $color['type'] && 'menu_order' === $color['orderby'] && true === $color['has_archives'] && array( 'type' => 'visual', 'shape' => 'circle', 'size' => 32 ) === $color['swatches'], '3: etiqueta, tipo, orden, archivos públicos y entrada de muestras (Swatches\\Data::entry()).' );
	$check( ! isset( $out['attributes']['talla']['swatches'] ) && 'name' === $out['attributes']['talla']['orderby'], '3: un desplegable no lleva muestras.' );
	$check( 1 === preg_match( '/^[0-9a-f]{40}$/', $color['hash'] ) && $color['hash'] === Attributes_Part::fingerprint( 'color', $color ) && false === strpos( json_encode( $out ), '"id"' ), '3: con huella y sin IDs del origen.' );
	$check( null === $attributes->export( new Export_Context( array( 'include' => array( 'content' ) ) ) ) && null === $attributes->export( new Export_Context( array( 'include' => array( 'templates' ) ) ) ), '3: sin productos exportados (o sin contenido), nada.' );
	$kit_attributes = $out;

	// ── 4. woo-attributes · import_early ─────────────────────────────────────────────────────────────────────────
	$destination = function () use ( $site ) {
		$site();
		Kit::reset();
		Kit::init();
		woo_attribute( 'talla', 'Tamaño', 'select', 'menu_order', 0 );
	};
	$destination();
	$kit_attributes['attributes']['zapato']  = array( 'label' => 'Talla de zapato', 'type' => 'wc-visual', 'orderby' => 'id', 'has_archives' => false, 'swatches' => array( 'type' => 'label', 'shape' => 'pill', 'size' => 40 ) );
	$kit_attributes['attributes']['Mal Slug'] = array( 'label' => 'x' );
	$kit_attributes['attributes']['pa_doble'] = array( 'label' => 'x' );
	$context = $import_ctx( 'add' );
	$summary = $run( $attributes, 'import_early', $kit_attributes, $context );
	$check( array( 'created' => 2, 'reused' => 1, 'failed' => 0 ) === $summary, '4: crea los que faltan y reutiliza el que existe: ' . json_encode( $summary ) );
	$created = $GLOBALS['wc']['created'];
	$check( 2 === count( $created ) && 'color' === $created[0]['slug'] && false === $created[0]['has_archives'] && false === $created[1]['has_archives'], '4: en «agregar», siempre con has_archives = 0.' );
	$off = $codes( $context, 'woocommerce.attribute-archives-off' );
	$check( 1 === count( $off ) && array( 'color' ) === $off[0]['data']['attributes'] && 'requested' === Report::section_for( 'woocommerce.attribute-archives-off' ), '4: y se informa woocommerce.attribute-archives-off con los atributos que tenían archivos.' );
	$check( isset( $GLOBALS['wc']['registered']['pa_color'], $GLOBALS['wc']['registered']['pa_zapato'] ) && taxonomy_exists( 'pa_color' ) && array( 'product' ) === $GLOBALS['wc']['registered']['pa_color']['objects'], '4: register_taxonomy() en la misma petición, para el runner de taxonomías.' );
	$args = $GLOBALS['wc']['registered']['pa_color']['args'];
	$check( false === $args['public'] && false === $args['query_var'] && false === $args['rewrite'] && 'manage_product_terms' === $args['capabilities']['manage_terms'], '4: registrada sin URL públicas y con los permisos de WooCommerce.' );
	$check( isset( $GLOBALS['wc_product_attributes']['pa_color'], $GLOBALS['wc_product_attributes']['pa_talla'] ), '4: la global $wc_product_attributes queda al día.' );
	$check( 'select' === wc_get_attribute( wc_attribute_taxonomy_id_by_name( 'zapato' ) )->type, '4: un tipo que el destino no ofrece (wc-visual apagado) vuelve a select, como WooCommerce.' );
	$check( 'Tamaño' === wc_get_attribute( wc_attribute_taxonomy_id_by_name( 'talla' ) )->name, '4: el atributo que ya existía no cambia…' );
	$differs = $codes( $context, 'woocommerce.attribute-differs' );
	$check( 1 === count( $differs ) && 'talla' === $differs[0]['data']['attribute'], '4: …y se informa que difiere.' );
	$check( 1 === count( $codes( $context, 'woocommerce.attribute-invalid' ) ) && array( 'Mal Slug', 'pa_doble' ) === $codes( $context, 'woocommerce.attribute-invalid' )[0]['data']['attributes'], '4: un slug inválido o con pa_ se descarta con aviso.' );
	$stored = $context->stored( 'woo-attributes' );
	$check( array( 'color', 'zapato' ) === array_keys( $stored['attributes'] ) && $stored['attributes']['color']['hash'] === Attributes_Part::print_of( wc_get_attribute( $stored['attributes']['color']['id'] ) ), '4: guarda los creados con la huella del atributo del sitio (R8).' );
	$again = $run( $attributes, 'import_early', $kit_attributes, $import_ctx( 'add', array(), 1000, array( 'woo-attributes' => $stored ) ) );
	$check( 2 === count( $GLOBALS['wc']['created'] ) && 0 === $again['created'], '4: un reintento de la misma sesión no crea nada más.' );

	$site();
	$context = $import_ctx( 'fresh' );
	$run( $attributes, 'import_early', array( 'attributes' => array( 'color' => $out['attributes']['color'] ) ), $context );
	$check( true === $GLOBALS['wc']['created'][0]['has_archives'] && true === $GLOBALS['wc']['registered']['pa_color']['args']['public'] && array() === $codes( $context, 'woocommerce.attribute-archives-off' ), '4: en «nuevo», has_archives tal cual.' );

	$site();
	register_taxonomy( 'pa_ajena', array( 'product' ), array() );
	$GLOBALS['wc']['fail_create'] = 'zapato';
	$context = $import_ctx( 'add' );
	$summary = $run( $attributes, 'import_early', array( 'attributes' => array( 'ajena' => array( 'label' => 'Ajena' ), 'zapato' => array( 'label' => 'Zapato' ) ) ), $context );
	$failed  = $codes( $context, 'woocommerce.attribute-failed' );
	$check( 2 === $summary['failed'] && 2 === count( $failed ) && 'taxonomy' === $failed[0]['data']['reason'] && 'cannot_create_attribute' === $failed[1]['data']['reason'] && array() === $context->stored( 'woo-attributes' )['attributes'], '4: una taxonomía ajena con ese nombre o un error de WooCommerce: no se crea y se informa.' );

	// ── 5. woo-attributes · import (muestras) ────────────────────────────────────────────────────────────────────
	$destination();
	$GLOBALS['wp']['options']['digitalisimo_elements_swatches'] = array( 'v' => 1, 'attributes' => array( 'talla' => array( 'type' => 'label', 'shape' => 'rounded', 'size' => 24 ), 'zapato' => array( 'type' => 'visual', 'shape' => 'circle', 'size' => 50 ) ) );
	Data::flush();
	$kit_attributes['attributes']['talla']['swatches'] = array( 'type' => 'visual', 'shape' => 'circle', 'size' => 32 );
	$context = $import_ctx( 'add' );
	$run( $attributes, 'import_early', $kit_attributes, $context );
	$stored  = $context->stored_all();
	$context = $import_ctx( 'add', array(), 1000, $stored );
	$summary = $run( $attributes, 'import', $kit_attributes, $context );
	Data::flush();
	$check( array( 'type' => 'visual', 'shape' => 'circle', 'size' => 32 ) === Data::entry( 'color' ), '5: las muestras del atributo creado se escriben (Swatches\\Data::put(), bajo su cerrojo).' );
	$check( array( 'type' => 'label', 'shape' => 'rounded', 'size' => 24 ) === Data::entry( 'talla' ), '5: las de un atributo que ya existía no se aplican…' );
	$check( array( 'type' => 'visual', 'shape' => 'circle', 'size' => 50 ) === Data::entry( 'zapato' ), '5: …ni se pisa una entrada que el sitio ya tenía, aunque el atributo sea nuevo («agregar»).' );
	$skipped = array_column( array_column( $codes( $context, 'woocommerce.swatches-skipped' ), 'data' ), 'attribute' );
	$check( array( 'talla', 'zapato' ) === $skipped && 1 === $summary['swatches'] && 2 === $summary['skipped'], '5: y se informa woocommerce.swatches-skipped: ' . json_encode( $summary ) );
	$check( ! isset( $GLOBALS['wp']['options']['digitalisimo_elements_lock_swatches'] ), '5: el cerrojo se suelta.' );
	$stored = $context->stored_all();
	$check( isset( $stored['woo-attributes']['swatches']['color'] ) && $stored['woo-attributes']['swatches']['color'] === Part_Context::hash( Data::entry( 'color' ) ), '5: guarda la huella de las muestras escritas.' );
	$before = $GLOBALS['wp']['options']['digitalisimo_elements_swatches'];
	$run( $attributes, 'import', $kit_attributes, $import_ctx( 'add', array(), 1000, $stored ) );
	$check( $before === $GLOBALS['wp']['options']['digitalisimo_elements_swatches'], '5: reimportar no cambia la opción.' );
	$attr_stored = $stored['woo-attributes'];

	// ── 6. woo-attributes · revert ───────────────────────────────────────────────────────────────────────────────
	$revert = function ( array $stored, string $session = 'sesWoo' ) use ( $attributes ) {
		$context = new Revert_Context( array( 'session' => $session, 'mode' => 'add' ) );
		$context->begin_part( 'woo-attributes' );
		$attributes->revert( $stored, $context );
		$context->end_part();
		Data::flush();
		return $context;
	};
	$color_id  = (int) $attr_stored['attributes']['color']['id'];
	$zapato_id = (int) $attr_stored['attributes']['zapato']['id'];
	kit_stub_term( array( 'term_id' => 540, 'taxonomy' => 'pa_color', 'slug' => 'rojo' ) );
	add_term_meta( 540, '_elementor_import_session_id', 'sesWoo' );
	$context = $revert( $attr_stored );
	$check( null === wc_get_attribute( $color_id ) && null === Data::entry( 'color' ), '6: un atributo creado, sin cambios y sólo con términos de la sesión se borra, con sus muestras.' );
	$check( null === wc_get_attribute( $zapato_id ) && array( 'type' => 'visual', 'shape' => 'circle', 'size' => 50 ) === Data::entry( 'zapato' ), '6: la entrada que ya tenía el sitio no se toca (sólo se quita lo que la sesión escribió).' );
	$check( null !== wc_get_attribute( wc_attribute_taxonomy_id_by_name( 'talla' ) ), '6: el reutilizado nunca se borra.' );

	// R8: etiqueta cambiada después de importar.
	$destination();
	$context = $import_ctx( 'add' );
	$run( $attributes, 'import_early', $kit_attributes, $context );
	$stored = $context->stored_all()['woo-attributes'];
	$GLOBALS['wc']['attributes'][ $stored['attributes']['color']['id'] ]->attribute_label = 'Color editado';
	$context = $revert( $stored );
	$check( null !== wc_get_attribute( $stored['attributes']['color']['id'] ) && 1 === count( $codes( $context, 'woocommerce.attribute-edited' ) ) && null === wc_get_attribute( $stored['attributes']['zapato']['id'] ), '6: R8 · el atributo editado después se conserva y se informa; el que no, se borra.' );

	// Muestras editadas, término ajeno, objeto ajeno y producto ajeno que lo nombra.
	$scenario = function ( callable $tamper, ?array $kit = null ) use ( $destination, $import_ctx, $run, $attributes, $kit_attributes, $revert ) {
		$kit = null === $kit ? $kit_attributes : $kit;
		$destination();
		$GLOBALS['wp']['options']['digitalisimo_elements_swatches'] = array();
		Data::flush();
		$context = $import_ctx( 'add' );
		$run( $attributes, 'import_early', $kit, $context );
		$context = $import_ctx( 'add', array(), 1000, $context->stored_all() );
		$run( $attributes, 'import', $kit, $context );
		$stored = $context->stored_all()['woo-attributes'];
		$tamper( $stored );
		return array( $stored, $revert( $stored ) );
	};
	$alive  = function ( array $stored, string $slug ) {
		return null !== wc_get_attribute( (int) $stored['attributes'][ $slug ]['id'] );
	};
	$edited = function ( Revert_Context $context, string $code = 'woocommerce.attribute-edited' ) use ( $codes ) {
		return array_column( array_column( $codes( $context, $code ), 'data' ), 'attribute' );
	};
	list( $stored, $context ) = $scenario(
		function () {
		}
	);
	$check( ! $alive( $stored, 'color' ) && ! $alive( $stored, 'zapato' ) && null === Data::entry( 'color' ) && null === Data::entry( 'zapato' ), '6: sin cambios, se borran los dos con las muestras que escribió la sesión.' );
	list( $stored, $context ) = $scenario(
		function () {
			Data::put( 'color', array( 'type' => 'label', 'shape' => 'pill', 'size' => 30 ) );
		}
	);
	$check( $alive( $stored, 'color' ) && ! $alive( $stored, 'zapato' ) && array( 'color' ) === $edited( $context ), '6: con sus muestras cambiadas después, el atributo se conserva.' );
	$no_swatches = $kit_attributes;
	unset( $no_swatches['attributes']['zapato']['swatches'] );
	list( $stored, $context ) = $scenario(
		function () {
			Data::put( 'zapato', array( 'type' => 'visual', 'shape' => 'circle', 'size' => 30 ) );
		},
		$no_swatches
	);
	$check( $alive( $stored, 'zapato' ) && array( 'zapato' ) === $edited( $context ), '6: muestras añadidas después a un atributo creado sin ellas: también se conserva.' );
	list( $stored, $context ) = $scenario(
		function () {
			kit_stub_term( array( 'term_id' => 600, 'taxonomy' => 'pa_color', 'slug' => 'propio' ) );
		}
	);
	$check( $alive( $stored, 'color' ) && array( 'color' ) === $edited( $context, 'woocommerce.attribute-in-use' ), '6: un término que no es de la sesión: el atributo se conserva («no pisar»).' );
	list( $stored, $context ) = $scenario(
		function () {
			kit_stub_term( array( 'term_id' => 601, 'taxonomy' => 'pa_color', 'slug' => 'rojo' ) );
			add_term_meta( 601, '_elementor_import_session_id', 'sesWoo' );
			kit_stub_post( array( 'ID' => 77, 'post_type' => 'product' ) );
			$GLOBALS['wc']['relationships'][601] = array( 77 );
		}
	);
	$check( $alive( $stored, 'color' ) && array( 'color' ) === $edited( $context, 'woocommerce.attribute-in-use' ), '6: un producto ajeno con un término de la sesión: se conserva.' );
	list( $stored, $context ) = $scenario(
		function () {
			kit_stub_post( array( 'ID' => 78, 'post_type' => 'product' ) );
			update_post_meta( 78, '_product_attributes', array( 'pa_color' => array( 'name' => 'pa_color', 'is_taxonomy' => 1 ) ) );
		}
	);
	$check( $alive( $stored, 'color' ) && array( 'color' ) === $edited( $context, 'woocommerce.attribute-in-use' ), '6: un producto ajeno que nombra el atributo en _product_attributes: se conserva.' );
	list( $stored, $context ) = $scenario(
		function () {
			kit_stub_post( array( 'ID' => 79, 'post_type' => 'product' ) );
			update_post_meta( 79, '_product_attributes', array( 'pa_color' => array( 'name' => 'pa_color', 'is_taxonomy' => 1 ) ) );
			update_post_meta( 79, '_elementor_import_session_id', 'sesWoo' );
		}
	);
	$check( ! $alive( $stored, 'color' ), '6: si el producto que lo nombra es de la sesión, se borra.' );
	list( $stored, $context ) = $scenario(
		function () {
			$GLOBALS['wpdb'] = null;
		}
	);
	$GLOBALS['wpdb'] = new \Woo_Kit_Wpdb();
	$check( $alive( $stored, 'color' ) && $alive( $stored, 'zapato' ), '6: si no se puede comprobar el uso, no se borra (falla cerrado).' );

	// ── 7. woo-variations · exportar ─────────────────────────────────────────────────────────────────────────────
	$site();
	Kit::reset();
	Kit::init();
	woo_attribute( 'color', 'Color', 'select', 'menu_order', 0 );
	kit_stub_term( array( 'term_id' => 40, 'taxonomy' => 'pa_color', 'slug' => 'rojo' ) );
	kit_stub_term( array( 'term_id' => 45, 'taxonomy' => 'product_shipping_class', 'slug' => 'pesado' ) );
	woo_product( 10, 'variable' );
	woo_product( 11, 'simple' );
	woo_product( 12, 'variable' );
	woo_variation( 101, 10, array( 'menu_order' => 2, 'attributes' => array( 'pa_color' => 'rojo', 'talla' => 'M' ), 'regular_price' => '20', 'sale_price' => '15', 'date_on_sale_from' => 1790000000, 'sku' => 'CAM-R', 'manage_stock' => true, 'stock_quantity' => 5, 'weight' => '0.3', 'shipping_class_id' => 45, 'image_id' => 207, 'description' => 'Roja' ), array( array( 'mi_meta', 'valor' ), array( '_price', '15' ), array( '_edit_lock', '1:1' ), array( 'attribute_otra', 'x' ), array( 'objeto', 'O:8:"stdClass":0:{}' ) ) );
	woo_variation( 102, 10, array( 'menu_order' => 1, 'status' => 'private', 'attributes' => array( 'pa_color' => '', 'talla' => 'L' ), 'downloadable' => true, 'downloads' => array( 'a' => 1 ), 'image_id' => 208 ) );
	woo_variation( 103, 10, array( 'status' => 'draft' ) );
	woo_variation( 111, 11, array() );
	woo_variation( 121, 12, array( 'image_id' => 209 ) );
	$export = new Export_Context( array( 'include' => array( 'content' ) ) );
	$export->add_exported( 'post', array( 10, 11, 50 ) );
	$export->not_exported( 'variations', 5 );
	$export->not_exported( 'drafts', 1 );
	$check( array( 207, 208 ) === Variations_Part::image_ids( $export ), '7: imágenes de las variaciones exportadas, para media.' );
	$out = $variations->export( $export );
	$check( array( '10' ) === array_map( 'strval', array_keys( $out['variations'] ) ) && array( 102, 101 ) === array_column( $out['variations'][10], 'id' ), '7: sólo variaciones publish y private de los variables exportados, por menu_order: ' . json_encode( array_keys( $out['variations'] ?? array() ) ) );
	$row = $out['variations'][10][1];
	$check( array( 'pa_color' => 'rojo', 'talla' => 'M' ) === $row['attributes'] && '20' === $row['regular_price'] && '15' === $row['sale_price'] && 1790000000 === $row['sale_from'] && null === $row['sale_to'] && 'CAM-R' === $row['sku'] && true === $row['manage_stock'] && 5.0 === $row['stock'] && 'pesado' === $row['shipping_class'] && 207 === $row['image'] && 'Roja' === $row['description'] && 'publish' === $row['status'] && 2 === $row['menu_order'], '7: los datos de WooCommerce de la variación: ' . json_encode( $row ) );
	$check( array( array( 'mi_meta', 'valor' ) ) === $row['meta'], '7: sus metas propias viajan; las internas, de atributos, de la lista negra y con objetos, no.' );
	$check( 'private' === $out['variations'][10][0]['status'] && '' === $out['variations'][10][0]['attributes']['pa_color'], '7: una variación privada y un atributo «cualquiera» del origen viajan tal cual.' );
	$check( array( 'variations' => 3, 'drafts' => 1, 'downloads' => 1 ) === $export->get_not_exported(), '7: variations baja a las que no viajan (V.11.4) y los archivos descargables se anotan como no exportados: ' . json_encode( $export->get_not_exported() ) );
	$other = new Export_Context( array( 'include' => array( 'content' ) ) );
	$other->not_exported( 'variations', 5 );
	$check( array( 'variations' => 5 ) === $other->get_not_exported(), '7: con otro contexto (en el que la parte no exportó), el contador no baja.' );
	$check( null === $variations->export( new Export_Context( array( 'include' => array( 'content' ) ) ) ), '7: sin productos exportados, nada.' );

	// ── 8. woo-variations · importar ─────────────────────────────────────────────────────────────────────────────
	$kit_variations = $out;
	$kit_variations['variations']['12'] = array( array( 'id' => 121 ) );
	$kit_variations['variations']['13'] = array( array( 'id' => 131 ) );
	$kit_variations['variations']['14'] = array( array( 'id' => 141, 'attributes' => array( 'pa_color' => 'verde' ) ) );
	$import_site = function () use ( $site ) {
		$site();
		Kit::reset();
		Kit::init();
		woo_attribute( 'color', 'Color', 'select', 'menu_order', 0 );
		kit_stub_term( array( 'term_id' => 540, 'taxonomy' => 'pa_color', 'slug' => 'rojo' ) );
		kit_stub_term( array( 'term_id' => 545, 'taxonomy' => 'product_shipping_class', 'slug' => 'pesado' ) );
		woo_product( 500, 'simple', array( '_sku' => 'CAM-R' ) );
		$GLOBALS['wc']['skus']['CAM-R'] = 500;
		kit_stub_post( array( 'ID' => 907, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		woo_product( 1010, 'variable' );
		woo_product( 1011, 'simple' );
		woo_product( 1014, 'variable' );
		woo_product( 1015, 'simple' );
		kit_stub_post( array( 'ID' => 1016, 'post_type' => 'page' ) );
	};
	$import_site();
	$maps    = array(
		'post'       => array( 10 => 1010, 11 => 1011, 12 => 500, 14 => 1014, 15 => 1015, 16 => 1016 ),
		'attachment' => array( 207 => 907 ),
	);
	$context = $import_ctx( 'add', $maps );
	$summary = $run( $variations, 'import', $kit_variations, $context );
	$made    = array_values(
		array_filter(
			$GLOBALS['wp']['posts'],
			function ( $post ) {
				return 'product_variation' === $post->post_type;
			}
		)
	);
	$check( 2 === count( $made ) && 2 === $summary['created'] && array( 1010 ) === array_values( array_unique( array_map( function ( $p ) { return (int) $p->post_parent; }, $made ) ) ), '8: crea las variaciones bajo el padre que creó la sesión: ' . json_encode( $summary ) );
	$red = null;
	foreach ( $made as $post ) {
		if ( 'rojo' === ( $GLOBALS['wc']['variations'][ $post->ID ]['attributes']['pa_color'] ?? '' ) ) {
			$red = $GLOBALS['wc']['variations'][ $post->ID ];
		}
	}
	$check( null !== $red && '' === $red['sku'] && 1 === $summary['sku'] && 'CAM-R' === $codes( $context, 'woocommerce.sku-conflict' )[0]['data']['sku'], '8: un SKU que ya usa otro producto del sitio: la variación entra sin SKU y se informa.' );
	$check( 907 === $red['image_id'] && 545 === $red['shipping_class_id'] && '20' === $red['regular_price'] && 1790000000 === $red['date_on_sale_from'] && array( array( 'mi_meta', 'valor' ) ) === $red['meta'] && 'publish' === $red['status'], '8: imagen traducida con el mapa de media, clase de envío por slug, precios, fechas y metas.' );
	$missing = $codes( $context, 'reference.attachment' );
	$check( 1 === count( $missing ) && 208 === $missing[0]['data']['attachment'] && 'unresolved' === Report::section_for( 'reference.attachment' ), '8: una imagen sin destino queda sin imagen y se informa en «Referencias sin destino».' );
	$check( 1 === count( $codes( $context, 'woocommerce.variations-kept' ) ) && 500 === $codes( $context, 'woocommerce.variations-kept' )[0]['data']['product'], '8: un padre reutilizado (ya era del sitio) no se toca.' );
	$check( 1 === count( $codes( $context, 'woocommerce.variations-orphan' ) ) && 13 === $codes( $context, 'woocommerce.variations-orphan' )[0]['data']['parent'], '8: un padre que no llegó: nada.' );
	$check( 1 === count( $codes( $context, 'woocommerce.variation-attribute' ) ) && ! array_filter( $made, function ( $p ) { return 1014 === (int) $p->post_parent; } ), '8: un término que el destino no tiene descarta la variación (nunca «cualquiera»).' );
	$check( in_array( 1010, $GLOBALS['wc']['synced'], true ) && in_array( 1010, $GLOBALS['wc']['transients'], true ) && ! in_array( 500, $GLOBALS['wc']['synced'], true ), '8: sync() y transitorios del padre creado.' );
	sort( $GLOBALS['wc']['lookup'] );
	$check( array( 1010, 1011, 1014, 1015 ) === $GLOBALS['wc']['lookup'] && 4 === $summary['lookup'], '8: la tabla de búsqueda de cada producto que creó la sesión, también los simples (V.11.3): ' . json_encode( $GLOBALS['wc']['lookup'] ) );
	$check( 4 === $codes( $context, 'woocommerce.lookup-refreshed' )[0]['data']['products'], '8: y se informa woocommerce.lookup-refreshed.' );
	$stored = $context->stored( 'woo-variations' );
	$check( array( '1010', '1014' ) === array_map( 'strval', array_keys( $stored['created'] ) ) && 2 === count( $stored['created'][1010] ) && array() === $stored['created'][1014], '8: guarda las variaciones creadas con su huella, por padre procesado: ' . json_encode( array_keys( $stored['created'] ) ) );
	$count = count( $GLOBALS['wp']['posts'] );
	$run( $variations, 'import', $kit_variations, $import_ctx( 'add', $maps, 1000, array( 'woo-variations' => $stored ) ) );
	$check( $count === count( $GLOBALS['wp']['posts'] ), '8: un reintento de la misma sesión no duplica las variaciones.' );

	$import_site();
	$GLOBALS['wc']['direct'] = false;
	$context = $import_ctx( 'add', array( 'post' => array( 11 => 1011 ) ) );
	$run( $variations, 'import', array(), $context );
	$check( array( 1011 ) === $GLOBALS['wc']['saved'] && array() === $GLOBALS['wc']['lookup'], '8: sin refresh_product_lookup_table() (antes de 10.8), save() del producto; también con un kit sin variaciones.' );
	$import_site();
	$run( $variations, 'import', array(), $import_ctx( 'add', array( 'post' => array( 11 => 1011 ) ), 2000 ) );
	$check( array() === $GLOBALS['wc']['lookup'], '8: un producto que no creó la sesión no se toca.' );

	// ── 9. woo-variations · revertir ─────────────────────────────────────────────────────────────────────────────
	$import_site();
	$context = $import_ctx( 'add', $maps );
	$run( $variations, 'import', $kit_variations, $context );
	$stored = $context->stored( 'woo-variations' );
	$ids    = array_keys( $stored['created'][1010] );
	$GLOBALS['wc']['variations'][ $ids[1] ]['regular_price'] = '99';
	$GLOBALS['wc']['synced'] = array();
	$revert_ctx = new Revert_Context( array( 'session' => 'sesWoo', 'mode' => 'add' ) );
	$revert_ctx->begin_part( 'woo-variations' );
	$variations->revert( $stored, $revert_ctx );
	$revert_ctx->end_part();
	$check( null === get_post( $ids[0] ) && null !== get_post( $ids[1] ), '9: la variación sin cambios se borra; la editada después se conserva…' );
	$check( 1 === count( $codes( $revert_ctx, 'woocommerce.variation-edited' ) ) && in_array( 1010, $GLOBALS['wc']['synced'], true ), '9: …se informa y el padre se sincroniza.' );
	$revert_ctx = new Revert_Context( array( 'session' => 'sesWoo', 'mode' => 'add' ) );
	$variations->revert( array( 'created' => array( 1010 => array( 9999 => array( 'hash' => 'x', 'status' => 'publish' ) ) ) ), $revert_ctx );
	$check( array() === $revert_ctx->get_report()['entries'], '9: una variación que ya no existe (WooCommerce la borró con su padre) no deja aviso.' );

	// En la papelera con su padre: cuenta como sin cambios.
	$import_site();
	$context = $import_ctx( 'add', $maps );
	$run( $variations, 'import', $kit_variations, $context );
	$stored = $context->stored( 'woo-variations' );
	foreach ( array_keys( $stored['created'][1010] ) as $id ) {
		$GLOBALS['wc']['variations'][ $id ]['status'] = 'trash';
	}
	$revert_ctx = new Revert_Context( array( 'session' => 'sesWoo', 'mode' => 'add' ) );
	$variations->revert( $stored, $revert_ctx );
	foreach ( array_keys( $stored['created'][1010] ) as $id ) {
		$check( null === get_post( (int) $id ), '9: una variación en la papelera con su padre, sin otros cambios, se borra.' );
	}

	// ── 10. Runner de medios ─────────────────────────────────────────────────────────────────────────────────────
	$site();
	woo_product( 10, 'variable' );
	woo_variation( 101, 10, array( 'image_id' => 207 ) );
	$process = new class() {
		public $runners = array();
		public function register( $runner ) {
			$this->runners[ $runner::get_name() ] = $runner;
		}
	};
	Kit::export_runner( $process );
	$check( array() === $process->runners, '10: sin el contexto de exportación de K, no se registra nada.' );
	$export   = new Export_Context( array( 'include' => array( 'content' ) ) );
	$property = new \ReflectionProperty( \Digitalisimo\Elements\Import\Kits\Context::class, 'export' );
	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true );
	}
	$property->setValue( null, $export );
	Kit::export_runner( $process );
	$check( array( 'digitalisimo-woo-media' ) === array_keys( $process->runners ), '10: con el contexto activo, el runner digitalisimo-woo-media.' );
	$runner = $process->runners['digitalisimo-woo-media'];
	$export->add_exported( 'post', array( 10 ) );
	$result = $runner->export( array() );
	$check( array( 'files' => array(), 'manifest' => array() ) === $result && array( 207 ) === $export->exported( 'attachment' ), '10: anota las imágenes como adjuntos exportados, sin archivos ni manifiesto propios.' );
	$check( $runner->should_export( array() ), '10: corre con contenido y WooCommerce.' );
	$property->setValue( null, null );

	// ── 11. Informe, PHP 7.4 y llamadas prohibidas ───────────────────────────────────────────────────────────────
	$sources = '';
	foreach ( glob( $E . '/modules/digitalisimo-woo/kit/*.php' ) as $file ) {
		$sources .= file_get_contents( $file );
	}
	preg_match_all( "/->report\(\s*'([a-z0-9_:.-]+)'/", $sources, $found );
	$check( count( $found[1] ) >= 15, '11: se encontraron los códigos del informe (' . count( $found[1] ) . ').' );
	$check( 'Archivos descargables de variaciones' === Report::not_exported_label( 'downloads' ), '11: el motivo `downloads` tiene texto.' );
	$check( array() === array_intersect_key( Kit::LABELS, Report::CODE_LABELS ), '11: ningún texto de F4-Woo intenta pisar uno de K.' );
	Kit::reset();
	Kit::init();
	Report::reset_labels();
	$labels = Report::labels();
	foreach ( array_unique( $found[1] ) as $code ) {
		$check( isset( Kit::LABELS[ $code ], $labels[ $code ] ) && Kit::LABELS[ $code ] === $labels[ $code ], "11: el código {$code} tiene su propio texto en el informe." );
		$section = Report::section_for( $code );
		$check( 0 === strpos( $code, 'woocommerce.' ) ? 'requested' === $section : ( 'reference.attachment' === $code && 'unresolved' === $section ), "11: el código {$code} tiene la sección que dice V.11.5 ({$section})." );
	}
	foreach ( token_get_all( $sources ) as $token ) {
		if ( is_array( $token ) && in_array( token_name( $token[0] ), array( 'T_MATCH', 'T_NULLSAFE_OBJECT_OPERATOR' ), true ) ) {
			$check( false, '11: kit/ es PHP 7.4 (sin ' . token_name( $token[0] ) . ').' );
		}
	}
	$check( false === strpos( $sources, 'str_contains(' ) && false === strpos( $sources, 'str_starts_with(' ) && false === strpos( $sources, 'Automattic\\WooCommerce\\Internal' ), '11: sin funciones de PHP 8 ni clases internas de WooCommerce.' );
	$check( false === strpos( $sources, 'woocommerce_attribute_taxonomies' ) && false === strpos( $sources, 'update_option(' ) && false === strpos( $sources, '->insert(' ) && false === strpos( $sources, '->update(' ) && false === strpos( $sources, 'switch_theme' ) && false === strpos( $sources, 'activate_plugin' ) && false === strpos( $sources, "'type'         => 'wc-visual'" ), '11: sólo escribe atributos con wc_create_attribute() y muestras con Swatches\\Data; nunca la tabla de atributos, opciones, el tema ni plugins.' );

	echo "DIGITALÍSIMO Elements: kits de WooCommerce validados (woo-attributes temprana con has_archives cerrado en «agregar», muestras sólo en lo creado, woo-variations, tabla de búsqueda de lo importado, referencias, contador que sólo baja, medios y reversión sin pisar).\n";
}
