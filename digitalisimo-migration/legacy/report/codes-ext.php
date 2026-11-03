<?php
/**
 * Códigos del informe del frente C (`ext.`): extensiones de Element Pack en los elementos y en los ajustes de la
 * página (F8, §3.C y V.9). Formato en Report::catalog(). Sólo `review` deja el sitio «con asuntos por revisar».
 *
 * - `ext.converted.<extensión>`: convertida (`converted` si el resultado es el mismo o pasa a un rasgo propio,
 *   `approx` si tiene pérdidas declaradas).
 * - `ext.loss.<extensión>`: lo que no se reproduce, en la nota (información).
 * - `ext.dropped.<extensión>`: descartes aprobados por el propietario (los informa `Dropped` leyendo la copia).
 */
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

return array(
	// Convertidas.
	'ext.converted.tooltip'                      => array( 'section' => 'converted', 'label' => __( 'Información emergente: pasa al rasgo «Información emergente» de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ) ),
	'ext.converted.transform_effects'            => array( 'section' => 'approx', 'label' => __( 'Efectos de transformación: pasan a Transformar del constructor (normal y al pasar el puntero).', 'digitalisimo-migration' ) ),
	'ext.converted.parallax_effects'             => array( 'section' => 'approx', 'label' => __( 'Efectos de paralaje: pasan a los efectos de movimiento al desplazar.', 'digitalisimo-migration' ) ),
	'ext.converted.background_parallax'          => array( 'section' => 'approx', 'label' => __( 'Paralaje del fondo: pasa al movimiento del fondo al desplazar.', 'digitalisimo-migration' ) ),
	'ext.converted.image_parallax'               => array( 'section' => 'approx', 'label' => __( 'Capas de imágenes con paralaje: pasan a capas con movimiento al mover el ratón.', 'digitalisimo-migration' ) ),
	'ext.converted.background_overlay'           => array( 'section' => 'approx', 'label' => __( 'Superposición del fondo: pasa al CSS personalizado del elemento.', 'digitalisimo-migration' ) ),
	'ext.converted.background_expand'            => array( 'section' => 'approx', 'label' => __( 'Fondo que se expande al desplazar: pasa a una acción «Al aparecer en pantalla» y al CSS personalizado.', 'digitalisimo-migration' ) ),
	'ext.converted.section_sticky'               => array( 'section' => 'approx', 'label' => __( 'Sección fija al desplazar: pasa a «Fijo» del constructor; los estilos del estado fijo, al CSS personalizado.', 'digitalisimo-migration' ) ),
	'ext.converted.equal_height'                 => array( 'section' => 'approx', 'label' => __( 'Igualar alturas: pasa a la alineación del contenedor o al CSS personalizado.', 'digitalisimo-migration' ) ),
	'ext.converted.reveal_effects'               => array( 'section' => 'approx', 'label' => __( 'Efecto de revelado: pasa a la animación de entrada del constructor.', 'digitalisimo-migration' ) ),
	'ext.converted.scroll_fill_effect'           => array( 'section' => 'approx', 'label' => __( 'Relleno del texto al desplazar: pasa al CSS personalizado (animación ligada al desplazamiento) o a los efectos de movimiento.', 'digitalisimo-migration' ) ),
	'ext.converted.scroll_box'                   => array( 'section' => 'converted', 'label' => __( 'Caja con desplazamiento propio: pasa al CSS personalizado del elemento, igual que antes.', 'digitalisimo-migration' ) ),
	'ext.converted.threed_text'                  => array( 'section' => 'approx', 'label' => __( 'Texto en 3D: pasa a sombras de texto en capas e inclinación con el ratón.', 'digitalisimo-migration' ) ),
	'ext.converted.grid_line'                    => array( 'section' => 'converted', 'label' => __( 'Líneas de cuadrícula: pasan al CSS personalizado de la página, igual que antes.', 'digitalisimo-migration' ) ),
	'ext.converted.global_badge'                 => array( 'section' => 'converted', 'label' => __( 'Insignia: pasa al rasgo «Distintivo» de DIGITALÍSIMO Elements, con su texto.', 'digitalisimo-migration' ) ),
	'ext.converted.image_hover_effects'          => array( 'section' => 'converted', 'label' => __( 'Efectos de la imagen al pasar el cursor: pasan al CSS personalizado del widget, igual que antes.', 'digitalisimo-migration' ) ),
	'ext.converted.animated_gradient_background' => array( 'section' => 'approx', 'label' => __( 'Fondo con degradado animado: pasa al CSS personalizado (sin movimiento si el visitante lo pide).', 'digitalisimo-migration' ) ),

	// Pérdidas declaradas (la nota dice cuál).
	'ext.loss.tooltip'                      => array( 'section' => 'approx', 'label' => __( 'Información emergente: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.transform_effects'            => array( 'section' => 'approx', 'label' => __( 'Efectos de transformación: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.parallax_effects'             => array( 'section' => 'approx', 'label' => __( 'Efectos de paralaje: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.background_parallax'          => array( 'section' => 'approx', 'label' => __( 'Paralaje del fondo: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.image_parallax'               => array( 'section' => 'approx', 'label' => __( 'Capas de imágenes con paralaje: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.background_overlay'           => array( 'section' => 'approx', 'label' => __( 'Superposición del fondo: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.background_expand'            => array( 'section' => 'approx', 'label' => __( 'Fondo que se expande al desplazar: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.section_sticky'               => array( 'section' => 'approx', 'label' => __( 'Sección fija al desplazar: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.equal_height'                 => array( 'section' => 'approx', 'label' => __( 'Igualar alturas: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.reveal_effects'               => array( 'section' => 'approx', 'label' => __( 'Efecto de revelado: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.scroll_fill_effect'           => array( 'section' => 'approx', 'label' => __( 'Relleno del texto al desplazar: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.threed_text'                  => array( 'section' => 'approx', 'label' => __( 'Texto en 3D: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.global_badge'                 => array( 'section' => 'approx', 'label' => __( 'Insignia: no se reproduce', 'digitalisimo-migration' ) ),
	'ext.loss.animated_gradient_background' => array( 'section' => 'approx', 'label' => __( 'Fondo con degradado animado: no se reproduce', 'digitalisimo-migration' ) ),

	// Descartes aprobados (información, sección «Sin equivalente»).
	'ext.dropped.background_image_parallax' => array( 'section' => 'dropped', 'label' => __( 'Paralaje de la imagen: sin equivalente (uso marginal, librería sin licencia declarada y efecto sólo decorativo). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.particles'                 => array( 'section' => 'dropped', 'label' => __( 'Partículas en el fondo: sin equivalente (lienzo animado continuo sin movimiento reducido y JSON que puede cargar imágenes externas). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.cursor_effects'            => array( 'section' => 'dropped', 'label' => __( 'Efectos del cursor: sin equivalente (ocultan el puntero del sistema y usan librerías sin licencia compatible). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.ripple_effects'            => array( 'section' => 'dropped', 'label' => __( 'Onda al pulsar: sin equivalente (anulaba todos los enlaces «#» de la página y actuaba fuera del elemento). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.tile_scroll'               => array( 'section' => 'dropped', 'label' => __( 'Mosaico al desplazar: sin equivalente (sólo existía con JavaScript, con imágenes sin texto alternativo). Puede recrearse con contenedores y efectos de movimiento. Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.hash_link'                 => array( 'section' => 'dropped', 'label' => __( 'Enlaces a anclas de la página: sin equivalente (convertía cada encabezado en un enlace). Usa el ID CSS del elemento o la Tabla de contenidos. Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.confetti_effects'          => array( 'section' => 'dropped', 'label' => __( 'Confeti: sin equivalente (lienzo a pantalla completa sin movimiento reducido). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.sound_effects'             => array( 'section' => 'dropped', 'label' => __( 'Efectos de sonido: sin equivalente (los sonidos vivían en la carpeta del plugin retirado). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.content_protector'         => array( 'section' => 'dropped', 'label' => __( 'Protección contra copiar el contenido: sin equivalente (bloqueaba pegar, copiar e imprimir y no protegía nada real). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),
	'ext.dropped.adblock_detector'          => array( 'section' => 'dropped', 'label' => __( 'Detector de bloqueadores de anuncios: sin equivalente (cada visitante hacía una petición a un servicio de anuncios sin consentimiento). Se retira; queda en la copia del documento.', 'digitalisimo-migration' ) ),

	// Casos generales.
	'ext.disabled'             => array( 'section' => 'dropped', 'label' => __( 'Efecto heredado que estaba desactivado en los ajustes de Element Pack (no se veía): se retira sin convertir.', 'digitalisimo-migration' ) ),
	'ext.inert'                => array( 'section' => 'dropped', 'label' => __( 'Ajuste heredado que no tenía efecto visible donde estaba: se retira sin convertir.', 'digitalisimo-migration' ) ),
	'ext.ambiguous'            => array( 'section' => 'dropped', 'label' => __( 'Ajuste sin prefijo que podría ser de otro plugin (Element Pack no está instalado y el documento no tiene otra señal suya): no se toca.', 'digitalisimo-migration' ) ),
	'ext.conflict'             => array( 'section' => 'review', 'label' => __( 'El efecto heredado no se convirtió porque el elemento ya tiene su propio valor en ese ajuste (no se pisa). Compáralo con la copia y elige uno en el editor.', 'digitalisimo-migration' ) ),
	'ext.v4'                   => array( 'section' => 'review', 'label' => __( 'Elemento atómico (v4) con un efecto heredado: no se convierte. Recréalo en el editor.', 'digitalisimo-migration' ) ),
	'ext.css_rejected'         => array( 'section' => 'review', 'label' => __( 'El CSS calculado para el efecto heredado no pasó la validación y no se escribió. Recréalo en el editor.', 'digitalisimo-migration' ) ),
	'ext.review.layers'        => array( 'section' => 'review', 'label' => __( 'Sección con varias capas de imágenes con paralaje o con su propia imagen de fondo: no se convierte sin cambiar su estructura. Pásala a un contenedor o recrea las capas.', 'digitalisimo-migration' ) ),
	'ext.review.overlay_image' => array( 'section' => 'review', 'label' => __( 'La superposición del fondo tenía una imagen: se convirtió sin ella (nunca se escribe una dirección absoluta en el CSS). Añádela en el editor si hace falta.', 'digitalisimo-migration' ) ),
	'ext.review.customjs'      => array( 'section' => 'review', 'label' => __( 'JavaScript propio de la página: está en un borrador de Código personalizado limitado a esta página. No se ejecuta hasta que lo revises y lo publiques.', 'digitalisimo-migration' ) ),
	'ext.customjs.not_created' => array( 'section' => 'review', 'label' => __( 'JavaScript propio de la página que deja de ejecutarse: no se creó el borrador porque quien lanzó la migración no puede publicar código sin filtrar. Queda en la copia de los ajustes de la página.', 'digitalisimo-migration' ) ),
	'ext.customjs.inert'       => array( 'section' => 'dropped', 'label' => __( 'JavaScript propio en una plantilla, cabecera, pie o popup: Element Pack nunca lo ejecutaba ahí. Se retira; queda en la copia de los ajustes.', 'digitalisimo-migration' ) ),
);
