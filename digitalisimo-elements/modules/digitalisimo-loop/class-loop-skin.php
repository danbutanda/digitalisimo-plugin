<?php
namespace Digitalisimo\Elements\Loop;

use Elementor\Controls_Manager;
use Elementor\Core\Files\CSS\Post as Post_CSS;
use ElementorPro\Modules\LoopBuilder\Documents\Loop as LoopDocument;
use ElementorPro\Modules\LoopBuilder\Files\Css\Loop as Loop_CSS;
use ElementorPro\Modules\LoopBuilder\Files\Css\Loop_Css_Trait;
use ElementorPro\Modules\LoopBuilder\Files\Css\Loop_Preview;
use ElementorPro\Modules\LoopBuilder\Traits\Alternate_Templates_Trait;
use ElementorPro\Modules\QueryControl\Controls\Group_Control_Related;
use ElementorPro\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Piel base de los bucles propios (C5, frente J): repite `modules/loop-builder/skins/skin-loop-base.php` de PRO y el
 * recorrido de `modules/posts/skins/skin-base.php` (`render()`), con el contenedor y el pie de cada widget
 * (`Loop_Base::render_loop_header()`/`render_loop_footer()`).
 *
 * - Controles: el grupo de consulta de PRO (`Group_Control_Related`, `query`, sin `posts_per_page`) al empezar la
 *   sección `section_query` del widget, con el prefijo de la piel (`post_query_post_type`…), como PRO.
 * - Pintado: el documento del Elemento de bucle (`print_content()`) por cada entrada, con su CSS dinámico, y las
 *   plantillas alternas de PRO (`Alternate_Templates_Trait`, que también ajusta la consulta con las fijas).
 *
 * Derivado de PRO Elements (GPL): `modules/loop-builder/skins/skin-loop-base.php`, `modules/posts/skins/skin-base.php`.
 */
abstract class Loop_Skin extends \Elementor\Skin_Base {
	use Alternate_Templates_Trait;
	use Loop_Css_Trait;

	/** @var string Enlace de la entrada en curso (como PRO, por si un plugin lo filtra mientras se pinta). */
	protected $current_permalink;

