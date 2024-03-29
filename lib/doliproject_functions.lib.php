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
		if ($conf->global->DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as elel ON (t.rowid = elel.fk_target AND elel.targettype='project_task')";
		}
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND t.fk_projet = p.rowid";
		if ($conf->global->DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS) {
			$sql .= " AND elel.fk_target = t.rowid";
			$sql .= " AND elel.fk_source = " . $user->id;
		}

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

				$addcolspan = 2;
				if (!empty($arrayfields['timeconsumed']['checked'])) {
					$addcolspan++;
					$addcolspan++;
				}
				foreach ($arrayfields as $key => $val) {
					if ($val['checked'] && substr($key, 0, 5) == 'efpt.') {
						$addcolspan++;
					}
				}

				if (empty($oldprojectforbreak) || ($oldprojectforbreak != -1 && $oldprojectforbreak != $projectstatic->id)) {
					print '<tr class="oddeven trforbreak nobold project-line" id="project-'. $projectstatic->id .'">'."\n";
					print '<td colspan="'. $addcolspan.'">';
					print $projectstatic->getNomUrl(1, '', 0, '<strong>'.$langs->transnoentitiesnoconv("YourRole").':</strong> '.$projectsrole[$lines[$i]->fk_project]);
					if ($thirdpartystatic->id > 0) {
						print ' - '.$thirdpartystatic->getNomUrl(1);
					}
					if ($projectstatic->title) {
						print ' - ';
						print '<span class="secondary">'.$projectstatic->title.'</span>';
					}

					print '</td>';
					print '<td style="text-align: center; ">';
					if (!empty($conf->use_javascript_ajax)) {
						print img_picto("Auto fill", 'rightarrow', "class='auto-fill-timespent-project' data-rowname='".$namef."' data-value='".($sign * $remaintopay)."'");
					}
					print ' ' . $langs->trans('DivideTimeIntoTasks');
					print '</td>';

					print '<td>';

					print '</td>';

					print '<td>';

					print '</td>';
					print '</tr>';
				}

				if ($oldprojectforbreak != -1) {
					$oldprojectforbreak = $projectstatic->id;
				}

				print '<tr class="oddeven project-'. $projectstatic->id .'" data-taskid="'.$lines[$i]->id.'">'."\n";

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
				if (!empty($conf->use_javascript_ajax)) {
					$tableCell .= img_picto("Auto fill", 'rightarrow', "class='auto-fill-timespent' data-rowname='".$namef."' data-value='".($sign * $remaintopay)."'");
				}
				$tableCell .= $form->select_duration($lines[$i]->id.'duration', '', $disabledtask, 'text', 0, 1);
				//$tableCell.='&nbsp;<input type="submit" class="button"'.($disabledtask?' disabled':'').' value="'.$langs->trans("Add").'">';
				print $tableCell;

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

/**
 * Prepare array with list of tabs
 *
 * @param	string	$mode		Mode
 * @param   string  $fuser      Filter on user
 * @return  array				Array of tabs to show
 */
function doliproject_timesheet_prepare_head($mode, $fuser = null)
{
	global $langs, $conf, $user;
	$h = 0;
	$head = array();

	$h = 0;

	$param = '';
	$param .= ($mode ? '&mode='.$mode : '');
	if (is_object($fuser) && $fuser->id > 0 && $fuser->id != $user->id) {
		$param .= '&search_usertoprocessid='.$fuser->id;
	}

	if (empty($conf->global->PROJECT_DISABLE_TIMESHEET_PERMONTH)) {
		$head[$h][0] = DOL_URL_ROOT."/custom/doliproject/view/timespent_month.php".($param ? '?'.$param : '');
		$head[$h][1] = $langs->trans("InputPerMonth");
		$head[$h][2] = 'inputpermonth';
		$h++;
	}

	if (empty($conf->global->PROJECT_DISABLE_TIMESHEET_PERWEEK)) {
		$head[$h][0] = DOL_URL_ROOT."/custom/doliproject/view/timespent_week.php".($param ? '?'.$param : '');
		$head[$h][1] = $langs->trans("InputPerWeek");
		$head[$h][2] = 'inputperweek';
		$h++;
	}

	if (empty($conf->global->PROJECT_DISABLE_TIMESHEET_PERTIME)) {
		$head[$h][0] = DOL_URL_ROOT."/custom/doliproject/view/timespent_day.php".($param ? '?'.$param : '');
		$head[$h][1] = $langs->trans("InputPerDay");
		$head[$h][2] = 'inputperday';
		$h++;
	}

	complete_head_from_modules($conf, $langs, null, $head, $h, 'project_timesheet');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'project_timesheet', 'remove');

	return $head;
}

/**
 * Sets object to given categories.
 *
 * Adds it to non existing supplied categories.
 * Deletes object from existing categories not supplied (if remove_existing==true).
 * Existing categories are left untouch.
 *
 * @param 	int[]|int 	$categories 		Category ID or array of Categories IDs
 * @param 	string 		$type_categ 		Category type ('customer', 'supplier', 'website_page', ...) definied into const class Categorie type
 * @param 	boolean		$remove_existing 	True: Remove existings categories from Object if not supplies by $categories, False: let them
 * @return	int							<0 if KO, >0 if OK
 */
