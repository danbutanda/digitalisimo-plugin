<?php
namespace ElementorPro\Modules\ThemeBuilder\ImportExportCustomization;

use Elementor\Core\Base\Document;
use Elementor\TemplateLibrary\Source_Local;
use Elementor\App\Modules\ImportExportCustomization\Utils as ImportExportCustomizationUtils;
use ElementorPro\Modules\ThemeBuilder\Documents\Theme_Document;
use ElementorPro\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Import {

	private $templates_conditions = [];

	public function add_theme_builder_to_import( $result, $data, $customization, $runner ) {
		if ( $customization && ( ! isset( $customization['themeBuilder']['enabled'] ) || ! $customization['themeBuilder']['enabled'] ) ) {
			return $result;
		}

		$theme_builder_result = $this->import_theme_builder_templates( $data, $runner );

		if ( ! empty( $theme_builder_result ) ) {
			$result['templates']['succeed'] = $this->array_merge_with_key_preservation( $result['templates']['succeed'], $theme_builder_result['succeed'] );
			$result['templates']['failed'] = $this->array_merge_with_key_preservation( $result['templates']['failed'], $theme_builder_result['failed'] );

			foreach ( $theme_builder_result['succeed_summary'] as $doc_type => $count ) {
				$result['templates']['succeed_summary'][ $doc_type ] = ( $result['templates']['succeed_summary'][ $doc_type ] ?? 0 ) + $count;
			}
		}

		return $result;
	}

	private function import_theme_builder_templates( $data, $runner ) {
		$path = $data['extracted_directory_path'] . 'templates/';
		$templates = $data['manifest']['templates'];

		$result = [
			'succeed' => [],
			'failed' => [],
			'succeed_summary' => [],
		];

		$theme_builder_types = array_keys( Plugin::elementor()->documents->get_document_types( [
			'is_editable' => true,
			'export_group' => Theme_Document::EXPORT_GROUP,
		] ) );

		foreach ( $templates as $id => $template_settings ) {
			if ( ! in_array( $template_settings['doc_type'], $theme_builder_types, true ) ) {
				continue;
			}

			try {
				$template_data = ImportExportCustomizationUtils::read_json_file( $path . $id );
				$imported_id = $this->import_theme_builder_template( $id, $template_settings, $template_data, $data );

				$result['succeed'][ $id ] = $imported_id;
				$result['succeed_summary'][ $template_settings['doc_type'] ] = ( $result['succeed_summary'][ $template_settings['doc_type'] ] ?? 0 ) + 1;
			} catch ( \Exception $error ) {
				$result['failed'][ $id ] = $error->getMessage();
			}
		}

		if ( ! empty( $this->templates_conditions ) ) {
			$runner->add_import_session_metadata( 'template_conditions', $this->templates_conditions );
			$this->templates_conditions = [];
		}

		return $result;
	}

	private function import_theme_builder_template( $id, array $template_settings, array $template_data, array $import_data ) {
		// DIGITALÍSIMO (§7.8 #6, D7): lo que el mismo kit ya importó sin cambios se reutiliza, en cualquier estado.
		$existing = apply_filters( 'digitalisimo_elements_kit_existing_document', null, $template_settings['doc_type'], $id, $template_data + [ 'import_settings' => $template_settings ] );
		if ( is_int( $existing ) && $existing > 0 ) {
			return $existing;
		}

		$new_document = Plugin::elementor()->documents->create(
			$template_settings['doc_type'],
			[
				'post_title' => $template_settings['title'],
				'post_type' => Source_Local::CPT,
				'post_status' => 'publish',
			]
		);

		if ( is_wp_error( $new_document ) ) {
			throw new \Exception( esc_html( $new_document->get_error_message() ) );
		}

		// DIGITALÍSIMO (A8): una miniatura que el mismo kit ya importó se reutiliza; el núcleo la volvería a descargar.
		$digitalisimo_thumbnail = 0;
		if ( class_exists( '\Digitalisimo\Elements\Import\Kits\Parts\Media' ) ) {
			list( $template_settings, $digitalisimo_thumbnail ) = \Digitalisimo\Elements\Import\Kits\Parts\Media::reuse_thumbnail( $template_settings );
		}

		$template_data['import_settings'] = $template_settings;
		$template_data['id'] = $id;

		$this->set_templates_conditions( $template_data );

		if ( isset( $import_data['session_id'] ) ) {
			$new_attachment_callback = function( $attachment_id ) use ( $import_data ) {
				$this->set_session_post_meta( $attachment_id, $import_data['session_id'] );
			};

			add_filter( 'elementor/template_library/import_images/new_attachment', $new_attachment_callback );
		}

		$new_document->import( $template_data );

		if ( isset( $new_attachment_callback ) ) {
			remove_filter( 'elementor/template_library/import_images/new_attachment', $new_attachment_callback );
		}

		$document_id = $new_document->get_main_id();

		if ( $digitalisimo_thumbnail > 0 ) {
			\Digitalisimo\Elements\Import\Kits\Parts\Media::set_thumbnail( (int) $document_id, $digitalisimo_thumbnail );
		}

		if ( isset( $import_data['session_id'] ) ) {
			$this->set_session_post_meta( $document_id, $import_data['session_id'] );
		}

		self::digitalisimo_created( (int) $document_id, (string) $template_settings['doc_type'], $id, $template_data );

		return $document_id;
	}

	/** DIGITALÍSIMO: procedencia de lo creado (D7), si hay una importación de kits en curso. */
	private static function digitalisimo_created( int $post_id, string $type, $old_id, array $data ): void {
		$context = '\Digitalisimo\Elements\Import\Kits\Context';
		$session = '\Digitalisimo\Elements\Import\Kits\Session';
		if ( class_exists( $context, false ) && class_exists( $session ) && method_exists( $context, 'created' ) ) {
			$context::created( $post_id, $type, $old_id, $session::document_hash( $data ) );
		}
	}

	private function set_session_post_meta( $post_id, $session_id ) {
		update_post_meta( $post_id, '_elementor_import_session_id', $session_id );
	}

	private function set_templates_conditions( $template_data ) {
		$conditions = $template_data['import_settings']['conditions'] ?? [];

		if ( empty( $conditions ) ) {
			return;
		}

		$condition = $conditions[0];

		$condition = rtrim( implode( '/', $condition ), '/' );

		/** @var ThemeBuilderModule $theme_builder_module */
		$theme_builder_module = Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
		$conditions_manager = $theme_builder_module->get_conditions_manager();

		$conflicts = $conditions_manager->get_conditions_conflicts_by_location(
			$condition,
			$template_data['import_settings']['location']
		);

		foreach ( $conflicts as $template ) {
			$template_document = Plugin::elementor()->documents->get( $template['template_id'] );

			$template_conditions = $theme_builder_module->get_conditions_manager()->get_document_conditions( $template_document );

			$this->templates_conditions[ $template['template_id'] ] = $template_conditions;
		}
	}

	public function array_merge_with_key_preservation( array $base_array, array $additional_array ): array {
		return $base_array + $additional_array;
	}
}
