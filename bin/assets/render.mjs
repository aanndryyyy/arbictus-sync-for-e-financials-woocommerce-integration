/**
 * Renders the WordPress.org icon and banner PNGs from the HTML sources in this
 * directory into .wordpress-org/.
 *
 * Usage: node bin/assets/render.mjs
 */
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const out = path.resolve( here, '../../.wordpress-org' );

const targets = [
	{ src: 'mark.html', file: 'icon-256x256.png', w: 512, h: 512, scale: 0.5 },
	{ src: 'mark.html', file: 'icon-128x128.png', w: 512, h: 512, scale: 0.25 },
	{ src: 'banner.html', file: 'banner-1544x500.png', w: 1544, h: 500, scale: 1 },
	{ src: 'banner.html', file: 'banner-772x250.png', w: 1544, h: 500, scale: 0.5 },
];

const browser = await chromium.launch();

for ( const target of targets ) {
	const page = await browser.newPage( {
		viewport: { width: target.w, height: target.h },
		deviceScaleFactor: target.scale,
	} );

	await page.goto( 'file://' + path.join( here, target.src ) );
	// Webfonts in the banner need a beat before the paint is final.
	await page.evaluate( () => document.fonts.ready );
	await page.waitForTimeout( 400 );
	await page.screenshot( { path: path.join( out, target.file ) } );
	await page.close();

	console.log( 'wrote', target.file, `${ Math.round( target.w * target.scale ) }x${ Math.round( target.h * target.scale ) }` );
}

await browser.close();
