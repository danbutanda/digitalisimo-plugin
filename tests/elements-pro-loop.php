<?php
/**
 * Bucles propios de C5 (frente J, docs/pro/c5.md): Cuadrícula de bucle, Carrusel de bucle y Filtro de taxonomías, y
 * sus mapas (`modules/digitalisimo-convert/maps/{loop-grid,loop-carousel,taxonomy-filter}.php`), sin WordPress:
 *
 * - cobertura de cada mapa contra el código de PRO: los controles del widget, de su base (`loop-builder/widgets/base.php`),
 *   de Entradas (`posts/widgets/posts-base.php` con `Button_Widget_Trait`) y, en el carrusel, los del rasgo de carrusel
 *   (los mismos que declara el mapa del carrusel anidado, que `elements-convert.php` comprueba con el rasgo real); las
 *   pieles (`_skin`) por sus prefijos `post_query`, `post_taxonomy`, `product_query` y `product_taxonomy`;
 * - cada clave que el mapa conserva existe como control en el widget propio (sus archivos, la base común y el rasgo);
 * - conversión de ajustes reales de PRO (con `__dynamic__`, `__globals__`, pieles, plantillas alternas y CSS
 *   personalizado) sin ninguna clave sin declarar, con los nombres y el CSS en las clases propias;
 * - recursos: hojas ≤ 4 KB sin `--e-global-*` ni `url()`, scripts sin jQuery ni Swiper y ≤ 8 KB, movimiento reducido,
 *   y ningún nombre de los plugins de origen que la guarda de `scripts/build-packages.sh` rechaza.
 */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['lp'] = array( 'n' => 0 );
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {}
function lp_check( $ok, $message ) {
	$GLOBALS['lp']['n']++;
	if ( ! $ok ) {
		echo 'FALLA ' . $message . "\n";
		exit( 1 );
	}
}

$elements = __DIR__ . '/../digitalisimo-elements/';
require $elements . 'modules/digitalisimo-convert/class-module.php';
\Digitalisimo\Elements\Convert\Module::init();
use Digitalisimo\Elements\Convert\Registry;
use Digitalisimo\Elements\Convert\Translator;

/** Nombres literales de controles y grupos de unos archivos. */
function lp_names( array $files ): array {
	$php = '';
	foreach ( $files as $file ) {
		$php .= "\n" . (string) file_get_contents( $file );
	}
	preg_match_all( "/->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'\s*,/", $php, $c );
	preg_match_all( "/->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'\s*,/s", $php, $g );
	// Los del filtro propio por estado: `'taxonomy_filter_' . $state . '_text_color'` con normal, hover y active.
	preg_match_all( '/\'([a-z_]+_)\'\s*\.\s*\$state\s*\.\s*\'(_[a-z_]+)\'/', $php, $s, PREG_SET_ORDER );
	$states = array();
	foreach ( $s as $m ) {
		foreach ( array( 'normal', 'hover', 'active' ) as $state ) {
			$states[] = $m[1] . $state . $m[2];
		}
	}
	return array_values( array_unique( array_merge( $c[1], $g[1], $states ) ) );
}
/** Todo lo que declara un mapa (y sus repetidores). */
function lp_declared( array $map ): array {
	$out = array_merge( array_keys( (array) ( $map['rename'] ?? array() ) ), (array) ( $map['keep'] ?? array() ), array_keys( (array) ( $map['groups'] ?? array() ) ), array_keys( (array) ( $map['drop'] ?? array() ) ), array_keys( (array) ( $map['approx'] ?? array() ) ), array_keys( (array) ( $map['repeaters'] ?? array() ) ), (array) ( $map['ui'] ?? array() ) );
	foreach ( (array) ( $map['repeaters'] ?? array() ) as $spec ) {
		$out = array_merge( $out, (array) ( $spec['keep'] ?? array() ), (array) ( $spec['ui'] ?? array() ) );
	}
	return array_values( array_unique( $out ) );
}

