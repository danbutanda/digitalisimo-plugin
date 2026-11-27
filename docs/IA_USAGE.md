# IA Tools · Registro de uso de IA

Pedido del propietario (2026-11-26): «Hay que crear un registro de uso de tokens en IA Tools para que sepa exactamente cuánto saldo está gastando cada sitio WordPress.»

## Qué se registra y qué no

Cada llamada a un proveedor de IA hecha con una clave de IA Tools deja una fila con: fecha (UTC) y día local del sitio, sitio (`blog_id`), función, usuario (0 = visitante del chatbot público o tarea sin sesión), proveedor, modelo, tokens de entrada cobrados completos, de salida, leídos de la caché, escritos en la caché (y la parte escrita por una hora), imágenes, costo estimado en USD (vacío = «sin precio»), estado HTTP, si fue error y si fue reintento.

Nunca se guardan el texto de la conversación (prompt, contexto ni respuesta), la clave ni datos del visitante (IP, mensaje). De la petición sólo se lee el modelo; de la respuesta, el bloque de uso (`usage`, `usageMetadata`) y el modelo.

## Un solo punto de registro

`Digitalisimo_AI_Usage::capture()` escucha `http_api_debug`, que WordPress dispara después de cada petición de `wp_remote_*()`. Registra la petición si va al dominio de un proveedor (OpenAI, Anthropic, Google, DeepSeek, xAI o la «URL compatible» configurada) **y** lleva una de las claves de IA Tools (Bearer, `x-api-key`, `x-goog-api-key` o `?key=`, comparadas con `hash_equals` y nunca guardadas). Así no depende de que cada función se acuerde de anotar, y lo que se agregue mañana también queda.

Caminos encontrados en IA Tools (todos pasan por ese punto):

| Función | Dónde llama | Cómo se sabe la función |
| --- | --- | --- |
| Chatbot público (`/digitalisimo-ai/v1/chat`) | `Digitalisimo_AI::chat()` → `complete( 'chatbot' )` | contexto `begin( perfil )` en `complete()` |
| Asistente editorial (metabox, `/digitalisimo-ai/v1/complete`) | `Digitalisimo_AI::complete_api()` | contexto `editorial` |
| Creación de contenido (publisher REST, playbook, MCP) | `Digitalisimo_AI_Content_Playbook::generate()` → `complete( 'content' )` | perfil `content` |
| SEO (DIGITALÍSIMO SEO: intención, editorial) | `Digitalisimo_AI::complete( 'seo' )` desde el plugin SEO | perfil `seo` |
| Ecommerce y automatizaciones | `complete( 'ecommerce' \| 'automation' )` | perfil |
| Asistente IA (chat del escritorio, con su caché de prompts y los archivos subidos) | `Digitalisimo_AI_Assistant::turn()` → `Assistant_Model::chat()` | filtro `digitalisimo_ai_assistant_pre_model` (cada paso) |
| Aprendizaje del sitio («Entrenar con mi sitio web» y el repaso de conversaciones, v520) | `Digitalisimo_AI_Assistant_Learning::distill()` y `review_locked()` → `Assistant_Model::chat()` | `Digitalisimo_AI_Usage::begin( 'learning' )` / `end()` alrededor de cada llamada |
| Imágenes | `Digitalisimo_AI_Images::generate()` (`/v1/images/generations`) | la ruta `/images/` |
| Base de conocimiento (embeddings al indexar y al buscar) | `Digitalisimo_AI_Knowledge::generate_embeddings()` y `query_embedding()` | la ruta `/embeddings` |
| Cualquier otra llamada con la clave de IA Tools | — | «Otra función» |

`tests/ia-usage.php` comprueba que toda llamada a `Assistant_Model::chat()` fuera del chat del Asistente esté entre `begin()` y `end()`: un camino nuevo que se olvide de abrir su contexto rompe la prueba (si no, quedaría como «Asistente IA»). La caché del Asistente (v518) y las subidas de archivos no llaman a ningún proveedor. Una función que el Asistente dispara desde una herramienta (p. ej. crear contenido con `complete( 'content' )`) cuenta con la función de esa herramienta.

Dentro de un mismo contexto, la segunda petición y las siguientes son **reintentos** (el cambio a otro proveedor de `complete()` tras un error; la herramienta que el Asistente quita y repite).

No se registran las llamadas del cliente de IA propio de DIGITALÍSIMO SEO (`Digitalisimo_SEO_AI_Client`) con sus propias claves, ni las de otros plugins: no gastan el saldo de IA Tools. Si usan la misma clave, sí cuentan (gastan el mismo saldo) y aparecen como «Otra función».

## Costo

`Digitalisimo_AI_Usage_Prices` trae los precios de fábrica (USD por millón de tokens; imagen por imagen) con fuente y fecha, y el administrador los corrige o agrega en «Uso de IA → Ajustes → Precios» (en una red, la red y cada sitio que no herede). Lo que difiere de lo heredado se guarda como corrección; un modelo sin precio se registra igual con costo vacío y la pantalla lo avisa. Al agregar su precio, sus llamadas sin precio se calculan (las que ya tenían costo conservan el precio con el que se registraron).

