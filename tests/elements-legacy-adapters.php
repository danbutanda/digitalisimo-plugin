<?php
namespace {
	define( 'ABSPATH', __DIR__ );
	function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
	function check_adapter( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$base = __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-legacy/';
	require $base . 'class-translator.php';
	require $base . 'class-migration.php';
	use Digitalisimo\Elements\Legacy\Translator;
	use Digitalisimo\Elements\Legacy\Migration;

	// Todos los mapas cargan, apuntan a un widget propio y sus estilos ya no nombran clases de Element Pack.
	$ids = Translator::ids();
	check_adapter( in_array( 'bdt-accordion', $ids, true ) && ! preg_grep( '/\.styles$/', $ids ), 'Los mapas deben listarse sin los archivos de estilos.' );
	foreach ( $ids as $id ) {
		$map = Translator::map( $id );
		check_adapter( is_array( $map ) && 0 === strpos( (string) $map['target'], 'digitalisimo-' ), 'Mapa inválido: ' . $id );
		foreach ( Translator::styles( $id ) as $control ) {
			$css = wp_json_encode_stub( $control );
			check_adapter( ! empty( $control['name'] ) && false === strpos( $css, '.bdt-' ), 'Estilo con clase de Element Pack en ' . $id . ': ' . $control['name'] );
			check_adapter( isset( $control['group'] ) ? 0 === strpos( $control['selector'], '{{WRAPPER}}' ) : ! empty( $control['selectors'] ), 'Estilo sin selector en ' . $id );
		}
	}
	function wp_json_encode_stub( $value ) { return json_encode( $value ); }
	check_adapter( null === Translator::map( '../class-translator' ) && null === Translator::map( 'bdt-no-existe' ), 'Sólo se cargan mapas por ID válido.' );

	// Defaults de Element Pack: Elementor no guarda lo que coincide con ellos.
	$empty = Translator::translate( 'bdt-accordion', array() );
	check_adapter( 3 === count( $empty['tabs'] ) && 'Accordion #1' === $empty['tabs'][0]['tab_title'] && 'custom' === $empty['tabs'][0]['source'], 'Sin filas guardadas deben usarse las tres de Element Pack.' );
	check_adapter( 0 === $empty['active_item'] && 'span' === $empty['title_html_tag'] && 'fas fa-plus' === $empty['accordion_icon']['value'], 'Los defaults de Element Pack deben traducirse a valores propios.' );
	check_adapter( 'bdt-accordion' === $empty[ Translator::MARKER ] && ! isset( $empty['collapsible'], $empty['view'] ), 'Se marca la traducción y se descartan opciones sin equivalente.' );

	$custom = Translator::translate( 'bdt-accordion', array(
		'tabs'                    => array( array( '_id' => 'x', 'tab_title' => 'Uno' ) ),
		'active_item'             => '2',
		'always_active_all_items' => 'yes',
		'title_html_tag'          => 'h1',
		'_margin'                 => array( 'top' => '10' ),
		'title_color'             => '#123456',
	) );
	check_adapter( 2 === $custom['active_item'] && 'yes' === $custom['open_all_initially'] && ! isset( $custom['always_active_all_items'] ) && 'h2' === $custom['title_html_tag'], 'Renombres y valores deben aplicarse.' );
	check_adapter( 'Accordion Content' === $custom['tabs'][0]['tab_content'] && 'x' === $custom['tabs'][0]['_id'], 'Las filas completan defaults y conservan su ID.' );
	check_adapter( array( 'top' => '10' ) === $custom['_margin'] && '#123456' === $custom['title_color'], 'Ajustes comunes y de estilo pasan sin cambios.' );
	check_adapter( $custom === Translator::translate( 'bdt-accordion', $custom ), 'Un documento ya traducido no se vuelve a traducir.' );

	// Variantes responsivas, dinámicas y limpieza de HTML.
	$heading = Translator::translate( 'bdt-animated-heading', array(
		'pre_heading'        => 'Hola <b>soy</b>',
		'pre_heading_mobile' => 'x',
		'animated_heading'   => 'uno, dos ,tres,',
		'__dynamic__'        => array( 'post_heading' => '[elementor-tag id="1"]' ),
	) );
	check_adapter( 'Hola soy' === $heading['before_text'] && 'x' === $heading['before_text_mobile'] && ! isset( $heading['pre_heading'] ), 'Renombrar debe incluir variantes responsivas y quitar HTML.' );
	check_adapter( "uno\ndos\ntres" === $heading['animated_text'] && 'yes' === $heading['rotate'] && 2500 === $heading['interval'], 'Las palabras separadas por coma deben alternarse.' );
	check_adapter( isset( $heading['__dynamic__']['after_text'] ) && ! isset( $heading['__dynamic__']['post_heading'] ), 'Las etiquetas dinámicas siguen al ajuste renombrado.' );
	$split = Translator::translate( 'bdt-animated-heading', array( 'heading_layout' => 'split_text', 'animated_heading' => 'una, frase' ) );
	check_adapter( 'una, frase' === $split['animated_text'] && '' === $split['rotate'], 'El texto partido es una sola frase.' );

	$divider = Translator::translate( 'bdt-advanced-divider', array( 'advanced_divider_select' => 'line-star', 'divider_line_align' => 'left', 'max_width' => array( 'size' => 200 ) ) );
	check_adapter( 'star' === $divider['divider_type'] && 'left' === $divider['divider_align'] && 200 === $divider['divider_width']['size'], 'El separador usa la forma propia equivalente.' );
	$asset = Translator::translate( 'bdt-advanced-divider', array( 'advanced_divider_type' => 'choose', 'advanced_divider_choose' => array( 'url' => 'https://s.test/wp-content/plugins/bdthemes-element-pack/assets/images/divider/heart.svg' ) ) );
	$media = Translator::translate( 'bdt-advanced-divider', array( 'advanced_divider_type' => 'choose', 'advanced_divider_choose' => array( 'id' => 9, 'url' => 'https://s.test/wp-content/uploads/linea.svg' ) ) );
	check_adapter( 'line' === $asset['divider_type'] && 'image' === $media['divider_type'] && 9 === $media['divider_image']['id'], 'Sólo se conservan imágenes propias del sitio, no recursos de Element Pack.' );

	// Migración: al widget propio sólo si todos los ajustes existen allí.
	$element  = array( 'id' => 'e1', 'elType' => 'widget', 'widgetType' => 'bdt-accordion', 'settings' => array( 'tabs' => array( array( 'tab_title' => 'A' ) ), '_padding' => array() ) );
	$controls = array_fill_keys( array( 'tabs', 'active_item', 'multiple', 'open_all_initially', 'title_html_tag', 'show_custom_icon', 'accordion_icon', 'accordion_active_icon', 'icon_align' ), array() );
	list( $native, $state ) = Migration::convert_element( $element, $controls );
	check_adapter( 'native' === $state && 'digitalisimo-accordion' === $native['widgetType'] && ! isset( $native['settings'][ Translator::MARKER ] ), 'Sin estilos de Element Pack el elemento pasa al widget propio.' );
	$element['settings']['accordion_item_background_color'] = '#000';
	list( $kept, $state ) = Migration::convert_element( $element, $controls );
	check_adapter( 'adapter' === $state && 'bdt-accordion' === $kept['widgetType'] && 'bdt-accordion' === $kept['settings'][ Translator::MARKER ], 'Con estilos que sólo tiene el adaptador, se queda en él ya traducido.' );
	list( , $state ) = Migration::convert_element( array( 'widgetType' => 'bdt-sin-mapa', 'settings' => array() ), $controls );
	check_adapter( 'skip' === $state, 'Un widget sin mapa no se toca.' );

	echo 'DIGITALÍSIMO Elements: adaptadores de Element Pack (' . count( $ids ) . ") validados.\n";
}
