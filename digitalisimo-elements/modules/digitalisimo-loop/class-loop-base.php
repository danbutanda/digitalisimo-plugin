<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Core\Base\Document;
use ElementorPro\Modules\LoopBuilder\Documents\Loop as LoopDocument;
use ElementorPro\Modules\QueryControl\Controls\Template_Query;
use ElementorPro\Modules\QueryControl\Module as QueryControlModule;

defined( 'ABSPATH' ) || exit;

/**
 * Base de la Cuadrícula y el Carrusel de bucle propios (C5, frente J; `docs/pro/c5.md`). Repite
 * `modules/loop-builder/widgets/base.php` de PRO (que hereda de Entradas): las mismas secciones, controles, valores por
 * defecto y condiciones sobre la base común de I (`Posts_Base`: paginación, botón «Cargar más», enlaces de página).
 *
 * - **Pieles** (el `_skin` de PRO decide el origen del bucle): `post` (entradas, el grupo de consulta de PRO con prefijo
 *   `post_query_`), `post_taxonomy` (términos) y, con WooCommerce, `product` y `product_taxonomy`, en el mismo orden que
 *   PRO (`Loop_Skin*`). Los ajustes guardados conservan sus nombres (`post_query_post_type`…), así que el mapa es `keep`.
 * - **Consulta**: la del grupo de consulta de PRO (`QueryControlModule::get_query()`, con `elementor/query/{id}` y
 *   `elementor/query/query_args`), así que las consultas personalizadas, las guardadas de F7b y los filtros de
 *   `loop-filter` siguen funcionando: todos miran el widget por su ID o por `is_loop` en su configuración.
 * - **Plantilla**: el Elemento de bucle se pinta con el documento del Theme Builder (`print_content()`) y el CSS de
 *   cada elemento (`Loop_Css_Trait::print_dynamic_css()`), con las plantillas alternas (`Alternate_Templates_Trait`).
 * - Cada widget pinta su contenedor: `render_loop_header()` y `render_loop_footer()` (la piel los llama), y puede
 *   ajustar los atributos de cada elemento con `loop_item_attributes()` mientras dura el bucle.
 *
 * Derivado de PRO Elements (GPL): `modules/loop-builder/widgets/base.php` y `loop-grid.php` (`on_import_…`).
 */
abstract class Loop_Base extends Posts_Base {
	const QUERY_ID = 'query';

	const SKIN_POST             = 'post';
	const SKIN_POST_TAXONOMY    = 'post_taxonomy';
	const SKIN_PRODUCT          = 'product';
	const SKIN_PRODUCT_TAXONOMY = 'product_taxonomy';

	public function get_group_name() {
		return 'loop-builder';
	}

	/** `post_query`, `product_query`…: el grupo de consulta de la piel actual, como PRO. */
	public function get_query_name() {
		$skin_id = str_replace( '-', '_', (string) $this->get_current_skin_id() );
		return ( '' !== $skin_id ? $skin_id : self::SKIN_POST ) . '_' . self::QUERY_ID;
	}

	/** `is_loop` lo leen el módulo de WooCommerce (consulta de productos) y el editor de PRO. */
	protected function get_initial_config() {
		$config                             = parent::get_initial_config();
		$config['is_loop']                  = true;
		$config['add_parent_render_header'] = true;
		$config['add_parent_render_footer'] = true;
		return $config;
	}

	/** La consulta: la de la piel (plantillas alternas fijas) o la del grupo de consulta, como Entradas de PRO. */
	public function query_posts() {
		$skin  = $this->get_current_skin();
		$query = $skin && method_exists( $skin, 'query_posts' ) ? $skin->query_posts() : false;
		if ( $query ) {
			$this->query = $query;
			return;
		}
		$this->query = QueryControlModule::instance()->get_query(
			$this,
			$this->get_query_name(),
			array(
				'posts_per_page'        => $this->get_posts_per_page_value(),
				'paged'                 => $this->get_current_page(),
				'has_custom_pagination' => $this->is_allow_to_use_custom_page_option(),
			),
			array()
		);
	}

	/** Elementos por página: el control de «Disposición» (público: lo leen las plantillas alternas). */
	public function get_posts_per_page_value() {
		return $this->get_settings_for_display( 'posts_per_page' );
	}

