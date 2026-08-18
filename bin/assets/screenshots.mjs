/**
 * Captures the WordPress.org screenshots from the running wp-env site.
 *
 * Seed the site first:
 *   npx wp-env run cli wp eval-file \
 *     wp-content/plugins/<dir>/bin/assets/seed-screenshots.php
 *
 * Then: node bin/assets/screenshots.mjs
 */
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const out = path.resolve( here, '../../.wordpress-org' );
const base = process.env.WP_BASE_URL || 'http://localhost:8888';

const browser = await chromium.launch();
const context = await browser.newContext( {
	viewport: { width: 1280, height: 1000 },
	deviceScaleFactor: 2,
} );
const page = await context.newPage();

// Log in.
await page.goto( `${ base }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'password' );
await page.click( '#wp-submit' );
await page.waitForURL( /wp-admin/ );

// Collapse the admin menu so the plugin UI, not WordPress chrome, fills the shot.
await page.goto( `${ base }/wp-admin/` );
await page.evaluate( () => {
	document.cookie = 'wp-settings-1=mfold%3Df; path=/';
} );

const shots = [
	{
		file: 'screenshot-1.png',
		url: '/wp-admin/admin.php?page=wc-settings&tab=integration&section=efinancials_integration',
		clip: '#mainform',
		// The settings form is very tall; the API and invoicing sections are the
		// part worth showing on the plugin page.
		maxHeight: 1180,
	},
	{
		file: 'screenshot-2.png',
		url: '/wp-admin/admin.php?page=wc-orders',
		clip: '.wp-list-table',
	},
	{
		file: 'screenshot-3.png',
		url: null, // resolved below: the first synced order's edit screen.
		clip: '#order_data',
		maxHeight: 900,
	},
];

// Find an order that has an e-Financials invoice so the metabox has content.
await page.goto( `${ base }/wp-admin/admin.php?page=wc-orders` );
const orderHref = await page
	.locator( 'a.order-view' )
	.first()
	.getAttribute( 'href' );
shots[ 2 ].url = orderHref.replace( base, '' );

for ( const shot of shots ) {
	await page.goto( base + shot.url );
	await page.waitForLoadState( 'networkidle' );

	// Notices and the screen-meta bar are noise in a store screenshot.
	await page.evaluate( () => {
		document
			.querySelectorAll(
				'.notice, .updated, .error, #wpfooter, #screen-meta, #screen-meta-links, #wp-admin-bar-wp-logo'
			)
			.forEach( ( el ) => el.remove() );
	} );

	const target = page.locator( shot.clip ).first();

	if ( shot.maxHeight ) {
		// boundingBox() is viewport-relative; pin the page to the top so those
		// coordinates are also page coordinates for the full-page clip below.
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		await page.waitForTimeout( 400 );

		const box = await target.boundingBox();

		await page.screenshot( {
			path: path.join( out, shot.file ),
			fullPage: true,
			clip: { ...box, height: Math.min( box.height, shot.maxHeight ) },
		} );
	} else {
		await target.scrollIntoViewIfNeeded();
		await page.waitForTimeout( 400 );
		await target.screenshot( { path: path.join( out, shot.file ) } );
	}
	console.log( 'wrote', shot.file, '<-', shot.url );
}

await browser.close();
