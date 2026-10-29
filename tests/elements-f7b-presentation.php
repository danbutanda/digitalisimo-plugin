<?php
/**
 * F7b, frente G: presentación de pestañas y acordeón anidados (C6; D16, §3.5 y V.7).
 *
 * - Guarda: sin `Elementor\Controls_Manager` no se registra nada y se anota.
 * - Sección «Presentación» de Estilo detrás de `section_tabs_style` (`nested-tabs`) y de su equivalente del acordeón,
 *   `section_accordion_style` (`nested-accordion`): las dos son la primera sección de Estilo de su widget en las fuentes
 *   del núcleo. Una sola vez por pila.
 * - Control `digitalisimo_presentation`: sin `default`, sin `selectors`, `prefix_class` `digi-nested-`, `none` como
 *   alias de «Ninguno» (`classes_dictionary`), `render_type` `ui`, `style_transfer` verdadero y la hoja en `assets`
 *   con su condición, evaluada con el `Conditions::check()` real del núcleo.
 * - Sin estilo elegido (sin ajuste, vacío o `none`), el HTML y el CSS de los dos widgets son los de 4.3.0.168: la clase
 *   del envoltorio, calculada con el algoritmo de `Element_Base::add_render_attributes()` (contrato leído de su
 *   fuente), no cambia; ningún control añade CSS al documento; la hoja no se encola; y, si está el ZIP publicado de
 *   4.3.0.168 (`dist/`, `rollback/` o `DIGI_BASELINE_ZIP`), las fuentes del núcleo que pintan esos widgets son byte a
 *   byte las mismas.
 * - La hoja: ≤ 4 KB (objetivo 2,5 KB), registrada y encolada sólo por `assets`; en la vista previa del editor, siempre.
 * La especificidad, los colores y el comportamiento en un navegador real los mira `elements-f7b-presentation.mjs`.
 */
namespace {
	require __DIR__ . '/fixtures/f7b-wp.php';
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', realpath( __DIR__ . '/../digitalisimo-elements/pro-elements.php' ) );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$GLOBALS['styles']   = array();
	$GLOBALS['enqueued'] = array();
	function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['styles'][ $handle ] = compact( 'src', 'deps', 'ver' );
		return true;
	}
	function wp_enqueue_style( $handle ) {
		$GLOBALS['enqueued'][] = $handle;
	}
	function wp_style_is( $handle, $list = 'enqueued' ) {
		return 'registered' === $list ? isset( $GLOBALS['styles'][ $handle ] ) : in_array( $handle, $GLOBALS['enqueued'], true );
	}
	function plugins_url( $path, $file ) {
		return 'https://example.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' );
	}
	require $root . '/modules/digitalisimo-f7b/class-module.php';
	require $root . '/modules/digitalisimo-f7b/nested/class-presentation.php';
	require $root . '/elementor/includes/conditions.php';
	$P = '\\Digitalisimo\\Elements\\F7b\\Nested\\Presentation';
	$M = '\\Digitalisimo\\Elements\\F7b\\Module';

	// Guarda: el gestor de controles aún no existe.
	$P::init();
	f7b_check( array() === $GLOBALS['f7b']['hooks'] || array( 'elementor/editor/after_enqueue_scripts' ) === array_keys( $GLOBALS['f7b']['hooks'] ), 'Sin el gestor de controles no se engancha nada propio.' );
	f7b_check( in_array( 'Elementor\\Controls_Manager', $M::missing()['presentation'] ?? array(), true ), 'La guarda anota el símbolo que falta.' );
	$GLOBALS['f7b']['hooks'] = array();
}
namespace Elementor {
	if ( true ) { // Declarada en ejecución, después de la prueba de la guarda.
		class Controls_Manager {
			const SELECT    = 'select';
			const TAB_STYLE = 'style';
		}
	}
}
namespace {
	$P::init();
	$P::init();
	$targets = array(
		'elementor/element/nested-tabs/section_tabs_style/after_section_end',
		'elementor/element/nested-accordion/section_accordion_style/after_section_end',
		'elementor/frontend/after_register_styles',
		'elementor/preview/enqueue_styles',
	);
	foreach ( $targets as $hook ) {
		f7b_check( 1 === count( f7b_hooks( $hook ) ), "Un solo gancho en {$hook}." );
	}
	f7b_check( count( $targets ) === count( $GLOBALS['f7b']['hooks'] ), 'Nada más: ningún gancho del render, ni before_render, ni CSS del documento.' );

