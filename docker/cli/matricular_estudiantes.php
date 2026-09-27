<?php
// Crea (o actualiza) cuentas de estudiantes y las matricula en un curso. El número de documento (DNI) se
// guarda en el campo "Número de ID" de Moodle y se muestra a los docentes en la lista de participantes.
// Idempotente: se puede volver a ejecutar; no cambia los nombres ni el documento si ya coinciden.
//
// Uso:  php matricular_estudiantes.php <shortname-del-curso> <archivo.csv> [rol]
//
// El CSV lleva una fila por estudiante, con cabecera opcional:
//   correo,documento,nombres,apellidos
// Si faltan nombres y apellidos se consultan en el directorio de Google Workspace.
// El archivo tiene datos personales: guárdalo fuera del repositorio.
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

use mod_clasemeet\google\service_account;

[$script, $shortname, $csvfile] = array_pad($argv, 3, null);
$rolename = $argv[3] ?? 'student';
if (!$shortname || !$csvfile || !is_readable($csvfile)) {
    fwrite(STDERR, "uso: matricular_estudiantes.php <shortname> <archivo.csv> [rol]\n");
    exit(1);
}

\core\session\manager::set_user(get_admin());
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
$roleid = $DB->get_field('role', 'id', ['shortname' => $rolename], MUST_EXIST);
$context = context_course::instance($course->id);

// El documento se muestra a docentes y gestores junto al correo en las listas de participantes.
$identity = array_filter(explode(',', (string) get_config(null, 'showuseridentity')));
if (!in_array('idnumber', $identity)) {
    $identity[] = 'idnumber';
    set_config('showuseridentity', implode(',', $identity));
    echo "ajuste: el número de documento se mostrará en las listas de participantes\n";
}

/** Nombre oficial desde el directorio de Workspace (requiere el uso compartido de contactos). */
$directoryname = function (string $email): array {
    static $token = null;
    try {
        $token = $token ?? service_account::from_config()->access_token(
            get_config('mod_clasemeet', 'directoryuser') ?: $email, [service_account::SCOPE_DIRECTORY]);
        $curl = new curl();
        $curl->setHeader(['Authorization: Bearer ' . $token]);
        $data = json_decode((string) $curl->get('https://admin.googleapis.com/admin/directory/v1/users/'
            . rawurlencode($email) . '?viewType=domain_public'), true);
        if (($curl->get_info()['http_code'] ?? 0) !== 200) {
            return ['', ''];
        }
        return [(string) ($data['name']['givenName'] ?? ''), (string) ($data['name']['familyName'] ?? '')];
    } catch (\Throwable $e) {
        return ['', ''];
    }
};

/** "GIULIANA DEL PILAR" -> "Giuliana del Pilar" (las partículas quedan en minúscula). */
$titlecase = function (string $name): string {
    $small = ['de', 'del', 'la', 'las', 'los', 'y', 'da', 'do', 'van', 'von'];
    $parts = preg_split('/\s+/', \core_text::strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($parts as $i => $part) {
        $parts[$i] = ($i > 0 && in_array($part, $small, true)) ? $part : \core_text::strtotitle($part);
    }
    return implode(' ', $parts);
};

$handle = fopen($csvfile, 'r');
$creados = $actualizados = $matriculados = $yaestaban = 0;
while (($row = fgetcsv($handle, 0, ',')) !== false) {
    $row = array_map('trim', $row);
    $email = \core_text::strtolower($row[0] ?? '');
    if ($email === '' || !validate_email($email) || str_starts_with($email, '#')) {
        continue; // Cabecera, comentario o línea vacía.
    }
    $documento = preg_replace('/\D/', '', $row[1] ?? '');
    [$first, $last] = [$row[2] ?? '', $row[3] ?? ''];
    if ($first === '' || $last === '') {
        [$gfirst, $glast] = $directoryname($email);
        $first = $first ?: $gfirst;
        $last = $last ?: $glast;
    }
    $first = $titlecase($first ?: explode('@', $email)[0]);
    $last = $titlecase($last ?: '');

    $user = $DB->get_record_select('user', 'LOWER(email) = ? AND deleted = 0', [$email]);
    if (!$user) {
        $userid = user_create_user((object) [
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => explode('@', $email)[0],
            'email' => $email,
            'idnumber' => $documento,
            'firstname' => $first,
            'lastname' => $last,
            'lang' => 'es',
            // Entran con "Acceder con correo @unamad.edu.pe"; no se les pide contraseña.
            'password' => complex_random_string(20) . 'aA1!',
        ], true, false);
        $user = core_user::get_user($userid);
        echo "creado: {$user->username} ({$first} {$last}) doc {$documento}\n";
        $creados++;
    } else {
        $update = (object) ['id' => $user->id];
        $cambios = [];
        if ($documento !== '' && $user->idnumber !== $documento) {
            $update->idnumber = $documento;
            $cambios[] = 'documento';
        }
        if ($user->firstname !== $first || $user->lastname !== $last) {
            $update->firstname = $first;
            $update->lastname = $last;
            $cambios[] = 'nombre';
        }
        if ($cambios) {
            user_update_user($update, false, false);
            echo "actualizado: {$user->username} (" . implode(', ', $cambios) . ")\n";
            $actualizados++;
        }
    }

    if (is_enrolled($context, $user)) {
        role_assign($roleid, $user->id, $context->id);
        $yaestaban++;
        continue;
    }
    if (!enrol_try_internal_enrol($course->id, $user->id, $roleid)) {
        $manual = enrol_get_plugin('manual');
        $manual->add_default_instance($course);
        enrol_try_internal_enrol($course->id, $user->id, $roleid);
    }
    echo "matriculado en {$course->shortname}: {$user->username}\n";
    $matriculados++;
}
fclose($handle);

echo "resumen: {$creados} creado(s), {$actualizados} actualizado(s), {$matriculados} matriculado(s), "
    . "{$yaestaban} ya estaba(n) en el curso\n";
echo "participantes: {$CFG->wwwroot}/user/index.php?id={$course->id}\n";
