/**
 * PressNative wp-admin live preview.
 * Fetches layout with optional overrides and renders a phone-frame preview.
 */
(function () {
	'use strict';

	var config = window.pressnativePreview || {};
	var restUrl = config.restUrl || '';
	var nonce = config.nonce || '';
	var debounceMs = 400;
	var debounceTimer = null;

	function getEl(id) {
		return document.getElementById(id);
	}

	function getVal(id) {
		var el = getEl(id);
		return el ? (el.value || '').trim() : '';
	}

	function getCheckedRadio(name) {
		var el = document.querySelector('input[name="' + name + '"]:checked');
		return el ? el.value : '';
	}

	/**
	 * Collect override params from the current page form (App Settings or Layout Settings).
	 * @param {string} page - 'app-settings' or 'layout-settings'
	 * @returns {Object}
	 */
	function collectFormOverrides(page) {
		var overrides = {};
		if (page === 'app-settings') {
			var themeId = getCheckedRadio('pressnative_theme_id');
			if (themeId) overrides.theme_id = themeId;
			var appName = getVal('pressnative_app_name');
			if (appName) overrides.app_name = appName;
			var primary = getVal('pressnative_primary_color');
			if (primary) overrides.primary_color = primary;
			var accent = getVal('pressnative_accent_color');
			if (accent) overrides.accent_color = accent;
			var bg = getVal('pressnative_background_color');
			if (bg) overrides.background_color = bg;
			var text = getVal('pressnative_text_color');
			if (text) overrides.text_color = text;
			var font = getVal('pressnative_font_family');
			if (font) overrides.font_family = font;
			var fontSize = getVal('pressnative_base_font_size');
			if (fontSize) overrides.base_font_size = parseInt(fontSize, 10);
			var logoId = getVal('pressnative_logo_attachment_id');
			if (logoId) overrides.logo_attachment = parseInt(logoId, 10);
		} else if (page === 'layout-settings') {
			var heroSlug = getVal('pressnative_hero_category_slug');
			if (heroSlug) overrides.hero_category_slug = heroSlug;
			var heroMax = getVal('pressnative_hero_max_items');
			if (heroMax) overrides.hero_max_items = parseInt(heroMax, 10);
			var gridCols = getVal('pressnative_post_grid_columns');
			if (gridCols) overrides.post_grid_columns = parseInt(gridCols, 10);
			var gridPer = getVal('pressnative_post_grid_per_page');
			if (gridPer) overrides.post_grid_per_page = parseInt(gridPer, 10);
			var orderEl = getEl('pressnative-component-order-value');
			if (orderEl && orderEl.value) {
				var components = orderEl.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
				
				// If WooCommerce is active, ensure WooCommerce components are included in preview
				if (window.pressnativePreview && window.pressnativePreview.woocommerceActive) {
					var wooComponents = ['product-grid', 'product-category-list', 'product-carousel'];
					wooComponents.forEach(function(comp) {
						if (components.indexOf(comp) === -1) {
							// Insert WooCommerce components in logical positions
							if (comp === 'product-grid' && components.indexOf('post-grid') !== -1) {
								// Insert product-grid after post-grid
								var postGridIndex = components.indexOf('post-grid');
								components.splice(postGridIndex + 1, 0, comp);
							} else if (comp === 'product-category-list' && components.indexOf('category-list') !== -1) {
								// Insert product-category-list after category-list
								var catListIndex = components.indexOf('category-list');
								components.splice(catListIndex + 1, 0, comp);
							} else if (comp === 'product-carousel' && components.indexOf('hero-carousel') !== -1) {
								// Insert product-carousel after hero-carousel
								var heroIndex = components.indexOf('hero-carousel');
								components.splice(heroIndex + 1, 0, comp);
							} else {
								// Fallback: add at the end
								components.push(comp);
							}
						}
					});
				}
				
				overrides.enabled_components = components;
			}
			var catCheckboxes = document.querySelectorAll('input[name="pressnative_enabled_categories[]"]:checked');
			if (catCheckboxes && catCheckboxes.length) {
				overrides.enabled_categories = Array.prototype.map.call(catCheckboxes, function (cb) { return parseInt(cb.value, 10); });
			}
		}
		return overrides;
	}

	/**
	 * POST to preview endpoint and return layout JSON.
	 * @param {Object} overrides
	 * @returns {Promise<Object>}
	 */
	function fetchPreview(overrides) {
		var url = restUrl.replace(/\/$/, '') + '/pressnative/v1/preview';
		return fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify(overrides || {})
		}).then(function (res) {
			if (!res.ok) throw new Error('Preview request failed: ' + res.status);
			return res.json();
		});
	}

	function escapeHtml(s) {
		if (s == null) return '';
		var div = document.createElement('div');
		div.textContent = s;
		return div.innerHTML;
	}

	function renderHeroCarousel(component) {
		var styles = component.styles || {};
		var colors = styles.colors || {};
		var pad = styles.padding || {};
		var items = (component.content && component.content.items) || [];
		if (items.length === 0) return '';

		var html = '<div class="pressnative-preview-component pressnative-hero" style="--pn-card-bg:' + escapeHtml(colors.background || '#fff') + ';--pn-card-text:' + escapeHtml(colors.text || '#111') + ';--pn-accent:' + escapeHtml(colors.accent || '#34c759') + ';padding:' + (pad.vertical || 16) + 'px ' + (pad.horizontal || 16) + 'px;">';
		html += '<div class="pressnative-hero-track">';
		items.forEach(function (item) {
			html += '<div class="pressnative-hero-card">';
			if (item.image_url) {
				html += '<img class="pressnative-hero-card-img" src="' + escapeHtml(item.image_url) + '" alt="" loading="lazy" />';
			}
			html += '<div class="pressnative-hero-card-body">';
			html += '<p class="pressnative-hero-card-title">' + escapeHtml(item.title || '') + '</p>';
			if (item.subtitle) html += '<p class="pressnative-hero-card-subtitle">' + escapeHtml(item.subtitle) + '</p>';
			html += '</div></div>';
		});
		html += '</div>';
		html += '<div class="pressnative-hero-dots">';
		items.forEach(function (_, i) {
			html += '<span class="pressnative-hero-dot' + (i === 0 ? ' active' : '') + '"></span>';
		});
		html += '</div></div>';
		return html;
	}

	function renderPostGrid(component) {
		var styles = component.styles || {};
		var colors = styles.colors || {};
		var pad = styles.padding || {};
		var content = component.content || {};
		var posts = content.posts || [];
		var cols = Math.min(4, Math.max(1, parseInt(content.columns, 10) || 2));

		var html = '<div class="pressnative-preview-component pressnative-postgrid pressnative-postgrid-cols-' + cols + '" style="--pn-card-bg:' + escapeHtml(colors.background || '#f6f7f9') + ';--pn-card-text:' + escapeHtml(colors.text || '#111') + ';padding:' + (pad.vertical || 16) + 'px ' + (pad.horizontal || 16) + 'px;">';
		html += '<div class="pressnative-postgrid-inner">';
		posts.forEach(function (post) {
			html += '<div class="pressnative-postgrid-card">';
			if (post.thumbnail_url) {
				html += '<img class="pressnative-postgrid-card-img" src="' + escapeHtml(post.thumbnail_url) + '" alt="" loading="lazy" />';
			}
			html += '<div class="pressnative-postgrid-card-body">';
			html += '<p class="pressnative-postgrid-card-title">' + escapeHtml(post.title || '') + '</p>';
			if (post.excerpt) html += '<p class="pressnative-postgrid-card-excerpt">' + escapeHtml(post.excerpt) + '</p>';
			html += '</div></div>';
		});
		html += '</div></div>';
		return html;
	}

	function renderCategoryList(component) {
		var styles = component.styles || {};
		var colors = styles.colors || {};
		var pad = styles.padding || {};
		var categories = (component.content && component.content.categories) || [];

		var html = '<div class="pressnative-preview-component pressnative-categories" style="--pn-card-bg:' + escapeHtml(colors.background || '#fff') + ';--pn-card-text:' + escapeHtml(colors.text || '#111') + ';padding:' + (pad.vertical || 12) + 'px ' + (pad.horizontal || 16) + 'px;">';
		html += '<div class="pressnative-categories-list">';
		categories.forEach(function (cat) {
			html += '<div class="pressnative-category-chip">';
			if (cat.icon_url) {
				html += '<img class="pressnative-category-chip-img" src="' + escapeHtml(cat.icon_url) + '" alt="" loading="lazy" />';
			}
			html += '<span class="pressnative-category-chip-name">' + escapeHtml(cat.name || '') + '</span>';
			html += '</div>';
		});
		html += '</div></div>';
		return html;
	}

	function renderPageList(component) {
		var styles = component.styles || {};
		var colors = styles.colors || {};
		var pad = styles.padding || {};
		var pages = (component.content && component.content.pages) || [];

		var html = '<div class="pressnative-preview-component pressnative-pages" style="--pn-card-bg:' + escapeHtml(colors.background || '#fff') + ';--pn-card-text:' + escapeHtml(colors.text || '#111') + ';padding:' + (pad.vertical || 12) + 'px ' + (pad.horizontal || 16) + 'px;">';
		html += '<div class="pressnative-pages-list">';
		pages.forEach(function (p) {
			html += '<div class="pressnative-page-chip">';
			if (p.icon_url) {
				html += '<img class="pressnative-page-chip-img" src="' + escapeHtml(p.icon_url) + '" alt="" loading="lazy" />';
			}
			html += '<span class="pressnative-page-chip-name">' + escapeHtml(p.name || '') + '</span>';
			html += '</div>';
		});
		html += '</div></div>';
		return html;
	}

	function renderAdPlacement(component) {
		var styles = component.styles || {};
		var colors = styles.colors || {};
		var pad = styles.padding || {};
		var content = component.content || {};
		var provider = (content.provider || 'admob').toUpperCase();
		var format = content.format || 'banner';

		var html = '<div class="pressnative-preview-component pressnative-ad" style="--pn-card-bg:' + escapeHtml(colors.background || '#f6f7f9') + ';--pn-card-text:' + escapeHtml(colors.text || '#111') + ';padding:' + (pad.vertical || 16) + 'px ' + (pad.horizontal || 16) + 'px;">';
		html += '<div class="pressnative-ad-banner">' + escapeHtml(provider) + ' ' + escapeHtml(format) + ' ad</div>';
		html += '</div>';
		return html;
	}

	function renderComponent(component) {
		var type = (component.type || '').toLowerCase();
		switch (type) {
			case 'herocarousel': return renderHeroCarousel(component);
			case 'postgrid': return renderPostGrid(component);
			case 'categorylist': return renderCategoryList(component);
			case 'pagelist': return renderPageList(component);
			case 'adplacement': return renderAdPlacement(component);
			default: return '';
		}
	}

	/**
	 * Render full layout into the preview container.
	 * @param {Object} layout - { branding, screen, components }
	 * @param {HTMLElement} container
	 */
	function renderLayout(layout, container) {
		if (!layout || !container) return;

		var branding = layout.branding || {};
		var theme = branding.theme || {};
		var screen = layout.screen || {};
		var components = layout.components || [];

		var bg = theme.background_color || '#ffffff';
		var text = theme.text_color || '#111111';
		var accent = theme.accent_color || '#34c759';
		var primary = theme.primary_color || '#1a1a1a';

		container.style.setProperty('--pn-preview-bg', bg);
		container.style.setProperty('--pn-preview-text', text);
		container.style.setProperty('--pn-accent', accent);
		container.style.setProperty('--pn-header-bg', primary);

		var toolbar = container.querySelector('.pressnative-preview-toolbar');
		var content = container.querySelector('.pressnative-preview-content');
		if (!content) return;

		if (toolbar) {
			var logoUrl = branding.logo_url || '';
			toolbar.innerHTML = '';
			if (logoUrl) {
				var img = document.createElement('img');
				img.className = 'pressnative-preview-toolbar-logo';
				img.src = logoUrl;
				img.alt = '';
				img.loading = 'lazy';
				toolbar.appendChild(img);
			}
			var title = document.createElement('span');
			title.className = 'pressnative-preview-toolbar-title';
			title.textContent = branding.app_name || screen.title || 'PressNative';
			toolbar.appendChild(title);
		}

		content.innerHTML = components.map(renderComponent).join('');
	}

	function showLoading(container) {
		var content = container ? container.querySelector('.pressnative-preview-content') : null;
		if (content) {
			content.innerHTML = '<div class="pressnative-preview-loading">Loading preview…</div>';
		}
	}

	function showError(container, message) {
		var content = container ? container.querySelector('.pressnative-preview-content') : null;
		if (content) {
			content.innerHTML = '<div class="pressnative-preview-error">' + escapeHtml(message || 'Failed to load preview') + '</div>';
		}
	}

	function refreshPreview() {
		var wrap = document.getElementById('pressnative-preview');
		if (!wrap) return;

		var page = wrap.getAttribute('data-page') || 'app-settings';
		var viewport = wrap.querySelector('.pressnative-preview-viewport');
		if (!viewport) return;

		showLoading(wrap);
		var overrides = collectFormOverrides(page);

		fetchPreview(overrides)
			.then(function (layout) {
				renderLayout(layout, wrap);
			})
			.catch(function (err) {
				showError(wrap, err && err.message ? err.message : 'Failed to load preview');
			});
	}

	function scheduleRefresh() {
		if (debounceTimer) clearTimeout(debounceTimer);
		debounceTimer = setTimeout(refreshPreview, debounceMs);
	}

	function bindDeviceSwitcher() {
		var frame = document.getElementById('pressnative-device-frame');
		var btns = document.querySelectorAll('.pressnative-device-btn');
		if (!frame || !btns.length) return;
		btns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var device = this.getAttribute('data-device');
				if (!device) return;
				frame.classList.remove('pressnative-device-iphone', 'pressnative-device-android');
				frame.classList.add('pressnative-device-' + device);
				btns.forEach(function (b) {
					var isActive = b.getAttribute('data-device') === device;
					b.classList.toggle('active', isActive);
					b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				});
			});
		});
	}

	function bindFormEvents() {
		var wrap = document.getElementById('pressnative-preview');
		if (!wrap) return;

		var form = document.querySelector('.pressnative-settings-form');
		if (form) {
			form.addEventListener('input', scheduleRefresh);
			form.addEventListener('change', scheduleRefresh);
		}

		// Layout page: sortable order changes (jQuery sortupdate)
		var list = document.getElementById('pressnative-component-order');
		if (list) {
			list.addEventListener('change', scheduleRefresh);
			list.addEventListener('sortupdate', scheduleRefresh);
		}
	}

	function init() {
		if (!restUrl || !nonce) return;

		var wrap = document.getElementById('pressnative-preview');
		if (!wrap) return;

		bindDeviceSwitcher();
		bindFormEvents();
		refreshPreview();
	}

	window.pressnativePreviewRefresh = refreshPreview;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
