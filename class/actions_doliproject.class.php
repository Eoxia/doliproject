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

		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('invoicecard'))) {
			// Action that will be done after pressing the button
			if ($action == 'createtask-doliproject') {

				// Start
				// Variable : ref
				// Description : create the ref of the task
				$mod = new mod_task_simple;
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
				$i = 0;
				$query = "SELECT fk_facture, date_start, date_end FROM ".MAIN_DB_PREFIX."facturedet";
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
						$date_start[$i] = $row['date_start'];
					}
				}
				$dateo = $date_start[0];
				//End

				//Start
				//Variable : datee
				//Description : retrieval of the end date of the invoice
				$i = 0;
				$query = "SELECT fk_facture, date_start, date_end FROM ".MAIN_DB_PREFIX."facturedet";
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
						$date_end[$i] = $row['date_end'];
						$i += 1;
					}
				}
				$datee = $date_end[0];
				//End

				//Start
				//Variable : planned_workload
				//Description : time calculation of the planned workload
				//We recover all the products from the invoice
				$i = 0;
				//We recover the quantity of all the products
				$query = "SELECT fk_facture, fk_product, qty FROM ".MAIN_DB_PREFIX."facturedet";
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
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

				//Check if the invoice is already linked to the task
				$error_button = 0;
				$query = "SELECT fk_facture_name FROM ".MAIN_DB_PREFIX."projet_task_extrafields";
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture_name'] == $object->id) {
						$error_button = 1;
					}
				}
				//Start
				//Filling of the llx_projet_task table with the variables to create the task
				if ($error_button == 0) {
					if (isset($fk_projet) && $planned_workload != 0 && isset($dateo) && isset($datee)) {
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
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'projet_task_extrafields(fk_object, fk_facture_name) VALUES('.$rowid_last_task[0].', '.$object->lines[0]->fk_facture.')';
						$this->db->query($req);
						//Filling of the llx_facture_extrafields table
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'facture_extrafields(fk_object, fk_task) VALUES('.$object->lines[0]->fk_facture.', '.$rowid_last_task[0].')';
						$this->db->query($req);
						setEventMessages($langs->trans("MessageInfo").' : '.'<a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$rowid_last_task[0].'">'.$ref.'</a>', null, 'mesgs');
					}
					//Error messages
					else {
						if (!isset($fk_projet)) {
							setEventMessages($langs->trans("MessageInfoNoCreateProject"), null, 'errors');
						}
						if ($planned_workload == 0) {
							setEventMessages($langs->trans("MessageInfoNoCreateTime"), null, 'errors');
						}
						if (!isset($datee) || !isset($dateo)) {
							setEventMessages($langs->trans("MessageInfoNoCreatedate"), null, 'errors');
						}
					}
				}
				//End
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

		if (in_array('invoicecard', explode(':', $parameters['context'])))
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
			//Check for grey button
			//Start

			//Check date
			$i = 0;
			$query = "SELECT fk_facture, date_start, date_end FROM ".MAIN_DB_PREFIX."facturedet";
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
					$date_end[$i] = $row['date_end'];
					$date_start[$i] = $row['date_start'];
					$i += 1;
				}
			}
			$datee = $date_end[0];
			$dateo = $date_start[0];

			//Check service time
			$i = 0;
			$query = "SELECT fk_facture, fk_product, qty FROM ".MAIN_DB_PREFIX."facturedet";
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
					$fk_product[$i] = $row['fk_product'];
					$fk_quantity[$i] = $row['qty'];
					$i += 1;
				}
			}
			$i = 0;
			$j = 0;
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
			while (isset($duration[$i])) {
				if (is_int($duration[$i])) {
					$duration[$i] *= intval($fk_quantity[$i]);
				}
				$i += 1;
			}
			$i = 0;
			$planned_workload = 0;
			while (isset($duration[$i])) {
				$planned_workload += intval($duration[$i]);
				$i += 1;
			}
			//End

			//Button
			if ($error_button == 0) {
				if (isset($object->fk_project) && isset($dateo) && isset($datee) && $planned_workload != 0) {
					print '<div class="inline-block divButAction"><a class="butAction" href="'. $actual_link .'">Créer tâche</a></div>';
				} elseif (!isset($object->fk_project)) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("ErrorNoProject").'">Créer tâche</a></div>';
				} elseif (!isset($dateo)) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("ErrorDateStart").'">Créer tâche</a></div>';
				} elseif (!isset($datee)) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("ErrorDateEnd").'">Créer tâche</a></div>';
				} elseif ($planned_workload == 0) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans("ErrorServiceTime").'">Créer tâche</a></div>';
				}
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	public function printCommonFooter($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$langs->load('projects');
		if (in_array('ticketcard', explode(':', $parameters['context'])))
		{
			if (GETPOST('action') == 'presend_addmessage') {
				$ticket = new Ticket($this->db);
				$result = $ticket->fetch('',GETPOST('ref','alpha'),GETPOST('track_id','alpha'));
				dol_syslog(var_export($ticket, true), LOG_DEBUG);
				if ($result > 0 && ((int)$ticket->id) > 0) {
					if ( is_array($ticket->array_options) && array_key_exists('options_fk_task',$ticket->array_options) && $ticket->array_options['options_fk_task']>0) {
					?>
					<script>
						let InputTime = document.createElement("input");
						InputTime.id = "timespent";
						InputTime.name = "timespent";
						InputTime.type = "number";
						InputTime.value = <?php echo (!empty($conf->global->DOLIPROJECT_DEFAUT_TICKET_TIME)?$conf->global->DOLIPROJECT_DEFAUT_TICKET_TIME:0); ?>;
						let $tr = $('<tr>');
						$tr.append($('<td>').append('<?php echo $langs->trans('NewTimeSpent');?>'));
						$tr.append($('<td>').append(InputTime));

						let currElement = $("form[name='ticket'] > table tbody");
						currElement.append($tr);
					</script>
					<?php
					} else {
						setEventMessage($langs->trans('MessageNoTaskLink'),'warnings');
					}
				} else {
					setEventMessages($ticket->error,$ticket->errors,'errors');
				}
				dol_htmloutput_events();
			}
		}
		if (in_array($parameters['currentcontext'], array('projecttaskcard'))) {
			require_once __DIR__ . '/../lib/doliproject_functions.lib.php';

			if (GETPOST('action') == 'toggleTaskFavorite') {
				toggleTaskFavorite(GETPOST('id'), $user->id);
			}

			if (isTaskFavorite(GETPOST('id'), $user->id)) {
				$favoriteStar = '<span class="fas fa-star toggleTaskFavorite" onclick="toggleTaskFavorite()"></span>';
			} else {
				$favoriteStar = '<span class="far fa-star toggleTaskFavorite" onclick="toggleTaskFavorite()"></span>';
			}
			?>
			<script>
				function toggleTaskFavorite () {
					let token = $('.fichecenter').find('input[name="token"]').val();
					$.ajax({
						url: document.URL + '&action=toggleTaskFavorite&token='+token,
						type: "POST",
						processData: false,
						contentType: false,
						success: function ( resp ) {
							if ($('.toggleTaskFavorite').hasClass('fas')) {
								$('.toggleTaskFavorite').removeClass('fas')
								$('.toggleTaskFavorite').addClass('far')
							} else if ($('.toggleTaskFavorite').hasClass('far')) {
								$('.toggleTaskFavorite').removeClass('far')
								$('.toggleTaskFavorite').addClass('fas')
							}
						},
						error: function ( resp ) {

						}
					});
				}
				jQuery('.fas.fa-tasks').closest('.tabBar').find('.marginbottomonly.refid').html(<?php echo json_encode($favoriteStar) ?> + jQuery('.fas.fa-tasks').closest('.tabBar').find('.marginbottomonly.refid').html());
			</script>
			<?php
		}
		if (in_array($parameters['currentcontext'], array('projecttaskscard'))) {
			global $db;
			require_once __DIR__ . '/../lib/doliproject_functions.lib.php';

			$task = new Task($db);
			$tasksarray = $task->getTasksArray(0, 0, GETPOST('id'));
			if (is_array($tasksarray) && !empty($tasksarray)) {
				foreach ($tasksarray as $linked_task) {

					if (isTaskFavorite($linked_task->id, $user->id)) {
						$favoriteStar = '<span class="fas fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavorite(this.id)"></span>';
					} else {
						$favoriteStar = '<span class="far fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavorite(this.id)"></span>';
					}
					?>
					<script>
						jQuery('#row-'+<?php echo json_encode($linked_task->id) ?>).find('.nowraponall').html(jQuery('#row-'+<?php echo json_encode($linked_task->id) ?>).find('.nowraponall').html()  + ' ' + <?php echo json_encode($favoriteStar) ?>  )
					</script>
					<?php
				}
			}
		}
		if (in_array($parameters['currentcontext'], array('tasklist'))) {
			global $db;
			require_once __DIR__ . '/../lib/doliproject_functions.lib.php';

			$task = new Task($db);
			$tasksarray = $task->getTasksArray(0, 0, GETPOST('id'));

			if (is_array($tasksarray) && !empty($tasksarray)) {
				foreach ($tasksarray as $linked_task) {

					if (isTaskFavorite($linked_task->id, $user->id)) {
						$favoriteStar = '<span class="fas fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavorite(this.id)"></span>';
					} else {
						$favoriteStar = '<span class="far fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavorite(this.id)"></span>';
					}
					?>
					<script>
						console.log('oui')
						if (typeof taskId == null) {
							let taskId = <?php echo json_encode($linked_task->id); ?>
						} else {
							taskId = <?php echo json_encode($linked_task->id); ?>
						}
						jQuery("tr[data-rowid="+taskId+"] .nowraponall:not(.tdoverflowmax150)").html(jQuery("tr[data-rowid="+taskId+"] .nowraponall:not(.tdoverflowmax150)").html()  + ' ' + <?php echo json_encode($favoriteStar) ?>  )

					</script>
					<?php
				}
			}
		}
		if (GETPOST('action') == 'toggleTaskFavorite') {
			toggleTaskFavorite(GETPOST('taskId'), $user->id);
		}
		?>
		<script>
			function toggleTaskFavorite (taskId) {
				let token = $('#searchFormList').find('input[name="token"]').val();
				$.ajax({
					url: document.URL + '&action=toggleTaskFavorite&taskId='+ taskId +'&token='+token,
					type: "POST",
					processData: false,
					contentType: false,
					success: function ( resp ) {
						let taskContainer = $('#'+taskId)

						if (taskContainer.hasClass('fas')) {
							taskContainer.removeClass('fas')
							taskContainer.addClass('far')
						} else if (taskContainer.hasClass('far')) {
							taskContainer.removeClass('far')
							taskContainer.addClass('fas')
						}
					},
					error: function ( resp ) {

					}
				});
			}
		</script>
		<?php
	}
	/* Add here any other hooked methods... */
}
