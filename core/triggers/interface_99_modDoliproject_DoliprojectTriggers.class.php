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
		$this->version = 'development';
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

			case 'ECMFILES_CREATE':

				// echo '<pre>';
				// echo '<br><br><br>';
				dol_syslog($langs->trans('StartTask'), 6);

				//Connection to the db dolibarr
				try {
					$bdd = new PDO("mysql:host=" . $conf->db->host . ";dbname=" . $conf->db->name . ";charset=UTF8", $conf->db->user, $conf->db->pass);
					dol_syslog($langs->trans('ConnectionDBSuccess'), 6);
				} catch (Exception $e) {
					dol_syslog($langs->trans('ConnectionDBFailed').__line__, 4);
					die('Erreur : ' . $e->getMessage());
				}

				//Generate variables to insert in the llx_projet_task table
				dol_syslog($langs->trans('StartVariable'), 6);

				//Start
				//Variable : ref
				//Description : take the name of the last task
				$data = $bdd->query('SELECT rowid, fk_projet, ref FROM llx_projet_task');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['ref']) {
						$ref_last_task[0] = $data_cut['ref'];
					}
					if ($data_cut['rowid']) {
						$rowid_last_task[0] = $data_cut['rowid'];
					}
				}

				//Increase the number of the task taken
				$ref_last_task = str_split($ref_last_task[0], 5);
				$length = 0;
				while ($ref_last_task[$length] != NULL) {
					$length += 1;
				}
				$ref_last_task[$length - 1] += 1;
				$ref = implode($ref_last_task);
				dol_syslog($langs->trans('VRef') . $ref, 6);
				// echo '$ref = ' . $ref . '<br>';
				// echo '<br><br><br>';
				//End

				//Start
				//Variable : fk_projet
				//Description : take the fk_projet from the invoice
				$data = $bdd->query('SELECT fk_projet, ref FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['ref']) {
						$fk_projet_last_invoice[0] = $data_cut['fk_projet'];
						$ref_last_invoice[0] = $data_cut['ref'];
					}
				}
				$fk_projet = implode($fk_projet_last_invoice);
				dol_syslog($langs->trans('Vfk_projet') . $fk_projet, 6);
				// echo '$fk_projet = ' . $fk_projet . '<br>';
				// echo '<br><br><br>';
				//End

				//Start
				//Variable : label
				//Description : creation of the label of the task
				//Retrieval of the project wording
				$data = $bdd->query('SELECT rowid, title, ref FROM llx_projet');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['rowid'] == $fk_projet) {
						$rowid_projet = $data_cut['rowid'];
					}
					if ($data_cut['rowid'] == $fk_projet) {
						$title_projet[0] = $data_cut['title'];
					}
					if ($data_cut['rowid'] == $fk_projet) {
						$ref_projet[0] = $data_cut['ref'];
					}
				}
				if ($title_projet) {
					$wording = implode($title_projet);
				}
				//Tag retrieval
				$data = $bdd->query('SELECT note_private FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['note_private']) {
						$note_private_invoice[0] = $data_cut['note_private'];
					}
				}
				$note_private_invoice = explode(' ', $note_private_invoice[0]);
				if (in_array('Hebergement', $note_private_invoice)) {
					$tag = "SAV";
				}
				if (in_array('Referencement', $note_private_invoice)) {
					$tag = "REF";
				}
				//Date retrieval
				$data = $bdd->query('SELECT datef FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['datef']) {
						$datef_invoice[0] = $data_cut['datef'];
					}
				}
				$datef_invoice = explode('-', $datef_invoice[0]);
				$datef = implode($datef_invoice);
				//Concatenation of the date, wording and tag to obtain the label
				$label = $datef . '-' . $wording . '-' . $tag;
				dol_syslog($langs->trans('Vlabel') . $label, 6);
				// echo '$label = ' . $label . '<br>';
				// echo '<br><br><br>';
				//End

				//Start
				//Variable : description
				//Description : creation of the description of the task
				unset($ref_last_invoice);
				$data = $bdd->query('SELECT fk_projet, ref FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['ref']) {
						$ref_last_invoice[0] = $data_cut['ref'];
					}
				}
				$ref_last_invoice = implode($ref_last_invoice);
				$description = $langs->trans('DescriptionTask') . $ref_last_invoice;
				dol_syslog($langs->trans('Vdescription') . $description, 6);
				// echo '$description = ' . $description . '<br>';
				// echo '<br><br><br>';
				//End

				//Start
				//Variable : dateo
				//Decription : retrieval of the start date of the invoice
				unset($datef_invoice);
				$data = $bdd->query('SELECT datef FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['datef']) {
						$datef_invoice[0] = $data_cut['datef'];
					}
				}
				$dateo = implode($datef_invoice);
				dol_syslog($langs->trans('Vdateo') . $dateo, 6);
				// echo '$dateo = ' . $dateo . '<br>';
				// echo '<br><br><br>';
				//End

				//Start
				//Variable : datee
				//Description : retrieval of the end date of the invoice
				unset($datef_invoice);
				$data = $bdd->query('SELECT fk_fac_rec_source, datef FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['datef']) {
						$datef_invoice[0] = $data_cut['datef'];
					}
					if ($data_cut['fk_fac_rec_source']) {
						$fk_fac_rec_source_invoice[0] = $data_cut['fk_fac_rec_source'];
					}
				}
				$datef_invoice = explode('-', $datef_invoice[0]);
				$data = $bdd->query('SELECT rowid, frequency, unit_frequency FROM llx_facture_rec');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['rowid'] == $fk_fac_rec_source_invoice[0]) {
						$frequency_invoice_rec[0] = $data_cut['frequency'];
						$unit_frequency_invoice_rec[0] = $data_cut['unit_frequency'];
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
				dol_syslog($langs->trans('Vdatee') . $datee, 6);
				// echo '$datee = ' . $datee . '<br>';
				// echo '<br><br><br>';
				//End

				//Start
				//Variable : planned_workload
				//Description : time calculation of the planned workload
				$data = $bdd->query('SELECT rowid FROM llx_facture');
				while ($data_cut = $data->fetch()) {
					if ($data_cut['rowid']) {
						$rowid_invoice[0] = $data_cut['rowid'];
					}
				}
				$data = $bdd->query('SELECT fk_facture, fk_product, qty FROM llx_facturedet');
				$i = 0;
				$j = 0;
				while ($data_cut = $data->fetch()) {
					if ($data_cut['fk_facture'] == $rowid_invoice[0]) {
						$fk_product_invoicedet[$i] = $data_cut['fk_product'];
						$i += 1;
					}
					if ($data_cut['fk_facture'] == $rowid_invoice[0]) {
						$qty_invoicedet[$j] = intval($data_cut['qty']);
						$j += 1;
					}
				}
				$i = 0;
				$j = 0;
				$data = $bdd->query('SELECT rowid, duration FROM llx_product');
				//We fill the $duration table with the times of all the products of the invoice
				while ($data_cut = $data->fetch()) {
					while ($fk_product_invoicedet[$i]) {
						if ($data_cut['rowid'] == $fk_product_invoicedet[$i]) {
							$time[$j] = $data_cut['duration'];
							$j += 1;
						}
						$i += 1;
					}
					$i = 0;
				}
				$i = 0;
				$j = 0;
				//We multiply the time to have the time in seconds and we remove the unit to have the values ​​in int
				while (isset($time[$i])) {
					while (isset($time[$i][$j])) {
						if ($time[$i][$j] == 's') {
							$time[$i] = substr($time[$i], 0, -1);
						} elseif ($time[$i][$j] == 'i') {
							$time[$i] = substr($time[$i], 0, -1);
							$time[$i] *= 60;
						} elseif ($time[$i][$j] == 'h') {
							$time[$i] = substr($time[$i], 0, -1);
							$time[$i] *= 3600;
						} elseif ($time[$i][$j] == 'd') {
							$time[$i] = substr($time[$i], 0, -1);
							$time[$i] *= 86400;
						} elseif ($time[$i][$j] == 'w') {
							$time[$i] = substr($time[$i], 0, -1);
							$time[$i] *= 604800;
						} elseif ($time[$i][$j] == 'm') {
							$time[$i] = substr($time[$i], 0, -1);
							$time[$i] *= 2592000;
						} elseif ($time[$i][$j] == 'y') {
							$time[$i] = substr($time[$i], 0, -1);
							$time[$i] *= 31536000;
						}
						$j += 1;
					}
					$i += 1;
					$j = 0;
				}
				$i = 0;
				//We multiply the time of each product by its quantity
				while (isset($time[$i])) {
					if (is_int($time[$i])) {
						$time[$i] *= intval($qty_invoicedet[$i]);
					}
					$i += 1;
				}
				$i = 0;
				//We add up all the times
				while (isset($time[$i])) {
					$planned_workload += intval($time[$i]);
					$i += 1;
				}
				dol_syslog($langs->trans('Vplanned_workload') . $planned_workload, 6);
				// echo '$planned_workload = ' . $planned_workload . '<br>';
				// echo '<br><br><br>';
				// End

				//Rowid last task used for url to choose which task went to
				$rowid_last_task = intval($rowid_last_task[0]);
				$rowid_last_task += 1;

				//Preparation of the insertion of the new spot in the bdd
				$req = $bdd->prepare('INSERT INTO llx_projet_task(ref, fk_projet, label, description, dateo, datee, planned_workload) VALUES(:ref, :fk_projet, :label, :description, :dateo, :datee, :planned_workload)');

				//Filling of the llx_projet_task table with the variables to create the task
				if ($fk_projet && $planned_workload != 0) {
					$req->execute(array(
					'ref' => $ref,
					'fk_projet' => $fk_projet,
					'label' => $label,
					'description' => $description,
					'dateo' => $dateo,
					'datee' => $datee,
					'planned_workload' => $planned_workload
					));
					setEventMessages("<a href=\"" . DOL_URL_ROOT . "/projet/tasks/task.php?id=" . $rowid_last_task . "&withproject=". $fk_projet . "\">". $langs->trans('MessageInfo'). "</a>", null, 'mesgs');
					dol_syslog($langs->trans('TaskCreated') . $ref . $langs->trans('TaskCreated2'), 6);
				} else {
					setEventMessages("<a>" . $langs->trans('MessageInfoNoCreate') . "</a>", null, 'mesgs');
					dol_syslog($langs->trans('TaskCreatedFailed'), 6);
				}

				dol_syslog($langs->trans('EndTask'), 6);
				// echo '<br><br><br>';
				// echo '</pre>';

			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}
}
