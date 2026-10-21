<?php

namespace ElementorPro\Modules\AssetsManager\AssetTypes\Fonts\ImportExportCustomization;

use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;
use ElementorPro\Modules\AssetsManager\AssetTypes\Fonts_Manager;
use ElementorPro\Modules\AssetsManager\AssetTypes\Fonts\Custom_Fonts;
use ElementorPro\Modules\AssetsManager\AssetTypes\ImportExport\Traits\External_Attachment_Trait;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fuentes personalizadas de un kit.
 *
 * DIGITALÍSIMO (§7.8 #1): `font_face` nunca se copia del kit (es CSS que se imprime tal cual en la administración y
 * en el sitio: un kit podía colar HTML). Se regenera con `Custom_Fonts` a partir de los archivos importados, que son
 * adjuntos locales (#2). Una fuente que el mismo kit ya importó se reutiliza en cualquier estado
 * (`digitalisimo_elements_kit_existing_document`), no se busca por título publicado.
 */
class Import_Runner extends Import_Runner_Base {
	use External_Attachment_Trait;

	/** Formatos de archivo de una variante de la fuente. */
	const FILE_TYPES = [ 'woff2', 'woff', 'ttf', 'svg', 'eot', 'otf' ];

	private $session_id;
	private $imported_fonts = [];

	public static function get_name(): string {
		return 'custom-fonts';
	}

	public function should_import( array $data ) {
		return (
			isset( $data['include'] ) &&
			in_array( 'settings', $data['include'], true )
		);
	}

	public function import( array $data, array $imported_data ) {
		$this->session_id = $data['session_id'];

		$include_custom_fonts = $data['customization']['settings']['customFonts'] ?? true;

		$custom_fonts = new Custom_Fonts();
		$custom_fonts->get_fonts( true );

		$result = [];

		$custom_fonts_file_path = $data['extracted_directory_path'] . Import_Export_Customization::FILE_NAME;
		$fonts_data = ImportExportUtils::read_json_file( $custom_fonts_file_path );

		if ( empty( $fonts_data ) || ! is_array( $fonts_data ) || ! $include_custom_fonts ) {
			return $result;
		}

		foreach ( $fonts_data as $font_data ) {
			if ( is_array( $font_data ) ) {
				$this->import_font( $font_data, $custom_fonts );
			}
		}

		if ( empty( $this->imported_fonts ) ) {
			return $result;
		}

		$result['site-settings']['custom-fonts'] = $this->imported_fonts;

		return $result;
	}

	public function get_import_session_metadata(): array {
		return [
			'imported_fonts' => $this->imported_fonts,
		];
	}

	private function import_font( array $font_data, Custom_Fonts $custom_fonts ) {
		$title = self::family( $font_data['post_title'] ?? '' );
		if ( '' === $title ) {
			return;
		}

		$old_id = $font_data['ID'] ?? $title;
		$existing = apply_filters( 'digitalisimo_elements_kit_existing_document', null, Fonts_Manager::CPT, $old_id, $font_data );
		if ( is_int( $existing ) && $existing > 0 ) {
			return;
		}

		$font_id = $this->create_font( $font_data, $title, $custom_fonts );

		if ( $font_id ) {
			$this->imported_fonts[] = [
				'id' => $font_id,
				'title' => $title,
			];
			$this->digitalisimo_created( $font_id, Fonts_Manager::CPT, $old_id, $font_data );
		}
	}

	private function create_font( array $font_data, string $title, Custom_Fonts $custom_fonts ) {
		$status  = in_array( $font_data['post_status'] ?? '', [ 'publish', 'draft', 'private' ], true ) ? $font_data['post_status'] : 'publish';
		$font_id = wp_insert_post( [
			'post_title' => $title,
			'post_content' => '',
			'post_status' => $status,
			'post_type' => Fonts_Manager::CPT,
		] );

		if ( is_wp_error( $font_id ) || ! $font_id ) {
			return false;
		}

		$this->set_session_post_meta( $font_id, $this->session_id );

		// DIGITALÍSIMO: el gestor sólo lista las fuentes con su tipo (`elementor_font_type`); sin él, la fuente importada
		// no aparecía en el editor ni se cargaba en la página. Las de un kit son siempre personalizadas.
		wp_set_object_terms( $font_id, 'custom', Fonts_Manager::TAXONOMY );
		delete_option( Fonts_Manager::FONTS_OPTION_NAME );
		delete_option( Fonts_Manager::FONTS_NAME_TYPE_OPTION_NAME );

		if ( ! empty( $font_data['attachments'] ) && is_array( $font_data['attachments'] ) ) {
			$this->create_attachments_from_urls( $font_id, $font_data['attachments'] );
		}

		$files = $this->font_files( isset( $font_data['font_files'] ) && is_array( $font_data['font_files'] ) ? $font_data['font_files'] : [] );
		if ( $files ) {
			update_post_meta( $font_id, Custom_Fonts::FONT_META_KEY, $files );
			// Regenerado desde los archivos locales: nunca el `font_face` del kit.
			update_post_meta( $font_id, Custom_Fonts::FONT_FACE_META_KEY, $custom_fonts->generate_font_face( $font_id ) );
		} else {
			$this->digitalisimo_report( 'fonts.no_files', [ 'font' => $title ] );
		}

		return $font_id;
	}

	/**
	 * Variantes de la fuente con sus campos saneados y sólo los archivos que se importaron como adjuntos locales.
	 */
	private function font_files( array $rows ): array {
		$clean = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$item = [
				'font_weight' => self::weight( $row['font_weight'] ?? 'normal' ),
				'font_style' => in_array( $row['font_style'] ?? '', [ 'normal', 'italic', 'oblique' ], true ) ? $row['font_style'] : 'normal',
			];
			if ( isset( $row['font_type'] ) && in_array( $row['font_type'], [ 'static', 'variable' ], true ) ) {
				$item['font_type'] = $row['font_type'];
			}
			foreach ( [ 'variable_weight', 'variable_width' ] as $flag ) {
				if ( ! empty( $row[ $flag ] ) ) {
					$item[ $flag ] = 'yes';
				}
			}
			foreach ( [ 'variable_weight_min', 'variable_weight_max', 'variable_width_min', 'variable_width_max' ] as $range ) {
				if ( isset( $row[ $range ] ) && is_numeric( $row[ $range ] ) ) {
					$item[ $range ] = (string) (int) $row[ $range ];
				}
			}
			$has_file = false;
			foreach ( self::FILE_TYPES as $type ) {
				$url = isset( $row[ $type ]['url'] ) && is_string( $row[ $type ]['url'] ) ? $row[ $type ]['url'] : '';
				if ( '' === $url ) {
					continue;
				}
				$local = $this->digitalisimo_attachment_for( $url );
				if ( null === $local ) {
					continue;
				}
				$item[ $type ] = [
					'id' => $local['id'],
					'url' => esc_url_raw( $local['url'] ),
				];
				$has_file = true;
			}
			if ( $has_file ) {
				$clean[] = $item;
			}
		}
		return $clean;
	}

	/** Nombre de familia seguro para el CSS que genera `Custom_Fonts`. */
	private static function family( $title ): string {
		$title = is_scalar( $title ) ? sanitize_text_field( (string) $title ) : '';
		return trim( (string) preg_replace( '/[^\p{L}\p{N} _.-]/u', '', $title ) );
	}

	/** Peso de fuente válido. */
	private static function weight( $weight ): string {
		$weight = is_scalar( $weight ) ? strtolower( (string) $weight ) : 'normal';
		return in_array( $weight, [ 'normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900' ], true ) ? $weight : 'normal';
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
