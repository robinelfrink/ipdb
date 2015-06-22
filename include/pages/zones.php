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


class zones {


	public $error = null;


	public function get() {
		global $config, $database;

		$zones = $database->getZones();

		$tpl = new Template('zones.html');
		if (count($zones)>0) {
			foreach ($zones as $zone) {
				$tpl->setVar('zonename', $zone['name']);
				$tpl->setVar('description', $zone['description']);
				$tpl->setVar('editlink', me().'?page=editzone&amp;zone='.htmlentities($zone['name']));
				$tpl->setVar('deletelink', me().'?page=deletezone&amp;zone='.htmlentities($zone['name']));
				$tpl->parse('zone');
			}
			$tpl->parse('zones');
		} else {
			$tpl->parse('nozones');
		}

		$tpl->setVar('addlink', me().'?page=addzone');
		return array('title'=>'IPDB :: Zones',
					 'content'=>$tpl->get());

	}


}


?>
