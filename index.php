<?php

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


/* Include necessary files */
require_once 'functions.php';
require_once 'classes/config.php';
require_once 'classes/database.php';
require_once 'classes/session.php';
require_once 'classes/skin.php';


/* Set default page to fetch */
$page = request('page', 'main');


/* It's good to know where we are */
$root = dirname(__FILE__);


/* Version */
$version = 0.1;


/* Read configuration file */
$config = new Config();
if ($config->error)
	exit('Error: '.$config->error);


/* Start the session */
$session = new Session();
if ($session->error)
	exit('Error: '.$session->error);


/* Initialize the database */
$database = new Database($config->database);
if ($database->error)
	exit('Error: '.$database->error);
if (!$database->hasDatabase())
	$page = 'initdb';
else if (!$session->authenticate())
	$page = 'login';
else if ($database->hasUpgrade())
	$page = 'upgradedb';


/* Fetch the selected page */
if (!file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.$page.'.php'))
	exit('Error: No code defined for page '.$page);
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.$page.'.php';
$pageobj = new $page();
if (request('action', null) &&
	method_exists($pageobj, 'action')) {
	$pageobj->action();
	if ($pageobj->error)
		exit('Error: '.$pageobj->error);
}
if (get_class($pageobj)!=$page)
	$pageobj = new $page();
$pagedata = $pageobj->get();
if ($pageobj->error)
	exit('Error: '.$pageobj->error);



/* Send back the requested content */
$skin = new Skin($config->skin);
if ($skin->error)
	exit('Error: '.$skin->error);
$skin->setFile('index.html');
$skin->setVar('title', $pagedata['title']);
$skin->setVar('version', $version);
$skin->setVar('content', $pagedata['content']);
echo $skin->get();



/* Close the database */
$database->close();


?>
