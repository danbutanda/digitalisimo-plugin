<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Cita: `<blockquote>` con el autor en `<footer>`/`<cite>` y un botón opcional que abre la intención de publicación
 * del servicio de microblogs con la cita, el autor y el enlace. Sustituye al widget Blockquote de PRO
 * (`modules/blockquote/`, mapa `modules/digitalisimo-convert/maps/blockquote.php`): tiene un control por cada control
 * de aquél, con los mismos valores por defecto visibles, y su hoja reproduce el mismo diseño (pieles borde, comillas,
 * caja y limpia; botón clásico, burbuja o enlace). Sin JavaScript; el icono es un SVG en línea.
 *
 * Lo convertido desde PRO se ve como en PRO:
 * - Icono: el pájaro de Font Awesome que pintaba el widget de PRO. Lo decide `share_icon` (oculto, lo fija el mapa) o,
 *   sin él, la marca `_digitalisimo_pro` de lo convertido antes de existir. Queda en el mismo sitio: en la vista
 *   «Icono», arriba a la izquierda del botón cuando el sitio pinta los iconos como SVG en línea, como PRO; centrado con
 *   los iconos de fuente y en el editor, donde PRO usaba la fuente.
 * - Alineación: PRO pintaba a la vez, en todos los anchos, la clase de la alineación de cada dispositivo, y se veía lo
 *   que resultaba de la cascada. El mapa calcula ese resultado y lo fija en «Alineación heredada» (`pro_align`), que
 *   sólo aparece en lo convertido. Lo convertido antes de existir conserva la alineación por dispositivo.
 * Una Cita nueva usa su icono propio y la alineación de cada dispositivo sólo en él.
 *
 * Diferencias declaradas con PRO (`docs/pro/c0.md`):
 * - En una Cita nueva, el icono de la vista «Icono» queda centrado también con iconos SVG.
 * - El enlace de la página actual se construye con el origen del sitio más la ruta pedida (PRO duplicaba la subcarpeta),
 *   y los textos del enlace para compartir van codificados por completo.
 * - En RTL no se reproducen los dos defectos del pico de la burbuja de PRO.
 */
class Blockquote_Widget extends \Elementor\Widget_Base {
	/** Intención de publicación del servicio (la misma que usaba PRO). */
	const SHARE_URL = 'https://twitter.com/intent/tweet';

	/** Icono propio (24×24), dibujado para este widget. */
	const ICON = '<svg class="digi-blockquote__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h5.6L21 21h-5.6zM19.4 3h2.1l-7.44 7.93-.89-1.3zM9.94 13.07l.89 1.3L4.6 21H2.5z"/></svg>';

	/**
	 * Icono de lo convertido desde PRO: el pájaro (`twitter`, 512×512) de Font Awesome Free 5 (iconos CC BY 4.0), el mismo
	 * trazo que PRO pinta, tomado de la copia del núcleo (`elementor/assets/lib/font-awesome/json/brands.json`).
	 */
	const ICON_LEGACY = '<svg class="digi-blockquote__icon" viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z"/></svg>';

	/** Paquetes de variables de cada alineación (también los de la alineación heredada de PRO). */
	const ALIGN = array(
		'left'         => 'text-align:inherit;--digi-bq-ta:left;--digi-bq-fd:row;--digi-bq-as:flex-end;--digi-bq-s:1',
		'center'       => 'text-align:center;--digi-bq-ta:center;--digi-bq-fd:column;--digi-bq-as:center;--digi-bq-s:initial',
		'right'        => 'text-align:inherit;--digi-bq-ta:right;--digi-bq-fd:row-reverse;--digi-bq-as:flex-end;--digi-bq-s:-1',
		'center-left'  => 'text-align:center;--digi-bq-ta:left;--digi-bq-fd:column;--digi-bq-as:center;--digi-bq-s:1',
		'center-right' => 'text-align:center;--digi-bq-ta:right;--digi-bq-fd:column;--digi-bq-as:center;--digi-bq-s:-1',
	);

	const VIEWS = array( 'icon-text', 'icon', 'text' );

