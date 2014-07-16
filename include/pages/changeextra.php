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


class changeextra {


	public $error = null;


	public function get() {
		global $config, $error, $database;
		if (!$item = $database->getExtra(request('table'), request('item'))) {
			request('item', null, true);
			$error = 'Item does not exist.';
			require_once dirname(__FILE__).'/extratable.php';
			return extratable::get();
		}
		$skin = new Skin($config->skin);
		$skin->setFile('extradetails.html');
		if (isset($config->extratables[request('table')]['columns']) &&
			is_array($config->extratables[request('table')]['columns']) &&
			count($config->extratables[request('table')]['columns']))
			foreach ($config->extratables[request('table')]['columns'] as $column=>$type) {
				$skin->setVar('name', $column);
				if ($type=='password')
					$skin->setVar('input', '<input type="password" name="'.htmlentities($column).'" />');
				else if (isset($item[$column]))
					$skin->setVar('input', '<input type="text" name="'.htmlentities($column).'" value="'.htmlentities($item[$column]).'" />');
				else
					$skin->setVar('input', '<input type="text" name="'.htmlentities($column).'" value="" />');
				$skin->parse('column');
			}
		$skin->setVar('item', htmlentities(request('item')));
		$skin->setVar('description', htmlentities($item['description']));
		$skin->setVar('comments', htmlentities($item['comments']));
		$skin->setVar('table', htmlentities($config->extratables[request('table')]['description']));
		$skin->parse('change');
		return array('title'=>'IPDB :: Change '.$config->extratables[request('table')]['description'],
					 'content'=>$skin->get());

	}


}


?>
