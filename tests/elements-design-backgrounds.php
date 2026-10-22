<?php
/**
 * Máscaras de recorte y patrones de F7a (frente G, §3.3 y V.5 de la especificación).
 *
 * - Biblioteca: `library.php` es exactamente lo que genera `scripts/design-library.mjs` desde `svg/` (la generación se
 *   repite aquí en PHP); `MANIFEST.json` lleva el sha256, los bytes, la autoría y la fecha de cada fuente; 12 máscaras
 *   y 12 teselas propias, de ≤ 1,5 KB, con `viewBox`, un solo relleno y nada de la lista de rechazo.
 * - Codificación en porcentaje (V-B3): ningún `data:` lleva `;`, `#` ni llaves, y las reglas pasan por el `Stylesheet`
 *   real del núcleo.
 * - Controles: máscara en contenedor, sección y columna (no en widgets); patrón en los cuatro, con una instancia en
 *   widgets con el marcado optimizado y otra sin él; `::after` con `display: block`; «Elevar sobre lo siguiente»
 *   activado por defecto y sin pisar el `z-index` del usuario.
 * - Una variable por slug y documento, también con 20 usos; diccionario a `var(--digi-…)`.
 * - SVG del sitio: copia saneada en `uploads/digitalisimo-elements/masks/<sha8>.svg` con la ruta en el control
 *   oculto, comprobada con `realpath` y con un nombre que la parte `files` de K admite; uno hostil, uno de otro sitio o
 *   uno fuera de `uploads` no dejan regla; ninguna regla lleva `url(http`, `url(//` ni la ruta de `uploads`.
 */

require __DIR__ . '/sources/design/backgrounds-doubles.php';

use Digitalisimo\Elements\Design\Css;
use Digitalisimo\Elements\Design\Backgrounds\Library;
use Digitalisimo\Elements\Design\Backgrounds\Mask;
use Digitalisimo\Elements\Design\Backgrounds\Pattern;

bg_breakpoints(
	array(
		'mobile' => array( 767, 'max' ),
		'tablet' => array( 1024, 'max' ),
	)
);

$dir = BG_ROOT . 'modules/digitalisimo-design/backgrounds/';

// ── Guardas ─────────────────────────────────────────────────────────────────────────────────────────────────────
Mask::init();
Pattern::init();
bg_assert( 0 === bg_hook_count( 'elementor/element/parse_css' ) && 0 === bg_hook_count( 'elementor/document/save/data' ), 'Sin el saneador SVG del núcleo, máscaras y patrones no registran nada.' );
$missing = \Digitalisimo\Elements\Design\Module::missing();
bg_assert( isset( $missing['máscaras'], $missing['patrones'] ), 'Las guardas anotan lo que falta para el aviso del editor.' );

bg_declare_late();
Mask::init();
Pattern::init();
Mask::init();
foreach ( array( 'section', 'column', 'container' ) as $type ) {
	bg_assert( 1 === bg_hook_count( 'elementor/element/' . $type . '/section_effects/after_section_end' ), 'Máscara en ' . $type );
}
bg_assert( 0 === bg_hook_count( 'elementor/element/common/section_effects/after_section_end' ), 'Los widgets conservan la máscara del núcleo (D11).' );
foreach ( array( 'section/section_background', 'column/section_style', 'container/section_background', 'common/_section_background' ) as $place ) {
	bg_assert( 1 === bg_hook_count( 'elementor/element/' . $place . '/before_section_end' ), 'Patrón en ' . $place );
}
foreach ( array_keys( $GLOBALS['bg_hooks'] ) as $hook ) {
	bg_assert( false === strpos( $hook, 'common-optimized' ), 'Nunca se engancha common-optimized (V-I1).' );
}
bg_assert( 2 === bg_hook_count( 'elementor/element/parse_css' ), 'Un parse_css de la máscara y otro del patrón.' );
foreach ( array( 'elementor/document/save/data', 'elementor/files/css/selectors', 'elementor/preview/enqueue_styles' ) as $hook ) {
	bg_assert( 1 === bg_hook_count( $hook ), 'Library se engancha una sola vez a ' . $hook );
}
foreach ( array( 'wp_enqueue_scripts', 'wp_head', 'wp_footer', 'elementor/frontend/after_enqueue_styles' ) as $hook ) {
	bg_assert( 0 === bg_hook_count( $hook ), 'Nada de máscaras ni patrones en el front fuera del CSS del documento: ' . $hook );
}

// ── Biblioteca: generación, manifiesto y reglas de las fuentes ──────────────────────────────────────────────────

/** `encode()` de scripts/design-library.mjs. */
function bg_encode( string $svg ): string {
	$min = trim( (string) preg_replace( array( '/>\s+</', '/\s+/' ), array( '><', ' ' ), trim( $svg ) ) );
	if ( false === strpos( $min, "'" ) ) {
		$min = str_replace( '"', "'", $min );
	}
	return 'url("data:image/svg+xml,' . rawurlencode( $min ) . '")';
}

