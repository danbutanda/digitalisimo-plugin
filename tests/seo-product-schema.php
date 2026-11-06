<?php
/**
 * RM8: producto completo sin WordPress. MPN, ISBN (con Book) y estado en el nodo propio y en el de WooCommerce,
 * identificadores de la variación elegida sin heredar los del padre, marca fija y paridad sitio/red de las opciones.
 */
define( 'ABSPATH', __DIR__ );
class Digitalisimo_Integrations_SEO_Suite { const P = 'digitalisimo_seo_'; }
$GLOBALS['settings'] = array( 'enable_seo' => 1, 'seo_schema_enabled' => 1, 'seo_schema_unify' => 1 );
$GLOBALS['foreign']  = '';
class Digitalisimo_Integrations_Settings { public static function get( $key, $default = '' ) { return $GLOBALS['settings'][ $key ] ?? $default; } }
class Digitalisimo_Integrations_SEO_Resolver {
	public static function foreign_seo() { return $GLOBALS['foreign']; }
	public static function option( $key, $default = '' ) { return $GLOBALS['settings'][ $key ] ?? $default; }
}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) ); }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function wc_clean( $value ) { return is_string( $value ) ? trim( strip_tags( $value ) ) : $value; }
function wc_variation_attribute_name( $name ) { return 'attribute_' . sanitize_key( $name ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function taxonomy_exists( $taxonomy ) { return in_array( $taxonomy, array( 'product_brand', 'pa_marca' ), true ); }
function get_the_terms( $id, $taxonomy ) { return 'pa_marca' === $taxonomy && 1 === $id ? array( (object) array( 'name' => '<b>Acme</b>' ) ) : false; }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? null; }

/** Lo mínimo de WC_Product y WC_Product_Variation que usan Schema_Graph y la vista de WooCommerce. */
class Fake_Product {
	public $id, $type, $sku, $gtin, $price, $status, $attributes, $children;
	public function __construct( array $p ) { foreach ( $p as $k => $v ) $this->$k = $v; }
	public function get_id() { return $this->id; }
	public function is_type( $type ) { return $type === $this->type; }
	public function get_sku() { return (string) $this->sku; }
	public function get_global_unique_id() { return (string) $this->gtin; }
	public function get_price() { return (string) $this->price; }
	public function get_status() { return $this->status ?? 'publish'; }
	public function get_children() { return (array) $this->children; }
	public function get_variation_attributes() { return (array) $this->attributes; }
}

require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-vocabulary.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-rules.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-graph.php';
function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }
$G = 'Digitalisimo_Integrations_Schema_Graph';
$R = 'Digitalisimo_Integrations_Schema_Rules';

// --- Saneado: nada que no supere su formato se publica ---
check( '9780306406157' === $G::sanitize_isbn( '978-0-306-40615-7' ) && '080442957X' === $G::sanitize_isbn( '0-8044-2957-x' ), 'ISBN-13 e ISBN-10 válidos, sin guiones y con la X en mayúscula.' );
check( '' === $G::sanitize_isbn( '978-0-306-40615-8' ) && '' === $G::sanitize_isbn( '0-8044-2957-1' ) && '' === $G::sanitize_isbn( '1234567890123' ) && '' === $G::sanitize_isbn( 'abc' ), 'Un dígito de control equivocado o un prefijo que no es 978/979 no se acepta.' );
check( 'AB-123/4 x' === $G::sanitize_mpn( "  <b>AB-123/4</b>\t x " ) && '' === $G::sanitize_mpn( str_repeat( 'x', 71 ) ) && 70 === strlen( $G::sanitize_mpn( str_repeat( 'x', 70 ) ) ), 'El MPN se conserva tal cual, en una línea y con como mucho 70 caracteres.' );
check( '4006381333931' === $G::sanitize_gtin( '4006-3813-3393-1' ) && '' === $G::sanitize_gtin( '0123' ) && '' === $G::sanitize_gtin( '12345678901' ), 'El GTIN se valida como WooCommerce: 8, 12, 13 o 14 cifras.' );
check( 'UsedCondition' === $G::sanitize_condition( 'https://schema.org/UsedCondition' ) && '' === $G::sanitize_condition( 'MintCondition' ) && '' === $G::sanitize_condition( '' ), 'Sólo los estados de OfferItemCondition de la lista.' );
foreach ( array_keys( $G::CONDITIONS ) as $condition ) check( 'official' === Digitalisimo_Integrations_Schema_Vocabulary::status( $condition ), "$condition está en el vocabulario oficial" );
check( 'Acme' === $G::sanitize_brand_name( " <i>Acme</i>\n " ) && 100 === mb_strlen( $G::sanitize_brand_name( str_repeat( 'ñ', 150 ) ) ), 'La marca fija es una línea de texto de hasta 100 caracteres.' );

