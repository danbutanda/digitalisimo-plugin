<?php
/**
 * Información de la entrada (`digitalisimo-post-info`), el widget propio que sustituye al Post Info de PRO (C4 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/post-info.php`:
 * - recursos: sólo su hoja (≤ 4 KB, sin el kit, `url()` ni clases del proveedor, sin colores propios), sin script, y las
 *   hojas de Font Awesome sólo cuando los iconos se pintan con la fuente (como PRO);
 * - los mismos controles que el Post Info de PRO, cargado a la vez: secciones, nombres, orden, tipos, valores por
 *   defecto, opciones, condiciones, rangos, unidades, globales y los campos del repetidor;
 * - marcado con los mismos datos que PRO en una entrada de prueba (autor, avatar, fecha, hora, comentarios, términos,
 *   texto personalizado, prefijos y formatos): mismos textos visibles y mismos enlaces; sin microdatos; escape;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, en línea y tradicional, divisor, responsivos,
 *   globales, dinámicos en el widget y en las filas, FA4 heredado, CSS personalizado con clases de PRO): ningún
 *   `pro.unmapped`, cada clave cae en un control propio y lo convertido se pinta con los mismos textos que PRO.
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php'; // PHP 7.4: polyfills de WordPress y reflexión abierta.
}
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace {
	$GLOBALS['h_wp'] = array();
	$GLOBALS['pi']   = array( 'comments_open' => true, 'comments' => 3, 'terms' => array() );
	function get_the_ID() { return 42; }
	function get_the_author_meta( $field ) { return 'ID' === $field ? 7 : ( 'display_name' === $field ? $GLOBALS['pi']['author'] ?? 'Ana Autora' : '' ); }
	function get_author_posts_url( $id ) { return 'https://sitio.test/author/ana/?id=' . (int) $id; }
	function get_avatar_url( $id, $size = 96 ) { return 'https://sitio.test/wp-content/uploads/avatar-' . (int) $id . '-' . (int) ( is_array( $size ) ? 96 : $size ) . '.png'; }
	function get_the_time( $format = '' ) { $t = mktime( 15, 31, 0, 3, 6, 2018 ); return 'c' === $format ? date( 'c', $t ) : date( '' === $format ? 'F j, Y' : $format, $t ); }
	function get_post_time( $format = 'U' ) { return date( $format, mktime( 15, 31, 0, 3, 6, 2018 ) ); }
	function get_day_link( $y, $m, $d ) { return "https://sitio.test/$y/$m/$d/"; }
	function comments_open() { return $GLOBALS['pi']['comments_open']; }
	function get_comments_number() { return (string) $GLOBALS['pi']['comments']; }
	function get_comments_link() { return 'https://sitio.test/entrada/#comments'; }
	function wp_get_post_terms( $id, $taxonomy ) { return $GLOBALS['pi']['terms'][ is_array( $taxonomy ) ? implode( ',', $taxonomy ) : $taxonomy ] ?? array(); }
	function get_term_link( $term ) { return 'https://sitio.test/' . $term->taxonomy . '/' . $term->slug . '/'; }

	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/post-info.php' );

	\Elementor\Plugin::$instance = (object) array( 'experiments' => new \ElementorPro\H_Experiments() );
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Theme_Elements\Widgets\Post_Info;

	$term = static function ( $id, $name, $slug, $tax ) { return (object) array( 'term_id' => $id, 'name' => $name, 'slug' => $slug, 'taxonomy' => $tax ); };
	$GLOBALS['pi']['terms'] = array(
		'category' => array( $term( 3, 'Categoría de prueba', 'categoria-ab', 'category' ), $term( 4, 'Otra & más', 'otra', 'category' ) ),
		'post_tag' => array( $term( 9, 'Etiqueta', 'etiqueta', 'post_tag' ) ),
	);

	// Visibles y enlaces de un marcado, para comparar el de PRO y el propio.
	$read = static function ( string $html ): array {
		preg_match_all( '/<a\b[^>]*href="([^"]*)"/', $html, $links );
		preg_match_all( '/<img\b[^>]*src="([^"]*)"/', $html, $images );
		$text = html_entity_decode( trim( preg_replace( '/\s+/', ' ', strip_tags( preg_replace( '/<(span|time)\b/', ' <$1', $html ) ) ) ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+([,])/', '$1', $text );
		return array( 'text' => $text, 'links' => $links[1], 'images' => $images[1] );
	};

	// Como Elementor: cada fila guardada se completa con los valores por defecto de los campos del repetidor.
	$fill = static function ( $widget, array $settings ): array {
		if ( isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ) {
			foreach ( $settings['icon_list'] as $i => $row ) {
				$row += array( '_id' => 'r' . $i ); // El editor da un id a cada fila, también a las filas por defecto.
				foreach ( $widget->controls['icon_list']['fields'] as $name => $field ) {
					if ( ! array_key_exists( $name, $row ) ) {
						$row[ $name ] = array_key_exists( 'default', $field ) ? $field['default'] : '';
					}
				}
				$settings['icon_list'][ $i ] = $row;
			}
		}
		return $settings;
	};
	$paint = static function ( $widget, array $settings ) use ( $fill ): string {
		return h_render( $widget, $fill( $widget, array_merge( h_defaults( $widget ), $settings ) ) );
	};

	// 1. Registro y recursos.
	$own = h_widget( Post_Info::class );
	$pro = h_widget( \ElementorPro\Modules\ThemeElements\Widgets\Post_Info::class );
	$css = h_assets( $own, 'Información de la entrada', 'post-info', array() );
	$GLOBALS['h_wp']['features'] = array( 'e_optimized_markup' );
	h_check( array( 'digitalisimo-post-info', 'elementor-icons-fa-regular', 'elementor-icons-fa-solid' ) === $own->get_style_depends() && false === $own->has_widget_inner_wrapper(), 'Con iconos de fuente, las hojas de Font Awesome que usa PRO; marcado optimizado sin envoltura interior.' );
	$GLOBALS['h_wp']['features'] = array( 'e_optimized_markup', 'e_font_icon_svg' );
	h_check( str_contains( $css, '.digi-post-info a:focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $css, '.digi-post-info .digi-post-info__avatar{' ), 'Foco visible propio y avatar redondo con más especificidad que la regla de imágenes del núcleo.' );
	foreach ( array( 'digi-post-info--inline', 'digi-post-info__item', 'digi-post-info__icon', 'digi-post-info__text', 'digi-post-info__avatar', 'digi-post-info__link' ) as $class ) {
		h_check( str_contains( $css, '.' . $class ), "La hoja estiliza .$class" );
	}

	// 2. Los mismos controles que PRO.
	$skip = array(
		// El prefijo propio de la alineación y su diccionario de variables (PRO usaba las clases de la hoja del núcleo).
		'icon_align.selectors' => true,
	);
	$diff = h_compare_widgets( $pro, $own, $skip );
	h_check( ! $diff, "Controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	h_check( 'digi-post-info%s--align-' === $own->controls['icon_align']['prefix_class'] && 'elementor%s-align-' === $pro->controls['icon_align']['prefix_class'], 'La alineación lleva el prefijo propio.' );
	foreach ( $own->controls['icon_align']['selectors_dictionary'] as $value => $rules ) {
		h_check( str_contains( $rules, 'text-align:' ) && str_contains( $rules, '--digi-pi-justify:' ), "Alineación $value: fija las dos propiedades (un dispositivo no hereda las de otro)." );
	}
	h_check( 'center' === array_search( 'text-align: center; --digi-pi-justify: center', $own->controls['icon_align']['selectors_dictionary'], true ), 'Sólo «Centro» centra los elementos, como PRO con la hoja actual del núcleo.' );
	h_check( 'text-align: left; --digi-pi-justify: center' === $own->controls['icon_align']['selectors_dictionary']['center-left'] && 'text-align: right; --digi-pi-justify: center' === $own->controls['icon_align']['selectors_dictionary']['center-right'] && array( 'left', 'center', 'right' ) === array_keys( $own->controls['icon_align']['options'] ), 'Lo convertido puede centrar los elementos con el texto a un lado (`center-left`/`center-right`), sin que el panel lo ofrezca.' );
	h_check( array_keys( $pro->controls['icon_list']['fields'] ) === array_keys( $own->controls['icon_list']['fields'] ) && $pro->controls['icon_list']['default'] === $own->controls['icon_list']['default'], 'El repetidor tiene los mismos campos y filas por defecto.' );
	foreach ( $own->controls + $own->controls['icon_list']['fields'] as $name => $control ) {
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $rule ) {
			h_check( ! str_contains( $selector, 'elementor-' ) || str_contains( $selector, '{{CURRENT_ITEM}}' ) && ! preg_match( '/\.elementor-(?!repeater)/', $selector ), "$name: selector con clases del proveedor: $selector" );
			h_check( ! str_contains( $rule, '--e-' ), "$name: variable del proveedor en $rule" );
		}
	}

	// 3. Marcado con los mismos datos que PRO.
	$rows = array(
		array( '_id' => 'a1', 'type' => 'author', 'link' => 'yes', 'show_icon' => 'default', 'selected_icon' => array( 'value' => 'far fa-user-circle', 'library' => 'fa-regular' ) ),
		array( '_id' => 'a2', 'type' => 'date', 'date_format' => 'default', 'link' => 'yes', 'show_icon' => 'default', 'selected_icon' => array( 'value' => 'fas fa-calendar', 'library' => 'fa-solid' ) ),
		array( '_id' => 'a3', 'type' => 'time', 'time_format' => 'default', 'link' => 'yes', 'show_icon' => 'default', 'selected_icon' => array( 'value' => 'far fa-clock', 'library' => 'fa-regular' ) ),
		array( '_id' => 'a4', 'type' => 'comments', 'link' => 'yes', 'show_icon' => 'default', 'comments_custom_strings' => 'yes', 'string_no_comments' => 'Nada', 'string_one_comment' => 'Uno', 'string_comments' => '%s comentarios' ),
		array( '_id' => 'a5', 'type' => 'terms', 'taxonomy' => 'category', 'link' => 'yes', 'show_icon' => 'none', 'text_prefix' => 'En' ),
		array( '_id' => 'a6', 'type' => 'terms', 'taxonomy' => 'post_tag', 'link' => '', 'show_icon' => 'custom', 'selected_icon' => array( 'value' => 'fas fa-tag', 'library' => 'fa-solid' ) ),
		array( '_id' => 'a7', 'type' => 'custom', 'custom_text' => 'Lectura de 5 min', 'link' => 'yes', 'custom_url' => array( 'url' => 'https://sitio.test/lectura/', 'is_external' => 'on', 'nofollow' => 'on' ), 'show_icon' => 'default' ),
		array( '_id' => 'a8', 'type' => 'author', 'link' => '', 'show_avatar' => 'yes', 'show_icon' => 'default', 'text_prefix' => 'Por' ),
		array( '_id' => 'a9', 'type' => 'date', 'date_format' => 'custom', 'custom_date_format' => 'd/m/Y', 'link' => '', 'show_icon' => 'none' ),
		array( '_id' => 'b1', 'type' => 'time', 'time_format' => '2', 'show_icon' => 'none' ),
		array( '_id' => 'b2', 'type' => 'date', 'date_format' => '1', 'link' => 'yes', 'show_icon' => 'default' ),
		array( '_id' => 'b3', 'type' => 'terms', 'taxonomy' => 'genero', 'link' => 'yes' ),
	);
	foreach ( array( 'inline', 'traditional' ) as $view ) {
		$settings = array( 'view' => $view, 'icon_list' => $rows );
		$a        = $read( $paint( $pro, $settings ) );
		$b        = $read( $html = $paint( $own, $settings ) );
		h_check( $a === $b, "$view: los mismos textos, enlaces e imágenes que PRO.\nPRO: " . json_encode( $a, JSON_UNESCAPED_UNICODE ) . "\npropio: " . json_encode( $b, JSON_UNESCAPED_UNICODE ) );
		h_check( 0 === strpos( $html, '<ul class="digi-post-info' . ( 'inline' === $view ? ' digi-post-info--inline' : '' ) . '"><li class="digi-post-info__item elementor-repeater-item-a1"><a class="digi-post-info__link" href="https://sitio.test/author/ana/?id=7"><span class="digi-post-info__icon"><svg class="e-font-icon-svg" data-icon="far fa-user-circle" aria-hidden="true"></svg></span><span class="digi-post-info__text digi-post-info__text--type-author">Ana Autora</span></a></li>' ), "$view: lista con el enlace, el icono decorativo y el texto: " . substr( $html, 0, 400 ) );
	}
	h_check( 'Ana Autora March 6, 2018 3:31 pm 3 comentarios En Categoría de prueba, Otra & más Etiqueta Lectura de 5 min Por Ana Autora 06/03/2018 15:31 2018-03-06' === $b['text'], 'Textos de cada tipo, con los formatos de PRO: ' . $b['text'] );
	h_check( ! str_contains( $html, 'itemprop' ) && str_contains( $html, '<time datetime="2018-03-06T15:31:00+00:00">March 6, 2018</time>' ) && str_contains( $html, 'href="https://sitio.test/lectura/" target="_blank" rel="nofollow"' ), 'Sin microdatos, fecha con datetime y enlace personalizado con sus atributos.' );
	h_check( str_contains( $html, '<img class="digi-post-info__avatar" src="https://sitio.test/wp-content/uploads/avatar-7-96.png" alt="Imagen de Ana Autora" width="96" height="96" decoding="async">' ) && str_contains( $html, '<span class="digi-post-info__prefix">Por</span> Ana Autora' ), 'Avatar de 96 px con texto alternativo, y el prefijo antes del dato.' );
	h_check( str_contains( $html, '<a href="https://sitio.test/category/categoria-ab/" class="digi-post-info__term">Categoría de prueba</a>, <a href="https://sitio.test/category/otra/" class="digi-post-info__term">Otra &amp; más</a>' ) && str_contains( $html, '<span class="digi-post-info__term">Etiqueta</span>' ) && str_contains( $html, 'data-icon="fas fa-tag"' ), 'Términos enlazados y sin enlace, con el icono elegido.' );
	h_check( ! str_contains( $html, 'elementor-repeater-item-b3' ) && ! preg_match( '/elementor-repeater-item-a5"><a/', $html ), 'Una taxonomía sin términos no pinta la fila; los términos no envuelven la fila en un enlace.' );
	// Comentarios: textos por defecto (propios), uno y cerrados.
	$comments = array( 'icon_list' => array( array( '_id' => 'c1', 'type' => 'comments', 'link' => 'yes', 'show_icon' => 'default' ) ) );
	h_check( str_contains( $paint( $own, $comments ), '>3 comentarios</span>' ), 'Comentarios con el texto propio por defecto.' );
	$GLOBALS['pi']['comments'] = 1;
	h_check( str_contains( $paint( $own, $comments ), '>Un comentario</span>' ) && str_contains( $paint( $pro, $comments ), 'One Comment' ), 'Un comentario.' );
	$GLOBALS['pi']['comments'] = 0;
	h_check( str_contains( $paint( $own, $comments ), '>Sin comentarios</span>' ), 'Sin comentarios.' );
	$GLOBALS['pi']['comments_open'] = false;
	h_check( '' === $paint( $own, $comments ) && '' === trim( $paint( $pro, $comments ) ), 'Con los comentarios cerrados no se pinta nada, como PRO.' );
	$GLOBALS['pi']['comments_open'] = true;
	$GLOBALS['pi']['comments']      = 3;
	h_check( '' === $paint( $own, array( 'icon_list' => array() ) ), 'Sin filas no se pinta nada.' );
	// FA4 heredado sin migrar y migrado.
	$fa4 = $paint( $own, array( 'icon_list' => array( array( '_id' => 'f1', 'type' => 'date', 'show_icon' => 'custom', 'icon' => 'fa fa-star', 'selected_icon' => array( 'value' => '', 'library' => '' ) ), array( '_id' => 'f2', 'type' => 'date', 'show_icon' => 'custom', 'icon' => 'fa fa-star', '__fa4_migrated' => array( 'selected_icon' => true ), 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) ) ) ) );
	h_check( str_contains( $fa4, '<i class="fa fa-star" aria-hidden="true"></i>' ) && str_contains( $fa4, 'data-icon="fas fa-star"' ), 'El icono de Font Awesome 4 sin migrar se pinta como en PRO; el migrado, con Icons_Manager.' );
	// Escape.
	$GLOBALS['pi']['author'] = 'Ana <script>x</script>"';
	$evil = $paint( $own, array( 'icon_list' => array( array( '_id' => 'e1', 'type' => 'custom', 'custom_text' => 'Hola <a href="https://a.test/" onclick="x()">enlace</a><script>alert(1)</script><img src=x onerror=y>', 'text_prefix' => '<b>no</b>', 'link' => '' ), array( '_id' => 'e2', 'type' => 'author', 'text_prefix' => 'A & B', 'show_avatar' => 'yes' ) ) ) );
	unset( $GLOBALS['pi']['author'] );
	h_check( ! str_contains( $evil, '<script' ) && ! str_contains( $evil, '<img src=x' ) && str_contains( $evil, '<a href="https://a.test/"' ) && str_contains( $evil, '<span class="digi-post-info__prefix">A &amp; B</span>' ) && str_contains( $evil, 'alt="Imagen de Ana &quot;"' ), 'Texto por wp_kses() con los enlaces permitidos (como PRO), prefijo escapado una vez y texto alternativo sin etiquetas: ' . $evil );
	$markdown = $own;
	$markdown->settings = $fill( $own, array( 'view' => 'inline', 'icon_list' => $rows ) );
	$pro->settings      = $markdown->settings;
	h_check( $pro->render_markdown() === $markdown->render_markdown() && str_contains( $markdown->render_markdown(), "- [Ana Autora](https://sitio.test/author/ana/?id=7)\n- [March 6, 2018](https://sitio.test/2018/03/6/)\n- 3:31 pm" ), "Markdown como PRO:\n" . $markdown->render_markdown() );

	// 4. Conversión de Post Info de PRO.
	Registry::reset();
	$map = Registry::map( 'post-info', true );
	h_check( null !== $map && null !== Registry::map( 'post-info' ) && 'ready' === $map['status'] && 'digitalisimo-post-info' === $map['target'] && 'hide' === $map['panel'], 'El mapa está listo (A/B de tests/playground/pro-ab.sh en verde) y oculta del panel el widget de PRO.' );
	$cases = array(
		'defaults'    => array(),
		'traditional' => array( 'view' => 'traditional', 'icon_list' => $rows, 'divider' => 'yes', 'divider_style' => 'dashed', 'divider_weight' => array( 'unit' => 'px', 'size' => 2 ), 'divider_width' => array( 'unit' => '%', 'size' => 50 ), 'divider_color' => '#ff0000', 'space_between' => array( 'unit' => 'px', 'size' => 20 ) ),
		'inline'      => array( 'icon_list' => $rows, 'divider' => 'yes', 'divider_height' => array( 'unit' => 'px', 'size' => 12 ), 'icon_align' => 'center', 'icon_align_mobile' => 'left', 'space_between_tablet' => array( 'unit' => 'px', 'size' => 8 ) ),
		'styles'      => array( 'icon_color' => '#123456', 'icon_size' => array( 'unit' => 'px', 'size' => 20 ), 'icon_size_mobile' => array( 'unit' => 'px', 'size' => 12 ), 'text_indent' => array( 'unit' => 'px', 'size' => 8 ), 'text_color' => '#333333', 'icon_typography_typography' => 'custom', 'icon_typography_font_size' => array( 'unit' => 'px', 'size' => 13 ), 'icon_typography_font_weight' => '600', 'icon_typography_text_transform' => 'uppercase' ),
		'globals'     => array( 'icon_color' => '', 'text_color' => '', '__globals__' => array( 'icon_color' => 'globals/colors?id=accent', 'text_color' => 'globals/colors?id=primary', 'divider_color' => 'globals/colors?id=text', 'icon_typography_typography' => 'globals/typography?id=secondary' ) ),
		'dynamic'     => array( 'icon_list' => array( array( '_id' => 'd1', 'type' => 'custom', 'custom_text' => '', 'link' => 'yes', 'custom_url' => array( 'url' => '' ), '__dynamic__' => array( 'custom_text' => '[elementor-tag id="t1" name="post-custom-field" settings="%7B%7D"]', 'custom_url' => '[elementor-tag id="t2" name="post-url" settings="%7B%7D"]' ) ), array( '_id' => 'd2', 'type' => 'author', 'text_prefix' => '', '__dynamic__' => array( 'text_prefix' => '[elementor-tag id="t3" name="site-title" settings="%7B%7D"]' ), 'avatar_size' => array( 'unit' => 'px', 'size' => 30 ), 'avatar_size_mobile' => array( 'unit' => 'px', 'size' => 20 ), 'show_avatar' => 'yes' ) ) ),
		'fa4'         => array( 'icon_list' => array( array( '_id' => 'g1', 'type' => 'date', 'show_icon' => 'custom', 'icon' => 'fa fa-star', 'selected_icon' => array( 'value' => '', 'library' => '' ) ), array( '_id' => 'g2', 'type' => 'comments', 'comments_custom_strings' => 'yes', 'string_one_comment' => 'Un solo', 'string_comments' => '' ) ) ),
		// «Centro» en escritorio sigue centrando en los menores en PRO; una tableta en «Fin» con el móvil sin valor, y la pantalla
		// ancha (min-width) también, porque la clase de escritorio no tiene consulta de medios.
		'cascade'     => array( 'icon_align' => 'left', 'icon_align_laptop' => 'center', 'icon_align_tablet' => 'right', 'icon_align_mobile' => '', 'icon_align_widescreen' => 'right' ),
		'wide'        => array( 'icon_align' => 'center', 'icon_align_widescreen' => 'left', 'icon_align_tablet' => 'center', 'icon_align_mobile' => 'right' ),
		'css'         => array( 'custom_css' => "selector .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item{gap:2px}\nselector .elementor-post-info__item--type-date{color:red}\nselector .elementor-icon-list-icon .elementor-avatar{border:1px solid}\nselector .elementor-post-info__terms-list-item:hover,selector .elementor-post-info__item-prefix{x:y}\n.elementor-widget-post-info.elementor-align-center .elementor-icon-list-text{z:1}", '_css_classes' => 'mi-info', '_margin' => array( 'top' => '4', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
	);
	list( $out, $codes ) = h_convert( 'post-info', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni pro.approx (la alineación por dispositivo ya no es aproximada); sólo el CSS convertido: ' . implode( ', ', $codes ) );
	h_check( 4 === count( $out['defaults']['icon_list'] ) && 'comments' === $out['defaults']['icon_list'][3]['type'] && 'yes' === $out['defaults']['icon_list'][3]['comments_custom_strings'] && 'No Comments' === $out['defaults']['icon_list'][3]['string_no_comments'] && '%s Comments' === $out['defaults']['icon_list'][3]['string_comments'] && 'post-info' === $out['defaults']['_digitalisimo_pro'], 'Un Post Info sin cambios llega con las filas por defecto y los textos de comentarios de PRO.' );
	h_check( 'Un solo' === $out['fa4']['icon_list'][1]['string_one_comment'] && '%s Comments' === $out['fa4']['icon_list'][1]['string_comments'] && 'No Comments' === $out['fa4']['icon_list'][1]['string_no_comments'] && 'fa fa-star' === $out['fa4']['icon_list'][0]['icon'], 'Un formato propio conserva lo escrito y completa lo vacío con lo de PRO; el icono FA4 heredado pasa.' );
	h_check( 'Nada' === $out['traditional']['icon_list'][3]['string_no_comments'] && '%s comentarios' === $out['traditional']['icon_list'][3]['string_comments'], 'Los textos escritos por el sitio no cambian.' );
	h_check( 'traditional' === $out['traditional']['view'] && 'dashed' === $out['traditional']['divider_style'] && 50 === $out['traditional']['divider_width']['size'] && '#ff0000' === $out['traditional']['divider_color'] && 20 === $out['traditional']['space_between']['size'], 'Disposición, divisor y separación.' );
	h_check( 'center' === $out['inline']['icon_align'] && 'center-left' === $out['inline']['icon_align_mobile'] && 8 === $out['inline']['space_between_tablet']['size'] && 12 === $out['inline']['divider_height']['size'], 'Alineación y separación por dispositivo: con escritorio en «Centro», el móvil en «Inicio» queda con los elementos centrados y el texto a la izquierda, como en PRO.' );
	h_check( 'left' === $out['cascade']['icon_align'] && 'center' === $out['cascade']['icon_align_laptop'] && 'center-right' === $out['cascade']['icon_align_tablet'] && '' === $out['cascade']['icon_align_mobile'] && 'right' === $out['cascade']['icon_align_widescreen'], '«Centro» en el portátil centra la tableta; el móvil sin valor hereda; la pantalla ancha no depende del portátil: ' . json_encode( array_intersect_key( $out['cascade'], array_flip( array( 'icon_align', 'icon_align_laptop', 'icon_align_tablet', 'icon_align_mobile', 'icon_align_widescreen' ) ) ) ) );
	h_check( 'center-left' === $out['wide']['icon_align_widescreen'] && 'center' === $out['wide']['icon_align_tablet'] && 'center-right' === $out['wide']['icon_align_mobile'], 'Con escritorio en «Centro», la pantalla ancha y los menores centran los elementos y conservan la alineación de su texto.' );
	h_check( 'center' === $out['inline']['icon_align'] && ! isset( $out['defaults']['icon_align'] ), 'Sin alineación guardada no se añade ninguna.' );
	h_check( 12 === $out['styles']['icon_size_mobile']['size'] && '600' === $out['styles']['icon_typography_font_weight'] && 'uppercase' === $out['styles']['icon_typography_text_transform'] && 8 === $out['styles']['text_indent']['size'], 'Icono, sangría y tipografía.' );
	h_check( array( 'icon_color' => 'globals/colors?id=accent', 'text_color' => 'globals/colors?id=primary', 'divider_color' => 'globals/colors?id=text', 'icon_typography_typography' => 'globals/typography?id=secondary' ) === $out['globals']['__globals__'], 'Colores y tipografía globales del kit siguen a su control.' );
	$d = $out['dynamic']['icon_list'];
	h_check( 'd1' === $d[0]['_id'] && '[elementor-tag id="t1" name="post-custom-field" settings="%7B%7D"]' === $d[0]['__dynamic__']['custom_text'] && isset( $d[0]['__dynamic__']['custom_url'], $d[1]['__dynamic__']['text_prefix'] ) && 20 === $d[1]['avatar_size_mobile']['size'], 'Las etiquetas dinámicas y los tamaños por dispositivo de las filas siguen a su campo, con su id.' );
	h_check( "selector .digi-post-info.digi-post-info--inline .digi-post-info__item{gap:2px}\nselector .digi-post-info__text--type-date{color:red}\nselector .digi-post-info__icon .digi-post-info__avatar{border:1px solid}\nselector .digi-post-info__term:hover,selector .digi-post-info__prefix{x:y}\n.elementor-widget-digitalisimo-post-info.digi-post-info--align-center .digi-post-info__text{z:1}" === $out['css']['custom_css'] && 'mi-info' === $out['css']['_css_classes'], 'CSS personalizado con las clases propias: ' . $out['css']['custom_css'] );
	h_classes_exist( $map, $own, $paint( $own, array( 'view' => 'inline', 'icon_list' => $rows ) ) . $paint( $own, array( 'icon_list' => array( array( '_id' => 'x', 'type' => 'terms', 'taxonomy' => 'category', 'text_prefix' => 'p', 'link' => 'yes' ), array( '_id' => 'y', 'type' => 'author', 'show_avatar' => 'yes' ) ) ) ) );
	// Lo convertido se pinta con los mismos textos que PRO.
	foreach ( array( 'defaults', 'traditional', 'inline', 'fa4' ) as $id ) {
		$pro_settings = $cases[ $id ] + array( 'icon_list' => $pro->controls['icon_list']['default'] );
		$a = $read( $paint( $pro, $pro_settings ) );
		$b = $read( $paint( $own, $out[ $id ] ) );
		h_check( $a === $b, "$id: lo convertido se pinta como PRO.\nPRO: " . json_encode( $a, JSON_UNESCAPED_UNICODE ) . "\npropio: " . json_encode( $b, JSON_UNESCAPED_UNICODE ) );
	}
	h_check( 'Ana Autora March 6, 2018 3:31 pm 3 Comments' === $read( $paint( $own, $out['defaults'] ) )['text'], 'Por defecto: autor, fecha, hora y «3 Comments», como PRO en un sitio en inglés.' );

	echo 'DIGITALÍSIMO Elements: Información de la entrada (' . count( $own->controls ) . ' controles, ' . count( $own->controls['icon_list']['fields'] ) . ' campos por fila, ' . count( $own->groups ) . ' grupo, hoja de ' . strlen( $css ) . ' B) y su mapa desde el Post Info de PRO (' . count( $cases ) . " casos) validados.\n";
}