function setCategoriesObject($categories, $type_categ = '', $remove_existing = true, $object)
{
	// Handle single category
	if (!is_array($categories)) {
		$categories = array($categories);
	}

	dol_syslog(get_class($object)."::setCategoriesCommon Oject Id:".$object->id.' type_categ:'.$type_categ.' nb tag add:'.count($categories), LOG_DEBUG);

	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

	if (empty($type_categ)) {
		dol_syslog(__METHOD__.': Type '.$type_categ.'is an unknown category type. Done nothing.', LOG_ERR);
		return -1;
	}

	// Get current categories
	$c = new Categorie($object->db);
	$existing = $c->containing($object->id, $type_categ, 'id');
	if ($remove_existing) {
		// Diff
		if (is_array($existing)) {
			$to_del = array_diff($existing, $categories);
			$to_add = array_diff($categories, $existing);
		} else {
			$to_del = array(); // Nothing to delete
			$to_add = $categories;
		}
	} else {
		$to_del = array(); // Nothing to delete
		$to_add = array_diff($categories, $existing);
	}

	$error = 0;
	$ok = 0;

	// Process
	foreach ($to_del as $del) {
		if ($c->fetch($del) > 0) {
			$result=$c->del_type($object, $type_categ);
			if ($result < 0) {
				$error++;
				$object->error = $c->error;
				$object->errors = $c->errors;
				break;
			} else {
				$ok += $result;
			}
		}
	}
	foreach ($to_add as $add) {
		if ($c->fetch($add) > 0) {
			$result = $c->add_type($object, $type_categ);
			if ($result < 0) {
				$error++;
				$object->error = $c->error;
				$object->errors = $c->errors;
				break;
			} else {
				$ok += $result;
			}
		}
	}

	return $error ? -1 * $error : $ok;
}

