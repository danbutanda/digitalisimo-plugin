<?php

namespace ElementorPro\Modules\CustomCode\ImportExportCustomization;

use Elementor\App\Modules\ImportExportCustomization\Runners\Export\Export_Runner_Base;
use ElementorPro\Modules\CustomCode\Module as Custom_Code_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fragmentos de Custom Code de un kit.
 *
 * DIGITALÍSIMO (§7.8 #5, CC-3): la exportación y la importación son simétricas. Viajan el código, la ubicación, la
 * prioridad, las opciones y las condiciones (`_elementor_conditions`), y las reglas por dispositivo, rol, idioma,
 * URL, fecha y hora si el sitio las tiene. Antes sólo viajaba `post_content`, que no es el código.
 */
class Export_Runner extends Export_Runner_Base {
	/** Metas del fragmento que viajan en el kit: clave del JSON => meta. */
	const FIELDS = [
		'location' => '_elementor_location',
		'priority' => '_elementor_priority',
		'code' => '_elementor_code',
		'extra_options' => '_elementor_extra_options',
		'conditions' => '_elementor_conditions',
		'device_mode' => '_elementor_device_mode',
		'user_roles' => '_elementor_user_roles',
		'site_languages' => '_elementor_site_languages',
		'url_conditions' => '_elementor_url_conditions',
		'date_conditions' => '_elementor_date_conditions',
		'time_conditions' => '_elementor_time_conditions',
	];

	public static function get_name(): string {
		return 'custom-code';
	}

	public function should_export( array $data ) {
		return (
			isset( $data['include'] ) &&
			in_array( 'settings', $data['include'], true )
		);
	}

	public function export( array $data ) {
		$code_snippets = $this->get_custom_code_snippets();
		$include_custom_code = $data['customization']['settings']['customCode'] ?? true;

		if ( empty( $code_snippets ) || ! $include_custom_code ) {
			return [
				'manifest' => [],
				'files' => [],
			];
		}

		$snippets_data = [];
		$manifest = [];

		foreach ( $code_snippets as $snippet ) {
			$data = $this->prepare_snippet_data( $snippet );
			$snippets_data[] = $data;
			$manifest['custom-code'][ $snippet->ID ] = [
				'ID' => $data['ID'],
				'post_title' => $data['post_title'],
				'location' => $data['location'] ?? '',
			];
		}

		return [
			'files' => [
				'path' => Import_Export_Customization::FILE_NAME,
				'data' => $snippets_data,
			],
			'manifest' => [
				$manifest,
			],
		];
	}

	private function get_custom_code_snippets() {
		return get_posts( [
			'post_type' => Custom_Code_Module::CPT,
			'posts_per_page' => -1,
			'post_status' => 'publish',
		] );
	}

	private function prepare_snippet_data( $snippet ) {
		$data = [
			'ID' => $snippet->ID,
			'post_title' => $snippet->post_title,
			'post_status' => $snippet->post_status,
		];

		foreach ( self::FIELDS as $field => $meta_key ) {
			if ( metadata_exists( 'post', $snippet->ID, $meta_key ) ) {
				$data[ $field ] = get_post_meta( $snippet->ID, $meta_key, true );
			}
		}

		return $data;
	}
}
