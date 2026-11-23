<?php
/**
 * DIGITALÍSIMO Elements · enlace firmado de `create-preview-link` en una red: sólo vale en el sitio que lo emitió (revisión
 * 2026-11-22). El token del núcleo firma página, revisión y caducidad con la sal de toda la red, sin el sitio: en otro
 * sitio con la misma página y la misma revisión (un clon, un kit importado igual) el enlace pintaba su contenido sin
 * sesión. `Preview_Link` marca la revisión con el sitio al emitir el enlace y, antes del manejador del núcleo, quita de
 * la petición un enlace de otro sitio. Usa el `Preview_Token` real del núcleo incluido.
 */
namespace {
	error_reporting( E_ALL );
	define( 'ABSPATH', __DIR__ . '/' );
	$checks = 0;
	function check( $ok, $m ) { global $checks; if ( ! $ok ) { fwrite( STDERR, "FALLA: $m\n" ); exit( 1 ); } $checks++; }
	$GLOBALS['ps'] = array( 'multisite' => true, 'blog' => 1, 'posts' => array(), 'meta' => array(), 'filters' => array(), 'actions' => array() );
	function is_multisite() { return $GLOBALS['ps']['multisite']; }
	function get_current_blog_id() { return $GLOBALS['ps']['blog']; }
	function wp_salt( $s ) { return 'sal-de-toda-la-red'; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function sanitize_text_field( $t ) { return trim( (string) $t ); }
	function wp_unslash( $v ) { return $v; }
	function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['ps']['actions'][ $h ][ $p ][] = $cb; return true; }
	function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['ps']['filters'][ $h ][ $p ][] = $cb; return true; }
	function get_post( $id ) { return $GLOBALS['ps']['posts'][ get_current_blog_id() ][ (int) $id ] ?? null; }
	function get_metadata( $type, $id, $key, $single = false ) { $l = $GLOBALS['ps']['meta'][ get_current_blog_id() ][ (int) $id ][ $key ] ?? array(); return $single ? ( $l[0] ?? '' ) : $l; }
	function add_metadata( $type, $id, $key, $value ) { $GLOBALS['ps']['meta'][ get_current_blog_id() ][ (int) $id ][ $key ][] = $value; return true; }
	function delete_metadata( $type, $id, $key, $value = '' ) { $l = &$GLOBALS['ps']['meta'][ get_current_blog_id() ][ (int) $id ][ $key ]; $l = array_values( array_diff( (array) $l, array( $value ) ) ); return true; }
	require __DIR__ . '/../digitalisimo-elements/elementor/modules/mcp/preview/preview-token.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-mcp/class-css-sync.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-mcp/class-preview-link.php';
	use Elementor\Modules\Mcp\Preview\Preview_Token;
	use Digitalisimo\Elements\Mcp\Preview_Link;

	// Sitios 1 y 2 con la misma página 90 y la misma revisión 91 (un clon).
	foreach ( array( 1, 2 ) as $blog ) {
		$GLOBALS['ps']['posts'][ $blog ][90] = (object) array( 'ID' => 90, 'post_type' => 'page', 'post_parent' => 0 );
		$GLOBALS['ps']['posts'][ $blog ][91] = (object) array( 'ID' => 91, 'post_type' => 'revision', 'post_parent' => 90 );
	}
	Preview_Link::init();
	check( isset( $GLOBALS['ps']['actions']['parse_request'][ Preview_Link::SITE_PRIORITY ] ) && Preview_Link::SITE_PRIORITY < 1, 'El candado de sitio va antes del manejador del núcleo (parse_request 1)' );

	// La herramienta emite el enlace en el sitio 1: la revisión queda marcada con ese sitio.
	$exp_a = time() + 300; $exp_b = time() + 290;
	$GLOBALS['ps']['next_exp'] = $exp_a;
	$args = array( 'execute_callback' => static function () { return array( 'url' => 'x', 'post_id' => 90, 'revision_id' => 91, 'expires_at_unix' => $GLOBALS['ps']['next_exp'] ); } );
	$wrapped = Preview_Link::wrap_ability( $args, 'elementor/create-preview-link' );
	check( $args === Preview_Link::wrap_ability( $args, 'elementor/create-page' ), 'Otras herramientas no se tocan' );
	$out = call_user_func( $wrapped['execute_callback'], array( 'post_id' => 90 ) );
	check( 91 === $out['revision_id'] && array( '1:' . $exp_a ) === get_metadata( 'post', 91, Preview_Link::SITE_META ), 'Emitir el enlace marca la revisión con el sitio que lo emitió y su caducidad' );
	$token = Preview_Token::encode( 90, 91, $exp_a, Preview_Token::secret() );

	// En el sitio 1 vale; en el sitio 2 (misma página y revisión, sin la marca) no.
	check( 90 === Preview_Link::post_for( $token ), 'Sitio 1: el enlace vale' );
	$_GET = $_REQUEST = array( 'elementor_preview_token' => $token );
	Preview_Link::site_guard();
	check( isset( $_GET['elementor_preview_token'] ), 'Sitio 1: el candado deja pasar el enlace' );
	$GLOBALS['ps']['blog'] = 2;
	check( 0 === Preview_Link::post_for( $token ), 'Sitio 2: el enlace del sitio 1 no vale' );
	Preview_Link::site_guard();
	check( ! isset( $_GET['elementor_preview_token'] ) && ! isset( $_REQUEST['elementor_preview_token'] ), 'Sitio 2: el candado quita el enlace antes de que lo vea el núcleo' );
	// Un clon que copió también la marca del sitio 1 tampoco lo acepta.
	add_metadata( 'post', 91, Preview_Link::SITE_META, '1:' . $exp_a );
	check( 0 === Preview_Link::post_for( $token ), 'Sitio 2: con la marca copiada del sitio 1, tampoco' );
	// El sitio 2 emite su propio enlace de la misma página y revisión: el suyo vale ahí, el del sitio 1 sigue sin valer.
	$GLOBALS['ps']['next_exp'] = $exp_b;
	call_user_func( $wrapped['execute_callback'], array( 'post_id' => 90 ) );
	check( 90 === Preview_Link::post_for( Preview_Token::encode( 90, 91, $exp_b, Preview_Token::secret() ) ), 'Sitio 2: su propio enlace vale' );
	check( 0 === Preview_Link::post_for( $token ), 'Sitio 2: el del sitio 1 sigue sin valer aunque el sitio 2 emitiera uno de la misma revisión' );
	$_GET = $_REQUEST = array( 'elementor_preview_token' => $token );
	Preview_Link::site_guard();
	check( ! isset( $_GET['elementor_preview_token'] ), 'Sitio 2: el candado también lo quita' );
	// Las marcas vencidas de este sitio se limpian al emitir otra.
	$old = '2:' . ( time() - 10 );
	add_metadata( 'post', 91, Preview_Link::SITE_META, $old );
	call_user_func( $wrapped['execute_callback'], array( 'post_id' => 90 ) );
	check( ! in_array( $old, get_metadata( 'post', 91, Preview_Link::SITE_META ), true ), 'Las marcas vencidas del sitio se limpian' );
	// Un token falso o vencido no lo toca el candado (lo rechaza el núcleo).
	$_GET = array( 'elementor_preview_token' => 'falso.token' );
	Preview_Link::site_guard();
	check( isset( $_GET['elementor_preview_token'] ), 'Un token inválido lo deja al núcleo' );

	// Sitio individual: sin marca y sin candado, como siempre.
	$GLOBALS['ps'] = array_merge( $GLOBALS['ps'], array( 'multisite' => false, 'blog' => 1, 'meta' => array() ) );
	call_user_func( $wrapped['execute_callback'], array() );
	check( array() === $GLOBALS['ps']['meta'] && 90 === Preview_Link::post_for( $token ), 'Sitio individual: no marca nada y el enlace vale' );
	$_GET = array( 'elementor_preview_token' => $token );
	Preview_Link::site_guard();
	check( isset( $_GET['elementor_preview_token'] ), 'Sitio individual: el candado no actúa' );

	echo "Elements, enlace firmado de vista previa ligado a su sitio: $checks comprobaciones correctas.\n";
}
