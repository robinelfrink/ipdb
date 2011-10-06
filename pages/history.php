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

$Id$
*/


class history {


	public function get() {
		global $database, $config;
		$skin = new Skin($config->skin);
		$skin->setFile('history.html');

		$history = $database->getLog(request('historysearch'));

		$total = count($history);
		$start = request('pagenr', 1)-1;
		$max = 20;
		if ($total<($start*$max)) {
			$start = 0;
			request('pagenr', 1, true);
		}
		$history = array_slice($history, $start*$max, $max);

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
		foreach ($history as $entry) {
			$skin->setVar('timestamp', $entry['stamp']);
			$skin->setVar('username', $entry['username']);
			$skin->setVar('action', $entry['action']);
			$skin->setVar('oddeven', ' class="'.($even ? 'even' : 'odd').'"');
			$skin->parse('entry');
			$even = !$even;
		}

		$skin->setVar('historysearch', request('historysearch'));
		$skin->setVar('navigation', $navigation);
		$content = $skin->get();

		return array('title'=>'IPDB :: History',
					 'content'=>$content);
	}


}


?>
