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


class editcustomfield {


	public $error;


	public function get() {
		global $database, $session, $config;

		$field = $database->getCustomField(request('field'));

		$tpl = new Template('customfieldform.html');
		$tpl->setVar('fieldname', $field['field']);
		foreach (array('text', 'integer', 'boolean', 'url') as $type) {
			$tpl->setVar('type', $type);
			$tpl->setVar('typeselected', $type==$field['type'] ? 'selected="selected"' : '');
			$tpl->parse('typeoption');
		}
		$tpl->setVar('description', $field['description']);
		if ($field['type']=='url')
			$tpl->setVar('url', $field['url']);
		else
			$tpl->setVar('urlinactive', 'style="display: none;"');
		$tpl->setVar('inoverviewchecked', $field['inoverview'] ? 'checked="checked"' : '');
		$tpl->setVar('action', 'changecustomfield');
		$tpl->setVar('buttontext', 'change');
		$content = $tpl->get();

		return array('title'=>'IPDB :: Custom field '.request('field'),
					 'content'=>$content);
	}


}


?>
