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
 * File : createcohorts.php
 * Create cohorts, assign cohort members and fill table local_cohortmanager_info
 */

define('CLI_SCRIPT', true);
require_once( __DIR__.'/../../config.php');

require_once($CFG->dirroot .'/cohort/lib.php');
require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->libdir .'/filelib.php');

$timesync = time();

// Cohortes de groupes remplies.

$xmldocetuens = new DOMDocument();
$fileopeningetuens = $xmldocetuens->load('/home/referentiel/dokeos_elp_etu_ens.xml');
if ($fileopeningetuens == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvaretuens = new Domxpath($xmldocetuens);

    $listtreatedgroups = array();

    $groupsetuens = $xpathvaretuens->query('//Structure_diplome/Cours/Group');

    foreach ($groupsetuens as $group) {

        $vet = $group->parentNode->parentNode;
        $idvet = $vet->getAttribute('Etape');
        $idvetyear = "$CFG->yearprefix-$idvet";

        $cours = $group->parentNode;
        $courselp = $cours->getAttribute('element_pedagogique');

        $groupcode = $group->getAttribute('GroupCode');
        $groupname = $group->getAttribute('GroupName');

        $cohortcode = "$CFG->yearprefix-".$idvet."-".$groupcode;

        if (!in_array($cohortcode, $listtreatedgroups)) {

            if ($courselp != "" && $groupcode != "") {

                if ($DB->record_exists('course_categories', array('idnumber' => $idvetyear))) {

                    $category = $DB->get_record('course_categories', array('idnumber' => $idvetyear));

                    $parentcategory = $DB->get_record('course_categories', array('id' => $category->parent));
                    $contextidparentcategory = $DB->get_record('context',
                                                    array('contextlevel' => 40,
                                                        'instanceid' => $parentcategory->id))->id;

                    $tableteachername = array();

                    if ($DB->record_exists('cohort', array('idnumber' => $cohortcode,
                        'contextid' => $contextidparentcategory))) {

                        $cohort = $DB->get_record('cohort', array('idnumber' => $cohortcode,
                                'contextid' => $contextidparentcategory));

                        $cohortid = $cohort->id;

                        if ($cohort->name != $groupname." ($idvet-$groupcode)") {

                            if (!$DB->record_exists('local_cohortmanager_names',
                                    array('cohortid' => $cohortid, 'codeelp' => $cohortcode))) {

                                $cohortnameentry = new stdClass();
                                $cohortnameentry->cohortid = $cohortid;
                                $cohortnameentry->codeelp = $cohortcode;
                                $cohortnameentry->cohortname = $groupname;

                                $DB->insert_record('local_cohortmanager_names', $cohortnameentry);

                            } else if (!$DB->record_exists('local_cohortmanager_names',
                                    array('cohortid' => $cohortid, 'codeelp' => $cohortcode,
                                        'cohortname' => $groupname))) {

                                $cohortnameentry = $DB->get_record('local_cohortmanager_names',
                                        array('cohortid' => $cohortid, 'codeelp' => $cohortcode));

                                $cohortnameentry->cohortname = $groupname;

                                $DB->update_record('local_cohortmanager_names', $cohortnameentry);
                            }
                        }

                        echo "La cohorte ".$cohort->name." existe\n";

                        $listcohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

                        $listtempcohortmembers = array();

                        foreach ($listcohortmembers as $cohortmembers) {

                            $tempcohortmember = new stdClass();
                            $tempcohortmember->userid = $cohortmembers->userid;
                            $tempcohortmember->stillexists = 0;

                            $listtempcohortmembers[] = $tempcohortmember;
                        }

                        $group->removeChild($group->lastChild);

                        foreach ($group->childNodes as $groupmember) {

                            if ($groupmember->nodeType !== 1 ) {
                                    continue;
                            }

                            $username = $groupmember->getAttribute('StudentUID');
                            $tableteachername[] = $groupmember->getAttribute('StaffUID');

                            if ($DB->record_exists('user', array('username' => $username))) {

                                $memberid = $DB->get_record('user',
                                        array('username' => $username))->id;

                                if ($DB->record_exists('cohort_members',
                                        array('cohortid' => $cohortid, 'userid' => $memberid))) {

                                    foreach ($listtempcohortmembers as $tempcohortmember) {

                                        if ($tempcohortmember->userid == $memberid) {

                                            $tempcohortmember->stillexists = 1;
                                        }
                                    }
                                } else {

                                    echo "Inscription de l'utilisateur ".$username."\n";

                                    cohort_add_member($cohortid, $memberid);

                                    echo "Utilisateur inscrit\n";
                                }
                            }
                        }

                        if (isset($listtempcohortmembers)) {

                            foreach ($listtempcohortmembers as $tempcohortmember) {

                                if ($tempcohortmember->stillexists == 0) {

                                    echo "Désinscription de l'utilisateur $tempcohortmember->userid"
                                                    . " de la cohorte $cohortid Cas 1\n";

                                    cohort_remove_member($cohortid, $tempcohortmember->userid);

                                    echo "Utilisateur désinscrit\n";
                                }
                            }
                        }
                    } else {

                        $cohort = new stdClass();
                        $cohort->contextid = $contextidparentcategory;
                        $cohort->name = $group->getAttribute('GroupName')." ($idvet-$groupcode)";
                        $cohort->idnumber = $cohortcode;
                        $cohort->component = 'local_cohortmanager';

                        echo "La cohorte ".$cohort->name." n'existe pas\n";

                        $cohortid = cohort_add_cohort($cohort);

                        echo "Elle est créée.\n";

                        $group->removeChild($group->lastChild);
                        $groupmembers = $group->childNodes;

                        foreach ($groupmembers as $groupmember) {

                            if ($groupmember->nodeType !== 1 ) {
                                    continue;
                            }

                            $username = $groupmember->getAttribute('StudentUID');
                            $tableteachername[] = $groupmember->getAttribute('StaffUID');

                            if ($DB->record_exists('user', array('username' => $username))) {

                                echo "Inscription de l'utilisateur ".$username."\n";

                                $memberid = $DB->get_record('user',
                                                array('username' => $username))->id;

                                cohort_add_member($cohortid, $memberid);

                                echo "Utilisateur inscrit\n";
                            }
                        }
                    }

                    $listtreatedgroups[] = $cohortcode;
                }
            }
        }

        foreach ($tableteachername as $teachername) {

            if ($DB->record_exists('user', array('username' => $teachername))) {

                $teacherid = $DB->get_record('user', array('username' => $teachername))->id;
                // Ici, rajouter l'entrée dans local_cohortmanager_info.

                $yearlycourselp = "$CFG->yearprefix-".$idvet.$courselp;

                if ($DB->record_exists('local_cohortmanager_info',
                        array('cohortid' => $cohortid, 'teacherid' => $teacherid,
                            'codeelp' => $yearlycourselp))) {

                    // Update record.

                    $cohortinfo = $DB->get_record('local_cohortmanager_info',
                        array('cohortid' => $cohortid, 'teacherid' => $teacherid,
                            'codeelp' => $yearlycourselp));

                    $cohortinfo->timesynced = $timesync;

                    $DB->update_record('local_cohortmanager_info', $cohortinfo);

                } else {

                    $cohortinfo = new stdClass();
                    $cohortinfo->cohortid = $cohortid;
                    $cohortinfo->teacherid = $teacherid;
                    $cohortinfo->codeelp = $yearlycourselp;
                    $cohortinfo->timesynced = $timesync;
                    $cohortinfo->typecohort = "group";

                    $DB->insert_record('local_cohortmanager_info', $cohortinfo);
                }
            }
        }
    }
}

