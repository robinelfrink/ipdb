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


class Config {


	public $error = null;
	public $database = array('provider'=>'mysql',
							 'host'=>'localhost',
							 'database'=>'ipdb',
							 'username'=>'ipuser',
							 'password'=>'secret',
							 'prefix'=>'_ipdb');
	public $session = array('expire'=>'10m',
							'auth'=>array('type'=>'ipdb'));
	public $debug = false;
	public $skin = 'default';
	public $pools = array('default_ipv4_prefix'=>30, 'default_ipv6_prefix'=>64);


	public function __construct() {

		global $root;
		$files = array($root.DIRECTORY_SEPARATOR.'config.dist.php',
					   $root.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'config.dist.php',
					   $root.DIRECTORY_SEPARATOR.'config.php',
					   $root.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'config.php');
		foreach ($files as $file)
			if (file_exists($file))
				if (false === @include_once($file))
					$this->error = 'Cannot read config file '.$file;
				else
					foreach (array('database', 'debug', 'session', 'skin', 'pools') as $section)
						if (is_array($this->$section))
							$this->$section = array_merge($this->$section, $config[$section]);
						else
							$this->$section = $config[$section];
	}


}


?>
