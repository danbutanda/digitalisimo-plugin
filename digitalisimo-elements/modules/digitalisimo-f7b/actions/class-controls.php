<?php
namespace Digitalisimo\Elements\F7b\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Controles de las acciones con destino (F7b, frente B; V.3 y V.12).
 *
 * Sección «Acciones» en la pestaña Avanzado de los widgets v3 (`common`: el núcleo repite sus ganchos para
 * `common-optimized`), contenedores, secciones y columnas, detrás de «Adaptable» (`_section_responsive`, que tienen
 * los cuatro). Tres controles, todos con `style_transfer => false` («Pegar estilo», «Aplicar a todos» y los presets
 * de F7a no los copian) y sin `default` que cambie nada:
 *
 * - `_digitalisimo_hidden_initial` («Oculto al cargar»): el destino sale del servidor con `hidden`;
 * - `_digitalisimo_actions_on`: interruptor que muestra el repetidor y declara en `assets` el script del front, así
 *   la caché de elementos y el cargador de recursos del núcleo lo encolan sólo donde se usa (C1-11);
 * - `_digitalisimo_actions`: repetidor de disparador, acción y destino. El selector de destino lo llena
 *   `assets/js/actions-editor.js` con los elementos del documento abierto, leídos del modelo del editor (sin AJAX).
 *
 * Guardan exactamente las agujas de `F7b\Flags`: `"_digitalisimo_actions_on":"yes"` y
 * `"_digitalisimo_hidden_initial":"yes"`.
 */
final class Controls {
	const SECTION = 'digitalisimo_actions_section';
	const ON      = '_digitalisimo_actions_on';
	const LIST    = '_digitalisimo_actions';
	const HIDDEN  = '_digitalisimo_hidden_initial';
	const NOTE    = '_digitalisimo_actions_note';

	/** Pilas y sección tras la que entra «Acciones». */
	const STACKS = array( 'common', 'container', 'section', 'column' );
	const AFTER  = '_section_responsive';

	/** @var bool */
	private static $initialized = false;

	/** Disparadores en el editor (español, sin nombres de proveedores). */
	public static function trigger_labels(): array {
		return array(
			'click'     => __( 'Al hacer clic (o con Intro o Espacio)', 'pro-elements' ),
			'hover'     => __( 'Al pasar el puntero o enfocar', 'pro-elements' ),
			'scroll_in' => __( 'Al aparecer en pantalla (una vez)', 'pro-elements' ),
			'load'      => __( 'Al cargar la página', 'pro-elements' ),
		);
	}

	/** Acciones en el editor. */
	public static function action_labels(): array {
		return array(
			'show'             => __( 'Mostrar', 'pro-elements' ),
			'hide'             => __( 'Ocultar', 'pro-elements' ),
			'toggle'           => __( 'Mostrar u ocultar', 'pro-elements' ),
			'toggle_class'     => __( 'Poner o quitar una clase', 'pro-elements' ),
			'set_attribute'    => __( 'Cambiar un atributo de estado', 'pro-elements' ),
			'scroll_to'        => __( 'Ir al elemento', 'pro-elements' ),
			'popup_open'       => __( 'Abrir un popup', 'pro-elements' ),
			'popup_close'      => __( 'Cerrar el popup que lo contiene', 'pro-elements' ),
			'offcanvas_toggle' => __( 'Abrir o cerrar un panel lateral', 'pro-elements' ),
			'load_more'        => __( 'Cargar más en un Loop Grid', 'pro-elements' ),
		);
	}

