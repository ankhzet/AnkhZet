function patternize(pattern, data) {
	return pattern.replace(/\{\%([^\}]+)\}/g, function(a, b) { return data[b]; });
}
function urldecode(str) {
	return decodeURIComponent((str+'').replace(/\+/g, '%20'));
}

function locate(location) {
	document.location.href = urldecode(location);
}

var mapEn = (
	"eh|ju|ja|shh|" +
	"ch|sh|kh|jo|" +
	"zh|" +
	"a|b|v|g|" +
	"d|e|z|i|" +
	"j|k|l|m|" +
	"n|o|p|r|" +
	"s|t|u|f|" +
	"c|y|-").split('|');

var mapRu = (
	"э|ю|я|щ|" +
	"ч|ш|х|ё|" +
	"ж|" +
	"а|б|в|г|" +
	"д|е|з|и|" +
	"й|к|л|м|" +
	"н|о|п|р|" +
	"с|т|у|ф|" +
	"ц|ы|--").split('|');


function inArray(char, array) {
	for (var i in array)
		if (char == array[i])
			return i;

	return false;
}

function translateStr(s) {
	var chars = s.split("");
	for (var i in chars) {
		var char = chars[i], j = inArray(char.toLowerCase(), mapRu);
		if (!char.match(/[a-z0-9\-\@\(\)]/i))
			if (j == false)
				while (s.indexOf(char) >= 0)
					s = s.replace(char, '-');
			else
				while (s.indexOf(char) >= 0)
					s = s.replace(char, mapEn[j]);
	}

	while (s.indexOf('--') >= 0)
		s = s.replace('--', '-');

	return s.replace(/^\-/, '').replace(/\-$/, '');
}

function translate() {
	var title = $('#iname'), link = $('#ilink');
	link.val(translateStr(title.val()));
}

$(document).ready(function() {
	$('[name="logo-iframe"]').load(function() {
		var response = $(this).contents().find('body').html();
		if (!response) return;
		$(this).contents().find('body').html('');
		var r = {result: 'parse'};
		try { r = eval('(' + response + ')'); } catch (e) {
			try { r = eval('a = ' + response); } catch (e) {};
		}
		switch (r.result) {
		case 'ok': // finally ^_^"
			$('[target="logo-iframe"] [src]').attr('src', r.data + '?rnd' + Math.round(Math.random() * 10000));
			$('[name="logo"], [name="preview"]').val(r.data);
			break;
		case 'parse':
		case 'err':
			alert('Upload error: ' + r.data + "\n\n" + response);
			break;
		}
	});
	$('#ilogo').change(function() {
		$('[target="logo-iframe"]').submit();
	});

	var chart = $('.profiler-chart')[0];
	if (chart) {
		$('.ap-admin .ap-drop').append(chart);
		$(chart).load(fitAdminPanel);
	}
	$('.ap-admin>a').click(function(){
		var drop = $(this).parents('.ap-admin:first');
		if (drop.hasClass('selected')) {
			setupProfilerReload(chart);
			drop.removeClass('selected');
		} else {
			drop.addClass('selected');
			fitAdminPanel();
			setupProfilerReload(chart, true);
		}

		return false;
	});
	$(':not(.ap-admin>a)').click(function(){setupProfilerReload(chart);$('.ap-admin').removeClass('selected');});

	$("a").each(function(){
		var href = this.href;
		if (this.href.indexOf("delete") >= 0)
			$(this).click(function() {
				upform.init({
					title: 'Удаление'
				, content: '<div class="static">Вы действительно хотите выполнить это действие?<br/>[<span style="color: red;">' + this.href + '</span>]</div>'
				, controls: [
					{action: "javascript:locate('" + encodeURIComponent(href) + "')", caption: 'Удалить'}
				, upform.BTN_CLOSE]
				, onready: function(){
					upform.show();
				}
				});
				return false;
			});
	});
});

function setupProfilerReload(chart, run) {
	if (!chart) return;
	clearInterval(chart.reloader);
	if (run)
		chart.reloader = setInterval(function(){chart.src="/models/core_timeleech.php?" + Math.random()}, 1000);
}

function fitAdminPanel() {
	var
		down = $('.ap-admin .ap-drop')
	, o = down.offset()
	, w = $('.ap-admin .ap-drop').width()
	, b = $('#content .content').width()
	, d = ((o.left - parseInt(down.css('margin-left'))) + w) - b
	;

	down.css('margin-left', ( - d - $('.ap-admin>a').width() - 25) + 'px');
}


