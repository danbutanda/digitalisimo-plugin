<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Titular animado (`digitalisimo-animated-headline`): sustituye al widget «Animated Headline» de PRO
 * (`modules/animated-headline/widgets/animated-headline.php`, mapa `modules/digitalisimo-convert/maps/animated-headline.php`).
 * Repite sus controles con el mismo nombre, valor por defecto, opciones y condiciones; el marcado, las clases, la hoja
 * (`assets/css/animated-headline.css`) y el script (`assets/js/animated-headline.js`, sin jQuery) son propios.
 *
 * Marcado: `<h3 class="digi-ah …">` (dentro de `<a class="digi-ah-link">` con enlace) con el texto anterior y el
 * posterior (`digi-ah__plain`) y, entre ellos, la parte animada (`digi-ah__dyn`) con una palabra por línea del texto que
 * rota (`digi-ah__word`, la primera con `--on`) o el texto resaltado y su trazo SVG, que se pinta ya en el servidor (PRO lo
 * añadía con su script). Las animaciones de palabras y letras usan dos fotogramas clave comunes (`digi-ah-in`/`-out`) con
 * las variables de cada tipo en línea (`TYPES`), así la hoja cabe en 4 KB. El script (mismos tiempos que PRO) arranca al
 * entrar el titular en la pantalla y, al partir las letras, deja la palabra entera para los lectores de pantalla.
 *
 * Diferencias declaradas con PRO: con «reducir movimiento» el texto no rota (se queda la primera palabra; PRO seguía
 * rotando) y el trazo aparece dibujado. En lo convertido de PRO (la marca del traductor, `Translator::MARKER`) el texto
 * sigue rotando como en PRO (clase `digi-ah--motion`), y sólo el trazo respeta la preferencia, como en PRO; el texto se lee también sin JavaScript (PRO dejaba invisibles las palabras de los
 * tipos por letras); el texto que rota pasa por `wp_kses_post()` (PRO lo imprimía sin filtrar); el enlace tiene foco
 * visible propio; las animaciones «giro» y «caída» reparten sus fotogramas en 0/60/100 % (PRO: 0/35/65/100 % y 0/100 %).
 */
class Animated_Headline extends \Elementor\Widget_Base {

	/** Tipos de animación del texto que rota, por letras (los demás, por palabras o recorte). */
	const LETTERS = array( 'typing', 'swirl', 'blinds', 'wave' );

	/**
	 * Variables de los fotogramas comunes por tipo (`--t` duración; `--a`, `--b`, `--c` transformación al entrar en 0, 60 y
	 * 100 %; `--o0`, `--o6` opacidad al entrar; `--d`, `--e`, `--f` y `--p6`, `--p1` al salir). Se escriben todas en línea
	 * para que ninguna se herede de un antepasado. Valores de los fotogramas de PRO.
	 */
	const TYPES = array(
		'flip'       => array( '1.2s', 'rotateX(180deg)', 'rotateX(240deg)', 'rotateX(1turn)', '0', '0', 'none', 'rotateX(60deg)', 'rotateX(180deg)', '.2', '0' ),
		'drop-in'    => array( '.8s', 'translateZ(100px)', 'translateZ(40px)', 'none', '0', '.6', 'none', 'translateZ(-60px)', 'translateZ(-100px)', '.4', '0' ),
		'slide'      => array( '.6s', 'translateX(-100%)', 'translateX(10%)', 'none', '0', '1', 'none', 'translateX(110%)', 'translateX(100%)', '0', '0' ),
		'slide-down' => array( '.6s', 'translateY(-100%)', 'translateY(20%)', 'none', '0', '1', 'none', 'translateY(120%)', 'translateY(100%)', '0', '0' ),
		'swirl'      => array( '.4s', 'translateZ(-20px) rotateX(90deg)', 'translateZ(-20px) rotateX(-10deg)', 'translateZ(-20px)', '0', '1', 'translateZ(-20px)', 'translateZ(-20px) rotateX(-100deg)', 'translateZ(-20px) rotateX(-90deg)', '0', '0' ),
		'blinds'     => array( '.6s', 'rotateY(180deg)', 'rotateY(72deg)', 'none', '1', '1', 'none', 'rotateY(-108deg)', 'rotateY(-180deg)', '1', '1' ),
		'wave'       => array( '.6s', 'scale(0)', 'scale(1.2)', 'none', '0', '1', 'none', 'scale(0)', 'scale(0)', '0', '0' ),
	);

