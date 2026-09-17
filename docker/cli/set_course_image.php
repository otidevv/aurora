<?php
// Establece la "imagen del curso" (course overview file) que se muestra en las tarjetas de
// Área personal > Mis cursos (/my/) y en el catálogo. Reemplaza la imagen anterior si existe.
//
// Uso:  php set_course_image.php <shortname> <ruta/imagen.(jpg|png|webp)>
define('CLI_SCRIPT', true);

// Moodle cambia el directorio de trabajo al del script al arrancar: resolver la ruta antes.
[$script, $shortname, $source] = array_pad($argv, 3, null);
$source = $source ? realpath($source) : null;
if (!$shortname || !$source || !is_readable($source)) {
    fwrite(STDERR, "uso: set_course_image.php <shortname> <imagen>\n");
    exit(1);
}

require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$context = context_course::instance($course->id);
$fs = get_file_storage();

// Moodle solo muestra la primera imagen del área (courseoverviewfileslimit = 1): la sustituimos.
$fs->delete_area_files($context->id, 'course', 'overviewfiles', 0);
$file = $fs->create_file_from_pathname([
    'contextid' => $context->id,
    'component' => 'course',
    'filearea'  => 'overviewfiles',
    'itemid'    => 0,
    'filepath'  => '/',
    'filename'  => clean_filename(basename($source)),
], $source);

// El panel /my/ (Mis cursos, Cursos recientes) lee la URL desde la caché 'course_image',
// que no se invalida sola: hay que borrar la entrada del curso.
\cache::make('core', 'course_image')->delete($course->id);
\core_courseformat\base::reset_course_cache($course->id);

echo "imagen '{$file->get_filename()}' (" . display_size($file->get_filesize()) . ") asignada a {$course->shortname}\n";
echo "ver: {$CFG->wwwroot}/my/\n";