	/** ¿Está activo el módulo de WooCommerce de PRO (y con él, sus pieles de bucle)? */
	public static function woocommerce(): bool {
		if ( ! function_exists( 'WC' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
			return false;
		}
		$manager = \ElementorPro\Plugin::instance()->modules_manager ?? null;
		return is_object( $manager ) && null !== $manager->get_modules( 'woocommerce' );
	}

	/** Mismo orden que PRO: entradas, productos, términos de entradas y términos de productos. */
	protected function register_skins() {
		$this->add_skin( new Loop_Skin_Post( $this ) );
		$woo = self::woocommerce();
		if ( $woo ) {
			$this->add_skin( new Loop_Skin_Product( $this ) );
		}
		$this->add_skin( new Loop_Skin_Post_Taxonomy( $this ) );
		if ( $woo ) {
			$this->add_skin( new Loop_Skin_Product_Taxonomy( $this ) );
		}
	}

	protected function register_controls() {
		$this->register_layout_section();
		$this->register_query_section();

		// Carrusel.
		$this->register_settings_section_controls();
		$this->register_navigation_section_controls();

		$this->register_pagination_section_controls();
		$this->register_additional_options_section_controls();

		$this->register_design_layout_controls();
		$this->register_design_nothing_found_message_controls();
		$this->register_design_navigation_controls();
		$this->register_design_pagination_controls();

		// `_skin` decide el origen del bucle: se rotula como en PRO.
		$this->update_control(
			'_skin',
			array(
				'label'              => esc_html__( 'Tipo de plantilla', 'pro-elements' ),
				'label_block'        => true,
				'frontend_available' => true,
			)
		);
	}

	/** Control de la plantilla de Elemento de bucle (también en el repetidor de plantillas alternas). */
	protected function template_control( bool $redirect = false ): array {
		$actions = array(
			'new'  => array(
				'visible'         => true,
				'document_config' => array( 'type' => LoopDocument::get_type() ),
			),
			'edit' => array( 'visible' => true ),
		);
		if ( $redirect ) {
			$actions['new']['after_action']  = 'redirect';
			$actions['edit']['after_action'] = 'redirect';
		}
		return array(
			'label'              => esc_html__( 'Elige una plantilla', 'pro-elements' ),
			'type'               => Template_Query::CONTROL_ID,
			'label_block'        => true,
			'autocomplete'       => array(
				'object' => QueryControlModule::QUERY_OBJECT_LIBRARY_TEMPLATE,
				'query'  => array(
					'post_status' => Document::STATUS_PUBLISH,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- consulta del control de PRO.
						array(
							'key'     => Document::TYPE_META_KEY,
							'value'   => LoopDocument::get_type(),
							'compare' => 'IN',
						),
					),
				),
			),
			'actions'            => $actions,
			'frontend_available' => true,
		);
	}

