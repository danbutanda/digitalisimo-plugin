<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Normalizer;
use Digitalisimo\Elements\Import\Kits\Parts;
use Digitalisimo\Elements\Import\Kits\Session;

defined( 'ABSPATH' ) || exit;

/**
 * Enganche de K en los procesos del importador 3.0 (§7.3, §7.5, §7.7, §8.3).
 *
 * - `import-kit` (20): `configure()` fija el modo (D1), guarda el respaldo (D2), apaga tema, experimentos, plugins y
 *   condiciones que pisan (D3, D5), y sustituye los runners por nombre en su sitio.
 * - `import-kit` (`PHP_INT_MAX`): `finish()` registra el runner final `digitalisimo`.
 * - `export-kit` (20 y `PHP_INT_MAX`), `revert-kit` (20 y `PHP_INT_MAX`): lo mismo al exportar y al revertir.
 * - `…/import-kit/runner/after-run` (10): `after_run()` cierra la sesión cuando ya no queda runner.
 */
final class Import_Hooks {
	/** Ajustes de kit v4 que «agregar» no fusiona (CC-10, CV-18). */
	const V4_OFF = array(
		'variables'            => false,
		'classes'              => false,
		'variablesOverrideAll' => false,
		'classesOverrideAll'   => false,
	);

	/**
	 * Metas del kit que se respaldan además de las de las partes. Las de versión y del índice de clases globales las
	 * reescribe el guardado del kit: sin respaldarlas, revertir no lo dejaría como estaba (A10).
	 */
	const KIT_METAS = array( '_elementor_elements_default_values', Module::KIT_META_UNCONVERTED_DEFAULTS, '_elementor_version', '_elementor_pro_version', '_elementor_global_class_usage_indexed', '_elementor_global_class_usage_indexed_preview' );

	/** Prefijos de las metas v4 del kit (variables, clases globales y estilos por defecto). */
	const V4_PREFIXES = array( '_elementor_global_', '_elementor_default_styles' );

