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


class Skin {


	public $error = null;
	private $skin = null;
	private $file = null;
	private $vars = array();


	public function __construct($config) {

		global $root;
		$this->skin = $config['skin'];
		if (!is_dir($root.DIRECTORY_SEPARATOR.'skins'.DIRECTORY_SEPARATOR.$this->skin))
			$this->error = 'Skin '.$this->skin.' does not exist';
		else if (!file_exists($root.DIRECTORY_SEPARATOR.'skins'.DIRECTORY_SEPARATOR.$this->skin.DIRECTORY_SEPARATOR.'index.html'))
			$this->error = 'Skin '.$this->skin.' file not found';

		$this->vars['skindir'] = 'skins/'.$this->skin;

	}


	public function setFile($filename) {

		global $root;
		$this->error = null;
		if (file_exists($this->file = $root.DIRECTORY_SEPARATOR.'skins'.DIRECTORY_SEPARATOR.$this->skin.DIRECTORY_SEPARATOR.$filename))
			return true;
		$this->error = 'Cannot read skin file skins'.DIRECTORY_SEPARATOR.$this->skin.DIRECTORY_SEPARATOR.$filename;
		return false;

	}


	public function setVar($var, $value) {

		$this->vars[$var] = $value;

	}


	public function get($cleanunused = true) {

		$data = file_get_contents($this->file);
		$vars = array();
		foreach ($this->vars as $var=>$value)
			$vars['/\{'.preg_quote($var).'\}/'] = $value;
		if ($cleanunused)
			$vars['/\{[a-zA-Z0-9_]+\}/'] = '';
		return preg_replace(array_keys($vars), $vars, $data);

	}

}


?>
