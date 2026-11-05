<?php
/**
 * Código resaltado (`digitalisimo-code-highlight`, C2 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c2.md, frente
 * A), el widget propio que sustituye al Code Highlight de PRO, y su mapa `modules/digitalisimo-convert/maps/code-highlight.php`:
 * - registro y recursos: hoja propia ≤ 4 KB (temas como variables, foco visible, movimiento reducido, sin el kit,
 *   `url()` ni clases del proveedor) y script ≤ 6 KB que carga Prism del plugin al acercarse el bloque (sin jQuery, sin
 *   CDN, sin clipboard.js ni la barra de Prism, sólo los complementos que el bloque usa);
 * - controles: se ejecutan los `register_controls()` de PRO y del widget propio con los mismos dobles y se comparan uno
 *   a uno (nombre, tipo, valor por defecto, valor de retorno, opciones, condiciones, unidades, rangos, dinámicos);
 * - paleta: cada tema reproduce los colores efectivos de PRO por grupo de tokens;
 * - marcado del servidor: código escapado por completo en `<code>`, idioma saneado, números de línea, líneas
 *   resaltadas, ajuste, botón Copiar oculto hasta que el script lo active, sin nada con el código vacío;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, completos, responsivos, `__dynamic__`,
 *   CSS personalizado con clases de PRO): ningún `pro.unmapped`, cada clave cae en un control propio.
 */
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public $controls = array();
		public $groups   = array();
		public $sections = array();
		public function get_settings_for_display( $key = null ) { return null === $key ? $this->settings : ( $this->settings[ $key ] ?? null ); }
		public function start_controls_section( $name, $args ) { $this->sections[ $name ] = $args; }
		public function end_controls_section() {}
		public function add_control( $name, $args ) { $this->controls[ $name ] = $args; }
		public function add_responsive_control( $name, $args ) { $this->controls[ $name ] = $args + array( 'responsive' => true ); }
		public function add_group_control( $type, $args ) { $this->groups[ $args['name'] ] = array( 'type' => $type ) + $args; }
		public function print_unescaped_setting( $key ) { echo $this->settings[ $key ]; }
	}
	class Controls_Manager { const TEXT = 'text'; const SELECT = 'select'; const SELECT2 = 'select2'; const SWITCHER = 'switcher'; const SLIDER = 'slider'; const CODE = 'code'; const TAB_STYLE = 'style'; }
}
namespace Elementor\Modules\DynamicTags {
	class Module { const TEXT_CATEGORY = 'text'; }
}
namespace ElementorPro {
	class Plugin {
		public static function elementor() { return (object) array( 'preview' => new class() { public function is_preview_mode() { return false; } }, 'experiments' => new class() { public function is_feature_active( $f ) { return false; } } ); }
	}
}
namespace ElementorPro\Base {
	class Base_Widget extends \Elementor\Widget_Base {}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DIGITALISIMO_ELEMENTS_FILE', '/sitio/wp-content/plugins/digitalisimo-elements/pro-elements.php' );
	$GLOBALS['ch_filters'] = array();
	function esc_html__( $v ) { return $v; }
	function esc_attr__( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
	// Como WordPress: sin volver a codificar las entidades que ya trae el valor.
	function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8', false ); }
	function esc_url( $v ) { return preg_match( '~^https?://~', (string) $v ) ? htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) : ''; }
	function plugins_url( $path, $file ) { return 'https://sitio.test/wp-content/plugins/digitalisimo-elements/' . ltrim( $path, '/' ); }
	function apply_filters( $hook, $value ) { return isset( $GLOBALS['ch_filters'][ $hook ] ) ? call_user_func( $GLOBALS['ch_filters'][ $hook ], $value ) : $value; }
	function add_filter() {}
	function check_ch( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }

