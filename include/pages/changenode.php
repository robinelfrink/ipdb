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


class changenode {


	public $error = null;


	public function get() {
		global $config, $database;
		$skin = new skin($config->skin);
		$skin->setFile('node.html');
		if ($node = $database->getNode(request('node'))) {
			if ($parent = $database->getParent($node['node'])) {
				$skin->setVar('parentaddress', $parent['node']);
				$skin->setVar('parentlink', me().'?page=main&amp;node='.$parent['node']);
			} else {
				$skin->setVar('parentaddress', '::/0');
				$skin->setVar('parentlink', me().'?page=main&amp;node=::/0');
			}
			$skin->parse('parent');
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					$skin->setVar('name', $field);
					$skin->setVar('value', $database->getField($field, $node['node']));
					$skin->parse('extrafield');
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details)
					if ($details['linkaddress']) {
						$tableitems = $database->getExtra($table);
						$item = $database->getItem($table, $node['node']);
						$options = '<option value="">-</option>';
						if (count($tableitems)>0)
							foreach ($tableitems as $tableitem)
								$options .= '<option value="'.$tableitem['item'].'"'.
								($item['item']==$tableitem['item'] ? ' selected="selected"' : '').
								'>'.$tableitem['item'].' '.
								($details['type']=='password' ?
								 crypt($tableitem['description'], randstr(2)) :
								 $tableitem['description']).'</option>';
						$skin->setVar('table', $table);
						$skin->setVar('tableoptions', $options);
						$skin->parse('extratable');
					}
			$skin->setVar('address', preg_replace('/\/.*/', '', $node['node']));
			$skin->setVar('bits', preg_replace('/.*\//', '', $node['node']));
			$skin->setVar('description', htmlentities($node['description']));
			$skin->setVar('node', $node['node']);
		}
		$skin->parse('changenode');
		$content = $skin->get();
		return array('title'=>'IPDB :: Change node',
					 'content'=>$content);
	}


}


?>
