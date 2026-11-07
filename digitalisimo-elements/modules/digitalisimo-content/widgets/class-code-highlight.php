<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Código resaltado (`digitalisimo-code-highlight`): sustituye al widget «Code Highlight» de PRO
 * (`modules/code-highlight/widgets/code-highlight.php`, mapa `modules/digitalisimo-convert/maps/code-highlight.php`).
 * Repite sus nueve controles con el mismo nombre, valor por defecto, opciones y condiciones; el marcado, las clases y los
 * recursos son propios.
 *
 * Marcado: `div.digi-code.digi-code--<tema>` con la paleta del tema en línea (variables `--dc-*`, sólo las del tema
 * elegido), `pre.digi-code__pre.language-<idioma>` (con `line-numbers` y `data-line` cuando corresponde) y el código
 * escapado en `<code>`: sin JavaScript se lee igual. El botón «Copiar» es un `<button>` propio, oculto hasta que el
 * script comprueba que puede copiar.
 *
 * Resaltado: Prism 1.23.0 del plugin (`assets/lib/prism`, MIT), que el script `code-highlight.js` carga sólo en las
 * páginas con el widget y cuando el bloque se acerca a la pantalla: núcleo, cargador de gramáticas (`components/`),
 * espacios en blanco y, sólo si el bloque los usa, números de línea y líneas resaltadas. Ninguna petición a terceros.
 *
 * Diferencias declaradas con PRO: botón «Copiar» propio (en lo convertido de PRO, con los textos de la barra de Prism:
 * «Copy», «Copied!» y «Press Ctrl+C to copy») de 24 × 24 px como mínimo, con foco visible, sin clipboard.js
 * ni la barra de Prism (copia el código sin las líneas resaltadas); el código se escapa en `<code>` (PRO usaba `<xmp>`,
 * que un `</xmp>` en el código rompía); el color de la selección de texto es el del tema sólo donde PRO lo fijaba.
 */
class Code_Highlight extends \Elementor\Widget_Base {

