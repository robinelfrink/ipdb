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


class deletenode {


	public $error = null;


	public function get() {
		global $config, $database;
		if ($node = $database->getNode(request('node'))) {
			$tpl = new Template('deletenode.html');
			$children = $database->getChildren($node['node']);
			if (count($children))
				$tpl->parse('children');
			$tpl->setVar('nodename', $node['name']);
			$tpl->setVar('description', $node['description']);
			$tpl->setVar('node', $node['node']);
			$content = $tpl->get();
			return array('title'=>'IPDB :: Delete node',
						 'content'=>$content);
		} else
			return array('title'=>'Error',
						 'content'=>'Requested node cannot be found');
	}


}


?>
