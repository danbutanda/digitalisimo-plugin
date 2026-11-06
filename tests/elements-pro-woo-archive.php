<?php
/**
 * Widgets de tienda del frente N de C7 (docs/pro/c7.md, fork S): Categorías de productos, Descripción del archivo de la
 * tienda, Imagen de la categoría, Migas de la tienda y Avisos de la tienda (`modules/digitalisimo-woo/store/`), frente a
 * los widgets de PRO que sustituyen (`modules/woocommerce/widgets/{categories,archive-description,category-image,
 * breadcrumb,notices}.php`), cargados a la vez sobre el entorno de `tests/elements-theme-elements/stubs.php`:
 *
 * - registro: nombre, título y archivo en `Woo\Registry`, categorías de PRO, envoltura interior como PRO, recursos
 *   propios (hoja ≤ 4 KB con foco visible, sin `--e-global-*` ni `url()`; script sólo en los avisos, ≤ 8 KB, sin jQuery),
 *   nada vetado por el empaquetado ni dominios de proveedores;
 * - controles: los mismos que PRO (también las columnas de su base), con el mismo tipo, valor por defecto (también por
 *   dispositivo), opciones, condiciones, globales y prefijos; la Imagen de la categoría cambia lo mismo que PRO sobre la
 *   Imagen del núcleo;
 * - salida: la misma llamada a WooCommerce con los mismos argumentos (atajo `[product_categories]`,
 *   `woocommerce_breadcrumb()`, `woocommerce_archive_description`, `woocommerce_output_all_notices()`), sin `<style>` ni
 *   `<script>` en el marcado, y el aviso de muestra en el editor;
 * - mapas: cada control de PRO declarado y conversión de ajustes reales (por defecto, por dispositivo, globales,
 *   dinámicos, CSS personalizado) sin `pro.unmapped`, con cada clave en un control propio.
 */
