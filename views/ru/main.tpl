<?php
	$p1 = '<div class="cnt-item">
					<div class="title update">
						<span class="head"><a href="/authors/id/{%id}">{%fio}</a></span>
						<span class="head small">
							<span class="past_since" style="float: right">Обновление:&nbsp;
								<span class="time" style="min-width: 60px;width: 70px;">{%delta} назад</span>
								<span class="time" style="min-width: 60px;width: 70px;"> (проверка через {%after})</span>
							</span>
						</span>
					</div>
				</div>
				';
	$p2 = '<div class="cnt-item">
					<div class="title update">
						<span class="head"><a href="/pages/version/{%id}">{%title}</a></span>
						<span class="head small">
							<span class="link past_since">Прошло с изменения страницы:&nbsp;<span class="link time" style="min-width: 60px;width: 70px;">{%delta}</span></span>
						</span>
					</div>
				</div>
				';
	$p3 = '
					<div class="title update dotted">
						<span class="head">
							{%inline}<a href="/pages?group={%group}" class="nowrap">{%group_title}</a>{%title}
							{%moder}
						</span>
						<span class="head small break">
							<span class="delta {%color}"><b>{%delta}</b></span>
							<span class="link time">{%time}</span>
						</span>
					</div>
				';
	$p4 = '<div class="title">
						<span class="head"><a href="/authors/id/{%author}" class="nowrap">{%fio}</a></span>
					</div>';
	$p5 = ':
							<a href="/pages/version/{%id}" class="nowrap">{%title}</a>{%hint}';
	$p6 = '<span class="pull_right" style="float: right">[ <a href="/updates/delete/{%update}">{%delete}</a> ]</span>';

	$updatelist = post('action') == 'updatelist';
	$pagesize = $updatelist ? 100 : 20;
	$page = post('page');
	$page = $page ? explode('/', $page) : array(0, 1);
	$page = $page[1];

	require_once 'core_pagecontroller_utils.php';
	require_once 'core_page.php';
	$p = PagesAggregator::getInstance();
	require_once 'core_updates.php';
	$a = UpdatesAggregator::getInstance();
	require_once 'core_history.php';
	$h = HistoryAggregator::getInstance();

	$d = $a->getUpdates($page, $pagesize);
	$u = array();
	$c3 = 0;
	$nn = count($d['data']);

	$day = 60 * 60 * 24.0;
	$config = Config::read('INI', 'cms://config/config.ini');
	$offset = $config->get('main.time-offset');
	$offset = explode('=', $offset);
	$offset = $offset[0] * 60 * 60;
	$now = time() + $offset;

	$now = intval(floor($now / $day));
	$timeslice = array();
	$pages = array();
	if ($d['total']) {
		$sizes = array();
		foreach ($d['data'] as &$row) {
			$pages[] = intval($row['id']);
			$time = intval($row['time']) + $offset;
			$days = intval(floor($time / $day));
			$delta = date('d', intval($row['time'])) - date('d', intval($days * $day));
			$time = $days + $delta;

			if ($now - $time > 30) $time = $now - 31;
			$timeslice[$time][] = &$row;
			if ($row['kind'] == UPKIND_SIZE)
				$sizes[intval($row['id'])] = 0;
		}

		$q = $p->get(array_keys($sizes), 'id, size');
		foreach ($q as $size)
			$sizes[intval($size['id'])] = intval($size['size']);

		$uid = User::get()->ID();
		$traces = array();
		if ($uid) {
			$f = $h->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => "`user` = $uid and `page` in (" . join(",", $pages) . ")", 'collumns' => '`page`, `trace`'));
			if ($f['total'])
				foreach ($f['data'] as &$row)
					$traces[intval($row['page'])] = intval($row['trace']);
		}


