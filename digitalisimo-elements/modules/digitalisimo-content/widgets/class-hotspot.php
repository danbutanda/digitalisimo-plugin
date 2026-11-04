<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Puntos de interés: una imagen con puntos marcados encima, cada uno con su texto, su icono, su enlace y su
 * información emergente. Sustituye al Hotspot de PRO (`modules/hotspot/widgets/hotspot.php`, mapa
 * `modules/digitalisimo-convert/maps/hotspot.php`): repite sus controles y los que hereda del widget Imagen del núcleo
 * (los que PRO no quita, con los selectores que PRO les pone) con el mismo nombre, valores por defecto y condiciones.
 *
 * - **Marcado propio** (`digi-hotspots`, `digi-hs__…`): la imagen con `Group_Control_Image_Size` del núcleo
 *   (`wp_get_attachment_image()` con `srcset`), y cada punto con su disparador. Un punto con información emergente es un
 *   `<button>` con `aria-expanded` y `aria-controls`; con enlace, un `<a>` (la información emergente va a su lado y la
 *   describe); sin ninguna de las dos cosas, un `<span>` que no entra en el orden del foco. Un punto sin texto lleva
 *   como nombre «Punto de interés N».
 * - **Información emergente** al pulsar o al pasar el puntero (por dispositivo, como PRO) y también con el foco del
 *   teclado en el modo «al pasar»; Escape la cierra. Script propio `hotspot.js` (`defer`, sin dependencias).
 * - **Animaciones** (latido, expansión, superposición, secuencia y las de la información emergente) con CSS, apagadas
 *   con `prefers-reduced-motion`.
 *
 * Diferencias declaradas con PRO: los puntos son botones o enlaces que se alcanzan con el teclado (PRO los pintaba como
 * `<div>`); la información emergente se abre también con el foco y se cierra con Escape; cerrada queda oculta
 * (`visibility`) además de transparente, así que sus enlaces no reciben el foco; con enlace y «al pasar», la información
 * emergente va junto al enlace y no dentro; los estilos de la imagen no alcanzan a las imágenes de la información
 * emergente; la animación en secuencia aparece aunque la página no cargue la animación de entrada `fadeIn` del núcleo;
 * el texto y la información emergente pasan por `wp_kses_post()`.
 *
 * Deriva de PRO Elements (GPL): `modules/hotspot/widgets/hotspot.php` (controles, textos y marcado),
 * `assets/css/widget-hotspot.min.css` (aspecto por defecto) y `modules/hotspot/assets/js/frontend/handlers/hotspot.js`
 * (disparadores y secuencia); y de los controles de `elementor/includes/widgets/image.php` del núcleo.
 */
class Hotspot extends \Elementor\Widget_Base {
	/** Dispositivos de los controles por dispositivo, en el orden del núcleo. */
	const DEVICES = array( 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' );

	public function get_name() { return 'digitalisimo-hotspot'; }
	public function get_title() { return esc_html__( 'Puntos de interés', 'pro-elements' ); }
	public function get_icon() { return 'eicon-image-hotspot'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'puntos', 'interés', 'imagen', 'información', 'emergente', 'mapa', 'hotspot', 'tooltip', 'dot' ); }
	public function get_style_depends(): array { return Module::style_depends( $this->get_name() ); }
	public function get_script_depends(): array { return Module::script_depends( $this->get_name() ); }
	public function has_widget_inner_wrapper(): bool { return false; }
	protected function is_dynamic_content(): bool { return false; }

