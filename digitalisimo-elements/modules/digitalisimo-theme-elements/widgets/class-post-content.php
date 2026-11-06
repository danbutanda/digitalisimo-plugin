<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Contenido de la entrada (`digitalisimo-post-content`): sustituye al widget «Post Content» de PRO
 * (`modules/theme-builder/widgets/post-content.php` con `render_post_content()` de
 * `modules/posts/skins/skin-content-base.php`; mapa `modules/digitalisimo-convert/maps/theme-post-content.php`). Repite
 * sus controles (alineación, color y tipografía, con los mismos nombres, valores por defecto y selectores) y su forma de
 * pintar el contenido:
 *
 * - formulario de contraseña si la entrada la pide;
 * - la guarda de PRO contra la recursión (una entrada no se pinta dentro de sí misma, a ninguna profundidad);
 * - en la vista previa del editor, la envoltura del constructor para editar el contenido;
 * - si se ve la propia plantilla del Theme Builder, el contenido de su «Vista previa» (`preview_id`);
 * - el contenido construido con el editor (`get_builder_content()` sin su CSS, que ya está en la página), pasado por
 *   `the_content` sin el filtro del constructor; si no lo hay, `the_content` de WordPress con la paginación
 *   (`wp_link_pages()`), como PRO.
 *
 * El envoltorio conserva la clase `elementor-widget-theme-post-content` además de la propia: es la que buscan el
 * «Indicador de progreso» («relativo al contenido de la entrada»), la regla del núcleo que respeta los márgenes de las
 * figuras del contenido y el CSS que un sitio ya tenga. Sin hoja ni script propios. En la vista previa de una plantilla
 * individual, como pinta el contenido, quita el aviso de PRO «falta el widget de contenido».
 *
 * Diferencia declarada con PRO: PRO dejaba marcada para toda la petición una entrada pintada con el `the_content` de
 * WordPress (un segundo widget de contenido de esa entrada ya no pintaba nada); el propio la libera al terminar, como PRO
 * ya hacía con el contenido construido con el editor. La guarda contra la recursión es la misma.
 */
class Post_Content extends \Elementor\Widget_Base {

	/** Clase del envoltorio de PRO que se conserva (ver la cabecera). */
	const COMPAT_CLASS = 'elementor-widget-theme-post-content';

	/** Documento del Theme Builder (PRO; el Theme Builder es del constructor, no un widget). */
	const THEME_DOCUMENT = 'ElementorPro\\Modules\\ThemeBuilder\\Documents\\Theme_Document';

	/** @var array<int,bool> Entradas que se están pintando (guarda contra la recursión, como PRO). */
	private static $did_posts = array();

	/** @var int Profundidad de la pintura en curso. */
	private static $level = 0;

	public function get_name() {
		return 'digitalisimo-post-content';
	}

