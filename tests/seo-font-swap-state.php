<?php
/**
 * Estado efectivo de font-display bajo la casilla perf_font_swap: en el sitio, el valor y su origen
 * (esta casilla, la fila de los ajustes del constructor, el valor por defecto de DIGITALÍSIMO Elements
 * o «auto»); en la red, una nota fija. Sin Elements, «auto» o el valor guardado.
 */
define( 'ABSPATH', __DIR__ );
$GLOBALS['multisite']    = false;
$GLOBALS['site_options'] = array();
$GLOBALS['network']      = array();
$GLOBALS['rows']         = array();
$GLOBALS['filters']      = array();
function is_multisite() { return $GLOBALS['multisite']; }
function get_current_blog_id() { return 1; }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $key, $default = false ) { return $GLOBALS['site_options'][ $key ] ?? $default; }
function get_site_option( $key, $default = false ) { return $GLOBALS['network'][ $key ] ?? $default; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $value ) { return esc_html( $value ); }
function checked( $actual, $expected, $echo = true ) { return (string) $actual === (string) $expected ? 'checked="checked"' : ''; }
function selected( $actual, $expected, $echo = true ) { return (string) $actual === (string) $expected ? 'selected="selected"' : ''; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][ $hook ][] = $callback; return true; }
function has_filter( $hook ) { return ! empty( $GLOBALS['filters'][ $hook ] ); }
function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $callback ) $value = $callback( $value, ...$args ); return $value; }
/** Sólo lecturas de una fila de opciones, preparadas. */
class Test_WPDB {
	public $options = 'wp_options';
	public $queries = array();
	public function prepare( $query, ...$args ) {
		if ( 1 !== substr_count( $query, '%s' ) || array( 'elementor_font_display' ) !== $args ) throw new RuntimeException( 'La fila debe leerse con una consulta preparada por su nombre.' );
		return str_replace( '%s', "'" . addslashes( $args[0] ) . "'", $query );
	}
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( ! preg_match( "/^SELECT option_value FROM wp_options WHERE option_name = '([a-z_]+)' LIMIT 1$/", $sql, $match ) ) throw new RuntimeException( 'Consulta inesperada: ' . $sql );
		return array_key_exists( $match[1], $GLOBALS['rows'] ) ? (string) $GLOBALS['rows'][ $match[1] ] : null;
	}
}
$GLOBALS['wpdb'] = new Test_WPDB();
require __DIR__ . '/../digitalisimo-seo/includes/class-settings.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-resolver.php';
require __DIR__ . '/../digitalisimo-seo/includes/class-seo-suite.php';
$suite = Digitalisimo_Integrations_SEO_Suite::class;
$key   = Digitalisimo_Integrations_Settings::OPTION;

