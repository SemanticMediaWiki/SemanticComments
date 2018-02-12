/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
* CE_Language.js
* 
* A class that reads language strings from the server by an ajax call.
* 
* @author Thomas Schweitzer, Benjamin Langguth
*
*/

function CELanguage() {

/**
 * This class provides language dependent strings for an identifier.
 * 
 */

	/**
	 * @public
	 * 
	 * Constructor.
	 */
	this.initialize = function() {
	};

	/*
	 * @public
	 * 
	 * Returns a language dependent message for an ID, or the ID, if there is 
	 * no message for it.
	 * 
	 * @param string id
	 * 			ID of the message to be retrieved.
	 * @return string
	 * 			The language dependent message for the given ID.
	 */
	this.getMessage = function( id ) {
		var mw = window.mediaWiki;
		var msg = mw.msg( id );
		if (!msg) {
			msg = id;
		}

		// Replace variables
		msg = msg.replace( /\$n/g, mw.config.get( 'wgCanonicalNamespace' ) );
		msg = msg.replace( /\$p/g, mw.config.get( 'wgPageName' ) );
		msg = msg.replace( /\$t/g, mw.config.get( 'wgTitle' ) );
		msg = msg.replace( /\$u/g, mw.config.get( 'wgUserName' ) );
		msg = msg.replace( /\$s/g, mw.config.get( 'wgServer' ) );
		return msg;
	};
	
}

// Singleton of this class
var ceLanguage;

//Initialize if page is loaded
jQuery(document).ready(
	function(){
		ceLanguage = new CELanguage();
	}
);
