// Browser tests: every page in light and dark themes, the phone menu, the theme button, tables and the export menu.
// Run by tests/run.sh, which sets BASE_URL. ADMIN_PASSWORD is the admin password set by the Python tests.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8080/';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'TestAdmin123';
let browser;
const errors = [];

before(async () => {
	browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
});
after(async () => {
	await browser?.close();
});

// A logged in page, recording any JavaScript errors
async function loggedIn(options = {}) {
	// A fixed language, as browsers on some systems report one which isn't valid (such as en-US@posix)
	const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, locale: 'en-US', ...options });
	const page = await context.newPage();
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
	await page.goto(BASE + 'login.php');
	await page.fill('input[name=username]', 'admin');
	await page.fill('input[name=password]', ADMIN_PASSWORD);
	await page.click('button[type=submit]');
	await page.waitForLoadState('networkidle');
	assert.match(page.url(), /index\.php$/, 'logged in');
	return page;
}

const PAGES = ['index.php', 'users.php', 'logs.php', 'api.php', 'add-contact.php', 'add-user.php', 'add-api.php', 'import.php', 'logout.php'];

for (const scheme of ['light', 'dark']) {
	test(`every page in the ${scheme} theme`, async () => {
		const page = await loggedIn({ colorScheme: scheme });
		assert.equal(await page.evaluate(() => document.documentElement.dataset.bsTheme), scheme);
		for (const path of PAGES) {
			await page.goto(BASE + path);
			await page.waitForLoadState('networkidle');
			assert.equal(await page.evaluate(() => document.documentElement.dataset.bsTheme), scheme, path);
			assert.doesNotMatch(await page.content(), /(Warning|Deprecated|Fatal error)<\/b>:/, path);
		}
		await page.context().close();
	});
}

test('the contacts table can be searched', async () => {
	const page = await loggedIn();
	const rows = await page.locator('#contacts tbody tr').count();
	assert.ok(rows >= 1);
	await page.locator('input[type=search]').fill('zzzz-no-such-contact');
	await page.waitForTimeout(300);
	assert.match(await page.locator('#contacts tbody').innerText(), /No matching records/);
	await page.context().close();
});

test('the logs table loads entries from the server', async () => {
	const page = await loggedIn();
	await page.goto(BASE + 'logs.php');
	await page.waitForSelector('#logs tbody tr td');
	const firstRow = await page.locator('#logs tbody tr').first().innerText();
	assert.match(firstRow, /\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/);
	assert.match(await page.locator('.dt-info').innerText(), /of [\d,]+ entries/);
	await page.context().close();
});

test('the export menu offers CSV and vCard downloads', async () => {
	const page = await loggedIn();
	await page.click('button:has-text("Export")');
	const csv = page.locator('a.dropdown-item:has-text("CSV")');
	assert.ok(await csv.isVisible());
	const [download] = await Promise.all([page.waitForEvent('download'), csv.click()]);
	assert.match(download.suggestedFilename(), /^address-book-\d{4}-\d{2}-\d{2}\.csv$/);
	await page.context().close();
});

test('the theme button cycles Auto, Dark and Light and is remembered', async () => {
	const page = await loggedIn({ colorScheme: 'dark' });
	const theme = () => page.evaluate(() => [document.documentElement.dataset.bsTheme, document.documentElement.dataset.themeChoice]);
	assert.deepEqual(await theme(), ['dark', 'auto']);
	await page.click('.navbar-theme');
	assert.deepEqual(await theme(), ['dark', 'dark']);
	await page.click('.navbar-theme');
	assert.deepEqual(await theme(), ['light', 'light']);
	await page.goto(BASE + 'users.php');
	assert.deepEqual(await theme(), ['light', 'light']);
	await page.click('.navbar-theme');
	assert.deepEqual(await theme(), ['dark', 'auto']);
	// Auto follows the device setting while the page is open
	await page.emulateMedia({ colorScheme: 'light' });
	await page.waitForFunction(() => document.documentElement.dataset.bsTheme === 'light', null, { timeout: 2000 });
	await page.context().close();
});

for (const scheme of ['light', 'dark']) {
	test(`the phone menu opens and works in the ${scheme} theme`, async () => {
		const page = await loggedIn({ viewport: { width: 390, height: 844 }, colorScheme: scheme, isMobile: true, hasTouch: true });
		assert.equal(await page.locator('#navbar').isVisible(), false);
		await page.tap('.navbar-toggler');
		await page.waitForTimeout(500);
		assert.ok(await page.locator('a.nav-link:has-text("Users")').isVisible());
		await Promise.all([page.waitForURL(/users\.php/), page.tap('a.nav-link:has-text("Users")')]);
		// Pages don't scroll sideways on a phone
		for (const path of ['index.php', 'users.php', 'logs.php', 'api.php', 'add-contact.php', 'import.php']) {
			await page.goto(BASE + path);
			await page.waitForLoadState('networkidle');
			assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, path);
		}
		await page.context().close();
	});
}

test('the log out button logs out', async () => {
	const page = await loggedIn();
	await page.click('button.navbar-logout');
	await page.waitForLoadState('networkidle');
	assert.match(page.url(), /login\.php$/);
	await page.context().close();
});

test('no JavaScript errors on any page', () => {
	assert.deepEqual(errors, []);
});
