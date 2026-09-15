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

use calendar_event;
use html_writer;
use mod_clasemeet\google\meet_client;
use mod_clasemeet\google\service_account;
use stdClass;

/**
 * Business logic: create the Meet space for a class and keep its recordings in sync.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /**
     * Institutional domain from settings.
     *
     * @return string
     */
    public static function domain(): string {
        return self::lower((string) get_config('mod_clasemeet', 'domain'));
    }

    /**
     * Whether the user has a Workspace account in the institutional domain.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function is_domain_user(stdClass $user): bool {
        $domain = self::domain();
        return $domain !== '' && str_ends_with(self::lower((string) $user->email), '@' . $domain);
    }

    /**
     * Trimmed lower-case string.
     *
     * @param string $value
     * @return string
     */
    private static function lower(string $value): string {
        return \core_text::strtolower(trim($value));
    }

    /**
     * Meet client acting as the given Workspace user.
     *
     * @param string $email
     * @param string[]|null $scopes
     * @return meet_client
     */
    public static function client_for(string $email, ?array $scopes = null): meet_client {
        $token = service_account::from_config()->access_token($email, $scopes);
        return new meet_client($token);
    }

    /**
     * Create the Meet space for an instance and fill spacename / meetingcode / meetinguri on it.
     *
     * @param stdClass $instance clasemeet record (or form data) with owneremail, autorecording, autotranscription
     * @return stdClass the same object, updated
     */
    public static function create_space(stdClass $instance): stdClass {
        $accesstype = (string) (get_config('mod_clasemeet', 'accesstype') ?: 'TRUSTED');
        $client = self::client_for($instance->owneremail);
        $space = $client->create_space($accesstype, !empty($instance->autorecording), !empty($instance->autotranscription));

        $instance->spacename = $space['name'] ?? '';
        $instance->meetingcode = $space['meetingCode'] ?? '';
        $instance->meetinguri = $space['meetingUri'] ?? '';
        $recording = $space['config']['artifactConfig']['recordingConfig']['autoRecordingGeneration'] ?? 'OFF';
        $instance->autorecording = $recording === 'ON' ? 1 : 0;
        return $instance;
    }

    /**
     * Keep the Moodle calendar event of the class in sync with its schedule.
     *
     * @param stdClass $instance clasemeet record with id, course, name, timestart, timeend
     */
    public static function update_moodle_calendar(stdClass $instance): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/calendar/lib.php');

        $existing = $DB->get_record('event', ['modulename' => 'clasemeet', 'instance' => $instance->id, 'eventtype' => 'class']);
        if (empty($instance->timestart)) {
            if ($existing) {
                calendar_event::load($existing)->delete();
            }
            return;
        }
        $event = (object) [
            'name' => $instance->name,
            'description' => $instance->meetinguri ? html_writer::link($instance->meetinguri, $instance->meetinguri) : '',
            'format' => FORMAT_HTML,
            'courseid' => $instance->course,
            'groupid' => 0,
            'userid' => 0,
            'modulename' => 'clasemeet',
            'instance' => $instance->id,
            'eventtype' => 'class',
            'type' => CALENDAR_EVENT_TYPE_STANDARD,
            'timestart' => (int) $instance->timestart,
            'timeduration' => max(0, (int) $instance->timeend - (int) $instance->timestart),
            'visible' => 1,
        ];
        if ($existing) {
            $event->id = $existing->id;
        }
        calendar_event::create($event, false);
    }

    /**
     * Create or update the class event in the teacher's Google Calendar (best effort: never blocks saving).
     *
     * @param stdClass $instance clasemeet record
     * @return string|null Google event id, or null when nothing was done
     */
    public static function update_google_calendar(stdClass $instance): ?string {
        global $DB;
        if (empty($instance->timestart) || empty($instance->timeend) || empty($instance->meetinguri)) {
            return null;
        }
        try {
            $owner = \core_user::get_user($instance->ownerid);
            $timezone = $owner ? \core_date::get_user_timezone($owner) : \core_date::get_server_timezone();
            $client = self::client_for($instance->owneremail);
            $description = html_to_text((string) ($instance->intro ?? ''), 0, false);
            if (!empty($instance->googleeventid)) {
                $client->update_calendar_event($instance->googleeventid, $instance->name, $description,
                    $instance->meetinguri, (int) $instance->timestart, (int) $instance->timeend, $timezone);
                return $instance->googleeventid;
            }
            $eventid = $client->create_calendar_event($instance->name, $description, $instance->meetinguri,
                (int) $instance->timestart, (int) $instance->timeend, $timezone);
            if ($eventid) {
                $DB->set_field('clasemeet', 'googleeventid', $eventid, ['id' => $instance->id]);
            }
            return $eventid ?: null;
        } catch (\moodle_exception $e) {
            debugging('mod_clasemeet: Google Calendar event not synced: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Can we share recordings with the domain, i.e. is the Drive write scope delegated for this owner?
     * The answer is cached for 10 minutes to avoid a token request on every page view.
     *
     * @param string $owneremail
     * @return bool
     */
    public static function can_share_recordings(string $owneremail): bool {
        if (!get_config('mod_clasemeet', 'sharewithdomain')) {
            return false;
        }
        $cache = \cache::make('mod_clasemeet', 'tokens');
        $key = 'canshare_' . sha1($owneremail);
        $cached = $cache->get($key);
        if (is_array($cached) && $cached['until'] > time()) {
            return (bool) $cached['ok'];
        }
        try {
            service_account::from_config()->access_token($owneremail, [service_account::SCOPE_DRIVE]);
            $ok = true;
        } catch (\moodle_exception $e) {
            $ok = false;
        }
        $cache->set($key, ['ok' => $ok, 'until' => time() + 600]);
        return $ok;
    }

    /**
     * Fetch recordings from Google for one class and store the new ones.
     *
     * @param stdClass $instance clasemeet record
     * @return int number of recordings added
     */
    public static function sync_recordings(stdClass $instance): int {
        global $DB;

        if (empty($instance->spacename)) {
            return 0;
        }
        $client = self::client_for($instance->owneremail);
        $sharewithdomain = (bool) get_config('mod_clasemeet', 'sharewithdomain');
        $driveclient = null;
        if ($sharewithdomain) {
            try {
                $driveclient = self::client_for($instance->owneremail, [service_account::SCOPE_DRIVE]);
            } catch (\moodle_exception $e) {
                debugging('mod_clasemeet: drive scope not delegated, recordings will not be shared: ' . $e->getMessage(),
                    DEBUG_DEVELOPER);
            }
        }

        $added = 0;
        foreach ($client->list_conference_records($instance->spacename) as $record) {
            foreach ($client->list_recordings($record['name']) as $recording) {
                $existing = $DB->get_record('clasemeet_recording', ['recordingname' => $recording['name']]);
                $row = (object) [
                    'clasemeetid' => $instance->id,
                    'conferencerecord' => $record['name'],
                    'recordingname' => $recording['name'],
                    'driveid' => $recording['driveDestination']['file'] ?? '',
                    'exporturi' => $recording['driveDestination']['exportUri'] ?? '',
                    'state' => $recording['state'] ?? '',
                    'starttime' => self::to_timestamp($recording['startTime'] ?? $record['startTime'] ?? null),
                    'endtime' => self::to_timestamp($recording['endTime'] ?? $record['endTime'] ?? null),
                ];
                if ($existing) {
                    $row->id = $existing->id;
                    $row->shared = $existing->shared;
                    $row->timecreated = $existing->timecreated;
                    $DB->update_record('clasemeet_recording', $row);
                } else {
                    $row->shared = 0;
                    $row->timecreated = time();
                    $row->id = $DB->insert_record('clasemeet_recording', $row);
                    $added++;
                }
                if ($driveclient && !$row->shared && $row->driveid && $row->state === 'FILE_GENERATED') {
                    try {
                        $driveclient->share_with_domain($row->driveid, self::domain());
                        $DB->set_field('clasemeet_recording', 'shared', 1, ['id' => $row->id]);
                    } catch (\moodle_exception $e) {
                        debugging('mod_clasemeet: could not share recording ' . $row->driveid . ': ' . $e->getMessage(),
                            DEBUG_DEVELOPER);
                    }
                }
            }
        }
        $DB->set_field('clasemeet', 'lastsync', time(), ['id' => $instance->id]);
        return $added;
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
