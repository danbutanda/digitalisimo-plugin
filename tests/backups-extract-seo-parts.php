<?php
/**
 * La extracción a sitio individual y el permiso por partes de SEO (tanda B, B5). Un sitio que no heredaba el interruptor
 * único `seo_abilities_write` (lo tenía apagado por su cuenta) no puede quedar con la escritura MCP abierta porque la red
 * ya guardó las cuatro partes: para una parte ausente de su mapa de herencia vale la herencia del interruptor único.
 * Falla con 029d7fbf. Uso: php tests/backups-extract-seo-parts.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
require __DIR__ . '/../digitalisimo-backups/includes/class-backups-site-extract.php';
function check( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$X     = 'Digitalisimo_Backups_Site_Extract';
$parts = array( 'seo_abilities_write_meta', 'seo_abilities_write_schema', 'seo_abilities_write_index', 'seo_abilities_write_redirects' );
$network = array( 'digitalisimo_integrations_options' => array( 'enable_seo' => 1, 'seo_abilities_write' => 1, 'seo_abilities_write_meta' => 1, 'seo_abilities_write_schema' => 1, 'seo_abilities_write_index' => 1, 'seo_abilities_write_redirects' => 1 ) );

// Sin migrar y sin heredar el interruptor (apagado por su cuenta): no recibe las partes de la red.
$site = array( 'digitalisimo_integrations_options' => array( 'seo_abilities_write' => 0 ), 'digitalisimo_seo_network_inherit' => array( 'seo_abilities_write' => false ) );
$o    = $X::materialize( $site, $network, false, null )['set']['digitalisimo_integrations_options'];
check( 0 === $o['seo_abilities_write'], 'Conserva su interruptor apagado.' );
foreach ( $parts as $key ) check( ! array_key_exists( $key, $o ), "No hereda $key de la red: " . json_encode( $o ) );

// El que heredaba el interruptor recibe las cuatro partes de la red.
$site = array( 'digitalisimo_integrations_options' => array(), 'digitalisimo_seo_network_inherit' => array() );
$o    = $X::materialize( $site, $network, false, null )['set']['digitalisimo_integrations_options'];
foreach ( $parts as $key ) check( 1 === ( $o[ $key ] ?? null ), "Hereda $key de la red." );

// Una parte que el sitio ya eligió manda sobre el interruptor único.
$site = array( 'digitalisimo_integrations_options' => array( 'seo_abilities_write' => 0, 'seo_abilities_write_meta' => 0 ), 'digitalisimo_seo_network_inherit' => array( 'seo_abilities_write' => false, 'seo_abilities_write_meta' => 1 ) );
$o    = $X::materialize( $site, $network, false, null )['set']['digitalisimo_integrations_options'];
check( 1 === $o['seo_abilities_write_meta'] && ! array_key_exists( 'seo_abilities_write_index', $o ), 'La parte que el sitio marcó como heredada se hereda; las demás siguen al interruptor único.' );
echo "Extracción a sitio individual: las partes del permiso de SEO heredan como el interruptor único.\n";
