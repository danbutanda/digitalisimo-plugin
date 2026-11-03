<?php
namespace Digitalisimo\Elements\Legacy\Effects;

defined( 'ABSPATH' ) || exit;

/**
 * ¿Estaba encendida una extensión en Element Pack? (F8-D10)
 *
 * La regla de Element Pack (`ModuleService::is_module_active()`, `EP/admin/module-settings.php:3191-3201`): sin la
 * clave en la opción `element_pack_elementor_extend`, manda `default_activation` de su `module.info.php` (falso en
 * background-expand, background-image-parallax y ripple-effects); con la clave, sólo `'on'` la enciende. Una
 * extensión apagada no se convierte: sus claves se retiran y el informe dice que estaba desactivada (`ext.disabled`).
 *
 * `Native::visibility_enabled_on_site()` sigue con su regla («todo lo que no sea `off`»): discrepancia declarada,
 * fuera del alcance de F8.
 */
final class Activation {
	/** @var array|null opción leída en esta petición (por sitio). */
	private static $options = array();

	public static function enabled( string $slug ): bool {
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
		if ( ! array_key_exists( $blog, self::$options ) ) {
			$value                  = function_exists( 'get_option' ) ? get_option( 'element_pack_elementor_extend', null ) : null;
			self::$options[ $blog ] = is_array( $value ) ? $value : null;
		}
		$options = self::$options[ $blog ];
		if ( is_array( $options ) && array_key_exists( $slug, $options ) ) {
			return 'on' === $options[ $slug ];
		}
		$spec = Dispatcher::keys()[ $slug ] ?? null;
		return is_array( $spec ) ? ! empty( $spec['activation'] ) : true;
	}

	/** Olvida la opción leída (pruebas, o tras cambiar de sitio). */
	public static function reset(): void {
		self::$options = array();
	}
}
