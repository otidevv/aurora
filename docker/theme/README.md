# Aurora login — theme customisations

The Moodle Boost login page is customised **without editing core files**: everything lives in Moodle
settings / moodledata and can be re-applied from this folder.

**Currently applied: v4 "Hexágonos"** (2026-09-15) — modelled on the user's reference mock-up
`login_plantilla.png`: light grey left panel (#EEF2F5) with a headline and a hexagon collage (campus
photo + coloured icon hexagons), white right panel with the logo top-left, "Acceder" heading in
UNAMAD garnet (#A6192E), Montserrat everywhere. Single-file reference design:
`aurora-login-v4.html` (photo + logo embedded as base64, works offline).

| File | Where it goes |
|------|---------------|
| `boost-login.scss` | Boost > Advanced > **Raw SCSS** (`theme_boost/scss`). `theme_boost/scsspre` = `$login-layout-left-bg: #eef2f5;` |
| `auth-instructions.html` | Site admin > Plugins > Authentication > Manage authentication > **Instructions** (`auth_instructions`). Replaces the default left panel (headline + collage). |
| `additionalhtmlhead.html` | Site admin > Appearance > Additional HTML > **Within HEAD** (`additionalhtmlhead`). Loads Montserrat from Google Fonts. |
| `lang/es_local/moodle.php`, `lang/en_local/moodle.php` | `$CFG->dataroot/lang/<lang>_local/moodle.php`. Overrides `loginto` ("Ingresa con tu cuenta Aurora.") and the "o" separator. |
| `logo_unamad.png` + `set_logo.php` | Site logo (`core_admin/logo`). Shown top-left of the form (`#loginlogo`, wordmark added via CSS). |
| `fondo_unamad.jpg` + `set_login_background.php` | Boost **Login background image**. Must stay set (otherwise Boost prints an "AI-generated image" watermark); the SCSS hides it with `background-image: none !important`. |
| `fondo_hex.jpg` + `store_theme_file.php` | 900 px version of the campus photo, stored in the same `loginbackgroundimage` file area and shown inside the big hexagon at `/pluginfile.php/1/theme_boost/loginbackgroundimage/0/fondo_hex.jpg`. |
| `logo_160.png` | Small logo used only inside `aurora-login-v4.html`. |

Site full name / short name are set to **Aurora**.

## Re-apply everything (v4)

```sh
export MSYS_NO_PATHCONV=1   # Git Bash on Windows only
docker compose cp docker/theme/boost-login.scss web:/tmp/login.scss
docker compose cp docker/theme/auth-instructions.html web:/tmp/auth.html
docker compose cp docker/theme/additionalhtmlhead.html web:/tmp/head.html
docker compose cp docker/theme/lang web:/tmp/lang_local
docker compose exec -T web sh -c '
  mkdir -p /var/www/moodledata/lang/es_local /var/www/moodledata/lang/en_local &&
  cp /tmp/lang_local/es_local/moodle.php /var/www/moodledata/lang/es_local/ &&
  cp /tmp/lang_local/en_local/moodle.php /var/www/moodledata/lang/en_local/ &&
  php admin/cli/cfg.php --name=auth_instructions --set="$(cat /tmp/auth.html)" &&
  php admin/cli/cfg.php --name=additionalhtmlhead --set="$(cat /tmp/head.html)" &&
  php admin/cli/cfg.php --component=theme_boost --name=scsspre --set="\$login-layout-left-bg: #eef2f5;" &&
  php admin/cli/cfg.php --component=theme_boost --name=scss --set="$(cat /tmp/login.scss)" &&
  php admin/cli/purge_caches.php'
```

Images (only when an image changes):

```sh
docker compose cp docker/theme/fondo_unamad.jpg web:/tmp/fondo_unamad.jpg
docker compose cp docker/theme/set_login_background.php web:/tmp/set_login_background.php
docker compose exec -T web sh -c 'php /tmp/set_login_background.php /tmp/fondo_unamad.jpg'

docker compose cp docker/theme/fondo_hex.jpg web:/tmp/fondo_hex.jpg
docker compose cp docker/theme/store_theme_file.php web:/tmp/store_theme_file.php
docker compose exec -T web sh -c 'php /tmp/store_theme_file.php /tmp/fondo_hex.jpg'   # run AFTER set_login_background.php (that script wipes the area)

docker compose cp docker/theme/logo_unamad.png web:/tmp/logo_unamad.png
docker compose cp docker/theme/set_logo.php web:/tmp/set_logo.php
docker compose exec -T web sh -c 'php /tmp/set_logo.php && php admin/cli/purge_caches.php'
```

Notes:

- Right after a purge, Moodle serves the previous stylesheet while it recompiles; reload once more
  (with a cache-busting query string if Chrome keeps the old page) if it still looks stale. For a
  few seconds after `purge_caches.php` the site (and `cfg.php`) can answer "El sitio está siendo
  actualizado" / HTTP 500 while the cache bootstrap is rebuilt: wait and retry, nothing is broken.
- v4 details (2026-09-15): eyebrow with gold bar above the headline, drop-shadow that follows the
  photo hexagon (clip the `<img>`, `filter: drop-shadow` on the box), floating icon hexagons
  (`aurora-float`, disabled under `prefers-reduced-motion`), three benefit chips under the collage,
  Font Awesome user/lock icons inside the two fields (`::after` on `.login-form-username` /
  `.login-form-password`, coloured on `:focus-within`), inset hairline between the panels.
- `auth_instructions` goes through format_text/HTML Purifier: no inline SVG or `style` attributes,
  so decorative shapes are CSS (`clip-path` hexagons, data-URI SVG backgrounds) and icons are Font
  Awesome (`fa-book-open`, `fa-users`, `fa-trophy`, `fa-graduation-cap`).
- The left column is `d-lg-flex` (flex !important), so `.login-layout-left-content` needs
  `width: 100%` or it shrinks to its max-content width.

## Older versions kept in this folder

| Version | Files | Look |
|---------|-------|------|
| v2 (applied until 2026-09-15) | `boost-login-v2.scss`, `auth-instructions-v2.html`, `additionalhtmlhead-v2.html` | Campus photo tinted garnet #b5103d, Source Serif 4 / Source Sans 3. `scsspre` = `$login-layout-left-bg: #b5103d;` |
| v3 (never kept) | `boost-login-v3.scss`, `auth-instructions-v3.html`, `additionalhtmlhead-v3.html`, `shapes-v3.svg`, `aurora-login-v3.html` | Solid gradient #A6192E → #7A0C1F with SVG waves, Playfair Display + Inter. |

To go back to v2, copy the `-v2` files over the canonical names, set `scsspre` to `#b5103d` and
re-run the block above.

## Not covered by Moodle 5.2

- The mock-up's "Recordar mi sesión" checkbox: the Moodle 5.2 login template has no remember-me
  control, so it would need a template override in a child theme. It exists only in
  `aurora-login-v4.html`.
- The "Escríbenos a soporte" line under the Google button (mock-up only).

## Second login method: Google account @unamad.edu.pe (OAuth 2)

`setup_oauth2_google.php` creates the Google OAuth 2 service named **correo @unamad.edu.pe**
(login page only, logins restricted to `unamad.edu.pe`, no e-mail confirmation step) and enables
the `auth_oauth2` plugin. The button "Acceder con correo @unamad.edu.pe" only appears once the
Google client credentials are stored.

1. Google Workspace admin creates an OAuth client (Google Cloud Console > APIs & Services >
   Credentials > OAuth client ID > Web application) with this authorised redirect URI:
   `http://localhost:8090/admin/oauth2callback.php` (and the production URL later).
2. Store the credentials:

```sh
docker compose cp docker/theme/setup_oauth2_google.php web:/tmp/setup_oauth2_google.php
docker compose exec -T web sh -c 'php /tmp/setup_oauth2_google.php "<CLIENT_ID>" "<CLIENT_SECRET>" && php admin/cli/purge_caches.php'
```

   Or paste them in Site administration > Server > OAuth 2 services > correo @unamad.edu.pe.

`php /tmp/setup_oauth2_google.php --clear` blanks the credentials again (hides the button).

Status 2026-09-15: the real client id / secret are stored in the Moodle database (never in this
folder). The Google consent screen is of type "Internal" (Workspace organisation only), so an
account outside unamad.edu.pe gets Google's "restricted to users within its organization" page.

Behaviour: an existing Aurora account with the same e-mail is linked automatically on first Google
login; unknown @unamad.edu.pe users get an account created.

## Volver al login estándar de Moodle

El 2026-09-16 se decidió usar el login tal como lo trae Moodle. `reset_login_default.php` vacía el
SCSS de Boost, el panel de bienvenida, las fuentes del `<head>`, el fondo, el logo y las cadenas de
idioma locales del login. Conserva el nombre del sitio y el acceso con Google (OAuth 2).

```sh
docker compose cp docker/theme/reset_login_default.php web:/tmp/reset_login_default.php
docker compose exec -T web php /tmp/reset_login_default.php
```

En un servidor sin Docker, pasa la ruta de `config.php` como argumento. Los demás archivos de esta
carpeta se conservan por si se quiere volver a aplicar el diseño Aurora.

### Logo horizontal UNAMAD (2026-09-16)

Sobre el login estándar, el logo del sitio es `logo_horizontal.png` (escudo + "UNAMAD Universidad
Nacional Amazónica de Madre de Dios", 468×150). Moodle lo muestra centrado encima de "Le damos la
bienvenida de nuevo" sin ningún estilo extra.

```sh
docker compose cp docker/theme/logo_horizontal.png web:/tmp/logo_horizontal.png
docker compose cp docker/theme/set_logo.php web:/tmp/set_logo.php
docker compose exec -T web sh -c 'php /tmp/set_logo.php /tmp/logo_horizontal.png && php admin/cli/purge_caches.php'
```

### Carrusel, textos e icono sobre el login estándar (2026-09-16)

- **Carrusel del panel izquierdo:** `login-carousel.html` (estilos + script) va en Administración del sitio >
  Apariencia > HTML adicional > *Antes de cerrar BODY* (`additionalhtmlfooter`). Solo actúa en la página de
  login y rota cada 6 s las fotos de `public/banner/web/` (copias de 1600 px hechas con `resize_image.php`;
  los originales de `public/banner/` no se suben, ver su `.gitignore`). Se pausa si la pestaña no está visible
  y no anima con "reducir movimiento". La primera foto es también el fondo del login (respaldo sin JavaScript).
- **Textos:** `lang/es_local` y `lang/en_local` cambian el panel ("Le damos la bienvenida a Aurora", lema de
  la UNAMAD y tres rasgos en lugar de las cifras de Moodle) y el crédito del menú "?" por la Oficina de
  Tecnología de la Información con oti@unamad.edu.pe.
- **Icono de la pestaña:** `public/pestaña_icono/favicon.ico` guardado en Apariencia > Logos > Favicon.

```sh
docker compose exec -T web sh -c '
  php admin/cli/cfg.php --name=additionalhtmlfooter --set="$(cat /var/www/html/docker/theme/login-carousel.html)" &&
  php /var/www/html/docker/theme/set_login_background.php /var/www/html/public/banner/web/banner1.jpg &&
  php /var/www/html/docker/theme/set_logo.php "/var/www/html/public/pestaña_icono/favicon.ico" favicon &&
  for l in es_local en_local; do mkdir -p /var/www/moodledata/lang/$l &&
    cp /var/www/html/docker/theme/lang/$l/moodle.php /var/www/moodledata/lang/$l/; done &&
  php admin/cli/purge_caches.php'
```

Nota: `reset_login_default.php` borra el fondo y el logo, pero no el carrusel (`additionalhtmlfooter`),
el favicon ni estas cadenas de idioma (solo quita las del diseño Aurora anterior: `loginto`,
`loginseparatoror`, `loginwith`).
