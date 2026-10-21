<?php

namespace ElementorPro\Modules\AssetsManager\AssetTypes\Icons\ImportExportCustomization;

use Elementor\App\Modules\ImportExportCustomization\Runners\Revert\Revert_Runner_Base;
use Elementor\Plugin;
use ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Revert_Runner extends Revert_Runner_Base {

	public static function get_name(): string {
		return 'custom-icons';
	}

	public function should_revert( array $data ): bool {
		return (
			isset( $data['runners'] ) &&
			array_key_exists( static::get_name(), $data['runners'] )
		);
	}

	public function revert( array $data ) {
		$metadata = $data['runners'][ static::get_name() ] ?? [];

		if ( empty( $metadata ) ) {
			return;
		}

		$this->revert_imported_icon_sets( $metadata );

		$this->revert_attachments( $data['session_id'] );

		Custom_Icons::clear_icon_list_option();
		// DIGITALÍSIMO: la caché de conjuntos se rehace con los que quedan, como estaba antes de importar (A10).
		Custom_Icons::get_custom_icons_config();
		self::digitalisimo_tidy_folder();
	}

	/**
	 * DIGITALÍSIMO: sin conjuntos, la carpeta `uploads/elementor/custom-icons/` que creó la importación (sólo con su
	 * `index.php` y su `.htaccess`) no se queda en el sitio tras revertir (A10).
	 */
	private static function digitalisimo_tidy_folder(): void {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'elementor/custom-icons';
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}
		if ( array_diff( (array) scandir( $dir ), [ '.', '..', 'index.php', '.htaccess' ] ) ) {
			return;
		}
		foreach ( [ 'index.php', '.htaccess' ] as $file ) {
			if ( is_file( $dir . '/' . $file ) && ! is_link( $dir . '/' . $file ) ) {
				wp_delete_file( $dir . '/' . $file );
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	private function revert_imported_icon_sets( $metadata ) {
		$imported_icon_sets = $metadata['imported_icon_sets'] ?? [];

		foreach ( $imported_icon_sets as $icon_set ) {
			$this->delete_icon_set_and_attachments( $icon_set['id'] );
		}
	}

	private function revert_attachments( $session_id ) {
		$attachments_query = new \WP_Query( [
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => static::META_KEY_ELEMENTOR_IMPORT_SESSION_ID,
					'value' => $session_id,
					'compare' => '=',
				],
			],
		] );

		if ( ! $attachments_query->have_posts() ) {
			return;
		}

		foreach ( $attachments_query->posts as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}
	}

	private function delete_icon_set_and_attachments( $icon_set_id ) {
		$icon_set_path = get_post_meta( $icon_set_id, '_elementor_icon_set_path', true );
		if ( $icon_set_path && is_dir( $icon_set_path ) ) {
			Plugin::$instance->uploads_manager->remove_file_or_dir( $icon_set_path );
		}

		$attachments = get_attached_media( '', $icon_set_id );

		foreach ( $attachments as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}

		wp_delete_post( $icon_set_id, true );
	}
}
