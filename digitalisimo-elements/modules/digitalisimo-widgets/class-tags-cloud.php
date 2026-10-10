<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Nube de términos públicos del sitio actual. */
class Tags_Cloud_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-tags-cloud'; }
	public function get_title() { return 'Nube de etiquetas'; }
	public function get_icon() { return 'eicon-tags'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'etiquetas', 'categorías', 'términos', 'nube' ); }
	public function get_style_depends() { return array( 'digitalisimo-tags-cloud' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$taxonomies[ $taxonomy->name ] = $taxonomy->labels->singular_name ?? $taxonomy->label;
		}
		$this->start_controls_section( 'section_tags', array( 'label' => 'Términos' ) );
		$this->add_control( 'source', array( 'label' => 'Origen', 'type' => $c::SELECT, 'default' => 'taxonomy', 'options' => array( 'taxonomy' => 'Términos del sitio', 'static' => 'Etiquetas escritas aquí' ) ) );
		$this->add_control( 'taxonomy', array( 'label' => 'Taxonomía', 'type' => $c::SELECT, 'default' => isset( $taxonomies['post_tag'] ) ? 'post_tag' : ( key( $taxonomies ) ?: '' ), 'options' => $taxonomies, 'condition' => array( 'source' => 'taxonomy' ) ) );
		$this->add_control( 'limit', array( 'label' => 'Cantidad máxima', 'type' => $c::NUMBER, 'default' => 20, 'min' => 1, 'max' => 50, 'condition' => array( 'source' => 'taxonomy' ) ) );
		$this->add_control( 'orderby', array( 'label' => 'Ordenar por', 'type' => $c::SELECT, 'default' => 'count', 'options' => array( 'count' => 'Popularidad', 'name' => 'Nombre' ), 'condition' => array( 'source' => 'taxonomy' ) ) );
		$this->add_control( 'show_count', array( 'label' => 'Mostrar cantidad', 'type' => $c::SWITCHER, 'default' => '', 'return_value' => 'yes', 'condition' => array( 'source' => 'taxonomy' ) ) );
		$tags = new \Elementor\Repeater();
		$tags->add_control( 'tag_text', array( 'label' => 'Texto', 'type' => $c::TEXT, 'default' => 'Etiqueta' ) );
		$tags->add_control( 'tag_link', array( 'label' => 'Enlace', 'type' => $c::URL ) );
		$tags->add_control( 'tag_weight', array( 'label' => 'Peso', 'type' => $c::NUMBER, 'default' => 1, 'min' => 1, 'max' => 10, 'description' => 'Las etiquetas con más peso se muestran más grandes.' ) );
		$this->add_control( 'static_tags', array( 'label' => 'Etiquetas', 'type' => $c::REPEATER, 'fields' => $tags->get_controls(), 'title_field' => '{{{ tag_text }}}', 'condition' => array( 'source' => 'static' ) ) );
		$this->add_control( 'label', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Explorar etiquetas' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'color', array( 'label' => 'Color de enlaces', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-tags-cloud__link' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Etiquetas escritas en el widget: texto, enlace opcional y peso relativo. */
	private function static_items( $settings ) {
		$items = array();
		foreach ( is_array( $settings['static_tags'] ?? null ) ? $settings['static_tags'] : array() as $index => $tag ) {
			$name = trim( wp_strip_all_tags( (string) ( $tag['tag_text'] ?? '' ) ) );
			if ( '' === $name ) { continue; }
			$link = is_array( $tag['tag_link'] ?? null ) ? $tag['tag_link'] : array();
			$items[] = array( 'name' => $name, 'weight' => max( 1, (int) ( $tag['tag_weight'] ?? 1 ) ), 'link' => $link, 'key' => 'static_tag_' . $index );
		}
		return $items;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$label = trim( wp_strip_all_tags( (string) ( $s['label'] ?? '' ) ) );
		if ( '' === $label ) { $label = 'Explorar etiquetas'; }
		if ( 'static' === ( $s['source'] ?? '' ) ) {
			$items = $this->static_items( $s );
			if ( ! $items ) { return; }
			$weights = wp_list_pluck( $items, 'weight' );
			$minimum = min( $weights );
			$span = max( 1, max( $weights ) - $minimum );
			$out = '';
			foreach ( $items as $item ) {
				$size = number_format( 0.85 + ( ( $item['weight'] - $minimum ) / $span ) * 0.65, 2, '.', '' );
				$out .= '<li class="digi-tags-cloud__item">';
				if ( ! empty( $item['link']['url'] ) ) {
					$this->add_link_attributes( $item['key'], $item['link'] );
					if ( ! empty( $item['link']['is_external'] ) ) { $this->add_render_attribute( $item['key'], 'rel', array( 'noopener', 'noreferrer' ) ); }
					$out .= '<a class="digi-tags-cloud__link" ' . $this->get_render_attribute_string( $item['key'] ) . ' style="--digi-tag-size:' . esc_attr( $size ) . 'em">' . esc_html( $item['name'] ) . '</a>';
				} else {
					$out .= '<span class="digi-tags-cloud__link" style="--digi-tag-size:' . esc_attr( $size ) . 'em">' . esc_html( $item['name'] ) . '</span>';
				}
				$out .= '</li>';
			}
			echo '<nav class="digi-tags-cloud" aria-label="' . esc_attr( $label ) . '"><ul class="digi-tags-cloud__list">' . $out . '</ul></nav>';
			return;
		}
		$taxonomy = sanitize_key( (string) ( $s['taxonomy'] ?? 'post_tag' ) );
		$definition = get_taxonomy( $taxonomy );
		if ( ! $definition || ! $definition->public ) { return; }
		$limit = max( 1, min( 50, absint( $s['limit'] ?? 20 ) ) );
		$orderby = 'name' === ( $s['orderby'] ?? '' ) ? 'name' : 'count';
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => $limit, 'orderby' => $orderby, 'order' => 'name' === $orderby ? 'ASC' : 'DESC' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) || ! $terms ) { return; }
		$counts = array_map( static function ( $term ) { return max( 0, (int) $term->count ); }, $terms );
		$minimum = min( $counts );
		$span = max( 1, max( $counts ) - $minimum );
		$out = '';
		foreach ( $terms as $term ) {
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) || ! is_string( $url ) || '' === $url ) { continue; }
			$name = trim( wp_strip_all_tags( (string) $term->name ) );
			if ( '' === $name ) { continue; }
			$size = number_format( 0.85 + ( ( max( 0, (int) $term->count ) - $minimum ) / $span ) * 0.65, 2, '.', '' );
			$out .= '<li class="digi-tags-cloud__item"><a class="digi-tags-cloud__link" href="' . esc_url( $url ) . '" rel="tag" style="--digi-tag-size:' . esc_attr( $size ) . 'em">' . esc_html( $name );
			if ( 'yes' === ( $s['show_count'] ?? '' ) ) { $out .= ' <span class="digi-tags-cloud__count">(' . (int) $term->count . ')</span>'; }
			$out .= '</a></li>';
		}
		if ( '' !== $out ) { echo '<nav class="digi-tags-cloud" aria-label="' . esc_attr( $label ) . '"><ul class="digi-tags-cloud__list">' . $out . '</ul></nav>'; }
	}

	protected function content_template() {
		?>
		<# if ( settings.source === 'static' ) { var tags = ( settings.static_tags || [] ).filter( function( tag ) { return tag && tag.tag_text; } ); #><# if ( tags.length ) { #><nav class="digi-tags-cloud" aria-label="{{ settings.label || 'Explorar etiquetas' }}"><ul class="digi-tags-cloud__list"><# _.each( tags, function( tag ) { #><li class="digi-tags-cloud__item"><span class="digi-tags-cloud__link">{{ tag.tag_text }}</span></li><# } ); #></ul></nav><# } #><# } else { #><div class="digi-tags-cloud"><p>La nube mostrará los términos publicados de la taxonomía elegida en este sitio.</p></div><# } #>
		<?php
	}
}
