<?php
/**
 * Kit de ventas de DIGITALÍSIMO Elements: la landing hereda la identidad DEL SITIO y una oferta de tienda se arma con
 * sus productos reales. Caso plomerito 3 (2026-11-25, IA Tools 1.0.73 + Elements 4.3.0.197): «créame una landing de
 * oferta navideña con todos los productos al 40 %» en un sitio azul rey y amarillo salió gris oscuro y verde menta (la
 * paleta DIGITALÍSIMO), sin la cabecera ni el pie del sitio, con «Lo que vas a lograr», «+ todos los bonus», la factura
 * y el aviso de «resultados e ingresos», sin un solo producto y con el botón al carrito vacío.
 *
 * Comprueba, sin WordPress (dobles mínimos):
 * - T · ninguna plantilla del kit lleva fija la paleta DIGITALÍSIMO (apuntan a los globales del sitio) y todas piden la
 *   cabecera y el pie del sitio;
 * - K · `Brand::resolve()` con un kit real (azul y amarillo): los papeles apuntan a los globales del kit;
 * - F · con el kit de fábrica pero la cabecera y el pie azules y amarillos: los colores y las fuentes salen del diseño;
 * - A · `Brand::apply()` sobre la venta corta: ni un color DIGITALÍSIMO, los del sitio, las fuentes del sitio y el texto
 *   de los botones legible;
 * - S · `Store::page()`: cuadrícula de productos reales con foto, precio tachado y 40 % menos, garantías del pie, pagos y
 *   envíos reales, preguntas reales, botón a la tienda y ningún texto de cursos (en contenedores y en secciones);
 * - E · `create-page` con `store_offer`: borrador con cabecera y pie, `check` y `look` que lo confirman, sin tocar
 *   precios; y sus errores (con template, porcentaje fuera de rango, categoría inexistente).
 * Falla con el código anterior: las plantillas llevan #00A86B, #00FF99, #1F252C… y el lienzo vacío; Brand y Store no
 * existen y create-page rechaza store_offer («Parámetros desconocidos»).
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['sb_count'] = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['sb_count'];
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	}
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function add_action() {
	}
	function add_filter() {
	}
	function apply_filters( $hook, $value ) {
		return $value;
	}
	function current_user_can( $cap ) {
		return 'edit_pages' === $cap;
	}
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
	}
	function sanitize_title( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ) );
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
	function home_url( $path = '' ) {
		return 'https://ferre.test' . $path;
	}
	function wp_timezone() {
		return new DateTimeZone( 'America/Mexico_City' );
	}
	function wp_timezone_string() {
		return 'America/Mexico_City';
	}
	function get_option( $name, $default = false ) {
		return array( 'timezone_string' => 'America/Mexico_City' )[ $name ] ?? $default;
	}

	$base = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-sales';
	foreach ( array( 'class-catalog.php', 'class-placeholders.php', 'class-builder.php', 'class-site.php', 'class-finisher.php', 'class-product.php', 'class-brand.php', 'class-store.php', 'class-pages.php', 'mcp/class-tools.php', 'mcp/class-executor.php' ) as $file ) {
		check( is_file( "$base/$file" ), "falta $file (el kit no lee la identidad del sitio ni arma ofertas de tienda)" );
		require_once "$base/$file";
	}
	use Digitalisimo\Elements\Sales\Brand;
	use Digitalisimo\Elements\Sales\Catalog;
	use Digitalisimo\Elements\Sales\Store;
	use Digitalisimo\Elements\Sales\Mcp\Executor;

	const PALETTE = array( '#1592DA', '#0A6FCF', '#0F141A', '#F5F8FB', '#00A86B', '#00FF99', '#1F252C', '#4E5863', '#E1E6EC', '#161D25', '#E9EEF3', '#9AA6B2', '#0B2A45', '#26313C', '#DCEBFA' );
	const BLUE   = '#2546C8';
	const YELLOW = '#F5CE3E';
	const GRAY   = '#333333';

	function palette_in( string $json ): array {
		$hit = array();
		foreach ( PALETTE as $hex ) {
			if ( false !== stripos( $json, $hex ) ) {
				$hit[] = $hex;
			}
		}
		if ( preg_match( '/rgba\(\s*0\s*,\s*168\s*,\s*107/', $json ) ) {
			$hit[] = 'rgba(0,168,107)';
		}
		return $hit;
	}

	// ── T · Las plantillas del kit ───────────────────────────────────────────────────────────────────────────────
	foreach ( array( 'containers', 'sections' ) as $variant ) {
		foreach ( Catalog::ids() as $id ) {
			$data = Catalog::load( $id, $variant );
			$json = json_encode( $data );
			check( ! palette_in( $json ), "T1 $variant/$id: lleva fija la paleta DIGITALÍSIMO: " . implode( ', ', palette_in( $json ) ) );
			check( array( 'template' => 'elementor_header_footer' ) === $data['page_settings'], "T2 $variant/$id: no pide la cabecera y el pie del sitio" );
		}
	}
	$corta = Catalog::load( 'venta-corta', 'containers' );
	check( false !== strpos( json_encode( $corta ), 'globals\/colors?id=accent' ) && false !== strpos( json_encode( $corta ), 'globals\/colors?id=primary' ), 'T3 la venta corta apunta a los globales primary y accent' );

	// ── Un sitio como plomerito, inventado: cabecera azul con botón amarillo, pie con cuatro garantías ───────────
	$header = array(
		array(
			'id'       => 'h1',
			'elType'   => 'container',
			'settings' => array( 'background_background' => 'classic', 'background_color' => BLUE ),
			'elements' => array(
				array( 'id' => 'h2', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Ferre Ensayo', 'title_color' => '#FFFFFF', 'typography_typography' => 'custom', 'typography_font_family' => 'Red Hat Display' ), 'elements' => array() ),
				array( 'id' => 'h3', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Buscar', 'background_color' => YELLOW, 'button_text_color' => '#1A1A1A', 'typography_typography' => 'custom', 'typography_font_family' => 'Inter' ), 'elements' => array() ),
			),
		),
	);
	$box    = static function ( $id, $title, $text, $icon ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'icon-box', 'settings' => array( 'title_text' => $title, 'description_text' => $text, 'selected_icon' => array( 'value' => $icon, 'library' => 'fa-solid' ), 'primary_color' => BLUE, 'title_color' => GRAY, 'description_color' => GRAY ), 'elements' => array() );
	};
	$footer = array(
		array(
			'id'       => 'f1',
			'elType'   => 'container',
			'settings' => array( 'background_background' => 'classic', 'background_color' => '#F4F4F4' ),
			'elements' => array(
				$box( 'f2', 'Marcas de calidad', 'Productos originales con garantía del proveedor.', 'fas fa-award' ),
				$box( 'f3', '30 días de garantía', 'Puedes devolver tus pedidos en los primeros 30 días.', 'far fa-calendar-minus' ),
				$box( 'f4', 'Envíos a todo México', 'Tus pedidos se envían seguros.', 'fas fa-box-open' ),
				$box( 'f5', '100% seguro', 'Tus pagos están protegidos.', 'fas fa-thumbs-up' ),
				array( 'id' => 'f6', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Ferre Ensayo</p>', 'text_color' => GRAY, 'typography_typography' => 'custom', 'typography_font_family' => 'Inter' ), 'elements' => array() ),
			),
		),
	);
	$docs = array( 'header' => array( $header ), 'footer' => array( $footer ), 'front' => array() );

	// ── K · Kit real (azul y amarillo): los papeles apuntan a sus globales ───────────────────────────────────────
	$kit_real = Brand::kit_from_settings(
		array(
			'system_colors'     => array(
				array( '_id' => 'primary', 'title' => 'Principal', 'color' => BLUE ),
				array( '_id' => 'secondary', 'title' => 'Secundario', 'color' => '#54595F' ),
				array( '_id' => 'text', 'title' => 'Texto', 'color' => GRAY ),
				array( '_id' => 'accent', 'title' => 'Acento', 'color' => YELLOW ),
			),
			'system_typography' => array(
				array( '_id' => 'primary', 'typography_font_family' => 'Red Hat Display' ),
				array( '_id' => 'text', 'typography_font_family' => 'Inter' ),
			),
		)
	);
	$real = Brand::resolve( $kit_real, $docs );
	check( BLUE === $real['colors']['primary']['color'] && 'primary' === $real['colors']['primary']['global'], 'K1 primary: el azul del sitio, como global «primary» del kit · ' . json_encode( $real['colors'] ) );
	check( YELLOW === $real['colors']['accent']['color'] && 'accent' === $real['colors']['accent']['global'], 'K2 accent: el amarillo de los botones, como global «accent» del kit' );
	check( 'Red Hat Display' === $real['fonts']['heading'] && 'Inter' === $real['fonts']['body'], 'K3 las fuentes del sitio · ' . json_encode( $real['fonts'] ) );
	check( '#FFFFFF' !== $real['on_accent'], 'K4 el texto sobre el amarillo es oscuro (se lee)' );
	check( false === $real['kit_factory'], 'K5 el kit no es de fábrica' );

	// ── F · Kit de fábrica pero cabecera y pie con colores reales: manda el diseño ──────────────────────────────
	$kit_factory = Brand::kit_from_settings(
		array(
			'system_colors'     => array(
				array( '_id' => 'primary', 'title' => 'Primary', 'color' => '#6EC1E4' ),
				array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => '#54595F' ),
				array( '_id' => 'text', 'title' => 'Text', 'color' => '#7A7A7A' ),
				array( '_id' => 'accent', 'title' => 'Accent', 'color' => '#61CE70' ),
			),
			'system_typography' => array(
				array( '_id' => 'primary', 'typography_font_family' => 'Roboto' ),
				array( '_id' => 'secondary', 'typography_font_family' => 'Roboto Slab' ),
				array( '_id' => 'text', 'typography_font_family' => 'Roboto' ),
			),
		)
	);
	$fac = Brand::resolve( $kit_factory, $docs );
	check( true === $fac['kit_factory'], 'F1 reconoce el kit de fábrica' );
	check( BLUE === $fac['colors']['primary']['color'] && '' === $fac['colors']['primary']['global'], 'F2 primary: el azul de la cabecera, literal (el kit de fábrica no lo tiene) · ' . json_encode( $fac['colors'] ) );
	check( YELLOW === $fac['colors']['accent']['color'], 'F3 accent: el amarillo del botón de la cabecera' );
	check( 'Red Hat Display' === $fac['fonts']['heading'] && 'Inter' === $fac['fonts']['body'], 'F4 las fuentes que se ven, no Roboto · ' . json_encode( $fac['fonts'] ) );
	check( 'diseño' === $fac['source'], 'F5 la fuente de la identidad es el diseño real' );
	$none = Brand::resolve( $kit_factory, array(), '#C0392B' );
	check( '#C0392B' === $none['colors']['primary']['color'] && 'logo' === $none['source'], 'F6 sin cabecera ni pie: el color del logo' );
	$nothing = Brand::resolve( $kit_factory, array() );
	check( ! in_array( $nothing['colors']['primary']['color'], array_merge( PALETTE, array( '#6EC1E4', '#61CE70' ) ), true ), 'F7 sin nada: un neutro, nunca la paleta DIGITALÍSIMO ni la de fábrica' );

	// ── A · La venta corta con la identidad del sitio ────────────────────────────────────────────────────────────
	foreach ( array( 'real' => $real, 'fábrica' => $fac ) as $case => $brand ) {
		foreach ( array( 'containers', 'sections' ) as $variant ) {
			$els  = Brand::apply( Catalog::load( 'venta-corta', $variant )['content'], $brand );
			$json = json_encode( $els );
			$look = Brand::look( $els, $brand, array_map( function ( $c ) { return $c['color']; }, $brand['kit_colors'] ) );
			check( ! $look['digitalisimo'] && ! palette_in( $json ), "A1 $case/$variant: ningún color DIGITALÍSIMO · " . implode( ',', $look['digitalisimo'] ) );
			check( in_array( BLUE, $look['colors'], true ) && in_array( YELLOW, $look['colors'], true ), "A2 $case/$variant: lleva el azul y el amarillo del sitio · " . implode( ',', $look['colors'] ) );
			check( $look['matches_site'], "A3 $case/$variant: look dice que coincide con el sitio" );
			check( in_array( 'Red Hat Display', $look['fonts'], true ) && ! in_array( 'Roboto', $look['fonts'], true ), "A4 $case/$variant: las fuentes del sitio · " . implode( ',', $look['fonts'] ) );
			check( 'real' !== $case || false !== strpos( $json, 'globals\/colors?id=primary' ), "A5 $case/$variant: con kit real, los colores siguen al kit (globales)" );
			check( 'fábrica' !== $case || false === strpos( $json, 'globals\/colors?id=' ), "A6 $case/$variant: con kit de fábrica, nada apunta a sus globales de fábrica" );
			$texts = array();
			array_walk_recursive(
				$els,
				function ( $v, $k ) use ( &$texts ) {
					if ( 'button_text_color' === $k ) {
						$texts[] = $v;
					}
				}
			);
			check( $texts && ! in_array( '#FFFFFF', $texts, true ), "A7 $case/$variant: el texto de los botones amarillos no es blanco · " . implode( ',', $texts ) );
		}
	}

	// ── S · La landing de la tienda ───────────────────────────────────────────────────────────────────────────────
	$products = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$products[] = array( 'id' => 300 + $i, 'name' => "Bomba de agua $i", 'url' => "https://ferre.test/producto/bomba-$i/", 'image' => array( 'id' => 900 + $i, 'url' => "https://ferre.test/wp-content/uploads/bomba-$i.png", 'alt' => '' ), 'regular' => 1000.0 * $i, 'from' => false );
	}
	$catalog = array( 'products' => $products, 'categories' => array(), 'total' => 12, 'scope' => 'all', 'scope_name' => '', 'url' => 'https://ferre.test/tienda/', 'top' => false );
	$site    = array(
		'name'            => 'Ferre Ensayo',
		'currency'        => 'MXN',
		'currency_symbol' => '$',
		'whatsapp'        => '5218710000000',
		'whatsapp_source' => 'el pie del sitio',
		'contact_url'     => '',
		'gateways'        => array( 'Transferencia bancaria', 'Tarjeta de crédito o débito' ),
		'shipping'        => array( array( 'title' => 'Envío a domicilio', 'method' => 'flat_rate', 'free' => false, 'condition' => '' ) ),
		'links'           => array( '/politica-de-reembolso/' => 'https://ferre.test/devoluciones/', '/carrito/' => 'https://ferre.test/carrito/' ),
		'benefits'        => Store::benefits( $docs ),
		'refund_line'     => 'Tienes 30 días para devolver tu producto.',
	);
	check( 4 === count( $site['benefits'] ) && 'Marcas de calidad' === $site['benefits'][0]['title'], 'S0 las garantías salen del pie del sitio · ' . json_encode( $site['benefits'] ) );
	$env   = array( 'timezone' => new DateTimeZone( 'America/Mexico_City' ), 'site_timezone' => new DateTimeZone( 'UTC' ), 'now' => strtotime( '2026-11-25 12:00:00 UTC' ) );
	$offer = Store::offer( array( 'percent' => 40, 'label' => 'Oferta navideña', 'ends' => '2026-12-24' ), $env );
	check( is_array( $offer ) && 40.0 === (float) $offer['percent'] && '2026-12-24 23:59' === $offer['ends'], 'S1 la oferta se valida' );
	foreach ( array( 'containers', 'sections' ) as $variant ) {
		$page = Store::page( $offer, $catalog, $site, $env + array( 'variant' => $variant ) );
		$els  = Brand::apply( $page['elements'], $fac );
		$json = json_encode( $els, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$text = implode( ' | ', Store::texts( $els ) );
		check( 12 === $page['shown'] && 12 === substr_count( $json, '"widgetType":"image"' ), "S2 $variant: los 12 productos con foto" );
		check( false !== strpos( $json, '<del>$12,000 MXN</del>' ) && false !== strpos( $json, '$7,200 MXN' ), "S3 $variant: precio normal tachado y 40 % menos" );
		check( 'https://ferre.test/tienda/' === $page['button_url'] && false === strpos( $json, '/carrito/' ), "S4 $variant: el botón lleva a la tienda, nunca al carrito" );
		check( ! Store::course_texts( $els ), "S5 $variant: ningún texto de cursos · " . implode( ',', Store::course_texts( $els ) ) );
		check( false !== strpos( $text, 'Marcas de calidad' ) && false !== strpos( $text, '30 días de garantía' ), "S6 $variant: las garantías del pie" );
		check( false !== strpos( $text, 'Transferencia bancaria' ) && false !== strpos( $text, 'Envío a domicilio' ), "S7 $variant: pagos y envíos reales" );
		check( false !== strpos( $text, '¿Puedo devolver un producto?' ) && false !== strpos( $text, 'Tienes 30 días' ), "S8 $variant: preguntas con la política real" );
		check( false !== strpos( $json, '"due_date":"2026-12-25 05:59"' ), "S9 $variant: el contador cierra a las 23:59 de la tienda (05:59 del sitio en UTC)" );
		check( ! palette_in( $json ) && false !== strpos( $json, BLUE ) && false !== strpos( $json, YELLOW ), "S10 $variant: con los colores del sitio" );
		$ids = array();
		preg_match_all( '/"id":"([0-9a-f]{7})"/', $json, $m );
		check( count( $m[1] ) === count( array_unique( $m[1] ) ) && count( $m[1] ) > 40, "S11 $variant: IDs únicos de siete hexadecimales" );
		check( 'Oferta navideña: 40 % de descuento en toda la tienda' === $page['title'], "S12 $variant: título" );
	}
	// Muchos productos: los más vendidos y las categorías.
	$many  = array( 'products' => array_slice( $products, 0, 8 ), 'categories' => array( array( 'name' => 'Bombas', 'url' => 'https://ferre.test/categoria/bombas/', 'image' => array( 'id' => 1, 'url' => 'https://ferre.test/b.png', 'alt' => '' ), 'count' => 30 ) ), 'total' => 80, 'scope' => 'all', 'scope_name' => '', 'url' => 'https://ferre.test/tienda/', 'top' => true );
	$page  = Store::page( $offer, $many, $site, $env + array( 'variant' => 'containers' ) );
	$text  = implode( ' | ', Store::texts( $page['elements'] ) );
	check( false !== strpos( $text, 'Los más vendidos' ) && false !== strpos( $text, 'Compra por categoría' ) && false !== strpos( $text, 'Bombas' ), 'S13 con muchos productos: los más vendidos y las categorías con imagen' );
	// Una categoría: el botón a la categoría.
	$cat  = array( 'products' => array_slice( $products, 0, 3 ), 'categories' => array(), 'total' => 3, 'scope' => 'category', 'scope_name' => 'Bombas', 'url' => 'https://ferre.test/categoria/bombas/', 'top' => false );
	$page = Store::page( Store::offer( array( 'percent' => '30%' ), $env ), $cat, $site, $env + array( 'variant' => 'containers' ) );
	check( 'https://ferre.test/categoria/bombas/' === $page['button_url'] && false !== strpos( $page['title'], 'en Bombas' ), 'S14 oferta de una categoría: el botón a la categoría' );
	// Errores de la oferta.
	foreach ( array( array( 'percent' => 0 ), array( 'percent' => 95 ), array( 'percent' => 40, 'headline' => 'Lo que vas a lograr' ), array( 'percent' => 40, 'ends' => '2020-01-01' ), array( 'percent' => 40, 'cupon' => 'X' ) ) as $bad ) {
		check( is_wp_error( Store::offer( $bad, $env ) ), 'S15 rechaza la oferta ' . json_encode( $bad, JSON_UNESCAPED_UNICODE ) );
	}
	// Sin garantías en el pie: las arma con los datos reales de la tienda.
	$bare = $site;
	unset( $bare['benefits'] );
	$page = Store::page( $offer, $catalog, $bare, $env + array( 'variant' => 'containers' ) );
	$text = implode( ' | ', Store::texts( $page['elements'] ) );
	check( false !== strpos( $text, 'Devoluciones' ) && false !== strpos( $text, 'Atención por WhatsApp' ) && false === strpos( $text, 'Marcas de calidad' ), 'S16 sin garantías publicadas: las de los datos reales de la tienda, nada inventado' );

	// ── E · create-page con store_offer ──────────────────────────────────────────────────────────────────────────
	$saved  = array();
	$create = function ( $title, $elements, $post_id, $template, $layout = 'site' ) use ( &$saved ) {
		$saved = array( 'title' => $title, 'elements' => $elements, 'post_id' => $post_id, 'template' => $template, 'layout' => $layout );
		return array( 'id' => 777, 'status' => 'draft', 'edit_url' => 'https://ferre.test/wp-admin/post.php?post=777&action=elementor', 'preview_url' => 'https://ferre.test/?page_id=777&preview=true', 'regenerated' => false, 'layout' => 'blank' === $layout ? 'elementor_canvas' : 'elementor_header_footer' );
	};
	$deps = array(
		'brand'   => $fac,
		'site'    => $site,
		'docs'    => $docs,
		'catalog' => function ( $category ) use ( $catalog ) {
			return '' === $category ? $catalog : new WP_Error( 'digitalisimo_sales_store_offer', 'No encontré la categoría' );
		},
	);
	$out = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40, 'label' => 'Oferta navideña' ) ), $create, $deps );
	check( is_array( $out ) && true === $out['ok'] && 'draft' === $out['status'], 'E1 crea el borrador con store_offer · ' . ( is_wp_error( $out ) ? $out->get_error_message() : '' ) );
	check( 'site' === $saved['layout'] && true === $out['check']['site_header_footer'], 'E2 con la cabecera y el pie del sitio' );
	check( true === $out['check']['ok'] && true === $out['look']['matches_site'] && ! $out['look']['digitalisimo'], 'E3 check y look confirman los colores y las fuentes del sitio · ' . json_encode( $out['check'] ) );
	check( 'https://ferre.test/tienda/' === $out['check']['main_button'] && false === $out['check']['main_button_cart'], 'E4 el botón principal va a la tienda' );
	check( 12 === $out['store_offer']['products_shown'] && 12 === $out['check']['products'], 'E5 informa los productos mostrados' );
	check( (bool) preg_grep( '/sigue cobrando el precio normal/', $out['review'] ), 'E6 avisa que la tienda sigue cobrando el precio normal (no lo cambia)' );
	check( 'Oferta navideña: 40 % de descuento en toda la tienda' === $saved['title'] && 'tienda' === $saved['template'], 'E7 título y marca de la página' );
	$out = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40 ), 'blank_page' => true ), $create, $deps );
	check( 'blank' === $saved['layout'] && false === $out['check']['site_header_footer'], 'E8 blank_page pide el lienzo vacío (sólo si la persona lo pide)' );
	$err = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40 ), 'template' => 'venta-corta' ), $create, $deps );
	check( is_wp_error( $err ) && 'digitalisimo_sales_invalid' === $err->get_error_code(), 'E9 store_offer con template se rechaza' );
	$err = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40, 'category' => 'Nada' ) ), $create, $deps );
	check( is_wp_error( $err ), 'E10 una categoría que no existe se rechaza' );
	$err = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 140 ) ), $create, $deps );
	check( is_wp_error( $err ), 'E11 un porcentaje fuera de rango se rechaza' );
	// Una plantilla del kit también sale con la identidad del sitio y con la cabecera y el pie.
	$out = Executor::run( 'create-page', array( 'template' => 'captacion', 'title' => 'Prueba', 'finish' => false ), $create, $deps );
	check( is_array( $out ) && ! palette_in( json_encode( $saved['elements'] ) ) && 'site' === $saved['layout'], 'E12 una plantilla del kit sale con los colores del sitio y su cabecera y pie' );

	echo "elements-sales-brand: {$GLOBALS['sb_count']} comprobaciones, todas bien.\n";
}
