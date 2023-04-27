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
 * Update form
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\form;

defined('MOODLE_INTERNAL') || die();

/**
 * class for displaying update session form.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatesession extends \moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $DB, $COURSE;
        $mform    =& $this->_form;

        $modcontext    = $this->_customdata['modcontext'];
        $sessionid     = $this->_customdata['sessionid'];

        if (!$sess = $DB->get_record('attendance_sessions', array('id' => $sessionid) )) {
            error('No such session in this course');
        }

        $sess_extra = $DB->get_record('attendance_sessions_extra', array('sessionid' => $sessionid) );

        $attendancesubnet = $DB->get_field('attendance', 'subnet', array('id' => $sess->attendanceid));
        $defopts = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $modcontext);
        $sess = file_prepare_standard_editor($sess, 'description', $defopts, $modcontext, 'mod_attendance', 'session', $sess->id);

        $starttime = $sess->sessdate - usergetmidnight($sess->sessdate);
        $starthour = floor($starttime / HOURSECS);
        $startminute = floor(($starttime - $starthour * HOURSECS) / MINSECS);

        $enddate = $sess->sessdate + $sess->duration;
        $endtime = $enddate - usergetmidnight($enddate);
        $endhour = floor($endtime / HOURSECS);
        $endminute = floor(($endtime - $endhour * HOURSECS) / MINSECS);

        $data = array(
            'sessiondate' => $sess->sessdate,
            'sestime' => array('starthour' => $starthour, 'startminute' => $startminute,
            'endhour' => $endhour, 'endminute' => $endminute),
            'sdescription' => $sess->description_editor,
            'calendarevent' => $sess->calendarevent,
            'studentscanmark' => $sess->studentscanmark,
            'studentpassword' => $sess->studentpassword,
            'autoassignstatus' => $sess->autoassignstatus,
            'subnet' => $sess->subnet,
            'automark' => $sess->automark,
            'absenteereport' => $sess->absenteereport,
            'automarkcompleted' => 0,
            'preventsharedip' => $sess->preventsharedip,
            'preventsharediptime' => $sess->preventsharediptime,
            'includeqrcode' => $sess->includeqrcode,
            'rotateqrcode' => $sess->rotateqrcode,
            'automarkcmid' => $sess->automarkcmid,

            'sessionform' => ['sessionformtype' => $sess_extra->sessionform],
            'sessionmethod' => $sess_extra->sessionmethod,
            'sessionlanguage' => $sess_extra->classlanguage,
            'datestart' => $sess_extra->sessiondatestart,
            'dateend' => $sess_extra->sessiondateend,
            'applicationdeadline' => $sess_extra->applicationdeadline,
            'country' => isset($sess_extra->country) ? $sess_extra->country : '',
            'city' => isset($sess_extra->city) ? $sess_extra->city : ''
        );
        if ($sess->subnet == $attendancesubnet) {
            $data['usedefaultsubnet'] = 1;
        } else {
            $data['usedefaultsubnet'] = 0;
        }

        $mform->addElement('header', 'general', get_string('changesession', 'attendance'));

        if ($sess->groupid == 0) {
            $strtype = get_string('commonsession', 'attendance');
        } else {
            $groupname = $DB->get_field('groups', 'name', array('id' => $sess->groupid));
            $strtype = get_string('group') . ': ' . $groupname;
        }
        $mform->addElement('static', 'sessiontypedescription', get_string('sessiontype', 'attendance'), $strtype);

        $olddate = construct_session_full_date_time($sess->sessdate, $sess->duration);
        $mform->addElement('static', 'olddate', get_string('olddate', 'attendance'), $olddate);

        attendance_form_sessiondate_selector($mform);

        // Show which status set is in use.
        $maxstatusset = attendance_get_max_statusset($this->_customdata['att']->id);
        if ($maxstatusset > 0) {
            $mform->addElement('static', 'statussetstring', get_string('usestatusset', 'mod_attendance'),
                attendance_get_setname($this->_customdata['att']->id, $sess->statusset));
        }
        $mform->addElement('hidden', 'statusset', $sess->statusset);
        $mform->setType('statusset', PARAM_INT);

        $mform->addElement('editor', 'sdescription', get_string('description', 'attendance'),
                           array('rows' => 1, 'columns' => 80), $defopts);
        $mform->setType('sdescription', PARAM_RAW);

        if (!empty(get_config('attendance', 'enablecalendar'))) {
            $mform->addElement('checkbox', 'calendarevent', '', get_string('calendarevent', 'attendance'));
            $mform->addHelpButton('calendarevent', 'calendarevent', 'attendance');
        } else {
            $mform->addElement('hidden', 'calendarevent', 0);
            $mform->setType('calendarevent', PARAM_INT);
        }

        // If warnings allow selector for reporting.
        if (!empty(get_config('attendance', 'enablewarnings'))) {
            $mform->addElement('checkbox', 'absenteereport', '', get_string('includeabsentee', 'attendance'));
            $mform->addHelpButton('absenteereport', 'includeabsentee', 'attendance');
        }

        // For Extended informations.
        $mform->addElement('header', 'headeraddextendedinfo', get_string('addextendedinfo', 'attendance'));
        $mform->setExpanded('headeraddextendedinfo');

        $radio = array();
        $radio[] = $mform->createElement('radio', 'sessionformtype', null, get_string('sessionform_w', 'attendance'), 'W');
        $radio[] = $mform->createElement('radio', 'sessionformtype', null, get_string('sessionform_s', 'attendance'), 'S');
        $mform->addGroup($radio, 'sessionform', get_string('sessionform', 'attendance'));

        $options = array(
            'F2F' => get_string('sessionmethod_f2f', 'attendance'),
            'V' => get_string('sessionmethod_virtual', 'attendance'),
        );

        $mform->addElement('select', 'sessionmethod', get_string('sessionmethod', 'attendance'), $options);

        $languages = get_string_manager()->get_list_of_languages();
        $mform->addElement('select', 'sessionlanguage', get_string('sessionlanguage', 'attendance'), $languages);

        $mform->addElement('date_selector', 'datestart', get_string('sessiondatestart', 'attendance'));
        $mform->addElement('date_selector', 'dateend', get_string('sessiondateend', 'attendance'));
        $mform->addElement('date_selector', 'applicationdeadline', get_string('applicationdeadline', 'attendance'));

        $choices = get_string_manager()->get_list_of_countries();
        $choices = array(''=>get_string('selectacountry').'...') + $choices;
        $mform->addElement('select', 'country', get_string('country', 'attendance'), $choices);

        $mform->hideIf('country', 'sessionmethod', 'eq', 'V');

        $options = ['maxlength' => '100', 'size' => '25', 'autocomplete' => 'none'];
        $mform->addElement('text', 'city', get_string('city', 'attendance'), $options);
        $mform->setType('city', PARAM_ALPHAEXT);

        $mform->hideIf('city', 'sessionmethod', 'eq', 'V');


        // Students can mark own attendance.
        $studentscanmark = get_config('attendance', 'studentscanmark');

        $mform->addElement('header', 'headerstudentmarking', get_string('studentmarking', 'attendance'), true);
        $mform->setExpanded('headerstudentmarking');
        if (!empty($studentscanmark)) {
            $mform->addElement('checkbox', 'studentscanmark', '', get_string('studentscanmark', 'attendance'));
            $mform->addHelpButton('studentscanmark', 'studentscanmark', 'attendance');
        } else {
            $mform->addElement('hidden', 'studentscanmark', '0');
            $mform->settype('studentscanmark', PARAM_INT);
        }

        if ($DB->record_exists('attendance_statuses', ['attendanceid' => $this->_customdata['att']->id, 'setunmarked' => 1])) {
            $options2 = attendance_get_automarkoptions();

            $mform->addElement('select', 'automark', get_string('automark', 'attendance'), $options2);
            $mform->setType('automark', PARAM_INT);
            $mform->addHelpButton('automark', 'automark', 'attendance');

            $automarkcmoptions2 = attendance_get_coursemodulenames($COURSE->id);

            $mform->addElement('select', 'automarkcmid', get_string('selectactivity', 'attendance'), $automarkcmoptions2);
            $mform->setType('automarkcmid', PARAM_INT);
            $mform->hideif('automarkcmid', 'automark', 'neq', '3');
            if (!empty($sess->automarkcompleted)) {
                $mform->hardFreeze('automarkcmid,automark,studentscanmark');
            }
        }
        if (!empty($studentscanmark)) {
            $mform->addElement('text', 'studentpassword', get_string('studentpassword', 'attendance'));
            $mform->setType('studentpassword', PARAM_TEXT);
            $mform->addHelpButton('studentpassword', 'passwordgrp', 'attendance');
            $mform->disabledif('studentpassword', 'rotateqrcode', 'checked');
            $mform->hideif('studentpassword', 'studentscanmark', 'notchecked');
            $mform->hideif('studentpassword', 'automark', 'eq', ATTENDANCE_AUTOMARK_ALL);
            $mform->hideif('randompassword', 'automark', 'eq', ATTENDANCE_AUTOMARK_ALL);
            $mform->addElement('checkbox', 'includeqrcode', '', get_string('includeqrcode', 'attendance'));
            $mform->hideif('includeqrcode', 'studentscanmark', 'notchecked');
            $mform->disabledif('includeqrcode', 'rotateqrcode', 'checked');
            $mform->addElement('checkbox', 'rotateqrcode', '', get_string('rotateqrcode', 'attendance'));
            $mform->hideif('rotateqrcode', 'studentscanmark', 'notchecked');
            $mform->addElement('checkbox', 'autoassignstatus', '', get_string('autoassignstatus', 'attendance'));
            $mform->addHelpButton('autoassignstatus', 'autoassignstatus', 'attendance');
            $mform->hideif('autoassignstatus', 'studentscanmark', 'notchecked');
        }

        $mgroup = array();
        $mgroup[] = & $mform->createElement('text', 'subnet', get_string('requiresubnet', 'attendance'));
        $mform->setDefault('subnet', $this->_customdata['att']->subnet);
        $mgroup[] = & $mform->createElement('checkbox', 'usedefaultsubnet', get_string('usedefaultsubnet', 'attendance'));
        $mform->setDefault('usedefaultsubnet', 1);
        $mform->setType('subnet', PARAM_TEXT);

        $mform->addGroup($mgroup, 'subnetgrp', get_string('requiresubnet', 'attendance'), array(' '), false);
        $mform->setAdvanced('subnetgrp');
        $mform->addHelpButton('subnetgrp', 'requiresubnet', 'attendance');
        $mform->hideif('subnet', 'usedefaultsubnet', 'checked');

        $mform->addElement('hidden', 'automarkcompleted', '0');
        $mform->settype('automarkcompleted', PARAM_INT);

        $mgroup3 = array();
        $options = attendance_get_sharedipoptions();
        $mgroup3[] = & $mform->createElement('select', 'preventsharedip',
            get_string('preventsharedip', 'attendance'), $options);
        $mgroup3[] = & $mform->createElement('text', 'preventsharediptime',
            get_string('preventsharediptime', 'attendance'), '', 'test');
        $mform->addGroup($mgroup3, 'preventsharedgroup',
            get_string('preventsharedip', 'attendance'), array(' '), false);
        $mform->addHelpButton('preventsharedgroup', 'preventsharedip', 'attendance');
        $mform->setAdvanced('preventsharedgroup');
        $mform->setType('preventsharediptime', PARAM_INT);

        $mform->setDefaults($data);
        $this->add_action_buttons(true);
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $sesstarttime = $data['sestime']['starthour'] * HOURSECS + $data['sestime']['startminute'] * MINSECS;
        $sesendtime = $data['sestime']['endhour'] * HOURSECS + $data['sestime']['endminute'] * MINSECS;
        if ($sesendtime < $sesstarttime) {
            $errors['sestime'] = get_string('invalidsessionendtime', 'attendance');
        }

        if (!empty($data['studentscanmark']) && isset($data['automark'])
            && $data['automark'] == ATTENDANCE_AUTOMARK_CLOSE) {

            $cm            = $this->_customdata['cm'];
            // Check that the selected statusset has a status to use when unmarked.
            $sql = 'SELECT id
            FROM {attendance_statuses}
            WHERE deleted = 0 AND (attendanceid = 0 or attendanceid = ?)
            AND setnumber = ? AND setunmarked = 1';
            $params = array($cm->instance, $data['statusset']);
            if (!$DB->record_exists_sql($sql, $params)) {
                $errors['automark'] = get_string('noabsentstatusset', 'attendance');
            }
        }

        if (!empty($data['studentscanmark']) && !empty($data['preventsharedip']) &&
                empty($data['preventsharediptime'])) {
            $errors['preventsharedgroup'] = get_string('iptimemissing', 'attendance');

        }

        if(empty($data['sessionmethod'])){
            $errors['sessionmethod'] = get_string('should_be_selected', 'attendance', 'method');
        }

        $f2f_session = $data['sessionmethod'] !== 'V';
        if($f2f_session){
            if(empty($data['country'])){
                $errors['country'] = get_string('should_be_selected', 'attendance', 'country');
            }
            if(empty($data['city'])){
                $errors['city'] = get_string('should_be_filled', 'attendance');
            }
        }

        if($data['datestart'] > $data['dateend']){
            $errors['datestart'] = get_string('start_before_end', 'attendance');
        }

        if(empty($data['sessionlanguage'])){
            $errors['sessionlanguage'] = get_string('should_be_selected', 'attendance', 'language');
        }
        return $errors;
    }
}
