<?php
/**
 * IA Tools · el Asistente con la identidad del sitio (caso plomerito 3 del 2026-11-25, IA Tools 1.0.73 + Elements
 * 4.3.0.197): la landing de «todos los productos al 40 %» salió con los colores de DIGITALÍSIMO y una plantilla de
 * curso; el Asistente dijo «hay que corregirlo en el editor porque desde aquí no puedo», luego «ya usa los colores de tu
 * kit» sin revisarlo y terminó pidiéndole al dueño que active el «Editor atómico». Sin WordPress:
 *   K1  las instrucciones: la oferta de toda la tienda o de una categoría va con store_offer (nunca con una plantilla de
 *       cursos); la página sale sola con los colores, las tipografías, la cabecera y el pie del sitio; nunca pedir que
 *       active nada; el botón a la tienda, nunca al carrito vacío; corregir su propio borrador (nada de «desde aquí no
 *       puedo»); afirmar sólo lo comprobado (look);
 *   K2  con el editor atómico apagado, el catálogo no ofrece las herramientas del constructor que lo exigen (la razón
 *       de «activa el Editor atómico»); con él encendido, sí;
 *   K3  read-page-text trae el look de una página del constructor: los colores y las tipografías que lleva de verdad,
 *       los del sitio y si coinciden (una con la paleta DIGITALÍSIMO no coincide; una con los del sitio, sí), y si lleva
 *       la cabecera y el pie del sitio.
 * Falla con el código de 1.0.73. Uso: php tests/ia-assistant-marca.php
 */
namespace Elementor\Modules\Mcp\Utils {
	class Mcp_V4_Gate {
		public static $atomic = false;
		public static function is_gated( string $id ): bool {
			return in_array( $id, array( 'elementor/manage-elements', 'elementor/build-composition', 'elementor/manage-global-variable' ), true );
		}
		public static function is_atomic_editor_active(): bool {
			return self::$atomic;
		}
	}
}

namespace {
	require __DIR__ . '/ia-assistant-agent/wp.php';
	require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai.php';
	foreach ( array( 'model', 'tools', 'history', 'guard', 'ability', '' ) as $part ) {
		require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-assistant' . ( $part ? '-' . $part : '' ) . '.php';
	}
	require dirname( __DIR__ ) . '/digitalisimo-ia-tools/includes/class-digitalisimo-ai-site-reader.php';
	// El kit de ventas de Elements (lo que lee la identidad del sitio).
	require dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-sales/class-site.php';
	require dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-sales/class-brand.php';

	/* ---------------- K1: las instrucciones ---------------- */
	$sys = Digitalisimo_AI_Assistant::system_prompt();
	foreach ( array(
		'con store_offer',
		'Nunca armes la oferta de una tienda con una plantilla de cursos',
		'«Lo que vas a lograr», bonus, módulos, factura ni avisos de resultados e ingresos',
		'sale sola con los colores, las tipografías, la cabecera y el pie DEL SITIO',
		'nunca le pidas a la persona que active, prenda o configure algo',
		'nunca al carrito vacío',
		'corrígelo tú en ese mismo turno, antes de contestar',
		'Nunca digas «hay que corregirlo en el editor» ni «desde aquí no puedo»',
		'Afirma sólo lo que comprobaste',
		'look.matches_site',
	) as $piece ) {
		check( false !== strpos( $sys, $piece ), "K1: las instrucciones dicen «{$piece}»" );
	}
	// Lo que ya funcionaba sigue igual (caso plomerito 2).
	foreach ( array( 'créalo YA en borrador', 'Como mucho una pregunta corta', 'Haz únicamente lo que la persona pidió', 'Nunca escribas IDs', 'sólo lo propones cuando la persona lo pidió' ) as $piece ) {
		check( false !== strpos( $sys, $piece ), "K1: siguen las instrucciones de «primero hacer y mostrar»: «{$piece}»" );
	}

