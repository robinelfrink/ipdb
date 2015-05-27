<?php

/*
Copyright: Robin Elfrink <robin@15augustus.nl>

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


class customtables {


	public $error = null;


	public function get() {
		global $config, $database;

		$tables = $database->getCustomTables();

		$tpl = new Template('customtables.html');
		if (count($tables)>0) {
			foreach ($tables as $table) {
				$tpl->setVar('tablename', $table['table']);
				$tpl->setVar('inoverview', $table['inoverview'] ? 'yes' : 'no');
				$tpl->setVar('editlink', me().'?page=editcustomtable&amp;table='.htmlentities($table['table']));
				$tpl->setVar('deletelink', me().'?page=deletecustomtable&amp;table='.htmlentities($table['table']));
				$tpl->parse('table');
			}
			$tpl->parse('tables');
		} else {
			$tpl->parse('notables');
		}

		$tpl->setVar('addlink', me().'?page=addcustomtable');
		return array('title'=>'IPDB :: Custom tables',
					 'content'=>$tpl->get());

	}


}


?>