	// Las secciones de anclaje son la primera de Estilo de cada widget, y los nombres de pila coinciden.
	foreach ( array(
		'nested-tabs'      => array( 'elementor/modules/nested-tabs/widgets/nested-tabs.php', 'section_tabs_style' ),
		'nested-accordion' => array( 'elementor/modules/nested-accordion/widgets/nested-accordion.php', 'section_accordion_style' ),
	) as $name => list( $file, $section ) ) {
		$source = (string) file_get_contents( $root . '/' . $file );
		$at     = strpos( $source, "'{$section}'" );
		$style  = strpos( $source, 'Controls_Manager::TAB_STYLE' );
		f7b_check( false !== strpos( $source, "return '{$name}';" ) && false !== $at && false !== $style && $at < $style && $style - $at < 200, "{$name}: «{$section}» es su primera sección de Estilo." );
		f7b_check( $section === \Digitalisimo\Elements\F7b\Nested\Presentation::TARGETS[ $name ], "La presentación se ancla tras {$section}." );
	}

	// Pila falsa.
	$stack = new class() {
		public $controls = array();
		public $sections = array();
		public $open     = null;
		public function get_controls( $name = null ) { return null === $name ? $this->controls : ( $this->controls[ $name ] ?? null ); }
		public function start_controls_section( $id, array $args ) {
			if ( null !== $this->open ) { throw new \RuntimeException( 'Sección abierta.' ); }
			$this->open            = $id;
			$this->sections[ $id ] = $args;
		}
		public function end_controls_section() { $this->open = null; }
		public function add_control( $name, array $args ) {
			if ( null === $this->open || isset( $this->controls[ $name ] ) ) { throw new \RuntimeException( 'Control fuera de sección o repetido: ' . $name ); }
			$this->controls[ $name ] = $args + array( 'section' => $this->open );
		}
	};
	do_action( 'elementor/element/nested-tabs/section_tabs_style/after_section_end', $stack, array() );
	do_action( 'elementor/element/nested-tabs/section_tabs_style/after_section_end', $stack, array() );
	f7b_check( array( 'digitalisimo_presentation' ) === array_keys( $stack->controls ) && array( 'digitalisimo_presentation_section' ) === array_keys( $stack->sections ), 'Una sola sección con un solo control, aunque el gancho corra dos veces.' );
	f7b_check( 'style' === $stack->sections['digitalisimo_presentation_section']['tab'] && 'Presentación' === $stack->sections['digitalisimo_presentation_section']['label'], 'Sección «Presentación» en la pestaña Estilo.' );
	$c = $stack->controls['digitalisimo_presentation'];
	f7b_check( ! array_key_exists( 'default', $c ) && ! isset( $c['selectors'] ) && ! isset( $c['selector'] ), 'Sin default ni selectors: no cambia el CSS de ningún widget existente.' );
	f7b_check( 'select' === $c['type'] && array( '', 'pills', 'underline', 'boxed', 'minimal' ) === array_keys( $c['options'] ), 'Ninguno, píldoras, subrayado, recuadro y mínimo.' );
	f7b_check( 'digi-nested-' === $c['prefix_class'] && array( 'none' => '' ) === $c['classes_dictionary'] && 'ui' === $c['render_type'] && true === $c['style_transfer'], 'prefix_class, none como alias de Ninguno, render_type ui y style_transfer verdadero (es estilo).' );
	foreach ( $c['options'] as $label ) {
		f7b_check( ! preg_match( '/\b(Elementor|Bricks|Divi|none|pills|boxed|underline|minimal)\b/', $label ), "Etiqueta en español sin proveedores: {$label}." );
	}

