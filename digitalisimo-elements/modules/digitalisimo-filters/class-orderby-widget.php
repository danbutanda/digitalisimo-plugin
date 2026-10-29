<?php
namespace Digitalisimo\Elements\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Orden de un Loop Grid o de un archivo (`digitalisimo-loop-orderby`, F7b V.5).
 *
 * El administrador elige las opciones de una lista cerrada (`Query::ORDERS`: fecha, título, modificación, precio con
 * WooCommerce en bucles de productos y su campo numérico); nunca `rand`. En la URL viaja la clave de la opción
 * (`digi_o_{widget}=title_asc`) y el servidor sólo aplica una de la lista de este widget. Desplegable o botones.
 */
class Orderby_Widget extends Filter_Widget {
	const TYPE = Query::ORDERBY;

	public function get_name() {
		return 'digitalisimo-loop-orderby';
	}

	public function get_title() {
		return 'Orden del Loop Grid';
	}

	public function get_icon() {
		return 'eicon-sort-amount-desc';
	}

	public function get_keywords() {
		return array( 'orden', 'ordenar', 'filtro', 'loop grid', 'digitalisimo' );
	}

	protected function register_controls() {
		$c       = '\\Elementor\\Controls_Manager';
		$choices = array();
		foreach ( Query::ORDERS as $key => $order ) {
			$choices[ $key ] = $order[2];
		}
		$this->start_controls_section( 'section_filter', array( 'label' => 'Orden' ) );
		$this->register_target_controls( 'Archivo (la consulta principal)', 'Ordena la consulta principal del archivo que muestra este widget. La dirección lleva la referencia de este widget; sin ella, el orden no se aplica.' );
		$this->add_control(
			'orders',
			array(
				'label'          => 'Opciones',
				'type'           => $c::REPEATER,
				'separator'      => 'before',
				'fields'         => array(
					array( 'name' => 'order_key', 'label' => 'Orden', 'type' => $c::SELECT, 'default' => 'date_desc', 'options' => $choices ),
					array( 'name' => 'label', 'label' => 'Texto visible', 'type' => $c::TEXT, 'default' => '', 'label_block' => true ),
				),
				'default'        => Query::default_orders(),
				'title_field'    => '{{{ label || order_key }}}',
				'description'    => 'El precio sólo con WooCommerce y en bucles de productos; el campo, con una clave elegida abajo. Nunca un orden aleatorio.',
				'style_transfer' => false,
			)
		);
		Meta_Widget::key_controls( $this, 'order_meta_key', 'Campo numérico para ordenar' );
		$this->add_control( 'display', array( 'label' => 'Presentación', 'type' => $c::SELECT, 'default' => 'dropdown', 'options' => array( 'dropdown' => 'Desplegable', 'buttons' => 'Botones' ), 'style_transfer' => false ) );
		$this->text_control( 'default_text', 'Texto del orden predeterminado', 'Orden predeterminado', array( 'condition' => array( 'display' => 'dropdown' ) ) );
		$this->register_text_controls( 'Ordenar por', 'Aplicar', 'Restablecer' );
		$this->end_controls_section();
		$this->register_style_controls();
	}

	protected function render() {
		if ( ! $this->ready() ) {
			return;
		}
		$s       = $this->filter_settings();
		$options = $s['orders'];
		if ( ! class_exists( 'WooCommerce', false ) ) {
			unset( $options['price_asc'], $options['price_desc'] );
		}
		if ( ! $options ) {
			$this->editor_message( 'Añade las opciones de orden.' );
			return;
		}
		$this->require_pro();
		$label   = $this->setting_text( 'legend', 'Ordenar por' );
		$display = 'buttons' === $this->get_settings_for_display( 'display' ) ? 'buttons' : 'dropdown';
		$this->render_choices(
			array(
				'kind'        => 'orderby',
				'label'       => $label,
				'form_label'  => $label,
				'options'     => $options,
				'multiple'    => false,
				'display'     => $display,
				'any'         => $this->setting_text( 'default_text', 'Orden predeterminado' ),
				'button'      => $this->setting_text( 'button_text', 'Aplicar' ),
				'clear'       => $this->setting_text( 'clear_text', 'Restablecer' ),
				'clear_label' => 'Volver al orden predeterminado',
			)
		);
	}
}
