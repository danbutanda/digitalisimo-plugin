<?php
/**
 * Paleta DIGITALÍSIMO del constructor: con el núcleo incluido, la administración, el editor (también en tema oscuro,
 * con el mismo media que la hoja oscura del núcleo), la App y el lienzo de vista previa cargan las hojas propias,
 * después de las del núcleo y con dependencias que siempre existen. Con el plugin Elementor activo no se carga nada.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	function check_palette( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['t'] = array( 'hooks' => array(), 'styles' => array(), 'scripts' => array(), 'inline' => array(), 'registered' => array() );
	function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['t']['hooks'][ $hook ][] = array( $callback, $priority ); }
	function did_action() { return false; }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . $path; }
	function wp_style_is( $handle, $list = 'enqueued' ) { return 'registered' === $list ? isset( $GLOBALS['t']['registered'][ $handle ] ) || isset( $GLOBALS['t']['styles'][ $handle ] ) : isset( $GLOBALS['t']['styles'][ $handle ] ); }
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) { $GLOBALS['t']['styles'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'media' => $media ); }
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) { $GLOBALS['t']['scripts'][ $handle ] = array( 'src' => $src, 'footer' => $footer, 'enqueued' => false ); }
	function wp_add_inline_script( $handle, $js ) { $GLOBALS['t']['inline'][ $handle ] = $js; }
	function wp_enqueue_script( $handle ) { $GLOBALS['t']['scripts'][ $handle ]['enqueued'] = true; }
	function wp_styles() { return (object) array( 'registered' => $GLOBALS['t']['registered'] ); }
	function digitalisimo_ui_dependency() { return array( 'digitalisimo-ui' ); }
	final class Digitalisimo_Elements_Core {
		public static $bundled = false;
		public static function bundled() { return self::$bundled; }
	}
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-identity.php';
	$id = 'Digitalisimo_Elements_Identity';
	$hook = static function ( $name, $method ) {
		foreach ( $GLOBALS['t']['hooks'][ $name ] ?? array() as $entry ) {
			if ( array( 'Digitalisimo_Elements_Identity', $method ) === $entry[0] ) {
				return $entry[1];
			}
		}
		return null;
	};

	$id::init();
	check_palette( array() === $GLOBALS['t']['hooks'], 'Con el plugin Elementor activo no se carga la paleta propia.' );
	Digitalisimo_Elements_Core::$bundled = true;
	$id::init();
	check_palette( 100 === $hook( 'admin_enqueue_scripts', 'enqueue_palette' ) && 100 === $hook( 'elementor/editor/after_enqueue_styles', 'enqueue_editor_palette' ) && null !== $hook( 'elementor/app/init', 'enqueue_app_palette' ) && 100 === $hook( 'elementor/preview/enqueue_styles', 'enqueue_preview_palette' ), 'La paleta se engancha a la administración, el editor, la App y el lienzo, después de las hojas del núcleo.' );

	// Administración: admin_enqueue_scripts pasa el sufijo de la pantalla, que nunca debe acabar como dependencia.
	$id::enqueue_palette( 'post.php' );
	$palette = $GLOBALS['t']['styles'][ $id::PALETTE ];
	check_palette( array( 'digitalisimo-ui' ) === $palette['deps'] && false !== strpos( $palette['src'], 'assets/digitalisimo/palette.css' ) && 'all' === $palette['media'], 'En la administración, palette.css depende sólo de la hoja compartida.' );

	// Editor en tema «auto»: la hoja oscura propia copia el media de la del núcleo y un script lo sigue en vivo.
	$GLOBALS['t']['styles']     = array();
	$GLOBALS['t']['registered'] = array( 'elementor-editor' => true, 'e-theme-ui-dark' => (object) array( 'args' => '(prefers-color-scheme: dark)' ) );
	$id::enqueue_editor_palette();
	$dark = $GLOBALS['t']['styles'][ $id::PALETTE_DARK ];
	check_palette( array( 'digitalisimo-ui', 'elementor-editor' ) === $GLOBALS['t']['styles'][ $id::PALETTE ]['deps'], 'En el editor, palette.css se imprime después de las hojas del editor.' );
	check_palette( '(prefers-color-scheme: dark)' === $dark['media'] && array( $id::PALETTE ) === $dark['deps'] && false !== strpos( $dark['src'], 'palette-dark.css' ), 'palette-dark.css lleva el media de la hoja oscura del núcleo y sale después de palette.css.' );
	$sync = $GLOBALS['t']['inline'][ $id::PALETTE_DARK ] ?? '';
	check_palette( false === $GLOBALS['t']['scripts'][ $id::PALETTE_DARK ]['src'] && true === $GLOBALS['t']['scripts'][ $id::PALETTE_DARK ]['footer'] && $GLOBALS['t']['scripts'][ $id::PALETTE_DARK ]['enqueued'] && false !== strpos( $sync, '"e-theme-ui-dark-css"' ) && false !== strpos( $sync, '"digitalisimo-elements-palette-dark-css"' ) && false !== strpos( $sync, 'attributeFilter:["media"]' ), 'Un script en el pie copia el media cuando la preferencia cambia en vivo.' );
	// Tema claro elegido (la hoja oscura del núcleo con media none) o sin hoja oscura registrada: la propia no aplica.
	foreach ( array( (object) array( 'args' => 'none' ), null ) as $registered ) {
		$GLOBALS['t']['styles']     = array();
		$GLOBALS['t']['registered'] = array_filter( array( 'elementor-editor' => true, 'e-theme-ui-dark' => $registered ) );
		$id::enqueue_editor_palette();
		check_palette( 'none' === $GLOBALS['t']['styles'][ $id::PALETTE_DARK ]['media'], 'Sin tema oscuro en el editor, palette-dark.css no aplica.' );
	}
	$GLOBALS['t']['styles'] = array();
	$GLOBALS['t']['registered'] = array( 'e-theme-ui-dark' => (object) array( 'args' => 'all' ) );
	$id::enqueue_editor_palette();
	check_palette( array( 'digitalisimo-ui' ) === $GLOBALS['t']['styles'][ $id::PALETTE ]['deps'] && 'all' === $GLOBALS['t']['styles'][ $id::PALETTE_DARK ]['media'], 'Una dependencia sin registrar nunca se declara (WordPress descartaría la hoja).' );

	// App: la acción corre antes de encolar app.css; la dependencia la imprime después.
	$GLOBALS['t']['styles'] = array();
	$id::enqueue_app_palette( new stdClass() );
	check_palette( array( 'digitalisimo-ui', 'elementor-app' ) === $GLOBALS['t']['styles'][ $id::PALETTE ]['deps'], 'En la App, palette.css sale después de app.css.' );
	$id::enqueue_palette( 'toplevel_page_elementor-app' );
	check_palette( array( 'digitalisimo-ui', 'elementor-app' ) === $GLOBALS['t']['styles'][ $id::PALETTE ]['deps'], 'Una segunda llamada no reemplaza la hoja ya encolada.' );

	// Lienzo: sólo la hoja del lienzo, sin la compartida (es la página real del sitio).
	$GLOBALS['t']['styles']     = array();
	$GLOBALS['t']['registered'] = array( 'editor-preview' => true );
	$id::enqueue_preview_palette();
	check_palette( array( $id::PALETTE_PREVIEW ) === array_keys( $GLOBALS['t']['styles'] ) && array( 'editor-preview' ) === $GLOBALS['t']['styles'][ $id::PALETTE_PREVIEW ]['deps'] && false !== strpos( $GLOBALS['t']['styles'][ $id::PALETTE_PREVIEW ]['src'], 'palette-preview.css' ), 'El lienzo carga sólo palette-preview.css, después de editor-preview.' );
	echo "DIGITALÍSIMO Elements: paleta propia en la administración, el editor, la App y el lienzo.\n";
}
