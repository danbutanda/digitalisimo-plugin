<?php
/**
 * Códigos del informe del frente A (`core.`): runner, retirada, detección, red y reversión. Formato en
 * Report::catalog(). Sólo `review` deja el sitio «con asuntos por revisar».
 */
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

return array(
	// Conversión de documentos.
	'core.target_missing'      => array( 'section' => 'review', 'label' => __( 'Widget sin convertir: su widget de destino no está disponible en este sitio. Se conserva tal cual (en un marcador que el editor no borra) hasta que lo esté.', 'digitalisimo-migration' ) ),
	'core.no_adapter'          => array( 'section' => 'review', 'label' => __( 'Widget de Element Pack sin equivalente: se conserva en un marcador (no se muestra sin Element Pack). Sustitúyelo en el editor por la alternativa indicada.', 'digitalisimo-migration' ) ),
	'core.residual'            => array( 'section' => 'review', 'label' => __( 'Documento migrado con una versión anterior: quedan efectos o ajustes de página heredados que esta versión no convierte para no cambiar lo editado. Revísalo en el editor.', 'digitalisimo-migration' ) ),
	'core.addon_missing'       => array( 'section' => 'dropped', 'label' => __( 'Widgets del Element Pack Addon sin el Addon instalado: ya no se ven con Element Pack 10; se verán al migrar.', 'digitalisimo-migration' ) ),
	'core.document_failed'     => array( 'section' => 'review', 'label' => __( 'No se pudo convertir este documento (la conversión se cortó dos veces): se sigue viendo como antes.', 'digitalisimo-migration' ) ),
	// Imágenes de ejemplo de Element Pack (F8-D17, Demo_Media).
	'core.demo.copied'         => array( 'section' => 'created', 'label' => __( 'Imágenes de ejemplo de Element Pack copiadas a la biblioteca de medios (la carpeta del plugin no se usa)', 'digitalisimo-migration' ) ),
	'core.demo.skipped'        => array( 'section' => 'dropped', 'review_if_published' => true, 'label' => __( 'Imágenes de ejemplo de Element Pack que no se pudieron copiar (plugin borrado, archivo que falta o SVG sin la subida sin filtrar): la imagen queda vacía', 'digitalisimo-migration' ) ),
	'core.demo.reverted'       => array( 'section' => 'converted', 'label' => __( 'Imagen de ejemplo de Element Pack copiada, enviada a la papelera de medios al revertir', 'digitalisimo-migration' ) ),
	'core.demo.revert_kept'    => array( 'section' => 'review', 'label' => __( 'Imagen de ejemplo de Element Pack copiada que se conserva en la biblioteca (cambió o no hay papelera de medios)', 'digitalisimo-migration' ) ),
	// Tarea y etapas.
	'core.elements_old'        => array( 'section' => 'review', 'label' => __( 'DIGITALÍSIMO Elements es anterior a 4.3.0.170: la migración está en pausa hasta actualizarlo.', 'digitalisimo-migration' ) ),
	'core.stage_skipped'       => array( 'section' => 'review', 'label' => __( 'Una etapa no pudo completarse (la petición se cortó dos veces) y se saltó sin desactivar Element Pack.', 'digitalisimo-migration' ) ),
	'core.step_failed'         => array( 'section' => 'review', 'label' => __( 'Un paso de la plataforma falló y se dejó como estaba.', 'digitalisimo-migration' ) ),
	'core.tb_unprepared'       => array( 'section' => 'review', 'label' => __( 'Las plantillas de Element Pack no pudieron prepararse: Element Pack sigue activo para que el sitio no pierda su cabecera ni su pie.', 'digitalisimo-migration' ) ),
	// Retirada de plugins.
	'core.addon_scope'         => array( 'section' => 'review', 'label' => __( 'El Element Pack Addon y Element Pack Pro están activos en ámbitos distintos (red y sitio): se desactivan en el orden que evita cargar el Addon sin Pro, y lo que falta lo hace la migración de la red.', 'digitalisimo-migration' ) ),
	'core.addon_scope_info'    => array( 'section' => 'converted', 'label' => __( 'Element Pack Pro sigue activo en toda la red mientras el Addon se desactivó en este sitio: lo desactiva la migración de la red.', 'digitalisimo-migration' ) ),
	'core.addon_unknown'       => array( 'section' => 'review', 'label' => __( 'Hay un plugin que parece del Element Pack Addon y no se reconoce: Element Pack no se desactivó, para no dejar el Addon cargado sin él.', 'digitalisimo-migration' ) ),
	'core.network_scope'       => array( 'section' => 'converted', 'label' => __( 'Activo en toda la red: lo desactiva la migración de la red.', 'digitalisimo-migration' ) ),
	'core.late_retired'        => array( 'section' => 'converted', 'label' => __( 'Prime Slider se desactivó al final, con todos sus widgets convertidos. No se borró.', 'digitalisimo-migration' ) ),
	'core.late_kept'           => array( 'section' => 'review', 'label' => __( 'Prime Slider sigue activo: quedan widgets suyos sin convertir en estos documentos.', 'digitalisimo-migration' ) ),
	// Red.
	'core.site_skipped'        => array( 'section' => 'review', 'label' => __( 'Sitio archivado, marcado como spam o borrado: no se migró. Migrará al volver a pulsar Migrar toda la red cuando esté activo.', 'digitalisimo-migration' ) ),
	'core.barrier_unprepared'  => array( 'section' => 'review', 'label' => __( 'Element Pack se desactivó en la red sin que este sitio terminara de preparar sus plantillas (venció el plazo): puede haberse quedado un tiempo sin la cabecera de Element Pack.', 'digitalisimo-migration' ) ),
	'core.barrier_blocked'     => array( 'section' => 'review', 'label' => __( 'La migración de la red se detuvo: un sitio tiene una cabecera o un pie visibles sin copia que pueda publicarse. Corrígelo y vuelve a pulsar Migrar toda la red.', 'digitalisimo-migration' ) ),
	// Reversión.
	'core.revert_skipped'      => array( 'section' => 'review', 'label' => __( 'No se restauró porque cambió después de migrar (se conserva lo editado). Usa --force para restaurarlo igualmente.', 'digitalisimo-migration' ) ),
	'core.revert_refused'      => array( 'section' => 'review', 'label' => __( 'Restaurar exige Element Pack activo (si no, el sitio se quedaría sin cabecera). Actívalo o usa --force.', 'digitalisimo-migration' ) ),
);