	/**
	 * Paleta de cada tema de PRO (sus colores efectivos, medidos en su hoja): `0` texto, `bg` fondo, `ts` sombra del
	 * texto, `bd`/`br`/`sh` borde, radio y sombra del bloque, `sel` selección, `no`/`po` opacidad del espacio de nombres y
	 * de la puntuación, `cs` cadenas de CSS, `ob`/`eb` fondo de operadores y entidades, `l*` líneas resaltadas y `a`–`x`
	 * los grupos de tokens de la hoja (un grupo que falta hereda el color, como en PRO).
	 */
	const THEMES = array(
		'default'        => array( '0' => '#000', 'bg' => '#f5f2f0', 'ts' => '0 1px #fff', 'sel' => '#b3d4fc', 'cs' => '#9a6e3a', 'ob' => '#ffffff80', 'a' => '#708090', 'c' => '#999', 'd' => '#905', 'e' => '#905', 'f' => '#905', 'g' => '#905', 'h' => '#690', 'i' => '#690', 'j' => '#690', 'k' => '#690', 'l' => '#9a6e3a', 'm' => '#9a6e3a', 'n' => '#9a6e3a', 'o' => '#e90', 'p' => '#07a', 'q' => '#07a', 'r' => '#07a', 's' => '#e90', 't' => '#e90', 'u' => '#dd4a68', 'v' => '#dd4a68' ),
		'dark'           => array( '0' => '#fff', 'bg' => '#4c3f33', 'ts' => '0 -.1em .2em #000', 'bd' => '.3em solid #7a6651', 'br' => '.5em', 'sh' => 'inset 1px 1px .5em #000', 'po' => '.7', 'cs' => '#f4b73d', 'a' => '#997f66', 'd' => '#d1939e', 'e' => '#d1939e', 'f' => '#d1939e', 'g' => '#f00', 'h' => '#bce051', 'i' => '#bce051', 'j' => '#bce051', 'k' => '#bce051', 'l' => '#f4b73d', 'm' => '#f4b73d', 'n' => '#f4b73d', 'o' => '#f4b73d', 'p' => '#d1939e', 'q' => '#d1939e', 'r' => '#d1939e', 's' => '#e90', 't' => '#e90' ),
		'okaidia'        => array( '0' => '#f8f8f2', 'bg' => '#272822', 'ts' => '0 1px #0000004d', 'br' => '.3em', 'cs' => '#f8f8f2', 'a' => '#8292a2', 'c' => '#f8f8f2', 'd' => '#ae81ff', 'e' => '#f92672', 'f' => '#f92672', 'g' => '#f92672', 'h' => '#a6e22e', 'i' => '#a6e22e', 'j' => '#a6e22e', 'k' => '#a6e22e', 'l' => '#f8f8f2', 'm' => '#f8f8f2', 'n' => '#f8f8f2', 'o' => '#f8f8f2', 'p' => '#e6db74', 'q' => '#e6db74', 'r' => '#66d9ef', 's' => '#fd971f', 't' => '#fd971f', 'u' => '#e6db74', 'v' => '#e6db74' ),
		'solarizedlight' => array( '0' => '#657b83', 'bg' => '#fdf6e3', 'br' => '.3em', 'sel' => '#073642', 'eb' => '#eee8d5', 'a' => '#93a1a1', 'c' => '#586e75', 'd' => '#268bd2', 'e' => '#268bd2', 'f' => '#268bd2', 'g' => '#268bd2', 'h' => '#2aa198', 'i' => '#2aa198', 'j' => '#2aa198', 'k' => '#2aa198', 'l' => '#657b83', 'n' => '#2aa198', 'o' => '#cb4b16', 'p' => '#859900', 'q' => '#859900', 'r' => '#859900', 's' => '#cb4b16', 't' => '#cb4b16', 'u' => '#b58900', 'v' => '#b58900' ),
		'tomorrow'       => array( '0' => '#ccc', 'bg' => '#2d2d2d', 'no' => '1', 'a' => '#999', 'b' => '#e2777a', 'c' => '#ccc', 'd' => '#f08d49', 'e' => '#f8c555', 'f' => '#e2777a', 'g' => '#e2777a', 'h' => '#e2777a', 'i' => '#cc99cd', 'j' => '#7ec699', 'k' => '#008000', 'l' => '#67cdcc', 'm' => '#67cdcc', 'n' => '#67cdcc', 'o' => '#7ec699', 'p' => '#cc99cd', 'q' => '#7ec699', 'r' => '#cc99cd', 's' => '#cc99cd', 't' => '#7ec699', 'u' => '#f8c555', 'v' => '#f08d49', 'w' => '#999', 'x' => '#6196cc' ),
		'twilight'       => array( '0' => '#fff', 'bg' => '#141414', 'ts' => '0 -.1em .2em #000', 'bd' => '.3em solid #545454', 'br' => '.5em', 'sh' => 'inset 1px 1px .5em #000', 'po' => '.7', 'sel' => '#eeeeee26', 'lp' => '.8em', 'lh' => '#5454541a', 'lb' => '1px dashed #545454', 'lm' => '.75em', 'lc' => '#8693a6', 'a' => '#777', 'd' => '#ce6849', 'e' => '#f9ed99', 'f' => '#ce6849', 'g' => '#ce6849', 'h' => '#909e6a', 'i' => '#f9ed99', 'j' => '#909e6a', 'k' => '#909e6a', 'l' => '#909e6a', 'm' => '#909e6a', 'n' => '#909e6a', 'o' => '#909e6a', 'p' => '#7385a5', 'q' => '#909e6a', 'r' => '#f9ed99', 's' => '#e8c062', 't' => '#e8c062' ),
	);

	public function get_name() {
		return 'digitalisimo-code-highlight';
	}

