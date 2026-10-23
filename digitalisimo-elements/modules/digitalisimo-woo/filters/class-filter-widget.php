<?php
namespace Digitalisimo\Elements\Woo\Filters;

use Digitalisimo\Elements\Woo\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Base de los filtros de precio y de atributo (F4-Woo, v1 §5.5 y V.8). Sigue el contrato de `Woo\Widget`: sólo
 * `render_widget()`, y `register_controls()` sin funciones de WooCommerce (las opciones que dependen de él se construyen
 * con WooCommerce y fuera de la pila optimizada del front, V.12.1).
 *
 * - **Destino** «Loop Grid de este documento» (por defecto) o «Tienda». En el primero los nombres son
 *   `e-filter-{bucle}-{nombre}`: el servidor los lee al pintar (sin JS, al recargar) y el coordinador los manda en el
 *   refresco. En «Tienda», los parámetros nativos de WooCommerce, que sólo filtran la consulta principal (V.8.5).
 * - **Marcado:** un `<form method="get">` con su `fieldset` y `legend`, un `<span class="e-filter" hidden>` con los mismos
 *   `data-base-url` y `data-page-num` que pinta el filtro de PRO (V.8.3), campos ocultos con los demás parámetros
 *   vigentes (V.8.6, V-M11) y enlaces «Quitar filtro»/«Limpiar» con `rel="nofollow"` (V.13).
 * - El HTML no depende del visitante (sólo de la URL): una página filtrada es otra entrada de la caché de página.
 */
abstract class Filter_Widget extends Widget {
	use \ElementorPro\Modules\Posts\Traits\Pagination_Trait;

	const HANDLE      = 'digitalisimo-woo-filters';
	const FORM_HANDLE = 'digitalisimo-woo-filters-form';

	/** Parámetros de paginación que un filtro nuevo descarta. */
	const PAGINATION = array( 'paged', 'page', 'product-page' );

	public function get_style_depends() {
		return array( 'digitalisimo-woo-filters' );
	}

	public function get_script_depends() {
		return array( 'digitalisimo-woo-filters', 'digitalisimo-woo-filters-form' );
	}

	public function get_keywords() {
		return array( 'filtro', 'woocommerce', 'tienda', 'loop grid', 'digitalisimo' );
	}

	/** Destino, bucle y los avisos del editor, al principio de la sección `section_filter`. */
	protected function register_target_controls(): void {
		$c = '\\Elementor\\Controls_Manager';
		$this->add_control( 'destination', array( 'label' => 'Destino', 'type' => $c::SELECT, 'default' => 'loop', 'options' => array( 'loop' => 'Loop Grid de este documento', 'shop' => 'Tienda' ) ) );
		$this->add_control( 'selected_element', array( 'label' => 'Loop Grid', 'type' => $c::SELECT, 'options' => array( '' => 'Elige un Loop Grid' ), 'label_block' => true, 'condition' => array( 'destination' => 'loop' ) ) );
		$this->add_control( 'loop_note', array( 'type' => $c::RAW_HTML, 'raw' => 'Filtra un Loop Grid de este documento sin recargar la página. El Loop Carousel no se puede filtrar, y un Loop Grid con «Consulta actual» tampoco: dale su propia consulta de productos.', 'content_classes' => 'elementor-descriptor', 'condition' => array( 'destination' => 'loop' ) ) );
		$this->add_control( 'shop_note', array( 'type' => $c::RAW_HTML, 'raw' => 'Filtra la consulta principal de la tienda con los parámetros de WooCommerce: el archivo de productos, el widget Productos con «Consulta actual» y las plantillas de la tienda. Este destino no filtra un Loop Grid; usa el destino Loop Grid.', 'content_classes' => 'elementor-descriptor', 'condition' => array( 'destination' => 'shop' ) ) );
	}

