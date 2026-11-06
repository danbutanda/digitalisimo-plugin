<?php
/**
 * Entradas (`digitalisimo-posts`), Entradas del archivo (`digitalisimo-archive-posts`) y Portafolio
 * (`digitalisimo-portfolio`), los widgets propios que sustituyen a Posts, Archive Posts y Portfolio de PRO (C5 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c5.md, frente I), su base común (`Posts_Base`, pieles) y sus mapas:
 * - recursos: hojas ≤ 4 KB (Entradas, Tarjetas, la compartida de paginación y Portafolio) y scripts ≤ 6 KB, sin
 *   `--e-global-*`, `url()`, jQuery, Swiper ni los paquetes de PRO, sin nombres vetados por el empaquetado, sólo los
 *   colores que PRO usaba por defecto, y los recursos por instancia (paginación, tarjetas, mampostería, filtro);
 * - **los mismos controles que PRO**, comparados con los de PRO **ejecutando su código** en el entorno simulado
 *   (`elements-pro-posts/env.php`): nombres (también los de las pieles y los heredados de `Posts_Base` y
 *   `Button_Widget_Trait`), tipo, valor por defecto (por dispositivo), opciones, condiciones, sensibilidad al
 *   dispositivo, `frontend_available`, `render_type`, globales y clases del envoltorio; las mismas secciones y grupos;
 * - marcado: clásica, tarjetas (insignia, avatar), contenido completo, metadatos con su espacio alrededor del separador,
 *   «Leer más» con su texto en el nombre accesible, imágenes con `wp_get_attachment_image()`, categorías y etiquetas,
 *   paginación de números y anterior/siguiente con enlaces reales, «Cargar más» con un botón y la infinita, «Nada
 *   encontrado» del archivo, filtro del Portafolio con `aria-pressed` y los términos de cada elemento;
 * - la consulta: el grupo `posts_` del constructor con las entradas por página de la piel y la página actual;
 * - conversión con `Converter::tree()` de ajustes reales de los tres widgets (pieles, responsivos, globales, dinámicos,
 *   CSS personalizado con clases de PRO): ningún `pro.unmapped` y cada clave cae en un control propio.
 */
require __DIR__ . '/elements-pro-posts/env.php';