	public function get_title() {
		return esc_html__( 'Código resaltado', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-code-highlight';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'código', 'resaltado', 'sintaxis', 'code', 'highlight', 'syntax', 'javascript', 'css', 'php', 'html', 'java' );
	}

	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	/** Idiomas del control: los de PRO, con su filtro (`elementor_pro/code_highlight/languages`) y uno propio después. */
	public static function languages(): array {
		$languages = array(
			'markup'        => 'Markup',
			'html'          => 'HTML',
			'css'           => 'CSS',
			'sass'          => 'Sass (Sass)',
			'scss'          => 'Sass (Scss)',
			'less'          => 'Less',
			'javascript'    => 'JavaScript',
			'typescript'    => 'TypeScript',
			'jsx'           => 'React JSX',
			'tsx'           => 'React TSX',
			'php'           => 'PHP',
			'ruby'          => 'Ruby',
			'json'          => 'JSON + Web App Manifest',
			'http'          => 'HTTP',
			'xml'           => 'XML',
			'svg'           => 'SVG',
			'rust'          => 'Rust',
			'csharp'        => 'C#',
			'dart'          => 'Dart',
			'git'           => 'Git',
			'java'          => 'Java',
			'sql'           => 'SQL',
			'go'            => 'Go',
			'kotlin'        => 'Kotlin + Kotlin Script',
			'julia'         => 'Julia',
			'python'        => 'Python',
			'swift'         => 'Swift',
			'bash'          => 'Bash + Shell',
			'scala'         => 'Scala',
			'haskell'       => 'Haskell',
			'perl'          => 'Perl',
			'objectivec'    => 'Objective-C',
			// La misma clave que PRO (con la coma): es la que guardan los sitios.
			'visual-basic,' => 'Visual Basic + VBA',
			'r'             => 'R',
			'c'             => 'C',
			'cpp'           => 'C++',
			'aspnet'        => 'ASP.NET (C#)',
		);
		if ( function_exists( 'apply_filters' ) ) {
			$languages = (array) apply_filters( 'elementor_pro/code_highlight/languages', $languages );
			$languages = (array) apply_filters( 'digitalisimo_elements_code_highlight_languages', $languages );
		}
		return $languages;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Código resaltado', 'pro-elements' ),
			)
		);

		$this->add_control(
			'language',
			array(
				'label'    => esc_html__( 'Idioma', 'pro-elements' ),
				'type'     => Controls_Manager::SELECT2,
				'multiple' => false,
				'options'  => self::languages(),
				'default'  => 'javascript',
			)
		);

		$this->add_control(
			'code',
			array(
				'label'   => esc_html__( 'Código', 'pro-elements' ),
				'type'    => Controls_Manager::CODE,
				'default' => 'console.log( \'Code is Poetry\' );',
				'dynamic' => array(
					'active'     => true,
					'categories' => array( 'text' ),
				),
			)
		);

