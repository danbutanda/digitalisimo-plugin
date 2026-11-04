<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Botones para compartir (`digitalisimo-share-buttons`): sustituye al widget «Share Buttons» de PRO
 * (`modules/share-buttons/widgets/share-buttons.php` y su lista de redes en `modules/share-buttons/module.php`; mapa
 * `modules/digitalisimo-convert/maps/share-buttons.php`). Repite sus controles con el mismo nombre, los mismos valores
 * por defecto, opciones y condiciones; controles y lista de redes derivados de PRO Elements (GPL), el marcado, las clases
 * y la hoja son propios.
 *
 * - Cada botón es un **enlace normal** a la URL de compartir de su red, construido en el servidor con todo codificado
 *   (las plantillas de `elementor/assets/lib/share-link/share-link.js`, del núcleo): la URL de la página actual (la del
 *   objeto consultado, sin la consulta de la petición) o la personalizada, y como PRO el título del documento, el extracto
 *   y la imagen destacada (sólo con la página actual). Se abre en otra pestaña (`noopener noreferrer`); sin scripts de
 *   las redes, sin contadores y sin ninguna petición antes del clic. El correo es un `mailto:`; imprimir, un `<button>`
 *   con un script mínimo (`assets/js/share-buttons.js`, `defer`, sólo en las páginas con ese botón).
 * - Iconos de Font Awesome del núcleo (`Icons_Manager`, con los mismos datos que PRO; SVG en línea con el experimento de
 *   iconos SVG): ninguna fuente ni petición de terceros.
 * - Colores oficiales de cada red como variable en línea (`--digi-share-o`), en lugar de una regla por red y piel: la
 *   hoja (`assets/css/share-buttons.css`) reproduce las cinco pieles, las tres formas, las tres vistas y los colores
 *   personalizados de PRO con pocas reglas. Rejilla con las clases del núcleo (`elementor-grid`, `elementor-grid%s-`).
 *
 * Diferencias declaradas con PRO: enlaces con destino (PRO pintaba `div role="button"` y abría una ventana emergente con
 * su script al hacer clic), nombre accesible en español con el nombre de la red («Compartir en Facebook»; PRO usaba el
 * identificador en inglés), los títulos «Email» y «Print» se traducen («Correo», «Imprimir»), «Mix» (servicio cerrado,
 * sin URL de compartir en PRO) se pinta sin enlace, objetivos de 24 × 24 px como mínimo y foco visible propio, y el
 * relleno del texto (`text_padding`) se aplica al texto (en PRO apuntaba a un selector que su marcado no tiene).
 */
class Share_Buttons extends \Elementor\Widget_Base {

	/**
	 * Redes de PRO (`Module::$networks`, en su orden), con su color oficial (el de la hoja de PRO) y la plantilla de su
	 * URL de compartir (`share-link.js` del núcleo). `mix` no tiene plantilla: se pinta sin enlace.
	 */
	const NETWORKS = array(
		'facebook'      => array( 'Facebook', '#3b5998', 'https://www.facebook.com/sharer.php?u={url}' ),
		'twitter'       => array( 'Twitter', '#1da1f2', 'https://twitter.com/intent/tweet?text={text}%20{url}' ),
		'linkedin'      => array( 'LinkedIn', '#0077b5', 'https://www.linkedin.com/shareArticle?mini=true&url={url}&title={title}&summary={text}&source={url}' ),
		'pinterest'     => array( 'Pinterest', '#bd081c', 'https://www.pinterest.com/pin/create/button/?url={url}&media={image}' ),
		'reddit'        => array( 'Reddit', '#ff4500', 'https://reddit.com/submit?url={url}&title={title}' ),
		'vk'            => array( 'VK', '#45668e', 'https://vkontakte.ru/share.php?url={url}&title={title}&description={text}&image={image}' ),
		'odnoklassniki' => array( 'OK', '#f4731c', 'https://connect.ok.ru/offer?url={url}&title={title}&imageUrl={image}' ),
		'tumblr'        => array( 'Tumblr', '#35465c', 'https://tumblr.com/share/link?url={url}' ),
		'digg'          => array( 'Digg', '#005be2', 'https://digg.com/submit?url={url}' ),
		'skype'         => array( 'Skype', '#00aff0', 'https://web.skype.com/share?url={url}' ),
		'stumbleupon'   => array( 'StumbleUpon', '#eb4924', 'https://www.stumbleupon.com/submit?url={url}' ),
		'mix'           => array( 'Mix', '#f3782b', '' ),
		'telegram'      => array( 'Telegram', '#2ca5e0', 'https://telegram.me/share/url?url={url}&text={text}' ),
		'pocket'        => array( 'Pocket', '#ef3f56', 'https://getpocket.com/edit?url={url}' ),
		'xing'          => array( 'XING', '#026466', 'https://www.xing.com/spi/shares/new?url={url}' ),
		'whatsapp'      => array( 'WhatsApp', '#25d366', 'https://api.whatsapp.com/send?text=*{title}*%0A{text}%0A{url}' ),
		'email'         => array( 'Correo', '#ea4335', 'mailto:?subject={title}&body={text}%0A{url}' ),
		'print'         => array( 'Imprimir', '#aaa', '' ),
		'x-twitter'     => array( 'X', '#000', 'https://x.com/intent/tweet?text={text}%20{url}' ),
		'threads'       => array( 'Threads', '#000', 'https://threads.net/intent/post?text={text}%20{url}' ),
	);

