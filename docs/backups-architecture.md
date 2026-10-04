# Arquitectura actual y evolución de DIGITALÍSIMO Backups

Auditoría inicial realizada sobre la versión 1.0.35. Esta descripción registra el comportamiento observado en el código, no una garantía de recuperación.

## Componentes presentes

| Componente | Estado observado |
| --- | --- |
| `digitalisimo-backups.php` | Registra el módulo, Core compartido y actualizador autónomo. |
| `includes/class-backups.php` | Concentra interfaz, settings, cron, generación ZIP/SQL, subida remota, historial, retención y restauración. No hay separación en servicios o motor de jobs. |
| `includes/class-digitalisimo-core.php` | Integra el menú Digitalisimo con otros módulos sin exigir su presencia. |
| `includes/class-digitalisimo-updater.php` | Consulta Releases propios y ofrece actualizaciones por `Update URI`; independiente de los otros plugins. |
| `assets/admin-ui.css` | Presentación administrativa. |

La configuración vive en options del sitio o de la red. El historial también usa options. Existen acciones `admin-post`, eventos WP-Cron, creación manual y políticas automáticas. Los destinos declarados son local, SFTP, Google Drive y OneDrive. Se ofrecen importación de ZIP propio y conjuntos Updraft. Hay flujos de red y de sitio; la interfaz actual conserva parte del contexto administrativo, pero el motor de respaldo completo aún no está unificado.

## Flujo actual y brechas críticas

1. `run()` y `run_policy()` crean y transfieren un ZIP durante la misma petición o ejecución cron. `remember()` graba el registro tras obtener un archivo y metadatos de destino, sin una máquina de estados ni verificación criptográfica. Un fallo parcial de ZIP, SQL o transferencia puede aparentar éxito.
2. `create_network()` agrega SQL y archivos bajo `ABSPATH`. `create_site()` excluye las subidas de otros sitios, modifica el conjunto de tablas y omite `wp-config.php` en su modo de sitio. En Multisite esto **no** equivale a una copia de WordPress completo. Un backup completo debe abarcar la red, sin inferir que una exportación parcial de subsitio sea restaurable por sí sola.
3. `database_sql()` usa `SHOW TABLES` y consultas `SELECT *` por tabla y acumula el SQL completo en memoria; no congela una instantánea consistente. En 1.0.35 podía omitir tablas si `SHOW CREATE TABLE` fallaba y la copia ignoraba resultados de `ZipArchive::addFile()`. En 1.0.37 esos fallos detienen el trabajo y se reabre y lee cada entrada del ZIP comprobando tamaño y CRC. En 1.0.38 el manifiesto v3 añade tamaño y SHA-256 de SQL y de cada archivo, pero aún faltan lectura por streaming, consistencia de base de datos y autenticación del manifiesto.
4. En 1.0.35 el método llamado `sftp()` subía mediante `ftp_connect()`/`ftp_put()`; su nombre no reflejaba transporte cifrado. En 1.0.36 se sustituyó por SSH2/SFTP, con escritura temporal, verificación SHA-256 y publicación sólo de una copia íntegra. Queda pendiente verificar la clave del host, probar el destino real y revisar datos históricos cuyo proveedor quedó registrado como `ftp`.
5. `create_incremental_site()` selecciona archivos por fecha de modificación y referencia un ZIP base, pero no inventaría eliminaciones ni verifica la cadena. La retención puede borrar la base aunque existan incrementales dependientes.
6. `restore_digitalisimo_zip()` importa SQL antes de extraer archivos. Desde 1.0.37 verifica estructura, lectura, CRC y tipo de paquete antes de importar; en 1.0.38 comprueba SHA-256 cuando hay manifiesto v3. En 1.0.39, además, verifica el hash registrado del ZIP, rechaza alcance de subsitio o topología Multisite incompatible, crea una copia previa bloqueada y escribe cada archivo mediante temporal verificado antes de reemplazarlo. Todavía no verifica espacio suficiente ni hace staging/rollback de toda la instalación. Si falla a mitad, puede dejar datos y archivos de versiones distintas. `restore_updraft_set()` tiene el mismo riesgo general y la validación actual no prueba que todas las piezas de WordPress estén presentes.
7. El almacenamiento local bajo `WP_PLUGIN_DIR` añade `index.php` y `.htaccess`, pero este último no protege Nginx. Hay que moverlo a una ruta privada demostrable o servirlo exclusivamente mediante descarga autenticada, con defensa verificable para servidores web comunes.

## Arquitectura objetivo, compatible por etapas

Un motor único recibirá una especificación de trabajo inmutable (`blog_id`/red, tipo, destinos, retención). Una cola persistente gestionará locks, leases, heartbeat, cancelación y reanudación. Exportadores de archivos y base de datos escribirán a staging privado por lotes; un manifiesto versionado enumerará componentes, tamaños, hashes y compatibilidad. El verificador reabrirá el paquete y comparará todas las entradas antes de autorizar transferencia o registrar `SUCCESS`.

Adaptadores de destino implementarán `put/get/head/delete/verify` con SFTP y APIs oficiales. El trabajo conserva recibos y hashes de cada destino. La retención tratará cada cadena incremental como una unidad. La restauración primero descargará y verificará en staging, tomará una copia de seguridad del destino, activará mantenimiento y aplicará cambios con recuperación o rollback; nunca importará SQL a partir de un archivo no verificado.

REST, WP-CLI y administración invocarán el mismo motor, con capabilities, nonces o autenticación apropiada, sin ejecutar tareas largas durante el guardado. Los formatos antiguos se identificarán como heredados y sólo se restaurarán tras un análisis explícito de las piezas disponibles; no se les atribuirá una integridad que no tienen.
