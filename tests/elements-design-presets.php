<?php
/**
 * F7a · presets de estilo: frentes B (almacén, saneado, guarda y acciones) y C (control y aplanado).
 *
 * Con el núcleo real incluido (`tests/elements-design/core.php`): controles, grupos, saneador de valores por defecto
 * y generador del CSS del documento. Casos obligatorios de V.2.2 (tipografía propia y global, fondo clásico y
 * degradado, sombra, divisor, desplazamiento en RTL) en `tests/sources/design/presets.json`, que comparte el editor.
 *
 * B · almacén:
 * - opción `digitalisimo_elements_presets` sin carga automática (R-I12), límites, slug (D6), `inert`, `hash` y
 *   `updated` conservados, un solo vaciado por petición (sin vaciar al renombrar), cerrojo con robo, usos sin
 *   revisiones, papelera ni borradores automáticos (y los valores por defecto del kit), borrar con usos bloqueado (D8);
 * - saneado: cierre por dependencias (R-B1, V-B2), lista blanca por tipo (R-I11), exclusiones (contenido, MEDIA,
 *   URL, repetidores, `__dynamic__`, `custom_css`, `prefix_class`, `frontend_available`), `__globals__` filtrados,
 *   comprobación final (`\75 rl(`, `//`, `image-set(`, `@`), y `set_use_style_controls( true )` también en el front
 *   y en WP-CLI, donde el núcleo quita `options` y `size_units` (V-I4);
 * - permisos (D7): `manage_options` sin la restricción `design`; las cuatro acciones devuelven 403 sin permiso;
 * - `Guard`: vacía vínculos de otro tipo o inexistentes (V-I2) y restaura los de un rol «sólo contenido» (D7).
 *
 * C · control y aplanado:
 * - `digitalisimo_preset` sin `prefix_class`, una vez por pila con y sin marcado optimizado (V-I1), al principio de la
 *   pestaña Avanzado, con opciones sólo en el editor (en el front no lee la opción);
 * - aplanado en `elementor/element/before_parse_css` con los selectores del elemento (R-I10) y el valor del preset en
 *   lugar del valor por defecto local (V-B1); lo local gana en cualquier variante (R-M10); un preset con error, de
 *   otro tipo o inerte no se aplica (V-I3); nada cambia en `_elementor_data`; sin vínculos, 0 B y 0 consultas;
 * - la pestaña «Presets de estilo» de Ajustes del sitio.
 */
require __DIR__ . '/elements-design/core.php';
require_once __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-design/class-module.php';
\Digitalisimo\Elements\Design\Module::register_autoloader();
require __DIR__ . '/elements-design/presets-fixtures.php';

use Digitalisimo\Elements\Design\Css;
use Digitalisimo\Elements\Design\Module as Design;
use Digitalisimo\Elements\Design\Presets\Ajax;
use Digitalisimo\Elements\Design\Presets\Compiler;
use Digitalisimo\Elements\Design\Presets\Controls;
use Digitalisimo\Elements\Design\Presets\Guard;
use Digitalisimo\Elements\Design\Presets\Kit_Tab;
use Digitalisimo\Elements\Design\Presets\Module as Presets;
use Digitalisimo\Elements\Design\Presets\Store;
use Digitalisimo\Elements\Lock;

$check = 'check_design';

/** Estado limpio: WordPress, núcleo, cachés del almacén y los ganchos que usan las pilas y el CSS. */
function presets_boot( array $options = array() ): void {
	Store::flush();
	design_wp_reset();
	design_frontend( empty( $options['admin'] ) );
	design_boot( $options );
	Store::reset_cache();
	Compiler::reset_cache();
	foreach ( Controls::HOOKS as $hook ) {
		add_action( $hook, array( Controls::class, 'register' ), 10, 2 );
	}
	add_action( 'elementor/element/before_parse_css', array( Compiler::class, 'flatten' ), 10, 2 );
}

/** Lecturas de la opción de presets desde el último reinicio. */
function presets_reads(): int {
	return count( array_keys( $GLOBALS['dh']['queries'], 'get_option:' . Store::OPTION, true ) );
}

/** Excepción lanzada por una acción AJAX, o `null`. */
function presets_ajax_error( callable $action, array $data ): ?Exception {
	try {
		$action( $data );
	} catch ( Exception $error ) {
		return $error;
	}
	return null;
}

/** Normaliza un valor como lo hace un viaje por JSON (objetos vacíos y arrays). */
function presets_json( $value ) {
	return json_decode( json_encode( $value ), true );
}

$fixture = json_decode( (string) file_get_contents( __DIR__ . '/sources/design/presets.json' ), true );
$check( is_array( $fixture ) && 10 <= count( $fixture['cases'] ?? array() ), 'tests/sources/design/presets.json debe tener los casos compartidos.' );
$cases = array();
foreach ( $fixture['cases'] as $case ) {
	$cases[ $case['name'] ] = $case;
}
foreach ( array( 'heading-typography', 'heading-typography-global', 'container-background-classic', 'container-background-gradient', 'heading-box-shadow', 'divider-weight', 'heading-offset-rtl', 'heading-local-wins' ) as $required ) {
	$check( isset( $cases[ $required ] ), 'Falta el caso obligatorio ' . $required . ' en las fixtures.' );
}

// ── 0 · Arranque y guardas ───────────────────────────────────────────────────────────────────────────────────────
presets_boot();
$hooks_before = design_hooks();
$check( false === Presets::start( array( 'Elementor\No_Existe_En_El_Nucleo' ) ), 'Sin un símbolo del núcleo, los presets no arrancan.' );
$added = array();
foreach ( design_hooks() as $hook => $count ) {
	if ( $count !== ( $hooks_before[ $hook ] ?? 0 ) ) {
		$added[ $hook ] = $count - ( $hooks_before[ $hook ] ?? 0 );
	}
}
$check( array( 'elementor/editor/after_enqueue_scripts' => 1 ) === $added, 'Sin símbolos, sólo se añade el aviso del editor: ' . json_encode( $added ) );
$check( isset( Design::missing()['presets'] ), 'La guarda anota lo que falta.' );

design_wp_reset();
Presets::init();
Presets::init();
$hooks = design_hooks();
unset( $hooks['elementor/widgets/register'] );
foreach ( array( 'elementor/document/save/data', 'elementor/ajax/register_actions', 'elementor/element/before_parse_css', 'elementor/kit/register_tabs', Controls::HOOKS[0], Controls::HOOKS[1] ) as $hook ) {
	$check( 1 === ( $hooks[ $hook ] ?? 0 ), 'Presets\Module::init() registra ' . $hook . ' una sola vez: ' . json_encode( $hooks ) );
}
$check( Guard::PRIORITY === has_filter( 'elementor/document/save/data', array( Guard::class, 'on_save' ) ) && Guard::PRIORITY > 20, 'La guarda corre después del remapeo de los kits (prioridad 20).' );
foreach ( array_keys( $hooks ) as $hook ) {
	$check( false === strpos( $hook, 'common-optimized' ), 'Nunca se engancha la pila common-optimized (V-I1): ' . $hook );
}
$check( isset( $hooks['digitalisimo_elements_kit_parts'] ) || ! class_exists( 'Digitalisimo\Elements\Design\Presets\Editor' ), 'Con el editor del frente D, también se registran los kits.' );

// ── 1 · Saneado: qué es estilo (V.2.2) ───────────────────────────────────────────────────────────────────────────
foreach ( array( false, true ) as $front ) {
	presets_boot( array( 'admin' => ! $front ) );
	$check( $front === \Elementor\Core\Frontend\Performance::should_optimize_controls(), 'El contexto de las pilas es el pedido.' );
	foreach ( $cases as $case ) {
		$clean = Store::sanitize_settings( $case['type'], $case['preset'], $dropped );
		$check( presets_json( $case['preset'] ) === presets_json( $clean ) && array() === $dropped, ( $front ? '[front] ' : '[admin] ' ) . 'El caso ' . $case['name'] . ' conserva todo su estilo (cierre por dependencias): ' . json_encode( $clean ) . ' sin ' . json_encode( $dropped ) );
	}
	$check( false === \Elementor\Core\Frontend\Performance::is_use_style_controls(), 'El saneado restaura set_use_style_controls().' );
}

