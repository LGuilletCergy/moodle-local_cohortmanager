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
 * Send notification to teachers when they can link a new cohort.
 *
 * @package   local_cohortlinker
 * @copyright 2018 Laurent Guillet <laurent.guillet@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : syncothercohorts.php
 * Send message with link
 */

namespace local_cohortmanager\task;

defined('MOODLE_INTERNAL') || die();

class syncothercohorts extends \core\task\scheduled_task {

    public function get_name() {

        return get_string('syncothercohorts', 'local_cohortmanager');
    }

    public function execute() {

        global $DB, $CFG;

        $listvets = array('5B01A1', '5B01B1', '5B01C1', '2B01A1', '2B01B1', '7B03A1');
        $idnumberother = $CFG->yearprefix.'-8';
        $categoryother = $DB->get_record('course_categories', array('idnumber' => $idnumberother));
        $contextother = $DB->get_record('context',
                array('instanceid' => $categoryother->id, 'contextlevel' => CONTEXT_COURSECAT));

        foreach ($listvets as $vet) {

            $vetidnumber = $CFG->yearprefix.'-'.$vet;
            $originalcohort = $DB->get_record('cohort', array('idnumber' => $vetidnumber));
            $typecode = 'copie-'.$originalcohort->id;

            if ($DB->record_exists('local_cohortmanager_info', array('typecohort' => $typecode))) {

                $copycohortinfo = $DB->get_record('local_cohortmanager_info', array('typecohort' => $typecode));
                $copycohortinfo->timesynced = time();
                $DB->update_record('local_cohortmanager_info', $copycohortinfo);
            } else {

                $copycohort = new \stdClass();
                $copycohort->contextid = $contextother->id;
                $copycohort->name = $originalcohort->name;
                $copycohort->idnumber = $originalcohort->idnumber.'-COPIE';
                $copycohort->description = $originalcohort->description;
                $copycohort->descriptionformat = $originalcohort->descriptionformat;
                $copycohort->visible = $originalcohort->visible;
                $copycohort->component = $originalcohort->component;
                $copycohort->timecreated = time();
                $copycohort->timemodified = time();
                $copycohort->theme = $originalcohort->theme;

                $copycohort->id = cohort_add_cohort($copycohort);

                $copycohortinfo = new \stdClass();
                $copycohortinfo->cohortid = $copycohort->id;
                $copycohortinfo->teacherid = null;
                $copycohortinfo->codeelp = 0;
                $copycohortinfo->timesynced = time();
                $copycohortinfo->typecohort = 'copie-'.$originalcohort->id;

                $DB->insert_record('local_cohortmanager_info', $copycohortinfo);
            }

            // Utiliser les mêmes membres de la cohorte.

            $listoriginalstudents = $DB->get_records('cohort_members', array('cohortid' => $originalcohort->id));

            foreach ($listoriginalstudents as $originalstudent) {

                if (!$DB->record_exists('cohort_members',
                        array('cohortid' => $copycohortinfo->cohortid, 'userid' => $originalstudent->userid))) {

                    cohort_add_member($copycohortinfo->cohortid, $originalstudent->userid);
                }
            }

            $sql = "SELECT * FROM mdl_cohort_members WHERE cohortid = $copycohortinfo->cohortid AND"
                    . " userid NOT IN (SELECT userid FROM mdl_cohort_members WHERE cohortid = $originalcohort->id)";

            $listdeletestudents = $DB->execute($sql);

            if (is_array($listdeletestudents)) {

                foreach ($listdeletestudents as $student) {

                    cohort_remove_member($copycohortinfo->cohortid, $student->userid);
                }
            }
        }
    }
}

