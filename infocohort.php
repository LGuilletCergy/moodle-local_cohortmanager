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
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Create cohorts and add ways to manage them for teachers.
 *
 * @package   local_cohortmanager
 * @copyright 2017 Laurent Guillet <laurent.guillet@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : infocohort.php
 * View information on a specific cohort
 */

require_once("../../config.php");

$cohortid = required_param('cohortid', PARAM_INT);
$contextid = required_param('contextid', PARAM_INT);
$origin = required_param('origin', PARAM_TEXT);

global $PAGE, $DB;

require_login();
$context = context::instance_by_id($contextid);

$PAGE->set_context($context);

$cohort = $DB->get_record('cohort', array('id' => $cohortid));

$pageurl = new moodle_url('/local/cohortmanager/infocohort.php',
                array('cohortid' => $cohort->id, 'origin' => $origin, 'contextid' => $contextid));
$title = get_string('infocohort', 'local_cohortmanager', $cohort->name);
$PAGE->set_title($title);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($title);

$PAGE->navbar->add(get_string('viewinfo', 'local_cohortmanager'),
        new moodle_url('/local/cohortmanager/viewinfo.php',
        array('contextid' => $contextid, 'origin' => $origin)));

if ($origin == 'course') {

    $courseid = $DB->get_record('context', array('id' => $contextid))->instanceid;

    $course = get_course($courseid);

    require_login($course);
} else {

    require_login();
}

$PAGE->navbar->add(get_string('infocohort', 'local_cohortmanager', $cohort->name));

echo $OUTPUT->header();

$contextcohort = context::instance_by_id($cohort->contextid);

if (has_capability('local/cohortmanager:viewinfocategory', $contextcohort)) {

    echo get_string('cohortusers', 'local_cohortmanager', $cohort->name)."<br><br>";

    $table = new html_table();
    $table->head  = array(get_string('firstname', 'local_cohortmanager'),
        get_string('lastname', 'local_cohortmanager'));
    $table->colclasses = array('leftalign firstname', 'leftalign lastname');
    $table->id = 'cohortmembers';
    $table->attributes['class'] = 'admintable generaltable';

    $listmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

    foreach ($listmembers as $member) {

        $user = $DB->get_record('user', array('id' => $member->userid));

        $line = array();
        $line[] = $user->firstname;
        $line[] = $user->lastname;
        $data[] = $row = new html_table_row($line);
    }

    $table->data  = $data;
    echo html_writer::table($table);


    $tablecourse = new html_table();
    $tablecourse->head  = array(get_string('course', 'local_cohortmanager'));
    $tablecourse->colclasses = array('leftalign course');
    $tablecourse->id = 'cohortcourses';
    $tablecourse->attributes['class'] = 'admintable generaltable';

    echo "<br>".get_string('coursewithcohort', 'local_cohortmanager', $cohort->name)."<br><br>";

    $listenrols = $DB->get_records('enrol', array('enrol' => 'cohort', 'customint1' => $cohortid));

    $datacourse = array();

    foreach ($listenrols as $enrol) {

        $coursename = $DB->get_record('course', array('id' => $enrol->courseid))->fullname;

        $line = array();
        $line[] = $coursename;
        $datacourse[] = $row = new html_table_row($line);
    }

    $tablecourse->data  = $datacourse;
    echo html_writer::table($tablecourse);

} else {

    $home = new moodle_url('/', array());
    echo "<a href=$home>".get_string('returnhome', 'local_cohortmanager')."</a>";
}

echo $OUTPUT->footer();