// --- Nodo propio ---
$site = array( 'home' => 'https://tienda.test/', 'name' => 'Tienda', 'org_name' => 'Tienda', 'org_type' => 'Organization', 'org_url' => '', 'logo' => '', 'image' => '', 'same_as' => array(), 'email' => '', 'language' => 'es-MX', 'local' => null );
$page = array( 'kind' => 'singular', 'url' => 'https://tienda.test/producto/libro/', 'title' => 'Libro', 'description' => 'Un libro.', 'breadcrumbs' => array(), 'image' => '' );
$find = function( $graph ) { foreach ( $graph as $node ) if ( in_array( 'Product', (array) $node['@type'], true ) ) return $node; return null; };
$bare = $find( $G::build( $site, array( 'entity' => array( 'type' => 'Product', 'sku' => 'L-1', 'offers' => array( 'price' => '10', 'priceCurrency' => 'MXN' ) ) ) + $page ) );
check( 'Product' === $bare['@type'] && ! array_intersect( array( 'mpn', 'isbn', 'gtin', 'itemCondition', 'inProductGroupWithID' ), array_keys( $bare ) ) && ! isset( $bare['offers']['itemCondition'] ), 'Sin datos declarados no se inventa ningún identificador ni estado.' );
$full = $find( $G::build( $site, array( 'entity' => array( 'type' => 'Product', 'sku' => 'L-1', 'gtin' => '9780306406157', 'mpn' => 'ED-7', 'isbn' => '978-0-306-40615-7', 'condition' => 'UsedCondition', 'brand' => 'Acme', 'offers' => array( 'price' => '10', 'priceCurrency' => 'MXN' ) ) ) + $page ) );
check( array( 'Product', 'Book' ) === $full['@type'] && $page['url'] . '#product' === $full['@id'], 'Con ISBN el producto también es Book (la propiedad isbn es de Book) y conserva su @id.' );
check( '9780306406157' === $full['isbn'] && 'ED-7' === $full['mpn'] && '9780306406157' === $full['gtin'] && 'Acme' === $full['brand']['name'], 'ISBN, MPN, GTIN y marca en el nodo propio.' );
check( 'https://schema.org/UsedCondition' === $full['itemCondition'] && 'https://schema.org/UsedCondition' === $full['offers']['itemCondition'], 'El estado va en el producto y en su oferta.' );
$report = $R::analyze_graph( array( '@context' => 'https://schema.org', '@graph' => $G::build( $site, array( 'entity' => array( 'type' => 'Product', 'mpn' => 'ED-7', 'isbn' => '9780306406157', 'condition' => 'NewCondition', 'offers' => array( 'price' => '10', 'priceCurrency' => 'MXN' ) ) ) + $page ) ) );
foreach ( $report['issues'] as $issue ) check( false === strpos( $issue['message'], 'no es una propiedad' ) && false === strpos( $issue['message'], 'no corresponde' ), 'El auditor acepta isbn, mpn e itemCondition: ' . $issue['message'] );

