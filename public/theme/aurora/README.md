# Tema Aurora (theme_aurora)

Tema hijo de Boost con el sistema de diseño **Aurora** de la UNAMAD. Sustituye al SCSS en bruto que se
guardaba en los ajustes de Boost (`docker/theme/boost-header.scss`): ahora todo vive en archivos del
repositorio y se versiona.

Fuentes del diseño:

- Sistema de diseño Aurora (tokens, componente Header): <https://claude.ai/artifact/UhumDhTPKUjchUp4a2TVEh>
- Tablero "Header Aurora" (antes/después, estados, móvil): <https://claude.ai/artifact/Bu7DBorLpVPpdrB2PcNNvf>

## Qué contiene

| Archivo | Para qué |
|---------|----------|
| `config.php` | Hereda de Boost (layouts, plantillas, renderers). Carga `scss/pre.scss` antes del preset y `scss/post.scss` después. |
| `scss/pre.scss` | Tokens Aurora y `$navbar-height: 64px`. Ninguna otra variable de Bootstrap/Boost se toca: fuera del header y del panel de notificaciones el sitio es Boost tal cual (decisión del usuario, 2026-09-27). |
| `scss/post.scss` | Sección 1: header (fondo plano navy-900, Inter, pestaña activa con subrayado cian, foco visible, insignia amber, controles de 40 px, interruptor con etiqueta blanca) y cajón móvil. Sección 2: panel de notificaciones. |
| `classes/hook_callbacks.php`, `db/hooks.php` | Cargan Inter desde Google Fonts en el `<head>` (el compilador SCSS de Moodle no admite `@import url()`). |
| `templates/core/user_menu_metadata.mustache` | Muestra el nombre del usuario junto al avatar (oculto por debajo de 992 px). |
| `templates/message_popup/notification_popover.mustache` | Panel de notificaciones del tablero "Notificaciones": cabecera con contador y "Marcar todo como leído" con texto, filtro Todas / No leídas (JS propio en el `{{#js}}` de la plantilla, solo oculta las leídas), estado vacío "Estás al día", pie "Ver todas las notificaciones". Conserva los `data-region` / `data-action` del JS de Moodle. |
| `templates/message_popup/notification_content_item.mustache` | Fila entera clicable: icono en círculo, título, contexto (`contexturlname`) en gris, hora, punto de no leída o chevrón. "Ver notificación completa" queda solo para teclado y lectores de pantalla. |
| `lang/en`, `lang/es` | Nombre y descripción del tema. |

El logo compacto del header sigue siendo el del sitio (`core_admin/logocompact`, ver `docker/theme/README.md`).

## Instalar / activar

```sh
docker compose exec -T web sh -c '
  php admin/cli/upgrade.php --non-interactive &&
  php admin/cli/cfg.php --name=theme --set=aurora &&
  php admin/cli/purge_caches.php'
```

Tras cambiar `scss/*.scss` o una plantilla basta con `php admin/cli/purge_caches.php`. Si el SCSS no compila,
Moodle sirve el CSS por defecto sin avisar: comprobar con `curl` que `theme/styles.php/aurora/.../all` contiene
`background:#14224d`.

## Alcance del tema (2026-09-27)

A petición del usuario el tema solo cambia dos cosas: el **header** (con el modo de edición y el nombre junto al
avatar) y el **panel de notificaciones**. Se aplicaron y se retiraron el mismo día: el fondo de página y el ancho
de contenido (1344 px), los bloques como tarjetas, los botones y enlaces en navy, la fuente Inter global, y la
página del curso (pestañas, secciones como tarjetas, filas de actividades, índice). Los artboards de "Después:
Área personal" y "Página del curso" siguen en el tablero por si se retoman; lo que en ellos depende de datos
(ruta, progreso, "Próxima entrega", chips de estado, fechas cortas) necesitaría un renderer propio.

## Panel de notificaciones: lo que no cubre el tema

De la nota del tablero quedan fuera tres puntos que exigen cambiar el JS de `message_popup` (core) y no una
plantilla del tema: la agrupación por fecha (Hoy / Ayer), colapsar las bienvenidas repetidas en un solo ítem y
acortar la hora relativa ("hace 2 h" en lugar de "hace 2 horas 19 minutos", que viene del servidor).

## Lo que el tablero muestra y depende de datos, no del tema

- Campo de búsqueda en el header: aparece al activar la búsqueda global (`enableglobalsearch`); ya está estilado.
- Pestaña "Mis cursos", tarjetas de módulos e insignias con número: salen con matrículas y notificaciones reales.
