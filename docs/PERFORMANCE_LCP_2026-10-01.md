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
