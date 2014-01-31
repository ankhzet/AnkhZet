<?php

	class TimeZoneHelper {
		var $offsets = array();
		var $abbrevs = array();
		private static $i = null;

		static function get() {
			if (!self::$i)
				self::$i = new self();

			return self::$i;
		}

		private function __construct() {
			$timezones = DateTimeZone::listAbbreviations();
			foreach ($timezones as $key => $zones) {
				foreach ($zones as $id => $zone) {
					if (strpos($zone['timezone_id'], '/') !== false && $zone['dst']) {
						$hours = intval($zone['offset']) / 3600.0;
						preg_match('/(\w+)\/(.*)/i', $zone['timezone_id'], $match);
						$region = $match[1];
						$city = $match[2];
						$this->offsets[$region][$hours][] = $city;
						$this->abbrevs[$hours][$region][] = $city;
					}
				}
			}

			foreach($this->offsets as $key => $zone) {
				foreach ($zone as $id => $cities) {
					$c = array();
					$cities = array_unique($cities);
					asort($cities);
					$a = array_chunk($cities, 4);
					foreach ($a as $chunk)
						$c[$id . '=' . count($c)] = join(', ', $chunk);

					unset($this->offsets[$key][$id]);
					$this->offsets[$key] = ($this->offsets[$key] + $c);
					ksort($this->offsets[$key]);
				}
			}

			foreach ($this->abbrevs as $offset => $keys)
				foreach ($keys as $id => $cities)
					$this->abbrevs[$offset][$id] = array_unique($this->abbrevs[$offset][$id]);

			ksort($this->offsets);
		}

		function getZones() {
			return array_keys($this->offsets);
		}

		function getOffsets($zone = null) {
			return $zone ? $this->offsets[$zone] : $this->offsets;
		}

		function getAbbreviations($offset) {
			return $this->abbrevs[$offset];
		}
	}