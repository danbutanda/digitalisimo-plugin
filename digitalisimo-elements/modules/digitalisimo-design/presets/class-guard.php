<?php
namespace Digitalisimo\Elements\Design\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * Guarda del vínculo `digitalisimo_preset` al guardar un documento (`elementor/document/save/data`, prioridad 30).
 *
 * - Vacía un vínculo con un slug que no cumple D6, de un preset que no existe o de otro tipo (V-I2: la lista del
 *   control es común a todos los widgets, así que el servidor decide). Un preset inerte del mismo tipo se conserva.
 * - Con la restricción `design` del gestor de roles («sólo contenido») en alguno de los roles del usuario, restaura
 *   el valor que cada elemento tenía guardado, por ID (D7); un elemento nuevo queda sin vínculo.
 *
 * Corre después del remapeo de los kits (`Kits\Context::remap()`, prioridad 20), que traduce `digitalisimo_preset`
 * con `map:presets`: así nunca se vacía un slug antes de traducirlo. Los marcadores `digitalisimo-unconverted` no se
 * tocan. Si no hay ningún vínculo en juego, no lee nada.
 */
final class Guard {
	const PRIORITY = 30;

	/** @var bool */
	private static $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'elementor/document/save/data', array( __CLASS__, 'on_save' ), self::PRIORITY, 2 );
	}

	/**
	 * @param mixed $data     Datos del documento (`elements`, `settings`).
	 * @param mixed $document Documento que se guarda.
	 * @return mixed
	 */
	public static function on_save( $data, $document = null ) {
		if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}
		$restricted = Store::design_restricted();
		if ( ! $restricted && ! self::mentions( $data['elements'] ) ) {
			return $data;
		}
		$previous = $restricted ? self::previous( $document ) : array();
		$data['elements'] = self::walk( $data['elements'], $restricted, $previous );
		return $data;
	}

	/** Algún elemento nombra el control (barato: sin decodificar nada). */
	private static function mentions( array $elements ): bool {
		return false !== strpos( (string) json_encode( $elements ), '"' . Controls::CONTROL . '"' );
	}

	/**
	 * Vínculos guardados del documento, por ID de elemento.
	 *
	 * @param mixed $document Documento.
	 * @return array<string,string>
	 */
	private static function previous( $document ): array {
		$elements = is_object( $document ) && method_exists( $document, 'get_elements_data' ) ? $document->get_elements_data() : array();
		$out      = array();
		$collect  = function ( $nodes ) use ( &$collect, &$out ) {
			foreach ( is_array( $nodes ) ? $nodes : array() as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( isset( $node['id'] ) && isset( $node['settings'][ Controls::CONTROL ] ) && is_string( $node['settings'][ Controls::CONTROL ] ) ) {
					$out[ (string) $node['id'] ] = $node['settings'][ Controls::CONTROL ];
				}
				$collect( isset( $node['elements'] ) ? $node['elements'] : array() );
			}
		};
		$collect( $elements );
		return $out;
	}

	/**
	 * @param array                $elements   Elementos.
	 * @param bool                 $restricted El usuario sólo puede editar contenido.
	 * @param array<string,string> $previous   Vínculos guardados por ID.
	 */
	private static function walk( array $elements, bool $restricted, array $previous ): array {
		foreach ( $elements as $index => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$node['elements'] = self::walk( $node['elements'], $restricted, $previous );
			}
			if ( 'widget' === ( $node['elType'] ?? '' ) && 'digitalisimo-unconverted' === ( $node['widgetType'] ?? '' ) ) {
				$elements[ $index ] = $node;
				continue;
			}
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$has      = array_key_exists( Controls::CONTROL, $settings );
			$value    = $has && is_string( $settings[ Controls::CONTROL ] ) ? $settings[ Controls::CONTROL ] : '';
			$id       = isset( $node['id'] ) ? (string) $node['id'] : '';
			if ( $restricted ) {
				$value = isset( $previous[ $id ] ) ? $previous[ $id ] : '';
				$has   = $has || isset( $previous[ $id ] );
			}
			if ( $has ) {
				$value = self::valid_link( $value, self::type_of( $node ) );
				if ( '' === $value ) {
					unset( $settings[ Controls::CONTROL ] );
				} else {
					$settings[ Controls::CONTROL ] = $value;
				}
				$node['settings'] = $settings;
			}
			$elements[ $index ] = $node;
		}
		return $elements;
	}

	/** El vínculo si es válido para ese tipo de elemento; si no, ''. */
	public static function valid_link( string $slug, string $type ): string {
		if ( '' === $slug || '' === $type || ! Store::valid_slug( $slug ) ) {
			return '';
		}
		$preset = Store::get( $slug );
		return null !== $preset && $type === $preset['type'] ? $slug : '';
	}

	/** Tipo de un elemento: su `widgetType` o su `elType`. */
	private static function type_of( array $node ): string {
		$el = isset( $node['elType'] ) ? (string) $node['elType'] : '';
		return 'widget' === $el ? (string) ( $node['widgetType'] ?? '' ) : $el;
	}
}
