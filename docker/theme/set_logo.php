<?php
// Stores /tmp/logo_unamad.png as the site logo (core_admin/logo).
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';

$source = '/tmp/logo_unamad.png';
$filename = 'logo_unamad.png';
$context = context_system::instance();
$fs = get_file_storage();

$record = [
    'contextid' => $context->id,
    'component' => 'core_admin',
    'filearea'  => 'logo',
    'itemid'    => 0,
    'filepath'  => '/',
    'filename'  => $filename,
];

// Replace any previous logo files.
$fs->delete_area_files($context->id, 'core_admin', 'logo', 0);
$fs->create_file_from_pathname($record, $source);
set_config('logo', '/' . $filename, 'core_admin');

echo "logo stored: " . get_config('core_admin', 'logo') . "\n";
