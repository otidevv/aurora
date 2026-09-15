<?php
// Stores the given image (default /tmp/fondologounamad.png) as the Boost login background image (theme_boost/loginbackgroundimage).
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';

$source = $argv[1] ?? '/tmp/fondologounamad.png';
$filename = basename($source);
$context = context_system::instance();
$fs = get_file_storage();

$fs->delete_area_files($context->id, 'theme_boost', 'loginbackgroundimage', 0);
$fs->create_file_from_pathname([
    'contextid' => $context->id,
    'component' => 'theme_boost',
    'filearea'  => 'loginbackgroundimage',
    'itemid'    => 0,
    'filepath'  => '/',
    'filename'  => $filename,
], $source);
set_config('loginbackgroundimage', '/' . $filename, 'theme_boost');
theme_reset_all_caches();

echo "login background: " . get_config('theme_boost', 'loginbackgroundimage') . "\n";
