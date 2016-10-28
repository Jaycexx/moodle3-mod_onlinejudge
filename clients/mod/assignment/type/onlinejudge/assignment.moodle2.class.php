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
 * Assignment upload type implementation
 *
 * @package   mod-assignment
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/mod/onlinejudge/upload_form.php');
require_once($CFG->libdir . '/portfoliolib.php');
require_once($CFG->dirroot . '/mod/assignment/lib.php');

define('ASSIGNMENT_STATUS_SUBMITTED', 'submitted'); // student thinks it is finished
define('ASSIGNMENT_STATUS_CLOSED', 'closed');       // teacher prevents more submissions


class assignment_base {

    const FILTER_ALL             = 0;
    const FILTER_SUBMITTED       = 1;
    const FILTER_REQUIRE_GRADING = 2;

    /** @var object */
    var $cm;
    /** @var object */
    var $course;
    /** @var stdClass */
    var $coursecontext;
    /** @var object */
    var $assignment;
    /** @var string */
    var $strassignment;
    /** @var string */
    var $strassignments;
    /** @var string */
    var $strsubmissions;
    /** @var string */
    var $strlastmodified;
    /** @var string */
    var $pagetitle;
    /**
     * @todo document this var
     */
    var $defaultformat;
    /**
     * @todo document this var
     */
    var $context;
    /** @var string */
    var $type;

    /**
     * Constructor for the base assignment class
     *
     * Constructor for the base assignment class.
     * If cmid is set create the cm, course, assignment objects.
     * If the assignment is hidden and the user is not a teacher then
     * this prints a page header and notice.
     *
     * @global object
     * @global object
     * @param int $cmid the current course module id - not set for new assignments
     * @param object $assignment usually null, but if we have it we pass it to save db access
     * @param object $cm usually null, but if we have it we pass it to save db access
     * @param object $course usually null, but if we have it we pass it to save db access
     */
    function assignment_base($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        global $COURSE, $DB;

        if ($cmid == 'staticonly') {
            //use static functions only!
            return;
        }

        global $CFG;

        if ($cm) {
            $this->cm = $cm;
        } else if (! $this->cm = get_coursemodule_from_id('assignment', $cmid)) {
            print_error('invalidcoursemodule');
        }

        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if ($this->cm->course == $COURSE->id) {
            $this->course = $COURSE;
        } else if (! $this->course = $DB->get_record('course', array('id'=>$this->cm->course))) {
            print_error('invalidid', 'assignment');
        }
        $this->coursecontext = context_course::instance($this->course->id);
        $courseshortname = format_text($this->course->shortname, true, array('context' => $this->coursecontext));

        if ($assignment) {
            $this->assignment = $assignment;
        } else if (! $this->assignment = $DB->get_record('assignment', array('id'=>$this->cm->instance))) {
            print_error('invalidid', 'assignment');
        }

        $this->assignment->cmidnumber = $this->cm->idnumber; // compatibility with modedit assignment obj
        $this->assignment->courseid   = $this->course->id; // compatibility with modedit assignment obj

        $this->strassignment = get_string('modulename', 'assignment');
        $this->strassignments = get_string('modulenameplural', 'assignment');
        $this->strsubmissions = get_string('submissions', 'assignment');
        $this->strlastmodified = get_string('lastmodified');
        $this->pagetitle = strip_tags($courseshortname.': '.$this->strassignment.': '.format_string($this->assignment->name, true, array('context' => $this->context)));

        // visibility handled by require_login() with $cm parameter
        // get current group only when really needed

    /// Set up things for a HTML editor if it's needed
        $this->defaultformat = editors_get_preferred_format();
    }

    /**
     * Display the assignment, used by view.php
     *
     * This in turn calls the methods producing individual parts of the page
     */
    function view() {

        $context = context_module::instance($this->cm->id);
        require_capability('mod/assignment:view', $context);

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}",
                   $this->assignment->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

        $this->view_dates();

        $this->view_feedback();

