<?php
/**
 * Marca propia (docs/MARCA_PROPIA.md), PROTOTIPO de la fase 0: la capa de marca sobre el HTML servido, con expresiones
 * limitadas a atributos y bloques conocidos (nunca `src`/`srcset`/`href` salvo las URL de assets del constructor y
 * `#elementor-action`, nunca el texto). Se usa de dos formas:
 *
 * - medición: `php scripts/marca-prototipo/buffer.php <html> [vueltas]` transforma el HTML N veces (100 por defecto) y
 *   da la mediana en ms, el tamaño y las apariciones de «elementor» antes y después, por tipo;
 * - como biblioteca: `require` y `digitalisimo_marca_proto_html( $html, $rules, $opts )` (lo carga el mu-plugin de
 *   Playground `tests/playground/brand-zero-trace/proto.php`).
 *
 * `$opts`: `plugin_url` (…/plugins/digitalisimo-elements/), `mirror_dir` (carpeta del espejo en disco, para saber qué
 * archivos propios tienen copia), `motor` (carpeta del motor en el paquete: `elementor` o `motor`).
 */

function digitalisimo_marca_proto_css( string $css, string $p = 'dg' ): string {
	$css = (string) preg_replace_callback( '#/\*.*?\*/#s', static function ( $m ) { return stripos( $m[0], 'elementor' ) !== false ? '' : $m[0]; }, $css );
	$css = str_replace( array( 'data-elementor-', '--elementor-' ), array( 'data-' . $p . '-', '--' . $p . '-' ), $css );
	return (string) preg_replace( '/elementor/i', $p, $css );
}

