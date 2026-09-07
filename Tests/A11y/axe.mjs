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
 *
 * 'best-practice' is in there for one specific reason: heading-order, region,
 * landmark-unique, landmark-one-main, page-has-heading-one and skip-link carry *no*
 * wcag tag in axe-core, only this one. Those six are exactly what the full-page run
 * exists to check, so without it the run answered a different question than it claimed
 * to. Everything it adds beyond them is a real finding worth triaging - the fix is
 * never to narrow the tags again.
 */
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

/**
 * One page is not one test. Contrast depends on the theme, and target-size and reflow
 * depend on the viewport, so a single 1280px light-mode pass silently leaves the dark
 * theme and the whole mobile layout - including the navigation panel, which only exists
 * below the flyout breakpoint - unmeasured.
 */
const PASSES = [
    { name: 'desktop light', viewport: { width: 1280, height: 1024 }, theme: null },
    { name: 'desktop dark', viewport: { width: 1280, height: 1024 }, theme: 'dark' },
    { name: 'mobile light', viewport: { width: 390, height: 844 }, theme: null },
];

const args = process.argv.slice(2);
let targets = args;
if (args[0] === '--sitemap') {
    if (args.length < 2) {
        console.error('--sitemap needs a base URL: node axe.mjs --sitemap https://host/ path …');
        process.exit(2);
    }
    const base = args[1].replace(/\/$/, '');
    targets = args.slice(2).map((path) => `${base}/${path.replace(/^\//, '')}`);
    if (targets.length === 0) {
        targets = [base + '/'];
    }
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

let passes = 0;
const failures = [];
const incompletes = [];
const pageErrors = [];
const unstyled = [];

for (const pass of PASSES) {
    // A context per pass, and a page per URL: one shared page would carry
    // localStorage, focus and the theme attribute from the previous document.
    const context = await browser.newContext({ viewport: pass.viewport });

    for (const url of urls) {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));

        await page.goto(url, { waitUntil: 'load' });

        if (pass.theme !== null) {
            await page.evaluate((theme) => document.body.setAttribute('data-kern-theme', theme), pass.theme);
        }

        // Fail loudly if the stylesheet never arrived: without it axe silently skips
        // every contrast and visibility rule, and the run would pass for the wrong
        // reason.
        const styled = await page.evaluate(
            () => getComputedStyle(document.body).fontFamily.toLowerCase().includes('fira'),
        );
        if (!styled) {
            unstyled.push(`${url} (${pass.name})`);
        }

        const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
        passes += results.passes.length;

        for (const violation of results.violations) {
            failures.push({ url, pass: pass.name, violation });
        }
        for (const item of results.incomplete) {
            incompletes.push({ url, pass: pass.name, violation: item });
        }
        if (errors.length > 0) {
            pageErrors.push({ url, pass: pass.name, errors });
        }

        // This page's own result, not the running total - the marker used to turn to x
        // for every page after the first failure.
        process.stdout.write(results.violations.length > 0 ? 'x' : '.');
        await page.close();
    }

    await context.close();
}

await browser.close();
process.stdout.write('\n');

console.log(`Pages:      ${urls.length} × ${PASSES.length} passes (${PASSES.map((p) => p.name).join(', ')})`);
console.log(`Rules:      ${TAGS.join(', ')}`);
console.log(`Passes:     ${passes}`);
console.log(`Incomplete: ${incompletes.length}`);
console.log(`Violations: ${failures.length}`);

/**
 * @param {{url: string, pass: string, violation: any}} entry
 */
function report(entry, label) {
    const { url, pass, violation } = entry;
    console.error(`\n[${label}${violation.impact ? ' ' + violation.impact : ''}] ${violation.id} - ${violation.help}`);
    console.error(`  ${url} (${pass})`);
    console.error(`  ${violation.helpUrl}`);
    for (const node of violation.nodes.slice(0, 5)) {
        console.error(`  → ${node.target.join(' ')}`);
        console.error(`    ${node.html.replaceAll(/\s+/g, ' ').slice(0, 160)}`);
    }
    if (violation.nodes.length > 5) {
        console.error(`  … and ${violation.nodes.length - 5} more`);
    }
}

for (const { url, pass, errors } of pageErrors) {
    console.error(`\nJavaScript errors on ${url} (${pass}):\n  ${errors.join('\n  ')}`);
}

for (const entry of failures) {
    report(entry, 'violation');
}

// Printed, not just counted. An aria-controls pointing at an id that does not exist
// lands here rather than in violations, so a silent count hides exactly the kind of
// defect that is cheap to fix and invisible in review.
for (const entry of incompletes) {
    report(entry, 'incomplete');
}

if (unstyled.length > 0) {
    console.error(`\nKERN stylesheet did not load on:\n  ${unstyled.join('\n  ')}`);
    console.error('Contrast rules would be skipped, so this counts as a failure.');
    process.exit(1);
}

process.exit(failures.length > 0 || pageErrors.length > 0 ? 1 : 0);
