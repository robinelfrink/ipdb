<?php

/*  Copyright 2009  Robin Elfrink  (email : robin@15augustus.nl)

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
		global $config, $database;
		if ($node = request('node')) {
			$data = $database->getAddress($node);
			$title = ip2address($data['address']).'/'.
				(strcmp($data['address'], '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']);
			$skin = new Skin($config->skin);
			$skin->setFile('netinfo.html');
			$skin->setBlock('ipv4');
			$skin->setBlock('parent');
			if (strcmp($data['address'], '00000000000000000000000100000000')<0) {
				$skin->setVar('netmask', ip2address(ipv4netmask($data['bits'])));
				$skin->setVar('broadcast', ip2address(broadcast($data['address'], $data['bits'])));
				$skin->parse('ipv4');
			}
			if ($data['parent']>0) {
				$parent = $database->getAddress($data['parent']);
				$skin->setVar('label', ip2address($parent['address']).'/'.
							  (strcmp($parent['address'], '00000000000000000000000100000000')<0 ? $parent['bits']-96 : $parent['bits']));
				$skin->setVar('link', '?node='.$parent['id']);
				$skin->parse('parent');
			}
			if ($data['bits']==128) {
				$skin->setVar('label', 'host '.ip2address($data['address']));
				$skin->setVar('address', ip2address($data['address']));
				$skin->hideBlock('showunused');
			} else {
				$skin->setVar('label', 'network '.ip2address($data['address']).'/'.
							  (strcmp($data['address'], '00000000000000000000000100000000')<0 ? $data['bits']-96 : $data['bits']));
				$skin->setVar('address', ip2address($data['address']));
				$skin->setBlock('showunused');
				if (request('showunused')=='yes') {
					$skin->setVar('unusedlink', me().'?node='.$data['id'].'&showunused=no');
					$skin->setVar('unusedlabel', 'hide unused blocks');
				} else {
					$skin->setVar('unusedlink', me().'?node='.$data['id'].'&showunused=yes');
					$skin->setVar('unusedlabel', 'show unused blocks');
				}
				$skin->parse('showunused');
			}
			$skin->setVar('node', $data['id']);
			$skin->setVar('description', $data['description']);
			$content = $skin->get();
			if ($children = $database->getTree($data['id'])) {
				$skin->setFile('children.html');
				$skin->setBlock('child');
				$base = $data['address'];
				$networks = array();
				foreach ($children as $child) {
					if ((request('showunused')=='yes') &&
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
					$unused = findunused($base, plus(broadcast($data['address'], $data['bits']), '00000000000000000000000000000001'));
					if (is_array($unused) && (count($unused)>0))
						foreach ($unused as $network)
							$networks[] = $network;
				}
				foreach ($networks as $network) {
					$skin->setVar('link', ($network['id'] ? '?page=main&node='.$network['id'] : '?page=modify&action=add&address='.$network['address'].'&bits='.(strcmp($network['address'], '00000000000000000000000100000000')<0 ? $network['bits']-96 : $network['bits'])));
					$skin->setVar('label', ip2address($network['address']).
								  ($network['bits']==128 ? '' : '/'.(strcmp($network['address'], '00000000000000000000000100000000')<0 ? $network['bits']-96 : $network['bits'])));
					$skin->setVar('description', ($network['id'] ? htmlentities($network['description']) : 'unused'));
					$skin->setVar('class', ($network['id'] ? '' : ' class="unused"'));
					$skin->parse('child');
				}
				$content .= $skin->get();
			}
		} else {
			$title = 'Main page';
			$tree = $database->getTree(0, false);
			if (count($tree)>0) {
				$skin = new Skin($config->skin);
				$skin->setFile('main.html');
				$skin->setBlock('network');
				foreach ($tree as $network) {
					$skin->setVar('label', ip2address($network['address']).'/'.
								  (strcmp($network['address'], '00000000000000000000000100000000')<0 ? $network['bits']-96 : $network['bits']));
					$skin->setVar('description', $network['description']);
					$skin->parse('network');
				}
				$skin->setVar('count', count($tree));
				$content = $skin->get();
			} else {
				$content = '
<p>You currently do not have any networks in your database.</p>';
			}
		}
		return array('title'=>$title,
					 'content'=>$content);
	}


}


?>
