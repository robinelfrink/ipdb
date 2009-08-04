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


class extratable {


	public $error = null;


	public function get() {
		global $config, $database;
		$type = $config->extratables[request('table')]['type'];
		$items = $database->getExtra(request('table'));
		$skin = new Skin($config->skin);
		$skin->setFile('extratable.html');

		$items = $database->getExtra(request('table'));
		if (count($items)>0) {
			foreach ($items as $item) {
				$skin->setVar('item', $item['item']);
				$skin->setVar('description', ($type=='password' ? crypt($item['description'], randstr(2)) : $item['description']));
				$skin->setVar('comments', $item['comments']);
				$skin->parse('itemrow');
			}
			$skin->parse('items');
		} else {
			$skin->parse('noitems');
		}

		$skin->setVar('table', $config->extratables[request('table')]['description']);
		return array('title'=>'IPDB :: Table '.$config->extratables[request('table')]['description'],
					 'content'=>$skin->get());

	}


}


?>
