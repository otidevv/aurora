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

namespace mod_clasemeet\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the only personal data stored is who owns each Meet space.
 *
 * @package    mod_clasemeet
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
        $collection->add_database_table('clasemeet', [
            'ownerid' => 'privacy:metadata:clasemeet:ownerid',
            'owneremail' => 'privacy:metadata:clasemeet:owneremail',
        ], 'privacy:metadata:clasemeet');
        $collection->add_external_location_link('google', [
            'owneremail' => 'privacy:metadata:clasemeet:owneremail',
        ], 'privacy:metadata:google');
        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {clasemeet} c
                  JOIN {course_modules} cm ON cm.instance = c.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'clasemeet'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE c.ownerid = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['modlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT c.ownerid
                  FROM {clasemeet} c
                  JOIN {course_modules} cm ON cm.instance = c.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'clasemeet'
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('ownerid', $sql, ['cmid' => $context->instanceid]);
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
            $cm = get_coursemodule_from_id('clasemeet', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $instance = $DB->get_record('clasemeet', ['id' => $cm->instance, 'ownerid' => $userid]);
            if ($instance) {
                writer::with_context($context)->export_data([], (object) [
                    'name' => $instance->name,
                    'owneremail' => $instance->owneremail,
                    'meetinguri' => $instance->meetinguri,
                ]);
            }
        }
    }

    /**
     * The owner link is course content, not user-owned data: we anonymise instead of deleting the class.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('clasemeet', $context->instanceid);
        if ($cm) {
            $DB->set_field('clasemeet', 'ownerid', 0, ['id' => $cm->instance]);
        }
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('clasemeet', $context->instanceid);
            if ($cm) {
                $DB->set_field('clasemeet', 'ownerid', 0, ['id' => $cm->instance, 'ownerid' => $userid]);
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
        $cm = get_coursemodule_from_id('clasemeet', $context->instanceid);
        if (!$cm) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['instance'] = $cm->instance;
        $DB->set_field_select('clasemeet', 'ownerid', 0, "id = :instance AND ownerid $insql", $params);
    }
}
