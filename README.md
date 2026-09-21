# Digitalisimo para WordPress

Suite modular de plugins WordPress. Cada módulo puede instalarse de forma independiente y, cuando hay varios activos, todos se agrupan bajo el menú administrativo **Digitalisimo** mediante un núcleo interno compartido. No existe un plugin Core adicional que instalar.

## Módulos

- **Digitalisimo SEO**: SEO técnico, contenido, schema, sitemap, SEO AI e importación desde Yoast y Rank Math.
- **Digitalisimo Ecommerce**: funciones e integraciones WooCommerce.
- **Digitalisimo Geolocalización**: ubicación administrativa, IPInfo y señales locales.
- **Digitalisimo IA Tools**: proveedores IA, chatbot, conocimiento RAG, asistentes para contenido, SEO y ecommerce, y generación de imágenes.
- **Digitalisimo Hosting**: dominios, Namecheap y flujos de hosting.

Cada módulo incluye verificación de actualizaciones desde las [Releases del repositorio](https://github.com/danbutanda/digitalisimo-plugin/releases). Las versiones instalables se generan con `scripts/build-packages.sh` y se publican como assets de una Release.

## Desarrollo y validación

Consulta [AGENTS.md](AGENTS.md) y [docs/PLUGIN_ROADMAP.md](docs/PLUGIN_ROADMAP.md). El workflow de GitHub valida sintaxis PHP, construye los ZIP y verifica su estructura antes de publicar una Release.
