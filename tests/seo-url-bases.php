<?php
/** RM12: quitar la base de una URL es exacto y nunca toca otra ruta. */
define( 'ABSPATH', __DIR__ );
function add_action() {} function add_filter() {}
class Digitalisimo_Integrations_Settings { const OPTION = 'x'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
require __DIR__ . '/../digitalisimo-seo/includes/class-url-bases.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$u = Digitalisimo_Integrations_URL_Bases::class;
check( 'https://sitio.test/tenis/' === $u::strip( 'https://sitio.test/product/tenis/', 'https://sitio.test/', 'product' ), 'Producto sin base.' );
check( 'https://sitio.test/sub/ropa/camisas/' === $u::strip( 'https://sitio.test/sub/product-category/ropa/camisas/', 'https://sitio.test/sub', '/product-category/' ), 'Categoría en un subdirectorio, con barras en la base.' );
check( '' === $u::strip( 'https://sitio.test/products/tenis/', 'https://sitio.test/', 'product' ), 'Una base parecida no se confunde.' );
check( '' === $u::strip( 'https://sitio.test/tienda/product/tenis/', 'https://sitio.test/', 'product' ), 'La base sólo se quita al principio de la ruta.' );
check( '' === $u::strip( 'https://otro.test/product/tenis/', 'https://sitio.test/', 'product' ), 'Otro dominio no se toca.' );
check( '' === $u::strip( 'https://sitio.test/product/tenis/', 'https://sitio.test/', '' ), 'Sin base no hay nada que quitar.' );
check( array( 'seo_strip_product_base' => 'product', 'seo_strip_product_cat_base' => 'product_cat', 'seo_strip_category_base' => 'category' ) === $u::KEYS, 'Las tres opciones.' );
echo "URL sin base: la base se quita sólo donde corresponde.\n";
