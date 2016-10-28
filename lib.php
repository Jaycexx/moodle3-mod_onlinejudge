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
 * Online Judge navigation menu
 * 
 * @package   local_onlinejudge
 * @copyright 2011 Sun Zhigang (http://sunner.cn)
 * @author    Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * add the onlinejudge plugin into navigation
 */
function onlinejudge_extends_navigation(global_navigation $navigation) {

    $onlinejudge = $navigation->add(get_string('pluginname', 'local_onlinejudge'), new moodle_url('/local/onlinejudge/'));

}

function onlinejudge_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

function onlinejudge_add_instance(stdClass $newmodule, mod_onlinejudge_mod_form $mform = null) {
    global $DB;

    $newmodule->timecreated = time();

    // You may have to add extra stuff in here.

    $newmodule->id = $DB->insert_record('onlinejudge', $newmodule);

    onlinejudge_grade_item_update($newmodule);

    return $newmodule->id;
}

function onlinejudge_update_instance($onlinejudge) {

    global $DB;
    $onlinejudge->timemodified = time();
    var_dump($onlinejudge);
    // You may have to add extra stuff in here.
    $old_onlinejudge = $DB->get_record('onlinejudge', array('id' => $onlinejudge->instance));
    if ($old_onlinejudge) {
        $onlinejudge->id = $old_onlinejudge->id;
        $DB->update_record('onlinejudge', $onlinejudge);
    }
    $result = $DB->update_record('onlinejudge', $onlinejudge);

    onlinejudge_grade_item_update($onlinejudge);

    return $result;
}

function onlinejudge_delete_instance($onlinejudge) {
    global $CFG, $DB;

    // delete onlinejudge submissions
    $submissions = $DB->get_records('assignment_submissions', array('assignment' => $assignment->id));
    foreach ($submissions as $submission) {
        if (!$DB->delete_records('onlinejudge_submissions', array('submission' => $submission->id)))
            return false;
    }

    // delete testcases
    // parent will delete all files in this context
    if (!$DB->delete_records('onlinejudge_testcases', array('assignment' => $assignment->id))) {
        return false;
    }

    // delete onlinejudge settings
    if (!$DB->delete_records('onlinejudge', array('assignment' => $assignment->id))) {
        return false;
    }

    // inform judgelib to delete related tasks
    $cm = get_coursemodule_from_instance('assignment', $assignment->id);
    if (!onlinejudge_delete_coursemodule($cm->id)) {
        return false;
    }

    $result = parent::delete_instance($assignment);

    return $result;
}

function onlinejudge_grade_item_update(stdClass $newmodule, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($newmodule->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($newmodule->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $newmodule->grade;
        $item['grademin']  = 0;
    } else if ($newmodule->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$newmodule->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('mod/onlinejudge', $newmodule->course, 'mod', 'onlinejudge',
            $newmodule->id, 0, null, $item);
}

function unescape($str) {
    //对转义的html字符进行转换
    $str = str_replace('&lt;', '<', $str);
    $str = str_replace('&gt;', '>', $str);
    $str = str_replace('&nbsp;', ' ', $str);

    return $str;
}