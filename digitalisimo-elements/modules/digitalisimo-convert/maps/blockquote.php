<?php
/**
 * Blockquote de PRO → Cita (`digitalisimo-blockquote`, modules/digitalisimo-widgets/class-blockquote.php).
 *
 * La Cita tiene un control por cada control del Blockquote, con el mismo nombre y los mismos valores, salvo los cuatro
 * del botón, que dejan de nombrar al servicio (`tweet_button*` → `share_*`). Las clases de las pieles, la vista y la
 * piel del botón van en el envoltorio como en PRO, así que el CSS personalizado del elemento sigue apuntando al mismo
 * nodo. Formato: cabecera de `class-translator.php`.
 *
 * Fidelidad (2026-11-06):
 * - Icono: `share_icon` = `legacy` pinta el pájaro que pintaba PRO; una Cita nueva, el icono propio.
 * - Alineación: PRO pintaba en el envoltorio, en todos los anchos, la clase `elementor-blockquote--align-<valor>` de
 *   la alineación de escritorio y de cada dispositivo (su `prefix_class` no lleva `%s`), y se veía lo que resultaba de
 *   la cascada de su hoja. `filter` calcula ese resultado y lo fija en `pro_align`.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'source'   => 'blockquote',
	'target'   => 'digitalisimo-blockquote',
	'status'   => 'ready',
	'panel'    => 'hide',
	// Valores por defecto de los controles del Blockquote de PRO (Elementor no guarda lo que coincide con ellos).
	'defaults' => array(
		'blockquote_skin'            => 'border',
		'blockquote_content'         => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.Lorem ipsum dolor sit amet consectetur adipiscing elit dolor',
		'author_name'                => 'John Doe',
		'tweet_button'               => 'yes',
		'tweet_button_view'          => 'icon-text',
		'tweet_button_skin'          => 'classic',
		'tweet_button_label'         => 'Tweet',
		'url_type'                   => 'current_page',
		'author_gap'                 => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
		'quote_size'                 => array( 'unit' => 'px', 'size' => 1, 'sizes' => array() ),
		'button_transition_duration' => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
		'border_transition_duration' => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
		'box_transition_duration'    => array( 'unit' => 'ms', 'size' => '', 'sizes' => array() ),
	),
	'keep'     => array(
		'blockquote_skin',
		'blockquote_content',
		'author_name',
		'user_name',
		'url_type',
		'url',
		'alignment',
		'content_text_color',
		'content_gap',
		'author_text_color',
		'author_gap',
		'button_size',
		'button_border_radius',
		'button_background_color',
		'button_text_color',
		'button_background_color_hover',
		'button_text_color_hover',
		'button_transition_duration',
		'border_color',
		'border_width',
		'border_gap',
		'border_color_hover',
		'border_width_hover',
		'border_gap_hover',
		'border_transition_duration',
		'border_vertical_padding',
		'box_padding',
		'box_background_color',
		'box_border_radius',
		'box_background_color_hover',
		'box_border_radius_hover',
		'box_transition_duration',
		'quote_text_color',
		'quote_size',
		'quote_gap',
	),
	'rename'   => array(
		'tweet_button'       => 'share_button',
		'tweet_button_view'  => 'share_view',
		'tweet_button_skin'  => 'share_skin',
		'tweet_button_label' => 'share_label',
	),
	// Grupos con el mismo prefijo en la Cita: tipografías, bordes y sombras de la caja.
	'groups'   => array(
		'content_typography'   => 'content_typography',
		'author_typography'    => 'author_typography',
		'button_typography'    => 'button_typography',
		'box_border'           => 'box_border',
		'box_border_hover'     => 'box_border_hover',
		'box_box_shadow'       => 'box_box_shadow',
		'box_box_shadow_hover' => 'box_box_shadow_hover',
	),
	// El icono que pintaba PRO (el pájaro) en lo convertido; una Cita nueva conserva el propio.
	'set'      => array( 'share_icon' => 'legacy' ),
	// La alineación que se veía en el Blockquote de PRO, en los ajustes de la Cita.
	//
	// PRO pintaba a la vez la clase de la alineación de escritorio y la de cada dispositivo activo (un dispositivo
	// inactivo no tiene control ni clase). Con su hoja, de la unión U de esas clases resultaba:
	// - contenido: a la derecha con `right` en U; si no, a la izquierda con `left`; si no, hereda (centrado con `center`);
	// - pie: con `center` en U, en bloque, con el autor y el botón centrados; si no, en fila invertida con `right` y en
	//   fila con `left`;
	// - pico de la burbuja: a la derecha con `right`; si no, a la izquierda con `left`; si no, el de por defecto.
	// La Cita aplica cada alineación sólo en su dispositivo, así que se quitan las de los dispositivos, la de escritorio
	// se conserva (de ella depende la separación del autor) y `pro_align` fija el resultado en todos los anchos.
	//
	// La separación del autor (`author_gap`) sólo se pintaba en PRO, por dispositivo, donde la alineación de ese
	// dispositivo (o la del más próximo que la tuviera, como en `Controls_Stack::is_control_visible()`) era `center`. Sin
	// alineaciones por dispositivo, en la Cita depende sólo de la de escritorio: se quita la de los dispositivos donde PRO
	// no la pintaba y, si PRO la pintaba en un dispositivo sin tener `center` en escritorio, la de escritorio pasa a
	// `center` con su separación vacía (PRO no la pintaba) para que la de ese dispositivo siga apareciendo.
	//
	// El CSS personalizado del elemento que nombraba la clase de un valor de U (siempre presente en PRO) pasa a nombrar la
	// clase del envoltorio, con la misma especificidad, salvo la del valor que queda en escritorio.
	'filter'   => static function ( array $out, array $src ): array {
		$active  = array( '_tablet', '_mobile' );
		$plugin  = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		$manager = is_object( $plugin ) && isset( $plugin->breakpoints ) && is_object( $plugin->breakpoints ) && method_exists( $plugin->breakpoints, 'get_active_breakpoints' ) ? $plugin->breakpoints : null;
		if ( $manager ) {
			$active = array();
			foreach ( array_keys( (array) $manager->get_active_breakpoints() ) as $name ) {
				$active[] = '_' . $name;
			}
		}
		$valid = array( 'left', 'center', 'right' );
		$value = static function ( string $device ) use ( $src ): string {
			$raw = $src[ 'alignment' . $device ] ?? '';
			return is_scalar( $raw ) ? (string) $raw : '';
		};
		// Dispositivos activos de mayor a menor, como los crea `add_responsive_control()`; cada uno hereda del anterior,
		// salvo el panorámico, que hereda de escritorio.
		$devices = array( '' );
		$cascade = array( '' => $value( '' ) );
		$parent  = '';
		foreach ( array( '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' ) as $device ) {
			if ( ! in_array( $device, $active, true ) ) {
				continue;
			}
			$devices[]          = $device;
			$own                = $value( $device );
			$cascade[ $device ] = '' !== $own ? $own : $cascade[ '_widescreen' === $device ? '' : $parent ];
			if ( '_widescreen' !== $device ) {
				$parent = $device;
			}
		}
		$union = array();
		foreach ( $devices as $device ) {
			if ( in_array( $value( $device ), $valid, true ) ) {
				$union[ $value( $device ) ] = true;
			}
		}
		foreach ( \Digitalisimo\Elements\Convert\Translator::DEVICES as $device ) {
			if ( '' !== $device ) {
				unset( $out[ 'alignment' . $device ] );
			}
		}
		$desktop = (string) ( $out['alignment'] ?? '' );
		if ( 'center' !== $desktop ) {
			foreach ( $devices as $device ) {
				if ( '' !== $device && 'center' === $cascade[ $device ] && '' !== (string) ( $out[ 'author_gap' . $device ]['size'] ?? '' ) ) {
					$desktop           = 'center';
					$out['alignment']  = 'center';
					$out['author_gap'] = array( 'unit' => is_array( $out['author_gap'] ?? null ) ? (string) ( $out['author_gap']['unit'] ?? 'px' ) : 'px', 'size' => '', 'sizes' => array() );
					break;
				}
			}
		}
		if ( 'center' === $desktop ) {
			foreach ( $devices as $device ) {
				if ( '' !== $device && 'center' !== $cascade[ $device ] ) {
					unset( $out[ 'author_gap' . $device ] );
				}
			}
		}
		if ( isset( $union['center'] ) ) {
			$effective = isset( $union['right'] ) ? 'center-right' : ( isset( $union['left'] ) ? 'center-left' : 'center' );
		} else {
			$effective = isset( $union['right'] ) ? 'right' : ( isset( $union['left'] ) ? 'left' : '' );
		}
		if ( '' !== $effective && $effective !== $desktop ) {
			$out['pro_align'] = $effective;
		}
		if ( isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) ) {
			foreach ( array_keys( $union ) as $align ) {
				if ( $align !== $desktop ) {
					$out['custom_css'] = (string) preg_replace( '/\.digi-blockquote--align-' . $align . '(?![\w-])/', '.elementor-widget-digitalisimo-blockquote', $out['custom_css'] );
				}
			}
		}
		return $out;
	},
	// Encabezados del panel: no guardan valor.
	'ui'       => array( 'heading_content_style', 'heading_author_style' ),
	'classes'  => array(
		'.elementor-widget-blockquote'                => '.elementor-widget-digitalisimo-blockquote',
		'.elementor-blockquote'                       => '.digi-blockquote',
		'.elementor-blockquote__content'              => '.digi-blockquote__content',
		'.elementor-blockquote__author'               => '.digi-blockquote__author',
		'.elementor-blockquote__tweet-button'         => '.digi-blockquote__button',
		'.elementor-blockquote__tweet-label'          => '.digi-blockquote__label',
		'.e-q-footer'                                 => '.digi-blockquote__footer',
		'.elementor-blockquote--skin-border'          => '.digi-blockquote--skin-border',
		'.elementor-blockquote--skin-quotation'       => '.digi-blockquote--skin-quotation',
		'.elementor-blockquote--skin-boxed'           => '.digi-blockquote--skin-boxed',
		'.elementor-blockquote--skin-clean'           => '.digi-blockquote--skin-clean',
		'.elementor-blockquote--button-view-icon-text' => '.digi-blockquote--view-icon-text',
		'.elementor-blockquote--button-view-icon'     => '.digi-blockquote--view-icon',
		'.elementor-blockquote--button-view-text'     => '.digi-blockquote--view-text',
		'.elementor-blockquote--button-skin-classic'  => '.digi-blockquote--button-classic',
		'.elementor-blockquote--button-skin-bubble'   => '.digi-blockquote--button-bubble',
		'.elementor-blockquote--button-skin-link'     => '.digi-blockquote--button-link',
		'.elementor-blockquote--align-left'           => '.digi-blockquote--align-left',
		'.elementor-blockquote--align-center'         => '.digi-blockquote--align-center',
		'.elementor-blockquote--align-right'          => '.digi-blockquote--align-right',
	),
);
