<?php
/**
 * F4-Woo · frente G: partes «Vista rápida», «Lista de deseos» y «Muestras de variación» del widget Productos
 * (`modules/digitalisimo-widgets/class-products-parts.php`, D24, V.12.1 y V.12.2), con el **núcleo real**
 * (`tests/elements-design/core.php`).
 *
 * 1. Controles: los mismos que en 4.3.0.164 (`tests/fixtures/products-parts-4.3.0.164.json`) salvo tres opciones más al
 *    final de «Partes», fuera del valor por defecto, y su declaración `assets`; ningún control nuevo ni de estilo.
 * 2. CSS del documento `===` con y sin las partes nuevas, y `===` al de un Productos sin `Products_Parts`.
 * 3. `assets`: una hoja y un script por parte, con «Elegir qué partes mostrar» y `contains`; con el núcleo real
 *    (`Conditions::check()` sobre `get_active_settings()`), sólo los de las partes elegidas.
 * 4. Sin las partes nuevas, `before_render()` deja los mismos ganchos y la misma salida que 4.3.0.164; con ellas, cada
 *    renderizador en su gancho y su prioridad, y todo se restaura en `after_render()`. Sin renderizador, nada.
 *
 * `php tests/elements-woo-products-parts.php --baseline <copia de class-products-parts.php>` imprime la referencia
 * (así se generó la de 4.3.0.164, desde `git show 487bce6:digitalisimo-elements/modules/digitalisimo-widgets/class-products-parts.php`).
 */
namespace {
	require __DIR__ . '/elements-design/core.php';
	$baseline = isset( $argv[1], $argv[2] ) && '--baseline' === $argv[1] ? (string) $argv[2] : '';
	// Doble del widget Productos de PRO (después del núcleo, del que hereda): su sección `section_content` y un control de
	// estilo con `selectors`.
	eval( 'namespace ElementorPro\\Modules\\Woocommerce\\Widgets; class Products extends \\Elementor\\Widget_Base {
		public function get_name() { return "woocommerce-products"; }
		public function get_title() { return "Productos"; }
		protected function register_controls() {
			$this->start_controls_section( "section_content", array( "label" => "Contenido" ) );
			$this->add_control( "columns", array( "type" => \\Elementor\\Controls_Manager::NUMBER, "default" => 4 ) );
			$this->end_controls_section();
			$this->start_controls_section( "section_style", array( "label" => "Estilo", "tab" => \\Elementor\\Controls_Manager::TAB_STYLE ) );
			$this->add_control( "column_gap", array( "type" => \\Elementor\\Controls_Manager::SLIDER, "default" => array( "size" => 20, "unit" => "px" ), "selectors" => array( "{{WRAPPER}} ul.products" => "gap: {{SIZE}}{{UNIT}};" ) ) );
			$this->end_controls_section();
		}
	}' );

