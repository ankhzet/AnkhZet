<?php
	require_once 'dbtable.php';
	require_once 'session.php';

	function checkLength($str, $min, $max) {
		$l = strlen($str);
		return ($l >= $min) && ($l <= $max);
	}

	define('LEN_DEFAULT_MIN', 5);
	define('LEN_DEFAULT_MAX', 200);
	define('LEN_LOGIN_MIN'  , 5);
	define('LEN_LOGIN_MAX'  , 12);
	define('LEN_PASS_MIN'   , 5);
	define('LEN_PASS_MAX'   , 18);
	define('LEN_EMAIL_MIN'  , 6);
	define('LEN_EMAIL_MAX'  , 250);
	define('LEN_CAPTCHA_MIN', 6);
	define('LEN_CAPTCHA_MAX', 8);

	define('PAT_LOGIN'    , '/^[a-z0-9_\-\.]+$/i');
	define('PAT_PASSWORD' , '/^[a-z0-9_]+$/i');
	define('PAT_EMAIL'    , '/^(\w+(\-\w+)*)+(\.\w+(\-\w+)*)*@(\w+(\-\w+)*)+(\.\w+(\-\w+)*)*\.[\w]{2,4}$/i');
	define('PAT_CAPTCHA'  , '/^[0-9a-z]+$/i');
	define('PAT_NAME'     , '/^[\p{L}\-\s]{2,200}$/ui');
	define('PAT_DATE'     , '/^\d{1,2}\.\d{1,2}\.\d{4,4}$/i');
	define('PAT_INTEGER'  , '/^\d*$/i');
	define('PAT_PHONE'    , '/^\+?(\d+\-?){2,}\d$/i');

	class User extends msqlTableRow {
		private static $data = null;
		const COL_LOGIN  = 'login';
		const COL_PASS   = 'password';
		const COL_ACL    = 'acl';
		const COL_LANG   = 'lang';
		const COL_NAME   = 'name';
		const COL_SURNAME= 'surname';
		const COL_FATHER = 'fathername';
		const COL_CITIZEN= 'citizenship';
		const COL_GENDER = 'gender';
		const COL_BIRTH  = 'birthday';
		const COL_EMAIL  = 'mail';
		const COL_PHONE  = 'phone';
		const COL_ACTION = 'action';

		const FLD_LOGIN  = 'email';
		const FLD_PASS1  = 'pass';
		const FLD_PASS2  = 'pass2';
		const FLD_AGREE  = 'terms';
		const FLD_CAPTCHA= 'captcha';
		const FLD_NAME   = 'name';
		const FLD_SURNAME= 'surname';
		const FLD_FATHER = 'fathername';
		const FLD_CITIZEN= 'citizenship';
		const FLD_GENDER = 'gender';
		const FLD_BIRTH  = 'birthday';
		const FLD_PHONE  = 'phone';
		const FLD_EMAIL  = 'mail';
		const FLD_ACTION = 'action';

		const PASS_SALT  = 'SiLlySaLt';

		const ERR_LOGIN  = 1;
		const ERR_PASS1  = 2;
		const ERR_PASS2  = 3;
		const ERR_ACCOUNT= 4;
		const ERR_AGREE  = 5;
		const ERR_CAPTCHA= 6;
		const ERR_LOGIN2 = 7;
		const ERR_NAME   = 8;
		const ERR_SURNAME= 12;
		const ERR_FATHER = 15;
		const ERR_CITIZEN= 16;
		const ERR_GENDER = 9;
		const ERR_BIRTH  = 10;
		const ERR_EMAIL  = 11;
		const ERR_ACTION = 14;
		const ERR_PHONE  = 13;


		function __construct() {
			$this->table = 'users';
		}

		function add() {
			$this->insert();
		}

		function init_table() {
			return msqlDB::o()->create_table($this->table, array(
				'`id` int auto_increment null'
			, '`login` varchar(250) not null'
			, '`password` varchar(100) not null'
			, '`acl` int not null default "1"'
			, '`lang` tinyint not null default "1"'
			, '`name` varchar(200) null'
			, 'primary key (`id`)'
			), false);
		}

		static function get($id = null) {
			if ($id = intval($id)) {
				$u = new User();
				$u->_set(self::COL_ID, $id, true);
				return $u;
			}
			if (null === self::$data) {
				self::$data = new User();
				$s = Ses::get(SAM::SAM_COOKIES);

				if ($s->valid()){
					self::$data->_set(self::COL_ID, $s->linked, true);}
			};
			return self::$data;
		}

		static function ACL() {
			$acl = self::get()->_get(self::COL_ACL);
			return isset($acl) ? $acl : ACL::ACL_GUEST;
		}

		static function Lang() {
			$lang = self::get()->_get(self::COL_LANG);
			return isset($lang) ? intval($lang) : 0;
		}

		static function Login() {
			$log = self::get()->_get(self::COL_LOGIN);
			if (isset($log)) return $log;
			require_once 'Localization.php';
			return Loc::lget('acl_guest');
		}

		function getLink($caption = null) {
			if ($this->_get(self::COL_ACL) > 0)
				return '<a href="/user/' . $this->_get(User::COL_ID) . '">' . ($caption ? $caption : $this->readable()) . '</a>';
			else
				return '<unknown>';
		}

		function readable($short = false) {
			if ($this->_get(self::COL_ACL) > ACL::ACL_GUEST) {
				return $this->_get(self::COL_NAME) . ($short ? '' : ' ' . $this->_get(self::COL_SURNAME));
			} else
				return Loc::lget('acl_guest');
		}

		function ID() {
			return $this->_get(self::COL_ID);
		}

		static function checkRegData($regdata) {
			$flds = array(
					self::FLD_NAME
				, self::FLD_SURNAME
				, self::FLD_FATHER
				, self::FLD_CITIZEN
				, self::FLD_LOGIN
				, self::FLD_PASS1
				, self::FLD_PASS2
				, self::FLD_BIRTH
				, self::FLD_GENDER
				, self::FLD_ACTION
				, self::FLD_PHONE
				, self::FLD_EMAIL
				, self::FLD_AGREE
				, self::FLD_CAPTCHA
			);
			$res  = array();
			foreach ($flds as $field) {
				$value = uri_frag($regdata, $field, null, 0);
				switch ($field) {
				case self::FLD_NAME:
					if (!(preg_match(PAT_NAME, $value)))
						$res[$field] = self::ERR_NAME;
					break;
				case self::FLD_SURNAME:
					if (!(preg_match(PAT_NAME, $value)))
						$res[$field] = self::ERR_SURNAME;
					break;
				case self::FLD_FATHER:
					if (!(preg_match(PAT_NAME, $value)))
						$res[$field] = self::ERR_FATHER;
					break;
				case self::FLD_CITIZEN:
					if (!(preg_match(PAT_NAME, $value)))
						$res[$field] = self::ERR_CITIZEN;
					break;
				case self::FLD_BIRTH:
					if ($value && !preg_match(PAT_DATE, $value))
						$res[$field] = self::ERR_BIRTH;
					break;
				case self::FLD_GENDER:
					if ($value && !preg_match(PAT_INTEGER, $value))
						$res[$field] = self::ERR_GENDER;
					break;
				case self::FLD_PHONE:
					if (!preg_match(PAT_PHONE, $value))
						$res[$field] = self::ERR_PHONE;
					break;
				case self::FLD_ACTION:
					if ($value && !preg_match(PAT_INTEGER, $value))
						$res[$field] = self::ERR_ACTION;
					break;
				case self::FLD_EMAIL:
					if (!(checkLength($value, LEN_EMAIL_MIN, LEN_EMAIL_MAX) && preg_match(PAT_EMAIL, $value)))
						$res[$field] = self::ERR_EMAIL;
					break;
				case self::FLD_LOGIN:
					if (!(checkLength($value, LEN_EMAIL_MIN, LEN_EMAIL_MAX) && preg_match(PAT_EMAIL, $value)))
						$res[$field] = self::ERR_LOGIN;
					else {
						$dbc = msqlDB::o();
						$s = $dbc->select('users', 'login = \'' . $value . '\'');
						if ($dbc->rows($s))
							$res[$field] = self::ERR_LOGIN2;
					}
					break;
				case self::FLD_PASS1:
					if (!(checkLength($value, LEN_PASS_MIN, LEN_PASS_MAX) && preg_match(PAT_PASSWORD, $value)))
						$res[$field] = self::ERR_PASS1;
					break;
				case self::FLD_PASS2:
					if ($res[self::ERR_PASS1] || ($regdata[self::FLD_PASS1] != $value))
						$res[$field] = self::ERR_PASS2;
					break;
				case self::FLD_AGREE:
					if (!intval($value))
						$res[$field] = self::ERR_AGREE;
					break;
				case self::FLD_CAPTCHA:
					if (!(checkLength($value, LEN_CAPTCHA_MIN, LEN_CAPTCHA_MAX) && preg_match(PAT_CAPTCHA, $value)))
						$res[$field] = self::ERR_CAPTCHA;
					else {
						require_once 'captcha.php';
						$c = new Captcha($_REQUEST[rid]);
						if (!$c->valid($value))
							$res[$field] = self::ERR_CAPTCHA;
					}
					break;
				default:
					if (!checkLength($value, LEN_DEFAULT_MIN, LEN_DEFAULT_MAX))
						$res[$field] = $field;
					break;
				}
			}
			foreach ($res as $field => $error)
				if ($res && !isset($regdata[$field]))
					unset($res[$field]);

			return $res;
		}

		static function register($data) {
			$errors = self::checkRegData($data);
			if (count($errors)) return $errors;

			$login    = $data[self::FLD_LOGIN];
			$password = $data[self::FLD_PASS1];
			$dbc = msqlDB::o();
			$s   = $dbc->insert('users', array(
					self::COL_LOGIN => $login
				, self::COL_PASS => md5($password . self::PASS_SALT)
				, self::COL_ACL => ACL::ACL_USER
				, self::COL_NAME => $data[self::FLD_NAME]
//				, self::COL_SURNAME => $data[self::FLD_SURNAME]
//				, self::COL_FATHER => $data[self::FLD_FATHER]
//				, self::COL_CITIZEN => $data[self::FLD_CITIZEN]
//				, self::COL_BIRTH => trim($data[self::FLD_BIRTH])
//				, self::COL_GENDER => trim($data[self::FLD_GENDER])
//				, self::COL_ACTION => intval($data[self::FLD_ACTION])
//				, self::COL_PHONE => $data[self::FLD_PHONE]
//				, self::COL_MAIL => $data[self::FLD_MAIL]
			));
			self::passReminder($login, $password);
			return array();
		}

		static function update($data) {
			$check = self::checkRegData($data);
			$t = self::get();
			$a = array(
				self::FLD_NAME => self::COL_NAME
//			, self::FLD_SURNAME => self::COL_SURNAME
//			, self::FLD_FATHER => self::COL_FATHER
//			, self::FLD_CITIZEN => self::COL_CITIZEN
			, self::FLD_PASS2 => null
//			, self::FLD_BIRTH => self::COL_BIRTH
//			, self::FLD_GENDER => self::COL_GENDER
//			, self::FLD_ACTION => self::COL_ACTION
//			, self::FLD_PHONE => self::COL_PHONE
//			, self::FLD_MAIL => self::COL_MAIL
			);
			if ($data[self::FLD_PASS1])
				$a[self::FLD_PASS1] = self::COL_PASS;

			$u = array();
			$errors = array();
			foreach ($a as $field => $col) {
				$value = $data[$field];

				if ($err = $check[$field])
					$errors[$field] = $err;
				else
					if ($col && $value && ($t->_get($col) != $value))
						$u[$col] = $value;
			}
			if ($u[self::COL_PASS]) $u[self::COL_PASS] = md5($u[self::COL_PASS] . self::PASS_SALT);

			if (count($errors))
				return $errors;
			if (count($u)) {
				$dbc = msqlDB::o();
				$s = $dbc->update('users', $u, array(self::COL_ID => $t->_get(self::COL_ID)));
			}
			return array();
		}

		static function genPassword($len) {
			$result = '';
			$offset = ord('a');
			while ($len--) {
				$char = rand(0, 35);
				if ($char < 25)
					$char = rand(0, 9) > 4 ? strtoupper(chr($char + $offset)) : chr($char + $offset);
				else
					$char -= 26;
				$result .= $char;
			}
			return $result;
		}

		static function passReminder($login, $password, $newpass = false) {
			$config = FrontEnd::getInstance()->get('config');
			$subject = $config->get('site-title');
			$notifier = $config->get('mail-notifier');
			$tpl     = array(false => '_pwdremind.ini', true => '_pwdchange.ini');
			$content = file_get_contents(SUB_DOMEN . '/_engine/' . $tpl[$newpass]);
			$data = array('title' => $subject, 'login' => $login, 'password' => $password);
			$content = patternize($content, $data);
			$sent = mail($login, $subject, $content, 'From: ' . $notifier . ' <noreply@' . str_replace(array('http://', 'www.'), '', $_SERVER[HTTP_HOST]) . '>');

			if ($sent && $newpass) {
				$dbc = msqlDB::o();
				$s = $dbc->update('users', array(self::COL_PASS => md5($password . self::PASS_SALT)), self::COL_LOGIN . ' = \'' . $login . '\'');
			}
			return $sent;
		}

	}
