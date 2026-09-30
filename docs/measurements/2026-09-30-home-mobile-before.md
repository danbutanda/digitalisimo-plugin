# Portada móvil antes de instalar SEO 1.0.148

Medición de laboratorio local con Lighthouse y Chromium headless el 2026-09-30 21:05:49 UTC. URL final `https://digitalisimo.mx/`. El sitio público todavía reportaba DIGITALÍSIMO SEO 1.0.147 mediante la API de plugins de WordPress. [Captura final](2026-09-30-home-mobile-before.jpg).

| Métrica | Resultado |
| --- | ---: |
| Performance | 73 |
| FCP | 1.6 s |
| LCP | 2.1 s |
| TBT | 960 ms |
| CLS | 0.005 |
| Speed Index | 5.1 s |
| Respuesta documento raíz | 1,052 ms |

Comando reproducible:

```bash
CHROME_PATH=/opt/digitalisimo/playwright-browsers/chromium-1243/chrome-linux64/chrome npx --yes lighthouse https://digitalisimo.mx/ --chrome-flags='--headless --no-sandbox --disable-dev-shm-usage' --form-factor=mobile --only-categories=performance --output=json --output-path=/tmp/digitalisimo-baseline-lighthouse.json --quiet
```

Esta corrida difiere de la referencia PageSpeed proporcionada por el propietario (Performance 61, FCP 3.1 s, LCP 7.4 s, TBT 220 ms, CLS 0). La diferencia puede deberse al momento, contenido, caché, red y entorno de laboratorio; se necesita una corrida comparable después de instalar la nueva versión. No se atribuye ninguna mejora al módulo todavía.
