<?php
namespace Digitalisimo\Elements\F7b\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * El servidor ignora lo oculto (F7b, frente J; V.9, C1-2 y criterio 11).
 *
 * `Form_Record::validate()` ya añadió el error de obligatorio vacío (`modules/forms/classes/form-record.php:110-111`)
 * y corrió `elementor_pro/forms/validation/{tipo}` de cada campo antes de `elementor_pro/forms/validation` (`:142`), y
 * `Ajax_Handler::add_error()` dejó el éxito en falso (`ajax-handler.php:248-256`). En ese gancho, con prioridad 5:
 *
 * 1. las reglas se recalculan con lo enviado (`raw_value` de cada campo de `$record->get( 'fields' )`, el mismo texto
 *    que lee el navegador; los nombres de los archivos de una subida), nunca con lo que diga el navegador sobre qué
 *    estaba oculto;
 * 2. cada campo oculto sale del registro (`remove_field()`) y de los datos enviados (`sent_data`, que lee la respuesta
 *    al remitente del correo), y su error de `$ajax_handler->errors` se borra: así no llega a ninguna acción (correo,
 *    webhook, guardado de envíos) ni se procesa (`process_fields()` sólo recorre los campos del registro, y una
 *    subida oculta no se guarda);
 * 3. si no queda ningún error ni mensaje de error, `set_success( true )`.
 *
 * Un formulario sin campos condicionales no cambia nada.
 */
final class Validation {
	/**
	 * `elementor_pro/forms/validation` (prioridad 5).
	 *
	 * @param object $record       `Form_Record`.
	 * @param object $ajax_handler `Ajax_Handler`.
	 * @return string[] IDs de los campos ocultos que se quitaron.
	 */
	public static function validate( $record, $ajax_handler ): array {
		if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) || ! method_exists( $record, 'remove_field' ) ) {
			return array();
		}
		$settings = $record->get( 'form_settings' );
		$defs     = Rules::defs( is_array( $settings ) && isset( $settings['form_fields'] ) ? $settings['form_fields'] : array() );
		if ( ! $defs ) {
			return array();
		}
		$hidden = Rules::hidden( $defs, self::values( $record ) );
		if ( ! $hidden ) {
			return array();
		}
		$sent = $record->get( 'sent_data' );
		foreach ( $hidden as $id ) {
			$record->remove_field( $id );
			if ( is_array( $sent ) ) {
				unset( $sent[ $id ] );
			}
			if ( is_object( $ajax_handler ) && isset( $ajax_handler->errors ) && is_array( $ajax_handler->errors ) ) {
				unset( $ajax_handler->errors[ $id ] );
			}
		}
		if ( is_array( $sent ) && method_exists( $record, 'set' ) ) {
			$record->set( 'sent_data', $sent );
		}
		if ( is_object( $ajax_handler ) && method_exists( $ajax_handler, 'set_success' ) && empty( $ajax_handler->errors ) && self::no_messages( $ajax_handler ) ) {
			$ajax_handler->set_success( true );
		}
		return $hidden;
	}

	/** ¿Sin mensajes de error para el visitante ni para el administrador? */
	private static function no_messages( $ajax_handler ): bool {
		$messages = isset( $ajax_handler->messages ) && is_array( $ajax_handler->messages ) ? $ajax_handler->messages : array();
		return empty( $messages['error'] ) && empty( $messages['admin_error'] );
	}

	/**
	 * Valores crudos por ID: `raw_value` de cada campo y, en una subida, los nombres de sus archivos.
	 *
	 * @param object $record `Form_Record`.
	 * @return array<string,mixed>
	 */
	public static function values( $record ): array {
		$out    = array();
		$fields = $record->get( 'fields' );
		foreach ( is_array( $fields ) ? $fields : array() as $id => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$out[ (string) $id ] = 'upload' === ( $field['type'] ?? '' ) ? self::upload_names( (string) $id ) : ( $field['raw_value'] ?? '' );
		}
		return $out;
	}

	/**
	 * Nombres de los archivos enviados en una subida, tanto en la forma de PHP (`name[id]`) como en la que deja
	 * `Upload::fix_file_indices()` (`id[n][name]`), que ya corrió en `elementor_pro/forms/validation/upload`.
	 */
	public static function upload_names( string $id ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sólo se leen los nombres; PRO ya validó el envío.
		$files = isset( $_FILES['form_fields'] ) && is_array( $_FILES['form_fields'] ) ? $_FILES['form_fields'] : array();
		$names = array();
		if ( isset( $files['name'] ) && is_array( $files['name'] ) && isset( $files['name'][ $id ] ) ) {
			$names = (array) $files['name'][ $id ];
		} elseif ( isset( $files[ $id ] ) && is_array( $files[ $id ] ) ) {
			foreach ( $files[ $id ] as $file ) {
				if ( is_array( $file ) && isset( $file['name'] ) ) {
					$names[] = $file['name'];
				}
			}
		}
		$out = array();
		foreach ( $names as $name ) {
			if ( is_string( $name ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}
}
