<?php

	/* ---------------------- Table renderer --------------------- */
	require_once 'table.php';

	class MetaTableRenderer extends DBTableRenderer {
		const T_X       = '';
		const T_NAME    = 'name';
		const T_CONTENT = 'content';

		function __construct($p, $link) {
			$this->params = $p;
			parent::__construct($link, 'meta');
		}

		function columns() {
			return array(self::T_X, self::T_NAME, self::T_CONTENT);
		}

		function colWidths() {
			return array(self::T_X => '24px', self::T_CONTENT => 70);
		}

		function colTitles() {
			return array(
					self::T_NAME => 'Name'
				, self::T_CONTENT => 'Content'
			);
		}

		function prepareRow($column, $data) {
			switch ($column) {
			case self::T_X:
				return '<a class="del" href="/admin/meta/delete/' . $data[self::T_ID] . '"></a>';
			case self::T_NAME:
				return '<a href="/admin/meta/' . $data[self::T_ID] . '">' . $data[self::T_NAME] . '</a>';
			case self::T_CONTENT:
				$d = $data[$column];
				if (strlen($d) < 145)
					return $d;
				else
					return substr($d, 0, 242) . '...';
			default:
				return $data[$column];
			}
		}
	}

/* --------------------------------- */

	function getParam($params, $param) {
		if (!($p = $params[$param]))
			return $_REQUEST[$param];
		else
			return $p;
	}

	$r = $this->request->getList();
	array_shift($r);
	$p = array_shift($r);
	switch ($p) {
	case 'delete':
		$id = intval($r[0]);
		$o = msqlDB::o();
		$o->delete('meta', '`id` = \'' . $id . '\' and `name` != \'generator\'');
		locate_to('/admin/meta');
		break;
	case 'add':
		$name = addslashes($_REQUEST['name']);
		$content = addslashes($_REQUEST['content']);
		$id = intval($_REQUEST['id']);
		$a = array('name' => $name, 'content' => $content);
		$o = msqlDB::o();
		if ($id)
			$o->update('meta', $a, '`id` = \'' . $id . '\'');
		else {
			$s = $o->insert('meta', $a, true);
			$r = @mysql_fetch_row($s);
			$id = intval($r[0]);
		}
		locate_to('/admin/meta/' . $id);
		break;
	default      :
	}

	$r = new MetaTableRenderer($this->request->getArgs(), '/admin/meta/?');
	echo '<div id="dtcontainer">';
	$r->render();
	echo '</div>';
	$id = intval($p);
	$r = null;
	if ($id) {
		$o = msqlDB::o();
		$s = $o->select('meta', '`id` = \'' . $id . '\'');
		$r = $s ? $o->fetchrows($s) : 0;
		$r = $r[0];
	}

	$name = getParam($r, 'name');
	$content = getParam($r, 'content');
?>
<form id="upload" class="long" action="/admin/meta/add" method="POST" >
	<input type=hidden name=id value="<?echo $id?>" />
	<h3><?echo $id ? 'Редактирование МЕТА-данных' : 'Добавить МЕТА-тег'?></h3>
	<div>
		<label for=iname>Имя (name):</label>
		<input type=text id=iname name=name value="<?echo $name?>" />
	</div>
	<div>
		<label for=icontent>Содержание (content):</label>
		<textarea id=icontent name=content><?echo $content?></textarea>
	</div>
	<div>
		<label for=isubmit></label>
		<input id=isubmit type=submit value="<?echo $id ? 'Сохранить' : 'Добавить'?>" />
	</div>
</form>
<?php
	if ($id) {
		echo '<center>';
		$this->renderButton('Назад', '/admin/meta');
		echo '</center>';
	}
?>