// --- Nodo de WooCommerce y unificación ---
$woo = array( '@type' => 'Product', '@id' => $page['url'] . '#product', 'name' => 'Libro', 'sku' => 'L-1', 'offers' => array( array( '@type' => 'AggregateOffer', 'lowPrice' => '8', 'highPrice' => '12', 'priceCurrency' => 'MXN' ) ) );
$extra = $G::product_extras( $woo, array( 'mpn' => 'ED-7', 'isbn' => 'malo', 'condition' => 'RefurbishedCondition' ) );
check( 'Product' === $extra['@type'] && ! isset( $extra['isbn'] ) && 'ED-7' === $extra['mpn'], 'Un ISBN inválido nunca llega al nodo ni cambia el tipo.' );
check( 'https://schema.org/RefurbishedCondition' === $extra['offers'][0]['itemCondition'] && 'https://schema.org/RefurbishedCondition' === $extra['itemCondition'], 'El estado pasa a cada oferta de la lista de WooCommerce.' );
$kept = $G::product_extras( array( 'mpn' => 'SUYO', 'itemCondition' => 'https://schema.org/NewCondition', 'offers' => array( '@type' => 'Offer', 'itemCondition' => 'https://schema.org/DamagedCondition' ) ), array( 'mpn' => 'ED-7', 'condition' => 'UsedCondition' ) );
check( 'SUYO' === $kept['mpn'] && 'https://schema.org/NewCondition' === $kept['itemCondition'] && 'https://schema.org/DamagedCondition' === $kept['offers']['itemCondition'], 'Lo que el nodo ya trae nunca se pisa.' );
$own_graph = $G::build( $site, array( 'entity' => array( 'type' => 'Product', 'isbn' => '9780306406157', 'condition' => 'NewCondition', 'offers' => array( 'price' => '10', 'priceCurrency' => 'MXN' ) ) ) + $page );
list( $merged ) = $G::merge( $own_graph, array( $woo ), $page );
$products = array_values( array_filter( $merged, function( $n ) { return in_array( 'Product', (array) $n['@type'], true ); } ) );
check( 1 === count( $products ) && array( 'Product', 'Book' ) === $products[0]['@type'], 'WooCommerce se funde con el Product+Book propio sin duplicarlo.' );
check( 'AggregateOffer' === $products[0]['offers'][0]['@type'] && 'https://schema.org/NewCondition' === $products[0]['offers'][0]['itemCondition'], 'La oferta de WooCommerce reemplaza la propia y recibe el estado declarado.' );
$other = array( '@type' => 'Product', 'name' => 'Libro', 'offers' => array( '@type' => 'Offer', 'price' => '9', 'priceCurrency' => 'MXN' ) );
list( $merged_same ) = $G::merge( $own_graph, array( $other ), $page );
check( 'https://schema.org/NewCondition' === $find( $merged_same )['offers']['itemCondition'], 'También con un Product sin @id que se funde por tipo.' );

// --- Variación elegida en la URL (reglas de WooCommerce) ---
$GLOBALS['products'] = array(
	11 => new Fake_Product( array( 'id' => 11, 'type' => 'variation', 'sku' => 'L-1-R', 'gtin' => '96385074', 'price' => '9', 'attributes' => array( 'attribute_pa_color' => 'rojo', 'attribute_tapa' => 'dura' ) ) ),
	12 => new Fake_Product( array( 'id' => 12, 'type' => 'variation', 'sku' => 'L-1', 'gtin' => '', 'price' => '9', 'attributes' => array( 'attribute_pa_color' => 'azul', 'attribute_tapa' => '' ) ) ),
	13 => new Fake_Product( array( 'id' => 13, 'type' => 'variation', 'sku' => 'L-1-V', 'price' => '9', 'status' => 'private', 'attributes' => array( 'attribute_pa_color' => 'verde', 'attribute_tapa' => 'dura' ) ) ),
	14 => new Fake_Product( array( 'id' => 14, 'type' => 'variation', 'sku' => 'L-1-N', 'price' => '', 'attributes' => array( 'attribute_pa_color' => 'negro', 'attribute_tapa' => 'dura' ) ) ),
	15 => new Fake_Product( array( 'id' => 15, 'type' => 'variation', 'sku' => 'L-1-A', 'price' => '9', 'attributes' => array( 'attribute_pa_color' => 'azul', 'attribute_tapa' => 'blanda' ) ) ),
);
$parent = new Fake_Product( array( 'id' => 10, 'type' => 'variable', 'sku' => 'L-1', 'gtin' => '4006381333931', 'attributes' => array( 'pa_color' => array(), 'Tapa' => array() ), 'children' => array( 11, 12, 13, 14, 15 ) ) );
$GLOBALS['meta'] = array( 10 => array( 'digitalisimo_seo_mpn' => 'PADRE-1', 'digitalisimo_seo_isbn' => '9780306406157', 'digitalisimo_seo_condition' => 'UsedCondition' ), 11 => array( 'digitalisimo_seo_mpn' => 'ROJO-1' ) );
$_GET = array( 'attribute_pa_color' => 'rojo', 'attribute_tapa' => 'dura' );
check( 11 === $G::selected_variation( $parent )->id, 'Todos los atributos y una sola coincidencia: esa variación.' );
$_GET = array( 'attribute_pa_color' => 'rojo' );
check( null === $G::selected_variation( $parent ), 'Falta un atributo: ninguna variación.' );
$_GET = array( 'attribute_pa_color' => 'azul', 'attribute_tapa' => 'blanda' );
check( null === $G::selected_variation( $parent ), 'Una variación «cualquiera» que también coincide hace ambigua la elección.' );
$_GET = array( 'attribute_pa_color' => 'verde', 'attribute_tapa' => 'dura' );
check( null === $G::selected_variation( $parent ), 'Una variación privada no cuenta.' );
$_GET = array( 'attribute_pa_color' => 'negro', 'attribute_tapa' => 'dura' );
check( null === $G::selected_variation( $parent ), 'Una variación sin precio no se elige.' );
$_GET = array( 'attribute_pa_color' => array( 'rojo' ), 'attribute_tapa' => 'dura' );
check( null === $G::selected_variation( $parent ), 'Un arreglo en la consulta no se acepta.' );
check( null === $G::selected_variation( $GLOBALS['products'][11] ), 'Un producto que no es variable no tiene variación elegida.' );

