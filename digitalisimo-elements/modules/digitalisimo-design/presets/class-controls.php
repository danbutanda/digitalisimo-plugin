<?php
namespace Digitalisimo\Elements\Design\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * Control `digitalisimo_preset`: el vínculo de un elemento v3 con un preset de estilo (V.2.4 de la especificación).
 *
 * - Una sección «Preset de estilo» al principio de la pestaña Avanzado:
 *   - widgets: `elementor/element/common/_section_style/before_section_start`. **Sólo `common`**: con el marcado
 *     optimizado, el núcleo repite los ganchos de `common` para la pila `common-optimized`
 *     (`Controls_Stack::should_manually_trigger_common_action()`), así que cada pila tiene el control una sola vez;
 *   - contenedores: `elementor/element/container/section_layout/before_section_start`, la primera sección de su
 *     pestaña Avanzado (`Container::register_advanced_controls()`).
 *   Los elementos atómicos (v4) no usan esas pilas: nunca ven el control (D9).
 * - SELECT **sin `prefix_class`** ni `frontend_available`: no añade clases ni `data-settings` al HTML.
 *   `render_type => 'ui'`: al cambiar el vínculo (también al deshacer o con «Pegar estilo») el editor llama a
 *   `renderUI()`, que repinta los estilos sin pedir HTML al servidor; sólo afecta al editor. `style_transfer => true`
 *   («Pegar estilo» copia el vínculo).
 * - Opciones: en el editor (administración o vista previa), «Sin preset» y todos los presets aplicables; el script
 *   del editor las filtra por tipo (V-I2). En el front, sólo «Sin preset»: no se lee la opción de presets.
 *
 * Quién puede cambiar el vínculo lo decide el servidor al guardar (`Guard`).
 */
final class Controls {
	const CONTROL = 'digitalisimo_preset';
	const SECTION = 'digitalisimo_section_preset';

	/** Ganchos donde se inserta la sección: pila y sección del núcleo. */
	const HOOKS = array(
		'elementor/element/common/_section_style/before_section_start',
		'elementor/element/container/section_layout/before_section_start',
	);

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::HOOKS as $hook ) {
			add_action( $hook, array( __CLASS__, 'register' ), 10, 2 );
		}
	}

	/**
	 * Añade la sección y el control a la pila del elemento.
	 *
	 * @param mixed $element Pila de controles (`common`, `common-optimized` o el contenedor).
	 * @param mixed $args    Argumentos de la sección del núcleo.
	 */
	public static function register( $element, $args = array() ): void {
		if ( ! $element instanceof \Elementor\Controls_Stack || null !== $element->get_controls( self::CONTROL ) ) {
			return;
		}
		$element->start_controls_section(
			self::SECTION,
			array(
				'label' => __( 'Preset de estilo', 'pro-elements' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);
		$element->add_control(
			self::CONTROL,
			array(
				'label'          => __( 'Preset', 'pro-elements' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'default'        => '',
				'options'        => self::options(),
				'render_type'    => 'ui',
				'style_transfer' => true,
				'description'    => __( 'Aplica el estilo guardado en el preset. Lo que cambies en este elemento gana siempre sobre el preset.', 'pro-elements' ),
			)
		);
		$element->end_controls_section();
	}

	/** Opciones del control: «Sin preset» y, en el editor, todos los presets aplicables. */
	public static function options(): array {
		$options = array( '' => __( 'Sin preset', 'pro-elements' ) );
		if ( ! self::is_editor() ) {
			return $options;
		}
		return $options + Store::options();
	}

	/** Administración (el editor y sus peticiones) o la vista previa del editor. */
	public static function is_editor(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		$plugin = class_exists( 'Elementor\\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		return $plugin && isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode();
	}
}
