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


class addnode {


	public $error = null;


	public function get() {
		global $config, $database;
		$skin = new skin($config->skin);
		$skin->setFile('node.html');
		if ($data = $database->findAddress(address2ip(request('address')), strcmp(address2ip(request('address')), '00000000000000000000000100000000')<0 ? request('bits')+96 : request('bits'))) {
			$parent = $database->getAddress($data['parent']);
			$skin->setVar('parentaddress', ip2address($parent['address']));
			$skin->setVar('parentbits', (strcmp($parent['address'], '00000000000000000000000100000000')<0 ? $parent['bits']-96 : $parent['bits']));
			$skin->setVar('parentlink', me().'?page=main&node='.$node);
			$skin->parse('parent');
			$skin->setVar('description', request('description', $data['description']));
		} else
			$skin->setVar('description', request('description'));
		if (count($config->extrafields)>0)
			foreach ($config->extrafields as $field=>$details) {
				$skin->setVar('name', $field);
				$skin->setVar('fullname', $details['name']);
				if ($data)
					$skin->setVar('value', request($field, $database->getField($field, $node)));
				else
					$skin->setVar('value', request($field));
				$skin->parse('extrafield');
			}
		$skin->setVar('address', request('address'));
		$skin->setVar('bits', request('bits'));
		$skin->parse('addnode');
		$content = $skin->get();
		return array('title'=>$title,
					 'content'=>$content);
	}


}


?>
