<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Separador decorativo con CSS específico y sin runtime JavaScript. */
final class Advanced_Divider_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-advanced-divider'; }
	public function get_title() { return 'Separador avanzado'; }
	public function get_icon() { return 'eicon-divider'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'separador', 'divider', 'línea', 'onda' ); }
	public function get_style_depends() { return array( 'digitalisimo-advanced-divider' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_divider', array( 'label' => 'Separador' ) );
		$this->add_control( 'divider_type', array( 'label' => 'Diseño', 'type' => $c::SELECT, 'default' => 'line', 'options' => array( 'line' => 'Línea', 'dashed' => 'Guiones', 'dotted' => 'Puntos', 'double' => 'Doble línea', 'circle' => 'Círculo central', 'wave' => 'Onda', 'image' => 'Imagen de la biblioteca' ) ) );
		$this->add_control( 'divider_image', array( 'label' => 'Imagen decorativa', 'type' => $c::MEDIA, 'condition' => array( 'divider_type' => 'image' ), 'description' => 'Selecciona una imagen de la biblioteca. Si no está disponible se muestra una línea.' ) );
		$this->add_responsive_control( 'divider_align', array( 'label' => 'Alineación', 'type' => $c::SELECT, 'default' => 'center', 'options' => array( 'left' => 'Izquierda', 'center' => 'Centro', 'right' => 'Derecha' ), 'selectors_dictionary' => array( 'left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => 'margin:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'divider_width', array( 'label' => 'Ancho máximo', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 20, 'max' => 1200 ), '%' => array( 'min' => 1, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => 'max-width:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'divider_gap_top', array( 'label' => 'Espacio superior', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 150 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => 'padding-top:{{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'divider_gap_bottom', array( 'label' => 'Espacio inferior', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 150 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => 'padding-bottom:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_divider_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'divider_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => 'color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'divider_stroke', array( 'label' => 'Grosor', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 1, 'max' => 10 ) ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => '--digi-divider-stroke:{{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'divider_circle_size', array( 'label' => 'Diámetro del círculo', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 6, 'max' => 80 ) ), 'condition' => array( 'divider_type' => 'circle' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => '--digi-divider-circle-size:{{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'divider_circle_gap', array( 'label' => 'Separación del círculo', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'condition' => array( 'divider_type' => 'circle' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider' => '--digi-divider-circle-gap:{{SIZE}}px;' ) ) );
		$this->add_responsive_control( 'divider_wave_height', array( 'label' => 'Altura de onda', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 8, 'max' => 120 ) ), 'condition' => array( 'divider_type' => 'wave' ), 'selectors' => array( '{{WRAPPER}} .digi-advanced-divider__wave' => 'height:{{SIZE}}px;' ) ) );
		$this->end_controls_section();
	}

	private static function safe_type( $value ) {
		return in_array( $value, array( 'line', 'dashed', 'dotted', 'double', 'circle', 'wave', 'image' ), true ) ? $value : 'line';
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$type = self::safe_type( $s['divider_type'] ?? 'line' );
		$image = '';
		if ( 'image' === $type && ! empty( $s['divider_image']['id'] ) && function_exists( 'wp_get_attachment_image' ) ) {
			$image = wp_get_attachment_image( absint( $s['divider_image']['id'] ), 'full', false, array( 'alt' => '', 'aria-hidden' => 'true' ) );
		}
		if ( 'image' === $type && ! $image ) { $type = 'line'; }
		echo '<div class="digi-advanced-divider digi-advanced-divider--' . esc_attr( $type ) . '" role="presentation" aria-hidden="true">';
		if ( 'image' === $type ) {
			echo '<span class="digi-advanced-divider__image">' . $image . '</span>';
		} elseif ( 'circle' === $type ) {
			echo '<span class="digi-advanced-divider__circle"><span></span><i></i><span></span></span>';
		} elseif ( 'wave' === $type ) {
			echo '<svg class="digi-advanced-divider__wave" viewBox="0 0 1000 40" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M0 20 C125 0 125 40 250 20 S375 0 500 20 S625 40 750 20 S875 0 1000 20" /></svg>';
		} else {
			echo '<span class="digi-advanced-divider__line"></span>';
		}
		echo '</div>';
	}

	protected function content_template() {
		?>
		<# var allowed = ['line','dashed','dotted','double','circle','wave','image'];
		var type = _.contains( allowed, settings.divider_type ) ? settings.divider_type : 'line';
		if ( type === 'image' && !( settings.divider_image && Number( settings.divider_image.id ) > 0 && settings.divider_image.url ) ) type = 'line'; #>
		<div class="digi-advanced-divider digi-advanced-divider--{{ type }}" role="presentation" aria-hidden="true">
		<# if ( type === 'image' ) { #><span class="digi-advanced-divider__image"><img src="{{ settings.divider_image.url }}" alt="" /></span>
		<# } else if ( type === 'circle' ) { #><span class="digi-advanced-divider__circle"><span></span><i></i><span></span></span>
		<# } else if ( type === 'wave' ) { #><svg class="digi-advanced-divider__wave" viewBox="0 0 1000 40" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path d="M0 20 C125 0 125 40 250 20 S375 0 500 20 S625 40 750 20 S875 0 1000 20" /></svg>
		<# } else { #><span class="digi-advanced-divider__line"></span><# } #>
		</div>
		<?php
	}
}
