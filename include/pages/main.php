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
		global $config, $database, $session;
		if (($node = request('node')) &&
			($node!='::/0') &&
			($data = $database->getNode($node))) {
			$skin = new skin($config->skin);
			$skin->setfile('netinfo.html');
			if (preg_match('/^[\.0-9]+\//', $data['node'])) {
				$skin->setvar('netmask', $database->getNetmask($data['node']));
				$skin->setvar('broadcast', $database->getBroadcast($data['node']));
				$skin->parse('ipv4');
			}
			if ($parent = $database->getParent($data['node'])) {
				$skin->setvar('parentnode', $parent['node']);
				$skin->parse('parent');
			}
			$skin->setvar('address', preg_replace('/\/.*/', '', $data['node']));
			$skin->setvar('bits', preg_replace('/.*\//', '', $data['node']));
			$children = $database->getChildren($data['node']);
			if (preg_match('/^[\.0-9]+\/32$/', $data['node']) ||
				preg_match('/^[:0-9a-f]+\/128$/', $data['node'])) {
				$skin->setvar('label', 'host '.preg_replace('/\/.*/', '', $data['node']));
			} else {
				$skin->setvar('label', 'network '.$data['node']);
				if (count($children)>0) {
					if (request('showunused')=='yes') {
						$skin->setvar('unusedyesno', 'no');
						$skin->setvar('unusedlabel', 'hide unused blocks');
					} else {
						$skin->setvar('unusedyesno', 'yes');
						$skin->setvar('unusedlabel', 'show unused blocks');
					}
					$skin->setvar('printtreelabel', 'print tree');
					$skin->setvar('node', $data['node']);
					$skin->parse('haschildren');
				}
			}
			$skin->setvar('description', htmlentities($data['description']));
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					$skin->setvar('field', $field);
					$value = $database->getField($field, $node);
					if ($details['url'])
						$skin->setvar('value', '<a href="'.sprintf($details['url'], $value).'">'.$value.'</a>');
					else
						$skin->setvar('value', $value);
					$skin->parse('extrafield');
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details)
					if ($details['linkaddress'] &&
						($item = $database->getItem($table, $node))) {
						$skin->setvar('table', $table);
						if ($details['type']!='password')
							$skin->setvar('item', $item['item'].' '.$item['description']);
						else
							$skin->setvar('item', $item['item'].' '.crypt($item['description']));
						$skin->parse('extratable');
					}

			$skin->setVar('address', preg_replace('/\/.*/', '', $data['node']));
			$skin->setVar('bits', preg_replace('/.*\//', '', $data['node']));

			$access = $database->getAccess($data['node'], $session->username);
			if (($session->username=='admin') || ($access['access']=='w')) {
				$links = '
<a href="'.me().'?page=addnode&amp;node='.$data['node'].'" remote="remote">add</a> |
<a href="'.me().'?page=deletenode&amp;node='.$data['node'].'" remote="remote">delete</a> |
<a href="'.me().'?page=changenode&amp;node='.$data['node'].'" remote="remote">change</a>';
				if ($session->username=='admin')
					$links .= ' |
	<a href="'.me().'?page=nodeaccess&amp;node='.$data['node'].'" remote="remote">access</a>';
			} else {
				$links = '';
			}
			$skin->setVar('links', $links);
			$content = $skin->get();
			$children = $database->getChildren($data['node'], false, request('showunused')=='yes');
			if (count($children)>0)
				$content .= $this->listchildren($children);
		} else {
			global $searchresult;
			$node = 'The World';
			$nodes = $searchresult ? $searchresult : $database->getChildren('::/0', false, request('showunused')=='yes');
			$content = $this->listchildren($nodes);
		}
		return array('title'=>'IPDB :: '.$node,
					 'content'=>$content);
	}


	private function listchildren($children) {
		global $config, $database;
		$skin = new Skin($config->skin);
		$skin->setFile('children.html');
		$even = true;
		foreach ($children as $child) {
			$skin->setVar('link', '?page='.(isset($child['unused']) ? 'addnode' : 'main').
								  '&node='.$child['node']);
			$skin->setVar('label', $child['node']);
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					if ($details['inoverview']) {
						$value = $database->getField($field, $child['node']);
						if (isset($details['url']))
							$skin->setVar('extrafield', '<a href="'.htmlentities(sprintf($details['url'], $value)).'">'.htmlentities($value).'</a>');
						else
							$skin->setVar('extrafield', htmlentities($value));
						$skin->parse('extrafielddata');
					}
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details) 
					 if (isset($details['inoverview']) && $details['inoverview'] &&
						 isset($details['linkaddress']) && $details['linkaddress']) {
						 $item = $database->getItem($table, $child['node']);
						 $skin->setVar('extratable', $item['item']);
						 $skin->parse('extratabledata');
					 }

			$skin->setVar('description', htmlentities($child['description']));
			$skin->setVar('class', isset($child['unused']) ? ' class="unused"' : '');
			$skin->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').
						  (isset($child['unused']) ? ' unused' : '').'"');
			$skin->parse('child');
			$even = !$even;
		}
		if (count($config->extrafields)>0)
			foreach ($config->extrafields as $field=>$details)
				if ($details['inoverview']) {
					$skin->setVar('extrafield', $field);
					$skin->parse('extrafieldheader');
				}
		if (count($config->extratables)>0)
			foreach ($config->extratables as $table=>$details)
				if (isset($details['inoverview']) && $details['inoverview'] &&
					isset($details['linkaddress']) && $details['linkaddress']) {
					$skin->setVar('extratable', $table);
					$skin->parse('extratableheader');
				}
		return $skin->get();
	}


}


?>