        $this->view_footer();
    }

    /**
     * Display the header and top of a page
     *
     * (this doesn't change much for assignment types)
     * This is used by the view() method to print the header of view.php but
     * it can be used on other pages in which case the string to denote the
     * page in the navigation trail should be passed as an argument
     *
     * @global object
     * @param string $subpage Description of subpage to be used in navigation trail
     */
    function view_header($subpage='') {
        global $CFG, $PAGE, $OUTPUT;

        if ($subpage) {
            $PAGE->navbar->add($subpage);
        }

        $PAGE->set_title($this->pagetitle);
        $PAGE->set_heading($this->course->fullname);

        echo $OUTPUT->header();

        groups_print_activity_menu($this->cm, $CFG->wwwroot . '/mod/assignment/view.php?id=' . $this->cm->id);

        echo '<div class="reportlink">'.$this->submittedlink().'</div>';
        echo '<div class="clearer"></div>';
        if (has_capability('moodle/site:config', context_system::instance())) {
            echo $OUTPUT->notification(get_string('upgradenotification', 'assignment'));
            $adminurl = new moodle_url('/admin/tool/assignmentupgrade/listnotupgraded.php');
            echo $OUTPUT->single_button($adminurl, get_string('viewassignmentupgradetool', 'assignment'));
        }
    }


    /**
     * Display the assignment intro
     *
     * This will most likely be extended by assignment type plug-ins
     * The default implementation prints the assignment description in a box
     */
    function view_intro() {
        global $OUTPUT;
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo format_module_intro('assignment', $this->assignment, $this->cm->id);
        echo $OUTPUT->box_end();
        echo plagiarism_print_disclosure($this->cm->id);
    }

    /**
     * Display the assignment dates
     *
     * Prints the assignment start and end dates in a box.
     * This will be suitable for most assignment types
     */
    function view_dates() {
        global $OUTPUT;
        if (!$this->assignment->timeavailable && !$this->assignment->timedue) {
            return;
        }

        echo $OUTPUT->box_start('generalbox boxaligncenter', 'dates');
        echo '<table>';
        if ($this->assignment->timeavailable) {
            echo '<tr><td class="c0">'.get_string('availabledate','assignment').':</td>';
            echo '    <td class="c1">'.userdate($this->assignment->timeavailable).'</td></tr>';
        }
        if ($this->assignment->timedue) {
            echo '<tr><td class="c0">'.get_string('duedate','assignment').':</td>';
            echo '    <td class="c1">'.userdate($this->assignment->timedue).'</td></tr>';
        }
        echo '</table>';
        echo $OUTPUT->box_end();
    }


    /**
     * Display the bottom and footer of a page
     *
     * This default method just prints the footer.
     * This will be suitable for most assignment types
     */
    function view_footer() {
        global $OUTPUT;
        echo $OUTPUT->footer();
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     * If advanced grading is used the method render_grade from the
     * advanced grading controller is called to display the grade.
     *
     * @global object
     * @global object
     * @global object
     * @param object $submission The submission object or NULL in which case it will be loaded
     * @return bool
     */
    function view_feedback($submission=NULL) {
        global $USER, $CFG, $DB, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');
        require_once("$CFG->dirroot/grade/grading/lib.php");

        if (!$submission) { /// Get submission for this assignment
            $userid = $USER->id;
            $submission = $this->get_submission($userid);
        } else {
            $userid = $submission->userid;
        }
        // Check the user can submit
        $canviewfeedback = ($userid == $USER->id && has_capability('mod/assignment:submit', $this->context, $USER->id, false));
        // If not then check if the user still has the view cap and has a previous submission
        $canviewfeedback = $canviewfeedback || (!empty($submission) && $submission->userid == $USER->id && has_capability('mod/assignment:view', $this->context));
        // Or if user can grade (is a teacher or admin)
        $canviewfeedback = $canviewfeedback || has_capability('mod/assignment:grade', $this->context);

        if (!$canviewfeedback) {
            // can not view or submit assignments -> no feedback
            return false;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $userid);
        $item = $grading_info->items[0];
        $grade = $item->grades[$userid];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return false;
        }

        if ($grade->grade === null and empty($grade->str_feedback)) { // No grade to show yet
            // If sumbission then check if feedback is avaiable to show else return.
            if (!$submission) {
                return false;
            }

            $fs = get_file_storage();
            $noresponsefiles = $fs->is_area_empty($this->context->id, 'mod_assignment', 'response', $submission->id);
            if (empty($submission->submissioncomment) && $noresponsefiles) { // Nothing to show yet
                return false;
            }

            // We need the teacher info
            if (!$teacher = $DB->get_record('user', array('id'=>$submission->teacher))) {
                print_error('cannotfindteacher');
            }

            $feedbackdate = $submission->timemarked;
            $feedback = format_text($submission->submissioncomment, $submission->format);
            $strlonggrade = '-';
        }
        else {
            // We need the teacher info
            if (!$teacher = $DB->get_record('user', array('id'=>$grade->usermodified))) {
                print_error('cannotfindteacher');
            }

            $feedbackdate = $grade->dategraded;
            $feedback = $grade->str_feedback;
            $strlonggrade = $grade->str_long_grade;
        }

        // Print the feedback
        echo $OUTPUT->heading(get_string('submissionfeedback', 'assignment'), 3);

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        if ($teacher) {
            echo $OUTPUT->user_picture($teacher);
        }
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        if ($teacher) {
            echo '<div class="fullname">'.fullname($teacher).'</div>';
        }
        echo '<div class="time">'.userdate($feedbackdate).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';

        if ($this->assignment->grade) {
            $gradestr = '<div class="grade">'. get_string("grade").': '.$strlonggrade. '</div>';
            if (!empty($submission) && $controller = get_grading_manager($this->context, 'mod_assignment', 'submission')->get_active_controller()) {
                $controller->set_grade_range(make_grades_menu($this->assignment->grade));
                echo $controller->render_grade($PAGE, $submission->id, $item, $gradestr, has_capability('mod/assignment:grade', $this->context));
            } else {
                echo $gradestr;
            }
            echo '<div class="clearer"></div>';
        }

        echo '<div class="comment">';
        echo $feedback;
        echo '</div>';
        echo '</tr>';
        if (method_exists($this, 'view_responsefile')) {
            $this->view_responsefile($submission);
        }
        echo '</table>';

        return true;
    }

    /**
     * Returns a link with info about the state of the assignment submissions
     *
     * This is used by view_header to put this link at the top right of the page.
     * For teachers it gives the number of submitted assignments with a link
     * For students it gives the time of their submission.
     * This will be suitable for most assignment types.
     *
     * @global object
     * @global object
     * @param bool $allgroup print all groups info if user can access all groups, suitable for index.php
     * @return string
     */
    function submittedlink($allgroups=false) {
        global $USER;
        global $CFG;

        $submitted = '';
        $urlbase = "{$CFG->wwwroot}/mod/assignment/";

        $context = context_module::instance($this->cm->id);
        if (has_capability('mod/assignment:grade', $context)) {
            if ($allgroups and has_capability('moodle/site:accessallgroups', $context)) {
                $group = 0;
            } else {
                $group = groups_get_activity_group($this->cm);
            }
            if ($this->type == 'offline') {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('viewfeedback', 'assignment').'</a>';
            } else if ($count = $this->count_real_submissions($group)) {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('viewsubmissions', 'assignment', $count).'</a>';
            } else {
                $submitted = '<a href="'.$urlbase.'submissions.php?id='.$this->cm->id.'">'.
                             get_string('noattempts', 'assignment').'</a>';
            }
        } else {
            if (isloggedin()) {
                if ($submission = $this->get_submission($USER->id)) {
                    // If the submission has been completed
                    if ($this->is_submitted_with_required_data($submission)) {
                        if ($submission->timemodified <= $this->assignment->timedue || empty($this->assignment->timedue)) {
                            $submitted = '<span class="early">'.userdate($submission->timemodified).'</span>';
                        } else {
                            $submitted = '<span class="late">'.userdate($submission->timemodified).'</span>';
                        }
                    }
                }
            }
        }

        return $submitted;
    }

    /**
     * Returns whether the assigment supports lateness information
     *
     * @return bool This assignment type supports lateness (true, default) or no (false)
     */
    function supports_lateness() {
        return true;
    }

    /**
     * @todo Document this function
     */
    function setup_elements(&$mform) {

    }

    /**
     * Any preprocessing needed for the settings form for
     * this assignment type
     *
     * @param array $default_values - array to fill in with the default values
     *      in the form 'formelement' => 'value'
     * @param object $form - the form that is to be displayed
     * @return none
     */
    function form_data_preprocessing(&$default_values, $form) {
    }

    /**
     * Any extra validation checks needed for the settings
     * form for this assignment type
     *
     * See lib/formslib.php, 'validation' function for details
     */
    function form_validation($data, $files) {
        return array();
    }

    /**
     * Create a new assignment activity
     *
     * Given an object containing all the necessary data,
     * (defined by the form in mod_form.php) this function
     * will create a new instance and return the id number
     * of the new instance.
     * The due data is added to the calendar
     * This is common to all assignment types.
     *
     * @global object
     * @global object
     * @param object $assignment The data from the form on mod_form.php
     * @return int The id of the assignment
     */
    function add_instance($assignment) {
        global $COURSE, $DB;

        $assignment->timemodified = time();
        $assignment->courseid = $assignment->course;

        $returnid = $DB->insert_record("assignment", $assignment);
        $assignment->id = $returnid;

        if ($assignment->timedue) {
            $event = new stdClass();
            $event->name        = $assignment->name;
            $event->description = format_module_intro('assignment', $assignment, $assignment->coursemodule);
            $event->courseid    = $assignment->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'assignment';
            $event->instance    = $returnid;
            $event->eventtype   = 'due';
            $event->timestart   = $assignment->timedue;
            $event->timeduration = 0;

            calendar_event::create($event);
        }

        assignment_grade_item_update($assignment);

        return $returnid;
    }

    /**
     * Deletes an assignment activity
     *
     * Deletes all database records, files and calendar events for this assignment.
     *
     * @global object
     * @global object
     * @param object $assignment The assignment to be deleted
     * @return boolean False indicates error
     */
    function delete_instance($assignment) {
        global $CFG, $DB;

        $assignment->courseid = $assignment->course;

        $result = true;

        // now get rid of all files
        $fs = get_file_storage();
        if ($cm = get_coursemodule_from_instance('assignment', $assignment->id)) {
            $context = context_module::instance($cm->id);
            $fs->delete_area_files($context->id);
        }

        if (! $DB->delete_records('assignment_submissions', array('assignment'=>$assignment->id))) {
            $result = false;
        }

        if (! $DB->delete_records('event', array('modulename'=>'assignment', 'instance'=>$assignment->id))) {
            $result = false;
        }

        if (! $DB->delete_records('assignment', array('id'=>$assignment->id))) {
            $result = false;
        }
        $mod = $DB->get_field('modules','id',array('name'=>'assignment'));

        assignment_grade_item_delete($assignment);

        return $result;
    }

    /**
     * Updates a new assignment activity
     *
     * Given an object containing all the necessary data,
     * (defined by the form in mod_form.php) this function
     * will update the assignment instance and return the id number
     * The due date is updated in the calendar
     * This is common to all assignment types.
     *
     * @global object
     * @global object
     * @param object $assignment The data from the form on mod_form.php
     * @return bool success
     */
    function update_instance($assignment) {
        global $COURSE, $DB;

        $assignment->timemodified = time();

        $assignment->id = $assignment->instance;
        $assignment->courseid = $assignment->course;

        $DB->update_record('assignment', $assignment);

        if ($assignment->timedue) {
            $event = new stdClass();

            if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'assignment', 'instance'=>$assignment->id))) {

                $event->name        = $assignment->name;
                $event->description = format_module_intro('assignment', $assignment, $assignment->coursemodule);
                $event->timestart   = $assignment->timedue;

                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
            } else {
                $event = new stdClass();
                $event->name        = $assignment->name;
                $event->description = format_module_intro('assignment', $assignment, $assignment->coursemodule);
                $event->courseid    = $assignment->course;
                $event->groupid     = 0;
                $event->userid      = 0;
                $event->modulename  = 'assignment';
                $event->instance    = $assignment->id;
                $event->eventtype   = 'due';
                $event->timestart   = $assignment->timedue;
                $event->timeduration = 0;

                calendar_event::create($event);
            }
        } else {
            $DB->delete_records('event', array('modulename'=>'assignment', 'instance'=>$assignment->id));
        }

        // get existing grade item
        assignment_grade_item_update($assignment);

        return true;
    }

    /**
     * Update grade item for this submission.
     *
     * @param stdClass $submission The submission instance
     */
    function update_grade($submission) {
        assignment_update_grades($this->assignment, $submission->userid);
    }

    /**
     * Top-level function for handling of submissions called by submissions.php
     *
     * This is for handling the teacher interaction with the grading interface
     * This should be suitable for most assignment types.
     *
     * @global object
     * @param string $mode Specifies the kind of teacher interaction taking place
     */
    function submissions($mode) {
        ///The main switch is changed to facilitate
        ///1) Batch fast grading
        ///2) Skip to the next one on the popup
        ///3) Save and Skip to the next one on the popup

        //make user global so we can use the id
        global $USER, $OUTPUT, $DB, $PAGE;

        $mailinfo = optional_param('mailinfo', null, PARAM_BOOL);

        if (optional_param('next', null, PARAM_BOOL)) {
            $mode='next';
        }
        if (optional_param('saveandnext', null, PARAM_BOOL)) {
            $mode='saveandnext';
        }

        if (is_null($mailinfo)) {
            if (optional_param('sesskey', null, PARAM_BOOL)) {
                set_user_preference('assignment_mailinfo', 0);
            } else {
                $mailinfo = get_user_preferences('assignment_mailinfo', 0);
            }
        } else {
            set_user_preference('assignment_mailinfo', $mailinfo);
        }

        if (!($this->validate_and_preprocess_feedback())) {
            // form was submitted ('Save' or 'Save and next' was pressed, but validation failed)
            $this->display_submission();
            return;
        }

        switch ($mode) {
            case 'grade':                         // We are in a main window grading
                if ($submission = $this->process_feedback()) {
                    $this->display_submissions(get_string('changessaved'));
                } else {
                    $this->display_submissions();
                }
                break;

            case 'single':                        // We are in a main window displaying one submission
                if ($submission = $this->process_feedback()) {
                    $this->display_submissions(get_string('changessaved'));
                } else {
                    $this->display_submission();
                }
                break;

            case 'all':                          // Main window, display everything
                $this->display_submissions();
                break;

            case 'fastgrade':
                ///do the fast grading stuff  - this process should work for all 3 subclasses
                $grading    = false;
                $commenting = false;
                $col        = false;
                if (isset($_POST['submissioncomment'])) {
                    $col = 'submissioncomment';
                    $commenting = true;
                }
                if (isset($_POST['menu'])) {
                    $col = 'menu';
                    $grading = true;
                }
                if (!$col) {
                    //both submissioncomment and grade columns collapsed..
                    $this->display_submissions();
                    break;
                }

                foreach ($_POST[$col] as $id => $unusedvalue){

                    $id = (int)$id; //clean parameter name

                    $this->process_outcomes($id);

                    if (!$submission = $this->get_submission($id)) {
                        $submission = $this->prepare_new_submission($id);
                        $newsubmission = true;
                    } else {
                        $newsubmission = false;
                    }
                    unset($submission->data1);  // Don't need to update this.
                    unset($submission->data2);  // Don't need to update this.

                    //for fast grade, we need to check if any changes take place
                    $updatedb = false;

                    if ($grading) {
                        $grade = $_POST['menu'][$id];
                        $updatedb = $updatedb || ($submission->grade != $grade);
                        $submission->grade = $grade;
                    } else {
                        if (!$newsubmission) {
                            unset($submission->grade);  // Don't need to update this.
                        }
                    }
                    if ($commenting) {
                        $commentvalue = trim($_POST['submissioncomment'][$id]);
                        $updatedb = $updatedb || ($submission->submissioncomment != $commentvalue);
                        $submission->submissioncomment = $commentvalue;
                    } else {
                        unset($submission->submissioncomment);  // Don't need to update this.
                    }

                    $submission->teacher    = $USER->id;
                    if ($updatedb) {
                        $submission->mailed = (int)(!$mailinfo);
                    }

                    $submission->timemarked = time();

                    //if it is not an update, we don't change the last modified time etc.
                    //this will also not write into database if no submissioncomment and grade is entered.

                    if ($updatedb){
                        if ($newsubmission) {
                            if (!isset($submission->submissioncomment)) {
                                $submission->submissioncomment = '';
                            }
                            $sid = $DB->insert_record('assignment_submissions', $submission);
                            $submission->id = $sid;
                        } else {
                            $DB->update_record('assignment_submissions', $submission);
                        }

                        // trigger grade event
                        $this->update_grade($submission);

                        //add to log only if updating
                        add_to_log($this->course->id, 'assignment', 'update grades',
                                   'submissions.php?id='.$this->cm->id.'&user='.$submission->userid,
                                   $submission->userid, $this->cm->id);
                    }

                }

                $message = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');

                $this->display_submissions($message);
                break;


            case 'saveandnext':
                ///We are in pop up. save the current one and go to the next one.
                //first we save the current changes
                if ($submission = $this->process_feedback()) {
                    //print_heading(get_string('changessaved'));
                    //$extra_javascript = $this->update_main_listing($submission);
                }

            case 'next':
                /// We are currently in pop up, but we want to skip to next one without saving.
                ///    This turns out to be similar to a single case
                /// The URL used is for the next submission.
                $offset = required_param('offset', PARAM_INT);
                $nextid = required_param('nextid', PARAM_INT);
                $id = required_param('id', PARAM_INT);
                $filter = optional_param('filter', self::FILTER_ALL, PARAM_INT);

                if ($mode == 'next' || $filter !== self::FILTER_REQUIRE_GRADING) {
                    $offset = (int)$offset+1;
                }
                $redirect = new moodle_url('submissions.php',
                        array('id' => $id, 'offset' => $offset, 'userid' => $nextid,
                        'mode' => 'single', 'filter' => $filter));

                redirect($redirect);
                break;

            case 'singlenosave':
                $this->display_submission();
                break;

            default:
                echo "something seriously is wrong!!";
                break;
        }
    }

    /**
     * Checks if grading method allows quickgrade mode. At the moment it is hardcoded
     * that advanced grading methods do not allow quickgrade.
     *
     * Assignment type plugins are not allowed to override this method
     *
     * @return boolean
     */
    public final function quickgrade_mode_allowed() {
        global $CFG;
        require_once("$CFG->dirroot/grade/grading/lib.php");
        if ($controller = get_grading_manager($this->context, 'mod_assignment', 'submission')->get_active_controller()) {
            return false;
        }
        return true;
    }

    /**
     * Helper method updating the listing on the main script from popup using javascript
     *
     * @global object
     * @global object
     * @param $submission object The submission whose data is to be updated on the main page
     */
    function update_main_listing($submission) {
        global $SESSION, $CFG, $OUTPUT;

        $output = '';

        $perpage = get_user_preferences('assignment_perpage', 10);

        $quickgrade = get_user_preferences('assignment_quickgrade', 0) && $this->quickgrade_mode_allowed();

        /// Run some Javascript to try and update the parent page
        $output .= '<script type="text/javascript">'."\n<!--\n";
        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['submissioncomment'])) {
            if ($quickgrade){
                $output.= 'opener.document.getElementById("submissioncomment'.$submission->userid.'").value="'
                .trim($submission->submissioncomment).'";'."\n";
             } else {
                $output.= 'opener.document.getElementById("com'.$submission->userid.
                '").innerHTML="'.shorten_text(trim(strip_tags($submission->submissioncomment)), 15)."\";\n";
            }
        }

        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['grade'])) {
            //echo optional_param('menuindex');
            if ($quickgrade){
                $output.= 'opener.document.getElementById("menumenu'.$submission->userid.
                '").selectedIndex="'.optional_param('menuindex', 0, PARAM_INT).'";'."\n";
            } else {
                $output.= 'opener.document.getElementById("g'.$submission->userid.'").innerHTML="'.
                $this->display_grade($submission->grade)."\";\n";
            }
        }
        //need to add student's assignments in there too.
        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['timemodified']) &&
            $submission->timemodified) {
            $output.= 'opener.document.getElementById("ts'.$submission->userid.
                 '").innerHTML="'.addslashes_js($this->print_student_answer($submission->userid)).userdate($submission->timemodified)."\";\n";
        }

        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['timemarked']) &&
            $submission->timemarked) {
            $output.= 'opener.document.getElementById("tt'.$submission->userid.
                 '").innerHTML="'.userdate($submission->timemarked)."\";\n";
        }

        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['status'])) {
            $output.= 'opener.document.getElementById("up'.$submission->userid.'").className="s1";';
            $buttontext = get_string('update');
            $url = new moodle_url('/mod/assignment/submissions.php', array(
                    'id' => $this->cm->id,
                    'userid' => $submission->userid,
                    'mode' => 'single',
                    'offset' => (optional_param('offset', '', PARAM_INT)-1)));
            $button = $OUTPUT->action_link($url, $buttontext, new popup_action('click', $url, 'grade'.$submission->userid, array('height' => 450, 'width' => 700)), array('ttile'=>$buttontext));

            $output .= 'opener.document.getElementById("up'.$submission->userid.'").innerHTML="'.addslashes_js($button).'";';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $submission->userid);

        if (empty($SESSION->flextable['mod-assignment-submissions']->collapse['finalgrade'])) {
            $output.= 'opener.document.getElementById("finalgrade_'.$submission->userid.
            '").innerHTML="'.$grading_info->items[0]->grades[$submission->userid]->str_grade.'";'."\n";
        }

        if (!empty($CFG->enableoutcomes) and empty($SESSION->flextable['mod-assignment-submissions']->collapse['outcome'])) {

            if (!empty($grading_info->outcomes)) {
                foreach($grading_info->outcomes as $n=>$outcome) {
                    if ($outcome->grades[$submission->userid]->locked) {
                        continue;
                    }

                    if ($quickgrade){
                        $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.
                        '").selectedIndex="'.$outcome->grades[$submission->userid]->grade.'";'."\n";

                    } else {
                        $options = make_grades_menu(-$outcome->scaleid);
                        $options[0] = get_string('nooutcome', 'grades');
                        $output.= 'opener.document.getElementById("outcome_'.$n.'_'.$submission->userid.'").innerHTML="'.$options[$outcome->grades[$submission->userid]->grade]."\";\n";
                    }

                }
            }
        }

        $output .= "\n-->\n</script>";
        return $output;
    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object
     * @param mixed $grade
     * @return string User-friendly representation of grade
     */
    function display_grade($grade) {
        global $DB;

        static $scalegrades = array();   // Cache scales for each assignment - they might have different scales!!

        if ($this->assignment->grade >= 0) {    // Normal number
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->assignment->grade;
            }

        } else {                                // Scale
            if (empty($scalegrades[$this->assignment->id])) {
                if ($scale = $DB->get_record('scale', array('id'=>-($this->assignment->grade)))) {
                    $scalegrades[$this->assignment->id] = make_menu_from_list($scale->scale);
                } else {
                    return '-';
                }
            }
            if (isset($scalegrades[$this->assignment->id][$grade])) {
                return $scalegrades[$this->assignment->id][$grade];
            }
            return '-';
        }
    }

    /**
     *  Display a single submission, ready for grading on a popup window
     *
     * This default method prints the teacher info and submissioncomment box at the top and
     * the student info and submission at the bottom.
     * This method also fetches the necessary data in order to be able to
     * provide a "Next submission" button.
     * Calls preprocess_submission() to give assignment type plug-ins a chance
     * to process submissions before they are graded
     * This method gets its arguments from the page parameters userid and offset
     *
     * @global object
     * @global object
     * @param string $extra_javascript
     */
    function display_submission($offset=-1,$userid =-1, $display=true) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/tablelib.php');
        require_once("$CFG->dirroot/repository/lib.php");
        require_once("$CFG->dirroot/grade/grading/lib.php");
        if ($userid==-1) {
            $userid = required_param('userid', PARAM_INT);
        }
        if ($offset==-1) {
            $offset = required_param('offset', PARAM_INT);//offset for where to start looking for student.
        }
        $filter = optional_param('filter', 0, PARAM_INT);

        if (!$user = $DB->get_record('user', array('id'=>$userid))) {
            print_error('nousers');
        }

        if (!$submission = $this->get_submission($user->id)) {
            $submission = $this->prepare_new_submission($userid);
        }
        if ($submission->timemodified > $submission->timemarked) {
            $subtype = 'assignmentnew';
        } else {
            $subtype = 'assignmentold';
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array($user->id));
        $gradingdisabled = $grading_info->items[0]->grades[$userid]->locked || $grading_info->items[0]->grades[$userid]->overridden;

    /// construct SQL, using current offset to find the data of the next student
        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;
        $context    = context_module::instance($cm->id);

        //reset filter to all for offline assignment
        if ($assignment->assignmenttype == 'offline' && $filter == self::FILTER_SUBMITTED) {
            $filter = self::FILTER_ALL;
        }
        /// Get all ppl that can submit assignments

        $currentgroup = groups_get_activity_group($cm);
        $users = get_enrolled_users($context, 'mod/assignment:submit', $currentgroup, 'u.id');
        if ($users) {
            $users = array_keys($users);
            // if groupmembersonly used, remove users who are not in any group
            if (!empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
                if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                    $users = array_intersect($users, array_keys($groupingusers));
                }
            }
        }

        $nextid = 0;
        $where = '';
        if($filter == self::FILTER_SUBMITTED) {
            $where .= 's.timemodified > 0 AND ';
        } else if($filter == self::FILTER_REQUIRE_GRADING) {
            $where .= 's.timemarked < s.timemodified AND ';
        }

        if ($users) {
            $userfields = user_picture::fields('u', array('lastaccess'));
            $select = "SELECT $userfields,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
                              CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
                                   ELSE 0 END AS status ";

            $sql = 'FROM {user} u '.
                   'LEFT JOIN {assignment_submissions} s ON u.id = s.userid
                   AND s.assignment = '.$this->assignment->id.' '.
                   'WHERE '.$where.'u.id IN ('.implode(',', $users).') ';

            if ($sort = flexible_table::get_sort_for_table('mod-assignment-submissions')) {
                $sort = 'ORDER BY '.$sort.' ';
            }
            $auser = $DB->get_records_sql($select.$sql.$sort, null, $offset, 2);

            if (is_array($auser) && count($auser)>1) {
                $nextuser = next($auser);
                $nextid = $nextuser->id;
            }
        }

        if ($submission->teacher) {
            $teacher = $DB->get_record('user', array('id'=>$submission->teacher));
        } else {
            global $USER;
            $teacher = $USER;
        }

        $this->preprocess_submission($submission);

        $mformdata = new stdClass();
        $mformdata->context = $this->context;
        $mformdata->maxbytes = $this->course->maxbytes;
        $mformdata->courseid = $this->course->id;
        $mformdata->teacher = $teacher;
        $mformdata->assignment = $assignment;
        $mformdata->submission = $submission;
        $mformdata->lateness = $this->display_lateness($submission->timemodified);
        $mformdata->auser = $auser;
        $mformdata->user = $user;
        $mformdata->offset = $offset;
        $mformdata->userid = $userid;
        $mformdata->cm = $this->cm;
        $mformdata->grading_info = $grading_info;
        $mformdata->enableoutcomes = $CFG->enableoutcomes;
        $mformdata->grade = $this->assignment->grade;
        $mformdata->gradingdisabled = $gradingdisabled;
        $mformdata->nextid = $nextid;
        $mformdata->submissioncomment= $submission->submissioncomment;
        $mformdata->submissioncommentformat= FORMAT_HTML;
        $mformdata->submission_content= $this->print_user_files($user->id,true);
        $mformdata->filter = $filter;
        $mformdata->mailinfo = get_user_preferences('assignment_mailinfo', 0);
         if ($assignment->assignmenttype == 'upload') {
            $mformdata->fileui_options = array('subdirs'=>1, 'maxbytes'=>$assignment->maxbytes, 'maxfiles'=>$assignment->var1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        } elseif ($assignment->assignmenttype == 'uploadsingle') {
            $mformdata->fileui_options = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1, 'accepted_types'=>'*', 'return_types'=>FILE_INTERNAL);
        }
        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($this->context, 'mod_assignment', 'submission');
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if (!empty($submission->id)) {
                    $itemid = $submission->id;
                }
                if ($gradingdisabled && $itemid) {
                    $mformdata->advancedgradinginstance = $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $mformdata->advancedgradinginstance = $controller->get_or_create_instance($instanceid, $USER->id, $itemid);
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }

        $submitform = new assignment_grading_form( null, $mformdata );

         if (!$display) {
            $ret_data = new stdClass();
            $ret_data->mform = $submitform;
            if (isset($mformdata->fileui_options)) {
                $ret_data->fileui_options = $mformdata->fileui_options;
            }
            return $ret_data;
        }

        if ($submitform->is_cancelled()) {
            redirect('submissions.php?id='.$this->cm->id);
        }

        $submitform->set_data($mformdata);

        $PAGE->set_title($this->course->fullname . ': ' .get_string('feedback', 'assignment').' - '.fullname($user, true));
        $PAGE->set_heading($this->course->fullname);
        $PAGE->navbar->add(get_string('submissions', 'assignment'), new moodle_url('/mod/assignment/submissions.php', array('id'=>$cm->id)));
        $PAGE->navbar->add(fullname($user, true));

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('feedback', 'assignment').': '.fullname($user, true));

        // display mform here...
        if ($advancedgradingwarning) {
            echo $OUTPUT->notification($advancedgradingwarning, 'error');
        }
        $submitform->display();

        $customfeedback = $this->custom_feedbackform($submission, true);
        if (!empty($customfeedback)) {
            echo $customfeedback;
        }

        echo $OUTPUT->footer();
    }

    /**
     *  Preprocess submission before grading
     *
     * Called by display_submission()
     * The default type does nothing here.
     *
     * @param object $submission The submission object
     */
    function preprocess_submission(&$submission) {
    }

    /**
     *  Display all the submissions ready for grading
     *
     * @global object
     * @global object
     * @global object
     * @global object
     * @param string $message
     * @return bool|void
     */
    function display_submissions($message='') {
        global $CFG, $DB, $USER, $DB, $OUTPUT, $PAGE;
        require_once($CFG->libdir.'/gradelib.php');

        /* first we check to see if the form has just been submitted
         * to request user_preference updates
         */

       $filters = array(self::FILTER_ALL             => get_string('all'),
                        self::FILTER_REQUIRE_GRADING => get_string('requiregrading', 'assignment'));

        $updatepref = optional_param('updatepref', 0, PARAM_BOOL);
        if ($updatepref) {
            $perpage = optional_param('perpage', 10, PARAM_INT);
            $perpage = ($perpage <= 0) ? 10 : $perpage ;
            $filter = optional_param('filter', 0, PARAM_INT);
            set_user_preference('assignment_perpage', $perpage);
            set_user_preference('assignment_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
            set_user_preference('assignment_filter', $filter);
        }

        /* next we get perpage and quickgrade (allow quick grade) params
         * from database
         */
        $perpage    = get_user_preferences('assignment_perpage', 10);
        $quickgrade = get_user_preferences('assignment_quickgrade', 0) && $this->quickgrade_mode_allowed();
        $filter = get_user_preferences('assignment_filter', 0);
        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id);

        if (!empty($CFG->enableoutcomes) and !empty($grading_info->outcomes)) {
            $uses_outcomes = true;
        } else {
            $uses_outcomes = false;
        }

        $page    = optional_param('page', 0, PARAM_INT);
        $strsaveallfeedback = get_string('saveallfeedback', 'assignment');

    /// Some shortcuts to make the code read better

        $course     = $this->course;
        $assignment = $this->assignment;
        $cm         = $this->cm;
        $hassubmission = false;

        // reset filter to all for offline assignment only.
        if ($assignment->assignmenttype == 'offline') {
            if ($filter == self::FILTER_SUBMITTED) {
                $filter = self::FILTER_ALL;
            }
        } else {
            $filters[self::FILTER_SUBMITTED] = get_string('submitted', 'assignment');
        }

        $tabindex = 1; //tabindex for quick grading tabbing; Not working for dropdowns yet
        add_to_log($course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->cm->id, $this->assignment->id, $this->cm->id);

        $PAGE->set_title($this->assignment->name);
        $PAGE->set_heading($this->course->fullname);
        echo $OUTPUT->header();

        echo '<div class="usersubmissions">';

        //hook to allow plagiarism plugins to update status/print links.
        echo plagiarism_update_status($this->course, $this->cm);

        $course_context = context_course::instance($course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
                . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }

        if (!empty($message)) {
            echo $message;   // display messages here if any
        }

        $context = context_module::instance($cm->id);

    /// Check to see if groups are being used in this assignment

        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/assignment/submissions.php?id=' . $this->cm->id);

        /// Print quickgrade form around the table
        if ($quickgrade) {
            $formattrs = array();
            $formattrs['action'] = new moodle_url('/mod/assignment/submissions.php');
            $formattrs['id'] = 'fastg';
            $formattrs['method'] = 'post';

            echo html_writer::start_tag('form', $formattrs);
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'id',      'value'=> $this->cm->id));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'mode',    'value'=> 'fastgrade'));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'page',    'value'=> $page));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=> sesskey()));
        }

        /// Get all ppl that are allowed to submit assignments
        list($esql, $params) = get_enrolled_sql($context, 'mod/assignment:submit', $currentgroup);

        if ($filter == self::FILTER_ALL) {
            $sql = "SELECT u.id FROM {user} u ".
                   "LEFT JOIN ($esql) eu ON eu.id=u.id ".
                   "WHERE u.deleted = 0 AND eu.id=u.id ";
        } else {
            $wherefilter = ' AND s.assignment = '. $this->assignment->id;
            $assignmentsubmission = "LEFT JOIN {assignment_submissions} s ON (u.id = s.userid) ";
            if($filter == self::FILTER_SUBMITTED) {
                $wherefilter .= ' AND s.timemodified > 0 ';
            } else if($filter == self::FILTER_REQUIRE_GRADING && $assignment->assignmenttype != 'offline') {
                $wherefilter .= ' AND s.timemarked < s.timemodified ';
            } else { // require grading for offline assignment
                $assignmentsubmission = "";
                $wherefilter = "";
            }

            $sql = "SELECT u.id FROM {user} u ".
                   "LEFT JOIN ($esql) eu ON eu.id=u.id ".
                   $assignmentsubmission.
                   "WHERE u.deleted = 0 AND eu.id=u.id ".
                   $wherefilter;
        }

        $users = $DB->get_records_sql($sql, $params);
        if (!empty($users)) {
            if($assignment->assignmenttype == 'offline' && $filter == self::FILTER_REQUIRE_GRADING) {
                //remove users who has submitted their assignment
                foreach ($this->get_submissions() as $submission) {
                    if (array_key_exists($submission->userid, $users)) {
                        unset($users[$submission->userid]);
                    }
                }
            }
            $users = array_keys($users);
        }

        // if groupmembersonly used, remove users who are not in any group
        if ($users and !empty($CFG->enablegroupmembersonly) and $cm->groupmembersonly) {
            if ($groupingusers = groups_get_grouping_members($cm->groupingid, 'u.id', 'u.id')) {
                $users = array_intersect($users, array_keys($groupingusers));
            }
        }

        $extrafields = get_extra_user_fields($context);
        $tablecolumns = array_merge(array('picture', 'fullname'), $extrafields,
                array('grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade'));
        if ($uses_outcomes) {
            $tablecolumns[] = 'outcome'; // no sorting based on outcomes column
        }

        $extrafieldnames = array();
        foreach ($extrafields as $field) {
            $extrafieldnames[] = get_user_field_name($field);
        }
        $tableheaders = array_merge(
                array('', get_string('fullnameuser')),
                $extrafieldnames,
                array(
                    get_string('grade'),
                    get_string('comment', 'assignment'),
                    get_string('lastmodified').' ('.get_string('submission', 'assignment').')',
                    get_string('lastmodified').' ('.get_string('grade').')',
                    get_string('status'),
                    get_string('finalgrade', 'grades'),
                ));
        if ($uses_outcomes) {
            $tableheaders[] = get_string('outcome', 'grades');
        }

        require_once($CFG->libdir.'/tablelib.php');
        $table = new flexible_table('mod-assignment-submissions');

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($CFG->wwwroot.'/mod/assignment/submissions.php?id='.$this->cm->id.'&amp;currentgroup='.$currentgroup);

        $table->sortable(true, 'lastname');//sorted by lastname by default
        $table->collapsible(true);
        $table->initialbars(true);

        $table->column_suppress('picture');
        $table->column_suppress('fullname');

        $table->column_class('picture', 'picture');
        $table->column_class('fullname', 'fullname');
        foreach ($extrafields as $field) {
            $table->column_class($field, $field);
        }
        $table->column_class('grade', 'grade');
        $table->column_class('submissioncomment', 'comment');
        $table->column_class('timemodified', 'timemodified');
        $table->column_class('timemarked', 'timemarked');
        $table->column_class('status', 'status');
        $table->column_class('finalgrade', 'finalgrade');
        if ($uses_outcomes) {
            $table->column_class('outcome', 'outcome');
        }

        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', 'attempts');
        $table->set_attribute('class', 'submissions');
        $table->set_attribute('width', '100%');

        $table->no_sorting('finalgrade');
        $table->no_sorting('outcome');
        $table->text_sorting('submissioncomment');

        // Start working -- this is necessary as soon as the niceties are over
        $table->setup();

        /// Construct the SQL
        list($where, $params) = $table->get_sql_where();
        if ($where) {
            $where .= ' AND ';
        }

        if ($filter == self::FILTER_SUBMITTED) {
           $where .= 's.timemodified > 0 AND ';
        } else if($filter == self::FILTER_REQUIRE_GRADING) {
            $where = '';
            if ($assignment->assignmenttype != 'offline') {
               $where .= 's.timemarked < s.timemodified AND ';
            }
        }

        if ($sort = $table->get_sql_sort()) {
            $sort = ' ORDER BY '.$sort;
        }

        $ufields = user_picture::fields('u', $extrafields);
        if (!empty($users)) {
            $select = "SELECT $ufields,
                              s.id AS submissionid, s.grade, s.submissioncomment,
                              s.timemodified, s.timemarked,
                              CASE WHEN s.timemarked > 0 AND s.timemarked >= s.timemodified THEN 1
                                   ELSE 0 END AS status ";

            $sql = 'FROM {user} u '.
                   'LEFT JOIN {assignment_submissions} s ON u.id = s.userid
                    AND s.assignment = '.$this->assignment->id.' '.
                   'WHERE '.$where.'u.id IN ('.implode(',',$users).') ';

            $ausers = $DB->get_records_sql($select.$sql.$sort, $params, $table->get_page_start(), $table->get_page_size());

            $table->pagesize($perpage, count($users));

            ///offset used to calculate index of student in that particular query, needed for the pop up to know who's next
            $offset = $page * $perpage;
            $strupdate = get_string('update');
            $strgrade  = get_string('grade');
            $strview  = get_string('view');
            $grademenu = make_grades_menu($this->assignment->grade);

            if ($ausers !== false) {
                $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array_keys($ausers));
                $endposition = $offset + $perpage;
                $currentposition = 0;
                foreach ($ausers as $auser) {
                    if ($currentposition == $offset && $offset < $endposition) {
                        $rowclass = null;
                        $final_grade = $grading_info->items[0]->grades[$auser->id];
                        $grademax = $grading_info->items[0]->grademax;
                        $final_grade->formatted_grade = round($final_grade->grade,2) .' / ' . round($grademax,2);
                        $locked_overridden = 'locked';
                        if ($final_grade->overridden) {
                            $locked_overridden = 'overridden';
                        }

                        // TODO add here code if advanced grading grade must be reviewed => $auser->status=0

                        $picture = $OUTPUT->user_picture($auser);

                        if (empty($auser->submissionid)) {
                            $auser->grade = -1; //no submission yet
                        }

                        if (!empty($auser->submissionid)) {
                            $hassubmission = true;
                        ///Prints student answer and student modified date
                        ///attach file or print link to student answer, depending on the type of the assignment.
                        ///Refer to print_student_answer in inherited classes.
                            if ($auser->timemodified > 0) {
                                $studentmodifiedcontent = $this->print_student_answer($auser->id)
                                        . userdate($auser->timemodified);
                                if ($assignment->timedue && $auser->timemodified > $assignment->timedue && $this->supports_lateness()) {
                                    $studentmodifiedcontent .= $this->display_lateness($auser->timemodified);
                                    $rowclass = 'late';
                                }
                            } else {
                                $studentmodifiedcontent = '&nbsp;';
                            }
                            $studentmodified = html_writer::tag('div', $studentmodifiedcontent, array('id' => 'ts' . $auser->id));
                        ///Print grade, dropdown or text
                            if ($auser->timemarked > 0) {
                                $teachermodified = '<div id="tt'.$auser->id.'">'.userdate($auser->timemarked).'</div>';

                                if ($final_grade->locked or $final_grade->overridden) {
                                    $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                                } else if ($quickgrade) {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $menu = html_writer::label(get_string('assignment:grade', 'assignment'), 'menumenu'. $auser->id, false, array('class' => 'accesshide'));
                                    $menu .= html_writer::select(make_grades_menu($this->assignment->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                    $grade = '<div id="g'.$auser->id.'">'. $menu .'</div>';
                                } else {
                                    $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                                }

                            } else {
                                $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                                if ($final_grade->locked or $final_grade->overridden) {
                                    $grade = '<div id="g'.$auser->id.'" class="'. $locked_overridden .'">'.$final_grade->formatted_grade.'</div>';
                                } else if ($quickgrade) {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $menu = html_writer::label(get_string('assignment:grade', 'assignment'), 'menumenu'. $auser->id, false, array('class' => 'accesshide'));
                                    $menu .= html_writer::select(make_grades_menu($this->assignment->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                    $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                                } else {
                                    $grade = '<div id="g'.$auser->id.'">'.$this->display_grade($auser->grade).'</div>';
                                }
                            }
                        ///Print Comment
                            if ($final_grade->locked or $final_grade->overridden) {
                                $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($final_grade->str_feedback),15).'</div>';

                            } else if ($quickgrade) {
                                $comment = '<div id="com'.$auser->id.'">'
                                         . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                         . $auser->id.'" rows="2" cols="20" spellcheck="true">'.($auser->submissioncomment).'</textarea></div>';
                            } else {
                                $comment = '<div id="com'.$auser->id.'">'.shorten_text(strip_tags($auser->submissioncomment),15).'</div>';
                            }
                        } else {
                            $studentmodified = '<div id="ts'.$auser->id.'">&nbsp;</div>';
                            $teachermodified = '<div id="tt'.$auser->id.'">&nbsp;</div>';
                            $status          = '<div id="st'.$auser->id.'">&nbsp;</div>';

                            if ($final_grade->locked or $final_grade->overridden) {
                                $grade = '<div id="g'.$auser->id.'">'.$final_grade->formatted_grade . '</div>';
                                $hassubmission = true;
                            } else if ($quickgrade) {   // allow editing
                                $attributes = array();
                                $attributes['tabindex'] = $tabindex++;
                                $menu = html_writer::label(get_string('assignment:grade', 'assignment'), 'menumenu'. $auser->id, false, array('class' => 'accesshide'));
                                $menu .= html_writer::select(make_grades_menu($this->assignment->grade), 'menu['.$auser->id.']', $auser->grade, array(-1=>get_string('nograde')), $attributes);
                                $grade = '<div id="g'.$auser->id.'">'.$menu.'</div>';
                                $hassubmission = true;
                            } else {
                                $grade = '<div id="g'.$auser->id.'">-</div>';
                            }

                            if ($final_grade->locked or $final_grade->overridden) {
                                $comment = '<div id="com'.$auser->id.'">'.$final_grade->str_feedback.'</div>';
                            } else if ($quickgrade) {
                                $comment = '<div id="com'.$auser->id.'">'
                                         . '<textarea tabindex="'.$tabindex++.'" name="submissioncomment['.$auser->id.']" id="submissioncomment'
                                         . $auser->id.'" rows="2" cols="20" spellcheck="true">'.($auser->submissioncomment).'</textarea></div>';
                            } else {
                                $comment = '<div id="com'.$auser->id.'">&nbsp;</div>';
                            }
                        }

                        if (empty($auser->status)) { /// Confirm we have exclusively 0 or 1
                            $auser->status = 0;
                        } else {
                            $auser->status = 1;
                        }

                        $buttontext = ($auser->status == 1) ? $strupdate : $strgrade;
                        if ($final_grade->locked or $final_grade->overridden) {
                            $buttontext = $strview;
                        }

                        ///No more buttons, we use popups ;-).
                        $popup_url = '/mod/assignment/submissions.php?id='.$this->cm->id
                                   . '&amp;userid='.$auser->id.'&amp;mode=single'.'&amp;filter='.$filter.'&amp;offset='.$offset++;

                        $button = $OUTPUT->action_link($popup_url, $buttontext);

                        $status  = '<div id="up'.$auser->id.'" class="s'.$auser->status.'">'.$button.'</div>';

                        $finalgrade = '<span id="finalgrade_'.$auser->id.'">'.$final_grade->str_grade.'</span>';

                        $outcomes = '';

                        if ($uses_outcomes) {

                            foreach($grading_info->outcomes as $n=>$outcome) {
                                $outcomes .= '<div class="outcome"><label for="'. 'outcome_'.$n.'_'.$auser->id .'">'.$outcome->name.'</label>';
                                $options = make_grades_menu(-$outcome->scaleid);

                                if ($outcome->grades[$auser->id]->locked or !$quickgrade) {
                                    $options[0] = get_string('nooutcome', 'grades');
                                    $outcomes .= ': <span id="outcome_'.$n.'_'.$auser->id.'">'.$options[$outcome->grades[$auser->id]->grade].'</span>';
                                } else {
                                    $attributes = array();
                                    $attributes['tabindex'] = $tabindex++;
                                    $attributes['id'] = 'outcome_'.$n.'_'.$auser->id;
                                    $outcomes .= ' '.html_writer::select($options, 'outcome_'.$n.'['.$auser->id.']', $outcome->grades[$auser->id]->grade, array(0=>get_string('nooutcome', 'grades')), $attributes);
                                }
                                $outcomes .= '</div>';
                            }
                        }

                        $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course=' . $course->id . '">' . fullname($auser, has_capability('moodle/site:viewfullnames', $this->context)) . '</a>';
                        $extradata = array();
                        foreach ($extrafields as $field) {
                            $extradata[] = $auser->{$field};
                        }
                        $row = array_merge(array($picture, $userlink), $extradata,
                                array($grade, $comment, $studentmodified, $teachermodified,
                                $status, $finalgrade));
                        if ($uses_outcomes) {
                            $row[] = $outcomes;
                        }
                        $table->add_data($row, $rowclass);
                    }
                    $currentposition++;
                }
                if ($hassubmission && method_exists($this, 'download_submissions')) {
                    echo html_writer::start_tag('div', array('class' => 'mod-assignment-download-link'));
                    echo html_writer::link(new moodle_url('/mod/assignment/submissions.php', array('id' => $this->cm->id, 'download' => 'zip')), get_string('downloadall', 'assignment'));
                    echo html_writer::end_tag('div');
                }
                $table->print_html();  /// Print the whole table
            } else {
                if ($filter == self::FILTER_SUBMITTED) {
                    echo html_writer::tag('div', get_string('nosubmisson', 'assignment'), array('class'=>'nosubmisson'));
                } else if ($filter == self::FILTER_REQUIRE_GRADING) {
                    echo html_writer::tag('div', get_string('norequiregrading', 'assignment'), array('class'=>'norequiregrading'));
                }
            }
        }

        /// Print quickgrade form around the table
        if ($quickgrade && $table->started_output && !empty($users)){
            $mailinfopref = false;
            if (get_user_preferences('assignment_mailinfo', 1)) {
                $mailinfopref = true;
            }
            $emailnotification =  html_writer::checkbox('mailinfo', 1, $mailinfopref, get_string('enablenotification','assignment'));

            $emailnotification .= $OUTPUT->help_icon('enablenotification', 'assignment');
            echo html_writer::tag('div', $emailnotification, array('class'=>'emailnotification'));

            $savefeedback = html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'fastg', 'value'=>get_string('saveallfeedback', 'assignment')));
            echo html_writer::tag('div', $savefeedback, array('class'=>'fastgbutton'));

            echo html_writer::end_tag('form');
        } else if ($quickgrade) {
            echo html_writer::end_tag('form');
        }

        echo '</div>';
        /// End of fast grading form

        /// Mini form for setting user preference

        $formaction = new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id));
        $mform = new MoodleQuickForm('optionspref', 'post', $formaction, '', array('class'=>'optionspref'));

        $mform->addElement('hidden', 'updatepref');
        $mform->setDefault('updatepref', 1);
        $mform->addElement('header', 'qgprefs', get_string('optionalsettings', 'assignment'));
        $mform->addElement('select', 'filter', get_string('show'),  $filters);

        $mform->setDefault('filter', $filter);

        $mform->addElement('text', 'perpage', get_string('pagesize', 'assignment'), array('size'=>1));
        $mform->setDefault('perpage', $perpage);

        if ($this->quickgrade_mode_allowed()) {
            $mform->addElement('checkbox', 'quickgrade', get_string('quickgrade','assignment'));
            $mform->setDefault('quickgrade', $quickgrade);
            $mform->addHelpButton('quickgrade', 'quickgrade', 'assignment');
        }

        $mform->addElement('submit', 'savepreferences', get_string('savepreferences'));

        $mform->display();

        echo $OUTPUT->footer();
    }

    /**
     * If the form was cancelled ('Cancel' or 'Next' was pressed), call cancel method
     * from advanced grading (if applicable) and returns true
     * If the form was submitted, validates it and returns false if validation did not pass.
     * If validation passes, preprocess advanced grading (if applicable) and returns true.
     *
     * Note to the developers: This is NOT the correct way to implement advanced grading
     * in grading form. The assignment grading was written long time ago and unfortunately
     * does not fully use the mforms. Usually function is_validated() is called to
     * validate the form and get_data() is called to get the data from the form.
     *
     * Here we have to push the calculated grade to $_POST['xgrade'] because further processing
     * of the form gets the data not from form->get_data(), but from $_POST (using statement
     * like  $feedback = data_submitted() )
     */
    protected function validate_and_preprocess_feedback() {
        global $USER, $CFG;
        require_once($CFG->libdir.'/gradelib.php');
        if (!($feedback = data_submitted()) || !isset($feedback->userid) || !isset($feedback->offset)) {
            return true;      // No incoming data, nothing to validate
        }
        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);
        $gradinginfo = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, array($userid));
        $gradingdisabled = $gradinginfo->items[0]->grades[$userid]->locked || $gradinginfo->items[0]->grades[$userid]->overridden;
        if ($gradingdisabled) {
            return true;
        }
        $submissiondata = $this->display_submission($offset, $userid, false);
        $mform = $submissiondata->mform;
        $gradinginstance = $mform->use_advanced_grading();
        if (optional_param('cancel', false, PARAM_BOOL) || optional_param('next', false, PARAM_BOOL)) {
            // form was cancelled
            if ($gradinginstance) {
                $gradinginstance->cancel();
            }
        } else if ($mform->is_submitted()) {
            // form was submitted (= a submit button other than 'cancel' or 'next' has been clicked)
            if (!$mform->is_validated()) {
                return false;
            }
            // preprocess advanced grading here
            if ($gradinginstance) {
                $data = $mform->get_data();
                // create submission if it did not exist yet because we need submission->id for storing the grading instance
                $submission = $this->get_submission($userid, true);
                $_POST['xgrade'] = $gradinginstance->submit_and_get_grade($data->advancedgrading, $submission->id);
            }
        }
        return true;
    }

    /**
     *  Process teacher feedback submission
     *
     * This is called by submissions() when a grading even has taken place.
     * It gets its data from the submitted form.
     *
     * @global object
     * @global object
     * @global object
     * @return object|bool The updated submission object or false
     */
    function process_feedback($formdata=null) {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }

        ///For save and next, we need to know the userid to save, and the userid to go
        ///We use a new hidden field in the form, and set it to -1. If it's set, we use this
        ///as the userid to store
        if ((int)$feedback->saveuserid !== -1){
            $feedback->userid = $feedback->saveuserid;
        }

        if (!empty($feedback->cancel)) {          // User hit cancel button
            return false;
        }

        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $feedback->userid);

        // store outcomes if needed
        $this->process_outcomes($feedback->userid);

        $submission = $this->get_submission($feedback->userid, true);  // Get or make one

        if (!($grading_info->items[0]->grades[$feedback->userid]->locked ||
            $grading_info->items[0]->grades[$feedback->userid]->overridden) ) {

            $submission->grade      = $feedback->xgrade;
            $submission->submissioncomment    = $feedback->submissioncomment_editor['text'];
            $submission->teacher    = $USER->id;
            $mailinfo = get_user_preferences('assignment_mailinfo', 0);
            if (!$mailinfo) {
                $submission->mailed = 1;       // treat as already mailed
            } else {
                $submission->mailed = 0;       // Make sure mail goes out (again, even)
            }
            $submission->timemarked = time();

            unset($submission->data1);  // Don't need to update this.
            unset($submission->data2);  // Don't need to update this.

            if (empty($submission->timemodified)) {   // eg for offline assignments
                // $submission->timemodified = time();
            }

            $DB->update_record('assignment_submissions', $submission);

            // triger grade event
            $this->update_grade($submission);

            add_to_log($this->course->id, 'assignment', 'update grades',
                       'submissions.php?id='.$this->cm->id.'&user='.$feedback->userid, $feedback->userid, $this->cm->id);
             if (!is_null($formdata)) {
                    if ($this->type == 'upload' || $this->type == 'uploadsingle') {
                        $mformdata = $formdata->mform->get_data();
                        $mformdata = file_postupdate_standard_filemanager($mformdata, 'files', $formdata->fileui_options, $this->context, 'mod_assignment', 'response', $submission->id);
                    }
             }
        }

        return $submission;

    }

    function process_outcomes($userid) {
        global $CFG, $USER;

        if (empty($CFG->enableoutcomes)) {
            return;
        }

        require_once($CFG->libdir.'/gradelib.php');

        if (!$formdata = data_submitted() or !confirm_sesskey()) {
            return;
        }

        $data = array();
        $grading_info = grade_get_grades($this->course->id, 'mod', 'assignment', $this->assignment->id, $userid);

        if (!empty($grading_info->outcomes)) {
            foreach($grading_info->outcomes as $n=>$old) {
                $name = 'outcome_'.$n;
                if (isset($formdata->{$name}[$userid]) and $old->grades[$userid]->grade != $formdata->{$name}[$userid]) {
                    $data[$n] = $formdata->{$name}[$userid];
                }
            }
        }
        if (count($data) > 0) {
            grade_update_outcomes('mod/assignment', $this->course->id, 'mod', 'assignment', $this->assignment->id, $userid, $data);
        }

    }

    /**
     * Load the submission object for a particular user
     *
     * @global object
     * @global object
     * @param int $userid int The id of the user whose submission we want or 0 in which case USER->id is used
     * @param bool $createnew boolean optional Defaults to false. If set to true a new submission object will be created in the database
     * @param bool $teachermodified student submission set if false
     * @return object|bool The submission or false (if $createnew is false and there is no existing submission).
     */
    function get_submission($userid=0, $createnew=false, $teachermodified=false) {
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $submission = $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'userid'=>$userid));

        if ($submission) {
            return $submission;
        } else if (!$createnew) {
            return false;
        }
        $newsubmission = $this->prepare_new_submission($userid, $teachermodified);
        $DB->insert_record("assignment_submissions", $newsubmission);

        return $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'userid'=>$userid));
    }

    /**
     * Check the given submission is complete. Preliminary rows are often created in the assignment_submissions
     * table before a submission actually takes place. This function checks to see if the given submission has actually
     * been submitted.
     *
     * @param  stdClass $submission The submission we want to check for completion
     * @return bool                 Indicates if the submission was found to be complete
     */
    public function is_submitted_with_required_data($submission) {
        return $submission->timemodified;
    }

    /**
     * Instantiates a new submission object for a given user
     *
     * Sets the assignment, userid and times, everything else is set to default values.
     *
     * @param int $userid The userid for which we want a submission object
     * @param bool $teachermodified student submission set if false
     * @return object The submission
     */
    function prepare_new_submission($userid, $teachermodified=false) {
        $submission = new stdClass();
        $submission->assignment   = $this->assignment->id;
        $submission->userid       = $userid;
        $submission->timecreated = time();
        // teachers should not be modifying modified date, except offline assignments
        if ($teachermodified) {
            $submission->timemodified = 0;
        } else {
            $submission->timemodified = $submission->timecreated;
        }
        $submission->numfiles     = 0;
        $submission->data1        = '';
        $submission->data2        = '';
        $submission->grade        = -1;
        $submission->submissioncomment      = '';
        $submission->format       = 0;
        $submission->teacher      = 0;
        $submission->timemarked   = 0;
        $submission->mailed       = 0;
        return $submission;
    }

    /**
     * Return all assignment submissions by ENROLLED students (even empty)
     *
     * @param string $sort optional field names for the ORDER BY in the sql query
     * @param string $dir optional specifying the sort direction, defaults to DESC
     * @return array The submission objects indexed by id
     */
    function get_submissions($sort='', $dir='DESC') {
        return assignment_get_all_submissions($this->assignment, $sort, $dir);
    }

    /**
     * Counts all complete (real) assignment submissions by enrolled students
     *
     * @param  int $groupid (optional) If nonzero then count is restricted to this group
     * @return int          The number of submissions
     */
    function count_real_submissions($groupid=0) {
        global $CFG;
        global $DB;

        // Grab the context assocated with our course module
        $context = context_module::instance($this->cm->id);

        // Get ids of users enrolled in the given course.
        list($enroledsql, $params) = get_enrolled_sql($context, 'mod/assignment:submit', $groupid);
        $params['assignmentid'] = $this->cm->instance;

        // Get ids of users enrolled in the given course.
        return $DB->count_records_sql("SELECT COUNT('x')
                                         FROM {assignment_submissions} s
                                    LEFT JOIN {assignment} a ON a.id = s.assignment
                                   INNER JOIN ($enroledsql) u ON u.id = s.userid
                                        WHERE s.assignment = :assignmentid AND
                                              s.timemodified > 0", $params);
    }

    /**
     * Alerts teachers by email of new or changed assignments that need grading
     *
     * First checks whether the option to email teachers is set for this assignment.
     * Sends an email to ALL teachers in the course (or in the group if using separate groups).
     * Uses the methods email_teachers_text() and email_teachers_html() to construct the content.
     *
     * @global object
     * @global object
     * @param $submission object The submission that has changed
     * @return void
     */
    function email_teachers($submission) {
        global $CFG, $DB;

        if (empty($this->assignment->emailteachers)) {          // No need to do anything
            return;
        }

        $user = $DB->get_record('user', array('id'=>$submission->userid));

        if ($teachers = $this->get_graders($user)) {

            $strassignments = get_string('modulenameplural', 'assignment');
            $strassignment  = get_string('modulename', 'assignment');
            $strsubmitted  = get_string('submitted', 'assignment');

            foreach ($teachers as $teacher) {
                $info = new stdClass();
                $info->username = fullname($user, true);
                $info->assignment = format_string($this->assignment->name,true);
                $info->url = $CFG->wwwroot.'/mod/assignment/submissions.php?id='.$this->cm->id;
                $info->timeupdated = userdate($submission->timemodified, '%c', $teacher->timezone);

                $postsubject = $strsubmitted.': '.$info->username.' -> '.$this->assignment->name;
                $posttext = $this->email_teachers_text($info);
                $posthtml = ($teacher->mailformat == 1) ? $this->email_teachers_html($info) : '';

                $eventdata = new stdClass();
                $eventdata->modulename       = 'assignment';
                $eventdata->userfrom         = $user;
                $eventdata->userto           = $teacher;
                $eventdata->subject          = $postsubject;
                $eventdata->fullmessage      = $posttext;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml  = $posthtml;
                $eventdata->smallmessage     = $postsubject;

                $eventdata->name            = 'assignment_updates';
                $eventdata->component       = 'mod_assignment';
                $eventdata->notification    = 1;
                $eventdata->contexturl      = $info->url;
                $eventdata->contexturlname  = $info->assignment;

                message_send($eventdata);
            }
        }
    }

    /**
     * Sends a file
     *
     * @param string $filearea
     * @param array $args
     * @param bool $forcedownload whether or not force download
     * @param array $options additional options affecting the file serving
     * @return bool
     */
    function send_file($filearea, $args, $forcedownload, array $options=array()) {
        debugging('plugin does not implement file sending', DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Returns a list of teachers that should be grading given submission
     *
     * @param object $user
     * @return array
     */
    function get_graders($user) {
        global $DB;

        //potential graders
        list($enrolledsql, $params) = get_enrolled_sql($this->context, 'mod/assignment:grade', 0, true);
        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN ($enrolledsql) je ON je.id = u.id";
        $potgraders = $DB->get_records_sql($sql, $params);

        $graders = array();
        if (groups_get_activity_groupmode($this->cm) == SEPARATEGROUPS) {   // Separate groups are being used
            if ($groups = groups_get_all_groups($this->course->id, $user->id)) {  // Try to find all groups
                foreach ($groups as $group) {
                    foreach ($potgraders as $t) {
                        if ($t->id == $user->id) {
                            continue; // do not send self
                        }
                        if (groups_is_member($group->id, $t->id)) {
                            $graders[$t->id] = $t;
                        }
                    }
                }
            } else {
                // user not in group, try to find graders without group
                foreach ($potgraders as $t) {
                    if ($t->id == $user->id) {
                        continue; // do not send self
                    }
                    if (!groups_get_all_groups($this->course->id, $t->id)) { //ugly hack
                        $graders[$t->id] = $t;
                    }
                }
            }
        } else {
            foreach ($potgraders as $t) {
                if ($t->id == $user->id) {
                    continue; // do not send self
                }
                $graders[$t->id] = $t;
            }
        }
        return $graders;
    }

    /**
     * Creates the text content for emails to teachers
     *
     * @param $info object The info used by the 'emailteachermail' language string
     * @return string
     */
    function email_teachers_text($info) {
        $posttext  = format_string($this->course->shortname, true, array('context' => $this->coursecontext)).' -> '.
                     $this->strassignments.' -> '.
                     format_string($this->assignment->name, true, array('context' => $this->context))."\n";
        $posttext .= '---------------------------------------------------------------------'."\n";
        $posttext .= get_string("emailteachermail", "assignment", $info)."\n";
        $posttext .= "\n---------------------------------------------------------------------\n";
        return $posttext;
    }

     /**
     * Creates the html content for emails to teachers
     *
     * @param $info object The info used by the 'emailteachermailhtml' language string
     * @return string
     */
    function email_teachers_html($info) {
        global $CFG;
        $posthtml  = '<p><font face="sans-serif">'.
                     '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$this->course->id.'">'.format_string($this->course->shortname, true, array('context' => $this->coursecontext)).'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/assignment/index.php?id='.$this->course->id.'">'.$this->strassignments.'</a> ->'.
                     '<a href="'.$CFG->wwwroot.'/mod/assignment/view.php?id='.$this->cm->id.'">'.format_string($this->assignment->name, true, array('context' => $this->context)).'</a></font></p>';
        $posthtml .= '<hr /><font face="sans-serif">';
        $posthtml .= '<p>'.get_string('emailteachermailhtml', 'assignment', $info).'</p>';
        $posthtml .= '</font><hr />';
        return $posthtml;
    }

    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER, $OUTPUT;

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = '';

        $submission = $this->get_submission($userid);
        if (!$submission) {
            return $output;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false);
        if (!empty($files)) {
            require_once($CFG->dirroot . '/mod/assignment/locallib.php');
            if ($CFG->enableportfolios) {
                require_once($CFG->libdir.'/portfoliolib.php');
                $button = new portfolio_add_button();
            }
            foreach ($files as $file) {
                $filename = $file->get_filename();
                $mimetype = $file->get_mimetype();
                $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submission/'.$submission->id.'/'.$filename);
                $output .= '<a href="'.$path.'" >'.$OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon')).s($filename).'</a>';
                if ($CFG->enableportfolios && $this->portfolio_exportable() && has_capability('mod/assignment:exportownsubmission', $this->context)) {
                    $button->set_callback_options('assignment_portfolio_caller',
                                                  array('id' => $this->cm->id, 'submissionid' => $submission->id, 'fileid' => $file->get_id()),
                                                  'mod_assignment');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }

                if ($CFG->enableplagiarism) {
                    require_once($CFG->libdir.'/plagiarismlib.php');
                    $output .= plagiarism_get_links(array('userid'=>$userid, 'file'=>$file, 'cmid'=>$this->cm->id, 'course'=>$this->course, 'assignment'=>$this->assignment));
                    $output .= '<br />';
                }
            }
            if ($CFG->enableportfolios && count($files) > 1  && $this->portfolio_exportable() && has_capability('mod/assignment:exportownsubmission', $this->context)) {
                $button->set_callback_options('assignment_portfolio_caller',
                                              array('id' => $this->cm->id, 'submissionid' => $submission->id),
                                              'mod_assignment');
                $output .= '<br />'  . $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
            }
        }

        $output = '<div class="files">'.$output.'</div>';

        if ($return) {
            return $output;
        }
        echo $output;
    }

    /**
     * Count the files uploaded by a given user
     *
     * @param $itemid int The submission's id as the file's itemid.
     * @return int
     */
    function count_user_files($itemid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $itemid, "id", false);
        return count($files);
    }

    /**
     * Returns true if the student is allowed to submit
     *
     * Checks that the assignment has started and, if the option to prevent late
     * submissions is set, also checks that the assignment has not yet closed.
     * @return boolean
     */
    function isopen() {
        $time = time();
        if ($this->assignment->preventlate && $this->assignment->timedue) {
            return ($this->assignment->timeavailable <= $time && $time <= $this->assignment->timedue);
        } else {
            return ($this->assignment->timeavailable <= $time);
        }
    }


    /**
     * Return true if is set description is hidden till available date
     *
     * This is needed by calendar so that hidden descriptions do not
     * come up in upcoming events.
     *
     * Check that description is hidden till available date
     * By default return false
     * Assignments types should implement this method if needed
     * @return boolen
     */
    function description_is_hidden() {
        return false;
    }

    /**
     * Return an outline of the user's interaction with the assignment
     *
     * The default method prints the grade and timemodified
     * @param $grade object
     * @return object with properties ->info and ->time
     */
    function user_outline($grade) {

        $result = new stdClass();
        $result->info = get_string('grade').': '.$grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }

    /**
     * Print complete information about the user's interaction with the assignment
     *
     * @param $user object
     */
    function user_complete($user, $grade=null) {
        global $OUTPUT;

        if ($submission = $this->get_submission($user->id)) {

            $fs = get_file_storage();

            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                $countfiles = count($files)." ".get_string("uploadedfiles", "assignment");
                foreach ($files as $file) {
                    $countfiles .= "; ".$file->get_filename();
                }
            }

            echo $OUTPUT->box_start();
            echo get_string("lastmodified").": ";
            echo userdate($submission->timemodified);
            echo $this->display_lateness($submission->timemodified);

            $this->print_user_files($user->id);

            echo '<br />';

            $this->view_feedback($submission);

            echo $OUTPUT->box_end();

        } else {
            if ($grade) {
                echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
                if ($grade->str_feedback) {
                    echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
                }
            }
            print_string("notsubmittedyet", "assignment");
        }
    }

    /**
     * Return a string indicating how late a submission is
     *
     * @param $timesubmitted int
     * @return string
     */
    function display_lateness($timesubmitted) {
        return assignment_display_lateness($timesubmitted, $this->assignment->timedue);
    }

    /**
     * Empty method stub for all delete actions.
     */
    function delete() {
        //nothing by default
        redirect('view.php?id='.$this->cm->id);
    }

    /**
     * Empty custom feedback grading form.
     */
    function custom_feedbackform($submission, $return=false) {
        //nothing by default
        return '';
    }

    /**
     * Add a get_coursemodule_info function in case any assignment type wants to add 'extra' information
     * for the course (see resource).
     *
     * Given a course_module object, this function returns any "extra" information that may be needed
     * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
     *
     * @param $coursemodule object The coursemodule object (record).
     * @return cached_cm_info Object used to customise appearance on course page
     */
    function get_coursemodule_info($coursemodule) {
        return null;
    }

    /**
     * Plugin cron method - do not use $this here, create new assignment instances if needed.
     * @return void
     */
    function cron() {
        //no plugin cron by default - override if needed
    }

    /**
     * Reset all submissions
     */
    function reset_userdata($data) {
        global $CFG, $DB;

        if (!$DB->count_records('assignment', array('course'=>$data->courseid, 'assignmenttype'=>$this->type))) {
            return array(); // no assignments of this type present
        }

        $componentstr = get_string('modulenameplural', 'assignment');
        $status = array();

        $typestr = get_string('type'.$this->type, 'assignment');
        // ugly hack to support pluggable assignment type titles...
        if($typestr === '[[type'.$this->type.']]'){
            $typestr = get_string('type'.$this->type, 'assignment_'.$this->type);
        }

        if (!empty($data->reset_assignment_submissions)) {
            $assignmentssql = "SELECT a.id
                                 FROM {assignment} a
                                WHERE a.course=? AND a.assignmenttype=?";
            $params = array($data->courseid, $this->type);

            // now get rid of all submissions and responses
            $fs = get_file_storage();
            if ($assignments = $DB->get_records_sql($assignmentssql, $params)) {
                foreach ($assignments as $assignmentid=>$unused) {
                    if (!$cm = get_coursemodule_from_instance('assignment', $assignmentid)) {
                        continue;
                    }
                    $context = context_module::instance($cm->id);
                    $fs->delete_area_files($context->id, 'mod_assignment', 'submission');
                    $fs->delete_area_files($context->id, 'mod_assignment', 'response');
                }
            }

            $DB->delete_records_select('assignment_submissions', "assignment IN ($assignmentssql)", $params);

            $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallsubmissions','assignment').': '.$typestr, 'error'=>false);

            if (empty($data->reset_gradebook_grades)) {
                // remove all grades from gradebook
                assignment_reset_gradebook($data->courseid, $this->type);
            }
        }

        return $status;
    }


    function portfolio_exportable() {
        return false;
    }

    /**
     * base implementation for backing up subtype specific information
     * for one single module
     *
     * @param filehandle $bf file handle for xml file to write to
     * @param mixed $preferences the complete backup preference object
     *
     * @return boolean
     *
     * @static
     */
    static function backup_one_mod($bf, $preferences, $assignment) {
        return true;
    }

    /**
     * base implementation for backing up subtype specific information
     * for one single submission
     *
     * @param filehandle $bf file handle for xml file to write to
     * @param mixed $preferences the complete backup preference object
     * @param object $submission the assignment submission db record
     *
     * @return boolean
     *
     * @static
     */
    static function backup_one_submission($bf, $preferences, $assignment, $submission) {
        return true;
    }

    /**
     * base implementation for restoring subtype specific information
     * for one single module
     *
     * @param array  $info the array representing the xml
     * @param object $restore the restore preferences
     *
     * @return boolean
     *
     * @static
     */
    static function restore_one_mod($info, $restore, $assignment) {
        return true;
    }

    /**
     * base implementation for restoring subtype specific information
     * for one single submission
     *
     * @param object $submission the newly created submission
     * @param array  $info the array representing the xml
     * @param object $restore the restore preferences
     *
     * @return boolean
     *
     * @static
     */
    static function restore_one_submission($info, $restore, $assignment, $submission) {
        return true;
    }

} ////// End of the assignment_base class

