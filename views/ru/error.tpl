<?php
	$errs = $this->errors ? $this->errors : array();
	if (count($errs) > 0) {
		foreach ($errs as $err)
			$this->renderMessage(Loc::lget($err), View::MSG_ERROR);
	}
?>
<a class="reverse_link" href="javascript:history.back(0);"></a>