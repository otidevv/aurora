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
 * Observador: se ejecuta después del de plagiarism_turnitin (prioridad -100) y aplica el límite al instante.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * @param \core\event\base $event
     */
    public static function after_turnitin_queued(\core\event\base $event): void {
        $cmid = (int) $event->contextinstanceid;
        $userid = (int) ($event->relateduserid ?: $event->userid);
        if ($cmid && $userid) {
            limiter::enforce($cmid, $userid);
        }
    }
}
