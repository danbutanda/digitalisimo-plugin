<?php
/**
 * Navegación entre entradas (`digitalisimo-post-navigation`), el widget propio que sustituye al Post Navigation de PRO
 * (C4 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/post-navigation.php`:
 * - recursos: sólo su hoja (≤ 4 KB, sin script, sin el kit ni clases del proveedor, sólo el gris de PRO), foco visible,
 *   movimiento reducido, nada vetado por el empaquetado;
 * - un control propio por cada control de PRO (también `<tipo>_taxonomy`, por cada tipo público con taxonomías), con el
 *   mismo nombre, tipo, valor por defecto, opciones y condiciones (comparados con el widget de PRO en el mismo entorno),
 *   también con el sitio en RTL (flechas por defecto invertidas);
 * - marcado: `<nav>` con nombre, los enlaces de `get_previous_post_link()`/`get_next_post_link()` con «En el mismo
 *   término» y la taxonomía como PRO, flechas decorativas, separador con «Bordes», nombre accesible sin etiqueta ni
 *   título, escape de las etiquetas;
 * - conversión con `Converter::tree()` de Post Navigation de PRO reales: ningún `pro.unmapped`, cada clave cae en un
 *   control propio, el CSS personalizado pasa a las clases propias y lo convertido se pinta con los mismos textos.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace {
	$GLOBALS['h_wp'] = array(
		'post_types'  => array( 'post' => 'Entradas', 'page' => 'Páginas', 'producto' => 'Productos' ),
		'taxonomies'  => array( 'category' => 'Categorías', 'post_tag' => 'Etiquetas' ),
		'queried'     => 'post',
		'links'       => array(),
	);
	function get_queried_object_id() { return 7; }
	if ( ! function_exists( 'esc_attr_e' ) ) {
		function esc_attr_e( $v, $d = '' ) { echo htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	}
	function get_post_type( $id = null ) { return $GLOBALS['h_wp']['queried']; }
	/** Como `get_adjacent_post_link()` de WordPress, con una entrada fija a cada lado y el registro de los argumentos. */
	function h_adjacent( $format, $link, $in_same_term, $excluded, $taxonomy, $previous ) {
		$GLOBALS['h_wp']['links'][] = array( $previous ? 'prev' : 'next', $in_same_term, $taxonomy );
		if ( ! empty( $GLOBALS['h_wp']['no_' . ( $previous ? 'prev' : 'next' )] ) ) {
			return '';
		}
		$title = $previous ? 'Entrada <em>anterior</em>' : 'Entrada siguiente & más';
		$url   = $previous ? 'https://sitio.test/anterior/' : 'https://sitio.test/siguiente/';
		$inner = '<a href="' . $url . '" rel="' . ( $previous ? 'prev' : 'next' ) . '">' . str_replace( '%title', $title, $link ) . '</a>';
		return str_replace( '%link', $inner, $format );
	}
	function get_previous_post_link( $format = '&laquo; %link', $link = '%title', $in_same_term = false, $excluded = '', $taxonomy = 'category' ) { return h_adjacent( $format, $link, $in_same_term, $excluded, $taxonomy, true ); }
	function get_next_post_link( $format = '%link &raquo;', $link = '%title', $in_same_term = false, $excluded = '', $taxonomy = 'category' ) { return h_adjacent( $format, $link, $in_same_term, $excluded, $taxonomy, false ); }
	function previous_post_link( ...$a ) { echo get_previous_post_link( ...$a ); }
	function next_post_link( ...$a ) { echo get_next_post_link( ...$a ); }

	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/post-navigation.php' );
	use Digitalisimo\Elements\Theme_Elements\Widgets\Post_Navigation;
	use ElementorPro\Modules\ThemeElements\Widgets\Post_Navigation as Pro_Post_Navigation;

	$pro = h_widget( Pro_Post_Navigation::class );
	$own = h_widget( Post_Navigation::class );
	$map = require h_path( 'modules/digitalisimo-convert/maps/post-navigation.php' );

	// 1. Recursos.
	$css = h_assets( $own, 'Navegación entre entradas', 'post-navigation', array( '#d5d8dc' ) );
	foreach ( array( '.digi-post-navigation{display:flex;overflow:hidden}', '.digi-post-navigation__link{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:calc(50% - .5px)}', '.digi-post-navigation__link a{align-items:center;display:flex;gap:15px;max-width:100%}', '.digi-post-navigation__arrow{color:#d5d8dc;display:flex;fill:#d5d8dc;font-size:30px}', '.digi-post-navigation__separator{background-color:#d5d8dc;height:100%;margin:0 auto;width:1px}', '.digi-post-navigation__label{font-size:.8em;text-transform:uppercase}', '.digi-post-navigation__title{font-size:.7em}' ) as $rule ) {
		h_check( str_contains( $css, $rule ), "La hoja repite la de PRO: $rule" );
	}
	h_check( str_contains( $css, '.digi-post-navigation__link a:focus-visible{outline:2px solid;outline-offset:-2px}' ), 'Foco visible del enlace hacia dentro: la columna recorta lo que sobresale (overflow:hidden, como PRO).' );
	h_check( (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{[^}]*\{transition:none!important\}\}/', $css ), 'Movimiento reducido: sin transiciones de color.' );
	h_check( 'eicon-post-navigation' === $own->get_icon(), 'Icono de PRO.' );

	// 2. Los mismos controles que PRO (y con el sitio en RTL). Diferencia declarada: los textos por defecto de las
	// etiquetas están en español («Anterior», «Siguiente»); el mapa completa los de PRO al convertir.
	$skip = array( 'prev_label.default' => true, 'next_label.default' => true );
	$diff = h_compare_widgets( $pro, $own, $skip );
	h_check( ! $diff, "Controles distintos de los de PRO:\n" . implode( "\n", array_slice( $diff, 0, 30 ) ) );
	h_check( isset( $own->controls['post_taxonomy'], $own->controls['page_taxonomy'], $own->controls['producto_taxonomy'] ) && array( 'in_same_term' => 'producto' ) === $own->controls['producto_taxonomy']['condition'] && array( 'category' => 'Categorías', 'post_tag' => 'Etiquetas' ) === $own->controls['post_taxonomy']['options'], 'Una taxonomía por tipo de entrada público, con la misma condición que PRO.' );
	h_check( 'digi-post-navigation--borders-' === $own->controls['show_borders']['prefix_class'], 'Bordes con clase propia en el envoltorio.' );
	h_check( 'Anterior' === $own->controls['prev_label']['default'] && 'Siguiente' === $own->controls['next_label']['default'] && 'Previous' === $map['defaults']['prev_label'] && 'Next' === $map['defaults']['next_label'], 'Textos por defecto propios en español; el mapa completa los de PRO.' );
	$GLOBALS['h_wp']['rtl'] = true;
	$pro_rtl = h_widget( Pro_Post_Navigation::class );
	$own_rtl = h_widget( Post_Navigation::class );
	h_check( ! h_compare_widgets( $pro_rtl, $own_rtl, $skip ) && 'fas fa-angle-right' === $own_rtl->controls['arrow_previous_icon']['default']['value'], 'En RTL, las mismas flechas por defecto que PRO.' );
	$GLOBALS['h_wp']['rtl'] = false;
	foreach ( $map['keep'] as $key ) {
		h_check( isset( $own->controls[ $key ] ) || preg_match( '/_taxonomy$/', $key ), "El mapa conserva $key, que no es un control propio." );
	}
	h_check( in_array( 'post_taxonomy', $map['keep'], true ), 'El mapa declara siempre la taxonomía de las entradas.' );

	// 3. Marcado.
	$html = h_render( $own );
	h_check( 0 === strpos( $html, '<nav class="digi-post-navigation" aria-label="Navegación entre entradas"><div class="digi-post-navigation__prev digi-post-navigation__link"><a href="https://sitio.test/anterior/" rel="prev"><span class="digi-post-navigation__arrow digi-post-navigation__arrow--prev" aria-hidden="true"><svg class="e-font-icon-svg" data-icon="fas fa-angle-left" aria-hidden="true"></svg></span><span class="digi-post-navigation__text digi-post-navigation__text--prev"><span class="digi-post-navigation__label digi-post-navigation__label--prev">Anterior</span><span class="digi-post-navigation__title digi-post-navigation__title--prev">Entrada <em>anterior</em></span></span></a></div><div class="digi-post-navigation__separator-wrapper"><div class="digi-post-navigation__separator"></div></div><div class="digi-post-navigation__next digi-post-navigation__link"><a href="https://sitio.test/siguiente/" rel="next"><span class="digi-post-navigation__text digi-post-navigation__text--next"><span class="digi-post-navigation__label digi-post-navigation__label--next">Siguiente</span><span class="digi-post-navigation__title digi-post-navigation__title--next">Entrada siguiente & más</span></span><span class="digi-post-navigation__arrow digi-post-navigation__arrow--next" aria-hidden="true"><svg class="e-font-icon-svg" data-icon="fas fa-angle-right" aria-hidden="true"></svg></span></a></div></nav>' ), 'Marcado por defecto: ' . $html );
	h_check( ! str_contains( $html, 'digi-post-navigation__sr' ), 'Con etiqueta o título no hace falta texto oculto.' );
	$bare = h_render( $own, array( 'show_label' => '', 'show_title' => '', 'show_borders' => '' ) );
	h_check( str_contains( $bare, '<span class="digi-post-navigation__sr">Entrada anterior</span>' ) && str_contains( $bare, '<span class="digi-post-navigation__sr">Entrada siguiente</span>' ) && ! str_contains( $bare, '__separator' ) && ! str_contains( $bare, '__label' ), 'Sólo flechas: los enlaces conservan un nombre accesible; sin bordes, sin separador.' );
	$noarrow = h_render( $own, array( 'show_arrow' => '' ) );
	h_check( ! str_contains( $noarrow, '__arrow' ) && ! str_contains( $noarrow, '<svg' ), 'Sin flechas, ningún icono.' );
	$empty_icon = h_render( $own, array( 'arrow_previous_icon' => array( 'value' => '', 'library' => '' ) ) );
	h_check( str_contains( $empty_icon, 'digi-post-navigation__arrow--prev" aria-hidden="true"></span>' ), 'Un icono vaciado deja la flecha sin icono, como PRO.' );
	$evil = h_render( $own, array( 'prev_label' => 'Antes <script>alert(1)</script><b onclick="x()">ya</b>', 'next_label' => '<img src=x onerror=alert(1)>Luego' ) );
	h_check( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onclick' ) && ! str_contains( $evil, 'onerror' ) && str_contains( $evil, '<b>ya</b>' ), 'Las etiquetas admiten el HTML permitido y nada más (PRO las imprimía sin filtrar).' );
	$GLOBALS['h_wp']['no_prev'] = true;
	$first = h_render( $own );
	h_check( str_contains( $first, '<div class="digi-post-navigation__prev digi-post-navigation__link"></div>' ) && str_contains( $first, 'rel="next"' ), 'En la primera entrada, la columna anterior queda vacía, como PRO.' );
	$GLOBALS['h_wp']['no_prev'] = false;
	// «En el mismo término»: el tipo de la entrada actual y su taxonomía, como PRO.
	$same = array( 'in_same_term' => array( 'producto', 'post' ), 'post_taxonomy' => 'post_tag' );
	foreach ( array( 'post' => array( true, 'post_tag' ), 'page' => array( false, 'category' ), 'producto' => array( true, '' ) ) as $type => $expected ) {
		$GLOBALS['h_wp']['queried'] = $type;
		$GLOBALS['h_wp']['links']   = array();
		h_render( $pro, $same );
		$pro_links                = $GLOBALS['h_wp']['links'];
		$GLOBALS['h_wp']['links'] = array();
		h_render( $own, $same );
		h_check( $pro_links === $GLOBALS['h_wp']['links'] && array( array( 'prev', $expected[0], $expected[1] ), array( 'next', $expected[0], $expected[1] ) ) === $pro_links, "Tipo $type: los mismos argumentos de enlace que PRO." );
	}
	$GLOBALS['h_wp']['queried'] = 'post';
	// Mismos textos visibles que PRO (salvo el texto oculto «Prev»/«Next» que PRO metía en las flechas).
	$words = static function ( $html ) {
		return trim( preg_replace( '/\s+/', ' ', strip_tags( str_replace( '<', ' <', preg_replace( '#<span class="elementor-screen-only">[^<]*</span>#', '', $html ) ) ) ) );
	};
	$pro_settings = array( 'prev_label' => 'Previous', 'next_label' => 'Next' );
	h_check( $words( h_render( $pro, $pro_settings ) ) === $words( h_render( $own, $pro_settings ) ), 'Los mismos textos visibles que PRO: ' . $words( h_render( $own, $pro_settings ) ) );
	preg_match_all( '/href="([^"]+)"/', h_render( $pro ), $pro_hrefs );
	preg_match_all( '/href="([^"]+)"/', h_render( $own ), $own_hrefs );
	h_check( $pro_hrefs[1] === $own_hrefs[1], 'Los mismos destinos que PRO.' );
	// Markdown como PRO.
	eval( 'function get_previous_post() { return 3; } function get_next_post() { return null; } function get_the_title( $p ) { return "Hola [mundo]"; } function get_permalink( $p ) { return "https://sitio.test/hola/"; }' ); // phpcs:ignore -- sólo pruebas.
	$md = new Post_Navigation();
	$md->settings = array( 'prev_label' => '', 'next_label' => 'Luego' );
	h_check( '[Anterior: Hola \\[mundo\\]](https://sitio.test/hola/)' === $md->render_markdown(), 'Markdown con la anterior, como PRO: ' . $md->render_markdown() );

	// 4. Conversión de Post Navigation de PRO.
	\Digitalisimo\Elements\Convert\Registry::reset();
	h_check( null !== \Digitalisimo\Elements\Convert\Registry::map( 'post-navigation', true ) && 'hide' === $map['panel'], 'El mapa existe y oculta el widget de PRO cuando está listo.' );
	$cases = array(
		'defaults'   => array(),
		'content'    => array( 'show_label' => 'yes', 'prev_label' => 'Atrás', 'next_label' => 'Adelante', 'show_arrow' => 'yes', 'arrow_previous_icon' => array( 'value' => 'fas fa-chevron-left', 'library' => 'fa-solid' ), 'arrow_next_icon' => array( 'value' => array( 'id' => 9, 'url' => 'https://sitio.test/f.svg' ), 'library' => 'svg' ), 'show_title' => '', 'show_borders' => '', 'in_same_term' => array( 'post' ), 'post_taxonomy' => 'post_tag' ),
		'styles'     => array( 'label_color' => '#111111', 'label_hover_color' => '#222222', 'label_hover_color_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ), 'label_typography_typography' => 'custom', 'label_typography_font_size' => array( 'unit' => 'px', 'size' => 12 ), 'text_color' => '#333333', 'hover_color' => '#444444', 'hover_color_transition_duration' => array( 'unit' => 's', 'size' => 1 ), 'title_typography_typography' => 'custom', 'title_typography_font_weight' => '700', 'arrow_color' => '#555555', 'arrow_hover_color' => '#666666', 'arrow_hover_color_transition_duration' => array( 'unit' => 'ms', 'size' => 200 ), 'arrow_size' => array( 'unit' => 'px', 'size' => 20 ), 'arrow_padding' => array( 'unit' => 'px', 'size' => 8 ), 'sep_color' => '#777777', 'borders_width' => array( 'unit' => 'px', 'size' => 2 ), 'borders_spacing' => array( 'unit' => 'px', 'size' => 12 ) ),
		'responsive' => array( 'arrow_size_tablet' => array( 'unit' => 'px', 'size' => 24 ), 'arrow_size_mobile' => array( 'unit' => 'px', 'size' => 18 ), 'borders_width_mobile' => array( 'unit' => 'px', 'size' => 1 ), 'label_typography_typography' => 'custom', 'label_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 10 ) ),
		'globals'    => array( 'label_color' => '', 'text_color' => '', '__globals__' => array( 'label_color' => 'globals/colors?id=accent', 'text_color' => 'globals/colors?id=primary', 'sep_color' => 'globals/colors?id=text', 'label_typography_typography' => 'globals/typography?id=accent', 'title_typography_typography' => 'globals/typography?id=primary', '_background_color' => 'globals/colors?id=secondary' ) ),
		'dynamic'    => array( '__dynamic__' => array( 'prev_label' => '[elementor-tag id="a1" name="post-title" settings="%7B%7D"]', 'next_label' => '[elementor-tag id="a2" name="site-title" settings="%7B%7D"]' ) ),
		'css'        => array( 'custom_css' => "selector .elementor-post-navigation__link a{gap:4px}\nselector.elementor-post-navigation-borders-yes .elementor-post-navigation__separator{width:3px}\nselector span.post-navigation__prev--label, selector .post-navigation__arrow-wrapper.post-navigation__arrow-next{color:red}\nselector .elementor-post-navigation__link__next .post-navigation__next--title{color:blue}\n.elementor-widget-post-navigation .post-navigation__prev--title{color:#000}", '_css_classes' => 'mi-nav', '_padding' => array( 'top' => '5', 'right' => '0', 'bottom' => '5', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
	);
	list( $out, $codes ) = h_convert( 'post-navigation', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún descarte ni aproximación; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	h_check( 'post-navigation' === $d['_digitalisimo_pro'] && 'Previous' === $d['prev_label'] && 'Next' === $d['next_label'], 'Un Post Navigation sin cambios llega con los textos de PRO.' );
	h_check( $words( h_render( $pro, $cases['defaults'] + array( 'prev_label' => 'Previous', 'next_label' => 'Next' ) ) ) === $words( h_render( $own, $d ) ), 'Lo convertido se pinta con los mismos textos que PRO.' );
	$c = $out['content'];
	h_check( 'Atrás' === $c['prev_label'] && 'fas fa-chevron-left' === $c['arrow_previous_icon']['value'] && 'svg' === $c['arrow_next_icon']['library'] && '' === $c['show_title'] && '' === $c['show_borders'] && array( 'post' ) === $c['in_same_term'] && 'post_tag' === $c['post_taxonomy'], 'Contenido: etiquetas, iconos (también SVG), título, bordes y «En el mismo término» con su taxonomía.' );
	$GLOBALS['h_wp']['links'] = array();
	h_render( $own, $c );
	h_check( array( array( 'prev', true, 'post_tag' ), array( 'next', true, 'post_tag' ) ) === $GLOBALS['h_wp']['links'], 'Lo convertido enlaza dentro del mismo término, como PRO.' );
	$s = $out['styles'];
	foreach ( $cases['styles'] as $key => $value ) {
		h_check( $value === $s[ $key ], "Estilo $key pasa con su nombre." );
	}
	h_check( 24 === $out['responsive']['arrow_size_tablet']['size'] && 18 === $out['responsive']['arrow_size_mobile']['size'] && 1 === $out['responsive']['borders_width_mobile']['size'] && 10 === $out['responsive']['label_typography_font_size_mobile']['size'], 'Valores por dispositivo.' );
	h_check( $cases['globals']['__globals__'] === $out['globals']['__globals__'], 'Colores y tipografías globales del kit siguen a su control.' );
	h_check( $cases['dynamic']['__dynamic__'] === $out['dynamic']['__dynamic__'], 'Las etiquetas dinámicas siguen a su control.' );
	h_check( "selector .digi-post-navigation__link a{gap:4px}\nselector.digi-post-navigation--borders-yes .digi-post-navigation__separator{width:3px}\nselector span.digi-post-navigation__label--prev, selector .digi-post-navigation__arrow.digi-post-navigation__arrow--next{color:red}\nselector .digi-post-navigation__text--next .digi-post-navigation__title--next{color:blue}\n.elementor-widget-digitalisimo-post-navigation .digi-post-navigation__title--prev{color:#000}" === $out['css']['custom_css'] && 'mi-nav' === $out['css']['_css_classes'], 'CSS personalizado con las clases propias; Avanzado intacto: ' . $out['css']['custom_css'] );
	h_classes_exist( $map, $own, h_render( $own ) );
	// La taxonomía de un tipo que no está registrado al convertir (su plugin inactivo durante «Migrar» o al importar un
	// kit) se conserva: al volver el tipo, «En el mismo término» sigue con ella, como en PRO.
	list( $kept, $gone ) = \Digitalisimo\Elements\Convert\Translator::translate( array( 'in_same_term' => array( 'portfolio' ), 'portfolio_taxonomy' => 'portfolio_cat' ), \Digitalisimo\Elements\Convert\Registry::map( 'post-navigation', true ) );
	h_check( array() === $gone && 'portfolio_cat' === $kept['portfolio_taxonomy'] && array( 'portfolio' ) === $kept['in_same_term'], 'La taxonomía de un tipo que hoy no existe pasa tal cual, sin «Pendiente de revisar»: ' . json_encode( $gone ) );

	echo 'DIGITALÍSIMO Elements: Navegación entre entradas (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B) y su mapa desde el Post Navigation de PRO (' . count( $cases ) . " casos) validados.\n";
}
