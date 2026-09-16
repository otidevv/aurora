<?php
// Guarda una imagen como logo del sitio (core_admin/logo). Moodle lo muestra encima del
// formulario de login y en otros lugares donde se usa el logo.
//
// Uso (dentro del contenedor web):
//   php set_logo.php [/ruta/imagen.png] [logo|logocompact|favicon]
//   (por defecto /tmp/logo_unamad.png como "logo"; "favicon" es el icono de la pestaña del navegador)
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';

$source = $argv[1] ?? '/tmp/logo_unamad.png';
$area = $argv[2] ?? 'logo';
if (!in_array($area, ['logo', 'logocompact', 'favicon'], true)) {
    fwrite(STDERR, "tipo no válido: {$area} (usa logo, logocompact o favicon)\n");
    exit(1);
}
if (!is_readable($source)) {
    fwrite(STDERR, "no se puede leer la imagen: {$source}\n");
    exit(1);
}
$filename = basename($source);
$context = context_system::instance();
$fs = get_file_storage();

$record = [
    'contextid' => $context->id,
    'component' => 'core_admin',
    'filearea'  => $area,
    'itemid'    => 0,
    'filepath'  => '/',
    'filename'  => $filename,
];

// Sustituye cualquier archivo anterior del mismo tipo.
$fs->delete_area_files($context->id, 'core_admin', $area, 0);
$fs->create_file_from_pathname($record, $source);
set_config($area, '/' . $filename, 'core_admin');
theme_reset_all_caches();

echo "{$area} guardado: " . get_config('core_admin', $area) . "\n";
