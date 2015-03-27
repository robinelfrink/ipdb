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


class users {


	public $error = null;


	public function get() {
		global $database, $config;
		$tpl = new Template('users.html');

		$users = $database->getUsers();

		$even = true;
		foreach ($users as $user) {
			$tpl->setVar('username', htmlentities($user['username']));
			$tpl->setVar('name', htmlentities($user['name']));
			$tpl->setVar('editlink', me().'?page=edituser&amp;user='.htmlentities($user['username']));
			$tpl->setVar('deletelink', me().'?page=deleteuser&amp;user='.htmlentities($user['username']));
			$tpl->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').'"');
			$tpl->parse('user');
			$even = !$even;
		}

		$content = $tpl->get();

		return array('title'=>'IPDB :: Users',
					 'content'=>$content);
	}


}


?>
