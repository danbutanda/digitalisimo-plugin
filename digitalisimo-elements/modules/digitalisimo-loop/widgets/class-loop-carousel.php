<?php
namespace Digitalisimo\Elements\Loop\Widgets;

use Digitalisimo\Elements\Carousel\Engine;
use Digitalisimo\Elements\Carousel\Nested_Controls;
use Digitalisimo\Elements\Loop\Loop_Base;
use Digitalisimo\Elements\Loop\Module;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Carrusel de bucle (`digitalisimo-loop-carousel`, C5, frente J; `docs/pro/c5.md`): sustituye al Loop Carousel de PRO
 * (`modules/loop-builder/widgets/loop-carousel.php`, mapa `modules/digitalisimo-convert/maps/loop-carousel.php`).
 *
 * - **Controles**: los del Loop Carousel (número de diapositivas, separación) y los de carrusel de PRO a través de
 *   `Nested_Controls` (C1), con los mismos parámetros, más los de `Loop_Base` (plantilla, pieles y su consulta). Como
 *   en PRO, la paginación es la del carrusel (puntos, fracción o barra), no la de las entradas.
 * - **Marcado**: el del motor `digi-crs` (`Engine::open()`/`close()`) con cada Elemento de bucle (`.e-loop-item`)
 *   directamente en la pista como diapositiva (`digi-crs__slide`, `role="group"`, «N de M»), por el mismo filtro que
 *   PRO (`elementor/document/wrapper_attributes`) mientras dura el bucle.
 * - **Recursos**: la hoja y el script del motor (`Engine::HANDLE`) y `assets/css/loop-carousel.css` (valores por
 *   defecto del Loop Carousel de PRO); sin script propio: el del motor ya hace todo lo que el bucle necesita.
 *
 * Derivado de PRO Elements (GPL): `modules/loop-builder/widgets/loop-carousel.php`.
 */
class Loop_Carousel extends Loop_Base {
	use Nested_Controls;

	const NAME = 'digitalisimo-loop-carousel';

	/** El CSS dinámico de cada elemento, después del carrusel: cada hijo de la pista es una diapositiva. */
	public $defer_dynamic_css = true;

	/** @var int Diapositiva en curso mientras dura el bucle. */
	private $slide_index = 0;

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Carrusel de bucle', 'pro-elements' );
	}

	public function get_keywords() {
		return array( 'bucle', 'loop', 'carrusel', 'carousel', 'dinámico', 'listado', 'archivo', 'blog', 'entradas', 'productos', 'consulta', 'digitalisimo' );
	}

	public function get_icon() {
		return 'eicon-carousel-loop';
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return array_merge( array( Engine::HANDLE ), Module::style_depends( self::NAME ) );
	}

	public function get_script_depends(): array {
		return array( Engine::HANDLE );
	}

	public function register_settings_section_controls() {
		$this->add_carousel_settings_controls(
			array(
				'css_prefix'               => '',
				'autoplay_custom_settings' => array(
					'description' => esc_html__( 'La reproducción automática no corre mientras editas: mírala en la vista previa. Con ella se muestra el botón «Pausar».', 'pro-elements' ),
				),
			)
		);
	}

	public function register_navigation_section_controls() {
		$this->add_carousel_navigation_controls(
			array(
				'css_prefix'                 => '',
				'navigation_custom_settings' => array(
					'condition' => array(
						'template_id!' => '',
					),
				),
			)
		);
	}

	/** Como PRO: la paginación del carrusel sustituye a la de las entradas. */
	public function register_pagination_section_controls() {
		$this->add_carousel_pagination_controls(
			array(
				'css_prefix'                  => '',
				'section_carousel_pagination' => array(
					'condition' => array(
						'template_id!' => '',
					),
				),
			)
		);
	}

	public function register_design_layout_controls() {
		$this->start_controls_section(
			'section_layout_style',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'image_spacing_custom',
			array(
				'label'              => esc_html__( 'Separación entre diapositivas', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px' ),
				'range'              => array(
					'px' => array( 'max' => 400 ),
				),
				'default'            => array( 'size' => 10 ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'selectors'          => array(
					'{{WRAPPER}}' => '--digi-crs-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function register_design_navigation_controls() {
		$this->add_carousel_navigation_styling_controls(
			array(
				'css_prefix'                         => '',
				'navigation_styling_custom_settings' => array(
					'condition' => array(
						'arrows'       => 'yes',
						'template_id!' => '',
					),
				),
			)
		);
	}

	public function register_design_pagination_controls() {
		$this->add_carousel_pagination_style_controls( array( 'css_prefix' => '' ) );
	}

	protected function register_layout_section() {
		parent::register_layout_section();

		$this->start_injection(
			array(
				'of' => 'template_id',
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'     => esc_html__( 'Número de diapositivas', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 6,
				'min'       => 1,
				'condition' => array(
					'template_id!' => '',
				),
				'separator' => 'before',
			)
		);

		$condition = array(
			'posts_per_page!' => 1,
			'template_id!'    => '',
		);

		$this->add_carousel_layout_controls(
			array(
				'css_prefix'                       => '',
				'slides_to_show_custom_settings'   => array(
					'default'              => '3',
					'widescreen_default'   => '3',
					'laptop_default'       => '3',
					'tablet_extra_default' => '3',
					'tablet_default'       => '2',
					'mobile_extra_default' => '2',
					'mobile_default'       => '1',
					'condition'            => $condition,
					'selectors'            => array(
						'{{WRAPPER}}' => '--digi-crs-view: {{VALUE}}',
					),
				),
				'slides_to_scroll_custom_settings' => array(
					'default'   => '1',
					'condition' => $condition,
				),
				'equal_height_custom_settings'     => array(
					'condition' => $condition,
					'selectors' => array(
						'{{WRAPPER}}'                                   => '--digi-crs-align: stretch;',
						'{{WRAPPER}} .digi-crs__slide > .elementor-element' => 'height: 100%',
					),
				),
				'slides_on_display'                => 8,
			)
		);

		// Dónde se pone el asa «Editar plantilla» en el editor.
		$this->add_control(
			'edit_handle_selector',
			array(
				'label'              => esc_html__( 'Selector del asa de edición', 'pro-elements' ),
				'type'               => Controls_Manager::HIDDEN,
				'default'            => '.digi-crs__track',
				'render_type'        => 'none',
				'frontend_available' => true,
			)
		);

		$this->end_injection();
	}

	public function render_loop_header() {
		$this->slide_index           = 0;
		$this->num_of_carousel_items = $this->loop_count();
		$args                        = $this->carousel_args( $this->get_settings_for_display() );
		$args['classes']             = array( 'digi-loop-carousel' );
		echo Engine::open( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado del motor, escapado allí.
	}

	public function render_loop_footer() {
		echo Engine::close() . $this->deferred_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cierre fijo y `<style>` del CSS dinámico del núcleo.
		$this->deferred_css = '';
	}

	/** Cada Elemento de bucle es una diapositiva de la pista. */
	protected function loop_item_attributes( array $attributes ): array {
		$slide = Engine::slide_attribute_array( $this->slide_index++, max( 1, (int) $this->num_of_carousel_items ) );
		$attributes['class'] = trim( ( $attributes['class'] ?? '' ) . ' ' . $slide['class'] );
		unset( $slide['class'] );
		return array_merge( $attributes, $slide );
	}
}
