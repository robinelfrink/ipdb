/*
Copyright 2009 Introweb Nederland bv
Author: Robin Elfrink <robin@15augustus.nl>

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
	ajaxify();
}


/* Get element by ID */
function getElement(id) {
	if (document.layers)
		return document.layers[id];
	else if (document.all)
		return document.all[id];
	else if (document.getElementById)
		return document.getElementById(id);
	else
		return false;
}


/* escape() does not escape '+' */
function escapeplus(str) {
	return escape(str).replace(/\+/, '%2B');
}


/* AJAXify the anchors and forms */
function ajaxify() {
	var div, litems, anchors, i;
	if (div = getElement('tree')) {
		anchors = div.getElementsByTagName('a');
		for (i=0; i<anchors.length; i++)
			anchors[i].onclick = clicktree;
		litems = div.getElementsByTagName('li');
		for (i=0; i<litems.length; i++)
			if (litems[i].id && litems[i].id.match(/^a_/))
				litems[i].onclick = clicktree;
	}
	var names = ['menu', 'content'];
	for (var j in names) {
		if (div = getElement(names[j])) {
			anchors = div.getElementsByTagName('a');
			for (i=0; i<anchors.length; i++) {
				if (anchors[i].hasAttribute('remote') &&
					(anchors[i].getAttribute('remote')=='remote')) {
					anchors[i].onclick = clicka;
				}
			}
			forms = div.getElementsByTagName('form');
			for (i=0; i<forms.length; i++) {
				if (forms[i].getAttribute('remote')!=null) {
					forms[i].onsubmit = function(event) {
						return submitform(event);
					}
				}
			}
		}
	}
}


/* Click on an anchor */
function clicka(event) {
	var target;
	if (!event) var event = window.event;
	if (event.target) target = event.target;
	else if (event.srcElement) target = event.srcElement;
	if (target.nodeType == 3)
		target = target.parentNode;
	var href = target.href;
	if (href.match(/\?/))
		href = href.replace(/\?/, '?remote=remote&');
	else
		href = href+'?remote=remote';
	ajaxrequest(href.replace(/.*\?/, ''));
	return false;
}


/* Submit a form */
function submitform(event) {
	var vars = 'remote=remote';
	var form;
	var submit = null;

	if (event && event.name && event.name.match(/form/))
		form = event;
	else if (event && !event.target && event.type && event.type == 'submit') {
		form = event.form;
		submit = event.name;
	} else {
		if (!event) 
			var event = window.event;
		if (event.target) 
			form = event.target;
		else 
			if (event.srcElement) 
				form = event.srcElement;
	}

	if (!form.confirm &&
		form.getAttribute('confirm') &&
		!eval(form.getAttribute('confirm')+'(form)'))
		return false;

	if (form.elements) {
		for (var i = 0; i < form.elements.length; i++) {
			if (form.elements[i].name) {
				if (form.elements[i].type == 'checkbox') 
					vars = vars + '&' + escapeplus(form.elements[i].name) + '=' + (form.elements[i].checked ? 'on' : 'off');
				else if (form.elements[i].type == 'radio') 
					vars = vars + (form.elements[i].checked ? '&' + escapeplus(form.elements[i].name) + '=' + escapeplus(form.elements[i].value) : '');
				else if (form.elements[i].type == 'submit') {
					if (form.elements[i].name == submit) 
						vars = vars + '&submit=' + escapeplus(form.elements[i].name);
				} else if (form.elements[i].type == 'select-one') {
					vars = vars + '&' + escapeplus(form.elements[i].name) + '=' + escapeplus(form.elements[i].options[form.elements[i].selectedIndex].value);
				} else
					vars = vars + '&' + escapeplus(form.elements[i].name) + '=' + escapeplus(form.elements[i].value);
				if (form.elements[i].type == 'password') 
					form.elements[i].value = '';
			}
		}
		ajaxrequest(location.href.replace(/\?.*/, '')+'?'+vars);
	}
	return false;
}


