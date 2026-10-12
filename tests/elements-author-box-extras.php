<?php
/**
 * Perfil ampliado de la Caja de autor: piezas (distintivo, menú, nombre de usuario, cifras y redes) y su
 * inserción en el HTML real del widget, con usuario elegido o datos escritos a mano. También el texto
 * bajo Lottie (descripción y botón) y la leyenda de Hotspot.
 */
namespace Elementor {
	class Icons_Manager { public static function render_icon( $icon, $attrs ) { echo '<i aria-hidden="true"></i>'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_extras( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $v ) { return 0 === strpos( (string) $v, 'javascript:' ) ? '' : htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function absint( $v ) { return abs( (int) $v ); }
	function get_userdata( $id ) { return 7 === $id ? (object) array( 'ID' => 7, 'user_nicename' => 'ana-lopez', 'first_name' => 'Ana', 'last_name' => 'López', 'display_name' => 'ana', 'description' => 'Bio' ) : false; }
	function count_user_posts( $id ) { return 7 === $id ? 12 : 0; }
	function wp_count_comments() { return (object) array( 'approved' => 34 ); }
	function get_the_ID() { return 5; }
	function get_post_field( $field, $id ) { return 'post_author' === $field && 5 === $id ? 7 : ''; }
	function wp_kses_post( $v ) { return preg_replace( '#<script.*?</script>#s', '', (string) $v ); }
	function wpautop( $v ) { return '<p>' . $v . '</p>'; }

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-author-box-extras.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-media-text-extras.php';
	use Digitalisimo\Elements\Author_Box_Extras;
	use Digitalisimo\Elements\Media_Text_Extras;

	// HTML del widget author-box tal como lo pinta (con nombre enlazado, biografía y botón).
	$box = "\t\t<div class=\"elementor-author-box\">\n\t\t\t<a href=\"https://s.test/a\" class=\"elementor-author-box__avatar\"><img src=\"a.png\" alt=\"Ana\"></a>\n\t\t\t<div class=\"elementor-author-box__text\">\n\t\t\t\t<a href=\"https://s.test/a\">\n\t\t\t\t\t<h4 class=\"elementor-author-box__name\">\n\t\t\t\t\t\tAna\t\t\t\t\t</h4>\n\t\t\t\t</a>\n\t\t\t\t<div class=\"elementor-author-box__bio\">Bio</div>\n\t\t\t\t<a class=\"elementor-author-box__button elementor-button elementor-size-xs\" href=\"https://s.test/a\">Seguir</a>\n\t\t\t</div>\n\t\t</div>\n\t\t";

	check_extras( array( 'header' => '', 'username' => '', 'body' => '' ) === Author_Box_Extras::parts( array() ), 'Sin opciones no hay piezas y la caja queda igual.' );

	$s = array(
		'source' => 'custom', 'digitalisimo_author_badge' => 'Pro <b>', 'digitalisimo_author_show_username' => 'yes', 'digitalisimo_author_username' => '@ana',
		'digitalisimo_author_stats' => array( array( 'stat_source' => 'custom', 'stat_value' => '423', 'stat_label' => 'Seguidores' ), array( 'stat_source' => 'comments', 'stat_label' => 'Comentarios' ) ),
		'digitalisimo_author_social' => array( array( 'social_icon' => array( 'value' => 'fab fa-x' ), 'social_label' => 'X', 'social_link' => array( 'url' => 'https://x.test/ana', 'is_external' => 'on' ) ), array( 'social_label' => 'Sin enlace', 'social_link' => array( 'url' => '' ) ) ),
		'digitalisimo_author_menu' => array( array( 'menu_label' => 'Ajustes', 'menu_link' => array( 'url' => 'https://s.test/ajustes' ) ), array( 'menu_label' => 'Ayuda', 'menu_link' => array( 'url' => '' ) ) ),
	);
	$parts = Author_Box_Extras::parts( $s );
	check_extras( '<div class="digi-author-box__header"><span class="digi-author-box__badge">Pro</span><details class="digi-author-box__menu"><summary aria-label="Más opciones"></summary><ul><li><a class="digi-author-box__menu-link" href="https://s.test/ajustes">Ajustes</a></li><li><span class="digi-author-box__menu-link">Ayuda</span></li></ul></details></div>' === $parts['header'], 'La cabecera lleva el distintivo y el menú, con texto sin enlace cuando falta la URL.' );
	check_extras( '<span class="digi-author-box__username">@ana</span>' === $parts['username'], 'El nombre de usuario escrito a mano se muestra tal cual.' );
	check_extras( str_contains( $parts['body'], '<li><span class="digi-author-box__stat">423</span> <span class="digi-author-box__stat-label">Seguidores</span></li><li><span class="digi-author-box__stat">34</span>' ) && str_contains( $parts['body'], 'href="https://x.test/ana" target="_blank" rel="noopener noreferrer" aria-label="X"><i aria-hidden="true"></i></a>' ) && ! str_contains( $parts['body'], 'Sin enlace' ), 'Cifras y redes: comentarios del sitio, nombre accesible y perfiles sin enlace omitidos.' );