/**
 * Extend the base assignment class for assignments where you upload a single file
 *
 */
class assignment_upload extends assignment_base {

    function assignment_upload($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'upload';
    }

    function view() {
        global $USER, $OUTPUT;

        require_capability('mod/assignment:view', $this->context);
        $cansubmit = has_capability('mod/assignment:submit', $this->context);

        add_to_log($this->course->id, 'assignment', 'view', "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        if ($this->assignment->timeavailable > time()
          and !has_capability('mod/assignment:grade', $this->context)      // grading user can see it anytime
          and $this->assignment->var3) {                                   // force hiding before available date
            echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
            print_string('notavailableyet', 'assignment');
            echo $OUTPUT->box_end();
        } else {
            $this->view_intro();
        }

        $this->view_dates();

        if (is_enrolled($this->context, $USER)) {
            if ($submission = $this->get_submission($USER->id)) {
                if ($submission->timemarked) {
                    $this->view_feedback($submission);
                }

                $filecount = $this->count_user_files($submission->id);
            } else {
                $filecount = 0;
            }
            if ($cansubmit or !empty($filecount)) { //if a user has submitted files using a previous role we should still show the files

                if (!$this->drafts_tracked() or !$this->isopen() or $this->is_finalized($submission)) {
                    echo $OUTPUT->heading(get_string('submission', 'assignment'), 3);
                } else {
                    echo $OUTPUT->heading(get_string('submissiondraft', 'assignment'), 3);
                }

                if ($filecount and $submission) {
                    echo $OUTPUT->box($this->print_user_files($USER->id, true), 'generalbox boxaligncenter', 'userfiles');
                } else {
                    if (!$this->isopen() or $this->is_finalized($submission)) {
                        echo $OUTPUT->box(get_string('nofiles', 'assignment'), 'generalbox boxaligncenter nofiles', 'userfiles');
                    } else {
                        echo $OUTPUT->box(get_string('nofilesyet', 'assignment'), 'generalbox boxaligncenter nofiles', 'userfiles');
                    }
                }

                $this->view_upload_form();

                if ($this->notes_allowed()) {
                    echo $OUTPUT->heading(get_string('notes', 'assignment'), 3);
                    $this->view_notes();
                }

                $this->view_final_submission();
            }
        }
        $this->view_footer();
    }

