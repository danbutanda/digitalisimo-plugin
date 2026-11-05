<?php
/**
 * Code Highlight de PRO → Código resaltado (`digitalisimo-code-highlight`,
 * modules/digitalisimo-content/widgets/class-code-highlight.php).
 *
 * El widget propio repite los nueve controles con el mismo nombre, los mismos valores (también la clave de idioma
 * `visual-basic,` que guardan los sitios) y los mismos valores de retorno, así que el mapa es todo `keep`. El marcado y
 * las clases son propios: los temas pasan a `digi-code--<tema>`, el bloque a `digi-code__pre` y el botón de copiar es
 * un `<button>` propio (`digi-code__copy`) en lugar de la barra de Prism. Las clases de Prism (`.token`,
 * `.line-numbers-rows`, `.line-highlight`, `language-*`) no cambian. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_ch_classes = array(
	'.elementor-widget-code-highlight' => '.elementor-widget-digitalisimo-code-highlight',
	'.highlight-height'                => '.digi-code__pre',
	'.copy-to-clipboard'               => '.digi-code--copy',
	'.word-wrap'                       => '.digi-code--wrap',
	'.code-toolbar'                    => '.digi-code',
	'.copy-to-clipboard-button'        => '.digi-code__copy',
);
foreach ( array( 'default', 'dark', 'okaidia', 'solarizedlight', 'tomorrow', 'twilight' ) as $digi_ch_theme ) {
	$digi_ch_classes[ '.prismjs-' . $digi_ch_theme ] = '.digi-code--' . $digi_ch_theme;
}

return array(
	'source'   => 'code-highlight',
	'target'   => 'digitalisimo-code-highlight',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de PRO (Elementor no guarda lo que coincide con ellos).
	'defaults' => array(
		'language'          => 'javascript',
		'code'              => 'console.log( \'Code is Poetry\' );',
		'line_numbers'      => 'line-numbers',
		'copy_to_clipboard' => 'copy-to-clipboard',
		'highlight_lines'   => '',
		'word_wrap'         => '',
		'theme'             => 'default',
	),
	'keep'     => array(
		'language',
		'code',
		'line_numbers',
		'copy_to_clipboard',
		'highlight_lines',
		'word_wrap',
		'theme',
		'height',
		'font_size',
	),
	'classes'  => $digi_ch_classes,
);