$_GET = array();
$data = $G::product_data( $parent );
check( 'L-1' === $data['sku'] && '4006381333931' === $data['gtin'] && 'PADRE-1' === $data['mpn'] && '9780306406157' === $data['isbn'] && 'UsedCondition' === $data['condition'] && ! isset( $data['group'] ), 'Sin variación elegida: los datos del producto.' );
$data = $G::product_data( $parent, $GLOBALS['products'][11] );
check( 'L-1-R' === $data['sku'] && '96385074' === $data['gtin'] && 'ROJO-1' === $data['mpn'] && '' === $data['isbn'] && 'L-1' === $data['group'] && 'UsedCondition' === $data['condition'], 'Con variación: sus identificadores, nunca el ISBN del padre; el estado es el del producto.' );
$data = $G::product_data( $parent, $GLOBALS['products'][12] );
check( '' === $data['gtin'] && '' === $data['mpn'], 'Una variación sin GTIN ni MPN no hereda los del producto.' );

// Estado: el del producto, «none» o el del sitio.
$GLOBALS['settings']['seo_product_condition'] = 'NewCondition';
$GLOBALS['meta'][20] = array( 'digitalisimo_seo_condition' => 'none' );
$GLOBALS['meta'][21] = array( 'digitalisimo_seo_condition' => 'Inventado' );
check( 'UsedCondition' === $G::product_condition( 10 ) && '' === $G::product_condition( 20 ) && 'NewCondition' === $G::product_condition( 21 ) && 'NewCondition' === $G::product_condition( 22 ), 'El estado del producto manda; «none» no publica nada y un valor desconocido usa el del sitio.' );
$GLOBALS['settings']['seo_product_condition'] = 'Inventado';
check( '' === $G::product_condition( 22 ), 'Un estado del sitio desconocido no se publica.' );

