<?php
/**
 * Kits (etapa K): contrato del registro extensible y de los contextos (§5.5, §11 y §13.1 «parts», punto 1).
 *
 * Fija lo que comparten todos los frentes antes de que existan: la autocarga y la correspondencia clase ↔ archivo
 * de `modules/digitalisimo-import/`, las firmas exactas de `Part` y `Early_Part`, el registro de partes y de
 * referencias (validación, orden, memoria por petición, `_doing_it_wrong`), los contextos de exportación,
 * importación y reversión, y los nombres de los ganchos de §5.6. Los casos de las partes de K (B1 a B8) se añaden
 * debajo, en sus propias secciones.
 */
namespace Elementor {
	class Plugin {
		public static $instance;
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	$GLOBALS['kit'] = array(
		'filters'   => array(),
		'applied'   => array(),
		'wrong'     => array(),
		'multisite' => false,
		'main'      => true,
		'uploads'   => array(),
	);
	function apply_filters( $hook, $value, ...$args ) {
		$GLOBALS['kit']['applied'][ $hook ] = ( $GLOBALS['kit']['applied'][ $hook ] ?? 0 ) + 1;
		return isset( $GLOBALS['kit']['filters'][ $hook ] ) ? call_user_func( $GLOBALS['kit']['filters'][ $hook ], $value, ...$args ) : $value;
	}
	function _doing_it_wrong( $function, $message, $version ) {
		$GLOBALS['kit']['wrong'][] = $function . ': ' . $message;
	}
	function wp_json_encode( $value, $flags = 0 ) {
		return json_encode( $value, $flags );
	}
	function wp_upload_dir( $time = null, $create = true ) {
		return $GLOBALS['kit']['uploads'];
	}
	function is_multisite() {
		return $GLOBALS['kit']['multisite'];
	}
	function is_main_site() {
		return $GLOBALS['kit']['main'];
	}
	function home_url() {
		return 'https://a.test';
	}
	function get_site_url() {
		return 'https://a.test/wp';
	}

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};
	/** Avisos `_doing_it_wrong` que deja una llamada. */
	$wrongs = function ( callable $fn ) {
		$before = count( $GLOBALS['kit']['wrong'] );
		$fn();
		return array_slice( $GLOBALS['kit']['wrong'], $before );
	};
	$has = function ( array $list, $needle ) {
		foreach ( $list as $item ) {
			if ( false !== strpos( $item, $needle ) ) {
				return true;
			}
		}
		return false;
	};

	$module_dir = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import';
	require $module_dir . '/class-module.php';

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Part;
	use Digitalisimo\Elements\Import\Kits\Early_Part;
	use Digitalisimo\Elements\Import\Kits\Parts;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\Export_Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Revert_Context;

