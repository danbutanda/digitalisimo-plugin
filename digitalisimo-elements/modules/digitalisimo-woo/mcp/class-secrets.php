<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Secretos de los ajustes de la tienda (pasarelas de pago, métodos de envío y opciones de ajustes), puro.
 *
 * WooCommerce describe cada campo con `id`, `type` y `value`. Es secreto todo campo de tipo `password` y, por si una
 * extensión guarda una clave en un campo de texto (el `identity_token` de PayPal Standard, p. ej.), todo campo cuyo ID
 * nombra una contraseña, un secreto, un token, una firma o una clave de API. Al leer, su valor se cambia por `MASK` y
 * el campo lleva `"masked": true`; nunca sale el valor ni una parte de él. Al escribir, un valor nuevo se guarda, pero
 * uno vacío o la máscara (lo que el cliente recibió) se quitan del envío: el valor guardado se conserva.
 */
final class Secrets {
	const MASK = '••••••••';

	const NAME_PATTERN = '/(password|passwd|secret|token|signature|api[_-]?key|private[_-]?key|access[_-]?key|license[_-]?key|auth[_-]?key)/i';

	/** ¿Es secreto este campo? `$key` es su clave en el mapa `settings` cuando no trae `id`. */
	public static function is_secret_field( array $field, string $key = '' ): bool {
		$type = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : '';
		$id   = isset( $field['id'] ) && is_string( $field['id'] ) ? $field['id'] : $key;
		return 'password' === $type || ( '' !== $id && 1 === preg_match( self::NAME_PATTERN, $id ) );
	}

	/** Un campo de ajustes de WooCommerce: lleva `type` y `value`. */
	private static function is_field( array $value ): bool {
		return array_key_exists( 'type', $value ) && array_key_exists( 'value', $value ) && is_string( $value['type'] );
	}

	/** Enmascara, en cualquier profundidad, el valor de los campos secretos de una respuesta. */
	public static function mask( $data, string $key = '' ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		if ( self::is_field( $data ) && self::is_secret_field( $data, $key ) ) {
			$set            = ! ( null === $data['value'] || '' === $data['value'] || array() === $data['value'] );
			$data['value']  = $set ? self::MASK : '';
			$data['masked'] = true;
			if ( isset( $data['default'] ) && is_string( $data['default'] ) && '' !== $data['default'] ) {
				$data['default'] = self::MASK;
			}
			return $data;
		}
		foreach ( $data as $child_key => $child ) {
			$data[ $child_key ] = self::mask( $child, is_string( $child_key ) ? $child_key : '' );
		}
		return $data;
	}

	/** ¿Es un valor que no cambia un secreto: vacío, nulo o la máscara (también escrita con asteriscos o puntos)? */
	public static function keeps_stored( $value ): bool {
		if ( null === $value || false === $value ) {
			return true;
		}
		if ( ! is_string( $value ) ) {
			return false;
		}
		$value = trim( $value );
		return '' === $value || self::MASK === $value || 1 === preg_match( '/^[•*·]+$/u', $value );
	}

	/**
	 * IDs de los campos secretos de una respuesta de WooCommerce: un mapa `settings` (pasarela o método de envío), una
	 * opción suelta o la lista de opciones de un grupo.
	 */
	public static function secret_ids( $current ): array {
		$ids = array();
		if ( ! is_array( $current ) ) {
			return $ids;
		}
		if ( isset( $current['settings'] ) && is_array( $current['settings'] ) ) {
			foreach ( $current['settings'] as $key => $field ) {
				if ( is_array( $field ) && self::is_secret_field( $field, (string) $key ) ) {
					$ids[] = (string) $key;
				}
			}
			return $ids;
		}
		if ( self::is_field( $current ) ) {
			return isset( $current['id'] ) && is_string( $current['id'] ) && self::is_secret_field( $current ) ? array( $current['id'] ) : array();
		}
		foreach ( $current as $field ) {
			if ( is_array( $field ) && self::is_field( $field ) && isset( $field['id'] ) && is_string( $field['id'] ) && self::is_secret_field( $field ) ) {
				$ids[] = $field['id'];
			}
		}
		return $ids;
	}

	/**
	 * Cuerpo de una escritura sin los secretos que no cambian. `$kind`: `map` (`settings` de una pasarela o un método
	 * de envío), `option` (una opción: `value`) u `option-batch` (`update` de un grupo). Devuelve el cuerpo y cuántos
	 * campos se conservaron.
	 *
	 * @return array{0:array,1:int}
	 */
	public static function strip_unchanged( array $body, array $secret_ids, string $kind ): array {
		$kept = 0;
		if ( 'map' === $kind && isset( $body['settings'] ) && is_array( $body['settings'] ) ) {
			foreach ( $body['settings'] as $key => $value ) {
				$name = (string) $key;
				if ( ( in_array( $name, $secret_ids, true ) || 1 === preg_match( self::NAME_PATTERN, $name ) ) && self::keeps_stored( $value ) ) {
					unset( $body['settings'][ $key ] );
					++$kept;
				}
			}
			if ( ! $body['settings'] ) {
				unset( $body['settings'] );
			}
		} elseif ( 'option' === $kind && $secret_ids && array_key_exists( 'value', $body ) && self::keeps_stored( $body['value'] ) ) {
			unset( $body['value'] );
			++$kept;
		} elseif ( 'option-batch' === $kind && isset( $body['update'] ) && is_array( $body['update'] ) ) {
			$update = array();
			foreach ( $body['update'] as $item ) {
				$id = is_array( $item ) && isset( $item['id'] ) && is_string( $item['id'] ) ? $item['id'] : '';
				if ( '' !== $id && in_array( $id, $secret_ids, true ) && self::keeps_stored( array_key_exists( 'value', $item ) ? $item['value'] : null ) ) {
					++$kept;
					continue;
				}
				$update[] = $item;
			}
			$body['update'] = $update;
		}
		return array( $body, $kept );
	}
}
