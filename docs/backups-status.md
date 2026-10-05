# Estado de DIGITALÍSIMO Backups

Fecha de auditoría inicial: 2026-10-04. Versión inicial analizada: 1.0.35. Cambios publicados: 1.0.36, 1.0.37, 1.0.38, 1.0.39, 1.0.40 y 1.0.41; 1.0.42 en preparación. **Objetivo abierto.** La presencia de una función no implica que cumpla los criterios de integridad o de restauración.

## IMPLEMENTADO

- Plugin autónomo con menú compartido, actualizador propio y formularios de administración.
- Interfaz de ejecución manual, políticas programadas, historial y destinos local/Google Drive/OneDrive, además de rutas etiquetadas SFTP.
- Exportación ZIP/SQL, respaldo incremental inicial e importadores de formato propio y de conjuntos Updraft.
- Controles básicos de capability/nonce en acciones administrativas y algunas comprobaciones de rutas del ZIP.

## EN PROGRESO

- Auditoría exhaustiva, documentación del objetivo y separación de los riesgos de integridad antes de modificar el motor.
- En 1.0.37, los errores de lectura de tablas, adición de archivos, cierre del ZIP o lectura/CRC del contenido rechazan el respaldo; se elimina el ZIP fallido. El cambio de blog se revierte aunque falle la exportación. La restauración rechaza un archivo corrupto o parcial antes de importar SQL.
- La subida etiquetada SFTP usa ahora SSH2 y una ruta temporal; compara SHA-256 del archivo remoto antes de publicarlo. Una copia truncada o alterada se rechaza. Aún faltan fijación/verificación de clave del host y prueba contra un servidor SFTP real.
- En 1.0.38, el respaldo manual y las automatizaciones nuevas crean sólo un ZIP integral de la instalación. El manifiesto v3 enumera SQL y archivos con tamaño y SHA-256; el ZIP se vuelve a leer antes de registrarlo. En Multisite la acción completa exige permisos de red. Las automatizaciones parciales anteriores quedan desactivadas y sus cron se eliminan; sus archivos históricos se conservan. Si `wp-config.php` o `wp-content` están fuera de `ABSPATH`, o hay enlaces simbólicos, la operación falla expresamente porque aún no se incluyen esas rutas.
- En 1.0.39, la restauración propia comprueba el SHA-256 del ZIP registrado, rechaza exportaciones anteriores de subsitio y alcances incompatibles, crea una copia previa bloqueada fuera de la rotación y verifica cada archivo escrito antes de reemplazarlo. Sigue sin contar con rollback automático de base de datos ni staging total de la instalación.
- En 1.0.40, un fallo durante la importación o extracción desencadena un intento de recuperación desde la copia previa. Si también falla, el ZIP de seguridad sigue disponible y el error indica que requiere intervención manual. La recuperación actual repone base de datos y archivos existentes, pero todavía no elimina archivos nuevos que hayan quedado tras el intento; no se considera atomicidad completa.
- En 1.0.41, el SQL de los respaldos integrales se escribe por filas en un temporal y se importa desde el ZIP por sentencias, reduciendo el pico de memoria. Las tablas InnoDB se leen en una instantánea transaccional; si aparece una tabla no transaccional se bloquean las tablas durante la exportación SQL. Un DB drop-in sin acceso `mysqli` pagina por clave primaria y lee de una vez las tablas sin ella para no omitir filas. Todavía faltan consistencia entre archivos y base de datos, BLOBs/objetos especiales y pruebas con bases grandes reales.
- En 1.0.42, SFTP exige una huella SHA-1 de la clave SSH configurada y la compara antes de enviar credenciales o respaldos. Las conexiones anteriores sin huella quedan inhabilitadas hasta configurarla. Todavía falta la prueba contra un servidor SFTP real.

## PENDIENTE

| Bloque | Brecha principal |
| --- | --- |
| Integridad | El manifiesto v3 y SHA-256 cubren el ZIP local; faltan estado persistente por trabajo, firma/autenticación, verificación de copias en todos los destinos y tolerancia a archivos cambiantes. |
| Restauración | Preflight completo, staging, backup previo, atomicidad/rollback, mantenimiento, reanudación, prueba de restaurabilidad y migración E2E. |
| Seguridad | Verificación de clave del servidor SFTP, credenciales seguras, cifrado autenticado, protección del almacén en Apache/Nginx y validación de archivos no confiables. |
| Portabilidad | Manifest versionado, configuración portable, reemplazo de dominio/ruta respetando datos serializados y servidor nuevo. |
| Jobs | Cola, locks, concurrencia, heartbeat, cancelación, reintentos y recuperación tras interrupción. |
| Automatización | Pre-update, políticas fiables, incremental con borrados/cadena íntegra, retención que preserve dependencias y múltiples destinos. |
| Escala | SQL por streaming implementado en el flujo integral; faltan probar bases grandes, BLOBs, objetos SQL especiales, espacio libre, archivos grandes y consistencia entre archivos, base de datos y WooCommerce. |
| Cobertura | WordPress individual y Multisite completo; REST, WP-CLI, logs, alertas e interfaz con estado verificable. |
| Pruebas | Fallos reales, regresión de respaldos antiguos, backup/restore/migración en servidores y dominios distintos. |

