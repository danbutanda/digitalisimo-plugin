<?php
namespace Digitalisimo\Elements\Loop\Widgets;

use Digitalisimo\Elements\Loop\Archive_Skin_Cards;
use Digitalisimo\Elements\Loop\Archive_Skin_Classic;
use Digitalisimo\Elements\Loop\Archive_Skin_Full_Content;
use Digitalisimo\Elements\Loop\Posts_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Entradas del archivo (`digitalisimo-archive-posts`): sustituye al widget «Archive Posts» de PRO
 * (`modules/theme-builder/widgets/archive-posts.php`, mapa `modules/digitalisimo-convert/maps/archive-posts.php`).
 * Repite sus controles (los de `Posts_Base`, sus tres pieles `archive_*` y el mensaje «Nada encontrado») con el mismo
 * nombre, valor por defecto (la paginación, «Números») y condiciones. Lista la consulta principal de la vista, con el
 * mismo filtro que PRO (`elementor/theme/posts_archive/query_posts/query_vars`) y añadiendo lo listado a «Evitar
 * duplicados». Usa la hoja de Entradas.
 *
 * Derivado de PRO Elements (GPL): `modules/theme-builder/widgets/archive-posts.php`.
 */
class Archive_Posts extends Posts_Base {

	public function get_name() {
		return 'digitalisimo-archive-posts';
	}

	public function get_title() {
		return esc_html__( 'Entradas del archivo', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-archive-posts';
	}

	public function get_categories() {
		return array( 'theme-elements-archive' );
	}

	public function get_keywords() {
		return array( 'entradas', 'archivo', 'posts', 'blog', 'categoría', 'bucle', 'consulta', 'tarjetas', 'listado' );
	}

	/** Sin envoltorio interior con el marcado optimizado, como el widget de PRO. */
	public function has_widget_inner_wrapper(): bool {
		$plugin = class_exists( '\\Elementor\\Plugin' ) ? \Elementor\Plugin::$instance : null;
		return ! ( $plugin && isset( $plugin->experiments ) && is_object( $plugin->experiments ) && $plugin->experiments->is_feature_active( 'e_optimized_markup' ) );
	}

	/** Usa la hoja de Entradas. */
	protected function own_assets_name(): string {
		return 'digitalisimo-posts';
	}

	protected function needs_script(): bool {
		$skin = $this->get_current_skin();
		return parent::needs_script() || ( $skin && 'yes' === $skin->get_instance_value( 'masonry' ) );
	}

	protected function register_skins() {
		$this->add_skin( new Archive_Skin_Classic( $this ) );
		$this->add_skin( new Archive_Skin_Cards( $this ) );
		$this->add_skin( new Archive_Skin_Full_Content( $this ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->register_pagination_section_controls();

		$this->register_advanced_section_controls();

		$this->update_control( 'pagination_type', array( 'default' => 'numbers' ) );
	}

	public function register_advanced_section_controls() {
		$this->start_controls_section(
			'section_advanced',
			array( 'label' => esc_html__( 'Avanzado', 'pro-elements' ) )
		);

		$this->add_control(
			'nothing_found_message',
			array(
				'label'   => esc_html__( 'Mensaje si no hay entradas', 'pro-elements' ),
				'type'    => Controls_Manager::TEXTAREA,
				'default' => esc_html__( 'Parece que no encontramos lo que buscas.', 'pro-elements' ),
				'dynamic' => array( 'active' => true ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_nothing_found_style',
			array(
				'tab'       => Controls_Manager::TAB_STYLE,
				'label'     => esc_html__( 'Mensaje si no hay entradas', 'pro-elements' ),
				'condition' => array( 'nothing_found_message!' => '' ),
			)
		);

		$this->add_control(
			'nothing_found_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => 'globals/colors?id=text' ),
				'selectors' => array( '{{WRAPPER}} .digi-posts__nothing' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'nothing_found_typography',
				'global'   => array( 'default' => 'globals/typography?id=text' ),
				'selector' => '{{WRAPPER}} .digi-posts__nothing',
			)
		);

		$this->end_controls_section();
	}

	public function query_posts() {
		global $wp_query;

		$query_vars = $wp_query->query_vars;

		/** Mismo filtro que PRO: las variables de la consulta del archivo. */
		$query_vars = apply_filters( 'elementor/theme/posts_archive/query_posts/query_vars', $query_vars );

		$this->query = $query_vars !== $wp_query->query_vars ? new \WP_Query( $query_vars ) : $wp_query;

		if ( class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
			\ElementorPro\Modules\QueryControl\Module::add_to_avoid_list( wp_list_pluck( $this->query->posts, 'ID' ) );
		}
	}
}
