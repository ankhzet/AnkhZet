<html>
	<head>
		<script type="text/javascript" src="/tinymce/tinymce.min.js"></script>
		<script type="text/javascript">
			tinymce.init({
					selector: "textarea"
				, language: 'ru'
				, plugins: [
					"save advlist autolink lists link image charmap print preview anchor",
					"searchreplace visualblocks code fullscreen textcolor",
					"insertdatetime media table contextmenu paste"
				]
				, toolbar: "save undo redo | bold italic | alignleft aligncenter alignright alignjustify | forecolor backcolor | bullist numlist outdent indent | link image"
				, document_base_url: "{%host%}/"
				, relative_urls: false
				, body_class: "text"
				, body_id: "bigBlock"
				, content_css: "/theme/css/style.css?" + (new Date().getTime()) + ",/theme/css/upd.css?" + (new Date().getTime())
			});
		</script>
	</head>
	<body>
		<form style="height: 85%;" action="/templates/{%lang}/{%template}?back={%back}" method="POST">
			<textarea name="editor" style="width: 100%; height: 100%">{%contents}</textarea>
			<center><a href="/{%back}">Назад</a></center>
		</form>
	</body>
</html>