//		debug($sizes);
//		debug($timeslice);

		$isModer = User::ACL() >= ACL::ACL_MODER;
		foreach ($timeslice as $time => $dayUpdates) {
			$t = array();
			$daysDelta = $now - $time;
			$nearPast = $daysDelta <= 30;
			$daysAgo = daysAgo($daysDelta) . ($nearPast ? ', ' . date('d.m.Y', $dayUpdates[0]['time']) : '');
			$timestamp = $nearPast ? 'H:i' : 'd.m.Y H:i';
			foreach ($dayUpdates as &$row) {
				$c3++;
				$val = $row['value'];
//				if (!$val) continue;
				$row['time'] = date($timestamp, intval($row['time']));
				$row['inline'] = (strpos($row['group_title'], '@') !== false) ? '<span class="inline-mark">@</span>' : '';
				$row['group_title'] = str_replace(array('@ ', '@'), '', $row['group_title']);
				$row['title'] = patternize($p5, $row);
				$pageid = intval($row['id']);
				$trace = ($uid && isset($traces[$pageid])) ? $traces[$pageid] : -1;
				$hint = $pageid ? PageUtils::traceMark($uid, $trace, $pageid, $row['author']) : '';
				$hint = '<span style="position: absolute; margin-left: 10px;">' . $hint . '</span>';
				$row['hint'] = $hint;
				$row['delete'] = Loc::lget('delete');
				$change = 0; // don't move, UPKIND_SIZE/ADD/DELETE depends on this
				switch ($row['kind']) {
				case UPKIND_GROUP:
					$row['delta'] = patternize('перенесено из <a href="/pages?group={%old_id}">{%old_title}</a>', $row);
					$row['color'] = 'blue';
					break;
				case UPKIND_DELETED_GROUP:
					$row['title'] = '';
					$row['delta'] = 'группа удалена';
					$row['color'] = 'red';
					break;
				case UPKIND_INLINE:
					$row['title'] = '';
					$row['delta'] = $val ? 'вложенная группа' : 'не вложенная группа';
					$row['color'] = 'blue';
					break;
				case UPKIND_SIZE:
					$change = $sizes[$row['id']] - $val;
				case UPKIND_ADDED:
				case UPKIND_DELETED:
					$added = $val > 0;
					if (!$change) {
						$row['delta'] = Loc::lget($added ? 'added' : 'deleted') . ' (' . fs(abs($val * 1024)) . ')';
						$row['color'] = $added ? 'green' : 'maroon';
					} else {
						$row['color'] = $added ? 'green' : 'red';
						$row['delta'] = ($added ? '+' : '-') . fs(abs($val * 1024));
					}
					break;
				case UPKIND_RENAMED:
					$row['color'] = 'teal';
					$row['delta'] = 'переименовано';
				default:
				}

				$row['moder'] = $isModer ? patternize($p6, $row) : '';
				$t[patternize($p4, $row)][] = patternize($p3, $row);
			}
			$e = array();
			foreach ($t as $author => $updates) {
				$updates[$i = count($updates) - 1] = str_replace(' dotted', '', $updates[$i]);
				$o = join('', $updates);
				$e[] = "<div class=\"cnt-item\">
							$author{$o}
						</div>
						";
			}
			$u[] = "<small><b>&nbsp; $daysAgo</b></small>" . join('', $e);
		}
	}

	$_total = $d['total'];
	$_from = ($page - 1) * $pagesize + 1;
	$_to = min($_total, $_from + $pagesize - 1);

	$c3 = $updatelist ? "$_from-$_to" : $c3;
	$c3 = $c3 . '/<a href="?action=updatelist">' . $d['total'] . '</a>';
	$u = join('', $u);

	if ($updatelist)
		$u .= '<ul class="pages">' . $a->generatePageList($page, ceil($d['total'] / $pagesize), '?page=', '&action=updatelist') . '</ul>';
	else {
		require_once 'core_authors.php';
		$a = AuthorsAggregator::getInstance();
		$idx = $h->authorsToUpdate(0, 1, true);
		$d = $a->get($idx, '`id`, `fio`, `time`, `update_freq`');
		$r = array();
		$time = time();
		if (!!$d)
			foreach ($d as &$row) {
				$row['delta'] = tmDelta($t = intval($row['time']));
				$row['time'] = date('d.m.Y H:i:s', $t);
				$delta = 60 * intval(intval($row['update_freq']) - ($time - $t) / 60);
				$delta = $delta > 0 ? $delta : 0;
				$row['after'] = tmDelta($time - $delta);
				$r["$delta." . count($r)] = patternize($p1, $row);
			}

		ksort($r);
		$r = join('', $r);

		require_once 'core_queue.php';
		$q = QueueAggregator::getInstance();
		$d = $q->fetch(array('desc' => 0
		, 'filter' => '(`state` = 0) or (`state` <> 0 and `updated` < ' . ($t - QUEUE_FAILTIME) . ') limit 50'
		, 'collumns' => '`id` as `0`, `page` as `1`'
		));
		$e = array();
		if ($d['total']) {
			$idx = array();
			foreach ($d['data'] as &$row)
				$idx[] = intval($row[1]);

			$y = $p->get($idx, '`id`, `title`, `link`, `time`');
			foreach ($y as $idx => &$row) {
				$row['delta'] = tmDelta($row['time']);
				$row['time'] = date('d.m.Y H:i:s', intval($row['time']));
				$e[] = patternize($p2, $row);
			}
		}

		$_c2 = count($e);
		$c2 = $_c2 . '/' . $d['total'];
		$e = join('', $e);

		if ($a_id = post_int('a')) {
			require_once 'core_updates.php';
			$w = new AuthorWorker();
			msqlDB::o()->debug = 1;
			$w->check($a_id);
		}
	}

	function tmDelta($t) {
		$t = time() - intval($t);
		$s = $t % 60;
		$t = ($t - $s) / 60;
		$m = $t % 60;
		$t = ($t - $m) / 60;
		$h = $t % 24;
		$d = ($t - $h) / 24;
//		$h+= $d * 24;
		if ($m < 10) $m = "0" . $m;
		if ($h < 10) $h = "0" . $h;
		$l = array();
		if ($d) $l[] = "{$d}д.";
		if ($h) $l[] = "{$h}ч.";
		$l[] = "{$m}м.";
		return join(' ', $l);
	}

	function daysAgo($delta) {
		switch (true) {
		case $delta <=  0: return 'сегодня';
		case $delta ==  1: return 'вчера';
		case $delta <= 30: return $delta . ' ' . aaxx($delta, 'д', array('ень', 'ня', 'ней')) . ' назад';
		case $delta >  30: return 'больше месяца назад';
		}
	}

	if ($this->ctl->userModer)
		View::addKey('moder', '<span class="pull_right">[ <a href="/updates/authors">проверка авторов</a> | <a href="/updates/pages/10">обработка очереди</a> | <a href="/pages/cleanup">чистка</a> ]</span>')
?>
				<div class="title content-header">Последние обновления (<?=$c3?>):</div>
				<?=$u?>


<?php
	if (!$updatelist) {
		if ($_c2) {
?>

				<div class="title content-header">Обновления произведений (<?=$c2?>):</div>
				<?=$e?>
<?php
		}
?>
				<div class="title content-header">Очередь на проверку обновлений:</div>
				<?=$r?>
<?php
	}
?>
