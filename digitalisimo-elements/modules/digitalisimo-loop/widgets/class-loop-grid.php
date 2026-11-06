<?php
namespace Digitalisimo\Elements\Loop\Widgets;

use Digitalisimo\Elements\Loop\Loop_Base;
use Digitalisimo\Elements\Loop\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Cuadrícula de bucle (`digitalisimo-loop-grid`, C5, frente J; `docs/pro/c5.md`): sustituye al Loop Grid de PRO
 * (`modules/loop-builder/widgets/loop-grid.php`, mapa `modules/digitalisimo-convert/maps/loop-grid.php`).
 *
 * - **Controles**: los del Loop Grid (columnas, elementos por página, mampostería, misma altura, plantillas alternas,
 *   mensaje sin resultados, separaciones), los de su base (`Loop_Base`: plantilla, pieles y su consulta, paginación de
 *   la base común) con el mismo nombre, valor por defecto y condiciones. Textos, selectores, marcado y hoja propios.
 * - **Marcado**: `div.digi-loop-grid__items[role=list][data-digi-items]` (con `data-digi-masonry` en mampostería) con
 *   cada Elemento de bucle (`.e-loop-item`, el documento del Theme Builder) como `role="listitem"`; después, el pie de
 *   la base común (`render_pagination()`: números y anterior/siguiente con enlaces reales, «Cargar más» con un botón,
 *   infinita). Sin resultados, `div.digi-loop-grid__nothing` con el mensaje si está activo.
 * - **Recursos**: `assets/css/loop-grid.css` (rejilla con `--grid-columns`, separaciones por defecto de PRO y mensaje)
 *   más la hoja compartida si pagina; el script compartido (`loop.js`) con «Cargar más», infinita o mampostería, y
 *   `assets/js/loop-grid.js` sólo con la paginación sin recargar (`pagination_load_type = ajax`): pide el enlace (la
 *   misma URL que PRO) y sustituye el contenido del widget, sin jQuery.
 *
 * Derivado de PRO Elements (GPL): `modules/loop-builder/widgets/loop-grid.php`.
 */
