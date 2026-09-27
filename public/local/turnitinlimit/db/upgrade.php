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

/**
 * Actualizaciones de la base de datos.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_turnitinlimit_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026092703) {
        // La tabla pasa de "archivos bloqueados" a "registro propio de todos los envíos".
        $table = new xmldb_table('local_turnitinlimit');
        $field = new xmldb_field('identifier', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'fileid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('blocked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('local_turnitinlimit', 'blocked', 1); // Todo lo registrado hasta ahora eran bloqueos.
        }
        upgrade_plugin_savepoint(true, 2026092703, 'local', 'turnitinlimit');
    }

    return true;
}
