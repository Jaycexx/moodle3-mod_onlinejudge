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
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... onlinejudge instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('onlinejudge', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $onlinejudge  = $DB->get_record('onlinejudge', array('id' => $cm->instance), '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
} else if ($n) {
    $onlinejudge  = $DB->get_record('onlinejudge', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $onlinejudge->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('onlinejudge', $onlinejudge->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$event = \mod_onlinejudge\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $onlinejudge);
$event->trigger();

// Print the page header.
$PAGE->set_url('/mod/onlinejudge/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string(($onlinejudge->name)));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('onlinejudge-'.$somevar);
 */

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->heading($onlinejudge->name);

//link: submission.php
$submissionURL = new moodle_url('./submission.php', array('id' => $id, 'userid' => $USER->id, 'contextid' => $context->id));
echo '<div><a href="'.$submissionURL.'">xx '.get_string('submissionURL', 'onlinejudge').'</a></div>';
//link: submission.php
$testcaseURL = new moodle_url('./testcase.php', array('id' => $id, 'userid' => $USER->id, 'contextid' => $context->id));
echo '<div><a href="'.$testcaseURL.'">'.get_string('testcaseURL', 'onlinejudge').'</a></div>';

// Conditions to show the intro can change to look for own settings or whatever.
if ($onlinejudge->intro) {
    echo $OUTPUT->box(format_module_intro('onlinejudge', $onlinejudge, $cm->id), 'generalbox mod_introbox', 'onlinejudgeintro');
}

//文件上传
$uploadURL = new moodle_url('./upload.php', array(
                                                'id' => $id,
                                                'userid' => $USER->id,
                                                'contextid' => $context->id));
echo '<form action="' . $uploadURL . '" method="post"><input type="submit" value="' . get_string('SubmitFile', 'onlinejudge') . '">';

/* 另一种ihtml输出方式
$uploadURL = new moodle_url('./upload.php',array('id'=>$id));
$link = html_writer::link($uploadURL, get_string('SubmitFile', 'onlinejudge'));
echo $link;
*/

//显示有效时间
require('./view/view-date-tpl.php');

// Finish the page.
echo $OUTPUT->footer();