/**
 * Output a task line into a perday intput mode
 *
 * @param	string	   	$inc					Line output identificator (start to 0, then increased by recursive call)
 * @param	int			$firstdaytoshow			First day to show
 * @param	User|null	$fuser					Restrict list to user if defined
 * @param   string		$parent					Id of parent task to show (0 to show all)
 * @param   Task[]		$lines					Array of lines (list of tasks but we will show only if we have a specific role on task)
 * @param   int			$level					Level (start to 0, then increased/decrease by recursive call)
 * @param   string		$projectsrole			Array of roles user has on project
 * @param   string		$tasksrole				Array of roles user has on task
 * @param	string		$mine					Show only task lines I am assigned to
 * @param   int			$restricteditformytask	0=No restriction, 1=Enable add time only if task is assigned to me, 2=Enable add time only if tasks is assigned to me and hide others
 * @param   array       $isavailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function projectLinesPerDayOnMonth(&$inc, $firstdaytoshow, $fuser, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, &$isavailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null, $dayInMonth)
{
	global $conf, $db, $user, $langs;
	global $form, $formother, $projectstatic, $taskstatic, $thirdpartystatic;

	$numlines = count($lines);

	$lastprojectid = 0;
	$workloadforid = array();
	$totalforeachday = array();
	$lineswithoutlevel0 = array();

	// Create a smaller array with sublevels only to be used later. This increase dramatically performances.
	if ($parent == 0) { // Always and only if at first level
		for ($i = 0; $i < $numlines; $i++) {
			if ($lines[$i]->fk_task_parent) {
				$lineswithoutlevel0[] = $lines[$i];
			}
		}
	}

	//dol_syslog('projectLinesPerWeek inc='.$inc.' firstdaytoshow='.$firstdaytoshow.' task parent id='.$parent.' level='.$level." count(lines)=".$numlines." count(lineswithoutlevel0)=".count($lineswithoutlevel0));

	if (empty($oldprojectforbreak)) {
		$oldprojectforbreak = (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT) ? 0 : -1); // 0 = start break, -1 = never break
	}

	$restrictBefore = null;

	if (! empty($conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS)) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$restrictBefore = dol_time_plus_duree(dol_now(), - $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
	}

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
					$projectstatic->id = $lines[$i]->fk_project;
				}

				//var_dump('--- '.$level.' '.$firstdaytoshow.' '.$fuser->id.' '.$projectstatic->id.' '.$workloadforid[$projectstatic->id]);
				//var_dump($projectstatic->monthWorkLoadPerTask);
				if (empty($workloadforid[$projectstatic->id])) {
					loadTimeSpentMonthByDay($firstdaytoshow, 0, $fuser->id, $projectstatic); // Load time spent from table projet_task_time for the project into this->weekWorkLoad and this->monthWorkLoadPerTask for all days of a week
					$workloadforid[$projectstatic->id] = 1;
				}
				//var_dump('--- '.$projectstatic->id.' '.$workloadforid[$projectstatic->id]);

				$projectstatic->id = $lines[$i]->fk_project;
				$projectstatic->ref = $lines[$i]->projectref;
				$projectstatic->title = $lines[$i]->projectlabel;
				$projectstatic->public = $lines[$i]->public;
				$projectstatic->thirdparty_name = $lines[$i]->thirdparty_name;
				$projectstatic->status = $lines[$i]->projectstatus;

				$taskstatic->id = $lines[$i]->id;
				$taskstatic->ref = ($lines[$i]->ref ? $lines[$i]->ref : $lines[$i]->id);
				$taskstatic->label = $lines[$i]->label;
				$taskstatic->date_start = $lines[$i]->date_start;
				$taskstatic->date_end = $lines[$i]->date_end;

				$thirdpartystatic->id = $lines[$i]->thirdparty_id;
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
					if (!empty($arrayfields['timeconsumed']['checked'])) {
						$addcolspan++;
					}
					foreach ($arrayfields as $key => $val) {
						if ($val['checked'] && substr($key, 0, 5) == 'efpt.') {
							$addcolspan++;
						}
					}

					if ($conf->global->DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS) {
						$taskfavorite = isTaskFavorite($lines[$i]->id, $fuser->id);
					} else {
						$taskfavorite = 1;
					}

					print '<tr class="oddeven trforbreak nobold"'.(!$taskfavorite ? 'style="display:none;"': '').'>'."\n";
					print '<td colspan="'.(2 + $addcolspan + $dayInMonth).'">';
					print $projectstatic->getNomUrl(1, '', 0, '<strong>'.$langs->transnoentitiesnoconv("YourRole").':</strong> '.$projectsrole[$lines[$i]->fk_project]);
					if ($thirdpartystatic->id > 0) {
						print ' - '.$thirdpartystatic->getNomUrl(1);
					}
					if ($projectstatic->title) {
						print ' - ';
						print '<span class="secondary" title="'.$projectstatic->title.'">'.dol_trunc($projectstatic->title, '64').'</span>';
					}

					/*$colspan=5+(empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)?0:2);
					print '<table class="">';

					print '<tr class="liste_titre">';

					// PROJECT fields
					if (! empty($arrayfields['p.fk_opp_status']['checked'])) print_liste_field_titre($arrayfields['p.fk_opp_status']['label'], $_SERVER["PHP_SELF"], 'p.fk_opp_status', "", $param, '', $sortfield, $sortorder, 'center ');
					if (! empty($arrayfields['p.opp_amount']['checked']))    print_liste_field_titre($arrayfields['p.opp_amount']['label'], $_SERVER["PHP_SELF"], 'p.opp_amount', "", $param, '', $sortfield, $sortorder, 'right ');
					if (! empty($arrayfields['p.opp_percent']['checked']))   print_liste_field_titre($arrayfields['p.opp_percent']['label'], $_SERVER["PHP_SELF"], 'p.opp_percent', "", $param, '', $sortfield, $sortorder, 'right ');
					if (! empty($arrayfields['p.budget_amount']['checked'])) print_liste_field_titre($arrayfields['p.budget_amount']['label'], $_SERVER["PHP_SELF"], 'p.budget_amount', "", $param, '', $sortfield, $sortorder, 'right ');
					if (! empty($arrayfields['p.usage_bill_time']['checked']))     print_liste_field_titre($arrayfields['p.usage_bill_time']['label'], $_SERVER["PHP_SELF"], 'p.usage_bill_time', "", $param, '', $sortfield, $sortorder, 'right ');

					$extrafieldsobjectkey='projet';
					$extrafieldsobjectprefix='efp.';
					include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';

					print '</tr>';
					print '<tr>';

					// PROJECT fields
					if (! empty($arrayfields['p.fk_opp_status']['checked']))
					{
						print '<td class="nowrap">';
						$code = dol_getIdFromCode($db, $lines[$i]->fk_opp_status, 'c_lead_status', 'rowid', 'code');
						if ($code) print $langs->trans("OppStatus".$code);
						print "</td>\n";
					}
					if (! empty($arrayfields['p.opp_amount']['checked']))
					{
						print '<td class="nowrap">';
						print price($lines[$i]->opp_amount, 0, $langs, 1, 0, -1, $conf->currency);
						print "</td>\n";
					}
					if (! empty($arrayfields['p.opp_percent']['checked']))
					{
						print '<td class="nowrap">';
						print price($lines[$i]->opp_percent, 0, $langs, 1, 0).' %';
						print "</td>\n";
					}
					if (! empty($arrayfields['p.budget_amount']['checked']))
					{
						print '<td class="nowrap">';
						print price($lines[$i]->budget_amount, 0, $langs, 1, 0, 0, $conf->currency);
						print "</td>\n";
					}
					if (! empty($arrayfields['p.usage_bill_time']['checked']))
					{
						print '<td class="nowrap">';
						print yn($lines[$i]->usage_bill_time);
						print "</td>\n";
					}

					$extrafieldsobjectkey='projet';
					$extrafieldsobjectprefix='efp.';
					include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

					print '</tr>';
					print '</table>';
					*/

					print '</td>';
					print '</tr>';
				}

				if ($oldprojectforbreak != -1) {
					$oldprojectforbreak = $projectstatic->id;
				}

				if ($conf->global->DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS) {
					$taskfavorite = isTaskFavorite($lines[$i]->id, $fuser->id);
				} else {
					$taskfavorite = 1;
				}

				print '<tr class="oddeven"'.(!$taskfavorite ? 'style="display:none;"': '').'data-taskid="'.$lines[$i]->id.'" >'."\n";

				// User
				/*
				print '<td class="nowrap">';
				print $fuser->getNomUrl(1, 'withproject', 'time');
				print '</td>';
				*/

				// Project
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print '<td class="nowrap">';
					if ($oldprojectforbreak == -1) {
						print $projectstatic->getNomUrl(1, '', 0, $langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project]);
					}
					print "</td>";
				}

				// Thirdparty
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print '<td class="tdoverflowmax100">';
					if ($thirdpartystatic->id > 0) {
						print $thirdpartystatic->getNomUrl(1, 'project');
					}
					print '</td>';
				}

				// Ref
				print '<td class="nowrap">';
				print '<!-- Task id = '.$lines[$i]->id.' -->';
				for ($k = 0; $k < $level; $k++) {
					print '<div class="marginleftonly">';
				}
				print $taskstatic->getNomUrl(1, 'withproject', 'time');
				// Label task
				print '<br>';
				print '<span class="opacitymedium" title="'.$taskstatic->label.'">'.dol_trunc($taskstatic->label, '64').'</span>';
				for ($k = 0; $k < $level; $k++) {
					print "</div>";
				}
				print "</td>\n";

				// TASK extrafields
				$extrafieldsobjectkey = 'projet_task';
				$extrafieldsobjectprefix = 'efpt.';
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

				// Planned Workload
				if (!empty($arrayfields['t.planned_workload']['checked'])) {
					print '<td class="leftborder plannedworkload right">';
					if ($lines[$i]->planned_workload) {
						print convertSecondToTime($lines[$i]->planned_workload, 'allhourmin');
					} else {
						print '--:--';
					}
					print '</td>';
				}

				if (!empty($arrayfields['t.progress']['checked'])) {
					// Progress declared %
					print '<td class="right">';
					print $formother->select_percent($lines[$i]->progress, $lines[$i]->id.'progress');
					print '</td>';
				}

				if (!empty($arrayfields['timeconsumed']['checked'])) {
					// Time spent by user
					print '<td class="right">';
					$firstday = dol_print_date($firstdaytoshow, 'dayrfc');
					$currentMonth = date( 'm', dol_now());
					$year  = GETPOST('reyear', 'int') ?GETPOST('reyear', 'int') : (GETPOST("year", 'int') ?GETPOST("year", "int") : date("Y"));
					$month = GETPOST('remonth', 'int') ?GETPOST('remonth', 'int') : (GETPOST("month", 'int') ?GETPOST("month", "int") : date("m"));
					if ($currentMonth == $month) {
						$lastday = dol_print_date(dol_now(), 'dayrfc');
					} else {
						$lastdaytoshow =  dol_get_last_day($year, $month);
						$lastday = dol_print_date($lastdaytoshow, 'dayrfc');
					}
					$filter = ' AND t.task_datehour BETWEEN ' . "'" . $firstday . "'" . ' AND ' . "'" . $lastday . "'";
					$tmptimespent = $taskstatic->getSummaryOfTimeSpent($fuser->id, $filter);
					if ($tmptimespent['total_duration']) {
						print convertSecondToTime($tmptimespent['total_duration'], 'allhourmin');
					} else {
						print '--:--';
					}
					print "</td>\n";
				}

				$disabledproject = 1;
				$disabledtask = 1;
				//print "x".$lines[$i]->fk_project;
				//var_dump($lines[$i]);
				//var_dump($projectsrole[$lines[$i]->fk_project]);
				// If at least one role for project
				if ($lines[$i]->public || !empty($projectsrole[$lines[$i]->fk_project]) || $user->rights->projet->all->creer) {
					$disabledproject = 0;
					$disabledtask = 0;
				}
				// If $restricteditformytask is on and I have no role on task, i disable edit
				if ($restricteditformytask && empty($tasksrole[$lines[$i]->id])) {
					$disabledtask = 1;
				}

				//var_dump($projectstatic->monthWorkLoadPerTask);

				// Fields to show current time
				$tableCell = '';
				$modeinput = 'hours';
				for ($idw = 0; $idw < $dayInMonth; $idw++) {
					$tmpday = dol_time_plus_duree($firstdaytoshow, $idw, 'd');

					$cssonholiday = '';
					if (!$isavailable[$tmpday]['morning'] && !$isavailable[$tmpday]['afternoon']) {
						$cssonholiday .= 'onholidayallday ';
					} elseif (!$isavailable[$tmpday]['morning']) {
						$cssonholiday .= 'onholidaymorning ';
					} elseif (!$isavailable[$tmpday]['afternoon']) {
						$cssonholiday .= 'onholidayafternoon ';
					}

					$tmparray = dol_getdate($tmpday);
					$dayWorkLoad = $projectstatic->monthWorkLoadPerTask[$tmpday][$lines[$i]->id];
					$totalforeachday[$tmpday] += $dayWorkLoad;

					$alreadyspent = '';
					if ($dayWorkLoad > 0) {
						$alreadyspent = convertSecondToTime($dayWorkLoad, 'allhourmin');
					}
					$alttitle = $langs->trans("AddHereTimeSpentForDay", $tmparray['day'], $tmparray['mon']);

					global $numstartworkingday, $numendworkingday;
					$cssweekend = '';
					if (($idw + 1 < $numstartworkingday) || ($idw + 1 > $numendworkingday)) {	// This is a day is not inside the setup of working days, so we use a week-end css.
						//$cssweekend = 'weekend';
					}

					$disabledtaskday = $disabledtask;

					if (! $disabledtask && $restrictBefore && $tmpday < $restrictBefore) {
						$disabledtaskday = 1;
					}

					$tableCell = '<td class="center '.$idw.($cssonholiday ? ' '.$cssonholiday : '').($cssweekend ? ' '.$cssweekend : '').'">';
					//$tableCell .= 'idw='.$idw.' '.$conf->global->MAIN_START_WEEK.' '.$numstartworkingday.'-'.$numendworkingday;
					$placeholder = '';
					if ($alreadyspent) {
						$tableCell .= '<span class="timesheetalreadyrecorded" title="texttoreplace"><input type="text" class="center smallpadd" size="2" disabled id="timespent['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="'.$alreadyspent.'"></span>';
						//$placeholder=' placeholder="00:00"';
						//$tableCell.='+';
					}
					$tableCell .= '<input type="text" alt="'.($disabledtaskday ? '' : $alttitle).'" title="'.($disabledtaskday ? '' : $alttitle).'" '.($disabledtaskday ? 'disabled' : $placeholder).' class="center smallpadd" size="2" id="timeadded['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="" cols="2"  maxlength="5"';
					$tableCell .= ' onkeypress="return regexEvent(this,event,\'timeChar\')"';
					$tableCell .= ' onkeyup="updateTotal('.$idw.',\''.$modeinput.'\')"';
					$tableCell .= ' onblur="regexEvent(this,event,\''.$modeinput.'\'); updateTotal('.$idw.',\''.$modeinput.'\')" />';
					$tableCell .= '</td>';
					print $tableCell;
				}

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

			// Call to show task with a lower level (task under the current task)
			$inc++;
			$level++;
			if ($lines[$i]->id > 0) {
				//var_dump('totalforeachday after taskid='.$lines[$i]->id.' and previous one on level '.$level);
				//var_dump($totalforeachday);
				$ret = projectLinesPerDayOnMonth($inc, $firstdaytoshow, $fuser, $lines[$i]->id, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $isavailable, $oldprojectforbreak, $arrayfields, $extrafields, $dayInMonth);
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

/**
 * Load time spent into this->weekWorkLoad and this->weekWorkLoadPerTask for all day of a week of project.
 * Note: array weekWorkLoad and weekWorkLoadPerTask are reset and filled at each call.
 *
 * @param 	int		$datestart		First day of week (use dol_get_first_day to find this date)
 * @param 	int		$taskid			Filter on a task id
 * @param 	int		$userid			Time spent by a particular user
 * @return 	int						<0 if OK, >0 if KO
 */
function loadTimeSpentMonthByDay($datestart, $taskid = 0, $userid = 0, $project)
{
	$error = 0;

	$project->monthWorkLoad = array();
	$project->monthWorkLoadPerTask = array();

	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}

	$sql = "SELECT ptt.rowid as taskid, ptt.task_duration, ptt.task_date, ptt.task_datehour, ptt.fk_task";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time AS ptt, ".MAIN_DB_PREFIX."projet_task as pt";
	$sql .= " WHERE ptt.fk_task = pt.rowid";
	$sql .= " AND pt.fk_projet = ".((int) $project->id);
	$sql .= " AND (ptt.task_date >= '".$project->db->idate($datestart)."' ";
	$sql .= " AND ptt.task_date <= '".$project->db->idate(dol_time_plus_duree($datestart, 1, 'm') - 1)."')";
	if ($task_id) {
		$sql .= " AND ptt.fk_task=".((int) $taskid);
	}
	if (is_numeric($userid)) {
		$sql .= " AND ptt.fk_user=".((int) $userid);
	}

	//print $sql;
	$resql = $project->db->query($sql);
	if ($resql) {
		$daylareadyfound = array();

		$num = $project->db->num_rows($resql);
		$i = 0;
		// Loop on each record found, so each couple (project id, task id)
		while ($i < $num) {
			$obj = $project->db->fetch_object($resql);
			$day = $project->db->jdate($obj->task_date); // task_date is date without hours
			if (empty($daylareadyfound[$day])) {
				$project->monthWorkLoad[$day] = $obj->task_duration;
				$project->monthWorkLoadPerTask[$day][$obj->fk_task] = $obj->task_duration;
			} else {
				$project->monthWorkLoad[$day] += $obj->task_duration;
				$project->monthWorkLoadPerTask[$day][$obj->fk_task] += $obj->task_duration;
			}
			$daylareadyfound[$day] = 1;
			$i++;
		}
		$project->db->free($resql);
		return 1;
	} else {
		$project->error = "Error ".$project->db->lasterror();
		dol_syslog(get_class($project)."::fetch ".$project->error, LOG_ERR);
		return -1;
	}
}

