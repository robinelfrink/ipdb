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


class Database {


	private $db = null;
	public $error = null;
	private $provider = null;
	private $dbversion = '4';
	private $prefix = '';

	public function __construct($config) {

		$this->provider = $config['provider'];
		if ($this->provider=='mysql') {
			if (function_exists('mysql_connect'))
				if ($this->db = @mysql_connect($config['server'],
											   $config['username'],
											   $config['password'])) {
					if (!@mysql_select_db($config['database'], $this->db)) {
						$this->error = mysql_error($this->db);
						mysql_close($this->db);
					}
				} else
					$this->error = mysql_error();
			else
				$this->error = 'No support for '.$this->provider.' databases';
		} else
			$this->error = 'Unknown database provider '.$this->provider;
		$this->prefix = $config['prefix'];

	}


	public function __destruct() {

		$this->close();

	}


	public function close() {

		if ($this->provider=='mysql')
			mysql_close($this->db);

	}


	public function escape($string) {

		if ($this->provider=='mysql')
			return mysql_escape_string($string);
		return addslashes($string);

	}


	public function query($sql) {

		$this->error = null;
		if ($this->provider=='mysql') {
			if (!($resource = mysql_query($sql, $this->db))) {
				$this->error = mysql_error($this->db);
				debug($this->error);
				debug('Faulty query: '.$sql);
				return false;
			}
			$result = array();
			if ($resource===true)
				return true;
			while ($row = mysql_fetch_array($resource, MYSQL_ASSOC))
				$result[] = $row;
			return $result;
		}

	}


	public function log($action) {
		global $session;
		return $this->query("INSERT INTO `".$this->prefix."log` (`stamp`, `username`, `action`) ".
							"VALUES(NOW(), '".$this->escape($session->username).
							"', '".$this->escape($action)."')");
	}


	public function hasDatabase() {

		$this->error = null;
		$version = $this->query('SELECT `version` FROM `'.$this->prefix.'version`');
		if ($this->error) {
			$this->error = null;
			return false;
		}
		return true;

	}


	public function hasUpgrade() {

		$this->error = null;
		$version = $this->query('SELECT `version` FROM `'.$this->prefix.'version`');
		return ($version[0]['version']<$this->dbversion);

	}


