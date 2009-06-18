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


/* Include necessary files */
require_once 'actions.php';
require_once 'functions.php';
require_once 'classes/config.php';
require_once 'classes/database.php';
require_once 'classes/session.php';
require_once 'classes/skin.php';
require_once 'classes/tree.php';


/* Set some settings */
ini_set('session.bug_compat_warn', 0);
ini_set('session.bug_compat_42', 0);
$error = false;
$debugstr = '';


/* It's good to know where we are */
$root = dirname(__FILE__);


/* Version */
$version = 0.1;


/* Read configuration file */
$config = new Config();
if ($config->error)
	exit('Error: '.$config->error);


/* Start the session */
$session = new Session($config->session);
if ($session->error)
	exit('Error: '.$session->error);


/* Initialize the database */
$database = new Database($config->database);
if ($database->error)
	exit('Error: '.$database->error);
if (!$database->hasDatabase())
	request('page', 'initdb', true);
else if (!$session->authenticate())
	request('page', 'login', true);
else if ($database->hasUpgrade())
	request('page', 'upgradedb', true);


/* Check if we need to act */
if ($action = request('action'))
	acton($action);

/* Set default page to fetch */
$page = request('page', 'main');


/* Fetch the selected page */
if (!file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.$page.'.php'))
	exit('Error: No code defined for page '.$page);
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.$page.'.php';
$pageobj = new $page();
if (method_exists($pageobj, 'get')) {
	$pagedata = $pageobj->get();
	if ($pageobj->error)
		exit('Error: '.$pageobj->error);
}


/* Send back the requested content */
send($pagedata);


?>