	/** Nombres de las variables de `TYPES`, en su orden. */
	const VARS = array( '--t', '--a', '--b', '--c', '--o0', '--o6', '--d', '--e', '--f', '--p6', '--p1' );

	/** Trazos del resaltado (caja 500 × 150), los mismos dibujos que PRO. */
	const SHAPES = array(
		'circle'           => array( 'M325,18C228.7-8.3,118.5,8.3,78,21C22.4,38.4,4.6,54.6,5.6,77.6c1.4,32.4,52.2,54,142.6,63.7 c66.2,7.1,212.2,7.5,273.5-8.3c64.4-16.6,104.3-57.6,33.8-98.2C386.7-4.9,179.4-1.4,126.3,20.7' ),
		'underline_zigzag' => array( 'M9.3,127.3c49.3-3,150.7-7.6,199.7-7.4c121.9,0.4,189.9,0.4,282.3,7.2C380.1,129.6,181.2,130.6,70,139 c82.6-2.9,254.2-1,335.9,1.3c-56,1.4-137.2-0.3-197.1,9' ),
		'x'                => array( 'M497.4,23.9C301.6,40,155.9,80.6,4,144.4', 'M14.1,27.6c204.5,20.3,393.8,74,467.3,111.7' ),
		'strikethrough'    => array( 'M3,75h493.5' ),
		'curly'            => array( 'M3,146.1c17.1-8.8,33.5-17.8,51.4-17.8c15.6,0,17.1,18.1,30.2,18.1c22.9,0,36-18.6,53.9-18.6 c17.1,0,21.3,18.5,37.5,18.5c21.3,0,31.8-18.6,49-18.6c22.1,0,18.8,18.8,36.8,18.8c18.8,0,37.5-18.6,49-18.6c20.4,0,17.1,19,36.8,19 c22.9,0,36.8-20.6,54.7-18.6c17.7,1.4,7.1,19.5,33.5,18.8c17.1,0,47.2-6.5,61.1-15.6' ),
		'diagonal'         => array( 'M13.5,15.5c131,13.7,289.3,55.5,475,125.5' ),
		'double'           => array( 'M8.4,143.1c14.2-8,97.6-8.8,200.6-9.2c122.3-0.4,287.5,7.2,287.5,7.2', 'M8,19.4c72.3-5.3,162-7.8,216-7.8c54,0,136.2,0,267,7.8' ),
		'double_underline' => array( 'M5,125.4c30.5-3.8,137.9-7.6,177.3-7.6c117.2,0,252.2,4.7,312.7,7.6', 'M26.9,143.8c55.1-6.1,126-6.3,162.2-6.1c46.5,0.2,203.9,3.2,268.9,6.4' ),
		'underline'        => array( 'M7.7,145.6C109,125,299.9,116.2,401,121.3c42.1,2.2,87.6,11.8,87.3,25.7' ),
	);

	public function get_name() {
		return 'digitalisimo-animated-headline';
	}

