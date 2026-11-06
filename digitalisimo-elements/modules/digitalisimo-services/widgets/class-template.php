<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Plantilla (`digitalisimo-template`): sustituye al widget «Template» de PRO (`modules/library/widgets/template.php`;
 * mapa `modules/digitalisimo-convert/maps/template.php`). Pinta una plantilla de la biblioteca dentro de la página. Repite
 * su control con el mismo nombre, tipo y consulta (`template_id`, el control de consulta de plantillas); derivado de PRO
 * Elements (GPL), el marcado es propio.
 *
 * - Misma regla de pintado que PRO: sólo una plantilla publicada, con `get_builder_content_for_display()` del núcleo.
 * - **Sin bucles:** una plantilla que se incluye a sí misma (directamente o a través de otra Plantilla) no se pinta; en el
 *   editor lo dice un aviso. PRO dejaba que el núcleo la repitiera hasta agotar la pila.
 * - **Sobrescrituras de F7b** (`digitalisimo_overrides` y `_digitalisimo_slot`): `F7b\Overrides` reconoce este widget
 *   como al de PRO (controles en `section_template`, aplicación al pintar, usos y referencias de kit).
 * - Kits: `template_id` se traduce al importar como en PRO (`on_import_update_dynamic_content()`, con los IDs de
 *   entradas del importador del núcleo).
 * - Sin hoja ni script propios.
 */
class Template extends \Elementor\Widget_Base {

	/** @var int[] Plantillas que se están pintando ahora (de fuera adentro). */
	private static $stack = array();

	public function get_name() {
		return 'digitalisimo-template';
	}

	public function get_title() {
		return esc_html__( 'Plantilla', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-document-file';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'plantilla', 'template', 'biblioteca', 'library', 'bloque', 'sección', 'reutilizable' );
	}

	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	public function is_reload_preview_required() {
		return false;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_template',
			array(
				'label' => esc_html__( 'Plantilla', 'pro-elements' ),
			)
		);

		$plugin = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$types  = is_object( $plugin ) && isset( $plugin->documents ) && is_object( $plugin->documents ) ? (array) $plugin->documents->get_document_types( array( 'show_in_library' => true ) ) : array();
		$query  = '\ElementorPro\Modules\QueryControl\Module';

		$this->add_control(
			'template_id',
			array(
				'label'        => esc_html__( 'Elige una plantilla', 'pro-elements' ),
				'type'         => class_exists( $query ) ? $query::QUERY_CONTROL_ID : \Elementor\Controls_Manager::NUMBER,
				'label_block'  => true,
				'autocomplete' => array(
					'object' => class_exists( $query ) ? $query::QUERY_OBJECT_LIBRARY_TEMPLATE : 'library_template',
					'query'  => array(
						'meta_query' => array(
							array(
								'key'     => '_elementor_template_type',
								'value'   => array_keys( $types ),
								'compare' => 'IN',
							),
						),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	/** ID de la plantilla elegida, o 0. */
	private function template(): int {
		$id = $this->get_settings( 'template_id' );
		return is_scalar( $id ) ? absint( $id ) : 0;
	}

	/** ¿Pintarla crearía un bucle? (ya se está pintando, o es el documento que la contiene) */
	public static function loops( int $template ): bool {
		if ( in_array( $template, self::$stack, true ) ) {
			return true;
		}
		$plugin  = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		$current = is_object( $plugin ) && isset( $plugin->documents ) && is_object( $plugin->documents ) && method_exists( $plugin->documents, 'get_current' ) ? $plugin->documents->get_current() : null;
		return is_object( $current ) && method_exists( $current, 'get_main_id' ) && (int) $current->get_main_id() === $template;
	}

	protected function render() {
		$template = $this->template();
		if ( ! $template || 'publish' !== get_post_status( $template ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( self::loops( $template ) ) {
			if ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) {
				echo '<p class="digi-template__loop">' . esc_html__( 'Esta plantilla se incluye a sí misma: no se pinta para no repetirse sin fin.', 'pro-elements' ) . '</p>';
			}
			return;
		}
		self::$stack[] = $template;
		try {
			$content = (string) $plugin->frontend->get_builder_content_for_display( $template );
		} finally {
			array_pop( self::$stack );
		}
		echo '<div class="digi-template">' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML del constructor, como en PRO.
	}

	public function render_plain_content() {}

	public function render_markdown(): string {
		$template = $this->template();
		if ( ! $template || 'publish' !== get_post_status( $template ) || self::loops( $template ) || ! class_exists( '\Elementor\Modules\MarkdownRender\Markdown_Renderer' ) ) {
			return '';
		}
		$document = \Elementor\Plugin::$instance->documents->get( $template );
		if ( ! $document ) {
			return '';
		}
		self::$stack[] = $template;
		try {
			return ( new \Elementor\Modules\MarkdownRender\Markdown_Renderer() )->render_elements_data( $document->get_elements_data() );
		} finally {
			array_pop( self::$stack );
		}
	}

	/**
	 * Importación (núcleo y kits): `template_id` pasa al ID nuevo de la plantilla, como el control de consulta de PRO
	 * (`base/on-import-trait.php`), además de las etiquetas dinámicas del núcleo.
	 */
	public static function on_import_update_dynamic_content( array $config, array $data, $controls = null ): array {
		$config = parent::on_import_update_dynamic_content( $config, $data, $controls );
		$old    = $config['settings']['template_id'] ?? null;
		$map    = isset( $data['post_ids'] ) && is_array( $data['post_ids'] ) ? $data['post_ids'] : array();
		if ( is_scalar( $old ) && '' !== (string) $old && isset( $map[ $old ] ) ) {
			$config['settings']['template_id'] = $map[ $old ];
		}
		return $config;
	}
}
