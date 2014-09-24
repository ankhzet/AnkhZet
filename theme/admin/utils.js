function patternize(pattern, data) {
	return pattern.replace(/\{\%([^\}]+)\}/g, function(a, b) { return (data && (typeof data[b] !== 'undefined')) ? data[b] : "{" + b + "}"; });
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
				<div class="state" style="width: 140px; text-align: center; padding: 0 5px; display: inline-block;"></div>\
				<a href="/authors/id/{%id}">{%fio}</a>\
			</div>\
			<div style="font-size: 8px; color: #999;">\
				<div class="timings" style="float: left; text-align: center; width: 140px; padding: 0 5px; display: inline-block;">&nbsp;</div>\
				<div class="updates" style="float: left; padding: 0 5px;"><span class="loaded">0</span>/<span class="total">0</span></div>\
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
			$.getJSON('/api/admin/' + action, params)
			.success(function(response) {
				if (response.result != 'ok') {
					callbacks.error(response.data, true);
					return;
				}
				callbacks.success(response.data);
			})
			.error(function(response, status, err){
				error(status + ': ' + err, true);
			});
		}
		this.init = function() {
			if (!this.$('').length)
				return;

			this.toggleUI('.update-btn', true);
			this.toggleUI('.load-btn', false);
			this.$('.update-btn').click(function(){updatesService.checkUpdates();});
			this.$('.load-btn').click(function(){updatesService.loadUpdates(true);});

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
			this.json('authors-to-update', {'force': 0/*force ? 1 : 0*/, 'all': 1/*first_time*/
			, 'success': function (data) {
					updatesService.first_time = 0;
					updatesService.serveAuthors(data);
				}
			, 'error': function(response, verbose) {
					updatesService.error(response, verbose);
					setTimeout(function(){updatesService.checkUpdates();}, 500);
				}
			});
		}

		this.loadUpdates = function(force) {
			if (!(force || waits)) {
				this.$('.author').each(function(){
					var id = parseInt($(this).attr('data-id'));
					updatesService.authorState(id, AS_WAIT);
				});
				this.serveTimers();
				return;
			}

			if (!this.doLoadUpdates())
				return;

			this.toggleUI('.update-btn', false);
			this.toggleUI('.load-btn', false);
			this.json('load-updates', {
				'success': function(data) {
					updatesService.toggleUI('.load-btn', true);
					updatesService.processLoaded(data);
				}
			, 'error': function (response, verbose) {
					updatesService.toggleUI('.load-btn', true);
					updatesService.error(response, verbose);
					updatesService.loadUpdates();
				}
			});
		}

		this.processLoaded = function(data) {

			var updated = 0;
			var colors = {'deleted': 'maroon', 'fail': 'red', 'no-diff': 'silver'};
			var fails = ['deleted', 'fail', 'no-diff'];
			for (var j in fails) {
				var type = fails[j];
				var pages = data[type] || [];
				for (var i in pages) {
					var page = pages[i];
					var pageID = parseInt(page['page-id']);
					var authorID = parseInt(page['author']);

					var p = this.$('#versions-' + pageID);
					p.css('color', colors[type]).attr('id', null);

					this.authorNewUpdates(authorID, 0, -1);
				}
			}

			if (data.diff)
				for (var i in data.diff) {
					var page = data.diff[i];
					var pageID = parseInt(page['page-id']);
					var authorID = parseInt(page['author']);

					var p = this.$('#versions-' + pageID);
					p.css('color', 'navy');
					p.attr('href', p.attr('a-href'));
					p.mousedown(function(){$(this).css('color', 'green').attr('id', null)});

					updated++;
					this.authorNewUpdates(authorID, 1, 0);
				}

			this.dropUpdates(updated, parseInt(data.left));
			setTimeout(function(){updatesService.loadUpdates();}, 200);
		}

		this.dropUpdates = function(loaded, left) {
			this.error('Loaded ' + loaded + '/' + (loaded + left) + ' updates', true);
			waits = left;
		}

		this.doLoadUpdates = function() {
			return this.$('#do-load-check').is(':checked');
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
			var toAquire = [];

			for (var i in authors)
				if (!authorData[parseInt(authors[i])])
					toAquire.push(authors[i]);

			if (toAquire.length)
				this.json('authors', {'id': toAquire.join(',')
				, 'success': function (data) {
						for (var i in data) {
							var author = data[i];
							updatesService.regAuthorData(author.id, author);
							updatesService.authorDataAquired(author.id);
						}
					}
				, 'error': function (data) {
						for (var i in authors) {
							var id = authors[i];
							updatesService.error('Network error (auid: ' + id + ')', true);
							updatesService.authorDataAquired(id);
						}
					}
				});
			else
				for (var i in authors)
					this.authorDataAquired(authors[i]);
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
			this.json('updates-calc-freq', {
				'success': function() {
					updatesService.toggleUI('.update-btn', true);
					updatesService.toggleUI('.load-btn', true);
					updatesService.loadUpdates();
				}
			, 'error': function(response, verbose) {
					updatesService.error(response, verbose);
					updatesService.loadUpdates();
//					updatesService.authorsServed();
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

			this.json('author-update', {'id': id
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

//			alert(toJSON(data));
			var cast = parseFloat(data['cast-for']);
			if ((cast != 0) && (data['load-timings'] && (data['load-timings'] != ''))) {
				plate.find('.timings').html(patternize("{%speed} ({%length}/{%time})", data['load-timings']));
			} else
				plate.find('.timings').html('***');

			var mainUpdated = !!data['pages-updated'];
			var groupsUpdated = !!data['groups-updates'];

			var changes = 0;
			if (groupsUpdated) {
				for (var i in data['groups-updates']) {
					var group = data['groups-updates'][i];
					var groupUL = $('<UL style="padding-left: 10px;">');
					updates.append(patternize('<div><a href="/pages?group={%group-id}">{%group-title}</a>:</div>', group))
					.append(groupUL);

					var u = this.showUpdates(id, updates, group['updates']);
					changes+= u[0];
				}
			}

			var u = this.showUpdates(id, updates, data);
			changes += u[0];

			this.authorState(id, AS_UPD);

			if (!changes) {
				this.$('.temp').append(plate);
				this.$('.authors').append(plate);
			} else {
				updatesDIV.prepend(authorDIV);
			}
		}

		this.showUpdates = function (authorID, container, data) {
			var links = data['pages-queued-links'] || [];
			var queuedPages = data['pages-queued'] || [];
			var newPages = data['pages-new'] || [];
			var renamedPages = data['pages-changed-title'] || [];

			var changes = 0, updates = 0;
			var typed = {'added': newPages, 'diff': queuedPages};
			var blocks = {'diff': [], 'added': [], 'renamed': [], 'moved': [], 'removed': []};
			var used = {};
			for (var type in typed) {
				pages = typed[type];
				for (var i in pages) {
					var page = pages[i];
					var pageID = parseInt(page['page-id']) || parseInt(page['id']);
					if (!(pageID && !used['p' + pageID])) continue;
					used['p' + pageID] = true;

					var link = page.link;
					links = drop(links, link);
					if (page.title == '') page.title = '&lt;no title&gt;';
					page['id'] = pageID;
					page['page-id'] = pageID;

					blocks[type].push(this.appendUpdate(
						'&laquo;<a style="color: gray" target="_blank" id="versions-{%page-id}" a-href="/pages/version/{%page-id}?r={%random}">{%title}</a>&raquo;'
					, page));

					waits++;
					changes++;
					updates++;
				}
			}

			for (var i in renamedPages) {
				var page = renamedPages[i];
				var pageID = parseInt(page['page-id']) || parseInt(page['id']);
				if (!pageID) continue;

				var link = page.link;
				links = drop(links, link);
				if (page.title == '') page.title = '&lt;no title&gt;';
				page['id'] = pageID;
				page['page-id'] = pageID;

				blocks['renamed'].push(this.appendUpdate(
					'&laquo;<a href="/pages/version/{%id}?r={%random}">{%title}</a>&raquo; &rarr; &laquo;<a href="/pages/version/{%id}?r={%random}">{%new-title}</a>&raquo;'
				, page));
				changes++;
			}

			if (links.length)
				updatesService.error('Already queued: ' + links, true);

			for (var type in blocks) {
				if (!blocks[type].length) continue;
				var outer = $(patternize('<span class="page-update {%type}"></span>', {'type': type}));
				outer.append('<ul>' + blocks[type].join('\n') + '</ul>');
				container.append($('<div>').append(outer));
			}
			this.authorNewUpdates(authorID, 0, updates);

			return [changes, links];
		}

		this.appendUpdate = function (pattern, data) {
			var time = (new Date()).toUTCString();
			time = time.match(/((\d+)\:[^ ]+)/);

			data['random'] = Math.round(111111 + Math.random() * 888889);
			return patternize(
				'<li>{%time}: <span>{%update}</span></li>'
			, {'time': time[1], 'update': patternize(pattern, data)}
			);
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

		this.authorNewUpdates = function(id, loaded, total) {
			var color = ['silver', 'lime'];
			var updatesDIV = this.$('#author-plate-' + id + ' .updates');
			var lDIV = updatesDIV.find('.loaded');
			var tDIV = updatesDIV.find('.total');
			var prevLoaded = parseInt(lDIV.text()) + loaded;
			var prevTotal = parseInt(tDIV.text()) + total;
			lDIV.text(prevLoaded);
			tDIV.text(prevTotal);
			updatesDIV.css({'color': color[(prevLoaded + prevTotal) > 0]});
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

	/* ============================================= */

	compositerServant = new function() {
		var AJAX_MAX_RETRIES = 10;
		var PATT_DETOUCHER = '<a href="javascript:void(0)" data-id="{%composition}" class="composition-detouch">X</a>';
		var PATT_ATTOUCHER = '<a href="javascript:void(0)" data-id="{%composition}" class="composition-attach">&rarr;</a>';
		var PATT_ORDERDOWN = '<a com-id="{%composition}" page-id="{%page}" class="composition-page-up">&darr;</a>';
		var PATT_ORDERUP   = '<a com-id="{%composition}" page-id="{%page}" class="composition-page-down">&uarr;</a>';
		var PATT_PAGE      = '<li>[{%order}: {%up} {%down}] <a href="/pages?group={%group}">{%group:title}</a> - <a href="/pages/version/{%page}">{%title}</a></li>';
		var summoner = null;
		$(function() {
			$('.composite-summoner').click(function(event){compositerServant.show($(this));event.stopPropagation();return false;});
			$(':not(.composite-summoner)').click(function(){if ($(this).parents('.composite-summoner').length) return; compositerServant.hide();});
			$('.composite-summoner>div *').click(function(event){event.stopPropagation();return true;});
			$('.composition-create').click(function() {compositerServant.createComposition($(this));});
		});

		this.show = function(source) {
			summoner = source;

			this.$('>div').show();
			this.fetchRelated(parseInt(summoner.attr('data-id')));
		}
		this.hide = function(source) {
			$('.composite-summoner>div').hide();
		}
		this.fetchRelated = function(pageID) {
			this.json('composition-related', {'page': pageID
			, 'success': function (data) {
					compositerServant.attachedTo('can-be-in', 'Can be added to', pageID, data, PATT_ATTOUCHER);
					compositerServant.fetchContained(pageID);
				}
			, 'error': function (e, v) {
				compositerServant.error(e, v);
				compositerServant.fetchRelated(pageID);
			}
			});
		}
		this.fetchContained = function(pageID) {
			this.json('composition-state', {'page': pageID
			, 'success': function (data) {
					compositerServant.attachedTo('is-in', 'Remove from', pageID, data, PATT_DETOUCHER);
				}
			, 'error': function (e, v) {
				compositerServant.error(e, v);
				compositerServant.fetchContained(pageID);
			}
			});
		}

		this.attachedTo = function (target, header, pageID, data, modifier) {
			var target = this.$('.work-area .composition-modificants-' + target);
			target.empty();
			if (data && data.length) {
				var pattern = '\
					<li class="composition-attachment-{%composition}">\
						<ul>\
							<li>[{%modifier}]\
								<a href="/pages?author={%author}">{%fio}</a> - <a href="/composition/id/{%composition}">{%title}</a>\
							</li>\
							<li><ul class="composition-pages"></ul></li>\
						</ul>\
					</li>';

				for (var i in data) {
					data[i]['modifier'] = patternize(modifier, data[i]);
					var compositionID = parseInt(data[i].composition);
					this.$('.composition-attachment-' + compositionID).remove();
					var innerDIV = $(patternize(pattern, data[i]));
					target.prepend(innerDIV);
					innerDIV.find('.composition-detouch').click(function() {compositerServant.detouchFrom($(this));});
					innerDIV.find('.composition-attach').click(function(event) {compositerServant.attachTo($(this));});
					var innerUL = innerDIV.find('.composition-pages');

					(function(innerUL, compositionID){
					this.json('composition-pages', {'id': compositionID
						, 'success': function (data) {
								for (var i in data) {
									var idx = parseInt(i), first = !idx, last = idx == data.length - 1;
									data[i]['composition'] = compositionID;
									data[i]['up'] = first ? '&nbsp; &nbsp;' : patternize(PATT_ORDERUP, data[i]);
									data[i]['down'] = last ? '&nbsp; &nbsp;' : patternize(PATT_ORDERDOWN, data[i]);
									data[i]['order'] = idx + 1;
									var page = $(patternize(PATT_PAGE, data[i]));
									innerUL.append(page);

									page.find('.composition-page-up').click(function(){compositerServant.reorder($(this), 1)});
									page.find('.composition-page-down').click(function(){compositerServant.reorder($(this), -1)});
								}

							}
						}
					);
					}).call(this, innerUL, compositionID);
				}
				target.prepend($('<li>' + header + ':</li>'));
			}
		}

		this.reorder = function (source, direction) {
			var pageID = parseInt(source.attr('page-id'));
			var compositionID = parseInt(source.attr('com-id'));

			this.json('composition-order', {'composition': compositionID, 'page': pageID, 'direction': direction
				, 'success': function (data) {
						compositerServant.show(summoner);
					}
				}
			);
		}

		this.detouchFrom = function (source) {
			summoner = source.parents('.composite-summoner');
			var pageID = parseInt(summoner.attr('data-id'));
			var compositionID = parseInt(source.attr('data-id'));

			this.json('composition-remove', {'composition': compositionID, 'pages': [pageID]
				, 'success': function (data) {
						compositerServant.show(summoner);
					}
				}
			);
		}

		this.attachTo = function (source) {
			summoner = source.parents('.composite-summoner');
			var pageID = parseInt(summoner.attr('data-id'));
			var compositionID = parseInt(source.attr('data-id'));

			this.json('composition-add', {'composition': compositionID, 'pages': [pageID]
				, 'success': function (data) {
						compositerServant.show(summoner);
					}
				}
			);
		}
		this.createComposition = function (source) {
			summoner = source.parents('.composite-summoner');
			var id = parseInt(summoner.attr('data-id'));
			var title = this.$('[type="text"]').val().trim();
			if (title == "") return;

			this.json('composition-add', {'composition': 0, 'pages': [id], 'title': title
				, 'success': function (data) {
						compositerServant.show(summoner);
					}
				}
			);
		}


		this.json = function(action, params, depth) {
			var original = params;
			var callbacks = {'success': params.success || function(x){}};
			var error = params.error || this.error;
			callbacks.error = (!params.retry) ? function(e,v){compositerServant.error(e,v);} : function (response, verbose) {
				depth = depth ? depth + 1 : 1;
				if (depth > AJAX_MAX_RETRIES)
					return error(response, verbose);
				compositerServant.json(action, original, depth);
			};

			delete(params.success);
			delete(params.error);
			delete(params.retry);
			$.getJSON('/api/admin/' + action, params)
			.success(function(response) {
				if (response.result != 'ok') {
					callbacks.error(response.data, true);
					return;
				}
				callbacks.success(response.data);
			})
			.error(function(response){
				callbacks.error(response.responseText);
			});
		}

		this.$ = function(css) {
			return summoner.find(css);
		}
		var errorTimeout = 0;
		this.error = function(message, verbose) {
			this.clearError();
			if (verbose)
				this.$('.error-div').html(message);
			else
				this.$('.error-div').html("Network error:\n" + message);

			errorTimeout = setTimeout(function(){compositerServant.clearError();}, 5000);
		}
		this.clearError = function() {
			if (errorTimeout)
				clearTimeout(errorTimeout);
			this.$('.error-div').html('');
		}
	}