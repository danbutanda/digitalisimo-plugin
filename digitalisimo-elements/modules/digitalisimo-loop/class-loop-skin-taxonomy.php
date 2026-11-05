<?php
namespace Digitalisimo\Elements\Loop;

use ElementorPro\Modules\LoopBuilder\Documents\Loop as LoopDocument;
use ElementorPro\Modules\LoopBuilder\Module as LoopBuilderModule;
use ElementorPro\Modules\LoopBuilder\Providers\Taxonomy_Loop_Provider;
use ElementorPro\Modules\LoopFilter\Traits\Taxonomy_Filter_Trait;
use ElementorPro\Modules\QueryControl\Controls\Group_Control_Taxonomy;
use ElementorPro\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Piel base de los bucles de términos (`post_taxonomy`, `product_taxonomy`): repite
 * `modules/loop-builder/skins/skin-loop-taxonomy-base.php` de PRO con su proveedor de consulta
 * (`Taxonomy_Loop_Provider`, el grupo `Group_Control_Taxonomy` y `Taxonomy_Filter_Trait::get_filtered_taxonomies()`),
 * que son función del constructor. Cada término se pinta con la plantilla del bucle y `$wp_query->loop_term` (lo leen
 * las etiquetas dinámicas de términos). Sin paginación, como PRO. Derivado de PRO Elements (GPL).
 */
abstract class Loop_Skin_Taxonomy extends Loop_Skin {
	use Taxonomy_Filter_Trait;

	/** @var string Tipo de contenido de los términos (`post` o `product`). */
	protected $post_type = 'post';

	/** @var Taxonomy_Loop_Provider|null */
	protected $provider;

	abstract protected function get_default_source_option();

	protected function init_provider() {
		$this->provider = new Taxonomy_Loop_Provider( $this->get_id(), $this->get_default_source_option() );
	}

	public function render() {
		$template_id      = $this->parent->get_settings_for_display( 'template_id' );
		$current_document = Plugin::elementor()->documents->get_current();

		if ( $template_id ) {
			if ( ! $this->provider ) {
				$this->init_provider();
			}
			$this->prepare_template_loop( (array) $this->get_terms(), $template_id );
		} elseif ( Plugin::elementor()->editor->is_edit_mode() ) {
			$this->render_empty_view();
		}

		if ( $current_document ) {
			Plugin::elementor()->documents->switch_to_document( $current_document );
		}
	}

	protected function prepare_template_loop( array $terms, $template_id ) {
		global $wp_query;
		$wp_query->is_loop_taxonomy = ! empty( $terms );

		$widget = $this->parent;
		$this->enqueue_loop_document_css_meta( $template_id );
		$widget->before_skin_render();
		$widget->loop_size = count( $terms );
		$widget->render_loop_header();
		$widget->begin_items();

		foreach ( $terms as $term ) {
			$wp_query->loop_term = $term;
			$this->render_post();
			$wp_query->loop_term = null;
		}

		$widget->end_items();
		$widget->render_loop_footer();
		$widget->loop_size = null;
		$widget->after_skin_render();

		$wp_query->is_loop_taxonomy = false;
	}

	/** Cada término con la plantilla del bucle y su CSS dinámico (por el ID del término). */
	protected function render_post() {
		$template_id = $this->parent->get_settings_for_display( 'template_id' );
		/** @var LoopDocument|false $document */
		$document = Plugin::elementor()->documents->get( $template_id );
		if ( ! $document ) {
			return;
		}
		global $wp_query;
		$term_id = isset( $wp_query->loop_term->term_id ) ? (int) $wp_query->loop_term->term_id : 0;
		$this->loop_dynamic_css( $term_id, $template_id );
		$document->print_content();
	}

	public function register_query_controls( $widget ) {
		$this->parent = $widget;
		$this->init_provider();
		$post_type_options   = $this->get_taxonomy_options( Taxonomy_Loop_Provider::get_supported_cpts( $this->post_type ) );
		$default_source_type = $this->get_default_source_option();

		$this->add_group_control(
			Group_Control_Taxonomy::get_type(),
			array(
				'name'                            => Taxonomy_Loop_Provider::QUERY_CONTROL_GROUP_NAME,
				Taxonomy_Loop_Provider::POST_TYPE => Taxonomy_Loop_Provider::get_default_source_type( $this->get_id() ),
				'presets'                         => array( 'include', 'exclude' ),
				'fields_options'                  => array(
					Taxonomy_Loop_Provider::POST_TYPE   => array(
						'default' => $default_source_type,
						'options' => $post_type_options,
					),
					Taxonomy_Loop_Provider::FILTER_BY   => $this->provider->get_control_args( Taxonomy_Loop_Provider::FILTER_BY, false ),
					Taxonomy_Loop_Provider::INCLUDE_TAB => $this->provider->get_control_args( Taxonomy_Loop_Provider::INCLUDE_TAB, false ),
					Taxonomy_Loop_Provider::EXCLUDE_TAB => $this->provider->get_control_args( Taxonomy_Loop_Provider::EXCLUDE_TAB, false ),
				),
				'exclude'                         => array(
					'include',
					'exclude',
				),
			)
		);

		foreach ( array(
			Taxonomy_Loop_Provider::PARENT,
			Taxonomy_Loop_Provider::INCLUDE,
			Taxonomy_Loop_Provider::EXCLUDE,
			Taxonomy_Loop_Provider::ORDER_BY,
			Taxonomy_Loop_Provider::ORDER,
			Taxonomy_Loop_Provider::AVOID_DUPLICATES,
			Taxonomy_Loop_Provider::HIDE_EMPTY,
			Taxonomy_Loop_Provider::OFFSET,
			Taxonomy_Loop_Provider::HIERARCHICAL,
			Taxonomy_Loop_Provider::QUERY_DEPTH,
			Taxonomy_Loop_Provider::QUERY_ID,
		) as $control_id ) {
			$this->add_control( $control_id, $this->provider->get_control_args( $control_id, true ) );
		}
	}

	/** Términos que se pintan, con los ajustes del widget (como PRO). */
	protected function get_terms() {
		$display_settings = $this->parent->get_settings_for_display();
		$settings         = $this->provider->get_query_settings( $display_settings );

		add_filter( 'elementor/loop_taxonomy/args', array( $this, 'filter_loop_taxonomy_args' ), 10, 3 );
		$terms = $this->get_filtered_taxonomies( $settings, $settings );
		remove_filter( 'elementor/loop_taxonomy/args', array( $this, 'filter_loop_taxonomy_args' ) );

		$terms = is_array( $terms ) ? $terms : array();
		LoopBuilderModule::add_to_taxonomies_avoid_list( wp_list_pluck( $terms, 'term_id' ) );
		return $terms;
	}

	/** «Evitar duplicados» entre bucles de términos de la misma página (la lista de PRO). */
	public function filter_loop_taxonomy_args( $args, $settings, $display_settings ) {
		$key     = $this->provider->get_property_name( 'avoid_duplicates' );
		$current = $this->parent->get_settings_for_display();
		if ( isset( $current[ $key ] ) && 'yes' === $current[ $key ] ) {
			$exclude         = isset( $args['exclude'] ) && is_array( $args['exclude'] ) ? $args['exclude'] : array();
			$args['exclude'] = array_merge( $exclude, LoopBuilderModule::get_taxonomies_avoid_list_ids() );
		}
		return $args;
	}
}
