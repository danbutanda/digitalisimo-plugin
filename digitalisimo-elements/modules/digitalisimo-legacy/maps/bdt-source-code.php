<?php
/** Element Pack Pro 9.9.1 `bdt-source-code` → `code-highlight`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'code-highlight',
	'classes'   => array(),
	'defaults'  => array(
		'theme' => 'default',
		'source_code_copy_button' => 'true',
		'source_code_language_selector' => 'language-markup',
		'source_code_content' => '
                &lt;!DOCTYPE html&gt;
                &lt;html lang="en"&gt;
                &lt;head&gt;
                &lt;meta charset="UTF-8"&gt;
                &lt;title&gt;Document&lt;/title&gt;
                &lt;/head&gt;
                &lt;body&gt;
                &lt;h1&gt;Hello World!&lt;/h1&gt;
                &lt;p&gt;Lorem ipsum dolor sit amet, consectetur adipisicing elit. &lt;/p&gt;
                &lt;/body&gt;
                &lt;/html&gt;',
		'source_code_preview_height' => array(
			'size' => 500,
			'unit' => 'px',
		),
	),
	'filter'    => static function ( array $out ) {
		// Element Pack y Code Highlight imprimen el código tal como se guardó (con sus entidades).
		$out['code']              = (string) ( $out['source_code_content'] ?? '' );
		$out['language']          = preg_replace( '/^language-/', '', (string) ( $out['source_code_language_selector'] ?? 'language-markup' ) );
		$out['copy_to_clipboard'] = 'true' === (string) ( $out['source_code_copy_button'] ?? 'true' ) ? 'yes' : '';
		$out['line_numbers']      = '' !== (string) ( $out['line_numbers'] ?? '' ) ? 'line-numbers' : '';
		$out['highlight_lines']   = (string) ( $out['line_highlight'] ?? '' );
		$themes                   = array( 'dark' => 'dark', 'okaidia' => 'okaidia', 'solarizedlight' => 'solarizedlight', 'tomorrow' => 'tomorrow', 'twilight' => 'twilight' );
		$out['theme']             = $themes[ (string) ( $out['theme'] ?? '' ) ] ?? 'default';
		foreach ( array( 'source_code_content', 'source_code_language_selector', 'source_code_copy_button', 'line_highlight' ) as $key ) {
			unset( $out[ $key ] );
		}
		return $out;
	},
);
