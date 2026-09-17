<?php
// Mejoras gratuitas para estudiantes y docentes del Diplomado en TIC. Idempotente.
//   1. Copias de seguridad automáticas de cursos (diarias, en un directorio fuera de la web).
//   2. Calificación por defecto vigesimal (0-20) para actividades nuevas.
//   3. Bloque "Progreso de finalización" en cada curso DTIC y en el Área personal por defecto.
//   4. Actividad "Asistencia" (mod_attendance) en la sección Presentación de cada módulo, sin nota.
//
// Requiere los plugins mod_attendance y block_completion_progress instalados.
// Uso:  php configurar_mejoras.php [directorio_copias_cursos]
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/my/lib.php');

$backupdir = $argv[1] ?? dirname($CFG->dataroot) . '/respaldos/cursos';
\core\session\manager::set_user(get_admin());

// --- 1. Copias automáticas de cursos --------------------------------------------------
if (!is_dir($backupdir)) {
    mkdir($backupdir, 0700, true);
}
$backup = [
    'backup_auto_active'          => 1,          // Activado.
    'backup_auto_weekdays'        => '1111111',  // Todos los días.
    'backup_auto_hour'            => 3,          // 03:00, después del respaldo completo de las 02:30.
    'backup_auto_minute'          => 0,
    'backup_auto_storage'         => 1,          // Solo en el directorio indicado.
    'backup_auto_destination'     => $backupdir,
    'backup_auto_max_kept'        => 7,          // Una semana por curso.
    'backup_auto_delete_days'     => 0,
    'backup_auto_min_kept'        => 0,
    'backup_auto_skip_hidden'     => 1,
    'backup_auto_skip_modif_days' => 0,
    'backup_auto_skip_modif_prev' => 1,          // No repetir si el curso no cambió.
    'backup_auto_users'           => 1,          // Incluye entregas y calificaciones.
];
foreach ($backup as $name => $value) {
    set_config($name, $value, 'backup');
}
echo "copias automáticas de cursos: diarias 03:00 -> {$backupdir} (7 por curso)\n";

// --- 2. Escala vigesimal por defecto ---------------------------------------------------
set_config('gradepointdefault', 20);
echo "calificación por defecto de actividades nuevas: 20\n";

// --- 3. Bloque de progreso ---------------------------------------------------------------
$courses = $DB->get_records_select('course', "shortname LIKE 'DTIC-%'", null, 'id');
foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    $exists = $DB->record_exists('block_instances', ['blockname' => 'completion_progress', 'parentcontextid' => $context->id]);
    if ($exists) {
        echo "progreso: ya existe en {$course->shortname}\n";
        continue;
    }
    $page = new moodle_page();
    $page->set_course($course);
    $page->set_pagelayout('course');
    $page->set_pagetype('course-view-' . $course->format);
    $page->blocks->add_regions(['side-pre'], false);
    $page->blocks->add_block('completion_progress', 'side-pre', -10, false, 'course-view-*');
    echo "progreso: añadido a {$course->shortname}\n";
}

// Área personal por defecto (la heredan los usuarios que aún no la personalizaron).
$syspage = my_get_page(null, MY_PAGE_PRIVATE);
$syscontext = context_system::instance();
if (!$DB->record_exists('block_instances', ['blockname' => 'completion_progress', 'parentcontextid' => $syscontext->id,
        'pagetypepattern' => 'my-index', 'subpagepattern' => $syspage->id])) {
    $page = new moodle_page();
    $page->set_context($syscontext);
    $page->set_pagelayout('mydashboard');
    $page->set_pagetype('my-index');
    $page->set_subpage($syspage->id);
    $page->blocks->add_regions(['content', 'side-pre'], false);
    $page->blocks->add_block('completion_progress', 'side-pre', -10, false, 'my-index', $syspage->id);
    echo "progreso: añadido al Área personal por defecto\n";
} else {
    echo "progreso: ya existe en el Área personal por defecto\n";
}

// --- 4. Asistencia en cada módulo -----------------------------------------------------------
$attmodule = $DB->get_record('modules', ['name' => 'attendance'], '*', MUST_EXIST);
foreach ($courses as $course) {
    if (!preg_match('/^DTIC-M\d+$/', $course->shortname)) {
        continue;
    }
    if ($DB->record_exists('attendance', ['course' => $course->id])) {
        echo "asistencia: ya existe en {$course->shortname}\n";
        continue;
    }
    $info = add_moduleinfo((object) [
        'course' => $course->id,
        'module' => $attmodule->id,
        'modulename' => 'attendance',
        'section' => 0,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'name' => 'Asistencia',
        'intro' => '<p>Registro de asistencia a las clases en vivo (viernes y sábado). El docente crea las sesiones; '
            . 'cuando se habilite, podrás marcar tu propia asistencia durante la clase.</p>',
        'introformat' => FORMAT_HTML,
        'grade' => 0,        // Sin nota: el docente decide si cuenta para la calificación.
        'subnet' => '',
        'cmidnumber' => '',
        'groupmode' => 0,
        'groupingid' => 0,
        'completion' => 0,
    ], $course);
    echo "asistencia: creada en {$course->shortname} (cmid {$info->coursemodule})\n";
}

purge_caches(['muc' => true, 'theme' => false, 'lang' => false, 'js' => false]);
echo "listo\n";
