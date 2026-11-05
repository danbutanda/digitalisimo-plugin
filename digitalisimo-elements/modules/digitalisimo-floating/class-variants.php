<?php
namespace Digitalisimo\Elements\Floating;

defined( 'ABSPATH' ) || exit;

/**
 * Unión de los controles de varias variantes en un widget con presentaciones (C8, `docs/pro/c8.md`).
 *
 * Cada familia (botones de contacto, barra flotante, Link in Bio) tiene en el núcleo una base configurable por
 * `static::get_configuration()`; cada variante de PRO sólo cambia esa configuración y la lista de secciones. El widget
 * propio construye cada variante en una «sonda» (una clase que hereda la base del núcleo y responde con la configuración
 * de la presentación pedida), toma su pila de controles ya procesada (`get_stack( false )`) y registra la unión:
 *
 * - mismo nombre y mismo orden relativo; un control que sólo existe en algunas presentaciones se inserta detrás del
 *   anterior de su misma variante y lleva la condición `presentation` con esas presentaciones;
 * - los argumentos son los de la primera presentación que lo declara; de las demás se suman los `selectors`, las
 *   `options` y los campos de un repetidor (`fields`), que no cambian lo que hace la primera;
 * - si la condición propia del control cambia entre presentaciones, se escribe como `conditions` con un término por
 *   grupo de presentaciones (`presentation` y la condición de ese grupo).
 *
 * `merge()` es pura (sólo arreglos) para las pruebas; `collect()` e `import()` hablan con el gestor de controles del
 * núcleo. Las diferencias de valores por defecto entre presentaciones quedan en `merge()['differences']`: las completa el
 * mapa de cada variante (`defaults`), porque un control de Elementor tiene un solo valor por defecto.
 */
final class Variants {
	/** Claves de un control que no se comparan entre presentaciones (las fija el gestor o las arma `merge()`). */
	const SKIP = array( 'name', 'condition', 'conditions', 'selectors', 'options', 'fields', 'inheritors' );

	/**
	 * Une las listas de controles de cada presentación.
	 *
	 * @param array<string, array<string, array>> $lists Presentación => controles ordenados (`id => argumentos`).
	 * @param string                              $key   Nombre del control de presentación.
	 * @return array{controls: array<string, array>, presentations: array<string, string[]>, differences: array<string, string[]>}
	 */
	public static function merge( array $lists, string $key = 'presentation' ): array {
		$all   = array_map( 'strval', array_keys( $lists ) );
		$order = array();
		$by    = array();
		foreach ( $lists as $presentation => $controls ) {
			$presentation = (string) $presentation;
			$previous     = null;
			foreach ( $controls as $id => $args ) {
				$id = (string) $id;
				if ( ! isset( $by[ $id ] ) ) {
					// Justo detrás del anterior de esta misma variante: los grupos, pestañas y ventanas emergentes siguen juntos.
					$at = null === $previous ? 0 : array_search( $previous, $order, true ) + 1;
					array_splice( $order, $at, 0, array( $id ) );
					$by[ $id ] = array();
				}
				$by[ $id ][ $presentation ] = (array) $args;
				$previous                   = $id;
			}
		}
		$out         = array();
		$sets        = array();
		$differences = array();
		foreach ( $order as $id ) {
			$variants = $by[ $id ];
			$first    = reset( $variants );
			$merged   = $first;
			$diff     = array();
			foreach ( $variants as $args ) {
				if ( $args === $first ) {
					continue;
				}
				foreach ( array( 'selectors', 'options' ) as $field ) {
					if ( isset( $args[ $field ] ) && is_array( $args[ $field ] ) ) {
						$merged[ $field ] = self::union( (array) ( $merged[ $field ] ?? array() ), $args[ $field ], 'selectors' === $field );
					}
				}
				if ( isset( $args['fields'] ) && is_array( $args['fields'] ) ) {
					$merged['fields'] = self::fields( (array) ( $merged['fields'] ?? array() ), $args['fields'], $diff );
				}
				foreach ( array_unique( array_merge( array_keys( $first ), array_keys( $args ) ) ) as $arg ) {
					if ( ! in_array( $arg, self::SKIP, true ) && ( $first[ $arg ] ?? null ) !== ( $args[ $arg ] ?? null ) ) {
						$diff[] = $arg;
					}
				}
			}
			$present = array_map( 'strval', array_keys( $variants ) );
			$merged  = self::conditions( $merged, $variants, $present, $all, $key );
			$out[ $id ]  = $merged;
			$sets[ $id ] = $present;
			if ( $diff ) {
				$differences[ $id ] = array_values( array_unique( $diff ) );
			}
		}
		return array( 'controls' => $out, 'presentations' => $sets, 'differences' => $differences );
	}


	/** Une dos mapas: lo nuevo se añade al final; en `selectors`, una declaración distinta en el mismo selector se suma. */
	private static function union( array $base, array $more, bool $selectors ): array {
		foreach ( $more as $k => $v ) {
			if ( ! array_key_exists( $k, $base ) ) {
				$base[ $k ] = $v;
			} elseif ( $selectors && is_string( $v ) && is_string( $base[ $k ] ) && false === strpos( $base[ $k ], $v ) ) {
				$base[ $k ] = rtrim( $base[ $k ], '; ' ) . '; ' . $v;
			}
		}
		return $base;
	}

