/**
 * @file cypress/tests/functional/KeywordCloudClassicBeautiful.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: the keyword cloud a reader sees in the sidebar.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword (captcha on login
 * must be off for the run), pagePath (a reader page with the sidebar; default the
 * home page). The defaults match the data set of PKP's continuous integration.
 * The first test enables the plugin and places the block in the sidebar; both the
 * sidebar and the settings are put back as they were after the run.
 */

describe('Keyword Cloud (Classic) plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';
	const pagePath = Cypress.env('pagePath') || '';

	const block = 'keywordcloudclassicbeautifulblockplugin';
	const form = '#keywordCloudClassicBeautifulSettingsForm';
	const settingsUrl = () => pageUrl('$$$call$$$/grid/settings/plugins/settings-plugin-grid/manage') + '?verb=settings&plugin=' + block + '&category=blocks&save=1';
	let originalSidebar = null;
	let originalSettings = null;

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	// The Plugins tab can keep requests open for a while (the plugin gallery), hence the timeout.
	const waitJQuery = () => cy.window().its('jQuery.active', {timeout: 60000}).should('eq', 0);

	// Requests carry the browser's User-Agent: OJS 3.3 drops a session whose agent changes.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into), then
	// falls back to the form when the session did not stick (OJS 3.3 cookie handling).
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// REST API calls made from the page itself, so they carry the browser's own session.
	const api = (path, options = {}) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(path, Object.assign({credentials: 'same-origin'}, options)).then((response) => {
			if (!response.ok) {
				return response.text().then((text) => {
					throw new Error(path + ' answered ' + response.status + ': ' + text.slice(0, 300));
				});
			}
			return response.json();
		}),
		{log: false, timeout: 30000}
	));

	// The website settings page on its Plugins tab (a new query string forces a load). Load it
	// once per test: loading it again while its plugin gallery request is pending stalls the
	// web server of PKP's CI; API calls and settings modals work on the page already open.
	const openPluginsTab = () => {
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// Enables the plugin in the grid when it is off (never turns it off).
	const enablePlugin = (rowName) => {
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]').should('be.checked');
	};

	// Opens the settings modal from the grid, without reloading the page: a reload right
	// after saving can stall the web server of PKP's CI. The form is fetched each time.
	const openPluginSettings = (rowName, formSelector) => {
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]', {timeout: 30000}).then(($link) => {
			if (!$link.is(':visible')) {
				cy.get('tr[id$="-row-' + rowName + '"] a.show_extras').first().click();
			}
		});
		// The grid may still be animating the extras row: the link is clicked once it exists.
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]').first().click({force: true});
		waitJQuery();
		cy.window().should((win) => {
			expect(win.jQuery(formSelector).data('pkp.handler')).to.exist;
		});
	};

	// ---- end of helpers ----
	// The journal of contextPath with all its settings, and the CSRF token of the page.
	const withJournal = (callback) => {
		cy.window({timeout: 60000}).its('pkp.currentUser.csrfToken').then((token) => {
			api('/index.php/index/api/v1/contexts?count=100').then((list) => {
				const journal = list.items.find((item) => item.urlPath === contextPath);
				api(pageUrl('api/v1/contexts/' + journal.id)).then((details) => callback(details, token));
			});
		});
	};

	const saveSidebar = (journal, token, sidebar) => api(pageUrl('api/v1/contexts/' + journal.id), {
		method: 'PUT',
		headers: {'Content-Type': 'application/json', 'X-Csrf-Token': token},
		body: JSON.stringify({sidebar: sidebar}),
	});

	const postSettings = (fields) => cy.window({timeout: 60000}).its('pkp.currentUser.csrfToken').then((token) => request({
		method: 'POST',
		url: settingsUrl(),
		form: true,
		body: Object.assign({}, fields, {csrfToken: token}),
	}));

	const visitPage = () => {
		cy.clearCookies();
		cy.visit(pageUrl(pagePath) + '?reload=' + Date.now(), {headers: {Cookie: 'OJSSID=cypress' + Date.now()}});
	};

	it('Enables the plugin and places the block in the sidebar', function() {
		login(adminUser, adminPassword);
		openPluginsTab();
		enablePlugin(block);
		withJournal((journal, token) => {
			originalSidebar = journal.sidebar || [];
			if (!originalSidebar.includes(block)) {
				saveSidebar(journal, token, originalSidebar.concat([block]));
			}
		});
	});

	it('Draws the keywords as links to the search, with its own assets', function() {
		visitPage();
		cy.get('.block_keyword_cloud_classic', {timeout: 30000}).should('have.length', 1);
		cy.get('link[href*="/keywordCloudClassicBeautiful/css/keywordCloud.css"]').should('have.length', 1);
		cy.get('script[src*="/keywordCloudClassicBeautiful/lib/wordcloud2/wordcloud2.js"]').should('have.length', 1);
		cy.get('script[src*="/keywordCloudClassicBeautiful/js/keywordCloud.js"]').should('have.length', 1);
		// Nothing is fetched from a CDN.
		cy.get('.block_keyword_cloud_classic script, .block_keyword_cloud_classic style').should('have.length', 0);

		// Without JavaScript the block is a list of links; with it, a canvas is drawn.
		cy.get('.block_keyword_cloud_classic .ojsbrKwc__list a').should('have.length.at.least', 1).each(($link) => {
			expect($link.attr('href')).to.contain('search');
			expect($link.attr('href')).to.contain('query=');
			expect($link.text().trim()).to.not.eq('');
		});
		cy.get('.block_keyword_cloud_classic .ojsbrKwc__canvas').should('exist');
	});

	it('Shows no more keywords than the journal asked for', function() {
		login(adminUser, adminPassword);
		openPluginsTab();
		openPluginSettings(block, form);
		cy.window().then((win) => {
			if (originalSettings === null) {
				originalSettings = {};
				win.jQuery(form).serializeArray().filter((field) => field.name !== 'csrfToken').forEach((field) => {
					originalSettings[field.name] = field.value;
				});
			}
		});
		postSettings({numKeywords: 5, minFont: 12, maxFont: 48, size: 'large', heightPx: 0, rotation: 'diagonal', palette: 'soft', font: 'serif', sampleWhenEmpty: 1})
			.its('body.status').should('eq', true);

		visitPage();
		cy.get('.block_keyword_cloud_classic .ojsbrKwc__list a').should('have.length.at.most', 5);
	});

	// Puts the sidebar and the settings back as they were, also when a test failed.
	after(function() {
		if (originalSidebar === null && originalSettings === null) {
			return;
		}
		login(adminUser, adminPassword);
		openPluginsTab();
		if (originalSettings !== null) {
			postSettings(originalSettings);
		}
		if (originalSidebar !== null && !originalSidebar.includes(block)) {
			withJournal((journal, token) => saveSidebar(journal, token, originalSidebar));
		}
	});
});
