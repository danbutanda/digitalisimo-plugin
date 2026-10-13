<?php
/**
 * Identidad DIGITALÍSIMO del constructor: textos de PHP y JavaScript con la marca propia (sólo en los dominios del
 * núcleo y sólo con el núcleo incluido), datos remotos del inicio rápido, título automático y pie de valoración.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', __DIR__ . '/../digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_ELEMENTS_VERSION', 'test' );
	function check_identity( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	$GLOBALS['hooks'] = array();
	function add_filter( $hook, $callback ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
	function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
	function did_action() { return false; }
	function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
	final class Digitalisimo_Elements_Core {
		public static $bundled = false;
		public static function bundled() { return self::$bundled; }
	}
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-identity.php';
	$id = 'Digitalisimo_Elements_Identity';

	$id::init();
	check_identity( array() === $GLOBALS['hooks'], 'Con el plugin Elementor activo no se cambia ninguna marca.' );
	Digitalisimo_Elements_Core::$bundled = true;
	$id::init();
	foreach ( array( 'gettext', 'ngettext', 'gettext_with_context', 'wp_default_scripts', 'pre_option_elementor_meta_generator_tag', 'elementor/core/admin/homescreen', 'pre_option_elementor_onboarded', 'wp_insert_post_data' ) as $hook ) {
		check_identity( ! empty( $GLOBALS['hooks'][ $hook ] ), 'Con el núcleo incluido se engancha ' . $hook . '.' );
	}

	$cases = array(
		'Edit with Elementor'                             => 'Edit with DIGITALÍSIMO Elements',
		'Editar con Elementor'                            => 'Editar con DIGITALÍSIMO Elements',
		'Elementor Pro'                                   => 'DIGITALÍSIMO Elements',
		'Connect to Elementor One'                        => 'Connect to DIGITALÍSIMO Elements',
		'Generar con la IA de Elementor'                  => 'Generar con la IA',
		'Generate with Elementor AI'                      => 'Generate with AI',
		"Checking this box will disable Elementor's Default Colors" => "Checking this box will disable DIGITALÍSIMO Elements' Default Colors",
		'Visita https://elementor.com/help'               => 'Visita https://elementor.com/help',
		'Sin marca'                                       => 'Sin marca',
		'<a class="elementor-button">Elementor</a>'       => '<a class="elementor-button">DIGITALÍSIMO Elements</a>',
	);
	foreach ( $cases as $in => $out ) {
		check_identity( $out === $id::brand( $in ), 'Regla de marca: «' . $in . '» → «' . $id::brand( $in ) . '».' );
	}
	check_identity( 'Editar con DIGITALÍSIMO Elements' === $id::gettext( 'Editar con Elementor', 'Edit with Elementor', 'elementor' ) && 'Elementor Addons' === $id::gettext( 'Elementor Addons', 'Elementor Addons', 'otro-plugin' ) && 'DIGITALÍSIMO Elements' === $id::ngettext( 'Elementor', 'Elementor', 'Elementors', 1, 'elementor-pro' ), 'Sólo los textos del núcleo y del derivado de Pro cambian.' );

	// Las reglas de JavaScript son las mismas y se aplican a los filtros i18n de wp.hooks de los dos dominios.
	$scripts = new class() { public $inline = ''; public function add_inline_script( $handle, $js ) { $this->inline = $handle . '|' . $js; } };
	$id::script_filters( $scripts );
	check_identity( 0 === strpos( $scripts->inline, 'wp-i18n|' ) && false !== strpos( $scripts->inline, '"i18n.gettext_"' ) && false !== strpos( $scripts->inline, '"elementor-pro"' ) && false !== strpos( $scripts->inline, json_encode( $id::RULES, JSON_UNESCAPED_UNICODE ) ), 'Los scripts reciben las mismas reglas por wp.hooks.' );

	$home = array( 'home-screen' => array(
		'top_with_licences'          => array( array( 'license' => array( 'pro' ), 'title' => 'Unleash your imagination with Elementor', 'button_watch_url' => 'https://www.youtube.com/watch?v=x', 'youtube_embed_id' => 'x' ) ),
		'get_started'                => array( array( 'license' => array( 'pro' ), 'repeater' => array( array( 'title' => 'Site Settings', 'image' => 'https://assets.elementor.com/i/site-settings.svg' ) ) ) ),
		'add_ons'                    => array( 'hide_section' => false, 'repeater' => array( array( 'title' => 'Image Optimizer' ) ) ),
		'sidebar_promotion_variants' => array( array( 'is_enabled' => true, 'type' => 'default' ) ),
		'external_links'             => array( array( 'label' => 'Blog', 'url' => 'https://elementor.com/blog' ) ),
	) );
	$home = $id::home_data( $home )['home-screen'];
	check_identity( 'Unleash your imagination with DIGITALÍSIMO Elements' === $home['top_with_licences'][0]['title'] && '' === $home['top_with_licences'][0]['youtube_embed_id'] && '' === $home['top_with_licences'][0]['button_watch_url'], 'El inicio rápido lleva la marca propia y sin el vídeo del proveedor.' );
	check_identity( true === $home['add_ons']['hide_section'] && array() === $home['add_ons']['repeater'] && false === $home['sidebar_promotion_variants'][0]['is_enabled'] && 2 === count( $home['external_links'] ) && 'Código y actualizaciones' === $home['external_links'][1]['label'], 'Sin promociones ni complementos; los enlaces del proveedor pasan a los propios.' );
	check_identity( 'https://assets.elementor.com/i/site-settings.svg' === $home['get_started'][0]['repeater'][0]['image'] && array( 'pro' ) === $home['get_started'][0]['license'], 'Los accesos y sus imágenes se conservan.' );

	check_identity( 'DIGITALÍSIMO Elements #42' === $id::default_title( array( 'post_title' => 'Elementor #42' ) )['post_title'] && 'Mi Elementor #42' === $id::default_title( array( 'post_title' => 'Mi Elementor #42' ) )['post_title'], 'Sólo el título automático exacto cambia.' );
	$flat = $id::home_data( array( 'top_with_licences' => array( array( 'title' => 'Elementor', 'youtube_embed_id' => 'y' ) ), 'external_links' => array( array( 'url' => 'https://x' ) ) ) );
	check_identity( '' === $flat['top_with_licences'][0]['youtube_embed_id'] && 2 === count( $flat['external_links'] ) && 'DIGITALÍSIMO Elements' === $flat['top_with_licences'][0]['title'], 'Los datos sin envoltorio también se filtran.' );
	check_identity( '' === $id::footer_text( 'Enjoyed Elementor? <a href="https://go.elementor.com/admin-review/">★★★★★</a>' ) && '' === $id::footer_text( 'Enjoyed Elementor? <a href="https://wordpress.org/support/plugin/elementor/reviews/">★★★★★</a>' ) && 'Gracias por crear con WordPress.' === $id::footer_text( 'Gracias por crear con WordPress.' ), 'El pie que pide valorar el núcleo desaparece; el de WordPress queda.' );
	check_identity( '1' === $id::no_generator() && '1' === $id::onboarded(), 'Sin meta generator ni asistente de bienvenida del núcleo.' );
	echo "DIGITALÍSIMO Elements: identidad propia del constructor validada.\n";
}
