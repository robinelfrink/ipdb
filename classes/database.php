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


	private function escape($string) {

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


}


?>