	protected function register_controls() {
		$text    = array( 'px', 'em', 'rem', 'custom' );
		$wide    = array( 'px', '%', 'em', 'rem', 'vw', 'custom' );
		$tall    = array( 'px', 'em', 'rem', 'vh', 'custom' );
		$range   = static function ( $px, $em ) {
			return array( 'px' => array( 'max' => $px ), 'em' => array( 'max' => $em ), 'rem' => array( 'max' => $em ) );
		};
		$sides   = array(
			'right'  => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-h-align-left' ),
			'bottom' => array( 'title' => esc_html__( 'Arriba', 'pro-elements' ), 'icon' => 'eicon-v-align-top' ),
			'left'   => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-h-align-right' ),
			'top'    => array( 'title' => esc_html__( 'Abajo', 'pro-elements' ), 'icon' => 'eicon-v-align-bottom' ),
		);
		$place   = 'right: initial;bottom: initial;left: initial;top: initial;{{VALUE}}: calc(100% + 5px );';
		$item    = '{{WRAPPER}} {{CURRENT_ITEM}}';
		$on_off  = array( 'label_off' => esc_html__( 'No', 'pro-elements' ), 'label_on' => esc_html__( 'Sí', 'pro-elements' ) );
		$fit     = array( 'max' => 1, 'min' => 0.10, 'step' => 0.01 );
		$img     = '{{WRAPPER}} .digi-hotspots>img';
		$percent = array( 'unit' => '%' );
		$width_r = array( '%' => array( 'min' => 1, 'max' => 100 ), 'px' => array( 'min' => 1, 'max' => 1000 ), 'vw' => array( 'min' => 1, 'max' => 100 ) );

		// Imagen (del widget Imagen del núcleo, sin leyenda ni enlace, como en PRO).
		$this->start_controls_section( 'section_image', array( 'label' => esc_html__( 'Imagen', 'pro-elements' ) ) );
		$this->add_control(
			'image',
			array(
				'label'   => esc_html__( 'Elegir imagen', 'pro-elements' ),
				'type'    => Controls_Manager::MEDIA,
				'dynamic' => array( 'active' => true ),
				'default' => array( 'url' => class_exists( '\\Elementor\\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '' ),
			)
		);
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'image',
				'default'   => 'large',
				'condition' => array( 'image[url]!' => '' ),
			)
		);
		$this->end_controls_section();

		// Puntos.
		$this->start_controls_section( 'hotspot_section', array( 'label' => esc_html__( 'Puntos de interés', 'pro-elements' ) ) );
		$repeater = new Repeater();
		$repeater->start_controls_tabs( 'hotspot_repeater' );
		$repeater->start_controls_tab( 'hotspot_content_tab', array( 'label' => esc_html__( 'Contenido', 'pro-elements' ) ) );
		$repeater->add_control(
			'hotspot_label',
			array(
				'label'       => esc_html__( 'Texto', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);
		$repeater->add_control(
			'hotspot_link',
			array(
				'label'   => esc_html__( 'Enlace', 'pro-elements' ),
				'type'    => Controls_Manager::URL,
				'dynamic' => array( 'active' => true ),
			)
		);
		$repeater->add_control(
			'hotspot_icon',
			array(
				'label'       => esc_html__( 'Icono', 'pro-elements' ),
				'type'        => Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
			)
		);
		$start = function_exists( 'is_rtl' ) && is_rtl() ? 'right' : 'left';
		$end   = 'right' === $start ? 'left' : 'right';
		$repeater->add_control(
			'hotspot_icon_position',
			array(
				'label'                => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start' => array( 'title' => esc_html__( 'Inicio', 'pro-elements' ), 'icon' => "eicon-h-align-{$start}" ),
					'end'   => array( 'title' => esc_html__( 'Final', 'pro-elements' ), 'icon' => "eicon-h-align-{$end}" ),
				),
				'selectors_dictionary' => array(
					'start' => 'grid-column: 1;',
					'end'   => 'grid-column: 2;',
				),
				'condition'            => array(
					'hotspot_icon[value]!'  => '',
					'hotspot_label[value]!' => '',
				),
				'selectors'            => array( $item . ' .digi-hs__icon' => '{{VALUE}}' ),
				'default'              => 'start',
			)
		);
		$repeater->add_control(
			'hotspot_icon_spacing',
			array(
				'label'      => esc_html__( 'Separación del icono', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $text,
				'range'      => $range( 100, 10 ),
				'default'    => array( 'size' => 5 ),
				'selectors'  => array( $item . ' .digi-hs__button' => 'grid-gap: {{SIZE}}{{UNIT}};' ),
				'condition'  => array(
					'hotspot_icon[value]!'  => '',
					'hotspot_label[value]!' => '',
				),
			)
		);
		$repeater->add_control(
			'hotspot_custom_size',
			array(
				'label'       => esc_html__( 'Tamaño propio del punto', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'no',
				'description' => esc_html__( 'Un tamaño que sólo afecta a este punto.', 'pro-elements' ),
			) + $on_off
		);
		$repeater->add_control(
			'hotspot_width',
			array(
				'label'      => esc_html__( 'Ancho mínimo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $wide,
				'range'      => $range( 1000, 100 ),
				'selectors'  => array( $item => '--hs-min-width: {{SIZE}}{{UNIT}}' ),
				'condition'  => array( 'hotspot_custom_size' => 'yes' ),
			)
		);
		$repeater->add_control(
			'hotspot_height',
			array(
				'label'      => esc_html__( 'Alto mínimo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $tall,
				'range'      => $range( 1000, 100 ),
				'selectors'  => array( $item => '--hs-min-height: {{SIZE}}{{UNIT}}' ),
				'condition'  => array( 'hotspot_custom_size' => 'yes' ),
			)
		);
		$repeater->add_control(
			'hotspot_tooltip_content',
			array(
				'render_type' => 'template',
				'label'       => esc_html__( 'Información emergente', 'pro-elements' ),
				'type'        => Controls_Manager::WYSIWYG,
				'default'     => esc_html__( 'Escribe aquí la información emergente', 'pro-elements' ),
			)
		);
		$repeater->end_controls_tab();
		$repeater->start_controls_tab( 'hotspot_position_tab', array( 'label' => esc_html__( 'Posición', 'pro-elements' ) ) );
		$repeater->add_control(
			'hotspot_horizontal',
			array(
				'label'   => esc_html__( 'Orientación horizontal', 'pro-elements' ),
				'type'    => Controls_Manager::CHOOSE,
				'default' => $start,
				'options' => array(
					'left'  => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-h-align-left' ),
					'right' => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-h-align-right' ),
				),
				'toggle'  => false,
			)
		);
		$repeater->add_responsive_control(
			'hotspot_offset_x',
			array(
				'label'      => esc_html__( 'Desplazamiento', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'default'    => array( 'unit' => '%', 'size' => 50 ),
				'selectors'  => array( $item => '{{hotspot_horizontal.VALUE}}: {{SIZE}}%; --hs-translate-x: {{SIZE}}%;' ),
			)
		);
		$repeater->add_control(
			'hotspot_vertical',
			array(
				'label'   => esc_html__( 'Orientación vertical', 'pro-elements' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => array(
					'top'    => array( 'title' => esc_html__( 'Arriba', 'pro-elements' ), 'icon' => 'eicon-v-align-top' ),
					'bottom' => array( 'title' => esc_html__( 'Abajo', 'pro-elements' ), 'icon' => 'eicon-v-align-bottom' ),
				),
				'default' => 'top',
				'toggle'  => false,
			)
		);
		$repeater->add_responsive_control(
			'hotspot_offset_y',
			array(
				'label'      => esc_html__( 'Desplazamiento', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%' ),
				'default'    => array( 'unit' => '%', 'size' => 50 ),
				'selectors'  => array( $item => '{{hotspot_vertical.VALUE}}: {{SIZE}}%; --hs-translate-y: {{SIZE}}%;' ),
			)
		);
		$repeater->add_control(
			'hotspot_tooltip_position',
			array(
				'label'       => esc_html__( 'Información emergente propia', 'pro-elements' ),
				'type'        => Controls_Manager::SWITCHER,
				'default'     => 'no',
				'description' => esc_html__( 'Una apertura de la información emergente que sólo afecta a este punto.', 'pro-elements' ),
			) + $on_off
		);
		$repeater->add_control(
			'hotspot_heading',
			array(
				'label'     => esc_html__( 'Caja', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array( 'hotspot_tooltip_position' => 'yes' ),
			)
		);
		$repeater->add_responsive_control(
			'hotspot_position',
			array(
				'label'       => esc_html__( 'Posición', 'pro-elements' ),
				'type'        => Controls_Manager::CHOOSE,
				'options'     => $sides,
				'selectors'   => array( $item . ' .digi-hs--tip-pos' => $place ),
				'condition'   => array( 'hotspot_tooltip_position' => 'yes' ),
				'render_type' => 'template',
			)
		);
		$repeater->add_responsive_control(
			'hotspot_tooltip_width',
			array(
				'label'      => esc_html__( 'Ancho mínimo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $wide,
				'range'      => $range( 2000, 200 ),
				'selectors'  => array( $item . ' .digi-hs__tip' => 'min-width: {{SIZE}}{{UNIT}}' ),
				'condition'  => array( 'hotspot_tooltip_position' => 'yes' ),
			)
		);
		$repeater->add_control(
			'hotspot_tooltip_text_wrap',
			array(
				'label'     => esc_html__( 'Ajuste del texto', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array( $item => '--white-space: normal' ),
				'condition' => array( 'hotspot_tooltip_position' => 'yes' ),
			) + $on_off
		);
		$repeater->end_controls_tab();
		$repeater->end_controls_tabs();

		$this->add_control(
			'hotspot',
			array(
				'label'              => esc_html__( 'Puntos de interés', 'pro-elements' ),
				'type'               => Controls_Manager::REPEATER,
				'fields'             => $repeater->get_controls(),
				'title_field'        => '{{{ hotspot_label }}}',
				'default'            => array( array() ),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'hotspot_animation',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'e-hotspot--soft-beat' => esc_html__( 'Latido suave', 'pro-elements' ),
					'e-hotspot--expand'    => esc_html__( 'Expansión', 'pro-elements' ),
					'e-hotspot--overlay'   => esc_html__( 'Superposición', 'pro-elements' ),
					''                     => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'default'   => 'e-hotspot--expand',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'hotspot_sequenced_animation',
			array(
				'label'              => esc_html__( 'Animación en secuencia', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'no',
				'frontend_available' => true,
				'render_type'        => 'none',
			) + $on_off
		);
		$this->add_control(
			'hotspot_sequenced_animation_duration',
			array(
				'label'              => esc_html__( 'Duración de la secuencia', 'pro-elements' ) . ' (ms)',
				'type'               => Controls_Manager::SLIDER,
				'range'              => array( 'px' => array( 'min' => 0, 'max' => 20000, 'step' => 100 ) ),
				'condition'          => array( 'hotspot_sequenced_animation' => 'yes' ),
				'frontend_available' => true,
				'render_type'        => 'ui',
			)
		);
		$this->end_controls_section();

		// Información emergente.
		$this->start_controls_section( 'tooltip_section', array( 'label' => esc_html__( 'Información emergente', 'pro-elements' ) ) );
		$this->add_responsive_control(
			'tooltip_position',
			array(
				'label'              => esc_html__( 'Posición', 'pro-elements' ),
				'type'               => Controls_Manager::CHOOSE,
				'default'            => 'top',
				'toggle'             => false,
				'options'            => $sides,
				'selectors'          => array( '{{WRAPPER}} .digi-hs--tip-pos' => $place ),
				'frontend_available' => true,
			)
		);
		$this->add_responsive_control(
			'tooltip_trigger',
			array(
				'label'              => esc_html__( 'Disparador', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'mouseenter' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
					'click'      => esc_html__( 'Al pulsar', 'pro-elements' ),
					'none'       => esc_html__( 'Siempre visible', 'pro-elements' ),
				),
				'default'            => 'click',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'tooltip_animation',
			array(
				'label'              => esc_html__( 'Animación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'e-hotspot--fade-in-out'     => esc_html__( 'Aparecer y desaparecer', 'pro-elements' ),
					'e-hotspot--fade-grow'       => esc_html__( 'Aparecer creciendo', 'pro-elements' ),
					'e-hotspot--fade-direction'  => esc_html__( 'Aparecer desde la dirección', 'pro-elements' ),
					'e-hotspot--slide-direction' => esc_html__( 'Deslizar desde la dirección', 'pro-elements' ),
				),
				'default'            => 'e-hotspot--fade-in-out',
				'placeholder'        => esc_html__( 'Escribe la leyenda de la imagen', 'pro-elements' ),
				'condition'          => array( 'tooltip_trigger!' => 'none' ),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'tooltip_animation_duration',
			array(
				'label'     => esc_html__( 'Duración de la animación', 'pro-elements' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 10000, 'step' => 100 ) ),
				'selectors' => array( '{{WRAPPER}}' => '--tip-transition-duration: {{SIZE}}ms;' ),
				'condition' => array( 'tooltip_trigger!' => 'none' ),
			)
		);
		$this->end_controls_section();

		// Estilo de la imagen (del widget Imagen del núcleo, con los selectores que les pone PRO).
		$this->start_controls_section( 'section_style_image', array( 'label' => esc_html__( 'Imagen', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'flex-start' => array( 'title' => esc_html__( 'Inicio', 'pro-elements' ), 'icon' => 'eicon-text-align-left' ),
					'center'     => array( 'title' => esc_html__( 'Centro', 'pro-elements' ), 'icon' => 'eicon-text-align-center' ),
					'flex-end'   => array( 'title' => esc_html__( 'Final', 'pro-elements' ), 'icon' => 'eicon-text-align-right' ),
				),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => function_exists( 'is_rtl' ) && is_rtl() ? 'end' : 'start',
					'right' => function_exists( 'is_rtl' ) && is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array( '{{WRAPPER}}' => '--background-align: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'width',
			array(
				'label'          => esc_html__( 'Ancho', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'default'        => $percent,
				'tablet_default' => $percent,
				'mobile_default' => $percent,
				'size_units'     => $wide,
				'range'          => $width_r,
				'selectors'      => array( '{{WRAPPER}}' => '--container-width: {{SIZE}}{{UNIT}}; --image-width: 100%;' ),
			)
		);
		$this->add_responsive_control(
			'space',
			array(
				'label'          => esc_html__( 'Ancho máximo', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'default'        => $percent,
				'tablet_default' => $percent,
				'mobile_default' => $percent,
				'size_units'     => $wide,
				'range'          => $width_r,
				'selectors'      => array( '{{WRAPPER}}' => '--container-max-width: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_responsive_control(
			'height',
			array(
				'label'      => esc_html__( 'Alto', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array( 'px' => array( 'min' => 1, 'max' => 500 ), 'vh' => array( 'min' => 1, 'max' => 100 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--container-height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'object-fit',
			array(
				'label'     => esc_html__( 'Ajuste del objeto', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'condition' => array( 'height[size]!' => '' ),
				'options'   => array(
					''           => esc_html__( 'Predeterminado', 'pro-elements' ),
					'fill'       => esc_html__( 'Rellenar', 'pro-elements' ),
					'cover'      => esc_html__( 'Cubrir', 'pro-elements' ),
					'contain'    => esc_html__( 'Contener', 'pro-elements' ),
					'scale-down' => esc_html__( 'Reducir', 'pro-elements' ),
				),
				'default'   => '',
				'selectors' => array( $img => 'object-fit: {{VALUE}};' ),
			)
		);
		$positions = array(
			'center center' => esc_html__( 'Centro centro', 'pro-elements' ),
			'center left'   => esc_html__( 'Centro izquierda', 'pro-elements' ),
			'center right'  => esc_html__( 'Centro derecha', 'pro-elements' ),
			'top center'    => esc_html__( 'Arriba centro', 'pro-elements' ),
			'top left'      => esc_html__( 'Arriba izquierda', 'pro-elements' ),
			'top right'     => esc_html__( 'Arriba derecha', 'pro-elements' ),
			'bottom center' => esc_html__( 'Abajo centro', 'pro-elements' ),
			'bottom left'   => esc_html__( 'Abajo izquierda', 'pro-elements' ),
			'bottom right'  => esc_html__( 'Abajo derecha', 'pro-elements' ),
		);
		$this->add_responsive_control(
			'object-position',
			array(
				'label'     => esc_html__( 'Posición del objeto', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $positions,
				'default'   => 'center center',
				'selectors' => array( $img => 'object-position: {{VALUE}};' ),
				'condition' => array(
					'height[size]!' => '',
					'object-fit'    => array( 'cover', 'contain', 'scale-down' ),
				),
			)
		);
		$this->add_control( 'separator_panel_style', array( 'type' => Controls_Manager::DIVIDER, 'style' => 'thick' ) );
		$this->start_controls_tabs( 'image_effects' );
		$this->start_controls_tab( 'normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control(
			'opacity',
			array(
				'label'     => esc_html__( 'Opacidad', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => $fit ),
				'selectors' => array( '{{WRAPPER}}' => '--opacity: {{SIZE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'css_filters',
				'selector' => $img,
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( 'hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_control(
			'opacity_hover',
			array(
				'label'     => esc_html__( 'Opacidad', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => $fit ),
				'selectors' => array( $img . ':hover' => '--opacity: {{SIZE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'css_filters_hover',
				'selector' => '{{WRAPPER}}:hover .digi-hotspots>img',
			)
		);
		$this->add_control(
			'background_hover_transition',
			array(
				'label'     => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (s)',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( $img => 'transition-duration: {{SIZE}}s' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'image_border',
				'selector'  => $img,
				'separator' => 'before',
			)
		);
		$this->add_responsive_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( $img => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'image_box_shadow',
				'exclude'  => array( 'box_shadow_position' ),
				'selector' => $img,
			)
		);
		$this->end_controls_section();

		// Estilo de los puntos.
		$this->start_controls_section( 'section_style_hotspot', array( 'label' => esc_html__( 'Puntos de interés', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'style_hotspot_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--hs-color: {{VALUE}};' ),
				'global'    => array( 'default' => 'globals/colors?id=primary' ),
			)
		);
		$this->add_responsive_control(
			'style_hotspot_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => $range( 300, 30 ),
				'selectors'  => array( '{{WRAPPER}}' => '--hs-size: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'style_typography',
				'selector' => '{{WRAPPER}} .digi-hs__label',
				'global'   => array( 'default' => 'globals/typography?id=primary' ),
			)
		);
		$this->add_responsive_control(
			'style_hotspot_width',
			array(
				'label'      => esc_html__( 'Ancho mínimo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $wide,
				'range'      => $range( 1000, 100 ),
				'selectors'  => array( '{{WRAPPER}}' => '--hs-min-width: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_responsive_control(
			'style_hotspot_height',
			array(
				'label'      => esc_html__( 'Alto mínimo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $tall,
				'range'      => $range( 1000, 100 ),
				'selectors'  => array( '{{WRAPPER}}' => '--hs-min-height: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_control(
			'style_hotspot_box_color',
			array(
				'label'     => esc_html__( 'Color de la caja', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--hs-box-color: {{VALUE}};' ),
				'global'    => array( 'default' => 'globals/colors?id=secondary' ),
			)
		);
		$this->add_responsive_control(
			'style_hotspot_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => $wide,
				'range'      => $range( 100, 10 ),
				'selectors'  => array( '{{WRAPPER}}' => '--hs-padding: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'style_hotspot_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--hs-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'style_hotspot_box_shadow',
				'selector' => '{{WRAPPER}} .digi-hs:not(.digi-hs--circle) .digi-hs__button, {{WRAPPER}} .digi-hs.digi-hs--circle .digi-hs__button .digi-hs__outer',
			)
		);
		$this->end_controls_section();

		// Estilo de la información emergente.
		$this->start_controls_section( 'section_style_tooltip', array( 'label' => esc_html__( 'Información emergente', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'style_tooltip_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--tip-text-color: {{VALUE}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'style_tooltip_typography',
				'selector' => '{{WRAPPER}} .digi-hs__tip',
				'global'   => array( 'default' => 'globals/typography?id=secondary' ),
			)
		);
		$this->add_responsive_control(
			'style_tooltip_align',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'    => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-text-align-left' ),
					'center'  => array( 'title' => esc_html__( 'Centro', 'pro-elements' ), 'icon' => 'eicon-text-align-center' ),
					'right'   => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-text-align-right' ),
					'justify' => array( 'title' => esc_html__( 'Justificado', 'pro-elements' ), 'icon' => 'eicon-text-align-justify' ),
				),
				'selectors' => array( '{{WRAPPER}}' => '--tip-align: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'style_tooltip_heading',
			array(
				'label'     => esc_html__( 'Caja', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);
		$this->add_responsive_control(
			'style_tooltip_width',
			array(
				'label'      => esc_html__( 'Ancho mínimo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => $range( 2000, 200 ),
				'selectors'  => array( '{{WRAPPER}}' => '--tip-min-width: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_responsive_control(
			'style_tooltip_max_width',
			array(
				'label'      => esc_html__( 'Ancho máximo', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => $range( 2000, 200 ),
				'selectors'  => array( '{{WRAPPER}}' => '--tip-max-width: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_responsive_control(
			'style_tooltip_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => $wide,
				'selectors'  => array( '{{WRAPPER}}' => '--tip-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'style_tooltip_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--tip-color: {{VALUE}}' ),
				'global'    => array( 'default' => 'globals/colors?id=secondary' ),
			)
		);
		$this->add_control(
			'style_tooltip_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--tip-border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'style_tooltip_box_shadow',
				'selector' => '{{WRAPPER}} .digi-hs__tip',
			)
		);
		$this->end_controls_section();
	}

	/** Clase propia de una opción de animación guardada con el nombre de PRO (`e-hotspot--x` → `digi-hs--x`). */
	public static function animation_class( string $value, array $allowed ): string {
		$name = (string) preg_replace( '/^e-hotspot--/', '', $value );
		return in_array( $name, $allowed, true ) ? 'digi-hs--' . $name : '';
	}

	/**
	 * Dirección de la que entra la información emergente con las animaciones «desde la dirección»: la del widget y, con
	 * «Información emergente propia», la del punto. PRO ponía las dos clases y ganaba la última regla de su hoja (arriba,
	 * izquierda, derecha, abajo); aquí se elige la misma y se pinta una sola clase.
	 */
	public static function from( string $widget, string $own ): string {
		$order = array( 'top', 'left', 'right', 'bottom' );
		$a     = array_search( $widget, $order, true );
		$b     = array_search( $own, $order, true );
		$a     = false === $a ? 0 : $a;
		return $order[ false !== $b && $b > $a ? $b : $a ];
	}

	/** Disparador por dispositivo (`desktop` y los dispositivos con valor propio), para el script. */
	public static function triggers( array $settings ): array {
		$allowed = array( 'mouseenter', 'click', 'none' );
		$out     = array( 'desktop' => in_array( (string) ( $settings['tooltip_trigger'] ?? 'click' ), $allowed, true ) ? (string) $settings['tooltip_trigger'] : 'click' );
		foreach ( self::DEVICES as $device ) {
			$value = (string) ( $settings[ 'tooltip_trigger_' . $device ] ?? '' );
			if ( in_array( $value, $allowed, true ) ) {
				$out[ $device ] = $value;
			}
		}
		return $out;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['image']['url'] ) ) {
			return;
		}
		$triggers    = self::triggers( $settings );
		$trigger     = $triggers['desktop'];
		$interactive = array() !== array_diff( $triggers, array( 'none' ) );
		$animation   = (string) ( $settings['tooltip_animation'] ?? 'e-hotspot--fade-in-out' );
		$tooltip_an  = self::animation_class( $animation, array( 'fade-in-out', 'fade-grow', 'fade-direction', 'slide-direction' ) );
		$direction   = in_array( $tooltip_an, array( 'digi-hs--fade-direction', 'digi-hs--slide-direction' ), true );
		$show        = 'none' === $trigger;
		$sides       = array( 'top', 'bottom', 'left', 'right' );
		$from        = in_array( (string) ( $settings['tooltip_position'] ?? 'top' ), $sides, true ) ? (string) $settings['tooltip_position'] : 'top';
		$button_an   = self::animation_class( (string) ( $settings['hotspot_animation'] ?? '' ), array( 'soft-beat', 'expand', 'overlay' ) );
		$sequenced   = 'yes' === ( $settings['hotspot_sequenced_animation'] ?? '' );
		$prefix      = 'digi-hotspot-' . $this->get_id() . '-';

		$this->add_render_attribute( 'root', 'class', 'digi-hotspots' );
		$this->add_render_attribute( 'root', 'data-digi-trigger', (string) wp_json_encode( $triggers ) );
		if ( $sequenced ) {
			$size = $settings['hotspot_sequenced_animation_duration']['size'] ?? '';
			$this->add_render_attribute( 'root', 'data-digi-sequence', (string) ( '' !== $size && null !== $size ? max( 0, (int) $size ) : 1000 ) );
		}
		echo '<div ' . $this->get_render_attribute_string( 'root' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados por el núcleo.
		echo Group_Control_Image_Size::get_attachment_image_html( $settings, 'image', 'image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado del núcleo.

		foreach ( (array) ( $settings['hotspot'] ?? array() ) as $index => $hotspot ) {
			if ( ! is_array( $hotspot ) ) {
				continue;
			}
			$label   = (string) ( $hotspot['hotspot_label'] ?? '' );
			$icon    = (array) ( $hotspot['hotspot_icon'] ?? array() );
			$has_ico = ! empty( $icon['value'] );
			$circle  = '' === $label && ! $has_ico;
			$link    = (array) ( $hotspot['hotspot_link'] ?? array() );
			$is_link = ! empty( $link['url'] );
			$content = (string) ( $hotspot['hotspot_tooltip_content'] ?? '' );
			$tooltip = '' !== $content && ! ( 'click' === $trigger && $is_link );
			$id      = $prefix . ( isset( $hotspot['_id'] ) ? sanitize_html_class( (string) $hotspot['_id'] ) : $index );
			$x_unit  = $hotspot['hotspot_offset_x']['unit'] ?? '%';
			$y_unit  = $hotspot['hotspot_offset_y']['unit'] ?? '%';
			$h_side  = in_array( $hotspot['hotspot_horizontal'] ?? '', array( 'left', 'right' ), true ) ? $hotspot['hotspot_horizontal'] : 'left';
			$v_side  = in_array( $hotspot['hotspot_vertical'] ?? '', array( 'top', 'bottom' ), true ) ? $hotspot['hotspot_vertical'] : 'top';
			$classes = array(
				'digi-hs',
				'elementor-repeater-item-' . ( $hotspot['_id'] ?? '' ),
				$sequenced ? 'digi-hs--sequenced' : '',
				'%' === $x_unit ? 'digi-hs--pos-' . $h_side : '',
				'%' === $y_unit ? 'digi-hs--pos-' . $v_side : '',
				$is_link ? 'digi-hs--link' : '',
				'click' === $trigger && $is_link ? 'digi-hs--no-tip' : '',
				$circle ? 'digi-hs--circle' : '',
				'' === $label && $has_ico ? 'digi-hs--icon' : '',
			);
			echo '<div class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '">';

			// Disparador: botón con información emergente, enlace con enlace y, si no, sólo el dibujo.
			$key = 'trigger' . $index;
			$this->add_render_attribute( $key, 'class', array_filter( array( 'digi-hs__button', $button_an ) ) );
			$tag = 'span';
			if ( $is_link ) {
				$tag = 'a';
				$this->add_link_attributes( $key, $link );
				if ( $tooltip ) {
					$this->add_render_attribute( $key, 'aria-describedby', $id );
				}
			} elseif ( $tooltip && $interactive ) {
				$tag = 'button';
				$this->add_render_attribute( $key, array( 'type' => 'button', 'aria-expanded' => $show ? 'true' : 'false', 'aria-controls' => $id ) );
			}
			if ( '' === trim( wp_strip_all_tags( $label ) ) && 'span' !== $tag ) {
				/* translators: %d: número del punto. */
				$this->add_render_attribute( $key, 'aria-label', sprintf( esc_html__( 'Punto de interés %d', 'pro-elements' ), (int) $index + 1 ) );
			}
			echo '<' . $tag . ' ' . $this->get_render_attribute_string( $key ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de una lista cerrada y atributos escapados.
			if ( $circle ) {
				echo '<span class="digi-hs__outer"></span><span class="digi-hs__inner"></span>';
			} else {
				if ( $has_ico && class_exists( '\\Elementor\\Icons_Manager' ) ) {
					echo '<span class="digi-hs__icon">';
					\Elementor\Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
					echo '</span>';
				}
				if ( '' !== $label ) {
					echo '<span class="digi-hs__label">' . wp_kses_post( $label ) . '</span>';
				}
			}
			echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de una lista cerrada.

			if ( $tooltip ) {
				$own   = $hotspot['hotspot_position'] ?? '';
				$tip   = array(
					'digi-hs__tip',
					$show ? 'digi-hs--show-tip' : '',
					$direction ? '' : 'digi-hs--tip-pos',
					$show ? '' : $tooltip_an,
					$direction ? 'digi-hs--from-' . self::from( $from, 'yes' === ( $hotspot['hotspot_tooltip_position'] ?? '' ) ? (string) $own : '' ) : '',
				);
				if ( $direction ) {
					echo '<div class="digi-hs__mask digi-hs--tip-pos">';
				}
				echo '<div id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', array_filter( $tip ) ) ) . '">' . wp_kses_post( $content ) . '</div>';
				if ( $direction ) {
					echo '</div>';
				}
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