// Cohortes de groupes vides.

$xmldocens = new DOMDocument();
$fileopeningens = $xmldocens->load('/home/referentiel/dokeos_elp_ens.xml');
if ($fileopeningens == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarens = new Domxpath($xmldocens);

    $groupsens = $xpathvarens->query('//Structure_diplome/Teacher/Cours/Group');

    foreach ($groupsens as $group) {

        $vet = $group->parentNode->parentNode->parentNode;
        $idvet = $vet->getAttribute('Etape');
        $idvetyear = "$CFG->yearprefix-$idvet";

        $cours = $group->parentNode;
        $courselp = $cours->getAttribute('element_pedagogique');

        $groupcode = $group->getAttribute('GroupCode');
        $groupname = $group->getAttribute('GroupName');

        $cohortcode = "$CFG->yearprefix-".$idvet."-".$groupcode;

        if ($courselp != "" && $groupcode != "") {

            if (!in_array($cohortcode, $listtreatedgroups)) {

                if ($DB->record_exists('course_categories', array('idnumber' => $idvetyear))) {

                    $category = $DB->get_record('course_categories', array('idnumber' => $idvetyear));

                    $parentcategory = $DB->get_record('course_categories', array('id' => $category->parent));
                    $contextidparentcategory = $DB->get_record('context',
                            array('contextlevel' => CONTEXT_COURSECAT, 'instanceid' => $parentcategory->id))->id;

                    if (!$DB->record_exists('cohort', array('idnumber' => $cohortcode,
                        'contextid' => $contextidparentcategory))) {

                        $cohort = new stdClass();
                        $cohort->contextid = $contextidparentcategory;
                        $cohort->name = $group->getAttribute('GroupName')." ($idvet-$groupcode)";
                        $cohort->idnumber = $cohortcode;
                        $cohort->component = 'local_cohortmanager';

                        echo "La cohorte ".$cohort->name." n'existe pas\n";

                        $cohortid = cohort_add_cohort($cohort);

                        echo "Elle est créée.\n";
                    }
                }

                $listtreatedgroups[] = $cohortcode;
            } else {

                if ($DB->record_exists('course_categories', array('idnumber' => $idvetyear))) {

                    $category = $DB->get_record('course_categories', array('idnumber' => $idvetyear));
                    $parentcategory = $DB->get_record('course_categories', array('id' => $category->parent));
                    $contextidparentcategory = $DB->get_record('context',
                            array('contextlevel' => 40, 'instanceid' => $parentcategory->id))->id;

                    $cohort = $DB->get_record('cohort', array('idnumber' => $cohortcode,
                            'contextid' => $contextidparentcategory));

                    $cohortid = $cohort->id;

                    if ($cohort->name != $groupname." ($idvet-$groupcode)") {

                        if (!$DB->record_exists('local_cohortmanager_names',
                                array('cohortid' => $cohortid, 'codeelp' => $cohortcode))) {

                            $cohortnameentry = new stdClass();
                            $cohortnameentry->cohortid = $cohortid;
                            $cohortnameentry->codeelp = $cohortcode;
                            $cohortnameentry->cohortname = $groupname;

                            $DB->insert_record('local_cohortmanager_names', $cohortnameentry);

                        } else if (!$DB->record_exists('local_cohortmanager_names',
                                array('cohortid' => $cohortid, 'codeelp' => $cohortcode, 'cohortname' => $groupname))) {

                            $cohortnameentry = $DB->get_record('local_cohortmanager_names',
                                    array('cohortid' => $cohortid, 'codeelp' => $cohortcode));

                            $cohortnameentry->cohortname = $groupname;

                            $DB->update_record('local_cohortmanager_names', $cohortnameentry);
                        }
                    }
                }

                echo "Cohorte $cohortcode déjà traitée\n";
            }
        }

        // Ici, rajouter l'entrée dans local_cohortmanager_info.

        $yearlycourselp = "$CFG->yearprefix-".$courselp;

        if ($DB->record_exists('local_cohortmanager_info',
                array('cohortid' => $cohortid, 'codeelp' => $yearlycourselp))) {

            // Update record.

            $cohortinfo = $DB->get_record('local_cohortmanager_info',
                array('cohortid' => $cohortid,
                    'codeelp' => $yearlycourselp));

            $cohortinfo->timesynced = $timesync;

            $DB->update_record('local_cohortmanager_info', $cohortinfo);

        } else {

            $cohortinfo = new stdClass();
            $cohortinfo->cohortid = $cohortid;
            $cohortinfo->teacherid = null;
            $cohortinfo->codeelp = $yearlycourselp;
            $cohortinfo->timesynced = $timesync;
            $cohortinfo->typecohort = "group";

            $DB->insert_record('local_cohortmanager_info', $cohortinfo);
        }
    }
}

// Cohortes de VETs remplies et de composantes.

$sqllistcohortsvets = "SELECT distinct cohortid FROM {local_cohortmanager_info} WHERE "
        . "(typecohort LIKE 'vet' OR typecohort LIKE 'composante')";

$listcohortsvetsdb = $DB->get_records_sql($sqllistcohortsvets);

$listexistence = array();

foreach ($listcohortsvetsdb as $cohortvetdb) {

    $listmembersdb = $DB->get_records('cohort_members', array('cohortid' => $cohortvetdb->cohortid));

    foreach ($listmembersdb as $memberdb) {

        $tempexistence = new stdClass();
        $tempexistence->cohortid = $cohortvetdb->cohortid;
        $tempexistence->userid = $memberdb->userid;
        $tempexistence->stillexists = 0;

        $listexistence[] = $tempexistence;
    }
}

