<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       view/timesheet/timesheet_card.php
 *		\ingroup    doliproject
 *		\brief      Page to create/edit/view timesheet
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

require_once __DIR__ . '/../../class/timesheet.class.php';
require_once __DIR__ . '/../../lib/doliproject_timesheet.lib.php';
require_once __DIR__ . '/../../lib/doliproject_function.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("doliproject@doliproject", "other"));

// Get parameters
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetcard'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$lineid              = GETPOST('lineid', 'int');
$year                = (GETPOST("year", 'int') ? GETPOST("year", "int") : date("Y"));
$month               = (GETPOST("month", 'int') ?GETPOST("month", "int") : date("m"));

// Initialize technical objects
$object      = new TimeSheet($db);
$extrafields = new ExtraFields($db);
$project     = new Project($db);

$hookmanager->initHooks(array('timesheetcard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

$permissiontoread   = $user->rights->doliproject->timesheet->read;
$permissiontoadd    = $user->rights->doliproject->timesheet->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->doliproject->timesheet->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
$permissionnote     = $user->rights->doliproject->timesheet->write; // Used by the include of actions_setnotes.inc.php

$upload_dir = $conf->doliproject->multidir_output[isset($object->entity) ? $object->entity : 1];

// Security check (enable the most restrictive one)
if (empty($conf->doliproject->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$error = 0;

	$backurlforlist = dol_buildpath('/doliproject/view/timesheet/timesheet_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/doliproject/view/timesheet/timesheet_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	$triggermodname = 'TIMESHEET_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	// Action validate object
	if ($action == 'confirm_validate2' && $confirm == 'yes' && $permissiontoadd) {
		$result = $object->validate($user);
		if ($result >= 0) {
			// Define output language
//			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
//				if (method_exists($object, 'generateDocument')) {
//					$outputlangs = $langs;
//					$newlang = '';
//					if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
//						$newlang = GETPOST('lang_id', 'aZ09');
//					}
//					if ($conf->global->MAIN_MULTILANGS && empty($newlang)) {
//						$newlang = $object->thirdparty->default_lang;
//					}
//					if (!empty($newlang)) {
//						$outputlangs = new Translate("", $conf);
//						$outputlangs->setDefaultLang($newlang);
//					}
//
//					$ret = $object->fetch($id); // Reload to get new records
//
//					$model = $object->model_pdf;
//
//					$retgen = $object->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);
//					if ($retgen < 0) {
//						setEventMessages($object->error, $object->errors, 'warnings');
//					}
//				}
//			}
		} else {
			$error++;
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}

	// Actions when printing a doc from card
	include DOL_DOCUMENT_ROOT.'/core/actions_printing.inc.php';

	// Action to move up and down lines of object
	include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

	// Action to build doc
	if ($action == 'builddoc' && $permissiontoadd) {
		if (is_numeric(GETPOST('model', 'alpha'))) {
			$error = $langs->trans("ErrorFieldRequired", $langs->transnoentities("Model"));
		} else {
			// Reload to get all modified line records and be ready for hooks
			$ret = $object->fetch($id);
			$ret = $object->fetch_thirdparty();
			/*if (empty($object->id) || ! $object->id > 0)
			{
				dol_print_error('Object must have been loaded by a fetch');
				exit;
			}*/

			// Save last template used to generate document
//			if (GETPOST('model', 'alpha')) {
//				$object->setDocModel($user, GETPOST('model', 'alpha'));
//			}

			// Special case to force bank account
			//if (property_exists($object, 'fk_bank'))
			//{
			if (GETPOST('fk_bank', 'int')) {
				// this field may come from an external module
				$object->fk_bank = GETPOST('fk_bank', 'int');
			} elseif (!empty($object->fk_account)) {
				$object->fk_bank = $object->fk_account;
			}
			//}

			$outputlangs = $langs;
			$newlang = '';

			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) {
				$newlang = GETPOST('lang_id', 'aZ09');
			}
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && isset($object->thirdparty->default_lang)) {
				$newlang = $object->thirdparty->default_lang; // for proposal, order, invoice, ...
			}
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && isset($object->default_lang)) {
				$newlang = $object->default_lang; // for thirdparty
			}
			if (!empty($newlang)) {
				$outputlangs = new Translate("", $conf);
				$outputlangs->setDefaultLang($newlang);
			}

			// To be sure vars is defined
			if (empty($hidedetails)) {
				$hidedetails = 0;
			}
			if (empty($hidedesc)) {
				$hidedesc = 0;
			}
			if (empty($hideref)) {
				$hideref = 0;
			}
			if (empty($moreparams)) {
				$moreparams = null;
			}

			$moreparams['object'] = $object;

			$result = $object->generateDocument(GETPOST('model'), $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);

			if ($result <= 0) {
				setEventMessages($object->error, $object->errors, 'errors');
				$action = '';
			} else {
				if (empty($donotredirect)) {	// This is set when include is done by bulk action "Bill Orders"
					setEventMessages($langs->trans("FileGenerated"), null);

					$urltoredirect = $_SERVER['REQUEST_URI'];
					$urltoredirect = preg_replace('/#builddoc$/', '', $urltoredirect);
					$urltoredirect = preg_replace('/action=builddoc&?/', '', $urltoredirect); // To avoid infinite loop

					header('Location: '.$urltoredirect.'#builddoc');
					exit;
				}
			}
		}
	}

	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOST('fk_soc', 'int'), '', '', 'date', '', $user, $triggermodname);
	}

	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOST('projectid', 'int'));
	}

	// Actions to send emails
	$triggersendname = 'DOLIPROJECT_TIMESHEET_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_TIMESHEET_TO';
	$trackid = 'timesheet'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}