// Marcado de WooCommerce: lo que el sitio declaró, sólo si este plugin publica su schema.
$_GET = array( 'attribute_pa_color' => 'rojo', 'attribute_tapa' => 'dura' );
$woo_variation = $G::woo_markup( array( '@type' => 'Product', 'sku' => 'L-1-R', 'inProductGroupWithID' => 'L-1', 'offers' => array( array( '@type' => 'Offer', 'price' => '9' ) ) ), $parent );
check( 'ROJO-1' === $woo_variation['mpn'] && ! isset( $woo_variation['isbn'] ) && 'Product' === $woo_variation['@type'] && 'https://schema.org/UsedCondition' === $woo_variation['offers'][0]['itemCondition'], 'WooCommerce con variación elegida recibe el MPN de la variación y el estado.' );
$_GET = array( 'attribute_pa_color' => 'azul', 'attribute_tapa' => 'blanda' );
$woo_unknown = $G::woo_markup( array( '@type' => 'Product', 'inProductGroupWithID' => 'L-1' ), $parent );
check( ! isset( $woo_unknown['mpn'] ) && ! isset( $woo_unknown['isbn'] ), 'Si la variación de WooCommerce no se reconoce, nunca se publican los identificadores del padre.' );
$_GET = array();
$woo_parent = $G::woo_markup( array( '@type' => 'Product' ), $parent );
check( 'PADRE-1' === $woo_parent['mpn'] && array( 'Product', 'Book' ) === $woo_parent['@type'], 'Sin variación, los datos del producto (con Book por su ISBN).' );
$GLOBALS['foreign'] = 'Rank Math';
check( array( '@type' => 'Product' ) === $G::woo_markup( array( '@type' => 'Product' ), $parent ), 'Con otro plugin SEO activo no se añade nada.' );
$GLOBALS['foreign'] = '';
$GLOBALS['settings']['seo_schema_enabled'] = 0;
check( array( '@type' => 'Product' ) === $G::woo_markup( array( '@type' => 'Product' ), $parent ), 'Con el schema apagado no se añade nada.' );
$GLOBALS['settings']['seo_schema_enabled'] = 1;
check( 'texto' === $G::woo_markup( 'texto', $parent ), 'Un marcado que no es un arreglo pasa intacto.' );

// --- Marca ---
$GLOBALS['settings']['seo_product_brand'] = 'pa_marca';
check( 'Acme' === $G::product_brand( 1 ) && '' === $G::product_brand( 2 ), 'Marca desde la taxonomía elegida; sin marca asignada, ninguna.' );
$GLOBALS['settings']['seo_product_brand'] = $G::BRAND_FIXED;
$GLOBALS['settings']['seo_product_brand_name'] = ' Marca Propia ';
check( 'Marca Propia' === $G::product_brand( 2 ) && '' === $G::brand_taxonomy(), 'La marca fija se publica en todos los productos.' );
$GLOBALS['settings']['seo_product_brand_name'] = '';
check( '' === $G::product_brand( 1 ), 'Marca fija vacía: ninguna marca (falla cerrada, ni siquiera la de la taxonomía).' );
$GLOBALS['settings']['seo_product_brand'] = 'no_existe';
check( '' === $G::product_brand( 1 ), 'Una taxonomía que no existe no publica marca.' );

// --- Paridad sitio/red de las opciones nuevas (los cuatro puntos de AGENTS.md) ---
$settings = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-settings.php' );
$suite    = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php' );
foreach ( array( 'seo_product_brand_name' => 'sanitize_brand_name', 'seo_product_condition' => 'sanitize_condition' ) as $key => $method ) {
	check( 1 === preg_match( "/'$key'\\s+=> '',/", $settings ), "$key en defaults() con valor neutro" );
	check( false !== strpos( $settings, "\$output['$key'] = Digitalisimo_Integrations_Schema_Graph::$method(" ), "$key se sanea por sitio" );
	check( false !== strpos( $suite, "self::field( \$network, '$key'" ), "$key se pinta en el sitio y en la red" );
	check( false !== strpos( $suite, "if ( '$key' === \$key ) { \$current[ \$key ] = Digitalisimo_Integrations_Schema_Graph::$method( \$value ); continue; }" ), "$key se sanea en save_network()" );
}
check( false !== strpos( $suite, '$brands[ Digitalisimo_Integrations_Schema_Graph::BRAND_FIXED ]' ), 'La marca fija se puede elegir en el selector de marca.' );
check( 1 === preg_match( "/add_filter\\( 'woocommerce_structured_data_product', array\\( __CLASS__, 'woo_markup' \\)/", file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-graph.php' ) ), 'El marcado de WooCommerce se completa con un filtro, no con una acción.' );
$editor = file_get_contents( __DIR__ . '/../digitalisimo-seo/includes/class-product-identifiers.php' );
check( false !== strpos( $editor, "'woocommerce_admin_process_product_object'" ) && false !== strpos( $editor, "'woocommerce_admin_process_variation_object'" ) && false === strpos( $editor, 'update_post_meta' ), 'El editor guarda en el objeto de WooCommerce, dentro de su guardado (nonce y permisos de WooCommerce).' );

echo "Producto en el schema: MPN, ISBN, estado, variaciones, marca y paridad correctos.\n";
