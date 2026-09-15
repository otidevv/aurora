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
 * Add/edit form for a Meet class.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_clasemeet\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Meet class settings form.
 */
class mod_clasemeet_mod_form extends moodleform_mod {
    /**
     * Form definition.
     */
    public function definition() {
        global $USER;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('clasemeetname', 'mod_clasemeet'), ['size' => '48']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'schedule', get_string('schedule', 'mod_clasemeet'));
        $mform->setExpanded('schedule', true);
        $mform->addElement('date_time_selector', 'timestart', get_string('timestart', 'mod_clasemeet'), ['step' => 5]);
        $mform->addRule('timestart', null, 'required', null, 'client');
        $mform->addElement('date_time_selector', 'timeend', get_string('timeend', 'mod_clasemeet'), ['step' => 5]);
        $mform->addRule('timeend', null, 'required', null, 'client');
        $mform->addHelpButton('timestart', 'schedule', 'mod_clasemeet');
        if (empty($this->current->instance)) {
            // Default: next full hour, two hours long.
            $nexthour = (int) (ceil(time() / 3600) * 3600);
            $mform->setDefault('timestart', $nexthour);
            $mform->setDefault('timeend', $nexthour + 2 * 3600);
        }

        $mform->addElement('header', 'meetsettings', 'Google Meet');
        $mform->setExpanded('meetsettings', true);

        $hasspace = !empty($this->current->spacename);
        if ($hasspace) {
            $mform->addElement('static', 'meetinguri', 'Google Meet',
                html_writer::link($this->current->meetinguri, $this->current->meetinguri, ['target' => '_blank']));
        } else {
            $mform->addElement('static', 'createdfor', 'Google Meet',
                get_string('createdfor', 'mod_clasemeet', s($USER->email)));
        }

        $mform->addElement('advcheckbox', 'autorecording', get_string('autorecording', 'mod_clasemeet'));
        $mform->addHelpButton('autorecording', 'autorecording', 'mod_clasemeet');
        $mform->setDefault('autorecording', 1);

        $mform->addElement('advcheckbox', 'autotranscription', get_string('autotranscription', 'mod_clasemeet'));
        $mform->setDefault('autotranscription', 0);

        if ($hasspace) {
            // Recording settings belong to the Meet space and are fixed once it exists.
            $mform->freeze(['autorecording', 'autotranscription']);
        }

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Only teachers with an institutional Workspace account can create the Meet in their name.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);
        if (!empty($data['timestart']) && !empty($data['timeend']) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('errortimeend', 'mod_clasemeet');
        }
        if (empty($this->current->instance) && !manager::is_domain_user($USER)) {
            $errors['name'] = get_string('errornodomainuser', 'mod_clasemeet',
                (object) ['email' => $USER->email, 'domain' => manager::domain()]);
        }
        return $errors;
    }
}