Fórmula: entrada × precio de entrada + salida × precio de salida + caché leída × precio de lectura + (escrita 5 min) × precio de escritura + (escrita 1 h) × precio de 1 h; en modelos de imagen sin tokens, imágenes × precio por imagen. Haiku 5.5, Gemini 2.5 Pro y Grok pasan a su precio largo cuando la entrada (con la caché) supera su umbral. Un precio que falta se toma del de entrada.

| Proveedor · modelos | Entrada / salida / caché leída / escrita 5 min / 1 h | Fuente y fecha |
| --- | --- | --- |
| Claude Opus 5.5 | 4 / 20 / 0.20 / 5 / 8 | platform.claude.com/docs/en/about-claude/pricing, 2026-11-26 (página oficial) |
| Claude Sonnet 5.5 | 2 / 10 / 0.10 / 2.50 / 4 | ídem |
| Claude Haiku 5.5 (≤ 100 000 / más) | 0.10 / 0.50 / 0.01 / 0.125 / 0.20 · 0.50 / 2.50 / 0.05 / 0.625 / 1 | ídem |
| Claude Fable 5.2 · Mythos 5.2 | 8 / 40 / 0.20 / 10 / 16 | ídem |
| Fable 5.1 · Mythos 5.1 / Fable 5 · Mythos 5 | 10 / 50 / 0.25 (5: 1) / 12.50 / 20 | ídem |
| Opus 5, 4.8, 4.7, 4.6, 4.5 / Opus 4.1 | 5 / 25 / 0.50 / 6.25 / 10 · 15 / 75 / 1.50 / 18.75 / 30 | ídem |
| Sonnet 5 / Sonnet 4.6, 4.5, 4 / Haiku 4.5 | 2 / 10 / 0.20 / 2.50 / 4 · 3 / 15 / 0.30 / 3.75 / 6 · 1 / 5 / 0.10 / 1.25 / 2 | ídem |
| gpt-4o-mini · gpt-4o · gpt-4.1 · gpt-4.1-mini | 0.15/0.60/0.075 · 2.50/10/1.25 · 2/8/0.50 · 0.40/1.60/0.10 | developers.openai.com/api/docs/pricing, 2026-11-26 (por búsqueda: la página no se dejó leer; confirmar) |
| text-embedding-3-small / -large | 0.02 / 0.13 (sólo entrada) | ídem |
| gpt-image-1 · -1-mini · -1.5 · -2 (texto / imagen de salida / por imagen) | 5/40/0.063 · 2/8/0.015 · 5/32/0.05 · 5/30/0.041 | ídem; gpt-image-2 con texto de listados de terceros (confirmar) |
| gemini-2.5-flash · -flash-lite · -pro (≤ 200 000 / más) | 0.30/2.50/0.03 · 0.10/0.40/0.01 · 1.25/10/0.125 y 2.50/15/0.25 | ai.google.dev/gemini-api/docs/pricing, 2026-11-26 (por búsqueda; confirmar) |
| deepseek-flash · deepseek-v4-pro | 0.30/1.20/0.006 · 1.32/3.96/0.044 (horas pico; fuera de pico, la mitad) | api-docs.deepseek.com/quick_start/pricing, 2026-11-26 (página oficial) |
| grok-4.7 · 4.6 · 4.5 · 4.3 (< 200 000 / más) | 2/6/0.50 · 2/6/0.50 · 2/6/0.30 · 1.25/2.50/0.20, el doble en el tramo largo | docs.x.ai/docs/models, 2026-11-26 (página oficial) |

Sin precio de fábrica (la página oficial ya no los lista): `deepseek-chat` (modelo por defecto de IA Tools para DeepSeek), `grok-3-mini` y `grok-4` (los de xAI), `gpt-image-2.5-*`. Quien los use verá el aviso «sin precio» hasta escribir el suyo.

No se cobran aparte: modo rápido, `inference_geo` y Batch de Anthropic (IA Tools no los usa), búsquedas web del servidor.

## Almacenamiento

Dos tablas propias de cada sitio (`{prefijo}digitalisimo_ai_usage` y `…_daily`), creadas con `dbDelta` la primera vez que hacen falta (`Digitalisimo_AI_Usage_Store::ensure()`, marca con autoload). El detalle tiene índices por día y fecha y se conserva `retention_days` (90 por omisión; tarea diaria `digitalisimo_ai_usage_purge`). El resumen por día, función, proveedor, modelo y usuario se suma al escribir (`ON DUPLICATE KEY UPDATE`) y no se borra: de él salen los totales, los desgloses, el presupuesto y la Ability. Escribir son dos INSERT tras una llamada que tarda segundos; una petición HTTP ajena no hace ninguna consulta.