	/** Estilo común: separación, etiquetas, campos, color de acento y botón. Sin valores por defecto que pinten. */
	protected function register_style_controls(): void {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_filter_style', array( 'label' => 'Filtro', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'filter_gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .digi-filter' => '--digi-filter-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'legend_typography', 'label' => 'Título', 'selector' => '{{WRAPPER}} .digi-filter__legend' ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'option_typography', 'label' => 'Opciones', 'selector' => '{{WRAPPER}} .digi-filter__field, {{WRAPPER}} .digi-filter__option, {{WRAPPER}} .digi-filter__term' ) );
		$this->add_control( 'text_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-filter' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'accent_color', array( 'label' => 'Color de acento', 'type' => $c::COLOR, 'description' => 'Casillas, deslizadores y opciones elegidas.', 'selectors' => array( '{{WRAPPER}} .digi-filter' => '--digi-filter-accent: {{VALUE}};' ) ) );
		$this->add_control( 'border_color', array( 'label' => 'Color de los bordes', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-filter' => '--digi-filter-border: {{VALUE}};' ) ) );
		$this->add_control( 'border_radius', array( 'label' => 'Radio de los bordes', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'em' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .digi-filter' => '--digi-filter-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'button_heading', array( 'label' => 'Botón', 'type' => $c::HEADING, 'separator' => 'before' ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'selector' => '{{WRAPPER}} .digi-filter__submit' ) );
		$this->add_control( 'button_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-filter' => '--digi-filter-button-color: {{VALUE}};' ) ) );
		$this->add_control( 'button_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-filter' => '--digi-filter-button-bg: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** `loop` o `shop`. */
	protected function destination(): string {
		return 'shop' === $this->get_settings_for_display( 'destination' ) ? 'shop' : 'loop';
	}

	/** ID del Loop Grid (7 caracteres en minúscula, como los que lee `parse_query_string()`), o ''. */
	protected function loop_id(): string {
		$id = $this->get_settings_for_display( 'selected_element' );
		return is_string( $id ) && preg_match( '/^[a-z0-9]{7}$/', $id ) ? $id : '';
	}

	/** Prefijo de los nombres en la URL con destino Loop Grid. */
	protected function loop_prefix(): string {
		return 'e-filter-' . $this->loop_id() . '-';
	}

	/** ¿Editor o vista previa? */
	protected function in_editor(): bool {
		$plugin = class_exists( '\Elementor\Plugin', false ) ? \Elementor\Plugin::$instance : null;
		return is_object( $plugin ) && ( ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() ) );
	}

	/** Aviso del editor (fuera de él, nada). */
	protected function editor_message( string $text ): void {
		if ( $this->in_editor() ) {
			echo '<div class="digi-filter-empty">' . esc_html( $text ) . '</div>';
		}
	}

	/**
	 * Parámetros de la URL actual: sólo cadenas, sin barras invertidas añadidas, con claves y valores acotados. Los
	 * arreglos (`x[]=…`) no se conservan.
	 *
	 * @return array<string,string>
	 */
	protected static function query_params(): array {
		$out = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro público de sólo lectura.
		foreach ( isset( $_GET ) && is_array( $_GET ) ? $_GET : array() as $key => $value ) {
			if ( ! is_string( $key ) || ! is_string( $value ) || strlen( $key ) > 100 || strlen( $value ) > 2000 || count( $out ) >= 50 ) {
				continue;
			}
			$out[ $key ] = (string) wp_unslash( $value );
		}
		return $out;
	}

	/** Valor actual de un parámetro, o ''. */
	protected static function param( string $name ): string {
		$params = self::query_params();
		return isset( $params[ $name ] ) ? $params[ $name ] : '';
	}

	/**
	 * Base de la URL del filtro: la que pinta el filtro de PRO (`Pagination_Trait::get_base_url()`), sin paginación.
	 * Con destino «Tienda», sin `/page/N/`.
	 */
	protected function base_url(): string {
		$url = (string) $this->get_base_url();
		return '' !== $url ? $url : home_url( '/' );
	}

	/** Acción del formulario: la base sin su cadena de consulta (un GET la sustituye por los campos). */
	protected function action_url(): string {
		$url = $this->base_url();
		$pos = strpos( $url, '?' );
		return false === $pos ? $url : substr( $url, 0, $pos );
	}

	/**
	 * Parámetros que el formulario conserva como campos ocultos o en sus enlaces: los de la URL y los de la base (enlaces
	 * simples), menos los propios del filtro y la paginación del bucle y de la tienda.
	 *
	 * @param string[] $own Nombres que el filtro escribe.
	 * @return array<string,string>
	 */
	protected function kept_params( array $own ): array {
		$base  = array();
		$query = (string) wp_parse_url( $this->base_url(), PHP_URL_QUERY );
		if ( '' !== $query ) {
			wp_parse_str( $query, $base );
		}
		$drop = array_merge( $own, self::PAGINATION );
		if ( 'loop' === $this->destination() && '' !== $this->loop_id() ) {
			$drop[] = 'e-page-' . $this->loop_id();
		}
		$kept = array();
		foreach ( array_merge( array_filter( $base, 'is_string' ), self::query_params() ) as $key => $value ) {
			if ( ! in_array( (string) $key, $drop, true ) ) {
				$kept[ (string) $key ] = (string) $value;
			}
		}
		return $kept;
	}

	/**
	 * URL de la acción con estos parámetros. Los valores de `$raw` ya van codificados (separadores `~`, `+` o `,`
	 * literales entre términos codificados).
	 *
	 * @param array<string,string> $params Parámetros sin codificar.
	 * @param array<string,string> $raw    Parámetros ya codificados.
	 */
	protected function url_with( array $params, array $raw = array() ): string {
		$pairs = array();
		foreach ( $params as $key => $value ) {
			$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
		}
		foreach ( $raw as $key => $value ) {
			$pairs[] = rawurlencode( (string) $key ) . '=' . $value;
		}
		return $this->action_url() . ( $pairs ? '?' . implode( '&', $pairs ) : '' );
	}

	/**
	 * Términos codificados y unidos por un separador literal.
	 *
	 * @param string[] $terms Términos.
	 * @param string   $glue  `~`, `+` o `,`.
	 */
	protected static function join_terms( array $terms, string $glue ): string {
		return implode( $glue, array_map( 'rawurlencode', $terms ) );
	}

	/** Campos ocultos con los parámetros que el formulario conserva. */
	protected function hidden_fields( array $own ): string {
		$html = '';
		foreach ( $this->kept_params( $own ) as $key => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		return $html;
	}

	/** El `<span class="e-filter">` oculto con los datos que pinta el filtro de PRO (V.8.3). */
	protected function helper_span(): string {
		$page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		return '<span class="e-filter" hidden data-base-url="' . esc_url( $this->base_url() ) . '" data-page-num="' . esc_attr( (string) $page ) . '"></span>';
	}

	/** Atributos comunes del formulario. */
	protected function form_attributes( string $kind, string $label, array $extra ): string {
		$attrs = array_merge(
			array(
				'class'            => 'digi-filter digi-filter--' . $kind,
				'method'           => 'get',
				'action'           => esc_url( $this->action_url() ),
				'aria-label'       => $label,
				'data-digi-filter' => $kind,
				'data-target'      => $this->destination(),
				'data-loop'        => 'loop' === $this->destination() ? $this->loop_id() : '',
				'data-status'      => wp_json_encode( array( 'updated' => 'Resultados actualizados', 'none' => 'Ningún producto con estos filtros', 'one' => '1 producto', 'many' => '%d productos' ) ),
			),
			$extra
		);
		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . ( 'action' === $name ? $value : esc_attr( (string) $value ) ) . '"';
		}
		return $html;
	}

	/** Minúsculas para el nombre accesible («Filtrar por color»). */
	protected static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/** Con destino Loop Grid, los paquetes de PRO (el coordinador los necesita para el filtro de PRO del bucle). */
	protected function require_pro(): void {
		$gate = '\Digitalisimo\Elements\Front\Pro_Gate';
		if ( 'loop' === $this->destination() && class_exists( $gate ) && method_exists( $gate, 'require' ) ) {
			$gate::require( 'woo-filter' );
		}
	}
}
