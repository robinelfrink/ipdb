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


class Tree {


	public $error = null;

	public function get($id, $node = null) {

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
							$subtree = Tree::get($network['id'], $child['id']);
						} else {
							$class = 'class="collapsed"';
						}
					} else {
						$class = '';
					}
					$skin->setVar('node', $network['id']);
					$skin->setVar('link', '?page=main&node='.$network['id']);
					$skin->setVar('label', showip($network['address'], $network['bits']));
					$skin->setVar('description', htmlentities($network['description']));
					$skin->setVar('subtree', $subtree);
					$skin->setVar('class', $class);
					$skin->parse('network');
				}
			}
			if (!$id)
				return '<form method="" action="" remote="remote">
	<input type="text" size="16" name="search" value="'.htmlentities(request('search', '')).'" />
	<input type="image" src="skins/'.$config->skin['skin'].'/images/search.png" />
	<input type="hidden" name="action" value="search" />
</form>'.$skin->get();
			else
				return $skin->get();
		} else
			return '';
	}


}


?>
