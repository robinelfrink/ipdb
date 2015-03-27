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


class customtable {


	public $error = null;


	public function get() {
		global $config, $database;
		$table = $database->getCustomTable(request('table'));
		$type = $table['type'];
		$items = $database->getCustomTableItems(request('table'));
		$tpl = new Template('customtableitems.html');

		$items = $database->searchCustomTableItem(request('table'), request('customtablesearch'));
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
				$nodes = $database->getCustomTableItemNodes(request('table'), $item['item']);
				if (count($nodes)>0) {
					$comments .= 'Nodes:<p style="margin-left: 2em;">';
					foreach ($nodes as $node)
						$comments .= $node['node'].'<br />';
					$comments .= '</p>';
				}

				$tpl->setVar('item', $item['item']);
				$tpl->setVar('description', ($type=='password' ? crypt($item['description'], randstr(2)) : $item['description']));
				if (empty($comments))
					$tpl->setVar('comments', '');
				else
					$tpl->setVar('comments', '<span>'.$comments.'</span>');
				$tpl->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').'"');
				$tpl->parse('itemrow');
				$even = !$even;
			}
			$tpl->parse('items');
			$tpl->setVar('navigation', $navigation);
		} else {
			$tpl->parse('noitems');
		}

		$tpl->setVar('customtablesearch', request('customtablesearch'));
		$tpl->setVar('table', $table['description']);
		return array('title'=>'IPDB :: Table '.$table['description'],
					 'content'=>$tpl->get());

	}


}


?>
