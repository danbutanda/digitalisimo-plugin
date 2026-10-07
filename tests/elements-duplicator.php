<?php
define( 'ABSPATH', __DIR__ );
class WP_Post {
	public $ID, $post_type, $post_status, $post_title, $post_content, $post_content_filtered, $post_excerpt, $post_parent, $post_password, $comment_status, $ping_status, $menu_order;
	public function __construct( $id, $type = 'post' ) {
		$this->ID = $id;
		$this->post_type = $type;
		$this->post_status = 'publish';
		$this->post_title = 'Artículo original';
		$this->post_content = 'Contenido';
		$this->post_content_filtered = '';
		$this->post_excerpt = 'Resumen';
		$this->post_parent = 0;
		$this->post_password = '';
		$this->comment_status = 'closed';
		$this->ping_status = 'closed';
		$this->menu_order = 0;
	}
}
class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
$GLOBALS['digi_hooks'] = array();
$GLOBALS['digi_can_create'] = true;
$GLOBALS['digi_meta_error'] = false;
$GLOBALS['digi_deleted'] = array();
$GLOBALS['digi_saved_meta'] = array();
$GLOBALS['digi_terms'] = array();
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['digi_hooks'][] = $name; }
function add_action( $name, $callback ) { $GLOBALS['digi_hooks'][] = $name; }
function is_admin() { return true; }
function get_post_types( $args, $output ) { return array( 'post' => 'post', 'page' => 'page', 'product' => 'product', 'attachment' => 'attachment' ); }
function get_post_type_object( $type ) {
	if ( ! in_array( $type, array( 'post', 'page', 'product', 'elementor_library' ), true ) ) return null;
	return (object) array( 'show_ui' => true, 'cap' => (object) array( 'create_posts' => 'create_' . $type ) );
}
function apply_filters( $name, $value ) { return $value; }
function current_user_can( $cap, $id = 0 ) { return 'edit_post' === $cap || $GLOBALS['digi_can_create']; }
function admin_url( $path ) { return 'https://site.example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function get_current_blog_id() { return 17; }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=' . rawurlencode( $action ); }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
function get_current_user_id() { return 7; }
function wp_insert_post( $args, $return_error ) { $GLOBALS['digi_inserted'] = $args; return 91; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_object_taxonomies( $type ) { return array( 'category', 'post_tag' ); }
function wp_get_object_terms( $id, $taxonomy, $args ) { return 'category' === $taxonomy ? array( 2, 4 ) : array(); }
function wp_set_object_terms( $id, $terms, $taxonomy, $append ) { $GLOBALS['digi_terms'][ $taxonomy ] = $terms; return $terms; }
function get_post_meta( $id ) { return array( '_elementor_data' => array( '[{"title":"\"Hola\""}]' ), '_thumbnail_id' => array( '23' ), '_edit_lock' => array( 'old-lock' ), '_elementor_css' => array( 'stale-css' ), 'digitalisimo_seo_canonical' => array( 'https://site.example.test/original/' ) ); }
function maybe_unserialize( $value ) { return $value; }
function wp_slash( $value ) { return addslashes( $value ); }
function add_post_meta( $id, $key, $value ) {
	if ( $GLOBALS['digi_meta_error'] && '_thumbnail_id' === $key ) return false;
	$GLOBALS['digi_saved_meta'][ $key ][] = $value;
	return true;
}
function wp_delete_post( $id, $force ) { $GLOBALS['digi_deleted'][] = $id; }
function check_duplicator( $value, $message ) { if ( ! $value ) throw new RuntimeException( $message ); }
require __DIR__ . '/../digitalisimo-elements/digitalisimo-duplicator.php';
Digitalisimo_Elements_Duplicator::init();
check_duplicator( in_array( 'post_row_actions', $GLOBALS['digi_hooks'], true ) && in_array( 'page_row_actions', $GLOBALS['digi_hooks'], true ) && in_array( 'admin_post_digitalisimo_elements_duplicate_post', $GLOBALS['digi_hooks'], true ), 'Faltan acciones de la lista o el handler propio.' );
$post = new WP_Post( 12 );
$actions = Digitalisimo_Elements_Duplicator::row_action( array( 'edit' => 'Editar' ), $post );
check_duplicator( isset( $actions['digitalisimo_elements_duplicate'] ) && str_contains( $actions['digitalisimo_elements_duplicate'], 'site.example.test/wp-admin/admin-post.php' ) && str_contains( $actions['digitalisimo_elements_duplicate'], '17_12' ), 'El enlace debe usar el sitio actual y un nonce ligado al blog y al post.' );
check_duplicator( ! isset( Digitalisimo_Elements_Duplicator::row_action( array(), new WP_Post( 13, 'product' ) )['digitalisimo_elements_duplicate'] ), 'Un producto no debe duplicarse con este flujo editorial.' );
$new = Digitalisimo_Elements_Duplicator::duplicate( $post );
check_duplicator( 91 === $new && 'draft' === $GLOBALS['digi_inserted']['post_status'] && 'Artículo original (copia)' === $GLOBALS['digi_inserted']['post_title'] && ! isset( $GLOBALS['digi_inserted']['guid'], $GLOBALS['digi_inserted']['post_name'] ), 'Debe crear un borrador nuevo con URL propia.' );
check_duplicator( array( 2, 4 ) === $GLOBALS['digi_terms']['category'] && array() === $GLOBALS['digi_terms']['post_tag'], 'Las taxonomías deben copiarse por ID.' );
check_duplicator( stripslashes( $GLOBALS['digi_saved_meta']['_elementor_data'][0] ) === '[{"title":"\"Hola\""}]' && ! isset( $GLOBALS['digi_saved_meta']['_edit_lock'], $GLOBALS['digi_saved_meta']['_elementor_css'], $GLOBALS['digi_saved_meta']['digitalisimo_seo_canonical'] ), 'Los datos de Elementor deben conservarse sin copiar cachés, bloqueos ni la canonical original.' );
$GLOBALS['digi_can_create'] = false;
check_duplicator( is_wp_error( Digitalisimo_Elements_Duplicator::duplicate( $post ) ), 'Debe exigir permiso para crear contenido.' );
$GLOBALS['digi_can_create'] = true;
$GLOBALS['digi_meta_error'] = true;
check_duplicator( is_wp_error( Digitalisimo_Elements_Duplicator::duplicate( $post ) ) && in_array( 91, $GLOBALS['digi_deleted'], true ), 'Un error de copia debe eliminar el borrador parcial.' );
echo "DIGITALÍSIMO Elements: duplicación editorial validada.\n";
