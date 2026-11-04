<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Animación Lottie: reproduce un archivo JSON de Lottie (adjunto de la biblioteca o URL) con los mismos disparadores que
 * PRO (al entrar en la pantalla, al pulsar, al pasar el cursor, ligada al desplazamiento o sin disparador). Sustituye al
 * Lottie de PRO (`modules/lottie/widgets/lottie.php`, mapa `modules/digitalisimo-convert/maps/lottie.php`): repite sus
 * controles con el mismo nombre, valores por defecto, opciones y condiciones, y las mismas variables CSS en el
 * envoltorio, así que el mapa es todo `keep`.
 *
 * - **Librería local** (`assets/lib/lottie/lottie.min.js`, lottie-web 5.6.8, MIT, ≈ 250 KB): nunca en la carga inicial.
 *   `assets/js/lottie.js` (`defer`, sin jQuery ni dependencias) la inserta una sola vez cuando el primer widget está a
 *   una pantalla de distancia (`IntersectionObserver`); con «Carga diferida», la animación se crea además cuando el
 *   widget queda a 200 px, como en PRO. La configuración viaja en `data-digi-lottie` (sin `elementorFrontend`).
 * - **Archivo** como en PRO: el JSON del adjunto si su dirección acaba en `json`, si no la URL externa, y si no la
 *   animación de muestra propia (`assets/lottie/default.json`, neutra: la de PRO es un logotipo de otra marca).
 * - **Leyenda** con las mismas reglas que PRO (título o leyenda del adjunto sólo si quien mira puede leerlo,
 *   personalizada con un archivo o con una URL externa). Descripción y botón de DIGITALÍSIMO (`digitalisimo_lottie_*`,
 *   los de `Media_Text_Extras` sobre el Lottie de PRO) con el mismo marcado.
 * - **Accesibilidad.** La animación es decorativa (`aria-hidden`); el enlace sin leyenda lleva el nombre del adjunto o
 *   «Animación»; con «Al pulsar» y sin enlace, la animación es un botón para el teclado (Intro y Espacio). Con
 *   `prefers-reduced-motion`, se muestra fija en su estado final (el último fotograma del tramo, o el primero si se
 *   invierte).
 *
 * Deriva de PRO Elements (GPL): `modules/lottie/widgets/lottie.php` y `classes/caption-helper.php` (controles, textos y
 * leyenda), `modules/lottie/assets/js/frontend/handler.js` (disparadores, bucle, tramos, inversión y desplazamiento; con
 * las utilidades de desplazamiento del núcleo) y `assets/css/widget-lottie.min.css` (aspecto por defecto).
 */
class Lottie extends \Elementor\Widget_Base {
	/** Librería de Lottie (relativa a la raíz del plugin) y su versión, la de la cabecera del archivo. */
	const LIBRARY = 'assets/lib/lottie/lottie.min.js';
	const LIBRARY_VERSION = '5.6.8';

	/** Animación de muestra propia (relativa a la raíz del plugin). */
	const SAMPLE = 'modules/digitalisimo-content/assets/lottie/default.json';

