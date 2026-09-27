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

namespace local_turnitinlimit\task;

use local_turnitinlimit\limiter;

/**
 * Tarea programada de respaldo: aplica el límite a todo lo que esté en cola.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforce extends \core\task\scheduled_task {
    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('taskenforce', 'local_turnitinlimit');
    }

    /**
     * Ejecuta la revisión.
     */
    public function execute(): void {
        $blocked = limiter::enforce_all();
        if ($blocked) {
            mtrace("local_turnitinlimit: {$blocked} archivo(s) no enviados a Turnitin por superar el límite");
        }
    }
}
