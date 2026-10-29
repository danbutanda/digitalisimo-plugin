<?php
/**
 * F7b, frente A: arranque del módulo `modules/digitalisimo-f7b/` (§3.0, V.12 y el contrato del brief).
 *
 * - `BASE` y `BOOT` exactos; autocarga acotada a `Digitalisimo\Elements\F7b\` (minúsculas, `_` → `-`, prefijos
 *   `class-`/`interface-`/`trait-`, como mucho tres subespacios) que nunca toca el disco con otro nombre.
 * - `load()` con y sin archivos: un submódulo sin archivo se omite; sin `init()`, no arranca; uno que lanza al cargar o
 *   al arrancar (también una clase que hereda de otra que aún no existe) no tumba la petición y queda anotado.
 * - `requires( array $symbols, string $who )` (clases, métodos, funciones, constantes) y su aviso del editor, una vez.
 * - `editing()`: editor, vista previa, `elementor_ajax`, REST del constructor; el front, el resto de la REST y WP-CLI no.
 * - `Flags`: la opción con las seis claves existe siempre (`ensure()` en `admin_init`, sin usar `add_option()` como
 *   cerrojo), `has()` sólo lee `wp_load_alloptions()` (ni `get_option()` ni consultas), `active()`, y el recálculo
 *   (una consulta para las seis agujas, sólo cuando un documento empieza o deja de usar algo, una vez por petición).
 * - `Hidden_Rule`: la regla exacta, una sola vez por hoja de documento.
 * - Con los contadores a 0, en el front: la base no engancha nada del render, y tampoco los submódulos de los frentes
 *   que dependen de un contador (se arrancan los que existan en el árbol, con dobles de los símbolos de sus guardas).
 * - `pro-elements.php`: una sola línea de arranque (con su `require_once` y un comentario) detrás de `Woo\Module::init()`.
 */
namespace {
	require __DIR__ . '/fixtures/f7b-wp.php';
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$dir  = $root . '/modules/digitalisimo-f7b/';
	require $dir . 'class-module.php';
	$M = '\\Digitalisimo\\Elements\\F7b\\Module';

	// ── 1. Contrato de la base y del arranque ────────────────────────────────────────────────────────────────────
	f7b_check( array( 'Actions\\Module', 'Queries\\Module', 'Overrides\\Module', 'Nested\\Module', 'Editor\\Module', 'Inline_Tags\\Module', 'Forms\\Module', 'Kit\\Module' ) === $M::BOOT, 'BOOT es exactamente el del brief, en su orden.' );
	f7b_check( array( 'Flags', 'Usages', 'Scan', 'Deletion' ) === $M::BASE, 'La base: banderas, usos, recorrido y borrado.' );
	foreach ( array( 'class-flags.php', 'class-usages.php', 'class-scan.php', 'class-deletion.php', 'class-hidden-rule.php' ) as $file ) {
		f7b_check( is_file( $dir . $file ), "Existe {$file}." );
	}

	// ── 2. Autocarga ─────────────────────────────────────────────────────────────────────────────────────────────
	$tmp = sys_get_temp_dir() . '/f7b-module-' . getmypid() . '/';
	foreach ( array( 'inline-tags', 'kit/parts', 'nested', 'boot-a', 'boot-c', 'boot-d', 'boot-e' ) as $sub ) {
		@mkdir( $tmp . $sub, 0777, true ); // phpcs:ignore
	}
	$ns = 'namespace Digitalisimo\\Elements\\F7b';
	file_put_contents( $tmp . 'class-foo-bar.php', "<?php {$ns}; final class Foo_Bar {}" );
	file_put_contents( $tmp . 'inline-tags/class-module.php', "<?php {$ns}\\Inline_Tags; final class Module {}" );
	file_put_contents( $tmp . 'kit/parts/class-saved-queries.php', "<?php {$ns}\\Kit\\Parts; final class Saved_Queries {}" );
	file_put_contents( $tmp . 'nested/interface-thing.php', "<?php {$ns}\\Nested; interface Thing {}" );
	file_put_contents( $tmp . 'boot-a/class-module.php', "<?php {$ns}\\Boot_A; final class Module { public static function init(): void { \$GLOBALS['booted'][] = 'A'; } }" );
	file_put_contents( $tmp . 'boot-c/class-module.php', "<?php {$ns}\\Boot_C; final class Module {}" );
	file_put_contents( $tmp . 'boot-d/class-module.php', "<?php {$ns}\\Boot_D; final class Module { public static function init(): void { \$GLOBALS['booted'][] = 'D'; throw new \\RuntimeException( 'roto' ); } }" );
	file_put_contents( $tmp . 'boot-e/class-module.php', "<?php {$ns}\\Boot_E; final class Module extends \\No\\Existe\\Todavia { public static function init(): void { \$GLOBALS['booted'][] = 'E'; } }" );

