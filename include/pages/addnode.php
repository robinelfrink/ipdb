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


class addnode {


	public $error = null;


	public function get() {
		global $config, $database;
		$skin = new skin($config->skin);
		$skin->setFile('node.html');
		$skin->setVar('description', request('description'));
		if (!($basenode = $database->getNode(request('node'))))
			$basenode = $database->getParent(request('node'));
		if (count($config->extrafields)>0)
			foreach ($config->extrafields as $field=>$details) {
				$skin->setVar('name', $field);
				$skin->setVar('fullname', isset($details['name']) ? $details['name'] : '');
				$skin->setVar('value', $database->getField($field, $basenode['node']));
				$skin->parse('extrafield');
			}
		if (count($config->extratables)>0)
			foreach ($config->extratables as $table=>$details)
				if ($details['linkaddress']) {
					$tableitems = $database->getExtra($table);
					$item = $database->getItem($table, $basenode['node']);
					$options = '<option value="">-</option>';
					if (count($tableitems)>0)
						foreach ($tableitems as $tableitem)
							$options .= '<option value="'.$tableitem['item'].'"'.
								($item && $item['item']==$tableitem['item'] ? ' selected="selected"' : '').
								'>'.$tableitem['item'].' '.
								($details['type']=='password' ?
								 crypt($tableitem['description'], randstr(2)) :
								 $tableitem['description']).'</option>';
					$skin->setVar('table', $table);
					$skin->setVar('tableoptions', $options);
					$skin->parse('extratable');
				}
		$skin->setVar('address', preg_replace('/\/.*/', '', request('node')));
		$skin->setVar('bits', preg_replace('/.*\//', '', request('node')));
		$skin->parse('addnode');
		$content = $skin->get();
		return array('title'=>'IPDB :: Add node',
					 'content'=>$content);
	}


}


?>