$site = function() use ( $suite ) { ob_start(); $suite::performance_site_fields(); return ob_get_clean(); };
$net  = function() use ( $suite ) { ob_start(); $suite::performance_network_fields(); return ob_get_clean(); };
$row  = function( $html ) {
	if ( 1 !== preg_match_all( '~<tr class="digitalisimo-font-display-state">.*?</tr>~s', $html, $match ) ) throw new RuntimeException( 'Debe haber una sola fila de estado de font-display.' );
	return $match[0][0];
};
$expect = function( $label, $value, $origin, $extra = null ) use ( $site, $row ) {
	$html  = $site();
	$state = $row( $html );
	$class = 'swap' === $value ? 'is-active' : 'is-inactive';
	if ( false === strpos( $state, '<span class="digitalisimo-effective-state ' . $class . '">' . $value . '</span>' ) ) throw new RuntimeException( $label . ': el valor en vigor debe ser «' . $value . '».' . "\n" . $state );
	if ( false === strpos( $state, '<span class="description">' . $origin . '</span>' ) ) throw new RuntimeException( $label . ': el origen debe ser «' . $origin . '».' . "\n" . $state );
	if ( null === $extra ? false !== strpos( $state, 'Sin esta casilla' ) : false === strpos( $state, $extra ) ) throw new RuntimeException( $label . ': ' . ( null === $extra ? 'sólo con la casilla activa se explica qué habría sin ella.' : 'falta «' . $extra . '».' ) . "\n" . $state );
	// Bajo la casilla del sitio, antes del siguiente campo, y sin campos propios (ningún `name` repetido).
	$after = strpos( $html, 'id="perf_font_swap"' );
	if ( false === $after || strpos( $html, $state ) < $after || strpos( $html, $state ) > strpos( $html, 'id="perf_css_defer"' ) ) throw new RuntimeException( $label . ': el estado va justo bajo la casilla perf_font_swap.' );
	if ( false !== strpos( $state, '<input' ) || false !== strpos( $state, 'name=' ) || false !== stripos( $state, 'elementor' ) ) throw new RuntimeException( $label . ': la fila no lleva campos ni nombra el constructor original.' );
	return $state;
};
$builder  = 'Elegido en los ajustes del constructor.';
$elements = 'Valor por defecto de DIGITALÍSIMO Elements, sólo para las fuentes de Google; se desactiva en DIGITALÍSIMO Elements → Rendimiento.';
$auto     = 'Valor por defecto del constructor: este sitio nunca lo eligió.';
$seo      = 'Lo fuerza esta casilla de DIGITALÍSIMO SEO, por encima de los ajustes del constructor.';

// Sin Elements: «auto» o el valor guardado.
$expect( 'Sin Elements ni fila', 'auto', $auto );
if ( array( 'value' => 'auto', 'origin' => 'default', 'base' => 'auto', 'base_origin' => 'default' ) !== $suite::font_display_state() ) throw new RuntimeException( 'Sin Elements ni fila, el estado es «auto» del constructor.' );
$GLOBALS['rows']['elementor_font_display'] = 'block';
$expect( 'Sin Elements con fila', 'block', $builder );
$GLOBALS['rows']['elementor_font_display'] = 'swap';
$expect( 'Sin Elements con swap guardado', 'swap', $builder );

// Con el valor por defecto de Elements (default_option_): sólo rige si la fila no existe.
unset( $GLOBALS['rows']['elementor_font_display'] );
$GLOBALS['elements_swap'] = true;
add_filter( 'default_option_elementor_font_display', function( $default, $option = '', $passed = false ) {
	if ( 'elementor_font_display' !== $option || true !== $passed ) throw new RuntimeException( 'El filtro debe recibir lo mismo que en get_option( …, \'auto\' ).' );
	return $GLOBALS['elements_swap'] ? 'swap' : $default;
}, 10, 3 );
$expect( 'Valor por defecto de Elements', 'swap', $elements );
$GLOBALS['elements_swap'] = false;
$expect( 'Elements con swap apagado', 'auto', $auto );
$GLOBALS['elements_swap'] = true;
$GLOBALS['rows']['elementor_font_display'] = 'auto';
$expect( 'La fila manda sobre el valor por defecto', 'auto', $builder );
$GLOBALS['rows']['elementor_font_display'] = '';
$expect( 'Una fila vacía también es elección del sitio', '', $builder );

// La casilla de SEO gana (pre_option_) y explica qué habría sin ella.
unset( $GLOBALS['rows']['elementor_font_display'] );
$GLOBALS['site_options'][ $key ] = array( 'perf_font_swap' => 1 );
$expect( 'SEO con Elements', 'swap', $seo, 'Sin esta casilla: swap · ' . $elements );
$GLOBALS['rows']['elementor_font_display'] = 'fallback';
$expect( 'SEO con fila', 'swap', $seo, 'Sin esta casilla: fallback · ' . $builder );
$GLOBALS['filters'] = array();
unset( $GLOBALS['rows']['elementor_font_display'] );
$expect( 'SEO sin Elements', 'swap', $seo, 'Sin esta casilla: auto · ' . $auto );

