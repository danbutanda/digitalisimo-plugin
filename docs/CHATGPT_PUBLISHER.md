# DIGITALÍSIMO Content Publisher — fase 1 (experimental)

Esta primera fase **depende del módulo Digitalisimo SEO**, no del módulo IA Tools, y no llama a OpenAI ni consume tokens por sí misma.

## Instalaciones

La misma clase funciona en WordPress individual y en cada sitio de WordPress Multisite. Cada sitio expone una REST API con su propio dominio/ruta, usuario y permisos. No se admiten cambios de blog_id arbitrarios desde una solicitud.

## API

Autenticación: mecanismo estándar de WordPress REST API (por ejemplo, Application Password por HTTPS y un usuario editorial con permisos mínimos). No registrar contraseñas en código, Git ni logs. No usar credenciales del administrador principal.

- GET `/wp-json/digitalisimo-publisher/v1/site` — identidad del sitio, capacidades y rutas.
- POST `/wp-json/digitalisimo-publisher/v1/articles` — crea **solo borradores** con metadatos nativos de Digitalisimo SEO. Requiere `edit_posts`, y comprueba también permiso por tipo y medio.
- POST `/wp-json/wp/v2/media` — usar endpoint nativo de WordPress para subir la imagen *antes* de crear el artículo. Pasar su ID en `featured_media`, junto con `image_alt`.

Ejemplo de cuerpo para /articles:

```json
{
  "post_type": "post",
  "title": "SEO en Torreón",
  "slug": "seo-en-torreon",
  "content": "<p>Contenido editorial...</p>",
  "seo_title": "SEO en Torreón | DIGITALÍSIMO",
  "meta_description": "Descripción de búsqueda.",
  "primary_keyword": "seo en torreón",
  "secondary_keywords": ["agencia seo torreón"],
  "featured_media": 123,
  "image_alt": "Equipo trabajando en SEO",
  "category_ids": [4],
  "status": "draft"
}
```

No inventar URL de un medio, ID de categoría ni ID de pilar: deben corresponder al sitio seleccionado.

## Chat nativo del artículo (editor WordPress)

El artículo muestra el chat cuando el cuerpo incluye el shortcode **literal** `[digitalisimo_article_chat]`. Los mensajes **no** se insertan como HTML dentro de `content`: se guardan separados en el metadato WordPress `digitalisimo_article_chat` como cadena JSON válida. El editor nativo del plugin puede modificarlos después.

El endpoint `POST /wp-json/digitalisimo-publisher/v1/articles` admite opcionalmente la propiedad `digitalisimo_article_chat` como objeto JSON (o cadena JSON válida) y devuelve `article_chat_saved: true` cuando la guardó. Su respuesta de `GET /site` anuncia `article_chat_supported: true`; el puente debe comprobarla **antes de crear un borrador con chat**, para no enviar chats a una versión del plugin que todavía no los admita. Sin chat, el flujo anterior sigue funcionando.

Ejemplo parcial del nuevo cuerpo REST:

```json
{
  "title": "¿Cuándo rediseñar una web?",
  "content": "<p>Texto introductorio.</p>\n[digitalisimo_article_chat]\n<h2>Señales de rediseño</h2>",
  "digitalisimo_article_chat": {
    "speakers": [
      {"id":"empresario","name":"Empresario","icon":"🧑","image_id":0,"align":"end"},
      {"id":"maryia","name":"MaryIA","icon":"🤖","image_id":0,"align":"center"},
      {"id":"daniel","name":"Daniel","icon":"👨‍💼","image_id":0,"align":"start"}
    ],
    "messages": [
      {"speaker":"empresario","text":"Mi página ya no explica bien lo que vendemos."},
      {"speaker":"maryia","text":"Comparé el catálogo y el texto: hay servicios desactualizados."},
      {"speaker":"daniel","text":"Conservamos lo que todavía atrae clientes y actualizamos lo que ya cambió."}
    ]
  }
}
```

`align`: `start` izquierda, `center` centro, `end` derecha. Cada `messages[].speaker` debe apuntar a un `speakers[].id` único. `image_id: 0` usa el emoji; si hay foto, utilizar un ID real del mismo WordPress. El plugin valida los datos y almacena `wp_json_encode(..., JSON_UNESCAPED_UNICODE)` en el metadato.

**Despliegue:** modificar el repositorio del plugin no actualiza automáticamente la instalación activa ni los ZIP de Releases. No crear borradores con chat hasta que el módulo SEO actualizado esté instalado y `GET /site` confirme `article_chat_supported: true`.



## Pendiente antes de instalar en producción

1. Validar flujo end-to-end en un WordPress de pruebas y otro Multisite (login, media, permisos, campos, SEO renderizado).
2. Añadir actualización, publicación con confirmación y programación.
3. Crear servidor MCP remoto que traduzca herramientas Apps SDK a esta REST API con autenticación segura por instalación. **Este repositorio todavía no implementa el complemento instalable de ChatGPT**.
4. Confirmar cómo transferir al servidor la imagen generada dentro de ChatGPT; el endpoint WordPress requiere el archivo o un ID de medio válido.
5. Para una app MCP privada con herramientas de escritura dentro de ChatGPT, confirmar acceso a un espacio Business/Enterprise/Edu; Plus no debe asumirse compatible.

No activar despliegue automático de esta rama sin validación.