$xmldocvet = new DOMDocument();
$fileopeningvet = $xmldocvet->load('/home/referentiel/DOKEOS_Etudiants_Inscriptions.xml');
if ($fileopeningvet == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarvet = new Domxpath($xmldocvet);

    $anneunivsvet = $xpathvarvet->query("//Student/Annee_universitaire[@AnneeUniv=$CFG->thisyear]");
    $student = null;

    foreach ($anneunivsvet as $anneuniv) {

        if ($student != $anneuniv->parentNode) {

            if ($student) {

                $username = $student->getAttribute('StudentUID');
                $user = $DB->get_record('user', array('username' => $username));

                if ($DB->record_exists('user_info_field', array('shortname' => 'cohortinfo1'))
                        && $DB->record_exists('user_info_field', array('shortname' => 'cohortinfo2'))) {

                    $fieldinfo1id = $DB->get_record('user_info_field', array('shortname' => 'cohortinfo1'))->id;
                    $fieldinfo2id = $DB->get_record('user_info_field', array('shortname' => 'cohortinfo2'))->id;

                    if ($compteurvet == 1) {

                        $DB->delete_records('user_info_data', array('userid' => $user->id, 'fieldid' => $fieldinfo1id));
                        $DB->delete_records('user_info_data', array('userid' => $user->id, 'fieldid' => $fieldinfo2id));

                    } else if ($compteurvet == 2) {

                        $DB->delete_records('user_info_data', array('userid' => $user->id, 'fieldid' => $fieldinfo2id));
                    }
                }
            }

            $compteurvet = 1;
        }

        $student = $anneuniv->parentNode;
        $username = $student->getAttribute('StudentUID');

        if ($DB->record_exists('user', array('username' => $username))) {

            $user = $DB->get_record('user', array('username' => $username));

            $student = $anneuniv->parentNode;
            $username = $student->getAttribute('StudentUID');

            if ($DB->record_exists('user', array('username' => $username))) {

                $memberid = $DB->get_record('user', array('username' => $username))->id;

                // Lister toutes ses inscriptions.

                foreach ($anneuniv->childNodes as $inscription) {

                    if ($inscription->nodeType !== 1 ) {
                        continue;
                    }
                    // Trouver la cohorte ou la créer et l'y inscrire.

                    $cohortcode = $CFG->yearprefix."-".$inscription->getAttribute('CodeEtape');

                    if ($DB->record_exists('course_categories', array('idnumber' => $cohortcode))) {

                        $vetcategory = $DB->get_record('course_categories', array('idnumber' => $cohortcode));

                        $contextidparentincategory = $DB->get_record('context',
                                array('instanceid' => $vetcategory->parent,
                                    'contextlevel' => CONTEXT_COURSECAT))->id;

                        if (!$DB->record_exists('cohort', array('idnumber' => $cohortcode,
                            'contextid' => $contextidparentincategory))) {

                            $cohort = new stdClass();
                            $cohort->contextid = $contextidparentincategory;
                            $cohort->name = substr($inscription->getAttribute('LibEtape'), 9);
                            $cohort->idnumber = $cohortcode;
                            $cohort->component = 'local_cohortmanager';

                            echo "La cohorte ".$cohort->name." n'existe pas\n";

                            $cohortid = cohort_add_cohort($cohort);

                            echo "Elle est créée.\n";
                        } else {

                            $cohortid = $DB->get_record('cohort', array('idnumber' => $cohortcode,
                                'contextid' => $contextidparentincategory))->id;
                        }

                        // Ici, rajouter l'entrée dans local_cohortmanager_info.

                        if ($DB->record_exists('local_cohortmanager_info',
                                array('cohortid' => $cohortid,
                                    'codeelp' => 0))) {

                            // Update record.

                            $cohortinfo = $DB->get_record('local_cohortmanager_info',
                                array('cohortid' => $cohortid,
                                    'codeelp' => 0));

                            $cohortinfo->timesynced = $timesync;

                            $DB->update_record('local_cohortmanager_info', $cohortinfo);

                        } else {

                            $cohortinfo = new stdClass();
                            $cohortinfo->cohortid = $cohortid;
                            $cohortinfo->teacherid = null;
                            $cohortinfo->codeelp = 0;
                            $cohortinfo->timesynced = $timesync;
                            $cohortinfo->typecohort = "vet";

                            $DB->insert_record('local_cohortmanager_info', $cohortinfo);
                        }

                        if (!$DB->record_exists('cohort_members',
                                array('cohortid' => $cohortid, 'userid' => $user->id))) {

                            echo "Inscription de l'utilisateur ".$username."\n";

                            cohort_add_member($cohortid, $user->id);

                            echo "Utilisateur inscrit\n";
                        } else {

                            foreach ($listexistence as $tempexistence) {

                                if ($tempexistence->userid == $user->id
                                        && $tempexistence->cohortid == $cohortid) {

                                    $tempexistence->stillexists = 1;
                                }
                            }
                        }
                    }

                    // Trouver la cohorte de composante ou la créer et l'y inscrire.

                    $composantecode = substr($inscription->getAttribute('CodeEtape'), 0, 1);

                    $cohortcomposantecode = $CFG->yearprefix."-S".$composantecode;
                    $categorycode = $CFG->yearprefix."-".$composantecode;

                    if ($DB->record_exists('course_categories',
                            array('idnumber' => $categorycode))) {

                        $composantecategory = $DB->get_record('course_categories',
                                array('idnumber' => $categorycode));

                        $contextidcomposantecategory = $DB->get_record('context',
                                array('instanceid' => $composantecategory->id,
                                    'contextlevel' => CONTEXT_COURSECAT))->id;

                        if (!$DB->record_exists('cohort', array('idnumber' => $cohortcomposantecode,
                            'contextid' => $contextidcomposantecategory))) {

                            $cohortcomposante = new stdClass();
                            $cohortcomposante->contextid = $contextidcomposantecategory;
                            $cohortcomposante->name = 'Etudiants de'
                                    . ' '.substr($composantecategory->name, 4);
                            $cohortcomposante->idnumber = $cohortcomposantecode;
                            $cohortcomposante->component = 'local_cohortmanager';

                            echo "La cohorte ".$cohortcomposante->name." n'existe pas\n";

                            $cohortcomposanteid = cohort_add_cohort($cohortcomposante);

                            echo "Elle est créée.\n";
                        } else {

                            $cohortcomposanteid = $DB->get_record('cohort',
                                    array('idnumber' => $cohortcomposantecode,
                                        'contextid' => $contextidcomposantecategory))->id;
                        }

                        // Ici, rajouter l'entrée dans local_cohortmanager_info.

                        if ($DB->record_exists('local_cohortmanager_info',
                                array('cohortid' => $cohortcomposanteid,
                                    'codeelp' => 0))) {

                            // Update record.

                            $cohortcomposanteinfo = $DB->get_record('local_cohortmanager_info',
                                array('cohortid' => $cohortcomposanteid,
                                    'codeelp' => 0));

                            $cohortcomposanteinfo->timesynced = $timesync;

                            $DB->update_record('local_cohortmanager_info', $cohortcomposanteinfo);

                        } else {

                            $cohortcomposanteinfo = new stdClass();
                            $cohortcomposanteinfo->cohortid = $cohortcomposanteid;
                            $cohortcomposanteinfo->teacherid = null;
                            $cohortcomposanteinfo->codeelp = 0;
                            $cohortcomposanteinfo->timesynced = $timesync;
                            $cohortcomposanteinfo->typecohort = "composante";

                            $DB->insert_record('local_cohortmanager_info', $cohortcomposanteinfo);
                        }

                        if (!$DB->record_exists('cohort_members',
                                array('cohortid' => $cohortcomposanteid, 'userid' => $user->id))) {

                            echo "Inscription de l'utilisateur ".$username."\n";

                            cohort_add_member($cohortcomposanteid, $user->id);

                            echo "Utilisateur inscrit\n";
                        } else {

                            foreach ($listexistence as $tempexistence) {

                                if ($tempexistence->userid == $user->id
                                        && $tempexistence->cohortid == $cohortcomposanteid) {

                                    $tempexistence->stillexists = 1;
                                }
                            }
                        }
                    }

                    // Ici, mettre dans son profil la VET de sa cohorte.

                    if ($DB->record_exists('user_info_field', array('shortname' => 'cohortinfo1'))
                            && $DB->record_exists('user_info_field', array('shortname' => 'cohortinfo2'))) {

                        $vetname = $inscription->getAttribute('LibEtape');

                        $fieldinfo1id = $DB->get_record('user_info_field', array('shortname' => 'cohortinfo1'))->id;
                        $fieldinfo2id = $DB->get_record('user_info_field', array('shortname' => 'cohortinfo2'))->id;

                        $recorduserinfo = new stdClass();
                        $recorduserinfo->userid = $user->id;
                        $recorduserinfo->data = $vetname;

                        if ($compteurvet == 1) {

                            $recorduserinfo->fieldid = $fieldinfo1id;
                            if ($DB->record_exists('user_info_data',
                                    array('userid' => $user->id, 'fieldid' => $fieldinfo1id))) {

                                $recorduserinfo = $DB->get_record('user_info_data',
                                        array('userid' => $user->id, 'fieldid' => $fieldinfo1id));
                                $recorduserinfo->data = $vetname;

                                $DB->update_record('user_info_data', $recorduserinfo);
                            } else {

                                $DB->insert_record('user_info_data', $recorduserinfo);
                            }

                        } else if ($compteurvet == 2) {

                            $recorduserinfo->fieldid = $fieldinfo2id;
                            if ($DB->record_exists('user_info_data',
                                    array('userid' => $user->id, 'fieldid' => $fieldinfo2id))) {

                                $recorduserinfo = $DB->get_record('user_info_data',
                                        array('userid' => $user->id, 'fieldid' => $fieldinfo2id));
                                $recorduserinfo->data = $vetname;

                                $DB->update_record('user_info_data', $recorduserinfo);
                            } else {

                                $DB->insert_record('user_info_data', $recorduserinfo);
                            }
                        }

                        $compteurvet++;
                    }
                }
            }
        }
    }

    if (isset($listexistence)) {

        foreach ($listexistence as $tempexistence) {

            if ($tempexistence->stillexists == 0) {

                echo "Désinscription de l'utilisateur $tempexistence->userid "
                        . "de la cohorte $cohortid Cas 2\n";

                cohort_remove_member($tempexistence->cohortid, $tempexistence->userid);

                echo "Utilisateur désinscrit\n";
            }
        }
    }
}

