<?php
/**
 * Paso desde el tema anterior (inc/switch.php): importación idempotente desde Hello, un hijo suyo u otro tema;
 * modo de cabecera y pie; inventario del hijo y de los filtros; evento con el cerrojo ya suelto; dry-run sin
 * escrituras; rodeo que conserva la paridad; reversión; cerrojo atómico con robo condicional; tamaño del registro
 * con carga automática; y vista previa del Personalizador sin escrituras.
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ . '/' );
define( 'MB_IN_BYTES', 1048576 );

function check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
}

// ------------------------------------------------------------------ Stubs de WordPress
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
class WP_Theme {
	public $data;
	public function __construct( array $data ) {
		$this->data = $data + array( 'exists' => true, 'version' => '1.0.0', 'parent' => null, 'dir' => '', 'templates' => array(), 'allowed' => true );
	}
	public function exists() {
		return $this->data['exists'];
	}
	public function get_stylesheet() {
		return $this->data['stylesheet'];
	}
	public function get_template() {
		return $this->data['template'];
	}
	public function get( $header ) {
		return 'Version' === $header ? $this->data['version'] : '';
	}
	public function parent() {
		return $this->data['parent'] ? wp_get_theme( $this->data['parent'] ) : false;
	}
	public function get_stylesheet_directory() {
		return $this->data['dir'];
	}
	public function get_post_templates() {
		$own = $this->data['templates'];
		return $this->parent() ? array_merge_recursive( $own, $this->parent()->get_post_templates() ) : $own;
	}
	public function is_allowed( $check = 'both', $blog = null ) {
		return $this->data['allowed'];
	}
}
class WP_Customize_Manager {
	protected $original_stylesheet;
	public function __construct( $original ) {
		$this->original_stylesheet = $original;
	}
}
class Fake_WPDB {
	public $options = 'wp_options';
	public $rows    = array();
	public $queries = array();
	public $race    = false;
	public function prepare( $query, ...$args ) {
		return array( $query, $args );
	}
	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
	public function query( $prepared ) {
		list( $sql, $args )        = $prepared;
		$this->queries[]          = strtok( $sql, ' ' );
		if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
			check( false !== strpos( $sql, "'off'" ), 'El cerrojo no se carga automáticamente.' );
			if ( isset( $this->rows[ $args[0] ] ) ) {
				return 0;
			}
			$this->rows[ $args[0] ] = $args[1];
			return 1;
		}
		if ( 0 === strpos( $sql, 'UPDATE' ) ) {
			list( $value, $name, $old ) = $args;
			check( false !== strpos( $sql, 'AND option_value = %s' ), 'El robo del cerrojo es condicional.' );
			if ( $this->race || ( $this->rows[ $name ] ?? null ) !== $old ) {
				return 0;
			}
			$this->rows[ $name ] = $value;
			return 1;
		}
		if ( 0 === strpos( $sql, 'DELETE' ) ) {
			list( $name, $like ) = $args;
			$token               = stripslashes( substr( $like, 2 ) );
			if ( isset( $this->rows[ $name ] ) && '|' . $token === substr( $this->rows[ $name ], -strlen( $token ) - 1 ) ) {
				unset( $this->rows[ $name ] );
				return 1;
			}
			return 0;
		}
		throw new RuntimeException( 'Consulta inesperada: ' . $sql );
	}
	public function get_var( $prepared ) {
		$this->queries[] = 'SELECT';
		return $this->rows[ $prepared[1][0] ] ?? null;
	}
}
$GLOBALS['wpdb'] = new Fake_WPDB();

function reset_site() {
	$GLOBALS['options']    = array(
		'stylesheet' => 'digitalisimo-theme',
		'template'   => 'digitalisimo-theme',
	);
	$GLOBALS['autoload']   = array();
	$GLOBALS['writes']     = array();
	$GLOBALS['css']        = array();
	$GLOBALS['css_posts']  = array();
	$GLOBALS['css_writes'] = array();
	$GLOBALS['events']     = array();
	$GLOBALS['switched']   = array();
	$GLOBALS['images']     = array( 10, 11 );
	$GLOBALS['menus']      = array( 3, 4 );
	$GLOBALS['posts']      = array();
	$GLOBALS['meta']       = array();
	$GLOBALS['doing']      = '';
	$GLOBALS['hooks']      = array();
	$GLOBALS['wp_filter']  = array();
	$GLOBALS['multisite']  = false;
	$GLOBALS['preview']    = false;
	$GLOBALS['user']       = 1;
	$GLOBALS['wpdb']->rows    = array();
	$GLOBALS['wpdb']->queries = array();
	$GLOBALS['wpdb']->race    = false;
	unset( $GLOBALS['wp_customize'] );
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;
	$GLOBALS['writes'][]        = $key;
	if ( null !== $autoload ) {
		$GLOBALS['autoload'][ $key ] = $autoload;
	}
	return true;
}
function wp_cache_delete() {
	return true;
}
function get_template() {
	return 'digitalisimo-theme';
}
function get_stylesheet() {
	return 'digitalisimo-theme';
}
function wp_get_theme( $stylesheet = '' ) {
	return isset( $GLOBALS['themes'][ $stylesheet ] ) ? new WP_Theme( $GLOBALS['themes'][ $stylesheet ] ) : new WP_Theme( array( 'stylesheet' => $stylesheet, 'template' => $stylesheet, 'exists' => false ) );
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = array( $callback, $priority, $args );
	return true;
}
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return add_action( $hook, $callback, $priority, $args );
}
function has_action( $hook ) {
	return ! empty( $GLOBALS['hooks'][ $hook ] );
}
function do_action( $hook, ...$args ) {
	$GLOBALS['events'][] = array( $hook, $args );
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $listener ) {
		call_user_func_array( $listener[0], array_slice( $args, 0, $listener[2] ) );
	}
}
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $listener ) {
		$value = call_user_func_array( $listener[0], array_slice( array_merge( array( $value ), $args ), 0, $listener[2] ) );
	}
	return $value;
}
function doing_action( $hook ) {
	return $GLOBALS['doing'] === $hook;
}
function get_current_user_id() {
	return $GLOBALS['user'];
}
function wp_doing_cron() {
	return false;
}
function is_admin() {
	return true;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}
function wp_generate_password( $length ) {
	return substr( bin2hex( random_bytes( $length ) ), 0, $length );
}
function wp_attachment_is_image( $id ) {
	return in_array( (int) $id, $GLOBALS['images'], true );
}
function is_nav_menu( $id ) {
	return in_array( (int) $id, $GLOBALS['menus'], true );
}
function wp_get_custom_css( $stylesheet = '' ) {
	if ( ! empty( $GLOBALS['throw_css'] ) ) {
		throw new RuntimeException( 'fallo simulado al leer el CSS adicional' );
	}
	return $GLOBALS['css'][ $stylesheet ] ?? '';
}
function wp_get_custom_css_post( $stylesheet = '' ) {
	return $GLOBALS['css_posts'][ $stylesheet ] ?? null;
}
function wp_update_custom_css_post( $css, $args = array() ) {
	$GLOBALS['css'][ $args['stylesheet'] ] = $css;
	$GLOBALS['css_writes'][]               = $args;
	return (object) array( 'ID' => 900 + count( $GLOBALS['css_writes'] ) );
}
function get_theme_root_uri() {
	return 'https://sitio.test/wp-content/themes';
}
function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}
function untrailingslashit( $path ) {
	return rtrim( $path, '/\\' );
}
function get_post_status( $id ) {
	return $GLOBALS['posts'][ $id ] ?? false;
}
function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['meta'][ $id ][ $key ] ?? '';
}
function wp_update_post( $post ) {
	$GLOBALS['posts'][ $post['ID'] ] = $post['post_status'];
	return $post['ID'];
}
function get_posts( $args ) {
	return $GLOBALS['page_templates'][ $args['meta_value'] ] ?? array();
}
function switch_theme( $stylesheet ) {
	$GLOBALS['switched'][] = $stylesheet;
}
function is_multisite() {
	return $GLOBALS['multisite'];
}
function get_current_blog_id() {
	return 2;
}
function is_customize_preview() {
	return $GLOBALS['preview'];
}

require __DIR__ . '/../digitalisimo-theme/inc/switch.php';

// ------------------------------------------------------------------ Escenario
$tmp   = sys_get_temp_dir() . '/digitalisimo-theme-switch-' . getmypid();
$child = $tmp . '/hello-child';
@mkdir( $child . '/inc', 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
file_put_contents( $child . '/style.css', "/*\n Theme Name: Hello Child\n Template: hello-elementor\n*/\n.site-header{background:url(img/a.png)}\n.site-footer{background:url('../x.png') , url(data:image/png;base64,AA) , url(/abs.png) , url(\"https://cdn.test/x.png\") , url(./b.png)}\n" );
file_put_contents( $child . '/functions.php', "<?php wp_enqueue_style( 'hijo', get_stylesheet_uri(), array( 'hello-elementor-theme-style' ) );\n" );
file_put_contents( $child . '/inc/x.php', "<?php\n" );
file_put_contents( $child . '/tpl-landing.php', "<?php /* Template Name: Landing */\n" );
$GLOBALS['themes'] = array(
	'hello-elementor'    => array( 'stylesheet' => 'hello-elementor', 'template' => 'hello-elementor', 'version' => '3.5.1', 'templates' => array( 'page' => array( 'parent-tpl.php' => 'P' ) ) ),
	'hello-child'        => array( 'stylesheet' => 'hello-child', 'template' => 'hello-elementor', 'version' => '1.0.0', 'parent' => 'hello-elementor', 'dir' => $child, 'templates' => array( 'page' => array( 'tpl-landing.php' => 'Landing' ) ) ),
	'twentytwentyfive'   => array( 'stylesheet' => 'twentytwentyfive', 'template' => 'twentytwentyfive', 'version' => '1.3' ),
	'digitalisimo-theme' => array( 'stylesheet' => 'digitalisimo-theme', 'template' => 'digitalisimo-theme' ),
	'hello-biz'          => array( 'stylesheet' => 'hello-biz', 'template' => 'hello-biz', 'version' => '1.2.0' ),
);
$GLOBALS['page_templates'] = array( 'tpl-landing.php' => array( 41, 42 ) );

function hello_site( $stylesheet = 'hello-elementor' ) {
	reset_site();
	$GLOBALS['options'][ 'theme_mods_' . $stylesheet ] = array(
		'custom_logo'         => 10,
		'nav_menu_locations'  => array( 'menu-1' => 3, 'menu-2' => 4, 'otra' => 9 ),
		'header_textcolor'    => 'blank',
		'sidebars_widgets'    => array( 'time' => 1, 'data' => array() ),
		'custom_css_post_id'  => 99,
		0                     => 'numérica',
		'hello-header-footer' => array( 'x' => 1 ),
	);
	// WordPress ya trasladó la ubicación por nombre al activar (_wp_menus_changed).
	$GLOBALS['options']['theme_mods_digitalisimo-theme']      = array( 'nav_menu_locations' => array( 'menu-1' => 3 ) );
	$GLOBALS['css'][ $stylesheet ]                            = 'hello-elementor' === $stylesheet ? 'body{color:red}' : '';
	$GLOBALS['css_posts'][ $stylesheet ]                      = (object) array( 'ID' => 77, 'post_content' => $GLOBALS['css'][ $stylesheet ], 'post_content_filtered' => 'body{color:$rojo}' );
	$GLOBALS['options']['hello_elementor_settings_page_title'] = 'true';
	$GLOBALS['options']['hello_elementor_settings_skip_link']  = '';
}
function switch_from( $stylesheet ) {
	$GLOBALS['doing'] = 'after_switch_theme';
	digitalisimo_theme_after_switch( 'Nombre', wp_get_theme( $stylesheet ) );
	$GLOBALS['doing'] = '';
}
function record() {
	return digitalisimo_theme_switch_record();
}
function journal() {
	return digitalisimo_theme_switch_log();
}
function mods() {
	return $GLOBALS['options']['theme_mods_digitalisimo-theme'];
}
function warning_codes() {
	return array_column( journal()['warnings'] ?? array(), 'code' );
}
function switched_events() {
	return array_values( array_filter( $GLOBALS['events'], function ( $event ) {
		return 'digitalisimo_theme_switched' === $event[0];
	} ) );
}

// 1. Hello padre: T1-T5, origen hello y paridad.
hello_site();
switch_from( 'hello-elementor' );
$mods = mods();
check( 10 === $mods['custom_logo'], 'T1: el logo se copia.' );
check( array( 'menu-1' => 3, 'menu-2' => 4 ) === $mods['nav_menu_locations'], 'T2: menu-1 y menu-2 (sólo esas ubicaciones).' );
check( 'blank' === $mods['header_textcolor'], 'T4: el resto de ajustes se copia.' );
foreach ( array( 'sidebars_widgets', 'custom_css_post_id', 0, 'hello-header-footer' ) as $excluded ) {
	check( ! array_key_exists( $excluded, $mods ), 'T4 excluye ' . $excluded );
}
check( 'body{color:red}' === $GLOBALS['css']['digitalisimo-theme'] && 'digitalisimo-theme' === $GLOBALS['css_writes'][0]['stylesheet'] && 'body{color:$rojo}' === $GLOBALS['css_writes'][0]['preprocessed'], 'T3: el CSS adicional se copia con su preprocesado.' );
$record = record();
check( 'hello' === $record['origin']['family'] && 'hello-elementor' === $record['origin']['stylesheet'] && '3.5.1' === $record['origin']['version'] && false === $record['origin']['child'], 'Origen hello.' );
check( 'hello-elementor' === $record['last']['stylesheet'] && 'admin' === $record['last']['via'] && 1 === $record['user'] && 1 === $record['v'], 'Último tema, vía y usuario.' );
check( array( 'description_meta_tag' => false, 'skip_link' => false, 'header_footer' => false, 'page_title' => true, 'hello_style' => false, 'hello_theme' => false ) === $record['hello'], 'T5: los seis ajustes (true = apagado en Hello).' );
check( digitalisimo_theme_parity() && digitalisimo_theme_hello_settings()['page_title'], 'Paridad y ajustes desde el registro.' );
check( true === $GLOBALS['autoload']['digitalisimo_theme_switch'] && false === $GLOBALS['autoload']['digitalisimo_theme_switch_log'], 'Registro con carga automática; bitácora sin ella.' );
check( 'partial' === $record['state'] && in_array( 'description_lost', warning_codes(), true ), 'T9: Hello imprimía la descripción y SEO no: aviso y estado parcial.' );
check( array( 'hello_printed' => true, 'seo_prints' => false ) === journal()['description'], 'T9 en la bitácora.' );
check( isset( journal()['mods']['custom_logo'], journal()['mods']['nav_menu_locations[menu-2]'], journal()['mods']['header_textcolor'] ) && 'body{color:red}' !== journal()['custom_css']['hash'] && md5( 'body{color:red}' ) === journal()['custom_css']['hash'] && 77 === journal()['custom_css']['from_post'], 'La bitácora guarda los hashes de lo escrito.' );
check( 1 === count( switched_events() ) && false === switched_events()[0][1][0]['restore'] && 'hello-elementor' === switched_events()[0][1][1]->get_stylesheet(), 'Un evento, sin restaurar, con el tema anterior.' );
check( array() === $GLOBALS['wpdb']->rows && '' === digitalisimo_theme_lock_token(), 'El cerrojo quedó suelto.' );
check( is_wp_error( digitalisimo_theme_import_previous( wp_get_theme( 'hello-elementor' ) ) ), 'Fuera del cambio de tema, la importación pide force.' );
check( is_wp_error( digitalisimo_theme_import_previous( 'hello-elementor', array( 'force' => true ) ) ), 'Sin un WP_Theme, error.' );
if ( true ) {
	class Digitalisimo_Integrations_Settings {
		public static function get( $key ) {
			return 'enable_seo' === $key ? $GLOBALS['seo'] : null;
		}
	}
}
$GLOBALS['seo'] = 1;
hello_site();
switch_from( 'hello-elementor' );
check( array( 'hello_printed' => true, 'seo_prints' => true ) === journal()['description'] && ! in_array( 'description_lost', warning_codes(), true ), 'Con SEO activo, la descripción no se pierde.' );
$GLOBALS['seo'] = 0;

// 2. Hijo de Hello: versión del padre, inventario, filtros y CSS del hijo sólo si se pide.
hello_site( 'hello-child' );
$GLOBALS['wp_filter'] = array(
	'hello_elementor_page_title' => (object) array( 'callbacks' => array( 10 => array( 'a' => 1, 'b' => 2 ) ) ),
	'hello-plus-theme/settings'  => (object) array( 'callbacks' => array( 5 => array( 'c' => 1 ), 20 => array( 'd' => 1 ) ) ),
	'hello_elementor_vacio'      => (object) array( 'callbacks' => array() ),
	'otro_filtro'                => (object) array( 'callbacks' => array( 10 => array( 'e' => 1 ) ) ),
);
switch_from( 'hello-child' );
$record = record();
$log    = journal();
check( 'hello' === $record['origin']['family'] && '3.5.1' === $record['origin']['version'] && true === $record['origin']['child'] && 'hello-child' === $record['origin']['stylesheet'] && 'hello-elementor' === $record['origin']['template'], 'Hijo: familia hello, versión del padre (FR-13).' );
check( array( 'functions.php', 'inc/x.php', 'tpl-landing.php' ) === $log['child']['php_files'], 'T10: archivos PHP del hijo.' );
check( $log['child']['style_bytes'] > 50 && array( '.site-header', '.site-footer' ) === $log['child']['selectors'] && true === $log['child']['enqueues_hello_deps'], 'T10: bytes del style.css, selectores y dependencias de Hello.' );
check( array( array( 'template' => 'tpl-landing.php', 'pages' => 2 ) ) === $log['child']['page_templates'], 'T10: plantillas que sólo existen en el hijo y cuántos contenidos las usan.' );
check( array( 'hello-plus-theme/settings' => 2, 'hello_elementor_page_title' => 2 ) === $log['filters'], 'T10: callbacks en los filtros de Hello (FR-9).' );
check( ! isset( $GLOBALS['css']['digitalisimo-theme'] ) && array() === $GLOBALS['css_writes'], 'Sin child_css no se escribe CSS.' );
check( in_array( 'child_css', warning_codes(), true ) && in_array( 'child_php', warning_codes(), true ), 'Avisos del CSS y del PHP del hijo.' );
$result = digitalisimo_theme_import_previous( wp_get_theme( 'hello-child' ), array( 'force' => true, 'child_css' => true, 'via' => 'cli' ) );
$child_block    = $GLOBALS['css']['digitalisimo-theme'];
check( ! is_wp_error( $result ) && 'cli' === record()['last']['via'], 'La importación por CLI.' );
check( 0 === strpos( $child_block, "/* digitalisimo-theme: hijo hello-child */\n" ) && "/* /digitalisimo-theme: hijo hello-child */\n" === substr( $child_block, -strlen( "/* /digitalisimo-theme: hijo hello-child */\n" ) ), 'El style.css del hijo va entre sus marcas.' );
check( false === strpos( $child_block, 'Theme Name' ), 'Sin la cabecera del hijo.' );
foreach ( array( 'url(https://sitio.test/wp-content/themes/hello-child/img/a.png)', "url('https://sitio.test/wp-content/themes/hello-child/../x.png')", 'url(data:image/png;base64,AA)', 'url(/abs.png)', 'url("https://cdn.test/x.png")', 'url(https://sitio.test/wp-content/themes/hello-child/b.png)' ) as $url ) {
	check( false !== strpos( $child_block, $url ), 'url() del hijo: ' . $url );
}
digitalisimo_theme_import_previous( wp_get_theme( 'hello-child' ), array( 'force' => true, 'child_css' => true ) );
check( 1 === substr_count( $GLOBALS['css']['digitalisimo-theme'], '/* digitalisimo-theme: hijo hello-child */' ) && $child_block === $GLOBALS['css']['digitalisimo-theme'], 'Repetir con child_css no duplica el bloque.' );
file_put_contents( $child . '/style.css', "/*\n Theme Name: Hello Child\n Template: hello-elementor\n*/\n@import url('x.css');\n.a{color:red}\n" );
hello_site( 'hello-child' );
digitalisimo_theme_import_previous( wp_get_theme( 'hello-child' ), array( 'force' => true, 'child_css' => true ) );
check( ! isset( $GLOBALS['css']['digitalisimo-theme'] ) && in_array( 'child_css_import', warning_codes(), true ), 'Con @import no se copia nada y se avisa.' );

// 3. Otro tema: T1, T2 y T4 sí; T3 y T5 no.
reset_site();
$GLOBALS['options']['theme_mods_twentytwentyfive'] = array( 'custom_logo' => 11, 'nav_menu_locations' => array( 'menu-1' => 4, 'primary' => 3 ), 'background_color' => 'fff' );
$GLOBALS['css']['twentytwentyfive']                = 'h1{color:blue}';
$GLOBALS['options']['hello_elementor_settings_page_title'] = 'true';
switch_from( 'twentytwentyfive' );
$record = record();
check( 'other' === $record['origin']['family'] && 11 === mods()['custom_logo'] && array( 'menu-1' => 4 ) === mods()['nav_menu_locations'] && 'fff' === mods()['background_color'], 'Otro tema: logo, menú y ajustes.' );
reset_site();
$GLOBALS['options']['theme_mods_twentytwentyfive'] = array( 'custom_logo' => 12, 'nav_menu_locations' => array( 'menu-1' => 99, 'menu-2' => 3 ) );
switch_from( 'twentytwentyfive' );
check( ! isset( mods()['custom_logo'] ) && array( 'menu-2' => 3 ) === mods()['nav_menu_locations'], 'Un logo que no es una imagen y un menú que ya no existe no se copian.' );
check( ! isset( $GLOBALS['css']['digitalisimo-theme'] ) && ! isset( $record['hello'] ) && ! isset( $record['header_footer'] ) && ! digitalisimo_theme_parity(), 'Otro tema: ni CSS, ni ajustes de Hello, ni paridad.' );
check( 'done' === $record['state'] && 1 === count( switched_events() ), 'Sin avisos: estado done; evento.' );
reset_site();
switch_from( 'hello-biz' );
check( 'other' === record()['origin']['family'] && in_array( 'hello_variant', warning_codes(), true ), 'hello-biz es otro tema, con aviso.' );

// 4. Mismo template (o tema ya inexistente): no se importa nada.
reset_site();
switch_from( 'digitalisimo-theme' );
check( 'none' === record()['origin']['family'] && array( 'digitalisimo_theme_switch' ) === $GLOBALS['writes'] && array() === switched_events(), 'Desde el mismo tema: sólo origin none.' );
switch_from( 'borrado' );
check( array( 'digitalisimo_theme_switch' ) === $GLOBALS['writes'], 'Con registro ya escrito no se reescribe.' );

// 5. Segunda ejecución: no pisa lo editado y sí actualiza lo que escribió.
hello_site();
switch_from( 'hello-elementor' );
$GLOBALS['options']['theme_mods_digitalisimo-theme']['header_textcolor'] = 'ff0000'; // Editado en el tema.
$GLOBALS['options']['theme_mods_hello-elementor']['custom_logo']        = 11;       // Cambiado en el origen.
$GLOBALS['options']['theme_mods_hello-elementor']['header_textcolor']   = 'blank';
switch_from( 'hello-elementor' );
check( 'ff0000' === mods()['header_textcolor'] && 'editado en destino' === journal()['skipped']['header_textcolor'] && in_array( 'mods_kept', warning_codes(), true ), 'Un ajuste editado en el tema se conserva (skipped).' );
check( 11 === mods()['custom_logo'], 'Un ajuste que escribió la importación anterior se actualiza.' );
$GLOBALS['css']['hello-elementor'] = 'body{color:green}';
switch_from( 'hello-elementor' );
check( 'body{color:green}' === $GLOBALS['css']['digitalisimo-theme'], 'El CSS que escribió la importación se actualiza.' );

// 6. CSS ya presente en el tema: se conserva y se avisa.
hello_site();
$GLOBALS['css']['digitalisimo-theme'] = 'p{margin:0}';
switch_from( 'hello-elementor' );
check( 'p{margin:0}' === $GLOBALS['css']['digitalisimo-theme'] && array() === $GLOBALS['css_writes'] && in_array( 'custom_css_kept', warning_codes(), true ) && 'editado en destino' === journal()['skipped']['custom_css'], 'El CSS propio del tema se conserva.' );

// 7. T6: modo de cabecera y pie.
reset_site();
$off = array_fill_keys( digitalisimo_theme_hello_keys(), false );
check( array( 'experiment' => false, 'header' => 'classic', 'footer' => 'classic', 'kit_id' => 0, 'pending' => false, 'blocked' => '' ) === digitalisimo_theme_header_footer_modes( $off ), 'classic sin experimento ni plantillas.' );
check( 'disabled' === digitalisimo_theme_header_footer_modes( array( 'header_footer' => true ) + $off )['header'], 'disabled con cabecera y pie apagados en Hello.' );
$GLOBALS['options']['elementor_pro_theme_builder_conditions'] = array(
	'header' => array( 50 => array( 'include/general' ), 52 => array( 'include/general' ) ),
	'footer' => array( 51 => array( 'include/general', 'exclude/singular/page' ) ),
);
$GLOBALS['posts'] = array( 50 => 'publish', 51 => 'publish', 52 => 'draft' );
$modes            = digitalisimo_theme_header_footer_modes( $off );
check( 'tb' === $modes['header'] && 'tb_partial' === $modes['footer'], 'tb y tb_partial según las condiciones (incluido _elementor_conditions).' );
$GLOBALS['meta'][50]['_elementor_conditions'] = array( 'include/general', 'exclude/archive/category' );
check( 'tb_partial' === digitalisimo_theme_header_footer_modes( $off )['header'], 'La meta _elementor_conditions manda sobre la caché.' );
$GLOBALS['options']['elementor_pro_theme_builder_conditions']['header'][53] = array( 'include/general' );
$GLOBALS['posts'][53] = 'publish';
check( 'tb' === digitalisimo_theme_header_footer_modes( $off )['header'], 'Si otra plantilla general no tiene exclusiones, tb.' );
unset( $GLOBALS['options']['elementor_pro_theme_builder_conditions']['header'][53] );
$GLOBALS['posts'][50] = 'draft';
check( 'classic' === digitalisimo_theme_header_footer_modes( $off )['header'], 'Una plantilla en borrador no cuenta.' );
unset( $GLOBALS['options']['elementor_pro_theme_builder_conditions'] );
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'active';
$modes = digitalisimo_theme_header_footer_modes( $off );
check( true === $modes['experiment'] && 'dynamic' === $modes['header'] && 'dynamic' === $modes['footer'] && true === $modes['pending'], 'dynamic con el experimento activo: pendiente de convertir.' );
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'default';
$GLOBALS['options']['elementor_install_history']                     = array( '3.20.1' => 1, '3.25.0' => 2 );
check( 'dynamic' === digitalisimo_theme_header_footer_modes( $off )['header'], 'default con la primera instalación ≥ 3.3.0: dynamic.' );
$GLOBALS['options']['elementor_install_history'] = array( '3.2.5' => 1, '3.25.0' => 2 );
check( 'classic' === digitalisimo_theme_header_footer_modes( $off )['header'], 'default con una instalación anterior a 3.3.0: classic.' );
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'inactive';
check( 'classic' === digitalisimo_theme_header_footer_modes( $off )['header'], 'Experimento inactivo: classic.' );
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'active';
$GLOBALS['options']['elementor_active_kit']                          = 8;
$GLOBALS['meta'][8]['_elementor_page_settings']                      = array( 'hello_header_logo_display' => '', 'hello_header_tagline_display' => '', 'hello_header_menu_display' => '', 'hello_footer_logo_display' => '', 'hello_footer_tagline_display' => '', 'hello_footer_menu_display' => '' );
$modes = digitalisimo_theme_header_footer_modes( $off );
check( 'hidden' === $modes['header'] && 'dynamic' === $modes['footer'] && 8 === $modes['kit_id'] && true === $modes['pending'], 'hidden con todo apagado; un interruptor ausente vale «yes».' );
$GLOBALS['meta'][8]['_elementor_page_settings']['hello_footer_copyright_display'] = '';
$modes = digitalisimo_theme_header_footer_modes( $off );
check( 'hidden' === $modes['header'] && 'dynamic' === $modes['footer'] && true === $modes['pending'], 'Con todo oculto sólo la cabecera es hidden: Hello sigue imprimiendo el pie vacío, que se convierte (pendiente).' );
hello_site();
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'active';
switch_from( 'hello-elementor' );
check( 'dynamic' === record()['header_footer']['header'] && true === record()['header_footer']['pending'] && 'partial' === record()['state'], 'La importación deja el modo y el estado parcial.' );

// 7b. Errores que no pueden tumbar el sitio: si after_switch_theme lanzara una excepción, WordPress no vaciaría
// theme_switched y cada petición volvería a fallar.
// Subcarpeta ilegible del hijo: se salta (CATCH_GET_CHILD) y la importación termina.
@mkdir( $child . '/privado', 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
file_put_contents( $child . '/privado/oculto.php', "<?php\n" );
chmod( $child . '/privado', 0 );
clearstatcache();
$unreadable = ! is_readable( $child . '/privado' );
hello_site( 'hello-child' );
switch_from( 'hello-child' );
if ( $unreadable ) {
	check( 'hello' === record()['origin']['family'] && array( 'functions.php', 'inc/x.php', 'tpl-landing.php' ) === journal()['child']['php_files'] && ! in_array( 'switch_failed', warning_codes(), true ), 'Una subcarpeta ilegible del hijo se salta y la importación termina.' );
} else {
	echo "Aviso: chmod 0 no quita la lectura en este sistema (¿root?); se omite el caso de la subcarpeta ilegible.\n";
}
chmod( $child . '/privado', 0777 );
// La carpeta entera del hijo ilegible: inventario sin archivos, sin excepción.
chmod( $child, 0 );
clearstatcache();
if ( ! is_readable( $child ) ) {
	hello_site( 'hello-child' );
	switch_from( 'hello-child' );
	check( 'hello' === record()['origin']['family'] && array() === journal()['child']['php_files'] && ! in_array( 'switch_failed', warning_codes(), true ), 'Con la carpeta del hijo ilegible, el inventario queda sin archivos y la importación termina.' );
}
chmod( $child, 0777 );
unlink( $child . '/privado/oculto.php' );
rmdir( $child . '/privado' );
clearstatcache();
// Una excepción dentro de la importación: no escapa, suelta el cerrojo y queda en la bitácora.
hello_site();
$GLOBALS['throw_css'] = true;
switch_from( 'hello-elementor' );
$GLOBALS['throw_css'] = false;
check( in_array( 'switch_failed', warning_codes(), true ) && false !== strpos( journal()['warnings'][ count( journal()['warnings'] ) - 1 ]['message'], 'fallo simulado' ), 'Una excepción en la importación queda en la bitácora (switch_failed) con su mensaje.' );
check( array() === $GLOBALS['wpdb']->rows && '' === digitalisimo_theme_lock_token() && array() === switched_events(), 'Y suelta el cerrojo, sin avisar a Elements.' );
switch_from( 'hello-elementor' );
check( 'hello' === record()['origin']['family'], 'La siguiente importación funciona.' );
// Una excepción de un oyente de digitalisimo_theme_switched (Elements): la importación ya quedó guardada.
hello_site();
add_action( 'digitalisimo_theme_switched', function () {
	throw new RuntimeException( 'fallo simulado en la conversión' );
} );
switch_from( 'hello-elementor' );
check( 'hello' === record()['origin']['family'] && 10 === mods()['custom_logo'] && in_array( 'switched_event_failed', warning_codes(), true ) && array() === $GLOBALS['wpdb']->rows, 'Una excepción de Elements no deshace la importación, queda en la bitácora y no deja el cerrojo tomado.' );
$GLOBALS['hooks'] = array();

// 8. Evento con el cerrojo ya suelto; dry-run sin eventos ni escrituras.
hello_site();
$seen = array();
add_action( 'digitalisimo_theme_switched', function ( $record, $old ) use ( &$seen ) {
	$seen[] = array( isset( $GLOBALS['wpdb']->rows['digitalisimo_theme_switch_lock'] ) || '' !== digitalisimo_theme_lock_token(), $record['origin']['family'], $old->get_stylesheet() );
}, 10, 2 );
switch_from( 'hello-elementor' );
check( array( array( false, 'hello', 'hello-elementor' ) ) === $seen, 'digitalisimo_theme_switched se dispara una vez y con el cerrojo ya suelto.' );
hello_site();
$simulated = digitalisimo_theme_import_previous( wp_get_theme( 'hello-elementor' ), array( 'force' => true, 'dry_run' => true ) );
check( array() === $GLOBALS['writes'] && array() === $GLOBALS['css_writes'] && array() === $GLOBALS['events'] && array() === $GLOBALS['wpdb']->queries, 'dry_run: cero escrituras, cero eventos y sin tocar el cerrojo.' );
check( true === $simulated['dry_run'] && 'hello' === $simulated['record']['origin']['family'] && true === $simulated['record']['hello']['page_title'] && isset( $simulated['log']['mods']['custom_logo'] ), 'dry_run devuelve lo que guardaría.' );

// 9. Rodeo: Hello → tema → otro → tema conserva el origen, la paridad y pide restaurar.
hello_site();
switch_from( 'hello-elementor' );
$GLOBALS['options']['theme_mods_twentytwentyfive'] = array();
$GLOBALS['events']                                 = array();
switch_from( 'twentytwentyfive' );
$record = record();
check( 'hello' === $record['origin']['family'] && 'hello-elementor' === $record['origin']['stylesheet'] && 'twentytwentyfive' === $record['last']['stylesheet'], 'El origen sigue siendo Hello; el último tema, el del rodeo.' );
check( digitalisimo_theme_parity() && true === $record['hello']['page_title'] && true === switched_events()[0][1][0]['restore'], 'Paridad, ajustes conservados y restore = true (FR-11).' );

// 10. Reversión.
hello_site();
switch_from( 'hello-elementor' );
$GLOBALS['options']['hello_elementor_settings_page_title'] = 'true';
$before = array( $GLOBALS['options']['theme_mods_hello-elementor'], $GLOBALS['options']['hello_elementor_settings_page_title'], $GLOBALS['options']['hello_elementor_settings_skip_link'] );
$GLOBALS['writes'] = array();
check( true === digitalisimo_theme_revert(), 'La reversión termina bien.' );
check( array( 'hello-elementor' ) === $GLOBALS['switched'] && 'reverted' === record()['state'] && record()['reverted'] > 0 && ! digitalisimo_theme_parity(), 'Vuelve al tema anterior y guarda reverted.' );
check( array( 'digitalisimo_theme_switch' ) === $GLOBALS['writes'] && $before === array( $GLOBALS['options']['theme_mods_hello-elementor'], $GLOBALS['options']['hello_elementor_settings_page_title'], $GLOBALS['options']['hello_elementor_settings_skip_link'] ), 'No escribe ajustes ni opciones del tema anterior.' );
check( array() === $GLOBALS['wpdb']->rows, 'Suelta el cerrojo.' );
reset_site();
check( 'digitalisimo_theme_no_previous' === digitalisimo_theme_revert()->get_error_code() && array() === $GLOBALS['switched'], 'Sin tema anterior: WP_Error.' );
hello_site();
switch_from( 'hello-elementor' );
$GLOBALS['multisite'] = true;
$GLOBALS['themes']['hello-elementor']['allowed'] = false;
check( 'digitalisimo_theme_not_allowed' === digitalisimo_theme_revert()->get_error_code() && array() === $GLOBALS['switched'], 'En una red, sólo a un tema habilitado.' );
$GLOBALS['themes']['hello-elementor']['allowed'] = true;
$GLOBALS['multisite']                            = false;
$GLOBALS['options']['digitalisimo_theme_switch']['generated'] = array( 'header' => array( 'id' => 70, 'hash' => 'h' ), 'footer' => array( 'id' => 71, 'hash' => 'f' ) );
$GLOBALS['posts'] = array( 70 => 'publish', 71 => 'trash' );
$error            = digitalisimo_theme_revert();
check( is_wp_error( $error ) && 'digitalisimo_theme_needs_elements' === $error->get_error_code() && array() === $GLOBALS['switched'] && array() === $GLOBALS['wpdb']->rows && 'publish' === $GLOBALS['posts'][70], 'Plantillas generadas sin DIGITALÍSIMO Elements: WP_Error, sin cambiar de tema (FR-6c).' );
check( true === digitalisimo_theme_revert( array( 'force' => true ) ) && 'draft' === $GLOBALS['posts'][70] && 'trash' === $GLOBALS['posts'][71], 'Con force, la publicada pasa a borrador.' );
check( 'draft' === record()['generated']['header']['retired'] && ! isset( record()['generated']['footer']['retired'] ) && array( 'hello-elementor' ) === $GLOBALS['switched'], 'Y queda anotada como retirada.' );
hello_site();
switch_from( 'hello-elementor' );
$GLOBALS['options']['digitalisimo_theme_switch']['generated'] = array( 'header' => array( 'id' => 70, 'hash' => 'h' ) );
$GLOBALS['posts']   = array( 70 => 'publish' );
$heard              = array();
add_action( 'digitalisimo_theme_reverted', function ( $record ) use ( &$heard ) {
	$heard[] = array( $record['state'], ! empty( record()['reverted'] ), $GLOBALS['switched'] );
} );
check( true === digitalisimo_theme_revert() && array( array( 'reverted', true, array() ) ) === $heard && 'publish' === $GLOBALS['posts'][70], 'Con oyente: el registro ya dice reverted, el aviso llega antes del cambio de tema y el tema no toca las plantillas.' );
// Vuelta al tema tras revertir: se quita reverted, vuelve la paridad y se pide restaurar las plantillas (FR-6b).
$GLOBALS['events'] = array();
switch_from( 'hello-elementor' );
check( ! isset( record()['reverted'] ) && digitalisimo_theme_parity() && 'reverted' !== record()['state'] && true === switched_events()[0][1][0]['restore'] && 70 === record()['generated']['header']['id'], 'Ida y vuelta: sin reverted, con paridad, restore = true y las plantillas generadas conservadas en el registro.' );

// 11. Cerrojo: INSERT IGNORE con token; uno reciente bloquea, uno viejo se roba sólo con UPDATE condicional.
hello_site();
$GLOBALS['wpdb']->rows['digitalisimo_theme_switch_lock'] = ( time() - 10 ) . '|ajeno';
switch_from( 'hello-elementor' );
check( array() === record() && ! isset( $GLOBALS['options']['theme_mods_digitalisimo-theme']['custom_logo'] ) && array( 'digitalisimo_theme_switch_log' ) === $GLOBALS['writes'] && array( 'locked' ) === warning_codes(), 'Con un cerrojo reciente no se escribe nada salvo el aviso locked.' );
check( ( time() - 10 ) . '|ajeno' === $GLOBALS['wpdb']->rows['digitalisimo_theme_switch_lock'] && array() === switched_events(), 'El cerrojo ajeno sigue intacto y no hay evento.' );
$GLOBALS['options']['digitalisimo_theme_switch'] = array( 'last' => array( 'stylesheet' => 'hello-elementor' ) );
check( 'digitalisimo_theme_locked' === digitalisimo_theme_revert()->get_error_code() && array() === $GLOBALS['switched'], 'La reversión tampoco entra.' );
unset( $GLOBALS['options']['digitalisimo_theme_switch'] );
$GLOBALS['wpdb']->rows['digitalisimo_theme_switch_lock'] = ( time() - 301 ) . '|ajeno';
$GLOBALS['wpdb']->race                                  = true;
switch_from( 'hello-elementor' );
check( array() === record(), 'Si otro lo renovó entre la lectura y el robo, tampoco entra.' );
$GLOBALS['wpdb']->race    = false;
$GLOBALS['wpdb']->queries = array();
switch_from( 'hello-elementor' );
check( 'hello' === record()['origin']['family'] && array( 'INSERT', 'SELECT', 'UPDATE', 'DELETE' ) === $GLOBALS['wpdb']->queries && array() === $GLOBALS['wpdb']->rows, 'Uno de más de 300 s se roba con UPDATE condicional y se suelta con el token propio.' );
$GLOBALS['wpdb']->queries = array();
check( digitalisimo_theme_lock() && ! digitalisimo_theme_lock(), 'El cerrojo no se toma dos veces en la misma petición.' );
$GLOBALS['wpdb']->rows['digitalisimo_theme_switch_lock'] = time() . '|robado';
digitalisimo_theme_unlock();
check( time() . '|robado' === $GLOBALS['wpdb']->rows['digitalisimo_theme_switch_lock'] && '' === digitalisimo_theme_lock_token(), 'Nunca suelta el cerrojo de otra petición.' );

// 12. Tamaño del registro con carga automática: menos de 1 KB aunque haya 50 avisos.
reset_site();
// Caso realista y desfavorable: el hijo con el nombre más común, cabecera y pie generados y un kit con id largo.
$GLOBALS['themes']['hello-theme-child-master'] = array( 'stylesheet' => 'hello-theme-child-master', 'template' => 'hello-elementor', 'version' => '2.0.0', 'parent' => 'hello-elementor', 'dir' => $tmp . '/no-existe' );
$GLOBALS['options']['elementor_experiment-hello-theme-header-footer'] = 'active';
$GLOBALS['options']['elementor_active_kit']                          = 123456;
$previous = array();
for ( $i = 0; $i < 60; $i++ ) {
	$previous[] = array( 'code' => 'aviso_' . $i, 'message' => str_repeat( 'x', 120 ) );
}
$GLOBALS['options']['digitalisimo_theme_switch_log'] = array( 'warnings' => $previous );
$GLOBALS['user'] = 123;
switch_from( 'hello-theme-child-master' );
$GLOBALS['options']['digitalisimo_theme_switch']['generated'] = array( 'header' => array( 'id' => 123456, 'hash' => md5( 'a' ) ), 'footer' => array( 'id' => 123457, 'hash' => md5( 'b' ) ) );
$GLOBALS['options']['digitalisimo_theme_switch']['header_footer']['pending'] = false;
$size = strlen( serialize( record() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
check( $size < 1024, 'El registro pesa ' . $size . ' B serializado (máximo 1 KB).' );
echo 'Registro con carga automática: ' . $size . " B serializado.\n";
check( 50 === count( journal()['warnings'] ) && 'aviso_0' !== journal()['warnings'][0]['code'], 'La bitácora guarda como mucho 50 avisos, los más recientes.' );
check( 1 === count( array_keys( array_column( digitalisimo_theme_merge_warnings( array( array( 'code' => 'a', 'message' => '1' ) ), array( array( 'code' => 'a', 'message' => '2' ) ) ), 'code' ), 'a' ) ), 'Un aviso repetido no se duplica.' );

// 13. Vista previa del Personalizador: sin registro, la paridad sale del tema original; nada se escribe.
reset_site();
$GLOBALS['preview']                                         = true;
$GLOBALS['wp_customize']                                    = new WP_Customize_Manager( 'hello-child' );
$GLOBALS['options']['hello_elementor_settings_skip_link']   = 'true';
$GLOBALS['options']['hello_elementor_settings_hello_style'] = 'false';
check( digitalisimo_theme_parity(), 'Vista previa desde un hijo de Hello: paridad.' );
check( true === digitalisimo_theme_hello_settings()['skip_link'] && false === digitalisimo_theme_hello_settings()['hello_style'], 'Los ajustes de Hello se leen en vivo.' );
$GLOBALS['wp_customize'] = new WP_Customize_Manager( 'twentytwentyfive' );
check( ! digitalisimo_theme_parity(), 'Vista previa desde otro tema: sin paridad.' );
$GLOBALS['preview'] = false;
$GLOBALS['wp_customize'] = new WP_Customize_Manager( 'hello-elementor' );
check( ! digitalisimo_theme_parity(), 'Fuera de la vista previa, sin registro no hay paridad.' );
check( array() === $GLOBALS['writes'] && array() === $GLOBALS['wpdb']->queries, 'La vista previa no escribe nada.' );

$cleanup = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $cleanup as $entry ) {
	$entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
}
rmdir( $tmp );
echo "Tema DIGITALÍSIMO: paso desde el tema anterior validado (importación, inventario, modos, evento, rodeo, reversión, cerrojo, tamaño y vista previa).\n";
