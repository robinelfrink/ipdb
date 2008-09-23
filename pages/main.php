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


class main {


	public $error = null;


	public function get() {
		global $node, $config, $database;
		if ($node) {
			$data = $database->getAddress($node);
			$title = ip2address($data['address']).'/'.
				(strcmp($data['address'], '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']);
			$content = $title;
		} else {
			$title = 'Main page';
			$tree = $database->getTree(0, false);
			if (count($tree)>0) {
				$skin = new Skin($config->skin);
				$skin->setFile('main.html');
				$skin->setBlock('network');
				foreach ($tree as $network) {
					$skin->setVar('label', ip2address($network['address']).'/'.
								  (strcmp($data, '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']));
					$skin->setVar('description', $network['description']);
					$skin->parse('network');
				}
				$skin->setVar('count', count($tree));
				$content = $skin->get();
			} else {
				$content = '
<p>You currently do not have any networks in your database.</p>';
			}
		}
		return array('title'=>$title,
					 'content'=>$content);
	}


}


?>
