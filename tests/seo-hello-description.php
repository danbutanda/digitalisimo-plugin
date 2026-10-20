<?php
/**
 * Una sola meta description: cuando SEO imprime la suya (wp_head, 1) retira la del tema Hello (wp_head, 10); si SEO
 * no imprime ninguna, o está apagado, la de Hello se queda. Además, el catálogo de plugins de SEO deja a otros
 * módulos añadir su sección con el contexto de la pantalla.
 */
define( 'ABSPATH', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/no-existe-plugins' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['hooks']    = array();
$GLOBALS['removed']  = array();
$GLOBALS['settings'] = array();
$GLOBALS['meta']     = array();
$GLOBALS['network']  = false;
$GLOBALS['post']     = (object) array( 'ID' => 468, 'post_type' => 'page', 'post_title' => 'Inicio', 'post_name' => 'inicio', 'post_excerpt' => 'Extracto escrito.', 'post_content' => 'Contenido de la página.', 'post_author' => 0 );
$GLOBALS['tagline']  = 'Lema del sitio.';

class Digitalisimo_Integrations_Settings {
	public static function get( $key, $fallback = '' ) { return array_key_exists( $key, $GLOBALS['settings'] ) ? $GLOBALS['settings'][ $key ] : $fallback; }
	public static function tools_handles_site_options() { return true; }
}
class Digitalisimo_Integrations_SEO_Resolver { public static function post( $id, $key, $default = '' ) { return $default; } }

// Ganchos como WP_Hook: cada prioridad se lee al llegar a ella, así que lo retirado en una prioridad anterior ya no corre.
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; return true; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { return add_action( $hook, $callback, $priority, $args ); }
function remove_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['removed'][] = array( 'call' => array( $hook, $callback, $priority ), 'printed' => ob_get_level() ? (string) ob_get_contents() : '' );
	foreach ( $GLOBALS['hooks'][ $hook ][ $priority ] ?? array() as $index => $registered ) if ( $registered === $callback ) unset( $GLOBALS['hooks'][ $hook ][ $priority ][ $index ] );
	return true;
}
function do_action( $hook, ...$args ) {
	$priorities = array_keys( $GLOBALS['hooks'][ $hook ] ?? array() );
	sort( $priorities );
	foreach ( $priorities as $priority ) foreach ( $GLOBALS['hooks'][ $hook ][ $priority ] ?? array() as $callback ) call_user_func_array( $callback, $args );
}
function has_hello() { foreach ( $GLOBALS['hooks']['wp_head'] ?? array() as $callbacks ) if ( in_array( 'hello_elementor_add_description_meta_tag', $callbacks, true ) ) return true; return false; }
function add_shortcode() {}
function is_multisite() { return false; }
function is_admin() { return false; }
function is_feed() { return false; }
function is_paged() { return false; }
function is_singular() { return true; }
function is_network_admin() { return $GLOBALS['network']; }
function is_wp_error( $value ) { return false; }
function get_queried_object() { return $GLOBALS['post']; }
function get_queried_object_id() { return $GLOBALS['post']->ID; }
function get_post( $id ) { return $GLOBALS['post']; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function get_userdata( $id ) { return false; }
function get_the_title( $id ) { return $GLOBALS['post']->post_title; }
function get_permalink( $id ) { return 'https://ejemplo.test/'; }
function get_bloginfo( $key ) { return 'description' === $key ? $GLOBALS['tagline'] : 'Sitio'; }
function get_query_var( $key ) { return ''; }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function strip_shortcodes( $value ) { return preg_replace( '/\[[^\]]+\]/', '', (string) $value ); }
function wp_trim_words( $value, $count, $more ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY ), 0, $count ) ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_url( $value ) { return (string) $value; }
function admin_url( $path = '' ) { return 'https://ejemplo.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url, $action ) { return $url; }
function current_user_can( $capability ) { return true; }
function get_site_transient( $key ) { return 'digitalisimo_plugin_catalog_releases' === $key ? array( 'digitalisimo-seo' => array( 'version' => '1.0.229', 'package' => 'https://github.com/danbutanda/digitalisimo-plugin/releases/download/v1/digitalisimo-seo-1.0.229.zip' ) ) : false; }

// Copia de la función de Hello 3.5.1 (functions.php:193-216), enganchada como la engancha el tema: sin prioridad, es decir, en 10.
function hello_elementor_add_description_meta_tag() {
	if ( ! is_singular() ) return;
	$post = get_queried_object();
	if ( empty( $post->post_excerpt ) ) return;
	echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
}

function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
function front_head() { ob_start(); do_action( 'wp_head' ); return (string) ob_get_clean(); }
function boot( $enable_seo ) {
	$GLOBALS['hooks'] = array(); $GLOBALS['removed'] = array();
	$GLOBALS['settings'] = array( 'enable_seo' => $enable_seo, 'seo_title_page' => '%title%', 'seo_description_page' => '%excerpt%' );
	Digitalisimo_Integrations_SEO_Suite::init();
	add_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );
}
function hello_removals() { return array_values( array_filter( $GLOBALS['removed'], function( $row ) { return 'hello_elementor_add_description_meta_tag' === $row['call'][1]; } ) ); }

