<?php
namespace Digitalisimo\Elements\F7b\Overrides;

defined( 'ABSPATH' ) || exit;

/**
 * Controles de las sobrescrituras (F7b, frente E; D12, V.2 y V.12). Sólo se registran en el editor: el front lee los
 * ajustes crudos y no necesita las pilas.
 *
 * - `_digitalisimo_slot` («Clave de sobrescritura», texto, **sin `default`**, `style_transfer => false`,
 *   `render_type => none`) al final de la primera sección de contenido de Encabezado, Editor de texto, Imagen, Botón,
 *   Icono, Vídeo y Caja de icono, seguido de `digitalisimo_slot_note` (HTML fijo vacío que el script del editor llena
 *   con el aviso de clave repetida o no válida y con «N instancias sobrescriben esta clave»). El script los oculta
 *   fuera de las plantillas de la biblioteca (`elementor_library`): las pilas de controles son por tipo de widget, no
 *   por documento, y el núcleo no tiene condiciones de documento.
 * - En el Plantilla, tras `template_id`: el repetidor `digitalisimo_overrides` (sin filas por defecto,
 *   `style_transfer => false`) con `slot` (lista que el script llena con `digitalisimo_f7b_slots`), `kind`
 *   (`save_default`: el editor no guarda los valores por defecto y la fila debe decir qué hace), `text`, `html`,
 *   `image` y `link` (sólo `is_external` y `nofollow`), y el aviso `digitalisimo_overrides_notice`
 *   («N sobrescrituras sin destino»). Todo con la condición `template_id!` vacío.
 *
 * Ningún control tiene `selectors`, `prefix_class` ni `frontend_available`: no cambian el HTML ni el CSS de nada.
 */
final class Controls {
	const NOTE   = 'digitalisimo_slot_note';
	const NOTICE = 'digitalisimo_overrides_notice';