	// Lo que usan las partes de siempre.
	function get_permalink( $post = 0 ) { return 'https://sitio.test/producto/'; }
	function has_excerpt( $post = 0 ) { return false; }
	function get_the_excerpt( $post = 0 ) { return ''; }
	function get_the_content( $more = null ) { return 'Uno dos tres cuatro cinco seis siete ocho nueve diez once doce'; }
	function strip_shortcodes( $text ) { return $text; }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function wp_trim_words( $text, $num = 55, $more = null ) { $w = preg_split( '/\s+/', trim( (string) $text ) ); return count( $w ) > $num ? implode( ' ', array_slice( $w, 0, $num ) ) . $more : implode( ' ', $w ); }
	function wc_get_product_category_list( $id, $sep = ', ' ) { return '<a href="#">Ropa</a>'; }
	$GLOBALS['dh']['posts'][10] = array( 'title' => 'Camiseta' );
	// Plantillas del bucle de WooCommerce: cada una pinta su nombre.
	const PARTS_WC_DEFAULTS = array(
		array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 ),
		array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 ),
		array( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 ),
		array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 ),
		array( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 ),
		array( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 ),
	);
	const PARTS_WC_HOOKS = array( 'woocommerce_before_shop_loop_item_title', 'woocommerce_shop_loop_item_title', 'woocommerce_after_shop_loop_item_title', 'woocommerce_after_shop_loop_item', 'woocommerce_shortcode_products_query' );
	foreach ( PARTS_WC_DEFAULTS as $hook ) {
		eval( 'function ' . $hook[1] . '() { echo "[' . $hook[1] . ']"; }' );
	}

	/** Carga `Products_Parts` desde `$file` (la de 4.3.0.164 para la referencia, o la actual) y lo inicia. */
	function parts_load( string $file ): void {
		require_once $file;
		\Digitalisimo\Elements\Products_Parts::init();
	}
	parts_load( '' !== $baseline ? $baseline : __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-products-parts.php' );

	/** Núcleo nuevo con el doble de Productos registrado. */
	function parts_boot(): void {
		design_boot();
		\Elementor\Plugin::$instance->widgets_manager->types['woocommerce-products'] = new \ElementorPro\Modules\Woocommerce\Widgets\Products( array(), null );
		foreach ( PARTS_WC_HOOKS as $hook ) {
			unset( $GLOBALS['dh']['hooks'][ $hook ] );
		}
		foreach ( PARTS_WC_DEFAULTS as $hook ) {
			add_action( $hook[0], $hook[1], $hook[2] );
		}
	}

	function parts_widget( array $settings ) {
		return design_element( array( 'id' => 'p000001', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => $settings ) );
	}

	/** Ganchos del bucle (`gancho|prioridad|nombre`, `closure` para las anónimas) y su salida. */
	function parts_snapshot(): array {
		$out = array();
		foreach ( PARTS_WC_HOOKS as $hook ) {
			$list = array();
			foreach ( $GLOBALS['dh']['hooks'][ $hook ] ?? array() as $priority => $entries ) {
				foreach ( $entries as $entry ) {
					$list[] = $priority . '|' . ( is_string( $entry[0] ) ? $entry[0] : 'closure' );
				}
			}
			sort( $list );
			$out[ $hook ]['hooks'] = $list;
			if ( 'woocommerce_shortcode_products_query' === $hook ) {
				$out[ $hook ]['output'] = json_encode( apply_filters( $hook, array( 'posts_per_page' => 8 ) ) );
				continue;
			}
			ob_start();
			do_action( $hook );
			$out[ $hook ]['output'] = (string) ob_get_clean();
		}
		return $out;
	}

	/** Casos sin las partes nuevas: los ganchos y la salida deben ser los de 4.3.0.164. */
	const PARTS_CASES = array(
		'sin elegir'            => array(),
		'partes por defecto'    => array( 'digitalisimo_wc_custom' => 'yes' ),
		'título h3 y extras'    => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'title', 'category', 'excerpt', 'readmore' ), 'digitalisimo_wc_title_tag' => 'h3', 'digitalisimo_wc_readmore_text' => 'Ver' ),
		'resumen y compra'      => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'summary', 'cart' ), 'digitalisimo_wc_excerpt_length' => 3 ),
		'ninguna parte'         => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array() ),
		'límite de productos'   => array( 'digitalisimo_products_limit' => 4 ),
		'tabla'                 => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_layout' => 'table', 'digitalisimo_wc_parts' => array( 'image', 'sku', 'price' ) ),
	);

	/**
	 * Controles propios (`digitalisimo_*`) del doble de Productos, como los guarda el gestor de controles: la pila
	 * completa del editor (`$front = false`) o la optimizada del front, sin `options` ni etiquetas (`true`).
	 */
	function parts_controls( bool $front ): array {
		design_frontend( $front );
		parts_boot();
		$controls = array();
		foreach ( parts_widget( array() )->get_controls() as $name => $control ) {
			if ( 0 === strpos( $name, 'digitalisimo_' ) ) {
				ksort( $control );
				$controls[ $name ] = $control;
			}
		}
		design_frontend( true );
		parts_boot();
		return $controls;
	}

	/** Lo que la referencia guarda: controles y, por caso, ganchos y salida durante y después del render. */
	function parts_reference(): array {
		$reference = array( 'controls' => parts_controls( false ), 'front' => parts_controls( true ), 'cases' => array() );
		foreach ( PARTS_CASES as $label => $settings ) {
			parts_boot();
			$widget = parts_widget( $settings );
			\Digitalisimo\Elements\Products_Parts::before_render( $widget );
			$during = parts_snapshot();
			\Digitalisimo\Elements\Products_Parts::after_render( $widget );
			$reference['cases'][ $label ] = array( 'during' => $during, 'after' => parts_snapshot() );
		}
		return $reference;
	}

	if ( '' !== $baseline ) {
		echo json_encode( parts_reference(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
		return;
	}

	use Digitalisimo\Elements\Products_Parts;

	$fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/products-parts-4.3.0.164.json' ), true );
	check_design( is_array( $fixture ) && isset( $fixture['controls']['digitalisimo_wc_parts'], $fixture['cases']['sin elegir'] ), 'Referencia de 4.3.0.164 legible.' );
	$current = json_decode( json_encode( parts_reference() ), true );

	// 1. Controles: los de 4.3.0.164, salvo tres opciones más y `assets`.
	check_design( array_keys( $fixture['controls'] ) === array_keys( $current['controls'] ), 'Los mismos controles propios, en el mismo orden (' . count( $current['controls'] ) . ').' );
	$parts = $current['controls'][ Products_Parts::PARTS_KEY ];
	$old   = $fixture['controls'][ Products_Parts::PARTS_KEY ];
	check_design( array_merge( $old['options'], array( 'quickview' => 'Vista rápida', 'wishlist' => 'Lista de deseos', 'swatches' => 'Muestras de variación' ) ) === $parts['options'], 'Tres opciones nuevas al final de «Partes».' );
	check_design( $old['default'] === $parts['default'] && array( 'image', 'title', 'rating', 'price', 'cart', 'badge' ) === $parts['default'] && ! array_intersect( array( 'quickview', 'wishlist', 'swatches' ), $parts['default'] ), 'Las partes nuevas quedan fuera del valor por defecto.' );
	$rest = $parts;
	unset( $rest['options'], $rest['assets'], $old['options'] );
	check_design( $old === $rest && empty( $parts['selectors'] ), 'El control de partes no cambia en nada más y no tiene selectors.' );
	foreach ( $current['controls'] as $name => $control ) {
		if ( Products_Parts::PARTS_KEY !== $name ) {
			check_design( $fixture['controls'][ $name ] === $control, "{$name}: igual que en 4.3.0.164." );
		}
	}
	$front = $current['front'];
	check_design( isset( $front[ Products_Parts::PARTS_KEY ]['assets'] ) && ! isset( $front[ Products_Parts::PARTS_KEY ]['options'] ), 'En la pila optimizada del front, assets se conserva (el cargador del núcleo lo lee) y las opciones no.' );
	unset( $front[ Products_Parts::PARTS_KEY ]['assets'] );
	check_design( $fixture['front'] === $front, 'La pila del front es la de 4.3.0.164 salvo assets.' );

	// 2. CSS del documento: `===` con y sin las partes nuevas, y al de un Productos sin el rasgo.
	parts_boot();
	$css = array();
	foreach ( array(
		'sin ajustes'        => array(),
		'partes por defecto' => array( 'digitalisimo_wc_custom' => 'yes' ),
		'partes nuevas'      => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'image', 'title', 'quickview', 'wishlist', 'swatches' ) ),
		'sólo nuevas'        => array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'swatches' ) ),
	) as $label => $settings ) {
		$css[ $label ] = design_css( array( array( 'id' => 'p000001', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => $settings ) ) );
	}
	$hook   = 'elementor/element/woocommerce-products/section_content/after_section_end';
	$saved  = $GLOBALS['dh']['hooks'][ $hook ];
	unset( $GLOBALS['dh']['hooks'][ $hook ] );
	parts_boot();
	$bare = design_css( array( array( 'id' => 'p000001', 'elType' => 'widget', 'widgetType' => 'woocommerce-products', 'settings' => array() ) ) );
	$GLOBALS['dh']['hooks'][ $hook ] = $saved;
	check_design( false !== strpos( $bare, 'gap:20px' ) && 1 === count( array_unique( $css ) ) && $bare === $css['sin ajustes'], 'CSS === con y sin las partes nuevas y === al de Productos sin el rasgo (' . strlen( $bare ) . ' B).' );

	// 3. `assets` del control: una hoja y un script por parte, evaluados por el núcleo.
	$assets = $parts['assets'];
	check_design( array( 'styles', 'scripts' ) === array_keys( $assets ) && 3 === count( $assets['styles'] ) && 3 === count( $assets['scripts'] ), 'assets: tres hojas y tres scripts.' );
	foreach ( $assets as $type => $list ) {
		foreach ( $list as $i => $dependency ) {
			$part = array_keys( Products_Parts::WOO_PARTS )[ $i ];
			check_design( 'digitalisimo-woo-' . Products_Parts::WOO_PARTS[ $part ][3] === $dependency['name'] && 'digitalisimo_wc_custom' === $dependency['conditions']['terms'][0]['name'] && array( 'name' => 'digitalisimo_wc_parts', 'operator' => 'contains', 'value' => $part ) === $dependency['conditions']['terms'][1], "{$type}: {$dependency['name']} con «Elegir qué partes mostrar» y contains {$part}." );
		}
	}
	$enabled = function ( array $settings ) use ( $assets ) {
		parts_boot();
		$active = parts_widget( $settings )->get_active_settings();
		$names  = array();
		foreach ( $assets['styles'] as $dependency ) {
			if ( \Elementor\Conditions::check( $dependency['conditions'], $active ) ) {
				$names[] = $dependency['name'];
			}
		}
		return $names;
	};
	check_design( array() === $enabled( array() ) && array() === $enabled( array( 'digitalisimo_wc_parts' => array( 'quickview' ) ) ), 'Sin «Elegir qué partes mostrar», ningún recurso (y ningún error con las partes en null).' );
	check_design( array() === $enabled( array( 'digitalisimo_wc_custom' => 'yes' ) ), 'Con las partes por defecto, ningún recurso nuevo.' );
	check_design( array( 'digitalisimo-woo-quick-view', 'digitalisimo-woo-swatches' ) === $enabled( array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'swatches', 'image', 'quickview' ) ) ), 'Con vista rápida y muestras, sólo sus recursos.' );
	check_design( Products_Parts::has_part( array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'quickview' ) ), 'quickview' ) && ! Products_Parts::has_part( array( 'digitalisimo_wc_parts' => array( 'quickview' ) ), 'quickview' ) && ! Products_Parts::has_part( array( 'digitalisimo_wc_custom' => 'yes' ), 'wishlist' ), 'has_part(): sólo con «Elegir qué partes mostrar» y la parte elegida (permiso de la vista rápida).' );

	// 4. Ganchos: sin las partes nuevas, los de 4.3.0.164; con ellas, cada renderizador en su sitio.
	foreach ( PARTS_CASES as $label => $settings ) {
		check_design( $fixture['cases'][ $label ] === $current['cases'][ $label ], "Sin las partes nuevas, «{$label}»: los mismos ganchos y la misma salida que 4.3.0.164." );
	}
	$new = array( 'digitalisimo_wc_custom' => 'yes', 'digitalisimo_wc_parts' => array( 'image', 'price', 'quickview', 'wishlist', 'swatches' ) );
	parts_boot();
	$widget = parts_widget( $new );
	$before = parts_snapshot();
	Products_Parts::before_render( $widget );
	$without = parts_snapshot();
	Products_Parts::after_render( $widget );
	check_design( false === strpos( json_encode( $without ), '12|closure' ) && false === strpos( json_encode( $without ), '13|closure' ) && false === strpos( json_encode( $without ), '15|closure' ), 'Sin renderizadores (WooCommerce visual ausente), las partes nuevas no añaden ningún gancho.' );
	check_design( $before === parts_snapshot(), 'Y todo se restaura al terminar.' );

	eval( 'namespace Digitalisimo\\Elements\\Woo\\Quick_View; final class Trigger { public static function markup( int $id, array $args ): string { return "<a data-qv=\\"" . $id . "\\" data-post=\\"" . $args["post"] . "\\" data-widget=\\"" . $args["widget"] . "\\" data-same=\\"" . ( $args["post"] === $args["post_id"] && $args["widget"] === $args["widget_id"] ? 1 : 0 ) . "\\">QV</a>"; } }' );
	eval( 'namespace Digitalisimo\\Elements\\Woo\\Wishlist; final class Button { public static function markup( int $id, array $args ): string { return "<button data-wish=\\"" . $id . "\\">" . $args["context"] . "</button>"; } }' );
	eval( 'class WC_Product { public $id = 10; }' );
	parts_boot();
	\Elementor\Plugin::$instance->documents          = new class() {
		public $docs = array();
		public function get( $id, $from_cache = true ) { return null; }
		public function get_current() { return new Design_Document( 77, array() ); }
	};
	$widget = parts_widget( $new );
	$before = parts_snapshot();
	Products_Parts::before_render( $widget );
	$during = parts_snapshot();
	check_design( in_array( '12|closure', $during['woocommerce_after_shop_loop_item']['hooks'], true ) && in_array( '13|closure', $during['woocommerce_after_shop_loop_item']['hooks'], true ) && ! in_array( '15|closure', $during['woocommerce_after_shop_loop_item_title']['hooks'], true ), 'Vista rápida (12) y lista de deseos (13) tras la compra; sin el renderizador de muestras, nada en su gancho.' );
	check_design( false !== strpos( $during['woocommerce_after_shop_loop_item']['output'], '<a data-qv="10" data-post="77" data-widget="p000001" data-same="1">QV</a><button data-wish="10">products</button>' ), 'El renderizador recibe el producto del bucle, el documento que contiene el widget y su ID, en ese orden.' );
	Products_Parts::after_render( $widget );
	check_design( $before === parts_snapshot(), 'after_render() quita los ganchos de las partes nuevas.' );
	eval( 'namespace Digitalisimo\\Elements\\Woo\\Swatches; final class Loop { public static function markup( \\WC_Product $product, array $args ): string { return "<ul data-sw=\\"" . $product->id . "\\"></ul>"; } }' );
	$GLOBALS['product'] = new WC_Product();
	$widget             = parts_widget( $new );
	Products_Parts::before_render( $widget );
	$during = parts_snapshot();
	check_design( in_array( '15|closure', $during['woocommerce_after_shop_loop_item_title']['hooks'], true ) && false !== strpos( $during['woocommerce_after_shop_loop_item_title']['output'], '[woocommerce_template_loop_price]<ul data-sw="10"></ul>' ), 'Las muestras van tras el precio (15) con el producto global del bucle.' );
	Products_Parts::after_render( $widget );
	$GLOBALS['product'] = null;
	$widget             = parts_widget( $new );
	Products_Parts::before_render( $widget );
	check_design( false === strpos( parts_snapshot()['woocommerce_after_shop_loop_item_title']['output'], 'data-sw' ), 'Sin producto en el bucle, las muestras no pintan nada.' );
	Products_Parts::after_render( $widget );

	// La parte de la tabla no cambia: Products_Table usa sus propias columnas (V.5.6).
	$table = file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-products-table.php' );
	check_design( false === strpos( $table, 'quickview' ) && false === strpos( $table, 'swatches' ), 'La presentación «Tabla» ignora las partes nuevas (declarado).' );
	$source = file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-products-parts.php' );
	check_design( ! preg_match( '/\bstr_contains\(|\bmatch\s*\(|\?->/', $source ), 'class-products-parts.php: compatible con PHP 7.4.' );

	echo "F4-Woo · Productos: tres partes fuera del valor por defecto, controles y CSS === 4.3.0.164, assets con contains, ganchos de siempre sin las partes y renderizadores en su sitio con ellas, en verde.\n";
}
