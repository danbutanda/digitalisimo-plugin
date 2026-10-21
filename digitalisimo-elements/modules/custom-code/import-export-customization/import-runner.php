<?php

namespace ElementorPro\Modules\CustomCode\ImportExportCustomization;

use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportUtils;
use ElementorPro\Modules\CustomCode\Module as Custom_Code_Module;
use ElementorPro\Modules\CustomCode\Custom_Code_Metabox;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fragmentos de Custom Code de un kit.
 *
 * DIGITALÍSIMO (§7.8 #5, CC-3):
 * - simétrico con la exportación: código, ubicación, prioridad, opciones, condiciones y reglas;
 * - sin `unfiltered_html` no se importa nada (el código es HTML y JS que se imprime tal cual) y se informa;
 * - siempre en borrador, en los dos modos (D8): un fragmento nunca se activa solo;
 * - las condiciones se guardan pendientes y el runner final las traduce cerradas (§9.5): un `sub_id` sin destino
 *   deja el fragmento sin condiciones, en borrador y para revisar;
 * - un fragmento que el mismo kit ya importó se reutiliza en cualquier estado, no se busca por título publicado.
 */
class Import_Runner extends Import_Runner_Base {
	/** Meta pendiente que traduce `Runners\Finish::after_save()` de DIGITALÍSIMO Elements. */
	const PENDING_CONDITIONS = '_digitalisimo_import_conditions';

	/** Reglas opcionales que se guardan tal cual (saneadas), si el kit las trae. */
	const RULES = [
		'device_mode' => '_elementor_device_mode',
		'user_roles' => '_elementor_user_roles',
		'site_languages' => '_elementor_site_languages',
		'url_conditions' => '_elementor_url_conditions',
		'date_conditions' => '_elementor_date_conditions',
		'time_conditions' => '_elementor_time_conditions',
	];

	private $session_id;
	private $imported_snippets = [];

	public static function get_name(): string {
		return 'custom-code';
	}

	public function should_import( array $data ) {
		return (
			isset( $data['include'] ) &&
			in_array( 'settings', $data['include'], true )
		);
	}

	public function import( array $data, array $imported_data ) {
		$this->session_id = $data['session_id'];
		$include_custom_code = $data['customization']['settings']['customCode'] ?? true;

		$custom_code_file_path = $data['extracted_directory_path'] . Import_Export_Customization::FILE_NAME;
		$snippets_data = ImportExportUtils::read_json_file( $custom_code_file_path );

		$result = [];
		if ( empty( $snippets_data ) || ! is_array( $snippets_data ) || ! $include_custom_code ) {
			return $result;
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$this->digitalisimo_report( 'custom_code.skipped', [ 'count' => count( $snippets_data ) ] );
			return $result;
		}

		foreach ( $snippets_data as $snippet_data ) {
			if ( is_array( $snippet_data ) ) {
				$this->import_snippet( $snippet_data );
			}
		}

		if ( empty( $this->imported_snippets ) ) {
			return $result;
		}

		$result['site-settings']['custom-code'] = $this->imported_snippets;

		return $result;
	}

	public function get_import_session_metadata(): array {
		return [
			'imported_snippets' => $this->imported_snippets,
		];
	}

	private function import_snippet( array $snippet_data ) {
		$title = is_scalar( $snippet_data['post_title'] ?? null ) ? sanitize_text_field( (string) $snippet_data['post_title'] ) : '';
		$old_id = $snippet_data['ID'] ?? $title;
		$existing = apply_filters( 'digitalisimo_elements_kit_existing_document', null, Custom_Code_Module::CPT, $old_id, $snippet_data );

		if ( is_int( $existing ) && $existing > 0 ) {
			return;
		}

		$snippet_id = $this->create_snippet( $snippet_data, $title );

		if ( $snippet_id ) {
			$this->imported_snippets[] = [
				'id' => $snippet_id,
				'title' => $title,
			];
			$this->digitalisimo_created( (int) $snippet_id, Custom_Code_Module::CPT, $old_id, $snippet_data );
		}
	}

	private function create_snippet( array $snippet_data, string $title ) {
		$snippet_id = wp_insert_post( [
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'draft',
			'post_type' => Custom_Code_Module::CPT,
		] );

		if ( is_wp_error( $snippet_id ) || ! $snippet_id ) {
			return false;
		}

		$this->set_session_post_meta( $snippet_id, $this->session_id );

		$locations = [ Custom_Code_Metabox::OPTION_LOCATION_HEAD, Custom_Code_Metabox::OPTION_LOCATION_BODY_START, Custom_Code_Metabox::OPTION_LOCATION_BODY_END ];
		$location = $snippet_data['location'] ?? '';
		update_post_meta( $snippet_id, '_elementor_' . Custom_Code_Metabox::FIELD_LOCATION, in_array( $location, $locations, true ) ? $location : Custom_Code_Metabox::DEFAULT_LOCATION );

		$priority = isset( $snippet_data['priority'] ) && is_numeric( $snippet_data['priority'] ) ? (int) $snippet_data['priority'] : Custom_Code_Metabox::DEFAULT_PRIORITY;
		update_post_meta( $snippet_id, '_elementor_' . Custom_Code_Metabox::FIELD_PRIORITY, (string) max( 1, min( Custom_Code_Metabox::OPTION_PRIORITY_LENGTH, $priority ) ) );

		if ( isset( $snippet_data['code'] ) && is_string( $snippet_data['code'] ) ) {
			update_post_meta( $snippet_id, '_elementor_' . Custom_Code_Metabox::FIELD_CODE, wp_slash( $snippet_data['code'] ) );
		} elseif ( isset( $snippet_data['post_content'] ) && is_string( $snippet_data['post_content'] ) && '' !== $snippet_data['post_content'] ) {
			// Kits anteriores: el código venía en el contenido de la entrada.
			update_post_meta( $snippet_id, '_elementor_' . Custom_Code_Metabox::FIELD_CODE, wp_slash( $snippet_data['post_content'] ) );
		}

		if ( isset( $snippet_data['extra_options'] ) && is_array( $snippet_data['extra_options'] ) ) {
			$options = array_values( array_intersect( array_map( 'strval', $snippet_data['extra_options'] ), Custom_Code_Metabox::INPUT_OPTIONS ) );
			if ( $options ) {
				update_post_meta( $snippet_id, '_elementor_' . Custom_Code_Metabox::FILED_EXTRA_OPTIONS, $options );
			}
		}

		foreach ( self::RULES as $field => $meta_key ) {
			if ( isset( $snippet_data[ $field ] ) && '' !== $snippet_data[ $field ] && [] !== $snippet_data[ $field ] ) {
				update_post_meta( $snippet_id, $meta_key, self::clean( $snippet_data[ $field ] ) );
			}
		}

		if ( ! empty( $snippet_data['conditions'] ) && is_array( $snippet_data['conditions'] ) ) {
			// Pendientes: el runner final las traduce cerradas cuando ya existen todos los mapas (§9.5).
			update_post_meta( $snippet_id, self::PENDING_CONDITIONS, array_values( array_filter( $snippet_data['conditions'], 'is_string' ) ) );
		}

		return $snippet_id;
	}

	/** Escalares y listas de escalares saneados. */
	private static function clean( $value ) {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $key => $item ) {
				$out[ sanitize_key( (string) $key ) ] = self::clean( $item );
			}
			return $out;
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/** Anota un aviso en el informe del kit, si hay una importación de kits en curso. */
	private function digitalisimo_report( string $code, array $data = [] ): void {
		$context = '\Digitalisimo\Elements\Import\Kits\Context';
		if ( class_exists( $context, false ) && method_exists( $context, 'report' ) ) {
			$context::report( $code, $data, 'warning' );
		}
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
