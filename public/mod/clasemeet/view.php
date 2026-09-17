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
 * Meet class page: join button and recordings.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_clasemeet\attendance;
use mod_clasemeet\manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$sync = optional_param('sync', 0, PARAM_BOOL);
$applyattendance = optional_param('applyattendance', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('clasemeet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('clasemeet', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/clasemeet:view', $context);

$canmanage = has_capability('mod/clasemeet:manage', $context);
$url = new moodle_url('/mod/clasemeet/view.php', ['id' => $cm->id]);

if ($sync && $canmanage) {
    require_sesskey();
    try {
        $added = manager::sync_recordings($instance);
        if (!empty($instance->timestart) && time() >= $instance->timestart - 2 * HOURSECS) {
            attendance::sync_participants($instance);
            if (attendance::enabled() && empty($instance->attendancetaken) && attendance::class_is_over($instance)) {
                attendance::apply($instance);
            }
        }
        redirect($url, get_string('recordingsfound', 'mod_clasemeet', $added), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($applyattendance && $canmanage) {
    require_sesskey();
    if (!attendance::class_is_over($instance)) {
        redirect($url, get_string('attendancepending', 'mod_clasemeet'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $written = attendance::apply($instance, !empty($instance->attendancetaken));
    if ($written === null) {
        redirect($url, get_string('attendancenoactivity', 'mod_clasemeet'), null, \core\output\notification::NOTIFY_WARNING);
    }
    redirect($url, get_string('attendanceapplied', 'mod_clasemeet', $written), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$event = \mod_clasemeet\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('clasemeet', $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url($url);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($instance);

$owner = core_user::get_user($instance->ownerid);
$recordings = $DB->get_records('clasemeet_recording', ['clasemeetid' => $instance->id], 'starttime DESC');
$rows = [];
foreach ($recordings as $rec) {
    $duration = ($rec->endtime && $rec->starttime) ? format_time($rec->endtime - $rec->starttime) : '';
    $statekey = 'state_' . $rec->state;
    $rows[] = [
        'date' => $rec->starttime ? userdate($rec->starttime, get_string('strftimedatetimeshort', 'langconfig')) : '',
        'duration' => $duration,
        'state' => get_string_manager()->string_exists($statekey, 'mod_clasemeet')
            ? get_string($statekey, 'mod_clasemeet') : $rec->state,
        'ready' => $rec->state === 'FILE_GENERATED' && !empty($rec->exporturi),
        'inprogress' => $rec->state === 'STARTED',
        'exporturi' => $rec->exporturi,
        'shared' => (bool) $rec->shared,
    ];
}

$hasunshared = false;
foreach ($recordings as $rec) {
    if ($rec->state === 'FILE_GENERATED' && !$rec->shared) {
        $hasunshared = true;
        break;
    }
}
$sharingnotice = '';
if ($canmanage && $hasunshared) {
    if (!get_config('mod_clasemeet', 'sharewithdomain')) {
        $sharingnotice = get_string('sharingdisabled', 'mod_clasemeet');
    } else if (!manager::can_share_recordings($instance->owneremail)) {
        $sharingnotice = get_string('sharingscopemissing', 'mod_clasemeet', \mod_clasemeet\google\service_account::SCOPE_DRIVE);
    }
}

// Meet attendance (teachers only).
$attendancedata = [];
if ($canmanage && !empty($instance->timestart)) {
    $timefmt = get_string('strftimetime', 'langconfig');
    $people = attendance::people($instance);
    $ended = time() > (int) $instance->timeend;
    $resultclass = [
        attendance::PRESENT => 'bg-success',
        attendance::LATE => 'bg-warning text-dark',
        attendance::ABSENT => 'bg-danger',
    ];
    $attrows = [];
    $seen = [];
    foreach ($people as $person) {
        $user = $person->userid ? core_user::get_user($person->userid) : null;
        $result = attendance::classify($instance, $person);
        $typekey = 'participanttype_' . $person->usertype;
        $attrows[] = [
            'name' => $user ? fullname($user) : $person->displayname,
            'meetname' => $user && $person->displayname !== fullname($user) ? $person->displayname : '',
            'email' => $person->email,
            'identified' => (bool) $user,
            'profileurl' => $user ? (new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]))->out(false) : '',
            'typelabel' => get_string_manager()->string_exists($typekey, 'mod_clasemeet') ? get_string($typekey, 'mod_clasemeet') : '',
            'firstjoin' => $person->firstjoin ? userdate($person->firstjoin, $timefmt) : '',
            'lastleave' => $person->connected ? '' : ($person->lastleave ? userdate($person->lastleave, $timefmt) : ''),
            'connected' => $person->connected,
            'time' => format_time($person->duration),
            'percent' => attendance::percent($instance, $person->duration),
            'sessions' => $person->sessions,
            'result' => $ended ? get_string('result_' . $result, 'mod_clasemeet') : '',
            'resultclass' => $resultclass[$result],
        ];
        if ($person->userid) {
            $seen[$person->userid] = true;
        }
    }
    // Students who never joined (only meaningful once the class has ended).
    $found = attendance::attendance_activity((int) $course->id);
    if ($ended && $people) {
        $studentcontext = $found ? context_module::instance($found[1]->id) : context_course::instance($course->id);
        $capability = $found ? 'mod/attendance:canbelisted' : 'mod/clasemeet:view';
        $namefields = implode(', ', array_map(fn($f) => 'u.' . $f, \core_user\fields::get_name_fields()));
        foreach (get_enrolled_users($studentcontext, $capability, 0, 'u.id, u.email, ' . $namefields, 'u.lastname, u.firstname',
                0, 0, true) as $student) {
            if (isset($seen[$student->id]) || has_capability('mod/clasemeet:manage', $context, $student)) {
                continue;
            }
            $attrows[] = [
                'name' => fullname($student),
                'meetname' => '',
                'email' => $student->email,
                'identified' => true,
                'profileurl' => (new moodle_url('/user/view.php', ['id' => $student->id, 'course' => $course->id]))->out(false),
                'typelabel' => '',
                'firstjoin' => '',
                'lastleave' => '',
                'connected' => false,
                'time' => get_string('notjoined', 'mod_clasemeet'),
                'percent' => 0,
                'sessions' => 0,
                'result' => get_string('result_absent', 'mod_clasemeet'),
                'resultclass' => $resultclass[attendance::ABSENT],
            ];
        }
    }

    $sessionurl = '';
    if ($found && !empty($instance->attendancesessionid)) {
        $sessionurl = (new moodle_url('/mod/attendance/take.php', [
            'id' => $found[1]->id,
            'sessionid' => $instance->attendancesessionid,
            'grouptype' => 0,
        ]))->out(false);
    }
    $attendancedata = [
        'rows' => $attrows,
        'hasrows' => !empty($attrows),
        'enabled' => attendance::enabled(),
        'hasactivity' => (bool) $found,
        'taken' => !empty($instance->attendancetaken),
        'takentext' => !empty($instance->attendancetaken)
            ? get_string('attendancetaken', 'mod_clasemeet', userdate($instance->attendancetaken)) : '',
        'sessionurl' => $sessionurl,
        'canapply' => attendance::enabled() && $found && attendance::class_is_over($instance),
        'applyurl' => (new moodle_url($url, ['applyattendance' => 1, 'sesskey' => sesskey()]))->out(false),
        'rules' => get_string('attendancerules', 'mod_clasemeet', (object) [
            'late' => attendance::late_minutes(),
            'percent' => attendance::min_percent(),
        ]),
    ];
}

$status = clasemeet_schedule_status($instance);
$templatedata = [
    'sharingnotice' => $sharingnotice,
    'domain' => manager::domain(),
    'name' => format_string($instance->name),
    'intro' => format_module_intro('clasemeet', $instance, $cm->id),
    'schedule' => clasemeet_format_schedule($instance),
    'status' => $status,
    'statustext' => $status ? get_string('status_' . $status, 'mod_clasemeet') : '',
    'statusclass' => ['upcoming' => 'bg-info', 'live' => 'bg-success', 'ended' => 'bg-secondary'][$status] ?? '',
    'hasspace' => !empty($instance->spacename),
    'meetinguri' => $instance->meetinguri,
    'meetingcode' => $instance->meetingcode,
    'ownername' => $owner ? fullname($owner) : $instance->owneremail,
    'autorecording' => (bool) $instance->autorecording,
    'recordings' => $rows,
    'hasrecordings' => !empty($rows),
    'canmanage' => $canmanage,
    'syncurl' => (new moodle_url($url, ['sync' => 1, 'sesskey' => sesskey()]))->out(false),
    'lastsync' => $instance->lastsync ? userdate($instance->lastsync) : get_string('never', 'mod_clasemeet'),
    'attendance' => $attendancedata ?: false,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_clasemeet/view', $templatedata);
echo $OUTPUT->footer();
