<?php
/**
 * H24 (ensayo real E4): con una actualización de DIGITALÍSIMO Elements pendiente, la pantalla Plugins (del sitio y de la
 * red) daba error 500. El módulo de compatibilidad de PRO engancha la fila de actualización de Elements
 * (`in_plugin_update_message-digitalisimo-elements/pro-elements.php`) y `Version::create_from_string()` del núcleo de
 * Elementor sólo acepta versiones de tres partes: con las de cuatro de Elements (4.3.0.184) lanzaba una excepción sin
 * capturar.
 *
 * Con las clases reales del núcleo embebido y del componente de Elements, y WordPress mínimo:
 * - el `do_action` de la reproducción no lanza nada y no pinta nada (misma versión menor);
 * - con un cambio de versión menor (4.3.0.183 → 4.4.0.1) se pinta el aviso de compatibilidad con los plugins que lo
 *   declaran: los que no llegan a 4.4 y el que trae una versión inválida (de cuatro partes), como «Unknown»;
 * - una versión nueva inválida, o ausente, no lanza ni pinta nada, y un error dentro del aviso no deja HTML a medias;
 * - el informe de información del sistema lee la versión de Elements en tres partes.
 */
namespace Elementor {
	class Plugin { public static $instance; }
}

namespace Elementor\Modules\System_Info {
	class Module { public static $reports = array(); public static function add_report( $name, $args ) { self::$reports[ $name ] = $args; } }
}

namespace {
	error_reporting( E_ALL );
	set_error_handler( static function ( $no, $str, $file, $line ) { throw new ErrorException( $str, 0, $no, $file, $line ); } );
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ELEMENTOR_PRO_PLUGIN_BASE', 'digitalisimo-elements/pro-elements.php' );
	define( 'ELEMENTOR_PRO_VERSION', '4.3.0.183' ); // Peor caso: la constante con cuatro partes.

