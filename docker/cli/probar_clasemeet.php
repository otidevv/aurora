<?php
// Prueba de mod_clasemeet: añade una "Clase Meet" a un curso actuando como el docente indicado.
//
// Uso (dentro del contenedor web):
//   php probar_clasemeet.php <correo_docente> <shortname_curso> "<Nombre de la clase>" [seccion=1] ["YYYY-MM-DD HH:MM"] ["YYYY-MM-DD HH:MM"]
//   (inicio y fin en la zona horaria del sitio; por defecto mañana de 08:00 a 10:00)
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');

[$script, $email, $shortname, $nombre] = array_pad($argv, 4, null);
$section = (int) ($argv[4] ?? 1);
$tz = new DateTimeZone(core_date::get_server_timezone());
$start = new DateTime($argv[5] ?? 'tomorrow 08:00', $tz);
$end = new DateTime($argv[6] ?? 'tomorrow 10:00', $tz);
if (!$email || !$shortname || !$nombre) {
    fwrite(STDERR, "uso: probar_clasemeet.php <correo_docente> <shortname> <nombre> [seccion]\n");
    exit(1);
}

$teacher = $DB->get_record('user', ['email' => core_text::strtolower($email), 'deleted' => 0], '*', MUST_EXIST);
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$module = $DB->get_record('modules', ['name' => 'clasemeet'], '*', MUST_EXIST);

// Actuamos como el docente: clasemeet_add_instance toma el propietario de $USER.
\core\session\manager::set_user($teacher);

$moduleinfo = (object) [
    'course' => $course->id,
    'module' => $module->id,
    'modulename' => 'clasemeet',
    'section' => $section,
    'visible' => 1,
    'visibleoncoursepage' => 1,
    'name' => $nombre,
    'introeditor' => ['text' => '<p>Clase en vivo por Google Meet. Se graba automáticamente y las grabaciones '
        . 'aparecen en esta actividad.</p>', 'format' => FORMAT_HTML, 'itemid' => 0],
    'autorecording' => 1,
    'autotranscription' => 0,
    'timestart' => $start->getTimestamp(),
    'timeend' => $end->getTimestamp(),
    'cmidnumber' => '',
    'groupmode' => 0,
    'groupingid' => 0,
    'completion' => 0,
];

$info = add_moduleinfo($moduleinfo, $course);
rebuild_course_cache($course->id, true);

$instance = $DB->get_record('clasemeet', ['id' => $info->instance], '*', MUST_EXIST);
echo "Clase Meet creada (cmid {$info->coursemodule}, instancia {$instance->id})\n";
echo "  docente       : {$instance->owneremail}\n";
echo "  espacio       : {$instance->spacename}\n";
echo "  código        : {$instance->meetingcode}\n";
echo "  enlace        : {$instance->meetinguri}\n";
echo "  autograbación : " . ($instance->autorecording ? 'ON' : 'OFF') . "\n";
echo "  horario       : " . userdate($instance->timestart) . " -> " . userdate($instance->timeend) . "\n";
echo "  evento Moodle : " . ($DB->record_exists('event', ['modulename' => 'clasemeet', 'instance' => $instance->id]) ? 'sí' : 'no') . "\n";
echo "  evento Google : " . ($instance->googleeventid ?: 'no (ver depuración)') . "\n";
echo "  ver actividad : {$CFG->wwwroot}/mod/clasemeet/view.php?id={$info->coursemodule}\n";
