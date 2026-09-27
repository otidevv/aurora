<?php
// Copia la foto de perfil de Google Workspace a la foto de usuario de Moodle. La foto se ve en el perfil,
// en foros, en la lista de participantes, en la asistencia y en las tarjetas de los módulos.
//
// Requiere el scope admin.directory.user.readonly delegado y el uso compartido de contactos activado
// (los mismos que usa Clase Meet para identificar a los participantes de una reunión).
//
// Uso:  php importar_fotos_google.php <correo> [correo...]
//       php importar_fotos_google.php --curso <shortname> [rol]     (por defecto, docentes del curso)
//       php importar_fotos_google.php --todos                       (todas las cuentas del dominio)
//       Añade --forzar para reemplazar fotos que el usuario ya tenga en Moodle.
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->libdir . '/gdlib.php');
require_once($CFG->libdir . '/filelib.php');

use mod_clasemeet\google\service_account;

$args = array_slice($argv, 1);
$forzar = in_array('--forzar', $args, true);
$args = array_values(array_diff($args, ['--forzar']));
if (!$args) {
    fwrite(STDERR, "uso: importar_fotos_google.php <correo...> | --curso <shortname> [rol] | --todos [--forzar]\n");
    exit(1);
}

\core\session\manager::set_user(get_admin());
$domain = (string) (get_config('mod_clasemeet', 'domain') ?: 'unamad.edu.pe');

// --- A quién ------------------------------------------------------------------------------------
$users = [];
if ($args[0] === '--curso') {
    $course = $DB->get_record('course', ['shortname' => $args[1] ?? ''], '*', MUST_EXIST);
    $rolename = $args[2] ?? 'editingteacher';
    $roleid = $DB->get_field('role', 'id', ['shortname' => $rolename], MUST_EXIST);
    $users = get_role_users($roleid, context_course::instance($course->id));
} else if ($args[0] === '--todos') {
    $users = $DB->get_records_select('user', "deleted = 0 AND suspended = 0 AND email LIKE :dom",
        ['dom' => '%@' . $domain]);
} else {
    foreach ($args as $email) {
        $user = $DB->get_record_select('user', 'LOWER(email) = ? AND deleted = 0', [\core_text::strtolower($email)]);
        if ($user) {
            $users[$user->id] = $user;
        } else {
            echo "no existe en Aurora: {$email}\n";
        }
    }
}

// --- Token del directorio -------------------------------------------------------------------------
$directoryuser = (string) (get_config('mod_clasemeet', 'directoryuser') ?: '');
if ($directoryuser === '') {
    foreach ($users as $candidate) {
        if (str_ends_with(\core_text::strtolower($candidate->email), '@' . $domain)) {
            $directoryuser = $candidate->email; // Cualquier cuenta del dominio sirve para consultar.
            break;
        }
    }
}
$token = service_account::from_config()->access_token($directoryuser, [service_account::SCOPE_DIRECTORY]);

$tempdir = make_temp_directory('clasemeet_fotos');
$importadas = $sinfoto = $omitidas = 0;
foreach ($users as $user) {
    if ($user->picture > 0 && !$forzar) {
        echo "ya tiene foto en Aurora: {$user->username}\n";
        $omitidas++;
        continue;
    }

    $curl = new curl();
    $curl->setHeader(['Authorization: Bearer ' . $token]);
    $data = json_decode((string) $curl->get('https://admin.googleapis.com/admin/directory/v1/users/'
        . rawurlencode($user->email) . '?viewType=domain_public'), true);
    if (($curl->get_info()['http_code'] ?? 0) !== 200 || empty($data['thumbnailPhotoUrl'])) {
        echo "sin foto en Google: {$user->username}\n";
        $sinfoto++;
        continue;
    }

    // La URL termina en "=s96-c" (96 px); se pide la versión de 512 px.
    $photourl = preg_replace('~=s\d+(-c)?$~', '=s512', $data['thumbnailPhotoUrl']);
    $download = new curl();
    $image = $download->get($photourl);
    if (($download->get_info()['http_code'] ?? 0) !== 200 || !@getimagesizefromstring((string) $image)) {
        echo "no se pudo descargar la foto de {$user->username}\n";
        $sinfoto++;
        continue;
    }

    $tempfile = $tempdir . '/' . $user->id . '.jpg';
    file_put_contents($tempfile, $image);
    $iconid = process_new_icon(context_user::instance($user->id), 'user', 'icon', 0, $tempfile);
    unlink($tempfile);
    if (!$iconid) {
        echo "Moodle no aceptó la imagen de {$user->username}\n";
        $sinfoto++;
        continue;
    }
    $DB->set_field('user', 'picture', $iconid, ['id' => $user->id]);
    echo "foto importada: {$user->username} ({$user->firstname} {$user->lastname})\n";
    $importadas++;
}

echo "resumen: {$importadas} importada(s), {$sinfoto} sin foto utilizable, {$omitidas} ya tenía(n) foto\n";