// Cohorte Tous étudiants.

if (!$DB->record_exists('cohort', array('idnumber' => 1,
                'contextid' => context_system::instance()->id))) {

    $cohort = new stdClass();
    $cohort->contextid = context_system::instance()->id;
    $cohort->name = "Etudiants $CFG->thisyear";
    $cohort->idnumber = 1;
    $cohort->component = 'local_cohortmanager';
    $cohort->visible = 0;

    $cohortid = cohort_add_cohort($cohort);
} else {

    $cohort = $DB->get_record('cohort', array('idnumber' => 1,
                'contextid' => context_system::instance()->id));

    $cohortid = $cohort->id;

    if ($cohort->name != "Etudiants $CFG->thisyear") {

        $cohort->name = "Etudiants $CFG->thisyear";

        $cohort = $DB->update_record('cohort', $cohort);
    }
}

$listcohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

$listtempcohortmembers = array();

foreach ($listcohortmembers as $cohortmembers) {

    $tempcohortmember = new stdClass();
    $tempcohortmember->userid = $cohortmembers->userid;
    $tempcohortmember->stillexists = 0;

    $listtempcohortmembers[] = $tempcohortmember;
}

$xmldocall = new DOMDocument();
$fileopeningall = $xmldocall->load('/home/referentiel/DOKEOS_Etudiants_Inscriptions.xml');
if ($fileopeningall == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarall = new Domxpath($xmldocall);

    $anneunivsall = $xpathvarall->query("//Student/Annee_universitaire[@AnneeUniv=$CFG->thisyear]");

    foreach ($anneunivsall as $anneuniv) {

        $student = $anneuniv->parentNode;
        $username = $student->getAttribute('StudentUID');

        if ($DB->record_exists('user', array('username' => $username))) {

            $memberid = $DB->get_record('user',
                        array('username' => $username))->id;

            if ($DB->record_exists('cohort_members',
                        array('cohortid' => $cohortid, 'userid' => $memberid))) {

                foreach ($listtempcohortmembers as $tempcohortmember) {

                    if ($tempcohortmember->userid == $memberid) {

                        $tempcohortmember->stillexists = 1;
                    }
                }
            } else {

                echo "Inscription de l'utilisateur ".$username."\n";

                cohort_add_member($cohortid, $memberid);

                echo "Utilisateur inscrit\n";
            }
        }
    }

    if ($DB->record_exists('local_cohortmanager_info',
            array('cohortid' => $cohortid, 'teacherid' => null,
                'codeelp' => 1))) {

        // Update record.

        $cohortinfo = $DB->get_record('local_cohortmanager_info',
            array('cohortid' => $cohortid, 'teacherid' => null,
                'codeelp' => 1));

        $cohortinfo->timesynced = $timesync;

        $DB->update_record('local_cohortmanager_info', $cohortinfo);

    } else {

        $cohortinfo = new stdClass();
        $cohortinfo->cohortid = $cohortid;
        $cohortinfo->teacherid = null;
        $cohortinfo->codeelp = 1;
        $cohortinfo->timesynced = $timesync;
        $cohortinfo->typecohort = "allstudents";

        $DB->insert_record('local_cohortmanager_info', $cohortinfo);
    }

    if (isset($listtempcohortmembers)) {

        foreach ($listtempcohortmembers as $tempcohortmember) {

            if ($tempcohortmember->stillexists == 0) {

                echo "Désinscription de l'utilisateur $tempcohortmember->userid"
                        . " de la cohorte $cohortid Cas 3\n";

                cohort_remove_member($cohortid, $tempcohortmember->userid);

                echo "Utilisateur désinscrit\n";
            }
        }
    }

    $selectdeleteoldcohortinfo = "timesynced < $timesync";
    $DB->delete_records_select('local_cohortmanager_info', $selectdeleteoldcohortinfo);
}

// Cohorte Tous enseignants.

