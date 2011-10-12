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

$Id$
*/


class Tree {


	public $error = null;

	public function getHtml($id, $node = null) {
		global $config, $database;
		$tree = $database->getTree($id);
		$skin = new Skin($config->skin);
		$skin->setFile('tree.html');
		if (count($tree)>0) {
			foreach ($tree as $network) {
				if ($network['bits']<128) {
					$subtree = '';
					if ($database->hasNetworks($network['id'])) {
						if (is_numeric($node) &&
							($child = $database->getAddress($node)) &&
							addressIsChild($child['address'], $network['address'], $network['bits'])) {
							$class = 'class="expanded"';
							$subtree = Tree::getHtml($network['id'], $child['id']);
						} else {
							$class = 'class="collapsed"';
						}
					} else {
						$class = '';
					}
					$skin->setVar('node', $network['id']);
					$skin->setVar('link', '?page=main&amp;node='.$network['id']);
					$skin->setVar('label', showip($network['address'], $network['bits']));
					$skin->setVar('description', htmlentities($network['description']));
					$skin->setVar('subtree', $subtree);
					$skin->setVar('class', $class);
					$skin->parse('network');
				}
			}
			return $skin->get();
		} else
			return '';
	}


	public function getTxt($id, $level = 0) {
		global $config, $database;
		$txt = '';
		$tree = $database->getTree($id);
		if (count($tree)>0) {
			foreach ($tree as $id=>$child) {
				$txt .= str_pad('', ($level+1)*2, ' ').ip2address($child['address']).
					($child['address']<'00000000000000000000000100000000' ?
					($child['bits']<128 ? '/'.($child['bits']-96) : '') :
					($child['bits']<128 ? '/'.$child['bits'] : '')).
					str_pad('      ', ($level+1)*2, ' ').$child['description']."\n";
				$txt .= Tree::getTxt($child['id'], 1);
			}
		}
		return $txt;
	}


}


?>