		$this->add_control(
			'line_numbers',
			array(
				'label'        => esc_html__( 'Números de línea', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'line-numbers',
				'default'      => 'line-numbers',
			)
		);

		$this->add_control(
			'copy_to_clipboard',
			array(
				'label'        => esc_html__( 'Botón para copiar', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'    => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'copy-to-clipboard',
				'default'      => 'copy-to-clipboard',
			)
		);

		$this->add_control(
			'highlight_lines',
			array(
				'label'       => esc_html__( 'Resaltar líneas', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => '1, 3-6',
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'word_wrap',
			array(
				'label'        => esc_html__( 'Ajuste de línea', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'    => esc_html__( 'No', 'pro-elements' ),
				'return_value' => 'word-wrap',
				'default'      => '',
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'     => esc_html__( 'Tema', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default'        => esc_html__( 'Claro', 'pro-elements' ),
					'dark'           => esc_html__( 'Oscuro', 'pro-elements' ),
					'okaidia'        => 'Okaidia',
					'solarizedlight' => 'Solarized Light',
					'tomorrow'       => 'Tomorrow',
					'twilight'       => 'Twilight',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'height',
			array(
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 115,
						'max' => 1000,
					),
					'em'  => array(
						'min' => 6,
						'max' => 50,
					),
					'rem' => array(
						'min' => 6,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-code__pre' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'font_size',
			array(
				'label'      => esc_html__( 'Tamaño de la fuente', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 200,
					),
					'em'  => array(
						'max' => 20,
					),
					'rem' => array(
						'max' => 20,
					),
					'vw'  => array(
						'min'  => 0.1,
						'max'  => 10,
						'step' => 0.1,
					),
				),
				'responsive' => true,
				'selectors'  => array(
					'{{WRAPPER}} pre, {{WRAPPER}} code, {{WRAPPER}} .line-numbers .line-numbers-rows' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Variables en línea con la paleta del tema (sólo las suyas). */
	public static function palette( string $theme ): string {
		$out = '';
		foreach ( self::THEMES[ isset( self::THEMES[ $theme ] ) ? $theme : 'default' ] as $key => $value ) {
			$out .= '--dc-' . $key . ':' . $value . ';';
		}
		return $out;
	}

	/**
	 * El código tal como lo dejaba PRO tras el complemento de espacios en blanco de Prism: PRO lo imprimía con cinco
	 * tabuladores delante (la sangría de su plantilla), así que la sangría común de las demás líneas se quitaba aunque la
	 * primera no tuviera. Mismo orden que el complemento (espacios al final de cada línea, sangría común, inicio y
	 * final), con los saltos de línea que deja el analizador HTML. El complemento, que también se carga, ya no cambia nada.
	 */
	public static function normalize( string $code ): string {
		$text = "\t\t\t\t\t" . str_replace( array( "\r\n", "\r" ), "\n", $code );
		$u    = 1 === preg_match( '//u', $text ) ? 'u' : '';
		$ucp  = $u ? '(*UCP)' : '';
		$text = (string) preg_replace( '/' . $ucp . '\s*?$/m' . $u, '', $text );
		if ( preg_match_all( '/' . $ucp . '^[^\S\n\r]*(?=\S)/m' . $u, $text, $found ) && '' !== $found[0][0] ) {
			$indents = $found[0];
			$lengths = array_map( $u && function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen', $indents );
			asort( $lengths ); // Estable, como el `sort` de JavaScript: el primero de los más cortos.
			$shortest = $indents[ (int) key( $lengths ) ];
			if ( '' !== $shortest ) {
				$text = (string) preg_replace( '/^' . preg_quote( $shortest, '/' ) . '/m' . $u, '', $text );
			}
		}
		$text = (string) preg_replace( '/' . $ucp . '^\s+/' . $u, '', $text );
		return (string) preg_replace( '/' . $ucp . '\s+$/D' . $u, '', $text );
	}

	/** URL de la carpeta de Prism del plugin (con barra final), o '' si no se conoce. */
	public static function prism_url(): string {
		if ( ! defined( 'DIGITALISIMO_ELEMENTS_FILE' ) || ! function_exists( 'plugins_url' ) ) {
			return '';
		}
		return plugins_url( 'assets/lib/prism/', DIGITALISIMO_ELEMENTS_FILE );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$code     = (string) ( $settings['code'] ?? '' );
		if ( '' === $code ) {
			return;
		}
		$theme    = (string) ( $settings['theme'] ?? 'default' );
		$theme    = isset( self::THEMES[ $theme ] ) ? $theme : 'default';
		$language = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $settings['language'] ?? '' ) );
		$language = '' !== $language ? strtolower( $language ) : 'javascript';
		$lines    = trim( (string) ( $settings['highlight_lines'] ?? '' ) );
		$copy     = ! empty( $settings['copy_to_clipboard'] );
		$classes  = array( 'digi-code', 'digi-code--' . $theme );
		if ( ! empty( $settings['word_wrap'] ) ) {
			$classes[] = 'digi-code--wrap';
		}
		if ( $copy ) {
			$classes[] = 'digi-code--copy';
		}
		if ( isset( self::THEMES[ $theme ]['sel'] ) ) {
			$classes[] = 'digi-code--sel';
		}
		$pre = 'digi-code__pre language-' . $language . ( ! empty( $settings['line_numbers'] ) ? ' line-numbers' : '' );

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( self::palette( $theme ) ) . '" data-digi-prism="' . esc_url( self::prism_url() ) . '">';
		echo '<pre class="' . esc_attr( $pre ) . '"' . ( '' !== $lines ? ' data-line="' . esc_attr( $lines ) . '"' : '' ) . '>';
		// El código, normalizado como en PRO, se escapa entero (también las entidades que ya traiga): se lee tal cual, con
		// o sin JavaScript.
		echo '<code class="language-' . esc_attr( $language ) . '">' . htmlspecialchars( self::normalize( $code ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true ) . '</code></pre>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado con htmlspecialchars().
		if ( $copy ) {
			// En lo convertido de PRO (marca del traductor, también en lo ya migrado), los textos del botón de la barra de
			// Prism que pintaba PRO, que no se traducen; en lo nuevo, los propios.
			$texts = '' !== (string) $this->get_settings( '_digitalisimo_pro' )
				? array( 'Copy', 'Copied!', 'Press Ctrl+C to copy' )
				: array( esc_html__( 'Copiar', 'pro-elements' ), esc_attr__( 'Copiado', 'pro-elements' ), esc_attr__( 'Pulsa Ctrl+C para copiar', 'pro-elements' ) );
			echo '<button type="button" class="digi-code__copy" aria-live="polite" data-digi-done="' . esc_attr( $texts[1] ) . '" data-digi-fail="' . esc_attr( $texts[2] ) . '" hidden>' . esc_html( $texts[0] ) . '</button>';
		}
		echo '</div>';
	}
}