// Lo guardado se escapa.
$GLOBALS['site_options'][ $key ] = array();
$GLOBALS['rows']['elementor_font_display'] = '"><script>alert(1)</script>';
$state = $row( $site() );
if ( false !== strpos( $state, '<script>' ) || false === strpos( $state, '&quot;&gt;&lt;script&gt;' ) ) throw new RuntimeException( 'El valor guardado debe escaparse.' );
unset( $GLOBALS['rows']['elementor_font_display'] );

// Multisite: el sitio lee la casilla por el resolver (herencia incluida) y conserva «Heredar de la red».
$GLOBALS['multisite'] = true;
$GLOBALS['network'][ $key ] = array( 'perf_font_swap' => 1 );
$html = $site();
if ( ! preg_match( '/data-field="perf_font_swap"[^>]*checked="checked"> Heredar de la red<\/label>/', $html ) ) throw new RuntimeException( 'La casilla del sitio conserva «Heredar de la red».' );
$expect( 'Heredado de la red', 'swap', $seo, 'Sin esta casilla: auto · ' . $auto );
$GLOBALS['site_options']['digitalisimo_seo_network_inherit'] = array( 'perf_font_swap' => 0 );
$GLOBALS['site_options'][ $key ] = array( 'perf_font_swap' => 0 );
$expect( 'El sitio personaliza la casilla', 'auto', $auto );

// Red: una nota fija, sin estado de un sitio, sin consultas y sin «Heredar de la red».
$GLOBALS['wpdb']->queries = array();
$GLOBALS['rows']['elementor_font_display'] = 'block';
add_filter( 'default_option_elementor_font_display', function() { return 'swap'; } );
$network_html = $net();
$note    = $row( $network_html );
$GLOBALS['rows'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['network'][ $key ] = array();
if ( $note !== $row( $net() ) ) throw new RuntimeException( 'La nota de red es fija: no depende de ningún sitio.' );
if ( $GLOBALS['wpdb']->queries ) throw new RuntimeException( 'La red no consulta la fila de ningún sitio.' );
foreach ( array( 'Con esta casilla apagada, cada sitio usa el font-display que eligió en los ajustes del constructor', 'el valor por defecto de DIGITALÍSIMO Elements (swap, sólo para las fuentes de Google; se desactiva en DIGITALÍSIMO Elements → Rendimiento) o, sin él, auto', 'Activada, aplica swap en los sitios que la heredan, por encima de los ajustes del constructor', 'Cada sitio muestra bajo esta casilla su valor en vigor y su origen' ) as $text ) {
	if ( false === strpos( $note, $text ) ) throw new RuntimeException( 'Falta en la nota de red: «' . $text . '».' );
}
$position = strpos( $network_html, $note );
if ( $position < strpos( $network_html, 'id="network_perf_font_swap"' ) || $position > strpos( $network_html, 'id="network_perf_css_defer"' ) ) throw new RuntimeException( 'La nota de red va justo bajo su casilla perf_font_swap.' );
if ( false !== strpos( $network_html, '> Heredar de la red</label>' ) || false !== strpos( $network_html, 'digitalisimo-network-inherit' ) || false !== strpos( $note, 'digitalisimo-effective-state' ) || false !== strpos( $note, '<input' ) || false !== stripos( $note, 'elementor' ) ) throw new RuntimeException( 'La red no muestra «Heredar de la red», ni un estado de sitio, ni campos, ni el nombre del constructor original.' );

// Sitio único: sin herencia, el mismo estado.
$GLOBALS['multisite'] = false;
$GLOBALS['site_options'] = array();
$html = $site();
if ( false !== strpos( $html, '> Heredar de la red</label>' ) ) throw new RuntimeException( 'Un sitio único no muestra «Heredar de la red».' );
$expect( 'Sitio único', 'auto', $auto );
echo "Estado de font-display: casilla de SEO, ajustes del constructor, valor por defecto de Elements y «auto» en el sitio; nota fija en la red.\n";
