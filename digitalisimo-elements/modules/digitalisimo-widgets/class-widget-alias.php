<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/**
 * Un widget propio fusionado en otro no desaparece: su identificador, su clase PHP y sus clases CSS
 * siguen existiendo como alias oculto del widget que lo absorbió. Así el contenido guardado sigue
 * abriéndose en el editor y pintándose igual, los adaptadores de DIGITALÍSIMO Migración (que extienden
 * la clase PHP por su nombre) y sus mapas de estilo (que apuntan a las clases CSS de antes) siguen
 * funcionando sin convertir nada, y el panel sólo ofrece el widget fusionado.
 *
 * La clase alias declara ALIAS_ID (su nombre de siempre), traduce sus ajustes guardados a los del widget
 * fusionado en upgrade_settings(), enumera en LEGACY_KEYS las claves de antes que traduce (para que la
 * conversión de la Migración las conserve) y describe sus clases CSS en LEGACY_CLASSES: el prefijo de
 * antes y, por elemento del marcado unificado, el nombre que tenía (false si no existía).
 */
trait Widget_Alias {
	public function __construct( $data = array(), $args = null ) {
		if ( is_array( $data ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data['settings'] = static::upgrade_settings( $data['settings'] );
		}
		// La clase fusionada (no un adaptador que extienda este alias) puede no tener constructor en las pruebas.
		if ( method_exists( (string) get_parent_class( __CLASS__ ), '__construct' ) ) {
			parent::__construct( $data, $args );
		}
	}

	public function get_name() {
		return static::ALIAS_ID;
	}

	public function show_in_panel() {
		return false;
	}

	/** Prefijo y nombres de elemento de las clases CSS que el widget tenía antes de fusionarse. */
	protected function legacy_classes() {
		return static::LEGACY_CLASSES;
	}

	/**
	 * Las claves del esquema de antes (LEGACY_KEYS: nombre => si era responsivo) se registran como controles
	 * ocultos: la conversión de DIGITALÍSIMO Migración conserva sólo las claves con control en el widget
	 * destino, y esas claves son las que este alias traduce al pintarse. Una clave que ya existe en el
	 * widget fusionado no se repite.
	 */
	protected function register_controls() {
		parent::register_controls();
		$hidden   = defined( '\\Elementor\\Controls_Manager::HIDDEN' ) ? \Elementor\Controls_Manager::HIDDEN : 'hidden';
		$existing = (array) $this->get_controls();
		$keys     = array();
		foreach ( static::LEGACY_KEYS as $key => $responsive ) {
			if ( ! isset( $existing[ $key ] ) ) {
				$keys[ $key ] = $responsive;
			}
		}
		if ( ! $keys ) {
			return;
		}
		// Elementor exige una sección; su condición nunca se cumple, así que el panel no la muestra.
		$this->start_controls_section( 'digitalisimo_alias_legacy', array( 'label' => 'Ajustes anteriores', 'condition' => array( 'digitalisimo_alias_legacy_section' => 'never' ) ) );
		foreach ( $keys as $key => $responsive ) {
			if ( $responsive ) {
				$this->add_responsive_control( $key, array( 'type' => $hidden ) );
			} else {
				$this->add_control( $key, array( 'type' => $hidden ) );
			}
		}
		$this->end_controls_section();
	}
}

/**
 * Clases CSS del marcado unificado. cls( 'card', array( 'stretch' ) ) devuelve `prefijo__card prefijo__card--stretch`
 * y, en un alias, también las clases que ese widget tenía (`digi-brand-carousel__card ...`): el contenido
 * guardado conserva sus estilos y los mapas heredados siguen encontrando sus selectores. El widget fusionado
 * sólo imprime su propio prefijo.
 */
trait Legacy_Classes {
	protected function legacy_classes() {
		return array();
	}

	/** Prefijos [ prefijo, mapa de elementos ] que se imprimen: el propio y, en un alias, el de antes. */
	protected function class_prefixes() {
		$prefixes = array( array( static::CSS_PREFIX, array() ) );
		$legacy   = $this->legacy_classes();
		if ( ! empty( $legacy['prefix'] ) ) {
			$prefixes[] = array( (string) $legacy['prefix'], is_array( $legacy['elements'] ?? null ) ? $legacy['elements'] : array() );
		}
		return $prefixes;
	}

	protected function cls( $element = '', $modifiers = array() ) {
		$out = array();
		foreach ( $this->class_prefixes() as $spec ) {
			list( $prefix, $map ) = $spec;
			$name = '' === $element ? $prefix : ( $map[ $element ] ?? $element );
			if ( false === $name ) {
				continue;
			}
			$base  = '' === $element ? $prefix : $prefix . '__' . $name;
			$out[] = $base;
			foreach ( (array) $modifiers as $modifier ) {
				if ( '' !== (string) $modifier ) {
					$out[] = $base . '--' . $modifier;
				}
			}
		}
		return implode( ' ', $out );
	}

	/** El mismo generador de clases para la vista previa del editor. */
	protected function cls_template_js() {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $this->class_prefixes() ) : json_encode( $this->class_prefixes() );
		return 'var P = ' . $json . '; var cls = function ( el, mods ) { var out = []; P.forEach( function ( p ) { var m = el ? ( p[1][ el ] === undefined ? el : p[1][ el ] ) : p[0]; if ( m === false ) { return; } var base = el ? p[0] + "__" + m : p[0]; out.push( base ); ( mods || [] ).forEach( function ( x ) { if ( x ) { out.push( base + "--" + x ); } } ); } ); return out.join( " " ); };';
	}
}

/** Traducción de ajustes guardados: renombra claves con sus variantes responsivas y dinámicas. */
final class Settings_Upgrade {
	const DEVICES = array( '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

	/** Renombra $from a $to (y sus variantes por dispositivo y en __dynamic__) sin pisar un $to ya presente. */
	public static function rename( array &$settings, $from, $to ) {
		foreach ( self::DEVICES as $device ) {
			if ( array_key_exists( $from . $device, $settings ) ) {
				if ( ! array_key_exists( $to . $device, $settings ) ) {
					$settings[ $to . $device ] = $settings[ $from . $device ];
				}
				unset( $settings[ $from . $device ] );
			}
		}
		if ( isset( $settings['__dynamic__'] ) && is_array( $settings['__dynamic__'] ) && array_key_exists( $from, $settings['__dynamic__'] ) ) {
			if ( ! array_key_exists( $to, $settings['__dynamic__'] ) ) {
				$settings['__dynamic__'][ $to ] = $settings['__dynamic__'][ $from ];
			}
			unset( $settings['__dynamic__'][ $from ] );
		}
	}

	/** Fija un valor sólo si la clave no existe: el default que el widget de antes aplicaba. */
	public static function fill( array &$settings, array $defaults ) {
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}
	}

	/** Traduce las filas de un repetidor: renombra campos y añade valores fijos; conserva `_id` y `__dynamic__`. */
	public static function rows( $rows, array $fields, array $extra = array() ) {
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = $row;
			foreach ( $fields as $from => $to ) {
				self::rename( $item, $from, $to );
			}
			$out[] = $extra + $item;
		}
		return $out;
	}
}
