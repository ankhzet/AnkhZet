<?php

	class SAMLIBParser {
		static $i = null;

		public static function get($class = null) {
			if (!isset(self::$i))
				self::$i = $class ? new $class() : new self();

			return self::$i;
		}

		static function getData($link, $method, $usecache = false) {
			$url  = "http://samlib.ru/$link";
			if ($usecache) {
				$hash = md5($url);
				$file = "cms://root/cache/$hash";
				if (is_file($file) && filesize($file))
					$html = file_get_contents($file);
				else {
					$html = url_get_contents($url);
					file_put_contents($file, $html);
				}
			} else
				$html = url_get_contents($url);

			if (($html === false) || trim($html) == '')
				return false;

			$html = mb_convert_encoding($html, 'UTF-8', 'CP1251');
			$parser = self::get();//'SAMLIBPHPQueryParser');
			if (method_exists($parser, $method = 'parse' . ucfirst($method)))
				return $parser->{$method}($html);
			else
				return null;
		}

		public function parseAuthor($html) {
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
				$t = ucfirst(preg_replace('/:$/', '', $t));
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
				if ($t)
					foreach ($t[1] as $link)
						if (preg_match('/<a href="?([^ >"]+)"?>(.*?)<\/a>[^<]*<b>(.*?)k<\/b>/i', $link, $t2)) {
							preg_match('/<dd>(.*)/i', $link, $t3);
//							debug($t2, htmlspecialchars($link));
							$links[$id][$t2[1]] = array(
								$idx
							, strip_tags($t2[2])
							, intval($t2[3])
							, isset($t3) ? strip_tags($t3[1], '<br><p>') : ''
							);
						}
//				debug($links[$id], "Group #$id");
			}
//			debug($m);
//			debug($html);
			preg_match('/<h3>(.*?)<br/i', $html, $t);
			if ($t)
				$t = preg_replace('/:$/', '', $t[1]);

			return array($groups, $inline, $links, $t);
		}


		public function parseGroup($html) {
			$p1 = strpos($html, '<dl>') + 4;
			$p2 = strpos($html, '</dl>');
			$links = substr($html, $p1, $p2 - $p1);
			$l = array();
			if ($links) {
				preg_match_all('"<dl>(.*?)</dl>"i', $links, $t);
				if ($t)
					foreach ($t[1] as $link)
						if (preg_match('/<a href="?([^ >"]+)"?>(.*?)<\/a>[^<]*<b>(.*?)k<\/b>/i', $link, $t2)) {
							preg_match('/<dd>(.*)/i', $link, $t3);
//							debug($t2, htmlspecialchars($link));
							$l[$t2[1]] = array(
								strip_tags($t2[2])
							, intval($t2[3])
							, isset($t3) ? strip_tags($t3[1], '<br><p>') : ''
							);
						}
			}
			return array('links' => $l);
		}
	}

	class SAMLIBPHPQueryParser extends SAMLIBParser {
		public function parseAuthor($html) {
			$p1 = '- Блок ссылок на произведения -';
			$p2 = '- Подножие -';

			require_once 'core_phpquery.php';
			libxml_use_internal_errors(true);
			$doc = phpQuery::newDocument($html);

			return $this->_parseGroups($doc);
		}

		function _parseGroups($pq) {
			$groups = array();
			foreach ($pq->find('dl:has(dl)') as $item) {
				$pieces = explode('<p><font', phpQuery::pq($item)->html());

				foreach ($pieces as $ghtml) {
					$group = phpQuery::newDocument($ghtml);
					$hdr = phpQuery::pq($group->find('b'));
					$h = $hdr->find('a');
					$title = preg_replace('/:$/', '', trim(strip_tags($h->html())));
					if (!$title) continue;
					$gidx = str_replace('gr', '', $h->attr('name'));
					$link = $hdr->find('a[href]')->attr('href');
					if (strpos($link, '/type/') !== false) $link = '';

					$pages = $this->_parsePages($group);
					$groups[] = array(
						'title' => $title
					, 'desc' => trim(phpQuery::pq($group->find('font i'))->html())
					, 'idx' => $gidx
					, 'link' => $link
					, 'pages' => $pages
					);
				}
			}
//			debug($groups);
			return $groups;
		}

		function _parsePages($pq) {
			$pages = array();
			foreach ($pq->find('li') as $p) {
				$page = phpQuery::pq($p);
//				debug($page->html());
				$title = phpQuery::pq($page->find('a b'))->text();
				if (!$title) continue;

				$pages[] = array(
					'title' => $title
				, 'size' => intval(phpQuery::pq($page->find('b:eq(1)'))->text())
				, 'comm' => phpQuery::pq($page->find('b:eq(2)'))->text()
				, 'link' => $page->find('a')->attr('href')
				, 'desc' => trim(strip_tags(phpQuery::pq($page->find('dd'))->html(), '<p><br><u><i><b>'))
				);
			}
			return $pages;
		}
	}