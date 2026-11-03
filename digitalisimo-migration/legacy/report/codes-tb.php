<?php
/**
 * Códigos del informe del frente G (`tb.`): copias del Theme Builder de Element Pack, sus condiciones, su prioridad y
 * su publicación. Formato en Report::catalog(). Sólo `review` deja el sitio «con asuntos por revisar».
 */
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

return array(
	// Copias.
	'tb.created'             => array( 'section' => 'created', 'label' => __( 'Plantilla del Theme Builder de Element Pack copiada al Theme Builder de DIGITALÍSIMO Elements, con su tipo y sus condiciones. El original no se toca.', 'digitalisimo-migration' ) ),
	'tb.published'           => array( 'section' => 'converted', 'label' => __( 'Copia publicada al dejar de estar activo Element Pack; su prioridad manual conserva el orden en que Element Pack elegía la plantilla.', 'digitalisimo-migration' ) ),
	'tb.priority'            => array( 'section' => 'converted', 'label' => __( 'Copias con prioridad manual para que se vea la misma plantilla que mostraba Element Pack.', 'digitalisimo-migration' ) ),
	'tb.inactive'            => array( 'section' => 'created', 'label' => __( 'Copiada en borrador: estaba inactiva o sin publicar en Element Pack.', 'digitalisimo-migration' ) ),
	'tb.duplicate'           => array( 'section' => 'created', 'label' => __( 'Copiada en borrador: Element Pack mostraba otra plantilla activa del mismo tipo.', 'digitalisimo-migration' ) ),
	'tb.copy_failed'         => array( 'section' => 'review', 'label' => __( 'La copia no quedó idéntica al original (su contenido releído no coincide): queda en borrador. Revísala antes de publicarla.', 'digitalisimo-migration' ) ),
	'tb.priority_overflow'   => array( 'section' => 'review', 'label' => __( 'Más de 99 plantillas en la misma ubicación: esta copia queda en borrador para no cambiar cuál se ve.', 'digitalisimo-migration' ) ),
	'tb.too_many'            => array( 'section' => 'review', 'label' => __( 'Demasiadas plantillas que publicar a la vez: esta copia queda en borrador.', 'digitalisimo-migration' ) ),
	'tb.reverted'            => array( 'section' => 'converted', 'label' => __( 'Copia retirada al revertir la migración.', 'digitalisimo-migration' ) ),
	// Pendiente de revisar: borradores.
	'tb.review.unknown_type' => array( 'section' => 'review', 'label' => __( 'Tipo de plantilla desconocido o de un tipo de contenido que ya no existe: la copia queda en borrador sin condiciones.', 'digitalisimo-migration' ) ),
	'tb.review.woo_page'     => array( 'section' => 'review', 'label' => __( 'Plantilla de una página de WooCommerce sin tipo equivalente: la copia queda en borrador sin condiciones. Úsala como contenido de esa página o asígnala a mano.', 'digitalisimo-migration' ) ),
	'tb.review.gutenberg'    => array( 'section' => 'review', 'label' => __( 'Plantilla hecha con el editor de bloques: la copia queda en borrador con su contenido. Rehazla con el constructor antes de publicarla.', 'digitalisimo-migration' ) ),
	'tb.review.empty'        => array( 'section' => 'review', 'label' => __( 'Plantilla sin contenido del constructor: la copia queda en borrador.', 'digitalisimo-migration' ) ),
	'tb.review.conditions'   => array( 'section' => 'review', 'label' => __( 'Una exclusión no tiene traducción segura: la copia queda en borrador sin condiciones para no mostrarse donde Element Pack no la mostraba. Asígnale las condiciones a mano.', 'digitalisimo-migration' ) ),
	'tb.review.scope'        => array( 'section' => 'review', 'label' => __( 'No se puede limitar la copia a las mismas páginas que Element Pack: queda en borrador sin condiciones.', 'digitalisimo-migration' ) ),
	'tb.review.no_include'   => array( 'section' => 'review', 'label' => __( 'Ninguna condición de inclusión tiene traducción segura: la copia queda en borrador sin condiciones.', 'digitalisimo-migration' ) ),
	'tb.review.type_lost'    => array( 'section' => 'review', 'label' => __( 'Esta plantilla no se publicará: donde Element Pack la mostraba se verá la plantilla del tema con el contenido.', 'digitalisimo-migration' ) ),
	'tb.fallback_lost'       => array( 'section' => 'review', 'label' => __( 'Element Pack también usaba esta plantilla en las páginas de su tipo que no cumplían ninguna condición; la copia sólo se aplica con sus condiciones.', 'digitalisimo-migration' ) ),
	'tb.stale'               => array( 'section' => 'review', 'label' => __( 'El original cambió después de copiarlo: la copia conserva la versión de cuando se preparó la migración.', 'digitalisimo-migration' ) ),
	// Condiciones (detalle por fila).
	'tb.cond.dropped'        => array( 'section' => 'review', 'label' => __( 'Condición de inclusión sin equivalente exacto: se omitió, así que la copia se aplica en menos páginas que en Element Pack.', 'digitalisimo-migration' ) ),
	'tb.cond.approx'         => array( 'section' => 'approx', 'label' => __( 'Condición traducida de forma aproximada (la copia se aplica en las mismas páginas o en menos).', 'digitalisimo-migration' ) ),
	'tb.cond.never'          => array( 'section' => 'dropped', 'label' => __( 'Condición que nunca se cumplía en Element Pack para este tipo de plantilla: se omitió.', 'digitalisimo-migration' ) ),
	'tb.cond.ids_missing'    => array( 'section' => 'dropped', 'label' => __( 'IDs que ya no existen en el sitio: se omitieron de la condición.', 'digitalisimo-migration' ) ),
	'tb.cond.untranslatable' => array( 'section' => 'review', 'label' => __( 'Exclusión sin traducción segura.', 'digitalisimo-migration' ) ),
	'tb.scope.added'         => array( 'section' => 'converted', 'label' => __( 'Exclusiones añadidas para que la copia se aplique en las mismas páginas que en Element Pack.', 'digitalisimo-migration' ) ),
	'tb.scope.snapshot'      => array( 'section' => 'approx', 'label' => __( 'Excluidas las páginas que hoy usan una plantilla PHP del tema; una página que la cambie después no se actualiza sola.', 'digitalisimo-migration' ) ),
	'tb.shared'              => array( 'section' => 'approx', 'label' => __( 'Comparte condiciones con otras plantillas del sitio: decide la prioridad manual.', 'digitalisimo-migration' ) ),
	// Retirada de Element Pack (V.5.3).
	'tb.plugin_inactive'     => array( 'section' => 'review', 'label' => __( 'Element Pack no estaba activo al empezar: sus plantillas se copiaron en borrador y no se publican solas. Revísalas y publícalas a mano.', 'digitalisimo-migration' ) ),
	'tb.unprepared'          => array( 'section' => 'review', 'label' => __( 'La preparación de las plantillas no terminó: Element Pack sigue activo para no dejar páginas sin cabecera ni pie. Vuelve a pulsar Migrar.', 'digitalisimo-migration' ) ),
	'tb.blocked_retire'      => array( 'section' => 'review', 'label' => __( 'Una cabecera o un pie que se ven hoy no tienen una copia publicable: Element Pack sigue activo para no dejar páginas sin cabecera ni pie. Corrige la plantilla indicada y vuelve a pulsar Migrar.', 'digitalisimo-migration' ) ),
	'tb.conflict'            => array( 'section' => 'review', 'label' => __( 'Elementor Pro o PRO Elements siguen activos: Element Pack sigue activo hasta que se desactiven, para que las copias no compitan con ellos.', 'digitalisimo-migration' ) ),
	'tb.no_builder'          => array( 'section' => 'review', 'label' => __( 'El Theme Builder de DIGITALÍSIMO Elements no está disponible: Element Pack sigue activo.', 'digitalisimo-migration' ) ),
	'tb.late'                => array( 'section' => 'review', 'label' => __( 'Las plantillas se copiaron después de desactivar Element Pack (tarea empezada con una versión anterior): hubo un tiempo sin su cabecera y su pie.', 'digitalisimo-migration' ) ),
	// Sitio.
	'tb.wpml'                => array( 'section' => 'review', 'label' => __( 'Con WPML, Element Pack elegía la plantilla de cada idioma: revisa las copias de cada idioma y sus condiciones.', 'digitalisimo-migration' ) ),
	'tb.theme_override'      => array( 'section' => 'review', 'label' => __( 'El tema tiene plantillas PHP propias para Element Pack (carpeta bdthemes-element-pack): no se convierten y dejan de usarse.', 'digitalisimo-migration' ) ),
);