	// ── 1. Autocarga acotada y correspondencia clase ↔ archivo (§5.1) ──────────────────────────────────────────
	$autoloaders = function () {
		return count( array_filter( spl_autoload_functions(), function ( $fn ) {
			return is_array( $fn ) && Module::class === ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) && 'autoload' === $fn[1];
		} ) );
	};
	$check( 0 === $autoloaders(), 'Cargar class-module.php no debe registrar nada por sí solo.' );
	Module::register_autoloader();
	Module::register_autoloader();
	$check( 1 === $autoloaders(), 'La autocarga del módulo se registra una sola vez.' );

	// Los archivos que §6 asigna a cada frente, con el nombre de clase que les corresponde.
	$files = array(
		// E · contrato.
		array( 'Kits\Part', 'interface-', 'kits/interface-part.php' ),
		array( 'Kits\Early_Part', 'interface-', 'kits/interface-early-part.php' ),
		array( 'Kits\Parts', 'class-', 'kits/class-parts.php' ),
		array( 'Kits\References', 'class-', 'kits/class-references.php' ),
		array( 'Kits\Part_Context', 'class-', 'kits/class-part-context.php' ),
		array( 'Kits\Export_Context', 'class-', 'kits/class-export-context.php' ),
		array( 'Kits\Import_Context', 'class-', 'kits/class-import-context.php' ),
		array( 'Kits\Revert_Context', 'class-', 'kits/class-revert-context.php' ),
		// A · importador.
		array( 'Module', 'class-', 'class-module.php' ),
		array( 'Kits\Session', 'class-', 'kits/class-session.php' ),
		array( 'Kits\Context', 'class-', 'kits/class-context.php' ),
		array( 'Kits\Zip_Guard', 'class-', 'kits/class-zip-guard.php' ),
		array( 'Kits\Normalizer', 'class-', 'kits/class-normalizer.php' ),
		array( 'Kits\Media_Proxy', 'class-', 'kits/class-media-proxy.php' ),
		array( 'Kits\Wxr_Guard', 'class-', 'kits/class-wxr-guard.php' ),
		array( 'Kits\Legacy_Guard', 'class-', 'kits/class-legacy-guard.php' ),
		array( 'Kits\Cli_Guard', 'class-', 'kits/class-cli-guard.php' ),
		array( 'Kits\Runners\Finish', 'class-', 'kits/runners/class-finish.php' ),
		array( 'Kits\Runners\Site_Settings_Revert', 'class-', 'kits/runners/class-site-settings-revert.php' ),
		// B · datos propios.
		array( 'Kits\Parts\Origin', 'class-', 'kits/parts/class-origin.php' ),
		array( 'Kits\Parts\Element_Defaults', 'class-', 'kits/parts/class-element-defaults.php' ),
		array( 'Kits\Parts\Kit_Settings', 'class-', 'kits/parts/class-kit-settings.php' ),
		array( 'Kits\Parts\Files', 'class-', 'kits/parts/class-files.php' ),
		array( 'Kits\Parts\Media', 'class-', 'kits/parts/class-media.php' ),
		array( 'Kits\Parts\Theme_Builder', 'class-', 'kits/parts/class-theme-builder.php' ),
		array( 'Kits\Parts\Term_Meta', 'class-', 'kits/parts/class-term-meta.php' ),
		array( 'Kits\Export\Templates', 'class-', 'kits/export/class-templates.php' ),
		array( 'Kits\Export\Elementor_Content', 'class-', 'kits/export/class-elementor-content.php' ),
		array( 'Kits\Export\Wp_Content', 'class-', 'kits/export/class-wp-content.php' ),
		// C · remapeo.
		array( 'Kits\Remapper', 'class-', 'kits/class-remapper.php' ),
		// D · terceros.
		array( 'Sources\Envato_Kit', 'class-', 'sources/class-envato-kit.php' ),
		array( 'Kits\Preserve', 'class-', 'kits/class-preserve.php' ),
		array( 'Unconverted_Widget', 'class-', 'class-unconverted-widget.php' ),
		array( 'Kits\Library', 'class-', 'kits/class-library.php' ),
		// F · interfaz.
		array( 'Kits\Report', 'class-', 'kits/class-report.php' ),
		array( 'Kits\Report_Page', 'class-', 'kits/class-report-page.php' ),
		array( 'Kits\Cli', 'class-', 'kits/class-cli.php' ),
	);
	foreach ( $files as list( $class, $prefix, $file ) ) {
		$got = Module::relative_path( Module::NAMESPACE_PREFIX . $class, $prefix );
		$check( $file === $got, "La clase {$class} debe vivir en {$file}, no en {$got}." );
	}
	foreach ( array( 'Otro\\Clase', 'Digitalisimo\\Elements\\Importar\\X', Module::NAMESPACE_PREFIX, Module::NAMESPACE_PREFIX . 'Kits\\..\\x', Module::NAMESPACE_PREFIX . 'Kits/../x', Module::NAMESPACE_PREFIX . 'Kits\\\\X', Module::NAMESPACE_PREFIX . '9Kits\\X' ) as $bad ) {
		$check( '' === Module::relative_path( $bad ), 'Un nombre fuera del espacio de nombres o con caracteres raros nunca llega al disco: ' . $bad );
	}
	$check( '' === Module::relative_path( Module::NAMESPACE_PREFIX . 'Kits\\Part', 'evil-' ), 'Sólo se prueban los prefijos class-, interface- y trait-.' );
	$check( 'kits/class-parts.php' === Module::relative_path( '\\' . Module::NAMESPACE_PREFIX . 'Kits\\Parts' ), 'Un nombre con barra inicial también se resuelve.' );
	$check( interface_exists( Part::class ) && interface_exists( Early_Part::class ), 'Las interfaces deben cargarse por la autocarga.' );
	foreach ( array( Parts::class, References::class, Export_Context::class, Import_Context::class, Revert_Context::class ) as $class ) {
		$check( class_exists( $class ), "La autocarga debe cargar {$class}." );
	}
	$check( ! class_exists( Module::NAMESPACE_PREFIX . 'Kits\\No_Existe' ), 'Una clase del módulo que no existe no falla: simplemente no se carga.' );

	// Cada archivo del módulo, de cualquier frente: guarda ABSPATH y una sola clase que la autocarga encuentra.
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $module_dir, FilesystemIterator::SKIP_DOTS ) );
	$seen     = 0;
	foreach ( $iterator as $info ) {
		if ( 'php' !== $info->getExtension() ) {
			continue;
		}
		++$seen;
		$relative = ltrim( str_replace( '\\', '/', substr( $info->getPathname(), strlen( $module_dir ) ) ), '/' );
		$source   = file_get_contents( $info->getPathname() );
		$check( 0 === strpos( $source, '<?php' ) && false !== strpos( $source, "defined( 'ABSPATH' ) || exit;" ), "{$relative} debe empezar con <?php y cortar el acceso directo." );
		$check( 1 === preg_match( '/^namespace\s+(Digitalisimo\\\\Elements\\\\Import(?:\\\\[A-Za-z0-9_]+)*)\s*;/m', $source, $ns ), "{$relative} debe declarar un espacio de nombres de Digitalisimo\\Elements\\Import." );
		$found = preg_match_all( '/^\s*(?:final\s+|abstract\s+)*(class|interface|trait)\s+([A-Za-z_][A-Za-z0-9_]*)/m', $source, $decl, PREG_SET_ORDER );
		$check( 1 === $found, "{$relative} debe declarar exactamente una clase, interfaz o trait ({$found})." );
		$name   = $ns[1] . '\\' . $decl[0][2];
		$expect = Module::relative_path( $name, $decl[0][1] . '-' );
		$check( $expect === $relative, "{$name} está en {$relative}, pero la autocarga lo busca en {$expect}." );
		if ( 'class' === $decl[0][1] ) {
			$check( realpath( Module::file_for( $name ) ) === realpath( $module_dir . '/' . $relative ), "La autocarga no encuentra {$name}." );
		}
	}
	$check( $seen >= 9, 'El recorrido del módulo debe ver al menos los archivos del contrato.' );

	// ── 2. Interfaces con las firmas exactas de §5.5 ────────────────────────────────────────────────────────────
	$signature = function ( ReflectionMethod $method ) {
		$params = array();
		foreach ( $method->getParameters() as $param ) {
			$params[] = ( $param->hasType() ? (string) $param->getType() . ' ' : '' ) . '$' . $param->getName() . ( $param->isOptional() ? ' = …' : '' );
		}
		return $method->getName() . '( ' . implode( ', ', $params ) . ' ): ' . ( $method->hasReturnType() ? (string) $method->getReturnType() : '' );
	};
	$ns       = 'Digitalisimo\\Elements\\Import\\Kits\\';
	$expected = array(
		'key'           => 'key(  ): string',
		'version'       => 'version(  ): int',
		'kit_meta_keys' => 'kit_meta_keys(  ): array',
		'export'        => "export( {$ns}Export_Context \$context ): ?array",
		'import'        => "import( array \$data, int \$version, {$ns}Import_Context \$context ): array",
		'revert'        => "revert( array \$stored, {$ns}Revert_Context \$context ): void",
	);
	$part = new ReflectionClass( Part::class );
	$check( $part->isInterface() && array() === $part->getInterfaceNames(), 'Part es una interfaz sin padres.' );
	$check( count( $part->getMethods() ) === count( $expected ), 'Part tiene exactamente seis métodos.' );
	foreach ( $expected as $method => $text ) {
		$check( $part->hasMethod( $method ) && $text === $signature( $part->getMethod( $method ) ), "Firma de Part::{$method}: se esperaba «{$text}»." );
	}
	$early = new ReflectionClass( Early_Part::class );
	$check( $early->isInterface() && array( Part::class ) === $early->getInterfaceNames(), 'Early_Part extiende Part y nada más.' );
	$check( count( $early->getMethods() ) === count( $expected ) + 1, 'Early_Part sólo añade import_early().' );
	$check( "import_early( array \$data, int \$version, {$ns}Import_Context \$context ): array" === $signature( $early->getMethod( 'import_early' ) ), 'Firma de Early_Part::import_early().' );
	foreach ( array( Export_Context::class, Import_Context::class, Revert_Context::class ) as $class ) {
		$check( is_subclass_of( $class, Part_Context::class ) && ( new ReflectionClass( $class ) )->isFinal(), "{$class} es final y comparte el informe de Part_Context." );
	}
	foreach ( array( 'interface-part.php', 'interface-early-part.php', 'class-part-context.php', 'class-export-context.php', 'class-import-context.php', 'class-revert-context.php', 'class-parts.php' ) as $file ) {
		$check( false === stripos( file_get_contents( $module_dir . '/kits/' . $file ), 'Elementor\\' ), "kits/{$file} no depende del núcleo del constructor." );
	}

	// ── 3. Identificadores y ganchos (§5.1, §5.3, §5.6) ─────────────────────────────────────────────────────────
	$constants = array(
		'RUNNER'                        => 'digitalisimo',
		'SOURCE'                        => 'digitalisimo',
		'MANIFEST_KEY'                  => 'digitalisimo',
		'KIT_FOLDER'                    => 'digitalisimo/',
		'MODE_ADD'                      => 'add',
		'MODE_FRESH'                    => 'fresh',
		'WIDGET'                        => 'digitalisimo-unconverted',
		'PAGE'                          => 'digitalisimo-elements-kits',
		'CAPABILITY'                    => 'manage_options',
		'CLI_COMMAND'                   => 'digitalisimo-elements kit',
		'ACTION_UPLOAD'                 => 'digitalisimo_elements_kit_upload',
		'ACTION_APPLY_SETTINGS'         => 'digitalisimo_elements_kit_apply_settings',
		'ACTION_UNAPPLY_SETTINGS'       => 'digitalisimo_elements_kit_unapply_settings',
		'ACTION_PUBLISH'                => 'digitalisimo_elements_kit_publish',
		'OPTION_SESSION_PREFIX'         => 'digitalisimo_elements_kit_session_',
		'OPTION_UNCONVERTED'            => 'digitalisimo_elements_unconverted',
		'USER_META_NOTICE'              => 'digitalisimo_elements_kit_notice',
		'META_ORIGIN'                   => '_digitalisimo_import_origin',
		'META_UNCONVERTED'              => '_digitalisimo_unconverted',
		'META_ORIGINAL_TYPE'            => '_digitalisimo_original_type',
		'KIT_META_UNCONVERTED_DEFAULTS' => '_digitalisimo_unconverted_defaults',
		'CLEANUP_EVENT'                 => 'digitalisimo_elements_kits_cleanup',
		'TMP_DIR'                       => 'digitalisimo-elements/tmp',
		'FILTER_PARTS'                  => 'digitalisimo_elements_kit_parts',
		'FILTER_REFERENCES'             => 'digitalisimo_elements_kit_references',
		'FILTER_MEDIA_LIMITS'           => 'digitalisimo_elements_kit_media_limits',
		'FILTER_GUARD_LIMITS'           => 'digitalisimo_elements_kit_guard_limits',
		'FILTER_EXISTING_DOCUMENT'      => 'digitalisimo_elements_kit_existing_document',
		'ACTION_IMPORTED'               => 'digitalisimo_elements_kit_imported',
		'FILTER_UNCONVERTED_CONVERT'    => 'digitalisimo_elements_unconverted_convert',
		'FILTER_THEME_BUILDER_FIELDS'   => 'digitalisimo_elements_kit_theme_builder_fields',
	);
	foreach ( $constants as $name => $value ) {
		$check( constant( Module::class . '::' . $name ) === $value, "Module::{$name} debe ser «{$value}»." );
	}
	$check( 1 === Module::FORMAT, 'manifest.digitalisimo.v empieza en 1.' );
	$check( strlen( Module::OPTION_SESSION_PREFIX . str_repeat( 'x', 64 ) ) <= 191, 'La opción de sesión cabe en option_name con una sesión de 64 caracteres.' );

	// Formas válidas de §5.2.
	$sha = hash( 'sha256', 'x' );
	foreach ( array( "media/{$sha}.png", "media/{$sha}.woff2", 'files/shapes/ola.svg', 'files/masks/ola-2-b.svg' ) as $ok ) {
		$check( Module::is_kit_file( $ok ), "Ruta de kit válida: {$ok}" );
	}
	foreach ( array( "media/{$sha}.php", "media/{$sha}.PNG", 'media/abc.png', "media/../{$sha}.png", 'files/shapes/Ola.svg', 'files/shapes/-ola.svg', 'files/shapes/ola--b.svg', 'files/fonts/ola.svg', 'files/shapes/ola.svg/x', "digitalisimo/media/{$sha}.png", '', 'files/shapes/../masks/x.svg' ) as $bad ) {
		$check( ! Module::is_kit_file( $bad ), "Ruta de kit inválida: {$bad}" );
	}
	$check( 1 === preg_match( Module::URLS_KEY_PATTERN, 'page:12' ) && 0 === preg_match( Module::URLS_KEY_PATTERN, 'page:12/x' ), 'Claves de urls.json.' );
	$check( 1 === preg_match( Module::THEME_BUILDER_KEY_PATTERN, '12' ) && 0 === preg_match( Module::THEME_BUILDER_KEY_PATTERN, '12a' ), 'Claves de theme-builder.json.' );

	// ── 4. Registro de partes ───────────────────────────────────────────────────────────────────────────────────
	abstract class Kit_Test_Part implements Part {
		const KEY     = '';
		const VERSION = 1;
		const METAS   = array();
		public function key(): string {
			return static::KEY;
		}
		public function version(): int {
			return static::VERSION;
		}
		public function kit_meta_keys(): array {
			return static::METAS;
		}
		public function export( Export_Context $context ): ?array {
			return array( 'key' => static::KEY );
		}
		public function import( array $data, int $version, Import_Context $context ): array {
			return array( 'imported' => count( $data ) );
		}
		public function revert( array $stored, Revert_Context $context ): void {
		}
	}
	final class Kit_Test_Presets extends Kit_Test_Part {
		const KEY     = 'presets';
		const VERSION = 2;
		const METAS   = array( '_digitalisimo_presets', '_digitalisimo_presets' );
	}
	final class Kit_Test_Woo extends Kit_Test_Part implements Early_Part {
		const KEY = 'woo-attributes';
		public function import_early( array $data, int $version, Import_Context $context ): array {
			return array();
		}
	}
	final class Kit_Test_Metas extends Kit_Test_Part {
		const KEY   = 'metas';
		const METAS = array( '_ok', 'mal clave', 5 );
	}
	final class Kit_Test_Dup extends Kit_Test_Part {
		const KEY = 'presets';
	}
	final class Kit_Test_Bad_Key extends Kit_Test_Part {
		const KEY = 'Mal Clave';
	}
	final class Kit_Test_Reserved extends Kit_Test_Part {
		const KEY = 'urls';
	}
	final class Kit_Test_Zero extends Kit_Test_Part {
		const KEY     = 'cero';
		const VERSION = 0;
	}
	final class Kit_Test_Throws extends Kit_Test_Part {
		public function key(): string {
			throw new RuntimeException( 'falla' );
		}
	}
	final class Kit_Test_Not_A_Part {
	}

	$check( array( 'origin', 'element-defaults', 'kit-settings', 'files', 'media', 'theme-builder', 'term-meta' ) === array_keys( Parts::BUILTIN ), 'Las partes de K y su orden de importación son los de §7.3.' );
	foreach ( Parts::BUILTIN as $key => $class ) {
		$check( 0 === strpos( Module::relative_path( $class ), 'kits/parts/class-' ), "La parte {$key} vive en kits/parts/." );
		if ( class_exists( $class ) ) {
			$check( is_subclass_of( $class, Part::class ) && ( new $class() )->key() === $key, "La parte de K {$class} implementa Part con la clave «{$key}»." );
		}
	}
	$builtin = array_values( array_diff( array_keys( Parts::BUILTIN ), Parts::missing_builtin() ) );

	$seen_in_filter = null;
	$GLOBALS['kit']['filters'][ Module::FILTER_PARTS ] = function ( $parts ) use ( &$seen_in_filter ) {
		$seen_in_filter = array_keys( $parts );
		$parts[]        = new Kit_Test_Presets();
		$parts[]        = 'Kit_Test_Woo';
		$parts[]        = new Kit_Test_Metas();
		$parts[]        = new Kit_Test_Dup();
		$parts[]        = new Kit_Test_Bad_Key();
		$parts[]        = new Kit_Test_Reserved();
		$parts[]        = new Kit_Test_Zero();
		$parts[]        = new Kit_Test_Throws();
		$parts[]        = new Kit_Test_Not_A_Part();
		$parts[]        = 'Kit_Test_No_Existe';
		$parts[]        = 'Kit_Test_Not_A_Part';
		$parts[]        = 42;
		return $parts;
	};
	Parts::reset();
	$warned = $wrongs( function () {
		Parts::all();
	} );
	$check( $builtin === $seen_in_filter, 'El filtro recibe las partes de K indexadas por clave.' );
	$check( array_merge( $builtin, array( 'metas', 'presets', 'woo-attributes' ) ) === Parts::keys(), 'Primero las partes de K en su orden; después las demás por clave: ' . implode( ', ', Parts::keys() ) );
	$check( $has( $warned, 'repetida: «presets»' ) && $has( $warned, 'reservada' ) && $has( $warned, 'versión de 1 o más' ) && $has( $warned, 'falló al describirse' ), 'Se avisan la clave repetida, la reservada, la versión 0 y la parte que falla.' );
	$check( $has( $warned, 'Kit_Test_No_Existe' ) && $has( $warned, 'Kit_Test_Not_A_Part' ) && $has( $warned, '42' ) && $has( $warned, '"mal clave"' ) && $has( $warned, ': 5' ), 'Se avisan las entradas que no son partes y las metas inválidas.' );
	$check( 11 === count( $warned ), 'Cada entrada inválida deja un aviso (' . count( $warned ) . '): ' . implode( ' | ', $warned ) );
	$check( Parts::get( 'presets' ) instanceof Kit_Test_Presets, 'Con una clave repetida gana la primera.' );
	$check( null === Parts::get( 'cero' ) && null === Parts::get( 'urls' ) && null === Parts::get( 'nada' ), 'Lo rechazado no se registra.' );
	$check( array( 'woo-attributes' ) === array_keys( Parts::early() ), 'Sólo las Early_Part corren en el runner plugins.' );
	$check( 2 === Parts::versions()['presets'] && 1 === Parts::versions()['woo-attributes'], 'versions() da la versión de cada parte para el manifiesto.' );
	$check( Parts::accepts( 'presets', 1 ) && Parts::accepts( 'presets', 2 ) && ! Parts::accepts( 'presets', 3 ) && ! Parts::accepts( 'presets', 0 ) && ! Parts::accepts( 'nada', 1 ), 'Una parte acepta cualquier versión de 1 a la suya.' );
	$metas = Parts::kit_meta_keys();
	sort( $metas );
	$check( array_values( array_intersect( $metas, array( '_digitalisimo_presets', '_ok', 'mal clave' ) ) ) === array( '_digitalisimo_presets', '_ok' ) && count( $metas ) === count( array_unique( $metas ) ), 'Metas del kit sin repetir y sin nombres inválidos.' );
	$preserved = Parts::meta_to_preserve( array( '_previa', '_ok' ) );
	$check( '_previa' === $preserved[0] && 1 === count( array_keys( $preserved, '_ok', true ) ) && in_array( '_digitalisimo_presets', $preserved, true ), 'meta_to_preserve añade las metas de las partes sin repetir.' );
	$check( array() !== Parts::meta_to_preserve( 'x' ), 'meta_to_preserve tolera un valor que no es array.' );
	$check( 'digitalisimo/presets.json' === Parts::file( 'presets' ), 'Cada parte viaja en digitalisimo/<clave>.json.' );
	Parts::all();
	Parts::keys();
	$check( 1 === $GLOBALS['kit']['applied'][ Module::FILTER_PARTS ], 'El filtro de partes se aplica una sola vez por petición.' );
	foreach ( array( 'a', 'a-b', 'a1-2b', 'element-defaults', str_repeat( 'a', 64 ) ) as $key ) {
		$check( Parts::is_valid_key( $key ), "Clave válida: {$key}" );
	}
	foreach ( array( '', '-a', 'a-', 'a--b', 'A', 'a_b', 'a.b', 'a/b', 'urls', str_repeat( 'a', 65 ) ) as $key ) {
		$check( ! Parts::is_valid_key( $key ), "Clave inválida: {$key}" );
	}
	$GLOBALS['kit']['filters'][ Module::FILTER_PARTS ] = function () {
		return 'nada';
	};
	Parts::reset();
	$warned = $wrongs( function () {
		Parts::all();
	} );
	$check( $builtin === Parts::keys() && $has( $warned, 'debe devolver un array' ), 'Un filtro que no devuelve un array deja sólo las partes de K.' );
	unset( $GLOBALS['kit']['filters'][ Module::FILTER_PARTS ] );
	Parts::reset();

	// ── 5. Registro de referencias ──────────────────────────────────────────────────────────────────────────────
	$warned = $wrongs( function () use ( $check ) {
		foreach ( References::builtin() as $ref ) {
			$check( null !== References::normalize( $ref ), 'Toda referencia integrada es válida: ' . json_encode( $ref ) );
		}
	} );
	$check( array() === $warned, 'Las referencias integradas no dejan avisos.' );

	$GLOBALS['kit']['filters'][ Module::FILTER_REFERENCES ] = function ( $refs ) {
		$refs[] = array(
			'scope' => 'element',
			'type'  => 'digitalisimo-offcanvas',
			'path'  => 'template_id',
			'kind'  => 'template',
		);
		$refs[] = array( 'element', 'digitalisimo-offcanvas', 'sidebar', 'note:sidebar' );
		$refs[] = array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_user_value', 'user', true, 999999999 );
		$refs[] = array( 'element', '*', 'e_display_conditions', 'conditions' );
		$refs[] = array( 'element', 'section', 'shape_divider_top', 'map:shapes' );
		$refs[] = array( 'element', 'digitalisimo-icon-box', 'link.url', 'post' );
		$refs[] = array( 'condition', 'digitalisimo_user', 'users.*.id', 'user' );
		$refs[] = array( 'document', '*', 'custom_css', 'css' );
		$refs[] = array( 'post_meta', '_related_pillar', '', 'post' );
		$refs[] = array( 'term_meta', 'thumbnail_id', '', 'attachment', null );
		// Inválidas.
		$refs[] = array( 'widget', 'x', 'a', 'post' );
		$refs[] = array( 'element', 'x', 'a', 'pagina' );
		$refs[] = array( 'element', 'x', 'a', 'map:' );
		$refs[] = array( 'element', 'x', 'a', 'note:Mal' );
		$refs[] = array( 'element', 'x', 'a..b', 'post' );
		$refs[] = array( 'element', 'x', '', 'post' );
		$refs[] = array( 'element', '', 'a', 'post' );
		$refs[] = array( 'post_meta', '*', '', 'post' );
		$refs[] = array( 'element', 'x', 'a', 'user', true, '' );
		$refs[] = array( 'element', 'x', 'a', 'user', true, array( 1 ) );
		$refs[] = 'x';
		return $refs;
	};
	References::reset();
	$warned = $wrongs( function () {
		References::all();
	} );
	$check( 11 === count( $warned ) && $has( $warned, 'ámbito desconocido' ) && $has( $warned, 'tipo de referencia desconocido' ) && $has( $warned, 'ruta inválida' ) && $has( $warned, 'tipo inválido' ) && $has( $warned, 'valor de cierre inválido' ) && $has( $warned, 'no es un array' ), 'Cada referencia inválida deja su aviso (' . count( $warned ) . '): ' . implode( ' | ', $warned ) );
	$index = References::all();
	$check( 'template' === $index['element']['digitalisimo-offcanvas'][0]['kind'] && false === $index['element']['digitalisimo-offcanvas'][0]['gate'] && null === $index['element']['digitalisimo-offcanvas'][0]['never'], 'Una referencia con claves se completa: sin gate ni valor de cierre.' );
	$check( 'note:sidebar' === $index['element']['digitalisimo-offcanvas'][1]['kind'], 'Una referencia posicional sigue el orden scope, type, path, kind, gate, never.' );
	// Las integradas van primero en el índice: las del filtro se buscan por su ruta y su valor de cierre.
	$by_ref = function ( array $refs, string $path, $never = 'cualquiera' ) {
		foreach ( $refs as $ref ) {
			if ( $path === $ref['path'] && ( 'cualquiera' === $never || $never === $ref['never'] ) ) {
				return $ref;
			}
		}
		return null;
	};
	$visibility = $by_ref( $index['element']['*'], 'digitalisimo_visibility_rules.*.rule_user_value', 999999999 );
	$check( null !== $visibility && true === $visibility['gate'] && 999999999 === $visibility['never'], 'gate y el valor de cierre se conservan.' );
	$check( true === $by_ref( $index['element']['*'], 'e_display_conditions' )['gate'] && true === $index['condition']['digitalisimo_user'][0]['gate'], 'Las condiciones (scope condition o kind conditions) deciden quién ve: gate por defecto.' );
	$check( '' === $index['post_meta']['_related_pillar'][0]['path'] && false === $index['term_meta']['thumbnail_id'][0]['gate'], 'Las metas admiten la ruta vacía (el valor entero); gate nulo vale el defecto.' );
	$check( 1 === $GLOBALS['kit']['applied'][ Module::FILTER_REFERENCES ] && References::all() === $index, 'El índice de referencias se arma una vez por petición.' );
	$check( References::is_valid_kind( 'media-url' ) && References::is_valid_kind( 'note:google_places' ) && References::is_valid_kind( 'map:shapes' ) && ! References::is_valid_kind( 'map:' ) && ! References::is_valid_kind( 'otro:x' ) && ! References::is_valid_kind( 'Post' ), 'Tipos de referencia válidos: KINDS, note:<código> y map:<nombre>.' );
	$check( 'shapes' === References::map_name( 'map:shapes' ) && '' === References::map_name( 'post' ) && 'form' === References::note_code( 'note:form' ) && '' === References::note_code( 'map:form' ), 'map_name() y note_code() leen el sufijo.' );
	$check( array( 'tabs', '*', 'template_id' ) === References::split_path( 'tabs.*.template_id' ) && array() === References::split_path( '' ), 'split_path() separa por puntos.' );

	// Derivadas por tipo de control URL, también dentro de repetidores (CC-6, CV-20).
	class Kit_Test_Widget {
		public $calls = 0;
		public $controls;
		public function __construct( array $controls ) {
			$this->controls = $controls;
		}
		public function get_controls() {
			++$this->calls;
			return $this->controls;
		}
	}
	class Kit_Test_Manager {
		public $types;
		public function __construct( array $types ) {
			$this->types = $types;
		}
		public function get_widget_types( $name = null ) {
			return $this->types[ $name ] ?? null;
		}
		public function get_element_types( $name = null ) {
			return $this->types[ $name ] ?? null;
		}
	}
	$box_controls = array(
		'title' => array( 'name' => 'title', 'type' => 'text' ),
		'link'  => array( 'name' => 'link', 'type' => 'url' ),
		'items' => array(
			'name'   => 'items',
			'type'   => 'repeater',
			'fields' => array(
				array( 'name' => 'item_link', 'type' => 'url' ),
				array( 'name' => 'label', 'type' => 'text' ),
				array( 'name' => 'sub', 'type' => 'repeater', 'fields' => array( 'deep' => array( 'type' => 'url' ) ) ),
				array( 'name' => 'mal nombre', 'type' => 'url' ),
			),
		),
		'roto'  => 'no es un control',
	);
	$box       = new Kit_Test_Widget( $box_controls );
	$container = new Kit_Test_Widget( array( 'bg_link' => array( 'name' => 'bg_link', 'type' => 'url' ) ) );
	\Elementor\Plugin::$instance = (object) array(
		'widgets_manager'  => new Kit_Test_Manager( array( 'digitalisimo-icon-box' => $box ) ),
		'elements_manager' => new Kit_Test_Manager( array( 'container' => $container ) ),
	);
	$derived = References::for_type( 'digitalisimo-icon-box' );
	$check( array( 'link.url', 'items.*.item_link.url', 'items.*.sub.*.deep.url' ) === array_column( $derived, 'path' ), 'Cada control URL, también en repetidores anidados, da una referencia url: ' . implode( ', ', array_column( $derived, 'path' ) ) );
	$check( array( 'url' ) === array_values( array_unique( array_column( $derived, 'kind' ) ) ) && ! in_array( true, array_column( $derived, 'gate' ), true ) && true === $derived[0]['derived'], 'Las derivadas son url, sin gate y marcadas como derivadas.' );
	References::for_type( 'digitalisimo-icon-box' );
	$check( 1 === $box->calls, 'Los controles de un tipo se leen una vez por petición.' );
	$check( array( 'bg_link.url' ) === array_column( References::for_type( 'container' ), 'path' ), 'Un elType se busca en el gestor de elementos.' );
	$check( array() === References::for_type( 'no-existe' ) && array() === References::for_type( '*' ), 'Un tipo desconocido no tiene derivadas.' );
	$check( array( 'x.url' ) === array_column( References::for_type( 'otro', array( array( 'name' => 'x', 'type' => 'url' ) ) ), 'path' ) && 1 === $box->calls, 'Con controles explícitos, for_type() es pura y no usa la memoria.' );
	$matching = References::matching( 'element', 'digitalisimo-icon-box' );
	$by_path  = array_column( $matching, 'kind', 'path' );
	$check( 'post' === $by_path['link.url'] && 'url' === $by_path['items.*.item_link.url'] && 'user' === $by_path['digitalisimo_visibility_rules.*.rule_user_value'] && 'conditions' === $by_path['e_display_conditions'], 'matching() junta las del tipo, las de * y las derivadas; la declarada gana a la derivada.' );
	$check( count( $matching ) === count( $by_path ), 'matching() no repite rutas.' );
	$check( array( 'custom_css' ) === array_column( References::matching( 'document', 'wp-page' ), 'path' ) && array() === References::matching( 'term_meta', '_otra' ), 'matching() vale para documentos y metas.' );
	\Elementor\Plugin::$instance = null;

	// media-url: una url bajo uploads del origen (D6).
	$url_ref  = array( 'kind' => 'url' );
	$uploads  = 'http://A.test/wp-content/uploads';
	$kind_for = function ( $value, $ref = null, $base = null ) use ( $url_ref, $uploads ) {
		return References::kind_for( $ref ?? $url_ref, $value, $base ?? $uploads );
	};
	$check( 'media-url' === $kind_for( 'https://a.test/wp-content/uploads/2026/01/x.png' ) && 'media-url' === $kind_for( '//a.test/wp-content/uploads/x.pdf' ), 'Una URL bajo uploads del origen es media-url, sin importar http/https ni mayúsculas del host.' );
	foreach ( array( 'https://a.test/contacto/', 'https://a.test/wp-content/uploads', 'https://a.test/wp-content/uploads-old/x.png', 'https://b.test/wp-content/uploads/x.png', '/wp-content/uploads/x.png', 'https://a.test/WP-CONTENT/uploads/x.png', array( 'url' => 'x' ) ) as $value ) {
		$check( 'url' === $kind_for( $value ), 'No es media-url: ' . json_encode( $value ) );
	}
	$check( 'template' === $kind_for( 'https://a.test/wp-content/uploads/x.png', array( 'kind' => 'template' ) ) && 'url' === $kind_for( 'https://a.test/wp-content/uploads/x.png', null, '' ), 'Otros tipos no cambian, y sin uploads del origen no hay media-url.' );
	unset( $GLOBALS['kit']['filters'][ Module::FILTER_REFERENCES ] );
	References::reset();

	// ── 6. Contextos ────────────────────────────────────────────────────────────────────────────────────────────
	$tmp = sys_get_temp_dir() . '/digi-kit-contract-' . bin2hex( random_bytes( 6 ) );
	$put = function ( $relative, $bytes ) use ( $tmp ) {
		$file = $tmp . '/' . $relative;
		if ( ! is_dir( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0777, true );
		}
		file_put_contents( $file, $bytes );
		return $file;
	};
	$rmrf = function ( $dir ) use ( &$rmrf ) {
		foreach ( is_dir( $dir ) && ! is_link( $dir ) ? scandir( $dir ) : array() as $name ) {
			if ( '.' !== $name && '..' !== $name ) {
				$path = $dir . '/' . $name;
				is_dir( $path ) && ! is_link( $path ) ? $rmrf( $path ) : unlink( $path );
			}
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}
	};
	try {
		// Exportación: topes (D6).
		$mb = 1048576;
		$check( array( 'file' => 20 * $mb, 'total' => 32 * $mb ) === Export_Context::media_limits( 'app', '512M' ), 'App con memoria de sobra: 20 MB por archivo y 32 MB en total.' );
		$check( 16 * $mb === Export_Context::media_limits( 'app', '128M' )['total'] && 32 * $mb === Export_Context::media_limits( 'app', '-1' )['total'], 'En la App el total es min(32 MB, memory_limit / 8).' );
		$check( 0 === Export_Context::media_limits( 'cli', '128M' )['total'] && 20 * $mb === Export_Context::media_limits( 'cli', '128M' )['file'], 'WP-CLI no tiene tope total, sí por archivo.' );
		$GLOBALS['kit']['filters'][ Module::FILTER_MEDIA_LIMITS ] = function ( $limits, $surface ) {
			return array( 'file' => 'cli' === $surface ? 5 : -3, 'otra' => 1 );
		};
		$check( array( 'file' => 5, 'total' => 0 ) === Export_Context::media_limits( 'cli', '1G' ) && array( 'file' => 0, 'total' => 32 * $mb ) === Export_Context::media_limits( 'app', '1G' ), 'El filtro de topes recibe la superficie; claves ajenas y negativos se descartan.' );
		unset( $GLOBALS['kit']['filters'][ Module::FILTER_MEDIA_LIMITS ] );
		$check( 1073741824 === Export_Context::parse_bytes( '1G' ) && 524288 === Export_Context::parse_bytes( '512k' ) && 65536 === Export_Context::parse_bytes( '65536' ) && -1 === Export_Context::parse_bytes( '-1' ) && 0 === Export_Context::parse_bytes( '' ), 'parse_bytes() entiende K, M, G y -1.' );

		$a     = $put( 'uploads/2026/a.png', str_repeat( 'a', 10 ) );
		$b     = $put( 'uploads/2026/b.png', str_repeat( 'b', 30 ) );
		$c     = $put( 'uploads/2026/c.png', str_repeat( 'c', 30 ) );
		$big   = $put( 'uploads/2026/big.png', str_repeat( 'g', 51 ) );
		$site3 = $put( 'uploads/sites/3/x.png', 'x' );
		$woo   = $put( 'uploads/woocommerce_uploads/x.pdf', 'x' );
		$tmpk  = $put( 'uploads/digitalisimo-elements/tmp/' . str_repeat( 'f', 64 ) . '/x.png', 'x' );
		$out   = $put( 'outside/x.png', 'x' );
		$shape = $put( 'uploads/digitalisimo-elements/shapes/ola.svg', '<svg/>' );
		$link  = $tmp . '/uploads/2026/link.png';
		$linked = @symlink( $out, $link );

		$export = new Export_Context(
			array(
				'surface'  => 'otra',
				'media'    => 'otro',
				'include'  => array( 'templates', 'settings', 3, '' ),
				'origin'   => array( 'home' => 'https://a.test', 'uploads' => 5 ),
				'roots'    => array( $tmp . '/uploads', $tmp . '/no-existe' ),
				'excluded' => array( $tmp . '/uploads/sites', $tmp . '/uploads/woocommerce_uploads', $tmp . '/uploads/' . Module::TMP_DIR ),
				'limits'   => array( 'file' => 50, 'total' => 60 ),
			)
		);
		$check( 'app' === $export->surface() && 'embed' === $export->media_format() && $export->embeds_media(), 'Valores desconocidos: App y medios incrustados (D6).' );
		$check( array( 'templates', 'settings' ) === $export->include() && $export->includes( 'settings' ) && ! $export->includes( 'content' ), 'include() sólo guarda cadenas.' );
		$check( 'https://a.test' === $export->origin( 'home' ) && '' === $export->origin( 'uploads' ) && '' === $export->origin( 'otra' ) && 3 === count( $export->origin() ), 'origin() da home, site y uploads.' );
		$check( $export->add_file( "media/{$sha}.png", $a ) && $export->add_file( "media/{$sha}.png", $a ) && 10 === $export->budget()['used'], 'Un archivo del sitio entra una vez aunque se pida dos.' );
		$check( $export->add_file( 'files/shapes/ola.svg', $shape ) && $export->has_file( 'files/shapes/ola.svg' ), 'Los separadores propios salen de uploads/digitalisimo-elements/.' );
		$warned = $wrongs( function () use ( $export, $a, $sha ) {
			$export->add_file( "media/../{$sha}.png", $a );
			$export->add_file( 'files/fonts/x.svg', $a );
		} );
		$check( 2 === count( $warned ), 'Una ruta de kit inválida es un error de la parte.' );
		$sha2 = hash( 'sha256', 'y' );
		$check( ! $export->add_file( "media/{$sha2}.png", $out ) && ! $export->add_file( "media/{$sha2}.png", $site3 ) && ! $export->add_file( "media/{$sha2}.pdf", $woo ) && ! $export->add_file( "media/{$sha2}.png", $tmpk ), 'Nada fuera de la raíz, ni de otro sitio de la red, ni de woocommerce_uploads, ni temporales (CR-2).' );
		$check( ! $export->add_file( "media/{$sha2}.png", $tmp . '/uploads/2026/no.png' ) && ! $export->add_file( "media/{$sha2}.png", '' ) && ! $export->add_file( "media/{$sha2}.png", $tmp . '/uploads/2026' ), 'Un archivo que no existe o una carpeta no entran.' );
		if ( $linked ) {
			$check( ! $export->add_file( "media/{$sha2}.png", $link ), 'Un enlace simbólico que sale de la raíz no entra.' );
		}
		$check( ! $export->add_file( "media/{$sha2}.png", $big ), 'Un archivo mayor que el tope por archivo no entra.' );
		$check( $export->add_file( "media/{$sha2}.png", $b ) && ! $export->add_file( 'media/' . hash( 'sha256', 'z' ) . '.png', $c ), 'Lo que no cabe en el total no entra.' );
		$budget = $export->budget();
		$check( 50 === $budget['file'] && 60 === $budget['total'] && 46 === $budget['used'] && 14 === $budget['left'], 'budget() da topes, uso y lo que queda: ' . json_encode( $budget ) );
		$reasons = array_count_values( array_column( $export->rejected(), 'reason' ) );
		$check( 2 === $reasons['path'] && 4 + ( $linked ? 1 : 0 ) === $reasons['outside'] && 3 === $reasons['missing'] && 1 === $reasons['file-limit'] && 1 === $reasons['total-limit'], 'rejected() dice por qué no entró cada archivo: ' . json_encode( $reasons ) );
		$check( 1 === $export->get_report()['counts']['files.rejected.total-limit'], 'Los rechazos también cuentan en el informe.' );
		$check( array( "media/{$sha}.png", 'files/shapes/ola.svg', "media/{$sha2}.png" ) === array_keys( $export->files() ) && realpath( $a ) === $export->files()[ "media/{$sha}.png" ]['source'], 'files() da la ruta real de cada archivo para el ZIP.' );
		$export->not_exported( 'webhooks' );
		$export->not_exported( 'drafts', 3 );
		$export->not_exported( 'drafts' );
		$check( array( 'webhooks' => 1, 'drafts' => 4 ) === $export->get_not_exported() && 1 === count( $wrongs( function () use ( $export ) {
			$export->not_exported( 'Mal motivo' );
		} ) ), 'not_exported() suma por motivo.' );
		$export->add_exported( 'template', array( 5, 6, 5, '7', '', 1.5 ) );
		$check( array( 5, 6, '7' ) === $export->exported( 'template' ) && array() === $export->exported( 'term' ) && 1 === count( $wrongs( function () use ( $export ) {
			$export->add_exported( 'Mal', array( 1 ) );
		} ) ), 'exported() guarda los IDs sin repetir.' );
		$closed = new Export_Context( array( 'limits' => array( 'file' => 0, 'total' => 0 ) ) );
		$check( ! $closed->add_file( "media/{$sha}.png", $a ) && -1 === $closed->budget()['left'], 'Sin raíces no sale ningún archivo; 0 es sin tope.' );

		$GLOBALS['kit']['uploads']   = array(
			'basedir' => $tmp . '/uploads',
			'baseurl' => 'https://a.test/wp-content/uploads',
			'error'   => false,
		);
		$GLOBALS['kit']['multisite'] = true;
		$site = Export_Context::for_site( array( 'limits' => array( 'file' => 100, 'total' => 0 ) ) );
		$check( 'https://a.test/wp' === $site->origin( 'site' ) && 'https://a.test/wp-content/uploads' === $site->origin( 'uploads' ), 'for_site() toma las URL del sitio actual.' );
		$check( $site->add_file( "media/{$sha}.png", $a ) && ! $site->add_file( "media/{$sha2}.png", $site3 ) && ! $site->add_file( "media/{$sha2}.pdf", $woo ) && ! $site->add_file( "media/{$sha2}.png", $tmpk ), 'En el sitio principal de una red se excluyen los otros sitios, woocommerce_uploads y los temporales.' );
		$GLOBALS['kit']['uploads'] = array( 'error' => 'sin carpeta' );
		$check( ! Export_Context::for_site()->add_file( "media/{$sha}.png", $a ), 'Sin carpeta de archivos no sale nada.' );

		// Importación.
		foreach ( array( '', '../x', 'a/b', str_repeat( 'a', 65 ), 5 ) as $session ) {
			$thrown = false;
			try {
				new Import_Context( array( 'session' => $session ) );
			} catch ( InvalidArgumentException $error ) {
				$thrown = true;
			}
			$check( $thrown, 'Una sesión inválida no crea contexto: ' . json_encode( $session ) );
		}
		$staged = $tmp . '/uploads/digitalisimo-elements/tmp/' . str_repeat( 'a', 64 ) . '/staged';
		$put( substr( $staged, strlen( $tmp ) + 1 ) . "/media/{$sha}.png", 'png' );
		$put( substr( $staged, strlen( $tmp ) + 1 ) . '/files/shapes/ola.svg', '<svg/>' );
		$escape = $linked ? @symlink( $out, $staged . "/media/{$sha2}.png" ) : false;
		$import = new Import_Context(
			array(
				'session'     => '6512bd43d9caa',
				'mode'        => 'otro',
				'kit'         => sha1( 'kit' ),
				'origin'      => array( 'format' => 'digitalisimo', 'home' => 'https://a.test', 'uploads' => array() ),
				'manifest'    => array( 'v' => 1, 'parts' => array( 'presets' => 2 ) ),
				'user'        => '3',
				'max_post_id' => 100,
				'kit_id'      => 7,
				'staged_dir'  => $staged,
				'maps'        => array( 'post' => array( 5 => 12, 6 => array( 1 ), 7 => '' ), 'Mal' => array( 1 => 2 ), 'term' => 'x' ),
				'stored'      => array( 'presets' => array( 'z' => 0 ), 'Mal' => array( 1 ) ),
			)
		);
		$check( 'add' === $import->mode() && $import->is_add() && ! $import->is_fresh(), 'Un modo desconocido es «sólo agregar» (D1).' );
		$check( $import->is_fresh() === false && ( new Import_Context( array( 'session' => 'x', 'mode' => 'fresh' ) ) )->is_fresh(), '«Nuevo» sólo si se pide.' );
		$check( '6512bd43d9caa' === $import->session() && sha1( 'kit' ) === $import->kit() && 3 === $import->user() && 7 === $import->kit_id() && 100 === $import->max_post_id(), 'Datos de la sesión.' );
		$check( 'digitalisimo' === $import->origin( 'format' ) && '' === $import->origin( 'uploads' ) && 5 === count( $import->origin() ), 'origin() da formato, kit, home, site y uploads.' );
		$check( array( 'presets' => 2 ) === $import->manifest( 'parts' ) && null === $import->manifest( 'needs' ) && 1 === $import->manifest()['v'], 'manifest() lee la clave digitalisimo del manifiesto.' );
		$check( $import->is_new_post( 101 ) && ! $import->is_new_post( 100 ), 'Lo posterior a max_post_id es de la sesión (D8).' );
		$check( array( 'post' => array( 5 => 12 ) ) === $import->maps(), 'Los mapas sólo guardan nombres válidos y pares escalares.' );
		$check( 12 === $import->mapped( 'post', '5' ) && 'x' === $import->mapped( 'post', 7, 'x' ) && null === $import->mapped( 'nada', 1 ) && null === $import->mapped( 'post', array( 5 ) ), 'mapped() traduce o devuelve el defecto.' );
		$import->add_map( 'shapes', array( 'ola' => 'ola-k1a2b3c', 'mar' => 'mar' ) );
		$import->add_map( 'shapes', array( 'ola' => 'otra', 'rio' => 'rio' ) );
		$import->add_map( 'post', array( 5 => 12, 8 => 20 ) );
		$check( array( 'ola' => 'ola-k1a2b3c', 'mar' => 'mar', 'rio' => 'rio' ) === $import->map( 'shapes' ) && array( 5 => 12, 8 => 20 ) === $import->map( 'post' ), 'add_map() añade sin pisar: gana el primer destino.' );
		$check( 1 === $import->get_report()['counts']['map-conflict'], 'Un destino distinto para el mismo valor se anota como conflicto.' );
		$check( 1 === count( $wrongs( function () use ( $import ) {
			$import->add_map( 'Mal Nombre', array( 1 => 2 ) );
		} ) ), 'Un nombre de mapa inválido es un error.' );
		$check( 1 === count( $wrongs( function () use ( $import ) {
			$import->store( array( 'a' => 1 ) );
		} ) ) && array( 'presets' => array( 'z' => 0 ) ) === $import->stored_all(), 'store() fuera de una parte no guarda nada.' );
		$import->begin_part( 'presets' );
		$import->store( array( 'a' => 1, 'b' => array( 1 ) ) );
		$import->store( array( 'b' => array( 2 ) ) );
		$import->report( 'unresolved', array( 'path' => 'x' ), 'warning' );
		$check( array( 'z' => 0, 'a' => 1, 'b' => array( 2 ) ) === $import->stored() && 'presets' === $import->current_part(), 'store() une por clave con lo guardado en peticiones anteriores.' );
		$import->flag_review( 150, 'gate-unresolved', array( 'rule' => 'user' ) );
		$import->flag_review( 0, 'x' );
		$import->end_part();
		$check( array( 150 ) === $import->review_ids() && 'presets' === $import->review()[150][0]['part'] && 1 === $import->get_report()['counts']['review'], 'flag_review() marca el documento y lo anota en el informe.' );
		$check( 'presets' === $import->get_report()['entries'][1]['part'] && '' === $import->current_part() && array( 'z' => 0, 'a' => 1, 'b' => array( 2 ) ) === $import->stored( 'presets' ), 'El informe sabe qué parte avisó.' );
		$check( 1 === count( $wrongs( function () use ( $import ) {
			$import->begin_part( 'Mal' );
		} ) ) && '' === $import->current_part(), 'begin_part() con una clave inválida es un error.' );
		$check( realpath( $staged . "/media/{$sha}.png" ) === $import->staged_path( "media/{$sha}.png" ) && null !== $import->staged_path( 'files/shapes/ola.svg' ), 'staged_path() da los archivos preparados.' );
		foreach ( array( "media/../media/{$sha}.png", 'files/shapes/no.svg', "media/{$sha}.jpg", '../staged/files/shapes/ola.svg', '/etc/passwd', '' ) as $bad ) {
			$check( null === $import->staged_path( $bad ), 'staged_path() rechaza: ' . $bad );
		}
		if ( $escape ) {
			$check( null === $import->staged_path( "media/{$sha2}.png" ), 'Un enlace simbólico que sale de la carpeta preparada no se lee.' );
		}
		$check( null === ( new Import_Context( array( 'session' => 'x', 'staged_dir' => $tmp . '/no' ) ) )->staged_path( "media/{$sha}.png" ) && '' === ( new Import_Context( array( 'session' => 'x' ) ) )->staged_dir(), 'Sin carpeta preparada no hay archivos.' );

		// Informe: tope por código, niveles y recortes (§5.3).
		$report = new Import_Context( array( 'session' => 'informe' ) );
		for ( $i = 0; $i < Part_Context::MAX_ENTRIES_PER_CODE + 5; $i++ ) {
			$report->report( 'unresolved', array( 'i' => $i ) );
		}
		$report->report( 'nota', array( 'larga' => str_repeat( 'ñ', 400 ), 'objeto' => new stdClass(), 'hondo' => array( array( array( array( 1 ) ) ) ), 'lista' => range( 1, 60 ) ), 'grave' );
		$report->tally( 'media.embedded', 4 );
		$check( 1 === count( $wrongs( function () use ( $report ) {
			$report->report( 'Mal Código' );
		} ) ), 'Un código de informe inválido es un error.' );
		$got = $report->get_report();
		$check( Part_Context::MAX_ENTRIES_PER_CODE + 5 === $got['counts']['unresolved'] && 5 === $got['dropped'] && Part_Context::MAX_ENTRIES_PER_CODE + 2 === count( $got['entries'] ), 'Cada código guarda como mucho 200 entradas; las demás sólo cuentan.' );
		$nota = $got['entries'][ Part_Context::MAX_ENTRIES_PER_CODE ];
		$check( 'warning' === $nota['level'] && strlen( $nota['data']['larga'] ) <= Part_Context::MAX_STRING + 3 && 1 === preg_match( '//u', $nota['data']['larga'] ) && 'object' === $nota['data']['objeto'], 'Nivel desconocido = warning; cadenas recortadas sin romper UTF-8; objetos fuera.' );
		$check( is_string( $nota['data']['hondo'][0][0] ) && 10 === $nota['data']['lista']['_more'] && 4 === $got['counts']['media.embedded'] && 'invalid-code' === end( $got['entries'] )['code'], 'Profundidad y largo de las listas acotados; tally() sólo cuenta.' );
		$check( ! $report->has_errors() && ( function () {
			$c = new Import_Context( array( 'session' => 'e' ) );
			$c->report( 'fallo', array(), 'error' );
			return $c->has_errors();
		} )(), 'has_errors() detecta avisos de nivel error.' );
		$copy = unserialize( serialize( $import ) );
		$check( $copy->get_report() === $import->get_report() && $copy->maps() === $import->maps(), 'Un contexto sólo tiene datos: se puede serializar.' );

		// Reversión y huella.
		$check( Part_Context::hash( array( 'a' => 1, 'b' => array( 'y' => 2, 'x' => 1 ) ) ) === Part_Context::hash( array( 'b' => array( 'x' => 1, 'y' => 2 ), 'a' => 1 ) ), 'La huella no depende del orden de las claves.' );
		$check( Part_Context::hash( array( 1, 2 ) ) !== Part_Context::hash( array( 2, 1 ) ) && Part_Context::hash( array( 'a' => '1' ) ) !== Part_Context::hash( array( 'a' => 1 ) ) && Part_Context::hash( 1.0 ) !== Part_Context::hash( 1 ), 'La huella sí depende del orden de una lista y del tipo.' );
		$check( 40 === strlen( Part_Context::hash( "\xff" ) ) && Part_Context::is_list( array() ) && ! Part_Context::is_list( array( 1 => 'a' ) ), 'La huella tolera bytes inválidos.' );
		$revert = new Revert_Context( array( 'session' => '6512bd43d9caa', 'mode' => 'fresh', 'user' => 3, 'kit_id' => 7 ) );
		$settings = array( 'custom_colors' => array( array( '_id' => 'k1a2b3c', 'color' => '#000' ) ) );
		$check( $revert->is_fresh() && '6512bd43d9caa' === $revert->session() && 3 === $revert->user() && 7 === $revert->kit_id(), 'Datos de la reversión.' );
		$check( $revert->unchanged( array( 'custom_colors' => array( array( 'color' => '#000', '_id' => 'k1a2b3c' ) ) ), Part_Context::hash( $settings ) ), 'unchanged(): el valor que dejó la importación.' );
		$check( ! $revert->unchanged( array( 'custom_colors' => array() ), Part_Context::hash( $settings ) ) && ! $revert->unchanged( $settings, '' ), 'unchanged(): un valor editado, o sin huella, no se pisa.' );
		$check( ( new Revert_Context( array( 'session' => 'x', 'mode' => 'otro' ) ) )->is_add(), 'Un modo desconocido en la reversión es «agregar».' );
		$thrown = false;
		try {
			new Revert_Context( array() );
		} catch ( InvalidArgumentException $error ) {
			$thrown = true;
		}
		$check( $thrown, 'La reversión necesita una sesión.' );
	} finally {
		$rmrf( $tmp );
	}

	// ── 7. Partes de K (B1 a B8): las siete existen y viajan de ida y vuelta ───────────────────────────────────
	$check( array() === Parts::missing_builtin(), 'Faltan partes de K: ' . implode( ', ', Parts::missing_builtin() ) );
	require __DIR__ . '/elements-kit/parts-b.php';

	echo "DIGITALÍSIMO Elements: contrato de kits validado (autocarga, interfaces, partes, referencias y contextos) y las siete partes de K (B1 a B8).\n";
}