	public function get_name() { return 'digitalisimo-blockquote'; }
	public function get_title() { return 'Cita'; }
	public function get_icon() { return 'eicon-blockquote'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'cita', 'blockquote', 'quote', 'testimonio', 'compartir', 'texto' ); }
	public function get_style_depends() { return array( 'digitalisimo-blockquote' ); }
	public function get_script_depends() { return array(); }

	protected function register_controls() {
		$c     = '\\Elementor\\Controls_Manager';
		$units = array( 'px', '%', 'em', 'rem', 'custom' );
		$time  = array( 'label' => 'Duración de la transición', 'type' => $c::SLIDER, 'size_units' => array( 's', 'ms', 'custom' ), 'default' => array( 'unit' => 'ms' ) );

		$this->start_controls_section( 'section_blockquote_content', array( 'label' => 'Cita' ) );
		$this->add_control( 'blockquote_skin', array( 'label' => 'Piel', 'type' => $c::SELECT, 'options' => array( 'border' => 'Borde', 'quotation' => 'Comillas', 'boxed' => 'Caja', 'clean' => 'Limpia' ), 'default' => 'border', 'prefix_class' => 'digi-blockquote--skin-', 'separator' => 'after' ) );
		$this->add_control( 'blockquote_content', array( 'label' => 'Contenido', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ), 'default' => 'Escribe aquí la cita que quieres destacar: una idea, un testimonio o una frase que merezca leerse dos veces.', 'placeholder' => 'Escribe la cita', 'rows' => 10 ) );
		$this->add_control( 'author_name', array( 'label' => 'Autor', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'ai' => array( 'active' => false ), 'default' => 'Nombre del autor', 'separator' => 'after' ) );
		$this->add_control( 'share_button', array( 'label' => 'Botón para compartir', 'type' => $c::SWITCHER, 'label_on' => 'Sí', 'label_off' => 'No', 'default' => 'yes' ) );
		$this->add_control( 'share_view', array( 'label' => 'Vista', 'type' => $c::SELECT, 'options' => array( 'icon-text' => 'Icono y texto', 'icon' => 'Icono', 'text' => 'Texto' ), 'prefix_class' => 'digi-blockquote--view-', 'default' => 'icon-text', 'render_type' => 'template', 'condition' => array( 'share_button' => 'yes' ) ) );
		$this->add_control( 'share_skin', array( 'label' => 'Piel del botón', 'type' => $c::SELECT, 'options' => array( 'classic' => 'Clásica', 'bubble' => 'Burbuja', 'link' => 'Enlace' ), 'default' => 'classic', 'prefix_class' => 'digi-blockquote--button-', 'condition' => array( 'share_button' => 'yes' ) ) );
		// Icono de lo convertido desde PRO (`legacy`: el pájaro); vacío, el propio. Lo fija el mapa: no se elige en el panel.
		$this->add_control( 'share_icon', array( 'type' => $c::HIDDEN, 'default' => '' ) );
		$this->add_control( 'share_label', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => 'Compartir', 'condition' => array( 'share_button' => 'yes', 'share_view!' => 'icon' ), 'dynamic' => array( 'active' => true ), 'ai' => array( 'active' => false ) ) );
		$this->add_control( 'user_name', array( 'label' => 'Usuario', 'type' => $c::TEXT, 'placeholder' => '@usuario', 'condition' => array( 'share_button' => 'yes' ), 'dynamic' => array( 'active' => true ), 'ai' => array( 'active' => false ) ) );
		$this->add_control( 'url_type', array( 'label' => 'Enlace que se comparte', 'type' => $c::SELECT, 'options' => array( 'current_page' => 'Página actual', 'none' => 'Ninguno', 'custom' => 'Personalizado' ), 'default' => 'current_page', 'condition' => array( 'share_button' => 'yes' ) ) );
		$this->add_control( 'url', array( 'label' => 'Enlace', 'type' => $c::TEXT, 'input_type' => 'url', 'dynamic' => array( 'active' => true, 'categories' => array( 'post_meta', 'url' ) ), 'ai' => array( 'active' => false ), 'placeholder' => 'https://tu-enlace.com', 'label_block' => true, 'condition' => array( 'share_button' => 'yes', 'url_type' => 'custom' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_content_style', array( 'label' => 'Contenido', 'tab' => $c::TAB_STYLE ) );
		// Por variables: cada dispositivo aplica la suya.
		$this->add_responsive_control(
			'alignment',
			array(
				'label'                => 'Alineación',
				'type'                 => $c::CHOOSE,
				'options'              => array(
					'left'   => array( 'title' => 'Izquierda', 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => 'Centro', 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => 'Derecha', 'icon' => 'eicon-text-align-right' ),
				),
				'prefix_class'         => 'digi-blockquote%s--align-',
				'selectors_dictionary' => array_intersect_key( self::ALIGN, array_flip( array( 'left', 'center', 'right' ) ) ),
				'selectors'            => array( '{{WRAPPER}} .digi-blockquote' => '{{VALUE}}' ),
				'separator'            => 'after',
			)
		);
		// Sólo en lo convertido desde PRO, que pintaba a la vez la clase de la alineación de cada dispositivo: el mapa fija
		// aquí el resultado de esa cascada, en todos los anchos. Va después de «Alineación», así que en la hoja del
		// documento sus variables sustituyen a las de ella; «Por dispositivo» la quita y vuelve a mandar «Alineación».
		$this->add_control(
			'pro_align',
			array(
				'label'                => 'Alineación heredada',
				'description'          => 'El widget original aplicaba a la vez la alineación de todos los dispositivos, y esta es la que resultaba. «Por dispositivo» vuelve a la de arriba.',
				'type'                 => $c::SELECT,
				'options'              => array(
					''             => 'Por dispositivo',
					'left'         => 'Izquierda',
					'center'       => 'Centro',
					'right'        => 'Derecha',
					'center-left'  => 'Centro, texto a la izquierda',
					'center-right' => 'Centro, texto a la derecha',
				),
				'default'              => '',
				'selectors_dictionary' => self::ALIGN,
				'selectors'            => array( '{{WRAPPER}} .digi-blockquote' => '{{VALUE}}' ),
				'condition'            => array( 'pro_align!' => '' ),
			)
		);
		$this->add_control( 'heading_content_style', array( 'type' => $c::HEADING, 'label' => 'Contenido', 'separator' => 'before' ) );
		$this->add_control( 'content_text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'global' => array( 'default' => 'globals/colors?id=text' ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote__content' => 'color: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'content_typography', 'selector' => '{{WRAPPER}} .digi-blockquote__content' ) );
		$this->add_responsive_control( 'content_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__content + .digi-blockquote__footer' => 'margin-top: {{SIZE}}{{UNIT}}' ) ) );
		$this->add_control( 'heading_author_style', array( 'type' => $c::HEADING, 'label' => 'Autor', 'separator' => 'before' ) );
		$this->add_control( 'author_text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'global' => array( 'default' => 'globals/colors?id=secondary' ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote__author' => 'color: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'author_typography', 'selector' => '{{WRAPPER}} .digi-blockquote__author' ) );
		$this->add_responsive_control( 'author_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => $units, 'default' => array( 'size' => 20 ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote__author' => 'margin-bottom: {{SIZE}}{{UNIT}}' ), 'condition' => array( 'alignment' => 'center', 'share_button' => 'yes' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_button_style', array( 'label' => 'Botón', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'button_size', array( 'label' => 'Tamaño', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0.5, 'max' => 2, 'step' => 0.1 ) ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button' => 'font-size: calc({{SIZE}}{{UNIT}} * 10);' ) ) );
		$this->add_responsive_control( 'button_border_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button' => 'border-radius: {{SIZE}}{{UNIT}}' ), 'range' => array( 'px' => array( 'max' => 50 ), 'em' => array( 'min' => 0, 'max' => 5 ), 'rem' => array( 'min' => 0, 'max' => 5 ) ) ) );
		$this->start_controls_tabs( 'tabs_button_style' );
		$this->start_controls_tab( 'tab_button_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'button_background_color', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button' => 'background-color: {{VALUE}}' ), 'condition' => array( 'share_skin!' => 'link' ) ) );
		$this->add_control( 'button_text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button' => 'color: {{VALUE}}', '{{WRAPPER}} .digi-blockquote__icon' => 'fill: {{VALUE}}' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'tab_button_hover', array( 'label' => 'Al pasar el cursor' ) );
		$this->add_control( 'button_background_color_hover', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button:hover' => 'background-color: {{VALUE}}' ), 'condition' => array( 'share_skin!' => 'link' ) ) );
		$this->add_control( 'button_text_color_hover', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button:hover' => 'color: {{VALUE}}', '{{WRAPPER}} .digi-blockquote__button:hover .digi-blockquote__icon' => 'fill: {{VALUE}}' ) ) );
		$this->add_control( 'button_transition_duration', $time + array( 'selectors' => array( '{{WRAPPER}} .digi-blockquote__button' => '--digi-bq-bt: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'selector' => '{{WRAPPER}} .digi-blockquote__label', 'separator' => 'before' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_border_style', array( 'label' => 'Borde', 'tab' => $c::TAB_STYLE, 'condition' => array( 'blockquote_skin' => 'border' ) ) );
		$this->start_controls_tabs( 'tabs_border_style' );
		$this->start_controls_tab( 'tab_border_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'border_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'border-color: {{VALUE}}' ) ) );
		$this->add_responsive_control( 'border_width', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'border-inline-start-width: {{SIZE}}{{UNIT}}' ) ) );
		$this->add_responsive_control( 'border_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'padding-inline-start: {{SIZE}}{{UNIT}}' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'tab_border_hover', array( 'label' => 'Al pasar el cursor' ) );
		$this->add_control( 'border_color_hover', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote:hover' => 'border-color: {{VALUE}}' ) ) );
		$this->add_responsive_control( 'border_width_hover', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote:hover' => 'border-inline-start-width: {{SIZE}}{{UNIT}}' ) ) );
		$this->add_responsive_control( 'border_gap_hover', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote:hover' => 'padding-inline-start: {{SIZE}}{{UNIT}}' ) ) );
		$this->add_control( 'border_transition_duration', $time + array( 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => '--digi-bq-t: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_responsive_control( 'border_vertical_padding', array( 'label' => 'Relleno vertical', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem', 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}}' ), 'separator' => 'before', 'condition' => array( 'blockquote_skin' => 'border' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_box_style', array( 'label' => 'Caja', 'tab' => $c::TAB_STYLE, 'condition' => array( 'blockquote_skin!' => 'border' ) ) );
		$this->add_responsive_control( 'box_padding', array( 'label' => 'Relleno', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'padding: {{SIZE}}{{UNIT}}' ) ) );
		$this->start_controls_tabs( 'tabs_box_style' );
		$this->start_controls_tab( 'tab_box_normal', array( 'label' => 'Normal' ) );
		$this->add_control( 'box_background_color', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'background-color: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'box_border', 'selector' => '{{WRAPPER}} .digi-blockquote' ) );
		$this->add_responsive_control( 'box_border_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => 'border-radius: {{SIZE}}{{UNIT}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'box_box_shadow', 'exclude' => array( 'box_shadow_position' ), 'selector' => '{{WRAPPER}} .digi-blockquote' ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'tab_box_hover', array( 'label' => 'Al pasar el cursor' ) );
		$this->add_control( 'box_background_color_hover', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote:hover' => 'background-color: {{VALUE}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), array( 'name' => 'box_border_hover', 'selector' => '{{WRAPPER}} .digi-blockquote:hover' ) );
		$this->add_responsive_control( 'box_border_radius_hover', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote:hover' => 'border-radius: {{SIZE}}{{UNIT}}' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), array( 'name' => 'box_box_shadow_hover', 'exclude' => array( 'box_shadow_position' ), 'selector' => '{{WRAPPER}} .digi-blockquote:hover' ) );
		$this->add_control( 'box_transition_duration', $time + array( 'selectors' => array( '{{WRAPPER}} .digi-blockquote' => '--digi-bq-t: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		$this->start_controls_section( 'section_quote_style', array( 'label' => 'Comillas', 'tab' => $c::TAB_STYLE, 'condition' => array( 'blockquote_skin' => 'quotation' ) ) );
		$this->add_control( 'quote_text_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-blockquote:before' => 'color: {{VALUE}}' ) ) );
		$this->add_responsive_control( 'quote_size', array( 'label' => 'Tamaño', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0.5, 'max' => 2, 'step' => 0.1 ) ), 'default' => array( 'size' => 1 ), 'selectors' => array( '{{WRAPPER}} .digi-blockquote:before' => 'font-size: calc({{SIZE}}{{UNIT}} * 100)' ) ) );
		$this->add_responsive_control( 'quote_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => $units, 'selectors' => array( '{{WRAPPER}} .digi-blockquote__content' => 'margin-top: {{SIZE}}{{UNIT}}' ) ) );
		$this->end_controls_section();
	}

	/** Texto sin etiquetas ni entidades (para el enlace de compartir y el nombre accesible). */
	private static function plain( $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
	}

	/** Dirección de la página pedida: el origen del sitio más la ruta (también en subcarpetas y subdominios). */
	private static function current_url(): string {
		$home   = wp_parse_url( (string) home_url( '/' ) );
		$origin = ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . (int) $home['port'] : '' );
		$path   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Va codificado entero en la consulta.
		return $origin . ( '' === $path || '/' !== $path[0] ? '/' . $path : $path );
	}

	/**
	 * ¿Pinta el icono de PRO? Sí si el mapa lo fijó (`share_icon` = `legacy`) o, sin valor, si la Cita se convirtió desde
	 * el Blockquote de PRO antes de existir ese control (marca `_digitalisimo_pro`, que el traductor ya no vuelve a tocar).
	 */
	private static function legacy_icon( array $s ): bool {
		$icon = (string) ( $s['share_icon'] ?? '' );
		return 'legacy' === $icon || ( '' === $icon && 'blockquote' === (string) ( $s['_digitalisimo_pro'] ?? '' ) );
	}

	/**
	 * ¿Pinta el núcleo los iconos de fuente como SVG en línea? Así pintaba PRO su icono (`e_font_icon_svg` activo y los
	 * iconos ya migrados a Font Awesome 5, como `is_migration_allowed()` del gestor de iconos del núcleo); si no, con la
	 * fuente, y la hoja de PRO centraba ese `<i>` en la vista «Icono», pero no el SVG.
	 */
	private static function inline_svg_icons(): bool {
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		if ( ! is_object( $plugin ) || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) || ! method_exists( $plugin->experiments, 'is_feature_active' ) || ! $plugin->experiments->is_feature_active( 'e_font_icon_svg' ) ) {
			return false;
		}
		return (bool) apply_filters( 'elementor/icons_manager/migration_allowed', null === get_option( 'elementor_icon_manager_needs_update', null ) );
	}

	/** Enlace a la intención de publicación con la cita, el autor, el enlace y el usuario, todo codificado. */
	public static function share_url( array $s ): string {
		$text   = self::plain( $s['blockquote_content'] ?? '' );
		$author = self::plain( $s['author_name'] ?? '' );
		if ( '' !== $author ) {
			$text .= ' — ' . $author;
		}
		$args = array( 'text' => $text );
		$type = (string) ( $s['url_type'] ?? 'current_page' );
		if ( 'current_page' === $type ) {
			$args['url'] = self::current_url();
		} elseif ( 'custom' === $type ) {
			$args['url'] = trim( (string) ( $s['url'] ?? '' ) );
		}
		$user = ltrim( self::plain( $s['user_name'] ?? '' ), '@' );
		if ( '' !== $user ) {
			$args['via'] = $user;
		}
		return self::SHARE_URL . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$content = (string) ( $s['blockquote_content'] ?? '' );
		$author  = (string) ( $s['author_name'] ?? '' );
		$share   = 'yes' === ( $s['share_button'] ?? '' );
		if ( '' === $content && '' === $author && ! $share ) {
			return;
		}
		echo '<blockquote class="digi-blockquote"><p class="digi-blockquote__content">' . wp_kses_post( $content ) . '</p>';
		if ( '' !== $author || $share ) {
			echo '<footer class="digi-blockquote__footer">';
			if ( '' !== $author ) {
				echo '<cite class="digi-blockquote__author">' . wp_kses_post( $author ) . '</cite>';
			}
			if ( $share ) {
				$view  = in_array( $s['share_view'] ?? '', self::VIEWS, true ) ? $s['share_view'] : 'icon-text';
				$label = self::plain( $s['share_label'] ?? '' );
				$named = 'icon' !== $view && '' !== $label;
				// En la vista «Icono» el texto no se muestra (su control está oculto y llega vacío), pero sigue siendo el
				// nombre del enlace: el guardado («Tweet» en lo convertido desde PRO) o, sin él, uno descriptivo.
				$name   = '' !== $label ? $label : self::plain( (string) ( $this->get_settings( 'share_label' ) ?? '' ) );
				$legacy = 'text' !== $view && self::legacy_icon( $s );
				// El icono de PRO, donde lo dejaba PRO: con SVG en línea, su hoja no lo centraba en la vista «Icono».
				echo '<a class="digi-blockquote__button' . ( $legacy && self::inline_svg_icons() ? ' digi-blockquote__button--icon-start' : '' ) . '" href="' . esc_url( self::share_url( $s ) ) . '" target="_blank" rel="noopener noreferrer"' . ( $named ? '' : ' aria-label="' . esc_attr( '' !== $name ? $name : 'Compartir en X' ) . '"' ) . '>';
				if ( 'text' !== $view ) {
					echo $legacy ? self::ICON_LEGACY : self::ICON; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constantes propias.
				}
				if ( 'icon' !== $view ) {
					echo '<span class="digi-blockquote__label">' . esc_html( $label ) . '</span>';
				}
				echo '</a>';
			}
			echo '</footer>';
		}
		echo '</blockquote>';
	}

	public function render_markdown(): string {
		$s       = $this->get_settings_for_display();
		$content = self::plain( $s['blockquote_content'] ?? '' );
		$author  = self::plain( $s['author_name'] ?? '' );
		if ( '' === $content && '' === $author ) {
			return '';
		}
		$lines = array();
		foreach ( '' !== $content ? preg_split( "/\r\n|\n|\r/", $content ) : array() as $line ) {
			$lines[] = '> ' . $line;
		}
		if ( '' !== $author ) {
			if ( $lines ) {
				$lines[] = '>';
			}
			$lines[] = '> — ' . $author;
		}
		return implode( "\n", $lines );
	}

	protected function content_template() {
		?>
		<# var share = 'yes' === settings.share_button, mode = _.contains( [ 'icon-text', 'icon', 'text' ], settings.share_view ) ? settings.share_view : 'icon-text', label = settings.share_label ? String( settings.share_label ).replace( /<[^>]*>/g, '' ).trim() : '';
		if ( settings.blockquote_content || settings.author_name || share ) {
		view.addRenderAttribute( 'blockquote_content', 'class', 'digi-blockquote__content' ); view.addInlineEditingAttributes( 'blockquote_content' );
		view.addRenderAttribute( 'author_name', 'class', 'digi-blockquote__author' ); view.addInlineEditingAttributes( 'author_name', 'none' ); #>
		<blockquote class="digi-blockquote"><p {{{ view.getRenderAttributeString( 'blockquote_content' ) }}}>{{ settings.blockquote_content }}</p>
		<# if ( settings.author_name || share ) { #><footer class="digi-blockquote__footer">
		<# if ( settings.author_name ) { #><cite {{{ view.getRenderAttributeString( 'author_name' ) }}}>{{ settings.author_name }}</cite><# } #>
		<# if ( share ) { #><a class="digi-blockquote__button" href="#" target="_blank" rel="noopener noreferrer"<# if ( 'icon' === mode || ! label ) { #> aria-label="{{ label || 'Compartir en X' }}"<# } #>>
		<# if ( 'text' !== mode ) { if ( 'legacy' === settings.share_icon || ( ! settings.share_icon && 'blockquote' === settings._digitalisimo_pro ) ) { #><?php echo self::ICON_LEGACY; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constante propia. ?><# } else { #><?php echo self::ICON; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constante propia. ?><# } } #>
		<# if ( 'icon' !== mode ) { #><span class="digi-blockquote__label">{{ label }}</span><# } #></a><# } #>
		</footer><# } #></blockquote>
		<# } #>
		<?php
	}
}