	// Recursos: la condición real del núcleo.
	$assets = static function ( array $settings ) use ( $c ): array {
		$out = array();
		foreach ( $settings as $key => $value ) {
			if ( 'digitalisimo_presentation' !== $key ) {
				continue;
			}
			foreach ( $c['assets']['styles'] as $dependency ) {
				if ( empty( $dependency['conditions'] ) || \Elementor\Conditions::check( $dependency['conditions'], $settings ) ) {
					$out[] = $dependency['name'];
				}
			}
		}
		return $out;
	};
	foreach ( array( array(), array( 'digitalisimo_presentation' => '' ), array( 'digitalisimo_presentation' => 'none' ), array( 'digitalisimo_presentation' => 'rainbow' ), array( 'digitalisimo_presentation' => null ) ) as $settings ) {
		f7b_check( array() === $assets( $settings ), 'Sin estilo, la hoja no se encola: ' . json_encode( $settings ) );
	}
	foreach ( array( 'pills', 'underline', 'boxed', 'minimal' ) as $style ) {
		f7b_check( array( 'digitalisimo-f7b-nested-presentation' ) === $assets( array( 'digitalisimo_presentation' => $style ) ), "Con {$style}, la hoja." );
	}

	// Clase del envoltorio: el algoritmo del núcleo (contrato leído de su fuente).
	$base = (string) file_get_contents( $root . '/elementor/includes/base/element-base.php' );
	foreach ( array(
		"if ( isset( \$controls[ \$setting_key ]['classes_dictionary'][ \$setting ] ) ) {",
		"\$value = \$controls[ \$setting_key ]['classes_dictionary'][ \$setting ];",
		"if ( empty( \$setting ) && '0' !== \$setting ) {",
		"\$this->add_render_attribute( '_wrapper', 'class', \$controls[ \$setting_key ]['prefix_class'] . \$setting );",
	) as $line ) {
		f7b_check( false !== strpos( $base, $line ), 'Element_Base::add_render_attributes(): ' . $line );
	}
	$classes = static function ( array $settings ) use ( $c ): array {
		$out = array();
		foreach ( $settings as $key => $setting ) {
			if ( 'digitalisimo_presentation' !== $key ) {
				continue;
			}
			$value = is_scalar( $setting ) && isset( $c['classes_dictionary'][ $setting ] ) ? $c['classes_dictionary'][ $setting ] : $setting;
			if ( empty( $value ) && '0' !== $value ) {
				continue;
			}
			$out[] = $c['prefix_class'] . $value;
		}
		return $out;
	};
	foreach ( array( array(), array( 'digitalisimo_presentation' => '' ), array( 'digitalisimo_presentation' => 'none' ) ) as $settings ) {
		f7b_check( array() === $classes( $settings ), 'Sin estilo, el envoltorio no lleva ninguna clase nueva: ' . json_encode( $settings ) );
	}
	f7b_check( array( 'digi-nested-pills' ) === $classes( array( 'digitalisimo_presentation' => 'pills' ) ), 'Con un estilo, sólo digi-nested-<estilo>.' );
	$editor = (string) file_get_contents( $root . '/elementor/assets/js/editor.js' );
	f7b_check( false !== strpos( $editor, 'if (void 0 !== control.classes_dictionary[value]) classValue = control.classes_dictionary[value];' ) && false !== strpos( $editor, '(classValue || 0 === classValue)) self.$el.addClass(control.prefix_class + classValue);' ), 'El editor aplica el mismo diccionario y tampoco pone clase vacía.' );
	f7b_check( false !== strpos( $editor, 'if (-1 !== ["none", "ui"].indexOf(control.render_type)) return;' ) && false !== strpos( $editor, 'this.renderUI();' ), 'render_type ui: el editor sólo vuelve a pintar las clases (sin repintar el widget).' );

