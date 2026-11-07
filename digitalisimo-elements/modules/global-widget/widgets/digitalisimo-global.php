<?php
namespace ElementorPro\Modules\GlobalWidget\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * DIGITALÍSIMO · Widget global propio (`digitalisimo-global`; programa «sin widgets de PRO», C9, corrección de
 * fidelidad: `docs/pro/c9.md`). «Migrar» y los kits pasan a él cada instancia del widget global de PRO (`global`) cuya
 * plantilla existe: conserva su `templateID` y, como el de PRO, lee la plantilla de widget en cada pintado y pinta su
 * elemento con el id de la instancia, las clases `elementor-global-<plantilla>` y `elementor-widget-<tipo>` y el
 * `data-widget_type` del contenido. Así, editar la plantilla cambia a la vez todas las páginas que la usan, como en PRO
 * (antes, la instancia se sustituía por una copia del contenido y perdía el vínculo).
 *
 * Es la misma función del constructor que el de PRO (hereda de él, sin otro motor): el editor lo trata igual (el
 * elemento llega al editor con el tipo de su plantilla y su `templateID`), la exportación lo exporta como el contenido de
 * su plantilla (`on_export()`) y no sale en el panel. Al guardar en el editor, el módulo devuelve a este tipo las
 * instancias que ya lo eran (`Module::keep_own_globals()`); una instancia nueva queda como la de PRO y la siguiente
 * pasada de «Migrar» la pasa a este.
 */
class Digitalisimo_Global extends Global_Widget {

	public function get_name() {
		return 'digitalisimo-global';
	}

	public function get_title() {
		return esc_html__( 'Global', 'pro-elements' );
	}
}
