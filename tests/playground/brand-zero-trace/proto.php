<?php
/**
 * PROTOTIPO de la capa de marca del front (fase 0, docs/MARCA_PROPIA.md), sólo para la auditoría en Playground y sólo
 * con la marca propia en vigor: el HTML del front pasa por scripts/marca-prototipo/buffer.php (copiado como
 * digi-zt-buffer.inc) y los assets se sirven del espejo `marca/` que brand-zero-trace.sh genera en la copia del plugin.
 * El CSS generado (`uploads/elementor/css/post-N.css`) se copia transformado a `uploads/digitalisimo-elements/css/`.
 * Nunca en el escritorio, el editor, su vista previa, la API ni AJAX. No es el código de la fase 2.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once __DIR__ . '/digi-zt-buffer.inc';
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || isset( $_GET['elementor-preview'] ) || is_feed() ) {
			return;
		}
		if ( ! class_exists( '\Digitalisimo\Elements\Marca\Toggle' ) || ! \Digitalisimo\Elements\Marca\Toggle::active() ) {
			return;
		}
		// Desde la fase 2 la capa de verdad (Marca\Front_Layer) corre sola con el espejo: el prototipo sólo hace falta
		// para auditar una versión sin ella.
		if ( class_exists( '\Digitalisimo\Elements\Marca\Front_Layer' ) ) {
			return;
		}
		$rules  = json_decode( (string) file_get_contents( WP_PLUGIN_DIR . '/digitalisimo-elements/marca/reglas.json' ), true );
		$core   = class_exists( 'Digitalisimo_Elements_Core' ) ? basename( rtrim( Digitalisimo_Elements_Core::dir(), '/' ) ) : 'elementor';
		$plugin = plugins_url( '/', WP_PLUGIN_DIR . '/digitalisimo-elements/pro-elements.php' );
		$opts   = array( 'plugin_url' => $plugin, 'mirror_dir' => WP_PLUGIN_DIR . '/digitalisimo-elements/marca', 'motor' => $core );
		ob_start(
			static function ( $html ) use ( $rules, $opts ) {
				$t   = hrtime( true );
				$out = digitalisimo_marca_proto_html( (string) $html, $rules, $opts );
				// Copias transformadas del CSS generado.
				$up = wp_get_upload_dir();
				if ( preg_match_all( '#/uploads/digitalisimo-elements/(css|google-fonts)/([\w./-]+\.css)#', $out, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $hit ) {
						$src = $up['basedir'] . '/elementor/' . $hit[1] . '/' . $hit[2];
						$dst = $up['basedir'] . '/digitalisimo-elements/' . $hit[1] . '/' . $hit[2];
						if ( is_readable( $src ) && ( ! is_file( $dst ) || filemtime( $dst ) < filemtime( $src ) ) ) {
							wp_mkdir_p( dirname( $dst ) );
							file_put_contents( $dst, digitalisimo_marca_proto_css( (string) file_get_contents( $src ) ) );
						}
					}
				}
				file_put_contents( '/digi-probe/buffer-ms.log', round( ( hrtime( true ) - $t ) / 1e6, 2 ) . ' ' . strlen( (string) $html ) . "\n", FILE_APPEND );
				return $out;
			}
		);
	},
	0
);
