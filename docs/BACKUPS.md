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
| **Trabajos persistentes (T1).** Todo respaldo (manual por AJAX o formulario, «Ejecutar ahora», automatizaciones y hooks de cron anteriores) es un trabajo en cola con estado en el almacén privado (`jobs/<uuid>/job.json`, escritura atómica, carpetas `0700` y archivos `0600`), no en la base de datos. Estados `queued → running → verifying → transferring → success \| failed \| cancelled`; sólo llega a `success` después de verificar el ZIP y, con destino remoto, la copia remota. Fases `preflight`, `database`, `pack`, `verify`, `publish`, `transfer` y `prune`, cada una un paso con punto de control: tras una muerte (proceso matado, tiempo agotado, reinicio) el trabajo repite sólo la fase interrumpida y nunca lo confirmado (una base de datos exportada no se exporta otra vez; un ZIP verificado no se reconstruye si falla la copia remota). Todo lo intermedio vive en `jobs/<uuid>/staging/` y el nombre final sólo nace de un `rename()` tras verificar; el SQL ya no pasa por `/tmp`. El latido escribe en el almacén, nunca dentro de la instantánea de la base de datos. Detector: un arrendamiento de este servidor con el cerrojo libre es un proceso muerto (el kernel libera `flock`); uno vivo nunca se toca aunque su latido sea viejo («sin señal»); el de otro servidor sólo se toma tras 30 min sin latido, con fencing. Reintentos con espera de 1, 5 y 15 min ±20 % y tope por fase (2 base de datos, 3 las demás, 5 transferencia); errores permanentes (requisitos, credenciales, huella SFTP) fallan al momento; caducidad a las 24 h. Cancelación desde la tarjeta «Trabajos» (AJAX y formulario sin JavaScript, con capability, nonce propio y contexto de sitio o red): revierte la instantánea, borra lo intermedio y, durante la transferencia, retira la sesión remota y conserva el ZIP local verificado; tras registrar el respaldo ya no se cancela. Disparadores: el navegador mientras la pantalla está abierta, un tick único de WP-Cron mientras quede trabajo y un watchdog horario (colgados, tick perdido tras restaurar la base de datos, restos de versiones anteriores de más de 24 h). La restauración se niega con un trabajo activo y su copia previa usa el mismo motor. La retención, global o de una política, nunca borra el ZIP de un trabajo sin terminar (el verificado que espera el reintento de su copia remota, o el recién creado con la misma fecha que otro); un tick que encuentra el cerrojo ocupado, el detector y la restauración dejan programado el siguiente tick; y el historial se lee de la base de datos (nunca de la caché de la petición) después de calcular el SHA-256 del ZIP, y cada cambio suyo (registro, estado de la copia remota, bloqueo, eliminación y retención) va con el cerrojo corto del almacén, así que un «Bloquear» pulsado durante un proceso largo (WP-CLI o varios trabajos seguidos) no se pierde, la retención no borra ese respaldo y registrar otra vez el mismo ZIP conserva su bloqueo. Una copia de Google Drive u OneDrive que sube completa pero no supera la verificación se retira del destino antes de reintentar, así que ningún intento deja allí una copia sin verificar con el nombre del respaldo. En una red, la cola es de toda la instalación: sólo el sitio principal (o la administración de red) la hace avanzar, pasa el detector o cancela, y en un subsitio la tarjeta «Trabajos» sólo informa. Un área de trabajo que sea un enlace simbólico nunca recibe el SQL ni el ZIP. | 1.0.59 | `backups-job-store.php`, `backups-job-states.php`, `backups-job-heartbeat.php`, `backups-job-resume.php`, `backups-job-cancel.php`, `backups-job-compat.php`, `backups-job-kill.php`, `backups-jobs-ui.mjs` |
| Validación de archivos no confiables antes de la copia previa y de cualquier consulta o escritura: rutas (`..`, absolutas, unidades, NUL, `\` sin firma, raíces desconocidas), enlaces y archivos especiales, entradas cifradas, métodos de compresión, duplicados, colisiones de mayúsculas, número de entradas, esquema estricto del manifiesto v3 y espacio en disco. La extracción nunca escribe más de lo declarado. UpdraftPlus pasa por la misma validación y el mismo escritor seguro. | 1.0.59 | `backups-untrusted-archive.php` |
| Guardián SQL: un solo divisor por fragmentos (idéntico al anterior) y una lista blanca de sentencias, en seco antes de la copia previa y por sentencia al importar. Acepta todo lo que escribió cualquier versión del exportador; en un archivo no confiable rechaza GRANT, LOAD DATA, INTO OUTFILE, funciones o SELECT en INSERT, DATA/INDEX DIRECTORY, CONNECTION, motores remotos (también escritos como texto o entre comillas invertidas: `ENGINE='CONNECT'`), tablas fuera del manifiesto y comentarios ejecutables ajenos. Lee los comentarios como MySQL: «--» seguido de un carácter de control (también DEL) es comentario, y un `/*!` de seis dígitos sólo se salta si también lo es leído con cinco (MySQL puede leer `/*!900000` como 9.0.0). UpdraftPlus usa el dialecto mysqldump, por fragmentos y sin cargar el SQL en memoria. | 1.0.59 | `backups-sql-guard.php` |
| Firma HMAC-SHA512/256 de los bytes exactos del manifiesto con la clave del sitio, verificada al crear y al restaurar. Tres niveles de confianza: firmado, registrado en este sitio (fila del historial con el mismo SHA-256) y sin firma, que pide `RESTAURAR SIN FIRMA` o se bloquea con «Restaurar sólo respaldos firmados». | 1.0.59 | `backups-manifest-auth.php` |
| Clave de los respaldos: constante `DIGITALISIMO_BACKUPS_KEY` o frase del administrador (PBKDF2-SHA256, 600 000 iteraciones) guardada envuelta con AUTH_KEY y SECURE_AUTH_KEY; ni la frase ni la clave vuelven al navegador ni entran en ningún respaldo. Llavero con claves retiradas que siguen sirviendo para restaurar; una restauración (también la de UpdraftPlus) nunca lo pierde ni debilita la protección, y los reconstruye en cuanto termina el SQL, antes de extraer los archivos. Pestaña «Seguridad». | 1.0.59 | `backups-keys.php` |
| Cifrado autenticado opcional (contenedor DGBK v1, `crypto_secretstream_xchacha20poly1305`, libsodium o el sodium_compat de WordPress): el ZIP verificado se cifra al final de la fase «verify» de su trabajo, dentro de su área de trabajo (`jobs/<uuid>/staging/`), se descifra completo para comprobarlo y sólo entonces se publica en el almacén con `rename()`; el ZIP en claro nunca sale del área de trabajo y se borra al publicar. Lo que deja en `.staging/` (el ZIP descifrado de una restauración) o en un `*.part` un proceso interrumpido lo borra la operación siguiente, cifre o no, al tomar el bloqueo, y el área de un trabajo fallido o cancelado se borra con él. Se restaura con la clave del sitio o escribiendo la frase en otro servidor. Falla cerrado: nunca sale en claro un respaldo que debía cifrarse. Sin la clave no se puede restaurar, y la interfaz lo dice antes de intentarlo: la columna «Seguridad» del historial marca «falta la clave» y la lista de Restaurar pide «escribe su frase o su clave», también para un `.dgbk` suelto o renombrado. | 1.0.59 | `backups-encryption.php` |
| Compatibilidad: los respaldos de todas las versiones con manifiesto v3 que conserva el repositorio (19, de 1.0.38 a 1.0.58) se siguen restaurando, con su fila como propios y sin ella como no confiables, y dejan el mismo estado que antes; los de red anteriores a v3 (13 versiones, de 1.0.1 a 1.0.37, sin SHA-256 en su fila), como no confiables; 1.0.58 restaura un respaldo nuevo firmado sin cifrar y su rotación no borra los `.dgbk`. | 1.0.59 | `backups-compat-legacy.php` |
| Restauración interrumpida: antes del SQL se escribe en el almacén un diario sin secretos (historial con la copia previa, seguridad y la parte pública del llavero: huellas y parámetros de cada frase). Si el proceso muere mientras corre el SQL, la operación siguiente (o la pantalla de Backups) lo aplica al tomar el bloqueo: el historial vuelve, la seguridad nunca queda más débil y cada clave vuelve sin su envoltura, así que el cifrado falla cerrado hasta volver a escribir la frase, que abre la misma clave. Sin diario no se restaura. | 1.0.59 | `backups-interrupted-restore.php` |
| **Seguridad dentro de los trabajos.** La especificación inmutable de cada trabajo fija al encolarse si se cifra y la huella de la clave (`security: {encrypt, key_id}`), nunca la clave ni la frase, y el nombre final (`.dgbk` si se cifra). Con el cifrado activo y sin clave no se encola nada. Cada fase vuelve a abrir esa clave por su huella: «pack» firma el manifiesto aunque entretanto se haya activado otra frase, «verify» comprueba la firma y, si el trabajo cifra, sella el DGBK en el área de trabajo, y «publish» lo publica y registra una sola vez aunque el proceso muera tras el `rename()`. Una muerte durante el sellado repite la verificación y el sellado sin volver a empaquetar. Si la clave fijada deja de abrirse (se quitó del llavero o cambiaron las claves de `wp-config.php`), un trabajo cifrado falla cerrado y de forma permanente sin publicar nada; uno sin cifrar vuelve a empaquetarse sin firma. La copia previa a una restauración usa el mismo motor y nunca se cifra: se firma si hay clave. | 1.0.59 | `backups-job-security.php` y, por el motor real, `backups-encryption.php`, `backups-keys.php`, `backups-manifest-auth.php`, `backups-interrupted-restore.php` y `backups-compat-legacy.php` |
| REST (`digitalisimo-backups/v1`) y WP-CLI (`wp digitalisimo-backups`) usan los casos de uso de la pantalla (`Digitalisimo_Backups_Service`) sobre el motor de «Trabajos» y «Seguridad»: estado, historial, crear, verificar, restaurar, automatizaciones y la cola (listar, consultar, avanzar y cancelar). La pantalla («Crear respaldo completo» por AJAX o formulario, «Ejecutar ahora» y «Restaurar») pasa por los mismos casos de uso. Crear y ejecutar una automatización encolan un trabajo y nunca lo duplican: REST responde 202 con el trabajo (409 con el que ya estaba pendiente) y `POST /jobs/tick` lo hace avanzar como el navegador; WP-CLI lo hace avanzar en su proceso hasta terminar (o sólo lo encola con `--async`), y `wp digitalisimo-backups jobs list|cancel|run` opera sobre la misma cola. Restaurar y verificar abren el paquete como la pantalla: aceptan `.dgbk`, piden `RESTAURAR` o `RESTAURAR SIN FIRMA` según su confianza («Restaurar sólo respaldos firmados» responde 403) y `verify` hace las mismas comprobaciones del paquete que la restauración. Un respaldo cifrado cuya clave no tiene el sitio responde, antes de tomar el bloqueo, `secret_required` con la huella corta y dónde va la frase: en REST sólo en el cuerpo (en la URL, 400); en WP-CLI por `--secret-file` o `--secret-stdin` (`--secret` es un error). Ninguna respuesta ni salida lleva la frase, la clave, su envoltura ni la huella completa, tampoco al rechazar una frase equivocada. REST pide la capability de la pantalla con la autenticación de WordPress (401 sin sesión, 403 sin permiso). En una red sólo responde el sitio principal (con varias redes, el de la red principal, porque el respaldo las abarca todas; el motor tampoco exporta desde una red secundaria), y crear, restaurar o ejecutar exigen el alcance de red explícito. Otra operación en curso o un trabajo activo impiden restaurar (409). Los resúmenes no llevan la ruta del almacén ni identificadores remotos (ruta SFTP o identificador de Google Drive y OneDrive); un aviso que impide respaldar nombra la carpeta que hay que corregir (ver «REST y WP-CLI»). Ensayado en Playground: WordPress 7.1.4, sitio individual y red (`tests/playground/backups-coverage.sh`). | 1.0.60 | `backups-service.php`, `backups-rest.php`, `backups-cli.php` |
| Rutas externas. `wp-config.php` en la carpeta superior o enlazado (archivos compartidos de un despliegue) se respalda; la restauración nunca lo escribe ni crea otro en `ABSPATH`. Fuera de `ABSPATH` o tras un enlace simbólico, wp-content, plugins, mu-plugins, medios (en una red, también los de cada sitio con `upload_path` propio, salvo con la constante `UPLOADS`, que hace a WordPress ignorarlo) o un directorio de temas se rechazan antes de exportar nada (al crear o ejecutar desde la pantalla, REST o WP-CLI, antes de encolar el trabajo), con la carpeta, quién la define y qué hacer; antes, plugins, medios y temas externos se omitían sin aviso. La restauración se detiene antes de tocar el sitio, un `ABSPATH` que es enlace de despliegue (`current → releases/N`) funciona y otro enlace dentro de `ABSPATH` hace fallar el trabajo al momento (error permanente, sin reintentos) con su destino, sin dejar ZIP. | 1.0.60 | `backups-layout.php`, `backups-external-config.php` |
| **Credenciales de los destinos cifradas que viajan en el respaldo (S4).** La contraseña de SFTP y el Client secret y el Refresh token de Google Drive y OneDrive viven en `digitalisimo_backups_credentials` (de la red en Multisite), cada uno cifrado con AEAD XChaCha20-Poly1305 (libsodium o el sodium_compat de WordPress) con una clave que sale de `AUTH_KEY` y `SECURE_AUTH_KEY` con su propia separación de dominio, y un dato adicional que lo ata a su campo. `digitalisimo_backups_settings` ya no guarda ninguno en claro: guardar «Servidores», la conexión OAuth (refresh token) y la migración de los sitios que vienen de 1.0.60 o anteriores (al cargar el escritorio, el cron o WP-CLI, sin ninguna consulta cuando no queda nada en claro, y después de cada restauración) pasan por el almacén, que comprueba cada valor antes de retirar el texto en claro; el navegador nunca recibe un valor. El almacén sí entra en `database.sql`: restaurar deja los destinos funcionando con las credenciales del respaldo, que mandan sobre las que tenía el servidor. En otro servidor (otras salts) se vuelven a cifrar con las de éste usando las del `wp-config.php` que va dentro del propio respaldo (sólo `define()` con literales de cadena, leídos con el tokenizador de PHP; el archivo nunca se ejecuta ni se incluye). Si no se pueden leer (`getenv()`, otro archivo) o cambiaron las salts de este servidor, el destino queda en «vuelve a escribir la contraseña / conéctalo de nuevo» en la pantalla, en `status` de REST y WP-CLI, en el aviso de la restauración y en el error permanente del trabajo con destino remoto; nunca se cae al texto en claro. Sin `AUTH_KEY` y `SECURE_AUTH_KEY` propias se guardan como hasta ahora, con aviso. Una restauración que muere entre el SQL y el nuevo cifrado lo termina con el diario. El dato adicional ata cada secreto también a su conexión (host, puerto, usuario y huella SSH; Client ID), así que una escritura directa que cambie el host no lo desvía. Ningún respaldo lleva un secreto en claro si hay salts propias (se migra antes del volcado y cada fila de ajustes sale sin ellos, también la de un subsitio); los ajustes van con autoload, así que la comprobación al cargar no cuesta consultas y detecta lo que 1.0.60 reescriba tras volver desde `rollback/`. En una red sólo el sitio principal lee y escribe el almacén; un sitio convertido en red lo adopta; «Olvidar las credenciales guardadas» retira las de un destino que ya no se usa; un almacén con datos que no son texto pide volver a escribir sin romper nada, y un `wp-config.php` de más de 64 KiB no se analiza. Cada secreto (del formulario o del retorno de OAuth) se guarda exactamente como se escribió, sin las barras de `$_POST` y sin saneado ni recorte: antes, comillas y barras invertidas ganaban barras, y `<…>`, `%xx` y los espacios al borde se perdían; sólo se rechazan, con un mensaje que no repite el valor y sin cambiar nada, NUL, los caracteres de control y más de 8192 bytes. | 1.0.61 | `backups-credentials.php`, `backups-credentials-surfaces.php`, `backups-credentials-nokeys.php`, `backups-credentials-exact.php`, `backups-credentials-hardening.php` y, en Playground, S9 de `tests/playground/backups-security.sh` |
| **Restaurar en otro dominio, otra ruta y otro prefijo (E1).** Un respaldo de otra dirección, de otra carpeta del servidor o con otro prefijo de tablas ya no se rechaza ni deja el sitio apuntando al origen: por defecto el sitio conserva **sus** direcciones (las de este servidor) y las del respaldo se reescriben al restaurar; «Conservar las direcciones del respaldo» es una opción explícita (pantalla, `addresses=backup` en REST, `--addresses=backup` en WP-CLI). El motor, `Digitalisimo_Backups_Relocate`, cambia URL y rutas del servidor dentro de PHP serializado sin llamar nunca a `unserialize()` (recorre el formato y recalcula cada longitud en bytes, también anidado y con multibyte; ningún objeto del respaldo se instancia), JSON con barras escapadas (el `_elementor_data` de Elements), URL codificadas, `http` y `https`, `//dominio`, con y sin `www`; un dominio sólo coincide completo (`origen.com` no toca `origen.com.mx`, `miorigen.com` ni un correo). Se aplica fila por fila durante la importación, con memoria constante, sobre los literales que comprobó el guardián SQL. Otro prefijo renombra las tablas (también en `REFERENCES`) y las claves que lo llevan (`{prefijo}user_roles`, `{prefijo}capabilities`…), y los permisos de otra instalación que traiga el respaldo no se aplican; las tablas sin el prefijo del origen no se restauran y las de este servidor no se tocan. En una red (subdominios o subdirectorios) cambian `site`, `sitemeta`, `blogs` y las direcciones de cada sitio, durante la importación (cortar el proceso después del SQL deja la red accesible); una red de otro tipo o un `wp-config.php` que declara otro dominio se rechazan antes de tocar nada diciendo qué cambiar. Después se vacían transitorios, reglas de reescritura, el CSS generado de Elements/Elementor, las cachés de página y la caché de objetos, se pide a LiteSpeed que purgue la suya, y al extraer `.htaccess` y `.user.ini` toman las rutas de este servidor (un `auto_prepend_file` que aquí no existe queda comentado); una comprobación final cuenta lo que quedó del origen (0 salvo `guid` y lo declarado). La pantalla dice «Origen → este sitio» antes de confirmar y guarda el informe por tabla. Ver «Restaurar en otro dominio». | @NEXT_BACKUPS@ | `backups-relocate.php`, `backups-relocate-interrupted.php` y, en Playground, `tests/playground/backups-relocate.sh` |
| **Restauración por pasos (H1–H4 del ensayo real).** La restauración es un trabajo del motor de trabajos (cola, fases con punto de control, arrendamiento, latido y reanudación), como un respaldo: verificación, copia previa, mantenimiento, base de datos, archivos, cambio, direcciones y limpieza (ver «Restauración por pasos»). El SQL se importa por tramos a tablas temporales con el prefijo del sitio (`<prefijo>dgbkr_<marca>_<n>`) y las tablas pasan de viejas a nuevas de una vez (MySQL: un solo `RENAME TABLE`, atómico; el SQLite de WordPress: una transacción); hasta ese cambio el sitio se sirve de sus tablas, intactas. Los archivos que cambian se preparan en un área de trabajo y se mueven con `rename()` (cada carpeta de código —wp-admin, wp-includes, cada plugin y tema, mu-plugins— de una vez; este plugin y los `.htaccess`, al final). Tras una muerte (corte de la petición, recarga del servidor web, memoria, `kill -9`), la operación siguiente (pantalla, WP-Cron, `POST /jobs/tick` o WP-CLI) la reanuda desde el último punto de control; si falla después del cambio, encola la vuelta a la copia previa, que la restaura entera con el mismo motor. La pantalla y `status` avisan mientras no termina, y el modo de mantenimiento deja pasar sólo los ticks con el testigo de la restauración. Lo que no estaba en el respaldo no sobrevive dentro de las raíces que gestiona (núcleo, plugins, temas, mu-plugins y medios): queda en la copia previa y en el informe. El manifiesto se lee por partes a un índice en disco y cada fase avanza por tramos con el presupuesto de T2: un sitio de 40 008 entradas se verifica y se restaura con `memory_limit=64M` y 38 MB ya ocupados. Las tablas de otra aplicación que traiga el respaldo no se tocan; la red y las direcciones se fijan en las tablas temporales antes del cambio; las sentencias más grandes pasan por la reubicación antes de la copia previa; `.user.ini`, `php.ini` y `.htaccess` con rutas o el dominio del origen se corrigen, y un `auto_prepend_file` que aquí no existiría se desactiva. | @NEXT_BACKUPS@ | `backups-restore-steps.php`, `backups-restore-memory.php` y, en Playground, `tests/playground/backups-restore.sh` |

Ensayos en WordPress Playground 7.1.2 (SQLite): respaldo y restauración de un sitio (ZIP de 37 MB, 3792 archivos) y
de una red con un segundo sitio; en ambos un cambio posterior al respaldo volvió a su valor anterior y el historial
conservó el ZIP original y la copia previa. No sustituyen ensayos con MySQL, Multisite en un servidor convencional,
migración a otro servidor ni destinos remotos reales.

Compatibilidad de los trabajos (1.0.59), probada con el código real de 1.0.58 tomado de su ZIP publicado: sus
respaldos se verifican y restauran con el motor nuevo, y los del motor nuevo (manifiesto v3 con las mismas claves
obligatorias más `job_id`, `packer`, `database_snapshot_at`, `files_started_at` y `files_completed_at`) pasan su
verificador y su restauración, así que volver a 1.0.58 sigue restaurando. Las filas antiguas del historial se leen igual
(las nuevas añaden `job`, `transfer`, `transfer_destination` y `transfer_error`); `digitalisimo_backups_full` y
`digitalisimo_backups_policy` conservan nombre y argumentos y ahora encolan; el AJAX de 1.0.58 (una pestaña abierta
durante la actualización) sigue funcionando con su forma de respuesta, y la opción `digitalisimo_backups_manual_job_<uid>`
ya no bloquea y se retira al abrir la pantalla. La muerte real con `SIGKILL` se ensayó con dos procesos de php-wasm
(trabajador bloqueado dentro de la exportación, `kill -9` y reanudación) y la CI la repite con PHP del sistema; igual se
ensayó un error fatal real dentro de la instantánea (memoria agotada), que el apagado de PHP anota y devuelve a la cola
antes de que pase el detector.

Ensayo de seguridad (1.0.59, `tests/playground/backups-security.sh`) en WordPress 7.1.4 sobre Playground (SQLite,
PHP 8.5 sin libsodium nativo, así que con el sodium_compat de WordPress), por los handlers `admin-post` reales con
nonce y capacidad. En un sitio individual, la frase (con comillas, barra, `<` y `&`) se guardó sin alterarse, un nonce
falso no cambió nada y ni la pestaña ni la redirección la devolvieron; un respaldo firmado de 36 MB se restauró y
revirtió un cambio con el llavero conservado y fuera de `database.sql`; una copia con un byte del manifiesto cambiado
se rechazó sin copia previa; un archivo suelto sin firma pidió `RESTAURAR SIN FIRMA` y «sólo firmados» lo bloqueó; con
la envoltura del llavero de otras claves de `wp-config.php`, el respaldo cifrado falló cerrado sin dejar archivos hasta
volver a escribir la frase. Un respaldo cifrado de 36 MB tardó unos 11 minutos en crearse, cifrarse y comprobarse, sin ZIP en claro ni restos y sin nombres de archivo legibles; en «otro servidor» (llavero vacío) la restauración sin frase y con una frase equivocada se rechazó sin cambios, y con la frase restauró el sitio en unos 6 minutos. En una red, el llavero quedó en `sitemeta`, la restauración lo conservó y un subsitio
no pudo cambiar la seguridad. Con `DIGITALISIMO_BACKUPS_KEY`, el cifrado fue obligatorio, la pestaña mostró la huella
y nunca el valor, rechazó una frase y la copia previa quedó en claro y firmada con la constante. Tras la revisión de
1.0.59 (guardián SQL, UpdraftPlus, huérfanos y diario de la restauración) se repitieron los tres modos: 247 s, todo correcto.

Ensayo conjunto de trabajos y seguridad (1.0.59, `tests/playground/backups-security.sh` con `ENCRYPT=1`: 1273 s,
todo correcto), con cada respaldo creado por el handler `admin-post` real de «Crear respaldo», que lo encola, y la cola
avanzada por ticks como WP-Cron. En un sitio individual, el respaldo firmado de 36 MB se creó en 17 s y se restauró; el
respaldo cifrado de 36 MB recorrió las fases del trabajo (firma en «pack», comprobación y sellado del DGBK en «verify»,
unos 11 minutos con sodium_compat sin perder el arrendamiento, y publicación con `rename()`) sin ZIP en claro ni restos,
y en «otro servidor» (llavero vacío) se restauró escribiendo la frase en unos 6 minutos. S8: un respaldo creado y
registrado por la clase de 1.0.58 (el código publicado antes de esta entrega, tomado de su ZIP) se restauró con el código
actual, revirtió el cambio y conservó el llavero, en el sitio individual y en una red con un subsitio. En la red, el
llavero quedó en `sitemeta` y un subsitio no pudo cambiar la seguridad; con `DIGITALISIMO_BACKUPS_KEY`, la copia previa
quedó en claro y firmada con la constante. Con el código unido se repitieron además, con procesos de php-wasm (PHP 8.2),
la muerte real con `SIGKILL` y el error fatal de `backups-job-kill.php`.

Ensayo de las credenciales de los destinos (1.0.61, S9 de `tests/playground/backups-security.sh`: 471 s junto con
S1, S3, S4, S6, S7 y S8) en WordPress 7.1.4 sobre Playground (SQLite, PHP 8.5 con el sodium_compat de WordPress), por los
handlers `admin-post` reales. En un sitio individual y en una red (almacén en `sitemeta`): las cinco credenciales
guardadas por «Servidores» no aparecen en claro en ninguna fila de `options` ni de `sitemeta`, y `status` las da por
cifradas con los tres destinos conectados; el `database.sql` de un respaldo de 36 MB lleva el almacén cifrado y ningún
secreto; tras cambiar la contraseña, restaurar devuelve las del respaldo, cifradas con las salts del servidor, y Google
renueva el acceso con ellas (la petición se intercepta; Playground no tiene SSH2); y un respaldo de «otro servidor»
(su `wp-config.php` con otras salts y su almacén cifrado con ellas) se restaura con las credenciales vueltas a cifrar
con las salts de éste, sin tocar su `wp-config.php`. Los ajustes quedan con autoload; reescritos en claro y sin autoload
(como lo haría 1.0.60 tras volver desde `rollback/`), la siguiente carga del escritorio los cifra y les devuelve el
autoload, también con `wp_set_option_autoload()` cuando no queda nada en claro. S8 identifica ahora el respaldo de la clase anterior por lo que
sólo escribe el motor de trabajos (`job_id`, `packer`): esa clase escribe en `plugin_version` la versión del plugin
cargado, así que compararla con 1.0.58 fallaba desde que el repositorio pasó de esa versión.

**Pendiente:**

| Bloque | Brecha principal |
| --- | --- |
| Trabajos | Hecho en 1.0.59 con reanudación por fase (T1). Falta **T2**: pasos cortos dentro de la exportación y el empaquetado (escritor ZIP propio de sólo añadir, inventario por directorios y verificación por tramos), porque hoy esas dos fases son un paso largo cada una y una interrupción las repite enteras; con ella, la reducción del lote tras un tiempo agotado. Falta **T3**: transferencias reanudables por fragmentos (SFTP por desplazamiento, sesiones de Google y OneDrive guardadas de forma privada) y retirar los temporales SFTP que deja un proceso muerto; hoy una cancelación pedida durante una subida SFTP llega cuando la copia ya terminó, y el respaldo se completa. Desde 1.0.60, REST y WP-CLI consultan, hacen avanzar y cancelan la misma cola (`wp digitalisimo-backups jobs cancel`, `POST /jobs/<id>/cancel`). Ensayo real pendiente: `kill -9` y `max_execution_time` cortos en un hosting con MySQL (en Playground y con muertes simuladas ya pasa la restauración por pasos, @NEXT_BACKUPS@). Desde @NEXT_BACKUPS@ la restauración es un trabajo por pasos que se reanuda o vuelve sola a la copia previa (ver «Restauración por pasos»); la copia previa usa las fases del respaldo, que hasta T2 son un paso largo cada una. Límite declarado sin consecuencia para los datos: con el almacén compartido entre varios servidores sin `flock` común y PHP anterior a 8 (sin el aviso de avance de `ZipArchive::close()`), un empaquetado de más de 30 min sin latido puede tomarlo otro servidor; se pierde trabajo y puede agotar intentos, pero nunca se presenta un parcial como completo. |
| Restauración | Desde @NEXT_BACKUPS@, trabajo por pasos con tablas temporales y un cambio atómico, archivos preparados y movidos, mantenimiento, reanudación y vuelta automática a la copia previa (ver «Restauración por pasos»). Falta el ensayo con MySQL real y el corte del laboratorio (Webuzo con OpenLiteSpeed, 30 s, 64 MB y Cloudflare), la prueba extremo a extremo a otro servidor (E2), la migración en un clic, una vuelta atrás sin rehacer la copia previa (las tablas y carpetas viejas ya se conservan hasta la limpieza) y restaurar UpdraftPlus por pasos. |
| Seguridad | Argon2id opcional para la frase. Botón «Inspeccionar» con el informe de un archivo antes de restaurarlo (hoy la inspección completa ocurre al restaurar, antes de la copia previa). Ensayo del cifrado en un hosting real con libsodium nativo y MySQL. Sin la extensión, sodium_compat es lento: en php-wasm, unos 11 s por MiB (36 MB tardaron unos 11 minutos en cifrarse y comprobarse y unos 6 en descifrarse y restaurarse); la pestaña mide la velocidad del servidor y estima el tiempo del último respaldo. |
| Restauración (UpdraftPlus) | La ruta de UpdraftPlus ya valida todo antes de cambiar nada, pero aún no crea copia previa ni revierte si falla a medias. Su restauración y el diario de una restauración interrumpida sólo se han probado en php-wasm, sobre la base de datos simulada; falta ensayarlos en WordPress real. |
| Automatización | Respaldo previo a actualizaciones, incrementales con borrados y cadena íntegra, retención que preserve dependencias y varios destinos por trabajo. |
| Escala | Bases y archivos grandes reales, BLOBs, objetos SQL especiales y consistencia entre archivos y base de datos (WooCommerce). |
| Cobertura | Hecho en 1.0.60: REST y WP-CLI sobre los casos de uso de la pantalla, con la cola de «Trabajos» (crear y ejecutar encolan; WP-CLI hace avanzar la cola hasta terminar; listar, consultar, avanzar y cancelar trabajos) y la confianza y el cifrado de «Seguridad» (`.dgbk`, `RESTAURAR SIN FIRMA`, la frase sólo en el cuerpo de REST o por `--secret-file` o STDIN en WP-CLI, y un error claro sin la clave), y rutas externas que funcionan (`wp-config.php`) o fallan antes de exportar con un mensaje claro (carpetas). Falta respaldar de verdad wp-content, plugins, medios o temas fuera de `ABSPATH`, que necesita una raíz nueva del ZIP aceptada por el validador estricto de «Seguridad» y por el empaquetador de «Trabajos». Faltan en REST y WP-CLI bloquear, eliminar y descargar, y los ajustes de «Seguridad» (frase, cifrado y «sólo firmados»), que siguen sólo en la pantalla; también que la pantalla avise al abrirse de una estructura no respaldable y repetir en un hosting con MySQL el ensayo de REST y WP-CLI que ya pasa en Playground. |
| Ensayos reales | MySQL, Multisite convencional, almacén privado en hosting real, servidor SFTP real y cuentas reales de Google Drive y OneDrive. |

Siguiente bloque: cerrar seguridad y restauración en entornos reales (MySQL, Multisite,
almacén privado y SFTP en un hosting real) y preparar la restauración transaccional; después, rutas externas y
destinos remotos.

## Arquitectura

| Componente | Función |
| --- | --- |
| `digitalisimo-backups.php` | Registra el módulo, el menú compartido y el actualizador propio. |
| `includes/class-backups.php` | Interfaz, ajustes, cron, ZIP/SQL, destinos, historial, retención y restauración; expone al motor de trabajos sus piezas (`job_preflight`, `job_export_database`, `job_build_package`, `job_verify_package`, `job_seal_package`, `job_register`, `job_transfer`, `job_prune`, `history_update`). Carga las clases de `includes/security/`. |
| `includes/class-backups-job-store.php` | `Digitalisimo_Backups_Job_Store`: estado de cada trabajo en `<almacén>/jobs/<uuid>/` (escritura atómica, `.jobs.lock`, lectura validada, registro de eventos acotado, marca de cancelación y poda). |
| `includes/class-backups-jobs.php` | `Digitalisimo_Backups_Jobs`: cola, máquina de estados, arrendamiento, latido, detector, reintentos, caducidad, cancelación, disparadores (tick único, watchdog horario, AJAX y admin-post) y vista pública. API `enqueue`, `tick`, `cancel`, `get`, `all` y `views`, que usan también REST y WP-CLI por `Digitalisimo_Backups_Service`. |
| `includes/class-backups-backup-job.php` | `Digitalisimo_Backups_Backup_Job`: fases del respaldo con su punto de control y `run_inline()` para la copia previa de una restauración. |
| `includes/security/class-sodium.php` | libsodium nativo o el `sodium_compat` de `wp-includes`; qué funciones faltan. |
| `includes/security/class-keys.php` | Constante, PBKDF2, huella y subclaves (BLAKE2b con separación de dominio), llavero envuelto, clave y descriptor por huella, unión tras restaurar. |
| `includes/security/class-credentials.php` | `Digitalisimo_Backups_Credentials`: almacén cifrado de las credenciales de los destinos (`digitalisimo_backups_credentials`), clave desde AUTH_KEY y SECURE_AUTH_KEY (también desde valores dados), lectura de las salts literales del `wp-config.php` de un respaldo (hasta 64 KiB), contexto de conexión del dato adicional, nuevo cifrado tras restaurar, claves del origen para el diario, «Olvidar», adopción al convertir en red y estado público sin valores. |
| `includes/security/class-manifest-auth.php` | Bloque `auth` del manifiesto, comentario `DIGITALISIMO-AUTH/1` y estados de la firma. |
| `includes/security/class-encrypted-file.php` | Contenedor DGBK v1: cabecera, cifrado y descifrado por bloques con memoria constante. |
| `includes/security/class-archive-inspector.php` | Reglas de entradas, esquema estricto del manifiesto v3 y espacio en disco. |
| `includes/security/class-sql-guard.php` | Divisor de sentencias por fragmentos y lista blanca de los dialectos `exporter` y `mysqldump`. Para la reubicación, `rename_tables()` (cambia el identificador exacto que leyó, también en `REFERENCES`) y `on_insert()` (posición de cada literal de un INSERT comprobado). |
| `includes/security/class-security.php` | Costuras `seal()` (la llama la fase «verify» de un trabajo cifrado) y `open()`; `job_settings()` y `job_plan()`, el cifrado y la huella que fija la especificación de un trabajo y la clave que cada fase abre por esa huella; nivel de confianza y confirmación, pestaña «Seguridad» y sus acciones `admin-post`. |
| `assets/manual-progress.js` | Encola, hace avanzar la cola con la pantalla abierta, pinta su estado como texto y cancela. |
| `includes/class-digitalisimo-core.php` | Integra el menú DIGITALÍSIMO sin exigir otro plugin. |
| `includes/class-digitalisimo-updater.php` | Actualizador por `Update URI` e índice de la Release, independiente de los demás plugins. |
| `includes/trait-backups-service.php` | `Digitalisimo_Backups_Service`: casos de uso compartidos por la pantalla, REST y WP-CLI (estado, historial, crear y ejecutar en cola, trabajos, verificar y restaurar con «Seguridad», y automatizaciones), con el alcance de sitio o red y el bloqueo del almacén. |
| `includes/class-backups-rest.php` | `Digitalisimo_Backups_Rest`: rutas `digitalisimo-backups/v1` con la capability de la pantalla; la frase de un respaldo, sólo en el cuerpo de la petición. |
| `includes/class-backups-cli.php` | `wp digitalisimo-backups`, `wp digitalisimo-backups automation` y `wp digitalisimo-backups jobs`; hace avanzar la cola en su proceso y lee la frase de un archivo o de STDIN. |
| `includes/class-backups-layout.php` | `Digitalisimo_Backups_Layout`: carpetas de WordPress que un respaldo completo debe contener; rechaza las externas o enlazadas antes de exportar. |
| `includes/class-backups-restore-job.php` | `Digitalisimo_Backups_Restore_Job`: fases de la restauración por pasos, etiquetas, tramos de la barra y qué fases se pueden cancelar; las fases las ejecuta el motor de `includes/restore/trait-backups-restore-engine.php`. |
| `includes/restore/trait-backups-restore-engine.php` | `Digitalisimo_Backups_Restore_Engine` (dentro de `Digitalisimo_Backups`): encolar y conducir la restauración, cada fase con su punto de control, tablas temporales y cambio, preparación y movimiento de archivos, archivos ajenos, vuelta a la copia previa y estado público (`restore_status()`). |
| `includes/restore/class-manifest-stream.php`, `class-manifest-index.php` | Lectura de `manifest.json` por partes con la semántica de `json_decode()` y su índice en disco por posición del ZIP (memoria constante). La usan la firma, el esquema estricto, la verificación del contenido y la restauración. |
| `includes/restore/class-restore-maintenance.php` | `.maintenance` de la restauración por pasos: deja pasar sólo los ticks con el testigo de la restauración; renovado en cada tick y sin caducar desde el cambio. |
| `includes/steps/class-step-budget.php` | Presupuesto de cada tramo (copiado tal cual de T2): plazo del tick, límite real de la petición, escala adaptativa tras una muerte y cupo de memoria. |
| `includes/class-backups-relocate.php` | `Digitalisimo_Backups_Relocate`: reescritor de valores (URL y rutas en serializado, JSON y URL codificadas, sin `unserialize()`), prefijo de tablas y claves, plan de origen a destino (sitio o red) con sus comprobaciones previas, aplicación en la importación (`attach()`) o sobre una base ya importada (`rewrite_tables()`), red, direcciones, cachés y comprobación final (`finish()`). `Digitalisimo_Backups_Relocate_Probe` lee del SQL en seco las direcciones y la red del origen. |
| `assets/backups-admin.css` | Presentación propia sobre el diseño compartido `digitalisimo-ui/`. |

La configuración y el historial viven en opciones del sitio o de la red; el estado de los trabajos, en el almacén
privado. Hay acciones `admin-post`, eventos WP-Cron, creación manual y políticas automáticas; destinos local, SFTP,
Google Drive y OneDrive; e importación de ZIP propio y de conjuntos Updraft. Los formatos heredados (copia de base de
datos, copia por sitio, Updraft) siguen inventariados, pero no satisfacen el requisito de respaldo completo y no se
presentan como verificados.

Riesgos conocidos del flujo actual:

1. Desde 1.0.59 todo respaldo es un trabajo con estado persistente, pero la exportación de la base de datos y el
   empaquetado siguen siendo un paso largo cada uno (T2): en un hosting que corta las peticiones largas, una base o un
   sitio muy grandes pueden agotar sus intentos. El fallo es limpio (nada intermedio queda registrado) y el cron del
   sistema lo evita.
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

## REST y WP-CLI

Las dos superficies llaman a los mismos casos de uso que la pantalla (`Digitalisimo_Backups_Service`), sin repetir pasos
del motor: alcance, comprobaciones previas, la cola de «Trabajos», la apertura del paquete de «Seguridad» y la
restauración con copia previa. Crear y ejecutar una automatización encolan un trabajo, como los botones de la pantalla;
restaurar y verificar trabajan en la misma petición.

| REST (`/wp-json/digitalisimo-backups/v1`) | WP-CLI | Qué hace |
| --- | --- | --- |
| `GET /status` | `wp digitalisimo-backups status [--format=json]` | Si la instalación puede respaldarse completa (y por qué no), operación y trabajos en curso, ubicación de `wp-config.php`, destinos, cifrado, «sólo firmados», origen de la clave (`wp-config`, `passphrase` o `none`), credenciales de los destinos (`credentials`: `none`, `encrypted`, `pending`, `unprotected` o `needs_reentry`, y `credentials_notice` con qué hacer, nunca un valor), último respaldo y automatizaciones. `restoring` y `restore` (`state`: `none`, `running`, `interrupted`, `rollback`, `failed`, `failed_midway`, `rolled_back` o `cancelled`, con fase, avance, copia previa y `notice`) dicen qué pasa con una restauración sin terminar o que no terminó bien. |
| `GET /backups` | `wp digitalisimo-backups list [--fields=…] [--format=…]` | Historial, del más reciente al más antiguo: tamaño, SHA-256, destino, alcance, contenedor (`zip` o `dgbk`), firma, huella corta de la clave, si falta la clave (`needs_secret`), bloqueo y si es restaurable aquí. |
| `POST /backups` (`destination`, `network`) | `wp digitalisimo-backups create [--destination=…] [--network] [--async] [--porcelain]` | Encola el respaldo completo de la instalación o de la red. REST responde 202 con el trabajo; WP-CLI lo hace avanzar hasta que queda verificado (y copiado, con destino remoto) o, con `--async`, sólo lo encola. |
| `GET\|POST /backups/<archivo>/verify` (`secret`, `addresses`) | `wp digitalisimo-backups verify <archivo> [--secret-file=…\|--secret-stdin] [--addresses=current\|backup]` | Sin tocar el sitio, las mismas comprobaciones del paquete que la restauración: SHA-256 del historial, firma o registro, descifrado completo de un `.dgbk`, entradas, CRC y SHA-256 de cada una contra el manifiesto, manifiesto v3 estricto y el SQL en seco. Informa la confianza, la confirmación que pedirá, si las condiciones de esta instalación lo admiten (`problems` dice qué cambiar) y, en `relocation`, «Origen → este sitio» y qué se reescribiría al restaurarlo (direcciones, rutas del servidor, prefijo, sitios de la red). |
| `POST /backups/<archivo>/restore` (`confirm`, `network`, `secret`, `adopt_secret`, `addresses`) | `wp digitalisimo-backups restore <archivo> --confirm=… [--network] [--secret-file=…\|--secret-stdin] [--adopt-secret] [--addresses=current\|backup]` | Restauración por pasos (ver «Restauración por pasos») con la copia previa bloqueada. REST avanza lo que cabe en la petición y responde 202 con `restoring`, el trabajo (`job`) y `maintenance_token` (el testigo que deja pasar sus ticks durante el mantenimiento; también llega como cookie) si sigue, o 200 con el resultado si terminó; `POST /jobs/tick` o la pantalla la terminan. WP-CLI la hace avanzar hasta terminar. `RESTAURAR` para un archivo firmado o registrado aquí; `RESTAURAR SIN FIRMA` para uno sin firma ni registro. Con `adopt_secret` o `--adopt-secret`, la frase escrita firma y cifra los respaldos nuevos. Un respaldo de otra dirección, carpeta o prefijo se reubica: `addresses` `current` (por defecto, el sitio conserva sus direcciones) o `backup` (conserva las del respaldo); la respuesta lleva `relocation` con lo reescrito por tabla, las cachés vaciadas y la comprobación final, y el aviso lo resume. Otro valor responde 400 (`addresses`). |
| `GET /automations` | `wp digitalisimo-backups automation list` | Automatizaciones y su próxima ejecución. |
| `POST /automations/<id>/run` (`network`) | `wp digitalisimo-backups automation run <id> [--network] [--async] [--porcelain]` | «Ejecutar ahora»: encola la automatización (202), como `create`. |
| `GET /jobs` (`limit`) · `GET /jobs/<id>` | `wp digitalisimo-backups jobs list [--limit=…] [--fields=…] [--format=…]` | Trabajos sin terminar y los últimos terminados, con la vista de la tarjeta «Trabajos» (estado, fase, avance, reintento, archivo o error) más destino, automatización y si se cifra. |
| `POST /jobs/<id>/cancel` | `wp digitalisimo-backups jobs cancel <id>` | Como «Cancelar»: uno en cola termina ya; uno en curso se detiene en su siguiente punto de control; uno ya verificado y registrado no se cancela (409). |
| `POST /jobs/tick` | `wp digitalisimo-backups jobs run [--quiet]` | Hace avanzar la cola: REST da un paso con el presupuesto de una petición web, como el navegador con la pantalla abierta; WP-CLI sigue sin límite de tiempo hasta que no queda trabajo listo (sirve para el cron del sistema en un hosting que corta las peticiones largas). |

- **Autorización.** REST usa la autenticación de WordPress: con cookie exige el nonce `wp_rest` en `X-WP-Nonce`, y
  también sirven las contraseñas de aplicación. Cada ruta pide la capability de la pantalla (`manage_network_options`
  en una red, `manage_options` en un sitio individual): sin sesión responde 401 y sin permiso, 403. WP-CLI corre con la
  cuenta del sistema, que ya controla los archivos y `wp-config.php`; como cualquier comando que escribe archivos, debe
  ser la del servidor web: con `--allow-root`, los ZIP y lo restaurado quedarían de root y WordPress no podría
  reemplazarlos.
- **Contexto.** En una red, el historial, las automatizaciones y la cola son del sitio principal: un subsitio (otra URL
  de REST o `--url`) responde 400 y su `status` lo explica. Crear, restaurar y ejecutar abarcan todos los sitios y piden
  `network=true` o `--network`; en un sitio individual ese alcance se rechaza. Cancelar o hacer avanzar un trabajo no
  abarca más que la cola, así que no lo pide. Con varias redes en la misma base de datos, el respaldo las abarca todas:
  sólo responde el sitio principal de la red principal, porque administrar una red secundaria no autoriza a copiar ni a
  reemplazar las demás, y el motor tampoco exporta desde ella (vista con avance, cron y automatizaciones).
- **Cola.** Un respaldo manual o una automatización nunca tienen dos trabajos sin terminar: el segundo responde 409 con
  el identificador del primero (`data.job`). El trabajo anota su origen (`rest` o `cli`) y lo continúan el navegador,
  WP-Cron, `POST /jobs/tick` o WP-CLI; con otra operación en el almacén, un paso responde `busy` y WP-CLI espera y
  vuelve a probar. Restaurar se niega con un trabajo pendiente o en curso (409): hay que cancelarlo o esperar.
- **Frase o clave.** Sólo hace falta para un respaldo cifrado o firmado con una clave que este sitio no tiene, y se usa
  tal cual. En REST va en el cuerpo de la petición (JSON o formulario): en la cadena de consulta quedaría en los
  registros del servidor y del proxy, así que se rechaza (400, `secret_in_url`) sin usarla. En WP-CLI se lee de un
  archivo (`--secret-file`, hasta 64 KiB, sin el salto de línea final) o de la entrada estándar (`--secret-stdin`, sin
  eco en una terminal); `--secret=<valor>` es un error, porque quedaría en la lista de procesos y en el historial del
  shell. Un `.dgbk` cuya clave no tiene el sitio responde, sin la frase y antes de tomar el bloqueo, 400
  `secret_required` con la huella corta de la clave (`data.key`) y dónde va la frase en esa superficie.
- **Respuestas.** Los resúmenes (estado, historial, trabajos, verificación y automatizaciones) no llevan la ruta del
  almacén ni identificadores remotos: de una copia remota sólo se nombra el proveedor. Ninguna respuesta ni salida lleva
  la frase, la clave, su envoltura ni la huella completa (de una clave sólo se nombran sus 8 primeros caracteres),
  tampoco el error de una frase equivocada. Un aviso que impide respaldar nombra la carpeta que hay que corregir. Un
  archivo se busca sólo dentro del almacén: un nombre con ruta o un enlace simbólico que salga de él responde 404.
- **Errores.** Responden con el mensaje del motor y un código estable, `digitalisimo_backups_<motivo>` (los que vienen
  de una automatización conservan los suyos, `digitalisimo_backup_*`):
  - 400: alcance (`scope`, `main_site`), destino (con credenciales que no se abren, el mensaje dice qué volver a escribir), automatización parcial, confirmación (`confirm`, `confirm_unsigned`),
    `secret_required`, `secret_in_url` o identificador de trabajo no válido.
  - 403: sin permiso, o un archivo sin firma con «Restaurar sólo respaldos firmados» (`signed_only`).
  - 404: archivo, automatización o trabajo inexistente.
  - 409: otra operación en curso o un trabajo activo al restaurar (`busy`), respaldo ya pendiente (`duplicate`),
    automatización desactivada o trabajo que ya no se puede cancelar.
  - 422: instalación no respaldable (`not_ready`; también al restaurar, porque la copia previa es un respaldo completo,
    y con el cifrado activo y sin clave), paquete que no supera las comprobaciones o frase que no corresponde
    (`invalid`), y restauración rechazada antes de tocar el sitio (`restore_rejected`).
  - 500: fallo del motor o del almacén, y restauración que falla después de la copia previa (`restore_failed`, con lo
    que se recuperó).

  WP-CLI termina con código 1 y el mismo mensaje.
- **Almacén.** Sin `DOCUMENT_ROOT` (WP-CLI y cron), la carpeta privada sale de `DIGITALISIMO_BACKUPS_STORAGE_DIR` o de la
  ruta que registró la pantalla (ver «Almacenamiento local»).

Ensayo en WordPress Playground (`tests/playground/backups-coverage.sh`), sobre el motor de 1.0.59: WordPress 7.1.4,
PHP 8.5 y SQLite, en un sitio individual y en una red con un subsitio, todo por HTTP real. Pasaron 99 comprobaciones
(45 en el sitio individual y 54 en la red, 395 s). Con E1 (@NEXT_BACKUPS@) se repitió con `addresses` en `verify` y `restore` (REST con la validación de WordPress, WP-CLI
con la suya): 109 comprobaciones, 320 s, todo correcto. El ensayo corta el loopback de WP-Cron (un mu-plugin de `setup.php`)
para que la cola sólo avance cuando él lo pide; sin ese corte, en el sitio individual WP-Cron terminó por su cuenta el
trabajo encolado por REST y el de `create --async` antes de que el ensayo los cancelara.

| Qué | Resultado |
| --- | --- |
| Autenticación | Sin sesión, 401. Cookie sin nonce, 401 (WordPress la trata como anónima). Nonce falso, 403. Cookie con nonce, 200. |
| Contraseñas de aplicación | De admin, 200. De una editora, 403. En la red, de un administrador del sitio principal que no administra la red, 403. |
| Cola por REST | `POST /backups` responde 202 con el trabajo y un segundo, 409 con el identificador del primero. `GET /jobs` lo lista y `POST /jobs/tick` lo termina (ZIP de 39 MB en 21 a 25 s). `POST /jobs/<id>/cancel` cancela uno en cola, y repetirlo responde 409. |
| Respaldo y restauración por REST | El respaldo del trabajo queda en el historial, restaurable, y `verify` lo da por íntegro y registrado sin nombrar rutas del servidor. La frase en la URL se rechaza (400) sin repetirla. Un cambio posterior vuelve atrás al restaurar (25 a 28 s), con su copia previa. |
| «Seguridad» | Con un `.dgbk` pequeño cifrado con una frase que el sitio no tiene: `verify` y `restore` sin ella responden 400 `secret_required` con la huella corta y dónde va la frase, y WP-CLI nombra `--secret-file` y `--secret-stdin`. Una frase equivocada da 422 sin repetirla. Con la buena, en el cuerpo o en `--secret-file`, lo descifrado no es un ZIP: la validación lo rechaza (422) y `.staging` queda vacía. Ninguna respuesta lleva la frase ni la huella completa. |
| Red por REST | Sin `network=true`, 400. El subsitio responde 400 y su estado explica por qué. |
| WP-CLI real | Sinopsis tomadas del PHPDoc. `restore` sin `--confirm` no corre. `create --porcelain` hace avanzar la cola hasta terminar (19 a 20 s) y su respaldo aparece primero en el historial de REST. `create --async` deja el trabajo en cola, `jobs list` lo muestra primero y `jobs cancel` lo cancela. En la red, sin `--network` o desde el subsitio, falla con el motivo. |
| Medios de un subsitio | Con `upload_path` fuera de `ABSPATH`, el estado nombra el sitio, el ajuste y la carpeta que calcula WordPress, y `POST /backups` responde 422 sin exportar. Con su valor original, la red vuelve a estar lista. |

## Restaurar en otro dominio

Desde @NEXT_BACKUPS@ (E1). Un respaldo completo se puede restaurar en otro servidor, otro dominio, otra ruta y con otro
prefijo de tablas, en un sitio individual o en una red. El motor es `Digitalisimo_Backups_Relocate`
(`includes/class-backups-relocate.php`), con su contrato en el PHPDoc; lo reutilizarán la migración en un clic y
«extraer un sitio de la red».

**Cuándo se aplica.** Al restaurar, se compara el origen del respaldo (su manifiesto y, sobre todo, lo que dice su
propio SQL: `home` y `siteurl` de cada sitio, `blogs`, `site` y `subdomain_install`, leídos en la pasada en seco del
guardián) con esta instalación. Si coinciden direcciones, rutas y prefijo, nada cambia respecto a versiones anteriores.
Si no:

- Por defecto, el sitio **conserva sus direcciones** (las de este servidor) y las del respaldo se cambian por ellas.
- «Conservar las direcciones del respaldo» (pantalla), `addresses=backup` (REST) o `--addresses=backup` (WP-CLI) deja
  las URL del respaldo. Las rutas del servidor y el prefijo de tablas siguen siempre a este servidor: son hechos de la
  instalación, no una preferencia. Si `wp-config.php` de este servidor define `WP_HOME` o `WP_SITEURL`, esas constantes
  son «las direcciones de este sitio» y siguen mandando después de restaurar, también con la opción del respaldo.
- La pantalla de Restaurar dice en cada archivo «origen … → este sitio» y, al elegirlo, qué se reescribirá; `verify`
  lo devuelve en `relocation` (REST) o lo escribe (WP-CLI). Después de restaurar, el aviso resume lo hecho y la pantalla
  muestra el informe por tabla («Última restauración en otro dominio», `digitalisimo_backups_last_relocation`).

**Qué se reescribe.**

- **URL**: `https://origen.com/ruta` → `https://destino.com/otra`, también `http://`, `//origen.com` (protocolo
  relativo, queda `//destino.com`), `www.origen.com`, JSON con barras escapadas una y dos veces (`https:\/\/…`, como el
  `_elementor_data` de Elements) y URL codificadas una y dos veces (`https%3A%2F%2F…`, `https%253A%252F%252F…`,
  conservando `%2f` o `%2F`, también con el puerto codificado, `localhost%3A8080`). Un puerto por defecto explícito
  (`https://origen.com:443/`, `http://origen.com:80/`) es el mismo origen. El host compara sin mayúsculas y sólo
  completo: `origen.com` no toca `origen.com.mx`, `miorigen.com`, `cdn.origen.com`, `origen.com:8443` ni
  `ftp://origen.com`. La ruta compara hasta un límite de segmento (`/blog` no toca `/blogs` ni `/blog.html`). Un sitio
  que se queda en su dirección (un par igual) no lo cambia el par más corto de su host.
- **PHP serializado**: se recorre el formato (nunca `unserialize()`, así que ningún objeto del respaldo se instancia y
  ningún `__wakeup` o `__destruct` corre) y cada cadena se reescribe con su longitud en bytes recalculada, también
  serializados dentro de cadenas serializadas (hasta 16 niveles) y con multibyte. Las claves de un array que son URL (un
  mapa de redirecciones) también cambian, salvo que chocaran con otra clave del mismo array.
- **Rutas del servidor**: `ABSPATH` (y su ruta real), `wp-content` y `uploads` del origen → las de este servidor, con
  límites (`/var/www/origen2` no se toca), sólo donde las guardan WordPress y los plugins: un valor que **es** una ruta
  (empieza por ella y no sigue texto), en cualquier tabla y dentro de un serializado; y, en ajustes y metas (`options`,
  `sitemeta`, `*meta`), una ruta dentro de un texto o JSON si va seguida de `/wp-content`, `/wp-includes`, `/wp-admin` o
  `/wp-config.php`. El contenido público (`post_content`, extractos, comentarios) y un tutorial que nombra la ruta no
  cambian, y un origen de un solo segmento (`/wordpress`, el de Playground) nunca se cambia dentro de un texto, porque
  se confunde con un enlace relativo (`href="/wordpress/"`). El manifiesto trae las rutas desde @NEXT_BACKUPS@ (bloque
  `origin`).
- **Prefijo de tablas**: cada tabla del origen se crea, vacía y llena con el prefijo de este sitio (el guardián cambia
  el identificador exacto que leyó, también en `REFERENCES` de una clave foránea). Cambian `{prefijo}user_roles` de cada
  sitio y en `usermeta` todas las claves que empiezan por el prefijo (`{prefijo}capabilities`, `{prefijo}N_user_level`,
  lo que escribe `update_user_option()`). Las claves de permisos que el respaldo ya traía con el prefijo de **este**
  sitio (`{prefijo-destino}capabilities`, `user_level` o `user_roles` de otra instalación: una tabla de usuarios
  compartida o un cambio de prefijo anterior) no se aplican, porque darían permisos que el usuario no tenía en el sitio
  restaurado; de otra clave repetida queda la del sitio restaurado. El otro WordPress que use en esta base el prefijo
  del origen no se toca. Un prefijo distinto ya no impide restaurar.
- **Tablas ajenas**: el respaldo guarda todas las tablas de la base del origen. Al restaurar en otro dominio, servidor o
  prefijo, las que no tienen el prefijo del origen (otra aplicación u otro WordPress de aquella base) **no se
  restauran**: las de este servidor con el mismo nombre no se borran ni se recrean. `verify`, la pantalla y el informe
  las nombran antes de confirmar. Restaurar en el mismo sitio, sin reubicar, las sigue restaurando como antes.
- **Red**: con el dominio y la ruta de la red de este servidor, `site`, `sitemeta.siteurl`, `blogs` y `home`/`siteurl` de
  cada sitio, todo **durante la importación**, fila por fila: ningún punto de la restauración deja unas tablas de la red
  en un dominio y otras en el otro. Por subdirectorios, `origen.com/uno/` pasa a `destino.com/<ruta de la red>uno/`; por subdominios,
  `uno.origen.com` pasa a `uno.destino.com`; también la meta `source_domain` de cada usuario (el dominio, sin esquema,
  del sitio donde se registró). Un sitio con dominio propio (`cliente.com`) se queda como está.
- **Cachés**: transitorios (`options` y `sitemeta`), `rewrite_rules` de cada sitio (WordPress las regenera en la
  siguiente visita: es `flush_rewrite_rules` sin depender del `$wp_rewrite` de otro sitio, que `switch_to_blog()` no
  cambia), las metas `_elementor_css`, `_elementor_element_cache` y `_elementor_page_assets`, las opciones
  `_elementor_assets_data`, `elementor-custom-breakpoints-files` y `_elementor_global_css`, los archivos de
  `uploads[/sites/N]/elementor/css/` (Elements los regenera) y la caché de objetos. El CSS de las fuentes locales de
  Google (`elementor/google-fonts/css/`) se reescribe. En un sitio individual cuya ruta cambia, el bloque de WordPress de
  `.htaccess` se regenera como al guardar los enlaces permanentes, como último paso: en algunos hostings escribir
  `.htaccess` recarga el servidor web (OpenLiteSpeed de Webuzo) y corta la petición en curso. Las cachés de página y de
  recursos optimizados que viajan en el respaldo (`wp-content/cache/`: WP Super Cache, W3TC, WP Rocket, Autoptimize;
  `wp-content/litespeed/`) llevan las direcciones del origen: con otras direcciones se vacían. Después de cualquier
  restauración se pide a LiteSpeed que purgue su caché (cabecera `X-LiteSpeed-Purge` y la acción del plugin LiteSpeed
  Cache); por WP-CLI sólo queda la acción del plugin.
- **Archivos de configuración del servidor** (en la raíz de WordPress: `.htaccess`, `.user.ini`, `php.ini`,
  `wordfence-waf.php`), al extraerlos: las rutas y las direcciones del origen cambian por las de este servidor (también
  el host escapado de un `RewriteCond`, `^www\.origen\.com$`), y un `auto_prepend_file` o `auto_append_file` cuyo
  archivo no existirá aquí queda comentado con un aviso: si no, PHP daría un error fatal en cada petición, también en el
  escritorio desde el que se restauraría la copia previa. El cortafuegos de Wordfence del respaldo queda apuntando a este
  servidor (su `wordfence-waf.php` viaja); si el del respaldo se desactiva y este servidor tenía uno que funcionaba en
  ese archivo, se conserva el de este servidor.

**Qué no se reescribe, y por qué.**

- La columna `guid` de las entradas: WordPress pide no cambiarla (identifica la entrada en los lectores de feeds).
- Los correos (`hola@origen.com`, `admin_email`, `user_email`): un buzón vive en el DNS del dominio, no en el servidor
  web; cambiarlo rompería el inicio de sesión por correo, los avisos y los pedidos.
- El dominio como texto, sin esquema ni `//` («visita origen.com»): es redacción, no una dirección.
- Un serializado dañado (longitudes que no cuadran): se deja intacto, porque reescribirlo como texto lo rompería más. Un
  objeto `C:` (Serializable: su contenido es un formato propio de la clase) y un anidamiento de más de 16 serializados o
  512 arrays también se dejan e informan.
- Las rutas del servidor en el contenido público y en textos libres (ver «Rutas del servidor»), y las de un origen en
  Windows (`C:\xampp\htdocs\…`).
- Base64, entidades HTML (`&#x2F;`) y otras codificaciones propias de un plugin.
- Las vistas SQL y las tablas dentro de comentarios ejecutables con otro prefijo: se rechazan antes de tocar nada.
- Las rutas del servidor de un respaldo anterior a @NEXT_BACKUPS@: su manifiesto no las trae (las URL sí se reescriben).
- Un conjunto de UpdraftPlus: sigue su propio camino, que exige el prefijo de este sitio y no reubica direcciones.
- Las reglas de nginx o del `.htaccess` de una red si cambia la ruta: se avisa.

La comprobación final busca en la base de datos (con `LIKE`) cada host y cada ruta del origen en las tablas de este
sitio y clasifica cada fila candidata con el mismo criterio: `urls` y `paths` (lo que la reescritura habría cambiado y
sigue ahí) deben ser 0; `guid`, `kept` (lo que se deja a propósito, también las rutas en el contenido público), `emails`
y `other` se informan. Un error de la base de datos en la comprobación se avisa; nunca cuenta como 0.

**Comprobaciones previas** (antes de la copia previa y de cualquier escritura, con qué cambiar): sitio individual ↔ red;
red por subdominios ↔ por subdirectorios («define SUBDOMAIN_INSTALL como …»); `DOMAIN_CURRENT_SITE` o
`PATH_CURRENT_SITE` definidas en `wp-config.php` con otro dominio o ruta que los que tendrá la red (los de este sitio o,
con «conservar las del respaldo», los del respaldo); `SITE_ID_CURRENT_SITE` o `BLOG_ID_CURRENT_SITE` que el respaldo no
tiene; un respaldo sin lista de tablas (formato anterior al v3) con otro prefijo; tablas que acabarían con el mismo
nombre o con más de 64 caracteres; una vista o una tabla dentro de un comentario ejecutable que no se puede renombrar
(`verify` responde lo mismo que la restauración); y un valor que menciona el origen y no cabe en la memoria de PHP para
reescribirlo (ver «Escala y fallos»).

**Escala y fallos.** La reubicación ocurre en la importación, sentencia por sentencia y con los literales que comprobó
el guardián, así que la memoria no depende del tamaño de las **tablas** ni hace falta clave primaria, y no hay una
segunda pasada sobre la base de datos. Sí depende del tamaño de cada **valor**: recorrer un serializado grande cuesta de
3 a 19 veces su tamaño. Por eso la pasada en seco mide cada valor que menciona el origen contra lo libre de
`memory_limit` (entre 24, como mucho 32 MiB) y, si alguno no cabe, rechaza la restauración antes de la copia previa
diciendo qué subir; en la importación, uno que se escapara haría fallar la sentencia y volver a la copia previa, nunca
dejaría el sitio a medias por un error fatal. Para una base ya importada, `rewrite_tables()` trabaja por lotes de clave
primaria (simple o compuesta, con el literal según el tipo de la columna), lee grupos de hasta 4 MiB y no toca una
tabla sin clave primaria (la informa). Si algo falla durante la importación, el sitio no cambió (desde @NEXT_BACKUPS@ la importación va a tablas temporales);
si falla después del cambio (la red, las direcciones, los archivos), la restauración vuelve sola a la copia previa. La
restauración es un trabajo por pasos que se reanuda tras una muerte, y la red y las direcciones se fijan en las tablas
temporales antes del cambio, así que ningún punto intermedio deja una red sin cargar (ver «Restauración por pasos»;
`backups-relocate-interrupted.php` corta justo después del cambio).

Ensayo en WordPress Playground (`tests/playground/backups-relocate.sh`): WordPress 7.1.4, PHP 8.5, SQLite y núcleo de
Elements 4.3.5, todo por HTTP. Un sitio individual y una red por subdirectorios (con el subsitio `/uno/`), con páginas
de Elements con imagen de fondo, se respaldan por REST en `http://origen.test` (prefijo `wp_`) y se restauran por REST
como archivo suelto de otro servidor en `http://destino.test`, instalado con el prefijo `dst_` junto a otro WordPress
con `wp_` en la misma base. `verify` dice «Origen → este sitio»; después de restaurar (71 y 72 s con el servidor
cargado), las páginas y su CSS regenerado cargan sin `origen.test`, los enlaces internos van al dominio nuevo, ninguna
tabla del sitio menciona el origen salvo `guid` (7 y 13, que la comprobación final encuentra y deja), el otro WordPress
queda intacto, los sitios de la red tienen el dominio nuevo y se inicia sesión con la cuenta del respaldo, con el
escritorio (de la red) y sus permisos: 45 comprobaciones, 574 s, todo correcto. El cambio de ruta del sitio y de carpeta
del servidor y la red por subdominios no caben en Playground (sirve en la raíz, con `ABSPATH=/wordpress` y hosts por
cabecera) y se prueban en `backups-relocate.php`.

## Restauración por pasos

Desde @NEXT_BACKUPS@. El ensayo en un hosting real (`/var/tmp/digi-wt/bk-lab/LAB.md`: Webuzo con OpenLiteSpeed,
`max_execution_time=30`, `memory_limit=64M`, Cloudflare que corta a los 100 s y un cron que recarga el servidor web al
cambiar un `.htaccess`) mostró cuatro fallos de la restauración en una sola petición: con 64 MB no se podía restaurar
desde la web (H1), una restauración cortada a mitad del SQL dejó una red a medias y sin aviso (H2), lo que no estaba en
el respaldo sobrevivía (H3) y la restauración entera duraba de 2 a 4 minutos en una petición (H4). Ahora la restauración
es un trabajo del mismo motor que los respaldos (`Digitalisimo_Backups_Restore_Job`).

**Fases** (cada una con su punto de control en `jobs/<id>/job.json` y lo intermedio en su área de trabajo):

| Fase | Qué hace | Si el proceso muere |
| --- | --- | --- |
| Verificación del respaldo | Entradas del ZIP, manifiesto v3 leído por partes a un índice en disco, CRC y SHA-256 de cada entrada, espacio, el SQL en seco (guardián, sonda de la reubicación, tablas), el plan de origen a destino con el sitio intacto y las sentencias más grandes pasadas por la reubicación (memoria). Un rechazo es permanente y no toca nada. | Sigue desde la entrada o la sentencia en que se quedó. |
| Copia previa | Un respaldo completo con las fases del respaldo (sus tramos, cuando T2 los tenga), bloqueado en el historial. La vuelta a la copia previa no hace otra. | Sigue desde la fase del respaldo en que se quedó. |
| Base de datos | Desde aquí, mantenimiento. El SQL va a tablas temporales `<prefijo>dgbkr_<marca>_<n>`; el sitio sigue sirviéndose de las suyas. Al terminar se fijan en ellas la red (`site`, `sitemeta`, `blogs`) y `home`/`siteurl` de cada sitio. | Repite sólo la tabla en curso desde su `DROP TABLE IF EXISTS` (lo ejecutado tras el último punto de control no consta). |
| Archivos | Recorre las raíces gestionadas (lo ajeno, al informe), compara cada archivo con el sitio (tamaño y SHA-256) y prepara lo que cambia en un área de trabajo: una carpeta de código entera si algo suyo cambia, un medio o un archivo suelto, solo. | Sigue desde la entrada en que se quedó; el recorrido se repite (sólo mira). |
| Cambio del sitio | Las tablas pasan de una vez (MySQL: un solo `RENAME TABLE`, atómico; el SQLite de WordPress, que no lo tiene: una transacción); se reponen historial, llavero, seguridad y credenciales; se mueven los archivos (cada carpeta de código con dos `rename()`: la vieja se aparta junto a la viva como `.digitalisimo-old-<marca>-<nombre>`, en su mismo disco, y si la preparada está en otro disco se copia junto al destino y se renombra allí), se retira lo ajeno, este plugin y, al final y una sola vez, los `.htaccess`. Desde aquí el mantenimiento no caduca. | Sigue; si murió justo tras el `RENAME`, el diario del almacén repone historial, llavero y credenciales al tomar el bloqueo. |
| Direcciones y cachés | `finish()` de la reubicación (E1): red, direcciones, cachés y comprobación final. | Se repite (idempotente). |
| Limpieza | Fin del mantenimiento, informe y retirada de tablas y carpetas viejas. Nunca hace fallar la restauración. | Se repite. |

**Reanudar o volver.** Un tramo que muere se reconoce como en los respaldos (el detector, o el apagado de PHP) y la
operación siguiente lo reanuda: la pantalla abierta, WP-Cron, `POST /jobs/tick` o `wp digitalisimo-backups jobs run`. Una
fase tiene 5 intentos seguidos sin avanzar (cada tramo guardado vuelve a contar desde cero) con esperas de 10 s, 1 min y
5 min. Si se agotan antes del cambio, o se cancela, se retiran las tablas temporales y el área de trabajo y el sitio queda
como estaba. Si falla después del cambio, se encola la **vuelta a la copia previa**: una restauración de la copia previa
con el mismo motor, sin reubicación; si también falla, el trabajo lo dice con el archivo que hay que restaurar a mano y el
sitio queda en mantenimiento. En WP-CLI (y en la restauración dentro de un proceso) no hay reintentos: un fallo tras el
cambio vuelve enseguida a la copia previa.

**Avisos.** Mientras no termina, la pantalla Backups muestra en todas sus pestañas qué fase lleva, si se interrumpió y
qué pasa con el sitio; `status` (REST y WP-CLI) lo da en `restore` y lo cuenta entre los `problems` (los respaldos
esperan a que termine). Una restauración que falló a mitad, que volvió a la copia previa o que se canceló se avisa
durante 7 días. La tarjeta «Trabajos» la lista («Restauración» o «Vuelta a la copia previa») y deja cancelarla antes del
cambio.

**Mantenimiento.** Desde la base de datos hasta el final, `ABSPATH/.maintenance` pone el sitio en mantenimiento, pero
deja pasar las peticiones que traen el testigo de la restauración (en el archivo sólo va su SHA-256): la cookie
`digitalisimo_backups_restore` del navegador que la empezó (con ella ve la pantalla), el parámetro del mismo nombre que
añade el tick de WP-Cron y `maintenance_token` de la respuesta de REST; WP-CLI pasa siempre. El tick por testigo
(`admin-ajax.php?action=digitalisimo_backups_restore_tick`) no exige sesión: tras el cambio de tablas, las sesiones y
las contraseñas de aplicación son las del respaldo. Hasta el cambio el mantenimiento caduca solo a los 10 minutos si la
restauración se detiene (el sitio sigue entero); desde el cambio, no.

**Archivos ajenos (H3).** Dentro de las raíces que el respaldo gestiona —núcleo (`wp-admin`, `wp-includes` y los `.php`
de la raíz), plugins, temas, mu-plugins y medios—, lo que no figura en un manifiesto v3 se retira: un `.php` inyectado
en uploads, un plugin instalado después. Nunca fuera de esas raíces (otra aplicación junto a WordPress, `robots.txt`,
`wp-content/languages` u otras carpetas de `wp-content`), nunca `wp-config.php`, el almacén, la base de datos SQLite
activa ni lo que el respaldo excluye a propósito (`.git`, `node_modules`, carpetas `cache`, enlaces simbólicos): una
carpeta de plugin o de tema con algo así no se cambia entera, sus archivos van uno a uno y lo excluido se queda. Un
respaldo anterior al v3 no retira nada (puede no traer el árbol entero). Todo lo retirado está en la copia previa; el
resultado (`removed`), el aviso y `jobs/<id>/removed.txt` lo nombran.

**Memoria (H1).** El manifiesto se lee por partes (`Digitalisimo_Backups_Manifest_Stream`, la misma semántica que
`json_decode()`) a un índice en disco por posición del ZIP; los duplicados los reconoce la tabla de nombres de libzip.
Así lo hacen también la firma (HMAC por partes), el esquema estricto y la verificación del contenido de un respaldo
nuevo. Cada petición eleva la memoria con `wp_raise_memory_limit()`, pero las fases caben en 64 MB aunque no suba:
`backups-restore-memory.php` verifica y restaura un respaldo de 40 008 entradas con `memory_limit=64M` y 38 MB ya
ocupados.

**Presupuesto (H4).** Cada fase trabaja por tramos con el medidor de T2 (`Digitalisimo_Backups_Step_Budget`, copiado tal
cual): el plazo del tick, nunca más allá del límite real de la petición menos un margen, la mitad tras un tramo que murió
y un cupo de memoria. La copia previa usa las fases del respaldo: hasta que llegue T2, su empaquetado y su verificación
son un paso largo cada uno (límite declarado).

**Garantías de la revisión de E1.** La red y las direcciones se fijan en las tablas temporales antes del cambio, así que
las tablas llegan de una vez coherentes (H1); de dos claves de `usermeta` que chocan al cambiar de prefijo queda la del
sitio restaurado, también resuelto en las temporales (`after_import()` de E1, con lo que cada tramo anota). Una tabla sin
el prefijo del WordPress que se restaura (otra aplicación u otro WordPress de la misma base) se comprueba con el guardián
pero nunca se borra ni se recrea, y se informa (H3). La sonda de E1 mide en la verificación los valores que mencionan el
origen y no cabrían en la memoria para reescribirlos (el plan los rechaza), y además las tres sentencias más grandes pasan
por la reubicación en seco: si no caben, la petición muere ahí, antes de la copia previa; y si algo muere en la
importación, el sitio sigue intacto (H4). Los archivos del servidor de la raíz (`.htaccess`, `.user.ini`, `php.ini`,
`wordfence-waf.php`) pasan por `server_file()` de E1 al prepararlos (rutas y dominio del origen, un `auto_prepend_file`
que aquí no existiría se desactiva y el de este servidor que sí, se conserva), y sin reubicación un `auto_prepend_file`
inexistente también se desactiva (P1); la limpieza avisa si alguno sigue apuntando a un archivo que no existe y vacía la
caché del servidor web (LSCache).

**Límites declarados.**

- Las vistas SQL de un respaldo no se restauran por pasos: el renombrado a las tablas temporales las rechaza antes de la
  copia previa, como E1 con otro prefijo. Una clave foránea con nombre que ya usa la tabla viva se crea con el nombre que
  pone MySQL.
- La copia previa: su empaquetado y su verificación son un paso largo cada uno hasta T2; sin T2, un sitio muy grande en
  un hosting que corta a los 30 s puede no terminarla (el sitio no cambia).
- Un archivo muy grande se prepara entero en un tramo; mover entre discos distintos copia y comprueba (más lento). Si el
  proceso muere para siempre a mitad del cambio, quedan carpetas apartadas `.digitalisimo-old-*` junto a las vivas (ocultas:
  WordPress no las carga) hasta que una restauración termina (también la vuelta a la copia previa, que retira las de la que
  deshizo).
- Una muerte justo después del `RENAME` deja el llavero sin su envoltura hasta volver a escribir la frase (el diario, como
  en 1.0.59); el resto se repone.
- Si muere en el cambio y lo restaurado no deja cargar WordPress (un plugin a medio cambiar que da un error fatal), la
  restauración no puede seguir por la web: `wp digitalisimo-backups jobs run --skip-plugins=<el que falla>` (el
  `.maintenance` de la restauración siempre deja pasar a WP-CLI) o restaurar a mano la copia previa. Cada carpeta de
  código cambia de una vez para que eso no pase.
- Volver a una versión anterior desde `rollback/` con una restauración sin terminar: esa versión no conoce los trabajos de
  restauración y los da por dañados.
- UpdraftPlus sigue restaurándose en una sola petición.

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

Junto a los ZIP, el almacén guarda `jobs/` (estado y área de trabajo de cada respaldo en curso; se conservan los
últimos 20 trabajos terminados o los de 30 días) y los cerrojos `.operation.lock` y `.jobs.lock`. Nada de eso se
incluye en un respaldo ni se puede descargar. Si cambias `DIGITALISIMO_BACKUPS_STORAGE_DIR` con un trabajo activo,
ese trabajo se queda en la carpeta anterior y no continúa: vuelve a iniciar el respaldo.

Después de actualizar, comprueba en Backups que aparecen los archivos históricos y descarga uno con la acción
autenticada. Si una migración no termina, los originales permanecen en su ubicación anterior y el plugin muestra el
error: corrige permisos o configura otra carpeta privada antes de reintentar.

## Cifrado, firma y archivos no confiables

Desde 1.0.59. Qué se promete y qué no:

- El cifrado protege las copias **fuera del servidor** (SFTP, Google Drive, OneDrive y descargas). No protege de quien
  ya controla el servidor: quien lee `wp-config.php` y la base de datos también lee la clave.
- La firma detecta cualquier cambio en un respaldo hecho con una clave que el sitio o el administrador conocen. La
  validación garantiza que un archivo no salga de la raíz de WordPress ni de sus tablas, que no agote el disco y que
  no traiga entradas ocultas. **No** garantiza que su contenido sea benigno: restaurarlo instala su código y sus datos.
- Sin la clave, un respaldo cifrado no se puede restaurar. Nadie puede recuperarlo, tampoco DIGITALÍSIMO.
- Las credenciales de los destinos viajan en cada respaldo, cifradas (ver «Credenciales de los destinos»): la base de
  datos y su SQL ya no las revelan, pero quien tiene también `wp-config.php` sí puede abrirlas.

### La clave

Hay dos formas, y la constante manda si existen las dos (las claves del llavero siguen sirviendo para restaurar):

1. **Frase** en Backups → Seguridad (de 16 a 1024 caracteres). La frase no se guarda: se deriva la clave con
   PBKDF2-SHA256 (600 000 iteraciones y un salt de 16 bytes) y se guarda envuelta (secretbox) con una clave que sale de
   `AUTH_KEY` y `SECURE_AUTH_KEY`. Sin esas dos constantes propias en `wp-config.php` no se puede guardar una frase.
2. **Constante** en `wp-config.php`, base64 de 32 bytes. Con ella el cifrado es obligatorio, porque cada respaldo
   lleva el propio `wp-config.php`:

   ```bash
   php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
   ```

   ```php
   define( 'DIGITALISIMO_BACKUPS_KEY', 'pega-aquí-el-resultado' );
   ```

   Una constante mal formada no se muestra nunca: la pestaña dice «inválida» y los respaldos fallan hasta corregirla.

Guarda la frase o la constante **fuera del sitio**. Cambiar la frase retira la clave anterior sin borrarla; «Quitar del
llavero» (con la confirmación `QUITAR`) es aparte y muestra cuántos respaldos del historial dependen de cada clave. Si
cambian `AUTH_KEY` o `SECURE_AUTH_KEY`, la entrada queda en «Vuelve a escribir la frase» y, mientras tanto, los
respaldos con cifrado activo fallan: nunca salen en claro. El llavero vive en `digitalisimo_backups_vault_keyring` (de
red en Multisite) y las opciones `digitalisimo_backups_vault_*` no entran en ningún respaldo; una restauración une los
llaveros por huella y nunca deja la configuración más débil que antes, también si el proceso muere a mitad: el diario
`.restore-journal.json` del almacén (sin la frase, la clave ni su envoltura) devuelve cada clave pidiendo la frase. Límite declarado: si vuelves a 1.0.58 desde
`rollback/` y restauras con ella, esa versión no conoce el llavero y lo pierde; los respaldos cifrados se siguen
abriendo escribiendo la frase.

### Restaurar en otro servidor

En Restaurar, elige el archivo (también los que copies a la carpeta privada del almacén, que aparecen como «sin
registro»), escribe la frase o la clave y confirma. La lista dice si un archivo está cifrado (por sus bytes, no por su
extensión), con qué clave y si este sitio la tiene; si no la tiene, pide «escribe su frase o su clave». El salt viaja en cada respaldo, así que basta con la frase. La
casilla «Usar esta frase para los respaldos nuevos» la guarda en el llavero de ese servidor.

| Archivo | Confianza | Confirmación |
| --- | --- | --- |
| Firmado con una clave del sitio o con la frase escrita | Firmado | `RESTAURAR` |
| Fila del historial creada aquí con el mismo SHA-256 (todos los respaldos desde 1.0.39) | Registrado en este sitio | `RESTAURAR` |
| Sin firma válida y sin fila (otro servidor, versiones anteriores sin registro, UpdraftPlus) | Sin firma | `RESTAURAR SIN FIRMA`, o bloqueado con «Restaurar sólo respaldos firmados» |
| Firma que no coincide, comentario sin bloque de firma o frase de otra clave | — | Se rechaza siempre |

La copia `safety-before-restore-*` nunca se cifra (es local y se recupera sin depender de una clave que el SQL
restaurado podría no traer), pero se firma si hay clave.

### Credenciales de los destinos

Desde 1.0.61. Decisión del propietario (2026-11-08): un respaldo restaura el sitio **completo**, también las
conexiones de sus destinos, así que las credenciales siguen viajando en el SQL de cada respaldo, pero cifradas.

- **Qué se guarda y dónde.** La contraseña de SFTP y el Client secret y el Refresh token de Google Drive y OneDrive
  viven en `digitalisimo_backups_credentials` (`sitemeta` en una red, `options` en un sitio individual), nunca en
  `digitalisimo_backups_settings`. Guardar «Servidores», conectar por OAuth y la migración pasan por el almacén; el
  navegador nunca recibe un valor (campo vacío y su estado en el `placeholder`).
- **Valores exactos.** Un secreto se guarda tal como se escribió (o como lo devolvió el proveedor por OAuth): sin
  las barras que WordPress añade a `$_POST` y sin saneado ni recorte, así que comillas, barras invertidas, `<…>`, `%xx`,
  espacios al borde y texto no ASCII forman parte del secreto. Sólo se rechazan, con un mensaje que nombra el campo y
  nunca su valor y sin cambiar nada, NUL, los caracteres de control (C0, DEL y C1) y lo que pase de 8192 bytes. Un campo
  vacío o sólo con espacios conserva lo guardado. Host, usuario, ruta y los demás campos que no son secretos mantienen
  su saneado. REST y WP-CLI no reciben secretos de destinos.
- **Formato v1.** `{"v":1,"items":{"<campo>":{"kw":"<huella>","c":"<base64url(nonce ‖ cifrado)>"}}}`. Cada elemento es
  AEAD XChaCha20-Poly1305-IETF con un nonce aleatorio de 24 bytes y el dato adicional
  `DGBK1/credentials|v1|<campo>|<contexto>`, donde el contexto son los datos de la conexión a la que va el secreto
  (host, puerto, usuario y huella SSH para SFTP; el Client ID para Google Drive y OneDrive), cada uno como
  `<longitud>:<valor>;`: un valor movido a otro campo o alterado no se abre, y una escritura directa en la base de datos
  que cambie el host o el Client ID no desvía el secreto, que deja de abrirse y pide escribirse (cambiarlos desde el
  formulario, en cambio, vuelve a atarlo sin escribirlo). La clave es
  `K = BLAKE2b-256("DGBK1/credentials", clave = BLAKE2b-256(AUTH_KEY ‖ "\0" ‖ SECURE_AUTH_KEY))` (separación de dominio
  distinta a la del llavero) y su huella, `kw = hex(BLAKE2b-128("DGBK1/credentials-id", clave = K))`, los 16 primeros
  caracteres. Cada elemento lleva su huella, así que unas credenciales que no se abren no impiden usar las demás. Los
  vectores de `backups-credentials.php` se contrastaron con `hashlib` y `cryptography` de Python.
- **Qué protege.** Quien sólo tiene la base de datos o el SQL de un respaldo (un volcado filtrado, una copia de
  staging, una inyección SQL de lectura, la exportación de otro plugin) ya no lee las credenciales. **No** protege de
  quien tiene también `wp-config.php`, igual que la contraseña de la base de datos: un respaldo ZIP sin cifrar lleva
  `wp-config.php`, así que quien tenga ese ZIP puede abrirlas. Un `.dgbk` lo protege todo con la frase.
- **Restaurar en el mismo servidor.** Las credenciales del respaldo mandan sobre las que tenía el servidor (restaurar
  es volver al estado del respaldo) y los destinos quedan funcionando sin volver a escribirlas.
- **Restaurar en otro servidor o dominio.** Tras el SQL, lo cifrado con las salts del origen se vuelve a cifrar con las
  de este servidor. Las del origen se leen del `wp-config.php` que va dentro del propio respaldo, ya comprobado contra su
  manifiesto: sólo `define( 'AUTH_KEY'|'SECURE_AUTH_KEY', '<literal>' )` con literales de cadena, con el tokenizador de
  PHP; el archivo nunca se ejecuta ni se incluye, y cada candidato se comprueba por su huella. Un respaldo de 1.0.60 o
  anterior trae los secretos en claro en los ajustes: se cifran al restaurarlo.
- **Cuando no se abren.** Si las salts del respaldo no se pueden leer (`getenv()`, una constante definida en otro
  archivo o con una expresión) o cambiaron `AUTH_KEY` o `SECURE_AUTH_KEY` de este servidor, cada destino afectado queda
  en «vuelve a escribir la contraseña de SFTP» o «vuelve a escribir el Client secret y conéctalo de nuevo»: lo dicen la
  pantalla, `status` de REST y WP-CLI y el aviso de la restauración, crear un respaldo hacia él responde 400 con el
  motivo y un trabajo con ese destino falla de forma permanente con el mismo mensaje y conserva el ZIP local
  verificado. Nunca se cae al texto en claro. Lo cifrado se conserva: si vuelven las salts que lo cifraron, vuelve a
  abrirse. Al escribir un valor nuevo, ese campo se cifra con las salts actuales y los demás no cambian.
- **Sin `AUTH_KEY` y `SECURE_AUTH_KEY` propias** (las mismas reglas que el llavero: al menos 16 caracteres y no el texto
  de ejemplo de WordPress) no hay con qué cifrar: se guardan en los ajustes como hasta 1.0.60, porque no guardarlas
  dejaría el sitio sin destino, y la redirección, la pantalla y `status` avisan que hay que definirlas. En cuanto
  existen, la siguiente carga del escritorio las cifra.
- **Migración.** Al cargar el escritorio, el cron o WP-CLI, antes de volcar el SQL de cada respaldo y después de cada
  restauración. Los ajustes se guardan con autoload, así que comprobarlos al cargar no cuesta ninguna consulta; una
  versión anterior (1.0.60 tras volver desde `rollback/`) los reescribe sin autoload y en claro, y la siguiente carga
  los lee, los cifra y les devuelve el autoload. Se cifra, se comprueba que vuelve a abrirse y sólo entonces se retira
  el texto en claro. La única lectura de los secretos (los ajustes en memoria) cifra también lo que aparezca en claro
  por otro camino.
- **Ningún respaldo lleva un secreto en claro si hay salts propias.** Antes del volcado se migra lo que quede en claro
  (si no se puede comprobar, la fase falla y se reintenta) y, fila a fila, una fila de `digitalisimo_backups_settings`
  de cualquier tabla de opciones sale sin secretos en claro (también la de un subsitio con unos de una versión anterior,
  que el motor no usa).
- **Red.** Las credenciales son de toda la red y sólo las lee y escribe el sitio principal (la administración de red
  corre en él). La pantalla de un subsitio no muestra «Servidores» ni anuncia los secretos de la red, y guardar o
  conectar por OAuth desde un subsitio se rechaza sin cambiar nada; su migración tampoco toca el almacén de la red. Un
  sitio individual convertido en red adopta su almacén (de sus opciones a `sitemeta`) la primera vez que se lee.
- **Olvidar.** Cada destino con algo guardado tiene en «Servidores» la casilla «Olvidar las credenciales guardadas»
  (mismo formulario, con su nonce y su capability): al guardar se retiran del almacén, también las que ya no se abren,
  y su aviso desaparece. Lo que se escriba en sus campos en ese mismo guardado sí se guarda.
- **Datos hostiles.** Un elemento del almacén cuyos datos no son texto (un objeto de un SQL no confiable o de una
  escritura directa) se lee como «vuelve a escribir»: nunca rompe los ajustes, la pantalla ni `status`. Un
  `wp-config.php` de más de 64 KiB dentro de un respaldo no se analiza (uno real ocupa de 3 a 10 KB): sus salts quedan en
  «vuelve a escribir» y el tokenizador nunca usa más de unos 18 MiB.
- **Restauración interrumpida.** El diario lleva las claves del origen cifradas con las de este servidor (nunca en
  claro), como lista con su huella (una huella toda decimal no se pierde): si el proceso muere entre el SQL y el nuevo
  cifrado, la operación siguiente lo termina. Lo que el servidor
  tenía antes de restaurar está en la copia previa.

Límites declarados: volver a 1.0.60 desde `rollback/` deja los destinos sin secreto (esa versión no conoce el almacén):
hay que volver a escribirlos. Un conjunto de UpdraftPlus no trae `wp-config.php`, así que lo cifrado con otras salts
que traiga su SQL queda en «vuelve a escribir». Unos secretos en claro en la opción de un subsitio (de una versión
anterior, sin efecto) se quedan en la base de datos de ese subsitio, aunque ningún respaldo los lleva. Una escritura
directa en la base de datos todavía puede reponer un texto cifrado antiguo del mismo campo y la misma conexión. El
parser de `wp-config.php` no reconoce la coma final en `define()`, los argumentos con nombre, `const` ni nowdoc: esas
salts quedan en «vuelve a escribir», nunca con un valor equivocado. En WordPress anterior a 6.4 (sin
`wp_set_option_autoload()`), unos ajustes que 1.0.60 dejó sin autoload y sin nada en claro se leen con una consulta en
cada carga del escritorio hasta el siguiente guardado.

### Firma del manifiesto

Con clave, el manifiesto v3 añade `"auth":{"version":1,"alg":"hmac-sha512256","key":<descriptor>,"encrypted":bool}` y
el ZIP lleva el comentario `DIGITALISIMO-AUTH/1 <key_id> <mac>`, con
`mac = HMAC-SHA512/256(K_mac, "DIGITALISIMO-AUTH/1\n" ‖ key_id ‖ "\n" ‖ bytes exactos de manifest.json)` en base64url
sin relleno. El manifiesto fija tamaño y SHA-256 de todas las entradas y la verificación rechaza entradas sobrantes o
ausentes, así que la firma cubre todo el respaldo. El formato sigue siendo `digitalisimo-backup/v3`: 1.0.58 ignora el
bloque y el comentario. Quien borra a la vez el bloque y el comentario deja un archivo sin firma, que pide
`RESTAURAR SIN FIRMA`.

### Formato DGBK v1

Archivo `digitalisimo-backup-<host>-<fecha>-<rand>.dgbk`, descargado como `application/octet-stream`. Se abre con
cualquier libsodium:

```text
"DGBK" | 0x01 | 0x00 0x00 0x00 | u32be len(H) | H (JSON UTF-8, ≤ 16 KiB) | cabecera secretstream (24 B) | C_1 … C_n
H = {"format":"digitalisimo-encrypted-backup/v1","backup_id","created_at","plugin_version","scope":"installation",
     "multisite","inner_size","inner_sha256","cipher":"secretstream-xchacha20poly1305","chunk_size":1048576,
     "file_salt":"<base64url de 32 B>","key":{"id","source":"passphrase|wp-config","kdf":{…}|null}}
```

- Claves, con `H(m, k, n) = BLAKE2b-n(m, clave k)`: `key_id = hex(H("DGBK1/key-id", K_root, 16))` (la interfaz muestra
  8 caracteres), `K_mac = H("DGBK1/manifest-mac", K_root, 32)` y `K_file = H("DGBK1/file|" ‖ file_salt, K_root, 32)`.
  Con frase, `K_root = PBKDF2-HMAC-SHA256(frase, salt, iteraciones, 32)` con los parámetros de `key.kdf`, que se
  validan antes de derivar (100 000 a 5 000 000 iteraciones y salt de 16 bytes).
- `C_i = crypto_secretstream_xchacha20poly1305_push(P_i)` con bloques de `chunk_size` bytes (potencia de 2 entre 4 KiB
  y 8 MiB; el último, de 1 a `chunk_size`). El dato adicional de `C_1` es todo el prefijo, desde `"DGBK"` hasta el
  final de `H`; los demás no llevan. Etiqueta `MESSAGE` (0) en los intermedios y `FINAL` (3) en el último, y tras
  `FINAL` debe llegar el fin del archivo.
- Tamaño exacto: `prefijo + 24 + inner_size + ceil(inner_size / chunk_size) · 17`; compararlo detecta truncamientos
  antes de descifrar. El texto descifrado es el ZIP v3 firmado (`auth.encrypted = true`), cuyo `backup_id` y huella
  deben coincidir con `H`, y cuyo SHA-256 es `inner_sha256`.
- `tests/fixtures/backups/kat-v1.dgbk` es el vector conocido: clave base64 de 32 bytes `0x42`
  (`QkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkI=`), huella `c0748d5b…`. `backups-encryption.php` lo abre y lo
  restaura en cada ejecución; los vectores de PBKDF2, de las subclaves y del MAC se contrastaron con `hashlib` y `hmac`
  de Python.
- Detalle de libsodium que una implementación propia debe respetar: tras el texto cifrado, Poly1305 se rellena con
  `(0x10 − 64 + mlen) & 0xf` bytes nulos (no hasta el múltiplo de 16 de `64 + mlen`).
- **Recuperación sin WordPress:** `scripts/dgbk-decrypt.py` es una implementación independiente en Python (HChaCha20
  propio y ChaCha20-IETF y Poly1305 de `cryptography`) que descifra y verifica un `.dgbk` con la frase o la clave y
  deja el ZIP interior: `python3 -I scripts/dgbk-decrypt.py respaldo.dgbk respaldo.zip` (pide el secreto por la
  terminal, o `--secret-file`; nunca en la línea de órdenes). Se comprobó con el vector conocido, con un archivo de
  varios bloques y con una frase con espacios, `<` y `&`.

Rendimiento: con libsodium nativo el cifrado no es el cuello de botella. Sin él, `sodium_compat` es PHP puro; la
pestaña mide la velocidad de este servidor y estima cuánto tardaría el último respaldo.

### Archivos no confiables

Antes de la copia previa y antes de cualquier consulta o escritura, en el respaldo propio, en un archivo suelto del
almacén, en un ZIP descifrado y en UpdraftPlus:

- **Archivo.** El tipo sale de los bytes mágicos (`PK` o `DGBK`), nunca de la extensión; debe ser un archivo normal
  de la raíz del almacén, no un enlace. Si tiene fila en el historial, su SHA-256 debe coincidir.
- **Entradas.** Hasta `DIGITALISIMO_BACKUPS_MAX_ENTRIES` (2 000 000); nombres de hasta 4096 bytes con segmentos de
  hasta 255; sin NUL, rutas absolutas, unidades ni segmentos `.`, `..` o vacíos; sólo las raíces `manifest.json`,
  `database.sql`, `migration.json`, `MIGRAR-A-WORDPRESS-UNICO.txt` y `wordpress/`; sin enlaces ni archivos especiales
  (atributos Unix), sin cifrado ZIP y sólo los métodos guardado y deflate; sin duplicados, y sin nombres que sólo se
  distinguen por mayúsculas si el disco de destino no las distingue. La barra invertida se convierte en un respaldo
  confiable, como siempre, y se rechaza en uno sin firma.
- **Manifiesto v3.** Hasta 256 MiB, con identificador UUID, prefijo `[A-Za-z0-9_]`, lista de tablas, URL http(s),
  tamaños enteros y SHA-256 hexadecimales; los totales deben coincidir con lo observado.
- **Espacio.** Lo que crecen los archivos sobrescritos o nuevos, más el temporal del mayor y 64 MiB, debe caber junto a
  WordPress; descifrar necesita `inner_size · 1,05 + 256 MiB` en el almacén. Si el servidor deshabilita
  `disk_free_space()`, se sigue: la restaurabilidad manda.
- **SQL.** El guardián recorre todo el SQL antes de la copia previa y vuelve a comprobar cada sentencia al importar.
  Error con el número de sentencia, su tipo y sus primeros caracteres, sin valores de filas.
