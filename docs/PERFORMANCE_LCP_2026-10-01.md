# Estabilidad LCP · digitalisimo.mx · 2026-10-01

Se ejecutaron cinco auditorías móviles consecutivas con Lighthouse 13.0.1 y Chrome 153.0.8010.12, emulación móvil y throttling `simulate`. Todas encontraron **Digitalisimo SEO 1.0.176** instalado. Esta es la referencia anterior a desplegar 1.0.177; no valida todavía la corrección publicada.

| Métrica | Mínimo | Máximo | Mediana |
|---|---:|---:|---:|
| FCP | 1619 ms | 2522 ms | 1676 ms |
| LCP | 2047 ms | 4972 ms | 2092 ms |
| TBT | 156 ms | 454 ms | 310 ms |
| CLS | 0.000 | 0.123 | 0.098 |
| H1 render delay | 418 ms | 2196 ms | 428 ms |
| TTFB | 310 ms | 437 ms | 428 ms |
| Source Serif completion | 1051 ms | 1199 ms | 1156 ms |
| Inter completion | 1048 ms | 1233 ms | 1156 ms |
| Critical path latency | 1248 ms | 1377 ms | 1322 ms |

| Ejecución | FCP | LCP | TBT | CLS | H1 render delay | TTFB | Source Serif completion | Inter completion | Critical path latency |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 1657 | 2092 | 214 | 0.098 | 418 | 428 | 1156 | 1156 | 1377 |
| 2 | 2446 | 4913 | 454 | 0.000 | 2196 | 310 | 1199 | 1198 | 1322 |
| 3 | 1676 | 2085 | 156 | 0.098 | 423 | 433 | 1051 | 1048 | 1248 |
| 4 | 1619 | 2047 | 310 | 0.123 | 428 | 437 | 1052 | 1051 | 1263 |
| 5 | 2522 | 4972 | 441 | 0.000 | 2191 | 316 | 1197 | 1233 | 1345 |

Los valores de TTFB y H1 render delay proceden del desglose de LCP de Lighthouse. Las completions de fuentes proceden de `network-requests.networkEndTime`; la latencia de ruta crítica procede de `network-dependency-tree-insight.longestChain.duration`. Son mediciones de laboratorio locales, no puntuaciones PageSpeed Insights en la infraestructura de Google.

La versión 1.0.177 se publicó en GitHub, pero no estaba instalada al terminar estas cinco pruebas. Repetir cinco auditorías equivalentes tras comprobar la versión instalada y la presencia de los preloads y `media="print"` en el HTML público.

## Comprobación posterior con SEO 1.0.181

Cinco nuevas auditorías móviles equivalentes encontraron SEO 1.0.181 instalado. El HTML público emite dos preloads WOFF2 únicos, de Inter y Source Serif 4, antes del primer CSS de Elementor; los cuatro CSS de widgets seleccionados se entregan con `media="print"`. La descarga de ambas fuentes terminó entre 683 y 811 ms, pero el LCP continuó alternando. **El objetivo de estabilidad no está resuelto.**

| Métrica | Mínimo | Máximo | Mediana |
|---|---:|---:|---:|
| FCP | 1767 ms | 2014 ms | 1946 ms |
| LCP | 2926 ms | 4975 ms | 4829 ms |
| TBT | 364 ms | 628 ms | 597 ms |
| CLS | 0.000 | 0.021 | 0.000 |
| H1 render delay | 534 ms | 2209 ms | 2186 ms |
| TTFB | 311 ms | 437 ms | 338 ms |
| Source Serif completion | 685 ms | 811 ms | 708 ms |
| Inter completion | 683 ms | 793 ms | 712 ms |
| Critical path latency | 1203 ms | 1547 ms | 1342 ms |

El H1 es un widget Heading de Elementor en la primera sección (`elementor-element-41420ebb`) sin atributo de animación en su HTML. La demora restante no está explicada por el tiempo de descarga de las fuentes; se necesita inspeccionar la pintura/composición y el CSS efectivo en las ejecuciones lentas antes de atribuirle otra causa. Los JSON de Lighthouse están en `/tmp/digitalisimo-lcp-after-181/` en el entorno de validación.
