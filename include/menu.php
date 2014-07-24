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


class Menu {


	public static function get() {

		global $config, $session;
		if ($session->authenticated) {
			$tpl = new Template('menu.html');
			$tpl->setVar('item', '<a href="'.me().'?page=main&amp;node=::/0">The World</a>');
			$tpl->parse('menuitem');
			if (count($config->extratables)>0) {
				$submenu = '<a href="#">Tables</a><ul>';
				foreach ($config->extratables as $table=>$details)
					$submenu .= '<li><a href="'.me().'?page=extratable&amp;table='.$table.
						'">'.$details['description'].'</a></li>';
				$submenu .= '</ul>';
				$tpl->setVar('item', $submenu);
				$tpl->parse('menuitem');
			}
			$tpl->setVar('item', '<a href="'.me().'?page=history">History</a>');
			$tpl->parse('menuitem');
			$tpl->setVar('item', '<a href="'.me().'?page=account">My account</a>');
			$tpl->parse('menuitem');
			if ($session->username=='admin') {
				$tpl->setVar('item', '<a href="'.me().'?page=users">Users</a>');
				$tpl->parse('menuitem');
			}
			$tpl->setVar('item', '<a href="'.me().'?page=login&amp;action=logout" remote="remote">Logout</a>');
			$tpl->parse('menuitem');
			$tpl->setVar('search', request('search'));
			return $tpl->get();
		}
		return '';
	}


}


?>