	// Hoja.
	do_action( 'elementor/frontend/after_register_styles' );
	$style = $GLOBALS['styles']['digitalisimo-f7b-nested-presentation'] ?? array();
	$path  = $root . '/modules/digitalisimo-f7b/assets/css/nested-presentation.css';
	f7b_check( isset( $style['src'] ) && str_ends_with( $style['src'], '/modules/digitalisimo-f7b/assets/css/nested-presentation.css' ) && array() === $style['deps'] && '4.3.0.169' === $style['ver'] && is_file( $path ), 'La hoja se registra (sin dependencias: no arrastra la de otro widget) y no se encola.' );
	f7b_check( array() === $GLOBALS['enqueued'], 'Registrar no encola.' );
	$bytes = filesize( $path );
	f7b_check( $bytes <= 4096, "La hoja cabe en 4 KB ({$bytes} B)." );
	do_action( 'elementor/preview/enqueue_styles' );
	f7b_check( array( 'digitalisimo-f7b-nested-presentation' ) === $GLOBALS['enqueued'], 'En la vista previa del editor se encola siempre, para verla en vivo.' );
	$css = (string) file_get_contents( $path );
	f7b_check( ! preg_match( '/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|url\(|!important|--e-global/i', $css ), 'Sin colores propios, url(), !important ni variables globales del kit.' );
	foreach ( array( 'pills', 'underline', 'boxed', 'minimal' ) as $name ) {
		f7b_check( 1 <= preg_match_all( '/digi-nested-' . $name . '\b[^{]*\[aria-selected=true\][^{]*\{[^}]*(box-shadow|text-decoration-line)/', $css ) && 1 <= preg_match_all( '/digi-nested-' . $name . '\b[^{]*\[open\][^{]*\{[^}]*(box-shadow|text-decoration-line)/', $css ), "{$name}: pestaña activa y elemento abierto marcados con algo más que color." );
	}

	// Línea base: 4.3.0.168.
	$zip  = '';
	$want = 'digitalisimo-elements-4.3.0.168.zip';
	foreach ( array( (string) getenv( 'DIGI_BASELINE_ZIP' ), dirname( $root ) . '/dist/' . $want, dirname( $root ) . '/rollback/' . $want ) as $candidate ) {
		if ( '' !== $candidate && is_file( $candidate ) ) {
			$zip = $candidate;
			break;
		}
	}
	if ( '' === $zip || ! class_exists( 'ZipArchive' ) ) {
		echo "Aviso: sin el ZIP de 4.3.0.168 (dist/, rollback/ o DIGI_BASELINE_ZIP) se omite la comparación byte a byte con el núcleo publicado.\n";
	} else {
		$archive = new \ZipArchive();
		f7b_check( true === $archive->open( $zip ), 'Se abre el ZIP de 4.3.0.168.' );
		$same = 0;
		foreach ( array(
			'elementor/modules/nested-tabs/widgets/nested-tabs.php',
			'elementor/modules/nested-accordion/widgets/nested-accordion.php',
			'elementor/modules/nested-elements/base/widget-nested-base.php',
			'elementor/assets/css/widget-nested-tabs.min.css',
			'elementor/assets/css/widget-nested-tabs-rtl.min.css',
			'elementor/assets/css/widget-nested-accordion.min.css',
			'elementor/assets/css/widget-nested-accordion-rtl.min.css',
			'elementor/assets/css/templates/widget-nested-tabs.min.css',
			'elementor/assets/css/templates/widget-nested-tabs-rtl.min.css',
			'elementor/includes/base/element-base.php',
			'elementor/includes/base/widget-base.php',
			'elementor/includes/conditions.php',
			'elementor/core/base/elements-iteration-actions/assets.php',
			'elementor/core/files/css/base.php',
			'elementor/assets/js/editor.js',
		) as $file ) {
			$old = $archive->getFromName( 'digitalisimo-elements/' . $file );
			f7b_check( false !== $old && $old === file_get_contents( $root . '/' . $file ), "Igual que 4.3.0.168: {$file}." );
			++$same;
		}
		$archive->close();
		echo "Comparado con {$zip}: {$same} archivos del núcleo idénticos.\n";
	}

	echo 'DIGITALÍSIMO Elements: presentación de anidados (frente G) en verde (' . $GLOBALS['f7b_passed'] . " comprobaciones; hoja de {$bytes} B).\n";
}
