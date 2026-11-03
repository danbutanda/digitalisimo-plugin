<?php
/**
 * Códigos del informe del frente I (`mk.`): marcadores de K (`digitalisimo-unconverted`) que guardan un widget de
 * Element Pack o de Prime Slider. Formato en Report::catalog(). Sólo `review` deja el sitio «con asuntos por revisar».
 *
 * `mk.convertible`, `mk.blocked` y `mk.declared` los escribe también el análisis previo (Migration::analyze_document(),
 * con `type` y `count`); la tarea escribe `mk.converted`, `mk.blocked`, `mk.declared` y `mk.fragment` (con `widget`).
 */
namespace Digitalisimo\Elements\Legacy;

defined( 'ABSPATH' ) || exit;

return array(
	'mk.convertible' => array( 'section' => 'converted', 'label' => __( 'Widget de Element Pack o de Prime Slider guardado en un marcador (contenido de un kit importado o de una migración anterior que no se muestra): Migrar lo convierte a su widget de DIGITALÍSIMO Elements.', 'digitalisimo-migration' ) ),
	'mk.converted'   => array( 'section' => 'converted', 'label' => __( 'Widget guardado en un marcador convertido a su widget de DIGITALÍSIMO Elements: vuelve a verse en la página.', 'digitalisimo-migration' ) ),
	'mk.blocked'     => array( 'section' => 'review', 'label' => __( 'Widget guardado en un marcador sin destino disponible en este sitio: no se muestra y el editor lo conserva. Se convertirá al volver a pulsar Migrar cuando su destino esté disponible.', 'digitalisimo-migration' ) ),
	'mk.declared'    => array( 'section' => 'dropped', 'label' => __( 'Widget sin equivalente guardado en un marcador: no se muestra y el editor lo conserva. Sustitúyelo por la alternativa que indica el informe si lo necesitas.', 'digitalisimo-migration' ) ),
	'mk.fragment'    => array( 'section' => 'approx', 'label' => __( 'Sólo se convirtió el elemento principal: los elementos que lo acompañaban (redes sociales, botón para bajar) no caben en esa posición y no se añadieron.', 'digitalisimo-migration' ) ),
);
