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
require_once(dirname(__FILE__).'/upload_form.php');
require_once(dirname(__FILE__).'/libjudgeX.php');

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
$PAGE->set_url('/mod/onlinejudge/upload.php', array('id'=>$id,'offset'=>$formdata->offset,'forcerefresh'=>$formdata->forcerefresh,'userid'=>$formdata->userid,'mode'=>$formdata->mode));
$PAGE->set_title(get_string('uploadPageTitle', 'onlinejudge'));

//填入一些初始化表单字段
$mform = new onlinejudge_upload_form();
$mform->set_data($formdata);

$instance = new assignment_onlinejudge($cm->id, $onlinejudge, $cm, $course);
$submission = $instance->get_submission($formdata->userid, true);

$filemanager_options = array('subdirs'=>1, 'maxbytes'=>$onlinejudge->maxbytes, 'maxfiles'=>$onlinejudge->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('./view.php', array('id'=>$id)));
} else if ($formdata = $mform->get_data()) {
	//upload 操作
  	// $success = $mform->save_file('taskFile', $CFG->tempdir.'/oj/hhh', true);
    // $instance->upload($mform, $filemanager_options);
    try {
        $user = 'xuzhj';
        $pwd = 'm0i5ina@';
        $j = new JudgexSoapClient('http://judgeide.com/api');
        $code = $formdata->code['text'];
        $res = $j->createSubmission($user, $pwd, unescape($code), 1, '', true, true);
        sleep(3);
        var_dump($res);
        var_dump( $j->getSubmissionStatus($user,$pwd,$res['link']) );
        var_dump( $j->getSubmissionDetails($user,$pwd,$res['link'],
          true, false, true, false, true) );
    } catch(Exception $e) {
        echo $e;
    }
    
    // redirect(new moodle_url('./view.php', array('id'=>$id, 'data'=>$formdata->id)));
}

echo $OUTPUT->header();

//渲染表单
$mform->display();

echo $OUTPUT->footer();