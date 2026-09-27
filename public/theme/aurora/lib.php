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
 * Funciones del tema Aurora.
 *
 * @package    theme_aurora
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * SCSS principal: el preset por defecto de Boost (Bootstrap + Moodle).
 *
 * @param theme_config $theme
 * @return string
 */
function theme_aurora_get_main_scss_content($theme) {
    global $CFG;

    return file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
}

/**
 * Variables del sistema de diseño Aurora: van antes del preset para que Bootstrap
 * y Boost las tomen como valores por defecto.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_aurora_get_pre_scss($theme) {
    global $CFG;

    $scss = file_get_contents($CFG->dirroot . '/theme/aurora/scss/pre.scss');

    if (defined('BEHAT_SITE_RUNNING')) {
        $scss .= "\$behatsite: true;\n";
    }

    return $scss;
}

/**
 * Estilos del header, contenido y bloques: van después del preset.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_aurora_get_extra_scss($theme) {
    global $CFG;

    return file_get_contents($CFG->dirroot . '/theme/aurora/scss/post.scss');
}
