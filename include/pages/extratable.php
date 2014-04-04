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


class extratable {


	public $error = null;


	public function get() {
		global $config, $database;
		$type = $config->extratables[request('table')]['type'];
		$items = $database->getExtra(request('table'));
		$skin = new Skin($config->skin);
		$skin->setFile('extratable.html');

		$items = $database->findExtra(request('table'), request('extrasearch'));
		if (is_array($items) && (count($items)>0)) {
			$total = count($items);
			$start = request('pagenr', 1)-1;
			$max = 40;
			if ($total<($start*$max)) {
				$start = 0;
				request('pagenr', 1, true);
			}
			$items = array_slice($items, $start*$max, $max);
			if (($total/$max)<=1) {
				$navigation = '';
			} else {
				$navigation = 'Jump to page ';
				for ($p = 1; ($p-1)<($total/$max); $p++)
					if (($p==1) ||
						(abs($start+1-$p)<4) ||
						(($p-1)==floor($total/$max)) ||
						(($p % (floor($total/$max)/10))==0))
						$navigation .= ($start==($p-1) ? $p : '<a href="'.me().'?pagenr='.$p.'">'.$p.'</a>').'&nbsp;';
					else
						$navigation .= '&hellip;&nbsp;';
				$navigation = preg_replace('/(&hellip;&nbsp;)+/', '&hellip;&nbsp;', $navigation);
			}
			$even = true;
			foreach ($items as $item) {
				$comments = '';
				if (strlen(trim($item['comments']))>0)
					$comments .= 'Comments:<p style="margin-left: 2em;">'.$item['comments'].'</p>';
				$nodes = $database->getItemNodes(request('table'), $item['item']);
				if (count($nodes)>0) {
					$comments .= 'Nodes:<p style="margin-left: 2em;">';
					foreach ($nodes as $node)
						$comments .= showip($node['address'], $node['bits']).'<br />';
					$comments .= '</p>';
				}

				$skin->setVar('item', $item['item']);
				$skin->setVar('description', ($type=='password' ? crypt($item['description'], randstr(2)) : $item['description']));
				if (empty($comments))
					$skin->setVar('comments', '');
				else
					$skin->setVar('comments', '<span>'.$comments.'</span>');
				$skin->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').'"');
				$skin->parse('itemrow');
				$even = !$even;
			}
			$skin->parse('items');
			$skin->setVar('navigation', $navigation);
		} else {
			$skin->parse('noitems');
		}

		$skin->setVar('extrasearch', request('extrasearch'));
		$skin->setVar('table', $config->extratables[request('table')]['description']);
		return array('title'=>'IPDB :: Table '.$config->extratables[request('table')]['description'],
					 'content'=>$skin->get());

	}


}


?>
