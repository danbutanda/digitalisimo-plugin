<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Galería avanzada: cuadrícula, justificada o mampostería, con filtros por galería, superposición y animaciones al pasar
 * el cursor. Sustituye a la Gallery de PRO (`modules/gallery/widgets/gallery.php`, mapa
 * `modules/digitalisimo-convert/maps/gallery.php`): repite sus controles con el mismo nombre, valores por defecto,
 * opciones y condiciones, y las mismas variables CSS en el envoltorio, así que el mapa es todo `keep`.
 *
 * - **Sin la librería de galería del núcleo ni jQuery.** La cuadrícula es CSS (`grid`, columnas, separación y proporción
 *   por dispositivo con `selectors`); justificada y mampostería las coloca `assets/js/gallery.js` (≈ 3 KB, `defer`) con
 *   el mismo cálculo que la librería (altura de fila ideal, última fila sin estirar por debajo del 70 %, columna más baja
 *   con 5 px de margen), leyendo las medidas del adjunto que imprime el servidor (`data-w`, `data-h`) y las variables
 *   calculadas del dispositivo. Sin JavaScript, las dos se ven como una cuadrícula sin recorte.
 * - **Imágenes** con `wp_get_attachment_image()` y el tamaño de «Tamaño de la imagen» (`srcset`, `sizes`, `decoding`;
 *   «Carga diferida», `loading="lazy"`), un `<img>` con su texto alternativo en lugar del fondo CSS de PRO. Con
 *   «Carga diferida», cada imagen entra al verse y cargarse desde la mitad de su tamaño y sin opacidad, como con la
 *   librería de PRO, que sólo carga las que están en la vista (`data-digi-lazy`; lo pone el script, así que sin él se ven). Con «Personalizado», el recorte exacto de PRO
 *   (`Group_Control_Image_Size::get_attachment_image_src()` del núcleo) sin `srcset`.
 * - **Enlaces y lightbox** del núcleo (`add_lightbox_data_attributes()`, grupo por galería filtrada como PRO); el
 *   núcleo no carga nada hasta que se abre.
 * - **Filtros**: botones (`aria-pressed`, `aria-controls`) en un grupo con nombre; el estado inicial (el primer filtro,
 *   como el clic que PRO simula al iniciar) lo pinta el servidor, así que sin JavaScript se ve lo mismo. El indicador y
 *   su animación (`data-digi-fx="<familia>-<animación>"`) reproducen las reglas `e--pointer-*`/`e--animation-*` que PRO
 *   toma de la hoja de su Menú, en hojas propias que sólo se cargan con «Varias galerías» (`Module::STYLES`).
 * - **Superposición y animaciones** (imagen, fondo y contenido; también secuenciadas) con las clases propias del
 *   servidor, al pasar el cursor y con el foco del teclado; con `prefers-reduced-motion`, sin transiciones.
 *
 * Deriva de PRO Elements (GPL): `modules/gallery/widgets/gallery.php` (controles, textos y datos de cada imagen),
 * `modules/gallery/assets/js/frontend/handler.js` y la librería de galería del núcleo (cálculo de justificada y
 * mampostería), `assets/css/widget-gallery.min.css` y `assets/css/conditionals/transitions.min.css` (aspecto y
 * animaciones por defecto).
 */
class Gallery extends \Elementor\Widget_Base {
	/**
	 * Animaciones al pasar el cursor (las de los cuatro controles de animación de PRO): transformación y opacidad en
	 * reposo (`--_t0`, `--_o0`) y con el cursor o el foco (`--_t1`, `--_o1`), los mismos valores que
	 * `assets/css/conditionals/transitions.min.css` de PRO. Van en línea en el elemento animado (`digi-gallery__fx`), así
	 * la hoja sólo lleva dos reglas.
	 */
	const FX = array(
		'grow'                  => '--_t1:scale(1.1)',
		'shrink'                => '--_t1:scale(.85)',
		'shrink-contained'      => '--_t0:scale(1.17);--_t1:scale(1)',
		'move-contained-left'   => '--_t0:scale(1.2);--_t1:scale(1.2) translate(-8%,0)',
		'move-contained-right'  => '--_t0:scale(1.2);--_t1:scale(1.2) translate(8%,0)',
		'move-contained-top'    => '--_t0:scale(1.2);--_t1:scale(1.2) translate(0,-8%)',
		'move-contained-bottom' => '--_t0:scale(1.2);--_t1:scale(1.2) translate(0,8%)',
		'enter-from-right'      => '--_t0:translateX(1000px);--_o0:0',
		'enter-from-left'       => '--_t0:translateX(-1000px);--_o0:0',
		'enter-from-top'        => '--_t0:translateY(-600px);--_o0:0',
		'enter-from-bottom'     => '--_t0:translateY(500px);--_o0:0',
		'enter-zoom-in'         => '--_t0:scale(.2);--_o0:0',
		'enter-zoom-out'        => '--_t0:scale(2);--_o0:0',
		'fade-in'               => '--_o0:0',
		'exit-to-right'         => '--_t1:translateX(1000px);--_o1:0',
		'exit-to-left'          => '--_t1:translateX(-1000px);--_o1:0',
		'exit-to-top'           => '--_t1:translateY(-600px);--_o1:0',
		'exit-to-bottom'        => '--_t1:translateY(600px);--_o1:0',
		'exit-zoom-in'          => '--_t1:scale(2);--_o1:0',
		'exit-zoom-out'         => '--_t1:scale(.2);--_o1:0',
		'fade-out'              => '--_o1:0',
		'move-right'            => '--_t1:translateX(30px)',
		'move-left'             => '--_t1:translateX(-30px)',
		'move-up'               => '--_t1:translateY(-30px)',
		'move-down'             => '--_t1:translateY(30px)',
	);