## Pantalla, red y paridad

**Digitalisimo → Uso de IA** (`manage_options`), escrita para el dueño, sin jerga:

- arriba, «Gastado este mes» en grande (dólares y, si hay tipo de cambio, «unos MX$…»), hoy y el mes pasado, el tope si lo hay y de dónde salen los pesos;
- botones Este mes / Hoy / Últimos 7 días / El mes pasado y fechas libres;
- «¿En qué se gastó?»: una fila por función con su nombre, para qué sirve en una línea, el gasto, en pesos, la parte del total y las consultas; debajo, el gasto por día;
- «Detalle técnico» (plegado): tokens por función, modelo y usuario, las llamadas más caras y los CSV (resumen diario y detalle);
- «Ajustes» (plegado): tope, tipo de cambio, retención, borrar al desinstalar y precios.

En la red (Red → Digitalisimo → Uso de IA, superadministrador): lo gastado en toda la red, «¿Cuánto gastó cada sitio?» de mayor a menor con enlace a su pantalla, «¿En qué se gastó en toda la red?» y, plegados, el detalle técnico (por sitio con tokens, por función y por modelo, CSV por sitio) y los ajustes de la red. Cada sitio lee sólo sus tablas.

Los ajustes (presupuesto, tipo de cambio, retención, borrar al desinstalar y precios) son los mismos campos en las dos superficies (`settings_fields()`) con una sola validación (`sanitize_settings()`); «Heredar los ajustes de uso de la red» existe sólo en el sitio y un sitio que nunca guardó hereda. En una red los cambia sólo el superadministrador: el administrador de un sitio los ve sin poder cambiarlos (el tope de red lo define el superadministrador).

Tipo de cambio (`Digitalisimo_AI_Usage::mxn_info()`), sin consultar nada por la red: el escrito en Ajustes (el de la red si el sitio hereda, y en la pantalla de la red); si no hay, el último que guardó DIGITALÍSIMO Hosting al actualizar precios de dominios (`digitalisimo_hosting_price_history`: FIX de Banxico o su respaldo Frankfurter) en el sitio o en el principal de la red, de hasta 90 días y entre 5 y 100 pesos (una tienda en dólares guarda 1 y no cuenta); al final, el filtro `digitalisimo_ai_usage_mxn_rate`. Sin ninguno, sólo dólares, y la pantalla dice cómo ver pesos.

## Presupuesto

Opcional, apagado de fábrica: tope mensual en USD desde el día 1 (hora del sitio). Al cruzar el 80 % y el 100 %: aviso en el escritorio por el ayudante común `Digitalisimo_Notices` (con la X; cerrado no vuelve en el mes mientras siga en el mismo nivel) y un correo al administrador o al correo elegido, programado en una tarea aparte (`digitalisimo_ai_usage_alert`) para que el chatbot no espere al servidor de correo. Al 100 %, según el ajuste: sólo avisar, o pausar las funciones de IA del sitio: `pre_http_request` corta la llamada antes de salir (sin costo) con el mensaje «Las funciones de IA de este sitio están en pausa…», y el Asistente IA lo dice en su chat. Se reanuda el mes siguiente o al subir el tope.

## Lectura desde fuera (MCP)

Ability `digitalisimo-ia/usage` (herramienta MCP `digitalisimo-ia-usage`; sólo lectura, `manage_options`, pública para MCP y en el servidor MCP de Elements): `resumen` (el gasto en una frase: el mes, hoy, en qué se fue, el tope y de dónde salen los pesos), totales por periodo, desglose por función y modelo del rango (`from`, `to`), modelos sin precio, el tipo de cambio y su fuente y el estado del presupuesto. Sólo lee las tablas de resumen: nunca devuelve claves, conversaciones ni datos de visitantes. Así el dueño puede preguntarle a Claude «¿cuánto ha gastado el plomerito con el asistente?».

## Desinstalar

`uninstall.php` quita siempre las tareas programadas. Las tablas, los ajustes y las correcciones de precios del registro sólo se borran donde está marcado «Borrar el registro de uso al desinstalar» (de fábrica no: borrar y volver a instalar no pierde el historial).

## Pruebas

- `tests/ia-usage.php` (CI): costo con caché, cada función por el único punto (también `distill()` real del aprendizaje y la regla estática de `begin()`/`end()`), resumen, aislamiento entre sitios, permisos, la pantalla en lenguaje llano (lo técnico sólo en «Detalle técnico»), el tipo de cambio de Hosting, el `resumen` de la Ability, presupuesto y pausa con el aviso común, CSV, sin contenido ni claves, paridad, precios y retención.
- `tests/playground/ia-usage.sh`: red real de Playground (también el aprendizaje, y guarda `pantalla-sitio.html`, `pantalla-red.html` y `ability.json`); `MODE=real` con la API de Anthropic (no va en la CI; la clave por STDIN, como `ia-assistant-real.sh`).
