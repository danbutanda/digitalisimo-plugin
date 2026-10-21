<?php
/**
 * F-front, frente C: análisis de dependencias de los iconos de fuente (D4). Cada patrón de la especificación, con
 * positivos y negativos y sin distinguir mayúsculas (`FontAwesome` sin espacio, `[class*="fa-"]`…); cada fuente (CSS
 * adicional, CSS de SEO, widgets clásicos, opciones de iconos del núcleo, `post_content`, menús, fragmentos, CSS de
 * página y de kit, datos del constructor con claves `icon` sin `__fa4_migrated` y widgets de terceros, uno por clase);
 * lotes con cursor por id que continúan por su evento; como mucho 50 hallazgos con extractos de 120 caracteres; el
 * veredicto que la transición copia al aplicado; y el estado guardado sin carga automática.
 */
namespace Elementor {
	class Plugin { public static $instance; public $widgets_manager; public $experiments; }
	class Widget_Heading {}
}
namespace ElementorPro\Modules\Countdown\Widgets { class Countdown {} }
namespace Digitalisimo\Elements { class Own_Widget {} }
namespace Digitalisimo\Elements\Legacy { class Navbar {} }
namespace Acme\Addons { class Fancy_Widget {} class Other_Widget {} }
namespace Digitalisimo\Elements\Front {
	/** Doble del frente B (firma de §7.2): al ejecutarse copia el veredicto al aplicado, como hará run_now(). */
	final class Transition {
		const APPLIED = 'digitalisimo_elements_front_applied';
		public static $scheduled = 0;
		public static function applied(): array { $v = get_option( self::APPLIED, array() ); return is_array( $v ) ? $v : array(); }
		public static function schedule(): void { self::$scheduled++; $a = self::applied(); $a['icons'] = Icon_Scan::verdict(); $GLOBALS['opts'][ self::APPLIED ] = $a; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'ARRAY_A', 'ARRAY_A' );
	function check_scan( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$GLOBALS['hooks'] = array();
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = array( $cb, $args ); return true; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
	function remove_all_filters( $hook ) { unset( $GLOBALS['hooks'][ $hook ] ); }
	function has_action( $hook ) { return ! empty( $GLOBALS['hooks'][ $hook ] ); }
	function apply_filters( $hook, $value, ...$rest ) {
		foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $entries ) { foreach ( $entries as $entry ) { $value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $rest ), 0, $entry[1] ) ); } }
		return $value;
	}
	function do_action( $hook, ...$args ) { foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $entries ) { foreach ( $entries as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } } }

	$GLOBALS['opts']     = array();
	$GLOBALS['autoload'] = array();
	function get_option( $option, $default = false ) { return array_key_exists( $option, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $option ] : $default; }
	function update_option( $option, $value, $autoload = null ) { $GLOBALS['opts'][ $option ] = $value; $GLOBALS['autoload'][ $option ][] = $autoload; return true; }
	function maybe_unserialize( $value ) { return is_string( $value ) && preg_match( '/^(a|O|s|i|b):/', $value ) ? unserialize( $value ) : $value; }
	function get_post_types( $args = array() ) { return array( 'post' => 'post', 'page' => 'page', 'attachment' => 'attachment' ); }
	function wp_get_custom_css() { return $GLOBALS['custom_css']; }

	// Cron: un evento único por gancho; ejecutarlo lo saca de la lista antes de llamar a su acción (como wp-cron).
	$GLOBALS['cron'] = array();
	function wp_next_scheduled( $hook ) { return $GLOBALS['cron'][ $hook ] ?? false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['cron'][ $hook ] = $time; return true; }
	function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); return 1; }
	function run_cron( $hook ) { $n = 0; while ( isset( $GLOBALS['cron'][ $hook ] ) && $n++ < 100 ) { unset( $GLOBALS['cron'][ $hook ] ); do_action( $hook ); } return $n; }

	class Digitalisimo_Elements_Core { public static $bundled = false; public static function bundled() { return self::$bundled; } }
	class Digitalisimo_Integrations_SEO_Resolver { public static function option( $key, $fallback = null ) { return $GLOBALS['seo'][ $key ] ?? $fallback; } }
	class Fake_Widgets {
		public $map = array();
		public function get_widget_types( $type = null ) { $class = $this->map[ $type ] ?? null; return $class ? new $class() : null; }
	}
	class Fake_Experiments {
		public $features;
		public function __construct( $state ) { $this->features = array( 'e_font_icon_svg' => array( 'state' => $state, 'default' => 'inactive' ) ); }
		public function get_features( $name = null ) { return $this->features[ $name ] ?? null; }
	}

	/** Tablas de entradas y metadatos. prepare() conserva la consulta y sus valores para que el doble los lea. */
	class Fake_Wpdb {
		public $posts = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $rows = array();
		public $meta = array();
		public $queries = array();
		public function prepare( $query, ...$args ) {
			if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
			check_scan( preg_match_all( '/%[ds]/', $query ) === count( $args ), 'prepare(): un valor por marcador en «' . $query . '».' );
			return json_encode( array( $query, $args ) );
		}
		public function get_results( $sql, $output = 'OBJECT' ) {
			list( $query, $args ) = json_decode( $sql, true );
			$this->queries[] = $query;
			check_scan( ARRAY_A === $output, 'Las filas se piden como arreglos.' );
			if ( false !== strpos( $query, 'FROM wp_posts' ) ) {
				list( $cursor, $skip_type, $status_a, $status_b, $limit ) = $args;
				check_scan( preg_match( '/WHERE ID > %d .*ORDER BY ID ASC LIMIT %d/', $query ), 'Cursor por id, en orden y con límite.' );
				ksort( $this->rows );
				$out = array();
				foreach ( $this->rows as $id => $row ) {
					if ( $id <= $cursor || $skip_type === $row[0] || in_array( $row[1], array( $status_a, $status_b ), true ) ) { continue; }
					$out[] = array( 'ID' => (string) $id, 'post_type' => $row[0], 'post_title' => $row[2], 'post_content' => $row[3] );
					if ( count( $out ) >= $limit ) { break; }
				}
				return $out;
			}
			$keys = array_values( array_filter( $args, 'is_string' ) );
			$ids  = array_values( array_filter( $args, 'is_int' ) );
			check_scan( ! in_array( '_elementor_data', $keys, true ), 'Los datos del constructor no se leen en bloque.' );
			$out = array();
			foreach ( $ids as $id ) { foreach ( $keys as $key ) { if ( isset( $this->meta[ $id ][ $key ] ) ) { $out[] = array( 'post_id' => (string) $id, 'meta_key' => $key, 'meta_value' => $this->meta[ $id ][ $key ] ); } } }
			return $out;
		}
		public function get_col( $sql ) {
			list( $query, $args ) = json_decode( $sql, true );
			$this->queries[] = $query;
			$key = array_shift( $args );
			return array_map( 'strval', array_values( array_filter( $args, function ( $id ) use ( $key ) { return isset( $this->meta[ $id ][ $key ] ); } ) ) );
		}
		public function get_var( $sql ) {
			list( $query, $args ) = json_decode( $sql, true );
			$this->queries[] = $query;
			return $this->meta[ $args[0] ][ $args[1] ] ?? null;
		}
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();

	$dir = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-front/';
	require $dir . 'class-core-defaults.php';
	require $dir . 'class-icon-scan.php';
	use Digitalisimo\Elements\Front\Core_Defaults;
	use Digitalisimo\Elements\Front\Icon_Scan;
	use Digitalisimo\Elements\Front\Transition;
	Icon_Scan::init();
	Icon_Scan::init();
	check_scan( 1 === count( $GLOBALS['hooks'][ Icon_Scan::HOOK ][10] ), 'El evento del análisis se engancha una sola vez.' );

	/** Patrones encontrados en un texto. */
	$patterns = static function ( $text, $kind ) { return array_column( Icon_Scan::scan_text( $text, $kind ), 'pattern' ); };

	// CSS: cada patrón, sin distinguir mayúsculas.
	$css = array(
		'.fa{color:red}'                                => 'fa-class',
		'.icon .FAS:before{content:""}'                 => 'fa-class',
		'.fab{}'                                        => 'fa-class',
		'.fa-star{color:gold}'                          => 'fa-prefix',
		'.FA-STAR{}'                                    => 'fa-prefix',
		'body{font-family:FontAwesome}'                 => 'font-awesome',
		"h1:before{font-family:'Font Awesome 5 Free'}"  => 'font-awesome',
		'@import "font-awesome.min.css";'               => 'font-awesome',
		'[class*="fa-"]{color:red}'                     => 'class-attribute',
		"[class^='fa']{}"                               => 'class-attribute',
		'[CLASS~=fas]{}'                                => 'class-attribute',
		'.eicon-close{}'                                => 'eicons',
		'i{font-family:eicons}'                         => 'eicons',
		'@import url(elementor-icons.min.css);'         => 'elementor-icons',
		'.elementor-icon i{color:red}'                  => 'icon-selector',
		'.elementor-button-icon i{}'                    => 'icon-selector',
		'.elementor-icon-list-icon > i{}'               => 'icon-selector',
		'.elementor-social-icon-facebook i{}'           => 'icon-selector',
		'.elementor-icon-box-icon:hover I:before{}'     => 'icon-selector',
		'.digi .elementor-icon .marco i{}'              => 'icon-selector',
	);
	foreach ( $css as $text => $pattern ) { check_scan( in_array( $pattern, $patterns( $text, 'css' ), true ), 'CSS: «' . $text . '» debe dar ' . $pattern . '.' ); }
	$clean_css = '.fab-button{} .fade{} .fast{} .sofa-cama{} .facebook{} .elementor-icon svg{fill:red} .elementor-icon img{} .elementor-icon .item{} .elementor-icon-wrapper{} [class*="fake"]{} .eiconsole{} .elementor-icon{width:1em} .elementor-icon-list-icon svg{fill:currentColor}';
	check_scan( array() === Icon_Scan::scan_text( $clean_css, 'css' ), 'CSS sin iconos de fuente: ' . implode( ', ', $patterns( $clean_css, 'css' ) ) );

	// JavaScript: clases puestas o leídas, selectores y patrones comunes.
	$js = array(
		"el.classList.add('fa')"                         => 'class-list',
		'jQuery(el).addClass("fas fa-star")'             => 'class-list',
		"el.classList.replace('far', 'fas')"             => 'class-list',
		"el.className = 'fab'"                           => 'class-list',
		"el.setAttribute('class', 'fa')"                 => 'class-list',
		"if (el.classList.contains(`fa-spin`)) {}"       => 'class-list',
		"x.toggleClass('eicon-close')"                   => 'class-list',
		'document.querySelectorAll(\'[class*="fa-"]\')'  => 'class-attribute',
		"document.querySelector('.elementor-icon i')"    => 'icon-selector',
		"document.querySelector('.fas')"                 => 'fa-class',
		"loadCss('https://kit.fontawesome.com/x.css')"   => 'font-awesome',
	);
	foreach ( $js as $text => $pattern ) { check_scan( in_array( $pattern, $patterns( $text, 'js' ), true ), 'JS: «' . $text . '» debe dar ' . $pattern . '.' ); }
	$clean_js = "el.classList.add('fade'); el.classList.toggle('fast'); $(x).addClass('fab-button facebook'); el.className = 'sofa'; el.setAttribute('class', 'fancy');";
	check_scan( array() === Icon_Scan::scan_text( $clean_js, 'js' ), 'JS sin iconos de fuente: ' . implode( ', ', $patterns( $clean_js, 'js' ) ) );

	// HTML: clases como palabra completa, hojas, estilos y scripts en línea, enlaces a Font Awesome.
	$html = array(
		'<i class="fa fa-home"></i>'                                     => array( 'fa-class', 'fa-prefix' ),
		"<span class='fas'>x</span>"                                    => array( 'fa-class' ),
		'<i class=fab></i>'                                             => array( 'fa-class' ),
		'<link rel="stylesheet" href="https://use.fontawesome.com/all.css">' => array( 'font-awesome' ),
		'<style>.fa{color:red}</style>'                                 => array( 'fa-class' ),
		'<style>.elementor-icon i{color:red}</style>'                   => array( 'icon-selector' ),
		'<script>el.classList.add("far")</script>'                      => array( 'class-list' ),
		'<a onclick="this.classList.toggle(\'fas\')">x</a>'             => array( 'class-list' ),
		'<span style="font-family: FontAwesome">x</span>'               => array( 'font-awesome' ),
		'<span class="eicon-star"></span>'                              => array( 'eicons' ),
		'<div class="x" data-x=\'[class^="fa"]\'></div>'                => array( 'class-attribute' ),
	);
	foreach ( $html as $text => $expected ) { check_scan( array() === array_diff( $expected, $patterns( $text, 'html' ) ), 'HTML: «' . $text . '» debe dar ' . implode( ', ', $expected ) . '.' ); }
	$clean_html = '<p class="fab-menu fancy sofa">Sofá cómodo, fast y fade; facebook.</p><a href="https://example.com/fabrica">x</a><div class="elementor-icon"><svg class="e-font-icon-svg e-fas-star"></svg></div><style>.x svg{fill:currentColor}</style><script>el.classList.add("fade")</script>';
	check_scan( array() === Icon_Scan::scan_text( $clean_html, 'html' ), 'HTML sin iconos de fuente: ' . implode( ', ', $patterns( $clean_html, 'html' ) ) );

	// Clases (menús, clases CSS de un elemento): palabra completa.
	check_scan( array( 'fa-class', 'fa-prefix' ) === $patterns( 'fa fa-star', 'class' ) && array( 'fa-class' ) === $patterns( 'menu-item FAS', 'class' ) && array( 'eicons' ) === $patterns( 'eicon-star', 'class' ), 'Clases: fa, fas, fa-* y eicon-* como palabra completa.' );
	check_scan( array() === Icon_Scan::scan_text( 'fab-button fancy fa_bar sofa elementor-icon', 'class' ) && array() === Icon_Scan::scan_text( '   ', 'html' ), 'Clases parecidas no cuentan; un texto vacío tampoco.' );

	// Extractos: como mucho 120 caracteres, en una línea, alrededor del hallazgo y en UTF-8 válido.
	$long = str_repeat( 'Diseño ágil ', 60 ) . "\n<i class=\"fa fa-star\"></i>\n" . str_repeat( ' Más señales', 60 );
	foreach ( Icon_Scan::scan_text( $long, 'html' ) as $m ) {
		check_scan( mb_strlen( $m['excerpt'], 'UTF-8' ) <= 120 && mb_check_encoding( $m['excerpt'], 'UTF-8' ) && false === strpos( $m['excerpt'], "\n" ), 'Extracto de ≤ 120 caracteres en una línea: ' . $m['excerpt'] );
		check_scan( false !== stripos( $m['excerpt'], 'fa' ), 'El extracto muestra lo encontrado: ' . $m['excerpt'] );
	}

	// Sin núcleo incluido no hay análisis: veredicto n/a y ninguna consulta.
	check_scan( 'n/a' === Icon_Scan::verdict() && ! Icon_Scan::applicable(), 'Con el plugin original el análisis no aplica.' );
	check_scan( 'none' === Icon_Scan::run_batch()['status'] && array() === $GLOBALS['wpdb']->queries, 'Con el plugin original run_batch no consulta nada.' );

	// Con el núcleo incluido y el experimento activo por elección del sitio, tampoco: no hay nada que decidir.
	Digitalisimo_Elements_Core::$bundled = true;
	$GLOBALS['opts']['elementor_experiment-e_font_icon_svg'] = 'active';
	check_scan( ! Icon_Scan::applicable() && 'none' === Icon_Scan::run_batch()['status'], 'Con los iconos en línea ya activos no se analiza.' );
	$GLOBALS['opts']['elementor_experiment-e_font_icon_svg'] = 'inactive';
	check_scan( Icon_Scan::applicable() && 'n/a' === Icon_Scan::verdict(), 'Inactivo y sin análisis: aplica y aún no hay veredicto.' );

	// Sitio de prueba: fuentes globales, entradas de todos los tipos y datos del constructor.
	$GLOBALS['custom_css'] = '.fa-star{color:gold}';
	$GLOBALS['seo'] = array( 'perf_custom_css' => '.menu [class^="fa"]{margin:0}' );
	$GLOBALS['opts']['widget_text']        = array( 2 => array( 'title' => 'Contacto', 'text' => '<i class="fa fa-phone"></i> 55 1234', 'filter' => true ), 3 => array( 'title' => 'Limpio', 'text' => 'Sin iconos' ), '_multiwidget' => 1 );
	$GLOBALS['opts']['widget_custom_html'] = array( 3 => array( 'title' => '', 'content' => '<span class="eicon-star"></span>' ) );
	$GLOBALS['opts']['widget_block']       = array( 4 => array( 'content' => '<!-- wp:html --><i class="fas fa-bars"></i><!-- /wp:html -->' ) );
	$GLOBALS['opts'][ Icon_Scan::NEEDS_UPDATE ] = 'yes';
	$GLOBALS['opts'][ Icon_Scan::FA4_SHIM ]     = 'yes';
	$GLOBALS['opts']['elementor_active_kit']    = 12;
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->widgets_manager = new Fake_Widgets();
	\Elementor\Plugin::$instance->widgets_manager->map = array(
		'heading' => 'Elementor\Widget_Heading', 'text-editor' => 'Elementor\Widget_Heading', 'icon-box' => 'Elementor\Widget_Heading', 'icon-list' => 'Elementor\Widget_Heading', 'button' => 'Elementor\Widget_Heading', 'html' => 'Elementor\Widget_Heading',
		'countdown' => 'ElementorPro\Modules\Countdown\Widgets\Countdown', 'digitalisimo-own' => 'Digitalisimo\Elements\Own_Widget', 'legacy-navbar' => 'Digitalisimo\Elements\Legacy\Navbar',
		'acme-fancy' => 'Acme\Addons\Fancy_Widget', 'acme-other' => 'Acme\Addons\Other_Widget',
	);
	$widget = static function ( $type, $settings = array() ) { return array( 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings ); };
	$page_data = array( array( 'elType' => 'container', 'settings' => array( 'custom_css' => 'selector .elementor-icon i{color:red}' ), 'elements' => array(
		$widget( 'text-editor', array( 'editor' => '<p><span class="fa-solid fa-phone"></span> Llámanos</p>' ) ),
		$widget( 'icon-box', array( 'icon' => 'fa fa-star', 'title_text' => 'Hola' ) ),
		$widget( 'icon-box', array( 'icon' => 'fa fa-heart', 'selected_icon' => array( 'value' => 'fas fa-heart', 'library' => 'fa-solid' ), '__fa4_migrated' => array( 'selected_icon' => true ) ) ),
		$widget( 'icon-list', array( 'icon_list' => array( array( 'text' => 'Uno', 'icon' => 'fa fa-check' ), array( 'text' => 'Dos', 'social' => 'fa fa-check', 'selected_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ), '__fa4_migrated' => array( 'selected_icon' => true ) ) ) ) ),
		$widget( 'button', array( '_css_classes' => 'boton FAS', 'selected_icon' => array( 'value' => 'fab fa-whatsapp', 'library' => 'fa-brands' ) ) ),
		$widget( 'acme-fancy' ),
		$widget( 'heading', array( 'title' => 'Sofá rápido' ) ),
		$widget( 'digitalisimo-own', array( 'selected_icon' => array( 'value' => array( 'url' => 'https://sitio.test/a.svg', 'id' => 5 ), 'library' => 'svg' ) ) ),
		$widget( 'countdown' ), $widget( 'legacy-navbar' ), $widget( 'no-registrado' ),
	) ) );
	$wpdb = $GLOBALS['wpdb'];
	$wpdb->rows = array(
		11 => array( 'page', 'publish', 'Inicio', 'Texto plano sin iconos' ),
		12 => array( 'elementor_library', 'publish', 'Kit', '' ),
		13 => array( 'page', 'draft', 'Borrador', '' ),
		14 => array( 'elementor_snippet', 'publish', 'Fragmento', '' ),
		15 => array( 'nav_menu_item', 'publish', '<i class="fa fa-home"></i> Inicio', '' ),
		16 => array( 'page', 'trash', 'Papelera', '<i class="fa fa-trash"></i>' ),
		17 => array( 'revision', 'inherit', 'Revisión', '<i class="fa fa-undo"></i>' ),
		18 => array( 'post', 'publish', 'Limpio', '<p>Sofá cómodo y fast</p>' ),
		19 => array( 'elementor_library', 'publish', 'Plantilla', '<i class="fa fa-x"></i>' ),
		20 => array( 'wp_template_part', 'publish', 'Cabecera', '<!-- wp:html --><i class="fas fa-bars"></i><!-- /wp:html -->' ),
		21 => array( 'page', 'private', 'Datos rotos', '' ),
		22 => array( 'page', 'auto-draft', 'Automático', '<i class="fa fa-a"></i>' ),
		23 => array( 'page', 'publish', 'Otra', '' ),
		24 => array( 'attachment', 'inherit', 'Imagen', 'Descripción limpia' ),
		25 => array( 'page', 'future', 'Limpia', '' ),
		26 => array( 'page', 'publish', 'Iconos migrados', '' ),
	);
	$wpdb->meta = array(
		11 => array( '_elementor_data' => json_encode( $page_data ), '_elementor_page_settings' => serialize( array( 'hide_title' => 'yes' ) ), '_elementor_element_cache' => '<i class="fa fa-cache"></i>' ),
		12 => array( '_elementor_page_settings' => serialize( array( 'custom_css' => '.fa-x{}', 'system_colors' => array() ) ), '_elementor_data' => '[]' ),
		13 => array( '_elementor_page_settings' => serialize( array( 'custom_css' => 'body .EICON-close{}' ) ) ),
		14 => array( '_elementor_code' => '<script src="https://kit.fontawesome.com/abc.js"></script>' ),
		15 => array( '_menu_item_classes' => serialize( array( '', 'fas', 'fa-home' ) ) ),
		16 => array( '_elementor_data' => json_encode( array( $widget( 'html', array( 'html' => '<i class="fa"></i>' ) ) ) ) ),
		17 => array( '_elementor_data' => json_encode( array( $widget( 'html', array( 'html' => '<i class="fa"></i>' ) ) ) ) ),
		19 => array( '_elementor_data' => json_encode( array( $widget( 'html', array( 'html' => '<style>.fab{}</style>' ) ) ) ) ),
		21 => array( '_elementor_data' => '[{"elType":"widget","settings":{"html":"<i class=\"fa-star\"' ),
		23 => array( '_elementor_data' => json_encode( array( $widget( 'acme-fancy' ), $widget( 'heading', array( 'title' => 'Hola', '__globals__' => array( 'title_color' => 'globals/colors?id=fa-primary' ) ) ) ) ) ),
		26 => array( '_elementor_data' => json_encode( array( $widget( 'icon-box', array( 'icon' => 'fa fa-heart', 'selected_icon' => array( 'value' => 'fas fa-heart', 'library' => 'fa-solid' ), '__fa4_migrated' => array( 'selected_icon' => true ) ) ), $widget( 'social-icons', array( 'social_icon_list' => array( array( 'social' => 'fa fa-facebook', 'social_icon' => array( 'value' => 'fab fa-facebook', 'library' => 'fa-brands' ), '__fa4_migrated' => array( 'social_icon' => true ) ) ) ) ) ) ) ),
		25 => array( '_elementor_data' => json_encode( array( $widget( 'html', array( 'html' => '<div class="caja"><svg class="e-font-icon-svg"></svg></div>' ) ) ) ), '_elementor_page_settings' => serialize( array( 'custom_css' => 'selector svg{fill:currentColor}' ) ) ),
	);

	// «Analizar»: el primer lote revisa las fuentes globales y 4 entradas; el resto sigue por su evento.
	$state = Icon_Scan::run_batch( 4 );
	check_scan( 'running' === $state['status'] && 14 === $state['cursor'] && 4 === $state['scanned'] && 'pending' === Icon_Scan::verdict(), 'Primer lote: cursor en la cuarta entrada (14) y veredicto pendiente.' );
	check_scan( false !== wp_next_scheduled( Icon_Scan::HOOK ) && 0 === Transition::$scheduled, 'Queda agendado el siguiente lote; la transición aún no.' );
	check_scan( array( false ) === array_unique( $GLOBALS['autoload'][ Icon_Scan::OPTION ] ), 'El estado se guarda sin carga automática.' );
	// El evento continúa con lotes de 200 hasta terminar.
	$runs = run_cron( Icon_Scan::HOOK );
	$state = Icon_Scan::state();
	check_scan( 1 === $runs && 'done' === $state['status'] && 26 === $state['cursor'] && 13 === $state['scanned'] && false === wp_next_scheduled( Icon_Scan::HOOK ), 'El evento termina el análisis (13 entradas revisadas, sin revisiones, papelera ni borradores automáticos) y no deja eventos.' );
	check_scan( array( false ) === array_unique( $GLOBALS['autoload'][ Icon_Scan::OPTION ] ), 'Cada guardado del estado va sin carga automática.' );

	$found = array();
	foreach ( $state['findings'] as $f ) {
		check_scan( isset( $f['source'], $f['id'], $f['pattern'], $f['excerpt'] ) && isset( Icon_Scan::SOURCES[ $f['source'] ] ) && mb_strlen( $f['excerpt'], 'UTF-8' ) <= 120, 'Cada hallazgo lleva fuente conocida, id, patrón y extracto ≤ 120.' );
		$found[ $f['source'] . ':' . $f['id'] ][] = $f['pattern'];
	}
	$expected = array(
		'custom_css:' => 'fa-prefix', 'seo_css:' => 'class-attribute', 'widget_text:2' => 'fa-class', 'widget_custom_html:3' => 'eicons', 'widget_block:4' => 'fa-prefix',
		'option:' => 'fa4-pending',
		'element_css:11' => 'icon-selector', 'element_text:11' => 'fa-prefix', 'fa4:11' => 'fa4-icon', 'element_class:11' => 'fa-class', 'third_party:11' => 'third-party',
		'kit_css:12' => 'fa-prefix', 'page_css:13' => 'eicons', 'snippet:14' => 'font-awesome', 'menu_title:15' => 'fa-prefix', 'menu_classes:15' => 'fa-class',
		'element_text:19' => 'fa-class', 'post_content:20' => 'fa-prefix', 'element_text:21' => 'fa-prefix',
	);
	foreach ( $expected as $where => $pattern ) { check_scan( in_array( $pattern, $found[ $where ] ?? array(), true ), 'Falta el hallazgo ' . $pattern . ' en ' . $where . '.' ); }
	check_scan( array() === array_diff( array_keys( $found ), array_keys( $expected ) ), 'Hallazgos inesperados: ' . implode( ', ', array_diff( array_keys( $found ), array_keys( $expected ) ) ) );
	check_scan( count( $state['findings'] ) === (int) $state['total'] && 'findings' === Icon_Scan::verdict(), 'El total cuenta cada hallazgo y el veredicto es «findings».' );
	check_scan( array( 'fa4-pending', 'fa4-shim' ) === $found['option:'], 'Las dos opciones de iconos antiguos del núcleo cuentan, sin mostrar su nombre interno.' );
	foreach ( $state['findings'] as $f ) { check_scan( ! is_string( $f['id'] ) || false === stripos( $f['id'], 'elementor' ), 'Ningún id de hallazgo nombra al proveedor: ' . $f['id'] ); }
	foreach ( Icon_Scan::SOURCES as $label ) { check_scan( false === stripos( $label, 'elementor' ), 'Etiqueta sin el nombre del proveedor: ' . $label ); }
	check_scan( array( 'fa4-icon' ) === $found['fa4:11'] && 'fa fa-star' === array_values( array_filter( $state['findings'], static function ( $f ) { return 'fa4' === $f['source']; } ) )[0]['excerpt'], 'La clave icon sin __fa4_migrated cuenta (una vez por entrada); la migrada no.' );
	check_scan( array( 'Acme\Addons\Fancy_Widget' ) === $state['classes'] && 1 === count( $found['third_party:11'] ), 'Un hallazgo por clase de terceros aunque se use en dos entradas; núcleo, PRO, propios y adaptadores no cuentan.' );
	check_scan( ! array_filter( $wpdb->queries, static function ( $q ) { return false !== stripos( $q, 'elementor' ) || ! preg_match( '/^SELECT /', $q ); } ), 'Sólo lecturas, con las claves como valores preparados.' );
	check_scan( 'findings' === ( Transition::applied()['icons'] ?? '' ) && 1 === Transition::$scheduled, 'Al terminar se pide la transición una vez y el veredicto llega al aplicado.' );

	// Con el análisis terminado, el evento y run_batch() (la transición empuja así un análisis sin continuación) sólo
	// devuelven el resultado: ni consultas ni otro análisis, así que no hay bucle entre los dos eventos.
	$before = count( $wpdb->queries );
	do_action( Icon_Scan::HOOK );
	$again = Icon_Scan::run_batch();
	check_scan( count( $wpdb->queries ) === $before && 'done' === $again['status'] && $state['findings'] === $again['findings'] && 1 === Transition::$scheduled && false === wp_next_scheduled( Icon_Scan::HOOK ), 'Un análisis terminado no se repite solo.' );

	// «Analizar» de nuevo (start) con el mismo resultado no vuelve a pedir la transición; limpiar el sitio, sí.
	$state = Icon_Scan::start();
	check_scan( 'done' === $state['status'] && 'findings' === $state['previous'] && 'findings' === Icon_Scan::verdict() && 1 === Transition::$scheduled, 'Mismo veredicto: sin otra transición.' );
	$GLOBALS['custom_css'] = '';
	$GLOBALS['seo'] = array();
	foreach ( array( 'widget_text', 'widget_custom_html', 'widget_block', Icon_Scan::NEEDS_UPDATE, Icon_Scan::FA4_SHIM ) as $option ) { unset( $GLOBALS['opts'][ $option ] ); }
	$wpdb->rows = array( 18 => $wpdb->rows[18], 24 => $wpdb->rows[24], 25 => $wpdb->rows[25] );
	$state = Icon_Scan::start();
	check_scan( 'done' === $state['status'] && array() === $state['findings'] && 0 === $state['total'] && array() === $state['classes'] && 'clean' === Icon_Scan::verdict(), 'Un sitio limpio da «clean» y el análisis nuevo no arrastra hallazgos.' );
	check_scan( 2 === Transition::$scheduled && 'clean' === Transition::applied()['icons'], 'El veredicto nuevo llega al aplicado.' );

	// Como mucho 50 hallazgos guardados; el total sigue contando.
	$wpdb->rows = array();
	$wpdb->meta = array();
	for ( $i = 100; $i < 180; $i++ ) { $wpdb->rows[ $i ] = array( 'post', 'publish', 'P' . $i, str_repeat( 'Texto largo con acentos áéí ', 20 ) . '<i class="fa fa-star-' . $i . '"></i>' ); }
	$state = Icon_Scan::start();
	check_scan( 'done' === $state['status'] && 80 === $state['scanned'] && 50 === count( $state['findings'] ) && (int) $state['total'] >= 80, 'Se guardan 50 hallazgos y el total cuenta todos (' . $state['total'] . ').' );
	foreach ( $state['findings'] as $f ) { check_scan( mb_strlen( $f['excerpt'], 'UTF-8' ) <= 120 && mb_check_encoding( $f['excerpt'], 'UTF-8' ), 'Extracto guardado de ≤ 120 caracteres.' ); }

	// Un filtro declara compatible un widget de terceros.
	$wpdb->rows = array( 30 => array( 'page', 'publish', 'Terceros', '' ) );
	$wpdb->meta = array( 30 => array( '_elementor_data' => json_encode( array( $widget( 'acme-other' ) ) ) ) );
	add_filter( 'digitalisimo_elements_icon_scan_third_party', static function ( $finding, $class ) { return 'Acme\Addons\Other_Widget' === $class ? false : $finding; }, 10, 2 );
	$state = Icon_Scan::start();
	check_scan( 'clean' === Icon_Scan::verdict() && array( 'Acme\Addons\Other_Widget' ) === $state['classes'], 'Un widget de terceros declarado compatible no cuenta.' );
	remove_all_filters( 'digitalisimo_elements_icon_scan_third_party' );
	$state = Icon_Scan::start();
	check_scan( 'findings' === Icon_Scan::verdict() && 'third_party' === $state['findings'][0]['source'] && 'acme-other (Acme\Addons\Other_Widget)' === $state['findings'][0]['excerpt'], 'Sin el filtro, el mismo widget cuenta, con su nombre y su clase.' );

	// Lotes con un límite exacto: el último lote vacío cierra el análisis.
	$wpdb->rows = array( 40 => array( 'post', 'publish', 'A', '' ), 41 => array( 'post', 'publish', 'B', '' ) );
	$wpdb->meta = array();
	$state = Icon_Scan::start( 2 );
	check_scan( 'running' === $state['status'] && 41 === $state['cursor'], 'Dos entradas con lote de 2: sigue pendiente.' );
	check_scan( 'done' === Icon_Scan::run_batch( 2 )['status'] && 'clean' === Icon_Scan::verdict(), 'El lote siguiente, vacío, lo termina.' );

	// Un experimento con el valor propio aplicado sigue analizándose (el estado nativo es inactivo); si el sitio ya los
	// trae activos, no.
	Core_Defaults::experiments( new Fake_Experiments( 'default' ) );
	check_scan( 'inactive' === Core_Defaults::native_icons() && Icon_Scan::applicable(), 'Con el valor por defecto de Elements el análisis se puede repetir.' );
	Core_Defaults::experiments( new Fake_Experiments( 'active' ) );
	$queries = count( $wpdb->queries );
	$state = Icon_Scan::start();
	check_scan( ! Icon_Scan::applicable() && 'done' === $state['status'] && 'clean' === Icon_Scan::verdict() && count( $wpdb->queries ) === $queries, 'Con el experimento activo por elección del sitio no se analiza ni se borra el resultado anterior.' );

	echo "DIGITALÍSIMO Elements: análisis de iconos de fuente validado (patrones, fuentes, lotes, límites y veredicto).\n";
}
