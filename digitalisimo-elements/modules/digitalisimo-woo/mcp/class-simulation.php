<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Simulación de una escritura (`dry_run: true`): dice qué cambiaría sin escribir nada (tanda B de la auditoría de
 * digitalisimo.lat, 2026-11-24).
 *
 * Sólo hace lecturas (`GET`) a la API REST v3 de WooCommerce con el mismo despachador que la ejecución, así que
 * WooCommerce vuelve a comprobar el permiso de lectura objeto a objeto. Nunca manda `POST`, `PUT` ni `DELETE`.
 *
 * - **update**: lee el objeto y da, campo por campo de los enviados, el valor actual y el nuevo (`changes`) y los que no
 *   cambiarían (`unchanged`). En un mapa `settings` (pasarela, método de envío) baja un nivel. Un secreto nunca sale:
 *   su valor actual y el nuevo se dan como la máscara, y uno vacío o enmascarado se conservaría (como al escribir).
 * - **create**: lo que se crearía (los campos enviados, con los secretos enmascarados) y lo que WooCommerce haría por su
 *   cuenta (publicar un producto sin `status`, devolver el dinero con `api_refund`).
 * - **delete**: qué objeto (sus campos de identificación, sin datos personales) y si iría a la papelera o se borraría
 *   para siempre.
 * - **batch**: el recuento por lista y, de cada `update` con ID, su diferencia (leída una a una, como mucho
 *   `Tools::BATCH_MAX`); los `delete` de un lote son siempre definitivos.
 */
final class Simulation {
	/** Campos de identificación de un objeto que se va a borrar (ningún dato personal: ni nombres de personas ni correos). */
	const SUMMARY = array( 'id', 'name', 'slug', 'sku', 'code', 'status', 'type', 'number', 'parent_id', 'product_id', 'title', 'method_id', 'count', 'date_created' );

	/**
	 * Lo que cambiaría la escritura del plan.
	 *
	 * @param string   $tool     Herramienta.
	 * @param array    $plan     Plan del enrutador (kind `write`).
	 * @param callable $dispatch Despachador (`Executor::rest()` o el de las pruebas).
	 * @return array|\WP_Error
	 */
	public static function would( string $tool, array $plan, $dispatch ) {
		$action = $plan['action'];
		if ( 'create' === $action ) {
			$would = array(
				'create' => $plan['resource'],
				'fields' => self::show_body( $tool, $plan['body'] ),
			);
			if ( in_array( $plan['resource'], array( 'product', 'variation' ), true ) && is_array( $plan['body'] ) && ! array_key_exists( 'status', $plan['body'] ) ) {
				$would['note'] = 'Sin status, WooCommerce lo crearía publicado.';
			}
			if ( 'refund' === $plan['resource'] ) {
				$would['note'] = ! empty( $plan['body']['api_refund'] ) ? 'Se pediría a la pasarela de pago que devuelva el dinero (api_refund: true).' : 'Se registraría el reembolso sin mover dinero (api_refund: false).';
			}
			return $would;
		}
		if ( 'delete' === $action ) {
			list( $status, $current ) = call_user_func( $dispatch, 'GET', $plan['route'], array(), null );
			if ( $status >= 400 ) {
				return Executor::woo_error( (int) $status, $current );
			}
			return array(
				'delete' => self::summary( $current ),
				'mode'   => ! empty( $plan['query']['force'] ) ? 'permanent' : 'trash',
			);
		}
		if ( 'batch' === $action ) {
			return self::batch( $tool, $plan, $dispatch );
		}
		// update.
		list( $status, $current ) = call_user_func( $dispatch, 'GET', $plan['route'], array(), null );
		if ( $status >= 400 ) {
			return Executor::woo_error( (int) $status, $current );
		}
		if ( 'shipping_zone_location' === $plan['resource'] ) {
			// La lista entera reemplaza a la actual.
			return array(
				'replace' => 'locations',
				'from'    => $current,
				'to'      => $plan['body'],
			);
		}
		$secrets = '' !== $plan['secrets'] ? Secrets::secret_ids( $current ) : array();
		return self::diff( is_array( $current ) ? $current : array(), is_array( $plan['body'] ) ? $plan['body'] : array(), $secrets, 'option' === $plan['secrets'] );
	}

