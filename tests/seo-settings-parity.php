<?php
/**
 * DIGITALÍSIMO SEO: las pantallas de ajustes guardan lo mismo en un sitio individual, en cada sitio de una red y en la
 * administración de la red, sin mezclar sitios (revisión de paridad sitio/red del 2026-11-22). Pinta los formularios
 * reales (SEO → cada sección del sitio, Red → SEO), los envía como un navegador (tests/lib/browser-form.php) por el mismo
 * camino que WordPress (options.php con sanitize_option_{opción}, network/edit.php) y comprueba:
 *   1. paridad: cada opción del sitio existe en la red y al revés, salvo las exclusivas de cada sitio;
 *   2. guardar sin cambiar nada no cambia ningún valor efectivo, en ningún sitio ni en la red;
 *   3. lo que se escribe se guarda donde corresponde (sitio o red) y no toca el otro almacén;
 *   4. guardar la red no copia en ella los ajustes ni las claves del sitio principal;
 *   5. la red guarda aunque el sitio principal tenga el SEO propio apagado.
 */
error_reporting( E_ALL );
require __DIR__ . '/lib/browser-form.php';
require __DIR__ . '/lib/seo-admin-wp.php';
set_error_handler( function ( $no, $str, $file, $line ) { throw new ErrorException( $str, 0, $no, $file, $line ); } );
$checks = 0;
function check( $c, $m ) { global $checks; if ( ! $c ) { echo "FALLA: $m\n"; exit( 1 ); } $checks++; }

const OPT = 'digitalisimo_integrations_options';
Digitalisimo_Integrations_Settings::register_settings();

/**
 * Pantallas con formulario de ajustes: cada una con su página de sitio y, si la tiene, su página de red. La red de SEO
 * pinta todas sus pestañas en un solo formulario; Rendimiento tiene un formulario por sección en las dos superficies.
 */