	$GLOBALS['h24_hooks'] = array();
	function add_action( $tag, $cb, $priority = 10, $args = 1 ) { $GLOBALS['h24_hooks'][ $tag ][] = $cb; return true; }
	function add_filter( $tag, $cb, $priority = 10, $args = 1 ) { return add_action( $tag, $cb, $priority, $args ); }
	function do_action( $tag, ...$args ) { foreach ( $GLOBALS['h24_hooks'][ $tag ] ?? array() as $cb ) $cb( ...$args ); }
	function esc_html__( $text, $domain = '' ) { return $text; }
	function __( $text, $domain = '' ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function get_option( $name, $default = false ) { return 'active_plugins' === $name ? array( 'a/a.php' ) : $default; }

	function h24_check( $ok, $message ) {
		if ( ! $ok ) {
			fwrite( STDERR, "FALLA: $message\n" );
			exit( 1 );
		}
	}

	$core = __DIR__ . '/../digitalisimo-elements/elementor/';
	require $core . 'core/base/base-object.php';
	require $core . 'core/base/module.php';
	require $core . 'core/utils/version.php';
	require $core . 'core/utils/collection.php';
	require $core . 'modules/compatibility-tag/compatibility-tag.php';
	require $core . 'modules/compatibility-tag/base-module.php';
	require __DIR__ . '/../digitalisimo-elements/modules/compatibility-tag/compatibility-tag-component.php';

	$header = ElementorPro\Modules\CompatibilityTag\Compatibility_Tag_Component::PLUGIN_VERSION_TESTED_HEADER;
	Elementor\Plugin::$instance = (object) array(
		'wp' => new class() {
			public $plugins = array();
			public function get_plugins() { return new Elementor\Core\Utils\Collection( $this->plugins ); }
		},
	);
	Elementor\Plugin::$instance->wp->plugins = array(
		'a/a.php' => array( 'Name' => 'Extensión vieja', $header => '4.3' ),
		'b/b.php' => array( 'Name' => 'Extensión al día', $header => '4.4' ),
		'c/c.php' => array( 'Name' => 'Extensión de cuatro partes', $header => '4.3.0.9' ),
		'd/d.php' => array( 'Name' => 'Sin cabecera' ),
	);
	new ElementorPro\Modules\CompatibilityTag\Compatibility_Tag_Component();

	$row   = 'in_plugin_update_message-digitalisimo-elements/pro-elements.php';
	$paint = static function ( $args ) use ( $row ) {
		$level = ob_get_level();
		ob_start();
		try {
			do_action( $row, $args );
		} catch ( Throwable $e ) {
			while ( ob_get_level() > $level ) ob_end_clean();
			return 'LANZÓ ' . get_class( $e ) . ': ' . $e->getMessage();
		}
		h24_check( ob_get_level() === $level + 1, 'El aviso dejó búferes de salida abiertos o cerrados de más.' );
		return (string) ob_get_clean();
	};

	h24_check( isset( $GLOBALS['h24_hooks'][ $row ] ), 'El componente no engancha la fila de actualización de Elements.' );
	$out = $paint( array( 'new_version' => '4.3.0.184', 'Version' => '4.3.0.183' ) );
	h24_check( '' === $out, 'La reproducción de H24 (4.3.0.183 → 4.3.0.184) no debe lanzar ni pintar nada: ' . $out );

	$out = $paint( array( 'new_version' => '4.4.0.1', 'Version' => '4.3.0.183' ) );
	h24_check( 0 !== strpos( $out, 'LANZÓ' ) && false !== strpos( $out, 'Compatibility Alert' ) && false !== strpos( $out, '(4.4.0)' ), 'Con un cambio de versión menor se pinta el aviso de compatibilidad: ' . $out );
	h24_check( false !== strpos( $out, 'Extensión vieja' ) && false === strpos( $out, 'Extensión al día' ) && false === strpos( $out, 'Sin cabecera' ), 'El aviso lista sólo las extensiones que no llegan a la versión nueva: ' . $out );
	h24_check( false !== strpos( $out, 'Extensión de cuatro partes</td>' ) && false !== strpos( $out, '<td>Unknown</td>' ), 'Una versión de tercero inválida (cuatro partes) sale como «Unknown», sin lanzar: ' . $out );

	foreach ( array( array( 'new_version' => 'beta', 'Version' => '4.3.0.183' ), array( 'new_version' => '4.3.0.184.7', 'Version' => '4.3.0.183' ), array( 'Version' => '4.3.0.183' ), array( 'new_version' => array( 'x' ), 'Version' => null ) ) as $args ) {
		$out = $paint( $args );
		h24_check( '' === $out, 'Una versión nueva inválida o ausente no lanza ni pinta nada: ' . json_encode( $args ) . ' → ' . $out );
	}

	// Un error dentro del aviso (aquí, la lista de plugins): nada a medias y la pantalla sigue.
	$wp                          = Elementor\Plugin::$instance->wp;
	Elementor\Plugin::$instance->wp = new class() { public function get_plugins() { echo '<hr class="a-medias">'; throw new RuntimeException( 'fallo al leer los plugins' ); } };
	$out = $paint( array( 'new_version' => '4.4.0.1', 'Version' => '4.3.0.183' ) );
	h24_check( '' === $out, 'Un error dentro del aviso no lanza ni deja HTML a medias: ' . $out );
	Elementor\Plugin::$instance->wp = $wp;

	do_action( 'elementor/system_info/get_allowed_reports' );
	$report = Elementor\Modules\System_Info\Module::$reports['pro-elements_compatibility'] ?? null;
	h24_check( is_array( $report ) && '4.3.0' === (string) $report['fields']['plugin_version'], 'El informe del sistema lee la versión de Elements en tres partes.' );

	h24_check( '4.3.0' === ElementorPro\Modules\CompatibilityTag\Compatibility_Tag_Component::three_parts( '4.3.0.184' ) && '4.3.0' === ElementorPro\Modules\CompatibilityTag\Compatibility_Tag_Component::three_parts( '4.3.0' ) && '4.3.0.1.2' === ElementorPro\Modules\CompatibilityTag\Compatibility_Tag_Component::three_parts( '4.3.0.1.2' ), 'three_parts() sólo recorta las versiones de cuatro partes.' );

	echo "DIGITALÍSIMO Elements: la fila de actualización de Elements no rompe la pantalla Plugins con versiones de cuatro partes (H24).\n";
}
