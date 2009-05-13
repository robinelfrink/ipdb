<?php

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


function acton($action) {

	switch ($action) {
	  case 'login':
		  global $session;
		  if ($session->authenticated)
			  request('page', 'main', true);
		  break;
	  case 'logout':
		  if (request('remote')!='remote')
			  header('Location: '.me());
		  break;
	  case 'getsubtree':
		  global $session;
		  if ((request('remote')=='remote') && $session->authenticated) {
			  header('Content-type: text/xml; charset=utf-8');
			  header('Cache-Control: no-cache, must-revalidate');
			  header('Expires: Fri, 15 Aug 2003 15:00:00 GMT'); /* Remember my wedding day */
			  $commands = str_split(escape('expandtree(\''.request('leaf').'\', \''.escape(Tree::get(request('leaf'))).'\');'), 1024);
			  echo '<?xml version="1.0" encoding="UTF-8"?>
<content>
	<commands>'.implode('</commands><commands>', $commands).'</commands>
</content>';
			  exit;
		  }
		  break;
	}

}


?>
