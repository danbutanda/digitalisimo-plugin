<?php
/**
 * Schema: vocabulario oficial, reglas del auditor, grafo único y unificación
 * del JSON-LD de otros plugins. El HTML de prueba reproduce los problemas
 * reales detectados: tipo inventado, @id repetidos en bloques separados,
 * BreadcrumbList en la portada y FAQPage aparte.
 */
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-vocabulary.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-rules.php';
require __DIR__ . '/../digitalisimo-seo/includes/schema/class-schema-graph.php';
$V = 'Digitalisimo_Integrations_Schema_Vocabulary';
$R = 'Digitalisimo_Integrations_Schema_Rules';
$G = 'Digitalisimo_Integrations_Schema_Graph';
function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FALLO: $message\n" ); exit( 1 ); } }
function has_issue( $report, $status, $needle ) { foreach ( $report['issues'] as $i ) if ( $i['status'] === $status && false !== strpos( $i['where'] . ' ' . $i['message'], $needle ) ) return true; return false; }
function types_in( $graph ) { $out = array(); foreach ( $graph as $n ) $out[] = implode( '+', (array) $n['@type'] ); return $out; }

// --- Vocabulario ---
check( 'official' === $V::status( 'LocalBusiness' ), 'LocalBusiness es oficial' );
check( 'unknown' === $V::status( 'Agencia de Marketing Digital' ), 'un tipo inventado no es oficial' );
check( 'LocalBusiness' === $V::local_type( 'Agencia de Marketing Digital' ), 'un tipo inventado cae en LocalBusiness' );
check( 'ProfessionalService' === $V::local_type( 'ProfessionalService' ), 'subtipo oficial se conserva' );
check( 'LocalBusiness' === $V::local_type( 'local business' ), 'el espacio y la capitalización se corrigen' );
check( 'LocalBusiness' === $V::local_type( 'WebPage' ), 'un tipo oficial que no es negocio no vale como negocio' );
check( 'Restaurant' === $V::local_type( 'https://schema.org/Restaurant' ), 'acepta la URL del tipo' );
check( $V::is_a( 'BlogPosting', 'Article' ) && $V::is_a( 'Dentist', 'Organization' ), 'herencia' );
check( $V::property_fits( array( 'LocalBusiness' ), 'areaServed' ) && ! $V::property_fits( array( 'WebPage' ), 'areaServed' ), 'dominio de propiedades' );
check( in_array( 'Restaurant', $V::subtypes( 'LocalBusiness' ), true ) && ! in_array( 'WebPage', $V::subtypes( 'LocalBusiness' ), true ), 'subtipos de LocalBusiness' );
check( 'superseded' === $V::property_status( 'serviceArea' ) && 'areaServed' === $V::property_replacement( 'serviceArea' ), 'propiedad obsoleta' );

