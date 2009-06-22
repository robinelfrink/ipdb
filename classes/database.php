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
	private $dbversion = '1';


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


	public function hasDatabase() {

		$this->error = null;
		$version = $this->query('SELECT version FROM version');
		if ($this->error) {
			$this->error = null;
			return false;
		}
		return true;

	}


	public function hasUpgrade() {

		$this->error = null;
		$version = $this->query('SELECT version FROM version');
		return ($version[0]['version']<1);

	}


	public function initialize() {

		$this->error = null;
		if (in_array($this->provider, array('mysql', 'mysqli'))) {
			/* Drop old tables, even though we're pretty sure they don't exist. */
			$this->query("DROP TABLE ip");
			if (!$this->query("CREATE TABLE `ip` (".
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
			if (!$this->query("INSERT INTO `ip` (`id`, `address`, `bits`, `parent`, `description`) VALUES(".
							  "1, 'fc030000000000000000000000000000', 16, 0, ".
							  "'Default IPv6 network.')"))
				return false;
			if (!$this->query("INSERT INTO `ip` (`id`, `address`, `bits`, `parent`, `description`) VALUES(".
							  "2, '000000000000000000000000C0A80300', 120, 0, ".
							  "'Default IPv4 network.')"))
				return false;
			$this->query("DROP TABLE admin");
			if (!$this->query("CREATE TABLE `admin` (".
							  "`username` varchar(15) NOT NULL,".
							  "`password` varchar(32) NOT NULL,".
							  "PRIMARY KEY  (`username`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			if (!$this->query("INSERT INTO `admin` (`username`, `password`) VALUES('admin', '".
							  md5('secret')."')"))
				return false;
			$this->query("DROP TABLE version");
			if (!$this->query("CREATE TABLE `version` (".
							  "`version` INT NOT NULL".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
			if (!$this->query("INSERT INTO `version` (`version`) VALUES(1)"))
				return false;
			$this->query("DROP TABLE extrafields");
			if (!$this->query("CREATE TABLE `extrafields` (".
							  "`node` INT UNSIGNED NOT NULL,".
							  "`field` varchar(15) NOT NULL,".
							  "`value` varchar(255) NOT NULL,".
							  "PRIMARY KEY(`node`, `field`)".
							  ") ENGINE=InnoDB DEFAULT CHARSET=utf8"))
				return false;
		}

	}


	public function getUser($username) {
		return $this->query("SELECT `password` FROM `admin` WHERE `username` = '".
							$this->escape($username)."'");
	}


	public function getAddress($node) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `ip` WHERE ".
							   "`id`=".$this->escape($node));
		return ($result ? $result[0] : false);
	}


	public function findAddress($address, $bits) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `ip` WHERE ".
							   "`address`='".$this->escape($address)."' AND `bits`=".
							   $this->escape($bits));
		return ($result ? $result[0] : false);
	}


	public function getTree($parent, $recursive = false) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `ip` WHERE ".
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
		$result = $this->query("SELECT COUNT(`id`) AS `total` FROM `ip` WHERE `parent`=".
							   $this->escape($parent));
		return ($result[0]['total']>0);
	}


	public function hasNetworks($parent) {
		$result = $this->query("SELECT COUNT(`id`) AS `total` FROM `ip` WHERE `parent`=".
							   $this->escape($parent)." AND `bits`<128");
		return ($result[0]['total']>0);
	}


	public function getParentTree($address) {
		$tree = array();
		$entry = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `ip` WHERE ".
							  "STRCMP('".$this->escape($address)."', `address`)>=0 ".
							  "ORDER BY `address` DESC, `bits` ASC LIMIT 1");
		if (count($entry)>0) {
			$entry = $entry[0];
			array_unshift($tree, $entry);
			while ($entry && ($entry['parent']>0)) {
				$entry = $this->getAddress($entry['parent']);
				array_unshift($tree, $entry);
			}
		}
		return $tree;
	}


	public function search($search) {
		return $this->query("SELECT DISTINCT `id`, `address`, `bits`, `parent`, `description` FROM `ip` LEFT JOIN ".
							"`extrafields` ON `extrafields`.`node`=`ip`.`id` WHERE `address`='".
							address2ip($search)."' OR `description` LIKE '%".
							$this->escape($search)."%' OR `extrafields`.`value` LIKE '%".
							$this->escape($search)."%'");
	}

	public function getNext($address) {
		$entry = $this->query("SELECT `id`, `address`, `bits`, `parent`, `description` FROM `ip` WHERE ".
							  "STRCMP('".$this->escape($address)."', `address`)<0 ".
							  "ORDER BY `address` ASC LIMIT 1");
		return (count($entry)>0 ? $entry[0] : null);
	}


	public function addNode($address, $bits, $description) {
		/* Prepare for stupidity */
		if ($address=='00000000000000000000000000000000') {
			$this->error = 'The World already exists';
			return false;
		}

		$broadcast = broadcast($address, $bits);
		/* Check for exact match */
		$check = $this->query("SELECT `id` FROM `ip` WHERE `address`='".
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
		$parents = $this->query("SELECT `id`, `address`, `bits` FROM `ip` WHERE `address`<='".
								$this->escape($address)."' ORDER BY `address` DESC, `bits` DESC");
		if (count($parents)>0)
			foreach ($parents as $parentnode)
				if (strcmp(broadcast($address, $bits), broadcast($parentnode['address'], $parentnode['bits']))<=0) {
					$parent = $parentnode['id'];
					break;
				}

		/* Check possible children */
		$children = $this->getTree($parent);
		if (count($children)>0)
			foreach ($children as $id=>$childnode)
				if ((strcmp($address, $childnode['address'])>0) ||
					(strcmp(broadcast($address, $bits), broadcast($childnode['address'], $childnode['bits']))<0))
					unset($children[$id]);

		/* Add new node */
		$max = $this->query("SELECT MAX(`id`) AS `max` FROM `ip`");
		$this->query("INSERT INTO `ip` (`id`, `address`, `bits`, `parent`, `description`) VALUES(".
					 $this->escape($max[0]['max']+1).", '".
					 $this->escape($address)."', ".$this->escape($bits).", ".
					 $this->escape($parent).", '".$this->escape($description)."')");

		/* Update possible children */
		if (count($children)>0) {
			$ids = array();
			foreach ($children as $child)
				$ids[] = $child['id'];
			$this->query("UPDATE `ip` SET `parent`=".$this->escape($max[0]['max']+1).
						 " WHERE `id` IN (".implode(',', $ids).")");
		}
		return $max[0]['max']+1;
	}


	public function deleteNode($node, $childaction = 'none') {
		$address = $this->getAddress($node);
		if ($this->error)
			return false;
		$children = $this->query("SELECT `id` FROM `ip` WHERE `parent`=".
								 $this->escape($node));
		if ($this->error)
			return false;
		if (count($children)>0) {
			if ($childaction=='delete') {
				foreach ($children as $child)
					if (!($this->deleteNode($child['id'], $childaction)))
						return false;
				$this->query("DELETE FROM `ip` WHERE `id`=".$this->escape($node));
			} else if ($childaction=='move') {
				if (!$this->query("UPDATE `ip` SET `parent`=".
								  $this->escape($address['parent']).
								  " WHERE `parent`=".
								  $this->escape($node)))
					return false;
				if (!$this->query("DELETE FROM `ip` WHERE `id`=".$this->escape($node)))
					return false;
			} else {
				$this->error = 'Node has children';
				return false;
			}
		} else {
			if (!$this->query("DELETE FROM `ip` WHERE `id`=".$this->escape($node)))
				return false;
		}
		return true;
	}


	public function getField($field, $node) {
		$value = $this->query("SELECT `value` FROM `extrafields` WHERE `node`=".
							  $this->escape($node)." AND `field`='".
							  $this->escape($field)."'");
		return ($value===false ? '' : $value[0]['value']);
	}


	public function changeNode($node, $address, $bits, $description) {
		global $config;
		if (!($entry = $this->getAddress($node))) {
			$this->error = 'Node not found';
			return false;
		}

		/* Prepare for stupidity */
		if ($address=='00000000000000000000000000000000') {
			$this->error = 'The World already exists';
			return false;
		}

		/* Check for exact match */
		$check = $this->query("SELECT `id` FROM `ip` WHERE `address`='".
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

		/* Start transaction */
		if (!$this->query('BEGIN'))
			return false;

		/* Change node */
		if (!($this->query("UPDATE `ip` SET `address`='".$this->escape($address).
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
		$parents = $this->query("SELECT `id`, `address`, `bits` FROM `ip` WHERE `address`<='".
								$this->escape($address)."' AND `id`!=".$this->escape($node).
								" ORDER BY `address` DESC, `bits` DESC");
		if (count($parents)>0)
			foreach ($parents as $parentnode)
				if (strcmp(broadcast($address, $bits), broadcast($parentnode['address'], $parentnode['bits']))<=0) {
					$parent = $parentnode['id'];
					break;
				}
		if (($parent!=$entry['parent']) &&
			!$this->query("UPDATE `ip` SET `parent`=".$this->escape($parent)." WHERE `id`=".
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
					if (!$this->query("UPDATE `ip` SET `parent`=".$this->escape($entry['parent']).
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
					if (!$this->query("UPDATE `ip` SET `parent`=".$this->escape($entry['id']).
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

		return true;
	}


	public function setField($field, $node, $value) {
		$old = $this->getField($field, $node);
		if (strcmp($value, $old)!=0)
			return $this->query("REPLACE INTO `extrafields` (`node`, `field`, `value`) VALUES (".
								$this->escape($node).", '".
								$this->escape($field)."', '".
								$this->escape($value)."')");
		return true;
	}


}


?>