	/* ---------------- K2: las herramientas que exigen el editor atómico ---------------- */
	$GLOBALS['options']['digitalisimo_ai_options'] = array( 'providers' => array( 'anthropic' => array( 'enabled' => 1, 'key' => KEY_ANTHROPIC, 'url' => '' ) ) );
	$meta = array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'mcp' => array( 'public' => true, 'type' => 'tool' ) );
	foreach ( array( 'elementor/manage-elements', 'elementor/build-composition', 'elementor/manage-global-variable', 'elementor/create-page' ) as $name ) {
		wp_register_ability( $name, array( 'label' => $name, 'category' => strtok( $name, '/' ), 'input_schema' => array( 'type' => 'object', 'properties' => array() ), 'permission_callback' => '__return_true', 'execute_callback' => function () { return array(); }, 'meta' => $meta ) );
	}
	// El kit: crea el borrador o lo rehace con post_id (como el real, con su check).
	$GLOBALS['kit_runs'] = array();
	wp_register_ability(
		'digitalisimo-sales/create-page',
		array(
			'label'               => 'Kit de ventas: crear una página en borrador',
			'category'            => 'digitalisimo-sales',
			'input_schema'        => array( 'type' => 'object', 'properties' => array( 'store_offer' => array( 'type' => 'object' ), 'title' => array( 'type' => 'string' ), 'post_id' => array( 'type' => 'integer' ) ) ),
			'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
			'execute_callback'    => function ( $in ) {
				$GLOBALS['kit_runs'][] = $in;
				$id = isset( $in['post_id'] ) ? (int) $in['post_id'] : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Oferta', 'post_author' => get_current_user_id() ) );
				$GLOBALS['last_page'] = $id;
				return array( 'ok' => true, 'id' => $id, 'status' => 'draft', 'regenerated' => isset( $in['post_id'] ), 'preview_url' => 'https://sitio.test/?page_id=' . $id . '&preview=true', 'edit_url' => 'https://sitio.test/wp-admin/post.php?post=' . $id . '&action=elementor', 'check' => array( 'ok' => false ) );
			},
			'meta'                => $meta,
		)
	);
	\Elementor\Modules\Mcp\Utils\Mcp_V4_Gate::$atomic = false;
	$off = Digitalisimo_AI_Assistant_Tools::catalog();
	check( ! isset( $off['elementor/manage-elements'] ) && ! isset( $off['elementor/build-composition'] ) && ! isset( $off['elementor/manage-global-variable'] ), 'K2: con el editor atómico apagado, no se ofrecen las herramientas que lo exigen · ' . implode( ', ', array_keys( $off ) ) );
	check( isset( $off['elementor/create-page'] ) && isset( $off['digitalisimo-sales/create-page'] ), 'K2: las demás sí' );
	\Elementor\Modules\Mcp\Utils\Mcp_V4_Gate::$atomic = true;
	$on = Digitalisimo_AI_Assistant_Tools::catalog();
	check( isset( $on['elementor/manage-elements'] ) && isset( $on['elementor/manage-global-variable'] ), 'K2: con el editor atómico encendido, se ofrecen' );

	/* ---------------- K3: el look de una página ---------------- */
	// El kit del sitio: azul y amarillo (como el del caso, inventado); su cabecera, en uso, con esos colores.
	$GLOBALS['options']['elementor_active_kit'] = 50;
	$kit = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Kit' ) );
	$GLOBALS['options']['elementor_active_kit'] = $kit;
	update_post_meta(
		$kit,
		'_elementor_page_settings',
		array(
			'system_colors'     => array(
				array( '_id' => 'primary', 'title' => 'Principal', 'color' => '#2546C8' ),
				array( '_id' => 'text', 'title' => 'Texto', 'color' => '#333333' ),
				array( '_id' => 'accent', 'title' => 'Acento', 'color' => '#F5CE3E' ),
			),
			'system_typography' => array( array( '_id' => 'primary', 'typography_font_family' => 'Red Hat Display' ) ),
		)
	);
	$header = wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'post_title' => 'Cabecera' ) );
	update_post_meta( $header, '_elementor_template_type', 'header' );
	update_post_meta( $header, '_elementor_conditions', array( 'include/general' ) );
	update_post_meta( $header, '_elementor_data', wp_json_encode( array( array( 'id' => 'h', 'elType' => 'container', 'settings' => array( 'background_background' => 'classic', 'background_color' => '#2546C8' ), 'elements' => array( array( 'id' => 'b', 'elType' => 'widget', 'widgetType' => 'button', 'settings' => array( 'background_color' => '#F5CE3E', 'typography_font_family' => 'Red Hat Display' ), 'elements' => array() ) ) ) ) ) );
	$page = function ( array $els, $template ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Landing' ) );
		update_post_meta( $id, '_elementor_data', wp_json_encode( $els ) );
		update_post_meta( $id, '_wp_page_template', $template );
		return $id;
	};
	$heading = function ( $color, $global = '' ) {
		$s = array( 'title' => 'Oferta', 'title_color' => $color, 'typography_typography' => 'custom', 'typography_font_family' => 'Red Hat Display' );
		if ( '' !== $global ) {
			$s['__globals__'] = array( 'title_color' => 'globals/colors?id=' . $global );
			$s['title_color'] = '';
		}
		return array( 'id' => substr( md5( $color . $global ), 0, 7 ), 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $s, 'elements' => array() );
	};
	$old  = $page( array( array( 'id' => 'c', 'elType' => 'container', 'settings' => array( 'background_background' => 'classic', 'background_color' => '#1F252C' ), 'elements' => array( $heading( '#00FF99' ) ) ) ), 'elementor_canvas' );
	$GLOBALS['user'] = 1;
	$read = Digitalisimo_AI_Site_Reader::read_text( array( 'id' => $old ) );
	check( isset( $read['look'] ) && false === $read['look']['matches_site'] && in_array( '#00FF99', $read['look']['digitalisimo'], true ) && false === $read['look']['site_header_footer'], 'K3: una landing con la paleta DIGITALÍSIMO y sin cabecera ni pie no coincide con el sitio · ' . wp_json_encode( $read['look'] ?? null ) );
	check( '#2546C8' === $read['look']['site_colors']['primary'] && '#F5CE3E' === $read['look']['site_colors']['accent'], 'K3: dice cuáles son los colores del sitio' );
	$new  = $page( array( array( 'id' => 'd', 'elType' => 'container', 'settings' => array( 'background_background' => 'classic', '__globals__' => array( 'background_color' => 'globals/colors?id=primary' ) ), 'elements' => array( $heading( '#FFFFFF' ), $heading( '', 'accent' ) ) ) ), 'elementor_header_footer' );
	$read = Digitalisimo_AI_Site_Reader::read_text( array( 'id' => $new ) );
	check( true === $read['look']['matches_site'] && in_array( '#2546C8', $read['look']['colors'], true ) && in_array( '#F5CE3E', $read['look']['colors'], true ) && true === $read['look']['site_header_footer'], 'K3: una landing con los globales del kit del sitio y su cabecera y pie coincide · ' . wp_json_encode( $read['look'] ) );

	/* ---------------- K4: como mucho una corrección por turno ---------------- */
	// El modelo (simulado) ve check.ok en falso y quiere rehacer una y otra vez: sólo se hace la primera corrección.
	add_filter( 'digitalisimo_ai_assistant_limits', function () { return array( 'per_minute' => 0, 'per_day' => 0, 'concurrent' => 1 ); } );
	Digitalisimo_AI_Assistant::init();
	$n    = 0;
	$call = function ( $name, array $input ) use ( &$n ) { return array( 'id' => 'toolu_k' . ( ++$n ), 'name' => $name, 'input' => $input ); };
	$GLOBALS['script'] = array(
		array( 'calls' => array( $call( 'digitalisimo-sales-create-page', array( 'store_offer' => array( 'percent' => 40 ) ) ) ) ),
		array( 'calls' => array( $call( 'digitalisimo-sales-create-page', array( 'store_offer' => array( 'percent' => 40 ), 'post_id' => 0 ) ) ) ),
		array( 'calls' => array( $call( 'digitalisimo-sales-create-page', array( 'store_offer' => array( 'percent' => 40 ), 'post_id' => 0 ) ) ) ),
		array( 'calls' => array( $call( 'digitalisimo-sales-create-page', array( 'store_offer' => array( 'percent' => 40 ), 'post_id' => 0 ) ) ) ),
		'Listo: [Ver la landing](https://sitio.test/?page_id=1&preview=true).',
	);
	$GLOBALS['model_calls'] = array();
	add_filter(
		'digitalisimo_ai_assistant_pre_model',
		function ( $pre, $request ) {
			$GLOBALS['model_calls'][] = $request;
			$next = $GLOBALS['script'] ? array_shift( $GLOBALS['script'] ) : 'Listo.';
			// Rehacer el borrador que creó (su id real).
			if ( is_array( $next ) && isset( $next['calls'][0]['input']['post_id'] ) && $GLOBALS['kit_runs'] ) {
				$next['calls'][0]['input']['post_id'] = (int) ( $GLOBALS['last_page'] ?? 0 );
			}
			return $next;
		},
		10,
		2
	);
	$req      = new WP_REST_Request( array( 'message' => 'créame una landing de oferta navideña con todos los productos al 40 %' ), array( 'X-Digitalisimo-Assistant' => wp_create_nonce( Digitalisimo_AI_Assistant::NONCE ) ) );
	$response = Digitalisimo_AI_Assistant::rest_message( $req );
	check( $response instanceof WP_REST_Response, 'K4: la ruta responde' );
	$tools = array_values( array_filter( $response->data['events'], function ( $e ) { return 'tool' === $e['type']; } ) );
	$runs  = count( $GLOBALS['kit_runs'] );
	$skip  = array_values( array_filter( $tools, function ( $t ) { return 'skipped' === $t['message']['status']; } ) );
	check( 2 === $runs && count( $skip ) >= 1, "K4: se crea la landing y se corrige UNA vez; los demás intentos del mismo turno no se hacen ($runs corridas del kit, " . count( $skip ) . ' retenidas)' );
	$last = null;
	foreach ( array_reverse( $GLOBALS['model_calls'] ) as $request ) {
		foreach ( array_reverse( $request['messages'] ) as $m ) {
			if ( 'tool' === $m['role'] ) {
				$last = json_decode( $m['content'], true );
				break 2;
			}
		}
	}
	check( is_array( $last ) && false !== strpos( (string) ( $last['error'] ?? '' ), 'ya creaste la landing y la corregiste una vez' ), 'K4: el modelo sabe por qué y que debe entregarla como está · ' . json_encode( $last, JSON_UNESCAPED_UNICODE ) );
	check( false !== strpos( Digitalisimo_AI_Assistant::system_prompt(), 'Como mucho una corrección por turno' ), 'K4: las instrucciones lo dicen' );

	echo "Asistente IA con la identidad del sitio: $checks comprobaciones correctas.\n";
}
