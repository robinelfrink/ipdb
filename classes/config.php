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


class Config {


	public $error = null;
	public $database = array('provider'=>'mysql',
							 'host'=>'localhost',
							 'database'=>'ipdb',
							 'username'=>'ipuser',
							 'password'=>'secret',
							 'prefix'=>'_ipdb');
	public $skin = array('skin'=>'default');


	public function __construct() {

		$ds = DIRECTORY_SEPARATOR;
		$files = array(dirname(__FILE__).$ds.'..'.$ds.'ipdb.ini.dist',
					   dirname(__FILE__).$ds.'..'.$ds.'..'.$ds.'ipdb.ini.dist',
					   dirname(__FILE__).$ds.'..'.$ds.'ipdb.ini',
					   dirname(__FILE__).$ds.'..'.$ds.'..'.$ds.'ipdb.ini');
		foreach ($files as $file)
			if (file_exists($file))
				if ($ini = @parse_ini_file($file, true)) {
					foreach (array('database', 'skin') as $section)
						if (isset($ini[$section]))
							$this->$section = array_merge($this->$section, $ini[$section]);
				} else
					$this->error = 'Cannot read config file '.$file;
	}


}


?>
