<?php

namespace ElementorPro\Modules\AssetsManager\AssetTypes\Icons\ImportExportCustomization;

use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;
use ElementorPro\Modules\AssetsManager\AssetTypes\Icons_Manager;
use ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons;
use ElementorPro\Modules\AssetsManager\AssetTypes\ImportExport\Traits\External_Attachment_Trait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conjuntos de iconos personalizados de un kit.
 *
 * DIGITALÍSIMO (§7.8 #3, CR-8): nada del kit decide una ruta ni una URL. Se ignoran `icon_set_path` (una ruta de
 * disco del sitio de origen) e `icon_set_config` (traía `url`, `enqueue` y `fetchJson` hacia el dominio del autor).
 * Los archivos del conjunto se descargan (por el proxy de medios durante una importación de kits) a una carpeta
 * temporal propia y se reconstruye el conjunto con el mismo extractor que una subida manual (`Icon_Set_Base`): la
 * configuración queda con URL locales. Si no se puede, el conjunto se omite y se informa. Un conjunto que el mismo
 * kit ya importó se reutiliza en cualquier estado.
 */
class Import_Runner extends Import_Runner_Base {
	use External_Attachment_Trait;

	/** Archivos de un conjunto que se descargan; el HTML y el JS del kit nunca. */
	const SET_FILES = [ 'css', 'json', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg' ];

	/** Archivos que deja el extractor y no deben quedar en la carpeta de subidas. */
	const DROP_AFTER = [ 'html', 'htm', 'txt' ];

	private $session_id;
	private $imported_icon_sets = [];

	public static function get_name(): string {
		return 'custom-icons';
	}

	public function should_import( array $data ) {
		return (
			isset( $data['include'] ) &&
			in_array( 'settings', $data['include'], true )
		);
	}

	public function import( array $data, array $imported_data ) {
		$this->session_id = $data['session_id'];

		$result = [];

		$custom_icons_file_path = $data['extracted_directory_path'] . Import_Export_Customization::FILE_NAME;
		$icon_sets_data = ImportExportUtils::read_json_file( $custom_icons_file_path );

		$include_custom_icons = $data['customization']['settings']['customIcons'] ?? true;

		if ( empty( $icon_sets_data ) || ! is_array( $icon_sets_data ) || ! $include_custom_icons ) {
			return $result;
		}

		foreach ( $icon_sets_data as $icon_set_data ) {
			if ( is_array( $icon_set_data ) ) {
				$this->import_icon_set( $icon_set_data );
			}
		}

		if ( empty( $this->imported_icon_sets ) ) {
			return $result;
		}

		$result['site-settings']['custom-icons'] = $this->imported_icon_sets;

		return $result;
	}

	public function get_import_session_metadata(): array {
		return [
			'imported_icon_sets' => $this->imported_icon_sets,
		];
	}

	private function import_icon_set( array $icon_set_data ) {
		$title = is_scalar( $icon_set_data['post_title'] ?? null ) ? sanitize_text_field( (string) $icon_set_data['post_title'] ) : '';
		if ( '' === $title ) {
			return;
		}
		$old_id = $icon_set_data['ID'] ?? $title;
		$existing = apply_filters( 'digitalisimo_elements_kit_existing_document', null, Icons_Manager::CPT, $old_id, $icon_set_data );
		if ( is_int( $existing ) && $existing > 0 ) {
			return;
		}

		$icon_set_id = $this->create_icon_set( $icon_set_data, $title );

		if ( $icon_set_id ) {
			$this->imported_icon_sets[] = [
				'id' => $icon_set_id,
				'title' => $title,
			];
			$this->digitalisimo_created( $icon_set_id, Icons_Manager::CPT, $old_id, $icon_set_data );
		}
	}

	private function create_icon_set( array $icon_set_data, string $title ) {
		$status = in_array( $icon_set_data['post_status'] ?? '', [ 'publish', 'draft', 'private' ], true ) ? $icon_set_data['post_status'] : 'publish';
		$icon_set_id = wp_insert_post( [
			'post_title' => $title,
			'post_content' => '',
			'post_status' => $status,
			'post_type' => Icons_Manager::CPT,
		] );

		if ( is_wp_error( $icon_set_id ) || ! $icon_set_id ) {
			return false;
		}

		$this->set_session_post_meta( $icon_set_id, $this->session_id );

		$config = $this->rebuild( (int) $icon_set_id, isset( $icon_set_data['attachments'] ) && is_array( $icon_set_data['attachments'] ) ? $icon_set_data['attachments'] : [] );
		if ( null === $config ) {
			$this->discard( (int) $icon_set_id );
			$this->digitalisimo_report( 'icons.skipped', [ 'set' => $title ] );
			return false;
		}

		update_post_meta( $icon_set_id, Custom_Icons::META_KEY, wp_json_encode( $config ) );
		Custom_Icons::clear_icon_list_option();

		return $icon_set_id;
	}

	/**
	 * Reconstruye el conjunto desde sus archivos con el extractor de `Custom_Icons`.
	 *
	 * @return array|null Configuración con URL locales, o null si no se pudo.
	 */
	private function rebuild( int $icon_set_id, array $attachments ): ?array {
		$tmp = trailingslashit( get_temp_dir() ) . 'digitalisimo-icons-' . wp_generate_password( 12, false, false ) . '/';
		if ( ! wp_mkdir_p( $tmp ) ) {
			return null;
		}
		$root = realpath( $tmp );
		if ( false === $root ) {
			return null;
		}
		$root  = trailingslashit( wp_normalize_path( $root ) );
		$count = 0;
		foreach ( $attachments as $attachment ) {
			$url = is_array( $attachment ) && isset( $attachment['url'] ) && is_string( $attachment['url'] ) ? $attachment['url'] : '';
			$relative = self::relative_path( $url );
			if ( '' === $relative || ! $this->fetch_into( $url, $root, $relative ) ) {
				continue;
			}
			++$count;
		}
		if ( 0 === $count ) {
			$this->remove_dir( $root );
			return null;
		}
		// Fontastic sólo comprueba que exista su página de referencia: un marcador vacío, nunca el HTML del kit.
		if ( is_file( $root . 'styles.css' ) && ! is_file( $root . 'icons-reference.html' ) ) {
			file_put_contents( $root . 'icons-reference.html', '' );
		}

		$config = null;
		foreach ( Custom_Icons::get_supported_icon_sets() as $handler_class ) {
			$handler = new $handler_class( $root );
			if ( ! $handler || ! $handler->is_valid() ) {
				continue;
			}
			$handler->handle_new_icon_set();
			$handler->move_files( $icon_set_id );
			$config = $handler->build_config();
			break;
		}
		if ( is_dir( $root ) ) {
			$this->remove_dir( $root );
		}
		$this->drop_documents( $icon_set_id );
		return is_array( $config ) ? $this->safe_config( $config ) : null;
	}

	/**
	 * Ruta del archivo dentro de su conjunto (`custom-icons/<conjunto>/<ruta>`), validada: como mucho una carpeta,
	 * sin `..` y con una extensión de `SET_FILES`. '' si no vale.
	 */
	private static function relative_path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$at   = strpos( $path, '/custom-icons/' );
		if ( false === $at ) {
			return '';
		}
		$inside = explode( '/', substr( $path, $at + strlen( '/custom-icons/' ) ), 2 );
		$relative = isset( $inside[1] ) ? rawurldecode( $inside[1] ) : '';
		if ( 1 !== preg_match( '#^[A-Za-z0-9 ._-]+(?:/[A-Za-z0-9 ._-]+)?$#', $relative ) || false !== strpos( $relative, '..' ) ) {
			return '';
		}
		return in_array( strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) ), self::SET_FILES, true ) ? $relative : '';
	}

	/** Descarga un archivo del conjunto a la carpeta temporal (saneando CSS y SVG). */
	private function fetch_into( string $url, string $root, string $relative ): bool {
		$target = $root . $relative;
		$folder = dirname( $target );
		if ( ! is_dir( $folder ) && ! wp_mkdir_p( $folder ) ) {
			return false;
		}
		$real = realpath( $folder );
		if ( false === $real || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $root ) ) {
			return false;
		}
		$response = wp_safe_remote_get( $url, [
			'timeout' => 20,
			'redirection' => 3,
		] );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->digitalisimo_report( 'assets.attachment_failed', [ 'url' => $url ] );
			return false;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		$ext  = strtolower( (string) pathinfo( $relative, PATHINFO_EXTENSION ) );
		if ( 'css' === $ext ) {
			$body = self::safe_css( $body );
		} elseif ( 'svg' === $ext ) {
			$body = ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize( $body );
			if ( ! is_string( $body ) ) {
				return false;
			}
		}
		return '' !== $body && false !== file_put_contents( $target, $body );
	}

	/** CSS del conjunto sin `@import` ni recursos de otros dominios. */
	private static function safe_css( string $css ): string {
		$css = (string) preg_replace( '/@import\b[^;]*;?/i', '', $css );
		$css = (string) preg_replace( '#url\(\s*([\'"]?)\s*(?:[a-z][a-z0-9+.-]*:)?//[^)]*\)#i', 'url()', $css );
		return str_ireplace( [ '</style', 'expression(' ], [ '', '' ], $css );
	}

	/**
	 * Configuración con sólo URL de la carpeta local de iconos y nombres seguros (CR-8).
	 *
	 * @return array|null
	 */
	private function safe_config( array $config ): ?array {
		$uploads = wp_upload_dir( null, false );
		$base    = empty( $uploads['error'] ) ? trailingslashit( $uploads['baseurl'] ) . 'elementor/custom-icons/' : '';
		$local   = function ( $url ) use ( $base ) {
			return is_string( $url ) && '' !== $base && 0 === strpos( $url, $base ) && false === strpos( $url, '..' );
		};
		if ( empty( $config['name'] ) || ! $local( $config['url'] ?? '' ) ) {
			return null;
		}
		if ( isset( $config['fetchJson'] ) && ! $local( $config['fetchJson'] ) ) {
			return null;
		}
		if ( ! empty( $config['enqueue'] ) ) {
			foreach ( (array) $config['enqueue'] as $url ) {
				if ( ! $local( $url ) ) {
					return null;
				}
			}
		}
		foreach ( [ 'name', 'prefix', 'displayPrefix' ] as $key ) {
			if ( isset( $config[ $key ] ) && is_string( $config[ $key ] ) && 1 !== preg_match( '/^[A-Za-z0-9_-]*$/', $config[ $key ] ) ) {
				return null;
			}
		}
		$config['label'] = sanitize_text_field( (string) ( $config['label'] ?? $config['name'] ) );
		$config['icons'] = array_values( array_filter( isset( $config['icons'] ) && is_array( $config['icons'] ) ? $config['icons'] : [], function ( $icon ) {
			return is_string( $icon ) && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $icon );
		} ) );
		$config['count'] = count( $config['icons'] );
		return $config;
	}

	/** Quita del conjunto lo que el extractor movió y no debe servirse (HTML y texto). */
	private function drop_documents( int $icon_set_id ): void {
		foreach ( get_attached_media( '', $icon_set_id ) as $attachment ) {
			$file = (string) get_attached_file( $attachment->ID );
			$ext  = strtolower( (string) pathinfo( '' !== $file ? $file : (string) $attachment->guid, PATHINFO_EXTENSION ) );
			if ( in_array( $ext, self::DROP_AFTER, true ) ) {
				wp_delete_attachment( $attachment->ID, true );
			}
		}
		$dir = (string) get_post_meta( $icon_set_id, '_elementor_icon_set_path', true );
		if ( '' !== $dir && is_dir( $dir ) ) {
			foreach ( (array) glob( trailingslashit( $dir ) . '*.{html,htm,txt}', GLOB_BRACE ) as $file ) {
				wp_delete_file( $file );
			}
		}
	}

	/** Borra un conjunto que no se pudo reconstruir (y lo que llegó a mover). */
	private function discard( int $icon_set_id ): void {
		foreach ( get_attached_media( '', $icon_set_id ) as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}
		wp_delete_post( $icon_set_id, true );
	}

	/** Borrado recursivo de la carpeta temporal propia (nunca sale de ella). */
	private function remove_dir( string $root ): void {
		$real = realpath( $root );
		$temp = realpath( get_temp_dir() );
		if ( false === $real || false === $temp || 0 !== strpos( wp_normalize_path( $real ), trailingslashit( wp_normalize_path( $temp ) ) . 'digitalisimo-icons-' ) ) {
			return;
		}
		Custom_Icons::get_wp_filesystem()->rmdir( $real, true );
	}

	/** Procedencia de lo creado (D7), si hay una importación de kits en curso. */
	private function digitalisimo_created( int $post_id, string $type, $old_id, array $data ): void {
		$context = '\Digitalisimo\Elements\Import\Kits\Context';
		$session = '\Digitalisimo\Elements\Import\Kits\Session';
		if ( class_exists( $context, false ) && class_exists( $session ) && method_exists( $context, 'created' ) ) {
			$context::created( $post_id, $type, $old_id, $session::document_hash( $data ) );
		}
	}
}
