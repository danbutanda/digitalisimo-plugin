<?php
/**
 * Comentarios (`digitalisimo-post-comments`), el widget propio que sustituye al Post Comments de PRO (C4 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/post-comments.php`:
 * - recursos: ni hoja ni script (pinta la plantilla de comentarios del tema), nada vetado por el empaquetado;
 * - los mismos controles que PRO (cargado a la vez);
 * - render como PRO: `comments_template()` en la entrada actual o en la elegida (cambio y vuelta con el núcleo), aviso
 *   sólo en el editor o la vista previa con los comentarios cerrados, nada fuera de la plantilla;
 * - conversión con `Converter::tree()` sin `pro.unmapped`, con la entrada elegida y el CSS personalizado.
 */
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace {
	$GLOBALS['pc'] = array( 'open' => true, 'calls' => array() );
	function comments_open() { $GLOBALS['pc']['calls'][] = 'open'; return $GLOBALS['pc']['open']; }
	function comments_template() { $GLOBALS['pc']['calls'][] = 'template'; echo '<div id="comments" class="comments-area">Plantilla del tema</div>'; }

	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/post-comments.php' );
	use Digitalisimo\Elements\Theme_Elements\Module;
	use Digitalisimo\Elements\Theme_Elements\Widgets\Post_Comments;

	// El núcleo del constructor que ve el widget propio es el mismo que el de PRO en este entorno.
	\Elementor\Plugin::$instance = \ElementorPro\Plugin::elementor();

	$own = h_widget( Post_Comments::class );
	$pro = h_widget( \ElementorPro\Modules\ThemeElements\Widgets\Post_Comments::class );
	$map = require h_path( 'modules/digitalisimo-convert/maps/post-comments.php' );

	// 1. Recursos: ni hoja ni script.
	h_check( 'digitalisimo-post-comments' === $own->get_name() && 'Comentarios' === $own->get_title() && array( 'digitalisimo' ) === $own->get_categories() && 'eicon-comments' === $own->get_icon(), 'Nombre, título, categoría e icono.' );
	h_check( 'Comentarios' === Module::TITLES['digitalisimo-post-comments'] && 'widgets/class-post-comments.php' === Module::WIDGETS['digitalisimo-post-comments']['file'], 'En Module::WIDGETS y TITLES.' );
	h_check( array() === $own->get_style_depends() && array() === $own->get_script_depends() && ! is_file( h_path( Module::WIDGETS['digitalisimo-post-comments']['css'] ) ) && ! is_file( h_path( Module::WIDGETS['digitalisimo-post-comments']['js'] ) ), 'Sin hoja ni script: el aspecto es el de la plantilla del tema, como en PRO.' );
	h_check( false === $own->has_widget_inner_wrapper(), 'Sin envoltorio interior con el marcado optimizado.' );
	$file = h_path( 'modules/digitalisimo-theme-elements/widgets/class-post-comments.php' );
	h_check( ! preg_match( '/jquery|wp_enqueue_script|ElementorPro\\\\Modules\\\\ThemeElements/i', h_code( $file ) ), 'Sin jQuery, scripts ni clases del módulo de PRO.' );
	foreach ( array( $file, h_path( 'modules/digitalisimo-convert/maps/post-comments.php' ) ) as $f ) {
		h_check( ! preg_match( H_VETOED, (string) file_get_contents( $f ) ), basename( $f ) . ': nombra algo que el empaquetado veta.' );
	}

	// 2. Los mismos controles que PRO.
	$diff = h_compare_widgets( $pro, $own );
	h_check( array() === $diff, "Controles distintos de PRO:\n" . implode( "\n", $diff ) );
	h_check( array( '_skin', 'skin_temp', 'source_type', 'source_custom' ) === $own->order && 'query' === $own->controls['source_custom']['type'], 'Los cuatro controles de PRO, en su orden, con el control de búsqueda de entradas del constructor.' );

	// 3. Render como PRO.
	$run = static function ( $widget, array $settings ) {
		$GLOBALS['pc']['calls']        = array();
		$GLOBALS['h_wp']['switched'] = array();
		$html                          = h_render( $widget, $settings );
		return array( $html, $GLOBALS['pc']['calls'], $GLOBALS['h_wp']['switched'], \ElementorPro\Plugin::elementor()->db->stack );
	};
	foreach ( array( array(), array( 'source_type' => 'custom', 'source_custom' => '42' ) ) as $settings ) {
		$a = $run( $pro, $settings );
		$b = $run( $own, $settings );
		h_check( $a === $b, 'Mismo resultado que PRO con ' . json_encode( $settings ) . ': ' . json_encode( $b ) );
	}
	h_check( '<div id="comments" class="comments-area">Plantilla del tema</div>' === $b[0] && array( 42 ) === $b[2] && array() === $b[3], 'Con «Personalizado» pinta la plantilla en la entrada elegida y vuelve a la actual.' );
	$GLOBALS['pc']['open'] = false;
	list( $closed ) = $run( $own, array() );
	h_check( '<div id="comments" class="comments-area">Plantilla del tema</div>' === $closed, 'Comentarios cerrados en el sitio: la plantilla del tema decide, como PRO.' );
	$GLOBALS['h_wp']['edit_mode'] = true;
	list( $alert ) = $run( $own, array() );
	h_check( str_starts_with( $alert, '<div class="digi-post-comments__alert" role="alert"><strong>Los comentarios están cerrados.</strong>' ) && ! str_contains( $alert, 'Plantilla' ), 'En el editor, con los comentarios cerrados, un aviso en lugar de la plantilla.' );
	$GLOBALS['h_wp']['edit_mode'] = false;
	$GLOBALS['pc']['open']        = true;

	// 4. Conversión.
	$cases = array(
		'defaults' => array(),
		'custom'   => array( 'source_type' => 'custom', 'source_custom' => '42', '_skin' => '', 'skin_temp' => '' ),
		'css'      => array( 'custom_css' => ".elementor-widget-post-comments .comment-respond{margin:0}\nselector .comments-title{color:red}", '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
		'dynamic'  => array( 'source_type' => 'custom', '__dynamic__' => array( 'source_custom' => '[elementor-tag id="a1" name="post-id" settings="%7B%7D"]' ) ),
	);
	list( $out, $codes ) = h_convert( 'post-comments', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Sólo el CSS convertido se informa: ' . implode( ', ', $codes ) );
	h_check( 'custom' === $out['custom']['source_type'] && '42' === $out['custom']['source_custom'] && 'post-comments' === $out['defaults']['_digitalisimo_pro'], 'Origen y entrada elegida pasan tal cual.' );
	h_check( ".elementor-widget-digitalisimo-post-comments .comment-respond{margin:0}\nselector .comments-title{color:red}" === $out['css']['custom_css'] && '10' === $out['css']['_margin']['top'], 'CSS personalizado con la clase del envoltorio propio; las del tema no cambian.' );
	h_check( $cases['dynamic']['__dynamic__'] === $out['dynamic']['__dynamic__'], 'Dinámicos siguen a su control.' );
	h_check( $run( $pro, $cases['custom'] ) === $run( $own, $out['custom'] ), 'Lo convertido se pinta igual que PRO.' );
	h_classes_exist( $map, $own, '' );

	echo 'DIGITALÍSIMO Elements: Comentarios (' . count( $own->controls ) . ' controles, sin hoja ni script) y su mapa desde el Post Comments de PRO (' . count( $cases ) . " casos) validados.\n";
}