	public function get_title() {
		return esc_html__( 'Contenido de la entrada', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-post-content';
	}

	public function get_categories() {
		return array( 'theme-elements-single' );
	}

	public function get_keywords() {
		return array( 'contenido', 'entrada', 'texto', 'content', 'post' );
	}

	/**
	 * PRO lo oculta del panel salvo en las plantillas individuales (las activan con `widgets_settings`). El propio sólo
	 * existe en la categoría «Individual», que sólo tienen esas plantillas, así que se ofrece en los mismos sitios.
	 */
	public function show_in_panel() {
		return true;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	protected function get_html_wrapper_class() {
		return parent::get_html_wrapper_class() . ' ' . self::COMPAT_CLASS;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'align',
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
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'   => 'typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		// Sin el CSS del contenido: ya está en la página y lo pisaría (como PRO).
		$this->render_post_content( false );
	}

	public function render_plain_content() {}

	/**
	 * El contenido de la entrada actual, con las mismas ramas que `Skin_Content_Base::render_post_content()` de PRO.
	 *
	 * @param bool $with_css Imprimir el CSS del contenido construido con el editor antes de él.
	 */
	public function render_post_content( $with_css = true ) {
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( post_password_required( $post->ID ) ) {
			echo get_the_password_form( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formulario de WordPress.
			return;
		}

		// Guarda contra la recursión: una entrada nunca se pinta dentro de sí misma.
		if ( isset( self::$did_posts[ $post->ID ] ) ) {
			return;
		}
		self::$level++;
		self::$did_posts[ $post->ID ] = true;

		$elementor    = \Elementor\Plugin::$instance;
		$editor       = $elementor->editor;
		$is_edit_mode = $editor->is_edit_mode();

		if ( $elementor->preview->is_preview_mode( $post->ID ) ) {
			$content = $elementor->preview->builder_wrapper( '' );
			self::drop_missing_content_notice();
		} else {
			$document = self::theme_document( $post->ID );
			// Al ver la plantilla del Theme Builder, el contenido de su vista previa.
			if ( $document ) {
				$preview_type = (string) $document->get_settings( 'preview_type' );
				$preview_id   = $document->get_settings( 'preview_id' );
				if ( 0 === strpos( $preview_type, 'single' ) && ! empty( $preview_id ) ) {
					$post = get_post( $preview_id );
					if ( ! $post ) {
						self::$level--;
						return;
					}
				}
			}

			// Sin modo de edición mientras se pinta: ni ajustes ni marcas del editor dentro del contenido.
			$editor->set_edit_mode( false );

			// A mano (no `the_content()`): ya estamos dentro de otro `the_content` sin el filtro del constructor.
			$content = $elementor->frontend->get_builder_content( $post->ID, $with_css );
			$elementor->frontend->remove_content_filter();

			if ( empty( $content ) ) {
				setup_postdata( $post );

				echo apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtro de WordPress.

				wp_link_pages(
					array(
						// Texto visible: el msgid y el dominio de PRO («Pages:» o la traducción del paquete de idioma de PRO).
						'before'      => '<div class="page-links elementor-page-links"><span class="page-links-title elementor-page-links-title">' . esc_html__( 'Pages:', 'elementor-pro' ) . '</span>', // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- mismo texto que PRO (posts/skins/skin-content-base.php).
						'after'       => '</div>',
						'link_before' => '<span>',
						'link_after'  => '</span>',
						'pagelink'    => '<span class="screen-reader-text">' . esc_html__( 'Página', 'pro-elements' ) . ' </span>%',
						'separator'   => '<span class="screen-reader-text">, </span>',
					)
				);

				$elementor->frontend->add_content_filter();
				$editor->set_edit_mode( $is_edit_mode );
				// PRO no vacía aquí su guarda: la misma entrada ya no se volvía a pintar en toda la petición.
				self::$level--;
				if ( 0 === self::$level ) {
					self::$did_posts = array();
				}
				return;
			}

			$elementor->frontend->remove_content_filters();
			$content = apply_filters( 'the_content', $content );
			$elementor->frontend->restore_content_filters();
		}

		$editor->set_edit_mode( $is_edit_mode );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido ya filtrado por `the_content`.

		self::$level--;
		if ( 0 === self::$level ) {
			self::$did_posts = array();
		}
	}

	/** Como PRO: el contenido en Markdown, salvo en un contenido construido con el editor. */
	public function render_markdown(): string {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return '';
		}
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( $document && $document->is_built_with_elementor() ) {
			return '';
		}
		$content = get_the_content();
		if ( empty( $content ) || ! class_exists( '\Elementor\Modules\MarkdownRender\Html_To_Markdown' ) ) {
			return '';
		}
		$content = str_replace( ']]>', ']]&gt;', (string) apply_filters( 'the_content', $content ) );
		return \Elementor\Modules\MarkdownRender\Html_To_Markdown::convert( $content );
	}

	/** El documento de una entrada si es una plantilla del Theme Builder, o `null`. */
	private static function theme_document( $post_id ) {
		try {
			$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		} catch ( \Exception $e ) {
			return null;
		}
		return $document && is_a( $document, self::THEME_DOCUMENT ) ? $document : null;
	}

	/**
	 * En la vista previa, una plantilla individual sin el widget de contenido de PRO deja en el pie el aviso «falta el
	 * widget de contenido» (`preview_error_handler` del documento). Este widget pinta el contenido, así que el aviso se
	 * quita antes de que corra (`wp_footer`, prioridad 9).
	 */
	private static function drop_missing_content_notice(): void {
		static $hooked = false;
		if ( $hooked ) {
			return;
		}
		$hooked = true;
		add_action(
			'wp_footer',
			static function () {
				global $wp_filter;
				if ( empty( $wp_filter['wp_footer'] ) || ! is_object( $wp_filter['wp_footer'] ) ) {
					return;
				}
				foreach ( (array) $wp_filter['wp_footer']->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						$function = $callback['function'] ?? null;
						if ( is_array( $function ) && is_object( $function[0] ) && 'preview_error_handler' === ( $function[1] ?? '' ) && is_a( $function[0], self::THEME_DOCUMENT ) ) {
							remove_action( 'wp_footer', $function, $priority );
						}
					}
				}
			},
			9
		);
	}
}