namespace {
	$GLOBALS['h_wp'] = array( 'features' => array( 'e_optimized_markup' ) );
	$GLOBALS['wa']   = array( 'shortcodes' => array(), 'out' => array() );
	function do_shortcode( $code ) {
		$GLOBALS['wa']['shortcodes'][] = $code;
		return '<div class="woocommerce columns-4 "><ul class="products columns-4"><li class="product-category product first"><a aria-label="Ver la categoría" href="https://sitio.test/c/uno/"><img src="u.png" alt="Uno" /><h2 class="woocommerce-loop-category__title">Uno <mark class="count">(4)</mark></h2></a></li></ul></div>';
	}
	function do_action( $hook, ...$args ) {
		$GLOBALS['h_wp']['actions'][] = $hook;
		if ( 'woocommerce_archive_description' === $hook ) {
			echo '<div class="term-description"><p>Descripción de la categoría.</p></div>';
		}
	}
	function woocommerce_breadcrumb( $args = array() ) {
		$GLOBALS['wa']['breadcrumb'][] = $args;
		echo '<nav class="woocommerce-breadcrumb" aria-label="Breadcrumb"><a href="https://sitio.test/">Inicio</a>&nbsp;&#47;&nbsp;Producto</nav>';
	}
	function woocommerce_output_all_notices() {
		echo '<div class="woocommerce-notices-wrapper"><div class="woocommerce-message" role="alert">Hecho.</div></div>';
	}
	function wc_get_template( $name, $args = array() ) {
		$GLOBALS['wa']['templates'][] = array( $name, $args );
		echo '<div class="woocommerce-info">' . $args['notices'][0]['notice'] . '</div>';
	}
	function get_queried_object_id() { return 77; }
	function taxonomy_exists( $t ) { return 'product_cat' === $t; }
	function get_terms( $args = array() ) {
		return array( (object) array( 'term_id' => 16, 'name' => 'Ropa' ), (object) array( 'term_id' => 17, 'name' => 'Hogar' ) );
	}
	function plugins_url( $path = '', $file = '' ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function wp_style_is( $h, $s = 'enqueued' ) { return false; }
	function wp_script_is( $h, $s = 'enqueued' ) { return false; }
	function wp_register_style( ...$a ) { return true; }
	define( 'DIGITALISIMO_ELEMENTS_FILE', dirname( __DIR__ ) . '/digitalisimo-elements/pro-elements.php' );
	require __DIR__ . '/elements-theme-elements/stubs.php';

	// Lo que la base de PRO y la propia necesitan del núcleo, y la Imagen del núcleo mínima (sólo para comparar lo que
	// cambian PRO y el propio sobre ella: los dos la heredan tal cual).
	eval( 'namespace Elementor; class Controls_Stack { const RESPONSIVE_DESKTOP = "desktop"; const RESPONSIVE_TABLET = "tablet"; const RESPONSIVE_MOBILE = "mobile"; }
	class Plugin { public static $instance; }
	class Widget_Image extends Widget_Base {
		public function get_name() { return "image"; }
		public function get_style_depends(): array { return array( "widget-image" ); }
		protected function register_controls() {
			$this->start_controls_section( "section_image", array( "label" => "Image" ) );
			$this->add_control( "image", array( "type" => Controls_Manager::MEDIA, "dynamic" => array( "active" => true ), "default" => array( "url" => "" ) ) );
			$this->add_group_control( Group_Control_Image_Size::get_type(), array( "name" => "image", "default" => "large" ) );
			$this->add_control( "link_to", array( "type" => Controls_Manager::SELECT, "default" => "none", "options" => array( "none" => "", "file" => "", "custom" => "" ) ) );
			$this->end_controls_section();
		}
		protected function get_html_wrapper_class() { return "elementor-widget-" . $this->get_name(); }
		public function wrapper_class() { return $this->get_html_wrapper_class(); }
		public function render_markdown(): string { return ""; }
		protected function render() { echo "<img class=\"core-image\" />"; }
	}' ); // phpcs:ignore Squiz.PHP.Eval -- sólo pruebas.
	eval( 'namespace Elementor\Core\Breakpoints; class Manager { public static function get_default_config() { return array( "mobile" => array(), "mobile_extra" => array(), "tablet" => array(), "tablet_extra" => array(), "laptop" => array(), "widescreen" => array() ); } }' ); // phpcs:ignore
	eval( 'namespace ElementorPro\Base; trait Base_Widget_Trait { public function is_editable() { return true; } }' ); // phpcs:ignore
	eval( 'namespace ElementorPro\Modules\Woocommerce\Classes; class Products_Renderer { const DEFAULT_COLUMNS_AND_ROWS = 4; }' ); // phpcs:ignore
	$wa_tags = new class() { public function tag_data_to_tag_text( $id, $name, array $settings = array() ) { return '[elementor-tag id="" name="' . $name . '" settings="%7B%7D"]'; } };
	\Elementor\Plugin::$instance = (object) array( 'experiments' => \ElementorPro\Plugin::elementor()->experiments, 'editor' => \ElementorPro\Plugin::elementor()->editor, 'preview' => \ElementorPro\Plugin::elementor()->preview, 'dynamic_tags' => $wa_tags );
	\ElementorPro\Plugin::elementor()->dynamic_tags = $wa_tags;

	$wa_root = dirname( __DIR__ ) . '/digitalisimo-elements/';
	require_once $wa_root . 'modules/woocommerce/traits/product-id-trait.php';
	require_once $wa_root . 'modules/woocommerce/widgets/base-widget.php';
	foreach ( array( 'categories', 'archive-description', 'category-image', 'breadcrumb', 'notices' ) as $wa_file ) {
		require_once $wa_root . 'modules/woocommerce/widgets/' . $wa_file . '.php';
	}
	require_once $wa_root . 'modules/digitalisimo-woo/class-module.php';
	\Digitalisimo\Elements\Woo\Module::register_autoloader();

	use Digitalisimo\Elements\Woo\Registry;
	use Digitalisimo\Elements\Woo\Store;

	$wa_pairs = array(
		'wc-categories'                   => array( \ElementorPro\Modules\Woocommerce\Widgets\Categories::class, Store\Categories::class, 'Categorías de productos', array( 'woocommerce-elements' ) ),
		'woocommerce-archive-description' => array( \ElementorPro\Modules\Woocommerce\Widgets\Archive_Description::class, Store\Archive_Description::class, 'Descripción del archivo de la tienda', array( 'woocommerce-elements-archive' ) ),
		'woocommerce-category-image'      => array( \ElementorPro\Modules\ThemeBuilder\Widgets\Category_Image::class, Store\Category_Image::class, 'Imagen de la categoría', array( 'woocommerce-elements', 'woocommerce-elements-single' ) ),
		'woocommerce-breadcrumb'          => array( \ElementorPro\Modules\Woocommerce\Widgets\Breadcrumb::class, Store\Breadcrumb::class, 'Migas de la tienda', array( 'woocommerce-elements', 'woocommerce-elements-single' ) ),
		'woocommerce-notices'             => array( \ElementorPro\Modules\Woocommerce\Widgets\Notices::class, Store\Notices::class, 'Avisos de la tienda', array( 'woocommerce-elements' ) ),
	);
	$vendors = json_decode( (string) file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
	$hosts   = array_merge( (array) ( $vendors['hosts'] ?? array() ), (array) ( $vendors['future'] ?? array() ) );
	$host_re = '/(?<![a-z0-9-])(?:[a-z0-9-]+\.)*(?:' . implode( '|', array_map( static function ( $h ) { return preg_quote( $h, '/' ); }, $hosts ) ) . ')(?![a-z0-9-])/i';

	$checks  = 0;
	$widgets = array();
	foreach ( $wa_pairs as $pro_name => list( $pro_class, $own_class, $title, $categories ) ) {
		$pro = h_widget( $pro_class );
		$own = h_widget( $own_class );
		$widgets[ $pro_name ] = array( $pro, $own );
		$name = $own->get_name();
		$slug = substr( $name, strlen( 'digitalisimo-woo-' ) );

		// 1. Registro y recursos.
		h_check( $pro_name === $pro->get_name() && $title === $own->get_title() && $categories === $own->get_categories() && $categories === $pro->get_categories(), "$name: nombre, título y categorías de PRO." );
		h_check( isset( Registry::WIDGETS[ $name ], Registry::TITLES[ $name ] ) && $title === Registry::TITLES[ $name ] && 'store/class-' . $slug . '.php' === Registry::WIDGETS[ $name ]['file'] && ltrim( $own_class, '\\' ) === ltrim( Registry::WIDGETS[ $name ]['class'], '\\' ), "$name: en Registry::WIDGETS y TITLES con su archivo, clase y título." );
		h_check( $pro->has_widget_inner_wrapper() === $own->has_widget_inner_wrapper() && false === $own->has_widget_inner_wrapper(), "$name: envoltura interior como PRO (sin ella con el marcado optimizado)." );
		h_check( $pro->get_icon() === $own->get_icon() && 'woocommerce' === $own->get_group_name(), "$name: icono y grupo de PRO." );
		$css_file = h_path( Registry::WIDGETS[ $name ]['css'] );
		$js_file  = h_path( Registry::WIDGETS[ $name ]['js'] );
		$css      = (string) @file_get_contents( $css_file );
		$styles   = 'woocommerce-category-image' === $pro_name ? array( 'widget-image', $name ) : array( $name );
		h_check( $styles === $own->get_style_depends(), "$name: hojas " . json_encode( $own->get_style_depends() ) );
		h_check( strlen( $css ) > 0 && strlen( $css ) <= 4096 && ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ), "$name: hoja propia de " . strlen( $css ) . ' B (≤ 4 096), sin el kit ni url().' );
		h_check( preg_match( '/:focus-visible\{outline:2px solid;outline-offset:2px\}/', $css ), "$name: foco visible de 2 px con separación de 2 px." );
		if ( preg_match( '/transition|animation/', $css ) ) {
			h_check( str_contains( $css, 'prefers-reduced-motion' ), "$name: movimiento reducido." );
		}
		$script = 'woocommerce-notices' === $pro_name;
		h_check( ( $script ? array( $name ) : array() ) === ( method_exists( $own, 'get_script_depends' ) ? $own->get_script_depends() : array() ) && $script === is_file( $js_file ), "$name: " . ( $script ? 'su script' : 'sin script' ) . '.' );
		$files = array( h_path( 'modules/digitalisimo-woo/' . Registry::WIDGETS[ $name ]['file'] ), $css_file, h_path( 'modules/digitalisimo-convert/maps/' . $pro_name . '.php' ) );
		if ( $script ) {
			$js = (string) file_get_contents( $js_file );
			h_check( strlen( $js ) <= 8192 && ! preg_match( '/\bjQuery\s*[.(]|\$\(|elementorFrontend/', $js ), "$name: script ≤ 8 KB sin jQuery ni el front de PRO." );
			$files[] = $js_file;
		}
		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );
			h_check( ! preg_match( H_VETOED, $source ) && ! preg_match( $host_re, $source ), basename( $file ) . ': nada vetado por el empaquetado ni dominios de proveedores.' );
		}
		$code = h_code( $files[0] );
		h_check( ! preg_match( '/ElementorPro\\\\Modules|wp_enqueue_script|<img|<script|<style|ld\+json|wp_get_attachment_image\(/i', $code ), "$name: sin clases de PRO, scripts encolados a mano, <img>, <script>, <style> ni JSON-LD." );

		// 2. Controles.
		$skip = array();
		if ( 'wc-categories' === $pro_name ) {
			// Mismo control con el prefijo propio (`digi-woo-grid digi-woo-` y `digi-woo-grid--align-`).
			$skip = array( 'products_class.prefix_class' => true, 'align.prefix_class' => true );
		}
		$diff = h_compare_widgets( $pro, $own, $skip );
		h_check( ! $diff, "$name: controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
		foreach ( $pro->controls as $control => $args ) {
			h_check( isset( $args['selectors'] ) === isset( $own->controls[ $control ]['selectors'] ) && count( (array) ( $args['selectors'] ?? array() ) ) === count( (array) ( $own->controls[ $control ]['selectors'] ?? array() ) ), "$name.$control: los mismos selectores." );
			foreach ( (array) ( $own->controls[ $control ]['selectors'] ?? array() ) as $selector => $rule ) {
				h_check( in_array( $rule, (array) $args['selectors'], true ) && ! preg_match( '/\.(elementor-wc-products|elementor-products-grid|e-)/', $selector ), "$name.$control: misma regla y clase propia ($selector)." );
			}
		}
		$checks += count( $pro->controls ) + count( $pro->groups );

		// 3. Mapa: cada control de PRO declarado.
		$map      = require h_path( 'modules/digitalisimo-convert/maps/' . $pro_name . '.php' );
		$declared = array_merge( (array) ( $map['keep'] ?? array() ), array_keys( (array) ( $map['groups'] ?? array() ) ), (array) ( $map['ui'] ?? array() ), array_keys( (array) ( $map['drop'] ?? array() ) ), array_keys( (array) ( $map['approx'] ?? array() ) ), array_keys( (array) ( $map['rename'] ?? array() ) ) );
		$missing  = array_diff( array_merge( array_keys( $pro->controls ), array_keys( $pro->groups ) ), $declared );
		h_check( ! $missing, "$pro_name: el mapa no declara " . implode( ', ', $missing ) );
		h_check( $pro_name === $map['source'] && $name === $map['target'] && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), "$pro_name: mapa hacia $name, oculto del panel." );
		h_check( isset( $map['classes'][ '.elementor-widget-' . $pro_name ] ) && '.elementor-widget-' . $name === $map['classes'][ '.elementor-widget-' . $pro_name ], "$pro_name: el CSS personalizado pasa a la clase del widget propio." );
	}

