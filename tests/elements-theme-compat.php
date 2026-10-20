<?php
/**
 * Convivencia de DIGITALÍSIMO Elements con el tema DIGITALÍSIMO: el asistente de configuración lo da por bueno y no
 * puede cambiarlo por el tema de origen (ni por AJAX ni por REST), y el aviso de cambio de tema convierte la cabecera
 * y el pie en la administración, los deja para el cron en el front y espera a un usuario con permisos. El tema se
 * reconoce también en una carpeta renombrada (por su Update URI), y dejarlo desde Apariencia retira lo generado.
 */
namespace Elementor {
	class Plugin { public static $instance; public $documents; public $widgets_manager; public $files_manager; }
}
namespace ElementorPro\Modules\ThemeBuilder {
	class Module {
		private static $instance;
		public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }
		public function get_conditions_manager() { return new \Fake_Conditions(); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['c'] = array( 'hooks' => array(), 'template' => 'hello-elementor', 'ajax' => false, 'admin' => false, 'cron' => false, 'json' => array(), 'options' => array(), 'scheduled' => array(), 'user' => 0, 'caps' => array( 1 => true ), 'posts' => array(), 'meta' => array(), 'next' => 10, 'created' => 0, 'uris' => array( 'digitalisimo-theme' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme', 'digitalisimo-theme-1.0.0' => 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme', 'otro' => 'https://github.com/otro/tema' ), 'cleared' => 0 );
	function check_tc( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['c']['hooks'][] = array( $hook, $callback, $priority, $args ); }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback, $priority, $args ); }
	function get_template() { return $GLOBALS['c']['template']; }
	function wp_doing_ajax() { return $GLOBALS['c']['ajax']; }
	function wp_doing_cron() { return $GLOBALS['c']['cron']; }
	function is_admin() { return $GLOBALS['c']['admin']; }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
	function wp_unslash( $value ) { return $value; }
	class Json_Sent extends Exception {}
	function wp_send_json_error( $data = null, $status = null ) { $GLOBALS['c']['json'][] = array( $data, $status ); throw new Json_Sent(); }
	class WP_Error { public $code; public $message; public $data; public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; } }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	class Fake_Request { private $method; private $route; public function __construct( $method, $route ) { $this->method = $method; $this->route = $route; } public function get_method() { return $this->method; } public function get_route() { return $this->route; } }
	function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['c']['options'] ) ? $GLOBALS['c']['options'][ $name ] : $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['c']['options'][ $name ] = $value; return true; }
	function wp_cache_delete() { return true; }
	function wp_next_scheduled( $hook ) { return isset( $GLOBALS['c']['scheduled'][ $hook ] ) ? 1 : false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['c']['scheduled'][ $hook ] = ( $GLOBALS['c']['scheduled'][ $hook ] ?? 0 ) + 1; return true; }
	function get_current_user_id() { return $GLOBALS['c']['user']; }
	function wp_set_current_user( $id ) { $GLOBALS['c']['user'] = (int) $id; }
	function user_can( $user, $cap ) { return ! empty( $GLOBALS['c']['caps'][ (int) $user ] ); }
	function current_user_can( $cap ) { return user_can( $GLOBALS['c']['user'], $cap ); }
	function wp_generate_password( $length = 12, $special = true ) { return substr( md5( (string) mt_rand() ), 0, $length ); }
	function get_current_blog_id() { return 1; }
	function __( $text ) { return $text; }
	function wp_kses_post( $html ) { return $html; }
	function get_bloginfo( $what ) { return 'name' === $what ? 'Sitio' : 'Lema'; }
	function has_custom_logo() { return false; }
	function get_nav_menu_locations() { return array(); }
	function wp_get_nav_menu_object( $id ) { return false; }
	function wp_get_nav_menu_items( $id ) { return array(); }
	function get_post( $id ) { return $GLOBALS['c']['posts'][ (int) $id ] ?? null; }
	function get_posts( array $args ) { return array(); }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['c']['meta'][ (int) $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['c']['meta'][ (int) $id ][ $key ] = $value; return true; }
	class Fake_Conditions { public function save_conditions( $id, $conditions ) { update_post_meta( $id, '_elementor_conditions', $conditions ); } public function get_cache() { return $this; } public function regenerate() {} }
	class Fake_Document { public $id; public function __construct( $id ) { $this->id = $id; } public function get_main_id() { return $this->id; } public function save( $data ) { if ( isset( $data['elements'] ) ) { update_post_meta( $this->id, '_elementor_data', json_encode( $data['elements'] ) ); } return true; } }
	class Fake_Documents { public function create( $type, $post = array(), $meta = array() ) { $id = $GLOBALS['c']['next']++; $GLOBALS['c']['created']++; $GLOBALS['c']['posts'][ $id ] = (object) array( 'ID' => $id, 'post_status' => 'publish', 'post_title' => $post['post_title'] ); foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); } return new Fake_Document( $id ); } }
	class Fake_Widgets { public function get_widget_types( $type ) { return new stdClass(); } }
	class Fake_Files { public function clear_cache() { $GLOBALS['c']['cleared']++; } }
	/** Tema: carpeta propia, padre (Template) y cabecera Update URI según la carpeta. */
	class WP_Theme {
		private $template; private $stylesheet;
		public function __construct( $template, $stylesheet = null ) { $this->template = $template; $this->stylesheet = null === $stylesheet ? $template : $stylesheet; }
		public function get_template() { return $this->template; }
		public function get_stylesheet() { return $this->stylesheet; }
		public function get( $header ) { return 'UpdateURI' === $header ? ( $GLOBALS['c']['uris'][ $this->stylesheet ] ?? '' ) : ''; }
	}
	function wp_get_theme( $stylesheet = '' ) { return new WP_Theme( (string) $stylesheet ); }
	function wp_trash_post( $id ) { $GLOBALS['c']['posts'][ (int) $id ]->post_status = 'trash'; return true; }
	function wp_update_post( array $data ) { $GLOBALS['c']['posts'][ (int) $data['ID'] ]->post_status = $data['post_status']; return (int) $data['ID']; }
	class Fake_Wpdb {
		public $options = 'wp_options';
		public $rows = array();
		public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
		public function esc_like( $text ) { return $text; }
		public function get_var( $query ) { return $this->rows[ $query[1][0] ] ?? null; }
		public function query( $query ) {
			list( $sql, $args ) = $query;
			if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) { if ( isset( $this->rows[ $args[0] ] ) ) { return 0; } $this->rows[ $args[0] ] = $args[1]; return 1; }
			if ( 0 === strpos( $sql, 'DELETE' ) ) { unset( $this->rows[ $args[0] ] ); return 1; }
			return 0;
		}
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->documents = new Fake_Documents();
	\Elementor\Plugin::$instance->widgets_manager = new Fake_Widgets();
	\Elementor\Plugin::$instance->files_manager = new Fake_Files();

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-theme/class-module.php';
	use Digitalisimo\Elements\Theme_Compat as TC;

	TC::init();
	$hooked = static function ( $hook, $method, $priority ) {
		foreach ( $GLOBALS['c']['hooks'] as $h ) {
			if ( $hook === $h[0] && array( TC::class, $method ) === $h[1] && $priority === $h[2] ) { return true; }
		}
		return false;
	};
	check_tc( $hooked( 'elementor/onboarding/is_elementor_theme_active', 'onboarding', 10 ) && $hooked( 'admin_init', 'block_hello_activation', 9 ) && $hooked( 'rest_pre_dispatch', 'block_hello_rest', 10 ) && $hooked( 'digitalisimo_theme_switched', 'switched', 10 ) && $hooked( 'digitalisimo_theme_reverted', 'reverted', 10 ) && $hooked( 'digitalisimo_theme_convert_hello', 'convert', 10 ) && $hooked( 'admin_init', 'retry_pending', 20 ), 'Ganchos y prioridades de Theme_Compat.' );
	check_tc( ! class_exists( 'Digitalisimo\\Elements\\Hello_Header_Footer', false ), 'La conversión sólo se carga cuando hace falta.' );

	// 1. Onboarding: con el tema, true; en cualquier otro caso, el valor recibido.
	check_tc( false === TC::onboarding( false ) && true === TC::onboarding( true ) && 'x' === TC::onboarding( 'x' ) && 0 === TC::onboarding( 0 ), 'Sin el tema se devuelve el valor recibido.' );
	$GLOBALS['c']['template'] = 'digitalisimo-theme';
	check_tc( true === TC::onboarding( false ) && true === TC::onboarding( 0 ), 'Con el tema activo el asistente lo da por bueno.' );

	// 2. Bloqueos: sólo con el tema activo y en la acción o ruta exacta.
	$block = static function () {
		try {
			TC::block_hello_activation();
		} catch ( Json_Sent $e ) {
			return end( $GLOBALS['c']['json'] );
		}
		return null;
	};
	$_POST = array( 'action' => 'elementor_activate_hello_theme' );
	check_tc( null === $block(), 'Fuera de AJAX no se bloquea nada.' );
	$GLOBALS['c']['ajax'] = true;
	$sent = $block();
	check_tc( is_array( $sent ) && 403 === $sent[1] && false !== strpos( $sent[0]['message'], 'Apariencia' ), 'Con el tema activo la activación de Hello por AJAX responde 403.' );
	$_POST = array( 'action' => 'elementor_update_site_name' );
	check_tc( null === $block(), 'Las demás acciones del asistente siguen.' );
	$_POST = array( 'action' => 'elementor_activate_hello_theme' );
	$GLOBALS['c']['template'] = 'hello-elementor';
	check_tc( null === $block(), 'Sin el tema propio activo no se bloquea.' );
	$GLOBALS['c']['template'] = 'digitalisimo-theme';
	$GLOBALS['c']['ajax'] = false;
	$_POST = array();

	$rest = TC::block_hello_rest( null, null, new Fake_Request( 'POST', '/elementor/v1/onboarding/install-theme' ) );
	check_tc( is_wp_error( $rest ) && 'digitalisimo_theme_active' === $rest->code && 403 === $rest->data['status'], 'Con el tema activo la ruta REST de instalar tema responde 403.' );
	check_tc( is_wp_error( TC::block_hello_rest( null, null, new Fake_Request( 'POST', '/elementor/v1/onboarding/install-theme/' ) ) ), 'También con la barra final.' );
	foreach ( array( array( 'GET', '/elementor/v1/onboarding/install-theme' ), array( 'POST', '/elementor/v1/onboarding/install-pro' ), array( 'POST', '/elementor/v1/onboarding/install-theme/x' ), array( 'POST', '/otro/elementor/v1/onboarding/install-theme' ), array( 'POST', '/wp/v2/themes' ) ) as $case ) {
		check_tc( null === TC::block_hello_rest( null, null, new Fake_Request( $case[0], $case[1] ) ), 'Sólo la ruta exacta por POST: ' . implode( ' ', $case ) );
	}
	$previous = array( 'ya resuelto' );
	check_tc( $previous === TC::block_hello_rest( $previous, null, new Fake_Request( 'POST', '/elementor/v1/onboarding/install-theme' ) ), 'Una respuesta ya resuelta no se toca.' );
	$GLOBALS['c']['template'] = 'hello-elementor';
	check_tc( null === TC::block_hello_rest( null, null, new Fake_Request( 'POST', '/elementor/v1/onboarding/install-theme' ) ), 'Sin el tema propio activo la ruta sigue.' );

	// 3. Contexto del aviso de cambio de tema.
	$record = array(
		'v' => 1, 'user' => 1,
		'origin' => array( 'family' => 'hello', 'version' => '3.5.1' ),
		'header_footer' => array( 'header' => 'dynamic', 'footer' => 'classic', 'pending' => true, 'blocked' => '' ),
	);
	$GLOBALS['c']['options']['digitalisimo_theme_switch'] = $record;
	$GLOBALS['c']['template'] = 'digitalisimo-theme';
	// Front: se programa el evento una sola vez y la tarea sigue pendiente.
	TC::switched( $record, null );
	TC::switched( $record, null );
	check_tc( array( 'digitalisimo_theme_convert_hello' => 1 ) === $GLOBALS['c']['scheduled'] && 0 === $GLOBALS['c']['created'] && true === get_option( 'digitalisimo_theme_switch' )['header_footer']['pending'], 'En el front se programa la conversión una sola vez y queda pendiente.' );
	// convert() sin el tema activo no hace nada.
	$GLOBALS['c']['template'] = 'hello-elementor';
	check_tc( false === TC::convert() && 0 === $GLOBALS['c']['created'], 'Sin el tema activo la conversión no hace nada.' );
	$GLOBALS['c']['template'] = 'digitalisimo-theme';
	// Sin un usuario válido (cron con un usuario que ya no puede editar el tema), queda pendiente.
	$GLOBALS['c']['cron'] = true;
	$GLOBALS['c']['caps'][1] = false;
	check_tc( false === TC::convert() && 0 === $GLOBALS['c']['created'] && true === get_option( 'digitalisimo_theme_switch' )['header_footer']['pending'], 'Sin usuario con permisos la tarea queda pendiente.' );
	$GLOBALS['c']['caps'][1] = true;
	$GLOBALS['c']['cron'] = false;
	// retry_pending: en la administración, con permisos, la tarea pendiente se completa.
	$GLOBALS['c']['admin'] = true;
	$GLOBALS['c']['user'] = 1;
	$GLOBALS['c']['ajax'] = true;
	TC::retry_pending();
	check_tc( 0 === $GLOBALS['c']['created'], 'En peticiones AJAX no se reintenta.' );
	$GLOBALS['c']['ajax'] = false;
	$GLOBALS['c']['options']['digitalisimo_theme_switch']['header_footer']['blocked'] = 'widget:nav-menu';
	TC::retry_pending();
	check_tc( 0 === $GLOBALS['c']['created'], 'Una conversión bloqueada no se reintenta.' );
	$GLOBALS['c']['options']['digitalisimo_theme_switch']['header_footer']['blocked'] = '';
	TC::retry_pending();
	$after = get_option( 'digitalisimo_theme_switch' );
	check_tc( 1 === $GLOBALS['c']['created'] && false === $after['header_footer']['pending'] && isset( $after['generated']['header'] ) && ! isset( $after['generated']['footer'] ), 'En la administración se convierte la parte dinámica (sólo esa).' );
	// Administración: el aviso convierte en la misma petición.
	$GLOBALS['c']['options']['digitalisimo_theme_switch'] = array( 'header_footer' => array( 'header' => 'classic', 'footer' => 'dynamic', 'pending' => true ) ) + $record;
	$GLOBALS['c']['scheduled'] = array();
	TC::switched( get_option( 'digitalisimo_theme_switch' ), null );
	check_tc( 2 === $GLOBALS['c']['created'] && array() === $GLOBALS['c']['scheduled'] && false === get_option( 'digitalisimo_theme_switch' )['header_footer']['pending'], 'En la administración el aviso convierte en la misma petición, sin cron.' );
	// Un usuario sin permisos en la administración no convierte.
	$GLOBALS['c']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	$GLOBALS['c']['user'] = 3;
	TC::retry_pending();
	check_tc( 2 === $GLOBALS['c']['created'], 'Sin permiso para editar el tema no se reintenta.' );

	// La petición que cambia el tema es de un administrador, pero el tema se registra en la siguiente (un visitante): el
	// registro llega sin usuario y el cron convierte con quien activó el tema (S3c).
	$GLOBALS['c']['user'] = 1;
	TC::remember_switcher( 'otro', new WP_Theme( 'otro' ) );
	check_tc( ! isset( $GLOBALS['c']['options']['digitalisimo_theme_switch_user'] ), 'Cambiar a otro tema no anota a nadie.' );
	$GLOBALS['c']['user'] = 3;
	TC::remember_switcher( 'digitalisimo-theme', new WP_Theme( 'digitalisimo-theme' ) );
	check_tc( ! isset( $GLOBALS['c']['options']['digitalisimo_theme_switch_user'] ), 'Un usuario sin permisos no se anota.' );
	$GLOBALS['c']['user'] = 1;
	TC::remember_switcher( 'digitalisimo-theme', new WP_Theme( 'digitalisimo-theme' ) );
	check_tc( 1 === get_option( 'digitalisimo_theme_switch_user' ), 'Se anota al administrador que activa el tema.' );
	$GLOBALS['c']['user'] = 0;
	$GLOBALS['c']['cron'] = true;
	$GLOBALS['c']['posts'] = array();
	$GLOBALS['c']['meta'] = array();
	$GLOBALS['c']['created'] = 0;
	$GLOBALS['c']['options']['digitalisimo_theme_switch'] = array( 'user' => 0, 'header_footer' => array( 'header' => 'dynamic', 'footer' => 'classic', 'pending' => true, 'blocked' => '' ) ) + $record;
	TC::convert();
	check_tc( 1 === $GLOBALS['c']['created'] && false === get_option( 'digitalisimo_theme_switch' )['header_footer']['pending'], 'Sin usuario en el registro, el cron convierte con quien activó el tema.' );
	$GLOBALS['c']['cron'] = false;

	// 4. Carpeta renombrada (theme-network.sh activa digitalisimo-theme-1.0.0 en un subsitio): el tema se reconoce por
	// su Update URI, así que el asistente, los bloqueos, la conversión pendiente y quien activó el tema siguen igual.
	check_tc( $hooked( 'switch_theme', 'remember_switcher', 10 ) && $hooked( 'switch_theme', 'left', 10 ), 'switch_theme: quién activa el tema y retirada al dejarlo.' );
	foreach ( $GLOBALS['c']['hooks'] as $h ) {
		if ( 'switch_theme' === $h[0] && array( TC::class, 'left' ) === $h[1] ) { check_tc( 3 === $h[3], 'left() recibe los tres argumentos de switch_theme.' ); }
	}
	$GLOBALS['c']['template'] = 'digitalisimo-theme-1.0.0';
	check_tc( TC::active() && true === TC::onboarding( false ), 'Con la copia renombrada activa, el asistente la da por buena.' );
	check_tc( is_wp_error( TC::block_hello_rest( null, null, new Fake_Request( 'POST', '/elementor/v1/onboarding/install-theme' ) ) ), 'Con la copia renombrada activa, la ruta REST que instala Hello responde 403.' );
	$GLOBALS['c']['ajax'] = true;
	$_POST = array( 'action' => 'elementor_activate_hello_theme' );
	$sent  = $block();
	check_tc( is_array( $sent ) && 403 === $sent[1], 'Con la copia renombrada activa, la activación de Hello por AJAX responde 403.' );
	$_POST = array();
	$GLOBALS['c']['ajax'] = false;
	$GLOBALS['c']['template'] = 'otro';
	check_tc( ! TC::active() && false === TC::onboarding( false ), 'Un tema con otro Update URI no es el tema DIGITALÍSIMO.' );
	$GLOBALS['c']['template'] = 'sin-uri';
	check_tc( ! TC::active(), 'Un tema sin Update URI tampoco.' );
	check_tc( TC::is_theme( new WP_Theme( 'digitalisimo-theme-1.0.0', 'hijo-renombrado' ) ) && TC::is_theme( new WP_Theme( 'digitalisimo-theme-1.0.0' ) ) && ! TC::is_theme( new WP_Theme( 'otro' ) ) && ! TC::is_theme( null ), 'is_theme(): la copia renombrada y su hijo sí; otro tema o nada, no.' );
	unset( $GLOBALS['c']['options']['digitalisimo_theme_switch_user'] );
	$GLOBALS['c']['user'] = 1;
	TC::remember_switcher( 'Copia', new WP_Theme( 'digitalisimo-theme-1.0.0' ) );
	check_tc( 1 === get_option( 'digitalisimo_theme_switch_user' ), 'Activar la copia renombrada anota a quien la activa.' );
	// La conversión pendiente se completa en la administración también con la copia renombrada.
	$GLOBALS['c']['template'] = 'digitalisimo-theme-1.0.0';
	$GLOBALS['c']['admin']    = true;
	$GLOBALS['c']['posts']    = array();
	$GLOBALS['c']['meta']     = array();
	$GLOBALS['c']['created']  = 0;
	$GLOBALS['c']['options']['digitalisimo_theme_switch'] = array( 'user' => 1, 'header_footer' => array( 'header' => 'dynamic', 'footer' => 'classic', 'pending' => true, 'blocked' => '' ) ) + $record;
	$cleared = $GLOBALS['c']['cleared'];
	TC::retry_pending();
	$after = get_option( 'digitalisimo_theme_switch' );
	check_tc( 1 === $GLOBALS['c']['created'] && false === $after['header_footer']['pending'] && isset( $after['generated']['header'] ), 'Con la copia renombrada, la conversión pendiente se completa en la administración.' );
	check_tc( $GLOBALS['c']['cleared'] > $cleared, 'Tras crear la plantilla se limpia la caché del CSS (T8 al final de la conversión).' );
	$hid = (int) $after['generated']['header']['id'];

	// 5. Dejar el tema desde Apariencia (switch_theme, sin la reversión de WP-CLI): la cabecera generada se retira y el
	// registro queda en reverted; repetirlo no hace nada; pasar a un hijo del propio tema no es dejarlo.
	$GLOBALS['c']['template'] = 'hello-elementor'; // switch_theme() ya cambió las opciones cuando avisa.
	TC::left( 'Hello', new WP_Theme( 'hello-elementor' ), new WP_Theme( 'digitalisimo-theme-1.0.0', 'hijo-renombrado' ) );
	$after = get_option( 'digitalisimo_theme_switch' );
	check_tc( 'trash' === get_post( $hid )->post_status && 'trash' === $after['generated']['header']['retired'], 'Al dejar el tema (un hijo de la copia renombrada) la cabecera generada sin cambios va a la papelera.' );
	check_tc( 'reverted' === $after['state'] && $after['reverted'] > 0 && 'hello' === $after['origin']['family'], 'El registro queda en reverted y conserva el origen.' );
	$stamp = $after['reverted'];
	$GLOBALS['c']['posts'][ $hid ]->post_status = 'publish';
	TC::left( 'Hello', new WP_Theme( 'hello-elementor' ), new WP_Theme( 'digitalisimo-theme' ) );
	check_tc( 'publish' === get_post( $hid )->post_status && $stamp === get_option( 'digitalisimo_theme_switch' )['reverted'], 'Con el registro ya en reverted (la reversión de WP-CLI lo marca antes de cambiar), no se repite.' );
	unset( $GLOBALS['c']['options']['digitalisimo_theme_switch']['reverted'] );
	$GLOBALS['c']['options']['digitalisimo_theme_switch']['state'] = 'done';
	TC::left( 'Hijo', new WP_Theme( 'digitalisimo-theme', 'hijo' ), new WP_Theme( 'digitalisimo-theme' ) );
	TC::left( 'Otro', new WP_Theme( 'otro' ), new WP_Theme( 'hello-elementor' ) );
	TC::left( 'Otro', null, null );
	check_tc( 'publish' === get_post( $hid )->post_status && 'done' === get_option( 'digitalisimo_theme_switch' )['state'], 'Pasar a un hijo del propio tema, o cambiar entre otros temas, no retira nada.' );
	$GLOBALS['c']['meta'][ $hid ]['_elementor_data'] .= ' ';
	TC::left( 'Otro', new WP_Theme( 'otro' ), new WP_Theme( 'digitalisimo-theme' ) );
	check_tc( 'draft' === get_post( $hid )->post_status && 'draft' === get_option( 'digitalisimo_theme_switch' )['generated']['header']['retired'], 'Una plantilla generada que se editó pasa a borrador (nunca se borra), como al revertir.' );
	unset( $GLOBALS['c']['options']['digitalisimo_theme_switch'] );
	TC::left( 'Otro', new WP_Theme( 'otro' ), new WP_Theme( 'digitalisimo-theme' ) );
	check_tc( ! isset( $GLOBALS['c']['options']['digitalisimo_theme_switch'] ), 'Sin registro del tema no se escribe nada.' );
	$GLOBALS['c']['admin'] = false;
	$GLOBALS['wpdb']->rows = array();

	// 6. Estática: pro-elements.php carga el módulo después de las condiciones de visualización.
	$loader = file_get_contents( __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	$a      = strpos( $loader, '\\Digitalisimo\\Elements\\Display_Conditions::init();' );
	$b      = strpos( $loader, "require_once __DIR__ . '/modules/digitalisimo-theme/class-module.php';" );
	$d      = strpos( $loader, '\\Digitalisimo\\Elements\\Theme_Compat::init();' );
	check_tc( false !== $a && false !== $b && false !== $d && $a < $b && $b < $d && 1 === substr_count( $loader, 'Theme_Compat::init()' ), 'pro-elements.php debe cargar Theme_Compat (una vez) después de Display_Conditions.' );
	$source = file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-theme/class-module.php' ) . file_get_contents( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-theme/class-hello-header-footer.php' );
	foreach ( token_get_all( $source ) as $i => $token ) {
		if ( is_array( $token ) && T_STRING === $token[0] && in_array( strtolower( $token[1] ), array( 'switch_theme', 'delete_theme', 'add_option' ), true ) ) {
			throw new RuntimeException( 'El módulo no puede llamar a ' . $token[1] . '().' );
		}
	}

	echo "DIGITALÍSIMO Elements: convivencia con el tema DIGITALÍSIMO validada (asistente, bloqueos y contexto).\n";
}