	/** Campos de un repetidor: unión por nombre, con sus `options` y `selectors` sumados. */
	private static function fields( array $base, array $more, array &$diff ): array {
		$index = array();
		foreach ( $base as $i => $field ) {
			if ( isset( $field['name'] ) ) {
				$index[ $field['name'] ] = $i;
			}
		}
		$assoc = array_keys( $base ) !== range( 0, count( $base ) - 1 );
		foreach ( $more as $k => $field ) {
			$name = (string) ( $field['name'] ?? $k );
			if ( ! isset( $index[ $name ] ) ) {
				if ( $assoc ) {
					$base[ $name ] = $field;
				} else {
					$base[] = $field;
				}
				$index[ $name ] = $assoc ? $name : count( $base ) - 1;
				$diff[]         = 'fields';
				continue;
			}
			$i = $index[ $name ];
			foreach ( array( 'selectors', 'options' ) as $part ) {
				if ( isset( $field[ $part ] ) && is_array( $field[ $part ] ) ) {
					$base[ $i ][ $part ] = self::union( (array) ( $base[ $i ][ $part ] ?? array() ), $field[ $part ], 'selectors' === $part );
				}
			}
			foreach ( array( 'default', 'condition', 'conditions' ) as $part ) {
				if ( ( $base[ $i ][ $part ] ?? null ) !== ( $field[ $part ] ?? null ) ) {
					$diff[] = 'fields.' . $name . '.' . $part;
				}
			}
		}
		return $base;
	}

	/**
	 * Condición del control unido: la propia (igual en todas sus presentaciones) más `presentation` cuando no está en
	 * todas; si la propia cambia, un `conditions` con un término por grupo.
	 */
	private static function conditions( array $merged, array $variants, array $present, array $all, string $key ): array {
		$groups = array();
		foreach ( $variants as $presentation => $args ) {
			$sig = wp_json_encode_safe( array( $args['condition'] ?? null, $args['conditions'] ?? null ) );
			$groups[ $sig ]['presentations'][] = (string) $presentation;
			$groups[ $sig ]['condition']       = $args['condition'] ?? null;
			$groups[ $sig ]['conditions']      = $args['conditions'] ?? null;
		}
		$partial = count( $present ) !== count( $all );
		unset( $merged['condition'], $merged['conditions'] );
		if ( 1 === count( $groups ) ) {
			$group = reset( $groups );
			$cond  = (array) ( $group['condition'] ?? array() );
			if ( $partial ) {
				$cond[ $key ] = $present;
			}
			if ( $cond ) {
				$merged['condition'] = $cond;
			}
			if ( ! empty( $group['conditions'] ) ) {
				$merged['conditions'] = $group['conditions'];
			}
			return $merged;
		}
		if ( $partial ) {
			$merged['condition'] = array( $key => $present );
		}
		$terms = array();
		foreach ( $groups as $group ) {
			$and = array( array( 'name' => $key, 'operator' => 'in', 'value' => $group['presentations'] ) );
			foreach ( (array) ( $group['condition'] ?? array() ) as $name => $value ) {
				$negative = '!' === substr( (string) $name, -1 );
				$and[]    = array(
					'name'     => $negative ? substr( (string) $name, 0, -1 ) : (string) $name,
					'operator' => is_array( $value ) ? ( $negative ? '!in' : 'in' ) : ( $negative ? '!==' : '===' ),
					'value'    => $value,
				);
			}
			if ( ! empty( $group['conditions'] ) ) {
				$and[] = $group['conditions'];
			}
			$terms[] = array( 'relation' => 'and', 'terms' => $and );
		}
		$merged['conditions'] = array( 'relation' => 'or', 'terms' => $terms );
		return $merged;
	}

	/**
	 * Controles propios (sin los comunes) de la sonda de cada presentación, ya procesados por el núcleo.
	 *
	 * @param string   $probe         Clase de la sonda (hereda la base del núcleo y tiene `use_presentation()`).
	 * @param string[] $presentations Presentaciones, en su orden.
	 * @return array<string, array<string, array>>
	 */
	public static function collect( string $probe, array $presentations ): array {
		$lists = array();
		foreach ( $presentations as $presentation ) {
			$probe::use_presentation( (string) $presentation );
			try {
				$instance = new $probe();
				$stack    = $instance->get_stack( false );
				$controls = (array) ( $stack['controls'] ?? array() );
				if ( ! empty( $stack['style_controls'] ) ) {
					$controls += (array) $stack['style_controls'];
				}
				$lists[ (string) $presentation ] = $controls;
				// La pila de la sonda ya no hace falta: el gestor la olvida (cada sonda tiene su nombre por presentación).
				$manager = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->controls_manager ) ? \Elementor\Plugin::$instance->controls_manager : null;
				if ( is_object( $manager ) && method_exists( $manager, 'delete_stack' ) ) {
					$manager->delete_stack( $instance );
				}
			} finally {
				$probe::use_presentation( '' );
			}
		}
		return $lists;
	}

	/**
	 * Registra en el widget los controles unidos, ya procesados (sección, pestaña, grupo y dispositivo incluidos), con
	 * el gestor de controles del núcleo, sin volver a pasar por `add_control()`.
	 *
	 * @param object               $widget   Widget propio (en su `register_controls()`).
	 * @param array<string, array> $controls Controles unidos.
	 */
	public static function import( $widget, array $controls ): void {
		$manager = \Elementor\Plugin::$instance->controls_manager;
		foreach ( $controls as $id => $args ) {
			unset( $args['name'] );
			$manager->add_control_to_stack( $widget, (string) $id, $args );
		}
	}
}

/** `wp_json_encode()` si existe (en las pruebas sin WordPress, `json_encode()`). */
function wp_json_encode_safe( $value ): string {
	return (string) ( function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value ) );
}
