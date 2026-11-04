<?php
/**
 * Caja de autor (`digitalisimo-author-box`), el widget propio que sustituye al Author Box de PRO (C4 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/author-box.php`:
 * - recursos: sólo su hoja (≤ 4 KB, sin el kit, `url()` ni clases del proveedor, sólo el color por defecto de PRO), sin
 *   JavaScript ni nada vetado por el empaquetado;
 * - los mismos controles que PRO (cargado a la vez): nombre, tipo, valores por defecto, opciones, condiciones, rangos,
 *   unidades, globales, secciones y orden; más la sección «Perfil ampliado» integrada (la de `Author_Box_Extras`);
 * - marcado con los mismos datos que PRO (autor actual y personalizado, enlaces a la web y al archivo, botón, imagen de la
 *   biblioteca con `wp_get_attachment_image()`), mismos textos y enlaces que el de PRO pintado con los mismos ajustes,
 *   escape, perfil ampliado en sus puntos y el usuario elegido sustituyendo los datos;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, personalizado, globales, dinámicos, CSS
 *   personalizado con clases de PRO, responsivos, perfil ampliado): ningún `pro.unmapped`, cada clave cae en un control
 *   propio y lo convertido se pinta con los mismos textos.
 */
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace {
	$GLOBALS['ab'] = array(
		'author' => array( 'ID' => 7, 'display_name' => 'Ana <b>Pérez</b>', 'user_url' => 'https://ana.test/', 'description' => 'Escribe sobre <em>diseño</em>.' ),
		'users'  => array( 12 => (object) array( 'ID' => 12, 'first_name' => 'Luis', 'last_name' => 'Gómez', 'display_name' => 'luisg', 'description' => 'Bio de Luis', 'user_nicename' => 'luis-g' ) ),
		'styles' => array(),
	);
	function get_the_author_meta( $field = '' ) { return $GLOBALS['ab']['author'][ $field ] ?? ''; }
	function get_avatar_url( $id, $args = array() ) { return 'https://sitio.test/avatar/' . (int) $id . '?s=' . (int) ( is_array( $args ) ? ( $args['size'] ?? 96 ) : $args ); }
	function get_author_posts_url( $id ) { return 'https://sitio.test/author/' . (int) $id . '/'; }
	function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) { return array( 'https://sitio.test/wp-content/uploads/a-' . (int) $id . '-' . $size . '.jpg', 300, 300 ); }
	function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) {
		$out = '<img width="300" height="300" src="https://sitio.test/wp-content/uploads/a-' . (int) $id . '-' . $size . '.jpg" srcset="https://sitio.test/wp-content/uploads/a-' . (int) $id . '-150.jpg 150w"';
		foreach ( $attr as $k => $v ) {
			$out .= ' ' . $k . '="' . htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return $out . ' />';
	}
	function sanitize_html_class( $c ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $c ); }
	function get_userdata( $id ) { return $GLOBALS['ab']['users'][ (int) $id ] ?? false; }
	function wp_style_is( $h, $what = 'enqueued' ) { return in_array( $h, $GLOBALS['ab']['styles'], true ); }
	function wp_register_style( $h, ...$a ) { return true; }
	function wp_enqueue_style( $h ) { $GLOBALS['ab']['styles'][] = $h; }
	function plugins_url( $p = '', $f = '' ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . $p; }
	function get_the_ID() { return 5; }
	function get_post_field( $f, $id ) { return 7; }
	function count_user_posts( $id ) { return 4; }
	function wp_count_comments() { return (object) array( 'approved' => 9 ); }
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '0' );

	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/author-box.php' );
	require_once h_path( 'modules/digitalisimo-widgets/class-author-box-extras.php' );
	use Digitalisimo\Elements\Theme_Elements\Widgets\Author_Box;
	\Elementor\Plugin::$instance = (object) array( 'experiments' => new \ElementorPro\H_Experiments() );

	$own = h_widget( Author_Box::class );
	$pro = h_widget( \ElementorPro\Modules\ThemeElements\Widgets\Author_Box::class );
	$map = require h_path( 'modules/digitalisimo-convert/maps/author-box.php' );

	// 1. Recursos.
	$css = h_assets( $own, 'Caja de autor', 'author-box', array( '#3f444b' ) );
	foreach ( array( 'layout-image-left', 'layout-image-right', 'layout-image-above', 'align-left', 'align-center', 'align-right', 'image-valign-middle' ) as $mod ) {
		h_check( str_contains( $css, '.digi-author-box--' . $mod ), "La hoja tiene la presentación $mod." );
	}
	h_check( str_contains( $css, '.digi-author-box a:focus-visible{outline:2px solid;outline-offset:2px}' ), 'Foco visible propio en los enlaces de la caja.' );
	h_check( 'eicon-person' === $own->get_icon() && false === $own->has_widget_inner_wrapper(), 'Icono de PRO; sin envoltorio interior con el marcado optimizado (experimento activo en el entorno).' );

	// 2. Los mismos controles que PRO, más el «Perfil ampliado» integrado.
	$extras = array( '§digitalisimo_author_extras' => true );
	foreach ( array_keys( $own->controls ) as $name ) {
		if ( 0 === strpos( $name, 'digitalisimo_author_' ) ) {
			$extras[ $name ] = true;
		}
	}
	h_check( 8 === count( $extras ) && 'section_author_info' === array_keys( $own->sections )[0] && 'digitalisimo_author_extras' === array_keys( $own->sections )[1], 'El «Perfil ampliado» va justo después de «Datos del autor», como en la caja de PRO.' );
	$diff = h_compare_widgets( $pro, $own, $extras );
	h_check( array() === $diff, "Controles distintos de PRO:\n" . implode( "\n", $diff ) );
	h_check( count( $pro->controls ) + 7 === count( $own->controls ) && count( $pro->groups ) === count( $own->groups ), 'Un control propio por cada control de PRO (' . count( $pro->controls ) . ' y ' . count( $pro->groups ) . ' grupos) y los siete del perfil ampliado.' );
	foreach ( $own->controls as $name => $control ) {
		if ( isset( $control['prefix_class'] ) ) {
			h_check( 0 === strpos( $control['prefix_class'], 'digi-author-box--' ) && str_replace( 'elementor-author-box--', 'digi-author-box--', $pro->controls[ $name ]['prefix_class'] ) === $control['prefix_class'], "$name: clase de presentación propia con el mismo sufijo que PRO." );
		}
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $rule ) {
			h_check( ! str_contains( $selector, 'elementor-' ), "$name: selector con clases del proveedor ($selector)." );
		}
	}

	// 3. Marcado con los mismos datos que PRO.
	$text  = static function ( $html ) { return trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( str_replace( '<', ' <', $html ) ), ENT_QUOTES, 'UTF-8' ) ) ); };
	$hrefs = static function ( $html ) { preg_match_all( '/href="([^"]*)"/', $html, $m ); return $m[1]; };
	$same  = static function ( array $settings, $label ) use ( $own, $pro, $text, $hrefs ) {
		$a = h_render( $pro, $settings );
		$b = h_render( $own, $settings );
		h_check( $text( $a ) === $text( $b ), "$label: mismos textos que PRO.\nPRO: " . $text( $a ) . "\nPropio: " . $text( $b ) );
		h_check( $hrefs( $a ) === $hrefs( $b ), "$label: mismos enlaces que PRO: " . implode( ' ', $hrefs( $a ) ) . ' · ' . implode( ' ', $hrefs( $b ) ) );
		return $b;
	};
	$html = $same( array(), 'Por defecto' );
	h_check( '<div class="digi-author-box"><div class="digi-author-box__avatar"><img src="https://sitio.test/avatar/7?s=300" alt="Imagen de Ana Pérez" width="300" height="300" loading="lazy" decoding="async"></div><div class="digi-author-box__text"><div><h4 class="digi-author-box__name">Ana <b>Pérez</b></h4></div><div class="digi-author-box__bio">Escribe sobre <em>diseño</em>.</div></div></div>' === $html, 'Autor actual por defecto: avatar con el tamaño de PRO, nombre en h4 y biografía: ' . $html );
	$web = $same( array( 'link_to' => 'website', 'show_link' => 'yes', 'author_name_tag' => 'h2', 'button_hover_animation' => 'grow' ), 'Enlace a la web y botón' );
	h_check( 2 === substr_count( $web, '<a href="https://ana.test/" target="_blank" rel="noopener"' ) && str_contains( $web, '<h2 class="digi-author-box__name">' ) && str_contains( $web, '<a class="digi-author-box__button elementor-button elementor-size-xs elementor-animation-grow" href="https://sitio.test/author/7/">All Posts</a>' ), 'Imagen y nombre enlazan a la web en otra pestaña; botón al archivo con las clases de botón del núcleo y la animación: ' . $web );
	$archive = $same( array( 'link_to' => 'posts_archive', 'show_avatar' => '', 'show_biography' => '' ), 'Enlace al archivo' );
	h_check( '<div class="digi-author-box"><div class="digi-author-box__text"><a href="https://sitio.test/author/7/"><h4 class="digi-author-box__name">Ana <b>Pérez</b></h4></a></div></div>' === $archive, 'Sin imagen ni biografía, el nombre enlaza al archivo: ' . $archive );
	h_check( ! str_contains( $same( array( 'show_link' => 'yes', 'link_text' => '' ), 'Botón sin texto' ), '__button' ), 'Sin texto no hay botón, como PRO.' );
	$custom = array( 'source' => 'custom', 'author_name' => 'Marta', 'author_bio' => "Uno\n\nDos", 'author_website' => array( 'url' => 'https://marta.test/', 'is_external' => '' ), 'posts_url' => array( 'url' => 'https://sitio.test/blog/' ), 'author_avatar' => array( 'url' => 'https://sitio.test/m.jpg', 'id' => '' ) );
	$c = $same( $custom, 'Personalizado' );
	h_check( str_contains( $c, '<a href="https://marta.test/" class="digi-author-box__avatar"><img src="https://sitio.test/m.jpg" alt="Imagen de Marta" loading="lazy" decoding="async"></a>' ) && str_contains( $c, '<div class="digi-author-box__bio"><p>Uno</p>' ) && str_contains( $c, 'href="https://sitio.test/blog/">All Posts</a>' ) && ! str_contains( $c, 'target=' ), 'Personalizado: imagen enlazada sin medidas, biografía con párrafos, botón al enlace del archivo y sin otra pestaña: ' . $c );
	$lib = h_render( $own, array( 'author_avatar' => array( 'url' => 'https://sitio.test/x.jpg', 'id' => 33 ) ) + $custom );
	h_check( str_contains( $lib, '<img width="300" height="300" src="https://sitio.test/wp-content/uploads/a-33-medium.jpg" srcset=' ) && str_contains( $lib, 'alt="Imagen de Marta" loading="lazy" decoding="async"' ), 'Una imagen de la biblioteca sale de wp_get_attachment_image() con el tamaño mediano de PRO: ' . $lib );
	$empty = $same( array( 'source' => 'custom', 'author_name' => '', 'author_bio' => '', 'author_avatar' => array( 'url' => '' ), 'posts_url' => array( 'url' => '' ) ), 'Personalizado vacío' );
	h_check( '<div class="digi-author-box"><div class="digi-author-box__text"></div></div>' === $empty, 'Personalizado vacío: la caja sin piezas, como PRO.' );
	$evil = h_render( $own, array( 'source' => 'custom', 'author_name' => '<script>x()</script>Eva', 'author_name_tag' => 'script', 'author_bio' => '<img src=x onerror=alert(1)>Hola', 'link_text' => '<b onclick="y()">Ver</b>', 'posts_url' => array( 'url' => 'javascript:alert(1)' ), 'author_website' => array( 'url' => '"><script>' ) ) + $custom );
	h_check( ! preg_match( '/<script|onerror|onclick|javascript:/i', $evil ) && str_contains( $evil, '<div class="digi-author-box__name">' ), 'Nombre, biografía, botón, enlaces y etiqueta escapados o validados: ' . $evil );
	h_check( "**Ana Pérez**\n\nEscribe sobre diseño.\n\n[All Posts](https://sitio.test/author/7/)" === ( static function () use ( $own ) { $own->settings = h_defaults( $own ); return $own->render_markdown(); } )(), 'Markdown como PRO.' );

	// Perfil ampliado: piezas en sus puntos, hoja sólo cuando se usan y usuario elegido.
	h_check( array() === $GLOBALS['ab']['styles'], 'Sin perfil ampliado no se carga su hoja.' );
	$x = h_render( $own, array( 'digitalisimo_author_badge' => 'Editora', 'digitalisimo_author_show_username' => 'yes', 'digitalisimo_author_username' => 'ana', 'digitalisimo_author_stats' => array( array( 'stat_source' => 'posts', 'stat_label' => 'Entradas' ) ), 'show_link' => 'yes' ) );
	h_check( 0 === strpos( $x, '<div class="digi-author-box digi-author-box--header"><div class="digi-author-box__header"><span class="digi-author-box__badge">Editora</span></div><div class="digi-author-box__avatar">' ) && str_contains( $x, '</h4></div><span class="digi-author-box__username">ana</span><div class="digi-author-box__bio">' ) && str_contains( $x, '</div><ul class="digi-author-box__stats"><li><span class="digi-author-box__stat">4</span> <span class="digi-author-box__stat-label">Entradas</span></li></ul><a class="digi-author-box__button' ) && array( 'digitalisimo-author-box-extras' ) === $GLOBALS['ab']['styles'], 'Perfil ampliado: cabecera al inicio, usuario tras el nombre, cifras antes del botón y su hoja: ' . $x );
	$u = h_render( $own, array( 'source' => 'custom', 'digitalisimo_author_user' => '12', 'posts_url' => array( 'url' => '' ) ) );
	h_check( str_contains( $u, '>Luis Gómez</h4>' ) && str_contains( $u, '<p>Bio de Luis</p>' ) && str_contains( $u, 'src="https://sitio.test/avatar/12?s=300"' ) && str_contains( $u, 'href="https://sitio.test/author/12/">All Posts</a>' ), 'El usuario elegido sustituye nombre, biografía, avatar y enlace del archivo: ' . $u );
	h_check( ! str_contains( h_render( $own, array( 'digitalisimo_author_user' => '12' ) ), 'Luis' ), 'Con «Autor actual» el usuario elegido no cuenta (como el perfil ampliado de PRO).' );

	// 4. Conversión.
	$cases = array(
		'defaults'   => array(),
		'custom'     => array( 'source' => 'custom', 'author_name' => 'Marta', 'author_avatar' => array( 'url' => 'https://sitio.test/m.jpg', 'id' => 9 ), 'author_website' => array( 'url' => 'https://marta.test/', 'is_external' => 'on', 'nofollow' => '' ), 'author_bio' => 'Hola', 'posts_url' => array( 'url' => 'https://sitio.test/blog/' ), 'link_text' => 'Leer más', 'layout' => 'above', 'alignment' => 'center' ),
		'styled'     => array( 'show_link' => 'yes', 'link_to' => 'posts_archive', 'avatar_size' => 120, 'image_vertical_align' => 'middle', 'image_border' => 'yes', 'image_border_color' => '#ff0000', 'image_border_width' => array( 'unit' => 'px', 'size' => 3 ), 'image_border_radius' => array( 'unit' => '%', 'size' => 10 ), 'input_box_shadow_box_shadow_type' => 'yes', 'input_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 6, 'spread' => 0, 'color' => 'rgba(0,0,0,.3)' ), 'name_color' => '#111111', 'name_typography_typography' => 'custom', 'name_typography_font_weight' => '700', 'bio_color' => '#222222', 'bio_typography_typography' => 'custom', 'button_text_color' => '#333333', 'button_background_color' => '#eeeeee', 'button_hover_color' => '#000000', 'button_background_hover_color' => '#dddddd', 'button_hover_transition_duration' => array( 'unit' => 'ms', 'size' => 300 ), 'button_hover_animation' => 'grow', 'button_border_width' => array( 'unit' => 'px', 'size' => 1 ), 'button_border_radius' => array( 'unit' => 'px', 'size' => 4 ), 'button_text_padding' => array( 'top' => '8', 'right' => '16', 'bottom' => '8', 'left' => '16', 'unit' => 'px', 'isLinked' => false ), 'button_typography_typography' => 'custom', 'button_typography_font_size' => array( 'unit' => 'px', 'size' => 14 ) ),
		'responsive' => array( 'image_size' => array( 'unit' => 'px', 'size' => 80 ), 'image_size_tablet' => array( 'unit' => 'px', 'size' => 60 ), 'image_size_mobile' => array( 'unit' => 'px', 'size' => 40 ), 'image_gap_mobile' => array( 'unit' => 'px', 'size' => 10 ), 'name_gap_tablet' => array( 'unit' => 'px', 'size' => 2 ), 'bio_gap_mobile' => array( 'unit' => 'em', 'size' => 1 ), 'image_border_width_tablet' => array( 'unit' => 'px', 'size' => 2 ), 'name_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 18 ) ),
		'globals'    => array( 'name_color' => '', 'bio_color' => '', '__globals__' => array( 'name_color' => 'globals/colors?id=accent', 'bio_color' => 'globals/colors?id=text', 'button_text_color' => 'globals/colors?id=primary', 'name_typography_typography' => 'globals/typography?id=primary', 'bio_typography_typography' => 'globals/typography?id=text', 'button_typography_typography' => 'globals/typography?id=accent', 'image_border_color' => 'globals/colors?id=secondary', '_background_color' => 'globals/colors?id=text' ) ),
		'dynamic'    => array( 'source' => 'custom', '__dynamic__' => array( 'author_name' => '[elementor-tag id="a1" name="author-name" settings="%7B%7D"]', 'author_bio' => '[elementor-tag id="a2" name="author-info" settings="%7B%22key%22%3A%22description%22%7D"]', 'author_avatar' => '[elementor-tag id="a3" name="author-profile-picture" settings="%7B%7D"]', 'posts_url' => '[elementor-tag id="a4" name="author-url" settings="%7B%7D"]', 'link_text' => '[elementor-tag id="a5" name="site-title" settings="%7B%7D"]' ) ),
		'css'        => array( 'custom_css' => "selector .elementor-author-box__name{color:red}\nselector.elementor-author-box--layout-image-above .elementor-author-box__avatar img{border:0}\nselector .elementor-author-box .elementor-author-box__button.elementor-button:hover{color:#000}\n.elementor-widget-author-box .elementor-author-box__bio p{margin:0}", '_css_classes' => 'mi-autor', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
		'extras'     => array( 'source' => 'custom', 'digitalisimo_author_user' => '12', 'digitalisimo_author_badge' => 'Editora', 'digitalisimo_author_stats' => array( array( 'stat_source' => 'posts', 'stat_label' => 'Entradas', '_id' => 's1' ) ) ),
	);
	list( $out, $codes ) = h_convert( 'author-box', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Sólo el CSS convertido se informa: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	h_check( 'John Doe' === $d['author_name'] && 'All Posts' === $d['link_text'] && 0 === strpos( $d['author_bio'], 'Lorem ipsum' ) && 'author-box' === $d['_digitalisimo_pro'], 'Un Author Box sin cambios llega con los textos por defecto que pintaba PRO.' );
	h_check( $text( h_render( $pro, $cases['custom'] ) ) === $text( h_render( $own, $out['custom'] ) ) && $hrefs( h_render( $pro, $cases['custom'] ) ) === $hrefs( h_render( $own, $out['custom'] ) ), 'Lo convertido (personalizado) se pinta con los mismos textos y enlaces que PRO.' );
	h_check( $text( h_render( $pro, $cases['styled'] ) ) === $text( h_render( $own, $out['styled'] ) ) && str_contains( h_render( $own, $out['styled'] ), 'elementor-animation-grow' ), 'Lo convertido (con estilos y botón) se pinta con los mismos textos y la misma animación.' );
	foreach ( $cases['styled'] + $cases['responsive'] as $key => $value ) {
		h_check( ( $out['styled'][ $key ] ?? $out['responsive'][ $key ] ?? null ) === $value, "$key pasa tal cual." );
	}
	h_check( $cases['globals']['__globals__'] === $out['globals']['__globals__'] && $cases['dynamic']['__dynamic__'] === $out['dynamic']['__dynamic__'], 'Globales y dinámicos siguen a su control.' );
	h_check( "selector .digi-author-box__name{color:red}\nselector.digi-author-box--layout-image-above .digi-author-box__avatar img{border:0}\nselector .digi-author-box .digi-author-box__button.elementor-button:hover{color:#000}\n.elementor-widget-digitalisimo-author-box .digi-author-box__bio p{margin:0}" === $out['css']['custom_css'] && 'mi-autor' === $out['css']['_css_classes'], 'CSS personalizado con las clases propias (la del botón del núcleo se conserva); Avanzado intacto: ' . $out['css']['custom_css'] );
	h_check( '12' === $out['extras']['digitalisimo_author_user'] && 'Editora' === $out['extras']['digitalisimo_author_badge'] && 's1' === $out['extras']['digitalisimo_author_stats'][0]['_id'], 'El perfil ampliado pasa sin cambios.' );
	h_check( str_contains( h_render( $own, $out['extras'] ), '>Luis Gómez</h4>' ), 'Y el widget propio lo pinta (usuario elegido).' );
	h_classes_exist( $map, $own, h_render( $own, array( 'show_link' => 'yes' ) ) );

	echo 'DIGITALÍSIMO Elements: Caja de autor (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B) y su mapa desde el Author Box de PRO (' . count( $cases ) . " casos) validados.\n";
}
