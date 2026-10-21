<?php
namespace Digitalisimo\Elements\Import\Kits\Runners;

use Digitalisimo\Elements\Import\Module;
use Digitalisimo\Elements\Import\Kits\Context;
use Digitalisimo\Elements\Import\Kits\Normalizer;
use Digitalisimo\Elements\Import\Kits\Part_Context;
use Digitalisimo\Elements\Import\Kits\Session;
use Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base;
use Elementor\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Runner `site-settings` de K (§7.5, D2, D3, D11, §9.4, CR-5, CC-7).
 *
 * Nunca crea un kit, nunca instala ni activa el tema, nunca cambia experimentos: escribe en el **kit activo** con
 * `$kit->save()`, que aplica kses y los ganchos de guardado.
 * - «Nuevo»: importa los medios de los ajustes con el `on_import` de cada control del kit y reemplaza los ajustes,
 *   sin identidad.
 * - «Agregar»: sólo añade colores y tipografías (§9.4) y deja el resto en el respaldo, para «Aplicar los ajustes del
 *   kit» (que se puede deshacer).
 * Su metadata nativa queda inerte (`active_kit_id = 0`): la reversión nativa aborta sin borrar nada (V-30).
 */
class Site_Settings extends Import_Runner_Base {
	/** Grupos de ajustes que respetan las casillas de la App (`customization.settings`) o el manifiesto. */
	const GROUPS = array(
		'globalColors'       => array( 'system_colors', 'custom_colors' ),
		'globalFonts'        => array( 'system_typography', 'custom_typography', 'default_generic_fonts' ),
		'themeStyleSettings' => '/^(?:body_|h[1-6]_|button_|link_|form_field_)/',
		'generalSettings'    => array( 'template', 'container_width', 'container_padding', 'space_between_widgets', 'viewport_md', 'viewport_lg', 'page_title_selector', 'activeItemIndex' ),
	);

	/** @var string */
	private $mode = Module::MODE_ADD;

	/** @var array Resumen para el informe. */
	private $summary = array();

	public static function get_name(): string {
		return 'site-settings';
	}

	public function should_import( array $data ) {
		return isset( $data['include'] ) && is_array( $data['include'] ) && in_array( 'settings', $data['include'], true )
			&& ! empty( $data['site_settings']['settings'] ) && is_array( $data['site_settings']['settings'] );
	}

	public function import( array $data, array $imported_data ) {
		$session = (string) ( $data['session_id'] ?? '' );
		$record  = Session::get( $session );
		$record  = null === $record ? array() : $record;
		$kit     = Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit || ! $kit->get_id() ) {
			return array();
		}
		$this->mode = Module::MODE_FRESH === ( $record['mode'] ?? '' ) ? Module::MODE_FRESH : Module::MODE_ADD;

		$new = self::filter(
			$data['site_settings']['settings'],
			isset( $data['customization']['settings'] ) && is_array( $data['customization']['settings'] ) ? $data['customization']['settings'] : null,
			isset( $data['manifest']['site-settings'] ) && is_array( $data['manifest']['site-settings'] ) ? $data['manifest']['site-settings'] : array()
		);
		$old = $kit->get_meta( '_elementor_page_settings' );
		$old = is_array( $old ) ? $old : array();

