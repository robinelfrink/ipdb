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


function collapse(address) {
	var li = elementById('a_'+address);
	if (li) {
		var children = li.getElementsByTagName('UL');
		if (children.length>0)
			li.removeChild(children[0]);
	}
	return false;
}


function expand(address) {
	return false;
}
