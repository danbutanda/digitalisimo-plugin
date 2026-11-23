<?php
/**
 * WordPress mínimo para pintar y guardar las pantallas de ajustes de DIGITALÍSIMO SEO sin WordPress (pruebas de paridad
 * sitio/red: tests/seo-settings-parity.php). Opciones de sitio por blog y de red en memoria, ganchos reales, y las
 * escrituras pasan por sanitize_option_{opción} como en WordPress (update_option y update_network_option lo hacen).
 * Lo demás que las pantallas llaman y no cambia el resultado (encolar assets, cachés, programar tareas…) responde con un
 * valor neutro; cada llamada queda en $GLOBALS['wp_calls'] para poder comprobarla.
 */
define( 'ABSPATH', dirname( __DIR__ ) . '/fixtures/seo-admin-wp/' );
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 ); define( 'WEEK_IN_SECONDS', 604800 ); }
define( 'DIGITALISIMO_INTEGRATIONS_DIR', dirname( __DIR__, 2 ) . '/digitalisimo-seo/' );
define( 'DIGITALISIMO_INTEGRATIONS_URL', 'https://red.test/wp-content/plugins/digitalisimo-seo/' );
define( 'DIGITALISIMO_INTEGRATIONS_VERSION', 'test' );
define( 'DIGITALISIMO_INTEGRATIONS_FILE', DIGITALISIMO_INTEGRATIONS_DIR . 'digitalisimo-integrations.php' );
define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) . '/sin-plugins' );
require_once __DIR__ . '/../fixtures/wp-compat.php';

class WP_Error { public $code; public $message; public $data; public function __construct( $c = '', $m = '', $d = null ) { $this->code = $c; $this->message = $m; $this->data = $d; } public function get_error_message() { return $this->message; } }
class Digi_Test_Redirect extends Exception {}
class Digi_Test_Die extends Exception {}

$GLOBALS['wp'] = array( 'blog' => 1, 'multisite' => false, 'network_admin' => false, 'options' => array(), 'site_options' => array(), 'filters' => array() );
$GLOBALS['wp_calls'] = array();
$GLOBALS['pagenow'] = 'admin.php';

/* Ganchos reales. */
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['wp']['filters'][ $hook ][ $priority ][] = array( $cb, $args ); return true; }
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
function has_filter( $hook, $cb = false ) { return ! empty( $GLOBALS['wp']['filters'][ $hook ] ); }
function has_action( $hook, $cb = false ) { return has_filter( $hook, $cb ); }
function remove_filter( $hook, $cb, $priority = 10 ) { foreach ( $GLOBALS['wp']['filters'][ $hook ][ $priority ] ?? array() as $i => $row ) if ( $row[0] == $cb ) unset( $GLOBALS['wp']['filters'][ $hook ][ $priority ][ $i ] ); return true; }
function remove_action( $hook, $cb, $priority = 10 ) { return remove_filter( $hook, $cb, $priority ); }
function apply_filters( $hook, $value, ...$args ) {
	$all = $GLOBALS['wp']['filters'][ $hook ] ?? array(); ksort( $all );
	foreach ( $all as $rows ) foreach ( $rows as $row ) $value = call_user_func_array( $row[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $row[1] ) ) );
	return $value;
}
function do_action( $hook, ...$args ) {
	$all = $GLOBALS['wp']['filters'][ $hook ] ?? array(); ksort( $all );
	foreach ( $all as $rows ) foreach ( $rows as $row ) call_user_func_array( $row[0], array_slice( $args, 0, $row[1] ) );
}
function did_action( $hook ) { return 0; }
function doing_action( $hook = null ) { return false; }

