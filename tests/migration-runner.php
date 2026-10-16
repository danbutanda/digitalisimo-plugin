<?php
/**
 * Migrar en un clic (DIGITALÍSIMO Migración): plugins que se retiran y cómo, conversión de alias de widgets fusionados
 * (clases CSS, ajustes, CSS que impide convertir) y la tarea por etapas del sitio y de la red (pausa tras desactivar,
 * cerrojo, reintentos, documentos que fallan, informe y origen permitido para la pantalla de red).
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
	}
	class Repeater {}
	class Controls_Manager { const TEXT = 'text'; }
	class Plugin { public static $instance; }
}
namespace Digitalisimo\Elements {
	/** Los widgets propios están cargados (la conversión lo exige). */
	final class Widget_Registry {}
}
namespace Digitalisimo\Elements\Legacy {
	/** Motor de conversión de Element Pack simulado: documentos, revisiones y fallos configurables. */
	final class Translator {
		public static function element_pack_active() { return ! empty( $GLOBALS['t']['ep_loaded'] ); }
	}
	final class Migration {
		const BACKUP        = '_digitalisimo_ep_backup';
		const PAGE          = 'digitalisimo-ep-migration';
		const REPAIRED      = 'digitalisimo_ep_conditions_repaired';
		const REPAIR_CUTOFF = 'digitalisimo_ep_conditions_repair_cutoff';
		const REPAIR_BATCH  = 25;
		public static function recover_candidates() { return array_keys( $GLOBALS['t']['overwritten'] ); }
		public static function overwritten_by( $revision ) { $parent = $GLOBALS['t']['overwritten'][ $revision ] ?? 0; return $parent ? array( 'parent' => $parent, 'revision' => $revision, 'date' => '', 'autosave' => false ) : null; }
		public static function repair_step( $limit ) { $GLOBALS['t']['repair_calls']++; update_option( self::REPAIRED, '1' ); return array( 'documents' => 1, 'elements' => 2, 'remaining' => 0, 'done' => true ); }
		public static function documents() { return array_values( array_diff( $GLOBALS['t']['docs'], $GLOBALS['t']['converted'] ) ); }
		public static function legacy_revisions() { return array_values( array_diff( $GLOBALS['t']['revisions'], $GLOBALS['t']['converted'] ) ); }
		public static function convert_document( $id ) {
			if ( isset( $GLOBALS['t']['crash'][ $id ] ) && $GLOBALS['t']['crash'][ $id ]-- > 0 ) {
				throw new \RuntimeException( 'caída simulada en ' . $id );
			}
			$GLOBALS['t']['converted'][] = $id;
			return array( 'native' => 3 );
		}
		public static function recover_from_revision( $id, $revision ) { return 7 === $id ? 'revision' : ''; }
		public static function classify() { return array( 'convertible' => array(), 'blocked' => $GLOBALS['t']['blocked'] ); }
		public static function inventory() { return $GLOBALS['t']['blocked'] ? array( 'bdt-weather' => array( 'elements' => 1, 'documents' => 1, 'map' => false ) ) : array(); }
		public static function reason( $type ) { return 'Clima: sin API.'; }
		public static function figures() { return array( 'elements' => 0, 'documents' => 0, 'missing' => array() ); }
		public static function clear_css( $id ) { $GLOBALS['t']['cleared'][] = $id; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', '/wp/plugins/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_MIGRATION_VERSION', '9.9.9' );
	function check_runner( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	/* -------- WordPress mínimo: opciones y metadatos por sitio, plugins, cron y escapes -------- */
	$GLOBALS['t'] = array();
	function t_reset() {
		$GLOBALS['t'] = array( 'blog' => 1, 'stack' => array(), 'multisite' => false, 'options' => array( 1 => array(), 2 => array() ), 'site_options' => array(), 'meta' => array( 1 => array(), 2 => array() ), 'types' => array(), 'plugins' => array(), 'deactivated' => array(), 'cron' => array(), 'docs' => array(), 'revisions' => array(), 'converted' => array(), 'crash' => array(), 'overwritten' => array(), 'blocked' => array(), 'cleared' => array(), 'ep_loaded' => false, 'can' => true, 'ajax' => false, 'repair_calls' => 0 );
		new_request();
		\Digitalisimo\Elements\Legacy\Aliases::set_map( null );
	}
	/** Una petición nueva: arranca después de cualquier desactivación anterior. */
	function new_request() { $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) + 1; usleep( 1000 ); }
	function get_current_blog_id() { return $GLOBALS['t']['blog']; }
	function get_current_network_id() { return 1; }
	function wp_generate_password( $n, $s ) { return 'tok' . mt_rand( 100000, 999999 ); }
	function wp_cache_delete( $k, $g ) { return true; }
	function wp_is_post_revision( $id ) { return 'revision' === ( $GLOBALS['t']['types'][ $id ] ?? '' ) ? (int) ( $GLOBALS['t']['parents'][ $id ] ?? 1 ) : false; }
	function get_post( $id ) { return (object) array( 'ID' => $id, 'post_parent' => $GLOBALS['t']['parents'][ $id ] ?? 0, 'post_modified_gmt' => '2026-10-16 10:00:00' ); }
	function is_multisite() { return $GLOBALS['t']['multisite']; }
	function switch_to_blog( $id ) { $GLOBALS['t']['stack'][] = $GLOBALS['t']['blog']; $GLOBALS['t']['blog'] = (int) $id; }
	function restore_current_blog() { $GLOBALS['t']['blog'] = array_pop( $GLOBALS['t']['stack'] ); }
	function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ] ) ? $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ][ $k ] : $d; }
	function get_blog_option( $b, $k, $d = false ) { return $GLOBALS['t']['options'][ $b ][ $k ] ?? $d; }
	function add_option( $k, $v = '', $x = '', $a = true ) { if ( array_key_exists( $k, $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ] ) ) { return false; } $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ][ $k ] = $v; return true; }
	function update_option( $k, $v, $a = null ) { $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ][ $k ] = $v; return true; }
	function delete_option( $k ) { unset( $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ][ $k ] ); return true; }
	function get_site_option( $k, $d = false ) { return $GLOBALS['t']['site_options'][ $k ] ?? $d; }
	function update_site_option( $k, $v ) { $GLOBALS['t']['site_options'][ $k ] = $v; return true; }
	function get_sites( $args ) { return array( 1, 2 ); }
	function get_blog_details( $id ) { return (object) array( 'blogname' => 'Sitio ' . $id, 'siteurl' => 'https://s' . $id . '.test' ); }
	function get_bloginfo( $k ) { return 'Sitio ' . $GLOBALS['t']['blog']; }
	function home_url( $p = '' ) { return 'https://s' . $GLOBALS['t']['blog'] . '.test' . $p; }
	function admin_url( $p = '' ) { return 'https://s' . $GLOBALS['t']['blog'] . '.test/wp-admin/' . $p; }
	function network_admin_url( $p = '' ) { return 'https://red.test:8443/wp-admin/network/' . $p; }
	function wp_parse_url( $u ) { return parse_url( $u ); }
	function get_metadata( $type, $id, $key, $single ) { return $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] ?? ''; }
	function add_metadata( $type, $id, $key, $value, $unique ) { $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] = stripslashes( $value ); return true; }
	function update_metadata( $type, $id, $key, $value ) { $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] = stripslashes( $value ); return true; }
	function wp_slash( $v ) { return addslashes( $v ); }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function apply_filters( $hook, $value ) { return $value; }
	function add_action() {} function add_filter() {}
	function current_user_can( $cap ) { return $GLOBALS['t']['can']; }
	function wp_doing_ajax() { return $GLOBALS['t']['ajax']; }
	function wp_raise_memory_limit( $c ) {} function wp_convert_hr_to_bytes( $v ) { return (int) $v * 1048576; }
	function wp_next_scheduled( $hook ) { return $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] ?? false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] = $time; }
	function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] ); }
	function wp_create_nonce( $a ) { return 'n-' . $a; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return esc_html( $v ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function get_the_title( $id ) { return 'Página ' . $id; }
	function wp_date( $f, $t ) { return gmdate( $f, $t ); }
	function plugin_basename( $file ) { return 'digitalisimo-elements/pro-elements.php'; }
	function get_plugins() { return $GLOBALS['t']['plugins']; }
	function deactivate_plugins( $file, $silent, $network ) {
		$GLOBALS['t']['deactivated'][] = array( $file, (bool) $network );
		if ( $network ) {
			unset( $GLOBALS['t']['site_options']['active_sitewide_plugins'][ $file ] );
		} else {
			$GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ]['active_plugins'] = array_values( array_diff( (array) get_option( 'active_plugins', array() ), array( $file ) ) );
		}
	}
	function digitalisimo_elements_conflicting_plugin_active() { return ! empty( $GLOBALS['t']['pro_conflict'] ); }
	/** Alias de prueba con plantillas en sus filas (como el alternador). */
	class Fake_Tabs { const CSS_PREFIX = 'digi-fancy-tabs'; }
	class Fake_Switcher extends Fake_Tabs {
		const LEGACY_CLASSES = array( 'prefix' => 'digi-content-switcher', 'elements' => array() );
		public static function upgrade_settings( array $s ) { $s['tabs'] = $s['switcher_items'] ?? array(); unset( $s['switcher_items'] ); return $s; }
	}
	/** Núcleo incluido simulado: su carpeta existe salvo que la prueba la quite. */
	final class Digitalisimo_Elements_Core {
		public static function dir() { return empty( $GLOBALS['t']['no_core'] ) ? __DIR__ . '/../digitalisimo-elements/elementor/' : '/no/existe/'; }
		public static function version() { return '4.3.5'; }
	}
	/** $wpdb mínimo: busca en los metadatos y opciones simulados los textos de las condiciones LIKE. */
	final class Fake_Wpdb {
		public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts'; public $options = 'wp_options'; public $sitemeta = 'wp_sitemeta';
		private $args = array();
		public function esc_like( $t ) { return addcslashes( $t, '_%\\' ); }
		public function prepare( $sql, ...$args ) { $this->args = 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args; return $sql; }
		private function needles() {
			$out = array();
			foreach ( $this->args as $arg ) {
				if ( '%' === substr( (string) $arg, 0, 1 ) ) { $out[] = stripslashes( trim( $arg, '%' ) ); }
			}
			return $out;
		}
		private function matches( $value ) {
			$value = is_string( $value ) ? $value : serialize( $value );
			foreach ( $this->needles() as $needle ) { if ( false !== strpos( $value, $needle ) ) { return true; } }
			return false;
		}
		private function revision( $id ) { return 'revision' === ( $GLOBALS['t']['types'][ $id ] ?? 'page' ); }
		public function get_col( $sql ) {
			$ids   = array();
			$after = false !== strpos( $sql, 'post_id > %d' ) ? (int) $this->args[0] : 0;
			foreach ( $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ] as $id => $meta ) {
				if ( $id > $after && isset( $meta['_elementor_data'] ) && $this->matches( $meta['_elementor_data'] ) && ! ( false !== strpos( $sql, "<> 'revision'" ) && $this->revision( $id ) ) ) { $ids[] = $id; }
			}
			sort( $ids );
			return preg_match( '/LIMIT (\d+)/', $sql, $m ) ? array_slice( $ids, 0, (int) $m[1] ) : $ids;
		}
		/** El cerrojo: INSERT IGNORE, robo con UPDATE condicionado, renovación y borrado por token. */
		public function query( $sql ) {
			$opts = &$GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ];
			$key  = \Digitalisimo\Elements\Legacy\Runner::LOCK;
			if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
				if ( array_key_exists( $key, $opts ) ) { return 0; }
				$opts[ $key ] = $this->args[1];
				return 1;
			}
			if ( 0 === strpos( $sql, 'UPDATE' ) && false !== strpos( $sql, 'option_value = %s WHERE option_name = %s AND option_value = %s' ) ) {
				if ( ( $opts[ $key ] ?? null ) !== $this->args[2] ) { return 0; }
				$opts[ $key ] = $this->args[0];
				return 1;
			}
			$token = substr( stripslashes( (string) end( $this->args ) ), 2 );
			$mine  = isset( $opts[ $key ] ) && substr( (string) $opts[ $key ], -strlen( $token ) ) === $token;
			if ( 0 === strpos( $sql, 'UPDATE' ) ) {
				if ( ! $mine ) { return 0; }
				$opts[ $key ] = $this->args[0];
				return 1;
			}
			if ( 0 === strpos( $sql, 'DELETE' ) && $mine ) { unset( $opts[ $key ] ); return 1; }
			return 0;
		}
		public function get_var( $sql ) { return $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ][ \Digitalisimo\Elements\Legacy\Runner::LOCK ] ?? null; }
		public function get_results( $sql ) {
			$rows = array();
			if ( false !== strpos( $sql, 'FROM wp_options' ) ) {
				foreach ( $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ] as $name => $value ) {
					// Las exclusiones son los `NOT LIKE 'prefijo%'` de la propia consulta.
					$excluded = false;
					foreach ( $this->args as $arg ) {
						$arg = (string) $arg;
						if ( '%' !== substr( $arg, 0, 1 ) && '%' === substr( $arg, -1 ) && 0 === strpos( $name, stripslashes( substr( $arg, 0, -1 ) ) ) ) { $excluded = true; }
					}
					if ( ! $excluded && $this->matches( $value ) ) { $rows[] = (object) array( 'option_name' => $name, 'option_value' => is_string( $value ) ? $value : serialize( $value ) ); }
				}
			} elseif ( false !== strpos( $sql, 'FROM wp_postmeta m' ) ) {
				foreach ( $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ] as $id => $meta ) {
					foreach ( $meta as $key => $value ) {
						if ( ! in_array( $key, \Digitalisimo\Elements\Legacy\Aliases::SKIP_META, true ) && ! $this->revision( $id ) && $this->matches( $value ) ) { $rows[] = (object) array( 'post_id' => $id, 'meta_key' => $key, 'meta_value' => $value ); }
					}
				}
			}
			return $rows;
		}
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();

	$widgets = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/';
	foreach ( array( 'brand-carousel', 'brand-grid', 'logo-grid', 'creative-button' ) as $alias ) { require_once $widgets . 'class-' . $alias . '.php'; }
	$legacy = __DIR__ . '/../digitalisimo-migration/legacy/';
	require $legacy . 'class-retire.php';
	require $legacy . 'class-aliases.php';
	require $legacy . 'class-runner.php';
	use Digitalisimo\Elements\Legacy\Aliases;
	use Digitalisimo\Elements\Legacy\Retire;
	use Digitalisimo\Elements\Legacy\Runner;
	t_reset();
	$aliases = array(
		'digitalisimo-brand-carousel'  => Aliases::spec( 'digitalisimo-logos', \Digitalisimo\Elements\Brand_Carousel_Widget::class, \Digitalisimo\Elements\Logos_Widget::class ),
		'digitalisimo-brand-grid'      => Aliases::spec( 'digitalisimo-logos', \Digitalisimo\Elements\Brand_Grid_Widget::class, \Digitalisimo\Elements\Logos_Widget::class ),
		'digitalisimo-logo-grid'       => Aliases::spec( 'digitalisimo-logos', \Digitalisimo\Elements\Logo_Grid_Widget::class, \Digitalisimo\Elements\Logos_Widget::class ),
		'digitalisimo-creative-button' => Aliases::spec( 'digitalisimo-advanced-button', \Digitalisimo\Elements\Creative_Button_Widget::class, \Digitalisimo\Elements\Advanced_Button_Widget::class ),
	);
	check_runner( 'digi-brand-carousel' === $aliases['digitalisimo-brand-carousel']['prefix'] && 'digi-logos' === $aliases['digitalisimo-brand-carousel']['target_prefix'] && 'slide' === $aliases['digitalisimo-brand-carousel']['elements']['item'], 'El alias se describe con sus clases de antes y las del fusionado.' );

	/* -------- Plugins que se retiran -------- */
	$GLOBALS['t']['plugins'] = array(
		'elementor/elementor.php'                         => array( 'Name' => 'Elementor', 'Version' => '4.4.0' ),
		'elementor-pro/elementor-pro.php'                 => array( 'Name' => 'Elementor Pro', 'Version' => '4.3.0' ),
		'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'Name' => 'Element Pack Pro', 'Version' => '9.9.1' ),
		'pro-elements/pro-elements.php'                   => array( 'Name' => 'PRO Elements', 'Version' => '4.3.0' ),
		'digitalisimo-elements/pro-elements.php'          => array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.153' ),
		'copia/elementor.php'                             => array( 'Name' => 'Elementor', 'Version' => '4.3.5' ),
		'otro/elementor.php'                              => array( 'Name' => 'Otro constructor', 'Version' => '1.0' ),
	);
	$installed = Retire::installed();
	check_runner( array( 'bdthemes-element-pack/bdthemes-element-pack.php', 'elementor-pro/elementor-pro.php', 'pro-elements/pro-elements.php', 'elementor/elementor.php', 'copia/elementor.php' ) === array_keys( $installed ), 'Se reconocen por archivo y nombre, en orden de retirada, en cualquier carpeta, sin DIGITALÍSIMO Elements ni plugins ajenos con el mismo archivo: ' . implode( ', ', array_keys( $installed ) ) );
	check_runner( $installed['elementor/elementor.php']['core'] && ! $installed['elementor-pro/elementor-pro.php']['core'], 'Sólo Elementor es el núcleo.' );
	$GLOBALS['t']['options'][1]['active_plugins'] = array( 'elementor/elementor.php', 'bdthemes-element-pack/bdthemes-element-pack.php', 'digitalisimo-elements/pro-elements.php' );
	$GLOBALS['t']['no_core'] = true;
	$result = Retire::deactivate( false );
	check_runner( array( 'bdthemes-element-pack/bdthemes-element-pack.php' => 'Element Pack Pro' ) === $result['deactivated'] && $result['warnings'] && in_array( 'elementor/elementor.php', get_option( 'active_plugins' ), true ), 'Sin núcleo incluido disponible, Elementor no se desactiva y se avisa.' );
	$GLOBALS['t']['no_core'] = false;
	$result = Retire::deactivate( false );
	check_runner( array( 'elementor/elementor.php' => 'Elementor' ) === $result['deactivated'] && str_contains( implode( ' ', $result['warnings'] ), '4.4.0' ) && array( 'digitalisimo-elements/pro-elements.php' ) === get_option( 'active_plugins' ), 'Con núcleo incluido, Elementor se desactiva; si era más nuevo, se avisa. DIGITALÍSIMO Elements sigue activo.' );
	check_runner( array( array( 'bdthemes-element-pack/bdthemes-element-pack.php', false ), array( 'elementor/elementor.php', false ) ) === $GLOBALS['t']['deactivated'], 'Sólo se desactiva en el sitio: nada se borra.' );

	/* -------- Alias: clases CSS -------- */
	$grid  = $aliases['digitalisimo-brand-grid'];
	$css   = 'selector .digi-brand-grid__image{a:1}selector .digi-brand-grid__logo--x{b:1}selector .digi-brand-grid{c:1}selector .digi-brand-grid--grid .digi-brand-grid__card{d:1}selector .digi-brand-grid__name{e:1}selector .digi-brand-gridx,.digi-brand-grid-extra,.digi-carousel{f:1}';
	$after = Aliases::rewrite_css( $css, $grid['prefix'], $grid['target_prefix'], $grid['elements'] );
	check_runner( 'selector .digi-logos__card{a:1}selector .digi-logos__image--x{b:1}selector .digi-logos{c:1}selector .digi-logos--grid .digi-brand-grid__card{d:1}selector .digi-logos__name{e:1}selector .digi-brand-gridx,.digi-brand-grid-extra,.digi-carousel{f:1}' === $after, 'Cada clase de antes pasa a su nombre unificado, con modificadores; la que el alias nunca imprimía y las ajenas no cambian: ' . $after );
	$carousel = $aliases['digitalisimo-brand-carousel'];
	check_runner( 'selector .digi-logos__item--active .digi-logos__link-text{}' === Aliases::rewrite_css( 'selector .digi-brand-carousel__slide--active .digi-brand-carousel__website{}', $carousel['prefix'], $carousel['target_prefix'], $carousel['elements'] ), 'Los nombres con guion y los renombrados se traducen.' );

	/* -------- Alias: CSS limitado al propio elemento -------- */
	check_runner( Aliases::scoped( 'selector .digi-brand-carousel__card{color:red}', 'digi-brand-carousel' ), 'Una regla del propio elemento se puede reescribir.' );
	check_runner( Aliases::scoped( '@media (max-width:767px){selector .digi-brand-carousel__slide{color:red}}', 'digi-brand-carousel' ), 'Dentro de @media también.' );
	check_runner( Aliases::scoped( 'selector{content:"digi-brand-carousel"} /* .digi-brand-carousel{} */', 'digi-brand-carousel' ), 'Un valor o un comentario no nombran clases.' );
	check_runner( ! Aliases::scoped( '.digi-brand-carousel__card{color:red}', 'digi-brand-carousel' ), 'Una regla sin `selector` alcanza a otros widgets.' );
	check_runner( ! Aliases::scoped( 'selector .x, .digi-brand-carousel__card{color:red}', 'digi-brand-carousel' ), 'Cada selector de la lista cuenta.' );
	check_runner( ! Aliases::scoped( 'selector [class*="digi-brand-carousel"]{color:red}', 'digi-brand-carousel' ), 'Un selector de atributo con el prefijo no se puede reescribir.' );
	check_runner( Aliases::scoped( 'selector[data-widget_type="digitalisimo-brand-carousel.default"]{}', 'digitalisimo-brand-carousel.default', true ), 'El tipo del envoltorio en un selector propio sí.' );

	/* -------- Alias: conversión de un documento -------- */
	Aliases::set_map( $aliases );
	$doc = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-brand-carousel', 'settings' => array( 'brand_items' => array( array( '_id' => 'r1', 'brand_name' => 'Uno', 'image' => array( 'url' => 'u.png' ) ) ), 'item_gap' => array( 'size' => 8 ), 'item_gap_mobile' => array( 'size' => 4 ), 'custom_css' => 'selector .digi-brand-carousel__slide{x:1} selector.elementor-widget-digitalisimo-brand-carousel{y:1}' ), 'elements' => array() ),
		array( 'id' => 'w2', 'elType' => 'widget', 'widgetType' => 'digitalisimo-creative-button', 'settings' => array( 'effect' => 'lift', 'text' => 'Ver' ), 'elements' => array() ),
		array( 'id' => 'w3', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Igual' ), 'elements' => array() ),
	) ) );
	list( $converted, $changes ) = Aliases::convert_data( $doc );
	$w1 = $converted[0]['elements'][0];
	$w2 = $converted[0]['elements'][1];
	check_runner( 2 === $changes && 'digitalisimo-logos' === $w1['widgetType'] && 'digitalisimo-advanced-button' === $w2['widgetType'] && $doc[0]['elements'][2] === $converted[0]['elements'][2], 'Los dos alias anidados pasan a su widget fusionado; lo demás no cambia.' );
	check_runner( 'Uno' === $w1['settings']['items'][0]['name'] && 'yes' === $w1['settings']['items'][0]['caption'] && 'carousel' === $w1['settings']['layout'] && 8 === $w1['settings']['gap']['size'] && 4 === $w1['settings']['gap_mobile']['size'] && ! isset( $w1['settings']['brand_items'], $w1['settings']['item_gap'] ), 'Los ajustes se traducen como al pintarse el alias, también los responsivos.' );
	check_runner( 'selector .digi-logos__item{x:1} selector.elementor-widget-digitalisimo-logos{y:1}' === $w1['settings']['custom_css'], 'El CSS propio pasa a las clases del fusionado: ' . $w1['settings']['custom_css'] );
	check_runner( 'g' === $w2['settings']['button_effect'] && 'fill' !== $w2['settings']['button_effect'] && ! isset( $w2['settings']['effect'] ), 'El botón creativo conserva su efecto.' );
	list( $again, $none ) = Aliases::convert_data( $converted );
	check_runner( 0 === $none && $again === $converted, 'Convertir dos veces no cambia nada.' );
	list( $skipped, $only ) = Aliases::convert_data( $doc, array( 'digitalisimo-brand-carousel' ) );
	check_runner( 1 === $only && 'digitalisimo-brand-carousel' === $skipped[0]['elements'][0]['widgetType'] && $doc[0]['elements'][0] === $skipped[0]['elements'][0], 'Un tipo bloqueado se queda tal cual.' );

	/* -------- Alias: CSS ajeno que impide convertir -------- */
	$GLOBALS['t']['meta'][1][30] = array( '_elementor_data' => json_encode( $doc ) );
	$GLOBALS['t']['meta'][1][31] = array( '_elementor_data' => json_encode( array( array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'html', 'settings' => array( 'html' => '<style>.digi-logo-grid__item{outline:0}</style>' ), 'elements' => array() ) ) ) );
	$GLOBALS['t']['meta'][1][32] = array( '_elementor_data' => json_encode( array( array( 'id' => 'g1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-brand-grid', 'settings' => array( 'custom_css' => '.digi-brand-grid__image{x:1}' ), 'elements' => array() ) ) ) );
	$GLOBALS['t']['meta'][1][33] = array( '_elementor_page_settings' => serialize( array( 'custom_css' => 'body .elementor-widget-digitalisimo-creative-button{z:1}' ) ) );
	$GLOBALS['t']['options'][1]['digitalisimo_migration_report'] = array( 'x' => 'digi-brand-carousel' );
	$refs = Aliases::references();
	check_runner( array( 'digitalisimo-brand-grid', 'digitalisimo-creative-button', 'digitalisimo-logo-grid' ) === array_keys( $refs ), 'Bloquean el CSS de otro elemento, el CSS sin `selector` del propio alias y el de la página; el propio limitado y las opciones de la migración no: ' . implode( ', ', array_keys( $refs ) ) );
	check_runner( str_contains( $refs['digitalisimo-logo-grid'][0], 'elemento h1' ) && str_contains( $refs['digitalisimo-creative-button'][0], '_elementor_page_settings' ), 'Cada bloqueo dice dónde está el CSS.' );
	check_runner( array( 30, 32 ) === Aliases::documents() && array( 32 ) === Aliases::documents( array( 'digitalisimo-brand-grid' ) ) && array( 32 ) === Aliases::documents( null, 30 ) && array( 30 ) === Aliases::documents( null, 0, 1 ), 'Documentos con alias, por tipo, desde un cursor y por lotes.' );
	// El envoltorio del widget fusionado en CSS ajeno bloquea todos sus alias; la auditoría de SEO no cuenta.
	$GLOBALS['t']['options'][1]['digitalisimo_quality_audit_abc'] = array( 'images' => array( array( 'cls' => 'digi-logos__image digi-brand-carousel__image' ) ) );
	$GLOBALS['t']['options'][1]['widget_custom_html'] = array( 2 => array( 'content' => '<style>.elementor-widget-digitalisimo-logos{width:50%}</style>' ) );
	$refs = Aliases::references();
	check_runner( isset( $refs['digitalisimo-brand-carousel'] ) && str_contains( implode( ' ', $refs['digitalisimo-brand-carousel'] ), 'widget_custom_html' ) && ! str_contains( implode( ' ', $refs['digitalisimo-brand-carousel'] ), 'quality_audit' ), 'Un CSS que nombra el envoltorio del fusionado bloquea sus alias; los registros de la auditoría no.' );
	unset( $GLOBALS['t']['options'][1]['widget_custom_html'] );
	// El CSS propio del alias: sólo las formas que se reescriben exactamente.
	check_runner( Aliases::own_css_ok( 'digitalisimo-brand-carousel', 'selector .digi-brand-carousel__slide{a:1} selector.elementor-widget-digitalisimo-brand-carousel{b:1} selector[data-widget_type="digitalisimo-brand-carousel.default"]{c:1}' ), 'Clases, envoltorio y tipo con .default en reglas del propio elemento se reescriben.' );
	check_runner( ! Aliases::own_css_ok( 'digitalisimo-brand-carousel', 'selector[data-widget_type^="digitalisimo-brand-carousel"] .digi-logos__card{a:1}' ), 'El nombre del alias en otra forma no se reescribe: bloquea.' );
	check_runner( ! Aliases::own_css_ok( 'digitalisimo-brand-carousel', 'selector ~ .digi-brand-carousel__slide{a:1}' ) && ! Aliases::own_css_ok( 'digitalisimo-brand-carousel', 'selector + * .digi-brand-carousel{a:1}' ), 'Una regla que alcanza a hermanos bloquea.' );
	$aliases_with_switcher = $aliases + array( 'digitalisimo-content-switcher' => Aliases::spec( 'digitalisimo-fancy-tabs', 'Fake_Switcher', 'Fake_Tabs' ) );
	Aliases::set_map( $aliases_with_switcher );
	check_runner( ! Aliases::own_css_ok( 'digitalisimo-content-switcher', 'selector .digi-content-switcher__panel{padding:40px}', array( 'switcher_items' => array( array( 'template_id' => '9' ) ) ) ) && Aliases::own_css_ok( 'digitalisimo-content-switcher', 'selector .digi-content-switcher__panel{padding:40px}', array( 'switcher_items' => array( array( 'content' => 'x' ) ) ) ), 'Un alternador con plantillas dentro no se reescribe; sin plantillas, sí.' );
	Aliases::set_map( $aliases );

	/* -------- Tarea del sitio -------- */
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['plugins'] = array( 'elementor/elementor.php' => array( 'Name' => 'Elementor', 'Version' => '4.3.5' ), 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'Name' => 'Element Pack Pro', 'Version' => '9.9.1' ) );
	$GLOBALS['t']['options'][1]['active_plugins'] = array( 'elementor/elementor.php', 'bdthemes-element-pack/bdthemes-element-pack.php', 'digitalisimo-elements/pro-elements.php' );
	$GLOBALS['t']['ep_loaded'] = true;
	$GLOBALS['t']['docs'] = array( 10, 11, 12 );
	$GLOBALS['t']['revisions'] = array( 20, 70, 80 );
	foreach ( array( 20 => 10, 70 => 7, 80 => 8 ) as $rev => $parent ) { $GLOBALS['t']['types'][ $rev ] = 'revision'; $GLOBALS['t']['parents'][ $rev ] = $parent; }
	$GLOBALS['t']['crash'][11] = 2;
	// La revisión 70 sobrescribió la página 7 (se recupera); la 80, la página 8 (sin origen: se lista y se conserva).
	$GLOBALS['t']['overwritten'] = array( 70 => 7, 80 => 8 );
	$GLOBALS['t']['meta'][1][30] = array( '_elementor_data' => json_encode( $doc ) );
	$state = Runner::site_step( true, 100 );
	check_runner( ! empty( $state['pause'] ) && 'recover' === $state['stage'] && array( 'Element Pack Pro', 'Elementor' ) === $state['counts']['deactivated'] && empty( $state['done'] ) && $state['deactivated_at'] > 0, 'La primera petición desactiva y se pausa: la siguiente corre sin esos plugins.' );
	check_runner( (int) $state['started'] === (int) get_option( 'digitalisimo_ep_conditions_repair_cutoff' ), 'Fija el corte de la reparación antes de convertir nada.' );
	check_runner( ! array_key_exists( Runner::LOCK, $GLOBALS['t']['options'][1] ) && isset( $GLOBALS['t']['cron'][1][ Runner::CRON ] ), 'Suelta el cerrojo y deja programada la continuación.' );
	// Un proceso que arrancó antes de la desactivación (con Element Pack cargado) no trabaja.
	$_SERVER['REQUEST_TIME_FLOAT'] = $state['deactivated_at'] - 5;
	$stale = Runner::site_step( false, 100 );
	check_runner( ! empty( $stale['pause'] ) && 'recover' === $stale['stage'] && array() === $GLOBALS['t']['converted'], 'Un proceso anterior a la desactivación se pausa sin convertir nada.' );
	$GLOBALS['t']['ep_loaded'] = false;
	new_request();
	$crashed = 0;
	for ( $i = 0; $i < 6 && empty( $state['done'] ); $i++ ) {
		try {
			$state = Runner::site_step( false, 100 );
		} catch ( \RuntimeException $e ) {
			++$crashed;
			check_runner( ! array_key_exists( Runner::LOCK, $GLOBALS['t']['options'][1] ), 'Una caída no deja el cerrojo tomado.' );
		}
		new_request();
	}
	check_runner( 2 === $crashed && ! empty( $state['done'] ), 'El documento que tumba la petición se reintenta una vez y después se salta (' . $crashed . ' caídas).' );
	check_runner( array( 10, 12, 20, 70 ) === $GLOBALS['t']['converted'] && array( 11 ) === $state['counts']['failed'] && 2 === $state['counts']['converted'] && 2 === $state['counts']['revisions'], 'Se convierten documentos y revisiones; el que falló queda listado; la revisión que delata una página sin origen se conserva: ' . implode( ',', $GLOBALS['t']['converted'] ) );
	check_runner( 1 === $state['counts']['recovered'] && array( 8 ) === $state['review'] && array( 80 ) === $state['keep_revisions'] && 1 === $state['counts']['repaired'] && 1 === $GLOBALS['t']['repair_calls'], 'Recupera lo que puede de conversiones antiguas, lista lo demás y repara las reglas una vez.' );
	$doc30 = json_decode( $GLOBALS['t']['meta'][1][30]['_elementor_data'], true );
	check_runner( 1 === $state['counts']['aliases'] && 2 === $state['counts']['alias_elements'] && 'digitalisimo-logos' === $doc30[0]['elements'][0]['widgetType'] && '' !== $GLOBALS['t']['meta'][1][30]['_digitalisimo_ep_backup'] && in_array( 30, $GLOBALS['t']['cleared'], true ), 'Los alias se convierten con copia del original y cachés vaciadas.' );
	check_runner( ! array_key_exists( Runner::JOB, $GLOBALS['t']['options'][1] ) && ! empty( get_option( Runner::REPORT )['done'] ) && ! isset( $GLOBALS['t']['cron'][1][ Runner::CRON ] ), 'Al terminar queda el informe, sin tarea ni continuación programada.' );
	$html = Runner::render( get_option( Runner::REPORT ) );
	check_runner( str_contains( $html, 'Migración completada' ) && str_contains( $html, 'Element Pack Pro, Elementor' ) && str_contains( $html, 'Página 11' ) && str_contains( $html, 'Página 8' ), 'El informe dice qué se desactivó y qué revisar.' );
	check_runner( str_contains( $html, 'listos para borrarse desde Plugins cuando hayas comprobado el sitio: Elementor.' ) && str_contains( $html, 'aún no los borres: Element Pack Pro' ), 'Elementor puede borrarse; Element Pack no, mientras quede contenido suyo que falló al convertir: ' . strip_tags( $html ) );
	$again = Runner::site_step( true, 100 );
	check_runner( ! empty( $again['done'] ) && empty( $again['counts']['deactivated'] ) && 1 === $again['counts']['converted'] && array( 10, 12, 20, 70, 11 ) === $GLOBALS['t']['converted'] && array() === $again['counts']['failed'], 'Pulsar Migrar otra vez es seguro: sólo reintenta el documento que había fallado.' );
	check_runner( array( 8 ) === $again['review'] && 1 === $GLOBALS['t']['repair_calls'], 'La página sin origen sigue listada y la reparación de reglas no se repite.' );
	// Un reintento del mismo clic (se perdió la respuesta de una pasada que terminó) no lanza otra pasada.
	$once = Runner::site_step( true, 100, null, 'clic1' );
	$converted_before = $GLOBALS['t']['converted'];
	$retry = Runner::site_step( true, 100, null, 'clic1' );
	check_runner( ! empty( $once['done'] ) && ! empty( $retry['idle'] ) && $converted_before === $GLOBALS['t']['converted'] && 'clic1' === get_option( Runner::REPORT )['run'], 'El mismo clic repetido no repite la migración.' );
	// Un fallo de una revisión se presenta como revisión, con enlace a la pantalla de revisiones.
	$GLOBALS['t']['crash'][80] = 0;
	$info = ( new \ReflectionMethod( Runner::class, 'documents_info' ) );
	$info->setAccessible( true );
	$rev = $info->invoke( null, array( 80 ) );
	check_runner( str_starts_with( $rev[80]['title'], 'Revisión de «Página 8»' ) && str_contains( $rev[80]['link'], 'revision.php?revision=80' ), 'Una revisión del informe se reconoce y enlaza a sus revisiones.' );

	// Cerrojo: otra petición en curso, abandonado, y nunca se suelta el de otro.
	$GLOBALS['t']['options'][1][ Runner::LOCK ] = time() . '|ajeno';
	$busy = Runner::site_step( true, 100 );
	check_runner( ! empty( $busy['busy'] ) && time() . '|ajeno' === $GLOBALS['t']['options'][1][ Runner::LOCK ], 'Con otra petición trabajando, ésta espera y no toca su cerrojo.' );
	$GLOBALS['t']['options'][1][ Runner::LOCK ] = ( time() - Runner::STALE - 5 ) . '|muerto';
	check_runner( empty( Runner::site_step( true, 100 )['busy'] ) && ! array_key_exists( Runner::LOCK, $GLOBALS['t']['options'][1] ), 'Un cerrojo abandonado se recupera y el propio se suelta al terminar.' );

	// Sin permiso para desactivar, con Element Pack activo: no convierte nada (ni alias) y lo explica.
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['plugins'] = array( 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'Name' => 'Element Pack Pro', 'Version' => '9.9.1' ) );
	$GLOBALS['t']['options'][1]['active_plugins'] = array( 'bdthemes-element-pack/bdthemes-element-pack.php' );
	$GLOBALS['t']['ep_loaded'] = true;
	$GLOBALS['t']['docs'] = array( 10 );
	$GLOBALS['t']['meta'][1][30] = array( '_elementor_data' => json_encode( $doc ) );
	$GLOBALS['t']['can'] = false;
	$state = Runner::site_step( true, 100 );
	check_runner( ! empty( $state['done'] ) && array() === $GLOBALS['t']['deactivated'] && array() === $GLOBALS['t']['converted'] && 0 === $state['counts']['aliases'] && str_contains( implode( ' ', $state['warnings'] ), 'no puede desactivar' ) && str_contains( implode( ' ', $state['warnings'] ), 'Element Pack sigue activo' ), 'Sin permiso no desactiva ni convierte con Element Pack activo (tampoco los alias), y lo dice.' );
	check_runner( Runner::has_pending( $state ) && str_contains( Runner::render( $state ), 'Pendiente de revisar' ), 'El informe lo marca como pendiente.' );
	// Element Pack cargado en el proceso pero ya desactivado en las opciones: se espera a una petición nueva.
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['plugins'] = array( 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'Name' => 'Element Pack Pro', 'Version' => '9.9.1' ) );
	$GLOBALS['t']['ep_loaded'] = true;
	$GLOBALS['t']['docs'] = array( 10 );
	$state = Runner::site_step( true, 100 );
	check_runner( ! empty( $state['pause'] ) && empty( $state['done'] ) && 1 === $state['pauses'], 'Un proceso con Element Pack cargado que las opciones ya no tienen activo se pausa.' );
	$GLOBALS['t']['ep_loaded'] = false;
	$state = Runner::site_step( false, 100 );
	check_runner( ! empty( $state['done'] ) && array( 10 ) === $GLOBALS['t']['converted'], 'La petición siguiente convierte.' );
	// Elementor Pro activo: DIGITALÍSIMO Elements no carga sus widgets y la conversión espera.
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['docs'] = array( 10 );
	$GLOBALS['t']['pro_conflict'] = true;
	$state = Runner::site_step( true, 100 );
	check_runner( ! empty( $state['done'] ) && array() === $GLOBALS['t']['converted'] && str_contains( implode( ' ', $state['warnings'] ), 'Elementor Pro o PRO Elements sigue activo' ), 'Con Elementor Pro activo no se convierte nada.' );
	// Una etapa sin documentos que tumba la petición dos veces se salta.
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['options'][1][ Runner::JOB ] = array_merge( Runner::fresh( true ), array( 'stage' => 'references', 'stage_attempts' => 2 ) );
	$state = Runner::site_step( false, 100 );
	check_runner( ! empty( $state['done'] ) && str_contains( implode( ' ', $state['warnings'] ), 'no pudo completarse' ), 'Una etapa que se cortó dos veces se salta y se avisa.' );

	/* -------- Red -------- */
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['multisite'] = true;
	$GLOBALS['t']['plugins'] = array( 'bdthemes-element-pack/bdthemes-element-pack.php' => array( 'Name' => 'Element Pack Pro', 'Version' => '9.9.1' ), 'elementor/elementor.php' => array( 'Name' => 'Elementor', 'Version' => '4.3.5' ), 'elementor-pro/elementor-pro.php' => array( 'Name' => 'Elementor Pro', 'Version' => '4.3.0' ) );
	$GLOBALS['t']['site_options']['active_sitewide_plugins'] = array( 'bdthemes-element-pack/bdthemes-element-pack.php' => 1, 'digitalisimo-migration/digitalisimo-migration.php' => 1 );
	$GLOBALS['t']['options'][2]['active_plugins'] = array( 'digitalisimo-elements/pro-elements.php', 'elementor/elementor.php', 'elementor-pro/elementor-pro.php' );
	$job = Runner::network_start( true );
	check_runner( array() === $GLOBALS['t']['deactivated'] && str_contains( implode( ' ', $job['warnings'] ), 'no está activado en toda la red' ) && array( 2 ) === $job['sites'] && array( 1 ) === $job['skipped'], 'Sin DIGITALÍSIMO Elements en toda la red no se desactiva nada desde la red, y el sitio sin él no se toca.' );
	check_runner( ! isset( $GLOBALS['t']['options'][1][ Runner::JOB ] ) && true === $GLOBALS['t']['options'][2][ Runner::JOB ]['capable'] && (int) $job['started'] === $GLOBALS['t']['options'][2][ Runner::JOB ]['network'] && isset( $GLOBALS['t']['cron'][2][ Runner::CRON ] ), 'Encola la tarea del sitio con DIGITALÍSIMO Elements, autorizada y con su continuación.' );
	check_runner( 'running' === $job['status'][2]['status'] && array( array( 'id' => 2, 'name' => 'Sitio 2', 'url' => 'https://s2.test/', 'ajax' => 'https://s2.test/wp-admin/admin-ajax.php', 'nonce' => 'n-digitalisimo_migration_step_2' ) ) === Runner::network_pending_sites( $job ), 'La pantalla de red conduce cada sitio por su propia administración.' );
	check_runner( 1 === $GLOBALS['t']['blog'] && empty( $GLOBALS['t']['stack'] ), 'Cada cambio de sitio se deshace.' );
	// El sitio 2 corre su tarea en sus propias peticiones, con la autorización de la red aunque quien siga no pueda desactivar.
	$GLOBALS['t']['can'] = false;
	switch_to_blog( 2 );
	$state = Runner::site_step( false, 100 );
	new_request();
	$state = Runner::site_step( false, 100 );
	restore_current_blog();
	check_runner( ! empty( $state['done'] ) && array( array( 'elementor-pro/elementor-pro.php', false ), array( 'elementor/elementor.php', false ) ) === $GLOBALS['t']['deactivated'], 'El sitio desactiva lo suyo con la autorización de la red.' );
	$summary = Runner::network_summary();
	check_runner( ! empty( $summary['done'] ) && 'done' === $summary['status'][2]['status'] && ! empty( Runner::network_state()['done'] ), 'Con todos los sitios terminados, la red da la migración por terminada.' );
	// Con DIGITALÍSIMO Elements en toda la red: Elementor Pro de cada sitio se desactiva antes que Element Pack de la red.
	$GLOBALS['t']['can'] = true;
	$GLOBALS['t']['deactivated'] = array();
	$GLOBALS['t']['options'][2]['active_plugins'] = array( 'elementor-pro/elementor-pro.php' );
	$GLOBALS['t']['site_options']['active_sitewide_plugins']['digitalisimo-elements/pro-elements.php'] = 1;
	$job = Runner::network_start( true );
	check_runner( array( array( 'elementor-pro/elementor-pro.php', false ), array( 'bdthemes-element-pack/bdthemes-element-pack.php', true ) ) === $GLOBALS['t']['deactivated'] && array( 'Element Pack Pro' ) === $job['deactivated'] && array( 1, 2 ) === $job['sites'] && empty( $job['done'] ), 'Desactiva Elementor Pro en cada sitio y después lo activado en toda la red, y encola los dos sitios.' );
	check_runner( in_array( 'Elementor Pro', $GLOBALS['t']['options'][2][ Runner::JOB ]['counts']['deactivated'], true ) && $GLOBALS['t']['options'][2][ Runner::JOB ]['deactivated_at'] > 0, 'La tarea del sitio sabe lo que la red desactivó por él.' );
	check_runner( $job['started'] === Runner::network_start( true )['started'], 'Pulsar otra vez continúa la misma migración de red.' );
	// Una tarea del sitio en marcha, creada sin permiso y ya pasada la desactivación, recibe la autorización de la red.
	t_reset();
	Aliases::set_map( $aliases );
	$GLOBALS['t']['multisite'] = true;
	$GLOBALS['t']['plugins'] = array( 'elementor/elementor.php' => array( 'Name' => 'Elementor', 'Version' => '4.3.5' ) );
	$GLOBALS['t']['site_options']['active_sitewide_plugins'] = array( 'digitalisimo-elements/pro-elements.php' => 1 );
	$GLOBALS['t']['options'][2]['active_plugins'] = array( 'elementor/elementor.php' );
	$GLOBALS['t']['options'][2][ Runner::JOB ] = array_merge( Runner::fresh( false ), array( 'stage' => 'convert' ) );
	Runner::network_start( true );
	$queued = $GLOBALS['t']['options'][2][ Runner::JOB ];
	check_runner( true === $queued['capable'] && 'prepare' === $queued['stage'] && ! isset( $GLOBALS['t']['options'][2][ Runner::LOCK ] ), 'La tarea sin permiso recibe el de la red y vuelve a desactivar, bajo el cerrojo del sitio.' );
	switch_to_blog( 2 );
	new_request();
	$state = Runner::site_step( false, 100 );
	restore_current_blog();
	check_runner( array( array( 'elementor/elementor.php', false ) ) === $GLOBALS['t']['deactivated'], 'Y desactiva lo que seguía activo en el sitio.' );

	/* -------- Origen permitido para la pantalla de red -------- */
	$_REQUEST['action'] = Runner::AJAX;
	$GLOBALS['t']['ajax'] = true;
	check_runner( array( 'https://s2.test', 'https://red.test:8443' ) === Runner::allowed_origins( array( 'https://s2.test' ) ), 'Sólo esta acción admite el origen de la administración de red.' );
	$_REQUEST['action'] = 'otra';
	check_runner( array( 'https://s2.test' ) === Runner::allowed_origins( array( 'https://s2.test' ) ), 'Las demás acciones no cambian.' );

	echo "DIGITALÍSIMO Migración: migrar en un clic validado.\n";
}