    /**
     * Display the response file to the student
     *
     * This default method prints the response file
     *
     * @param object $submission The submission object
     */
    function view_responsefile($submission) {
        $fs = get_file_storage();
        $noresponsefiles = $fs->is_area_empty($this->context->id, 'mod_assignment', 'response', $submission->id);
        if (!$noresponsefiles) {
            echo '<tr>';
            echo '<td class="left side">&nbsp;</td>';
            echo '<td class="content">';
            echo $this->print_responsefiles($submission->userid);
            echo '</td></tr>';
        }
    }

    function view_upload_form() {
        global $CFG, $USER, $OUTPUT;

        $submission = $this->get_submission($USER->id);

        if ($this->is_finalized($submission)) {
            // no uploading
            return;
        }

        if ($this->can_upload_file($submission)) {
            $fs = get_file_storage();
            // edit files in another page
            if ($submission) {
                if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                    $str = get_string('editthesefiles', 'assignment');
                } else {
                    $str = get_string('uploadfiles', 'assignment');
                }
            } else {
                $str = get_string('uploadfiles', 'assignment');
            }
            echo $OUTPUT->single_button(new moodle_url('/mod/assignment/type/upload/upload.php', array('contextid'=>$this->context->id, 'userid'=>$USER->id)), $str, 'get');
        }
    }

    function view_notes() {
        global $USER, $OUTPUT;

        if ($submission = $this->get_submission($USER->id)
          and !empty($submission->data1)) {
            echo $OUTPUT->box(format_text($submission->data1, FORMAT_HTML, array('overflowdiv'=>true)), 'generalbox boxaligncenter boxwidthwide');
        } else {
            echo $OUTPUT->box(get_string('notesempty', 'assignment'), 'generalbox boxaligncenter');
        }
        if ($this->can_update_notes($submission)) {
            $options = array ('id'=>$this->cm->id, 'action'=>'editnotes');
            echo '<div style="text-align:center">';
            echo $OUTPUT->single_button(new moodle_url('upload.php', $options), get_string('edit'));
            echo '</div>';
        }
    }

    function view_final_submission() {
        global $CFG, $USER, $OUTPUT;

        $submission = $this->get_submission($USER->id);

        if ($this->isopen() and $this->can_finalize($submission)) {
            //print final submit button
            echo $OUTPUT->heading(get_string('submitformarking','assignment'), 3);
            echo '<div style="text-align:center">';
            echo '<form method="post" action="upload.php">';
            echo '<fieldset class="invisiblefieldset">';
            echo '<input type="hidden" name="id" value="'.$this->cm->id.'" />';
            echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
            echo '<input type="hidden" name="action" value="finalize" />';
            echo '<input type="submit" name="formarking" value="'.get_string('sendformarking', 'assignment').'" />';
            echo '</fieldset>';
            echo '</form>';
            echo '</div>';
        } else if (!$this->isopen()) {
            if ($this->assignment->timeavailable < time()) {
                echo $OUTPUT->heading(get_string('closedassignment','assignment'), 3);
            } else {
                echo $OUTPUT->heading(get_string('futureaassignment','assignment'), 3);
            }
        } else if ($this->drafts_tracked() and $state = $this->is_finalized($submission)) {
            if ($state == ASSIGNMENT_STATUS_SUBMITTED) {
                echo $OUTPUT->heading(get_string('submitedformarking','assignment'), 3);
            } else {
                echo $OUTPUT->heading(get_string('nomoresubmissions','assignment'), 3);
            }
        } else {
            //no submission yet
        }
    }


    /**
     * Return true if var3 == hide description till available day
     *
     *@return boolean
     */
    function description_is_hidden() {
        return ($this->assignment->var3 && (time() <= $this->assignment->timeavailable));
    }

    function print_student_answer($userid, $return=false){
        global $CFG, $OUTPUT, $PAGE;

        $submission = $this->get_submission($userid);

        $output = '';

        if ($this->drafts_tracked() and $this->isopen() and !$this->is_finalized($submission)) {
            $output .= '<strong>'.get_string('draft', 'assignment').':</strong> ';
        }

        if ($this->notes_allowed() and !empty($submission->data1)) {
            $link = new moodle_url("/mod/assignment/type/upload/notes.php", array('id'=>$this->cm->id, 'userid'=>$userid));
            $action = new popup_action('click', $link, 'notes', array('height' => 500, 'width' => 780));
            $output .= $OUTPUT->action_link($link, get_string('notes', 'assignment'), $action, array('title'=>get_string('notes', 'assignment')));

            $output .= '&nbsp;';
        }


        $renderer = $PAGE->get_renderer('mod_assignment');
        $output = $OUTPUT->box_start('files').$output;
        $output .= $renderer->assignment_files($this->context, $submission->id);
        $output .= $OUTPUT->box_end();

        return $output;
    }


    /**
     * Produces a list of links to the files uploaded by a user
     *
     * @param $userid int optional id of the user. If 0 then $USER->id is used.
     * @param $return boolean optional defaults to false. If true the list is returned rather than printed
     * @return string optional
     */
    function print_user_files($userid=0, $return=false) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $mode    = optional_param('mode', '', PARAM_ALPHA);
        $offset  = optional_param('offset', 0, PARAM_INT);

        if (!$userid) {
            if (!isloggedin()) {
                return '';
            }
            $userid = $USER->id;
        }

        $output = $OUTPUT->box_start('files');

        $submission = $this->get_submission($userid);

        // only during grading
        if ($this->drafts_tracked() and $this->isopen() and !$this->is_finalized($submission) and !empty($mode)) {
            $output .= '<strong>'.get_string('draft', 'assignment').':</strong><br />';
        }

        if ($this->notes_allowed() and !empty($submission->data1) and !empty($mode)) { // only during grading

            $npurl = $CFG->wwwroot."/mod/assignment/type/upload/notes.php?id={$this->cm->id}&amp;userid=$userid&amp;offset=$offset&amp;mode=single";
            $output .= '<a href="'.$npurl.'">'.get_string('notes', 'assignment').'</a><br />';

        }

        if ($this->drafts_tracked() and $this->isopen() and has_capability('mod/assignment:grade', $this->context) and $mode != '') { // we do not want it on view.php page
            if ($this->can_unfinalize($submission)) {
                //$options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'unfinalize', 'mode'=>$mode, 'offset'=>$offset);
                $output .= '<br /><input type="submit" name="unfinalize" value="'.get_string('unfinalize', 'assignment').'" />';
                $output .=  $OUTPUT->help_icon('unfinalize', 'assignment');

            } else if ($this->can_finalize($submission)) {
                //$options = array ('id'=>$this->cm->id, 'userid'=>$userid, 'action'=>'finalizeclose', 'mode'=>$mode, 'offset'=>$offset);
                $output .= '<br /><input type="submit" name="finalize" value="'.get_string('finalize', 'assignment').'" />';
            }
        }

        if ($submission) {
            $renderer = $PAGE->get_renderer('mod_assignment');
            $output .= $renderer->assignment_files($this->context, $submission->id);
        }
        $output .= $OUTPUT->box_end();

        if ($return) {
            return $output;
        }
        echo $output;
    }

    function submissions($mode) {
        // redirects out of form to process (un)finalizing.
        $unfinalize = optional_param('unfinalize', FALSE, PARAM_TEXT);
        $finalize = optional_param('finalize', FALSE, PARAM_TEXT);
        if ($unfinalize) {
            $this->unfinalize('single');
        } else if ($finalize) {
            $this->finalize('single');
        }
        if ($unfinalize || $finalize) {
            $mode = 'singlenosave';
        }
        parent::submissions($mode);
    }

    function process_feedback($formdata=null) {
        if (!$feedback = data_submitted() or !confirm_sesskey()) {      // No incoming data?
            return false;
        }
        $userid = required_param('userid', PARAM_INT);
        $offset = required_param('offset', PARAM_INT);
        $mform = $this->display_submission($offset, $userid, false);
        parent::process_feedback($mform);
    }

    /**
     * Counts all complete (real) assignment submissions by enrolled students. This overrides assignment_base::count_real_submissions().
     * This is necessary for tracked advanced file uploads where we need to check that the data2 field is equal to ASSIGNMENT_STATUS_SUBMITTED
     * to determine if a submission is complete.
     *
     * @param  int $groupid (optional) If nonzero then count is restricted to this group
     * @return int          The number of submissions
     */
    function count_real_submissions($groupid=0) {
        global $DB;

        // Grab the context assocated with our course module
        $context = context_module::instance($this->cm->id);

        // Get ids of users enrolled in the given course.
        list($enroledsql, $params) = get_enrolled_sql($context, 'mod/assignment:submit', $groupid);
        $params['assignmentid'] = $this->cm->instance;

        $query = '';
        if ($this->drafts_tracked() and $this->isopen()) {
            $query = ' AND ' . $DB->sql_compare_text('s.data2') . " = '"  . ASSIGNMENT_STATUS_SUBMITTED . "'";
        } else {
            // Count on submissions with files actually uploaded
            $query = " AND s.numfiles > 0";
        }
        return $DB->count_records_sql("SELECT COUNT('x')
                                         FROM {assignment_submissions} s
                                    LEFT JOIN {assignment} a ON a.id = s.assignment
                                   INNER JOIN ($enroledsql) u ON u.id = s.userid
                                        WHERE s.assignment = :assignmentid" .
                                              $query, $params);
    }

    function print_responsefiles($userid, $return=false) {
        global $OUTPUT, $PAGE;

        $output = $OUTPUT->box_start('responsefiles');

        if ($submission = $this->get_submission($userid)) {
            $renderer = $PAGE->get_renderer('mod_assignment');
            $output .= $renderer->assignment_files($this->context, $submission->id, 'response');
        }
        $output .= $OUTPUT->box_end();

        if ($return) {
            return $output;
        }
        echo $output;
    }

    /**
     * Upload files
     * upload_file function requires moodle form instance and file manager options
     * @param object $mform
     * @param array $options
     */
    function upload($mform = null, $filemanager_options = null) {
        $action = required_param('action', PARAM_ALPHA);
        switch ($action) {
            case 'finalize':
                $this->finalize();
                break;
            case 'finalizeclose':
                $this->finalizeclose();
                break;
            case 'unfinalize':
                $this->unfinalize();
                break;
            case 'uploadresponse':
                $this->upload_responsefile($mform, $filemanager_options);
                break;
            case 'uploadfile':
                $this->upload_file($mform, $filemanager_options);
            case 'savenotes':
            case 'editnotes':
                $this->upload_notes();
            default:
                print_error('unknowuploadaction', '', '', $action);
        }
    }

    function upload_notes() {
        global $CFG, $USER, $OUTPUT, $DB;

        $action = required_param('action', PARAM_ALPHA);

        $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));

        $mform = new mod_assignment_upload_notes_form();

        $defaults = new stdClass();
        $defaults->id = $this->cm->id;

        if ($submission = $this->get_submission($USER->id)) {
            $defaults->text = clean_text($submission->data1);
        } else {
            $defaults->text = '';
        }

        $mform->set_data($defaults);

        if ($mform->is_cancelled()) {
            $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
            redirect($returnurl);
        }

        if (!$this->can_update_notes($submission)) {
            $this->view_header(get_string('upload'));
            echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if ($data = $mform->get_data() and $action == 'savenotes') {
            $submission = $this->get_submission($USER->id, true); // get or create submission
            $updated = new stdClass();
            $updated->id           = $submission->id;
            $updated->timemodified = time();
            $updated->data1        = $data->text;

            $DB->update_record('assignment_submissions', $updated);
            add_to_log($this->course->id, 'assignment', 'upload', 'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
            redirect($returnurl);
            $submission = $this->get_submission($USER->id);
            $this->update_grade($submission);
        }

        /// show notes edit form
        $this->view_header(get_string('notes', 'assignment'));

        echo $OUTPUT->heading(get_string('notes', 'assignment'));

        $mform->display();

        $this->view_footer();
        die;
    }

    function upload_responsefile($mform, $options) {
        global $CFG, $USER, $OUTPUT, $PAGE;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        $returnurl = new moodle_url("submissions.php", array('id'=>$this->cm->id,'userid'=>$userid,'mode'=>$mode,'offset'=>$offset)); //not xhtml, just url.

        if ($formdata = $mform->get_data() and $this->can_manage_responsefiles()) {
            $fs = get_file_storage();
            $submission = $this->get_submission($userid, true, true);
            if ($formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $this->context, 'mod_assignment', 'response', $submission->id)) {
                $returnurl = new moodle_url("/mod/assignment/submissions.php", array('id'=>$this->cm->id,'userid'=>$formdata->userid,'mode'=>$formdata->mode,'offset'=>$formdata->offset));
                redirect($returnurl->out(false));
            }
        }
        $PAGE->set_title(get_string('upload'));
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
        echo $OUTPUT->continue_button($returnurl->out(true));
        echo $OUTPUT->footer();
        die;
    }

    function upload_file($mform, $options) {
        global $CFG, $USER, $DB, $OUTPUT;

        $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
        $submission = $this->get_submission($USER->id);

        if (!$this->can_upload_file($submission)) {
            $this->view_header(get_string('upload'));
            echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if ($formdata = $mform->get_data()) {
            $fs = get_file_storage();
            $submission = $this->get_submission($USER->id, true); //create new submission if needed
            $fs->delete_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id);
            $formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $this->context, 'mod_assignment', 'submission', $submission->id);
            $updates = new stdClass();
            $updates->id = $submission->id;
            $updates->numfiles = count($fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, 'sortorder', false));
            $updates->timemodified = time();
            $DB->update_record('assignment_submissions', $updates);
            $this->update_grade($submission);
            if (!$this->drafts_tracked()) {
                $this->email_teachers($submission);
            }

            // send files to event system
            $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id);

            // Let Moodle know that assessable files were  uploaded (eg for plagiarism detection)
            $params = array(
                'context' => $this->context,
                'objectid' => $submission->id,
                'other' => array(
                    'assignmentid' => $this->assignment->id,
                    'content' => '',
                    'pathnamehashes' => array_keys($files)
                )
            );
            $event = \assignment_upload\event\assessable_uploaded::create($params);
            $event->set_legacy_files($files);
            $event->trigger();

            $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
            redirect($returnurl);
        }

        $this->view_header(get_string('upload'));
        echo $OUTPUT->notification(get_string('uploaderror', 'assignment'));
        echo $OUTPUT->continue_button($returnurl);
        $this->view_footer();
        die;
    }

    function send_file($filearea, $args, $forcedownload, array $options=array()) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir.'/filelib.php');

        require_login($this->course, false, $this->cm);

        if ($filearea === 'submission') {
            $submissionid = (int)array_shift($args);

            if (!$submission = $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'id'=>$submissionid))) {
                return false;
            }

            if ($USER->id != $submission->userid and !has_capability('mod/assignment:grade', $this->context)) {
                return false;
            }

            $relativepath = implode('/', $args);
            $fullpath = "/{$this->context->id}/mod_assignment/submission/$submission->id/$relativepath";

            $fs = get_file_storage();
            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                return false;
            }

            send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!

        } else if ($filearea === 'response') {
            $submissionid = (int)array_shift($args);

            if (!$submission = $DB->get_record('assignment_submissions', array('assignment'=>$this->assignment->id, 'id'=>$submissionid))) {
                return false;
            }

            if ($USER->id != $submission->userid and !has_capability('mod/assignment:grade', $this->context)) {
                return false;
            }

            $relativepath = implode('/', $args);
            $fullpath = "/{$this->context->id}/mod_assignment/response/$submission->id/$relativepath";

            $fs = get_file_storage();
            if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
                return false;
            }
            send_stored_file($file, 0, 0, true, $options);
        }

        return false;
    }

    function finalize($forcemode=null) {
        global $USER, $DB, $OUTPUT;
        $userid = optional_param('userid', $USER->id, PARAM_INT);
        $offset = optional_param('offset', 0, PARAM_INT);
        $confirm    = optional_param('confirm', 0, PARAM_BOOL);
        $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
        $submission = $this->get_submission($userid);

        if ($forcemode!=null) {
            $returnurl  = new moodle_url('/mod/assignment/submissions.php',
                array('id'=>$this->cm->id,
                    'userid'=>$userid,
                    'mode'=>$forcemode,
                    'offset'=>$offset
                ));
        }

        if (!$this->can_finalize($submission)) {
            redirect($returnurl->out(false)); // probably already graded, redirect to assignment page, the reason should be obvious
        }

        if ($forcemode==null) {
            if (!data_submitted() or !$confirm or !confirm_sesskey()) {
                $optionsno = array('id'=>$this->cm->id);
                $optionsyes = array ('id'=>$this->cm->id, 'confirm'=>1, 'action'=>'finalize', 'sesskey'=>sesskey());
                $this->view_header(get_string('submitformarking', 'assignment'));
                echo $OUTPUT->heading(get_string('submitformarking', 'assignment'));
                echo $OUTPUT->confirm(get_string('onceassignmentsent', 'assignment'), new moodle_url('upload.php', $optionsyes),new moodle_url( 'view.php', $optionsno));
                $this->view_footer();
                die;
            }
        }
        $updated = new stdClass();
        $updated->id           = $submission->id;
        $updated->data2        = ASSIGNMENT_STATUS_SUBMITTED;
        $updated->timemodified = time();

        $DB->update_record('assignment_submissions', $updated);
        add_to_log($this->course->id, 'assignment', 'upload', //TODO: add finalize action to log
                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
        $submission = $this->get_submission($userid);
        $this->update_grade($submission);
        $this->email_teachers($submission);

        // Trigger assessable_submitted event to show files are complete.
        $params = array(
            'context' => $this->context,
            'objectid' => $submission->id,
            'other' => array(
                'assignmentid' => $this->assignment->id,
                'submission_editable' => false
            )
        );
        $event = \assignment_upload\event\assessable_submitted::create($params);
        $event->trigger();

        if ($forcemode==null) {
            redirect($returnurl->out(false));
        }
    }

    function finalizeclose() {
        global $DB;

        $userid    = optional_param('userid', 0, PARAM_INT);
        $mode      = required_param('mode', PARAM_ALPHA);
        $offset    = required_param('offset', PARAM_INT);
        $returnurl  = new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset, 'forcerefresh'=>1));

        // create but do not add student submission date
        $submission = $this->get_submission($userid, true, true);

        if (!data_submitted() or !$this->can_finalize($submission) or !confirm_sesskey()) {
            redirect($returnurl); // probably closed already
        }

        $updated = new stdClass();
        $updated->id    = $submission->id;
        $updated->data2 = ASSIGNMENT_STATUS_CLOSED;

        $DB->update_record('assignment_submissions', $updated);
        add_to_log($this->course->id, 'assignment', 'upload', //TODO: add finalize action to log
                'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
        $submission = $this->get_submission($userid, false, true);
        $this->update_grade($submission);
        redirect($returnurl);
    }

    function unfinalize($forcemode=null) {
        global $DB;

        $userid = required_param('userid', PARAM_INT);
        $mode   = required_param('mode', PARAM_ALPHA);
        $offset = required_param('offset', PARAM_INT);

        if ($forcemode!=null) {
            $mode=$forcemode;
        }
        $returnurl = new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset, 'forcerefresh'=>1) );
        if (data_submitted()
          and $submission = $this->get_submission($userid)
          and $this->can_unfinalize($submission)
          and confirm_sesskey()) {

            $updated = new stdClass();
            $updated->id = $submission->id;
            $updated->data2 = '';
            $DB->update_record('assignment_submissions', $updated);
            //TODO: add unfinalize action to log
            add_to_log($this->course->id, 'assignment', 'view submission', 'submissions.php?id='.$this->cm->id.'&userid='.$userid.'&mode='.$mode.'&offset='.$offset, $this->assignment->id, $this->cm->id);
            $submission = $this->get_submission($userid);
            $this->update_grade($submission);
        }

        if ($forcemode==null) {
            redirect($returnurl);
        }
    }


    function delete() {
        $action   = optional_param('action', '', PARAM_ALPHA);

        switch ($action) {
            case 'response':
                $this->delete_responsefile();
                break;
            default:
                $this->delete_file();
        }
        die;
    }


    function delete_responsefile() {
        global $CFG, $OUTPUT,$PAGE;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $mode     = required_param('mode', PARAM_ALPHA);
        $offset   = required_param('offset', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);

        $returnurl  = new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id, 'userid'=>$userid, 'mode'=>$mode, 'offset'=>$offset));

        if (!$this->can_manage_responsefiles()) {
           redirect($returnurl);
        }

        $urlreturn = 'submissions.php';
        $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);

        if (!data_submitted() or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'action'=>'response', 'mode'=>$mode, 'offset'=>$offset, 'sesskey'=>sesskey());
            $PAGE->set_title(get_string('delete'));
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('delete'));
            echo $OUTPUT->confirm(get_string('confirmdeletefile', 'assignment', $file), new moodle_url('delete.php', $optionsyes), new moodle_url($urlreturn, $optionsreturn));
            echo $OUTPUT->footer();
            die;
        }

        if ($submission = $this->get_submission($userid)) {
            $fs = get_file_storage();
            if ($file = $fs->get_file($this->context->id, 'mod_assignment', 'response', $submission->id, '/', $file)) {
                $file->delete();
            }
        }
        redirect($returnurl);
    }


    function delete_file() {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $file     = required_param('file', PARAM_FILE);
        $userid   = required_param('userid', PARAM_INT);
        $confirm  = optional_param('confirm', 0, PARAM_BOOL);
        $mode     = optional_param('mode', '', PARAM_ALPHA);
        $offset   = optional_param('offset', 0, PARAM_INT);

        require_login($this->course, false, $this->cm);

        if (empty($mode)) {
            $urlreturn = 'view.php';
            $optionsreturn = array('id'=>$this->cm->id);
            $returnurl  = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
        } else {
            $urlreturn = 'submissions.php';
            $optionsreturn = array('id'=>$this->cm->id, 'offset'=>$offset, 'mode'=>$mode, 'userid'=>$userid);
            $returnurl  = new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id, 'offset'=>$offset, 'userid'=>$userid));
        }

        if (!$submission = $this->get_submission($userid) // incorrect submission
          or !$this->can_delete_files($submission)) {     // can not delete
            $this->view_header(get_string('delete'));
            echo $OUTPUT->notification(get_string('cannotdeletefiles', 'assignment'));
            echo $OUTPUT->continue_button($returnurl);
            $this->view_footer();
            die;
        }

        if (!data_submitted() or !$confirm or !confirm_sesskey()) {
            $optionsyes = array ('id'=>$this->cm->id, 'file'=>$file, 'userid'=>$userid, 'confirm'=>1, 'sesskey'=>sesskey(), 'mode'=>$mode, 'offset'=>$offset, 'sesskey'=>sesskey());
            if (empty($mode)) {
                $this->view_header(get_string('delete'));
            } else {
                $PAGE->set_title(get_string('delete'));
                echo $OUTPUT->header();
            }
            echo $OUTPUT->heading(get_string('delete'));
            echo $OUTPUT->confirm(get_string('confirmdeletefile', 'assignment', $file), new moodle_url('delete.php', $optionsyes), new moodle_url($urlreturn, $optionsreturn));
            if (empty($mode)) {
                $this->view_footer();
            } else {
                echo $OUTPUT->footer();
            }
            die;
        }

        $fs = get_file_storage();
        if ($file = $fs->get_file($this->context->id, 'mod_assignment', 'submission', $submission->id, '/', $file)) {
            $file->delete();
            $submission->timemodified = time();
            $DB->update_record('assignment_submissions', $submission);
            add_to_log($this->course->id, 'assignment', 'upload', //TODO: add delete action to log
                    'view.php?a='.$this->assignment->id, $this->assignment->id, $this->cm->id);
            $this->update_grade($submission);
        }
        redirect($returnurl);
    }


    function can_upload_file($submission) {
        global $USER;

        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')
          and $this->isopen()                                                 // assignment not closed yet
          and (empty($submission) or ($submission->userid == $USER->id))        // his/her own submission
          and !$this->is_finalized($submission)) {                            // no uploading after final submission
            return true;
        } else {
            return false;
        }
    }

    function can_manage_responsefiles() {
        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        } else {
            return false;
        }
    }

    function can_delete_files($submission) {
        global $USER;

        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;
        }

        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')
          and $this->isopen()                                      // assignment not closed yet
          and $this->assignment->resubmit                          // deleting allowed
          and $USER->id == $submission->userid                     // his/her own submission
          and !$this->is_finalized($submission)) {                 // no deleting after final submission
            return true;
        } else {
            return false;
        }
    }

    function drafts_tracked() {
        return !empty($this->assignment->var4);
    }

    /**
     * Returns submission status
     * @param object $submission - may be empty
     * @return string submission state - empty, ASSIGNMENT_STATUS_SUBMITTED or ASSIGNMENT_STATUS_CLOSED
     */
    function is_finalized($submission) {
        if (!$this->drafts_tracked()) {
            return '';

        } else if (empty($submission)) {
            return '';

        } else if ($submission->data2 == ASSIGNMENT_STATUS_SUBMITTED or $submission->data2 == ASSIGNMENT_STATUS_CLOSED) {
            return $submission->data2;

        } else {
            return '';
        }
    }

    function can_unfinalize($submission) {
        if(is_bool($submission)) {
            return false;
        }

        if (!$this->drafts_tracked()) {
            return false;
        }

        if (has_capability('mod/assignment:grade', $this->context)
          and $this->isopen()
          and $this->is_finalized($submission)) {
            return true;
        } else {
            return false;
        }
    }

    function can_finalize($submission) {
        global $USER;

        if(is_bool($submission)) {
            return false;
        }

        if (!$this->drafts_tracked()) {
            return false;
        }

        if ($this->is_finalized($submission)) {
            return false;
        }

        if (has_capability('mod/assignment:grade', $this->context)) {
            return true;

        } else if (is_enrolled($this->context, $USER, 'mod/assignment:submit')
          and $this->isopen()                                                 // assignment not closed yet
          and !empty($submission)                                             // submission must exist
          and $submission->userid == $USER->id                                // his/her own submission
          and ($this->count_user_files($submission->id)
            or ($this->notes_allowed() and !empty($submission->data1)))) {    // something must be submitted

            return true;
        } else {
            return false;
        }
    }

    function can_update_notes($submission) {
        global $USER;

        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')
          and $this->notes_allowed()                                          // notesd must be allowed
          and $this->isopen()                                                 // assignment not closed yet
          and (empty($submission) or $USER->id == $submission->userid)        // his/her own submission
          and !$this->is_finalized($submission)) {                            // no updateingafter final submission
            return true;
        } else {
            return false;
        }
    }

    function notes_allowed() {
        return (boolean)$this->assignment->var2;
    }

    function count_responsefiles($userid) {
        if ($submission = $this->get_submission($userid)) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'response', $submission->id, "id", false);
            return count($files);
        } else {
            return 0;
        }
    }

    function setup_elements(&$mform) {
        global $CFG, $COURSE;

        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maximumsize', 'assignment'), $choices);
        $mform->setDefault('maxbytes', $CFG->assignment_maxbytes);

        $mform->addElement('select', 'resubmit', get_string('allowdeleting', 'assignment'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowdeleting', 'assignment');
        $mform->setDefault('resubmit', 1);

        $options = array();
        for($i = 1; $i <= 20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'var1', get_string('allowmaxfiles', 'assignment'), $options);
        $mform->addHelpButton('var1', 'allowmaxfiles', 'assignment');
        $mform->setDefault('var1', 3);

        $mform->addElement('select', 'var2', get_string('allownotes', 'assignment'), $ynoptions);
        $mform->addHelpButton('var2', 'allownotes', 'assignment');
        $mform->setDefault('var2', 0);

        $mform->addElement('select', 'var3', get_string('hideintro', 'assignment'), $ynoptions);
        $mform->addHelpButton('var3', 'hideintro', 'assignment');
        $mform->setDefault('var3', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'assignment'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'assignment');
        $mform->setDefault('emailteachers', 0);

        $mform->addElement('select', 'var4', get_string('trackdrafts', 'assignment'), $ynoptions);
        $mform->addHelpButton('var4', 'trackdrafts', 'assignment');
        $mform->setDefault('var4', 1);

        $course_context = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $course_context, 'mod_assignment');
    }

    function portfolio_exportable() {
        return true;
    }

    function extend_settings_navigation($node) {
        global $CFG, $USER, $OUTPUT;

        // get users submission if there is one
        $submission = $this->get_submission();
        if (is_enrolled($this->context, $USER, 'mod/assignment:submit')) {
            $editable = $this->isopen() && (!$submission || $this->assignment->resubmit || !$submission->timemarked);
        } else {
            $editable = false;
        }

        // If the user has submitted something add some related links and data
        if (isset($submission->data2) AND $submission->data2 == 'submitted') {
            // Add a view link to the settings nav
            $link = new moodle_url('/mod/assignment/view.php', array('id'=>$this->cm->id));
            $node->add(get_string('viewmysubmission', 'assignment'), $link, navigation_node::TYPE_SETTING);
            if (!empty($submission->timemodified)) {
                $submittednode = $node->add(get_string('submitted', 'assignment') . ' ' . userdate($submission->timemodified));
                $submittednode->text = preg_replace('#([^,])\s#', '$1&nbsp;', $submittednode->text);
                $submittednode->add_class('note');
                if ($submission->timemodified <= $this->assignment->timedue || empty($this->assignment->timedue)) {
                    $submittednode->add_class('early');
                } else {
                    $submittednode->add_class('late');
                }
            }
        }

        // Check if the user has uploaded any files, if so we can add some more stuff to the settings nav
        if ($submission && is_enrolled($this->context, $USER, 'mod/assignment:submit') && $this->count_user_files($submission->id)) {
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false)) {
                if (!$this->drafts_tracked() or !$this->isopen() or $this->is_finalized($submission)) {
                    $filenode = $node->add(get_string('submission', 'assignment'));
                } else {
                    $filenode = $node->add(get_string('submissiondraft', 'assignment'));
                }
                foreach ($files as $file) {
                    $filename = $file->get_filename();
                    $mimetype = $file->get_mimetype();
                    $link = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$this->context->id.'/mod_assignment/submission/'.$submission->id.'/'.$filename);
                    $filenode->add($filename, $link, navigation_node::TYPE_SETTING, null, null, new pix_icon(file_file_icon($file),''));
                }
            }
        }

        // Show a notes link if they are enabled
        if ($this->notes_allowed()) {
            $link = new moodle_url('/mod/assignment/upload.php', array('id'=>$this->cm->id, 'action'=>'editnotes', 'sesskey'=>sesskey()));
            $node->add(get_string('notes', 'assignment'), $link);
        }
    }

    /**
     * creates a zip of all assignment submissions and sends a zip to the browser
     */
    public function download_submissions() {
        global $CFG,$DB;
        require_once($CFG->libdir.'/filelib.php');
        $submissions = $this->get_submissions('','');
        if (empty($submissions)) {
            print_error('errornosubmissions', 'assignment', new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id)));
        }
        $filesforzipping = array();
        $fs = get_file_storage();

        $groupmode = groups_get_activity_groupmode($this->cm);
        $groupid = 0;   // All users
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($this->cm, true);
            $groupname = groups_get_group_name($groupid).'-';
        }
        $filename = str_replace(' ', '_', clean_filename($this->course->shortname.'-'.$this->assignment->name.'-'.$groupname.$this->assignment->id.".zip")); //name of new zip file.
        foreach ($submissions as $submission) {
            // If assignment is open and submission is not finalized and marking button enabled then don't add it to zip.
            $submissionstatus = $this->is_finalized($submission);
            if ($this->isopen() && empty($submissionstatus) && !empty($this->assignment->var4)) {
                continue;
            }
            $a_userid = $submission->userid; //get userid
            if ((groups_is_member($groupid,$a_userid)or !$groupmode or !$groupid)) {
                $a_assignid = $submission->assignment; //get name of this assignment for use in the file names.
                $a_user = $DB->get_record("user", array("id"=>$a_userid),'id,username,firstname,lastname'); //get user firstname/lastname

                $files = $fs->get_area_files($this->context->id, 'mod_assignment', 'submission', $submission->id, "timemodified", false);
                foreach ($files as $file) {
                    //get files new name.
                    $fileext = strstr($file->get_filename(), '.');
                    $fileoriginal = str_replace($fileext, '', $file->get_filename());
                    $fileforzipname =  clean_filename(fullname($a_user) . "_" . $fileoriginal."_".$a_userid.$fileext);
                    //save file name to array for zipping.
                    $filesforzipping[$fileforzipname] = $file;
                }
            }
        } // end of foreach loop

        // Throw error if no files are added.
        if (empty($filesforzipping)) {
            print_error('errornosubmissions', 'assignment', new moodle_url('/mod/assignment/submissions.php', array('id'=>$this->cm->id)));
        }

        if ($zipfile = assignment_pack_files($filesforzipping)) {
            send_temp_file($zipfile, $filename); //send file and delete after sending.
        }
    }

    /**
     * Check the given submission is complete. Preliminary rows are often created in the assignment_submissions
     * table before a submission actually takes place. This function checks to see if the given submission has actually
     * been submitted.
     *
     * @param  stdClass $submission The submission we want to check for completion
     * @return bool                 Indicates if the submission was found to be complete
     */
    public function is_submitted_with_required_data($submission) {
        if ($this->drafts_tracked()) {
            $submitted = $submission->timemodified > 0 &&
                         $submission->data2 == ASSIGNMENT_STATUS_SUBMITTED;
        } else {
            $submitted = $submission->numfiles > 0;
        }
        return $submitted;
    }
}