if (!$DB->record_exists('cohort', array('idnumber' => 2,
                'contextid' => context_system::instance()->id))) {

    $cohort = new stdClass();
    $cohort->contextid = context_system::instance()->id;
    $cohort->name = "Enseignants";
    $cohort->idnumber = 2;
    $cohort->component = 'local_cohortmanager';
    $cohort->visible = 0;

    $cohortid = cohort_add_cohort($cohort);
} else {

    $cohort = $DB->get_record('cohort', array('idnumber' => 2,
                'contextid' => context_system::instance()->id));

    $cohortid = $cohort->id;

    if ($cohort->name != "Enseignants") {

        $cohort->name = "Enseignants";

        $cohort = $DB->update_record('cohort', $cohort);
    }
}

$listcohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

$listtempcohortmembers = array();

foreach ($listcohortmembers as $cohortmembers) {

    $tempcohortmember = new stdClass();
    $tempcohortmember->userid = $cohortmembers->userid;
    $tempcohortmember->stillexists = 0;

    $listtempcohortmembers[] = $tempcohortmember;
}

$xmldocall = new DOMDocument();
$fileopeningall = $xmldocall->load('/home/referentiel/DOKEOS_Enseignants_Affectations.xml');
if ($fileopeningall == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarall = new Domxpath($xmldocall);

    $teachers = $xpathvarall->query("//Teacher");

    foreach ($teachers as $teacher) {

        $username = $teacher->getAttribute('StaffUID');

        if ($DB->record_exists('user', array('username' => $username))) {

            $memberid = $DB->get_record('user',
                        array('username' => $username))->id;

            if ($DB->record_exists('cohort_members',
                        array('cohortid' => $cohortid, 'userid' => $memberid))) {

                foreach ($listtempcohortmembers as $tempcohortmember) {

                    if ($tempcohortmember->userid == $memberid) {

                        $tempcohortmember->stillexists = 1;
                    }
                }
            } else {

                echo "Inscription de l'utilisateur ".$username."\n";

                cohort_add_member($cohortid, $memberid);

                echo "Utilisateur inscrit\n";
            }
        }
    }

    if ($DB->record_exists('local_cohortmanager_info',
            array('cohortid' => $cohortid, 'teacherid' => null,
                'codeelp' => 2))) {

        // Update record.

        $cohortinfo = $DB->get_record('local_cohortmanager_info',
            array('cohortid' => $cohortid, 'teacherid' => null,
                'codeelp' => 2));

        $cohortinfo->timesynced = $timesync;

        $DB->update_record('local_cohortmanager_info', $cohortinfo);

    } else {

        $cohortinfo = new stdClass();
        $cohortinfo->cohortid = $cohortid;
        $cohortinfo->teacherid = null;
        $cohortinfo->codeelp = 2;
        $cohortinfo->timesynced = $timesync;
        $cohortinfo->typecohort = "allteachers";

        $DB->insert_record('local_cohortmanager_info', $cohortinfo);
    }

    if (isset($listtempcohortmembers)) {

        foreach ($listtempcohortmembers as $tempcohortmember) {

            if ($tempcohortmember->stillexists == 0) {

                echo "Désinscription de l'utilisateur $tempcohortmember->userid"
                        . " de la cohorte $cohortid Cas 3\n";

                cohort_remove_member($cohortid, $tempcohortmember->userid);

                echo "Utilisateur désinscrit\n";
            }
        }
    }

    $selectdeleteoldcohortinfo = "timesynced < $timesync";
    $DB->delete_records_select('local_cohortmanager_info', $selectdeleteoldcohortinfo);
}

// Cohorte Tous personnels.

if (!$DB->record_exists('cohort', array('idnumber' => 3,
                'contextid' => context_system::instance()->id))) {

    $cohort = new stdClass();
    $cohort->contextid = context_system::instance()->id;
    $cohort->name = "Personnels";
    $cohort->idnumber = 3;
    $cohort->component = 'local_cohortmanager';
    $cohort->visible = 0;

    $cohortid = cohort_add_cohort($cohort);
} else {

    $cohort = $DB->get_record('cohort', array('idnumber' => 3,
                'contextid' => context_system::instance()->id));

    $cohortid = $cohort->id;

    if ($cohort->name != "Personnels") {

        $cohort->name = "Personnels";

        $cohort = $DB->update_record('cohort', $cohort);
    }
}

$listcohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

$listtempcohortmembers = array();

foreach ($listcohortmembers as $cohortmembers) {

    $tempcohortmember = new stdClass();
    $tempcohortmember->userid = $cohortmembers->userid;
    $tempcohortmember->stillexists = 0;

    $listtempcohortmembers[] = $tempcohortmember;
}

$xmldocall = new DOMDocument();
$fileopeningall = $xmldocall->load('/home/referentiel/sefiap_personnel_composante.xml');
if ($fileopeningall == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarall = new Domxpath($xmldocall);

    $staffs = $xpathvarall->query("//Composante/Service/Individu");

    foreach ($staffs as $staff) {

        $username = $staff->getAttribute('UID');

        if ($DB->record_exists('user', array('username' => $username))) {

            $memberid = $DB->get_record('user',
                        array('username' => $username))->id;

            if ($DB->record_exists('cohort_members',
                        array('cohortid' => $cohortid, 'userid' => $memberid))) {

                foreach ($listtempcohortmembers as $tempcohortmember) {

                    if ($tempcohortmember->userid == $memberid) {

                        $tempcohortmember->stillexists = 1;
                    }
                }
            } else {

                echo "Inscription de l'utilisateur ".$username."\n";

                cohort_add_member($cohortid, $memberid);

                echo "Utilisateur inscrit\n";
            }
        }
    }

    if ($DB->record_exists('local_cohortmanager_info',
            array('cohortid' => $cohortid, 'teacherid' => null,
                'codeelp' => 3))) {

        // Update record.

        $cohortinfo = $DB->get_record('local_cohortmanager_info',
            array('cohortid' => $cohortid, 'teacherid' => null,
                'codeelp' => 3));

        $cohortinfo->timesynced = $timesync;

        $DB->update_record('local_cohortmanager_info', $cohortinfo);

    } else {

        $cohortinfo = new stdClass();
        $cohortinfo->cohortid = $cohortid;
        $cohortinfo->teacherid = null;
        $cohortinfo->codeelp = 3;
        $cohortinfo->timesynced = $timesync;
        $cohortinfo->typecohort = "allstaffs";

        $DB->insert_record('local_cohortmanager_info', $cohortinfo);
    }

    if (isset($listtempcohortmembers)) {

        foreach ($listtempcohortmembers as $tempcohortmember) {

            if ($tempcohortmember->stillexists == 0) {

                echo "Désinscription de l'utilisateur $tempcohortmember->userid"
                        . " de la cohorte $cohortid Cas 3\n";

                cohort_remove_member($cohortid, $tempcohortmember->userid);

                echo "Utilisateur désinscrit\n";
            }
        }
    }

    $selectdeleteoldcohortinfo = "timesynced < $timesync";
    $DB->delete_records_select('local_cohortmanager_info', $selectdeleteoldcohortinfo);
}

// Cohortes de services.

$sqllistcohortsservices = "SELECT distinct cohortid FROM {local_cohortmanager_info} WHERE "
        . "(typecohort LIKE 'service')";

