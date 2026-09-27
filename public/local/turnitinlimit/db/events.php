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
 * Se observan los mismos eventos que plagiarism_turnitin, con prioridad menor para actuar justo después
 * de que ese plugin haya encolado el archivo.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [];
foreach ([
    '\assignsubmission_file\event\assessable_uploaded',
    '\assignsubmission_onlinetext\event\assessable_uploaded',
    '\mod_assign\event\assessable_submitted',
    '\mod_workshop\event\assessable_uploaded',
    '\mod_forum\event\assessable_uploaded',
] as $eventname) {
    $observers[] = [
        'eventname' => $eventname,
        'callback'  => '\local_turnitinlimit\observer::after_turnitin_queued',
        'internal'  => false,
        'priority'  => -100,
    ];
}
