<?php
namespace Digitalisimo\Elements\Convert;

defined( 'ABSPATH' ) || exit;

/**
 * Widget global de PRO (C9 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c9.md`): cada instancia
 * (`widgetType: global` con `templateID`) se sustituye por el elemento guardado en su plantilla de la biblioteca (tipo
 * `widget`), que es lo que PRO pinta (`modules/global-widget/widgets/global-widget.php`: `try_load_from_template()` toma
 * el tipo y los ajustes del primer elemento de la plantilla y `init_original_element_instance()` lo pinta con el id de
 * la instancia). PRO no permite cambios locales en una instancia (sus ajustes guardados son una copia que no se usa), así
 * que el resultado son exactamente los ajustes de la plantilla.
 *
 * - **Pura**, como `Converter`: no lee la base de datos. Quien llama da el contenido de cada plantilla (`$resolve`:
 *   `callable( int $plantilla ): ?array` con su `_elementor_data` ya decodificado, o null si no existe, está en la
 *   papelera o no es una plantilla de widget). DIGITALÍSIMO Migración lo lee del sitio y el importador de kits, del
 *   sitio tras remapear.
 * - El elemento conserva el **id de la instancia** (así lo pintaba PRO y no choca con otras instancias de la misma
 *   plantilla) y su `isInner`; sus hijos, si los hay, reciben ids nuevos estables (`child_id()`). Los ajustes son los de
 *   la plantilla más `_digitalisimo_global` (la plantilla de origen) y la clase `elementor-global-<id>` en
 *   `_css_classes`, la que PRO ponía en el envoltorio, para que el CSS del sitio que la nombre siga aplicando.
 * - **Falla cerrado:** sin plantilla, con un contenido que no es un widget o que es otra instancia global, la instancia
 *   se queda como estaba (PRO tampoco la pinta) y se informa `pro.global_missing`. La plantilla nunca se borra.
 * - Lo que queda dentro (un widget de PRO con mapa listo) lo convierte después `Converter::tree()`; la marca del traductor
 *   no se pone aquí, para que un widget de PRO aún sin mapa se convierta cuando su familia lo tenga.
 */
final class Globals {
	const TYPE = 'global';

	/** Plantilla de origen en los ajustes del elemento sustituido. */
	const KEY = '_digitalisimo_global';

	const MAX_DEPTH = 256;

	/** ¿Es una instancia de widget global? */
	public static function is_instance( $element ): bool {
		return is_array( $element ) && 'widget' === ( $element['elType'] ?? '' ) && self::TYPE === ( $element['widgetType'] ?? '' );
	}