	protected function register_layout_section() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => esc_html__( 'Disposición', 'pro-elements' ),
			)
		);
		$this->add_control( 'template_id', $this->template_control() );
		$this->end_controls_section();
	}

	/** Sección de consulta vacía: cada piel pone en ella su grupo de consulta. */
	protected function register_query_section() {
		$this->start_controls_section(
			'section_query',
			array(
				'label' => esc_html__( 'Consulta', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);
		$this->end_controls_section();
	}

	public function register_pagination_section_controls() {
		parent::register_pagination_section_controls();

		$this->remove_responsive_control( 'align' );

		$this->start_injection(
			array(
				'of' => 'pagination_align',
				'at' => 'after',
			)
		);

		$this->add_responsive_control(
			'load_more_button_align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'start'   => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-text-align-center',
					),
					'end'     => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'default'              => 'center',
				'condition'            => array(
					'pagination_type' => 'load_more_on_click',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'selectors_dictionary' => array(
					'start'   => '--digi-loop-more-align: start;',
					'center'  => '--digi-loop-more-align: center;',
					'end'     => '--digi-loop-more-align: end;',
					'justify' => '--digi-loop-more-width: 100%;',
				),
			)
		);

		$this->add_control(
			'pagination_load_type',
			array(
				'label'              => esc_html__( 'Tipo de carga', 'pro-elements' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'page_reload',
				'options'            => array(
					'page_reload' => esc_html__( 'Recargar la página', 'pro-elements' ),
					'ajax'        => esc_html__( 'Sin recargar (AJAX)', 'pro-elements' ),
				),
				'frontend_available' => true,
				'condition'          => array(
					'pagination_type' => array( 'numbers', 'prev_next', 'numbers_and_prev_next' ),
				),
				'separator'          => 'before',
			)
		);

		$this->add_control(
			'auto_scroll',
			array(
				'label'              => esc_html__( 'Desplazar al bucle', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => '',
				'condition'          => array(
					'pagination_load_type' => array( 'ajax' ),
				),
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'auto_scroll_offset',
			array(
				'label'     => esc_html__( 'Margen del desplazamiento', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 0,
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-loop-scroll-offset: {{VALUE}}px;',
				),
				'condition' => array(
					'pagination_load_type' => array( 'ajax' ),
					'auto_scroll'          => 'yes',
				),
			)
		);

		$this->end_injection();

		// Sin las flechas de entidad HTML que Entradas pone en las etiquetas de anterior y siguiente.
		$this->update_control( 'pagination_prev_label', array( 'default' => esc_html__( 'Anterior', 'pro-elements' ) ) );
		$this->update_control( 'pagination_next_label', array( 'default' => esc_html__( 'Siguiente', 'pro-elements' ) ) );

		$individual = array(
			'pagination_type'      => array( 'numbers', 'numbers_and_prev_next', 'prev_next' ),
			'pagination_load_type' => array( 'page_reload' ),
		);
		$this->update_control( 'pagination_individual_divider', array( 'condition' => $individual ) );
		$this->update_control( 'pagination_individual_handle', array( 'condition' => $individual ) );
		$this->update_control(
			'pagination_individual_handle_message',
			array(
				'raw'       => esc_html__( 'Con varios bucles en la misma página, actívalo para paginar cada uno por separado. Cambia la estructura de la URL de la página.', 'pro-elements' ),
				'condition' => $individual,
			)
		);
	}

	protected function register_additional_options_section_controls() {}

	protected function register_design_layout_controls() {}

	protected function register_design_nothing_found_message_controls() {}

	protected function register_design_navigation_controls() {}

	protected function register_design_pagination_controls() {}

	public function register_settings_section_controls() {}

	public function register_navigation_section_controls() {}

	/** Abre el contenedor de los elementos (la piel lo llama tras consultar y antes del primer elemento). */
	abstract public function render_loop_header();

	/** Cierra el contenedor y pinta lo que va después (paginación, navegación del carrusel). */
	abstract public function render_loop_footer();

	/** Mensaje «No se encontró nada» (la piel lo llama sin resultados). */
	public function render_nothing_found() {}

	/** Antes y después del bucle de la piel: los widgets enganchan aquí los atributos de cada elemento. */
	public function before_skin_render() {
		add_filter( 'elementor/document/wrapper_attributes', array( $this, 'filter_loop_item_attributes' ), 10, 2 );
	}

	public function after_skin_render() {
		remove_filter( 'elementor/document/wrapper_attributes', array( $this, 'filter_loop_item_attributes' ), 10 );
	}

	/** `elementor/document/wrapper_attributes` mientras dura el bucle: sólo los documentos de Elemento de bucle. */
	public function filter_loop_item_attributes( $attributes, $document = null ) {
		if ( is_object( $document ) && LoopDocument::DOCUMENT_TYPE === $document::get_type() && is_array( $attributes ) ) {
			$attributes = $this->loop_item_attributes( $attributes );
		}
		return $attributes;
	}

	/** Atributos de cada elemento del bucle (`class` ya trae `e-loop-item`). */
	protected function loop_item_attributes( array $attributes ): array {
		return $attributes;
	}

	/** @var bool El CSS dinámico de cada elemento va después del contenedor (lo guarda la piel en `$deferred_css`). */
	public $defer_dynamic_css = false;

	/** @var string CSS dinámico guardado mientras dura el bucle (con `$defer_dynamic_css`). */
	public $deferred_css = '';

	/** Antes del primer elemento (tras `render_loop_header()`): con `$defer_dynamic_css`, guarda lo que se pinta. */
	public function begin_items() {
		if ( $this->defer_dynamic_css ) {
			ob_start();
		}
	}

	/**
	 * Tras el último elemento (antes de `render_loop_footer()`): con `$defer_dynamic_css`, saca del contenedor las hojas
	 * que el núcleo imprime entre los elementos (`<style id="loop-<plantilla>">` del documento del bucle,
	 * `<style id="loop-dynamic-…">` y, en el editor, `<link id="fonts-loop-…">`) y las deja en `$deferred_css`.
	 */
	public function end_items() {
		if ( ! $this->defer_dynamic_css ) {
			return;
		}
		$html = (string) ob_get_clean();
		$html = (string) preg_replace_callback(
			'#<style id="loop-[^"]*">.*?</style>|<link [^>]*id="fonts-loop-[^"]*"[^>]*>#s',
			function ( $m ) {
				$this->deferred_css .= $m[0];
				return '';
			},
			$html
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- elementos ya pintados por el núcleo.
	}

	/** @var int|null Elementos que pintará el bucle; lo fija la piel antes de `render_loop_header()`. */
	public $loop_size = null;

	/** Número de elementos que pintará el bucle (entradas de la página o términos), tras consultar. */
	public function loop_count(): int {
		if ( null !== $this->loop_size ) {
			return (int) $this->loop_size;
		}
		return $this->query ? (int) $this->query->post_count : 0;
	}
}
