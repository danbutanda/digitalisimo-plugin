# DIGITALÍSIMO Backups

Objetivo, estado vigente, arquitectura y almacenamiento del plugin `digitalisimo-backups/`. El historial de cada
versión publicada está en [PLUGIN_ROADMAP.md](PLUGIN_ROADMAP.md) y en el historial de Git.

## Objetivo

Un respaldo es **la instalación completa de WordPress, tratada como una unidad indivisible**: archivos del núcleo,
`wp-content`, configuración y base de datos. En Multisite, un respaldo completo incluye la red entera, sus sitios y
tablas. Un archivo parcial no puede llamarse respaldo completo.

El objetivo sigue **abierto** hasta demostrar respaldo, verificación, transferencia, restauración y migración
completos, incluso a otro servidor o dominio, sin depender del hosting de origen. Cada cambio conserva los datos
existentes y las funciones utilizables mientras sustituye los caminos inseguros.

Condiciones de aceptación:

1. Un trabajo persistente avanza por `QUEUED`, `RUNNING`, `VERIFYING` y, si aplica, `TRANSFERRING`, hasta `SUCCESS`,
   `FAILED` o `CANCELLED`, y sólo llega a `SUCCESS` tras verificar todas las partes en todos los destinos exigidos.
2. El paquete lleva un manifiesto versionado, inventario íntegro y sumas criptográficas. Una copia incompleta,
   ilegible, truncada, alterada o sin piezas requeridas se rechaza antes de tocar el sitio de destino.
3. La restauración completa es portable entre servidor, ruta y dominio; conserva datos serializados, cambia URL con
   seguridad y se recupera de fallos. La prueba decisiva es una restauración extremo a extremo en otro entorno.
4. Automatización, incrementales con base válida, respaldo previo a actualizaciones, retención consciente de cadenas,
   varios destinos, reintentos, cifrado, SFTP real e instalaciones grandes y Multisite comparten un mismo motor.
5. Interfaz, REST y WP-CLI muestran el mismo estado real, con registros, alertas y comprobaciones de restaurabilidad.
   WordPress individual y una red funcionan sin otro plugin DIGITALÍSIMO activo.
6. Las condiciones se verifican con pruebas automatizadas, inyección de fallos y ensayos reales; ningún ZIP por sí solo
   prueba que el objetivo terminó.

Prioridad: integridad de datos → restaurabilidad → seguridad → portabilidad → fiabilidad → recuperación ante fallos →
automatización → compatibilidad → rendimiento → experiencia de usuario.

## Estado vigente

**Implementado y cubierto por pruebas** (`tests/backups-*.php`, en la suite de los dos workflows):

| Garantía | Desde | Prueba |
| --- | --- | --- |
| El respaldo manual y las automatizaciones nuevas crean sólo un ZIP integral (instalación o red completa); las políticas parciales antiguas quedan desactivadas y sus archivos se conservan. | 1.0.38 | `backups-policies.php` |
| Manifiesto v3 con tamaño y SHA-256 de SQL y archivos; el ZIP se vuelve a leer antes de registrarlo. Errores de tabla, archivo, cierre o CRC rechazan el respaldo y borran el ZIP fallido. | 1.0.37–1.0.38 | `backups-integrity.php`, `backups-manifest.php` |
| SQL por filas a un temporal e importación por sentencias; instantánea InnoDB o bloqueo de lectura con tablas no transaccionales; paginación por clave primaria con DB drop-ins; SQLite oficial con su transacción. | 1.0.41, 1.0.45 | `backups-streaming.php` |
| La restauración comprueba el SHA-256 registrado, rechaza alcances incompatibles, crea una copia previa bloqueada, escribe cada archivo mediante temporal verificado y, si falla, recupera desde la copia previa y retira los archivos nuevos que escribió. | 1.0.39–1.0.43 | `backups-manifest.php`, `backups-integrity.php` |
| SFTP por SSH2 con huella SHA-1 obligatoria de la clave del servidor, escritura temporal y comparación SHA-256 antes de publicar. | 1.0.36, 1.0.42 | `backups-sftp.php` |
| Google Drive y OneDrive vuelven a descargar la copia subida y comparan tamaño y SHA-256 antes de aceptarla. | 1.0.47 | `backups-cloud-verification.php` |
| Almacén local privado fuera de la raíz web (ver «Almacenamiento local»), con migración verificada del almacén anterior. | 1.0.44 | `backups-storage.php`, `backups-manifest.php` |
| Bloqueo exclusivo del almacén durante respaldos, cron, políticas y restauraciones. | 1.0.46 | `backups-operation-lock.php` |
| Avance del respaldo manual por base de datos, archivos y verificación; comprobación de alcance y archivos esenciales antes de iniciar; `wp-config.php` en la carpeta superior incluido. | 1.0.49 | `backups-manual-progress.php` |
| Actualizador propio por el índice de la Release, en la carpeta real del plugin y conservándola al actualizar. | 1.0.48–1.0.55 | `backups-updater.php`, `backups-updater-manifest.php`, `updater-real-folder.php` |

Ensayos en WordPress Playground 7.1.2 (SQLite): respaldo y restauración de un sitio (ZIP de 37 MB, 3792 archivos) y
de una red con un segundo sitio; en ambos un cambio posterior al respaldo volvió a su valor anterior y el historial
conservó el ZIP original y la copia previa. No sustituyen ensayos con MySQL, Multisite en un servidor convencional,
migración a otro servidor ni destinos remotos reales.

**Pendiente:**