class Loop_Grid extends Loop_Base {
	const NAME = 'digitalisimo-loop-grid';

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Cuadrícula de bucle', 'pro-elements' );
	}

	public function get_keywords() {
		return array( 'bucle', 'loop', 'cuadrícula', 'grid', 'dinámico', 'listado', 'archivo', 'blog', 'entradas', 'productos', 'portafolio', 'consulta', 'digitalisimo' );
	}

	public function get_icon() {
		return 'eicon-loop-builder';
	}

	public function has_widget_inner_wrapper(): bool {
		return true;
	}

	/** El script compartido también con mampostería. */
	protected function needs_script(): bool {
		return parent::needs_script() || ( 'yes' === $this->get_settings( 'masonry' ) && 1 !== (int) $this->get_settings( 'columns' ) );
	}

	/** El compartido (si hace falta) y el propio sólo con la paginación sin recargar. */
	public function get_script_depends(): array {
		$scripts = parent::get_script_depends();
		// En el editor y su vista previa, primero: ahí el núcleo pide los recursos al widget registrado, sin ajustes
		// (`Widgets_Manager::enqueue_widgets_scripts()`), y leerlos fallaría.
		if ( self::is_editor() || ( 'ajax' === $this->get_settings( 'pagination_load_type' ) && in_array( (string) $this->get_settings( 'pagination_type' ), array( 'numbers', 'prev_next', 'numbers_and_prev_next' ), true ) ) ) {
			$scripts = array_merge( $scripts, Module::script_depends( self::NAME ) );
		}
		return $scripts;
	}

	protected function register_layout_section() {
		parent::register_layout_section();

		$this->start_injection(
			array(
				'of' => 'template_id',
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'              => esc_html__( 'Columnas', 'pro-elements' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => '3',
				'tablet_default'     => '2',
				'mobile_default'     => '1',
				'min'                => 1,
				'max'                => 12,
				'prefix_class'       => 'elementor-grid%s-',
				'frontend_available' => true,
				'separator'          => 'before',
				'condition'          => array(
					'template_id!' => '',
				),
				'selectors'          => array(
					'{{WRAPPER}}' => '--grid-columns: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'     => esc_html__( 'Elementos por página', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 6,
				'min'       => 1,
				'condition' => array(
					'template_id!' => '',
				),
			)
		);

		$this->add_control(
			'masonry',
			array(
				'label'              => esc_html__( 'Mampostería', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'condition'          => array(
					'columns!'     => 1,
					'template_id!' => '',
				),
				'render_type'        => 'ui',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'equal_height',
			array(
				'label'     => esc_html__( 'Misma altura', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'condition' => array(
					'columns!'     => 1,
					'template_id!' => '',
					'masonry'      => '',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-loop-grid__items' => 'grid-auto-rows: 1fr',
					// `.elementor-section-wrap` sólo existe al editar la plantilla del bucle en su sitio.
					'{{WRAPPER}} .e-loop-item > .elementor-section, {{WRAPPER}} .e-loop-item > .elementor-section > .elementor-container, {{WRAPPER}} .e-loop-item > .e-con, {{WRAPPER}} .e-loop-item .elementor-section-wrap  > .e-con' => 'height: 100%',
				),
			)
		);

		$this->add_control(
			'alternate_template',
			array(
				'label'              => esc_html__( 'Aplicar una plantilla alterna', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'condition'          => array(
					'posts_per_page!' => 1,
					'template_id!'    => '',
					'_skin!'          => array( self::SKIN_POST_TAXONOMY, self::SKIN_PRODUCT_TAXONOMY ),
				),
				'render_type'        => 'template',
				'frontend_available' => true,
				'separator'          => 'before',
			)
		);

		$repeater = new Repeater();

		$repeater->add_control( 'template_id', $this->template_control( true ) );

		$repeater->add_control(
			'repeat_template',
			array(
				'label'     => esc_html__( 'Posición en la cuadrícula', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'condition' => array(
					'template_id!' => '',
				),
			)
		);

		$repeater->add_control(
			'repeat_template_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Nota: la plantilla alterna se repite cada ese número de elementos.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array(
					'template_id!' => '',
				),
			)
		);

		$repeater->add_control(
			'show_once',
			array(
				'label'       => esc_html__( 'Aplicar una vez', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'label_off'   => esc_html__( 'No', 'pro-elements' ),
				'label_on'    => esc_html__( 'Sí', 'pro-elements' ),
				'condition'   => array(
					'template_id!' => '',
				),
				'render_type' => 'template',
			)
		);

		$repeater->add_responsive_control(
			'column_span',
			array(
				'label'     => esc_html__( 'Columnas que ocupa', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '1',
				'options'   => array_combine( array_map( 'strval', range( 1, 12 ) ), array_map( 'strval', range( 1, 12 ) ) ),
				'condition' => array(
					'template_id!' => '',
				),
				'selectors' => array(
					'{{WRAPPER}} {{CURRENT_ITEM}}' => 'grid-column: span min( {{VALUE}}, var(--grid-columns) );',
				),
			)
		);

		$repeater->add_control(
			'column_span_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Nota: el elemento ocupará ese número de columnas.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array(
					'template_id!' => '',
				),
			)
		);

		$repeater->add_control(
			'column_span_masonry_note',
			array(
				'type'       => Controls_Manager::ALERT,
				'alert_type' => 'warning',
				'content'    => esc_html__( 'Nota: la mampostería junto con «Columnas que ocupa» puede dar resultados inesperados y romper la disposición.', 'pro-elements' ),
				'condition'  => array(
					'column_span!' => '1',
				),
			)
		);

		$repeater->add_control(
			'static_position',
			array(
				'label'       => esc_html__( 'Posición fija', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_off'   => esc_html__( 'No', 'pro-elements' ),
				'label_on'    => esc_html__( 'Sí', 'pro-elements' ),
				'condition'   => array(
					'template_id!' => '',
				),
				'render_type' => 'template',
			)
		);

		$repeater->add_control(
			'static_position_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Nota: los elementos fijos se quedan en su sitio cuando se añaden más elementos a la cuadrícula; los demás siguen el orden de la consulta.', 'pro-elements' ),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array(
					'static_position!' => '',
					'template_id!'     => '',
				),
			)
		);

		$this->add_control(
			'alternate_templates',
			array(
				'label'       => esc_html__( 'Plantillas', 'pro-elements' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => esc_html__( 'Plantilla alterna', 'pro-elements' ),
				'condition'   => array(
					'alternate_template' => 'yes',
					'posts_per_page!'    => 1,
					'template_id!'       => '',
				),
				'default'     => array(
					array(
						'template_id' => null,
					),
				),
			)
		);

		// Dónde se pone el asa «Editar plantilla» en el editor.
		$this->add_control(
			'edit_handle_selector',
			array(
				'label'              => esc_html__( 'Selector del asa de edición', 'pro-elements' ),
				'type'               => Controls_Manager::HIDDEN,
				'default'            => '[data-elementor-type="loop-item"]',
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->end_injection();
	}

	protected function register_additional_options_section_controls() {
		$this->start_controls_section(
			'section_additional_options',
			array(
				'label'     => esc_html__( 'Opciones adicionales', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => array(
					'template_id!' => '',
				),
			)
		);

		$this->add_control(
			'enable_nothing_found_message',
			array(
				'label'       => esc_html__( 'Mensaje sin resultados', 'pro-elements' ),
				'description' => esc_html__( 'Aparece cuando la cuadrícula no carga ningún contenido.', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'label_off'   => esc_html__( 'No', 'pro-elements' ),
				'label_on'    => esc_html__( 'Sí', 'pro-elements' ),
			)
		);

		$this->add_control(
			'nothing_found_message_text',
			array(
				'type'      => Controls_Manager::TEXTAREA,
				'ai'        => array(
					'type' => 'text',
				),
				'dynamic'   => array(
					'active' => true,
				),
				'condition' => array(
					'enable_nothing_found_message' => 'yes',
				),
				'default'   => esc_html__( 'Parece que no encontramos lo que buscas.', 'pro-elements' ),
			)
		);

		$this->add_responsive_control(
			'nothing_found_message_align',
			array(
				'label'       => esc_html__( 'Alineación', 'pro-elements' ),
				'type'        => Controls_Manager::CHOOSE,
				'options'     => array(
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors'   => array(
					'{{WRAPPER}}' => '--digi-loop-nothing-align: {{VALUE}};',
				),
				'condition'   => array(
					'enable_nothing_found_message' => 'yes',
				),
				'render_type' => 'ui',
			)
		);

		$this->add_control(
			'nothing_found_message_html_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
				'default'   => 'div',
				'condition' => array(
					'enable_nothing_found_message' => 'yes',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_design_layout_controls() {
		$this->start_controls_section(
			'section_design_layout',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$range = array(
			'px'  => array( 'max' => 100 ),
			'em'  => array( 'max' => 10 ),
			'rem' => array( 'max' => 10 ),
		);

		$this->add_responsive_control(
			'column_gap',
			array(
				'label'      => esc_html__( 'Separación entre columnas', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => $range,
				'selectors'  => array(
					'{{WRAPPER}}' => '--grid-column-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label'              => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', 'rem', 'custom' ),
				'range'              => $range,
				'frontend_available' => true,
				// `--digi-masonry-gap`: la separación de la mampostería, el número del ajuste en px como en PRO (`loop.js`).
				'selectors'          => array(
					'{{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --digi-masonry-gap: {{SIZE}}px',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_design_nothing_found_message_controls() {
		$this->start_controls_section(
			'section_nothing_found_message_design',
			array(
				'label'     => esc_html__( 'Mensaje sin resultados', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'template_id!'                 => '',
					'enable_nothing_found_message' => 'yes',
				),
			)
		);

		$range = array(
			'px'  => array( 'max' => 200 ),
			'em'  => array( 'max' => 20 ),
			'rem' => array( 'max' => 20 ),
		);

		$this->add_responsive_control(
			'nothing_found_message_space_from_top',
			array(
				'label'      => esc_html__( 'Espacio arriba', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => $range,
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-loop-nothing-top: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'nothing_found_message_space_from_bottom',
			array(
				'label'      => esc_html__( 'Espacio abajo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => $range,
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-loop-nothing-bottom: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$text = '{{WRAPPER}} .digi-loop-grid__nothing-text';

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'nothing_found_message_typography',
				'selector'  => $text,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'nothing_found_message_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-loop-nothing-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'nothing_found_message_text_shadow',
				'selector' => $text,
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'nothing_found_message_text_stroke',
				'selector' => $text,
			)
		);

		$this->end_controls_section();
	}

	/** Sin resultados: el contenedor del mensaje (vacío si el mensaje está apagado), como PRO. */
	public function render_nothing_found() {
		$settings = $this->get_settings_for_display();
		echo '<div class="digi-loop-grid__nothing">';
		if ( 'yes' === ( $settings['enable_nothing_found_message'] ?? '' ) ) {
			$tag = Utils::validate_html_tag( (string) ( $settings['nothing_found_message_html_tag'] ?? 'div' ) );
			echo '<' . $tag . ' class="digi-loop-grid__nothing-text">' . wp_kses_post( (string) ( $settings['nothing_found_message_text'] ?? '' ) ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta validada.
		}
		echo '</div>';
	}

	public function render_loop_header() {
		$settings = $this->get_settings_for_display();
		$attrs    = array(
			'class'           => 'digi-loop-grid__items',
			'role'            => 'list',
			'data-digi-items' => '',
		);
		if ( 'yes' === ( $settings['masonry'] ?? '' ) && 1 !== (int) ( $settings['columns'] ?? 3 ) ) {
			$attrs['data-digi-masonry'] = '';
		}
		/** El mismo filtro que PRO para los atributos del contenedor del bucle. */
		$attrs = (array) apply_filters( 'elementor/skin/loop_header_attributes', $attrs );
		$this->add_render_attribute( 'digi-loop-items', $attrs, null, true );
		echo '<div ' . $this->get_render_attribute_string( 'digi-loop-items' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados.
	}

	public function render_loop_footer() {
		echo '</div>';
		$skin = (string) $this->get_settings( '_skin' );
		if ( in_array( $skin, array( self::SKIN_POST_TAXONOMY, self::SKIN_PRODUCT_TAXONOMY ), true ) ) {
			return;
		}
		$this->render_pagination();
	}

	/** Cada Elemento de bucle es un elemento de la lista. */
	protected function loop_item_attributes( array $attributes ): array {
		$attributes['role'] = 'listitem';
		return $attributes;
	}

	/** La paginación sin recargar la marca el contenedor para `loop-grid.js` (y el desplazamiento al bucle). */
	public function render_pagination() {
		$settings = $this->get_settings_for_display();
		$ajax     = 'ajax' === ( $settings['pagination_load_type'] ?? '' ) && in_array( (string) ( $settings['pagination_type'] ?? '' ), array( 'numbers', 'prev_next', 'numbers_and_prev_next' ), true );
		if ( ! $ajax ) {
			parent::render_pagination();
			return;
		}
		ob_start();
		parent::render_pagination();
		$html = (string) ob_get_clean();
		$data = ' data-digi-ajax="' . esc_attr( 'yes' === ( $settings['auto_scroll'] ?? '' ) ? 'scroll' : '1' ) . '" data-digi-loaded="' . esc_attr__( 'Página %d cargada.', 'pro-elements' ) . '"';
		echo preg_replace( '/<nav class="digi-loop__pagination"/', '<nav class="digi-loop__pagination"' . $data, $html, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pie de la base, ya escapado.
	}

	/** La plantilla del bucle al importar (como PRO), además de las etiquetas dinámicas del núcleo. */
	public static function on_import_update_dynamic_content( array $element_config, array $data, $controls = null ): array {
		$element_config = parent::on_import_update_dynamic_content( $element_config, $data, $controls );
		if ( isset( $element_config['settings']['template_id'] ) && isset( $data['post_ids'] ) ) {
			$element_config['settings']['template_id'] = $data['post_ids'][ $element_config['settings']['template_id'] ] ?? 0;
		}
		return $element_config;
	}
}
