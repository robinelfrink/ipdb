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
				  send(array('commands'=>array('location.reload();')));
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
			  if (!($node = $database->addNode(request('address').'/'.request('bits'), request('nodename'), request('description')))) {
				  $error = $database->error;
				  break;
			  }
			  $customfields = $database->getCustomFields();
			  if (count($customfields)>0)
				  foreach ($customfields as $field)
					  if (!$database->setNodeCustomField($field['field'], $node, request('field-'.$field['field']), (request('field-'.$field['field'].'-recursive')=='on' ? true : false))) {
						  $error = $database->error;
						  break;
					  }
			  $customtables = $database->getCustomTables();
			  if (count($customtables)>0)
				  foreach ($customtables as $table)
					  if ($table['linkaddress'] &&
						  !$database->setNodeCustomTableItem($table['table'], $node, request('table-'.$table), (request('table-'.$table.'-recursive')=='on' ? true : false))) {
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
				  $database->changeNode(request('node'), request('address').'/'.request('bits'), request('nodename'), request('description'));
				  if ($database->error) {
					  $error = $database->error;
				  } else {
					  $customfields = $database->getCustomFields();
					  if (count($customfields)>0)
						  foreach ($customfields as $field) {
							  $value = $database->getNodeCustomField($field['field'], request('node'));
							  if ((($value!=request('field-'.$field['field'])) ||
								   (request('field-'.$field['field'].'-recursive')=='on')) &&
								  $database->setNodeCustomField($field['field'], request('node'), request('field-'.$field['field']), (request('field-'.$field['field'].'-recursive')=='on'))) {
								  $error = $database->error;
								  break;
							  }
						  }
					  $customtables = $database->getCustomTables();
					  if (count($customtables)>0)
						  foreach ($customtables as $table) {
						  	  $item = $database->getNodeCustomTableItem($table['table'], request('node'));
							  if ($table['linkaddress'] &&
								  ($item['item']!=request('table-'.$table['table'])) &&
								  !$database->setItem($table['table'], request('node'), request('table-'.$table['table']), (request('table-'.$table['table'].'-recursive')=='on'))) {
								  $error = $database->error;
								  break;
							  }
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
					  // If it's an IP address/block, try to find parent
					  try {
						  $node = $database->getParent(request('search'));
						  request('node', $node['node'], true);
					  } catch (Exception $e) {
						  // Ignore the error.
					  }
					  request('page', 'main', true);
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
		  $database->upgradeDb($config);
		  if ($database->error)
			  $error = $database->error;
		  break;
	  case 'addcustomtableitem':
		  if ($session->authenticated) {
			  $columndata = array();
			  if (($table = $database->getCustomTable(request('table'))) &&
				  isset($table['columns']) &&
				  is_array($table['columns']) &&
				  count($table['columns']))
				  foreach ($table['columns'] as $column=>$type)
					  if (request($column))
						  $columndata[$column] = request($column);
			  if ($database->addCustomTableItem(request('table'), request('item'), request('description'), request('comments'), $columndata))
				  request('page', 'customtable', true);
		  }
		  break;
	  case 'changecustomtableitem':
		  if ($session->authenticated) {
			  $columndata = array();
			  if (($table = $database->getCustomTable(request('table'))) &&
				  isset($table['columns']) &&
				  is_array($table['columns']) &&
				  count($table['columns']))
				  foreach ($table['columns'] as $column=>$type)
					  if (request($column))
						  $columndata[$column] = request($column);
			  $database->changeCustomTableItem(request('table'), request('olditem'), request('item'), request('description'), request('comments'), $columndata);
			  request('page', 'customtable', true);
		  }
		  break;
	  case 'deletecustomtableitem':
		  if ($session->authenticated &&
			  $database->deleteCustomTableItem(request('table'), request('item')))
			  request('page', 'customtable', true);
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
			  if (!$database->deleteUser(request('user')))
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
				  !$database->changeUsername(request('name'), request('user'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('password1')) {
				  if (request('password1')!=request('password2')) {
					  $error = 'Passwords do not match';
					  break;
				  } else if (!$database->changePassword(request('password1'), request('user'))) {
					  $error = $database->error;
					  break;
				  }
			  }
			  if ((request('admin')!=request('oldadmin')) &&
				  !$database->changeAdmin(request('admin')=='on', request('user'))) {
				  $error = $database->error;
				  break;
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
			  echo $node['node']."\t".$node['name']."\t".$node['description']."\n\n";
			  echo Tree::getTxt(request('node'));
			  exit;
		  }
		  break;
	  case 'changecustomfield':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if (!$database->changeCustomField(request('oldfield'), request('fieldname'), request('type'),
											    request('description'), request('inoverview')=='on', request('url'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=customfields\';')));
			  else {
				  request('page', 'fields', true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'addcustomfield':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if (!$database->addCustomField(request('fieldname'), request('type'), request('description'),
											 request('inoverview'), request('url'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=customfields\';')));
			  else {
				  request('page', 'customfields', true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'deletecustomfield':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if (!$database->removeCustomField(request('fieldname'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=customfields\';')));
			  else {
				  request('page', 'customfields', true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'addcustomtable':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if (!$database->addCustomTable(request('tablename'), request('type'), request('description'),
											 request('inoverview'), request('linkaddress'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=editcustomtable&table='.request('tablename').'\';')));
			  else {
				  request('page', 'editcustomtable', true);
				  request('table', request('tablename'), true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'deletecustomtable':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if (!$database->deleteCustomTable(request('table'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=customtables\';')));
			  else {
				  request('page', 'customtables', true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'changecustomtable':
		  if ($session->authenticated && $database->isAdmin($session->username)) {
			  if (!$database->changeCustomTable(request('oldtable'), request('tablename'), request('type'),
												request('description'), request('inoverview'), request('linkaddress'))) {
				  $error = $database->error;
				  break;
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=editcustomtable&table='.request('tablename').'\';')));
			  else {
				  request('page', 'editcustomtable', true);
				  request('table', request('tablename'), true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  case 'changecustomtablecolumns':
		  if ($session->authenticated && $database->isAdmin($session->username) &&
			  ($table = $database->getCustomTable(request('table')))) {
			  if (request('submit')=='_add') {
				  if (!$database->addCustomTableColumn(request('table'), request('_name'), request('_type'))) {
					  $error = $database->error;
					  break;
				  }
			  } elseif (preg_match('/(.*)_delete$/', request('submit'), $matches)) {
				  if (!$database->deleteCustomTableColumn(request('table'), $matches[1])) {
					  $error = $database->error;
					  break;
				  }
			  } else {
				  foreach ($table['columns'] as $column=>$type) {
					  if (!empty($column) &&
						  (($column!=request($column.'_name')) ||
						   ($type!=request($column.'_type'))) &&
						  !$database->changeCustomTableColumn(request('table'), $column, request($column.'_name'), request($column.'_type'))) {
						  $error = $database->error;
						  break;
					  }
				  }
			  }
			  if (request('remote')=='remote')
				  send(array('commands'=>array('location.href=\''.me().'?page=editcustomtable&table='.request('table').'\';')));
			  else {
				  request('page', 'editcustomtable', true);
				  request('table', request('table'), true);
				  header('Location: '.me());
			  }
			  exit;
		  }
		  break;
	  default:
		  debug('action: '.request('action'));
		  $error = 'Unknown action requested';
	}

}


?>
