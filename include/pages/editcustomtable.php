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


class editcustomtable {


	public $error;


	public function get() {
		global $database, $session, $config;

		$table = $database->getCustomTable(request('table'));

		$tpl = new Template('customtableform.html');
		$tpl->setVar('tablename', $table['table']);
		foreach (array('text', 'integer') as $type) {
			$tpl->setVar('type', $type);
			$tpl->setVar('typeselected', $type==$table['type'] ? 'selected="selected"' : '');
			$tpl->parse('typeoption');
		}
		$tpl->setVar('description', $table['description']);
		$tpl->setVar('linkaddresschecked', $table['linkaddress'] ? 'checked="checked"' : '');
		$tpl->setVar('inoverviewchecked', $table['inoverview'] ? 'checked="checked"' : '');
		$tpl->setVar('action', 'changecustomtable');
		$tpl->setVar('buttontext', 'change');
		$table['columns'][''] = '';
		foreach ($table['columns'] as $column=>$ctype) {
			$tpl->setVar('columnname', $column);
			foreach (array('text', 'password', 'integer', 'boolean', 'url') as $type) {
				$tpl->setVar('type', $type);
				$tpl->setVar('typeselected', $type==$ctype ? 'selected="selected"' : '');
				$tpl->parse('columntypeoption');
			}
			$tpl->setVar('columnbuttontype', $column=='' ? 'add' : 'delete');
			$tpl->setVar('columnbuttontext', $column=='' ? 'add' : 'delete');
			$tpl->parse('column');
		}
		$tpl->parse('columns');
		$content = $tpl->get();

		return array('title'=>'IPDB :: Custom table '.request('table'),
					 'content'=>$content);
	}


}


?>
