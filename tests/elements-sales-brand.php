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

	function get_post_meta( $id, $key, $single = false ) {
		return $GLOBALS['sb_meta'][ (int) $id ][ $key ] ?? '';
	}
	function get_theme_mod( $name ) {
		return 'custom_logo' === $name && isset( $GLOBALS['sb_logo'] ) ? 900 : false;
	}
	function get_attached_file( $id ) {
		return 900 === (int) $id ? (string) $GLOBALS['sb_logo'] : '';
	}
	function wp_get_attachment_url( $id ) {
		return 'https://ferre.test/wp-content/uploads/logo.png';
	}
	function get_transient( $key ) {
		return $GLOBALS['sb_transients'][ $key ] ?? false;
	}
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['sb_transients'][ $key ] = $value;
		return true;
	}
	function url_to_postid( $url ) {
		return false !== strpos( (string) $url, 'devoluciones' ) ? 70 : 0;
	}
	function get_post( $id ) {
		return $GLOBALS['sb_posts'][ (int) $id ] ?? null;
	}
	// La consulta de los más vendidos: se anota y se contesta como WP_Query (ordenado por total_sales).
	function get_posts( $args ) {
		$GLOBALS['sb_queries'][] = $args;
		$sales = array();
		foreach ( (array) ( $GLOBALS['sb_meta'] ?? array() ) as $id => $m ) {
			$sales[ $id ] = (int) ( $m['total_sales'] ?? 0 );
		}
		arsort( $sales );
		return array_slice( array_keys( $sales ), 0, (int) $args['posts_per_page'] );
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
		'price'           => array( 'decimals' => 2, 'decimal_sep' => '.', 'thousand_sep' => ',', 'format' => '%1$s%2$s', 'trim_zeros' => false ),
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
		check( false !== strpos( $json, '<del>$12,000.00</del>' ) && false !== strpos( $json, '$7,200.00' ) && false === strpos( $json, 'MXN' ), "S3 $variant: precio de la tienda tachado y 40 % menos, con el formato de la tienda (sin agregar «MXN»)" );
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
		// Los bordes claros de las tarjetas y de las preguntas (no los botones, que pueden llevar un borde para distinguirse).
		$light_edges = 0;
		$dark_edges  = 0;
		$edges = static function ( array $list ) use ( &$edges, &$light_edges, &$dark_edges ) {
			foreach ( $list as $el ) {
				if ( 'button' !== ( $el['widgetType'] ?? '' ) && isset( $el['settings']['border_color'] ) ) {
					if ( '#E6E6E6' === $el['settings']['border_color'] ) {
						++$light_edges;
					} elseif ( GRAY === $el['settings']['border_color'] ) {
						++$dark_edges;
					}
				}
				$edges( (array) ( $el['elements'] ?? array() ) );
			}
		};
		$edges( $els );
		check( $light_edges > 0 && 0 === $dark_edges, "S17 $variant: los bordes claros de las tarjetas y las preguntas siguen claros (la corrección de contraste es sólo del texto) · $light_edges claros, $dark_edges oscuros" );
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
	check( '' === $saved['layout'] && true === $out['check']['site_header_footer'], 'E2 con la cabecera y el pie del sitio (lo normal en una página nueva)' );
	check( true === $out['check']['ok'] && true === $out['look']['matches_site'] && ! $out['look']['digitalisimo'], 'E3 check y look confirman los colores y las fuentes del sitio · ' . json_encode( $out['check'] ) );
	check( 'https://ferre.test/tienda/' === $out['check']['main_button'] && false === $out['check']['main_button_cart'], 'E4 el botón principal va a la tienda' );
	check( 12 === $out['store_offer']['products_shown'] && 12 === $out['check']['products'], 'E5 informa los productos mostrados' );
	check( (bool) preg_grep( '/todavía no aplica este descuento/', $out['review'] ), 'E6 avisa que la tienda todavía no aplica el descuento (no lo cambia)' );
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
	check( is_array( $out ) && ! palette_in( json_encode( $saved['elements'] ) ) && true === $out['check']['site_header_footer'], 'E12 una plantilla del kit sale con los colores del sitio y su cabecera y pie' );

	// ── G3 · Dentro del sitio: un solo pie, la barra de cierre no tapa una cabecera fija (grave 3 de la revisión) ──────
	$future = ( new DateTimeImmutable( '+20 days', new DateTimeZone( 'America/Mexico_City' ) ) )->format( 'Y-m-d' ) . ' 23:59';
	$kit_pie = 'no forma parte de Meta Platforms';
	$corta_in = array( 'template' => 'venta-corta', 'title' => 'Prueba', 'close_date' => $future );
	$sticky_docs = $docs;
	$sticky_docs['header'][0][0]['settings']['sticky'] = 'top';
	foreach ( array( 'containers', 'sections' ) as $variant ) {
		\Elementor\Plugin::$instance = (object) array( 'experiments' => new class( $variant ) {
			private $v;
			public function __construct( $v ) { $this->v = $v; }
			public function is_feature_active( $f ) { return 'container' === $f && 'containers' === $this->v; }
		} );
		$out = Executor::run( 'create-page', $corta_in, $create, array( 'docs' => $docs ) + $deps );
		$j   = json_encode( $saved['elements'], JSON_UNESCAPED_UNICODE );
		check( is_array( $out ) && false === strpos( $j, $kit_pie ) && true === $out['check']['site_header_footer'], "G3a $variant: con la cabecera y el pie del sitio, sin el pie propio de la plantilla (un solo pie)" );
		check( false !== strpos( $j, '"sticky":"top"' ), "G3b $variant: con una cabecera que no es fija, la barra de cierre sigue fija" );
		$out = Executor::run( 'create-page', $corta_in, $create, array( 'docs' => $sticky_docs ) + $deps );
		$j   = json_encode( $saved['elements'], JSON_UNESCAPED_UNICODE );
		check( is_array( $out ) && false === strpos( $j, '"sticky"' ) && false !== strpos( $j, 'Esta oferta termina' ), "G3b $variant: con la cabecera del sitio fija, la barra de cierre sigue pero ya no es fija (no la tapa)" );
		$out = Executor::run( 'create-page', $corta_in + array( 'blank_page' => true ), $create, array( 'docs' => $sticky_docs ) + $deps );
		$j   = json_encode( $saved['elements'], JSON_UNESCAPED_UNICODE );
		check( is_array( $out ) && false !== strpos( $j, $kit_pie ) && false !== strpos( $j, '"sticky":"top"' ) && false === $out['check']['site_header_footer'], "G3c $variant: en el lienzo vacío (pedido), el pie propio y la barra fija se quedan" );
		// Rehacer un borrador hecho en el lienzo: se queda en el lienzo (con su pie) si no se pide otra cosa.
		$out = Executor::run( 'create-page', $corta_in + array( 'post_id' => 55 ), function ( $t, $e, $p, $tpl, $l = '' ) use ( &$saved ) { $saved = array( 'elements' => $e, 'layout' => $l ); return array( 'id' => 55, 'status' => 'draft', 'edit_url' => '', 'preview_url' => '', 'regenerated' => true ); }, array( 'docs' => $docs, 'regenerable' => function () { return true; }, 'template_of' => function () { return 'elementor_canvas'; } ) + $deps );
		check( is_array( $out ) && '' === $saved['layout'] && false === $out['check']['site_header_footer'] && false !== strpos( json_encode( $saved['elements'], JSON_UNESCAPED_UNICODE ), $kit_pie ), "G3d $variant: rehacer un borrador del lienzo no le cambia la plantilla de página ni le quita su pie" );
	}
	\Elementor\Plugin::$instance = null;

	// ── R · Lo que encontró la revisión adversarial ─────────────────────────────────────────────────────────────
	$find = static function ( array $els, string $type ) use ( &$find ): array {
		$out = array();
		foreach ( $els as $el ) {
			if ( $type === ( $el['widgetType'] ?? '' ) ) {
				$out[] = $el;
			}
			$out = array_merge( $out, $find( (array) ( $el['elements'] ?? array() ), $type ) );
		}
		return $out;
	};
	// R1 · El contador de la portada se lee contra sus cajas, no contra la sección.
	$page = Store::page( $offer, $catalog, $site, $env + array( 'variant' => 'containers' ) );
	foreach ( array( 'real' => $real, 'fábrica' => $fac ) as $case => $brand ) {
		$cd  = $find( Brand::apply( $page['elements'], $brand ), 'countdown' )[0];
		$kit = array_map( function ( $c ) { return $c['color']; }, $brand['kit_colors'] );
		$col = function ( $key ) use ( $cd, $kit ) {
			$g = $cd['settings']['__globals__'][ $key ] ?? '';
			return '' !== $g ? $kit[ Brand::global_id( $g, 'colors' ) ] : Brand::hex( (string) $cd['settings'][ $key ] );
		};
		$box = $col( 'box_background_color' );
		check( Brand::contrast( $col( 'digits_color' ), $box ) >= 3 && Brand::contrast( $col( 'label_color' ), $box ) >= 3, "R1 $case: los números y las etiquetas del contador se leen en sus cajas · " . json_encode( array( $box, $col( 'digits_color' ), $col( 'label_color' ) ) ) );
	}
	// R2 · Una marca de un solo color: los botones no desaparecen en su sección.
	$mono_docs = array( 'header' => array( array( array( 'id' => 'm1', 'elType' => 'container', 'settings' => array( 'background_background' => 'classic', 'background_color' => '#1E3A8A' ), 'elements' => array( array( 'id' => 'm2', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'background_color' => '#1E3A8A' ), 'elements' => array() ) ) ) ) ) );
	$mono      = Brand::resolve( $kit_factory, $mono_docs );
	foreach ( $find( Brand::apply( $page['elements'], $mono ), 'button' ) as $btn ) {
		$bgc = Brand::hex( (string) ( $btn['settings']['background_color'] ?? '' ) );
		check( '' !== $bgc && Brand::contrast( $bgc, Brand::hex( (string) $btn['settings']['button_text_color'] ) ) >= 3, 'R2 un botón de una marca de un solo color se lee · ' . json_encode( $btn['settings'] ) );
	}
	$hero_btn = $find( Brand::apply( array( $page['elements'][0] ), $mono ), 'button' );
	$hb = end( $hero_btn )['settings'];
	check( Brand::contrast( Brand::hex( (string) $hb['background_color'] ), '#1E3A8A' ) >= 3 || ( 'solid' === ( $hb['border_border'] ?? '' ) && Brand::contrast( Brand::hex( (string) $hb['border_color'] ), '#1E3A8A' ) >= 3 ), 'R2 el botón de la portada se distingue de la portada de su mismo color (fondo o borde con contraste ≥ 3) · ' . json_encode( $hb ) );
	// R3 · Palabras de tienda no son textos de cursos.
	$tools = $products;
	$tools[0]['name'] = 'Módulo solar 100W';
	$tools[1]['name'] = 'Curso de agua: filtro de 5 etapas';
	$shop_site = $site;
	$shop_site['benefits'][0]['title'] = 'Facturamos tu compra';
	$p3 = Store::page( Store::offer( array( 'percent' => 40, 'headline' => 'Facturación incluida y transformación total de tu cocina' ), $env ), array( 'products' => $tools ) + $catalog, $shop_site, $env + array( 'variant' => 'containers' ) );
	check( ! Store::course_texts( $p3['elements'] ), 'R3 «Módulo solar», «Facturamos tu compra» o «transformación» no son textos de cursos · ' . implode( ',', Store::course_texts( $p3['elements'] ) ) );
	check( (bool) Store::course_texts( Catalog::load( 'venta-corta', 'containers' )['content'] ), 'R3 la venta corta (de cursos) sí los tiene' );
	// R4 · Sin identidad en el sitio, look no dice que coincide y check no pide rehacer.
	$nada = Brand::resolve( $kit_factory, array() );
	$out  = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40 ) ), $create, array( 'brand' => $nada ) + $deps );
	check( false === $out['look']['matches_site'] && false === $out['look']['site_identity'] && true === $out['check']['ok'] && preg_grep( '/No digas que usa los colores del sitio/', $out['review'] ), 'R4 sin colores de marca en el sitio no se afirma que coinciden · ' . json_encode( $out['look'] ) );
	// R5 · Los más vendidos son los de más ventas: UNA consulta ordenada por total_sales (medio 5), y sin ventas no se
	// dice «los más vendidos».
	$GLOBALS['sb_queries'] = array();
	$GLOBALS['sb_meta']    = array( 10 => array( 'total_sales' => '3' ), 11 => array( 'total_sales' => '40' ), 12 => array( 'total_sales' => '0' ), 13 => array( 'total_sales' => '7' ) );
	$r = Store::best_sellers( 'bombas', 3 );
	$q = $GLOBALS['sb_queries'];
	check( 1 === count( $q ) && 'total_sales' === $q[0]['meta_key'] && 'DESC' === ( $q[0]['orderby']['meta_value_num'] ?? '' ) && 3 === $q[0]['posts_per_page'] && 'product' === $q[0]['post_type'], 'R5 una sola consulta ordenada por total_sales · ' . json_encode( $q ) );
	check( 'NOT IN' === $q[0]['tax_query'][0]['operator'] && in_array( 'exclude-from-catalog', $q[0]['tax_query'][0]['terms'], true ) && array( 'bombas' ) === $q[0]['tax_query'][1]['terms'], 'R5 sin los ocultos del catálogo y sólo de la categoría' );
	check( array( 11, 13, 10 ) === $r['ids'] && true === $r['sold'], 'R5 los más vendidos por sus ventas · ' . json_encode( $r ) );
	$GLOBALS['sb_meta'] = array();
	check( false === Store::best_sellers( '', 3 )['sold'], 'R5 sin ventas no son «los más vendidos»' );
	// R7 · La política de devoluciones hecha con el constructor: su texto visible, nunca el JSON guardado (medio 6).
	$GLOBALS['sb_posts'] = array( 70 => (object) array( 'ID' => 70, 'post_content' => '' ) );
	$GLOBALS['sb_meta']  = array( 70 => array( '_elementor_data' => json_encode( array( array( 'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => array( 'content_width' => 'boxed' ), 'elements' => array( array( 'id' => 'e5f6a7b', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Tienes 30 días para devolver tu compra. Escríbenos.</p>' ), 'elements' => array() ) ) ) ) ) ) );
	$line = Store::refund_line( 'https://ferre.test/devoluciones/' );
	check( 'Tienes 30 días para devolver tu compra.' === $line, 'R7 la primera frase visible de la política hecha con el constructor · ' . $line );
	$GLOBALS['sb_meta'] = array();

	// R8 · El análisis del logo se guarda por su URL y su fecha de modificación (medio 7): una vez por logo.
	$logo = sys_get_temp_dir() . '/sb-logo-' . getmypid() . '.png';
	if ( function_exists( 'imagecreatetruecolor' ) ) {
		$im = imagecreatetruecolor( 40, 40 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 30, 40 ) );
		imagepng( $im, $logo );
		if ( PHP_VERSION_ID < 80000 ) {
			imagedestroy( $im ); // Desde PHP 8 la libera el recolector (y en 8.5 avisa de obsoleta).
		}
	} else {
		file_put_contents( $logo, 'x' );
	}
	$GLOBALS['sb_transients'] = array();
	$GLOBALS['sb_logo']       = $logo;
	$first = Brand::logo_color();
	check( 1 === count( $GLOBALS['sb_transients'] ) && 0 === strpos( (string) key( $GLOBALS['sb_transients'] ), 'digi_sales_logo_' ), 'R8 el análisis del logo queda guardado · ' . json_encode( $GLOBALS['sb_transients'] ) );
	check( ! function_exists( 'imagecreatetruecolor' ) || Brand::vivid( $first ), 'R8 el color del logo (con GD) · ' . $first );
	$k = (string) key( $GLOBALS['sb_transients'] );
	$GLOBALS['sb_transients'][ $k ] = '#123ABC';
	check( '#123ABC' === Brand::logo_color(), 'R8 la segunda vez sale de lo guardado, sin volver a abrir la imagen' );
	touch( $logo, time() + 120 );
	clearstatcache();
	Brand::logo_color();
	check( 2 === count( $GLOBALS['sb_transients'] ), 'R8 si el logo cambia (otra fecha de modificación), se vuelve a analizar' );
	@unlink( $logo );

	// R6 · El aviso del precio no dice «precio normal» de algo que puede estar rebajado.
	check( (bool) preg_grep( '/todavía no aplica este descuento/', $page['warnings'] ), 'R6 el aviso del precio de la tienda' );

	// ── P · El precio anunciado es lo que cobraría la tienda (grave 2 de la revisión) ─────────────────────────────
	// P1 · El formato de la tienda: símbolo después, separadores de Europa, sin decimales en cero.
	$eu = array( 'currency_symbol' => '€', 'price' => array( 'decimals' => 2, 'decimal_sep' => ',', 'thousand_sep' => '.', 'format' => '%2$s&nbsp;%1$s', 'trim_zeros' => true ) );
	check( '1.234,50 €' === \Digitalisimo\Elements\Sales\Site::price( 1234.5, $eu ) && '7.200 €' === \Digitalisimo\Elements\Sales\Site::price( 7200.0, $eu ), 'P1 el precio con el formato de la tienda · ' . \Digitalisimo\Elements\Sales\Site::price( 1234.5, $eu ) );
	check( '$199' === \Digitalisimo\Elements\Sales\Site::price( 199.0, array( 'currency_symbol' => '$', 'price' => array( 'decimals' => 0 ) ) ), 'P1 sin decimales si la tienda no los usa' );
	// P2 · Un producto ya rebajado: el descuento sobre lo que cobra hoy, eso es lo tachado, y se dice.
	$sale_cat = $catalog;
	$sale_cat['products'] = array( array( 'current' => 700.0, 'on_sale' => true ) + $products[0], array( 'current' => 2000.0, 'on_sale' => false ) + $products[1] );
	$sale_cat['products'][0]['regular'] = 1000.0;
	$sp = Store::page( $offer, $sale_cat, $site, $env + array( 'variant' => 'containers' ) );
	$sj = json_encode( $sp['elements'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	check( false !== strpos( $sj, '<del>$700.00</del>' ) && false !== strpos( $sj, '$420.00' ) && false === strpos( $sj, '$600.00' ), 'P2 ya rebajado a 700: tacha 700 y anuncia 420 (40 % sobre lo que cobra hoy)' );
	check( (bool) preg_grep( '/Ya estaban rebajados en la tienda: Bomba de agua 1\./u', $sp['warnings'] ), 'P2 se dice qué productos ya estaban rebajados' );
	// P3 · Qué productos no coinciden, con lo que cobra la tienda y lo que anuncia la landing; nunca «subir».
	check( (bool) preg_grep( '/Bomba de agua 1 \(la tienda cobra \$700\.00; la landing anuncia \$420\.00\); Bomba de agua 2 \(la tienda cobra \$2,000\.00; la landing anuncia \$1,200\.00\)/u', $sp['warnings'] ) && ! preg_grep( '/sub(e|ir)/iu', $sp['warnings'] ), 'P3 dice qué productos no coinciden · ' . json_encode( $sp['warnings'], JSON_UNESCAPED_UNICODE ) );
	check( 2 === count( $sp['mismatch'] ) && '$420.00' === $sp['mismatch'][0]['landing'], 'P3 la lista va también en la respuesta' );

	// ── C · Contraste WCAG AA en todas las paletas (bloqueo 1 de la revisión) ───────────────────────────────────────
	// Un recorrido propio de la prueba (no el de Brand): cada texto contra el fondo donde se pinta. Botones, cifras,
	// iconos y títulos grandes: 3; el resto: 4.5.
	$kitset = static function ( array $colors ) {
		$sys = array();
		foreach ( $colors as $id => $hex ) {
			$sys[] = array( '_id' => $id, 'title' => $id, 'color' => $hex );
		}
		return Brand::kit_from_settings( array( 'system_colors' => $sys ) );
	};
	$eff = static function ( array $s, string $key, array $kit ): string {
		$g = $s['__globals__'][ $key ] ?? '';
		return '' !== $g ? Brand::resolve_global( $g, $kit ) : Brand::hex( (string) ( $s[ $key ] ?? '' ) );
	};
	$audit = static function ( array $els, string $bg, array $kit, array &$bad ) use ( &$audit, $eff ) {
		foreach ( $els as $e ) {
			$s    = (array) ( $e['settings'] ?? array() );
			$here = $bg;
			if ( 'widget' !== $e['elType'] && in_array( $s['background_background'] ?? '', array( 'classic', 'gradient' ), true ) && '' !== $eff( $s, 'background_color', $kit ) ) {
				$here = $eff( $s, 'background_color', $kit );
			}
			$w     = (string) ( $e['widgetType'] ?? '' );
			$large = static function () use ( $s ) {
				$size = (float) ( $s['typography_font_size']['size'] ?? 0 );
				return $size >= 24 || ( $size >= 18.66 && (int) ( $s['typography_font_weight'] ?? 400 ) >= 700 ) || ( ! $size && in_array( $s['header_size'] ?? 'h2', array( 'h1', 'h2', 'h3' ), true ) );
			};
			$pairs = array();
			if ( 'button' === $w ) {
				$pairs[] = array( 'button_text_color', $eff( $s, 'background_color', $kit ) ?: $here, 4.5 );
				// El botón se distingue de su sección: su fondo o su borde con contraste ≥ 3.
				$bb = $eff( $s, 'background_color', $kit );
				$be = 'solid' === ( $s['border_border'] ?? '' ) ? $eff( $s, 'border_color', $kit ) : '';
				if ( '' !== $bb && Brand::contrast( $bb, $here ) < 3 && ( '' === $be || Brand::contrast( $be, $here ) < 3 ) ) {
					$bad[] = sprintf( 'button.fondo %s sobre %s = %.2f sin borde', $bb, $here, Brand::contrast( $bb, $here ) );
				}
			} elseif ( 'countdown' === $w ) {
				$box     = $eff( $s, 'box_background_color', $kit ) ?: $here;
				$pairs[] = array( 'digits_color', $box, 3 );
				$pairs[] = array( 'label_color', $box, 4.5 );
			} elseif ( 'heading' === $w ) {
				$pairs[] = array( 'title_color', $here, $large() ? 3 : 4.5 );
			} elseif ( 'accordion' === $w ) {
				$pairs[] = array( 'title_color', $eff( $s, 'title_background', $kit ) ?: $here, 4.5 );
				$pairs[] = array( 'content_color', $eff( $s, 'content_background_color', $kit ) ?: $here, 4.5 );
			} elseif ( in_array( $w, array( 'text-editor', 'icon-list' ), true ) ) {
				$pairs[] = array( 'text_color', $here, 4.5 );
			} elseif ( 'icon-box' === $w ) {
				$pairs[] = array( 'title_color', $here, 4.5 );
				$pairs[] = array( 'description_color', $here, 4.5 );
				$pairs[] = array( 'primary_color', $here, 3 );
			}
			foreach ( $pairs as $p ) {
				$c = $eff( $s, $p[0], $kit );
				if ( '' !== $c && '' !== $p[1] && Brand::contrast( $c, $p[1] ) + 1e-9 < $p[2] ) {
					$bad[] = sprintf( '%s.%s %s sobre %s = %.2f', $w, $p[0], $c, $p[1], Brand::contrast( $c, $p[1] ) );
				}
			}
			$audit( (array) ( $e['elements'] ?? array() ), $here, $kit, $bad );
		}
	};
	$palettes = array(
		'fábrica'           => array( $kitset( Brand::FACTORY_COLORS ), false ),
		'negro y gris'      => array( $kitset( array( 'primary' => '#000000', 'secondary' => '#333333', 'text' => '#555555', 'accent' => '#111111' ) ), true ),
		'pastel'            => array( $kitset( array( 'primary' => '#A8E6CF', 'secondary' => '#DCEDC1', 'text' => '#BBBBBB', 'accent' => '#FFD3B6' ) ), true ),
		'DIGITALÍSIMO'      => array( $kitset( array( 'primary' => '#1592DA', 'secondary' => '#0F141A', 'text' => '#4E5863', 'accent' => '#00A86B' ) ), true ),
		'morado y naranja'  => array( $kitset( array( 'primary' => '#7B2CBF', 'secondary' => '#5A5A5A', 'text' => '#2B2B2B', 'accent' => '#FF8C1A' ) ), true ),
		'azul y amarillo'   => array( $kitset( array( 'primary' => '#2546C8', 'secondary' => '#54595F', 'text' => '#333333', 'accent' => '#F5CE3E' ) ), true ),
		'amarillo claro'    => array( $kitset( array( 'primary' => '#FFD700', 'secondary' => '#FFE680', 'text' => '#AAAAAA', 'accent' => '#FFF3B0' ) ), true ),
	);
	$store_page = Store::page( $offer, $catalog, $site, $env + array( 'variant' => 'containers' ) );
	foreach ( $palettes as $name => $case ) {
		$brand = Brand::resolve( $case[0], array() );
		check( $case[1] === ( 'ninguna' !== $brand['source'] ), "C0 $name: " . ( $case[1] ? 'el sitio tiene identidad (no «ninguna»)' : 'sin identidad' ) . " · {$brand['source']}" );
		$kitc = $brand['kit_colors'];
		foreach ( array( 'tienda' => $store_page['elements'], 'venta corta' => Catalog::load( 'venta-corta', 'containers' )['content'], 'captación (secciones)' => Catalog::load( 'captacion', 'sections' )['content'] ) as $what => $els ) {
			$done = Brand::apply( $els, $brand );
			$bad  = array();
			$audit( $done, '#FFFFFF', $kitc, $bad );
			check( ! $bad, "C1 $name/$what: todo texto pasa WCAG AA · " . implode( ' | ', array_slice( array_unique( $bad ), 0, 5 ) ) );
			$look = Brand::look( $done, $brand, array_map( function ( $c ) { return $c['color']; }, $kitc ) );
			check( ! $look['contrast'], "C2 $name/$what: look no ve problemas de contraste · " . implode( ' | ', array_slice( $look['contrast'], 0, 3 ) ) );
			// C6 · Ningún color de la página queda casi igual a uno de la paleta DIGITALÍSIMO (salvo que sea del sitio): la
			// prueba real vio #F7F7F7 a 4.6 de #F5F8FB.
			$near = array();
			foreach ( $look['colors'] as $hex ) {
				foreach ( PALETTE as $pal ) {
					$d = sqrt( array_sum( array_map( function ( $a, $b ) { return ( hexdec( $a ) - hexdec( $b ) ) ** 2; }, str_split( substr( $hex, 1 ), 2 ), str_split( substr( $pal, 1 ), 2 ) ) ) );
					if ( $d < 7 && 'DIGITALÍSIMO' !== $name ) {
						$near[] = "{$hex}≈{$pal}";
					}
				}
			}
			check( ! $near, "C6 $name/$what: ningún color casi igual a uno de la paleta DIGITALÍSIMO · " . implode( ', ', array_unique( $near ) ) );
			// C7 · Los enlaces de los textos llevan color (el de la marca o, si no se lee, blanco o negro), nunca el del navegador.
			foreach ( $find( $done, 'text-editor' ) as $te ) {
				if ( false !== strpos( (string) ( $te['settings']['editor'] ?? '' ), '<a ' ) ) {
					check( '' !== ( $te['settings']['link_color'] ?? '' ) || isset( $te['settings']['__globals__']['link_color'] ), "C7 $name/$what: un texto con enlaces tiene el color de sus enlaces" );
				}
			}
			check( ! $case[1] || $look['matches_site'], "C3 $name/$what: look reconoce los colores del sitio (también la paleta DIGITALÍSIMO cuando es la del sitio, y el pastel aunque no alcance contraste en el texto) · " . json_encode( array( $look['digitalisimo'], $look['brand_used'] ) ) );
		}
	}
	// C8 · Detalles de la verificación: el texto sobre el acento con blanco o negro, el de más contraste (#00A86B:
	// negro 6.8, no blanco 3.08), y el botón #333 sobre la portada negra con un borde que se distingue.
	$dg      = Brand::resolve( $palettes['DIGITALÍSIMO'][0], array() );
	$dg_btns = $find( Brand::apply( $store_page['elements'], $dg ), 'button' );
	$green   = array_values( array_filter( $dg_btns, function ( $b ) use ( $dg ) { return isset( $b['settings']['__globals__']['background_color'] ) || '#00A86B' === Brand::hex( (string) ( $b['settings']['background_color'] ?? '' ) ); } ) );
	check( $green && '#000000' === $green[0]['settings']['button_text_color'] && '#000000' === $dg['on_accent'], 'C8 sobre el verde #00A86B el texto es negro (6.8), no blanco (3.08) · ' . json_encode( $green ? $green[0]['settings'] : null ) );
	$mono_brand = Brand::resolve( $palettes['negro y gris'][0], array() );
	$mono_hero  = $find( Brand::apply( array( $store_page['elements'][0] ), $mono_brand ), 'button' );
	$mh         = end( $mono_hero )['settings'];
	check( '#333333' === $eff( $mh, 'background_color', $mono_brand['kit_colors'] ) && 'solid' === ( $mh['border_border'] ?? '' ) && Brand::contrast( Brand::hex( (string) $mh['border_color'] ), '#000000' ) >= 3, 'C8 el botón #333 sobre la portada negra lleva un borde que se distingue · ' . json_encode( $mh ) );

	// C4 · Un botón con el texto del color de su fondo: look lo ve y check.ok no puede ser verdadero.
	$broken = array( array( 'id' => 'x1', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'text' => 'Ver', 'background_color' => '#222222', 'button_text_color' => '#222222' ), 'elements' => array() ) );
	$look   = Brand::look( $broken, $fac, array() );
	check( (bool) $look['contrast'], 'C4 look ve el botón ilegible' );
	$out = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40 ) ), function ( $t, $e, $p, $tpl, $l = 'site' ) use ( $create, $broken ) { return $create( $t, $broken, $p, $tpl, $l ); }, $deps );
	check( true === $out['check']['contrast_ok'] && true === $out['check']['ok'], 'C4 lo que se guardó pasó por el contraste (check mide lo guardado)' );
	// C5 · El negro y gris no es «sin colores propios».
	$mono_kit = Brand::resolve( $palettes['negro y gris'][0], array() );
	$out      = Executor::run( 'create-page', array( 'store_offer' => array( 'percent' => 40 ) ), $create, array( 'brand' => $mono_kit ) + $deps );
	check( 'kit' === $mono_kit['source'] && ! preg_grep( '/no tiene colores de marca propios/', $out['review'] ) && true === $out['look']['site_identity'] && true === $out['check']['ok'], 'C5 un sitio en negro y gris tiene identidad: no se le dice que no tiene colores propios · ' . json_encode( $out['check'] ) );

	echo "elements-sales-brand: {$GLOBALS['sb_count']} comprobaciones, todas bien.\n";
}
