/*  Copyright 2008  Robin Elfrink  (email : robin@15augustus.nl)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

$Id$
*/


/* Initialize */
function initialize() {
	var lis = document.getElementsByTagName('LI');
	if (lis && (lis.length>0))
		for (var li in lis)
			if (lis[li].addEventListener)
				lis[li].addEventListener('click', click, false);
			else if (lis[li].attachEvent)
				lis[li].attachEvent('onclick', click);
}


/* Source: http://www.netlobo.com/javascript_get_element_id.html */
function elementById(id) {
	if (document.getElementById)
		return document.getElementById(id);
	else if (document.all)
		return document.all[id];
	else if (document.layers)
		return document.layers[id];
	return false; 
}


function stopPropagation(e) {
	e.cancelBubble = true;
	if (e.stopPropagation) e.stopPropagation();
}


function click(e) {
	if (!e) var e = window.event;
	if (e.target && e.target.id &&
		e.target.id.match(/^a_[0-9a-f]{32}$/)) {
		stopPropagation(e);
		if (e.target.parentNode &&
			e.target.parentNode.tagName &&
			(e.target.parentNode.tagName=='LI') &&
			(e.target.parentNode.className=='expanded') &&
			(e.target.parentNode.getElementsByTagName('UL').length>0)) {
			e.target.parentNode.removeChild(e.target.parentNode.getElementsByTagName('UL')[0]);
			e.target.parentNode.className = 'collapsed';
		}
		return false;
	}
}


window.onload = function() {
	initialize();
}
