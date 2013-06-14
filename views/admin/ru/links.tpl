<div id="config">
	<form class="adminfunctions">
		<h3><span></span>Админ-функции</h3>
		<div><label class="afuserlist"></label><a href="/admin/userlist"><?=Loc::lget('titles.adminmoderusers')?></a></div>
		<div><label class="afacl"></label><a href="/acl"><?=Loc::lget('titles.acl')?></a></div>
		<div><label class="afconfig"></label><a href="/config"><?=Loc::lget('titles.adminconfig')?></a></div>
		<div><label class="afmeta"></label><a href="/admin/meta"><?=Loc::lget('titles.adminmeta')?></a></div>
		<div><label class="aftemplates"></label><a href="/admin/templates"><?=Loc::lget('titles.admintemplates')?></a></div>
		<div><label></label></div>
		<div><label class="afreset"></label><a href="/admin/confirm/reset?return=admin"><?=Loc::lget('titles.adminresetall')?></a></div>
	</form>
</div>