	// 4. Salida.
	$GLOBALS['h_wp']['edit_mode'] = false;
	list( $pro, $own ) = $widgets['wc-categories'];
	foreach ( array(
		'por defecto' => array(),
		'por ID'      => array( 'source' => 'by_id', 'categories' => array( '16', '17' ), 'number' => '2', 'orderby' => 'count', 'order' => 'asc', 'hide_empty' => 'yes' ),
		'por padre'   => array( 'source' => 'by_parent', 'parent' => '16', 'columns' => 3 ),
		'subcategorías de la actual' => array( 'source' => 'current_subcategories' ),
	) as $label => $settings ) {
		$GLOBALS['wa']['shortcodes'] = array();
		$a = h_render( $pro, $settings );
		$b = h_render( $own, $settings );
		h_check( $a === $b && 2 === count( $GLOBALS['wa']['shortcodes'] ) && $GLOBALS['wa']['shortcodes'][0] === $GLOBALS['wa']['shortcodes'][1], "Categorías ($label): el mismo atajo y el mismo marcado que PRO: " . implode( ' | ', $GLOBALS['wa']['shortcodes'] ) );
		h_check( str_contains( $b, '<ul class="products elementor-grid columns-4">' ), "Categorías ($label): lista en la rejilla del núcleo." );
	}
	h_check( str_contains( $GLOBALS['wa']['shortcodes'][0], 'parent="77"' ), 'Subcategorías de la actual: el término consultado como padre.' );
	$own->attrs = array();
	ob_start();
	$own->render_plain_content();
	h_check( '[product_categories number="4" columns="4" hide_empty="0" orderby="name" order="desc" parent="77"]' === trim( (string) ob_get_clean() ), 'Contenido plano: el atajo, como PRO.' );
	h_check( 'digi-woo-grid digi-woo-' === $own->controls['products_class']['prefix_class'] && 'wc-products' === $own->controls['products_class']['default'] && 'digi-woo-grid--align-' === $own->controls['align']['prefix_class'], 'Clases propias del envoltorio (rejilla y alineación).' );

