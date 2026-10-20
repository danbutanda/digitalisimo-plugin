# MCP de DIGITALÍSIMO para WordPress

Servidor MCP local (stdio) que guarda como **borradores** los artículos que redacta el cliente MCP (Codex, VS Code u
otro) en esta sesión. Usa la API editorial de **DIGITALÍSIMO IA Tools** de cada sitio: nunca publica, no llama a un
proveedor de IA y no necesita API key.

## Requisitos

- En cada WordPress: **DIGITALÍSIMO IA Tools** activo (registra las rutas REST de la tabla de abajo). DIGITALÍSIMO SEO
  es recomendable: el borrador guarda title, description y keywords en sus metadatos, y `consultar_sitio` indica si
  el SEO está activo (`seo_enabled`).
- Un usuario con permiso para editar entradas (y `upload_files` para imágenes), sin usar la cuenta del administrador
  principal, con una contraseña de aplicación creada en **Usuarios → Perfil → Contraseñas de aplicación**.
- Node.js 20 o superior en el equipo que ejecuta el cliente MCP.

En Multisite cada sitio expone su propia API con su dominio o ruta, usuario y permisos; no se acepta un `blog_id`
elegido desde la solicitud.

## Instalación

```bash
cd /ruta/al/repositorio/mcp/digitalisimo-wordpress
npm ci
npm test
```

## Configuración

**Un sitio.** Variables del entorno local del cliente MCP (nunca en WordPress ni en el repositorio):

```toml
[mcp_servers.digitalisimo_wordpress]
command = "node"
args = ["/ruta/al/repositorio/mcp/digitalisimo-wordpress/server.mjs"]

[mcp_servers.digitalisimo_wordpress.env]
DIGITALISIMO_WP_URL = "https://tu-sitio.com"
DIGITALISIMO_WP_USERNAME = "usuario-editorial"
DIGITALISIMO_WP_APP_PASSWORD = "xxxx xxxx xxxx xxxx xxxx xxxx"
```

**Varios sitios** (independientes, subdominios o subdirectorios de una red): copia `sites.example.json` a un archivo
privado fuera del repositorio (`chmod 600`) con un alias por sitio y define `DIGITALISIMO_SITES_FILE` con su ruta en
lugar de las tres variables anteriores. Las herramientas aceptan entonces `site` con el alias; con un solo sitio no
hace falta indicarlo.

**Imágenes locales.** Opcionalmente, `DIGITALISIMO_IMAGE_DIR` limita las imágenes que el MCP puede leer a esa carpeta.

Sólo se admiten URL HTTPS sin credenciales ni parámetros (HTTP únicamente para `localhost`), y una redirección de
WordPress se rechaza en lugar de seguirla con la contraseña.

## Herramientas

| Herramienta | Qué hace |
| --- | --- |
| `listar_sitios` | Alias, nombre y URL de los sitios configurados, sin credenciales. |
| `consultar_sitio` | Permisos, SEO activo, tipos permitidos y `article_chat_supported` del sitio. |
| `listar_clusters_seo` | Contenidos pilar y sus artículos relacionados. |
| `crear_borrador_seo` | Crea el borrador con title, description, keywords, pilar, chat editorial y, opcionalmente, slug, canonical, textos sociales, categorías e imagen destacada (`featured_media`). |
| `subir_imagen` | Sube un PNG/JPG/WEBP/GIF (máx. 8 MB, ruta local o base64) a la biblioteca de medios y devuelve su ID para `featured_media`. |
| `subir_imagen_destacada` | Sube una imagen local y la asigna a un borrador existente. Requiere activar la opción MCP de imágenes en IA Tools. |

## Flujo editorial

1. `listar_clusters_seo` y, si el artículo refuerza un cluster, elegir su `pillar_id`.
2. Redactar el artículo con el contexto del cluster: title, description y HTML **sin H1** (WordPress ya muestra el
   título de la entrada).
