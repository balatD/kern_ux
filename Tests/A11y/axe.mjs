/**
 * Runs axe-core against the component gallery, or against any number of pages.
 *
 * Two targets, for two different questions. The gallery is dumped as a static
 * directory first (kern-ux:styleguide:dump), so it needs no web server and no database
 * and can run in CI - it answers "is every component, in every documented state,
 * accessible on its own". A live site answers the question the gallery cannot: whether
 * the elements are still accessible *together* - heading order over a whole page,
 * duplicate landmarks, contrast in a real layout.
 *
 * Usage:
 *   node axe.mjs                                  # the dumped gallery
 *   node axe.mjs path/to/index.html               # a specific dump
 *   node axe.mjs https://host/a https://host/b    # live pages
 *   node axe.mjs --sitemap https://host/ a b c    # a base plus paths
 */
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { existsSync } from 'node:fs';

/**
 * KERN states it meets at least BITV 2.0 level AA, which reaches WCAG 2.1 AA through
 * EN 301 549, and says it additionally tests against WCAG 2.2. We check that set.
 */
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const args = process.argv.slice(2);
let targets = args;
if (args[0] === '--sitemap') {
    const base = args[1].replace(/\/$/, '');
    targets = args.slice(2).map((path) => `${base}/${path.replace(/^\//, '')}`);
}
if (targets.length === 0) {
    targets = ['../../var/styleguide/index.html'];
}

const urls = targets.map((target) => {
    if (/^https?:\/\//.test(target)) {
        return target;
    }
    const absolute = resolve(target);
    if (!existsSync(absolute)) {
        console.error(`Not found: ${absolute}`);
        console.error('For the gallery run: vendor/bin/typo3 kern-ux:styleguide:dump --target=var/styleguide');
        process.exit(2);
    }
    return pathToFileURL(absolute).href;
});

const browser = await chromium.launch();
// @axe-core/playwright requires a page created through an explicit context.
const context = await browser.newContext({ viewport: { width: 1280, height: 1024 } });
const page = await context.newPage();

let passes = 0;
let incomplete = 0;
const failures = [];
const pageErrors = [];
const unstyled = [];

for (const url of urls) {
    const errors = [];
    const onError = (error) => errors.push(error.message);
    page.on('pageerror', onError);

    await page.goto(url, { waitUntil: 'load' });

    // Fail loudly if the stylesheet never arrived: without it axe silently skips every
    // contrast and visibility rule, and the run would pass for the wrong reason.
    const styled = await page.evaluate(
        () => getComputedStyle(document.body).fontFamily.toLowerCase().includes('fira'),
    );
    if (!styled) {
        unstyled.push(url);
    }

    const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
    passes += results.passes.length;
    incomplete += results.incomplete.length;
    for (const violation of results.violations) {
        failures.push({ url, violation });
    }
    if (errors.length > 0) {
        pageErrors.push({ url, errors });
    }

    page.off('pageerror', onError);
    process.stdout.write(failures.length > 0 ? 'x' : '.');
}

await browser.close();
process.stdout.write('\n');

console.log(`Pages:      ${urls.length}`);
console.log(`Rules:      ${TAGS.join(', ')}`);
console.log(`Passes:     ${passes}`);
console.log(`Incomplete: ${incomplete}`);
console.log(`Violations: ${failures.length}`);

for (const { url, errors } of pageErrors) {
    console.error(`\nJavaScript errors on ${url}:\n  ${errors.join('\n  ')}`);
}

for (const { url, violation } of failures) {
    console.error(`\n[${violation.impact}] ${violation.id} - ${violation.help}`);
    console.error(`  ${url}`);
    console.error(`  ${violation.helpUrl}`);
    for (const node of violation.nodes.slice(0, 5)) {
        console.error(`  → ${node.target.join(' ')}`);
        console.error(`    ${node.html.replaceAll(/\s+/g, ' ').slice(0, 160)}`);
    }
    if (violation.nodes.length > 5) {
        console.error(`  … and ${violation.nodes.length - 5} more`);
    }
}

if (unstyled.length > 0) {
    console.error(`\nKERN stylesheet did not load on:\n  ${unstyled.join('\n  ')}`);
    console.error('Contrast rules would be skipped, so this counts as a failure.');
    process.exit(1);
}

process.exit(failures.length > 0 || pageErrors.length > 0 ? 1 : 0);
