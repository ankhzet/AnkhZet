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
		var AS_WAIT = 1;
		var AS_PROC = 2;
		var AS_SERV = 3;
		var AS_FAIL = 4;
		var AS_UPD  = 5;
		var queue1 = [];
		var queue2 = [];
		var waits = 0;
		var authorData = [];
		var timings = [];
		var authorsID = [];
		var div = '\
		<div class="author" id="author-plate-{%id}" data-id="{%id}" style="display: inline-block; width: 100%; border: 1px solid gray; border-radius: 4px;">\
			<div>\
				<div class="state" style="width: 100px; text-align: center; padding: 0 5px; display: inline-block;"></div>\
				<a href="/authors/id/{%id}">{%fio}</a>\
			</div>\
			<div>\
				<div class="timings" style="font-size: 8px; color: #999; text-align: center; width: 100px; padding: 0 5px; display: inline-block;"></div>\
				<div class="updates"></div>\
			</div>\
		</div>';
		var globalTimer = 0;
		var displayTimer = 0;
		var startedAt = 0;
		var checkFrequency = 1000 * 60 * 5;
		$(function(){updatesService.init();});

		this.$ = function(css) {
			return $('.update-service ' + css);
		}
		this.json = function(action, params, depth) {
			var original = params;
			var callbacks = {'success': params.success || function(x){}};
			var error = params.error || this.error;
			callbacks.error = (!params.retry) ? error : function (response) {
				depth = depth ? depth + 1 : 1;
				if (depth > AJAX_MAX_RETRIES)
					return error(response);
				updatesService.json(action, original, depth);
			};

			delete(params.success);
			delete(params.error);
			delete(params.retry);
			$.getJSON('/clientapi.json?api-action=' + action, params)
			.success(function(response) {
				if (response.result != 'ok') {
					callbacks.error(response.data, true);
					return;
				}
				callbacks.success(response.data);
			})
			.error(function(response){
				error(response.responseText);
			});
		}
		this.init = function() {
			if (!this.$('').length)
				return;

			this.toggleUI('.update-btn', true);
			this.toggleUI('.load-btn', false);
			this.$('.update-btn').click(function(){updatesService.checkUpdates();});
			this.$('.load-btn').click(function(){updatesService.loadUpdates();});

			this.serveTimers();
			displayTimer = setInterval(function(){updatesService.updateTimers();}, 100);
			updatesService.checkUpdates();
		}

		this.toggleUI = function (selector, enable) {
			this.$(selector).css({'color': enable ? 'blue' : 'silver'});
		}

		this.serveTimers = function(drop) {
			clearTimeout(globalTimer);
			globalTimer = 0;
			if (drop)
				return;

			startedAt = (new Date()).getTime();
			globalTimer = setTimeout(function(){
				updatesService.checkUpdates();
			}, checkFrequency);
		}
		this.updateTimers = function() {
			var timerDIV = this.$('.timer-div');
			if (!globalTimer) {
				timerDIV.html('working...');
				return;
			}

			var now = (new Date()).getTime();
			var delta = checkFrequency - (now - startedAt);
			var time = (new Date(delta)).toUTCString();
			time = time.match(/((\d+)\:[^ ]+)/);
			timerDIV.html(time[1]);
		}

		var first_time = 1;
		this.checkUpdates = function() {
			this.serveTimers(true);
			this.toggleUI('.update-btn', true);
			var force = !authorsID.length;
			this.json('authorsToUpdate', {'force': 0/*force ? 1 : 0*/, 'all': 1/*first_time*/
			, 'success': function (data) {
					updatesService.first_time = 0;
					updatesService.serveAuthors(data);
				}
			, 'error': function(response, verbose) {
					updatesService.error(response, verbose);
					updatesService.checkUpdates();
				}
			});
		}

		this.loadUpdates = function() {
			if (!waits) {
				this.$('.author').each(function(){
					var id = parseInt($(this).attr('data-id'));
					updatesService.authorState(id, AS_WAIT);
				});
				this.serveTimers();
				return;
			}

			this.toggleUI('.update-btn', false);
			this.toggleUI('.load-btn', false);
			this.json('loadUpdates', {
				'success': function(data) {
					updatesService.toggleUI('.load-btn', true);
					var left = parseInt(data.left);
					updatesService.dropUpdates(left);
					updatesService.loadUpdates();
				}
			, 'error': function (response, verbose) {
					updatesService.toggleUI('.load-btn', true);
					updatesService.error(response, verbose);
					updatesService.loadUpdates();
				}
			});
		}
		this.dropUpdates = function(left) {
			if ((waits - left) > 0)
				this.error('Loaded ' + (waits - left) + ' updates', true);
			waits = left;
		}

		this.serveAuthors = function(authors) {
			if (!authors.length) {
				this.serveTimers();
				return;
			}
			queue1 = authors;
			queue2 = authors;
			authorsID = authors;
			authorData = [];
			for (var i in authors)
				this.formAuthorPlate(authors[i]);
		}
		this.readyForService = function() {
			var authors = queue2;
			for (var i in authors) {
				var plate = this.$('#author-plate-' + authors[i]);
				this.$('.temp').append(plate);
				this.$('.authors').append(plate);
			}
			this.serveAuthor();
		}
		this.authorsServed = function() {
			this.json('updatesCalcFreq', {
				'success': function() {
					updatesService.toggleUI('.update-btn', true);
					updatesService.toggleUI('.load-btn', true);
					updatesService.loadUpdates();
				}
			, 'error': function(response, verbose) {
					updatesService.error(response, verbose);
					updatesService.authorsServed();
				}
			});
		}
		this.serveAuthor = function(author) {
			var id = author || this.hasQueuedForServe();
			if (!id)
				return this.authorsServed();

			this.authorState(id, AS_PROC);
			var plate = this.$('#author-plate-' + id);
			this.$('.temp').append(plate);
			this.$('.authors').prepend(plate);

			this.json('authorUpdate', {'id': id
			, 'success': function(data) {
					updatesService.authorServed(id, data);
					updatesService.serveAuthor();
				}
			, 'error': function(data) {
					updatesService.authorState(id, AS_FAIL);
					updatesService.queueForServe(id);
					updatesService.serveAuthor();
				}
			});
		}
		this.authorServed = function(id, data) {
			this.authorState(id, AS_SERV);
			var plate = this.$('#author-plate-' + id);
			var updates = $('<UL style="padding-left: 10px;">');
			var updatesDIV = this.$('.all-updates').append('<DIV></DIV>');
			var authorDIV = $('<div><a href="/authors/id/' + id + '">' + authorData[id].fio + '</a></div>');
			authorDIV.append('<DIV></DIV>').append(updates);

			if (data['load-timings']) {
				plate.find('.timings').html(patternize("{%speed}<br/>({%length}/{%time})", data['load-timings']));
			}

			var cast = parseFloat(data['cast-for']);
			var mainUpdated = !!data['pages-updated'];
			var groupsUpdated = !!data['groups-updates'];

			if (groupsUpdated) {
				for (var i in data['groups-updates']) {
					var group = data['groups-updates'][i];
					var groupUL = $('<UL style="padding-left: 10px;">');
					updates.append(patternize('<div><a href="/pages?group={%group-id}">{%group-title}</a>:</div>', group))
					.append(groupUL);

					for (var j in group['updates']['pages-queued']) {
						var queued = group['updates']['pages-queued'][j];
						if (!parseInt(queued['page-id'])) continue;
						waits ++;
						groupUL.append(patternize('<li><a href="/pages/version/{%page-id}">{%link}</a></li>', queued));
					}
				}
			}

			if (mainUpdated) {
				var all = data['pages-queued'][0];
				var links = data['pages-queued-links'];
				for (var i in links) {
					var queued = links[i];
					var page = parseInt(queued['page-id']);
					if (!page) continue;
					var link = queued.link;
					all = drop(all, link);
					waits ++;
					updates.append(patternize('<li><a href="/pages/version/{%page-id}">{%link}</a></li>', queued));
				}
				if (all.length)
					updatesService.error('Already queued: ' + all, true);

				this.authorState(id, AS_UPD);
			}

			if (!(mainUpdated || groupsUpdated)) {
				this.$('.temp').append(plate);
				this.$('.authors').append(plate);
			} else {
				updatesDIV.prepend(authorDIV);
			}
		}

		this.hasQueuedForAquire = function() {
			return !!queue1.length;
		}
		this.aquired = function(id) {
			queue1 = drop(queue1, id);
		}
		this.hasQueuedForServe = function() {
			return queue2.length ? queue2.shift() : false;
		}
		this.queueForServe = function(id) {
			queue2.push(id);
		}
		this.served = function(id, state) {
			queue2 = drop(queue2, id);
			this.authorState(id, state);
		}

		this.formAuthorPlate = function(id) {
			if (authorData[parseInt(id)])
				return this.authorDataAquired(id);

			this.json('author', {'id': id
			, 'success': function (data) {
					updatesService.regAuthorData(id, data);
					updatesService.authorDataAquired(id);
				}
			, 'error': function (data) {
					updatesService.error('Network error (auid: ' + id + ')', true);
					updatesService.formAuthorPlate(id);
				}
			});
		}
		this.authorDataAquired = function(id) {
			var data = authorData[parseInt(id)];
			this.$('#author-plate-' + id).remove();
			this.$('.authors').append(patternize(div, data));

			this.aquired(id);
			this.authorState(id, AS_WAIT);
			if (!this.hasQueuedForAquire())
				this.readyForService();
		}

		this.regAuthorData = function (id, data) {
			authorData[parseInt(id)] = data;
		}

		this.authorState = function(id, state) {
			var color = ['', 'silver', 'blue', 'green', 'red', 'lime'];
			var label = ['', 'WAIT', 'PROCESS', 'SERVED', '<span onclick="updatesService.serveAuthor(' + id + ')">FAILED</span>', 'UPDATES'];
			var stateDIV = this.$('#author-plate-' + id + ' .state');
			stateDIV.html(label[state]);
			stateDIV.css({'color': color[state]});
		}

		var errorTimeout = 0;
		this.error = function(message, verbose) {
			this.clearError();
			if (verbose)
				this.$('.error-div').html(message);
			else
				this.$('.error-div').html('Network error');

			errorTimeout = setTimeout(function(){updatesService.clearError();}, 5000);
		}
		this.clearError = function() {
			if (errorTimeout)
				clearTimeout(errorTimeout);
			this.$('.error-div').html('');
		}
	}