	$GLOBALS['booted'] = array();
	$started           = $M::load( $tmp, array( 'Boot_A\\Module', 'Boot_B\\Module', 'Boot_C\\Module', 'Boot_D\\Module', 'Boot_E\\Module' ) );
	f7b_check( array( 'Boot_A\\Module' ) === $started, 'Sin archivos de la base ni de B se omiten; C sin init() no arranca; D y E fallan sin tumbar la petición (' . implode( ', ', $started ) . ').' );
	f7b_check( array( 'A', 'D' ) === $GLOBALS['booted'], 'Se llamó init() de A y de D (que lanzó); E no llegó a cargarse.' );
	$missing = $M::missing();
	f7b_check( isset( $missing['boot_d/module'][0] ) && false !== strpos( $missing['boot_d/module'][0], 'roto' ) && isset( $missing['boot_e/module'][0] ), 'Los fallos al arrancar quedan anotados para el aviso del editor.' );
	f7b_check( 1 === count( f7b_hooks( 'elementor/editor/after_enqueue_scripts' ) ), 'El aviso del editor se engancha una sola vez.' );

	f7b_check( $tmp . 'class-foo-bar.php' === $M::file_for( 'Digitalisimo\\Elements\\F7b\\Foo_Bar' ), 'F7b\\Foo_Bar → class-foo-bar.php.' );
	f7b_check( $tmp . 'inline-tags/class-module.php' === $M::file_for( '\\Digitalisimo\\Elements\\F7b\\Inline_Tags\\Module' ), 'F7b\\Inline_Tags\\Module → inline-tags/class-module.php.' );
	f7b_check( $tmp . 'kit/parts/class-saved-queries.php' === $M::file_for( 'Digitalisimo\\Elements\\F7b\\Kit\\Parts\\Saved_Queries' ), 'Dos subespacios: kit/parts/class-saved-queries.php.' );
	f7b_check( $tmp . 'nested/interface-thing.php' === $M::file_for( 'Digitalisimo\\Elements\\F7b\\Nested\\Thing' ), 'Interfaces con prefijo interface-.' );
	foreach ( array( 'Digitalisimo\\Elements\\Woo\\Flags', 'Digitalisimo\\Elements\\F7bX\\Flags', 'Digitalisimo\\Elements\\F7b\\..\\x', 'Digitalisimo\\Elements\\F7b\\a-b', 'Digitalisimo\\Elements\\F7b\\Missing', 'Digitalisimo\\Elements\\F7b\\A\\B\\C\\D\\E', 'Digitalisimo\\Elements\\F7b\\' ) as $name ) {
		f7b_check( '' === $M::file_for( $name ), "Sin archivo para {$name}." );
	}
	f7b_check( 'inline-tags' === $M::slug( 'Inline_Tags' ), 'Inline_Tags → inline-tags.' );
	f7b_check( class_exists( 'Digitalisimo\\Elements\\F7b\\Kit\\Parts\\Saved_Queries' ) && ! class_exists( 'Digitalisimo\\Elements\\Woo\\Ninguna' ), 'La autocarga carga las clases del módulo e ignora las demás.' );

