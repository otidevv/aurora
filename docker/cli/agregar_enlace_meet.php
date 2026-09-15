<?php
// Añade al curso un recurso URL con el enlace de la clase en Google Meet.
//
// Uso (dentro del contenedor web):
//   php agregar_enlace_meet.php <shortname_curso> "<Nombre de la clase>" <url_meet> [seccion=1] [descripcion]
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/resourcelib.php');

[$script, $shortname, $nombre, $url] = array_pad($argv, 4, null);
$section = (int) ($argv[4] ?? 1);
$descripcion = $argv[5] ?? 'Clase en vivo por Google Meet. La sesión se graba automáticamente y la grabación '
    . 'se publica en esta misma sección para quienes no pudieron asistir.';
if (!$shortname || !$nombre || !$url) {
    fwrite(STDERR, "uso: agregar_enlace_meet.php <shortname> <nombre> <url> [seccion] [descripcion]\n");
    exit(1);
}

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$module = $DB->get_record('modules', ['name' => 'url'], '*', MUST_EXIST);

$moduleinfo = (object) [
    'course' => $course->id,
    'module' => $module->id,
    'modulename' => 'url',
    'section' => $section,
    'visible' => 1,
    'visibleoncoursepage' => 1,
    'name' => $nombre,
    'introeditor' => ['text' => $descripcion, 'format' => FORMAT_HTML, 'itemid' => 0],
    'externalurl' => $url,
    'display' => RESOURCELIB_DISPLAY_NEW, // Abrir Meet en una pestaña nueva.
    'printintro' => 1,
    'cmidnumber' => '',
    'groupmode' => 0,
    'groupingid' => 0,
    'completion' => 0,
];
$info = add_moduleinfo($moduleinfo, $course);
rebuild_course_cache($course->id, true);

echo "recurso URL creado (cmid {$info->coursemodule}) en la sección {$section} del curso {$course->shortname}\n";
echo "ver: {$CFG->wwwroot}/course/view.php?id={$course->id}#section-{$section}\n";
