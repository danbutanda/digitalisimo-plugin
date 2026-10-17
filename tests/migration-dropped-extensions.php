<?php
/**
 * Lo que «Migrar» todavía no convierte ya no se pierde sin aviso (DIGITALÍSIMO Migración): las extensiones de Element
 * Pack sin equivalente se listan por documento (nombre visible, clave detectada, elemento e ID) en el análisis previo y
 * en el informe final del sitio y de la red, también las que la conversión retira del elemento, leídas de la copia
 * original (pulsar Migrar otra vez no las borra); el Element Pack Addon y Prime Slider se detectan e informan sin
 * desactivarlos; todo eso es información y no deja el sitio pendiente para siempre, salvo widgets de Prime Slider que no
 * se ven; y lo convertible se convierte igual que antes. Documentos sintéticos y conversión real (Native) con un
 * WordPress mínimo de dos sitios.
 */
namespace Digitalisimo\Elements {
	/** Los widgets propios están cargados (la conversión lo exige). */
	final class Widget_Registry {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', '/wp/plugins/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_MIGRATION_VERSION', '9.9.9' );
	function check_dropped( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }

	/* -------- WordPress mínimo: dos sitios con opciones, metadatos y tipos de entrada propios -------- */
	function t_reset() {
		$GLOBALS['t'] = array( 'blog' => 1, 'stack' => array(), 'multisite' => false, 'options' => array( 1 => array(), 2 => array() ), 'site_options' => array(), 'meta' => array( 1 => array(), 2 => array() ), 'types' => array(), 'parents' => array(), 'plugins' => array(), 'deactivated' => array(), 'cron' => array() );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) + 1;
	}
	t_reset();
	function new_request() { $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) + 1; usleep( 1000 ); }
	function get_current_blog_id() { return $GLOBALS['t']['blog']; }
	function get_current_network_id() { return 1; }
	function wp_generate_password( $n, $s ) { return 'tok' . mt_rand( 100000, 999999 ); }
	function wp_cache_delete( $k, $g ) { return true; }
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
	function get_bloginfo( $k = '' ) { return 'Sitio ' . $GLOBALS['t']['blog']; }
	function home_url( $p = '' ) { return 'https://s' . $GLOBALS['t']['blog'] . '.test' . $p; }
	function admin_url( $p = '' ) { return 'https://s' . $GLOBALS['t']['blog'] . '.test/wp-admin/' . $p; }
	function network_admin_url( $p = '' ) { return 'https://red.test/wp-admin/network/' . $p; }
	function wp_parse_url( $u ) { return parse_url( $u ); }
	function get_metadata( $type, $id, $key, $single ) { return $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] ?? ''; }
	function get_post_meta( $id, $key, $single ) { return get_metadata( 'post', $id, $key, $single ); }
	function add_metadata( $type, $id, $key, $value, $unique ) { $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value; return true; }
	function update_metadata( $type, $id, $key, $value ) { $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value; return true; }
	function delete_metadata( $type, $id, $key ) { unset( $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ][ $id ][ $key ] ); return true; }
	function get_post( $id ) { return isset( $GLOBALS['t']['types'][ $id ] ) ? (object) array( 'ID' => $id, 'post_type' => $GLOBALS['t']['types'][ $id ], 'post_parent' => $GLOBALS['t']['parents'][ $id ] ?? 0, 'post_title' => 'Página ' . $id, 'post_modified' => '2026-10-16 10:00:00', 'post_modified_gmt' => '2026-10-16 10:00:00' ) : null; }
	function wp_is_post_revision( $id ) { return 'revision' === ( $GLOBALS['t']['types'][ $id ] ?? '' ) ? (int) ( $GLOBALS['t']['parents'][ $id ] ?? 0 ) : false; }
	function wp_is_post_autosave( $id ) { return false; }
	function wp_slash( $v ) { return is_string( $v ) ? addslashes( $v ) : $v; }
	function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
	function wp_json_encode( $v ) { return json_encode( $v ); }
	function apply_filters( $hook, $value ) { return $value; }
	function add_action() {} function add_filter() {}
	function current_user_can( $cap ) { return true; }
	function is_super_admin() { return true; }
	function wp_doing_ajax() { return false; }
	function wp_doing_cron() { return false; }
	function wp_raise_memory_limit( $c ) {} function wp_convert_hr_to_bytes( $v ) { return (int) $v * 1048576; }
	function wp_next_scheduled( $hook ) { return $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] ?? false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] = $time; }
	function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['t']['cron'][ $GLOBALS['t']['blog'] ][ $hook ] ); }
	function wp_create_nonce( $a ) { return 'n-' . $a; }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $v ) { return esc_html( $v ); }
	function esc_url( $v ) { return esc_html( $v ); }
	function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
	function wp_kses_post( $v ) { return (string) $v; }
	function absint( $v ) { return abs( (int) $v ); }
	function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
	function is_email( $v ) { return false !== strpos( (string) $v, '@' ); }
	function get_term( $id ) { return (object) array( 'term_id' => $id, 'term_taxonomy_id' => $id ); }
	function get_the_title( $id ) { return 'Página ' . $id; }
	function wp_date( $f, $t ) { return gmdate( $f, (int) $t ); }
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
	/** $wpdb mínimo: busca en los metadatos simulados del sitio en curso los textos de las condiciones LIKE. */
	final class Fake_Wpdb {
		public $postmeta = 'wp_postmeta'; public $posts = 'wp_posts'; public $options = 'wp_options'; public $sitemeta = 'wp_sitemeta';
		public $queries = array();
		private $args = array();
		public function esc_like( $t ) { return addcslashes( $t, '_%\\' ); }
		public function prepare( $sql, ...$args ) { $this->args = 1 === count( $args ) && is_array( $args[0] ) ? $args[0] : $args; return $sql; }
		private function needles() {
			$out = array();
			foreach ( $this->args as $arg ) {
				if ( is_string( $arg ) && '%' === substr( $arg, 0, 1 ) ) { $out[] = stripslashes( trim( $arg, '%' ) ); }
			}
			return $out;
		}
		private function matches( $value ) {
			$value = is_string( $value ) ? $value : serialize( $value );
			foreach ( $this->needles() as $needle ) { if ( false !== strpos( $value, $needle ) ) { return true; } }
			return false;
		}
		public function get_col( $sql ) {
			$this->queries[] = $sql;
			$meta = $GLOBALS['t']['meta'][ $GLOBALS['t']['blog'] ];
			$ids  = array();
			if ( false !== strpos( $sql, 'WHERE meta_key = %s' ) ) {
				foreach ( $meta as $id => $values ) { if ( isset( $values[ $this->args[0] ] ) && '' !== $values[ $this->args[0] ] ) { $ids[] = $id; } }
				sort( $ids );
				return $ids;
			}
			$keys  = false !== strpos( $sql, "IN ( '_elementor_data', '_elementor_page_settings' )" ) ? array( '_elementor_data', '_elementor_page_settings' ) : array( '_elementor_data' );
			// La copia original guarda el JSON del documento dentro de otro: sus comillas van con barra invertida, así
			// que sólo coinciden las condiciones escritas para ella.
			if ( false !== strpos( $sql, "m.meta_key = '_digitalisimo_ep_backup'" ) ) { $keys[] = '_digitalisimo_ep_backup'; }
			$after = false !== strpos( $sql, 'post_id > %d' ) ? (int) $this->args[0] : 0;
			foreach ( $meta as $id => $values ) {
				$revision = 'revision' === ( $GLOBALS['t']['types'][ $id ] ?? 'page' );
				if ( $id <= $after || ( false !== strpos( $sql, "<> 'revision'" ) && $revision ) || ( false !== strpos( $sql, "= 'revision'" ) && ! $revision ) ) { continue; }
				foreach ( $keys as $key ) {
					if ( isset( $values[ $key ] ) && $this->matches( $values[ $key ] ) ) { $ids[] = $id; break; }
				}
			}
			sort( $ids );
			return preg_match( '/LIMIT (\d+)/', $sql, $m ) ? array_slice( $ids, 0, (int) $m[1] ) : $ids;
		}
		/** El cerrojo de la tarea: INSERT IGNORE, robo con UPDATE condicionado, renovación y borrado por token. */
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
		public function get_var( $sql ) {
			if ( false !== strpos( $sql, 'SELECT option_value' ) ) {
				return $GLOBALS['t']['options'][ $GLOBALS['t']['blog'] ][ \Digitalisimo\Elements\Legacy\Runner::LOCK ] ?? null;
			}
			// Última revisión guardada de una página.
			$last = 0;
			foreach ( $GLOBALS['t']['parents'] as $id => $parent ) { if ( (int) $parent === (int) $this->args[0] ) { $last = max( $last, (int) $id ); } }
			return $last;
		}
		public function get_results( $sql ) { return array(); }
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();

	$legacy = __DIR__ . '/../digitalisimo-migration/legacy/';
	require $legacy . 'class-translator.php';
	require $legacy . 'class-native.php';
	require $legacy . 'class-migration.php';
	use Digitalisimo\Elements\Legacy\Aliases;
	use Digitalisimo\Elements\Legacy\Dropped;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Retire;
	use Digitalisimo\Elements\Legacy\Runner;
	// Sin widgets fusionados en este sitio: la etapa de alias no tiene nada que hacer.
	Aliases::set_map( array() );

	/* -------- La tabla: las 28 extensiones sin equivalente, ninguna de las que sí se convierten -------- */
	check_dropped( 28 === count( Dropped::EXTENSIONS ), 'Son las 28 extensiones de Element Pack 10.0.1 sin tratamiento: ' . count( Dropped::EXTENSIONS ) );
	$map     = json_decode( (string) file_get_contents( __DIR__ . '/../docs/migration-map.json' ), true );
	$treated = array_keys( (array) ( $map['extensions'] ?? array() ) );
	check_dropped( $treated && ! array_intersect( $treated, array_keys( Dropped::EXTENSIONS ) ), 'Ninguna extensión que ya se convierte (docs/migration-map.json) se lista como descartada: ' . implode( ', ', array_intersect( $treated, array_keys( Dropped::EXTENSIONS ) ) ) );
	foreach ( Dropped::EXTENSIONS as $ext => $spec ) {
		check_dropped( '' !== $spec['label'] && false === stripos( $spec['label'], 'element pack' ) && $spec['keys'], 'Cada extensión tiene un nombre visible propio y sus claves: ' . $ext );
		foreach ( $spec['keys'] as $key ) {
			check_dropped( ! preg_match( Native::ELEMENT_KEYS, $key ), 'Una clave que la conversión traslada no se da por perdida: ' . $key );
		}
	}

	/* -------- Detección pura, por elemento y en los ajustes de la página -------- */
	$doc = array(
		array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(
			'section_sticky_on'            => 'yes',
			'ep_parallax_effects_show'     => 'yes',
			'element_pack_backdrop_filter' => 'yes',
			'section_particles_on'         => '',
			'section_parallax_elements'    => array(),
			'ep_bg_expand_enable'          => 'no',
		), 'elements' => array(
			array( 'id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola', 'element_pack_widget_tooltip' => 'yes', 'element_pack_widget_tooltip_text' => 'Ayuda', 'ep_notation_active' => 'yes', 'ep_threed_text_active' => 'yes' ), 'elements' => array() ),
			array( 'id' => 'w1', 'elType' => 'widget', 'widgetType' => 'bdt-accordion', 'settings' => array( 'tabs' => array( array( 'tab_title' => 'A' ) ), 'element_pack_widget_transform' => 'yes', 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions' => array() ), 'elements' => array() ),
			array( 'id' => 'c2', 'elType' => 'container', 'settings' => array( 'element_pack_section_transform' => 'wrapper', 'section_parallax_elements' => array( array( '_id' => 'p1', 'section_parallax_depth' => array( 'size' => 0.2 ) ) ), 'element_pack_agbg_show' => 'yes' ), 'elements' => array(
				array( 'id' => 'i1', 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( 'ep_image_hover_effects_on' => 'yes' ), 'elements' => array() ),
			) ),
		) ),
	);
	$found = Dropped::scan_elements( $doc );
	$short = array_map( static function ( $item ) { return $item['ext'] . '|' . $item['key'] . '|' . $item['el'] . '|' . $item['id']; }, $found );
	check_dropped( array(
		'parallax-effects|ep_parallax_effects_show|container|c1',
		'section-sticky|section_sticky_on|container|c1',
		'tooltip|element_pack_widget_tooltip|heading|h1',
		'threed-text|ep_threed_text_active|heading|h1',
		'transform-effects|element_pack_widget_transform|bdt-accordion|w1',
		'transform-effects|element_pack_section_transform|container|c2',
		'image-parallax|section_parallax_elements|container|c2',
		'animated-gradient-background|element_pack_agbg_show|container|c2',
		'image-hover-effects|ep_image_hover_effects_on|image|i1',
	) === $short, 'Cada extensión usada, con su clave, su elemento y su ID, en el orden del documento; nada apagado, vacío ni convertible: ' . implode( ' ; ', $short ) );
	$page = Dropped::scan_page( array( 'ep_custom_footer_script' => '<script>x()</script>', 'ep_hash_link_enable' => 'yes', 'ep_grid_line_enable' => '', 'hide_title' => 'yes' ) );
	check_dropped( array( array( 'ext' => 'hash-link', 'key' => 'ep_hash_link_enable', 'el' => '', 'id' => '' ), array( 'ext' => 'custom-js', 'key' => 'ep_custom_footer_script', 'el' => '', 'id' => '' ) ) === $page, 'Los ajustes de la página también: el JavaScript propio se delata por su código.' );
	check_dropped( 'grid-line' === ( Dropped::scan_page( array( 'ep_grid_line_enable' => 'yes' ) )[0]['ext'] ?? '' ), 'Las líneas de cuadrícula de la página se detectan.' );

	/* -------- Conversión real: lo convertible no cambia; lo descartado se ve antes de convertir -------- */
	list( $converted, $changes ) = Migration::convert_data( $doc, 1 );
	$c1 = $converted[0];
	$h1 = $c1['elements'][0];
	$w1 = $c1['elements'][1];
	$c2 = $c1['elements'][2];
	check_dropped( 5 === $changes && 'digitalisimo-accordion' === $w1['widgetType'] && 'yes' === $w1['settings']['digitalisimo_visibility_enable'] && false !== strpos( (string) $c1['settings']['custom_css'], 'backdrop-filter' ) && false !== strpos( (string) $h1['settings']['custom_css'], 'text-decoration' ), 'Lo convertible se convierte como siempre: widget, visibilidad, filtro de fondo y anotación: ' . $changes );
	$after = json_encode( $converted );
	check_dropped( ! preg_match( '/"(ep_|element_pack_|bdt_)/', $after ) && 'yes' === $c1['settings']['section_sticky_on'] && 'p1' === $c2['settings']['section_parallax_elements'][0]['_id'] && array() === $c2['elements'][0]['settings'], 'La conversión retira del elemento las claves con prefijo heredado (también las de una extensión sin equivalente); las que no lo tienen quedan guardadas sin efecto.' );
	$left = array_map( static function ( $item ) { return $item['key'] . '|' . $item['id']; }, Dropped::scan_elements( $converted ) );
	check_dropped( array( 'section_sticky_on|c1', 'section_parallax_elements|c2' ) === $left, 'Tras convertir sólo quedan a la vista las claves sin prefijo: lo demás hay que anotarlo antes de convertir: ' . implode( ', ', $left ) );

	/* -------- Conjuntos: sin repetir, con topes por documento y por informe -------- */
	$set = Dropped::add( Dropped::none(), 10, $found );
	$set = Dropped::add( $set, 10, Dropped::scan_elements( $converted ) );
	check_dropped( 9 === count( $set['docs'][10]['items'] ) && 0 === $set['docs'][10]['more'], 'Lo anotado antes y lo que queda después se unen sin repetir.' );
	$many = array();
	for ( $i = 0; $i < Dropped::MAX_ITEMS + 3; $i++ ) { $many[] = array( 'id' => 't' . $i, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'element_pack_widget_tooltip' => 'yes' ), 'elements' => array() ); }
	$big = Dropped::add( Dropped::none(), 5, Dropped::scan_elements( $many ) );
	check_dropped( Dropped::MAX_ITEMS === count( $big['docs'][5]['items'] ) && 3 === $big['docs'][5]['more'] && array( 'documents' => 1, 'settings' => Dropped::MAX_ITEMS + 3 ) === Dropped::totals( $big ), 'Un documento lista hasta el tope y cuenta el resto.' );
	$capped = Dropped::none();
	for ( $i = 1; $i <= Dropped::MAX_DOCUMENTS + 2; $i++ ) { $capped = Dropped::add( $capped, $i, $page ); }
	check_dropped( Dropped::MAX_DOCUMENTS === count( $capped['docs'] ) && 2 === $capped['omitted'] && Dropped::MAX_DOCUMENTS + 2 === Dropped::totals( $capped )['documents'], 'El informe lista hasta el tope de documentos y cuenta los demás.' );
	$merged = Dropped::merge( $big, Dropped::add( Dropped::none(), 5, Dropped::scan_elements( array_slice( $many, 0, 2 ) ) ) );
	check_dropped( Dropped::MAX_ITEMS === count( $merged['docs'][5]['items'] ) && 3 === $merged['docs'][5]['more'], 'Unir dos pasadas no cuenta dos veces lo mismo.' );
	check_dropped( 3 === Dropped::add( $big, 5, Dropped::scan_elements( $many ) )['docs'][5]['more'], 'Reintentar el mismo documento (una petición cortada) no suma otra vez lo que no cupo.' );
	// 14 informaciones emergentes y una sección fija (15 ajustes): lo que queda tras convertir ya estaba contado.
	$sticky = array( 'id' => 'sx', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() );
	$full   = Dropped::add( Dropped::none(), 6, Dropped::scan_elements( array_merge( array_slice( array_merge( $many, $many ), 0, 14 ), array( $sticky ) ) ) );
	$full   = Dropped::merge( $full, Dropped::add( Dropped::none(), 6, Dropped::scan_elements( array( $sticky ) ) ) );
	check_dropped( 15 === Dropped::totals( $full )['settings'], 'Lo que no cupo en la lista y vuelve a aparecer al unir no se cuenta dos veces: ' . Dropped::totals( $full )['settings'] );

	/* -------- Plugins de la familia: se detectan, no se desactivan -------- */
	t_reset();
	Aliases::set_map( array() );
	$GLOBALS['t']['plugins'] = array(
		'bdthemes-element-pack/bdthemes-element-pack.php'                   => array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1' ),
		'bdthemes-element-pack-lite/bdthemes-element-pack-lite.php'         => array( 'Name' => 'Element Pack Addons for Elementor', 'Version' => '8.0.0' ),
		'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php'       => array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.2' ),
		'bdthemes-prime-slider/bdthemes-prime-slider.php'                   => array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0' ),
		'bdthemes-prime-slider-lite/bdthemes-prime-slider.php'              => array( 'Name' => 'Prime Slider', 'Version' => '4.6.0' ),
		'carpeta-copia/principal.php'                                       => array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.1' ),
		'otro/otro-addon.php'                                               => array( 'Name' => 'Otros Addons', 'Version' => '1.0' ),
		'digitalisimo-elements/pro-elements.php'                            => array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.5.1' ),
	);
	$GLOBALS['t']['options'][1]['active_plugins'] = array_keys( $GLOBALS['t']['plugins'] );
	$kept = Retire::kept_status();
	check_dropped( array( 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php', 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php', 'carpeta-copia/principal.php' ) === array_keys( $kept ), 'Se reconocen el Addon (por archivo o por nombre, en cualquier carpeta) y Prime Slider Pro y Lite; nunca Element Pack Lite, DIGITALÍSIMO Elements ni un plugin ajeno: ' . implode( ', ', array_keys( $kept ) ) );
	check_dropped( 'Prime Slider Pro' === $kept['bdthemes-prime-slider/bdthemes-prime-slider.php']['label'] && 'Prime Slider' === $kept['bdthemes-prime-slider-lite/bdthemes-prime-slider.php']['label'] && 'element-pack-addon' === $kept['carpeta-copia/principal.php']['slug'] && $kept['carpeta-copia/principal.php']['site'], 'Cada uno con su etiqueta, su familia y su estado.' );
	check_dropped( ! array_intersect( array_keys( $kept ), array_keys( Retire::installed() ) ), 'Ninguno es de los que la migración retira.' );
	Retire::deactivate( false );
	$off = array_column( $GLOBALS['t']['deactivated'], 0 );
	check_dropped( in_array( 'bdthemes-element-pack/bdthemes-element-pack.php', $off, true ) && ! array_intersect( array_keys( $kept ), $off ), 'Migrar desactiva Element Pack, pero todavía no el Addon ni Prime Slider: ' . implode( ', ', $off ) );

	/* -------- Migrar de punta a punta en un sitio -------- */
	t_reset();
	Aliases::set_map( array() );
	$GLOBALS['t']['plugins'] = array(
		'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php' => array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.2' ),
		'bdthemes-prime-slider/bdthemes-prime-slider.php'             => array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0' ),
	);
	$GLOBALS['t']['options'][1]['active_plugins'] = array( 'digitalisimo-elements/pro-elements.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php' );
	$slider = array( array( 'id' => 'ps', 'elType' => 'container', 'settings' => array(), 'elements' => array(
		array( 'id' => 's1', 'elType' => 'widget', 'widgetType' => 'prime-slider-custom', 'settings' => array( 'sliders' => array() ), 'elements' => array() ),
		array( 'id' => 's2', 'elType' => 'widget', 'widgetType' => 'prime-slider-custom', 'settings' => array(), 'elements' => array() ),
		array( 'id' => 's3', 'elType' => 'widget', 'widgetType' => 'prime-slider-expo-slider', 'settings' => array(), 'elements' => array() ),
	) ) );
	foreach ( array( 10 => 'page', 11 => 'page', 12 => 'page', 13 => 'revision', 14 => 'page', 15 => 'elementor_library' ) as $id => $type ) { $GLOBALS['t']['types'][ $id ] = $type; }
	$GLOBALS['t']['parents'][13] = 10;
	$GLOBALS['t']['meta'][1][10] = array( '_elementor_data' => json_encode( $doc ) );
	// Sólo ajustes de la página heredados: el documento no es «de Element Pack» para la conversión.
	$GLOBALS['t']['meta'][1][11] = array( '_elementor_data' => json_encode( array( array( 'id' => 'x1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Sin nada heredado' ), 'elements' => array() ) ) ), '_elementor_page_settings' => array( 'ep_custom_header_script' => '<script>a()</script>', 'ep_content_protector_enable' => 'yes' ) );
	// Sólo extensiones que sí se convierten: no se lista.
	$GLOBALS['t']['meta'][1][12] = array( '_elementor_data' => json_encode( array( array( 'id' => 'n1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'ep_notation_active' => 'yes' ), 'elements' => array() ) ) ) );
	// Una revisión de la página 10: se convierte, pero el informe no lista revisiones.
	$GLOBALS['t']['meta'][1][13] = array( '_elementor_data' => json_encode( $doc ) );
	$GLOBALS['t']['meta'][1][14] = array( '_elementor_data' => json_encode( $slider ) );
	// Sólo una clave sin prefijo heredado (sección fija): ni siquiera es un documento «de Element Pack».
	$GLOBALS['t']['meta'][1][15] = array( '_elementor_data' => json_encode( array( array( 'id' => 'hd', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() ) ) ) );

	// Análisis previo: el mismo listado que dará el informe, por documento.
	$analysis = Dropped::analyze();
	check_dropped( array( 10, 11, 15 ) === array_keys( $analysis['docs'] ), 'El análisis lista los documentos con efectos sin equivalente, también los que sólo los tienen en los ajustes de la página o sin prefijo heredado; ni revisiones ni lo convertible: ' . implode( ', ', array_keys( $analysis['docs'] ) ) );
	check_dropped( 9 === count( $analysis['docs'][10]['items'] ) && 'custom-js' === $analysis['docs'][11]['items'][1]['ext'] && 'section_sticky_on' === $analysis['docs'][15]['items'][0]['key'], 'Con cada efecto y su clave.' );
	check_dropped( array( 'prime-slider-custom' => array( 'elements' => 2, 'documents' => 1 ), 'prime-slider-expo-slider' => array( 'elements' => 1, 'documents' => 1 ) ) === Dropped::prime_slider()['types'], 'Los widgets de Prime Slider se cuentan por tipo.' );
	ob_start();
	Migration::page();
	$screen = ob_get_clean();
	$text   = strip_tags( str_replace( '</td>', ' | ', $screen ) );
	foreach ( array( 'Efectos heredados sin equivalente en DIGITALÍSIMO Elements', 'Página 10 | Información emergente | element_pack_widget_tooltip | heading #h1', 'Página 10 | Sección fija al desplazar | section_sticky_on | container #c1', 'Página 11 | JavaScript personalizado de la página | ep_custom_header_script | Ajustes de la página', 'Página 15 | Sección fija al desplazar', 'Prime Slider Pro 4.7.0 (activo en este sitio): Migrar todavía no lo desactiva ni convierte sus widgets (3 elementos en 1 documentos)', 'También instalados y desactivados (Migrar no cambia su estado): Element Pack Addon 1.0.2.' ) as $expected ) {
		check_dropped( false !== strpos( $text, $expected ), 'Falta en el análisis previo de la herramienta: ' . $expected . "\n" . $text );
	}
	check_dropped( false === strpos( $text, 'Página 12 |' ) && false === strpos( $text, 'ep_notation_active' ) && 1 === substr_count( $screen, '<button' ), 'Lo que sí se convierte no se lista, y la pantalla sigue teniendo un solo botón.' );

	// Migrar: la conversión retira las claves heredadas de la página 10, y el informe las sigue listando.
	$state = Runner::site_step( true, 100 );
	for ( $i = 0; $i < 5 && empty( $state['done'] ); $i++ ) { new_request(); $state = Runner::site_step( false, 100 ); }
	check_dropped( ! empty( $state['done'] ) && 2 === $state['counts']['converted'] && 1 === $state['counts']['revisions'], 'La migración convierte las páginas 10 y 12 y la revisión 13, como antes: ' . json_encode( $state['counts'] ) );
	$stored = json_decode( $GLOBALS['t']['meta'][1][10]['_elementor_data'], true );
	check_dropped( ! preg_match( '/"(ep_|element_pack_|bdt_)/', $GLOBALS['t']['meta'][1][10]['_elementor_data'] ) && 'digitalisimo-accordion' === $stored[0]['elements'][1]['widgetType'] && '' !== $GLOBALS['t']['meta'][1][10]['_digitalisimo_ep_backup'], 'La página 10 queda convertida, con su copia original.' );
	check_dropped( ! isset( $GLOBALS['t']['meta'][1][11]['_digitalisimo_ep_backup'] ) && is_array( $GLOBALS['t']['meta'][1][11]['_elementor_page_settings'] ), 'Los ajustes de la página no se tocan.' );
	$report = get_option( Runner::REPORT );
	$r      = $report['report'];
	check_dropped( array( 10, 11, 15 ) === array_keys( $r['dropped'] ) && 9 === count( $r['dropped'][10]['items'] ) && 'Página 10' === $r['dropped'][10]['title'] && false !== strpos( $r['dropped'][10]['link'], 'post.php?post=10&action=elementor' ), 'El informe lista por documento lo que la conversión retiró y lo que queda sin efecto, con título y enlace: ' . implode( ', ', array_keys( $r['dropped'] ) ) );
	check_dropped( ! isset( $report['dropped'] ) && 1 === count( $r['kept'] ) && 'Prime Slider Pro' === $r['kept'][0]['label'] && 3 === $r['kept'][0]['elements'] && 'Element Pack Addon' === $r['kept_idle'][0]['label'], 'El informe guarda una sola vez lo anotado, y separa los plugins de la familia pendientes de los que no.' );
	check_dropped( ! Runner::has_pending( $report ), 'Los efectos sin equivalente y Prime Slider activo son información: nadie podría hacer desaparecer esas claves, así que no dejan el sitio «con asuntos por revisar» para siempre.' );
	$html = Runner::render( $report );
	$text = strip_tags( str_replace( '</td>', ' | ', $html ) );
	foreach ( array( 'Sin equivalente', 'Página 10 | Efectos de transformación | element_pack_widget_transform | bdt-accordion #w1', 'Página 10 | Efectos de la imagen al pasar el cursor | ep_image_hover_effects_on | image #i1' ) as $expected ) {
		check_dropped( false !== strpos( $text, $expected ), 'Falta en el informe (lo retirado al convertir también): ' . $expected . "\n" . $text );
	}
	check_dropped( false === strpos( $text, 'Efectos del cursor' ) && false === strpos( $text, 'Página 12 |' ) && false === strpos( $text, 'Revisión de' ), 'El informe no inventa efectos ni lista lo convertible ni las revisiones.' );
	check_dropped( false !== strpos( $text, 'Página 10 | Efectos de paralaje | ep_parallax_effects_show | container #c1' ) && false !== strpos( $text, 'Página 11 | Protección contra copiar el contenido | ep_content_protector_enable | Ajustes de la página' ) && false !== strpos( $text, 'Prime Slider Pro 4.7.0 (activo en este sitio)' ), 'El informe final muestra la misma tabla que el análisis y los avisos de Prime Slider.' );
	check_dropped( false === strpos( $text, 'Pendiente de revisar' ) && false !== strpos( $text, 'ya no depende de Elementor' ), 'Sin nada más pendiente, el informe da el sitio por terminado y sigue mostrando lo que no tiene equivalente.' );
	// Sin copia original, nada que anotar: la conversión la crea con esos mismos datos.
	check_dropped( array() === Dropped::uncovered( 11 ) && array() === Dropped::uncovered( 15 ), 'Un documento sin copia original no tiene nada fuera de ella.' );
	// Después, alguien vuelve a añadir efectos heredados a una página que ya se convirtió una vez (su copia es la de entonces).
	$GLOBALS['t']['types'][16] = 'page';
	$GLOBALS['t']['meta'][1][16] = array(
		'_digitalisimo_ep_backup' => json_encode( array( 'time' => 1, 'data' => json_encode( array( array( 'id' => 'h9', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'element_pack_widget_tooltip' => 'yes' ), 'elements' => array() ) ) ) ) ),
		'_elementor_data'         => json_encode( array(
			array( 'id' => 'h9', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'element_pack_widget_tooltip' => 'yes' ), 'elements' => array() ),
			array( 'id' => 'n9', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'ep_threed_text_active' => 'yes' ), 'elements' => array() ),
		) ),
	);
	$uncovered = array_map( static function ( $item ) { return $item['key'] . '|' . $item['id']; }, Dropped::uncovered( 16 ) );
	check_dropped( array( 'ep_threed_text_active|n9' ) === $uncovered, 'Con una copia de una conversión anterior, sólo se anota lo que esa copia no recoge: ' . implode( ', ', $uncovered ) );
	// Pulsar Migrar otra vez: lo retirado ya no está en los datos, pero sí en la copia original, y se sigue listando.
	new_request();
	$again = Runner::site_step( true, 100 );
	for ( $i = 0; $i < 5 && empty( $again['done'] ); $i++ ) { new_request(); $again = Runner::site_step( false, 100 ); }
	$second = get_option( Runner::REPORT );
	$again  = $second['report'];
	check_dropped( ! preg_match( '/"(ep_|element_pack_|bdt_)/', $GLOBALS['t']['meta'][1][16]['_elementor_data'] ) && false === strpos( $GLOBALS['t']['meta'][1][16]['_digitalisimo_ep_backup'], 'threed' ), 'La página 16 se convierte y su copia sigue siendo la de la primera conversión.' );
	check_dropped( array( 10, 11, 15, 16 ) === array_keys( $again['dropped'] ) && array_column( $r['dropped'][10]['items'], 'ext' ) === array_column( $again['dropped'][10]['items'], 'ext' ), 'Otra pasada sigue listando lo que la primera conversión retiró: ' . json_encode( array_column( $again['dropped'][10]['items'] ?? array(), 'ext' ) ) );
	check_dropped( array( 'tooltip|h9', 'threed-text|n9' ) === array_map( static function ( $item ) { return $item['ext'] . '|' . $item['id']; }, $again['dropped'][16]['items'] ?? array() ), 'Y lo añadido después de la copia, anotado antes de convertir: ' . json_encode( $again['dropped'][16]['items'] ?? null ) );
	check_dropped( ! Runner::has_pending( $second ) && ! isset( $second['dropped'] ), 'La segunda pasada tampoco queda pendiente, y lo anotado no se guarda dos veces.' );
	// Widgets de Prime Slider que hoy no se ven (plugin sin activar): eso sí es un asunto por revisar.
	$hidden = array( 'slug' => 'prime-slider', 'label' => 'Prime Slider Pro', 'version' => '4.7.0', 'installed' => true, 'site' => false, 'network' => false, 'elements' => 3, 'documents' => 1 );
	$shown  = array( 'site' => true ) + $hidden;
	check_dropped( Dropped::kept_hidden( $hidden ) && ! Dropped::kept_hidden( $shown ) && 1 === count( Dropped::kept_messages( array( $hidden ) )['pending'] ) && 1 === count( Dropped::kept_messages( array( $shown ) )['info'] ), 'Sólo los widgets de Prime Slider sin su plugin activo son contenido que no se ve.' );
	$broken = $second;
	$broken['report']['kept'] = array( $hidden );
	$broken_text = strip_tags( Runner::render( $broken ) );
	check_dropped( Runner::has_pending( $broken ) && ! Runner::has_pending( array( 'report' => array( 'kept' => array( $shown ) ) ) + $second ) && false !== strpos( $broken_text, 'Pendiente de revisar' ) && false !== strpos( $broken_text, 'Hay 3 widgets de Prime Slider en 1 documentos y el plugin está desactivado: no se muestran.' ) && false === strpos( $broken_text, 'ya no depende de Elementor' ), 'Con widgets de Prime Slider que no se ven, el informe sí queda pendiente.' );

	// Un informe de una versión anterior (sin estos datos) se sigue pintando igual.
	$old = $report;
	unset( $old['report']['dropped'], $old['report']['dropped_omitted'], $old['report']['kept'], $old['report']['kept_idle'] );
	check_dropped( false === strpos( Runner::render( $old ), 'Efectos heredados' ), 'Un informe anterior se pinta sin la tabla nueva.' );

	/* -------- Red: el resumen de cada sitio y la pantalla de red dicen lo mismo -------- */
	$GLOBALS['t']['multisite'] = true;
	$GLOBALS['t']['site_options']['active_sitewide_plugins'] = array( 'digitalisimo-elements/pro-elements.php' => 1, 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php' => 1 );
	$GLOBALS['t']['options'][2]['active_plugins'] = array( 'bdthemes-prime-slider/bdthemes-prime-slider.php' );
	$GLOBALS['t']['meta'][2][20] = array( '_elementor_data' => json_encode( array( array( 'id' => 'r1', 'elType' => 'container', 'settings' => array( 'section_particles_on' => 'yes' ), 'elements' => array() ) ) ) );
	$job = Runner::network_start( true );
	foreach ( array( 1, 2 ) as $site ) {
		switch_to_blog( $site );
		for ( $i = 0; $i < 6; $i++ ) { new_request(); $s = Runner::site_step( false, 100 ); if ( ! empty( $s['done'] ) || ! empty( $s['idle'] ) ) { break; } }
		restore_current_blog();
	}
	$summary = Runner::network_summary();
	check_dropped( ! empty( $summary['done'] ) && 4 === $summary['status'][1]['dropped'] && 1 === $summary['status'][2]['dropped'] && array( 'Element Pack Addon', 'Prime Slider Pro' ) === $summary['status'][1]['kept'] && empty( $summary['status'][1]['pending'] ) && empty( $summary['status'][2]['pending'] ), 'Cada sitio de la red cuenta sus documentos con efectos descartados y sus plugins sin desactivar, sin quedar pendiente por ello (también tras una migración anterior del sitio): ' . json_encode( array( $summary['status'][1]['dropped'] ?? null, $summary['status'][2]['dropped'] ?? null, $summary['status'][1]['kept'] ?? null ) ) );
	$network = strip_tags( str_replace( '</td>', ' | ', Runner::render_network( $summary ) ) );
	check_dropped( false !== strpos( $network, 'efectos heredados sin equivalente en 4 documentos; sin desactivar: Element Pack Addon, Prime Slider Pro' ) && false !== strpos( $network, 'Detectados y sin desactivar (Migrar todavía no los desactiva; los widgets del Element Pack Addon con adaptador se convierten como los de Element Pack, y los de Prime Slider todavía no se convierten): Element Pack Addon (activo en toda la red), Prime Slider Pro (activo en 2 sitios)' ) && false === strpos( $network, 'con asuntos por revisar' ), 'La pantalla de red resume lo mismo que el informe de cada sitio: ' . $network );
	check_dropped( 'Detectados y sin desactivar (Migrar todavía no los desactiva; los de Prime Slider todavía no se convierten): Prime Slider (desactivado). Fin.' === Dropped::kept_notice( array( 'Prime Slider (desactivado)' ), 'Fin.' ), 'El aviso de red sólo habla de los plugins que hay.' );
	check_dropped( ! in_array( 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', array_column( $GLOBALS['t']['deactivated'], 0 ), true ), 'La red tampoco desactiva el Addon.' );
	ob_start();
	Migration::network_page();
	$table = strip_tags( str_replace( '</td>', ' | ', ob_get_clean() ) );
	check_dropped( false !== strpos( $table, 'Sin equivalente' ) && false !== strpos( $table, 'efectos heredados sin equivalente: 1 en 1 documentos; Element Pack Addon (activo en toda la red); Prime Slider Pro (activo en este sitio) |' ) && false !== strpos( $table, '; Prime Slider Pro (activo en este sitio, 3 widgets) |' ), 'El análisis de la red tiene, por sitio, la columna «Sin equivalente»: ' . $table );
	check_dropped( false !== strpos( $table, 'Detectados y sin desactivar' ), 'Y el mismo aviso de los plugins de la familia que el resumen de la migración de red.' );
	check_dropped( 1 === $GLOBALS['t']['blog'] && empty( $GLOBALS['t']['stack'] ), 'Cada cambio de sitio se deshace.' );

	echo "DIGITALÍSIMO Migración: extensiones heredadas sin equivalente, Element Pack Addon y Prime Slider informados en el análisis y en el informe validados.\n";
}
