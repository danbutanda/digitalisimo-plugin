# Digitalisimo para WordPress

Suite modular de plugins WordPress. Cada módulo puede instalarse de forma independiente y, cuando hay varios activos, todos se agrupan bajo el menú administrativo **Digitalisimo** mediante un núcleo interno compartido. No existe un plugin Core adicional que instalar.

## Módulos

- **Digitalisimo SEO**: SEO técnico, contenido, schema, sitemap, SEO AI, rendimiento conservador e importación desde Yoast y Rank Math.
- **Digitalisimo Backups**: respaldos completos e individuales para WordPress y Multisite.
- **DIGITALÍSIMO Tools**: herramientas de administración, plantillas Elementor y optimización WebP.
- **Digitalisimo IA Tools**: proveedores IA, chatbot, conocimiento RAG, asistentes para contenido, SEO y ecommerce, y generación de imágenes.
- **Digitalisimo Hosting**: dominios, Namecheap, WooCommerce y flujos de venta de hosting.

Cada módulo incluye verificación de actualizaciones desde las [Releases del repositorio](https://github.com/danbutanda/digitalisimo-plugin/releases). Sólo se incrementa y publica el ZIP del módulo modificado. Las versiones instalables se generan con `scripts/build-packages.sh`.

## Desarrollo y validación

Consulta [AGENTS.md](AGENTS.md), [docs/PLUGIN_ROADMAP.md](docs/PLUGIN_ROADMAP.md) y el [objetivo de rendimiento](docs/PERFORMANCE_OBJECTIVE.md). El workflow de GitHub valida sintaxis PHP, construye los ZIP y verifica su estructura antes de publicar una Release.