	/**
	 * Diferencia campo a campo entre lo actual y lo enviado (sólo las claves enviadas).
	 *
	 * @param array $current    Objeto actual de WooCommerce.
	 * @param array $body       Campos enviados.
	 * @param array $secret_ids IDs secretos (de `Secrets::secret_ids()`).
	 * @param bool  $option     Una opción de ajustes: `value` es secreto si la opción lo es.
	 * @return array{changes:array,unchanged:array}
	 */
	public static function diff( array $current, array $body, array $secret_ids = array(), bool $option = false ): array {
		$out = array(
			'changes'   => array(),
			'unchanged' => array(),
		);
		foreach ( $body as $key => $to ) {
			$key = (string) $key;
			if ( 'settings' === $key && is_array( $to ) && isset( $current['settings'] ) && is_array( $current['settings'] ) ) {
				foreach ( $to as $sub => $value ) {
					$sub    = (string) $sub;
					$field  = isset( $current['settings'][ $sub ] ) && is_array( $current['settings'][ $sub ] ) ? $current['settings'][ $sub ] : null;
					$from   = is_array( $field ) && array_key_exists( 'value', $field ) ? $field['value'] : null;
					$secret = in_array( $sub, $secret_ids, true ) || Secrets::is_secret_name( $sub ) || ( is_array( $field ) && Secrets::is_secret_field( $field, $sub ) );
					self::push( $out, 'settings.' . $sub, $from, $value, $secret );
				}
				continue;
			}
			$from   = array_key_exists( $key, $current ) ? $current[ $key ] : null;
			$secret = ( $option && 'value' === $key && $secret_ids ) || ( ! $option && in_array( $key, $secret_ids, true ) );
			self::push( $out, $key, $from, $to, $secret );
		}
		return $out;
	}

	/** Un campo en `changes` o en `unchanged`; un secreto con la máscara y, si llega vacío o enmascarado, se conserva. */
	private static function push( array &$out, string $field, $from, $to, bool $secret ): void {
		if ( $secret ) {
			if ( Secrets::keeps_stored( $to ) ) {
				$out['unchanged'][] = $field;
				return;
			}
			$out['changes'][] = array(
				'field'  => $field,
				'from'   => ( null === $from || '' === $from ) ? '' : Secrets::MASK,
				'to'     => Secrets::MASK,
				'masked' => true,
			);
			return;
		}
		if ( self::same( $from, $to ) ) {
			$out['unchanged'][] = $field;
			return;
		}
		$out['changes'][] = array(
			'field' => $field,
			'from'  => $from,
			'to'    => $to,
		);
	}

