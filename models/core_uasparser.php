<?php
/**
 * PHP version 5
 *
 * @package    UASparser
 * @author     Jaroslav Mallat (http://mallat.cz/)
 * @copyright  Copyright (c) 2008 Jaroslav Mallat
 * @copyright  Copyright (c) 2010 Alex Stanev (http://stanev.org)
 * @copyright  Copyright (c) 2012 Martin van Wingerden (http://www.copernica.com)
 * @version    0.51
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @link       http://user-agent-string.info/download/UASparser
 */

class UASparser
{
		private $_cache_dir         =   null;
		public  $_data              =   null;

		/**
		 *  Constructor with an optional cache directory
		 *  @param  string  cache directory to be used by this instance
		 */
		public function __construct($cacheDirectory = null) {
				if ($cacheDirectory) $this->SetCacheDir($cacheDirectory);
		}

		/**
		 *  Parse the useragent string if given otherwise parse the current user agent
		 *  @param  string  user agent string
		 */
		public function Parse($useragent = null) {
				// intialize some variables
				$browser_id = $os_id = null;
				$result = array();

				// initialize the return value
				$result['typ']              = 'unknown';
				$result['typ_id']           = 0;
				$result['ua_id']            = 0;
				$result['os_id']            = -1;
				$result['ua_family']        = 'unknown';
				$result['ua_name']          = 'unknown';
				$result['ua_version']       = 'unknown';
				$result['ua_url']           = 'unknown';
				$result['ua_company']       = 'unknown';
				$result['ua_company_url']   = 'unknown';
				$result['ua_icon']          = 'unknown.png';
				$result["os_family"]        = 'unknown';
				$result["os_name"]          = 'unknown';
				$result["os_url"]           = 'unknown';
				$result["os_company"]       = 'unknown';
				$result["os_company_url"]   = 'unknown';
				$result["os_icon"]          = 'unknown.png';

				// if no user agent is supplied process the one from the server vars
				if (!isset($useragent) && isset($_SERVER['HTTP_USER_AGENT'])){
						$useragent = $_SERVER['HTTP_USER_AGENT'];
				}

				// if we haven't loaded the data yet, do it now
				if(!$this->_data) {
						$this->_data = $this->_loadData();
				}

				// we have no data or no valid user agent, just return the default data
				if(!$this->_data || !isset($useragent)) {
						return $result;
				}

				// crawler
				foreach ($this->_data['robots'] as $b_id => $test) {
						if ($test[0] == $useragent) {
								$result['typ']                            = 'Robot';
								$result['typ_id'] = -1;
								$result['ua_id'] = $b_id;
								if ($test[1]) $result['ua_family']        = $test[1];
								if ($test[2]) $result['ua_name']          = $test[2];
								if ($test[3]) $result['ua_url']           = $test[3];
								if ($test[4]) $result['ua_company']       = $test[4];
								if ($test[5]) $result['ua_company_url']   = $test[5];
								if ($test[6]) $result['ua_icon']          = $test[6];
								$result['os_id'] = $test[7];
								if ($test[7]) { // OS set
										$os_data = $this->_data['os'][$test[7]];
										if ($os_data[0]) $result['os_family']       =   $os_data[0];
										if ($os_data[1]) $result['os_name']         =   $os_data[1];
										if ($os_data[2]) $result['os_url']          =   $os_data[2];
										if ($os_data[3]) $result['os_company']      =   $os_data[3];
										if ($os_data[4]) $result['os_company_url']  =   $os_data[4];
										if ($os_data[5]) $result['os_icon']         =   $os_data[5];
								}
								return $result;
						}
				}

				// find a browser based on the regex
				foreach ($this->_data['browser_reg'] as $test) {
						if (@preg_match($test[0],$useragent,$info)) { // $info may contain version
								$browser_id = $test[1];
								break;
						}
				}

				// a valid browser was found
				if ($browser_id) { // browser detail
						$browser_data = $this->_data['browser'][$browser_id];
						if ($this->_data['browser_type'][$browser_data[0]][0]) {
							$result['typ']    = $this->_data['browser_type'][$browser_data[0]][0];
							$result['typ_id'] = $browser_data[0];
						}
						$result['ua_id'] = $browser_id;
						if (isset($info[1]))    $result['ua_version']     = $info[1];
						if ($browser_data[1])   $result['ua_family']      = $browser_data[1];
						if ($browser_data[1])   $result['ua_name']        = $browser_data[1].(isset($info[1]) ? ' '.$info[1] : '');
						if ($browser_data[2])   $result['ua_url']         = $browser_data[2];
						if ($browser_data[3])   $result['ua_company']     = $browser_data[3];
						if ($browser_data[4])   $result['ua_company_url'] = $browser_data[4];
						if ($browser_data[5])   $result['ua_icon']        = $browser_data[5];
				}

				// browser OS, does this browser match contain a reference to an os?
				if (isset($this->_data['browser_os'][$browser_id])) { // os detail
						$os_id = $this->_data['browser_os'][$browser_id][0]; // Get the os id
						$os_data = $this->_data['os'][$os_id];
						$result['os_id'] = $os_id;
						if ($os_data[0])    $result['os_family']      = $os_data[0];
						if ($os_data[1])    $result['os_name']        = $os_data[1];
						if ($os_data[2])    $result['os_url']         = $os_data[2];
						if ($os_data[3])    $result['os_company']     = $os_data[3];
						if ($os_data[4])    $result['os_company_url'] = $os_data[4];
						if ($os_data[5])    $result['os_icon']        = $os_data[5];
						return $result;
				}

				// search for the os
				foreach ($this->_data['os_reg'] as $test) {
						if (@preg_match($test[0],$useragent)) {
								$os_id = $test[1];
								break;
						}
				}

				// a valid os was found
				if ($os_id) { // os detail
						$os_data = $this->_data['os'][$os_id];
						$result['os_id'] = $os_id;
						if ($os_data[0]) $result['os_family']       = $os_data[0];
						if ($os_data[1]) $result['os_name']         = $os_data[1];
						if ($os_data[2]) $result['os_url']          = $os_data[2];
						if ($os_data[3]) $result['os_company']      = $os_data[3];
						if ($os_data[4]) $result['os_company_url']  = $os_data[4];
						if ($os_data[5]) $result['os_icon']         = $os_data[5];
				}
				return $result;
		}

		/**
		 *  Load the data from the files
		 */
		function _loadData() {
				if (!file_exists($this->_cache_dir)) return;

				// we have file with data, parse and return it
				if (file_exists($this->_cache_dir.'/uasdata.ini')) {
						return @parse_ini_file($this->_cache_dir.'/uasdata.ini', true);
				}
				else {
						trigger_error('ERROR: No datafile (uasdata.ini in Cache Dir), maybe update the file manually.');
				}
		}

		/**
		 *  Set the cache directory
		 *  @param string
		 */
		public function SetCacheDir($cache_dir) {

				// The directory does not exist at this moment, try to make it
				if (!file_exists($cache_dir)) @mkdir($cache_dir, 0777, true);

				// perform some extra checks
				if (!is_writable($cache_dir) || !is_dir($cache_dir)){
						trigger_error('ERROR: Cache dir('.$cache_dir.') is not a directory or not writable');
						return;
				}

				// store the cache dir
				$cache_dir = realpath($cache_dir);
				$this->_cache_dir = $cache_dir;
		}
}