// --- HTML como el publicado hoy ---
$home = 'https://agencia.test/';
$questions = array( '¿Qué hace una agencia digital?' => 'Integra marketing y tecnología.', '¿Trabajan con empresas consolidadas?' => 'Sí, con empresas con trayectoria.' );
$faq = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array() );
foreach ( $questions + array( '¿Pregunta oculta que no se ve?' => 'Respuesta.' ) as $q => $a ) $faq['mainEntity'][] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) );
$legacy = array( '@context' => 'https://schema.org', '@graph' => array(
	array( '@type' => 'Organization', '@id' => $home . '#organization', 'name' => 'Agencia', 'url' => $home ),
	array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => 'Agencia', 'publisher' => array( '@id' => $home . '#organization' ) ),
	array( '@type' => 'WebPage', '@id' => $home . '#webpage', 'url' => $home, 'name' => 'Inicio', 'isPartOf' => array( '@id' => $home . '#website' ) ),
	array( '@type' => 'BreadcrumbList', '@id' => $home . '#breadcrumb', 'itemListElement' => array( array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $home ), array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Inicio', 'item' => $home ) ) ),
	array( '@type' => 'Agencia de Marketing Digital', '@id' => $home . '#localbusiness', 'name' => 'Agencia', 'telephone' => '+52 8711111111', 'address' => array( '@type' => 'PostalAddress', 'addressLocality' => 'Torreón', 'addressCountry' => 'México' ) ),
) );
$location = array( '@context' => 'https://schema.org', '@graph' => array( array( '@type' => 'WebPage', '@id' => $home . '#webpage', 'spatialCoverage' => array( '@type' => 'Country', 'name' => 'México' ) ), array( '@type' => 'Agencia de Marketing Digital', '@id' => $home . '#localbusiness', 'areaServed' => array( '@type' => 'Country', 'name' => 'México' ) ) ) );
$body = '<h1>Agencia</h1>'; foreach ( $questions as $q => $a ) $body .= '<div class="elementor-tab-title">' . htmlspecialchars( $q ) . '</div><p>' . $a . '</p>';
$script = function( $data, $class = '' ) { return '<script type="application/ld+json"' . ( $class ? ' class="' . $class . '"' : '' ) . '>' . json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>'; };
$before = '<html><head>' . $script( $legacy ) . $script( $location ) . '</head><body>' . $body . $script( $faq ) . '</body></html>';
$ctx = array( 'url' => $home, 'home' => true, 'expected' => array( array( 'type' => 'Organization', 'why' => '', 'base' => true ), array( 'type' => 'WebSite', 'why' => '', 'base' => true ), array( 'type' => 'WebPage', 'why' => '', 'base' => true ) ) );
$r = $R::analyze( $before, $ctx );
check( 3 === $r['blocks'], 'tres bloques' );
check( 'ERROR' === $r['status'], 'el HTML actual tiene errores' );
check( has_issue( $r, 'ERROR', 'no es un tipo de Schema.org' ), 'detecta el tipo inventado' );
check( has_issue( $r, 'ADVERTENCIA', 'bloques separados' ), 'detecta @id en bloques separados' );
check( has_issue( $r, 'ADVERTENCIA', 'La portada no necesita BreadcrumbList' ), 'breadcrumb en portada' );
check( has_issue( $r, 'ERROR', '¿Pregunta oculta que no se ve?' ), 'pregunta no visible' );
check( has_issue( $r, 'ADVERTENCIA', 'addressCountry' ), 'país sin código ISO' );
check( has_issue( $r, 'ADVERTENCIA', 'páginas (WebPage, FAQPage' ), 'dos páginas para la misma URL' );

// --- Grafo propio ---
$site = array( 'home' => $home, 'name' => 'Agencia', 'org_name' => 'Agencia', 'org_type' => 'Organization', 'org_url' => '', 'logo' => $home . 'logo.png', 'image' => '', 'same_as' => array( 'https://www.facebook.com/agencia' ), 'email' => '', 'language' => 'es-MX',
	'local' => array( 'type' => $V::local_type( 'Agencia de Marketing Digital' ), 'name' => 'Agencia', 'telephone' => '+52 8711111111', 'price' => '', 'address' => array( 'streetAddress' => 'Centro', 'addressLocality' => 'Torreón', 'addressRegion' => 'Coahuila', 'postalCode' => '27000', 'addressCountry' => 'México' ), 'geo' => array(), 'hours' => array() ) );
$page = array( 'kind' => 'home', 'home' => true, 'url' => $home, 'title' => 'Agencia digital', 'description' => 'Marketing y tecnología.', 'breadcrumbs' => array( array( 'name' => 'Inicio', 'url' => $home ), array( 'name' => 'Inicio', 'url' => $home ) ), 'entity' => null );
$graph = $G::build( $site, $page );
check( array( 'LocalBusiness', 'WebSite', 'WebPage' ) === types_in( $graph ), 'portada: negocio, sitio y página, sin breadcrumb: ' . implode( ',', types_in( $graph ) ) );
check( 'MX' === $graph[0]['address']['addressCountry'] && $home . '#organization' === $graph[0]['@id'], 'una sola entidad con país ISO' );
check( array( '@id' => $home . '#organization' ) === $graph[2]['about'] && array( '@id' => $home . '#website' ) === $graph[2]['isPartOf'], 'relaciones por @id' );
$site_plain = array( 'local' => null ) + $site;
check( 'Organization' === $G::build( $site_plain, $page )[0]['@type'], 'sin datos locales se publica Organization' );
$incomplete = $site; $incomplete['local']['telephone'] = ''; $incomplete['local']['address']['streetAddress'] = ''; $incomplete['local']['address']['postalCode'] = '';
check( 'Organization' === $G::build( $incomplete, $page )[0]['@type'], 'datos locales incompletos no generan LocalBusiness' );
$post = array( 'kind' => 'singular', 'home' => false, 'url' => $home . 'guia/', 'title' => 'Guía SEO | Agencia', 'headline' => 'Guía SEO', 'description' => 'Paso a paso.', 'published' => '2026-01-02T10:00:00-06:00', 'modified' => '2026-02-03T10:00:00-06:00', 'image' => $home . 'guia.jpg', 'breadcrumbs' => array( array( 'name' => 'Inicio', 'url' => $home ), array( 'name' => 'Guía SEO', 'url' => $home . 'guia/' ) ), 'entity' => array( 'type' => 'BlogPosting' ), 'author' => array( 'name' => 'Ana', 'slug' => 'ana', 'url' => $home . 'author/ana/' ) );
$graph = $G::build( $site, $post );
check( array( 'LocalBusiness', 'WebSite', 'WebPage', 'BreadcrumbList', 'BlogPosting', 'Person' ) === types_in( $graph ), 'entrada: ' . implode( ',', types_in( $graph ) ) );
check( array( '@id' => $home . 'guia/#blogposting' ) === $graph[2]['mainEntity'] && array( '@id' => $home . '#/schema/person/ana' ) === $graph[4]['author'], 'artículo enlazado a página y autor' );
$report = $R::analyze_graph( array( '@context' => 'https://schema.org', '@graph' => $graph ), array( 'url' => $post['url'], 'home' => false, 'expected' => $G::expected( $site, $post ) ) );
check( ! has_issue( $report, 'ERROR', '' ) && ! $report['missing'], 'el grafo propio no tiene errores: ' . json_encode( $report['issues'], JSON_UNESCAPED_UNICODE ) );
$service = array( 'entity' => array( 'type' => 'Service' ), 'spatial' => array( '@type' => 'City', 'name' => 'Torreón' ) ) + $post;
$graph = $G::build( $site, $service );
check( 'Service' === $graph[4]['@type'] && array( '@id' => $home . '#organization' ) === $graph[4]['provider'] && 'Torreón' === $graph[4]['areaServed']['name'], 'servicio con proveedor y área' );
$person_site = array( 'org_type' => 'Person' ) + $site;
$graph = $G::build( $person_site, $page );
check( 'Person' === $graph[0]['@type'] && $home . '#person' === $graph[0]['@id'] && 'LocalBusiness' === $graph[1]['@type'] && array( '@id' => $home . '#person' ) === $graph[1]['founder'], 'persona con negocio propio' );
check( 'Organization' === $G::build( array( 'org_type' => 'LocalBusiness', 'local' => null ) + $site, $page )[0]['@type'], 'LocalBusiness sin datos reales cae en Organization' );

// --- Unificación del HTML actual ---
$own = $G::script( $G::build( $site, $page ) );
$html = '<html><head>' . $own . $script( $location ) . '</head><body>' . $body . $script( $faq ) . '</body></html>';
list( $after, $log ) = $G::unify( $html, $page );
$final = $R::analyze( $after, $ctx );
check( 1 === $final['blocks'], 'un solo bloque tras unificar' );
check( ! has_issue( $final, 'ERROR', '' ), 'sin errores tras unificar: ' . json_encode( $final['issues'], JSON_UNESCAPED_UNICODE ) );
$data = json_decode( preg_replace( '#^.*?<script[^>]*>(.*?)</script>.*$#s', '$1', $after ), true )['@graph'];
check( array( 'WebPage', 'FAQPage' ) === $data[2]['@type'] && 2 === count( $data[2]['mainEntity'] ), 'FAQ integradas en el WebPage sólo con preguntas visibles' );
check( 'México' === $data[2]['spatialCoverage']['name'] && ! isset( $data[0]['areaServed'] ), 'la cobertura llega al WebPage y el negocio global no cambia por página' );
check( false === strpos( $after, 'Agencia de Marketing Digital' ), 'el tipo inventado desaparece' );
check( count( $log ) >= 3, 'registro de cambios: ' . implode( ' | ', $log ) );
if ( getenv( 'SCHEMA_DEBUG' ) ) { echo implode( "\n", $log ), "\n", $G::json( $data, true ), "\n"; print_r( $final['issues'] ); }
// Breadcrumb ajeno en la portada se retira.
list( $after ) = $G::unify( '<head>' . $own . $script( $legacy ) . '</head><body>' . $body . '</body>', $page );
check( false === strpos( $after, 'BreadcrumbList' ), 'sin BreadcrumbList en la portada' );
// Bloque ilegible: se deja tal cual.
list( $after ) = $G::unify( '<head>' . $own . '<script type="application/ld+json">{roto</script></head>', $page );
check( false !== strpos( $after, '{roto' ), 'un bloque ilegible no se toca' );
// Sin grafo propio no se modifica nada.
check( $before === $G::unify( $before, $page )[0], 'sin grafo propio el HTML queda igual' );

// --- Producto de WooCommerce ---
$product_page = array( 'kind' => 'singular', 'home' => false, 'url' => $home . 'producto/taza/', 'title' => 'Taza', 'description' => 'Taza.', 'breadcrumbs' => array(), 'entity' => null );
$woo = array( '@context' => 'https://schema.org/', '@type' => 'Product', 'name' => 'Taza', 'offers' => array( array( '@type' => 'Offer', 'price' => '100', 'priceCurrency' => 'MXN' ) ), 'brand' => array( '@id' => 'https://otro.test/#org' ) );
list( $merged ) = $G::merge( $G::build( $site, $product_page ), array( $woo ), $product_page );
check( 'Product' === $merged[3]['@type'] && array( '@id' => $home . 'producto/taza/#product' ) === $merged[2]['mainEntity'] && array( '@id' => $home . 'producto/taza/#webpage' ) === $merged[3]['mainEntityOfPage'], 'producto integrado como entidad principal' );

// --- Detección contextual y elegibilidad ---
$profile_page = array( 'kind' => 'author', 'home' => false, 'url' => $home . 'author/ana/', 'title' => 'Ana', 'description' => 'Autora.', 'breadcrumbs' => array(), 'entity' => null, 'profile' => array( 'name' => 'Ana', 'slug' => 'ana', 'url' => $home . 'author/ana/' ) );
$profile_graph = $G::build( $site_plain, $profile_page );
check( array( 'Organization', 'WebSite', 'ProfilePage', 'Person' ) === types_in( $profile_graph ), 'archivo de autor: ProfilePage y Person, sin Article inventado' );
check( $profile_graph[2]['mainEntity']['@id'] === $profile_graph[3]['@id'], 'perfil enlaza a la persona con @id estable' );
$profile_report = $R::analyze_graph( array( '@context' => 'https://schema.org', '@graph' => $profile_graph ), array( 'url' => $profile_page['url'], 'expected' => $G::expected( $site_plain, $profile_page ) ) );
check( ! $profile_report['missing'] && ! has_issue( $profile_report, 'ERROR', '' ), 'perfil validado y esperado por el auditor' );
$video_page = array( 'video' => array( 'name' => 'Demostración', 'thumbnailUrl' => $home . 'poster.jpg', 'uploadDate' => '2026-01-02T10:00:00-06:00', 'contentUrl' => $home . 'video.mp4' ) ) + $post;
$video_graph = $G::build( $site_plain, $video_page );
check( in_array( 'VideoObject', types_in( $video_graph ), true ) && $video_graph[2]['video']['@id'] === $post['url'] . '#video', 'video real enlazado a WebPage' );
$video_html = '<html><head>' . $G::script( $video_graph ) . '</head><body><video controls src="' . $home . 'video.mp4" poster="' . $home . 'poster.jpg"></video></body></html>';
check( $video_html === $G::unify( $video_html, $video_page )[0], 'video presente permanece en el grafo final' );
$video_missing = '<html><head>' . $G::script( $video_graph ) . '</head><body><p>Sin video</p></body></html>';
$video_clean = $G::unify( $video_missing, $video_page )[0];
check( false === strpos( $video_clean, 'VideoObject' ) && false === strpos( $video_clean, '"video":' ), 'video no renderizado se retira del JSON-LD final' );
$video_incomplete = array( 'video' => array( 'name' => 'Demostración' ) ) + $post;
check( ! in_array( 'VideoObject', types_in( $G::build( $site_plain, $video_incomplete ) ), true ), 'video incompleto se omite' );
$service_no_offer = array( 'entity' => array( 'type' => 'Service' ), 'spatial' => array() ) + $post;
$service_graph = $G::build( $site_plain, $service_no_offer );
check( ! isset( $service_graph[4]['offers'] ) && ! isset( $service_graph[4]['areaServed'] ), 'servicio no inventa precio ni zona' );
$service_with_offer = array( 'entity' => array( 'type' => 'Service', 'offers' => array( 'price' => '500', 'priceCurrency' => 'MXN' ) ) ) + $service_no_offer;
check( 'MXN' === $G::build( $site_plain, $service_with_offer )[4]['offers']['priceCurrency'], 'servicio con oferta explícita' );
$product_page = array( 'entity' => array( 'type' => 'Product', 'sku' => 'SKU-1', 'offers' => array( 'price' => '0', 'priceCurrency' => 'MXN', 'availability' => 'https://schema.org/InStock' ) ) ) + $post;
$product_graph = $G::build( $site_plain, $product_page );
check( '0' === $product_graph[4]['offers']['price'] && 'SKU-1' === $product_graph[4]['sku'] && ! isset( $product_graph[4]['brand'] ), 'producto usa precio real cero y no inventa marca' );
$faq_report = $R::analyze( '<script type="application/ld+json">' . $G::json( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array( array( '@type' => 'Question', 'name' => '¿Cuánto cuesta?', 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => 'Depende del proyecto.' ) ) ) ) ) . '</script><p>¿Cuánto cuesta?</p><p>Depende del proyecto.</p>' );
check( 'Semántico' === $faq_report['rich'][0]['category'] && 'NO APLICABLE' === $faq_report['rich'][0]['status'], 'FAQ válido es semántico, no rich result de Google' );
$own_faq_graph = $G::build( $site_plain, $page );
$own_faq_graph[2]['@type'] = array( 'WebPage', 'FAQPage' );
$own_faq_graph[2]['mainEntity'] = array( array( '@type' => 'Question', 'name' => '¿Cuánto cuesta?', 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => 'No publicada.' ) ) );
$own_faq_html = '<html><head>' . $G::script( $own_faq_graph ) . '</head><body><p>¿Cuánto cuesta?</p></body></html>';
$own_faq_clean = $G::sanitize_own_faq( $own_faq_html, $page );
check( false === strpos( $own_faq_clean, 'FAQPage' ) && false === strpos( $own_faq_clean, 'No publicada.' ), 'FAQ propia se filtra aun sin unificación externa' );
$hidden_answer = $R::analyze( '<script type="application/ld+json">' . $G::json( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array( array( '@type' => 'Question', 'name' => '¿Cuánto cuesta?', 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => 'Respuesta oculta.' ) ) ) ) ) . '</script><p>¿Cuánto cuesta?</p>' );
check( has_issue( $hidden_answer, 'ERROR', 'respuestas no aparecen'), 'auditor detecta respuesta FAQ oculta' );

