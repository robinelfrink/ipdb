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


class Tree {


	public $error = null;

	public static function getHtml($parent, $node = null) {
		global $config, $database;
		$children = $database->getChildren($parent, false);
		$skin = new Skin($config->skin);
		$skin->setFile('tree.html');
		if (count($children)) {
			foreach ($children as $child) {
				$subtree = Tree::getHtml($child['node'], $node);
				if (empty($subtree)) {
					$class = '';
				} else if ($node && (Database::isSame($node, $child['node']) ||
									 Database::isChild($node, $child['node']))) {
					$class = 'class="expanded"';
				} else {
					$class = 'class="collapsed"';
				}
				$skin->setVar('node', $child['node']);
				$skin->setVar('description', htmlentities($child['description']));
				$skin->setVar('subtree', $subtree);
				$skin->setVar('class', $class);
				$skin->parse('network');
			}
			return $skin->get();
		} else
			return '';
	}


	public static function getTxt($node, $level = 0) {
		global $config, $database;
		$txt = '';
		$children = $database->getChildren($node);
		if (count($children))
			foreach ($children as $child) {
				$txt .= str_pad('', ($level+1)*2, ' ').$child['node'].
					str_pad('      ', ($level+1)*2, ' ').$child['description']."\n";
				$txt .= Tree::getTxt($child['node'], 1);
			}
		return $txt;
	}


}


?>
