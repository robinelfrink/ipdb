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
require_once 'classes/tree.php';


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


/* Set default page to fetch */
$page = (request('page') ? request('page') : (isset($_SESSION['page']) ? $_SESSION['page'] : 'main'));


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


/* Save page and node request */
$_SESSION['page'] = $page;
$node = (request('node') ? request('node') : (isset($_SESSION['node']) ? $_SESSION['node'] : null));
$_SESSION['node'] = $node;


/* AJAX requests */
if (request('remote')=='remote') {
	header('Content-type: text/xml; charset=utf-8');
	header("Cache-Control: no-cache, must-revalidate");
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	if ($session->authenticated && (request('action')=='getsubtree')) {
		$commands = str_split(escape('expandtree(\''.$node.'\', \''.escape(Tree::get($node)).'\');'), 1024);
		echo '<?xml version="1.0" encoding="UTF-8"?>
<content>
	<commands>'.implode('</commands><commands>', $commands).'</commands>
</content>';
		exit;
	}
}


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

if (request('remote')=='remote') {
	echo '<?xml version="1.0" encoding="UTF-8"?>
<content>
	'.(isset($pagedata['title']) ? '<title>'.implode('</title><title>', str_split(escape($pagedata['title']), 1024)).'</title>' : '').'
	'.(isset($pagedata['content']) ? '<content>'.implode('</content><content>', str_split(escape($pagedata['content']), 1024)).'</content>' : '').'
	'.(isset($pagedata['tree']) ? '<tree>'.implode('</tree><tree>', str_split(escape($pagedata['tree']), 1024)).'</tree>' : '').'
	'.(isset($pagedata['menu']) ? '<menu>'.implode('</menu><menu>', str_split(escape($pagedata['menu']), 1024)).'</menu>' : '').'
	'.(isset($pagedata['commands']) ? '<commands>'.implode('</commands><commands>', str_split(escape($pagedata['commands']), 1024)).'</commands>' : '').'
</content>';
} else {
	$skin->setFile('index.html');
	$skin->setVar('title', $pagedata['title']);
	$skin->setVar('version', $version);
	$skin->setVar('meta', '<script type="text/javascript" src="ipdb.js"></script>');
	if ($session->authenticated)
		$skin->setVar('tree', Tree::get(0, $node));
	$skin->setVar('content', $pagedata['content']);
	echo $skin->get();
}


/* Close the database */
$database->close();


?>
