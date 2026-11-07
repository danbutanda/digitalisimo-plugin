<?php
namespace Digitalisimo\Elements\Carousel;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los carruseles propios con repetidor de diapositivas: multimedia, testimonios y reseñas (C1, `docs/pro/c1.md`
 * §1.4). Repite `modules/carousel/widgets/base.php` de PRO: mismas secciones, mismos controles con el mismo nombre, el
 * mismo valor por defecto, las mismas condiciones y opciones, y los mismos métodos abstractos, así que el mapa de cada
 * widget es casi todo `keep`. Lo propio: los textos, los selectores (variables de `Engine`, documentadas en su
 * cabecera), el marcado (`Engine`), sin `prefix_class` (las posiciones son modificadores de la raíz, que no alcanzan a un
 * carrusel anidado), y los recursos (`Engine::HANDLE` + la hoja del widget; nada de Swiper ni de PRO).
 *
 * Para las subclases:
 * - `add_repeater_controls( Repeater $repeater )`, `get_repeater_defaults()` y `print_slide( array $slide, array
 *   $settings, $element_key )`, como en PRO.
 * - `render()` llama a `print_slider()`; `print_slider( ?array $settings )` acepta, como PRO, `container_class` (clases
 *   de la raíz) y, además, `engine` (arreglo que se suma a los argumentos de `Engine::open()`: `id`, `sync`, `center`,
 *   `classes`…) y `slide_class` (clases de cada diapositiva). `slider_args( array $settings )` traduce los ajustes a
 *   `Engine::open()` (redefinible).
 * - Imágenes: `get_slide_image_url()` y `get_slide_image_alt_attribute()` como PRO, y `get_slide_image()`, la etiqueta
 *   `<img>` con `wp_get_attachment_image()` (tamaño de `image_size`, `srcset`, `decoding`, `loading`), y `preloader()`,
 *   el indicador de la «Carga diferida» que va junto a ella.
 * - El efecto «Cubo» de PRO (sólo en el Carrusel multimedia) lo pintan `carousel-3d.css|js` sobre `slide`.
 */
abstract class Base_Widget extends \Elementor\Widget_Base {

	/** @var int Diapositivas impresas en esta petición (clave única de cada una, como PRO). */
	private $slide_prints_count = 0;

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_style_depends(): array {
		return array_merge( Module::style_depends( $this->get_name() ), $this->extra_depends() );
	}

	public function get_script_depends(): array {
		return array_merge( Module::script_depends( $this->get_name() ), $this->extra_depends() );
	}

	/** Recursos opcionales del motor que usan los ajustes (`Module::extra_depends()`); en el editor, todos. */
	protected function extra_depends(): array {
		$all = method_exists( $this, 'is_type_instance' ) && $this->is_type_instance();
		return Module::extra_depends( $all ? array() : (array) $this->get_settings(), $all );
	}