$listcohortsservicesdb = $DB->get_records_sql($sqllistcohortsservices);

$listexistenceservice = array();

foreach ($listcohortsservicesdb as $cohortservicedb) {

    $listmembersdb = $DB->get_records('cohort_members',
            array('cohortid' => $cohortservicedb->cohortid));

    foreach ($listmembersdb as $memberdb) {

        $tempexistence = new stdClass();
        $tempexistence->cohortid = $cohortservicedb->cohortid;
        $tempexistence->userid = $memberdb->id;
        $tempexistence->stillexists = 0;

        $listexistenceservice[] = $tempexistence;
    }
}

$xmldocservice = new DOMDocument();
$fileopeningservice = $xmldocservice->load('/home/referentiel/sefiap_personnel_composante.xml');
if ($fileopeningservice == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $idnumberservicecentraux = $CFG->yearprefix."-8SC";
    $categoryservicecentrauxid = $DB->get_record('course_categories',
            array('idnumber' => $idnumberservicecentraux))->id;
    $contextidservicecentraux = context_coursecat::instance($categoryservicecentrauxid)->id;

    $xpathvarservice = new Domxpath($xmldocservice);

    $services = $xpathvarservice->query("//Composante/Service");

    foreach ($services as $service) {

        $servicecode = $service->getAttribute('COD_SERVICE');
        $cohortcode = $CFG->yearprefix."-8SC-8".$servicecode;

        if (substr($servicecode, 0, 1) == '3') {

            if (!$DB->record_exists('cohort', array('idnumber' => $cohortcode,
                'contextid' => $contextidservicecentraux))) {

                $cohort = new stdClass();
                $cohort->contextid = $contextidservicecentraux;
                $cohort->name = $service->getAttribute('LL_SERVICE');
                $cohort->idnumber = $cohortcode;
                $cohort->component = 'local_cohortmanager';

                echo "La cohorte ".$cohort->name." n'existe pas\n";

                $cohortid = cohort_add_cohort($cohort);

                echo "Elle est créée.\n";
            } else {

                $cohortid = $DB->get_record('cohort', array('idnumber' => $cohortcode,
                    'contextid' => $contextidservicecentraux))->id;
            }

            // Ici, rajouter l'entrée dans local_cohortmanager_info.

            if ($DB->record_exists('local_cohortmanager_info',
                    array('cohortid' => $cohortid,
                        'codeelp' => $cohortcode))) {

                // Update record.

                $cohortinfo = $DB->get_record('local_cohortmanager_info',
                    array('cohortid' => $cohortid,
                        'codeelp' => $cohortcode));

                $cohortinfo->timesynced = $timesync;

                $DB->update_record('local_cohortmanager_info', $cohortinfo);

            } else {

                $cohortinfo = new stdClass();
                $cohortinfo->cohortid = $cohortid;
                $cohortinfo->teacherid = null;
                $cohortinfo->codeelp = $cohortcode;
                $cohortinfo->timesynced = $timesync;
                $cohortinfo->typecohort = "service";

                $DB->insert_record('local_cohortmanager_info', $cohortinfo);
            }

            $listcohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

            $listtempcohortmembers = array();

            foreach ($listcohortmembers as $cohortmembers) {

                $tempcohortmember = new stdClass();
                $tempcohortmember->userid = $cohortmembers->userid;
                $tempcohortmember->stillexists = 0;

                $listtempcohortmembers[] = $tempcohortmember;
            }

            foreach ($service->childNodes as $servicemember) {

                if ($servicemember->nodeType !== 1 ) {
                    continue;
                }

                $username = $servicemember->getAttribute('UID');

                if ($DB->record_exists('user', array('username' => $username))) {

                    $memberid = $DB->get_record('user',
                                array('username' => $username))->id;

                    if ($DB->record_exists('cohort_members',
                                array('cohortid' => $cohortid, 'userid' => $memberid))) {

                        foreach ($listtempcohortmembers as $tempcohortmember) {

                            if ($tempcohortmember->userid == $memberid) {

                                $tempcohortmember->stillexists = 1;
                            }
                        }
                    } else {

                        echo "Inscription de l'utilisateur ".$username."\n";

                        cohort_add_member($cohortid, $memberid);

                        echo "Utilisateur inscrit\n";
                    }
                }
            }

            if (isset($listtempcohortmembers)) {

                foreach ($listtempcohortmembers as $tempcohortmember) {

                    if ($tempcohortmember->stillexists == 0) {

                        echo "Désinscription de l'utilisateur $tempcohortmember->userid "
                                . "de la cohorte $cohortid Cas 4\n";

                        cohort_remove_member($cohortid, $tempcohortmember->userid);

                        echo "Utilisateur désinscrit\n";
                    }
                }
            }
        }
    }
}

// Cohortes des faux vacataires.

