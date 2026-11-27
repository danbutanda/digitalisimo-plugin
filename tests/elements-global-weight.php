<?php
/**
 * Aviso de plantillas globales pesadas (`Front\Global_Weight`, B1 de la auditoría de digitalisimo.lat del 2026-11-24:
 * el pie 9353 con el contenedor 13280 metía unas 1 350 palabras y ocho H2 en cada URL). Sin WordPress:
 *   G1  la medida del HTML: palabras visibles, H1 y H2 (sin scripts, estilos, SVG ni comentarios);
 *   G2  qué es global: la caché de condiciones del Theme Builder (todo el sitio, todas las entradas o todos los
 *       archivos), sólo publicadas;
 *   G3  el cálculo con lo que se incrusta, los motivos, los umbrales filtrables y la opción sin carga automática;
 *   G4  cuándo: al guardar una plantilla, cambiar condiciones, borrar o restaurar, importar un kit o con la
 *       administración (cálculo viejo), siempre en un evento de WP-Cron; nunca en el frente;
 *   G5  el editor: aviso fijo al abrir una plantilla pesada o una que ella incrusta, escapado; nada en las ligeras;
 *   G6  la nota del «Análisis SEO» (no cambia la puntuación) y la sección de «Rendimiento» con «Analizar ahora».
 */
