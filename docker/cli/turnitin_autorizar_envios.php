<?php
// Autoriza envíos adicionales a Turnitin a un estudiante en una actividad (cuando alcanzó el límite del
// plugin local_turnitinlimit) y vuelve a encolar sus archivos bloqueados más recientes.
//
// Uso:  php turnitin_autorizar_envios.php <correo-del-estudiante> <cmid-de-la-actividad> [cantidad=1]
//       php turnitin_autorizar_envios.php --estado <cmid>        (muestra envíos usados y límite por estudiante)
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';

use local_turnitinlimit\limiter;

\core\session\manager::set_user(get_admin());

if (($argv[1] ?? '') === '--estado') {
    $cmid = (int) ($argv[2] ?? 0);
    $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
    echo "Actividad: {$cm->name} (cmid {$cmid})\n";
    $rows = $DB->get_records_sql("SELECT DISTINCT f.userid FROM {plagiarism_turnitin_files} f WHERE f.cm = :cm", ['cm' => $cmid]);
    foreach ($rows as $r) {
        $u = core_user::get_user($r->userid);
        printf("  %-40s envíos usados: %d / límite: %d\n", fullname($u) . " <{$u->email}>",
            limiter::sends_used($cmid, $u->id), limiter::limit_for($cmid, $u->id));
    }
    exit(0);
}

[$script, $email, $cmid] = array_pad($argv, 3, null);
$extra = max(1, (int) ($argv[3] ?? 1));
if (!$email || !$cmid) {
    fwrite(STDERR, "uso: turnitin_autorizar_envios.php <correo> <cmid> [cantidad] | --estado <cmid>\n");
    exit(1);
}
$user = $DB->get_record_select('user', 'LOWER(email) = ? AND deleted = 0', [core_text::strtolower($email)], '*', MUST_EXIST);
$cm = get_coursemodule_from_id('', (int) $cmid, 0, false, MUST_EXIST);

$requeued = limiter::grant_extra((int) $cmid, $user->id, $extra);
echo "{$user->firstname} {$user->lastname} en '{$cm->name}': +{$extra} envío(s) autorizado(s), "
    . "límite ahora " . limiter::limit_for((int) $cmid, $user->id) . ", archivos vueltos a encolar: {$requeued}\n";
