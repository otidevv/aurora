<?php
// Presenta el Diplomado en TIC como un solo programa. Idempotente: se puede volver a ejecutar al cambiar
// fechas o docentes.
//   1. Aula general: sección "Módulos del diplomado" con una tarjeta por módulo (portada, docente, fechas,
//      botón) y, en cada módulo, un enlace "Volver al diplomado".
//   2. Matrícula única: cada módulo tiene una matrícula por metaenlace al Aula general. Solo se sincroniza
//      el rol de estudiante; los docentes se matriculan a mano en su módulo.
//   4. Apertura por fechas: activa la tarea de Moodle que muestra los cursos al llegar su fecha de inicio
//      (cada hora) y oculta los módulos cuya fecha aún no llega. Sin fecha, el módulo no se toca.
//
// Uso:  php organizar_diplomado.php [--simular]
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$simular = in_array('--simular', $argv, true);
$general = 'DTIC-TIC-GENERAL';

// shortname => [docente, correo, inicio, fin] con fechas 'AAAA-MM-DD' (hora de Lima) o null si no se conocen.
$modulos = [
    'DTIC-M1' => ['M.Sc. Mario Jesús Ormachea Mejía', 'mormachea@unamad.edu.pe', null, null],
    'DTIC-M2' => ['M.Sc. Frank Arpita Salcedo', 'farpita@unamad.edu.pe', null, null],
    'DTIC-M3' => ['M.Sc. Jaffet Sillo Sosa', 'jsillo@unamad.edu.pe', null, null],
    'DTIC-M4' => ['Dr. Aldo Alarcón Sucasaca', 'aalarcon@unamad.edu.pe', null, null],
    'DTIC-M5' => ['M.Sc. Danger David Castellón Apaza', 'dcastellon@unamad.edu.pe', null, null],
    'DTIC-M6' => ['M.Sc. Jaime César Prieto Luna', 'jprieto@unamad.edu.pe', null, null],
];

\core\session\manager::set_user(get_admin());
$tz = new DateTimeZone('America/Lima');
$transaction = $simular ? $DB->start_delegated_transaction() : null;

$parent = $DB->get_record('course', ['shortname' => $general], '*', MUST_EXIST);
$parentcontext = context_course::instance($parent->id);
$fs = get_file_storage();

// --- 4. Fechas y visibilidad ------------------------------------------------------------------
$task = \core\task\manager::get_scheduled_task(\core\task\show_started_courses_task::class);
$task->set_disabled(false);
$task->set_minute('5');
$task->set_hour('*');
\core\task\manager::configure_scheduled_task($task);
echo "tarea 'mostrar cursos iniciados': activada, cada hora (min 5)\n";

$courses = [];
foreach ($modulos as $shortname => [$docente, $correo, $inicio, $fin]) {
    $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
    if ($inicio) {
        $start = (new DateTime($inicio . ' 00:00', $tz))->getTimestamp();
        $end = $fin ? (new DateTime($fin . ' 23:59', $tz))->getTimestamp() : 0;
        $update = (object) ['id' => $course->id, 'startdate' => $start, 'enddate' => $end];
        // Un módulo futuro queda oculto; la tarea lo mostrará en su fecha de inicio.
        if ($start > time()) {
            $update->visible = 0;
            $update->visibleold = 0;
        } else if (!$course->visible) {
            $update->visible = 1;
            $update->visibleold = 1;
        }
        update_course($update);
        $course = get_course($course->id);
        echo "{$shortname}: " . userdate($start, '%d/%m/%Y') . ($end ? ' - ' . userdate($end, '%d/%m/%Y') : '')
            . ($course->visible ? ' (visible)' : ' (oculto hasta su inicio)') . "\n";
    } else {
        echo "{$shortname}: sin fechas, visibilidad sin cambios\n";
    }
    $courses[$shortname] = $course;
}

// --- 2. Matrícula por metaenlace ------------------------------------------------------------------
\core\plugininfo\enrol::enable_plugin('meta', 1);
// Solo se copia el rol de estudiante: un docente del Aula general no debe volverse docente de todos los módulos.
$nosync = $DB->get_fieldset_select('role', 'id', "archetype <> 'student' OR archetype IS NULL");
set_config('nosyncroleids', implode(',', $nosync), 'enrol_meta');
// Solo se inscribe en los módulos a quien tiene un rol sincronizado (estudiante) en el Aula general.
set_config('syncall', 0, 'enrol_meta');

$meta = enrol_get_plugin('meta');
require_once($CFG->dirroot . '/enrol/meta/locallib.php');
foreach ($courses as $shortname => $course) {
    if (!$DB->record_exists('enrol', ['enrol' => 'meta', 'courseid' => $course->id, 'customint1' => $parent->id])) {
        $meta->add_instance($course, ['customint1' => $parent->id]);
        echo "{$shortname}: matrícula por metaenlace al Aula general creada\n";
    }
    enrol_meta_sync($course->id);
}
echo "estudiantes del Aula general sincronizados en los módulos\n";

