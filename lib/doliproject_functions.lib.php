<?php

/**
* \file    doliproject/lib/doliproject_functions.lib.php
* \ingroup doliproject
* \brief   Library files with common functions for DoliCar
												   */

require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

/**
 * Add or delete task from favorite by the user
 *
 * @return float|int
 */
function toggleTaskFavorite($task_id, $user_id)
{
	global $db;
	$task = new Task($db);
	$task->fetch($task_id);
	$task->fetchObjectLinked();

	if (!empty($task->linkedObjects) && key_exists('user', $task->linkedObjects)) {
		foreach($task->linkedObjects['user'] as $userLinked) {
			if ($userLinked->id == $user_id) {
				$link_exists = 1;
				$task->deleteObjectLinked($user_id, 'user');
			}
		}
	}

	if (!$link_exists) {
		$result = $task->add_object_linked('user', $user_id,'','');
		return $result;
	}
	return 0;
}

/**
 * \file    doliproject/lib/doliproject_functions.lib.php
 * \ingroup doliproject
 * \brief   Library files with common functions for DoliCar
 */

/**
 * Check if task is set to favorite by the user
 *
 * @return float|int
 */
function isTaskFavorite($task_id, $user_id)
{
	global $db;
	$task = new Task($db);
	$task->fetch($task_id);
	$task->fetchObjectLinked();
	$link_exists = 0;
	if (!empty($task->linkedObjects) && key_exists('user', $task->linkedObjects)) {
		foreach($task->linkedObjects['user'] as $userLinked) {
			if ($userLinked->id == $user_id) {
				$link_exists = 1;
			}
		}
	}

	return $link_exists;
}

/**
 * Return list of tasks for all projects or for one particular project
 * Sort order is on project, then on position of task, and last on start date of first level task
 *
 * @param	User	$usert				Object user to limit tasks affected to a particular user
 * @param	User	$userp				Object user to limit projects of a particular user and public projects
 * @param	int		$projectid			Project id
 * @param	int		$socid				Third party id
 * @param	int		$mode				0=Return list of tasks and their projects, 1=Return projects and tasks if exists
 * @param	string	$filteronproj    	Filter on project ref or label
 * @param	string	$filteronprojstatus	Filter on project status ('-1'=no filter, '0,1'=Draft+Validated only)
 * @param	string	$morewherefilter	Add more filter into where SQL request (must start with ' AND ...')
 * @param	string	$filteronprojuser	Filter on user that is a contact of project
 * @param	string	$filterontaskuser	Filter on user assigned to task
 * @param	array	$extrafields	    Show additional column from project or task
 * @param   int     $includebilltime    Calculate also the time to bill and billed
 * @param   array   $search_array_options Array of search
 * @param   int     $loadextras         Fetch all Extrafields on each task
 * @return 	array						Array of tasks
 */