$sources = array();
$lines   = array( '<?php', '/**', ' * Biblioteca propia de máscaras y teselas de F7a: geometría DIGITALÍSIMO, GPL-3.0-or-later.', ' *', ' * Generada por `scripts/design-library.mjs` desde `svg/`; no se edita a mano. `node scripts/design-library.mjs', ' * --write` la regenera y `tests/elements-design-backgrounds.php` comprueba que coincide con las fuentes.', ' */', '', "defined( 'ABSPATH' ) || exit;", '', 'return array(' );
foreach ( array( 'masks', 'patterns' ) as $kind ) {
	$lines[] = "\t'" . $kind . "' => array(";
	$files   = glob( $dir . 'svg/' . $kind . '/*.svg' );
	sort( $files, SORT_STRING );
	foreach ( $files as $file ) {
		$slug                      = basename( $file, '.svg' );
		$svg                       = (string) file_get_contents( $file );
		$sources[ $kind ][ $slug ] = $svg;
		$lines[]                   = "\t\t'" . $slug . "' => '" . bg_encode( $svg ) . "',";
	}
	$lines[] = "\t),";
}
$lines[] = ');';
$lines[] = '';
bg_assert( implode( "\n", $lines ) === file_get_contents( $dir . 'library.php' ), 'library.php no coincide con las fuentes: node scripts/design-library.mjs --write' );
bg_assert( count( $sources['masks'] ) === 12 && count( $sources['patterns'] ) === 12, 'Doce máscaras y doce teselas.' );
foreach ( array( 'masks' => Library::MASKS, 'patterns' => Library::TILES ) as $kind => $labels ) {
	$named = array_keys( $labels );
	$files = array_keys( $sources[ $kind ] );
	sort( $named );
	sort( $files );
	bg_assert( $named === $files, 'Library nombra exactamente las fuentes de svg/' . $kind . '/.' );
	foreach ( $labels as $label ) {
		bg_assert( '' !== $label && false === stripos( $label, 'elementor' ), 'Etiqueta en español sin marcas: ' . $label );
	}
}
bg_assert( Library::slugs( 'masks' ) === array_keys( Library::MASKS ) && Library::slugs( 'patterns' ) === array_keys( Library::TILES ) && array() === Library::slugs( 'otra' ), 'slugs() por tipo.' );
foreach ( array( 'diagonal', 'arco', 'ola', 'ondas', 'escamas', 'zigzag', 'hexagonos', 'pildora', 'mancha' ) as $origin ) {
	bg_assert( ! isset( $sources['masks'][ $origin ] ) && ! isset( $sources['patterns'][ $origin ] ), 'Catálogo propio (R-M5): sin ' . $origin );
}

$manifest = json_decode( (string) file_get_contents( $dir . 'MANIFEST.json' ), true );
bg_assert( is_array( $manifest ) && 'scripts/design-library.mjs' === $manifest['generator'] && 24 === count( $manifest['files'] ), 'MANIFEST.json con sus 24 fuentes.' );
foreach ( $sources as $kind => $svgs ) {
	foreach ( $svgs as $slug => $svg ) {
		$entry = isset( $manifest['files'][ $kind . '/' . $slug . '.svg' ] ) ? $manifest['files'][ $kind . '/' . $slug . '.svg' ] : null;
		bg_assert( is_array( $entry ) && hash( 'sha256', $svg ) === $entry['sha256'] && strlen( $svg ) === $entry['bytes'], 'sha256 o bytes de ' . $kind . '/' . $slug . ' no coinciden con el manifiesto.' );
		bg_assert( 'DIGITALÍSIMO, GPL-3.0-or-later' === $entry['author'] && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $entry['date'] ), 'Autoría y fecha de ' . $slug );
		bg_assert( strlen( $svg ) <= 1536, $slug . ' pasa de 1,5 KB.' );
		bg_assert( 1 === preg_match( '#^<svg xmlns="http://www\.w3\.org/2000/svg" viewBox="[0-9. ]+">#', $svg ), $slug . ' necesita xmlns y viewBox.' );
		bg_assert( ! Css::svg_rejected( $svg ), $slug . ' contiene algo de la lista de rechazo.' );
		bg_assert( ! preg_match( '/stroke|style|opacity|gradient|<image|<use|<text|href|transform/i', $svg ), $slug . ' debe ser una primitiva de un solo relleno.' );
		preg_match_all( '/\sfill="([^"]*)"/', $svg, $fills );
		bg_assert( count( array_unique( $fills[1] ) ) <= 1, $slug . ' tiene más de un relleno.' );
		$sanitized = Css::svg_data_uri( $svg );
		bg_assert( null !== $sanitized, $slug . ' no supera el saneador del núcleo.' );
		$uri = 'masks' === $kind ? Library::mask( $slug ) : Library::tile( $slug );
		bg_assert( bg_encode( $svg ) === $uri, 'Library devuelve la entrada generada de ' . $slug );
		$data = substr( $uri, 5, -2 );
		bg_assert( 0 === strpos( $uri, 'url("data:image/svg+xml,%3Csvg' ) && '")' === substr( $uri, -2 ) && ! preg_match( '/[;#{}"\'\s]/', $data ), 'Codificado en porcentaje: ' . $slug );
		bg_assert( strlen( $uri ) <= 4096, 'Una entrada de la biblioteca cabe en 4 KB por uso: ' . $slug );
	}
}
bg_assert( null === Library::mask( 'puntos' ) && null === Library::tile( 'circulo' ) && null === Library::mask( 'nueva' ), 'Cada slug sólo en su tipo.' );

