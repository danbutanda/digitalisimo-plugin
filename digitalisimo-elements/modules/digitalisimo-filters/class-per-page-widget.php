<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Resultados por página (`digitalisimo-loop-per-page`, F7b V.5): `posts_per_page` de la lista del widget, de 1 a 100.
 * En la URL viaja el número (`digi_pp_{widget}=24`) y el servidor sólo aplica uno de la lista. Desplegable o botones.
 */
class Per_Page_Widget extends Filter_Widget {
	const TYPE = Query::PER_PAGE;

	public function get_name() {
		return 'digitalisimo-loop-per-page';
	}

	public function get_title() {
		return 'Resultados por página';
	}

	public function get_icon() {
		return 'eicon-number-field';
	}

	public function get_keywords() {
		return array( 'por página', 'paginación', 'cantidad', 'filtro', 'loop grid', 'digitalisimo' );
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_filter', array( 'label' => 'Por página' ) );
		$this->register_target_controls( 'Archivo (la consulta principal)', 'Cambia cuántos resultados muestra la consulta principal del archivo que muestra este widget. La dirección lleva la referencia de este widget; sin ella, el cambio no se aplica.' );
		$this->text_control( 'values', 'Cantidades', '12, 24, 48', array( 'separator' => 'before', 'description' => 'Números de 1 a 100 separados por comas (como mucho 10). Sólo se aplican éstos.' ) );
		$this->add_control( 'display', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'dropdown', 'options' => array( 'dropdown' => 'Desplegable', 'buttons' => 'Botones' ), 'style_transfer' => false ) );
		$this->text_control( 'default_text', 'Texto de la cantidad predeterminada', 'Predeterminado', array( 'condition' => array( 'display' => 'dropdown' ) ) );
		$this->register_text_controls( 'Por página', 'Aplicar', 'Restablecer' );
		$this->end_controls_section();
		$this->register_style_controls();
	}

	protected function render() {
		if ( ! $this->ready() ) {
			return;
		}
		$s = $this->filter_settings();
		if ( ! $s['values'] ) {
			$this->editor_message( 'Escribe las cantidades que se pueden elegir.' );
			return;
		}
		$this->require_pro();
		$options = array();
		foreach ( $s['values'] as $value ) {
			$options[ (string) $value ] = (string) $value;
		}
		$label   = $this->setting_text( 'legend', 'Por página' );
		$display = 'buttons' === $this->get_settings_for_display( 'display' ) ? 'buttons' : 'dropdown';
		$this->render_choices(
			array(
				'kind'        => 'per-page',
				'label'       => $label,
				'form_label'  => $label,
				'options'     => $options,
				'multiple'    => false,
				'display'     => $display,
				'any'         => $this->setting_text( 'default_text', 'Predeterminado' ),
				'button'      => $this->setting_text( 'button_text', 'Aplicar' ),
				'clear'       => $this->setting_text( 'clear_text', 'Restablecer' ),
				'clear_label' => 'Volver a la cantidad predeterminada',
			)
		);
	}
}
