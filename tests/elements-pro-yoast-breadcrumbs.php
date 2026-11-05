<?php
/**
 * Migas (Yoast o DIGITALÍSIMO SEO) (`digitalisimo-yoast-breadcrumbs`), el widget propio que sustituye al Breadcrumbs de
 * PRO (C4 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/breadcrumbs.php`:
 * - recursos: sólo su hoja (≤ 4 KB, sin script, sin el kit ni clases del proveedor), nada vetado por el empaquetado;
 * - un control propio por cada control de PRO, con el mismo nombre, tipo, valor por defecto, opciones y condiciones
 *   (comparados con el widget de PRO cargado en el mismo entorno);
 * - los tres caminos: con Yoast, sus migas en la etiqueta elegida con `id="breadcrumbs"` (como PRO, también con
 *   `yoast_breadcrumb()` sin la clase); sin Yoast, la ruta de DIGITALÍSIMO SEO (la misma fuente que «Ruta de
 *   navegación») o nada con otro plugin SEO; sin ninguno, nada (y un aviso sólo en el editor). Ningún JSON-LD;
 * - conversión con `Converter::tree()` de Breadcrumbs de PRO reales: ningún `pro.unmapped`, cada clave cae en un control
 *   propio, el CSS personalizado pasa a las clases propias y lo convertido se pinta igual que PRO.
 */
namespace {
	$GLOBALS['h_wp'] = array();
	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/breadcrumbs.php' );
	use Digitalisimo\Elements\Theme_Elements\Widgets\Yoast_Breadcrumbs;
	use ElementorPro\Modules\ThemeElements\Widgets\Breadcrumbs;

	$pro = h_widget( Breadcrumbs::class );
	$own = h_widget( Yoast_Breadcrumbs::class );
	$map = require h_path( 'modules/digitalisimo-convert/maps/breadcrumbs.php' );

	// 1. Recursos.
	$css = h_assets( $own, 'Migas (Yoast o DIGITALÍSIMO SEO)', 'breadcrumbs', array() );
	h_check( str_contains( $css, '.elementor-widget-digitalisimo-yoast-breadcrumbs{font-size:.85em}' ) && str_contains( $css, '.elementor-widget-digitalisimo-yoast-breadcrumbs p{margin-bottom:0}' ), 'La hoja repite la de PRO: letra al 85 % y párrafo sin margen inferior.' );
	h_check( str_contains( $css, 'a:focus-visible{outline:2px solid;outline-offset:2px}' ), 'Foco visible propio de los enlaces (2 px con separación de 2 px).' );
	h_check( ! str_contains( h_code( h_path( 'modules/digitalisimo-theme-elements/widgets/class-yoast-breadcrumbs.php' ) ), 'ld+json' ), 'Ningún JSON-LD propio.' );
	h_check( 'eicon-yoast' === $own->get_icon() && Yoast_Breadcrumbs::yoast() === false, 'Icono de PRO; sin Yoast en este entorno.' );

