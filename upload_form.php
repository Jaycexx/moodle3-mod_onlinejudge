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
 * Form used to select a file and file format for the import
 *
 * @package mod_lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

/**
 * Form used to select a file and file format for the import
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class onlinejudge_upload_form extends moodleform {

    public function definition() {

        $mform = $this->_form;
        $instance = $this->_customdata;

        //Using filemanager as filepicker
        // $mform->addElement('filepicker', 'taskFile', get_string('uploadFormLabel', 'onlinejudge'));
        // $mform->addRule('taskFile', null, 'required', null, 'client');

        $mform->addElement('editor', 'code', get_string('codeArea', 'onlinejudge'), null, null);
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', get_string('required'), 'required', null, 'client');
        // hidden params
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $instance['contextid']);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'userid', $instance['userid']);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'uploadfile');
        $mform->setType('action', PARAM_ALPHA);

        $this->add_action_buttons(true, get_string('submitCode', 'onlinejudge'), false);
    }

    /**
     * Checks that a file has been uploaded, and that it is of a plausible type.
     * @param array $data the submitted data.
     * @param array $errors the errors so far.
     * @return array the updated errors.
     */
    // protected function validate_uploaded_file($data, $errors) {
    //     global $CFG;

    //     if (empty($data['taskFile'])) {
    //         $errors['taskFile'] = get_string('required');
    //         return $errors;
    //     }

    //     $files = $this->get_draft_files('taskFile');
    //     if (count($files) < 1) {
    //         $errors['taskFile'] = get_string('required');
    //         return $errors;
    //     }

    //     return $errors;
    // }

    // public function validation($data, $files) {
    //     $errors = parent::validation($data, $files);
    //     $errors = $this->validate_uploaded_file($data, $errors);
    //     return $errors;
    // }
}