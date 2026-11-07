<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Cards;
use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Lista de videos: una lista de vídeos en pestañas y el reproductor del elegido, sin jQuery ni los paquetes de PRO.
 * Sustituye al Video Playlist de PRO (`modules/video-playlist/widgets/video-playlist.php`, mapa
 * `modules/digitalisimo-convert/maps/video-playlist.php`): repite sus controles con el mismo nombre, valores por
 * defecto, opciones y condiciones; el marcado, las clases y el script son propios.
 *
 * - **Pestañas accesibles.** La lista es un `tablist` vertical con un `tab` por vídeo (flechas, Inicio y Fin mueven el
 *   foco; Intro y Espacio eligen, como la activación manual del patrón, porque elegir reproduce) y un `tabpanel` por
 *   vídeo con el reproductor. Las secciones son separadores visuales dentro de la lista. Las pestañas internas de cada
 *   vídeo («Contenido» y la segunda) son otro `tablist` con botones; «Mostrar más» es un botón con `aria-expanded`.
 * - **Reproductor del primer vídeo como PRO.** Sin las fachadas de vídeo del sitio (`Front\Facades::active()`, apagadas
 *   por defecto), el reproductor del servicio del primer vídeo (YouTube con el dominio sin cookies) se crea al cargar la
 *   página o, con «Carga diferida», al acercarse (`data-embed`, `data-lazy`), y una miniatura guardada en otro dominio
 *   (la que rellena «Obtener datos del vídeo») se pinta tal cual. Con las fachadas aplicadas, **ninguna petición a
 *   terceros antes del clic**: un vídeo de YouTube o Vimeo es la portada servida por el sitio (`Front\Facade_Images`) o
 *   el fondo negro con un botón «Reproducir el vídeo: …», el iframe sólo se crea al pulsarlo o al elegir el vídeo, y la
 *   miniatura de otro dominio se sustituye por la portada del sitio o no se pinta. Un vídeo alojado es un
 *   `<video controls preload="none">`: no se descarga nada hasta reproducirlo.
 * - **Como PRO:** imagen superpuesta en el primer vídeo, siguiente vídeo al terminar, vídeos vistos (en el
 *   almacenamiento local del navegador), parámetros `?playlist=<id>&video=<id de la fila>` al elegir y al cargar,
 *   degradados arriba y abajo de la lista cuando se puede desplazar (`digi-vpl__shadow`), botón de la cabecera que pliega
 *   la lista en el móvil, contenido plegable y, en el móvil, pestañas internas apiladas con un título por panel
 *   (`digi-vpl__in-mob`, hoja `digitalisimo-video-playlist-inner`, sólo con pestañas internas). El título de cada vídeo
 *   va en un `<button>` dentro de su encabezado, como en PRO, así que toma del navegador y del tema la letra de los
 *   botones. Reproducción automática al cargar: silenciada; los vídeos de un servicio, sólo sin las fachadas del sitio.
 * - **«Obtener datos del vídeo»** (`vimeo_fetch_data`, el botón del editor de PRO): `assets/js/video-playlist-editor.js`
 *   pide el título, la miniatura y la duración al proxy oEmbed de WordPress (la duración de YouTube, que oEmbed no da, a
 *   su reproductor) sólo cuando el administrador lo pulsa.
 * - **Imágenes** con `wp_get_attachment_image()` (miniaturas e imagen superpuesta, con `srcset`, `loading`, `decoding`).
 * - Iconos de reproducir y visto: los de PRO por defecto en SVG en línea; uno elegido por el usuario, con
 *   `Icons_Manager` del núcleo.
 *
 * Deriva de PRO Elements (GPL): `modules/video-playlist/widgets/video-playlist.php` (controles, textos y marcado),
 * `modules/video-playlist/assets/js/frontend/*.js` (comportamiento) y `assets/css/widget-video-playlist.min.css`
 * (aspecto por defecto).
 */
class Video_Playlist extends \Elementor\Widget_Base {
	const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span' );

