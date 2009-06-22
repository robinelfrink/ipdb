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
			  if (request('remote')=='remote')
				  send(array('commands'=>'location.href=\''.me().'\''));
		  	  else
			  	request('page', 'main', true);
		  }
		  break;
	  case 'logout':
		  if (request('remote')!='remote')
			  header('Location: '.me());
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
			  $searchresult = $database->search(request('search'));
			  if (count($searchresult)>0) {
				  request('node', -1, true);
				  request('page', 'main', true);
			  } else
				  $error = 'Search result is empty.';
		  }
		  break;
	  case 'createdb':
		  $database->initialize();
		  if (!$database->error)
			  request('page', 'main', true);
		  break;
	  default:
		  $error = 'Unknown action requested';
	}

}


?>
