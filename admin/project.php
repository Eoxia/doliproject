<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2020 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    doliproject/admin/about.php
 * \ingroup doliproject
 * \brief   About page of module Doliproject.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . "/core/class/html.formprojet.class.php";

require_once '../lib/doliproject.lib.php';

// Translations
$langs->loadLangs(array("errors", "admin", "doliproject@doliproject"));

// Access control
if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');


/*
 * Actions
 */

if (($action == 'update' && ! GETPOST("cancel", 'alpha')) || ($action == 'updateedit')) {
	$HRProject = GETPOST('HRProject', 'none');
	$HRProject = preg_split('/_/', $HRProject);

	dolibarr_set_const($db, "DOLIPROJECT_HR_PROJECT", $HRProject[0], 'integer', 0, '', $conf->entity);
	setEventMessages($langs->transnoentities('TicketProjectUpdated'), array());

	if ($action != 'updateedit' && ! $error) {
		header("Location: " . $_SERVER["PHP_SELF"]);
		exit;
	}
}

/*
 * View
 */

$form = new Form($db);
if ( ! empty($conf->projet->enabled)) { $formproject = new FormProjets($db); }

$page_name = "DoliprojectAbout";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_doliproject@doliproject');

// Configuration header
$head = doliprojectAdminPrepareHead();
dol_fiche_head($head, 'projecttasks', '', -1, 'doliproject@doliproject');
dol_get_fiche_head($head, 'projecttasks', '', -1, 'doliproject@doliproject');

// Project
print load_fiche_titre($langs->transnoentities("HRProject"), '', '');

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '" name="project_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent editmode">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Name") . '</td>';
print '<td>' . $langs->transnoentities("SelectProject") . '</td>';
print '<td>' . $langs->transnoentities("Action") . '</td>';
print '</tr>';

if ( ! empty($conf->projet->enabled)) {
	$langs->load("projects");
	print '<tr class="oddeven"><td><label for="TSProject">' . $langs->transnoentities("HRProject") . '</label></td><td>';
	$numprojet = $formproject->select_projects(0,  $conf->global->DOLIPROJECT_HR_PROJECT, 'HRProject', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 'maxwidth500');
	print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?&action=create&status=1&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities("AddProject") . '"></span></a>';
	print '<td><input type="submit" class="button" name="save" value="' . $langs->transnoentities("Save") . '">';
	print '</td></tr>';
}

print '</table>';
print '</form>';

//Time spent
print load_fiche_titre($langs->transnoentities("TimeSpent"), '', '');

print '<table class="noborder centpercent editmode">';

print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Parameters") . '</td>';
print '<td class="center">' . $langs->transnoentities("Status") . '</td>';
print '<td class="center">' . $langs->transnoentities("Action") . '</td>';
print '<td class="center">' . $langs->transnoentities("ShortInfo") . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>' . $langs->transnoentities("SpendMoreTimeThanPlanned") . '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLIPROJECT_SPEND_MORE_TIME_THAN_PLANNED');
print '</td>';
print '<td class="center">';
print '';
print '</td>';
print '<td class="center">';
print $form->textwithpicto('', $langs->transnoentities("SpendMoreTimeThanPlannedHelp"), 1, 'help');
print '</td>';
print '</tr>';

print '</table>';

// Page end
llxFooter();
$db->close();
