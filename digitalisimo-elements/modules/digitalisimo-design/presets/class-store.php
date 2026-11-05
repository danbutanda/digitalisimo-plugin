<?php
namespace Digitalisimo\Elements\Design\Presets;

use Digitalisimo\Elements\Design\Css;
use Digitalisimo\Elements\Lock;

defined( 'ABSPATH' ) || exit;

/**
 * Almacén de los presets de estilo del sitio (F7a, V.2.1 y V.2.2 de la especificación).
 *
 * **Datos.** Opción del sitio `digitalisimo_elements_presets`, **sin carga automática**:
 * `{ v: 1, presets: { slug: { type, label, settings, hash, updated, inert } } }`. No vive en la meta del kit, así que
 * no se lee en cada petición: sólo al generar el CSS de un documento con un elemento vinculado, en el editor y en las
 * acciones. Sin presets, la opción no existe.
 *
 * **Qué es estilo** (`sanitize_settings()`, siempre con `set_use_style_controls( true )`):
 * 1. la cadena del núcleo `Settings_Sanitizer` → `for()` → `using()` → `remove_invalid_settings()` → `kses_deep()`;
 * 2. semilla: los controles con `selectors`, salvo `MEDIA`, `GALLERY`, `REPEATER`, `URL`, `WYSIWYG`, `CODE`,
 *    `custom_css`, `_css_classes`, `css_classes`, `_element_id`, `digitalisimo_preset` y todo control con
 *    `frontend_available` o `prefix_class` (R-M9); `__dynamic__` nunca entra;
 * 3. cierre por dependencias, repetido hasta que no cambia: los controles de `condition`/`conditions` de uno
 *    incluido (SELECT, CHOOSE, VISUAL_CHOICE, SWITCHER o POPOVER_TOGGLE), los nombrados en un `{{otro.X}}` de sus
 *    selectores (por su tipo de la lista blanca) y el POPOVER_TOGGLE de cada grupo con algún campo incluido. El
 *    cierre tampoco añade controles con `prefix_class`, pero sí con `frontend_available` (el tipo de fondo);
 * 4. lista blanca por tipo de control para cada valor y sus variantes responsivas; `*_background` = `video` o
 *    `slideshow` se rechaza porque es contenido. En las pilas optimizadas del front y de WP-CLI el núcleo quita
 *    `options` y `size_units` de cada control: ahí una opción se valida por su forma (`OPTION_TOKEN`) y una unidad
 *    contra `UNITS`; en la administración, el editor y REST, contra las del control;
 * 5. comprobación final de claves y valores (`\`, `//`, `url(`, `image-set(`, `image(`, `cross-fade(`, `@`, llaves,
 *    `<`, `expression(`): una coincidencia descarta el preset entero;
 * 6. `__globals__` sólo de las claves que sobreviven y con la forma `globals/(colors|typography)?id=<id>`.
 *
 * **Inertes.** Un preset de un tipo que este sitio no registra se guarda tal cual (pasando la comprobación final),
 * marcado `inert`: no se aplica ni se edita y viaja en los kits. Vuelve a funcionar cuando el tipo existe.
 *
 * **Escrituras.** Bajo el cerrojo compartido `digitalisimo_elements_lock_presets` (se roba a los 30 s). Crear,
 * actualizar o borrar deja pendiente **un** `files_manager->clear_cache()` por petición, que corre en `shutdown`
 * (las acciones que el editor agrupa en una misma petición vacían una sola vez). Renombrar sólo cambia el nombre
 * visible y no vacía nada.
 */
final class Store {
	const OPTION  = 'digitalisimo_elements_presets';
	const LOCK    = 'digitalisimo_elements_lock_presets';
	const VERSION = 1;

	/** Límites (filtro `digitalisimo_elements_preset_limits`). Bytes medidos en JSON. */
	const LIMITS = array(
		'count'          => 200,
		'settings_bytes' => 16384,
		'total_bytes'    => 131072,
		'label'          => 60,
		'slug'           => 40,
		'uses'           => 200,
	);

