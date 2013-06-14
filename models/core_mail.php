<?php
	if (ROOT == 'ROOT') die('oO');

	require_once 'dbengine.php';
	require_once 'user.php';

	/*
		Tags:
			0 - normal (just send)
			1 - readed
			2 - deleted

			tag1 - sender tag
			tag2 - recv tag
	*/

	define('TAG_NORMAL', 0);
	define('TAG_READED', 1);
	define('TAG_DELETED', 2);

	class SA_Mail {
		private $dbc = null;
		var     $user= null;
		var     $collumns = array(
			'`id` int auto_increment null'
		, '`sender` varchar(200) not null'
		, '`subject` varchar(200) not null'
		, '`content` varchar(500) not null'
		, '`tag` tinyint not null'
		, '`date` int not null'
		, 'primary key (`id`)'
		);

		function __construct() {
			$this->dbc  = msqlDB::o();
			$this->user = intval(User::get()->_get(User::COL_ID));
		}

		function init_table() {
			return $this->dbc->create_table('_mail', $this->collumns);
		}

		function queryCount($query) {
			$s = $this->dbc->select('_mail', $query, 'count(id) as c');
			return intval(@mysql_result($s, 0));
		}

		function inboxCount() {
			return $this->queryCount('`tag` <> 2');
		}
		function inboxNew() {
			return $this->queryCount('`tag` = 0');
		}
		function trashboxCount() {
			return $this->queryCount('`tag` = 2');
		}

		function fetchInbox() { // for receiver, so tag 2
			$s = $this->dbc->select('_mail', '`tag` <> 2 order by `date` desc');
			return $this->dbc->fetchrows($s);
		}

		function fetchDeleted() { // both tags
			$s = $this->dbc->select('_mail', '`tag` = 2 order by `date` desc');
			return $this->dbc->fetchrows($s);
		}

		function fetchMessage($id) {
			$s = $this->dbc->select('_mail', '`id` = \'' . $id . '\' limit 1');
			return $s ? @mysql_fetch_assoc($s) : null;
		}

		function messageState($m) {
			if (!$m) return -1;
			return intval($m['tag']);
		}

		function markMessage($id, $mark) {
			$m = $this->fetchMessage($id);
			$s = $this->messageState($m);
			if ($s >= 0) {
				return $this->dbc->update('_mail', array('tag' => intval($mark)), array('id' => intval($id)));
			} else
				return -1;
		}

		function sendMsg($sender, $subject, $contents) {
			return $this->dbc->insert('_mail', array(
					'sender' => $sender
				, 'subject' => $subject
				, 'content' => $contents
				, 'date' => time()
			));
		}
	}
?>
