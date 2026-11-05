<?php

namespace ElementorPro\Modules\AssetsManager\AssetTypes\Icons\ImportExportCustomization;

use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Export_Runner_Base;
use ElementorPro\Modules\AssetsManager\AssetTypes\Icons_Manager;
use ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Export_Runner extends Export_Runner_Base {
	public static function get_name(): string {
		return 'custom-icons';
	}

	public function should_export( array $data ) {
		return (
			isset( $data['include'] ) &&
			in_array( 'settings', $data['include'], true )
		);
	}

	public function export( array $data ) {
		$icon_sets = $this->get_custom_icon_sets();

		$include_custom_icons = $data['customization']['settings']['customIcons'] ?? true;

		if ( empty( $icon_sets ) || ! $include_custom_icons ) {
			return [
				'manifest' => [],
				'files' => [],
			];
		}

		$icon_sets_data = [];
		$manifest = [];

		foreach ( $icon_sets as $icon_set ) {
			$icon_set_data = $this->prepare_icon_set_data( $icon_set );
			if ( $icon_set_data ) {
				$icon_sets_data[] = $icon_set_data;
				$manifest['custom-icons'][ $icon_set->ID ] = $icon_set_data;
			}
		}

		return [
			'files' => [
				'path' => Import_Export_Customization::FILE_NAME,
				'data' => $icon_sets_data,
			],
			'manifest' => [
				$manifest,
			],
		];
	}

	private function get_custom_icon_sets() {
		return get_posts( [
			'post_type' => Icons_Manager::CPT,
			'posts_per_page' => -1,
			'post_status' => 'publish',
		] );
	}

	/** Extensiones de los archivos de un conjunto que reconstruye la importación. */
	const DIGITALISIMO_SET_FILES = [ 'css', 'json', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg' ];

	/**
	 * DIGITALÍSIMO: URL de los archivos de un conjunto (hasta una subcarpeta), sólo si su carpeta está dentro de
	 * `uploads/elementor/custom-icons/` de este sitio.
	 *
	 * @param string $path Carpeta del conjunto (`_elementor_icon_set_path`).
	 *
	 * @return string[]
	 */
	private static function digitalisimo_set_files( string $path ): array {
		$uploads = wp_upload_dir( null, false );
		if ( '' === $path || ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return [];
		}
		$root = realpath( trailingslashit( $uploads['basedir'] ) . 'elementor/custom-icons' );
		$dir  = realpath( $path );
		if ( false === $root || false === $dir || ! is_dir( $dir ) ) {
			return [];
		}
		$root = trailingslashit( wp_normalize_path( $root ) );
		$dir  = trailingslashit( wp_normalize_path( $dir ) );
		if ( 0 !== strpos( $dir, $root ) || $dir === $root ) {
			return [];
		}
		$base  = trailingslashit( $uploads['baseurl'] ) . 'elementor/custom-icons/' . substr( $dir, strlen( $root ) );
		$urls  = [];
		$stack = [ '' ];
		while ( $stack && count( $urls ) < 200 ) {
			$sub = array_shift( $stack );
			foreach ( (array) scandir( $dir . $sub ) as $name ) {
				if ( '.' === $name || '..' === $name || 1 !== preg_match( '/^[A-Za-z0-9 ._-]+$/', (string) $name ) ) {
					continue;
				}
				$file = $dir . $sub . $name;
				if ( is_dir( $file ) && ! is_link( $file ) ) {
					if ( '' === $sub ) {
						$stack[] = $name . '/';
					}
					continue;
				}
				if ( is_file( $file ) && in_array( strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ), self::DIGITALISIMO_SET_FILES, true ) ) {
					$urls[] = $base . $sub . rawurlencode( $name );
				}
			}
		}
		sort( $urls, SORT_STRING );

		return $urls;
	}

	private function prepare_icon_set_data( $icon_set ) {
		$icon_set_config = Custom_Icons::get_icon_set_config( $icon_set->ID );
		$icon_set_path = get_post_meta( $icon_set->ID, '_elementor_icon_set_path', true );

		$icon_type = '';
		if ( $icon_set_config ) {
			$config_data = json_decode( $icon_set_config, true );
			$icon_type = $config_data['custom_icon_type'] ?? '';
		}

		$attachments = get_attached_media( '', $icon_set->ID );
		$attachments_data = [];

		foreach ( $attachments as $attachment ) {
			$url = wp_get_attachment_url( $attachment->ID );
			$attachments_data[] = [
				'id' => $attachment->ID,
				'title' => $attachment->post_title,
				'url' => $url,
			];

			do_action( 'elementor/templates/collect_media_url', $url, (array) $attachment );
		}

		// DIGITALÍSIMO: los archivos del conjunto viajan aunque no sean adjuntos (al subir un conjunto sólo se extrae su
		// ZIP en la carpeta): la importación los pide por su URL y el proxy de kits los sirve desde el paquete.
		$known = array_column( $attachments_data, 'url' );
		foreach ( self::digitalisimo_set_files( is_string( $icon_set_path ) ? $icon_set_path : '' ) as $file_url ) {
			if ( in_array( $file_url, $known, true ) ) {
				continue;
			}
			$known[] = $file_url;
			$attachments_data[] = [
				'id' => 0,
				'title' => '',
				'url' => $file_url,
			];
			do_action( 'elementor/templates/collect_media_url', $file_url, [] );
		}

		return [
			'ID' => $icon_set->ID,
			'post_title' => $icon_set->post_title,
			'post_content' => $icon_set->post_content,
			'post_status' => $icon_set->post_status,
			'icon_set_config' => $icon_set_config,
			'icon_set_path' => $icon_set_path,
			'icon_type' => $icon_type,
			'attachments' => $attachments_data,
		];
	}
}
