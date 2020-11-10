<?php
/* Copyright (C) 2020 SuperAdmin
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
 * \file    doliproject/class/actions_doliproject.class.php
 * \ingroup doliproject
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsDoliproject
 */
class ActionsDoliproject
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/modules/project/task/mod_task_simple.php';

		global $conf, $user, $langs;

		$mod = new mod_task_simple;
		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('invoicecard')))
		{
			if (in_array('invoicecard', explode(':', $parameters['context']))) {
				// Action that will be done after pressing the button
				if ($action == 'createtask-doliproject') {

					// Start
					// Variable : ref
					// Description : create the ref of the task
					$ref = $mod->getNextValue(0, "");
					// End

					//Start
					//Variable : label
					//Description : creation of the label of the task

					//Contruction de la chaine de caractère sur le modèle AAAAMMJJ-nomprojet-tag
					//Variable : datef = Date de début de période de facturation
					$query = "SELECT datef, ref FROM ".MAIN_DB_PREFIX."facture";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['ref'] == $object->ref) {
							$datef_invoice[0] = $row['datef'];
						}
					}
					$datef_invoice = explode('-', $datef_invoice[0]);
					$datef = implode($datef_invoice);
					//datef
				
					// Contruction de la chaine de caractère REGEX : AAAAMMJJ-nomprojet-tag
					// Wording retrieval
					$fk_projet_fac = $object->fk_project;
					$query = "SELECT rowid, title FROM ".MAIN_DB_PREFIX."projet";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['rowid'] == $object->fk_project) {
							$title[0] = $row['title'];
						}
					}
					$wording = $title[0];

					//Tag retrieval
					//@todo REGEX à construire dans les réglages dans notre cas : DATEDEBUTPERIODE-NOMPROJET-TAGS EX: 20200801-eoxia.fr-ref
					$query = "SELECT ref, fk_projet FROM ".MAIN_DB_PREFIX."facture";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['ref'] == $object->ref) {
							$invoice_fk_projet[0] = $row['fk_projet'];
						}
					}
					$query = "SELECT fk_project, fk_categorie FROM ".MAIN_DB_PREFIX."categorie_project";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['fk_project'] == $invoice_fk_projet[0]) {
							$fk_categorie[0] = $row['fk_categorie'];
						}
					}
					$query = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."categorie";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['rowid'] == $fk_categorie[0]) {
							$tag[0] = $row['label'];
						}
					}
					//Concatenation of the date, wording and tag to obtain the label
					$label = $datef . '-' . $wording . '-' . $tag[0];
					//End

					//Start
					//Variable : fk_projet
					//Description : take the fk_projet from the invoice
					$fk_projet = $object->fk_project;
					//End

					//Start
					//Variable : dateo
					//Decription : retrieval of the start date of the invoice
					unset($datef_invoice);
					$query = "SELECT datef, ref FROM ".MAIN_DB_PREFIX."facture";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['ref'] == $object->ref) {
							$datef_invoice[0] = $row['datef'];
						}
					}
					$dateo = implode($datef_invoice);
					//End

					//Start
					//Variable : datee
					//Description : retrieval of the end date of the invoice
					unset($datef_invoice);
					$query = "SELECT fk_fac_rec_source, datef, ref FROM ".MAIN_DB_PREFIX."facture";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['ref'] == $object->ref) {
							$datef_invoice[0] = $row['datef'];
						}
						if ($row['fk_fac_rec_source']) {
							$fk_fac_rec_source_invoice[0] = $row['fk_fac_rec_source'];
						}
					}
					$datef_invoice = explode('-', $datef_invoice[0]);
					$query = "SELECT rowid, frequency, unit_frequency FROM ".MAIN_DB_PREFIX."facture_rec";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['rowid'] == $fk_fac_rec_source_invoice[0]) {
							$frequency_invoice_rec[0] = $row['frequency'];
							$unit_frequency_invoice_rec[0] = $row['unit_frequency'];
						}
					}
					$day = $datef_invoice[2];
					$month = $datef_invoice[1];
					$year = $datef_invoice[0];
					$hour = 0;
					$minute = 0;
					$second = 0;
					//Creation of the end date of the invoice with the start date according to the frequency
					if ($unit_frequency_invoice_rec[0] == 'd') {
						$datee = date("Y-m-d H:i:s", mktime($hour, $minute, $second - 1, $month, $day + 1, $year));
					} elseif ($unit_frequency_invoice_rec[0] == 'm') {
						$datee = date("Y-m-d", mktime($hour, $minute, $second, $month + 1, 0, $year));
					} elseif ($unit_frequency_invoice_rec[0] == 'y') {
						$datee = date("Y-m-d", mktime($hour, $minute, $second, $month, 0, $year + 1));
					}
					//End

					//Start
					//Variable : planned_workload
					//Description : time calculation of the planned workload
					//We recover all the products from the invoice
					$query = "SELECT rowid, ref FROM ".MAIN_DB_PREFIX."facture";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['ref'] == $object->ref) {
							$rowid_invoice[0] = $row['rowid'];
						}
					}
					$i = 0;
					//We recover the quantity of all the products
					$query = "SELECT fk_facture, fk_product, qty FROM ".MAIN_DB_PREFIX."facturedet";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						if ($row['fk_facture'] == $rowid_invoice[0]) {
							$fk_product[$i] = $row['fk_product'];
							$fk_quantity[$i] = $row['qty'];
							$i += 1;
						}
					}
					$i = 0;
					$j = 0;
					//We recover the time of each product
					$query = "SELECT rowid, duration FROM ".MAIN_DB_PREFIX."product";
					$result = $this->db->query($query);
					while ($row = $result->fetch_array()) {
						while (isset($fk_product[$i])) {
							if ($row['rowid'] == $fk_product[$i]) {
								$duration[$i] = $row['duration'];
								$i += 1;
							}
							$i += 1;
						}
						$i = 0;
					}
					$i = 0;
					$j = 0;
					// We transform time into seconds
					while (isset($duration[$i])) {
						while (isset($duration[$i][$j])) {
							if ($duration[$i][$j] == 's') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 1;
							} elseif ($duration[$i][$j] == 'i') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 60;
							} elseif ($duration[$i][$j] == 'h') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 3600;
							} elseif ($duration[$i][$j] == 'd') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 86400;
							} elseif ($duration[$i][$j] == 'w') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 604800;
							} elseif ($duration[$i][$j] == 'm') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 2592000;
							} elseif ($duration[$i][$j] == 'y') {
								$duration[$i] = substr($duration[$i], 0, -1);
								$duration[$i] *= 31104000;
							}
							$j += 1;
						}
						$i += 1;
						$j = 0;
					}
					$i = 0;
					//We multiply the time by the duration
					while (isset($duration[$i])) {
						if (is_int($duration[$i])) {
							$duration[$i] *= intval($fk_quantity[$i]);
						}
						$i += 1;
					}
					$i = 0;
					//We add all the time to all the products
					$planned_workload = 0;
					while (isset($duration[$i])) {
						$planned_workload += intval($duration[$i]);
						$i += 1;
					}
					//End

					//Start
					//Filling of the llx_projet_task table with the variables to create the task
					if ($fk_projet && $planned_workload != 0) {
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'projet_task(ref, fk_projet, label, dateo, datee, planned_workload) VALUES("'.$ref.'", '.intval($fk_projet).', "'.$label.'", "'.$dateo.'", "'.$datee.'", '.intval($planned_workload).')';
						$this->db->query($req);
						$query = "SELECT rowid, ref, fk_projet FROM ".MAIN_DB_PREFIX."projet_task";
						$result = $this->db->query($query);
						while ($row = $result->fetch_array()) {
							if ($row['rowid']) {
								$rowid_last_task[0] = $row['rowid'];
							}
							if ($row['ref']) {
								$ref_last_task[0] = $row['ref'];
							}
						}
						//Filling of the llx_projet_task_extrafields table
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'projet_task_extrafields(fk_object, fk_facture_name) VALUES('.$rowid_last_task[0].', '.$object->id.')';
						$this->db->query($req);
						//Filling of the llx_facture_extrafields table
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'facture_extrafields(fk_object, fk_task) VALUES('.$object->lines[0]->fk_facture.', '.$rowid_last_task[0].')';
						$this->db->query($req);
						setEventMessages('<a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$rowid_last_task[0].'">'.$langs->trans("MessageInfo").'</a>', null, 'mesgs');
						//End
					}
					else {
						if ($fk_projet) {
							setEventMessages($langs->trans("MessageInfoNoCreateTime"), null, 'errors');
						}
						else if ($planned_workload != 0) {
							setEventMessages($langs->trans("MessageInfoNoCreateProject"), null, 'errors');
						}
					}
				}
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		
		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('invoicecard')))
		{
			//Creation of the link that will be send
			if ( isset( $_SERVER['HTTPS'] ) ) {
				if ( $_SERVER['HTTPS'] == 'on' ) {
				$server_protocol = 'https';
				} else {
				$server_protocol = 'http';
				} 
			} else {
				$server_protocol = 'http';
			}
			$actual_link = $server_protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$actual_link .= '&action=createtask-doliproject'; //Action

			//Check if the invoice is already linked to the task
			$error_button = 0;
			$query = "SELECT fk_facture_name FROM ".MAIN_DB_PREFIX."projet_task_extrafields";
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				if ($row['fk_facture_name'] == $object->id) {
					$error_button = 1;
				}
			}

			//Button
			if ($error_button == 0) {
				print '<div class="inline-block divButAction"><a class="butAction" href="'. $actual_link .'">Créer tâche</a></div>';
			}

		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/* Add here any other hooked methods... */
}
