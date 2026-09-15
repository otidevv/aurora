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
 * English strings for mod_clasemeet.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesstype'] = 'Who can join directly';
$string['accesstype_desc'] = 'TRUSTED: accounts of the institutional domain join directly, others must ask. OPEN: anyone with the link. RESTRICTED: only invited people.';
$string['autorecording'] = 'Record automatically';
$string['autorecording_help'] = 'Every meeting held in this Meet space starts recording by itself. The recording is saved in the teacher\'s Google Drive and listed here once Google finishes processing it.';
$string['autotranscription'] = 'Transcribe automatically';
$string['clasemeet:addinstance'] = 'Add a new Meet class';
$string['clasemeet:manage'] = 'Manage a Meet class and sync its recordings';
$string['clasemeet:view'] = 'View a Meet class';
$string['clasemeetname'] = 'Class name';
$string['createdfor'] = 'The Meet will be created in the name of <strong>{$a}</strong>, who will own the recordings.';
$string['credentialsfile'] = 'Service account key file';
$string['credentialsfile_desc'] = 'Absolute path, inside moodledata, of the Google service account JSON key with domain-wide delegation.';
$string['date'] = 'Date';
$string['domain'] = 'Institutional domain';
$string['domain_desc'] = 'Only users whose e-mail belongs to this Google Workspace domain can create Meet classes.';
$string['duration'] = 'Duration';
$string['errorapi'] = 'Google Meet did not accept the request: {$a}';
$string['errorcredentials'] = 'The service account key file is missing or invalid: {$a}';
$string['errornodomainuser'] = 'Your Moodle account e-mail ({$a->email}) does not belong to the {$a->domain} domain, so a Meet cannot be created in your name.';
$string['errornospace'] = 'This class has no Meet space yet. Edit the activity and save it again to create it.';
$string['errortoken'] = 'Google refused to issue an access token for {$a->email}: {$a->error}';
$string['inprogress'] = 'Recording now';
$string['join'] = 'Join the class';
$string['processing'] = 'Processing; available in a few minutes';
$string['lastsync'] = 'Recordings last checked: {$a}';
$string['meetingcode'] = 'Meeting code';
$string['modulename'] = 'Meet class';
$string['modulename_help'] = 'Creates a Google Meet space for the class in the teacher\'s name. If automatic recording is on, every session is recorded and the recordings are listed in the activity so students who could not attend can watch them.';
$string['modulenameplural'] = 'Meet classes';
$string['never'] = 'never';
$string['norecordings'] = 'No recordings yet. They appear a few minutes after a recorded session ends.';
$string['notshared'] = 'Only the owner can open it';
$string['notshared_help'] = 'The file is in the teacher\'s Drive and has not been shared with the domain yet.';
$string['sharingdisabled'] = 'Recordings are not shared with students because "Share recordings with the whole domain" is disabled in the plugin settings.';
$string['sharingscopemissing'] = 'Recordings cannot be shared automatically with students: the service account\'s domain-wide delegation does not include the <code>{$a}</code> scope. Ask the Google Workspace admin to add it; meanwhile open the recording with "Watch" and share it from Drive with the domain.';
$string['opennewtab'] = 'Opens in a new tab';
$string['owner'] = 'Teacher';
$string['pluginadministration'] = 'Meet class administration';
$string['pluginname'] = 'Meet class';
$string['privacy:metadata:clasemeet'] = 'Meet classes created by teachers.';
$string['privacy:metadata:clasemeet:owneremail'] = 'Google Workspace e-mail the Meet space belongs to.';
$string['privacy:metadata:clasemeet:ownerid'] = 'The teacher who created the class and owns the Meet space.';
$string['privacy:metadata:google'] = 'Meet spaces are created on Google servers in the teacher\'s name; the recordings are stored in the teacher\'s Google Drive.';
$string['recording'] = 'Recording';
$string['recordingautomatic'] = 'This class is recorded automatically';
$string['recordings'] = 'Class recordings';
$string['recordingsfound'] = '{$a} new recording(s) found.';
$string['schedule'] = 'Class schedule';
$string['schedule_help'] = 'Start and end date/time of the class. It is added to the course calendar in Moodle and to the teacher\'s Google Calendar with the Meet link. The link works at any time; the schedule tells students when to connect.';
$string['errortimeend'] = 'The end time must be after the start time.';
$string['status_ended'] = 'Ended';
$string['status_live'] = 'Live now';
$string['status_upcoming'] = 'Upcoming';
$string['timeend'] = 'End';
$string['timestart'] = 'Start';
$string['scopes'] = 'Delegated scopes';
$string['scopes_desc'] = 'Comma-separated OAuth scopes authorised for the service account in the Workspace admin console. They must match exactly.';
$string['sharewithdomain'] = 'Share recordings with the whole domain';
$string['sharewithdomain_desc'] = 'Give read access to every account of the institutional domain over the recording files (requires the https://www.googleapis.com/auth/drive scope in the delegation).';
$string['shared'] = 'Shared with the domain';
$string['state_ENDED'] = 'Ended, processing';
$string['state_FILE_GENERATED'] = 'Ready';
$string['state_STARTED'] = 'Recording';
$string['sync'] = 'Check for new recordings';
$string['tasksyncrecordings'] = 'Sync Meet class recordings';
$string['watch'] = 'Watch';