	$html = Author_Box_Extras::insert( $box, $parts );
	check_extras( str_contains( $html, '<div class="elementor-author-box digi-author-box--header"><div class="digi-author-box__header">' ), 'La cabecera abre la caja.' );
	check_extras( (bool) preg_match( '#</h4>\s*</a><span class="digi-author-box__username">@ana</span>#', $html ), 'El nombre de usuario sigue al nombre enlazado.' );
	check_extras( (bool) preg_match( '#<ul class="digi-author-box__social">.*</ul><a class="elementor-author-box__button#s', $html ) && str_ends_with( rtrim( $html ), '</div>' ), 'Cifras y redes van antes del botón y la caja sigue cerrada.' );

	$no_button = Author_Box_Extras::insert( str_replace( "<a class=\"elementor-author-box__button elementor-button elementor-size-xs\" href=\"https://s.test/a\">Seguir</a>\n", '', $box ), array( 'header' => '', 'username' => '', 'body' => '<ul class="x">$1</ul>' ) );
	check_extras( (bool) preg_match( '#<ul class="x">\$1</ul></div>\s*</div>\s*$#', $no_button ), 'Sin botón el cuerpo cierra el texto, y un «$1» se copia literal.' );

	$user = Author_Box_Extras::parts( array( 'source' => 'custom', 'digitalisimo_author_user' => '7', 'digitalisimo_author_show_username' => 'yes', 'digitalisimo_author_stats' => array( array( 'stat_source' => 'posts', 'stat_label' => 'Entradas' ) ) ) );
	check_extras( '<span class="digi-author-box__username">ana-lopez</span>' === $user['username'] && str_contains( $user['body'], '<span class="digi-author-box__stat">12</span> <span class="digi-author-box__stat-label">Entradas</span>' ), 'Con un usuario elegido se usan su nombre de usuario y sus entradas.' );
	$current = Author_Box_Extras::parts( array( 'source' => 'current', 'digitalisimo_author_user' => '9', 'digitalisimo_author_stats' => array( array( 'stat_source' => 'posts', 'stat_label' => 'Entradas' ) ) ) );
	check_extras( str_contains( $current['body'], '<span class="digi-author-box__stat">12</span>' ), 'Con el autor actual se cuentan las entradas del autor de la entrada.' );
	check_extras( '<p class="digi-media-caption">Plano &lt;b&gt;</p>' !== Media_Text_Extras::extra( 'hotspot', array( 'digitalisimo_hotspot_caption' => 'Plano <b>' ) ) && '<p class="digi-media-caption">Plano</p>' === Media_Text_Extras::extra( 'hotspot', array( 'digitalisimo_hotspot_caption' => 'Plano <b>' ) ) && '' === Media_Text_Extras::extra( 'hotspot', array() ), 'La leyenda de Hotspot es texto plano y sólo aparece si existe.' );
	$lottie = Media_Text_Extras::extra( 'lottie', array( 'digitalisimo_lottie_description' => 'Texto<script>x</script>', 'digitalisimo_lottie_button_text' => 'Leer', 'digitalisimo_lottie_button_link' => array( 'url' => 'https://s.test/', 'is_external' => 'on' ) ) );
	check_extras( '<div class="digi-media-description"><p>Texto</p></div><a class="digi-media-button elementor-button" href="https://s.test/" target="_blank" rel="noopener noreferrer">Leer</a>' === $lottie, 'Lottie añade descripción saneada y botón con enlace seguro.' );
	check_extras( '' === Media_Text_Extras::extra( 'lottie', array( 'digitalisimo_lottie_button_text' => 'Leer' ) ) && '' === Media_Text_Extras::extra( 'image', array( 'digitalisimo_hotspot_caption' => 'x' ) ), 'Sin enlace no hay botón y otros widgets no cambian.' );
	check_extras( str_ends_with( Media_Text_Extras::print_template( '<div></div>', new class() { public function get_name() { return 'hotspot'; } } ), '{{ settings.digitalisimo_hotspot_caption }}</p><# } #>' ), 'La vista previa del editor incluye la leyenda.' );
	echo "DIGITALÍSIMO Elements: perfil ampliado de la Caja de autor y textos de Lottie y Hotspot validados.\n";
}