	public function get_title() {
		return esc_html__( 'Titular animado', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-animated-headline';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'titular', 'encabezado', 'animado', 'animación', 'resaltado', 'rotar', 'headline', 'heading', 'animation', 'title', 'text' );
	}

	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	/** Como PRO: sin el envoltorio interior con el marcado optimizado del núcleo. */
	public function has_widget_inner_wrapper(): bool {
		$plugin = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$experiments = is_object( $plugin ) && isset( $plugin->experiments ) ? $plugin->experiments : null;
		return ! ( is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && $experiments->is_feature_active( 'e_optimized_markup' ) );
	}

	protected function register_controls() {
		$text = array(
			'active'     => true,
			'categories' => array( 'text' ),
		);

		$this->start_controls_section(
			'text_elements',
			array(
				'label' => esc_html__( 'Titular', 'pro-elements' ),
			)
		);

		$this->add_control(
			'headline_style',
			array(
				'label'              => esc_html__( 'Estilo de animación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'highlight',
				'options'            => array(
					'highlight' => esc_html__( 'Texto resaltado', 'pro-elements' ),
					'rotate'    => esc_html__( 'Texto que rota', 'pro-elements' ),
				),
				'prefix_class'       => 'digi-ah--style-',
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'animation_type',
			array(
				'label'              => esc_html__( 'Tipo de animación', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'typing'     => esc_html__( 'Escritura', 'pro-elements' ),
					'clip'       => esc_html__( 'Recorte', 'pro-elements' ),
					'flip'       => esc_html__( 'Giro', 'pro-elements' ),
					'swirl'      => esc_html__( 'Remolino', 'pro-elements' ),
					'blinds'     => esc_html__( 'Persianas', 'pro-elements' ),
					'drop-in'    => esc_html__( 'Caída', 'pro-elements' ),
					'wave'       => esc_html__( 'Ola', 'pro-elements' ),
					'slide'      => esc_html__( 'Deslizamiento', 'pro-elements' ),
					'slide-down' => esc_html__( 'Deslizamiento hacia abajo', 'pro-elements' ),
				),
				'default'            => 'typing',
				'condition'          => array(
					'headline_style' => 'rotate',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'marker',
			array(
				'label'              => esc_html__( 'Forma', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'circle',
				'options'            => array(
					'circle'           => esc_html__( 'Círculo', 'pro-elements' ),
					'curly'            => esc_html__( 'Ondulado', 'pro-elements' ),
					'underline'        => esc_html__( 'Subrayado', 'pro-elements' ),
					'double'           => esc_html__( 'Doble', 'pro-elements' ),
					'double_underline' => esc_html__( 'Subrayado doble', 'pro-elements' ),
					'underline_zigzag' => esc_html__( 'Subrayado en zigzag', 'pro-elements' ),
					'diagonal'         => esc_html__( 'Diagonal', 'pro-elements' ),
					'strikethrough'    => esc_html__( 'Tachado', 'pro-elements' ),
					'x'                => 'X',
				),
				'render_type'        => 'template',
				'condition'          => array(
					'headline_style' => 'highlight',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'before_text',
			array(
				'label'       => esc_html__( 'Texto anterior', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => $text,
				'default'     => esc_html__( 'Esta página es', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe tu titular', 'pro-elements' ),
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'highlighted_text',
			array(
				'label'              => esc_html__( 'Texto resaltado', 'pro-elements' ),
				'type'               => Controls_Manager::TEXT,
				'dynamic'            => $text,
				'default'            => esc_html__( 'increíble', 'pro-elements' ),
				'label_block'        => true,
				'condition'          => array(
					'headline_style' => 'highlight',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'rotating_text',
			array(
				'label'              => esc_html__( 'Texto que rota', 'pro-elements' ),
				'type'               => Controls_Manager::TEXTAREA,
				'placeholder'        => esc_html__( 'Escribe cada palabra en una línea', 'pro-elements' ),
				'default'            => "mejor\nmás grande\nmás rápida",
				'dynamic'            => $text,
				'condition'          => array(
					'headline_style' => 'rotate',
				),
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'after_text',
			array(
				'label'       => esc_html__( 'Texto posterior', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => $text,
				'placeholder' => esc_html__( 'Escribe tu titular', 'pro-elements' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'loop',
			array(
				'label'              => esc_html__( 'Bucle infinito', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'render_type'        => 'template',
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}}' => '--digi-ah-iteration: infinite',
				),
				'separator'          => 'before',
			)
		);

		$this->add_control(
			'highlight_animation_duration',
			array(
				'label'              => esc_html__( 'Duración', 'pro-elements' ) . ' (ms)',
				'type'               => Controls_Manager::NUMBER,
				'default'            => 1200,
				'render_type'        => 'template',
				'frontend_available' => true,
				'selectors'          => array(
					'{{WRAPPER}}' => '--digi-ah-duration: {{VALUE}}ms',
				),
				'condition'          => array(
					'headline_style' => 'highlight',
				),
			)
		);

		$this->add_control(
			'highlight_iteration_delay',
			array(
				'label'              => esc_html__( 'Pausa', 'pro-elements' ) . ' (ms)',
				'type'               => Controls_Manager::NUMBER,
				'default'            => 8000,
				'render_type'        => 'template',
				'frontend_available' => true,
				'condition'          => array(
					'headline_style' => 'highlight',
					'loop'           => 'yes',
				),
			)
		);

		$this->add_control(
			'rotate_iteration_delay',
			array(
				'label'              => esc_html__( 'Duración', 'pro-elements' ) . ' (ms)',
				'type'               => Controls_Manager::NUMBER,
				'default'            => 2500,
				'render_type'        => 'template',
				'frontend_available' => true,
				'condition'          => array(
					'headline_style' => 'rotate',
				),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'     => esc_html__( 'Enlace', 'pro-elements' ),
				'type'      => Controls_Manager::URL,
				'dynamic'   => array(
					'active' => true,
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'tag',
			array(
				'label'   => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
					'p'    => 'p',
				),
				'default' => 'h3',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_text',
			array(
				'label' => esc_html__( 'Titular', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$rtl = function_exists( 'is_rtl' ) && is_rtl();
		$this->add_responsive_control(
			'alignment',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'  => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'    => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'              => 'center',
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => $rtl ? 'end' : 'start',
					'right' => $rtl ? 'start' : 'end',
				),
				'selectors'            => array(
					'{{WRAPPER}} .digi-ah' => 'text-align: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'heading_style',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Texto', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => 'globals/colors?id=secondary',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-ah__plain' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'global'   => array(
					'default' => 'globals/typography?id=primary',
				),
				'selector' => '{{WRAPPER}} .digi-ah',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'text_stroke',
				'selector' => '{{WRAPPER}} .digi-ah .digi-ah__plain',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'title_text_shadow',
				'selector' => '{{WRAPPER}} .digi-ah .digi-ah__plain',
			)
		);

		$this->add_control(
			'heading_words_style',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Texto animado', 'pro-elements' ),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'words_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => 'globals/colors?id=secondary',
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-ah-color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'words_typography',
				'global'   => array(
					'default' => 'globals/typography?id=primary',
				),
				'selector' => '{{WRAPPER}} .digi-ah__word',
				'exclude'  => array( 'font_size' ),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'     => 'animated_text_stroke',
				'selector' => '{{WRAPPER}} .digi-ah .digi-ah__dyn',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'animated_text_shadow',
				'selector' => '{{WRAPPER}} .digi-ah .digi-ah__dyn',
			)
		);

		$typing = array(
			'headline_style' => 'rotate',
			'animation_type' => 'typing',
		);
		$this->add_control(
			'typing_animation_highlight_colors',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Texto seleccionado', 'pro-elements' ),
				'separator' => 'before',
				'condition' => $typing,
			)
		);

		$this->add_control(
			'highlighted_text_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-ah-sel-bg: {{VALUE}}',
				),
				'condition' => $typing,
			)
		);

		$this->add_control(
			'highlighted_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-ah-sel-color: {{VALUE}}',
				),
				'condition' => $typing,
			)
		);

		$highlight = array(
			'headline_style' => 'highlight',
		);
		$this->add_control(
			'highlight_animation_shape_colors',
			array(
				'type'      => Controls_Manager::HEADING,
				'label'     => esc_html__( 'Forma del resaltado', 'pro-elements' ),
				'separator' => 'before',
				'condition' => $highlight,
			)
		);

		$this->add_control(
			'marker_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => 'globals/colors?id=accent',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-ah__dyn path' => 'stroke: {{VALUE}}',
				),
				'condition' => $highlight,
			)
		);

		$this->add_control(
			'stroke_width',
			array(
				'label'      => esc_html__( 'Grosor', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
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
					'{{WRAPPER}} .digi-ah__dyn path' => 'stroke-width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => $highlight,
			)
		);

		$this->add_control(
			'above_content',
			array(
				'label'     => esc_html__( 'Traer al frente', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array(
					'{{WRAPPER}} .digi-ah__dyn svg' => 'z-index: 2',
					'{{WRAPPER}} .digi-ah__word'    => 'z-index: auto',
				),
				'condition' => $highlight,
			)
		);

		$this->add_control(
			'rounded_edges',
			array(
				'label'     => esc_html__( 'Bordes redondeados', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'selectors' => array(
					'{{WRAPPER}} .digi-ah__dyn path' => 'stroke-linecap: round; stroke-linejoin: round',
				),
				'condition' => $highlight,
			)
		);

		$this->end_controls_section();
	}

	/** Etiqueta permitida (la lista del control); otra cosa, `h3`. */
	private static function tag( $tag ): string {
		$tag = strtolower( (string) $tag );
		return in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ? $tag : 'h3';
	}

	/** Una palabra del texto que rota: HTML permitido y espacios duros fuera de las etiquetas (como PRO, sin romperlas). */
	private static function word( string $text ): string {
		$html = wp_kses_post( rtrim( $text, "\r" ) );
		return (string) preg_replace_callback(
			'/(^|>)([^<]*)/',
			static function ( $m ) {
				return $m[1] . str_replace( ' ', '&nbsp;', $m[2] );
			},
			$html
		);
	}

	/** Variables en línea de un tipo de animación por palabras o letras (`TYPES`); '' para escritura y recorte. */
	public static function type_style( string $type ): string {
		if ( ! isset( self::TYPES[ $type ] ) ) {
			return '';
		}
		$out = array();
		foreach ( self::VARS as $i => $name ) {
			$out[] = $name . ':' . self::TYPES[ $type ][ $i ];
		}
		return implode( ';', $out );
	}

	/** El trazo SVG de una forma del resaltado ('' si no existe). */
	public static function shape( $marker ): string {
		$marker = (string) $marker;
		if ( ! isset( self::SHAPES[ $marker ] ) ) {
			return '';
		}
		$paths = '';
		foreach ( self::SHAPES[ $marker ] as $d ) {
			$paths .= '<path d="' . esc_attr( $d ) . '"></path>';
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$style    = 'rotate' === ( $settings['headline_style'] ?? '' ) ? 'rotate' : 'highlight';
		$type     = (string) ( $settings['animation_type'] ?? 'typing' );
		$type     = in_array( $type, array( 'typing', 'clip', 'flip', 'swirl', 'blinds', 'drop-in', 'wave', 'slide', 'slide-down' ), true ) ? $type : 'typing';
		$tag      = self::tag( $settings['tag'] ?? 'h3' );

		$classes = array( 'digi-ah' );
		$attrs   = array(
			'data-digi-ah-style' => $style,
			'data-digi-ah-loop'  => 'yes' === ( $settings['loop'] ?? '' ) ? '1' : '0',
		);
		if ( 'rotate' === $style ) {
			$classes[] = 'digi-ah--' . $type;
			// Lo convertido de PRO rota también con «reducir movimiento», como PRO (que sólo la consulta en el resaltado).
			if ( 'animated-headline' === (string) $this->get_settings( '_digitalisimo_pro' ) ) {
				$classes[] = 'digi-ah--motion';
			}
			if ( in_array( $type, self::LETTERS, true ) ) {
				$classes[] = 'digi-ah--letters';
			}
			if ( in_array( $type, array( 'swirl', 'blinds', 'wave' ), true ) ) {
				$classes[] = 'digi-ah--fx';
			} elseif ( isset( self::TYPES[ $type ] ) ) {
				$classes[] = 'digi-ah--words';
			}
			$attrs['data-digi-ah-type']  = $type;
			$attrs['data-digi-ah-delay'] = (string) absint( $settings['rotate_iteration_delay'] ?? 0 );
			if ( '' !== self::type_style( $type ) ) {
				$attrs['style'] = self::type_style( $type );
			}
		} else {
			$attrs['data-digi-ah-duration'] = (string) absint( $settings['highlight_animation_duration'] ?? 0 );
			$attrs['data-digi-ah-wait']     = (string) absint( $settings['highlight_iteration_delay'] ?? 0 );
		}
		$attr_html = '';
		foreach ( $attrs as $name => $value ) {
			$attr_html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		$link = ! empty( $settings['link']['url'] );
		if ( $link ) {
			$this->add_link_attributes( 'url', $settings['link'] );
			$this->add_render_attribute( 'url', 'class', 'digi-ah-link' );
			echo '<a ' . $this->get_render_attribute_string( 'url' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados por el núcleo.
		}
		echo '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $attr_html . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista y atributos escapados.
		if ( ! empty( $settings['before_text'] ) ) {
			echo '<span class="digi-ah__plain digi-ah__wrap">' . wp_kses_post( $settings['before_text'] ) . "</span>\n";
		}
		echo '<span class="digi-ah__dyn digi-ah__wrap">';
		if ( 'rotate' === $style && ! empty( $settings['rotating_text'] ) ) {
			foreach ( explode( "\n", (string) $settings['rotating_text'] ) as $i => $text ) {
				// Separadas por un salto como en PRO (texto legible sin estilos); el espacio se pierde al inicio y al final de la línea.
				echo ( 0 === $i ? '' : "\n" ) . '<span class="digi-ah__word' . ( 0 === $i ? ' digi-ah__word--on' : '' ) . '">' . self::word( $text ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() en word().
			}
		} elseif ( 'highlight' === $style ) {
			if ( ! empty( $settings['highlighted_text'] ) ) {
				echo '<span class="digi-ah__word digi-ah__word--on">' . wp_kses_post( $settings['highlighted_text'] ) . '</span>';
			}
			echo self::shape( $settings['marker'] ?? 'circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trazos fijos de SHAPES.
		}
		echo '</span>';
		if ( ! empty( $settings['after_text'] ) ) {
			echo "\n" . '<span class="digi-ah__plain digi-ah__wrap">' . wp_kses_post( $settings['after_text'] ) . '</span>';
		}
		echo '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista.
		if ( $link ) {
			echo '</a>';
		}
	}
}