	$root = __DIR__ . '/../digitalisimo-elements/';
	require $root . 'modules/code-highlight/widgets/code-highlight.php';
	require $root . 'modules/digitalisimo-content/class-module.php';
	\Digitalisimo\Elements\Content\Module::init();
	require $root . 'modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Content\Module as Content;
	use Digitalisimo\Elements\Content\Widgets\Code_Highlight;
	use Digitalisimo\Elements\Convert\Converter;
	use Digitalisimo\Elements\Convert\Registry;

	check_ch( Code_Highlight::class === Content::load( 'digitalisimo-code-highlight' ), 'El módulo de contenido carga el Código resaltado.' );
	$render = static function ( array $settings ) {
		$widget           = new Code_Highlight();
		$widget->settings = $settings;
		ob_start();
		( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		return (string) ob_get_clean();
	};

	// 1. Registro y recursos.
	$widget = new Code_Highlight();
	check_ch( 'digitalisimo-code-highlight' === $widget->get_name() && 'Código resaltado' === $widget->get_title() && array( 'digitalisimo' ) === $widget->get_categories(), 'Nombre, título y categoría propios.' );
	check_ch( array( 'digitalisimo-code-highlight' ) === $widget->get_style_depends() && array( 'digitalisimo-code-highlight' ) === $widget->get_script_depends(), 'La hoja y el script propios (nada de Prism en la carga inicial).' );
	$entry = Content::WIDGETS['digitalisimo-code-highlight'];
	check_ch( 'Código resaltado' === Content::TITLES['digitalisimo-code-highlight'] && 'widgets/class-code-highlight.php' === $entry['file'] && Code_Highlight::class === $entry['class'], 'El módulo lo declara con su archivo, su clase y su título.' );
	$css = (string) file_get_contents( $root . $entry['css'] );
	$js  = (string) file_get_contents( $root . $entry['js'] );
	check_ch( strlen( $css ) > 0 && strlen( $css ) <= 4096, 'La hoja pesa ' . strlen( $css ) . ' B (tope 4 096).' );
	check_ch( strlen( $js ) > 0 && strlen( $js ) <= 6144, 'El script pesa ' . strlen( $js ) . ' B (tope 6 144).' );
	check_ch( ! str_contains( $css, '--e-global' ) && ! preg_match( '/url\(/i', $css ) && ! str_contains( $css, 'elementor' ) && ! str_contains( $css, 'prismjs-' ) && ! str_contains( $css, 'code-toolbar' ), 'La hoja no usa el kit, url(), clases del proveedor ni las de PRO.' );
	check_ch( str_contains( $css, '.digi-code__copy:focus-visible{outline:2px solid;outline-offset:2px}' ) && str_contains( $css, 'min-height:24px;min-width:24px' ) && str_contains( $css, '@media (prefers-reduced-motion:reduce){.digi-code__copy{transition:none}}' ), 'Botón Copiar con foco visible, 24 × 24 px como mínimo y sin transición con movimiento reducido.' );
	check_ch( str_contains( $css, '.digi-code:is(:hover,:focus-within) .digi-code__copy{opacity:1}' ), 'El botón Copiar aparece con el puntero encima y con el foco dentro (como la barra de PRO).' );
	check_ch( ! preg_match( '/jQuery|\$\(|cdnjs|https?:|ClipboardJS|clipboard\.min|toolbar|fetch\(|XMLHttpRequest|elementorProFrontend/i', $js ), 'Script sin jQuery, sin URL absolutas ni CDN, sin clipboard.js ni la barra de Prism.' );
	foreach ( array( 'components/prism-core.min.js', 'plugins/autoloader/prism-autoloader.min.js', 'plugins/normalize-whitespace/prism-normalize-whitespace.min.js', 'plugins/line-numbers/prism-line-numbers.min.js', 'plugins/line-highlight/prism-line-highlight.min.js' ) as $file ) {
		check_ch( str_contains( $js, "'" . $file . "'" ) && is_file( $root . 'assets/lib/prism/' . $file ), "El script pide $file del plugin, que existe." );
	}
	check_ch( str_contains( $js, "rootMargin:'100% 0px'" ) && str_contains( $js, 'IntersectionObserver' ) && str_contains( $js, ".manual=true" ) && str_contains( $js, "r.querySelector('pre.line-numbers')" ) && str_contains( $js, "r.querySelector('pre[data-line]')" ), 'Prism se carga al acercarse el bloque, en modo manual, y los complementos sólo si el bloque los usa.' );
	check_ch( str_contains( $js, "'data-autoloader-path'" ) && str_contains( $js, "frontend/element_ready/global" ), 'Las gramáticas se piden a la carpeta del plugin; el editor reinicia el bloque al pintarlo.' );
	$source = '';
	foreach ( token_get_all( (string) file_get_contents( $root . 'modules/digitalisimo-content/' . $entry['file'] ) ) as $token ) {
		$source .= is_array( $token ) ? ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ? '' : $token[1] ) : $token;
	}
	check_ch( '' !== $source && ! preg_match( '/jquery|wp_enqueue_script|wp_register_script|<xmp|ElementorPro\\\\|Lazy_Libs|prismjs_|cdnjs/i', $source ), 'El widget no encola scripts a mano, no usa <xmp>, clases de PRO ni el cargador de PRO.' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	foreach ( array( 'modules/digitalisimo-content/widgets/class-code-highlight.php', $entry['css'], $entry['js'], 'modules/digitalisimo-convert/maps/code-highlight.php' ) as $file ) {
		check_ch( ! preg_match( $guard, (string) file_get_contents( $root . $file ) ), "$file pasa la guarda de build-packages.sh." );
	}

	// 2. Los controles de PRO, uno a uno, en el widget propio.
	$pro = new \ElementorPro\Modules\CodeHighlight\Widgets\Code_Highlight();
	( new ReflectionMethod( $pro, 'register_controls' ) )->invoke( $pro );
	( new ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );
	check_ch( array_keys( $pro->controls ) === array_keys( $widget->controls ) && array_keys( $pro->sections ) === array_keys( $widget->sections ) && array() === $pro->groups && array() === $widget->groups, 'Los mismos controles en el mismo orden y en la misma sección: ' . implode( ', ', array_keys( $widget->controls ) ) );
	foreach ( $pro->controls as $name => $a ) {
		$b = $widget->controls[ $name ];
		foreach ( array( 'type', 'default', 'return_value', 'multiple', 'condition', 'conditions', 'responsive', 'size_units', 'range', 'tablet_default', 'mobile_default', 'placeholder', 'separator', 'dynamic' ) as $key ) {
			check_ch( ( $a[ $key ] ?? null ) === ( $b[ $key ] ?? null ), "$name: «{$key}» distinto de PRO (" . json_encode( $a[ $key ] ?? null ) . ' / ' . json_encode( $b[ $key ] ?? null ) . ').' );
		}
		check_ch( array_keys( (array) ( $a['options'] ?? array() ) ) === array_keys( (array) ( $b['options'] ?? array() ) ), "$name: las mismas opciones que PRO." );
		check_ch( isset( $a['selectors'] ) === isset( $b['selectors'] ) && count( (array) ( $a['selectors'] ?? array() ) ) === count( (array) ( $b['selectors'] ?? array() ) ), "$name: aplica su estilo como PRO." );
	}
	check_ch( 'Visual Basic + VBA' === $widget->controls['language']['options']['visual-basic,'], 'La clave de idioma `visual-basic,` de PRO se conserva tal cual.' );
	check_ch( array( '{{WRAPPER}} .digi-code__pre' => 'height: {{SIZE}}{{UNIT}};' ) === $widget->controls['height']['selectors'] && array( '{{WRAPPER}} pre, {{WRAPPER}} code, {{WRAPPER}} .line-numbers .line-numbers-rows' => 'font-size: {{SIZE}}{{UNIT}};' ) === $widget->controls['font_size']['selectors'], 'Altura en el bloque y tamaño de fuente en el código y los números de línea.' );
	$GLOBALS['ch_filters'] = array(
		'elementor_pro/code_highlight/languages'          => static function ( $l ) { return $l + array( 'yaml' => 'YAML' ); },
		'digitalisimo_elements_code_highlight_languages' => static function ( $l ) { unset( $l['perl'] ); return $l; },
	);
	$langs = Code_Highlight::languages();
	check_ch( isset( $langs['yaml'] ) && ! isset( $langs['perl'] ), 'Los idiomas pasan por el filtro de PRO y después por el propio.' );
	$GLOBALS['ch_filters'] = array();
	$map = require $root . 'modules/digitalisimo-convert/maps/code-highlight.php';
	foreach ( array_keys( $pro->controls ) as $name ) {
		check_ch( in_array( $name, $map['keep'], true ), "$name: el mapa lo conserva con su nombre." );
	}
	foreach ( $map['defaults'] as $name => $value ) {
		check_ch( $widget->controls[ $name ]['default'] === $value && $pro->controls[ $name ]['default'] === $value, "$name: el valor por defecto del mapa es el de PRO y el del control propio." );
	}
	check_ch( 'ready' === $map['status'] && 'hide' === $map['panel'] && 'digitalisimo-code-highlight' === $map['target'] && 'code-highlight' === $map['source'], 'Mapa listo, con el widget de PRO fuera del panel.' );

	// 3. Paleta: colores efectivos de los temas de PRO (medidos en su hoja con Chrome).
	check_ch( array( 'default', 'dark', 'okaidia', 'solarizedlight', 'tomorrow', 'twilight' ) === array_keys( Code_Highlight::THEMES ) && array_keys( Code_Highlight::THEMES ) === array_keys( $widget->controls['theme']['options'] ), 'Una paleta por cada tema del control.' );
	$expect = array(
		'default'        => array( '0' => '#000', 'bg' => '#f5f2f0', 'a' => '#708090', 'r' => '#07a', 'j' => '#690' ),
		'dark'           => array( '0' => '#fff', 'bg' => '#4c3f33', 'bd' => '.3em solid #7a6651', 'g' => '#f00', 'po' => '.7' ),
		'okaidia'        => array( 'bg' => '#272822', 'd' => '#ae81ff', 'r' => '#66d9ef', 'cs' => '#f8f8f2' ),
		'solarizedlight' => array( 'bg' => '#fdf6e3', 'eb' => '#eee8d5', 'r' => '#859900' ),
		'tomorrow'       => array( 'bg' => '#2d2d2d', 'b' => '#e2777a', 'x' => '#6196cc', 'no' => '1' ),
		'twilight'       => array( 'bg' => '#141414', 'lb' => '1px dashed #545454', 'lp' => '.8em', 'sel' => '#eeeeee26' ),
	);
	foreach ( $expect as $theme => $values ) {
		foreach ( $values as $key => $value ) {
			check_ch( $value === ( Code_Highlight::THEMES[ $theme ][ $key ] ?? null ), "$theme: --dc-$key es $value como en PRO." );
		}
	}
	preg_match_all( '/var\(--dc-([a-z0-9]+)/', $css, $used );
	$keys = array();
	foreach ( Code_Highlight::THEMES as $palette ) {
		$keys = array_merge( $keys, array_keys( $palette ) );
	}
	check_ch( ! array_diff( array_map( 'strval', array_unique( $keys ) ), array_unique( $used[1] ) ), 'Cada variable de las paletas la usa la hoja.' );
	check_ch( ! array_diff( array_unique( $used[1] ), array_map( 'strval', array_unique( $keys ) ) ), 'La hoja no usa variables que ninguna paleta defina.' );

	// 4. Marcado del servidor.
	check_ch( '' === $render( array( 'code' => '' ) ) && '' === $render( array() ), 'Sin código no se pinta nada (como PRO).' );
	$defaults = $map['defaults'];
	$html     = $render( $defaults );
	check_ch( str_starts_with( $html, '<div class="digi-code digi-code--default digi-code--copy digi-code--sel" style="--dc-0:#000;--dc-bg:#f5f2f0;--dc-ts:0 1px #fff;' ) && str_contains( $html, ' data-digi-prism="https://sitio.test/wp-content/plugins/digitalisimo-elements/assets/lib/prism/">' ), 'Raíz con el tema, la paleta en línea y la carpeta de Prism del plugin: ' . substr( $html, 0, 200 ) );
	check_ch( str_contains( $html, '<pre class="digi-code__pre language-javascript line-numbers"><code class="language-javascript">console.log( &#039;Code is Poetry&#039; );</code></pre>' ), 'Bloque con el idioma y los números de línea, el código escapado sin espacios añadidos.' );
	check_ch( str_contains( $html, '<button type="button" class="digi-code__copy" aria-live="polite" data-digi-done="Copiado" data-digi-fail="Pulsa Ctrl+C para copiar" hidden>Copiar</button></div>' ), 'Botón Copiar propio, oculto hasta que el script lo active.' );
	$evil = "<?php echo '&amp; <b>'; ?>\n</xmp><script>alert(1)</script>";
	$html = $render( array( 'code' => $evil, 'language' => 'php"><img', 'theme' => 'nada', 'line_numbers' => '', 'copy_to_clipboard' => '', 'highlight_lines' => ' 1, 3-4" onload="x', 'word_wrap' => 'word-wrap' ) );
	check_ch( str_contains( $html, '<code class="language-phpimg">&lt;?php echo &#039;&amp;amp; &lt;b&gt;&#039;; ?&gt;' . "\n" . '&lt;/xmp&gt;&lt;script&gt;alert(1)&lt;/script&gt;</code>' ), 'El código se escapa entero, también las entidades que ya trae; el idioma, saneado: ' . $html );
	check_ch( str_contains( $html, 'class="digi-code digi-code--default digi-code--wrap digi-code--sel"' ) && str_contains( $html, '<pre class="digi-code__pre language-phpimg" data-line="1, 3-4&quot; onload=&quot;x">' ) && ! str_contains( $html, 'digi-code__copy' ) && ! str_contains( $html, '<script' ), 'Tema desconocido → el de PRO por defecto; ajuste de línea; líneas resaltadas escapadas; sin botón Copiar.' );
	$html = $render( array( 'code' => 'x', 'language' => 'visual-basic,', 'theme' => 'twilight', 'line_numbers' => 'line-numbers', 'copy_to_clipboard' => 'copy-to-clipboard', 'highlight_lines' => '', 'word_wrap' => '' ) );
	check_ch( str_contains( $html, 'class="digi-code digi-code--twilight digi-code--copy digi-code--sel"' ) && str_contains( $html, '--dc-lb:1px dashed #545454;' ) && str_contains( $html, '<pre class="digi-code__pre language-visual-basic line-numbers"><code class="language-visual-basic">x</code>' ) && ! str_contains( $html, 'data-line' ), 'Twilight con su paleta; `visual-basic,` pide la gramática visual-basic; sin líneas resaltadas no hay data-line.' );
	$html = $render( array( 'code' => 'x', 'language' => 'go', 'theme' => 'tomorrow' ) );
	check_ch( str_contains( $html, 'class="digi-code digi-code--tomorrow"' ) && ! str_contains( $html, '--dc-sel' ), 'Tomorrow sin color de selección propio (PRO no lo fijaba).' );

	// Espacios en blanco como los dejaba PRO (su plantilla ponía cinco tabuladores delante del código y el complemento de
	// Prism quitaba la sangría común); comprobado contra el complemento real con 400 entradas al azar al escribirlo.
	$n = array(
		"// Saludo\n\tconst a = 1;\n\tif ( a ) {\n\t\tb();\n\t}" => "// Saludo\nconst a = 1;\nif ( a ) {\n\tb();\n}",
		"\n\n  uno  \r\n    dos\t\r\n\n" => "uno\n  dos",
		"a\nb" => "a\nb",
		"\t\t\t\t\t\tx\n\ty" => "x\ny",
		"  x\n\ty" => "x\ny",
		"   " => '',
	);
	foreach ( $n as $in => $expected ) {
		check_ch( $expected === Code_Highlight::normalize( $in ), 'normalize(' . json_encode( $in ) . ') = ' . json_encode( Code_Highlight::normalize( $in ) ) . ', no ' . json_encode( $expected ) );
	}
	check_ch( str_contains( $render( array( 'code' => '   ' ) ), '<code class="language-javascript"></code>' ), 'Un código sólo de espacios pinta el bloque vacío, como PRO.' );

	// El marcado de la prueba en Chrome (tests/fixtures/pro-code-highlight.html) es el de render().
	$fixture = (string) file_get_contents( __DIR__ . '/fixtures/pro-code-highlight.html' );
	$fixture_cases = array(
		'top'   => array( 'code' => "// Saludo\n\tconst re = /a+b/g;\n\tfunction f( a ) {\n\t\treturn 'x' + a;\n\t}", 'line_numbers' => '', 'theme' => 'default' ),
		'lines' => array( 'code' => "<?php\n\$a = 1 < 2 && 3 > 2;\necho \"<p>&amp;</p>\";\n?>\n<b>fin</b>", 'language' => 'php', 'theme' => 'okaidia', 'line_numbers' => '', 'copy_to_clipboard' => '', 'highlight_lines' => '2, 4-5' ),
		'far'   => array( 'code' => "const a = 1;\nconst b = 2;\nconst c = a + b;", 'theme' => 'twilight', 'highlight_lines' => '2' ),
	);
	foreach ( $fixture_cases as $id => $settings ) {
		$html = str_replace( 'https://sitio.test/wp-content/plugins/digitalisimo-elements/', '../../digitalisimo-elements/', $render( $settings + $map['defaults'] ) );
		check_ch( str_contains( $fixture, '<div class="elementor-widget" data-id="' . $id . '">' . $html . '</div>' ), "La fixture de Chrome tiene el marcado de render() para «{$id}»." );
	}

	// 5. Conversión con ajustes reales.
	$cases = array(
		'defaults' => array(),
		'full'     => array(
			'language' => 'php', 'code' => "<?php\n\techo 'Hola';", 'line_numbers' => '', 'copy_to_clipboard' => '', 'highlight_lines' => '2', 'word_wrap' => 'word-wrap', 'theme' => 'okaidia',
			'height' => array( 'unit' => 'px', 'size' => 300, 'sizes' => array() ), 'height_mobile' => array( 'unit' => 'vh', 'size' => 40, 'sizes' => array() ),
			'font_size' => array( 'unit' => 'px', 'size' => 15, 'sizes' => array() ), 'font_size_tablet' => array( 'unit' => 'em', 'size' => 0.9, 'sizes' => array() ),
			'custom_css' => "selector .prismjs-okaidia .highlight-height{max-width:100%}\n.elementor-widget-code-highlight .copy-to-clipboard .code-toolbar{margin:0}\nselector .word-wrap code{color:red}",
			'_css_classes' => 'mi-codigo',
		),
		'vb'       => array( 'language' => 'visual-basic,', 'theme' => 'twilight' ),
		'dynamic'  => array( 'code' => '', '__dynamic__' => array( 'code' => '[elementor-tag id="d1" name="post-excerpt" settings="%7B%7D"]', 'highlight_lines' => '[elementor-tag id="d2" name="post-custom-field" settings="%7B%7D"]' ) ),
	);
	$tree = array( array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(), 'elements' => array() ) );
	foreach ( $cases as $id => $settings ) {
		$tree[0]['elements'][] = array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'code-highlight', 'settings' => $settings, 'elements' => array() );
	}
	$converted = Converter::tree( $tree, $log, false );
	check_ch( count( $cases ) === count( $log ), 'Con el mapa listo, se convierte cada Code Highlight del árbol (también fuera de las pruebas).' );
	$out   = array();
	$codes = array();
	foreach ( $converted[0]['elements'] as $element ) {
		check_ch( 'digitalisimo-code-highlight' === $element['widgetType'] && 'widget' === $element['elType'], $element['id'] . ': pasa al Código resaltado con el mismo id.' );
		$out[ $element['id'] ] = $element['settings'];
		foreach ( array_keys( $element['settings'] ) as $key ) {
			$base = preg_replace( '/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/', '', (string) $key );
			check_ch( isset( $widget->controls[ $base ] ) || in_array( $key, array( 'custom_css', '_css_classes', '__dynamic__', '_digitalisimo_pro' ), true ), "{$element['id']}: $key no cae en un control propio." );
		}
	}
	foreach ( $log as $entry ) {
		foreach ( $entry['codes'] as $code ) {
			$codes[] = $entry['element'] . ':' . $code['code'] . ':' . $code['key'];
		}
	}
	check_ch( array( 'full:pro.css:custom_css' ) === $codes, 'Ningún pro.unmapped, pro.dropped ni aproximación; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	check_ch( 'javascript' === $d['language'] && 'line-numbers' === $d['line_numbers'] && 'copy-to-clipboard' === $d['copy_to_clipboard'] && 'default' === $d['theme'] && '' === $d['word_wrap'] && "console.log( 'Code is Poetry' );" === $d['code'] && 'code-highlight' === $d['_digitalisimo_pro'], 'Un Code Highlight sin cambios llega con los valores por defecto visibles de PRO.' );
	check_ch( $render( $d ) === $render( $map['defaults'] ), 'Lo convertido se pinta igual que los valores por defecto del widget propio.' );
	$f = $out['full'];
	check_ch( 'php' === $f['language'] && '' === $f['line_numbers'] && '' === $f['copy_to_clipboard'] && '2' === $f['highlight_lines'] && 'word-wrap' === $f['word_wrap'] && 'okaidia' === $f['theme'] && 300 === $f['height']['size'] && 'vh' === $f['height_mobile']['unit'] && 15 === $f['font_size']['size'] && 0.9 === $f['font_size_tablet']['size'] && 'mi-codigo' === $f['_css_classes'], 'Ajustes y dispositivos pasan a su control.' );
	check_ch( "selector .digi-code--okaidia .digi-code__pre{max-width:100%}\n.elementor-widget-digitalisimo-code-highlight .digi-code--copy .digi-code{margin:0}\nselector .digi-code--wrap code{color:red}" === $f['custom_css'], 'CSS personalizado con las clases propias: ' . $f['custom_css'] );
	check_ch( str_contains( $render( $f ), '<div class="digi-code digi-code--okaidia digi-code--wrap"' ) && str_contains( $render( $f ), '<pre class="digi-code__pre language-php" data-line="2"><code class="language-php">&lt;?php' . "\necho &#039;Hola&#039;;</code>" ), 'Lo convertido se pinta con su tema, su idioma y sus líneas resaltadas.' );
	check_ch( 'visual-basic,' === $out['vb']['language'] && str_contains( $render( $out['vb'] ), 'language-visual-basic' ), 'La clave `visual-basic,` pasa tal cual y pinta su gramática.' );
	check_ch( array( 'code', 'highlight_lines' ) === array_keys( $out['dynamic']['__dynamic__'] ), 'Las etiquetas dinámicas siguen a su control.' );
	check_ch( $converted === Converter::tree( $converted, $again, true ) && array() === $again, 'Lo convertido no se convierte otra vez.' );
	check_ch( 'digitalisimo-code-highlight' === ( Registry::map( 'code-highlight' )['target'] ?? '' ), 'El registro de mapas lo ofrece a Migración y a los kits.' );

	echo 'DIGITALÍSIMO Elements: Código resaltado (' . count( $widget->controls ) . ' controles, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B, ' . count( Code_Highlight::THEMES ) . ' temas) y su mapa desde el Code Highlight de PRO (' . count( $cases ) . " casos) validados.\n";
}
