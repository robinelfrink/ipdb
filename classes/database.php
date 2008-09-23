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
			/* Drop old tables, even though we're pretty sure they don't exists. */
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
		}

	}


	public function getUser($username) {
		return $this->query("SELECT `password` FROM `admin` WHERE `username` = '".
							$this->escape($username)."'");
	}


	public function getAddress($node) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `description` FROM `ip` WHERE ".
							   "`id`=".$this->escape($node));
		return ($result ? $result[0] : false);
	}


	public function getTree($parent, $recursive = false) {
		$result = $this->query("SELECT `id`, `address`, `bits`, `description` FROM `ip` WHERE ".
							   "`parent`=".$this->escape($parent)." ORDER BY `address`");
		if ($recursive===false)
			return $result;
		foreach ($result as $network)
			if (($recursive===true) ||
				(is_string($recursive) && addressIsChild($recursive, $result['address'], $result['bits'])))
				$result['children'] = $this->getTree($result['id'], $recursive);
		return $result;
	}


	public function hasNetworks($parent) {
		$result = $this->query("SELECT COUNT(`id`) AS `total` FROM `ip` WHERE `parent`=".
							   $this->escape($parent)." AND `bits`<128");
		return ($result[0]['total']>0);
	}


}


?>
