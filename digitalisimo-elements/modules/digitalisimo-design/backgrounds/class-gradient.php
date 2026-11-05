<?php
namespace Digitalisimo\Elements\Design\Backgrounds;

use Digitalisimo\Elements\Design\Css;
use Digitalisimo\Elements\Design\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Degradados de N paradas (F7a, frente F; §3.2 y V.3 de la especificación).
 *
 * Sobre el grupo de fondo del núcleo, sin tocarlo: las paradas A y B, el tipo, el ángulo y la posición siguen siendo
 * los del núcleo. Se añaden, por prefijo de grupo:
 * - `digi_stops`: hasta 8 paradas intermedias (color con globales y posición responsiva en %);
 * - `digi_kind` = `conic`, con sus propios `digi_conic_from` (ángulo, responsivo) y `digi_conic_at` (9 posiciones),
 *   porque el núcleo nunca muestra a la vez el ángulo y la posición (V-I12);
 * - `digi_repeat`: `repeating-*-gradient`.
 *
 * Sin ninguno de ellos no se emite nada y el CSS es byte a byte el del núcleo. Con alguno, `parse_css()` añade
 * `background-image` en **los mismos selectores y consultas** que el núcleo (`Css::core_selectors()` sobre
 * `gradient_angle`), con `add_rules()` en forma de array: el `Stylesheet` fusiona por selector y la propiedad del
 * núcleo queda sustituida, sin duplicarse. `build()` y `rules()` son puras; `design-editor.js` las repite para la
 * vista previa y las dos comparten `tests/sources/design/gradients.json`.
 *
 * En widgets sólo se engancha la pila `common`: el núcleo repite esos ganchos para `common-optimized` (V-I1).
 */
final class Gradient {
	/** Pila → sección → prefijos del grupo de fondo (D10). */
	const PREFIXES = array(
		'section'   => array(
			'section_background'         => array( 'background_', 'background_hover_' ),
			'section_background_overlay' => array( 'background_overlay_', 'background_overlay_hover_' ),
		),
		'column'    => array(
			'section_style'              => array( 'background_', 'background_hover_' ),
			'section_background_overlay' => array( 'background_overlay_', 'background_overlay_hover_' ),
		),
		'container' => array(
			'section_background'         => array( 'background_', 'background_hover_' ),
			'section_background_overlay' => array( 'background_overlay_', 'background_overlay_hover_' ),
		),
		'common'    => array(
			'_section_background' => array( '_background_', '_background_hover_' ),
		),
	);

	/** Tope de paradas intermedias. */
	const MAX_STOPS = 8;

	/** Las 9 posiciones del núcleo (`gradient_position`), también para `digi_conic_at`. */
	const POSITIONS = array( 'center center', 'center left', 'center right', 'top center', 'top left', 'top right', 'bottom center', 'bottom left', 'bottom right' );

	/** Etiquetas de las posiciones (las usa también la máscara). */
	const POSITION_LABELS = array(
		'center center' => 'Centro',
		'center left'   => 'Izquierda',
		'center right'  => 'Derecha',
		'top center'    => 'Arriba',
		'top left'      => 'Arriba a la izquierda',
		'top right'     => 'Arriba a la derecha',
		'bottom center' => 'Abajo',
		'bottom left'   => 'Abajo a la izquierda',
		'bottom right'  => 'Abajo a la derecha',
	);

	/** Valores por defecto del grupo del núcleo (`background.php`), para ajustes que no los traen. */
	const CORE_DEFAULTS = array(
		'color'             => '',
		'color_stop'        => array(
			'unit' => '%',
			'size' => 0,
		),
		'color_b'           => '#f2295b',
		'color_b_stop'      => array(
			'unit' => '%',
			'size' => 100,
		),
		'gradient_type'     => 'linear',
		'gradient_angle'    => array(
			'unit' => 'deg',
			'size' => 180,
		),
		'gradient_position' => 'center center',
	);

	/** Claves responsivas que, con valor en un dispositivo, piden una regla en ese dispositivo. */
	const RESPONSIVE = array( 'color_stop', 'color_b_stop', 'gradient_angle', 'gradient_position', 'digi_conic_from' );

	/** Manejador de `design-editor.js` (F crea el archivo; G añade la sección `svg`). */
	const HANDLE = 'digitalisimo-design-editor';

	/** Ruta del script del editor, relativa al plugin. */
	const SCRIPT = 'modules/digitalisimo-design/backgrounds/assets/design-editor.js';

	/** @var bool */
	private static $started = false;

