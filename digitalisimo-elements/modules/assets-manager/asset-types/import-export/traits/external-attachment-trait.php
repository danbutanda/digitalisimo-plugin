<?php

namespace ElementorPro\Modules\AssetsManager\AssetTypes\ImportExport\Traits;

use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adjuntos de fuentes e iconos personalizados al importar un kit.
 *
 * DIGITALÍSIMO: un adjunto nunca queda apuntando al dominio del autor del kit (antes se creaba un adjunto vacío con
 * `_external_url` y un filtro que servía la URL ajena). Sin archivo local, se descarga con `wp_safe_remote_get()`
 * —que, durante una importación de kits, pasa por el proxy de medios de DIGITALÍSIMO Elements y sus límites— y se
 * crea un adjunto **local**. Si la descarga falla, el adjunto se omite y se anota en el informe del kit.
 */
trait External_Attachment_Trait {
	/** Extensiones que pueden traer las fuentes y los conjuntos de iconos. */
	private static $digitalisimo_attachment_types = [
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf' => 'font/ttf',
		'otf' => 'font/otf',
		'eot' => 'application/vnd.ms-fontobject',
		'svg' => 'image/svg+xml',
		'css' => 'text/css',
		'json' => 'application/json',
	];

	/** @var array[] URL vieja => `{ id, url }` de lo creado en esta importación. */
	private $digitalisimo_attachments = [];

	private function create_attachment_from_url( $parent_id, $attachment_data ) {
		$url   = isset( $attachment_data['url'] ) && is_string( $attachment_data['url'] ) ? $attachment_data['url'] : '';
		$title = isset( $attachment_data['title'] ) && is_scalar( $attachment_data['title'] ) ? sanitize_text_field( (string) $attachment_data['title'] ) : '';
		if ( '' === $url ) {
			return false;
		}

		$local_file_path = \Elementor\TemplateLibrary\Classes\Media_Mapper::get_local_file_path( $url );
		$imported        = false;

		if ( $local_file_path !== $url && file_exists( $local_file_path ) ) {
			$imported = Plugin::$instance->templates_manager->get_import_images_instance()->import_local_file( $local_file_path, $parent_id );
		}

		if ( ! $imported ) {
			$imported = $this->digitalisimo_download_attachment( $url, (int) $parent_id );
		}

		if ( ! $imported || empty( $imported['id'] ) ) {
			$this->digitalisimo_report( 'assets.attachment_failed', [ 'url' => $url ] );
			return false;
		}

		wp_update_post( [
			'ID' => $imported['id'],
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'inherit',
			'post_parent' => $parent_id,
		] );

		$this->set_session_post_meta( $imported['id'], $this->session_id );
		// DIGITALÍSIMO: la misma huella que los medios del kit, para que la parte `media` reutilice este adjunto en
		// lugar de crear otro con el mismo archivo.
		update_post_meta( $imported['id'], '_elementor_source_image_hash', sha1( $url ) );
		$this->digitalisimo_attachments[ $url ] = [
			'id' => (int) $imported['id'],
			'url' => (string) wp_get_attachment_url( $imported['id'] ),
		];

		return $imported['id'];
	}

	/**
	 * Descarga un archivo del kit y lo guarda como adjunto local.
	 *
	 * @return array|false `{ id, url }` o false.
	 */
	private function digitalisimo_download_attachment( string $url, int $parent_id ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = sanitize_file_name( wp_basename( $path ) );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( '' === $name || ! isset( self::$digitalisimo_attachment_types[ $ext ] ) ) {
			return false;
		}
		if ( 'svg' === $ext && ! \Elementor\Core\Files\Uploads_Manager::are_unfiltered_uploads_enabled() ) {
			// Decisión del administrador (D15): sin la subida sin filtrar, los SVG no entran en la biblioteca.
			$this->digitalisimo_report( 'assets.svg_skipped', [ 'url' => $url ] );
			return false;
		}

		$response = wp_safe_remote_get( $url, [
			'timeout' => 20,
			'redirection' => 3,
		] );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return false;
		}
		if ( 'svg' === $ext ) {
			$body = ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize( $body );
			if ( ! is_string( $body ) || '' === $body ) {
				return false;
			}
		}

		// El tipo se admite sólo durante esta escritura y antes del filtro de la red, que sigue mandando (CC-17).
		$mime  = self::$digitalisimo_attachment_types[ $ext ];
		$allow = function ( $mimes ) use ( $ext, $mime ) {
			if ( is_array( $mimes ) && ! isset( $mimes[ $ext ] ) ) {
				$mimes[ $ext ] = $mime;
			}
			return $mimes;
		};
		add_filter( 'upload_mimes', $allow, 5 );
		$upload = wp_upload_bits( $name, null, $body );
		remove_filter( 'upload_mimes', $allow, 5 );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return false;
		}

		$type = wp_check_filetype( $upload['file'] );
		$id   = wp_insert_attachment( [
			'post_title' => $name,
			'post_content' => '',
			'post_status' => 'inherit',
			'post_parent' => $parent_id,
			'post_mime_type' => ! empty( $type['type'] ) ? $type['type'] : $mime,
		], $upload['file'], $parent_id, true );

		if ( is_wp_error( $id ) || ! $id ) {
			wp_delete_file( $upload['file'] );
			return false;
		}

		return [
			'id' => (int) $id,
			'url' => (string) $upload['url'],
		];
	}

	/** Adjunto nuevo de una URL vieja del kit, o null. */
	private function digitalisimo_attachment_for( string $url ): ?array {
		return isset( $this->digitalisimo_attachments[ $url ] ) ? $this->digitalisimo_attachments[ $url ] : null;
	}

	/** Anota un aviso en el informe del kit, si hay una importación de kits en curso. */
	private function digitalisimo_report( string $code, array $data = [], string $level = 'warning' ): void {
		$context = '\Digitalisimo\Elements\Import\Kits\Context';
		if ( class_exists( $context, false ) && method_exists( $context, 'report' ) ) {
			$context::report( $code, $data, $level );
		}
	}

	public function create_attachments_from_urls( $parent_id, $attachments_data ): array {
		$attachment_ids = [];

		foreach ( is_array( $attachments_data ) ? $attachments_data : [] as $attachment_data ) {
			if ( ! is_array( $attachment_data ) ) {
				continue;
			}
			$attachment_id = $this->create_attachment_from_url( $parent_id, $attachment_data );
			if ( $attachment_id ) {
				$attachment_ids[] = $attachment_id;
			}
		}

		return $attachment_ids;
	}
}
