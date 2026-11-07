<?php
/**
 * Kits con WooCommerce (ronda de fidelidad, grupo kit-woo-refs; docs/pro/c7.md y docs/fusion/k-kits.md §18): lo que un
 * kit perdía o dejaba sin traducir en la ida y vuelta R1 con WooCommerce.
 *
 *  1. Registro: la clave `export` de las referencias (sólo en un elemento concreto con un ID, booleana; sin ella, la
 *     forma de siempre), `References::exported()` y `References::kit_settings_keys()` (las seis páginas de WooCommerce de
 *     los ajustes del kit, integradas en K). Las declaraciones de `Woo\Kit\Module`: el producto del «Añadir al carrito
 *     personalizado» y la selección manual de los Productos, propios y de PRO, con `export` y el valor que nunca coincide.
 *  2. Exportar: `Export\Documents::restore_ids()` repone `product_id` y `query_posts_ids` que quitan `on_export()` y
 *     `export => false`, sólo para las referencias con `export` (los webhooks de un formulario siguen fuera), sin pisar
 *     lo que la exportación dejó; sin las declaraciones, el árbol es el de siempre.
 *  3. Importar: el remapeo traduce el producto y la selección una sola vez (la referencia que K deriva del control de
 *     consulta, `query_posts_ids`, no repite la declarada `query_posts_ids.*`); sin destino, el valor que nunca coincide
 *     (vacío sería el producto de la página o todos los productos) y `unresolved` en el informe.
 *  4. `site-settings` en «nuevo»: las páginas de WooCommerce del kit no se guardan en la primera petición (su `on_save()`
 *     las copiaba sin traducir a las opciones) y quedan apartadas en la sesión; en «agregar», ni se aplican ni quedan
 *     para «Aplicar los ajustes del kit» y se informa `settings.reference_kept`.
 *  5. Parte `kit-settings` en la petición final de «nuevo»: guarda las páginas traducidas con el guardado del kit, y el
 *     `on_save()` real de la pestaña WooCommerce las copia a las opciones; sin destino, vacías y `unresolved`. En
 *     «agregar» no hace nada.
 *  6. Runner `woocommerce-settings` de K en «agregar»: informa las páginas que llegaron por el WXR (antes sólo las del
 *     constructor) sin cambiar ninguna opción.
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $kits_manager;
		public $documents;
		public $app;
		public $experiments;
		public $widgets_manager;
		public $elements_manager;
	}
}

namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';

	if ( ! function_exists( 'current_action' ) ) {
		function current_action() {
			return '';
		}
	}

	/** Kit activo en memoria: `save()` guarda los ajustes y llama al `on_save()` real de la pestaña WooCommerce. */
	class Kwr_Kit {
		public $id;
		public $saves = array();
		public function __construct( $id ) { $this->id = $id; }
		public function get_id() { return $this->id; }
		public function get_main_id() { return $this->id; }
		public function get_meta( $key ) { return get_post_meta( $this->id, $key, true ); }
		public function get_settings() { $s = $this->get_meta( '_elementor_page_settings' ); return is_array( $s ) ? $s : array(); }
		public function save( array $data ) {
			$this->saves[] = $data;
			if ( isset( $data['settings'] ) ) {
				$settings = $data['settings'];
				unset( $settings['post_status'] );
				update_post_meta( $this->id, '_elementor_page_settings', $settings );
				$tab = ( new ReflectionClass( '\ElementorPro\Modules\Woocommerce\Settings\Settings_Woocommerce' ) )->newInstanceWithoutConstructor();
				$tab->on_save( $data );
			}
			return true;
		}
	}
	class Kwr_Kits_Manager {
		public $kit;
		public function __construct( $kit ) { $this->kit = $kit; }
		public function get_active_kit() { return $this->kit; }
	}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-woo/kit/class-module.php';

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Remapper;
	use Digitalisimo\Elements\Import\Kits\Session;
	use Digitalisimo\Elements\Import\Kits\Export\Documents;
	use Digitalisimo\Elements\Import\Kits\Parts\Kit_Settings;
	use Digitalisimo\Elements\Import\Kits\Runners\Import_Hooks;
	use Digitalisimo\Elements\Import\Kits\Runners\Site_Settings;
	use Digitalisimo\Elements\Import\Kits\Runners\Woocommerce_Report;
	use Digitalisimo\Elements\Woo\Kit\Module as Woo_Kit;

	$E     = __DIR__ . '/../digitalisimo-elements';
	$never = References::NEVER_ID;
	$woo   = function () {
		References::reset();
		add_filter( Module::FILTER_REFERENCES, array( Woo_Kit::class, 'references' ) );
	};
	$codes = function ( array $entries, string $code ) {
		return array_values(
			array_filter(
				$entries,
				function ( $entry ) use ( $code ) {
					return $code === $entry['code'];
				}
			)
		);
	};
	$wrongs = function ( callable $fn ) {
		$before = count( $GLOBALS['kt']['wrong'] );
		$fn();
		return array_slice( $GLOBALS['kt']['wrong'], $before );
	};

	// ── 1. Registro ─────────────────────────────────────────────────────────────────────────────────────────────────
	kt_reset();
	References::reset();
	$plain = References::normalize( array( 'element', 'digitalisimo-woo-add-to-cart', 'product_id', 'post', false, $never ) );
	kt_check( null !== $plain && ! array_key_exists( 'export', $plain ), '1: sin `export`, la referencia tiene la forma de siempre.' );
	$off = References::normalize( array( 'element', 'digitalisimo-woo-add-to-cart', 'product_id', 'post', 'export' => false ) );
	kt_check( null !== $off && ! array_key_exists( 'export', $off ), '1: `export` falso no añade la clave.' );
	$on = References::normalize( array( 'element', 'digitalisimo-woo-add-to-cart', 'product_id', 'post', false, $never, 'export' => true ) );
	kt_check( null !== $on && true === $on['export'] && $never === $on['never'] && false === $on['gate'], '1: posicional más `export` por nombre: ' . json_encode( $on ) );
	$named = References::normalize( array( 'scope' => 'element', 'type' => 'x-widget', 'path' => 'ids.*', 'kind' => 'term', 'export' => true ) );
	kt_check( null !== $named && true === $named['export'], '1: con claves, `export` también vale.' );
	foreach (
		array(
			'en cualquier elemento'  => array( 'element', '*', 'product_id', 'post', 'export' => true ),
			'en un documento'        => array( 'document', 'kit', 'woocommerce_cart_page_id', 'post', 'export' => true ),
			'con un usuario'         => array( 'element', 'x-widget', 'user_id', 'user', 'export' => true ),
			'con una URL'            => array( 'element', 'x-widget', 'link.url', 'url', 'export' => true ),
			'con un ID de elemento'  => array( 'element', 'x-widget', 'selected_element', 'element', 'export' => true ),
			'dentro de un repetidor' => array( 'element', 'x-widget', 'items.*.product_id', 'post', 'export' => true ),
			'en un subcampo'         => array( 'element', 'x-widget', 'product.id', 'post', 'export' => true ),
			'que no es un booleano'  => array( 'element', 'x-widget', 'product_id', 'post', 'export' => 'yes' ),
		) as $why => $bad
	) {
		$warned = $wrongs( function () use ( $bad, &$result ) { $result = References::normalize( $bad ); } );
		kt_check( null === $result && 1 === count( $warned ), "1: `export` {$why} se rechaza con _doing_it_wrong." );
	}
	kt_check( array() === References::exported( 'digitalisimo-woo-add-to-cart' ), '1: sin la declaración de F4-Woo, nada se repone.' );
	$pages = array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'woocommerce_purchase_summary_page_id' );
	kt_check( $pages === References::kit_settings_keys(), '1: las seis páginas de WooCommerce de los ajustes del kit son referencias integradas de K: ' . implode( ', ', References::kit_settings_keys() ) );
	foreach ( References::matching( 'document', 'kit' ) as $ref ) {
		kt_check( in_array( $ref['path'], array_merge( $pages, array( 'custom_css' ) ), true ) && ( 'custom_css' === $ref['path'] || ( 'post' === $ref['kind'] && false === $ref['gate'] && null === $ref['never'] ) ), '1: página del kit: entrada, sin gate ni valor de cierre (vacía, WooCommerce no tiene esa página): ' . json_encode( $ref ) );
	}
	$woo();
	$warned = $wrongs( function () { References::all(); } );
	kt_check( array() === $warned, '1: las declaraciones de F4-Woo son válidas: ' . implode( ' | ', $warned ) );
	foreach ( Woo_Kit::ADD_TO_CART as $type ) {
		kt_check( array( 'product_id' ) === References::exported( $type ), "1: {$type}: el producto viaja aunque su on_export() lo quite." );
		$ref = References::matching( 'element', $type )[0];
		kt_check( 'product_id' === $ref['path'] && 'post' === $ref['kind'] && $never === $ref['never'] && false === $ref['gate'], "1: {$type}: producto con el valor que nunca coincide." );
	}
	foreach ( Woo_Kit::PRODUCTS as $type ) {
		kt_check( array( 'query_posts_ids' ) === References::exported( $type ), "1: {$type}: la selección manual viaja aunque el grupo de consulta la marque export => false." );
	}
	// Productos clásicos (el grupo antiguo `posts`: `export => false` y `on_export()` quitan la selección y los términos).
	foreach ( Woo_Kit::CLASSIC_PRODUCTS as $type ) {
		kt_check( array( 'posts_posts_ids', 'posts_product_cat_ids', 'posts_product_tag_ids', 'posts_product_brand_ids' ) === References::exported( $type ), "1: {$type}: la selección manual y los términos de la consulta antigua viajan; los autores no: " . implode( ', ', References::exported( $type ) ) );
		foreach ( References::matching( 'element', $type ) as $ref ) {
			if ( in_array( $ref['path'], array( 'posts_posts_ids.*', 'posts_product_cat_ids.*' ), true ) ) {
				kt_check( $never === $ref['never'] && false === $ref['gate'], "1: {$type}: {$ref['path']} con el valor que nunca coincide (vacío serían todos los productos)." );
			}
		}
	}
	kt_check( array( 'digitalisimo-woo-add-to-cart', 'wc-add-to-cart' ) === Woo_Kit::ADD_TO_CART && array( 'digitalisimo-woo-products', 'woocommerce-products' ) === Woo_Kit::PRODUCTS, '1: el widget propio y el de PRO que K convierte al importar.' );
	kt_check( array() === References::exported( 'form' ) && array() === References::exported( '*' ) && array() === References::exported( 'heading' ), '1: nada más se repone.' );

	// ── 2. Exportar ─────────────────────────────────────────────────────────────────────────────────────────────────
	$saved = array(
		array(
			'id'       => 'c000001',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array( 'id' => 'w000001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-add-to-cart', 'settings' => array( 'product_id' => '11', 'text' => 'Comprar del kit' ), 'elements' => array() ),
				array( 'id' => 'w000002', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-products', 'settings' => array( 'query_post_type' => 'by_id', 'query_posts_ids' => array( '11', '12' ) ), 'elements' => array() ),
				array( 'id' => 'w000003', 'elType' => 'widget', 'widgetType' => 'form', 'settings' => array( 'webhooks' => 'https://privado.test/hook' ), 'elements' => array() ),
				array( 'id' => 'w000004', 'elType' => 'widget', 'widgetType' => 'wc-add-to-cart', 'settings' => array( 'product_id' => '12' ), 'elements' => array() ),
				array( 'id' => 'w000005', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => array( 'query_posts_ids' => array( '12' ) ), 'elements' => array() ),
				array( 'id' => 'w000006', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-add-to-cart', 'settings' => array( 'product_id' => '11' ), 'elements' => array() ),
			),
		),
	);
	// Lo que da el núcleo: ids nuevos y los ajustes que quitan `on_export()` y `export => false`.
	$exported = $saved;
	$exported[0]['id'] = 'rnd0001';
	foreach ( $exported[0]['elements'] as $i => $node ) {
		$exported[0]['elements'][ $i ]['id'] = 'rnd1' . $i;
		unset( $exported[0]['elements'][ $i ]['settings']['product_id'], $exported[0]['elements'][ $i ]['settings']['query_posts_ids'], $exported[0]['elements'][ $i ]['settings']['webhooks'] );
	}
	$exported[0]['elements'][5]['settings']['product_id'] = '77'; // Lo que la exportación sí dejó no se pisa.
	kt_reset();
	References::reset();
	$before = Documents::restore_ids( $exported, $saved );
	kt_check( ! isset( $before[0]['elements'][0]['settings']['product_id'] ) && ! isset( $before[0]['elements'][1]['settings']['query_posts_ids'] ) && 'c000001' === $before[0]['id'], '2: sin las declaraciones, la exportación es la de siempre (ids repuestos, nada más).' );
	$woo();
	$after = Documents::restore_ids( $exported, $saved );
	$el    = $after[0]['elements'];
	kt_check( '11' === ( $el[0]['settings']['product_id'] ?? null ) && 'Comprar del kit' === $el[0]['settings']['text'] && 'w000001' === $el[0]['id'], '2: el producto del «Añadir al carrito personalizado» viaja en el kit.' );
	kt_check( array( '11', '12' ) === ( $el[1]['settings']['query_posts_ids'] ?? null ), '2: la selección manual de los Productos viaja en el kit.' );
	kt_check( ! isset( $el[2]['settings']['webhooks'] ), '2: lo que otro elemento quita al exportar sin referencia `export` sigue fuera (webhooks).' );
	kt_check( '12' === ( $el[3]['settings']['product_id'] ?? null ) && array( '12' ) === ( $el[4]['settings']['query_posts_ids'] ?? null ), '2: también en los widgets de PRO, que K convierte al importar.' );
	kt_check( '77' === $el[5]['settings']['product_id'], '2: lo que la exportación dejó no se pisa.' );
	$classic_saved = array(
		array( 'id' => 'p000001', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-products-classic', 'settings' => array( 'posts_post_type' => 'by_id', 'posts_posts_ids' => array( '11' ), 'posts_product_cat_ids' => array( '31' ), 'posts_authors' => array( '1' ), '_skin' => 'classic' ), 'elements' => array() ),
		array( 'id' => 'p000002', 'elType' => 'widget', 'widgetType' => 'wc-products', 'settings' => array( 'posts_post_type' => 'product', 'posts_product_tag_ids' => array( '32' ) ), 'elements' => array() ),
	);
	$classic_exported = $classic_saved;
	foreach ( $classic_exported as $i => $node ) {
		$classic_exported[ $i ]['id'] = 'rnd2' . $i;
		unset( $classic_exported[ $i ]['settings']['posts_posts_ids'], $classic_exported[ $i ]['settings']['posts_product_cat_ids'], $classic_exported[ $i ]['settings']['posts_product_tag_ids'], $classic_exported[ $i ]['settings']['posts_authors'] );
	}
	$classic = Documents::restore_ids( $classic_exported, $classic_saved );
	kt_check( array( '11' ) === ( $classic[0]['settings']['posts_posts_ids'] ?? null ) && array( '31' ) === ( $classic[0]['settings']['posts_product_cat_ids'] ?? null ) && ! isset( $classic[0]['settings']['posts_authors'] ) && array( '32' ) === ( $classic[1]['settings']['posts_product_tag_ids'] ?? null ), '2: Productos clásicos (propio y de PRO): la selección y los términos viajan; los autores no: ' . json_encode( $classic ) );
	$src = (string) file_get_contents( $E . '/modules/digitalisimo-woo/single/class-add-to-cart.php' );
	kt_check( false !== strpos( $src, "unset( \$element['settings']['product_id'] );" ), '2: fuera de un kit (una plantilla suelta de la biblioteca), como PRO: on_export() quita el producto.' );

	// ── 3. Importar ─────────────────────────────────────────────────────────────────────────────────────────────────
	kt_reset();
	$woo();
	// Los widgets registrados, con sus controles de consulta: K también deriva de ellos una referencia `post`
	// (`query_posts_ids` sin `.*`), que no debe repetir la declarada (`query_posts_ids.*`).
	\Elementor\Plugin::$instance                  = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = new class() {
		public function get_widget_types( $type ) {
			return new class() {
				public function get_controls() {
					return array(
						'product_id'      => array( 'name' => 'product_id', 'type' => 'query', 'autocomplete' => array( 'object' => 'post' ) ),
						'query_posts_ids' => array( 'name' => 'query_posts_ids', 'type' => 'query', 'autocomplete' => array( 'object' => 'post' ) ),
					);
				}
			};
		}
	};
	$paths = array_column( References::matching( 'element', 'digitalisimo-woo-products' ), 'path' );
	kt_check( in_array( 'query_posts_ids.*', $paths, true ) && ! in_array( 'query_posts_ids', $paths, true ), '3: la derivada `query_posts_ids` no repite la lista declarada `query_posts_ids.*`: ' . implode( ', ', $paths ) );
	$context = new Import_Context( array( 'session' => 'kwr0000000001', 'mode' => 'fresh', 'maps' => array( 'post' => array( 11 => 111, 12 => 112 ) ) ) );
	$tree    = array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-add-to-cart', 'settings' => array( 'product_id' => '11' ), 'elements' => array() ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-add-to-cart', 'settings' => array( 'product_id' => '99' ), 'elements' => array() ),
		array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-products', 'settings' => array( 'query_posts_ids' => array( '11', '12' ) ), 'elements' => array() ),
		array( 'id' => 'w4', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-products', 'settings' => array( 'query_posts_ids' => array( '12', '98' ) ), 'elements' => array() ),
		array( 'id' => 'w5', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-products', 'settings' => array( 'query_posts_ids' => array( '97', '98' ) ), 'elements' => array() ),
		array( 'id' => 'w6', 'elType' => 'widget', 'widgetType' => 'wc-add-to-cart', 'settings' => array( 'product_id' => '12' ), 'elements' => array() ),
		array( 'id' => 'w7', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => array( 'query_posts_ids' => array( '96' ) ), 'elements' => array() ),
	);
	$out = ( new Remapper( $context ) )->apply_elements( $tree, 0 );
	kt_check( '111' === $out[0]['settings']['product_id'], '3: el producto del kit pasa al producto importado.' );
	kt_check( $never === $out[1]['settings']['product_id'], '3: sin destino, el valor que nunca coincide (vacío sería el producto de la página): ' . json_encode( $out[1]['settings'] ) );
	kt_check( array( '111', '112' ) === $out[2]['settings']['query_posts_ids'], '3: la selección manual pasa a los productos importados.' );
	kt_check( array( '112' ) === $out[3]['settings']['query_posts_ids'], '3: lo que no llegó se quita de la selección.' );
	kt_check( array( $never ) === $out[4]['settings']['query_posts_ids'], '3: sin ninguno, el valor que nunca coincide (vacío serían todos los productos).' );
	kt_check( '112' === $out[5]['settings']['product_id'] && array( $never ) === $out[6]['settings']['query_posts_ids'], '3: igual en los widgets de PRO.' );
	$unresolved = $codes( $context->get_report()['entries'], 'unresolved' );
	kt_check( 4 === count( $unresolved ) && 'product_id' === $unresolved[0]['data']['path'] && array( '99' ) === $unresolved[0]['data']['values'], '3: cada referencia sin destino queda en el informe (`unresolved`): ' . json_encode( $unresolved ) );
	// Productos clásicos: una sola traducción (la derivada no repite la lista declarada) y, sin destino, el valor que
	// nunca coincide en la selección y en los términos (una categoría vacía mostraría todos los productos).
	$classic_context = new Import_Context( array( 'session' => 'kwr0000000003', 'mode' => 'fresh', 'maps' => array( 'post' => array( 11 => 111 ), 'term' => array( 31 => 131 ) ) ) );
	$classic_tree    = array(
		array( 'id' => 'c1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-woo-products-classic', 'settings' => array( 'posts_posts_ids' => array( '11' ), 'posts_product_cat_ids' => array( '31', '39' ) ), 'elements' => array() ),
		array( 'id' => 'c2', 'elType' => 'widget', 'widgetType' => 'wc-products', 'settings' => array( 'posts_posts_ids' => array( '98' ), 'posts_product_tag_ids' => array( '38' ) ), 'elements' => array() ),
	);
	$classic_out = ( new Remapper( $classic_context ) )->apply_elements( $classic_tree, 0 );
	kt_check( array( '111' ) === $classic_out[0]['settings']['posts_posts_ids'] && array( '131' ) === $classic_out[0]['settings']['posts_product_cat_ids'], '3: Productos clásicos: la selección y la categoría pasan a las importadas (lo que no llegó se quita): ' . json_encode( $classic_out[0]['settings'] ) );
	kt_check( array( $never ) === $classic_out[1]['settings']['posts_posts_ids'] && array( $never ) === $classic_out[1]['settings']['posts_product_tag_ids'], '3: Productos clásicos sin destino: el valor que nunca coincide, nunca todos los productos: ' . json_encode( $classic_out[1]['settings'] ) );

	// ── 4. `site-settings` ──────────────────────────────────────────────────────────────────────────────────────────
	$session = 'kwr0000000002';
	$site    = function ( array $before = array() ) use ( $session ) {
		Context::deactivate();
		Session::forget( $session );
		kt_reset();
		References::reset();
		$kit = new Kwr_Kit( 5 );
		wp_insert_post( array( 'import_id' => 5, 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_name' => 'default-kit' ) );
		update_post_meta( 5, '_elementor_template_type', 'kit' );
		update_post_meta( 5, '_elementor_page_settings', array_replace( array( 'custom_colors' => array(), 'custom_css' => '/* sitio */' ), $before ) );
		update_option( 'elementor_active_kit', 5 );
		foreach ( array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'elementor_woocommerce_purchase_summary_page_id' ) as $option ) {
			update_option( $option, '' );
		}
		\Elementor\Plugin::$instance               = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->kits_manager = new Kwr_Kits_Manager( $kit );
		return $kit;
	};
	$bind = function ( $mode ) use ( $session ) {
		Session::bind( $session, array( 'mode' => $mode, 'requested' => $mode, 'origin' => array( 'kit' => sha1( 'kit-tienda' ), 'home' => 'http://origen.test', 'site' => 'http://origen.test', 'uploads' => 'http://origen.test/wp-content/uploads' ) ) );
		Session::update( $session, array( 'backup' => Import_Hooks::backup( 5 ), 'kit_id' => 5 ) );
	};
	// Ajustes del kit del origen: las páginas de la tienda con sus IDs de A (6 a 9), la de términos vacía y la del resumen
	// de compra sin elegir (como los guarda la pestaña WooCommerce).
	$kit_settings = array(
		'custom_colors'                        => array( array( '_id' => 'c9d8e7f', 'title' => 'Nuevo', 'color' => '#EEEEEE' ) ),
		'custom_css'                           => '/* kit */',
		'woocommerce_shop_page_id'             => '6',
		'woocommerce_cart_page_id'             => '7',
		'woocommerce_checkout_page_id'         => '8',
		'woocommerce_myaccount_page_id'        => '9',
		'woocommerce_terms_page_id'            => '',
		'woocommerce_purchase_summary_page_id' => false,
	);
	$run = function ( array $settings ) use ( $session ) {
		return ( new Site_Settings() )->import(
			array(
				'session_id'    => $session,
				'include'       => array( 'settings' ),
				'site_settings' => array( 'settings' => $settings ),
				'customization' => array(),
				'manifest'      => array(),
			),
			array()
		);
	};
	$options = function () {
		$out = array();
		foreach ( array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'elementor_woocommerce_purchase_summary_page_id' ) as $option ) {
			$out[ $option ] = get_option( $option );
		}
		return $out;
	};
	$empty = array_fill_keys( array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id', 'elementor_woocommerce_purchase_summary_page_id' ), '' );

	// La pestaña real copia al sitio los IDs que recibe (la prueba usa su on_save()).
	$kit = $site();
	$kit->save( array( 'settings' => array( 'woocommerce_cart_page_id' => '41', 'post_status' => 'publish' ) ) );
	kt_check( '41' === get_option( 'woocommerce_cart_page_id' ), '4: el on_save() real de la pestaña WooCommerce copia la página del kit a la opción.' );

	// «Nuevo»: nada de A llega a las opciones en la primera petición.
	$kit = $site( array( 'woocommerce_cart_page_id' => '' ) );
	$bind( Module::MODE_FRESH );
	$result = $run( $kit_settings );
	kt_check( $empty === $options(), '4: «nuevo»: la primera petición no copia los IDs del origen a las opciones de WooCommerce: ' . json_encode( $options() ) );
	$saved_kit = $kit->get_settings();
	kt_check( '/* kit */' === $saved_kit['custom_css'] && '' === $saved_kit['woocommerce_cart_page_id'] && ! isset( $saved_kit['woocommerce_shop_page_id'] ), '4: «nuevo»: el resto de ajustes se aplica; las páginas quedan como estaban: ' . json_encode( $saved_kit ) );
	$record = Session::get( $session );
	kt_check( array_intersect_key( $kit_settings, array_flip( $pages ) ) === $record['kit_deferred'], '4: «nuevo»: las páginas quedan apartadas en la sesión para la petición final: ' . json_encode( $record['kit_deferred'] ?? null ) );
	kt_check( Module::MODE_FRESH === $result['site-settings'][ Module::RUNNER ], '4: el runner sigue en «nuevo».' );

	// «Agregar»: ni se aplican ni quedan para «Aplicar los ajustes del kit».
	$kit = $site( array( 'woocommerce_cart_page_id' => '3' ) ); // Como en un sitio real: la opción y el kit, sincronizados.
	update_option( 'woocommerce_cart_page_id', '3' );
	$bind( Module::MODE_ADD );
	$run( $kit_settings );
	kt_check( '3' === get_option( 'woocommerce_cart_page_id' ) && '' === get_option( 'woocommerce_shop_page_id' ), '4: «agregar»: las páginas del sitio no cambian.' );
	$record = Session::get( $session );
	kt_check( array() === array_intersect_key( $record['backup']['kit_settings'], array_flip( $pages ) ) && isset( $record['backup']['kit_settings']['custom_css'] ) && array() === $record['kit_deferred'], '4: «agregar»: las páginas no quedan para «Aplicar los ajustes del kit» (el resto sí).' );
	$kept = $codes( Context::notes()['report']['entries'] ?? array(), 'settings.reference_kept' );
	kt_check( 1 === count( $kept ) && array( 'woocommerce_shop_page_id', 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id' ) === $kept[0]['data']['keys'] && 'settings' === \Digitalisimo\Elements\Import\Kits\Report::section_for( 'settings.reference_kept' ), '4: «agregar»: se informa `settings.reference_kept` con las páginas que el kit elige (no las vacías ni `false`), en «Ajustes del kit»: ' . json_encode( $kept[0]['data']['keys'] ?? null ) );
	kt_check( 'Ajustes del kit' !== \Digitalisimo\Elements\Import\Kits\Report::code_label( 'settings.reference_kept' ), '4: con su propio texto.' );
	// «Aplicar los ajustes del kit», también con el respaldo de una sesión anterior que aún guardaba las páginas.
	$record['backup']['kit_settings'] += array_intersect_key( $kit_settings, array_flip( $pages ) );
	Session::update( $session, array( 'backup' => $record['backup'] ) );
	$applied = Site_Settings::apply( $session );
	Context::deactivate();
	kt_check( true === $applied && '/* kit */' === $kit->get_settings()['custom_css'], '4: «Aplicar los ajustes del kit» aplica el resto.' );
	kt_check( '3' === get_option( 'woocommerce_cart_page_id' ) && '' === get_option( 'woocommerce_shop_page_id' ) && '3' === $kit->get_settings()['woocommerce_cart_page_id'], '4: …pero nunca las páginas del kit: el sitio conserva las suyas.' );
	// Un kit de una tienda sin páginas elegidas (todas vacías): nada que conservar, ninguna entrada en el informe.
	$kit = $site();
	$bind( Module::MODE_ADD );
	$run( array_replace( $kit_settings, array_fill_keys( $pages, '' ), array( 'woocommerce_purchase_summary_page_id' => false ) ) );
	kt_check( array() === $codes( Context::notes()['report']['entries'] ?? array(), 'settings.reference_kept' ), '4: «agregar» con todas las páginas del kit vacías: no se informa `settings.reference_kept`.' );

	// ── 5. Parte `kit-settings` (petición final) ────────────────────────────────────────────────────────────────────
	$kit = $site( array( 'woocommerce_cart_page_id' => '' ) );
	$bind( Module::MODE_FRESH );
	$run( $kit_settings );
	foreach ( array( 106 => 'shop', 107 => 'cart', 108 => 'checkout' ) as $id => $slug ) {
		wp_insert_post( array( 'import_id' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug ) );
	}
	$final = new Import_Context( array( 'session' => $session, 'mode' => 'fresh', 'kit_id' => 5, 'maps' => array( 'post' => array( 6 => 106, 7 => 107, 8 => 108 ) ) ) );
	$summary = ( new Kit_Settings() )->import( array(), 1, $final );
	$saved_kit = $kit->get_settings();
	kt_check( 106 === (int) $saved_kit['woocommerce_shop_page_id'] && 107 === (int) $saved_kit['woocommerce_cart_page_id'] && 108 === (int) $saved_kit['woocommerce_checkout_page_id'], '5: las páginas del kit se guardan traducidas: ' . json_encode( $saved_kit ) );
	kt_check( '' === $saved_kit['woocommerce_myaccount_page_id'] && '' === $saved_kit['woocommerce_terms_page_id'] && false === $saved_kit['woocommerce_purchase_summary_page_id'], '5: sin destino, vacía (nunca el ID del origen); lo vacío, igual.' );
	$now = $options();
	kt_check( '106' === (string) $now['woocommerce_shop_page_id'] && '107' === (string) $now['woocommerce_cart_page_id'] && '108' === (string) $now['woocommerce_checkout_page_id'] && '' === $now['woocommerce_myaccount_page_id'] && '' === $now['elementor_woocommerce_purchase_summary_page_id'], '5: el on_save() de la pestaña WooCommerce copia al sitio las páginas del destino: ' . json_encode( $now ) );
	$miss = $codes( $final->get_report()['entries'], 'unresolved' );
	kt_check( 1 === count( $miss ) && 'woocommerce_myaccount_page_id' === $miss[0]['data']['path'] && array( '9' ) === $miss[0]['data']['values'] && 'kit' === $miss[0]['data']['type'], '5: la página que no llegó queda en el informe: ' . json_encode( $miss ) );
	kt_check( 6 === ( $summary['references'] ?? 0 ) && '/* kit */' === $saved_kit['custom_css'], '5: resumen de la parte y el resto de ajustes intactos: ' . json_encode( $summary ) );
	// «Agregar» y un kit que cambió: nada.
	$kit = $site();
	$bind( Module::MODE_ADD );
	$run( $kit_settings );
	$add = new Import_Context( array( 'session' => $session, 'mode' => 'add', 'kit_id' => 5, 'maps' => array( 'post' => array( 6 => 106, 7 => 107 ) ) ) );
	$summary = ( new Kit_Settings() )->import( array(), 1, $add );
	kt_check( $empty === $options() && ! isset( $summary['references'] ) && ! isset( $kit->get_settings()['woocommerce_shop_page_id'] ), '5: «agregar»: la parte no aplica páginas.' );
	$kit = $site();
	$bind( Module::MODE_FRESH );
	$run( $kit_settings );
	\Elementor\Plugin::$instance->kits_manager = new Kwr_Kits_Manager( new Kwr_Kit( 9 ) );
	$moved = new Import_Context( array( 'session' => $session, 'mode' => 'fresh', 'kit_id' => 5, 'maps' => array( 'post' => array( 6 => 106 ) ) ) );
	( new Kit_Settings() )->import( array(), 1, $moved );
	kt_check( $empty === $options() && 1 === count( $codes( $moved->get_report()['entries'], 'kit-settings.kit_changed' ) ), '5: si el kit activo cambió, no se aplica nada y se informa.' );

	// ── 6. `woocommerce-settings` en «agregar» ──────────────────────────────────────────────────────────────────────
	$kit = $site();
	$bind( Module::MODE_ADD );
	Context::deactivate();
	$imported = array(
		'content'    => array( 'page' => array( 'succeed' => array( 50 => 150 ) ) ),
		'wp-content' => array( 'page' => array( 'succeed' => array( 7 => 107, 6 => 106 ) ) ),
	);
	( new Woocommerce_Report() )->import(
		array(
			'session_id'    => $session,
			'include'       => array( 'settings' ),
			'site_settings' => array( 'settings' => array( 'woocommerce_cart_page_id' => '7', 'woocommerce_shop_page_id' => 6, 'woocommerce_checkout_page_id' => '50', 'woocommerce_terms_page_id' => '' ) ),
		),
		$imported
	);
	$reported = $codes( Context::notes()['report']['entries'] ?? array(), 'woocommerce.page' );
	$by_option = array();
	foreach ( $reported as $entry ) {
		$by_option[ $entry['data']['option'] ] = $entry['data']['page'];
	}
	kt_check( array( 'woocommerce_cart_page_id' => 107, 'woocommerce_checkout_page_id' => 150, 'woocommerce_shop_page_id' => 106 ) === $by_option, '6: «agregar» informa también las páginas que llegaron por el WXR: ' . json_encode( $by_option ) );
	kt_check( $empty === $options(), '6: y no cambia ninguna opción.' );

	Context::deactivate();
	kt_rmdir( $GLOBALS['kt']['root'] );
	echo "Kits con WooCommerce: el producto del «Añadir al carrito personalizado», la selección manual de los Productos y las páginas de la tienda viajan y se traducen (k-kits.md §18).\n";
}
