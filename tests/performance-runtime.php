<?php
/** Pruebas de contexto y dependencias del módulo de rendimiento, sin WordPress real. */
define( 'ABSPATH', __DIR__ );
$state = array(
	'options' => array( 'perf_safe_mode' => 1, 'perf_gutenberg' => 1, 'perf_dashicons' => 0, 'perf_embeds' => 0, 'perf_font_swap' => 0 ),
	'admin' => false, 'preview' => false, 'feed' => false, 'ajax' => false, 'blocks' => false,
	'singular' => true, 'home' => false, 'theme' => 'hello-elementor', 'logged' => false,
	'content' => '', 'data' => '[]', 'dequeued' => array(),
);
class WP_Post { public $ID = 12; public $post_content = ''; }
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key ) { global $state; return $state['options'][ $key ] ?? 0; } }
function is_admin() { global $state; return $state['admin']; }
function wp_doing_ajax() { global $state; return $state['ajax']; }
function is_feed() { global $state; return $state['feed']; }
function is_preview() { global $state; return $state['preview']; }
function is_customize_preview() { return false; }
function current_user_can( $capability ) { global $state; return $state['admin']; }
function apply_filters( $hook, $value, ...$args ) { global $state; return isset( $state['filters'][ $hook ] ) ? $state['filters'][ $hook ]( $value, ...$args ) : $value; }
function is_singular( $type = '' ) { global $state; return $state['singular']; }
function is_home() { global $state; return $state['home']; }
function get_template() { global $state; return $state['theme']; }
class Fake_Theme { private $folder; public function __construct( $folder ) { $this->folder = $folder; } public function get( $header ) { return 'UpdateURI' === $header && 0 === strpos( $this->folder, 'digitalisimo-theme' ) ? 'https://github.com/danbutanda/digitalisimo-plugin/digitalisimo-theme' : ( 'UpdateURI' === $header && 'otro-tema' === $this->folder ? 'https://github.com/otro/tema' : '' ); } }
function wp_get_theme( $stylesheet = '' ) { return new Fake_Theme( (string) $stylesheet ); }
function get_queried_object() { global $state; $post = new WP_Post(); $post->post_content = $state['content']; return $post; }
function get_post_meta( $id, $key ) { global $state; return '_elementor_edit_mode' === $key ? 'builder' : $state['data']; }
function has_blocks( $post ) { global $state; return $state['blocks']; }
function has_shortcode( $content, $name ) { return false; }
function is_user_logged_in() { global $state; return $state['logged']; }
function is_admin_bar_showing() { return false; }
function wp_styles() { global $styles; return $styles; }
function wp_scripts() { global $scripts; return $scripts; }
function wp_style_is( $handle, $state_name ) { global $styles; return in_array( $handle, $styles->queue, true ); }
function wp_script_is( $handle, $state_name ) { global $scripts; return in_array( $handle, $scripts->queue, true ); }
function wp_dequeue_style( $handle ) { global $styles, $state; $styles->queue = array_values( array_diff( $styles->queue, array( $handle ) ) ); $state['dequeued'][] = $handle; }
function wp_dequeue_script( $handle ) { global $scripts, $state; $scripts->queue = array_values( array_diff( $scripts->queue, array( $handle ) ) ); $state['dequeued'][] = $handle; }
function add_action() {}
function add_filter() {}
function remove_action() {}
function remove_filter() {}

function registry( $dependent = false ) {
	$registry = new stdClass();
	$registry->registered = array(
		'wp-block-library' => (object) array( 'deps' => array() ),
		'wp-block-library-theme' => (object) array( 'deps' => array() ),
		'dashicons' => (object) array( 'deps' => array() ),
		'widget-css' => (object) array( 'deps' => $dependent ? array( 'wp-block-library' ) : array() ),
	);
	$registry->queue = array( 'wp-block-library', 'wp-block-library-theme', 'dashicons' );
	if ( $dependent ) $registry->queue[] = 'widget-css';
	return $registry;
}
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }

require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-manager.php';
$styles = registry();
$scripts = (object) array( 'registered' => array(), 'queue' => array() );
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( ! in_array( 'wp-block-library', $styles->queue, true ) && ! in_array( 'wp-block-library-theme', $styles->queue, true ), 'La página Elementor confirmada debe retirar CSS de bloques sin dependientes.' );
check( in_array( 'dashicons', $styles->queue, true ), 'Dashicons debe permanecer cuando la opción está apagada.' );

