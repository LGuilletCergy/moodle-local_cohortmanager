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
 * Adds to the course a section where the teacher can submit a problem to groups of students
 * and give them various collaboration tools to work together on a solution.
 *
 * @package   local_cohortmanager
 * @copyright 2017 Laurent Guillet <laurent.guillet@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : redirecttoinfo.php
 * Redirect to infocohort.php, used by javascript
 */

require_once("../../config.php");

$courseid = required_param('courseid', PARAM_INT);

global $PAGE, $DB;

$contextid = context_course::instance($courseid);

$pageurl = new moodle_url('/local/cohortmanager/redirecttoinfo.php',
                array('courseid' => $courseid));

$PAGE->set_url($pageurl);

$context = context::instance_by_id($contextid);

$PAGE->set_context($context);

$viewinfourl = new moodle_url('/local/cohortmanager/viewinfo.php',
                array('origin' => 'course', 'contextid' => $contextid));

redirect($viewinfourl);