$pro  = $elements . 'modules/';
$own  = $elements . 'modules/digitalisimo-loop/';
$maps = array(
	'loop-grid'       => array( 'digitalisimo-loop-grid', array( $pro . 'loop-builder/widgets/loop-grid.php', $pro . 'loop-builder/widgets/base.php', $pro . 'posts/widgets/posts-base.php', $pro . 'posts/traits/button-widget-trait.php' ), array( $own . 'widgets/class-loop-grid.php', $own . 'class-loop-base.php', $own . 'class-posts-base.php' ) ),
	'loop-carousel'   => array( 'digitalisimo-loop-carousel', array( $pro . 'loop-builder/widgets/loop-carousel.php', $pro . 'loop-builder/widgets/base.php', $pro . 'posts/widgets/posts-base.php', $pro . 'posts/traits/button-widget-trait.php' ), array( $own . 'widgets/class-loop-carousel.php', $own . 'class-loop-base.php', $elements . 'modules/digitalisimo-carousel/trait-nested-controls.php' ) ),
	'taxonomy-filter' => array( 'digitalisimo-taxonomy-filter', array( $pro . 'loop-filter/widgets/taxonomy-filter.php' ), array( $own . 'widgets/class-taxonomy-filter.php' ) ),
);

// 1. Mapas: destino, estado y cobertura.
foreach ( $maps as $source => list( $target, $pro_files, $own_files ) ) {
	$map = Registry::map( $source, true );
	lp_check( is_array( $map ) && $target === $map['target'] && in_array( $map['status'], array( 'draft', 'ready' ), true ) && 'hide' === $map['panel'], "$source: mapa hacia $target con estado y panel" );
	$declared = lp_declared( $map );
	$missing  = array_diff( lp_names( $pro_files ), $declared );
	lp_check( ! $missing, "$source: el mapa declara cada control de PRO (widget, base, Entradas y botón); faltan: " . implode( ', ', $missing ) );
	if ( 'taxonomy-filter' !== $source ) {
		foreach ( array( 'post_query', 'post_taxonomy', 'product_query', 'product_taxonomy' ) as $prefix ) {
			lp_check( isset( $map['groups'][ $prefix ] ) && $prefix === $map['groups'][ $prefix ], "$source: la piel $prefix pasa con su prefijo" );
		}
	}
	// Lo que se conserva existe en el widget propio (o en lo que hereda).
	$own_names = lp_names( $own_files );
	// Los del rasgo que se arman por concatenación son los del carrusel anidado (comprobados con el rasgo real).
	$absent = array_diff( (array) $map['keep'], $own_names, array( 'icon' ), 'loop-carousel' === $source ? (array) Registry::map( 'nested-carousel', true )['keep'] : array() );
	lp_check( ! $absent, "$target: cada clave conservada es un control propio; faltan: " . implode( ', ', $absent ) );
	foreach ( (array) ( $map['groups'] ?? array() ) as $from => $to ) {
		lp_check( in_array( $to, $own_names, true ) || in_array( $to, array( 'post_query', 'post_taxonomy', 'product_query', 'product_taxonomy' ), true ) || ( 'loop-carousel' === $source && isset( Registry::map( 'nested-carousel', true )['groups'][ $to ] ) ), "$target: el grupo $to existe" );
	}
}
// El carrusel declara lo mismo que el carrusel anidado para el rasgo de carrusel (comprobado con el rasgo real).
$nested = Registry::map( 'nested-carousel', true );
$lc     = lp_declared( Registry::map( 'loop-carousel', true ) );
$trait  = array_diff( lp_declared( $nested ), array( 'carousel_name', 'border_radius', 'content_padding', 'content_background', 'content_border', 'carousel_items', 'slide_title' ) );
lp_check( ! array_diff( $trait, $lc ), 'loop-carousel: cubre los controles del rasgo de carrusel de PRO; faltan: ' . implode( ', ', array_diff( $trait, $lc ) ) );

