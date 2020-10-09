<?php

require '../../../master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


function button_pressed() {

	//Connection to the db
	try {
		$bdd = new PDO("mysql:host=" . $_GET['db_host'] . ";dbname=" . $_GET['db_name'] . ";charset=UTF8", $_GET['db_user'], ''); //@todo connection db KO LM
	} catch (Exception $e) {
		die('Erreur : ' . $e->getMessage());
	}

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
    //@todo KO utiliser la fonction de dolibarr native
	$ref_last_task = str_split($ref_last_task[0], 5);
	$length = 0;
	while (isset($ref_last_task[$length])) {
		$length += 1;
	}
	$ref_last_task[$length - 1] += 1;
	$name = implode($ref_last_task);
	//End

	//Start
	//Variable : label
	//Description : creation of the label of the task
	//Wording retrieval
	$fk_projet_fac = $_GET['fk_project'];
	$data = $bdd->query('SELECT rowid, title FROM llx_projet');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['rowid'] == $_GET['fk_project']) {
			$title[0] = $data_cut['title'];
		}
	}
	$wording = $title[0];
	//Tag retrieval
    //@todo lié aux tags des projets affectés à la facture modèle
    //@todo paramtres de récupération des tags projets
    //@todo REGEX à construire dans les réglages dans notre cas : DATEDEBUTPERIODE-NOMPROJET-TAGS EX: 20200801-eoxia.fr-ref
	$note_private_invoice = $_GET['note_private'];
	$note_private_invoice = explode(' ', $note_private_invoice);
	if (in_array('Hebergement', $note_private_invoice)) {
		$tag = "SAV";
	}
	if (in_array('Referencement', $note_private_invoice)) {
		$tag = "REF";
	}
	//Date retrieval
	$data = $bdd->query('SELECT datef, ref FROM llx_facture');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['ref'] == $_GET['fk_name']) {
			$datef_invoice[0] = $data_cut['datef'];
		}
	}
	$datef_invoice = explode('-', $datef_invoice[0]);
	$datef = implode($datef_invoice);
	//Concatenation of the date, wording and tag to obtain the label
	$label = $datef . '-' . $wording . '-' . $tag;
	//End

	//Start
	//Variable : fk_projet
	//Description : take the fk_projet from the invoice
	$fk_projet = $_GET['fk_project'];
	//End

	//Start
	//Variable : dateo
	//Decription : retrieval of the start date of the invoice
	unset($datef_invoice);
	$data = $bdd->query('SELECT datef, ref FROM llx_facture');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['ref'] == $_GET['fk_name']) {
			$datef_invoice[0] = $data_cut['datef'];
		}
	}
	$dateo = implode($datef_invoice);
	//End

	//Start
	//Variable : datee
	//Description : retrieval of the end date of the invoice
	unset($datef_invoice);
	$data = $bdd->query('SELECT fk_fac_rec_source, datef, ref FROM llx_facture');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['ref'] == $_GET['fk_name']) {
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
	//End

	//Start
	//Variable : planned_workload
	//Description : time calculation of the planned workload
	//We recover all the products from the invoice
	$data = $bdd->query('SELECT rowid, ref FROM llx_facture');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['ref'] == $_GET['fk_name']) {
			$rowid_invoice[0] = $data_cut['rowid'];
		}
	}
	$i = 0;
	//We recover the quantity of all the products
	$data = $bdd->query('SELECT fk_facture, fk_product, qty FROM llx_facturedet');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['fk_facture'] == $rowid_invoice[0]) {
			$fk_product[$i] = $data_cut['fk_product'];
			$fk_quantity[$i] = $data_cut['qty'];
			$i += 1;
		}
	}
	$i = 0;
	$j = 0;
	//We recover the time of each product
	$data = $bdd->query('SELECT rowid, duration FROM llx_product');
	while ($data_cut = $data->fetch()) {
		while (isset($fk_product[$i])) {
			if ($data_cut['rowid'] == $fk_product[$i]) {
				$duration[$i] = $data_cut['duration'];
				$i += 1;
			}
			$i += 1;
		}
		$i = 0;
	}
	$i = 0;
	$j = 0;
	//We transform time into seconds
	while (isset($duration[$i])) {
		while (isset($duration[$i][$j])) {
			if ($duration[$i][$j] == 's') {
				$duration[$i] = substr($duration[$i], 0, -1);
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
				$duration[$i] *= 31536000;
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

	//Check if the facture exist
	$error = 0;
	$data = $bdd->query('SELECT fk_facture_name FROM llx_projet_task_extrafields');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['fk_facture_name'] == $_GET['fk_name']) {
			$error = 1;
		}
	}

	//Start
	//Filling of the llx_projet_task table with the variables to create the task
	$req = $bdd->prepare('INSERT INTO llx_projet_task(ref, fk_projet, label, dateo, datee, planned_workload) VALUES(:ref, :fk_projet, :label, :dateo, :datee, :planned_workload)');
	if ($fk_projet && $planned_workload != 0 && $error == 0) {
		$req->execute(array(
			'ref' => $name,
			'fk_projet' => $fk_projet,
			'label' => $label,
			'dateo' => $dateo,
			'datee' => $datee,
			'planned_workload' => $planned_workload
		));
	}
	$data = $bdd->query('SELECT rowid, ref, fk_projet FROM llx_projet_task');
	while ($data_cut = $data->fetch()) {
		if ($data_cut['rowid']) {
			$rowid_last_task[0] = $data_cut['rowid'];
		}
		if ($data_cut['ref']) {
			$ref_last_task[0] = $data_cut['ref'];
		}
	}
	//Filling of the llx_projet_task_extrafields table
	$req = $bdd->prepare('INSERT INTO llx_projet_task_extrafields(fk_object, fk_facture_name) VALUES(:fk_object, :fk_facture_name)');
	$req->execute(array(
		'fk_object' => $rowid_last_task[0],
		'fk_facture_name' => $_GET['id']
	));
	//Filling of the llx_facture_extrafields table
	$req = $bdd->prepare('INSERT INTO llx_facture_extrafields(fk_object, fk_task) VALUES(:fk_object, :fk_task)');
	$req->execute(array(
		'fk_object' => $_GET['fk_object'],
		'fk_task' => $rowid_last_task[0]
	));
	//End

	header('Location: '. $_GET['link'] . '');
}
button_pressed();