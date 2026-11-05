<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Formulario de búsqueda (`digitalisimo-search-form`): sustituye al widget «Search Form» de PRO
 * (`modules/theme-elements/widgets/search-form.php`, mapa `modules/digitalisimo-convert/maps/search-form.php`). Repite
 * sus controles con el mismo nombre, valor por defecto, opciones y condiciones; el marcado, las clases, la hoja y el
 * script son propios, sin jQuery ni los paquetes de PRO.
 *
 * Marcado: `<search role="search">` con un formulario GET a `home_url()` (campo `s`, valor `get_search_query()`), una
 * etiqueta «Buscar» para lectores de pantalla y, según la piel:
 * - Clásica: botón de envío (`<button type="submit">`) con icono SVG propio (lupa o flecha, que se invierte en RTL) o
 *   texto, y nombre accesible «Buscar».
 * - Mínima: la lupa es la propia etiqueta del campo (`<label for>`), así que pulsarla lo enfoca sin JavaScript.
 * - Pantalla completa: un botón que abre un `<dialog>` modal (`showModal()`: capa superior, foco dentro, Escape lo
 *   cierra y el foco vuelve al botón), con el campo y un botón «Cerrar la búsqueda». Es la única piel con script
 *   (`assets/js/search-form.js`, `defer`, sin dependencias).
 * Los ganchos `elementor_pro/search_form/before_input` y `…/after_input` se siguen disparando para los complementos que
 * añaden campos (idioma, tipo de contenido).
 *
 * Estado «Foco» de los colores: PRO lo aplicaba con una clase que ponía su script; aquí es
 * `:has(.digi-search-form__input:focus)`, sin script. Variables propias (`--digi-sf-*`) en lugar de las de PRO.
 *
 * Diferencias declaradas con PRO: foco visible propio (el campo y los botones tienen contorno; PRO lo quitaba); el
 * conmutador y el cierre de pantalla completa son `<button>` (PRO usaba `div role="button"`); iconos SVG propios en
 * lugar de los de la fuente de iconos; textos de la interfaz en español; el widget aparece en el panel (PRO lo
 * ocultaba en favor de su widget «Search»).
 */
class Search_Form extends \Elementor\Widget_Base {

	/** Lupa, flecha y aspa, dibujadas para este widget en una caja de 24 × 24 (`currentColor`). */
	const ICONS = array(
		'search' => '<svg class="digi-search-form__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 2a8 8 0 0 1 6.32 12.9l5.39 5.4-1.41 1.4-5.4-5.38A8 8 0 1 1 10 2zm0 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10z"/></svg>',
		'arrow'  => '<svg class="digi-search-form__svg digi-search-form__svg--arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.3 3.3 22 12l-8.7 8.7-2.1-2.1 5.1-5.1H2v-3h14.3l-5.1-5.1z"/></svg>',
		'close'  => '<svg class="digi-search-form__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m5.6 3.5 6.4 6.4 6.4-6.4 2.1 2.1-6.4 6.4 6.4 6.4-2.1 2.1-6.4-6.4-6.4 6.4-2.1-2.1 6.4-6.4-6.4-6.4z"/></svg>',
	);

	public function get_name() {
		return 'digitalisimo-search-form';
	}

