<?php
/* Copyright (C) 2020 SuperAdmin <gagluiome@gmail.com>
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
 * \file    core/triggers/interface_99_modDoliproject_DoliprojectTriggers.class.php
 * \ingroup doliproject
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modDoliproject_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 * - The constructor method must be named InterfaceMytrigger
 * - The name property name must be MyTrigger
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for Doliproject module
 */
class InterfaceDoliprojectTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "Doliproject triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.3.0';
		$this->picto = 'doliproject@doliproject';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->doliproject->enabled)) return 0; // If module is not enabled, we do nothing

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		switch ($action) {
			// Actions
			case 'ACTION_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				if (((int)$object->fk_element) > 0 && $object->elementtype == 'ticket' && preg_match('/^TICKET_/s',$object->code)) {
					dol_syslog("Add time spent");
					$result= 0;
					$ticket = new Ticket($this->db);
					$result = $ticket->fetch($object->fk_element);
					dol_syslog(var_export($ticket, true), LOG_DEBUG);
					if ($result > 0 && ((int)$ticket->id) > 0) {
						if (is_array($ticket->array_options) && array_key_exists('options_fk_task',$ticket->array_options) && $ticket->array_options['options_fk_task']>0) {
							require_once DOL_DOCUMENT_ROOT .'/projet/class/task.class.php';
							$task = new Task($this->db);
							$result = $task->fetch($ticket->array_options['options_fk_task']);
							dol_syslog(var_export($task, true), LOG_DEBUG);
							if ($result > 0 && ((int)$task->id) > 0) {
								$task->timespent_note = $object->note_private;
								$task->timespent_duration = GETPOST('timespent','int') * 60; // We store duration in seconds
								$task->timespent_date = dol_now();
								$task->timespent_withhour = 1;
								$task->timespent_fk_user = $user->id;

								$id_message = $task->id;
								$name_message = $task->ref;

								$result = $task->addTimeSpent($user);
								setEventMessages($langs->trans("MessageTimeSpentCreate").' : '.'<a href="'.DOL_URL_ROOT.'/projet/tasks/time.php?id='.$id_message.'">'.$name_message.'</a>', null, 'mesgs');
							} else {
								setEventMessages($task->error,$task->errors,'errors');
								return -1;
							}
						}
					} else {
						setEventMessages($ticket->error,$ticket->errors,'errors');
						return -1;
					}
				}
			break;

			case 'BILL_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once __DIR__ . '/../../lib/doliproject_function.lib.php';
				$categories = GETPOST('categories', 'array:int');
				setCategoriesObject($categories, 'invoice', false, $object);
				break;

			case 'BILLREC_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
				require_once __DIR__ . '/../../lib/doliproject_function.lib.php';
				$cat = new Categorie($this->db);
				$categories = $cat->containing(GETPOST('facid'),'invoice');
				if (is_array($categories) && !empty($categories)) {
					foreach ($categories as $category) {
						$categoryArray[] =  $category->id;
					}
				}
				setCategoriesObject($categoryArray, 'invoicerec', false, $object);
				break;

			// Timesheet
			case 'TIMESHEET_CREATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

				require_once __DIR__ . '/../../class/timesheet.class.php';

				$signatory  = new TimeSheetSignature($this->db);
				$usertmp    = new User($this->db);
				$product    = new Product($this->db);
				$objectline = new TimeSheetLine($this->db);

				$usertmp->fetch($object->fk_user_assign);

				$signatory->setSignatory($object->id, 'timesheet', 'user', array($object->fk_user_assign), 'TIMESHEET_SOCIETY_ATTENDANT');
				$signatory->setSignatory($object->id, 'timesheet', 'user', array($usertmp->fk_user), 'TIMESHEET_SOCIETY_RESPONSIBLE');

				$now = dol_now();

				if ($conf->global->DOLIPROJECT_PRODUCT_SERVICE_SET) {
					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities("MealTicket")))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 1;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 0;
					$objectline->insert($user);

					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities("JourneySubscription")))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 2;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 1;
					$objectline->insert($user);

					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities("13thMonthBonus")))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 3;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 1;
					$objectline->insert($user);

					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities("SpecialBonus")))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 4;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 1;
					$objectline->insert($user);
				}
				break;

			case 'TIMESHEET_MODIFY' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@doliproject';
				$actioncomm->code        = 'AC_TIMESHEET_MODIFY';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetModifyTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_DELETE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@doliproject';
				$actioncomm->code        = 'AC_TIMESHEET_DELETE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetDeleteTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_VALIDATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@doliproject';
				$actioncomm->code        = 'AC_TIMESHEET_VALIDATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetValidateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_UNVALIDATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@doliproject';
				$actioncomm->code        = 'AC_TIMESHEET_UNVALIDATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetUnValidateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_LOCKED' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@doliproject';
				$actioncomm->code        = 'AC_TIMESHEET_LOCKED';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetLockedTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_ARCHIVED' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@doliproject';
				$actioncomm->code        = 'AC_TIMESHEET_ARCHIVED';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetArchivedTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'ECMFILES_CREATE' :
				if ($object->src_object_type == 'doliproject_timesheet') {
					dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
					require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

					require_once __DIR__ . '/../../class/timesheet.class.php';

					$now        = dol_now();
					$signatory  = new TimeSheetSignature($this->db);
					$actioncomm = new ActionComm($this->db);

					$signatories = $signatory->fetchSignatories($object->src_object_id, 'timesheet');

					if (!empty($signatories) && $signatories > 0) {
						foreach ($signatories as $signatory) {
							$signatory->signature = $langs->transnoentities("FileGenerated");
							$signatory->update($user, false);
						}
					}

					$actioncomm->elementtype = 'timesheet@doliproject';
					$actioncomm->code        = 'AC_TIMESHEET_GENERATE';
					$actioncomm->type_code   = 'AC_OTH_AUTO';
					$actioncomm->label       = $langs->trans('TimeSheetGenerateTrigger');
					$actioncomm->datep       = $now;
					$actioncomm->fk_element  = $object->src_object_id;
					$actioncomm->userownerid = $user->id;
					$actioncomm->percentage  = -1;

					$actioncomm->create($user);
				}
				break;

			case 'DOLIPROJECTSIGNATURE_ADDATTENDANT' :
				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@doliproject';
				$actioncomm->code              = 'AC_DOLIPROJECTSIGNATURE_ADDATTENDANT';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliProjectAddAttendantTrigger', $object->firstname . ' ' . $object->lastname);
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			case 'DOLIPROJECTSIGNATURE_SIGNED' :
				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = $object->object_type . '@doliproject';
				$actioncomm->code        = 'AC_DOLIPROJECTSIGNATURE_SIGNED';
				$actioncomm->type_code   = 'AC_OTH_AUTO';

				$actioncomm->label = $langs->transnoentities('DoliProjectSignatureSignedTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;

				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'DOLIPROJECTSIGNATURE_PENDING_SIGNATURE' :

				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@doliproject';
				$actioncomm->code              = 'AC_DOLIPROJECTSIGNATURE_PENDING_SIGNATURE';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliProjectSignaturePendingSignatureTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			case 'DOLIPROJECTSIGNATURE_ABSENT' :

				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@doliproject';
				$actioncomm->code              = 'AC_DOLIPROJECTSIGNATURE_ABSENT';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliProjectSignatureAbsentTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			case 'DOLIPROJECTSIGNATURE_DELETED' :

				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@doliproject';
				$actioncomm->code              = 'AC_DOLIPROJECTSIGNATURE_DELETED';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliProjectSignatureDeletedTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}
		return 0;
	}
}
