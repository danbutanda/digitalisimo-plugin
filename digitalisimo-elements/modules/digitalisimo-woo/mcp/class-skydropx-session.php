<?php
namespace Digitalisimo\Elements\Woo\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Sesión de la cotización de prueba de Skydropx: el método del plugin guarda su caché de tarifas (huella, caducidad de
 * 2 minutos y tarifas) en `WC()->session`, que en una petición del MCP no existe. Ésta la sustituye sólo mientras
 * cotiza, con el `get()`/`set()` de WooCommerce sin cambios (no sobrescribe nada), y `Skydropx` guarda sus datos en un
 * transitorio del usuario: la caché del plugin se respeta entre llamadas del MCP sin tocar la sesión de ningún cliente.
 */
final class Skydropx_Session extends \WC_Session {
	/** @param array $data Datos guardados (los de `data()` de una cotización anterior). */
	public function __construct( array $data = array() ) {
		$this->_data        = $data;
		$this->_dirty       = false;
		$this->_customer_id = '';
	}

	/** Lo que el plugin dejó en la sesión. */
	public function data(): array {
		return is_array( $this->_data ) ? $this->_data : array();
	}

	/** ¿Escribió el plugin algo (una cotización nueva)? */
	public function changed(): bool {
		return (bool) $this->_dirty;
	}
}