require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-plugin-catalog.php';
$seo = Digitalisimo_Integrations_SEO_Suite::class;

// 1. El método retira exactamente la acción de Hello, en su prioridad.
$GLOBALS['removed'] = array();
$seo::release_theme_description();
check( 1 === count( $GLOBALS['removed'] ) && array( 'wp_head', 'hello_elementor_add_description_meta_tag', 10 ) === $GLOBALS['removed'][0]['call'], 'Debe retirarse exactamente (wp_head, hello_elementor_add_description_meta_tag, 10).' );

// 2. SEO imprime su descripción: una sola meta, la de SEO, y la retirada ocurre después de imprimirla.
boot( 1 );
check( in_array( array( $seo, 'head' ), $GLOBALS['hooks']['wp_head'][1] ?? array(), true ), 'SEO imprime sus metas en wp_head con prioridad 1.' );
$GLOBALS['meta'] = array( 'digitalisimo_seo_description' => 'Descripción SEO.' );
$html = front_head();
check( 1 === substr_count( $html, '<meta name="description"' ) && false !== strpos( $html, 'content="Descripción SEO."' ), 'Con SEO debe quedar una sola meta description, la de SEO: ' . $html );
$removals = hello_removals();
check( 1 === count( $removals ) && array( 'wp_head', 'hello_elementor_add_description_meta_tag', 10 ) === $removals[0]['call'], 'La descripción de Hello se retira una sola vez, en su prioridad.' );
check( false !== strpos( $removals[0]['printed'], '<meta name="description" content="Descripción SEO.">' ), 'La retirada ocurre después de imprimir la descripción de SEO.' );
check( ! has_hello(), 'La acción de Hello ya no queda enganchada.' );

// 3. Sin descripción de SEO (sin extracto, contenido ni lema), la acción de Hello se queda.
boot( 1 );
$GLOBALS['meta'] = array();
$saved = array( $GLOBALS['post']->post_excerpt, $GLOBALS['post']->post_content, $GLOBALS['tagline'] );
$GLOBALS['post']->post_excerpt = ''; $GLOBALS['post']->post_content = ''; $GLOBALS['tagline'] = '';
$html = front_head();
check( 0 === substr_count( $html, '<meta name="description"' ) && ! hello_removals() && has_hello(), 'Si SEO no imprime descripción, no retira la de Hello.' );
list( $GLOBALS['post']->post_excerpt, $GLOBALS['post']->post_content, $GLOBALS['tagline'] ) = $saved;

// 4. Con SEO apagado, Hello sigue siendo la única fuente.
boot( 0 );
$html = front_head();
check( 1 === substr_count( $html, '<meta name="description"' ) && false !== strpos( $html, 'content="Extracto escrito."' ) && ! hello_removals(), 'Sin SEO, la descripción de Hello se mantiene: ' . $html );

