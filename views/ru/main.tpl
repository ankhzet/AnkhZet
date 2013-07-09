<?php
	$p1 = '<div class="cnt-item">
					<div class="title">
						<span class="head"> <a href="/authors/id/{%id}">{%fio}</a></span>
						<span class="link size" style="width: 150px; text-align: right;">{%time}</span>
						<span class="link" style="width: 50%;">Прошло с последнего обновления: <b>{%delta}</b></span>
					</div>
				</div>
				';
	$p2 = '<div class="cnt-item">
					<div class="title">
						<span class="head"> <a href="/pages/version/{%id}">{%title}</a></span>
						<span class="link size" style="width: 150px; text-align: right;">{%time}</span>
						<span class="link" style="width: 50%;">Прошло с изменения страницы: <b>{%delta}</b></span>
					</div>
				</div>
				';
	$p3 = '<div class="cnt-item">
					<div class="title">
						<span class="head">
							<a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages?group={%group}">{%group_title}</a>:
							<a href="/pages/version/{%id}">{%title}</a>
						</span>
						<span class="link size" style="width: 150px; text-align: right;">{%time}</span>
						<span class="link size" style="width: 30%; color: {%color};"><b>{%delta}</b></span>
					</div>
				</div>
				';
	$p4 = '<div class="cnt-item">
					<div class="title">
						<span class="head">
							<a href="/authors/id/{%author}">{%fio}</a> - <a href="/pages?group={%group}">{%group_title}</a>
						</span>
						<span class="link size" style="width: 150px; text-align: right;">{%time}</span>
						<span class="link size" style="width: 30%; color: {%color};"><b>{%delta}</b></span>
					</div>
				</div>
				';
	$t = time();
	require_once 'core_updates.php';
	$a = UpdatesAggregator::getInstance();
	$d = $a->getUpdates(1);
	$u = array();
	if ($d['total'])
		foreach ($d['data'] as &$row) {
			$val = $row['value'];
//			if (!$val) continue;
			$row['time'] = date('d.m.Y H:i:s', intval($row['time']));
			switch ($row['kind']) {
			case UPKIND_GROUP:
				$row['delta'] = patternize('перенесено из <a href="/pages?group={%old_id}">{%old_title}</a>', $row);
				$row['color'] = 'blue';
				$u[] = patternize($p3, $row);
				break;
			case UPKIND_INLINE:
				$row['delta'] = $val ? 'вложенная группа' : 'не вложенная группа';
				$row['color'] = 'blue';
				$u[] = patternize($p4, $row);
				break;
			case UPKIND_SIZE:
				$row['delta'] = (($pos = $val > 0) ? '+' : '-') . fs(abs($val * 1024));
				$row['color'] = $pos ? 'green' : 'red';
			default:
				$u[] = patternize($p3, $row);
			}
		}

	$c3 = count($u) . '/' . $d['total'];
	$u = join('', $u);

	require_once 'core_authors.php';
	require_once 'core_history.php';
	$a = AuthorsAggregator::getInstance();
	$h = HistoryAggregator::getInstance();
	$idx = $h->authorsToUpdate(0, 1);
	$d = $a->get($idx, '`id`, `fio`, `time`');
	$r = array();
	if (!!$d)
		foreach ($d as &$row) {
			$row['delta'] = tmDelta($row['time']);
			$row['time'] = date('d.m.Y H:i:s', $t = intval($row['time']));
			$r["$t . " . count($r)] = patternize($p1, $row);
		}

	$c1 = count($r);
	ksort($r);
	$r = join('', $r);

	require_once 'core_queue.php';
	require_once 'core_page.php';
	$q = QueueAggregator::getInstance();
	$p = PagesAggregator::getInstance();
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

	$c2 = count($e) . '/' . $d['total'];
	$e = join('', $e);

	if ($a_id = post_int('a')) {
		require_once 'core_updates.php';
		$w = new AuthorWorker();
		msqlDB::o()->debug = 1;
		$w->check($a_id);
	}

	function tmDelta($t) {
		$t = time() - intval($t);
		$s = $t % 60;
		$t = ($t - $s) / 60;
		$m = $t % 60;
		$t = ($t - $m) / 60;
		$h = $t % 24;
		$d = ($t - $h) / 24;
		return "{$d}д. {$h}ч. {$m}м.";
	}

	if ($this->ctl->userModer)
		View::addKey('moder', '<span class="pull_right">[ <a href="/updates/authors">проверка авторов</a> | <a href="/updates/pages/10">обработка очереди</a> | <a href="/pages/cleanup">чистка</a> ]</span>')
?>
				<div class="title content-header">Последние обновления (<?=$c3?>):</div>
				<?=$u?>

				<div class="title content-header">Очередь на проверку обновлений (<?=$c1?>):</div>
				<?=$r?>

				<div class="title content-header">Обновления произведений (<?=$c2?>):</div>
				<?=$e?>
