<?php
namespace Digitalisimo\Elements\F7b\Inline_Tags;

defined( 'ABSPATH' ) || exit;

/**
 * Etiquetas dinámicas dentro del texto (F7b, frente I; V.8 de la especificación, que sustituye a §3.7).
 *
 * - **Interruptor** `digitalisimo_inline_tags` (sin `default`, `style_transfer => false`, guarda `yes`: la aguja de
 *   `F7b\Flags`) al final de la sección de contenido de los widgets de `WIDGETS`, con «Insertar dato» debajo.
 * - **Sustitución en los ajustes** (C1-10): en `elementor/widget/before_render_content`, sólo los controles de texto
 *   declarados en `WIDGETS` pasan por `Engine::render()`, con `set_settings()`. Los enlaces, los atributos y el HTML que
 *   pinta el widget nunca se tocan. Como algo pudo leer ya los ajustes para mostrar (`get_settings_for_display()` los
 *   memoriza), se vacía esa memoria con `reset_render_state()` conservando los atributos ya añadidos.
 * - En el editor y su vista previa no se sustituye: el lienzo muestra lo que se escribió (como las plantillas JS de
 *   esos widgets) y la edición en línea nunca guarda un valor resuelto en lugar de la etiqueta.
 * - **Caché**: un elemento con etiquetas es dinámico para la caché de elementos (`elementor/element/is_dynamic_content`,
 *   `element-base.php:609`); `{user:…}` con sesión llama a `Visitor_Content::flag_page()` desde `Engine`.
 * - **Editor**: `inline-tags-editor.js` (sólo en el editor) inserta la etiqueta elegida en el control indicado con
 *   `document/elements/settings` y pide la vista previa a `digitalisimo_f7b_inline_preview` (acción de
 *   `elementor/ajax/register_actions` con `current_user_can( 'edit_post', editor_post_id )`; sólo lee).
 *
 * Con `Flags::active( 'inline_tags' )` falso no engancha nada; en el front, nunca registra los controles.
 */
final class Module {
	const FLAG    = 'inline_tags';
	const CONTROL = 'digitalisimo_inline_tags';
	const HELP    = 'digitalisimo_inline_tags_help';
	const VALUE   = 'yes';
	const AJAX    = 'digitalisimo_f7b_inline_preview';
	const HANDLE  = 'digitalisimo-f7b-inline-tags';
	const OBJECT  = 'digitalisimoInlineTags';
	const SCRIPT  = 'assets/js/inline-tags-editor.js';

	/**
	 * Widgets con el interruptor: sección de contenido donde se añade y controles de texto que se sustituyen
	 * (nombres comprobados en el núcleo, en PRO y en los widgets propios).
	 */
	const WIDGETS = array(
		'heading'                        => array(
			'section'  => 'section_title',
			'controls' => array( 'title' ),
		),
		'text-editor'                    => array(
			'section'  => 'section_editor',
			'controls' => array( 'editor' ),
		),
		'button'                         => array(
			'section'  => 'section_button',
			'controls' => array( 'text' ),
		),
		'digitalisimo-advanced-button'   => array(
			'section'  => 'section_button',
			'controls' => array( 'text' ),
		),
		'icon-box'                       => array(
			'section'  => 'section_icon',
			'controls' => array( 'title_text', 'description_text' ),
		),
		'digitalisimo-advanced-icon-box' => array(
			'section'  => 'section_icon_box',
			'controls' => array( 'title_text', 'sub_title_text', 'description_text' ),
		),
		'call-to-action'                 => array(
			'section'  => 'section_content',
			'controls' => array( 'title', 'description', 'button' ),
		),
		'digitalisimo-call-out'          => array(
			'section'  => 'section_call_out',
			'controls' => array( 'title', 'description', 'button_text' ),
		),
	);