function screens() {
	$out = array();
	foreach ( array( 'general', 'titles', 'schema', 'social', 'local', 'indexing', 'sitemap', 'robots', 'breadcrumbs', 'redirections', 'monitor404' ) as $section )
		$out[ 'SEO → ' . $section ] = array( 'site' => array( array( 'Digitalisimo_Integrations_SEO_Suite', 'page' ), array( 'page' => 'digitalisimo-seo-' . $section ), 'options.php' ), 'network' => array( array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' ), array( 'page' => 'digitalisimo-network-seo', 'tab' => $section ), 'digitalisimo_save_network_seo' ) );
	$out['SEO → Contenido → Configuración'] = array( 'site' => array( function () { Digitalisimo_Integrations_SEO::content_page( 'defaults' ); }, array( 'page' => 'digitalisimo-content' ), 'field:' . OPT . '[default_cluster_pillar]' ), 'network' => array( array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' ), array( 'page' => 'digitalisimo-network-seo', 'tab' => 'content' ), 'digitalisimo_save_network_seo' ) );
	$out['SEO → Ajustes de keywords'] = array( 'site' => array( array( 'Digitalisimo_Integrations_Settings', 'keyword_settings_page' ), array( 'page' => 'digitalisimo-keyword-settings' ), 'options.php' ), 'network' => array( array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' ), array( 'page' => 'digitalisimo-network-seo', 'tab' => 'content' ), 'digitalisimo_save_network_seo' ) );
	foreach ( array( 'css', 'tracking', 'fonts', 'preloads', 'images', 'javascript', 'a11y' ) as $section )
		$out[ 'Rendimiento → ' . $section ] = array( 'site' => array( array( 'Digitalisimo_Integrations_Performance_Console', 'site_page' ), array( 'page' => 'digitalisimo-performance', 'section' => $section ), 'options.php' ), 'network' => array( array( 'Digitalisimo_Integrations_Performance_Console', 'network_page' ), array( 'page' => 'digitalisimo-network-performance', 'section' => $section ), 'digitalisimo_save_network_seo' ) );
	return $out;
}
/** Ningún secreto se imprime en ninguna pantalla ni modo (tampoco como «valor efectivo»): las claves de prueba empiezan por sk-. */
function form_of( array $surface, array $fill = array() ) { $html = digi_seo_render( $surface[0], $surface[1] ); check( ! preg_match( '/sk-[A-Za-z-]*[A-Z]/', $html, $m ), 'Una pantalla imprimió un secreto (' . ( $m[0] ?? '' ) . '): ' . json_encode( $surface[1] ) . ( $GLOBALS['wp']['network_admin'] ? ' en la red' : ' en el sitio ' . $GLOBALS['wp']['blog'] ) ); return digi_browser_form( $html, $fill, null, $surface[2] ); }
/** Opciones que pinta un formulario (también las deshabilitadas por la herencia). */
function form_keys( array $form, $prefix ) { return array_values( array_diff( digi_browser_keys( array_merge( $form['names'], $form['disabled'] ), $prefix ), array( 'network_inherit' ) ) ); }
/** Envía el formulario como WordPress lo procesa: options.php en el sitio, network/edit.php en la red. */
function submit( array $surface, array $fill = array() ) {
	$form = form_of( $surface, $fill );
	check( $form['pressed'], 'El formulario tiene botón de guardar: ' . json_encode( $surface[1] ) );
	if ( 'digitalisimo_save_network_seo' === $surface[2] ) check( null !== digi_seo_network_edit( 'digitalisimo_save_network_seo', $form['post'] ), 'Guardar la red termina en su pantalla (sin quien reciba el envío, WordPress lleva al escritorio de la red sin guardar): ' . json_encode( $surface[1] ) );
	else digi_seo_options_php( $form['post'] );
	return $form;
}
/** Valor efectivo de cada opción en el sitio en curso, comparable (los números y casillas como texto). */
function effective() { $out = array(); foreach ( array_keys( Digitalisimo_Integrations_Settings::defaults() ) as $key ) { $v = Digitalisimo_Integrations_SEO_Resolver::option( $key ); $out[ $key ] = is_scalar( $v ) || null === $v ? (string) $v : json_encode( $v ); } return $out; }
/** Formas equivalentes de «nada»: vacío, 0, lista vacía; un horario con todos los días cerrados no publica horario. */
function same( $key, $a, $b ) {
	$norm = function ( $v ) use ( $key ) {
		if ( 'ai_providers' === $key && is_array( $d = json_decode( (string) $v, true ) ) ) $v = json_encode( array_filter( $d, function ( $p ) { return ! empty( $p['key'] ) || ! empty( $p['enabled'] ) || ! empty( $p['url'] ); } ) );
		if ( 'seo_local_schedule' === $key && is_array( $d = json_decode( (string) $v, true ) ) ) $v = json_encode( array_filter( $d, function ( $row ) { return 'closed' !== ( $row['state'] ?? 'closed' ); } ) );
		return in_array( (string) $v, array( '', '0', '[]', '{}', 'null' ), true ) ? '' : (string) $v;
	};
	return $norm( $a ) === $norm( $b );
}
function changed( array $before, array $after ) { $diff = array(); foreach ( $before as $k => $v ) if ( ! same( $k, $v, $after[ $k ] ?? null ) ) $diff[] = $k . ' (' . substr( $v, 0, 40 ) . ' → ' . substr( (string) ( $after[ $k ] ?? '∅' ), 0, 40 ) . ')'; return $diff; }
function blog_option( $blog ) { return (array) ( $GLOBALS['wp']['options'][ $blog ][ OPT ] ?? array() ); }

// 1. Paridad de campos: todo lo que un sitio puede ajustar existe en la red y al revés.
$site_keys = array(); $network_keys = array();
foreach ( screens() as $name => $screen ) {
	digi_seo_mode( 'site' ); $site_keys = array_merge( $site_keys, form_keys( form_of( $screen['site'] ), OPT ) );
	digi_seo_mode( 'network' ); $network_keys = array_merge( $network_keys, form_keys( form_of( $screen['network'] ), 'digitalisimo_network' ) );
}
$site_only = Digitalisimo_Integrations_SEO_Resolver::site_only_keys();
$only_site = array_values( array_unique( array_diff( $site_keys, $network_keys, $site_only ) ) ); sort( $only_site );
$only_network = array_values( array_unique( array_diff( $network_keys, $site_keys ) ) ); sort( $only_network );
// El interruptor del módulo de la página oculta «Ajustes de SEO» sólo existe en cada sitio (pendiente de decidir si la red lo
// ofrece). Los proveedores de IA antiguos de SEO son exclusivos de cada sitio (site_only_keys()).
$legacy = array( 'enable_seo' );
check( array() === array_values( array_diff( $only_site, $legacy ) ), 'Opciones que el sitio ajusta y la red no ofrece: ' . implode( ', ', array_diff( $only_site, $legacy ) ) );
check( array() === $only_network, 'Opciones que la red ofrece y ningún sitio puede personalizar: ' . implode( ', ', $only_network ) );

// Estado de partida de la red: el sitio principal (1) personaliza su identidad, tiene sus claves y el SEO propio apagado
// (así, en la administración de la red, Settings::get( 'enable_seo' ) es 0); el sitio 2 lo hereda todo.
$GLOBALS['wp']['options'] = array(
	1 => array( OPT => array( 'enable_seo' => 0, 'seo_organization_name' => 'PRINCIPAL', 'seo_twitter_user' => '@principal', 'openai_api_key' => 'sk-PRINCIPAL', 'ai_providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => 'sk-ant-PRINCIPAL', 'url' => '' ) ), 'seo_title_post' => '%title% · PRINCIPAL' ), 'digitalisimo_seo_network_inherit' => array( 'enable_seo' => 0, 'seo_organization_name' => 0, 'seo_twitter_user' => 0, 'seo_title_post' => 0 ) ),
	2 => array(),
);
$GLOBALS['wp']['site_options'] = array( OPT => array( 'openai_api_key' => 'sk-RED-OAI', 'ai_providers' => array( 'openai' => array( 'enabled' => 1, 'key' => 'sk-RED-PROV', 'url' => '' ) ), 'noindex_search' => 1, 'seo_organization_name' => 'RED', 'seo_twitter_user' => '@red', 'seo_title_post' => '%title% · RED', 'seo_schema_enabled' => 0, 'seo_robots_rules' => "User-agent: *\nDisallow: /red/", 'perf_safe_mode' => 0, 'seo_404_limit' => 500 ) );
// El plugin carga en la administración de la red con el sitio principal en curso.
digi_seo_mode( 'network' ); Digitalisimo_Integrations_SEO_Suite::init();
function on_blog( $blog, callable $read ) { switch_to_blog( $blog ); try { return $read(); } finally { restore_current_blog(); } }

// 2. Guardar sin cambiar nada no cambia nada, en ninguna pantalla ni superficie.
foreach ( screens() as $name => $screen ) {
	digi_seo_mode( 'network' );
	$before = array( on_blog( 1, 'effective' ), on_blog( 2, 'effective' ), blog_option( 1 ), blog_option( 2 ) );
	submit( $screen['network'] );
	check( array() === changed( $before[1], on_blog( 2, 'effective' ) ), "Red, $name: guardar sin cambios cambió lo que heredan los sitios: " . implode( ', ', changed( $before[1], on_blog( 2, 'effective' ) ) ) );
	check( array() === changed( $before[0], on_blog( 1, 'effective' ) ) && $before[2] === blog_option( 1 ) && $before[3] === blog_option( 2 ), "Red, $name: guardar la red no toca los ajustes de los sitios" );
	$network = json_encode( get_site_option( OPT ) );
	check( false === strpos( preg_replace( '/"(openai_api_key|ai_providers)":("[^"]*"|\{.*?\}\}),?/', '', $network ), 'PRINCIPAL' ), "Red, $name: guardar la red no copia en ella los ajustes ni las claves del sitio principal: " . $network );
	digi_seo_mode( 'site' );
	$before = array( effective(), get_site_option( OPT ), on_blog( 1, 'effective' ) );
	submit( $screen['site'] );
	check( array() === changed( $before[0], effective() ), "Sitio de red, $name: guardar sin cambios cambió valores: " . implode( ', ', changed( $before[0], effective() ) ) );
	check( $before[1] === get_site_option( OPT ) && array() === changed( $before[2], on_blog( 1, 'effective' ) ), "Sitio de red, $name: guardar un sitio no toca la red ni otro sitio" );
}

// 3. Lo escrito se guarda donde corresponde.
digi_seo_mode( 'network' );
$seo = function ( $tab, $network ) { return $network ? array( array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' ), array( 'page' => 'digitalisimo-network-seo', 'tab' => $tab ), 'digitalisimo_save_network_seo' ) : array( array( 'Digitalisimo_Integrations_SEO_Suite', 'page' ), array( 'page' => 'digitalisimo-seo-' . $tab ), 'options.php' ); };
submit( $seo( 'general', true ), array( 'digitalisimo_network[seo_organization_name]' => 'RED NUEVA', 'digitalisimo_network[seo_organization_url]' => 'https://red.test/marca/' ) );
submit( $seo( 'social', true ), array( 'digitalisimo_network[seo_twitter_card]' => 'summary' ) );
check( 'RED NUEVA' === on_blog( 2, function () { return Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ); } ) && 'PRINCIPAL' === on_blog( 1, function () { return Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ); } ), 'La red cambia lo que heredan los sitios y respeta al que lo personalizó' );
check( 'summary' === on_blog( 2, function () { return Digitalisimo_Integrations_SEO_Resolver::option( 'seo_twitter_card' ); } ), 'La red define la tarjeta de X/Twitter que heredan los sitios' );
digi_seo_mode( 'site' );
$inherit = OPT . '[network_inherit]';
submit( $seo( 'general', false ), array( $inherit . '[seo_organization_name]' => '', $inherit . '[seo_organization_url]' => '' ) );
check( 'RED NUEVA' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ), 'Dejar de heredar sin escribir conserva el valor que se veía (el de la red)' );
submit( $seo( 'general', false ), array( OPT . '[seo_organization_name]' => 'SITIO DOS', OPT . '[seo_organization_url]' => 'https://red.test/sitio2/marca/' ) );
check( 'SITIO DOS' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ) && 'https://red.test/sitio2/marca/' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_url' ), 'Un sitio personaliza el nombre y la URL de su organización' );
check( 'RED NUEVA' === get_site_option( OPT )['seo_organization_name'] && 'PRINCIPAL' === on_blog( 1, function () { return Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ); } ), 'Personalizar un sitio no toca la red ni el sitio principal' );
submit( $seo( 'general', false ), array( $inherit . '[seo_organization_name]' => '1' ) );
check( 'RED NUEVA' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_name' ), 'Volver a heredar vuelve al valor de la red' );
check( '1' === (string) Digitalisimo_Integrations_SEO_Resolver::option( 'noindex_search' ), 'El sitio 2 hereda «noindex» en búsquedas de la red' );
submit( $seo( 'indexing', false ), array( $inherit . '[noindex_search]' => '' ) );
check( '1' === (string) Digitalisimo_Integrations_SEO_Resolver::option( 'noindex_search' ), 'Dejar de heredar una casilla conserva lo que se veía, con o sin JavaScript (antes viajaba su «0» oculto y la apagaba)' );
check( empty( blog_option( 2 )['openai_api_key'] ?? '' ) && empty( Digitalisimo_Integrations_SEO_Resolver::option( 'openai_api_key' ) ) && empty( ( Digitalisimo_Integrations_SEO_Resolver::option( 'ai_providers' )['openai']['key'] ) ?? '' ), 'Las claves de IA de SEO son de cada sitio: el sitio 2 no usa ni copia las que quedaron en la red' );

// 4. WordPress individual: cada pantalla guarda sin cambios lo que ya tenía y guarda lo escrito.
$GLOBALS['wp']['options'] = array( 1 => array( OPT => array( 'seo_organization_name' => 'SOLO', 'seo_title_post' => '%title% · SOLO', 'seo_404_limit' => 700, 'openai_api_key' => 'sk-SOLO' ) ) ); $GLOBALS['wp']['site_options'] = array();
digi_seo_mode( 'single' );
foreach ( screens() as $name => $screen ) {
	$before = effective(); submit( $screen['site'] );
	check( array() === changed( $before, effective() ), "Sitio individual, $name: guardar sin cambios cambió valores: " . implode( ', ', changed( $before, effective() ) ) );
	check( array() === $GLOBALS['wp']['site_options'], "Sitio individual, $name: no escribe opciones de red" );
}
submit( $seo( 'general', false ), array( OPT . '[seo_organization_email]' => 'hola@solo.test' ) );
submit( $seo( 'monitor404', false ), array( OPT . '[seo_404_limit]' => '900' ) );
check( 'hola@solo.test' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_organization_email' ) && 900 === (int) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_404_limit' ) && 'sk-SOLO' === blog_option( 1 )['openai_api_key'], 'Un sitio individual guarda lo escrito y conserva su clave' );

// 5. Claves (pedido del propietario: «deben salir los *****»): con una guardada el campo muestra la máscara y nunca el
// secreto; guardar sin tocarla la conserva, una nueva la reemplaza y «Quitar la clave» la borra.
$keywords = screens()['SEO → Ajustes de keywords']['site'];
$html = digi_seo_render( $keywords[0], $keywords[1] );
check( false !== strpos( $html, 'value="••••••••••••"' ) && false === strpos( $html, 'sk-SOLO' ), 'La clave de OpenAI guardada se ve como ••••, no el secreto' );
submit( $keywords, array( OPT . '[openai_api_key]' => 'sk-NUEVA' ) );
check( 'sk-NUEVA' === blog_option( 1 )['openai_api_key'], 'Una clave nueva reemplaza la guardada' );
submit( $keywords, array( OPT . '[ai_providers][anthropic][key]' => 'sk-ant-SOLO' ) );
$html = digi_seo_render( $keywords[0], $keywords[1] );
check( 'sk-ant-SOLO' === blog_option( 1 )['ai_providers']['anthropic']['key'] && false === strpos( $html, 'sk-ant-SOLO' ) && false !== strpos( $html, 'name="' . OPT . '[ai_providers][anthropic][key]" value="••••••••••••"' ), 'Proveedores de SEO: la clave se guarda y se muestra como ••••' );
submit( $keywords );
check( 'sk-ant-SOLO' === blog_option( 1 )['ai_providers']['anthropic']['key'] && 'sk-NUEVA' === blog_option( 1 )['openai_api_key'], 'Guardar sin tocar las claves (llega la máscara) las conserva' );
submit( $keywords, array( OPT . '[openai_api_key]' => '••••••••••••sk-PEGADA', OPT . '[ai_providers][anthropic][key]' => 'sk-ant-DELANTE••••••••••••' ) );
check( 'sk-PEGADA' === blog_option( 1 )['openai_api_key'] && 'sk-ant-DELANTE' === blog_option( 1 )['ai_providers']['anthropic']['key'], 'Una clave pegada junto a la máscara guarda sólo la clave' );
check( false !== strpos( digi_seo_render( $keywords[0], $keywords[1] ), 'value="••••••••••••" onfocus="this.select()"' ), 'Al enfocar un campo de clave se selecciona la máscara' );
submit( $keywords, array( OPT . '[secret_remove][openai_api_key]' => '1', OPT . '[ai_providers][anthropic][remove]' => '1' ) );
check( '' === blog_option( 1 )['openai_api_key'] && '' === blog_option( 1 )['ai_providers']['anthropic']['key'], '«Quitar la clave» borra la de OpenAI y la del proveedor' );

// 6. SEO AI (misma pantalla para el sitio y la red) y sus pantallas de sitio.
if ( ! class_exists( 'Digitalisimo_AI' ) ) { class Digitalisimo_AI {} }
if ( ! function_exists( 'wp_generate_password' ) ) { function wp_generate_password( $n = 12 ) { return str_repeat( 'k', $n ); } }
$GLOBALS['wp']['options'] = array( 1 => array( OPT => array( 'seo_ai_enabled' => 1, 'seo_organization_name' => 'PRINCIPAL', 'openai_api_key' => 'sk-PRINCIPAL' ), 'digitalisimo_seo_network_inherit' => array( 'seo_ai_enabled' => 0 ) ), 2 => array() );
$GLOBALS['wp']['site_options'] = array( OPT => array( 'seo_ai_enabled' => 1, 'seo_ai_llms_enabled' => 1, 'seo_ai_llms_mode' => 'automatic', 'seo_ai_indexnow_key' => '' ) );
function seo_ai( $tab, array $fill = array() ) {
	$html = digi_seo_render( array( 'Digitalisimo_Integrations_SEO_AI', 'page' ), array( 'tab' => $tab ) );
	$form = digi_browser_form( $html, $fill, 'digitalisimo_ai_save' );
	$_GET = array( 'tab' => $tab ); $_POST = $form['post']; $_REQUEST = $form['post']; ob_start(); try { Digitalisimo_Integrations_SEO_AI::page(); } catch ( Throwable $e ) { $GLOBALS["render_errors"][] = $e->getMessage(); } finally { ob_end_clean(); $_POST = array(); $_REQUEST = array(); }
	return $form;
}
digi_seo_mode( 'site' );
$form = seo_ai( 'llms', array( 'ai[seo_ai_llms_mode]' => 'manual' ) );
check( array() === digi_browser_keys( $form['names'], 'ai' ) && 'automatic' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_llms_mode' ), 'SEO AI, sitio que hereda: los campos no se envían (antes se guardaban en el sitio y la pantalla volvía a mostrar los de la red)' );
seo_ai( 'general', array( 'ai_inherit' => '' ) );
check( '1' === (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_llms_enabled' ) && 'automatic' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_llms_mode' ), 'SEO AI: dejar de heredar conserva lo que se veía (antes el sitio caía a sus valores o a los predeterminados)' );
seo_ai( 'llms', array( 'ai[seo_ai_llms_mode]' => 'manual' ) );
check( 'manual' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_llms_mode' ) && 'automatic' === get_site_option( OPT )['seo_ai_llms_mode'], 'SEO AI, sitio que personaliza: guarda en el sitio y no toca la red' );
seo_ai( 'general', array( 'ai[seo_ai_enabled]' => '' ) );
check( '0' === (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_enabled' ), 'SEO AI: el interruptor del sitio se guarda (sanitize() descartaba las claves seo_ai_ que no conocía)' );
digi_seo_mode( 'network' );
seo_ai( 'indexnow', array( 'ai[seo_ai_indexnow_auto]' => '1' ) );
check( 1 === get_site_option( OPT )['seo_ai_indexnow_auto'] && false === strpos( json_encode( get_site_option( OPT ) ), 'PRINCIPAL' ), 'SEO AI de red: guarda en la red sin copiar el sitio principal' );
digi_seo_mode( 'site' );
$_POST = $_REQUEST = array( '_wpnonce' => 'n:digitalisimo_indexnow_tools', 'indexnow_action' => 'generate' );
try { Digitalisimo_Integrations_SEO_AI_IndexNow_Tools::action(); } catch ( Digi_Test_Redirect $r ) {}
$_POST = $_REQUEST = array();
check( '' !== (string) Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_indexnow_key' ), 'IndexNow: la clave generada en un sitio se aplica aunque la red defina una vacía' );
seo_ai( 'general', array( 'ai_inherit' => '1' ) );
$GLOBALS['wp']['options'][2][ OPT ]['seo_ai_indexnow_key'] = 'kkk'; Digitalisimo_Integrations_Settings::personalize( array( 'seo_ai_indexnow_key' ) );
seo_ai( 'general' );
check( 'kkk' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_ai_indexnow_key' ), '«Guardar SEO AI» sin tocar la herencia no deshace lo personalizado en IndexNow, llms.txt o Referencias' );

// 7. Redes contaminadas por el guardado antiguo (armaba la red sobre el sitio principal). Sólo las claves secretas de IA
// salen solas; lo demás se queda (la red lo usa a propósito: la ruta de acceso sin Tools, el SEO propio, el horario…) y
// Red → SEO lo revisa con casillas, explica qué pasa al quitarlo, guarda lo quitado y permite deshacerlo.
$GLOBALS['wp']['options'] = array( 1 => array( OPT => array( 'enable_seo' => 1, 'openai_api_key' => 'sk-PRINCIPAL-NUEVA', 'seo_organization_name' => 'PRINCIPAL', 'perf_ga4_id' => 'G-PRINCIPAL', 'seo_twitter_user' => '@principal', 'namecheap_api_user' => 'cuenta' ) ), 2 => array( OPT => array( 'enable_seo' => 0, 'seo_hide_login_slug' => '', 'openai_api_key' => 'sk-DOS' ) ) );
$GLOBALS['wp']['site_options'] = array( OPT => array( 'enable_seo' => 1, 'seo_hide_login_slug' => 'acceso-red', 'seo_local_hours' => 'Mo-Fr 09:00-18:00', 'content_editorial_voice' => 'Voz de la red', 'namecheap_api_user' => 'cuenta', 'openai_api_key' => 'sk-PRINCIPAL-VIEJA', 'openai_model' => 'gpt-x', 'ai_providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => 'sk-ant-PRINCIPAL', 'url' => '' ) ), 'ai_profile_model' => 'm', 'seo_organization_name' => 'PRINCIPAL', 'perf_ga4_id' => 'G-PRINCIPAL', 'seo_twitter_user' => '@red' ) );
$GLOBALS['wp']['site_options']['digitalisimo_seo_network_secrets_cleaned'] = array( 'time' => 1, 'removed' => array() ); // la limpieza anterior ya pasó por esta red
$sites_before = $GLOBALS['wp']['options'];
digi_seo_mode( 'network' ); Digitalisimo_Integrations_Settings::clean_network_secrets();
$net = get_site_option( OPT ); $log = get_site_option( Digitalisimo_Integrations_Settings::NETWORK_CLEANED );
check( array( 'openai_api_key', 'ai_providers', 'ai_profile_model' ) === $log['removed'] && ! isset( $net['openai_api_key'], $net['ai_providers'], $net['ai_profile_model'] ), 'Sólo salen solas las claves secretas de IA, aunque ya no coincidan con las del principal, también donde corrió la limpieza anterior' );
check( false === strpos( json_encode( $log ), 'sk-' ), 'El registro de la limpieza no guarda secretos' );
foreach ( array( 'enable_seo' => 1, 'seo_hide_login_slug' => 'acceso-red', 'seo_local_hours' => 'Mo-Fr 09:00-18:00', 'content_editorial_voice' => 'Voz de la red', 'namecheap_api_user' => 'cuenta', 'openai_model' => 'gpt-x' ) as $key => $value ) check( $value === $net[ $key ], "La limpieza conserva en la red $key" );
check( $sites_before === $GLOBALS['wp']['options'], 'La limpieza no toca ningún sitio' );
digi_seo_mode( 'site' );
check( '1' === (string) Digitalisimo_Integrations_SEO_Resolver::option( 'enable_seo' ) && 'acceso-red' === Digitalisimo_Integrations_SEO_Resolver::option( 'seo_hide_login_slug' ), 'Sin Tools, el sitio 2 conserva la ruta privada de acceso y su SEO activo (caso de la sonda)' );
digi_seo_mode( 'network' );
$review = function () { return digi_seo_render( array( 'Digitalisimo_Integrations_SEO_Suite', 'network_page' ), array( 'page' => 'digitalisimo-network-seo' ) ); };
$html = $review();
preg_match_all( '#name="keys\[\]" value="([^"]+)"#', $html, $m ); $listed = $m[1]; sort( $listed );
check( array( 'content_editorial_voice', 'enable_seo', 'openai_model', 'perf_ga4_id', 'seo_local_hours', 'seo_organization_name' ) === $listed, 'Red → SEO lista lo que coincide con el principal y lo que la red ya no edita, sin Hosting ni Tools sin activar: ' . implode( ', ', $listed ) );
check( false !== strpos( $html, 'los sitios que lo heredan usarán su propio valor guardado' ) && false === strpos( $html, 'Deshacer' ), 'El aviso explica qué pasa en los sitios al quitar un valor' );
$send = function ( array $fill, $button ) use ( $review ) {
	$form = digi_browser_form( $review(), $fill, 'polluted', 'digitalisimo_seo_network_polluted' );
	$_POST = $_REQUEST = $form['post']; $_POST['polluted'] = $_REQUEST['polluted'] = $button;
	try { do_action( 'network_admin_edit_digitalisimo_seo_network_polluted' ); } catch ( Digi_Test_Redirect $r ) {} $_POST = $_REQUEST = array();
};
$send( array( 'keys[]' => 'enable_seo' ), 'remove' );
$net = get_site_option( OPT ); $removed = get_site_option( Digitalisimo_Integrations_SEO_Suite::NETWORK_REMOVED );
check( ! isset( $net['enable_seo'] ) && 'PRINCIPAL' === $net['seo_organization_name'] && 'acceso-red' === $net['seo_hide_login_slug'] && array( 'enable_seo' => 1 ) === $removed['values'] && $sites_before === $GLOBALS['wp']['options'], '«Quitar seleccionadas» quita sólo lo marcado, guarda su valor y no toca los sitios' );
digi_seo_mode( 'site' ); check( '0' === (string) Digitalisimo_Integrations_SEO_Resolver::option( 'enable_seo' ), 'Quitado de la red, el sitio usa su propio valor guardado' ); digi_seo_mode( 'network' );
$html = $review();
check( false !== strpos( $html, 'Deshacer lo quitado (enable_seo)' ), 'Mientras hay registro, el aviso ofrece «Deshacer»' );
$send( array(), 'undo' );
check( 1 === get_site_option( OPT )['enable_seo'] && false === get_site_option( Digitalisimo_Integrations_SEO_Suite::NETWORK_REMOVED ), '«Deshacer» devuelve a la red lo quitado y borra el registro' );
$send( array( 'keys[]' => 'perf_ga4_id' ), 'keep' );
preg_match_all( '#name="keys\[\]" value="([^"]+)"#', $review(), $m );
check( ! in_array( 'perf_ga4_id', $m[1], true ) && 'G-PRINCIPAL' === get_site_option( OPT )['perf_ga4_id'], '«Conservar seleccionadas» lo deja en la red y deja de listarlo' );
$GLOBALS['wp']['site_options'][ OPT ]['openai_api_key'] = 'sk-OTRA'; Digitalisimo_Integrations_Settings::clean_network_secrets();
check( 'sk-OTRA' === get_site_option( OPT )['openai_api_key'], 'La limpieza corre una sola vez por red' );

echo "SEO, paridad sitio/red de los ajustes: $checks comprobaciones correctas (" . count( screens() ) . " pantallas).\n";