3. Opcional: `subir_imagen` en el **mismo** sitio y usar el ID devuelto como `featured_media` con su `image_alt`.
4. `crear_borrador_seo` con el contenido, `primary_keyword`, secundarias, pilar y el chat editorial.
5. Abrir `edit_url`, revisar el borrador y publicarlo a mano.

No inventes URL de medios ni IDs de categoría o de pilar: deben existir en el sitio elegido.

## Chat editorial del artículo

El chat se muestra donde el contenido (o la plantilla de entrada) incluya el shortcode literal
`[digitalisimo_article_chat]`. Los mensajes no van como HTML dentro de `content`: se guardan aparte, en el metadato
`digitalisimo_article_chat` (cadena JSON), y se editan después en la caja del editor de IA Tools.

`crear_borrador_seo` exige `digitalisimo_article_chat` con al menos tres personajes y seis mensajes contextualizados
de Empresario, MaryIA y Daniel; no uses frases genéricas. Si un cliente antiguo llama a la API sin chat, IA Tools crea
una conversación de respaldo con el título y la keyword.

```json
{
  "speakers": [
    {"id": "empresario", "name": "Empresario", "icon": "🧑", "image_id": 0, "align": "start"},
    {"id": "maryia", "name": "MaryIA", "icon": "🤖", "image_id": 0, "align": "center"},
    {"id": "daniel", "name": "Daniel", "icon": "👨‍💼", "image_id": 0, "align": "end"}
  ],
  "messages": [
    {"speaker": "empresario", "text": "Mi página ya no explica bien lo que vendemos."},
    {"speaker": "maryia", "text": "Comparé el catálogo y el texto: hay servicios desactualizados."},
    {"speaker": "daniel", "text": "Conservamos lo que todavía atrae clientes y actualizamos lo que ya cambió."}
  ]
}
```

`align`: `start` izquierda, `center` centro, `end` derecha. Cada `messages[].speaker` apunta a un `speakers[].id`
único; `image_id: 0` usa el emoji y, si hay foto, debe ser un ID real del mismo sitio.

## API de WordPress

Rutas relativas a `/wp-json/` que registra IA Tools (autenticación estándar de la REST API con contraseña de
aplicación):

| Ruta | Archivo de IA Tools | Uso |
| --- | --- | --- |
| `GET digitalisimo-publisher/v1/site` | `includes/class-digitalisimo-ai-content-publisher.php` | Identidad, permisos y capacidades del sitio. |
| `POST digitalisimo-publisher/v1/articles` | `includes/class-digitalisimo-ai-content-publisher.php` | Crea sólo borradores (`status` distinto de `draft` se rechaza). Requiere `edit_posts` y comprueba el permiso por tipo, medio y categoría. |
| `GET digitalisimo-mcp/v1/clusters` | `includes/class-digitalisimo-mcp-articles.php` | Pilares y artículos relacionados. |
| `POST digitalisimo/v1/import-image` | `includes/class-content-automation.php` | Imagen destacada de un borrador (opción MCP de imágenes). |
| `POST wp/v2/media` | núcleo de WordPress | Biblioteca de medios. |

Cuerpo de `articles`: `post_type`, `title`, `slug`, `content`, `seo_title`, `meta_description`, `primary_keyword`,
`secondary_keywords`, `featured_media`, `image_alt`, `category_ids`, `pillar_id`, `canonical`, `social_title`,
`social_description`, `excerpt` y `digitalisimo_article_chat`.

## Seguridad y reversión

Las credenciales sólo viven en el entorno del cliente MCP o en el archivo privado de sitios. Para desactivar el MCP
basta con quitarlo de la configuración del cliente: no hay que tocar WordPress.

Pendiente: un servidor MCP remoto (HTTP con autenticación por instalación) para usarlo desde aplicaciones que no
ejecutan procesos locales, como ChatGPT; este repositorio no lo implementa.