$xmldocfakevac = new DOMDocument();
$fileopeningfakevac = $xmldocfakevac->load('/home/referentiel/dokeos_vac_tempo.xml');
if ($fileopeningfakevac == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarfakevac = new Domxpath($xmldocfakevac);

    $listtreatedgroupsfakevac = array();

    $groupsfakevac = $xpathvarfakevac->query('//Structure_diplome/Teacher/Cours/Group');

    foreach ($groupsfakevac as $group) {

        $vet = $group->parentNode->parentNode->parentNode;
        $idvet = $vet->getAttribute('Etape');
        $idvetyear = "$CFG->yearprefix-$idvet";

        $cours = $group->parentNode;
        $courselp = $cours->getAttribute('element_pedagogique');

        $groupcode = $group->getAttribute('GroupCode');
        $groupname = $group->getAttribute('GroupName');

        $cohortcode = "$CFG->yearprefix-".$idvet."-".$groupcode;

        if (!in_array($cohortcode, $listtreatedgroupsfakevac)) {

            if ($courselp != "" && $groupcode != "") {

                if ($DB->record_exists('course_categories', array('idnumber' => $idvetyear))) {

                    $category = $DB->get_record('course_categories', array('idnumber' => $idvetyear));
                    $parentcategory = $DB->get_record('course_categories', array('id' => $category->parent));
                    $contextidparentcategory = $DB->get_record('context',
                            array('contextlevel' => 40, 'instanceid' => $parentcategory->id))->id;

                    $tableteachername = array();

                    if ($DB->record_exists('cohort', array('idnumber' => $cohortcode,
                            'contextid' => $contextidparentcategory))) {

                        $cohort = $DB->get_record('cohort', array('idnumber' => $cohortcode,
                                'contextid' => $contextidparentcategory));

                        $cohortid = $cohort->id;

                        if ($cohort->name != $groupname." ($idvet-$groupcode)") {

                            if (!$DB->record_exists('local_cohortmanager_names',
                                    array('cohortid' => $cohortid, 'codeelp' => $cohortcode))) {

                                $cohortnameentry = new stdClass();
                                $cohortnameentry->cohortid = $cohortid;
                                $cohortnameentry->codeelp = $cohortcode;
                                $cohortnameentry->cohortname = $groupname;

                                $DB->insert_record('local_cohortmanager_names', $cohortnameentry);

                            } else if (!$DB->record_exists('local_cohortmanager_names',
                                    array('cohortid' => $cohortid, 'codeelp' => $cohortcode,
                                        'cohortname' => $groupname))) {

                                $cohortnameentry = $DB->get_record('local_cohortmanager_names',
                                        array('cohortid' => $cohortid, 'codeelp' => $cohortcode));

                                $cohortnameentry->cohortname = $groupname;

                                $DB->update_record('local_cohortmanager_names', $cohortnameentry);
                            }
                        }

                        echo "La cohorte ".$cohort->name." existe\n";

                        $listcohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohortid));

                        $listtempcohortmembers = array();

                        foreach ($listcohortmembers as $cohortmembers) {

                            $tempcohortmember = new stdClass();
                            $tempcohortmember->userid = $cohortmembers->userid;
                            $tempcohortmember->stillexists = 0;

                            $listtempcohortmembers[] = $tempcohortmember;
                        }

                        foreach ($group->childNodes as $groupmember) {

                            if ($groupmember->nodeType !== 1 ) {
                                    continue;
                            }

                            $username = $groupmember->getAttribute('UID_etu');

                            if ($DB->record_exists('user', array('username' => $username))) {

                                $memberid = $DB->get_record('user', array('username' => $username))->id;

                                if ($DB->record_exists('cohort_members',
                                        array('cohortid' => $cohortid, 'userid' => $memberid))) {

                                    foreach ($listtempcohortmembers as $tempcohortmember) {

                                        if ($tempcohortmember->userid == $memberid) {

                                                $tempcohortmember->stillexists = 1;
                                        }
                                    }
                                } else {

                                    echo "Inscription de l'utilisateur ".$username."\n";

                                    cohort_add_member($cohortid, $memberid);

                                    echo "Utilisateur inscrit\n";
                                }
                            }
                        }

                        if (isset($listtempcohortmembers)) {

                            foreach ($listtempcohortmembers as $tempcohortmember) {

                                if ($tempcohortmember->stillexists == 0) {

                                    echo "Désinscription de l'utilisateur $tempcohortmember->userid "
                                                    . "de la cohorte $cohortid Cas 5\n";

                                    cohort_remove_member($cohortid, $tempcohortmember->userid);

                                    echo "Utilisateur désinscrit\n";
                                }
                            }
                        }
                    } else {

                        $cohort = new stdClass();
                        $cohort->contextid = $contextidparentcategory;
                        $cohort->name = $group->getAttribute('GroupName')." ($idvet-$groupcode)";
                        $cohort->idnumber = $cohortcode;
                        $cohort->component = 'local_cohortmanager';

                        echo "La cohorte ".$cohort->name." n'existe pas\n";

                        $cohortid = cohort_add_cohort($cohort);

                        echo "Elle est créée.\n";

                        $group->removeChild($group->lastChild);
                        $groupmembers = $group->childNodes;

                        foreach ($groupmembers as $groupmember) {

                            if ($groupmember->nodeType !== 1 ) {
                                    continue;
                            }

                            $username = $groupmember->getAttribute('StudentUID');

                            if ($DB->record_exists('user', array('username' => $username))) {

                                echo "Inscription de l'utilisateur ".$username."\n";

                                $memberid = $DB->get_record('user',
                                                array('username' => $username))->id;

                                cohort_add_member($cohortid, $memberid);

                                echo "Utilisateur inscrit\n";
                            }
                        }
                    }

                    $listtreatedgroupsfakevac[] = $cohortcode;
                }
            }
        }
    }
}

// Cohortes de profs de composantes.

$sqllistcohortscomposanteprof = "SELECT distinct cohortid FROM {local_cohortmanager_info} WHERE "
        . "typecohort LIKE 'composanteprof'";

$listcohortscomposanteprof = $DB->get_records_sql($sqllistcohortscomposanteprof);

$listexistenceprof = array();

foreach ($listcohortscomposanteprof as $cohortcomposanteprofdb) {

    $listmembersdb = $DB->get_records('cohort_members',
            array('cohortid' => $cohortcomposanteprofdb->cohortid));

    foreach ($listmembersdb as $memberdb) {

        $tempexistence = new stdClass();
        $tempexistence->cohortid = $cohortcomposanteprofdb->cohortid;
        $tempexistence->userid = $memberdb->userid;
        $tempexistence->stillexists = 0;

        $listexistenceprof[] = $tempexistence;
    }
}

$xmldoccomposanteprof = new DOMDocument();
$fileopeningcomposanteprof = $xmldoccomposanteprof->load('/home/referentiel/'
        . 'DOKEOS_Enseignants_Affectations.xml');
if ($fileopeningcomposanteprof == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarcomposanteprof = new Domxpath($xmldoccomposanteprof);

    $diplomesprofs = $xpathvarcomposanteprof->query("//Teacher/Diplomes");

    foreach ($diplomesprofs as $diplomeprof) {

        $composantecode = substr($diplomeprof->getAttribute('CodeEtape'), 0, 1);
        $cohortcomposantecode = $CFG->yearprefix."-T".$composantecode;
        $categorycode = $CFG->yearprefix."-".$composantecode;

        if ($DB->record_exists('course_categories', array('idnumber' => $categorycode))) {

            $composantecategory = $DB->get_record('course_categories',
                    array('idnumber' => $categorycode));

            $contextidcomposantecategory = $DB->get_record('context',
                    array('instanceid' => $composantecategory->id, 'contextlevel' => CONTEXT_COURSECAT))->id;

            if (!$DB->record_exists('cohort',
                    array('idnumber' => $cohortcomposantecode, 'contextid' => $contextidcomposantecategory))) {

                $cohortcomposante = new stdClass();
                $cohortcomposante->contextid = $contextidcomposantecategory;
                $cohortcomposante->name = 'Enseignants de '.substr($composantecategory->name, 4);
                $cohortcomposante->idnumber = $cohortcomposantecode;
                $cohortcomposante->component = 'local_cohortmanager';

                echo "La cohorte ".$cohortcomposante->name." n'existe pas\n";

                $cohortcomposanteid = cohort_add_cohort($cohortcomposante);

                echo "Elle est créée.\n";
            } else {

                $cohortcomposanteid = $DB->get_record('cohort',
                        array('idnumber' => $cohortcomposantecode, 'contextid' => $contextidcomposantecategory))->id;
            }

            // Ici, rajouter l'entrée dans local_cohortmanager_info.

            if ($DB->record_exists('local_cohortmanager_info',
                    array('cohortid' => $cohortcomposanteid, 'codeelp' => 0))) {

                // Update record.

                $cohortcomposanteinfo = $DB->get_record('local_cohortmanager_info',
                    array('cohortid' => $cohortcomposanteid, 'codeelp' => 0));

                $cohortcomposanteinfo->timesynced = $timesync;

                $DB->update_record('local_cohortmanager_info', $cohortcomposanteinfo);

            } else {

                $cohortcomposanteinfo = new stdClass();
                $cohortcomposanteinfo->cohortid = $cohortcomposanteid;
                $cohortcomposanteinfo->teacherid = null;
                $cohortcomposanteinfo->codeelp = 0;
                $cohortcomposanteinfo->timesynced = $timesync;
                $cohortcomposanteinfo->typecohort = "composanteprof";

                $DB->insert_record('local_cohortmanager_info', $cohortcomposanteinfo);
            }

            $teacher = $diplomeprof->parentNode;
            $username = $teacher->getAttribute('StaffUID');

            if ($DB->record_exists('user', array('username' => $username))) {

                $user = $DB->get_record('user', array('username' => $username));

                if (!$DB->record_exists('cohort_members',
                        array('cohortid' => $cohortcomposanteid, 'userid' => $user->id))) {

                    echo "Inscription de l'utilisateur ".$username."\n";

                    cohort_add_member($cohortcomposanteid, $user->id);

                    echo "Utilisateur inscrit\n";
                } else {

                    foreach ($listexistence as $tempexistence) {

                        if ($tempexistence->userid == $user->id &&
                                $tempexistence->cohortid == $cohortcomposanteid) {

                            $tempexistence->stillexists = 1;
                        }
                    }
                }
            }
        }
    }

    if (isset($listexistenceprof)) {

        foreach ($listexistenceprof as $tempexistence) {

            if ($tempexistence->stillexists == 0) {

                echo "Désinscription de l'utilisateur $tempexistence->userid "
                        . "de la cohorte $cohortid Cas 6\n";

                cohort_remove_member($tempexistence->cohortid, $tempexistence->userid);

                echo "Utilisateur désinscrit\n";
            }
        }
    }
}

