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


/*
 * Calculating network and broadcast addresses in SQL:
 *
 * SET @address='000000000000000000000000c0a80300';
 * SET @bits=120;
 * -- network address
 * ...
 * -- broadcast address
 * SELECT LPAD(LOWER(CONV(CONV(REPEAT('1', 128-@bits), 2, 10) | CONV(@address, 16,10), 10, 16)), 32, '0') AS broadcast;
 *
 * Unfortunately MySQL won't handle this with numbers >64 bits, so moving
 * calculations to SQL is still under investigation.
 */


class Database {


	private $db = null;
	public $error = null;
	private $provider = null;
	private $dbversion = '8';
	private $prefix = '';

	/*
	 * Constructor. Set some sane database defaults.
	 */
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


	/*
	 * Log an action to the database.
	 */
	public function log($action) {
		global $session;
		$sql = "INSERT INTO `".$this->prefix."log` (`stamp`, `username`, `action`) ".
			"VALUES(:stamp, :username, :action)";
		try {
			$timestamp = date('c');
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':stamp', $timestamp, PDO::PARAM_STR);
			$stmt->bindParam(':username', $session->username, PDO::PARAM_STR);
			$stmt->bindParam(':action', $action, PDO::PARAM_STR);
			return $stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Do we have a database?
	 */
	public function hasDatabase() {
		$this->error = null;
		$sql = "SELECT `version` FROM `".$this->prefix."version`";
		try {
			$stmt = $this->db->prepare($sql);
		} catch (PDOException $e) {
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		try {
			$stmt->execute();
			if ($stmt->fetch())
				return true;
			return false;
		} catch (PDOException $e) {
			return false;
		}
	}


	/*
	 * Is a database upgrade available?
	 */
	public function hasUpgrade() {
		return ($this->getVersion()<$this->dbversion);
	}


	/*
	 * Check database version.
	 */
	public function getVersion() {
		$this->error = null;
		$sql = "SELECT `version` FROM `".$this->prefix."version`";
		try {
			$stmt = $this->db->prepare($sql);
			$stmt->execute();
			if ($row = $stmt->fetch(PDO::FETCH_ASSOC))
				return (int)$row['version'];
			$this->error = 'Version unknown';
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
		}
		return false;
	}


	/*
	 * Initialize example database.
	 */
	public function initializeDb() {
		$this->error = null;
		try {
			if (in_array($this->db->getAttribute(PDO::ATTR_DRIVER_NAME), array('mysql', 'sqlite')))
				/* Drop old tables, even though we're pretty sure they don't exist. */
				foreach (array('ip', 'version', 'fields', 'fieldvalues', 'tables', 'tablecolumns',
							   'tableitems', 'tablenode', 'tablecolumn', 'log', 'access') as $table)
					$this->db->exec("DROP TABLE IF EXISTS `".$this->prefix.$table."`");

			/* ip */
			$this->db->exec("CREATE TABLE `".$this->prefix."ip` (".
								"`address` varchar(32) NOT NULL,".
								"`bits` INT UNSIGNED NOT NULL,".
								"`description` varchar(255),".
								"PRIMARY KEY (`address`, `bits`)".
							")");
			$this->db->exec("CREATE UNIQUE INDEX `addressbits` ON `".$this->prefix."ip` (`address`, `bits`)");
			$this->db->exec("INSERT INTO `".$this->prefix."ip` (`address`, `bits`, `description`) ".
							"VALUES('fc030000000000000000000000000000', 16, 'Default IPv6 network.')");
			$this->db->exec("INSERT INTO `".$this->prefix."ip` (`address`, `bits`, `description`) ".
							"VALUES('000000000000000000000000C0A80300', 120, 'Default IPv4 network.')");

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

			/* fields */
			$this->db->exec("CREATE TABLE `".$this->prefix."fields` (".
								"`field` varchar(15) NOT NULL,".
								"`type` varchar(10) NOT NULL DEFAULT 'text',".
								"`description` varchar(80) NOT NULL,".
								"`url` varchar(255) NOT NULL,".
								"`inoverview` BOOLEAN NOT NULL DEFAULT TRUE,".
								"PRIMARY KEY(`field`)".
							")");

			/* fieldvalues */
			$this->db->exec("CREATE TABLE `".$this->prefix."fieldvalues` (".
								"`address` varchar(32) NOT NULL,".
								"`bits` INT UNSIGNED NOT NULL,".
								"`field` varchar(15) NOT NULL,".
								"`value` varchar(255) NOT NULL,".
								"PRIMARY KEY(`address`, `bits`, `field`)".
							")");

			/* tables */
			$this->db->exec("CREATE TABLE `".$this->prefix."tables` (".
								"`table` varchar(15) NOT NULL,".
								"`description` varchar(80) NOT NULL,".
								"`linkaddress` BOOLEAN NOT NULL DEFAULT TRUE,".
								"PRIMARY KEY(`table`)".
							")");

			/* tablecolumns */
			$this->db->exec("CREATE TABLE `".$this->prefix."tablecolumns` (".
								"`table` varchar(15) NOT NULL,".
								"`column` varchar(15) NOT NULL,".
								"`type` varchar(10) NOT NULL DEFAULT 'text',".
								"PRIMARY KEY(`table`, `column`)".
							")");

			/* tableitems */
			$this->db->exec("CREATE TABLE `".$this->prefix."tableitems` (".
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
								"`address` varchar(32) NOT NULL,".
								"`bits` INT UNSIGNED NOT NULL,".
								"PRIMARY KEY(`table`, `item`, `address`, `bits`)".
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
								"`address` varchar(32) NOT NULL,".
								"`bits` INT UNSIGNED NOT NULL,".
								"`username` varchar(15) NOT NULL,".
								"`access` VARCHAR(1),".
								"PRIMARY KEY(`address`, `bits`, `username`)".
							")");
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return $this->log('Initialized database');
	}


	/*
	 * Upgrade the database structure.
	 */
	public function upgradeDb($config) {
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
			if ($version<7) {
				$this->db->exec("UPDATE `".$this->prefix."ip` ".
								 "SET `address`=LOWER(`address`)");
				foreach (array('access', 'extrafields', 'tablenode') as $table) {
					$this->db->exec("ALTER TABLE `".$this->prefix.$table."` ".
									"DROP PRIMARY KEY");
					$this->db->exec("ALTER TABLE `".$this->prefix.$table."` ".
									"ADD COLUMN `address` varchar(32) NOT NULL DEFAULT '' AFTER `node`");
					$this->db->exec("ALTER TABLE `".$this->prefix.$table."` ".
									"ADD COLUMN `bits` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `address`");
					$this->db->exec("UPDATE `".$this->prefix.$table."` ".
									"LEFT JOIN `".$this->prefix."ip` ".
									"ON `".$this->prefix.$table."`.`node`=`".$this->prefix."ip`.`id` ".
									"SET `".$this->prefix.$table."`.`address`=`".$this->prefix."ip`.`address`, ".
										"`".$this->prefix.$table."`.`bits`=`".$this->prefix."ip`.`bits`");
					$this->db->exec("ALTER TABLE `".$this->prefix.$table."` ".
									"DROP COLUMN `node`");
				}
				$this->db->exec("ALTER TABLE `".$this->prefix."access` ".
								"ADD PRIMARY KEY(`address`, `bits`, `username`)");
				$this->db->exec("ALTER TABLE `".$this->prefix."extrafields` ".
								"ADD PRIMARY KEY(`address`, `bits`, `field`)");
				$this->db->exec("ALTER TABLE `".$this->prefix."tablenode` ".
								"ADD PRIMARY KEY(`table`, `item`, `address`, `bits`)");
				$this->db->exec("ALTER TABLE `".$this->prefix."ip` ".
								"DROP PRIMARY KEY");
				$this->db->exec("ALTER TABLE `".$this->prefix."ip` ".
								"DROP COLUMN `id`");
				$this->db->exec("ALTER TABLE `".$this->prefix."ip` ".
								"DROP COLUMN `parent`");
				$this->db->exec("ALTER TABLE `".$this->prefix."ip` ".
								"DROP INDEX `addressbits`");
				$this->db->exec("ALTER TABLE `".$this->prefix."ip` ".
								"ADD PRIMARY KEY(`address`, `bits`)");
			}
			if ($version<8) {
				$this->db->exec("ALTER TABLE `".$this->prefix."extrafields` RENAME TO `".$this->prefix."fieldvalues`");
				$this->db->exec("ALTER TABLE `".$this->prefix."extratables` RENAME TO `".$this->prefix."tableitems`");
				$this->db->exec("CREATE TABLE `".$this->prefix."fields` (".
									"`field` varchar(15) NOT NULL,".
									"`type` varchar(10) NOT NULL DEFAULT 'text',".
									"`description` varchar(80) NOT NULL,".
									"`url` varchar(255) NOT NULL,".
									"`inoverview` BOOLEAN NOT NULL DEFAULT TRUE,".
									"PRIMARY KEY(`field`)".
								")");
				$this->db->exec("CREATE TABLE `".$this->prefix."tables` (".
									"`table` varchar(15) NOT NULL,".
									"`description` varchar(80) NOT NULL,".
									"`linkaddress` BOOLEAN NOT NULL DEFAULT TRUE,".
									"`inoverview` BOOLEAN NOT NULL DEFAULT TRUE,".
									"PRIMARY KEY(`table`)".
								")");
				$this->db->exec("CREATE TABLE `".$this->prefix."tablecolumns` (".
									"`table` varchar(15) NOT NULL,".
									"`column` varchar(15) NOT NULL,".
									"`type` varchar(10) NOT NULL DEFAULT 'text',".
									"PRIMARY KEY(`table`, `column`)".
								")");
				if (property_exists($config, 'extrafields'))
					foreach ($config->extrafields as $field=>$details)
						if (!$this->addField($field,
											 $details['type'],
											 isset($details['description']) ? $details['description'] : '',
											 isset($details['url']) ? $details['url'] : '',
											 isset($details['inoverview']) ? $details['inoverview'] : true))
							return false;
				if (property_exists($config, 'extratables'))
					foreach ($config->extratables as $table=>$details)
						if (!$this->addTable($table,
											 $details['type'],
											 isset($details['description']) ? $details['description'] : '',
											 isset($details['inoverview']) ? $details['inoverview'] : true,
											 isset($details['linkaddress']) ? $details['linkaddress'] : true,
											 isset($details['columns']) ? $details['columns'] : array()))
							return false;
			}
			$sql = "UPDATE `".$this->prefix."version` SET version=:version";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':version', $this->dbversion, PDO::PARAM_INT);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return $this->log('Upgraded database version '.$version.' to '.$this->dbversion);
	}


	/*
	 * Fetch user details.
	 */
	public function getUser($username) {
		try {
			if ($this->getVersion()<5) {
				$sql = "SELECT `username`, `password`, `name`, IF ('admin'=:username, 1, 0) AS `admin` ".
					"FROM `".$this->prefix."admin` ".
					"WHERE `username` = :username";
				$stmt = $this->db->prepare($sql);
				$stmt->bindParam(':username', $username, PDO::PARAM_STR);
				$stmt->execute();
			} else {
				$sql = "SELECT `username`, `password`, `name`, `admin` ".
					"FROM `".$this->prefix."users` ".
					"WHERE `username` = :username";
				$stmt = $this->db->prepare($sql);
				$stmt->bindParam(':username', $username, PDO::PARAM_STR);
				$stmt->execute();
			}
			if (!($user = $stmt->fetch(PDO::FETCH_ASSOC)))
				return false;
			$sql = "SELECT `address`, `bits`, `access` ".
				"FROM `".$this->prefix."access` ".($this->getVersion()<7 ?
				"LEFT JOIN `".$this->prefix."ip` ".
					"ON `node`=`id` " : "").
				"WHERE `username` = :username ".
				"ORDER BY `address`, `bits`";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->execute();
			$user['access'] = array();
			while ($access = $stmt->fetch(PDO::FETCH_ASSOC))
				$user['access'][] = array('node'=>self::_address2node($access['address'], $access['bits']),
										  'access'=>$access['access']);
			return $user;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Is this user an administrator?
	 */
	public function isAdmin($username) {
		$user = $this->getUser($username);
		return ($user['admin'] ? true : false);
	}


	/*
	 * Get a node's per user access settings.
	 */
	public function getAccess($node, $username = null) {
		$block = self::_node2address($node);
		try {
			if ($username) {
				$sql = "SELECT `username`, `address`, `bits`, `access` ".
					"FROM `".$this->prefix."access` ".
					"WHERE `username`=:username ".
						"AND `address`<=:address ".
						"AND `bits`<=:bits ".
						"ORDER BY `address` DESC, `bits` DESC";
				$stmt = $this->db->prepare($sql);
				$stmt->bindParam(':username', $username, PDO::PARAM_STR);
				$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
				$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
				$stmt->execute();
				$broadcast = self::_broadcast($block['address'], $block['bits']);
				while ($access = $stmt->fetch(PDO::FETCH_ASSOC))
					if (strcmp($broadcast, self::_broadcast($access['address'], $access['bits']))<=0)
						return array('node'=>$access['address'].'/'.$access['bits'],
									 'access'=>$access['access']);
				return array('node'=>'::/0',
							 'access'=>'r');
			}
			$useraccess = array();
			foreach ($this->getUsers() as $user)
				$useraccess[$user['username']] = $this->getAccess($node, $user['username']);
			return $useraccess;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Fetch user list.
	 */
	public function getUsers() {
		try {
			$sql = "SELECT `username`, `name`, `admin` ".
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


	/*
	 * Convert an IPv4 or IPv6 address, optionally in CIDR notation,
	 * to the internal representation.
	 */
	private static function _node2address($node) {
		if (false==($address = @inet_pton(preg_replace('/\/.*/', '', $node))))
			throw new Exception(sprintf(_('%s is not a valid IP address'), $node));
		$address = str_pad(unpack('H*', $address)[1], 32, '0', STR_PAD_LEFT);
		$bits = preg_replace('/.*\/([0-9]+)$/', '\1', $node);
		if ($bits=='')
			$bits = 128;
		return array('address'=>strtolower($address),
					 'bits'=>(false===strpos($node, ':') ? $bits+96 : $bits));
	}


	/*
	 * Convert the internal representation of an addres to a human-readable
	 * IPv4 or IPv6 address, optionally in CIDR notation.
	 */
	private static function _address2node($address, $bits = null) {
		if (!preg_match('/^[0-9a-f]{32}$/i', $address))
			throw new Exception(sprintf(_('%s is not a valid internal address representation'), $address));
		if ($bits && !is_numeric($bits))
			throw new Exception(sprintf(_('%s is not a valid number of bits'), $bits));
		return strtolower(inet_ntop(pack('H*', preg_replace('/^[0]{24}/', '', $address)))).
			($bits ? '/'.(strpos($address, '000000000000000000000000')==0 ? $bits-96 : $bits) : '');
	}


	/*
	 * Fetch node from the database.
	 */
	public function getNode($node) {
		$block = self::_node2address($node);
		$sql = "SELECT `address`, `bits`, `description` ".
			"FROM `".$this->prefix."ip` ".
			"WHERE `address`=:address AND `bits`=:bits";
		$stmt = $this->db->prepare($sql);
		$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
		$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
		$stmt->execute();
		if ($result = $stmt->fetch(PDO::FETCH_ASSOC))
			return array('node'=>self::_address2node($result['address'], $result['bits']),
						 'description'=>$result['description']);
		return false;	
	}


	/*
	 * Fetch node children from the database.
	 */
	public function getChildren($node, $hosts = true, $unused = false) {
		$block = self::_node2address($node);
		/* Get all children and grandchildren. Filter out
		 * grandchildren, until we find a way to do that
		 * using sql. */
		$sql = "SELECT `address`, `bits`, `description` ".
			"FROM `".$this->prefix."ip` ".
			"WHERE `address`>=:address AND `address`<=:broadcast AND `bits`>:bits ".
			"ORDER BY `address`, `bits`";
		$stmt = $this->db->prepare($sql);
		$broadcast = self::_broadcast($block['address'], $block['bits']);
		$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
		$stmt->bindParam(':broadcast', $broadcast, PDO::PARAM_STR);
		$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
		$stmt->execute();
		$children = array();
		$lastbroadcast = null;
		while ($result = $stmt->fetch(PDO::FETCH_ASSOC))
			if (!$lastbroadcast ||
				(strcmp($result['address'], $lastbroadcast)>0)) {
				$lastbroadcast = self::_broadcast($result['address'], $result['bits']);
				if ($hosts ||
					($result['bits']<128))
					$children[] = array('node'=>self::_address2node($result['address'], $result['bits']),
										'description'=>$result['description']);
			}
		return $unused ? self::findUnused($node, $children) : $children;
	}


	/*
	 * Find unused network blocks.
	 */
	private static function _splitblocks($address, $nextaddress, $startbits = 1) {
		$blocks = array();
		do {
			$bits = $startbits;
			do {
				$bits++;
				while (strcmp($address, self::_network($address, $bits))!=0)
					$bits++;
				$broadcast = self::_broadcast($address, $bits);
			} while (($bits<=128) && (strcmp($broadcast, $nextaddress)>=0));
			if ($bits<129)
				$blocks[] = array('node'=>self::_address2node($address, $bits),
								  'unused'=>true,
								  'description'=>'');
			$address = self::_add(self::_broadcast($address, $bits), 1);
		} while (strcmp($address, $nextaddress)<0);
		return $blocks;
	}
	public static function findUnused($node, $children) {
		$block = self::_node2address($node);
		$network = self::_network($block['address'], $block['bits']);
		$broadcast = self::_broadcast($block['address'], $block['bits']);
		$unused = array();

		while (strcmp($network, $broadcast)<=0)
			if (count($children)) {
				$child = array_shift($children);
				$childblock = self::_node2address($child['node']);
				$childnetwork = self::_network($childblock['address'], $childblock['bits']);
				$childbroadcast = self::_broadcast($childblock['address'], $childblock['bits']);
				if (strcmp($childnetwork, $network)==0) {
					/* First block within node */
					$unused[] = $child;
					$network = self::_add($childbroadcast, 1);
				} else if (strcmp($childbroadcast, $broadcast)==0) {
					/* Last block within node */
					$unused[] = $child;
					return $unused;
				} else {
					$unused = array_merge($unused, self::_splitblocks($network, $childnetwork, $block['bits']));
					$unused[] = $child;
					$network = self::_add($childbroadcast, 1);
				}
			} else {
				$unused = array_merge($unused, self::_splitblocks($network, self::_add($broadcast, 1), $block['bits']));
				$network = self::_add($broadcast, 1);
			}
		return $unused;
	}


	/*
	 * Calculate a node's netmask (IPv4 only).
	 */
	public static function getNetmask($node) {
		return inet_ntop(pack('N', 0xffffffff & (0xffffffff << (32-preg_replace('/.*\//', '', $node)))));
	}


	/*
	 * Calculate a node's network address.
	 */
	private static function _network($address, $bits) {
		$ones = str_pad('', $bits, '1');
		$binary = str_pad($ones, 128, '0', STR_PAD_RIGHT);
		$hex = gmp_strval(gmp_init($binary, 2), 16);
		$fullhex = str_pad($hex, 32, '0', STR_PAD_LEFT);
		return unpack('H*', pack('H*', $address) & pack('H*', $fullhex))[1];
	}
	public static function getNetwork($node) {
		$block = self::_node2address($node);
		$network = self::_network($block['address'], $block['bits']);
		return inet_ntop(pack('H*', preg_replace('/^000000000000000000000000/', '', $network)));
	}


	/*
	 * Calculate a node's broadcast address.
	 */
	private static function _broadcast($address, $bits) {
		$ones = str_pad('', 128-$bits, '1');
		$binary = str_pad($ones, 128, '0', STR_PAD_LEFT);
		$hex = gmp_strval(gmp_init($binary, 2), 16);
		$fullhex = str_pad($hex, 32, '0', STR_PAD_LEFT);
		return unpack('H*', pack('H*', $address) | pack('H*', $fullhex))[1];
	}
	public static function getBroadcast($node) {
		$block = self::_node2address($node);
		$broadcast = self::_broadcast($block['address'], $block['bits']);
		return inet_ntop(pack('H*', preg_replace('/^000000000000000000000000/', '', $broadcast)));
	}


	/*
	 * Perform addition on an address.
	 */
	private static function _add($address, $value) {
		$hex = gmp_strval(gmp_add(gmp_init($address, 16), gmp_init($value, 10)), 16);
		return str_pad($hex, 32, '0', STR_PAD_LEFT);
	}


	/*
	 * Perform subtraction from an address.
	 */
	private static function _subtract($address, $value) {
		$hex = gmp_strval(gmp_sub(gmp_init($address, 16), gmp_init($value, 10)), 16);
		return str_pad($hex, 32, '0', STR_PAD_LEFT);
	}


	/*
	 * Check if $node1 equals $node2.
	 */
	public static function isSame($node1, $node2) {
		$node1 = self::_node2address($node1);
		$node2 = self::_node2address($node2);
		return (($node1['address']==$node2['address']) &&
				($node1['bits']==$node2['bits']));
	}


	/*
	 * Check if $node is child of $parent.
	 */
	public static function isChild($node, $parent) {
		$node = self::_node2address($node);
		$parent = self::_node2address($parent);
		return (($node['bits']>$parent['bits']) &&
				(strcmp($node['address'], self::_network($parent['address'], $parent['bits']))>=0) &&
				(strcmp($node['address'], self::_broadcast($parent['address'], $parent['bits']))<=0));
	}


	/*
	 * Fetch node's parent.
	 */
	public function getParent($node) {
		$block = self::_node2address($node);
		$sql = "SELECT `address`, `bits`, `description` ".
			"FROM `".$this->prefix."ip` ".
			"WHERE address<=:address AND bits<=:bits AND ".
				"NOT (address=:address AND bits=:bits) ".
			"ORDER BY address DESC, bits DESC";
		$stmt = $this->db->prepare($sql);
		$stmt->bindValue('address', $block['address']);
		$stmt->bindValue('bits', $block['bits']);
		$stmt->execute();
		while ($result = $stmt->fetch(PDO::FETCH_ASSOC))
			if ((self::_network($result['address'], $result['bits'])<=self::_network($block['address'], $block['bits'])) &&
				(self::_broadcast($result['address'], $result['bits'])>=self::_broadcast($block['address'], $block['bits'])))
				return array('node'=>self::_address2node($result['address'], $result['bits']),
							 'description'=>$result['description']);
		return array('node'=>'::/0',
					 'description'=>_('The World'));
	}


	/*
	 * Search the database.
	 */
	public function searchDb($search) {
		$block = null;
		// Check if this is an ip address
		try {
			$block = self::_node2address($search);
		} catch (Exception $e) {
			// Ignore the error.
		}
		try {
			$sql = "SELECT DISTINCT `".$this->prefix."ip`.`address`, ".
					"`".$this->prefix."ip`.`bits`, ".
					"`".$this->prefix."ip`.`description` ".
				"FROM `".$this->prefix."ip` ".
				"LEFT JOIN `".$this->prefix."extrafields` ".
				"ON `".$this->prefix."extrafields`.`address`=`".$this->prefix."ip`.`address` ".
					"AND `".$this->prefix."extrafields`.`bits`=`".$this->prefix."ip`.`bits` ".
				"WHERE `description` LIKE CONCAT('%', :search, '%') ".
					"OR `".$this->prefix."extrafields`.`value` LIKE CONCAT('%', :search, '%') ".
					($block ? "OR (`".$this->prefix."ip`.`address`=:address AND `".$this->prefix."ip`.`bits`=:bits) " : "").
				"ORDER BY `address`";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':search', $search, PDO::PARAM_STR);
			if ($block) {
				$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
				$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			}
			$stmt->execute();
			$result = array();
			while ($node = $stmt->fetch(PDO::FETCH_ASSOC))
				$result[] = array('node'=>self::_address2node($node['address'], $node['bits']),
								  'description'=>$node['description']);
			return $result;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Add a node to the database.
	 */
	public function addNode($node, $description) {
		global $session;

		$block = self::_node2address($node);

		/* Prepare for stupidity */
		if ($block['address']=='00000000000000000000000000000000') {
			$this->error = 'The World already exists';
			return false;
		}

		/* Check for exact match */
		try {
			$sql = "SELECT `address` FROM `".$this->prefix."ip` ".
				"WHERE `address`=:address AND `bits`=:bits";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->execute();
			if ($stmt->fetch()) {
				$this->error = 'Node '.$node.' already exists';
				return false;
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

		/* Check if network address matches bitmask */
		if (strcmp($block['address'], self::_network($block['address'], $block['bits']))!=0) {
			$this->error = 'Address '.$node.' is not on a boundary with '.(strcmp($block['address'], '00000000000000000000000100000000')>0 ? $block['bits'] : $block['bits']-96).' bits';
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

		/* Add new node */
		try {
			$sql = "INSERT INTO `".$this->prefix."ip` (`address`, `bits`, `description`) ".
				"VALUES(:address, :bits, :description)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}

		$this->log('Added node '.$node.
				   (empty($description) ? '' : ' ('.$description.')'));
		return $node;
	}


	/*
	 * Delete a node.
	 */
	public function deleteNode($node, $removechildren = false) {
		global $session;

		/* Check for access */
		if (!$this->isAdmin($session->username)) {
			$access = $this->getAccess($node, $session->username);
			if ($access['access']!='w') {
				$this->error = 'Access denied';
				return false;
			}
		}

		/* Check for children */
		$children = $this->getChildren($node);
		if (count($children) && $removechildren)
			foreach ($children as $child)
				$this->deleteNode(self::_address2node($child['address'], $child['bits']));

		$block = self::_node2address($node);
		try {
			foreach (array('ip', 'extrafields', 'tablenode', 'access') as $table) {
				$sql = "DELETE FROM `".$this->prefix.$table."` ".
					"WHERE `address`=:address AND `bits`=:bits";
				$stmt = $this->db->prepare($sql);
				$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
				$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
				$stmt->execute();
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		$this->log('Deleted node '.$node);
		return true;
	}


	/*
	 * Fetch a node's extra field value.
	 */
	public function getField($field, $node) {
		$block = self::_node2address($node);
		if (empty($node))
			return false;
		try {
			$sql = "SELECT `value` ".
				"FROM `".$this->prefix."fieldvalues` ".
				"WHERE `address`=:address ".
				"AND `bits`=:bits ".
				"AND `field`=:field";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':field', $field, PDO::PARAM_STR);
			$stmt->execute();
			if ($value = $stmt->fetch(PDO::FETCH_ASSOC))
				return $value['value'];
			return '';
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Change a node's address and/or description.
	 */
	public function changeNode($node, $newnode, $description) {
		global $config, $session;

		/* Check for access */
		if (!$this->isAdmin($session->username)) {
			$access = $this->getAccess($node, $session->username);
			$newaccess = $this->getAccess($newnode, $session->username);
			if (($access['access']!='w') &&
				($newaccess['access']!='w')) {
				$this->error = 'Access denied';
				return false;
			}   
		}

		if (!($block = self::_node2address($node))) {
			$this->error = 'Node '.$node.' not found';
			return false;
		}

		$newblock = self::_node2address($newnode);

		/* Check if network address matches bitmask */
		if (strcmp($newblock['address'], self::_network($newblock['address'], $newblock['bits']))!=0) {
			$this->error = 'Address '.$newnode.' is not on a boundary with '.(strcmp($newblock['address'], '00000000000000000000000100000000')>0 ? $newblock['bits'] : $newblock['bits']-96).' bits';
			return false;
		}

		/* Check if not changing ipv4 <> ipv6 */
		if (preg_match('/^000000000000000000000000/', $block['address']) !=
			preg_match('/^000000000000000000000000/', $newblock['address'])) {
			$this->error = 'Cannot renumber ipv4 to ipv6 and vice versa';
			return false;
		}

		try {
			// Change node
			$sql = "UPDATE `".$this->prefix."ip` ".
				"SET `address`=:newaddress, `bits`=:newbits, `description`=:newdescription ".
				"WHERE `address`=:address AND `bits`=:bits";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':newaddress', $newblock['address'], PDO::PARAM_STR);
			$stmt->bindParam(':newbits', $newblock['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':newdescription', $description, PDO::PARAM_STR);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->execute();
			// Change children
			$sql = "UPDATE `".$this->prefix."ip` ".
				"SET `address`=REPLACE(`address`, :base, :newbase) ".
				"WHERE `address` LIKE (CONCAT(:base, '%')) AND `bits`>:bits";
			$stmt = $this->db->prepare($sql);
			$base = preg_replace('/0+$/', '', self::_network($block['address'], $block['bits']));
			$newbase = preg_replace('/0+$/', '', self::_network($newblock['address'], $newblock['bits']));
			$stmt->bindParam(':base', $base, PDO::PARAM_STR);
			$stmt->bindParam(':newbase', $newbase, PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->execute();
			$this->log('Changed node '.$node);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			$this->db->rollBack();
			return false;
		}
	}


	/*
	 * Set a node's extra field value.
	 */
	public function setField($field, $node, $value, $recursive = false) {
		$block = self::_node2address($node);
		try {
			if ($recursive)
				$sql = "DELETE FROM `".$this->prefix."fieldvalues` ".
					"WHERE `address`>=:address ".
						"AND `address`<=:broadcast ".
						"AND `bits`>=:bits ".
						"AND `field`=:field";
			else
				$sql = "DELETE FROM `".$this->prefix."fieldvalues` ".
					"WHERE `address`=:address ".
						"AND `bits`=:bits ".
						"AND `field`=:field";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':field', $field, PDO::PARAM_STR);
			if ($recursive) {
				$broadcast = self::_broadcast($block['address'], $block['bits']);
				$stmt->bindParam(':broadcast', $broadcast, PDO::PARAM_STR);
			}
			$stmt->execute();

			$sql = "INSERT INTO `".$this->prefix."fieldvalues` (`address`, `bits`, `field`, `value`) ".
				"VALUES(:address, :bits, :field, :value)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':field', $field, PDO::PARAM_STR);
			$stmt->bindParam(':value', $value, PDO::PARAM_STR);
			$stmt->execute();
			if ($recursive)
				foreach ($this->getChildren($node) as $child) {
					$block = self::_node2address($child['node']);
					$sql = "INSERT INTO `".$this->prefix."fieldvalues` (`address`, `bits`, `field`, `value`) ".
						"VALUES(:address, :bits, :field, :value)";
					$stmt = $this->db->prepare($sql);
					$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
					$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
					$stmt->bindParam(':field', $field, PDO::PARAM_STR);
					$stmt->bindParam(':value', $value, PDO::PARAM_STR);
					$stmt->execute();
				}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return true;
	}


	/*
	 * Get extra table item.
	 */
	public function getExtra($table, $item = null) {
		global $config;
		if ($item===null)
			try {
				$sql = "SELECT * FROM `".$this->prefix."extratables` ".
					"WHERE `table`=:table ";
				if ($config->extratables[$table]['type']=='integer')
					$sql .= "ORDER BY CAST(`item` AS SIGNED)";
				else
					$sql .= "ORDER BY `".$this->prefix."extratables`.`item`";
				$stmt = $this->db->prepare($sql);
				$stmt->bindParam(':table', $table, PDO::PARAM_STR);
				$stmt->execute();
				return $stmt->fetchAll(PDO::FETCH_ASSOC);
			} catch (PDOException $e) {
				$this->error = $e->getMessage();
				error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
				return false;
			}
		try {
			$sql = "SELECT * FROM `".$this->prefix."extratables` ".
				"WHERE `table`=:table AND `item`=:item";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->execute();
			if (!($extra = $stmt->fetch(PDO::FETCH_ASSOC)))
				return false;
			$sql = "SELECT * FROM `".$this->prefix."tablecolumn` ".
				"WHERE `table`=:table AND `item`=:item";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->execute();
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


	/*
	 * Search through extra table items.
	 */
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
				"WHERE `".$this->prefix."extratables`.`table`=:table ".
				"AND (`".$this->prefix."extratables`.`item` LIKE CONCAT('%', :search, '%') ".
				"OR `".$this->prefix."extratables`.`description` LIKE CONCAT('%', :search, '%') ".
				"OR `".$this->prefix."tablecolumn`.`value` LIKE CONCAT('%', :search, '%') ".
				"ORDER BY ";
			if ($config->extratables[$table]['type']=='integer')
				$sql .= "CAST(`".$this->prefix."tablecolumn`.`item` AS SIGNED)";
			else
				$sql .= "`".$this->prefix."tablecolumn`.`item`";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':search', $item, PDO::PARAM_STR);
			$stmt->execute();
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


	/*
	 * Add extra item to a table.
	 */
	public function addExtra($table, $item, $description, $comments, $columndata = null) {
		global $config;
		if (!isset($config->extratables[$table])) {
			$this->error = 'Unknown table '.$table;
			return false;
		}
		try {
			$sql = "INSERT INTO `".$this->prefix."extratables` (`table`, `item`, `description`, `comments`) ".
				"VALUES(:table, :item, :description, :comments)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->bindParam(':comments', $comments, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (is_array($columndata) && (count($columndata)>0))
			foreach ($columndata as $column=>$data)
				try {
					$sql = "INSERT INTO `".$this->prefix."tablecolumn` (`table`, `item`, `column`, `value`) ".
						"VALUES(:table, :item, :column, :value)";
					$stmt = $this->db->prepare($sql);
					$stmt->bindParam(':table', $table, PDO::PARAM_STR);
					$stmt->bindParam(':item', $item, PDO::PARAM_STR);
					$stmt->bindParam(':column', $column, PDO::PARAM_STR);
					$stmt->bindParam(':data', $data, PDO::PARAM_STR);
					$stmt->execute();
				} catch (PDOException $e) {
					$this->error = $e->getMessage();
					error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
					return false;
				}
		return $this->log('Added \''.$table.'\' item '.$item.
						  (empty($description) ? '' : ' ('.$description.')'));
	}


	/*
	 * Change extra table item.
	 */
	public function changeExtra($table, $olditem, $item, $description, $comments, $columndata) {
		global $config;
		try {
			$sql = "UPDATE `".$this->prefix."extratables` ".
				"SET `item`=:item, `description`=:description, `comments`=:comments ".
				"WHERE `item`=:olditem AND `table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->bindParam(':olditem', $olditem, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->bindParam(':comments', $comments, PDO::PARAM_STR);
			$stmt->execute();
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
				"SET `item`=:item ".
				"WHERE `item`=:olditem AND `table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':olditem', $olditem, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		if (is_array($columndata) && (count($columndata)>0))
			foreach ($columndata as $column=>$data)
				if (!isset($entry['column']) ||
					($data!=$entry[$column])) {
					try {
						$sql = "REPLACE INTO `".$this->prefix."tablecolumn` (`table`, `item`, `column`, `value`) ".
							"VALUES(:table, :item, :column, :value)";
						$stmt = $this->db->prepare($sql);
						$stmt->bindParam(':table', $table, PDO::PARAM_STR);
						$stmt->bindParam(':item', $item, PDO::PARAM_STR);
						$stmt->bindParam(':column', $column, PDO::PARAM_STR);
						$stmt->bindParam(':value', $data, PDO::PARAM_STR);
						$stmt->execute();
					} catch (PDOException $e) {
						$this->error = $e->getMessage();
						error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
						return false;
					}
					if ($config->extratables[$table]['columns'][$column]=='password')
						$changes[] = 'old password';
					else if (isset($entry[$column]))
						$changes[] = $column.'='.$entry[$column];
				}
		if (count($changes)>0)
			$this->log('Changed \''.$table.'\' item '.$item.' (was: '.implode(', ', $changes).')');
		return true;
	}


	/*
	 * Delete extra table item.
	 */
	public function deleteExtra($table, $item) {
		try {
			$sql = "DELETE FROM `".$this->prefix."extratables` ".
				"WHERE `item`=:item AND `table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->execute();
			$sql = "DELETE FROM `".$this->prefix."tablecolumn` ".
				"WHERE `item`=:item AND `table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->execute();
			$sql = "DELETE FROM `".$this->prefix."tablenode` ".
				"WHERE `item`=:item AND `table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return $this->log('Deleted \''.$table.'\' item '.$item);
	}


	/*
	 * Get extra node item.
	 */
	public function getItem($table, $node) {
		$block = self::_node2address($node);
		try {
			$sql = "SELECT `".$this->prefix."tablenode`.`item` AS `item`, ".
					"`".$this->prefix."extratables`.`description` AS `description` ".
				"FROM `".$this->prefix."tablenode` ".
				"LEFT JOIN `".$this->prefix."extratables` ".
					"ON `".$this->prefix."tablenode`.`item`=`".$this->prefix."extratables`.`item` ".
					"AND `".$this->prefix."tablenode`.`table`=`".$this->prefix."extratables`.`table` ".
				"WHERE `address`=:address ".
					"AND `bits`=:bits ".
					"AND `".$this->prefix."tablenode`.`table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->execute();
			if ($item = $stmt->fetch(PDO::FETCH_ASSOC))
				return $item;
			return null;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Get nodes with specific item.
	 */
	public function getItemNodes($table, $item) {
		try {
			$sql = "SELECT `".$this->prefix."ip`.`address`, ".
					"`".$this->prefix."ip`.`bits`, ".
					"`".$this->prefix."ip`.`description` ".
				"FROM `".$this->prefix."ip` ".
				"LEFT JOIN `".$this->prefix."tablenode` ".
					"ON `".$this->prefix."ip`.`address`=`".$this->prefix."tablenode`.`address` ".
					"AND `".$this->prefix."ip`.`bits`=`".$this->prefix."tablenode`.`bits` ".
				"WHERE `item`=:item AND `".$this->prefix."tablenode`.`table`=:table";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->execute();
			$nodes = array();
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $node)
				$nodes[] = array('node'=>self::_address2node($node['address'], $node['bits']),
								 'description'=>$node['description']);
			return $nodes;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Set table item for node.
	 */
	public function setItem($table, $node, $item, $recursive = false) {
		$block = self::_node2address($node);
		$olditem = preg_replace('/^-$/', '', $this->getItem($table, $node));
		$item = preg_replace('/^-$/', '', $item);
		try {
			if ($recursive)
				$sql = "DELETE FROM `".$this->prefix."tablenode` ".
					"WHERE `table`=:table ".
						"AND `address`>=:address ".
						"AND `address`<=:broadcast ".
						"AND `bits`>=:bits";
			else
				$sql = "DELETE FROM `".$this->prefix."tablenode` ".
					"WHERE `table`=:table ".
						"AND `address`=:address ".
						"AND `bits`=:bits";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			if ($recursive) {
				$broadcast = self::_broadcast($block['address'], $block['bits']);
				$stmt->bindParam(':broadcast', $broadcast, PDO::PARAM_STR);
			}
			$stmt->execute();
			$sql = "INSERT INTO `".$this->prefix."tablenode` (`table`, `item`, `address`, `bits`) ".
				"VALUES(:table, :item, :address, :bits)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':item', $item, PDO::PARAM_STR);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->execute();

			if ($recursive)
				foreach ($this->getChildren($node) as $child) {
					$block = self::_node2address($child['node']);
					$sql = "INSERT INTO `".$this->prefix."tablenode` (`table`, `item`, `address`, `bits`) ".
						"VALUES(:table, :item, :address, :bits)";
					$stmt = $this->db->prepare($sql);
					$stmt->bindParam(':table', $table, PDO::PARAM_STR);
					$stmt->bindParam(':item', $item, PDO::PARAM_STR);
					$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
					$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
					$stmt->execute();
				}

		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		$this->log('Set \''.$table.'\' for '.$node.' to '.$item);
		return true;
	}


	/*
	 * Change username.
	 */
	public function changeUsername($username, $oldusername) {
		if ($username==$oldusername)
			return true;
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `username`=:username ".
				"WHERE `username`=:oldusername";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->bindParam(':oldusername', $oldusername, PDO::PARAM_STR);
			$stmt->execute();
			$this->log('Changed username '.$oldusername.' to '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Change user's full name.
	 */
	public function changeName($name, $username = null) {
		global $session;
		if (!$username)
			$username = $session->username;
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `name`=:name ".
				"WHERE `username`=:username";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		$session->changeName($name);
		$this->log('Changed name for '.$username.' to '.$name);
		return true;
	}


	/*
	 * Change user's password.
	 */
	public function changePassword($password, $username = null) {
		global $session;
		if (!$username)
			$username = $session->username;
		try {
			$sql = "UPDATE `".$this->prefix."users` ".
				"SET `password`=:password ".
				"WHERE `username`=:username";
			$stmt = $this->db->prepare($sql);
			$md5 = md5($password);
			$stmt->bindParam(':password', $md5, PDO::PARAM_STR);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->execute();
			$this->log('Changed password for '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Set user's admin status.
	 */
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
				"SET `admin`=:admin ".
				"WHERE `username`=:username";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':admin', $admin ? 0 : 1, PDO::PARAM_INT);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$this->log('Changed admin setting for '.$username.' to '.
					   ($user['admin'] ? 'false' : 'true'));
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Add a user.
	 */
	public function addUser($username, $name, $password) {
		try {
			$sql = "INSERT INTO `".$this->prefix."users` (`username`, `name`, `password`, `admin`) ".
				"VALUES(:username, :name, :password, :admin)";
			$md5 = md5($password);
			$admin = 0;
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->bindParam(':name', $name, PDO::PARAM_STR);
			$stmt->bindParam(':password', $md5, PDO::PARAM_STR);
			$stmt->bindParam(':admin', $admin, PDO::PARAM_INT);
			$stmt->execute();
			$this->log('Added user '.$username. ' ('.$name.')');
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Delete a user.
	 */
	public function deleteUser($username) {
		try {
			$sql = "DELETE FROM `".$this->prefix."users` ".
				"WHERE `username`=:username";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->execute();
			$this->log('Deleted user '.$username);
			return true;
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Get (and search) log entries.
	 */
	public function getLog($search) {
		try {
			$sql = "SELECT * FROM `".$this->prefix."log`";
			if ($search && (trim($search)!=''))
				$sql .= " WHERE `username` LIKE CONCAT('%', :search, '%') ".
					"OR `action` LIKE CONCAT('%', :search, '%') ";
			$sql .= "ORDER BY `stamp` DESC";
			$stmt = $this->db->prepare($sql);
			if ($search && (trim($search)!=''))
				$stmt->bindParam(':search', $search, PDO::PARAM_STR);
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}


	/*
	 * Set user's per node access.
	 */
	public function setAccess($node, $username, $access, $recursive = false) {
		$block = self::_node2address($node);
		try {
			if ($recursive)
				$sql = "DELETE FROM `".$this->prefix."access` ".
					"WHERE `address`>=:address ".
						"AND `address`<=:broadcast ".
						"AND `bits`>=:bits ".
						"AND `username`=:username";
			else
				$sql = "DELETE FROM `".$this->prefix."access` ".
					"WHERE `address`=:address ".
						"AND `bits`=:bits ".
						"AND `username`=:username";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			if ($recursive) {
				$broadcast = self::_broadcast($block['address'], $block['bits']);
				$stmt->bindParam(':broadcast', $broadcast, PDO::PARAM_STR);
			}
			$stmt->execute();
			$sql = "INSERT INTO `".$this->prefix."access` (`address`, `bits`, `username`, `access`) ".
				"VALUES(:address, :bits, :username, :access)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':address', $block['address'], PDO::PARAM_STR);
			$stmt->bindParam(':bits', $block['bits'], PDO::PARAM_INT);
			$stmt->bindParam(':username', $username, PDO::PARAM_STR);
			$stmt->bindParam(':access', $access, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
	}

	public function findFree($nodes, $bits) {
		foreach ($nodes as $node) {
			$parent = $this->findParent($node['node']);
			$children = $this->getChildren($parent['node']);
			if (count($children)) {
				$block = self::_node2address($node['node']);
				$address = $block['address'];
				$childblock = array('address'=>self::_add(self::_broadcast($block['address'], $block['bits']), 1),
									'bits'=>128);
				$children[] = array('node'=>self::_address2node($childblock['address'], $childblock['bits']),
									'description'=>'');
				foreach ($children as $child) {
					$unused = self::_findunused($address, $child['address']);
					if (is_array($unused) && (count($unused)>0)) {
						foreach ($unused as $free)
							if ($free['bits']<=$bits) {
								$freeblock = array('node'=>'', 'description'=>'');
								if (($bits==128) && ($free['bits']<128) &&
									(preg_match('/00$/', $free['address'])))
									$freeblock['node'] = self::_address2node(self::_add($free['address'], 1), $bits);
								else if (($bits==128) && !preg_match('/(00|ff)$/', $free['address']))
									$freeblock['node'] = self::_address2node($free['address'], $bits);
								else if ($bits!=128)
									$freeblock['node'] = self::_address2node($free['address'], $bits);
								return $freeblock;
							}
					}
					$address = self::_add(self::_broadcast($child['address'], $child['bits']), 1);
				}
			} else {
				return array('node'=>self::_address2node($block['address'], $bits),
							 'description'=>'');
			}
		}
		return false;
	}


	public function addField($field, $type, $description = '', $url = '', $inoverview = true) {
		if (!in_array($type, array('text', 'integer', 'boolean', 'url'))) {
			$this->error = 'New field type unknown.';
			return false;
		}
		try {
			$sql = "INSERT INTO `".$this->prefix."fields` (`field`, `type`, `description`, `url`, `inoverview`) ".
				"VALUES(:field, :type, :description, :url, :inoverview)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':field', $field, PDO::PARAM_STR);
			$stmt->bindParam(':type', $type, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->bindParam(':url', $url, PDO::PARAM_STR);
			$stmt->bindParam(':inoverview', $inoverview, PDO::PARAM_BOOL);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return true;
	}


	public function removeField($field) {
		try {
			$sql = "DELETE FROM `".$this->prefix."fields` WHERE `field`=:field";
			$stmt->bindParam(':field', $field, PDO::PARAM_STR);
			$stmt->execute();
			$sql = "DELETE FROM `".$this->prefix."fieldvalues` WHERE `field`=:field";
			$stmt->bindParam(':field', $field, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return true;
	}


	public function addTable($table, $type, $description, $inoverview = true, $linkaddress = true, $columns = array()) {
		if (!in_array($type, array('text', 'integer'))) {
			$this->error = 'New table key type unknown.';
			return false;
		}
		try {
			$sql = "INSERT INTO `".$this->prefix."tables` (`table`, `description`, `inoverview`, `linkaddress`) ".
				"VALUES(:table, :description, :inoverview, :linkaddress)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':description', $description, PDO::PARAM_STR);
			$stmt->bindParam(':inoverview', $inoverview, PDO::PARAM_BOOL);
			$stmt->bindParam(':linkaddress', $linkaddress, PDO::PARAM_BOOL);
			$stmt->execute();
			$sql = "INSERT INTO `".$this->prefix."tablecolumns` (`table`, `column`, `type`) ".
				"VALUES(:table, '__pkey', :type)";
			$stmt = $this->db->prepare($sql);
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->bindParam(':type', $type, PDO::PARAM_STR);
			$stmt->execute();
			foreach ($columns as $column=>$columntype) {
				if (!in_array($columntype, array('text', 'integer', 'password'))) {
					$this->error = 'New table column type unknown.';
					return false;
				}
				$sql = "INSERT INTO `".$this->prefix."tablecolumns` (`table`, `column`, `type`) ".
					"VALUES(:table, :column, :type)";
				$stmt = $this->db->prepare($sql);
				$stmt->bindParam(':table', $table, PDO::PARAM_STR);
				$stmt->bindParam(':column', $column, PDO::PARAM_STR);
				$stmt->bindParam(':type', $columntype, PDO::PARAM_STR);
				$stmt->execute();
			}
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return true;
	}


	function removeTable($table) {
		try {
			$sql = "DELETE FROM `".$this->prefix."tables` WHERE `table`=:table";
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->execute();
			$sql = "DELETE FROM `".$this->prefix."tableitems` WHERE `table`=:table";
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->execute();
			$sql = "DELETE FROM `".$this->prefix."tablecolumns` WHERE `table`=:table";
			$stmt->bindParam(':table', $table, PDO::PARAM_STR);
			$stmt->execute();
		} catch (PDOException $e) {
			$this->error = $e->getMessage();
			error_log($e->getMessage().' in '.$e->getFile().' line '.$e->getLine().'.');
			return false;
		}
		return true;
	}


	function _findunused($base, $next) {
		$unused = array();
		if ((strcmp($base, $next)<0) &&
			preg_match('/^([0]*)([1-9a-f]|$)/', self::_subtract($next, $base), $matches)) {
			$bits = 1+(4*strlen($matches[1]))+(4-strlen(decbin(hexdec($matches[2]))));
			while (($bits<128) &&
				(strcmp($base, self::_network($base, $bits))!=0))
				$bits++;
			if ((strcmp($base, '00000000000000000000000100000000')>=0) ||
				($bits<=128))
				$unused[] = array('address'=>$base,
								  'bits'=>$bits);
			$base = self::_add(self::_broadcast($base, $bits), '00000000000000000000000000000001');
			$nextunused = self::_findunused($base, $next);
			if (is_array($nextunused) && (count($nextunused)>0))
				foreach ($nextunused as $network)
					$unused[] = $network;
			}
		return $unused;
	}


}


?>