	public function get_name() {
		return 'digitalisimo-share-buttons';
	}

	public function get_title() {
		return esc_html__( 'Botones para compartir', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-share';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'compartir', 'share', 'redes', 'social', 'botones', 'correo', 'imprimir' );
	}

	/** Hoja propia y, como PRO, las hojas de iconos de Font Awesome del núcleo (con la migración a FA5 permitida). */
	public function get_style_depends() {
		$out = Module::style_depends( $this->get_name() );
		if ( class_exists( '\Elementor\Icons_Manager' ) && \Elementor\Icons_Manager::is_migration_allowed() ) {
			$out[] = 'elementor-icons-fa-solid';
			$out[] = 'elementor-icons-fa-brands';
		}
		return $out;
	}

	/** El script de imprimir, sólo con un botón de imprimir (y en la instancia de registro, para el editor). */
	public function get_script_depends() {
		$type = method_exists( $this, 'is_type_instance' ) && $this->is_type_instance();
		if ( ! $type ) {
			$print = false;
			foreach ( (array) $this->get_settings( 'share_buttons' ) as $button ) {
				$print = $print || ( is_array( $button ) && 'print' === ( $button['button'] ?? '' ) );
			}
			if ( ! $print ) {
				return array();
			}
		}
		return Module::script_depends( $this->get_name() );
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';

		$this->start_controls_section( 'section_buttons_content', array( 'label' => esc_html__( 'Botones para compartir', 'pro-elements' ) ) );

		$repeater = new \Elementor\Repeater();
		$options  = array();
		foreach ( self::NETWORKS as $name => $network ) {
			$options[ $name ] = $network[0];
		}
		$repeater->add_control(
			'button',
			array(
				'label'   => esc_html__( 'Red', 'pro-elements' ),
				'type'    => $c::SELECT,
				'options' => $options,
				'default' => 'facebook',
			)
		);
		$repeater->add_control(
			'text',
			array(
				'label'   => esc_html__( 'Texto personalizado', 'pro-elements' ),
				'type'    => $c::TEXT,
				'dynamic' => array( 'active' => true ),
			)
		);
		$this->add_control(
			'share_buttons',
			array(
				'type'        => $c::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array( 'button' => 'facebook' ),
					array( 'button' => 'x-twitter' ),
					array( 'button' => 'linkedin' ),
				),
				'title_field' => '{{{ text || button }}}',
			)
		);

		$this->add_control(
			'view',
			array(
				'label'        => esc_html__( 'Vista', 'pro-elements' ),
				'type'         => $c::SELECT,
				'options'      => array(
					'icon-text' => esc_html__( 'Icono y texto', 'pro-elements' ),
					'icon'      => esc_html__( 'Icono', 'pro-elements' ),
					'text'      => esc_html__( 'Texto', 'pro-elements' ),
				),
				'default'      => 'icon-text',
				'separator'    => 'before',
				'prefix_class' => 'digi-share--view-',
				'render_type'  => 'template',
			)
		);
		$this->add_control(
			'show_label',
			array(
				'label'     => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type'      => $c::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'condition' => array( 'view' => 'icon-text' ),
			)
		);
		$this->add_control(
			'skin',
			array(
				'label'        => esc_html__( 'Piel', 'pro-elements' ),
				'type'         => $c::SELECT,
				'options'      => array(
					'gradient' => esc_html__( 'Degradado', 'pro-elements' ),
					'minimal'  => esc_html__( 'Mínima', 'pro-elements' ),
					'framed'   => esc_html__( 'Enmarcada', 'pro-elements' ),
					'boxed'    => esc_html__( 'Icono en caja', 'pro-elements' ),
					'flat'     => esc_html__( 'Plana', 'pro-elements' ),
				),
				'default'      => 'gradient',
				'prefix_class' => 'digi-share--skin-',
			)
		);
		$this->add_control(
			'shape',
			array(
				'label'        => esc_html__( 'Forma', 'pro-elements' ),
				'type'         => $c::SELECT,
				'options'      => array(
					'square'  => esc_html__( 'Cuadrada', 'pro-elements' ),
					'rounded' => esc_html__( 'Redondeada', 'pro-elements' ),
					'circle'  => esc_html__( 'Círculo', 'pro-elements' ),
				),
				'default'      => 'square',
				'prefix_class' => 'digi-share--shape-',
			)
		);
		$this->add_responsive_control(
			'columns',
			array(
				'label'        => esc_html__( 'Columnas', 'pro-elements' ),
				'type'         => $c::SELECT,
				'default'      => '0',
				'options'      => array(
					'0' => esc_html__( 'Automático', 'pro-elements' ),
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'prefix_class' => 'elementor-grid%s-',
			)
		);
		$this->add_responsive_control(
			'alignment',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => $c::CHOOSE,
				'options'   => array(
					'left'    => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-text-align-left' ),
					'center'  => array( 'title' => esc_html__( 'Centro', 'pro-elements' ), 'icon' => 'eicon-text-align-center' ),
					'right'   => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-text-align-right' ),
					'justify' => array( 'title' => esc_html__( 'Justificado', 'pro-elements' ), 'icon' => 'eicon-text-align-justify' ),
				),
				'condition' => array( 'columns' => '0' ),
				'selectors' => array( '{{WRAPPER}}' => '--digi-share-align: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'share_url_type',
			array(
				'label'     => esc_html__( 'URL que se comparte', 'pro-elements' ),
				'type'      => $c::SELECT,
				'options'   => array(
					'current_page' => esc_html__( 'Página actual', 'pro-elements' ),
					'custom'       => esc_html__( 'Personalizada', 'pro-elements' ),
				),
				'default'   => 'current_page',
				'separator' => 'before',
			)
		);
		$this->add_control(
			'share_url',
			array(
				'label'      => esc_html__( 'Enlace', 'pro-elements' ),
				'type'       => $c::URL,
				'dynamic'    => array( 'active' => true ),
				'options'    => false,
				'condition'  => array( 'share_url_type' => 'custom' ),
				'show_label' => false,
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_buttons_style',
			array(
				'label' => esc_html__( 'Botones para compartir', 'pro-elements' ),
				'tab'   => $c::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'column_gap',
			array(
				'label'      => esc_html__( 'Separación entre columnas', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'selectors'  => array(
					'{{WRAPPER}}'          => '--grid-side-margin: {{SIZE}}{{UNIT}}; --grid-column-gap: {{SIZE}}{{UNIT}}; --grid-row-gap: {{SIZE}}{{UNIT}}',
					'(tablet) {{WRAPPER}}' => '--grid-side-margin: {{SIZE}}{{UNIT}}; --grid-column-gap: {{SIZE}}{{UNIT}}',
					'(mobile) {{WRAPPER}}' => '--grid-side-margin: {{SIZE}}{{UNIT}}; --grid-column-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);
		$this->add_responsive_control(
			'row_gap',
			array(
				'label'      => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array( 'size' => 10 ),
				'selectors'  => array(
					'{{WRAPPER}}'          => '--grid-row-gap: {{SIZE}}{{UNIT}}; --grid-bottom-margin: {{SIZE}}{{UNIT}}',
					'(tablet) {{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --grid-bottom-margin: {{SIZE}}{{UNIT}}',
					'(mobile) {{WRAPPER}}' => '--grid-row-gap: {{SIZE}}{{UNIT}}; --grid-bottom-margin: {{SIZE}}{{UNIT}}',
				),
			)
		);
		$this->add_responsive_control(
			'button_size',
			array(
				'label'     => esc_html__( 'Tamaño del botón', 'pro-elements' ),
				'type'      => $c::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0.5, 'max' => 2, 'step' => 0.05 ) ),
				'selectors' => array( '{{WRAPPER}} .digi-share__btn' => 'font-size: calc({{SIZE}}{{UNIT}} * 10);' ),
			)
		);
		$this->add_responsive_control(
			'icon_size',
			array(
				'label'          => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type'           => $c::SLIDER,
				'size_units'     => array( 'px', 'em', 'rem', 'custom' ),
				'range'          => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'default'        => array( 'unit' => 'em' ),
				'tablet_default' => array( 'unit' => 'em' ),
				'mobile_default' => array( 'unit' => 'em' ),
				'selectors'      => array( '{{WRAPPER}} .digi-share__icon' => '--digi-share-icon-size: {{SIZE}}{{UNIT}};' ),
				'condition'      => array( 'view!' => 'text' ),
			)
		);
		$this->add_responsive_control(
			'button_height',
			array(
				'label'          => esc_html__( 'Altura del botón', 'pro-elements' ),
				'type'           => $c::SLIDER,
				'size_units'     => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'range'          => array(
					'px'  => array( 'max' => 100 ),
					'em'  => array( 'max' => 10 ),
					'rem' => array( 'max' => 10 ),
				),
				'default'        => array( 'unit' => 'em' ),
				'tablet_default' => array( 'unit' => 'em' ),
				'mobile_default' => array( 'unit' => 'em' ),
				'selectors'      => array( '{{WRAPPER}} .digi-share__btn' => 'height: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_responsive_control(
			'border_size',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => $c::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array( 'size' => 2 ),
				'range'      => array(
					'px'  => array( 'max' => 20 ),
					'em'  => array( 'max' => 2 ),
					'rem' => array( 'max' => 2 ),
				),
				'selectors'  => array( '{{WRAPPER}} .digi-share__btn' => 'border-width: {{SIZE}}{{UNIT}};' ),
				'condition'  => array( 'skin' => array( 'framed', 'boxed' ) ),
			)
		);
		$this->add_control(
			'color_source',
			array(
				'label'        => esc_html__( 'Color', 'pro-elements' ),
				'type'         => $c::SELECT,
				'options'      => array(
					'official' => esc_html__( 'Oficial', 'pro-elements' ),
					'custom'   => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'default'      => 'official',
				'prefix_class' => 'digi-share--color-',
				'separator'    => 'before',
			)
		);
		$this->start_controls_tabs( 'tabs_button_style', array( 'condition' => array( 'color_source' => 'custom' ) ) );
		$this->start_controls_tab( 'tab_button_normal', array( 'label' => esc_html__( 'Normal', 'pro-elements' ) ) );
		$this->add_control(
			'primary_color',
			array(
				'label'     => esc_html__( 'Color principal', 'pro-elements' ),
				'type'      => $c::COLOR,
				'default'   => '',
				'selectors' => array( '{{WRAPPER}}' => '--digi-share-primary: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'secondary_color',
			array(
				'label'     => esc_html__( 'Color secundario', 'pro-elements' ),
				'type'      => $c::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--digi-share-secondary: {{VALUE}}' ),
				'separator' => 'after',
				'condition' => array( 'skin!' => 'framed' ),
			)
		);
		$this->end_controls_tab();
		$this->start_controls_tab( 'tab_button_hover', array( 'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ) ) );
		$this->add_control(
			'primary_color_hover',
			array(
				'label'     => esc_html__( 'Color principal', 'pro-elements' ),
				'type'      => $c::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-share__btn:hover' => '--digi-share-primary: {{VALUE}}' ),
			)
		);
		$this->add_control(
			'secondary_color_hover',
			array(
				'label'     => esc_html__( 'Color secundario', 'pro-elements' ),
				'type'      => $c::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-share__btn:hover' => '--digi-share-secondary: {{VALUE}}' ),
				'separator' => 'after',
			)
		);
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .digi-share__title',
				'exclude'  => array( 'line_height' ),
			)
		);
		$this->add_control(
			'text_padding',
			array(
				'label'      => esc_html__( 'Relleno del texto', 'pro-elements' ),
				'type'       => $c::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-share__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'separator'  => 'before',
				'condition'  => array( 'view' => 'text' ),
			)
		);
		$this->end_controls_section();
	}

	/** Datos del icono de una red: los mismos que PRO (`get_network_icon_data()`), FA5 o FA4 según la migración. */
	public static function icon_data( string $network ): array {
		$migrated = class_exists( '\Elementor\Icons_Manager' ) && \Elementor\Icons_Manager::is_migration_allowed();
		if ( $migrated ) {
			$map = array(
				'pocket' => array( 'value' => 'fab fa-get-pocket', 'library' => 'fa-brands' ),
				'email'  => array( 'value' => 'fas fa-envelope', 'library' => 'fa-solid' ),
				'print'  => array( 'value' => 'fas fa-print', 'library' => 'fa-solid' ),
			);
			return $map[ $network ] ?? array( 'value' => 'fab fa-' . $network, 'library' => 'fa-brands' );
		}
		$map = array(
			'pocket' => array( 'value' => 'fa fa-get-pocket' ),
			'email'  => array( 'value' => 'fa fa-envelope' ),
		);
		return $map[ $network ] ?? array( 'value' => 'fa fa-' . $network, 'library' => '' );
	}

	/** Icono de una red, como PRO: SVG en línea con el experimento de iconos SVG del núcleo, si no, `<i>` de la fuente. */
	private static function icon( string $network ): string {
		$data = self::icon_data( $network );
		$svg  = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) && is_object( \Elementor\Plugin::$instance->experiments ) && \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_font_icon_svg' );
		if ( $svg && class_exists( '\Elementor\Icons_Manager' ) ) {
			return (string) \Elementor\Icons_Manager::render_font_icon( $data, array( 'aria-hidden' => 'true' ) );
		}
		return '<i class="' . esc_attr( $data['value'] ) . '" aria-hidden="true"></i>';
	}

	/**
	 * URL de la página que se comparte: la del objeto consultado (entrada, término, autor, archivo), sin la consulta de la
	 * petición (así ningún parámetro añadido viaja con el enlace), o la de la portada.
	 */
	public static function current_url(): string {
		$url = '';
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$url = (string) get_permalink( get_queried_object_id() );
		} elseif ( function_exists( 'is_category' ) && ( is_category() || is_tag() || is_tax() ) ) {
			$link = get_term_link( get_queried_object() );
			$url  = is_string( $link ) ? $link : '';
		} elseif ( function_exists( 'is_author' ) && is_author() ) {
			$url = (string) get_author_posts_url( (int) get_queried_object_id() );
		} elseif ( function_exists( 'is_post_type_archive' ) && is_post_type_archive() ) {
			$url = (string) get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
		} elseif ( function_exists( 'get_the_ID' ) && get_the_ID() && ! ( function_exists( 'is_front_page' ) && ( is_front_page() || is_home() ) ) ) {
			// Fuera de la consulta principal (el editor pinta el widget por AJAX): el documento que se edita.
			$url = (string) get_permalink( get_the_ID() );
		}
		if ( '' === $url && function_exists( 'home_url' ) ) {
			global $wp;
			$path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
			$url  = home_url( '' === $path ? '/' : user_trailingslashit( $path ) );
		}
		return $url;
	}

	/**
	 * Lo que se comparte (como PRO con `elementorFrontend.config.post`): con la URL personalizada sólo ella; con la página
	 * actual, también el título del documento, el extracto (o la descripción del archivo) y la imagen destacada.
	 *
	 * @return array{url:string,title:string,text:string,image:string}
	 */
	public static function share_data( array $settings ): array {
		$custom = 'custom' === ( $settings['share_url_type'] ?? '' ) && ! empty( $settings['share_url']['url'] );
		if ( $custom ) {
			return array( 'url' => (string) $settings['share_url']['url'], 'title' => '', 'text' => '', 'image' => '' );
		}
		$data = array( 'url' => self::current_url(), 'title' => '', 'text' => '', 'image' => '' );
		if ( function_exists( 'wp_get_document_title' ) ) {
			$data['title'] = html_entity_decode( (string) wp_get_document_title(), ENT_QUOTES, 'UTF-8' );
		}
		if ( function_exists( 'is_singular' ) && is_singular() ) {
			$post          = get_post( get_queried_object_id() );
			$data['text']  = $post ? (string) $post->post_excerpt : '';
			$image         = get_the_post_thumbnail_url( $post, 'large' );
			$data['image'] = (string) ( $image ? $image : get_the_post_thumbnail_url( $post, 'full' ) );
		} elseif ( function_exists( 'get_the_archive_description' ) ) {
			$data['text'] = trim( wp_strip_all_tags( (string) get_the_archive_description() ) );
		}
		return $data;
	}

	/** URL de compartir de una red con todo codificado, o '' si la red no tiene (imprimir, Mix). */
	public static function link( string $network, array $data ): string {
		$template = self::NETWORKS[ $network ][2] ?? '';
		if ( '' === $template ) {
			return '';
		}
		if ( 'email' === $network && '' === $data['title'] ) {
			$template = str_replace( 'subject={title}&', '', $template );
		}
		$pairs = array();
		foreach ( array( 'url', 'title', 'text', 'image' ) as $key ) {
			$pairs[ '{' . $key . '}' ] = rawurlencode( (string) ( $data[ $key ] ?? '' ) );
		}
		return strtr( $template, $pairs );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$buttons  = $settings['share_buttons'] ?? array();
		if ( empty( $buttons ) || ! is_array( $buttons ) ) {
			return;
		}
		$view      = (string) ( $settings['view'] ?? 'icon-text' );
		$show_text = 'text' === $view || 'yes' === ( $settings['show_label'] ?? '' );
		$show_icon = 'icon' === $view || 'icon-text' === $view;
		$list      = count( $buttons ) > 1;
		$data      = self::share_data( $settings );
		$html      = '';
		foreach ( $buttons as $button ) {
			$network = is_array( $button ) ? (string) ( $button['button'] ?? '' ) : '';
			// Una red obsoleta (fuera de la lista de PRO) no se pinta, como en PRO.
			if ( ! isset( self::NETWORKS[ $network ] ) ) {
				continue;
			}
			$title  = 'email' === $network ? esc_html__( 'Correo', 'pro-elements' ) : ( 'print' === $network ? esc_html__( 'Imprimir', 'pro-elements' ) : self::NETWORKS[ $network ][0] );
			$custom = isset( $button['text'] ) && '' !== trim( wp_strip_all_tags( (string) $button['text'] ) );
			$inner  = '';
			if ( $show_icon ) {
				$inner .= '<span class="digi-share__icon">' . self::icon( $network ) . '</span>';
			}
			if ( $show_text ) {
				$inner .= '<span class="digi-share__text"><span class="digi-share__title">' . ( $custom ? wp_kses_post( (string) $button['text'] ) : esc_html( $title ) ) . '</span></span>';
			}
			// Nombre accesible: el texto que el autor eligió, si se ve; si no, «Compartir en <red>».
			if ( 'print' === $network ) {
				$label = esc_html__( 'Imprimir la página', 'pro-elements' );
			} elseif ( 'email' === $network ) {
				$label = esc_html__( 'Compartir por correo', 'pro-elements' );
			} else {
				/* translators: %s: nombre de la red social. */
				$label = sprintf( esc_html__( 'Compartir en %s', 'pro-elements' ), $title );
			}
			$attrs = 'class="digi-share__btn digi-share__btn--' . esc_attr( $network ) . '" style="--digi-share-o:' . esc_attr( self::NETWORKS[ $network ][1] ) . '"';
			if ( ! ( $show_text && $custom ) ) {
				$attrs .= ' aria-label="' . esc_attr( $label ) . '"';
			}
			$link = self::link( $network, $data );
			if ( 'print' === $network ) {
				$tag = '<button type="button" ' . $attrs . ' data-digi-share-print>' . $inner . '</button>';
			} elseif ( '' === $link ) {
				$tag = '<span ' . $attrs . '>' . $inner . '</span>';
			} else {
				$target = 0 === strpos( $link, 'mailto:' ) ? '' : ' target="_blank" rel="noopener noreferrer"';
				$tag    = '<a ' . $attrs . ' href="' . esc_url( $link, array( 'http', 'https', 'mailto' ) ) . '"' . $target . '>' . $inner . '</a>';
			}
			$html .= '<div class="elementor-grid-item"' . ( $list ? ' role="listitem"' : '' ) . '>' . $tag . '</div>';
		}
		echo '<div class="elementor-grid digi-share"' . ( $list ? ' role="list"' : '' ) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
	}
}
