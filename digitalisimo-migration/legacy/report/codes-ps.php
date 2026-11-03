<?php
/**
 * Códigos del informe de Prime Slider (frente E; los de F van en `codes-ps-dynamic.php` con el prefijo `ps.dyn.`).
 *
 * `section`: `converted`, `approx`, `created`, `dropped` (información) o `review` (pendiente de revisar); sólo `review`
 * deja el sitio con asuntos pendientes. `review_if_published`: el elemento pasa a «Pendiente de revisar» si el
 * documento está publicado (C2-27). Los textos nombran Prime Slider sin enlace y nunca llevan valores de ajustes.
 */
defined( 'ABSPATH' ) || exit;

return array(
	'ps.converted'                  => array(
		'section' => 'converted',
		'label'   => __( 'Prime Slider convertido a un widget de DIGITALÍSIMO Elements', 'digitalisimo-migration' ),
	),
	'ps.approx'                     => array(
		'section' => 'approx',
		'label'   => __( 'Prime Slider convertido con diferencias declaradas', 'digitalisimo-migration' ),
	),
	'ps.fallback.slides'            => array(
		'section' => 'approx',
		'label'   => __( 'Convertido al widget Diapositivas: el slider anidado necesita los experimentos de contenedores y de elementos anidados', 'digitalisimo-migration' ),
	),
	'ps.blocked.no_target'          => array(
		'section' => 'review',
		'label'   => __( 'Sin destino disponible en este sitio: el widget de Prime Slider se queda como está y Prime Slider no se desactiva', 'digitalisimo-migration' ),
	),
	'ps.dropped.widget'             => array(
		'section' => 'dropped',
		'label'   => __( 'Widget de Prime Slider sin equivalente: queda guardado con su original, sin mostrarse', 'digitalisimo-migration' ),
	),
	'ps.dropped.remote'             => array(
		'section' => 'dropped',
		'label'   => __( 'Control remoto de otro slider sin equivalente: el slider convertido ya trae sus propias flechas, puntos y contador', 'digitalisimo-migration' ),
	),
	'ps.dropped.reveal'             => array(
		'section' => 'dropped',
		'label'   => __( 'Efecto de revelado de Prime Slider sin equivalente', 'digitalisimo-migration' ),
	),
	'ps.dropped.advanced_animation' => array(
		'section' => 'dropped',
		'label'   => __( 'Animación avanzada del texto (GSAP) sin equivalente', 'digitalisimo-migration' ),
	),
	'ps.dropped.adaptive_bg'        => array(
		'section' => 'dropped',
		'label'   => __( 'Fondo adaptable al color de la imagen sin equivalente', 'digitalisimo-migration' ),
	),
	'ps.dropped.kenburns'           => array(
		'section' => 'dropped',
		'label'   => __( 'Efecto Ken Burns sin equivalente en el slider anidado', 'digitalisimo-migration' ),
	),
	'ps.vendor_removed'             => array(
		'section' => 'dropped',
		'label'   => __( 'Contenido de ejemplo de Prime Slider retirado (perfiles sociales, vídeos o mapas de demostración)', 'digitalisimo-migration' ),
	),
	'ps.defaults.static'            => array(
		'section' => 'approx',
		'label'   => __( 'Valores por defecto tomados de la copia de referencia de Prime Slider 4.7.0 (el plugin no estaba cargado)', 'digitalisimo-migration' ),
	),
	'ps.demo.copied'                => array(
		'section' => 'created',
		'label'   => __( 'Imágenes de demostración de Prime Slider copiadas a la biblioteca de medios', 'digitalisimo-migration' ),
	),
	'ps.demo.skipped'               => array(
		'section'             => 'dropped',
		'review_if_published' => true,
		'label'               => __( 'Imágenes de demostración de Prime Slider que no se pudieron copiar: la imagen queda vacía', 'digitalisimo-migration' ),
	),
	'ps.demo.reverted'              => array(
		'section' => 'converted',
		'label'   => __( 'Imagen de demostración copiada, enviada a la papelera de medios al revertir', 'digitalisimo-migration' ),
	),
	'ps.demo.revert_kept'           => array(
		'section' => 'review',
		'label'   => __( 'Imagen de demostración copiada que se conserva en la biblioteca (cambió o no hay papelera de medios)', 'digitalisimo-migration' ),
	),
	'ps.loss.feature'               => array(
		'section' => 'approx',
		'label'   => __( 'Diferencia declarada del widget convertido', 'digitalisimo-migration' ),
	),
	'ps.loss.effect'                => array(
		'section' => 'approx',
		'label'   => __( 'Transición sin equivalente: se usa deslizar o fundido', 'digitalisimo-migration' ),
	),
	'ps.loss.styles'                => array(
		'section' => 'approx',
		'label'   => __( 'Ajustes de estilo de Prime Slider sin equivalente', 'digitalisimo-migration' ),
	),
	'ps.loss.delay_clamped'         => array(
		'section' => 'approx',
		'label'   => __( 'Tiempo por diapositiva ajustado al intervalo admitido (de 2 a 20 segundos)', 'digitalisimo-migration' ),
	),
	'ps.loss.nav_none'              => array(
		'section' => 'approx',
		'label'   => __( 'Sin flechas ni puntos en el original: el slider muestra los puntos (siempre queda una forma de cambiar de diapositiva)', 'digitalisimo-migration' ),
	),
	'ps.loss.video_background'      => array(
		'section' => 'review',
		'label'   => __( 'Fondo de vídeo perdido en el widget Diapositivas: revisa la diapositiva', 'digitalisimo-migration' ),
	),
	'ps.h1'                         => array(
		'section' => 'approx',
		'label'   => __( 'Título con etiqueta h1 conservado: varias diapositivas dan varios h1, como en el original', 'digitalisimo-migration' ),
	),
	'ps.review.custom_css'          => array(
		'section' => 'review',
		'label'   => __( 'CSS personalizado que nombra clases de Prime Slider: ya no se aplica', 'digitalisimo-migration' ),
	),
	'ps.review.no_template'         => array(
		'section' => 'review',
		'label'   => __( 'Diapositiva sin plantilla: el contenedor queda vacío', 'digitalisimo-migration' ),
	),
	'ps.review.extensions'          => array(
		'section' => 'review',
		'label'   => __( 'Extensiones de Element Pack del widget sin convertir: revisa el elemento', 'digitalisimo-migration' ),
	),
	'ps.siblings'                   => array(
		'section' => 'approx',
		'label'   => __( 'Redes sociales o botón de desplazamiento como widgets junto al slider (sin la superposición)', 'digitalisimo-migration' ),
	),
	'ps.reveal.static'              => array(
		'section' => 'approx',
		'label'   => __( 'Contenido fijo de la cabecera como título y texto antes del slider', 'digitalisimo-migration' ),
	),
);