	public function get_name() { return 'digitalisimo-gallery'; }
	public function get_title() { return esc_html__( 'Galería avanzada', 'pro-elements' ); }
	public function get_icon() { return 'eicon-gallery-justified'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'galería', 'gallery', 'imágenes', 'justificada', 'mampostería', 'masonry', 'filtro', 'lightbox' ); }
	/** Familia de cada indicador y la animación que PRO toma de ella (la primera clave `animation*` activa). */
	const POINTERS = array(
		'underline'   => array( 'line', 'animation_line', 'fade' ),
		'overline'    => array( 'line', 'animation_line', 'fade' ),
		'double-line' => array( 'line', 'animation_line', 'fade' ),
		'framed'      => array( 'framed', 'animation_framed', 'fade' ),
		'background'  => array( 'background', 'animation_background', 'fade' ),
		'text'        => array( 'text', 'animation_text', 'grow' ),
	);

	/**
	 * Hojas además de la propia (`Module::STYLES`, registradas por el módulo): la barra de filtros con las líneas y el
	 * texto, y las del marco y del fondo. Sólo con «Varias galerías» (la de la familia del indicador elegido); en el
	 * editor, todas.
	 */
	public function get_style_depends(): array {
		$all = array_merge( Module::style_depends( $this->get_name() ), array( 'digitalisimo-gallery-filters', 'digitalisimo-gallery-filters-framed', 'digitalisimo-gallery-filters-background' ) );
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) {
			return $all;
		}
		return self::styles_for( (array) $this->get_settings() );
	}

	/** Hojas que necesitan unos ajustes. */
	public static function styles_for( array $settings ): array {
		$out = Module::style_depends( 'digitalisimo-gallery' );
		if ( 'multiple' !== ( $settings['gallery_type'] ?? 'single' ) ) {
			return $out;
		}
		$out[]   = 'digitalisimo-gallery-filters';
		$pointer = (string) ( $settings['pointer'] ?? 'underline' );
		if ( in_array( $pointer, array( 'framed', 'background' ), true ) ) {
			$out[] = 'digitalisimo-gallery-filters-' . $pointer;
		}
		return $out;
	}

	/**
	 * Animación del indicador: `<familia>-<animación>` con el valor de la clave de su familia, o '' sin indicador. Un
	 * indicador desconocido es el subrayado (como la clase de la barra) y una animación que no es una opción del control,
	 * la de por defecto.
	 */
	public static function pointer_fx( array $settings ): string {
		$pointer = (string) ( $settings['pointer'] ?? 'underline' );
		if ( 'none' === $pointer ) {
			return '';
		}
		$pointer = isset( self::POINTERS[ $pointer ] ) ? $pointer : 'underline';
		list( $family, $key, $default ) = self::POINTERS[ $pointer ];
		$options = array(
			'line'       => array( 'fade', 'slide', 'grow', 'drop-in', 'drop-out', 'none' ),
			'framed'     => array( 'fade', 'grow', 'shrink', 'draw', 'corners', 'none' ),
			'background' => array( 'fade', 'grow', 'shrink', 'sweep-left', 'sweep-right', 'sweep-up', 'sweep-down', 'shutter-in-vertical', 'shutter-out-vertical', 'shutter-in-horizontal', 'shutter-out-horizontal', 'none' ),
			'text'       => array( 'grow', 'shrink', 'sink', 'float', 'skew', 'rotate', 'none' ),
		);
		$value = (string) ( $settings[ $key ] ?? $default );
		return $family . '-' . ( in_array( $value, $options[ $family ], true ) ? $value : $default );
	}
	public function get_script_depends(): array { return Module::script_depends( $this->get_name() ); }
	protected function is_dynamic_content(): bool { return false; }

	/** Opciones de las animaciones de PRO (mismos valores, textos en español). */
	private static function fx_options( array $groups ): array {
		$labels = array(
			'none'     => array( '' => esc_html__( 'Ninguna', 'pro-elements' ) ),
			'entrance' => array(
				'enter-from-right'  => esc_html__( 'Entrar desde la derecha', 'pro-elements' ),
				'enter-from-left'   => esc_html__( 'Entrar desde la izquierda', 'pro-elements' ),
				'enter-from-top'    => esc_html__( 'Entrar desde arriba', 'pro-elements' ),
				'enter-from-bottom' => esc_html__( 'Entrar desde abajo', 'pro-elements' ),
				'enter-zoom-in'     => esc_html__( 'Acercar', 'pro-elements' ),
				'enter-zoom-out'    => esc_html__( 'Alejar', 'pro-elements' ),
				'fade-in'           => esc_html__( 'Aparecer', 'pro-elements' ),
			),
			'reaction' => array(
				'grow'       => esc_html__( 'Crecer', 'pro-elements' ),
				'shrink'     => esc_html__( 'Encoger', 'pro-elements' ),
				'move-right' => esc_html__( 'Mover a la derecha', 'pro-elements' ),
				'move-left'  => esc_html__( 'Mover a la izquierda', 'pro-elements' ),
				'move-up'    => esc_html__( 'Mover arriba', 'pro-elements' ),
				'move-down'  => esc_html__( 'Mover abajo', 'pro-elements' ),
			),
			'exit'     => array(
				'exit-to-right'  => esc_html__( 'Salir por la derecha', 'pro-elements' ),
				'exit-to-left'   => esc_html__( 'Salir por la izquierda', 'pro-elements' ),
				'exit-to-top'    => esc_html__( 'Salir por arriba', 'pro-elements' ),
				'exit-to-bottom' => esc_html__( 'Salir por abajo', 'pro-elements' ),
				'exit-zoom-in'   => esc_html__( 'Acercar', 'pro-elements' ),
				'exit-zoom-out'  => esc_html__( 'Alejar', 'pro-elements' ),
				'fade-out'       => esc_html__( 'Desvanecer', 'pro-elements' ),
			),
		);
		$titles = array(
			'none'     => esc_html__( 'Ninguna', 'pro-elements' ),
			'entrance' => esc_html__( 'Entrada', 'pro-elements' ),
			'reaction' => esc_html__( 'Reacción', 'pro-elements' ),
			'exit'     => esc_html__( 'Salida', 'pro-elements' ),
		);
		$out = array();
		foreach ( $groups as $group ) {
			$out[] = array( 'label' => $titles[ $group ], 'options' => $labels[ $group ] );
		}
		return $out;
	}

	/** Alineación izquierda, centro y derecha (los iconos del núcleo). */
	private static function align_options(): array {
		return array(
			'left'   => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-text-align-left' ),
			'center' => array( 'title' => esc_html__( 'Centro', 'pro-elements' ), 'icon' => 'eicon-text-align-center' ),
			'right'  => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-text-align-right' ),
		);
	}

	/** Etiquetas de los datos del adjunto para la superposición. */
	private static function data_options(): array {
		return array(
			''            => esc_html__( 'Ninguno', 'pro-elements' ),
			'title'       => esc_html__( 'Título', 'pro-elements' ),
			'caption'     => esc_html__( 'Leyenda', 'pro-elements' ),
			'alt'         => esc_html__( 'Texto alternativo', 'pro-elements' ),
			'description' => esc_html__( 'Descripción', 'pro-elements' ),
		);
	}

	protected function register_controls() {
		$this->register_settings_controls();
		$this->register_filter_bar_controls();
		$this->register_overlay_controls();
		$this->register_image_style_controls();
		$this->register_overlay_style_controls();
		$this->register_content_style_controls();
		$this->register_filter_bar_style_controls();
	}

	private function register_settings_controls() {
		$this->start_controls_section( 'settings', array( 'label' => esc_html__( 'Ajustes', 'pro-elements' ) ) );
		$this->add_control(
			'gallery_type',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'default' => 'single',
				'options' => array(
					'single'   => esc_html__( 'Una galería', 'pro-elements' ),
					'multiple' => esc_html__( 'Varias galerías', 'pro-elements' ),
				),
			)
		);
		$this->add_control(
			'gallery',
			array(
				'type'      => Controls_Manager::GALLERY,
				'condition' => array( 'gallery_type' => 'single' ),
				'dynamic'   => array( 'active' => true ),
			)
		);
		$repeater = new Repeater();
		$repeater->add_control(
			'gallery_title',
			array(
				'type'    => Controls_Manager::TEXT,
				'label'   => esc_html__( 'Título', 'pro-elements' ),
				'default' => esc_html__( 'Nueva galería', 'pro-elements' ),
				'dynamic' => array( 'active' => true ),
			)
		);
		$repeater->add_control(
			'multiple_gallery',
			array(
				'type'    => Controls_Manager::GALLERY,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_control(
			'galleries',
			array(
				'type'        => Controls_Manager::REPEATER,
				'label'       => esc_html__( 'Galerías', 'pro-elements' ),
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ gallery_title }}}',
				'default'     => array( array( 'gallery_title' => esc_html__( 'Nueva galería', 'pro-elements' ) ) ),
				'condition'   => array( 'gallery_type' => 'multiple' ),
			)
		);
		$this->add_control(
			'order_by',
			array(
				'type'    => Controls_Manager::SELECT,
				'label'   => esc_html__( 'Ordenar por', 'pro-elements' ),
				'options' => array(
					''       => esc_html__( 'Predeterminado', 'pro-elements' ),
					'random' => esc_html__( 'Aleatorio', 'pro-elements' ),
				),
				'default' => '',
			)
		);
		$this->add_control(
			'lazyload',
			array(
				'type'               => Controls_Manager::SWITCHER,
				'label'              => esc_html__( 'Carga diferida', 'pro-elements' ),
				'return_value'       => 'yes',
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'gallery_layout',
			array(
				'type'               => Controls_Manager::SELECT,
				'label'              => esc_html__( 'Disposición', 'pro-elements' ),
				'default'            => 'grid',
				'options'            => array(
					'grid'      => esc_html__( 'Cuadrícula', 'pro-elements' ),
					'justified' => esc_html__( 'Justificada', 'pro-elements' ),
					'masonry'   => esc_html__( 'Mampostería', 'pro-elements' ),
				),
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);
		// Columnas, altura de fila y separación: variables del dispositivo que leen la hoja (cuadrícula) y el script
		// (justificada y mampostería); el panel vuelve a pintar el widget al cambiarlas.
		$this->add_responsive_control(
			'columns',
			array(
				'label'              => esc_html__( 'Columnas', 'pro-elements' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => 4,
				'tablet_default'     => 2,
				'mobile_default'     => 1,
				'min'                => 1,
				'max'                => 24,
				'condition'          => array( 'gallery_layout!' => 'justified' ),
				'render_type'        => 'template',
				'frontend_available' => true,
				'selectors'          => array( '{{WRAPPER}}' => '--columns: {{VALUE}}' ),
			)
		);
		$row_height_args = array();
		$gap_args        = array();
		foreach ( self::breakpoints() as $breakpoint ) {
			if ( 'widescreen' !== $breakpoint ) {
				$row_height_args[ $breakpoint ] = array( 'default' => array( 'size' => 150 ) );
				$gap_args[ $breakpoint ]        = array( 'default' => array( 'size' => 10 ) );
			}
		}
		$this->add_responsive_control(
			'ideal_row_height',
			array(
				'label'              => esc_html__( 'Altura de la fila', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'range'              => array( 'px' => array( 'min' => 50, 'max' => 500 ) ),
				'default'            => array( 'size' => 200 ),
				'device_args'        => $row_height_args,
				'condition'          => array( 'gallery_layout' => 'justified' ),
				'required'           => true,
				'render_type'        => 'template',
				'frontend_available' => true,
				'selectors'          => array( '{{WRAPPER}}' => '--digi-gallery-row: {{SIZE}}' ),
			)
		);
		$this->add_responsive_control(
			'gap',
			array(
				'label'              => esc_html__( 'Separación', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'default'            => array( 'size' => 10 ),
				'device_args'        => $gap_args,
				'required'           => true,
				'render_type'        => 'template',
				'frontend_available' => true,
				'selectors'          => array( '{{WRAPPER}}' => '--hgap: {{SIZE}}px; --vgap: {{SIZE}}px' ),
			)
		);
		$this->add_control(
			'link_to',
			array(
				'label'              => esc_html__( 'Enlace', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'file',
				'options'            => array(
					''       => esc_html__( 'Ninguno', 'pro-elements' ),
					'file'   => esc_html__( 'Archivo multimedia', 'pro-elements' ),
					'custom' => esc_html__( 'URL personalizada', 'pro-elements' ),
				),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'url',
			array(
				'label'              => esc_html__( 'URL', 'pro-elements' ),
				'type'               => Controls_Manager::URL,
				'condition'          => array( 'link_to' => 'custom' ),
				'frontend_available' => true,
				'dynamic'            => array( 'active' => true ),
			)
		);
		$this->add_control(
			'open_lightbox',
			array(
				'label'       => esc_html__( 'Lightbox', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT,
				'description' => esc_html__( 'Los ajustes generales del lightbox están en los ajustes del sitio.', 'pro-elements' ),
				'default'     => 'default',
				'options'     => array(
					'default' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'yes'     => esc_html__( 'Sí', 'pro-elements' ),
					'no'      => esc_html__( 'No', 'pro-elements' ),
				),
				'condition'   => array( 'link_to' => 'file' ),
			)
		);
		$this->add_control(
			'aspect_ratio',
			array(
				'type'                 => Controls_Manager::SELECT,
				'label'                => esc_html__( 'Proporción', 'pro-elements' ),
				'default'              => '3:2',
				'options'              => array(
					'1:1'  => '1:1',
					'3:2'  => '3:2',
					'4:3'  => '4:3',
					'9:16' => '9:16',
					'16:9' => '16:9',
					'21:9' => '21:9',
				),
				'condition'            => array( 'gallery_layout' => 'grid' ),
				'render_type'          => 'none',
				'frontend_available'   => true,
				'selectors_dictionary' => array( '1:1' => '1/1', '3:2' => '3/2', '4:3' => '4/3', '9:16' => '9/16', '16:9' => '16/9', '21:9' => '21/9' ),
				'selectors'            => array( '{{WRAPPER}}' => '--digi-gallery-ratio: {{VALUE}}' ),
			)
		);
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'thumbnail_image',
				'default' => 'medium',
			)
		);
		$this->end_controls_section();
	}

	private function register_filter_bar_controls() {
		$this->start_controls_section( 'section_filter_bar_content', array( 'label' => esc_html__( 'Barra de filtros', 'pro-elements' ), 'condition' => array( 'gallery_type' => 'multiple' ) ) );
		$this->add_control(
			'show_all_galleries',
			array(
				'type'               => Controls_Manager::SWITCHER,
				'label'              => esc_html__( 'Filtro «Todas»', 'pro-elements' ),
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'show_all_galleries_label',
			array(
				'type'      => Controls_Manager::TEXT,
				'label'     => esc_html__( 'Texto del filtro «Todas»', 'pro-elements' ),
				'default'   => esc_html__( 'Todas', 'pro-elements' ),
				'condition' => array( 'show_all_galleries' => 'yes' ),
				'dynamic'   => array( 'active' => true ),
				'ai'        => array( 'active' => false ),
			)
		);
		$this->add_control(
			'pointer',
			array(
				'label'          => esc_html__( 'Indicador', 'pro-elements' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => 'underline',
				'options'        => array(
					'none'        => esc_html__( 'Ninguno', 'pro-elements' ),
					'underline'   => esc_html__( 'Subrayado', 'pro-elements' ),
					'overline'    => esc_html__( 'Línea superior', 'pro-elements' ),
					'double-line' => esc_html__( 'Doble línea', 'pro-elements' ),
					'framed'      => esc_html__( 'Marco', 'pro-elements' ),
					'background'  => esc_html__( 'Fondo', 'pro-elements' ),
					'text'        => esc_html__( 'Texto', 'pro-elements' ),
				),
				'style_transfer' => true,
			)
		);
		$this->add_control(
			'animation_line',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'     => esc_html__( 'Fundido', 'pro-elements' ),
					'slide'    => esc_html__( 'Deslizar', 'pro-elements' ),
					'grow'     => esc_html__( 'Crecer', 'pro-elements' ),
					'drop-in'  => esc_html__( 'Caer hacia dentro', 'pro-elements' ),
					'drop-out' => esc_html__( 'Caer hacia fuera', 'pro-elements' ),
					'none'     => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array( 'pointer' => array( 'underline', 'overline', 'double-line' ) ),
			)
		);
		$this->add_control(
			'animation_framed',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'    => esc_html__( 'Fundido', 'pro-elements' ),
					'grow'    => esc_html__( 'Crecer', 'pro-elements' ),
					'shrink'  => esc_html__( 'Encoger', 'pro-elements' ),
					'draw'    => esc_html__( 'Dibujar', 'pro-elements' ),
					'corners' => esc_html__( 'Esquinas', 'pro-elements' ),
					'none'    => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array( 'pointer' => 'framed' ),
			)
		);
		$this->add_control(
			'animation_background',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'fade',
				'options'   => array(
					'fade'                   => esc_html__( 'Fundido', 'pro-elements' ),
					'grow'                   => esc_html__( 'Crecer', 'pro-elements' ),
					'shrink'                 => esc_html__( 'Encoger', 'pro-elements' ),
					'sweep-left'             => esc_html__( 'Barrido a la izquierda', 'pro-elements' ),
					'sweep-right'            => esc_html__( 'Barrido a la derecha', 'pro-elements' ),
					'sweep-up'               => esc_html__( 'Barrido hacia arriba', 'pro-elements' ),
					'sweep-down'             => esc_html__( 'Barrido hacia abajo', 'pro-elements' ),
					'shutter-in-vertical'    => esc_html__( 'Persiana vertical hacia dentro', 'pro-elements' ),
					'shutter-out-vertical'   => esc_html__( 'Persiana vertical hacia fuera', 'pro-elements' ),
					'shutter-in-horizontal'  => esc_html__( 'Persiana horizontal hacia dentro', 'pro-elements' ),
					'shutter-out-horizontal' => esc_html__( 'Persiana horizontal hacia fuera', 'pro-elements' ),
					'none'                   => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array( 'pointer' => 'background' ),
			)
		);
		$this->add_control(
			'animation_text',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'grow',
				'options'   => array(
					'grow'   => esc_html__( 'Crecer', 'pro-elements' ),
					'shrink' => esc_html__( 'Encoger', 'pro-elements' ),
					'sink'   => esc_html__( 'Hundir', 'pro-elements' ),
					'float'  => esc_html__( 'Flotar', 'pro-elements' ),
					'skew'   => esc_html__( 'Inclinar', 'pro-elements' ),
					'rotate' => esc_html__( 'Girar', 'pro-elements' ),
					'none'   => esc_html__( 'Ninguna', 'pro-elements' ),
				),
				'condition' => array( 'pointer' => 'text' ),
			)
		);
		$this->end_controls_section();
	}

	private function register_overlay_controls() {
		$this->start_controls_section( 'overlay', array( 'label' => esc_html__( 'Superposición', 'pro-elements' ) ) );
		$this->add_control(
			'overlay_background',
			array(
				'label'              => esc_html__( 'Fondo', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'overlay_title',
			array(
				'label'              => esc_html__( 'Título', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '',
				'options'            => self::data_options(),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'overlay_description',
			array(
				'label'              => esc_html__( 'Descripción', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '',
				'options'            => self::data_options(),
				'frontend_available' => true,
			)
		);
		$this->end_controls_section();
	}

	private function register_image_style_controls() {
		$this->start_controls_section( 'image_style', array( 'label' => esc_html__( 'Imagen', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->start_controls_tabs( 'image_tabs' );
		$this->start_controls_tab( 'image_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control(
			'image_border_color',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--image-border-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array( 'px' => array( 'max' => 20 ), 'em' => array( 'max' => 2 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--image-border-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--image-border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'image_css_filters',
				'selector' => '{{WRAPPER}} .digi-gallery__image',
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( 'image_hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_control(
			'image_border_color_hover',
			array(
				'label'     => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-gallery__item:hover' => 'border-color: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'image_border_radius_hover',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-gallery__item:hover' => 'border-radius: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_group_control(
			Group_Control_Css_Filter::get_type(),
			array(
				'name'     => 'image_css_filters_hover',
				'selector' => '{{WRAPPER}} .digi-gallery__item:hover .digi-gallery__image',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control(
			'image_hover_animation',
			array(
				'label'              => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					''                      => esc_html__( 'Ninguna', 'pro-elements' ),
					'grow'                  => esc_html__( 'Acercar', 'pro-elements' ),
					'shrink-contained'      => esc_html__( 'Alejar', 'pro-elements' ),
					'move-contained-left'   => esc_html__( 'Mover a la izquierda', 'pro-elements' ),
					'move-contained-right'  => esc_html__( 'Mover a la derecha', 'pro-elements' ),
					'move-contained-top'    => esc_html__( 'Mover arriba', 'pro-elements' ),
					'move-contained-bottom' => esc_html__( 'Mover abajo', 'pro-elements' ),
				),
				'separator'          => 'before',
				'default'            => '',
				'frontend_available' => true,
				'render_type'        => 'template',
			)
		);
		$this->add_control(
			'image_animation_duration',
			array(
				'label'     => esc_html__( 'Duración de la animación', 'pro-elements' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 800 ),
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3000, 'step' => 100 ) ),
				'selectors' => array( '{{WRAPPER}}' => '--image-transition-duration: {{SIZE}}ms' ),
			)
		);
		$this->end_controls_section();
	}

	private function register_overlay_style_controls() {
		$this->start_controls_section( 'overlay_style', array( 'label' => esc_html__( 'Superposición', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'overlay_background' => 'yes' ) ) );
		$this->start_controls_tabs( 'overlay_background_tabs' );
		$this->start_controls_tab( 'overlay_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_background',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => '{{WRAPPER}} .digi-gallery__overlay',
				'fields_options' => array( 'background' => array( 'label' => esc_html__( 'Superposición', 'pro-elements' ) ) ),
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( 'overlay_hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_background_hover',
				'types'          => array( 'classic', 'gradient' ),
				'selector'       => '{{WRAPPER}} .digi-gallery__item:hover .digi-gallery__overlay, {{WRAPPER}} .digi-gallery__item:focus-visible .digi-gallery__overlay',
				'exclude'        => array( 'image' ),
				'fields_options' => array(
					'background' => array( 'default' => 'classic' ),
					'color'      => array( 'default' => 'rgba(0,0,0,0.5)' ),
				),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control(
			'image_blend_mode',
			array(
				'label'       => esc_html__( 'Modo de mezcla', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''            => esc_html__( 'Normal', 'pro-elements' ),
					'multiply'    => 'Multiply',
					'screen'      => 'Screen',
					'overlay'     => 'Overlay',
					'darken'      => 'Darken',
					'lighten'     => 'Lighten',
					'color-dodge' => 'Color Dodge',
					'color-burn'  => 'Color Burn',
					'hue'         => 'Hue',
					'saturation'  => 'Saturation',
					'color'       => 'Color',
					'exclusion'   => 'Exclusion',
					'luminosity'  => 'Luminosity',
				),
				'selectors'   => array( '{{WRAPPER}}' => '--overlay-mix-blend-mode: {{VALUE}}' ),
				'separator'   => 'before',
				'render_type' => 'ui',
			)
		);
		$this->add_control(
			'background_overlay_hover_animation',
			array(
				'label'              => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'groups'             => self::fx_options( array( 'none', 'entrance', 'exit' ) ),
				'separator'          => 'before',
				'default'            => '',
				'frontend_available' => true,
				'render_type'        => 'template',
			)
		);
		$this->add_control(
			'background_overlay_animation_duration',
			array(
				'label'     => esc_html__( 'Duración de la animación', 'pro-elements' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 800 ),
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3000, 'step' => 100 ) ),
				'selectors' => array( '{{WRAPPER}}' => '--overlay-transition-duration: {{SIZE}}ms' ),
			)
		);
		$this->end_controls_section();
	}

	private function register_content_style_controls() {
		$this->start_controls_section( 'overlay_content_style', array( 'label' => esc_html__( 'Contenido', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'content_alignment',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => self::align_options(),
				'default'   => 'center',
				'selectors' => array( '{{WRAPPER}}' => '--content-text-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'content_vertical_position',
			array(
				'label'                => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'top'    => array( 'title' => esc_html__( 'Arriba', 'pro-elements' ), 'icon' => 'eicon-v-align-top' ),
					'middle' => array( 'title' => esc_html__( 'En medio', 'pro-elements' ), 'icon' => 'eicon-v-align-middle' ),
					'bottom' => array( 'title' => esc_html__( 'Abajo', 'pro-elements' ), 'icon' => 'eicon-v-align-bottom' ),
				),
				'selectors_dictionary' => array( 'top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end' ),
				'selectors'            => array( '{{WRAPPER}}' => '--content-justify-content: {{VALUE}}' ),
			)
		);
		$this->add_responsive_control(
			'content_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array( 'size' => 20 ),
				'selectors'  => array( '{{WRAPPER}}' => '--content-padding: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_control(
			'heading_title',
			array(
				'label'     => esc_html__( 'Título', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'overlay_title!' => '' ),
			)
		);
		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--title-text-color: {{VALUE}}' ),
				'condition' => array( 'overlay_title!' => '' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'title_typography',
				'global'    => array( 'default' => 'globals/typography?id=primary' ),
				'selector'  => '{{WRAPPER}} .digi-gallery__title',
				'condition' => array( 'overlay_title!' => '' ),
			)
		);
		$this->add_control(
			'title_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}}' => '--description-margin-top: {{SIZE}}{{UNIT}}' ),
				'condition'  => array( 'overlay_title!' => '' ),
			)
		);
		$this->add_control(
			'heading_description',
			array(
				'label'     => esc_html__( 'Descripción', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array( 'overlay_description!' => '' ),
			)
		);
		$this->add_control(
			'description_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--description-text-color: {{VALUE}}' ),
				'condition' => array( 'overlay_description!' => '' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'description_typography',
				'global'    => array( 'default' => 'globals/typography?id=text' ),
				'selector'  => '{{WRAPPER}} .digi-gallery__description',
				'condition' => array( 'overlay_description!' => '' ),
			)
		);
		$this->add_control(
			'content_hover_animation',
			array(
				'label'              => esc_html__( 'Animación al pasar el cursor', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'groups'             => self::fx_options( array( 'none', 'entrance', 'reaction', 'exit' ) ),
				'default'            => 'fade-in',
				'separator'          => 'before',
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'content_animation_duration',
			array(
				'label'     => esc_html__( 'Duración de la animación', 'pro-elements' ) . ' (ms)',
				'type'      => Controls_Manager::SLIDER,
				'default'   => array( 'size' => 800 ),
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 3000, 'step' => 100 ) ),
				'selectors' => array( '{{WRAPPER}}' => '--content-transition-duration: {{SIZE}}ms; --content-transition-delay: {{SIZE}}ms;' ),
				'condition' => array( 'content_hover_animation!' => '' ),
			)
		);
		$this->add_control(
			'content_sequenced_animation',
			array(
				'label'              => esc_html__( 'Animación secuenciada', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'condition'          => array( 'content_hover_animation!' => '' ),
				'frontend_available' => true,
				'render_type'        => 'template',
			)
		);
		$this->end_controls_section();
	}

	private function register_filter_bar_style_controls() {
		$this->start_controls_section( 'filter_bar_style', array( 'label' => esc_html__( 'Barra de filtros', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'gallery_type' => 'multiple' ) ) );
		$this->add_control(
			'align_filter_bar_items',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => self::align_options(),
				'prefix_class'         => 'digi-gallery--filter-align-',
				'selectors_dictionary' => array( 'left' => 'flex-start', 'right' => 'flex-end' ),
				'selectors'            => array( '{{WRAPPER}}' => '--titles-container-justify-content: {{VALUE}}' ),
			)
		);
		$this->start_controls_tabs( 'filter_bar_colors' );
		$this->start_controls_tab( 'filter_bar_colors_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control(
			'galleries_title_color_normal',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=primary' ),
				'selectors' => array( '{{WRAPPER}}' => '--galleries-title-color-normal: {{VALUE}}' ),
			)
		);
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'galleries_titles_typography',
				'selector' => '{{WRAPPER}} .digi-gallery__filter',
				'global'   => array( 'default' => 'globals/typography?id=primary' ),
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( 'filter_bar_colors_hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_control(
			'galleries_title_color_hover',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=secondary' ),
				'selectors' => array( '{{WRAPPER}}' => '--galleries-title-color-hover: {{VALUE}}' ),
				'condition' => array( 'pointer!' => 'background' ),
			)
		);
		$this->add_control(
			'galleries_title_color_hover_pointer_bg',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'selectors' => array( '{{WRAPPER}}' => '--galleries-title-color-hover: {{VALUE}}' ),
				'condition' => array( 'pointer' => 'background' ),
			)
		);
		$this->add_control(
			'galleries_pointer_color_hover',
			array(
				'label'     => esc_html__( 'Color del indicador', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=accent' ),
				'selectors' => array( '{{WRAPPER}}' => '--galleries-pointer-bg-color-hover: {{VALUE}}' ),
				'condition' => array( 'pointer!' => array( 'none', 'text' ) ),
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( 'filter_bar_colors_active', array( 'label' => esc_html__( 'Activo', 'pro-elements' ) ) );
		$this->add_control(
			'galleries_title_color_active',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=secondary' ),
				'selectors' => array( '{{WRAPPER}}' => '--gallery-title-color-active: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'galleries_pointer_color_active',
			array(
				'label'     => esc_html__( 'Color del indicador', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=accent' ),
				'selectors' => array( '{{WRAPPER}}' => '--galleries-pointer-bg-color-active: {{VALUE}}' ),
				'condition' => array( 'pointer!' => array( 'none', 'text' ) ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_control(
			'pointer_width',
			array(
				'label'      => esc_html__( 'Grosor del indicador', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'devices'    => array( 'desktop', 'tablet' ),
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array( 'px' => array( 'max' => 30 ) ),
				'selectors'  => array( '{{WRAPPER}}' => '--galleries-pointer-border-width: {{SIZE}}{{UNIT}}' ),
				'separator'  => 'before',
				'condition'  => array( 'pointer' => array( 'underline', 'overline', 'double-line', 'framed' ) ),
			)
		);
		$this->add_control(
			'galleries_titles_space_between',
			array(
				'label'      => esc_html__( 'Espacio entre filtros', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-gallery__filter' => '--space-between: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_control(
			'galleries_titles_gap',
			array(
				'label'      => esc_html__( 'Separación inferior', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-gallery__filters' => 'margin-bottom: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->end_controls_section();
	}

	/** Puntos de corte activos del núcleo (sin el gestor, móvil y tableta). */
	private static function breakpoints(): array {
		$plugin = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance : null;
		if ( is_object( $plugin ) && isset( $plugin->breakpoints ) && is_object( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ) {
			return array_keys( (array) $plugin->breakpoints->get_active_breakpoints() );
		}
		return array( 'mobile', 'tablet' );
	}

	private static function edit_mode(): bool {
		return class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && is_object( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/** Atributos de un elemento animado (`class` y `style`) o '' sin animación válida. */
	private static function fx( $value ): string {
		$value = (string) $value;
		return isset( self::FX[ $value ] ) ? ' digi-gallery__fx" style="' . esc_attr( self::FX[ $value ] ) : '';
	}

	/**
	 * Galerías que se pintan: `[ índice => lista de imágenes ]` (el índice de la fila del repetidor, como PRO, que salta
	 * las galerías vacías y conserva los índices) y los filtros `[ índice => título ]`.
	 */
	private static function galleries( array $settings ): array {
		$galleries = array();
		if ( 'multiple' === ( $settings['gallery_type'] ?? 'single' ) ) {
			foreach ( array_values( (array) ( $settings['galleries'] ?? array() ) ) as $index => $row ) {
				if ( is_array( $row ) && ! empty( $row['multiple_gallery'] ) && is_array( $row['multiple_gallery'] ) ) {
					$galleries[ $index ] = $row['multiple_gallery'];
				}
			}
		} elseif ( ! empty( $settings['gallery'] ) && is_array( $settings['gallery'] ) ) {
			$galleries[0] = $settings['gallery'];
		}
		return $galleries;
	}

	/**
	 * Imágenes únicas con las galerías en las que están (`[ id => [ índices ] ]`), en el orden de PRO (o barajadas
	 * con «Aleatorio»).
	 */
	public static function items( array $settings ): array {
		$items = array();
		foreach ( self::galleries( $settings ) as $index => $gallery ) {
			foreach ( $gallery as $image ) {
				$id = is_array( $image ) ? (int) ( $image['id'] ?? 0 ) : 0;
				if ( $id > 0 ) {
					$items[ $id ][] = $index;
				}
			}
		}
		if ( 'random' === ( $settings['order_by'] ?? '' ) ) {
			$keys = array_keys( $items );
			shuffle( $keys );
			$items = array_replace( array_flip( $keys ), $items );
		}
		return $items;
	}

	/**
	 * Datos de una imagen para la superposición y el diseño, como `get_image_data()` de PRO: texto alternativo,
	 * leyenda, descripción, título, archivo completo y medidas del tamaño elegido. `null` si no es un adjunto de imagen.
	 */
	private static function image_data( int $id, array $settings ): ?array {
		$size = (string) ( $settings['thumbnail_image_size'] ?? 'medium' );
		$crop = '';
		if ( 'custom' === $size ) {
			$custom = (array) ( $settings['thumbnail_image_custom_dimension'] ?? array() );
			$width  = (int) ( $custom['width'] ?? 0 );
			$height = (int) ( $custom['height'] ?? 0 );
			$size   = $width > 0 && $height > 0 ? array( $width, $height ) : 'full';
			// Como `get_image_data()` de PRO: el recorte exacto del núcleo (BFI_Thumb en `uploads/elementor/thumbs/`), no el
			// tamaño registrado más cercano que daría WordPress.
			if ( ( $width > 0 || $height > 0 ) && class_exists( '\\Elementor\\Group_Control_Image_Size' ) && method_exists( '\\Elementor\\Group_Control_Image_Size', 'get_attachment_image_src' ) ) {
				$crop = (string) Group_Control_Image_Size::get_attachment_image_src( $id, 'thumbnail_image', $settings );
			}
		}
		$src = function_exists( 'wp_get_attachment_image_src' ) ? wp_get_attachment_image_src( $id, $size ) : false;
		$post = function_exists( 'get_post' ) ? get_post( $id ) : null;
		if ( ! $src || ! $post || 'attachment' !== $post->post_type ) {
			return null;
		}
		$full = wp_get_attachment_image_src( $id, 'full' );
		$data = array(
			'size'        => $size,
			'crop'        => $crop,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'media'       => $full ? (string) $full[0] : (string) $src[0],
			'width'       => (int) $src[1],
			'height'      => (int) $src[2],
			'caption'     => (string) $post->post_excerpt,
			'description' => (string) $post->post_content,
			'title'       => (string) $post->post_title,
		);
		if ( is_array( $size ) ) {
			// Como PRO: el diseño usa las medidas personalizadas.
			$data['width']  = $size[0];
			$data['height'] = $size[1];
		}
		return $data;
	}

	/**
	 * La imagen de un tamaño personalizado: `wp_get_attachment_image()` (los mismos atributos y decisiones de carga) con el
	 * recorte del núcleo como archivo y sus medidas. El recorte no es un tamaño del adjunto, así que WordPress no le pone
	 * `srcset`, que serviría otra proporción.
	 */
	private static function crop_image( int $image_id, array $data, array $attrs ): string {
		$crop = array( $data['crop'], (int) $data['width'], (int) $data['height'], true );
		$hook = static function ( $image, $attachment_id ) use ( $image_id, $crop ) {
			return (int) $attachment_id === $image_id ? $crop : $image;
		};
		add_filter( 'wp_get_attachment_image_src', $hook, PHP_INT_MAX, 2 );
		$html = (string) wp_get_attachment_image( $image_id, 'full', false, $attrs );
		remove_filter( 'wp_get_attachment_image_src', $hook, PHP_INT_MAX );
		return $html;
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$galleries = self::galleries( $settings );
		$multiple  = 'multiple' === ( $settings['gallery_type'] ?? 'single' ) && ! empty( $settings['galleries'] );
		if ( ! $galleries ) {
			if ( self::edit_mode() ) {
				echo '<i class="elementor-widget-empty-icon eicon-gallery-justified" aria-hidden="true"></i>';
			}
			return;
		}
		$id     = $this->get_id();
		$layout = in_array( $settings['gallery_layout'] ?? 'grid', array( 'grid', 'justified', 'masonry' ), true ) ? (string) $settings['gallery_layout'] : 'grid';
		$active = '';
		$box_id = 'digi-gallery-' . $id;

		echo '<div class="digi-gallery digi-gallery--' . esc_attr( $layout ) . '">';
		if ( $multiple ) {
			$active = $this->print_filters( $settings, $galleries, $box_id );
		}
		$group = $multiple ? $active . '_' . $id : $id;
		$attrs = array(
			'id'    => $box_id,
			'class' => 'digi-gallery__container',
		);
		if ( 'grid' !== $layout ) {
			$attrs['data-digi-layout'] = $layout;
		}
		if ( 'yes' === ( $settings['lazyload'] ?? 'yes' ) ) {
			$attrs['data-digi-lazy'] = '';
		}
		echo '<div';
		foreach ( $attrs as $name => $value ) {
			echo ' ' . esc_attr( $name ) . ( '' === $value ? '' : '="' . esc_attr( $value ) . '"' );
		}
		echo '>';
		foreach ( self::items( $settings ) as $image_id => $tags ) {
			$this->print_item( (int) $image_id, $tags, $settings, $multiple ? $active : '', $group );
		}
		echo '</div></div>';
	}

	/**
	 * Barra de filtros: «Todas» (si se muestra) y una por galería no vacía. Devuelve el filtro activo al empezar
	 * (`all` o el índice de la primera galería), el que PRO elige al iniciar con su clic simulado.
	 */
	private function print_filters( array $settings, array $galleries, string $box_id ): string {
		$pointer = (string) ( $settings['pointer'] ?? 'underline' );
		$pointer = in_array( $pointer, array( 'none', 'underline', 'overline', 'double-line', 'framed', 'background', 'text' ), true ) ? $pointer : 'underline';
		$filters = array();
		if ( ! empty( $settings['show_all_galleries'] ) ) {
			$filters['all'] = wp_kses_post( (string) ( $settings['show_all_galleries_label'] ?? '' ) );
		}
		$rows = array_values( (array) ( $settings['galleries'] ?? array() ) );
		foreach ( array_keys( $galleries ) as $index ) {
			$filters[ (string) $index ] = wp_kses_post( (string) ( $rows[ $index ]['gallery_title'] ?? '' ) );
		}
		$active    = (string) array_key_first( $filters );
		$fx        = self::pointer_fx( $settings );
		echo '<div class="digi-gallery__filters digi-gallery__filters--' . esc_attr( $pointer ) . '"' . ( '' !== $fx ? ' data-digi-fx="' . esc_attr( $fx ) . '"' : '' ) . ' role="group" aria-label="' . esc_attr__( 'Filtro de la galería', 'pro-elements' ) . '">';
		foreach ( $filters as $index => $label ) {
			$on = (string) $index === $active;
			echo '<button type="button" class="digi-gallery__filter' . ( $on ? ' digi-gallery__filter--active' : '' ) . '" data-digi-filter="' . esc_attr( $index ) . '" aria-controls="' . esc_attr( $box_id ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">' . $label . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texto con wp_kses_post().
		}
		echo '</div>';
		return $active;
	}

	/**
	 * Un elemento de la galería: enlace (lightbox del núcleo o URL personalizada) o `div`, con la imagen, el fondo de la
	 * superposición y el contenido; oculto al empezar si no está en el filtro activo.
	 *
	 * @param int[]  $tags   Galerías en las que está la imagen.
	 * @param string $active Filtro activo ('' sin filtros).
	 * @param string $group  Grupo del lightbox de los visibles.
	 */
	private function print_item( int $image_id, array $tags, array $settings, string $active, string $group ) {
		$data = self::image_data( $image_id, $settings );
		if ( ! $data ) {
			return;
		}
		$key     = 'digi_gallery_item_' . $image_id;
		$link_to = (string) ( $settings['link_to'] ?? 'file' );
		$tag     = '' !== $link_to ? 'a' : 'div';
		$title   = (string) ( $settings['overlay_title'] ?? '' );
		$desc    = (string) ( $settings['overlay_description'] ?? '' );
		$this->add_render_attribute( $key, 'class', 'digi-gallery__item' );
		$this->add_render_attribute( $key, 'data-w', (string) $data['width'] );
		$this->add_render_attribute( $key, 'data-h', (string) $data['height'] );
		if ( '' !== $active ) {
			$this->add_render_attribute( $key, 'data-digi-tags', implode( ',', $tags ) );
		}
		$hidden = '' !== $active && 'all' !== $active && ! in_array( (int) $active, $tags, true );
		if ( $hidden ) {
			$this->add_render_attribute( $key, 'hidden', 'hidden' );
		}
		if ( '' !== $title && 'div' === $tag ) {
			$this->add_render_attribute( $key, 'tabindex', '0' );
		}
		if ( 'file' === $link_to ) {
			$this->add_render_attribute( $key, 'href', esc_url( $data['media'] ) );
			if ( self::edit_mode() ) {
				$this->add_render_attribute( $key, 'class', 'elementor-clickable' );
			}
			$this->add_lightbox_data_attributes( $key, $image_id, (string) ( $settings['open_lightbox'] ?? 'default' ), $hidden ? $this->get_id() : $group );
		} elseif ( 'custom' === $link_to ) {
			$this->add_link_attributes( $key, (array) ( $settings['url'] ?? array() ) );
			if ( ! empty( $settings['url']['is_external'] ) ) {
				$this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) );
			}
		}
		$image_fx = (string) ( $settings['image_hover_animation'] ?? '' );
		$attrs    = array(
			'class'    => 'digi-gallery__image' . ( isset( self::FX[ $image_fx ] ) ? ' digi-gallery__fx' : '' ),
			'alt'      => $data['alt'],
			'decoding' => 'async',
			'loading'  => 'yes' === ( $settings['lazyload'] ?? 'yes' ) ? 'lazy' : false,
		);
		if ( isset( self::FX[ $image_fx ] ) ) {
			$attrs['style'] = self::FX[ $image_fx ];
		}
		$img = '' !== $data['crop'] ? self::crop_image( $image_id, $data, $attrs ) : wp_get_attachment_image( $image_id, $data['size'], false, $attrs );
		$html = (string) $img;
		if ( ! empty( $settings['overlay_background'] ) ) {
			$html .= '<div class="digi-gallery__overlay' . self::fx( $settings['background_overlay_hover_animation'] ?? '' ) . '"></div>';
		}
		if ( '' !== $title || '' !== $desc ) {
			$content_fx = self::fx( $settings['content_hover_animation'] ?? '' );
			$inner      = '';
			foreach ( array( 'title' => $title, 'description' => $desc ) as $part => $source ) {
				$text = '' !== $source && isset( $data[ $source ] ) ? trim( (string) $data[ $source ] ) : '';
				if ( '' !== $text ) {
					$inner .= '<div class="digi-gallery__' . $part . $content_fx . '">' . wp_kses_post( $text ) . '</div>';
				}
			}
			$sequenced = '' !== $content_fx && 'yes' === ( $settings['content_sequenced_animation'] ?? '' ) ? ' digi-gallery__content--sequenced' : '';
			$html     .= '<div class="digi-gallery__content' . $sequenced . '">' . $inner . '</div>';
		}
		echo '<' . $tag . ' ' . $this->get_render_attribute_string( $key ) . '>' . $html . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta fija, atributos y marcado propios escapados.
	}
}