	list( $pro, $own ) = $widgets['woocommerce-archive-description'];
	$GLOBALS['h_wp']['actions'] = array();
	h_check( h_render( $pro ) === h_render( $own ) && array( 'woocommerce_archive_description', 'woocommerce_archive_description' ) === $GLOBALS['h_wp']['actions'], 'Descripción: la misma acción de WooCommerce y el mismo marcado.' );

	list( $pro, $own ) = $widgets['woocommerce-breadcrumb'];
	$GLOBALS['wa']['breadcrumb'] = array();
	h_check( h_render( $pro ) === h_render( $own ) && array( array(), array() ) === $GLOBALS['wa']['breadcrumb'], 'Migas: `woocommerce_breadcrumb()` con sus argumentos por defecto y el mismo marcado.' );

	list( $pro, $own ) = $widgets['woocommerce-notices'];
	$a = h_render( $pro );
	$b = h_render( $own );
	h_check( str_contains( $a, '<style>' ) && ! str_contains( $b, '<style' ) && ! str_contains( $b, '<script' ), 'Avisos: sin el <style> en línea de PRO ni <script>.' );
	h_check( '<div class="digi-woo-notices" data-digi-woo-notices><div class="woocommerce-notices-wrapper"><div class="woocommerce-message" role="alert">Hecho.</div></div></div>' === $b, 'Avisos: los de WooCommerce dentro de la envoltura propia: ' . $b );
	h_check( str_contains( $a, '<div class="woocommerce-notices-wrapper"><div class="woocommerce-message" role="alert">Hecho.</div></div>' ), 'PRO pinta los mismos avisos.' );
	$GLOBALS['h_wp']['edit_mode'] = true;
	$GLOBALS['wa']['templates']   = array();
	$b = h_render( $own );
	h_check( str_contains( $b, 'digi-woo-notices__demo' ) && str_contains( $b, 'Así se ve un aviso de WooCommerce' ) && ! str_contains( $b, 'data-digi-woo-notices' ) && 'notices/notice.php' === $GLOBALS['wa']['templates'][0][0], 'Avisos en el editor: el aviso de muestra con la plantilla de WooCommerce, sin la envoltura del front.' );
	$GLOBALS['h_wp']['edit_mode'] = false;
	$notices_css = (string) file_get_contents( h_path( Registry::WIDGETS['digitalisimo-woo-notices']['css'] ) );
	h_check( str_contains( $notices_css, 'body:not(.elementor-editor-active) :is(.woocommerce-notices-wrapper,.woocommerce-message,.woocommerce-error,.woocommerce-info){display:none}' ), 'Avisos: la hoja oculta los avisos de fuera del widget, como el estilo en línea de PRO.' );
	$notices_js = (string) file_get_contents( h_path( Registry::WIDGETS['digitalisimo-woo-notices']['js'] ) );
	h_check( str_contains( $notices_js, ':not(.woocommerce-NoticeGroup) .wc-block-components-notice-banner, .woocommerce-NoticeGroup, :not(.woocommerce-NoticeGroup) .woocommerce-error, :not(.woocommerce-NoticeGroup) .woocommerce-message, :not(.woocommerce-NoticeGroup) .woocommerce-info' ) && str_contains( $notices_js, 'MutationObserver' ) && str_contains( $notices_js, 'prefers-reduced-motion' ), 'Avisos: el script busca los mismos avisos que PRO, sigue los que llegan después y respeta el movimiento reducido.' );