// Por el Stylesheet real: como array (variables) y como cadena (el diccionario del núcleo), sin perder la regla.
$sheet = new \Elementor\Stylesheet();
$sheet->add_rules( ':root', array( '--digi-mask-circulo' => Library::mask( 'circulo' ) ) );
$sheet->add_rules( '.x', '-webkit-mask-image: ' . Library::mask( 'estrella' ) . '; mask-image: ' . Library::mask( 'estrella' ) . ';' );
$text = (string) $sheet;
bg_assert( false !== strpos( $text, ':root{--digi-mask-circulo:' . Library::mask( 'circulo' ) . ';}' ), 'La variable pasa por add_rules(): ' . $text );
bg_assert( false !== strpos( $text, '.x{-webkit-mask-image:' . Library::mask( 'estrella' ) . ';mask-image:' . Library::mask( 'estrella' ) . ';}' ), 'El data: sobrevive al análisis por «;» del núcleo (V-B3).' );
$inline = Css::svg_data_uri( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect style="fill:#000;opacity:.5" width="10" height="10"/></svg>' );
bg_assert( null !== $inline && false === strpos( substr( $inline, 5, -2 ), ';' ), 'Un SVG con estilos en línea se codifica sin «;».' );
$sheet->add_rules( '.y', 'mask-image: ' . $inline . ';' );
bg_assert( false !== strpos( (string) $sheet, '.y{mask-image:' . $inline . ';}' ), 'Y pasa entero por el Stylesheet.' );

$root = Library::root_css();
bg_assert( 0 === strpos( $root, ':root{' ) && 24 === substr_count( $root, ':url("data:image/svg+xml,' ) && 1 === substr_count( $root, '--digi-tile-puntos:' ), 'La vista previa define las 24 variables, una vez cada una.' );

// ── Controles ───────────────────────────────────────────────────────────────────────────────────────────────────

/** Pila de un tipo con el grupo de fondo real y los ganchos que dispara el núcleo. */
function bg_element( string $class, string $name, string $type, array $settings = array(), string $id = 'abc1234', bool $optimized = false ) {
	$stack    = new $class( $name, $type, $settings, $id );
	$sections = array(
		'section'   => 'section_background',
		'column'    => 'section_style',
		'container' => 'section_background',
	);
	$section  = isset( $sections[ $name ] ) ? $sections[ $name ] : '_section_background';
	$stack->start_controls_section( $section, array( 'tab' => 'style' ) );
	$args = array( 'name' => isset( $sections[ $name ] ) ? 'background' : '_background' );
	if ( ! isset( $sections[ $name ] ) ) {
		$args['selector'] = constant( get_class( $stack ) . '::WRAPPER_SELECTOR_CHILD' );
	}
	$stack->add_group_control( 'background', $args );
	if ( $optimized ) {
		do_action( 'elementor/element/common-optimized/_section_background/before_section_end', $stack, array() );
	}
	do_action( 'elementor/element/' . ( isset( $sections[ $name ] ) ? $name : 'common' ) . '/' . $section . '/before_section_end', $stack, array() );
	$stack->end_controls_section();
	if ( isset( $sections[ $name ] ) ) {
		$stack->start_controls_section( 'section_effects', array( 'tab' => 'advanced' ) );
		$stack->end_controls_section();
		do_action( 'elementor/element/' . $name . '/section_effects/after_section_end', $stack, array() );
	}
	return $stack;
}

$targets = array(
	'section'   => '{{WRAPPER}}',
	'column'    => '{{WRAPPER}} > .elementor-widget-wrap',
	'container' => '{{WRAPPER}}',
);
foreach ( $targets as $type => $target ) {
	$el = bg_element( '\\Elementor\\Element_Base', $type, $type );
	bg_assert( 'advanced' === $el->get_controls( 'digitalisimo_section_mask' )['tab'], 'Sección «Máscara» en Avanzado: ' . $type );
	$keys = array_keys( $el->controls );
	bg_assert( array_search( 'digitalisimo_section_mask', $keys, true ) > array_search( 'section_effects', $keys, true ), 'Detrás de Efectos: ' . $type );
	$shape = $el->get_controls( 'digitalisimo_mask_shape' );
	bg_assert( array( $target ) === array_keys( $shape['selectors'] ), 'La máscara recorta ' . $target . ' en ' . $type );
	bg_assert( 'circulo' === $shape['default'] && '' === $el->get_controls( 'digitalisimo_mask' )['default'], 'Valores por defecto de la máscara.' );
	bg_assert( array( 'svg' ) === $el->get_controls( 'digitalisimo_mask_media' )['media_types'] && 'hidden' === $el->get_controls( 'digitalisimo_mask_file' )['type'], 'SVG de Medios y control oculto.' );
	foreach ( Library::slugs( 'masks' ) as $slug ) {
		bg_assert( 'var(--digi-mask-' . $slug . ')' === $shape['selectors_dictionary'][ $slug ], 'Diccionario a variable (V-m18): ' . $slug );
	}
	bg_assert( '' === $shape['selectors_dictionary'][ Library::MEDIA ], 'media no escribe nada por el diccionario.' );
	bg_assert( strlen( (string) json_encode( $shape['selectors_dictionary'] ) ) < 1024, 'El diccionario no engorda la configuración del editor (menor 18).' );
	foreach ( $el->controls as $name => $control ) {
		if ( 0 === strpos( $name, 'digitalisimo_mask' ) && ! empty( $control['selectors'] ) ) {
			foreach ( $control['selectors'] as $css ) {
				$properties = array_map(
					function ( $declaration ) {
						return trim( explode( ':', $declaration, 2 )[0] );
					},
					array_filter( array_map( 'trim', explode( ';', $css ) ) )
				);
				foreach ( $properties as $property ) {
					bg_assert( 0 !== strpos( $property, 'mask-' ) || in_array( '-webkit-' . $property, $properties, true ), 'Con y sin -webkit-: ' . $name . ' (' . $property . ')' );
				}
			}
		}
		if ( 0 === strpos( $name, 'digitalisimo_mask' ) && 'digitalisimo_mask' !== $name && 'digitalisimo_mask_file' !== $name && 'section' !== $control['type'] ) {
			bg_assert( 'yes' === $control['condition']['digitalisimo_mask'], 'Sólo con la máscara activa: ' . $name );
		}
	}
	$pattern = $el->get_controls( 'digitalisimo_pattern' );
	bg_assert( 'popover_toggle' === $pattern['type'] && '' === $pattern['default'], 'Popover «Patrón» apagado por defecto: ' . $type );
	bg_assert( isset( $pattern['selectors'][ $target ], $pattern['selectors'][ $target . '::after' ], $pattern['selectors'][ ':where(' . $target . ')' ] ), 'El patrón va en el elemento que lleva el fondo: ' . $type );
	bg_assert( false !== strpos( $pattern['selectors'][ $target ], 'isolation: isolate' ), 'isolation: isolate en ' . $type );
	bg_assert( false !== strpos( $pattern['selectors'][ $target . '::after' ], 'display: block' ) && false !== strpos( $pattern['selectors'][ $target . '::after' ], 'z-index: -1' ), '::after con display: block (V-m5) y bajo el contenido.' );
	$raise = $el->get_controls( 'digitalisimo_pattern_raise' );
	bg_assert( 'yes' === $raise['default'], '«Elevar sobre lo siguiente» activado por defecto (R-I13).' );
	bg_assert( 'container' === $type ? array( '{{WRAPPER}}' => '--z-index: 1;' ) === $raise['selectors'] : array( ':where(' . $target . ')' => 'z-index: 1;' ) === $raise['selectors'], 'Elevar sin pisar el z-index del usuario: ' . $type );
	bg_assert( array() === $el->redeclared, 'Ningún control repetido en ' . $type );
}
foreach ( array( '\\Elementor\\BG_Common' => array( 'common', false, '{{WRAPPER}} > .elementor-widget-container' ), '\\Elementor\\BG_Common_Optimized' => array( 'common-optimized', true, '{{WRAPPER}}' ) ) as $class => $case ) {
	$el = bg_element( $class, $case[0], '', array(), 'abc1234', $case[1] );
	bg_assert( null === $el->get_controls( 'digitalisimo_mask' ), 'Sin máscara propia en widgets.' );
	bg_assert( 1 === count( preg_grep( '/^digitalisimo_pattern$/', array_keys( $el->controls ) ) ) && array() === $el->redeclared, 'Una instancia del patrón en ' . $case[0] );
	$pattern = $el->get_controls( 'digitalisimo_pattern' );
	bg_assert( isset( $pattern['selectors'][ $case[2] . '::after' ] ), 'El patrón del widget usa su WRAPPER_SELECTOR_CHILD: ' . $case[0] );
	bg_assert( array( ':where(' . $case[2] . ')' => 'z-index: 1;' ) === $el->get_controls( 'digitalisimo_pattern_raise' )['selectors'], 'Elevar en widgets por :where().' );
}

// ── CSS del documento por el Stylesheet real ────────────────────────────────────────────────────────────────────

/** Lo que haría el núcleo con un control de estilo de valor simple (diccionario, filtro y {{VALUE}}/{{SIZE}}). */
function bg_style_rules( \Elementor\Core\Files\CSS\Post $css, \Elementor\Controls_Stack $el ): void {
	$settings = $el->get_settings();
	foreach ( $el->controls as $name => $control ) {
		if ( empty( $control['selectors'] ) || ! isset( $settings[ $name ] ) ) {
			continue;
		}
		foreach ( isset( $control['condition'] ) ? $control['condition'] : array() as $key => $want ) {
			if ( ( isset( $settings[ $key ] ) ? $settings[ $key ] : '' ) !== $want ) {
				continue 2;
			}
		}
		$value = $settings[ $name ];
		if ( is_string( $value ) && isset( $control['selectors_dictionary'][ $value ] ) ) {
			$value = $control['selectors_dictionary'][ $value ];
		}
		if ( '' === $value || array() === $value ) {
			continue;
		}
		$control = apply_filters( 'elementor/files/css/selectors', $control, $value, $css );
		foreach ( $control['selectors'] as $selector => $template ) {
			$out = str_replace( array( '{{VALUE}}', '{{SIZE}}', '{{UNIT}}' ), is_array( $value ) ? array( '', (string) $value['size'], (string) $value['unit'] ) : array( (string) $value, '', '' ), $template );
			$css->get_stylesheet()->add_rules( str_replace( '{{WRAPPER}}', $css->get_element_unique_selector( $el ), $selector ), $out );
		}
	}
	do_action( 'elementor/element/parse_css', $css, $el );
}

// Una variable por slug y documento, también con 20 usos.
$css = new \Elementor\Core\Files\CSS\Post( 42 );
for ( $i = 0; $i < 20; $i++ ) {
	bg_style_rules( $css, bg_element( '\\Elementor\\Element_Base', 'container', 'container', array( 'digitalisimo_pattern' => 'yes', 'digitalisimo_mask' => 'yes', 'digitalisimo_mask_shape' => 0 === $i % 2 ? 'estrella' : 'circulo' ), 'e' . $i ) );
}
$text = (string) $css->get_stylesheet();
bg_assert( 1 === substr_count( $text, Library::tile( 'puntos' ) ) && 1 === substr_count( $text, Library::mask( 'estrella' ) ) && 1 === substr_count( $text, Library::mask( 'circulo' ) ), 'Una sola definición por slug y documento con 20 usos.' );
bg_assert( 1 === substr_count( $text, ':root{' ), 'Todas en un solo :root.' );
bg_assert( 20 === substr_count( $text, '::after{content:"";display:block;' ), 'Cada patrón con su ::after.' );
bg_assert( false !== strpos( $text, '.elementor-42 .elementor-element.elementor-element-e1{isolation:isolate;--z-index:1;-webkit-mask-image:var(--digi-mask-circulo);mask-image:var(--digi-mask-circulo);' ), 'Regla del contenedor: ' . substr( $text, 0, 600 ) );
bg_assert( false !== strpos( $text, '.elementor-42 .elementor-element.elementor-element-e0::after{content:"";display:block;position:absolute;inset:0;z-index:-1;pointer-events:none;border-radius:inherit;background-color:currentColor;' ) && false !== strpos( $text, 'mask-image:var(--digi-tile-puntos);' ), 'Regla del ::after.' );
bg_assert( ! preg_match( '#url\((?!"data:image/svg\+xml,)|url\("data:[^"]*[;\#{}]|/uploads/#', $text ), 'Sólo data: propios en porcentaje: ' . $text );

// Columna: recorta su widget-wrap; sección: su raíz; el z-index del usuario gana al de «Elevar».
$css = new \Elementor\Core\Files\CSS\Post( 7 );
bg_style_rules( $css, bg_element( '\\Elementor\\Element_Base', 'column', 'column', array( 'digitalisimo_mask' => 'yes', 'digitalisimo_mask_shape' => 'gota', 'digitalisimo_pattern' => 'yes', 'digitalisimo_pattern_tile' => 'rayas', 'digitalisimo_pattern_raise' => '' ), 'col' ) );
$text = (string) $css->get_stylesheet();
bg_assert( false !== strpos( $text, '.elementor-7 .elementor-element.elementor-element-col > .elementor-widget-wrap{-webkit-mask-image:var(--digi-mask-gota)' ) || false !== strpos( $text, '.elementor-7 .elementor-element.elementor-element-col > .elementor-widget-wrap{isolation:isolate;-webkit-mask-image:var(--digi-mask-gota)' ), 'La columna recorta su widget-wrap: ' . $text );
bg_assert( false === strpos( $text, 'z-index: 1' ) && false === strpos( $text, 'z-index:1' ), 'Sin «Elevar», no se eleva.' );

// Slug desconocido (kit de otra versión) o valor con CSS: el núcleo no escribe la regla y no hay variable.
foreach ( array( 'nueva', 'circulo;}body{display:none', 'var(--digi-mask-x);}a{b:c' ) as $bad ) {
	$css = new \Elementor\Core\Files\CSS\Post( 9 );
	bg_style_rules( $css, bg_element( '\\Elementor\\Element_Base', 'section', 'section', array( 'digitalisimo_mask' => 'yes', 'digitalisimo_mask_shape' => $bad, 'digitalisimo_mask_repeat' => 'repeat;}x{y:z' ), 'bad' ) );
	$text = (string) $css->get_stylesheet();
	bg_assert( false === strpos( $text, 'mask-image' ) && false === strpos( $text, 'body{' ) && false === strpos( $text, 'x{y' ) && false === strpos( $text, ':root' ), 'Un valor ajeno no deja regla: ' . $bad . ' → ' . $text );
}

// El CSS dinámico no recibe nada.
$dynamic = new \Elementor\Core\DynamicTags\Dynamic_CSS( 9 );
do_action( 'elementor/element/parse_css', $dynamic, bg_element( '\\Elementor\\Element_Base', 'section', 'section', array( 'digitalisimo_mask' => 'yes', 'digitalisimo_pattern' => 'yes' ) ) );
bg_assert( '' === (string) $dynamic->get_stylesheet(), 'El archivo dinámico no recibe máscaras ni patrones.' );

// ── SVG del sitio ───────────────────────────────────────────────────────────────────────────────────────────────
$GLOBALS['bg_uploads'] = sys_get_temp_dir() . '/bg-uploads-' . getmypid() . '-' . mt_rand();
$GLOBALS['bg_posts']   = array();
mkdir( $GLOBALS['bg_uploads'] . '/2026/10', 0777, true );
mkdir( $GLOBALS['bg_uploads'] . '-otro', 0777, true );
function wp_upload_dir() {
	return array( 'basedir' => $GLOBALS['bg_uploads'], 'baseurl' => 'https://sitio.test/wp-content/uploads', 'error' => false );
}
function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0777, true );
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function get_post( $id ) {
	return isset( $GLOBALS['bg_posts'][ $id ] ) ? (object) array( 'ID' => $id, 'post_type' => $GLOBALS['bg_posts'][ $id ]['type'] ) : null;
}
function get_post_mime_type( $post ) {
	return $GLOBALS['bg_posts'][ $post->ID ]['mime'];
}
function get_attached_file( $id ) {
	return $GLOBALS['bg_posts'][ $id ]['file'];
}
function bg_attachment( int $id, string $file, string $svg, string $mime = 'image/svg+xml', string $type = 'attachment' ): void {
	file_put_contents( $file, $svg );
	$GLOBALS['bg_posts'][ $id ] = array( 'file' => $file, 'mime' => $mime, 'type' => $type );
}
final class BG_Document {
	public $editable = true;
	public function is_editable_by_current_user() {
		return $this->editable;
	}
}