	protected function _register_controls_actions() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- nombre del núcleo.
		add_action( 'elementor/element/' . $this->parent->get_name() . '/section_query/after_section_start', array( $this, 'register_query_controls' ) );
	}

	public function register_query_controls( $widget ) {
		$this->parent = $widget;

		$this->add_group_control(
			Group_Control_Related::get_type(),
			array(
				'name'    => Loop_Base::QUERY_ID,
				'presets' => array( 'full' ),
				'exclude' => array(
					'posts_per_page', // El de «Disposición».
				),
			)
		);

		if ( 'digitalisimo-loop-grid' === $widget->get_name() && self::is_editing_archive_template() ) {
			self::archive_query_note( $widget, $this->get_control_id( 'query_query_id' ), $this->get_control_id( 'query_post_type' ) );
		}
	}

	/** ¿Se edita una plantilla de archivo? (`Query_Note_Trait` de PRO). */
	public static function is_editing_archive_template(): bool {
		$id = Plugin::elementor()->documents->get_current() ? \ElementorPro\Core\Utils::get_current_post_id() : get_the_ID();
		return 'archive' === get_post_meta( (int) $id, \Elementor\Core\Base\Document::TYPE_META_KEY, true );
	}

	/** Nota de la consulta actual en una plantilla de archivo (`Query_Note_Trait::inject_archive_query_note()`). */
	public static function archive_query_note( $widget, string $placement_id, string $condition_id ): void {
		$widget->start_injection(
			array(
				'of' => $placement_id,
				'at' => 'before',
			)
		);
		$widget->add_control(
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
		$widget->end_injection();
	}

	/** La consulta con plantillas alternas fijas (o `false`: la del widget). */
	public function query_posts() {
		return $this->query_posts_for_alternate_templates();
	}

	/** CSS del documento del Elemento de bucle, antes de recorrer los elementos (como PRO). */
	protected function enqueue_loop_document_css_meta( $post_id ) {
		if ( ! empty( get_post_meta( $post_id, Post_CSS::META_KEY ) ) ) {
			return;
		}
		$css_file = wp_is_post_autosave( $post_id ) ? Loop_Preview::create( $post_id ) : Loop_CSS::create( $post_id );
		$css_file->update();
	}

	public function render() {
		$widget           = $this->parent;
		$template_id      = $widget->get_settings_for_display( 'template_id' );
		$current_document = Plugin::elementor()->documents->get_current();

		if ( $template_id ) {
			$this->enqueue_loop_document_css_meta( $template_id );
			$this->alternate_template_before_skin_render();
			$widget->before_skin_render();
			$this->render_loop();
			$widget->after_skin_render();
			$this->alternate_template_after_skin_render();
		} elseif ( Plugin::elementor()->editor->is_edit_mode() ) {
			$this->render_empty_view();
		}

		if ( $current_document ) {
			Plugin::elementor()->documents->switch_to_document( $current_document );
		}
	}

	/** El recorrido de `Skin_Base::render()` de Entradas de PRO. */
	protected function render_loop() {
		$widget = $this->parent;
		$widget->query_posts();
		$query = $widget->get_query();

		if ( ! $query || ! $query->found_posts ) {
			$widget->render_nothing_found();
			return;
		}

		$widget->loop_size = $query->in_the_loop ? 1 : min( (int) $query->post_count, max( 1, (int) $widget->get_posts_per_page_value() ) );
		$widget->render_loop_header();
		$widget->begin_items();

		if ( $query->in_the_loop ) {
			// Es la consulta global y el bucle lo empezó el tema.
			$this->current_permalink = get_permalink();
			$this->render_post();
		} else {
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->add_render_hooks();
				$this->current_permalink = get_permalink();
				$this->render_post();
				$this->remove_render_hooks();
			}
		}

		wp_reset_postdata();

		$widget->end_items();
		$widget->render_loop_footer();
		$widget->loop_size = null;
	}

	protected function add_render_hooks() {
		global $wp_query;
		$wp_query->is_loop_widget = true;
		add_filter( 'elementor-pro/off-canvas/id', array( $this, 'filter_off_canvas_id' ) );
	}

	protected function remove_render_hooks() {
		global $wp_query;
		unset( $wp_query->is_loop_widget );
		remove_filter( 'elementor-pro/off-canvas/id', array( $this, 'filter_off_canvas_id' ) );
	}

	public function filter_off_canvas_id( $off_canvas_id ) {
		return $this->parent->get_id() . '-' . get_the_ID() . '-' . $off_canvas_id;
	}

	/** Cada entrada con la plantilla elegida (o la alterna que le toque). */
	protected function render_post() {
		if ( $this->has_alternate_templates() ) {
			$this->render_post_if_widget_has_alternate_templates();
		} else {
			$this->render_post_content( $this->parent->get_settings_for_display( 'template_id' ) );
		}
	}

	private function render_post_content( $template_id ) {
		/** @var LoopDocument|false $document */
		$document = Plugin::elementor()->documents->get( $template_id );
		if ( ! $document ) {
			return;
		}
		$this->loop_dynamic_css( get_the_ID(), $template_id );
		$document->print_content();
	}

	/**
	 * CSS dinámico de un elemento (`<style id="loop-dynamic-…">`, `Loop_Css_Trait`): delante del elemento, como PRO, o
	 * guardado en el widget para después del contenedor si el widget lo pide (`Loop_Base::$defer_dynamic_css`: en el
	 * carrusel, cada hijo de la pista es una diapositiva).
	 */
	protected function loop_dynamic_css( $post_id, $template_id ) {
		$widget = $this->parent;
		if ( empty( $widget->defer_dynamic_css ) ) {
			$this->print_dynamic_css( $post_id, $template_id );
			return;
		}
		ob_start();
		$this->print_dynamic_css( $post_id, $template_id );
		$widget->deferred_css .= (string) ob_get_clean();
	}

	/** Sin plantilla, en el editor: cómo empezar (PRO pintaba una llamada a la acción con su script del editor). */
	protected function render_empty_view() {
		echo '<div class="digi-loop__empty">' . esc_html__( 'Elige o crea una plantilla de Elemento de bucle en «Disposición».', 'pro-elements' ) . '</div>';
	}
}
