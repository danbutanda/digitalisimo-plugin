<?php
/**
 * F8 · Sin regresión en lo ya soportado (V.17 n.º 1): la conversión de los 193 casos de `tests/legacy/*.json` que había
 * en 1.0.20 (186 `bdt-*`, 5 `ext-*`, el calendario de FooEvents y Lightbox) es la misma que guardó la instantánea
 * versionada `tests/fixtures/f8-legacy-baseline.json` (generada con el código de 1.0.20, `main` 59f46016, antes de
 * tocar nada; no depende del historial de git). Sólo se admite una diferencia en la lista declarada: casos con claves
 * de las extensiones que F8 convierte por primera vez (Effects\Dispatcher), que antes se perdían o se quedaban sin
 * efecto. Mismo método que la instantánea: Native::element() sin Elementor cargado, sha256 de [elemento, cambios].
 */
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration( array( 'translator', 'context', 'report', 'native' ) );
	use Digitalisimo\Elements\Legacy\Native;

	$baseline = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/f8-legacy-baseline.json' ), true );
	f8_check( is_array( $baseline ) && 193 === (int) $baseline['files'] && 301 === count( $baseline['cases'] ), 'La instantánea tiene los 193 archivos y sus 301 casos.' );

	/** ¿Tiene el caso claves de una extensión que F8 convierte por primera vez? (Lista declarada.) */
	function declared_case( array $settings ) {
		$dispatcher = '\\Digitalisimo\\Elements\\Legacy\\Effects\\Dispatcher';
		if ( ! class_exists( $dispatcher ) ) {
			return false;
		}
		if ( method_exists( $dispatcher, 'detect' ) && $dispatcher::detect( $settings ) ) {
			return true;
		}
		foreach ( array_keys( $settings ) as $key ) {
			if ( method_exists( $dispatcher, 'is_key' ) && $dispatcher::is_key( (string) $key ) && ! preg_match( Native::ELEMENT_KEYS, (string) $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Correcciones de fidelidad frente a Element Pack (2026-11-06, `ep-verdicts`): cada una escribe una clave opt-in del
	 * widget de destino; sin ella, la conversión debe ser exactamente la de 1.0.20 (nada más cambia).
	 */
	const FIDELITY = array(
		'digitalisimo_shortcode_if_registered' => 'el shortcode de un plugin que no está activo ya no se pinta como texto (Element Pack no registraba el widget)',
		'image_ratio'                          => 'Interactive Card muestra su imagen entera, a su proporción, como Element Pack',
	);

	/** Notas `_known` de la instantánea en los casos cuya nota se corrigió después (2026-11-06: sólo el ALT es distinto). */
	const SNAPSHOT_KNOWN = array(
		'bdt-animated-card'    => array( 'images' => 'Las imágenes de ejemplo eran recursos de Element Pack; se usa la de Elementor' ),
		'bdt-interactive-card' => array( 'images' => 'Las imágenes de ejemplo eran recursos de Element Pack; se usa la de Elementor' ),
	);

	/** Motivo declarado si el caso sólo difiere de la instantánea en claves de FIDELITY; null si no. */
	function fidelity_case( array $converted, $changes, string $expected ) {
		$settings = (array) ( $converted['settings'] ?? array() );
		$found    = array_intersect_key( FIDELITY, $settings );
		if ( ! $found ) {
			return null;
		}
		$converted['settings'] = array_diff_key( $settings, FIDELITY );
		return hash( 'sha256', json_encode( array( $converted, $changes ) ) ) === $expected ? implode( '; ', $found ) : null;
	}

	$by_file  = array();
	$declared = array();
	$fidelity = array();
	foreach ( $baseline['cases'] as $case => $expected ) {
		list( $name, $variant ) = explode( '/', $case, 2 );
		$by_file[ $name ][ $variant ] = $expected;
	}
	f8_check( 193 === count( $by_file ), 'Los casos pertenecen a 193 archivos.' );
	foreach ( $by_file as $name => $variants ) {
		$file = __DIR__ . '/legacy/' . $name . '.json';
		$data = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
		if ( ! is_array( $data ) ) {
			f8_check( false, 'Falta el caso ' . $name );
		}
		$ok     = true;
		$detail = array();
		foreach ( $variants as $variant => $expected ) {
			$settings = $data[ $variant ] ?? null;
			if ( ! is_array( $settings ) ) {
				$ok       = false;
				$detail[] = $variant . ' (desaparecido)';
				continue;
			}
			if ( 0 === strpos( $name, 'ext-' ) ) {
				$type    = (string) ( $settings['_widget'] ?? 'heading' );
				$el_type = in_array( $type, array( 'container', 'section', 'column' ), true ) ? $type : 'widget';
				foreach ( array_keys( $settings ) as $key ) {
					if ( 0 === strpos( $key, '_expect' ) || '_widget' === $key || '_known' === $key ) {
						unset( $settings[ $key ] );
					}
				}
				$element = array( 'id' => 'e1', 'elType' => $el_type, 'settings' => $settings, 'elements' => array() );
				if ( 'widget' === $el_type ) {
					$element['widgetType'] = $type;
				}
			} else {
				// `_known` es una nota del arnés (pasa intacta por la conversión): se usa la que tenía el caso al generar la
				// instantánea, así una nota corregida no esconde ni inventa un cambio de la conversión.
				if ( isset( SNAPSHOT_KNOWN[ $name ], $settings['_known'] ) ) {
					$settings['_known'] = SNAPSHOT_KNOWN[ $name ];
				}
				$element = array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => $name, 'settings' => $settings, 'elements' => array() );
			}
			list( $converted, $changes ) = Native::element( $element, 1 );
			$actual = hash( 'sha256', json_encode( array( $converted, $changes ) ) );
			if ( $actual === $expected ) {
				continue;
			}
			$why = fidelity_case( $converted, $changes, $expected );
			if ( null !== $why ) {
				$fidelity[ $why ][] = $name . '/' . $variant;
				continue;
			}
			if ( declared_case( $settings ) ) {
				$declared[] = $name . '/' . $variant;
				continue;
			}
			$ok       = false;
			$detail[] = $variant;
		}
		f8_check( $ok, 'La conversión de ' . $name . ' cambió respecto de 1.0.20 sin estar declarada: ' . implode( ', ', $detail ) );
	}
	f8_check( count( $declared ) <= 40, 'La lista declarada es corta (casos con claves de efectos): ' . count( $declared ) );
	if ( $declared ) {
		echo 'Diferencias declaradas (claves de extensiones convertidas por F8): ' . implode( ', ', $declared ) . "\n";
	}
	foreach ( $fidelity as $why => $cases ) {
		echo 'Corrección de fidelidad declarada (' . $why . '): ' . count( $cases ) . ' casos (' . implode( ', ', $cases ) . ")\n";
	}
	f8_check( isset( $fidelity[ FIDELITY['image_ratio'] ] ) && in_array( 'bdt-interactive-card/defaults', $fidelity[ FIDELITY['image_ratio'] ], true ), 'Interactive Card sólo cambia en la proporción de la imagen.' );
	f8_check( isset( $fidelity[ FIDELITY['digitalisimo_shortcode_if_registered'] ] ) && in_array( 'bdt-contact-form-7/chosen', $fidelity[ FIDELITY['digitalisimo_shortcode_if_registered'] ], true ), 'Los formularios con shortcode sólo cambian en la clave que lo oculta sin su plugin.' );
	f8_done( 'DIGITALÍSIMO Migración: línea base de 1.0.20', 193 );
}
