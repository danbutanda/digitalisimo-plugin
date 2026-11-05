<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Contadores públicos del sitio actual, sin animación ni recursos JavaScript. */
class Total_Count_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-total-count'; }
	public function get_title() { return 'Contador total'; }
	public function get_icon() { return 'eicon-counter'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'contador', 'total', 'entradas', 'comentarios', 'usuarios' ); }
	public function get_style_depends() { return array( 'digitalisimo-total-count' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$types[ $type->name ] = $type->labels->name ?? $type->label;
		}
		$this->start_controls_section( 'section_count', array( 'label' => 'Contador' ) );
		$this->add_control( 'source', array( 'label' => 'Contar', 'type' => $c::SELECT, 'default' => 'post', 'options' => array( 'post' => 'Contenido publicado', 'comment' => 'Comentarios aprobados', 'user' => 'Usuarios de este sitio' ) ) );
		$this->add_control( 'post_type', array( 'label' => 'Tipo de contenido', 'type' => $c::SELECT, 'default' => 'post', 'options' => $types, 'condition' => array( 'source' => 'post' ) ) );
		$this->add_control( 'label', array( 'label' => 'Etiqueta visible', 'type' => $c::TEXT, 'default' => 'Publicaciones', 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'extra_count', array( 'label' => 'Sumar a la cifra', 'type' => $c::NUMBER, 'default' => 0, 'min' => 0, 'description' => 'Cantidad que se añade al conteo real, por ejemplo clientes atendidos fuera del sitio.' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'number_color', array( 'label' => 'Color del número', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-total-count__number' => 'color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	private static function count( $settings ) {
		$source = (string) ( $settings['source'] ?? 'post' );
		if ( 'comment' === $source ) {
			$counts = wp_count_comments();
			return max( 0, (int) ( $counts->approved ?? 0 ) );
		}
		if ( 'user' === $source ) {
			$blog_id = get_current_blog_id();
			$key = 'digi_total_count_users_' . $blog_id;
			$cached = get_transient( $key );
			if ( false !== $cached ) { return max( 0, (int) $cached ); }
			$counts = count_users( 'time', $blog_id );
			$total = max( 0, (int) ( $counts['total_users'] ?? 0 ) );
			set_transient( $key, $total, 5 * MINUTE_IN_SECONDS );
			return $total;
		}
		$type = sanitize_key( (string) ( $settings['post_type'] ?? 'post' ) );
		$definition = get_post_type_object( $type );
		if ( ! $definition || ! $definition->public ) { return 0; }
		$counts = wp_count_posts( $type, 'readable' );
		return max( 0, (int) ( $counts->publish ?? 0 ) );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$label = trim( wp_strip_all_tags( (string) ( $settings['label'] ?? '' ) ) );
		if ( '' === $label ) { $label = 'Total'; }
		$total = self::count( $settings ) + max( 0, (int) ( $settings['extra_count'] ?? 0 ) );
		echo '<div class="digi-total-count"><span class="digi-total-count__number">' . esc_html( number_format_i18n( $total ) ) . '</span><span class="digi-total-count__label">' . esc_html( $label ) . '</span></div>';
	}

	protected function content_template() {
		?><div class="digi-total-count"><span class="digi-total-count__number">—</span><span class="digi-total-count__label">{{ settings.label || 'Total' }}</span></div><?php
	}
}
