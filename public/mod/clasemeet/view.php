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

use mod_clasemeet\manager;

require('../../config.php');

$id = required_param('id', PARAM_INT);
$sync = optional_param('sync', 0, PARAM_BOOL);

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
        redirect($url, get_string('recordingsfound', 'mod_clasemeet', $added), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
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
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_clasemeet/view', $templatedata);
echo $OUTPUT->footer();
