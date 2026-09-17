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

namespace mod_clasemeet\task;

use mod_clasemeet\attendance;
use mod_clasemeet\manager;

/**
 * Scheduled task: pull new recordings and participants for every Meet class, and write attendance.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_recordings extends \core\task\scheduled_task {
    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksyncrecordings', 'mod_clasemeet');
    }

    /**
     * Sync every class that has a Meet space.
     */
    public function execute(): void {
        global $DB;

        $instances = $DB->get_records_select('clasemeet', "spacename <> ''");
        foreach ($instances as $instance) {
            try {
                $added = manager::sync_recordings($instance);
                mtrace("clasemeet {$instance->id} ({$instance->name}): {$added} new recording(s)");
            } catch (\Throwable $e) {
                mtrace("clasemeet {$instance->id} ({$instance->name}): ERROR " . $e->getMessage());
            }

            if (!attendance::should_poll($instance)) {
                continue;
            }
            try {
                $count = attendance::sync_participants($instance);
                mtrace("clasemeet {$instance->id}: {$count} participant(s)");
                if (attendance::enabled() && attendance::class_is_over($instance)) {
                    $written = attendance::apply($instance);
                    mtrace("clasemeet {$instance->id}: attendance " .
                        ($written === null ? 'not written (no attendance activity in the course)' : "{$written} log(s) written"));
                }
            } catch (\Throwable $e) {
                mtrace("clasemeet {$instance->id}: attendance ERROR " . $e->getMessage());
            }
        }
    }
}
