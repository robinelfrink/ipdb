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


class nodeaccess {


	public $error = null;


	public function get() {
		global $database, $config;
		$tpl = new Template('nodeaccess.html');

		$node = $database->getNode(request('node'));
		$access = $database->getAccess(request('node'));

		$userselect = '<select name="user">';

		foreach ($access as $username=>$useraccess) {
			$tpl->setVar('userlink', me().'?page=edituser&amp;user='.$username);
			$tpl->setVar('username', $username);
			$tpl->setVar('readonly_checked', $useraccess['access']=='r' ? ' checked="checked"' : '');
			$tpl->setVar('write_checked', $useraccess['access']=='w' ? ' checked="checked"' : '');
			$tpl->parse('user');
		}

		$userselect .= '
</select>';
		$tpl->setVar('users', $userselect);
		$tpl->setVar('node', request('node'));
		$content = $tpl->get();

		return array('title'=>'IPDB :: Access',
					 'content'=>$content);
	}


}


?>
