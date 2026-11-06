<?php
namespace Digitalisimo\Elements\Loop\Widgets;

use Digitalisimo\Elements\Loop\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Utils;
use ElementorPro\Modules\LoopFilter\Traits\Taxonomy_Filter_Trait;
use ElementorPro\Modules\ThemeBuilder\Module as ThemeBuilderModule;
use ElementorPro\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Filtro de taxonomías (`digitalisimo-taxonomy-filter`, C5 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c5.md`
 * frente J): sustituye al «Taxonomy Filter» de PRO (`modules/loop-filter/widgets/taxonomy-filter.php`, mapa
 * `modules/digitalisimo-convert/maps/taxonomy-filter.php`).
 *
 * - **Controles**: los 22 del widget de PRO (y sus grupos), con el mismo nombre, valor por defecto y condiciones;
 *   textos propios y variables propias (`--digi-tfilter-*`) en lugar de las de la hoja de PRO. Estructura y controles
 *   derivados de PRO Elements (GPL).
 * - **Términos**: los mismos que PRO, con su mismo código de consulta (`Taxonomy_Filter_Trait` y los métodos del
 *   módulo `loop-filter`, que son función del constructor): ocultar vacíos según el bucle enlazado, hijos por
 *   profundidad, número máximo y exclusiones del control de consulta del bucle.
 * - **Marcado**: `<search class="digi-tfilter">` con un `<button type="button">` por término (`aria-pressed`,
 *   `data-filter` = ficha) y el botón «Todos» (`data-filter="__all"`) **primero en el orden del documento** (PRO lo
 *   ponía al final y lo adelantaba con `order: -1`: el foco con teclado seguía otro orden que la vista).
 * - **Vínculo**: `selected_element` (el ID del Loop Grid, propio o de PRO, del mismo documento). Al pulsar, el script
 *   escribe en la URL los mismos parámetros que PRO (`e-filter-<bucle>-<taxonomía>=fichas`, `+` para Y y `~` para O,
 *   sin `e-page-<bucle>`) y pide **la misma página con esa URL**: el módulo `loop-filter` aplica el filtro al bucle por
 *   su ID (`filter_loop_query()`), sea cual sea su widget, y el script sustituye el contenido del bucle por el de la
 *   respuesta. Sin REST ni jQuery; anuncia lo cargado con una región `role="status"`.
 * - **Recursos**: `assets/css/taxonomy-filter.css` y `assets/js/taxonomy-filter.js` (`defer`); en el editor,
 *   `assets/js/taxonomy-filter-editor.js` rellena «Loop Grid» y «Taxonomía» como el editor de PRO.
 */
class Taxonomy_Filter extends \Elementor\Widget_Base {
	use Taxonomy_Filter_Trait;

	const NAME = 'digitalisimo-taxonomy-filter';

	/** Valor del botón «Todos» (el mismo que PRO: lo leen la URL y los filtros de F7b). */
	const ALL = '__all';

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Filtro de taxonomías', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-taxonomy-filter';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'filtro', 'filter', 'taxonomía', 'taxonomy', 'categorías', 'etiquetas', 'bucle', 'loop', 'digitalisimo' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( self::NAME );
	}

	public function get_script_depends(): array {
		return Module::script_depends( self::NAME );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_taxonomy_filter',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
			)
		);

		$this->add_control(
			'selected_element',
			array(
				'label'              => esc_html__( 'Loop Grid', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'' => esc_html__( 'Elige un widget', 'pro-elements' ),
				),
				'label_block'        => true,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'taxonomy',
			array(
				'label'              => esc_html__( 'Taxonomía', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'' => esc_html__( 'Elige una taxonomía', 'pro-elements' ),
				),
				'label_block'        => true,
				'condition'          => array(
					'selected_element!' => '',
				),
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'direction',
			array(
				'label'                => esc_html__( 'Dirección', 'pro-elements' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => array(
					'horizontal' => esc_html__( 'Horizontal', 'pro-elements' ),
					'vertical'   => esc_html__( 'Vertical', 'pro-elements' ),
				),
				'default'              => 'horizontal',
				'selectors_dictionary' => array(
					'horizontal' => '--digi-tfilter-direction: row;--digi-tfilter-white-space: nowrap;',
					'vertical'   => '--digi-tfilter-direction: column;--digi-tfilter-white-space: initial;',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}};',
				),
				'condition'            => array(
					'selected_element!' => '',
				),
				'control_type'         => 'content',
			)
		);

		$align = array(
			'start'  => array(
				'title' => esc_html__( 'Inicio', 'pro-elements' ),
				'icon'  => 'eicon-align-start-h',
			),
			'center' => array(
				'title' => esc_html__( 'Centro', 'pro-elements' ),
				'icon'  => 'eicon-align-center-h',
			),
			'end'    => array(
				'title' => esc_html__( 'Fin', 'pro-elements' ),
				'icon'  => 'eicon-align-end-h',
			),
		);
		$stretch = array(
			'stretch' => array(
				'title' => esc_html__( 'Estirar', 'pro-elements' ),
				'icon'  => 'eicon-align-stretch-h',
			),
		);

		$this->add_responsive_control(
			'item_alignment_horizontal',
			array(
				'label'                => esc_html__( 'Alineación de los elementos', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => $align + $stretch,
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}};',
				),
				'selectors_dictionary' => array(
					'start'   => '--digi-tfilter-justify: flex-start; --digi-tfilter-item-width: initial; --digi-tfilter-item-grow: 0;',
					'center'  => '--digi-tfilter-justify: center; --digi-tfilter-item-width: initial; --digi-tfilter-item-grow: 0;',
					'end'     => '--digi-tfilter-justify: flex-end; --digi-tfilter-item-width: initial; --digi-tfilter-item-grow: 0;',
					'stretch' => '--digi-tfilter-justify: normal; --digi-tfilter-item-width: 100%; --digi-tfilter-item-grow: 1;',
				),
				'condition'            => array(
					'direction'         => 'horizontal',
					'selected_element!' => '',
				),
				'frontend_available'   => true,
			)
		);

		$this->add_responsive_control(
			'title_alignment_horizontal',
			array(
				'label'                => esc_html__( 'Alineación del título', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => $align,
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}};',
				),
				'selectors_dictionary' => array(
					'start'  => '--digi-tfilter-item-justify: flex-start;',
					'center' => '--digi-tfilter-item-justify: center;',
					'end'    => '--digi-tfilter-item-justify: flex-end;',
				),
				'condition'            => array(
					'direction'                 => 'horizontal',
					'selected_element!'         => '',
					'item_alignment_horizontal' => 'stretch',
				),
			)
		);

		$fit = '--digi-tfilter-item-width: initial; --digi-tfilter-item-max-width: calc(100% - calc(var(--digi-tfilter-item-padding) * 3)); --digi-tfilter-item-grow: initial; --digi-tfilter-item-box-sizing: initial;';
		$this->add_responsive_control(
			'item_alignment_vertical',
			array(
				'label'                => esc_html__( 'Alineación de los elementos', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => $align + $stretch,
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}};',
				),
				'selectors_dictionary' => array(
					'start'   => '--digi-tfilter-align: flex-start; ' . $fit . ' --digi-tfilter-item-text: start;',
					'center'  => '--digi-tfilter-align: center; ' . $fit . ' --digi-tfilter-item-text: center;',
					'end'     => '--digi-tfilter-align: flex-end; ' . $fit . ' --digi-tfilter-item-text: end;',
					'stretch' => '--digi-tfilter-align: center; --digi-tfilter-item-width: 100%; --digi-tfilter-item-max-width: 100%; --digi-tfilter-item-grow: 1; --digi-tfilter-item-box-sizing: border-box; --digi-tfilter-item-text: center;',
				),
				'condition'            => array(
					'direction'         => 'vertical',
					'selected_element!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'title_alignment_vertical',
			array(
				'label'                => esc_html__( 'Alineación del título', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => $align,
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}};',
				),
				'selectors_dictionary' => array(
					'start'  => '--digi-tfilter-item-justify: flex-start; --digi-tfilter-item-align: flex-start; --digi-tfilter-item-text: start;',
					'center' => '--digi-tfilter-item-justify: center; --digi-tfilter-item-align: center; --digi-tfilter-item-text: center;',
					'end'    => '--digi-tfilter-item-justify: flex-end; --digi-tfilter-item-align: flex-end; --digi-tfilter-item-text: end;',
				),
				'condition'            => array(
					'direction'               => 'vertical',
					'selected_element!'       => '',
					'item_alignment_vertical' => 'stretch',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_settings',
			array(
				'label'     => esc_html__( 'Ajustes', 'pro-elements' ),
				'condition' => array(
					'selected_element!' => '',
				),
			)
		);

		$this->add_control(
			'heading_filter_logic',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Lógica del filtro', 'pro-elements' ),
			)
		);

		$this->add_control(
			'multiple_selection',
			array(
				'label'              => esc_html__( 'Selección múltiple', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'default'            => 'no',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'logical_combination',
			array(
				'label'              => esc_html__( 'Combinación lógica', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'AND',
				'options'            => array(
					'AND' => esc_html__( 'Y (todas)', 'pro-elements' ),
					'OR'  => esc_html__( 'O (cualquiera)', 'pro-elements' ),
				),
				'condition'          => array(
					'multiple_selection' => 'yes',
				),
				'separator'          => 'after',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'heading_displayed_elements',
			array(
				'type'  => Controls_Manager::HEADING,
				'label' => esc_html__( 'Elementos que se muestran', 'pro-elements' ),
			)
		);

		$this->add_control(
			'show_empty_items',
			array(
				'label'     => esc_html__( 'Términos vacíos', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'no',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
			)
		);

		$this->add_control(
			'show_child_taxonomy',
			array(
				'label'     => esc_html__( 'Términos hijos', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'no',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
			)
		);

		$this->add_control(
			'child_taxonomy_depth',
			array(
				'label'     => esc_html__( 'Profundidad', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '1',
				'options'   => array_combine( array( '1', '2', '3', '4', '5', '6' ), array( '1', '2', '3', '4', '5', '6' ) ),
				'condition' => array(
					'show_child_taxonomy' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_first_item',
			array(
				'label'     => esc_html__( 'Primer elemento', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
			)
		);

		$this->add_control(
			'first_item_title',
			array(
				'label'       => esc_html__( 'Título del primer elemento', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Todos', 'pro-elements' ),
				'label_block' => true,
				'dynamic'     => array(
					'active' => true,
				),
				'condition'   => array(
					'show_first_item' => 'yes',
				),
			)
		);

		$this->add_control(
			'number_of_taxonomies',
			array(
				'label' => esc_html__( 'Número de términos', 'pro-elements' ),
				'type'  => Controls_Manager::NUMBER,
				'min'   => 1,
			)
		);

		$this->add_responsive_control(
			'horizontal_scroll',
			array(
				'label'                => esc_html__( 'Desplazamiento horizontal', 'pro-elements' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => array(
					'disable' => esc_html__( 'Desactivado', 'pro-elements' ),
					'enable'  => esc_html__( 'Activado', 'pro-elements' ),
				),
				'condition'            => array(
					'direction' => 'horizontal',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}};',
				),
				'selectors_dictionary' => array(
					'enable'  => '--digi-tfilter-wrap: nowrap; --digi-tfilter-overflow-x: auto;',
					'disable' => '--digi-tfilter-wrap: wrap; --digi-tfilter-overflow-x: initial;',
				),
				'default'              => 'disable',
				'frontend_available'   => true,
				'separator'            => 'before',
				'description'          => esc_html__( 'Desplaza los elementos que no caben en su contenedor.', 'pro-elements' ),
			)
		);

		$this->end_controls_section();

		$this->register_design_layout_controls();
	}

	protected function register_design_layout_controls() {
		$item   = '{{WRAPPER}} .digi-tfilter__item';
		$normal = $item . ':not([aria-pressed=true]):not(:hover)';
		$hover  = $item . ':hover:not([aria-pressed=true])';
		$active = $item . '[aria-pressed="true"]';

		$this->start_controls_section(
			'section_design_layout',
			array(
				'label' => esc_html__( 'Elementos', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'taxonomy_filter_items_space_between',
			array(
				'label'      => esc_html__( 'Separación entre elementos', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 200 ),
					'em'  => array( 'max' => 20 ),
					'rem' => array( 'max' => 20 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-tfilter-gap: {{SIZE}}{{UNIT}}',
				),
				'separator'  => 'after',
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'taxonomy_filter_typography',
				'selector' => $item,
			)
		);

		$this->start_controls_tabs( 'taxonomy_filter_tabs_section' );

		$states = array(
			'normal' => array( esc_html__( 'Normal', 'pro-elements' ), $normal, '--digi-tfilter-color' ),
			'hover'  => array( esc_html__( 'Al pasar el puntero', 'pro-elements' ), $hover, '--digi-tfilter-hover-color' ),
			'active' => array( esc_html__( 'Activo', 'pro-elements' ), $active, '--digi-tfilter-active-color' ),
		);
		foreach ( $states as $state => $spec ) {
			list( $label, $selector, $variable ) = $spec;
			$this->start_controls_tab( 'taxonomy_filter_tabs_' . $state, array( 'label' => $label ) );

			$this->add_control(
				'taxonomy_filter_' . $state . '_text_color',
				array(
					'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}}' => $variable . ': {{VALUE}};',
					),
				)
			);

			$this->add_group_control(
				Group_Control_Text_Shadow::get_type(),
				array(
					'name'     => 'taxonomy_filter_' . $state . '_text_shadow',
					'selector' => $selector,
				)
			);

			$this->add_group_control(
				Group_Control_Background::get_type(),
				array(
					'name'     => 'taxonomy_filter_' . $state . '_background',
					'selector' => $selector,
					'exclude'  => array( 'image', 'video' ),
				)
			);

			$this->add_group_control(
				Group_Control_Border::get_type(),
				array(
					'name'     => 'taxonomy_filter_' . $state . '_border',
					'selector' => $selector,
				)
			);

			$this->add_group_control(
				Group_Control_Box_Shadow::get_type(),
				array(
					'name'     => 'taxonomy_filter_' . $state . '_box_shadow',
					'selector' => $selector,
				)
			);

			$this->end_controls_tab();
		}

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'taxonomy_filter_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-tfilter-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_responsive_control(
			'taxonomy_filter_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vm', 'custom' ),
				'selectors'  => array(
					// Las variables dan el área mínima de 24 × 24 px con cualquier relleno (la hoja las resta de 24 px).
					$item => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; --digi-tfilter-pad-block: calc({{TOP}}{{UNIT}} + {{BOTTOM}}{{UNIT}}); --digi-tfilter-pad-inline: calc({{RIGHT}}{{UNIT}} + {{LEFT}}{{UNIT}});',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Aviso del editor (nada en el front). */
	protected function print_empty_results_if_editor( string $message_key ): void {
		if ( ! Plugin::elementor()->editor->is_edit_mode() ) {
			return;
		}
		$messages = array(
			'select_loop_widget'   => esc_html__( 'Elige un Loop Grid para ver el filtro de taxonomías.', 'pro-elements' ),
			'no_taxonomy_selected' => esc_html__( 'Elige una taxonomía.', 'pro-elements' ),
			'no_terms_found'       => esc_html__( 'No se encontraron términos de la taxonomía.', 'pro-elements' ),
		);
		echo '<div class="digi-tfilter__empty">' . esc_html( $messages[ $message_key ] ) . '</div>';
	}

	private function has_empty_results( $selected_element, $user_selected_taxonomy, $terms ): bool {
		if ( empty( $selected_element ) ) {
			$this->print_empty_results_if_editor( 'select_loop_widget' );
			return true;
		}
		if ( empty( $user_selected_taxonomy ) ) {
			$this->print_empty_results_if_editor( 'no_taxonomy_selected' );
			return true;
		}
		if ( empty( $terms ) ) {
			$this->print_empty_results_if_editor( 'no_terms_found' );
			return true;
		}
		return false;
	}

	/** Ajustes guardados del bucle enlazado, en el documento de la ubicación actual (como PRO). */
	private function get_loop_widget_settings(): array {
		$document = Plugin::elementor()->documents->get_doc_for_frontend( $this->get_current_ID() );
		if ( ! $document ) {
			return array();
		}
		$widget_data = Utils::find_element_recursive( $document->get_elements_data(), $this->get_settings_for_display( 'selected_element' ) );
		return ! empty( $widget_data['settings'] ) && is_array( $widget_data['settings'] ) ? $widget_data['settings'] : array();
	}

	/** Documento de la ubicación actual del Theme Builder, o la entrada en curso (como PRO). */
	private function get_current_ID() {
		if ( ! class_exists( ThemeBuilderModule::class ) ) {
			return get_the_ID();
		}
		$post_id       = 0;
		$theme_builder = ThemeBuilderModule::instance();
		$location      = $theme_builder->get_locations_manager()->get_current_location();
		$documents     = $theme_builder->get_conditions_manager()->get_documents_for_location( $location );
		if ( empty( $documents ) ) {
			return get_the_ID();
		}
		foreach ( $documents as $document ) {
			$post_id = $document->get_post()->ID;
		}
		return $post_id;
	}

	private function is_term_excluded_by_query_control( $term, $loop_filter_module ): bool {
		$loop_widget_settings = $this->get_loop_widget_settings();
		$skin                 = ! empty( $loop_widget_settings['_skin'] ) ? $loop_widget_settings['_skin'] : 'post';

		return $loop_filter_module->is_term_not_selected_for_inclusion( $loop_widget_settings, $term, $skin )
			|| $loop_filter_module->is_term_selected_for_exclusion( $loop_widget_settings, $term, $skin )
			|| $loop_filter_module->should_exclude_term_by_manual_selection( $loop_widget_settings, $term, $this->get_settings_for_display( 'taxonomy' ), $skin );
	}

	/** URL base de la página (la de PRO, `Pagination_Trait::get_base_url()`): la usa el script en la página 2 o más. */
	private function base_url(): string {
		if ( is_page() || is_single() ) {
			return (string) get_permalink();
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$object = get_queried_object();
			$link   = $object ? get_term_link( $object->term_id, $object->taxonomy ) : '';
			return is_string( $link ) ? $link : home_url( '/' );
		}
		if ( is_search() ) {
			return get_search_link();
		}
		if ( is_author() ) {
			return get_author_posts_url( (int) get_the_author_meta( 'ID' ) );
		}
		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( get_post_type() );
		}
		if ( ! is_front_page() && is_home() ) {
			return (string) get_permalink( get_option( 'page_for_posts' ) );
		}
		return home_url( '/' );
	}

	protected function render() {
		$settings               = $this->get_settings_for_display();
		$selected_element       = (string) ( $settings['selected_element'] ?? '' );
		$user_selected_taxonomy = (string) ( $settings['taxonomy'] ?? '' );
		$terms                  = '' !== $user_selected_taxonomy ? $this->get_filtered_taxonomy_terms( $user_selected_taxonomy, $settings ) : array();

		if ( $this->has_empty_results( $selected_element, $user_selected_taxonomy, $terms ) ) {
			return;
		}

		$loop_filter_module = Plugin::instance()->modules_manager->get_modules( 'loop-filter' );
		if ( ! $loop_filter_module ) {
			return;
		}
		$active_filters       = array();
		$query_string_filters = $loop_filter_module->get_query_string_filters();
		if ( array_key_exists( $selected_element, $query_string_filters ) && isset( $query_string_filters[ $selected_element ]['taxonomy'] ) ) {
			$active_filters = (array) $query_string_filters[ $selected_element ]['taxonomy'];
		}

		$buttons              = '';
		$active_terms         = 0;
		$total_taxonomies     = 0;
		$number_of_taxonomies = $settings['number_of_taxonomies'] ?? '';

		foreach ( (array) $terms as $term ) {
			$total_taxonomies++;
			$pressed = 'false';
			if ( ! isset( $term->taxonomy ) || $this->is_term_excluded_by_query_control( $term, $loop_filter_module ) ) {
				continue;
			}
			if ( array_key_exists( $term->taxonomy, $active_filters ) && in_array( urldecode( $term->slug ), (array) ( $active_filters[ $term->taxonomy ]['terms'] ?? array() ), true ) ) {
				$pressed = 'true';
				$active_terms++;
			}
			if ( ! empty( $number_of_taxonomies ) && $total_taxonomies > $number_of_taxonomies ) {
				continue;
			}
			// El mismo filtro que PRO: fichas con caracteres no latinos.
			$slug     = apply_filters( 'editable_slug', $term->slug, $term );
			$buttons .= '<button type="button" class="digi-tfilter__item" data-filter="' . esc_attr( $slug ) . '" aria-pressed="' . $pressed . '">' . esc_html( $term->name ) . '</button>';
		}

		$first = '';
		if ( 'yes' === ( $settings['show_first_item'] ?? '' ) ) {
			$first = '<button type="button" class="digi-tfilter__item" data-filter="' . self::ALL . '" aria-pressed="' . ( 0 === $active_terms ? 'true' : 'false' ) . '">' . wp_kses_post( (string) ( $settings['first_item_title'] ?? '' ) ) . '</button>';
		}

		$taxonomy = get_taxonomy( $user_selected_taxonomy );
		$multiple = 'yes' === ( $settings['multiple_selection'] ?? '' );
		$join     = $multiple && in_array( $settings['logical_combination'] ?? '', array( 'AND', 'OR' ), true ) ? $settings['logical_combination'] : 'DISABLED';
		$attrs    = array(
			'class'           => 'digi-tfilter',
			'role'            => 'search',
			/* translators: %s: nombre de la taxonomía. */
			'aria-label'      => sprintf( __( 'Filtrar por %s', 'pro-elements' ), $taxonomy ? $taxonomy->labels->singular_name : $user_selected_taxonomy ),
			'data-loop'       => $selected_element,
			'data-taxonomy'   => $user_selected_taxonomy,
			'data-join'       => $join,
			'data-base-url'   => $this->base_url(),
			'data-page-num'   => (string) max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
			'data-loading'    => __( 'Cargando resultados…', 'pro-elements' ),
			'data-none'       => __( 'No hay resultados.', 'pro-elements' ),
			/* translators: %d: número de resultados. */
			'data-many'       => __( '%d resultados.', 'pro-elements' ),
		);
		$this->add_render_attribute( 'filter-bar', $attrs );
		echo '<search ' . $this->get_render_attribute_string( 'filter-bar' ) . '>' . $first . $buttons . '</search>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
	}

	/**
	 * Términos de la taxonomía elegida, con el código de PRO (`Taxonomy_Filter_Trait::get_filtered_taxonomies()`).
	 *
	 * @return array|null
	 */
	private function get_filtered_taxonomy_terms( string $user_selected_taxonomy, array $settings ) {
		$display_settings     = $this->get_settings_for_display();
		$settings['taxonomy'] = $user_selected_taxonomy;
		return $this->get_filtered_taxonomies( $settings, $display_settings );
	}
}
