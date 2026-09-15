<?php
// Usage: php store_theme_file.php <source> [filearea=loginbackgroundimage]
// Adds (or replaces) one file in a theme_boost settings file area WITHOUT deleting the other
// files there. Used for extra login assets (e.g. fondo_hex.jpg) that the SCSS references as
// /pluginfile.php/1/theme_boost/<filearea>/0/<filename>.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';

$source = $argv[1] ?? null;
$filearea = $argv[2] ?? 'loginbackgroundimage';
if (!$source || !is_file($source)) {
    fwrite(STDERR, "usage: store_theme_file.php <source> [filearea]\n");
    exit(1);
}
$filename = basename($source);
$context = context_system::instance();
$fs = get_file_storage();

if ($existing = $fs->get_file($context->id, 'theme_boost', $filearea, 0, '/', $filename)) {
    $existing->delete();
}
$fs->create_file_from_pathname([
    'contextid' => $context->id,
    'component' => 'theme_boost',
    'filearea'  => $filearea,
    'itemid'    => 0,
    'filepath'  => '/',
    'filename'  => $filename,
], $source);
theme_reset_all_caches();

echo "stored theme_boost/{$filearea}/{$filename}\n";
