<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Comentarios (`digitalisimo-post-comments`): sustituye al widget «Post Comments» de PRO
 * (`modules/theme-elements/widgets/post-comments.php`, mapa `modules/digitalisimo-convert/maps/post-comments.php`). Repite
 * sus controles (piel oculta, «Comentarios del tema», origen y entrada elegida) con el mismo nombre y valor por defecto.
 *
 * Igual que PRO pinta la plantilla de comentarios del tema (o la de WordPress) con `comments_template()`, sin marcado,
 * hoja ni scripts propios: con «Personalizado», el núcleo cambia a la entrada elegida (`switch_to_post`) y la devuelve al
 * terminar. En el editor y en la vista previa, con los comentarios cerrados, avisa en lugar de pintar nada (como PRO, con
 * un aviso propio en español).
 */
class Post_Comments extends \Elementor\Widget_Base {

	const SOURCE_CURRENT = 'current_post';
	const SOURCE_CUSTOM  = 'custom';

	public function get_name() {
		return 'digitalisimo-post-comments';
	}

	public function get_title() {
		return esc_html__( 'Comentarios', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-comments';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'comentarios', 'comments', 'entrada', 'post', 'respuestas', 'response', 'formulario', 'form' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	/** Tipo del control de búsqueda de entradas del constructor (el mismo que usa PRO). */
	private static function query_control(): string {
		return class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ? \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID : 'query';
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_content', array( 'label' => esc_html__( 'Comentarios', 'pro-elements' ) ) );

		$this->add_control( '_skin', array( 'type' => Controls_Manager::HIDDEN ) );

		$this->add_control(
			'skin_temp',
			array(
				'label'       => esc_html__( 'Piel', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array( '' => esc_html__( 'Comentarios del tema', 'pro-elements' ) ),
				'description' => esc_html__( 'Usa el diseño de comentarios del tema activo para mostrar el formulario y los comentarios.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'source_type',
			array(
				'label'     => esc_html__( 'Origen', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					self::SOURCE_CURRENT => esc_html__( 'Entrada actual', 'pro-elements' ),
					self::SOURCE_CUSTOM  => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'default'   => self::SOURCE_CURRENT,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'source_custom',
			array(
				'label'        => esc_html__( 'Buscar y elegir', 'pro-elements' ),
				'type'         => self::query_control(),
				'label_block'  => true,
				'autocomplete' => array( 'object' => 'post' ),
				'condition'    => array( 'source_type' => self::SOURCE_CUSTOM ),
			)
		);

		$this->end_controls_section();
	}

	/** El núcleo del constructor (cambio de entrada y modos del editor), o `null` fuera de él. */
	private static function elementor() {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) && is_object( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
	}

	/** ¿Editor o vista previa del constructor? */
	private static function editing(): bool {
		$el = self::elementor();
		return $el && ( ( isset( $el->preview ) && $el->preview->is_preview_mode() ) || ( isset( $el->editor ) && $el->editor->is_edit_mode() ) );
	}

	protected function render() {
		$settings = (array) $this->get_settings();
		$custom   = self::SOURCE_CUSTOM === ( $settings['source_type'] ?? self::SOURCE_CURRENT );
		$el       = self::elementor();
		$switched = false;
		if ( $custom && $el && isset( $el->db ) ) {
			$el->db->switch_to_post( (int) ( $settings['source_custom'] ?? 0 ) );
			$switched = true;
		}

		if ( ! comments_open() && self::editing() ) {
			echo '<div class="digi-post-comments__alert" role="alert"><strong>' . esc_html__( 'Los comentarios están cerrados.', 'pro-elements' ) . '</strong> <span>' . esc_html__( 'Actívalos en el cuadro «Comentarios» de la pantalla de edición de la entrada o en Ajustes → Comentarios de WordPress.', 'pro-elements' ) . '</span></div>';
		} else {
			comments_template();
		}

		if ( $switched ) {
			$el->db->restore_current_post();
		}
	}

	public function render_plain_content() {}
}
