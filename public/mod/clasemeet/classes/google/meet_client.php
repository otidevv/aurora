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

namespace mod_clasemeet\google;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Minimal client for the Google Meet REST API v2 (and the Drive permission used to share recordings).
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meet_client {
    /** @var string */
    private const MEET = 'https://meet.googleapis.com/v2';
    /** @var string */
    private const DRIVE = 'https://www.googleapis.com/drive/v3';
    /** @var string */
    private const CALENDAR = 'https://www.googleapis.com/calendar/v3';

    /**
     * @param string $token OAuth access token acting as the space owner.
     */
    public function __construct(private readonly string $token) {
    }

    /**
     * Create a Meet space.
     *
     * @param string $accesstype TRUSTED | OPEN | RESTRICTED
     * @param bool $autorecording
     * @param bool $autotranscription
     * @return array Space resource (name, meetingCode, meetingUri, config...)
     */
    public function create_space(string $accesstype, bool $autorecording, bool $autotranscription = false): array {
        $config = [
            'accessType' => $accesstype,
            'entryPointAccess' => 'ALL',
        ];
        $artifacts = [];
        if ($autorecording) {
            $artifacts['recordingConfig'] = ['autoRecordingGeneration' => 'ON'];
        }
        if ($autotranscription) {
            $artifacts['transcriptionConfig'] = ['autoTranscriptionGeneration' => 'ON'];
        }
        if ($artifacts) {
            $config['artifactConfig'] = $artifacts;
        }
        return $this->request('POST', self::MEET . '/spaces', ['config' => $config]);
    }

    /**
     * Conference records (meetings held) of a space, newest first.
     *
     * @param string $spacename e.g. spaces/abc
     * @return array[]
     */
    public function list_conference_records(string $spacename): array {
        $records = [];
        $pagetoken = null;
        do {
            $query = ['filter' => 'space.name = "' . $spacename . '"', 'pageSize' => 50];
            if ($pagetoken) {
                $query['pageToken'] = $pagetoken;
            }
            $page = $this->request('GET', self::MEET . '/conferenceRecords', null, $query);
            $records = array_merge($records, $page['conferenceRecords'] ?? []);
            $pagetoken = $page['nextPageToken'] ?? null;
        } while ($pagetoken);
        return $records;
    }

    /**
     * Recordings of one conference record.
     *
     * @param string $record e.g. conferenceRecords/xyz
     * @return array[]
     */
    public function list_recordings(string $record): array {
        $page = $this->request('GET', self::MEET . '/' . $record . '/recordings', null, ['pageSize' => 50]);
        return $page['recordings'] ?? [];
    }

    /**
     * Give read access on a Drive file to every account of a domain.
     *
     * @param string $fileid
     * @param string $domain
     */
    public function share_with_domain(string $fileid, string $domain): void {
        $this->request('POST', self::DRIVE . '/files/' . urlencode($fileid) . '/permissions',
            ['type' => 'domain', 'role' => 'reader', 'domain' => $domain],
            ['sendNotificationEmail' => 'false', 'supportsAllDrives' => 'true']);
    }

    /**
     * Create an event in the owner's primary Google Calendar pointing to the Meet.
     *
     * @param string $summary
     * @param string $description
     * @param string $meetinguri
     * @param int $timestart unix time
     * @param int $timeend unix time
     * @param string $timezone IANA timezone, e.g. America/Lima
     * @return string Google event id
     */
    public function create_calendar_event(string $summary, string $description, string $meetinguri,
            int $timestart, int $timeend, string $timezone): string {
        $event = $this->request('POST', self::CALENDAR . '/calendars/primary/events',
            $this->calendar_body($summary, $description, $meetinguri, $timestart, $timeend, $timezone));
        return (string) ($event['id'] ?? '');
    }

    /**
     * Update an existing Google Calendar event.
     *
     * @param string $eventid
     * @param string $summary
     * @param string $description
     * @param string $meetinguri
     * @param int $timestart
     * @param int $timeend
     * @param string $timezone
     */
    public function update_calendar_event(string $eventid, string $summary, string $description, string $meetinguri,
            int $timestart, int $timeend, string $timezone): void {
        $this->request('PATCH', self::CALENDAR . '/calendars/primary/events/' . urlencode($eventid),
            $this->calendar_body($summary, $description, $meetinguri, $timestart, $timeend, $timezone));
    }

    /**
     * Event payload shared by create/update.
     *
     * @param string $summary
     * @param string $description
     * @param string $meetinguri
     * @param int $timestart
     * @param int $timeend
     * @param string $timezone
     * @return array
     */
    private function calendar_body(string $summary, string $description, string $meetinguri,
            int $timestart, int $timeend, string $timezone): array {
        return [
            'summary' => $summary,
            'description' => trim($description . "\n\n" . $meetinguri),
            'location' => $meetinguri,
            'start' => ['dateTime' => date(DATE_RFC3339, $timestart), 'timeZone' => $timezone],
            'end' => ['dateTime' => date(DATE_RFC3339, $timeend), 'timeZone' => $timezone],
            'source' => ['title' => 'Google Meet', 'url' => $meetinguri],
        ];
    }

    /**
     * Perform an authenticated JSON request.
     *
     * @param string $method
     * @param string $url
     * @param array|null $body
     * @param array $query
     * @return array
     */
    private function request(string $method, string $url, ?array $body = null, array $query = []): array {
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $payload = $body === null ? '{}' : json_encode($body);
        $response = match ($method) {
            'GET' => $curl->get($url),
            'POST' => $curl->post($url, $payload),
            'PATCH' => $curl->patch($url, $payload),
            default => throw new \coding_exception('Unsupported method ' . $method),
        };
        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $response, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $message = $data['error']['message'] ?? ($curl->error ?: "HTTP $status");
            $statusname = $data['error']['status'] ?? '';
            throw new moodle_exception('errorapi', 'mod_clasemeet', '', trim("$statusname $message"));
        }
        return $data;
    }
}
