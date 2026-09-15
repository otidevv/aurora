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
 * Admin settings for mod_clasemeet.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_clasemeet/credentialsfile',
        get_string('credentialsfile', 'mod_clasemeet'),
        get_string('credentialsfile_desc', 'mod_clasemeet'),
        $CFG->dataroot . '/clasemeet/service-account.json',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'mod_clasemeet/domain',
        get_string('domain', 'mod_clasemeet'),
        get_string('domain_desc', 'mod_clasemeet'),
        'unamad.edu.pe',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configselect(
        'mod_clasemeet/accesstype',
        get_string('accesstype', 'mod_clasemeet'),
        get_string('accesstype_desc', 'mod_clasemeet'),
        'TRUSTED',
        ['TRUSTED' => 'TRUSTED', 'OPEN' => 'OPEN', 'RESTRICTED' => 'RESTRICTED']
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_clasemeet/sharewithdomain',
        get_string('sharewithdomain', 'mod_clasemeet'),
        get_string('sharewithdomain_desc', 'mod_clasemeet'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'mod_clasemeet/scopes',
        get_string('scopes', 'mod_clasemeet'),
        get_string('scopes_desc', 'mod_clasemeet'),
        implode(',', \mod_clasemeet\google\service_account::DEFAULT_SCOPES),
        PARAM_RAW_TRIMMED
    ));
}