// 2. Conversión de ajustes reales.
$samples = array(
	'loop-grid'       => array(
		'_skin'                        => 'post',
		'template_id'                  => '42',
		'columns'                      => '4',
		'columns_tablet'               => '2',
		'posts_per_page'               => 8,
		'masonry'                      => 'yes',
		'post_query_post_type'         => 'post',
		'post_query_include'           => array( 'terms' ),
		'post_query_include_term_ids'  => array( '3' ),
		'post_query_orderby'           => 'title',
		'post_query_query_id'          => 'mi_consulta',
		'alternate_template'           => 'yes',
		'alternate_templates'          => array( array( '_id' => 'a1', 'template_id' => '43', 'repeat_template' => 3, 'column_span' => '2', 'column_span_tablet' => '1', 'static_position' => 'yes' ) ),
		'pagination_type'              => 'load_more_on_click',
		'text'                         => 'Ver más',
		'selected_icon'                => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ),
		'load_more_button_align'       => 'justify',
		'typography_typography'        => 'custom',
		'typography_font_size'         => array( 'unit' => 'px', 'size' => 18 ),
		'background_background'        => 'classic',
		'background_color'             => '#245EFF',
		'border_border'                => 'solid',
		'border_radius'                => array( 'unit' => 'px', 'top' => '4' ),
		'button_text_color'            => '#fff',
		'nothing_found_message_typography_typography' => 'custom',
		'__dynamic__'                  => array( 'nothing_found_message_text' => '[elementor-tag id="1" name="site-title" settings="%7B%7D"]' ),
		'__globals__'                  => array( 'pagination_color' => 'globals/colors?id=primary', 'typography_typography' => 'globals/typography?id=accent' ),
		'custom_css'                   => 'selector .elementor-loop-container .e-loop-item{gap:0} selector .elementor-pagination a{color:red}',
		'digitalisimo_saved_query'     => '12',
		'_element_id'                  => 'rejilla',
	),
	'loop-carousel'   => array(
		'_skin'                         => 'product',
		'template_id'                   => '42',
		'product_query_post_type'       => 'sale',
		'slides_to_show'                => '4',
		'slides_to_show_mobile'         => '1',
		'autoplay'                      => '',
		'arrows_size'                   => array( 'unit' => 'px', 'size' => 30 ),
		'arrows_normal_background_background' => 'classic',
		'pagination'                    => 'fraction',
		'typography_fraction_typography' => 'custom',
		'image_spacing_custom'          => array( 'unit' => 'px', 'size' => 24 ),
		'custom_css'                    => 'selector .swiper-slide{opacity:1} selector .elementor-swiper-button-next{left:0}',
	),
	'taxonomy-filter' => array(
		'selected_element'                     => 'abc1234',
		'taxonomy'                             => 'category',
		'direction_mobile'                     => 'vertical',
		'multiple_selection'                   => 'yes',
		'logical_combination'                  => 'OR',
		'taxonomy_filter_typography_typography' => 'custom',
		'taxonomy_filter_active_background_background' => 'classic',
		'taxonomy_filter_active_background_color' => '#000',
		'taxonomy_filter_hover_border_border'  => 'solid',
		'__dynamic__'                          => array( 'first_item_title' => '[elementor-tag id="2" name="site-title" settings="%7B%7D"]' ),
		'custom_css'                           => 'selector .e-filter .e-filter-item[aria-pressed=true]{font-weight:700}',
	),
);
foreach ( $samples as $source => $settings ) {
	$map = array( 'status' => 'ready' ) + Registry::map( $source, true );
	list( $out, $codes ) = Translator::translate( $settings, $map );
	$review = array_filter(
		$codes,
		static function ( $c ) {
			return 'review' === $c['section'];
		}
	);
	lp_check( ! $review, "$source: ninguna clave sin declarar al convertir: " . implode( ', ', array_column( $review, 'key' ) ) );
	foreach ( $settings as $key => $value ) {
		if ( 'custom_css' !== $key ) {
			lp_check( array_key_exists( $key, $out ), "$source: $key conserva su nombre" );
		}
	}
	lp_check( $source === $out['_digitalisimo_pro'], "$source: marca del traductor" );
}
list( $grid ) = Translator::translate( $samples['loop-grid'], Registry::map( 'loop-grid', true ) );
lp_check( 'selector .digi-loop-grid__items .e-loop-item{gap:0} selector .digi-loop__pagination a{color:red}' === $grid['custom_css'], 'loop-grid: el CSS personalizado pasa a las clases propias: ' . $grid['custom_css'] );
lp_check( 'Ver más' === $grid['text'] && 'a1' === $grid['alternate_templates'][0]['_id'] && '1' === $grid['alternate_templates'][0]['column_span_tablet'], 'loop-grid: botón, filas de plantillas alternas con su _id y valores por dispositivo' );
lp_check( isset( $grid['pagination_prev_label'] ) && isset( $grid['nothing_found_message_text'] ), 'loop-grid: los textos por defecto de PRO viajan como ajustes (se conserva lo que el sitio veía)' );
list( $car ) = Translator::translate( $samples['loop-carousel'], Registry::map( 'loop-carousel', true ) );
lp_check( 'selector .digi-crs__slide{opacity:1} selector .digi-crs__next{left:0}' === $car['custom_css'], 'loop-carousel: el CSS personalizado pasa a las clases del motor: ' . $car['custom_css'] );
list( $flt ) = Translator::translate( $samples['taxonomy-filter'], Registry::map( 'taxonomy-filter', true ) );
lp_check( 'selector .digi-tfilter .digi-tfilter__item[aria-pressed=true]{font-weight:700}' === $flt['custom_css'] && 'abc1234' === $flt['selected_element'], 'taxonomy-filter: CSS en clases propias y el mismo bucle enlazado: ' . $flt['custom_css'] );

