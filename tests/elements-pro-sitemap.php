<?php
/**
 * Mapa del sitio (`digitalisimo-sitemap`), el widget propio que sustituye al Sitemap de PRO (C4 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/sitemap.php`:
 * - recursos: sólo su hoja (≤ 4 KB, sin el kit ni clases del proveedor, foco visible), sin script;
 * - los controles de PRO cargados a la vez que los propios (también los campos del repetidor): mismo nombre, tipo, valor
 *   por defecto, opciones, condiciones, rangos y orden;
 * - el mismo dato que PRO: con un `WP_Query`, un `Walker_Page` y un `wp_list_categories()` de prueba, los dos widgets
 *   pintan los mismos títulos, enlaces y textos y piden las mismas consultas, salvo el tope de entradas (sin consultas
 *   sin límite) y la exclusión de la entrada actual, que PRO ignoraba; marcado semántico, nofollow, «Ninguno» y escape;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, filas de cada tipo, globales, dinámicos, CSS
 *   personalizado con clases de PRO, responsivos): ningún `pro.unmapped`, cada clave cae en un control propio y lo
 *   convertido se pinta con los mismos textos.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace {
	$GLOBALS['h_wp'] = array(
		'queries'    => array(),
		'categories' => array(),
		'singular'   => 0,
		'posts'      => array(
			'page' => array(
				array( 'ID' => 2, 'post_title' => 'Inicio', 'post_parent' => 0 ),
				array( 'ID' => 3, 'post_title' => 'Servicios & más', 'post_parent' => 0 ),
				array( 'ID' => 4, 'post_title' => 'Diseño', 'post_parent' => 3 ),
				array( 'ID' => 5, 'post_title' => 'Privada', 'post_parent' => 0, 'password' => 'x' ),
			),
			'post' => array(
				array( 'ID' => 10, 'post_title' => 'Hola mundo', 'post_parent' => 0 ),
			),
		),
		'terms'      => array(
			'category' => array( array( 1, 'Sin categoría', 0, 3 ), array( 7, 'Noticias', 0, 0 ), array( 8, 'Locales', 7, 2 ) ),
			'post_tag' => array( array( 20, 'Etiqueta', 0, 1 ) ),
		),
	);
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function is_singular( $types = '' ) { return (bool) $GLOBALS['h_wp']['singular']; }
	function get_queried_object_id() { return (int) $GLOBALS['h_wp']['singular']; }
	function wp_list_categories( $args ) {
		$GLOBALS['h_wp']['categories'][] = $args;
		$out = '';
		foreach ( $GLOBALS['h_wp']['terms'][ $args['taxonomy'] ] ?? array() as $term ) {
			if ( ( ! empty( $args['hide_empty'] ) && 0 === $term[3] ) || ( ! $args['hierarchical'] ? false : 0 !== $term[2] ) ) {
				continue;
			}
			$out .= "\t<li class=\"cat-item cat-item-{$term[0]}\"><a href=\"https://sitio.test/?cat={$term[0]}\">" . htmlspecialchars( $term[1], ENT_QUOTES, 'UTF-8' ) . "</a>\n";
			if ( $args['hierarchical'] ) {
				foreach ( $GLOBALS['h_wp']['terms'][ $args['taxonomy'] ] as $child ) {
					if ( $child[2] === $term[0] && ( empty( $args['hide_empty'] ) || $child[3] ) ) {
						$out .= "<ul class='children'>\n\t<li class=\"cat-item cat-item-{$child[0]}\"><a href=\"https://sitio.test/?cat={$child[0]}\">" . htmlspecialchars( $child[1], ENT_QUOTES, 'UTF-8' ) . "</a>\n</li>\n</ul>\n";
					}
				}
			}
			$out .= "</li>\n";
		}
		return $out;
	}
	class WP_Query {
		public $posts = array();
		public function __construct( $args ) {
			$GLOBALS['h_wp']['queries'][] = $args;
			foreach ( $GLOBALS['h_wp']['posts'][ $args['post_type'] ] ?? array() as $post ) {
				if ( in_array( $post['ID'], (array) ( $args['post__not_in'] ?? array() ), true ) || ( false === $args['has_password'] && ! empty( $post['password'] ) ) ) {
					continue;
				}
				$this->posts[] = (object) $post;
			}
			if ( isset( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
				$this->posts = array_slice( $this->posts, 0, $args['posts_per_page'] );
			}
		}
		public function have_posts() { return (bool) $this->posts; }
	}
	class Walker_Page {
		public $tree_type = 'page';
		public function walk( $posts, $depth ) {
			$out = '';
			foreach ( $posts as $post ) {
				if ( -1 !== $depth && $post->post_parent ) {
					continue;
				}
				$out .= '<li class="page_item page-item-' . $post->ID . '"><a href="https://sitio.test/?p=' . $post->ID . '">' . htmlspecialchars( $post->post_title, ENT_QUOTES, 'UTF-8' ) . '</a>';
				if ( -1 !== $depth ) {
					foreach ( $posts as $child ) {
						if ( $child->post_parent === $post->ID ) {
							$out .= "<ul class='children'><li class=\"page_item page-item-{$child->ID}\"><a href=\"https://sitio.test/?p={$child->ID}\">" . htmlspecialchars( $child->post_title, ENT_QUOTES, 'UTF-8' ) . '</a></li></ul>';
						}
					}
				}
				$out .= '</li>';
			}
			return $out;
		}
	}
	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/sitemap.php' );

	use Digitalisimo\Elements\Theme_Elements\Widgets\Sitemap;

	$pro = h_widget( \ElementorPro\Modules\ThemeElements\Widgets\Sitemap::class );
	$own = h_widget( Sitemap::class );
	$map = require h_path( 'modules/digitalisimo-convert/maps/sitemap.php' );

	// 1. Registro y recursos.
	$css = h_assets( $own, 'Mapa del sitio', 'sitemap', array() );
	h_check( '.digi-sitemap{display:flex;flex-flow:row wrap}' === strtok( $css, "\n" ) && str_contains( $css, '.digi-sitemap__item a:focus-visible{outline:2px solid;outline-offset:2px}' ), 'La rejilla de PRO y el foco visible de los enlaces.' );

	// 2. Los controles de PRO, uno a uno (también los campos del repetidor).
	$skip = array(
		'sitemap_items.default' => true, // Títulos de las filas por defecto en español; el mapa completa los de PRO.
	);
	$diff = h_compare_widgets( $pro, $own, $skip );
	h_check( array() === $diff, "Controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	$rows = static function ( $rows ) { return array_map( static function ( $row ) { unset( $row['sitemap_title'] ); return $row; }, $rows ); };
	h_check( $rows( $pro->controls['sitemap_items']['default'] ) === $rows( $own->controls['sitemap_items']['default'] ) && 'Páginas' === $own->controls['sitemap_items']['default'][0]['sitemap_title'] && 'Categorías' === $own->controls['sitemap_items']['default'][1]['sitemap_title'], 'Las mismas filas por defecto que PRO, con los títulos en español.' );
	h_check( array( 'post' => 'Entradas', 'page' => 'Páginas' ) === $own->controls['sitemap_items']['fields']['sitemap_source_post_type']['options'] && array( 'category' => 'Categorías (category)', 'post_tag' => 'Etiquetas (post_tag)' ) === $own->controls['sitemap_items']['fields']['sitemap_source_taxonomy']['options'], 'Orígenes: los tipos públicos y sus taxonomías, como PRO.' );
	foreach ( $own->controls as $name => $control ) {
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $rule ) {
			h_check( ! str_contains( $selector, 'elementor' ) && ! str_contains( $selector, 'body' ), "$name: selector de PRO o con `body` ($selector)." );
		}
	}

	// 3. El mismo dato que PRO.
	$facts = static function ( string $html ): array {
		preg_match_all( '/<(h[1-6]|div|span|p)\b[^>]*class="[^"]*(?:elementor-sitemap-title|digi-sitemap__title)[^"]*"[^>]*>(.*?)<\/\1>/', $html, $titles );
		preg_match_all( '/<a ([^>]*)>(.*?)<\/a>/', $html, $links );
		preg_match_all( '/<span class="(?:elementor-sitemap-list|digi-sitemap__list)[^"]*">(.*?)<\/span>/', $html, $empty );
		return array( 'titles' => $titles[2], 'links' => array_map( null, $links[1], $links[2] ), 'empty' => count( $empty[1] ), 'children' => substr_count( $html, "<ul class='children'>" ) );
	};
	$scenarios = array(
		'por defecto'  => array(),
		'tipos'        => array( 'sitemap_items' => array(
			array( '_id' => 'a', 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page', 'sitemap_title' => '', 'sitemap_orderby_post_type' => 'post_title', 'sitemap_order' => 'asc', 'sitemap_hierarchical' => 'yes', 'sitemap_depth' => '2', 'sitemap_hide_empty' => 'yes', 'sitemap_orderby_taxonomy' => 'name', 'sitemap_source_taxonomy' => 'category' ),
			array( '_id' => 'b', 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'post', 'sitemap_title' => 'Blog', 'sitemap_orderby_post_type' => 'rand', 'sitemap_order' => 'desc', 'sitemap_hierarchical' => 'no', 'sitemap_depth' => '0', 'sitemap_hide_empty' => 'yes', 'sitemap_orderby_taxonomy' => 'name', 'sitemap_source_taxonomy' => 'category' ),
			array( '_id' => 'c', 'sitemap_type_selector' => 'taxonomy', 'sitemap_source_post_type' => 'page', 'sitemap_title' => '', 'sitemap_orderby_post_type' => 'post_date', 'sitemap_order' => 'asc', 'sitemap_hierarchical' => 'yes', 'sitemap_depth' => '0', 'sitemap_hide_empty' => '', 'sitemap_orderby_taxonomy' => 'id', 'sitemap_source_taxonomy' => 'category' ),
			array( '_id' => 'd', 'sitemap_type_selector' => 'taxonomy', 'sitemap_source_post_type' => 'page', 'sitemap_title' => 'Etiquetas', 'sitemap_orderby_post_type' => 'post_date', 'sitemap_order' => 'desc', 'sitemap_hierarchical' => 'no', 'sitemap_depth' => '0', 'sitemap_hide_empty' => 'yes', 'sitemap_orderby_taxonomy' => 'name', 'sitemap_source_taxonomy' => 'post_tag' ),
			array( '_id' => 'e', 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'producto', 'sitemap_title' => 'Productos', 'sitemap_orderby_post_type' => 'post_date', 'sitemap_order' => 'desc', 'sitemap_hierarchical' => 'no', 'sitemap_depth' => '0', 'sitemap_hide_empty' => 'yes', 'sitemap_orderby_taxonomy' => 'name', 'sitemap_source_taxonomy' => 'category' ),
		), 'sitemap_title_tag' => 'h3', 'sitemap_add_nofollow' => 'yes', 'sitemap_password_protected' => 'yes' ),
		'excluidas'    => array( 'sitemap_exclude' => array( 'manual_selection' ), 'sitemap_exclude_ids' => array( 3 ), 'sitemap_title_tag' => 'p' ),
	);
	$items_default = $own->controls['sitemap_items']['default'];
	foreach ( $scenarios as $label => $settings ) {
		$pro_settings = $settings + array( 'sitemap_items' => $pro->controls['sitemap_items']['default'] );
		$own_settings = $settings + array( 'sitemap_items' => array_map( static function ( $row, $pro_row ) { return array( 'sitemap_title' => $pro_row['sitemap_title'] ) + $row; }, $items_default, $pro->controls['sitemap_items']['default'] ) );
		foreach ( array( &$pro_settings, &$own_settings ) as &$s ) {
			foreach ( $s['sitemap_items'] as &$row ) {
				$row += array( 'sitemap_hierarchical' => 'no', 'sitemap_depth' => '0', 'sitemap_orderby_post_type' => 'post_date', 'sitemap_orderby_taxonomy' => 'name', 'sitemap_order' => 'desc', 'sitemap_hide_empty' => 'yes', 'sitemap_source_post_type' => 'page', 'sitemap_source_taxonomy' => 'category', 'sitemap_title' => '' );
			}
			unset( $row );
		}
		unset( $s );
		$pro_settings += array( 'sitemap_exclude_ids' => null );
		$GLOBALS['h_wp']['queries']    = array();
		$GLOBALS['h_wp']['categories'] = array();
		$a        = h_render( $pro, $pro_settings );
		$a_q      = $GLOBALS['h_wp']['queries'];
		$a_c      = $GLOBALS['h_wp']['categories'];
		$GLOBALS['h_wp']['queries']    = array();
		$GLOBALS['h_wp']['categories'] = array();
		$b        = h_render( $own, $own_settings );
		$b_q      = $GLOBALS['h_wp']['queries'];
		$b_c      = $GLOBALS['h_wp']['categories'];
		h_check( $facts( $a ) === $facts( $b ), "$label: los mismos títulos, enlaces, vacíos y subniveles que PRO.\nPRO: $a\nPropio: $b" );
		$norm = static function ( array $calls ): array {
			return array_map( static function ( $args ) { $args['post__not_in'] = array_values( (array) $args['post__not_in'] ); $args['depth'] = (int) $args['depth']; ksort( $args ); return $args; }, $calls );
		};
		h_check( $norm( $a_c ) === $norm( $b_c ), "$label: las mismas llamadas a wp_list_categories() que PRO: " . json_encode( $b_c ) . ' · PRO ' . json_encode( $a_c ) );
		h_check( count( $a_q ) === count( $b_q ), "$label: las mismas consultas que PRO." );
		foreach ( $a_q as $i => $query ) {
			$own_query = $b_q[ $i ];
			h_check( -1 === $own_query['posts_per_page'] && -1 === $query['posts_per_page'] && true === $own_query['no_found_rows'] && false === $own_query['update_post_term_cache'], "$label: todas las entradas, como PRO, sin contar filas." );
			unset( $query['posts_per_page'], $query['filter'], $own_query['posts_per_page'], $own_query['no_found_rows'], $own_query['update_post_term_cache'] );
			$query['post__not_in'] = array_values( (array) $query['post__not_in'] );
			ksort( $query );
			ksort( $own_query );
			h_check( $query === $own_query, "$label: consulta $i con los argumentos de PRO: " . json_encode( $own_query ) . ' · PRO ' . json_encode( $query ) );
		}
		$html[ $label ] = $b;
	}
	$t = $html['tipos'];
	h_check( str_starts_with( $t, '<div class="digi-sitemap"><div class="digi-sitemap__section"><h3 class="digi-sitemap__title digi-sitemap__title--page">Páginas</h3><ul class="digi-sitemap__list digi-sitemap__list--page"><li class="digi-sitemap__item digi-sitemap__item--page page_item page-item-2"><a rel="nofollow" href="https://sitio.test/?p=2">Inicio</a></li>' ), 'Sección con encabezado, lista y las clases propias delante de las de WordPress, con nofollow: ' . $t );
	h_check( str_contains( $t, '<span class="digi-sitemap__list digi-sitemap__list--producto">Ninguno</span>' ) && str_contains( $t, "<ul class='children'>" ) && str_contains( $t, 'Servicios &amp; más' ) && str_contains( $t, '>Privada<' ), 'Lista vacía con «Ninguno», subniveles de WordPress, títulos escapados y protegidas con «Mostrar».' );
	h_check( ! str_contains( $html['por defecto'], 'nofollow' ) && ! str_contains( $html['por defecto'], '>Privada<' ) && str_contains( $html['por defecto'], '<h2 class="digi-sitemap__title digi-sitemap__title--page">Pages</h2>' ), 'Por defecto: h2, sin nofollow ni protegidas.' );
	h_check( ! str_contains( $html['excluidas'], 'Servicios' ) && str_contains( $html['excluidas'], '<p class="digi-sitemap__title' ), 'Selección manual excluida y título en párrafo.' );
	// «Entrada actual»: PRO la ignoraba; el propio excluye la entrada en curso.
	$GLOBALS['h_wp']['singular'] = 2;
	$current = h_render( $own, array( 'sitemap_exclude' => array( 'current_post' ) ) );
	h_check( ! str_contains( $current, '>Inicio<' ) && str_contains( $current, '>Servicios &amp; más<' ), '«Entrada actual» excluye la entrada en curso.' );
	$GLOBALS['h_wp']['singular'] = 0;
	h_check( str_contains( h_render( $own, array( 'sitemap_exclude' => array( 'current_post' ) ) ), '>Inicio<' ), 'Fuera de una entrada no se excluye nada.' );
	$GLOBALS['h_wp']['filters']['digitalisimo_elements_sitemap_limit'] = static function () { return 1; };
	h_check( 1 === substr_count( h_render( $own, array( 'sitemap_items' => array( array( 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page', 'sitemap_title' => 'P' ) ) ) ), '<li ' ), 'El filtro digitalisimo_elements_sitemap_limit pone un tope si el sitio lo quiere.' );
	$GLOBALS['h_wp']['filters']['digitalisimo_elements_sitemap_limit'] = static function () { return -1; };
	$GLOBALS['h_wp']['queries'] = array();
	h_render( $own, array( 'sitemap_items' => array( array( 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page', 'sitemap_title' => 'P' ) ) ) );
	h_check( -1 === ( $GLOBALS['h_wp']['queries'][0]['posts_per_page'] ?? null ), 'Con el filtro en -1 (o en 0) tampoco hay tope.' );
	unset( $GLOBALS['h_wp']['filters'] );
	$evil = h_render( $own, array( 'sitemap_title_tag' => 'script', 'sitemap_items' => array( array( 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page"><x', 'sitemap_title' => '<img src=x onerror=alert(1)>Uno' ) ) ) );
	h_check( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, 'onerror' ) && ! str_contains( $evil, '"><x' ) && str_contains( $evil, '<div class="digi-sitemap__title' ), 'Etiqueta validada, título con el HTML permitido y tipo saneado: ' . $evil );
	h_check( '' === h_render( $own, array( 'sitemap_items' => array() ) ), 'Sin filas no se pinta nada.' );

	// 4. Conversión con el mapa.
	h_check( 'sitemap' === $map['source'] && 'digitalisimo-sitemap' === $map['target'] && in_array( $map['status'], array( 'draft', 'ready' ), true ) && 'hide' === $map['panel'], 'Mapa del Sitemap hacia el Mapa del sitio.' );
	$cases = array(
		'defaults'   => array(),
		'rows'       => array( 'sitemap_items' => array( array( '_id' => 'r1', 'sitemap_type_selector' => 'taxonomy', 'sitemap_source_taxonomy' => 'post_tag', 'sitemap_title' => 'Temas', 'sitemap_orderby_taxonomy' => 'id', 'sitemap_hide_empty' => '', 'sitemap_hierarchical' => 'yes', 'sitemap_depth' => '2', 'sitemap_order' => 'asc' ), array( '_id' => 'r2', 'sitemap_source_post_type' => 'post', 'sitemap_orderby_post_type' => 'menu_order' ) ), 'sitemap_columns' => '3', 'sitemap_columns_tablet' => '1', 'sitemap_title_tag' => 'h4', 'sitemap_add_nofollow' => 'yes' ),
		'exclude'    => array( 'sitemap_exclude' => array( 'current_post', 'manual_selection' ), 'sitemap_exclude_ids' => array( '12', '15' ), 'sitemap_password_protected' => 'yes' ),
		'style'      => array( 'sitemap_list_indent' => array( 'unit' => 'px', 'size' => 20 ), 'sitemap_list_indent_mobile' => array( 'unit' => 'px', 'size' => 8 ), 'sitemap_section_padding' => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true ), 'sitemap_title_color' => '#111111', 'sitemap_title_typography_typography' => 'custom', 'sitemap_title_typography_font_size' => array( 'unit' => 'px', 'size' => 22 ), 'sitemap_list_item_color' => '#222222', 'sitemap_list_item_typography_typography' => 'custom', 'sitemap_list_item_typography_font_weight' => '600', 'sitemap_bullet_color' => '#333333', 'sitemap_list_item_bullet_style' => 'square' ),
		'globals'    => array( 'sitemap_title_color' => '', '__globals__' => array( 'sitemap_title_color' => 'globals/colors?id=accent', 'sitemap_list_item_typography_typography' => 'globals/typography?id=secondary', 'sitemap_bullet_color' => 'globals/colors?id=primary' ) ),
		'dynamic'    => array( 'sitemap_items' => array( array( '_id' => 'd1', 'sitemap_type_selector' => 'post_type', '__dynamic__' => array( 'sitemap_title' => '[elementor-tag id="a1" name="site-title" settings="%7B%7D"]' ) ) ) ),
		'css'        => array( 'custom_css' => "selector .elementor-sitemap-wrap{gap:10px}\nselector .elementor-sitemap-section .elementor-sitemap-title{margin:0}\nselector .elementor-sitemap-page-list .elementor-sitemap-item-page a{color:red}\n.elementor-widget-sitemap .elementor-sitemap-category-title,selector .elementor-sitemap-item-category{x:y}\nselector .elementor-sitemap-post-list,selector .elementor-sitemap-post_tag-title,selector .elementor-sitemap-item-post_tag{a:b}\nselector .elementor-sitemap-product-list .elementor-sitemap-item-product,selector .elementor-sitemap-product_cat-title{c:d}", '_css_classes' => 'mi-mapa' ),
		'responsive' => array( 'sitemap_columns_mobile' => '2', 'sitemap_section_padding_tablet' => array( 'top' => '4', 'right' => '4', 'bottom' => '4', 'left' => '4', 'unit' => 'px', 'isLinked' => true ) ),
	);
	list( $out, $codes ) = h_convert( 'sitemap', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Sólo el CSS convertido (la exclusión de la entrada actual ya no es aproximada): ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	h_check( 'Pages' === $d['sitemap_items'][0]['sitemap_title'] && 'Categories' === $d['sitemap_items'][1]['sitemap_title'] && 'taxonomy' === $d['sitemap_items'][1]['sitemap_type_selector'] && 'sitemap' === $d['_digitalisimo_pro'], 'Un Sitemap sin cambios llega con las filas y los títulos que PRO pintaba.' );
	h_check( str_contains( h_render( $own, $d ), '>Pages</h2>' ) && str_contains( h_render( $own, $d ), '>Categories</h2>' ), 'Lo convertido se pinta con los mismos títulos.' );
	$r = $out['rows'];
	h_check( 'post_tag' === $r['sitemap_items'][0]['sitemap_source_taxonomy'] && 'r1' === $r['sitemap_items'][0]['_id'] && '2' === $r['sitemap_items'][0]['sitemap_depth'] && 'menu_order' === $r['sitemap_items'][1]['sitemap_orderby_post_type'] && 'post_type' === $r['sitemap_items'][1]['sitemap_type_selector'] && '1' === $r['sitemap_columns_tablet'] && 'h4' === $r['sitemap_title_tag'] && 'yes' === $r['sitemap_add_nofollow'], 'Filas con su id, columnas por dispositivo, etiqueta y nofollow.' );
	h_check( array( 'manual_selection' ) === $out['exclude']['sitemap_exclude'] && array( '12', '15' ) === $out['exclude']['sitemap_exclude_ids'] && 'yes' === $out['exclude']['sitemap_password_protected'], 'Exclusiones y protegidas; «Entrada actual», que PRO no aplicaba, no llega a lo convertido.' );
	$GLOBALS['h_wp']['singular'] = 2;
	h_check( str_contains( h_render( $own, $out['exclude'] + array( 'sitemap_items' => array( array( 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page', 'sitemap_title' => 'P' ) ) ) ), '>Inicio<' ), 'Lo convertido sigue listando la entrada en curso, como PRO.' );
	$GLOBALS['h_wp']['singular'] = 0;
	h_check( 8 === $out['style']['sitemap_list_indent_mobile']['size'] && '600' === $out['style']['sitemap_list_item_typography_font_weight'] && 'square' === $out['style']['sitemap_list_item_bullet_style'] && 22 === $out['style']['sitemap_title_typography_font_size']['size'], 'Estilos de la lista, el título y las viñetas.' );
	h_check( array( 'sitemap_title_color' => 'globals/colors?id=accent', 'sitemap_list_item_typography_typography' => 'globals/typography?id=secondary', 'sitemap_bullet_color' => 'globals/colors?id=primary' ) === $out['globals']['__globals__'], 'Globales del kit.' );
	h_check( array( 'sitemap_title' ) === array_keys( $out['dynamic']['sitemap_items'][0]['__dynamic__'] ), 'La etiqueta dinámica del título de la fila sigue a su campo.' );
	h_check( "selector .digi-sitemap{gap:10px}\nselector .digi-sitemap__section .digi-sitemap__title{margin:0}\nselector .digi-sitemap__list--page .digi-sitemap__item--page a{color:red}\n.elementor-widget-digitalisimo-sitemap .digi-sitemap__title--category,selector .digi-sitemap__item--category{x:y}\nselector .digi-sitemap__list--post,selector .digi-sitemap__title--post_tag,selector .digi-sitemap__item--post_tag{a:b}\nselector .digi-sitemap__list--product .digi-sitemap__item--product,selector .digi-sitemap__title--product_cat{c:d}" === $out['css']['custom_css'], 'CSS personalizado con las clases propias, también las de un tipo de un plugin (producto, categoría de producto): ' . $out['css']['custom_css'] );
	h_check( '2' === $out['responsive']['sitemap_columns_mobile'] && '4' === $out['responsive']['sitemap_section_padding_tablet']['top'], 'Valores por dispositivo.' );
	$GLOBALS['h_wp']['posts']['post'][] = array( 'ID' => 11, 'post_title' => 'Otra', 'post_parent' => 0 );
	$all = h_render(
		$own,
		array(
			'sitemap_items' => array(
				array( 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'page', 'sitemap_title' => 'P' ),
				array( 'sitemap_type_selector' => 'post_type', 'sitemap_source_post_type' => 'post', 'sitemap_title' => 'E' ),
				array( 'sitemap_type_selector' => 'taxonomy', 'sitemap_source_taxonomy' => 'category', 'sitemap_title' => 'C' ),
				array( 'sitemap_type_selector' => 'taxonomy', 'sitemap_source_taxonomy' => 'post_tag', 'sitemap_title' => 'T' ),
			),
		)
	);
	h_classes_exist( $map, $own, $all );

	echo 'DIGITALÍSIMO Elements: Mapa del sitio (' . count( $own->controls ) . ' controles, ' . count( $own->controls['sitemap_items']['fields'] ) . ' campos por fila, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B) y su mapa desde el Sitemap de PRO (' . count( $scenarios ) . ' escenarios con el mismo dato, ' . count( $cases ) . " casos) validados.\n";
}
