<?php
/**
 * F7a, frente I: páginas visitadas para «Visitó una página» (V.4.3).
 *
 * Sin WordPress ni el constructor: dobles en memoria (opciones por sitio con su carga automática, entradas y metas,
 * `$wpdb`, cookies, la consulta y el estado de la petición). Comprueba:
 * - la cookie por sitio (`digi_seen` o `digi_seen_<blog_id>`, ruta de `home_url( '/' )`) y su lectura estricta: en una
 *   red de subdirectorios con IDs repetidos ningún sitio lee la lista de otro;
 * - el índice por documento (nativo, prop v4 a mano y con el transformador de PRO, nunca marcadores), la unión ≤ 200 en
 *   una opción con carga automática que existe siempre, sólo con documentos publicados construidos con el editor;
 * - el recálculo al guardar, al publicar, al despublicar, a la papelera, al borrar y al importar un kit, y la purga de
 *   lo que entra y de lo que sale;
 * - el script: sólo en vistas individuales rastreadas, nunca en la página de entradas, la tienda, la vista previa ni el
 *   editor, nunca con el filtro en `false` ni sin consentimiento posible; con `wp_print_inline_script_tag()` y menos
 *   de 400 B; un sitio sin el índice no lee nada;
 * - el texto de privacidad con todas las cookies propias y el registro en la WP Consent API.
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $editor;
		public $preview;
	}
}

namespace Digitalisimo\Elements\Front {
	/** Doble de los ajustes del front: sólo lo que lee Page_Visits. */
	final class Settings {
		public static $values = array();
		public static function get( string $key ): bool {
			return ! empty( self::$values[ $key ] );
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DIGITALISIMO_ELEMENTS_FILE', '/srv/wp-content/plugins/digitalisimo-elements/pro-elements.php' );

	$GLOBALS['pv'] = array();
	function pv_reset( array $over = array() ) {
		$GLOBALS['pv'] = array_merge(
			array(
				'hooks'     => array(),
				'filters'   => array(),
				'blog'      => 1,
				'multisite' => false,
				'home'      => array( 1 => 'https://a.test/' ),
				'ssl'       => false,
				'options'   => array(),
				'autoload'  => array(),
				'writes'    => array(),
				'reads'     => 0,
				'posts'     => array(),
				'meta'      => array(),
				'col'       => array(),
				'sql'       => array(),
				'purged'    => array(),
				'cleaned'   => array(),
				'printed'   => array(),
				'privacy'   => array(),
				'cookies'   => array(),
				'admin'     => false,
				'singular'  => true,
				'home_page' => false,
				'shop'      => false,
				'preview'   => false,
				'edit'      => false,
				'previewing' => false,
				'queried'   => 0,
				'mode'      => 'off',
			),
			$over
		);
		$_COOKIE = array();
		$_GET    = array();
		\Elementor\Plugin::$instance          = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->editor  = new class() { public function is_edit_mode() { return $GLOBALS['pv']['edit']; } };
		\Elementor\Plugin::$instance->preview = new class() { public function is_preview_mode() { return $GLOBALS['pv']['previewing']; } };
		\Digitalisimo\Elements\Front\Settings::$values = array();
	}
	pv_reset();

	function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['pv']['hooks'][ $hook ][] = array( $callback, $priority, $args ); }
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback, $priority, $args ); }
	function apply_filters( $hook, $value, ...$args ) {
		if ( ! array_key_exists( $hook, $GLOBALS['pv']['filters'] ) ) {
			return $value;
		}
		$filter = $GLOBALS['pv']['filters'][ $hook ];
		return is_callable( $filter ) ? $filter( $value, ...$args ) : $filter;
	}
	function do_action( $hook, ...$args ) {
		if ( 'litespeed_purge_post' === $hook ) {
			$GLOBALS['pv']['purged'][] = $args[0];
		}
	}
	function clean_post_cache( $id ) { $GLOBALS['pv']['cleaned'][] = $id; }
	function plugin_basename( $file ) { return 'digitalisimo-elements/pro-elements.php'; }
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function wp_kses_post( $html ) { return $html; }
	function is_multisite() { return $GLOBALS['pv']['multisite']; }
	function get_current_blog_id() { return $GLOBALS['pv']['blog']; }
	function home_url( $path = '' ) { return $GLOBALS['pv']['home'][ $GLOBALS['pv']['blog'] ] . ltrim( $path, '/' ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function is_ssl() { return $GLOBALS['pv']['ssl']; }
	function is_admin() { return $GLOBALS['pv']['admin']; }
	function is_singular() { return $GLOBALS['pv']['singular']; }
	function is_home() { return $GLOBALS['pv']['home_page']; }
	function is_shop() { return $GLOBALS['pv']['shop']; }
	function is_preview() { return $GLOBALS['pv']['preview']; }
	function is_feed() { return false; }
	function is_embed() { return false; }
	function get_queried_object_id() { return $GLOBALS['pv']['queried']; }
	function wp_print_inline_script_tag( $data, $attributes = array() ) { $GLOBALS['pv']['printed'][] = array( $data, $attributes ); }
	function wp_add_privacy_policy_content( $name, $text ) { $GLOBALS['pv']['privacy'][] = array( $name, $text ); }
	function wp_load_alloptions() {
		$blog = $GLOBALS['pv']['blog'];
		$out  = array();
		foreach ( $GLOBALS['pv']['options'][ $blog ] ?? array() as $name => $value ) {
			if ( ! empty( $GLOBALS['pv']['autoload'][ $blog ][ $name ] ) ) {
				$out[ $name ] = serialize( $value );
			}
		}
		return $out;
	}
	function get_option( $name, $default = false ) {
		++$GLOBALS['pv']['reads'];
		return $GLOBALS['pv']['options'][ $GLOBALS['pv']['blog'] ][ $name ] ?? $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$blog = $GLOBALS['pv']['blog'];
		$GLOBALS['pv']['writes'][]                    = array( $blog, $name, $autoload );
		$GLOBALS['pv']['options'][ $blog ][ $name ]   = $value;
		if ( null !== $autoload ) {
			$GLOBALS['pv']['autoload'][ $blog ][ $name ] = (bool) $autoload;
		}
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = true ) {
		if ( isset( $GLOBALS['pv']['options'][ $GLOBALS['pv']['blog'] ][ $name ] ) ) {
			return false;
		}
		return update_option( $name, $value, $autoload );
	}
	function get_post( $id ) { return $GLOBALS['pv']['posts'][ (int) $id ] ?? null; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['pv']['meta'][ (int) $id ][ $key ] ?? ''; }

	class Pv_Wpdb {
		public $posts    = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
		public function prepare( $sql, ...$args ) { $GLOBALS['pv']['sql'][] = array( $sql, $args ); return 'consulta-preparada'; }
		public function get_col( $sql ) { return 'consulta-preparada' === $sql ? $GLOBALS['pv']['col'] : array(); }
	}
	$GLOBALS['wpdb'] = new Pv_Wpdb();

	function check_pv( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$module = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-conditions/';
	require $module . 'class-page-visits.php';

	use Digitalisimo\Elements\Conditions\Page_Visits as PV;

	/** Entrada de prueba con su `_elementor_data`. */
	function pv_post( $id, $status, array $elements, $type = 'page', array $meta = array() ) {
		$GLOBALS['pv']['posts'][ $id ] = (object) array( 'ID' => $id, 'post_status' => $status, 'post_type' => $type );
		$GLOBALS['pv']['meta'][ $id ]  = array_merge( array( '_elementor_edit_mode' => 'builder', '_elementor_data' => json_encode( $elements ) ), $meta );
	}
	function pv_visit( array $pages, $comparator = 'is_one_of' ) {
		return array( 'condition' => 'digitalisimo_page_visit', 'comparator' => $comparator, 'pages' => $pages );
	}
	function pv_el( $id, array $groups, array $children = array(), $widget = null ) {
		$element = array( 'id' => $id, 'elType' => $widget ? 'widget' : 'container', 'settings' => array( 'e_display_conditions' => array( json_encode( $groups ) ) ), 'elements' => $children );
		if ( $widget ) {
			$element['widgetType'] = $widget;
		}
		return $element;
	}
	function pv_v4( $id, array $groups ) {
		$value = array();
		foreach ( $groups as $group ) {
			$value[] = array( '$$type' => 'condition-group', 'value' => array_map( function ( $c ) { return array( '$$type' => 'string', 'value' => json_encode( $c ) ); }, $group ) );
		}
		return array( 'id' => $id, 'elType' => 'e-div-block', 'settings' => array( 'display-conditions' => array( '$$type' => 'display-conditions', 'value' => $value ) ), 'elements' => array() );
	}
	function pv_option() {
		return $GLOBALS['pv']['options'][ $GLOBALS['pv']['blog'] ][ PV::OPTION ] ?? null;
	}

	// 1. Ganchos.
	PV::init();
	$hooks = $GLOBALS['pv']['hooks'];
	check_pv( array( array( PV::class, 'print_script' ), 20, 1 ) === $hooks['wp_footer'][0], 'El script va en wp_footer 20.' );
	foreach ( array( 'elementor/document/after_save', 'transition_post_status', 'deleted_post', 'digitalisimo_elements_kit_imported', 'admin_init', 'init' ) as $hook ) {
		check_pv( isset( $hooks[ $hook ] ), "Engancha {$hook}." );
	}
	check_pv( 0 === $hooks['digitalisimo_elements_kit_imported'][0][2] && 3 === $hooks['transition_post_status'][0][2], 'Reindexa tras importar un kit y en transition_post_status.' );
	check_pv( isset( $hooks['wp_consent_api_registered_digitalisimo-elements/pro-elements.php'] ), 'Se declara ante la WP Consent API.' );

	// 2. Cookie por sitio.
	check_pv( 'digi_seen' === PV::cookie_name() && '/' === PV::cookie_path(), 'Sitio único: digi_seen en /.' );
	pv_reset( array( 'multisite' => true, 'blog' => 3, 'home' => array( 2 => 'https://red.test/uno/', 3 => 'https://red.test/dos/' ) ) );
	check_pv( 'digi_seen_3' === PV::cookie_name() && '/dos/' === PV::cookie_path(), 'Red: digi_seen_<blog_id> con la ruta de home_url() del sitio.' );
	$GLOBALS['pv']['home'][3] = 'https://red.test/a b/"x<\'y/';
	check_pv( '/ab/xy/' === PV::cookie_path(), 'La ruta sólo conserva caracteres seguros.' );
	pv_reset( array( 'multisite' => true, 'blog' => 3, 'home' => array( 2 => 'https://red.test/uno/', 3 => 'https://red.test/dos/' ) ) );
	$_COOKIE = array( 'digi_seen_2' => 'c', 'digi_seen' => 'c' );
	check_pv( array() === PV::seen(), 'Red con IDs repetidos: el sitio 3 no lee la cookie del 2 ni la de un sitio único.' );
	$_COOKIE['digi_seen_3'] = 'd';
	check_pv( array( 13 ) === PV::seen(), 'Cada sitio lee la suya.' );

	// 3. Lectura estricta.
	pv_reset();
	$ok = implode( '.', array_map( function ( $id ) { return base_convert( (string) $id, 10, 36 ); }, range( 1, 50 ) ) );
	$_COOKIE = array( 'digi_seen' => $ok );
	check_pv( range( 1, 50 ) === PV::seen(), 'Hasta 50 IDs en base 36.' );
	foreach ( array( $ok . '.1a', 'C', 'abcdefghi', 'a..b', '.a', 'a.', 'a;b', '<b>', '0', '' ) as $bad ) {
		$_COOKIE = array( 'digi_seen' => $bad );
		check_pv( array() === PV::seen(), 'Una cookie fuera del patrón no da ninguno: ' . $bad );
	}
	$_COOKIE = array( 'digi_seen' => array( 'c' ) );
	check_pv( array() === PV::seen(), 'Una cookie en forma de lista no se lee.' );
	$_COOKIE = array( 'digi_seen' => 'c.c.0.d' );
	check_pv( array( 12, 13 ) === PV::seen(), 'Sin repetidos ni ceros.' );

	// 4. Índice por documento.
	$elements = array(
		pv_el( 'a', array( array( pv_visit( array( array( 'id' => 12, 'label' => 'Precios' ), array( 'id' => '15' ) ) ) ) ), array(
			pv_el( 'b', array( array( pv_visit( array( 15, 16 ), 'is_none_of' ), array( 'condition' => 'digitalisimo_special_page', 'comparator' => 'is_one_of', 'pages' => array( 'digitalisimo_f7a' ) ) ) ) ),
			pv_el( 'c', array( array( array( 'condition' => 'digitalisimo_specific_content', 'comparator' => 'is_one_of', 'items' => array( 99 ) ) ) ) ),
			pv_el( 'm', array( array( pv_visit( array( 77 ) ) ) ), array(), 'digitalisimo-unconverted' ),
			pv_v4( 'v', array( array( pv_visit( array( array( 'id' => 21 ) ) ) ) ) ),
		) ),
	);
	check_pv( array( 12, 15, 16, 21 ) === PV::index( $elements ), 'Índice: condiciones nativas anidadas y prop v4; ni otras condiciones ni marcadores.' );
	pv_post( 40, 'publish', $elements );
	check_pv( array( 12, 15, 16, 21 ) === PV::index( 40 ) && PV::index( 40 ) === PV::index( get_post( 40 ) ) && PV::index( 40 ) === PV::index( new class() { public function get_main_id() { return 40; } } ), 'El índice se lee igual desde un ID, una entrada o un documento.' );
	check_pv( array() === PV::index( array( pv_el( 'z', array() ) ) ) && array() === PV::index( 0 ) && array() === PV::index( 'x' ), 'Sin condiciones o sin documento no rastrea nada.' );
	$many = PV::index( array( pv_el( 'x', array( array( pv_visit( range( 1, 250 ) ) ) ) ) ) );
	check_pv( 200 === count( $many ) && 1 === $many[0], 'Un documento rastrea como mucho 200 páginas.' );

	// 5. ¿Qué documentos cuentan?
	pv_post( 41, 'draft', $elements );
	pv_post( 42, 'inherit', $elements, 'revision' );
	pv_post( 43, 'publish', $elements, 'page', array( '_elementor_edit_mode' => '' ) );
	pv_post( 44, 'publish', $elements, 'elementor_library' );
	pv_post( 45, 'publish', $elements, 'elementor_library', array( '_elementor_conditions' => array( 'include/general' ) ) );
	pv_post( 46, 'trash', $elements );
	check_pv( PV::counts( get_post( 40 ) ) && PV::counts( get_post( 45 ) ), 'Cuentan las entradas publicadas con el editor y las plantillas publicadas con condiciones.' );
	foreach ( array( 41 => 'un borrador', 42 => 'una revisión', 43 => 'una entrada sin el editor', 44 => 'una plantilla sin condiciones', 46 => 'la papelera' ) as $id => $what ) {
		check_pv( ! PV::counts( get_post( $id ) ), "No cuenta {$what}." );
	}

	// 6. Recálculo, unión y purga.
	pv_reset();
	pv_post( 40, 'publish', array( pv_el( 'a', array( array( pv_visit( array( 12, 15 ) ) ) ) ) ) );
	PV::reindex_document( 40 );
	check_pv( array( 'docs' => array( 40 => array( 12, 15 ) ), 'union' => array( 12, 15 ) ) === pv_option() && array( array( 1, PV::OPTION, true ) ) === $GLOBALS['pv']['writes'], 'Guardar un documento publicado escribe su entrada y la unión, con carga automática.' );
	check_pv( array( 12, 15 ) === $GLOBALS['pv']['purged'] && array( 12, 15 ) === $GLOBALS['pv']['cleaned'], 'Las páginas que entran se purgan (LiteSpeed y caché de objetos).' );
	PV::reindex_document( 40 );
	check_pv( 1 === count( $GLOBALS['pv']['writes'] ) && 2 === count( $GLOBALS['pv']['purged'] ), 'Sin cambios no se escribe ni se purga.' );
	pv_post( 50, 'publish', array( pv_el( 'a', array( array( pv_visit( array( 15, 30 ) ) ) ) ) ) );
	PV::on_document_saved( new class() { public function get_main_id() { return 50; } public function get_post() { return (object) array( 'ID' => 50, 'post_type' => 'page' ); } } );
	check_pv( array( 12, 15, 30 ) === PV::stored()['union'] && array( 12, 15, 30 ) === $GLOBALS['pv']['purged'], 'Otro documento suma a la unión y sólo purga lo nuevo.' );
	pv_post( 40, 'publish', array( pv_el( 'a', array( array( pv_visit( array( 15 ) ) ) ) ) ) );
	PV::on_document_saved( new class() { public function get_main_id() { return 40; } public function get_post() { return (object) array( 'ID' => 40, 'post_type' => 'page' ); } } );
	check_pv( array( 15, 30 ) === PV::stored()['union'] && 12 === end( $GLOBALS['pv']['purged'] ), 'La página que sale de la unión también se purga.' );
	$writes = count( $GLOBALS['pv']['writes'] );
	PV::on_document_saved( new class() { public function get_main_id() { return 40; } public function get_post() { return (object) array( 'ID' => 41, 'post_type' => 'revision' ); } } );
	check_pv( $writes === count( $GLOBALS['pv']['writes'] ), 'Un autoguardado no cambia el índice.' );
	PV::on_transition( 'publish', 'publish', get_post( 40 ) );
	check_pv( $writes === count( $GLOBALS['pv']['writes'] ), 'Una actualización sin cambio de estado la recoge after_save, no la transición.' );
	$GLOBALS['pv']['posts'][50]->post_status = 'draft';
	PV::on_transition( 'draft', 'publish', get_post( 50 ) );
	check_pv( array( 'docs' => array( 40 => array( 15 ) ), 'union' => array( 15 ) ) === pv_option() && 30 === end( $GLOBALS['pv']['purged'] ), 'Despublicar saca el documento y purga lo que deja de rastrearse.' );
	$GLOBALS['pv']['posts'][50]->post_status = 'publish';
	PV::on_transition( 'publish', 'draft', get_post( 50 ) );
	check_pv( array( 15, 30 ) === PV::stored()['union'], 'Publicar lo devuelve.' );
	$GLOBALS['pv']['posts'][50]->post_status = 'trash';
	PV::on_transition( 'trash', 'publish', get_post( 50 ) );
	check_pv( array( 15 ) === PV::stored()['union'], 'La papelera lo saca.' );
	PV::on_transition( 'publish', 'draft', (object) array( 'ID' => 42, 'post_type' => 'revision' ) );
	unset( $GLOBALS['pv']['posts'][40] );
	PV::on_deleted( 40 );
	check_pv( array() === pv_option() && 15 === end( $GLOBALS['pv']['purged'] ), 'Borrar un documento lo saca y purga, y el índice vacío vuelve a ser [].' );
	$writes = count( $GLOBALS['pv']['writes'] );
	PV::on_deleted( 999 );
	check_pv( $writes === count( $GLOBALS['pv']['writes'] ), 'Borrar otra entrada no escribe nada.' );
	pv_post( 60, 'draft', array( pv_el( 'a', array( array( pv_visit( array( 12 ) ) ) ) ) ) );
	PV::reindex_document( 60 );
	check_pv( array() === PV::stored()['union'], 'Un borrador (como lo que llega de un kit en «agregar») no activa el rastreo.' );
	for ( $doc = 100; $doc < 105; $doc++ ) {
		pv_post( $doc, 'publish', array( pv_el( 'a', array( array( pv_visit( range( ( $doc - 100 ) * 60 + 1, ( $doc - 100 ) * 60 + 60 ) ) ) ) ) ) );
		PV::reindex_document( $doc );
	}
	check_pv( 200 === count( PV::stored()['union'] ) && 5 === count( PV::stored()['docs'] ), 'La unión del sitio tiene como mucho 200 páginas.' );

	// 7. Tras importar un kit, el índice se reconstruye con una consulta.
	pv_reset();
	pv_post( 70, 'publish', array( pv_el( 'a', array( array( pv_visit( array( 5 ) ) ) ) ) ) );
	pv_post( 71, 'draft', array( pv_el( 'a', array( array( pv_visit( array( 6 ) ) ) ) ) ) );
	pv_post( 72, 'publish', array( pv_el( 'm', array( array( pv_visit( array( 7 ) ) ) ), array(), 'digitalisimo-unconverted' ) ) );
	pv_post( 73, 'publish', array( pv_v4( 'v', array( array( pv_visit( array( 8 ) ) ) ) ) ) );
	update_option( PV::OPTION, array( 'docs' => array( 99 => array( 1 ) ), 'union' => array( 1 ) ), true );
	$GLOBALS['pv']['col'] = array( '70', '71', '72', '73' );
	PV::reindex_all();
	list( $sql, $args ) = $GLOBALS['pv']['sql'][0];
	check_pv( false !== strpos( $sql, 'p.post_status = %s' ) && false !== strpos( $sql, 'p.post_type <> %s' ) && false !== strpos( $sql, 'LIKE %s' ) && array( '_elementor_data', 'publish', 'revision', '%digitalisimo\\_page\\_visit%' ) === $args, 'Una consulta preparada: publicados, sin revisiones, con esc_like().' );
	check_pv( array( 'docs' => array( 70 => array( 5 ), 73 => array( 8 ) ), 'union' => array( 5, 8 ) ) === pv_option() && in_array( 1, $GLOBALS['pv']['purged'], true ), 'Reconstruido: sin borradores ni marcadores, y lo viejo se purga al salir.' );

	// 8. Lectura en el front: sin el índice, ninguna consulta.
	pv_reset( array( 'queried' => 12 ) );
	check_pv( array() === PV::tracked() && 0 === $GLOBALS['pv']['reads'], 'Sin la opción entre las de carga automática no se lee nada.' );
	PV::print_script();
	check_pv( array() === $GLOBALS['pv']['printed'] && 0 === $GLOBALS['pv']['reads'], 'Un sitio sin el índice no imprime nada ni consulta.' );
	$GLOBALS['pv']['options'][1][ PV::OPTION ] = array( 'docs' => array( 40 => array( 12 ) ), 'union' => array( 12 ) );
	check_pv( array() === PV::tracked(), 'Una opción sin carga automática tampoco se lee en el front.' );

	// 9. Cuándo se imprime el script.
	$tracked = function ( array $over = array() ) {
		pv_reset( $over + array( 'queried' => 12 ) );
		update_option( PV::OPTION, array( 'docs' => array( 40 => array( 12, 15 ) ), 'union' => array( 12, 15 ) ), true );
		$GLOBALS['pv']['writes'] = array();
		\Digitalisimo\Elements\Front\Settings::$values = array( 'page_visits_no_consent' => true );
	};
	$tracked();
	PV::print_script();
	check_pv( 1 === count( $GLOBALS['pv']['printed'] ) && array( 'id' => PV::SCRIPT_ID ) === $GLOBALS['pv']['printed'][0][1], 'Página rastreada y opción encendida: un script con wp_print_inline_script_tag().' );
	$script = $GLOBALS['pv']['printed'][0][0];
	check_pv( strlen( $script ) < 400 && 0 === strpos( $script, PV::SCRIPT ) && substr( $script, strlen( PV::SCRIPT ) ) === '(document,"digi_seen=","c",";path=/;max-age=2592000;SameSite=Lax;Secure",!0)', 'Script de menos de 400 B, con nombre, ID en base 36, ruta, 30 días, SameSite=Lax, Secure en HTTPS y la opción: ' . $script );
	foreach ( array( 'fetch', 'XMLHttpRequest', 'sendBeacon', 'Image', 'src=', 'http' ) as $net ) {
		check_pv( false === strpos( PV::SCRIPT, $net ), "El script no pide nada a la red ({$net})." );
	}
	check_pv( false === strpos( PV::SCRIPT, "'" ) && false === strpos( PV::SCRIPT, '\\' ), 'El script no lleva comillas simples ni barras invertidas.' );
	check_pv( false !== strpos( PV::SCRIPT, '.slice(-' . PV::MAX_SEEN . ')' ) && 50 === PV::MAX_SEEN && 2592000 === PV::MAX_AGE, 'El script guarda los MAX_SEEN (50) más recientes durante 30 días.' );
	$tracked( array( 'home' => array( 1 => 'http://a.test/' ) ) );
	check_pv( false === strpos( PV::script( 12 ), 'Secure' ), 'Sin HTTPS no hay Secure.' );
	$GLOBALS['pv']['ssl'] = true;
	check_pv( false !== strpos( PV::script( 12 ), ';Secure"' ), 'Una petición HTTPS lleva Secure.' );
	$tracked( array( 'multisite' => true, 'blog' => 12, 'home' => array( 12 => 'https://red.test/sitio/' ) ) );
	PV::print_script();
	check_pv( 1 === count( $GLOBALS['pv']['printed'] ) && false !== strpos( $GLOBALS['pv']['printed'][0][0], '"digi_seen_12="' ) && false !== strpos( $GLOBALS['pv']['printed'][0][0], ';path=/sitio/;' ) && strlen( $GLOBALS['pv']['printed'][0][0] ) < 400, 'Red: el nombre y la ruta del sitio, también por debajo de 400 B.' );
	$cases = array(
		'otra página'            => array( 'queried' => 99 ),
		'un archivo'             => array( 'singular' => false ),
		'la página de entradas'  => array( 'home_page' => true ),
		'la tienda'              => array( 'shop' => true ),
		'la vista previa'        => array( 'preview' => true ),
		'la administración'      => array( 'admin' => true ),
		'el editor'              => array( 'edit' => true ),
		'la vista previa del editor' => array( 'previewing' => true ),
	);
	foreach ( $cases as $what => $over ) {
		$tracked( $over );
		PV::print_script();
		check_pv( array() === $GLOBALS['pv']['printed'], "Nada en {$what}." );
	}
	$tracked();
	$_GET['elementor-preview'] = '40';
	PV::print_script();
	check_pv( array() === $GLOBALS['pv']['printed'], 'Nada con ?elementor-preview.' );
	$tracked();
	$GLOBALS['pv']['filters'][ PV::FILTER ] = function ( $on, $id ) { return 15 === $id; };
	PV::print_script();
	check_pv( array() === $GLOBALS['pv']['printed'], 'El filtro digitalisimo_elements_page_visit_tracking puede apagarlo por página.' );
	$tracked();
	\Digitalisimo\Elements\Front\Settings::$values = array();
	PV::print_script();
	check_pv( array() === $GLOBALS['pv']['printed'], 'Sin la WP Consent API y con la opción apagada, no hay script.' );
	eval( 'function wp_has_consent( $category ) { return false; }' );
	PV::print_script();
	check_pv( 1 === count( $GLOBALS['pv']['printed'] ) && '!1)' === substr( $GLOBALS['pv']['printed'][0][0], -3 ), 'Con la WP Consent API el script se imprime y decide en el navegador (opción apagada: !1).' );
	check_pv( array() === $GLOBALS['pv']['writes'], 'El front nunca escribe opciones.' );

	// 10. Administración: la opción existe siempre y la política de privacidad declara todas las cookies.
	pv_reset();
	PV::admin_init();
	check_pv( array() === pv_option() && array( array( 1, PV::OPTION, true ) ) === $GLOBALS['pv']['writes'], 'admin_init crea la opción vacía con carga automática.' );
	PV::admin_init();
	check_pv( 1 === count( $GLOBALS['pv']['writes'] ), 'Una vez creada no se vuelve a escribir.' );
	$text = $GLOBALS['pv']['privacy'][0][1];
	check_pv( 'DIGITALÍSIMO Elements' === $GLOBALS['pv']['privacy'][0][0] && false === stripos( $text, 'elementor<' ) && false === stripos( $text, 'elementor ' . 'pro' ), 'Texto de privacidad con wp_add_privacy_policy_content().' );
	foreach ( array( 'digi_seen', 'digi_seen_', 'digi_visitor_tz', 'digi_session', 'digi-visit-count-', '_digi_', 'WP Consent API', '30 días', 'no protegen contenido' ) as $needle ) {
		check_pv( false !== strpos( $text, $needle ), "La política de privacidad nombra {$needle}." );
	}
	check_pv( false === strpos( $text, 'http' ), 'Sin enlaces.' );
	// F4-Woo (V.4.5): encabezado genérico y veraz para todo Elements; sin el filtro, la misma lista de siempre.
	check_pv( false !== strpos( $text, 'Según las funciones que use este sitio, guarda en el navegador del visitante (cookies o almacenamiento local) o en su cuenta' ) && false === strpos( $text, 'Sólo escribe estas cookies' ), 'El encabezado del texto de privacidad es genérico (cookies, almacenamiento local o cuenta).' );
	check_pv( 5 === substr_count( $text, '<li>' ) && false === strpos( $text, 'digi_wishlist' ), 'Sin el filtro, la lista de siempre (cinco elementos de las condiciones).' );
	check_pv( false !== strpos( $text, 'Las condiciones de visualización deciden qué se muestra' ), 'El párrafo final se acota a las condiciones.' );
	$GLOBALS['pv']['filters']['digitalisimo_elements_privacy_items'] = static function ( $items ) { $items[] = '<strong>digi_wishlist</strong>: lista de deseos.'; return $items; };
	$with = PV::privacy_text();
	check_pv( 6 === substr_count( $with, '<li>' ) && false !== strpos( $with, '<li><strong>digi_wishlist</strong>: lista de deseos.</li>' ) && false !== strpos( $with, 'digi_seen' ), 'Con el filtro, el elemento añadido aparece después de los de siempre.' );
	$GLOBALS['pv']['filters']['digitalisimo_elements_privacy_items'] = 'roto';
	check_pv( 5 === substr_count( PV::privacy_text(), '<li>' ), 'Un filtro que no devuelve una lista no borra los elementos de siempre.' );
	unset( $GLOBALS['pv']['filters']['digitalisimo_elements_privacy_items'] );
	eval( 'function wp_add_cookie_info( ...$args ) { $GLOBALS["pv"]["cookies"][] = $args; }' );
	PV::register_cookie_info();
	check_pv( 'digi_seen' === $GLOBALS['pv']['cookies'][0][0] && 'preferences' === $GLOBALS['pv']['cookies'][0][2], 'Con la WP Consent API, la cookie se declara en «preferences».' );

	// 11. Prop v4 con el transformador de PRO (`extract_from_settings()`); si falla, se lee a mano.
	eval( 'namespace ElementorPro\\Modules\\AtomicWidgets\\Transformers; class Display_Conditions { public static function extract_from_settings( $settings ) { if ( "throw" === $GLOBALS["pv"]["mode"] ) { throw new \\RuntimeException( "x" ); } return "ok" === $GLOBALS["pv"]["mode"] ? array( json_encode( array( array( pv_visit( array( 88 ) ) ), null ) ) ) : "[]"; } }' );
	$atomic = array( pv_v4( 'v', array( array( pv_visit( array( 21 ) ) ) ) ) );
	$GLOBALS['pv']['mode'] = 'ok';
	check_pv( array( 88 ) === PV::index( $atomic ), 'El prop v4 se lee con el transformador de PRO si existe.' );
	$GLOBALS['pv']['mode'] = 'throw';
	check_pv( array( 21 ) === PV::index( $atomic ), 'Si el transformador falla, se lee a mano.' );
	$GLOBALS['pv']['mode'] = 'off';
	check_pv( array( 21 ) === PV::index( $atomic ), 'Si el transformador no resuelve nada, se lee a mano.' );

	echo "F7a · visitas: cookie por sitio, lectura estricta, índice publicado en opción, recálculo, purga, script con consentimiento y menos de 400 B, y privacidad en verde.\n";
}