	/** Primera sección de contenido de cada widget con clave. */
	const SECTIONS = array(
		'heading'     => 'section_title',
		'text-editor' => 'section_editor',
		'image'       => 'section_image',
		'button'      => 'section_button',
		'icon'        => 'section_icon',
		'video'       => 'section_video',
		'icon-box'    => 'section_icon',
	);

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::SECTIONS as $widget => $section ) {
			add_action( "elementor/element/{$widget}/{$section}/before_section_end", array( __CLASS__, 'slot' ), 10, 2 );
		}
		add_action( 'elementor/element/template/section_template/before_section_end', array( __CLASS__, 'overrides' ), 10, 2 );
		// La Plantilla propia (C9) repite la sección `section_template` del widget de PRO.
		add_action( 'elementor/element/digitalisimo-template/section_template/before_section_end', array( __CLASS__, 'overrides' ), 10, 2 );
	}

	/** Etiqueta visible de cada `kind`. */
	public static function kind_labels(): array {
		return array(
			'text'  => __( 'Texto', 'pro-elements' ),
			'html'  => __( 'HTML', 'pro-elements' ),
			'image' => __( 'Imagen', 'pro-elements' ),
			'link'  => __( 'Enlace', 'pro-elements' ),
			'hide'  => __( 'Ocultar', 'pro-elements' ),
		);
	}

	/**
	 * Clave de sobrescritura en un widget de `SECTIONS`.
	 *
	 * @param mixed $element Pila del widget.
	 * @param mixed $args    Argumentos de la sección.
	 */
	public static function slot( $element, $args = array() ): void {
		if ( ! $element instanceof \Elementor\Controls_Stack || null !== $element->get_controls( Apply::SLOT ) ) {
			return;
		}
		$element->add_control(
			Apply::SLOT,
			array(
				'label'          => __( 'Clave de sobrescritura', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::TEXT,
				'separator'      => 'before',
				'placeholder'    => 'titulo',
				'ai'             => array( 'active' => false ),
				'render_type'    => 'none',
				'style_transfer' => false,
				'description'    => __( 'Sólo en plantillas. Con una clave, cada widget Plantilla que use esta plantilla puede cambiar este elemento sin tocar la plantilla. De 1 a 40 minúsculas, números, «-» o «_».', 'pro-elements' ),
			)
		);
		$element->add_control(
			self::NOTE,
			array(
				'type'           => \Elementor\Controls_Manager::RAW_HTML,
				'raw'            => '<div class="digitalisimo-slot-note elementor-panel-alert elementor-panel-alert-info" aria-live="polite"></div>',
				'style_transfer' => false,
			)
		);
	}

	/**
	 * Repetidor de sobrescrituras y aviso en el widget Plantilla.
	 *
	 * @param mixed $element Pila del Plantilla.
	 * @param mixed $args    Argumentos de la sección.
	 */
	public static function overrides( $element, $args = array() ): void {
		if ( ! $element instanceof \Elementor\Controls_Stack || null !== $element->get_controls( Apply::REPEATER ) || ! class_exists( 'Elementor\\Repeater' ) ) {
			return;
		}
		$labels   = self::kind_labels();
		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'slot',
			array(
				'label'          => __( 'Clave', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'options'        => array( '' => __( 'Elige una clave', 'pro-elements' ) ),
				'style_transfer' => false,
			)
		);
		$repeater->add_control(
			'kind',
			array(
				'label'          => __( 'Cambiar', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'options'        => $labels,
				'default'        => Apply::DEFAULT_KIND,
				'save_default'   => true,
				'style_transfer' => false,
			)
		);
		$repeater->add_control(
			'text',
			array(
				'label'          => __( 'Texto', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::TEXTAREA,
				'rows'           => 3,
				'ai'             => array( 'active' => false ),
				'style_transfer' => false,
				'condition'      => array( 'kind' => 'text' ),
			)
		);
		$repeater->add_control(
			'html',
			array(
				'label'          => __( 'HTML', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::WYSIWYG,
				'ai'             => array( 'active' => false ),
				'style_transfer' => false,
				'condition'      => array( 'kind' => 'html' ),
			)
		);
		$repeater->add_control(
			'image',
			array(
				'label'          => __( 'Imagen', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::MEDIA,
				'media_types'    => array( 'image' ),
				'ai'             => array( 'active' => false ),
				'style_transfer' => false,
				'condition'      => array( 'kind' => 'image' ),
			)
		);
		$repeater->add_control(
			'link',
			array(
				'label'          => __( 'Enlace', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::URL,
				'options'        => array( 'is_external', 'nofollow' ),
				'style_transfer' => false,
				'condition'      => array( 'kind' => 'link' ),
			)
		);
		$repeater->add_control(
			'hide_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Quita el elemento sólo en esta instancia. Si el sitio vuelve a una versión anterior de DIGITALÍSIMO Elements sin sobrescrituras, el elemento vuelve a verse.', 'pro-elements' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				'style_transfer'  => false,
				'condition'       => array( 'kind' => 'hide' ),
			)
		);
		$element->add_control(
			Apply::REPEATER,
			array(
				'label'          => __( 'Sobrescrituras', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::REPEATER,
				'fields'         => $repeater->get_controls(),
				'title_field'    => '<# var digiKinds = ' . wp_json_encode( $labels ) . '; #>{{ slot }} · {{ digiKinds[ kind ] || kind }}',
				'prevent_empty'  => false,
				'separator'      => 'before',
				'style_transfer' => false,
				'description'    => __( 'Cambia en esta instancia el contenido de los elementos de la plantilla que tienen «Clave de sobrescritura». La plantilla no cambia.', 'pro-elements' ),
				'condition'      => array( 'template_id!' => '' ),
			)
		);
		$element->add_control(
			self::NOTICE,
			array(
				'type'           => \Elementor\Controls_Manager::RAW_HTML,
				'raw'            => '<div class="digitalisimo-overrides-notice elementor-panel-alert elementor-panel-alert-warning" role="status"></div>',
				'style_transfer' => false,
				'condition'      => array( 'template_id!' => '' ),
			)
		);
	}
}
