<?php
	if (!(defined("ROOT") && (ROOT != 'ROOT'))) die('oO');

	require_once 'core_page.php';
	require_once 'core_authors.php';

	class UpdatesFetcher {
		static function aquireData($params, &$title_opts) {
			$uid = uri_frag($params, 'channel');
			$author = uri_frag($params, 'author');
			$group = uri_frag($params, 'group');
			$page = uri_frag($params, 'page');
			$limit = uri_frag($params, 'newer-than', 24 * 7); // one week by def

			$pageIDs = null;
			switch (true) {
			case !!$uid:
				$u = User::get($uid);
				if (!$u->valid())
					return ('Unknown channel UID');

				$title_opts = array('title' => $u->readable(), 'channel' => '-channel', 'self' => "channel=$uid");
				$pageIDs = self::fetchForUID($uid, $pages, $limit);
				break;
			case !!$author:
				$aa = AuthorsAggregator::getInstance();
				$a = $aa->get($author, 'id as `0`, fio as `1`');
				if (intval($a[0]) != $author)
					return ('Unknown author UID');

				$title_opts = array('title' => $a[1], 'channel' => '-author', 'self' => "author=$author");
				$pageIDs = self::fetchForAuthor($author, $pages, $limit);
				break;
			case !!$group:
				$ga = GroupsAggregator::getInstance();
				$g = $ga->get($group, 'id as `0`, title as `1`');
				if (intval($g[0]) != $group)
					return ('Unknown group UID');

				$title_opts = array('title' => preg_replace('"^@"', '', $g[1]), 'channel' => '-group', 'self' => "group=$group");
				$pageIDs = self::fetchForGroup($group, $pages, $limit);
				break;
			case !!$page:
				$pa = PagesAggregator::getInstance();
				$p = $pa->get($page, 'id as `0`, title as `1`');
				if (intval($p[0]) != $page)
					return ('Unknown page UID');

				$title_opts = array('title' => $p[1], 'channel' => '-page', 'self' => "page=$page");
				$pageIDs = self::fetchForPage($page, $pages, $limit);
				break;
			default:
				$title_opts = array('title' => 'all', 'channel' => '-all', 'self' => "");
				$pageIDs = self::fetchWithFilter("1", $pages, $limit);
//				return 'Category unspecified';
//				$pageIDs = $h->fetchUpdates($uid, $pages);
			}

			$i = array();
			if ($pageIDs) {
				self::fetchData($pageIDs, $pageData, $authorData, $groupData);

				$d = array();
				foreach ($pages as $hID => $row) {
					$pageId = intval($row['page']);
					$author = intval($pageData[$pageId]['author']);
					$group = intval($pageData[$pageId]['group']);
					$alink = $authorData[$author]['link'];
					$link = $pageData[$pageId]['link'];
					$slink = str_replace('.shtml', '', $link);
					$delta = intval($row['size']) - intval($row['size_old']);
					$old_version_date = date('d-m-Y/H-i-s', $row['time_old']);
					$i[$hID] = array(
						'uid' => intval($row[0])
					, 'kind' => intval($row[1])
					, 'authorID' => $author
					, 'groupID' => $group
					, 'pageID' => $pageId
					, 'title' => $row['title']
					, 'author' => $author ? $authorData[$author]['fio'] : '&lt;неизвестный автор&gt;'
					, 'group' => $group ? $groupData[$group] : '&lt;неизвестная группа&gt;'
					, 'link' => "/pages/version/{$pageId}/$old_version_date"
					, 'samlib' => "$alink/$slink"
					, 'pubDate' => date('r', $row['time'])
					, 'guid' => md5($pageId . $row['time_old'] . $delta)
					);

					$diff = ($delta < 0) ? 'red' : 'green';

					if (!isset($d[$pageId]))
						$d[$pageId] = safeSubstr($row['description'], 500, 3);

					$i[$hID]['description'] = array(
						'title' => $i[$hID]['title']
					, 'group' => $i[$hID]['group']
					, 'author' => $i[$hID]['author']
					, 'size' => $row['size']
					, 'delta' => (($delta < 0) ? '' : '+') . $delta
					, 'diff' => $diff
					, 'samlib' => "$alink/$link"
					, 'link' => $i[$hID]['link']
					, 'link2' => "/pages/id/{$pageId}"
					, 'pubdate' => date('d.m.Y H:i', $row['time'])
					, 'description' => &$d[$pageId]
					);
				}
			}

			return $i;
		}

		static function fetchData($pageIds, &$pageData, &$authorData, &$groupData) {
			$pa = PagesAggregator::getInstance();
			$pd = $pa->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => '`id` in (' . join(',', $pageIds) . ')'
			, 'collumns' => '`id`, `link`, `author`, `group`'));

			$pageData = array();
			foreach ($pd['data'] as &$row)
				$pageData[intval($row['id'])] = $row;

			$aa = AuthorsAggregator::getInstance();
			$ad = $aa->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => '`id` in (' . join(',', array_unique(fetch_field($pageData, 'author'))) . ')'
			, 'collumns' => '`id`, `fio`, `link`'));

			$authorData = array();
			foreach ($ad['data'] as &$row)
				$authorData[intval($row['id'])] = $row;

			$ga = GroupsAggregator::getInstance();
			$gd = $ga->fetch(array('nocalc' => 1, 'desc' => 0
			, 'filter' => '`id` in (' . join(',', array_unique(fetch_field($pageData, 'group'))) . ')'
			, 'collumns' => '`id`, `title`'));

			$groupData = array();
			foreach ($gd['data'] as &$row)
				$groupData[intval($row['id'])] = $row['title'];
		}

		static function fetchForUID($uid, &$pages, $limit) {
			require_once 'core_history.php';
			$f = HistoryAggregator::getInstance()->fetchUpdates($uid);

			$pages = array();
			if (!!$f) {
				$f = array_slice($f, 0, $limit);

				foreach ($f as &$row)
					$pages[intval($row['page'])] = $row;

				return array_keys($pages);
			} else
				return null;
		}

		static function fetchForAuthor($author, &$pages, $limit) {
			return self::fetchWithFilter("p.`author` = $author", $pages, $limit);
		}
		static function fetchForGroup($group, &$pages, $limit) {
			return self::fetchWithFilter("p.`group` = $group", $pages, $limit);
		}
		static function fetchForPage($page, &$pages, $limit) {
			return self::fetchWithFilter("p.`id` = $page", $pages, $limit);
		}
		static function fetchWithFilter($filter = "1", &$pages, $limit) {
			require_once 'core_updates.php';
			$limit = time() - $limit * (60 * 60);
			$dbc = msqlDB::o();
			$f1 = array(UPKIND_SIZE, UPKIND_DELETE, UPKIND_ADDED, UPKIND_DELETED, UPKIND_RENAMED);
			$f1 = join(',', $f1);
			$s = $dbc->select('pages p, updates u'
			, "{$filter} and u.kind in ($f1) and u.page = p.id and u.time >= $limit order by u.time desc"
			, 'u.id as `0`, u.kind as `1`, p.id as `page`, p.`description`, p.`size`, u.`time`, p.`title`, u.`value` as `delta`, u.`time` as `time_old`'
			);
			$f = $dbc->fetchrows($s);
			$pages = array();
			$pageIDs = array();
			$sizes = array();
			foreach ($f as &$row) {
				$pageIDs[] = $pid = intval($row['page']);
				$sizes[$pid] = (isset($sizes[$pid]) ? $sizes[$pid] : intval($row['size'])) - intval($row['delta']);
				$row['size'] = $sizes[$pid] + intval($row['delta']);
				$row['size_old'] = $sizes[$pid];
				$pages[] = $row;
			}

			return array_unique($pageIDs);
		}

	}