/* Stop event from propagating */
function stopEvent(event) {
	event.cancelBubble = true;
	if (event.stopPropagation)
		event.stopPropagation();
}


/* Handle click on the tree */
function clicktree(event) {
	var target;
	if (!event) var event = window.event;
	if (event.target)
		target = event.target;
	else if (event.srcElement)
		target = event.srcElement;
	if (target.nodeType == 3)
		target = target.parentNode;
	if (target.tagName=='A') {
		if (target.parentNode.parentNode.className=='collapsed')
			expand(target.parentNode.parentNode.id.replace(/^a_/, ''));
		ajaxrequest(target.href.replace(/.*\?/, ''));
		stopEvent(event);
		return false;
	} else if (target.tagName=='DIV') {
		if (target.parentNode.className=='collapsed')
			expand(target.parentNode.id.replace(/^a_/, ''));
		else if (target.parentNode.className=='expanded')
			collapse(target.parentNode.id.replace(/^a_/, ''));
		stopEvent(event);
		return false;
	}
	return true;
}


/* Expand a tree node */
function expand(address) {
	ajaxrequest('action=getsubtree&leaf='+address);
	return false;
}
function expandtree(address, content) {
	var li;
	collapse(address);
	if (li = getElement('a_'+address)) {
		li.innerHTML = li.innerHTML+unescape(content);
		li.className = 'expanded';
	}
}


/* Collapse a tree node */
function collapse(address) {
	var li, uls, i;
	if (li = getElement('a_'+address)) {
		uls = li.getElementsByTagName('ul');
		for (i=0; i<uls.length; i++)
			li.removeChild(uls[i]);
		li.className = 'collapsed';
	}
}


/* Send an AJAX request */
function ajaxrequest(args) {
	var request;
	document.URL.replace(/\?.*$/, '');
	try {
		request = new XMLHttpRequest();
	} catch (e) {
		try {
			request = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				request = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e) {
				request = null;
			}
		}
	}
	if (request) {
		request.onreadystatechange = function() {
			if (request.readyState==4) {
				var xml;
				if ((xml = request.responseXML) &&
					xml.getElementsByTagName('content') &&
					(xml.getElementsByTagName('content').length > 0)) {
					/* If we get an XML response, we're doing Ajax (obviously). But in the 
					 * case we had a JS error, the browsers URL may contain variables, which
					 * prevent the refresh button from function normally. So we will do a
					 * reload of the page automatically.
					 */
					if (document.URL.match(/\?.+/)) {
						document.location = document.URL.replace(/\?.*/, '');
						return;
					}
					var content = xml.getElementsByTagName('content')[0];
					var nodes = new Array();
					for (var i = 0; i < content.childNodes.length; i++) 
						if (!content.childNodes[i].nodeName.match(/^#/)) {
							if (typeof(nodes[content.childNodes[i].nodeName])=='object')
								nodes[content.childNodes[i].nodeName] = new String(nodes[content.childNodes[i].nodeName].concat(object_content(content.childNodes[i])));
							else 
								nodes[content.childNodes[i].nodeName] = new String(object_content(content.childNodes[i]));
						}
						for (node in nodes) {
							if (document.getElementById(node)) 
								document.getElementById(node).innerHTML = unescape(nodes[node]);
							else if (node == 'title') 
								document.title = unescape(nodes[node]);
						}
						if (typeof(nodes['commands']) == 'object') {
							eval(unescape(nodes['commands']));
						}
						initialize();
				} else {
					alert('Error: '+request.responseText);
				}
			}
		}
		request.open('GET', document.URL.replace(/\?.*$/, '')+'?remote=remote&'+args);
		request.send(null);
	}
}


/* Cross browser XML object content fetches */
function object_content(object) {
	if (object.firstChild && object.firstChild.data)
		/* Safari */
		return object.firstChild.data;
	else if (object.textContent)
		/* Mozilla */
		return object.textContent;
	else if (object.text)
		/* Internet Explorer */
		return object.text;
	else
		return false;
}



window.onload = initialize;