$shop_markup = array( '@type' => 'Product', '@id' => $product_page['url'] . '#product', 'name' => 'Producto real', 'offers' => array( '@type' => 'Offer', 'price' => '125', 'priceCurrency' => 'MXN', 'url' => $product_page['url'] ) );
list( $merged_product ) = $G::merge( $product_graph, array( $shop_markup ), $product_page );
check( '125' === $merged_product[4]['offers']['price'] && 1 === count( array_filter( types_in( $merged_product ), function( $type ) { return 'Product' === $type; } ) ), 'WooCommerce aporta su oferta sin duplicar Product' );
$faq_hidden_answer = array( '@type' => 'FAQPage', 'mainEntity' => array( array( '@type' => 'Question', 'name' => '¿Cuánto cuesta?', 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => 'No publicada.' ) ) ) );
list( $faq_filtered ) = $G::merge( $G::build( $site_plain, $page ), array( $faq_hidden_answer ), $page, '¿Cuánto cuesta?' );
check( ! in_array( 'FAQPage', types_in( $faq_filtered ), true ), 'FAQ sin respuesta visible no se publica' );
$site_other = $site_plain; $site_other['home'] = 'https://otro.ejemplo/';
$other_graph = $G::build( $site_other, array( 'url' => 'https://otro.ejemplo/guia/' ) + $post );
check( 0 === strpos( $other_graph[0]['@id'], $site_other['home'] ) && 0 === strpos( $other_graph[2]['@id'], $site_other['home'] ), 'los @id se aíslan por sitio de Multisite' );
$local_with_map = $site; $local_with_map['email'] = 'hola@agencia.test'; $local_with_map['local']['geo'] = array( 'latitude' => 25.5, 'longitude' => -103.4 );
$local_graph = $G::build( $local_with_map, $page );
check( isset( $local_graph[0]['hasMap'], $local_graph[0]['contactPoint'] ) && '25.5' === $local_graph[0]['geo']['latitude'], 'mapa y contacto salen de datos reales' );
$local_report = $R::analyze_graph( array( '@context' => 'https://schema.org', '@graph' => $local_graph ) );
check( ! has_issue( $local_report, 'ADVERTENCIA', 'hasMap' ) && ! has_issue( $local_report, 'ADVERTENCIA', 'contactPoint' ), 'mapa y contacto son propiedades válidas para negocio local' );

