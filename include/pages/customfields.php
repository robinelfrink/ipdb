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


class customfields {


	public $error = null;


	public function get() {
		global $config, $database;

		$fields = $database->getCustomFields();

		$tpl = new Template('customfields.html');
		if (count($fields)>0) {
			foreach ($fields as $field) {
				$tpl->setVar('fieldname', $field['field']);
				$tpl->setVar('type', $field['type']);
				$tpl->setVar('inoverview', $field['inoverview'] ? 'yes' : 'no');
				$tpl->setVar('editlink', me().'?page=editcustomfield&amp;field='.htmlentities($field['field']));
				$tpl->setVar('deletelink', me().'?page=deletecustomfield&amp;field='.htmlentities($field['field']));
				$tpl->parse('field');
			}
			$tpl->parse('fields');
		} else {
			$tpl->parse('nofields');
		}

		$tpl->setVar('addlink', me().'?page=addcustomfield');
		return array('title'=>'IPDB :: Custom fields',
					 'content'=>$tpl->get());

	}


}


?>