/** JS en línea: identificadores conocidos, eventos y acciones de URL. Nunca cambia URL de medios. */
function digitalisimo_marca_proto_js( string $js, array $rules, string $p = 'dg' ): string {
	static $ids = null;
	if ( null === $ids ) {
		$ids = array_merge( (array) ( $rules['js']['propiedades'] ?? array() ), (array) ( $rules['js']['globales'] ?? array() ) );
		uksort( $ids, static function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
	}
	foreach ( $ids as $from => $to ) {
		if ( false !== strpos( $js, $from ) ) {
			$js = (string) preg_replace( '/(?<![\w$])' . preg_quote( $from, '/' ) . '(?![\w$])/', $to, $js );
		}
	}
	foreach ( (array) ( $rules['js']['eventos_regla'] ?? array() ) as $r ) {
		$js = (string) preg_replace( '#(?<![\w./\\\\-])' . preg_quote( $r['de'], '#' ) . '#', $r['a'], $js );
	}
	$js = str_replace( array( '#elementor-action', '%23elementor-action', 'data-elementor-' ), array( '#' . $p . '-action', '%23' . $p . '-action', 'data-' . $p . '-' ), $js );
	// Selectores y clases en cadenas: `.elementor-x` y `"elementor-x"`.
	return (string) preg_replace( '/(?<=[."\'\s])elementor(?=-[a-z])/', $p, $js );
}

/** URL de un asset del constructor → su copia del espejo (o la misma si no hay copia). */
function digitalisimo_marca_proto_url( string $url, array $o, string $p = 'dg' ): string {
	$plugin = preg_quote( (string) $o['plugin_url'], '#' );
	$motor  = preg_quote( (string) ( $o['motor'] ?? 'elementor' ), '#' );
	$url    = (string) preg_replace_callback(
		'#^' . $plugin . '(?:' . $motor . '|elementor)/assets/([^?\#]+)#',
		static function ( $m ) use ( $o, $p ) {
			return $o['plugin_url'] . 'marca/motor/' . preg_replace( '/elementor/i', $p, $m[1] );
		},
		$url
	);
	$url = (string) preg_replace_callback(
		'#^' . $plugin . '((?:assets|modules)/[^?\#]+\.(?:css|js))#',
		static function ( $m ) use ( $o ) {
			return ! empty( $o['mirror_dir'] ) && is_file( $o['mirror_dir'] . '/' . $m[1] ) ? $o['plugin_url'] . 'marca/' . $m[1] : $m[0];
		},
		$url
	);
	return (string) preg_replace( '#/uploads/elementor/(css|google-fonts)/#', '/uploads/digitalisimo-elements/$1/', $url );
}

function digitalisimo_marca_proto_html( string $html, array $rules, array $o ): string {
	$p = (string) ( $rules['prefijo'] ?? 'dg' );
	// Bloques <style> y <script> en línea.
	$html = (string) preg_replace_callback(
		'#(<style\b[^>]*>)(.*?)(</style>)#is',
		static function ( $m ) use ( $p ) {
			return $m[1] . digitalisimo_marca_proto_css( $m[2], $p ) . $m[3];
		},
		$html
	);
	$html = (string) preg_replace_callback(
		'#(<script\b(?![^>]*\bsrc=)[^>]*>)(.*?)(</script>)#is',
		static function ( $m ) use ( $rules, $o, $p ) {
			if ( false === stripos( $m[2], 'elementor' ) ) {
				return $m[0];
			}
			$js = $m[2];
			// Las URL de assets también en JSON (con `\/`).
			$plain = str_replace( '\/', '/', (string) $o['plugin_url'] );
			foreach ( array( (string) ( $o['motor'] ?? 'elementor' ), 'elementor' ) as $dir ) {
				$js = str_replace( array( $plain . $dir . '/assets/', str_replace( '/', '\/', $plain . $dir . '/assets/' ) ), array( $plain . 'marca/motor/', str_replace( '/', '\/', $plain . 'marca/motor/' ) ), $js );
			}
			return $m[1] . digitalisimo_marca_proto_js( $js, $rules, $p ) . $m[3];
		},
		$html
	);
	// Etiquetas: atributos conocidos.
	return (string) preg_replace_callback(
		'#<([a-zA-Z][a-zA-Z0-9-]*)(\s[^<>]*?)?(/?)>#s',
		static function ( $m ) use ( $o, $p ) {
			$attrs = $m[2] ?? '';
			if ( '' === $attrs || false === stripos( $attrs, 'elementor' ) ) {
				return $m[0];
			}
			$attrs = (string) preg_replace_callback(
				'#(\s)([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*("[^"]*"|\'[^\']*\'))?#',
				static function ( $a ) use ( $o, $p ) {
					$name  = $a[2];
					$value = $a[4] ?? '';
					$lname = strtolower( $name );
					if ( 0 === strpos( $lname, 'data-elementor-' ) ) {
						$name = 'data-' . $p . '-' . substr( $name, 15 );
					}
					if ( '' !== $value && false !== stripos( $value, 'elementor' ) ) {
						if ( 'class' === $lname || 'id' === $lname || 'for' === $lname || 'aria-controls' === $lname || 'aria-labelledby' === $lname || 'aria-describedby' === $lname ) {
							$value = (string) preg_replace( '/elementor/i', $p, $value );
						} elseif ( 'href' === $lname || 'src' === $lname ) {
							$q     = $value[0];
							$inner = substr( $value, 1, -1 );
							if ( preg_match( '/^(#|%23)elementor-action/i', $inner ) ) {
								$inner = (string) preg_replace( '/elementor-action/', $p . '-action', $inner, 1 );
							} else {
								$inner = digitalisimo_marca_proto_url( $inner, $o, $p );
							}
							$value = $q . $inner . $q;
						} elseif ( 0 === strpos( $lname, 'data-' ) && preg_match( '/^["\'](#|%23)elementor-action/i', $value ) ) {
							$value = (string) preg_replace( '/elementor-action/', $p . '-action', $value, 1 );
						} elseif ( 0 === strpos( $lname, 'data-' ) && preg_match( '/^["\']elementor_library["\']$/', $value ) ) {
							$value = $value[0] . 'digitalisimo_library' . $value[0];
						}
					}
					return $a[1] . $name . ( '' !== $value ? '=' . $value : ( $a[3] ?? '' ) );
				},
				$attrs
			);
			return '<' . $m[1] . $attrs . $m[3] . '>';
		},
		$html
	);
}

/** Apariciones por tipo (para la medición). */
function digitalisimo_marca_proto_census( string $html ): array {
	return array(
		'total'      => preg_match_all( '/elementor/i', $html ),
		'class'      => preg_match_all( '/\sclass="[^"]*elementor/i', $html ),
		'data-attr'  => preg_match_all( '/\sdata-elementor-/i', $html ),
		'url'        => preg_match_all( '#(src|href)="[^"]*elementor#i', $html ),
		'script'     => preg_match_all( '#<script\b(?![^>]*\bsrc=)[^>]*>[^<]*elementor#is', $html ),
		'texto'      => preg_match_all( '/>[^<]*elementor[^<]*</i', $html ),
	);
}

if ( PHP_SAPI === 'cli' && isset( $argv ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$file  = $argv[1] ?? '';
	$runs  = (int) ( $argv[2] ?? 100 );
	$html  = (string) @file_get_contents( $file );
	if ( '' === $html ) {
		fwrite( STDERR, "uso: php buffer.php <html> [vueltas]\n" );
		exit( 2 );
	}
	$rules = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/digitalisimo-elements/marca/reglas.json' ), true );
	$o     = array( 'plugin_url' => 'https://digitalisimo.mx/wp-content/plugins/digitalisimo-elements/', 'mirror_dir' => $argv[3] ?? '', 'motor' => 'elementor' );
	$out   = digitalisimo_marca_proto_html( $html, $rules, $o );
	$times = array();
	for ( $i = 0; $i < $runs; $i++ ) {
		$t       = hrtime( true );
		digitalisimo_marca_proto_html( $html, $rules, $o );
		$times[] = ( hrtime( true ) - $t ) / 1e6;
	}
	sort( $times );
	$jit = function_exists( 'opcache_get_status' ) ? ini_get( 'pcre.jit' ) : ini_get( 'pcre.jit' );
	echo json_encode(
		array(
			'php'          => PHP_VERSION,
			'pcre_jit'     => $jit,
			'bytes'        => strlen( $html ),
			'bytes_despues' => strlen( $out ),
			'vueltas'      => $runs,
			'ms_mediana'   => round( $times[ intdiv( $runs, 2 ) ], 2 ),
			'ms_p95'       => round( $times[ (int) floor( $runs * 0.95 ) - 1 ], 2 ),
			'antes'        => digitalisimo_marca_proto_census( $html ),
			'despues'      => digitalisimo_marca_proto_census( $out ),
			'quedan'       => array_slice( array_count_values( array_map( 'strtolower', ( preg_match_all( '/[\w.\/:-]{0,40}elementor[\w.\/-]{0,30}/i', $out, $mm ) ? $mm[0] : array() ) ) ), 0, 40, true ),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . "\n";
	if ( ! empty( $argv[4] ) ) {
		file_put_contents( $argv[4], $out );
	}
}
