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

	// El inicio rápido es propio: con o sin datos remotos (que ya no se piden), el mismo contenido local.
	$remote = array(
		'top_with_licences'          => array( array( 'license' => array( 'pro' ), 'title' => 'Unleash your imagination with Elementor', 'button_watch_url' => 'https://www.youtube.com/watch?v=x', 'youtube_embed_id' => 'x' ) ),
		'get_started'                => array( array( 'license' => array( 'pro' ), 'repeater' => array( array( 'title' => 'Site Settings', 'image' => 'https://assets.elementor.com/i/site-settings.svg' ) ) ) ),
		'add_ons'                    => array( 'hide_section' => false, 'repeater' => array( array( 'title' => 'Image Optimizer' ) ) ),
		'sidebar_promotion_variants' => array( array( 'is_enabled' => true, 'type' => 'default' ) ),
		'external_links'             => array( array( 'label' => 'Blog', 'url' => 'https://elementor.com/blog' ) ),
	);
	foreach ( array( $remote, array(), array( 'home-screen' => $remote ) ) as $input ) {
		$home = $id::home_data( $input );
		// Sin la clave, el componente no pinta el bloque superior (con la clave vacía sí lo pintaría, con el vídeo).
		check_identity( ! array_key_exists( 'top_with_licences', $home ) && ! array_key_exists( 'add_ons', $home ) && ! array_key_exists( 'sidebar_promotion_variants', $home ), 'El inicio rápido no tiene el bloque del vídeo, complementos ni promociones.' );
		$json = json_encode( $home );
		check_identity( false === stripos( $json, 'elementor.com' ) && false === stripos( $json, 'youtube' ) && false === stripos( $json, 'github.com' ), 'Ningún dato del inicio rápido apunta a otra empresa.' );
		check_identity( array( 'free', 'pro', 'one' ) === array_map( static function ( $g ) { return $g['license'][0]; }, $home['get_started'] ) && 8 === count( $home['get_started'][0]['repeater'] ) && 'Ajustes del sitio' === $home['get_started'][0]['repeater'][0]['title'], 'Los accesos propios se muestran con cualquier nivel de licencia.' );
		check_identity( 1 === count( $home['external_links'] ) && 'Acerca de DIGITALÍSIMO Elements' === $home['external_links'][0]['label'], 'Sólo el enlace a la página propia.' );
	}

	check_identity( 'DIGITALÍSIMO Elements #42' === $id::default_title( array( 'post_title' => 'Elementor #42' ) )['post_title'] && 'Mi Elementor #42' === $id::default_title( array( 'post_title' => 'Mi Elementor #42' ) )['post_title'], 'Sólo el título automático exacto cambia.' );
	check_identity( '' === $id::footer_text( 'Enjoyed Elementor? <a href="https://go.elementor.com/admin-review/">★★★★★</a>' ) && '' === $id::footer_text( 'Enjoyed Elementor? <a href="https://wordpress.org/support/plugin/elementor/reviews/">★★★★★</a>' ) && 'Gracias por crear con WordPress.' === $id::footer_text( 'Gracias por crear con WordPress.' ), 'El pie que pide valorar el núcleo desaparece; el de WordPress queda.' );
	check_identity( '1' === $id::no_generator() && '1' === $id::onboarded(), 'Sin meta generator ni asistente de bienvenida del núcleo.' );

	// Menú «My account» de la barra superior (conexión, planes y cuenta del proveedor, «Have an … plan?»): su botón no se
	// pinta y, si la ventana llega a abrirse, tampoco se ve. Las marcas siguen en el bundle del núcleo (las dos formas).
	$css = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/assets/digitalisimo/identity.css' );
	check_identity( false !== strpos( $css, '#editor-one-top-bar [data-test="header-user-button"]' ) && false !== strpos( $css, '[data-test="user-profile-menu"] .MuiPaper-root' ) && false !== strpos( $css, '[data-test^="user-menu-"]' ), 'identity.css oculta el botón y la ventana «My account» de la barra superior.' );
	foreach ( array( 'editor-one-top-bar.js', 'editor-one-top-bar.min.js' ) as $bundle ) {
		$code = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/elementor/assets/js/' . $bundle );
		foreach ( array( 'header-user-button', 'user-profile-menu', 'user-menu-connect' ) as $mark ) {
			check_identity( 1 === preg_match( '/"data-test":\s*["`]' . $mark . '["`]/', $code ), 'El núcleo ya no marca «' . $mark . '» en ' . $bundle . ': revisar identity.css.' );
		}
	}
	// Importar y exportar kits en la App: el logotipo SVG en línea de la cabecera (reconocido por su trazo) lleva el
	// símbolo propio; el «Learn more» sin destino al importar y «Save to library» (nube del proveedor) no se pintan.
	check_identity( false !== strpos( $css, '#e-app svg:has( > path[d^="M2.69648 24.8891"] )' ) && false !== strpos( $css, '#e-app svg > path[d^="M2.69648 24.8891"]' ) && false !== strpos( $css, '#e-app [data-testid="import-learn-more-link"]' ) && false !== strpos( $css, '#e-app [data-testid="export-kit-footer-save-to-library-button"]' ), 'identity.css cubre la cabecera, el «Learn more» y «Save to library» (nube del proveedor) de importar y exportar kits.' );
	foreach ( array( 'app.js', 'app.min.js' ) as $bundle ) {
		$code = (string) file_get_contents( __DIR__ . '/../digitalisimo-elements/elementor/assets/js/' . $bundle );
		check_identity( false !== strpos( $code, 'M2.69648 24.8891' ) && 1 === preg_match( '/"data-testid":\s*["`]import-learn-more-link["`]/', $code ) && 1 === preg_match( '/"data-testid":\s*["`]export-kit-footer-save-to-library-button["`]/', $code ), 'El núcleo cambió el logotipo, el «Learn more» o «Save to library» de importar y exportar kits en ' . $bundle . ': revisar identity.css.' );
	}
	echo "DIGITALÍSIMO Elements: identidad propia del constructor validada.\n";
}
