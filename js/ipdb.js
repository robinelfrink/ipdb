/*
Copyright 2011 Previder bv (http://www.previder.nl)
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

*/


var timer = undefined;


/* Initialize */
$(function () {
	ajaxify();
	settimeout();
});


/* Set timeout */
function settimeout() {
	if ((typeof timeout != 'undefined') && (timeout!=0)) {
		if (timer!=undefined) {
			clearTimeout(timer);
		}
		timer = setTimeout('timeoutdummy();', 1050*timeout);
	}
}


/* Do dummy call after timeout */
function timeoutdummy() {
	ajaxrequest(location.href.replace(/.*\?/, 'dummy=dummy'));
}


/* escape() does not escape '+' */
function escapeplus(str) {
	return escape(str).replace(/\+/, '%2B');
}


/* AJAXify the anchors and forms */
function ajaxify() {
	$('.tree a').off('click').click(clicktree);
	$('.tree li[id^="a_"]').off('click').click(clicktree);
	$('.menu a[remote="remote"]').off('click').click(clicka);
	$('.menu form[remote="remote"]').off('submit').submit(submitform);
	$('.menu form[remote="remote"] input[name="cancel"]').off('click').click(function() { ajaxrequest(location.href.replace(/.*\?/, '')); return false; });
	$('.content a[remote="remote"]').off('click').click(clicka);
	$('.content form[remote="remote"]').off('submit').submit(submitform);
	$('.content form[remote="remote"] input[name="cancel"]').off('click').click(function() { ajaxrequest(location.href.replace(/.*\?/, '')); return false; });
}


/* Click on an anchor */
function clicka(event) {
	var href = $(event.target).attr('href');
	if (href.match(/\?/))
		href = href.replace(/\?/, '?remote=remote&');
	else
		href = href+'?remote=remote';
	ajaxrequest(href.replace(/.*\?/, ''));
	return false;
}


/* Submit a form */
function submitform(event) {
	var vars = { };
	$(event.target).find('input,select,textarea').each(function() {
		if ($(this).is('input[type=checkbox]'))
			vars[$(this).attr('name')] = this.checked ? 'on' : 'off';
		else if ($(this).is('input[type=radio]'))
			vars[$(this).attr('name')] = this.checked ? this.value : '';
		else if ($(this).is('input[type=submit]') && ($(this).attr('name')!='cancel'))
			vars['submit'] = $(this).attr('name');
		else
			vars[$(this).attr('name')] = this.value;
	});
	ajaxrequest($.param(vars));
	return false;
}


/* Handle click on the tree */
function clicktree(event) {
	if ($(event.target).is('a')) {
		document.location.href = target.href.replace(/.*\?/, '?');
	} else if ($(event.target).is('div')) {
		if ($(this).hasClass('expanded'))
			collapse($(this).attr('id').replace(/^a_/, ''));
		else if ($(this).hasClass('collapsed'))
			expand($(this).attr('id').replace(/^a_/, ''));
	}
	event.stopPropagation();
	return false;
}


/* Expand a tree node */
function expand(address) {
	ajaxrequest('action=getsubtree&leaf='+address);
	return false;
}
function expandtree(address, content) {
	$('.tree li[id="a_'+address+'"]').append(unescape(content)).addClass('expanded').removeClass('collapsed');
}


/* Collapse a tree node */
function collapse(address) {
	$('.tree li[id="a_'+address+'"]').addClass('collapsed').removeClass('expanded');
	$('.tree li[id="a_'+address+'"] ul').remove();
}


/* Send an AJAX request */
function ajaxrequest(args) {
	$.ajax(document.URL.replace(/\?.*$/, '')+'?remote=remote&'+args).done(function(json) {
		if (json.content)
			$('.content').html(json.content);
		if (json.title)
			document.title = json.title;
		if (json.commands)
			eval(json.commands);
		if (json.debug)
			$('.debug').html(json.debug);
		ajaxify();
	});
}