/* Opciones: las del sitio por blog y las de red, con sanitize_option_{opción} como WordPress. */
function sanitize_option( $option, $value ) { return apply_filters( "sanitize_option_{$option}", $value, $option, $value ); }
function get_option( $k, $d = false ) { $all = $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ] ?? array(); return array_key_exists( $k, $all ) ? $all[ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $old = get_option( $k ); $v = sanitize_option( $k, $v ); $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ][ $k ] = $v; do_action( "update_option_{$k}", $old, $v, $k ); return true; }
function add_option( $k, $v = '', $deprecated = '', $autoload = null ) { if ( false !== get_option( $k ) ) return false; $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ][ $k ] = sanitize_option( $k, $v ); return true; }
function delete_option( $k ) { unset( $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ][ $k ] ); return true; }
function get_blog_option( $id, $k, $d = false ) { $all = $GLOBALS['wp']['options'][ $id ] ?? array(); return $all[ $k ] ?? $d; }
function get_site_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['wp']['site_options'] ) ? $GLOBALS['wp']['site_options'][ $k ] : $d; }
function update_site_option( $k, $v ) { $old = get_site_option( $k ); if ( $GLOBALS['wp']['multisite'] ) $v = sanitize_option( $k, $v ); $GLOBALS['wp']['site_options'][ $k ] = $v; do_action( "update_site_option_{$k}", $k, $v, $old ); return true; }
function add_site_option( $k, $v ) { if ( false !== get_site_option( $k ) ) return false; return update_site_option( $k, $v ); }
function delete_site_option( $k ) { unset( $GLOBALS['wp']['site_options'][ $k ] ); return true; }
function get_transient( $k ) { return false; } function set_transient( $k, $v, $e = 0 ) { return true; } function delete_transient( $k ) { return true; }
function get_site_transient( $k ) { return false; } function set_site_transient( $k, $v, $e = 0 ) { return true; } function delete_site_transient( $k ) { return true; }
function wp_load_alloptions() { return $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ] ?? array(); }

/* Red y contexto. */
function is_multisite() { return $GLOBALS['wp']['multisite']; }
function is_network_admin() { return $GLOBALS['wp']['network_admin']; }
function is_admin() { return true; }
function get_current_blog_id() { return $GLOBALS['wp']['blog']; }
function get_main_site_id() { return 1; }
function get_current_network_id() { return 1; }
function switch_to_blog( $id ) { $GLOBALS['wp']['stack'][] = $GLOBALS['wp']['blog']; $GLOBALS['wp']['blog'] = (int) $id; return true; }
function restore_current_blog() { $GLOBALS['wp']['blog'] = array_pop( $GLOBALS['wp']['stack'] ); return true; }
function get_site( $id = null ) { return in_array( (int) $id, array( 1, 2 ), true ) ? (object) array( 'blog_id' => (int) $id, 'domain' => 'red.test', 'path' => 2 === (int) $id ? '/sitio2/' : '/' ) : null; }
function get_sites( $args = array() ) { return ! empty( $args['fields'] ) ? array( 1, 2 ) : array( get_site( 1 ), get_site( 2 ) ); }
function current_user_can( $cap ) { return true; }
function is_super_admin() { return true; }
function get_current_user_id() { return 1; }
function is_user_logged_in() { return true; }

/* Direcciones. */
function home_url( $p = '' ) { return get_home_url( null, $p ); }
function get_home_url( $id = null, $p = '' ) { $id = $id ?: $GLOBALS['wp']['blog']; return 'https://red.test' . ( 2 === (int) $id ? '/sitio2' : '' ) . '/' . ltrim( (string) $p, '/' ); }
function site_url( $p = '' ) { return home_url( $p ); }
function get_site_url( $id = null, $p = '' ) { return get_home_url( $id, $p ); }
function admin_url( $p = '' ) { return home_url( 'wp-admin/' . $p ); }
function get_admin_url( $id = null, $p = '' ) { return get_home_url( $id, 'wp-admin/' . $p ); }
function network_admin_url( $p = '' ) { return 'https://red.test/wp-admin/network/' . $p; }
function content_url( $p = '' ) { return 'https://red.test/wp-content/' . $p; }
function plugins_url( $p = '', $f = '' ) { return 'https://red.test/wp-content/plugins/' . $p; }
function rest_url( $p = '' ) { return home_url( 'wp-json/' . $p ); }
function add_query_arg( ...$a ) { if ( is_array( $a[0] ) ) { $args = $a[0]; $url = $a[1] ?? ''; } else { $args = array( $a[0] => $a[1] ); $url = $a[2] ?? ''; } return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }

