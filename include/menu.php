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

		global $config, $database, $session;
		if ($session->authenticated) {
			$tpl = new Template('menu.html');
			$menu = array('The World'=>'page=main&node=::/0');
			$customtables = $database->getCustomTables();
			if (count($customtables)>0) {
				$menu['Tables'] = array();
				foreach ($customtables as $table)
					$menu['Tables'][$table['description']] = 'page=customtable&table='.$table['table'];
			}
			$menu['History'] = 'page=history';
			$menu['Options'] = array('My account'=>'page=account');
			if ($database->isAdmin($session->username)) {
				$menu['Options']['Users'] = 'page=users';
				$menu['Options']['Custom fields'] = 'page=customfields';
				$menu['Options']['Custom tables'] = 'page=customtables';
			}
			$menu['Logout'] = 'page=login&action=logout&remote=remote';
			return self::makeHtml($menu);
		}
		return '';
	}

	private static function makeHtml($menu, $level=1) {
		$tpl = new Template('menu.html');
		foreach ($menu as $title=>$details) {
			if (is_array($details))
				$tpl->setVar('item', '<a href="#" class="more" onclick="$(this).toggleClass(\'open\'); $(this).siblings(\'ul\').toggleClass(\'active\');">'.htmlentities($title).'</a>'.self::makeHtml($details, $level+1));
			else {
				$remote = '';
				parse_str($details, $vars);
				if (isset($vars['remote']) &&
					($vars['remote']=='remote')) {
					$remote = ' remote="remote"';
					unset($vars['remote']);
					$details = http_build_query($vars);
				}
				$tpl->setVar('item', '<a href="?'.$details.'"'.$remote.'>'.htmlentities($title).'</a>');
			}
			$tpl->parse('menuitem');
		}
		if ($level==1)
			$tpl->parse('searchform');
		return $tpl->get();
	}
			

}


?>