	/** Símbolos que necesita. */
	const SYMBOLS = array( 'Elementor\\Plugin', 'Elementor\\Controls_Manager', 'esc_html', 'wp_strip_all_tags' );

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		if ( ! self::requires( self::SYMBOLS ) || ! self::active() ) {
			return;
		}
		add_action( 'elementor/widget/before_render_content', array( __CLASS__, 'before_render_content' ) );
		add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'is_dynamic_content' ), 10, 2 );
		if ( ! self::editor_context() ) {
			return;
		}
		foreach ( self::WIDGETS as $widget => $def ) {
			add_action( "elementor/element/{$widget}/{$def['section']}/before_section_end", array( __CLASS__, 'register_controls' ), 10, 2 );
		}
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_ajax' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Interruptor e «Insertar dato» al final de la sección de contenido.
	 *
	 * @param mixed $element Widget.
	 * @param mixed $args    Argumentos de la sección.
	 */
	public static function register_controls( $element, $args = array() ): void {
		if ( ! $element instanceof \Elementor\Controls_Stack || null !== $element->get_controls( self::CONTROL ) ) {
			return;
		}
		$name = $element->get_name();
		if ( ! isset( self::WIDGETS[ $name ] ) ) {
			return;
		}
		$element->add_control(
			self::CONTROL,
			array(
				'label'          => __( 'Datos dentro del texto', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::SWITCHER,
				'return_value'   => self::VALUE,
				'separator'      => 'before',
				'render_type'    => 'none',
				'style_transfer' => false,
				'description'    => __( 'Al publicar, sustituye etiquetas como {post:title} o {site:name} escritas en el texto. En el editor se ven tal cual. Si vuelves a una versión anterior de DIGITALÍSIMO Elements, también se verán tal cual en la página.', 'pro-elements' ),
			)
		);
		$element->add_control(
			self::HELP,
			array(
				'type'           => \Elementor\Controls_Manager::RAW_HTML,
				'raw'            => self::help( self::WIDGETS[ $name ]['controls'] ),
				'condition'      => array( self::CONTROL => self::VALUE ),
				'style_transfer' => false,
			)
		);
	}

	/**
	 * Sustituye las etiquetas en los ajustes de los controles de texto declarados, antes de pintar el widget.
	 *
	 * @param mixed $widget Widget.
	 */
	public static function before_render_content( $widget ): void {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || ! method_exists( $widget, 'set_settings' ) ) {
			return;
		}
		$name = $widget->get_name();
		if ( ! isset( self::WIDGETS[ $name ] ) || self::VALUE !== $widget->get_settings( self::CONTROL ) || self::in_editor() ) {
			return;
		}
		$changed = false;
		foreach ( self::WIDGETS[ $name ]['controls'] as $control ) {
			$raw = $widget->get_settings( $control );
			if ( ! is_string( $raw ) || false === strpos( $raw, '{' ) ) {
				continue;
			}
			$new = Engine::render( $raw );
			if ( $new !== $raw ) {
				$widget->set_settings( $control, $new );
				$changed = true;
			}
		}
		if ( $changed ) {
			self::refresh( $widget );
		}
	}

	/**
	 * Un elemento con etiquetas no entra en la caché de elementos: su texto depende de la entrada, del usuario o de la URL.
	 *
	 * @param mixed $is_dynamic Decisión previa.
	 * @param mixed $raw        Datos crudos del elemento.
	 */
	public static function is_dynamic_content( $is_dynamic, $raw = array() ) {
		if ( $is_dynamic || ! is_array( $raw ) || ! isset( $raw['widgetType'], self::WIDGETS[ $raw['widgetType'] ] ) ) {
			return $is_dynamic;
		}
		$settings = isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array();
		if ( ! isset( $settings[ self::CONTROL ] ) || self::VALUE !== $settings[ self::CONTROL ] ) {
			return $is_dynamic;
		}
		foreach ( self::WIDGETS[ $raw['widgetType'] ]['controls'] as $control ) {
			if ( isset( $settings[ $control ] ) && Engine::has_tokens( $settings[ $control ] ) ) {
				return true;
			}
		}
		return $is_dynamic;
	}

	/** @param mixed $ajax Módulo AJAX del núcleo. */
	public static function register_ajax( $ajax ): void {
		if ( is_object( $ajax ) && method_exists( $ajax, 'register_ajax_action' ) ) {
			$ajax->register_ajax_action( self::AJAX, array( __CLASS__, 'ajax_preview' ) );
		}
	}

	/**
	 * Vista previa de una etiqueta para «Insertar dato». Sólo lee; exige poder editar el documento del editor.
	 *
	 * @param mixed $data `{ token, editor_post_id }`.
	 * @throws \Exception Sin permiso (403).
	 */
	public static function ajax_preview( $data ): array {
		$data    = is_array( $data ) ? $data : array();
		$post_id = isset( $data['editor_post_id'] ) && is_scalar( $data['editor_post_id'] ) ? absint( $data['editor_post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \Exception( esc_html__( 'No tienes permiso para ver esta vista previa.', 'pro-elements' ), 403 );
		}
		$token = isset( $data['token'] ) && is_string( $data['token'] ) ? substr( $data['token'], 0, 300 ) : '';
		return Engine::preview( $token, $post_id );
	}

	/** Script del editor y su configuración. */
	public static function enqueue(): void {
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, dirname( __DIR__ ) . '/class-module.php' ), array( 'elementor-editor', 'jquery' ), $version, true );
		wp_add_inline_script(
			self::HANDLE,
			'window.' . self::OBJECT . '=' . wp_json_encode(
				array(
					'action' => self::AJAX,
					'i18n'   => self::strings(),
				)
			) . ';',
			'before'
		);
	}

	/** Textos de `inline-tags-editor.js`. */
	public static function strings(): array {
		return array(
			'loading' => __( 'Calculando la vista previa…', 'pro-elements' ),
			'ok'      => __( 'Vista previa con este documento:', 'pro-elements' ),
			'empty'   => __( 'Este documento no tiene ese dato: no se imprimirá nada.', 'pro-elements' ),
			'blocked' => __( 'No se sustituirá: es un dato protegido o de un tipo que no se puede mostrar.', 'pro-elements' ),
			'unknown' => __( 'No es una etiqueta reconocida: se verá tal cual.', 'pro-elements' ),
			'url'     => __( 'Depende de la URL; sólo se imprime uno de estos valores:', 'pro-elements' ),
			'missing' => __( 'Completa el dato antes de insertarlo.', 'pro-elements' ),
			'failed'  => __( 'No se pudo calcular la vista previa.', 'pro-elements' ),
		);
	}

	/**
	 * HTML de «Insertar dato»: la lista blanca, el argumento, el control de destino y la vista previa.
	 *
	 * @param string[] $controls Controles de texto del widget.
	 */
	public static function help( array $controls ): string {
		$options = '';
		foreach ( Engine::choices() as $group ) {
			$options .= '<optgroup label="' . esc_attr( $group['label'] ) . '">';
			foreach ( $group['options'] as $o ) {
				$options .= '<option value="' . esc_attr( $o['value'] ) . '" data-sep="' . esc_attr( $o['sep'] ) . '" data-arg="' . esc_attr( $o['arg'] ) . '"' . ( $o['required'] ? ' data-required="1"' : '' ) . '>' . esc_html( $o['label'] ) . '</option>';
			}
			$options .= '</optgroup>';
		}
		$targets = '';
		foreach ( $controls as $control ) {
			$targets .= '<option value="' . esc_attr( $control ) . '">' . esc_html( self::label( $control ) ) . '</option>';
		}
		$target = 1 < count( $controls )
			? '<label class="digitalisimo-inline-tags__row">' . esc_html__( 'Insertar en', 'pro-elements' ) . ' <select data-digi-inline-target>' . $targets . '</select></label>'
			: '<input type="hidden" data-digi-inline-target value="' . esc_attr( (string) reset( $controls ) ) . '">';
		return '<div class="digitalisimo-inline-tags" data-digi-inline-tags>'
			. '<label class="digitalisimo-inline-tags__row">' . esc_html__( 'Dato', 'pro-elements' ) . ' <select data-digi-inline-tag>' . $options . '</select></label>'
			. '<label class="digitalisimo-inline-tags__row" data-digi-inline-arg hidden><span data-digi-inline-arg-label></span> <input type="text" maxlength="120" data-digi-inline-arg-input></label>'
			. $target
			. '<button type="button" class="elementor-button" data-digi-inline-insert>' . esc_html__( 'Insertar dato', 'pro-elements' ) . '</button>'
			. '<p class="elementor-control-field-description" data-digi-inline-preview role="status" aria-live="polite"></p>'
			. '<p class="elementor-control-field-description">' . esc_html__( 'El dato se imprime siempre como texto, nunca como HTML ni dentro de enlaces. Las claves y los campos protegidos (contraseñas, correos, teléfonos, nombres…) no se sustituyen. Con datos del usuario, la página no se guarda en la caché para quien tiene la sesión iniciada; la caché de página debe saltarse con la sesión iniciada, como es habitual. Un parámetro de la URL sólo imprime uno de los valores que escribas: {query:plan|basico,pro}.', 'pro-elements' ) . '</p>'
			. '</div>';
	}

	// -----------------------------------------------------------------------------------------------------------------

	/** @param string $control Nombre del control de texto. */
	private static function label( string $control ): string {
		$labels = array(
			'title'            => __( 'Título', 'pro-elements' ),
			'title_text'       => __( 'Título', 'pro-elements' ),
			'sub_title_text'   => __( 'Subtítulo', 'pro-elements' ),
			'description'      => __( 'Descripción', 'pro-elements' ),
			'description_text' => __( 'Descripción', 'pro-elements' ),
			'button'           => __( 'Texto del botón', 'pro-elements' ),
			'button_text'      => __( 'Texto del botón', 'pro-elements' ),
			'text'             => __( 'Texto', 'pro-elements' ),
			'editor'           => __( 'Texto', 'pro-elements' ),
		);
		return isset( $labels[ $control ] ) ? $labels[ $control ] : $control;
	}

	/**
	 * Vacía la memoria de ajustes para mostrar sin perder los atributos que otros ganchos ya añadieron.
	 *
	 * @param object $widget Widget.
	 */
	private static function refresh( $widget ): void {
		if ( ! method_exists( $widget, 'reset_render_state' ) || ! method_exists( $widget, 'get_render_attributes' ) || ! method_exists( $widget, 'set_render_attribute' ) ) {
			return;
		}
		$attributes = $widget->get_render_attributes();
		$widget->reset_render_state();
		foreach ( (array) $attributes as $element => $keys ) {
			foreach ( (array) $keys as $key => $value ) {
				$widget->set_render_attribute( $element, $key, $value );
			}
		}
	}

	/** ¿Editor o su vista previa? Ahí el texto se muestra tal cual. */
	private static function in_editor(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	/** ¿Usa algún documento las etiquetas, o es una petición del editor? (`F7b\Flags`, frente A). */
	private static function active(): bool {
		$flags = 'Digitalisimo\\Elements\\F7b\\Flags';
		return class_exists( $flags ) && method_exists( $flags, 'active' ) && (bool) $flags::active( self::FLAG );
	}

	/** ¿Administración o editor? Sólo ahí hacen falta los controles, la acción AJAX y el script. */
	private static function editor_context(): bool {
		$f7b = 'Digitalisimo\\Elements\\F7b\\Module';
		if ( class_exists( $f7b ) && method_exists( $f7b, 'editing' ) && $f7b::editing() ) {
			return true;
		}
		return function_exists( 'is_admin' ) && is_admin();
	}

	/**
	 * Guarda de símbolos: la de `F7b\Module` (frente A) si existe; si no, la misma comprobación aquí.
	 *
	 * @param string[] $symbols Clases o funciones.
	 */
	private static function requires( array $symbols ): bool {
		$f7b = 'Digitalisimo\\Elements\\F7b\\Module';
		if ( class_exists( $f7b ) && method_exists( $f7b, 'requires' ) ) {
			return (bool) $f7b::requires( $symbols, 'inline_tags' );
		}
		foreach ( $symbols as $symbol ) {
			if ( ! function_exists( $symbol ) && ! class_exists( $symbol ) ) {
				return false;
			}
		}
		return true;
	}
}
