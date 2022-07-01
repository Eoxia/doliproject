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