	// 2. Los mismos controles que PRO. Diferencia declarada: la alineación también fija `text-align` (PRO lo dejaba a la
	// clase `elementor-align-*` y a la hoja del núcleo, que no pinta las clases propias).
	$diff = h_compare_widgets( $pro, $own, array( 'align.selectors' => true ) );
	h_check( ! $diff, "Controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	h_check( 'digi-yoast-breadcrumbs%s-align-' === $own->controls['align']['prefix_class'] && array( '{{WRAPPER}}' => 'text-align: {{VALUE}};' ) === $own->controls['align']['selectors'] && ! empty( $own->controls['align']['responsive'] ), 'Alineación responsiva con clase propia y la propiedad misma.' );
	h_check( 'raw_html' === $own->controls['html_description']['type'] && str_contains( $own->controls['html_description']['raw'], 'DIGITALÍSIMO SEO' ), 'La ayuda del panel explica de dónde salen las migas sin Yoast.' );

	// 3. Los tres caminos.
	$GLOBALS['h_wp']['edit_mode'] = false;
	h_check( '' === h_render( $own ), 'Sin Yoast ni DIGITALÍSIMO SEO no pinta nada en el sitio.' );
	eval( 'namespace Elementor; class Plugin { public static $instance; }' ); // phpcs:ignore -- sólo pruebas.
	\Elementor\Plugin::$instance = (object) array( 'editor' => new \ElementorPro\H_Mode() );
	$GLOBALS['h_wp']['edit_mode'] = true;
	h_check( str_contains( h_render( $own ), 'class="digi-yoast-breadcrumbs__notice"' ), 'En el editor, un aviso en lugar de nada.' );
	$GLOBALS['h_wp']['edit_mode'] = false;
	h_check( '' === h_render( $own ), 'El aviso sólo sale en el editor.' );

	// DIGITALÍSIMO SEO: la misma fuente que «Ruta de navegación» (`Digitalisimo_Integrations_Breadcrumbs`, con su `html()`).
	$seo_source = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-seo/includes/class-breadcrumbs.php' );
	h_check( str_contains( $seo_source, 'public static function html()' ) && str_contains( $seo_source, 'self::render( self::trail(),' ), 'DIGITALÍSIMO SEO ofrece html() sobre su ruta trail().' );
	h_check( str_contains( (string) file_get_contents( h_path( 'modules/digitalisimo-widgets/class-breadcrumbs.php' ) ), '\\Digitalisimo_Integrations_Breadcrumbs::trail()' ), '«Ruta de navegación» usa la misma clase de SEO.' );
	eval( 'class Digitalisimo_Integrations_SEO_Resolver { public static $foreign = false; public static function foreign_seo() { return self::$foreign; } }
	class Digitalisimo_Integrations_Breadcrumbs { public static $calls = 0; public static function trail() { return array(); } public static function html() { self::$calls++; return \'<nav class="digitalisimo-breadcrumbs" aria-label="Migas de pan"><a href="https://sitio.test/">Inicio</a> <span class="digitalisimo-breadcrumbs__separator" aria-hidden="true">›</span> <span aria-current="page">Entrada</span></nav>\'; } }' ); // phpcs:ignore -- sólo pruebas.
	$seo = h_render( $own, array( 'html_tag' => 'span' ) );
	h_check( '<div class="digi-yoast-breadcrumbs"><nav class="digitalisimo-breadcrumbs" aria-label="Migas de pan"><a href="https://sitio.test/">Inicio</a> <span class="digitalisimo-breadcrumbs__separator" aria-hidden="true">›</span> <span aria-current="page">Entrada</span></nav></div>' === $seo, 'Sin Yoast, la ruta de DIGITALÍSIMO SEO dentro de un div (su nav no cabe en p ni span): ' . $seo );
	h_check( '' === h_render( $pro ), 'El de PRO no pinta nada sin Yoast.' );
	\Digitalisimo_Integrations_SEO_Resolver::$foreign = true;
	h_check( '' === h_render( $own ), 'Con otro plugin SEO que no es Yoast, nada (como «Ruta de navegación» deja la ruta de SEO).' );
	\Digitalisimo_Integrations_SEO_Resolver::$foreign = false;

	// Yoast: sus migas como PRO, también por encima de DIGITALÍSIMO SEO.
	eval( 'class WPSEO_Breadcrumbs { public static $args = array(); public static function breadcrumb( $before = "", $after = "", $display = true ) { self::$args[] = array( $before, $after ); $out = $before . \'<span><span><a href="https://sitio.test/">Inicio</a></span> » <span class="breadcrumb_last" aria-current="page">Entrada &amp; más</span></span>\' . $after; if ( $display ) { echo $out; return; } return $out; } }' ); // phpcs:ignore -- sólo pruebas.
	h_check( Yoast_Breadcrumbs::yoast(), 'Yoast activo.' );
	$calls = \Digitalisimo_Integrations_Breadcrumbs::$calls;
	foreach ( array( '' => 'p', 'p' => 'p', 'div' => 'div', 'nav' => 'nav', 'span' => 'span', 'script' => 'div' ) as $tag => $expected ) {
		$a = h_render( $pro, array( 'html_tag' => $tag ) );
		$b = h_render( $own, array( 'html_tag' => $tag ) );
		h_check( $a === $b && 0 === strpos( $b, '<' . $expected . ' id="breadcrumbs"><span><span><a href="https://sitio.test/">Inicio</a></span>' ) && str_ends_with( $b, '</' . $expected . '>' ), "Etiqueta «{$tag}»: el mismo marcado que PRO: $b" );
	}
	h_check( $calls === \Digitalisimo_Integrations_Breadcrumbs::$calls, 'Con Yoast no se consulta la ruta de DIGITALÍSIMO SEO.' );
	h_check( str_contains( $own->controls['html_description']['raw'] ?? '', 'DIGITALÍSIMO SEO' ), 'La ayuda se fija al registrar los controles.' );
	$fresh = h_widget( Yoast_Breadcrumbs::class );
	h_check( str_contains( $fresh->controls['html_description']['raw'], 'admin.php?page=wpseo_titles#top#breadcrumbs' ) && str_contains( $fresh->controls['html_description']['raw'], 'Yoast SEO está activo' ), 'Con Yoast, la ayuda enlaza a su panel de migas, como PRO.' );

	// 4. Conversión de Breadcrumbs de PRO.
	\Digitalisimo\Elements\Convert\Registry::reset();
	h_check( null !== \Digitalisimo\Elements\Convert\Registry::map( 'breadcrumbs', true ) && 'hide' === $map['panel'] && in_array( $map['status'], array( 'draft', 'ready' ), true ), 'El mapa existe y oculta el widget de PRO cuando está listo.' );
	$cases = array(
		'defaults'   => array(),
		'styled'     => array( 'html_tag' => 'nav', 'text_color' => '#333333', 'link_color' => '#0000ff', 'link_hover_color' => '#ff0000', 'typography_typography' => 'custom', 'typography_font_size' => array( 'unit' => 'px', 'size' => 13 ), 'typography_font_weight' => '600' ),
		'responsive' => array( 'align' => 'center', 'align_tablet' => 'left', 'align_mobile' => 'right', 'typography_typography' => 'custom', 'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 11 ) ),
		'globals'    => array( 'text_color' => '', '__globals__' => array( 'text_color' => 'globals/colors?id=text', 'link_color' => 'globals/colors?id=accent', 'typography_typography' => 'globals/typography?id=primary', '_background_color' => 'globals/colors?id=secondary' ) ),
		'dynamic'    => array( '__dynamic__' => array( 'link_color' => '[elementor-tag id="a1" name="acf-color" settings="%7B%7D"]' ) ),
		'css'        => array( 'custom_css' => "selector.elementor-align-center p{margin:0}\n.elementor-widget-breadcrumbs a{color:red}\nselector.elementor-tablet-align-left a{text-decoration:none}", '_css_classes' => 'mis-migas', '_margin' => array( 'top' => '4', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
	);
	list( $out, $codes ) = h_convert( 'breadcrumbs', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún descarte ni aproximación; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	h_check( 'breadcrumbs' === $out['defaults']['_digitalisimo_pro'] && h_render( $pro, $cases['defaults'] ) === h_render( $own, $out['defaults'] ), 'Un Breadcrumbs sin cambios se pinta igual.' );
	$s = $out['styled'];
	h_check( 'nav' === $s['html_tag'] && '#333333' === $s['text_color'] && '#0000ff' === $s['link_color'] && '#ff0000' === $s['link_hover_color'] && 13 === $s['typography_font_size']['size'] && '600' === $s['typography_font_weight'], 'Etiqueta, colores y tipografía pasan con su nombre.' );
	h_check( h_render( $pro, $cases['styled'] ) === h_render( $own, $s ), 'Con «nav», el mismo marcado que PRO.' );
	h_check( 'center' === $out['responsive']['align'] && 'left' === $out['responsive']['align_tablet'] && 'right' === $out['responsive']['align_mobile'] && 11 === $out['responsive']['typography_font_size_mobile']['size'], 'Alineación y tipografía por dispositivo.' );
	h_check( array( 'text_color' => 'globals/colors?id=text', 'link_color' => 'globals/colors?id=accent', 'typography_typography' => 'globals/typography?id=primary', '_background_color' => 'globals/colors?id=secondary' ) === $out['globals']['__globals__'], 'Colores y tipografías globales del kit siguen a su control.' );
	h_check( array( 'link_color' => '[elementor-tag id="a1" name="acf-color" settings="%7B%7D"]' ) === $out['dynamic']['__dynamic__'], 'Las etiquetas dinámicas siguen a su control.' );
	h_check( "selector.digi-yoast-breadcrumbs-align-center p{margin:0}\n.elementor-widget-digitalisimo-yoast-breadcrumbs a{color:red}\nselector.digi-yoast-breadcrumbs-tablet-align-left a{text-decoration:none}" === $out['css']['custom_css'] && 'mis-migas' === $out['css']['_css_classes'], 'CSS personalizado con las clases propias (también por dispositivo); Avanzado intacto.' );
	// Cada clase propia del mapa la pone el `prefix_class` de la alineación (por dispositivo) o es la del envoltorio.
	foreach ( $map['classes'] as $from => $to ) {
		h_check( '.elementor-widget-digitalisimo-yoast-breadcrumbs' === $to || preg_match( '/^\.' . preg_quote( sprintf( $own->controls['align']['prefix_class'], '' ), '/' ) . '(left|center|right)$/', $to ) || preg_match( '/^\.digi-yoast-breadcrumbs-(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)-align-(left|center|right)$/', $to ), "La clase $to (de $from) no la pone el widget propio." );
	}

	echo 'DIGITALÍSIMO Elements: Migas (Yoast o DIGITALÍSIMO SEO) (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupo, hoja de ' . strlen( $css ) . ' B) y su mapa desde el Breadcrumbs de PRO (' . count( $cases ) . " casos) validados.\n";
}