foreach ( array( 'admin', 'preview', 'feed', 'blocks', 'home' ) as $condition ) {
	$state[ $condition ] = true; $styles = registry();
	Digitalisimo_Integrations_Performance_Manager::clean_styles();
	check( in_array( 'wp-block-library', $styles->queue, true ), 'Gutenberg debe permanecer en contexto ' . $condition );
	$state[ $condition ] = false;
}
$state['theme'] = 'twentytwentyfive'; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( in_array( 'wp-block-library', $styles->queue, true ), 'Un tema no Hello conserva Gutenberg.' );
// El tema DIGITALÍSIMO (también como padre de un hijo: get_template() devuelve el padre) cede el contenido igual que Hello.
$state['theme'] = 'digitalisimo-theme'; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( ! in_array( 'wp-block-library', $styles->queue, true ) && ! in_array( 'wp-block-library-theme', $styles->queue, true ), 'El tema DIGITALÍSIMO debe retirar el CSS de bloques como Hello.' );
check( Digitalisimo_Integrations_Performance_Manager::builder_theme(), 'El tema DIGITALÍSIMO es un tema del constructor.' );
// Una copia en otra carpeta (digitalisimo-theme-1.0.0) se reconoce por su Update URI; otro tema con otra URI, no.
$state['theme'] = 'digitalisimo-theme-1.0.0'; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( Digitalisimo_Integrations_Performance_Manager::builder_theme() && ! in_array( 'wp-block-library', $styles->queue, true ), 'El tema DIGITALÍSIMO en una carpeta renombrada debe reconocerse por su Update URI.' );
$state['theme'] = 'otro-tema'; $styles = registry();
check( ! Digitalisimo_Integrations_Performance_Manager::builder_theme(), 'Un tema con otro Update URI no es el tema DIGITALÍSIMO.' );
// Un tercero puede reconocer su tema (recibe la plantilla) o excluir uno de los dos.
$seen = '';
$state['filters']['digitalisimo_performance_builder_theme'] = function( $value, $template ) use ( &$seen ) { $seen = $template; return $value || 'tema-propio' === $template; };
$state['theme'] = 'tema-propio'; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( 'tema-propio' === $seen && ! in_array( 'wp-block-library', $styles->queue, true ), 'El filtro digitalisimo_performance_builder_theme debe recibir la plantilla y poder aceptar otro tema.' );
$state['filters']['digitalisimo_performance_builder_theme'] = function( $value, $template ) { return 'digitalisimo-theme' === $template ? false : $value; };
$state['theme'] = 'digitalisimo-theme'; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( in_array( 'wp-block-library', $styles->queue, true ), 'El filtro también puede excluir un tema del constructor.' );
unset( $state['filters'] );
$state['theme'] = 'hello-elementor';
$state['data'] = '[{"widgetType":"shortcode"}]'; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( in_array( 'wp-block-library', $styles->queue, true ), 'Un widget shortcode conserva Gutenberg.' );
$state['data'] = '[]'; $styles = registry( true );
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( in_array( 'wp-block-library', $styles->queue, true ), 'Una dependencia encolada conserva su requisito.' );
check( ! in_array( 'wp-block-library-theme', $styles->queue, true ), 'El otro CSS de bloques puede retirarse de forma independiente.' );
$state['options']['perf_gutenberg'] = 0; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( in_array( 'wp-block-library', $styles->queue, true ), 'La opción apagada revierte la descarga.' );
$state['options']['perf_dashicons'] = 1; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( ! in_array( 'dashicons', $styles->queue, true ), 'Dashicons se retira sólo para invitados sin dependientes.' );
$state['logged'] = true; $styles = registry();
Digitalisimo_Integrations_Performance_Manager::clean_styles();
check( in_array( 'dashicons', $styles->queue, true ), 'Un usuario autenticado conserva Dashicons.' );
$state['logged'] = false;
check( ! Digitalisimo_Integrations_Performance_Manager::advanced_allowed(), 'Modo seguro bloquea reglas avanzadas.' );
$state['options']['perf_safe_mode'] = 0;
check( Digitalisimo_Integrations_Performance_Manager::advanced_allowed(), 'Desactivar modo seguro permite reglas avanzadas futuras.' );
check( 'block' === Digitalisimo_Integrations_Performance_Manager::custom_font_display( 'block' ), 'La opción apagada respeta font-display previo.' );
check( false === Digitalisimo_Integrations_Performance_Manager::google_font_display( false ), 'Google Fonts conserva la opción de Elementor al estar apagado.' );
$state['options']['perf_font_swap'] = 1;
check( 'swap' === Digitalisimo_Integrations_Performance_Manager::custom_font_display( 'block' ), 'La opción activa usa swap.' );
check( 'swap' === Digitalisimo_Integrations_Performance_Manager::google_font_display( false ), 'Google Fonts usa swap cuando se activa.' );
echo "Rendimiento: contextos, temas del constructor, dependencias, reversión y fuentes correctos.\n";
