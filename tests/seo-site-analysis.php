<?php
/**
 * Tandas B y C de la auditoría SEO de digitalisimo.lat (2026-11-24), sin WordPress:
 *   B1  shortcodes y variables sin resolver en títulos, descripciones, migas y ajustes («[descripcion_sitio]
 *       [contenido_pilar]» como primera miga), sin falsos positivos con texto normal entre corchetes ni con «50 %»;
 *   B2  similitud de texto (tejas, MinHash, LSH y Jaccard exacto): /paginas-web/, /paginas-web-torreon/ y
 *       /diseno-de-paginas-web-en-torreon/ forman un grupo, una página distinta no; palabra en disputa y cuál se queda;
 *   B3  plan de noindex, 410 y 301 en lote: nunca la portada ni otras páginas del sistema, nunca un 301 a la portada ni
 *       fuera del sitio, el tope de seguridad de A5, respaldo antes de cambiar y deshacer exacto;
 *   B4  canonical a otro dominio y veredicto de la comprobación (dominio inexistente, 404, redirección, dudoso);
 *   C1  descripción para redes vieja: la página cambió mucho, o anuncia un plazo que ya pasó («El Buen Fin · Termina
 *       el martes 17 de noviembre») y la página se actualizó después; una descripción recién escrita no.
 * Falla con el código de v518 (no existe el análisis). Uso: php tests/seo-site-analysis.php
 */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 ); define( 'WEEK_IN_SECONDS', 604800 );
