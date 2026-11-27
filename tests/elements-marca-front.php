<?php
/**
 * Marca propia, fase 2 (docs/MARCA_PROPIA.md): la capa del front, sin WordPress (dobles mínimos).
 * - F1 una página con assets del núcleo, configuración en línea, estilos, atributos `data-elementor-*`, el lightbox
 *   (`#elementor-action`, también en `data-e-action-hash`), el formulario y el CSS generado queda sin «elementor» salvo
 *   el contenido del cliente: la imagen `elementor-banner.jpg`, un texto que habla de Elementor, un enlace a otra página
 *   y el schema `ld+json` salen intactos;
 * - F2 todo o nada: si la página pide un asset del núcleo sin copia en el espejo, sale sin transformar;
 * - F3 la capa sólo corre con la marca propia en vigor y con el espejo;
 * - F4 el CSS generado se copia transformado (y se rehace cuando el original cambia) y las copias se borran con la caché;
 * - F5 los fragmentos de la lista cerrada (REST y carrito) y los alias: `admin-ajax.php?action=digitalisimo_<x>` sólo
 *   para las acciones del front, `/digitalisimo-pro/v1/…` → `/elementor-pro/v1/…`.
 * Falla con el código anterior: no existe `modules/digitalisimo-marca/class-front-layer.php`.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $no, $str, $file, $line ) {
			echo "FALLA aviso de PHP: $str ($file:$line)\n";
			$GLOBALS['fails']++;
			return true;
		}
	);
	$GLOBALS['fails']  = 0;
	$GLOBALS['checks'] = 0;
	function check( $ok, $what, $detail = '' ) {
		$GLOBALS['checks']++;
		if ( $ok ) {
			echo "ok · $what\n";
		} else {
			$GLOBALS['fails']++;
			echo "FALLA · $what" . ( '' !== $detail ? ' · ' . substr( is_string( $detail ) ? $detail : json_encode( $detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 0, 1200 ) : '' ) . "\n";
		}
	}
	$tmp = sys_get_temp_dir() . '/digi-marca-front-' . getmypid();
	$GLOBALS['filters'] = array();
	$GLOBALS['options'] = array();
	function add_filter( $t, $cb, $p = 10, $a = 1 ) {
		$GLOBALS['filters'][ $t ][] = $cb;
		return true;
	}
	function add_action( $t, $cb, $p = 10, $a = 1 ) {
		return add_filter( $t, $cb, $p, $a );
	}
	function apply_filters( $t, $v, ...$r ) {
		foreach ( $GLOBALS['filters'][ $t ] ?? array() as $cb ) {
			$v = call_user_func( $cb, $v, ...$r );
		}
		return $v;
	}
	function get_option( $k, $d = false ) {
		return $GLOBALS['options'][ $k ] ?? $d;
	}
	function is_multisite() {
		return false;
	}
	function did_action( $t ) {
		return 1;
	}
	function wp_get_upload_dir() {
		return array( 'basedir' => $GLOBALS['tmp'] . '/uploads' );
	}
	function wp_mkdir_p( $d ) {
		return is_dir( $d ) || mkdir( $d, 0777, true );
	}
	$GLOBALS['tmp'] = $tmp;
	final class Digitalisimo_Elements_Core {
		public static $bundled = true;
		public static function bundled() {
			return self::$bundled;
		}
	}
	$el = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-marca';
	if ( ! is_file( $el . '/class-front-layer.php' ) ) {
		echo "FALLA no existe class-front-layer.php (código anterior)\n";
		exit( 1 );
	}
	foreach ( array( 'class-map.php', 'class-addons.php', 'class-toggle.php', 'class-transform.php', 'class-front-layer.php', 'class-exports.php', 'class-editor-layer.php' ) as $f ) {
		require $el . '/' . $f;
	}
	use Digitalisimo\Elements\Marca\Front_Layer;
	use Digitalisimo\Elements\Marca\Toggle;
	use Digitalisimo\Elements\Marca\Transform;

	$p    = 'https://ensayo.test/wp-content/plugins/digitalisimo-elements/';
	$page = '<!doctype html><html><head>'
		. '<link rel="stylesheet" id="elementor-frontend-css" href="' . $p . 'elementor/assets/css/frontend.min.css?ver=4.3.5" media="all">'
		. '<link rel="stylesheet" id="elementor-icons-css" href="' . $p . 'elementor/assets/lib/eicons/css/elementor-icons.min.css?ver=5" media="all">'
		. '<link rel="stylesheet" id="elementor-post-12-css" href="https://ensayo.test/wp-content/uploads/elementor/css/post-12.css?ver=1">'
		. '<style id="x-inline">.elementor-12 .elementor-element.elementor-element-abc{color:red}/* elementor */</style>'
		. '<script type="application/ld+json">{"headline":"Guía de Elementor","@type":"Article"}</script>'
		. '<script id="elementor-frontend-js-before">var elementorFrontendConfig = {"urls":{"assets":"https:\/\/ensayo.test\/wp-content\/plugins\/digitalisimo-elements\/elementor\/assets\/"},"kit":{}};</script>'
		. '<script>wp.i18n.setLocaleData( { "": {} }, "elementor" ); jQuery(window).on("elementor/frontend/init",function(){});</script>'
		. '<script id="elementor-frontend-js-translations">( function( domain, translations ) { var localeData = translations.locale_data[ domain ] || translations.locale_data.messages; wp.i18n.setLocaleData( localeData, domain ); } )( "elementor-pro", { "locale_data": { "messages": { "": { "lang": "es_MX" } } } } );</script>'
		. '<script id="elementor-frontend-js" src="' . $p . 'elementor/assets/js/frontend.min.js?ver=4.3.5"></script>'
		. '</head><body class="home elementor-default elementor-kit-5 elementor-page elementor-page-12">'
		. '<div data-elementor-type="wp-page" data-elementor-id="12" class="elementor elementor-12" data-elementor-post-type="elementor_library">'
		. '<div class="elementor-element elementor-element-abc e-con" data-id="abc" data-element_type="container">'
		. '<a href="https://ensayo.test/wp-content/uploads/2026/11/elementor-banner.jpg" data-elementor-open-lightbox="yes" data-e-action-hash="#elementor-action%3Aaction%3Dlightbox"><img src="https://ensayo.test/wp-content/uploads/2026/11/elementor-banner.jpg" class="attachment-full" alt="Banner de Elementor"></a>'
		. '<a href="#elementor-action%3Aaction%3Dpopup%3Aopen">Abrir</a>'
		. '<p>Este artículo explica Elementor y la clase elementor-widget.</p>'
		. '<a href="https://ensayo.test/elementor-tips/">Consejos</a>'
		. '<form class="elementor-form" method="post"><input type="hidden" name="action" value="elementor_pro_forms_send_form"><input type="hidden" name="form_id" value="elementor-x"></form>'
		. '</div></div></body></html>';

	// F1 -----------------------------------------------------------------------------------------------------------------
	$out  = Transform::html( $page );
	$keep = array( 'elementor-banner.jpg', 'Banner de Elementor', 'Este artículo explica Elementor y la clase elementor-widget.', 'https://ensayo.test/elementor-tips/', '{"headline":"Guía de Elementor","@type":"Article"}', 'value="elementor-x"' );
	foreach ( $keep as $k ) {
		check( false !== strpos( $out, $k ), "F1 queda intacto: $k" );
	}
	check( 0 === Transform::count( str_replace( $keep, '', $out ) ), 'F1 fuera del contenido del cliente no queda «elementor»', $out );
	foreach ( array( 'marca/motor/css/frontend.min.css?ver=4.3.5', 'marca/motor/lib/eicons/css/dg-icons.min.css', 'uploads/digitalisimo-elements/css/post-12.css', 'id="dg-frontend-css"', '.dg-12 .dg-element.dg-element-abc', 'var digitalisimoFrontendConfig', 'marca\/motor\/', '"dg" );', ')( "dg-pro", {', 'id="dg-frontend-js-translations"', 'digitalisimo/frontend/init', 'class="home dg-default dg-kit-5 dg-page dg-page-12"', 'data-dg-type="wp-page"', 'data-dg-post-type="digitalisimo_library"', 'data-dg-open-lightbox="yes"', 'data-e-action-hash="#dg-action%3Aaction', 'href="#dg-action%3Aaction%3Dpopup', 'value="digitalisimo_pro_forms_send_form"', 'class="dg-form"' ) as $want ) {
		check( false !== strpos( $out, $want ), "F1 lleva «{$want}»" );
	}

	$tpl = Transform::html( '<script type="text/template" id="tmpl-elementor-panel"><div class="elementor-panel">{{ title }}</div></script>' );
	check( '<script type="text/template" id="tmpl-dg-panel"><div class="dg-panel">{{ title }}</div></script>' === $tpl, 'F1 las plantillas del editor (text/template) se transforman como HTML', $tpl );
	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $v ) {
			return json_encode( $v );
		}
	}
	$ajax = json_decode( Front_Layer::ajax_buffer( json_encode( array( 'success' => true, 'data' => array( 'render' => '<div class="elementor-widget">Elementor</div>', 'id' => 'elementor_library', 'elements' => array( array( 'settings' => array( 'title' => 'Guía de Elementor', 'css' => '.elementor-x{}' ) ) ) ) ) ) ), true );
	// Con el paquete transformado el JS del editor pide los nombres nuevos (D-12): los identificadores salen como los pide
	// (`digitalisimo_library`), el texto del HTML y el contenido de los elementos, igual.
	check( '<div class="dg-widget">Elementor</div>' === $ajax['data']['render'] && 'digitalisimo_library' === $ajax['data']['id'], 'F1 AJAX del editor: el HTML y los identificadores como los pide el JS transformado', $ajax );
	check( array( array( 'settings' => array( 'title' => 'Guía de Elementor', 'css' => '.elementor-x{}' ) ) ) === $ajax['data']['elements'], 'F1 AJAX del editor: el contenido de los elementos no cambia', $ajax );
	// La configuración del editor (`var X = {json}`): el nombre y los datos con las reglas, el contenido igual.
	$cfg = Transform::js( 'var ElementorConfig = {"ajax":{"action":"elementor_ajax"},"document":{"elements":[{"settings":{"title":"Elementor"}}],"urls":{"preview":"https:\/\/ensayo.test\/?p=1&elementor-preview=1"}},"selectors":{"{{WRAPPER}} .elementor-heading-title":"color: {{VALUE}}"}};' );
	check( 0 === strpos( $cfg, 'var DigitalisimoConfig = ' ) && false !== strpos( $cfg, '"digitalisimo_ajax"' ) && false !== strpos( $cfg, '"title":"Elementor"' ) && false !== strpos( $cfg, 'dg-preview=1' ) && false !== strpos( $cfg, '{{WRAPPER}} .dg-heading-title' ), 'F1 configuración del editor como datos: nombre, acción, vista previa y selectores; el contenido igual', $cfg );
	// El editor transformado pide los espacios REST enteros (`digitalisimo/v1`) o armados por partes (`dg/v1`).
	$ns = array( 'elementor/v1' => Transform::str( 'elementor/v1' ), 'elementor-pro/v1' => Transform::str( 'elementor-pro/v1' ) );
	check( '/elementor/v1/globals' === \Digitalisimo\Elements\Marca\Editor_Layer::route_in( '/dg/v1/globals', $ns ) && '/elementor/v1/globals' === \Digitalisimo\Elements\Marca\Editor_Layer::route_in( '/digitalisimo/v1/globals', $ns ) && '/elementor-pro/v1/x' === \Digitalisimo\Elements\Marca\Editor_Layer::route_in( '/dg-pro/v1/x', $ns ), 'F1 rutas del editor: enteras y por partes' );
	// Una etiqueta de plantilla con un bloque `<# … #>` dentro.
	$tpl2 = Transform::template_html( '<input id="elementor-control-top-{{{ data._cid }}}" <# if ( elementor.x ) { #>checked<# } #>>' );
	check( '<input id="dg-control-top-{{{ data._cid }}}" <# if ( digitalisimo.x ) { #>checked<# } #>>' === $tpl2, 'F1 plantilla con bloque dentro de la etiqueta', $tpl2 );
	// Estilos en línea, atributos de evento y etiquetas con nombre dinámico en las plantillas del editor.
	$tpl3 = Transform::template_html( '<div class="elementor-x" style="--elementor-cols: {{ data.n }}" onclick="window.dispatchEvent( new CustomEvent( \'elementor/open-x\' ) )"></div><{{{ titleSizeTag }}} class="elementor-icon-box-title">' );
	check( '<div class="dg-x" style="--dg-cols: {{ data.n }}" onclick="window.dispatchEvent( new CustomEvent( \'digitalisimo/open-x\' ) )"></div><{{{ titleSizeTag }}} class="dg-icon-box-title">' === $tpl3, 'F1 style, on* y etiquetas dinámicas de las plantillas', $tpl3 );
	// Una página de varios MB (el editor): nunca sale vacía por un límite de PCRE, y se transforma igual.
	$big = '<html><body class="elementor-x"><script>var ElementorConfig = {"a":"' . str_repeat( 'x elementor-y ', 250000 ) . '"};</script><div class="elementor-z">t</div></body></html>';
	$bout = Transform::html( $big );
	check( strlen( $bout ) > 1000000 && 0 === Transform::count( $bout ) && false !== strpos( $bout, '<div class="dg-z">t</div>' ) && false !== strpos( $bout, 'var DigitalisimoConfig = ' ), 'F1 una página de más de 3 MB se transforma entera (sin vaciarse por PCRE)', strlen( $bout ) );
	// Lo que el paquete enmascara (separadores, twig) se trata como la palabra en las clases.
	check( '<path class="dg-shape-fill" d="M0">' === Transform::tag( '<path class="&#101;lementor-shape-fill" d="M0">' ), 'F1 la clase enmascarada con entidad se transforma', Transform::tag( '<path class="&#101;lementor-shape-fill" d="M0">' ) );
	$twig = Transform::json_out( array( 'elementor/macros' => "{% import ('elemen' ~ 'tor/macros') as m %}<div class=\"{{ ('elemen' ~ 'tor-element') }}\">" ) );
	check( array( 'digitalisimo/macros' => "{% import 'digitalisimo/macros' as m %}<div class=\"{{ 'dg-element' }}\">" ) === $twig, 'F1 Twig enmascarado en la configuración: nombres y clases como el JS', $twig );

	// F3 / F2 ----------------------------------------------------------------------------------------------------------
	@mkdir( $tmp . '/marca/motor/js', 0777, true );
	@mkdir( $tmp . '/marca/motor/css', 0777, true );
	file_put_contents( $tmp . '/marca/motor/js/frontend.min.js', '//' );
	add_filter(
		'digitalisimo_elements_marca_mirror_dir',
		static function () use ( $tmp ) {
			return $tmp . '/marca';
		}
	);
	check( ! Front_Layer::on(), 'F3 con la marca propia apagada, la capa no corre' );
	$GLOBALS['options'][ Toggle::OPTION ] = array( 'mode' => 'on', 'force' => true );
	Toggle::reset();
	check( Front_Layer::on(), 'F3 encendida y con el espejo, corre' );
	check( $page === Front_Layer::page( $page ), 'F2 sin copia de frontend.min.css y dg-icons.min.css en el espejo, la página sale sin transformar' );
	@mkdir( $tmp . '/marca/motor/lib/eicons/css', 0777, true );
	file_put_contents( $tmp . '/marca/motor/css/frontend.min.css', '' );
	file_put_contents( $tmp . '/marca/motor/lib/eicons/css/dg-icons.min.css', '' );
	@mkdir( $tmp . '/uploads/elementor/css', 0777, true );
	file_put_contents( $tmp . '/uploads/elementor/css/post-12.css', '.elementor-12 .elementor-element.elementor-element-abc{background:url("https://ensayo.test/wp-content/uploads/2026/11/elementor-banner.jpg")}' );
	$served = Front_Layer::page( $page );
	check( $served === $out, 'F2 con todas las copias, la página sale transformada' );
	file_put_contents( $tmp . '/marca/motor/js/frontend.min.js', '//' );
	$motor = Transform::tag( '<script src="' . $p . 'motor/assets/js/frontend.min.js?ver=4.3.5" id="x">' );
	check( false !== strpos( $motor, $p . 'marca/motor/js/frontend.min.js?ver=4.3.5' ), 'F2 con el núcleo en motor/ (sin la palabra en la URL), el script también sale del espejo', $motor );

	// F4 -----------------------------------------------------------------------------------------------------------------
	$copy = $tmp . '/uploads/digitalisimo-elements/css/post-12.css';
	check( is_file( $copy ) && '.dg-12 .dg-element.dg-element-abc{background:url("https://ensayo.test/wp-content/uploads/2026/11/elementor-banner.jpg")}' === (string) file_get_contents( $copy ), 'F4 el CSS generado se copió transformado, con la URL del medio intacta', @file_get_contents( $copy ) );
	file_put_contents( $tmp . '/uploads/elementor/css/post-12.css', '.elementor-12{color:blue}' );
	touch( $tmp . '/uploads/elementor/css/post-12.css', time() + 5 );
	Front_Layer::ensure_copies( $served );
	check( '.dg-12{color:blue}' === (string) file_get_contents( $copy ), 'F4 si el original cambia, la copia se rehace' );
	Front_Layer::clear_copies();
	check( ! is_file( $copy ), 'F4 al vaciar la caché del constructor, las copias se borran' );

	// F5 -----------------------------------------------------------------------------------------------------------------
	$frag = Front_Layer::cart_fragments( array( 'div.widget_shopping_cart_content' => '<div class="elementor-menu-cart__products">x</div>' ) );
	check( '<div class="dg-menu-cart__products">x</div>' === $frag['div.widget_shopping_cart_content'], 'F5 fragmentos del carrito' );
	$resp = new class() {
		public $d = array( 'data' => array( 'html' => '<div class="elementor-loop-container">Elementor</div>' ) );
		public function get_data() {
			return $this->d;
		}
		public function set_data( $d ) {
			$this->d = $d;
		}
	};
	$req = new class( '/elementor-pro/v1/refresh-loop' ) {
		public $r;
		public function __construct( $r ) {
			$this->r = $r;
		}
		public function get_route() {
			return $this->r;
		}
		public function set_route( $r ) {
			$this->r = $r;
		}
	};
	Front_Layer::rest_fragments( $resp, null, $req );
	check( '<div class="dg-loop-container">Elementor</div>' === $resp->d['data']['html'], 'F5 refresh-loop: el HTML con las clases nuevas y el texto igual', $resp->d );
	$other = new class() {
		public $d = array( 'title' => '<b class="elementor-x">t</b>' );
		public function get_data() {
			return $this->d;
		}
		public function set_data( $d ) {
			$this->d = $d;
		}
	};
	$req->r = '/wp/v2/posts';
	Front_Layer::rest_fragments( $other, null, $req );
	check( '<b class="elementor-x">t</b>' === $other->d['title'], 'F5 una ruta fuera de la lista cerrada no cambia' );
	$server = new class() {
		public $seen = '';
		public function dispatch( $r ) {
			$this->seen = $r->get_route();
			return 'ok';
		}
	};
	$req->r = '/digitalisimo-pro/v1/refresh-loop';
	check( 'ok' === Front_Layer::rest_alias( null, $server, $req ) && '/elementor-pro/v1/refresh-loop' === $server->seen, 'F5 /digitalisimo-pro/v1/… se atiende como /elementor-pro/v1/…' );
	$req->r = '/wp/v2/posts';
	check( null === Front_Layer::rest_alias( null, $server, $req ), 'F5 otras rutas no se tocan' );
	Transform::set_rules( array( 'ajax' => array( 'usados_por_el_front' => array( 'elementor_pro_forms_send_form', 'elementor_send_clicks' ) ) ) );
	check( 'elementor_pro_forms_send_form' === Front_Layer::ajax_args( array( 'action' => 'digitalisimo_pro_forms_send_form' ) )['action'] && 'elementor_send_clicks' === Front_Layer::ajax_args( array( 'action' => 'digitalisimo_send_clicks' ) )['action'], 'F5 alias AJAX del front' );
	check( 'digitalisimo_elements_global_weight_scan' === Front_Layer::ajax_args( array( 'action' => 'digitalisimo_elements_global_weight_scan' ) )['action'] && 'digitalisimo_x' === Front_Layer::ajax_args( array( 'action' => 'digitalisimo_x' ) )['action'], 'F5 las acciones propias no se tocan' );
	Transform::set_rules( null );

	exec( 'rm -rf ' . escapeshellarg( $tmp ) );
	echo "\n$GLOBALS[fails] fallos de $GLOBALS[checks] comprobaciones\n";
	exit( $GLOBALS['fails'] ? 1 : 0 );
}
