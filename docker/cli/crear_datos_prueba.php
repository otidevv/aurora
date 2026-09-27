<?php
// Crea un juego de datos de prueba para desarrollo:
//   - usuarios docente@unamad.edu.pe y estudiante@unamad.edu.pe (contraseña 12345678),
//   - categoría "Cursos de prueba" con 5 cursos donde el docente es profesor editor y el estudiante alumno,
//   - 3 tareas por curso (una próxima, una lejana y una vencida) con entrega en línea y de archivo,
//   - un anuncio del docente en el foro de avisos de cada curso,
//   - una entrega del estudiante en la primera tarea de cada curso; en los dos primeros cursos el docente la califica,
//   - notificaciones (entrega recibida, entrega para calificar, retroalimentación disponible) y mensajes directos.
// Es idempotente: los usuarios y cursos existentes se reutilizan; las tareas, anuncios y entregas se crean
// solo si el curso no tiene tareas todavía.
//
// Uso (dentro del contenedor web):
//   php crear_datos_prueba.php [password]
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/messagelib.php');

$password = $argv[1] ?? '12345678';
$admin = get_admin();
\core\session\manager::set_user($admin);

// --- Usuarios ------------------------------------------------------------------
function dp_user(string $email, string $firstname, string $lastname, string $password): stdClass {
    global $DB, $CFG;
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
    if (!$user) {
        $id = user_create_user((object) [
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $email,
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'lang' => 'es',
            'password' => $password,
        ], true, false);
        $user = core_user::get_user($id);
        echo "usuario creado: {$user->username} (id {$user->id})\n";
    } else {
        update_internal_user_password($user, $password);
        echo "usuario existente: {$user->username} (id {$user->id}); contraseña actualizada\n";
    }
    return $user;
}
$docente = dp_user('docente@unamad.edu.pe', 'Docente', 'Prueba', $password);
$estudiante = dp_user('estudiante@unamad.edu.pe', 'Estudiante', 'Prueba', $password);

// --- Categoría y cursos ----------------------------------------------------------
$category = $DB->get_record('course_categories', ['idnumber' => 'PRUEBAS']);
if (!$category) {
    $category = core_course_category::create(['name' => 'Cursos de prueba', 'idnumber' => 'PRUEBAS']);
    echo "categoría creada: Cursos de prueba (id {$category->id})\n";
}

$cursos = [
    ['PRUEBA-01', 'Redes de Computadoras', 'Fundamentos de redes, modelo OSI, TCP/IP y configuración de equipos.'],
    ['PRUEBA-02', 'Programación Web', 'HTML, CSS, JavaScript y desarrollo de aplicaciones con PHP.'],
    ['PRUEBA-03', 'Base de Datos', 'Modelado relacional, SQL y administración de PostgreSQL.'],
    ['PRUEBA-04', 'Sistemas Operativos', 'Procesos, memoria, sistemas de archivos y administración de Linux.'],
    ['PRUEBA-05', 'Ingeniería de Software', 'Requisitos, metodologías ágiles, pruebas y gestión de proyectos.'],
];
$roleteacher = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$rolestudent = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

function dp_enrol(stdClass $course, stdClass $user, int $roleid): void {
    $context = context_course::instance($course->id);
    if (!is_enrolled($context, $user)) {
        if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
            enrol_get_plugin('manual')->add_default_instance($course);
            enrol_try_internal_enrol($course->id, $user->id, $roleid);
        }
    } else {
        role_assign($roleid, $user->id, $context->id);
    }
}