$good = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path style="fill:#000;opacity:.9" d="M0 0h10v10H0z"/></svg>';
bg_attachment( 7, $GLOBALS['bg_uploads'] . '/2026/10/forma.svg', $good );
bg_attachment( 8, $GLOBALS['bg_uploads'] . '/2026/10/hostil.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><path d="M0 0h1v1z"/></svg>' );
bg_attachment( 9, $GLOBALS['bg_uploads'] . '-otro/fuera.svg', $good );
bg_attachment( 10, $GLOBALS['bg_uploads'] . '/2026/10/foto.png', 'png', 'image/png' );
bg_attachment( 11, $GLOBALS['bg_uploads'] . '/2026/10/enorme.svg', '<svg xmlns="http://www.w3.org/2000/svg"><path d="' . str_repeat( 'M0 0h1v1H0z', 900 ) . '"/></svg>' );

$doc = array(
	'elements' => array(
		array(
			'id'       => 's1',
			'elType'   => 'container',
			'settings' => array(
				'digitalisimo_mask'       => 'yes',
				'digitalisimo_mask_shape' => 'media',
				'digitalisimo_mask_media' => array( 'id' => 7, 'url' => 'https://sitio.test/wp-content/uploads/2026/10/forma.svg' ),
			),
			'elements' => array(
				array(
					'id'       => 's2',
					'elType'   => 'container',
					'settings' => array(
						'digitalisimo_pattern'       => 'yes',
						'digitalisimo_pattern_tile'  => 'media',
						'digitalisimo_pattern_media' => array( 'id' => '7', 'url' => 'https://sitio.test/wp-content/uploads/2026/10/forma.svg' ),
					),
				),
				array(
					'id'       => 's3',
					'elType'   => 'section',
					'settings' => array(
						'digitalisimo_mask'       => 'yes',
						'digitalisimo_mask_shape' => 'media',
						'digitalisimo_mask_media' => array( 'id' => 8, 'url' => 'https://sitio.test/wp-content/uploads/2026/10/hostil.svg' ),
						'digitalisimo_mask_file'  => 'digitalisimo-elements/masks/00000000.svg',
					),
				),
			),
		),
	),
);
$saved = apply_filters( 'elementor/document/save/data', $doc, new BG_Document() );
$file  = $saved['elements'][0]['settings']['digitalisimo_mask_file'];
bg_assert( 1 === preg_match( '#^digitalisimo-elements/masks/([0-9a-f]{8})\.svg$#', $file, $sha ), 'La copia saneada se anota como ruta relativa <sha8>: ' . $file );
$copy = $GLOBALS['bg_uploads'] . '/' . $file;
bg_assert( is_file( $copy ) && Css::svg_clean( $good ) === file_get_contents( $copy ) && substr( hash( 'sha256', (string) file_get_contents( $copy ) ), 0, 8 ) === $sha[1], 'El archivo es el SVG saneado, con el nombre de su huella.' );
bg_assert( $file === $saved['elements'][0]['elements'][0]['settings']['digitalisimo_pattern_file'], 'El mismo SVG da la misma copia para el patrón.' );
bg_assert( '' === $saved['elements'][0]['elements'][1]['settings']['digitalisimo_mask_file'], 'Un SVG hostil vacía la copia.' );
bg_assert( array( $copy ) === glob( $GLOBALS['bg_uploads'] . '/digitalisimo-elements/masks/*' ), 'Sólo se escribe la copia saneada: el hostil no deja archivo.' );

