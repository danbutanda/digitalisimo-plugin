<?php
/**
 * Comprobaciones de pro-loop.sh (C5, frente J) en WordPress real con el constructor incluido:
 *
 * 1. Registro: Cuadrícula de bucle, Carrusel de bucle y Filtro de taxonomías propios.
 * 2. Controles: cada control del widget de PRO (también los de su base, de Entradas, del rasgo de carrusel y de cada
 *    piel, con su prefijo) existe en el propio con el mismo tipo, condiciones, dispositivos, sección y pestaña, y con el
 *    mismo valor por defecto salvo los textos (que se traducen) y los selectores de las variables propias.
 * 3. Marcado del servidor de los widgets propios con las entradas de prueba: rejilla y elementos, plantillas alternas,
 *    paginación, «Cargar más», sin resultados, bucle de términos, carrusel y filtro.
 * 4. Conversión de ajustes reales de PRO con los mapas (también en borrador) y pintado de lo convertido.
 *
 * Escribe `results.txt` (líneas `OK` / `FALLA`) y `render-*.html` en la carpeta de la sonda.
 */
require '/wordpress/wp-load.php';

const DIGI_LOOP_PROBE = '/wordpress/wp-content/probe';

$GLOBALS['digi_loop_lines'] = array();
function digi_loop_ok( $ok, $message ) {
	$GLOBALS['digi_loop_lines'][] = ( $ok ? 'OK    ' : 'FALLA ' ) . $message;
}
register_shutdown_function(
	static function () {
		$e = error_get_last();
		if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			$GLOBALS['digi_loop_lines'][] = 'FALLA Fatal error: ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'];
		}
		file_put_contents( DIGI_LOOP_PROBE . '/results.txt', implode( "\n", $GLOBALS['digi_loop_lines'] ) . "\n" );
	}
);

$fx      = json_decode( (string) file_get_contents( DIGI_LOOP_PROBE . '/fixtures.json' ), true );
$plugin  = \Elementor\Plugin::$instance;
$widgets = $plugin->widgets_manager;
$pairs   = array(
	'loop-grid'       => 'digitalisimo-loop-grid',
	'loop-carousel'   => 'digitalisimo-loop-carousel',
	'taxonomy-filter' => 'digitalisimo-taxonomy-filter',
);

// 1. Registro.
foreach ( $pairs as $pro => $own ) {
	$w = $widgets->get_widget_types( $own );
	digi_loop_ok( null !== $w, "$own registrado" );
	digi_loop_ok( null !== $widgets->get_widget_types( $pro ), "$pro sigue registrado" );
	if ( $w ) {
		digi_loop_ok( in_array( 'digitalisimo', $w->get_categories(), true ), "$own en la categoría DIGITALÍSIMO" );
	}
}

