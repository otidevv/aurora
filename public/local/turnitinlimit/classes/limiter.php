<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_turnitinlimit;

/**
 * Aplica el límite de envíos a Turnitin por estudiante y actividad.
 *
 * plagiarism_turnitin encola cada archivo en plagiarism_turnitin_files ('queued') y su tarea programada lo
 * envía. Ese plugin BORRA sus filas cuando el estudiante elimina la entrega, así que el conteo no puede
 * apoyarse en ellas: este plugin lleva su propio registro (tabla local_turnitinlimit) de cada archivo que
 * vio encolado, con la marca 'blocked' para los que no se enviaron por superar el límite.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class limiter {
    /** @var string Preferencia de usuario con envíos extra autorizados por el docente, por actividad. */
    public const PREF_EXTRA = 'local_turnitinlimit_extra_';

    /**
     * Límite vigente para un estudiante en una actividad (ajuste global + extras autorizados).
     *
     * @param int $cmid
     * @param int $userid
     * @return int
     */
    public static function limit_for(int $cmid, int $userid): int {
        $max = (int) get_config('local_turnitinlimit', 'maxsends');
        if ($max <= 0) {
            $max = 3;
        }
        $extra = (int) get_user_preferences(self::PREF_EXTRA . $cmid, 0, $userid);
        return $max + max(0, $extra);
    }

    /**
     * Registra en el libro propio los archivos del plugin de Turnitin que aún no conocemos.
     *
     * @param int $cmid
     * @param int $userid
     */
    public static function sync(int $cmid, int $userid): void {
        global $DB;
        $sql = "SELECT f.id, f.identifier
                  FROM {plagiarism_turnitin_files} f
             LEFT JOIN {local_turnitinlimit} l ON l.fileid = f.id
                 WHERE f.cm = :cm AND f.userid = :userid AND l.id IS NULL
              ORDER BY f.id ASC";
        foreach ($DB->get_records_sql($sql, ['cm' => $cmid, 'userid' => $userid]) as $row) {
            // El mismo archivo (mismo hash) que vuelve a encolarse tras borrar la entrega no es un envío nuevo.
            $known = $DB->get_record('local_turnitinlimit', ['cm' => $cmid, 'userid' => $userid,
                'identifier' => $row->identifier]);
            if ($known) {
                $DB->set_field('local_turnitinlimit', 'fileid', $row->id, ['id' => $known->id]);
                continue;
            }
            $DB->insert_record('local_turnitinlimit', (object) [
                'fileid' => $row->id,
                'identifier' => (string) $row->identifier,
                'cm' => $cmid,
                'userid' => $userid,
                'blocked' => 0,
                'timecreated' => time(),
            ]);
        }
    }

    /**
     * Envíos que cuentan para el límite: todo archivo registrado que no fue bloqueado.
     *
     * @param int $cmid
     * @param int $userid
     * @return int
     */
    public static function sends_used(int $cmid, int $userid): int {
        global $DB;
        self::sync($cmid, $userid);
        return $DB->count_records('local_turnitinlimit', ['cm' => $cmid, 'userid' => $userid, 'blocked' => 0]);
    }

    /**
     * Archivos bloqueados por el límite.
     *
     * @param int $cmid
     * @param int $userid
     * @return int
     */
    public static function blocked_count(int $cmid, int $userid): int {
        global $DB;
        return $DB->count_records('local_turnitinlimit', ['cm' => $cmid, 'userid' => $userid, 'blocked' => 1]);
    }

    /**
     * Bloquea las filas encoladas que superen el límite. Devuelve cuántas bloqueó.
     *
     * @param int $cmid
     * @param int $userid
     * @return int
     */
    public static function enforce(int $cmid, int $userid): int {
        global $DB;
        if (!get_config('local_turnitinlimit', 'enabled')) {
            return 0;
        }
        self::sync($cmid, $userid);
        $limit = self::limit_for($cmid, $userid);

        $ledger = $DB->get_records('local_turnitinlimit', ['cm' => $cmid, 'userid' => $userid, 'blocked' => 0], 'id ASC');
        $blocked = 0;
        $position = 0;
        foreach ($ledger as $entry) {
            $position++;
            if ($position <= $limit) {
                continue;
            }
            $file = $DB->get_record('plagiarism_turnitin_files', ['id' => $entry->fileid]);
            if (!$file || !in_array($file->statuscode, ['queued', 'pending'], true)) {
                continue; // Ya se envió (o el plugin lo borró): no hay nada que detener.
            }
            $DB->update_record('plagiarism_turnitin_files', (object) [
                'id' => $file->id,
                'statuscode' => 'error',
                'errorcode' => 0,
                'errormsg' => get_string('limitreached', 'local_turnitinlimit', $limit),
                'lastmodified' => time(),
            ]);
            $DB->set_field('local_turnitinlimit', 'blocked', 1, ['id' => $entry->id]);
            $blocked++;
        }
        return $blocked;
    }

    /**
     * Revisa toda la cola de Turnitin (tarea de respaldo).
     *
     * @return int filas bloqueadas
     */
    public static function enforce_all(): int {
        global $DB;
        $pairs = $DB->get_records_sql("SELECT DISTINCT f.cm, f.userid
                                         FROM {plagiarism_turnitin_files} f
                                        WHERE f.statuscode IN ('queued', 'pending')");
        $blocked = 0;
        foreach ($pairs as $pair) {
            $blocked += self::enforce((int) $pair->cm, (int) $pair->userid);
        }
        return $blocked;
    }

    /**
     * Autoriza envíos adicionales y vuelve a encolar los archivos bloqueados más recientes que aún existan.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $extra
     * @return int archivos vueltos a encolar
     */
    public static function grant_extra(int $cmid, int $userid, int $extra): int {
        global $DB;
        $current = (int) get_user_preferences(self::PREF_EXTRA . $cmid, 0, $userid);
        set_user_preference(self::PREF_EXTRA . $cmid, $current + $extra, $userid);

        $requeued = 0;
        $entries = $DB->get_records('local_turnitinlimit', ['cm' => $cmid, 'userid' => $userid, 'blocked' => 1],
            'id DESC', '*', 0, $extra);
        foreach ($entries as $entry) {
            $DB->set_field('local_turnitinlimit', 'blocked', 0, ['id' => $entry->id]);
            $file = $DB->get_record('plagiarism_turnitin_files', ['id' => $entry->fileid]);
            if ($file && $file->statuscode === 'error') {
                $DB->update_record('plagiarism_turnitin_files', (object) [
                    'id' => $file->id, 'statuscode' => 'queued', 'errorcode' => null, 'errormsg' => null,
                    'lastmodified' => time(),
                ]);
                $requeued++;
            }
        }
        return $requeued;
    }
}
