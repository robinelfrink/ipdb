<?php

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


function acton($action) {

	global $config, $database, $error, $session;

	switch ($action) {
	  case 'login':
		  if ($session->authenticated) {
			  request('action', false, true);
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'\';')));
		  	  else {
				  request('page', 'main', true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'logout':
		  request('action', false, true);
		  if (request('remote')!='remote') {
			  header('Location: '.me());
			  exit;
		  }
		  break;
	  case 'getsubtree':
		  if ($session->authenticated)
			  send(array('commands'=>array('expandtree(\''.request('leaf').'\', \''.escape(Tree::getHtml(request('leaf'))).'\');')));
		  break;
	  case 'addnode':
		  if ($session->authenticated) {
			  if (!($node = $database->addNode(request('address').'/'.request('bits'), request('description')))) {
				  $error = $database->error;
				  break;
			  }
			  if (count($config->extrafields)>0)
				  foreach ($config->extrafields as $field=>$details)
					  if (!$database->setField($field, $node, request('field-'.$field), (request('field-'.$field.'-recursive')=='on' ? true : false))) {
						  $error = $database->error;
						  break;
					  }
			  if (count($config->extratables)>0)
				  foreach ($config->extratables as $table=>$details)
					  if ($details['linkaddress'] &&
						  !$database->setItem($table, $node, request('table-'.$table), (request('table-'.$table.'-recursive')=='on' ? true : false))) {
						  $error = $database->error;
						  break;
					  }
			  request('node', $node, true);
			  request('page', 'main', true);
		  }
		  break;
	  case 'changenode':
		  if ($session->authenticated) {
			  $node = $database->getNode(request('node'));
			  if ($database->error) {
				  $error = $database->error;
			  } else {
				  $database->changeNode(request('node'), request('address').'/'.request('bits'), request('description'));
				  if ($database->error) {
					  $error = $database->error;
				  } else {
					  if (count($config->extrafields)>0)
						  foreach ($config->extrafields as $field=>$details)
							  if (!$database->setField($field, request('node'), request('field-'.$field), (request('field-'.$field.'-recursive')=='on' ? true : false))) {
								  $error = $database->error;
								  break;
							  }
					  if (count($config->extratables)>0)
						  foreach ($config->extratables as $table=>$details)
							  if ($details['linkaddress'] &&
								  !$database->setItem($table, request('node'), request('table-'.$table), (request('table-'.$table.'-recursive')=='on' ? true : false))) {
								  $error = $database->error;
								  break;
							  }
					  if (!$error) {
						  request('node', request('address').'/'.request('bits'), true);
						  request('page', 'main', true);
					  }
				  }
			  }
		  }
		  break;
	  case 'deletenode':
		  if ($session->authenticated) {
			  $database->deleteNode(request('node'), request('childaction'));
			  if ($database->error) {
				  $error = $database->error;
			  } else {
				  $parent = $database->getParent(request('node'));
				  request('node', $parent['node'], true);
				  request('page', 'main', true);
			  }
		  }
		  break;
	  case 'search':
		  if ($session->authenticated) {
			  global $searchresult;
			  if (trim(request('search'))=='') {
				  request('node', 0, true);
				  request('page', 'main', true);
			  } else {
				  $searchresult = $database->searchDb(request('search'));
				  if (count($searchresult)>0) {
					  request('node', null, true);
					  request('page', 'main', true);
				  } else {
					  $error = 'Search result is empty.';
					  if (($node = $database->getNode(request('node'))) ||
						  ($node = $database->getParent(request('node')))) {
						  request('node', $node['node'], true);
						  request('page', 'main', true);
					  }
				  }
			  }
		  }
		  break;
	  case 'createdb':
		  $database->initializeDb();
		  if ($database->error)
			  $error = $database->error;
		  break;
	  case 'upgradedb':
		  $database->upgradeDb();
		  if ($database->error)
			  $error = $database->error;
		  break;
	  case 'addextra':
		  if ($session->authenticated) {
			  $columndata = array();
			  if (isset($config->extratables[request('table')]['columns']) &&
				  is_array($config->extratables[request('table')]['columns']) &&
				  count($config->extratables[request('table')]['columns']))
				  foreach ($config->extratables[request('table')]['columns'] as $column=>$type)
					  if (request($column))
						  $columndata[$column] = request($column);
			  if ($database->addExtra(request('table'), request('item'), request('description'), request('comments'), $columndata))
				  request('page', 'extratable', true);
		  }
		  break;
	  case 'changeextra':
		  if ($session->authenticated) {
			  $columndata = array();
			  if (isset($config->extratables[request('table')]['columns']) &&
				  is_array($config->extratables[request('table')]['columns']) &&
				  count($config->extratables[request('table')]['columns']))
				  foreach ($config->extratables[request('table')]['columns'] as $column=>$type)
					  if (request($column))
						  $columndata[$column] = request($column);
			  $database->changeExtra(request('table'), request('olditem'), request('item'), request('description'), request('comments'), $columndata);
			  request('page', 'extratable', true);
		  }
		  break;
	  case 'deleteextra':
		  if ($session->authenticated &&
			  $database->deleteExtra(request('table'), request('item')))
			  request('page', 'extratable', true);
		  break;
	  case 'changeaccount':
		  if ($session->authenticated) {
			  if ((request('name')!=$session->name) &&
				  !$database->changeName(request('name'))) {
				  $error = $database->error;
				  break;
			  }
			  if (!isset($config->debug['demo']) && request('password1')) {
				  if (request('password1')!=request('password2')) {
					  $error = 'Passwords do not match';
					  break;
				  } else if (!$database->changePassword(request('password1'))) {
					  $error = $database->error;
					  break;
				  }
			  }
		  }
		  break;
	  case 'adduser':
		  if ($session->authenticated && $database->isAdmin($session->username))
			  if (!$database->addUser(request('user'), request('name'), request('password')))
				  $error = $database->error;
		  break;
	  case 'deleteuser':
		  if ($session->authenticated && $database->isAdmin($session->username))
			  if (!$database->deleteUser(request('username')))
				  $error = $database->error;
		  break;
	  case 'changeuser':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if ((request('user')!=request('olduser')) &&
				  !$database->changeUsername(request('user'), request('olduser'))) {
				  $error = $database->error;
				  break;
			  }
			  if ((request('name')!=request('oldname')) &&
				  !$database->changeUsername(request('name'), request('olduser'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('password1')) {
				  if (request('password1')!=request('password2')) {
					  $error = 'Passwords do not match';
					  break;
				  } else if (!$database->changePassword(request('password1'), request('olduser'))) {
					  $error = $database->error;
					  break;
				  }
			  }
		  }
		  break;
	  case 'changeuseraccess':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  foreach ($_REQUEST as $name=>$value) {
				  if (preg_match('/^access_(.*)/', $name, $matches) &&
					  !$database->setAccess($matches[1], request('user'), ($value=='write' ? 'w' : 'r'))) {
					  $error = $database->error;
					  break;
				  }
			  }
		  }
		  break;
	  case 'adduseraccess':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  $prefixes = preg_split('/\s+/s', request('prefixes'));
			  foreach ($prefixes as $prefix) {
				  $oldaccess = $database->getAccess($prefix, request('user'));
				  if (!$database->setAccess($prefix, request('user'), $oldaccess['access'] == 'r' ? 'w' : 'r')) {
					  $error = $database->error;
					  break;
				  }
			  }
		  }
	  case 'changenodeaccess':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  foreach ($_REQUEST as $name=>$value) {
				  if (preg_match('/^access_(.*)/', $name, $matches) &&
					  !$database->setAccess(request('node'), $matches[1], ($value=='write' ? 'w' : 'r'))) {
					  $error = $database->error;
					  break;
				  }
			  }
		  }
		  break;
	  case 'printtree':
		  if ($session->authenticated) {
			  $node = $database->getNode(request('node'));
			  header('Content-Type: text/plain');
			  header('Content-Disposition: attachment; filename="'.$node['node'].'.txt"');
			  echo $node['node'].'    '.$node['description']."\n\n";
			  echo Tree::getTxt(request('node'));
			  exit;
		  }
		  break;
	  default:
		  debug('action: '.request('action'));
		  $error = 'Unknown action requested';
	}

}


?>