	/** Un lote: recuento por lista y la diferencia de cada `update` con ID. */
	private static function batch( string $tool, array $plan, $dispatch ) {
		$body  = is_array( $plan['body'] ) ? $plan['body'] : array();
		$base  = substr( $plan['route'], 0, -strlen( '/batch' ) );
		$would = array(
			'create' => isset( $body['create'] ) && is_array( $body['create'] ) ? count( $body['create'] ) : 0,
			'update' => array(),
			'delete' => isset( $body['delete'] ) && is_array( $body['delete'] ) ? array_values( $body['delete'] ) : array(),
		);
		if ( $would['create'] ) {
			$would['create_fields'] = array();
			foreach ( $body['create'] as $item ) {
				$would['create_fields'][] = self::show_body( $tool, $item );
			}
		}
		if ( $would['delete'] ) {
			$would['delete_mode'] = 'permanent';
		}
		$group = null;
		$done  = 0;
		foreach ( isset( $body['update'] ) && is_array( $body['update'] ) ? $body['update'] : array() as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['id'] ) || $done >= Tools::BATCH_MAX ) {
				continue;
			}
			++$done;
			$id     = $item['id'];
			$fields = $item;
			unset( $fields['id'] );
			if ( 'option' === $plan['secrets'] ) {
				// Opciones de un grupo: el grupo se lee una vez.
				if ( null === $group ) {
					list( $status, $group ) = call_user_func( $dispatch, 'GET', $base, array(), null );
					if ( $status >= 400 ) {
						return Executor::woo_error( (int) $status, $group );
					}
					$group = is_array( $group ) ? $group : array();
				}
				$current = array();
				foreach ( $group as $option ) {
					if ( is_array( $option ) && isset( $option['id'] ) && (string) $option['id'] === (string) $id ) {
						$current = $option;
					}
				}
				$diff = self::diff( $current, $fields, Secrets::secret_ids( $current ), true );
			} else {
				if ( ! is_int( $id ) && ! ( is_string( $id ) && 1 === preg_match( '/^[0-9]{1,18}$/', $id ) ) ) {
					$would['update'][] = array( 'id' => null, 'error' => 'ID no válido: WooCommerce lo rechazaría.' );
					continue;
				}
				list( $status, $current ) = call_user_func( $dispatch, 'GET', $base . '/' . (int) $id, array(), null );
				if ( $status >= 400 ) {
					$error             = Executor::woo_error( (int) $status, $current );
					$would['update'][] = array( 'id' => (int) $id, 'error' => $error->get_error_message() );
					continue;
				}
				$diff = self::diff( is_array( $current ) ? $current : array(), $fields );
			}
			$would['update'][] = array( 'id' => is_string( $id ) && ! is_numeric( $id ) ? $id : (int) $id ) + $diff;
		}
		return $would;
	}

	/** Campos de un objeto que se borraría, sin datos personales. */
	public static function summary( $current ): array {
		if ( ! is_array( $current ) ) {
			return array();
		}
		return array_intersect_key( $current, array_flip( self::SUMMARY ) );
	}

	/** Cuerpo de una creación para mostrar: en los ajustes, los secretos con la máscara. */
	private static function show_body( string $tool, $body ) {
		if ( 'store-settings' !== $tool || ! is_array( $body ) ) {
			return $body;
		}
		foreach ( $body as $key => $value ) {
			if ( is_string( $key ) && Secrets::is_secret_name( $key ) && ! Secrets::keeps_stored( $value ) ) {
				$body[ $key ] = Secrets::MASK;
			} elseif ( 'settings' === $key && is_array( $value ) ) {
				foreach ( $value as $sub => $item ) {
					if ( Secrets::is_secret_name( (string) $sub ) && ! Secrets::keeps_stored( $item ) ) {
						$body['settings'][ $sub ] = Secrets::MASK;
					}
				}
			}
		}
		return $body;
	}

	/**
	 * ¿Son iguales para WooCommerce? Números (también en texto: `"10"` y `10.00`), booleanos (también `"yes"`/`"no"`),
	 * nulos y vacíos, y listas u objetos campo a campo.
	 */
	public static function same( $a, $b ): bool {
		return self::norm( $a ) === self::norm( $b );
	}

	/** Forma comparable de un valor de la REST de WooCommerce. */
	public static function norm( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $child ) {
				$out[ $key ] = self::norm( $child );
			}
			if ( array_keys( $out ) !== range( 0, count( $out ) - 1 ) ) {
				ksort( $out );
			}
			return $out;
		}
		if ( null === $value || '' === $value ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'b:1' : 'b:0';
		}
		if ( is_string( $value ) && in_array( strtolower( $value ), array( 'yes', 'true' ), true ) ) {
			return 'b:1';
		}
		if ( is_string( $value ) && in_array( strtolower( $value ), array( 'no', 'false' ), true ) ) {
			return 'b:0';
		}
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			return 'n:' . rtrim( rtrim( sprintf( '%.6F', (float) $value ), '0' ), '.' );
		}
		return 's:' . (string) $value;
	}
}
