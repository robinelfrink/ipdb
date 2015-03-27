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


class editcustomtableitem {


	public $error = null;


	public function get() {
		global $config, $error, $database;
		if (!$item = $database->getCustomTableItem(request('table'), request('item'))) {
			request('item', null, true);
			$error = 'Item does not exist.';
			require_once dirname(__FILE__).'/customtable.php';
			return customtable::get();
		}
		$tpl = new Template('customtableitemform.html');
		$table = $database->getCustomTable(request('table'));
		if (isset($table['columns']) &&
			is_array($table['columns']) &&
			count($table['columns']))
			foreach ($table['columns'] as $column=>$type) {
				$tpl->setVar('name', $column);
				if ($type=='password')
					$tpl->setVar('input', '<input type="password" name="'.htmlentities($column).'" />');
				else if (isset($item[$column]))
					$tpl->setVar('input', '<input type="text" name="'.htmlentities($column).'" value="'.htmlentities($item[$column]).'" />');
				else
					$tpl->setVar('input', '<input type="text" name="'.htmlentities($column).'" value="" />');
				$tpl->parse('column');
			}
		$tpl->setVar('item', htmlentities(request('item')));
		$tpl->setVar('description', htmlentities($item['description']));
		$tpl->setVar('comments', htmlentities($item['comments']));
		$tpl->setVar('table', htmlentities($table['description']));
		$tpl->parse('change');
		return array('title'=>'IPDB :: Change '.$table['description'],
					 'content'=>$tpl->get());

	}


}


?>