/* Formularios. */
function wp_nonce_field( $a = -1, $n = '_wpnonce', $referer = true, $echo = true ) { $h = '<input type="hidden" name="' . $n . '" value="n:' . $a . '">'; if ( $echo ) echo $h; return $h; }
function wp_create_nonce( $a = -1 ) { return 'n:' . $a; }
function wp_nonce_url( $u, $a = -1 ) { return add_query_arg( '_wpnonce', 'n:' . $a, $u ); }
function wp_verify_nonce( $n, $a = -1 ) { return 'n:' . $a === $n; }
function check_admin_referer( $a = -1, $q = '_wpnonce' ) { if ( ( $_REQUEST[ $q ] ?? $_POST[ $q ] ?? '' ) === 'n:' . $a ) return 1; throw new Digi_Test_Die( 'nonce ' . $a ); }
function check_ajax_referer( $a = -1, $q = false ) { return 1; }
function settings_fields( $group ) { echo '<input type="hidden" name="option_page" value="' . $group . '"><input type="hidden" name="action" value="update">'; wp_nonce_field( $group . '-options' ); }
function settings_errors() {} function add_settings_error() {}
function register_setting( $group, $name, $args = array() ) { if ( is_callable( $args ) ) $args = array( 'sanitize_callback' => $args ); $GLOBALS['wp']['registered'][ $group ][] = $name; if ( ! empty( $args['sanitize_callback'] ) ) add_filter( "sanitize_option_{$name}", $args['sanitize_callback'] ); }
function submit_button( $text = 'Guardar cambios', $type = 'primary', $name = 'submit', $wrap = true ) { echo '<input type="submit" name="' . $name . '" value="' . esc_attr( $text ?: 'Guardar cambios' ) . '">'; }
function checked( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? " checked='checked'" : ''; }
function selected( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? " selected='selected'" : ''; }
function disabled( $a, $b = true, $echo = true ) { return (string) $a === (string) $b ? " disabled='disabled'" : ''; }
function wp_safe_redirect( $u, $s = 302 ) { throw new Digi_Test_Redirect( $u ); }
function wp_redirect( $u, $s = 302 ) { throw new Digi_Test_Redirect( $u ); }
function wp_die( $m = '' ) { throw new Digi_Test_Die( is_string( $m ) ? $m : 'wp_die' ); }