## BLOQUEADO

- Ningún bloqueo de implementación confirmado. La validación E2E final requerirá un WordPress de prueba con acceso controlado a dos entornos; no se declarará completada usando sólo mocks o ZIP local.

## VALIDADO

- Se inspeccionó la estructura de código de 1.0.35 y se identificaron rutas concretas con riesgo de éxito falso, pérdida de datos y transporte FTP sin cifrar.
- `tests/backups-sftp.php` simula una copia correcta y otra alterada, y comprueba que sólo la primera se publica. El workflow `37185250540` pasó sintaxis PHP y suite; la Release `v2026.10.04.231` contiene sólo `digitalisimo-backups-1.0.36.zip`. La descarga pública pasó `unzip -t` y su SHA-256 es `bfed121ae97e1221b8152f60ccdcd21d174afc03710333ef019ba1e777cb6dcf`. Aún falta una prueba en servidor SFTP real.
- `tests/backups-integrity.php` comprueba fallos de exportación SQL, creación ZIP sin metadata, archivos fuente ausentes, rechazo de un incremental durante restauración y rutas transversales. El workflow `37185919837` pasó y la Release `v2026.10.04.232` contiene sólo `digitalisimo-backups-1.0.37.zip`; descarga pública íntegra con SHA-256 `4bd91b99cdce367df7dc28881f4eb67d28f04830d600999eda7efd19d356bc9d`. Pendiente validar la actualización y un respaldo real en WordPress.
- `tests/backups-manifest.php` crea un WordPress sintético y rechaza un archivo alterado con CRC recalculado; `tests/backups-policies.php` comprueba la desactivación persistente de tareas parciales. El workflow `37187410172` pasó y la Release `v2026.10.04.233` contiene sólo `digitalisimo-backups-1.0.38.zip`; la descarga pública pasó `unzip -t` y su SHA-256 es `53c4cd8ce71c00ac229fe32e6cb5fd0b78e017f6124df7e4a86dcace023fe0f8`. Pendiente realizar una copia real.
- Para 1.0.39, `tests/backups-manifest.php` también comprueba copia previa al restaurar, conservación de la copia ante fallo SQL, ausencia de un archivo obligatorio y rechazo de enlaces simbólicos; `tests/backups-integrity.php` rechaza una exportación anterior de subsitio como restauración total. El workflow `37188303810` pasó y la Release `v2026.10.04.234` contiene sólo `digitalisimo-backups-1.0.39.zip`; la descarga pública pasó `unzip -t` y su SHA-256 es `c6bcfac121de6b7934bc9293a5f7694d04a7b5c068bd36947842a4a887394e9c`.
- Para 1.0.40, `tests/backups-manifest.php` simula un fallo SQL recuperable y uno persistente; verifica el mensaje y la conservación de cada ZIP previo. El workflow `37256124029` pasó y la Release `v2026.10.05.235` contiene sólo `digitalisimo-backups-1.0.40.zip`; la descarga pública pasó `unzip -t` y su SHA-256 es `47e84c3e94343aa813176ea9c55ddb0661079e71a5c1d0d1033e8b24faaca51d`.
- Para 1.0.41, `tests/backups-streaming.php` prueba paginación ordenada, lectura de tabla sin clave primaria, snapshot InnoDB, bloqueo MyISAM, fallo de un lote e importación de una sentencia que cruza el límite de lectura. La suite de siete módulos y cinco pruebas de Backups pasó. Los workflows de validación `37257125557` y publicación `37257125579` terminaron correctamente; la Release `v2026.10.05.236` contiene sólo `digitalisimo-backups-1.0.41.zip`. La descarga pública pasó `unzip -t` y su SHA-256 es `9d725ab8681a343afe6d0c836782ff6921ccc64403b2f7ca582b515d2a27b36a`.
- Aún **no** se ha validado ningún respaldo completo y restaurable de esta versión, ni seguridad de destinos ni operación en Multisite real.

## Siguiente bloque

Cerrar las brechas de seguridad y restauración: sacar el almacén local de una ruta potencialmente pública, verificar la clave del servidor SFTP y preparar una restauración transaccional que retire también los archivos añadidos si falla. Después abordar rutas externas y destinos remotos según `backups-objective.md`.