presets_boot( array( 'admin' => true ) );
$heading  = design_element( array( 'id' => 'hx', 'elType' => 'widget', 'widgetType' => 'heading' ) );
$controls = Css::with_style_controls(
	function () use ( $heading ) {
		return $heading->get_controls();
	}
);
$allowed = Store::allowed( 'heading', $controls );
$expect  = array(
	'typography_typography'       => 'closure',
	'typography_font_size'        => 'seed',
	'_box_shadow_box_shadow_type' => 'closure',
	'_offset_orientation_h'       => 'closure',
	'title_color'                 => 'seed',
	'_grid_column_custom'         => 'seed',
);
foreach ( $expect as $name => $origin ) {
	$check( $origin === ( $allowed[ $name ] ?? null ), 'Control admitido en un preset de encabezado: ' . $name . ' (' . $origin . '), hay ' . var_export( $allowed[ $name ] ?? null, true ) );
}
foreach ( array( 'title', 'link', 'header_size', '_position', '_animation', '_css_classes', '_element_id', 'custom_css', 'digitalisimo_preset', '_background_image' ) as $name ) {
	$check( ! isset( $allowed[ $name ] ), 'Control que nunca entra en un preset: ' . $name );
}
$container = design_element( array( 'id' => 'cx', 'elType' => 'container' ) );
$allowed_c = Store::allowed(
	'container',
	Css::with_style_controls(
		function () use ( $container ) {
			return $container->get_controls();
		}
	)
);
$check( 'closure' === ( $allowed_c['background_background'] ?? null ) && isset( $allowed_c['background_color_b'], $allowed_c['background_color_b_stop'], $allowed_c['background_gradient_type'] ) && ! isset( $allowed_c['background_image'], $allowed_c['background_video_link'], $allowed_c['background_slideshow_gallery'] ), 'Contenedor: el tipo de fondo y los auxiliares del degradado entran; imagen, vídeo y galería no.' );

