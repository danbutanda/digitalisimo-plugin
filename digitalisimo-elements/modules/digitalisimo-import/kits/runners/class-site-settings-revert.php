<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Session;
use Elementor\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Runner de reversión `site-settings` de K (§7.7, CR-11).
 *
 * - Una sesión sin el puntero de K (anterior a K) delega en el nativo.
 * - Si los ajustes del kit activo siguen como los dejó la importación, restaura `settings_before` con
 *   `$kit->save()`; si no, quita sólo los globales que añadió y lo anota.
 * - Las metas del kit (de las partes y v4) se restauran sólo si nadie las cambió después (`metas_after`).
 * La metadata nativa de K es inerte (`active_kit_id = 0`): `kits_manager->revert()` aborta sin borrar nada, y
 * `revert_theme()` y `revert_experiments()` no hacen nada (sin tema ni experimentos anotados).
 */
class Site_Settings_Revert extends \Elementor\App\Modules\ImportExportCustomization\Runners\Revert\Site_Settings {
	public function revert( array $data ) {
		$pointer = isset( $data['runners'][ Module::RUNNER ] ) && is_array( $data['runners'][ Module::RUNNER ] ) ? $data['runners'][ Module::RUNNER ] : array();
		if ( empty( $pointer['mode'] ) ) {
			parent::revert( $data );
			return;
		}
		$session = (string) ( $data['session_id'] ?? '' );
		$record  = Session::get( $session );
		$kit     = Plugin::$instance->kits_manager->get_active_kit();
		if ( null === $record || ! $kit || ! $kit->get_id() ) {
			return;
		}
		$backup  = isset( $record['backup'] ) && is_array( $record['backup'] ) ? $record['backup'] : array();
		$context = new Revert_Context(
			array(
				'session' => $session,
				'mode'    => (string) ( $record['mode'] ?? Module::MODE_ADD ),
				'user'    => get_current_user_id(),
				'kit_id'  => (int) $kit->get_id(),
			)
		);
		if ( (int) ( $backup['kit_id'] ?? 0 ) !== (int) $kit->get_id() ) {
			$context->report( 'revert.kit_changed', array(), Part_Context::WARNING );
			self::save_report( $session, $context );
			return;
		}
		$current = $kit->get_meta( '_elementor_page_settings' );
		$current = is_array( $current ) ? $current : array();
		if ( $context->unchanged( $current, (string) ( $backup['hash_after'] ?? '' ) ) ) {
			Site_Settings::save_kit( $kit, isset( $backup['settings_before'] ) && is_array( $backup['settings_before'] ) ? $backup['settings_before'] : array(), true );
			$context->report( 'revert.settings', array( 'restored' => true ) );
		} else {
			$removed = 0;
			foreach ( array( 'custom_colors', 'custom_typography' ) as $key ) {
				$added = isset( $backup['added'][ $key ] ) && is_array( $backup['added'][ $key ] ) ? $backup['added'][ $key ] : array();
				if ( ! $added || empty( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
					continue;
				}
				$before          = count( $current[ $key ] );
				$current[ $key ] = array_values(
					array_filter(
						$current[ $key ],
						function ( $item ) use ( $added ) {
							return ! ( is_array( $item ) && isset( $item['_id'] ) && in_array( (string) $item['_id'], $added, true ) );
						}
					)
				);
				$removed        += $before - count( $current[ $key ] );
			}
			if ( $removed > 0 ) {
				Site_Settings::save_kit( $kit, $current, true );
			}
			$context->report( 'revert.settings_partial', array( 'removed' => $removed ), Part_Context::WARNING );
		}

		$after = isset( $backup['metas_after'] ) && is_array( $backup['metas_after'] ) ? $backup['metas_after'] : array();
		foreach ( array( 'metas_before', 'v4_before' ) as $group ) {
			foreach ( isset( $backup[ $group ] ) && is_array( $backup[ $group ] ) ? $backup[ $group ] : array() as $key => $value ) {
				$key = (string) $key;
				$now = metadata_exists( 'post', (int) $kit->get_id(), $key ) ? get_post_meta( (int) $kit->get_id(), $key, true ) : null;
				if ( ! isset( $after[ $key ] ) || ! $context->unchanged( $now, (string) $after[ $key ] ) ) {
					if ( Part_Context::hash( $now ) !== Part_Context::hash( $value ) ) {
						$context->report( 'revert.meta_kept', array( 'meta' => $key ), Part_Context::WARNING );
					}
					continue;
				}
				if ( null === $value ) {
					delete_post_meta( (int) $kit->get_id(), $key );
				} else {
					update_post_meta( (int) $kit->get_id(), $key, wp_slash( $value ) );
				}
			}
		}
		self::save_report( $session, $context );
	}

	private static function save_report( string $session, Revert_Context $context ): void {
		$record = Session::get( $session );
		if ( null !== $record ) {
			Session::update( $session, array( 'report' => Session::merge_report( isset( $record['report'] ) && is_array( $record['report'] ) ? $record['report'] : array(), $context->get_report() ) ) );
		}
	}
}
