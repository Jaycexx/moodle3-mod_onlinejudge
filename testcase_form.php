<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
//                      Online Judge for Moodle                          //
//        https://github.com/hit-moodle/moodle-local_onlinejudge         //
//                                                                       //
// Copyright (C) 2009 onwards  Sun Zhigang  http://sunner.cn             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Testcase management form
 *
 * @package   local_onlinejudge
 * @copyright 2011 Sun Zhigang (http://sunner.cn)
 * @author    Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->libdir/formslib.php");
//require_once("$CFG->dirroot/mod/onlinejudge/assignment.class.php");

class testcase_form extends moodleform {
    var $testcasecount;

    function testcase_form($testcasecount) {
        $this->testcasecount = $testcasecount;
        parent::moodleform();
    }

    function definition() {
        global $CFG, $COURSE,$cm,$id;

        $mform =& $this->_form; // Don't forget the underscore!

        $repeatarray = array();
        $repeatarray[] = &$mform->createElement('header', 'testcases', get_string('testcases', 'onlinejudge').'{no}');

        // require_once($CFG->dirroot.'/lib/questionlib.php'); //for get_grade_options()
        // $choices = get_grade_options()->gradeoptions; // Steal from question lib

        $choices = ['10%', '20%', '30%', '40%', '50%', '60%', '70%', '80%', '90%', '100%'];
        $repeatarray[] = &$mform->createElement('select', 'subgrade', get_string('subgrade', 'onlinejudge'), $choices);

        // $repeatarray[] = &$mform->createElement('checkbox', 'usefile', get_string('usefile', 'onlinejudge'));
        $repeatarray[] = &$mform->createElement('textarea', 'input', get_string('input', 'onlinejudge'), 'wrap="virtual" rows="5" cols="50"');
        $repeatarray[] = &$mform->createElement('textarea', 'output', get_string('output', 'onlinejudge'), 'wrap="virtual" rows="5" cols="50"');
        // $repeatarray[] = &$mform->createElement('filemanager', 'inputfile', get_string('inputfile', 'onlinejudge'), null, array('subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => array('plaintext')));
        // $repeatarray[] = &$mform->createElement('filemanager', 'outputfile', get_string('outputfile', 'onlinejudge'), null, array('subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => array('plaintext')));
        $repeatarray[] = &$mform->createElement('text', 'feedback', get_string('feedback', 'onlinejudge'), array('size' => 50));
        $repeatarray[] = &$mform->createElement('hidden', 'caseid', -1);

        $repeateloptions = array();
        $repeateloptions['input']['type'] = PARAM_RAW;
        $repeateloptions['output']['type'] = PARAM_RAW;
        $repeateloptions['feedback']['type'] = PARAM_RAW;
        $repeateloptions['inputfile']['type'] = PARAM_FILE;
        $repeateloptions['outputfile']['type'] = PARAM_FILE;
        $repeateloptions['caseid']['type'] = PARAM_INT;
        $repeateloptions['testcases']['helpbutton'] =  array('testcases', 'onlinejudge');
        $repeateloptions['input']['helpbutton'] =  array('input', 'onlinejudge');
        $repeateloptions['output']['helpbutton'] =  array('output', 'onlinejudge');
        // $repeateloptions['inputfile']['helpbutton'] =  array('inputfile', 'onlinejudge');
        // $repeateloptions['outputfile']['helpbutton'] =  array('outputfile', 'onlinejudge');
        $repeateloptions['subgrade']['helpbutton'] =  array('subgrade', 'onlinejudge');
        $repeateloptions['feedback']['helpbutton'] =  array('feedback', 'onlinejudge');
        $repeateloptions['subgrade']['default'] = 0;
        $repeateloptions['inputfile']['disabledif'] = array( 'usefile', 'notchecked');
        $repeateloptions['outputfile']['disabledif'] = array( 'usefile', 'notchecked');
        $repeateloptions['input']['disabledif'] = array( 'usefile', 'checked');
        $repeateloptions['output']['disabledif'] = array( 'usefile', 'checked');

        $this->repeat_elements($repeatarray, 1, $repeateloptions, 'boundary_repeats', 'add_testcases', 1, get_string('addtestcases', 'onlinejudge', 1), true);

        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}