/**
 * Output a task line into a perday intput mode
 *
 * @param	string	   	$inc					Line output identificator (start to 0, then increased by recursive call)
 * @param	int			$firstdaytoshow			First day to show
 * @param	User|null	$fuser					Restrict list to user if defined
 * @param   string		$parent					Id of parent task to show (0 to show all)
 * @param   Task[]		$lines					Array of lines (list of tasks but we will show only if we have a specific role on task)
 * @param   int			$level					Level (start to 0, then increased/decrease by recursive call)
 * @param   string		$projectsrole			Array of roles user has on project
 * @param   string		$tasksrole				Array of roles user has on task
 * @param	string		$mine					Show only task lines I am assigned to
 * @param   int			$restricteditformytask	0=No restriction, 1=Enable add time only if task is assigned to me, 2=Enable add time only if tasks is assigned to me and hide others
 * @param   array       $isavailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function projectLinesPerWeekDoliProject(&$inc, $firstdaytoshow, $fuser, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, &$isavailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null)
{
	global $conf, $db, $user, $langs;
	global $form, $formother, $projectstatic, $taskstatic, $thirdpartystatic;

	$numlines = count($lines);

	$lastprojectid = 0;
	$workloadforid = array();
	$totalforeachday = array();
	$lineswithoutlevel0 = array();

	// Create a smaller array with sublevels only to be used later. This increase dramatically performances.
	if ($parent == 0) { // Always and only if at first level
		for ($i = 0; $i < $numlines; $i++) {
			if ($lines[$i]->fk_task_parent) {
				$lineswithoutlevel0[] = $lines[$i];
			}
		}
	}

	//dol_syslog('projectLinesPerWeek inc='.$inc.' firstdaytoshow='.$firstdaytoshow.' task parent id='.$parent.' level='.$level." count(lines)=".$numlines." count(lineswithoutlevel0)=".count($lineswithoutlevel0));

	if (empty($oldprojectforbreak)) {
		$oldprojectforbreak = (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT) ? 0 : -1); // 0 = start break, -1 = never break
	}

	$restrictBefore = null;

	if (! empty($conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS)) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$restrictBefore = dol_time_plus_duree(dol_now(), - $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
	}

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
					$projectstatic->id = $lines[$i]->fk_project;
				}

				//var_dump('--- '.$level.' '.$firstdaytoshow.' '.$fuser->id.' '.$projectstatic->id.' '.$workloadforid[$projectstatic->id]);
				//var_dump($projectstatic->weekWorkLoadPerTask);
				if (empty($workloadforid[$projectstatic->id])) {
					$projectstatic->loadTimeSpent($firstdaytoshow, 0, $fuser->id); // Load time spent from table projet_task_time for the project into this->weekWorkLoad and this->weekWorkLoadPerTask for all days of a week
					$workloadforid[$projectstatic->id] = 1;
				}
				//var_dump($projectstatic->weekWorkLoadPerTask);
				//var_dump('--- '.$projectstatic->id.' '.$workloadforid[$projectstatic->id]);

				$projectstatic->id = $lines[$i]->fk_project;
				$projectstatic->ref = $lines[$i]->projectref;
				$projectstatic->title = $lines[$i]->projectlabel;
				$projectstatic->public = $lines[$i]->public;
				$projectstatic->thirdparty_name = $lines[$i]->thirdparty_name;
				$projectstatic->status = $lines[$i]->projectstatus;

				$taskstatic->id = $lines[$i]->id;
				$taskstatic->ref = ($lines[$i]->ref ? $lines[$i]->ref : $lines[$i]->id);
				$taskstatic->label = $lines[$i]->label;
				$taskstatic->date_start = $lines[$i]->date_start;
				$taskstatic->date_end = $lines[$i]->date_end;

				$thirdpartystatic->id = $lines[$i]->thirdparty_id;
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

					if ($conf->global->DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS) {
						$taskfavorite = isTaskFavorite($lines[$i]->id, $fuser->id);
					} else {
						$taskfavorite = 1;
					}

					print '<tr class="oddeven trforbreak nobold"'.(!$taskfavorite ? 'style="display:none;"': '').'>'."\n";
					print '<td colspan="'.(11 + $addcolspan).'">';
					print $projectstatic->getNomUrl(1, '', 0, '<strong>'.$langs->transnoentitiesnoconv("YourRole").':</strong> '.$projectsrole[$lines[$i]->fk_project]);
					if ($thirdpartystatic->id > 0) {
						print ' - '.$thirdpartystatic->getNomUrl(1);
					}
					if ($projectstatic->title) {
						print ' - ';
						print '<span class="secondary" title="'.$projectstatic->title.'">'.dol_trunc($projectstatic->title, '64').'</span>';
					}

					/*$colspan=5+(empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)?0:2);
					print '<table class="">';

					print '<tr class="liste_titre">';

					// PROJECT fields
					if (! empty($arrayfields['p.fk_opp_status']['checked'])) print_liste_field_titre($arrayfields['p.fk_opp_status']['label'], $_SERVER["PHP_SELF"], 'p.fk_opp_status', "", $param, '', $sortfield, $sortorder, 'center ');
					if (! empty($arrayfields['p.opp_amount']['checked']))    print_liste_field_titre($arrayfields['p.opp_amount']['label'], $_SERVER["PHP_SELF"], 'p.opp_amount', "", $param, '', $sortfield, $sortorder, 'right ');
					if (! empty($arrayfields['p.opp_percent']['checked']))   print_liste_field_titre($arrayfields['p.opp_percent']['label'], $_SERVER["PHP_SELF"], 'p.opp_percent', "", $param, '', $sortfield, $sortorder, 'right ');
					if (! empty($arrayfields['p.budget_amount']['checked'])) print_liste_field_titre($arrayfields['p.budget_amount']['label'], $_SERVER["PHP_SELF"], 'p.budget_amount', "", $param, '', $sortfield, $sortorder, 'right ');
					if (! empty($arrayfields['p.usage_bill_time']['checked']))     print_liste_field_titre($arrayfields['p.usage_bill_time']['label'], $_SERVER["PHP_SELF"], 'p.usage_bill_time', "", $param, '', $sortfield, $sortorder, 'right ');

					$extrafieldsobjectkey='projet';
					$extrafieldsobjectprefix='efp.';
					include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';

					print '</tr>';
					print '<tr>';

					// PROJECT fields
					if (! empty($arrayfields['p.fk_opp_status']['checked']))
					{
						print '<td class="nowrap">';
						$code = dol_getIdFromCode($db, $lines[$i]->fk_opp_status, 'c_lead_status', 'rowid', 'code');
						if ($code) print $langs->trans("OppStatus".$code);
						print "</td>\n";
					}
					if (! empty($arrayfields['p.opp_amount']['checked']))
					{
						print '<td class="nowrap">';
						print price($lines[$i]->opp_amount, 0, $langs, 1, 0, -1, $conf->currency);
						print "</td>\n";
					}
					if (! empty($arrayfields['p.opp_percent']['checked']))
					{
						print '<td class="nowrap">';
						print price($lines[$i]->opp_percent, 0, $langs, 1, 0).' %';
						print "</td>\n";
					}
					if (! empty($arrayfields['p.budget_amount']['checked']))
					{
						print '<td class="nowrap">';
						print price($lines[$i]->budget_amount, 0, $langs, 1, 0, 0, $conf->currency);
						print "</td>\n";
					}
					if (! empty($arrayfields['p.usage_bill_time']['checked']))
					{
						print '<td class="nowrap">';
						print yn($lines[$i]->usage_bill_time);
						print "</td>\n";
					}

					$extrafieldsobjectkey='projet';
					$extrafieldsobjectprefix='efp.';
					include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

					print '</tr>';
					print '</table>';
					*/

					print '</td>';
					print '</tr>';
				}

				if ($oldprojectforbreak != -1) {
					$oldprojectforbreak = $projectstatic->id;
				}

				if ($conf->global->DOLIPROJECT_SHOW_ONLY_FAVORITE_TASKS) {
					$taskfavorite = isTaskFavorite($lines[$i]->id, $fuser->id);
				} else {
					$taskfavorite = 1;
				}

				print '<tr class="oddeven"'.(!$taskfavorite ? 'style="display:none;"': '').'data-taskid="'.$lines[$i]->id.'">'."\n";

				// User
				/*
				print '<td class="nowrap">';
				print $fuser->getNomUrl(1, 'withproject', 'time');
				print '</td>';
				*/

				// Project
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print '<td class="nowrap">';
					if ($oldprojectforbreak == -1) {
						print $projectstatic->getNomUrl(1, '', 0, $langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project]);
					}
					print "</td>";
				}

				// Thirdparty
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print '<td class="tdoverflowmax100">';
					if ($thirdpartystatic->id > 0) {
						print $thirdpartystatic->getNomUrl(1, 'project');
					}
					print '</td>';
				}

				// Ref
				print '<td class="nowrap">';
				print '<!-- Task id = '.$lines[$i]->id.' -->';
				for ($k = 0; $k < $level; $k++) {
					print '<div class="marginleftonly">';
				}
				print $taskstatic->getNomUrl(1, 'withproject', 'time');
				// Label task
				print '<br>';
				print '<span class="opacitymedium" title="'.$taskstatic->label.'">'.dol_trunc($taskstatic->label, '64').'</span>';
				for ($k = 0; $k < $level; $k++) {
					print "</div>";
				}
				print "</td>\n";

				// TASK extrafields
				$extrafieldsobjectkey = 'projet_task';
				$extrafieldsobjectprefix = 'efpt.';
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

				// Planned Workload
				if (!empty($arrayfields['t.planned_workload']['checked'])) {
					print '<td class="leftborder plannedworkload right">';
					if ($lines[$i]->planned_workload) {
						print convertSecondToTime($lines[$i]->planned_workload, 'allhourmin');
					} else {
						print '--:--';
					}
					print '</td>';
				}

				if (!empty($arrayfields['t.progress']['checked'])) {
					// Progress declared %
					print '<td class="right">';
					print $formother->select_percent($lines[$i]->progress, $lines[$i]->id.'progress');
					print '</td>';
				}

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
					$firstday = dol_print_date($firstdaytoshow, 'dayrfc');
					$CurrentDate = dol_getdate(dol_now());
					$currentWeek = getWeekNumber($CurrentDate['mday'], $CurrentDate['mon'], $CurrentDate['year']);
					$Date = dol_getdate($firstdaytoshow);
					$Week = getWeekNumber($Date['mday'], $Date['mon'], $Date['year']);

					if ($currentWeek == $Week) {
						$firstDay = date( 'd', $firstdaytoshow);
						$currentDay = date( 'd', dol_now());
						$nbday = $currentDay - $firstDay;
					} else {
						$nbday = 6;
					}
					$lastdaytoshow = dol_time_plus_duree($firstdaytoshow, $nbday, 'd');

					$lastday = dol_print_date($lastdaytoshow, 'dayrfc');
					$filter = ' AND t.task_datehour BETWEEN ' . "'" . $firstday . "'" . ' AND ' . "'" . $lastday . "'";
					$tmptimespent = $taskstatic->getSummaryOfTimeSpent($fuser->id, $filter);
					if ($tmptimespent['total_duration']) {
						print convertSecondToTime($tmptimespent['total_duration'], 'allhourmin');
					} else {
						print '--:--';
					}
					print "</td>\n";
				}

				$disabledproject = 1;
				$disabledtask = 1;
				//print "x".$lines[$i]->fk_project;
				//var_dump($lines[$i]);
				//var_dump($projectsrole[$lines[$i]->fk_project]);
				// If at least one role for project
				if ($lines[$i]->public || !empty($projectsrole[$lines[$i]->fk_project]) || $user->rights->projet->all->creer) {
					$disabledproject = 0;
					$disabledtask = 0;
				}
				// If $restricteditformytask is on and I have no role on task, i disable edit
				if ($restricteditformytask && empty($tasksrole[$lines[$i]->id])) {
					$disabledtask = 1;
				}

				//var_dump($projectstatic->weekWorkLoadPerTask);

				// Fields to show current time
				$tableCell = '';
				$modeinput = 'hours';
				for ($idw = 0; $idw < 7; $idw++) {
					$tmpday = dol_time_plus_duree($firstdaytoshow, $idw, 'd');

					$cssonholiday = '';
					if (!$isavailable[$tmpday]['morning'] && !$isavailable[$tmpday]['afternoon']) {
						$cssonholiday .= 'onholidayallday ';
					} elseif (!$isavailable[$tmpday]['morning']) {
						$cssonholiday .= 'onholidaymorning ';
					} elseif (!$isavailable[$tmpday]['afternoon']) {
						$cssonholiday .= 'onholidayafternoon ';
					}

					$tmparray = dol_getdate($tmpday);
					$dayWorkLoad = $projectstatic->weekWorkLoadPerTask[$tmpday][$lines[$i]->id];
					$totalforeachday[$tmpday] += $dayWorkLoad;

					$alreadyspent = '';
					if ($dayWorkLoad > 0) {
						$alreadyspent = convertSecondToTime($dayWorkLoad, 'allhourmin');
					}
					$alttitle = $langs->trans("AddHereTimeSpentForDay", $tmparray['day'], $tmparray['mon']);

					global $numstartworkingday, $numendworkingday;
					$cssweekend = '';
					if (($idw + 1 < $numstartworkingday) || ($idw + 1 > $numendworkingday)) {	// This is a day is not inside the setup of working days, so we use a week-end css.
						$cssweekend = 'weekend';
					}

					$disabledtaskday = $disabledtask;

					if (! $disabledtask && $restrictBefore && $tmpday < $restrictBefore) {
						$disabledtaskday = 1;
					}

					$tableCell = '<td class="center hide'.$idw.($cssonholiday ? ' '.$cssonholiday : '').($cssweekend ? ' '.$cssweekend : '').'">';
					//$tableCell .= 'idw='.$idw.' '.$conf->global->MAIN_START_WEEK.' '.$numstartworkingday.'-'.$numendworkingday;
					$placeholder = '';
					if ($alreadyspent) {
						$tableCell .= '<span class="timesheetalreadyrecorded" title="texttoreplace"><input type="text" class="center smallpadd" size="2" disabled id="timespent['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="'.$alreadyspent.'"></span>';
						//$placeholder=' placeholder="00:00"';
						//$tableCell.='+';
					}
					$tableCell .= '<input type="text" alt="'.($disabledtaskday ? '' : $alttitle).'" title="'.($disabledtaskday ? '' : $alttitle).'" '.($disabledtaskday ? 'disabled' : $placeholder).' class="center smallpadd" size="2" id="timeadded['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="" cols="2"  maxlength="5"';
					$tableCell .= ' onkeypress="return regexEvent(this,event,\'timeChar\')"';
					$tableCell .= ' onkeyup="updateTotal('.$idw.',\''.$modeinput.'\')"';
					$tableCell .= ' onblur="regexEvent(this,event,\''.$modeinput.'\'); updateTotal('.$idw.',\''.$modeinput.'\')" />';
					$tableCell .= '</td>';
					print $tableCell;
				}

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

			// Call to show task with a lower level (task under the current task)
			$inc++;
			$level++;
			if ($lines[$i]->id > 0) {
				//var_dump('totalforeachday after taskid='.$lines[$i]->id.' and previous one on level '.$level);
				//var_dump($totalforeachday);
				$ret = projectLinesPerWeekDoliProject($inc, $firstdaytoshow, $fuser, $lines[$i]->id, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $isavailable, $oldprojectforbreak, $arrayfields, $extrafields);
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
