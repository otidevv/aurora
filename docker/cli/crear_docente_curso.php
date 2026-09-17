<?php
// Crea (o reutiliza) un usuario docente y un curso de prueba, y lo matricula como profesor editor.
//
// Uso (dentro del contenedor web):
//   php crear_docente_curso.php <correo> "<Nombre>" "<Apellidos>" "<Nombre del curso>" <shortname> [password]
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

[$script, $email, $firstname, $lastname, $coursename, $shortname] = array_pad($argv, 6, null);
$password = $argv[6] ?? null;
if (!$email || !$firstname || !$lastname || !$coursename || !$shortname) {
    fwrite(STDERR, "uso: crear_docente_curso.php <correo> <nombre> <apellidos> <curso> <shortname> [password]\n");
    exit(1);
}
$email = core_text::strtolower($email);
$username = explode('@', $email)[0];

\core\session\manager::set_user(get_admin());

// --- Usuario ---------------------------------------------------------------
$user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
if (!$user) {
    $new = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $username,
        'email' => $email,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'lang' => 'es',
        'password' => $password ?: '',
    ];
    if (!$password) {
        $new->password = complex_random_string(16) . 'aA1!';
    }
    $userid = user_create_user($new, true, false);
    $user = core_user::get_user($userid);
    set_user_preference('auth_forcepasswordchange', 1, $user);
    echo "usuario creado: {$user->username} <{$user->email}> (id {$user->id})\n";
} else {
    echo "usuario existente: {$user->username} <{$user->email}> (id {$user->id})\n";
    if ($password) {
        update_internal_user_password($user, $password);
        echo "contraseña actualizada\n";
    }
}

// --- Curso -----------------------------------------------------------------
$course = $DB->get_record('course', ['shortname' => $shortname]);
if (!$course) {
    $category = core_course_category::get_default();
    $course = create_course((object) [
        'fullname' => $coursename,
        'shortname' => $shortname,
        'category' => $category->id,
        'format' => 'topics',
        'numsections' => 4,
        'visible' => 1,
        'summary' => 'Curso de prueba para clases en Google Meet con grabación automática.',
        'summaryformat' => FORMAT_HTML,
        'startdate' => time(),
    ]);
    echo "curso creado: {$course->fullname} [{$course->shortname}] (id {$course->id})\n";
} else {
    echo "curso existente: {$course->fullname} [{$course->shortname}] (id {$course->id})\n";
}

// --- Matrícula como profesor editor -------------------------------------------
$roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
$context = context_course::instance($course->id);
if (!is_enrolled($context, $user)) {
    if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
        // El método manual puede no existir en el curso: lo añadimos y reintentamos.
        $manual = enrol_get_plugin('manual');
        $manual->add_default_instance($course);
        enrol_try_internal_enrol($course->id, $user->id, $roleid);
    }
    echo "matriculado como profesor editor\n";
} else {
    role_assign($roleid, $user->id, $context->id);
    echo "ya matriculado; rol de profesor editor asegurado\n";
}

echo "URL del curso: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
