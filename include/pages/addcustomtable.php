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


class addcustomtable {


	public $error;


	public function get() {
		global $database, $session, $config;

		$table = $database->getCustomTable(request('table'));

		$tpl = new Template('customtableform.html');
		foreach (array('text', 'integer') as $type) {
			$tpl->setVar('type', $type);
			$tpl->parse('typeoption');
		}
		$tpl->setVar('urlinactive', 'style="display: none;"');
		$tpl->setVar('action', 'addcustomtable');
		$tpl->setVar('buttontext', 'add');
		$tpl->hideBlock('columns');
		$content = $tpl->get();

		return array('title'=>'IPDB :: Add custom table',
					 'content'=>$content);
	}


}


?>
