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


class Template {


	private $data = null;
	private $vars = array();
	private $blocks = array();


	public function __construct($file) {

		$dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR;
		if (!file_exists($dir.$file))
			throw new Exception('Cannot read template file '.$file);
		$this->data = file_get_contents($dir.$file);
		$this->findBlocks();
		return true;

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

	}


	public function setVar($var, $value) {

		if (!is_string($value) && !is_numeric($value)) {
			error_log('Warning: assigning '.gettype($value).' to '.$var.' in '.__FILE__.' line '.__LINE__);
			$value = serialize($value);
		}
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
