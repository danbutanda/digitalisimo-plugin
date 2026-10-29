# Evidencia de la fusión de constructores

Informes de la revisión del 16 de octubre de 2026 que sostienen el plan de `docs/FUSION_OBJECTIVE.md`. Son la base para continuar cada incremento: formatos de origen, lo que Elements ya resuelve, riesgos y propuestas de integración con rutas y líneas.

Están **depurados para el repositorio público**:

- Sin código de Betheme (propietario): sus formatos están descritos con palabras propias (clean-room).
- Sin bundles JS de Divi ni bloques de su código: sólo rutas, claves, tablas de datos con atribución y, donde hace falta para la interoperabilidad, algún valor de su CSS (GPL).
- Sin ejemplos copiados de la documentación de Bricks.
- Sin rutas de la máquina, credenciales ni datos de clientes.
- Sin notas sobre la procedencia de las copias de referencia, que no entran en Git.

Cada informe empieza con una nota de estado y termina con «Decisiones posteriores» cuando el plan aprobado cambió una recomendación. **Si un informe y `docs/FUSION_OBJECTIVE.md` no coinciden, manda el objetivo.** Las líneas citadas son de la versión revisada (Elements 4.3.0.156, núcleo 4.3.5, Migración 1.0.18) y pueden moverse.

## Orígenes

| Informe | Qué cubre | Incremento |
|---|---|---|
| [divi.md](divi.md) | Divi 5.15.0: D4 (shortcodes) y D5 (bloques), presets, globales, Theme Builder, JSON de portabilidad, 115 módulos y su mapeo | F3 (y F7a) |
| [betheme.md](betheme.md) | Betheme 29.0.3 / BeBuilder: tres generaciones de `mfn-page-items`, plantillas y condiciones, opciones del tema, demos locales, WooCommerce | F4 (y F7a) |
| [gp.md](gp.md) | GP Premium 2.5.6: `gp_elements`, condiciones, layout por página, Site Library local, módulos | F5 |
| [bricks.md](bricks.md) | Bricks 2.x (sólo documentación pública): árbol plano, clases, variables, componentes, interacciones, consultas y filtros; qué exige muestras | F6 (y F7b) |
| [ep-prime.md](ep-prime.md) | Element Pack Pro 10.0.1 (88 IDs ausentes en 10.0.1, de los que el changelog declara 85 movidos al Addon; 28 extensiones sin tratar) y Prime Slider Pro 4.7.0 | F0.1 y F8 |

## Destino y orquestación

| Informe | Qué cubre | Incremento |
|---|---|---|
| [elements-diseno.md](elements-diseno.md) | Sistema de diseño de Elements: kit, Default Styles, clases y variables v4, componentes, Global Widget, precedencia CSS, contratos v3/v4 | F2, F7a, F7b |
| [elements-runtime.md](elements-runtime.md) | Runtime: Theme Builder y prioridades, condiciones, popups, interacciones, etiquetas dinámicas, consultas y filtros, recursos, importador de kits y plantillas | F2, F7a, F7b |
| [migracion.md](migracion.md) | DIGITALÍSIMO Migración 1.0.18: etapas, cerrojo, red, informe, respaldo, DSL de mapas; propuesta de drivers y Migración v2 | F2 |
| [tema.md](tema.md) | Tema propio mínimo sin hijo: archivos, ubicaciones, estilos base, respaldos sin Elements, publicación y actualizador, paso desde Hello | F1 (y F1b) |
| [verificaciones.md](verificaciones.md) | Lo comprobado en el código por las críticas del plan y cómo se resolvió cada punto | Todos |

Desde el cambio de alcance del 20 de octubre de 2026, los informes de Divi, Betheme, GP y Bricks sólo sirven a las capacidades que se integran en Elements (F7a, F7b y F4-Woo): ya no se importan ni convierten esos orígenes.

## Kits de Elementor (K)

