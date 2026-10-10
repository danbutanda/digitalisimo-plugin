<?php
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

/**
 * Traduce los ajustes que Element Pack guardó para un widget `bdt-*` a los del widget propio.
 *
 * Elementor no guarda los valores que coinciden con el default del control, así que primero se
 * completan los defaults de Element Pack; después se renombran claves (con sus variantes
 * responsivas y dinámicas), se convierten valores y se traducen los repetidores. Las claves
 * comunes de Elementor (márgenes, fondos, visibilidad, CSS propio…) pasan sin cambios.
 */
final class Translator {
	const MARKER  = '_digitalisimo_legacy';
	const DEVICES = array( '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' );

	private static $maps = array();

	/** Element Pack conserva sus IDs mientras esté cargado; ni adaptadores ni migración compiten con él. */
	public static function element_pack_active() {
		return defined( 'BDTEP_VER' ) || class_exists( '\\ElementPack\\Element_Pack_Loader', false );
	}

	/** @return string[] IDs heredados con mapa disponible. */
	public static function ids() {
		$ids = array();
		foreach ( glob( __DIR__ . '/maps/bdt-*.php' ) ?: array() as $file ) {
			if ( false === strpos( basename( $file ), '.styles.' ) ) {
				$ids[] = basename( $file, '.php' );
			}
		}
		sort( $ids );
		return $ids;
	}

	public static function map( $legacy_id ) {
		if ( ! array_key_exists( $legacy_id, self::$maps ) ) {
			$file                     = __DIR__ . '/maps/' . basename( (string) $legacy_id ) . '.php';
			self::$maps[ $legacy_id ] = preg_match( '/^bdt-[a-z0-9-]+$/', (string) $legacy_id ) && is_file( $file ) ? require $file : null;
		}
		return self::$maps[ $legacy_id ];
	}

	/** Controles de estilo de Element Pack con selectores ya reescritos al marcado propio. */
	public static function styles( $legacy_id ) {
		$file = __DIR__ . '/maps/' . basename( (string) $legacy_id ) . '.styles.php';
		return preg_match( '/^bdt-[a-z0-9-]+$/', (string) $legacy_id ) && is_file( $file ) ? (array) require $file : array();
	}

	public static function target( $legacy_id ) {
		$map = self::map( $legacy_id );
		return is_array( $map ) ? (string) $map['target'] : '';
	}

	/**
	 * Traduce una vez: un documento ya traducido y guardado desde el editor conserva lo editado.
	 */
	public static function translate( $legacy_id, array $settings ) {
		$map = self::map( $legacy_id );
		if ( ! is_array( $map ) || isset( $settings[ self::MARKER ] ) ) {
			return $settings;
		}
		$out = self::apply( $settings, $map );
		foreach ( $map['repeaters'] ?? array() as $key => $spec ) {
			$rows = array_key_exists( $key, $settings ) ? $settings[ $key ] : ( $spec['default_rows'] ?? array() );
			unset( $out[ $key ] );
			$items = array();
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( is_array( $row ) ) {
					$items[] = self::apply( $row, $spec );
				}
			}
			$out[ $spec['target'] ?? $key ] = $items;
		}
		if ( isset( $map['filter'] ) && is_callable( $map['filter'] ) ) {
			$out = (array) call_user_func( $map['filter'], $out, $settings );
		}
		$out[ self::MARKER ] = $legacy_id;
		return $out;
	}

	/** Defaults, renombres, valores y fijos de un nivel (widget o fila de repetidor). */
	private static function apply( array $settings, array $spec ) {
		foreach ( $spec['defaults'] ?? array() as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
			}
		}
		$out = $settings;
		foreach ( $spec['rename'] ?? array() as $from => $to ) {
			foreach ( self::DEVICES as $device ) {
				if ( array_key_exists( $from . $device, $settings ) ) {
					unset( $out[ $from . $device ] );
					$out[ $to . $device ] = $settings[ $from . $device ];
				}
			}
			if ( isset( $settings['__dynamic__'][ $from ] ) ) {
				unset( $out['__dynamic__'][ $from ] );
				$out['__dynamic__'][ $to ] = $settings['__dynamic__'][ $from ];
			}
		}
		foreach ( $spec['groups'] ?? array() as $from => $to ) {
			foreach ( $settings as $key => $value ) {
				if ( 0 === strpos( $key, $from . '_' ) ) {
					unset( $out[ $key ] );
					$out[ $to . substr( $key, strlen( $from ) ) ] = $value;
				}
			}
		}
		foreach ( $spec['values'] ?? array() as $key => $values ) {
			if ( ! array_key_exists( $key, $out ) ) {
				continue;
			}
			if ( is_callable( $values ) ) {
				$out[ $key ] = call_user_func( $values, $out[ $key ], $settings );
			} elseif ( is_scalar( $out[ $key ] ) && array_key_exists( (string) $out[ $key ], $values ) ) {
				$out[ $key ] = $values[ (string) $out[ $key ] ];
			}
		}
		// Textos que Element Pack aceptaba con HTML y el widget propio escapa.
		foreach ( $spec['strip'] ?? array() as $key ) {
			if ( isset( $out[ $key ] ) && is_string( $out[ $key ] ) ) {
				$out[ $key ] = trim( wp_strip_all_tags( $out[ $key ] ) );
			}
		}
		foreach ( $spec['set'] ?? array() as $key => $value ) {
			$out[ $key ] = $value;
		}
		foreach ( $spec['drop'] ?? array() as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	}
}
