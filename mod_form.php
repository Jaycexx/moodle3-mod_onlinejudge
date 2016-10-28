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
 * The main onlinejudge configuration form 自定义了表单实例
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_onlinejudge
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/onlinejudge/judgelib.php');

ini_set('xdebug.var_display_max_depth', 50);
ini_set('xdebug.var_display_max_children', 2560);
ini_set('xdebug.var_display_max_data', 2048);
/**
 * Module instance settings form
 *
 * @package    mod_onlinejudge
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_onlinejudge_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;

        //general
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        $mform->addElement('text', 'name', get_string('modulename', 'onlinejudge').' name');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_ALPHANUMEXT);

        $this->standard_intro_elements();

        $name = get_string('allowsubmissionsfromdate', 'onlinejudge');
        $options = array('optional'=>true);
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', $name, $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'onlinejudge');

        $name = get_string('duedate', 'onlinejudge');
        $mform->addElement('date_time_selector', 'duedate', $name, array('optional'=>true));
        $mform->addHelpButton('duedate', 'duedate', 'onlinejudge');

        $name = get_string('cutoffdate', 'onlinejudge');
        $mform->addElement('date_time_selector', 'cutoffdate', $name, array('optional'=>true));
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'onlinejudge');

        $name = get_string('alwaysshowdescription', 'onlinejudge');
        $mform->addElement('checkbox', 'alwaysshowdescription', $name);
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'onlinejudge');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');

        //online judge
        $mform->addElement('header', 'onelinejudge', get_string('pluginname', 'onlinejudge'));

        //yes or not
        $ynoptions = array( 0 => get_string('no'), 1 => get_string('yes'));

        // upload file limit
        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('courseuploadlimit', 'onlinejudge') . ' ('.display_size($COURSE->maxbytes).')';
        $mform->addElement('select', 'maxbytes', get_string('maximumfilesize', 'onlinejudge'), $choices);
        //默认的文件大小限制
        $mform->setDefault('maxbytes', 1048576);

        $mform->addElement('select', 'resubmit', get_string('allowdeleting', 'onlinejudge'), $ynoptions);
        $mform->addHelpButton('resubmit', 'allowdeleting', 'onlinejudge');
        $mform->setDefault('resubmit', 1);

        $options = array();
        for($i = 1; $i <= 20; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'var1', get_string('allowmaxfiles', 'onlinejudge'), $options);
        $mform->addHelpButton('var1', 'allowmaxfiles', 'onlinejudge');
        $mform->setDefault('var1', 1);

        $mform->addElement('select', 'var2', get_string('allownotes', 'onlinejudge'), $ynoptions);
        $mform->addHelpButton('var2', 'allownotes', 'onlinejudge');
        $mform->setDefault('var2', 0);

        $mform->addElement('select', 'var3', get_string('hideintro', 'onlinejudge'), $ynoptions);
        $mform->addHelpButton('var3', 'hideintro', 'onlinejudge');
        $mform->setDefault('var3', 0);

        $mform->addElement('select', 'emailteachers', get_string('emailteachers', 'onlinejudge'), $ynoptions);
        $mform->addHelpButton('emailteachers', 'emailteachers', 'onlinejudge');
        $mform->setDefault('emailteachers', 0);


        // Programming languages
        unset($choices);
        $choices = onlinejudge_get_languages();
        $mform->addElement('select', 'language', get_string('assignmentlangs', 'onlinejudge'), $choices);
        $mform->setDefault('language', isset($onlinejudge) ? $onlinejudge->language : get_config('local_onlinejudge', 'defaultlanguage'));

        // Max. CPU time
        unset($choices);
        $choices = $this->get_max_cpu_times();
        $mform->addElement('select', 'cpulimit', get_string('cpulimit', 'onlinejudge'), $choices);
        $mform->setDefault('cpulimit', isset($onlinejudge) ? $onlinejudge->cpulimit : 1);

        // Max. memory usage
        unset($choices);
        $choices = $this->get_max_memory_usages();
        $mform->addElement('select', 'memlimit', get_string('memlimit', 'onlinejudge'), $choices);
        $mform->setDefault('memlimit', isset($onlinejudge) ? $onlinejudge->memlimit : 1048576);

        // Compile only? ; 原版menu没有，先注释掉
        // $mform->addElement('select', 'compileonly', get_string('compileonly', 'onlinejudge'), $ynoptions);
        // $mform->addHelpButton('compileonly', 'compileonly', 'onlinejudge');
        // $mform->setDefault('compileonly', isset($onlinejudge) ? $onlinejudge->compileonly : 0);
        // $mform->setAdvanced('compileonly');

        //ideone.com
        $mform->addElement('text', 'ideoneuser', get_string('ideoneuser', 'onlinejudge'), array('size' => 20));
        $mform->addHelpButton('ideoneuser', 'ideoneuser', 'onlinejudge');
        $mform->setType('ideoneuser', PARAM_ALPHANUMEXT);
        $mform->setDefault('ideoneuser', isset($onlinejudge) ? $onlinejudge->ideoneuser : '');
        $mform->addElement('password', 'ideonepass', get_string('ideonepass', 'onlinejudge'), array('size' => 20));
        $mform->addHelpButton('ideonepass', 'ideonepass', 'onlinejudge');
        $mform->setDefault('ideonepass', isset($onlinejudge) ? $onlinejudge->ideonepass : '');

        
        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        // 删了出错。
        $this->standard_coursemodule_elements();
        
        // Add standard buttons, common to all modules.
        $this->add_action_buttons();


    }

    //Custom validation should be added here
    function form_validation($data, $files) {
        $errors = array();
        if (substr($data['language'], -6) == 'ideone') {
            // ideone.com do not support multi-files
            // TODO: do not hardcode ideone here. judge should has support_multifile() function
            if ($data['var1'] > 1) {
                $errors['var1'] = get_string('onefileonlyideone', 'onlinejudge');
            }

            if (empty($data['ideoneuser'])) {
                $errors['ideoneuser'] = get_string('ideoneuserrequired', 'onlinejudge');
            }
            if (empty($data['ideonepass'])) {
                $errors['ideonepass'] = get_string('ideoneuserrequired', 'onlinejudge');
            } else if (!empty($data['ideoneuser'])) { // test username and password
                // creating soap client
                $client = new SoapClient("http://ideone.com/api/1/service.wsdl");
                // calling test function
                $testArray = $client->testFunction($data['ideoneuser'], $data['ideonepass']);
                if ($testArray['error'] == 'AUTH_ERROR') {
                    $errors['ideoneuser'] = $errors['ideonepass'] = get_string('ideoneautherror', 'onlinejudge');
                }
            }

        }
        return $errors;
    }

    static function get_max_cpu_times() {

        // Get max size
        $maxtime = get_config('onlinejudge', 'maxcpulimit');
        $cputime[$maxtime] = get_string('numseconds', 'moodle', $maxtime);

        $timelist = array(1, 2, 3, 4, 5, 6, 7, 8, 9,
                          10, 11, 12, 13, 14, 15, 20,
                          25, 30, 40, 50, 60);

        foreach ($timelist as $timesecs) {
           if ($timesecs < $maxtime) {
               $cputime[$timesecs] = get_string('numseconds', 'moodle', $timesecs);
           }
        }

        ksort($cputime, SORT_NUMERIC);

        return $cputime;
    }

    static function get_max_memory_usages() {

        // Get max size
        $maxsize = 1024 * 1024 * get_config('onlinejudge', 'maxmemlimit');
        $memusage[$maxsize] = display_size($maxsize);

        $sizelist = array(1048576, 2097152, 4194304, 8388608, 16777216, 33554432,
                          67108864, 134217728, 268435456, 536870912);

        foreach ($sizelist as $sizebytes) {
           if ($sizebytes < $maxsize) {
               $memusage[$sizebytes] = display_size($sizebytes);
           }
        }

        ksort($memusage, SORT_NUMERIC);

        return $memusage;
    }
}
