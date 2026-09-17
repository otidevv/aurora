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
 * Library of interface functions for mod_clasemeet.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_clasemeet\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Features supported by the module.
 *
 * @param string $feature FEATURE_xx constant
 * @return mixed
 */
function clasemeet_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_OTHER,
        FEATURE_GROUPS => false,
        FEATURE_GROUPINGS => false,
        FEATURE_MOD_INTRO => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_BACKUP_MOODLE2 => false,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COMMUNICATION,
        default => null,
    };
}

/**
 * Add a Meet class: creates the Google Meet space in the teacher's name.
 *
 * @param stdClass $data form data
 * @param mod_clasemeet_mod_form|null $mform
 * @return int new instance id
 */
function clasemeet_add_instance($data, $mform = null) {
    global $DB, $USER;

    $data->ownerid = $USER->id;
    $data->owneremail = core_text::strtolower(trim($USER->email));
    $data->autorecording = empty($data->autorecording) ? 0 : 1;
    $data->autotranscription = empty($data->autotranscription) ? 0 : 1;
    $data->timestart = (int) ($data->timestart ?? 0);
    $data->timeend = (int) ($data->timeend ?? 0);
    $data->googleeventid = '';
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->lastsync = 0;

    // Creates spacename / meetingcode / meetinguri; throws a moodle_exception the user sees if Google refuses.
    manager::create_space($data);

    $data->id = $DB->insert_record('clasemeet', $data);
    manager::update_moodle_calendar($data);
    manager::update_google_calendar($data);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'clasemeet', $data->id, $completiontimeexpected);

    return $data->id;
}

/**
 * Update a Meet class. The Meet space is kept; only name/description change.
 * If the space could not be created earlier, we try again now.
 *
 * @param stdClass $data
 * @param mod_clasemeet_mod_form|null $mform
 * @return bool
 */
function clasemeet_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $current = $DB->get_record('clasemeet', ['id' => $data->id], '*', MUST_EXIST);
    $data->timemodified = time();

    if (empty($current->spacename)) {
        $data->owneremail = $current->owneremail;
        $data->autorecording = empty($data->autorecording) ? 0 : 1;
        $data->autotranscription = empty($data->autotranscription) ? 0 : 1;
        manager::create_space($data);
    } else {
        // Recording settings are fixed once the space exists.
        unset($data->autorecording, $data->autotranscription);
    }

    $data->timestart = (int) ($data->timestart ?? 0);
    $data->timeend = (int) ($data->timeend ?? 0);
    $DB->update_record('clasemeet', $data);

    $instance = $DB->get_record('clasemeet', ['id' => $data->id], '*', MUST_EXIST);
    manager::update_moodle_calendar($instance);
    manager::update_google_calendar($instance);

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'clasemeet', $data->id, $completiontimeexpected);

    return true;
}

/**
 * Delete a Meet class (the Google space itself stays; Meet has no delete endpoint).
 *
 * @param int $id
 * @return bool
 */
function clasemeet_delete_instance($id) {
    global $DB;

    if (!$instance = $DB->get_record('clasemeet', ['id' => $id])) {
        return false;
    }
    $cm = get_coursemodule_from_instance('clasemeet', $id);
    if ($cm) {
        \core_completion\api::update_completion_date_event($cm->id, 'clasemeet', $id, null);
    }
    $DB->delete_records('event', ['modulename' => 'clasemeet', 'instance' => $id]);
    $DB->delete_records('clasemeet_recording', ['clasemeetid' => $id]);
    $DB->delete_records('clasemeet_participant', ['clasemeetid' => $id]);
    $DB->delete_records('clasemeet', ['id' => $id]);
    return true;
}

/**
 * Human-readable schedule of a class, e.g. "lunes, 15 de septiembre de 2026, 08:00 – 10:00".
 *
 * @param stdClass $instance with timestart / timeend
 * @return string empty when not scheduled
 */
function clasemeet_format_schedule(stdClass $instance): string {
    if (empty($instance->timestart)) {
        return '';
    }
    $start = userdate($instance->timestart, get_string('strftimedaydatetime', 'langconfig'));
    if (empty($instance->timeend) || $instance->timeend <= $instance->timestart) {
        return $start;
    }
    $sameday = userdate($instance->timestart, '%Y%m%d') === userdate($instance->timeend, '%Y%m%d');
    $end = userdate($instance->timeend, get_string($sameday ? 'strftimetime24' : 'strftimedaydatetime', 'langconfig'));
    return $start . ' – ' . $end;
}

/**
 * Schedule status: upcoming, live or ended (empty when not scheduled).
 *
 * @param stdClass $instance
 * @return string
 */
function clasemeet_schedule_status(stdClass $instance): string {
    if (empty($instance->timestart)) {
        return '';
    }
    $now = time();
    if ($now < $instance->timestart) {
        return 'upcoming';
    }
    if (!empty($instance->timeend) && $now > $instance->timeend) {
        return 'ended';
    }
    return 'live';
}

/**
 * Show schedule and join link directly on the course page under the activity name.
 *
 * @param cm_info $cm
 */
function clasemeet_cm_info_view(cm_info $cm) {
    global $DB;
    if (!$cm->uservisible) {
        return;
    }
    $instance = $DB->get_record('clasemeet', ['id' => $cm->instance], 'id, meetinguri, timestart, timeend');
    if (!$instance) {
        return;
    }
    $html = '';
    if ($schedule = clasemeet_format_schedule($instance)) {
        $html .= html_writer::div('<i class="fa fa-calendar me-1" aria-hidden="true"></i>' . $schedule, 'small text-muted mt-1');
    }
    if ($instance->meetinguri) {
        $html .= html_writer::link($instance->meetinguri, get_string('join', 'mod_clasemeet'),
            ['class' => 'btn btn-sm btn-outline-primary mt-2', 'target' => '_blank', 'rel' => 'noopener']);
    }
    if ($html) {
        $cm->set_after_link($html);
    }
}

/**
 * Actions counted as views in participation reports.
 *
 * @return string[]
 */
function clasemeet_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * Actions counted as posts in participation reports.
 *
 * @return string[]
 */
function clasemeet_get_post_actions() {
    return ['update', 'add'];
}
