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
 * English strings.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Turnitin submission limit';
$string['enabled'] = 'Enforce the limit';
$string['enabled_desc'] = 'When disabled, files are sent to Turnitin without limit, as the Turnitin plugin does by default.';
$string['maxsends'] = 'Submissions per student and activity';
$string['maxsends_desc'] = 'Maximum number of files a student can send to Turnitin in one assignment, forum or workshop. Every uploaded or replaced file counts. Files over the limit stay in Moodle but get no similarity report. A teacher or admin can grant extra submissions with docker/cli/turnitin_autorizar_envios.php.';
$string['limitreached'] = 'This file was not sent to Turnitin: the limit of {$a} submissions for this activity was reached. Ask your teacher if you need another one.';
$string['taskenforce'] = 'Enforce the Turnitin submission limit on the queue';
$string['privacy:metadata:table'] = 'Files not sent to Turnitin because the submission limit was reached.';
$string['privacy:metadata:userid'] = 'Student who owns the file.';
$string['privacy:metadata:cm'] = 'Activity where the file was uploaded.';
$string['privacy:metadata:timecreated'] = 'When the submission was blocked.';
$string['privacy:metadata:extra'] = 'Extra submissions granted to the user in an activity.';
$string['menulink'] = 'Turnitin submissions';
$string['pagetitle'] = 'Turnitin submissions: {$a}';
$string['pageintro'] = 'Each student can send up to {$a} files to Turnitin in this activity. Every uploaded or replaced file counts. "Grant 1 more" gives the student one extra submission and, if a file was blocked, sends it right away.';
$string['student'] = 'Student';
$string['sendsused'] = 'Submissions used';
$string['limit'] = 'Limit';
$string['blocked'] = 'Blocked';
$string['latest'] = 'Latest file';
$string['grantone'] = 'Grant 1 more';
$string['granted'] = '{$a->name}: {$a->amount} extra submission(s) granted. Files resent to Turnitin: {$a->requeued}.';
$string['similarity'] = 'similarity {$a} %';
$string['status_success'] = 'Sent to Turnitin';
$string['status_queued'] = 'Queued';
$string['status_error'] = 'Not sent';
$string['nostudents'] = 'No students are enrolled in this activity.';
$string['turnitinlimit:manage'] = 'View students\' Turnitin submissions and grant extra ones';
$string['studentstatuslabel'] = 'Turnitin submissions';
$string['studentstatus'] = '{$a->used} of {$a->limit}';
$string['studentstatusleft'] = 'You have {$a->left} left. Every file you upload or replace counts as one submission and gets its similarity report.';
$string['studentstatusnone'] = 'No submissions left: new files are saved but get no similarity report. Ask your teacher if you need another one.';
