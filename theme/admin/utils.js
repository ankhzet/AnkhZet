function has(arr, el) {
	for (var i in arr)
		if (arr[i] == el) return true;
	return false;
}

function toJSON(obj, r) {
	if (obj === null) return 'null';
	if (r == undefined) r = [];

	var type = typeof obj;
	switch (type) {
		case 'undefined':
		case 'unknown'  : return type;
		case 'function' : return /*obj.toString();//*/ '[' + type + ']';
		case 'string'   : return '"' + obj.toString() + '"';
		case 'number'   :
		case 'boolean'  : return obj.toString();
		default:
			if (!(/^\[object object\]$/i.test(obj.toString()))) return obj.toString();

			if (has(r, obj)) return '(* ' + obj.toString() + ')';
			r.push(obj);

			if (typeof obj.length !== 'undefined') {
				var vals = [];
				for (var prop in obj) {
					var val = toJSON(obj[prop], r);
					if (typeof val !== 'undefined')
						 vals.push(val);
				};
				return '[' + vals.join(', ') + ']';
			} else {
				var vals = [];
				for (var prop in obj) {
					var val = toJSON(obj[prop], r);
					if (typeof val !== 'undefined')
						vals.push(prop + " = " + val);
				};
				return '{\n' + vals.join(';\n ') + '\n}';
			}
	}
}

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
		chart.reloader = setInterval(function(){chart.src="/models/core_timeleech.php?" + Math.random()}, 5000);
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

function drop(array, element) {
	return $.grep(array, function(value) { return value != element; });
}

var
	updatesService = new function() {
		var AJAX_MAX_RETRIES = 10;
		$(function(){updatesService.init();});

		this.$ = function(css) {
			return $('.update-service ' + css);
		}
		this.json = function(action, params, depth) {
			var original = params;
			var callbacks = {'success': params.success};
			var error = params.error || this.error;
			callbacks.error = (!params.retry) ? error : function (response) {
				depth = depth ? depth + 1 : 1;
				if (depth > AJAX_MAX_RETRIES)
					return error(personse);


				updatesService.json(action, original, depth);
			};

			delete(params.success);
			delete(params.error);
			delete(params.retry);
			$.getJSON('/clientapi.json?api-action=' + action, params)
			.success(function(response) {
				if (response.result != 'ok') {
					callbacks.error(response.data);
					return;
				}
				callbacks.success(response.data);
			})
			.error(function(response){
				error(response.responseText);
			});
		}
		this.init = function() {
			this.$('.update-btn').click(function(){updatesService.checkUpdates();});
		}

		this.checkUpdates = function() {
			this.json('authorsToUpdate', {'force': 1, 'all': 1
			, 'success': function (data) {
					updatesService.serveAuthors(data);
				}
			});

		}

		var AS_WAIT = 1;
		var AS_PROC = 2;
		var AS_SERV = 3;
		var AS_FAIL = 4;
		var queue1 = [];
		var queue2 = [];
		var updates = [];
		var div = '\
		<div class="author" id="author-plate-{%id}" style="display: inline-block; width: 100%; border: 1px solid gray; border-radius: 4px;">\
			<div>\
				<div class="state" style="width: 100px; text-align: center; padding: 0 5px; display: inline-block;"></div>\
				<a href="/authors/id/{%id}">{%fio}</a>\
			</div>\
			<div>\
				<div class="timings" style="font-size: 8px; color: #999; text-align: center; width: 100px; padding: 0 5px; display: inline-block;"></div>\
				<div class="updates"></div>\
			</div>\
		</div>';
		this.serveAuthors = function(authors) {
			this.$('.authors').empty();
			this.queue1 = authors;
			this.queue2 = authors;
			for (var i in authors) {
				this.formAuthorPlate(authors[i]);
			}

		}
		this.readyForService = function() {
			var authors = this.queue2;
			alert(authors);
			for (var i in authors) {
				var plate = this.$('#author-plate-' + authors[i]);
				this.$('.temp').append(plate);
				this.$('.authors').append(plate);
			}
			this.serveAuthor();
		}
		this.authorsServed = function() {
			alert('done');
		}
		this.serveAuthor = function() {
			var id = this.hasQueuedForServe();
			if (!id)
				return this.authorsServed();

			this.authorState(id, AS_PROC);
			this.json('authorUpdate', {'id': id
			, 'success': function(data) {
					updatesService.authorServed(id, data);
					updatesService.serveAuthor();
				}
			, 'error': function(data) {
					updatesService.authorState(id, AS_FAIL);
					updatesService.error(data);
				}
			});
		}
		this.authorServed = function(id, data) {
			updatesService.authorState(id, AS_SERV);

			var plate = this.$('#author-plate-' + id);
			var updates = this.$('.all-updates');

			data['load-timings'] = { "speed": "10.12 Кб" ,"length": "18.94 Кб" ,"time": 1.872 };
			if (data['load-timings']) {
				plate.find('.timings').html(patternize("{%speed}<br/>({%length}/{%time})", data['load-timings']));
			}
			var cast = parseFloat(data['cast-for']);
			if (!!data['pages-updated']) {
				var all = data['pages-queued'][0];
				var links = data['pages-queued-links'];
				for (var i in links) {
					var queued = links[i];
					var page = parseInt(queued['page-id']);
					if (!page) continue;
					var link = queued.link;
					all = drop(all, link);
					updates.append(patternize('<div>#{%page-id}: {%link}</div>', queued));
				}
				if (all.length)
					alert('Already queued: ' + all);
			}
		}

		this.hasQueuedForAquire = function() {
			return !!this.queue1.length;
		}
		this.aquired = function(id) {
			this.queue1 = drop(this.queue1, id);
		}
		this.hasQueuedForServe = function() {
			return this.queue2.length ? this.queue2.shift() : false;
		}
		this.served = function(id, state) {
			this.queue2 = drop(this.queue2, id);
			this.authorState(id, state);
		}

		this.formAuthorPlate = function(id) {
			this.json('author', {'id': id
			, 'success': function (data) {
					updatesService.aquired(id);
					updatesService.$('.authors').append(patternize(div, data));
					updatesService.authorState(id, AS_WAIT);
					if (!updatesService.hasQueuedForAquire())
						updatesService.readyForService();
				}
			, 'retry': true
			});
		}

		this.authorState = function(id, state) {
			var color = ['', 'silver', 'blue', 'green', 'red'];
			var label = ['', 'WAIT', 'PROCESS', 'SERVED', 'FAILED'];
			var stateDIV = this.$('#author-plate-' + id + ' .state');
			stateDIV.html(label[state]);
			stateDIV.css({'color': color[state]});
		}

		this.error = function(message) {
			alert('Error: ' + message);
		}
	}