	public function get_title() {
		return esc_html__( 'Formulario de búsqueda', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-site-search';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'búsqueda', 'buscar', 'buscador', 'formulario', 'search', 'form' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/**
	 * Como PRO, fuera del panel: el formulario de búsqueda sólo existe para el contenido que ya lo usa (lo convertido y lo
	 * importado se sigue pintando); para uno nuevo el panel ofrece el buscador de la familia de navegación y búsqueda.
	 */
	public function show_in_panel(): bool {
		return false;
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	/** El script sólo abre y cierra la pantalla completa: las demás pieles no lo cargan (el tipo del editor, siempre). */
	public function get_script_depends(): array {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) {
			return Module::script_depends( $this->get_name() );
		}
		return 'full_screen' === ( $this->get_settings( 'skin' ) ?? 'classic' ) ? Module::script_depends( $this->get_name() ) : array();
	}

	protected function register_controls() {
		$this->start_controls_section(
			'search_content',
			array(
				'label' => esc_html__( 'Formulario de búsqueda', 'pro-elements' ),
			)
		);

		$this->add_control(
			'skin',
			array(
				'label'              => esc_html__( 'Apariencia', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'classic',
				'options'            => array(
					'classic'     => esc_html__( 'Clásica', 'pro-elements' ),
					'minimal'     => esc_html__( 'Mínima', 'pro-elements' ),
					'full_screen' => esc_html__( 'Pantalla completa', 'pro-elements' ),
				),
				'prefix_class'       => 'digi-search-form--skin-',
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label'     => esc_html__( 'Texto de ejemplo', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'separator' => 'before',
				'default'   => esc_html__( 'Buscar', 'pro-elements' ) . '...',
				'dynamic'   => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'heading_button_content',
			array(
				'label'     => esc_html__( 'Botón', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_control(
			'button_type',
			array(
				'label'        => esc_html__( 'Tipo', 'pro-elements' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'icon',
				'options'      => array(
					'icon' => esc_html__( 'Icono', 'pro-elements' ),
					'text' => esc_html__( 'Texto', 'pro-elements' ),
				),
				'prefix_class' => 'digi-search-form--button-type-',
				'render_type'  => 'template',
				'condition'    => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => esc_html__( 'Texto', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Buscar', 'pro-elements' ),
				'condition' => array(
					'button_type' => 'text',
					'skin'        => 'classic',
				),
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'        => esc_html__( 'Icono', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'default'      => 'search',
				'options'      => array(
					'search' => array(
						'title' => esc_html__( 'Lupa', 'pro-elements' ),
						'icon'  => 'eicon-search',
					),
					'arrow'  => array(
						'title' => esc_html__( 'Flecha', 'pro-elements' ),
						'icon'  => 'eicon-arrow-right',
					),
				),
				'render_type'  => 'template',
				'prefix_class' => 'digi-search-form--icon-',
				'condition'    => array(
					'button_type' => 'icon',
					'skin'        => 'classic',
				),
			)
		);

		$this->add_control(
			'size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 50,
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-search-form__container' => 'min-height: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-search-form__submit'    => 'min-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-search-form__icon'      => 'padding-inline-start: calc({{SIZE}}{{UNIT}} / 3)',
					'{{WRAPPER}} .digi-search-form__input, {{WRAPPER}}.digi-search-form--button-type-text .digi-search-form__submit' => 'padding-left: calc({{SIZE}}{{UNIT}} / 3); padding-right: calc({{SIZE}}{{UNIT}} / 3)',
				),
				'condition'  => array(
					'skin!' => 'full_screen',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'toggle_button_content',
			array(
				'label'     => esc_html__( 'Conmutador', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'skin' => 'full_screen',
				),
			)
		);

		$this->add_control(
			'toggle_align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'default'              => 'center',
				'options'              => array(
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array(
					'{{WRAPPER}} .digi-search-form' => 'text-align: {{VALUE}}',
				),
				'condition'            => array(
					'skin' => 'full_screen',
				),
			)
		);

		$this->add_control(
			'toggle_size',
			array(
				'label'     => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 33,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__toggle' => '--digi-sf-toggle-size: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'skin' => 'full_screen',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_input_style',
			array(
				'label' => esc_html__( 'Campo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'icon_size_minimal',
			array(
				'label'     => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__icon' => '--digi-sf-icon-minimal: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'skin' => 'minimal',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'overlay_background_color',
			array(
				'label'     => esc_html__( 'Color de la superposición', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.digi-search-form--skin-full_screen .digi-search-form__container' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin' => 'full_screen',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'input_typography',
				'selector' => '{{WRAPPER}} input[type="search"].digi-search-form__input',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->start_controls_tabs( 'tabs_input_colors' );

		$this->start_controls_tab(
			'tab_input_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'input_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__input, {{WRAPPER}} .digi-search-form__icon, {{WRAPPER}} .digi-search-form__close, {{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input' => 'color: {{VALUE}}; fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'input_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form__container' => 'background-color: {{VALUE}}',
					'{{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin!' => 'full_screen',
				),
			)
		);

		$this->add_control(
			'input_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form__container' => 'border-color: {{VALUE}}',
					'{{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'           => 'input_box_shadow',
				'selector'       => '{{WRAPPER}} .digi-search-form__container',
				'fields_options' => array(
					'box_shadow_type' => array(
						'separator' => 'default',
					),
				),
				'condition'      => array(
					'skin!' => 'full_screen',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_input_focus',
			array(
				'label' => esc_html__( 'Foco', 'pro-elements' ),
			)
		);

		$this->add_control(
			'input_text_color_focus',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form:has(.digi-search-form__input:focus) .digi-search-form__input, {{WRAPPER}} .digi-search-form:has(.digi-search-form__input:focus) .digi-search-form__icon, {{WRAPPER}} .digi-search-form__close:hover, {{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input:focus' => 'color: {{VALUE}}; fill: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'input_background_color_focus',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form:has(.digi-search-form__input:focus) .digi-search-form__container' => 'background-color: {{VALUE}}',
					'{{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input:focus' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'skin!' => 'full_screen',
				),
			)
		);

		$this->add_control(
			'input_border_color_focus',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form:has(.digi-search-form__input:focus) .digi-search-form__container' => 'border-color: {{VALUE}}',
					'{{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input:focus' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'           => 'input_box_shadow_focus',
				'selector'       => '{{WRAPPER}} .digi-search-form:has(.digi-search-form__input:focus) .digi-search-form__container',
				'fields_options' => array(
					'box_shadow_type' => array(
						'separator' => 'default',
					),
				),
				'condition'      => array(
					'skin!' => 'full_screen',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form__container' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 200,
					),
					'em'  => array(
						'max' => 20,
					),
					'rem' => array(
						'max' => 20,
					),
				),
				'default'    => array(
					'size' => 3,
				),
				'selectors'  => array(
					'{{WRAPPER}}:not(.digi-search-form--skin-full_screen) .digi-search-form__container' => 'border-radius: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.digi-search-form--skin-full_screen input[type="search"].digi-search-form__input' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_style',
			array(
				'label'     => esc_html__( 'Botón', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'skin' => 'classic',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'button_typography',
				'selector'  => '{{WRAPPER}} .digi-search-form__submit',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'condition' => array(
					'button_type' => 'text',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_button_colors' );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__submit' => '--digi-sf-submit-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__submit' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'button_text_color_hover',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__submit:hover' => '--digi-sf-submit-color: {{VALUE}}',
					'{{WRAPPER}} .digi-search-form__submit:focus' => '--digi-sf-submit-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'button_background_color_hover',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__submit:hover' => 'background-color: {{VALUE}}',
					'{{WRAPPER}} .digi-search-form__submit:focus' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'     => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__submit' => '--digi-sf-submit-icon: {{SIZE}}{{UNIT}}',
				),
				'condition' => array(
					'button_type' => 'icon',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'button_width',
			array(
				'label'     => esc_html__( 'Ancho', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 1,
						'max'  => 10,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__submit' => 'min-width: calc( {{SIZE}} * {{size.SIZE}}{{size.UNIT}} )',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_toggle_style',
			array(
				'label'     => esc_html__( 'Conmutador', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'skin' => 'full_screen',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_toggle_color' );

		$this->start_controls_tab(
			'tab_toggle_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'toggle_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__toggle' => '--digi-sf-toggle-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__toggle' => '--digi-sf-toggle-bg: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_toggle_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'toggle_color_hover',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__toggle:hover' => '--digi-sf-toggle-color: {{VALUE}}',
					'{{WRAPPER}} .digi-search-form__toggle:focus' => '--digi-sf-toggle-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'toggle_background_color_hover',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__toggle:hover' => '--digi-sf-toggle-bg: {{VALUE}}',
					'{{WRAPPER}} .digi-search-form__toggle:focus' => '--digi-sf-toggle-bg: {{VALUE}}',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'toggle_icon_size',
			array(
				'label'     => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .digi-search-form__toggle' => '--digi-sf-toggle-icon: calc({{SIZE}}em / 100)',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'toggle_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 20,
					),
					'em'  => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-search-form__toggle' => '--digi-sf-toggle-bw: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'toggle_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-search-form__toggle' => '--digi-sf-toggle-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Icono SVG propio; la flecha apunta al final de la línea (se invierte en RTL, como la de PRO). */
	private static function icon( string $name ): string {
		$svg = self::ICONS[ $name ] ?? self::ICONS['search'];
		if ( 'arrow' === $name && is_rtl() ) {
			$svg = str_replace( 'digi-search-form__svg--arrow', 'digi-search-form__svg--arrow digi-search-form__svg--rtl', $svg );
		}
		return $svg;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$skin     = in_array( $settings['skin'] ?? 'classic', array( 'classic', 'minimal', 'full_screen' ), true ) ? $settings['skin'] : 'classic';
		$id       = 'digi-search-form-' . $this->get_id();
		$label    = esc_html__( 'Buscar', 'pro-elements' );

		$this->add_render_attribute(
			'form',
			array(
				'class'  => 'digi-search-form',
				'action' => esc_url( home_url() ),
				'method' => 'get',
			)
		);
		$this->add_render_attribute(
			'input',
			array(
				'id'          => $id,
				'placeholder' => (string) ( $settings['placeholder'] ?? '' ),
				'class'       => 'digi-search-form__input',
				'type'        => 'search',
				'name'        => 's',
				'value'       => function_exists( 'get_search_query' ) ? get_search_query() : '',
			)
		);
		echo '<search role="search"><form ' . $this->get_render_attribute_string( 'form' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados.
		do_action( 'elementor_pro/search_form/before_input', $this );
		if ( 'full_screen' === $skin ) {
			echo '<button type="button" class="digi-search-form__toggle" aria-label="' . esc_attr( $label ) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '-dialog">' . self::icon( 'search' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio.
			echo '<dialog class="digi-search-form__container" id="' . esc_attr( $id ) . '-dialog" aria-label="' . esc_attr( $label ) . '">';
		} else {
			echo '<div class="digi-search-form__container">';
		}
		if ( 'minimal' === $skin ) {
			echo '<label class="digi-search-form__icon" for="' . esc_attr( $id ) . '">' . self::icon( 'search' ) . '<span class="digi-search-form__label">' . $label . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio y texto escapado.
		} else {
			echo '<label class="digi-search-form__label" for="' . esc_attr( $id ) . '">' . $label . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado.
		}
		echo '<input ' . $this->get_render_attribute_string( 'input' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados.
		do_action( 'elementor_pro/search_form/after_input', $this );
		if ( 'classic' === $skin ) {
			$is_icon = 'icon' === ( $settings['button_type'] ?? 'icon' );
			$text    = $is_icon ? '' : trim( wp_kses_post( (string) ( $settings['button_text'] ?? '' ) ) );
			$inner   = $is_icon ? self::icon( 'arrow' === ( $settings['icon'] ?? 'search' ) ? 'arrow' : 'search' ) : $text;
			// Con texto visible, el texto es el nombre accesible; con icono (o sin texto), «Buscar».
			$named   = '' === trim( wp_strip_all_tags( $text ) ) ? ' aria-label="' . esc_attr( $label ) . '"' : '';
			echo '<button class="digi-search-form__submit" type="submit"' . $named . '>' . $inner . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio o HTML permitido.
		}
		if ( 'full_screen' === $skin ) {
			echo '<button type="button" class="digi-search-form__close" aria-label="' . esc_attr__( 'Cerrar la búsqueda', 'pro-elements' ) . '">' . self::icon( 'close' ) . '</button></dialog>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio.
		} else {
			echo '</div>';
		}
		echo '</form></search>';
	}
}
