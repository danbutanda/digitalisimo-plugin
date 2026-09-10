# Digitalisimo · guía de arquitectura y avance

Este repositorio entrega módulos WordPress independientes que comparten un núcleo interno (`Digitalisimo_Core`). No existe un plugin Core que el usuario tenga que instalar: el primer módulo instalado crea el menú **Digitalisimo** y los siguientes se integran en él.

## Reglas de trabajo

- Repositorio oficial de código y Releases: `danbutanda/digitalisimo-plugin`. Los cinco actualizadores nativos consultan este repositorio.

- Cada módulo se instala solo o junto a los demás; nunca debe requerir que otro módulo esté activo salvo para una mejora opcional.
- WordPress Multisite: la red define defaults y cada sitio puede heredar o personalizar. La resolución es `contenido → sitio → red → default`.
- Nunca duplicar title, description, canonical, robots, sitemap o schema en motores paralelos.
- Nunca crear metatags inventados para IA, contenido invisible, modificaciones automáticas de contenido ni promesas de posicionamiento/citación.
- Configuraciones sensibles se protegen con capability, nonce y sanitización. Las tareas remotas se ejecutan en cola, no durante el guardado.
- Al incrementar una versión, mover el ZIP anterior a `rollback/`, actualizar `tests/validate-suite.mjs`, crear el nuevo ZIP y comprobarlo con `unzip -t`.
- No borrar fuentes ni paquetes históricos: usar `rollback/`.

## Módulos

### Digitalisimo SEO

Responsable de las señales SEO públicas: title, description, canonical, robots/noindex, sitemap, schema, keywords, clusters, SEO local, social, importación Yoast/Rank Math y SEO AI.

SEO AI pertenece aquí porque regula descubrimiento, rastreo, interpretación y citabilidad externa. Incluye crawlers, robots.txt, llms.txt, IndexNow, entidades, diagnóstico, auditoría y referencias de tráfico IA.

### Digitalisimo AI y Chatbot

Responsable de la IA interna: proveedores (OpenAI, Anthropic, Google, DeepSeek y xAI), perfiles de uso, chatbot, asistentes editoriales, automatizaciones, imágenes y base de conocimiento del sitio.

La base de conocimiento es RAG, no entrenamiento de modelos: sólo indexa contenido público permitido y lo usa como contexto. No afecta la indexación externa ni transfiere contenido a un proveedor hasta que una función IA configurada por el administrador realiza una consulta.

### Digitalisimo Ecommerce

Responsable de integraciones WooCommerce, productos, carrito, automatizaciones e IA ecommerce. Puede consumir la API interna de conocimiento si AI está activo, sin crear otro índice.

### Digitalisimo Geolocalización

Responsable de ubicación, IPInfo, ubicación administrativa y señales locales reutilizables por SEO Local.

### Digitalisimo Hosting

Responsable de búsqueda de dominios, configuración Namecheap, shortcodes/formularios, precios y el flujo de compra de dominio. Se integra con Ecommerce cuando este existe.

## Estado y prioridad

La matriz operativa está en `docs/PLUGIN_ROADMAP.md`. Antes de empezar un lote, actualizar su estado; al terminar, registrar versión, validación y paquete generado.
