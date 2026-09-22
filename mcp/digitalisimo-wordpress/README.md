# MCP de Digitalisimo para WordPress

Este servidor MCP usa los endpoints de **Digitalisimo IA Tools** para listar clusters SEO y guardar como borradores los artículos redactados por Codex en esta sesión. Nunca publica contenido.

## Requisitos

- WordPress con Digitalisimo IA Tools 1.0.22 o superior y Digitalisimo SEO activos.
- Un usuario de WordPress con permiso para editar entradas.
- Una Application Password creada para ese usuario en **Usuarios → Perfil → Contraseñas de aplicación**.
- No requiere API key ni proveedor IA: Codex redacta el contenido antes de enviarlo.
- Node.js 20 o superior en el equipo que ejecuta Codex.

## Instalación

```bash
cd /ruta/al/repositorio/mcp/digitalisimo-wordpress
npm install
```

Configura estas variables sólo en el entorno local de Codex. No se guardan en WordPress ni en el repositorio:

```bash
export DIGITALISIMO_WP_URL="https://tu-sitio.com"
export DIGITALISIMO_WP_USERNAME="usuario-editorial"
export DIGITALISIMO_WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
```

## Configuración MCP

Añade este servidor a la configuración MCP de Codex, usando rutas absolutas y las mismas variables de entorno:

```json
{
  "mcpServers": {
    "digitalisimo-wordpress": {
      "command": "node",
      "args": ["/ruta/al/repositorio/mcp/digitalisimo-wordpress/server.mjs"],
      "env": {
        "DIGITALISIMO_WP_URL": "https://tu-sitio.com",
        "DIGITALISIMO_WP_USERNAME": "usuario-editorial",
        "DIGITALISIMO_WP_APP_PASSWORD": "tu-application-password"
      }
    }
  }
}
```

## Flujo editorial

1. Ejecuta `listar_clusters_seo`.
2. Elige el `pillar_id` si el artículo debe reforzar un cluster existente.
3. Pide a Codex redactar el artículo usando el contexto del cluster, con title, description y contenido HTML sin H1: WordPress ya muestra el título de la entrada.
4. Ejecuta `crear_borrador_seo` con ese contenido, `primary_keyword`, secundarias y pilar opcional.
5. Abre `edit_url`, revisa el borrador y publícalo manualmente cuando esté listo.

El borrador guarda título y descripción SEO, keyword principal primero, secundarias, la relación con el pilar elegido y una conversación editorial obligatoria en `digitalisimo_article_chat`. La conversación debe tener varias intervenciones contextualizadas de Empresario, MaryIA y Daniel; no uses tres frases genéricas.