// «Separación» de «Cargar más»: el control de PRO escribe `--load-more—spacing`, pero sus hojas leen la variable con la
// raya codificada dos veces (igual en PRO Elements 4.3.2 oficial): el botón, el cargador y el mensaje quedan siempre a
// 30 px. Lo convertido la descarta (pérdida declarada), y el propio, sin valor, deja 30 px en los tres.
$pro_base = (string) file_get_contents( $pro . 'posts/widgets/posts-base.php' );
lp_check( str_contains( $pro_base, "'{{WRAPPER}}' => '--load-more—spacing: {{SIZE}}{{UNIT}};'," ), 'PRO: la separación de «Cargar más» escribe --load-more—spacing' );
foreach ( array( 'widget-posts.min.css', 'widget-loop-grid.min.css' ) as $sheet ) {
	$css = (string) file_get_contents( $elements . 'assets/css/' . $sheet );
	lp_check( ! str_contains( $css, '--load-more—spacing' ) && 3 <= substr_count( $css, 'margin-top:var(--load-more' ) && 3 <= substr_count( $css, 'spacing,30px)' ), "PRO ($sheet): ninguna regla lee la variable del control; el botón, el cargador y el mensaje usan 30 px (si esto cambia, la separación debe volver a conservarse al convertir)" );
}
lp_check( 3 === substr_count( (string) file_get_contents( $own . 'assets/css/loop.css' ), 'margin-top:var(--digi-loop-more-spacing,30px)' ), 'loop.css: sin valor, el botón, el cargador y el mensaje quedan a 30 px, como PRO' );
foreach ( array( 'loop-grid', 'posts', 'archive-posts' ) as $source ) {
	list( $out, $codes ) = Translator::translate( array( 'pagination_type' => 'load_more_on_click', 'load_more_spacing' => array( 'unit' => 'px', 'size' => 50 ), 'load_more_spacing_tablet' => array( 'unit' => 'px', 'size' => 10 ) ), Registry::map( $source, true ) );
	lp_check( ! preg_grep( '/^load_more_spacing/', array_keys( $out ) ) && in_array( 'load_more_spacing', array_column( array_filter( $codes, static function ( $c ) { return 'pro.dropped' === $c['code']; } ), 'key' ), true ), "$source: la separación de «Cargar más» de PRO se descarta al convertir, con su motivo en el informe" );
}
lp_check( in_array( 'load_more_spacing', (array) Registry::map( 'loop-carousel', true )['ui'], true ), 'loop-carousel: la separación de «Cargar más» sigue declarada (control sin valor en el carrusel)' );