| Bloque | Brecha principal |
| --- | --- |
| Trabajos | Cola persistente, estados por trabajo, heartbeat, cancelación, reintentos y reanudación tras una interrupción (el bloqueo del almacén no la sustituye). |
| Restauración | Preflight completo, espacio libre, staging de toda la instalación, mantenimiento, rollback atómico y migración extremo a extremo a otro servidor o dominio con datos serializados. |
| Seguridad | Cifrado autenticado, credenciales seguras, firma del manifiesto y validación de archivos no confiables. |
| Automatización | Respaldo previo a actualizaciones, incrementales con borrados y cadena íntegra, retención que preserve dependencias y varios destinos por trabajo. |
| Escala | Bases y archivos grandes reales, BLOBs, objetos SQL especiales y consistencia entre archivos y base de datos (WooCommerce). |
| Cobertura | REST y WP-CLI sobre el mismo motor; rutas externas (`wp-config.php` o `wp-content` fuera de `ABSPATH`, enlaces simbólicos) siguen fallando de forma explícita. |
| Ensayos reales | MySQL, Multisite convencional, almacén privado en hosting real, servidor SFTP real y cuentas reales de Google Drive y OneDrive. |

Siguiente bloque: cerrar seguridad y restauración en entornos reales (MySQL, Multisite, almacén privado y SFTP en un
hosting real) y preparar la restauración transaccional; después, rutas externas y destinos remotos.

## Arquitectura

| Componente | Función |
| --- | --- |
| `digitalisimo-backups.php` | Registra el módulo, el menú compartido y el actualizador propio. |
| `includes/class-backups.php` | Interfaz, ajustes, cron, ZIP/SQL, destinos, historial, retención y restauración (aún sin servicios separados ni motor de trabajos). |
| `includes/class-digitalisimo-core.php` | Integra el menú DIGITALÍSIMO sin exigir otro plugin. |
| `includes/class-digitalisimo-updater.php` | Actualizador por `Update URI` e índice de la Release, independiente de los demás plugins. |
| `assets/backups-admin.css` | Presentación propia sobre el diseño compartido `digitalisimo-ui/`. |

La configuración y el historial viven en opciones del sitio o de la red. Hay acciones `admin-post`, eventos WP-Cron,
creación manual y políticas automáticas; destinos local, SFTP, Google Drive y OneDrive; e importación de ZIP propio y
de conjuntos Updraft. Los formatos heredados (copia de base de datos, copia por sitio, Updraft) siguen inventariados,
pero no satisfacen el requisito de respaldo completo y no se presentan como verificados.

Riesgos conocidos del flujo actual:

1. `run()` y `run_policy()` crean y transfieren el ZIP en la misma petición o ejecución de cron, sin máquina de estados.
2. `create_incremental_site()` selecciona por fecha de modificación y no inventaría borrados ni verifica la cadena; la
   retención puede borrar la base de incrementales dependientes.
3. `restore_digitalisimo_zip()` importa SQL antes de extraer archivos; la recuperación es de mejor esfuerzo, no atómica.
   `restore_updraft_set()` no prueba que estén todas las piezas de WordPress.
4. `database_sql()` heredado acumula SQL en memoria para exportaciones antiguas; el respaldo integral usa
   `database_sql_to_file()` e `import_zip_sql()`.

Arquitectura objetivo, por etapas compatibles: un motor único recibe una especificación de trabajo inmutable (sitio o
red, tipo, destinos, retención) y una cola persistente gestiona bloqueos, heartbeat, cancelación y reanudación. Los
exportadores escriben a un staging privado por lotes; el verificador reabre el paquete y compara todas las entradas
del manifiesto antes de transferir o registrar `SUCCESS`. Los destinos implementan `put/get/head/delete/verify` y el
trabajo guarda recibos y hashes de cada uno; la retención trata cada cadena incremental como una unidad. La
restauración descarga y verifica en staging, respalda el destino, activa mantenimiento y aplica los cambios con
rollback, y nunca importa SQL de un archivo no verificado. Interfaz, REST y WP-CLI invocan el mismo motor, con
capability, nonce o autenticación, sin tareas largas durante el guardado.

## Almacenamiento local

Desde 1.0.44 los ZIP se guardan en una carpeta privada fuera de la raíz pública del servidor y de WordPress. Al abrir la
pantalla Backups, el plugin identifica la raíz web, crea la carpeta con permisos `0700` y traslada los archivos del
antiguo `wp-content/plugins/digitalisimo-backups-storage`. Los ZIP históricos conservan su nombre y siguen en el
historial; la migración compara SHA-256 antes de retirar una copia y se detiene ante archivos con el mismo nombre y
distinto contenido.

Si el hosting no permite crear una carpeta junto a la raíz web, define en `wp-config.php` una ruta absoluta privada y
escribible por PHP:

```php
define( 'DIGITALISIMO_BACKUPS_STORAGE_DIR', '/ruta/privada/digitalisimo-backups' );
```

La ruta debe quedar fuera del directorio público, de `ABSPATH`, de `WP_CONTENT_DIR` y de `WP_PLUGIN_DIR` (nunca
`uploads`, `plugins` ni otra carpeta que el servidor pueda servir). Sin una ruta segura, el plugin muestra el motivo y
no crea respaldos. En WP-CLI o cron sin `DOCUMENT_ROOT`, abre antes la pantalla Backups para registrar la ruta o
define la constante.

Después de actualizar, comprueba en Backups que aparecen los archivos históricos y descarga uno con la acción
autenticada. Si una migración no termina, los originales permanecen en su ubicación anterior y el plugin muestra el
error: corrige permisos o configura otra carpeta privada antes de reintentar.