function dp_assign(stdClass $course, int $section, string $name, string $intro, int $due): stdClass {
    global $DB;
    $moduleinfo = (object) [
        'course' => $course->id,
        'module' => $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST),
        'modulename' => 'assign',
        'section' => $section,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'name' => $name,
        'intro' => $intro,
        'introformat' => FORMAT_HTML,
        'showdescription' => 0,
        'alwaysshowdescription' => 1,
        'activityeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
        'submissionattachments' => 0,
        'allowsubmissionsfromdate' => $due - 21 * DAYSECS,
        'duedate' => $due,
        'cutoffdate' => 0,
        'gradingduedate' => $due + 7 * DAYSECS,
        'timelimit' => 0,
        'submissiondrafts' => 0,
        'requiresubmissionstatement' => 0,
        'sendnotifications' => 1,
        'sendlatenotifications' => 1,
        'sendstudentnotifications' => 1,
        'grade' => 100,
        'gradepenalty' => 0,
        'teamsubmission' => 0,
        'requireallteammemberssubmit' => 0,
        'teamsubmissiongroupingid' => 0,
        'preventsubmissionnotingroup' => 0,
        'blindmarking' => 0,
        'hidegrader' => 0,
        'attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
        'maxattempts' => -1,
        'markingworkflow' => 0,
        'markingallocation' => 0,
        'markinganonymous' => 0,
        'markercount' => 1,
        'multimarkmethod' => 0,
        'multimarkrounding' => 0,
        'completionsubmit' => 0,
        'assignsubmission_onlinetext_enabled' => 1,
        'assignsubmission_onlinetext_wordlimit_enabled' => 0,
        'assignsubmission_onlinetext_wordlimit' => 0,
        'assignsubmission_file_enabled' => 1,
        'assignsubmission_file_maxfiles' => 5,
        'assignsubmission_file_maxsizebytes' => 0,
        'assignsubmission_file_filetypes' => '',
        'assignfeedback_comments_enabled' => 1,
        'assignfeedback_comments_commentinline' => 0,
        'cmidnumber' => '',
        'groupmode' => 0,
        'groupingid' => 0,
        'completion' => 0,
    ];
    return add_moduleinfo($moduleinfo, $course);
}

$now = time();
$resumen = [];
foreach ($cursos as $i => [$shortname, $fullname, $summary]) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = create_course((object) [
            'fullname' => $fullname,
            'shortname' => $shortname,
            'category' => $category->id,
            'format' => 'topics',
            'numsections' => 4,
            'visible' => 1,
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
            'startdate' => $now - 7 * DAYSECS,
            'enablecompletion' => 1,
        ]);
        echo "curso creado: {$fullname} [{$shortname}] (id {$course->id})\n";
    } else {
        echo "curso existente: {$fullname} [{$shortname}] (id {$course->id})\n";
    }
    dp_enrol($course, $docente, $roleteacher);
    dp_enrol($course, $estudiante, $rolestudent);

    if ($DB->record_exists('assign', ['course' => $course->id])) {
        echo "  ya tiene tareas; se omiten tareas, anuncio y entregas\n";
        continue;
    }

    // Tareas: una próxima, una lejana y una vencida.
    \core\session\manager::set_user($admin);
    $tareas = [
        dp_assign($course, 1, "Tarea 1: Informe de la unidad 1 - {$fullname}",
            '<p>Redacta un informe de dos páginas sobre los conceptos de la unidad 1. Puedes escribirlo en línea o subir un PDF.</p>',
            $now + 7 * DAYSECS),
        dp_assign($course, 2, "Tarea 2: Proyecto práctico - {$fullname}",
            '<p>Desarrolla el proyecto práctico descrito en clase y sube el archivo comprimido con el código y la documentación.</p>',
            $now + 14 * DAYSECS),
        dp_assign($course, 3, "Tarea 3: Cuestionario de repaso (vencida) - {$fullname}",
            '<p>Esta tarea ya venció: sirve para probar entregas tardías y avisos de retraso.</p>',
            $now - 2 * DAYSECS),
    ];
    echo "  3 tareas creadas (cmid " . implode(', ', array_map(fn($t) => $t->coursemodule, $tareas)) . ")\n";

    // Anuncio del docente en el foro de avisos.
    \core\session\manager::set_user($docente);
    $news = forum_get_course_forum($course->id, 'news');
    $discussion = (object) [
        'course' => $course->id,
        'forum' => $news->id,
        'name' => "Bienvenida al curso {$fullname}",
        'message' => "<p>Hola, les doy la bienvenida al curso <strong>{$fullname}</strong>. Ya están publicadas las "
            . "tres primeras tareas; revisen las fechas de entrega en el calendario. Cualquier duda, escríbanme por mensaje.</p>",
        'messageformat' => FORMAT_HTML,
        'messagetrust' => 0,
        'attachments' => null,
        'mailnow' => 1,
        'groupid' => -1,
        'timestart' => 0,
        'timeend' => 0,
        'pinned' => 0,
    ];
    $discussionid = forum_add_discussion($discussion, null, null, $docente->id);
    echo "  anuncio publicado (discusión {$discussionid})\n";

    // Entrega del estudiante en la tarea 1.
    \core\session\manager::set_user($estudiante);
    [$cm1] = [get_coursemodule_from_id('assign', $tareas[0]->coursemodule, $course->id, false, MUST_EXIST)];
    $ctx1 = context_module::instance($cm1->id);
    $assign = new assign($ctx1, $cm1, $course);
    $submission = $assign->get_user_submission($estudiante->id, true);
    $onlinetext = $assign->get_submission_plugin_by_type('onlinetext');
    $onlinetext->save($submission, (object) ['onlinetext_editor' => [
        'text' => "<p>Entrega de prueba del estudiante para la tarea 1 del curso {$fullname}. "
            . "Este texto se generó automáticamente para probar el flujo de calificación.</p>",
        'format' => FORMAT_HTML,
        'itemid' => file_get_unused_draft_itemid(),
    ]]);
    $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
    $submission->timemodified = $now;
    $DB->update_record('assign_submission', $submission);
    $instance = $assign->get_instance();
    // Notificaciones reales de mod_assign: recibo al estudiante y aviso al docente.
    assign::send_assignment_notification($estudiante, $estudiante, 'submissionreceipt', 'assign_notification',
        $now, $cm1, $ctx1, $course, get_string('modulename', 'assign'), $instance->name, false, $submission->id);
    assign::send_assignment_notification($estudiante, $docente, 'gradersubmissionupdated', 'assign_notification',
        $now, $cm1, $ctx1, $course, get_string('modulename', 'assign'), $instance->name, false, $submission->id);
    echo "  entrega del estudiante registrada en la tarea 1\n";

    // En los dos primeros cursos el docente califica la entrega.
    if ($i < 2) {
        \core\session\manager::set_user($docente);
        $grade = $assign->get_user_grade($estudiante->id, true);
        $grade->grade = $i === 0 ? 85 : 92;
        $grade->grader = $docente->id;
        $assign->update_grade($grade);
        $comments = $assign->get_feedback_plugin_by_type('comments');
        $comments->save($grade, (object) ['assignfeedbackcomments_editor' => [
            'text' => '<p>Buen trabajo. Revisa la sección de conclusiones y cita las fuentes en formato APA.</p>',
            'format' => FORMAT_HTML,
        ]]);
        assign::send_assignment_notification($docente, $estudiante, 'feedbackavailable', 'assign_notification',
            $now, $cm1, $ctx1, $course, get_string('modulename', 'assign'), $instance->name, false, $grade->id);
        echo "  entrega calificada con {$grade->grade}/100\n";
    }
    rebuild_course_cache($course->id, true);
}

