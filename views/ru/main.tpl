<?php
	$p1 = '<div class="cnt-item">
		<div class="title">
			<span class="head"> <a href="/authors/id/{%id}">{%fio}</a></span>
			<span class="link" style="width: 50%;">Прошло с последнего обновления: <b>{%time}</b></span>
		</div>
	</div>';
	$p2 = '<div class="cnt-item">
		<div class="title">
			<span class="head"> <a href="/pages/version/{%id}">{%title}</a></span>
			<span class="link" style="width: 50%;">Прошло с изменения страницы: <b>{%time}</b></span>
		</div>
	</div>';
	$t = time();
	require_once 'core_authors.php';
	$a = AuthorsAggregator::getInstance();
	$d = $a->fetch(array('nocalc' => true, 'desc' => 0, 'filter' => '`time` < ' . $t . ' limit 10', 'collumns' => '`id`, `fio`, `time`'));
	$r = array();
	if ($d['total'])
		foreach ($d['data'] as &$row) {
			$row['time'] = date('h:i:s', $t - intval($row['time']));
			$r[] = patternize($p1, $row);
		}

	$r = join('', $r);

	require_once 'core_queue.php';
	require_once 'core_page.php';
	$q = QueueAggregator::getInstance();
	$p = PagesAggregator::getInstance();
	$d = $q->fetch(array('nocalc' => 1, 'desc' => 0
	, 'filter' => '(`state` = 0) or (`state` <> 0 and `updated` < ' . ($t - QUEUE_FAILTIME) . ')'
	, 'collumns' => '`id` as `0`, `page` as `1`'
	));
	$e = array();
	if ($d['total']) {
		$idx = array();
		foreach ($d['data'] as &$row)
			$idx[] = intval($row[1]);

		$e = $p->get($idx, '`id`, `title`, `link`, `time`');
		foreach ($e as &$row) {
			$row['time'] = date('h:i:s', $t - intval($row['time']));
			$e[] = patternize($p2, $row);
		}
	}


	$e = join('', $e);
?>
	<h4>Очередь на проверку обновлений:</h4>
<?=$r?>
	<h4>Обновления произведений:</h4>
<?=$e?>