// 2. Controles.
$text_types = array( 'text', 'textarea', 'wysiwyg', 'raw_html', 'heading', 'alert', 'notice', 'divider', 'deprecated_notice' );
$normalize  = static function ( $value ) use ( &$normalize ) {
	if ( is_array( $value ) ) {
		ksort( $value );
		return array_map( $normalize, $value );
	}
	return $value;
};
foreach ( $pairs as $pro => $own ) {
	$a = $widgets->get_widget_types( $pro );
	$b = $widgets->get_widget_types( $own );
	if ( ! $a || ! $b ) {
		continue;
	}
	$ca     = $a->get_controls();
	$cb     = $b->get_controls();
	$issues = array();
	foreach ( $ca as $name => $control ) {
		if ( in_array( $control['type'], array( 'section', 'tabs', 'tab' ), true ) ) {
			continue;
		}
		if ( ! isset( $cb[ $name ] ) ) {
			$issues[] = "falta $name";
			continue;
		}
		$mine = $cb[ $name ];
		if ( $control['type'] !== $mine['type'] ) {
			$issues[] = "$name: tipo {$control['type']} ≠ {$mine['type']}";
		}
		foreach ( array( 'condition', 'conditions' ) as $key ) {
			if ( $normalize( $control[ $key ] ?? null ) !== $normalize( $mine[ $key ] ?? null ) ) {
				$issues[] = "$name: $key " . wp_json_encode( $control[ $key ] ?? null ) . ' ≠ ' . wp_json_encode( $mine[ $key ] ?? null );
			}
		}
		foreach ( array( 'section', 'tab', 'responsive', 'frontend_available', 'prefix_class', 'render_type' ) as $key ) {
			if ( ( $control[ $key ] ?? null ) !== ( $mine[ $key ] ?? null ) && ! ( 'prefix_class' === $key && 'digitalisimo-taxonomy-filter' === $own ) ) {
				$issues[] = "$name: $key " . wp_json_encode( $control[ $key ] ?? null ) . ' ≠ ' . wp_json_encode( $mine[ $key ] ?? null );
			}
		}
		$default_a = $control['default'] ?? null;
		$default_b = $mine['default'] ?? null;
		if ( ! in_array( $control['type'], $text_types, true ) && ! is_string( $default_a ) || ( is_string( $default_a ) && ( '' === $default_a || preg_match( '/^[\d.]+$|^[a-z_\-]+$|^[A-Z]+$|^\[/', $default_a ) ) ) ) {
			if ( $normalize( $default_a ) !== $normalize( $default_b ) && 'edit_handle_selector' !== $name ) {
				$issues[] = "$name: default " . wp_json_encode( $default_a ) . ' ≠ ' . wp_json_encode( $default_b );
			}
		}
		if ( isset( $control['options'] ) && is_array( $control['options'] ) && 'icons' !== $control['type'] ) {
			if ( array_map( 'strval', array_keys( $control['options'] ) ) !== array_map( 'strval', array_keys( (array) ( $mine['options'] ?? array() ) ) ) ) {
				$issues[] = "$name: opciones " . wp_json_encode( array_keys( $control['options'] ) ) . ' ≠ ' . wp_json_encode( array_keys( (array) ( $mine['options'] ?? array() ) ) );
			}
		}
		foreach ( array( 'tablet_default', 'mobile_default', 'widescreen_default', 'laptop_default', 'tablet_extra_default', 'mobile_extra_default' ) as $key ) {
			if ( isset( $control[ $key ] ) && $normalize( $control[ $key ] ) !== $normalize( $mine[ $key ] ?? null ) ) {
				$issues[] = "$name: $key " . wp_json_encode( $control[ $key ] ) . ' ≠ ' . wp_json_encode( $mine[ $key ] ?? null );
			}
		}
	}
	$extra = array_diff( array_keys( $cb ), array_keys( $ca ) );
	$extra = array_filter(
		$extra,
		static function ( $name ) use ( $cb ) {
			return ! in_array( $cb[ $name ]['type'], array( 'section', 'tabs', 'tab' ), true ) && 0 !== strpos( $name, 'digitalisimo_' );
		}
	);
	digi_loop_ok( ! $issues, "$own: los " . count( $ca ) . " controles de $pro, con el mismo tipo, condiciones, dispositivos y sección" . ( $issues ? ': ' . implode( ' | ', array_slice( $issues, 0, 25 ) ) . ( count( $issues ) > 25 ? ' …(' . count( $issues ) . ')' : '' ) : '' ) );
	digi_loop_ok( ! $extra, "$own: ningún control que $pro no tenga" . ( $extra ? ': ' . implode( ', ', $extra ) : '' ) );
	file_put_contents( DIGI_LOOP_PROBE . "/controls-$own.json", wp_json_encode( array( 'pro' => array_keys( $ca ), 'own' => array_keys( $cb ) ) ) );
}

