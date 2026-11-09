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
| **Credenciales de los destinos cifradas que viajan en el respaldo (S4).** La contraseña de SFTP y el Client secret y el Refresh token de Google Drive y OneDrive viven en `digitalisimo_backups_credentials` (de la red en Multisite), cada uno cifrado con AEAD XChaCha20-Poly1305 (libsodium o el sodium_compat de WordPress) con una clave que sale de `AUTH_KEY` y `SECURE_AUTH_KEY` con su propia separación de dominio, y un dato adicional que lo ata a su campo. `digitalisimo_backups_settings` ya no guarda ninguno en claro: guardar «Servidores», la conexión OAuth (refresh token) y la migración de los sitios que vienen de 1.0.60 o anteriores (al cargar el escritorio, el cron o WP-CLI, sin ninguna consulta cuando no queda nada en claro, y después de cada restauración) pasan por el almacén, que comprueba cada valor antes de retirar el texto en claro; el navegador nunca recibe un valor. El almacén sí entra en `database.sql`: restaurar deja los destinos funcionando con las credenciales del respaldo, que mandan sobre las que tenía el servidor. En otro servidor (otras salts) se vuelven a cifrar con las de éste usando las del `wp-config.php` que va dentro del propio respaldo (sólo `define()` con literales de cadena, leídos con el tokenizador de PHP; el archivo nunca se ejecuta ni se incluye). Si no se pueden leer (`getenv()`, otro archivo) o cambiaron las salts de este servidor, el destino queda en «vuelve a escribir la contraseña / conéctalo de nuevo» en la pantalla, en `status` de REST y WP-CLI, en el aviso de la restauración y en el error permanente del trabajo con destino remoto; nunca se cae al texto en claro. Sin `AUTH_KEY` y `SECURE_AUTH_KEY` propias se guardan como hasta ahora, con aviso. Una restauración que muere entre el SQL y el nuevo cifrado lo termina con el diario. El dato adicional ata cada secreto también a su conexión (host, puerto, usuario y huella SSH; Client ID), así que una escritura directa que cambie el host no lo desvía. Ningún respaldo lleva un secreto en claro si hay salts propias (se migra antes del volcado y cada fila de ajustes sale sin ellos, también la de un subsitio); los ajustes van con autoload, así que la comprobación al cargar no cuesta consultas y detecta lo que 1.0.60 reescriba tras volver desde `rollback/`. En una red sólo el sitio principal lee y escribe el almacén; un sitio convertido en red lo adopta; «Olvidar las credenciales guardadas» retira las de un destino que ya no se usa; un almacén con datos que no son texto pide volver a escribir sin romper nada, y un `wp-config.php` de más de 64 KiB no se analiza. Cada secreto (del formulario o del retorno de OAuth) se guarda exactamente como se escribió, sin las barras de `$_POST` y sin saneado ni recorte: antes, comillas y barras invertidas ganaban barras, y `<…>`, `%xx` y los espacios al borde se perdían; sólo se rechazan, con un mensaje que no repite el valor y sin cambiar nada, NUL, los caracteres de control y más de 8192 bytes. | @NEXT_BACKUPS@ | `backups-credentials.php`, `backups-credentials-surfaces.php`, `backups-credentials-nokeys.php`, `backups-credentials-exact.php`, `backups-credentials-hardening.php` y, en Playground, S9 de `tests/playground/backups-security.sh` |
| **Pasos cortos dentro de la exportación, el empaquetado y la verificación (T2).** Para la pila del propietario (OpenLiteSpeed con 30 s y 64 MB, detrás de Cloudflare) sin WP-CLI ni cron del sistema. «database», «pack» y «verify» avanzan por tramos: cada uno trabaja hasta el fin del presupuesto del tick (la mitad de `max_execution_time`, entre 5 y 20 s, en la web), nunca más allá del límite real de la petición menos un margen (el `max_execution_time` leído antes de que el motor llame a `set_time_limit(0)`; `digitalisimo_backups_request_limit` para un proxy), se detiene antes si la memoria llega al 75 % de `memory_limit` y guarda su punto de control en el trabajo (`checkpoint.<fase>.resume`; el empaquetado y la verificación, al menos cada 5 s). Tras una muerte (tiempo o memoria agotados, proceso matado, recarga del servidor web) el tramo siguiente trunca lo escrito después del punto de control y sigue desde ahí; trabaja la mitad (y pide la mitad de filas) y vuelve a crecer al avanzar, y los intentos de una fase cuentan fallos seguidos sin avanzar, no tramos. **Exportación:** por tablas y, dentro de cada una, por lotes en el orden de la clave primaria con paginación por clave (`WHERE clave > última`; compuestas, de texto y binarias, comparadas según su tipo), sin repetir ni saltar filas; el tamaño del lote se adapta al de las filas y a la memoria libre. Cada tramo abre su instantánea (InnoDB, bloqueo de lectura de las tablas no transaccionales o la transacción de SQLite). Coherencia: si la exportación cabe en un tramo (siempre en WP-CLI y en el cron del sistema por PHP CLI, que no tienen plazo) es la instantánea única de siempre; si no, cada tramo es coherente por dentro, el manifiesto lo declara (`database_consistency`: `transaction-steps` o `lock-steps`) y el trabajo avisa de que entre tramos no hay una instantánea común. El SQL es idéntico byte a byte al del exportador de un paso, con las defensas de S4 (lo que quede en claro se cifra antes de cada tramo y cada fila pasa por `export_row()`). **Empaquetado:** escritor ZIP propio de sólo añadir (`Digitalisimo_Backups_Zip_Writer`) en lugar de `ZipArchive::close()`, que escribe todo el archivo de una vez y no se puede partir: inventario por carpetas, con su cola y su lista de archivos en el área de trabajo; cada entrada terminada se anota con su posición, CRC, tamaños y SHA-256; una entrada grande queda a medias entre tramos (deflate termina el tramo con un vaciado completo y el siguiente sigue con un compresor nuevo; el estado de CRC-32 y SHA-256 se guarda serializado o, en PHP 7.4, se reconstruye leyendo lo escrito), y lo ya escrito no se reescribe (sólo los 12 bytes de CRC y tamaños de la cabecera de la entrada abierta, al terminarla). Lo que ya viene comprimido (JPEG, PNG, WebP, MP4, ZIP, fuentes…) y lo de 2 GiB o más se guarda sin comprimir; Zip64 sólo cuando hace falta. Un archivo que desaparece después del inventario se omite con aviso; uno obligatorio hace fallar la fase. El manifiesto v3 se escribe por partes desde la lista de entradas (los mismos bytes que `wp_json_encode( …, JSON_PRETTY_PRINT )`, con `packer: digitalisimo-zip/1`) y su firma se calcula a la vez (la misma que `sodium_crypto_auth`). **Verificación por tramos:** estructura con ZipArchive (CHECKCONS), número de entradas y firma; después cada entrada contra su CRC, sus tamaños y su SHA-256 (las de 256 MB o más, por partes y retomables); el manifiesto entero sólo se decodifica si cabe con holgura en la memoria libre, y al registrar el respaldo uno de más de 1 MB se lee por partes (con 64 MB, uno de ~17 000 entradas ya no cabía entero: hallazgo H1 del laboratorio). El sellado DGBK sigue al final de «verify». Mismo motor para la pantalla, WP-Cron, `POST /jobs/tick` y WP-CLI. El formato es el de siempre: lo restauran sin cambios el código actual, 1.0.58 y 1.0.60, también con Zip64 y cifrado. | @NEXT_BACKUPS@ | `backups-steps.php`, `backups-steps-compat.php` y, en Playground, `tests/playground/backups-steps.sh` |

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

