<?php
namespace Digitalisimo\Elements\Loop\Widgets;

use Digitalisimo\Elements\Loop\Posts_Base;
use Digitalisimo\Elements\Loop\Skin_Cards;
use Digitalisimo\Elements\Loop\Skin_Classic;
use Digitalisimo\Elements\Loop\Skin_Full_Content;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Entradas (`digitalisimo-posts`): sustituye al widget «Posts» de PRO (`modules/posts/widgets/posts.php`, mapa
 * `modules/digitalisimo-convert/maps/posts.php`). Repite sus controles con el mismo nombre, valor por defecto y
 * condiciones: los de `Posts_Base` (paginación y botón «Cargar más»), los de sus tres pieles (clásica, tarjetas y
 * contenido completo, prefijados por la piel como en PRO), el grupo de consulta de PRO con el mismo prefijo (`posts_`) y
 * las categorías y etiquetas que Elements añade a la piel clásica (`digitalisimo_post_terms`). La consulta es la del
 * constructor (`Elementor_Post_Query`, con `elementor/query/{id}` y la exclusión de duplicados); el marcado, las clases y
 * los recursos son propios.
 *
 * Recursos: `posts.css` (≤ 4 KB) y, sólo si pagina, `loop.css`; `loop.js` sólo con mampostería, «Cargar más» o
 * desplazamiento infinito. Sin jQuery ni los paquetes de PRO.
 *
 * Derivado de PRO Elements (GPL): `modules/posts/widgets/posts.php`, `modules/posts/traits/query-note-trait.php`.
 */
class Posts extends Posts_Base {

	public function get_name() {
		return 'digitalisimo-posts';
	}

	public function get_title() {
		return esc_html__( 'Entradas', 'pro-elements' );
	}

	public function get_keywords() {
		return array( 'entradas', 'posts', 'blog', 'artículos', 'tipo de contenido', 'bucle', 'consulta', 'tarjetas', 'listado' );
	}

	public function on_import( $element ) {
		if ( isset( $element['settings']['posts_post_type'] ) && function_exists( 'get_post_type_object' ) && ! get_post_type_object( $element['settings']['posts_post_type'] ) ) {
			$element['settings']['posts_post_type'] = 'post';
		}

		return $element;
	}

	protected function register_skins() {
		$this->add_skin( new Skin_Classic( $this ) );
		$this->add_skin( new Skin_Cards( $this ) );
		$this->add_skin( new Skin_Full_Content( $this ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->register_terms_controls();
		$this->register_query_section_controls();
		$this->register_pagination_section_controls();
	}

	/** Mampostería de la piel actual (la clásica y las tarjetas la tienen). */
	protected function needs_script(): bool {
		$skin = $this->get_current_skin();
		return parent::needs_script() || ( $skin && 'yes' === $skin->get_instance_value( 'masonry' ) );
	}

	/**
	 * Categorías y etiquetas tras los metadatos de la piel clásica: los mismos controles que Elements añade al widget de
	 * PRO (`digitalisimo-widgets/class-posts-terms.php`), así lo que llega convertido se sigue pintando.
	 */
	protected function register_terms_controls() {
		$this->start_controls_section(
			'digitalisimo_post_terms_section',
			array(
				'label'     => esc_html__( 'Categorías y etiquetas', 'pro-elements' ),
				'condition' => array( '_skin' => 'classic' ),
			)
		);
		$this->add_control(
			'digitalisimo_post_terms',
			array(
				'label'    => esc_html__( 'Mostrar', 'pro-elements' ),
				'type'     => Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => array(),
				'options'  => array(
					'category' => esc_html__( 'Categorías', 'pro-elements' ),
					'post_tag' => esc_html__( 'Etiquetas', 'pro-elements' ),
				),
			)
		);
		$this->add_control(
			'digitalisimo_tags_label',
			array(
				'label'   => esc_html__( 'Texto antes de las etiquetas', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);
		$this->end_controls_section();
	}

	protected function register_query_section_controls() {
		$this->start_controls_section(
			'section_query',
			array(
				'label' => esc_html__( 'Consulta', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		if ( class_exists( '\ElementorPro\Modules\QueryControl\Controls\Group_Control_Related' ) ) {
			$this->add_group_control(
				\ElementorPro\Modules\QueryControl\Controls\Group_Control_Related::get_type(),
				array(
					'name'    => $this->get_query_name(),
					'presets' => array( 'full' ),
					'exclude' => array( 'posts_per_page' ),
				)
			);
		}

		if ( $this->is_editing_archive_template() ) {
			$this->inject_archive_query_note( 'posts_query_id', 'posts_post_type' );
		}

		$this->end_controls_section();
	}

	/** ¿Se edita una plantilla de archivo? (Como `Query_Note_Trait` de PRO.) */
	protected function is_editing_archive_template() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->documents ) || ! function_exists( 'get_post_meta' ) ) {
			return false;
		}
		$current = \Elementor\Plugin::$instance->documents->get_current();
		$id      = $current ? $current->get_main_id() : get_the_ID();
		return $id && 'archive' === get_post_meta( $id, '_elementor_template_type', true );
	}

	protected function inject_archive_query_note( $placement_id, $condition_id ) {
		$this->start_injection(
			array(
				'of' => $placement_id,
				'at' => 'before',
			)
		);

		$this->add_control(
			'archive_query_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: enlace a los ajustes de lectura. */
					esc_html__( 'El número de elementos del archivo lo fijan los ajustes de WordPress. %s', 'pro-elements' ),
					'<a target="_blank" href="' . esc_url( admin_url( 'options-reading.php' ) ) . '">' . esc_html__( 'Ir a los ajustes', 'pro-elements' ) . '</a>'
				),
				'content_classes' => 'elementor-descriptor',
				'condition'       => array( $condition_id => 'current_query' ),
			)
		);

		$this->end_injection();
	}

	public function query_posts() {
		$query_args = array(
			'posts_per_page'        => $this->get_posts_per_page_value(),
			'paged'                 => $this->get_current_page(),
			'has_custom_pagination' => $this->is_allow_to_use_custom_page_option(),
		);

		if ( ! class_exists( '\ElementorPro\Modules\QueryControl\Module' ) ) {
			$this->query = new \WP_Query( array( 'posts_per_page' => $query_args['posts_per_page'], 'paged' => $query_args['paged'] ) );
			return;
		}

		$this->query = \ElementorPro\Modules\QueryControl\Module::instance()->get_query( $this, $this->get_query_name(), $query_args, array() );
	}
}
