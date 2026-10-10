<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_mt( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return (string) $v; }
	function get_posts( $args ) { return array( (object) array( 'ID' => 1, 'post_title' => 'Entrada uno' ), (object) array( 'ID' => 2, 'post_title' => 'Entrada dos' ) ); }
	function get_the_title( $p ) { return $p->post_title; }
	function get_permalink( $p ) { return 'https://s.test/?p=' . $p->ID; }
	function get_the_date( $f, $p ) { return '1 enero 2026'; }
	function get_the_excerpt( $p ) { return 'Resumen ' . $p->ID; }
	function wp_trim_words( $t, $n ) { return $t; }
	function get_the_post_thumbnail_url( $p, $s ) { return ''; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-marquee.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-timeline.php';
	$render = static function ( $widget ) { ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); return ob_get_clean(); };

	$m = new \Digitalisimo\Elements\Marquee_Widget();
	$m->settings = array( 'source' => 'items', 'label' => 'Últimas', 'duration' => 2, 'direction' => 'right', 'items' => array( array( 'text' => 'Hola <b>x</b>', 'link' => array( 'url' => 'https://s.test/', 'is_external' => 'on' ) ), array( 'text' => '' ) ) );
	$html = $render( $m );
	check_mt( str_contains( $html, 'role="region" aria-label="Últimas"' ) && str_contains( $html, '--digi-marquee-duration:5s' ) && str_contains( $html, 'digi-marquee--right' ) && str_contains( $html, 'aria-hidden="true" inert' ) && str_contains( $html, 'aria-pressed="false"' ) && 2 === substr_count( $html, 'Hola x' ) && str_contains( $html, 'rel="noopener noreferrer"' ), 'La marquesina duplica la lista oculta, limita la duración y tiene pausa.' );
	$m->settings = array( 'source' => 'posts', 'posts_count' => 2 );
	check_mt( str_contains( $render( $m ), 'href="https://s.test/?p=2"><span class="digi-marquee__text">Entrada dos</span>' ), 'La marquesina puede mostrar las últimas entradas.' );

	$t = new \Digitalisimo\Elements\Timeline_Widget();
	$t->settings = array( 'source' => 'items', 'align' => 'left', 'title_tag' => 'h4', 'read_more' => 'Ver', 'items' => array( array( 'date' => '2020', 'title' => 'Inicio', 'text' => 'Texto', 'link' => array( 'url' => 'https://s.test/i' ) ) ) );
	$tl = $render( $t );
	check_mt( str_contains( $tl, '<ol class="digi-timeline digi-timeline--left">' ) && str_contains( $tl, '<h4 class="digi-timeline__title">Inicio</h4>' ) && str_contains( $tl, 'Ver<span class="screen-reader-text">: Inicio</span>' ), 'La línea de tiempo es una lista ordenada con enlaces con nombre propio.' );
	$t->settings = array( 'source' => 'posts', 'posts_count' => 2, 'read_more' => 'Leer' );
	check_mt( str_contains( $render( $t ), '<p class="digi-timeline__date">1 enero 2026</p>' ), 'La línea de tiempo puede mostrar entradas.' );
	echo "DIGITALÍSIMO Elements: marquesina y línea de tiempo validadas.\n";
}
