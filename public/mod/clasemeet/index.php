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
 * List of Meet classes in a course.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/clasemeet/index.php', ['id' => $id]);
$PAGE->set_pagelayout('incourse');
$strplural = get_string('modulenameplural', 'mod_clasemeet');
$PAGE->set_title($strplural);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($strplural);

echo $OUTPUT->header();
echo $OUTPUT->heading($strplural);

$modinfo = get_fast_modinfo($course);
$table = new html_table();
$table->head = [get_string('name'), get_string('owner', 'mod_clasemeet'), 'Google Meet'];
foreach ($modinfo->get_instances_of('clasemeet') as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    $instance = $DB->get_record('clasemeet', ['id' => $cm->instance]);
    $owner = $instance ? core_user::get_user($instance->ownerid) : null;
    $table->data[] = [
        html_writer::link(new moodle_url('/mod/clasemeet/view.php', ['id' => $cm->id]), format_string($cm->name)),
        $owner ? fullname($owner) : '',
        $instance && $instance->meetinguri
            ? html_writer::link($instance->meetinguri, $instance->meetingcode, ['target' => '_blank', 'rel' => 'noopener'])
            : '',
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