	// ── 3. Guardas ───────────────────────────────────────────────────────────────────────────────────────────────
	$M::reset();
	f7b_reset_wp();
	f7b_check( true === $M::requires( array( 'Digitalisimo\\Elements\\F7b\\Module', 'Digitalisimo\\Elements\\F7b\\Module::requires', 'add_action()', 'add_action', 'const:ABSPATH' ), 'ok' ), 'Clase, método, función (con y sin paréntesis) y constante existentes.' );
	f7b_check( array() === $M::missing() && array() === f7b_hooks( 'elementor/editor/after_enqueue_scripts' ), 'Una guarda que pasa no anota ni engancha nada.' );
	f7b_check( false === $M::requires( array( 'add_action()', 'No\\Existe', 'Digitalisimo\\Elements\\F7b\\Module::nada', 'no_existe()', 'const:NO_EXISTE' ), 'prueba' ), 'Falta algo: false.' );
	f7b_check( array( 'No\\Existe', 'Digitalisimo\\Elements\\F7b\\Module::nada', 'no_existe()', 'const:NO_EXISTE' ) === $M::missing()['prueba'], 'Anota exactamente lo que falta, con el nombre del submódulo.' );
	file_put_contents( $tmp . 'late.php', '<?php namespace Fake\\Core; class Late_Child extends Late_Parent_That_Comes_In_Init {}' );
	spl_autoload_register( static function ( $class ) use ( $tmp ) {
		if ( 'Fake\\Core\\Late_Child' === $class ) {
			require $tmp . 'late.php';
		}
	} );
	f7b_check( false === $M::requires( array( 'Fake\\Core\\Late_Child' ), 'tarde' ) && isset( $M::missing()['tarde'] ), 'Una clase que hereda de otra que aún no existe cuenta como ausente, sin error fatal.' );
	$M::requires( array( 'Otra\\Falta' ), 'otra' );
	f7b_check( 1 === count( f7b_hooks( 'elementor/editor/after_enqueue_scripts' ) ), 'El aviso se engancha una sola vez aunque fallen varias guardas.' );
	$GLOBALS['f7b']['can'] = static function ( $cap ) { return 'manage_options' === $cap; };
	$M::editor_notice();
	$inline = $GLOBALS['f7b']['inline'][0] ?? array( '', '' );
	f7b_check( 'elementor-editor' === $inline[0] && false !== strpos( $inline[1], 'prueba' ) && false !== strpos( $inline[1], 'otra' ) && false !== strpos( $inline[1], 'SIMO Elements' ), 'El aviso del editor nombra los submódulos, sólo para quien administra.' );
	$GLOBALS['f7b']['can']    = static function () { return false; };
	$GLOBALS['f7b']['inline'] = array();
	$M::editor_notice();
	f7b_check( empty( $GLOBALS['f7b']['inline'] ), 'Sin manage_options no hay aviso.' );
	$GLOBALS['f7b']['can'] = null;

