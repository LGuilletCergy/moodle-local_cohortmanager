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
 * File : lib.php
 * Library file
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot .'/cohort/lib.php');

function local_cohortmanager_extend_settings_navigation(settings_navigation $nav, context $context) {

    global $DB, $COURSE;

    if ($DB->record_exists('context', array('id' => $context->id, 'contextlevel' => 40))) {

        if (has_capability('local/cohortmanager:viewinfocategory', $context) &&
                $DB->record_exists('cohort', array('contextid' => $context->id))) {

            $branch = $nav->get('categorysettings');

            if (isset($branch)) {

                $params = array('contextid' => $context->id, 'origin' => 'course_cat');
                $manageurl = new moodle_url('/local/cohortmanager/viewinfo.php', $params);
                $managetext = get_string('viewinfo', 'local_cohortmanager');

                $icon = new pix_icon('cohort', $managetext, 'local_cohortmanager');
                $branch->add($managetext, $manageurl, $nav::TYPE_CONTAINER, null, null, $icon);
            }
        }
    }

    if ($DB->record_exists('context', array('id' => $context->id, 'contextlevel' => 50))) {

        if (count(cohort_get_available_cohorts($context)) > 0) {

            $canusecohorts = true;
        } else {

            $canusecohorts = false;
        }

        if (has_capability('local/cohortmanager:viewinfocourse', $context)
                && $canusecohorts && $COURSE->id != 1) {

            $branch = $nav->get('courseadmin');

            if (isset($branch)) {

                $params = array('contextid' => $context->id, 'origin' => 'course');
                $manageurl = new moodle_url('/local/cohortmanager/viewinfo.php', $params);
                $managetext = get_string('viewinfo', 'local_cohortmanager');

                $icon = new pix_icon('cohort', $managetext, 'local_cohortmanager');
                $branch->add($managetext, $manageurl, $nav::TYPE_CONTAINER, null, null, $icon);
            }
        }
    }
}