	/** Slug (D6): único en el sitio, `[a-z0-9]` en hasta 8 grupos separados por guiones. */
	const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+){0,7}$/';

	/** Tipo guardado: `widgetType` de un widget o `container` (la forma; si existe lo decide `supports_type()`). */
	const TYPE = '/^[A-Za-z0-9_.-]{1,191}$/';

	/** Global admitido en `__globals__`. */
	const GLOBAL_VALUE = '/^globals\/(?:colors|typography)\?id=[a-z0-9]+$/';

	/** Segundos tras los que se roba el cerrojo de los presets. */
	const LOCK_STALE = 30;

	/** Tipos de control que nunca entran en un preset. */
	const EXCLUDED_TYPES = array( 'media', 'gallery', 'repeater', 'url', 'wysiwyg', 'code' );

	/** Controles que nunca entran en un preset. */
	const EXCLUDED_NAMES = array( 'custom_css', '_css_classes', 'css_classes', '_element_id', 'digitalisimo_preset' );

	/** Tipos con opciones: los únicos que el cierre añade por una condición. */
	const CHOICE_TYPES = array( 'select', 'choose', 'visual_choice', 'switcher', 'popover_toggle' );

	/** Lista blanca por tipo de control (R-I11). */
	const VALUE_TYPES = array( 'color', 'slider', 'dimensions', 'number', 'gaps', 'select', 'choose', 'visual_choice', 'switcher', 'popover_toggle', 'font', 'text', 'textarea', 'box_shadow', 'text_shadow' );

	/** Lo que ninguna clave ni ningún valor de un preset puede contener (comprobación final). */
	const DENIED = array( '\\', '//', 'url(', 'image-set(', 'image(', 'cross-fade(', '@', '{', '}', '<', 'expression(' );

	/**
	 * Unidades que acepta un valor con unidad cuando el núcleo quitó `size_units` del control (pilas optimizadas del
	 * front y de WP-CLI, `Controls_Stack::add_control()`).
	 */
	const UNITS = array( 'px', 'em', 'rem', '%', 'vw', 'vh', 'vmin', 'vmax', 'deg', 'grad', 'rad', 'turn', 's', 'ms', 'fr', 'ch', 'ex', 'lh', 'rlh', 'custom' );

	/** Valor de un SELECT, CHOOSE o VISUAL_CHOICE cuando el núcleo quitó sus `options` (pilas optimizadas). */
	const OPTION_TOKEN = '/^[A-Za-z0-9 _.%+,#\/-]{0,100}$/';

	/** Texto libre de un TEXT o TEXTAREA con selectores. */
	const TEXT_VALUE = '/^[A-Za-z0-9 #%.,()+\-_*\/]*$/';

	/** @var array|null Datos leídos en esta petición. */
	private static $data = null;

	/** @var array<string,array> Controles admitidos por tipo y modo de duplicado responsivo. */
	private static $allowed = array();

	/** @var bool Hay un vaciado de la caché CSS pendiente para `shutdown`. */
	private static $pending = false;

	/** @var array[] Presets descartados por el último `replace()`: `[ slug, motivo ]`. */
	private static $dropped = array();

	// ── Lectura ──────────────────────────────────────────────────────────────────────────────────────────────────

	/** `{ v: 1, presets: { … } }` de la opción, normalizado. */
	public static function data(): array {
		if ( null === self::$data ) {
			$raw        = function_exists( 'get_option' ) ? get_option( self::OPTION, array() ) : array();
			self::$data = array(
				'v'       => self::VERSION,
				'presets' => self::normalize_stored( is_array( $raw ) && isset( $raw['presets'] ) && is_array( $raw['presets'] ) ? $raw['presets'] : array() ),
			);
		}
		return self::$data;
	}

	/**
	 * Presets del sitio: `{ slug: { type, label, settings, hash, updated, inert } }`.
	 *
	 * @return array<string,array>
	 */
	public static function all(): array {
		return self::data()['presets'];
	}

	/** Un preset, o `null`. */
	public static function get( string $slug ): ?array {
		$all = self::all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/** `{ slug: label }` de los presets aplicables de un tipo, por nombre. */
	public static function for_type( string $type ): array {
		$out = array();
		foreach ( self::all() as $slug => $preset ) {
			if ( empty( $preset['inert'] ) && $type === $preset['type'] ) {
				$out[ $slug ] = $preset['label'];
			}
		}
		asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	/** `{ slug: label }` de todos los presets aplicables (el editor los filtra por tipo con `types()`). */
	public static function options(): array {
		$out = array();
		foreach ( self::all() as $slug => $preset ) {
			if ( empty( $preset['inert'] ) ) {
				$out[ $slug ] = $preset['label'];
			}
		}
		asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	/** `{ slug: type }` de todos los presets aplicables. */
	public static function types(): array {
		$out = array();
		foreach ( self::all() as $slug => $preset ) {
			if ( empty( $preset['inert'] ) ) {
				$out[ $slug ] = $preset['type'];
			}
		}
		return $out;
	}

	/** Bytes de la opción en JSON (lo que mide el tope total). */
	public static function total(): int {
		return self::bytes( self::data() );
	}

	/** Límites efectivos (filtro `digitalisimo_elements_preset_limits`). */
	public static function limits(): array {
		$limits = self::LIMITS;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'digitalisimo_elements_preset_limits', $limits );
			if ( is_array( $filtered ) ) {
				foreach ( $limits as $key => $value ) {
					if ( isset( $filtered[ $key ] ) && (int) $filtered[ $key ] > 0 ) {
						$limits[ $key ] = (int) $filtered[ $key ];
					}
				}
			}
		}
		return $limits;
	}

	/** Slug válido según D6 y el límite de longitud. */
	public static function valid_slug( string $slug ): bool {
		return strlen( $slug ) <= self::limits()['slug'] && 1 === preg_match( self::SLUG, $slug );
	}

	/**
	 * Slug libre derivado de un nombre (D6): minúsculas sin acentos, guiones, como mucho 7 grupos y 36 caracteres, y
	 * `-2`, `-3`… si ya existe. Sin nada aprovechable, `preset`.
	 */
	public static function unique_slug( string $label ): string {
		$base = function_exists( 'remove_accents' ) ? remove_accents( $label ) : $label;
		if ( function_exists( 'iconv' ) && preg_match( '/[^\x00-\x7F]/', $base ) ) {
			$ascii = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			$base  = false === $ascii ? $base : $ascii;
		}
		$base  = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $base ) ), '-' );
		$parts = array_slice( array_filter( explode( '-', $base ), 'strlen' ), 0, 7 );
		$base  = trim( substr( implode( '-', $parts ), 0, 36 ), '-' );
		$base  = '' === $base ? 'preset' : $base;
		$all   = self::all();
		$slug  = $base;
		for ( $i = 2; isset( $all[ $slug ] ) && $i < 1000; $i++ ) {
			$slug = $base . '-' . $i;
		}
		return $slug;
	}

	/** Huella de unos ajustes: sha1 de su JSON canónico (claves ordenadas, sin escapar barras ni Unicode). */
	public static function hash( array $settings ): string {
		return sha1( self::canonical( $settings ) );
	}

	// ── Tipos ────────────────────────────────────────────────────────────────────────────────────────────────────

	/** El tipo existe en este sitio (cualquier widget o elemento registrado): si no, el preset es inerte. */
	public static function type_registered( string $type ): bool {
		$plugin = self::plugin();
		if ( ! $plugin || 1 !== preg_match( self::TYPE, $type ) ) {
			return false;
		}
		if ( isset( $plugin->elements_manager ) && method_exists( $plugin->elements_manager, 'get_element_types' ) && null !== $plugin->elements_manager->get_element_types( $type ) ) {
			return true;
		}
		return isset( $plugin->widgets_manager ) && null !== $plugin->widgets_manager->get_widget_types( $type );
	}

	/** El tipo admite presets (D1): un widget v3 registrado (ni `common*` ni atómico) o el contenedor. */
	public static function supports_type( string $type ): bool {
		if ( 'container' === $type ) {
			return self::type_registered( $type );
		}
		$plugin = self::plugin();
		if ( ! $plugin || 1 !== preg_match( self::TYPE, $type ) || ! isset( $plugin->widgets_manager ) ) {
			return false;
		}
		$widget = $plugin->widgets_manager->get_widget_types( $type );
		if ( ! $widget instanceof \Elementor\Widget_Base || $widget instanceof \Elementor\Widget_Common_Base ) {
			return false;
		}
		foreach ( array( 'Elementor\\Modules\\AtomicWidgets\\Elements\\Base\\Atomic_Widget_Base', 'Elementor\\Modules\\AtomicWidgets\\Elements\\Base\\Atomic_Element_Base' ) as $atomic ) {
			if ( $widget instanceof $atomic ) {
				return false;
			}
		}
		return true;
	}

	// ── Saneado (V.2.2) ──────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Sólo el estilo de `$settings` para el tipo `$type`, o `[]` si el tipo no admite presets o el preset no supera la
	 * comprobación final.
	 *
	 * @param string     $type     Tipo (`widgetType` o `container`).
	 * @param array      $settings Ajustes recibidos.
	 * @param array|null $dropped  Recibe las claves que no entran (quedan locales); `['*']` si se descarta entero.
	 */
	public static function sanitize_settings( string $type, array $settings, &$dropped = null ): array {
		$dropped = array();
		if ( ! $settings || ! self::supports_type( $type ) ) {
			$dropped = $settings ? array( '*' ) : array();
			return array();
		}
		$result = Css::with_style_controls(
			function () use ( $type, $settings ) {
				return self::sanitize_now( $type, $settings );
			}
		);
		$dropped = $result[1];
		return $result[0];
	}

	/**
	 * Saneado con los controles de estilo visibles.
	 *
	 * @return array [ ajustes, claves descartadas ]
	 */
	private static function sanitize_now( string $type, array $settings ): array {
		$plugin    = self::plugin();
		$sanitizer = 'Elementor\\Modules\\KitElementsDefaults\\Utils\\Settings_Sanitizer';
		$element   = self::sample( $type );
		if ( ! $element || ! class_exists( $sanitizer ) ) {
			return array( array(), array( '*' ) );
		}
		$controls = $element->get_controls();
		$input    = $settings;
		unset( $input['__dynamic__'] );
		$clean = ( new $sanitizer( $plugin->elements_manager, array_keys( (array) $plugin->widgets_manager->get_widget_types() ) ) )
			->for( $type )
			->using( $input )
			->remove_invalid_settings()
			->kses_deep()
			->get();
		$clean = is_array( $clean ) ? $clean : array();

		$allowed = self::allowed( $type, $controls );
		$out     = array();
		$globals = isset( $clean['__globals__'] ) && is_array( $clean['__globals__'] ) ? $clean['__globals__'] : array();
		unset( $clean['__globals__'], $clean['__dynamic__'] );
		foreach ( $clean as $key => $value ) {
			$root = self::root( (string) $key, $controls );
			if ( null === $root || ! isset( $allowed[ $root ] ) ) {
				continue;
			}
			$control = self::control( $controls, (string) $key, $root );
			$valid   = self::valid_value( $control, $value, 'closure' === $allowed[ $root ] );
			if ( null !== $valid ) {
				$out[ $key ] = $valid;
			}
		}
		$kept_globals = array();
		foreach ( $globals as $key => $value ) {
			$root = self::root( (string) $key, $controls );
			if ( null !== $root && isset( $allowed[ $root ] ) && is_string( $value ) && 1 === preg_match( self::GLOBAL_VALUE, $value ) ) {
				$kept_globals[ $key ] = $value;
			}
		}
		if ( $kept_globals ) {
			$out['__globals__'] = $kept_globals;
		}
		if ( ! self::safe( $out ) ) {
			return array( array(), array( '*' ) );
		}
		$dropped = array_values( array_diff( array_map( 'strval', array_keys( $settings ) ), array_map( 'strval', array_keys( $out ) ) ) );
		if ( isset( $settings['__globals__'] ) && is_array( $settings['__globals__'] ) ) {
			foreach ( array_keys( $settings['__globals__'] ) as $key ) {
				if ( ! isset( $kept_globals[ $key ] ) && ! in_array( (string) $key, $dropped, true ) ) {
					$dropped[] = (string) $key;
				}
			}
			$dropped = array_values( array_diff( $dropped, array( '__globals__' ) ) );
		}
		return array( $out, $dropped );
	}

	/**
	 * Controles que admite un preset del tipo, con su origen: `seed` (tiene selectores) o `closure` (añadido por el
	 * cierre). Se calcula una vez por tipo y por modo de duplicado responsivo.
	 *
	 * @param string $type     Tipo.
	 * @param array  $controls Controles del elemento.
	 * @return array<string,string>
	 */
	public static function allowed( string $type, array $controls ): array {
		$plugin = self::plugin();
		$mode   = $plugin && isset( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_responsive_control_duplication_mode' ) ? (string) $plugin->breakpoints->get_responsive_control_duplication_mode() : '';
		$cache  = $type . '|' . $mode . '|' . count( $controls );
		if ( isset( self::$allowed[ $cache ] ) ) {
			return self::$allowed[ $cache ];
		}
		$allowed = array();
		foreach ( $controls as $name => $control ) {
			if ( is_array( $control ) && ! empty( $control['selectors'] ) && empty( $control['frontend_available'] ) && self::admissible( (string) $name, $control ) && null === self::parent_of( (string) $name, $controls ) ) {
				$allowed[ (string) $name ] = 'seed';
			}
		}
		$groups = array();
		foreach ( $controls as $name => $control ) {
			if ( is_array( $control ) && 'popover_toggle' === ( $control['type'] ?? '' ) && ! empty( $control['groupPrefix'] ) ) {
				$groups[ (string) $control['groupPrefix'] ][] = (string) $name;
			}
		}
		do {
			$changed = false;
			foreach ( array_keys( $allowed ) as $name ) {
				$control = $controls[ $name ];
				$needs   = array();
				foreach ( self::condition_names( $control ) as $dependency ) {
					$needs[ $dependency ] = 'condition';
				}
				foreach ( self::placeholder_names( $control, $name ) as $dependency ) {
					if ( ! isset( $needs[ $dependency ] ) ) {
						$needs[ $dependency ] = 'placeholder';
					}
				}
				if ( ! empty( $control['groupPrefix'] ) && isset( $groups[ $control['groupPrefix'] ] ) ) {
					foreach ( $groups[ $control['groupPrefix'] ] as $toggle ) {
						$needs[ $toggle ] = 'condition';
					}
				}
				foreach ( $needs as $dependency => $why ) {
					$dependency = (string) self::root( (string) $dependency, $controls );
					if ( '' === $dependency || isset( $allowed[ $dependency ] ) || ! isset( $controls[ $dependency ] ) || ! self::admissible( $dependency, $controls[ $dependency ] ) ) {
						continue;
					}
					$kind = (string) ( $controls[ $dependency ]['type'] ?? '' );
					if ( 'condition' === $why ? ! in_array( $kind, self::CHOICE_TYPES, true ) : ! in_array( $kind, self::VALUE_TYPES, true ) ) {
						continue;
					}
					$allowed[ $dependency ] = 'closure';
					$changed                = true;
				}
			}
		} while ( $changed );
		self::$allowed[ $cache ] = $allowed;
		return $allowed;
	}

	/**
	 * El control puede entrar en un preset: ni un tipo ni un nombre excluidos, ni `prefix_class` (su efecto es una
	 * clase del HTML, que el aplanado no toca). `frontend_available` sólo excluye de la semilla: el tipo de fondo
	 * (`background_background`) lo lleva en secciones y contenedores y es la condición de todo su grupo; `video` y
	 * `slideshow`, lo único que necesita el script del front, se rechazan por valor.
	 */
	private static function admissible( string $name, array $control ): bool {
		$type = (string) ( $control['type'] ?? '' );
		return ! in_array( $type, self::EXCLUDED_TYPES, true )
			&& ! in_array( $name, self::EXCLUDED_NAMES, true )
			&& empty( $control['prefix_class'] )
			&& '__dynamic__' !== $name;
	}

	/**
	 * Nombres de los controles de `condition` y `conditions` (sin `!` ni `[subclave]`).
	 *
	 * @return string[]
	 */
	private static function condition_names( array $control ): array {
		$names = array();
		if ( ! empty( $control['condition'] ) && is_array( $control['condition'] ) ) {
			foreach ( array_keys( $control['condition'] ) as $key ) {
				$names[] = self::condition_key( (string) $key );
			}
		}
		if ( ! empty( $control['conditions'] ) && is_array( $control['conditions'] ) ) {
			$walk = function ( $node ) use ( &$walk, &$names ) {
				if ( ! is_array( $node ) ) {
					return;
				}
				if ( isset( $node['name'] ) && is_string( $node['name'] ) ) {
					$names[] = self::condition_key( $node['name'] );
				}
				if ( isset( $node['terms'] ) && is_array( $node['terms'] ) ) {
					foreach ( $node['terms'] as $term ) {
						$walk( $term );
					}
				}
			};
			$walk( $control['conditions'] );
		}
		return array_values( array_unique( array_filter( $names ) ) );
	}

	private static function condition_key( string $key ): string {
		return preg_match( '/^([A-Za-z0-9_-]+)/', $key, $found ) ? $found[1] : '';
	}

	/**
	 * Controles nombrados en un `{{otro.X}}` de los selectores (los grupos ya los prefijan).
	 *
	 * @return string[]
	 */
	private static function placeholder_names( array $control, string $own ): array {
		$names = array();
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $value ) {
			if ( preg_match_all( '/\{\{([A-Za-z0-9_-]+)\.[A-Z_]+\}\}/', $selector . ' ' . ( is_string( $value ) ? $value : '' ), $found ) ) {
				foreach ( $found[1] as $name ) {
					if ( $name !== $own ) {
						$names[] = $name;
					}
				}
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Control raíz de una clave de ajustes: la propia clave si es un control, o la clave sin su sufijo de dispositivo
	 * (`_tablet`, `_mobile_extra`…). Con el duplicado responsivo activo, `X_tablet` es un control con `parent`.
	 */
	public static function root( string $key, array $controls ): ?string {
		foreach ( Css::DEVICES as $device ) {
			$suffix = '_' . $device;
			if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix && isset( $controls[ substr( $key, 0, -strlen( $suffix ) ) ] ) ) {
				return substr( $key, 0, -strlen( $suffix ) );
			}
		}
		return isset( $controls[ $key ] ) ? $key : null;
	}

	/** Control padre de un control de dispositivo duplicado (`X_tablet` con `parent`), o `null`. */
	private static function parent_of( string $name, array $controls ): ?string {
		$root = self::root( $name, $controls );
		return null !== $root && $root !== $name ? $root : null;
	}

	/**
	 * Definición del control de una clave, con los ajustes por defecto de su tipo. En una pila optimizada (front y
	 * WP-CLI) el núcleo quitó `options` y `size_units`: no se rellenan con los del tipo y el control queda marcado
	 * con `__optimized`, para que la lista blanca valide su forma en lugar de sus opciones.
	 */
	private static function control( array $controls, string $key, string $root ): array {
		$control   = isset( $controls[ $key ] ) && is_array( $controls[ $key ] ) ? $controls[ $key ] : $controls[ $root ];
		$optimized = self::optimized_stacks();
		$plugin    = self::plugin();
		if ( $plugin && isset( $plugin->controls_manager ) ) {
			$object = $plugin->controls_manager->get_control( $control['type'] ?? '' );
			if ( $object && method_exists( $object, 'get_settings' ) ) {
				$defaults = $object->get_settings();
				if ( is_array( $defaults ) ) {
					if ( $optimized ) {
						unset( $defaults['options'], $defaults['size_units'] );
					}
					$control += $defaults;
				}
			}
		}
		if ( $optimized ) {
			$control['__optimized'] = true;
		}
		return $control;
	}

	/** Las pilas de controles de esta petición están optimizadas (sin `options` ni `size_units`). */
	public static function optimized_stacks(): bool {
		$performance = 'Elementor\\Core\\Frontend\\Performance';
		return method_exists( $performance, 'should_optimize_controls' ) && (bool) $performance::should_optimize_controls();
	}

	/**
	 * Valor validado por la lista blanca de su tipo de control, o `null` si no entra.
	 *
	 * @param array $control  Definición del control.
	 * @param mixed $value    Valor recibido.
	 * @param bool  $closure  El control entró por el cierre.
	 * @return mixed
	 */
	private static function valid_value( array $control, $value, bool $closure ) {
		$type = (string) ( $control['type'] ?? '' );
		if ( $closure && ! in_array( $type, self::VALUE_TYPES, true ) ) {
			return null;
		}
		switch ( $type ) {
			case 'color':
				return Css::color( $value );
			case 'slider':
				return self::units_value( $control, $value, array( 'size' ), array( 'sizes' => array() ) );
			case 'dimensions':
				return self::units_value( $control, $value, array( 'top', 'right', 'bottom', 'left' ), array( 'isLinked' => ! empty( $value['isLinked'] ) ) );
			case 'gaps':
				return self::units_value( $control, $value, array( 'column', 'row' ), array( 'isLinked' => ! empty( $value['isLinked'] ) ) );
			case 'number':
				$number = Css::number( $value );
				return null === $number ? null : 0 + $number;
			case 'select':
			case 'choose':
			case 'visual_choice':
				if ( ! is_string( $value ) && ! is_int( $value ) ) {
					return null;
				}
				$value = (string) $value;
				if ( in_array( $value, array( 'video', 'slideshow' ), true ) && 'background' === substr( (string) ( $control['name'] ?? '' ), -10 ) ) {
					return null;
				}
				if ( ! isset( $control['options'] ) && ! isset( $control['groups'] ) && ! empty( $control['__optimized'] ) ) {
					return 1 === preg_match( self::OPTION_TOKEN, $value ) ? $value : null;
				}
				return in_array( $value, self::option_keys( $control ), true ) ? $value : null;
			case 'switcher':
			case 'popover_toggle':
				$on = (string) ( $control['return_value'] ?? 'yes' );
				return is_string( $value ) && ( '' === $value || $on === $value ) ? $value : null;
			case 'font':
				if ( ! is_string( $value ) || '' === $value || ! class_exists( 'Elementor\\Fonts' ) ) {
					return null;
				}
				return array_key_exists( $value, (array) \Elementor\Fonts::get_fonts() ) ? $value : null;
			case 'text':
			case 'textarea':
				if ( empty( $control['selectors'] ) || ! is_string( $value ) || '' === $value || strlen( $value ) > 200 ) {
					return null;
				}
				return 1 === preg_match( self::TEXT_VALUE, $value ) && false === strpos( $value, '//' ) && false === strpos( $value, '/*' ) && false === strpos( $value, '*/' ) ? $value : null;
			case 'box_shadow':
			case 'text_shadow':
				if ( ! is_array( $value ) ) {
					return null;
				}
				$keys = 'box_shadow' === $type ? array( 'horizontal', 'vertical', 'blur', 'spread' ) : array( 'horizontal', 'vertical', 'blur' );
				$out  = array();
				foreach ( $keys as $key ) {
					$number = Css::number( isset( $value[ $key ] ) ? $value[ $key ] : 0 );
					if ( null === $number ) {
						return null;
					}
					$out[ $key ] = 0 + $number;
				}
				$color = Css::color( isset( $value['color'] ) ? $value['color'] : '' );
				if ( null === $color ) {
					return null;
				}
				$out['color'] = $color;
				return $out;
		}
		return null;
	}

	/**
	 * Valor con unidad (SLIDER, DIMENSIONS, GAPS): cada parte vacía o numérica (o una expresión con `custom`) y la
	 * unidad en `size_units`. Todas las partes vacías → `null`.
	 */
	private static function units_value( array $control, $value, array $parts, array $extra ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}
		if ( isset( $control['size_units'] ) && is_array( $control['size_units'] ) ) {
			$units = array_map( 'strval', $control['size_units'] );
		} else {
			$units = empty( $control['__optimized'] ) ? array( 'px' ) : self::UNITS;
		}
		$unit  = isset( $value['unit'] ) && is_string( $value['unit'] ) ? $value['unit'] : ( $units ? $units[0] : 'px' );
		if ( ! in_array( $unit, $units, true ) ) {
			return null;
		}
		$out   = array( 'unit' => $unit );
		$empty = true;
		foreach ( $parts as $part ) {
			$raw = isset( $value[ $part ] ) ? $value[ $part ] : '';
			if ( '' === $raw || null === $raw ) {
				$out[ $part ] = '';
				continue;
			}
			if ( 'custom' === $unit && is_string( $raw ) ) {
				$expression = Css::expression( $raw );
				if ( null === $expression ) {
					return null;
				}
				$out[ $part ] = $expression;
			} else {
				$number = Css::number( $raw );
				if ( null === $number ) {
					return null;
				}
				$out[ $part ] = 0 + $number;
			}
			$empty = false;
		}
		return $empty ? null : $out + $extra;
	}

	/** Claves de `options` (y de `groups` de un SELECT agrupado) como cadenas. */
	private static function option_keys( array $control ): array {
		$keys = array();
		foreach ( (array) ( $control['options'] ?? array() ) as $key => $label ) {
			$keys[] = (string) $key;
		}
		foreach ( (array) ( $control['groups'] ?? array() ) as $group ) {
			if ( is_array( $group ) && isset( $group['options'] ) && is_array( $group['options'] ) ) {
				foreach ( array_keys( $group['options'] ) as $key ) {
					$keys[] = (string) $key;
				}
			}
		}
		return $keys;
	}

	/** Comprobación final: ninguna clave ni ningún valor contiene algo de `DENIED`. */
	public static function safe( array $settings ): bool {
		$ok   = true;
		$walk = function ( $node ) use ( &$walk, &$ok ) {
			foreach ( $node as $key => $value ) {
				if ( ! $ok ) {
					return;
				}
				foreach ( array( (string) $key, is_scalar( $value ) ? (string) $value : '' ) as $text ) {
					$lower = strtolower( $text );
					foreach ( self::DENIED as $needle ) {
						if ( false !== strpos( $lower, $needle ) ) {
							$ok = false;
							return;
						}
					}
				}
				if ( is_array( $value ) ) {
					$walk( $value );
				} elseif ( null !== $value && ! is_scalar( $value ) ) {
					$ok = false;
					return;
				}
			}
		};
		$walk( $settings );
		return $ok;
	}

	// ── Escritura ────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Crea o actualiza un preset con el estilo de `$settings`.
	 *
	 * @param string $slug     Slug (D6).
	 * @param string $type     Tipo del elemento.
	 * @param string $label    Nombre visible; vacío conserva el actual (o usa el slug).
	 * @param array  $settings Ajustes del elemento.
	 * @return array|\WP_Error El preset guardado, con `slug` y `local` (claves que quedan locales).
	 */
	public static function save( string $slug, string $type, string $label, array $settings ) {
		if ( ! self::valid_slug( $slug ) ) {
			return self::error( 'invalid_slug', __( 'El identificador del preset sólo admite minúsculas, números y guiones (hasta 40 caracteres).', 'pro-elements' ) );
		}
		if ( ! self::supports_type( $type ) ) {
			return self::error( 'invalid_type', __( 'Este elemento no admite presets de estilo.', 'pro-elements' ) );
		}
		$label = self::clean_label( $label );
		$clean = self::sanitize_settings( $type, $settings, $local );
		if ( ! $clean ) {
			return self::error( array( '*' ) === $local ? 'unsafe' : 'empty', array( '*' ) === $local ? __( 'El estilo contiene valores que un preset no puede guardar.', 'pro-elements' ) : __( 'El elemento no tiene ningún estilo que pueda guardarse como preset.', 'pro-elements' ) );
		}
		$limits = self::limits();
		if ( self::bytes( $clean ) > $limits['settings_bytes'] ) {
			return self::error( 'too_large', __( 'El estilo es demasiado grande para un preset.', 'pro-elements' ) );
		}
		$result = self::write(
			function ( array $presets ) use ( $slug, $type, $label, $clean, $limits ) {
				if ( isset( $presets[ $slug ] ) && $presets[ $slug ]['type'] !== $type ) {
					return self::error( 'type_mismatch', __( 'Ya existe un preset con ese identificador para otro tipo de elemento.', 'pro-elements' ) );
				}
				if ( isset( $presets[ $slug ] ) && ! empty( $presets[ $slug ]['inert'] ) ) {
					return self::error( 'inert', __( 'Ese preset es de un tipo de elemento que no está disponible y no se puede editar.', 'pro-elements' ) );
				}
				if ( ! isset( $presets[ $slug ] ) && count( $presets ) >= $limits['count'] ) {
					return self::error( 'limit', __( 'Se alcanzó el número máximo de presets de estilo.', 'pro-elements' ) );
				}
				$name             = '' !== $label ? $label : ( isset( $presets[ $slug ] ) ? $presets[ $slug ]['label'] : $slug );
				$presets[ $slug ] = array(
					'type'     => $type,
					'label'    => $name,
					'settings' => $clean,
					'hash'     => self::hash( $clean ),
					'updated'  => time(),
					'inert'    => false,
				);
				return $presets;
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::changed();
		return array( 'slug' => $slug, 'local' => $local ) + $result[ $slug ];
	}

	/**
	 * Cambia el nombre visible de un preset (el slug no cambia, así que el CSS tampoco).
	 *
	 * @return true|\WP_Error
	 */
	public static function rename( string $slug, string $label ) {
		$label = self::clean_label( $label );
		if ( '' === $label ) {
			return self::error( 'invalid_label', __( 'El nombre del preset no puede quedar vacío.', 'pro-elements' ) );
		}
		$result = self::write(
			function ( array $presets ) use ( $slug, $label ) {
				if ( ! isset( $presets[ $slug ] ) ) {
					return self::error( 'not_found', __( 'Ese preset ya no existe.', 'pro-elements' ) );
				}
				$presets[ $slug ]['label'] = $label;
				return $presets;
			}
		);
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Borra un preset que nadie usa (D8). Con usos, `WP_Error` `in_use` con `data['uses']` (IDs).
	 *
	 * @return true|\WP_Error
	 */
	public static function delete( string $slug ) {
		if ( null === self::get( $slug ) ) {
			return self::error( 'not_found', __( 'Ese preset ya no existe.', 'pro-elements' ) );
		}
		$uses = self::uses( $slug );
		if ( $uses ) {
			return self::error( 'in_use', __( 'Este preset se usa en algún documento: desvincúlalo de cada elemento antes de borrarlo.', 'pro-elements' ), array( 'uses' => $uses ) );
		}
		$result = self::write(
			function ( array $presets ) use ( $slug ) {
				unset( $presets[ $slug ] );
				return $presets;
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::changed();
		return true;
	}

	/**
	 * Reemplaza todos los presets (lo usan la parte de kit y el arnés). Cada preset se valida de nuevo: los de un tipo
	 * que admite presets se sanean; los de un tipo que no existe aquí quedan inertes; lo demás se descarta
	 * (`dropped()`). Conserva `hash` y `updated` si el saneado no cambió los ajustes.
	 *
	 * `$keep`: slugs que son del sitio (los que ya tenía antes de importar un kit o los que vuelven al revertir). Se
	 * escriben **tal cual**, sin el saneado por controles, para que un preset del sitio que hoy no lo supera (su widget
	 * perdió un control) nunca se pierda al importar en «agregar» ni al revertir. Sólo pasan la forma, el tope por
	 * preset y la comprobación final, como un inerte; nada sin sanear llega al CSS, porque el aplanado vuelve a sanear
	 * cada preset antes de aplicarlo (V-I3).
	 *
	 * @param array    $data `{ v, presets }` o el mapa de presets.
	 * @param string[] $keep Slugs que se conservan tal cual.
	 */
	public static function replace( array $data, array $keep = array() ): void {
		$incoming      = isset( $data['presets'] ) && is_array( $data['presets'] ) ? $data['presets'] : ( isset( $data['v'] ) ? array() : $data );
		$keep          = array_flip( array_map( 'strval', $keep ) );
		self::$dropped = array();
		$limits        = self::limits();
		$presets       = array();
		foreach ( $incoming as $slug => $preset ) {
			$slug  = (string) $slug;
			$clean = self::normalize_incoming( $slug, $preset, isset( $keep[ $slug ] ) );
			if ( is_string( $clean ) ) {
				self::$dropped[] = array( $slug, $clean );
				continue;
			}
			if ( count( $presets ) >= $limits['count'] ) {
				self::$dropped[] = array( $slug, 'limit' );
				continue;
			}
			$next          = $presets;
			$next[ $slug ] = $clean;
			if ( self::bytes( array( 'v' => self::VERSION, 'presets' => $next ) ) > $limits['total_bytes'] ) {
				self::$dropped[] = array( $slug, 'total' );
				continue;
			}
			$presets = $next;
		}
		$before = null;
		$result = self::write(
			function ( array $current ) use ( $presets, &$before ) {
				$before = $current;
				return $presets;
			},
			true
		);
		if ( is_wp_error( $result ) ) {
			self::$dropped[] = array( '*', $result->get_error_code() );
		} elseif ( self::canonical( $result ) !== self::canonical( (array) $before ) ) {
			self::changed();
		}
	}

	/** Presets descartados por el último `replace()`: `[ [ slug, motivo ], … ]`. */
	public static function dropped(): array {
		return self::$dropped;
	}

	/**
	 * Preset recibido por `replace()`, validado, o el motivo de su descarte.
	 *
	 * @param string $slug   Slug.
	 * @param mixed  $preset Preset recibido.
	 * @param bool   $as_is  Es del sitio (`$keep`): sin el saneado por controles, sólo forma, tope y comprobación final.
	 * @return array|string
	 */
	private static function normalize_incoming( string $slug, $preset, bool $as_is = false ) {
		if ( ! self::valid_slug( $slug ) || ! is_array( $preset ) ) {
			return 'invalid';
		}
		$type     = isset( $preset['type'] ) && is_string( $preset['type'] ) ? $preset['type'] : '';
		$settings = isset( $preset['settings'] ) && is_array( $preset['settings'] ) ? $preset['settings'] : null;
		$label    = self::clean_label( isset( $preset['label'] ) && is_string( $preset['label'] ) ? $preset['label'] : '' );
		if ( 1 !== preg_match( self::TYPE, $type ) || null === $settings ) {
			return 'invalid';
		}
		$limits = self::limits();
		if ( self::bytes( $settings ) > $limits['settings_bytes'] ) {
			return 'too_large';
		}
		$registered = self::type_registered( $type );
		if ( $as_is ) {
			if ( ! self::safe( $settings ) || ! self::plain( $settings ) ) {
				return 'rejected';
			}
			$clean = $settings;
		} elseif ( $registered && ! self::supports_type( $type ) ) {
			return 'invalid_type';
		} elseif ( $registered ) {
			$clean = $settings ? self::sanitize_settings( $type, $settings ) : array();
			if ( $settings && ! $clean ) {
				return 'rejected';
			}
		} else {
			if ( ! self::safe( $settings ) || ! self::plain( $settings ) ) {
				return 'rejected';
			}
			$clean = $settings;
		}
		$hash    = isset( $preset['hash'] ) && is_string( $preset['hash'] ) && 1 === preg_match( '/^[0-9a-f]{40}$/', $preset['hash'] ) ? $preset['hash'] : '';
		$same    = self::canonical( $clean ) === self::canonical( $settings );
		$updated = isset( $preset['updated'] ) && is_numeric( $preset['updated'] ) && (int) $preset['updated'] > 0 ? (int) $preset['updated'] : time();
		return array(
			'type'     => $type,
			'label'    => '' !== $label ? $label : $slug,
			'settings' => $clean,
			'hash'     => '' !== $hash && $same ? $hash : self::hash( $clean ),
			'updated'  => $updated,
			'inert'    => ! $registered,
		);
	}

	/** Ajustes de un preset inerte: claves de control y valores escalares, como mucho 4 niveles. */
	private static function plain( array $settings, int $depth = 0 ): bool {
		if ( $depth > 4 ) {
			return false;
		}
		foreach ( $settings as $key => $value ) {
			if ( ! preg_match( '/^[A-Za-z0-9_-]{1,191}$/', (string) $key ) ) {
				return false;
			}
			if ( is_array( $value ) ? ! self::plain( $value, $depth + 1 ) : ( null !== $value && ! is_scalar( $value ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Lee, transforma y escribe la opción bajo el cerrojo. `$change` recibe los presets actuales y devuelve los
	 * nuevos o un `WP_Error`. Sin cambios no escribe; sin presets borra la opción.
	 *
	 * @param callable $change Transformación.
	 * @param bool     $wait   Reintentar unos segundos si el cerrojo está tomado (importaciones).
	 * @return array|\WP_Error Los presets escritos.
	 */
	private static function write( callable $change, bool $wait = false ) {
		require_once dirname( __DIR__ ) . '/class-lock.php';
		$tries = $wait ? 10 : 3;
		while ( ! Lock::acquire( self::LOCK, self::LOCK_STALE ) ) {
			if ( --$tries <= 0 ) {
				return self::error( 'busy', __( 'Otra persona está modificando los presets en este momento. Inténtalo de nuevo en unos segundos.', 'pro-elements' ) );
			}
			usleep( 200000 );
		}
		try {
			self::$data = null;
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( self::OPTION, 'options' );
			}
			$before = self::all();
			$after  = $change( $before );
			if ( is_wp_error( $after ) ) {
				return $after;
			}
			if ( self::bytes( array( 'v' => self::VERSION, 'presets' => $after ) ) > self::limits()['total_bytes'] ) {
				return self::error( 'total', __( 'Los presets de estilo del sitio superan el tamaño máximo.', 'pro-elements' ) );
			}
			if ( self::canonical( $after ) !== self::canonical( $before ) ) {
				if ( $after ) {
					update_option( self::OPTION, array( 'v' => self::VERSION, 'presets' => $after ), false );
				} else {
					delete_option( self::OPTION );
				}
			}
			self::$data = array( 'v' => self::VERSION, 'presets' => $after );
			return $after;
		} finally {
			Lock::release( self::LOCK );
		}
	}

	// ── Usos y permisos ──────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Documentos que usan un preset: `_elementor_data` de entradas que no son revisiones, ni están en la papelera ni
	 * son borradores automáticos, más el kit activo si sus valores por defecto lo nombran. Como mucho `$limit`.
	 *
	 * @return int[]
	 */
	public static function uses( string $slug, int $limit = 200 ): array {
		global $wpdb;
		$limit = max( 1, min( $limit, self::limits()['uses'] ) );
		$ids   = array();
		if ( self::valid_slug( $slug ) && is_object( $wpdb ) ) {
			$like = '%' . $wpdb->esc_like( '"digitalisimo_preset":"' . $slug . '"' ) . '%';
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value LIKE %s AND p.post_type <> %s AND p.post_status NOT IN ( %s, %s ) ORDER BY pm.post_id LIMIT %d", '_elementor_data', $like, 'revision', 'trash', 'auto-draft', $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $rows as $id ) {
				$ids[] = (int) $id;
			}
		}
		$kit = self::kit_id();
		if ( $kit && count( $ids ) < $limit && ! in_array( $kit, $ids, true ) && self::defaults_use( $kit, $slug ) ) {
			$ids[] = $kit;
		}
		return $ids;
	}

	/** Los valores por defecto del kit nombran el preset. */
	private static function defaults_use( int $kit, string $slug ): bool {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return false;
		}
		$defaults = get_post_meta( $kit, '_elementor_elements_default_values', true );
		if ( is_string( $defaults ) ) {
			$defaults = json_decode( $defaults, true );
		}
		foreach ( is_array( $defaults ) ? $defaults : array() as $settings ) {
			if ( is_array( $settings ) && isset( $settings['digitalisimo_preset'] ) && $slug === $settings['digitalisimo_preset'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Puede crear, actualizar, renombrar o borrar presets (D7): `manage_options` y ningún rol con la restricción
	 * `design` del gestor de roles.
	 */
	public static function can_manage( ?int $user_id = null ): bool {
		$user_id = null === $user_id ? ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ) : $user_id;
		return $user_id > 0 && function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' ) && ! self::design_restricted( $user_id );
	}

	/** Algún rol del usuario tiene «sólo contenido» (restricción `design` del gestor de roles). */
	public static function design_restricted( ?int $user_id = null ): bool {
		$user_id = null === $user_id ? ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 ) : $user_id;
		$user    = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : null;
		if ( ! $user || empty( $user->roles ) || ! function_exists( 'get_option' ) ) {
			return false;
		}
		$options = get_option( 'elementor_role-manager', array() );
		foreach ( (array) $user->roles as $role ) {
			if ( isset( $options[ $role ] ) && is_array( $options[ $role ] ) && in_array( 'design', $options[ $role ], true ) ) {
				return true;
			}
		}
		return false;
	}

	// ── Caché ────────────────────────────────────────────────────────────────────────────────────────────────────

	/** Deja pendiente un vaciado de la caché CSS para el final de la petición. */
	private static function changed(): void {
		if ( self::$pending ) {
			return;
		}
		self::$pending = true;
		if ( function_exists( 'add_action' ) ) {
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 0 );
		}
	}

	/** Vaciado pendiente: una sola vez por petición, como `Kit::save()`. */
	public static function flush(): void {
		if ( ! self::$pending ) {
			return;
		}
		self::$pending = false;
		$plugin        = self::plugin();
		if ( $plugin && isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
			$plugin->files_manager->clear_cache();
		}
	}

	/** Olvida lo leído en esta petición (otra opción, otro núcleo en las pruebas). */
	public static function reset_cache(): void {
		self::$data    = null;
		self::$allowed = array();
	}

	// ── Utilidades ───────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Presets guardados con su forma, sin volver a sanear (lo hace quien los aplica).
	 *
	 * @param array $presets Lo que hay en la opción.
	 */
	private static function normalize_stored( array $presets ): array {
		$out = array();
		foreach ( $presets as $slug => $preset ) {
			$slug = (string) $slug;
			if ( 1 !== preg_match( self::SLUG, $slug ) || ! is_array( $preset ) || ! isset( $preset['type'], $preset['settings'] ) || ! is_string( $preset['type'] ) || ! is_array( $preset['settings'] ) ) {
				continue;
			}
			$out[ $slug ] = array(
				'type'     => $preset['type'],
				'label'    => isset( $preset['label'] ) && is_string( $preset['label'] ) && '' !== $preset['label'] ? $preset['label'] : $slug,
				'settings' => $preset['settings'],
				'hash'     => isset( $preset['hash'] ) && is_string( $preset['hash'] ) ? $preset['hash'] : self::hash( $preset['settings'] ),
				'updated'  => isset( $preset['updated'] ) ? (int) $preset['updated'] : 0,
				'inert'    => ! empty( $preset['inert'] ),
			);
		}
		return $out;
	}

	/** Nombre visible saneado y acortado. */
	private static function clean_label( string $label ): string {
		$label = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $label ) : trim( strip_tags( $label ) );
		$max   = self::limits()['label'];
		return function_exists( 'mb_substr' ) ? mb_substr( $label, 0, $max ) : substr( $label, 0, $max );
	}

	/** Instancia de muestra del tipo (con los controles de estilo visibles). */
	private static function sample( string $type ) {
		$plugin = self::plugin();
		if ( ! $plugin || ! isset( $plugin->elements_manager ) ) {
			return null;
		}
		$data = 'container' === $type
			? array( 'id' => 'digipreset', 'elType' => 'container', 'settings' => array(), 'elements' => array() )
			: array( 'id' => 'digipreset', 'elType' => 'widget', 'widgetType' => $type, 'settings' => array(), 'elements' => array() );
		try {
			return $plugin->elements_manager->create_element_instance( $data );
		} catch ( \Throwable $error ) {
			return null;
		}
	}

	/** JSON canónico: claves ordenadas en todos los niveles. */
	private static function canonical( $value ): string {
		$sort = function ( $node ) use ( &$sort ) {
			if ( ! is_array( $node ) ) {
				return $node;
			}
			if ( array_keys( $node ) !== range( 0, count( $node ) - 1 ) ) {
				ksort( $node, SORT_STRING );
			}
			return array_map( $sort, $node );
		};
		return (string) json_encode( $sort( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/** Bytes en JSON. */
	private static function bytes( $value ): int {
		return strlen( (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private static function error( string $code, string $message, $data = '' ): \WP_Error {
		return new \WP_Error( $code, $message, $data );
	}

	private static function kit_id(): int {
		$plugin = self::plugin();
		return $plugin && isset( $plugin->kits_manager ) && method_exists( $plugin->kits_manager, 'get_active_id' ) ? (int) $plugin->kits_manager->get_active_id() : 0;
	}

	/** `Elementor\Plugin::$instance`, o `null`. */
	private static function plugin() {
		return class_exists( 'Elementor\\Plugin', false ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
	}
}
