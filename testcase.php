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
 * Prints a particular instance of onlinejudge
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_onlinejudge
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace onlinejudge with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once(dirname(__FILE__).'/clients/mod/assignment/type/onlinejudge/assignment.class.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/testcase_form.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... onlinejudge instance ID - it should be named as the first character of the module.

if($id) {
	$cm = get_coursemodule_from_id('onlinejudge', $id, 0, false, MUST_EXIST);
	$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
	$onlinejudge  = $DB->get_record('onlinejudge', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
	error('You must specify a course_module ID or an instance ID');
}

//access 控制
require_login($course, true, $cm);
if (isguestuser()) {
    die();
}

$formdata = new stdClass();
$formdata->id = $id;
$formdata->userid = optional_param('userid', 0, PARAM_INT);
$formdata->offset = optional_param('offset', null, PARAM_INT);
$formdata->forcerefresh = optional_param('forcerefresh', null, PARAM_INT);
$formdata->mode = optional_param('mode', null, PARAM_ALPHA);

// Print the page header.
$PAGE->set_url('/mod/onlinejudge/testcase.php', array('id'=>$id,'offset'=>$formdata->offset,'forcerefresh'=>$formdata->forcerefresh,'userid'=>$formdata->userid,'mode'=>$formdata->mode));
$PAGE->set_title(get_string('uploadPageTitle', 'onlinejudge'));

$mform = new testcase_form();

echo $OUTPUT->header();
//output strat here
$mform->display();

echo $OUTPUT->footer();