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
 * Envíos a Turnitin por estudiante en una actividad, con autorización de envíos adicionales.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_turnitinlimit\limiter;

require('../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$targetuser = optional_param('userid', 0, PARAM_INT);
$amount = optional_param('amount', 1, PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/turnitinlimit:manage', $context);

$url = new moodle_url('/local/turnitinlimit/index.php', ['cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('menulink', 'local_turnitinlimit'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'grant' && $targetuser) {
    require_sesskey();
    $amount = max(1, min(10, $amount));
    $requeued = limiter::grant_extra($cmid, $targetuser, $amount);
    $user = core_user::get_user($targetuser, '*', MUST_EXIST);
    redirect($url, get_string('granted', 'local_turnitinlimit', (object) [
        'name' => fullname($user), 'amount' => $amount, 'requeued' => $requeued,
    ]), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Estudiantes matriculados que pueden entregar en la actividad.
$students = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true);
if (!$students) {
    // Foros y talleres: todos los matriculados sin permiso de gestionar la actividad.
    $students = array_filter(get_enrolled_users($context, '', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true),
        fn($u) => !has_capability('local/turnitinlimit:manage', $context, $u));
}

$table = new html_table();
$table->head = [
    get_string('student', 'local_turnitinlimit'),
    get_string('sendsused', 'local_turnitinlimit'),
    get_string('limit', 'local_turnitinlimit'),
    get_string('blocked', 'local_turnitinlimit'),
    get_string('latest', 'local_turnitinlimit'),
    '',
];
$table->attributes['class'] = 'generaltable';
$statuses = [
    'success' => get_string('status_success', 'local_turnitinlimit'),
    'queued' => get_string('status_queued', 'local_turnitinlimit'),
    'pending' => get_string('status_queued', 'local_turnitinlimit'),
    'error' => get_string('status_error', 'local_turnitinlimit'),
];
foreach ($students as $student) {
    $used = limiter::sends_used($cmid, $student->id);
    $limit = limiter::limit_for($cmid, $student->id);
    $blocked = limiter::blocked_count($cmid, $student->id);
    $lastrow = $DB->get_records('plagiarism_turnitin_files', ['cm' => $cmid, 'userid' => $student->id], 'id DESC', '*', 0, 1);
    $latest = '';
    if ($lastrow) {
        $last = reset($lastrow);
        $latest = $statuses[$last->statuscode] ?? $last->statuscode;
        if ($last->statuscode === 'success' && $last->similarityscore !== null) {
            $latest .= ' · ' . get_string('similarity', 'local_turnitinlimit', $last->similarityscore);
        }
        $latest .= html_writer::tag('div', userdate($last->lastmodified, get_string('strftimedatetimeshort', 'langconfig')),
            ['class' => 'text-muted small']);
    }

    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'grant'])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $student->id])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'amount', 'value' => 1])
        . html_writer::tag('button', get_string('grantone', 'local_turnitinlimit'),
            ['type' => 'submit', 'class' => 'btn btn-outline-primary btn-sm'])
        . html_writer::end_tag('form');

    $badgeclass = $used >= $limit ? 'bg-danger' : ($used > 0 ? 'bg-primary' : 'bg-secondary');
    $table->data[] = [
        html_writer::link(new moodle_url('/user/view.php', ['id' => $student->id, 'course' => $course->id]), fullname($student))
            . html_writer::tag('div', s($student->email), ['class' => 'text-muted small']),
        html_writer::tag('span', $used, ['class' => 'badge ' . $badgeclass]),
        $limit,
        $blocked ?: '',
        $latest,
        $form,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pagetitle', 'local_turnitinlimit', format_string($cm->name)));
echo html_writer::tag('p', get_string('pageintro', 'local_turnitinlimit', (int) get_config('local_turnitinlimit', 'maxsends') ?: 3));
if ($students) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nostudents', 'local_turnitinlimit'), 'info');
}
echo $OUTPUT->footer();
