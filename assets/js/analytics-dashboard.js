/**
 * PressNative Analytics Dashboard.
 * Fetches analytics data via REST API and renders KPIs, Chart.js charts, and tables.
 */
(function () {
	'use strict';

	var config = window.pressnativeAnalytics || {};
	var baseUrl = (config.restUrl || '').replace(/\/$/, '');
	var nonce = config.nonce || '';
	var charts = { viewsOverTime: null, contentType: null, device: null };

	function headers() {
		var h = { 'Content-Type': 'application/json' };
		if (nonce) h['X-WP-Nonce'] = nonce;
		return h;
	}

	function fetchApi(path) {
		return fetch(baseUrl + path, { credentials: 'same-origin', headers: headers() }).then(function (r) {
			if (!r.ok) throw new Error('Request failed: ' + r.status);
			return r.json();
		});
	}

	function getDays() {
		var sel = document.getElementById('pressnative-analytics-days');
		return sel ? parseInt(sel.value, 10) || 30 : 30;
	}

	function formatNumber(n) {
		return n >= 1000000 ? (n / 1000000).toFixed(1) + 'M' : n >= 1000 ? (n / 1000).toFixed(1) + 'K' : String(n);
	}

	function escapeHtml(s) {
		if (s == null || s === '') return '';
		var div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}

	// ——— KPIs ———
	function renderKpis(summary) {
		if (!summary || !summary.by_type) return;
		var kpis = document.getElementById('pressnative-analytics-kpis');
		if (!kpis) return;
		var byType = summary.by_type;
		var favoritesEl = kpis.querySelector('[data-kpi="favorites"]');
		var totalEl = kpis.querySelector('[data-kpi="total"]');
		var postEl = kpis.querySelector('[data-kpi="post"]');
		var pageEl = kpis.querySelector('[data-kpi="page"]');
		var categoryEl = kpis.querySelector('[data-kpi="category"]');
		var pushReceivedEl = document.querySelector('[data-kpi="push_received"]');
		var pushClickedEl = document.querySelector('[data-kpi="push_clicked"]');
		if (favoritesEl) favoritesEl.textContent = formatNumber(summary.favorites ?? 0);
		if (totalEl) totalEl.textContent = formatNumber(summary.total);
		if (pushReceivedEl) pushReceivedEl.textContent = formatNumber(summary.push_received ?? 0);
		if (pushClickedEl) pushClickedEl.textContent = formatNumber(summary.push_clicked ?? 0);
		if (postEl) postEl.textContent = formatNumber(byType.post || 0);
		if (pageEl) pageEl.textContent = formatNumber(byType.page || 0);
		if (categoryEl) categoryEl.textContent = formatNumber(byType.category || 0);
	}

	// ——— Charts ———
	function renderViewsOverTime(data) {
		var canvas = document.getElementById('pressnative-chart-views-over-time');
		if (!canvas || typeof Chart === 'undefined') return;
		if (charts.viewsOverTime) charts.viewsOverTime.destroy();
		var labels = (data || []).map(function (d) { return d.date; });
		var values = (data || []).map(function (d) { return d.views; });
		var maxVal = values.length ? Math.max.apply(null, values) : 1;
		charts.viewsOverTime = new Chart(canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: 'Views',
					data: values,
					borderColor: '#2271b1',
					backgroundColor: 'rgba(34, 113, 177, 0.1)',
					fill: true,
					tension: 0.2,
					pointRadius: labels.length <= 7 ? 4 : 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: { legend: { display: false } },
				scales: {
					y: {
						beginAtZero: true,
						ticks: { precision: 0 },
						suggestedMax: Math.ceil(maxVal * 1.2) || 5
					},
					x: { maxTicksLimit: Math.min(12, labels.length || 1) }
				}
			}
		});
	}

	function renderContentTypeChart(byType) {
		var canvas = document.getElementById('pressnative-chart-content-type');
		if (!canvas || typeof Chart === 'undefined') return;
		if (charts.contentType) charts.contentType.destroy();
		var labels = ['Home', 'Posts', 'Pages', 'Categories', 'Search'];
		var keys = ['home', 'post', 'page', 'category', 'search'];
		var values = keys.map(function (k) { return byType[k] || 0; });
		var hasData = values.some(function (v) { return v > 0; });
		if (!hasData) {
			labels = ['No data'];
			values = [1];
		}
		charts.contentType = new Chart(canvas.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: ['#2271b1', '#00a32a', '#d63638', '#dba617', '#6366f1'],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: { legend: { position: 'bottom' } }
			}
		});
	}

	function renderDeviceChart(byDevice) {
		var canvas = document.getElementById('pressnative-chart-device');
		if (!canvas || typeof Chart === 'undefined') return;
		if (charts.device) charts.device.destroy();
		var labels = ['iOS', 'Android', 'Other'];
		var keys = ['ios', 'android', 'unknown'];
		var values = keys.map(function (k) { return byDevice[k] || 0; });
		var hasData = values.some(function (v) { return v > 0; });
		if (!hasData) {
			labels = ['No data'];
			values = [1];
		}
		charts.device = new Chart(canvas.getContext('2d'), {
			type: 'doughnut',
			data: {
				labels: labels,
				datasets: [{
					data: values,
					backgroundColor: ['#007aff', '#3ddc84', '#8e8e93'],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							afterLabel: function (ctx) {
								if (ctx.dataIndex === 2 && ctx.raw > 0) {
									return '(emulators, uncategorized)';
								}
								return '';
							}
						}
					}
				}
			}
		});
	}

	// ——— Tables ———
	function renderTopPosts(rows) {
		var container = document.getElementById('pressnative-table-top-posts');
		if (!container) return;
		if (!rows || rows.length === 0) {
			container.innerHTML = '<p class="pressnative-table-empty">No post views in this period.</p>';
			return;
		}
		var editPostUrl = (config.editPostUrl || '').replace(/\/$/, '');
		var html = '<table class="pressnative-analytics-table"><thead><tr><th>Post</th><th>Views</th><th></th></tr></thead><tbody>';
		rows.forEach(function (r) {
			var title = escapeHtml(r.resource_title || '(ID: ' + r.resource_id + ')');
			var id = String(r.resource_id || '').trim();
			var isNumeric = /^\d+$/.test(id);
			var editLink = (editPostUrl && isNumeric) ? '<a href="' + escapeHtml(editPostUrl + '?post=' + encodeURIComponent(id) + '&action=edit') + '" class="button button-small">Edit</a>' : '';
			html += '<tr><td>' + title + '</td><td>' + formatNumber(r.views) + '</td><td>' + editLink + '</td></tr>';
		});
		html += '</tbody></table>';
		container.innerHTML = html;
	}

	function renderTopPages(rows) {
		var container = document.getElementById('pressnative-table-top-pages');
		if (!container) return;
		if (!rows || rows.length === 0) {
			container.innerHTML = '<p class="pressnative-table-empty">No page views in this period.</p>';
			return;
		}
		var html = '<table class="pressnative-analytics-table"><thead><tr><th>Page</th><th>Views</th></tr></thead><tbody>';
		rows.forEach(function (r) {
			var title = escapeHtml(r.resource_title || r.resource_id || '—');
			html += '<tr><td>' + title + '</td><td>' + formatNumber(r.views) + '</td></tr>';
		});
		html += '</tbody></table>';
		container.innerHTML = html;
	}

	function renderTopCategories(rows) {
		var container = document.getElementById('pressnative-table-top-categories');
		if (!container) return;
		if (!rows || rows.length === 0) {
			container.innerHTML = '<p class="pressnative-table-empty">No category views in this period.</p>';
			return;
		}
		var html = '<table class="pressnative-analytics-table"><thead><tr><th>Category</th><th>Views</th></tr></thead><tbody>';
		rows.forEach(function (r) {
			var title = escapeHtml(r.resource_title || r.resource_id || '—');
			html += '<tr><td>' + title + '</td><td>' + formatNumber(r.views) + '</td></tr>';
		});
		html += '</tbody></table>';
		container.innerHTML = html;
	}

	function renderTopSearches(rows) {
		var container = document.getElementById('pressnative-table-top-searches');
		if (!container) return;
		if (!rows || rows.length === 0) {
			container.innerHTML = '<p class="pressnative-table-empty">No searches in this period.</p>';
			return;
		}
		var html = '<table class="pressnative-analytics-table"><thead><tr><th>Query</th><th>Searches</th></tr></thead><tbody>';
		rows.forEach(function (r) {
			html += '<tr><td>' + escapeHtml(r.resource_id || '—') + '</td><td>' + formatNumber(r.views) + '</td></tr>';
		});
		html += '</tbody></table>';
		container.innerHTML = html;
	}

	// ——— Load all ———
	function loadDashboard() {
		var days = getDays();
		var q = '?days=' + days + '&limit=10';

		Promise.all([
			fetchApi('/analytics/summary' + '?days=' + days),
			fetchApi('/analytics/views-over-time' + '?days=' + days + '&group_by=day'),
			fetchApi('/analytics/top-posts' + q),
			fetchApi('/analytics/top-pages' + q),
			fetchApi('/analytics/top-categories' + q),
			fetchApi('/analytics/device-breakdown' + '?days=' + days),
			fetchApi('/analytics/top-searches' + q)
		]).then(function (results) {
			var summary = results[0];
			var viewsOverTime = results[1];
			var topPosts = results[2];
			var topPages = results[3];
			var topCategories = results[4];
			var deviceBreakdown = results[5];
			var topSearches = results[6];

			renderKpis(summary);
			renderViewsOverTime(viewsOverTime);
			renderContentTypeChart(summary.by_type || {});
			renderDeviceChart(deviceBreakdown || {});
			renderTopPosts(topPosts);
			renderTopPages(topPages);
			renderTopCategories(topCategories);
			renderTopSearches(topSearches);
		}).catch(function (err) {
			console.error('PressNative Analytics:', err);
			var kpis = document.getElementById('pressnative-analytics-kpis');
			if (kpis) kpis.innerHTML = '<p class="pressnative-analytics-error">Failed to load analytics. Check console.</p>';
		});
	}

	function init() {
		var daysEl = document.getElementById('pressnative-analytics-days');
		if (daysEl) daysEl.addEventListener('change', loadDashboard);
		loadDashboard();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