		$backup = isset( $record['backup'] ) && is_array( $record['backup'] ) ? $record['backup'] : array();
		$map    = array(
			'global_color'      => array(),
			'global_typography' => array(),
		);
		if ( Module::MODE_FRESH === $this->mode ) {
			$new   = self::import_media( $kit, $new );
			$final = array_replace( $old, $new );
			self::save_kit( $kit, $final );
			$this->summary = array( 'replaced' => count( $new ) );
		} else {
			$used   = self::used_globals( (string) ( $data['extracted_directory_path'] ?? '' ) );
			$merged = self::merge_globals( $old, $new, (string) ( $record['origin']['kit'] ?? '' ), $used );
			if ( $merged['settings'] !== $old ) {
				self::save_kit( $kit, $merged['settings'], true );
			}
			$map                    = $merged['map'];
			$backup['kit_settings'] = $new;
			$backup['added']        = $merged['added'];
			$this->summary          = array(
				'colors'      => count( $merged['added']['custom_colors'] ),
				'typography'  => count( $merged['added']['custom_typography'] ),
				'not_applied' => count( array_diff_key( $new, array_flip( array( 'system_colors', 'custom_colors', 'system_typography', 'custom_typography' ) ) ) ),
			);
			if ( $this->summary['not_applied'] > 0 ) {
				Context::report( 'settings.not_applied', array( 'count' => $this->summary['not_applied'] ), Part_Context::INFO );
			}
		}
		Session::update(
			$session,
			array(
				'settings_map' => $map,
				'backup'       => $backup,
			)
		);
		Context::refresh();

