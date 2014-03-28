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


class Skin {


	public $error = null;
	private $skin = null;
	private $data = null;
	private $vars = array();
	private $blocks = array();


	public function __construct($config) {

		global $root;
		$this->skin = $config['skin'];
		if (!is_dir($root.DIRECTORY_SEPARATOR.'skins'.DIRECTORY_SEPARATOR.$this->skin))
			$this->error = 'Skin '.$this->skin.' does not exist';
		else if (!file_exists($root.DIRECTORY_SEPARATOR.'skins'.DIRECTORY_SEPARATOR.$this->skin.DIRECTORY_SEPARATOR.'index.html'))
			$this->error = 'Skin '.$this->skin.' file not found';

	}


	public function setFile($filename) {

		global $root;
		$this->error = null;
		$this->data = null;
		$this->blocks = array();
		if (file_exists($file = $root.DIRECTORY_SEPARATOR.'skins'.DIRECTORY_SEPARATOR.$this->skin.DIRECTORY_SEPARATOR.$filename)) {
			$this->data = file_get_contents($file);
			$this->findBlocks();
			return true;
		}
		$this->error = 'Cannot read skin file skins'.DIRECTORY_SEPARATOR.$this->skin.DIRECTORY_SEPARATOR.$filename;
		return false;

	}


	private function findBlocks() {

		if (preg_match_all('/<!-- BEGIN ([a-z0-9]+) -->/', $this->data, $blocks)) {
			$blocks = $blocks[1];
			krsort($blocks, SORT_NUMERIC);
			foreach ($blocks as $block) {
				$this->blocks[$block] = preg_replace('/.*<!-- BEGIN '.preg_quote($block).' -->/s', '', $this->data);
				$this->blocks[$block] = preg_replace('/<!-- END '.preg_quote($block).' -->.*/s', '', $this->blocks[$block]);
				$this->data = preg_replace('/<!-- BEGIN '.preg_quote($block).' -->.*<!-- END '.preg_quote($block).' -->/s', '{'.$block.'}', $this->data);
				$this->vars[$block] = '';
			}
		}
		$this->vars['skindir'] = 'skins/'.$this->skin;

	}


	public function setVar($var, $value) {

		$this->vars[$var] = $value;

	}


	public function parse($block = NULL) {

		if ($block)
			$blockdata = $this->blocks[$block];
		else
			$blockdata = $this->data;

		/* Find the vars we have */
		preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $blockdata, $matches);

		/* Replace our vars */
		$vars = array();
		foreach ($this->vars as $var=>$value)
			$vars['/\{'.preg_quote($var).'\}/'] = $value;
		$blockdata = preg_replace(array_keys($vars), $vars, $blockdata);
		if ($block)
			$this->vars[$block] .= $blockdata;
		else
			$this->data = $blockdata;

		/* Clear vars that came from blocks */
		if (count($matches)>1)
			foreach ($matches[1] as $var)
				if (isset($this->blocks[$var]))
					$this->vars[$var] = '';

	}


	public function get($cleanunused = true) {

		$vars = array();
		foreach ($this->vars as $var=>$value)
			$vars['/\{'.preg_quote($var).'\}/'] = $value;
		if ($cleanunused)
			$vars['/\{[a-zA-Z0-9_]+\}/'] = '';
		return preg_replace(array_keys($vars), $vars, $this->data);

	}


	public function hideBlock($block) {

		if ($this->blocks[$block]) {
			$this->blocks['__hidden__'.$block] = $this->blocks[$block];
			unset($this->blocks[$block]);
		}

	}

}


?>