// 3. Pintado.
$counter = 0;
function digi_loop_render( array $elements, string $name ) {
	global $counter, $wp_query, $wp_the_query, $post;
	$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $name, 'post_name' => $name ) );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
	$tree = array( array( 'id' => substr( md5( $name ), 0, 7 ), 'elType' => 'container', 'isInner' => false, 'settings' => array(), 'elements' => $elements ) );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $tree ) ) );
	$wp_the_query = new WP_Query( array( 'page_id' => $id ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$wp_query     = $wp_the_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$wp_query->the_post();
	$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
	wp_reset_postdata();
	file_put_contents( DIGI_LOOP_PROBE . "/render-$name.html", $html );
	return $html;
}
function digi_loop_widget( string $id, string $type, array $settings ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
}
$card = (string) $fx['card'];
$alt  = (string) $fx['alt'];

$html = digi_loop_render( array( digi_loop_widget( 'g000001', 'digitalisimo-loop-grid', array( 'template_id' => $card ) ) ), 'grid-defaults' );
preg_match_all( '/class="[^"]*\be-loop-item\b[^"]*"/', $html, $items );
digi_loop_ok( 6 === count( $items[0] ), 'Cuadrícula por defecto: 6 elementos (' . count( $items[0] ) . ')' );
digi_loop_ok( (bool) preg_match( '/<div class="digi-loop-grid__items" role="list" data-digi-items/', $html ), 'Cuadrícula: contenedor propio con role="list" y data-digi-items' );
digi_loop_ok( 6 === substr_count( $html, 'role="listitem"' ), 'Cuadrícula: cada Elemento de bucle es role="listitem"' );
digi_loop_ok( false !== strpos( $html, 'Entrada de prueba 9' ) && false === strpos( $html, 'Entrada de prueba 3<' ), 'Cuadrícula: las 6 más recientes, como la consulta de PRO' );
digi_loop_ok( false !== strpos( $html, 'id="loop-dynamic-' . $card . '"' ) || false !== strpos( $html, 'e-loop-item-' ), 'Cuadrícula: CSS dinámico de cada elemento y clase e-loop-item-<id>' );
digi_loop_ok( false === strpos( $html, 'elementor-loop-container' ) && false === strpos( $html, 'swiper' ), 'Cuadrícula: sin clases del Loop Grid de PRO ni de Swiper' );

$html = digi_loop_render( array( digi_loop_widget( 'g000002', 'digitalisimo-loop-grid', array( 'template_id' => $card, 'posts_per_page' => 4, 'pagination_type' => 'numbers_and_prev_next' ) ) ), 'grid-numbers' );
digi_loop_ok( (bool) preg_match( '/<nav class="digi-loop__pagination" aria-label="Paginación">/', $html ) && false !== strpos( $html, 'class="page-numbers next"' ), 'Cuadrícula: números y anterior/siguiente con enlaces reales' );
digi_loop_ok( (bool) preg_match( '/href="[^"]*grid-numbers\/2\/"/', $html ), 'Cuadrícula: el enlace de la página 2 es la URL /2/ de la página, como PRO' );

$html = digi_loop_render( array( digi_loop_widget( 'g000003', 'digitalisimo-loop-grid', array( 'template_id' => $card, 'posts_per_page' => 4, 'pagination_type' => 'numbers', 'pagination_load_type' => 'ajax', 'auto_scroll' => 'yes' ) ) ), 'grid-ajax' );
digi_loop_ok( false !== strpos( $html, 'data-digi-ajax="scroll"' ) && (bool) preg_match( '/href="[^"]*e-page-g000003=2/', $html ), 'Cuadrícula: paginación sin recargar marcada para loop-grid.js, con e-page-<id> como PRO' );

$html = digi_loop_render( array( digi_loop_widget( 'g000004', 'digitalisimo-loop-grid', array( 'template_id' => $card, 'posts_per_page' => 4, 'pagination_type' => 'load_more_on_click' ) ) ), 'grid-more' );
digi_loop_ok( (bool) preg_match( '/class="digi-loop__anchor" data-page="1" data-max-page="3" data-next-page="[^"]+"/', $html ) && false !== strpos( $html, '<button type="button" class="elementor-button' ), 'Cuadrícula: «Cargar más» con ancla y botón de la base común' );

$html = digi_loop_render(
	array(
		digi_loop_widget(
			'g000005',
			'digitalisimo-loop-grid',
			array(
				'template_id'         => $card,
				'alternate_template'  => 'yes',
				'alternate_templates' => array( array( '_id' => 'alt0001', 'template_id' => $alt, 'repeat_template' => 2, 'show_once' => '' ) ),
			)
		),
	),
	'grid-alternate'
);
digi_loop_ok( 3 === substr_count( $html, 'elementor-repeater-item-alt0001' ) && 3 === substr_count( $html, 'data-elementor-id="' . $alt . '"' ), 'Cuadrícula: la plantilla alterna cada 2 elementos (3 de 6), con su clase de fila' );

$html = digi_loop_render( array( digi_loop_widget( 'g000006', 'digitalisimo-loop-grid', array( 'template_id' => $card, 'post_query_post_type' => 'by_id', 'post_query_posts_ids' => array( 999999 ), 'enable_nothing_found_message' => 'yes', 'nothing_found_message_html_tag' => 'h2' ) ) ), 'grid-nothing' );
digi_loop_ok( (bool) preg_match( '/<div class="digi-loop-grid__nothing"><h2 class="digi-loop-grid__nothing-text">/', $html ), 'Cuadrícula: sin resultados, el mensaje con su etiqueta' );

$html = digi_loop_render( array( digi_loop_widget( 'g000007', 'digitalisimo-loop-grid', array( 'template_id' => $card, '_skin' => 'post_taxonomy', 'post_taxonomy_query_post_type' => 'category' ) ) ), 'grid-terms' );
preg_match_all( '/class="[^"]*\be-loop-item\b/', $html, $items );
digi_loop_ok( count( $items[0] ) >= 3 && false === strpos( $html, 'digi-loop__pagination' ), 'Cuadrícula de términos: un elemento por categoría, sin paginación (' . count( $items[0] ) . ')' );

$html = digi_loop_render( array( digi_loop_widget( 'c000001', 'digitalisimo-loop-carousel', array( 'template_id' => $card, 'autoplay' => '' ) ) ), 'carousel-defaults' );
digi_loop_ok( (bool) preg_match( '/<section class="digi-crs digi-loop-carousel"[^>]*aria-roledescription="carrusel"/', $html ), 'Carrusel: marcado del motor digi-crs' );
digi_loop_ok( 6 === substr_count( $html, 'digi-crs__slide' ) && false !== strpos( $html, 'aria-label="1 de 6"' ) && false !== strpos( $html, 'aria-label="6 de 6"' ), 'Carrusel: cada Elemento de bucle es una diapositiva «N de 6»' );
digi_loop_ok( false !== strpos( $html, 'digi-crs__prev' ) && 6 === substr_count( $html, 'class="digi-crs__dot"' ), 'Carrusel: flechas y puntos por defecto' );
digi_loop_ok( false === strpos( $html, 'swiper' ), 'Carrusel: sin Swiper' );

$html = digi_loop_render(
	array(
		digi_loop_widget( 'g000008', 'digitalisimo-loop-grid', array( 'template_id' => $card ) ),
		digi_loop_widget( 'f000001', 'digitalisimo-taxonomy-filter', array( 'selected_element' => 'g000008', 'taxonomy' => 'category' ) ),
	),
	'filter'
);
preg_match_all( '/<button type="button" class="digi-tfilter__item" data-filter="([^"]+)" aria-pressed="(true|false)">/', $html, $buttons );
digi_loop_ok( array( '__all', 'casos', 'guias', 'noticias' ) === $buttons[1] && 'true' === $buttons[2][0], 'Filtro: «Todos» (pulsado) primero y un botón por categoría con entradas (' . implode( ',', $buttons[1] ) . ')' );
digi_loop_ok( (bool) preg_match( '/<search class="digi-tfilter" role="search" aria-label="Filtrar por Categoría" data-loop="g000008" data-taxonomy="category" data-join="DISABLED"/', $html ), 'Filtro: <search> con nombre accesible y su bucle' );

// Filtro aplicado por la URL (`e-filter-<bucle>-<taxonomía>`), como lo pide el script.
$_SERVER['QUERY_STRING'] = 'e-filter-g000009-category=guias';
$module                  = new \ElementorPro\Modules\LoopFilter\Module();
$html                    = digi_loop_render(
	array(
		digi_loop_widget( 'g000009', 'digitalisimo-loop-grid', array( 'template_id' => $card ) ),
		digi_loop_widget( 'f000002', 'digitalisimo-taxonomy-filter', array( 'selected_element' => 'g000009', 'taxonomy' => 'category' ) ),
	),
	'filter-guias'
);
remove_filter( 'elementor/query/query_args', array( $module, 'filter_loop_query' ), 10 );
preg_match_all( '/class="[^"]*\be-loop-item\b/', $html, $items );
digi_loop_ok( 3 === count( $items[0] ) && (bool) preg_match( '/data-filter="guias" aria-pressed="true"/', $html ), 'Filtro por URL: el módulo loop-filter filtra el bucle propio por su ID (3 guías) y el botón queda pulsado (' . count( $items[0] ) . ')' );
unset( $_SERVER['QUERY_STRING'] );

// 4. Conversión con los mapas (también en borrador) y pintado de lo convertido.
$samples = array(
	'loop-grid'       => array( 'template_id' => $card, 'columns' => '4', 'columns_tablet' => '2', 'posts_per_page' => 8, 'post_query_post_type' => 'post', 'post_query_orderby' => 'title', 'post_query_order' => 'asc', 'pagination_type' => 'numbers', 'pagination_load_type' => 'ajax', 'column_gap' => array( 'unit' => 'px', 'size' => 12 ), 'nothing_found_message_typography_typography' => 'custom', 'custom_css' => 'selector .elementor-loop-container{gap:1px}', '__globals__' => array( 'nothing_found_message_color' => 'globals/colors?id=primary' ) ),
	'loop-carousel'   => array( 'template_id' => $card, 'slides_to_show' => '2', 'autoplay' => '', 'arrows' => 'yes', 'pagination' => 'fraction', 'image_spacing_custom' => array( 'unit' => 'px', 'size' => 20 ), 'post_query_post_type' => 'post' ),
	'taxonomy-filter' => array( 'selected_element' => 'g000010', 'taxonomy' => 'category', 'multiple_selection' => 'yes', 'logical_combination' => 'OR', 'taxonomy_filter_active_text_color' => '#ff0000', 'taxonomy_filter_normal_border_border' => 'solid', 'custom_css' => 'selector .e-filter-item{gap:0}' ),
);
foreach ( $samples as $type => $settings ) {
	$codes     = array();
	$converted = \Digitalisimo\Elements\Convert\Converter::element( array( 'id' => 'x' . substr( md5( $type ), 0, 6 ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() ), $codes, true );
	$review    = array_filter(
		(array) $codes,
		static function ( $c ) {
			return 'review' === $c['section'];
		}
	);
	digi_loop_ok( $converted && $pairs[ $type ] === $converted['widgetType'] && ! $review, "Conversión de $type: pasa a {$pairs[ $type ]} sin claves sin declarar" . ( $review ? ': ' . implode( ', ', wp_list_pluck( $review, 'key' ) ) : '' ) );
	if ( $converted ) {
		$same = array_intersect_key( $converted['settings'], $settings );
		unset( $same['custom_css'] );
		digi_loop_ok( count( $same ) >= count( $settings ) - 1, "Conversión de $type: los ajustes conservan su nombre" );
	}
}
