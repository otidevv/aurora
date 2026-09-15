<?php
// Matricula (creando la cuenta si no existe) a un usuario en un curso con el rol indicado.
//
// Uso (dentro del contenedor web):
//   php matricular_usuario.php <username_o_correo> <shortname_curso> <rol: student|editingteacher|teacher> ["Nombre" "Apellidos" [password]]
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');

[$script, $who, $shortname, $roleshort] = array_pad($argv, 4, null);
$firstname = $argv[4] ?? null;
$lastname = $argv[5] ?? null;
$password = $argv[6] ?? null;
if (!$who || !$shortname || !$roleshort) {
    fwrite(STDERR, "uso: matricular_usuario.php <username|correo> <shortname> <rol> [nombre apellidos [password]]\n");
    exit(1);
}

\core\session\manager::set_user(get_admin());

$who = core_text::strtolower($who);
$user = $DB->get_record_select('user', 'deleted = 0 AND (username = :u OR email = :e)', ['u' => $who, 'e' => $who]);
if (!$user) {
    if (!$firstname || !$lastname) {
        fwrite(STDERR, "el usuario no existe: indica nombre y apellidos para crearlo\n");
        exit(1);
    }
    $isemail = str_contains($who, '@');
    $new = (object) [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $isemail ? explode('@', $who)[0] : $who,
        'email' => $isemail ? $who : $who . '@example.com',
        'firstname' => $firstname,
        'lastname' => $lastname,
        'lang' => 'es',
        'password' => $password ?: complex_random_string(16) . 'aA1!',
    ];
    $user = core_user::get_user(user_create_user($new, true, false));
    echo "usuario creado: {$user->username} <{$user->email}> (id {$user->id})\n";
} else {
    echo "usuario existente: {$user->username} <{$user->email}> (id {$user->id}, auth {$user->auth})\n";
    if ($password && $user->auth === 'manual') {
        update_internal_user_password($user, $password);
        echo "contraseña actualizada\n";
    }
}

$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$roleid = $DB->get_field('role', 'id', ['shortname' => $roleshort], MUST_EXIST);
$context = context_course::instance($course->id);

if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
    $manual = enrol_get_plugin('manual');
    $manual->add_default_instance($course);
    enrol_try_internal_enrol($course->id, $user->id, $roleid);
}
role_assign($roleid, $user->id, $context->id);
echo "matriculado en {$course->shortname} como {$roleshort}\n";
echo "curso: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