| Informe | Qué cubre | Incremento |
|---|---|---|
| [k-kits.md](k-kits.md) | Diseño de K: hechos del núcleo con rutas y líneas, formato del kit, modos y estados, contextos y ganchos, seguridad de las entradas, runners, remapeo, kits de terceros, marcador `digitalisimo-unconverted` y registro extensible | K (y F7a, F7b, F4-Woo, F8) |
| [k-envato.md](k-envato.md) | Template Kits de Envato: estado (sintético), lecturas a confirmar y procedimiento con kits reales, que nunca entran en Git | K |

La evidencia de Playground de K se guarda en `k-kits-evidence.json`, el `summary.json` de `tests/playground/kit-roundtrip.sh`.

## Capacidades de Divi y Betheme en Elements (F7a)

| Informe | Qué cubre | Incremento |
|---|---|---|
| [f7a.md](f7a.md) | Diseño de F7a: comportamientos tomados de los orígenes (clean-room), hechos del núcleo con rutas y líneas, guardas, formato de la opción de presets, aplanado, biblioteca de formas y teselas, las siete condiciones, el centinela, la corrección de la caché v4, el consentimiento de «Visitó una página», ganchos y filtros nuevos, desviaciones de cada frente y límites | F7a (y K) |

La evidencia de Playground de F7a irá a `f7a-evidence.json`, con los `summary.json` de `tests/playground/design.sh` y de las ampliaciones de `front-budget.sh`, `conditions.sh`, `kit-roundtrip.sh` y `brand-audit.sh`.

## WooCommerce visual en Elements (F4-Woo)

| Informe | Qué cubre | Incremento |
|---|---|---|
| [f4woo.md](f4woo.md) | Diseño y evidencia de F4-Woo: hechos de WooCommerce 11.2.1 y de la WP Consent API verificados con archivo y línea (frente 0), la matriz corregida de V.2, la regla de eventos, el contrato del coordinador de filtros con el paquete de PRO (y su arranque), los formatos y ganchos nuevos, la privacidad de la lista de deseos, el presupuesto medido de los doce casos y las desviaciones de la especificación | F4-Woo (y K, F8) |

La evidencia de Playground de F4-Woo está en `f4woo-evidence.json`: el estado, los recuentos y la duración de `tests/playground/woo-flow.sh` (W0–W13, también `NET=1` y `CORE=plugin`), de `front-budget.sh WOO=1` en sus cuatro modos, de `kit-roundtrip.sh WOO=1` y de los arneses de regresión.

## Sin rastro de los plugins de origen (F0.2, resuelto)

| Informe | Qué cubre |
|---|---|
| [origen-cuentas-servicios.md](origen-cuentas-servicios.md) | Cuentas, Connect, biblioteca remota, kits, nube, IA, Angie, telemetría y peticiones del núcleo incluido |
| [origen-enlaces-videos.md](origen-enlaces-videos.md) | Enlaces, vídeos y accesos de cuenta visibles en la administración, el editor y la App |
| [origen-otros-plugins.md](origen-otros-plugins.md) | Textos y enlaces hacia los plugins de origen en SEO, IA, Hosting, Backups, Tools, Migración y los textos propios de Elements |
| [origen-verificacion.md](origen-verificacion.md) | Diseño de la verificación automática: red del navegador, DOM, servidor, cron, estados y exclusiones |

Estos cuatro siguen siendo útiles para repetir la revisión en cada `sync-elementor-core.sh` y para añadir los dominios de Divi, Betheme, GeneratePress y Bricks a `tests/playground/brand-audit/vendors.json` cuando entren en Migración.

## Reglas al ampliar esta carpeta

- Nada de código de Betheme ni de Bricks, ni de bundles de Divi; formatos descritos con palabras propias, rutas y claves.
- Las muestras reales del usuario no entran en Git: aquí sólo su descripción; los fixtures que lleguen a `tests/sources/` serán sintéticos o anonimizados.
- Ningún dato de clientes, credenciales ni rutas de la máquina.
- Un informe nuevo se añade a este índice y se enlaza desde el incremento que lo usa en `docs/FUSION_OBJECTIVE.md`.