	public function get_name() { return 'digitalisimo-lottie'; }
	public function get_title() { return esc_html__( 'Animación Lottie', 'pro-elements' ); }
	public function get_icon() { return 'eicon-lottie'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'lottie', 'animación', 'animation', 'json', 'svg', 'bodymovin' ); }
	public function get_style_depends(): array { return Module::style_depends( $this->get_name() ); }
	public function get_script_depends(): array { return Module::script_depends( $this->get_name() ); }
	protected function is_dynamic_content(): bool { return false; }

	/** Como PRO: sin envoltorio interior con el marcado optimizado. */
	public function has_widget_inner_wrapper(): bool {
		$plugin = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$exp    = is_object( $plugin ) && isset( $plugin->experiments ) ? $plugin->experiments : null;
		return ! ( is_object( $exp ) && method_exists( $exp, 'is_feature_active' ) && $exp->is_feature_active( 'e_optimized_markup' ) );
	}

	/** Como PRO: la URL externa sólo para quien puede publicar páginas. */
	protected function current_user_can_use_external_source() {
		return function_exists( 'current_user_can' ) && current_user_can( 'publish_pages' );
	}

	protected function get_source_options() {
		$options = array( 'media_file' => esc_html__( 'Archivo multimedia', 'pro-elements' ) );
		if ( $this->current_user_can_use_external_source() ) {
			$options['external_url'] = esc_html__( 'URL externa', 'pro-elements' );
		}
		return $options;
	}

	protected function register_controls() {
		$this->start_controls_section( 'lottie', array( 'label' => esc_html__( 'Lottie', 'pro-elements' ) ) );
		$this->add_control( 'source', array( 'label' => esc_html__( 'Origen', 'pro-elements' ), 'type' => Controls_Manager::SELECT, 'default' => 'media_file', 'options' => $this->get_source_options() ) );
		$this->add_control(
			'source_external_url',
			array(
				'label'       => esc_html__( 'URL externa', 'pro-elements' ),
				'type'        => $this->current_user_can_use_external_source() ? Controls_Manager::URL : Controls_Manager::HIDDEN,
				'condition'   => array( 'source' => 'external_url' ),
				'dynamic'     => array( 'active' => true, 'property' => 'url' ),
				'default'     => array( 'url' => '', 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ),
				'placeholder' => esc_html__( 'Escribe la URL', 'pro-elements' ),
			)
		);
		$this->add_control( 'source_json', array( 'label' => esc_html__( 'Subir el archivo JSON', 'pro-elements' ), 'type' => Controls_Manager::MEDIA, 'media_types' => array( 'application/json' ), 'condition' => array( 'source' => 'media_file' ) ) );
		$this->add_responsive_control(
			'align',
			array(
				'label'        => esc_html__( 'Alineación', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => self::align_options(),
				'prefix_class' => 'elementor%s-align-',
				'default'      => 'center',
			)
		);
		$this->add_control(
			'caption_source',
			array(
				'label'     => esc_html__( 'Leyenda', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'none',
				'options'   => array(
					'none'    => esc_html__( 'Ninguna', 'pro-elements' ),
					'title'   => esc_html__( 'Título', 'pro-elements' ),
					'caption' => esc_html__( 'Leyenda', 'pro-elements' ),
					'custom'  => esc_html__( 'Personalizada', 'pro-elements' ),
				),
				'condition' => array( 'source!' => 'external_url', 'source_json[url]!' => '' ),
			)
		);
		$this->add_control(
			'caption',
			array(
				'label'      => esc_html__( 'Leyenda personalizada', 'pro-elements' ),
				'type'       => Controls_Manager::TEXT,
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array( 'name' => 'caption_source', 'value' => 'custom' ),
						array( 'name' => 'source', 'value' => 'external_url' ),
					),
				),
				'dynamic'    => array( 'active' => true ),
			)
		);
		$this->add_control(
			'link_to',
			array(
				'label'   => esc_html__( 'Enlace', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'none',
				'options' => array(
					'none'   => esc_html__( 'Ninguno', 'pro-elements' ),
					'custom' => esc_html__( 'URL personalizada', 'pro-elements' ),
				),
			)
		);
		$this->add_control(
			'custom_link',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'type'        => Controls_Manager::URL,
				'placeholder' => esc_html__( 'Escribe la URL', 'pro-elements' ),
				'condition'   => array( 'link_to' => 'custom' ),
				'dynamic'     => array( 'active' => true ),
				'default'     => array( 'url' => '' ),
				'show_label'  => false,
			)
		);
		$this->end_controls_section();

		// Descripción y botón de DIGITALÍSIMO: los mismos que `Media_Text_Extras` añade al Lottie de PRO.
		$this->start_controls_section( 'digitalisimo_lottie_text', array( 'label' => esc_html__( 'Texto y botón', 'pro-elements' ) ) );
		$this->add_control( 'digitalisimo_lottie_description', array( 'label' => esc_html__( 'Descripción', 'pro-elements' ), 'type' => Controls_Manager::TEXTAREA, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'digitalisimo_lottie_button_text', array( 'label' => esc_html__( 'Texto del botón', 'pro-elements' ), 'type' => Controls_Manager::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'digitalisimo_lottie_button_link', array( 'label' => esc_html__( 'Enlace del botón', 'pro-elements' ), 'type' => Controls_Manager::URL, 'dynamic' => array( 'active' => true ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'settings', array( 'label' => esc_html__( 'Ajustes', 'pro-elements' ) ) );
		$this->add_control(
			'trigger',
			array(
				'label'   => esc_html__( 'Disparador', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'arriving_to_viewport',
				'options' => array(
					'arriving_to_viewport' => esc_html__( 'Al entrar en la pantalla', 'pro-elements' ),
					'on_click'             => esc_html__( 'Al pulsar', 'pro-elements' ),
					'on_hover'             => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
					'bind_to_scroll'       => esc_html__( 'Con el desplazamiento', 'pro-elements' ),
					'none'                 => esc_html__( 'Ninguno', 'pro-elements' ),
				),
			)
		);
		$this->add_control(
			'viewport',
			array(
				'label'      => esc_html__( 'Zona de la pantalla', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array( 'name' => 'trigger', 'operator' => '===', 'value' => 'arriving_to_viewport' ),
						array( 'name' => 'trigger', 'operator' => '===', 'value' => 'bind_to_scroll' ),
					),
				),
				'default'    => array( 'sizes' => array( 'start' => 0, 'end' => 100 ), 'unit' => '%' ),
				'labels'     => array( esc_html__( 'Abajo', 'pro-elements' ), esc_html__( 'Arriba', 'pro-elements' ) ),
				'scales'     => 1,
				'handles'    => 'range',
			)
		);
		$this->add_control(
			'effects_relative_to',
			array(
				'label'     => esc_html__( 'Efectos relativos a', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'condition' => array( 'trigger' => 'bind_to_scroll' ),
				'default'   => 'viewport',
				'options'   => array(
					'viewport' => esc_html__( 'La pantalla', 'pro-elements' ),
					'page'     => esc_html__( 'Toda la página', 'pro-elements' ),
				),
			)
		);
		$this->add_control( 'loop', array( 'label' => esc_html__( 'Repetir', 'pro-elements' ), 'type' => Controls_Manager::SWITCHER, 'condition' => array( 'trigger!' => 'bind_to_scroll' ), 'return_value' => 'yes', 'default' => '' ) );
		$this->add_control(
			'number_of_times',
			array(
				'label'      => esc_html__( 'Veces', 'pro-elements' ),
				'type'       => Controls_Manager::NUMBER,
				'conditions' => array(
					'relation' => 'and',
					'terms'    => array(
						array( 'name' => 'trigger', 'operator' => '!==', 'value' => 'bind_to_scroll' ),
						array( 'name' => 'loop', 'operator' => '===', 'value' => 'yes' ),
					),
				),
				'min'        => 0,
				'step'       => 1,
			)
		);
		$this->add_control(
			'link_timeout',
			array(
				'label'       => esc_html__( 'Espera antes del enlace', 'pro-elements' ) . ' (ms)',
				'type'        => Controls_Manager::NUMBER,
				'conditions'  => array(
					'relation' => 'and',
					'terms'    => array(
						array( 'name' => 'link_to', 'operator' => '===', 'value' => 'custom' ),
						array( 'name' => 'trigger', 'operator' => '===', 'value' => 'on_click' ),
						array( 'name' => 'custom_link[url]', 'operator' => '!==', 'value' => '' ),
					),
				),
				'description' => esc_html__( 'Abre el enlace después de este tiempo.', 'pro-elements' ),
				'min'         => 0,
				'max'         => 5000,
				'step'        => 1,
			)
		);
		$this->add_control(
			'on_hover_out',
			array(
				'label'     => esc_html__( 'Al quitar el cursor', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'condition' => array( 'trigger' => 'on_hover' ),
				'default'   => 'default',
				'options'   => array(
					'default' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'reverse' => esc_html__( 'Invertir', 'pro-elements' ),
					'pause'   => esc_html__( 'Pausar', 'pro-elements' ),
				),
			)
		);
		$this->add_control(
			'hover_area',
			array(
				'label'     => esc_html__( 'Zona del cursor', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'condition' => array( 'trigger' => 'on_hover' ),
				'default'   => 'animation',
				'options'   => array(
					'animation' => esc_html__( 'Animación', 'pro-elements' ),
					'column'    => esc_html__( 'Columna', 'pro-elements' ),
					'section'   => esc_html__( 'Sección', 'pro-elements' ),
					'container' => esc_html__( 'Contenedor', 'pro-elements' ),
				),
			)
		);
		$this->add_control(
			'play_speed',
			array(
				'label'      => esc_html__( 'Velocidad', 'pro-elements' ) . ' (x)',
				'type'       => Controls_Manager::SLIDER,
				'condition'  => array( 'trigger!' => 'bind_to_scroll' ),
				'default'    => array( 'size' => 1 ),
				'range'      => array( 'px' => array( 'min' => 0.1, 'max' => 5, 'step' => 0.1 ) ),
				'size_units' => array( 'px' ),
				'dynamic'    => array( 'active' => true ),
			)
		);
		$this->add_control( 'start_point', array( 'label' => esc_html__( 'Punto de inicio', 'pro-elements' ), 'type' => Controls_Manager::SLIDER, 'default' => array( 'size' => 0, 'unit' => '%' ), 'size_units' => array( '%' ) ) );
		$this->add_control( 'end_point', array( 'label' => esc_html__( 'Punto final', 'pro-elements' ), 'type' => Controls_Manager::SLIDER, 'default' => array( 'size' => 100, 'unit' => '%' ), 'size_units' => array( '%' ) ) );
		$this->add_control(
			'reverse_animation',
			array(
				'label'        => esc_html__( 'Invertir', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'conditions'   => array(
					'relation' => 'and',
					'terms'    => array(
						array( 'name' => 'trigger', 'operator' => '!==', 'value' => 'bind_to_scroll' ),
						array( 'name' => 'trigger', 'operator' => '!==', 'value' => 'on_hover' ),
					),
				),
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->add_control(
			'renderer',
			array(
				'label'     => esc_html__( 'Representación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'svg',
				'options'   => array(
					'svg'    => esc_html__( 'SVG', 'pro-elements' ),
					'canvas' => esc_html__( 'Canvas', 'pro-elements' ),
				),
				'separator' => 'before',
			)
		);
		$this->add_control( 'lazyload', array( 'label' => esc_html__( 'Carga diferida', 'pro-elements' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => esc_html__( 'Lottie', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'width', self::size_control( esc_html__( 'Ancho', 'pro-elements' ), '--lottie-container-width' ) );
		$this->add_responsive_control( 'space', self::size_control( esc_html__( 'Ancho máximo', 'pro-elements' ), '--lottie-container-max-width' ) + array( 'separator' => 'after' ) );
		$this->start_controls_tabs( 'image_effects' );
		$this->start_controls_tab( 'normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control( 'opacity', self::opacity_control( '--lottie-container-opacity' ) );
		$this->add_group_control( Group_Control_Css_Filter::get_type(), array( 'name' => 'css_filters', 'selector' => '{{WRAPPER}} .digi-lottie__container' ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_control( 'opacity_hover', self::opacity_control( '--lottie-container-opacity-hover' ) );
		$this->add_group_control( Group_Control_Css_Filter::get_type(), array( 'name' => 'css_filters_hover', 'selector' => '{{WRAPPER}} .digi-lottie__container:hover' ) );
		$this->add_control(
			'background_hover_transition',
			array(
				'label'     => esc_html__( 'Duración de la transición', 'pro-elements' ) . ' (s)',
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
				'selectors' => array( '{{WRAPPER}}' => '--lottie-container-transition-duration-hover: {{SIZE}}s' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_caption', array( 'label' => esc_html__( 'Leyenda', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'caption_source!' => 'none' ) ) );
		$this->add_control( 'caption_align', array( 'label' => esc_html__( 'Alineación', 'pro-elements' ), 'type' => Controls_Manager::CHOOSE, 'options' => self::align_options(), 'default' => 'center', 'selectors' => array( '{{WRAPPER}}' => '--caption-text-align: {{VALUE}};' ) ) );
		$this->add_control( 'text_color', array( 'label' => esc_html__( 'Color del texto', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'default' => '', 'selectors' => array( '{{WRAPPER}}' => '--caption-color: {{VALUE}};' ), 'global' => array( 'default' => Global_Colors::COLOR_TEXT ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'caption_typography', 'selector' => '{{WRAPPER}} .digi-lottie__caption', 'global' => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ) ) );
		$this->add_responsive_control(
			'caption_space',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array( 'px' => array( 'max' => 100 ), 'em' => array( 'max' => 10 ), 'rem' => array( 'max' => 10 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--caption-margin-top: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/** Izquierda, centro y derecha, como los dos controles de alineación de PRO. */
	private static function align_options(): array {
		return array(
			'left'   => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-text-align-left' ),
			'center' => array( 'title' => esc_html__( 'Centro', 'pro-elements' ), 'icon' => 'eicon-text-align-center' ),
			'right'  => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-text-align-right' ),
		);
	}

	/** «Ancho» y «Ancho máximo» de PRO: mismas unidades, rangos y valores por dispositivo, en su variable. */
	private static function size_control( string $label, string $variable ): array {
		return array(
			'label'          => $label,
			'type'           => Controls_Manager::SLIDER,
			'size_units'     => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
			'default'        => array( 'unit' => '%' ),
			'tablet_default' => array( 'unit' => '%' ),
			'mobile_default' => array( 'unit' => '%' ),
			'range'          => array(
				'%'   => array( 'min' => 1, 'max' => 100 ),
				'px'  => array( 'min' => 1, 'max' => 1000 ),
				'em'  => array( 'max' => 100 ),
				'rem' => array( 'max' => 100 ),
				'vw'  => array( 'min' => 1, 'max' => 100 ),
			),
			'selectors'      => array( '{{WRAPPER}}' => $variable . ': {{SIZE}}{{UNIT}};' ),
		);
	}

	/** Opacidad normal o con el cursor, en su variable. */
	private static function opacity_control( string $variable ): array {
		return array(
			'label'     => esc_html__( 'Opacidad', 'pro-elements' ),
			'type'      => Controls_Manager::SLIDER,
			'range'     => array( 'px' => array( 'max' => 1, 'min' => 0.10, 'step' => 0.01 ) ),
			'selectors' => array( '{{WRAPPER}}' => $variable . ': {{SIZE}};' ),
		);
	}

	/**
	 * Archivo de la animación, como `getAnimationPath()` de PRO: el JSON del adjunto si su dirección acaba en `json`, si
	 * no la URL externa guardada, y si no la animación de muestra propia.
	 */
	public static function animation_path( array $settings ): string {
		$json = (string) ( $settings['source_json']['url'] ?? '' );
		if ( '' !== $json && 'json' === strtolower( substr( $json, -4 ) ) ) {
			return $json;
		}
		$external = (string) ( $settings['source_external_url']['url'] ?? '' );
		if ( '' !== $external ) {
			return $external;
		}
		return defined( 'DIGITALISIMO_ELEMENTS_FILE' ) && function_exists( 'plugins_url' ) ? (string) plugins_url( self::SAMPLE, DIGITALISIMO_ELEMENTS_FILE ) : '';
	}

	/** Dirección de la librería (con su versión), para el script. */
	public static function library_url(): string {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! function_exists( 'plugins_url' ) ) {
			return '';
		}
		$url = (string) plugins_url( self::LIBRARY, DIGITALISIMO_ELEMENTS_FILE );
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'ver=' . self::LIBRARY_VERSION;
	}

	/**
	 * Leyenda con las reglas de `Caption_Helper` de PRO: la personalizada con un archivo (si hay leyenda) o con una URL
	 * externa (si no está vacía); el título o la leyenda del adjunto sólo si quien mira puede leerlo.
	 */
	public static function caption( array $settings ): string {
		$source  = (string) ( $settings['source'] ?? 'media_file' );
		$caption = (string) ( $settings['caption_source'] ?? 'none' );
		$custom  = (string) ( $settings['caption'] ?? '' );
		if ( ( 'external_url' === $source && '' !== $custom ) || ( 'custom' === $caption && 'media_file' === $source ) ) {
			return $custom;
		}
		if ( 'caption' !== $caption && 'title' !== $caption ) {
			return '';
		}
		$id = (int) ( $settings['source_json']['id'] ?? 0 );
		if ( ! $id || ! self::can_read( $id ) ) {
			return '';
		}
		if ( 'caption' === $caption ) {
			return function_exists( 'wp_get_attachment_caption' ) ? (string) wp_get_attachment_caption( $id ) : '';
		}
		return function_exists( 'get_the_title' ) ? (string) get_the_title( $id ) : '';
	}

	/** Como PRO: administrar el sitio, editar o leer la entrada. */
	private static function can_read( int $id ): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'manage_options' ) || current_user_can( 'edit_post', $id ) || current_user_can( 'read_post', $id );
	}

	/** Número de un control deslizante (`size`) o de un número, o el valor por defecto. */
	private static function number( $value, float $fallback = 0 ): float {
		$value = is_array( $value ) ? ( $value['size'] ?? '' ) : $value;
		return is_numeric( $value ) ? (float) $value : $fallback;
	}

	/** Configuración del script (`data-digi-lottie`). */
	public static function config( array $settings, string $id = '' ): array {
		$trigger = (string) ( $settings['trigger'] ?? 'arriving_to_viewport' );
		$link    = (array) ( $settings['custom_link'] ?? array() );
		$sizes   = (array) ( $settings['viewport']['sizes'] ?? array() );
		$config  = array(
			'lib'      => self::library_url(),
			'path'     => self::animation_path( $settings ),
			'name'     => 'digi-lottie-' . $id,
			'renderer' => 'canvas' === ( $settings['renderer'] ?? 'svg' ) ? 'canvas' : 'svg',
			'trigger'  => in_array( $trigger, array( 'arriving_to_viewport', 'on_click', 'on_hover', 'bind_to_scroll', 'none' ), true ) ? $trigger : 'arriving_to_viewport',
			'viewport' => array( self::number( $sizes['start'] ?? 0 ), self::number( $sizes['end'] ?? 100, 100 ) ),
			'relative' => 'page' === ( $settings['effects_relative_to'] ?? 'viewport' ) ? 'page' : 'viewport',
			'loop'     => 'yes' === ( $settings['loop'] ?? '' ),
			'times'    => (int) self::number( $settings['number_of_times'] ?? 0 ),
			'hoverOut' => (string) ( $settings['on_hover_out'] ?? 'default' ),
			'area'     => (string) ( $settings['hover_area'] ?? 'animation' ),
			'speed'    => self::number( $settings['play_speed'] ?? 1 ),
			'start'    => self::number( $settings['start_point'] ?? 0 ),
			'end'      => self::number( $settings['end_point'] ?? 100 ),
			'reverse'  => 'yes' === ( $settings['reverse_animation'] ?? '' ),
			'lazy'     => 'yes' === ( $settings['lazyload'] ?? '' ),
		);
		$timeout = (int) self::number( $settings['link_timeout'] ?? 0 );
		if ( 'on_click' === $trigger && 'custom' === ( $settings['link_to'] ?? 'none' ) && ! empty( $link['url'] ) && $timeout > 0 ) {
			$config['timeout'] = $timeout;
			$config['link']    = array( 'url' => esc_url_raw( (string) $link['url'] ), 'external' => 'on' === ( $link['is_external'] ?? '' ) );
		}
		return $config;
	}

	/**
	 * Marcado propio con la estructura de PRO: contenedor (`digi-lottie__container`) con la animación y la leyenda,
	 * dentro del enlace si lo hay, y después la descripción y el botón de DIGITALÍSIMO.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$caption  = self::caption( $settings );
		$link     = 'custom' === ( $settings['link_to'] ?? 'none' ) && ! empty( $settings['custom_link']['url'] );
		$attrs    = array(
			'class'           => 'digi-lottie__container',
			'data-digi-lottie' => wp_json_encode( self::config( $settings, (string) $this->get_id() ) ),
		);
		if ( ! $link && 'on_click' === ( $settings['trigger'] ?? '' ) ) {
			$attrs['role']       = 'button';
			$attrs['tabindex']   = '0';
			$attrs['aria-label'] = '' !== $caption ? $caption : esc_html__( 'Reproducir la animación', 'pro-elements' );
		}
		$this->add_render_attribute( 'container', $attrs );
		$html = '<div ' . $this->get_render_attribute_string( 'container' ) . '><div class="digi-lottie__animation" aria-hidden="true"></div>';
		if ( '' !== $caption ) {
			$html .= '<p class="digi-lottie__caption">' . esc_html( $caption ) . '</p>';
		}
		$html .= '</div>';
		if ( $link ) {
			$this->add_link_attributes( 'url', (array) $settings['custom_link'] );
			$this->add_render_attribute( 'url', 'class', 'digi-lottie__link' );
			if ( '' === $caption ) {
				$this->add_render_attribute( 'url', 'aria-label', self::link_name( $settings ) );
			}
			$html = '<a ' . $this->get_render_attribute_string( 'url' ) . '>' . $html . '</a>';
		}
		if ( class_exists( '\\Digitalisimo\\Elements\\Media_Text_Extras' ) ) {
			$html .= \Digitalisimo\Elements\Media_Text_Extras::extra( 'lottie', (array) $settings );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio escapado.
	}

	/** Nombre del enlace sin leyenda: el título del adjunto (si quien mira puede leerlo) o «Animación». */
	private static function link_name( array $settings ): string {
		$id    = (int) ( $settings['source_json']['id'] ?? 0 );
		$title = 'media_file' === ( $settings['source'] ?? 'media_file' ) && $id && self::can_read( $id ) && function_exists( 'get_the_title' ) ? trim( wp_strip_all_tags( (string) get_the_title( $id ) ) ) : '';
		return '' !== $title ? $title : esc_html__( 'Animación', 'pro-elements' );
	}
}