/* Texto. */
function __( $t, $d = '' ) { return $t; } function esc_html__( $t, $d = '' ) { return esc_html( $t ); } function esc_attr__( $t, $d = '' ) { return esc_attr( $t ); } function _e( $t, $d = '' ) { echo $t; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $u ) { $u = trim( (string) $u ); return '' === $u || preg_match( '#^(https?:)?//|^/#i', $u ) ? $u : ( preg_match( '#^[a-z0-9.-]+\.[a-z]{2,}#i', $u ) ? 'http://' . $u : '' ); }
function esc_js( $t ) { return addslashes( (string) $t ); }
function esc_xml( $t ) { return esc_html( $t ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_text_field( $t ) { return is_scalar( $t ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $t ) ) ) : ''; }
function sanitize_textarea_field( $t ) { return is_scalar( $t ) ? trim( strip_tags( (string) $t ) ) : ''; }
function sanitize_title( $t ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $t ) ), '-' ); }
function sanitize_title_with_dashes( $t ) { return sanitize_title( $t ); }
function sanitize_email( $e ) { return filter_var( (string) $e, FILTER_VALIDATE_EMAIL ) ? (string) $e : ''; }
function sanitize_file_name( $f ) { return (string) $f; }
function is_email( $e ) { return (bool) filter_var( (string) $e, FILTER_VALIDATE_EMAIL ); }
function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_kses_post( $t ) { return (string) $t; }
function wp_check_invalid_utf8( $t ) { return (string) $t; }
function remove_accents( $t ) { return (string) $t; }
function absint( $v ) { return abs( (int) $v ); }
function wp_unslash( $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function maybe_unserialize( $v ) { return $v; } function is_serialized( $v ) { return false; }
function wp_list_pluck( $l, $f ) { return array_map( function ( $r ) use ( $f ) { return is_object( $r ) ? $r->$f : $r[ $f ]; }, (array) $l ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d ); }
function size_format( $b ) { return $b . ' B'; }
function wp_normalize_path( $p ) { return str_replace( '\\', '/', (string) $p ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function get_bloginfo( $k = '' ) { return 'Sitio ' . $GLOBALS['wp']['blog']; }
function get_locale() { return 'es_MX'; }
function current_time( $t ) { return 'mysql' === $t ? date( 'Y-m-d H:i:s' ) : time(); }
function wp_date( $f, $t = null ) { return date( $f, $t ?: time() ); }
function date_i18n( $f, $t = false ) { return date( $f, $t ?: time() ); }
function is_subdomain_install() { return false; }

/* Contenido: dos tipos y dos taxonomías públicas, sin entradas. */
function digi_test_type( $name, $label, $hierarchical = false ) { return (object) array( 'name' => $name, 'label' => $label, 'labels' => (object) array( 'name' => $label, 'singular_name' => $label ), 'public' => true, 'hierarchical' => $hierarchical, 'has_archive' => false, 'object_type' => array( 'post' ), 'rewrite' => array( 'slug' => $name ) ); }
function get_post_types( $args = array(), $output = 'names' ) { $all = array( 'post' => digi_test_type( 'post', 'Entradas' ), 'page' => digi_test_type( 'page', 'Páginas', true ) ); return 'objects' === $output ? $all : array_combine( array_keys( $all ), array_keys( $all ) ); }
function get_post_type_object( $t ) { $all = get_post_types( array(), 'objects' ); return $all[ $t ] ?? null; }
function post_type_exists( $t ) { return null !== get_post_type_object( $t ); }
function get_taxonomies( $args = array(), $output = 'names' ) { $all = array( 'category' => digi_test_type( 'category', 'Categorías', true ), 'post_tag' => digi_test_type( 'post_tag', 'Etiquetas' ) ); return 'objects' === $output ? $all : array_combine( array_keys( $all ), array_keys( $all ) ); }
function get_taxonomy( $t ) { $all = get_taxonomies( array(), 'objects' ); return $all[ $t ] ?? false; }
function taxonomy_exists( $t ) { return false !== get_taxonomy( $t ); }
function get_object_taxonomies( $t, $o = 'names' ) { return 'post' === $t ? array( 'category', 'post_tag' ) : array(); }
function is_post_type_viewable( $t ) { return true; }
function get_posts( $args = array() ) { return array(); }
function get_terms( $args = array() ) { return array(); }
function get_post( $id = null ) { return null; }
function term_exists( $t, $tax = '' ) { return null; }
function get_page_by_path( $p, $o = OBJECT, $t = 'page' ) { return null; }
if ( ! defined( 'OBJECT' ) ) define( 'OBJECT', 'OBJECT' );

/** Lo demás que las pantallas tocan sin que cambie lo que se guarda: un valor neutro y la llamada anotada. */
function digi_test_stub( $name, $args ) { $GLOBALS['wp_calls'][] = array( $name, $args ); return null; }
foreach ( array( 'add_menu_page', 'add_submenu_page', 'remove_submenu_page', 'add_meta_box', 'remove_meta_box', 'add_shortcode', 'add_rewrite_rule', 'flush_rewrite_rules', 'wp_enqueue_media', 'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style', 'wp_add_inline_script', 'wp_localize_script', 'wp_dequeue_script', 'wp_dequeue_style', 'wp_schedule_event', 'wp_schedule_single_event', 'wp_clear_scheduled_hook', 'wp_next_scheduled', 'spawn_cron', 'wp_cache_delete', 'wp_cache_get', 'wp_cache_set', 'wp_prime_option_caches', 'wp_prime_network_option_caches', 'wp_set_option_autoload_values', 'nocache_headers', 'status_header', 'get_current_screen', 'digitalisimo_ui_dependency', 'digitalisimo_ui_screen', 'wp_using_ext_object_cache', 'wp_doing_ajax', 'wp_doing_cron', 'get_template', 'wp_get_theme', 'get_theme_mod', 'get_file_data', 'plugin_basename', 'wp_get_referer', 'get_post_meta', 'update_post_meta', 'get_term_meta', 'paginate_links', 'wp_remote_get', 'wp_remote_post', 'wp_safe_remote_get', 'wp_safe_remote_head', 'wp_upload_dir', 'wp_get_upload_dir', 'wp_get_custom_css', 'get_userdata', 'get_user_by', 'url_to_postid', 'attachment_url_to_postid', 'wp_get_attachment_url', 'wp_get_attachment_image_url', 'get_queried_object', 'get_queried_object_id', 'is_admin_bar_showing', 'wp_script_is', 'wp_style_is', 'has_blocks', 'has_shortcode', 'get_permalink', 'get_term_link', 'get_edit_post_link', 'get_the_title', 'wp_insert_term', 'get_term_by', 'get_term', 'get_network', 'update_blog_status', 'clean_blog_cache', 'is_ssl', 'wp_remote_retrieve_response_code', 'wp_remote_retrieve_header', 'wp_remote_retrieve_body' ) as $digi_fn ) {
	if ( ! function_exists( $digi_fn ) ) eval( 'function ' . $digi_fn . '( ...$a ) { return digi_test_stub( ' . var_export( $digi_fn, true ) . ', $a ); }' );
}

/** Base de datos vacía: las tablas propias (redirecciones, 404, enlaces) no tienen filas. */
class Digi_Test_WPDB { public $prefix = 'wp_'; public $options = 'wp_options'; public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $sitemeta = 'wp_sitemeta'; public $usermeta = 'wp_usermeta'; public $terms = 'wp_terms'; public $term_taxonomy = 'wp_term_taxonomy';
	public function prepare( $q, ...$a ) { return $q; } public function get_var( $q = null ) { return null; } public function get_results( $q = null, $o = null ) { return array(); } public function get_col( $q = null ) { return array(); } public function get_row( $q = null, $o = null ) { return null; } public function query( $q ) { return 0; } public function esc_like( $t ) { return $t; } public function get_charset_collate() { return ''; } public function get_blog_prefix( $id = null ) { return 'wp_'; } public function insert() { return 1; } public function update() { return 1; } public function delete() { return 1; } }
$GLOBALS['wpdb'] = new Digi_Test_WPDB();

/* Clases de SEO con su propio cargador, como el plugin (digitalisimo_integrations_classes()). */
preg_match_all( "#'(Digitalisimo_[A-Za-z0-9_]+)'\s*=>\s*'(includes/[^']+)'#", file_get_contents( DIGITALISIMO_INTEGRATIONS_FILE ), $digi_map, PREG_SET_ORDER );
$GLOBALS['digi_seo_classes'] = array(); foreach ( $digi_map as $row ) $GLOBALS['digi_seo_classes'][ $row[1] ] = $row[2];
spl_autoload_register( function ( $class ) { if ( isset( $GLOBALS['digi_seo_classes'][ $class ] ) ) require_once DIGITALISIMO_INTEGRATIONS_DIR . $GLOBALS['digi_seo_classes'][ $class ]; } );
require_once DIGITALISIMO_INTEGRATIONS_DIR . 'includes/class-digitalisimo-core.php';

/** Contexto de una prueba: 'single', 'site' (el sitio 2 de una red) o 'network' (administración de la red, sitio 1). */
function digi_seo_mode( $mode ) {
	$GLOBALS['wp']['multisite'] = 'single' !== $mode;
	$GLOBALS['wp']['network_admin'] = 'network' === $mode;
	$GLOBALS['wp']['blog'] = 'site' === $mode ? 2 : 1;
	$GLOBALS['pagenow'] = 'admin.php';
}

/** Pinta una pantalla con $_GET y devuelve el HTML. */
function digi_seo_render( callable $screen, array $get ) {
	$_GET = $get; $_POST = array(); $_REQUEST = array(); ob_start();
	// Lo que la pantalla pinta después del formulario (gestor de redirecciones, registro 404, favicon…) necesita tablas
	// y archivos que aquí no hay: el error queda anotado y el formulario ya pintado sigue sirviendo.
	try { $screen(); } catch ( Throwable $e ) { $GLOBALS['render_errors'][] = $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine(); }
	return ob_get_clean();
}

/** El envío a options.php como lo procesa WordPress: comprueba el grupo y su nonce, y guarda cada opción del grupo. */
function digi_seo_options_php( array $post ) {
	$GLOBALS['pagenow'] = 'options.php'; $_POST = $post; $_REQUEST = $post;
	$group = $post['option_page'] ?? '';
	check_admin_referer( $group . '-options' );
	foreach ( $GLOBALS['wp']['registered'][ $group ] ?? array() as $option ) update_option( $option, isset( $post[ $option ] ) ? $post[ $option ] : null );
	$GLOBALS['pagenow'] = 'admin.php'; $_POST = array(); $_REQUEST = array();
}

/** network/edit.php?action=…: dispara network_admin_edit_{acción}; el guardado termina en una redirección. */
function digi_seo_network_edit( $action, array $post ) {
	$_POST = $post; $_REQUEST = $post; $_GET = array( 'action' => $action );
	$redirect = null;
	try { do_action( 'network_admin_edit_' . $action ); } catch ( Digi_Test_Redirect $r ) { $redirect = $r->getMessage(); }
	$_POST = array(); $_REQUEST = array();
	return $redirect;
}
