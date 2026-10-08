<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Comparación de características mediante tabla HTML nativa. */
final class Comparison_List_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-comparison-list'; }
	public function get_title() { return 'Lista comparativa'; }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'comparativa', 'planes', 'características', 'tabla' ); }
	public function get_style_depends() { return array( 'digitalisimo-comparison-list' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_plans', array( 'label' => 'Columnas' ) );
		$this->add_control( 'comparison_list_title', array( 'label' => 'Título de características', 'type' => $c::TEXT, 'default' => 'Características' ) );
		$plans = new \Elementor\Repeater();
		$plans->add_control( 'header_title', array( 'label' => 'Plan', 'type' => $c::TEXT, 'default' => 'Plan' ) );
		$plans->add_control( 'header_sub_title', array( 'label' => 'Descripción breve', 'type' => $c::TEXTAREA ) );
		$plans->add_control( 'header_button_text', array( 'label' => 'Texto del enlace', 'type' => $c::TEXT ) );
		$plans->add_control( 'header_link', array( 'label' => 'Enlace', 'type' => $c::URL ) );
		$plans->add_control( 'header_active', array( 'label' => 'Destacar columna', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'comparison_header_list', array( 'label' => 'Planes', 'type' => $c::REPEATER, 'fields' => $plans->get_controls(), 'default' => array( array( 'header_title' => 'Básico' ), array( 'header_title' => 'Avanzado', 'header_active' => 'yes' ) ), 'title_field' => '{{{ header_title }}}' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_features', array( 'label' => 'Características' ) );
		$features = new \Elementor\Repeater();
		$features->add_control( 'title', array( 'label' => 'Característica', 'type' => $c::TEXT, 'default' => 'Característica' ) );
		$features->add_control( 'description', array( 'label' => 'Detalle opcional', 'type' => $c::TEXTAREA ) );
		$features->add_control( 'feature_ability', array( 'label' => 'Valores por plan', 'type' => $c::TEXT, 'default' => '1|1', 'description' => 'Separa cada valor con |. Usa 1 para incluido, 0 para no incluido, o texto concreto.' ) );
		$this->add_control( 'comparison_list', array( 'label' => 'Filas', 'type' => $c::REPEATER, 'fields' => $features->get_controls(), 'default' => array( array( 'title' => 'Función principal', 'feature_ability' => '1|1' ), array( 'title' => 'Soporte prioritario', 'feature_ability' => '0|1' ) ), 'title_field' => '{{{ title }}}' ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_comparison_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'highlight_color', array( 'label' => 'Fondo de columna destacada', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-comparison-list__featured' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$plans = array_values( array_filter( is_array( $s['comparison_header_list'] ?? null ) ? $s['comparison_header_list'] : array(), function ( $plan ) { return is_array( $plan ) && '' !== trim( wp_strip_all_tags( (string) ( $plan['header_title'] ?? '' ) ) ); } ) );
		$rows = is_array( $s['comparison_list'] ?? null ) ? $s['comparison_list'] : array();
		if ( ! $plans || ! $rows ) { return; }
		$plans = array_slice( $plans, 0, 8 );
		echo '<div class="digi-comparison-list" role="region" aria-label="Comparación de planes" tabindex="0"><table><thead><tr><th scope="col">' . esc_html( $s['comparison_list_title'] ?? 'Características' ) . '</th>';
		foreach ( $plans as $index => $plan ) {
			$featured = 'yes' === ( $plan['header_active'] ?? '' ) ? ' digi-comparison-list__featured' : '';
			echo '<th scope="col" class="digi-comparison-list__plan' . $featured . '"><span>' . esc_html( $plan['header_title'] ) . '</span>';
			if ( ! empty( $plan['header_sub_title'] ) ) { echo '<small>' . esc_html( wp_strip_all_tags( $plan['header_sub_title'] ) ) . '</small>'; }
			$link = is_array( $plan['header_link'] ?? null ) ? $plan['header_link'] : array();
			if ( ! empty( $link['url'] ) && ! empty( $plan['header_button_text'] ) ) {
				$key = 'comparison_link_' . $index;
				$this->add_link_attributes( $key, $link );
				echo '<a ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $plan['header_button_text'] ) . '</a>';
			}
			echo '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['title'] ) ) { continue; }
			$values = explode( '|', (string) ( $row['feature_ability'] ?? '' ) );
			echo '<tr><th scope="row">' . esc_html( wp_strip_all_tags( $row['title'] ) );
			if ( ! empty( $row['description'] ) ) { echo '<small>' . esc_html( wp_strip_all_tags( $row['description'] ) ) . '</small>'; }
			echo '</th>';
			foreach ( $plans as $index => $plan ) {
				$value = trim( wp_strip_all_tags( (string) ( $values[ $index ] ?? '' ) ) );
				$featured = 'yes' === ( $plan['header_active'] ?? '' ) ? ' class="digi-comparison-list__featured"' : '';
				echo '<td' . $featured . '>';
				if ( '1' === $value ) { echo '<span aria-label="Incluido">✓</span>'; }
				elseif ( '0' === $value ) { echo '<span aria-label="No incluido">—</span>'; }
				else { echo esc_html( $value ?: '—' ); }
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	protected function content_template() {
		?>
		<# var plans = settings.comparison_header_list || []; var rows = settings.comparison_list || []; #>
		<# if ( plans.length && rows.length ) { #>
		<div class="digi-comparison-list" role="region" aria-label="Comparación de planes" tabindex="0"><table><thead><tr><th scope="col">{{ settings.comparison_list_title || 'Características' }}</th>
		<# _.each( plans, function(plan){ #><th scope="col" class="digi-comparison-list__plan <# if ( plan.header_active === 'yes' ) { #>digi-comparison-list__featured<# } #>">{{ plan.header_title }}<# if ( plan.header_sub_title ) { #><small>{{ plan.header_sub_title }}</small><# } #></th><# }); #>
		</tr></thead><tbody><# _.each( rows, function(row){ var values = (row.feature_ability || '').split('|'); #><tr><th scope="row">{{ row.title }}<# if ( row.description ) { #><small>{{ row.description }}</small><# } #></th><# _.each( plans, function(plan,index){ #><td <# if ( plan.header_active === 'yes' ) { #>class="digi-comparison-list__featured"<# } #>>{{ values[index] === '1' ? '✓' : (values[index] === '0' ? '—' : (values[index] || '—')) }}</td><# }); #></tr><# }); #></tbody></table></div><# } #>
		<?php
	}
}