namespace {
	require_once __DIR__ . '/fixtures/php74.php';
	define( 'ABSPATH', __DIR__ . '/' );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message . ' en ' . basename( $file ) . ':' . $line, 0, $severity, $file, $line );
		}
	);
	$GLOBALS['gw'] = array(
		'options'   => array(),
		'autoload'  => array(),
		'hooks'     => array(),
		'scheduled' => array(),
		'posts'     => array(),
		'meta'      => array(),
		'scripts'   => array(),
		'caps'      => true,
		'ajax'      => false,
	);
	$GLOBALS['gw_fail']  = array();
	$GLOBALS['gw_count'] = 0;
	function gw_check( $ok, string $message ) {
		++$GLOBALS['gw_count'];
		if ( ! $ok ) {
			$GLOBALS['gw_fail'][] = $message;
		}
	}
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['gw']['hooks'][ $hook ][] = $cb; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['gw']['hooks'][ $hook ][] = $cb; }
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['gw']['hooks'][ $hook ] ?? array() as $cb ) {
			$value = call_user_func_array( $cb, array_merge( array( $value ), $args ) );
		}
		return $value;
	}
	function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['gw']['options'] ) ? $GLOBALS['gw']['options'][ $name ] : $default; }
	function add_option( $name, $value, $deprecated = '', $autoload = true ) { $GLOBALS['gw']['options'][ $name ] = $value; $GLOBALS['gw']['autoload'][ $name ] = $autoload; return true; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['gw']['options'][ $name ] = $value; if ( null !== $autoload ) { $GLOBALS['gw']['autoload'][ $name ] = $autoload; } return true; }
	function get_post_status( $id ) { return $GLOBALS['gw']['posts'][ $id ]['status'] ?? false; }
	function get_post_type( $id ) { return $GLOBALS['gw']['posts'][ $id ]['type'] ?? false; }
	function get_the_title( $id ) { return $GLOBALS['gw']['posts'][ $id ]['title'] ?? ''; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['gw']['meta'][ $id ][ $key ] ?? ''; }
	function get_edit_post_link( $id ) { return 'https://s.test/wp-admin/post.php?post=' . $id . '&action=elementor'; }
	function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
	function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); }
	function wp_next_scheduled( $hook ) { return isset( $GLOBALS['gw']['scheduled'][ $hook ] ) ? $GLOBALS['gw']['scheduled'][ $hook ] : false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['gw']['scheduled'][ $hook ] = $time; return true; }
	function wp_doing_ajax() { return $GLOBALS['gw']['ajax']; }
	function is_admin() { return $GLOBALS['gw']['admin'] ?? true; }
	function current_user_can( $cap, ...$args ) { return $GLOBALS['gw']['caps']; }
	function absint( $v ) { return abs( (int) $v ); }
	function wp_unslash( $v ) { return $v; }
	function sanitize_key( $v ) { return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
	function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return (string) $v; }
	function admin_url( $path = '' ) { return 'https://s.test/wp-admin/' . $path; }
	function wp_nonce_field( $action ) { echo '<input type="hidden" name="_wpnonce" value="n-' . esc_attr( $action ) . '">'; }
	function get_current_blog_id() { return 1; }
	function wp_date( $format, $time ) { return gmdate( $format, $time ); }
	function wp_add_inline_script( $handle, $code, $position = 'after' ) { $GLOBALS['gw']['scripts'][] = array( $handle, $code ); return true; }
	function get_posts( $args ) { $GLOBALS['gw']['get_posts'][] = $args; return $GLOBALS['gw']['latest'] ?? array(); }
	/** Lo justo de WP_Query para `front_context()`: una página y su entrada global. */
	class WP_Query {
		public $query_vars;
		private $done = false;
		public function __construct( $args ) { $this->query_vars = $args; }
		public function have_posts() { return ! $this->done; }
		public function the_post() { $this->done = true; $GLOBALS['post'] = (object) array( 'ID' => $this->query_vars['page_id'] ); }
	}

	$root = dirname( __DIR__ );
	$file = $root . '/digitalisimo-elements/modules/digitalisimo-front/class-global-weight.php';
	gw_check( is_file( $file ), 'G0 existe Front\\Global_Weight' );
	if ( ! is_file( $file ) ) {
		echo "FALLA: no existe Front\\Global_Weight\n";
		exit( 1 );
	}
	require $file;
	$G = '\\Digitalisimo\\Elements\\Front\\Global_Weight';

	// ------------------------------------------------------------------------------------------------- G1 medida.
	$m = $G::measure( '<header><h1 class="t">Hola &amp; adiós</h1><script>var x = "muchas palabras aquí dentro";</script><style>.a{color:red}</style><svg><text>Logo texto</text></svg><!-- un comentario largo --><h2>Uno</h2><p>tres<br>palabras más</p><H2 id="x">Dos <b>tres</b></H2></header>' );
	gw_check( 1 === $m['h1'] && 2 === $m['h2'], 'G1 cuenta H1 y H2 (también en mayúsculas): ' . $m['h1'] . '/' . $m['h2'] );
	gw_check( 8 === $m['words'], 'G1 palabras visibles sin script, estilo, SVG ni comentarios (las etiquetas separan palabras): ' . $m['words'] );
	gw_check( array( 'H1: Hola & adiós', 'H2: Uno', 'H2: Dos tres' ) === $m['headings'], 'G1 los títulos, legibles: ' . implode( ' | ', $m['headings'] ) );
	$m = $G::measure( '' );
	gw_check( 0 === $m['words'] && 0 === $m['h1'] && 0 === $m['h2'] && array() === $m['headings'], 'G1 vacío' );

	// ------------------------------------------------------------------------------------------------- G2 globales.
	$GLOBALS['gw']['posts'] = array(
		9373  => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Cabecera' ),
		9353  => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Pie <b>del sitio</b>' ),
		13280 => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Presentación' ),
		13300 => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Reseñas' ),
		500   => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Ficha' ),
		600   => array( 'status' => 'draft', 'type' => 'elementor_library', 'title' => 'Borrador' ),
		700   => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Ventana' ),
		800   => array( 'status' => 'publish', 'type' => 'elementor_library', 'title' => 'Archivo' ),
		42    => array( 'status' => 'publish', 'type' => 'page', 'title' => 'Inicio' ),
	);
	$GLOBALS['gw']['options']['elementor_pro_theme_builder_conditions'] = array(
		'header'  => array( 9373 => array( 'include/general' ), 600 => array( 'include/general' ) ),
		'footer'  => array( '9353' => array( 'include/general', 'exclude/singular/page/42' ) ),
		'single'  => array( 500 => array( 'include/singular/product' ) ),
		'popup'   => array( 700 => array( 'exclude/general' ) ),
		'archive' => array( 800 => array( 'include/archive' ) ),
	);
	$globals = $G::global_templates();
	ksort( $globals );
	gw_check( array( 800 => 'archive', 9353 => 'footer', 9373 => 'header' ) === $globals, 'G2 globales: todo el sitio y todos los archivos, publicadas; no una ficha concreta, ni un borrador, ni una exclusión: ' . json_encode( $globals ) );
	$GLOBALS['gw']['options']['elementor_pro_theme_builder_conditions'] = 'basura';
	gw_check( array() === $G::global_templates(), 'G2 una caché de condiciones rara no rompe nada' );
	$GLOBALS['gw']['options']['elementor_pro_theme_builder_conditions'] = array(
		'header' => array( 9373 => array( 'include/general' ) ),
		'footer' => array( 9353 => array( 'include/general' ) ),
	);

	// ------------------------------------------------------------------------------------------------- G3 cálculo.
	$GLOBALS['gw']['meta'][9353]['_elementor_data']  = '[{"elType":"container","elements":[{"widgetType":"digitalisimo-template","settings":{"template_id":"13280"}}]}]';
	$GLOBALS['gw']['meta'][13280]['_elementor_data'] = array( array( 'widgetType' => 'template', 'settings' => array( 'template_id' => 13300 ) ) );
	$GLOBALS['gw']['meta'][13300]['_elementor_data'] = '[{"widgetType":"template","settings":{"template_id":"13300"}}]';
	$rendered = array();
	$contexts = array();
	$GLOBALS['gw']['options']['page_on_front'] = 42;
	$GLOBALS['wp_query']     = 'consulta-antes';
	$GLOBALS['wp_the_query'] = 'principal-antes';
	$GLOBALS['post']         = 'entrada-antes';
	$G::$renderer = static function ( $id ) use ( &$rendered, &$contexts ) {
		$rendered[] = $id;
		$contexts[] = array( is_object( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query']->query_vars['page_id'] : $GLOBALS['wp_query'], is_object( $GLOBALS['post'] ) ? $GLOBALS['post']->ID : $GLOBALS['post'], $GLOBALS['wp_query'] === $GLOBALS['wp_the_query'] );
		if ( 9353 === $id ) {
			return '<footer>' . str_repeat( '<h2>Hola, te saluda Daniel</h2><p>' . str_repeat( 'palabra ', 160 ) . '</p>', 8 ) . '</footer>';
		}
		return 9373 === $id ? '<nav><a href="/">Inicio</a> <a href="/contacto/">Contacto</a></nav><h1>Título de la página</h1>' : null;
	};
	$data = $G::compute();
	gw_check( array( 9373, 9353 ) === $rendered, 'G3 se pinta cada plantilla global una vez' );
	gw_check( array( array( 42, 42, true ), array( 42, 42, true ) ) === $contexts, 'G3 se pintan como en la portada (el título dinámico de la página no sale vacío): ' . json_encode( $contexts ) );
	gw_check( 'consulta-antes' === $GLOBALS['wp_query'] && 'principal-antes' === $GLOBALS['wp_the_query'] && 'entrada-antes' === $GLOBALS['post'], 'G3 después todo queda como estaba' );
	unset( $GLOBALS['gw']['options']['page_on_front'] );
	$GLOBALS['gw']['latest'] = array( 77 );
	$contexts = array();
	$G::compute();
	gw_check( 77 === ( $contexts[0][0] ?? null ) && 'page' === ( $GLOBALS['gw']['get_posts'][0]['post_type'] ?? '' ) && 'publish' === ( $GLOBALS['gw']['get_posts'][0]['post_status'] ?? '' ), 'G3 sin página de inicio, la última página publicada' );
	$GLOBALS['gw']['latest'] = array();
	$contexts = array();
	$G::compute();
	gw_check( 'consulta-antes' === ( $contexts[0][0] ?? null ), 'G3 sin páginas, se pinta sin cambiar la consulta' );
	$GLOBALS['gw']['options']['page_on_front'] = 42;
	$data = $G::compute();
	$footer = $data['templates'][9353] ?? array();
	gw_check( 8 === ( $footer['h2'] ?? 0 ) && ( $footer['words'] ?? 0 ) >= 1300 && 'footer' === ( $footer['location'] ?? '' ) && 'Pie del sitio' === ( $footer['title'] ?? '' ), 'G3 el pie del caso: 8 H2 y más de 1 300 palabras' );
	gw_check( array( '8 H2', 'unas 1 312 palabras' ) === ( $footer['reasons'] ?? array() ), 'G3 motivos del pie: ' . json_encode( $footer['reasons'] ?? null, JSON_UNESCAPED_UNICODE ) );
	gw_check( array( 13280, 13300 ) === ( $footer['embeds'] ?? array() ), 'G3 lo que incrusta, también anidado y sin repetir: ' . json_encode( $footer['embeds'] ?? null ) );
	$header = $data['templates'][9373] ?? array();
	gw_check( array( '1 H1' ) === ( $header['reasons'] ?? null ), 'G3 la cabecera con el título de la página en H1 también avisa' );
	gw_check( false === $GLOBALS['gw']['autoload']['digitalisimo_elements_global_weight'] && $data['computed'] > 0, 'G3 la opción se guarda sin carga automática' );
	gw_check( array( 9353, 9373 ) === array_map( 'intval', array_keys( $G::heavy() ) ) || array( 9373, 9353 ) === array_map( 'intval', array_keys( $G::heavy() ) ), 'G3 las dos pesadas' );

	$GLOBALS['gw']['hooks']['digitalisimo_elements_global_weight_limits'][] = static function ( $limits ) { return array( 'h1' => 2, 'h2' => 'x', 'words' => -5 ); };
	gw_check( array( 'h1' => 2, 'h2' => 3, 'words' => 1 ) === $G::limits(), 'G3 umbrales filtrables, enteros ≥ 1 y los raros vuelven al valor de fábrica' );
	gw_check( array() === $G::reasons( array( 'h1' => 1, 'h2' => 0, 'words' => 0 ) ), 'G3 con el umbral de H1 en 2, un H1 no avisa' );
	unset( $GLOBALS['gw']['hooks']['digitalisimo_elements_global_weight_limits'] );
	gw_check( array() === $G::reasons( array( 'h1' => 0, 'h2' => 2, 'words' => 249 ) ), 'G3 dos H2 y 249 palabras no avisan' );

	$G::$renderer = static function () { return null; };
	$data = $G::compute();
	gw_check( ! empty( $data['templates'][9353]['error'] ) && array() === $data['templates'][9353]['reasons'] && ! $G::heavy(), 'G3 si no se puede pintar, se anota sin avisar' );
	$G::$renderer = static function ( $id ) {
		return 9353 === $id ? '<footer>' . str_repeat( '<h2>Hola, te saluda Daniel</h2><p>' . str_repeat( 'palabra ', 160 ) . '</p>', 8 ) . '</footer>' : '<nav>Inicio</nav>';
	};
	$G::compute();

	// ------------------------------------------------------------------------------------------------- G4 cuándo.
	$GLOBALS['gw']['hooks'] = array();
	$GLOBALS['gw']['admin'] = false;
	$G::init();
	$front_hooks = array_keys( $GLOBALS['gw']['hooks'] );
	gw_check( ! in_array( 'admin_init', $front_hooks, true ) && ! preg_grep( '/^admin_post_/', $front_hooks ) && ! in_array( 'digitalisimo_seo_analysis_notes', $front_hooks, true ), 'G4 fuera de la administración no se enganchan la pantalla, su acción ni la nota del Análisis SEO' );
	$GLOBALS['gw']['hooks'] = array();
	$GLOBALS['gw']['admin'] = true;
	$reset = new ReflectionProperty( $G, 'initialized' );
	digi_test_accessible( $reset )->setValue( null, false );
	$G::init();
	$hooks = $GLOBALS['gw']['hooks'];
	foreach ( array( 'digitalisimo_elements_global_weight', 'elementor/document/after_save', 'update_option_elementor_pro_theme_builder_conditions', 'add_option_elementor_pro_theme_builder_conditions', 'trashed_post', 'untrashed_post', 'deleted_post', 'digitalisimo_elements_kit_imported', 'admin_init', 'admin_post_digitalisimo_elements_global_weight_scan', 'elementor/editor/after_enqueue_scripts', 'digitalisimo_seo_analysis_notes' ) as $hook ) {
		gw_check( isset( $hooks[ $hook ] ), 'G4 enganchado: ' . $hook );
	}
	foreach ( array( 'wp', 'template_redirect', 'wp_head', 'wp_footer', 'the_content', 'init', 'wp_loaded', 'elementor/frontend/before_render' ) as $hook ) {
		gw_check( ! isset( $hooks[ $hook ] ), 'G4 nada en el frente: ' . $hook );
	}
	gw_check( array( $G, 'compute' ) === $hooks['digitalisimo_elements_global_weight'][0] || ( is_array( $hooks['digitalisimo_elements_global_weight'][0] ) && 'compute' === $hooks['digitalisimo_elements_global_weight'][0][1] ), 'G4 el evento de WP-Cron calcula' );
	$doc = new class() {
		public $id = 13280;
		public function get_main_id() { return $this->id; }
	};
	$GLOBALS['gw']['scheduled'] = array();
	$G::document_saved( $doc );
	$first = $GLOBALS['gw']['scheduled']['digitalisimo_elements_global_weight'] ?? 0;
	gw_check( $first > 0, 'G4 guardar una plantilla programa el cálculo' );
	$G::document_saved( $doc );
	gw_check( 1 === count( $GLOBALS['gw']['scheduled'] ) && $first === $GLOBALS['gw']['scheduled']['digitalisimo_elements_global_weight'], 'G4 un solo evento pendiente' );
	$GLOBALS['gw']['scheduled'] = array();
	$doc->id = 42;
	$G::document_saved( $doc );
	$G::post_changed( 42 );
	gw_check( array() === $GLOBALS['gw']['scheduled'], 'G4 guardar o borrar una página no programa nada' );
	$G::post_changed( 9353 );
	gw_check( isset( $GLOBALS['gw']['scheduled']['digitalisimo_elements_global_weight'] ), 'G4 borrar o restaurar una plantilla lo programa' );
	$GLOBALS['gw']['scheduled'] = array();
	$G::admin_init();
	gw_check( array() === $GLOBALS['gw']['scheduled'], 'G4 con un cálculo reciente la administración no programa nada' );
	$GLOBALS['gw']['options']['digitalisimo_elements_global_weight']['computed'] = time() - 8 * 86400;
	$GLOBALS['gw']['ajax'] = true;
	$G::admin_init();
	gw_check( array() === $GLOBALS['gw']['scheduled'], 'G4 ni en AJAX' );
	$GLOBALS['gw']['ajax'] = false;
	$G::admin_init();
	gw_check( isset( $GLOBALS['gw']['scheduled']['digitalisimo_elements_global_weight'] ), 'G4 un cálculo de más de una semana se programa de nuevo' );
	$G::compute();

	// ------------------------------------------------------------------------------------------------- G5 editor.
	$_GET['post'] = '9353';
	$G::enqueue_editor();
	$script = $GLOBALS['gw']['scripts'][0] ?? array( '', '' );
	gw_check( 'elementor-editor' === $script[0] && str_contains( $script[1], 'showToast' ) && str_contains( $script[1], 'sticky:true' ), 'G5 aviso fijo en el editor de la plantilla pesada' );
	gw_check( str_contains( $script[1], 'El pie «Pie del sitio» (ID 9353) se aplica a todo el sitio y repite en cada página 8 H2, unas 1 312 palabras' ), 'G5 el aviso dice qué y cuánto: ' . substr( $script[1], 0, 260 ) );
	gw_check( ! str_contains( $script[1], '<b>' ) && ! str_contains( $script[1], 'innerHTML' ), 'G5 el aviso va escapado y sin innerHTML' );
	$GLOBALS['gw']['scripts'] = array();
	$_GET['post'] = '13300';
	$G::enqueue_editor();
	gw_check( isset( $GLOBALS['gw']['scripts'][0] ) && str_contains( $GLOBALS['gw']['scripts'][0][1], 'Esta plantilla va dentro de «Pie del sitio» (ID 9353)' ), 'G5 una plantilla incrustada (también anidada) avisa de la global que la incluye' );
	$GLOBALS['gw']['scripts'] = array();
	$_GET['post'] = '9373';
	$G::enqueue_editor();
	$_GET['post'] = '42';
	$G::enqueue_editor();
	gw_check( array() === $GLOBALS['gw']['scripts'], 'G5 una plantilla ligera o una página no avisan' );
	$_GET['post'] = '9353';
	$GLOBALS['gw']['caps'] = false;
	$G::enqueue_editor();
	gw_check( array() === $GLOBALS['gw']['scripts'], 'G5 sin permiso para editarla, nada' );
	$GLOBALS['gw']['caps'] = true;
	unset( $_GET['post'] );

	// ------------------------------------------------------------------------------------------------- G6 SEO y pantalla.
	$notes = apply_filters( 'digitalisimo_seo_analysis_notes', array(), (object) array( 'ID' => 42 ) );
	gw_check( 1 === count( $notes ) && 'info' === $notes[0]['status'] && str_contains( $notes[0]['detail'], 'ID 9353' ), 'G6 una nota informativa por plantilla pesada' );
	function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
	function remove_accents( $text ) { return $text; }
	require $root . '/digitalisimo-seo/includes/class-keyword-match.php';
	require $root . '/digitalisimo-seo/includes/class-links.php';
	require $root . '/digitalisimo-seo/includes/class-content-analysis.php';
	$seo_notes = Digitalisimo_Integrations_Content_Analysis::notes( (object) array( 'ID' => 42 ) );
	gw_check( 1 === count( $seo_notes ) && 'info' === $seo_notes[0]['status'] && 'elements_global_template_9353' === $seo_notes[0]['id'], 'G6 el Análisis SEO las recibe como información' );
	$GLOBALS['gw']['hooks']['digitalisimo_seo_analysis_notes'][] = static function ( $n ) { $n[] = 'basura'; $n[] = array( 'label' => 'Sin detalle' ); $n[] = array( 'id' => 'x', 'label' => 'L', 'detail' => 'D', 'status' => 'fail' ); return $n; };
	$seo_notes = Digitalisimo_Integrations_Content_Analysis::notes( (object) array( 'ID' => 42 ) );
	gw_check( 2 === count( $seo_notes ) && 'info' === $seo_notes[1]['status'], 'G6 una nota mal formada se descarta y ninguna nota cambia de estado (siempre info)' );
	$base = array( 'keyword' => '', 'title' => 'Título de prueba suficientemente largo', 'description' => '', 'url' => '', 'home' => 'https://s.test/', 'html' => '<p>Hola</p>' );
	$plain = Digitalisimo_Integrations_Content_Analysis::analyze( $base );
	gw_check( ! in_array( 'elements_global_template_9353', array_column( $plain['checks'], 'id' ), true ), 'G6 las notas no entran en analyze() ni en la puntuación' );
	$seo_src = file_get_contents( $root . '/digitalisimo-seo/includes/class-content-analysis.php' );
	gw_check( str_contains( $seo_src, "array_merge( \$result['checks'], self::notes( \$post ) )" ), 'G6 el cuadro «Análisis SEO» pinta las notas tras las comprobaciones' );

	ob_start();
	$G::render_section();
	$html = ob_get_clean();
	gw_check( str_contains( $html, 'Plantillas que se repiten en cada página' ) && str_contains( $html, 'Pesada: 8 H2, unas 1 312 palabras' ) && str_contains( $html, '>Ligera<' ), 'G6 la sección de Rendimiento con la tabla' );
	gw_check( str_contains( $html, 'name="action" value="digitalisimo_elements_global_weight_scan"' ) && str_contains( $html, 'n-digitalisimo_elements_global_weight_scan|1' ) && str_contains( $html, 'Analizar ahora' ), 'G6 «Analizar ahora» con su acción y su nonce' );
	$GLOBALS['gw']['options']['digitalisimo_elements_global_weight'] = array();
	ob_start();
	$G::render_section();
	gw_check( str_contains( ob_get_clean(), 'Aún no se ha analizado este sitio.' ), 'G6 sin análisis lo dice' );
	$front = file_get_contents( $root . '/digitalisimo-elements/modules/digitalisimo-front/class-module.php' );
	$page  = file_get_contents( $root . '/digitalisimo-elements/modules/digitalisimo-front/class-settings-page.php' );
	gw_check( (bool) preg_match( "/const BOOT = array\([^)]*'Global_Weight'/", $front ) && str_contains( $page, 'Global_Weight::render_section();' ), 'G6 el módulo del front lo arranca y Rendimiento pinta la sección' );

	if ( $GLOBALS['gw_fail'] ) {
		foreach ( $GLOBALS['gw_fail'] as $failure ) {
			echo 'FALLA: ' . $failure . "\n";
		}
		echo count( $GLOBALS['gw_fail'] ) . ' de ' . $GLOBALS['gw_count'] . " comprobaciones fallaron.\n";
		exit( 1 );
	}
	echo 'Plantillas globales pesadas: ' . $GLOBALS['gw_count'] . " comprobaciones correctas.\n";
}
