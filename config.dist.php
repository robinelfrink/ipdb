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


$config = array(

	/* Database settings */
	'database' => array(
		// mysql example
		'provider' => 'mysql',
		'host' => 'localhost',
		'database' => 'ipdb',
		'username' => 'ipuser',
		'password' => 'secret',
		// sqlite example
		// 'provider' => 'sqlite',
		// 'file' => 'ipdb.db',
		'prefix' => 'ipdb_',
	),

	/* Session */
	'session' => array(
		'expire' => '10m',
	),

	/* Authentication */
	'auth' => array(
		// local authentication
		'type' => 'ipdb',
		// ldap authentication
		// 'type' => 'ldap',
		// 'url' => 'ldap.server.org',
		// 'basedn' => 'ou=users,dc=server.org',
		// 'binddn' => 'cn=root,dc=server.org',
		// 'bindpw' => 'secret',
	),

	/* Debugging mode */
	'debug' => false,

	/* Skin */
	'skin' => 'default',

	/* Additional fields, these are shown between 'address' and 'description' */
	'extrafields' => array(
		// 'hostname' => array(
		//		'type' => 'text',
		//		'description' => 'hostname',
		// ),
		// 'customercode' => array(
		//		'type' => 'url',
		//		'description' => 'customer code',
		//		'url' => 'http://customers.domain.com/?customer=%s',
		// ),
	),

	/* Additional tables */
	'extratables' => array(
		// 'vlan' => array(
		//		'type' => 'integer',
		//		'description' => 'VLAN',
		//		'linkaddress' => true,
		//		'inoverview' => true,
		// ),
	),

	/* IP pools for XML requests */
	'pools' => array(
		// 'dialup' => array('192.168.2.0/23'),
		// 'dsl' => array('192.168.4.0/24', 'fd00:d::/32'),
	),

);


?>
