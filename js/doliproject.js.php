/* Copyright (C) 2021 EOXIA <dev@eoxia.com>
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
 *
 * Library javascript to enable Browser notifications
 */

/**
 * \file    js/digiriskdolibarr.js.php
 * \ingroup digiriskdolibarr
 * \brief   JavaScript file for module DigiriskDolibarr.
 */

/* Javascript library of module DigiriskDolibarr */

'use strict';
/**
 * @namespace EO_Framework_Init
 *
 * @author Eoxia <dev@eoxia.com>
 * @copyright 2015-2021 Eoxia
 */

if ( ! window.eoxiaJS ) {
	/**
	 * [eoxiaJS description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @type {Object}
	 */
	window.eoxiaJS = {};

	/**
	 * [scriptsLoaded description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @type {Boolean}
	 */
	window.eoxiaJS.scriptsLoaded = false;
}

if ( ! window.eoxiaJS.scriptsLoaded ) {
	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.init = function() {
		window.eoxiaJS.load_list_script();
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.load_list_script = function() {
		if ( ! window.eoxiaJS.scriptsLoaded) {
			var key = undefined, slug = undefined;
			for ( key in window.eoxiaJS ) {

				if ( window.eoxiaJS[key].init ) {
					window.eoxiaJS[key].init();
				}

				for ( slug in window.eoxiaJS[key] ) {

					if ( window.eoxiaJS[key] && window.eoxiaJS[key][slug] && window.eoxiaJS[key][slug].init ) {
						window.eoxiaJS[key][slug].init();
					}

				}
			}

			window.eoxiaJS.scriptsLoaded = true;
		}
	};

	/**
	 * [description]
	 *
	 * @memberof EO_Framework_Init
	 *
	 * @returns {void} [description]
	 */
	window.eoxiaJS.refresh = function() {
		var key = undefined;
		var slug = undefined;
		for ( key in window.eoxiaJS ) {
			if ( window.eoxiaJS[key].refresh ) {
				window.eoxiaJS[key].refresh();
			}

			for ( slug in window.eoxiaJS[key] ) {

				if ( window.eoxiaJS[key] && window.eoxiaJS[key][slug] && window.eoxiaJS[key][slug].refresh ) {
					window.eoxiaJS[key][slug].refresh();
				}
			}
		}
	};

	$( document ).ready( window.eoxiaJS.init );
}


/**
 * Initialise l'objet "task" ainsi que la méthode "init" obligatoire pour la bibliothèque EoxiaJS.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
window.eoxiaJS.task = {};

/**
 * La méthode appelée automatiquement par la bibliothèque EoxiaJS.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.eoxiaJS.task.init = function() {
	window.eoxiaJS.task.event();
};

/**
 * La méthode contenant tous les événements pour le task.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return {void}
 */
window.eoxiaJS.task.event = function() {
	$( document ).on( 'click', '.auto-fill-timespent', window.eoxiaJS.task.addTimeSpent );
	$( document ).on( 'click', '.auto-fill-timespent-project', window.eoxiaJS.task.divideTimeSpent );
	$( document ).on( 'click', '.show-only-favorite-tasks', window.eoxiaJS.task.showOnlyFavoriteTasks );
};

/**
 * Remplit automatiquement le temps à pointer disponible sur une tâche
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {MouseEvent} event [description]
 * @return {void}
 */
window.eoxiaJS.task.addTimeSpent = function( event ) {
	let nonConsumedMinutes = $('.non-consumed-time-minute').val()
	let nonConsumedHours = $('.non-consumed-time-hour').val()
	$('.inputhour').val('')
	$('.inputminute').val('')
	$(this).closest('.duration').find('.inputhour').val(nonConsumedHours)
	$(this).closest('.duration').find('.inputminute').val(nonConsumedMinutes)
};

/**
 * Répartit automatiquement le temps à pointer disponible entre les tâches du projet
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {MouseEvent} event [description]
 * @return {void}
 */
window.eoxiaJS.task.divideTimeSpent = function( event ) {
	let projectId = $(this).closest('.project-line').attr('id')

	let taskMinute = 0
	let taskHour = 0

	let nonConsumedMinutes = $('.non-consumed-time-minute').val()
	let nonConsumedHours = $('.non-consumed-time-hour').val()
	let totalTimeInMinutes = +nonConsumedMinutes + +nonConsumedHours*60

	let taskLinkedCounter = $('.'+projectId).length
	let minutesToSpend = parseInt(totalTimeInMinutes/taskLinkedCounter)

	$('.inputhour').val('')
	$('.inputminute').val('')

	$('.'+projectId).each(function() {
		taskHour = parseInt(minutesToSpend/60)
		taskMinute = minutesToSpend%60

		$(this).find('.inputhour').val(taskHour)
		$(this).find('.inputminute').val(taskMinute)
	})
};

/**
 * Active/désactive la configuration pour n'afficher que les tâches favorites
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param  {MouseEvent} event [description]
 * @return {void}
 */
window.eoxiaJS.task.showOnlyFavoriteTasks = function( event ) {
	let token = $('.id-container').find('input[name="token"]').val();
	let querySeparator = '?';

	document.URL.match(/\?/) ? querySeparator = '&' : 1

	$.ajax({
		url: document.URL + querySeparator + "action=showOnlyFavoriteTasks&token=" + token,
		type: "POST",
		processData: false,
		contentType: false,
		success: function ( resp ) {
			window.location.reload()
		},
		error: function ( ) {
		}
	});
};