// ── WordPress mínimo para pintar ─────────────────────────────────────────────────────────────────────────────────
class WP_Query {
	public $posts = array(); public $found_posts = 0; public $max_num_pages = 1; public $in_the_loop = false; public $query_vars = array();
	private $i = -1;
	public function __construct( $args = array() ) { $this->query_vars = (array) $args; }
	public function have_posts() { return $this->i + 1 < count( $this->posts ); }
	public function rewind_posts() { $this->i = -1; }
	public function the_post() { $this->i++; $GLOBALS['pe']['post'] = $this->posts[ $this->i ]; $GLOBALS['post'] = $this->posts[ $this->i ]; }
}
class WP_Error {}
function pe_query( int $count, int $max = 1 ): WP_Query {
	$q = new WP_Query();
	for ( $n = 1; $n <= $count; $n++ ) {
		$q->posts[] = (object) array( 'ID' => 100 + $n, 'post_title' => "Entrada $n", 'post_excerpt' => 1 === $n ? 'Extracto propio de la entrada uno con varias palabras' : '', 'post_status' => 'publish', 'post_type' => 'post' );
	}
	$q->found_posts   = $count;
	$q->max_num_pages = $max;
	return $q;
}
function get_post( $id = null ) { return $GLOBALS['pe']['post'] ?? null; }
function get_permalink( $p = null ) { return 'https://sitio.test/entrada-' . ( $GLOBALS['pe']['post']->ID ?? 0 ) . '/'; }
function get_the_title( $p = null ) { return ( is_object( $p ) ? $p : $GLOBALS['pe']['post'] )->post_title; }
function get_post_class( $classes = array() ) { return array_merge( (array) $classes, array( 'post-' . get_the_ID(), 'post', 'type-post' ) ); }
function get_post_thumbnail_id() { return 2 === ( get_the_ID() % 3 ) ? 0 : get_the_ID() + 1000; }
function wp_attachment_is_image( $id ) { return $id > 0; }
function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'medium_large', 'large' ); }
function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
	$attr = (array) $attr + array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => "Alt $id" );
	$out  = '<img width="300" height="200" src="https://sitio.test/wp-content/uploads/i-' . $id . '-' . $size . '.jpg" srcset="https://sitio.test/wp-content/uploads/i-' . $id . '-768.jpg 768w"';
	foreach ( $attr as $k => $v ) {
		$out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES ) . '"';
	}
	return $out . ' />';
}
function get_the_excerpt( $p = null ) { $post = is_object( $p ) ? $p : get_post(); return '' !== $post->post_excerpt ? $post->post_excerpt : 'Texto generado de la entrada ' . $post->ID . apply_filters( 'excerpt_more', ' [&hellip;]' ); }
function the_excerpt() { echo apply_filters( 'the_excerpt', '<p>' . get_the_excerpt() . '</p>' ); }
function get_the_author() { return 'Autora'; }
function get_the_author_meta( $f, $id = 0 ) { return 'ID' === $f ? 7 : 'Autora Pruebas'; }
function get_the_date() { return '1 de enero de 2026'; }
function get_the_modified_date() { return '2 de enero de 2026'; }
function get_the_time() { return '10:00'; }
function get_option( $k, $d = false ) { return $GLOBALS['pe']['options'][ $k ] ?? $d; }
function comments_number() { echo 'Sin comentarios'; }
function get_avatar( $id, $size, $d = '', $alt = '' ) { return '<img alt="' . $alt . '" class="avatar avatar-' . $size . '" src="https://sitio.test/avatar.png" />'; }
function taxonomy_exists( $t ) { return in_array( $t, array( 'category', 'post_tag' ), true ); }
function get_the_terms( $id, $tax ) { return 'category' === $tax ? array( (object) array( 'term_id' => 5, 'name' => 'Noticias', 'taxonomy' => 'category' ), (object) array( 'term_id' => 6, 'name' => 'Guías', 'taxonomy' => 'category' ) ) : array( (object) array( 'term_id' => 9, 'name' => 'Clave', 'taxonomy' => 'post_tag' ) ); }
function wp_get_post_terms( $id, $tax ) { return 0 === $id % 2 ? array( (object) array( 'term_id' => 5, 'name' => 'Noticias' ) ) : array( (object) array( 'term_id' => 6, 'name' => 'Guías' ), (object) array( 'term_id' => 5, 'name' => 'Noticias' ) ); }
function get_term_link( $t ) { return 'https://sitio.test/t/' . $t->term_id . '/'; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function wp_reset_postdata() { $GLOBALS['pe']['post'] = (object) array( 'ID' => 0, 'post_status' => 'publish', 'post_title' => 'Página' ); }
function is_singular() { return $GLOBALS['pe']['singular'] ?? true; }
function is_front_page() { return false; }
function is_archive() { return ! is_singular(); }
function is_preview() { return false; }
function is_page() { return is_singular(); }
function is_single() { return false; }
function wp_get_referer() { return false; }
function get_query_var( $k, $d = '' ) { return $GLOBALS['pe']['qv'][ $k ] ?? $d; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
function user_trailingslashit( $s, $t = '' ) { return trailingslashit( $s ); }
function get_pagenum_link( $i ) { return 'https://sitio.test/blog/page/' . $i . '/'; }
function add_query_arg( $k, $v, $url ) { return $url . ( strpos( $url, '?' ) ? '&' : '?' ) . $k . '=' . $v; }
function get_queried_object_id() { return 0; }
function wp_list_pluck( $l, $f ) { return array_map( static function ( $o ) use ( $f ) { return $o->$f; }, (array) $l ); }
function paginate_links( $args ) {
	$out = array();
	for ( $i = 1; $i <= $args['total']; $i++ ) {
		$num   = $args['before_page_number'] . $i;
		$out[] = $i === (int) $args['current'] ? '<span aria-current="page" class="page-numbers current">' . $num . '</span>' : '<a class="page-numbers" href="' . str_replace( array( '%_%', '%#%' ), array( $i > 1 ? $args['format'] : '', $i ), $args['base'] ?? 'https://sitio.test/?%_%' ) . '">' . $num . '</a>';
	}
	return $out;
}
function post_password_required( $id ) { return false; }
function get_the_password_form( $id ) { return '<form class="post-password-form"></form>'; }
function setup_postdata( $post ) { return true; }
function get_the_content() { return (string) ( $GLOBALS['pe']['post']->post_content ?? '' ); }
/** Las páginas de una entrada con `<!--nextpage-->`: lo que pinta WordPress con los argumentos de la piel (sin `pagelink`, que sólo leen los lectores de pantalla). */
function wp_link_pages( $args ) { echo $args['before'] . '<a href="https://sitio.test/x/2/">' . $args['link_before'] . '2' . $args['link_after'] . '</a>' . $args['after']; }
function balanceTags( $s, $f = false ) { return $s; } // phpcs:ignore
$GLOBALS['wp_rewrite'] = new class() { public $pagination_base = 'page'; public function using_permalinks() { return true; } };
$GLOBALS['pe']['options'] = array( 'permalink_structure' => '/%postname%/', 'date_format' => 'j \d\e F \d\e Y' );

$root = __DIR__ . '/../digitalisimo-elements/';
$mod  = $root . 'modules/digitalisimo-loop/';
pe_load();
require_once $root . 'modules/digitalisimo-convert/class-module.php';
\Digitalisimo\Elements\Convert\Module::init();
use Digitalisimo\Elements\Convert\Converter;
use Digitalisimo\Elements\Convert\Registry;
use Digitalisimo\Elements\Loop\Module;
use Digitalisimo\Elements\Loop\Posts_Base;

$render = static function ( $widget ) {
	ob_start();
	$skin = $widget->get_current_skin();
	if ( $skin ) {
		$skin->set_parent( $widget );
		$skin->render();
	} else {
		$widget->render();
	}
	return (string) ob_get_clean();
};
$vetoed = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
$checks = 0;
$check  = static function ( $ok, $message ) use ( &$checks ) {
	++$checks;
	check_pe( $ok, $message );
};

// 1. Registro y recursos.
$names = array( 'posts' => array( 'digitalisimo-posts', 'Entradas', 'class-posts.php' ), 'archive-posts' => array( 'digitalisimo-archive-posts', 'Entradas del archivo', 'class-archive-posts.php' ), 'portfolio' => array( 'digitalisimo-portfolio', 'Portafolio', 'class-portfolio.php' ) );
foreach ( $names as $pro => $own ) {
	$class  = ltrim( pe_pairs()[ $pro ][1], '\\' );
	$widget = pe_widget( $class );
	$check( $own[0] === $widget->get_name() && $own[1] === $widget->get_title(), "$pro: nombre y título propios." );
	$check( isset( Module::WIDGETS[ $own[0] ] ) && 'widgets/' . $own[2] === Module::WIDGETS[ $own[0] ]['file'] && $own[1] === Module::TITLES[ $own[0] ] && $class === Module::WIDGETS[ $own[0] ]['class'], "$pro: en Module::WIDGETS y TITLES." );
}
$check( array( 'digitalisimo' ) === pe_widget( 'Digitalisimo\Elements\Loop\Widgets\Posts' )->get_categories() && array( 'theme-elements-archive' ) === pe_widget( 'Digitalisimo\Elements\Loop\Widgets\Archive_Posts' )->get_categories(), 'Categorías: la propia; Entradas del archivo, la de los elementos de archivo (como PRO y C4).' );
$colors_ok = array( 'posts.css' => array( '#777', '#adadad' ), 'posts-cards.css' => array( '#fff', '#69727d', '#eaeaea' ), 'loop.css' => array(), 'portfolio.css' => array( '#fff' ) );
foreach ( $colors_ok as $file => $colors ) {
	$css = (string) file_get_contents( $mod . 'assets/css/' . $file );
	$check( strlen( $css ) > 0 && strlen( $css ) <= 4096, "$file pesa " . strlen( $css ) . ' B (tope 4 096).' );
	$check( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(|swiper|elementor-post\b|elementor-posts|elementor-portfolio/i', $css ), "$file: sin el kit, url(), Swiper ni clases de PRO." );
	preg_match_all( '/#[0-9a-f]{3,6}\b/i', $css, $m );
	$check( array() === array_diff( array_unique( array_map( 'strtolower', $m[0] ) ), $colors ), "$file: sólo los colores que PRO usaba por defecto: " . implode( ', ', $m[0] ) );
	$check( ! preg_match( $vetoed, $css ), "$file: nombra algo que el empaquetado veta." );
}
foreach ( array( 'posts.css' => array( 'prefers-reduced-motion', '.elementor-widget-digitalisimo-posts,.elementor-widget-digitalisimo-archive-posts{--digi-masonry-gap:0px}' ), 'loop.css' => array( 'outline:2px solid;outline-offset:2px', 'prefers-reduced-motion', '.digi-loop--end .digi-loop__more{display:none}', '.digi-loop__more [tabindex]:focus-visible', '.digi-loop__more .elementor-button{cursor:pointer;position:relative;' ), 'portfolio.css' => array( 'outline:2px solid;outline-offset:2px', 'prefers-reduced-motion', '.digi-portfolio__item[hidden]{display:none}', '.elementor-widget-digitalisimo-portfolio{--digi-masonry-gap:0px}', '.elementor-widget-digitalisimo-portfolio .digi-portfolio__filter{background:none;border:0;border-radius:0;color:inherit;', '.elementor-widget-digitalisimo-portfolio .digi-portfolio__filter:is(:hover,:focus){background:none;border:0;border-radius:0;color:inherit;', '.elementor-widget-digitalisimo-portfolio .digi-portfolio__filter:not(:focus-visible){box-shadow:none}' ), 'loop-grid.css' => array( '--digi-masonry-gap:0px}' ) ) as $file => $needles ) {
	foreach ( $needles as $needle ) {
		$check( str_contains( (string) file_get_contents( $mod . 'assets/css/' . $file ), $needle ), "$file: contiene $needle (foco visible, movimiento reducido, estilos del tema y del kit anulados, separación de la mampostería)." );
	}
}
// Los números de la paginación conservan la medida y la separación de PRO (sin `display`, relleno ni mínimo de 24 px: con
// la maqueta de PRO no caben sin solapar el número vecino), y «Cargar más» no anula el borde, la fuente ni el espaciado
// que el kit da a `.elementor-button` (era para un `<button>`).
$loop_css = (string) file_get_contents( $mod . 'assets/css/loop.css' );
$check( ! preg_match( '/\.page-numbers[^{]*\{[^}]*(min-(width|height)|display:inline-block|padding)/', $loop_css ) && ! preg_match( '/\.digi-loop__pagination[^{]*\{[^}]*(min-(width|height)|padding)/', $loop_css ), 'loop.css: los números de la paginación conservan la medida de PRO (sin mínimo de 24 px).' );
$check( ! preg_match( '/\.digi-loop__more \.elementor-button\{[^}]*(border|font-family|letter-spacing)/', $loop_css ), 'loop.css: «Cargar más» no pisa el borde, la fuente ni el espaciado de los botones del kit.' );
// Los botones del filtro tampoco llevan un mínimo de 24 px: el relleno de 8 px de PRO ya lo da con letra normal, y donde
// no (un término de una letra con letra pequeña) un mínimo los ensancharía y movería la barra.
$check( ! preg_match( '/\.digi-portfolio__filter[^{]*\{[^}]*min-(width|height)/', (string) file_get_contents( $mod . 'assets/css/portfolio.css' ) ), 'portfolio.css: los botones del filtro conservan la medida de PRO (sin mínimo de 24 px).' );
// Mampostería: la separación de PRO (el número del ajuste en px, 0 sin valor) viaja en `--digi-masonry-gap`, en la misma
// regla que `--grid-row-gap`, en las pieles con mampostería y en el Portafolio.
$loop_js = (string) file_get_contents( $mod . 'assets/js/loop.js' );
$check( str_contains( $loop_js, "getPropertyValue('--digi-masonry-gap')" ) && ! str_contains( $loop_js, 'cs.rowGap' ), 'loop.js: la mampostería lee --digi-masonry-gap, no el row-gap calculado.' );
foreach ( array( 'loop.js', 'portfolio.js' ) as $file ) {
	$js = (string) file_get_contents( $mod . 'assets/js/' . $file );
	$check( strlen( $js ) <= 6144 && ! preg_match( '/jQuery|\$\(|swiper|elementorProFrontend|pro-elements-handlers|https?:\/\//i', $js ) && ! preg_match( $vetoed, $js ), "$file: ≤ 6 KB (" . strlen( $js ) . ' B), sin jQuery, Swiper, PRO ni direcciones externas.' );
}
$check( ! is_file( $mod . 'assets/js/posts.js' ), 'Entradas no tiene script propio: lo compartido (loop.js) sólo cuando hace falta.' );
foreach ( glob( $mod . '{*.php,widgets/class-posts.php,widgets/class-archive-posts.php,widgets/class-portfolio.php}', GLOB_BRACE ) as $file ) {
	$src = (string) file_get_contents( $file );
	$check( ! preg_match( $vetoed, $src ), basename( $file ) . ': nombra algo que el empaquetado veta.' );
}
foreach ( array( '_posts.php', 'posts.php', 'archive-posts.php', 'portfolio.php' ) as $file ) {
	$check( ! preg_match( $vetoed, (string) file_get_contents( $root . 'modules/digitalisimo-convert/maps/' . $file ) ), "maps/$file: nombra algo que el empaquetado veta." );
}
// Recursos por instancia.
$deps = static function ( $class, array $settings ) {
	$w = pe_widget( $class, $settings );
	return array( $w->get_style_depends(), $w->get_script_depends() );
};
$P = 'Digitalisimo\Elements\Loop\Widgets\Posts';
$check( array( array( 'digitalisimo-posts' ), array() ) === $deps( $P, array() ), 'Entradas por defecto: sólo su hoja, sin script (sin paginación ni mampostería): ' . json_encode( $deps( $P, array() ) ) );
$check( array( array( 'digitalisimo-loop', 'digitalisimo-posts', 'digitalisimo-posts-cards' ), array( 'digitalisimo-loop' ) ) === $deps( $P, array( '_skin' => 'cards', 'pagination_type' => 'load_more_on_click' ) ), 'Tarjetas con «Cargar más»: la hoja compartida, la propia y la de tarjetas; el script compartido.' );
$check( array( array( 'digitalisimo-loop', 'digitalisimo-posts' ), array() ) === $deps( $P, array( 'pagination_type' => 'numbers' ) ), 'Números: la hoja compartida, sin script (enlaces reales).' );
$check( array( 'digitalisimo-loop' ) === $deps( $P, array( 'classic_masonry' => 'yes' ) )[1], 'La mampostería pide el script compartido.' );
$check( array( 'digitalisimo-posts' ) === $deps( 'Digitalisimo\Elements\Loop\Widgets\Archive_Posts', array( 'pagination_type' => '' ) )[0] && array( 'digitalisimo-loop', 'digitalisimo-posts' ) === $deps( 'Digitalisimo\Elements\Loop\Widgets\Archive_Posts', array() )[0], 'Entradas del archivo: la hoja de Entradas y, con su paginación por defecto (números), la compartida.' );
$check( array( array( 'digitalisimo-portfolio' ), array() ) === $deps( 'Digitalisimo\Elements\Loop\Widgets\Portfolio', array() ) && array( 'digitalisimo-loop', 'digitalisimo-portfolio' ) === $deps( 'Digitalisimo\Elements\Loop\Widgets\Portfolio', array( 'masonry' => 'yes', 'show_filter_bar' => 'yes' ) )[1], 'Portafolio: script sólo con filtro (y el compartido con mampostería).' );
$check( isset( Module::SHARED['digitalisimo-loop'], Module::SHARED['digitalisimo-posts-cards'] ) && 'modules/digitalisimo-loop/assets/css/posts-cards.css' === Module::SHARED['digitalisimo-posts-cards']['css'], 'Module::SHARED registra la hoja y el script compartidos y la de tarjetas.' );
$check( in_array( array( '\\Digitalisimo\\Elements\\Loop\\Posts_Base', 'allow_pagination' ), array_map( static function ( $cb ) { return is_array( $cb ) ? array( '\\' . ltrim( $cb[0], '\\' ), $cb[1] ) : null; }, $GLOBALS['pe']['filters']['pre_handle_404'] ?? array() ), true ), 'Module::init() engancha pre_handle_404 para las páginas /2/ de los listados propios.' );

// 2. Los mismos controles que PRO (ejecutando su código).
$translated = array( 'pagination_prev_label', 'pagination_next_label', 'text', 'load_more_no_posts_custom_message', 'nothing_found_message' );
$prefixes   = array( 'elementor-posts--thumbnail-' => 'digi-posts--thumbnail-', 'elementor-posts--thumbnail-size-' => 'digi-posts--thumbnail-size-', 'elementor-posts--align-' => 'digi-posts--align-', 'elementor-card-shadow-' => 'digi-posts--shadow-', 'elementor-posts__hover-' => 'digi-posts--hover-', 'elementor-posts--' => 'digi-posts--', 'elementor-posts--show-' => 'digi-posts--show-', 'load-more-align-' => 'digi-loop--more-align-', 'elementor-grid%s-' => 'elementor-grid%s-', 'elementor-portfolio--thumbnail-size-' => 'digi-portfolio--thumbnail-size-' );
$built      = array();
foreach ( pe_pairs() as $pro => $pair ) {
	$a = pe_widget( ltrim( $pair[0], '\\' ) );
	$b = pe_widget( ltrim( $pair[1], '\\' ) );
	$built[ $pro ] = $b;
	$own_extra = array_values( array_diff( array_keys( $b->controls ), array_keys( $a->controls ) ) );
	$check( array() === array_diff( array_keys( $a->controls ), array_keys( $b->controls ) ), "$pro: faltan controles de PRO: " . implode( ', ', array_diff( array_keys( $a->controls ), array_keys( $b->controls ) ) ) );
	$check( array() === array_diff( $own_extra, array( 'digitalisimo_post_terms', 'digitalisimo_tags_label' ) ) && ( 'posts' !== $pro || 2 === count( $own_extra ) ), "$pro: sólo sobran los de categorías y etiquetas de Elements: " . implode( ', ', $own_extra ) );
	$check( array_keys( $a->groups ) === array_keys( $b->groups ), "$pro: los mismos grupos, en el mismo orden." );
	$check( array_keys( $a->sections ) === array_values( array_diff( array_keys( $b->sections ), array( "digitalisimo_post_terms_section" ) ) ), "$pro: las mismas secciones, en el mismo orden: " . implode( ', ', array_diff( array_keys( $a->sections ), array_keys( $b->sections ) ) ) );
	$order_a = array_values( array_intersect( $a->order, array_keys( $a->controls ) ) );
	$order_b = array_values( array_intersect( $b->order, array_keys( $a->controls ) ) );
	$check( array_values( array_unique( $order_a ) ) === array_values( array_unique( $order_b ) ), "$pro: los controles en el mismo orden que PRO." );
	foreach ( $a->sections as $id => $section ) {
		$check( ( $section['tab'] ?? 'content' ) === ( $b->sections[ $id ]['tab'] ?? 'content' ) && ( $section['condition'] ?? null ) === ( $b->sections[ $id ]['condition'] ?? null ), "$pro.$id: la sección en la misma pestaña y con la misma condición." );
	}
	foreach ( $a->controls as $name => $ca ) {
		$cb    = $b->controls[ $name ];
		$label = "$pro.$name";
		$check( ( $ca['type'] ?? null ) === ( $cb['type'] ?? null ), "$label: tipo." );
		$check( $ca['section'] === $cb['section'], "$label: en la sección {$ca['section']} (propio: {$cb['section']})." );
		$is_text = in_array( $name, $translated, true ) || preg_match( '/_read_more_text$/', $name );
		if ( ! $is_text ) {
			$check( ( $ca['default'] ?? null ) === ( $cb['default'] ?? null ), "$label: valor por defecto " . json_encode( $cb['default'] ?? null ) . ' (PRO: ' . json_encode( $ca['default'] ?? null ) . ').' );
		} else {
			$check( is_string( $cb['default'] ?? null ) && '' !== $cb['default'], "$label: texto por defecto propio, en español." );
		}
		foreach ( array( 'tablet_default', 'mobile_default', 'responsive', 'frontend_available', 'render_type', 'return_value', 'multiple', 'label_block', 'style_transfer', 'fa4compatibility', 'exclude_inline_options', 'skin', 'separator', 'size_units', 'range', 'label_on', 'classes', 'content_classes', 'alpha', 'dynamic', 'ai' ) as $key ) {
			if ( 'label_on' === $key ) {
				$check( isset( $ca[ $key ] ) === isset( $cb[ $key ] ), "$label: $key." );
				continue;
			}
			$check( ( $ca[ $key ] ?? null ) === ( $cb[ $key ] ?? null ), "$label: $key " . json_encode( $cb[ $key ] ?? null ) . ' (PRO: ' . json_encode( $ca[ $key ] ?? null ) . ').' );
		}
		$check( ( $ca['condition'] ?? null ) === ( $cb['condition'] ?? null ), "$label: misma condición que PRO " . json_encode( $ca['condition'] ?? null ) . ' (propio: ' . json_encode( $cb['condition'] ?? null ) . ').' );
		$check( ( $ca['global'] ?? null ) === ( $cb['global'] ?? null ), "$label: el mismo global por defecto." );
		$check( array_keys( (array) ( $ca['options'] ?? array() ) ) === array_keys( (array) ( $cb['options'] ?? array() ) ), "$label: mismas opciones que PRO." );
		$check( array_keys( (array) ( $ca['selectors_dictionary'] ?? array() ) ) === array_keys( (array) ( $cb['selectors_dictionary'] ?? array() ) ), "$label: el mismo diccionario de valores." );
		$check( empty( $ca['selectors'] ) === empty( $cb['selectors'] ), "$label: con selectores como en PRO." );
		if ( isset( $ca['prefix_class'] ) ) {
			$check( ( $prefixes[ $ca['prefix_class'] ] ?? '?' ) === ( $cb['prefix_class'] ?? null ), "$label: la clase del envoltorio de PRO ({$ca['prefix_class']}) pasa a la propia ({$cb['prefix_class']})." );
		} else {
			$check( ! isset( $cb['prefix_class'] ), "$label: sin clase del envoltorio, como PRO." );
		}
		foreach ( (array) ( $cb['selectors'] ?? array() ) as $selector => $rule ) {
			$check( ! preg_match( '/elementor-post|elementor-portfolio|e-load-more|elementor-pagination|--e-global/', $selector . $rule ), "$label: selectores propios ($selector)." );
		}
	}
	foreach ( $a->groups as $name => $ga ) {
		$gb = $b->groups[ $name ];
		foreach ( array( 'type', 'condition', 'global', 'exclude', 'default', 'types', 'presets', 'section' ) as $key ) {
			$check( ( $ga[ $key ] ?? null ) === ( $gb[ $key ] ?? null ), "$pro.$name (grupo): $key " . json_encode( $gb[ $key ] ?? null ) . ' (PRO: ' . json_encode( $ga[ $key ] ?? null ) . ').' );
		}
		$check( array_keys( (array) ( $ga['fields_options'] ?? array() ) ) === array_keys( (array) ( $gb['fields_options'] ?? array() ) ) && json_encode( $ga['fields_options'] ?? null ) === json_encode( $gb['fields_options'] ?? null ), "$pro.$name (grupo): los mismos fields_options." );
		$check( isset( $ga['prefix_class'] ) === isset( $gb['prefix_class'] ), "$pro.$name (grupo): clase del envoltorio como PRO." );
		if ( isset( $gb['selector'] ) ) {
			$check( ! preg_match( '/elementor-post|elementor-portfolio|e-load-more|elementor-pagination/', $gb['selector'] ), "$pro.$name (grupo): selector propio." );
		}
	}
	// La pestaña inyectada del archivo (Query_Note_Trait) también existe en Entradas propias.
}
\Elementor\Plugin::$instance            = new \Elementor\Plugin();
\Elementor\Plugin::$instance->documents = new class() { public function get_current() { return null; } public function get( $id, $f = true ) { return false; } };
$GLOBALS['pe']['meta'][77]['_elementor_template_type'] = 'archive';
$GLOBALS['pe']['post'] = (object) array( 'ID' => 77 );
$note_a = pe_widget( 'ElementorPro\Modules\Posts\Widgets\Posts' );
$note_b = pe_widget( 'Digitalisimo\Elements\Loop\Widgets\Posts' );
$check( isset( $note_a->controls['archive_query_note'], $note_b->controls['archive_query_note'] ) && $note_a->controls['archive_query_note']['condition'] === $note_b->controls['archive_query_note']['condition'] && array( 'of' => 'posts_query_id', 'at' => 'before' ) === $note_b->injected[0], 'En una plantilla de archivo, la nota de la consulta actual, inyectada antes del ID de la consulta como en PRO.' );
unset( $GLOBALS['pe']['post'], $GLOBALS['pe']['meta'] );

// 3. Consulta.
$GLOBALS['pe']['query'] = pe_query( 3 );
$w = pe_widget( $P, array( '_skin' => 'cards', 'cards_posts_per_page' => 9, 'pagination_type' => 'numbers' ) );
$GLOBALS['pe']['qv'] = array( 'page' => 2 );
\ElementorPro\Modules\QueryControl\Module::$queries = array();
$w->query_posts();
$q = \ElementorPro\Modules\QueryControl\Module::$queries[0];
$check( 'digitalisimo-posts' === $q[0] && 'posts' === $q[1] && 9 === $q[2]['posts_per_page'] && 2 === $q[2]['paged'] && false === $q[2]['has_custom_pagination'], 'La consulta del constructor con el grupo `posts_`, las entradas por página de la piel y la página actual: ' . json_encode( $q ) );
$GLOBALS['pe']['qv'] = array();

// 4. Marcado.
$GLOBALS['pe']['query'] = pe_query( 3 );
$html = $render( pe_widget( $P, array( 'classic_meta_data' => array( 'author', 'date', 'comments' ) ) ) );
$check( 1 === substr_count( $html, '<div class="digi-posts digi-posts--skin-classic elementor-grid" role="list" data-digi-items="">' ), 'Clásica: el contenedor propio con la rejilla del núcleo, lista y data-digi-items: ' . substr( $html, 0, 160 ) );
$check( 3 === substr_count( $html, 'role="listitem"' ) && 3 === substr_count( $html, 'class="digi-posts__item elementor-grid-item post-' ), 'Una entrada por elemento de lista, con post_class().' );
$check( 2 === substr_count( $html, '<a class="digi-posts__thumb-link" href="https://sitio.test/entrada-' ) && str_contains( $html, 'tabindex="-1"' ) && str_contains( $html, 'class="attachment-medium size-medium wp-image-1102"' ) && str_contains( $html, 'srcset=' ) && str_contains( $html, 'loading="lazy"' ), 'Imagen con wp_get_attachment_image() (tamaño de la piel, srcset, loading); sin imagen destacada no hay enlace de imagen.' );
$check( str_contains( $html, '<h3 class="digi-posts__title"><a href="https://sitio.test/entrada-101/" >Entrada 1</a></h3>' ), 'Título h3 enlazado.' );
$check( str_contains( $html, "<div class=\"digi-posts__meta\"><span class=\"digi-posts__author\">\nAutora\n</span>\n<span class=\"digi-posts__date\">\n1 de enero de 2026\n</span>\n<span class=\"digi-posts__comments\">\nSin comentarios\n</span>\n</div>" ), 'Metadatos con espacios alrededor de cada dato (el separador queda entre espacios, como en PRO).' );
$check( str_contains( $html, '<div class="digi-posts__excerpt"><p>Texto generado de la entrada 102</p></div>' ), 'Extracto sin «[…]» (excerpt_more vacío, como PRO).' );
$check( empty( $GLOBALS['pe']['filters']['excerpt_more'] ) && empty( $GLOBALS['pe']['filters']['excerpt_length'] ), 'Los filtros del extracto se quitan al terminar.' );
$check( str_contains( $html, '<a class="digi-posts__more" href="https://sitio.test/entrada-101/" tabindex="-1" >Leer más »<span class="elementor-screen-only">: Entrada 1</span></a>' ), '«Leer más» con su texto visible y el título sólo para lectores de pantalla (el texto visible está en el nombre accesible).' );
$check( ! str_contains( $html, 'digi-posts__more-wrap' ) && ! str_contains( $html, 'digi-loop__' ), 'Sin alineación automática no hay envoltorio de «Leer más»; sin paginación, sin pie.' );
$html = $render( pe_widget( $P, array( 'classic_read_more_alignment' => 'yes', 'classic_open_new_tab' => 'yes', 'digitalisimo_post_terms' => array( 'category', 'post_tag' ), 'digitalisimo_tags_label' => 'Etiquetas:' ) ) );
$check( 3 === substr_count( $html, '<div class="digi-posts__more-wrap">' ) && str_contains( $html, 'target="_blank" rel="noopener"' ), 'Alineación automática: «Leer más» en su envoltorio; otra ventana con rel="noopener".' );
$check( str_contains( $html, '<div class="digi-posts__meta digi-posts__taxonomies"><span class="digi-posts__terms digi-posts__terms--category"><a href="https://sitio.test/t/5/">Noticias</a>, <a href="https://sitio.test/t/6/">Guías</a></span><span class="digi-posts__terms digi-posts__terms--post_tag">Etiquetas: <a href="https://sitio.test/t/9/">Clave</a></span></div>' ), 'Categorías y etiquetas tras los metadatos (lo que Migración trae de las rejillas de entradas).' );
$html = $render( pe_widget( $P, array( '_skin' => 'cards' ) ) );
$check( str_contains( $html, 'digi-posts--skin-cards' ) && 3 === substr_count( $html, '<div class="digi-posts__card">' ) && 2 === substr_count( $html, '<div class="digi-posts__badge">Noticias</div>' ) && 2 === substr_count( $html, '<div class="digi-posts__avatar"><img alt="Autora Pruebas"' ), 'Tarjetas: tarjeta, insignia con el primer término y avatar sólo tras una imagen.' );
$check( strpos( $html, 'digi-posts__text' ) < strpos( $html, 'digi-posts__meta' ) && preg_match( '/<span class="digi-posts__date">.*?<\/span>\s*<span class="digi-posts__comments">/s', $html ), 'Tarjetas: los metadatos al pie de la tarjeta.' );
$html = $render( pe_widget( $P, array( '_skin' => 'cards', 'cards_thumbnail' => 'none', 'cards_show_badge' => '' ) ) );
$check( ! str_contains( $html, 'digi-posts__thumb' ) && ! str_contains( $html, 'digi-posts__badge' ), 'Tarjetas sin imagen: ni imagen, ni insignia, ni avatar.' );
$GLOBALS['pe']['query'] = pe_query( 4, 3 );
$GLOBALS['pe']['qv']    = array( 'page' => 2 );
$html = $render( pe_widget( $P, array( 'pagination_type' => 'numbers_and_prev_next', 'pagination_page_limit' => '' ) ) );
$check( str_contains( $html, '<nav class="digi-loop__pagination" aria-label="Paginación">' ) && str_contains( $html, '<a class="page-numbers prev" href="https://sitio.test/entrada-0/">&laquo; Anterior</a>' ) && str_contains( $html, '<span aria-current="page" class="page-numbers current"><span class="elementor-screen-only">Página</span>2</span>' ) && str_contains( $html, '<a class="page-numbers next" href="https://sitio.test/entrada-0/3/">Siguiente &raquo;</a>' ), 'Números y anterior/siguiente con enlaces reales del singular (/N/): ' . substr( $html, strpos( $html, '<nav' ) ) );
$html = $render( pe_widget( $P, array( 'pagination_type' => 'prev_next', 'pagination_page_limit' => '2' ) ) );
$check( str_contains( $html, '<span class="page-numbers next">Siguiente &raquo;</span>' ), 'Con el límite de páginas, «Siguiente» desactivado en la última (como PRO).' );
$GLOBALS['pe']['qv'] = array();
$html = $render( pe_widget( $P, array( 'pagination_type' => 'load_more_on_click', 'load_more_no_posts_message_switcher' => 'yes', 'load_more_no_posts_custom_message' => 'No hay más' ) ) );
$check( str_contains( $html, '<span class="digi-loop__spinner"><i aria-hidden="true" class="fas fa-spinner"></i></span><div class="digi-loop__anchor" data-page="1" data-max-page="3" data-next-page="https://sitio.test/entrada-0/2/" data-digi-more="click"></div>' ), '«Cargar más»: cargador y ancla con la página siguiente (la misma URL que PRO).' );
$check( str_contains( $html, '<div class="digi-loop__more elementor-button-wrapper"><a class="elementor-button elementor-size-sm"><span class="elementor-button-content-wrapper" role="button" tabindex="0"><span class="elementor-button-text">Cargar más</span></span></a></div><div class="digi-loop__message">No hay más</div><span class="digi-loop__status elementor-screen-only" aria-live="polite"' ), '«Cargar más» es el enlace de PRO (sin href ni tabindex: los estilos del tema y del kit le llegan igual) con su contenido como botón para el teclado; el mensaje del final y la región viva.' );
$check( ! preg_match( '/<button|<a [^>]*class="elementor-button[^"]*"[^>]*(href|tabindex)=/', $html ), '«Cargar más»: ni <button> ni href o tabindex en el enlace.' );
$html = $render( pe_widget( $P, array( 'pagination_type' => 'load_more_on_click', 'button_css_id' => 'mas', 'hover_animation' => 'grow', 'size' => 'md' ) ) );
$check( str_contains( $html, '<a class="elementor-button elementor-size-md elementor-animation-grow" id="mas"><span class="elementor-button-content-wrapper" role="button" tabindex="0">' ), '«Cargar más»: el tamaño, la animación y el ID en el enlace, como PRO.' );
$html = $render( pe_widget( $P, array( 'pagination_type' => 'load_more_infinite_scroll', 'load_more_spinner' => array( 'value' => '', 'library' => '' ), 'load_more_no_posts_custom_message' => null ) /* Con el interruptor apagado, get_settings_for_display() lo deja en null. */ ) );
$check( str_contains( $html, 'data-digi-more="infinite"' ) && ! str_contains( $html, 'digi-loop__more' ) && ! str_contains( $html, 'digi-loop__spinner' ) && str_contains( $html, '<div class="digi-loop__message"></div>' ), 'Infinita: ancla sin botón; sin cargador si no se elige; sin mensaje personalizado, vacío (como PRO).' );
$GLOBALS['pe']['query'] = pe_query( 2, 1 );
$html = $render( pe_widget( $P, array( 'pagination_type' => 'load_more_on_click' ) ) );
$check( ! str_contains( $html, 'digi-loop__anchor' ) && ! str_contains( $html, 'digi-loop__more' ), 'Con una sola página no hay ancla ni botón.' );
$GLOBALS['pe']['query'] = pe_query( 2 );
$html = $render( pe_widget( $P, array( 'classic_masonry' => 'yes' ) ) );
$check( str_contains( $html, 'data-digi-items="" data-digi-masonry=""' ), 'La mampostería marca el contenedor para loop.js.' );
$GLOBALS['pe']['query'] = pe_query( 0 );
$check( '' === $render( pe_widget( $P, array() ) ), 'Entradas sin entradas no pinta nada (como PRO).' );
// Contenido completo: la piel se registra y pinta título y metadatos (el contenido lo da el constructor).
$fc = pe_widget( $P, array( '_skin' => 'full_content' ) );
$check( 'Contenido completo' === $fc->get_skin( 'full_content' )->get_title() && isset( $fc->controls['full_content_row_gap']['selectors']['{{WRAPPER}} .digi-posts > .digi-posts__item'] ), 'Contenido completo: su piel y la separación entre filas sobre los elementos propios.' );
$masonry_gap = static function ( $widget, $name ) {
	return (string) ( $widget->controls[ $name ]['selectors']['{{WRAPPER}}'] ?? '' );
};
foreach ( array( 'classic_row_gap', 'cards_row_gap' ) as $name ) {
	$check( '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px' === $masonry_gap( $fc, $name ), "Entradas.$name: la separación de la mampostería en px (como el número del ajuste en PRO) en la misma regla." );
}
$aw_gap = pe_widget( 'Digitalisimo\Elements\Loop\Widgets\Archive_Posts' );
foreach ( array( 'archive_classic_row_gap', 'archive_cards_row_gap' ) as $name ) {
	$check( '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px' === $masonry_gap( $aw_gap, $name ), "Entradas del archivo.$name: la separación de la mampostería en px." );
}
$pf_gap = pe_widget( 'Digitalisimo\Elements\Loop\Widgets\Portfolio' );
$check( '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px' === $masonry_gap( $pf_gap, 'row_gap' ) && '--grid-row-gap: {{SIZE}}{{UNIT}}; --grid-column-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px;' === $masonry_gap( $pf_gap, 'item_gap' ) && array_search( 'item_gap', array_keys( $pf_gap->controls ), true ) < array_search( 'row_gap', array_keys( $pf_gap->controls ), true ), 'Portafolio: row_gap e item_gap llevan la separación de la mampostería; row_gap va después y gana (PRO: item_gap sólo sin row_gap).' );
$check( ! str_contains( $masonry_gap( $fc, 'full_content_row_gap' ), 'digi-masonry-gap' ) && '' === $masonry_gap( $fc, 'full_content_row_gap' ), 'Contenido completo no tiene mampostería: sin la variable.' );
// Contenido completo de una entrada sin el constructor: `the_content` y sus páginas, con el título de PRO («Pages:», su
// msgid y su dominio) en Entradas y en Entradas del archivo; con el paquete de idioma de PRO, la misma traducción.
$pe_core = new class() {
	public function is_edit_mode() { return false; }
	public function set_edit_mode( $v ) {}
	public function is_preview_mode( $id = 0 ) { return false; }
	public function get_builder_content( $id, $css = false ) { return ''; }
	public function remove_content_filter() {}
	public function add_content_filter() {}
	public function get_current() { return null; }
};
$GLOBALS['pe']['elementor'] = (object) array( 'editor' => $pe_core, 'preview' => $pe_core, 'frontend' => $pe_core, 'documents' => $pe_core );
foreach ( array( 'editor', 'preview', 'frontend' ) as $prop ) {
	\Elementor\Plugin::$instance->$prop = $pe_core;
}
$pe_pages = static function ( $class, $skin, $id, array $settings = array() ) {
	$GLOBALS['pe']['post'] = (object) array( 'ID' => $id, 'post_content' => '<p>Primera página</p>', 'post_status' => 'publish', 'post_title' => "Entrada $id" );
	$w = pe_widget( $class, array( '_skin' => $skin ) + $settings );
	$s = $w->get_skin( $skin );
	$s->set_parent( $w );
	ob_start();
	$s->render_post_content();
	return (string) ob_get_clean();
};
$pe_id = 400;
// «Pages:» no es un ajuste: lo convertido de PRO (la marca del traductor, `Translator::MARKER`, que dejan «Migrar» y los
// kits) usa el texto de PRO; lo nuevo, «Páginas:», como desde C5 (y nunca la traducción de PRO).
foreach ( array( array(), array( 'elementor-pro' => array( 'Pages:' => 'Seiten:' ) ) ) as $l10n ) {
	$GLOBALS['pe']['l10n'] = $l10n;
	$title = $l10n ? 'Seiten:' : 'Pages:';
	foreach ( array( array( 'ElementorPro\Modules\Posts\Widgets\Posts', $P, 'full_content', 'posts' ), array( 'ElementorPro\Modules\ThemeBuilder\Widgets\Archive_Posts', 'Digitalisimo\Elements\Loop\Widgets\Archive_Posts', 'archive_full_content', 'archive-posts' ) ) as list( $pro_class, $own_class, $skin, $source ) ) {
		$a = $pe_pages( $pro_class, $skin, ++$pe_id );
		$b = $pe_pages( $own_class, $skin, ++$pe_id, array( \Digitalisimo\Elements\Convert\Translator::MARKER => $source ) );
		$c = $pe_pages( $own_class, $skin, ++$pe_id );
		$check( '<p>Primera página</p><div class="page-links elementor-page-links"><span class="page-links-title elementor-page-links-title">' . $title . '</span><a href="https://sitio.test/x/2/"><span>2</span></a></div>' === $a && $a === $b, "$skin convertido: las páginas de la entrada con el título de PRO ($title), igual que PRO.\n PRO:    $a\n propio: $b" );
		$check( str_replace( $title, 'Páginas:', $a ) === $c, "$skin nuevo (sin la marca de la conversión): «Páginas:», sin la traducción de PRO.\n propio: $c" );
	}
}
unset( $GLOBALS['pe']['l10n'], $GLOBALS['pe']['elementor'] );
// Entradas del archivo.
$A = 'Digitalisimo\Elements\Loop\Widgets\Archive_Posts';
$GLOBALS['wp_query']    = pe_query( 0 );
$GLOBALS['pe']['query'] = null;
$html = $render( pe_widget( $A, array( 'nothing_found_message' => 'Nada <b>aquí</b>' ) ) );
$check( str_contains( $html, '<div class="digi-posts digi-posts--skin-classic elementor-grid" role="list" data-digi-items=""><div class="digi-posts__nothing">Nada &lt;b&gt;aquí&lt;/b&gt;</div></div>' ), 'Archivo sin entradas: el mensaje, escapado, dentro del contenedor (como PRO).' );
$GLOBALS['wp_query']                 = pe_query( 2 );
$GLOBALS['wp_query']->query_vars     = array( 'post_type' => 'post' );
\ElementorPro\Modules\QueryControl\Module::$displayed_ids = array();
$html = $render( pe_widget( $A, array( '_skin' => 'archive_cards', 'pagination_type' => '' ) ) );
$check( str_contains( $html, 'digi-posts--skin-cards' ) && 2 === substr_count( $html, '<div class="digi-posts__card">' ) && array( 101, 102 ) === \ElementorPro\Modules\QueryControl\Module::$displayed_ids, 'Archivo: la consulta global, con la clase de las tarjetas y lo listado en «Evitar duplicados».' );
add_filter( 'elementor/theme/posts_archive/query_posts/query_vars', static function ( $vars ) { return $vars + array( 'posts_per_page' => 1 ); } );
$aw = pe_widget( $A );
$aw->query_posts();
$check( $aw->get_query() !== $GLOBALS['wp_query'] && 1 === $aw->get_query()->query_vars['posts_per_page'], 'El filtro de PRO de las variables del archivo crea otra consulta, como PRO.' );
// Portafolio.
$F = 'Digitalisimo\Elements\Loop\Widgets\Portfolio';
$GLOBALS['pe']['query'] = pe_query( 3 );
$html = $render( pe_widget( $F, array( 'show_filter_bar' => 'yes', 'taxonomy' => 'category' ) ) );
$check( str_contains( $html, '<ul class="digi-portfolio__filters" aria-label="Filtrar"><li><button type="button" class="digi-portfolio__filter" data-filter="__all" aria-pressed="true">Todos</button></li><li><button type="button" class="digi-portfolio__filter" data-filter="6" aria-pressed="false">Guías</button></li><li><button type="button" class="digi-portfolio__filter" data-filter="5" aria-pressed="false">Noticias</button></li></ul>' ), 'Portafolio nuevo: filtro con botones y aria-pressed, «Todos» (como desde C5) y los términos por nombre.' );
// Lo convertido de PRO (la marca del traductor, `Translator::MARKER`, que dejan «Migrar» y los kits) dice lo mismo que PRO:
// su «All» con su msgid y su dominio, también con el paquete de idioma de PRO; lo nuevo, «Todos», sin la traducción de PRO.
$pf_label = static function ( $translations, array $marker = array( \Digitalisimo\Elements\Convert\Translator::MARKER => 'portfolio' ) ) use ( $render, $F ) {
	$GLOBALS['pe']['l10n'] = $translations;
	$GLOBALS['pe']['query'] = pe_query( 3 );
	$own = $render( pe_widget( $F, array( 'show_filter_bar' => 'yes', 'taxonomy' => 'category' ) + $marker ) );
	$pro = pe_widget( 'ElementorPro\Modules\Posts\Widgets\Portfolio', array( 'show_filter_bar' => 'yes', 'taxonomy' => 'category' ) );
	$q   = new ReflectionProperty( $pro, '_query' );
	$q->setValue( $pro, (object) array( 'posts' => array( (object) array( 'tags' => array( 6 => (object) array( 'term_id' => 6, 'name' => 'Guías' ) ) ) ) ) );
	ob_start();
	( new ReflectionMethod( $pro, 'render_filter_menu' ) )->invoke( $pro );
	$pro_html = (string) ob_get_clean();
	unset( $GLOBALS['pe']['l10n'] );
	preg_match( '~data-filter="__all">([^<]*)</li>~', $pro_html, $a );
	preg_match( '~data-filter="__all" aria-pressed="true">([^<]*)</button>~', $own, $b );
	return array( $a[1] ?? null, $b[1] ?? null );
};
list( $pro_all, $own_all ) = $pf_label( array() );
$check( 'All' === $pro_all && $pro_all === $own_all, "Portafolio: el primer botón del filtro dice lo mismo que el de PRO (PRO «{$pro_all}», propio «{$own_all}»)." );
list( $pro_all, $own_all ) = $pf_label( array( 'elementor-pro' => array( 'All' => 'Alle' ) ) );
$check( 'Alle' === $pro_all && $pro_all === $own_all, "Portafolio: con el paquete de idioma de PRO, la misma traducción (PRO «{$pro_all}», propio «{$own_all}»)." );
list( $pro_all, $own_all ) = $pf_label( array( 'elementor-pro' => array( 'All' => 'Alle' ) ), array() );
$check( 'Alle' === $pro_all && 'Todos' === $own_all, "Portafolio nuevo (sin la marca de la conversión): «Todos», sin la traducción de PRO (propio «{$own_all}»)." );
$GLOBALS['pe']['query'] = pe_query( 3 );
$check( str_contains( $html, '<div class="digi-portfolio elementor-grid" role="list" data-digi-items="">' ) && str_contains( $html, 'role="listitem" data-digi-terms="6 5"><a class="digi-portfolio__link" href="https://sitio.test/entrada-101/"><div class="digi-portfolio__img"></div>' ) && str_contains( $html, 'role="listitem" data-digi-terms="5"><a class="digi-portfolio__link" href="https://sitio.test/entrada-102/"><div class="digi-portfolio__img"><img' ) && str_contains( $html, '<div class="digi-portfolio__overlay"><h3 class="digi-portfolio__title">Entrada 1</h3></div></a></article>' ), 'Portafolio: elementos con sus términos, enlace, imagen y capa con el título.' );
$html = $render( pe_widget( $F, array() ) );
$check( ! str_contains( $html, 'digi-portfolio__filters' ) && str_contains( $html, 'data-digi-terms=""' ), 'Sin la barra de filtro, sin botones (y sin términos si no hay taxonomía).' );

// 5. Conversión de ajustes reales.
$tree = static function ( $type, array $settings ) {
	return array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() ) ) ) );
};
$cases = array(
	'posts'         => array(
		array(),
		array( '_skin' => 'cards', 'cards_columns' => '2', 'cards_columns_mobile' => '1', 'cards_thumbnail' => 'none', 'cards_meta_data' => array( 'author' ), 'cards_badge_taxonomy' => 'post_tag', 'cards_card_bg_color' => '#f5f5f5', 'cards_title_typography_typography' => 'custom', 'cards_title_typography_font_size' => array( 'unit' => 'px', 'size' => 24 ), 'cards_thumbnail_size_size' => 'large', 'posts_post_type' => 'page', 'posts_orderby' => 'title', 'posts_query_id' => 'mi_consulta', 'posts_avoid_duplicates' => 'yes', '__globals__' => array( 'cards_title_color' => 'globals/colors?id=primary', 'cards_badge_typography_typography' => 'globals/typography?id=accent' ), '__dynamic__' => array( 'cards_read_more_text' => '[elementor-tag id="a" name="post-title" settings="%7B%7D"]' ) ),
		array( '_skin' => 'full_content', 'full_content_thumbnail' => 'thumbnail', 'full_content_row_gap' => array( 'unit' => 'px', 'size' => 20 ), 'full_content_box_shadow_box_shadow_type' => 'yes', 'full_content_box_shadow_box_shadow' => array( 'horizontal' => 0 ), 'pagination_type' => 'load_more_on_click', 'text' => 'Más', 'selected_icon' => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ), 'background_background' => 'classic', 'background_color' => '#000', 'button_background_hover_color' => '#111', 'border_border' => 'solid', 'typography_font_weight' => '700', 'align_tablet' => 'justify', 'load_more_spacing' => array( 'size' => 10, 'unit' => 'px' ), 'load_more_no_posts_message_typography' => 'custom' ),
		array( 'classic_masonry' => 'yes', 'classic_item_ratio_mobile' => array( 'size' => 1 ), 'classic_thumbnail_filters_css_filter' => 'custom', 'classic_box_shadow_hover_box_shadow_type' => 'yes', 'pagination_type' => 'numbers', 'pagination_typography_typography' => 'custom', 'pagination_spacing' => array( 'size' => 4 ), 'digitalisimo_post_terms' => array( 'category' ), 'custom_css' => 'selector .elementor-post__title a{color:red} selector .elementor-posts-container .elementor-post__thumbnail__link{margin:0} selector .elementor-pagination .page-numbers{gap:1px}', 'size' => 'lg' ),
	),
	'archive-posts' => array(
		array(),
		array( '_skin' => 'archive_cards', 'archive_cards_show_avatar' => '', 'archive_cards_hover_effect' => 'none', 'nothing_found_message' => 'Nada', 'nothing_found_typography_typography' => 'custom', 'pagination_type' => 'prev_next', '__globals__' => array( 'nothing_found_color' => 'globals/colors?id=secondary' ), 'custom_css' => 'selector .elementor-posts-nothing-found{padding:1em}' ),
		array( '_skin' => 'archive_full_content', 'archive_full_content_box_padding' => array( 'top' => '1' ), 'archive_classic_columns' => '2' ),
	),
	'portfolio'     => array(
		array(),
		array( 'columns' => '4', 'masonry' => 'yes', 'show_filter_bar' => 'yes', 'taxonomy' => 'category', 'item_gap' => array( 'size' => 5 ), 'thumbnail_size_size' => 'large', 'typography_filter_typography' => 'custom', 'posts_post_type' => 'post', 'posts_include' => array( 'terms' ), 'posts_include_term_ids' => array( '5' ), '__globals__' => array( 'color_background' => 'globals/colors?id=secondary' ), 'custom_css' => 'selector .elementor-portfolio__filter.elementor-active{color:red}' ),
	),
);
foreach ( $cases as $type => $list ) {
	$own = $built[ $type ];
	$known = array_keys( $own->controls );
	foreach ( array_keys( $own->groups ) as $group ) {
		$known[] = $group . '_*';
	}
	foreach ( $list as $n => $settings ) {
		$log = array();
		$out = Converter::tree( $tree( $type, $settings ), $log, true );
		$el  = $out[0]['elements'][0];
		$check( $names[ $type ][0] === $el['widgetType'] && 'w1' === $el['id'], "$type #$n: pasa al widget propio con el mismo id." );
		$codes = array_merge( ...array_map( static function ( $l ) { return $l['codes'] ?? array(); }, $log ) );
		$check( array() === array_filter( $codes, static function ( $c ) { return 'pro.unmapped' === $c['code']; } ), "$type #$n: ninguna clave sin declarar: " . json_encode( $codes ) );
		foreach ( array_merge( array_keys( $el['settings'] ), array_keys( (array) ( $el['settings']['__globals__'] ?? array() ) ), array_keys( (array) ( $el['settings']['__dynamic__'] ?? array() ) ) ) as $key ) {
			if ( in_array( $key, array( '__globals__', '__dynamic__', '_digitalisimo_pro', 'custom_css', 'icon' ), true ) || 0 === strpos( $key, '_' ) || 0 === strpos( $key, 'digitalisimo_' ) ) {
				continue;
			}
			$base = preg_replace( '/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', '', $key );
			$ok   = in_array( $base, $known, true );
			foreach ( array_keys( $own->groups ) as $group ) {
				$ok = $ok || 0 === strpos( $key, $group . '_' ) || $key === $group;
			}
			$check( $ok, "$type #$n: «{$key}» cae en un control propio." );
		}
	}
}
$log = array();
$out = Converter::tree( $tree( 'posts', array() ), $log, true )[0]['elements'][0]['settings'];
$check( '&laquo; Previous' === $out['pagination_prev_label'] && 'Load More' === $out['text'] && 'Read More »' === $out['classic_read_more_text'] && 'Read More »' === $out['cards_read_more_text'] && 'No more posts to show' === $out['load_more_no_posts_custom_message'], 'Los textos por defecto de PRO viajan explícitos al widget propio (sus valores por defecto están en español).' );
// «Separación» de «Cargar más»: PRO no la aplicaba (su hoja lee otra variable; ver tests/elements-pro-loop.php) y dejaba
// siempre 30 px. Lo convertido la descarta como pérdida declarada (también por dispositivo), así que el propio deja los
// mismos 30 px; lo nuevo conserva el control.
$log = array();
$out = Converter::tree( $tree( 'posts', $cases['posts'][2] + array( 'load_more_spacing_mobile' => array( 'size' => 4, 'unit' => 'px' ) ) ), $log, true )[0]['elements'][0]['settings'];
$dropped = array_column( array_filter( array_merge( ...array_map( static function ( $l ) { return $l['codes'] ?? array(); }, $log ) ), static function ( $c ) { return 'pro.dropped' === $c['code']; } ), 'key' );
$check( ! preg_grep( '/^load_more_spacing/', array_keys( $out ) ) && in_array( 'load_more_spacing', $dropped, true ) && isset( $built['posts']->controls['load_more_spacing'] ), 'Entradas: la separación de «Cargar más» de PRO se descarta al convertir (PRO dejaba siempre 30 px; el control propio sigue para lo nuevo).' );
$out = Converter::tree( $tree( 'posts', $cases['posts'][3] ), $log, true )[0]['elements'][0]['settings'];
$check( 'selector .digi-posts__title a{color:red} selector .digi-posts .digi-posts__thumb-link{margin:0} selector .digi-loop__pagination .page-numbers{gap:1px}' === $out['custom_css'], 'El CSS personalizado pasa a las clases propias: ' . $out['custom_css'] );
$out = Converter::tree( $tree( 'archive-posts', array() ), $log, true )[0]['elements'][0]['settings'];
$check( 'numbers' === $out['pagination_type'] && 'It seems we can’t find what you’re looking for.' === $out['nothing_found_message'], 'Archivo: la paginación por defecto (números) y el mensaje de PRO viajan explícitos.' );
$out = Converter::tree( $tree( 'portfolio', $cases['portfolio'][1] ), $log, true )[0]['elements'][0]['settings'];
$check( 'selector .digi-portfolio__filter[aria-pressed="true"]{color:red}' === $out['custom_css'] && array( '5' ) === $out['posts_include_term_ids'], 'Portafolio: el filtro activo pasa a aria-pressed; la consulta, tal cual.' );
// PRO marca también con `elementor-active` los elementos que el filtro deja a la vista: pasan a los visibles del propio.
$out = Converter::tree( $tree( 'portfolio', array( 'custom_css' => 'selector .elementor-portfolio-item.elementor-active .elementor-portfolio-item__title{color:red} selector .elementor-post.elementor-active{opacity:.9} selector .elementor-portfolio__filter.elementor-active{color:blue}' ) ), $log, true )[0]['elements'][0]['settings'];
$check( 'selector .digi-portfolio__item:not([hidden]) .digi-portfolio__title{color:red} selector .digi-portfolio__item:not([hidden]){opacity:.9} selector .digi-portfolio__filter[aria-pressed="true"]{color:blue}' === $out['custom_css'], 'Portafolio: los elementos activos de PRO pasan a los visibles; el filtro activo, a aria-pressed: ' . $out['custom_css'] );
// La conversión (la de «Migrar» y la de los kits) deja la marca que hace decir al filtro el «All» de PRO.
$out = Converter::tree( $tree( 'portfolio', array( 'show_filter_bar' => 'yes', 'taxonomy' => 'category' ) ), $log, true )[0]['elements'][0]['settings'];
$GLOBALS['pe']['query'] = pe_query( 3 );
$check( 'portfolio' === ( $out[ \Digitalisimo\Elements\Convert\Translator::MARKER ] ?? null ) && str_contains( $render( pe_widget( $F, $out ) ), 'data-filter="__all" aria-pressed="true">All</button>' ), 'Portafolio convertido: la marca de la conversión da el «All» de PRO.' );
// Sin piel guardada: la que el widget de PRO pinta hoy (su primera piel registrada); sin el constructor, la de PRO.
$check( 'classic' === Converter::tree( $tree( 'posts', array() ), $log, true )[0]['elements'][0]['settings']['_skin'] && 'archive_classic' === Converter::tree( $tree( 'archive-posts', array() ), $log, true )[0]['elements'][0]['settings']['_skin'], 'Sin piel guardada y sin el constructor cargado: la de por defecto de PRO.' );
\Elementor\Plugin::$instance->widgets_manager = new class() { public function get_widget_types( $name = null ) { return 'posts' === $name ? new class() { public function get_skins() { return array( 'cards' => 1, 'full_content' => 1, 'classic' => 1 ); } } : null; } };
$check( 'cards' === Converter::tree( $tree( 'posts', array() ), $log, true )[0]['elements'][0]['settings']['_skin'] && 'full_content' === Converter::tree( $tree( 'posts', array( '_skin' => 'full_content' ) ), $log, true )[0]['elements'][0]['settings']['_skin'], 'Sin piel guardada: la primera que el widget de PRO registra en el sitio (la que pinta); una piel guardada no cambia.' );
\Elementor\Plugin::$instance->widgets_manager = null;
// Posts_Terms (Elements) sustituye la piel «classic» del Posts de PRO sin moverla: debe seguir siendo la primera, la de
// por defecto, la que se pinta cuando `_skin` no está guardado. Del 2026-10-12 a 4.3.0.177 quedaba al final y un Posts creado
// con PRO se veía en «Tarjetas»; un sitio migrado debe verse exactamente como con PRO.
require_once $root . 'modules/digitalisimo-widgets/class-posts-terms.php';
$pt = new \ElementorPro\Modules\Posts\Widgets\Posts( array(), array() );
$pt_before = array_keys( $pt->get_skins() );
\Digitalisimo\Elements\Posts_Terms::replace_skin( $pt );
( new ReflectionMethod( $pt, 'register_controls' ) )->invoke( $pt );
$check( 'classic' === $pt_before[0] && $pt_before === array_keys( $pt->get_skins() ) && $pt->get_skin( 'classic' ) instanceof \Digitalisimo\Elements\Posts_Terms_Skin && 'classic' === ( $pt->controls['_skin']['default'] ?? '' ), 'Posts_Terms: la piel clásica sigue primera y es la de por defecto (' . implode( ', ', array_keys( $pt->get_skins() ) ) . ').' );
$check( 'classic' === $pt->get_current_skin_id(), 'Posts_Terms: un Posts sin `_skin` guardado se pinta con la piel clásica, como en PRO.' );
foreach ( array( 'posts', 'archive-posts', 'portfolio' ) as $type ) {
	$map = Registry::map( $type );
	$check( null !== $map && 'ready' === $map['status'] && 'hide' === $map['panel'], "$type: mapa listo y oculto en el panel." );
}

echo "DIGITALÍSIMO Elements: Entradas, Entradas del archivo y Portafolio propios validados ($checks comprobaciones).\n";