	/** Iconos por defecto de PRO (Font Awesome Free, CC BY 4.0) en SVG en línea: `valor => [ancho, trazo]`. */
	const ICONS = array(
		'fas fa-play-circle'  => array( 512, 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm115.7 272l-176 101c-15.8 8.8-35.7-2.5-35.7-21V152c0-18.4 19.8-29.8 35.7-21l176 107c16.4 9.2 16.4 32.9 0 42z' ),
		'fas fa-check-circle' => array( 512, 'M504 256c0 136.967-111.033 248-248 248S8 392.967 8 256 119.033 8 256 8s248 111.033 248 248zM227.314 387.314l184-184c6.248-6.248 6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.249-16.379-6.249-22.628 0L216 308.118l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 0l-22.627 22.627c-6.248 6.248-6.248 16.379 0 22.627l104 104c6.249 6.249 16.379 6.249 22.628.001z' ),
		'far fa-play-circle'  => array( 512, 'M371.7 238l-176-107c-15.8-8.8-35.7 2.5-35.7 21v208c0 18.4 19.8 29.8 35.7 21l176-101c16.4-9.1 16.4-32.8 0-42zM504 256C504 119 393 8 256 8S8 119 8 256s111 248 248 248 248-111 248-248zm-448 0c0-110.5 89.5-200 200-200s200 89.5 200 200-89.5 200-200 200S56 366.5 56 256z' ),
	);

	/** Flecha del botón que pliega la lista (el dibujo de `caret-down` del núcleo). */
	const CARET = '<svg viewBox="0 0 571.4 571.4" aria-hidden="true" focusable="false"><path d="M571 393Q571 407 561 418L311 668Q300 679 286 679T261 668L11 418Q0 407 0 393T11 368 36 357H536Q550 357 561 368T571 393Z"/></svg>';

	public function get_name() { return 'digitalisimo-video-playlist'; }
	public function get_title() { return esc_html__( 'Lista de videos', 'pro-elements' ); }
	public function get_icon() { return 'eicon-video-playlist'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'lista', 'videos', 'vídeo', 'playlist', 'pestañas', 'youtube', 'vimeo', 'reproductor' ); }
	/**
	 * Su hoja y, sólo si algún vídeo muestra pestañas internas, la de las pestañas (`Module::STYLES`); en el editor, las dos.
	 */
	public function get_style_depends(): array {
		$all = array_merge( Module::style_depends( $this->get_name() ), array( 'digitalisimo-video-playlist-inner' ) );
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! method_exists( $this, 'get_settings' ) ) {
			return $all;
		}
		return self::styles_for( (array) $this->get_settings() );
	}

	/** Hojas que necesitan unos ajustes. */
	public static function styles_for( array $settings ): array {
		$out = Module::style_depends( 'digitalisimo-video-playlist' );
		foreach ( (array) ( $settings['tabs'] ?? array() ) as $item ) {
			if ( is_array( $item ) && 'section' !== ( $item['type'] ?? 'youtube' ) && 'show' === ( $item['inner_tab_is_content_visible'] ?? '' ) ) {
				$out[] = 'digitalisimo-video-playlist-inner';
				break;
			}
		}
		return $out;
	}
	public function get_script_depends(): array { return Module::script_depends( $this->get_name() ); }
	protected function is_dynamic_content(): bool { return false; }

	protected function register_controls() {
		$start = function_exists( 'is_rtl' ) && is_rtl() ? 'right' : 'left';
		$end   = function_exists( 'is_rtl' ) && is_rtl() ? 'left' : 'right';
		$tags  = array_combine( self::TAGS, array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'div', 'span' ) );
		$video = array( 'youtube', 'hosted', 'vimeo' );
		$text  = array( 'default' => 'globals/colors?id=text' );
		$font  = array( 'default' => 'globals/typography?id=text' );
		$lines = array(
			''       => esc_html__( 'Ninguno', 'pro-elements' ),
			'solid'  => esc_html__( 'Sólido', 'pro-elements' ),
			'double' => esc_html__( 'Doble', 'pro-elements' ),
			'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
			'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
			'groove' => esc_html__( 'Ranura', 'pro-elements' ),
		);
		$icon_range = array(
			'px'  => array( 'min' => 10, 'max' => 30 ),
			'em'  => array( 'min' => 1, 'max' => 3 ),
			'rem' => array( 'min' => 1, 'max' => 3 ),
		);
		$weight_range = array(
			'px'  => array( 'max' => 10 ),
			'em'  => array( 'max' => 1 ),
			'rem' => array( 'max' => 1 ),
		);
		$tab   = '{{WRAPPER}} .digi-vpl__tab';
		$on    = '{{WRAPPER}} .digi-vpl__tab:where(.digi-vpl__tab--on, :hover)';
		$inner = '{{WRAPPER}} .digi-vpl__in';

		// Lista.
		$this->start_controls_section( 'section_playlist', array( 'label' => esc_html__( 'Lista', 'pro-elements' ) ) );
		$this->add_control(
			'tabs_direction',
			array(
				'label'        => esc_html__( 'Posición', 'pro-elements' ),
				'type'         => Controls_Manager::HIDDEN,
				'default'      => 'vertical',
				'options'      => array(
					'horizontal' => esc_html__( 'Horizontal', 'pro-elements' ),
					'vertical'   => esc_html__( 'Vertical', 'pro-elements' ),
				),
				'prefix_class' => 'digi-vpl-view-',
			)
		);
		$this->add_control(
			'playlist_title',
			array(
				'label'              => esc_html__( 'Nombre de la lista', 'pro-elements' ),
				'type'               => Controls_Manager::TEXT,
				'default'            => esc_html__( 'Lista de videos', 'pro-elements' ),
				'placeholder'        => esc_html__( 'Lista de videos', 'pro-elements' ),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'playlist_title_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $tags,
				'default'   => 'h2',
				'condition' => array( 'playlist_title!' => '' ),
			)
		);

		$repeater = new Repeater();
		$repeater->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'youtube',
				'options' => array(
					'youtube' => 'YouTube',
					'vimeo'   => 'Vimeo',
					'hosted'  => esc_html__( 'Alojado en el sitio', 'pro-elements' ),
					'section' => esc_html__( 'Sección', 'pro-elements' ),
				),
			)
		);
		$repeater->add_control(
			'youtube_url',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'placeholder' => esc_html__( 'Pega la URL', 'pro-elements' ) . ' (YouTube)',
				'label_block' => true,
				'condition'   => array( 'type' => 'youtube' ),
			)
		);
		$repeater->add_control(
			'vimeo_url',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array(
					'active'     => true,
					'categories' => array( 'post_meta', 'url' ),
				),
				'ai'          => array( 'active' => false ),
				'placeholder' => esc_html__( 'Escribe la URL', 'pro-elements' ) . ' (Vimeo)',
				'default'     => '',
				'label_block' => true,
				'condition'   => array( 'type' => 'vimeo' ),
			)
		);
		$repeater->add_control(
			'vimeo_fetch_data',
			array(
				'type'        => Controls_Manager::BUTTON,
				'label_block' => true,
				'text'        => esc_html__( 'Obtener datos del vídeo', 'pro-elements' ),
				'event'       => 'digitalisimoPlaylist:fetchVideoData',
				'condition'   => array( 'type' => array( 'youtube', 'vimeo' ) ),
			)
		);
		$repeater->add_control(
			'is_external_url',
			array(
				'label'     => esc_html__( 'URL externa', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array( 'type' => 'hosted' ),
			)
		);
		$repeater->add_control(
			'hosted_url',
			array(
				'label'       => esc_html__( 'Elegir archivo', 'pro-elements' ),
				'type'        => Controls_Manager::MEDIA,
				'dynamic'     => array(
					'active'     => true,
					'categories' => array( 'media' ),
				),
				'media_types' => array( 'video' ),
				'condition'   => array(
					'type'            => 'hosted',
					'is_external_url' => '',
				),
			)
		);
		$repeater->add_control(
			'external_url',
			array(
				'label'        => esc_html__( 'URL', 'pro-elements' ),
				'type'         => Controls_Manager::URL,
				'autocomplete' => false,
				'options'      => false,
				'label_block'  => true,
				'show_label'   => false,
				'dynamic'      => array(
					'active'     => true,
					'categories' => array( 'post_meta', 'url' ),
				),
				'media_types'  => array( 'video' ),
				'placeholder'  => esc_html__( 'Escribe la URL', 'pro-elements' ),
				'condition'    => array(
					'type'            => 'hosted',
					'is_external_url' => 'yes',
				),
			)
		);
		$repeater->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Título', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => esc_html__( 'Título', 'pro-elements' ),
				'placeholder' => esc_html__( 'Escribe el texto', 'pro-elements' ),
				'label_block' => true,
			)
		);
		$repeater->add_control(
			'section_html_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $tags,
				'default'   => 'h3',
				'condition' => array( 'type' => 'section' ),
			)
		);
		$repeater->add_control(
			'video_html_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $tags,
				'default'   => 'h4',
				'condition' => array( 'type!' => 'section' ),
			)
		);
		$repeater->add_control(
			'duration',
			array(
				'label'       => esc_html__( 'Duración', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => '1:05',
				'default'     => '',
				'ai'          => array( 'active' => false ),
				'condition'   => array( 'type!' => 'section' ),
			)
		);
		$repeater->add_control(
			'thumbnail',
			array(
				'label'     => esc_html__( 'Miniatura', 'pro-elements' ),
				'type'      => Controls_Manager::MEDIA,
				'dynamic'   => array( 'active' => true ),
				'default'   => array( 'url' => self::placeholder() ),
				'condition' => array( 'type!' => 'section' ),
			)
		);
		$repeater->add_control(
			'inner_tab_is_content_visible',
			array(
				'label'        => esc_html__( 'Pestañas de contenido', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'    => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'show',
				'default'      => '',
				'condition'    => array( 'type' => $video ),
			)
		);
		$inner_condition = array(
			'type'                         => $video,
			'inner_tab_is_content_visible' => 'show',
		);
		$repeater->start_controls_tabs( 'video_tabs' );
		$repeater->start_controls_tab( 'inner_tab_1', array( 'label' => esc_html__( 'Pestaña 1', 'pro-elements' ), 'condition' => $inner_condition ) );
		$repeater->add_control(
			'inner_tab_content_1',
			array(
				'label'     => '',
				'type'      => Controls_Manager::WYSIWYG,
				'default'   => '<p>' . esc_html__( 'Añade contenido para cada vídeo, como una descripción, la transcripción o enlaces. Los nombres de las pestañas se cambian en «Pestañas».', 'pro-elements' ) . '</p>',
				'condition' => $inner_condition,
			)
		);
		$repeater->end_controls_tab();
		$repeater->start_controls_tab( 'inner_tab_2', array( 'label' => esc_html__( 'Pestaña 2', 'pro-elements' ), 'condition' => $inner_condition ) );
		$repeater->add_control(
			'inner_tab_content_2',
			array(
				'label'     => '',
				'type'      => Controls_Manager::WYSIWYG,
				'condition' => $inner_condition,
			)
		);
		$repeater->end_controls_tab();
		$repeater->end_controls_tabs();

		$this->add_control(
			'tabs',
			array(
				'label'              => esc_html__( 'Elementos de la lista', 'pro-elements' ),
				'type'               => Controls_Manager::REPEATER,
				'fields'             => $repeater->get_controls(),
				'default'            => array_fill(
					0,
					3,
					array(
						'title'       => esc_html__( 'Video de muestra', 'pro-elements' ),
						'youtube_url' => '',
						'duration'    => '',
					)
				),
				'frontend_available' => true,
				'title_field'        => '{{{ title }}}',
				'separator'          => 'before',
			)
		);
		$this->end_controls_section();

		// Pestañas internas.
		$this->start_controls_section( 'section_inner_tab', array( 'label' => esc_html__( 'Pestañas', 'pro-elements' ) ) );
		foreach ( array( 1, 2 ) as $n ) {
			$this->add_control(
				'inner_tab_title_' . $n,
				array(
					/* translators: %d: número de la pestaña. */
					'label'              => sprintf( esc_html__( 'Nombre de la pestaña %d', 'pro-elements' ), $n ),
					'type'               => Controls_Manager::TEXT,
					/* translators: %d: número de la pestaña. */
					'default'            => sprintf( esc_html__( 'Pestaña %d', 'pro-elements' ), $n ),
					'dynamic'            => array( 'active' => true ),
					'ai'                 => array( 'active' => false ),
					'placeholder'        => esc_html__( 'Nombre', 'pro-elements' ),
					'frontend_available' => true,
				)
			);
		}
		$this->add_control(
			'inner_tab_is_content_collapsible',
			array(
				'label'              => esc_html__( 'Plegable', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'          => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value'       => 'collapsible',
				'default'            => '',
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'inner_tab_label_show_more',
			array(
				'label'       => esc_html__( 'Texto de «Leer más»', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'placeholder' => esc_html__( 'Mostrar más', 'pro-elements' ),
				'default'     => esc_html__( 'Mostrar más', 'pro-elements' ),
				'condition'   => array( 'inner_tab_is_content_collapsible' => 'collapsible' ),
			)
		);
		$this->add_control(
			'inner_tab_label_show_less',
			array(
				'label'       => esc_html__( 'Texto de «Leer menos»', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'ai'          => array( 'active' => false ),
				'placeholder' => esc_html__( 'Mostrar menos', 'pro-elements' ),
				'default'     => esc_html__( 'Mostrar menos', 'pro-elements' ),
				'condition'   => array( 'inner_tab_is_content_collapsible' => 'collapsible' ),
			)
		);
		$this->add_responsive_control(
			'inner_tab_collapsible_height',
			array(
				'label'              => esc_html__( 'Altura', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'default'            => array( 'size' => 54 ),
				'range'              => array(
					'px'  => array( 'min' => 50, 'max' => 500 ),
					'em'  => array( 'min' => 5, 'max' => 50 ),
					'rem' => array( 'min' => 5, 'max' => 50 ),
				),
				'render_type'        => 'template',
				// Sin `--open`: «Mostrar más» quita el límite aunque la altura venga del panel.
				'selectors'          => array( $inner . '-panel--fold:not(.digi-vpl__in-panel--open) .digi-vpl__in-text' => 'height: {{SIZE}}{{UNIT}};' ),
				'condition'          => array( 'inner_tab_is_content_collapsible' => 'collapsible' ),
				'frontend_available' => true,
			)
		);
		$this->end_controls_section();

		// Imagen superpuesta.
		$this->start_controls_section( 'section_image_overlay', array( 'label' => esc_html__( 'Imagen superpuesta', 'pro-elements' ) ) );
		$this->add_control(
			'show_image_overlay',
			array(
				'label'     => esc_html__( 'Imagen superpuesta', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
			)
		);
		$this->add_control(
			'image_overlay',
			array(
				'label'     => esc_html__( 'Elegir imagen', 'pro-elements' ),
				'type'      => Controls_Manager::MEDIA,
				'default'   => array( 'url' => self::placeholder() ),
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'show_image_overlay' => 'yes' ),
			)
		);
		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name'      => 'image_overlay',
				'default'   => 'full',
				'condition' => array( 'show_image_overlay' => 'yes' ),
			)
		);
		$this->add_control(
			'show_play_icon',
			array(
				'label'            => esc_html__( 'Icono de reproducir', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default'          => array(
					'value'   => 'far fa-play-circle',
					'library' => 'fa-regular',
				),
				'label_block'      => false,
				'skin'             => 'inline',
				'condition'        => array( 'show_image_overlay' => 'yes' ),
			)
		);
		$this->end_controls_section();

		// Opciones adicionales.
		$this->start_controls_section( 'section_additional_options', array( 'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ) ) );
		$this->add_control( 'heading_autoplay', array( 'label' => esc_html__( 'Reproducción automática', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control(
			'autoplay_on_load',
			array(
				'label'              => esc_html__( 'Al cargar', 'pro-elements' ),
				'description'        => esc_html__( 'Silenciado. Con las fachadas de vídeo del sitio, los de YouTube y Vimeo esperan a que el visitante pulse.', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'          => esc_html__( 'Ocultar', 'pro-elements' ),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'autoplay_next',
			array(
				'label'              => esc_html__( 'Siguiente vídeo', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'          => esc_html__( 'Ocultar', 'pro-elements' ),
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'show_watched_indication',
			array(
				'label'              => esc_html__( 'Marcar los vistos', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);
		$this->add_control(
			'show_video_count',
			array(
				'label'   => esc_html__( 'Número de vídeos', 'pro-elements' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);
		$this->add_control(
			'show_duration',
			array(
				'label'     => esc_html__( 'Duración', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
			)
		);
		$this->add_control(
			'show_thumbnail',
			array(
				'label'     => esc_html__( 'Miniaturas', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
			)
		);
		$this->add_control(
			'play_icon',
			array(
				'label'            => esc_html__( 'Icono de reproducir', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default'          => array(
					'value'   => 'fas fa-play-circle',
					'library' => 'fa-solid',
				),
				'label_block'      => false,
				'skin'             => 'inline',
			)
		);
		$this->add_control(
			'watched_icon',
			array(
				'label'            => esc_html__( 'Icono de visto', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'default'          => array(
					'value'   => 'fas fa-check-circle',
					'library' => 'fa-solid',
				),
				'label_block'      => false,
				'skin'             => 'inline',
			)
		);
		$this->add_control(
			'lazy_load',
			array(
				'label'              => esc_html__( 'Carga diferida', 'pro-elements' ),
				'description'        => esc_html__( 'El reproductor del primer vídeo se crea al acercarse a la pantalla, no al cargar la página.', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'separator'          => 'before',
				'frontend_available' => true,
			)
		);
		$this->end_controls_section();

		// Estilo: disposición.
		$this->start_controls_section( 'section_style_layout', array( 'label' => esc_html__( 'Disposición', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'tabs_alignment',
			array(
				'label'        => esc_html__( 'Posición del vídeo', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'default'      => 'end',
				'options'      => array(
					'start' => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => "eicon-h-align-$start",
					),
					'end'   => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => "eicon-h-align-$end",
					),
				),
				'prefix_class' => 'digi-vpl-layout-',
			)
		);
		$this->add_responsive_control(
			'layout_height',
			array(
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array( 'min' => 200, 'max' => 1200 ),
					'em'  => array( 'min' => 20, 'max' => 120 ),
					'rem' => array( 'min' => 20, 'max' => 120 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-vpl__main' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();

		// Estilo: barra superior.
		$this->start_controls_section( 'section_style_top_bar', array( 'label' => esc_html__( 'Barra superior', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'heading_playlist_name', array( 'label' => esc_html__( 'Nombre de la lista', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'playlist_name_background', array( 'label' => esc_html__( 'Fondo', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-vpl__header' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'playlist_name_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'default' => '', 'selectors' => array( '{{WRAPPER}} .digi-vpl__name' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'playlist_name_typography', 'selector' => '{{WRAPPER}} .digi-vpl__name' ) );
		$this->add_control( 'heading_videos_amount', array( 'label' => esc_html__( 'Número de vídeos', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control(
			'videos_amount_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-vpl__count'      => 'color: {{VALUE}};',
					'{{WRAPPER}} .digi-vpl__fold'     => 'color: {{VALUE}};',
					'{{WRAPPER}} .digi-vpl__fold svg' => 'fill: {{VALUE}};',
				),
				'global'    => $text,
			)
		);
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'videos_amount_typography', 'selector' => '{{WRAPPER}} .digi-vpl__count' ) );
		$this->end_controls_section();

		// Estilo: vídeos.
		$this->start_controls_section( 'section_style_videos', array( 'label' => esc_html__( 'Vídeos', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->start_controls_tabs( 'playlist_tabs' );
		$this->start_controls_tab( 'playlist_tabs_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control( 'heading_tab_normal', array( 'label' => esc_html__( 'Elemento', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control(
			'normal_background',
			array(
				'label'     => esc_html__( 'Fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$tab . ':not(:where(.digi-vpl__tab--on))'           => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .digi-vpl__list'                           => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .digi-vpl__list .digi-vpl__shadow--bottom' => 'background: linear-gradient(180deg, transparent 0%, {{VALUE}} 100%)',
					'{{WRAPPER}} .digi-vpl__list .digi-vpl__shadow--top'    => 'background: linear-gradient(0deg, transparent 0%, {{VALUE}} 100%);',
				),
			)
		);
		$this->add_control( 'normal_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $tab . ' .digi-vpl__title' => 'color: {{VALUE}};', $tab . ' .digi-vpl__title button' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'normal_typography', 'selector' => $tab . ' .digi-vpl__title button', 'global' => $font ) );
		$this->add_control( 'heading_duration_normal', array( 'label' => esc_html__( 'Duración', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'normal_duration_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $tab . ' .digi-vpl__time' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'normal_duration_typography', 'selector' => $tab . ' .digi-vpl__time' ) );
		$this->add_control( 'heading_icon_normal', array( 'label' => esc_html__( 'Icono', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control(
			'normal_icon_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$tab . ' .digi-vpl__icon i'        => 'color: {{VALUE}};',
					$tab . ' .digi-vpl__icon svg'      => 'fill: {{VALUE}};',
					$tab . ' .digi-vpl__icon svg path' => 'fill: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'           => 'normal_icon_top_text_shadow',
				'fields_options' => array(
					'text_shadow_type' => array( 'label' => esc_html__( 'Sombra', 'pro-elements' ) ),
					'text_shadow'      => array(
						'selectors' => array(
							$tab . ' .digi-vpl__icon i'   => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							$tab . ' .digi-vpl__icon svg' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}});',
						),
					),
				),
			)
		);
		$this->add_responsive_control(
			'normal_icon_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => $icon_range,
				'selectors'  => array( '{{WRAPPER}}' => '--digi-vpl-icon: {{SIZE}}{{UNIT}}' ),
			)
		);
		$this->add_control( 'heading_separator_normal', array( 'label' => esc_html__( 'Separador', 'pro-elements' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'normal_separator_style', array( 'label' => esc_html__( 'Estilo', 'pro-elements' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => $lines, 'selectors' => array( $tab => 'border-style: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'normal_separator_weight', array( 'label' => esc_html__( 'Grosor', 'pro-elements' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ), 'range' => $weight_range, 'selectors' => array( $tab => 'border-width: 0 0 {{SIZE}}{{UNIT}} 0;' ), 'condition' => array( 'normal_separator_style!' => '' ) ) );
		$this->add_control( 'normal_separator_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $tab => 'border-color: {{VALUE}};' ), 'condition' => array( 'normal_separator_style!' => '' ) ) );
		$this->end_controls_tab();

		$this->start_controls_tab( 'playlist_tabs_active', array( 'label' => esc_html__( 'Activo', 'pro-elements' ) ) );
		$this->add_control( 'heading_tab_active', array( 'label' => esc_html__( 'Elemento', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'active_background', array( 'label' => esc_html__( 'Fondo', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $on => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'active_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'default' => '#556068', 'selectors' => array( $on . ' .digi-vpl__title' => 'color: {{VALUE}};', $on . ' .digi-vpl__title button' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'active_typography', 'selector' => $on . ' .digi-vpl__title button', 'global' => $font ) );
		$this->add_control( 'heading_duration_active', array( 'label' => esc_html__( 'Duración', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'active_duration_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'default' => '', 'selectors' => array( $on . ' .digi-vpl__time' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'active_duration_typography', 'selector' => $on . ' .digi-vpl__time' ) );
		$this->add_control( 'heading_icon_active', array( 'label' => esc_html__( 'Icono', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control(
			'active_icon_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$on . ' .digi-vpl__icon i'        => 'color: {{VALUE}};',
					$on . ' .digi-vpl__icon svg'      => 'color: {{VALUE}};',
					$on . ' .digi-vpl__icon svg path' => 'fill: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'           => 'active_icon_top_text_shadow',
				'fields_options' => array( 'text_shadow_type' => array( 'label' => esc_html__( 'Sombra', 'pro-elements' ) ) ),
				'selector'       => $on . ' .digi-vpl__icon i, ' . $on . ' .digi-vpl__icon svg',
			)
		);
		$this->add_responsive_control(
			'active_icon_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => $icon_range,
				'selectors'  => array(
					$on . ' .digi-vpl__icon i'   => 'font-size: {{SIZE}}{{UNIT}}',
					$on . ' .digi-vpl__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_control( 'heading_separator_active', array( 'label' => esc_html__( 'Separador', 'pro-elements' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'active_separator_style', array( 'label' => esc_html__( 'Estilo', 'pro-elements' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => $lines, 'selectors' => array( $tab . '.digi-vpl__tab--on' => 'border-style: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'active_separator_weight', array( 'label' => esc_html__( 'Grosor', 'pro-elements' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ), 'range' => $weight_range, 'selectors' => array( $tab . '.digi-vpl__tab--on' => 'border-width: 0 0 {{SIZE}}{{UNIT}} 0;' ), 'condition' => array( 'active_separator_style!' => '' ) ) );
		$this->add_control( 'active_separator_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $tab . '.digi-vpl__tab--on' => 'border-color: {{VALUE}};' ), 'condition' => array( 'active_separator_style!' => '' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		// Estilo: secciones.
		$section = '{{WRAPPER}} .digi-vpl__sec';
		$this->start_controls_section( 'section_style_sections', array( 'label' => esc_html__( 'Secciones', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'heading_section', array( 'label' => esc_html__( 'Sección', 'pro-elements' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'section_background', array( 'label' => esc_html__( 'Fondo', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $section => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'section_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'default' => '', 'selectors' => array( $section => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'section_typography', 'selector' => $section ) );
		$this->add_control( 'section_border_type', array( 'label' => esc_html__( 'Tipo de borde', 'pro-elements' ), 'type' => Controls_Manager::SELECT, 'default' => 'solid', 'options' => $lines, 'selectors' => array( $section => 'border-style: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'section_border_width', array( 'label' => esc_html__( 'Ancho', 'pro-elements' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'selectors' => array( $section => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'section_border_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $section => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		// Estilo: pestañas internas.
		$this->start_controls_section( 'section_inner_tab_style', array( 'label' => esc_html__( 'Pestañas', 'pro-elements' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'inner_tab_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'selectors'  => array(
					$inner . '-list'                                 => '--digi-vpl-in-border: {{SIZE}}{{UNIT}};',
					$inner . '-list .digi-vpl__in-tab--on' => 'border-width: 0 0 {{SIZE}}{{UNIT}} 0;',
				),
			)
		);
		$this->add_control( 'inner_tab_border_color', array( 'label' => esc_html__( 'Color del borde', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $inner . '-list' => '--digi-vpl-in-color: {{VALUE}};' ) ) );
		$this->add_control(
			'inner_tab_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$inner . '-list .digi-vpl__in-tab--on' => 'background-color: {{VALUE}};',
					$inner . '-panel'                             => 'background-color: {{VALUE}};',
					$inner . '-panels .digi-vpl__in-mob'         => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control( 'heading_inner_tab_title', array( 'label' => esc_html__( 'Título', 'pro-elements' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'inner_tab_title_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $inner . '-list .digi-vpl__in-tab' => 'color: {{VALUE}};' ) ) );
		$this->add_control(
			'inner_tab_active_title_color',
			array(
				'label'     => esc_html__( 'Color activo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					$inner . '-list .digi-vpl__in-tab--on'                     => 'color: {{VALUE}};',
					$inner . '-list .digi-vpl__in-tab.digi-vpl__in-tab--on' => 'border-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'inner_tab_title_typography', 'selector' => $inner . '-list .digi-vpl__in-tab', 'global' => $font ) );
		$this->add_control( 'heading_inner_tab_content', array( 'label' => esc_html__( 'Contenido', 'pro-elements' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'inner_tab_content_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $inner . '-panel .digi-vpl__in-text' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'inner_tab_content_typography', 'selector' => $inner . '-panel .digi-vpl__in-text', 'global' => $font ) );
		$this->add_responsive_control( 'inner_tab_content_padding', array( 'label' => esc_html__( 'Relleno', 'pro-elements' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ), 'selectors' => array( $inner . '-panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'heading_inner_tab_show_more', array( 'label' => esc_html__( 'Mostrar más', 'pro-elements' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->start_controls_tabs( 'inner_tab_show_more_color' );
		$this->start_controls_tab( 'inner_tab_normal_show_more', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control( 'inner_tab_normal_show_more_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $inner . '-panel .digi-vpl__more' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->end_controls_tab();
		$this->start_controls_tab( 'inner_tab_hover_show_more', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_control( 'inner_tab_hover_show_more_color', array( 'label' => esc_html__( 'Color', 'pro-elements' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $inner . '-panel .digi-vpl__more:hover' => 'color: {{VALUE}};' ), 'global' => $text ) );
		$this->add_control(
			'inner_tab_hover_show_more_color_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 'ms' ),
				'selectors'  => array( $inner . '-panel .digi-vpl__more' => 'transition-duration: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'inner_tab_show_more_typography', 'selector' => $inner . '-panel .digi-vpl__more', 'global' => array( 'default' => 'globals/typography?id=accent' ) ) );
		$this->end_controls_section();
	}

	/** Imagen de muestra del núcleo (la de PRO). */
	private static function placeholder(): string {
		return class_exists( '\\Elementor\\Utils' ) && method_exists( '\\Elementor\\Utils', 'get_placeholder_image_src' ) ? (string) \Elementor\Utils::get_placeholder_image_src() : '';
	}

	/** Etiqueta HTML permitida (las de PRO), o `$fallback`. */
	private static function tag( $tag, string $fallback ): string {
		$tag = strtolower( (string) $tag );
		return in_array( $tag, self::TAGS, true ) ? $tag : $fallback;
	}

	/** Dirección del vídeo de una fila, como PRO (`get_playlist_item_url()`): '' en una sección o sin dirección. */
	public static function item_url( array $item ): string {
		$type = (string) ( $item['type'] ?? 'youtube' );
		if ( 'youtube' === $type ) {
			return trim( (string) ( $item['youtube_url'] ?? '' ) );
		}
		if ( 'vimeo' === $type ) {
			return trim( (string) ( $item['vimeo_url'] ?? '' ) );
		}
		if ( 'hosted' === $type ) {
			$url = 'yes' === ( $item['is_external_url'] ?? '' ) ? ( $item['external_url']['url'] ?? '' ) : ( $item['hosted_url']['url'] ?? '' );
			return trim( (string) $url );
		}
		return '';
	}

	/**
	 * Servicio, ID y reproductor de un vídeo de YouTube o Vimeo (`Video_Player_Widget::embed_parts()` si existe),
	 * del tipo de la fila; [] si no lo es. YouTube con el dominio sin cookies y Vimeo sin seguimiento.
	 */
	public static function embed( string $type, string $url ): array {
		if ( '' === $url || ! in_array( $type, array( 'youtube', 'vimeo' ), true ) ) {
			return array();
		}
		if ( class_exists( '\\Digitalisimo\\Elements\\Video_Player_Widget' ) && method_exists( '\\Digitalisimo\\Elements\\Video_Player_Widget', 'embed_parts' ) ) {
			$parts = (array) \Digitalisimo\Elements\Video_Player_Widget::embed_parts( $url );
		} elseif ( preg_match( '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts)/|.*[?&]v=)|youtu\.be/)([A-Za-z0-9_-]{11})%i', $url, $m ) ) {
			$parts = array( 'service' => 'youtube', 'id' => $m[1] );
		} elseif ( preg_match( '%vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)%i', $url, $m ) ) {
			$parts = array( 'service' => 'vimeo', 'id' => $m[1] );
		} else {
			$parts = array();
		}
		if ( empty( $parts['service'] ) || $type !== $parts['service'] ) {
			return array();
		}
		$id  = (string) $parts['id'];
		$src = 'youtube' === $type ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id ) . '?rel=0&enablejsapi=1' : 'https://player.vimeo.com/video/' . rawurlencode( $id ) . '?dnt=1';
		return array( 'service' => $type, 'id' => $id, 'src' => $src );
	}

	/** Trabajo de la portada de un vídeo (con las fachadas del front), o []. */
	private static function facade_job( array $embed ): array {
		if ( ! $embed || ! class_exists( '\\Digitalisimo\\Elements\\Front\\Facade_Images' ) ) {
			return array();
		}
		return (array) \Digitalisimo\Elements\Front\Facade_Images::video( (string) $embed['service'], (string) $embed['id'] );
	}

	/** ¿Están aplicadas las fachadas de vídeo del sitio (nada de terceros antes del clic)? Sin el módulo, no. */
	private static function facades(): bool {
		return class_exists( '\\Digitalisimo\\Elements\\Front\\Facades' ) && \Digitalisimo\Elements\Front\Facades::active();
	}

	/** Portada servida por el sitio de un vídeo de un servicio (sólo con las fachadas aplicadas), o ''. */
	private static function facade_image( array $embed ): string {
		$job = self::facade_job( $embed );
		return $job && class_exists( '\\Digitalisimo\\Elements\\Front\\Facades' ) && \Digitalisimo\Elements\Front\Facades::active() ? (string) \Digitalisimo\Elements\Front\Facade_Images::url( $job ) : '';
	}

	/**
	 * Portadas que pinta la lista (para `Front\Facades::dynamic()`: mientras falte alguna, el widget no entra en la caché
	 * de elementos): las de los vídeos de un servicio sin miniatura propia del sitio.
	 */
	public static function facade_jobs( array $settings ): array {
		$jobs = array();
		foreach ( (array) ( $settings['tabs'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$job = self::facade_job( self::embed( (string) ( $item['type'] ?? 'youtube' ), self::item_url( $item ) ) );
			if ( $job ) {
				$jobs[] = $job;
			}
		}
		return $jobs;
	}

	/** ¿Es una dirección de este sitio (o relativa)? Lo de otro dominio no se pide antes del clic. */
	private static function local( string $url ): bool {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $host ) {
			return '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] );
		}
		$home = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_HOST ) : '';
		return '' !== $home && strtolower( $host ) === strtolower( $home );
	}

	/** Icono de los de PRO en SVG en línea o, si el usuario eligió otro, el de `Icons_Manager`. Decorativo. */
	public static function icon( $icon ): string {
		$icon  = is_array( $icon ) ? $icon : array();
		$value = is_string( $icon['value'] ?? null ) ? $icon['value'] : '';
		if ( isset( self::ICONS[ $value ] ) ) {
			return '<svg viewBox="0 0 ' . (int) self::ICONS[ $value ][0] . ' 512" aria-hidden="true" focusable="false"><path d="' . esc_attr( self::ICONS[ $value ][1] ) . '"/></svg>';
		}
		if ( empty( $icon['value'] ) || ! class_exists( '\\Elementor\\Icons_Manager' ) ) {
			return '';
		}
		return (string) \Elementor\Icons_Manager::try_get_icon_html( $icon, array( 'aria-hidden' => 'true' ) );
	}

	/** Miniatura de una fila: el adjunto (con `srcset`), una imagen del sitio, la portada de la fachada o ''. */
	private function thumbnail( array $item, array $embed ): string {
		$image = (array) ( $item['thumbnail'] ?? array() );
		$attrs = array( 'class' => 'digi-vpl__img', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '75px' );
		$id    = (int) ( $image['id'] ?? 0 );
		if ( $id && function_exists( 'wp_get_attachment_image' ) ) {
			$html = (string) wp_get_attachment_image( $id, 'full', false, $attrs );
			if ( '' !== $html ) {
				return $html;
			}
		}
		$url = (string) ( $image['url'] ?? '' );
		// La miniatura de otro dominio (la que guarda «Obtener datos del vídeo») se pinta como en PRO, salvo con las
		// fachadas del sitio, que no piden nada a terceros antes del clic: entonces, la portada servida por el sitio.
		if ( '' === $url || ( ! self::local( $url ) && self::facades() ) ) {
			$url = self::facade_image( $embed );
		}
		if ( '' === $url ) {
			return '';
		}
		return '<img class="digi-vpl__img" src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async">';
	}

	/** Imagen superpuesta del primer vídeo, con el tamaño de `image_overlay_size` (como PRO). */
	private function overlay_image( array $settings ): string {
		$image = (array) ( $settings['image_overlay'] ?? array() );
		$id    = (int) ( $image['id'] ?? 0 );
		$attrs = array( 'class' => 'digi-vpl__cover', 'alt' => '', 'decoding' => 'async' );
		if ( $id && function_exists( 'wp_get_attachment_image' ) ) {
			$size = (string) ( $settings['image_overlay_size'] ?? 'full' );
			$html = 'custom' === $size || '' === $size ? '' : (string) wp_get_attachment_image( $id, $size, false, $attrs );
			if ( '' === $html && class_exists( '\\Elementor\\Group_Control_Image_Size' ) ) {
				$url  = (string) Group_Control_Image_Size::get_attachment_image_src( $id, 'image_overlay', $settings );
				$html = '' !== $url ? '<img class="digi-vpl__cover" src="' . esc_url( $url ) . '" alt="" decoding="async">' : '';
			}
			if ( '' !== $html ) {
				return $html;
			}
		}
		$url = (string) ( $image['url'] ?? '' );
		return '' !== $url && self::local( $url ) ? '<img class="digi-vpl__cover" src="' . esc_url( $url ) . '" alt="" decoding="async">' : '';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$items    = array_values( array_filter( (array) ( $settings['tabs'] ?? array() ), 'is_array' ) );
		$id       = (string) $this->get_id();
		$uid      = 'digi-vpl-' . preg_replace( '/[^a-z0-9_-]/i', '', $id );
		$videos   = array();
		foreach ( $items as $index => $item ) {
			if ( 'section' !== ( $item['type'] ?? 'youtube' ) ) {
				$videos[] = $index;
			}
		}
		$first    = $videos ? $videos[0] : -1;
		$name     = (string) ( $settings['playlist_title'] ?? '' );
		$name_tag = self::tag( $settings['playlist_title_tag'] ?? 'h2', 'h2' );
		$thumbs   = ! empty( $settings['show_thumbnail'] );
		$play     = self::icon( $settings['play_icon'] ?? array() );
		$watched  = self::icon( $settings['watched_icon'] ?? array() );
		$overlay  = ! empty( $settings['show_image_overlay'] ) && 'yes' !== ( $settings['autoplay_on_load'] ?? '' );
		$data     = array(
			'data-digi-vpl' => $id,
			'data-next'     => 'yes' === ( $settings['autoplay_next'] ?? '' ),
			'data-watched'  => ! empty( $settings['show_watched_indication'] ),
			'data-onload'   => 'yes' === ( $settings['autoplay_on_load'] ?? '' ),
			// Como PRO: el reproductor del servicio del primer vídeo se crea sin esperar al clic, salvo con las fachadas.
			'data-embed'    => ! self::facades(),
			'data-lazy'     => ! empty( $settings['lazy_load'] ),
		);
		echo '<div class="digi-vpl" role="region" aria-labelledby="' . esc_attr( $uid ) . '-name"';
		foreach ( $data as $attr => $value ) {
			if ( false !== $value ) {
				echo ' ' . esc_attr( $attr ) . ( true === $value ? '' : '="' . esc_attr( (string) $value ) . '"' );
			}
		}
		echo '><div class="digi-vpl__main"><div class="digi-vpl__side"><div class="digi-vpl__header">';
		// El nombre de la lista es el texto principal del widget: HTML permitido, como en PRO.
		echo '<' . $name_tag . ' class="digi-vpl__name" id="' . esc_attr( $uid ) . '-name">' . wp_kses_post( $name ) . '</' . $name_tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista blanca.
		echo '<div class="digi-vpl__meta">';
		if ( ! empty( $settings['show_video_count'] ) ) {
			$count = count( $videos );
			/* translators: %d: número de vídeos de la lista. */
			echo '<span class="digi-vpl__count">' . esc_html( sprintf( _n( '%d video', '%d videos', $count, 'pro-elements' ), $count ) ) . '</span>';
		}
		echo '<button type="button" class="digi-vpl__fold" aria-expanded="true" aria-controls="' . esc_attr( $uid ) . '-list" aria-label="' . esc_attr__( 'Mostrar la lista de vídeos', 'pro-elements' ) . '">' . self::CARET . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio.
		echo '</div></div>';
		echo '<div class="digi-vpl__list" id="' . esc_attr( $uid ) . '-list"><div class="digi-vpl__items" role="tablist" aria-orientation="vertical" aria-labelledby="' . esc_attr( $uid ) . '-name">';
		foreach ( $items as $index => $item ) {
			$title = (string) ( $item['title'] ?? '' );
			if ( 'section' === ( $item['type'] ?? 'youtube' ) ) {
				$tag = self::tag( $item['section_html_tag'] ?? 'h3', 'h3' );
				echo '<' . $tag . ' class="digi-vpl__sec" role="presentation">' . wp_kses_post( $title ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista blanca.
				continue;
			}
			$n      = $index + 1;
			$active = $index === $first;
			$embed  = self::embed( (string) ( $item['type'] ?? 'youtube' ), self::item_url( $item ) );
			$icons  = '<span class="digi-vpl__icon digi-vpl__icon--play">' . $play . '</span><span class="digi-vpl__icon digi-vpl__icon--seen">' . $watched . '</span>';
			echo '<div class="digi-vpl__tab' . ( $active ? ' digi-vpl__tab--on' : '' ) . '" role="tab" id="' . esc_attr( $uid . '-tab-' . $n ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $uid . '-panel-' . $n ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '" data-tab="' . (int) $n . '" data-item="' . esc_attr( (string) ( $item['_id'] ?? '' ) ) . '">';
			echo $thumbs ? '<span class="digi-vpl__thumb">' . $this->thumbnail( $item, $embed ) . $icons . '</span>' : $icons; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen y SVG escapados.
			$tag = self::tag( $item['video_html_tag'] ?? 'h4', 'h4' );
			// Como PRO, el texto en un botón (fuera del orden del teclado: la pestaña es el control).
			echo '<' . $tag . ' class="digi-vpl__title"><button type="button" tabindex="-1">' . wp_kses_post( $title ) . '</button></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- etiqueta de la lista blanca.
			$duration = (string) ( $item['duration'] ?? '' );
			if ( ! empty( $settings['show_duration'] ) && '' !== $duration ) {
				echo '<span class="digi-vpl__time">' . esc_html( $duration ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div><div class="digi-vpl__shadow digi-vpl__shadow--top" aria-hidden="true"></div><div class="digi-vpl__shadow digi-vpl__shadow--bottom" aria-hidden="true"></div></div></div>';

		echo '<div class="digi-vpl__stage">';
		foreach ( $videos as $index ) {
			$item  = $items[ $index ];
			$n     = $index + 1;
			$type  = (string) ( $item['type'] ?? 'youtube' );
			$url   = self::item_url( $item );
			$embed = self::embed( $type, $url );
			$title = trim( wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ) );
			$attrs = 'class="digi-vpl__panel" role="tabpanel" id="' . esc_attr( $uid . '-panel-' . $n ) . '" aria-labelledby="' . esc_attr( $uid . '-tab-' . $n ) . '" tabindex="0" data-tab="' . (int) $n . '" data-type="' . esc_attr( $type ) . '"';
			if ( $embed ) {
				$attrs .= ' data-src="' . esc_url( $embed['src'] ) . '" data-title="' . esc_attr( $title ) . '"';
			}
			echo '<div ' . $attrs . ( $index === $first ? '' : ' hidden' ) . '><div class="digi-vpl__player">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados.
			$name = sprintf( /* translators: %s: título del vídeo. */ esc_html__( 'Reproducir el vídeo: %s', 'pro-elements' ), $title );
			if ( 'hosted' === $type && '' !== $url && preg_match( '#^(https?:)?//|^/#i', $url ) ) {
				echo '<video class="digi-vpl__video" src="' . esc_url( $url ) . '" controls preload="none" playsinline title="' . esc_attr( $title ) . '"></video>';
			} elseif ( $embed ) {
				$poster = self::facade_image( $embed );
				echo '' !== $poster ? '<img class="digi-vpl__cover" src="' . esc_url( $poster ) . '" alt="" loading="lazy" decoding="async">' : '';
				echo '<button type="button" class="digi-vpl__play" aria-label="' . esc_attr( $name ) . '">' . self::icon( array( 'value' => 'far fa-play-circle' ) ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio.
			}
			echo '</div>';
			if ( $overlay && $index === $first ) {
				echo '<div class="digi-vpl__overlay">' . $this->overlay_image( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen escapada.
				echo '<button type="button" class="digi-vpl__play" aria-label="' . esc_attr( $name ) . '">' . self::icon( $settings['show_play_icon'] ?? array() ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio o del núcleo.
			}
			echo '</div>';
		}
		echo '</div></div>';
		$this->render_inner_tabs( $settings, $items, $videos, $first, $uid );
		echo '</div>';
	}

	/** Pestañas internas de cada vídeo que las muestra (la del vídeo activo, visible). */
	private function render_inner_tabs( array $settings, array $items, array $videos, int $first, string $uid ) {
		$blocks = '';
		$labels = array( (string) ( $settings['inner_tab_title_1'] ?? '' ), (string) ( $settings['inner_tab_title_2'] ?? '' ) );
		$fold   = 'collapsible' === ( $settings['inner_tab_is_content_collapsible'] ?? '' );
		$more   = (string) ( $settings['inner_tab_label_show_more'] ?? '' );
		$less   = (string) ( $settings['inner_tab_label_show_less'] ?? '' );
		foreach ( $videos as $index ) {
			$item = $items[ $index ];
			if ( 'show' !== ( $item['inner_tab_is_content_visible'] ?? '' ) ) {
				continue;
			}
			$n        = $index + 1;
			$contents = array( (string) ( $item['inner_tab_content_1'] ?? '' ), (string) ( $item['inner_tab_content_2'] ?? '' ) );
			$tabs     = '';
			$panels   = '';
			$selected = false;
			foreach ( $contents as $i => $content ) {
				if ( '' === trim( $content ) ) {
					continue;
				}
				$key    = $uid . '-inner-' . $n . '-' . ( $i + 1 );
				$on     = ! $selected;
				$tabs  .= '<button type="button" class="digi-vpl__in-tab' . ( $on ? ' digi-vpl__in-tab--on' : '' ) . '" role="tab" id="' . esc_attr( $key ) . '-tab" aria-selected="' . ( $on ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $key ) . '"' . ( $on ? '' : ' tabindex="-1"' ) . '>' . wp_kses_post( $labels[ $i ] ) . '</button>';
				// Título del panel en el móvil, donde la fila de pestañas se oculta (como el `e-tab-mobile-title` de PRO).
				$panels .= '<button type="button" class="digi-vpl__in-mob" aria-expanded="' . ( $on ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $key ) . '">' . wp_kses_post( $labels[ $i ] ) . '</button>';
				$panels .= '<div class="digi-vpl__in-panel' . ( $fold ? ' digi-vpl__in-panel--fold' : '' ) . '" role="tabpanel" id="' . esc_attr( $key ) . '" aria-labelledby="' . esc_attr( $key ) . '-tab"' . ( $on ? '' : ' hidden' ) . '>'
					// El contenido se edita en el lienzo con la barra avanzada, como en PRO (`tabs.<índice>.inner_tab_content_<n>`).
					. '<div class="digi-vpl__in-text"><div' . Cards::inline_attributes( array(), 'tabs.' . $index . '.inner_tab_content_' . ( $i + 1 ), 'advanced' ) . '>' . $this->parse_text( $content ) . '</div></div>'
					. ( $fold ? '<button type="button" class="digi-vpl__more" aria-expanded="false" data-more="' . esc_attr( wp_strip_all_tags( $more ) ) . '" data-less="' . esc_attr( wp_strip_all_tags( $less ) ) . '" hidden>' . esc_html( wp_strip_all_tags( $more ) ) . '</button>' : '' )
					. '</div>';
				$selected = true;
			}
			$label   = sprintf( /* translators: %s: título del vídeo. */ esc_html__( 'Contenido: %s', 'pro-elements' ), trim( wp_strip_all_tags( (string) ( $item['title'] ?? '' ) ) ) );
			$blocks .= '<div class="digi-vpl__in" data-tab="' . (int) $n . '"' . ( $index === $first ? '' : ' hidden' ) . '>'
				. ( '' !== $tabs ? '<div class="digi-vpl__in-list" role="tablist" aria-label="' . esc_attr( $label ) . '">' . $tabs . '</div><div class="digi-vpl__in-panels">' . $panels . '</div>' : '' )
				. '</div>';
		}
		if ( '' !== $blocks ) {
			echo '<div class="digi-vpl__extra">' . $blocks . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio escapado; el contenido es el del editor de texto.
		}
	}

	/** Contenido del editor de texto como lo pinta el núcleo (párrafos y códigos cortos). */
	private function parse_text( string $content ): string {
		return method_exists( $this, 'parse_text_editor' ) ? (string) $this->parse_text_editor( $content ) : wp_kses_post( $content );
	}
}
