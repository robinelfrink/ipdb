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


class main {


	public $error = null;


	public function get() {
		global $config, $database, $session, $searchresult;
		if ($searchresult) {
			$node = 'Search result';
			$content = $this->listchildren($searchresult);
		} else if (($node = request('node')) &&
			($node!='::/0') &&
			($data = $database->getNode($node))) {
			$tpl = new Template('netinfo.html');
			$tpl->setvar('node', $data['node']);
			if (preg_match('/^[\.0-9]+\//', $data['node'])) {
				$tpl->setvar('netmask', $database->getNetmask($data['node']));
				$tpl->setvar('broadcast', $database->getBroadcast($data['node']));
				$tpl->parse('ipv4');
			}
			if ($parent = $database->getParent($data['node'])) {
				$tpl->setvar('parentnode', $parent['node']);
				$tpl->parse('parent');
			}
			$tpl->setvar('address', preg_replace('/\/.*/', '', $data['node']));
			$tpl->setvar('bits', preg_replace('/.*\//', '', $data['node']));
			$children = $database->getChildren($data['node'], true, request('showunused')=='yes');
			if (preg_match('/^[\.0-9]+\/32$/', $data['node']) ||
				preg_match('/^[:0-9a-f]+\/128$/', $data['node'])) {
				$tpl->setvar('label', 'host '.preg_replace('/\/.*/', '', $data['node']));
			} else {
				$tpl->setvar('label', 'network '.$data['node']);
				if (count($children)>0) {
					$tpl->setvar('printtreelabel', 'print tree');
					$tpl->parse('printtreebutton');
					$tpl->setvar('unusedyesno', request('showunused')=='yes' ? 'no' : 'yes');
					$tpl->setVar('showunusedchecked', request('showunused')=='yes' ? 'checked="checked"' : '');
					$tpl->setvar('unusedlabel', 'show unused blocks');
					$tpl->parse('haschildren');
				}
			}
			$tpl->setvar('nodename', htmlentities($data['name']));
			$tpl->setvar('description', htmlentities($data['description']));
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					$tpl->setvar('field', $field);
					$value = $database->getField($field, $node);
					if ($details['url'])
						$tpl->setvar('value', '<a href="'.sprintf($details['url'], $value).'">'.$value.'</a>');
					else
						$tpl->setvar('value', $value);
					$tpl->parse('extrafield');
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details)
					if ($details['linkaddress'] &&
						($item = $database->getItem($table, $node))) {
						$tpl->setvar('table', $table);
						if ($details['type']!='password')
							$tpl->setvar('item', $item['item'].' '.$item['description']);
						else
							$tpl->setvar('item', $item['item'].' '.crypt($item['description']));
						$tpl->parse('extratable');
					}

			$tpl->setVar('address', preg_replace('/\/.*/', '', $data['node']));
			$tpl->setVar('bits', preg_replace('/.*\//', '', $data['node']));

			$access = $database->getAccess($data['node'], $session->username);
			if ($database->isAdmin($session->username) || ($access['access']=='w')) {
				$tpl->setVar('node', $data['node']);
				$tpl->parse('editbuttons');
				if ($database->isAdmin($session->username))
					$tpl->parse('adminbuttons');
			}
			$content = $tpl->get();
			if (count($children)>0)
				$content .= $this->listchildren($children);
		} else {
			$node = 'The World';
			$children = $database->getChildren('::/0', false);
			$tpl = new Template('world.html');
			$tpl->setVar('count', count($children));
			$content = $tpl->get().$this->listchildren($children);
		}
		return array('title'=>'IPDB :: '.$node,
					 'content'=>$content);
	}


	private function listchildren($children) {
		global $config, $database;
		$tpl = new Template('children.html');
		$even = true;
		foreach ($children as $child) {
			$tpl->setVar('link', '?page='.(isset($child['unused']) ? 'addnode' : 'main').
								  '&node='.$child['node']);
			$tpl->setVar('label', preg_match('/(\..+\/32|:.+\/128)$/', $child['node']) ? preg_replace('/\/.*/', '', $child['node']) : $child['node']);
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					if ($details['inoverview']) {
						$value = $database->getField($field, $child['node']);
						if (isset($details['url']))
							$tpl->setVar('extrafield', '<a href="'.htmlentities(sprintf($details['url'], $value)).'">'.htmlentities($value).'</a>');
						else
							$tpl->setVar('extrafield', htmlentities($value));
						$tpl->parse('extrafielddata');
					}
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details) 
					 if (isset($details['inoverview']) && $details['inoverview'] &&
						 isset($details['linkaddress']) && $details['linkaddress']) {
						 $item = $database->getItem($table, $child['node']);
						 $tpl->setVar('extratable', $item['item']);
						 $tpl->parse('extratabledata');
					 }

			$tpl->setVar('nodename', htmlentities($child['name']));
			$tpl->setVar('description', htmlentities($child['description']));
			$tpl->setVar('class', isset($child['unused']) ? ' class="unused"' : '');
			$tpl->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').
						  (isset($child['unused']) ? ' unused' : '').'"');
			$tpl->parse('child');
			$even = !$even;
		}
		if (count($config->extrafields)>0)
			foreach ($config->extrafields as $field=>$details)
				if ($details['inoverview']) {
					$tpl->setVar('extrafield', $field);
					$tpl->parse('extrafieldheader');
				}
		if (count($config->extratables)>0)
			foreach ($config->extratables as $table=>$details)
				if (isset($details['inoverview']) && $details['inoverview'] &&
					isset($details['linkaddress']) && $details['linkaddress']) {
					$tpl->setVar('extratable', $table);
					$tpl->parse('extratableheader');
				}
		return $tpl->get();
	}


}


?>
