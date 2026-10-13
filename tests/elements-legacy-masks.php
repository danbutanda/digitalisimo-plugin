<?php
/**
 * Máscaras y separadores de Element Pack: copia saneada de los SVG usados a los archivos del sitio,
 * CSS de «Image Masking», conversión de los ajustes y separadores propios registrados por Elements.
 */
namespace {
	$tmp = sys_get_temp_dir() . '/digi-masks-' . getmypid();
	define( 'ABSPATH', __DIR__ );
	define( 'WP_PLUGIN_DIR', $tmp . '/plugins' );
	define( 'ELEMENTOR_PATH', $tmp . '/plugins/digitalisimo-elements/elementor/' );
	function check_mask( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function trailingslashit( $v ) { return rtrim( (string) $v, '/\\' ) . '/'; }
	function wp_normalize_path( $v ) { return str_replace( '\\', '/', (string) $v ); }
	function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
	function esc_url_raw( $v ) { return preg_match( '#^https?://#', (string) $v ) ? (string) $v : ''; }
	function plugins_url( $path = '' ) { return 'http://sitio.test/wp-content/plugins/' . ltrim( (string) $path, '/' ); }
	function wp_upload_dir() { return array( 'basedir' => $GLOBALS['tmp'] . '/uploads', 'baseurl' => 'http://sitio.test/wp-content/uploads', 'error' => false ); }
	function add_filter() {}

	$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/></svg>';
	$pack = WP_PLUGIN_DIR . '/bdthemes-element-pack/assets/images/';
	foreach ( array( 'mask/shape-7.svg', 'mask/shape-21.svg', 'mask/color-shape-21.svg', 'shape-divider/mosque.svg', 'shape-divider/mosque-negative.svg' ) as $file ) {
		wp_mkdir_p( dirname( $pack . $file ) );
		file_put_contents( $pack . $file, $svg );
	}
	file_put_contents( $pack . 'mask/shape-9.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' );
	// Copias que Element Pack dejaba dentro de Elementor (aquí, el núcleo incluido).
	foreach ( array( 'assets/mask-shapes/shape-12.svg', 'assets/shapes/ep-hill.svg' ) as $file ) {
		wp_mkdir_p( dirname( ELEMENTOR_PATH . $file ) );
		file_put_contents( ELEMENTOR_PATH . $file, $svg );
	}

	require __DIR__ . '/../digitalisimo-migration/legacy/class-masks.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-divider-shapes.php';
	use Digitalisimo\Elements\Legacy\Masks;
	$uploads = 'http://sitio.test/wp-content/uploads/digitalisimo-elements/';
	$own     = $tmp . '/uploads/digitalisimo-elements/';

	check_mask( null === Masks::mask( 'shape-30', false ) && null !== Masks::mask( 'shape-7', false ) && ! is_file( $own . 'masks/mask-7.svg' ), 'Comprobar si hay archivo no escribe nada.' );
	check_mask( $uploads . 'masks/mask-7.svg' === Masks::mask( 'shape-7' ) && $svg === file_get_contents( $own . 'masks/mask-7.svg' ), 'La máscara usada se copia de Element Pack a los archivos del sitio.' );
	check_mask( $uploads . 'masks/mask-12.svg' === Masks::mask( 'shape-12' ), 'Si Element Pack ya no está, sirve la copia que dejó en Elementor.' );
	check_mask( null === Masks::mask( 'shape-9' ) && ! is_file( $own . 'masks/mask-9.svg' ), 'Un SVG con scripts no se copia.' );
	check_mask( null === Masks::mask( '../shape-7' ) && null === Masks::mask( 'circle' ) && null === Masks::mask( 'shape-30' ), 'Sólo nombres de máscara de Element Pack con archivo.' );

	check_mask( 'digi-mezquita' === Masks::divider( 'ep-mosque' ) && is_file( $own . 'shapes/mezquita.svg' ) && is_file( $own . 'shapes/mezquita-negative.svg' ), 'El separador y su versión negativa pasan a un separador propio.' );
	check_mask( 'digi-colinas' === Masks::divider( 'ep-hill' ) && ! is_file( $own . 'shapes/colinas-negative.svg' ), 'Un separador sin versión negativa también se copia.' );
	check_mask( null === Masks::divider( 'ep-festival' ) && null === Masks::divider( 'mountains' ), 'Separadores sin archivo o nativos no se tocan.' );

	$dividers = Masks::legacy_dividers( array() );
	check_mask( $own . 'shapes/mezquita.svg' === $dividers['ep-mosque']['path'] && $dividers['ep-mosque']['has_negative'] && $uploads . 'shapes/mezquita.svg' === $dividers['ep-mosque']['url'] && ! $dividers['ep-hill']['has_negative'] && ! isset( $dividers['ep-festival'] ), 'Mientras dura la migración, los separadores heredados se pintan desde la copia propia.' );
	$shapes = \Digitalisimo\Elements\Divider_Shapes::register( array( 'mountains' => array( 'title' => 'Montañas' ) ) );
	check_mask( isset( $shapes['mountains'] ) && $own . 'shapes/mezquita.svg' === $shapes['digi-mezquita']['path'] && $shapes['digi-mezquita']['has_negative'] && 'Mezquita' === $shapes['digi-mezquita']['title'] && ! $shapes['digi-colinas']['has_negative'] && ! isset( $shapes['digi-mezquita-negative'] ), 'Elements ofrece los separadores guardados en los archivos del sitio.' );

	$box = array( 'image_mask_popover' => 'yes', 'image_mask_shape_default' => 'shape-7' );
	$css = Masks::css( $box, '.elementor-image-box-img img', '.elementor-image-box-img' );
	check_mask( 'selector .elementor-image-box-img img{-webkit-mask-image:url("' . $uploads . 'masks/mask-7.svg");mask-image:url("' . $uploads . 'masks/mask-7.svg");-webkit-mask-position:center center;mask-position:center center;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;-webkit-mask-size:contain;mask-size:contain}' === $css, 'La máscara usa los valores por defecto de Element Pack.' );
	check_mask( '' === Masks::css( array( 'image_mask_shape_default' => 'shape-7' ), 'img' ) && '' === Masks::css( array( 'image_mask_popover' => 'yes', 'image_mask_shape_default' => 'shape-30' ), 'img' ), 'Sin activar, o sin archivo, no hay máscara.' );
	$custom = Masks::css( array( 'image_mask_popover' => 'yes', 'image_mask_shape' => 'custom', 'image_mask_shape_custom' => array( 'url' => 'https://sitio.test/m.svg' ), 'image_mask_shape_position' => 'top-left', 'image_mask_shape_repeat' => 'round-space', 'image_mask_shape_size' => 'initial', 'image_mask_shape_custom_size' => array( 'size' => 50, 'unit' => 'px' ) ), 'img' );
	check_mask( str_contains( $custom, 'url("https://sitio.test/m.svg")' ) && str_contains( $custom, 'mask-position:top left' ) && str_contains( $custom, 'mask-repeat:round space' ) && str_contains( $custom, 'mask-size:50px' ) && ! str_contains( $custom, 'initial' ), 'Figura propia, posición, repetición y tamaño propio.' );
	$color = Masks::css( array( 'image_mask_popover' => 'yes', 'image_mask_shape_default' => 'shape-21' ), 'img', '' );
	check_mask( str_contains( $color, 'selector{position:relative}selector::before{' ) && str_contains( $color, 'masks/mask-21-color.svg' ) && ! str_contains( Masks::css( $box, 'img' ), '::before' ), 'La versión en color se superpone sólo en los widgets nativos.' );
	check_mask( str_contains( Masks::widget_css( 'digitalisimo-fancy-card', $box ), 'selector .digi-fancy-card__image{' ) && '' === Masks::widget_css( 'heading', $box ), 'Los widgets de Element Pack enmascaran la imagen de su destino.' );

	$errors  = array();
	$heading = array( 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( '_mask_switch' => 'yes', '_mask_shape' => 'shape-7', '_mask_size' => 'cover' ) );
	$section = array( 'elType' => 'section', 'settings' => array( 'shape_divider_top' => 'ep-mosque', 'shape_divider_top_negative' => 'yes', 'shape_divider_bottom' => 'ep-festival' ) );
	$image   = array( 'elType' => 'widget', 'widgetType' => 'image-box', 'settings' => $box + array( 'image_mask_shape_size' => 'cover', 'custom_css' => 'selector{color:red}', '__dynamic__' => array( 'image_mask_shape_custom' => 'x' ) ) );
	$lost    = array( 'elType' => 'widget', 'widgetType' => 'image', 'settings' => array( '_mask_switch' => 'yes', '_mask_shape' => 'shape-30' ) );
	check_mask( Masks::is_legacy( $heading ) && Masks::is_legacy( $section ) && Masks::is_legacy( $image ) && ! Masks::is_legacy( array( 'widgetType' => 'heading', 'settings' => array( 'image_mask_popover' => 'yes', '_mask_shape' => 'circle', 'shape_divider_top' => 'waves' ) ) ), 'Se detectan las figuras de Element Pack, no las nativas ni las claves de otros widgets.' );
	check_mask( array( 'ep-festival' ) === Masks::missing( $section ) && array( 'shape-30' ) === Masks::missing( $lost ) && array() === Masks::missing( $heading ), 'El inventario avisa de las figuras sin archivo.' );

	$s = Masks::convert( $heading, $errors );
	check_mask( 'custom' === $s['_mask_shape'] && $uploads . 'masks/mask-7.svg' === $s['_mask_image']['url'] && 'cover' === $s['_mask_size'] && ! Masks::is_legacy( array( 'settings' => $s ) + $heading ), 'La máscara nativa pasa a «Personalizada» con la copia propia.' );
	$s = Masks::convert( $section, $errors );
	check_mask( 'digi-mezquita' === $s['shape_divider_top'] && 'yes' === $s['shape_divider_top_negative'] && '' === $s['shape_divider_bottom'] && in_array( 'Separador sin archivo, retirado: ep-festival', $errors, true ), 'El separador pasa al propio; sin archivo se retira y se anota.' );
	$s = Masks::convert( $image, $errors );
	check_mask( ! preg_grep( '/^image_mask_/', array_keys( $s ) ) && ! isset( $s['__dynamic__'] ) && str_starts_with( $s['custom_css'], "selector{color:red}\nselector .elementor-image-box-img img{" ) && str_contains( $s['custom_css'], 'mask-size:cover' ), '«Image Masking» pasa al CSS personalizado del elemento.' );
	$atomic = array( 'elType' => 'widget', 'widgetType' => 'e-image', 'settings' => array( 'image' => array( '$$type' => 'image' ), 'image_mask_popover' => array( '$$type' => 'boolean', 'value' => true ) ) );
	$s      = Masks::convert( $atomic, $errors );
	check_mask( Masks::is_legacy( $atomic ) && array( 'image' ) === array_keys( $s ) && in_array( 'Máscara de la imagen atómica no trasladada: la imagen se ve sin máscara', $errors, true ), 'La máscara de la imagen atómica se retira y se anota.' );
	$s = Masks::convert( $lost, $errors );
	check_mask( '' === $s['_mask_switch'] && ! isset( $s['_mask_shape'] ) && in_array( 'Máscara sin archivo, retirada: shape-30', $errors, true ), 'Una máscara sin archivo se retira para que la imagen siga visible.' );

	exec( 'rm -rf ' . escapeshellarg( $tmp ) );
	echo "DIGITALÍSIMO Migración: máscaras y separadores de Element Pack validados.\n";
}
