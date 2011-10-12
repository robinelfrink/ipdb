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
			  send(array('commands'=>'expandtree(\''.request('leaf').'\', \''.escape(Tree::getHtml(request('leaf'))).'\');'));
		  break;
	  case 'addnode':
		  if ($session->authenticated) {
			  $address = address2ip(request('address'));
			  $bits = (strcmp($address, '00000000000000000000000100000000')<0 ? request('bits')+96 : request('bits'));
			  if (!($newnode = $database->addNode($address, $bits, request('description')))) {
				  $error = $database->error;
				  break;
			  }
			  if (count($config->extrafields)>0)
				  foreach ($config->extrafields as $field=>$details)
					  if (!$database->setField($field, $newnode, request($field), (request('field-'.$field.'-recursive')=='on' ? true : false))) {
						  $error = $database->error;
						  break;
					  }
			  if (count($config->extratables)>0)
				  foreach ($config->extratables as $table=>$details)
					  if ($details['linkaddress'] &&
						  !$database->setItem($table, request($table), $newnode, (request('table-'.$table.'-recursive')=='on' ? true : false))) {
						  $error = $database->error;
						  break;
					  }
			  request('node', $newnode, true);
			  request('page', 'main', true);
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
					  if (count($config->extrafields)>0)
						  foreach ($config->extrafields as $field=>$details)
							  if (!$database->setField($field, request('node'), request($field), (request('field-'.$field.'-recursive')=='on' ? true : false))) {
								  $error = $database->error;
								  break;
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
					  !$database->changeAccess($matches[1], request('user'), ($value=='write' ? 'w' : 'r'))) {
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
				  if (preg_match('/(.*)\/(.*)/', $prefix, $matches)) {
					  $address = address2ip($matches[1]);
					  $bits = ($address<'00000000000000000000000100000000' ? $matches[2]+96 : $matches[2]);
					  if ($node = $database->findAddress($address, $bits)) {
						  $oldaccess = $database->getAccess($node['id'], request('user'));
						  $newaccess = ($oldaccess['access'] == 'r' ? 'w' : 'r');
						  if (!$database->changeAccess($node['id'], request('user'), $newaccess)) {
							  $error = $database->error;
							  break;
						  }
					  }
				  }
			  }
		  }
	  case 'changenodeaccess':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  foreach ($_REQUEST as $name=>$value) {
				  if (preg_match('/^access_(.*)/', $name, $matches) &&
					  !$database->changeAccess(request('node'), $matches[1], ($value=='write' ? 'w' : 'r'))) {
					  $error = $database->error;
					  break;
				  }
			  }
		  }
		  break;
	  case 'printtree':
		  if ($session->authenticated) {
			  $node = $database->getAddress(request('node'));
			  $net = ip2address($node['address']).
				  ($node['address']<'00000000000000000000000100000000' ?
				   ($node['bits']<128 ? '/'.($node['bits']-96) : '') :
				   ($node['bits']<128 ? '/'.$node['bits'] : ''));
			  header('Content-Type: text/plain');
			  header('Content-Disposition: attachment; filename="'.$net.'.txt"');
			  echo $net.'    '.$node['description']."\n\n";
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