// La parte `files` de K transporta esa ruta: su patrón y su slug admiten <sha8>.
$k_module = (string) file_get_contents( BG_ROOT . 'modules/digitalisimo-import/class-module.php' );
$k_files  = (string) file_get_contents( BG_ROOT . 'modules/digitalisimo-import/kits/parts/class-files.php' );
bg_assert( preg_match( "/const FILES_KEY_PATTERN = '([^']+)';/", $k_module, $k_key ) && preg_match( "/const SLUG\s+= '([^']+)';/", $k_files, $k_slug ), 'No se encontró el patrón de K.' );
bg_assert( 1 === preg_match( $k_key[1], substr( $file, strlen( 'digitalisimo-elements/' ) ) ), 'FILES_KEY_PATTERN de K admite la copia.' );
bg_assert( 1 === preg_match( '#digitalisimo-elements/(shapes|masks)/(' . $k_slug[1] . ')\.svg#', $file ), 'collect_strings() de K recoge la ruta del control oculto.' );

// Reutiliza la copia y la lee comprobada.
$again = apply_filters( 'elementor/document/save/data', $doc, new BG_Document() );
bg_assert( $file === $again['elements'][0]['settings']['digitalisimo_mask_file'], 'Guardar otra vez reutiliza la copia.' );
// Durante una importación de kits (A10, R8 de kit-roundtrip.sh) no se crea ninguna copia: se reutiliza la que ya hay y,
// sin ella, se conserva la ruta del kit. La escribe la parte `files` de K, que la anota para «Revertir».
\Digitalisimo\Elements\Import\Kits\Context::$active = true;
$importing = apply_filters( 'elementor/document/save/data', $doc, new BG_Document() );
bg_assert( $file === $importing['elements'][0]['settings']['digitalisimo_mask_file'], 'Importando, se reutiliza la copia que ya existe.' );
$copy_bytes = (string) file_get_contents( $copy );
unlink( $copy );
rmdir( dirname( $copy ) );
$kit_doc = $doc;
$kit_doc['elements'][0]['settings']['digitalisimo_mask_file'] = $file;
$importing = apply_filters( 'elementor/document/save/data', $kit_doc, new BG_Document() );
bg_assert( ! is_file( $copy ) && ! is_dir( dirname( $copy ) ), 'Importando, sin copia no se escribe ninguna ni se crea la carpeta: la escribe la parte files de K.' );
bg_assert( $file === $importing['elements'][0]['settings']['digitalisimo_mask_file'], 'Importando, sin copia se conserva la ruta del kit.' );
bg_assert( '' === $importing['elements'][0]['elements'][1]['settings']['digitalisimo_mask_file'], 'Importando, un SVG hostil sigue vaciando la copia.' );
\Digitalisimo\Elements\Import\Kits\Context::$active = false;
$again = apply_filters( 'elementor/document/save/data', $doc, new BG_Document() );
bg_assert( $file === $again['elements'][0]['settings']['digitalisimo_mask_file'] && $copy_bytes === (string) file_get_contents( $copy ), 'Fuera de la importación, la copia se vuelve a escribir igual.' );
$found = Library::file_uri( $file );
bg_assert( is_array( $found ) && $sha[1] === $found['name'] && 0 === strpos( $found['uri'], 'url("data:image/svg+xml,' ) && ! preg_match( '/[;#]/', substr( $found['uri'], 5, -2 ) ), 'La copia se lee como data: en porcentaje.' );
foreach ( array( '../../etc/passwd', 'digitalisimo-elements/masks/../x.svg', 'digitalisimo-elements/shapes/' . $sha[1] . '.svg', 'digitalisimo-elements/masks/' . $sha[1] . '.SVG', 'https://sitio.test/wp-content/uploads/' . $file, 'digitalisimo-elements/masks/no-existe.svg', 42 ) as $bad ) {
	bg_assert( null === Library::file_uri( $bad ), 'Ruta rechazada: ' . json_encode( $bad ) );
}

