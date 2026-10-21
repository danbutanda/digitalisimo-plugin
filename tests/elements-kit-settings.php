<?php
/**
 * Kits (etapa K, §13.1 «settings»): ajustes del kit al importar y al revertir (D2, D3, D11, §9.4, CR-5, CR-11, V-31).
 *
 * Sin WordPress: tests/sources/kits/wp-stubs.php y un kit activo en memoria cuyo único camino de guardado es
 * `$kit->save()` (escribir la meta directamente falla, CR-5). Las clases del núcleo que K extiende o a las que delega
 * (runners, Kits\Manager::revert) se cargan de digitalisimo-elements/elementor/ tal cual.
 *  1. «Agregar»: añadir; `_id` estable `k<hex>` igual en dos sesiones (CV-10); reutilizar con el mismo valor; los
 *     del sistema que usa el contenido entran como personalizados «(kit)».
 *  2. Estilo del tema, CSS y puntos de corte no se aplican y quedan en backup.kit_settings.
 *  3. «Nuevo»: reemplazo sin identidad, con $kit->save() y los medios del kit importados (CC-7).
 *  4. Nunca switch_theme, Theme_Upgrader, elementor_experiment-* ni create_new_kit.
 *  5. Reversión: hash igual → completa, con v4_before; kit editado → sólo lo añadido; sin `mode` → el nativo; con los
 *     metadatos nativos inertes, Kits\Manager::revert() real no borra nada (CR-11) y tema y experimentos no cambian.
 *  6. customization.settings respeta las casillas; theme y experiments quedan en false tras el adaptador 2.0 (V-31).
 *  7. En «agregar», los runners v4 reciben skip (CC-10, CV-18).
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $kits_manager;
		public $documents;
		public $app;
		public $experiments;
	}
}

namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';

	// Lo que nunca debe llamarse (punto 4): cada llamada queda anotada.
	$GLOBALS['kt_forbidden'] = array();
	function switch_theme( $stylesheet ) { $GLOBALS['kt_forbidden'][] = 'switch_theme'; }
	function delete_theme( $stylesheet ) { $GLOBALS['kt_forbidden'][] = 'delete_theme'; return true; }
	function wp_get_theme( $slug = '' ) { return new class() { public function exists() { return false; } public function get_stylesheet() { return 'actual'; } }; }
	class Theme_Upgrader {
		public function __construct( ...$args ) { $GLOBALS['kt_forbidden'][] = 'Theme_Upgrader'; }
		public function install( $url ) { $GLOBALS['kt_forbidden'][] = 'Theme_Upgrader::install'; return false; }
	}

	/** Kit activo en memoria: sólo save() guarda los ajustes (CR-5). */
	class Kt_Kit {
		public $id;
		public $saves = array();
		public $imports = array();
		public function __construct( $id ) { $this->id = $id; }
		public function get_id() { return $this->id; }
		public function get_main_id() { return $this->id; }
		public function get_meta( $key ) { return get_post_meta( $this->id, $key, true ); }
		public function get_settings() { $s = $this->get_meta( '_elementor_page_settings' ); return is_array( $s ) ? $s : array(); }
		public function update_meta( $key, $value ) { throw new RuntimeException( "CR-5: los ajustes del kit se guardan con \$kit->save(), no con update_meta( {$key} )." ); }
		public function update_settings( $settings ) { throw new RuntimeException( 'CR-5: los ajustes del kit se guardan con $kit->save().' ); }
		public function save( array $data ) {
			$this->saves[] = $data;
			if ( isset( $data['settings'] ) ) {
				$settings = $data['settings'];
				unset( $settings['post_status'] );
				update_post_meta( $this->id, '_elementor_page_settings', $settings );
			}
			return true;
		}
		/** El `on_import` de los controles del kit (fondos, botones, precargador): marca las URL como importadas. */
		public function process_element_import_export( $element, $method, $data = null ) {
			$this->imports[] = $method;
			foreach ( $data['settings'] as $key => $value ) {
				if ( is_array( $value ) && isset( $value['url'] ) ) {
					$data['settings'][ $key ] = array( 'id' => 900, 'url' => 'https://b.test/wp-content/uploads/importada.png' );
				}
			}
			return $data;
		}
	}
	class Kt_Kits_Manager {
		public $kit;
		public $calls = array();
		public function __construct( $kit ) { $this->kit = $kit; }
		public function get_active_kit() { return $this->kit; }
		public function create_new_kit( ...$args ) { $GLOBALS['kt_forbidden'][] = 'create_new_kit'; return 0; }
		public function revert( $imported, $active, $previous ) { $this->calls[] = array( $imported, $active, $previous ); }
	}

	/** Proceso de importación del núcleo con la superficie que usa Import_Hooks::configure(). */
	class Kt_Import {
		public $session, $manifest, $include, $customization, $plugins = array( 'x' ), $overrides = array( 'y' ), $registered = array();
		public function __construct( $session, array $manifest, array $include, $customization ) {
			$this->session = $session; $this->manifest = $manifest; $this->include = $include; $this->customization = $customization;
		}
		public function get_session_id() { return $this->session; }
		public function get_manifest() { return $this->manifest; }
		public function settings_selected_override_conditions( array $value ) { $this->overrides = $value; }
		public function settings_selected_plugins( array $value ) { $this->plugins = $value; }
		public function get_settings_include() { return $this->include; }
		public function settings_include( array $value ) { $this->include = $value; }
		public function get_settings_customization() { return $this->customization; }
		public function settings_customization( $value ) { $this->customization = $value; }
		public function register( $runner ) { $this->registered[ $runner::get_name() ] = get_class( $runner ); }
	}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Context;
	use Digitalisimo\Elements\Import\Kits\Session;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\Runners\Site_Settings;
	use Digitalisimo\Elements\Import\Kits\Runners\Site_Settings_Revert;
	use Digitalisimo\Elements\Import\Kits\Runners\Import_Hooks;

	foreach ( array( Site_Settings::class, Site_Settings_Revert::class, Import_Hooks::class ) as $class ) {
		kt_check( class_exists( $class ), "Falta {$class} (frente A)." );
	}

	$session_a = 'kses0000000a1';
	$session_b = 'kses0000000b2';
	$kit_hash  = sha1( 'kit-de-ajustes' );
	$site      = static function () use ( $session_a, $session_b ) {
		Context::deactivate();
		Session::forget( $session_a );
		Session::forget( $session_b );
		kt_reset();
		$kit = new Kt_Kit( 5 );
		wp_insert_post( array( 'import_id' => 5, 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_name' => 'default-kit' ) );
		update_post_meta( 5, '_elementor_template_type', 'kit' );
		update_post_meta( 5, '_elementor_page_settings', array(
			'system_colors'     => array( array( '_id' => 'primary', 'title' => 'Principal', 'color' => '#111111' ), array( '_id' => 'text', 'title' => 'Texto', 'color' => '#222222' ) ),
			'custom_colors'     => array( array( '_id' => 'a1b2c3d', 'title' => 'Marca del sitio', 'color' => '#00AA55' ), array( '_id' => 'b2c3d4e', 'title' => 'Igual', 'color' => '#F6F1E8' ) ),
			'custom_typography' => array(),
			'custom_css'        => '/* sitio */',
			'container_width'   => array( 'unit' => 'px', 'size' => 1140 ),
			'body_typography_font_family' => 'Arial',
			'site_name'         => 'Sitio B',
			'viewport_md'       => 768,
		) );
		update_option( 'elementor_active_kit', 5 );
		\Elementor\Plugin::$instance               = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->kits_manager = new Kt_Kits_Manager( $kit );
		update_option( 'show_on_front', 'posts' );
		return $kit;
	};
	$extracted = static function () {
		$dir = $GLOBALS['kt']['root'] . '/kit-extraido';
		@mkdir( $dir . '/content/page', 0777, true ); // phpcs:ignore
		file_put_contents( $dir . '/content/page/10.json', json_encode( array( 'content' => array( array( 'id' => 'a000001', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( '__globals__' => array( 'title_color' => 'globals/colors?id=primary', 'typography_typography' => 'globals/typography?id=t1' ) ) ) ) ) ) );
		return $dir;
	};
	$kit_settings = array(
		'system_colors'     => array( array( '_id' => 'primary', 'title' => 'Principal', 'color' => '#7A1FA2' ), array( '_id' => 'text', 'title' => 'Texto', 'color' => '#222222' ) ),
		'custom_colors'     => array(
			array( '_id' => 'a1b2c3d', 'title' => 'Marca del kit', 'color' => '#123ABC' ),
			array( '_id' => 'b2c3d4e', 'title' => 'Igual', 'color' => '#f6f1e8' ),
			array( '_id' => 'c9d8e7f', 'title' => 'Nuevo', 'color' => '#EEEEEE' ),
		),
		'custom_typography' => array( array( '_id' => 't1', 'title' => 'Titular', 'typography_typography' => 'custom', 'typography_font_family' => 'Georgia' ) ),
		'custom_css'        => '/* kit */',
		'container_width'   => array( 'unit' => 'px', 'size' => 1300 ),
		'body_typography_font_family' => 'Verdana',
		'viewport_md'       => 900,
		'body_background_image' => array( 'id' => 77, 'url' => 'http://origen.test/wp-content/uploads/fondo.png' ),
		'site_name'         => 'Identidad del kit',
		'site_logo'         => array( 'id' => 99, 'url' => 'http://origen.test/logo.png' ),
		'theme'             => array( 'slug' => 'acme' ),
		'experiments'       => array( 'container' => 'inactive' ),
	);
	$bind = static function ( $session, $mode ) use ( $kit_hash ) {
		Session::bind( $session, array( 'mode' => $mode, 'requested' => $mode, 'origin' => array( 'kit' => $kit_hash, 'home' => 'http://origen.test', 'site' => 'http://origen.test', 'uploads' => 'http://origen.test/wp-content/uploads' ) ) );
		Session::update( $session, array( 'backup' => Import_Hooks::backup( 5 ), 'kit_id' => 5 ) );
	};
	$run = static function ( $session, array $settings, $customization = null, array $manifest = array() ) use ( $extracted ) {
		$runner = new Site_Settings();
		return $runner->import(
			array(
				'session_id'               => $session,
				'include'                  => array( 'settings' ),
				'site_settings'            => array( 'settings' => $settings ),
				'customization'            => null === $customization ? array() : array( 'settings' => $customization ),
				'manifest'                 => array( 'site-settings' => $manifest ),
				'extracted_directory_path' => $extracted(),
			),
			array()
		);
	};
	$by_id = static function ( array $list ) {
		$out = array();
		foreach ( $list as $item ) {
			$out[ $item['_id'] ] = $item;
		}
		return $out;
	};

	// ── 1 y 2. «Agregar» ────────────────────────────────────────────────────────────────────────────────────────
	$kit = $site();
	$before = $kit->get_settings();
	$bind( $session_a, Module::MODE_ADD );
	$run( $session_a, $kit_settings );
	$after  = $kit->get_settings();
	$colors = $by_id( $after['custom_colors'] );
	kt_check( '#00AA55' === $colors['a1b2c3d']['color'] && 'Marca del sitio' === $colors['a1b2c3d']['title'], '§9.4: un color del sitio con el mismo _id nunca se pisa.' );
	$derived = Site_Settings::derived_id( $kit_hash, 'a1b2c3d', '#123abc' );
	kt_check( 1 === preg_match( '/^k[0-9a-f]{6}$/', $derived ) && isset( $colors[ $derived ] ) && '#123ABC' === $colors[ $derived ]['color'], '§9.4: mismo _id con otro valor → _id nuevo k<6 hex> derivado del kit, del _id y del valor (CV-10).' );
	kt_check( isset( $colors['c9d8e7f'] ) && '#EEEEEE' === $colors['c9d8e7f']['color'], '§9.4: sin choque, el color se añade con su _id.' );
	kt_check( 1 === count( array_filter( $after['custom_colors'], static function ( $c ) { return 'b2c3d4e' === $c['_id']; } ) ), '§9.4: mismo _id y mismo valor (sin distinguir mayúsculas) → se reutiliza, sin duplicar.' );
	$system_derived = Site_Settings::derived_id( $kit_hash, 'primary', '#7a1fa2' );
	kt_check( isset( $colors[ $system_derived ] ) && false !== strpos( $colors[ $system_derived ]['title'], '(kit)' ), '§9.4: un color del sistema que usa el contenido y difiere entra como personalizado «<título> (kit)».' );
	kt_check( ! isset( $colors[ Site_Settings::derived_id( $kit_hash, 'text', '#222222' ) ] ), '§9.4: un color del sistema igual al del sitio no se añade.' );
	kt_check( '#111111' === $by_id( $after['system_colors'] )['primary']['color'], '§9.4: los colores del sistema del sitio no cambian en «agregar».' );
	$typo = $by_id( $after['custom_typography'] );
	kt_check( isset( $typo['t1'] ) && 'Georgia' === $typo['t1']['typography_font_family'], '§9.4: las tipografías del kit también se añaden.' );
	foreach ( array( 'custom_css', 'container_width', 'body_typography_font_family', 'viewport_md', 'site_name' ) as $key ) {
		kt_check( $before[ $key ] === $after[ $key ], "D11: «{$key}» no se aplica en «agregar»." );
	}
	$record = Session::get( $session_a );
	foreach ( array( 'custom_css', 'container_width', 'body_typography_font_family', 'viewport_md', 'body_background_image' ) as $key ) {
		kt_check( isset( $record['backup']['kit_settings'][ $key ] ), "D11: «{$key}» queda en backup.kit_settings para «Aplicar los ajustes del kit»." );
	}
	foreach ( array( 'site_name', 'site_logo', 'theme', 'experiments' ) as $key ) {
		kt_check( ! isset( $record['backup']['kit_settings'][ $key ] ), "§4.1: «{$key}» nunca se guarda para aplicarse después (identidad, tema y experimentos)." );
	}
	kt_check( $derived === $record['settings_map']['global_color']['a1b2c3d'] && $system_derived === $record['settings_map']['global_color']['primary'], '§9.4: settings_map traduce los _id que cambiaron para el Remapper.' );
	kt_check( array() !== $kit->saves && array( 'settings' ) === array_keys( end( $kit->saves ) ), 'CR-5: «agregar» guarda los globales con $kit->save().' );
	// Segunda sesión del mismo kit: los mismos _id y ninguna copia más (CV-10, A8).
	$count = count( $after['custom_colors'] );
	$bind( $session_b, Module::MODE_ADD );
	$run( $session_b, $kit_settings );
	$again = $kit->get_settings();
	kt_check( $count === count( $again['custom_colors'] ), 'CV-10: reimportar el mismo kit no añade colores (los _id derivados ya existen con el mismo valor).' );
	kt_check( $derived === Session::get( $session_b )['settings_map']['global_color']['a1b2c3d'], 'CV-10: el _id derivado es el mismo en dos sesiones.' );

	// ── 3. «Nuevo» ──────────────────────────────────────────────────────────────────────────────────────────────
	$kit = $site();
	$bind( $session_a, Module::MODE_FRESH );
	$result = $run( $session_a, $kit_settings );
	$after  = $kit->get_settings();
	kt_check( '#7A1FA2' === $by_id( $after['system_colors'] )['primary']['color'] && '/* kit */' === $after['custom_css'] && 900 === $after['viewport_md'], '«Nuevo»: los ajustes del kit reemplazan los del sitio.' );
	kt_check( 'Sitio B' === $after['site_name'] && ! isset( $after['site_logo'] ), '«Nuevo»: la identidad del sitio no viaja (§4.1 #7).' );
	kt_check( ! isset( $after['theme'] ) && ! isset( $after['experiments'] ), '«Nuevo»: ni tema ni experimentos en los ajustes.' );
	kt_check( in_array( 'on_import', $kit->imports, true ) && 900 === $after['body_background_image']['id'], 'CC-7: los medios de los ajustes del kit se importan con el on_import de sus controles.' );
	kt_check( 0 === $result['site-settings']['imported_kit_id'], 'D2: el runner escribe en el kit activo, nunca en un kit nuevo.' );

	// ── 4. Nada instala, activa ni crea ─────────────────────────────────────────────────────────────────────────
	$experiments = array_filter( array_keys( $GLOBALS['kt']['options'] ), static function ( $name ) { return 0 === strpos( $name, 'elementor_experiment-' ); } );
	kt_check( array() === $GLOBALS['kt_forbidden'] && array() === $experiments && 5 === (int) get_option( 'elementor_active_kit' ), 'Nunca switch_theme, Theme_Upgrader, elementor_experiment-* ni create_new_kit: ' . implode( ', ', array_merge( $GLOBALS['kt_forbidden'], $experiments ) ) );

	// ── 5. Reversión ────────────────────────────────────────────────────────────────────────────────────────────
	$revert = static function ( $session, array $pointer ) {
		$runner = new Site_Settings_Revert();
		$meta   = ( new Site_Settings() )->get_import_session_metadata();
		$runner->revert( array( 'session_id' => $session, 'runners' => array( 'site-settings' => $meta, Module::RUNNER => $pointer ) ) );
	};
	$kit = $site();
	update_post_meta( 5, '_elementor_global_variables', array( 'data' => array( 'e-gv-1' => array( 'value' => '#000' ) ) ) );
	$original = $kit->get_settings();
	$bind( $session_a, Module::MODE_ADD );
	$run( $session_a, $kit_settings );
	update_post_meta( 5, '_elementor_global_variables', array( 'data' => array( 'e-gv-1' => array( 'value' => '#FFF' ) ) ) );
	$record                         = Session::get( $session_a );
	$record['backup']['hash_after'] = Part_Context::hash( $kit->get_settings() );
	$record['backup']['metas_after'] = array( '_elementor_global_variables' => Part_Context::hash( get_post_meta( 5, '_elementor_global_variables', true ) ) );
	Session::update( $session_a, array( 'backup' => $record['backup'] ) );
	$revert( $session_a, array( 'v' => 1, 'mode' => Module::MODE_ADD ) );
	kt_check( $original === $kit->get_settings(), 'Reversión con el hash igual: los ajustes del kit vuelven a settings_before.' );
	kt_check( array( 'data' => array( 'e-gv-1' => array( 'value' => '#000' ) ) ) === get_post_meta( 5, '_elementor_global_variables', true ), 'Reversión: las metas v4 del kit vuelven a v4_before (CC-10).' );
	// Kit editado después de importar: sólo se quita lo añadido.
	$kit = $site();
	$bind( $session_a, Module::MODE_ADD );
	$run( $session_a, $kit_settings );
	$record                         = Session::get( $session_a );
	$record['backup']['hash_after'] = Part_Context::hash( $kit->get_settings() );
	Session::update( $session_a, array( 'backup' => $record['backup'] ) );
	$edited                = $kit->get_settings();
	$edited['custom_css']  = '/* editado después */';
	$kit->save( array( 'settings' => $edited ) );
	$revert( $session_a, array( 'v' => 1, 'mode' => Module::MODE_ADD ) );
	$after = $kit->get_settings();
	kt_check( '/* editado después */' === $after['custom_css'], 'Reversión con el kit editado: lo editado se conserva.' );
	kt_check( ! isset( $by_id( $after['custom_colors'] )[ $derived ] ) && ! isset( $by_id( $after['custom_colors'] )['c9d8e7f'] ) && isset( $by_id( $after['custom_colors'] )['a1b2c3d'] ), 'Reversión con el kit editado: sólo se quitan los globales que añadió la importación.' );
	// Sesión anterior a K (sin `mode`): delega en el nativo.
	$kit = $site();
	$runner = new Site_Settings_Revert();
	$runner->revert( array( 'session_id' => $session_a, 'runners' => array( 'site-settings' => array( 'imported_kit_id' => 55, 'active_kit_id' => 5, 'previous_kit_id' => 4, 'installed_theme' => null, 'activated_theme' => null, 'previous_active_theme' => null, 'previous_experiments' => array() ) ) ) );
	kt_check( array( array( 55, 5, 4 ) ) === \Elementor\Plugin::$instance->kits_manager->calls, 'Reversión de una sesión sin «mode» (anterior a K): delega en la reversión nativa.' );
	// CR-11: la reversión nativa real, con los metadatos inertes de K, no borra nada ni cambia tema o experimentos.
	$kit                                     = $site();
	$manager                                 = ( new ReflectionClass( '\Elementor\Core\Kits\Manager' ) )->newInstanceWithoutConstructor();
	\Elementor\Plugin::$instance->kits_manager = $manager;
	\Elementor\Plugin::$instance->documents    = new class() {
		public $asked = array();
		public function get( $id ) { $this->asked[] = $id; return null; }
	};
	$native = new \Elementor\App\Modules\ImportExportCustomization\Runners\Revert\Site_Settings();
	$meta   = ( new Site_Settings() )->get_import_session_metadata();
	kt_check( 0 === $meta['active_kit_id'] && 0 === $meta['imported_kit_id'] && empty( $meta['installed_theme'] ) && empty( $meta['activated_theme'] ) && array() === $meta['previous_experiments'], 'D2: los metadatos nativos de K son inertes (active_kit_id = 0, sin tema ni experimentos).' );
	$options = $GLOBALS['kt']['options'];
	$native->revert( array( 'runners' => array( 'site-settings' => $meta ) ) );
	kt_check( $options === $GLOBALS['kt']['options'] && 5 === (int) get_option( 'elementor_active_kit' ) && isset( $GLOBALS['kt']['posts'][5] ) && array() === $GLOBALS['kt_forbidden'], 'CR-11: Kits\Manager::revert() del núcleo aborta con active_kit_id = 0: no borra el kit ni cambia opciones, tema o experimentos.' );

	// ── 6 y 7. Casillas, adaptador 2.0 y v4 ─────────────────────────────────────────────────────────────────────
	$filtered = Site_Settings::filter( $kit_settings, array( 'globalColors' => false, 'globalFonts' => true, 'themeStyleSettings' => false, 'generalSettings' => true ), array() );
	kt_check( ! isset( $filtered['system_colors'] ) && ! isset( $filtered['custom_colors'] ) && isset( $filtered['custom_typography'] ), 'customization.settings: sin «colores globales» no viajan los colores.' );
	kt_check( ! isset( $filtered['body_typography_font_family'] ) && isset( $filtered['container_width'] ), 'customization.settings: sin «estilo del tema» no viaja body_*; los ajustes generales sí.' );
	$filtered = Site_Settings::filter( $kit_settings, null, array( 'globalColors' => true, 'globalFonts' => false ) );
	kt_check( isset( $filtered['custom_colors'] ) && ! isset( $filtered['custom_typography'] ), 'Sin casillas de la App manda el manifiesto (site-settings).' );
	foreach ( array( Module::MODE_ADD, Module::MODE_FRESH ) as $mode ) {
		$site();
		$bind( $session_a, $mode );
		// El adaptador 2.0 del núcleo vuelve a poner theme y experiments en true al leer el manifiesto (V-31).
		$import = new Kt_Import( $session_a, array( 'version' => '2.0', 'name' => 'acme', 'site' => 'http://origen.test' ), array( 'templates', 'content', 'settings', 'plugins' ), array( 'settings' => array( 'theme' => true, 'experiments' => true, 'globalColors' => true ) ) );
		Import_Hooks::configure( $import );
		Context::deactivate();
		$settings = $import->customization['settings'];
		kt_check( false === $settings['theme'] && false === $settings['experiments'] && true === $settings['globalColors'], "configure() ({$mode}): theme y experiments en false después del adaptador; el resto de casillas se respeta (D3, V-31)." );
		kt_check( ! in_array( 'plugins', $import->include, true ) && array() === $import->plugins && array() === $import->overrides, "configure() ({$mode}): sin plugins (D5) y sin pisar condiciones." );
		kt_check( Site_Settings::class === $import->registered['site-settings'], "configure() ({$mode}): el runner site-settings es el de K, en el mismo nombre (§7.5)." );
		if ( Module::MODE_ADD === $mode ) {
			kt_check( false === $settings['variables'] && false === $settings['classes'] && false === $settings['variablesOverrideAll'] && false === $settings['classesOverrideAll'], 'configure() «agregar»: los runners v4 reciben skip y *OverrideAll = false (CC-10, CV-18).' );
			kt_check( isset( $import->registered['default-styles'] ) && false === ( new $import->registered['default-styles']() )->should_import( array( 'include' => array( 'settings' ) ) ), 'configure() «agregar»: default-styles queda sustituido por un runner que no importa.' );
			kt_check( isset( $import->registered['floating-elements'] ), 'configure() «agregar»: floating-elements de K (no borra las condiciones de los botones del sitio).' );
		} else {
			kt_check( ! isset( $settings['variables'] ) || false !== $settings['variables'], 'configure() «nuevo»: v4 no se fuerza a skip.' );
		}
	}
	kt_check( array() === $GLOBALS['kt_forbidden'], 'Ningún paso de configure() instala ni cambia el tema: ' . implode( ', ', $GLOBALS['kt_forbidden'] ) );

	kt_rmdir( $GLOBALS['kt']['root'] );
	echo "Kits: ajustes del kit en «agregar» y «nuevo», reversión (también la nativa con metadatos inertes) y configuración del proceso validados (D2, D3, D11, §9.4, CR-5, CR-11, CV-10, V-31).\n";
}
