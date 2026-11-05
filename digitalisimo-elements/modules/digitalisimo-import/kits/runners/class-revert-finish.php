<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Parts;
use Digitalisimo\Elements\Import\Kits\Revert_Context;
use Digitalisimo\Elements\Import\Kits\Session;
use Elementor\App\Modules\ImportExportCustomization\Runners\Revert\Revert_Runner_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Runner final de reversión `digitalisimo` (§7.7): corre después de los nativos (que mandan los documentos a la
 * papelera y borran lo del WXR).
 *
 * - `Part::revert()` de cada parte, en orden inverso, con lo que guardó al importar;
 * - recuenta los marcadores del sitio;
 * - lista los documentos que fueron a la papelera (D16) y marca la sesión como revertida.
 */
class Revert_Finish extends Revert_Runner_Base {
	public static function get_name(): string {
		return Module::RUNNER;
	}

	public function should_revert( array $data ): bool {
		return isset( $data['runners'][ Module::RUNNER ] ) && is_array( $data['runners'][ Module::RUNNER ] );
	}

	public function revert( array $data ) {
		global $wpdb;
		$session = (string) ( $data['session_id'] ?? '' );
		$record  = Session::get( $session );
		if ( null === $record ) {
			return;
		}
		$context = new Revert_Context(
			array(
				'session' => $session,
				'mode'    => (string) ( $record['mode'] ?? Module::MODE_ADD ),
				'user'    => get_current_user_id(),
				'kit_id'  => (int) ( $record['kit_id'] ?? 0 ),
			)
		);
		$stored = isset( $record['stored'] ) && is_array( $record['stored'] ) ? $record['stored'] : array();
		foreach ( array_reverse( Parts::all(), true ) as $key => $part ) {
			$context->begin_part( (string) $key );
			try {
				$part->revert( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array(), $context );
			} catch ( \Throwable $error ) {
				$context->report( 'part.error', array( 'message' => $error->getMessage() ), Part_Context::ERROR );
			}
			$context->end_part();
		}
		Finish::count_markers();

		$trashed = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE m.meta_key = '_elementor_import_session_id' AND m.meta_value = %s AND p.post_status = 'trash' LIMIT 200", $session ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $trashed ) {
			$context->report( 'revert.trashed', array( 'posts' => $trashed ), Part_Context::INFO );
		}
		$record = (array) Session::get( $session );
		Session::update(
			$session,
			array(
				'status'   => Session::STATUS_REVERTED,
				'reverted' => time(),
				'trashed'  => $trashed,
				'report'   => Session::merge_report( isset( $record['report'] ) && is_array( $record['report'] ) ? $record['report'] : array(), $context->get_report() ),
			)
		);
		if ( ! empty( $record['token'] ) ) {
			Session::delete_tmp( (string) $record['token'] );
		}
	}
}
