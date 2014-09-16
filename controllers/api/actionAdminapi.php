<?php

	require_once 'actionClientapi.php';

	class actionAdminapi extends actionClientapi {

		function methodAuthorsToUpdate($params, &$error, &$message) {
			$force = !!uri_frag($params, 'force');
			$all = !!uri_frag($params, 'all');

			require_once 'core_history.php';
			require_once 'core_updates.php';

			$u = new AuthorWorker();
			$h = HistoryAggregator::getInstance();

			$a = $h->authorsToUpdate(0, $force, $all);
			return $a;
		}
		function methodAuthorUpdate($params, &$error, &$message) {
			require_once 'core_history.php';
			require_once 'core_updates.php';
			$id = uri_frag($params, 'id');
			$u = new AuthorWorker();
			$checked = $u->check($id);
			if ($error = $message = (isset($checked['error']) ? $checked['error'] : false))
				return;

			$gu = array();
			$h = HistoryAggregator::getInstance();
			$ga = GroupsAggregator::getInstance();
			$g = $h->authorGroupsToUpdate($id, true);
			foreach ($g as $gid) {
				$updatedGroup = $u->checkGroup($gid);
				if (count($updatedGroup)) {
					$d = $ga->get($gid, 'title');
					$gu[] = array('group-id' => $gid, 'group-title' => $d['title'], 'updates' => $updatedGroup);
				}
			}
			if (count($gu))
				$checked['groups-updates'] = $gu;
			return $checked;
		}
		function methodUpdatesCalcFreq($params, &$error, &$message) {
			require_once 'core_history.php';
			$h = HistoryAggregator::getInstance();
			$h->calcCheckFreq();
		}
		function methodLoadUpdates($params, &$error, &$message) {
			require_once 'core_updates.php';

			$u = new AuthorWorker();
			$left = $u->serveQueue(uri_frag($_REQUEST, 'left', 5), 20);
			return $left;
		}

		function methodDropUpdates($params, &$error, &$message) {
			require_once 'core_queue.php';

			$page = uri_frag($params, 'page');
			$ua = QueueAggregator::getInstance();
//			$ua->dbc->debug = 1;
			$data = $ua->fetch(array('filter' => $page ? "`page` = $page" : " group by `page`", 'collumns' => 'id, page, count(`page`) as `c`'));
			$cts = fetch_field($data['data'], 'page', 'id');
			$ids = array_keys($cts);
			if ($page)
				$ua->delete($ids);
			else {
				$uts = fetch_field($data['data'], 'c', 'page');
				$rts = array();
				foreach ($uts as $page => $count)
					if ($count > 1)
						$rts[] = $page;
				return array($cts, $uts, $rts);
			}
//				debug2(array($cts, $data));
			return $cts;
		}


		function processDir($root, $pageID) {
			$dir = "$root{$pageID}";
			if (@file_exists("$dir/Array.html")) {
				$pa = PagesAggregator::getInstance();
				$p = $pa->get($pageID, '`id`, `author`');
				if (intval($p['id']) <> $pageID)
					throw new Exception('Page with ID $pageID not existing');

				$aa = AuthorsAggregator::getInstance();
				$a = $aa->get($authorID = intval($p['author']), '`id`');
				if (intval($a['id']) <> $authorID)
					throw new Exception('Author with ID $authorID not existing');

				$aa->update(array('time' => 0), $authorID, true);

				@unlink("$dir/Array.html");
				@unlink("$dir/last.html");
				return true;
			}

			return false;
		}
		function methodDropFailes($params, &$error, &$message) {
			$r = array();
			$root = URIStream::real('cms://cache/pages/');
			$d = dir($root);
			while (($entry = $d->read()) !== false)
				if ($this->processDir($root, intval($entry)))
					$r[] = $entry;

			return $r;
		}

		/* ==================================================== */

		function methodCompositionState($params, &$error, &$message) {
			$pageID = uri_frag($params, 'page');

			if ($error = !$pageID) {
				return $message = 'Page ID not specified';
			}

			$pca = PagesCompositionAggregator::getInstance();
			$composition = $pca->inComposition($pageID);

			return $composition;
		}

		function methodCompositionRelated($params, &$error, &$message) {
			$pid = uri_frag($params, 'page');

			if ($error = !$pid) {
				return $message = 'Page ID not specified';
			}

			$pa = PagesAggregator::getInstance();
			$page = $pa->get($pid, '`id`, `author`');

			if ($error = ($pid != intval($page['id'])))
				return $message = "Page {@PAGE#$pid} not found";

			$pages = $pa->fetch(array('nocalc' => true, 'desc' => 0
				, 'filter' => "`author` = {$page['author']}"
				, 'collumns' => '`id`'
			));
			$pages = fetch_field($pages['data'], 'id');

			$pca = PagesCompositionAggregator::getInstance();
			$compositions = array();

			foreach ($pages as $id) {
				$data = $pca->inComposition($id);
				foreach ($data as $row) {
					$cid = intval($row['composition']);
					$compositions[$cid] = $row;
				}
			}

			return $compositions;
		}

		function methodCompositionPages($params, &$error, &$message) {
			$id = uri_frag($params, 'id');

			if ($error = !$id) {
				return $message = 'Composition ID not specified';
			}

			$pca = PagesCompositionAggregator::getInstance();
			$pages = $pca->fetchPages($id);

			return $pages;
		}

		function methodCompositionAdd($params, &$error, &$message) {
			$pages = uri_frag($params, 'pages', array(), false);
			$comID = uri_frag($params, 'composition');

			if ($error = !count($pages)) {
				return $message = 'Composition pages not specified';
			}

			$pca = PagesCompositionAggregator::getInstance();

			if (!$comID) {
				$title = uri_frag($params, 'title', null, false);
				if ($error = !$title)
					return $message = 'Composition pages not specified';

				$comID = $pca->genComposition($pages);
				$pca->update(array('title' => $title), $comID, true);
			} else {
				foreach ($pages as $pageID)
					$pca->compose($comID, $pageID);
			}

			return $comID;
		}

		function methodCompositionRemove($params, &$error, &$message) {
			$pages = uri_frag($params, 'pages', array(), false);
			$comID = uri_frag($params, 'composition');

			if ($error = !$comID) {
				return $message = 'Composition ID not specified';
			}

			if ($error = !count($pages)) {
				return $message = 'Composition pages not specified';
			}

			$pca = PagesCompositionAggregator::getInstance();
			return $pca->remove($comID, $pages);
		}

		function methodCompositionOrder($params, &$error, &$message) {
			$comID = uri_frag($params, 'composition');
			$pageID = uri_frag($params, 'page');
			$dir = uri_frag($params, 'direction');

			if ($error = !$comID)
				return $message = 'Composition ID not specified';

			if ($error = !$pageID)
				return $message = 'Page ID not specified';

			if ($error = (abs($dir) != 1))
				return $message = 'Wrond reordering direction';

			$pca = PagesCompositionAggregator::getInstance();
			$oldIdx = $pca->orderInComposition($comID, $pageID);
			if ($error = ($oldIdx === false))
				return $message = 'Page doesn\'t contained in specified composition';

			$newIdx = $oldIdx + $dir;
			return ($newIdx < 0) ? false : $pca->compose($comID, $pageID, $newIdx);
		}

		/* ==================================================== */

		function methodUser($params, &$error, &$message) {
			$uid = uri_frag($params, 'uid');
			$u = array();
			$fields = array(
				User::COL_LOGIN,
				User::COL_ACL,
				User::COL_LANG,
				User::COL_NAME
			);

			$error = !($uid && ($data = User::get($uid)) && ($data->ID()));
			if (!$error) {
				foreach ($fields as $field)
					$u[$field] = $data->_get($field);


				$u[User::COL_LANG] = Loc::$LOC_ALL[$u[User::COL_LANG]];

				return $u;
			} else
				$message = 'User with specified ID not found';
		}

	}
