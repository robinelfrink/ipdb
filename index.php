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


/* Request for xsd schema */
if (isset($_REQUEST['xsd'])) {
	header('Content-type: text/xml');
	readfile('classes/xmlschema.xsd');
	exit;
}


/* Include necessary files */
require_once 'actions.php';
require_once 'functions.php';
require_once 'classes/config.php';
require_once 'classes/database.php';
require_once 'classes/menu.php';
require_once 'classes/session.php';
require_once 'classes/skin.php';
require_once 'classes/tree.php';


/* Set some settings */
ini_set('session.bug_compat_warn', 0);
ini_set('session.bug_compat_42', 0);
ini_set('error_log', 'logs/errors');
ini_set('log_errors', 'on');
ini_set('display_errors','off');
error_reporting(E_ALL);
$error = false;
$debugstr = '';


/* It's good to know where we are */
$root = dirname(__FILE__);


/* Version */
$version = 0.1;


/* Check for incoming XML request */
$xml = (isset($HTTP_RAW_POST_DATA) && preg_match('/^<\?xml version=/', $HTTP_RAW_POST_DATA) ?
		$HTTP_RAW_POST_DATA :
		null);


/* Read configuration file */
$config = new Config();
if ($config->error)
	fatal($config->error);


/* Start the session */
$session = new Session($config->session);
if ($session->error)
	fatal($session->error);


/* Initialize the database */
$database = new Database($config->database);
if ($database->error)
	fatal($database->error);

if ($xml) {
	require_once 'classes/xml.php';
	XML::handle($xml);
	exit;
}

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
$oldpage = $_SESSION['page'];
$page = request('page', 'main');


/* Fetch the selected page */
if (!file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.$page.'.php')) {
	$_SESSION['page'] = $oldpage;
	fatal('No code defined for page '.$page);
}
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'pages'.DIRECTORY_SEPARATOR.$page.'.php';
$pageobj = new $page();
if (method_exists($pageobj, 'get')) {
	$pagedata = $pageobj->get();
	if ($pageobj->error)
		fatal($pageobj->error);
}


/* Send back the requested content */
send($pagedata);


?>
