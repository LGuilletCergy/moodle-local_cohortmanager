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
 * @copyright 2019 Laurent Guillet <laurent.guillet@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : manuallyemptycohorts.php
 * Script to launch manually to remove cohort members. To edit depending on what you want to delete.
 */

define('CLI_SCRIPT', true);
require_once( __DIR__.'/../../config.php');

require_once($CFG->dirroot .'/cohort/lib.php');
require_once($CFG->dirroot .'/course/lib.php');

global $DB;

$sqllistcohortstoempty = "SELECT * FROM {cohort} WHERE idnumber LIKE 'Y2019-______-%' "
        . "AND component LIKE 'local_cohortmanager'";

$listcohortstoempty = $DB->get_records_sql($sqllistcohortstoempty);

foreach ($listcohortstoempty as $cohorttoempty) {

    $cohortid = $cohorttoempty->id;

    $listmemberstoremove = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

    foreach ($listmemberstoremove as $membertoremove) {

        echo "Désinscription de l'utilisateur $membertoremove->userid de la cohorte $cohortid\n";
        cohort_remove_member($cohortid, $membertoremove->userid);
        echo "Utilisateur désinscrit\n";
    }
}