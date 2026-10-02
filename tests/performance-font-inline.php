<?php
define( 'ABSPATH', __DIR__ );
$GLOBALS['blog'] = 1;
$GLOBALS['sites'] = array();
$GLOBALS['root'] = sys_get_temp_dir() . '/digitalisimo-font-inline-' . bin2hex( random_bytes( 5 ) );
$GLOBALS['head'] = true;
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['root'] . '/site-' . $GLOBALS['blog'], 'baseurl' => 'https://red.test/site-' . $GLOBALS['blog'] . '/uploads' ); }
function get_option( $key, $default = false ) { return $GLOBALS['sites'][ $GLOBALS['blog'] ]['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['sites'][ $GLOBALS['blog'] ]['options'][ $key ] = $value; return true; }
function doing_action( $hook ) { return 'wp_head' === $hook && $GLOBALS['head']; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES ); }
class Digitalisimo_Integrations_Performance_Manager { public static function frontend_safe() { return true; } }
class Digitalisimo_Integrations_Performance_Font_Guard { public static $rows = array(); public static function manifest() { return array( 'rows' => self::$rows ); } }
require __DIR__ . '/../digitalisimo-seo/includes/performance/class-performance-font-inline.php';
$inline = 'Digitalisimo_Integrations_Performance_Font_Inline';
$check = function( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); };
$face = function( $family, $file ) { return "/* latin */ @font-face { font-family: '$family'; font-style: normal; font-weight: 400; font-display: swap; src: url(../fonts/$file.woff2) format('woff2'); unicode-range: U+0000-00FF; }"; };
$uploads = wp_upload_dir();
$css = $face( 'Familia A', 'a' ) . $face( 'Familia A', 'a' );
$compiled = $inline::compile( $css, $uploads );
$check( 1 === $compiled['count'] && 1 === substr_count( $compiled['css'], '@font-face' ) && false !== strpos( $compiled['css'], 'https://red.test/site-1/uploads/elementor/google-fonts/fonts/a.woff2' ), 'Debe resolver WOFF2 local y eliminar reglas idénticas.' );
$check( false === $inline::compile( $css . 'h1{color:red}', $uploads ), 'Nunca retira hojas que contienen estilos estructurales.' );
$check( false === $inline::compile( str_replace( '../fonts/a.woff2', 'https://other.test/a.woff2', $css ), $uploads ), 'Nunca acepta fuentes de otro sitio.' );
$check( false === $inline::compile( $css . '</style>', $uploads ), 'Nunca inserta HTML dentro de style.' );
$check( false === $inline::compile( '@import url(https://other.test/a.css);' . $css, $uploads ), 'Nunca inserta importaciones.' );
function prepare_site( $id, $source, $guard_copy = false ) {
	$GLOBALS['blog'] = $id;
	$uploads = wp_upload_dir();
	$dir = $uploads['basedir'] . '/elementor/google-fonts/css';
	if ( ! is_dir( $dir ) ) mkdir( $dir, 0777, true );
	file_put_contents( $dir . '/sourceserif4.css', $source );
	file_put_contents( $dir . '/inter.css', preg_replace( '/Familia [A-Z]/', 'Familia B', $source ) );
	if ( $guard_copy ) {
		$name = 'digitalisimo-1234567890abcdef-inter.css';
		file_put_contents( $dir . '/' . $name, preg_replace( '/Familia [A-Z]/', 'Familia B', $source ) );
		Digitalisimo_Integrations_Performance_Font_Guard::$rows = array( 'inter' => array( 'valid' => true, 'generated' => $dir . '/' . $name, 'url' => $uploads['baseurl'] . '/elementor/google-fonts/css/' . $name ) );
	} else Digitalisimo_Integrations_Performance_Font_Guard::$rows = array();
}
prepare_site( 1, $face( 'Familia A', 'a' ), true );
$manifest = $inline::rebuild();
$check( 1 === $manifest['blog_id'] && 3 === count( $manifest['rows'] ), 'Debe conservar original y copia aprobada del sitio.' );
$url = wp_upload_dir()['baseurl'] . '/elementor/google-fonts/css/sourceserif4.css';
$tag = '<link rel=\'stylesheet\' id=\'elementor-gf-local-sourceserif4-css\' href=\'' . $url . '\' media=\'all\' />';
$result = $inline::replace_tag( $tag, 'elementor-gf-local-sourceserif4', $url . '?ver=1', 'all' );
$check( 1 === substr_count( $result, '@font-face' ) && false === strpos( $result, '<link' ), 'Inline válido debe sustituir exactamente el enlace.' );
$check( $tag === $inline::replace_tag( $tag, 'elementor-post-468', $url, 'all' ), 'Otros CSS de Elementor quedan intactos.' );
$GLOBALS['head'] = false;
$check( $tag === $inline::replace_tag( $tag, 'elementor-gf-local-sourceserif4', $url, 'all' ), 'Fuera de head se conserva el enlace.' );
$GLOBALS['head'] = true;
$copy_url = wp_upload_dir()['baseurl'] . '/elementor/google-fonts/css/digitalisimo-1234567890abcdef-inter.css';
$copy_tag = "<link rel='stylesheet' href='$copy_url' media='all' />";
$check( false !== strpos( $inline::replace_tag( $copy_tag, 'elementor-gf-local-inter', $copy_url, 'all' ), '@font-face' ), 'La copia filtrada también debe poder inlinarse.' );
prepare_site( 2, $face( 'Familia C', 'c' ) );
$second = $inline::rebuild();
$check( 2 === $second['blog_id'] && false !== strpos( $second['rows'][ wp_upload_dir()['baseurl'] . '/elementor/google-fonts/css/inter.css' ]['css'], '/site-2/' ), 'El manifiesto usa fuentes y URLs del sitio actual.' );
$GLOBALS['blog'] = 1;
$check( 1 === get_option( $inline::OPTION )['blog_id'], 'El manifiesto del primer sitio permanece aislado.' );
file_put_contents( wp_upload_dir()['basedir'] . '/elementor/google-fonts/css/sourceserif4.css', $face( 'Familia A', 'changed' ) );
$check( $tag === $inline::replace_tag( $tag, 'elementor-gf-local-sourceserif4', $url, 'all' ), 'Un archivo modificado vuelve al enlace externo.' );
echo "Fuentes inline: reglas completas, WOFF2 local, fallbacks y aislamiento Multisite correctos.\n";