// 5. Estática de head(): la llamada va dentro del if de la descripción no vacía, después de su echo, y sólo una vez.
$tokens = token_get_all( file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php' ) );
$code = array_values( array_filter( $tokens, function( $token ) { return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ); } ) );
$text = function( $token ) { return is_array( $token ) ? $token[1] : $token; };
$block = function( $start ) use ( $code, $text ) {
	// Desde el token $start (una llave o un paréntesis de apertura) hasta su cierre.
	$open = $text( $code[ $start ] ); $close = '{' === $open ? '}' : ')'; $depth = 0;
	for ( $i = $start; $i < count( $code ); $i++ ) {
		$t = $text( $code[ $i ] );
		if ( $t === $open || ( '{' === $open && in_array( $t, array( '{', '${' ), true ) ) || ( is_array( $code[ $i ] ) && T_CURLY_OPEN === $code[ $i ][0] && '{' === $open ) ) $depth++;
		elseif ( $t === $close ) { $depth--; if ( 0 === $depth ) return $i; }
	}
	return -1;
};
$head_start = -1;
for ( $i = 0; $i < count( $code ) - 1; $i++ ) if ( is_array( $code[ $i ] ) && T_FUNCTION === $code[ $i ][0] && 'head' === $text( $code[ $i + 1 ] ) ) { $head_start = $i; break; }
check( $head_start > 0, 'No se encontró head().' );
$body_open = $head_start; while ( '{' !== $text( $code[ $body_open ] ) ) $body_open++;
$body_close = $block( $body_open );
$calls = array(); $found = false;
for ( $i = $body_open; $i < $body_close; $i++ ) {
	if ( 'release_theme_description' === $text( $code[ $i ] ) && '::' === $text( $code[ $i - 1 ] ) ) $calls[] = $i;
	if ( ! is_array( $code[ $i ] ) || T_IF !== $code[ $i ][0] ) continue;
	$cond_close = $block( $i + 1 );
	$condition = implode( '', array_map( $text, array_slice( $code, $i + 1, $cond_close - $i ) ) );
	if ( "(!empty(\$c['description']))" !== $condition ) continue;
	check( '{' === $text( $code[ $cond_close + 1 ] ), 'El if de la descripción debe abrir un bloque.' );
	$then_close = $block( $cond_close + 1 );
	$echo = -1; $call = -1;
	for ( $j = $cond_close + 1; $j < $then_close; $j++ ) {
		if ( is_array( $code[ $j ] ) && T_ECHO === $code[ $j ][0] && false !== strpos( $text( $code[ $j + 1 ] ), '<meta name="description"' ) ) $echo = $j;
		if ( 'release_theme_description' === $text( $code[ $j ] ) && 'self' === $text( $code[ $j - 2 ] ) && '(' === $text( $code[ $j + 1 ] ) ) $call = $j;
	}
	check( $echo > 0 && $call > $echo, 'release_theme_description() debe llamarse dentro del if de la descripción no vacía y después de su echo.' );
	$found = true;
}
check( $found, 'head() debe imprimir la descripción dentro de if ( ! empty( $c[\'description\'] ) ).' );
check( 1 === count( $calls ), 'head() llama a release_theme_description() una sola vez.' );

// 6. El catálogo de SEO dispara digitalisimo_plugin_catalog_after entre </table> y </div>, con el contexto.
$GLOBALS['hooks'] = array();
add_action( 'digitalisimo_plugin_catalog_after', function( $context ) { echo '<!--after:' . $context . '-->'; } );
foreach ( array( false => 'site', true => 'network' ) as $network => $context ) {
	$GLOBALS['network'] = (bool) $network;
	ob_start(); Digitalisimo_Plugin_Catalog::page(); $html = (string) ob_get_clean();
	check( 1 === substr_count( $html, '<!--after:' ) && '</tbody></table><!--after:' . $context . '--></div>' === substr( $html, -strlen( '</tbody></table><!--after:' . $context . '--></div>' ) ), 'El catálogo de SEO debe disparar la acción con el contexto «' . $context . '» entre </table> y </div>.' );
}

echo "SEO: una sola meta description (Hello se retira sólo si SEO imprime la suya) y acción del catálogo con su contexto.\n";
