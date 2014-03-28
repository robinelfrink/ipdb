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


class Database {


	private $db = null;
	public $error = null;
	private $provider = null;
	private $dbversion = '6';
	private $prefix = '';

	public function __construct($config) {

		try {
			$this->db = new PDO($config['dsn'],
								isset($config['username']) ? $config['username'] : '',
								isset($config['password']) ? $config['password'] : '',
								isset($config['options']) ? $config['options'] : array());
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)=='mysql') {
				/* Set default character set */
				$this->db->exec("SET collation_connection = utf8_unicode_ci");
				$this->db->exec("SET NAMES utf8");
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
		}
		$this->prefix = $config['prefix'];

	}


	public function log($action) {
		global $session;
		$sql = "INSERT INTO `".$this->prefix."log` (`stamp`, `username`, `action`) ".
			"VALUES(?, ?, ?)";
		try {
			$stmt = $this->db->prepare($sql);
			return $stmt->execute(array(date('c'), $session->username, $action));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function hasDatabase() {

		$this->error = null;
		$sql = "SELECT `version` FROM `".$this->prefix."version`";
		try {
			$stmt = $this->db->prepare($sql);
			$stmt->execute();
			if ($stmt->fetch())
				return true;
			return false;
		} catch (PDOException $e) {
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

	}


	public function hasUpgrade() {

		return ($this->getVersion()<$this->dbversion);

	}


	private function getVersion() {

		$this->error = null;
		$sql = "SELECT `version` FROM `".$this->prefix."version`";
		try {
			$stmt = $this->db->prepare($sql);
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_ASSOC))
				return $row['version'];
			$this->error = 'Version unknown';
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
		}
		return false;

	}


	public function initialize() {

		$this->error = null;
		try {
			if (in_array($this->db->getAttribute(PDO::ATTR_DRIVER_NAME), array('mysql', 'sqlite')))
				/* Drop old tables, even though we're pretty sure they don't exist. */
				foreach (array('ip', 'version', 'extrafields', 'extratables',
							   'tablenode', 'tablecolumn', 'log', 'access') as $table)
					$this->db->exec("DROP TABLE IF EXISTS `".$this->prefix.$table."`");

			/* ip */
			$this->db->exec("CREATE TABLE `".$this->prefix."ip` (".
								"`id` INT UNSIGNED NOT NULL,".
								"`address` varchar(32) NOT NULL,".
								"`bits` INT UNSIGNED NOT NULL,".
								"`parent` INT UNSIGNED NOT NULL DEFAULT 0,".
								"`description` varchar(255),".
								"PRIMARY KEY (`id`)".
							")");
			$this->db->exec("CREATE UNIQUE INDEX `addressbits` ON `".$this->prefix."ip` (`address`, `bits`)");
			$this->db->exec("CREATE INDEX `address` ON `".$this->prefix."ip` (`address`)");
			$this->db->exec("CREATE INDEX `bits` ON `".$this->prefix."ip` (`bits`)");
			$this->db->exec("CREATE INDEX `parent` ON `".$this->prefix."ip` (`parent`)");
			$this->db->exec("INSERT INTO `".$this->prefix."ip` (`id`, `address`, `bits`, `parent`, `description`) ".
							"VALUES(1, 'fc030000000000000000000000000000', 16, 0, 'Default IPv6 network.')");
			$this->db->exec("INSERT INTO `".$this->prefix."ip` (`id`, `address`, `bits`, `parent`, `description`) ".
							"VALUES(2, '000000000000000000000000C0A80300', 120, 0, 'Default IPv4 network.')");

			/* users */
			$this->db->exec("CREATE TABLE `".$this->prefix."users` (".
								"`username` varchar(15) NOT NULL,".
								"`password` varchar(32) NOT NULL,".
								"`name` varchar(50) NOT NULL,".
								"`admin` tinyint(1) NOT NULL DEFAULT 0,".
								"PRIMARY KEY  (`username`)".
							")");
			$this->db->exec("INSERT INTO `".$this->prefix."users` (`username`, `password`, `name`,`admin`) ".
							"VALUES('admin', '".md5('secret')."', 'Administrator', 1)");

			/* version */
			$this->db->exec("CREATE TABLE `".$this->prefix."version` (".
								"`version` INT NOT NULL".
							")");
			$this->db->exec("INSERT INTO `".$this->prefix."version` (`version`) ".
							"VALUES(".$this->dbversion.")");

			/* extrafields */
			$this->db->exec("CREATE TABLE `".$this->prefix."extrafields` (".
								"`node` INT UNSIGNED NOT NULL,".
								"`field` varchar(15) NOT NULL,".
								"`value` varchar(255) NOT NULL,".
								"PRIMARY KEY(`node`, `field`)".
							")");

			/* extratables */
			$this->db->exec("CREATE TABLE `".$this->prefix."extratables` (".
								"`table` varchar(15) NOT NULL,".
								"`item` varchar(50) NOT NULL,".
								"`description` varchar(80) NOT NULL,".
								"`comments` text NOT NULL,".
								"PRIMARY KEY(`table`, `item`)".
							")");

			/* tablenode */
			$this->db->exec("CREATE TABLE `".$this->prefix."tablenode` (".
								"`table` varchar(15) NOT NULL,".
								"`item` varchar(50) NOT NULL,".
								"`node` INT UNSIGNED NOT NULL,".
								"PRIMARY KEY(`table`, `item`, `node`)".
							")");

			/* tablecolumn */
			$this->db->exec("CREATE TABLE `".$this->prefix."tablecolumn` (".
								"`table` varchar(15) NOT NULL,".
								"`item` varchar(50) NOT NULL,".
								"`column` varchar(15) NOT NULL,".
								"`value` varchar(255) NOT NULL,".
								"PRIMARY KEY(`table`, `item`, `column`)".
							")");

			/* log */
			$this->db->exec("CREATE TABLE `".$this->prefix."log` (".
								"`stamp` datetime NOT NULL,".
								"`username` varchar(15) NOT NULL,".
								"`action` varchar(255) NOT NULL".
							")");

			/* access */
			$this->db->exec("CREATE TABLE `".$this->prefix."access` (".
								"`node` INT UNSIGNED NOT NULL,".
								"`username` varchar(15) NOT NULL,".
								"`access` VARCHAR(1),".
								"PRIMARY KEY(`node`, `username`)".
							")");
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return $this->log('Initialized database');

	}


	public function upgradeDb() {
		global $session;
		if (!$this->isAdmin($session->username)) {
			$this->error = 'Access denied';
			return false;
		}
		try {
			$sql = "SELECT `version` FROM `".$this->prefix."version`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute();
			if (!($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
				$this->error = 'Version unknown';
				return false;
			}
			$version = $row['version'];
			if ($version<2) {
				if (in_array($this->db->getAttribute(PDO::ATTR_DRIVER_NAME), array('mysql', 'sqlite')))
					$this->db->exec("DROP TABLE IF EXISTS `".$this->prefix."log`");
				$this->db->exec("CREATE TABLE `".$this->prefix."log` (".
									"`stamp` datetime NOT NULL,".
									"`username` varchar(15) NOT NULL,".
									"`action` varchar(255) NOT NULL".
								")");
			}
			if ($version<3) {
				if (in_array($this->db->getAttribute(PDO::ATTR_DRIVER_NAME), array('mysql', 'sqlite')))
					$this->db->exec("DROP TABLE IF EXISTS `".$this->prefix."access`");
				$this->db->exec("CREATE TABLE `".$this->prefix."access` (".
									"`node` INT UNSIGNED NOT NULL,".
									"`username` varchar(15) NOT NULL,".
									"`access` ENUM ('r', 'w'),".
									"PRIMARY KEY(`node`, `username`)".
								")");
			}
			if ($version<4) {
				if (in_array($this->db->getAttribute(PDO::ATTR_DRIVER_NAME), array('mysql', 'sqlite')))
					$this->db->exec("DROP TABLE IF EXISTS `".$this->prefix."tablecolumn`");
				$this->db->exec("CREATE TABLE `".$this->prefix."tablecolumn` (".
									"`table` varchar(15) NOT NULL,".
									"`item` varchar(50) NOT NULL,".
									"`column` varchar(15) NOT NULL,".
									"`value` varchar(255) NOT NULL,".
									"PRIMARY KEY(`table`, `item`, `column`)".
								")");
			}
			if ($version<5) {
				if (in_array($this->db->getAttribute(PDO::ATTR_DRIVER_NAME), array('mysql', 'sqlite')))
					$this->db->exec("DROP TABLE IF EXISTS `".$this->prefix."users`");
				$this->db->exec("ALTER TABLE `".$this->prefix."admin` ".
								"RENAME TO `".$this->prefix."users`");
				$this->db->exec("ALTER TABLE `".$this->prefix."users` ".
								"ADD COLUMN `admin` tinyint(1) NOT NULL DEFAULT 0");
				$this->db->exec("UPDATE `".$this->prefix."users` SET `admin`=1 WHERE `username`='admin'");
			}
			if ($version<6)
				$this->db->exec("CREATE INDEX `".$this->prefix."log` ".
								"ON `".$this->prefix."log`(`stamp`, `username`, `action`)");
			$sql = "UPDATE `".$this->prefix."version` SET version=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$this->dbversion));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return $this->log('Upgraded database version '.$version.' to '.$this->dbversion);
	}


	public function getUser($username) {
		try {
			if ($this->getVersion()<5) {
				$sql = "SELECT `username`, `password`, `name`, IF ('admin' = ?, 1, 0) AS `admin` ".
					"FROM `".$this->prefix."admin` ".
					"WHERE `username` = ?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($username, $username));
			} else {
				$sql = "SELECT `username`, `password`, `name`, `admin` ".
					"FROM `".$this->prefix."users` ".
					"WHERE `username` = ?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($username));
			}
			if (!($user = $stmt->fetch(PDO::FETCH_ASSOC)))
				return false;
			$sql = "SELECT `id`, `address`, `bits`, `access` ".
				"FROM `".$this->prefix."access` ".
				"LEFT JOIN `".$this->prefix."ip` ".
				"ON `node`=`id` ".
				"WHERE `username` = ? ".
				"ORDER BY `address`, `bits`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($username));
			$user['access'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
			return $user;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function isAdmin($username) {
		$user = $this->getUser($username);
		return ($user['admin'] ? true : false);
	}


	public function getAccess($node, $username = null) {
		$address = $this->getAddress($node);
		try {
			if ($username) {
				$sql = "SELECT `id`, `address`, `bits`, `access` ".
					"FROM `".$this->prefix."access` ".
					"LEFT JOIN `".$this->prefix."ip` ".
					"ON `id`=`node` ".
					"WHERE `username`=? ".
					"AND `address`<=? ".
					"ORDER BY `address` DESC, `bits` DESC";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($username, $address['address']));
				$access = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if (is_array($access))
					foreach ($access as $key=>$entry)
						if (strcmp(broadcast($address['address'], $address['bits']),
								   broadcast($entry['address'], $entry['bits']))>0)
							unset($access[$key]);
				if (count($access)>0)
					return reset($access);
				else
					return array('id'=>0,
								 'address'=>'00000000000000000000000000000000',
								 'bits'=>0,
								 'access'=>'r');
			}
			$sql = "SELECT `username`, `access` ".
				"FROM `".$this->prefix."access` ".
				"WHERE `node`=? ".
				"ORDER BY `username`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address['address']));
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function getUsers() {
		try {
			$sql = "SELECT `username`, `password`, `name`, `admin` ".
				"FROM `".$this->prefix."users` ".
				"ORDER BY `username`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function getAddress($node) {
		try {
			$sql = "SELECT `id`, `address`, `bits`, `parent`, `description` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `id`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$node));
			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function findAddress($address, $bits) {
		try {
			$sql = "SELECT `id`, `address`, `bits`, `parent`, `description` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `address`=? ".
				"AND `bits`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address, (int)$bits));
			return $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}   
	}


	public function getTree($parent, $recursive = false) {
		$sql = "SELECT `id`, `address`, `bits`, `parent`, `description` FROM `".$this->prefix."ip` ".
			"WHERE `parent`=? ORDER BY `address`";
		try {
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$parent));
			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if ($recursive===false)
				return $result;
			foreach ($result as $network)
				if (($recursive===true) ||
					(is_string($recursive) && addressIsChild($recursive, $result['address'], $result['bits'])))
					$result['children'] = $this->getTree($result['id'], $recursive);
			return $result;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function hasChildren($parent) {
		try {
			$sql = "SELECT COUNT(`id`) AS `total` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `parent`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$parent));
			return ($stmt->fetch() && true);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function hasNetworks($parent) {
		try {
			$sql = "SELECT COUNT(`id`) AS `total` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `parent`=? ".
				"AND `bits`<128";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$parent));
			if ($result = $stmt->fetch(PDO::FETCH_ASSOC))
				return ($result['total']>0);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
		}
		return false;
	}


	public function getParent($address, $bits=128) {
		try {
			$sql = "SELECT `id`, `address`, `bits`, `parent`, `description` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE STRCMP(?, `address`)>=0 ".
				"ORDER BY `address` DESC, `bits` ASC";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address));
			$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (count($entries)>0)
				foreach ($entries as $entry)
					if (strcmp(broadcast($address, $bits), broadcast($entry['address'], $entry['bits']))<=0)
						return $entry['id'];
			return 0;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function search($search) {
		try {
			$sql = "SELECT DISTINCT `id`, `address`, `bits`, `parent`, `description` ".
				"FROM `".$this->prefix."ip` ".
				"LEFT JOIN `".$this->prefix."extrafields` ".
				"ON `".$this->prefix."extrafields`.`node`=`".$this->prefix."ip`.`id` ".
				"WHERE `address`=? ".
				"OR `description` LIKE CONCAT('%', ?, '%') ".
				"OR `".$this->prefix."extrafields`.`value` LIKE CONCAT('%', ?, '%') ".
				"ORDER BY `address`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($search, $search, $search));
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}

	public function getNext($address) {
		try {
			$sql = "SELECT `id`, `address`, `bits`, `parent`, `description` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE STRCMP(? , `address`)<0 ".
				"ORDER BY `address` ASC";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address));
			return ($node = $stmt->fetch(PDO::FETCH_ASSOC) ? $node: null);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
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
		try {
			$sql = "SELECT `id` FROM `".$this->prefix."ip` ".
				"WHERE `address`=:address AND `bits`=:bits";
			$stmt = $this->db->prepare($sql);
			$stmt->bindValue('address', $address, PDO::PARAM_STR);
			$stmt->bindValue('bits', (int)$bits, PDO::PARAM_INT);
			$stmt->execute(array($address, (int)$bits));
			if ($stmt->fetch()) {
				$this->error = 'Node '.showip($address, $bits).' already exists';
				return false;
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

		/* Check if network address matches bitmask */
		if (strcmp($address, network($address, $bits))!=0) {
			$this->error = 'Address '.ip2address($address).' is not on a boundary with '.(strcmp($address, '00000000000000000000000100000000')>0 ? $bits : $bits-96).' bits';
			return false;
		}

		/* Check possible parent */
		$parent = 0;
		try {
			$sql = "SELECT `id`, `address`, `bits` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `address`<=? ".
				"ORDER BY `address` DESC, `bits` DESC";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address));
			$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (count($parents)>0)
				foreach ($parents as $parentnode)
					if (strcmp(broadcast($address, $bits), broadcast($parentnode['address'], $parentnode['bits']))<=0) {
						$parent = $parentnode['id'];
						break;
					}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

		/* Check for access */
		if (!$this->isAdmin($session->username)) {
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
		try {
			$sql = "SELECT MAX(`id`) AS `max` FROM `".$this->prefix."ip`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute();
			$max = $stmt->fetch(PDO::FETCH_ASSOC);
			$sql = "INSERT INTO `".$this->prefix."ip` (`id`, `address`, `bits`, `parent`, `description`) ".
				"VALUES(?, ?, ?, ?, ?)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)($max['max']+1), $address, (int)$bits, $parent, $description));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

		/* Update possible children */
		if (count($children)>0) {
			$ids = array();
			foreach ($children as $child)
				$ids[] = $child['id'];
			try {
				$sql = "UPDATE `".$this->prefix."ip` ".
					"SET `parent`=? ".
					"WHERE `id` IN (".implode(',', $ids).")";
				$stmt = $this->db->prepare($sql);
				$stmt->execute($max['max']+1);
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		}
		$node = $max['max']+1;
		$this->log('Added node '.showip($address, $bits).
				   (empty($description) ? '' : ' ('.$description.')'));
		return $node;
	}


	public function deleteNode($node, $childaction = 'none') {
		global $session;

		/* Check for access */
		if (!$this->isAdmin($session->username)) {
			$access = $this->getAccess($node, $session->username);
			if ($access['access']!='w') {
				$this->error = 'Access denied';
				return false;
			}
		}

		$address = $this->getAddress($node);
		if ($this->error)
			return false;
		try {
			$sql = "SELECT `id` FROM `".$this->prefix."ip` ".
				"WHERE `parent`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$node));
			$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (count($children)>0) {
			if ($childaction=='delete') {
				foreach ($children as $child)
					if (!($this->deleteNode($child['id'], $childaction)))
						return false;
				try {
					$sql = "DELETE FROM `".$this->prefix."ip` WHERE `id`=?";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array((int)$node));
				} catch (PDOException $e) {
					$this->error = $e->getMessage();
					error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
					return false;
				}
			} else if ($childaction=='move') {
				try {
					$sql = "UPDATE `".$this->prefix."ip` ".
						"SET `parent`=? ".
						"WHERE `parent`=";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array((int)$address['parent'], (int)$node));
					$sql = "DELETE FROM `".$this->prefix."ip` ".
						"WHERE `id`=?";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array((int)$node));
				} catch (PDOException $e) {
					$this->error = $e->getMessage();
					error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
					return false;
				}
			} else {
				$this->error = 'Node has children';
				return false;
			}
		} else {
			try {
				$sql = "DELETE FROM `".$this->prefix."ip` ".
					"WHERE `id`=?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array((int)$node));
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		}
		$this->log('Deleted node '.showip($address['address'], $address['bits']));
		return true;
	}


	public function getField($field, $node) {
		if (empty($node))
			return false;
		try {
			$sql = "SELECT `value` ".
				"FROM `".$this->prefix."extrafields` ".
				"WHERE `node`=? ".
				"AND `field`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$node, $field));
			if ($value = $stmt->fetch(PDO::FETCH_ASSOC))
				return $value['value'];
			return '';
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function changeNode($node, $address, $bits, $description) {
		global $config, $session;
		if (!($entry = $this->getAddress($node))) {
			$this->error = 'Node not found';
			return false;
		}

		/* Check for access */
		if (!$this->isAdmin($session->username)) {
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
		try {
			$sql = "SELECT `id` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `address`=? AND `bits`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address, (int)$bits));
			if (($check = $stmt->fetch(PDO::FETCH_ASSOC)) &&
				($check['id']!=$node)) {
				$this->error = 'Node '.showip($address, $bits).' already exists';
				return false;
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

		/* Check if network address matches bitmask */
		if (strcmp($address, network($address, $bits))!=0) {
			$this->error = 'Address '.ip2address($address).' is not on a boundary with '.(strcmp($address, '00000000000000000000000100000000')>0 ? $bits : $bits-96).' bits';
			return false;
		}

		/* Find change in address */
		$change = _xor($entry['address'], $address);

		try {
			/* Start transaction */
			$this->db->beginTransaction();

			/* Change node */
			$sql = "UPDATE `".$this->prefix."ip` ".
				"SET `address`=?, `bits`=?, `description`=? ".
				"WHERE `id`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute($address, (int)$bits, $description);

			/* Find new parent */
			$parent = $entry['parent'];
			$sql = "SELECT `id`, `address`, `bits` ".
				"FROM `".$this->prefix."ip` ".
				"WHERE `address`<=? AND id!=? ".
				"ORDER BY `address` DESC, `bits` DESC";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($address, (int)$node));
			$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (count($parents)>0)
				foreach ($parents as $parentnode)
					if (strcmp(broadcast($address, $bits), broadcast($parentnode['address'], $parentnode['bits']))<=0) {
						$parent = $parentnode['id'];
						break;
					}
			if ($parent!=$entry['parent']) {
				$sql = "UPDATE `".$this->prefix."ip` ".
					"SET `parent`=? ".
					"WHERE `id`=?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array((int)$parent, (int)$node));
			}

			/* Check if old children still fit */
			$children = $this->getTree($entry['id']);
			if (count($children)>0)
				foreach ($children as $child)
					if (($child['id']!=$node) &&
						((strcmp($address, $child['address'])>0) ||
						 (strcmp(broadcast($address, $bits), broadcast($child['address'], $child['bits']))<0))) {
						$sql = "UPDATE `".$this->prefix."ip` ".
							"SET `parent`=?, `address`=? ".
							"WHERE `id`=?";
						$stmt = $this->db->prepare($sql);
						$stmt->execute(array((int)$entry['parent'], _xor($child['address'], $change), (int)$child['id']));
					}

			/* Check for new children */
			$children = $this->getTree($parent);
			if (count($children)>0) 
				foreach ($children as $child)
					if (($child['id']!=$node) &&
						(strcmp($address, $child['address'])<=0) &&
						(strcmp(broadcast($address, $bits), broadcast($child['address'], $child['bits']))>=0)) {
						$sql = "UPDATE `".$this->prefix."ip` ".
							"SET `parent`=? ".
							"WHERE `id`=?";
						$stmt = $this->db->prepare($sql);
						$stmt->execute(array((int)$entry['id'], (int)$child['id']));
					}

			$this->db->commit();

		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			$this->db->rollBack();
			return false;
		}

		if (count($changes)>0)
			$this->log('Changed node '.showip($address, $bits).' (was: '.implode(', ', $changes).')');
		return true;
	}


	public function setField($field, $node, $value, $recursive = false) {
		$address = $this->getAddress($node);
		$old = $this->getField($field, $node);
		if ($value!=$old) 
			try {
				$sql = "REPLACE INTO `".$this->prefix."extrafields` (`node`, `field`, `value`) ".
					"VALUES(?, ?, ?)";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array((int)$node, $field, $value));
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		$this->log('Set field \''.$field.'\' for node '.
				   showip($address['address'], $address['bits']).' to '.
				   $value);
		if ($recursive) {
			try {
				$sql = "SELECT `id` FROM `".$this->prefix."ip` ".
					"WHERE `parent`=?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array((int)$node));
				$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
			if (count($children)>0)
				foreach ($children as $child)
					if (!$this->setField($field, $child['id'], $value, $recursive))
						return false;
		}
		return true;
	}


	public function getExtra($table, $item = null) {
		global $config;
		if ($item===null)
			try {
				$sql = "SELECT * FROM `".$this->prefix."extratables` ".
					"WHERE `table`=? ";
				if ($config->extratables[$table]['type']=='integer')
					$sql .= "ORDER BY CAST(`item` AS SIGNED)";
				else
					$sql .= "ORDER BY `".$this->prefix."extratables`.`item`";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($table));
				return $stmt->fetchAll(PDO::FETCH_ASSOC);
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		try {
			$sql = "SELECT * FROM `".$this->prefix."extratables` ".
				"WHERE `table`=? AND `item`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($table, $item));
			if (!($extra = $stmt->fetch(PDO::FETCH_ASSOC)))
				return false;
			$sql = "SELECT * FROM `".$this->prefix."tablecolumn` ".
				"WHERE `table`=? AND `item`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($table, $item));
			$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (count($columns)>0)
				foreach ($columns as $data)
					$extra[$data['column']] = $data['value'];
			return $extra;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function findExtra($table, $search = null) {
		global $config;
		if (empty($search))
			return $this->getExtra($table);
		try {
			$sql = "SELECT DISTINCT `".$this->prefix."extratables`.`item` ".
				"FROM `".$this->prefix."extratables` ".
				"LEFT JOIN `".$this->prefix."tablecolumn` ".
				"ON `".$this->prefix."extratables`.`table`=`".$this->prefix."tablecolumn`.`table` ".
				"AND `".$this->prefix."extratables`.`item`=`".$this->prefix."tablecolumn`.`item` ".
				"WHERE `".$this->prefix."extratables`.`table`=? ".
				"AND (`".$this->prefix."extratables`.`item` LIKE CONCAT('%', ?, '%') ".
				"OR `".$this->prefix."extratables`.`description` LIKE CONCAT('%', ?, '%') ".
				"OR `".$this->prefix."tablecolumn`.`value` LIKE CONCAT('%', ?, '%') ".
				"ORDER BY ";
			if ($config->extratables[$table]['type']=='integer')
				$sql .= "CAST(`".$this->prefix."tablecolumn`.`item` AS SIGNED)";
			else
				$sql .= "`".$this->prefix."tablecolumn`.`item`";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($table, $search, $search, $search));
			$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (count($items)>0) {
			$allitems = array();
			foreach ($items as $item)
				$allitems[] = $this->getExtra($table, $item['item']);
			return $allitems;
		}
		return false;
	}


	public function addExtra($table, $item, $description, $comments, $columndata = null) {
		global $config;
		if (!isset($config->extratables[$table])) {
			$this->error = 'Unknown table '.$table;
			return false;
		}
		try {
			$sql = "INSERT INTO `".$this->prefix."extratables` (`table`, `item`, `description`, `comments`) ".
				"VALUES(? ? ? ?)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($table, $item, $description, $comments));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (is_array($columndata) && (count($columndata)>0))
			foreach ($columndata as $column=>$data)
				try {
					$sql = "INSERT INTO `".$this->prefix."tablecolumn` (`table`, `item`, `column`, `value`) ".
						"VALUES(?, ?, ?, ?)";
					$stmt = $this->db->prepare($sql);
					$stmt->execute(array($table, $item, $column, $data));
				} catch (PDOException $e) {
					$this->error = $e->getMessage();
					error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
					return false;
				}
		return $this->log('Added \''.$table.'\' item '.$item.
						  (empty($description) ? '' : ' ('.$description.')'));
	}


	public function changeExtra($table, $olditem, $item, $description, $comments, $columndata) {
		global $config;
		try {
			$sql = "UPDATE `".$this->prefix."extratables` ".
				"SET `item`=?, `description`=?, `comments`=? ".
				"WHERE `item`=? AND `table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($item, $description, $comments, $olditem, $table));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		$entry = $this->getExtra($table, $olditem);
		$changes = array();
		if ($item!=$olditem)
			$changes[] = $olditem;
		if ($description!=$entry['description'])
			$changes[] = $entry['description'];
		if ($this->error)
			return false;
		try {
			$sql = "UPDATE `".$this->prefix."tablenode` ".
				"SET `item`=? ".
				"WHERE `item`=? AND `table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($item, $olditem, $table));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (is_array($columndata) && (count($columndata)>0))
			foreach ($columndata as $column=>$data)
				if ($data!=$entry[$column]) {
					try {
						$sql = "REPLACE INTO `".$this->prefix."tablecolumn` (`table`, `item`, `column`, `value`) ".
							"VALUES(?, ?, ?, ?)";
						$stmt = $this->db->prepare($sql);
						$stmt->execute(array($table, $item, $column, $data));
					} catch (PDOException $e) {
						$this->error = $e->getMessage();
						error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
						return false;
					}
					if ($config->extratables[$table]['columns'][$column]=='password')
						$changes[] = 'old password';
					else
						$changes[] = $column.'='.$entry[$column];
				}
		if (count($changes)>0)
			$this->log('Changed \''.$table.'\' item '.$item.' (was: '.implode(', ', $changes).')');
		return true;
	}


	public function deleteExtra($table, $item) {
		try {
			$sql = "DELETE FROM `".$this->prefix."extratables` ".
				"WHERE `item`=? AND `table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($item, $table));
			$sql = "DELETE FROM `".$this->prefix."tablecolumn` ".
				"WHERE `item`=? AND `table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($item, $table));
			$sql = "DELETE FROM `".$this->prefix."tablenode` ".
				"WHERE `item`=? AND `table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($item, $table));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return $this->log('Deleted \''.$table.'\' item '.$item);
	}


	public function getItem($table, $node) {
		if (empty($node))
			return false;
		try {
			$sql = "SELECT `".$this->prefix."tablenode`.`item` AS `item`, ".
				"`".$this->prefix."extratables`.`description` AS `description` ".
				"FROM `".$this->prefix."tablenode` ".
				"LEFT JOIN `".$this->prefix."extratables` ".
				"ON `".$this->prefix."tablenode`.`item`=`".$this->prefix."extratables`.`item` ".
				"AND `".$this->prefix."tablenode`.`table`=`".$this->prefix."extratables`.`table` ".
				"WHERE `node`=? AND `".$this->prefix."tablenode`.`table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$node, $table));
			if ($item = $stmt->fetch(PDO::FETCH_ASSOC))
				return $item;
			return array('item'=>'-', 'description'=>'');
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function getItemNodes($table, $item) {
		try {
			$sql = "SELECT `".$this->prefix."tablenode`.`node` ".
				"FROM `".$this->prefix."tablenode` ".
				"WHERE `item`=? AND `".$this->prefix."tablenode`.`table`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($item, $table));
			$nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (count($nodes)>0) {
			foreach ($nodes as $key=>$node)
				if ($details = $this->getAddress($node['node']))
					$nodes[$key] = $details;
				else
					unset($nodes[$key]);
			return $nodes;
		} else
			return array();
	}


	public function setItem($table, $node, $item, $recursive = false) {
		$olditem = preg_replace('/^-$/', '', $this->getItem($table, $node));
		$item = preg_replace('/^-$/', '', $item);
		if ($olditem['item']!=$item)
			try {
				$sql = "DELETE FROM `".$this->prefix."tablenode` WHERE ".
					"`table`=? AND `node`=?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($table, (int)$node));
				$sql = "INSERT INTO `".$this->prefix."tablenode` (`table`, `item`, `node`) ".
					"VALUES(?, ?, ?)";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($table, $item, (int)$node));
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		if ($recursive)
			try {
				$sql = "SELECT `id` FROM `".$this->prefix."ip` ".
					"WHERE `parent`=?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array((int)$node));
				$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if (count($children)>0)
					foreach ($children as $child)
						if (!$this->setItem($table, $child['id'], $item, $recursive ))
							return false;
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		$address = $this->getAddress($node);
		$this->log('Set \''.$table.'\' for '.showip($address['address'], $address['bits']).' to '.$item);
		return true;
	}


	public function changeUsername($username, $oldusername) {
		if ($username==$oldusername)
			return true;
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `username`=? ".
				"WHERE `username`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($username, $oldusername));
			$this->log('Changed username '.$oldusername.' to '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function changeName($name, $username = null) {
		global $session;
		if (!$username)
			$username = $session->username;
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `name`=? ".
				"WHERE `username`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($name, $username));
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		$session->changeName($name);
		$this->log('Changed name for '.$username.' to '.$name);
		return true;
	}


	public function changePassword($password, $username = null) {
		global $session;
		if (!$username)
			$username = $session->username;
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `password`=? ".
				"WHERE `username`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array(md5($password), $username));
			$this->log('Changed password for '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function changeAdmin($admin, $username) {
		global $session;
		if (($username==$session->username) ||
			!$this->isAdmin($session->username)) {
			$this->error = 'Access denied';
			return false;
		}
		$user = $this->getUser($username);
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `admin`=? ".
				"WHERE `username`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)($admin ? 0 : 1), $username));
			$this->log('Changed admin setting for '.$username.' to '.
					   ($user['admin'] ? 'false' : 'true'));
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function addUser($username, $name, $password) {
		try {
			$sql = "INSERT INTO `".$this->prefix."users` (`username`, `name`, `password`, `admin`) ".
				"VALUES(?, ?, ?, ?)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($username, $name, md5($password), 0));
			$this->log('Added user '.$username. ' ('.$name.')');
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function deleteUser($username) {
		try {
			$sql = "DELETE FROM `".$this->prefix."users` ".
				"WHERE `username`=?";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array($username));
			$this->log('Deleted user '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function getLog($search) {
		try {
			$sql = "SELECT * FROM `".$this->prefix."log`";
			if ($search && (trim($search)!=''))
				$sql .= " WHERE `username` LIKE CONCAT('%', ?, '%') ".
					"OR `action` LIKE CONCAT('%', ?, '%') ";
			$sql .= "ORDER BY `stamp` DESC";
			$stmt = $this->db->prepare($sql);
			if ($search && (trim($search)!=''))
				$stmt->execute(array($search, $search));
			else
				$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function changeAccess($node, $username, $access) {
		$address = $this->getAddress($node);
		$oldaccess = $this->getAccess($node, $username);
		if ($oldaccess['access']==$access)
			return true;
		$this->log('Set '.($access=='w' ? 'write' : 'read-only').' access to '.
				   showip($address['address'], $address['bits']).' for '.$username);
		if ($oldaccess['id']==$node) {
			try {
				$sql = "DELETE FROM `".$this->prefix."access` ".
					"WHERE `username`=? AND `node`=?";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(array($username, (int)$node));
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
			$oldaccess = $this->getAccess($node, $username);
			/* If parent has same access, try to clean up old rows */
			if ($oldaccess['access']==$access)
				return $this->changeAccess($oldaccess['id'], $username, $access);
		}
		try {
			$sql = "INSERT INTO `".$this->prefix."access` (`node`, `username`, `access`) ".
				"VALUES(?, ?, ?)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array((int)$node, $username, $access));
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	public function addAccess($node, $username, $access) {
		$address = $this->getAddress($node);
		try {
			$sql = "INSERT INTO `".$this->prefix."access` (`node`, `username`, `access`) ".
				"VALUES(?, ?, ?)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute((int)$node, $username, $access=='w' ? 'w' : 'r');
			$this->log('Added '.($access=='w' ? 'write' : 'read-only').' access to '.
					   showip($address['address'], $address['bits']).' for '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
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
								if (($bits==128) && ($free['bits']<128) &&
									(preg_match('/00$/', $free['address'])))
									return array('address'=>plus($free['address'], 1), 'bits'=>$bits);
								else if (($bits==128) && !preg_match('/(00|ff)$/', $free['address']))
									return array('address'=>$free['address'], 'bits'=>$bits);
								else if ($bits!=128)
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