define( 'ARRAY_A', 'ARRAY_A' );
$GLOBALS['opt'] = array( 'page_on_front' => 1, 'page_for_posts' => 0, 'digitalisimo_seo_redirections_db' => 3 );
$GLOBALS['meta'] = array(); $GLOBALS['transients'] = array(); $GLOBALS['scheduled'] = array();
$GLOBALS['shortcode_tags'] = array( 'gallery' => 'x', 'contact-form-7' => 'x' );
function add_action() {} function add_filter() {}
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opt'] ) ? $GLOBALS['opt'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opt'][ $k ] = $v; return true; }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); return true; }
function get_post( $id ) { $id = is_object( $id ) ? $id->ID : (int) $id; return $GLOBALS['posts'][ $id ] ?? null; }
function get_post_status( $id ) { return ( $GLOBALS['posts'][ (int) $id ] ?? null ) ? $GLOBALS['posts'][ (int) $id ]->post_status : false; }
function get_permalink( $id ) { $id = is_object( $id ) ? $id->ID : (int) $id; return 1 === $id ? 'https://cliente.test/' : 'https://cliente.test/' . $GLOBALS['posts'][ $id ]->post_name . '/'; }
function get_the_title( $id ) { $id = is_object( $id ) ? $id->ID : (int) $id; return $GLOBALS['posts'][ $id ]->post_title ?? ''; }
function url_to_postid( $url ) { foreach ( $GLOBALS['posts'] as $id => $p ) if ( rtrim( get_permalink( $id ), '/' ) === rtrim( $url, '/' ) ) return $id; return 0; }
function home_url( $path = '/' ) { return 'https://cliente.test' . ( '' === $path ? '/' : '/' . ltrim( $path, '/' ) ); }
function wp_parse_url( $url, $c = -1 ) { return parse_url( $url, $c ); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function esc_url_raw( $v, $p = null ) { return preg_match( '#^https?://#i', (string) $v ) ? (string) $v : ''; }
function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
function current_time( $t, $gmt = 0 ) { return gmdate( 'Y-m-d H:i:s' ); }
function get_current_user_id() { return 1; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_single_event( $t, $h, $a = array() ) { $GLOBALS['scheduled'][] = $h; return true; }
function wp_clear_scheduled_hook( $h ) { return 0; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function current_user_can() { return true; }
class WP_Error { public $code; public $message; public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; } public function get_error_message() { return $this->message; } public function get_error_code() { return $this->code; } }
/** La tabla de redirecciones en memoria. */
class Digi_Fake_DB {
	public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta'; public $prefix = 'wp_'; public $insert_id = 0; public $rows = array();
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function insert( $t, $row ) { $this->insert_id = count( $this->rows ) + 1; $row['id'] = $this->insert_id; $this->rows[ $this->insert_id ] = $row; return 1; }
	public function update( $t, $row, $where ) { $this->rows[ $where['id'] ] = array_merge( $this->rows[ $where['id'] ], $row ); return 1; }
	public function delete( $t, $where ) { unset( $this->rows[ $where['id'] ] ); return 1; }
	public function get_results( $sql, $o = null ) { return array_values( array_filter( $this->rows, function( $r ) { return 'active' === $r['status']; } ) ); }
	public function get_row( $q, $o = null ) { $id = (int) $q[1][0]; return $this->rows[ $id ] ?? null; }
	public function get_var( $q ) { return count( array_filter( $GLOBALS['posts'], function( $p ) { return 'publish' === $p->post_status; } ) ); }
}
$GLOBALS['wpdb'] = new Digi_Fake_DB();
class Digitalisimo_Integrations_SEO_Resolver { public static function option( $k, $d = null ) { return $GLOBALS['seo'][ $k ] ?? $d; } public static function foreign_seo() { return ''; } }
class Digitalisimo_Integrations_SEO_Variables { public static function catalog() { return array( 'title' => '', 'sitename' => '', 'sep' => '', 'excerpt' => '', 'date' => '' ); } }
require __DIR__ . '/../digitalisimo-seo/includes/class-index-hygiene.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-redirections.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-site-analysis.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-cleanup.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$A = 'Digitalisimo_Integrations_Site_Analysis';
$C = 'Digitalisimo_Integrations_SEO_Cleanup';
$page = function( $id, $slug, $title, $status = 'publish', $type = 'page' ) { $GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_name' => $slug, 'post_title' => $title, 'post_status' => $status, 'post_type' => $type, 'post_content' => '', 'post_modified_gmt' => '2026-11-23 18:00:00' ); };

/* B1 ---------------------------------------------------------------- */
check( array( '[descripcion_sitio]', '[contenido_pilar]' ) === $A::unresolved_in( '[descripcion_sitio] [contenido_pilar]' ), 'La miga de digitalisimo.lat: los dos shortcodes.' );
check( array() === $A::unresolved_in( '[Nota del editor] Precio en [MXN], [nuevo] y [texto](https://x.test)' ), 'Texto normal entre corchetes no cuenta: ' . json_encode( $A::unresolved_in( '[Nota del editor] Precio en [MXN], [nuevo]' ) ) );
check( array() === $A::unresolved_in( 'Receta de pan [video] y [gallery]' ), 'Una palabra suelta entre corchetes puede ser texto a propósito, aunque se llame como un shortcode del núcleo.' );
check( array( '[ubicacion_usuario]' ) === $A::unresolved_in( 'Soporte Técnico [ubicacion_usuario]' ), 'El de la tabla de hosting de la auditoría.' );
check( array( '[wpuf_account]', '[rank_math_breadcrumb]' ) === $A::unresolved_in( '[wpuf_account] [rank_math_breadcrumb]' ), 'Familias de plugins conocidos (del detector de A5).' );
check( array() === $A::unresolved_in( '[elementor-tag id="1" name="x"]' ), 'Las etiquetas dinámicas de Elementor nunca.' );
check( array( '%seo_title%' ) === $A::unresolved_in( '%title% %sep% %seo_title% %date(Y)%', true ), 'En una plantilla sólo la variable que el sitio no conoce.' );
check( array( '%sitename%' ) === $A::unresolved_in( 'Bienvenido a %sitename%', false ), 'En un texto sin plantilla cualquier %x% se vería tal cual.' );
check( array() === $A::unresolved_in( 'Hasta 50% de descuento y 100 % garantizado', false ), 'Un porcentaje no es una variable.' );
check( array() === $A::unresolved_in( 'Archivo de %s', false ), 'El %s de los formatos de las migas no es una variable.' );

/* B2 ---------------------------------------------------------------- */
$base = 'Diseñamos páginas web profesionales para empresas que quieren vender más en internet. Cada sitio es rápido, seguro y fácil de administrar, con hosting incluido, certificado SSL, correo corporativo y soporte técnico por WhatsApp. Trabajamos con WordPress y DIGITALÍSIMO Elements para que puedas cambiar textos, fotos y precios sin depender de nadie. Antes de empezar revisamos tu competencia, tus palabras clave y lo que buscan tus clientes; después armamos la estructura, escribimos los textos y optimizamos cada página para Google. Entregamos el sitio con capacitación, manual en video y un mes de ajustes sin costo. También conectamos tu tienda en línea, tus formularios y tus redes sociales, medimos las visitas con analítica y te enviamos un reporte mensual con lo que funcionó y lo que conviene mejorar. ';
$texts = array(
	10 => $base . 'Atendemos a todo México.',
	11 => $base . 'Atendemos en Torreón, Coahuila y la Comarca Lagunera.',
	12 => 'Diseño de páginas web en Torreón. ' . $base . 'Oficinas en Torreón.',
	13 => 'El hosting administrado con inteligencia artificial vigila tu sitio todo el día, aplica actualizaciones, hace respaldos diarios en dos ubicaciones y te avisa si algo falla. Incluye un panel propio para dominios, correos y certificados, migración gratuita desde tu proveedor actual y soporte en español. Los planes crecen contigo: empiezas con un sitio y agregas más cuando lo necesites, sin cambiar de servidor ni perder tus correos. Cada plan incluye protección contra ataques, caché en el borde y una prueba de velocidad mensual con recomendaciones concretas para tu negocio.',
	14 => str_repeat( 'Receta de pan de muerto con azahar, mantequilla y azúcar glas para compartir en familia. ', 12 ),
);
$sigs = array();
foreach ( $texts as $id => $text ) { $w = $A::words( $text ); check( count( $w ) >= 60, "Texto de prueba suficiente ($id)." ); $sigs[ $id ] = $A::signature( $A::shingles( $w ) ); }
check( 64 === count( $sigs[10] ) && $sigs[10] === $A::signature( $A::shingles( $A::words( $texts[10] ) ) ), 'Firma de 64 valores y determinista.' );
check( $A::unpack( $A::pack( $sigs[10] ) ) === $sigs[10], 'La firma se guarda y se lee igual.' );
$exact = $A::jaccard( $A::shingles( $A::words( $texts[10] ) ), $A::shingles( $A::words( $texts[11] ) ) );
check( $exact > 0.85 && abs( $A::similarity( $sigs[10], $sigs[11] ) - $exact ) < 0.15, 'Casi iguales: Jaccard ' . round( $exact, 2 ) . ', estimado ' . $A::similarity( $sigs[10], $sigs[11] ) );
check( $A::jaccard( $A::shingles( $A::words( $texts[10] ) ), $A::shingles( $A::words( $texts[13] ) ) ) < 0.05, 'Páginas distintas casi no comparten tejas.' );
$pairs = $A::candidates( $sigs );
check( isset( $pairs['10-11'], $pairs['10-12'], $pairs['11-12'] ) && ! isset( $pairs['10-13'] ) && ! isset( $pairs['13-14'] ), 'Candidatos del LSH: sólo los tres de páginas web. ' . json_encode( $pairs ) );
check( array( array( 10, 11, 12 ) ) === $A::clusters( array( '10-11' => 1, '11-12' => 1 ) ), 'Un grupo con los tres, aunque falte un par.' );
check( 'páginas web' === $A::shared_keyword( array( 'Páginas web', 'Páginas web Torreón', 'Diseño de páginas web en Torreón' ) ), 'Palabra en disputa: ' . $A::shared_keyword( array( 'Páginas web', 'Páginas web Torreón', 'Diseño de páginas web en Torreón' ) ) );
check( 'diseño web torreón' === $A::shared_keyword( array( 'A', 'B' ), array( 'Diseño web Torreón', 'diseño web torreón' ) ), 'Si todas tienen la misma keyword principal, ésa.' );
check( 11 === $A::keeper( array( 10 => array( 'incoming' => 1, 'words' => 900, 'date' => '2023-01-01' ), 11 => array( 'incoming' => 4, 'words' => 300, 'date' => '2024-01-01' ), 12 => array( 'incoming' => 4, 'words' => 200, 'date' => '2022-01-01' ) ) ), 'Se queda la de más enlaces entrantes; a igualdad, la de más texto.' );
$long = array(); for ( $i = 0; $i < 6000; $i++ ) $long[] = 'palabra' . $i;
check( $A::MAX_SHINGLES === count( $A::shingle_hashes( $long ) ) && is_int( $A::shingle_hashes( $long )[0] ), 'Tejas como enteros y con tope por contenido.' );
check( $A::jaccard( $A::shingle_hashes( $A::words( $texts[10] ) ), $A::shingle_hashes( $A::words( $texts[11] ) ) ) === $exact, 'Con hashes, el mismo Jaccard.' );
check( array() === $A::signature( array() ) && 0.0 === $A::similarity( array(), array( 1 ) ), 'Sin texto no hay firma ni similitud.' );

/* B4 ---------------------------------------------------------------- */
check( true === $A::offsite( 'https://digital.digitalisimo.org/paginas-web/', 'https://digitalisimo.lat/' ), '/que-es-una-pagina-web/: canonical a otro dominio.' );
check( false === $A::offsite( 'https://cdn.cliente.test/x/', 'https://www.cliente.test/' ) && false === $A::offsite( 'https://cliente.test/y/', 'https://cliente.test/' ), 'Un subdominio o el mismo sitio no cuentan.' );
check( 'gone' === $A::verdict( 0, 'cURL error 6: Could not resolve host: digital.digitalisimo.org' ) && 'gone' === $A::verdict( 0, 'timeout', false ), 'Dominio inexistente: no responde.' );
check( 'gone' === $A::verdict( 404 ) && 'gone' === $A::verdict( 410 ) && 'redirect' === $A::verdict( 301 ) && 'ok' === $A::verdict( 200 ), '404/410 roto, 3xx redirige, 200 bien.' );
foreach ( array( '169.254.169.254', '100.64.0.1', '127.0.0.1', '10.1.2.3', '192.168.1.25', '::1', '::', 'fe80::1', 'fd00::5', '::ffff:169.254.169.254', 'no-es-ip' ) as $ip ) check( $A::internal_ip( $ip ), "Dirección interna: $ip" );
foreach ( array( '8.8.8.8', '100.128.0.1', '2606:4700::1111' ) as $ip ) check( ! $A::internal_ip( $ip ), "Dirección pública: $ip" );
check( $A::internal_host( '169.254.169.254' ) && $A::internal_host( '[fe80::1]' ) && $A::internal_host( 'localhost' ), 'Un canonical a la red interna no se pide.' );
check( 'Apunta a una dirección interna: no se comprueba.' === $A::probe( 'http://169.254.169.254/latest/meta-data/' )['error'], 'La comprobación la rechaza antes de pedir nada.' );
check( 'unknown' === $A::verdict( 403 ) && 'unknown' === $A::verdict( 503 ) && 'unknown' === $A::verdict( 0, 'Operation timed out' ) && 'unknown' === $A::verdict( 0, 'A valid URL was not provided.' ), 'Lo dudoso (cortafuegos, caída, red, SSRF bloqueado) no se afirma.' );

/* C1 ---------------------------------------------------------------- */
$now      = gmmktime( 12, 0, 0, 11, 26, 2026 );
$modified = gmmktime( 18, 0, 0, 11, 23, 2026 );
$bf       = '| El Buen Fin · Termina el martes 17 de noviembre a las 23:59, hora del centro de México El Buen Fin · termina el martes 17 de noviembre, 23:59 Contrátalo';
$dates    = $A::dates_in( $bf, $now );
check( 2 === count( $dates ) && gmmktime( 23, 59, 59, 11, 17, 2026 ) === $dates[0][0], 'Lee «17 de noviembre» con el año en curso.' );
$r = $A::stale_reasons( $bf, $sigs[10], array(), $modified, $now );
check( 1 === count( $r ) && 'date' === $r[0][0], '/black-friday/: anuncia un plazo que ya pasó y la página se editó después. ' . json_encode( $r ) );
check( array() === $A::stale_reasons( $bf, $sigs[10], array(), gmmktime( 0, 0, 0, 11, 10, 2026 ), $now ), 'Si la página no se tocó desde el plazo, no se puede decir que quedó vieja.' );
check( array() === $A::stale_reasons( $bf, $sigs[10], array(), $modified, $now, true ), 'Una descripción recién escrita no está vieja.' );
check( array() === $A::stale_reasons( 'La agencia se fundó el 3 de marzo de 2015 en Torreón.', $sigs[10], array(), $modified, $now ), 'Una fecha sin plazo no dice nada.' );
check( array() === $A::stale_reasons( 'Oferta válida hasta el 30 de noviembre.', $sigs[10], array(), $modified, $now ), 'Un plazo que aún no llega, tampoco.' );
check( 1 === count( $A::stale_reasons( 'Oferta valid until November 20, 2026.', $sigs[10], array(), $modified, $now ) ), 'También en inglés.' );
check( array() === $A::dates_in( 'Promoción hasta julio 2027', $now ), '«julio 2027» no es «julio 20».' );
$december = gmmktime( 12, 0, 0, 12, 20, 2026 );
check( array() === $A::stale_reasons( 'Precio vigente hasta el 6 de enero.', $sigs[10], array(), $december - DAY_IN_SECONDS, $december ), 'Sin año, «6 de enero» escrito en diciembre es el del año que viene.' );
$changed = $A::stale_reasons( 'Diseño web en todo México.', $sigs[13], $sigs[10], $modified, $now );
check( 1 === count( $changed ) && 'changed' === $changed[0][0], 'La página cambió mucho desde que se escribió la descripción.' );
check( array() === $A::stale_reasons( 'Diseño web en todo México.', $sigs[11], $sigs[10], $modified, $now ), 'Un cambio pequeño no.' );

/* El repaso semanal no se reinicia mientras espera reunir su resultado --- */
$GLOBALS['opt'][ $A::STATE ] = array( 'version' => $A::VERSION, 'done' => true, 'dirty' => 1, 'started' => time() - 30 * DAY_IN_SECONDS, 'finished' => 0, 'cursor' => 0 );
$A::maybe_start();
check( ! empty( $GLOBALS['opt'][ $A::STATE ]['done'] ) && 1 === $GLOBALS['opt'][ $A::STATE ]['dirty'], 'Terminado y sin reunir: no vuelve a empezar (antes se reiniciaba con cada carga del escritorio).' );
$GLOBALS['opt'][ $A::STATE ]['dirty'] = 0;
$A::maybe_start();
check( empty( $GLOBALS['opt'][ $A::STATE ]['done'] ), 'Reunido y de hace más de una semana: empieza otro.' );

// Tres intentos fallidos de reunir el resultado: se deja de intentar (no cada minuto para siempre).
$GLOBALS['opt'][ $A::STATE ] = array( 'version' => $A::VERSION, 'done' => true, 'dirty' => 1, 'tries' => 3, 'started' => time(), 'finished' => 0 );
$A::run_batch();
check( 0 === $GLOBALS['opt'][ $A::STATE ]['dirty'] && $GLOBALS['opt'][ $A::STATE ]['failed'] > 0, 'Al tercer fallo se marca y no se reprograma.' );

/* B3 y B2: plan, tope, respaldo y deshacer --------------------------- */
$GLOBALS['posts'] = array();
$page( 1, '', 'Inicio' );
$page( 20, 'paginas-web', 'Páginas web' );
$page( 21, 'paginas-web-torreon', 'Páginas web Torreón' );
$page( 22, 'diseno-de-paginas-web-en-torreon', 'Diseño de páginas web en Torreón' );
$page( 23, 'china-prohibe-chips', 'China prohíbe chips', 'publish', 'post' );
$page( 24, 'borrador', 'Borrador', 'draft' );
$page( 25, 'ya-oculta', 'Ya oculta', 'publish', 'post' );
$GLOBALS['meta'][25]['digitalisimo_seo_noindex'] = '1';
$plan = $C::plan( 'noindex', array( 23, 1, 24, 25 ) );
check( array( 23 ) === array_column( $plan['items'], 'id' ), 'Sólo la huérfana publicada: ' . json_encode( $plan ) );
$why = array_column( $plan['skipped'], 1, 0 );
check( 'Es la portada' === $why[1] && 'No está publicada' === $why[24] && 'Ya lleva noindex' === $why[25], 'La portada, el borrador y la que ya lleva noindex se omiten con su motivo.' );
$page( 27, 'camiseta-azul', 'Camiseta azul', 'publish', 'product' );
$plan = $C::plan( '410', array( 27, 23 ) );
check( array( 23 ) === array_column( $plan['items'], 'id' ) && false !== strpos( $plan['skipped'][0][1], 'producto de la tienda' ), 'Un producto nunca entra en un lote (sólo se informa).' );
check( $A::store_type( 'product' ) && $A::store_type( 'product_variation' ) && ! $A::store_type( 'post' ), 'Tipos de la tienda.' );
check( is_wp_error( $C::plan( '301', array( 21 ), '/' ) ) && is_wp_error( $C::plan( '301', array( 21 ), '1' ) ), 'Nunca un 301 a la portada (soft 404).' );
check( is_wp_error( $C::plan( '301', array( 21 ), 'https://otro.test/paginas-web/' ) ) && is_wp_error( $C::plan( '301', array( 21 ), '/no-existe/' ) ) && is_wp_error( $C::plan( 'borrar', array( 21 ) ) ), 'Ni fuera del sitio, ni a algo que no existe, ni acciones fuera de la lista.' );
check( $C::over_cap( 25, 300 ) && $C::over_cap( 3, 10 ) && ! $C::over_cap( 3, 300 ), 'El mismo tope que A5: más de 20 páginas o del 10 %.' );

// B2: se queda /paginas-web/ y las otras dos van con 301.
$plan = $C::plan( '301', array( 20, 21, 22 ), '/paginas-web/', 'cluster' );
check( array( 21, 22 ) === array_column( $plan['items'], 'id' ) && 20 === $plan['target']['id'], 'La elegida no se redirige.' );
check( true === $plan['over_cap'], 'En un sitio de 6 páginas, 2 ya pasan del 10 %: pide la casilla del tope.' );
$batch = $C::apply( $plan );
$rules = array_values( $GLOBALS['wpdb']->rows );
check( 2 === count( $rules ) && 'cleanup' === $rules[0]['origin'] && 301 === $rules[0]['header_code'] && 'https://cliente.test/paginas-web/' === $rules[0]['url_to'], 'Dos reglas 301 de origen «cleanup» hacia la que se queda.' );
check( array( 'paginas-web-torreon', 'diseno-de-paginas-web-en-torreon' ) === array_map( function( $r ) { return json_decode( $r['sources'], true )[0]['pattern']; }, $rules ), 'Con el origen exacto de cada página.' );
check( '1' === get_post_meta( 21, 'digitalisimo_seo_noindex' ) && '1' === get_post_meta( 22, 'digitalisimo_seo_noindex' ) && '' === get_post_meta( 20, 'digitalisimo_seo_noindex' ), 'Salen del sitemap; la que se queda, no.' );
$log = get_option( $C::LOG );
check( 1 === count( $log ) && $batch === $log[0]['id'] && '' === $log[0]['items'][21]['noindex'] && $log[0]['items'][21]['rule'] > 0, 'El respaldo guarda lo de antes y las reglas creadas.' );
check( array( 21, 22 ) === $C::retired_ids(), 'El análisis deja de contar las redirigidas.' );
$again = $C::plan( '410', array( 21 ) );
check( array() === $again['items'] && 'Ya tiene una regla de redirección' === $again['skipped'][0][1], 'Una página que ya redirige no recibe otra regla.' );
check( $log[0]['items'][22]['rule'] > 0, 'El respaldo anota la regla de cada página.' );
check( is_wp_error( $C::plan( '301', array( 20 ), '/paginas-web-torreon/' ) ), 'Un 301 hacia una página que ya redirige (bucle A→B→A) se rechaza.' );
check( true === $C::undo( $batch ) && array() === $GLOBALS['wpdb']->rows && '' === get_post_meta( 21, 'digitalisimo_seo_noindex' ), 'Deshacer borra sus reglas y repone el noindex de antes.' );
check( false === $C::undo( $batch ) && array() === $C::retired_ids(), 'Un lote se deshace una sola vez.' );

// B3: 410 de una noticia de relleno; una regla ajena con el mismo id no se borra al deshacer.
$batch = $C::apply( $C::plan( '410', array( 23 ) ) );
$rule  = array_values( $GLOBALS['wpdb']->rows )[0];
check( 410 === $rule['header_code'] && '' === $rule['url_to'], '410 sin destino.' );
$GLOBALS['wpdb']->rows[ $rule['id'] ]['origin'] = 'manual';
$C::undo( $batch );
check( 1 === count( $GLOBALS['wpdb']->rows ), 'Deshacer sólo borra reglas que sigan siendo de la limpieza.' );
$GLOBALS['wpdb']->rows = array();

// Un lote cortado a mitad (sin el id de su regla anotado): deshacer la encuentra por su ruta.
$batch = $C::apply( $C::plan( '410', array( 23 ) ) );
$log = get_option( $C::LOG );
foreach ( $log as $i => $row ) if ( $batch === $row['id'] ) $log[ $i ]['items'][23]['rule'] = 0;
update_option( $C::LOG, $log );
check( true === $C::undo( $batch ) && array() === $GLOBALS['wpdb']->rows, 'Deshacer encuentra la regla de un lote cortado por su ruta.' );

// Dos lotes sobre la misma página, deshechos en el orden de su aplicación: queda como antes de los dos.
$page( 26, 'relleno', 'Relleno', 'publish', 'post' );
$first  = $C::apply( $C::plan( 'noindex', array( 26 ) ) );
$second = $C::apply( $C::plan( '410', array( 26 ) ) );
$C::undo( $first ); $C::undo( $second );
check( '' === get_post_meta( 26, 'digitalisimo_seo_noindex' ) && array() === $GLOBALS['wpdb']->rows, 'Lotes que se pisan, deshechos en cualquier orden: la página vuelve a estar como antes.' );

echo "Análisis del sitio: shortcodes y variables, canibalización, huérfanas, canonical y descripciones viejas.\n";
