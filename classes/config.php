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


class Config {


	public $error = null;
	public $database = array('provider'=>'mysql',
							 'host'=>'localhost',
							 'database'=>'ipdb',
							 'username'=>'ipuser',
							 'password'=>'secret',
							 'prefix'=>'_ipdb');
	public $session = array('expire'=>'10m');
	public $debug = array('debug'=>'false');
	public $skin = array('skin'=>'default');
	public $columns = array();


	public function __construct() {

		global $root;
		$files = array($root.DIRECTORY_SEPARATOR.'ipdb.ini.dist',
					   $root.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'ipdb.ini.dist',
					   $root.DIRECTORY_SEPARATOR.'ipdb.ini',
					   $root.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'ipdb.ini');
		foreach ($files as $file)
			if (file_exists($file))
				if ($ini = @parse_ini_file($file, true)) {
					foreach (array('database', 'debug', 'session', 'skin') as $section)
						if (isset($ini[$section]))
							$this->$section = array_merge($this->$section, $ini[$section]);
					foreach (array_keys($ini) as $section)
						if (preg_match('/^column_/', $section))
							$this->columns[preg_replace('/^column_/', '', $section)] = $ini[$section];
				} else
					$this->error = 'Cannot read config file '.$file;
	}


}


?>