// Mampostería: la separación de PRO (el número de «Separación entre filas» en px; 0 sin valor) viaja en
// `--digi-masonry-gap` junto a `--grid-row-gap`, y el envoltorio la reinicia (una cuadrícula anidada no hereda la de
// fuera). El mapa ya no la declara como aproximación.
$grid_php = (string) file_get_contents( $own . 'widgets/class-loop-grid.php' );
lp_check( false !== strpos( $grid_php, "'{{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px'," ), 'loop-grid: row_gap lleva la separación de la mampostería en px, en la misma regla' );
lp_check( false !== strpos( (string) file_get_contents( $own . 'assets/css/loop-grid.css' ), '.elementor-widget-digitalisimo-loop-grid{scroll-margin-top:var(--digi-loop-scroll-offset,initial);--digi-masonry-gap:0px}' ), 'loop-grid.css: sin valor, la mampostería no deja separación (como PRO)' );
lp_check( ! isset( Registry::map( 'loop-grid', true )['approx']['masonry'] ) && in_array( 'masonry', (array) Registry::map( 'loop-grid', true )['keep'], true ), 'loop-grid: la mampostería se conserva sin aproximación' );

// 3. Widgets propios: nombres, títulos y registro del módulo.
$module = (string) file_get_contents( $own . 'class-module.php' );
foreach ( array( 'digitalisimo-loop-grid' => array( 'Loop_Grid', 'Cuadrícula de bucle' ), 'digitalisimo-loop-carousel' => array( 'Loop_Carousel', 'Carrusel de bucle' ), 'digitalisimo-taxonomy-filter' => array( 'Taxonomy_Filter', 'Filtro de taxonomías' ) ) as $name => list( $class, $title ) ) {
	lp_check( false !== strpos( $module, "'$name' => array( 'file' => " ) && false !== strpos( $module, "'$name' => '$title'" ), "$name: en Module::WIDGETS y TITLES («{$title}»)" );
	$file = $own . 'widgets/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
	$php  = (string) file_get_contents( $file );
	lp_check( false !== strpos( $php, "const NAME = '$name';" ) && false !== strpos( $php, "esc_html__( '$title', 'pro-elements' )" ), "$name: nombre y título traducible" );
}

// 4. Recursos.
$assets = $own . 'assets/';
foreach ( array( 'css/loop-grid.css', 'css/loop-carousel.css', 'css/taxonomy-filter.css' ) as $file ) {
	$css = (string) file_get_contents( $assets . $file );
	lp_check( strlen( $css ) <= 4096 && false === strpos( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ), "$file: ≤ 4 KB (" . strlen( $css ) . ' B), sin paleta del núcleo ni url()' );
	if ( preg_match( '/animation:|transition:/', $css ) ) {
		lp_check( false !== strpos( $css, 'prefers-reduced-motion' ), "$file: respeta el movimiento reducido" );
	}
}
foreach ( array( 'js/loop-grid.js', 'js/taxonomy-filter.js', 'js/taxonomy-filter-editor.js' ) as $file ) {
	$js = (string) file_get_contents( $assets . $file );
	lp_check( strlen( $js ) <= 8192 && ! preg_match( '/jQuery|\$\(|swiper|elementorProFrontend|ElementorProFrontendConfig/i', $js ), "$file: ≤ 8 KB (" . strlen( $js ) . ' B), sin jQuery, Swiper ni el código de PRO' );
}
lp_check( ! is_file( $assets . 'js/loop-carousel.js' ), 'El Carrusel de bucle no tiene script propio: usa el del motor (que está al límite de su presupuesto).' );
$all = '';
foreach ( array_merge( glob( $own . 'class-loop*.php' ), glob( $own . 'widgets/class-loop-*.php' ), array( $own . 'widgets/class-taxonomy-filter.php' ), glob( $assets . '*/loop-*' ), glob( $assets . '*/taxonomy-filter*' ), glob( $elements . 'modules/digitalisimo-convert/maps/loop-*.php' ), array( $elements . 'modules/digitalisimo-convert/maps/taxonomy-filter.php', $elements . 'modules/digitalisimo-convert/maps/_loop-pagination.php' ) ) as $file ) {
	$all .= (string) file_get_contents( $file );
}
lp_check( ! preg_match( '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i', $all ), 'Ningún archivo del frente nombra los plugins de origen (guarda de build-packages.sh).' );
lp_check( ! preg_match( '/https?:\/\/(?!www\.w3\.org)/', $all ), 'Ningún archivo del frente apunta a otro sitio.' );

echo 'DIGITALÍSIMO Elements: bucles propios (Cuadrícula, Carrusel y Filtro de taxonomías) y sus mapas: ' . $GLOBALS['lp']['n'] . " comprobaciones en verde.\n";