// Contenido, tipos excluidos y valores fuera de la lista blanca.
$dirty = array(
	'title'                  => 'Hola',
	'link'                   => array( 'url' => 'https://x.test/' ),
	'header_size'            => 'h3',
	'_css_classes'           => 'mia',
	'_element_id'            => 'ancla',
	'custom_css'             => 'selector{color:red}',
	'_position'              => 'absolute',
	'_animation'             => 'fadeIn',
	'__dynamic__'            => array( 'title_color' => '[elementor-tag id="1" name="x"]' ),
	'title_color'            => 'red',
	'typography_typography'  => 'custom',
	'typography_font_weight' => '950',
	'typography_font_size'   => array( 'unit' => 'pt', 'size' => 12 ),
	'typography_font_family' => 'Fuente Inventada',
	'typography_line_height' => array( 'unit' => 'em', 'size' => 1.4 ),
	'_box_shadow_box_shadow' => array( 'horizontal' => 1, 'vertical' => 1, 'blur' => 1, 'spread' => 0, 'color' => 'url(x)' ),
	'_box_shadow_box_shadow_type' => 'si',
	'_grid_column_custom'    => '1 / span 2',
	'__globals__'            => array(
		'title_color'            => 'globals/colors?id=a;b',
		'typography_typography'  => 'https://x.test/',
		'_background_color'      => 'globals/colors?id=primary',
	),
);
$clean = Store::sanitize_settings( 'heading', $dirty, $dropped );
$check( array( 'typography_typography' => 'custom', 'typography_line_height' => array( 'unit' => 'em', 'size' => 1.4, 'sizes' => array() ), '_grid_column_custom' => '1 / span 2', '__globals__' => array( '_background_color' => 'globals/colors?id=primary' ) ) === $clean, 'Sólo queda el estilo válido: ' . json_encode( $clean ) );
foreach ( array( 'title', 'link', 'header_size', '_css_classes', '_element_id', 'custom_css', '_position', '_animation', '__dynamic__', 'title_color', 'typography_font_weight', 'typography_font_size', 'typography_font_family', '_box_shadow_box_shadow' ) as $key ) {
	$check( in_array( $key, $dropped, true ), 'La clave descartada se anuncia como local: ' . $key . ' en ' . json_encode( $dropped ) );
}
$check( array( 'typography_font_family' => 'Roboto' ) === Store::sanitize_settings( 'heading', array( 'typography_font_family' => 'Roboto' ) ), 'Una familia de la lista de fuentes del núcleo se conserva.' );
$check( array( 'typography_typography' => 'custom' ) === Store::sanitize_settings( 'heading', array( 'typography_typography' => 'custom', 'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 'x' ) ) ), 'Una variante responsiva inválida se descarta sola.' );

// El tipo de fondo vídeo o pase de diapositivas es contenido; los medios y la galería, también.
$background = Store::sanitize_settings( 'container', array( 'background_background' => 'video', 'background_video_link' => 'https://x.test/v.mp4', 'background_image' => array( 'url' => 'https://x.test/a.png', 'id' => 3 ), 'background_slideshow_gallery' => array( array( 'id' => 3 ) ), 'background_color' => '#123' ), $dropped );
$check( array( 'background_color' => '#123' ) === $background && in_array( 'background_background', $dropped, true ), 'Fondo de vídeo, imagen y galería fuera: ' . json_encode( $background ) );

// Repetidores (Lista de iconos): el repetidor entero fuera; el estilo de la lista, dentro.
$list = Store::sanitize_settings( 'icon-list', array( 'icon_list' => array( array( '_id' => 'a', 'text' => 'Uno', 'icon_color' => '#f00' ) ), 'icon_color' => '#00ff00', 'space_between' => array( 'unit' => 'px', 'size' => 8 ) ), $dropped );
$check( array( 'icon_color' => '#00ff00', 'space_between' => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ) ) === $list && in_array( 'icon_list', $dropped, true ), 'Un repetidor nunca entra en un preset: ' . json_encode( $list ) );

// Texto libre con selectores: lista blanca y comprobación final (R-I11).
foreach ( array( '\75 rl(//x.test/a.png)', '//x.test/a.png', '1 /* x', 'a@b', '"x"', "'x'", 'a;b', '{x}' ) as $hostile ) {
	$check( array() === Store::sanitize_settings( 'heading', array( '_grid_column_custom' => $hostile ) ), 'Texto con selectores rechazado: ' . $hostile );
}
foreach ( array( 'url(x.png)', 'image-set(x.png 1x)', 'cross-fade(a, b)', 'image(x)', 'expression(alert(1))' ) as $hostile ) {
	$result = Store::sanitize_settings( 'heading', array( '_grid_column_custom' => $hostile, 'title_color' => '#fff' ), $dropped );
	$check( array() === $result && array( '*' ) === $dropped, 'La comprobación final descarta el preset entero: ' . $hostile );
}
foreach ( array( '\75 rl(', '//x', 'image-set(', '@import', '{', '}', '<b>', 'expression(', 'cross-fade(', 'image(' ) as $hostile ) {
	$check( ! Store::safe( array( 'k' => array( 'size' => $hostile ) ) ) && ! Store::safe( array( $hostile => 1 ) ), 'safe() rechaza ' . $hostile . ' en valores y claves.' );
}
$check( Store::safe( array( 'a' => array( 'unit' => 'px', 'size' => 1 ), '__globals__' => array( 'b' => 'globals/colors?id=primary' ) ) ) && ! Store::safe( array( 'a' => new stdClass() ) ), 'safe() acepta ajustes normales y rechaza objetos.' );

// Tipos que no admiten presets (D1).
foreach ( array( 'section', 'column', 'common', 'common-optimized', 'no-existe', 'Mal Tipo' ) as $type ) {
	$check( array() === Store::sanitize_settings( $type, array( 'title_color' => '#fff' ), $dropped ) && array( '*' ) === $dropped && ! Store::supports_type( $type ), 'El tipo ' . $type . ' no admite presets.' );
}
$check( Store::supports_type( 'heading' ) && Store::supports_type( 'container' ) && Store::supports_type( 'icon-list' ), 'Widgets v3 y contenedores admiten presets.' );

// Pilas optimizadas (front y WP-CLI): el núcleo quita `options` y `size_units`; se valida la forma (V-I4).
presets_boot();
$check( \Elementor\Core\Frontend\Performance::should_optimize_controls() && ! \Elementor\Core\Frontend\Performance::is_use_style_controls(), 'El front optimiza las pilas y aparta los controles de estilo.' );
$front = Store::sanitize_settings( 'heading', array( 'typography_typography' => 'custom', 'typography_font_weight' => '700', 'typography_text_transform' => 'x;y', 'typography_font_size' => array( 'unit' => 'pt', 'size' => 2 ), 'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.1 ) ) );
$check( array( 'typography_typography' => 'custom', 'typography_font_weight' => '700', 'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.1, 'sizes' => array() ) ) === $front, 'En una pila optimizada, opciones y unidades se validan por su forma: ' . json_encode( $front ) );
$check( false === \Elementor\Core\Frontend\Performance::is_use_style_controls(), 'Y se restaura set_use_style_controls().' );

// ── 2 · Almacén ──────────────────────────────────────────────────────────────────────────────────────────────────
presets_boot( array( 'admin' => true ) );
$check( array() === Store::all() && ! isset( $GLOBALS['dh']['options'][ Store::OPTION ] ), 'Sin presets no hay opción.' );
$kit_hash = Store::hash( $cases['heading-typography']['preset'] );
Store::replace(
	array(
		'v'       => 1,
		'presets' => array(
			'titulo-grande' => array( 'type' => 'heading', 'label' => 'Título grande', 'settings' => $cases['heading-typography']['preset'], 'hash' => $kit_hash, 'updated' => 1792000000 ),
			'con-texto'     => array( 'type' => 'heading', 'label' => str_repeat( 'á', 80 ), 'settings' => array( 'title' => 'x', 'title_color' => '#fff' ), 'hash' => str_repeat( 'a', 40 ), 'updated' => 1792000001 ),
			'fondo-oscuro'  => array( 'type' => 'container', 'label' => '', 'settings' => $cases['container-background-classic']['preset'] ),
			'precio'        => array( 'type' => 'woocommerce-product-price', 'label' => 'Precio', 'settings' => array( 'price_color' => '#111', 'typography_typography' => 'custom', '__globals__' => array( 'price_color' => 'globals/colors?id=accent' ) ) ),
			'precio-malo'   => array( 'type' => 'woocommerce-product-price', 'label' => 'Malo', 'settings' => array( 'price_color' => 'url(x)' ) ),
			'precio-hondo'  => array( 'type' => 'woocommerce-product-price', 'label' => 'Hondo', 'settings' => array( 'a' => array( 'b' => array( 'c' => array( 'd' => array( 'e' => array( 'f' => 1 ) ) ) ) ) ) ),
			'Tarjeta'       => array( 'type' => 'heading', 'label' => 'x', 'settings' => array() ),
			'a--b'          => array( 'type' => 'heading', 'label' => 'x', 'settings' => array() ),
			'a-b-c-d-e-f-g-h-i' => array( 'type' => 'heading', 'label' => 'x', 'settings' => array() ),
			'seccion'       => array( 'type' => 'section', 'label' => 'x', 'settings' => array() ),
			'solo-contenido' => array( 'type' => 'heading', 'label' => 'x', 'settings' => array( 'title' => 'Hola' ) ),
		),
	)
);
$row = $GLOBALS['dh']['options'][ Store::OPTION ] ?? null;
$check( is_array( $row ) && 'off' === $row['autoload'] && 1 === $row['value']['v'], 'La opción de presets no se carga automáticamente (R-I12): ' . json_encode( $row['autoload'] ?? null ) );
$all = Store::all();
$check( array( 'titulo-grande', 'con-texto', 'fondo-oscuro', 'precio' ) === array_keys( $all ), 'replace() conserva los válidos y los inertes: ' . json_encode( array_keys( $all ) ) );
$dropped = array();
foreach ( Store::dropped() as $entry ) {
	$dropped[ $entry[0] ] = $entry[1];
}
$check( array( 'precio-malo' => 'rejected', 'precio-hondo' => 'rejected', 'Tarjeta' => 'invalid', 'a--b' => 'invalid', 'a-b-c-d-e-f-g-h-i' => 'invalid', 'seccion' => 'invalid_type', 'solo-contenido' => 'rejected' ) === $dropped, 'replace() anota cada descarte: ' . json_encode( $dropped ) );
$check( $kit_hash === $all['titulo-grande']['hash'] && 1792000000 === $all['titulo-grande']['updated'] && 'Título grande' === $all['titulo-grande']['label'] && false === $all['titulo-grande']['inert'], 'Se conservan hash y updated del kit si el saneado no cambia nada.' );
$check( array( 'title_color' => '#fff' ) === $all['con-texto']['settings'] && Store::hash( array( 'title_color' => '#fff' ) ) === $all['con-texto']['hash'] && 1792000001 === $all['con-texto']['updated'], 'Si el saneado cambia los ajustes, la huella se recalcula.' );
$check( 60 === mb_strlen( $all['con-texto']['label'] ) && 'fondo-oscuro' === $all['fondo-oscuro']['label'], 'El nombre se acorta a 60 caracteres y, vacío, toma el slug.' );
$check( true === $all['precio']['inert'] && array( 'price_color' => '#111', 'typography_typography' => 'custom', '__globals__' => array( 'price_color' => 'globals/colors?id=accent' ) ) === $all['precio']['settings'], 'Un preset de un tipo ausente se guarda tal cual y queda inerte.' );
$check( ! isset( Store::options()['precio'] ) && ! isset( Store::types()['precio'] ) && array() === Store::for_type( 'woocommerce-product-price' ) && null === Compiler::validated( 'precio' ), 'Un preset inerte no se ofrece ni se aplica.' );
$check( array( 'titulo-grande' => 'Título grande', 'con-texto' => str_repeat( 'á', 60 ) ) === Store::for_type( 'heading' ) && 'container' === Store::types()['fondo-oscuro'], 'for_type() (por nombre) y types() por tipo: ' . json_encode( Store::for_type( 'heading' ) ) );
$check( Store::valid_slug( 'a-b-c-d-e-f-g-h' ) && ! Store::valid_slug( 'a-b-c-d-e-f-g-h-i' ) && ! Store::valid_slug( str_repeat( 'a', 41 ) ) && Store::valid_slug( str_repeat( 'a', 40 ) ) && ! Store::valid_slug( '-a' ) && ! Store::valid_slug( 'á' ), 'Slug de D6: hasta 8 grupos y 40 caracteres.' );

// Límites por filtro.
add_filter(
	'digitalisimo_elements_preset_limits',
	function ( $limits ) {
		return array( 'count' => 2 ) + $limits;
	}
);
Store::replace( array( 'v' => 1, 'presets' => $all ) );
$check( 2 === count( Store::all() ) && array( 'fondo-oscuro', 'limit' ) === Store::dropped()[0], 'El tope de presets descarta los de más: ' . json_encode( Store::dropped() ) );
$GLOBALS['dh']['hooks']['digitalisimo_elements_preset_limits'] = array();
$one = strlen( json_encode( array( 'v' => 1, 'presets' => array( 'titulo-grande' => $all['titulo-grande'] ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
add_filter(
	'digitalisimo_elements_preset_limits',
	function ( $limits ) use ( $one ) {
		return array( 'total_bytes' => $one + 5 ) + $limits;
	}
);
Store::replace( array( 'v' => 1, 'presets' => $all ) );
$check( array( 'titulo-grande' ) === array_keys( Store::all() ) && 'total' === Store::dropped()[0][1], 'El tope total (128 KB por defecto) descarta lo que no cabe: ' . json_encode( Store::dropped() ) );
$GLOBALS['dh']['hooks']['digitalisimo_elements_preset_limits'] = array();
add_filter(
	'digitalisimo_elements_preset_limits',
	function ( $limits ) {
		return array( 'settings_bytes' => 40 ) + $limits;
	}
);
Store::replace( array( 'v' => 1, 'presets' => $all ) );
$check( array( 'con-texto' ) === array_keys( Store::all() ) && array( array( 'titulo-grande', 'too_large' ), array( 'fondo-oscuro', 'too_large' ), array( 'precio', 'too_large' ) ) === Store::dropped(), 'El tope por preset (16 KB por defecto) descarta los grandes: ' . json_encode( Store::dropped() ) );
$GLOBALS['dh']['hooks']['digitalisimo_elements_preset_limits'] = array();
$check( 200 === Store::limits()['count'] && 16384 === Store::limits()['settings_bytes'] && 131072 === Store::limits()['total_bytes'] && 60 === Store::limits()['label'], 'Límites por defecto de V.2.1.' );
Store::replace( array( 'v' => 1, 'presets' => array() ) );
$check( ! isset( $GLOBALS['dh']['options'][ Store::OPTION ] ) && array() === Store::all(), 'Sin presets, la opción se borra.' );

// `$keep`: los presets del sitio se escriben tal cual aunque hoy no superen el saneado (importar en «agregar» y revertir).
$site_preset = array( 'type' => 'heading', 'label' => 'Viejo', 'settings' => array( 'control_que_ya_no_existe' => '#ffffff', 'title' => 'x' ), 'hash' => str_repeat( 'c', 40 ), 'updated' => 1 );
$incoming    = array(
	'viejo'      => $site_preset,
	'del-kit'    => $site_preset,
	'hostil'     => array( 'type' => 'heading', 'label' => 'Hostil', 'settings' => array( 'a' => 'url(x)' ) ),
	'nuevo'      => array( 'type' => 'heading', 'label' => 'Nuevo', 'settings' => array( 'title_color' => '#123' ) ),
);
Store::replace( array( 'v' => 1, 'presets' => $incoming ), array( 'viejo', 'hostil', 'no-esta' ) );
$after_keep = Store::all();
$check( array( 'viejo', 'nuevo' ) === array_keys( $after_keep ), 'Con $keep, el preset del sitio no saneable se conserva y el igual sin conservar se descarta: ' . json_encode( array_keys( $after_keep ) ) );
$check( $site_preset['settings'] === $after_keep['viejo']['settings'] && str_repeat( 'c', 40 ) === $after_keep['viejo']['hash'] && 1 === $after_keep['viejo']['updated'] && false === $after_keep['viejo']['inert'], 'Lo conservado queda tal cual, con su huella y su fecha.' );
$check( array( array( 'del-kit', 'rejected' ), array( 'hostil', 'rejected' ) ) === Store::dropped(), 'Lo conservado también pasa la comprobación final: ' . json_encode( Store::dropped() ) );
$check( null === Compiler::validated( 'viejo' ), 'Un preset conservado sin sanear nunca se aplica sin pasar antes el saneado (V-I3).' );
$check( 2 === ( new ReflectionMethod( Store::class, 'replace' ) )->getNumberOfParameters(), 'replace() admite la lista de conservados (la detecta la parte de kit).' );
Store::replace( array( 'v' => 1, 'presets' => array() ) );

// save(), rename() y delete().
presets_boot( array( 'admin' => true ) );
$saved = Store::save( 'tarjeta', 'heading', 'Tarjeta', $cases['heading-typography']['preset'] + array( 'title' => 'Hola' ) );
$check( is_array( $saved ) && 'tarjeta' === $saved['slug'] && array( 'title' ) === $saved['local'] && presets_json( $cases['heading-typography']['preset'] ) === presets_json( $saved['settings'] ) && false === $saved['inert'] && Store::hash( $saved['settings'] ) === $saved['hash'] && $saved['updated'] > 0, 'save() crea el preset con su huella y anuncia lo que queda local: ' . json_encode( $saved ) );
$saved = Store::save( 'tarjeta', 'heading', '', array( 'title_color' => '#123456' ) );
$check( is_array( $saved ) && 'Tarjeta' === $saved['label'] && array( 'title_color' => '#123456' ) === $saved['settings'], 'save() actualiza y conserva el nombre si no se da otro.' );
$errors = array(
	'type_mismatch' => Store::save( 'tarjeta', 'divider', 'x', array( 'color' => '#fff' ) ),
	'invalid_slug'  => Store::save( 'Tarjeta Azul', 'heading', 'x', array( 'title_color' => '#fff' ) ),
	'invalid_type'  => Store::save( 'seccion', 'section', 'x', array( 'title_color' => '#fff' ) ),
	'empty'         => Store::save( 'vacio', 'heading', 'x', array( 'title' => 'Hola', 'link' => array( 'url' => 'https://x.test/' ) ) ),
	'unsafe'        => Store::save( 'malo', 'heading', 'x', array( '_grid_column_custom' => 'url(x)' ) ),
);
foreach ( $errors as $code => $error ) {
	$check( is_wp_error( $error ) && $code === $error->get_error_code() && '' !== $error->get_error_message(), 'save() rechaza con ' . $code . ': ' . ( is_wp_error( $error ) ? $error->get_error_code() : json_encode( $error ) ) );
}
Store::replace( array( 'v' => 1, 'presets' => Store::all() + array( 'precio' => array( 'type' => 'woocommerce-product-price', 'label' => 'Precio', 'settings' => array( 'price_color' => '#111' ) ) ) ) );
$check( 'inert' === Store::save( 'precio', 'heading', 'x', array( 'title_color' => '#fff' ) )->get_error_code() || 'type_mismatch' === Store::save( 'precio', 'heading', 'x', array( 'title_color' => '#fff' ) )->get_error_code(), 'Un preset inerte no se edita.' );
add_filter(
	'digitalisimo_elements_preset_limits',
	function ( $limits ) {
		return array( 'count' => 2 ) + $limits;
	}
);
$check( 'limit' === Store::save( 'otro', 'heading', 'x', array( 'title_color' => '#fff' ) )->get_error_code(), 'save() respeta el tope de presets.' );
$GLOBALS['dh']['hooks']['digitalisimo_elements_preset_limits'] = array();
add_filter(
	'digitalisimo_elements_preset_limits',
	function ( $limits ) {
		return array( 'settings_bytes' => 10 ) + $limits;
	}
);
$check( 'too_large' === Store::save( 'tarjeta', 'heading', 'x', array( 'title_color' => '#fff' ) )->get_error_code(), 'save() respeta el tope por preset.' );
$GLOBALS['dh']['hooks']['digitalisimo_elements_preset_limits'] = array();

$check( true === Store::rename( 'tarjeta', '  Tarjeta <b>azul</b> ' ) && 'Tarjeta azul' === Store::get( 'tarjeta' )['label'], 'rename() cambia sólo el nombre, saneado.' );
$check( 'not_found' === Store::rename( 'no-existe', 'x' )->get_error_code() && 'invalid_label' === Store::rename( 'tarjeta', ' <b></b> ' )->get_error_code(), 'rename() sin preset o sin nombre falla.' );

$check( 'tarjeta-azul' === Store::unique_slug( 'Tarjeta azul' ) && 'tarjeta' !== Store::unique_slug( 'Tarjeta' ) && 'tarjeta-2' === Store::unique_slug( 'Tarjeta' ) && 'preset' === Store::unique_slug( '¡¿!' ), 'unique_slug() crea un slug libre: ' . Store::unique_slug( 'Tarjeta' ) );
foreach ( array( 'Título con acentos y eñes', str_repeat( 'palabra ', 20 ), 'a b c d e f g h i j' ) as $label ) {
	$slug = Store::unique_slug( $label );
	$check( Store::valid_slug( $slug ) && Store::valid_slug( $slug . '-999' ), 'unique_slug() siempre cumple D6, también con sufijo: ' . $slug );
}

// Un solo vaciado por petición; renombrar no vacía.
$files = \Elementor\Plugin::$instance->files_manager;
Store::flush();
$cleared = $files->cleared;
Store::save( 'tarjeta', 'heading', '', array( 'title_color' => '#000' ) );
Store::save( 'otra', 'divider', 'Otra', array( 'color' => '#000' ) );
Store::delete( 'otra' );
$check( $cleared === $files->cleared && false !== has_action( 'shutdown', array( Store::class, 'flush' ) ), 'El vaciado espera al final de la petición.' );
Store::flush();
Store::flush();
$check( $cleared + 1 === $files->cleared, 'Varias acciones en una petición vacían la caché CSS una sola vez.' );
Store::rename( 'tarjeta', 'Otro nombre' );
Store::flush();
$check( $cleared + 1 === $files->cleared, 'Renombrar no vacía la caché CSS (el slug no cambia).' );
Store::replace( Store::data() );
Store::flush();
$check( $cleared + 1 === $files->cleared, 'Un replace() que no cambia nada no vacía la caché CSS.' );

// Cerrojo compartido (`INSERT IGNORE` con token, robo a los 30 s).
$GLOBALS['dh']['options'][ Store::LOCK ] = array( 'value' => time() . '|ajeno', 'autoload' => 'off' );
$busy = Store::save( 'tarjeta', 'heading', '', array( 'title_color' => '#111' ) );
$check( is_wp_error( $busy ) && 'busy' === $busy->get_error_code() && '#000' === Store::get( 'tarjeta' )['settings']['title_color'], 'Con el cerrojo tomado por otra petición, no se escribe.' );
$GLOBALS['dh']['options'][ Store::LOCK ] = array( 'value' => ( time() - 31 ) . '|ajeno', 'autoload' => 'off' );
$check( is_array( Store::save( 'tarjeta', 'heading', '', array( 'title_color' => '#111' ) ) ) && ! isset( $GLOBALS['dh']['options'][ Store::LOCK ] ) && 'digitalisimo_elements_lock_presets' === Store::LOCK && 0 === strpos( Store::LOCK, Lock::PREFIX ), 'Un cerrojo de más de 30 s se roba y se suelta al terminar.' );

// Usos (D8 y R-M8): ni revisiones, ni papelera, ni borradores automáticos; con los valores por defecto del kit.
$link = '[{"id":"a","elType":"widget","widgetType":"heading","settings":{"digitalisimo_preset":"tarjeta"}}]';
foreach ( array( 21 => array( 'page', 'publish' ), 22 => array( 'revision', 'inherit' ), 23 => array( 'page', 'trash' ), 24 => array( 'page', 'auto-draft' ), 25 => array( 'post', 'draft' ), 26 => array( 'page', 'publish' ), 27 => array( 'page', 'publish' ) ) as $id => $post ) {
	$GLOBALS['dh']['posts'][ $id ] = array( 'type' => $post[0], 'status' => $post[1], 'title' => 'Documento ' . $id );
	$GLOBALS['dh']['meta'][ $id ]['_elementor_data'] = $link;
}
$GLOBALS['dh']['meta'][26]['_elementor_data'] = str_replace( '"tarjeta"', '"tarjeta-2"', $link );
$GLOBALS['dh']['meta'][27]['_elementor_data'] = '[{"settings":{"editor":"<p>\"digitalisimo_preset\":\"tarjeta\"</p>"}}]';
$GLOBALS['dh']['meta'][5]['_elementor_elements_default_values'] = '{"heading":{"digitalisimo_preset":"tarjeta"}}';
$check( array( 21, 25, 5 ) === Store::uses( 'tarjeta' ), 'uses() cuenta publicados y borradores y los valores por defecto del kit: ' . json_encode( Store::uses( 'tarjeta' ) ) );
$query = end( $GLOBALS['wpdb']->log );
$check( false !== strpos( $query[0], 'meta_value LIKE' ) && '%"digitalisimo\_preset":"tarjeta"%' === $query[1][1] && array( 'revision', 'trash', 'auto-draft' ) === array_slice( $query[1], 2, 3 ) && 200 === $query[1][5], 'La consulta usa esc_like() y el tope de 200: ' . json_encode( $query ) );
$check( array( 21 ) === Store::uses( 'tarjeta', 1 ) && array() === Store::uses( 'no-existe' ), 'uses() respeta el tope pedido.' );
$in_use = Store::delete( 'tarjeta' );
$check( is_wp_error( $in_use ) && 'in_use' === $in_use->get_error_code() && array( 21, 25, 5 ) === $in_use->get_error_data()['uses'] && null !== Store::get( 'tarjeta' ), 'Borrar un preset en uso se bloquea con la lista (D8).' );
$check( 'not_found' === Store::delete( 'no-existe' )->get_error_code(), 'Borrar un preset que no existe falla.' );
unset( $GLOBALS['dh']['meta'][21], $GLOBALS['dh']['meta'][25], $GLOBALS['dh']['meta'][5] );
$check( true === Store::delete( 'tarjeta' ) && null === Store::get( 'tarjeta' ), 'Sin usos, el preset se borra.' );

// Permisos (D7).
$GLOBALS['dh']['users'][2] = array( 'roles' => array( 'editor' ), 'caps' => array( 'edit_posts', 'unfiltered_html' ) );
$GLOBALS['dh']['users'][3] = array( 'roles' => array( 'administrator', 'disenador' ), 'caps' => array( 'manage_options', 'edit_posts' ) );
$GLOBALS['dh']['options']['elementor_role-manager'] = array( 'value' => array( 'disenador' => array( 'design' ) ), 'autoload' => 'on' );
$check( Store::can_manage( 1 ) && ! Store::can_manage( 2 ) && ! Store::can_manage( 3 ) && ! Store::can_manage( 0 ) && ! Store::can_manage( 99 ), 'Gestiona presets quien tiene manage_options sin la restricción design.' );
$check( Store::design_restricted( 3 ) && ! Store::design_restricted( 1 ) && ! Store::design_restricted( 2 ), 'La restricción design se lee de cualquiera de los roles.' );

// ── 3 · Guard ────────────────────────────────────────────────────────────────────────────────────────────────────
presets_boot( array( 'admin' => true ) );
Store::replace(
	array(
		'presets' => array(
			'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta', 'settings' => array( 'title_color' => '#123' ) ),
			'fondo'   => array( 'type' => 'container', 'label' => 'Fondo', 'settings' => array( 'background_background' => 'classic', 'background_color' => '#000' ) ),
			'inerte'  => array( 'type' => 'woocommerce-product-price', 'label' => 'Inerte', 'settings' => array( 'price_color' => '#111' ) ),
		),
	)
);
$widget = function ( string $id, string $type, $link = null ) {
	$settings = null === $link ? array() : array( 'digitalisimo_preset' => $link );
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
};
$data = array(
	'elements' => array(
		array(
			'id'       => 'c1',
			'elType'   => 'container',
			'settings' => array( 'digitalisimo_preset' => 'fondo' ),
			'elements' => array(
				$widget( 'w1', 'heading', 'tarjeta' ),
				$widget( 'w2', 'heading', 'fondo' ),
				$widget( 'w3', 'heading', 'no-existe' ),
				$widget( 'w4', 'heading', 'Tarjeta!' ),
				$widget( 'w5', 'woocommerce-product-price', 'inerte' ),
				$widget( 'w6', 'divider', 'tarjeta' ),
				array( 'id' => 'm1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-unconverted', 'settings' => array( 'digitalisimo_preset' => 'no-existe' ), 'elements' => array() ),
			),
		),
	),
);
$saved = Guard::on_save( $data, new Design_Document( 30, array() ) );
$inner = $saved['elements'][0]['elements'];
$check( 'fondo' === $saved['elements'][0]['settings']['digitalisimo_preset'] && 'tarjeta' === $inner[0]['settings']['digitalisimo_preset'] && 'inerte' === $inner[4]['settings']['digitalisimo_preset'], 'Los vínculos válidos (también a un preset inerte del mismo tipo) se conservan.' );
foreach ( array( 1, 2, 3, 5 ) as $index ) {
	$check( ! isset( $inner[ $index ]['settings']['digitalisimo_preset'] ), 'Un vínculo de otro tipo, inexistente o mal formado se vacía: ' . $inner[ $index ]['id'] );
}
$check( 'no-existe' === $inner[6]['settings']['digitalisimo_preset'], 'Un marcador de widget desconocido no se toca.' );

$plain = array( 'elements' => array( $widget( 'w9', 'heading' ) ), 'settings' => array( 'a' => 1 ) );
Store::reset_cache();
$GLOBALS['dh']['queries'] = array();
$check( $plain === Guard::on_save( $plain, new Design_Document( 31, array() ) ) && 0 === presets_reads(), 'Sin vínculos en juego, la guarda no lee la opción ni cambia nada.' );

// Rol «sólo contenido»: restaura por ID lo guardado (D7).
$GLOBALS['dh']['users'][4] = array( 'roles' => array( 'disenador' ), 'caps' => array( 'manage_options', 'edit_posts' ) );
$GLOBALS['dh']['options']['elementor_role-manager'] = array( 'value' => array( 'disenador' => array( 'design' ) ), 'autoload' => 'on' );
$GLOBALS['dh']['user'] = 4;
$before = new Design_Document( 32, array( $widget( 'e1', 'heading', 'tarjeta' ), $widget( 'e2', 'heading' ), $widget( 'e4', 'heading', 'tarjeta' ) ) );
$after  = Guard::on_save( array( 'elements' => array( $widget( 'e1', 'heading', '' ), $widget( 'e2', 'heading', 'tarjeta' ), $widget( 'e3', 'heading', 'tarjeta' ), $widget( 'e4', 'heading' ) ) ), $before );
$links  = array_map(
	function ( $node ) {
		return $node['settings']['digitalisimo_preset'] ?? null;
	},
	$after['elements']
);
$check( array( 'tarjeta', null, null, 'tarjeta' ) === $links, 'Con la restricción design, el vínculo vuelve a lo guardado: ' . json_encode( $links ) );
$GLOBALS['dh']['user'] = 2;
$free = Guard::on_save( array( 'elements' => array( $widget( 'e5', 'heading', 'tarjeta' ) ) ), new Design_Document( 33, array() ) );
$check( 'tarjeta' === $free['elements'][0]['settings']['digitalisimo_preset'], 'Un editor sin la restricción design sí vincula (D7).' );
$GLOBALS['dh']['user'] = 1;

// ── 4 · Acciones AJAX ────────────────────────────────────────────────────────────────────────────────────────────
presets_boot( array( 'admin' => true ) );
$ajax = new class() {
	public $actions = array();
	public function register_ajax_action( $tag, $callback ) {
		$this->actions[ $tag ] = $callback;
	}
};
Ajax::register( $ajax );
$check( array( 'digitalisimo_preset_save', 'digitalisimo_preset_rename', 'digitalisimo_preset_delete', 'digitalisimo_preset_uses' ) === array_keys( $ajax->actions ), 'Se registran las cuatro acciones del editor.' );
Store::replace( array( 'presets' => array( 'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta', 'settings' => array( 'title_color' => '#123' ) ) ) ) );
foreach ( array( 2, 3 ) as $user ) {
	$GLOBALS['dh']['users'][3]                         = array( 'roles' => array( 'administrator', 'disenador' ), 'caps' => array( 'manage_options', 'edit_posts' ) );
	$GLOBALS['dh']['users'][2]                         = array( 'roles' => array( 'editor' ), 'caps' => array( 'edit_posts', 'unfiltered_html' ) );
	$GLOBALS['dh']['options']['elementor_role-manager'] = array( 'value' => array( 'disenador' => array( 'design' ) ), 'autoload' => 'on' );
	$GLOBALS['dh']['user']                             = $user;
	foreach ( $ajax->actions as $tag => $callback ) {
		$error = presets_ajax_error( $callback, array( 'slug' => 'tarjeta', 'type' => 'heading', 'label' => 'x', 'settings' => array( 'title_color' => '#fff' ) ) );
		$check( null !== $error && 403 === $error->getCode(), 'Sin permiso, ' . $tag . ' responde 403 (usuario ' . $user . ').' );
	}
}
$check( '#123' === Store::get( 'tarjeta' )['settings']['title_color'] && 'Tarjeta' === Store::get( 'tarjeta' )['label'], 'Sin permiso, nada cambia.' );
$GLOBALS['dh']['user'] = 1;

$response = Ajax::save( array( 'slug' => '', 'type' => 'heading', 'label' => 'Tarjeta azul', 'settings' => $cases['heading-typography']['preset'] + array( 'title' => 'Hola' ) ) );
$check( true === $response['ok'] && 'tarjeta-azul' === $response['slug'] && array( 'title' ) === $response['local'] && isset( $response['presets']['tarjeta-azul'], $response['options']['tarjeta-azul'], $response['types']['tarjeta-azul'] ) && 'heading' === $response['types']['tarjeta-azul'] && $response['total'] > 0, 'Guardar sin slug crea uno desde el nombre y responde con los mapas: ' . json_encode( array_keys( $response ) ) );
$entry = presets_json( $response['presets']['tarjeta-azul'] );
$check( 'heading' === $entry['type'] && 'Tarjeta azul' === $entry['label'] && false === $entry['error'] && presets_json( $cases['heading-typography']['preset'] ) === $entry['settings'], 'El mapa presets trae tipo, nombre, ajustes saneados y error: ' . json_encode( $entry ) );
if ( class_exists( 'Digitalisimo\Elements\Design\Presets\Editor' ) ) {
	$check( presets_json( \Digitalisimo\Elements\Design\Presets\Editor::config()['presets'] ) === presets_json( $response['presets'] ), 'El mapa presets es el de la configuración del editor.' );
}
$check( 'tarjeta-azul-2' === Ajax::save( array( 'slug' => '', 'type' => 'heading', 'label' => 'Tarjeta azul', 'settings' => array( 'title_color' => '#fff' ) ) )['slug'], 'Un segundo preset con el mismo nombre toma otro slug.' );
$codes = array(
	400 => presets_ajax_error( array( Ajax::class, 'save' ), array( 'slug' => 'x', 'type' => 'section', 'label' => 'x', 'settings' => array( 'title_color' => '#fff' ) ) ),
	404 => presets_ajax_error( array( Ajax::class, 'rename' ), array( 'slug' => 'no-existe', 'label' => 'x' ) ),
	409 => presets_ajax_error( array( Ajax::class, 'save' ), array( 'slug' => 'tarjeta', 'type' => 'divider', 'label' => 'x', 'settings' => array( 'color' => '#fff' ) ) ),
);
foreach ( $codes as $code => $error ) {
	$check( null !== $error && $code === $error->getCode() && '' !== $error->getMessage(), 'Los errores llegan con su código ' . $code . '.' );
}
$renamed = Ajax::rename( array( 'slug' => 'tarjeta', 'label' => 'Tarjeta nueva' ) );
$check( 'Tarjeta nueva' === $renamed['options']['tarjeta'], 'rename responde con las opciones al día.' );
$GLOBALS['dh']['posts'][40] = array( 'type' => 'page', 'status' => 'publish', 'title' => 'Inicio' );
$GLOBALS['dh']['meta'][40]['_elementor_data'] = '[{"settings":{"digitalisimo_preset":"tarjeta"}}]';
$uses = Ajax::uses( array( 'slug' => 'tarjeta' ) );
$check( array( array( 'id' => 40, 'title' => 'Inicio', 'edit' => 'https://sitio.test/wp-admin/post.php?post=40&action=elementor' ) ) === $uses['uses'] && 1 === $uses['count'] && false === $uses['more'], 'uses responde con los documentos: ' . json_encode( $uses['uses'] ) );
$error = presets_ajax_error( array( Ajax::class, 'delete' ), array( 'slug' => 'tarjeta' ) );
$check( null !== $error && 409 === $error->getCode() && false !== strpos( $error->getMessage(), 'Inicio' ) && null !== Store::get( 'tarjeta' ), 'Borrar con usos responde 409 y nombra los documentos.' );
$deleted = Ajax::delete( array( 'slug' => 'tarjeta-azul-2' ) );
$check( ! isset( $deleted['options']['tarjeta-azul-2'] ) && ! isset( presets_json( $deleted['presets'] )['tarjeta-azul-2'] ), 'Borrar responde sin el preset.' );

// ── 5 · Control del vínculo ──────────────────────────────────────────────────────────────────────────────────────
$seed = function () {
	Store::replace(
		array(
			'presets' => array(
				'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta', 'settings' => array( 'title_color' => '#123' ) ),
				'fondo'   => array( 'type' => 'container', 'label' => 'Fondo', 'settings' => array( 'background_background' => 'classic', 'background_color' => '#000' ) ),
			),
		)
	);
};
foreach ( array( false, true ) as $optimized ) {
	presets_boot( array( 'admin' => true, 'optimized' => $optimized ) );
	$seed();
	design_boot( array( 'admin' => true, 'optimized' => $optimized ) ); // Pilas nuevas: las opciones se leen al construirlas.
	Store::reset_cache();
	$element  = design_element( array( 'id' => 'hc', 'elType' => 'widget', 'widgetType' => 'heading' ) );
	$controls = $element->get_controls();
	$names    = array_keys( $controls );
	$control  = $controls[ Controls::CONTROL ] ?? array();
	$label    = $optimized ? '[optimizado] ' : '[clásico] ';
	$check( 1 === count( array_keys( $names, Controls::CONTROL, true ) ) && 'select' === ( $control['type'] ?? '' ) && empty( $control['prefix_class'] ) && 'ui' === $control['render_type'] && true === $control['style_transfer'] && empty( $control['frontend_available'] ) && '' === $control['default'] && 'advanced' === $control['tab'], $label . 'El control va una vez, como SELECT sin prefix_class: ' . json_encode( $control ) );
	$check( array_search( Controls::SECTION, $names, true ) < array_search( '_section_style', $names, true ), $label . 'Al principio de la pestaña Avanzado.' );
	$check( array( '' => 'Sin preset', 'fondo' => 'Fondo', 'tarjeta' => 'Tarjeta' ) === $control['options'], $label . 'En el editor, la lista completa (el script la filtra por tipo): ' . json_encode( $control['options'] ) );
	$common = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $optimized ? 'common-optimized' : 'common' )->get_controls();
	$check( isset( $common[ Controls::CONTROL ] ), $label . 'La pila ' . ( $optimized ? 'common-optimized' : 'common' ) . ' tiene el control.' );
	foreach ( $GLOBALS['dh']['wrong'] as $wrong ) {
		$check( false === strpos( $wrong, 'redeclare' ), $label . 'Sin controles repetidos (V-I1): ' . $wrong );
	}
}
$container = design_element( array( 'id' => 'cc', 'elType' => 'container' ) )->get_controls();
$names     = array_keys( $container );
$check( 1 === count( array_keys( $names, Controls::CONTROL, true ) ) && 'advanced' === $container[ Controls::CONTROL ]['tab'] && array_search( Controls::SECTION, $names, true ) < array_search( 'section_layout', $names, true ), 'El contenedor tiene el control al principio de su pestaña Avanzado.' );

presets_boot();
$GLOBALS['dh']['options'][ Store::OPTION ] = array( 'value' => array( 'v' => 1, 'presets' => array( 'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta', 'settings' => array( 'title_color' => '#123' ) ) ) ), 'autoload' => 'off' );
$GLOBALS['dh']['queries'] = array();
$front = design_element( array( 'id' => 'hf', 'elType' => 'widget', 'widgetType' => 'heading' ) );
\Elementor\Core\Frontend\Performance::set_use_style_controls( true );
$front_control = $front->get_controls( Controls::CONTROL );
\Elementor\Core\Frontend\Performance::set_use_style_controls( false );
$check( is_array( $front_control ) && ! isset( $front_control['options'] ) && 0 === presets_reads(), 'En el front, el control no lleva opciones y no se lee la opción de presets.' );
$check( array( '' => 'Sin preset' ) === Controls::options(), 'Fuera del editor, las opciones son sólo «Sin preset».' );

// El vínculo no cambia el HTML: ni clases (sin prefix_class) ni data-settings (sin frontend_available).
$wrapper = function ( array $settings ) {
	$element = design_element( array( 'id' => 'hw', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => $settings ) );
	$method  = new ReflectionMethod( $element, 'add_render_attributes' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	$method->invoke( $element );
	return $element->get_render_attribute_string( '_wrapper' );
};
$plain_html  = $wrapper( array( 'title_color' => '#000', '_position' => 'absolute' ) );
$linked_html = $wrapper( array( 'title_color' => '#000', '_position' => 'absolute', Controls::CONTROL => 'tarjeta' ) );
$check( '' !== $plain_html && $plain_html === $linked_html && false === strpos( $linked_html, 'tarjeta' ), 'En el front, el vínculo no añade nada al HTML del elemento: ' . $linked_html );

// ── 6 · Aplanado en el CSS del documento ─────────────────────────────────────────────────────────────────────────
foreach ( array( true, false ) as $front ) {
	presets_boot( array( 'admin' => ! $front ) );
	foreach ( $cases as $case ) {
		$expected = design_presets_expected( $case );
		$check( presets_json( $case['settings'] ?? null ) === presets_json( $expected['settings'] ), ( $front ? '[front] ' : '[admin] ' ) . 'Ajustes saneados de ' . $case['name'] . ' como en las fixtures: ' . json_encode( $expected['settings'] ) );
		$check( presets_json( $case['fill'] ) === presets_json( $expected['fill'] ), ( $front ? '[front] ' : '[admin] ' ) . 'fill de ' . $case['name'] . ' como en las fixtures: ' . json_encode( $expected['fill'] ) );
		$check( presets_json( $case['css'] ) === presets_json( $expected['css'] ), ( $front ? '[front] ' : '[admin] ' ) . 'CSS aplanado de ' . $case['name'] . ' como en las fixtures: ' . json_encode( $expected['css'] ) );
	}
}
foreach ( array( 'heading-typography', 'heading-typography-global', 'container-background-classic', 'container-background-gradient', 'heading-box-shadow', 'divider-weight', 'heading-offset-rtl', 'heading-padding' ) as $name ) {
	$check( array() !== $cases[ $name ]['css'], 'Cada caso obligatorio de V.2.2 emite su regla: ' . $name );
}
$offset = $cases['heading-offset-rtl']['css'];
$check( isset( $offset['body:not(.rtl) .elementor-10 .elementor-element.elementor-element-h8b'], $offset['body.rtl .elementor-10 .elementor-element.elementor-element-h8b'] ), 'El aplanado usa los selectores del elemento, también los que empiezan antes de {{WRAPPER}} (R-I10).' );
$check( array( '--divider-color' => '#ff0000', '--divider-border-width' => '6px' ) === $cases['divider-weight']['css']['.elementor-10 .elementor-element.elementor-element-d7a'], 'El preset sustituye el valor por defecto local del divisor (V-B1).' );
$check( array( '.elementor-10 .elementor-element.elementor-element-h9c .elementor-heading-title' => array( 'font-weight' => '800' ) ) === $cases['heading-local-wins']['css'] && array() === $cases['heading-local-global-wins']['css'], 'Lo local gana en cualquier variante y también con un global (R-M10).' );

// Con marcado optimizado, los selectores cambian con el elemento.
presets_boot( array( 'optimized' => true ) );
$shadow = design_presets_expected( $cases['heading-box-shadow'] );
$check( array( '.elementor-10 .elementor-element.elementor-element-h5e' => array( 'box-shadow' => '0px 4px 12px 0px rgba(0,0,0,0.25)' ) ) === presets_json( $shadow['css'] ), 'Con marcado optimizado, la sombra va en {{WRAPPER}}: ' . json_encode( $shadow['css'] ) );

// Lo que no se aplica: otro tipo, inexistente, inerte o con error (V-I3).
presets_boot();
$GLOBALS['dh']['options'][ Store::OPTION ] = array(
	'value'    => array(
		'v'       => 1,
		'presets' => array(
			'divisor'  => array( 'type' => 'divider', 'label' => 'Divisor', 'settings' => array( 'color' => '#ff0000' ) ),
			'precio'   => array( 'type' => 'woocommerce-product-price', 'label' => 'Precio', 'settings' => array( 'title_color' => '#ff0000' ), 'inert' => true ),
			'roto'     => array( 'type' => 'heading', 'label' => 'Roto', 'settings' => array( 'title' => 'Hola', '_grid_column_custom' => 'url(x)' ) ),
			'heading-ok' => array( 'type' => 'heading', 'label' => 'Bueno', 'settings' => array( 'title_color' => '#ff0000' ) ),
		),
	),
	'autoload' => 'off',
);
$base = design_css( array( $widget( 'z1', 'heading' ) ) );
foreach ( array( 'divisor', 'precio', 'roto', 'no-existe' ) as $slug ) {
	$check( $base === design_css( array( $widget( 'z1', 'heading', $slug ) ) ), 'No se aplica el preset ' . $slug . '.' );
}
$check( null === Compiler::validated( 'roto' ) && null === Compiler::validated( 'precio' ) && array( 'title_color' => '#ff0000' ) === Compiler::validated( 'heading-ok' ), 'Un preset que no supera hoy el saneado no se aplica.' );
$check( $base !== design_css( array( $widget( 'z1', 'heading', 'heading-ok' ) ) ), 'Uno válido sí.' );

// Nada se guarda y el CSS dinámico no se toca.
$element = design_element( $widget( 'z2', 'heading', 'heading-ok' ) );
$before  = $element->get_data( 'settings' );
Compiler::flatten( new stdClass(), $element );
$check( $before === $element->get_data( 'settings' ) && '#ff0000' === $element->get_settings( 'title_color' ) && ! isset( $before['title_color'] ), 'El aplanado sólo cambia los ajustes en memoria, nunca los datos guardados.' );
$dynamic = ( new ReflectionClass( \Elementor\Core\DynamicTags\Dynamic_CSS::class ) )->newInstanceWithoutConstructor();
$other   = design_element( $widget( 'z3', 'heading', 'heading-ok' ) );
Compiler::flatten( $dynamic, $other );
$check( '#ff0000' !== $other->get_settings( 'title_color' ), 'En el CSS dinámico no se aplana nada.' );

// Sin vínculos: 0 B y 0 consultas.
presets_boot();
$GLOBALS['dh']['options'][ Store::OPTION ] = array( 'value' => array( 'v' => 1, 'presets' => array( 'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta', 'settings' => array( 'title_color' => '#ff0000' ) ) ) ), 'autoload' => 'off' );
$GLOBALS['dh']['queries'] = array();
$document = array( array( 'id' => 'k1', 'elType' => 'container', 'settings' => array( 'background_background' => 'classic', 'background_color' => '#fafafa' ), 'elements' => array( $widget( 'k2', 'heading' ), $widget( 'k3', 'divider' ) ) ) );
$with_presets = design_css( $document );
$check( 0 === presets_reads(), 'Un documento sin vínculos no lee la opción de presets.' );
unset( $GLOBALS['dh']['options'][ Store::OPTION ] );
Store::reset_cache();
$check( $with_presets === design_css( $document ), 'Un documento sin vínculos genera el mismo CSS con presets en el sitio y sin ellos.' );
remove_action( 'elementor/element/before_parse_css', array( Compiler::class, 'flatten' ), 10 );
$check( $with_presets === design_css( $document ), 'Y el mismo que sin el módulo.' );

// ── 7 · Pestaña «Presets de estilo» de Ajustes del sitio ─────────────────────────────────────────────────────────
presets_boot( array( 'admin' => true ) );
Store::replace(
	array(
		'presets' => array(
			'tarjeta' => array( 'type' => 'heading', 'label' => 'Tarjeta <b>azul</b> & "gris"', 'settings' => array( 'title_color' => '#123' ) ),
			'precio'  => array( 'type' => 'woocommerce-product-price', 'label' => 'Precio', 'settings' => array( 'price_color' => '#111' ) ),
		),
	)
);
$GLOBALS['dh']['options'][ Store::OPTION ]['value']['presets']['roto'] = array( 'type' => 'heading', 'label' => 'Roto', 'settings' => array( 'title' => 'Hola' ) );
Store::reset_cache();
$table = Kit_Tab::table();
$check( 3 === substr_count( $table, 'data-digi-preset-row=' ) && 2 === substr_count( $table, 'data-digi-preset-action="rename"' ) && 2 === substr_count( $table, 'data-digi-preset-action="delete"' ) && false !== strpos( $table, 'data-digi-preset="tarjeta"' ) && false !== strpos( $table, 'data-digi-preset-label' ), 'La pestaña lista cada preset con sus acciones para el script del editor.' );
$check( false === strpos( $table, 'data-digi-preset="precio"' ), 'Un preset inerte no tiene acciones: ni se edita ni se borra desde la pestaña.' );
$check( false !== strpos( $table, 'tipo no disponible' ) && false !== strpos( $table, 'con error' ) && false !== strpos( $table, 'Tarjeta azul &amp; &quot;gris&quot;' ) && false === strpos( $table, '<b>' ), 'Inertes y con error se marcan, y el nombre va saneado y escapado: ' . $table );
$check( false !== strpos( $table, '3 de 200 presets' ) && false === stripos( $table, 'elementor' . ' ' ) && false === strpos( $table, 'style="color' ), 'El total frente al tope y sin colores propios.' );
$kit = new class() {
	public $tabs = array();
	public function register_tab( $id, $class ) {
		$this->tabs[ $id ] = $class;
	}
};
Presets::register_kit_tab( $kit );
$check( array( Kit_Tab::ID => Kit_Tab::class ) === $kit->tabs && 'settings-digitalisimo-presets' === Kit_Tab::ID, 'La pestaña se registra en el kit.' );
$parent = new class() {
	public $controls = array();
	public function start_controls_section( $id, $args = array() ) {}
	public function end_controls_section() {}
	public function add_control( $id, $args, $options = array() ) {
		$this->controls[ $id ] = $args;
	}
};
$tab = new Kit_Tab( $parent );
$tab->register_controls();
$check( 'global' === $tab->get_group() && 'Presets de estilo' === $tab->get_title() && false !== strpos( $parent->controls['digitalisimo_presets_list']['raw'], 'data-digi-preset-row' ), 'En el editor, la pestaña pinta la tabla.' );
presets_boot();
$GLOBALS['dh']['queries'] = array();
$parent->controls = array();
( new Kit_Tab( $parent ) )->register_controls();
$check( '' === $parent->controls['digitalisimo_presets_list']['raw'] && 0 === presets_reads(), 'En el front (CSS del kit), la pestaña no lee la opción.' );

// ── 8 · Fuentes: PHP 7.4 y textos ────────────────────────────────────────────────────────────────────────────────
foreach ( glob( __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-design/presets/class-{store,ajax,guard,controls,compiler,kit-tab,module}.php', GLOB_BRACE ) as $file ) {
	$source = file_get_contents( $file );
	$check( false !== strpos( $source, "defined( 'ABSPATH' ) || exit;" ), basename( $file ) . ' debe salir si se abre directamente.' );
	$check( ! preg_match( '/\b(str_contains|str_starts_with|str_ends_with)\s*\(|\bmatch\s*\(|\bfn\s*\(|\?->/', $source ), basename( $file ) . ' debe ser compatible con PHP 7.4.' );
	preg_match_all( "/__\(\s*'([^']*)'|esc_html__\(\s*'([^']*)'/", $source, $strings );
	foreach ( array_filter( array_merge( $strings[1], $strings[2] ) ) as $text ) {
		$check( false === stripos( $text, 'elementor' ) && false === stripos( $text, 'divi' ) && false === stripos( $text, 'betheme' ), basename( $file ) . ': texto visible sin nombres de otros constructores: ' . $text );
	}
}

echo "F7a · presets (B y C): saneado con grupos reales, lista blanca y cierre, almacén con límites, inertes, cerrojo y usos, permisos y guarda, acciones, control en common, aplanado en el CSS del documento con lo local ganando, y pestaña del kit en verde.\n";