	/**
	 * Indicador de la «Carga diferida» junto a la imagen, como el `swiper-lazy-preloader` que PRO pintaba con la opción:
	 * el aro girando de Swiper (`carousel-lazy.css`), que `carousel-lazy.js` retira al cargar la imagen. Decorativo.
	 */
	protected static function preloader( array $settings ): string {
		return 'yes' === ( $settings['lazyload'] ?? '' ) ? '<span class="digi-crs__preloader" aria-hidden="true"></span>' : '';
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	abstract protected function add_repeater_controls( Repeater $repeater );

	abstract protected function get_repeater_defaults();

	abstract protected function print_slide( array $slide, array $settings, $element_key );

	protected function register_controls() {
		$this->start_controls_section(
			'section_slides',
			array(
				'label' => esc_html__( 'Diapositivas', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'slides_name',
			array(
				'label'   => esc_html__( 'Nombre del carrusel', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Diapositivas', 'pro-elements' ),
			)
		);

		$repeater = new Repeater();

		$this->add_repeater_controls( $repeater );

		$this->add_control(
			'slides',
			array(
				'label'     => esc_html__( 'Diapositivas', 'pro-elements' ),
				'type'      => Controls_Manager::REPEATER,
				'fields'    => $repeater->get_controls(),
				'default'   => $this->get_repeater_defaults(),
				'separator' => 'after',
			)
		);

		$this->add_control(
			'effect',
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Efecto', 'pro-elements' ),
				'default'            => 'slide',
				'options'            => array(
					'slide' => esc_html__( 'Deslizar', 'pro-elements' ),
					'fade'  => esc_html__( 'Fundido', 'pro-elements' ),
					'cube'  => esc_html__( 'Cubo', 'pro-elements' ),
				),
				'frontend_available' => true,
			)
		);

		$slides_per_view = range( 1, 10 );
		$slides_per_view = array_combine( $slides_per_view, $slides_per_view );

		// «Predeterminado» en PRO lo resolvía su script por dispositivo (y nunca heredaba el valor del escritorio en tableta
		// o móvil); aquí son los valores por defecto de cada dispositivo activo, que el núcleo pinta en su consulta de medios.
		$view_defaults   = array();
		$scroll_defaults = array();
		$per_view        = $this->slides_per_view_defaults();
		foreach ( self::active_devices() as $device ) {
			$view_defaults[ $device . '_default' ] = (string) ( $per_view[ $device ] ?? $per_view['desktop'] ?? '1' );
			if ( 'desktop' !== $device ) {
				$scroll_defaults[ $device . '_default' ] = '1';
			}
		}

		$this->add_responsive_control(
			'slides_per_view',
			$view_defaults + array(
				'type'                 => Controls_Manager::SELECT,
				'label'                => esc_html__( 'Diapositivas visibles', 'pro-elements' ),
				'options'              => array( '' => esc_html__( 'Predeterminado', 'pro-elements' ) ) + $slides_per_view,
				'inherit_placeholders' => false,
				'condition'            => array(
					'effect' => 'slide',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '--digi-crs-view: {{VALUE}}',
				),
				'frontend_available'   => true,
			)
		);

		$this->add_responsive_control(
			'slides_to_scroll',
			$scroll_defaults + array(
				'type'                 => Controls_Manager::SELECT,
				'label'                => esc_html__( 'Diapositivas por paso', 'pro-elements' ),
				'description'          => esc_html__( 'Cuántas diapositivas avanza cada paso.', 'pro-elements' ),
				'options'              => array( '' => esc_html__( 'Predeterminado', 'pro-elements' ) ) + $slides_per_view,
				'inherit_placeholders' => false,
				'condition'            => array(
					'effect' => 'slide',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '--digi-crs-scroll: {{VALUE}}',
				),
				'frontend_available'   => true,
			)
		);

		$this->add_responsive_control(
			'height',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 100,
						'max' => 1000,
					),
					'em'  => array(
						'min' => 10,
						'max' => 100,
					),
					'rem' => array(
						'min' => 10,
						'max' => 100,
					),
					'vh'  => array(
						'min' => 20,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'width',
			array(
				'type'       => Controls_Manager::SLIDER,
				'label'      => esc_html__( 'Ancho', 'pro-elements' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 100,
						'max' => 1140,
					),
					'%'  => array(
						'min' => 50,
					),
				),
				'default'    => array(
					'unit' => '%',
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_additional_options',
			array(
				'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ),
			)
		);

		$this->add_control(
			'show_arrows',
			array(
				'type'               => Controls_Manager::SWITCHER,
				'label'              => esc_html__( 'Flechas', 'pro-elements' ),
				'default'            => 'yes',
				'label_off'          => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'           => esc_html__( 'Mostrar', 'pro-elements' ),
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pagination',
			array(
				'label'              => esc_html__( 'Paginación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'bullets',
				'options'            => array(
					''            => esc_html__( 'Ninguna', 'pro-elements' ),
					'bullets'     => esc_html__( 'Puntos', 'pro-elements' ),
					'fraction'    => esc_html__( 'Fracción', 'pro-elements' ),
					'progressbar' => esc_html__( 'Progreso', 'pro-elements' ),
				),
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'speed',
			array(
				'label'              => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 500,
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'              => esc_html__( 'Reproducción automática', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'autoplay_speed',
			array(
				'label'              => esc_html__( 'Intervalo de la reproducción automática', 'pro-elements' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 5000,
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'              => esc_html__( 'Bucle infinito', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pause_on_hover',
			array(
				'label'              => esc_html__( 'Pausar con el puntero encima', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'pause_on_interaction',
			array(
				'label'              => esc_html__( 'Detener tras una interacción', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'condition'          => array(
					'autoplay' => 'yes',
				),
				'frontend_available' => true,
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'image_size',
				'default'   => 'full',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'lazyload',
			array(
				'label'              => esc_html__( 'Carga diferida', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_slides_style',
			array(
				'label' => esc_html__( 'Diapositivas', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$space_between_config = array(
			'label'              => esc_html__( 'Separación', 'pro-elements' ),
			'type'               => Controls_Manager::SLIDER,
			'range'              => array(
				'px' => array(
					'max' => 50,
				),
			),
			'selectors'          => array(
				'{{WRAPPER}}' => '--digi-crs-gap: {{SIZE}}{{UNIT}};',
			),
			'frontend_available' => true,
		);
		foreach ( self::active_devices() as $active_device ) {
			$space_between_config[ $active_device . '_default' ] = array(
				'size' => 10,
			);
		}

		$this->add_responsive_control(
			'space_between',
			$space_between_config
		);

		$this->add_control(
			'slide_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-crs__slide' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'slide_border_size',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-crs__slide' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'slide_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => array(
					'%' => array(
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-crs__slide' => 'border-radius: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'slide_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-crs__slide' => 'border-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'slide_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-crs__slide' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_navigation',
			array(
				'label' => esc_html__( 'Navegación', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_arrows',
			array(
				'label' => esc_html__( 'Flechas', 'pro-elements' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_responsive_control(
			'arrows_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 20,
				),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-arrow-size: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'arrows_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-crs-arrow-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'heading_pagination',
			array(
				'label'     => esc_html__( 'Paginación', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'pagination_position',
			array(
				'label'     => esc_html__( 'Posición', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'outside',
				'options'   => array(
					'outside' => esc_html__( 'Fuera', 'pro-elements' ),
					'inside'  => esc_html__( 'Dentro', 'pro-elements' ),
				),
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'pagination_gap',
			array(
				'label'      => esc_html__( 'Separación entre puntos', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px' => array(
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-dot-gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'pagination' => 'bullets',
				),
			)
		);

		$this->add_responsive_control(
			'pagination_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-crs-dot-size: {{SIZE}}{{UNIT}}; --digi-crs-bar-size: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'pagination_color_inactive',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					// Como en PRO, un color elegido se pinta opaco (el punto inactivo por defecto lleva opacidad .2).
					'{{WRAPPER}}' => '--digi-crs-dot-color: {{VALUE}}; --digi-crs-dot-opacity: 1;',
				),
				'condition' => array(
					'pagination!' => '',
				),
			)
		);

		$this->add_control(
			'pagination_color',
			array(
				'label'        => esc_html__( 'Color activo', 'pro-elements' ),
				'type'         => Controls_Manager::COLOR,
				'selectors'    => array(
					'{{WRAPPER}}' => '--digi-crs-dot-active: {{VALUE}};',
					'{{WRAPPER}} .digi-crs__fraction' => 'color: {{VALUE}}',
				),
				'condition'    => array(
					'pagination!' => '',
				),
				'control_type' => 'content',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Diapositivas visibles por dispositivo cuando el control queda en «Predeterminado»: las que aplicaba el script de PRO
	 * (`carousel/assets/js/frontend/handlers/base.js`: 3, 2 en tableta y 1 en móvil). Los testimonios y las reseñas la
	 * redefinen (PRO: 1 en todos). «Diapositivas por paso» vale 1 en cada dispositivo que no la fija (como PRO).
	 *
	 * @return array<string,string> Dispositivo => valor.
	 */
	protected function slides_per_view_defaults(): array {
		return array( 'widescreen' => '3', 'desktop' => '3', 'laptop' => '3', 'tablet_extra' => '3', 'tablet' => '2', 'mobile_extra' => '2', 'mobile' => '1' );
	}

	/** Dispositivos activos, del mayor al menor (`widescreen`, `desktop`, …), como los recorre PRO. */
	protected static function active_devices(): array {
		$devices = array();
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) && is_object( \Elementor\Plugin::$instance->breakpoints ) ) {
			$devices = array_reverse( array_keys( (array) \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints() ) );
		}
		if ( in_array( 'widescreen', $devices, true ) ) {
			return array_merge( array_slice( $devices, 0, 1 ), array( 'desktop' ), array_slice( $devices, 1 ) );
		}
		return array_merge( array( 'desktop' ), $devices );
	}

	protected function render() {
		$this->print_slider();
	}

	/** Argumentos de `Engine::open()` a partir de los ajustes (redefinible). */
	protected function slider_args( array $settings ): array {
		$autoplay = 'yes' === ( $settings['autoplay'] ?? '' );
		$args     = array(
			'label'               => (string) ( $settings['slides_name'] ?? '' ),
			'count'               => count( (array) ( $settings['slides'] ?? array() ) ),
			'autoplay'            => $autoplay ? max( 0, (int) ( $settings['autoplay_speed'] ?? 5000 ) ) : 0,
			'loop'                => 'yes' === ( $settings['loop'] ?? '' ),
			'hover'               => 'yes' === ( $settings['pause_on_hover'] ?? '' ),
			'interaction'         => 'yes' === ( $settings['pause_on_interaction'] ?? '' ),
			'effect'              => 'fade' === ( $settings['effect'] ?? '' ) ? 'fade' : 'slide',
			'speed'               => (int) ( $settings['speed'] ?? 500 ),
			'pagination'          => (string) ( $settings['pagination'] ?? '' ),
			'pagination_position' => 'inside' === ( $settings['pagination_position'] ?? '' ) ? 'inside' : 'outside',
			'arrows'              => ! empty( $settings['show_arrows'] ),
			'classes'             => (string) ( $settings['container_class'] ?? '' ),
			// `grabCursor` y `slideToClickedSlide` de `getSwiperOptions()` en PRO.
			'grab'                => true,
			'click'               => true,
		);
		return array_merge( $args, (array) ( $settings['engine'] ?? array() ) );
	}

	/**
	 * Pinta el carrusel. Como en PRO, `$settings` puede traer `container_class`; además `engine` (argumentos extra de
	 * `Engine::open()`) y `slide_class` (clases de cada diapositiva).
	 */
	protected function print_slider( ?array $settings = null ) {
		if ( null === $settings ) {
			$settings = $this->get_settings_for_display();
		}
		$settings = array_merge(
			array(
				'container_class' => '',
				'video_play_icon' => true,
			),
			$settings
		);
		$slides = array_values( (array) ( $settings['slides'] ?? array() ) );
		$count  = count( $slides );
		$class  = (string) ( $settings['slide_class'] ?? '' );

		echo Engine::open( $this->slider_args( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Engine escapa.
		foreach ( $slides as $index => $slide ) {
			$this->slide_prints_count++;
			echo '<div ' . Engine::slide_attributes( $index, $count, $class ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$this->print_slide( (array) $slide, $settings, 'slide-' . $index . '-' . $this->slide_prints_count );
			echo '</div>';
		}
		echo Engine::close(); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** URL de la imagen de una diapositiva con el tamaño de `image_size` (como PRO). */
	protected function get_slide_image_url( $slide, array $settings ) {
		$image     = (array) ( $slide['image'] ?? array() );
		$image_url = ! empty( $image['id'] ) ? Group_Control_Image_Size::get_attachment_image_src( $image['id'], 'image_size', $settings ) : '';
		if ( ! $image_url ) {
			$image_url = (string) ( $image['url'] ?? '' );
		}
		return $image_url;
	}

	/** Texto alternativo: el nombre de la diapositiva o el de la imagen (como PRO). */
	protected function get_slide_image_alt_attribute( $slide ) {
		if ( ! empty( $slide['name'] ) ) {
			return $slide['name'];
		}
		if ( ! empty( $slide['image']['alt'] ) ) {
			return $slide['image']['alt'];
		}
		return '';
	}

	/**
	 * `<img>` de la diapositiva: `wp_get_attachment_image()` con el tamaño de `image_size` (con `srcset`), o, con un
	 * tamaño personalizado o sin adjunto, la URL de `get_slide_image_url()`. `decoding="async"`. `loading` lo decide
	 * WordPress (o la optimización de carga de imágenes del núcleo, que lo añade aunque ya exista: por eso aquí no se pasa
	 * nunca con medidas); con «Carga diferida», sólo una imagen sin medidas lleva `loading="lazy"` propio. `$attrs` se
	 * suma (p. ej. `class`).
	 */
	protected function get_slide_image( array $slide, array $settings, array $attrs = array() ): string {
		$image = (array) ( $slide['image'] ?? array() );
		$attrs = array_merge(
			array(
				'alt'      => (string) $this->get_slide_image_alt_attribute( $slide ),
				'decoding' => 'async',
			),
			$attrs
		);
		$lazy  = 'yes' === ( $settings['lazyload'] ?? '' );
		$size  = (string) ( $settings['image_size_size'] ?? 'full' );
		$id    = (int) ( $image['id'] ?? 0 );
		if ( $id && 'custom' !== $size && function_exists( 'wp_get_attachment_image' ) ) {
			$html = (string) wp_get_attachment_image( $id, '' !== $size ? $size : 'full', false, $attrs );
			if ( '' !== $html ) {
				return $html;
			}
		}
		$url = (string) $this->get_slide_image_url( $slide, $settings );
		if ( '' === $url ) {
			return '';
		}
		$attrs = array( 'src' => esc_url( $url ) ) + $attrs;
		if ( 'custom' === $size && ! empty( $settings['image_size_custom_dimension']['width'] ) ) {
			$attrs['width'] = (string) (int) $settings['image_size_custom_dimension']['width'];
			if ( ! empty( $settings['image_size_custom_dimension']['height'] ) ) {
				$attrs['height'] = (string) (int) $settings['image_size_custom_dimension']['height'];
			}
		}
		if ( $lazy && ! isset( $attrs['loading'] ) && ! isset( $attrs['width'], $attrs['height'] ) ) {
			$attrs['loading'] = 'lazy';
		}
		return '<img ' . Engine::attributes( $attrs ) . '>';
	}
}
