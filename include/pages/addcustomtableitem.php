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


class addcustomtableitem {


	public $error = null;


	public function get() {
		global $config, $error, $database;
		$tpl = new Template('customtableitemform.html');
		$tpl->setVar('item', request('item', ''));
		$tpl->setVar('description', request('description', ''));
		$tpl->setVar('comments', request('comments', ''));
		$table = $database-->getCustomTable(request('table'));
		$tpl->setVar('table', $table['description']);
		if (isset($table['columns']) &&
			is_array($table['columns']) &&
			count($table['columns']))
			foreach ($table['columns'] as $column=>$type) {
				$tpl->setVar('name', $column);
				if ($type=='password')
					$tpl->setVar('input', '<input type="password" name="'.htmlentities($column).'" />');
				else
					$tpl->setVar('input', '<input type="text" name="'.htmlentities($column).'" />');
				$tpl->parse('column');
			}
		$tpl->parse('add');
		return array('title'=>'IPDB :: Add '.$table['description'],
					 'content'=>$tpl->get());

	}


}


?>