// --- Mensajes directos entre docente y estudiante ---------------------------------
\core\session\manager::set_user($admin);
$conversation = \core_message\api::get_conversation_between_users([$docente->id, $estudiante->id]);
if (!$conversation) {
    $conversation = \core_message\api::create_conversation(
        \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL, [$docente->id, $estudiante->id]);
    $conversationid = $conversation->id;
} else {
    $conversationid = $conversation;
}
if (!$DB->record_exists('messages', ['conversationid' => $conversationid])) {
    \core_message\api::send_message_to_conversation($docente->id, $conversationid,
        'Hola, ya revisé tu primera entrega de Redes. Te dejé comentarios en la tarea.', FORMAT_PLAIN);
    \core_message\api::send_message_to_conversation($estudiante->id, $conversationid,
        'Gracias, profesor. Tengo una duda sobre la tarea 2 de Programación Web: ¿el proyecto puede ser en grupo?', FORMAT_PLAIN);
    \core_message\api::send_message_to_conversation($docente->id, $conversationid,
        'Es individual. Si necesitas más tiempo avísame antes de la fecha de entrega.', FORMAT_PLAIN);
    echo "3 mensajes directos creados entre docente y estudiante\n";
}

// --- Notificación general del sitio para ambos ----------------------------------
foreach ([$docente, $estudiante] as $to) {
    $m = new \core\message\message();
    $m->component = 'moodle';
    $m->name = 'notices';
    $m->userfrom = core_user::get_noreply_user();
    $m->userto = $to;
    $m->subject = 'Bienvenida a Aurora (entorno de pruebas)';
    $m->fullmessage = 'Esta es una notificación de prueba del entorno de desarrollo de Aurora.';
    $m->fullmessageformat = FORMAT_PLAIN;
    $m->fullmessagehtml = '<p>Esta es una <strong>notificación de prueba</strong> del entorno de desarrollo de Aurora.</p>';
    $m->smallmessage = 'Notificación de prueba de Aurora';
    $m->notification = 1;
    $m->contexturl = $CFG->wwwroot . '/my/';
    $m->contexturlname = 'Área personal';
    message_send($m);
}
echo "notificación de bienvenida enviada a ambos\n";

echo "\nnotificaciones en la base de datos:\n";
foreach ([$docente, $estudiante] as $u) {
    $n = $DB->count_records('notifications', ['useridto' => $u->id]);
    echo "  {$u->username}: {$n}\n";
}
echo "listo. Acceso: {$CFG->wwwroot}/login/index.php\n";