function getFavoriteTasksArray($task_id = 0, $usert = null, $userp = null, $projectid = 0, $socid = 0, $mode = 0, $filteronproj = '', $filteronprojstatus = '-1', $morewherefilter = '', $filteronprojuser = 0, $filterontaskuser = 0, $extrafields = array(), $includebilltime = 0, $search_array_options = array(), $loadextras = 0)
{
	global $conf, $hookmanager, $db, $user;
	require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

	$task = new Task($db);
	$task->fetch($task_id);

	$tasks = array();

	//print $usert.'-'.$userp.'-'.$projectid.'-'.$socid.'-'.$mode.'<br>';

	// List of tasks (does not care about permissions. Filtering will be done later)
	$sql = "SELECT ";
	if ($filteronprojuser > 0 || $filterontaskuser > 0) {
		$sql .= " DISTINCT"; // We may get several time the same record if user has several roles on same project/task
	}
	$sql .= " p.rowid as projectid, p.ref, p.title as plabel, p.public, p.fk_statut as projectstatus, p.usage_bill_time,";
	$sql .= " t.rowid as taskid, t.ref as taskref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut as status,";
	$sql .= " t.dateo as date_start, t.datee as date_end, t.planned_workload, t.rang,";
	$sql .= " t.description, ";
	$sql .= " t.budget_amount, ";
	$sql .= " s.rowid as thirdparty_id, s.nom as thirdparty_name, s.email as thirdparty_email,";
	$sql .= " p.fk_opp_status, p.opp_amount, p.opp_percent, p.budget_amount as project_budget_amount";
	if (!empty($extrafields->attributes['projet']['label'])) {
		foreach ($extrafields->attributes['projet']['label'] as $key => $val) {
			$sql .= ($extrafields->attributes['projet']['type'][$key] != 'separate' ? ",efp.".$key." as options_".$key : '');
		}
	}
	if (!empty($extrafields->attributes['projet_task']['label'])) {
		foreach ($extrafields->attributes['projet_task']['label'] as $key => $val) {
			$sql .= ($extrafields->attributes['projet_task']['type'][$key] != 'separate' ? ",efpt.".$key." as options_".$key : '');
		}
	}
	if ($includebilltime) {
		$sql .= ", SUM(tt.task_duration * ".$db->ifsql("invoice_id IS NULL", "1", "0").") as tobill, SUM(tt.task_duration * ".$db->ifsql("invoice_id IS NULL", "0", "1").") as billed";
	}

	$sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON p.fk_soc = s.rowid";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_extrafields as efp ON (p.rowid = efp.fk_object)";

	if ($mode == 0) {
		if ($filteronprojuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
		}
		$sql .= ", ".MAIN_DB_PREFIX."projet_task as t";
		if ($includebilltime) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON tt.fk_task = t.rowid";
		}
		if ($filterontaskuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec2";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc2";
		}
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as elel ON (t.rowid = elel.fk_target AND elel.targettype='project_task')";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND t.fk_projet = p.rowid";
		$sql .= " AND elel.fk_target = t.rowid";
		$sql .= " AND elel.fk_source = " . $user->id;

	} elseif ($mode == 1) {
		if ($filteronprojuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
		}
		if ($filterontaskuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."projet_task as t";
			if ($includebilltime) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON tt.fk_task = t.rowid";
			}
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec2";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc2";
		} else {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task as t on t.fk_projet = p.rowid";
			if ($includebilltime) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON tt.fk_task = t.rowid";
			}
		}
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
	} else {
		return 'BadValueForParameterMode';
	}

	if ($filteronprojuser > 0) {
		$sql .= " AND p.rowid = ec.element_id";
		$sql .= " AND ctc.rowid = ec.fk_c_type_contact";
		$sql .= " AND ctc.element = 'project'";
		$sql .= " AND ec.fk_socpeople = ".((int) $filteronprojuser);
		$sql .= " AND ec.statut = 4";
		$sql .= " AND ctc.source = 'internal'";
	}
	if ($filterontaskuser > 0) {
		$sql .= " AND t.fk_projet = p.rowid";
		$sql .= " AND p.rowid = ec2.element_id";
		$sql .= " AND ctc2.rowid = ec2.fk_c_type_contact";
		$sql .= " AND ctc2.element = 'project_task'";
		$sql .= " AND ec2.fk_socpeople = ".((int) $filterontaskuser);
		$sql .= " AND ec2.statut = 4";
		$sql .= " AND ctc2.source = 'internal'";
	}
	if ($socid) {
		$sql .= " AND p.fk_soc = ".((int) $socid);
	}
	if ($projectid) {
		$sql .= " AND p.rowid IN (".$db->sanitize($projectid).")";
	}
	if ($filteronproj) {
		$sql .= natural_search(array("p.ref", "p.title"), $filteronproj);
	}
	if ($filteronprojstatus && $filteronprojstatus != '-1') {
		$sql .= " AND p.fk_statut IN (".$db->sanitize($filteronprojstatus).")";
	}
	if ($morewherefilter) {
		$sql .= $morewherefilter;
	}
	// Add where from extra fields
	$extrafieldsobjectkey = 'projet_task';
	$extrafieldsobjectprefix = 'efpt.';
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
	// Add where from hooks
	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
	$sql .= $hookmanager->resPrint;
	if ($includebilltime) {
		$sql .= " GROUP BY p.rowid, p.ref, p.title, p.public, p.fk_statut, p.usage_bill_time,";
		$sql .= " t.datec, t.dateo, t.datee, t.tms,";
		$sql .= " t.rowid, t.ref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut,";
		$sql .= " t.dateo, t.datee, t.planned_workload, t.rang,";
		$sql .= " t.description, ";
		$sql .= " t.budget_amount, ";
		$sql .= " s.rowid, s.nom, s.email,";
		$sql .= " p.fk_opp_status, p.opp_amount, p.opp_percent, p.budget_amount";
		if (!empty($extrafields->attributes['projet']['label'])) {
			foreach ($extrafields->attributes['projet']['label'] as $key => $val) {
				$sql .= ($extrafields->attributes['projet']['type'][$key] != 'separate' ? ",efp.".$key : '');
			}
		}
		if (!empty($extrafields->attributes['projet_task']['label'])) {
			foreach ($extrafields->attributes['projet_task']['label'] as $key => $val) {
				$sql .= ($extrafields->attributes['projet_task']['type'][$key] != 'separate' ? ",efpt.".$key : '');
			}
		}
	}


	$sql .= " ORDER BY p.ref, t.rang, t.dateo";

	//print $sql;exit;
	dol_syslog(get_class($task)."::getTasksArray", LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		// Loop on each record found, so each couple (project id, task id)
		while ($i < $num) {
			$error = 0;

			$obj = $db->fetch_object($resql);

			if ((!$obj->public) && (is_object($userp))) {	// If not public project and we ask a filter on project owned by a user
				if (!$task->getUserRolesForProjectsOrTasks($userp, 0, $obj->projectid, 0)) {
					$error++;
				}
			}
			if (is_object($usert)) {							// If we ask a filter on a user affected to a task
				if (!$task->getUserRolesForProjectsOrTasks(0, $usert, $obj->projectid, $obj->taskid)) {
					$error++;
				}
			}

			if (!$error) {
				$tasks[$i] = new Task($db);
				$tasks[$i]->id = $obj->taskid;
				$tasks[$i]->ref = $obj->taskref;
				$tasks[$i]->fk_project		= $obj->projectid;
				$tasks[$i]->projectref		= $obj->ref;
				$tasks[$i]->projectlabel = $obj->plabel;
				$tasks[$i]->projectstatus = $obj->projectstatus;

				$tasks[$i]->fk_opp_status = $obj->fk_opp_status;
				$tasks[$i]->opp_amount = $obj->opp_amount;
				$tasks[$i]->opp_percent = $obj->opp_percent;
				$tasks[$i]->budget_amount = $obj->budget_amount;
				$tasks[$i]->project_budget_amount = $obj->project_budget_amount;
				$tasks[$i]->usage_bill_time = $obj->usage_bill_time;

				$tasks[$i]->label = $obj->label;
				$tasks[$i]->description = $obj->description;
				$tasks[$i]->fk_parent = $obj->fk_task_parent; // deprecated
				$tasks[$i]->fk_task_parent = $obj->fk_task_parent;
				$tasks[$i]->duration		= $obj->duration_effective;
				$tasks[$i]->planned_workload = $obj->planned_workload;

				if ($includebilltime) {
					$tasks[$i]->tobill = $obj->tobill;
					$tasks[$i]->billed = $obj->billed;
				}

				$tasks[$i]->progress		= $obj->progress;
				$tasks[$i]->fk_statut = $obj->status;
				$tasks[$i]->public = $obj->public;
				$tasks[$i]->date_start = $db->jdate($obj->date_start);
				$tasks[$i]->date_end		= $db->jdate($obj->date_end);
				$tasks[$i]->rang	   		= $obj->rang;

				$tasks[$i]->socid           = $obj->thirdparty_id; // For backward compatibility
				$tasks[$i]->thirdparty_id = $obj->thirdparty_id;
				$tasks[$i]->thirdparty_name	= $obj->thirdparty_name;
				$tasks[$i]->thirdparty_email = $obj->thirdparty_email;

				if (!empty($extrafields->attributes['projet']['label'])) {
					foreach ($extrafields->attributes['projet']['label'] as $key => $val) {
						if ($extrafields->attributes['projet']['type'][$key] != 'separate') {
							$tasks[$i]->{'options_'.$key} = $obj->{'options_'.$key};
						}
					}
				}

				if (!empty($extrafields->attributes['projet_task']['label'])) {
					foreach ($extrafields->attributes['projet_task']['label'] as $key => $val) {
						if ($extrafields->attributes['projet_task']['type'][$key] != 'separate') {
							$tasks[$i]->{'options_'.$key} = $obj->{'options_'.$key};
						}
					}
				}

				if ($loadextras) {
					$tasks[$i]->fetch_optionals();
				}
			}

			$i++;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	return $tasks;
}

/**
 * Output a task line into a pertime intput mode
 *
 * @param	string	   	$inc					Line number (start to 0, then increased by recursive call)
 * @param   string		$parent					Id of parent task to show (0 to show all)
 * @param	User|null	$fuser					Restrict list to user if defined
 * @param   Task[]		$lines					Array of lines
 * @param   int			$level					Level (start to 0, then increased/decrease by recursive call)
 * @param   string		$projectsrole			Array of roles user has on project
 * @param   string		$tasksrole				Array of roles user has on task
 * @param	string		$mine					Show only task lines I am assigned to
 * @param   int			$restricteditformytask	0=No restriction, 1=Enable add time only if task is assigned to me, 2=Enable add time only if tasks is assigned to me and hide others
 * @param	int			$preselectedday			Preselected day
 * @param   array       $isavailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function doliprojectLinesPerDay(&$inc, $parent, $fuser, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, $preselectedday, &$isavailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null)
{
	global $conf, $db, $user, $langs;
	global $form, $formother, $projectstatic, $taskstatic, $thirdpartystatic;

	$lastprojectid = 0;
	$totalforeachday = array();
	$workloadforid = array();
	$lineswithoutlevel0 = array();

	$numlines = count($lines);

	// Create a smaller array with sublevels only to be used later. This increase dramatically performances.
	if ($parent == 0) { // Always and only if at first level
		for ($i = 0; $i < $numlines; $i++) {
			if ($lines[$i]->fk_task_parent) {
				$lineswithoutlevel0[] = $lines[$i];
			}
		}
	}

	if (empty($oldprojectforbreak)) {
		$oldprojectforbreak = (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT) ? 0 : -1); // 0 to start break , -1 no break
	}

	$restrictBefore = null;

	if (! empty($conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS)) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$restrictBefore = dol_time_plus_duree(dol_now(), - $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
	}

	//dol_syslog('projectLinesPerDay inc='.$inc.' preselectedday='.$preselectedday.' task parent id='.$parent.' level='.$level." count(lines)=".$numlines." count(lineswithoutlevel0)=".count($lineswithoutlevel0));
	for ($i = 0; $i < $numlines; $i++) {
		if ($parent == 0) {
			$level = 0;
		}

		if ($lines[$i]->fk_task_parent == $parent) {
			$obj = &$lines[$i]; // To display extrafields

			// If we want all or we have a role on task, we show it
			if (empty($mine) || !empty($tasksrole[$lines[$i]->id])) {
				//dol_syslog("projectLinesPerWeek Found line ".$i.", a qualified task (i have role or want to show all tasks) with id=".$lines[$i]->id." project id=".$lines[$i]->fk_project);

				if ($restricteditformytask == 2 && empty($tasksrole[$lines[$i]->id])) {	// we have no role on task and we request to hide such cases
					continue;
				}

				// Break on a new project
				if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid) {
					$lastprojectid = $lines[$i]->fk_project;
					if ($preselectedday) {
						$projectstatic->id = $lines[$i]->fk_project;
					}
				}

				if (empty($workloadforid[$projectstatic->id])) {
					if ($preselectedday) {
						$projectstatic->loadTimeSpent($preselectedday, 0, $fuser->id); // Load time spent from table projet_task_time for the project into this->weekWorkLoad and this->weekWorkLoadPerTask for all days of a week
						$workloadforid[$projectstatic->id] = 1;
					}
				}

				$projectstatic->id = $lines[$i]->fk_project;
				$projectstatic->ref = $lines[$i]->projectref;
				$projectstatic->title = $lines[$i]->projectlabel;
				$projectstatic->public = $lines[$i]->public;
				$projectstatic->status = $lines[$i]->projectstatus;

				$taskstatic->id = $lines[$i]->id;
				$taskstatic->ref = ($lines[$i]->ref ? $lines[$i]->ref : $lines[$i]->id);
				$taskstatic->label = $lines[$i]->label;
				$taskstatic->date_start = $lines[$i]->date_start;
				$taskstatic->date_end = $lines[$i]->date_end;

				$thirdpartystatic->id = $lines[$i]->socid;
				$thirdpartystatic->name = $lines[$i]->thirdparty_name;
				$thirdpartystatic->email = $lines[$i]->thirdparty_email;

				if (empty($oldprojectforbreak) || ($oldprojectforbreak != -1 && $oldprojectforbreak != $projectstatic->id)) {
					$addcolspan = 0;
					if (!empty($arrayfields['t.planned_workload']['checked'])) {
						$addcolspan++;
					}
					if (!empty($arrayfields['t.progress']['checked'])) {
						$addcolspan++;
					}
					foreach ($arrayfields as $key => $val) {
						if ($val['checked'] && substr($key, 0, 5) == 'efpt.') {
							$addcolspan++;
						}
					}

					print '<tr class="oddeven trforbreak nobold">'."\n";
					print '<td colspan="'.(5 + $addcolspan).'">';
					print $projectstatic->getNomUrl(1, '', 0, '<strong>'.$langs->transnoentitiesnoconv("YourRole").':</strong> '.$projectsrole[$lines[$i]->fk_project]);
					if ($thirdpartystatic->id > 0) {
						print ' - '.$thirdpartystatic->getNomUrl(1);
					}
					if ($projectstatic->title) {
						print ' - ';
						print '<span class="secondary">'.$projectstatic->title.'</span>';
					}

					print '</td>';
					print '</tr>';
				}

				if ($oldprojectforbreak != -1) {
					$oldprojectforbreak = $projectstatic->id;
				}

				print '<tr class="oddeven" data-taskid="'.$lines[$i]->id.'">'."\n";

				// User
				/*
				print '<td class="nowrap">';
				print $fuser->getNomUrl(1, 'withproject', 'time');
				print '</td>';
				*/

				// Project
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print "<td>";
					if ($oldprojectforbreak == -1) {
						print $projectstatic->getNomUrl(1, '', 0, $langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project]);
					}
					print "</td>";
				}

				// Thirdparty
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print '<td class="tdoverflowmax100">';
					if ($thirdpartystatic->id > 0) {
						print $thirdpartystatic->getNomUrl(1, 'project', 10);
					}
					print '</td>';
				}

				// Ref
				print '<td>';
				print '<!-- Task id = '.$lines[$i]->id.' -->';
				for ($k = 0; $k < $level; $k++) {
					print '<div class="marginleftonly">';
				}
				print $taskstatic->getNomUrl(1, 'withproject', 'time');
				// Label task
				print '<br>';
				print '<span class="opacitymedium">'.$taskstatic->label.'</a>';
				for ($k = 0; $k < $level; $k++) {
					print "</div>";
				}
				print "</td>\n";

				// TASK extrafields
				$extrafieldsobjectkey = 'projet_task';
				$extrafieldsobjectprefix = 'efpt.';
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

				if (!empty($arrayfields['timeconsumed']['checked'])) {
					// Time spent by everybody
					print '<td class="right">';
					// $lines[$i]->duration is a denormalised field = summ of time spent by everybody for task. What we need is time consummed by user
					if ($lines[$i]->duration) {
						print '<a href="'.DOL_URL_ROOT.'/projet/tasks/time.php?id='.$lines[$i]->id.'">';
						print convertSecondToTime($lines[$i]->duration, 'allhourmin');
						print '</a>';
					} else {
						print '--:--';
					}
					print "</td>\n";

					// Time spent by user
					print '<td class="right">';
					$tmptimespent = $taskstatic->getSummaryOfTimeSpent($fuser->id);
					if ($tmptimespent['total_duration']) {
						print convertSecondToTime($tmptimespent['total_duration'], 'allhourmin');
					} else {
						print '--:--';
					}
					print "</td>\n";
				}

				$disabledproject = 1;
				$disabledtask = 1;

				// If at least one role for project
				if ($lines[$i]->public || !empty($projectsrole[$lines[$i]->fk_project]) || $user->rights->projet->all->creer) {
					$disabledproject = 0;
					$disabledtask = 0;
				}
				// If $restricteditformytask is on and I have no role on task, i disable edit
				if ($restricteditformytask && empty($tasksrole[$lines[$i]->id])) {
					$disabledtask = 1;
				}

				if ($restrictBefore && $preselectedday < $restrictBefore) {
					$disabledtask = 1;
				}

				// Select hour
				print '<td class="nowraponall leftborder center minwidth150imp">';
				$tableCell = $form->selectDate($preselectedday, $lines[$i]->id, 1, 1, 2, "addtime", 0, 0, $disabledtask);
				print $tableCell;
				print '</td>';

				$cssonholiday = '';
				if (!$isavailable[$preselectedday]['morning'] && !$isavailable[$preselectedday]['afternoon']) {
					$cssonholiday .= 'onholidayallday ';
				} elseif (!$isavailable[$preselectedday]['morning']) {
					$cssonholiday .= 'onholidaymorning ';
				} elseif (!$isavailable[$preselectedday]['afternoon']) {
					$cssonholiday .= 'onholidayafternoon ';
				}

				global $daytoparse;
				$tmparray = dol_getdate($daytoparse, true); // detail of current day

				$idw = ($tmparray['wday'] - (empty($conf->global->MAIN_START_WEEK) ? 0 : 1));
				global $numstartworkingday, $numendworkingday;
				$cssweekend = '';
				if ((($idw + 1) < $numstartworkingday) || (($idw + 1) > $numendworkingday)) {	// This is a day is not inside the setup of working days, so we use a week-end css.
					$cssweekend = 'weekend';
				}

				// Duration
				print '<td class="center duration'.($cssonholiday ? ' '.$cssonholiday : '').($cssweekend ? ' '.$cssweekend : '').'">';
				$dayWorkLoad = $projectstatic->weekWorkLoadPerTask[$preselectedday][$lines[$i]->id];
				$totalforeachday[$preselectedday] += $dayWorkLoad;

				$alreadyspent = '';
				if ($dayWorkLoad > 0) {
					$alreadyspent = convertSecondToTime($dayWorkLoad, 'allhourmin');
				}

				$idw = 0;

				$tableCell = '';
				$tableCell .= '<span class="timesheetalreadyrecorded" title="texttoreplace"><input type="text" class="center" size="2" disabled id="timespent['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="'.$alreadyspent.'"></span>';
				$tableCell .= '<span class="hideonsmartphone"> + </span>';
				//$tableCell.='&nbsp;&nbsp;&nbsp;';
				$tableCell .= $form->select_duration($lines[$i]->id.'duration', '', $disabledtask, 'text', 0, 1);
				//$tableCell.='&nbsp;<input type="submit" class="button"'.($disabledtask?' disabled':'').' value="'.$langs->trans("Add").'">';
				print $tableCell;
				print ' <i class="auto-fill-timespent fas fa-arrow-left"></i>';


				$modeinput = 'hours';

				print '<script type="text/javascript">';
				print "jQuery(document).ready(function () {\n";
				print " 	jQuery('.inputhour, .inputminute').bind('keyup', function(e) { updateTotal(0, '".$modeinput."') });";
				print "})\n";
				print '</script>';

				print '</td>';

				// Note
				print '<td class="center">';
				print '<textarea name="'.$lines[$i]->id.'note" rows="'.ROWS_2.'" id="'.$lines[$i]->id.'note"'.($disabledtask ? ' disabled="disabled"' : '').'>';
				print '</textarea>';
				print '</td>';

				// Warning
				print '<td class="right">';
				if ((!$lines[$i]->public) && $disabledproject) {
					print $form->textwithpicto('', $langs->trans("UserIsNotContactOfProject"));
				} elseif ($disabledtask) {
					$titleassigntask = $langs->trans("AssignTaskToMe");
					if ($fuser->id != $user->id) {
						$titleassigntask = $langs->trans("AssignTaskToUser", '...');
					}

					print $form->textwithpicto('', $langs->trans("TaskIsNotAssignedToUser", $titleassigntask));
				}
				print '</td>';

				print "</tr>\n";
			}

			$inc++;
			$level++;
			if ($lines[$i]->id > 0) {
				//var_dump('totalforeachday after taskid='.$lines[$i]->id.' and previous one on level '.$level);
				//var_dump($totalforeachday);
				$ret = doliprojectLinesPerDay($inc, $lines[$i]->id, $fuser, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $preselectedday, $isavailable, $oldprojectforbreak, $arrayfields, $extrafields);
				//var_dump('ret with parent='.$lines[$i]->id.' level='.$level);
				//var_dump($ret);
				foreach ($ret as $key => $val) {
					$totalforeachday[$key] += $val;
				}
				//var_dump('totalforeachday after taskid='.$lines[$i]->id.' and previous one on level '.$level.' + subtasks');
				//var_dump($totalforeachday);
			}
			$level--;
		} else {
			//$level--;
		}
	}

	return $totalforeachday;
}