		return array(
			'site-settings' => array(
				'imported_kit_id' => 0,
				Module::RUNNER    => $this->mode,
			),
		);
	}

	/** Metadata nativa inerte (D2, V-30): `revert_theme()` y `revert_experiments()` no hacen nada con ella. */
	public function get_import_session_metadata(): array {
		return array(
			'previous_kit_id'       => 0,
			'active_kit_id'         => 0,
			'imported_kit_id'       => 0,
			'installed_theme'       => null,
			'activated_theme'       => null,
			'previous_active_theme' => null,
			'previous_experiments'  => array(),
			'imported_experiments'  => array(),
			Module::RUNNER          => array(
				'mode'    => $this->mode,
				'summary' => $this->summary,
			),
		);
	}

	/**
	 * Ajustes del kit filtrados: sin identidad, sin tema ni experimentos y respetando las casillas de la App o, sin
	 * ellas, los grupos que declara el manifiesto.
	 *
	 * @param array      $settings      Ajustes del kit.
	 * @param array|null $customization `customization.settings` de la App.
	 * @param array      $manifest      `manifest['site-settings']`.
	 */
	public static function filter( array $settings, ?array $customization, array $manifest ): array {
		foreach ( Normalizer::IDENTITY as $key ) {
			unset( $settings[ $key ] );
		}
		unset( $settings['theme'], $settings['experiments'] );
		foreach ( self::GROUPS as $group => $keys ) {
			if ( null !== $customization ) {
				$on = ! array_key_exists( $group, $customization ) || ! empty( $customization[ $group ] );
			} else {
				$on = array() === $manifest || ! empty( $manifest[ $group ] );
			}
			if ( $on ) {
				continue;
			}
			if ( is_string( $keys ) ) {
				foreach ( array_keys( $settings ) as $key ) {
					if ( preg_match( $keys, (string) $key ) ) {
						unset( $settings[ $key ] );
					}
				}
			} else {
				foreach ( $keys as $key ) {
					unset( $settings[ $key ] );
				}
			}
		}
		return $settings;
	}

	/**
	 * «Agregar» (§9.4): los colores y tipografías personalizados del kit se reutilizan (mismo `_id` y valor), se
	 * renombran a un `_id` estable `k<6 hex>` (mismo `_id`, otro valor) o se añaden. Los del sistema que usa el
	 * contenido y difieren entran como personalizados «<título> (kit)». Nunca se reemplaza nada del sitio.
	 *
	 * @param array  $old  Ajustes actuales del kit activo.
	 * @param array  $new  Ajustes del kit importado.
	 * @param string $kit  Huella del kit.
	 * @param array  $used `colors` y `typography`: `_id` globales que usa el contenido.
	 *
	 * @return array{settings: array, map: array, added: array}
	 */
	public static function merge_globals( array $old, array $new, string $kit, array $used ): array {
		$settings = $old;
		$map      = array(
			'global_color'      => array(),
			'global_typography' => array(),
		);
		$added    = array(
			'custom_colors'     => array(),
			'custom_typography' => array(),
		);
		$kinds    = array(
			'global_color'      => array( 'custom_colors', 'system_colors', 'colors' ),
			'global_typography' => array( 'custom_typography', 'system_typography', 'typography' ),
		);
		foreach ( $kinds as $map_name => list( $custom_key, $system_key, $used_key ) ) {
			$list = isset( $settings[ $custom_key ] ) && is_array( $settings[ $custom_key ] ) ? array_values( $settings[ $custom_key ] ) : array();
			$have = array();
			foreach ( $list as $item ) {
				if ( is_array( $item ) && isset( $item['_id'] ) ) {
					$have[ (string) $item['_id'] ] = self::global_value( $item );
				}
			}
			$system = array();
			foreach ( isset( $old[ $system_key ] ) && is_array( $old[ $system_key ] ) ? $old[ $system_key ] : array() as $item ) {
				if ( is_array( $item ) && isset( $item['_id'] ) ) {
					$system[ (string) $item['_id'] ] = self::global_value( $item );
				}
			}
			$candidates = array();
			foreach ( isset( $new[ $custom_key ] ) && is_array( $new[ $custom_key ] ) ? $new[ $custom_key ] : array() as $item ) {
				if ( is_array( $item ) && isset( $item['_id'] ) && '' !== (string) $item['_id'] ) {
					$candidates[] = array( $item, false );
				}
			}
			foreach ( isset( $new[ $system_key ] ) && is_array( $new[ $system_key ] ) ? $new[ $system_key ] : array() as $item ) {
				$id = is_array( $item ) && isset( $item['_id'] ) ? (string) $item['_id'] : '';
				if ( '' === $id || ! in_array( $id, isset( $used[ $used_key ] ) ? $used[ $used_key ] : array(), true ) ) {
					continue;
				}
				if ( isset( $system[ $id ] ) && $system[ $id ] === self::global_value( $item ) ) {
					continue;
				}
				$item['title'] = trim( ( isset( $item['title'] ) ? (string) $item['title'] : $id ) . ' (kit)' );
				$candidates[]  = array( $item, true );
			}
			foreach ( $candidates as list( $item, $from_system ) ) {
				$id    = (string) $item['_id'];
				$value = self::global_value( $item );
				if ( ! $from_system && isset( $have[ $id ] ) && $have[ $id ] === $value ) {
					continue;
				}
				$target = $id;
				if ( $from_system || isset( $have[ $id ] ) ) {
					$target = self::derived_id( $kit, $id, $value );
				}
				$map[ $map_name ][ $id ] = $target;
				if ( isset( $have[ $target ] ) ) {
					if ( $have[ $target ] === $value ) {
						continue;
					}
					$target                  = self::derived_id( $kit, $id . '|' . $target, $value );
					$map[ $map_name ][ $id ] = $target;
				}
				$item['_id']           = $target;
				$list[]                = $item;
				$have[ $target ]       = $value;
				$added[ $custom_key ][] = $target;
			}
			foreach ( $map[ $map_name ] as $from => $to ) {
				if ( $from === $to ) {
					unset( $map[ $map_name ][ $from ] );
				}
			}
			if ( $added[ $custom_key ] ) {
				$settings[ $custom_key ] = $list;
			}
		}
		return array(
			'settings' => $settings,
			'map'      => $map,
			'added'    => $added,
		);
	}

	/** `_id` estable de un global del kit que choca: `k` + 6 hex de `sha1( kit | _id | valor )` (CV-10). */
	public static function derived_id( string $kit, string $id, string $value ): string {
		return 'k' . substr( sha1( $kit . '|' . $id . '|' . $value ), 0, 6 );
	}

	/** Valor de un global, sin `_id` ni `title`, como texto estable. */
	private static function global_value( array $item ): string {
		unset( $item['_id'], $item['title'] );
		if ( 1 === count( $item ) && isset( $item['color'] ) ) {
			return strtolower( (string) $item['color'] );
		}
		return Part_Context::hash( $item );
	}

	/**
	 * `_id` de colores y tipografías globales que usa el contenido del kit (`globals/colors?id=…`).
	 *
	 * @return array{colors: string[], typography: string[]}
	 */
	public static function used_globals( string $dir ): array {
		$used = array(
			'colors'     => array(),
			'typography' => array(),
		);
		$real = '' === $dir ? false : realpath( $dir );
		if ( false === $real ) {
			return $used;
		}
		$files = array_merge(
			(array) glob( $real . '/templates/*.json' ),
			(array) glob( $real . '/content/*/*.json' ),
			(array) glob( $real . '/kit-elements-defaults.json' ),
			(array) glob( $real . '/digitalisimo/element-defaults.json' )
		);
		foreach ( $files as $file ) {
			$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- archivo extraído del kit.
			if ( preg_match_all( '#globals(?:\\\\)?/(colors|typography)\?id=([A-Za-z0-9_-]+)#', $text, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$used[ $match[1] ][ $match[2] ] = true;
				}
			}
		}
		return array(
			'colors'     => array_keys( $used['colors'] ),
			'typography' => array_keys( $used['typography'] ),
		);
	}

	/**
	 * Medios de los ajustes del kit con el `on_import` de cada control (CC-7): fondos, botones, precargador de
	 * transiciones. Pasan por el proxy de medios del contexto.
	 */
	public static function import_media( $kit, array $settings ): array {
		if ( ! is_object( $kit ) || ! method_exists( $kit, 'process_element_import_export' ) ) {
			return $settings;
		}
		try {
			$result = $kit->process_element_import_export( $kit, 'on_import', array( 'settings' => $settings ) );
		} catch ( \Throwable $error ) {
			Context::report( 'settings.media', array( 'message' => $error->getMessage() ), Part_Context::WARNING );
			return $settings;
		}
		return isset( $result['settings'] ) && is_array( $result['settings'] ) ? $result['settings'] : $settings;
	}

	/**
	 * Guarda los ajustes del kit con `$kit->save()` (CR-5), fuera de la captura de elementos del núcleo: si no, la
	 * petición final volvería a guardar estos ajustes encima de lo que hagan las partes.
	 */
	public static function save_kit( $kit, array $settings, bool $exact = false ): void {
		$keys                    = $settings;
		$settings['post_status'] = get_post_status( $kit->get_id() );
		$import                  = null;
		$module                  = class_exists( '\Elementor\Plugin' ) && isset( Plugin::$instance->app ) ? Plugin::$instance->app->get_component( 'import-export-customization' ) : null;
		if ( is_object( $module ) && isset( $module->import ) && is_object( $module->import ) ) {
			$import = $module->import;
		}
		$callback = null === $import ? null : array( $import, 'prevent_saving_elements_on_post_creation' );
		$attached = null !== $callback && false !== has_filter( 'elementor/document/save/data', $callback );
		if ( $attached ) {
			remove_filter( 'elementor/document/save/data', $callback, 10 );
		}
		try {
			$kit->save( array( 'settings' => $settings ) );
		} finally {
			if ( $attached ) {
				add_filter( 'elementor/document/save/data', $callback, 10, 2 );
			}
		}
		if ( ! $exact ) {
			return;
		}
		// El guardado del kit añade por su cuenta ajustes que no se pidieron (los puntos de corte heredados
		// `viewport_md` y `viewport_lg`, con su valor por defecto). En «agregar», al revertir y al deshacer, el kit
		// debe quedar exactamente con las claves pedidas (D11, A7, A10): lo añadido se quita, lo saneado se conserva.
		$saved = get_post_meta( (int) $kit->get_id(), '_elementor_page_settings', true );
		if ( is_array( $saved ) ) {
			$extra = array_diff_key( $saved, $keys );
			if ( $extra ) {
				update_post_meta( (int) $kit->get_id(), '_elementor_page_settings', wp_slash( array_diff_key( $saved, $extra ) ) );
			}
		}
	}

	/**
	 * «Aplicar los ajustes del kit» (D11): respalda los ajustes actuales y aplica los del kit con sus medios. Los
	 * globales se unen por `_id` (lo del sitio primero), así que no se pierde ninguno.
	 *
	 * @return true|\WP_Error
	 */
	public static function apply( string $session ) {
		$record = Session::get( $session );
		if ( null === $record || Module::MODE_ADD !== ( $record['mode'] ?? '' ) || empty( $record['backup']['kit_settings'] ) ) {
			return new \WP_Error( 'digitalisimo_kit_apply', 'Esta importación no tiene ajustes del kit pendientes.' );
		}
		if ( ! empty( $record['backup']['applied_hash'] ) ) {
			return new \WP_Error( 'digitalisimo_kit_apply', 'Los ajustes del kit ya se aplicaron.' );
		}
		$kit = Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit || ! $kit->get_id() ) {
			return new \WP_Error( 'digitalisimo_kit_apply', 'El sitio no tiene un kit activo.' );
		}
		$current  = $kit->get_meta( '_elementor_page_settings' );
		$current  = is_array( $current ) ? $current : array();
		$settings = $record['backup']['kit_settings'];
		Context::activate( $session );
		try {
			$settings = self::import_media( $kit, $settings );
		} finally {
			Context::deactivate();
		}
		$final = array_replace( $current, $settings );
		foreach ( array( 'custom_colors', 'custom_typography' ) as $key ) {
			$final[ $key ] = self::union( isset( $current[ $key ] ) && is_array( $current[ $key ] ) ? $current[ $key ] : array(), isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : array() );
		}
		self::save_kit( $kit, $final );
		$after                    = $kit->get_meta( '_elementor_page_settings' );
		$backup                   = $record['backup'];
		$backup['applied_before'] = $current;
		$backup['applied_hash']   = Part_Context::hash( is_array( $after ) ? $after : array() );
		Session::update(
			$session,
			array(
				'backup'  => $backup,
				'applied' => time(),
			)
		);
		// Los medios del kit ya son adjuntos del sitio: la carpeta preparada ya no hace falta.
		if ( ! empty( $record['token'] ) ) {
			Session::delete_tmp( (string) $record['token'] );
		}
		return true;
	}

	/**
	 * Deshace «Aplicar» si nadie cambió el kit después.
	 *
	 * @return true|\WP_Error
	 */
	public static function unapply( string $session ) {
		$record = Session::get( $session );
		if ( null === $record || empty( $record['backup']['applied_hash'] ) ) {
			return new \WP_Error( 'digitalisimo_kit_unapply', 'Los ajustes del kit no se aplicaron.' );
		}
		$kit     = Plugin::$instance->kits_manager->get_active_kit();
		$current = $kit ? $kit->get_meta( '_elementor_page_settings' ) : null;
		if ( ! $kit || Part_Context::hash( is_array( $current ) ? $current : array() ) !== $record['backup']['applied_hash'] ) {
			return new \WP_Error( 'digitalisimo_kit_unapply', 'El kit cambió después de aplicar sus ajustes: no se deshace para no perder esos cambios.' );
		}
		self::save_kit( $kit, isset( $record['backup']['applied_before'] ) && is_array( $record['backup']['applied_before'] ) ? $record['backup']['applied_before'] : array(), true );
		$backup = $record['backup'];
		unset( $backup['applied_before'], $backup['applied_hash'] );
		Session::update(
			$session,
			array(
				'backup'  => $backup,
				'applied' => 0,
			)
		);
		return true;
	}

	/** Une dos listas de globales por `_id`: la primera manda. */
	private static function union( array $first, array $second ): array {
		$ids = array();
		foreach ( $first as $item ) {
			if ( is_array( $item ) && isset( $item['_id'] ) ) {
				$ids[ (string) $item['_id'] ] = true;
			}
		}
		foreach ( $second as $item ) {
			if ( is_array( $item ) && isset( $item['_id'] ) && ! isset( $ids[ (string) $item['_id'] ] ) ) {
				$first[]                      = $item;
				$ids[ (string) $item['_id'] ] = true;
			}
		}
		return array_values( $first );
	}
}
