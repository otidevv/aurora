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

namespace theme_aurora;

use core\hook\output\before_standard_head_html_generation;

/**
 * Callbacks de hooks del tema Aurora.
 *
 * @package    theme_aurora
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Carga la tipografía Inter (sistema de diseño Aurora) en el <head> de todas las páginas del tema.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if ($PAGE->theme->name !== 'aurora') {
            return;
        }

        $hook->add_html(
            '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n" .
            '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n" .
            '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">' . "\n"
        );
    }
}