	/** Plantilla de una instancia (entero positivo) o 0. */
	public static function template_id( array $element ): int {
		$id = $element['templateID'] ?? 0;
		return ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) ? max( 0, (int) $id ) : 0;
	}

	/**
	 * Elemento utilizable de los datos de una plantilla de widget global: el primero, un widget con tipo que no es otra
	 * instancia global (las mismas guardas que el widget global de PRO). null si no lo hay.
	 */
	public static function content( $data ): ?array {
		$first = is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : null;
		if ( null === $first || 'widget' !== ( $first['elType'] ?? '' ) ) {
			return null;
		}
		$type = $first['widgetType'] ?? '';
		return is_string( $type ) && '' !== $type && self::TYPE !== $type ? $first : null;
	}

	/** Id nuevo y estable (7 caracteres hexadecimales) de un hijo del contenido dentro de una instancia. */
	public static function child_id( string $instance, string $child ): string {
		return substr( md5( 'digitalisimo-global:' . $instance . ':' . $child ), 0, 7 );
	}

	/**
	 * Sustituye una instancia por su contenido. null si no es una instancia o el contenido no vale.
	 *
	 * @param array      $element Instancia guardada.
	 * @param mixed      $data    `_elementor_data` decodificado de su plantilla (o null).
	 * @param array|null $codes   Recibe los códigos del informe.
	 */
	public static function expand( array $element, $data, ?array &$codes = null ): ?array {
		$codes    = array();
		$template = self::is_instance( $element ) ? self::template_id( $element ) : 0;
		$content  = $template > 0 ? self::content( $data ) : null;
		if ( null === $content ) {
			return null;
		}
		$id       = isset( $element['id'] ) && is_scalar( $element['id'] ) && '' !== (string) $element['id'] ? (string) $element['id'] : self::child_id( 'global', (string) $template );
		$settings = isset( $content['settings'] ) && is_array( $content['settings'] ) ? $content['settings'] : array();
		$classes  = isset( $settings['_css_classes'] ) && is_string( $settings['_css_classes'] ) ? trim( $settings['_css_classes'] ) : '';
		$own      = 'elementor-global-' . $template;
		if ( ! in_array( $own, preg_split( '/\s+/', $classes ) ?: array(), true ) ) {
			$settings['_css_classes'] = trim( $classes . ' ' . $own );
		}
		$settings[ self::KEY ] = $template;
		$out                   = array(
			'id'         => $id,
			'elType'     => 'widget',
			'isInner'    => isset( $element['isInner'] ) ? (bool) $element['isInner'] : (bool) ( $content['isInner'] ?? false ),
			'settings'   => $settings,
			'elements'   => self::ids( isset( $content['elements'] ) && is_array( $content['elements'] ) ? $content['elements'] : array(), $id, 0 ),
			'widgetType' => (string) $content['widgetType'],
		);
		$codes[] = array(
			'code'    => 'pro.global',
			'key'     => 'templateID',
			'reason'  => sprintf( 'Instancia del widget global sustituida por el elemento guardado en su plantilla %d (%s), con sus mismos ajustes; la plantilla se conserva.', $template, $out['widgetType'] ),
			'section' => 'converted',
		);
		return $out;
	}

	/**
	 * Sustituye las instancias de un árbol a cualquier profundidad.
	 *
	 * @param array      $elements Árbol.
	 * @param callable   $resolve  `( int $plantilla ): ?array`, el `_elementor_data` decodificado de la plantilla.
	 * @param array|null $log      Recibe un registro por instancia: `source` (`global`), `target` (tipo del contenido, ''
	 *                             si no se sustituyó), `element`, `template`, `expanded` y `codes`.
	 */
	public static function tree( array $elements, callable $resolve, ?array &$log = null ): array {
		$log = array();
		return self::walk( $elements, $resolve, $log, array(), 0 );
	}

	/** Número de instancias de widget global en un árbol. */
	public static function count( array $elements, int $depth = 0 ): int {
		$n = 0;
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$n += self::is_instance( $element ) ? 1 : 0;
			if ( $depth < self::MAX_DEPTH && ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$n += self::count( $element['elements'], $depth + 1 );
			}
		}
		return $n;
	}

	/**
	 * @param int[] $chain Plantillas ya sustituidas en esta rama: una instancia de una de ellas dentro de su propio
	 *                     contenido se queda como está (nunca un bucle).
	 */
	private static function walk( array $elements, callable $resolve, array &$log, array $chain, int $depth ): array {
		if ( $depth > self::MAX_DEPTH ) {
			return $elements;
		}
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$branch = $chain;
			if ( self::is_instance( $element ) ) {
				$template = self::template_id( $element );
				$id       = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
				$codes    = array();
				$expanded = null;
				if ( $template > 0 && ! in_array( $template, $chain, true ) ) {
					$data     = call_user_func( $resolve, $template );
					$expanded = self::expand( $element, $data, $codes );
				}
				if ( null === $expanded ) {
					$log[] = array(
						'source'   => self::TYPE,
						'target'   => '',
						'element'  => $id,
						'template' => $template,
						'expanded' => false,
						'codes'    => array(
							array(
								'code'    => 'pro.global_missing',
								'key'     => 'templateID',
								'reason'  => sprintf( 'La plantilla %d del widget global no existe, está en la papelera o no guarda un widget: la instancia se queda como estaba (tampoco se pintaba).', $template ),
								'section' => 'review',
							),
						),
					);
				} else {
					$log[]    = array( 'source' => self::TYPE, 'target' => (string) $expanded['widgetType'], 'element' => $id, 'template' => $template, 'expanded' => true, 'codes' => $codes );
					$element  = $expanded;
					$branch[] = $template;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk( $element['elements'], $resolve, $log, $branch, $depth + 1 );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/** Ids nuevos y estables para los hijos del contenido de una instancia. */
	private static function ids( array $elements, string $instance, int $depth ): array {
		if ( $depth > self::MAX_DEPTH ) {
			return array();
		}
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$child          = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : (string) $index;
			$element['id']  = self::child_id( $instance, $child );
			$element['elements'] = self::ids( isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array(), $instance, $depth + 1 );
			$elements[ $index ] = $element;
		}
		return $elements;
	}
}