	/**
	 * `elementor/import-export-customization/import-kit` (20).
	 *
	 * @param object $import Proceso de importación del núcleo.
	 *
	 * @throws \Exception Si se pidió «nuevo» y el sitio ya no está vacío (D1).
	 */
	public static function configure( $import ): void {
		if ( ! is_object( $import ) || ! method_exists( $import, 'get_session_id' ) || ! current_user_can( Module::CAPABILITY ) ) {
			return;
		}
		$session = (string) $import->get_session_id();
		if ( ! Session::valid_id( $session ) ) {
			return;
		}
		$record = Session::get( $session );
		if ( null === $record ) {
			$manifest = (array) $import->get_manifest();
			$record   = Session::bind(
				$session,
				array(
					'mode'      => Module::MODE_ADD,
					'requested' => Module::MODE_ADD,
					'format'    => isset( $manifest['manifest_version'] ) ? Normalizer::FORMAT_ENVATO : ( version_compare( (string) ( $manifest['version'] ?? '0' ), '3.0', '<' ) ? Normalizer::FORMAT_LEGACY : Normalizer::FORMAT_KIT3 ),
					'origin'    => array(
						'format'  => '',
						'kit'     => sha1( (string) ( $manifest['site'] ?? '' ) . '|' . (string) ( $manifest['name'] ?? '' ) ),
						'home'    => (string) ( $manifest['site'] ?? '' ),
						'site'    => (string) ( $manifest['site'] ?? '' ),
						'uploads' => '',
					),
					'surface'   => 'app',
				)
			);
		}

		$mode = Module::MODE_ADD;
		if ( Module::MODE_FRESH === ( $record['requested'] ?? '' ) ) {
			$decision = Session::decide_mode( Module::MODE_FRESH, (string) ( $record['format'] ?? '' ) );
			if ( '' !== $decision['error'] ) {
				throw new \Exception( $decision['error'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$mode = Module::MODE_FRESH;
		}

		$kit     = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		$kit_id  = $kit ? (int) $kit->get_id() : 0;
		$changes = array(
			'mode'        => $mode,
			'status'      => Session::STATUS_IMPORTED,
			'max_post_id' => isset( $record['max_post_id'] ) ? (int) $record['max_post_id'] : self::max_post_id(),
			'kit_id'      => $kit_id,
		);
		if ( empty( $record['backup'] ) ) {
			$changes['backup'] = self::backup( $kit_id );
		}
		$record = Session::update( $session, $changes );
		Context::activate( $session );

		// Ajustes del proceso: nada pisa condiciones, nada instala plugins, nada cambia tema ni experimentos.
		$import->settings_selected_override_conditions( array() );
		$import->settings_selected_plugins( array() );
		$include = $import->get_settings_include();
		if ( is_array( $include ) ) {
			$import->settings_include( array_values( array_diff( $include, array( 'plugins' ) ) ) );
		}
		$customization = $import->get_settings_customization();
		$customization = is_array( $customization ) ? $customization : array();
		$settings      = isset( $customization['settings'] ) && is_array( $customization['settings'] ) ? $customization['settings'] : array();
		$settings['theme']       = false;
		$settings['experiments'] = false;
		if ( Module::MODE_ADD === $mode ) {
			$settings = array_replace( $settings, self::V4_OFF );
		}
		$customization['settings'] = $settings;
		$import->settings_customization( $customization );

		// Runners sustituidos por nombre, en su sitio (§7.5).
		$import->register( new Site_Settings() );
		$import->register( new Plugins_Report() );
		$import->register( new Templates() );
		$import->register( new Taxonomies() );
		$import->register( new Elementor_Content() );
		$import->register( new Wp_Content() );
		if ( Module::MODE_ADD === $mode ) {
			$import->register( new Floating_Elements() );
			if ( class_exists( '\WooCommerce' ) ) {
				$import->register( new Woocommerce_Report() );
			}
			$import->register( new Default_Styles_Skip() );
		}
		$import->register( new Element_Defaults() );
	}

	/** `import-kit` (`PHP_INT_MAX`): el runner final, siempre el último que registra K. */
	public static function finish( $import ): void {
		if ( is_object( $import ) && method_exists( $import, 'register' ) && current_user_can( Module::CAPABILITY ) ) {
			$import->register( new Finish() );
		}
	}

	/**
	 * `…/import-kit/runner/after-run` (10): cuando el runner que corrió es el último, la respuesta es el resumen de
	 * lo importado (sin `status`); entonces se cierra la sesión (§7.3).
	 *
	 * @param mixed $result Respuesta del runner.
	 */
	public static function after_run( $result ): void {
		if ( ! is_array( $result ) || isset( $result['status'] ) ) {
			return;
		}
		$session = isset( $result[ Module::RUNNER ]['session'] ) && is_string( $result[ Module::RUNNER ]['session'] ) ? $result[ Module::RUNNER ]['session'] : Context::session();
		if ( Session::valid_id( $session ) && null !== Session::get( $session ) ) {
			Finish::after_save( $session );
		}
	}

	/** `export-kit` (20): contexto de exportación y runners de exportación de K (frente B). */
	public static function export( $export ): void {
		if ( ! is_object( $export ) || ! method_exists( $export, 'register' ) || ! current_user_can( Module::CAPABILITY ) ) {
			return;
		}
		$include = method_exists( $export, 'get_settings_include' ) ? $export->get_settings_include() : array();
		Context::activate_export( is_array( $include ) ? $include : array() );
		foreach ( array( 'Templates', 'Elementor_Content', 'Wp_Content' ) as $name ) {
			$class = '\\' . Module::NAMESPACE_PREFIX . 'Kits\\Export\\' . $name;
			if ( class_exists( $class ) ) {
				$export->register( new $class() );
			}
		}
	}

	/** `export-kit` (`PHP_INT_MAX`): el runner final de exportación escribe `digitalisimo/` y su clave del manifiesto. */
	public static function export_finish( $export ): void {
		if ( is_object( $export ) && method_exists( $export, 'register' ) && null !== Context::export_context() ) {
			$export->register( new Export_Finish() );
		}
	}

	/** `revert-kit` (20): la reversión de los ajustes del kit de K (D2). */
	public static function revert( $revert ): void {
		if ( is_object( $revert ) && method_exists( $revert, 'register' ) && current_user_can( Module::CAPABILITY ) ) {
			$revert->register( new Site_Settings_Revert() );
		}
	}

	/** `revert-kit` (`PHP_INT_MAX`): partes, contadores y papelera (D16). */
	public static function revert_finish( $revert ): void {
		if ( is_object( $revert ) && method_exists( $revert, 'register' ) && current_user_can( Module::CAPABILITY ) ) {
			$revert->register( new Revert_Finish() );
		}
	}

	/** Mayor ID de entrada antes de importar: lo posterior lo creó la sesión (D8). */
	public static function max_post_id(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Respaldo del kit activo (D2): ajustes, metas de las partes y metas v4.
	 */
	public static function backup( int $kit_id ): array {
		$backup = array(
			'kit_id'          => $kit_id,
			'settings_before' => array(),
			'metas_before'    => array(),
			'v4_before'       => array(),
			'hash_after'      => '',
			'kit_settings'    => array(),
			'added'           => array(),
		);
		if ( $kit_id <= 0 ) {
			return $backup;
		}
		$settings                  = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$backup['settings_before'] = is_array( $settings ) ? $settings : array();
		foreach ( array_unique( array_merge( self::KIT_METAS, Parts::kit_meta_keys() ) ) as $key ) {
			$backup['metas_before'][ $key ] = metadata_exists( 'post', $kit_id, $key ) ? get_post_meta( $kit_id, $key, true ) : null;
		}
		foreach ( (array) get_post_meta( $kit_id ) as $key => $values ) {
			foreach ( self::V4_PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					$backup['v4_before'][ $key ] = maybe_unserialize( is_array( $values ) ? reset( $values ) : $values );
				}
			}
		}
		return $backup;
	}
}