// Adjuntos que no son SVG de este sitio: se conserva lo que hubiera; uno enorme o hostil vacía la copia.
$keep = 'digitalisimo-elements/masks/' . $sha[1] . '.svg';
foreach ( array(
	'fuera de uploads'       => array( 9, 'https://sitio.test/wp-content/uploads-otro/fuera.svg', $keep ),
	'otro sitio de la red'   => array( 7, 'https://otro.test/wp-content/uploads/sites/2/2026/10/distinta.svg', $keep ),
	'no es SVG'              => array( 10, 'https://sitio.test/wp-content/uploads/2026/10/foto.png', $keep ),
	'no existe'              => array( 99, 'https://sitio.test/x.svg', $keep ),
	'demasiado grande'       => array( 11, 'https://sitio.test/wp-content/uploads/2026/10/enorme.svg', '' ),
) as $label => $case ) {
	$one = array( 'elements' => array( array( 'id' => 'z', 'elType' => 'container', 'settings' => array( 'digitalisimo_mask' => 'yes', 'digitalisimo_mask_shape' => 'media', 'digitalisimo_mask_media' => array( 'id' => $case[0], 'url' => $case[1] ), 'digitalisimo_mask_file' => $keep ) ) ) );
	$out = apply_filters( 'elementor/document/save/data', $one, new BG_Document() );
	bg_assert( $case[2] === $out['elements'][0]['settings']['digitalisimo_mask_file'], 'Adjunto ' . $label . ': ' . json_encode( $out['elements'][0]['settings']['digitalisimo_mask_file'] ) );
}
$one = array( 'elements' => array( array( 'id' => 'z', 'elType' => 'container', 'settings' => array( 'digitalisimo_mask_shape' => 'rombo', 'digitalisimo_mask_file' => $keep ) ) ) );
$out = apply_filters( 'elementor/document/save/data', $one, new BG_Document() );
bg_assert( ! isset( $out['elements'][0]['settings']['digitalisimo_mask_file'] ), 'Con otra forma, el control oculto se quita.' );
$one = array( 'elements' => array( array( 'id' => 'z', 'elType' => 'container', 'settings' => array( 'digitalisimo_mask_shape' => 'media', 'digitalisimo_mask_media' => array( 'id' => '', 'url' => '' ), 'digitalisimo_mask_file' => $keep ) ) ) );
$out = apply_filters( 'elementor/document/save/data', $one, new BG_Document() );
bg_assert( '' === $out['elements'][0]['settings']['digitalisimo_mask_file'], 'Quitar el archivo vacía la copia.' );
$locked = new BG_Document();
$locked->editable = false;
bg_assert( $doc === apply_filters( 'elementor/document/save/data', $doc, $locked ), 'Quien no puede editar el documento no escribe copias.' );

