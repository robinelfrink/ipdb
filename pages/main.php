<?php

/*
Copyright 2009 Introweb Nederland bv
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

$Id$
*/


class main {


	public $error = null;


	public function get() {
		global $config, $database, $session;
		if (($node = request('node')) && ($node>0) && ($data = $database->getAddress($node))) {
			$title = showip($data['address'], $data['bits']);
			$skin = new Skin($config->skin);
			$skin->setFile('netinfo.html');
			if (strcmp($data['address'], '00000000000000000000000100000000')<0) {
				$skin->setVar('netmask', ip2address(ipv4netmask($data['bits'])));
				$skin->setVar('broadcast', ip2address(broadcast($data['address'], $data['bits'])));
				$skin->parse('ipv4');
			}
			if ($data['parent']>0) {
				$parent = $database->getAddress($data['parent']);
				$skin->setVar('label', showip($parent['address'], $parent['bits']));
				$skin->setVar('link', '?node='.$parent['id']);
				$skin->parse('parent');
			}
			$skin->setVar('address', ip2address($data['address']));
			$skin->setVar('bits', (strcmp($data['address'], '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']));
			$children = $database->getTree($data['id']);
			if ($data['bits']==128) {
				$skin->setVar('label', 'host '.ip2address($data['address']));
			} else {
				$skin->setVar('label', 'network '.showip($data['address'], $data['bits']));
				if (count($children)>0) {
					if (request('showunused')=='yes') {
						$skin->setVar('unusedlink', me().'?page=main&node='.$data['id'].'&showunused=no');
						$skin->setVar('unusedlabel', 'hide unused blocks');
					} else {
						$skin->setVar('unusedlink', me().'?page=main&node='.$data['id'].'&showunused=yes');
						$skin->setVar('unusedlabel', 'show unused blocks');
					}
					$skin->parse('showunused');
				}
			}
			$skin->setVar('node', $data['id']);
			$skin->setVar('description', htmlentities($data['description']));
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					$skin->setVar('field', $field);
					$value = $database->getField($field, $node);
					if ($details['url'])
						$skin->setVar('value', '<a href="'.sprintf($details['url'], $value).'">'.$value.'</a>');
					else
						$skin->setVar('value', $value);
					$skin->parse('extrafield');
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details)
					if ($details['linkaddress']) {
						$item = $database->getItem($table, $data['id']);
						$skin->setVar('table', $table);
						if (($item['item']=='-') || ($details['type']!='password'))
							$skin->setVar('item', $item['item'].' '.$item['description']);
						else
							$skin->setVar('item', $item['item'].' '.crypt($item['description'], randstr(2)));
						$skin->parse('extratable');
					}

			$skin->setVar('address', ip2address($data['address']));
			$skin->setVar('bits', (strcmp($data['address'], '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']));

			$access = $database->getAccess($data['id'], $session->username);
			if (($session->username=='admin') || ($access['access']=='w')) {
				$links = '
<a href="'.me().'?page=addnode&address='.htmlentities($data['address']).'&bits='.htmlentities($data['bits']).'" remote="remote">add</a> |
<a href="'.me().'?page=deletenode&node='.$data['id'].'" remote="remote">delete</a> |
<a href="'.me().'?page=changenode&node='.$data['id'].'" remote="remote">change</a>';
				if ($session->username=='admin')
					$links .= ' |
	<a href="'.me().'?page=nodeaccess&node='.$data['id'].'" remote="remote">access</a>';
			} else {
				$links = '';
			}
			$skin->setVar('links', $links);
			$content = $skin->get();
			if (count($children)>0)
				$content .= $this->listchildren($data, $children);
		} else if (request('node')<0) {
			global $searchresult;
			$content = $this->listchildren(NULL, $searchresult);
		} else {
			$title = 'Main page';
			$tree = $database->getTree(0, false);
			if (count($tree)>0) {
				$skin = new Skin($config->skin);
				$skin->setFile('main.html');
				$even = true;
				foreach ($tree as $network) {
					$skin->setVar('link', ($network['id'] ? '?page=main&node='.$network['id'] : '?page=modify&action=add&address='.$network['address'].'&bits='.(strcmp($network['address'], '00000000000000000000000100000000')<0 ? $network['bits']-96 : $network['bits'])));
					$skin->setVar('label', showip($network['address'], $network['bits']));
					$skin->setVar('description', $network['description']);
					$skin->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').'"');
					$skin->parse('network');
					$even = !$even;
				}
				$skin->setVar('count', count($tree));
				$content = $skin->get();
			} else {
				$content = '
<p>You currently do not have any networks in your database.</p>';
			}
		}
		return array('title'=>'IPDB :: '.$title,
					 'content'=>$content);
	}


	private function listchildren($node, $children) {
		global $config, $database;
		$skin = new Skin($config->skin);
		$skin->setFile('children.html');
		$base = ($node ? $node['address'] : '00000000000000000000000000000000');;
		$networks = array();
		foreach ($children as $child) {
			if ((request('action')!='search') &&
				(request('showunused')=='yes') &&
				(strcmp($base, $child['address'])<0)) {
				$unused = findunused($base, $child['address']);
				if (is_array($unused) && (count($unused)>0))
					foreach ($unused as $network)
						$networks[] = $network;
			}
			$networks[] = $child;
			$base = plus(broadcast($child['address'], $child['bits']), '00000000000000000000000000000001');
		}
		if (request('showunused')=='yes') {
			$unused = findunused($base, plus(broadcast($node['address'], $node['bits']), '00000000000000000000000000000001'));
			if (is_array($unused) && (count($unused)>0))
				foreach ($unused as $network)
					$networks[] = $network;
		}
		$even = true;
		foreach ($networks as $network) {
			$skin->setVar('link', ($network['id'] ? '?page=main&node='.$network['id'] : '?page=addnode&address='.$network['address'].'&bits='.$network['bits'].'&node='.$node['id']));
			$skin->setVar('label', showip($network['address'], $network['bits']));
			if (count($config->extrafields)>0)
				foreach ($config->extrafields as $field=>$details) {
					if ($details['inoverview']) {
						$value = $database->getField($field, $network['id']);
						if (isset($details['url']))
							$skin->setVar('extrafield', '<a href="'.htmlentities(sprintf($details['url'], $value)).'">'.htmlentities($value).'</a>');
						else
							$skin->setVar('extrafield', htmlentities($value));
						$skin->parse('extrafielddata');
					}
				}
			if (count($config->extratables)>0)
				foreach ($config->extratables as $table=>$details) 
					 if ($details['inoverview'] && $details['linkaddress']) {
						 $item = $database->getItem($table, $network['id']);
						 $skin->setVar('extratable', $item['item']);
						 $skin->parse('extratabledata');
					 }

			$skin->setVar('description', ($network['id'] ? htmlentities($network['description']) : 'unused'));
			$skin->setVar('class', ($network['id'] ? '' : ' class="unused"'));
			$skin->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').
						  ($network['id'] ? '' : ' unused').'"');
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
				if ($details['inoverview'] && $details['linkaddress']) {
					$skin->setVar('extratable', $table);
					$skin->parse('extratableheader');
				}
		return $skin->get();
	}


}


?>