	/**
	 * Símbolos del núcleo que usan los degradados (V.9).
	 *
	 * Sin `Repeater`: el núcleo no la autocarga, la declara al crear su gestor de elementos en `init`, después de esta
	 * guarda (`plugins_loaded`), así que aquí siempre faltaría. `controls()` la comprueba al registrar los controles,
	 * cuando ya existe.
	 */
	const SYMBOLS = array(
		'Elementor\\Controls_Manager',
		'Elementor\\Stylesheet',
		'Elementor\\Core\\Files\\CSS\\Post',
		'Elementor\\Controls_Stack::start_injection',
		'Elementor\\Controls_Stack::get_current_section',
	);

	/** Registra controles, CSS y script del editor si el núcleo tiene lo que usa (V.9). */
	public static function init(): void {
		self::start( self::SYMBOLS );
	}

	/**
	 * Comprueba la guarda con `$symbols` y, si pasa, registra los ganchos. `init()` la llama con `SYMBOLS`; las
	 * pruebas, con un símbolo que falta para comprobar que entonces no se registra nada.
	 *
	 * @param string[] $symbols Símbolos del núcleo.
	 */
	public static function start( array $symbols ): bool {
		if ( self::$started ) {
			return true;
		}
		if ( ! Module::requires( 'degradados', $symbols ) ) {
			return false;
		}
		self::$started = true;
		foreach ( self::PREFIXES as $stack => $sections ) {
			foreach ( array_keys( $sections ) as $section ) {
				add_action( "elementor/element/{$stack}/{$section}/before_section_end", array( __CLASS__, 'controls' ), 10, 2 );
			}
		}
		add_action( 'elementor/element/parse_css', array( __CLASS__, 'parse_css' ), 10, 2 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_script' ) );
		return true;
	}

	// ── Controles ──────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Inyecta los controles detrás de `P.gradient_position` de cada grupo de la sección en curso.
	 *
	 * @param \Elementor\Controls_Stack $element Pila (sección, columna, contenedor o `common`/`common-optimized`).
	 */
	public static function controls( $element ): void {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_current_section' ) || ! class_exists( '\\Elementor\\Repeater' ) ) {
			return;
		}
		$stack = self::stack_of( $element );
		$current = $element->get_current_section();
		$section = is_array( $current ) && isset( $current['section'] ) ? (string) $current['section'] : '';
		if ( '' === $stack || ! isset( self::PREFIXES[ $stack ][ $section ] ) ) {
			return;
		}
		foreach ( self::PREFIXES[ $stack ][ $section ] as $prefix ) {
			if ( null === $element->get_controls( $prefix . 'gradient_position' ) || null !== $element->get_controls( $prefix . 'digi_kind' ) ) {
				continue;
			}
			self::add_controls( $element, $prefix );
		}
	}

	/** Pila de PREFIXES que corresponde a un elemento (`common-optimized` es `common`), o ''. */
	private static function stack_of( $element ): string {
		$name = method_exists( $element, 'get_name' ) ? (string) $element->get_name() : '';
		if ( 'common-optimized' === $name ) {
			return 'common';
		}
		if ( isset( self::PREFIXES[ $name ] ) ) {
			return $name;
		}
		return method_exists( $element, 'get_type' ) && 'widget' === $element->get_type() ? 'common' : '';
	}