/*
 * View
 */

$form        = new Form($db);
$formfile    = new FormFile($db);
$formproject = new FormProjets($db);

$title    = $langs->trans("TimeSheet");
$help_url = '';
$morejs   = array("/doliproject/js/doliproject.js.php");
$morecss  = array("/doliproject/css/doliproject.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden($langs->trans('NotEnoughPermissions'), 0, 1);
		exit;
	}

	print load_fiche_titre($langs->trans("NewTimeSheet"), '', 'doliproject32px@doliproject');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head(array(), '');

	// Set some default values
	//if (! GETPOSTISSET('fieldname')) $_POST['fieldname'] = 'myvalue';

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	$object->fields['fk_project']['default']   = $conf->global->DOLIPROJECT_HR_PROJECT;
	$object->fields['fk_user_assign']['default'] = $user->id;
	if ($conf->global->DOLIPROJECT_TIMESHEET_PREFILL_DATE) {
		$firstday = dol_get_first_day($year, $month);
		$firstday = dol_getdate($firstday);

		$_POST['date_startday'] = $firstday['mday'];
		$_POST['date_startmonth'] = $firstday['mon'];
		$_POST['date_startyear'] = $firstday['year'];

		$lastday = dol_get_last_day($year, $month);
		$lastday = dol_getdate($lastday);

		$_POST['date_endday'] = $lastday['mday'];
		$_POST['date_endmonth'] = $lastday['mon'];
		$_POST['date_endyear'] = $lastday['year'];
	}
	$object->fields['note_public']['visible']  = 1;
	$object->fields['note_private']['visible'] = 1;

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("TimeSheet"), '', '');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	$object->fields['note_public']['visible']  = 1;
	$object->fields['note_private']['visible'] = 1;

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	$head = timesheetPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimeSheet"), -1, 'doliproject@doliproject');

	$formconfirm = '';

	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteTimeSheet'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	// Confirmation to delete line
	if ($action == 'deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
	}
	// Clone confirmation
	if ($action == 'clone') {
		// Create an array for form
		$formquestion = array();
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', $formquestion, 'yes', 1);
	}

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;

	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.dol_buildpath('/doliproject/view/timesheet/timesheet_list.php', 1).'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	// Thirdparty
	if (! empty($conf->societe->enabled)) {
		$object->fetch_thirdparty();
		$morehtmlref .= $langs->trans('ThirdParty') . ' : ' . (is_object($object->thirdparty) ? $object->thirdparty->getNomUrl(1) : '');
	}
	// Project
	 if (! empty($conf->projet->enabled)) {
		$langs->load("projects");
		$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';
		if (! empty($object->fk_project)) {
			$project->fetch($object->fk_project);
			$morehtmlref .= ': ' . $project->getNomUrl(1, '', 1);
		} else {
			$morehtmlref .= '';
		}
	}
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just before this field

	unset($object->fields['fk_project']);				// Hide field already shown in banner
	unset($object->fields['fk_soc']);					// Hide field already shown in banner

	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();


	/*
	 * Lines
	 */

	if (!empty($object->table_element_line)) {
		// Show object lines
		$result = $object->getLinesArray();

		print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOST('lineid', 'int')).'" method="POST">
		<input type="hidden" name="token" value="' . newToken().'">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id.'">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}

		if (!empty($object->lines)) {
			$object->printObjectLines($action, $mysoc, null, GETPOST('lineid', 'int'), 1);
		}

		// Form to add new line
		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
			if ($action != 'editline') {
				// Add products/services form
				$parameters = array();
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				if (empty($reshook))
					$object->formAddObjectLine(1, $mysoc, $soc);
			}
		}

		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '</table>';
		}
		print '</div>';

		print "</form>\n";
	}


	// Buttons for actions
	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
			$params = array(
				'attr' => array(
					'classOverride' => 'butActionRefused classfortooltip'
				)
			);

			// Modify
			print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd, ($object->status == $object::STATUS_DRAFT) ? '' : $params);

			// Validate
			if (empty($object->table_element_line) || (is_array($object->lines) && count($object->lines) > 0)) {
				print dolGetButtonAction($langs->trans('Validate'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate2&confirm=yes&token='.newToken(), '', $permissiontoadd, ($object->status == $object::STATUS_DRAFT) ? '' : $params);
			} else {
				$langs->load("errors");
				print dolGetButtonAction($langs->trans("ErrorAddAtLeastOneLineFirst"), $langs->trans("Validate"), 'default', '#', '', 0, $params);
			}

			// ReOpen
			print dolGetButtonAction($langs->trans('ReOpenDoli'), '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=confirm_setdraft&confirm=yes&token=' . newToken(), '', $permissiontoadd, ($object->status == $object::STATUS_VALIDATED) ? '' : $params);

			// Sign
			print dolGetButtonAction($langs->trans('Sign'), '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id, '', $permissiontoadd, ($object->status == $object::STATUS_VALIDATED) ? '' : $params);

			// Lock
			print dolGetButtonAction($langs->trans('Lock'), '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=close&token=' . newToken(), '', $permissiontoadd, ($object->status == $object::STATUS_LOCKED) ? '' : $params);

			// Send
			print dolGetButtonAction($langs->trans('SendMail'), '', 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=presend&mode=init&token='.newToken().'#formmailbeforetitle', '', $permissiontoadd, ($object->status == $object::STATUS_LOCKED) ? '' : $params);

			// Clone
			print dolGetButtonAction($langs->trans('ToClone'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.(!empty($object->socid)?'&socid='.$object->socid:'').'&action=clone&token='.newToken(), '', $permissiontoadd);

			// Delete (need delete permission, or if draft, just need create/modify permission)
			print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete || ($object->status == $object::STATUS_DRAFT && $permissiontoadd));
		}
		print '</div>'."\n";
	}

	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 1;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$dir_files = $object->element . 'document/' . $objref;
			$filedir = $upload_dir . '/' . $dir_files;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $permissiontoadd; // If you can read, you can build the PDF to read content
			$delallowed = $permissiontodelete; // If you can create/edit, you can remove a file on card

			print doliprojectshowdocuments('doliproject:TimeSheetDocument', $dir_files, $filedir, $urlsource, $genallowed, $delallowed, $conf->global->DOLIPROJECT_TIMESHEETDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, 0, '', '', '', $langs->defaultlang, $object, 0, 'remove_file', (($object->status == 2) ? 1 : 0), $langs->trans('TimeSheetMustBeLocked'));
		}

		print '</div><div class="fichehalfright">';

		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-list-alt imgforviewmode', dol_buildpath('/doliproject/view/timesheet/timesheet_agenda.php', 1).'?id='.$object->id);

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

		print '</div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'timesheet';
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->doliproject->dir_output;
	$trackid = 'timesheet'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();