	// ── 4. editing() ────────────────────────────────────────────────────────────────────────────────────────────
	$editing = static function ( array $get, array $request = array(), array $server = array(), string $pagenow = '', bool $ajax = false ) use ( $M ): bool {
		$_GET                     = $get;
		$_REQUEST                 = array_merge( $get, $request );
		$_SERVER                  = array_merge( array( 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php' ), $server );
		$GLOBALS['pagenow']       = $pagenow;
		$GLOBALS['f7b']['ajax']   = $ajax;
		$M::set_editing( null );
		return $M::editing();
	};
	f7b_check( false === $editing( array() ), 'Una página del front no es el editor.' );
	f7b_check( true === $editing( array( 'elementor-preview' => '12', 'ver' => '1' ) ), 'La vista previa del editor sí.' );
	f7b_check( true === $editing( array(), array( 'action' => 'elementor_ajax' ), array(), 'admin-ajax.php', true ), 'admin-ajax.php?action=elementor_ajax sí.' );
	f7b_check( false === $editing( array(), array( 'action' => 'heartbeat' ), array(), 'admin-ajax.php', true ), 'Otra acción de admin-ajax.php no.' );
	f7b_check( true === $editing( array( 'post' => '12', 'action' => 'elementor' ), array(), array(), 'post.php' ), 'post.php?action=elementor (el editor) sí.' );
	f7b_check( false === $editing( array( 'post' => '12', 'action' => 'edit' ), array(), array(), 'post.php' ), 'post.php?action=edit no.' );
	f7b_check( true === $editing( array(), array(), array( 'REQUEST_URI' => '/wp-json/elementor/v1/globals' ) ), 'La REST del constructor sí.' );
	f7b_check( true === $editing( array(), array(), array( 'REQUEST_URI' => '/sub/wp-json/elementor-pro/v1/refresh-loop?x=1' ) ), 'La REST de PRO, también en una instalación en subcarpeta.' );
	f7b_check( true === $editing( array( 'rest_route' => '/elementor/v1/import-export-customization/import' ) ), '?rest_route=/elementor/v1/… sí.' );
	f7b_check( false === $editing( array(), array(), array( 'REQUEST_URI' => '/wp-json/wp/v2/posts' ) ), 'La REST de WordPress no.' );
	f7b_check( false === $editing( array(), array(), array( 'REQUEST_URI' => '/blog/elementor/v1/' ) ), 'Una URL que sólo se parece no.' );
	f7b_check( false === $editing( array( 'rest_route' => '/wp/v2/elementor_library' ) ), '?rest_route de otro espacio no.' );
	$editing( array() );

	// ── 5. Flags ─────────────────────────────────────────────────────────────────────────────────────────────────
	$M::reset();
	f7b_reset_wp();
	$M::set_editing( false );
	$started = $M::load( $dir, array() );
	f7b_check( array( 'Flags', 'Usages', 'Scan', 'Deletion' ) === $started, 'Con la carpeta real y sin submódulos arranca exactamente la base.' );
	$F    = '\\Digitalisimo\\Elements\\F7b\\Flags';
	$keys = array( 'actions', 'hidden', 'overrides', 'saved_queries', 'inline_tags', 'form_conditions' );
	f7b_check( 'digitalisimo_elements_f7b_counts' === $F::OPTION && $keys === array_keys( $F::NEEDLES ), 'La opción y sus seis claves, en el orden del brief.' );
	f7b_check(
		array(
			'actions'         => '"_digitalisimo_actions_on":"yes"',
			'hidden'          => '"_digitalisimo_hidden_initial":"yes"',
			'overrides'       => '"digitalisimo_overrides":[{',
			'saved_queries'   => '"digitalisimo_saved_query":"',
			'inline_tags'     => '"digitalisimo_inline_tags":"yes"',
			'form_conditions' => '"digitalisimo_conditional":"yes"',
		) === $F::NEEDLES,
		'Las agujas son exactamente las del brief.'
	);
	$GLOBALS['f7b']['reads'] = 0;
	foreach ( $keys as $key ) {
		f7b_check( false === $F::has( $key ) && false === $F::active( $key ), "Sin la opción, {$key} vale 0 y no está activo." );
	}
	f7b_check( 0 === $GLOBALS['f7b']['reads'] && array() === $GLOBALS['wpdb']->log, 'has() y active() no leen get_option() ni consultan.' );
	f7b_check( array() === f7b_hooks( 'admin_init' ), 'Fuera de la administración no se engancha admin_init.' );
	$M::reset();
	f7b_reset_wp();
	$GLOBALS['f7b']['admin'] = true;
	$F::reset();
	$F::init();
	f7b_check( 1 === count( f7b_hooks( 'admin_init' ) ), 'En la administración, ensure() en admin_init.' );
	do_action( 'admin_init' );
	f7b_check( array_fill_keys( $keys, 0 ) === get_option( $F::OPTION ) && true === $GLOBALS['f7b']['autoload'][ $F::OPTION ], 'admin_init crea la opción con las seis claves a 0 y carga automática.' );
	f7b_check( array() === $GLOBALS['wpdb']->log, 'Crearla no consulta (el primer recorrido calcula los valores).' );
	$writes = count( $GLOBALS['f7b']['writes'] );
	do_action( 'admin_init' );
	f7b_check( $writes === count( $GLOBALS['f7b']['writes'] ), 'Ya creada, admin_init no escribe.' );
	$GLOBALS['f7b']['autoload'][ $F::OPTION ] = false;
	f7b_check( true === $F::ensure() && true === $GLOBALS['f7b']['autoload'][ $F::OPTION ] && $writes === count( $GLOBALS['f7b']['writes'] ), 'Si existe sin carga automática, se la pone sin reescribirla.' );
	$GLOBALS['f7b']['options'][ $F::OPTION ] = array( 'actions' => 2, 'form_conditions' => '1', 'otra' => 9, 'hidden' => -3 );
	f7b_check( true === $F::has( 'actions' ) && true === $F::has( 'form_conditions' ) && false === $F::has( 'hidden' ) && false === $F::has( 'otra' ), 'has(): enteros ≥ 0 y sólo las seis claves.' );
	f7b_check( array( 'actions' => 2, 'hidden' => 0, 'overrides' => 0, 'saved_queries' => 0, 'inline_tags' => 0, 'form_conditions' => 1 ) === $F::counts(), 'counts() normaliza.' );
	$M::set_editing( true );
	f7b_check( true === $F::active( 'overrides' ) && false === $F::has( 'overrides' ), 'active() también en el editor.' );
	$M::set_editing( false );

	// Recálculo.
	$data = static function ( array $settings, string $type = 'widget', string $widget = 'heading' ): string {
		return json_encode( array( array( 'id' => 'a1', 'elType' => $type, 'widgetType' => $widget, 'settings' => $settings, 'elements' => array() ) ) );
	};
	$doc = static function ( int $id, string $status = 'publish' ) {
		return new class( $id, $status ) {
			private $id;
			public function __construct( $id ) { $this->id = $id; }
			public function get_main_id() { return $this->id; }
			public function is_autosave() { return false; }
		};
	};
	$GLOBALS['f7b']['options'][ $F::OPTION ] = array_fill_keys( $keys, 0 );
	f7b_post( 10, 'page', 'publish', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $data( array( 'title' => 'x' ) ) ) );
	f7b_post( 11, 'elementor_library', 'publish', array( '_elementor_data' => $data( array( '_digitalisimo_actions_on' => 'yes' ) ) ) );
	f7b_post( 12, 'page', 'draft', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $data( array( 'digitalisimo_saved_query' => '55' ), 'widget', 'posts' ) ) );
	f7b_post( 13, 'page', 'trash', array( '_elementor_edit_mode' => 'builder', '_elementor_data' => $data( array( 'digitalisimo_inline_tags' => 'yes' ) ) ) );
	f7b_post( 14, 'revision', 'inherit', array( '_elementor_data' => $data( array( 'digitalisimo_conditional' => 'yes' ) ) ) );
	f7b_post( 15, 'shop_order', 'publish', array() );
	$counts = $F::refresh();
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ), 'refresh(): una sola consulta para las seis claves.' );
	list( $sql, $args ) = $GLOBALS['wpdb']->log[0];
	f7b_check( 6 === substr_count( $sql, 'LIKE %s' ) && false !== strpos( $sql, "meta_key = '_elementor_data'" ) && false !== strpos( $sql, "post_status IN ('publish','future','private','draft','pending')" ) && false !== strpos( $sql, "post_type <> 'revision'" ), 'La consulta mira sólo _elementor_data de los estados que cuentan, sin revisiones.' );
	f7b_check( '%"\\_digitalisimo\\_actions\\_on":"yes"%' === $args[0] && '%"digitalisimo\\_overrides":[{%' === $args[2], 'Los patrones pasan por esc_like().' );
	f7b_check( array( 'actions' => 1, 'hidden' => 0, 'overrides' => 0, 'saved_queries' => 1, 'inline_tags' => 0, 'form_conditions' => 0 ) === $counts && $counts === $F::counts(), 'Cuenta la plantilla publicada y el borrador; nunca la papelera ni las revisiones.' );
	$writes = count( $GLOBALS['f7b']['writes'] );
	$F::refresh();
	f7b_check( $writes === count( $GLOBALS['f7b']['writes'] ), 'Sin cambios, refresh() no escribe.' );

	$GLOBALS['wpdb']->log      = array();
	$GLOBALS['f7b']['meta_ops'] = array();
	do_action( 'transition_post_status', 'processing', 'pending', get_post( 15 ) );
	do_action( 'before_delete_post', 15 );
	do_action( 'deleted_post', 15 );
	f7b_check( array() === $GLOBALS['wpdb']->log && array() === $GLOBALS['f7b']['meta_ops'] && ! $F::dirty(), 'Un pedido: ni consultas ni lecturas de metas.' );

	// Guardado del editor sin cambio de capacidades: nada.
	do_action( 'elementor/document/before_save', $doc( 10 ), array() );
	do_action( 'elementor/document/after_save', $doc( 10 ), array( 'elements' => json_decode( $data( array( 'title' => 'y' ) ), true ) ) );
	f7b_check( ! $F::dirty() && array() === $GLOBALS['wpdb']->log, 'Guardar sin empezar ni dejar de usar nada: ningún recálculo.' );
	// Empezar a usar ocultar: recálculo diferido.
	do_action( 'elementor/document/before_save', $doc( 10 ), array() );
	update_post_meta( 10, '_elementor_data', $data( array( '_digitalisimo_hidden_initial' => 'yes' ) ) );
	f7b_check( ! $F::dirty(), 'Durante un guardado del editor, la meta no decide (lo hace after_save).' );
	do_action( 'elementor/document/after_save', $doc( 10 ), array( 'elements' => json_decode( $data( array( '_digitalisimo_hidden_initial' => 'yes' ) ), true ) ) );
	f7b_check( $F::dirty() && array() === $GLOBALS['wpdb']->log && 1 === count( f7b_hooks( 'shutdown' ) ), 'Empezar a usar una capacidad pide un recálculo, al final de la petición.' );
	do_action( 'elementor/document/before_save', $doc( 11 ), array() );
	update_post_meta( 11, '_elementor_data', '[]' );
	do_action( 'elementor/document/after_save', $doc( 11 ), array( 'elements' => array() ) );
	do_action( 'shutdown' );
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ) && 1 === $F::counts()['hidden'] && 0 === $F::counts()['actions'] && 1 === count( f7b_hooks( 'shutdown' ) ), 'Dos cambios en la petición: una sola consulta, y shutdown enganchado una vez.' );
	// Migración: _elementor_data escrito fuera del editor.
	$GLOBALS['wpdb']->log = array();
	update_post_meta( 12, '_elementor_data', $data( array( 'digitalisimo_saved_query' => '56' ), 'widget', 'posts' ) );
	f7b_check( ! $F::dirty(), 'Migración sin cambio de capacidades (el valor anterior sale de la caché): nada.' );
	update_post_meta( 12, '_elementor_data', $data( array( 'title' => 'sin consulta' ) ) );
	f7b_check( $F::dirty(), 'Migración que quita una capacidad: recálculo.' );
	$F::flush();
	f7b_check( 0 === $F::counts()['saved_queries'] && ! $F::dirty(), 'flush() recalcula y limpia.' );
	f7b_post( 16, 'page', 'draft' );
	update_post_meta( 16, '_elementor_data', $data( array( 'digitalisimo_overrides' => array( array( 'slot' => 'a' ) ) ), 'widget', 'template' ) );
	f7b_check( $F::dirty(), 'Una meta nueva con una capacidad: recálculo.' );
	$F::flush();
	f7b_check( 1 === $F::counts()['overrides'], 'La sobrescritura del borrador cuenta.' );
	$GLOBALS['f7b']['posts'][14]['status'] = 'inherit';
	update_post_meta( 14, '_elementor_data', $data( array( 'digitalisimo_conditional' => 'yes' ) ) );
	f7b_check( ! $F::dirty(), 'Una revisión nunca recalcula.' );
	// Estados.
	$GLOBALS['f7b']['posts'][11]['status'] = 'trash';
	update_post_meta( 11, '_elementor_data', $data( array( '_digitalisimo_actions_on' => 'yes' ) ) );
	f7b_check( ! $F::dirty(), 'Escribir en la papelera no cuenta.' );
	$GLOBALS['f7b']['posts'][11]['status'] = 'publish';
	do_action( 'transition_post_status', 'publish', 'trash', get_post( 11 ) );
	f7b_check( $F::dirty(), 'Restaurar desde la papelera un documento con acciones: recálculo.' );
	$F::flush();
	f7b_check( 1 === $F::counts()['actions'], 'Las acciones vuelven a contar.' );
	do_action( 'transition_post_status', 'draft', 'publish', get_post( 11 ) );
	f7b_check( ! $F::dirty(), 'De publicado a borrador sigue contando: nada.' );
	do_action( 'before_delete_post', 11 );
	unset( $GLOBALS['f7b']['posts'][11], $GLOBALS['f7b']['meta'][11] );
	do_action( 'deleted_post', 11 );
	f7b_check( $F::dirty(), 'Borrar un documento con acciones: recálculo.' );
	$F::flush();
	f7b_check( 0 === $F::counts()['actions'], 'Ya no cuenta.' );
	$GLOBALS['wpdb']->log = array();
	do_action( 'digitalisimo_elements_kit_imported', 'sesion', array() );
	f7b_check( 1 === count( $GLOBALS['wpdb']->log ), 'Al terminar una importación de K, una consulta.' );

	// ── 6. Hidden_Rule ───────────────────────────────────────────────────────────────────────────────────────────
	$H = '\\Digitalisimo\\Elements\\F7b\\Hidden_Rule';
	f7b_check( ':is(.elementor-element,.elementor-column)[hidden]{display:none!important}' === $H::RULE, 'La regla es exactamente la del brief.' );
	$sheet = static function () {
		return new class() {
			public $raw = array();
			public function add_raw_css( $css, $device = '' ) { $this->raw[] = $css; return $this; }
		};
	};
	$css = static function ( $sheet ) {
		return new class( $sheet ) {
			private $sheet;
			public function __construct( $sheet ) { $this->sheet = $sheet; }
			public function get_stylesheet() { return $this->sheet; }
		};
	};
	$one = $sheet();
	$two = $sheet();
	$H::add( $css( $one ) );
	$H::add( $css( $one ) );
	$H::add( $css( $two ) );
	$H::add( new \stdClass() );
	$H::add( null );
	f7b_check( array( $H::RULE ) === $one->raw && array( $H::RULE ) === $two->raw, 'add(): una sola vez por hoja de documento; cada documento la suya.' );
	unset( $two );
	for ( $i = 0; $i < 300; $i++ ) {
		$s = $sheet();
		$H::add( $css( $s ) );
		f7b_check( array( $H::RULE ) === $s->raw, 'Una hoja nueva (aunque reutilice el ID de otra liberada) recibe la regla.' );
	}

	// ── 7. Front con los contadores a 0 ─────────────────────────────────────────────────────────────────────────
	$render_hooks = array( 'wp_enqueue_scripts', 'wp_head', 'wp_footer', 'wp_print_footer_scripts', 'template_redirect', 'the_content', 'pre_get_posts', 'body_class', 'script_loader_tag', 'rest_request_before_callbacks', 'elementor/frontend/builder_content_data', 'elementor/element/parse_css', 'elementor/element/before_parse_css', 'elementor/element/is_dynamic_content', 'elementor/query/query_args', 'elementor_pro/forms/validation', 'pre_option_elementor_element_cache_ttl', 'elementor/frontend/the_content' );
	$is_render    = static function ( string $hook ) use ( $render_hooks ): bool {
		return in_array( $hook, $render_hooks, true ) || 0 === strpos( $hook, 'elementor/widget/' ) || ( 0 === strpos( $hook, 'elementor/frontend/' ) && ! in_array( $hook, array( 'elementor/frontend/after_register_styles', 'elementor/frontend/after_register_scripts' ), true ) );
	};
	$M::reset();
	f7b_reset_wp();
	$M::set_editing( false );
	$GLOBALS['f7b']['options']['digitalisimo_elements_f7b_counts']  = array_fill_keys( $keys, 0 );
	$GLOBALS['f7b']['autoload']['digitalisimo_elements_f7b_counts'] = true;
	foreach ( array( 'Flags', 'Usages', 'Scan', 'Deletion' ) as $base ) {
		$class = '\\Digitalisimo\\Elements\\F7b\\' . $base;
		$class::reset();
	}
	$M::load( $dir, array() );
	$base_hooks = array_keys( f7b_hooks_of( 'Digitalisimo\\Elements\\F7b\\' ) );
	sort( $base_hooks );
	$expected = array( 'added_post_meta', 'before_delete_post', 'deleted_post', 'deleted_user', 'delete_user', 'digitalisimo_elements_f7b_scan', 'digitalisimo_elements_kit_imported', 'elementor/ajax/register_actions', 'elementor/document/after_save', 'elementor/document/before_save', 'elementor/import-export-customization/import-kit', 'elementor/import-export-customization/revert-kit', 'elementor/import-export/import-kit', 'elementor/import-export/revert-kit', 'pre_delete_post', 'pre_trash_post', 'transition_post_status', 'update_post_meta', 'updated_post_meta', 'wpmu_delete_user', 'wxr_export_skip_postmeta' );
	sort( $expected );
	f7b_check( $expected === $base_hooks, 'En el front, la base sólo engancha datos (guardado, metas, estados, borrado, cron, kits, exportación): de más ' . implode( ', ', array_diff( $base_hooks, $expected ) ) . '; de menos ' . implode( ', ', array_diff( $expected, $base_hooks ) ) );
	f7b_check( array() === array_filter( $base_hooks, $is_render ) && array() === $GLOBALS['wpdb']->log && 0 === $GLOBALS['f7b']['reads'], 'Ni un gancho del render, ni consultas, ni get_option() en el front.' );

	// Los submódulos que existan en el árbol, con dobles de los símbolos de sus guardas.
	$gated   = array( 'Actions', 'Queries', 'Overrides', 'Inline_Tags', 'Forms' );
	$present = array();
	foreach ( $M::BOOT as $boot ) {
		if ( '' !== $M::file_for( 'Digitalisimo\\Elements\\F7b\\' . $boot ) ) {
			$present[] = $boot;
		}
	}
	$declared = array();
	$declare  = static function ( string $symbol ) use ( &$declared ) {
		$symbol = ltrim( str_replace( '\\\\', '\\', $symbol ), '\\' );
		if ( '' === $symbol || 0 === strpos( $symbol, 'const:' ) || 0 === strpos( $symbol, 'Digitalisimo\\' ) ) {
			return;
		}
		if ( '()' === substr( $symbol, -2 ) ) {
			$fn = substr( $symbol, 0, -2 );
			if ( ! function_exists( $fn ) && preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $fn ) ) {
				eval( "function {$fn}( ...\$a ) { return null; }" ); // phpcs:ignore Squiz.PHP.Eval
			}
			return;
		}
		list( $class, $method ) = array_pad( explode( '::', $symbol, 2 ), 2, '' );
		$declared[ $class ][]   = $method;
	};
	foreach ( $present as $boot ) {
		$path   = $M::file_for( 'Digitalisimo\\Elements\\F7b\\' . $boot );
		$source = (string) file_get_contents( $path );
		$consts = array();
		preg_match_all( "/const\\s+([A-Z_]+)\\s*=\\s*'([^']+)'/", $source, $found, PREG_SET_ORDER );
		foreach ( $found as $match ) {
			$consts[ $match[1] ] = $match[2];
		}
		preg_match_all( '/requires\(\s*array\(([\s\S]*?)\)\s*,/', $source, $lists );
		preg_match_all( '/(?:const SYMBOLS|\$symbols)\s*=\s*array\(([\s\S]*?)\);/', $source, $more );
		foreach ( array_merge( $lists[1], $more[1] ) as $list ) {
			preg_match_all( "/'([^']+)'|self::([A-Z_]+)/", $list, $items, PREG_SET_ORDER );
			foreach ( $items as $item ) {
				$declare( '' !== $item[1] ? $item[1] : ( $consts[ $item[2] ] ?? '' ) );
			}
		}
	}
	foreach ( $declared as $class => $methods ) {
		if ( class_exists( $class, false ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $class ) ) {
			continue;
		}
		$pos   = strrpos( $class, '\\' );
		$ns    = false === $pos ? '' : 'namespace ' . substr( $class, 0, $pos ) . '; ';
		$short = false === $pos ? $class : substr( $class, $pos + 1 );
		$body  = '';
		foreach ( array_unique( array_filter( $methods ) ) as $method ) {
			$body .= "public static function {$method}( ...\$a ) { return null; } ";
		}
		eval( "{$ns}abstract class {$short} { {$body} }" ); // phpcs:ignore Squiz.PHP.Eval
	}
	$before  = f7b_hooks_of( 'Digitalisimo\\Elements\\F7b\\' );
	$started = $M::load( $dir );
	$after   = f7b_hooks_of( 'Digitalisimo\\Elements\\F7b\\' );
	$report  = array();
	foreach ( $present as $boot ) {
		$sub    = 'Digitalisimo\\Elements\\F7b\\' . explode( '\\', $boot )[0] . '\\';
		$hooks  = array_keys( f7b_hooks_of( $sub ) );
		$render = array_values( array_filter( $hooks, $is_render ) );
		$name   = explode( '\\', $boot )[0];
		$report[] = $name . ( in_array( $boot, $started, true ) ? '' : ' (sin arrancar: ' . implode( ', ', $M::missing()[ strtolower( $name ) ] ?? $M::missing()[ str_replace( '_', '-', strtolower( $name ) ) ] ?? array( '?' ) ) . ')' ) . ( $render ? ' [render: ' . implode( ', ', $render ) . ']' : '' );
		if ( in_array( $name, $gated, true ) ) {
			f7b_check( array() === $render, "Con los contadores a 0, {$name} no engancha nada del render en el front: " . implode( ', ', $render ) );
		}
		if ( 'Editor' === $name ) {
			f7b_check( array() === $render, 'El submódulo del editor no engancha nada del render: ' . implode( ', ', $render ) );
		}
	}
	f7b_check( array() === $GLOBALS['wpdb']->log, 'Arrancar todos los submódulos en el front no hace consultas.' );
	echo 'Submódulos presentes en el árbol: ' . ( $report ? implode( '; ', $report ) : 'ninguno' ) . "\n";

	// ── 8. pro-elements.php ──────────────────────────────────────────────────────────────────────────────────────
	$pro   = (string) file_get_contents( $root . '/pro-elements.php' );
	$woo   = strpos( $pro, "\\Digitalisimo\\Elements\\Woo\\Module::init();" );
	$line  = "require_once __DIR__ . '/modules/digitalisimo-f7b/class-module.php';";
	$init  = "\\Digitalisimo\\Elements\\F7b\\Module::init();";
	$found = strpos( $pro, $line );
	f7b_check( false !== $woo && false !== $found && $found > $woo && 1 === substr_count( $pro, $line ) && 1 === substr_count( $pro, $init ) && strpos( $pro, $init ) > $found, 'pro-elements.php arranca F7b una sola vez, detrás de Woo\\Module::init().' );
	$between = substr( $pro, $woo + strlen( "\\Digitalisimo\\Elements\\Woo\\Module::init();" ), $found - $woo - strlen( "\\Digitalisimo\\Elements\\Woo\\Module::init();" ) );
	f7b_check( 1 === preg_match( '/^\s*\/\/ [^\n]+\n\s*$/', $between ) && false === stripos( $between, 'bricks' ), 'Con un comentario de una línea, sin nombres de proveedores.' );

	echo 'DIGITALÍSIMO Elements: módulo F7b (frente A) en verde (' . $GLOBALS['f7b_passed'] . " comprobaciones).\n";
}
