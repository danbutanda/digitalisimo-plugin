<?php
/**
 * Núcleo de conversión de los widgets de PRO (modules/digitalisimo-convert/), piezas de las correcciones de fidelidad de
 * C3–C4 (regla del propietario del 2026-11-05: un sitio migrado se ve igual que con PRO):
 * - idioma: los mapas completan los textos que PRO pintaba en el front con el idioma del sitio, también cuando «Migrar»
 *   (admin-ajax) o un kit corren con el idioma del perfil de quien los lanza (`Registry::in_site_locale()`, al cargar un
 *   mapa y al traducir, con un solo cambio por árbol), y el idioma vuelve al de la petición;
 * - `keep_match`: claves que PRO arma con datos del sitio (`<tipo>_taxonomy`) pasan aunque el tipo no exista al convertir,
 *   también en `__dynamic__`, sin `pro.unmapped`;
 * - `class_patterns`: clases que PRO arma con un dato, en el CSS personalizado, con el código `pro.css`;
 * - CSS que nombra las clases de PRO desde fuera del widget (`Translator::rewrite_external_css()`, `Converter::
 *   external_css()` y `tree_css()`): copia de cada regla con las clases propias justo después de la original, dentro de
 *   `@media`, sin tocar `@keyframes`, comentarios ni cadenas, sólo con una clase de anclaje propia, sin repetir copias y
 *   sin cambiar nada la segunda vez; en un árbol, sólo el CSS de lo que no es un widget convertido.
 *
 * Uso: php tests/elements-convert-fidelity.php
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['loc'] = array(
		'site'     => 'en_US',
		'current'  => 'es_MX', // El perfil del administrador que pulsa «Migrar».
		'stack'    => array(),
		'switches' => 0,
	);
	function check_cf( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( 'FALLA: ' . $message ); } }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {}
	function get_locale() { return $GLOBALS['loc']['stack'] ? $GLOBALS['loc']['current'] : $GLOBALS['loc']['site']; }
	function switch_to_locale( $locale ) {
		if ( $GLOBALS['loc']['current'] === $locale ) {
			return false;
		}
		$GLOBALS['loc']['stack'][] = $GLOBALS['loc']['current'];
		$GLOBALS['loc']['current'] = $locale;
		++$GLOBALS['loc']['switches'];
		return true;
	}
	function restore_previous_locale() {
		$GLOBALS['loc']['current'] = (string) array_pop( $GLOBALS['loc']['stack'] );
		return $GLOBALS['loc']['current'];
	}
	/** Traducción de prueba: en es_MX, el paquete de idioma de PRO traduce sus textos. */
	function cf_translate( $text, $domain ) {
		$es = array( 'Search' => 'Buscar (es_MX)', 'Type to start searching...' => 'Escribe… (es_MX)', 'No Comments' => 'Sin comentarios (es_MX)', 'One Comment' => 'Un comentario (es_MX)', '%s Comments' => '%s comentarios (es_MX)', 'Previous' => 'Anterior (es_MX)' );
		return 'elementor-pro' === $domain && 'es_MX' === $GLOBALS['loc']['current'] && isset( $es[ $text ] ) ? $es[ $text ] : $text;
	}
	function __( $text, $domain = 'default' ) { return cf_translate( $text, $domain ); }
	function esc_html__( $text, $domain = 'default' ) { return htmlspecialchars( cf_translate( $text, $domain ), ENT_QUOTES ); }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }

	$E = dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-convert';
	foreach ( array( 'class-catalog.php', 'class-registry.php', 'class-translator.php', 'class-converter.php' ) as $file ) {
		require $E . '/' . $file;
	}
	$registry   = '\\Digitalisimo\\Elements\\Convert\\Registry';
	$translator = '\\Digitalisimo\\Elements\\Convert\\Translator';
	$converter  = '\\Digitalisimo\\Elements\\Convert\\Converter';
	$checks     = 0;
	$ok         = static function ( $value, string $message ) use ( &$checks ) {
		check_cf( $value, $message );
		++$checks;
	};

	/* 1. Idioma del sitio al cargar los mapas y al traducir */
	$registry::reset();
	$map = $registry::map( 'search' );
	$ok( is_array( $map ) && 'Search' === $map['defaults']['submit_button_text'] && 'Type to start searching...' === $map['defaults']['search_input_placeholder_text'], '1: el mapa de la Búsqueda, cargado con el perfil en es_MX, completa los textos de PRO en el idioma del sitio (en_US): ' . json_encode( $map['defaults'] ?? null ) );
	$ok( 'es_MX' === $GLOBALS['loc']['current'] && array() === $GLOBALS['loc']['stack'], '1: al terminar, la petición vuelve al idioma del perfil.' );
	$registry::reset();
	$GLOBALS['loc']['switches'] = 0;
	$tree = array(
		array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			array( 'id' => 'p1', 'elType' => 'widget', 'widgetType' => 'post-info', 'settings' => array( 'icon_list' => array( array( '_id' => 'r1', 'type' => 'comments' ) ) ), 'elements' => array() ),
			array( 'id' => 'p2', 'elType' => 'widget', 'widgetType' => 'post-info', 'settings' => array(), 'elements' => array() ),
			array( 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'search', 'settings' => array(), 'elements' => array() ),
		) ),
	);
	$log = array();
	$out = $converter::tree( $tree, $log );
	$row = $out[0]['elements'][0]['settings']['icon_list'][0];
	$ok( 'No Comments' === $row['string_no_comments'] && 'One Comment' === $row['string_one_comment'] && '%s Comments' === $row['string_comments'], '1: el `filter` de la Información de la entrada completa los textos de los comentarios en el idioma del sitio: ' . json_encode( $row ) );
	$ok( 'Search' === $out[0]['elements'][2]['settings']['submit_button_text'], '1: la Búsqueda convertida dice «Search», como PRO en el front del sitio.' );
	$ok( 1 === $GLOBALS['loc']['switches'] && 'es_MX' === $GLOBALS['loc']['current'], '1: un solo cambio de idioma por árbol (' . $GLOBALS['loc']['switches'] . ') y vuelta al del perfil.' );
	$GLOBALS['loc']['switches'] = 0;
	$el = $converter::element( $tree[0]['elements'][0] );
	$ok( 'No Comments' === $el['settings']['icon_list'][0]['string_no_comments'] && 1 === $GLOBALS['loc']['switches'] && 'es_MX' === $GLOBALS['loc']['current'], '1: `element()` suelto también traduce en el idioma del sitio y lo restaura.' );
	$GLOBALS['loc']['current'] = 'en_US';
	$GLOBALS['loc']['switches'] = 0;
	$converter::tree( $tree, $log );
	$ok( 0 === $GLOBALS['loc']['switches'], '1: con el perfil en el idioma del sitio no hay cambio de idioma.' );
	$GLOBALS['loc']['current'] = 'es_MX';
	$caught = '';
	try {
		$registry::in_site_locale( static function () { throw new RuntimeException( 'roto' ); } );
	} catch ( RuntimeException $e ) {
		$caught = $e->getMessage();
	}
	$ok( 'roto' === $caught && 'es_MX' === $GLOBALS['loc']['current'] && array() === $GLOBALS['loc']['stack'], '1: una excepción dentro no deja la petición en otro idioma.' );

	/* 2. keep_match */
	$spec = array( 'source' => 'x', 'target' => 'y', 'keep' => array( 'in_same_term' ), 'keep_match' => '/^[a-z0-9_-]+_taxonomy$/' );
	list( $o, $codes ) = $translator::translate( array( 'in_same_term' => array( 'portfolio' ), 'portfolio_taxonomy' => 'portfolio_cat', 'post_taxonomy_mobile' => 'category', 'otra' => 1, '__dynamic__' => array( 'producto_taxonomy' => '[elementor-tag]' ) ), $spec );
	$ok( 'portfolio_cat' === $o['portfolio_taxonomy'] && 'category' === $o['post_taxonomy_mobile'] && '[elementor-tag]' === $o['__dynamic__']['producto_taxonomy'] && ! isset( $o['otra'] ), '2: las claves que cumplen `keep_match` pasan tal cual (también por dispositivo y en __dynamic__): ' . json_encode( $o ) );
	$unmapped = array_values( array_filter( $codes, static function ( $c ) { return 'pro.unmapped' === $c['code']; } ) );
	$ok( 1 === count( $unmapped ) && 'otra' === $unmapped[0]['key'], '2: sólo la clave que no cumple el patrón va a «Pendiente de revisar»: ' . json_encode( $codes ) );

	/* 3. class_patterns */
	$spec = array( 'source' => 'x', 'target' => 'y', 'classes' => array( '.elementor-sitemap-item' => '.digi-sitemap__item' ), 'class_patterns' => array( '/\.elementor-sitemap-item-([a-z0-9_-]+)(?![\w-])/' => '.digi-sitemap__item--$1' ) );
	list( $o, $codes ) = $translator::translate( array( 'custom_css' => 'selector .elementor-sitemap-item-product, selector .elementor-sitemap-item{color:red}' ), $spec );
	$ok( 'selector .digi-sitemap__item--product, selector .digi-sitemap__item{color:red}' === $o['custom_css'] && 'pro.css' === ( $codes[0]['code'] ?? '' ), '3: los patrones de clase se aplican al CSS personalizado con el código pro.css: ' . $o['custom_css'] );
	$ok( 'a .x' === $translator::rewrite_classes( 'a .x', array(), array( '/(/' => 'b' ) ), '3: un patrón mal escrito no rompe la conversión.' );

	/* 4. CSS desde fuera del widget */
	$nav  = array( 'classes' => array( '.elementor-nav-menu--main' => '.digi-nav__main', '.elementor-nav-menu' => '.digi-nav__list', '.elementor-item' => '.digi-nav__link', '.elementor-active' => '[aria-expanded=true]' ) );
	$css  = "selector .elementor-nav-menu--main .elementor-item{color:#d00}\n/* .elementor-item{x} */\n.otro{a:b}\n@media(max-width:576px){selector .elementor-nav-menu{justify-content:center}.otro{c:d}}\n@keyframes k{from{opacity:0}}\n.elementor-active{color:blue}\n[data-x=\"}{.elementor-item\"]{e:f}";
	$out  = $translator::rewrite_external_css( $css, array( 'nav-menu' => $nav ) );
	$want = "selector .elementor-nav-menu--main .elementor-item{color:#d00}selector .digi-nav__main .digi-nav__link{color:#d00}\n/* .elementor-item{x} */\n.otro{a:b}\n@media(max-width:576px){selector .elementor-nav-menu{justify-content:center}selector .digi-nav__list{justify-content:center}.otro{c:d}}\n@keyframes k{from{opacity:0}}\n.elementor-active{color:blue}\n[data-x=\"}{.elementor-item\"]{e:f}";
	$ok( $want === $out, "4: copia traducida tras cada regla que nombra las clases (también en @media), sin tocar @keyframes, comentarios, cadenas ni una regla con sólo un estado genérico:\n" . $out );
	$ok( $out === $translator::rewrite_external_css( $out, array( 'nav-menu' => $nav ) ), '4: idempotente: la segunda vez no añade nada.' );
	$ok( '.a{b:c}' === $translator::rewrite_external_css( '.a{b:c}', array( 'nav-menu' => $nav ) ) && '' === $translator::rewrite_external_css( '', array( 'nav-menu' => $nav ) ) && '.x{' === $translator::rewrite_external_css( '.x{', array( 'nav-menu' => $nav ) ), '4: sin clases de PRO, vacío o sin cerrar, el CSS queda igual.' );
	$two = $translator::rewrite_external_css( '.elementor-pagination a{x:y}', array( 'a' => array( 'classes' => array( '.elementor-pagination' => '.digi-a__pages' ) ), 'b' => array( 'classes' => array( '.elementor-pagination' => '.digi-b__pages' ) ), 'c' => array( 'classes' => array( '.elementor-pagination' => '.digi-a__pages' ) ) ) );
	$ok( '.elementor-pagination a{x:y}.digi-a__pages a{x:y}.digi-b__pages a{x:y}' === $two, '4: una clase que traducen dos mapas da una copia por destino, sin repetir: ' . $two );
	// Una lista de selectores con dos widgets de PRO (menú y buscador): en la misma pasada, la copia de cada uno y la que
	// traduce los dos; la segunda pasada (la etapa `pro_css` sobre lo que ya tocó `pro`, o el segundo guardado de un kit)
	// no añade nada.
	$pair = array( 'nav-menu' => array( 'classes' => array( '.elementor-nav-menu' => '.digi-nav__list' ) ), 'search-form' => array( 'classes' => array( '.elementor-search-form' => '.digi-search-form' ) ) );
	$both = $translator::rewrite_external_css( '.elementor-nav-menu a, .elementor-search-form input{color:red}', $pair );
	$ok( '.elementor-nav-menu a, .elementor-search-form input{color:red}.digi-nav__list a, .elementor-search-form input{color:red}.elementor-nav-menu a, .digi-search-form input{color:red}.digi-nav__list a, .digi-search-form input{color:red}' === $both && $both === $translator::rewrite_external_css( $both, $pair ), '4: una regla con dos widgets de PRO gana también la copia con los dos traducidos, y la segunda pasada no cambia nada: ' . $both );
	$sm  = array( 'class_patterns' =>array( '/\.elementor-sitemap-item-([a-z0-9_-]+)(?![\w-])/' => '.digi-sitemap__item--$1' ) );
	$ok( '.elementor-sitemap-item-product{x:y}.digi-sitemap__item--product{x:y}' === $translator::rewrite_external_css( '.elementor-sitemap-item-product{x:y}', array( 'sitemap' => $sm ) ), '4: los patrones de clase también anclan y traducen.' );
	$registry::reset();
	$real = $converter::external_css( 'selector .elementor-nav-menu--main .elementor-item{color:#d00}' );
	$ok( false !== strpos( $real, 'selector .digi-nav__main .digi-nav__link{color:#d00}' ), '4: con los mapas listos reales, la regla de una cabecera llega al Menú de WordPress propio: ' . $real );
	$tree = array(
		array( 'id' => 'c1', 'elType' => 'container', 'settings' => array( 'custom_css' => 'selector .elementor-item{color:red}' ), 'elements' => array(
			array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-nav-menu', 'settings' => array( '_digitalisimo_pro' => 'nav-menu', 'custom_css' => 'selector .elementor-item{x:y}' ), 'elements' => array() ),
			array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'custom_css' => 'selector{a:b}' ), 'elements' => array() ),
		) ),
	);
	$changed = 0;
	$new     = $converter::tree_css( $tree, array( 'nav-menu' => $nav ), $changed );
	$ok( 'selector .elementor-item{color:red}selector .digi-nav__link{color:red}' === $new[0]['settings']['custom_css'] && 'selector .elementor-item{x:y}' === $new[0]['elements'][0]['settings']['custom_css'] && 'selector{a:b}' === $new[0]['elements'][1]['settings']['custom_css'] && 1 === $changed, '4: en un árbol, el CSS del contenedor gana la copia; el de un widget convertido (con la marca) y el que no nombra clases de PRO, no.' );

	echo "DIGITALÍSIMO Elements: correcciones de fidelidad del núcleo de conversión ({$checks} comprobaciones) validadas.\n";
}