// --- Utilidades ---
check( '25.5597304' === $G::coordinate( 25.55973041976771042982 ) && '-103.4334658' === $G::coordinate( '-103.433465771164' ) && '25' === $G::coordinate( 25.0 ), 'coordenadas con 7 decimales' );
$geo = $site; $geo['local']['geo'] = array( 'latitude' => 25.55973041976771, 'longitude' => -103.43346577116407 );
check( false === strpos( $G::json( $G::build( $geo, $page ) ), '0429' ) && false !== strpos( $G::json( $G::build( $geo, $page ) ), '"latitude":"25.5597304"' ), 'geo sin cifras de más' );
check( has_issue( $R::analyze_graph( array( '@context' => 'https://schema.org', '@graph' => $G::build( $site, $page ) ) ), 'ADVERTENCIA', 'priceRange' ), 'priceRange recomendado como en Google' );
check( 'MX' === $G::country( 'México' ) && 'MX' === $G::country( 'mx' ) && 'Narnia' === $G::country( 'Narnia' ), 'código de país' );
check( false === strpos( $G::json( array( 'x' => '</script><b>' ) ), '</script>' ), 'el JSON no cierra la etiqueta' );
check( 'Organization' === $R::rule( array( 'Organization' ) )['type'] && 'LocalBusiness' === $R::rule( array( 'ProfessionalService' ) )['type'] && 'Article' === $R::rule( array( 'BlogPosting' ) )['type'], 'reglas de Google por herencia' );
$bad = $R::analyze( '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Article","headline":"x","datePublished":"02/01/2026","image":"/rel.jpg","fooBar":1}</script>', array() );
check( has_issue( $bad, 'ERROR', 'ISO 8601' ) && has_issue( $bad, 'ERROR', 'URL absoluta' ) && has_issue( $bad, 'ADVERTENCIA', '«fooBar» no es una propiedad' ), 'fecha, URL y propiedad inválidas' );
echo "Schema: OK\n";