class mod_assignment_upload_notes_form extends moodleform {

    function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->format = $data->text['format'];
            $data->text = $data->text['text'];
        }
        return $data;
    }

    function set_data($data) {
        if (!isset($data->format)) {
            $data->format = FORMAT_HTML;
        }
        if (isset($data->text)) {
            $data->text = array('text'=>$data->text, 'format'=>$data->format);
        }
        parent::set_data($data);
    }

    function definition() {
        $mform = $this->_form;

        // visible elements
        $mform->addElement('editor', 'text', get_string('notes', 'assignment'), null, null);
        $mform->setType('text', PARAM_RAW); // to be cleaned before display

        // hidden params
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'savenotes');
        $mform->setType('action', PARAM_ALPHA);

        // buttons
        $this->add_action_buttons();
    }
}

class mod_assignment_upload_response_form extends moodleform {
    function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;

        // visible elements
        $mform->addElement('filemanager', 'files_filemanager', get_string('uploadafile'), null, $instance->options);

        // hidden params
        $mform->addElement('hidden', 'id', $instance->cm->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $instance->contextid);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'uploadresponse');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'mode', $instance->mode);
        $mform->setType('mode', PARAM_ALPHA);
        $mform->addElement('hidden', 'offset', $instance->offset);
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('hidden', 'forcerefresh' , $instance->forcerefresh);
        $mform->setType('forcerefresh', PARAM_INT);
        $mform->addElement('hidden', 'userid', $instance->userid);
        $mform->setType('userid', PARAM_INT);

        // buttons
        $this->add_action_buttons(false, get_string('uploadthisfile'));
    }
}