	/** Aviso de la vuelta atrás (V.11, C1-15) junto a «Oculto al cargar» y a las acciones. */
	public static function revert_note(): string {
		return __( 'Con una versión de DIGITALÍSIMO Elements anterior a 4.3.0.169, lo que se oculta así vuelve a verse siempre.', 'pro-elements' );
	}

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		foreach ( self::STACKS as $stack ) {
			add_action( "elementor/element/{$stack}/" . self::AFTER . '/after_section_end', array( __CLASS__, 'register' ), 10, 2 );
		}
	}

	/**
	 * Sección y controles. Una sola vez por pila aunque el gancho se repita (`common` y `common-optimized`).
	 *
	 * @param object $element Pila de controles.
	 * @param array  $args    Argumentos de la sección.
	 */
	public static function register( $element, $args = array() ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_controls' ) || null !== $element->get_controls( self::ON ) ) {
			return;
		}
		$c = '\Elementor\Controls_Manager';
		$element->start_controls_section(
			self::SECTION,
			array(
				'label' => __( 'Acciones', 'pro-elements' ),
				'tab'   => $c::TAB_ADVANCED,
			)
		);
		$element->add_control(
			self::HIDDEN,
			array(
				'label'          => __( 'Oculto al cargar: lo muestra una acción', 'pro-elements' ),
				'type'           => $c::SWITCHER,
				'return_value'   => 'yes',
				'render_type'    => 'none',
				'style_transfer' => false,
				'description'    => __( 'Para un destino de «Mostrar» o «Mostrar u ocultar» de otro elemento. Se aplica fuera del editor; sin JavaScript el elemento se ve. ', 'pro-elements' ) . self::revert_note(),
			)
		);
		$element->add_control(
			self::ON,
			array(
				'label'          => __( 'Acciones al interactuar', 'pro-elements' ),
				'type'           => $c::SWITCHER,
				'return_value'   => 'yes',
				'render_type'    => 'none',
				'style_transfer' => false,
				'separator'      => 'before',
				'assets'         => array(
					'scripts' => array(
						array(
							'name'       => Module::HANDLE,
							'conditions' => array(
								'terms' => array(
									array(
										'name'     => self::ON,
										'operator' => '===',
										'value'    => 'yes',
									),
								),
							),
						),
					),
				),
			)
		);
		$element->add_control(
			self::LIST,
			array(
				'label'          => __( 'Acciones', 'pro-elements' ),
				'type'           => $c::REPEATER,
				'fields'         => self::fields(),
				'title_field'    => self::title_field(),
				'prevent_empty'  => false,
				'render_type'    => 'none',
				'style_transfer' => false,
				'condition'      => array( self::ON => 'yes' ),
			)
		);
		$element->add_control(
			self::NOTE,
			array(
				'type'            => $c::RAW_HTML,
				'raw'             => __( 'Con «Al hacer clic», el disparador es el único enlace o botón del elemento; si no tiene ninguno, el elemento se convierte en un botón que también responde a Intro y Espacio. Si tiene varios enlaces, botones o campos, esa opción no aparece. «Al aparecer en pantalla» y «Al cargar la página» sólo admiten acciones que no mueven el contenido.', 'pro-elements' ) . ' ' . self::revert_note(),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( self::ON => 'yes' ),
			)
		);
		$element->end_controls_section();
	}

	/** Campos del repetidor (definiciones como las devuelve `Repeater::get_controls()`). */
	public static function fields(): array {
		if ( class_exists( '\Elementor\Repeater' ) ) {
			$repeater = new \Elementor\Repeater();
			foreach ( self::field_definitions() as $name => $args ) {
				$repeater->add_control( $name, $args );
			}
			return $repeater->get_controls();
		}
		$fields = array();
		foreach ( self::field_definitions() as $name => $args ) {
			$fields[ $name ] = array_merge( array( 'name' => $name ), $args );
		}
		return $fields;
	}

	/** Definición de cada campo, en orden. */
	public static function field_definitions(): array {
		$c         = '\Elementor\Controls_Manager';
		$targeted  = Rules::TARGETED;
		$animation = defined( 'Elementor\Controls_Manager::ANIMATION' ) ? $c::ANIMATION : $c::SELECT;
		$fields    = array(
			'trigger'         => array(
				'label'          => __( 'Cuándo', 'pro-elements' ),
				'type'           => $c::SELECT,
				'default'        => 'click',
				'options'        => self::trigger_labels(),
				'style_transfer' => false,
			),
			'action'          => array(
				'label'          => __( 'Qué hacer', 'pro-elements' ),
				'type'           => $c::SELECT,
				'default'        => 'show',
				'options'        => self::action_labels(),
				'style_transfer' => false,
			),
			'target_kind'     => array(
				'label'          => __( 'Sobre qué', 'pro-elements' ),
				'type'           => $c::SELECT,
				'default'        => 'element',
				'options'        => array(
					'element' => __( 'Otro elemento de este documento', 'pro-elements' ),
					'self'    => __( 'Este elemento', 'pro-elements' ),
				),
				'condition'      => array( 'action' => $targeted ),
				'style_transfer' => false,
			),
			'target'          => array(
				'label'          => __( 'Elemento', 'pro-elements' ),
				'type'           => $c::SELECT,
				'options'        => array( '' => __( 'Elige un elemento', 'pro-elements' ) ),
				'label_block'    => true,
				'condition'      => array(
					'action'      => $targeted,
					'target_kind' => 'element',
				),
				'style_transfer' => false,
			),
			'popup_id'        => self::popup_control(),
			'class_name'      => array(
				'label'          => __( 'Clase', 'pro-elements' ),
				'type'           => $c::TEXT,
				'placeholder'    => __( 'abierto', 'pro-elements' ),
				'description'    => __( 'Minúsculas, números, guiones y guiones bajos (hasta 40). No se admiten las clases con los prefijos reservados del constructor, de los elementos propios ni de WordPress (e-, digi-, wp-…).', 'pro-elements' ),
				'ai'             => array( 'active' => false ),
				'condition'      => array( 'action' => 'toggle_class' ),
				'style_transfer' => false,
			),
			'attribute'       => array(
				'label'          => __( 'Atributo', 'pro-elements' ),
				'type'           => $c::SELECT,
				'default'        => 'aria-pressed',
				'options'        => array(
					'aria-pressed'    => __( 'aria-pressed (botón presionado)', 'pro-elements' ),
					'aria-current'    => __( 'aria-current (elemento actual)', 'pro-elements' ),
					'aria-label'      => __( 'aria-label (nombre accesible)', 'pro-elements' ),
					'data-digi-state' => __( 'data-digi-state (estado para tu CSS)', 'pro-elements' ),
				),
				'condition'      => array( 'action' => 'set_attribute' ),
				'style_transfer' => false,
			),
			'attribute_value' => array(
				'label'          => __( 'Valor', 'pro-elements' ),
				'type'           => $c::TEXT,
				'description'    => __( 'aria-pressed: true, false o mixed. aria-current: true, false, page, step, location, date o time. aria-label: texto de hasta 150 caracteres. data-digi-state: minúsculas, números y guiones.', 'pro-elements' ),
				'ai'             => array( 'active' => false ),
				'condition'      => array( 'action' => 'set_attribute' ),
				'style_transfer' => false,
			),
			'delay'           => array(
				'label'          => __( 'Retraso (ms)', 'pro-elements' ),
				'type'           => $c::NUMBER,
				'min'            => 0,
				'max'            => Rules::MAX_DELAY,
				'step'           => 100,
				'condition'      => array( 'trigger' => 'load' ),
				'style_transfer' => false,
			),
			'animation'       => array(
				'label'          => __( 'Animación del destino', 'pro-elements' ),
				'type'           => $animation,
				'description'    => __( 'Con movimiento reducido no se anima.', 'pro-elements' ),
				'condition'      => array( 'action' => Rules::ANIMATED ),
				'style_transfer' => false,
			),
			'once'            => array(
				'label'          => __( 'Sólo la primera vez', 'pro-elements' ),
				'type'           => $c::SWITCHER,
				'return_value'   => 'yes',
				'condition'      => array( 'trigger' => array( 'click', 'hover' ) ),
				'style_transfer' => false,
			),
		);
		if ( $c::SELECT === $animation ) {
			$fields['animation']['options'] = array( '' => __( 'Ninguna', 'pro-elements' ) );
		}
		return $fields;
	}

	/** Popup: el control de consulta de PRO (el mismo del enlace dinámico «Popup»); sin él, un número. */
	private static function popup_control(): array {
		$c    = '\Elementor\Controls_Manager';
		$base = array(
			'label'          => __( 'Popup', 'pro-elements' ),
			'label_block'    => true,
			'condition'      => array( 'action' => 'popup_open' ),
			'style_transfer' => false,
		);
		$query = 'ElementorPro\Modules\QueryControl\Module';
		if ( class_exists( $query ) && defined( $query . '::QUERY_CONTROL_ID' ) && defined( $query . '::QUERY_OBJECT_LIBRARY_TEMPLATE' ) ) {
			return array_merge(
				$base,
				array(
					'type'         => constant( $query . '::QUERY_CONTROL_ID' ),
					'autocomplete' => array(
						'object' => constant( $query . '::QUERY_OBJECT_LIBRARY_TEMPLATE' ),
						'query'  => array(
							'posts_per_page' => 20,
							'post_status'    => array( 'publish' ),
							'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- búsqueda del editor, como la del enlace dinámico de PRO.
								array(
									'key'   => '_elementor_template_type',
									'value' => 'popup',
								),
							),
						),
					),
				)
			);
		}
		return array_merge(
			$base,
			array(
				'type' => $c::NUMBER,
				'min'  => 1,
			)
		);
	}

	/** Título de cada fila en el editor: «Disparador · acción», con los textos de las listas. */
	public static function title_field(): string {
		$short = array(
			'click'     => 'Clic',
			'hover'     => 'Puntero o foco',
			'scroll_in' => 'Al aparecer',
			'load'      => 'Al cargar',
		);
		return '<# var t=' . wp_json_encode( $short ) . ',a=' . wp_json_encode( self::action_labels() ) . '; #>{{{ t[trigger] || t.click }}} · {{{ a[action] || a.show }}}';
	}
}
