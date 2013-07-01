<?php
	require_once 'core_bridge.php';

	class PageComparator {

		function compare($page, $link, $time) {
			$path = SUB_DOMEN . "/cache/pages/{$page}";
			$last = "{$path}/last.html";
			$store = "{$path}/{$time}.html";

			assume_dir_exists($path);
			$html1 = @file_get_contents($last);
			if ($html1) $html1 = @gzuncompress($html1);

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

		function prepareForGrammar($c, $cleanup = false) {
			if ($cleanup) {
				$c = strip_tags($c, '<dd><p><br><u><b><i><s>');
				$c = preg_replace('"<p([^>]*)?>(.*?)<dd>"i', '<p\1>\2</p><dd>', $c);
				$c = str_replace(array('<dd>', '<br>', '<br />'), PHP_EOL, $c);
				$c = preg_replace('/'.PHP_EOL.'{3,}/', PHP_EOL.PHP_EOL, $c);
			} else
				$c = str_replace('<br />', PHP_EOL, $c);

			$c = str_replace('&nbsp;&nbsp;', ' &nbsp;', $c);

			$c = preg_replace('"<([^>]+)>(\s*)</\1>"', '\2', $c);
			$c = preg_replace('"</([^>]+)>((\s|\&nbsp;)*)?<\1>"i', '\2', $c);
			$idx = 0;
			$p = 0;
			while (preg_match('"<(([\w]+)([^>/]*))>"', substr($c, $p), $m, PREG_OFFSET_CAPTURE)) {
				$p += intval($m[0][1]);
				$sub = $m[0][0];
				if (strpos($sub, 'class="pin"') === false) {
					$idx++;
					$tag = $m[2][0];
					$attr = $m[3][0];
					$u = "<$tag node=\"$idx\"$attr>";
					$c = substr_replace($c, $u, $p, strlen($sub));
					$p += strlen($u);
				} else
					$p += strlen($sub);
			}
			return str_replace(PHP_EOL, '<br />', $c);
		}
	}

?>