// --- 1. Tarjetas en el Aula general -----------------------------------------------------------
// Sección 1 del Aula general.
$section = $DB->get_record('course_sections', ['course' => $parent->id, 'section' => 1]);
if (!$section) {
    $section = course_create_section($parent, 1);
}

// Las portadas se copian al área de archivos de la sección: así se ven aunque el módulo esté oculto.
$fs->delete_area_files($parentcontext->id, 'course', 'section', $section->id);
$cards = '';
$n = 0;
foreach ($courses as $shortname => $course) {
    $n++;
    [$docente, $correo, $inicio, $fin] = $modulos[$shortname];
    $context = context_course::instance($course->id);
    $imgurl = '';
    foreach ($fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false) as $file) {
        $copy = $fs->create_file_from_storedfile([
            'contextid' => $parentcontext->id, 'component' => 'course', 'filearea' => 'section',
            'itemid' => $section->id, 'filepath' => '/', 'filename' => 'modulo-' . $n . '-' . $file->get_filename(),
        ], $file);
        $imgurl = '@@PLUGINFILE@@/' . rawurlencode($copy->get_filename());
        break;
    }
    $title = preg_replace('/^Módulo\s*\d+\s*:\s*/u', '', $course->fullname);
    if ($course->startdate && $inicio) {
        $fechas = userdate($course->startdate, '%d %b') . ($course->enddate ? ' – ' . userdate($course->enddate, '%d %b %Y') : '');
    } else {
        $fechas = 'Fechas por confirmar';
    }
    $url = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
    $abierto = $course->visible;
    $boton = $abierto
        ? '<a class="btn btn-primary btn-sm mt-auto" href="' . $url . '">Entrar al módulo</a>'
        : '<span class="btn btn-outline-secondary btn-sm mt-auto disabled">Disponible desde el '
            . userdate($course->startdate, '%d/%m/%Y') . '</span>';
    $cards .= '<div class="col-12 col-sm-6 col-lg-4 mb-4">'
        . '<div class="card h-100 shadow-sm">'
        . ($imgurl ? '<a href="' . $url . '"><img class="card-img-top" src="' . $imgurl . '" alt="Módulo ' . $n . ': '
            . s($title) . '"></a>' : '')
        . '<div class="card-body d-flex flex-column">'
        . '<div class="small text-uppercase text-muted mb-1">Módulo ' . $n . '</div>'
        . '<h4 class="h5 card-title mb-2"><a class="text-reset" href="' . $url . '">' . s($title) . '</a></h4>'
        . '<p class="card-text small mb-1"><i class="fa fa-user me-1" aria-hidden="true"></i>' . s($docente) . '</p>'
        . '<p class="card-text small text-muted mb-3"><i class="fa fa-calendar me-1" aria-hidden="true"></i>'
            . s($fechas) . '</p>'
        . $boton
        . '</div></div></div>';
}
$summary = '<p>El diplomado tiene <strong>seis módulos</strong>. Cada uno es un aula con su docente, sus clases en '
    . 'Google Meet, sus prácticas y su asistencia. Entra desde aquí; tu avance aparece en la barra de progreso '
    . 'de cada módulo y en <em>Mis cursos</em>.</p>'
    . '<div class="row">' . $cards . '</div>';
course_update_section($parent, $section, [
    'name' => 'Módulos del diplomado',
    'summary' => $summary,
    'summaryformat' => FORMAT_HTML,
    'visible' => 1,
]);
echo "Aula general: sección 'Módulos del diplomado' con " . count($courses) . " tarjetas\n";

// Enlace de vuelta en cada módulo (al inicio de su sección 0, sin duplicarlo).
$marker = 'aurora-volver-diplomado';
$back = '<p class="' . $marker . '"><a class="btn btn-outline-secondary btn-sm" href="'
    . (new moodle_url('/course/view.php', ['id' => $parent->id, 'section' => 1]))->out(false)
    . '"><i class="fa fa-arrow-left me-1" aria-hidden="true"></i>Volver al Diplomado en TIC</a></p>';
foreach ($courses as $shortname => $course) {
    $s0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
    $text = preg_replace('~<p class="' . $marker . '">.*?</p>~s', '', (string) $s0->summary);
    course_update_section($course, $s0, ['summary' => $back . $text, 'summaryformat' => FORMAT_HTML]);
}
echo "módulos: enlace 'Volver al Diplomado en TIC' añadido\n";

foreach (array_merge([$parent->id], array_map(fn($c) => $c->id, $courses)) as $id) {
    rebuild_course_cache($id, true);
}

if ($transaction) {
    foreach ($courses as $shortname => $course) {
        echo "  {$shortname}: matriculados " . count(enrol_get_course_users($course->id, true)) . "\n";
    }
    try {
        $transaction->rollback(new \moodle_exception('generalexceptionmessage', 'error', '', 'simulación'));
    } catch (\moodle_exception $e) {
        foreach (array_merge([$parent->id], array_map(fn($c) => $c->id, $courses)) as $id) {
            rebuild_course_cache($id, true);
        }
        purge_caches(['muc' => true, 'theme' => false, 'lang' => false, 'js' => false]);
        echo "SIMULACIÓN: todo deshecho\n";
    }
}
