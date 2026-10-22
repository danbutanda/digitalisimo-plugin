<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Registro oculto sin WooCommerce (D2 y V.10.1).
 *
 * El núcleo descarta al cargar **y al guardar** todo widget que no puede instanciar (`elementor/core/base/document.php`
 * `get_elements_raw_data()`), así que abrir y guardar en el editor un documento con widgets F4-Woo mientras WooCommerce
 * está desactivado borraría sus datos. Por eso, sólo en las tres peticiones del editor —la pantalla (`action=elementor`),
 * su vista previa (`elementor-preview`) y `admin-ajax.php?action=elementor_ajax`, donde guarda y pinta— y para quien
 * puede editar, `Inert` registra los siete widgets ocultos del panel y con el aviso «Necesita WooCommerce»:
 *
 * - si la clase del widget extiende `Woo\Widget` y no redefine `render()` ni `content_template()`, la misma clase, con
 *   sus mismos controles;
 * - si no (un frente que no siguió el contrato, o cuyo archivo falta o falla), `Inert_Widget` con el mismo nombre:
 *   sin controles, pero `get_data_for_save()` conserva los ajustes sin control.
 *
 * **Nunca en REST, cron ni WP-CLI:** una importación de kits por la pantalla «Kits», por la App
 * (`elementor/v1/import-export-customization`) o por WP-CLI ve los tipos como desconocidos y K los conserva en su
 * marcador `digitalisimo-unconverted` (motivo `unknown`), que vuelve solo cuando WooCommerce se activa. En el front no se
 * engancha nada: 0 ganchos y 0 consultas.
 */
final class Inert {
	/** Prioridad en `elementor/widgets/register`, la de `Registry`. */
	const PRIORITY = 20;

	/** @var bool */
	private static $initialized = false;

	/** @var bool Los widgets se registraron en modo oculto en esta petición. */
	private static $active = false;

	/** Sin WooCommerce: se engancha sólo en las peticiones del editor. Idempotente. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		if ( ! self::editor_request() ) {
			return;
		}
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register' ), self::PRIORITY );
	}

	/**
	 * ¿Es una de las tres peticiones del editor? La regla es una sola para `Inert` y para la guarda hacia delante
	 * (`Unknown_Guard::editor_request()`).
	 */
	public static function editor_request(): bool {
		$guard = '\Digitalisimo\Elements\Unknown_Guard';
		return class_exists( $guard ) && is_callable( array( $guard, 'editor_request' ) ) && (bool) call_user_func( array( $guard, 'editor_request' ) );
	}

	/**
	 * `elementor/widgets/register` (20) en una petición del editor: registra los siete widgets ocultos.
	 *
	 * @param object $manager `Widgets_Manager` del núcleo.
	 * @return string[] Widgets registrados.
	 */
	public static function register( $manager ): array {
		$registered = array();
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) || ! class_exists( '\Elementor\Widget_Base' ) ) {
			return $registered;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
			return $registered; // Nunca en REST (V.10.1), aunque la petición pareciera del editor.
		}
		self::$active = true;
		foreach ( Registry::names() as $name ) {
			if ( method_exists( $manager, 'get_widget_types' ) && $manager->get_widget_types( $name ) ) {
				continue;
			}
			$class = Registry::load( $name );
			try {
				$widget = '' !== $class && self::cooperates( $class )
					? new $class()
					: new Inert_Widget(
						array(),
						array(
							Inert_Widget::ARG_NAME  => $name,
							Inert_Widget::ARG_TITLE => Registry::title( $name ),
						)
					);
				$manager->register( $widget );
				$registered[] = $name;
			} catch ( \Throwable $error ) {
				continue;
			}
		}
		return $registered;
	}

	/**
	 * ¿Sigue la clase el contrato de `Woo\Widget` (la base pinta el aviso)? Si redefine `render()` o
	 * `content_template()`, su salida podría llamar a WooCommerce: se usa `Inert_Widget`.
	 */
	public static function cooperates( string $class ): bool {
		if ( ! class_exists( $class, false ) || ! is_subclass_of( $class, Widget::class ) ) {
			return false;
		}
		try {
			// Los dos métodos deben venir de `Woo\Widget` o del núcleo, nunca de la subclase.
			foreach ( array( 'render', 'content_template' ) as $method ) {
				$declared = ( new \ReflectionMethod( $class, $method ) )->getDeclaringClass()->getName();
				if ( Widget::class !== $declared && is_a( $declared, Widget::class, true ) ) {
					return false;
				}
			}
		} catch ( \Throwable $error ) {
			return false;
		}
		return true;
	}

	/** ¿Registró `Inert` los widgets en esta petición? */
	public static function active(): bool {
		return self::$active;
	}

	/** Aviso que pintan los widgets en modo oculto: sólo en el editor y en su vista previa; fuera, nada. */
	public static function notice(): string {
		if ( ! self::in_editor() ) {
			return '';
		}
		return '<div class="digi-woo-inert" role="note"><strong>' . esc_html__( 'Necesita WooCommerce.', 'pro-elements' ) . '</strong> '
			. esc_html__( 'Activa WooCommerce para ver este widget; sus ajustes se conservan.', 'pro-elements' ) . '</div>';
	}

	/** ¿Se pinta en el editor o en su vista previa? */
	public static function in_editor(): bool {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && is_object( $plugin->editor ) && $plugin->editor->is_edit_mode() )
			|| ( isset( $plugin->preview ) && is_object( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	/** Para las pruebas. */
	public static function reset(): void {
		self::$active      = false;
		self::$initialized = false;
	}
}
