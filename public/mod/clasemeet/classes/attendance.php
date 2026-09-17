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

namespace mod_clasemeet;

use mod_clasemeet\google\meet_client;
use mod_clasemeet\google\service_account;
use stdClass;

/**
 * Meet attendance: who joined each class (Meet REST API participants), matched to Moodle users through the
 * Workspace directory, and written to a mod_attendance session of the same course once the class is over.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance {
    /** @var string Prefix of the remarks written by this plugin; logs without it were set by a teacher. */
    public const REMARK_PREFIX = '[Meet]';

    /** @var int Conferences that start this long before the scheduled start still belong to the class. */
    private const WINDOW_BEFORE = 2 * HOURSECS;

    /** @var int Conferences that start up to this long after the scheduled end still belong to the class. */
    private const WINDOW_AFTER = 3 * HOURSECS;

    /** @var int Meet publishes participants a few minutes after the conference ends. */
    private const SETTLE_TIME = 15 * MINSECS;

    /** @var int Stop polling Google for a class this long after it ended. */
    private const POLL_UNTIL = 7 * DAYSECS;

    /** @var string */
    public const PRESENT = 'present';
    /** @var string */
    public const LATE = 'late';
    /** @var string */
    public const ABSENT = 'absent';

    /**
     * Minutes after the start from which a student counts as late.
     *
     * @return int
     */
    public static function late_minutes(): int {
        $value = get_config('mod_clasemeet', 'latethreshold');
        return $value === false || $value === '' ? 10 : max(0, (int) $value);
    }

    /**
     * Minimum share of the scheduled class (0-100) a student must be connected to not count as absent.
     *
     * @return int
     */
    public static function min_percent(): int {
        $value = get_config('mod_clasemeet', 'minpercent');
        return $value === false || $value === '' ? 50 : min(100, max(0, (int) $value));
    }

    /**
     * Whether Meet attendance should be written to mod_attendance.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('mod_clasemeet', 'attendancesync');
        return $value === false || (bool) $value;
    }

    /**
     * Does this class still need to be polled for participants?
     *
     * @param stdClass $instance
     * @return bool
     */
    public static function should_poll(stdClass $instance): bool {
        if (empty($instance->spacename) || empty($instance->timestart) || empty($instance->timeend)) {
            return false;
        }
        $now = time();
        return $now >= $instance->timestart - self::WINDOW_BEFORE && $now <= $instance->timeend + self::POLL_UNTIL;
    }

    /**
     * Conference records of the space that belong to this class (by start time).
     *
     * @param meet_client $client
     * @param stdClass $instance
     * @return array[]
     */
    private static function class_conferences(meet_client $client, stdClass $instance): array {
        $from = (int) $instance->timestart - self::WINDOW_BEFORE;
        $to = (int) $instance->timeend + self::WINDOW_AFTER;
        $result = [];
        foreach ($client->list_conference_records($instance->spacename) as $record) {
            $start = self::to_timestamp($record['startTime'] ?? null);
            if ($start >= $from && $start <= $to) {
                $result[] = $record;
            }
        }
        return $result;
    }

    /**
     * Fetch participants from Google and store them. Returns the number of participant rows stored.
     *
     * @param stdClass $instance clasemeet record
     * @return int
     */
    public static function sync_participants(stdClass $instance): int {
        global $DB;

        if (empty($instance->spacename) || empty($instance->timestart)) {
            return 0;
        }
        $client = manager::client_for($instance->owneremail, [service_account::SCOPE_MEET_READ]);
        $directory = self::directory_client($instance->owneremail);

        $count = 0;
        $alldone = true;
        $conferences = self::class_conferences($client, $instance);
        foreach ($conferences as $record) {
            if (empty($record['endTime'])) {
                $alldone = false;
            }
            foreach ($client->list_participants($record['name']) as $participant) {
                $row = self::participant_row($client, $directory, $instance, $record['name'], $participant);
                $existing = $DB->get_record('clasemeet_participant', ['participant' => $row->participant], 'id');
                if ($existing) {
                    $row->id = $existing->id;
                    $DB->update_record('clasemeet_participant', $row);
                } else {
                    $DB->insert_record('clasemeet_participant', $row);
                }
                $count++;
            }
        }
        // Remember whether every conference of the class has ended (needed before marking absences).
        $cache = \cache::make('mod_clasemeet', 'tokens');
        $cache->set('confdone_' . $instance->id, ['done' => $alldone && $conferences, 'at' => time()]);
        return $count;
    }

    /**
     * Build the DB row of one participant.
     *
     * @param meet_client $client
     * @param meet_client|null $directory
     * @param stdClass $instance
     * @param string $recordname
     * @param array $participant
     * @return stdClass
     */
    private static function participant_row(meet_client $client, ?meet_client $directory, stdClass $instance,
            string $recordname, array $participant): stdClass {
        $row = (object) [
            'clasemeetid' => $instance->id,
            'conferencerecord' => $recordname,
            'participant' => $participant['name'],
            'usertype' => '',
            'googleuserid' => '',
            'displayname' => '',
            'email' => '',
            'userid' => 0,
            'firstjoin' => self::to_timestamp($participant['earliestStartTime'] ?? null),
            'lastleave' => self::to_timestamp($participant['latestEndTime'] ?? null),
            'duration' => 0,
            'sessions' => 0,
            'timemodified' => time(),
        ];
        if (!empty($participant['signedinUser'])) {
            $row->usertype = 'signedin';
            $row->displayname = (string) ($participant['signedinUser']['displayName'] ?? '');
            $row->googleuserid = preg_replace('~^users/~', '', (string) ($participant['signedinUser']['user'] ?? ''));
        } else if (!empty($participant['anonymousUser'])) {
            $row->usertype = 'anonymous';
            $row->displayname = (string) ($participant['anonymousUser']['displayName'] ?? '');
        } else if (!empty($participant['phoneUser'])) {
            $row->usertype = 'phone';
            $row->displayname = (string) ($participant['phoneUser']['displayName'] ?? '');
        }

        $now = time();
        foreach ($client->list_participant_sessions($participant['name']) as $session) {
            $start = self::to_timestamp($session['startTime'] ?? null);
            $end = self::to_timestamp($session['endTime'] ?? null) ?: $now;
            if ($start && $end > $start) {
                $row->duration += $end - $start;
            }
            $row->sessions++;
        }

        if ($row->googleuserid !== '') {
            $row->email = self::email_for($directory, $row->googleuserid);
        }
        $row->userid = self::match_user($instance, $row);
        return $row;
    }

    /**
     * Client allowed to read the directory, or null if that scope is not delegated.
     *
     * @param string $owneremail
     * @return meet_client|null
     */
    private static function directory_client(string $owneremail): ?meet_client {
        // A regular account can only read the directory when contact sharing is on in Workspace;
        // an account with a (read-only) user admin role always can.
        $as = trim((string) get_config('mod_clasemeet', 'directoryuser')) ?: $owneremail;
        try {
            return manager::client_for($as, [service_account::SCOPE_DIRECTORY]);
        } catch (\moodle_exception $e) {
            debugging('mod_clasemeet: directory scope not delegated, matching participants by name: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * E-mail of a Google user id, cached for a day.
     *
     * @param meet_client|null $directory
     * @param string $googleuserid
     * @return string
     */
    private static function email_for(?meet_client $directory, string $googleuserid): string {
        if (!$directory) {
            return '';
        }
        $cache = \cache::make('mod_clasemeet', 'tokens');
        $key = 'dir_' . sha1($googleuserid);
        $cached = $cache->get($key);
        if (is_array($cached) && $cached['until'] > time()) {
            return $cached['email'];
        }
        try {
            $email = $directory->directory_email($googleuserid);
        } catch (\moodle_exception $e) {
            if ($e->debuginfo !== 'HTTP 404') {
                // API disabled, quota, network...: do not remember, try again next time.
                debugging('mod_clasemeet: directory lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                return '';
            }
            $email = ''; // Accounts outside the domain are not in the directory.
        }
        $cache->set($key, ['email' => $email, 'until' => time() + DAYSECS]);
        return $email;
    }

    /**
     * Moodle user of a participant: by e-mail, else by unique full name among the course participants.
     *
     * @param stdClass $instance
     * @param stdClass $row
     * @return int
     */
    private static function match_user(stdClass $instance, stdClass $row): int {
        global $DB;
        if ($row->email !== '') {
            $users = $DB->get_records_select('user', 'deleted = 0 AND LOWER(email) = :email', ['email' => $row->email],
                'id', 'id', 0, 2);
            if (count($users) === 1) {
                return (int) reset($users)->id;
            }
        }
        if ($row->displayname === '' || $row->usertype === 'phone') {
            return 0;
        }
        $wanted = self::normalise_name($row->displayname);
        $found = 0;
        foreach (self::course_users($instance->course) as $user) {
            if (self::normalise_name(fullname($user)) === $wanted) {
                if ($found) {
                    return 0; // Ambiguous.
                }
                $found = (int) $user->id;
            }
        }
        return $found;
    }

    /**
     * Enrolled users of a course (cached per request).
     *
     * @param int $courseid
     * @return stdClass[]
     */
    private static function course_users(int $courseid): array {
        static $cache = [];
        if (!isset($cache[$courseid])) {
            $context = \context_course::instance($courseid);
            $namefields = array_map(fn($field) => 'u.' . $field, \core_user\fields::get_name_fields());
            $cache[$courseid] = get_enrolled_users($context, '', 0, 'u.id, ' . implode(', ', $namefields));
        }
        return $cache[$courseid];
    }

    /**
     * Lower case, no accents, single spaces.
     *
     * @param string $name
     * @return string
     */
    private static function normalise_name(string $name): string {
        $name = \core_text::strtolower(trim($name));
        $name = \core_text::specialtoascii($name);
        return preg_replace('/\s+/', ' ', $name);
    }

    /**
     * Participants of a class aggregated per person (a person joining two conferences counts once).
     *
     * @param stdClass $instance
     * @return stdClass[] keyed by "u{userid}" or "p{participant row id}"
     */
    public static function people(stdClass $instance): array {
        global $DB;
        $people = [];
        foreach ($DB->get_records('clasemeet_participant', ['clasemeetid' => $instance->id], 'firstjoin ASC') as $row) {
            if ($row->userid) {
                $key = 'u' . $row->userid;
            } else if ($row->email !== '') {
                $key = 'e' . $row->email;
            } else if ($row->googleuserid !== '') {
                $key = 'g' . $row->googleuserid;
            } else {
                $key = 'p' . $row->id; // Guests and phone users cannot be told apart reliably.
            }
            if (!isset($people[$key])) {
                $people[$key] = (object) [
                    'userid' => (int) $row->userid,
                    'displayname' => $row->displayname,
                    'email' => $row->email,
                    'usertype' => $row->usertype,
                    'firstjoin' => (int) $row->firstjoin,
                    'lastleave' => (int) $row->lastleave,
                    'duration' => 0,
                    'sessions' => 0,
                    'connected' => false,
                ];
            }
            $person = $people[$key];
            if ($row->firstjoin && (!$person->firstjoin || $row->firstjoin < $person->firstjoin)) {
                $person->firstjoin = (int) $row->firstjoin;
            }
            if (!$row->lastleave) {
                $person->connected = true;
            } else if ($row->lastleave > $person->lastleave) {
                $person->lastleave = (int) $row->lastleave;
            }
            $person->duration += (int) $row->duration;
            $person->sessions += (int) $row->sessions;
        }
        return $people;
    }

    /**
     * Share (0-100) of the scheduled class a person was connected.
     *
     * @param stdClass $instance
     * @param int $duration seconds
     * @return int
     */
    public static function percent(stdClass $instance, int $duration): int {
        $scheduled = max(1, (int) $instance->timeend - (int) $instance->timestart);
        return (int) min(100, round($duration * 100 / $scheduled));
    }

    /**
     * Present / late / absent for one person (null = did not join).
     *
     * @param stdClass $instance
     * @param stdClass|null $person
     * @return string
     */
    public static function classify(stdClass $instance, ?stdClass $person): string {
        if (!$person || self::percent($instance, $person->duration) < self::min_percent()) {
            return self::ABSENT;
        }
        if ($person->firstjoin > (int) $instance->timestart + self::late_minutes() * MINSECS) {
            return self::LATE;
        }
        return self::PRESENT;
    }

    /**
     * Can attendance be written now? True once the class ended, Meet had time to publish participants
     * and no conference of the class is still running.
     *
     * @param stdClass $instance
     * @return bool
     */
    public static function class_is_over(stdClass $instance): bool {
        global $DB;
        if (empty($instance->timeend) || time() < $instance->timeend + self::SETTLE_TIME) {
            return false;
        }
        if (!$DB->record_exists('clasemeet_participant', ['clasemeetid' => $instance->id])) {
            return false; // The class was not held (or Google has not published it yet).
        }
        $state = \cache::make('mod_clasemeet', 'tokens')->get('confdone_' . $instance->id);
        if (is_array($state)) {
            return (bool) $state['done'];
        }
        // No fresh state: nobody may still be connected.
        return !$DB->record_exists('clasemeet_participant', ['clasemeetid' => $instance->id, 'lastleave' => 0]);
    }

    /**
     * The mod_attendance activity of the course that receives Meet attendance (first visible one).
     *
     * @param int $courseid
     * @return array|null [attendance record, cm_info]
     */
    public static function attendance_activity(int $courseid): ?array {
        global $DB;
        if (!$DB->record_exists('modules', ['name' => 'attendance', 'visible' => 1])) {
            return null;
        }
        $modinfo = get_fast_modinfo($courseid);
        foreach ($modinfo->get_instances_of('attendance') as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $record = $DB->get_record('attendance', ['id' => $cm->instance]);
            if ($record) {
                return [$record, $cm];
            }
        }
        return null;
    }

    /**
     * Write Meet attendance to mod_attendance.
     *
     * Students without a log get one. Logs written earlier by this plugin (remarks starting with
     * REMARK_PREFIX) are only rewritten when $overwrite is true; logs set by a teacher are never touched.
     *
     * @param stdClass $instance clasemeet record
     * @param bool $overwrite
     * @return int|null number of logs written, or null when nothing could be done
     */
    public static function apply(stdClass $instance, bool $overwrite = false): ?int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/attendance/locallib.php');

        $found = self::attendance_activity((int) $instance->course);
        if (!$found) {
            return null;
        }
        [$attrecord, $cm] = $found;
        $course = get_course($instance->course);
        $structure = new \mod_attendance_structure($attrecord, $cm->get_course_module_record(), $course);

        $session = self::attendance_session($instance, $structure);
        $statuses = attendance_get_statuses($attrecord->id, true, (int) $session->statusset);
        $map = self::status_map($statuses);
        if (!$map) {
            return null;
        }

        $people = self::people($instance);
        $byuser = [];
        foreach ($people as $person) {
            if ($person->userid) {
                $byuser[$person->userid] = $person;
            }
        }

        $students = get_enrolled_users(\context_module::instance($cm->id), 'mod/attendance:canbelisted', 0,
            'u.id', null, 0, 0, true);
        $logs = $structure->get_session_log($session->id);
        $takenby = (int) ($instance->ownerid ?: $USER->id);
        $statusset = implode(',', array_keys($statuses));
        $now = time();
        $written = [];
        foreach ($students as $student) {
            $existing = $logs[$student->id] ?? null;
            if ($existing && !($overwrite && str_starts_with((string) $existing->remarks, self::REMARK_PREFIX))) {
                continue;
            }
            $person = $byuser[$student->id] ?? null;
            $status = self::classify($instance, $person);
            $log = (object) [
                'sessionid' => $session->id,
                'studentid' => $student->id,
                'statusid' => $map[$status],
                'statusset' => $statusset,
                'timetaken' => $now,
                'takenby' => $takenby,
                'remarks' => self::remark($instance, $person),
            ];
            if ($existing) {
                $log->id = $existing->id;
                $DB->update_record('attendance_log', $log);
            } else {
                $DB->insert_record('attendance_log', $log);
            }
            $written[] = (int) $student->id;
        }

        if (!$written && !empty($instance->attendancetaken)) {
            return 0; // Nothing new since the last run.
        }
        $DB->update_record('attendance_sessions', (object) [
            'id' => $session->id,
            'lasttaken' => $now,
            'lasttakenby' => $takenby,
        ]);
        if ($written && !empty($attrecord->grade)) {
            attendance_update_users_grade($attrecord, $written);
        }
        if ($written) {
            $event = \mod_attendance\event\attendance_taken::create([
                'objectid' => $attrecord->id,
                'context' => \context_module::instance($cm->id),
                'other' => ['sessionid' => $session->id, 'grouptype' => 0],
            ]);
            $event->add_record_snapshot('attendance_sessions', $DB->get_record('attendance_sessions', ['id' => $session->id]));
            $event->trigger();
        }

        $DB->update_record('clasemeet', (object) [
            'id' => $instance->id,
            'attendancesessionid' => $session->id,
            'attendancetaken' => $now,
        ]);
        $instance->attendancesessionid = $session->id;
        $instance->attendancetaken = $now;
        return count($written);
    }

    /**
     * Session of the attendance activity for this class: the linked one, one starting within 30 minutes
     * of the class, or a new one.
     *
     * @param stdClass $instance
     * @param \mod_attendance_structure $structure
     * @return stdClass attendance_sessions record
     */
    private static function attendance_session(stdClass $instance, \mod_attendance_structure $structure): stdClass {
        global $DB;
        if (!empty($instance->attendancesessionid)) {
            $session = $DB->get_record('attendance_sessions',
                ['id' => $instance->attendancesessionid, 'attendanceid' => $structure->id]);
            if ($session) {
                return $session;
            }
        }
        $sessions = $DB->get_records_select('attendance_sessions',
            'attendanceid = :att AND groupid = 0 AND sessdate BETWEEN :from AND :to',
            ['att' => $structure->id, 'from' => $instance->timestart - 30 * MINSECS,
                'to' => $instance->timestart + 30 * MINSECS], 'sessdate ASC');
        if ($sessions) {
            return reset($sessions);
        }
        $sessionid = $structure->add_session((object) [
            'sessdate' => (int) $instance->timestart,
            'duration' => max(0, (int) $instance->timeend - (int) $instance->timestart),
            'groupid' => 0,
            'description' => get_string('attendancesessiondesc', 'mod_clasemeet', format_string($instance->name)),
            'descriptionformat' => FORMAT_HTML,
            'descriptionitemid' => IGNORE_FILE_MERGE,
            'timemodified' => time(),
            'statusset' => 0,
            'calendarevent' => 0, // The class already has its own calendar event.
            'absenteereport' => 1,
        ]);
        return $DB->get_record('attendance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
    }

    /**
     * Map present/late/absent to status ids of a status set. Works whatever the acronyms are called:
     * present = highest grade, absent = lowest grade, late = first remaining status (the install order is
     * Present, Absent, Late, Excused).
     *
     * @param stdClass[] $statuses
     * @return array|null
     */
    private static function status_map(array $statuses): ?array {
        if (count($statuses) < 2) {
            return null;
        }
        $byid = $statuses;
        ksort($byid);
        $present = $absent = null;
        foreach ($byid as $status) {
            if (!$present || $status->grade > $present->grade) {
                $present = $status;
            }
            if (!$absent || $status->grade < $absent->grade) {
                $absent = $status;
            }
        }
        $late = null;
        foreach ($byid as $status) {
            if ($status->id != $present->id && $status->id != $absent->id) {
                $late = $status;
                break;
            }
        }
        return [
            self::PRESENT => (int) $present->id,
            self::LATE => (int) ($late ?? $present)->id,
            self::ABSENT => (int) $absent->id,
        ];
    }

    /**
     * Remark stored with the attendance log.
     *
     * @param stdClass $instance
     * @param stdClass|null $person
     * @return string
     */
    private static function remark(stdClass $instance, ?stdClass $person): string {
        if (!$person) {
            return self::REMARK_PREFIX . ' ' . get_string('remarknotjoined', 'mod_clasemeet');
        }
        return self::REMARK_PREFIX . ' ' . get_string('remarkjoined', 'mod_clasemeet', (object) [
            'minutes' => (int) round($person->duration / MINSECS),
            'percent' => self::percent($instance, $person->duration),
            'join' => userdate($person->firstjoin, get_string('strftimetime', 'langconfig')),
        ]);
    }

    /**
     * RFC 3339 timestamp from Google to unix time.
     *
     * @param string|null $value
     * @return int
     */
    private static function to_timestamp(?string $value): int {
        if (!$value) {
            return 0;
        }
        $time = strtotime($value);
        return $time === false ? 0 : $time;
    }
}