	/** Los cinco controles de un grupo, inyectados tras su `gradient_position`. */
	private static function add_controls( $element, string $prefix ): void {
		$c        = '\\Elementor\\Controls_Manager';
		$gradient = array( $prefix . 'background' => array( 'gradient' ) );

		$element->start_injection( array( 'of' => $prefix . 'gradient_position' ) );

		$element->add_control(
			$prefix . 'digi_kind',
			array(
				'label'       => 'Forma avanzada',
				'type'        => $c::SELECT,
				'options'     => array(
					''      => 'Ninguna',
					'conic' => 'Cónica',
				),
				'default'     => '',
				'render_type' => 'ui',
				'separator'   => 'before',
				'description' => 'Con la forma cónica no se usan el ángulo ni la posición de arriba.',
				'condition'   => $gradient,
			)
		);
		$element->add_responsive_control(
			$prefix . 'digi_conic_from',
			array(
				'label'       => 'Ángulo inicial',
				'type'        => $c::SLIDER,
				'size_units'  => array( 'deg' ),
				'range'       => array(
					'deg' => array(
						'min'  => 0,
						'max'  => 360,
						'step' => 1,
					),
				),
				'default'     => array(
					'unit' => 'deg',
					'size' => 0,
				),
				'render_type' => 'ui',
				'condition'   => $gradient + array( $prefix . 'digi_kind' => 'conic' ),
			)
		);
		$element->add_control(
			$prefix . 'digi_conic_at',
			array(
				'label'       => 'Centro',
				'type'        => $c::SELECT,
				'options'     => self::POSITION_LABELS,
				'default'     => 'center center',
				'render_type' => 'ui',
				'condition'   => $gradient + array( $prefix . 'digi_kind' => 'conic' ),
			)
		);
		$element->add_control(
			$prefix . 'digi_repeat',
			array(
				'label'        => 'Repetir',
				'type'         => $c::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'render_type'  => 'ui',
				'condition'    => $gradient,
			)
		);

		$repeater = new \Elementor\Repeater();
		$repeater->add_control(
			'color',
			array(
				'label'       => 'Color',
				'type'        => $c::COLOR,
				'default'     => '#ffffff',
				'render_type' => 'ui',
			)
		);
		$repeater->add_responsive_control(
			'position',
			array(
				'label'       => 'Posición',
				'type'        => $c::SLIDER,
				'size_units'  => array( '%' ),
				'range'       => array(
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'     => array(
					'unit' => '%',
					'size' => 50,
				),
				'render_type' => 'ui',
			)
		);
		$element->add_control(
			$prefix . 'digi_stops',
			array(
				'label'         => 'Paradas intermedias (hasta 8)',
				'type'          => $c::REPEATER,
				'fields'        => $repeater->get_controls(),
				'default'       => array(),
				'prevent_empty' => false,
				'title_field'   => '{{{ position.size }}}%',
				'render_type'   => 'ui',
				'condition'     => $gradient,
			)
		);

		$element->end_injection();
	}

