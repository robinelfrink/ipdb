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

	global $config, $database, $error, $session;

	switch ($action) {
	  case 'login':
		  if ($session->authenticated) {
			  request('action', false, true);
			  if (request('remote')=='remote')
				  send(array('commands'=>'location.href=\''.me().'\';'));
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
			  send(array('commands'=>'expandtree(\''.request('leaf').'\', \''.escape(Tree::get(request('leaf'))).'\');'));
		  break;
	  case 'addnode':
		  if ($session->authenticated) {
			  $address = address2ip(request('address'));
			  $bits = (strcmp($address, '00000000000000000000000100000000')<0 ? request('bits')+96 : request('bits'));
			  $newnode = $database->addNode($address, $bits, request('description'));
			  if ($database->error) {
				  $error = $database->error;
			  } else {
				  request('node', $newnode, true);
				  request('page', 'main', true);
			  }
		  }
		  break;
	  case 'changenode':
		  if ($session->authenticated) {
			  $details = $database->getAddress(request('node'));
			  if ($database->error) {
				  $error = $database->error;
			  } else {
				  $address = address2ip(request('address'));
				  $bits = (strcmp($address, '00000000000000000000000100000000')<0 ? request('bits')+96 : request('bits'));
				  $database->changeNode(request('node'), $address, $bits,
										request('description'));
				  if ($database->error) {
					  $error = $database->error;
				  } else {
					  if (count($config->extrafields)>0) {
						  foreach ($config->extrafields as $field=>$details) {
							  if (!$database->setField($field, request('node'), request($field))) {
								  $error = $database->error;
								  break;
							  }
						  }
					  }
					  if (count($config->extratables)>0)
						  foreach ($config->extratables as $table=>$details)
							  if ($details['linkaddress'] &&
								  !$database->setItem($table, request($table), request('node'), (request($table.'-recursive')=='on' ? true : false))) {
								  $error = $database->error;
								  break;
							  }
					  if (!$error) {
						  request('page', 'main', true);
					  }
				  }
			  }
		  }
		  break;
	  case 'deletenode':
		  if ($session->authenticated) {
			  $details = $database->getAddress(request('node'));
			  if ($database->error) {
				  $error = $database->error;
			  } else {
				  $database->deleteNode(request('node'), request('childaction'));
				  if ($database->error) {
					  $error = $database->error;
				  } else {
					  request('node', $details['parent'], true);
					  request('page', 'main', true);
				  }
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
				  $searchresult = $database->search(request('search'));
				  if (count($searchresult)>0) {
					  request('node', -1, true);
					  request('page', 'main', true);
				  } else {
					  $error = 'Search result is empty.';
					  $ip = address2ip(request('search'));
					  if (strcmp($ip, request('search'))!=0) {
						  request('node', $database->getParent($ip), true);
						  request('page', 'main', true);
					  }
				  }
			  }
		  }
		  break;
	  case 'createdb':
		  $database->initialize();
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
			  foreach ($config->extratables[request('table')] as $column=>$type)
				  if (preg_match('/^column_([a-z0-9_]+)$/', $column, $matches) &&
					  request($column))
					  $columndata[$matches[1]] = request($column);
			  if ($database->addExtra(request('table'), request('item'), request('description'), request('comments'), $columndata))
				  request('page', 'extratable', true);
		  }
		  break;
	  case 'changeextra':
		  if ($session->authenticated) {
			  $columndata = array();
			  foreach ($config->extratables[request('table')] as $column=>$type)
				  if (preg_match('/^column_([a-z0-9_]+)$/', $column, $matches) &&
					  request($column))
					  $columndata[$matches[1]] = request($column);
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
			  if (request('password1')) {
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
		  if ($session->authenticated && ($session->username=='admin'))
			  if (!$database->addUser(request('user'), request('name'), request('password')))
				  $error = $database->error;
		  break;
	  case 'deleteuser':
		  if ($session->authenticated && ($session->username=='admin'))
			  if (!$database->deleteUser(request('username')))
				  $error = $database->error;
		  break;
	  case 'changeuser':
		  if ($session->authenticated && ($session->username=='admin')) {
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
	  case 'deleteaccess':
		  if ($session->authenticated && ($session->username=='admin') &&
			  !$database->deleteAccess(request('node'), request('user')))
			  $error = $database->error;
		  break;
	  case 'changeuseraccess':
		  if ($session->authenticated && ($session->username=='admin')) {
			  $user = $database->getUser(request('user'));
			  foreach ($user['access'] as $access)
				  if ((request('access_'.$access['id'])!=$access['access']) &&
					  !$database->changeAccess($access['id'], request('user'), request('access_'.$access['id']))) {
					  $error = $database->error;
					  break;
				  }
		  }
		  break;
	  case 'changenodeaccess':
		  if ($session->authenticated && ($session->username=='admin')) {
			  $access = $database->getAccess(request('node'));
			  foreach ($access as $entry)
				  if (($entry['access']!=request('access_'.$entry['username'])) &&
					  !$database->changeAccess(request('node'), $entry['username'], request('access_'.$entry['username']))) {
					  $error = $database->error;
					  break;
				  }
		  }
		  break;
	  case 'addnodeaccess':
		  if ($session->authenticated && ($session->username=='admin') &&
			  !$database->addAccess(request('node'), request('user'), request('access')))
			  $error = $database->error;
		  break;
	  default:
		  debug('action: '.request('action'));
		  $error = 'Unknown action requested';
	}

}


?>