// CSS con la copia: variable propia y regla en los selectores del control; nunca el adjunto ni una URL.
$css = new \Elementor\Core\Files\CSS\Post( 42 );
bg_style_rules( $css, bg_element( '\\Elementor\\Element_Base', 'container', 'container', $saved['elements'][0]['settings'], 'm1' ) );
bg_style_rules( $css, bg_element( '\\Elementor\\Element_Base', 'container', 'container', $saved['elements'][0]['elements'][0]['settings'], 'p1' ) );
bg_style_rules( $css, bg_element( '\\Elementor\\Element_Base', 'section', 'section', $saved['elements'][0]['elements'][1]['settings'], 'h1' ) );
$text = (string) $css->get_stylesheet();
bg_assert( false !== strpos( $text, '--digi-mask-file-' . $sha[1] . ':' . $found['uri'] ) && false !== strpos( $text, '--digi-tile-file-' . $sha[1] . ':' . $found['uri'] ), 'Variables de la copia en :root: ' . $text );
/** Declaraciones de la regla de un selector exacto en la hoja. */
function bg_rule( string $text, string $selector ): string {
	$at = strpos( $text, $selector . '{' );
	return false === $at ? '' : substr( $text, $at + strlen( $selector ) + 1, strpos( $text, '}', $at ) - $at - strlen( $selector ) - 1 );
}
$m1 = bg_rule( $text, '.elementor-42 .elementor-element.elementor-element-m1' );
bg_assert( false !== strpos( $m1, '-webkit-mask-image:var(--digi-mask-file-' . $sha[1] . ');mask-image:var(--digi-mask-file-' . $sha[1] . ');' ), 'La máscara usa su copia: ' . $m1 );
$p1 = bg_rule( $text, '.elementor-42 .elementor-element.elementor-element-p1::after' );
bg_assert( false !== strpos( $p1, '-webkit-mask-image:var(--digi-tile-file-' . $sha[1] . ');mask-image:var(--digi-tile-file-' . $sha[1] . ');' ), 'El patrón usa su copia en ::after: ' . $p1 );
$h1 = bg_rule( $text, '.elementor-42 .elementor-element.elementor-element-h1' );
bg_assert( false === strpos( $h1, 'mask-image' ) && 1 === substr_count( $text, '-webkit-mask-image:var(--digi-mask-file-' ), 'El SVG hostil no deja regla de imagen: el elemento se ve sin recortar: ' . $h1 );
bg_assert( ! preg_match( '#url\(http|url\(//|url\("http|/uploads|forma\.svg#', $text ), 'Ninguna regla lleva una URL ni la ruta de uploads.' );