	// ── CSS ────────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * `elementor/element/parse_css`: corre detrás de las reglas del núcleo para el elemento (`post.php`), así que la
	 * regla del mismo selector y la misma consulta sustituye a la suya.
	 *
	 * @param \Elementor\Core\Files\CSS\Post $post_css Archivo CSS del documento.
	 * @param \Elementor\Element_Base         $element  Elemento.
	 */
	public static function parse_css( $post_css, $element ): void {
		if ( ! self::is_post_css( $post_css ) || ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) ) {
			return;
		}
		$stack = self::element_stack( $element );
		if ( '' === $stack ) {
			return;
		}
		$settings = $element->get_settings();
		if ( ! is_array( $settings ) ) {
			return;
		}
		$devices = self::devices();
		$wrapper = array(
			'{{WRAPPER}}' => $post_css->get_element_unique_selector( $element ),
			'{{ID}}'      => (string) $element->get_id(),
		);
		foreach ( self::PREFIXES[ $stack ] as $prefixes ) {
			foreach ( $prefixes as $prefix ) {
				if ( null === $element->get_controls( $prefix . 'digi_kind' ) ) {
					continue;
				}
				$values = self::rules( $settings, $prefix, $devices );
				if ( ! $values ) {
					continue;
				}
				$selectors = Css::core_selectors( $element, $prefix . 'gradient_angle', $wrapper );
				if ( ! $selectors ) {
					continue;
				}
				$sheet = $post_css->get_stylesheet();
				foreach ( $values as $device => $value ) {
					$query = self::query( $device );
					foreach ( $selectors as $selector ) {
						$sheet->add_rules(
							$selector,
							array(
								'background-color' => 'transparent',
								'background-image' => $value,
							),
							$query
						);
					}
				}
			}
		}
	}

	/** Archivo CSS de un documento, sin el dinámico (que sólo repite controles con etiquetas dinámicas). */
	private static function is_post_css( $post_css ): bool {
		return $post_css instanceof \Elementor\Core\Files\CSS\Post
			&& ! $post_css instanceof \Elementor\Core\DynamicTags\Dynamic_CSS;
	}

	/** Pila de PREFIXES de un elemento ya construido (`widget` → `common`), o ''. */
	private static function element_stack( $element ): string {
		$type = method_exists( $element, 'get_type' ) ? (string) $element->get_type() : '';
		if ( 'widget' === $type ) {
			return 'common';
		}
		return isset( self::PREFIXES[ $type ] ) && 'common' !== $type ? $type : '';
	}

	/**
	 * Consulta de `Stylesheet::add_rules()` de un dispositivo: la misma que usa el núcleo para sus controles
	 * responsivos (`[ 'max' => 'tablet' ]`, `[ 'min' => 'widescreen' ]`); `null` en escritorio.
	 */
	private static function query( string $device ): ?array {
		if ( 'desktop' === $device || '' === $device ) {
			return null;
		}
		return array( self::direction( $device ) => $device );
	}

	/** `max` o `min` del punto de corte activo (sólo `widescreen` es `min` en el núcleo). */
	private static function direction( string $device ): string {
		$breakpoints = self::breakpoints();
		if ( isset( $breakpoints[ $device ] ) && is_object( $breakpoints[ $device ] ) && method_exists( $breakpoints[ $device ], 'get_direction' ) ) {
			return 'min' === $breakpoints[ $device ]->get_direction() ? 'min' : 'max';
		}
		return 'widescreen' === $device ? 'min' : 'max';
	}

	/** Puntos de corte activos del núcleo, o [] sin él. */
	private static function breakpoints(): array {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || empty( \Elementor\Plugin::$instance ) || empty( \Elementor\Plugin::$instance->breakpoints ) ) {
			return array();
		}
		$active = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();
		return is_array( $active ) ? $active : array();
	}

	/**
	 * Dispositivos activos de mayor a menor, como `get_active_devices_list( reverse, desktop_first )`:
	 * `desktop`, `widescreen`, `laptop`, `tablet_extra`, `tablet`, `mobile_extra`, `mobile`.
	 *
	 * @return string[]
	 */
	public static function devices(): array {
		$active = array_keys( self::breakpoints() );
		if ( ! $active ) {
			return array( 'desktop', 'tablet', 'mobile' );
		}
		$active[] = 'desktop';
		return array_values( array_map( 'strval', array_reverse( $active ) ) );
	}

	// ── Construcción pura (la repite design-editor.js) ─────────────────────────────────────────────────────────

	/**
	 * `[ dispositivo => valor de background-image ]` que hay que emitir para un grupo: el escritorio siempre que haya
	 * algo que añadir al núcleo, y cada otro dispositivo sólo si guarda alguna clave responsiva propia (las del núcleo,
	 * `digi_conic_from` o la posición de una parada). Así toda regla responsiva del núcleo queda cubierta por una
	 * propia en la misma consulta.
	 *
	 * @param array      $settings Ajustes del elemento (con `__globals__`).
	 * @param string     $prefix   Prefijo del grupo (`background_`, `_background_hover_`…).
	 * @param array|null $devices  Dispositivos de mayor a menor (`devices()`).
	 * @return array<string,string>
	 */
	public static function rules( array $settings, string $prefix, ?array $devices = null ): array {
		$devices = self::device_list( $devices );
		$out     = array();
		$desktop = self::build( $settings, $prefix, 'desktop', $devices );
		if ( null === $desktop ) {
			return $out;
		}
		$out['desktop'] = $desktop;
		foreach ( $devices as $device ) {
			if ( 'desktop' === $device || ! self::has_device_value( $settings, $prefix, $device ) ) {
				continue;
			}
			$value = self::build( $settings, $prefix, $device, $devices );
			if ( null !== $value ) {
				$out[ $device ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Valor de `background-image` de un grupo en un dispositivo, o `null` si no hay nada que añadir al núcleo (sin
	 * paradas válidas, sin forma cónica y sin repetir), si el fondo no es un degradado o si A o B no son colores
	 * válidos.
	 *
	 * - Paradas: A (`color` y `color_stop`), las intermedias válidas ordenadas por posición (estable) y B (`color_b` y
	 *   `color_b_stop`). Un color vacío en A o B es `transparent`; una parada intermedia sin color o sin posición
	 *   válidos se descarta. Como mucho 8.
	 * - Cabecera: el ángulo del núcleo (lineal), `at <posición>` (radial) o `from <ángulo> at <posición>` propios
	 *   (cónico). Lo responsivo se resuelve hacia el dispositivo mayor, como el núcleo.
	 *
	 * @param array      $settings Ajustes del elemento.
	 * @param string     $prefix   Prefijo del grupo.
	 * @param string     $device   Dispositivo ('' o `desktop` = escritorio).
	 * @param array|null $devices  Dispositivos de mayor a menor.
	 */
	public static function build( array $settings, string $prefix, string $device = '', ?array $devices = null ): ?string {
		$devices = self::device_list( $devices );
		$device  = '' === $device ? 'desktop' : $device;
		if ( 'gradient' !== self::get( $settings, $prefix . 'background', '' ) ) {
			return null;
		}
		$conic  = 'conic' === self::get( $settings, $prefix . 'digi_kind', '' );
		$repeat = 'yes' === self::get( $settings, $prefix . 'digi_repeat', '' );
		$middle = self::stops( $settings, $prefix, $device, $devices );
		if ( ! $middle && ! $conic && ! $repeat ) {
			return null;
		}
		$first = self::edge( $settings, $prefix, 'color', 'color_stop', $device, $devices );
		$last  = self::edge( $settings, $prefix, 'color_b', 'color_b_stop', $device, $devices );
		if ( null === $first || null === $last ) {
			return null;
		}
		if ( $conic ) {
			$from = Css::length( self::at( $settings, $prefix . 'digi_conic_from', $device, $devices, array( 'unit' => 'deg', 'size' => 0 ) ), array( 'deg' ) );
			$at   = self::position( self::get( $settings, $prefix . 'digi_conic_at', 'center center' ) );
			$head = 'from ' . ( null === $from ? '0deg' : $from ) . ' at ' . $at;
			$kind = 'conic';
		} elseif ( 'radial' === self::get( $settings, $prefix . 'gradient_type', 'linear' ) ) {
			$head = 'at ' . self::position( self::at( $settings, $prefix . 'gradient_position', $device, $devices, 'center center' ) );
			$kind = 'radial';
		} else {
			$angle = Css::length( self::at( $settings, $prefix . 'gradient_angle', $device, $devices, self::CORE_DEFAULTS['gradient_angle'] ), array( 'deg', 'grad', 'rad', 'turn', 'custom' ) );
			$head  = null === $angle ? '180deg' : $angle;
			$kind  = 'linear';
		}
		$stops = array_merge( array( $first ), $middle, array( $last ) );
		return ( $repeat ? 'repeating-' : '' ) . $kind . '-gradient(' . $head . ', ' . implode( ', ', $stops ) . ')';
	}

	/** Parada A o B: color (global, local o `transparent` si está vacío) y posición opcional; `null` si no es válida. */
	private static function edge( array $settings, string $prefix, string $color_key, string $stop_key, string $device, array $devices ): ?string {
		$color = Css::global_color( $settings, $prefix . $color_key );
		if ( null === $color ) {
			$raw = self::get( $settings, $prefix . $color_key, self::CORE_DEFAULTS[ $color_key ] );
			if ( null === $raw || ( is_string( $raw ) && '' === trim( $raw ) ) ) {
				$color = 'transparent';
			} else {
				$color = Css::color( $raw );
			}
		}
		if ( null === $color ) {
			return null;
		}
		$stop = Css::length( self::at( $settings, $prefix . $stop_key, $device, $devices, self::CORE_DEFAULTS[ $stop_key ] ), array( '%', 'custom' ) );
		return null === $stop ? $color : $color . ' ' . $stop;
	}

	/** Paradas intermedias válidas en un dispositivo, ordenadas por posición (estable entre iguales). */
	private static function stops( array $settings, string $prefix, string $device, array $devices ): array {
		$rows = self::get( $settings, $prefix . 'digi_stops', array() );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$valid = array();
		$index = 0;
		foreach ( array_slice( array_values( $rows ), 0, self::MAX_STOPS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$color = Css::global_color( $row, 'color' );
			if ( null === $color ) {
				$color = isset( $row['color'] ) ? Css::color( $row['color'] ) : null;
			}
			$raw      = self::at( $row, 'position', $device, $devices, null );
			$position = Css::length( $raw, array( '%' ) );
			if ( null === $color || null === $position ) {
				continue;
			}
			$valid[] = array( (float) $raw['size'], $index++, $color . ' ' . $position );
		}
		usort(
			$valid,
			function ( $a, $b ) {
				if ( $a[0] === $b[0] ) {
					return $a[1] - $b[1];
				}
				return $a[0] < $b[0] ? -1 : 1;
			}
		);
		return array_map(
			function ( $item ) {
				return $item[2];
			},
			$valid
		);
	}

	/** Una posición del núcleo o `center center`. */
	private static function position( $value ): string {
		return is_string( $value ) && in_array( $value, self::POSITIONS, true ) ? $value : 'center center';
	}

	/** Algún valor responsivo propio del grupo en ese dispositivo. */
	private static function has_device_value( array $settings, string $prefix, string $device ): bool {
		foreach ( self::RESPONSIVE as $key ) {
			if ( isset( $settings[ $prefix . $key . '_' . $device ] ) && self::is_set( $settings[ $prefix . $key . '_' . $device ] ) ) {
				return true;
			}
		}
		$rows = self::get( $settings, $prefix . 'digi_stops', array() );
		if ( is_array( $rows ) ) {
			foreach ( array_slice( array_values( $rows ), 0, self::MAX_STOPS ) as $row ) {
				if ( is_array( $row ) && isset( $row[ 'position_' . $device ] ) && self::is_set( $row[ 'position_' . $device ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Valor de una clave responsiva en un dispositivo, heredado del mayor como el núcleo (`laptop` y `widescreen`
	 * heredan de escritorio; el resto, del anterior en la lista).
	 *
	 * @param array  $settings Ajustes (o una fila de paradas).
	 * @param string $key      Clave de escritorio.
	 * @param string $device   Dispositivo.
	 * @param array  $devices  Dispositivos de mayor a menor.
	 * @param mixed  $fallback Valor si ninguno de la cadena lo tiene.
	 * @return mixed
	 */
	private static function at( array $settings, string $key, string $device, array $devices, $fallback ) {
		foreach ( self::chain( $device, $devices ) as $step ) {
			$name = 'desktop' === $step ? $key : $key . '_' . $step;
			if ( isset( $settings[ $name ] ) && self::is_set( $settings[ $name ] ) ) {
				return $settings[ $name ];
			}
		}
		return $fallback;
	}

	/** Cadena de herencia de un dispositivo hasta escritorio. */
	private static function chain( string $device, array $devices ): array {
		$index = array_search( $device, $devices, true );
		if ( 'desktop' === $device || false === $index ) {
			return array( 'desktop' );
		}
		$chain = array( $device );
		while ( $index > 0 ) {
			$parent = $devices[ $index - 1 ];
			if ( 'widescreen' === $parent || 'desktop' === $parent ) {
				break;
			}
			$chain[] = $parent;
			--$index;
		}
		$chain[] = 'desktop';
		return $chain;
	}

	/** Un valor guardado cuenta: cadena no vacía o SLIDER con `size`. */
	private static function is_set( $value ): bool {
		if ( is_array( $value ) ) {
			return isset( $value['size'] ) && '' !== $value['size'] && null !== $value['size'];
		}
		return null !== $value && '' !== $value;
	}

	/** Ajuste con su valor por defecto. */
	private static function get( array $settings, string $key, $fallback ) {
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/** Lista de dispositivos saneada; escritorio siempre primero. */
	private static function device_list( ?array $devices ): array {
		$devices = null === $devices ? self::devices() : array_values( array_map( 'strval', $devices ) );
		if ( ! $devices || 'desktop' !== $devices[0] ) {
			$devices = array_merge( array( 'desktop' ), array_values( array_diff( $devices, array( 'desktop' ) ) ) );
		}
		return $devices;
	}

	// ── Editor ─────────────────────────────────────────────────────────────────────────────────────────────────

	/** `design-editor.js` en el editor (nunca en el front), con los prefijos por tipo de elemento. */
	public static function editor_script(): void {
		if ( ! function_exists( 'wp_enqueue_script' ) || ! function_exists( 'plugins_url' ) || ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			return;
		}
		$version = defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false;
		wp_enqueue_script( self::HANDLE, plugins_url( self::SCRIPT, DIGITALISIMO_ELEMENTS_FILE ), array(), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.digitalisimoDesignConfig=' . wp_json_encode( self::editor_config() ) . ';', 'before' );
	}

	/**
	 * Configuración del script: las tablas que repite vienen de aquí y de `Css`, su única fuente. La fija
	 * `tests/sources/design/gradients.json` (`config`), que usa la prueba de Node.
	 */
	public static function editor_config(): array {
		$prefixes = array();
		foreach ( self::PREFIXES as $stack => $sections ) {
			$prefixes[ 'common' === $stack ? 'widget' : $stack ] = array_values( array_merge( ...array_values( $sections ) ) );
		}
		return array(
			'prefixes'   => $prefixes,
			'max'        => self::MAX_STOPS,
			'positions'  => self::POSITIONS,
			'defaults'   => self::CORE_DEFAULTS,
			'responsive' => self::RESPONSIVE,
			'deny'       => Css::SVG_DENIED,
			'svgMax'     => Css::SVG_MAX,
			'svgRawMax'  => Css::SVG_RAW_MAX,
			'svgError'   => 'El SVG elegido no se puede usar como máscara ni como patrón: debe ser un SVG de este sitio, de hasta 8 KB y sin scripts, imágenes ni enlaces externos.',
		);
	}
}