// Cohortes de niveaux.

$sqllistcohortsniveaux = "SELECT distinct cohortid FROM {local_cohortmanager_info} WHERE "
        . "typecohort LIKE 'niveau'";

$listcohortsniveauxdb = $DB->get_records_sql($sqllistcohortsniveaux);

$listexistenceniveaux = array();

foreach ($listcohortsniveauxdb as $cohortniveaudb) {

    $listmembersdb = $DB->get_records('cohort_members',
            array('cohortid' => $cohortniveaudb->cohortid));

    foreach ($listmembersdb as $memberdb) {

        $tempexistence = new stdClass();
        $tempexistence->cohortid = $cohortvetdb->cohortid;
        $tempexistence->userid = $memberdb->userid;
        $tempexistence->stillexists = 0;

        $listexistenceniveaux[] = $tempexistence;
    }
}

$xmldocniveau = new DOMDocument();
$fileopeningniveau = $xmldocniveau->load('/home/referentiel/DOKEOS_Etudiants_Inscriptions.xml');
if ($fileopeningniveau == false) {
    echo "Impossible de lire le fichier source.\n";
} else {

    $xpathvarniveau = new Domxpath($xmldocniveau);

    $listinscriptions = $xpathvarniveau->query("//Student/"
            . "Annee_universitaire[@AnneeUniv=$CFG->thisyear]/Inscriptions");

    // Ajouter le code ici.

    foreach ($listinscriptions as $inscription) {

        $vetcode = $CFG->yearprefix."-".$inscription->getAttribute('CodeEtape');

        if ($DB->record_exists('course_categories', array('idnumber' => $vetcode))) {

            $vetcategory = $DB->get_record('course_categories', array('idnumber' => $vetcode));
            $niveaucategory = $DB->get_record('course_categories',
                    array('id' => $vetcategory->parent));
            $ufrcategory = $DB->get_record('course_categories',
                    array('id' => $niveaucategory->parent));

            $cohortcode = substr($niveaucategory->idnumber, 0, 5)."-S".
                    substr($niveaucategory->idnumber, 5);


            $contextidniveaucategory = $DB->get_record('context',
                    array('instanceid' => $niveaucategory->id,
                        'contextlevel' => CONTEXT_COURSECAT))->id;

            if (!$DB->record_exists('cohort', array('idnumber' => $cohortcode,
                'contextid' => $contextidniveaucategory))) {

                $cohortniveau = new stdClass();
                $cohortniveau->contextid = $contextidniveaucategory;
                $cohortniveau->name = 'Tous les étudiants de '.substr($ufrcategory->name, 4).' '
                        . ''.$niveaucategory->name;
                $cohortniveau->idnumber = $cohortcode;
                $cohortniveau->component = 'local_cohortmanager';

                echo "La cohorte ".$cohortniveau->name." n'existe pas\n";

                $cohortniveauid = cohort_add_cohort($cohortniveau);

                echo "Elle est créée.\n";
            } else {

                $cohortniveauid = $DB->get_record('cohort',
                        array('idnumber' => $cohortcode,
                            'contextid' => $contextidniveaucategory))->id;
            }

            // Ici, rajouter l'entrée dans local_cohortmanager_info.

            if ($DB->record_exists('local_cohortmanager_info',
                    array('cohortid' => $cohortniveauid,
                        'codeelp' => 0))) {

                // Update record.

                $cohortniveauinfo = $DB->get_record('local_cohortmanager_info',
                    array('cohortid' => $cohortniveauid,
                        'codeelp' => 0));

                $cohortniveauinfo->timesynced = $timesync;

                $DB->update_record('local_cohortmanager_info', $cohortniveauinfo);

            } else {

                $cohortniveauinfo = new stdClass();
                $cohortniveauinfo->cohortid = $cohortniveauid;
                $cohortniveauinfo->teacherid = null;
                $cohortniveauinfo->codeelp = 0;
                $cohortniveauinfo->timesynced = $timesync;
                $cohortniveauinfo->typecohort = "niveau";

                $DB->insert_record('local_cohortmanager_info', $cohortniveauinfo);
            }

            $studentnode = $inscription->parentNode->parentNode;
            $username = $studentnode->getAttribute('StudentUID');

            if ($DB->record_exists('user', array('username' => $username))) {

                $user = $DB->get_record('user', array('username' => $username));

                if (!$DB->record_exists('cohort_members',
                        array('cohortid' => $cohortniveauid, 'userid' => $user->id))) {

                    echo "Inscription de l'utilisateur ".$username."\n";

                    cohort_add_member($cohortniveauid, $user->id);

                    echo "Utilisateur inscrit\n";
                } else {

                    foreach ($listexistence as $tempexistence) {

                        if ($tempexistence->userid == $user->id &&
                                $tempexistence->cohortid == $cohortniveauid) {

                            $tempexistence->stillexists = 1;
                        }
                    }
                }
            }
        }
    }

    if (isset($listexistence)) {

        foreach ($listexistence as $tempexistence) {

            if ($tempexistence->stillexists == 0) {

                echo "Désinscription de l'utilisateur $tempexistence->userid "
                        . "de la cohorte $cohortid Cas 7\n";

                cohort_remove_member($tempexistence->cohortid, $tempexistence->userid);

                echo "Utilisateur désinscrit\n";
            }
        }
    }
}