	public function initialize() {

		$this->error = null;
		if (in_array($this->provider, array('mysql', 'mysqli'))) {
			/* Drop old tables, even though we're pretty sure they don't exist. */
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."ip`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."ip` (".
							  "`id` INT UNSIGNED NOT NULL,".
							  "`address` varchar(32) NOT NULL,".
							  "`bits` INT UNSIGNED NOT NULL,".
							  "`parent` INT UNSIGNED NOT NULL DEFAULT 0,".
							  "`description` varchar(255),".
							  "PRIMARY KEY  (`id`),".
							  "KEY `address` (`address`),".
							  "KEY `bits` (`bits`),".
							  "UNIQUE INDEX `addressbits` (`address`, `bits`),".
							  "KEY `parent` (`parent`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			if (!$this->query("INSERT INTO `".$this->prefix.
							  "ip` (`id`, `address`, `bits`, `parent`, `description`) VALUES(".
							  "1, 'fc030000000000000000000000000000', 16, 0, ".
							  "'Default IPv6 network.')"))
				return false;
			if (!$this->query("INSERT INTO `".$this->prefix.
							  "ip` (`id`, `address`, `bits`, `parent`, `description`) VALUES(".
							  "2, '000000000000000000000000C0A80300', 120, 0, ".
							  "'Default IPv4 network.')"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."admin`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."admin` (".
							  "`username` varchar(15) NOT NULL,".
							  "`password` varchar(32) NOT NULL,".
							  "`name` varchar(50) NOT NULL,".
							  "PRIMARY KEY  (`username`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			if (!$this->query("INSERT INTO `".$this->prefix."admin` (`username`, `password`, `name`) ".
							  "VALUES('admin', '".md5('secret')."', 'Administrator')"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."version`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."version` (".
							  "`version` INT NOT NULL".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			if (!$this->query("INSERT INTO `".$this->prefix."version` (`version`) VALUES(".
							  $this->dbversion.")"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."extrafields`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."extrafields` (".
							  "`node` INT UNSIGNED NOT NULL,".
							  "`field` varchar(15) NOT NULL,".
							  "`value` varchar(255) NOT NULL,".
							  "PRIMARY KEY(`node`, `field`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."extratables`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."extratables` (".
							  "`table` varchar(15) NOT NULL,".
							  "`item` varchar(50) NOT NULL,".
							  "`description` varchar(80) NOT NULL,".
							  "`comments` text NOT NULL,".
							  "PRIMARY KEY(`table`, `item`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."tablenode`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."tablenode` (".
							  "`table` varchar(15) NOT NULL,".
							  "`item` varchar(50) NOT NULL,".
							  "`node` INT UNSIGNED NOT NULL,".
							  "PRIMARY KEY(`table`, `item`, `node`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."tablecolumn`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."tablecolumn` (".
							  "`table` varchar(15) NOT NULL,".
							  "`item` varchar(50) NOT NULL,".
							  "`column` varchar(15) NOT NULL,".
							  "`value` varchar(255) NOT NULL,".
							  "PRIMARY KEY(`table`, `item`, `column`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."log`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."log` (".
							  "`stamp` datetime NOT NULL,".
							  "`username` varchar(15) NOT NULL,".
							  "`action` varchar(255) NOT NULL".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."access`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."access` (".
							  "`node` INT UNSIGNED NOT NULL,".
							  "`username` varchar(15) NOT NULL,".
							  "`access` ENUM ('r', 'w'),".
							  "PRIMARY KEY(`node`, `username`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
		}
		return $this->log('Initialized database');

	}


	public function upgradeDb() {
		$version = $this->query('SELECT `version` FROM `'.$this->prefix.'version`');
		$version = $version[0]['version'];
		$this->error = null;
		if ($version<2) {
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."log`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."log` (".
							"`stamp` datetime NOT NULL,".
							"`username` varchar(15) NOT NULL,".
							"`action` varchar(255) NOT NULL".
							") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
		}
		if ($version<3) {
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."access`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."access` (".
							  "`node` INT UNSIGNED NOT NULL,".
							  "`username` varchar(15) NOT NULL,".
							  "`access` ENUM ('r', 'w'),".
							  "PRIMARY KEY(`node`, `username`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
		}
		if ($version<4) {
			$this->query("DROP TABLE IF EXISTS `".$this->prefix."tablecolumn`");
			$this->error = null;
			if (!$this->query("CREATE TABLE `".$this->prefix."tablecolumn` (".
							  "`table` varchar(15) NOT NULL,".
							  "`item` varchar(50) NOT NULL,".
							  "`column` varchar(15) NOT NULL,".
							  "`value` varchar(255) NOT NULL,".
							  "PRIMARY KEY(`table`, `item`, `column`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
		}
		if (!$this->query("UPDATE `".$this->prefix."version` SET version=".
						  $this->escape($this->dbversion)))
			return false;
		return $this->log('Upgraded database version '.$version.' to '.$this->dbversion);
	}


	public function getUser($username) {
		$user = $this->query("SELECT `username`, `password`, `name` FROM `".$this->prefix."admin` WHERE `username` = '".
							 $this->escape($username)."'");
		if (!is_array($user) || (count($user)==0))
			return false;
		$user = $user[0];
		$access = $this->query("SELECT `id`, `address`, `bits`, `access` FROM `".
							   $this->prefix."access` LEFT JOIN `".
							   $this->prefix."ip` ON `node`=`id` WHERE `username` = '".
							   $this->escape($username)."' ORDER BY `address`, `bits`");
		$user['access'] = $access;
		return $user;
	}


	public function getAccess($node, $username = null) {
		$address = $this->getAddress($node);
		if ($username) {
			$access = $this->query("SELECT `id`, `address`, `bits`, `access` FROM `".
								   $this->prefix."access` LEFT JOIN `".
								   $this->prefix."ip` ON `id`=`node` WHERE `username`='".
								   $this->escape($username)."' AND `address`<='".
								   $address['address']."' ORDER BY `address` DESC, `bits` DESC");
			if (is_array($access))
				foreach ($access as $key=>$entry)
					if (strcmp(broadcast($address['address'], $address['bits']),
							   broadcast($entry['address'], $entry['bits']))>0)
						unset($access[$key]);
			if (count($access)>0)
				return $access[0];
			else
				return array('id'=>0,
							 'address'=>'00000000000000000000000000000000',
							 'bits'=>0,
							 'access'=>'r');
		}
		return $this->query("SELECT `username`, `access` FROM `".
							$this->prefix."access` WHERE `node`=".
							$this->escape($node)." ORDER BY `username`");
	}


	public function getUsers() {
		return $this->query("SELECT `username`, `name` FROM `".$this->prefix."admin` ".
							"WHERE `username`!='admin' ORDER BY `username`");
	}


	public function getAddress($node) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `".
							   $this->prefix."ip` WHERE ".
							   "`id`=".$this->escape($node));
		return ($result ? $result[0] : false);
	}


	public function findAddress($address, $bits) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `".
							   $this->prefix."ip` WHERE ".
							   "`address`='".$this->escape($address)."' AND `bits`=".
							   $this->escape($bits));
		return ($result ? $result[0] : false);
	}


	public function getTree($parent, $recursive = false) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `".
							   $this->prefix."ip` WHERE ".
							   "`parent`=".$this->escape($parent)." ORDER BY `address`");
		if ($recursive===false)
			return $result;
		foreach ($result as $network)
			if (($recursive===true) ||
				(is_string($recursive) && addressIsChild($recursive, $result['address'], $result['bits'])))
				$result['children'] = $this->getTree($result['id'], $recursive);
		return $result;
	}


	public function hasChildren($parent) {
		$result = $this->query("SELECT COUNT(`id`) AS `total` FROM `".$this->prefix.
							   "ip` WHERE `parent`=".$this->escape($parent));
		return ($result[0]['total']>0);
	}


	public function hasNetworks($parent) {
		$result = $this->query("SELECT COUNT(`id`) AS `total` FROM `".$this->prefix."ip` WHERE `parent`=".
							   $this->escape($parent)." AND `bits`<128");
		return ($result[0]['total']>0);
	}


	public function getParent($address, $bits=128) {
		$entries = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `".
								$this->prefix."ip` WHERE ".
								"STRCMP('".$this->escape($address)."', `address`)>=0 ".
								"ORDER BY `address` DESC, `bits` ASC");
		if (count($entries)>0)
			foreach ($entries as $entry)
				if (strcmp(broadcast($address, $bits), broadcast($entry['address'], $entry['bits']))<=0)
					return $entry['id'];
		return 0;
	}


	public function search($search) {
		return $this->query("SELECT DISTINCT `id`, `address`, `bits`, `parent`, `description` FROM `".
							$this->prefix."ip` LEFT JOIN `".
							$this->prefix."extrafields` ON `".
							$this->prefix."extrafields`.`node`=`".
							$this->prefix."ip`.`id` WHERE `address`='".
							address2ip($search)."' OR `description` LIKE '%".
							$this->escape($search)."%' OR `".$this->prefix."extrafields`.`value` LIKE '%".
							$this->escape($search)."%'");
	}

	public function getNext($address) {
		$entry = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `".
							  $this->prefix."ip` WHERE ".
							  "STRCMP('".$this->escape($address)."', `address`)<0 ".
							  "ORDER BY `address` ASC LIMIT 1");
		return (count($entry)>0 ? $entry[0] : null);
	}


	public function addNode($address, $bits, $description) {
		global $session;

		/* Prepare for stupidity */
		if ($address=='00000000000000000000000000000000') {
			$this->error = 'The World already exists';
			return false;
		}

		$broadcast = broadcast($address, $bits);
		/* Check for exact match */
		$check = $this->query("SELECT `id` FROM `".$this->prefix."ip` WHERE `address`='".
							  $this->escape($address)."' AND `bits`=".
							  $this->escape($bits));
		if (count($check)>0) {
			$this->error = 'Node '.showip($address, $bits).' already exists';
			return false;
		}

		/* Check if network address matches bitmask */
		if (strcmp($address, network($address, $bits))!=0) {
			$this->error = 'Address '.ip2address($address).' is not on a boundary with '.(strcmp($address, '00000000000000000000000100000000')>0 ? $bits : $bits-96).' bits';
			return false;
		}

		/* Check possible parent */
		$parent = 0;
		$parents = $this->query("SELECT `id`, `address`, `bits` FROM `".$this->prefix."ip` WHERE `address`<='".
								$this->escape($address)."' ORDER BY `address` DESC, `bits` DESC");
		if (count($parents)>0)
			foreach ($parents as $parentnode)
				if (strcmp(broadcast($address, $bits), broadcast($parentnode['address'], $parentnode['bits']))<=0) {
					$parent = $parentnode['id'];
					break;
				}

		/* Check for access */
		if ($session->username!='admin') {
			$access = $this->getAccess($parent, $session->username);
			if ($access['access']!='w') {
				$this->error = 'Access denied';
				return false;
			}
		}

		/* Check possible children */
		$children = $this->getTree($parent);
		if (count($children)>0)
			foreach ($children as $id=>$childnode)
				if ((strcmp($address, $childnode['address'])>0) ||
					(strcmp(broadcast($address, $bits), broadcast($childnode['address'], $childnode['bits']))<0))
					unset($children[$id]);

		/* Add new node */
		$max = $this->query("SELECT MAX(`id`) AS `max` FROM `".$this->prefix."ip`");
		$this->query("INSERT INTO `".$this->prefix."ip` (`id`, `address`, `bits`, `parent`, `description`) VALUES(".
					 $this->escape($max[0]['max']+1).", '".
					 $this->escape($address)."', ".$this->escape($bits).", ".
					 $this->escape($parent).", '".$this->escape($description)."')");

		/* Update possible children */
		if (count($children)>0) {
			$ids = array();
			foreach ($children as $child)
				$ids[] = $child['id'];
			$this->query("UPDATE `".$this->prefix."ip` SET `parent`=".$this->escape($max[0]['max']+1).
						 " WHERE `id` IN (".implode(',', $ids).")");
		}
		$node = $max[0]['max']+1;
		$this->log('Added node '.showip($address, $bits).
				   (empty($description) ? '' : ' ('.$description.')'));
		return $node;
	}


	public function deleteNode($node, $childaction = 'none') {
		global $session;

		/* Check for access */
		if ($session->username!='admin') {
			$access = $this->getAccess($node, $session->username);
			if ($access['access']!='w') {
				$this->error = 'Access denied';
				return false;
			}
		}

		$address = $this->getAddress($node);
		if ($this->error)
			return false;
		$children = $this->query("SELECT `id` FROM `".$this->prefix."ip` WHERE `parent`=".
								 $this->escape($node));
		if ($this->error)
			return false;
		if (count($children)>0) {
			if ($childaction=='delete') {
				foreach ($children as $child)
					if (!($this->deleteNode($child['id'], $childaction)))
						return false;
				$this->query("DELETE FROM `".$this->prefix."ip` WHERE `id`=".$this->escape($node));
			} else if ($childaction=='move') {
				if (!$this->query("UPDATE `".$this->prefix."ip` SET `parent`=".
								  $this->escape($address['parent']).
								  " WHERE `parent`=".
								  $this->escape($node)))
					return false;
				if (!$this->query("DELETE FROM `".$this->prefix."ip` WHERE `id`=".$this->escape($node)))
					return false;
			} else {
				$this->error = 'Node has children';
				return false;
			}
		} else {
			if (!$this->query("DELETE FROM `".$this->prefix."ip` WHERE `id`=".$this->escape($node)))
				return false;
		}
		$this->log('Deleted node '.showip($address['address'], $address['bits']));
		return true;
	}


	public function getField($field, $node) {
		$value = $this->query("SELECT `value` FROM `".$this->prefix."extrafields` WHERE `node`=".
							  $this->escape($node)." AND `field`='".
							  $this->escape($field)."'");
		return ($value===false ? '' : $value[0]['value']);
	}


	public function changeNode($node, $address, $bits, $description) {
		global $config, $session;
		if (!($entry = $this->getAddress($node))) {
			$this->error = 'Node not found';
			return false;
		}

		/* Check for access */
		if ($session->username!='admin') {
			$access = $this->getAccess($node, $session->username);
			if ($access['access']!='w') {
				$this->error = 'Access denied';
				return false;
			}   
		}


		$changes = array();
		if (($address!=$entry['address']) || ($bits!=$entry['bits']))
			$changes[] = showip($entry['address'], $entry['bits']);
		if ($description!=$entry['description'])
			$changes[] = $entry['description'];

		/* Prepare for stupidity */
		if ($address=='00000000000000000000000000000000') {
			$this->error = 'The World already exists';
			return false;
		}

		/* Check for exact match */
		$check = $this->query("SELECT `id` FROM `".$this->prefix."ip` WHERE `address`='".
							  $this->escape($address)."' AND `bits`=".
							  $this->escape($bits));
		if ((count($check)>0) && ($check[0]['id']!=$node)) {
			$this->error = 'Node '.showip($address, $bits).' already exists';
			return false;
		}

		/* Check if network address matches bitmask */
		if (strcmp($address, network($address, $bits))!=0) {
			$this->error = 'Address '.ip2address($address).' is not on a boundary with '.(strcmp($address, '00000000000000000000000100000000')>0 ? $bits : $bits-96).' bits';
			return false;
		}

		/* Find change in address */
		$change = _xor($entry['address'], $address);

		/* Start transaction */
		if (!$this->query('BEGIN'))
			return false;

		/* Change node */
		if (!($this->query("UPDATE `".$this->prefix."ip` SET `address`='".$this->escape($address).
						   "', `bits`=".$this->escape($bits).
						   ", `description`='".$this->escape($description)."' WHERE `id`=".
						   $this->escape($node)))) {
			$error = $this->error;
			$this->query('ROLLBACK');
			$this->error = $error;
			return false;
		}

		/* Find new parent */
		$parent = $entry['parent'];
		$parents = $this->query("SELECT `id`, `address`, `bits` FROM `".$this->prefix."ip` WHERE `address`<='".
								$this->escape($address)."' AND `id`!=".$this->escape($node).
								" ORDER BY `address` DESC, `bits` DESC");
		if (count($parents)>0)
			foreach ($parents as $parentnode)
				if (strcmp(broadcast($address, $bits), broadcast($parentnode['address'], $parentnode['bits']))<=0) {
					$parent = $parentnode['id'];
					break;
				}
		if (($parent!=$entry['parent']) &&
			!$this->query("UPDATE `".$this->prefix."ip` SET `parent`=".$this->escape($parent)." WHERE `id`=".
						  $this->escape($node))) {
			$error = $this->error;
			$this->query('ROLLBACK');
			$this->error = $error;
			return false;
		}

		/* Check if old children still fit */
		$children = $this->getTree($entry['id']);
		if (count($children)>0)
			foreach ($children as $child)
				if (($child['id']!=$node) &&
					((strcmp($address, $child['address'])>0) ||
					 (strcmp(broadcast($address, $bits), broadcast($child['address'], $child['bits']))<0)))
					if (!$this->query("UPDATE `".$this->prefix."ip` SET `parent`=".$this->escape($entry['parent']).
									  ", `address`='".$this->escape(_xor($child['address'], $change))."'".
									  " WHERE `id`=".$this->escape($child['id']))) {
						$error = $this->error;
						$this->query('ROLLBACK');
						$this->error = $error;
						return false;
					}

		/* Check for new children */
		$children = $this->getTree($parent);
		if (count($children)>0) 
			foreach ($children as $child)
				if (($child['id']!=$node) &&
					(strcmp($address, $child['address'])<=0) &&
					(strcmp(broadcast($address, $bits), broadcast($child['address'], $child['bits']))>=0))
					if (!$this->query("UPDATE `".$this->prefix."ip` SET `parent`=".$this->escape($entry['id']).
									  " WHERE `id`=".$this->escape($child['id']))) {
						$error = $this->error;
						$this->query('ROLLBACK');
						$this->error = $error;
						return false;
					}

		if (!$this->query('COMMIT')) {
			$error = $this->error;
			$this->query('ROLLBACK');
			$this->error = $error;
			return false;
		}

		$this->log('Changed node '.showip($address, $bits).
				   (count($changes)>0 ? ' (was: '.implode(', ', $changes).')' : ''));
		return true;
	}


	public function setField($field, $node, $value) {
		$address = $this->getAddress($node);
		$old = $this->getField($field, $node);
		if (strcmp($value, $old)!=0) {
			return ($this->query("REPLACE INTO `".$this->prefix."extrafields` (`node`, `field`, `value`)".
								 " VALUES (".$this->escape($node).", '".
								 $this->escape($field)."', '".
								 $this->escape($value)."')") &&
					$this->log('Set field \''.$field.'\' for node '.
							   showip($address['address'], $address['bits']).' to '.
							   $value));
		}
		return true;
	}


	public function getExtra($table, $item = null) {
		if ($item===null)
			return $this->query("SELECT * FROM `".$this->prefix."extratables` WHERE `table`='".
								$this->escape($table)."' ORDER BY `item`");
		$items = $this->query("SELECT * FROM `".$this->prefix."extratables` WHERE `table`='".
							  $this->escape($table)."' AND `item`='".$this->escape($item)."'");
		if (count($items)<1)
			return false;
		$columns = $this->query("SELECT * FROM `".$this->prefix."tablecolumn` WHERE `table`='".
								$this->escape($table)."' AND `item`='".$this->escape($item)."'");
		if (count($columns)>0)
			foreach ($columns as $data)
				$items[0][$data['column']] = $data['value'];
		return $items[0];
	}


	public function addExtra($table, $item, $description, $comments, $columndata = null) {
		global $config;
		if (!isset($config->extratables[$table])) {
			$this->error = 'Unknown table '.$table;
			return false;
		}
		if (!$this->query("INSERT INTO `".$this->prefix.
						  "extratables` (`table`, `item`, `description`, `comments`) VALUES('".
						  $this->escape($table)."', '".
						  $this->escape($item)."', '".
						  $this->escape($description)."', '".
						  $this->escape($comments)."')"))
			return false;
		if (is_array($columndata) && (count($columndata)>0))
			foreach ($columndata as $column=>$data)
				if (!$this->query("INSERT INTO `".$this->prefix.
								  "tablecolumn` (`table`, `item`, `column`, `value`) VALUES('".
								  $this->escape($table)."', '".
								  $this->escape($item)."', '".
								  $this->escape($column)."', '".
								  $this->escape($data)."')"))
					return false;
		return $this->log('Added \''.$table.'\' item '.$item.
						  (empty($description) ? '' : ' ('.$description.')'));
	}


	public function changeExtra($table, $olditem, $item, $description, $comments, $columndata) {
		global $config;
		$this->query("UPDATE `".$this->prefix."extratables` SET `item`='".
					 $this->escape($item)."', `description`='".
					 $this->escape($description)."', `comments`='".
					 $this->escape($comments)."' WHERE `item`='".
					 $this->escape($olditem)."' AND `table`='".
					 $this->escape($table)."'");
		$entry = $this->getExtra($table, $olditem);
		$changes = array();
		if ($item!=$olditem)
			$changes[] = $olditem;
		if ($description!=$entry['description'])
			$changes[] = $entry['description'];
		if ($this->error)
			return false;
		if (!$this->query("UPDATE `".$this->prefix."tablenode` SET `item`='".
						  $this->escape($item)."' WHERE `item`='".
						  $this->escape($olditem)."' AND `table`='".
						  $this->escape($table)."'"))
			return false;
		if (is_array($columndata) && (count($columndata)>0))
			foreach ($columndata as $column=>$data)
				if ($data!=$entry[$column])
					if (!$this->query("REPLACE INTO `".$this->prefix.
									  "tablecolumn` (`table`, `item`, `column`, `value`) VALUES('".
									  $this->escape($table)."', '".
									  $this->escape($item)."', '".
									  $this->escape($column)."', '".
									  $this->escape($data)."')"))
						return false;
					else if ($config->extratables[$table]['column_'.$column]=='password')
						$changes[] = 'old password';
					else
						$changes[] = $column.'='.$entry[$column];
		$this->log('Changed \''.$table.'\' item '.$item.
				   (count($changes)>0 ? ' (was: '.implode(', ', $changes).')' : ''));
	}


	public function deleteExtra($table, $item) {
		$this->query("DELETE FROM `".$this->prefix."extratables` WHERE `item`='".
					 $this->escape($item)."' AND `table`='".
					 $this->escape($table)."'");
		if ($this->error)
			return false;
		$this->query("DELETE FROM `".$this->prefix."tablecolumn` WHERE `item`='".
					 $this->escape($item)."' AND `table`='".
					 $this->escape($table)."'");
		if ($this->error)
			return false;
		return ($this->query("DELETE FROM `".$this->prefix."tablenode` WHERE `item`='".
							 $this->escape($item)."' AND `table`='".
							 $this->escape($table)."'") &&
				$this->log('Deleted \''.$table.'\' item '.$item));
	}


	public function getItem($table, $node) {
		$item = $this->query("SELECT `".$this->prefix."tablenode`.`item` AS `item`, `".
							 $this->prefix."extratables`.`description` AS `description` FROM `".
							 $this->prefix."tablenode` LEFT JOIN `".
							 $this->prefix."extratables` ON `".$this->prefix."tablenode`.`item`=`".
							 $this->prefix."extratables`.`item` AND `".$this->prefix."tablenode`.`table`=`".
							 $this->prefix."extratables`.`table` WHERE `node`=".
							 $this->escape($node)." AND `".$this->prefix."tablenode`.`table`='".
							 $this->escape($table)."'");
		if ($this->error)
			return false;
		else if (count($item)>0)
			return $item[0];
		else
			return array('item'=>'-', 'description'=>'');
	}


	public function setItem($table, $item, $node, $recursive = false) {
		if (empty($item) || ($item=='-'))
			$this->query("DELETE FROM `".$this->prefix."tablenode` WHERE `table`='".
						 $this->escape($table)."' AND `node`=".
						 $this->escape($node));
		else
			$this->query("REPLACE INTO `".$this->prefix."tablenode` (`table`, `item`, `node`) VALUES('".
						 $this->escape($table)."', '".$this->escape($item)."', ".
						 $this->escape($node).")");
		if ($this->error)
			return false;
		if ($recursive) {
			$children = $this->query("SELECT `id` FROM `".$this->prefix."ip` WHERE `parent`=".
									 $this->escape($node));
			if ($this->error)
				return false;
			if (count($children)>0)
				foreach ($children as $child)
					if (!$this->setItem($table, $item, $child['id'], $recursive ))
						return false;
		}
		$address = $this->getAddress($node);
		$this->log('Set \''.$table.'\' for '.showip($address['address'], $address['bits']).' to '.$item);
		return true;
	}


	public function changeUsername($username, $oldusername) {
		return ($this->query("UPDATE `".$this->prefix."admin` SET `username`='".
							 $this->escape($username)."' WHERE `username`='".
							 $this->escape($oldusername)."'") &&
				$this->log('Changed username '.$oldusername.' to '.$username));
	}


	public function changeName($name, $username = null) {
		global $session;
		if (!$username)
			$username = $session->username;
		if ($this->query("UPDATE `".$this->prefix."admin` SET `name`='".
						 $this->escape($name)."' WHERE `username`='".
						 $this->escape($username)."'"))
			$session->changeName($name);
		else
			return false;
		$this->log('Changed name for '.$username.' to '.$name);
		return true;
	}


	public function changePassword($password, $username = null) {
		global $session;
		if (!$username)
			$username = $session->username;
		return ($this->query("UPDATE `".$this->prefix."admin` SET `password`='".
							 md5($password)."' WHERE `username`='".
							 $this->escape($username)."'") &&
				$this->log('Changed password for '.$username));
	}


	public function addUser($username, $name, $password) {
		return ($this->query("INSERT INTO `".$this->prefix."admin`(`username`, `name`, `password`) ".
							 "VALUES('".$this->escape($username).
							 "', '".$this->escape($name).
							 "', '".md5($password)."')") &&
				$this->log('Added user '.$username. ' ('.$name.')'));
	}


	public function deleteUser($username) {
		return ($this->query("DELETE FROM `".$this->prefix."admin` WHERE `username`='".
							 $this->escape($username)."'") &&
				$this->log('Deleted user '.$username));
	}


	public function getLog($search) {
		$sql = "SELECT * FROM `".$this->prefix."log`";
		if ($search && (trim($search)!=''))
			$sql .= " WHERE `username` LIKE '%".$this->escape($search).
				"%' OR `action` LIKE '%".$this->escape($search)."%'";
		$sql .= " ORDER BY `stamp` DESC";
		return $this->query($sql);
	}


	public function changeAccess($node, $username, $access) {
		$address = $this->getAddress($node);
		return ($this->query("UPDATE `".$this->prefix."access` SET `access`='".
							 ($access=='w' ? 'w' : 'r')."' WHERE `username`='".
							 $this->escape($username)."' AND `node`=".
							 $this->escape($node)) &&
				$this->log('Set '.($access=='w' ? 'write' : 'read-only').' access to '.
						   showip($address['address'], $address['bits']).' for '.$username));
	}


	public function deleteAccess($node, $username) {
		$address = $this->getAddress($node);
		return ($this->query("DELETE FROM `".$this->prefix."access` WHERE `username`='".
							 $this->escape($username)."' AND `node`=".
							 $this->escape($node)) &&
				$this->log('Deleted access rule to '.
						   showip($address['address'], $address['bits']).' for '.$username));
	}


	public function addAccess($node, $username, $access) {
		$address = $this->getAddress($node);
		return ($this->query("INSERT INTO `".$this->prefix."access` (`node`, `username`, `access`) VALUES(".
							 $this->escape($node).", '".
							 $this->escape($username)."', '".
							 ($access=='w' ? 'w' : 'r')."')") &&
				$this->log('Added '.($access=='w' ? 'write' : 'read-only').' access to '.
						   showip($address['address'], $address['bits']).' for '.$username));
	}


	public function findFree($blocks, $bits) {
		foreach ($blocks as $block) {
			$parent = $this->findAddress($block['address'], $block['bits']);
			$children = $this->getTree($parent['id']);
			if (is_array($children) && (count($children)>0)) {
				$address = $block['address'];
				$children[] = array('address'=>plus(broadcast($block['address'], $block['bits']), 1), 'bits'=>128);
				foreach ($children as $child) {
					$unused = findunused($address, $child['address']);
					if (is_array($unused) && (count($unused)>0)) {
						foreach ($unused as $free)
							if ($free['bits']<=$bits)
								return array('address'=>$free['address'], 'bits'=>$bits);
					}
					$address = plus(broadcast($child['address'], $child['bits']), 1);
				}
			} else {
				return array('address'=>$block['address'], 'bits'=>$bits);
			}
		}
		return false;
	}


}


?>
