<?php
// Devuelve la página de login al diseño estándar de Moodle (Boost sin personalizar).
//
// Quita: SCSS del tema, panel de bienvenida (auth_instructions), fuentes en el <head>,
// fondo y logo personalizados y las cadenas de idioma locales del login.
// Mantiene: nombre del sitio y acceso con Google (OAuth 2), que son funcionalidad, no diseño.
//
// Uso (dentro del contenedor web o en el servidor, desde la raíz de Moodle):
//   php reset_login_default.php [/ruta/a/config.php]
define('CLI_SCRIPT', true);
require $argv[1] ?? '/var/www/html/config.php';

$context = context_system::instance();
$fs = get_file_storage();

// Tema Boost.
set_config('scss', '', 'theme_boost');
set_config('scsspre', '', 'theme_boost');
$fs->delete_area_files($context->id, 'theme_boost', 'loginbackgroundimage');
set_config('loginbackgroundimage', '', 'theme_boost');
echo "tema Boost: SCSS y fondo del login restablecidos\n";

// Contenido del login.
set_config('auth_instructions', '');
set_config('additionalhtmlhead', '');
echo "panel de bienvenida y fuentes eliminados\n";

// Logo del sitio (se muestra encima del formulario de login).
$fs->delete_area_files($context->id, 'core_admin', 'logo');
set_config('logo', '', 'core_admin');
echo "logo del sitio eliminado\n";

// Cadenas de idioma personalizadas del login.
$strings = ['loginto', 'loginseparatoror', 'loginwith'];
foreach (['es_local', 'en_local'] as $lang) {
    $file = "{$CFG->dataroot}/lang/{$lang}/moodle.php";
    if (!is_file($file)) {
        continue;
    }
    $content = file_get_contents($file);
    foreach ($strings as $name) {
        $content = preg_replace('/^\$string\[\'' . $name . '\'\]\s*=.*\R/m', '', $content);
    }
    if (!preg_match('/^\$string\[/m', $content)) {
        unlink($file);
        echo "idioma {$lang}: archivo de personalización eliminado\n";
    } else {
        file_put_contents($file, $content);
        echo "idioma {$lang}: cadenas del login eliminadas\n";
    }
}

theme_reset_all_caches();
purge_all_caches();
echo "cachés purgadas\n";