	list( $pro, $own ) = $widgets['woocommerce-category-image'];
	h_check( '[elementor-tag id="" name="woocommerce-category-image-tag" settings="%7B%7D"]' === $own->controls['image']['dynamic']['default'] && $pro->controls['image'] === $own->controls['image'], 'Imagen de la categoría: la imagen de la categoría por defecto, como PRO.' );
	h_check( 'elementor-widget-digitalisimo-woo-category-image elementor-widget-image' === $own->wrapper_class() && 'elementor-widget-woocommerce-category-image elementor-widget-image' === $pro->wrapper_class(), 'Imagen de la categoría: envoltorio con la clase de la Imagen del núcleo.' );
	h_check( h_render( $pro ) === h_render( $own ), 'Imagen de la categoría: el marcado de la Imagen del núcleo.' );

	// 5. Conversión.
	$total     = 0;
	$converted = array();
	foreach ( array(
		'wc-categories'                   => array(
			'defaults'   => array(),
			'query'      => array( 'source' => 'by_id', 'categories' => array( '16', '17' ), 'hide_empty' => 'yes', 'orderby' => 'count', 'order' => 'asc', 'number' => '6' ),
			'responsive' => array( 'columns' => '3', 'columns_tablet' => '2', 'columns_mobile' => '1', 'align' => 'center', 'align_mobile' => 'left', 'column_gap' => array( 'unit' => 'px', 'size' => 10 ), 'row_gap' => array( 'unit' => 'px', 'size' => 12 ) ),
			'styled'     => array( 'image_border_border' => 'solid', 'image_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px' ), 'image_border_radius' => array( 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px' ), 'image_spacing' => array( 'unit' => 'px', 'size' => 6 ), 'title_color' => '#222222', 'title_typography_typography' => 'custom', 'title_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'count_color' => '#888888', 'count_typography_typography' => 'custom' ),
			'globals'    => array( '__globals__' => array( 'title_color' => 'globals/colors?id=accent', 'title_typography_typography' => 'globals/typography?id=secondary' ) ),
			'css'        => array( 'custom_css' => "selector.elementor-wc-products ul.products{gap:0}\n.elementor-widget-wc-categories .count{display:none}\nselector.elementor-product-loop-item--align-center h2{margin:0}" ),
		),
		'woocommerce-archive-description' => array(
			'defaults' => array(),
			'styled'   => array( 'text_align' => 'center', 'text_align_mobile' => 'start', 'text_color' => '#333333', 'text_typography_typography' => 'custom', 'text_typography_font_size' => array( 'unit' => 'px', 'size' => 17 ) ),
			'globals'  => array( '__globals__' => array( 'text_color' => 'globals/colors?id=text', 'text_typography_typography' => 'globals/typography?id=text' ) ),
		),
		'woocommerce-breadcrumb'          => array(
			'defaults' => array(),
			'styled'   => array( 'text_color' => '#444444', 'link_color' => '#0000ff', 'alignment' => 'end', 'alignment_tablet' => 'center', 'text_typography_typography' => 'custom', 'text_typography_font_weight' => '600' ),
			'css'      => array( 'custom_css' => '.elementor-widget-woocommerce-breadcrumb a{text-decoration:underline}' ),
		),
		'woocommerce-notices'             => array(
			'defaults' => array(),
			'css'      => array( 'custom_css' => "selector .e-woocommerce-notices-wrapper{margin:0}\nselector .e-notices-demo-notice{opacity:.5}" ),
		),
		'woocommerce-category-image'      => array(
			'defaults' => array(),
			'dynamic'  => array( '__dynamic__' => array( 'image' => '[elementor-tag id="c1" name="woocommerce-category-image-tag" settings="%7B%7D"]' ), 'image_size' => 'medium', 'link_to' => 'file' ),
		),
	) as $pro_name => $cases ) {
		list( $pro, $own ) = $widgets[ $pro_name ];
		list( $out, $codes ) = h_convert( $pro_name, $own, $cases );
		$converted[ $pro_name ] = $out;
		$total += count( $cases );
		h_check( ! preg_grep( '/:pro\.(dropped|approx)/', $codes ), "$pro_name: nada descartado ni aproximado: " . implode( ', ', $codes ) );
		foreach ( $cases as $id => $settings ) {
			foreach ( $settings as $key => $value ) {
				if ( 'custom_css' !== $key ) {
					h_check( ( $out[ $id ][ $key ] ?? null ) === $value, "$pro_name ($id): $key pasa con su nombre y su valor." );
				}
			}
		}
		if ( isset( $out['css'] ) ) {
			h_check( ! preg_match( '/elementor-wc-products|elementor-widget-' . preg_quote( $pro_name, '/' ) . '\b|e-woocommerce-notices|e-notices-demo|elementor-product-loop-item/', $out['css']['custom_css'] ), "$pro_name: el CSS personalizado pasa a las clases propias: " . $out['css']['custom_css'] );
		}
	}
	h_check( "selector.digi-woo-wc-products ul.products{gap:0}\n.elementor-widget-digitalisimo-woo-categories .count{display:none}\nselector.digi-woo-grid--align-center h2{margin:0}" === $converted['wc-categories']['css']['custom_css'], 'Categorías: CSS personalizado con las clases propias: ' . $converted['wc-categories']['css']['custom_css'] );
	h_check( "selector .digi-woo-notices{margin:0}\nselector .digi-woo-notices__demo{opacity:.5}" === $converted['woocommerce-notices']['css']['custom_css'], 'Avisos: CSS personalizado con las clases propias.' );

	echo 'DIGITALÍSIMO Elements: Categorías de productos, Descripción del archivo, Imagen de la categoría, Migas y Avisos de la tienda (' . $checks . ' controles y grupos de PRO comparados, ' . $total . " conversiones) validados.\n";
}
