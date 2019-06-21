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
 * File : createdummycohorts.php
 * Create cohorts with dummy information
 */

define('CLI_SCRIPT', true);
require_once( __DIR__.'/../../config.php');

require_once($CFG->dirroot .'/cohort/lib.php');

global $DB;

if (!$DB->record_exists('cohort', array('idnumber' => 'Y2019-8S3TES', 'contextid' => 223889))) {

    $cohort = new stdClass();
    $cohort->contextid = 223889;
    $cohort->name = "Fausse cohorte de VET";
    $cohort->idnumber = 'Y2019-8S3TES';
    $cohort->component = 'local_cohortmanager';

    echo "La cohorte ".$cohort->name." n'existe pas\n";

    $cohortid = cohort_add_cohort($cohort);

    echo "Elle est créée.\n";
} else {

    $cohortid = $DB->get_record('cohort', array('idnumber' => 'Y2019-8S3TES', 'contextid' => 223889));
}

$sql = "SELECT * FROM {user} WHERE username LIKE '%etudiant%'";

$listdummystudents = $DB->get_records_sql($sql);

foreach ($listdummystudents as $dummystudent) {

    if (!$DB->record_exists('cohort_members', array('cohortid' => $cohortid, 'userid' => $dummystudent->id))) {

        echo "Inscription de l'utilisateur ".$dummystudent->username."\n";

        cohort_add_member($cohortid, $dummystudent->id);

        echo "Utilisateur inscrit\n";
    }
}