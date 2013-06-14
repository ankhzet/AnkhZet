
function show_error(title, contents) {
	upform.init({
	title: title
	, content: contents
	, onready: function(form) {
		form.show(function(){setTimeout(function(){form.close()}, 5000)})
	}
	});
}