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

namespace local_turnitinlimit\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacidad: la tabla guarda qué archivos de qué usuario se bloquearon en qué actividad.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_turnitinlimit', [
            'userid' => 'privacy:metadata:userid',
            'cm' => 'privacy:metadata:cm',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:table');
        $collection->add_user_preference(\local_turnitinlimit\limiter::PREF_EXTRA . '<cmid>', 'privacy:metadata:extra');
        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql("SELECT ctx.id
                                      FROM {local_turnitinlimit} b
                                      JOIN {context} ctx ON ctx.instanceid = b.cm AND ctx.contextlevel = :level
                                     WHERE b.userid = :userid", ['level' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_MODULE) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_turnitinlimit} WHERE cm = :cm",
                ['cm' => $context->instanceid]);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $rows = $DB->get_records('local_turnitinlimit', ['cm' => $context->instanceid, 'userid' => $userid]);
            if ($rows) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_turnitinlimit')],
                    (object) ['blocked' => array_values(array_map(fn($r) => (object) [
                        'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated)], $rows))]);
            }
        }
    }

    /**
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_MODULE) {
            $DB->delete_records('local_turnitinlimit', ['cm' => $context->instanceid]);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_MODULE) {
                $DB->delete_records('local_turnitinlimit', ['cm' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['cm'] = $context->instanceid;
        $DB->delete_records_select('local_turnitinlimit', "cm = :cm AND userid $insql", $params);
    }
}
