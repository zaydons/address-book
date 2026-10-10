// Takes the screenshots in the screenshots/ directory and SCREENSHOTS.md, of a new system filled with example contacts.
// Run by tests/screenshots.sh, which sets BASE_URL and OUTPUT and starts the system with an empty database.
import { chromium } from 'playwright';
import path from 'node:path';

const BASE = process.env.BASE_URL;
const OUTPUT = process.env.OUTPUT;
const PASSWORD = 'Screenshots123';
const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});

async function newPage(scheme, options = {}) {
	const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, locale: 'en-US', colorScheme: scheme, ...options });
	return context.newPage();
}

async function submit(page) {
	await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type=submit][name=submit]')]);
}

async function logIn(page, password = PASSWORD) {
	await page.goto(BASE + 'login.php');
	await page.fill('input[name=username]', 'admin');
	await page.fill('input[name=password]', password);
	await submit(page);
}

async function shot(page, name, url) {
	if (url) {
		await page.goto(BASE + url);
	}
	await page.waitForLoadState('networkidle');
	await page.screenshot({ path: path.join(OUTPUT, name + '.png'), fullPage: true });
	console.log('  ' + name);
}

// The first login, which asks for a new password, then the example data
const setup = await newPage('light');
await setup.goto(BASE + 'login.php');
await shot(setup, 'login');
await logIn(setup, 'LetMeIn123');
await shot(setup, 'change-password');
await setup.fill('input[name=current_password]', 'LetMeIn123');
await setup.fill('input[name=password]', PASSWORD);
await setup.fill('input[name=confirm_password]', PASSWORD);
await submit(setup);

await setup.goto(BASE + 'import.php');
await setup.setInputFiles('input[name=file]', path.resolve('../fixtures/screenshots.csv'));
await submit(setup);
await shot(setup, 'contacts-import');

await setup.goto(BASE + 'add-user.php');
await setup.fill('input[name=full_name]', 'Jane Smith');
await setup.fill('input[name=username]', 'jsmith');
await setup.fill('input[name=password]', 'Example12345');
await setup.fill('input[name=confirm_password]', 'Example12345');
await submit(setup);

await setup.goto(BASE + 'add-api.php');
await setup.fill('input[name=cosmetic_name]', 'Phone system');
await submit(setup);

// Links to one contact, user and API token
await setup.goto(BASE + 'index.php');
const contact = await setup.locator('#contacts a[href*="view-contact.php?i="]').first().getAttribute('href');
const contactId = new URL(contact, BASE).searchParams.get('i');
await setup.goto(BASE + 'users.php');
const user = await setup.locator('a[href*="update-user.php?i="]').last().getAttribute('href');
const userId = new URL(user, BASE).searchParams.get('i');
await setup.goto(BASE + 'api.php');
const api = await setup.locator('a[href*="update-api.php?i="]').first().getAttribute('href');
const apiId = new URL(api, BASE).searchParams.get('i');
await setup.context().close();

const PAGES = [
	['contacts-table', 'index.php'],
	['contacts-view', 'view-contact.php?i=' + contactId],
	['contacts-add', 'add-contact.php'],
	['contacts-update', 'update-contact.php?i=' + contactId],
	['contacts-delete', 'delete-contact.php?i=' + contactId],
	['users-table', 'users.php'],
	['users-add', 'add-user.php'],
	['users-update', 'update-user.php?i=' + userId],
	['users-delete', 'delete-user.php?i=' + userId],
	['logs-table', 'logs.php'],
	['api-table', 'api.php'],
	['api-add', 'add-api.php'],
	['api-update', 'update-api.php?i=' + apiId],
	['api-delete', 'delete-api.php?i=' + apiId],
];
const DARK = ['contacts-table', 'contacts-view', 'contacts-update', 'logs-table'];

for (const scheme of ['light', 'dark']) {
	console.log(scheme);
	const page = await newPage(scheme);
	await logIn(page);
	for (const [name, url] of PAGES) {
		if (scheme === 'light' || DARK.includes(name)) {
			await shot(page, scheme === 'light' ? name : name + '-dark', url);
		}
	}
	await page.context().close();

	// A phone, with the menu open
	const phone = await newPage(scheme, { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
	await logIn(phone);
	await phone.tap('.navbar-toggler');
	await phone.waitForTimeout(500);
	await phone.screenshot({ path: path.join(OUTPUT, 'phone-menu' + (scheme === 'dark' ? '-dark' : '') + '.png') });
	console.log('  phone-menu');
	await phone.context().close();
}

await browser.close();
