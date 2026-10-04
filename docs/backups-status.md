# Estado de DIGITALÍSIMO Backups

Fecha de auditoría inicial: 2026-10-04. Versión inicial analizada: 1.0.35. Primer cambio: 1.0.36. **Objetivo abierto.** La presencia de una función no implica que cumpla los criterios de integridad o de restauración.

## IMPLEMENTADO

- Plugin autónomo con menú compartido, actualizador propio y formularios de administración.
- Interfaz de ejecución manual, políticas programadas, historial y destinos local/Google Drive/OneDrive, además de rutas etiquetadas SFTP.
- Exportación ZIP/SQL, respaldo incremental inicial e importadores de formato propio y de conjuntos Updraft.
- Controles básicos de capability/nonce en acciones administrativas y algunas comprobaciones de rutas del ZIP.

## EN PROGRESO

- Auditoría exhaustiva, documentación del objetivo y separación de los riesgos de integridad antes de modificar el motor.
- Corrección de los caminos donde un ZIP o SQL incompleto puede considerarse válido.
- La subida etiquetada SFTP usa ahora SSH2 y una ruta temporal; compara SHA-256 del archivo remoto antes de publicarlo. Una copia truncada o alterada se rechaza. Aún faltan fijación/verificación de clave del host y prueba contra un servidor SFTP real.

## PENDIENTE

| Bloque | Brecha principal |
| --- | --- |
| Integridad | Estado persistente por trabajo, inventario completo, checksums de origen y destino, verificación antes de `SUCCESS`, fallos no silenciosos. |
| Restauración | Preflight completo, staging, backup previo, atomicidad/rollback, mantenimiento, reanudación, prueba de restaurabilidad y migración E2E. |
| Seguridad | Verificación de clave del servidor SFTP, credenciales seguras, cifrado autenticado, protección del almacén en Apache/Nginx y validación de archivos no confiables. |
| Portabilidad | Manifest versionado, configuración portable, reemplazo de dominio/ruta respetando datos serializados y servidor nuevo. |
| Jobs | Cola, locks, concurrencia, heartbeat, cancelación, reintentos y recuperación tras interrupción. |
| Automatización | Pre-update, políticas fiables, incremental con borrados/cadena íntegra, retención que preserve dependencias y múltiples destinos. |
| Escala | SQL/archivos por streaming, límites de memoria, espacio libre, sitios grandes y WooCommerce con consistencia. |
| Cobertura | WordPress individual y Multisite completo; REST, WP-CLI, logs, alertas e interfaz con estado verificable. |
| Pruebas | Fallos reales, regresión de respaldos antiguos, backup/restore/migración en servidores y dominios distintos. |

## BLOQUEADO

- Ningún bloqueo de implementación confirmado. La validación E2E final requerirá un WordPress de prueba con acceso controlado a dos entornos; no se declarará completada usando sólo mocks o ZIP local.

## VALIDADO

- Se inspeccionó la estructura de código de 1.0.35 y se identificaron rutas concretas con riesgo de éxito falso, pérdida de datos y transporte FTP sin cifrar.
- `tests/backups-sftp.php` simula una copia correcta y otra alterada, y comprueba que sólo la primera se publica. Sintaxis PHP y paquete ZIP 1.0.36 se comprueban antes de la Release. Aún falta una prueba en servidor SFTP real.
- Aún **no** se ha validado ningún respaldo completo y restaurable de esta versión, ni seguridad de destinos ni operación en Multisite real.

## Siguiente bloque

Hacer fallar explícitamente la creación de ZIP y SQL ante cualquier error, verificar el paquete antes de historial/subida y añadir pruebas que inyecten fallos. Después cerrar el preflight de restauración para no modificar destinos con archivos incompletos. Avanzar en el orden de prioridad de `backups-objective.md`.
