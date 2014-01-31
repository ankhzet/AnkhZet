<?php
	require_once 'core_bridge.php';
	require_once 'core_pagecontroller_utils.php';

	class PageComparator {

		function compare($page, $link, $time) {
			$path = PageUtils::getPageStorage($page);
			$last = "{$path}/last.html";
			$store = "{$path}/{$time}.html";

			assume_dir_exists($path);
			$html1 = PageUtils::getPageContents($page, 'last', false);

			$html2 = url_get_contents("http://samlib.ru/{$link}");
			$c = null;
			if ($html2 !== false) {
				$p1 = '<!----------- Собственно произведение --------------->';
				$p2 = '<!--------------------------------------------------->';
				$p3 = '- Блок описания произведения (слева вверху) -';
				$p4 = '</small>';
				$i3 = strpos($html2, $p3);
				$i4 = strpos($html2, $p4, $i3);
				$m = substr($html2, $i3, $i4 - $i3);
				preg_match('/\. (\d+)k\./i', $m, $m);
				$size = intval($m[1]);

				$i1 = strpos($html2, $p1) + strlen($p1);
				$i2 = strpos($html2, $p2, $i1);
				$m = substr($html2, $i1, $i2 - $i1);
				$html2 = preg_replace('/(<!-+[^>]+>)/', '', $m);
				$html2 = preg_replace('/(<!-+|--+>)/', '', $html2);

				if ($html1 != $html2) {
					file_put_contents($store, $html = gzcompress($html2));
					file_put_contents($last, $html);
				}

				return array(strlen($html1), strlen($html2), $size);
			} else
				return false;
		}

	}
