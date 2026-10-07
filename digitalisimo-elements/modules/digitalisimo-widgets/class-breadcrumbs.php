<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Ruta de navegación del sitio actual. El schema lo publica el motor SEO, si existe. */
final class Breadcrumbs_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-breadcrumbs'; }
	public function get_title() { return 'Ruta de navegación'; }
	public function get_icon() { return 'eicon-breadcrumbs'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'breadcrumbs', 'migas', 'ruta', 'navegación' ); }
	public function get_style_depends() { return array( 'digitalisimo-breadcrumbs' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_breadcrumbs', array( 'label' => 'Contenido' ) );
		$this->add_control( 'home_text', array( 'label' => 'Nombre de inicio', 'type' => $c::TEXT, 'default' => 'Inicio', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'show_home_only', array( 'label' => 'Mostrar también en portada', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'separator', array( 'label' => 'Separador', 'type' => $c::SELECT, 'default' => 'slash', 'options' => array( 'slash' => '/', 'chevron' => '›', 'dot' => '·', 'dash' => '–' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_breadcrumbs_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'align', array( 'label' => 'Alineación', 'type' => $c::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .digi-breadcrumbs__list' => 'justify-content:{{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-breadcrumbs' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'link_color', array( 'label' => 'Color de enlaces', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-breadcrumbs a' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function add( &$crumbs, $label, $url = '' ) {
		$label = trim( wp_strip_all_tags( (string) $label ) );
		if ( '' === $label || is_wp_error( $url ) ) { return; }
		$crumbs[] = array( 'label' => $label, 'url' => (string) $url );
	}

	private static function term_ancestors( &$crumbs, $term ) {
		if ( ! $term || empty( $term->taxonomy ) ) { return; }
		$ids = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		foreach ( $ids as $id ) {
			$parent = get_term( $id, $term->taxonomy );
			if ( $parent && ! is_wp_error( $parent ) ) { self::add( $crumbs, $parent->name, get_term_link( $parent ) ); }
		}
	}

	private static function crumbs( $home_text ) {
		$crumbs = array();
		self::add( $crumbs, $home_text ?: 'Inicio', home_url( '/' ) );
		if ( is_front_page() ) { return $crumbs; }
		if ( is_home() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			self::add( $crumbs, $page_id ? get_the_title( $page_id ) : 'Artículos' );
			return $crumbs;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post || empty( $post->ID ) ) { return $crumbs; }
			$type = get_post_type( $post );
			if ( 'page' === $type ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $parent_id ) { self::add( $crumbs, get_the_title( $parent_id ), get_permalink( $parent_id ) ); }
			} elseif ( 'post' === $type ) {
				$posts_page = (int) get_option( 'page_for_posts' );
				if ( $posts_page ) { self::add( $crumbs, get_the_title( $posts_page ), get_permalink( $posts_page ) ); }
				else {
					$terms = get_the_terms( $post, 'category' );
					if ( is_array( $terms ) && $terms ) { self::term_ancestors( $crumbs, $terms[0] ); self::add( $crumbs, $terms[0]->name, get_term_link( $terms[0] ) ); }
				}
			} else {
				$type_object = get_post_type_object( $type );
				if ( $type_object && $type_object->has_archive ) { self::add( $crumbs, $type_object->labels->name, get_post_type_archive_link( $type ) ); }
			}
			self::add( $crumbs, get_the_title( $post->ID ) );
			return $crumbs;
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			self::term_ancestors( $crumbs, $term );
			self::add( $crumbs, $term->name ?? '' );
		} elseif ( is_post_type_archive() ) {
			self::add( $crumbs, post_type_archive_title( '', false ) );
		} elseif ( is_search() ) {
			self::add( $crumbs, sprintf( 'Resultados de búsqueda: %s', get_search_query( false ) ) );
		} elseif ( is_404() ) {
			self::add( $crumbs, 'Página no encontrada' );
		} elseif ( is_archive() ) {
			self::add( $crumbs, get_the_archive_title() );
		}
		return $crumbs;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$crumbs = self::crumbs( $s['home_text'] ?? 'Inicio' );
		if ( ! $crumbs || ( is_front_page() && 'yes' !== ( $s['show_home_only'] ?? '' ) ) ) { return; }
		$separators = array( 'slash' => '/', 'chevron' => '›', 'dot' => '·', 'dash' => '–' );
		$separator = $separators[ $s['separator'] ?? 'slash' ] ?? '/';
		echo '<nav class="digi-breadcrumbs" aria-label="Ruta de navegación"><ol class="digi-breadcrumbs__list">';
		foreach ( $crumbs as $index => $crumb ) {
			$last = count( $crumbs ) - 1 === $index;
			echo '<li class="digi-breadcrumbs__item">';
			if ( $index ) { echo '<span class="digi-breadcrumbs__separator" aria-hidden="true">' . esc_html( $separator ) . '</span>'; }
			if ( ! $last && $crumb['url'] ) { echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>'; }
			else { echo '<span' . ( $last ? ' aria-current="page"' : '' ) . '>' . esc_html( $crumb['label'] ) . '</span>'; }
			echo '</li>';
		}
		echo '</ol></nav>';
	}

	protected function content_template() {
		?>
		<nav class="digi-breadcrumbs" aria-label="Ruta de navegación"><ol class="digi-breadcrumbs__list"><li class="digi-breadcrumbs__item"><span>{{ settings.home_text || 'Inicio' }}</span></li><li class="digi-breadcrumbs__item"><span class="digi-breadcrumbs__separator" aria-hidden="true">/</span><span aria-current="page">Vista previa</span></li></ol></nav>
		<?php
	}
}