Ensayo de las credenciales de los destinos (@NEXT_BACKUPS@, S9 de `tests/playground/backups-security.sh`: 471 s junto con
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
| Trabajos | Hecho en 1.0.59 con reanudación por fase (T1) y en @NEXT_BACKUPS@ con tramos cortos dentro de la exportación, el empaquetado y la verificación (T2). Queda de T2 el sellado DGBK, que sigue siendo un paso por respaldo (con libsodium nativo cifra y comprueba unos 200 MB en pocos segundos; con sodium_compat, unos 11 s por MiB en php-wasm, no cabe en 30 s), y repetir en un hosting con MySQL (webuzo-lab) el ensayo de Playground con 30 s y 64 MB. Falta **T3**: transferencias reanudables por fragmentos (SFTP por desplazamiento, sesiones de Google y OneDrive guardadas de forma privada) y retirar los temporales SFTP que deja un proceso muerto; hoy una cancelación pedida durante una subida SFTP llega cuando la copia ya terminó, y el respaldo se completa. Desde 1.0.60, REST y WP-CLI consultan, hacen avanzar y cancelan la misma cola (`wp digitalisimo-backups jobs cancel`, `POST /jobs/<id>/cancel`). Ensayo real pendiente: `kill -9` en un hosting con MySQL (en Playground, T2 ya mata peticiones a mitad de tramo con 30 s y 64 MB). Una restauración interrumpida ya no pierde el historial, la seguridad ni el llavero (diario del almacén), pero deja el sitio a medias: hay que restaurar la copia previa a mano, escribiendo la frase si el llavero volvió sin su envoltura. Desde T2 el empaquetado ya no depende del aviso de avance de `ZipArchive::close()`: late y guarda su punto de control al menos cada 5 s, así que otro servidor sin `flock` común ya no lo toma por muerto mientras avanza; un tramo que otro servidor retomó empieza su fase de nuevo (sólo se retoma un punto de control escrito en el mismo servidor). |
| Restauración | Preflight completo, espacio libre, staging de toda la instalación, mantenimiento, rollback atómico y migración extremo a extremo a otro servidor o dominio con datos serializados. |
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
| `includes/class-backups-backup-job.php` | `Digitalisimo_Backups_Backup_Job`: fases del respaldo con su punto de control (`database`, `pack` y `verify` por tramos, con `checkpoint.<fase>.resume`) y `run_inline()` para la copia previa de una restauración. |
| `includes/steps/class-step-budget.php` | `Digitalisimo_Backups_Step_Budget` (T2): plazo de cada tramo (presupuesto del tick, mínimo para avanzar y límite real de la petición con margen), memoria libre frente a `memory_limit`, escala adaptativa tras una muerte y medida de cada tramo. |
| `includes/steps/class-sql-export.php` | `Digitalisimo_Backups_Sql_Export` (T2): fase «database» por tablas y lotes con paginación por clave primaria, una instantánea por tramo y el modo de coherencia que declara el manifiesto. |
| `includes/steps/class-zip-writer.php` | `Digitalisimo_Backups_Zip_Writer` (T2): escritor ZIP de sólo añadir; entradas que se suspenden y retoman (vaciado completo de deflate y estado del hash), Zip64 cuando hace falta y directorio central desde la lista de entradas. |
| `includes/steps/class-packer.php` | `Digitalisimo_Backups_Packer` (T2): fase «pack» (inventario por carpetas, escritura y cierre con el manifiesto y su firma por partes) y verificación por tramos de «verify». |
| `includes/security/class-sodium.php` | libsodium nativo o el `sodium_compat` de `wp-includes`; qué funciones faltan. |
| `includes/security/class-keys.php` | Constante, PBKDF2, huella y subclaves (BLAKE2b con separación de dominio), llavero envuelto, clave y descriptor por huella, unión tras restaurar. |
| `includes/security/class-credentials.php` | `Digitalisimo_Backups_Credentials`: almacén cifrado de las credenciales de los destinos (`digitalisimo_backups_credentials`), clave desde AUTH_KEY y SECURE_AUTH_KEY (también desde valores dados), lectura de las salts literales del `wp-config.php` de un respaldo (hasta 64 KiB), contexto de conexión del dato adicional, nuevo cifrado tras restaurar, claves del origen para el diario, «Olvidar», adopción al convertir en red y estado público sin valores. |
| `includes/security/class-manifest-auth.php` | Bloque `auth` del manifiesto, comentario `DIGITALISIMO-AUTH/1` y estados de la firma. |
| `includes/security/class-encrypted-file.php` | Contenedor DGBK v1: cabecera, cifrado y descifrado por bloques con memoria constante. |
| `includes/security/class-archive-inspector.php` | Reglas de entradas, esquema estricto del manifiesto v3 y espacio en disco. |
| `includes/security/class-sql-guard.php` | Divisor de sentencias por fragmentos y lista blanca de los dialectos `exporter` y `mysqldump`. |
| `includes/security/class-security.php` | Costuras `seal()` (la llama la fase «verify» de un trabajo cifrado) y `open()`; `job_settings()` y `job_plan()`, el cifrado y la huella que fija la especificación de un trabajo y la clave que cada fase abre por esa huella; nivel de confianza y confirmación, pestaña «Seguridad» y sus acciones `admin-post`. |
| `assets/manual-progress.js` | Encola, hace avanzar la cola con la pantalla abierta, pinta su estado como texto y cancela. |
| `includes/class-digitalisimo-core.php` | Integra el menú DIGITALÍSIMO sin exigir otro plugin. |
| `includes/class-digitalisimo-updater.php` | Actualizador por `Update URI` e índice de la Release, independiente de los demás plugins. |
| `includes/trait-backups-service.php` | `Digitalisimo_Backups_Service`: casos de uso compartidos por la pantalla, REST y WP-CLI (estado, historial, crear y ejecutar en cola, trabajos, verificar y restaurar con «Seguridad», y automatizaciones), con el alcance de sitio o red y el bloqueo del almacén. |
| `includes/class-backups-rest.php` | `Digitalisimo_Backups_Rest`: rutas `digitalisimo-backups/v1` con la capability de la pantalla; la frase de un respaldo, sólo en el cuerpo de la petición. |
| `includes/class-backups-cli.php` | `wp digitalisimo-backups`, `wp digitalisimo-backups automation` y `wp digitalisimo-backups jobs`; hace avanzar la cola en su proceso y lee la frase de un archivo o de STDIN. |
| `includes/class-backups-layout.php` | `Digitalisimo_Backups_Layout`: carpetas de WordPress que un respaldo completo debe contener; rechaza las externas o enlazadas antes de exportar. |
| `assets/backups-admin.css` | Presentación propia sobre el diseño compartido `digitalisimo-ui/`. |

La configuración y el historial viven en opciones del sitio o de la red; el estado de los trabajos, en el almacén
privado. Hay acciones `admin-post`, eventos WP-Cron, creación manual y políticas automáticas; destinos local, SFTP,
Google Drive y OneDrive; e importación de ZIP propio y de conjuntos Updraft. Los formatos heredados (copia de base de
datos, copia por sitio, Updraft) siguen inventariados, pero no satisfacen el requisito de respaldo completo y no se
presentan como verificados.

Riesgos conocidos del flujo actual:

1. Desde T2 la exportación, el empaquetado y la verificación avanzan por tramos cortos, pero una base que no cabe en
   un tramo se exporta en varias instantáneas (cada una coherente; declarado en el manifiesto y en el trabajo): para una
   sola instantánea hay que usar WP-CLI o el cron del sistema por PHP CLI. Una tabla sin clave primaria no se puede
   partir y se exporta entera dentro de un tramo; el sellado DGBK sigue siendo un paso; y la restauración sigue siendo
   una sola petición larga (fuera de T2).
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
| `GET /status` | `wp digitalisimo-backups status [--format=json]` | Si la instalación puede respaldarse completa (y por qué no), operación y trabajos en curso, ubicación de `wp-config.php`, destinos, cifrado, «sólo firmados», origen de la clave (`wp-config`, `passphrase` o `none`), credenciales de los destinos (`credentials`: `none`, `encrypted`, `pending`, `unprotected` o `needs_reentry`, y `credentials_notice` con qué hacer, nunca un valor), último respaldo y automatizaciones. |
| `GET /backups` | `wp digitalisimo-backups list [--fields=…] [--format=…]` | Historial, del más reciente al más antiguo: tamaño, SHA-256, destino, alcance, contenedor (`zip` o `dgbk`), firma, huella corta de la clave, si falta la clave (`needs_secret`), bloqueo y si es restaurable aquí. |
| `POST /backups` (`destination`, `network`) | `wp digitalisimo-backups create [--destination=…] [--network] [--async] [--porcelain]` | Encola el respaldo completo de la instalación o de la red. REST responde 202 con el trabajo; WP-CLI lo hace avanzar hasta que queda verificado (y copiado, con destino remoto) o, con `--async`, sólo lo encola. |
| `GET\|POST /backups/<archivo>/verify` (`secret`) | `wp digitalisimo-backups verify <archivo> [--secret-file=…\|--secret-stdin]` | Sin tocar el sitio, las mismas comprobaciones del paquete que la restauración: SHA-256 del historial, firma o registro, descifrado completo de un `.dgbk`, entradas, CRC y SHA-256 de cada una contra el manifiesto, manifiesto v3 estricto y el SQL en seco. Informa la confianza, la confirmación que pedirá y si las condiciones de esta instalación (red o sitio, prefijo) lo admiten. |
| `POST /backups/<archivo>/restore` (`confirm`, `network`, `secret`, `adopt_secret`) | `wp digitalisimo-backups restore <archivo> --confirm=… [--network] [--secret-file=…\|--secret-stdin] [--adopt-secret]` | Restauración con la copia previa bloqueada y la recuperación del motor. `RESTAURAR` para un archivo firmado o registrado aquí; `RESTAURAR SIN FIRMA` para uno sin firma ni registro. Con `adopt_secret` o `--adopt-secret`, la frase escrita firma y cifra los respaldos nuevos. |
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
(45 en el sitio individual y 54 en la red, 395 s). El ensayo corta el loopback de WP-Cron (un mu-plugin de `setup.php`)
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

Desde @NEXT_BACKUPS@. Decisión del propietario (2026-11-08): un respaldo restaura el sitio **completo**, también las
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
