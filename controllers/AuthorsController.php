<?php
	require_once 'core_bridge.php';

	require_once 'core_authors.php';
	require_once 'core_queue.php';
	require_once 'core_page.php';

	require_once 'AggregatorController.php';

	class AuthorsController extends AggregatorController {
		protected $_name = 'authors';

		var $EDIT_STRINGS = array('fio', 'link');
		var $EDIT_FILES   = array();
		var $EDIT_REQUIRES= array('link');
		var $ID_PATTERN = '
				<div class="panel">{%fio}{%time}</div>
				<div class="text">
					{%link}<br />
					<a href="/pages?author={%id}">{%pages}</a><br />
					<a href="/{%root}/check/{%id}">{%checkupdates}</a>
				</div>
';
		var $LIST_ITEM  = '
					<div class="cnt-item">
						<div class="title">
							<span class="head">
								<a href="/{%root}/id/{%id}">{%fio}</a>
								<span class="pull_right">[<a href="/updates/trace/{%id}">{%trace}</a>]</span>
							</span>
							<span class="link samlib"><a href="http://samlib.ru/{%link}">/{%link}</a></span>
						</div>
						<div class="text">{%moder}
							<a href="/pages?author={%id}">{%pages}</a> <a href="/{%root}/id/{%id}">{%detail}</a>
						</div>
					</div>
';

		function getAggregator($p = 0) {
			switch ($p) {
			case 0: return AuthorsAggregator::getInstance();
			case 1: return GroupsAggregator::getInstance();
			case 2: return QueueAggregator::getInstance();
			case 3: return PagesAggregator::getInstance();
			}
		}

		public function makeItem(&$aggregator, &$row) {
			html_escape($row, array('fio', 'link'));
			$row['trace'] = Loc::lget('trace');
			$row['pages'] = Loc::lget('pages');
			$row['detail'] = Loc::lget('detail');
			return patternize($this->LIST_ITEM, $row);
		}

		public function makeIDItem(&$aggregator, &$row) {
			View::addKey('title', $row['fio']);
			html_escape($row, array('fio', 'link'));
			$row['pages'] = Loc::lget('pages');
			$row['checkupdates'] = Loc::lget('checkupdates');
			return patternize($this->ID_PATTERN, $row);
		}

		public function actionCheck($r) {
			$id = intval($r[0]);
			if (!$id)
				return false;

			$g = $this->getAggregator();
			$a = $g->get($id);
			preg_match('/^[\/]*(.*?)[\/]*$/', $a['link'], $link);
			$link = $link[1] . '/';
			$url  = 'http://samlib.ru/' . $link;
			$hash = base64_encode($link);
			$store = SUB_DOMEN . '/cache/' . $hash;

			if (!is_file($store)) {
				$html = url_get_contents($url);
				if ($html !== false)
					file_put_contents($store, gzcompress/**/($html));
			} else
				$html = gzuncompress/**/(file_get_contents($store));

			$html = mb_convert_encoding($html, 'UTF-8', 'CP1251');

			$data = $this->parseAuthor($html);
			$groups = $data[0];
			$inline = $data[1];
			$links = $data[2];
			$fio = $data[3];
			if ($fio != $a['fio'])
				$g->update(array('fio' => $fio), $id);



//			debug($groups);
//			debug($inline);
//			debug($links);

			// check updates
			$ga = $this->getAggregator(1);
			$d = $ga->fetch(array('filter' => '`author` = ' . $id, 'nocalc' => 1, 'desc' => 0, 'collumns' => '`id`, `group`, `title`'));
			$gii = array();
			$gt = array();
			if ($d['total'])
				foreach ($d['data'] as $row) {
					$gt = array_merge($gt, explode('@', $row['title']));
					$gii[intval($row['id'])] = array(0 => $row['group'], 1 => $row['title']);
				}
//			debug($gt);

			$g = array();
			$gi = array();
//				debug($gii);
			foreach ($groups as $idx => &$_) {
//				debug($_);
				$g[$idx] = $_[1];
				foreach ($gii as $gid => $d)
					if (($_[1] == $d[1]) && ($d[0] == $_[0]))
						$gi[$gid] = $idx;
			}


//				debug($g);

			$new = array_diff($g, $gt);
//			debug($new);
			if (count($new))
				foreach ($new as $idx => $title) {
//					debug($groups[$idx], $title);
					$gid = $ga->add(array(
						'author' => $id
					, 'group' => $groups[$idx][0]
					, 'link' => $inline[$idx]
					, 'title' => $title
					, 'description' => $groups[$idx][2]
					));
					$gi[$gid] = $idx;
				}
			// ok, groups updated

//			debug($gi, 'gi');

			$check = array();
			// is there anything to check?
			foreach ($links as $idx => &$block)
				foreach ($block as $link => &$data)
					if ($data[2] > 0) // size > 0 ?
						$check[$link] = $data;

			if (count($check)) { // ok, there are smth to check
				$diff = array();
				$pa = $this->getAggregator(3);
				$d = $pa->fetch(array(
					'filter' => '`author` = ' . $id . ' and (`link` in ("' . join('", "', array_keys($check)) . '"))'
				, 'nocalc' => 1, 'desc' => 0
				, 'collumns' => '`id`, `link`, `size`'
				));
				if ($d['total']) // there are smth in db, check it if size diff
					foreach ($d['data'] as $row) {
						$data = $check[$link = $row['link']];
						unset($check[$link]); // don't check it later in this function

						if (intval($row['size']) <> $data[2])
							$diff[$row['id']] = $link; // check it
					}

				foreach ($check as $link => &$data) { // new pages
					$idx = $data[0];
					$gid = array_search($idx, $gi);
					$pid = $pa->add(array(
						'author' => $id
					, 'group' => $gid
					, 'link' => $link
					, 'title' => htmlspecialchars($data[1])
					, 'description' => htmlspecialchars($data[3])
					, 'size' => 0 // later it will be updated with diff checker
					));
					$diff[$pid] = $link;
				}

				if (count($diff))
					$this->queuePages($id, $diff);
			}
		}

		function queuePages($author, $pages) {
			$a = $this->getAggregator(0);
			$d = $a->get($author);
			$q = $this->getAggregator(2);

			$worker_stamp = md5(uniqid(rand(),1));
			$update = $q->queue(array_keys($pages), $worker_stamp);
			$idx = array_keys($pages);
			if (count($update)) {
				$diff = array_intersect($idx, $update);
				echo "Some page already queued for compare, check them later:<br />";
				foreach ($diff as $page) {
					$link = $pages[$page];
					echo "<a href=\"http://samlib.ru/$d[link]/$link\">$link</a><br />";
				}

				$idx = array_diff($idx, $update);
			}

			if (!count($idx))
				return;

			require_once 'core_comparator.php';
			$c = new PageComparator();
			$pa = $this->getAggregator(3);
			foreach ($idx as $page) {
				$size = $c->compare($page, $d['link'] . '/' . $pages[$page]);
				$s1 = $size[0];
				$s2 = $size[1];
				$pa->update(array('size' => round($s2 / 1024)), $page);
				$q->dbc->delete($q->TBL_DELETE, '`page` = ' . $page);
			}
		}

		function parseAuthor($html) {
			$p1 = '- Блок ссылок на произведения -';
			$p2 = '- Подножие -';
			$i1 = strpos($html, $p1);
			$i2 = strpos($html, $p2, $i1);
			$m = substr($html, $i1, $i2 - $i1);
			$m = preg_replace('/(<!-+|--+>)/', '', $m);

			preg_match_all('/<a name="?gr(\d+)"?>(.*?)(<gr|<\/a)/i', $m, $g, PREG_SET_ORDER);
			$groups = array();
			$inline = array();
			foreach($g as $match) {
				$id = intval($match[1]);

				$t = preg_replace('/((<[^>]+>)|([<\/]))/', '', $match[2]);
				$t = mb_ucfirst(preg_replace('/:$/', '', $t));
				$groups[] = array($id, $t);

				if (preg_match('/a href="?([^ ">]+)"?/i', $match[2], $t))
					$inline[count($groups) - 1] = $t[1];
			}

//			debug($groups);
//			debug($inline);

			$pieces = explode('<a name=gr', $m);
			unset($pieces[0]);

			$links = array();
			foreach ($groups as $idx => &$g) {
				$id = $g[0];
				$block = array_shift($pieces);
				preg_match('/<br>(.*?)<(dl|\/small)>/ism', $block, $t1);
				$g[2] = trim(preg_replace(array('/<br( \/)?>/i', '/<[^>]+>/'), array(PHP_EOL, ''), $t1[1]));
//				debug($g[2]);
				preg_match_all('/<dl>(.*?)<\/dl>/i', $block, $t);
//				debug($t, htmlspecialchars($block));
				foreach ($t[1] as $link) {
					preg_match('/<a href="?([^ >"]+)"?>(.*?)<\/a>[^<]*<b>(.*?)k<\/b>/i', $link, $t2);
					preg_match('/<dd>(.*)/i', $link, $t3);
//					debug($t2, htmlspecialchars($link));
					$links[$id][$t2[1]] = array($idx, strip_tags($t2[2]), intval($t2[3]), strip_tags($t3[1], '<br><p>'));
				}
//				debug($links[$id], "Group #$id");
			}
//			debug($m);
//			debug($html);
			preg_match('/<h3>(.*?)<br/i', $html, $t);
			$t = preg_replace('/:$/', '', $t[1]);
			return array($groups, $inline, $links, $t);
		}
	}
?>