// Vista previa del editor: la hoja en línea de la biblioteca sólo en el gancho de la vista previa.
$GLOBALS['bg_styles'] = array();
function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
	$GLOBALS['bg_styles'][ $handle ]['src'] = $src;
}
function wp_enqueue_style( $handle ) {
	$GLOBALS['bg_styles'][ $handle ]['enqueued'] = true;
}
function wp_add_inline_style( $handle, $css ) {
	$GLOBALS['bg_styles'][ $handle ]['inline'] = $css;
}
do_action( 'elementor/preview/enqueue_styles' );
bg_assert( false === $GLOBALS['bg_styles']['digitalisimo-design-library']['src'] && Library::root_css() === $GLOBALS['bg_styles']['digitalisimo-design-library']['inline'], 'La vista previa define la biblioteca en línea, sin archivo.' );

array_map( 'unlink', array_merge( glob( $GLOBALS['bg_uploads'] . '/2026/10/*' ), glob( $GLOBALS['bg_uploads'] . '/digitalisimo-elements/masks/*' ), glob( $GLOBALS['bg_uploads'] . '-otro/*' ) ) );

echo "DIGITALÍSIMO Elements: máscaras y patrones (biblioteca propia regenerada, variables por documento, copias saneadas en masks/<sha8>.svg) validados.\n";
