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
		$tpl = new Template('node.html');
		if ($node = $database->getNode(request('node'))) {
			if ($parent = $database->getParent($node['node'])) {
				$tpl->setVar('parentaddress', $parent['node']);
				$tpl->setVar('parentlink', me().'?page=main&amp;node='.$parent['node']);
			} else {
				$tpl->setVar('parentaddress', '::/0');
				$tpl->setVar('parentlink', me().'?page=main&amp;node=::/0');
			}
			$tpl->parse('parent');
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					$tpl->setVar('name', $field);
					$tpl->setVar('value', $database->getField($field, $node['node']));
					$tpl->parse('extrafield');
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
						$tpl->setVar('table', $table);
						$tpl->setVar('tableoptions', $options);
						$tpl->parse('extratable');
					}
			$tpl->setVar('address', preg_replace('/\/.*/', '', $node['node']));
			$tpl->setVar('bits', preg_replace('/.*\//', '', $node['node']));
			$tpl->setVar('description', htmlentities($node['description']));
			$tpl->setVar('node', $node['node']);
		}
		$tpl->parse('changenode');
		$content = $tpl->get();
		return array('title'=>'IPDB :: Change node',
					 'content'=>$content);
	}


}


?>
