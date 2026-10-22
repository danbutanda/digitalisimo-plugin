<?php
namespace Digitalisimo\Elements\Design\Presets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Tab_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Ajustes del sitio → «Presets de estilo» (grupo «Sistema de diseño»): la lista de los presets del sitio.
 *
 * Un solo control `RAW_HTML` con una tabla (nombre, tipo, slug, tamaño y acciones) y el total frente al tope. No
 * guarda nada en los ajustes del kit: renombrar y borrar van por las acciones AJAX, que escucha `presets-editor.js`
 * con `data-digi-preset-action="rename|delete"` y `data-digi-preset="<slug>"` en cada botón, `data-digi-preset-row`
 * en cada fila y `data-digi-preset-label` en el nombre. Los inertes salen con «tipo no disponible» y **sin
 * acciones**: ni se editan ni se borran desde aquí, viajan en los kits y vuelven a funcionar cuando el tipo existe.
 * Los que hoy no superan el saneado salen «con error».
 *
 * La tabla sólo se arma en el editor: en el front (cuando el núcleo genera el CSS del kit) el control va vacío y la
 * opción de presets no se lee.
 */
final class Kit_Tab extends Tab_Base {
	const ID = 'settings-digitalisimo-presets';

	/**
	 * Registra la pestaña en el kit (`elementor/kit/register_tabs`).
	 *
	 * @param mixed $kit Documento del kit.
	 */
	public static function register( $kit ): void {
		if ( is_object( $kit ) && method_exists( $kit, 'register_tab' ) ) {
			$kit->register_tab( self::ID, self::class );
		}
	}

	public function get_id() {
		return self::ID;
	}

	public function get_title() {
		return __( 'Presets de estilo', 'pro-elements' );
	}

	public function get_group() {
		return 'global';
	}

	public function get_icon() {
		return 'eicon-paint-brush';
	}

	public function get_help_url() {
		return '';
	}

	protected function register_tab_controls() {
		$this->start_controls_section(
			'digitalisimo_presets_section',
			array(
				'label' => __( 'Presets de estilo', 'pro-elements' ),
				'tab'   => $this->get_id(),
			)
		);
		$this->add_control(
			'digitalisimo_presets_list',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => Controls::is_editor() ? self::table() : '',
			)
		);
		$this->end_controls_section();
	}

	/** Tabla de presets del sitio. */
	public static function table(): string {
		$presets = Store::all();
		$limits  = Store::limits();
		$total   = Store::total();
		$html    = '<div class="digitalisimo-presets-tab">';
		$html   .= '<p class="elementor-control-field-description">' . esc_html__( 'Un preset guarda sólo estilo. Se crea desde el menú contextual de un elemento («Guardar estilo como preset…») y se aplica con «Preset de estilo» en su pestaña Avanzado. Lo que cambies en un elemento gana siempre sobre su preset.', 'pro-elements' ) . '</p>';
		if ( ! $presets ) {
			return $html . '<p>' . esc_html__( 'Este sitio aún no tiene presets de estilo.', 'pro-elements' ) . '</p></div>';
		}
		$html .= '<table class="digitalisimo-presets-table" style="width:100%;border-collapse:collapse"><thead><tr>';
		foreach ( array( __( 'Nombre', 'pro-elements' ), __( 'Tipo', 'pro-elements' ), __( 'Tamaño', 'pro-elements' ), '' ) as $head ) {
			$html .= '<th style="text-align:start;padding:4px">' . esc_html( $head ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $presets as $slug => $preset ) {
			$state = '';
			if ( ! empty( $preset['inert'] ) ) {
				$state = __( 'tipo no disponible', 'pro-elements' );
			} elseif ( null === Compiler::validated( (string) $slug ) ) {
				$state = __( 'con error', 'pro-elements' );
			}
			$html .= '<tr data-digi-preset-row="' . esc_attr( $slug ) . '">';
			$html .= '<td style="padding:4px"><strong data-digi-preset-label>' . esc_html( $preset['label'] ) . '</strong><br><code>' . esc_html( $slug ) . '</code>' . ( '' !== $state ? ' · <em>' . esc_html( $state ) . '</em>' : '' ) . '</td>';
			$html .= '<td style="padding:4px">' . esc_html( $preset['type'] ) . '</td>';
			$html .= '<td style="padding:4px">' . esc_html( self::size( strlen( (string) wp_json_encode( $preset['settings'] ) ) ) ) . '</td>';
			$html .= '<td style="padding:4px;white-space:nowrap">';
			if ( empty( $preset['inert'] ) ) {
				$html .= '<button type="button" class="elementor-button" data-digi-preset-action="rename" data-digi-preset="' . esc_attr( $slug ) . '">' . esc_html__( 'Renombrar', 'pro-elements' ) . '</button> ';
				$html .= '<button type="button" class="elementor-button" data-digi-preset-action="delete" data-digi-preset="' . esc_attr( $slug ) . '">' . esc_html__( 'Borrar', 'pro-elements' ) . '</button>';
			}
			$html .= '</td></tr>';
		}
		$html .= '</tbody></table>';
		$html .= '<p class="elementor-control-field-description">' . esc_html(
			sprintf(
				/* translators: 1: número de presets, 2: máximo, 3: tamaño usado, 4: tamaño máximo. */
				__( '%1$d de %2$d presets · %3$s de %4$s.', 'pro-elements' ),
				count( $presets ),
				$limits['count'],
				self::size( $total ),
				self::size( $limits['total_bytes'] )
			)
		) . '</p>';
		return $html . '</div>';
	}

	/** Tamaño legible (B o KB). */
	private static function size( int $bytes ): string {
		return $bytes < 1024 ? $bytes . ' B' : number_format( $bytes / 1024, 1, ',', '' ) . ' KB';
	}
}
