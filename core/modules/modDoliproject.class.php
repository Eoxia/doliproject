<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2020 SuperAdmin
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
 * 	\defgroup   doliproject     Module Doliproject
 *  \brief      Doliproject module descriptor.
 *
 *  \file       htdocs/doliproject/core/modules/modDoliproject.class.php
 *  \ingroup    doliproject
 *  \brief      Description and activation file for module Doliproject
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Doliproject
 */
class modDoliproject extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		$this->numero = 436370; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module
		$this->rights_class 			= 'doliproject';
		$this->family 					= "other";
		$this->module_position 			= '90';
		$this->name 					= preg_replace('/^mod/i', '', get_class($this));
		$this->description 				= "DoliprojectDescription";
		$this->descriptionlong 			= "Doliproject description (Long)";
		$this->editor_name 				= 'Eoxia';
		$this->editor_url 				= 'https://eoxia.com';
		$this->version 					= '1.3.0';
		$this->const_name 				= 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto 					= 'doliproject256px@doliproject';

		$this->module_parts 			= array(
			'triggers' 					=> 1,
			'login' 					=> 0,
			'substitutions' 			=> 0,
			'menus' 					=> 0,
			'tpl' 						=> 0,
			'barcode' 					=> 0,
			'models' 					=> 0,
			'theme' 					=> 0,
			'css' 						=> array(),
			'js' => array("/doliproject/js/doliproject.js.php"),
			'hooks' 					=> array(
				  'data' 				=> array(
				      'invoicecard',
					  'ticketcard',
					  'projecttaskcard',
					  'projecttaskscard',
					  'tasklist',
					  'category',
					  'categoryindex',
					  'invoicereccard',
					  'cron',
					  'cronjoblist',
					  'projecttasktime'
				  ),
			),
			'moduleforexternal' => 0,
		);

		$this->dirs 					= array("/doliproject/temp");
		$this->config_page_url 			= array("setup.php@doliproject");
		$this->hidden 					= false;
		$this->depends 					= array('modProjet', 'modBookmark', 'modHoliday');
		$this->requiredby 				= array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith 			= array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->langfiles 				= array("doliproject@doliproject");
		$this->phpmin 					= array(5, 5); // Minimum version of PHP required by module
		$this->need_dolibarr_version 	= array(11, -3); // Minimum version of Dolibarr required by module
		$this->warnings_activation 		= array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext 	= array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->const 					= array();

		$this->const = array(
			// CONST CONFIGURATION
			1 => array('DOLIPROJECT_DEFAUT_TICKET_TIME', 'chaine', '15', 'Default Time', 0, 'current'),
			2 => array('DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS', 'integer', 1, '', 0, 'current'),
			3 => array('DOLIPROJECT_HR_PROJECT', 'integer', 0, '', 0, 'current'),
			4 => array('DOLIPROJECT_TIMESPENT_BOOKMARK_SET', 'integer', 0, '', 0, 'current'),
			5 => array('DOLIPROJECT_EXCEEDED_TIME_SPENT_COLOR', 'chaine', '#FF0000', '', 0, 'current'),
			6 => array('DOLIPROJECT_NOT_EXCEEDED_TIME_SPENT_COLOR', 'chaine', '#FFA500', '', 0, 'current'),
			7 => array('DOLIPROJECT_PERFECT_TIME_SPENT_COLOR', 'chaine', '#008000', '', 0, 'current'),
		);

		if (!isset($conf->doliproject) || !isset($conf->doliproject->enabled)) {
			$conf->doliproject = new stdClass();
			$conf->doliproject->enabled = 0;
		}

		$this->tabs = array();
		$this->tabs[] = array('data' => 'user:+workinghours:Horaires:doliproject@doliproject:1:/custom/doliproject/view/workinghours_card.php?id=__ID__'); // To add a new tab identified by code tabname1

		$this->dictionaries 			= array();
		$this->boxes 					= array();
		$this->cronjobs 				= array();
		$this->rights 					= array();

		// Add here entries to declare new permissions
		/* BEGIN MODULEBUILDER PERMISSIONS */
		$r = 0;
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read objects of Doliproject'; // Permission label
		$this->rights[$r][4] = 'read'; // In php code, permission will be checked by test if ($user->rights->doliproject->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Read objects of Doliproject'; // Permission label
		$this->rights[$r][4] = 'lire'; // In php code, permission will be checked by test if ($user->rights->doliproject->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Create/Update objects of Doliproject'; // Permission label
		$this->rights[$r][4] = 'write'; // In php code, permission will be checked by test if ($user->rights->doliproject->level1->level2)
		$r++;
		$this->rights[$r][0] = $this->numero + $r; // Permission id (must not be already used)
		$this->rights[$r][1] = 'Delete objects of Doliproject'; // Permission label
		$this->rights[$r][4] = 'delete'; // In php code, permission will be checked by test if ($user->rights->doliproject->level1->level2)
		$r++;
		/* END MODULEBUILDER PERMISSIONS */

		// Main menu entries to add
		$this->menu = array();

		$langs->load('doliproject@doliproject');
		// Add here entries to declare new menus
		/* BEGIN MODULEBUILDER TOPMENU */
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=project,fk_leftmenu=timespent', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left', // This is a Top menu entry
			'titre'=>'DoliprojectTimeSpent',
			'mainmenu'=>'project',
			'leftmenu'=>'doliproject_timespent_list',
			'url'=>'/doliproject/view/timespent_list.php',
			'langs'=>'doliproject@doliproject', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliproject->enabled', // Define condition to show or hide menu entry. Use '$conf->doliproject->enabled' if entry must be visible if module is enabled.
			'perms'=>'1', // Use 'perms'=>'$user->rights->doliproject->myobject->read' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++] = array(
			'fk_menu'  =>'fk_mainmenu=project,fk_leftmenu=timespent', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left', // This is a Top menu entry
			'titre'    => $langs->trans('AddTimeSpent'),
			'mainmenu' => 'project',
			'leftmenu' => 'timespent',
			'url'      => '/doliproject/view/timespent_day.php',
			'langs'    => 'doliproject@doliproject', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 48520 + $r,
			'enabled'  => '$conf->doliproject->enabled', // Define condition to show or hide menu entry. Use '$conf->doliproject->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->doliproject->lire', // Use 'perms'=>'$user->rights->doliproject->digiriskconst->read' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++] = array(
			'fk_menu'=>'fk_mainmenu=hrm,fk_leftmenu=timespent', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left', // This is a Top menu entry
			'titre'=>'DoliprojectTimeSpent',
			'mainmenu'=>'hrm',
			'leftmenu'=>'doliproject_timespent_list',
			'url'=>'/doliproject/view/timespent_list.php',
			'langs'=>'doliproject@doliproject', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position'=>1000 + $r,
			'enabled'=>'$conf->doliproject->enabled', // Define condition to show or hide menu entry. Use '$conf->doliproject->enabled' if entry must be visible if module is enabled.
			'perms'=>'1', // Use 'perms'=>'$user->rights->doliproject->myobject->read' if you want your menu with a permission rules
			'target'=>'',
			'user'=>2, // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++] = array(
			'fk_menu'  =>'fk_mainmenu=hrm,fk_leftmenu=timespent', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left', // This is a Top menu entry
			'titre'    => $langs->trans('AddTimeSpent'),
			'mainmenu' => 'hrm',
			'leftmenu' => 'timespent',
			'url'      => '/doliproject/view/timespent_day.php',
			'langs'    => 'doliproject@doliproject', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 48520 + $r,
			'enabled'  => '$conf->doliproject->enabled', // Define condition to show or hide menu entry. Use '$conf->doliproject->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->doliproject->lire', // Use 'perms'=>'$user->rights->doliproject->digiriskconst->read' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++] = array(
			'fk_menu'  =>'fk_mainmenu=billing,fk_leftmenu=customers_bills', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left', // This is a Top menu entry
			'titre'    => $langs->trans('RecurringInvoicesStatistics'),
			'mainmenu' => 'billing',
			'leftmenu' => 'customers_bills',
			'url'      => '/doliproject/view/recurringinvoicestatistics.php',
			'langs'    => 'doliproject@doliproject', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->doliproject->enabled && $conf->facture->enabled', // Define condition to show or hide menu entry. Use '$conf->doliproject->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->doliproject->lire && $user->rights->facture->lire', // Use 'perms'=>'$user->rights->doliproject->digiriskconst->read' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 2, // 0=Menu for internal users, 1=external users, 2=both
		);
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=billing,fk_leftmenu=customers_bills',
			'type'     => 'left',
			'titre'    => $langs->trans('Categories'),
			'mainmenu' => 'billing',
			'leftmenu' => 'customers_bills',
			'url'      => '/categories/index.php?type=invoice',
			'langs'    => 'doliproject@doliproject',
			'position' => 1100 + $r,
			'enabled'  => '$conf->doliproject->enabled && $conf->categorie->enabled',
			'perms'    => '$user->rights->doliproject->lire && $user->rights->facture->lire',
			'target'   => '',
			'user'     => 0,
		);
		/* END MODULEBUILDER TOPMENU */
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs, $db, $user;
		$langs->load('doliproject@doliproject');

		$result = $this->_load_tables('/doliproject/sql/');
		if ($result < 0) return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')

		if ($conf->global->DOLIPROJECT_HR_PROJECT < 1) {

			require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
			require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
			require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
			require_once DOL_DOCUMENT_ROOT . '/core/modules/project/mod_project_simple.php';

			$project = new Project($db);
			$projectRef  = new $conf->global->PROJECT_ADDON();

			$project->ref         = $projectRef->getNextValue('', $project);
			$project->title       = $langs->trans('HumanResources') . ' - ' . $conf->global->MAIN_INFO_SOCIETE_NOM;
			$project->description = $langs->trans('HRDescription');
			$project->date_c      = dol_now();
			$currentYear          = dol_print_date(dol_now(), '%Y');
			$fiscalMonthStart     = $conf->global->SOCIETE_FISCAL_MONTH_START;
			$startdate            = dol_mktime('0', '0', '0', $fiscalMonthStart ? $fiscalMonthStart : '1', '1', $currentYear);
			$project->date_start  = $startdate;

			$project->usage_task = 1;

			$startdateAddYear      = dol_time_plus_duree($startdate, 1, 'y');
			$startdateAddYearMonth = dol_time_plus_duree($startdateAddYear, -1, 'd');
			$enddate               = dol_print_date($startdateAddYearMonth, 'dayrfc');
			$project->date_end     = $enddate;
			$project->statut       = 1;

			$result = $project->create($user);

			if ($result > 0) {
				dolibarr_set_const($db, 'DOLIPROJECT_HR_PROJECT', $result, 'integer', 0, '', $conf->entity);
				$task = new Task($db);
				$defaultref = '';
				$obj = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

				if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . ".php")) {
					require_once DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . '.php';
					$modTask = new $obj;
					$defaultref = $modTask->getNextValue('', null);
				}

				$task->fk_project = $result;
				$task->ref = $defaultref;
				$task->label = $langs->trans('Holidays');
				$task->date_c = dol_now();
				$task->create($user);

				$task->fk_project = $result;
				$task->ref = $modTask->getNextValue('', null);;
				$task->label = $langs->trans('PaidHolidays');
				$task->date_c = dol_now();
				$task->create($user);

				$task->fk_project = $result;
				$task->ref = $modTask->getNextValue('', null);;
				$task->label = $langs->trans('SickLeave');
				$task->date_c = dol_now();
				$task->create($user);

				$task->fk_project = $result;
				$task->ref = $modTask->getNextValue('', null);;
				$task->label = $langs->trans('PublicHoliday');
				$task->date_c = dol_now();
				$task->create($user);

				$task->fk_project = $result;
				$task->ref = $modTask->getNextValue('', null);;
				$task->label = $langs->trans('RTT');
				$task->date_c = dol_now();
				$task->create($user);

				dolibarr_set_const($db, 'DOLIPROJECT_RTT_TASK', 1, 'integer', 0, '', $conf->entity);
			}

		}
		if ($conf->global->DOLIPROJECT_RTT_TASK < 1) {
			require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

			$task = new Task($db);
			$obj = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

			if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . ".php")) {
				require_once DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . '.php';
				$modTask = new $obj;
			}

			$task->fk_project = $conf->global->DOLIPROJECT_HR_PROJECT;
			$task->ref = $modTask->getNextValue('', null);;
			$task->label = $langs->trans('RTT');
			$task->date_c = dol_now();
			$rtt_task_id = $task->create($user);

			dolibarr_set_const($db, 'DOLIPROJECT_RTT_TASK', $rtt_task_id, 'integer', 0, '', $conf->entity);
		}

		if ($conf->global->DOLIPROJECT_TIMESPENT_BOOKMARK_SET < 1) {
			include_once DOL_DOCUMENT_ROOT.'/bookmarks/class/bookmark.class.php';

			$bookmark = new Bookmark($db);

			$bookmark->title = $langs->trans('TimeSpent');
			$bookmark->url = DOL_URL_ROOT . '/custom/doliproject/view/timespent_day.php?mainmenu=project';
			$bookmark->target = 0;
			$bookmark->position = 10;
			$bookmark->create($user);
		}

		// Create extrafields during init
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extra_fields = new ExtraFields($this->db);

		$param['options']['Task:projet/class/task.class.php'] = NULL;
		$extra_fields->addExtraField('fk_task', 'Tâche', 'link', 100, NULL, 'facture', 1, 0, NULL, $param, 1, 1, 1); //extrafields invoice
		unset($param);
		$param['options']['Facture:compta/facture/class/facture.class.php'] = NULL;
		$extra_fields->addExtraField('fk_facture_name', 'Facture', 'link', 100, NULL, 'projet_task', 1, 0, NULL, $param, 1, 1, 1); //extrafields task
		unset($param);
		$extra_fields->update('fk_task', 'Tâche', 'sellist', '', 'ticket', 0, 0, 100, 'a:1:{s:7:"options";a:1:{s:110:"projet_task:ref:rowid::entity = $ENTITY$ AND fk_projet = ($SEL$ fk_project FROM '. MAIN_DB_PREFIX .'ticket WHERE rowid = $ID$)";N;}}', 1, 1, '1');
		$extra_fields->addExtraField('fk_task', 'Tâche', 'sellist', 100, NULL, 'ticket', 0, 0, NULL, 'a:1:{s:7:"options";a:1:{s:110:"projet_task:ref:rowid::entity = $ENTITY$ AND fk_projet = ($SEL$ fk_project FROM '. MAIN_DB_PREFIX .'ticket WHERE rowid = $ID$)";N;}}', 1, 1, '1'); //extrafields ticket

		return $this->_init(array(), $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
