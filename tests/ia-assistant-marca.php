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
	foreach ( array( 'elementor/manage-elements', 'elementor/build-composition', 'elementor/manage-global-variable', 'elementor/create-page', 'digitalisimo-sales/create-page' ) as $name ) {
		wp_register_ability( $name, array( 'label' => $name, 'category' => strtok( $name, '/' ), 'input_schema' => array( 'type' => 'object', 'properties' => array() ), 'permission_callback' => '__return_true', 'execute_callback' => function () { return array(); }, 'meta' => $meta ) );
	}
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

	echo "Asistente IA con la identidad del sitio: $checks comprobaciones correctas.\n";
}
