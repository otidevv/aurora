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
 * Enlace "Envíos a Turnitin" en el menú de las actividades que usan Turnitin.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Añade el enlace a la navegación de ajustes del módulo (aparece en el menú "Más" de la actividad).
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 */
function local_turnitinlimit_extend_settings_navigation(settings_navigation $settingsnav, $context) {
    global $PAGE;
    $cm = $PAGE->cm; // Propiedad mágica: empty() no funciona sobre ella.
    if (!$context || (int) $context->contextlevel !== CONTEXT_MODULE || !$cm) {
        return;
    }
    if (!in_array($cm->modname, ['assign', 'forum', 'workshop'], true)) {
        return;
    }
    if (!get_config('local_turnitinlimit', 'enabled') || !has_capability('local/turnitinlimit:manage', $context)) {
        return;
    }
    $modulenode = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulenode) {
        return;
    }
    $url = new moodle_url('/local/turnitinlimit/index.php', ['cmid' => $cm->id]);
    $modulenode->add(get_string('menulink', 'local_turnitinlimit'), $url, navigation_node::TYPE_SETTING,
        null, 'local_turnitinlimit', new pix_icon('i/report', ''));
}
