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


class history {


	public $error = null;


	public function get() {
		global $database, $config;
		$tpl = new Template('history.html');

		$history = $database->getLog(request('historysearch'));

		$total = count($history);
		$pagenr = request('pagenr', 1);
		$maxperpage = 20;
		$totalpages = floor($total/$maxperpage)+1;
		if ($total<(($pagenr-1)*$maxperpage)) {
			$pagenr = 1;
			request('pagenr', 1, true);
		}

		if (($total/$maxperpage)<=1) {
			$navigation = '';
		} else {
			$navigation = 'Jump to page ';
			$history = array_slice($history, ($pagenr-1)*$maxperpage, $maxperpage);
			/* Build array of page numbers to show in navigation */
			$pages = array_unique(array_merge(
				range(1, min(3, $pagenr+3)), // first three pages
				range(max(1, $pagenr-3), min($pagenr+3, $totalpages)), // current page +/- 3
				range(max($pagenr, $totalpages-3), $totalpages) // last three pages
			), SORT_NUMERIC);
			$previouspage = 1;
			foreach ($pages as $page) {
				if ($page>($previouspage+1))
					$navigation .= '&hellip;&nbsp;';
				if ($page==$pagenr)
					$navigation .= '<b>'.$page.'</b>&nbsp;';
				else
					$navigation .= '<a href="'.me().'?pagenr='.$page.'">'.$page.'</a>&nbsp;';
				$previouspage = $page;
			}
		}

		$even = true;
		foreach ($history as $entry) {
			$tpl->setVar('timestamp', $entry['stamp']);
			$tpl->setVar('username', $entry['username']);
			$tpl->setVar('action', $entry['action']);
			$tpl->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').'"');
			$tpl->parse('entry');
			$even = !$even;
		}

		$tpl->setVar('historysearch', request('historysearch', ''));
		$tpl->setVar('navigation', $navigation);
		$content = $tpl->get();

		return array('title'=>'IPDB :: History',
					 'content'=>$content);
	}


}


?>
